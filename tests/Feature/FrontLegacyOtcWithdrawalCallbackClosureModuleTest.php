<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/17
 * Time: 20:42
 */

/**
 * FrontLegacyOtcWithdrawalCallbackClosureModuleTest
 *
 * 文件功能：
 * - 验证旧 OTC 出金回调闭环：未配置回调返回 422 且重放不变更、签名非法与重放拒绝不变更出金单、入金适配器不能处理旧出金回调。
 * - 输入：HTTP 请求与事务回滚的数据库夹具；输出：PHPUnit 断言结果。
 * - 明确不负责：不覆盖真实 MT4 网关与线上支付/出金通道（由网关契约测试锁定）。
 */

namespace Tests\Feature;

use App\Contracts\PaymentGatewayAdapter;
use App\Models\DepositRecord;
use App\Models\PaymentChannel;
use App\Services\Payment\PaymentCallback;
use App\Services\Payment\PaymentGatewayRegistry;
use App\Services\Payment\PaymentOrderResult;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

/**
 * 锁定旧 OTC 出金回调在可信协议接入前的失败关闭和零写入边界。
 */
class FrontLegacyOtcWithdrawalCallbackClosureModuleTest extends TestCase
{
    use DatabaseTransactions;

    /**
     * OTC 出金回调用例的固定业务用户 ID。夹具据此建用户与出金单。
     * @var int
     */
    private const USER_ID = 993501;

    /**
     * 夹具创建的出金单主键。回调状态机断言与清理都按它定位。
     * @var int
     */
    private $withdrawId;

    protected function setUp(): void
    {
        parent::setUp();

        DB::table('payment_channels')->whereIn('channel_code', [
            'otc_withdraw_notify',
            'otc_withdraw_verify',
        ])->delete();

        $this->withdrawId = (int) DB::table('withdraw_records')->insertGetId([
            'user_id' => self::USER_ID,
            'user_name' => 'legacy-otc-callback-user',
            'mt4_ticket' => 'OTC-CALLBACK-TICKET',
            'apply_amount' => '100.00',
            'actual_amount' => '95.00',
            'fee' => '5.00',
            'exchange_rate' => '7.00000000',
            'rmb_fee' => '35.00',
            'bank_no' => '62220000993501',
            'bank_name' => 'Callback Bank',
            'bank_addr' => 'Callback Branch',
            'status' => 0,
            'local_order_no' => 'OTC-CALLBACK-LOCAL',
            'third_order_no' => '',
            'reject_reason' => '',
            'mt4_return_status' => '',
            'idempotency_key' => 'OTC-CALLBACK-IDEMPOTENCY',
            'funding_status' => 'debited',
            'funding_payload_hash' => hash('sha256', 'OTC-CALLBACK-IDEMPOTENCY'),
            'created_by' => 'test',
            'updated_by' => '',
            'created_at' => strtotime('2026-08-17 10:00:00'),
            'updated_at' => strtotime('2026-08-17 10:00:00'),
            'deleted_at' => null,
        ]);
    }

    public function test_unconfigured_post_callbacks_return_422_and_replays_never_mutate_withdrawal(): void
    {
        $before = $this->withdrawSnapshot();

        foreach ($this->callbackUris() as $uri) {
            for ($attempt = 0; $attempt < 2; $attempt++) {
                $response = $this->post($uri, $this->unsafeLegacyPayload());
                $response->assertStatus(422);
                $this->assertSame('callback_not_configured', $response->getContent());
                $this->assertNotContains(strtolower(trim($response->getContent())), ['success', 'ok']);
            }
        }

        $this->assertSame($before, $this->withdrawSnapshot());
    }

    public function test_configured_otc_adapter_rejects_invalid_signature_and_replays_without_mutation(): void
    {
        foreach (['otc_withdraw_notify', 'otc_withdraw_verify'] as $gateway) {
            $this->insertOtcChannel($gateway);
        }
        $before = $this->withdrawSnapshot();

        foreach ($this->callbackUris() as $uri) {
            for ($attempt = 0; $attempt < 2; $attempt++) {
                $response = $this->post($uri, $this->unsafeLegacyPayload());
                $response->assertStatus(400);
                $this->assertSame('invalid_signature', $response->getContent());
                $this->assertNotContains(strtolower(trim($response->getContent())), ['success', 'ok']);
            }
        }

        $this->assertSame($before, $this->withdrawSnapshot());
    }

