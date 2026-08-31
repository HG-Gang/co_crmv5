<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/02
 * Time: 01:46
 */

/**
 * 前台支付回调状态机闭环测试。
 *
 * 文件功能：
 * - 验证 PaymentCallbackService 对充值回调的状态机：pending、success、failed、refunded
 *   之间的合法迁移与幂等性。
 * - 验证成功回调创建且仅创建一条 pending 结算 outbox，并在持久化后仅派发一次
 *   SettleDepositPayment 任务。
 * - 验证失败为终态不可回退；成功可迁移到 refunded；结算前退款取消充值，
 *   已结算退款创建一条 pending 退款 outbox，处理中退款被阻塞。
 * - 验证回调身份/金额/币种不匹配、重复回调载荷哈希保持、通知路由的 ACK/400/422/500 行为。
 *
 * 适用场景：
 * - 前台支付回调处理与通知路由的回归测试，防止状态回退、重复结算与越权回调。
 *
 * 入参例子：
 * - PaymentCallback：gateway_code、local_order_no、provider_order_no、status、amount、
 *   currency、merchant_id、payload_hash。
 * - POST /api/front/payment/notify/{gateway}：渠道签名与回调载荷。
 *
 * 返回值：
 * - 成功回调后 payment_status=success、settlement_status=pending，outbox 一条 pending。
 * - 通知路由按场景返回 200（TASK5_ACK）/ 400 / 422 / 500。
 *
 * 异常或失败场景：
 * - 结算前退款、身份/金额/币种不匹配、渠道订单号不一致时抛出 InvalidArgumentException。
 */

declare(strict_types=1);

namespace Tests\Feature;

use App\Contracts\PaymentGatewayAdapter;
use App\Jobs\SettleDepositPayment;
use App\Jobs\RefundDepositPayment;
use App\Models\DepositRecord;
use App\Models\PaymentChannel;
use App\Models\PaymentSettlementOutbox;
use App\Services\Payment\PaymentCallback;
use App\Services\Payment\PaymentCallbackService;
use App\Services\Payment\PaymentGatewayRegistry;
use App\Services\Payment\PaymentOrderResult;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Queue;
use InvalidArgumentException;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

