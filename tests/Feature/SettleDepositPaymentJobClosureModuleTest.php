<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/02
 * Time: 15:08
 */

/**
 * SettleDepositPayment 队列任务结算闭环(SettleDepositPaymentJobClosureModuleTest)的测试。
 *
 * 文件功能:
 * - 通过实现 DepositSettlementGateway 的匿名类测试替身与数据库触发器,覆盖 deposit_settlement
 *   outbox 从 pending 到 settled/retryable/unknown/rejected 的完整状态机:外部成功后的本地提交、
 *   退避重试、幂等防重、陈旧 processing 认领、payload_hash 篡改、订单缺失、支付未成功等边界。
 * - 验证结算结果会联动解锁被阻塞的 deposit_refund outbox:settled 后放行、未送出/被拒时无需反向、
 *   结果未知时退款转为 unknown(deposit_result_unknown)。
 *
 * 适用场景:调整结算任务、DepositSettlementResult 分类、outbox 认领/幂等或退款联动逻辑后回归;
 * 这是资金结算与退款一致性的核心防线。
 *
 * 入参例子:createPendingSettlement() 构造 deposit_records 与 payment_settlement_outbox 双表 fixture,
 * gatewayReturning()/gatewayReturningSequence() 注入预设结果序列;数据提供器
 * abandonedDepositLockProvider/terminalOrderSettlementProvider/depositNotSentOrRejectedProvider
 * 覆盖锁时间缺失/过期、订单终态组合与未送出/被拒结果。
 *
 * 返回值:handle() 无返回值;断言通过表示订单结算状态、outbox 状态与 gateway 调用次数三者闭环一致。
 *
 * 失败场景:断言失败说明结算流程被破坏——可能重复调用网关、状态错置、退款被错误放行或错误阻塞,
 * 属于资金一致性问题,必须修复后回归通过。
 */

declare(strict_types=1);

namespace Tests\Feature;

use App\Contracts\DepositSettlementGateway;
use App\Jobs\SettleDepositPayment;
use App\Models\DepositRecord;
use App\Models\PaymentSettlementOutbox;
use App\Services\Payment\DepositSettlementResult;
use Illuminate\Support\Facades\DB;
use Illuminate\Contracts\Queue\ShouldQueue;
use Throwable;
use Tests\TestCase;

class SettleDepositPaymentJobClosureModuleTest extends TestCase
{
    /**
     * 入金结算用例的固定订单号（DEP-SETTLEMENT-TASK5-1001）。
     * 夹具据此建 deposit_records 与 outbox 行，断言与清理都围绕它定位。
     * @var string
     */
    private const ORDER_NO = 'DEP-SETTLEMENT-TASK5-1001';

    protected function setUp(): void
    {
        parent::setUp();
        $this->cleanup();
    }

    protected function tearDown(): void
    {
        $this->dropSettlementFailureTrigger();
        $this->cleanup();
        parent::tearDown();
    }

    public function test_settlement_result_exposes_closed_outcome_taxonomy(): void
    {
        $this->assertSame('settled', DepositSettlementResult::settled('9001')->status());
        $this->assertSame('retryable_not_sent', DepositSettlementResult::retryableNotSent('connect_failed')->status());
        $this->assertSame('unknown', DepositSettlementResult::unknown('read_timeout')->status());
        $this->assertSame('rejected', DepositSettlementResult::rejected('provider_rejected')->status());
    }

    public function test_settlement_job_has_bounded_queue_retry_policy(): void
    {
        $job = new SettleDepositPayment(123);

        $this->assertInstanceOf(ShouldQueue::class, $job);
        $this->assertSame(3, $job->tries);
        $this->assertSame([60, 300], $job->backoff());
    }

    public function test_settlement_job_ignores_refund_outbox_without_mutation(): void
    {
        [$order, $outbox] = $this->createPendingSettlement();
        $outbox->event_type = 'deposit_refund';
        $outbox->saveOrFail();
        $calls = [];

        (new SettleDepositPayment($outbox->id))->handle(
            $this->gatewayReturning(DepositSettlementResult::settled('9099'), $calls)
        );

        $order->refresh();
        $outbox->refresh();
        $this->assertSame([], $calls);
        $this->assertSame('pending', $order->settlement_status);
        $this->assertSame('01', $order->status);
        $this->assertSame('deposit_refund', $outbox->event_type);
        $this->assertSame('pending', $outbox->status);
        $this->assertSame(0, $outbox->attempts);
        $this->assertNull($outbox->locked_at);
        $this->assertNull($outbox->processed_at);
        $this->assertNull($outbox->provider_reference);
        $this->assertNull($outbox->last_error_code);
    }

