<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/02
 * Time: 01:49
 */

declare(strict_types=1);

/**
 * 佣金划转 Saga 派发闭环测试。
 *
 * 文件功能：
 * - 验证佣金划转相关网关契约（TradePasswordGateway、CommissionTransferFundingGateway、CommissionTransferAccountSnapshotGateway）绑定到 MT4 适配器。
 * - 验证派发命令只把到期或过期的非终态划转入队，过期财务步骤不被派发。
 * - 验证派发命令已注册到每分钟调度且带防重入锁。
 * - 验证队列任务委托给 Saga 服务且不会重复执行终态划转。
 *
 * 适用场景：
 * - 佣金划转 Saga 调度、派发与任务执行的回归测试。
 *
 * 入参例子：
 * - artisan commission:dispatch-transfers（测试内直接执行）。
 *
 * 返回值：
 * - 命令退出码 0；只有符合条件的划转被 Queue::assertPushed 命中。
 *
 * 异常或失败场景：
 * - 若终态/未到期/近期处理的划转被派发，或任务重复执行终态工作，断言失败。
 */

namespace Tests\Feature;

use App\Contracts\CommissionTransferAccountSnapshotGateway;
use App\Contracts\CommissionTransferFundingGateway;
use App\Contracts\TradePasswordGateway;
use App\Jobs\ProcessCommissionTransferSaga;
use App\Models\CommissionTransfer;
use App\Models\CommissionTransferOutbox;
use App\Services\CommissionTransfer\Mt4CommissionTransferAccountSnapshotGateway;
use App\Services\CommissionTransfer\Mt4CommissionTransferFundingGateway;
use App\Services\CommissionTransfer\Mt4TradePasswordGateway;
use App\Services\CommissionTransfer\CommissionTransferService;
use App\Services\CommissionTransfer\CommissionTransferAccountSnapshotResult;
use App\Services\CommissionTransfer\CommissionTransferCommandResult;
use App\Services\CommissionTransfer\TradePasswordVerificationResult;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

final class CommissionTransferSagaDispatchClosureModuleTest extends TestCase
{
    /**
     * 本夹具订单号与幂等键的统一前缀（CT-DISPATCH-CLOSURE-）。断言与清理都按前缀圈定。
     * @var string
     */
    private const PREFIX = 'CT-DISPATCH-CLOSURE-';

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
     * 验证佣金划转网关契约均绑定到 MT4 适配器实现。
     */
    public function test_mt4_gateway_contracts_are_bound_to_the_commission_transfer_adapters(): void
    {
        $this->assertInstanceOf(Mt4TradePasswordGateway::class, app(TradePasswordGateway::class));
        $this->assertInstanceOf(
            Mt4CommissionTransferFundingGateway::class,
            app(CommissionTransferFundingGateway::class)
        );
        $this->assertInstanceOf(
            Mt4CommissionTransferAccountSnapshotGateway::class,
            app(CommissionTransferAccountSnapshotGateway::class)
        );
    }

    /**
     * 验证派发命令只把到期或过期的非终态划转入队。
     */
    public function test_dispatcher_queues_only_due_or_stale_non_terminal_transfers(): void
    {
        $expected = [
            $this->createTransfer('PENDING-DUE', 'pending', 'verify', now()->subSecond()),
            $this->createTransfer('RETRYABLE-DUE', 'retryable', 'withdraw', now()->subSecond()),
            $this->createTransfer('STALE-SAFE', 'processing', 'accountinfo', now()->subSecond(), now()->subMinutes(10)),
        ];
        $unsafeStaleId = $this->createTransfer(
            'STALE-FINANCIAL',
            'processing',
            'deposit',
            now()->subSecond(),
            now()->subMinutes(10)
        );
        $this->createTransfer('PENDING-FUTURE', 'pending', 'verify', now()->addMinute());
        $this->createTransfer('PROCESSING-RECENT', 'processing', 'withdraw', now()->subSecond(), now());
        $this->createTransfer('COMPLETED', 'processed', 'finalize', now()->subSecond(), null, 'completed');
        $this->createTransfer('MANUAL', 'manual_reconcile_required', 'deposit', now()->subSecond(), null, 'manual_reconcile_required');
        Queue::fake();

        $this->artisan('commission:dispatch-transfers')->assertExitCode(0);

        Queue::assertPushed(ProcessCommissionTransferSaga::class, count($expected));
        foreach ($expected as $transferId) {
            Queue::assertPushed(ProcessCommissionTransferSaga::class, static function ($job) use ($transferId): bool {
                return $job->transferId() === $transferId;
            });
        }
        Queue::assertNotPushed(ProcessCommissionTransferSaga::class, static function ($job) use ($unsafeStaleId): bool {
            return $job->transferId() === $unsafeStaleId;
        });
    }

