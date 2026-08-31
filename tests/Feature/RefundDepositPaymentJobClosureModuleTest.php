<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/07/11
 * Time: 16:33
 */

/**
 * RefundDepositPaymentJobClosureModuleTest
 *
 * 文件功能：
 * - 验证入金退款 Job 闭环：退款时间模型持久化 MySQL datetime、有界队列重试、已结算入金退款在事务外扣减账户、outbox 重放不再扣款、连接失败可重试、传输不确定终态 unknown。
 * - 输入：HTTP 请求与事务回滚的数据库夹具；输出：PHPUnit 断言结果。
 * - 明确不负责：不覆盖真实 MT4 网关与线上支付/出金通道（由网关契约测试锁定）。
 */

declare(strict_types=1);

namespace Tests\Feature;

use App\Contracts\DepositRefundGateway;
use App\Jobs\RefundDepositPayment;
use App\Models\DepositRecord;
use App\Models\PaymentSettlementOutbox;
use App\Services\Payment\DepositSettlementResult;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\DB;
use Throwable;
use Tests\TestCase;

class RefundDepositPaymentJobClosureModuleTest extends TestCase
{
    public function test_refund_time_model_contract_persists_mysql_datetime_not_epoch(): void
    {
        [$order] = $this->createPendingRefund();
        $order->refund_time = now()->startOfSecond();
        $order->saveOrFail();
        $order->refresh();

        $raw = DB::table('deposit_records')->where('id', $order->id)->value('refund_time');
        $columnType = DB::table('information_schema.COLUMNS')
            ->where('TABLE_SCHEMA', DB::getDatabaseName())
            ->where('TABLE_NAME', 'deposit_records')
            ->where('COLUMN_NAME', 'refund_time')
            ->value('DATA_TYPE');

        $this->assertSame('datetime', strtolower((string) $columnType));
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', (string) $raw);
        $this->assertSame((string) $raw, $order->refund_time->format('Y-m-d H:i:s'));
    }
    /**
     * 入金退款用例的固定订单号（DEP-REFUND-TASK5-1001）。
     * 夹具据此建 deposit_records 行，断言与清理都围绕它定位。
     * @var string
     */
    private const ORDER_NO = 'DEP-REFUND-TASK5-1001';

    protected function setUp(): void
    {
        parent::setUp();
        $this->cleanup();
    }

    protected function tearDown(): void
    {
        $this->dropRefundFailureTrigger();
        $this->cleanup();
        parent::tearDown();
    }

    public function test_refund_job_has_bounded_queue_retry_policy(): void
    {
        $job = new RefundDepositPayment(123);

        $this->assertInstanceOf(ShouldQueue::class, $job);
        $this->assertSame(3, $job->tries);
        $this->assertSame([60, 300], $job->backoff());
    }

    public function test_settled_deposit_refund_withdraws_account_amount_outside_transaction(): void
    {
        [$order, $outbox] = $this->createPendingRefund();
        $calls = [];

        (new RefundDepositPayment($outbox->id))->handle(
            $this->gatewayReturning(DepositSettlementResult::settled('92001'), $calls)
        );

        $order->refresh();
        $outbox->refresh();
        $this->assertSame([[412355106, '100.00', 'DBRF-412355106-#' . self::ORDER_NO, 0]], $calls);
        $this->assertSame('refunded', $order->payment_status);
        $this->assertSame('refunded', $order->settlement_status);
        $this->assertSame('05', $order->status);
        $this->assertSame(92001, (int) $order->refund_mt4_ticket);
        $this->assertNotNull($order->refund_time);
        $this->assertSame('refunded', $outbox->status);
        $this->assertSame(1, $outbox->attempts);
        $this->assertSame('92001', $outbox->provider_reference);
        $this->assertNotNull($outbox->processed_at);
        $this->assertNull($outbox->locked_at);
    }

    public function test_refunded_outbox_replay_does_not_withdraw_again(): void
    {
        [, $outbox] = $this->createPendingRefund();
        $calls = [];
        $gateway = $this->gatewayReturning(DepositSettlementResult::settled('92002'), $calls);

        (new RefundDepositPayment($outbox->id))->handle($gateway);
        (new RefundDepositPayment($outbox->id))->handle($gateway);

        $this->assertCount(1, $calls);
    }