    public function test_success_claims_outbox_then_settles_outside_transaction_with_account_usd_amount(): void
    {
        [$order, $outbox] = $this->createPendingSettlement();
        $calls = [];
        $gateway = $this->gatewayReturning(DepositSettlementResult::settled('9001'), $calls);

        (new SettleDepositPayment($outbox->id))->handle($gateway);

        $order->refresh();
        $outbox->refresh();
        $this->assertSame([[412355006, '100.00', 'DBUN-412355006-#' . self::ORDER_NO, 0]], $calls);
        $this->assertSame('settled', $order->settlement_status);
        $this->assertSame('02', $order->status);
        $this->assertSame(9001, (int) $order->mt4_ticket);
        $this->assertSame('processed', $outbox->status);
        $this->assertSame('9001', $outbox->provider_reference);
        $this->assertNotNull($outbox->processed_at);
    }

    public function test_retryable_not_sent_returns_to_retryable_with_backoff(): void
    {
        [$order, $outbox] = $this->createPendingSettlement();
        $calls = [];

        (new SettleDepositPayment($outbox->id))->handle(
            $this->gatewayReturning(DepositSettlementResult::retryableNotSent('connect_before_send'), $calls)
        );

        $order->refresh();
        $outbox->refresh();
        $this->assertCount(1, $calls);
        $this->assertSame('pending', $order->settlement_status);
        $this->assertSame('01', $order->status);
        $this->assertSame('retryable', $outbox->status);
        $this->assertSame(1, $outbox->attempts);
        $this->assertNull($outbox->locked_at);
        $this->assertTrue($outbox->available_at->isFuture());
        $this->assertSame('connect_before_send', $outbox->last_error_code);
    }

    public function test_unknown_result_is_terminal_and_never_automatically_retried(): void
    {
        [$order, $outbox] = $this->createPendingSettlement();
        $calls = [];
        $gateway = $this->gatewayReturning(DepositSettlementResult::unknown('read_timeout'), $calls);

        (new SettleDepositPayment($outbox->id))->handle($gateway);
        (new SettleDepositPayment($outbox->id))->handle($gateway);

        $order->refresh();
        $outbox->refresh();
        $this->assertCount(1, $calls);
        $this->assertSame('unknown', $order->settlement_status);
        $this->assertSame('01', $order->status);
        $this->assertSame('unknown', $outbox->status);
        $this->assertSame('read_timeout', $outbox->last_error_code);
    }

    public function test_retryable_outbox_can_be_claimed_after_backoff(): void
    {
        [$order, $outbox] = $this->createPendingSettlement();
        $calls = [];
        $gateway = $this->gatewayReturningSequence([
            DepositSettlementResult::retryableNotSent('connect_before_send'),
            DepositSettlementResult::settled('9004'),
        ], $calls);

        (new SettleDepositPayment($outbox->id))->handle($gateway);
        $outbox->refresh();
        $outbox->available_at = now()->subSecond();
        $outbox->saveOrFail();
        (new SettleDepositPayment($outbox->id))->handle($gateway);

        $order->refresh();
        $outbox->refresh();
        $this->assertCount(2, $calls);
        $this->assertSame('settled', $order->settlement_status);
        $this->assertSame('processed', $outbox->status);
        $this->assertSame(2, $outbox->attempts);
    }

    public function test_claim_sets_order_processing_before_external_call_and_retryable_restores_pending(): void
    {
        [$order, $outbox] = $this->createPendingSettlement();
        $observed = [];
        $gateway = new class($order->id, $observed) implements DepositSettlementGateway {
            /**
             * 替身记住的结算订单主键。deposit() 时读取该订单的 settlement_status，
             * 断言"先置 processing 再调外部"的时序约束。
             * @var int
             */
            private $orderId;
            /**
             * 引用传入的观察记录。每次 deposit() 记下 [事务层级, 订单状态快照]，供用例断言外部调用发生在事务外。
             * @var array<int, array{0: int, 1: string}>
             */
            private $observed;

            public function __construct(int $orderId, array &$observed)
            {
                $this->orderId = $orderId;
                $this->observed = &$observed;
            }

            public function deposit(int $userId, string $amount, string $comment): DepositSettlementResult
            {
                $this->observed[] = [
                    DB::transactionLevel(),
                    DepositRecord::findOrFail($this->orderId)->settlement_status,
                ];

                return DepositSettlementResult::retryableNotSent('connect_before_send');
            }
        };

        (new SettleDepositPayment($outbox->id))->handle($gateway);

        $order->refresh();
        $outbox->refresh();
        $this->assertSame([[0, 'processing']], $observed);
        $this->assertSame('pending', $order->settlement_status);
        $this->assertSame('retryable', $outbox->status);
    }