    /**
     * 验证派发命令已注册到每分钟调度且带防重入锁。
     */
    public function test_dispatcher_is_registered_on_the_minute_schedule(): void
    {
        $source = file_get_contents(app_path('Console/Kernel.php'));

        $this->assertIsString($source);
        $this->assertStringContainsString("command('commission:dispatch-transfers')", $source);
        $this->assertStringContainsString('->everyMinute()', $source);
        $this->assertStringContainsString('->withoutOverlapping(5)', $source);
    }

    /**
     * 验证队列任务委托给 Saga 服务且不重复执行终态划转。
     */
    public function test_queue_job_delegates_to_the_saga_service_without_reissuing_terminal_work(): void
    {
        $transferId = $this->createTransfer('JOB-TERMINAL', 'completed', 'completed', now(), null, 'completed');
        $service = new CommissionTransferService(
            new class implements \App\Contracts\TradePasswordGateway {
                public function verify(int $userId, string $password): TradePasswordVerificationResult
                {
                    return TradePasswordVerificationResult::verified();
                }
            },
            new class implements \App\Contracts\CommissionTransferFundingGateway {
                public function withdraw(int $userId, string $amount, string $comment): CommissionTransferCommandResult
                {
                    return CommissionTransferCommandResult::processed('1');
                }
                public function deposit(int $userId, string $amount, string $comment): CommissionTransferCommandResult
                {
                    return CommissionTransferCommandResult::processed('2');
                }
                public function compensate(int $userId, string $amount, string $comment): CommissionTransferCommandResult
                {
                    return CommissionTransferCommandResult::processed('3');
                }
            },
            new class implements \App\Contracts\CommissionTransferAccountSnapshotGateway {
                public function snapshot(int $userId): CommissionTransferAccountSnapshotResult
                {
                    return CommissionTransferAccountSnapshotResult::confirmed('0.00');
                }
            }
        );

        (new ProcessCommissionTransferSaga($transferId))->handle($service);

        $this->assertSame('completed', CommissionTransfer::whereKey($transferId)->value('status'));
    }

    private function createTransfer(
        string $suffix,
        string $outboxStatus,
        string $step,
        $availableAt,
        $lockedAt = null,
        string $transferStatus = 'pending'
    ): int {
        $hash = hash('sha256', self::PREFIX . $suffix);
        $transfer = CommissionTransfer::create([
            'local_order_no' => self::PREFIX . $suffix,
            'source_user_id' => 498820001,
            'target_user_id' => 498820002,
            'request_purpose' => 'front_commission_transfer',
            'idempotency_key' => strtolower(str_replace('_', '-', $suffix)),
            'payload_hash' => $hash,
            'payload_ciphertext' => null,
            'amount' => '500.00',
            'remark' => '',
            'status' => $transferStatus,
            'current_step' => $step,
            'reservation_status' => 'not_required',
            'attempts' => $outboxStatus === 'processing' ? 1 : 0,
            'available_at' => $availableAt,
            'locked_at' => $lockedAt,
        ]);
        CommissionTransferOutbox::create([
            'commission_transfer_id' => $transfer->id,
            'event_type' => 'process',
            'status' => $outboxStatus,
            'attempts' => $outboxStatus === 'processing' ? 1 : 0,
            'payload_hash' => $hash,
            'available_at' => $availableAt,
            'locked_at' => $lockedAt,
            'processed_at' => $outboxStatus === 'processed' ? now() : null,
        ]);

        return (int) $transfer->id;
    }

    private function cleanup(): void
    {
        $transferIds = DB::table('commission_transfers')
            ->where('local_order_no', 'like', self::PREFIX . '%')
            ->pluck('id');
        if ($transferIds->isNotEmpty()) {
            DB::table('commission_transfer_outbox')->whereIn('commission_transfer_id', $transferIds)->delete();
            DB::table('commission_transfers')->whereIn('id', $transferIds)->delete();
        }
    }
}
