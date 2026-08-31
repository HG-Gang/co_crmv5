<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/02
 * Time: 01:51
 */

declare(strict_types=1);

/**
 * 待处理充值结算扫描命令（payments:dispatch-deposit-settlements）派发闭环测试。
 *
 * 文件功能：
 * - 验证扫描命令只派发到期且状态为 pending/retryable 的充值结算出站事件。
 * - 验证 deposit_settlement 与 deposit_refund 两类事件按各自任务派发。
 * - 验证未到期、已处理、无锁/近期锁定的 processing 事件不被派发。
 *
 * 适用场景：
 * - 充值结算扫描命令的派发规则回归测试。
 *
 * 入参例子：
 * - artisan payments:dispatch-deposit-settlements（测试内直接执行）。
 *
 * 返回值：
 * - 命令退出码 0；Queue::assertPushed 只命中预期数量的结算/退款任务。
 *
 * 异常或失败场景：
 * - 若派发数量与预期不符，断言失败。
 */

namespace Tests\Feature;

use App\Jobs\SettleDepositPayment;
use App\Jobs\RefundDepositPayment;
use App\Models\DepositRecord;
use App\Models\PaymentSettlementOutbox;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class DispatchPendingDepositSettlementsCommandClosureModuleTest extends TestCase
{
    /**
     * 本夹具订单号的统一前缀（DEP-SETTLEMENT-SCAN-）。扫描命令断言与清理都按前缀圈定。
     * @var string
     */
    private const PREFIX = 'DEP-SETTLEMENT-SCAN-';

    protected function setUp(): void
    {
        parent::setUp();
        $this->cleanup();
    }

    protected function tearDown(): void
    {
        $this->cleanup();
        parent::tearDown();
    }

    /**
     * 验证扫描命令只派发到期且 pending/retryable 的充值结算与退款事件。
     */
    public function test_dispatches_only_due_pending_and_retryable_outbox_events(): void
    {
        $this->createOutbox('PENDING-DUE', 'pending', now()->subSecond());
        $this->createOutbox('RETRYABLE-DUE', 'retryable', now()->subSecond());
        $this->createOutbox('PENDING-FUTURE', 'pending', now()->addMinute());
        $this->createOutbox('PROCESSED', 'processed', now()->subSecond());
        $this->createOutbox('PROCESSING-STALE', 'processing', now()->subSecond(), now()->subMinutes(10));
        $this->createOutbox('PROCESSING-NO-LOCK', 'processing', now()->subSecond(), null);
        $this->createOutbox('PROCESSING-RECENT', 'processing', now()->subSecond(), now());
        $this->createOutbox('REFUND-PENDING', 'pending', now()->subSecond(), null, 'deposit_refund');
        $this->createOutbox('REFUND-STALE', 'processing', now()->subSecond(), now()->subMinutes(10), 'deposit_refund');
        Queue::fake();

        $this->artisan('payments:dispatch-deposit-settlements')->assertExitCode(0);

        Queue::assertPushed(SettleDepositPayment::class, 4);
        Queue::assertPushed(RefundDepositPayment::class, 2);
    }

    private function createOutbox(
        string $suffix,
        string $status,
        $availableAt,
        $lockedAt = null,
        string $eventType = 'deposit_settlement'
    ): void
    {
        $localOrderNo = self::PREFIX . $suffix;
        $payloadHash = hash('sha256', $localOrderNo);
        $order = DepositRecord::create([
            'user_id' => 412355006,
            'user_name' => 'settlement-scan-user',
            'mt4_ticket' => 0,
            'amount' => '10.00',
            'actual_amount' => '70.00',
            'provider_amount' => '70.00',
            'exchange_rate' => '7.00000000',
            'channel_name' => 'Settlement Scan Fixture',
            'channel_order_no' => 'PROVIDER-' . $suffix,
            'local_order_no' => $localOrderNo,
            'idempotency_key' => 'settlement-scan-' . strtolower($suffix),
            'gateway_code' => 'wppay',
            'merchant_id' => 'settlement-scan-merchant',
            'currency' => 'CNY',
            'payment_status' => 'success',
            'settlement_status' => 'pending',
            'provider_payload_hash' => $payloadHash,
            'status' => '01',
            'remarks' => 'Settlement scan fixture',
            'created_by' => 'test',
        ]);
        PaymentSettlementOutbox::create([
            'deposit_record_id' => $order->id,
            'local_order_no' => $localOrderNo,
            'event_type' => $eventType,
            'status' => $status,
            'attempts' => 0,
            'payload_hash' => $payloadHash,
            'available_at' => $availableAt,
            'locked_at' => $lockedAt,
            'processed_at' => $status === 'processed' ? now() : null,
        ]);
    }

    private function cleanup(): void
    {
        DB::table('payment_settlement_outbox')->where('local_order_no', 'like', self::PREFIX . '%')->delete();
        DB::table('deposit_records')->where('local_order_no', 'like', self::PREFIX . '%')->delete();
    }
}