    public function test_provider_rejection_is_terminal_without_marking_legacy_success(): void
    {
        [$order, $outbox] = $this->createPendingSettlement();
        $calls = [];

        (new SettleDepositPayment($outbox->id))->handle(
            $this->gatewayReturning(DepositSettlementResult::rejected('provider_rejected'), $calls)
        );

        $order->refresh();
        $outbox->refresh();
        $this->assertCount(1, $calls);
        $this->assertSame('rejected', $order->settlement_status);
        $this->assertSame('01', $order->status);
        $this->assertSame('rejected', $outbox->status);
        $this->assertSame('provider_rejected', $outbox->last_error_code);
    }

    public function test_processed_and_recent_processing_outbox_are_not_called_again(): void
    {
        [, $processed] = $this->createPendingSettlement();
        $processed->status = 'processed';
        $processed->processed_at = now();
        $processed->saveOrFail();
        $calls = [];
        $gateway = $this->gatewayReturning(DepositSettlementResult::settled('9002'), $calls);

        (new SettleDepositPayment($processed->id))->handle($gateway);

        $this->cleanup();
        [, $processing] = $this->createPendingSettlement();
        $processing->status = 'processing';
        $processing->locked_at = now();
        $processing->saveOrFail();
        (new SettleDepositPayment($processing->id))->handle($gateway);

        $this->assertSame([], $calls);
    }

    public function test_stale_processing_claim_becomes_unknown_without_calling_gateway(): void
    {
        [$order, $outbox] = $this->createPendingSettlement();
        $order->settlement_status = 'processing';
        $order->saveOrFail();
        $outbox->status = 'processing';
        $outbox->attempts = 1;
        $outbox->locked_at = now()->subMinutes(10);
        $outbox->saveOrFail();
        $calls = [];
        $gateway = $this->gatewayReturning(DepositSettlementResult::settled('9003'), $calls);

        (new SettleDepositPayment($outbox->id))->handle($gateway);
        (new SettleDepositPayment($outbox->id))->handle($gateway);

        $order->refresh();
        $outbox->refresh();
        $this->assertSame([], $calls);
        $this->assertSame('unknown', $order->settlement_status);
        $this->assertSame('unknown', $outbox->status);
        $this->assertSame('stale_processing_claim', $outbox->last_error_code);
    }

    /** @dataProvider abandonedDepositLockProvider */
    public function test_abandoned_deposit_processing_propagates_unknown_to_blocked_refund($lockedAt): void
    {
        [$order, $outbox] = $this->createPendingSettlement();
        $order->payment_status = 'refund_pending';
        $order->settlement_status = 'processing';
        $order->saveOrFail();
        $outbox->status = 'processing';
        $outbox->attempts = 1;
        $outbox->locked_at = $lockedAt;
        $outbox->saveOrFail();
        $refund = $this->createBlockedRefundOutbox($order);
        $calls = [];

        (new SettleDepositPayment($outbox->id))->handle(
            $this->gatewayReturning(DepositSettlementResult::settled('9030'), $calls)
        );

        $order->refresh();
        $refund->refresh();
        $this->assertSame([], $calls);
        $this->assertSame('refund_unknown', $order->payment_status);
        $this->assertSame('unknown', $order->settlement_status);
        $this->assertSame('unknown', $refund->status);
        $this->assertSame('deposit_result_unknown', $refund->last_error_code);
    }

    public function abandonedDepositLockProvider(): array
    {
        return [
            'missing lock time' => [null],
            'expired lock time' => [now()->subMinutes(10)],
        ];
    }

