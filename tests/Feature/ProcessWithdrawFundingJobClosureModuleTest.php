<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/07/19
 * Time: 17:13
 */

/**
 * ProcessWithdrawFundingJobClosureModuleTest
 *
 * 文件功能：
 * - 验证出金资金化处理 Job 闭环：debited 结果在事务外提交、可重试结果回到 retryable 不在事务内重放、拒绝标记失败、过期 processing 冻结 unknown 不调网关、重放不调网关、哈希不匹配终态。
 * - 输入：HTTP 请求与事务回滚的数据库夹具；输出：PHPUnit 断言结果。
 * - 明确不负责：不覆盖真实 MT4 网关与线上支付/出金通道（由网关契约测试锁定）。
 */

declare(strict_types=1);

namespace Tests\Feature;

use App\Contracts\WithdrawalFundingGateway;
use App\Jobs\ProcessWithdrawFunding;
use App\Models\WithdrawRecord;
use App\Models\WithdrawSettlementOutbox;
use App\Services\Withdrawal\WithdrawalFundingResult;
use Illuminate\Database\Events\TransactionRolledBack;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ProcessWithdrawFundingJobClosureModuleTest extends TestCase
{
    /**
     * 出金资金命令夹具的基前缀（WD-FUNDING-TASK3-）。实例前缀在它之上再拼 pid 与随机数，
     * 保证多进程并行时订单号互不冲突。
     * @var string
     */
    private const PREFIX = 'WD-FUNDING-TASK3-';
    /**
     * setUp 生成的本进程专用前缀（基前缀 + pid + 随机数）。建单与按前缀清理 outbox 都用它定位，
     * 使本用例的数据在并行进程中可精确圈定。
     * @var string
     */
    private $prefix;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prefix = self::PREFIX . getmypid() . '-' . bin2hex(random_bytes(6)) . '-';
        $this->cleanup();
    }

    protected function tearDown(): void { $this->cleanup(); parent::tearDown(); }

    public function test_debited_result_is_committed_outside_external_transaction(): void
    {
        [$order, $outbox] = $this->createPending();
        $calls = [];
        (new ProcessWithdrawFunding($outbox->id))->handle($this->gateway(WithdrawalFundingResult::debited('93001'), $calls));

        $order->refresh(); $outbox->refresh();
        $this->assertSame('debited', $order->funding_status);
        $this->assertSame('93001', (string) $order->mt4_ticket);
        $this->assertSame('processed', $outbox->status);
        $this->assertSame('93001', $outbox->provider_reference);
        $this->assertSame(0, $calls[0][3]);
    }

    public function test_retryable_result_returns_to_retryable_without_replaying_inside_transaction(): void
    {
        [$order, $outbox] = $this->createPending();
        (new ProcessWithdrawFunding($outbox->id))->handle($this->gateway(WithdrawalFundingResult::retryableNotSent('connection_failed')));

        $order->refresh(); $outbox->refresh();
        $this->assertSame('pending', $order->funding_status);
        $this->assertSame('retryable', $outbox->status);
        $this->assertTrue($outbox->available_at->isFuture());
    }

    public function test_rejected_result_marks_withdrawal_failed(): void
    {
        [$order, $outbox] = $this->createPending();
        (new ProcessWithdrawFunding($outbox->id))->handle($this->gateway(WithdrawalFundingResult::rejected('insufficient_funds')));

        $order->refresh(); $outbox->refresh();
        $this->assertSame(3, (int) $order->status);
        $this->assertSame('rejected', $order->funding_status);
        $this->assertSame('rejected', $outbox->status);
    }

    public function test_stale_processing_is_frozen_unknown_without_gateway_call(): void
    {
        [$order, $outbox] = $this->createPending();
        $order->funding_status = 'processing'; $order->saveOrFail();
        $outbox->status = 'processing'; $outbox->locked_at = now()->subMinutes(10); $outbox->saveOrFail();
        $calls = [];
        (new ProcessWithdrawFunding($outbox->id))->handle($this->gateway(WithdrawalFundingResult::debited('93002'), $calls));

        $order->refresh(); $outbox->refresh();
        $this->assertSame([], $calls);
        $this->assertSame('unknown', $order->funding_status);
        $this->assertSame('unknown', $outbox->status);
    }

    public function test_processed_replay_does_not_call_gateway(): void
    {
        [$order, $outbox] = $this->createPending();
        $order->funding_status = 'debited'; $order->mt4_ticket = '93003'; $order->saveOrFail();
        $outbox->status = 'processed'; $outbox->provider_reference = '93003'; $outbox->saveOrFail();
        $calls = [];
        (new ProcessWithdrawFunding($outbox->id))->handle($this->gateway(WithdrawalFundingResult::debited('93004'), $calls));
        $this->assertSame([], $calls);
    }

    public function test_hash_mismatch_is_terminal_without_gateway_call(): void
    {
        [$order, $outbox] = $this->createPending();
        $outbox->payload_hash = hash('sha256', 'tampered'); $outbox->saveOrFail();
        $calls = [];
        (new ProcessWithdrawFunding($outbox->id))->handle($this->gateway(WithdrawalFundingResult::debited('93005'), $calls));
        $order->refresh(); $outbox->refresh();
        $this->assertSame([], $calls);
        $this->assertSame('rejected', $order->funding_status);
        $this->assertSame('rejected', $outbox->status);
    }

    public function test_result_from_replaced_claim_cannot_complete_the_new_claim(): void
    {
        [$order, $outbox] = $this->createPending();
        $gateway = $this->gatewayReplacingClaim(
            $outbox->id,
            WithdrawalFundingResult::debited('93006')
        );

        (new ProcessWithdrawFunding($outbox->id))->handle($gateway);

        $this->assertReplacementClaimRemainsOwned($order, $outbox);
    }

    /** @dataProvider replacedClaimNonSuccessResultProvider */
    public function test_non_success_result_from_replaced_claim_cannot_update_the_new_claim(string $status): void
    {
        [$order, $outbox] = $this->createPending();
        $result = $status === 'retryable_not_sent'
            ? WithdrawalFundingResult::retryableNotSent('connection_failed')
            : ($status === 'unknown'
                ? WithdrawalFundingResult::unknown('read_timeout')
                : WithdrawalFundingResult::rejected('insufficient_funds'));

        (new ProcessWithdrawFunding($outbox->id))->handle(
            $this->gatewayReplacingClaim($outbox->id, $result)
        );

        $this->assertReplacementClaimRemainsOwned($order, $outbox);
    }

    public function replacedClaimNonSuccessResultProvider(): array
    {
        return [
            'retryable result' => ['retryable_not_sent'],
            'unknown result' => ['unknown'],
            'rejected result' => ['rejected'],
        ];
    }

    public function test_local_commit_failure_from_replaced_claim_cannot_freeze_the_new_claim(): void
    {
        [$order, $outbox] = $this->createPending();
        $replaceOnRollback = true;
        Event::listen(TransactionRolledBack::class, function () use (&$replaceOnRollback, $outbox): void {
            if (!$replaceOnRollback) {
                return;
            }
            $replaceOnRollback = false;
            DB::table('withdraw_settlement_outbox')->where('id', $outbox->id)->update([
                'attempts' => 2,
                'status' => 'processing',
                'locked_at' => time(),
            ]);
        });

        (new ProcessWithdrawFunding($outbox->id))->handle(
            $this->gateway(WithdrawalFundingResult::debited('not-a-ticket'))
        );

        $this->assertReplacementClaimRemainsOwned($order, $outbox);
    }

    private function assertReplacementClaimRemainsOwned(
        WithdrawRecord $order,
        WithdrawSettlementOutbox $outbox
    ): void {
        $order->refresh();
        $outbox->refresh();
        $this->assertSame('processing', $order->funding_status);
        $this->assertSame('', (string) $order->mt4_ticket);
        $this->assertSame('processing', $outbox->status);
        $this->assertSame(2, (int) $outbox->attempts);
        $this->assertNull($outbox->provider_reference);
        $this->assertNotNull($outbox->locked_at);
        $this->assertNull($outbox->processed_at);
        $this->assertNull($outbox->last_error_code);
        $this->assertNull($order->funding_error_code);
    }

    private function createPending(): array
    {
        $local = $this->prefix . uniqid('', true);
        $hash = hash('sha256', $local);
        $order = WithdrawRecord::create([
            'user_id' => 412355006, 'user_name' => 'task3-user', 'mt4_ticket' => '',
            'apply_amount' => '25.00', 'actual_amount' => '24.00', 'fee' => '1.00',
            'exchange_rate' => '7.00000000', 'rmb_fee' => '7.00', 'bank_no' => 'BANK',
            'bank_name' => 'Bank', 'bank_addr' => 'Addr', 'status' => 0,
            'local_order_no' => $local, 'third_order_no' => '', 'reject_reason' => '',
            'mt4_return_status' => '', 'idempotency_key' => $local, 'funding_status' => 'pending',
            'funding_payload_hash' => $hash, 'created_by' => 'test', 'updated_by' => '',
        ]);
        $outbox = WithdrawSettlementOutbox::create([
            'withdraw_record_id' => $order->id, 'local_order_no' => $local, 'event_type' => 'withdraw_debit',
            'status' => 'pending', 'attempts' => 0, 'payload_hash' => $hash, 'available_at' => time(),
        ]);
        return [$order, $outbox];
    }

    private function gateway(WithdrawalFundingResult $result, array &$calls = []): WithdrawalFundingGateway
    {
        return new class($result, $calls) implements WithdrawalFundingGateway {
            /**
             * result：出金替身预设的返回结果；calls：withdraw() 收到的入参记录（引用共享给用例）。
             * 验证资金网关命令的次数与参数。
             * @var WithdrawalFundingResult|array<int, array{0: int, 1: string, 2: string}>
             */
            private $result; private $calls;
            public function __construct($result, array &$calls) { $this->result = $result; $this->calls = &$calls; }
            public function withdraw(int $userId, string $amount, string $comment): WithdrawalFundingResult {
                $this->calls[] = [$userId, $amount, $comment, DB::transactionLevel()]; return $this->result;
            }
        };
    }

    private function gatewayReplacingClaim(
        int $outboxId,
        WithdrawalFundingResult $result
    ): WithdrawalFundingGateway {
        return new class($outboxId, $result) implements WithdrawalFundingGateway {
            /**
             * 替身记住的 outbox 行主键。withdraw() 期间据此更新该行，构造"外部命令与本地状态并发变更"场景。
             * @var int
             */
            private $outboxId;
            /**
             * 出金替身预设的返回结果，驱动出金成功/失败/可重试分支。
             * @var WithdrawalFundingResult
             */
            private $result;

            public function __construct(int $outboxId, WithdrawalFundingResult $result)
            {
                $this->outboxId = $outboxId;
                $this->result = $result;
            }

            public function withdraw(int $userId, string $amount, string $comment): WithdrawalFundingResult
            {
                DB::table('withdraw_settlement_outbox')->where('id', $this->outboxId)->update([
                    'attempts' => 2,
                    'status' => 'processing',
                    'locked_at' => time(),
                ]);

                return $this->result;
            }
        };
    }

    private function cleanup(): void
    {
        if (!$this->prefix) {
            return;
        }
        DB::table('withdraw_settlement_outbox')->where('local_order_no', 'like', $this->prefix . '%')->delete();
        DB::table('withdraw_records')->where('local_order_no', 'like', $this->prefix . '%')->delete();
    }
}
