<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/17
 * Time: 01:36
 */

/**
 * RefundWithdrawFundingJobClosureModuleTest
 *
 * 文件功能：
 * - 验证出金退款 Job 闭环：退款成功标记订单与 outbox、可重试网关失败保持 pending、payload 篡改阻断、过期/无锁 processing 认定 stale claim、终态声明尊重、MT4 退款网关响应映射。
 * - 输入：HTTP 请求与事务回滚的数据库夹具；输出：PHPUnit 断言结果。
 * - 明确不负责：不覆盖真实 MT4 网关与线上支付/出金通道（由网关契约测试锁定）。
 */

declare(strict_types=1);

namespace Tests\Feature;

use App\Contracts\WithdrawalRefundGateway;
use App\Jobs\RefundWithdrawFunding;
use App\Models\WithdrawRecord;
use App\Models\WithdrawSettlementOutbox;
use App\Services\Withdrawal\WithdrawalFundingResult;
use App\Services\Withdrawal\Mt4WithdrawalRefundGateway;
use App\Services\Mt4ManagerService;
use Illuminate\Database\Events\TransactionRolledBack;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Throwable;
use Tests\TestCase;

class RefundWithdrawFundingJobClosureModuleTest extends TestCase
{
    /**
     * 本夹具订单号与幂等键的统一前缀（WD-REFUND-TASK4-）。
     * setUp 建行、断言与 tearDown 清理都按前缀定位，避免撞既有数据；退款失败触发器也按它匹配。
     * @var string
     */
    private const PREFIX = 'WD-REFUND-TASK4-';

    protected function setUp(): void
    {
        parent::setUp();
        $this->dropRefundFailureTrigger();
        $this->cleanup();
    }

    protected function tearDown(): void
    {
        $this->dropRefundFailureTrigger();
        $this->cleanup();
        parent::tearDown();
    }

    public function test_refund_success_marks_order_and_outbox_refunded(): void
    {
        [$order, $outbox] = $this->createRefundPending();
        (new RefundWithdrawFunding($outbox->id))->handle(new class implements WithdrawalRefundGateway {
            public function refund(int $userId, string $amount, string $comment): WithdrawalFundingResult
            {
                return WithdrawalFundingResult::debited('94001');
            }
        });

        $order->refresh(); $outbox->refresh();
        $this->assertSame(3, (int) $order->status);
        $this->assertSame('refunded', $order->funding_status);
        $this->assertSame('refunded', $outbox->status);
        $this->assertSame('94001', (string) $order->refund_mt4_ticket);
    }

    public function test_retryable_gateway_failure_keeps_refund_pending(): void
    {
        [$order, $outbox] = $this->createRefundPending(); $calls = [];
        (new RefundWithdrawFunding($outbox->id))->handle($this->gateway(WithdrawalFundingResult::retryableNotSent('connection_failed'), $calls));
        $order->refresh(); $outbox->refresh();
        $this->assertSame([['25.00', 0]], $calls);
        $this->assertSame('refund_pending', $order->funding_status);
        $this->assertSame('retryable', $outbox->status);
    }

    public function test_unknown_result_then_debited_keeps_refund_unknown(): void
    {
        [$order, $outbox] = $this->createRefundPending(); $calls = [];
        (new RefundWithdrawFunding($outbox->id))->handle($this->gateway(WithdrawalFundingResult::unknown('read_timeout'), $calls));
        (new RefundWithdrawFunding($outbox->id))->handle($this->gateway(WithdrawalFundingResult::debited('94002'), $calls));
        $order->refresh(); $outbox->refresh();
        $this->assertCount(1, $calls);
        $this->assertSame('refund_unknown', $order->funding_status);
        $this->assertSame('refund_unknown', $outbox->status);
    }

    public function test_stale_processing_claim_ignored(): void
    {
        [$order, $outbox] = $this->createRefundPending();
        $outbox->status = 'processing'; $outbox->locked_at = now()->subMinutes(10); $outbox->saveOrFail(); $calls = [];
        (new RefundWithdrawFunding($outbox->id))->handle($this->gateway(WithdrawalFundingResult::debited('94003'), $calls));
        $this->assertSame([], $calls);
        $this->assertSame('refund_unknown', $order->refresh()->funding_status);
        $this->assertSame('refund_unknown', $outbox->refresh()->status);
    }