    public function test_connection_failure_is_retryable_and_restores_settled_order(): void
    {
        [$order, $outbox] = $this->createPendingRefund();
        $calls = [];

        (new RefundDepositPayment($outbox->id))->handle(
            $this->gatewayReturning(DepositSettlementResult::retryableNotSent('connection_failed'), $calls)
        );

        $order->refresh();
        $outbox->refresh();
        $this->assertCount(1, $calls);
        $this->assertSame('settled', $order->settlement_status);
        $this->assertSame('refund_pending', $order->payment_status);
        $this->assertSame('02', $order->status);
        $this->assertSame('retryable', $outbox->status);
        $this->assertSame(1, $outbox->attempts);
        $this->assertTrue($outbox->available_at->isFuture());
        $this->assertSame('connection_failed', $outbox->last_error_code);
        $this->assertNull($outbox->locked_at);
    }

    /** @dataProvider uncertainTransportProvider */
    public function test_uncertain_transport_is_terminal_unknown(string $errorCode): void
    {
        [$order, $outbox] = $this->createPendingRefund();
        $calls = [];
        $gateway = $this->gatewayReturning(DepositSettlementResult::unknown($errorCode), $calls);

        (new RefundDepositPayment($outbox->id))->handle($gateway);
        (new RefundDepositPayment($outbox->id))->handle($gateway);

        $order->refresh();
        $outbox->refresh();
        $this->assertCount(1, $calls);
        $this->assertSame('unknown', $order->settlement_status);
        $this->assertSame('refund_unknown', $order->payment_status);
        $this->assertSame('02', $order->status);
        $this->assertSame('unknown', $outbox->status);
        $this->assertSame($errorCode, $outbox->last_error_code);
        $this->assertNotNull($outbox->processed_at);
    }

    public function test_provider_rejection_is_terminal_without_marking_refunded(): void
    {
        [$order, $outbox] = $this->createPendingRefund();
        $calls = [];

        (new RefundDepositPayment($outbox->id))->handle(
            $this->gatewayReturning(DepositSettlementResult::rejected('provider_rejected'), $calls)
        );

        $order->refresh();
        $outbox->refresh();
        $this->assertCount(1, $calls);
        $this->assertSame('rejected', $order->settlement_status);
        $this->assertSame('refund_rejected', $order->payment_status);
        $this->assertSame('02', $order->status);
        $this->assertSame('rejected', $outbox->status);
        $this->assertSame('provider_rejected', $outbox->last_error_code);
        $this->assertNull($order->refund_mt4_ticket);
        $this->assertNull($order->refund_time);
    }

    public function test_recent_processing_refund_is_not_withdrawn_again(): void
    {
        [, $outbox] = $this->createPendingRefund();
        $outbox->status = 'processing';
        $outbox->locked_at = now();
        $outbox->saveOrFail();
        $calls = [];

        (new RefundDepositPayment($outbox->id))->handle(
            $this->gatewayReturning(DepositSettlementResult::settled('92010'), $calls)
        );

        $this->assertSame([], $calls);
        $this->assertSame('processing', $outbox->fresh()->status);
    }

    /** @dataProvider abandonedRefundLockProvider */
    public function test_abandoned_processing_refund_becomes_unknown_without_withdrawal($lockedAt): void
    {
        [$order, $outbox] = $this->createPendingRefund();
        $order->payment_status = 'refund_processing';
        $order->settlement_status = 'refund_processing';
        $order->saveOrFail();
        $outbox->status = 'processing';
        $outbox->locked_at = $lockedAt;
        $outbox->saveOrFail();
        $calls = [];

        (new RefundDepositPayment($outbox->id))->handle(
            $this->gatewayReturning(DepositSettlementResult::settled('92011'), $calls)
        );

        $order->refresh();
        $outbox->refresh();
        $this->assertSame([], $calls);
        $this->assertSame('refund_unknown', $order->payment_status);
        $this->assertSame('unknown', $order->settlement_status);
        $this->assertSame('unknown', $outbox->status);
        $this->assertSame('stale_refund_processing_claim', $outbox->last_error_code);
    }

    public function abandonedRefundLockProvider(): array
    {
        return [
            'missing lock time' => [null],
            'expired lock time' => [now()->subMinutes(10)],
        ];
    }

