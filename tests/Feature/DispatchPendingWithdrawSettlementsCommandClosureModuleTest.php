<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/02
 * Time: 03:48
 */

declare(strict_types=1);

/**
 * 待处理提现结算扫描命令（payments:dispatch-withdraw-settlements）派发闭环测试。
 *
 * 文件功能：
 * - 验证扫描命令只派发到期且 pending/retryable 的提现扣款（withdraw_debit）事件。
 * - 验证退款（withdraw_refund）事件只对到期 pending/retryable 派发，blocked/refund_unknown/refunded 不派发。
 * - 验证被放弃的退款处理声明（缺锁或锁过期）被对账为 refund_unknown/stale_processing_claim，且不重复派发退款。
 *
 * 适用场景：
 * - 提现结算扫描命令的派发与退款声明对账回归测试。
 *
 * 入参例子：
 * - artisan payments:dispatch-withdraw-settlements（测试内直接执行）。
 *
 * 返回值：
 * - 命令退出码 0；只对预期 outbox 记录派发对应任务。
 *
 * 异常或失败场景：
 * - 未到期事件被派发、放弃的退款声明未对账或重复派发退款时断言失败。
 */

namespace Tests\Feature;

use App\Jobs\ProcessWithdrawFunding;
use App\Jobs\RefundWithdrawFunding;
use App\Models\WithdrawRecord;
use App\Models\WithdrawSettlementOutbox;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class DispatchPendingWithdrawSettlementsCommandClosureModuleTest extends TestCase
{
    /**
     * 本夹具订单号与幂等键的统一前缀（WD-FUNDING-SCAN-TASK3-）。扫描命令断言与清理都按前缀圈定。
     * @var string
     */
    private const PREFIX = 'WD-FUNDING-SCAN-TASK3-';

    protected function setUp(): void { parent::setUp(); $this->cleanup(); }
    protected function tearDown(): void { $this->cleanup(); parent::tearDown(); }

    /**
     * 验证扫描命令只派发到期且 pending/retryable 的提现扣款事件。
     */
    public function test_command_dispatches_only_due_pending_retryable_outbox_events(): void
    {
        // Shared MySQL may contain leftover outbox rows from other suites. Isolate assertions
        // to the rows created in this test instead of global push counts.
        $dueDebitIds = [
            $this->createOutbox('PENDING-DUE', 'pending', time() - 1),
            $this->createOutbox('RETRYABLE-DUE', 'retryable', time() - 1),
        ];
        $futureDebitId = $this->createOutbox('PENDING-FUTURE', 'pending', time() + 100);
        $this->createOutbox('PROCESSING-STALE', 'processing', time() - 1, time() - 600);
        $this->createOutbox('PROCESSING-RECENT', 'processing', time() - 1, time());
        $this->createOutbox('PROCESSED', 'processed', time() - 1);
        $this->createOutbox('CANCELLED', 'cancelled', time() - 1);
        $dueRefundIds = [
            $this->createOutbox('WRONG-EVENT', 'pending', time() - 1, null, 'withdraw_refund'),
            $this->createOutbox('REFUND-RETRYABLE', 'retryable', time() - 1, null, 'withdraw_refund'),
        ];
        $futureRefundId = $this->createOutbox('REFUND-FUTURE', 'pending', time() + 100, null, 'withdraw_refund');
        $this->createOutbox('REFUND-BLOCKED', 'blocked', time() - 1, null, 'withdraw_refund');
        $this->createOutbox('REFUND-UNKNOWN', 'refund_unknown', time() - 1, null, 'withdraw_refund');
        $this->createOutbox('REFUND-DONE', 'refunded', time() - 1, null, 'withdraw_refund');
        Queue::fake();

        $this->artisan('payments:dispatch-withdraw-settlements')->assertExitCode(0);

        foreach ($dueDebitIds as $outboxId) {
            Queue::assertPushed(ProcessWithdrawFunding::class, function ($job) use ($outboxId) {
                return $this->jobOutboxId($job) === $outboxId;
            });
        }
        Queue::assertNotPushed(ProcessWithdrawFunding::class, function ($job) use ($futureDebitId) {
            return $this->jobOutboxId($job) === $futureDebitId;
        });
        foreach ($dueRefundIds as $outboxId) {
            Queue::assertPushed(RefundWithdrawFunding::class, function ($job) use ($outboxId) {
                return $this->jobOutboxId($job) === $outboxId;
            });
        }
        Queue::assertNotPushed(RefundWithdrawFunding::class, function ($job) use ($futureRefundId) {
            return $this->jobOutboxId($job) === $futureRefundId;
        });
    }

    /**
     * 验证被放弃的退款声明被对账且不重复派发退款。
     */
    public function test_abandoned_refund_processing_claims_reconciled_without_redispatch(): void
    {
        [$missingLockOrder, $missingLock] = $this->createRefundClaim(
            'REFUND-PROCESSING-MISSING-LOCK',
            null
        );
        [$expiredOrder, $expired] = $this->createRefundClaim(
            'REFUND-PROCESSING-EXPIRED',
            time() - 600
        );
        [$recentOrder, $recent] = $this->createRefundClaim(
            'REFUND-PROCESSING-RECENT',
            time()
        );
        [$refundedOrder, $refunded] = $this->createRefundClaim(
            'REFUND-PROCESSING-ALREADY-REFUNDED',
            time() - 600,
            true
        );
        Queue::fake();

        $this->artisan('payments:dispatch-withdraw-settlements')->assertExitCode(0);

        $this->assertSame('refund_unknown', $missingLockOrder->refresh()->funding_status);
        $this->assertSame('refund_unknown', $missingLock->refresh()->status);
        $this->assertSame('stale_processing_claim', $missingLock->last_error_code);
        $this->assertSame('refund_unknown', $expiredOrder->refresh()->funding_status);
        $this->assertSame('refund_unknown', $expired->refresh()->status);
        $this->assertSame('processing', $recent->refresh()->status);
        $this->assertSame('refund_pending', $recentOrder->refresh()->funding_status);
        $this->assertSame('refunded', $refundedOrder->refresh()->funding_status);
        $this->assertSame('97120', (string) $refundedOrder->refund_mt4_ticket);
        $this->assertSame('refunded', $refunded->refresh()->status);
        $this->assertSame('97120', $refunded->provider_reference);
        Queue::assertNotPushed(RefundWithdrawFunding::class);
    }

    private function createOutbox(string $suffix, string $status, int $available, int $locked = null, string $event = 'withdraw_debit'): int
    {
        $local = self::PREFIX . $suffix; $hash = hash('sha256', $local);
        $order = WithdrawRecord::create([
            'user_id' => 412355006, 'user_name' => 'scan-user', 'mt4_ticket' => '', 'apply_amount' => '10.00',
            'actual_amount' => '10.00', 'fee' => '0.00', 'exchange_rate' => '1.00000000', 'rmb_fee' => '0.00',
            'bank_no' => 'BANK', 'bank_name' => 'Bank', 'bank_addr' => 'Addr', 'status' => 0,
            'local_order_no' => $local, 'third_order_no' => '', 'reject_reason' => '', 'mt4_return_status' => '',
            'idempotency_key' => $local, 'funding_status' => $status === 'processed' ? 'debited' : 'pending',
            'funding_payload_hash' => $hash, 'created_by' => 'test', 'updated_by' => '',
        ]);
        $outbox = WithdrawSettlementOutbox::create([
            'withdraw_record_id' => $order->id, 'local_order_no' => $local, 'event_type' => $event,
            'status' => $status, 'attempts' => 0, 'payload_hash' => $hash, 'available_at' => $available,
            'locked_at' => $locked, 'processed_at' => $status === 'processed' ? time() : null,
        ]);

        return (int) $outbox->id;
    }

    private function jobOutboxId(object $job): int
    {
        $property = new \ReflectionProperty($job, 'outboxId');
        $property->setAccessible(true);

        return (int) $property->getValue($job);
    }

    private function createRefundClaim(
        string $suffix,
        int $lockedAt = null,
        bool $alreadyRefunded = false
    ): array {
        $local = self::PREFIX . $suffix;
        $hash = hash('sha256', $local);
        $order = WithdrawRecord::create([
            'user_id' => 412355006,
            'user_name' => 'scan-refund-user',
            'mt4_ticket' => '93020',
            'apply_amount' => '10.00',
            'actual_amount' => '10.00',
            'fee' => '0.00',
            'exchange_rate' => '1.00000000',
            'rmb_fee' => '0.00',
            'bank_no' => 'BANK',
            'bank_name' => 'Bank',
            'bank_addr' => 'Addr',
            'status' => $alreadyRefunded ? 3 : 1,
            'local_order_no' => $local,
            'third_order_no' => '',
            'reject_reason' => '',
            'mt4_return_status' => '',
            'idempotency_key' => $local,
            'funding_status' => $alreadyRefunded ? 'refunded' : 'refund_pending',
            'funding_payload_hash' => $hash,
            'refund_mt4_ticket' => $alreadyRefunded ? '97120' : null,
            'refund_time' => $alreadyRefunded ? now()->subMinute() : null,
            'created_by' => 'test',
            'updated_by' => '',
        ]);
        $outbox = WithdrawSettlementOutbox::create([
            'withdraw_record_id' => $order->id,
            'local_order_no' => $local,
            'event_type' => 'withdraw_refund',
            'status' => 'processing',
            'attempts' => 1,
            'payload_hash' => $hash,
            'available_at' => time() - 1,
            'locked_at' => $lockedAt,
        ]);

        return [$order, $outbox];
    }

    private function cleanup(): void
    {
        DB::table('withdraw_settlement_outbox')->where('local_order_no', 'like', self::PREFIX . '%')->delete();
        DB::table('withdraw_records')->where('local_order_no', 'like', self::PREFIX . '%')->delete();
    }
}