    public function test_processing_claim_without_lock_time_becomes_unknown_without_calling_gateway(): void
    {
        [$order, $outbox] = $this->createPendingSettlement();
        $order->settlement_status = 'processing';
        $order->saveOrFail();
        $outbox->status = 'processing';
        $outbox->attempts = 1;
        $outbox->locked_at = null;
        $outbox->saveOrFail();
        $calls = [];

        (new SettleDepositPayment($outbox->id))->handle(
            $this->gatewayReturning(DepositSettlementResult::settled('9010'), $calls)
        );

        $order->refresh();
        $outbox->refresh();
        $this->assertSame([], $calls);
        $this->assertSame('unknown', $order->settlement_status);
        $this->assertSame('unknown', $outbox->status);
        $this->assertSame('stale_processing_claim', $outbox->last_error_code);
    }

    public function test_missing_order_rejects_outbox_instead_of_leaving_it_scannable(): void
    {
        [$order, $outbox] = $this->createPendingSettlement();
        $order->forceDelete();
        $calls = [];

        (new SettleDepositPayment($outbox->id))->handle(
            $this->gatewayReturning(DepositSettlementResult::settled('9011'), $calls)
        );

        $outbox->refresh();
        $this->assertSame([], $calls);
        $this->assertSame('rejected', $outbox->status);
        $this->assertSame('deposit_record_missing', $outbox->last_error_code);
        $this->assertNotNull($outbox->processed_at);
    }

    /** @dataProvider terminalOrderSettlementProvider */
    public function test_terminal_order_state_reconciles_pending_outbox_without_gateway_call(
        string $settlementStatus,
        string $expectedOutboxStatus
    ): void {
        [$order, $outbox] = $this->createPendingSettlement();
        $order->settlement_status = $settlementStatus;
        if ($settlementStatus === 'settled') {
            $order->mt4_ticket = 9012;
        }
        $order->saveOrFail();
        $calls = [];

        (new SettleDepositPayment($outbox->id))->handle(
            $this->gatewayReturning(DepositSettlementResult::settled('9013'), $calls)
        );

        $outbox->refresh();
        $this->assertSame([], $calls);
        $this->assertSame($expectedOutboxStatus, $outbox->status);
        $this->assertNotNull($outbox->processed_at);
        if ($settlementStatus === 'settled') {
            $this->assertSame('9012', $outbox->provider_reference);
        }
    }

    public function terminalOrderSettlementProvider(): array
    {
        return [
            'settled' => ['settled', 'processed'],
            'unknown' => ['unknown', 'unknown'],
            'rejected' => ['rejected', 'rejected'],
            'refunded' => ['refunded', 'refunded'],
        ];
    }

    public function test_payload_hash_mismatch_is_rejected_without_calling_gateway(): void
    {
        [$order, $outbox] = $this->createPendingSettlement();
        $outbox->payload_hash = hash('sha256', 'tampered-settlement-payload');
        $outbox->saveOrFail();
        $calls = [];

        (new SettleDepositPayment($outbox->id))->handle(
            $this->gatewayReturning(DepositSettlementResult::settled('9005'), $calls)
        );

        $order->refresh();
        $outbox->refresh();
        $this->assertSame([], $calls);
        $this->assertSame('rejected', $order->settlement_status);
        $this->assertSame('rejected', $outbox->status);
        $this->assertSame('payload_hash_mismatch', $outbox->last_error_code);
    }

    public function test_payment_not_success_rejects_outbox_without_changing_order_settlement(): void
    {
        [$order, $outbox] = $this->createPendingSettlement();
        $order->payment_status = 'failed';
        $order->saveOrFail();
        $calls = [];

        (new SettleDepositPayment($outbox->id))->handle(
            $this->gatewayReturning(DepositSettlementResult::settled('9007'), $calls)
        );

        $order->refresh();
        $outbox->refresh();
        $this->assertSame([], $calls);
        $this->assertSame('failed', $order->payment_status);
        $this->assertSame('pending', $order->settlement_status);
        $this->assertSame('rejected', $outbox->status);
        $this->assertSame('payment_not_success', $outbox->last_error_code);
    }

    public function test_local_commit_failure_after_external_success_is_frozen_as_unknown(): void
    {
        [$order, $outbox] = $this->createPendingSettlement();
        $this->createSettlementFailureTrigger();
        $calls = [];

        (new SettleDepositPayment($outbox->id))->handle(
            $this->gatewayReturning(DepositSettlementResult::settled('9006'), $calls)
        );

        $order->refresh();
        $outbox->refresh();
        $this->assertCount(1, $calls);
        $this->assertSame('unknown', $order->settlement_status);
        $this->assertSame('unknown', $outbox->status);
        $this->assertSame('local_commit_after_external_success_failed', $outbox->last_error_code);
        $this->assertNull($outbox->provider_reference);
    }