    public function test_processing_without_lock_marked_stale_claim(): void
    {
        [$order, $outbox] = $this->createRefundPending();
        $outbox->status = 'processing';
        $outbox->locked_at = null;
        $outbox->saveOrFail();
        $calls = [];

        (new RefundWithdrawFunding($outbox->id))->handle(
            $this->gateway(WithdrawalFundingResult::debited('940031'), $calls)
        );

        $this->assertSame([], $calls);
        $this->assertSame('refund_unknown', $order->refresh()->funding_status);
        $this->assertSame('refund_unknown', $outbox->refresh()->status);
        $this->assertSame('stale_processing_claim', $outbox->last_error_code);
    }

    public function test_payload_tamper_blocks_refund(): void
    {
        [$order, $outbox] = $this->createRefundPending();
        $outbox->payload_hash = hash('sha256', 'tampered'); $outbox->saveOrFail(); $calls = [];
        (new RefundWithdrawFunding($outbox->id))->handle($this->gateway(WithdrawalFundingResult::debited('94004'), $calls));
        $this->assertSame([], $calls);
        $this->assertSame('refund_unknown', $order->refresh()->funding_status);
    }

    public function test_pending_outbox_retry_succeeds_after_processing(): void
    {
        [$order, $outbox] = $this->createRefundPending();
        $order->funding_status = 'refund_pending'; $order->saveOrFail(); $outbox->status = 'pending'; $outbox->saveOrFail(); $calls = [];
        (new RefundWithdrawFunding($outbox->id))->handle($this->gateway(WithdrawalFundingResult::debited('94005'), $calls));
        $this->assertCount(1, $calls);
        $this->assertSame('refunded', $order->refresh()->funding_status);
    }

    public function test_gateway_exception_marks_refund_unknown(): void
    {
        [$order, $outbox] = $this->createRefundPending();
        $gateway = new class implements WithdrawalRefundGateway {
            public function refund(int $userId, string $amount, string $comment): WithdrawalFundingResult { throw new \RuntimeException('socket'); }
        };
        (new RefundWithdrawFunding($outbox->id))->handle($gateway);
        $this->assertSame('refund_unknown', $order->refresh()->funding_status);
        $this->assertSame('refund_unknown', $outbox->refresh()->status);
    }

    /** @dataProvider lateGatewayResultProvider */
    public function test_late_gateway_result_respects_terminal_claim(
        WithdrawalFundingResult $result,
        string $terminalStatus,
        string $terminalError = null,
        string $terminalTicket = null
    ): void {
        [$order, $outbox] = $this->createRefundPending();
        $gateway = $this->gatewayAfter(function () use (
            $order,
            $outbox,
            $terminalStatus,
            $terminalError,
            $terminalTicket
        ): void {
            $order->refresh();
            $order->funding_status = $terminalStatus;
            $order->funding_error_code = $terminalError;
            if ($terminalStatus === 'refunded') {
                $order->status = 3;
                $order->refund_mt4_ticket = $terminalTicket;
                $order->refund_time = now();
            }
            $order->saveOrFail();

            $outbox->refresh();
            $outbox->status = $terminalStatus;
            $outbox->provider_reference = $terminalTicket;
            $outbox->last_error_code = $terminalError;
            $outbox->processed_at = now();
            $outbox->locked_at = null;
            $outbox->saveOrFail();
        }, $result);

        (new RefundWithdrawFunding($outbox->id))->handle($gateway);

        $order->refresh();
        $outbox->refresh();
        $this->assertSame($terminalStatus, $order->funding_status);
        $this->assertSame($terminalStatus, $outbox->status);
        $this->assertSame($terminalError, $order->funding_error_code);
        $this->assertSame($terminalError, $outbox->last_error_code);
        $this->assertSame($terminalTicket, $outbox->provider_reference);
        $this->assertSame($terminalTicket, $order->refund_mt4_ticket === null
            ? null
            : (string) $order->refund_mt4_ticket);
    }