class FrontPaymentCallbackStateMachineClosureModuleTest extends TestCase
{
    /**
     * 回调状态机用例的固定订单号（DEP-CALLBACK-TASK5-1001）。夹具据此建单，
     * 断言回调把订单沿状态机推进且清理按它定位。
     * @var string
     */
    private const ORDER_NO = 'DEP-CALLBACK-TASK5-1001';

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
        $this->cleanup();
    }

    protected function tearDown(): void
    {
        $this->cleanup();
        parent::tearDown();
    }

    // 验证已验签成功回调更新支付状态并创建一条 pending 结算 outbox。
    public function test_verified_success_updates_payment_state_and_creates_one_pending_outbox(): void
    {
        $service = app(PaymentCallbackService::class);
        $order = $this->createOrder();

        $processed = $service->handle($this->paymentCallback());

        $processed->refresh();
        $this->assertSame($order->id, $processed->id);
        $this->assertSame('success', $processed->payment_status);
        $this->assertSame('pending', $processed->settlement_status);
        $this->assertSame('01', $processed->status);
        $this->assertSame('PROVIDER-CALLBACK-1001', $processed->channel_order_no);
        $this->assertSame(hash('sha256', 'verified-callback-payload'), $processed->provider_payload_hash);
        $this->assertNotNull($processed->payment_time);
        $this->assertDatabaseHas('payment_settlement_outbox', [
            'deposit_record_id' => $processed->id,
            'local_order_no' => self::ORDER_NO,
            'event_type' => 'deposit_settlement',
            'status' => 'pending',
            'attempts' => 0,
        ]);
    }

    // 验证重复成功回调幂等且不创建第二条 outbox。
    public function test_duplicate_success_is_idempotent_and_does_not_create_a_second_outbox(): void
    {
        $service = app(PaymentCallbackService::class);
        $this->createOrder();
        $service->handle($this->paymentCallback());

        $service->handle($this->paymentCallback());

        $this->assertSame(1, DB::table('payment_settlement_outbox')->where('local_order_no', self::ORDER_NO)->count());
        $this->assertSame('success', DepositRecord::where('local_order_no', self::ORDER_NO)->value('payment_status'));
    }

    // 验证首次成功在持久化后仅派发一次结算任务。
    public function test_first_success_dispatches_settlement_once_after_persistence(): void
    {
        Queue::fake();
        $service = app(PaymentCallbackService::class);
        $this->createOrder();

        $service->handle($this->paymentCallback());
        $service->handle($this->paymentCallback());

        $this->assertSame(1, DB::table('payment_settlement_outbox')->where('local_order_no', self::ORDER_NO)->count());
        Queue::assertPushed(SettleDepositPayment::class, function (SettleDepositPayment $job): bool {
            return $job->afterCommit === true;
        });
        Queue::assertPushed(SettleDepositPayment::class, 1);
    }

    // 验证首次成功派发后不再回查 outbox 的 event_type。
    public function test_first_success_dispatch_does_not_requery_outbox_event_type_after_commit(): void
    {
        $service = app(PaymentCallbackService::class);
        $this->createOrder();
        $queries = [];
        DB::listen(function ($query) use (&$queries): void {
            $queries[] = strtolower($query->sql);
        });

        $service->handle($this->paymentCallback());

        $eventTypeReads = array_filter($queries, function (string $sql): bool {
            return strpos($sql, 'select `event_type`') !== false
                && strpos($sql, 'payment_settlement_outbox') !== false;
        });
        $this->assertSame([], array_values($eventTypeReads));
    }

    // 验证成功后的失败回调不能回退支付状态或新增 outbox。
    public function test_failure_after_success_cannot_regress_payment_or_create_more_outbox_rows(): void
    {
        $service = app(PaymentCallbackService::class);
        $this->createOrder();
        $service->handle($this->paymentCallback());

        $service->handle($this->paymentCallback(['status' => 'failed']));

        $order = DepositRecord::where('local_order_no', self::ORDER_NO)->firstOrFail();
        $this->assertSame('success', $order->payment_status);
        $this->assertSame(1, DB::table('payment_settlement_outbox')->where('deposit_record_id', $order->id)->count());
    }

    // 验证失败为终态，后续成功回调不能复活支付。
    public function test_failed_payment_is_terminal_and_cannot_be_revived_by_later_success(): void
    {
        $service = app(PaymentCallbackService::class);
        $this->createOrder();
        $service->handle($this->paymentCallback(['status' => 'failed']));

        $service->handle($this->paymentCallback(['status' => 'success']));

        $order = DepositRecord::where('local_order_no', self::ORDER_NO)->firstOrFail();
        $this->assertSame('failed', $order->payment_status);
        $this->assertSame(0, DB::table('payment_settlement_outbox')->where('deposit_record_id', $order->id)->count());
    }

    // 验证 pending 回调为空操作。
    public function test_pending_callback_is_a_no_op(): void
    {
        $service = app(PaymentCallbackService::class);
        $this->createOrder();

        $service->handle($this->paymentCallback(['status' => 'pending']));

        $order = DepositRecord::where('local_order_no', self::ORDER_NO)->firstOrFail();
        $this->assertSame('pending', $order->payment_status);
        $this->assertSame('', (string) $order->channel_order_no);
        $this->assertSame(0, DB::table('payment_settlement_outbox')->where('deposit_record_id', $order->id)->count());
    }

    // 验证成功前的退款回调被拒绝且无任何变更。
    public function test_refund_before_success_is_rejected_without_mutation(): void
    {
        $service = app(PaymentCallbackService::class);
        $this->createOrder();

        try {
            $service->handle($this->paymentCallback(['status' => 'refunded']));
            $this->fail('Refund before success must be rejected.');
        } catch (InvalidArgumentException $exception) {
            $this->assertStringContainsString('before success', $exception->getMessage());
        }

        $order = DepositRecord::where('local_order_no', self::ORDER_NO)->firstOrFail();
        $this->assertSame('pending', $order->payment_status);
        $this->assertSame(0, DB::table('payment_settlement_outbox')->where('deposit_record_id', $order->id)->count());
    }

    // 验证成功可迁移到退款且不创建第二条 outbox。
    public function test_success_can_transition_to_refunded_without_creating_another_outbox(): void
    {
        $service = app(PaymentCallbackService::class);
        $this->createOrder();
        $service->handle($this->paymentCallback());

        $service->handle($this->paymentCallback([
            'status' => 'refunded',
            'payload_hash' => hash('sha256', 'verified-refund-payload'),
        ]));

        $order = DepositRecord::where('local_order_no', self::ORDER_NO)->firstOrFail();
        $this->assertSame('refunded', $order->payment_status);
        $this->assertSame(hash('sha256', 'verified-refund-payload'), $order->provider_payload_hash);
        $this->assertSame(1, DB::table('payment_settlement_outbox')->where('deposit_record_id', $order->id)->count());
    }

    // 验证入金结算前退款取消充值且不创建 MT4 反向退款 outbox。
    public function test_refund_before_deposit_settlement_cancels_deposit_without_mt4_reverse(): void
    {
        $service = app(PaymentCallbackService::class);
        $this->createOrder();
        $service->handle($this->paymentCallback());

        $service->handle($this->paymentCallback([
            'status' => 'refunded',
            'payload_hash' => hash('sha256', 'refund-before-deposit-settlement'),
        ]));

        $order = DepositRecord::where('local_order_no', self::ORDER_NO)->firstOrFail();
        $this->assertSame('refunded', $order->payment_status);
        $this->assertSame('refunded', $order->settlement_status);
        $this->assertSame('05', $order->status);
        $this->assertDatabaseHas('payment_settlement_outbox', [
            'deposit_record_id' => $order->id,
            'event_type' => 'deposit_settlement',
            'status' => 'refunded',
        ]);
        $this->assertSame(0, DB::table('payment_settlement_outbox')
            ->where('deposit_record_id', $order->id)
            ->where('event_type', 'deposit_refund')
            ->count());
    }

    // 验证已结算入金的退款创建一条 pending 反向退款 outbox 并派发退款任务。
    public function test_refund_after_settled_deposit_creates_one_pending_reverse_outbox(): void
    {
        $service = app(PaymentCallbackService::class);
        $order = $this->createOrder([
            'payment_status' => 'success',
            'settlement_status' => 'settled',
            'status' => '02',
            'mt4_ticket' => 92001,
            'channel_order_no' => 'PROVIDER-CALLBACK-1001',
            'provider_payload_hash' => hash('sha256', 'verified-callback-payload'),
        ]);
        $refund = $this->paymentCallback([
            'status' => 'refunded',
            'payload_hash' => hash('sha256', 'settled-refund-payload'),
        ]);

        $service->handle($refund);
        $service->handle($refund);

        $order->refresh();
        $this->assertSame('refund_pending', $order->payment_status);
        $this->assertSame('settled', $order->settlement_status);
        $this->assertSame('02', $order->status);
        $this->assertSame(1, DB::table('payment_settlement_outbox')
            ->where('deposit_record_id', $order->id)
            ->where('event_type', 'deposit_refund')
            ->where('status', 'pending')
            ->count());
        Queue::assertPushed(RefundDepositPayment::class, 1);
        Queue::assertNotPushed(SettleDepositPayment::class);
    }

    // 验证入金处理中的退款被阻塞等待入金结果。
    public function test_refund_during_deposit_processing_is_blocked_until_deposit_outcome(): void
    {
        $service = app(PaymentCallbackService::class);
        $order = $this->createOrder([
            'payment_status' => 'success',
            'settlement_status' => 'processing',
            'channel_order_no' => 'PROVIDER-CALLBACK-1001',
            'provider_payload_hash' => hash('sha256', 'verified-callback-payload'),
        ]);
        PaymentSettlementOutbox::create([
            'deposit_record_id' => $order->id,
            'local_order_no' => self::ORDER_NO,
            'event_type' => 'deposit_settlement',
            'status' => 'processing',
            'attempts' => 1,
            'payload_hash' => (string) $order->provider_payload_hash,
            'available_at' => now(),
            'locked_at' => now(),
        ]);

        $service->handle($this->paymentCallback([
            'status' => 'refunded',
            'payload_hash' => hash('sha256', 'processing-refund-payload'),
        ]));

        $order->refresh();
        $this->assertSame('refund_pending', $order->payment_status);
        $this->assertSame('processing', $order->settlement_status);
        $this->assertDatabaseHas('payment_settlement_outbox', [
            'deposit_record_id' => $order->id,
            'event_type' => 'deposit_refund',
            'status' => 'blocked',
            'last_error_code' => 'deposit_settlement_in_progress',
        ]);
        Queue::assertNotPushed(RefundDepositPayment::class);
    }

    /** @dataProvider unsettledTerminalRefundProvider */
    // 验证未知或已拒绝入金的退款创建终态审计 outbox（数据提供器驱动）。
    public function test_refund_for_unknown_or_rejected_deposit_creates_terminal_audit_outbox(
        string $settlementStatus,
        string $expectedPaymentStatus,
        string $expectedRefundStatus
    ): void {
        $service = app(PaymentCallbackService::class);
        $order = $this->createOrder([
            'payment_status' => 'success',
            'settlement_status' => $settlementStatus,
            'channel_order_no' => 'PROVIDER-CALLBACK-1001',
            'provider_payload_hash' => hash('sha256', 'verified-callback-payload'),
        ]);

        $service->handle($this->paymentCallback([
            'status' => 'refunded',
            'payload_hash' => hash('sha256', 'terminal-refund-' . $settlementStatus),
        ]));

        $order->refresh();
        $this->assertSame($expectedPaymentStatus, $order->payment_status);
        $this->assertDatabaseHas('payment_settlement_outbox', [
            'deposit_record_id' => $order->id,
            'event_type' => 'deposit_refund',
            'status' => $expectedRefundStatus,
        ]);
        Queue::assertNotPushed(RefundDepositPayment::class);
    }

    // 数据提供器：未知/已拒绝结算状态对应的退款终态用例。
    public function unsettledTerminalRefundProvider(): array
    {
        return [
            'unknown requires reconciliation' => ['unknown', 'refund_unknown', 'unknown'],
            'rejected needs no reverse' => ['rejected', 'refunded', 'processed'],
        ];
    }

    // 验证不同载荷哈希的重复成功回调保留原始审计哈希。
    public function test_duplicate_success_with_different_payload_hash_keeps_original_audit_hash(): void
    {
        $service = app(PaymentCallbackService::class);
        $this->createOrder();
        $originalHash = hash('sha256', 'verified-callback-payload');
        $service->handle($this->paymentCallback());

        $service->handle($this->paymentCallback([
            'payload_hash' => hash('sha256', 'equivalent-retried-payload'),
        ]));

        $order = DepositRecord::where('local_order_no', self::ORDER_NO)->firstOrFail();
        $this->assertSame('success', $order->payment_status);
        $this->assertSame($originalHash, $order->provider_payload_hash);
        $this->assertSame(1, DB::table('payment_settlement_outbox')->where('deposit_record_id', $order->id)->count());
    }

    // 验证首次验签回调后渠道订单号不一致的回调被拒绝。
    public function test_provider_order_mismatch_is_rejected_after_first_verified_callback(): void
    {
        $service = app(PaymentCallbackService::class);
        $this->createOrder();
        $service->handle($this->paymentCallback());

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('provider order mismatch');
        $service->handle($this->paymentCallback(['provider_order_no' => 'PROVIDER-CALLBACK-OTHER']));
    }

    /** @dataProvider mismatchProvider */
    // 验证渠道、商户、订单、金额、币种不匹配的回调整体回滚（数据提供器驱动）。
    public function test_callback_identity_currency_and_exact_amount_mismatches_roll_back(string $field, string $value): void
    {
        $service = app(PaymentCallbackService::class);
        $this->createOrder();

        $this->expectException(InvalidArgumentException::class);
        try {
            $service->handle($this->paymentCallback([$field => $value]));
        } finally {
            $this->assertSame('pending', DepositRecord::where('local_order_no', self::ORDER_NO)->value('payment_status'));
            if (Schema::hasTable('payment_settlement_outbox')) {
                $this->assertSame(0, DB::table('payment_settlement_outbox')->where('local_order_no', self::ORDER_NO)->count());
            }
        }
    }

    // 数据提供器：回调不匹配字段与非法值的用例。
    public function mismatchProvider(): array
    {
        return [
            'gateway' => ['gateway_code', 'other-gateway'],
            'merchant' => ['merchant_id', 'other-merchant'],
            'order' => ['local_order_no', 'DEP-CALLBACK-TASK5-OTHER'],
            'amount' => ['amount', '700.01'],
            'currency' => ['currency', 'USD'],
        ];
    }

    // 验证通知路由仅在成功提交后才返回渠道 ACK。
    public function test_notify_route_returns_provider_ack_only_after_committed_success(): void
    {
        $this->createOrder(['gateway_code' => 'callback-task5']);
        $this->registerRouteAdapter(new Task5CallbackFixtureAdapter($this->paymentCallback([
            'gateway_code' => 'callback-task5',
        ])));

        $response = $this->post('/api/front/payment/notify/callback-task5', ['signed' => 'yes']);

        $response->assertStatus(200)->assertSeeText('TASK5_ACK');
        $this->assertSame('success', DepositRecord::where('local_order_no', self::ORDER_NO)->value('payment_status'));
        $this->assertSame(1, DB::table('payment_settlement_outbox')->where('local_order_no', self::ORDER_NO)->count());
    }

    // 验证通知路由拒绝无效签名且不做任何变更。
    public function test_notify_route_rejects_invalid_signature_without_mutation(): void
    {
        $this->createOrder(['gateway_code' => 'callback-task5']);
        $this->registerRouteAdapter(new Task5CallbackFixtureAdapter($this->paymentCallback([
            'gateway_code' => 'callback-task5',
        ]), false));

        $this->post('/api/front/payment/notify/callback-task5', ['signed' => 'no'])->assertStatus(400);

        $this->assertSame('pending', DepositRecord::where('local_order_no', self::ORDER_NO)->value('payment_status'));
        $this->assertSame(0, DB::table('payment_settlement_outbox')->where('local_order_no', self::ORDER_NO)->count());
    }

    // 验证通知路由对畸形验签载荷返回 422。
    public function test_notify_route_returns_422_for_malformed_verified_payload(): void
    {
        $this->createOrder(['gateway_code' => 'callback-task5']);
        $this->registerRouteAdapter(new Task5CallbackFixtureAdapter(
            $this->paymentCallback(['gateway_code' => 'callback-task5']),
            true,
            true
        ));

        $this->post('/api/front/payment/notify/callback-task5', ['signed' => 'yes'])->assertStatus(422);

        $this->assertSame('pending', DepositRecord::where('local_order_no', self::ORDER_NO)->value('payment_status'));
    }

    // 验证回调事务失败时通知路由返回 500 且不返回 ACK。
    public function test_notify_route_returns_500_and_no_ack_when_callback_transaction_fails(): void
    {
        $this->createOrder(['gateway_code' => 'callback-task5']);
        $this->registerRouteAdapter(new Task5CallbackFixtureAdapter($this->paymentCallback([
            'gateway_code' => 'callback-task5',
        ])));
        $this->app->instance(PaymentCallbackService::class, new class {
            public function handle(PaymentCallback $callback): DepositRecord
            {
                throw new RuntimeException('task5 injected transaction failure');
            }
        });

        $response = $this->post('/api/front/payment/notify/callback-task5', ['signed' => 'yes']);

        $response->assertStatus(500)->assertDontSeeText('TASK5_ACK');
        $this->assertSame('pending', DepositRecord::where('local_order_no', self::ORDER_NO)->value('payment_status'));
    }

    /** @param array<string, mixed> $overrides */
    private function createOrder(array $overrides = []): DepositRecord
    {
        return DepositRecord::create(array_replace([
            'user_id' => 412355005,
            'user_name' => 'payment-callback-user',
            'mt4_ticket' => 0,
            'amount' => '100.00',
            'actual_amount' => '700.00',
            'exchange_rate' => '7.00000000',
            'channel_name' => 'Task 5 Fixture',
            'channel_order_no' => '',
            'local_order_no' => self::ORDER_NO,
            'idempotency_key' => 'callback-task5-key',
            'gateway_code' => 'wppay',
            'merchant_id' => 'wp-task5-merchant',
            'provider_amount' => '700.00',
            'currency' => 'CNY',
            'payment_status' => 'pending',
            'settlement_status' => 'pending',
            'status' => '01',
            'remarks' => 'Task 5 callback fixture',
            'created_by' => 'test',
        ], $overrides));
    }

    /** @param array<string, string> $overrides */
    private function paymentCallback(array $overrides = []): PaymentCallback
    {
        $values = array_replace([
            'gateway_code' => 'wppay',
            'local_order_no' => self::ORDER_NO,
            'provider_order_no' => 'PROVIDER-CALLBACK-1001',
            'status' => 'success',
            'amount' => '700.00',
            'currency' => 'CNY',
            'merchant_id' => 'wp-task5-merchant',
            'payload_hash' => hash('sha256', 'verified-callback-payload'),
        ], $overrides);

        return new PaymentCallback(
            $values['gateway_code'],
            $values['local_order_no'],
            $values['provider_order_no'],
            $values['status'],
            $values['amount'],
            $values['currency'],
            $values['merchant_id'],
            $values['payload_hash']
        );
    }

    private function cleanup(): void
    {
        if (Schema::hasTable('payment_settlement_outbox')) {
            DB::table('payment_settlement_outbox')->where('local_order_no', self::ORDER_NO)->delete();
        }
        DB::table('deposit_records')->where('local_order_no', self::ORDER_NO)->delete();
        DB::table('payment_channels')->where('channel_code', 'callback-task5')->delete();
    }

    private function registerRouteAdapter(Task5CallbackFixtureAdapter $adapter): void
    {
        app(PaymentGatewayRegistry::class)->register('callback-task5-adapter', $adapter, ['CNY']);
        PaymentChannel::create([
            'name' => 'Task 5 Callback Channel',
            'channel_code' => 'callback-task5',
            'exchange_rate' => '7.00000000',
            'is_enabled' => 1,
            'sort' => 501,
            'config' => [
                'adapter' => 'callback-task5-adapter',
                'gateway_code' => 'callback-task5',
                'merchant_id' => 'wp-task5-merchant',
                'gateway_url' => 'https://provider.example.test/task5',
                'secret_reference' => 'env:PAYMENT_TASK5_FIXTURE',
                'currency' => 'CNY',
                'amount_unit' => 'decimal',
                'notify_route' => 'front_api_payment_notify',
                'return_route' => 'front_api_payment_return',
            ],
        ]);
    }
}