    public function test_local_deposit_commit_failure_propagates_unknown_to_blocked_refund(): void
    {
        [$order, $outbox] = $this->createPendingSettlement();
        $order->payment_status = 'refund_pending';
        $order->saveOrFail();
        $refund = $this->createBlockedRefundOutbox($order);
        $this->createSettlementFailureTrigger();
        $calls = [];

        (new SettleDepositPayment($outbox->id))->handle(
            $this->gatewayReturning(DepositSettlementResult::settled('9031'), $calls)
        );

        $order->refresh();
        $refund->refresh();
        $this->assertCount(1, $calls);
        $this->assertSame('refund_unknown', $order->payment_status);
        $this->assertSame('unknown', $order->settlement_status);
        $this->assertSame('unknown', $refund->status);
        $this->assertSame('deposit_result_unknown', $refund->last_error_code);
    }

    public function test_blocked_refund_is_activated_after_inflight_deposit_settles(): void
    {
        [$order, $outbox] = $this->createPendingSettlement();
        $order->payment_status = 'refund_pending';
        $order->saveOrFail();
        $refund = $this->createBlockedRefundOutbox($order);
        $calls = [];

        (new SettleDepositPayment($outbox->id))->handle(
            $this->gatewayReturning(DepositSettlementResult::settled('9020'), $calls)
        );

        $order->refresh();
        $refund->refresh();
        $this->assertCount(1, $calls);
        $this->assertSame('settled', $order->settlement_status);
        $this->assertSame('refund_pending', $order->payment_status);
        $this->assertSame('pending', $refund->status);
        $this->assertNull($refund->last_error_code);
    }

    /** @dataProvider depositNotSentOrRejectedProvider */
    public function test_blocked_refund_needs_no_reverse_when_deposit_did_not_settle(
        DepositSettlementResult $result
    ): void {
        [$order, $outbox] = $this->createPendingSettlement();
        $order->payment_status = 'refund_pending';
        $order->saveOrFail();
        $refund = $this->createBlockedRefundOutbox($order);
        $calls = [];

        (new SettleDepositPayment($outbox->id))->handle($this->gatewayReturning($result, $calls));

        $order->refresh();
        $outbox->refresh();
        $refund->refresh();
        $this->assertCount(1, $calls);
        $this->assertSame('refunded', $order->payment_status);
        $this->assertSame('refunded', $order->settlement_status);
        $this->assertSame('05', $order->status);
        $this->assertSame('processed', $refund->status);
        $this->assertSame('deposit_not_settled_no_reverse_needed', $refund->last_error_code);
    }

    public function depositNotSentOrRejectedProvider(): array
    {
        return [
            'not sent' => [DepositSettlementResult::retryableNotSent('connection_failed')],
            'rejected' => [DepositSettlementResult::rejected('provider_rejected')],
        ];
    }

    public function test_blocked_refund_becomes_unknown_when_deposit_result_is_unknown(): void
    {
        [$order, $outbox] = $this->createPendingSettlement();
        $order->payment_status = 'refund_pending';
        $order->saveOrFail();
        $refund = $this->createBlockedRefundOutbox($order);
        $calls = [];

        (new SettleDepositPayment($outbox->id))->handle(
            $this->gatewayReturning(DepositSettlementResult::unknown('read_timeout'), $calls)
        );

        $order->refresh();
        $refund->refresh();
        $this->assertCount(1, $calls);
        $this->assertSame('refund_unknown', $order->payment_status);
        $this->assertSame('unknown', $order->settlement_status);
        $this->assertSame('unknown', $refund->status);
        $this->assertSame('deposit_result_unknown', $refund->last_error_code);
    }