    public function lateGatewayResultProvider(): array
    {
        return [
            'success after refund unknown' => [
                WithdrawalFundingResult::debited('94101'),
                'refund_unknown',
                'manual_review_required',
                null,
            ],
            'retry after refunded' => [
                WithdrawalFundingResult::retryableNotSent('connection_failed'),
                'refunded',
                null,
                '97102',
            ],
            'unknown after refunded' => [
                WithdrawalFundingResult::unknown('read_timeout'),
                'refunded',
                null,
                '97103',
            ],
            'rejection after refund unknown' => [
                WithdrawalFundingResult::rejected('refund_forbidden'),
                'refund_unknown',
                'manual_review_required',
                null,
            ],
        ];
    }

    /** @dataProvider changedClaimIdentityProvider */
    public function test_changed_claim_identity_rejects_result(string $change): void
    {
        [$order, $outbox] = $this->createRefundPending();
        $gateway = $this->gatewayAfter(function () use ($order, $outbox, $change): void {
            $outbox->refresh();
            if ($change === 'attempt') {
                $outbox->attempts = (int) $outbox->attempts + 1;
            } elseif ($change === 'lock') {
                $outbox->locked_at = now()->addMinute();
            } elseif ($change === 'outbox_payload') {
                $outbox->payload_hash = hash('sha256', 'new-outbox-payload');
            } else {
                $order->refresh();
                $order->funding_payload_hash = hash('sha256', 'new-order-payload');
                $order->saveOrFail();
            }
            $outbox->saveOrFail();
        }, WithdrawalFundingResult::debited('94110'));

        (new RefundWithdrawFunding($outbox->id))->handle($gateway);

        $this->assertSame('refund_pending', $order->refresh()->funding_status);
        $this->assertNull($order->refund_mt4_ticket);
        $this->assertSame('processing', $outbox->refresh()->status);
        $this->assertNull($outbox->provider_reference);
    }

    public function changedClaimIdentityProvider(): array
    {
        return [
            'attempt changed' => ['attempt'],
            'lock changed' => ['lock'],
            'outbox payload changed' => ['outbox_payload'],
            'order payload changed' => ['order_payload'],
        ];
    }

    public function test_local_commit_failure_after_external_success_replaces_claim(): void
    {
        [$order, $outbox] = $this->createRefundPending();
        $this->createRefundFailureTrigger();
        $claimWasReplaced = false;
        Event::listen(TransactionRolledBack::class, function () use (
            $outbox,
            &$claimWasReplaced
        ): void {
            if ($claimWasReplaced || DB::transactionLevel() !== 0) {
                return;
            }
            DB::table('withdraw_settlement_outbox')
                ->where('id', $outbox->id)
                ->increment('attempts');
            $claimWasReplaced = true;
        });

        (new RefundWithdrawFunding($outbox->id))->handle(
            $this->gateway(WithdrawalFundingResult::debited('94111'))
        );

        $this->assertTrue($claimWasReplaced);
        $this->assertSame('refund_pending', $order->refresh()->funding_status);
        $this->assertNull($order->funding_error_code);
        $this->assertSame('processing', $outbox->refresh()->status);
        $this->assertSame(2, (int) $outbox->attempts);
    }

    public function test_local_commit_failure_marks_refund_unknown(): void
    {
        [$order, $outbox] = $this->createRefundPending();
        $this->createRefundFailureTrigger();

        (new RefundWithdrawFunding($outbox->id))->handle(
            $this->gateway(WithdrawalFundingResult::debited('94115'))
        );

        $this->assertSame('refund_unknown', $order->refresh()->funding_status);
        $this->assertSame('local_commit_after_external_success_failed', $order->funding_error_code);
        $this->assertSame('refund_unknown', $outbox->refresh()->status);
        $this->assertSame('local_commit_after_external_success_failed', $outbox->last_error_code);
        $this->assertNull($outbox->provider_reference);
        $this->assertNull($outbox->locked_at);
    }