final class Task5CallbackFixtureAdapter implements PaymentGatewayAdapter
{
    /**
     * 替身预构造的 PaymentCallback 对象。parseCallback 原样返回它，驱动订单状态机的各种回调事件。
     * @var PaymentCallback
     */
    private $callback;
    /**
     * verifyCallback 的预设结论。false 时驱动验签失败分支，断言订单状态不被推进。
     * @var bool
     */
    private $signatureValid;
    /**
     * 是否返回畸形回调。true 时 parseCallback 抛出解析异常，验证失败关闭与错误码。
     * @var bool
     */
    private $malformed;

    public function __construct(PaymentCallback $callback, bool $signatureValid = true, bool $malformed = false)
    {
        $this->callback = $callback;
        $this->signatureValid = $signatureValid;
        $this->malformed = $malformed;
    }

    public function createOrder(DepositRecord $order, array $channelConfig): PaymentOrderResult
    {
        throw new RuntimeException('Not used by callback tests.');
    }

    public function verifyCallback(Request $request, array $channelConfig): bool
    {
        return $this->signatureValid;
    }

    public function parseCallback(Request $request, array $channelConfig): PaymentCallback
    {
        if ($this->malformed) {
            throw new InvalidArgumentException('Malformed Task 5 fixture callback.');
        }

        return $this->callback;
    }

    public function acknowledge(PaymentCallback $callback): Response
    {
        return new Response('TASK5_ACK', 200, ['Content-Type' => 'text/plain']);
    }
}