    /** @return array{0: DepositRecord, 1: PaymentSettlementOutbox} */
    private function createPendingSettlement(): array
    {
        $order = DepositRecord::create([
            'user_id' => 412355006,
            'user_name' => 'settlement-task5-user',
            'mt4_ticket' => 0,
            'amount' => '100.00',
            'actual_amount' => '700.00',
            'provider_amount' => '700.00',
            'exchange_rate' => '7.00000000',
            'channel_name' => 'Task 5 Settlement Fixture',
            'channel_order_no' => 'PROVIDER-SETTLEMENT-1001',
            'local_order_no' => self::ORDER_NO,
            'idempotency_key' => 'settlement-task5-key',
            'gateway_code' => 'wppay',
            'merchant_id' => 'settlement-task5-merchant',
            'currency' => 'CNY',
            'payment_status' => 'success',
            'settlement_status' => 'pending',
            'provider_payload_hash' => hash('sha256', 'settlement-task5-payload'),
            'status' => '01',
            'remarks' => 'Task 5 settlement fixture',
            'created_by' => 'test',
        ]);
        $outbox = PaymentSettlementOutbox::create([
            'deposit_record_id' => $order->id,
            'local_order_no' => self::ORDER_NO,
            'event_type' => 'deposit_settlement',
            'status' => 'pending',
            'attempts' => 0,
            'payload_hash' => (string) $order->provider_payload_hash,
            'available_at' => now(),
        ]);

        return [$order, $outbox];
    }

    private function createBlockedRefundOutbox(DepositRecord $order): PaymentSettlementOutbox
    {
        return PaymentSettlementOutbox::create([
            'deposit_record_id' => $order->id,
            'local_order_no' => self::ORDER_NO,
            'event_type' => 'deposit_refund',
            'status' => 'blocked',
            'attempts' => 0,
            'payload_hash' => (string) $order->provider_payload_hash,
            'available_at' => null,
            'last_error_code' => 'deposit_settlement_in_progress',
        ]);
    }

    private function gatewayReturning(DepositSettlementResult $result, array &$calls): DepositSettlementGateway
    {
        return new class($result, $calls) implements DepositSettlementGateway {
            /**
             * 替身预设的单次结算结果（成功/拒绝/可重试）。驱动订单与 outbox 的各终态分支。
             * @var DepositSettlementResult
             */
            private $result;
            /**
             * 引用传入的调用记录。deposit() 每次调用记下 [userId, amount, comment]，供调用次数与参数断言。
             * @var array<int, array{0: int, 1: string, 2: string}>
             */
            /**
             * 引用传入的调用记录。deposit() 每次调用记下入参，供调用次数与参数断言。
             * @var array<int, array{0: int, 1: string, 2: string}>
             */
            private $calls;

            public function __construct(DepositSettlementResult $result, array &$calls)
            {
                $this->result = $result;
                $this->calls = &$calls;
            }

            public function deposit(int $userId, string $amount, string $comment): DepositSettlementResult
            {
                $this->calls[] = [$userId, $amount, $comment, DB::transactionLevel()];

                return $this->result;
            }
        };
    }

    /** @param array<int, DepositSettlementResult> $results */
    private function gatewayReturningSequence(array $results, array &$calls): DepositSettlementGateway
    {
        return new class($results, $calls) implements DepositSettlementGateway {
            /**
             * 替身预设的结果序列，多次调用时逐个弹出。驱动连续重试、先失败后成功等场景。
             * @var array<int, DepositSettlementResult>
             */
            private $results;
            /**
             * 引用传入的调用记录。deposit() 每次调用记下入参，供调用次数与参数断言。
             * @var array<int, array{0: int, 1: string, 2: string}>
             */
            private $calls;

            public function __construct(array $results, array &$calls)
            {
                $this->results = $results;
                $this->calls = &$calls;
            }

            public function deposit(int $userId, string $amount, string $comment): DepositSettlementResult
            {
                $this->calls[] = [$userId, $amount, $comment, DB::transactionLevel()];

                return array_shift($this->results);
            }
        };
    }

    private function cleanup(): void
    {
        DB::table('payment_settlement_outbox')->where('local_order_no', self::ORDER_NO)->delete();
        DB::table('deposit_records')->where('local_order_no', self::ORDER_NO)->delete();
    }

    private function createSettlementFailureTrigger(): void
    {
        $this->dropSettlementFailureTrigger();
        DB::unprepared(<<<'SQL'
CREATE TRIGGER task5_fail_settled_update
BEFORE UPDATE ON deposit_records
FOR EACH ROW
BEGIN
    IF NEW.local_order_no = 'DEP-SETTLEMENT-TASK5-1001' AND NEW.settlement_status = 'settled' THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'task5 forced local settlement failure';
    END IF;
END
SQL
        );
    }

    private function dropSettlementFailureTrigger(): void
    {
        try {
            DB::unprepared('DROP TRIGGER IF EXISTS task5_fail_settled_update');
        } catch (Throwable $exception) {
            // The connection can already be unavailable during framework shutdown.
        }
    }
}