    public function test_missing_withdraw_record_rejects_refund(): void
    {
        [$order, $outbox] = $this->createRefundPending();
        $order->forceDelete();
        $calls = [];

        (new RefundWithdrawFunding($outbox->id))->handle(
            $this->gateway(WithdrawalFundingResult::debited('94112'), $calls)
        );

        $outbox->refresh();
        $this->assertSame([], $calls);
        $this->assertSame('refund_rejected', $outbox->status);
        $this->assertSame('withdraw_record_missing', $outbox->last_error_code);
        $this->assertNotNull($outbox->processed_at);
        $this->assertNull($outbox->locked_at);
    }

    public function test_refund_not_ready_rejects_refund(): void
    {
        [$order, $outbox] = $this->createRefundPending();
        $order->funding_status = 'debited';
        $order->saveOrFail();
        $calls = [];

        (new RefundWithdrawFunding($outbox->id))->handle(
            $this->gateway(WithdrawalFundingResult::debited('94113'), $calls)
        );

        $this->assertSame([], $calls);
        $this->assertSame('debited', $order->refresh()->funding_status);
        $this->assertSame('refund_rejected', $outbox->refresh()->status);
        $this->assertSame('refund_not_ready', $outbox->last_error_code);
        $this->assertNotNull($outbox->processed_at);
    }

    public function test_already_refunded_keeps_terminal_state(): void
    {
        [$order, $outbox] = $this->createRefundPending();
        $refundTime = now()->subMinute()->startOfSecond();
        $order->status = 3;
        $order->funding_status = 'refunded';
        $order->funding_error_code = null;
        $order->refund_mt4_ticket = '97114';
        $order->refund_time = $refundTime;
        $order->saveOrFail();
        $calls = [];

        (new RefundWithdrawFunding($outbox->id))->handle(
            $this->gateway(WithdrawalFundingResult::unknown('read_timeout'), $calls)
        );

        $this->assertSame([], $calls);
        $this->assertSame('refunded', $order->refresh()->funding_status);
        $this->assertSame('97114', (string) $order->refund_mt4_ticket);
        $this->assertSame('refunded', $outbox->refresh()->status);
        $this->assertSame('97114', $outbox->provider_reference);
        $this->assertNull($outbox->last_error_code);
    }

    public function test_gateway_rejection_marks_refund_rejected(): void
    {
        [$order, $outbox] = $this->createRefundPending();

        (new RefundWithdrawFunding($outbox->id))->handle(
            $this->gateway(WithdrawalFundingResult::rejected('provider_refund_forbidden'))
        );

        $this->assertSame('refund_rejected', $order->refresh()->funding_status);
        $this->assertSame('provider_refund_forbidden', $order->funding_error_code);
        $this->assertSame('refund_rejected', $outbox->refresh()->status);
        $this->assertSame('provider_refund_forbidden', $outbox->last_error_code);
    }

    public function test_mt4_refund_gateway_maps_responses(): void
    {
        config(['mt4.user_sync_enabled' => true]);
        $manager = new class extends Mt4ManagerService {
            /**
             * response：预设的 MT4 deposit 返回报文；calls：deposit 收到的 [userId, amount, comment] 记录。
             * 验证退款入账链路对 MT4 的调用与响应处理。
             * @var mixed|array<int, array{0: int, 1: string, 2: string}>
             */
            public $response; public $calls = [];
            public function __construct() {}
            public function deposit($userId, $amount, $comment) { $this->calls[] = [$userId, $amount, $comment]; return $this->response; }
        };
        $gateway = new Mt4WithdrawalRefundGateway($manager);
        $manager->response = ['status' => 'ok', 'data' => ['95001']];
        $this->assertSame('debited', $gateway->refund(412355006, '25.00', 'refund')->status());
        $manager->response = ['status' => 'error', 'error_code' => 'connection_failed'];
        $this->assertSame('retryable_not_sent', $gateway->refund(412355006, '25.00', 'refund')->status());
        $manager->response = ['status' => 'error', 'error_code' => 'read_timeout'];
        $this->assertSame('unknown', $gateway->refund(412355006, '25.00', 'refund')->status());
        $manager->response = ['status' => 'ok', 'ticket' => 'bad'];
        $this->assertSame('unknown', $gateway->refund(412355006, '25.00', 'refund')->status());
        $this->assertCount(4, $manager->calls);
    }