    public function test_valid_deposit_adapter_cannot_process_legacy_withdraw_callbacks(): void
    {
        $withdrawBefore = $this->withdrawSnapshot();

        foreach (['otc_withdraw_notify', 'otc_withdraw_verify'] as $gateway) {
            $localOrderNo = 'DEP-WITHDRAW-BYPASS-' . strtoupper(substr($gateway, 13));
            $merchantId = 'withdraw-bypass-merchant';
            DepositRecord::create([
                'user_id' => self::USER_ID,
                'user_name' => 'withdraw-bypass-deposit-user',
                'mt4_ticket' => 0,
                'amount' => '100.00',
                'actual_amount' => '100.00',
                'exchange_rate' => '1.00000000',
                'channel_name' => 'Invalid withdraw callback adapter',
                'channel_order_no' => '',
                'local_order_no' => $localOrderNo,
                'idempotency_key' => 'withdraw-bypass-' . $gateway,
                'gateway_code' => $gateway,
                'merchant_id' => $merchantId,
                'provider_amount' => '100.00',
                'currency' => 'USD',
                'payment_status' => 'pending',
                'settlement_status' => 'pending',
                'status' => '01',
                'remarks' => 'Must remain untouched by legacy withdraw callbacks',
                'created_by' => 'test',
            ]);

            $alias = 'withdraw-bypass-' . $gateway;
            $callback = new PaymentCallback(
                $gateway,
                $localOrderNo,
                'PROVIDER-WITHDRAW-BYPASS',
                'success',
                '100.00',
                'USD',
                $merchantId,
                hash('sha256', $gateway)
            );
            app(PaymentGatewayRegistry::class)->register(
                $alias,
                new LegacyWithdrawValidDepositAdapter($callback),
                ['USDT']
            );
            $this->insertChannel($gateway, $alias, $merchantId);
            $channel = PaymentChannel::enabled()->where('channel_code', $gateway)->firstOrFail();
            $this->assertSame($alias, $channel->config['adapter'] ?? null);
            $this->assertSame($gateway, $channel->config['gateway_code'] ?? null);
            $this->assertTrue(app(PaymentGatewayRegistry::class)->supportsAlias($alias));
            $this->assertNotNull(
                app(PaymentGatewayRegistry::class)->resolve($channel, $gateway),
                '测试必须先证明该出金 gateway 能解析到有效的入金 adapter。'
            );

            $depositBefore = (array) DB::table('deposit_records')
                ->where('local_order_no', $localOrderNo)
                ->first(['payment_status', 'settlement_status', 'channel_order_no', 'updated_at']);

            $response = $this->post(
                $gateway === 'otc_withdraw_notify'
                    ? '/user/withdraw_notfiy_otc'
                    : '/user/withdraw_verify_otc',
                ['signed' => 'yes']
            );

            $response->assertStatus(422);
            $this->assertSame('callback_not_configured', $response->getContent());
            $this->assertSame(
                $depositBefore,
                (array) DB::table('deposit_records')
                    ->where('local_order_no', $localOrderNo)
                    ->first(['payment_status', 'settlement_status', 'channel_order_no', 'updated_at'])
            );
            $this->assertSame(
                0,
                DB::table('payment_settlement_outbox')->where('local_order_no', $localOrderNo)->count()
            );
        }

        $this->assertSame($withdrawBefore, $this->withdrawSnapshot());
    }

    /** @return array<int, string> */
    private function callbackUris(): array
    {
        return ['/user/withdraw_notfiy_otc', '/user/withdraw_verify_otc'];
    }

    /** @return array<string, mixed> */
    private function unsafeLegacyPayload(): array
    {
        return [
            'id' => $this->withdrawId,
            'orderId' => 'OTC-CALLBACK-LOCAL',
            'outOrderId_LOC' => 'OTC-CALLBACK-LOCAL',
            'outOrderId_OTC' => 'OTC-CALLBACK-THIRD',
            'account' => self::USER_ID,
            'status' => '1',
            'orderStatus' => '200',
            'sign' => 'untrusted-signature',
        ];
    }

    /** @return array<string, mixed> */
    private function withdrawSnapshot(): array
    {
        $record = (array) DB::table('withdraw_records')->where('id', $this->withdrawId)->first([
            'status',
            'local_order_no',
            'third_order_no',
            'updated_at',
        ]);
        $record['outbox_count'] = DB::table('withdraw_settlement_outbox')
            ->where('withdraw_record_id', $this->withdrawId)
            ->count();

        return $record;
    }

    private function insertOtcChannel(string $gateway): void
    {
        $this->insertChannel($gateway, 'otc', 'otc-callback-fixture');
    }

    private function insertChannel(string $gateway, string $adapter, string $merchantId): void
    {
        DB::table('payment_channels')->insert([
            'name' => 'OTC callback fixture ' . $gateway,
            'channel_code' => $gateway,
            'exchange_rate' => 1,
            'is_enabled' => 1,
            'sort' => 0,
            'config' => json_encode([
                'gateway_code' => $gateway,
                'adapter' => $adapter,
                'merchant_id' => $merchantId,
                'gateway_url' => 'https://provider.example.test/otc/unsupported',
                'secret_reference' => 'env:PAYMENT_OTC_FIXTURE_KEY',
                'currency' => 'USDT',
                'amount_unit' => 'decimal',
                'notify_route' => 'front_api_payment_notify',
                'return_route' => 'front_api_payment_return',
            ]),
            'created_at' => time(),
            'updated_at' => time(),
            'deleted_at' => null,
        ]);
    }
}

final class LegacyWithdrawValidDepositAdapter implements PaymentGatewayAdapter
{
    /**
     * 替身预构造的 PaymentCallback。parseCallback 原样返回，驱动 OTC 出金单的回调推进。
     * @var PaymentCallback
     */
    private $callback;

    public function __construct(PaymentCallback $callback)
    {
        $this->callback = $callback;
    }

    public function createOrder(DepositRecord $order, array $channelConfig): PaymentOrderResult
    {
        throw new RuntimeException('Not used by callback tests.');
    }

    public function verifyCallback(Request $request, array $channelConfig): bool
    {
        return true;
    }

    public function parseCallback(Request $request, array $channelConfig): PaymentCallback
    {
        return $this->callback;
    }

    public function acknowledge(PaymentCallback $callback): Response
    {
        return new Response('WITHDRAW_BYPASS_ACK', 200);
    }
}