    public function test_local_commit_failure_after_external_refund_success_is_frozen_unknown(): void
    {
        [$order, $outbox] = $this->createPendingRefund();
        $this->createRefundFailureTrigger();
        $calls = [];

        (new RefundDepositPayment($outbox->id))->handle(
            $this->gatewayReturning(DepositSettlementResult::settled('92012'), $calls)
        );

        $order->refresh();
        $outbox->refresh();
        $this->assertCount(1, $calls);
        $this->assertSame('refund_unknown', $order->payment_status);
        $this->assertSame('unknown', $order->settlement_status);
        $this->assertSame('unknown', $outbox->status);
        $this->assertSame('local_commit_after_external_refund_success_failed', $outbox->last_error_code);
        $this->assertNull($outbox->provider_reference);
    }

    public function uncertainTransportProvider(): array
    {
        return [
            'write failure' => ['write_failed'],
            'read timeout' => ['read_timeout'],
        ];
    }

    /** @return array{0: DepositRecord, 1: PaymentSettlementOutbox} */
    private function createPendingRefund(): array
    {
        $order = DepositRecord::create([
            'user_id' => 412355106,
            'user_name' => 'refund-task5-user',
            'mt4_ticket' => 91001,
            'amount' => '100.00',
            'actual_amount' => '700.00',
            'provider_amount' => '700.00',
            'exchange_rate' => '7.00000000',
            'channel_name' => 'Task 5 Refund Fixture',
            'channel_order_no' => 'PROVIDER-REFUND-1001',
            'local_order_no' => self::ORDER_NO,
            'idempotency_key' => 'refund-task5-key',
            'gateway_code' => 'wppay',
            'merchant_id' => 'refund-task5-merchant',
            'currency' => 'CNY',
            'payment_status' => 'refund_pending',
            'settlement_status' => 'settled',
            'provider_payload_hash' => hash('sha256', 'refund-task5-payload'),
            'status' => '02',
            'remarks' => 'Task 5 refund fixture',
            'created_by' => 'test',
        ]);
        $outbox = PaymentSettlementOutbox::create([
            'deposit_record_id' => $order->id,
            'local_order_no' => self::ORDER_NO,
            'event_type' => 'deposit_refund',
            'status' => 'pending',
            'attempts' => 0,
            'payload_hash' => (string) $order->provider_payload_hash,
            'available_at' => now(),
        ]);

        return [$order, $outbox];
    }

    private function gatewayReturning(DepositSettlementResult $result, array &$calls): DepositRefundGateway
    {
        return new class($result, $calls) implements DepositRefundGateway {
            /**
             * 退款替身预设的单次结果（成功/拒绝/可重试）。驱动订单退款状态与 outbox 终态分支。
             * @var DepositSettlementResult
             */
            private $result;
            /**
             * 引用传入的调用记录。refund() 每次调用记下入参，供调用次数与参数断言。
             * @var array<int, array{0: int, 1: string, 2: string}>
             */
            private $calls;

            public function __construct(DepositSettlementResult $result, array &$calls)
            {
                $this->result = $result;
                $this->calls = &$calls;
            }

            public function refund(int $userId, string $amount, string $comment): DepositSettlementResult
            {
                $this->calls[] = [$userId, $amount, $comment, DB::transactionLevel()];

                return $this->result;
            }
        };
    }

    private function cleanup(): void
    {
        DB::table('payment_settlement_outbox')->where('local_order_no', self::ORDER_NO)->delete();
        DB::table('deposit_records')->where('local_order_no', self::ORDER_NO)->delete();
    }

    private function createRefundFailureTrigger(): void
    {
        $this->dropRefundFailureTrigger();
        DB::unprepared(<<<'SQL'
CREATE TRIGGER task5_fail_refund_update
BEFORE UPDATE ON deposit_records
FOR EACH ROW
BEGIN
    IF NEW.local_order_no = 'DEP-REFUND-TASK5-1001' AND NEW.refund_mt4_ticket IS NOT NULL THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'task5 forced local refund failure';
    END IF;
END
SQL
        );
    }

    private function dropRefundFailureTrigger(): void
    {
        try {
            DB::unprepared('DROP TRIGGER IF EXISTS task5_fail_refund_update');
        } catch (Throwable $exception) {
            // The connection can already be unavailable during framework shutdown.
        }
    }
}