    private function createRefundPending(): array
    {
        $local = self::PREFIX . uniqid('', true); $hash = hash('sha256', $local);
        $order = WithdrawRecord::create([
            'user_id' => 412355006, 'user_name' => 'refund-user', 'mt4_ticket' => '93001',
            'apply_amount' => '25.00', 'actual_amount' => '24.00', 'fee' => '1.00',
            'exchange_rate' => '7.00000000', 'rmb_fee' => '7.00', 'bank_no' => 'BANK',
            'bank_name' => 'Bank', 'bank_addr' => 'Addr', 'status' => 1,
            'local_order_no' => $local, 'third_order_no' => '', 'reject_reason' => 'reason',
            'mt4_return_status' => '', 'idempotency_key' => $local, 'funding_status' => 'refund_pending',
            'funding_payload_hash' => $hash, 'created_by' => 'test', 'updated_by' => '',
        ]);
        $outbox = WithdrawSettlementOutbox::create([
            'withdraw_record_id' => $order->id, 'local_order_no' => $local, 'event_type' => 'withdraw_refund',
            'status' => 'pending', 'attempts' => 0, 'payload_hash' => $hash, 'available_at' => time(),
        ]);
        return [$order, $outbox];
    }

    private function gateway(WithdrawalFundingResult $result, array &$calls = []): WithdrawalRefundGateway
    {
        return new class($result, $calls) implements WithdrawalRefundGateway {
            /**
             * result：退款替身预设的返回结果；calls：refund() 收到的入参记录（引用共享给用例）。
             * 验证退款网关命令的次数与参数。
             * @var WithdrawalFundingResult|array<int, array{0: int, 1: string, 2: string}>
             */
            private $result; private $calls;
            public function __construct($result, array &$calls) { $this->result = $result; $this->calls = &$calls; }
            public function refund(int $userId, string $amount, string $comment): WithdrawalFundingResult
            {
                $this->calls[] = [$amount, DB::transactionLevel()]; return $this->result;
            }
        };
    }

    private function gatewayAfter(
        callable $afterClaim,
        WithdrawalFundingResult $result
    ): WithdrawalRefundGateway {
        return new class($afterClaim, $result) implements WithdrawalRefundGateway {
            /**
             * 认领完成后、返回结果前执行的回调钩子。用例借它在关键时点改写数据，构造并发冲突场景。
             * @var callable
             */
            private $afterClaim;
            /**
             * 退款替身预设的返回结果，回调执行后原样返回，驱动退款成功/失败分支。
             * @var WithdrawalFundingResult
             */
            private $result;

            public function __construct(callable $afterClaim, WithdrawalFundingResult $result)
            {
                $this->afterClaim = $afterClaim;
                $this->result = $result;
            }

            public function refund(int $userId, string $amount, string $comment): WithdrawalFundingResult
            {
                ($this->afterClaim)();

                return $this->result;
            }
        };
    }

    private function createRefundFailureTrigger(): void
    {
        DB::unprepared(<<<'SQL'
CREATE TRIGGER task4_fail_withdraw_refund_update
BEFORE UPDATE ON withdraw_records
FOR EACH ROW
BEGIN
    IF NEW.local_order_no LIKE 'WD-REFUND-TASK4-%' AND NEW.refund_mt4_ticket IS NOT NULL THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'task4 forced local refund failure';
    END IF;
END
SQL
        );
    }

    private function dropRefundFailureTrigger(): void
    {
        try {
            DB::unprepared('DROP TRIGGER IF EXISTS task4_fail_withdraw_refund_update');
        } catch (Throwable $exception) {
            // The connection can already be unavailable during framework shutdown.
        }
    }

    private function cleanup(): void
    {
        DB::table('withdraw_settlement_outbox')->where('local_order_no', 'like', self::PREFIX . '%')->delete();
        DB::table('withdraw_records')->where('local_order_no', 'like', self::PREFIX . '%')->delete();
    }
}
