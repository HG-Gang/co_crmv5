<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/01
 * Time: 11:37
 */

/**
 * CommissionTransferSagaServiceTest
 *
 * 文件功能：
 * - 验证佣金划转 Saga 服务：外部调用序列与本地状态终结、同载荷幂等重放与改载荷拒绝、失败小额划转仍消耗自然日限额、目标入金拒绝按出金票补偿、未知入金绝不补偿或重发。
 * - 输入：HTTP 请求与事务回滚的数据库夹具；输出：PHPUnit 断言结果。
 * - 明确不负责：不覆盖真实 MT4 网关与线上支付/出金通道（由网关契约测试锁定）。
 */

declare(strict_types=1);

namespace Tests\Feature;

use App\Contracts\CommissionTransferAccountSnapshotGateway;
use App\Contracts\CommissionTransferFundingGateway;
use App\Contracts\TradePasswordGateway;
use App\Models\CommissionTransfer;
use App\Services\CommissionTransfer\CommissionTransferAccountSnapshotResult;
use App\Services\CommissionTransfer\CommissionTransferCommandResult;
use App\Services\CommissionTransfer\CommissionTransferService;
use App\Services\CommissionTransfer\TradePasswordVerificationResult;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;
use Tests\Support\MySqlFixtureMutex;
use Tests\Support\MySqlAutoIncrementSnapshot;
use Tests\Support\MySqlTableFingerprint;

final class CommissionTransferSagaServiceTest extends TestCase
{
    /**
     * 夹具会插入数据的表清单。setUp 捕获这些表的 AUTO_INCREMENT 基线，tearDown 恢复，
     * 防止共享测试库的自增计数被测试抬高。
     * @var array<int, string>
     */
    private const AUTO_INCREMENT_TABLES = [
        'user_infos',
        'commission_transfers',
        'commission_transfer_outbox',
        'commission_records',
        'operation_logs',
    ];

    /**
     * 转账发起方（佣金来源）业务用户 ID。setUp 用 unusedUserPair 分配并以余额 1000.00 建夹具行，
     * 转账金额断言围绕该账户展开。
     * @var int
     */
    private $sourceUserId;

    /**
     * 转账接收方业务用户 ID。setUp 以 sourceUserId 为父代理建行，构成 saga 校验的层级关系。
     * @var int
     */
    private $targetUserId;

    /**
     * 夹具创建的业务用户主键清单（user_infos/user_logins）。tearDown 据此删除夹具行。
     * @var array<int, int>
     */
    private $createdUserIds = [];

    /**
     * 夹具创建的 commission_transfers 主键清单。tearDown 按其删除转账单。
     * @var array<int, int>
     */
    private $createdTransferIds = [];

    /**
     * 夹具创建的 commission_transfer_outbox 主键清单。tearDown 据其清理发件箱行。
     * @var array<int, int>
     */
    private $createdOutboxIds = [];

    /**
     * 夹具写入 commission_records 的 unique_id 清单，用于精确删除本用例产生的佣金流水。
     * @var array<int, string>
     */
    private $createdLedgerUniqueIds = [];

    /**
     * 用例产生的 operation_logs.order_no 清单。tearDown 按单号删除操作日志夹具行。
     * @var array<int, string>
     */
    private $createdOrderNumbers = [];

    /**
     * setUp 捕获的 MySqlAutoIncrementSnapshot 实例；tearDown 调用 restore() 还原各表自增值。null 表示尚未捕获。
     * @var MySqlAutoIncrementSnapshot|null
     */
    private $autoIncrementSnapshot;

    /**
     * MySqlFixtureMutex 实例。串行化共享测试库上的夹具准备与清理，避免并行运行互相踩踏。
     * @var MySqlFixtureMutex|null
     */
    private $fixtureMutex;

    /**
     * setUp 捕获的各表行指纹基线。tearDown 重新捕获比对，不一致即夹具泄漏，测试失败上报。
     * @var array<string, array<string, int|string>>
     */
    private $tableFingerprints = [];

    /**
     * 用例开始前 commission_records 已有行的指纹（unique_id => digest）。清理时跳过，防止误删既有数据。
     * @var array<string, string>
     */
    private $initialLedgerFingerprints = [];

    /**
     * 用例过程中记录的佣金流水行指纹（unique_id => digest）。删除前比对，行被外部改动时拒绝删除。
     * @var array<string, string>
     */
    private $ledgerRowFingerprints = [];

    protected function setUp(): void
    {
        parent::setUp();
        try {
            $this->fixtureMutex = new MySqlFixtureMutex();
            $this->fixtureMutex->acquire();
            $this->tableFingerprints = MySqlTableFingerprint::capture(self::AUTO_INCREMENT_TABLES);
            $this->autoIncrementSnapshot = MySqlAutoIncrementSnapshot::capture(self::AUTO_INCREMENT_TABLES);
            $this->initialLedgerFingerprints = $this->captureLedgerFingerprints();
            [$this->sourceUserId, $this->targetUserId] = $this->unusedUserPair();
            $this->insertUser($this->sourceUserId, 0, '1000.00');
            $this->insertUser($this->targetUserId, $this->sourceUserId, '25.00');
        } catch (\Throwable $exception) {
            $this->abortFixtureSetup($exception);
        }
    }

    private function abortFixtureSetup(\Throwable $cause): void
    {
        $failures = [];
        $this->cleanupFixture($failures);
        try {
            if ($this->autoIncrementSnapshot !== null) {
                $this->autoIncrementSnapshot->restore();
            }
        } catch (\Throwable $exception) {
            $failures[] = 'auto_increment_restore: ' . $exception->getMessage();
        }
        try {
            if ($this->fixtureMutex !== null) {
                $this->fixtureMutex->releaseWithDisconnectFallback();
            }
        } catch (\Throwable $exception) {
            $failures[] = 'mutex_release: ' . $exception->getMessage();
        }
        try {
            parent::tearDown();
        } catch (\Throwable $exception) {
            $failures[] = 'parent_teardown: ' . $exception->getMessage();
        }
        $this->resetFixtureState();
        if ($failures !== []) {
            throw new \RuntimeException(
                'Commission saga fixture setup failed: ' . implode(' | ', $failures),
                0,
                $cause
            );
        }

        throw $cause;
    }

    protected function tearDown(): void
    {
        $cleanupFailures = [];
        try {
            $this->cleanupFixture($cleanupFailures);
        } finally {
            try {
                if ($this->autoIncrementSnapshot !== null) {
                    $this->autoIncrementSnapshot->restore();
                }
            } catch (\Throwable $exception) {
                $cleanupFailures[] = 'auto_increment_restore: ' . $exception->getMessage();
            }
            try {
                $after = MySqlTableFingerprint::capture(self::AUTO_INCREMENT_TABLES);
                if ($after !== $this->tableFingerprints) {
                    // 清理失败时保留表级前后指纹，明确指出残留来自数据、结构还是自增值，避免仅凭通用错误猜测。
                    $cleanupFailures[] = 'table_fingerprint_mismatch: ' . json_encode([
                        'before' => $this->tableFingerprints,
                        'after' => $after,
                    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
                }
            } catch (\Throwable $exception) {
                $cleanupFailures[] = 'table_fingerprint_capture: ' . $exception->getMessage();
            }
            try {
                if ($this->fixtureMutex !== null) {
                    $this->fixtureMutex->releaseWithDisconnectFallback();
                }
            } catch (\Throwable $exception) {
                $cleanupFailures[] = 'mutex_release: ' . $exception->getMessage();
            }
            try {
                parent::tearDown();
            } catch (\Throwable $exception) {
                $cleanupFailures[] = 'parent_teardown: ' . $exception->getMessage();
            }
        }

        $this->resetFixtureState();
        if ($cleanupFailures !== []) {
            throw new \RuntimeException(
                'Commission saga fixture teardown failures: ' . implode(' | ', $cleanupFailures)
            );
        }
    }

    public function test_success_runs_the_exact_external_sequence_then_finalizes_local_state(): void
    {
        $password = new RecordingTradePasswordGateway();
        $funding = new RecordingCommissionTransferFundingGateway();
        $snapshot = new RecordingCommissionTransferSnapshotGateway([
            $this->sourceUserId => '875.00',
            $this->targetUserId => '150.00',
        ]);
        $service = new CommissionTransferService($password, $funding, $snapshot);

        $result = $service->createOrRetrieve(
            $this->sourceUserId,
            $this->targetUserId,
            '125.00',
            'trade-secret',
            'successful transfer',
            'front_commission_transfer',
            'success-key'
        );
        $this->rememberTransfer($result['transfer']);

        $this->assertTrue($result['created']);
        $this->assertSame('completed', $result['transfer']->fresh()->status);
        $this->assertSame([[$this->sourceUserId, 'trade-secret']], $password->calls);
        $this->assertSame([
            ['withdraw', $this->sourceUserId, '125.00', 'WBCT-' . $this->targetUserId],
            ['deposit', $this->targetUserId, '125.00', 'DBCT-' . $this->sourceUserId],
        ], $funding->calls);
        $this->assertSame([$this->sourceUserId, $this->targetUserId], $snapshot->calls);
        $this->assertSame(875.0, (float) DB::table('user_infos')->where('user_id', $this->sourceUserId)->value('total_funds'));
        $this->assertSame(150.0, (float) DB::table('user_infos')->where('user_id', $this->targetUserId)->value('total_funds'));
        $this->assertSame(2, DB::table('commission_records')->where('data_type', 'transfer')->whereIn('agent_id', [$this->sourceUserId, $this->targetUserId])->count());
        $this->assertNull($result['transfer']->fresh()->payload_ciphertext);
    }

    public function test_idempotency_replays_same_payload_and_rejects_a_changed_payload(): void
    {
        $service = $this->service();
        $first = $service->createOrRetrieve(
            $this->sourceUserId,
            $this->targetUserId,
            '500.00',
            'trade-secret',
            '',
            'front_commission_transfer',
            'same-key'
        );
        $this->rememberTransfer($first['transfer']);
        $replay = $service->createOrRetrieve(
            $this->sourceUserId,
            $this->targetUserId,
            '500.00',
            'trade-secret',
            '',
            'front_commission_transfer',
            'same-key'
        );
        $this->rememberTransfer($replay['transfer']);

        $this->assertFalse($replay['created']);
        $this->assertSame($first['transfer']->id, $replay['transfer']->id);

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('idempotency_conflict');
        $service->createOrRetrieve(
            $this->sourceUserId,
            $this->targetUserId,
            '501.00',
            'trade-secret',
            '',
            'front_commission_transfer',
            'same-key'
        );
    }

    public function test_failed_small_transfer_still_consumes_the_natural_day_limit(): void
    {
        $password = new RecordingTradePasswordGateway();
        $funding = new RecordingCommissionTransferFundingGateway();
        $funding->withdrawResults = [CommissionTransferCommandResult::rejected('insufficient_funds')];
        $service = new CommissionTransferService(
            $password,
            $funding,
            new RecordingCommissionTransferSnapshotGateway('1000.00')
        );

        $first = $service->createOrRetrieve(
            $this->sourceUserId,
            $this->targetUserId,
            '100.00',
            'trade-secret',
            '',
            'front_commission_transfer',
            'small-first'
        );
        $this->rememberTransfer($first['transfer']);
        $second = $service->createOrRetrieve(
            $this->sourceUserId,
            $this->targetUserId,
            '100.00',
            'trade-secret',
            '',
            'front_commission_transfer',
            'small-second'
        );
        $this->rememberTransfer($second['transfer']);

        $this->assertSame('rejected', $first['transfer']->fresh()->status);
        $this->assertSame('small_transfer_daily_limit', $second['transfer']->fresh()->last_error_code);
        $this->assertSame(2, count($password->calls));
        $this->assertSame(1, count(array_filter($funding->calls, static function (array $call): bool {
            return $call[0] === 'withdraw';
        })));
    }

    public function test_explicit_target_deposit_rejection_compensates_with_the_withdraw_ticket(): void
    {
        $funding = new RecordingCommissionTransferFundingGateway();
        $funding->depositResults = [CommissionTransferCommandResult::rejected('target_rejected')];
        $service = new CommissionTransferService(
            new RecordingTradePasswordGateway(),
            $funding,
            new RecordingCommissionTransferSnapshotGateway('1000.00')
        );

        $result = $service->createOrRetrieve(
            $this->sourceUserId,
            $this->targetUserId,
            '500.00',
            'trade-secret',
            '',
            'front_commission_transfer',
            'compensate-key'
        );
        $this->rememberTransfer($result['transfer']);

        $this->assertSame('compensated', $result['transfer']->fresh()->status);
        $this->assertSame([
            ['withdraw', $this->sourceUserId, '500.00', 'WBCT-' . $this->targetUserId],
            ['deposit', $this->targetUserId, '500.00', 'DBCT-' . $this->sourceUserId],
            ['compensate', $this->sourceUserId, '500.00', 'DBCR-' . $this->sourceUserId . '-#700001'],
        ], $funding->calls);
    }

    public function test_unknown_target_deposit_never_compensates_or_resends_money(): void
    {
        $funding = new RecordingCommissionTransferFundingGateway();
        $funding->depositResults = [CommissionTransferCommandResult::unknown('read_timeout')];
        $service = new CommissionTransferService(
            new RecordingTradePasswordGateway(),
            $funding,
            new RecordingCommissionTransferSnapshotGateway('1000.00')
        );

        $result = $service->createOrRetrieve(
            $this->sourceUserId,
            $this->targetUserId,
            '500.00',
            'trade-secret',
            '',
            'front_commission_transfer',
            'deposit-unknown'
        );
        $this->rememberTransfer($result['transfer']);
        $service->process((int) $result['transfer']->id);

        $this->assertSame('manual_reconcile_required', $result['transfer']->fresh()->status);
        $this->assertSame(2, count($funding->calls));
        $this->assertSame(['withdraw', 'deposit'], array_column($funding->calls, 0));
    }

    private function service(): CommissionTransferService
    {
        return new CommissionTransferService(
            new RecordingTradePasswordGateway(),
            new RecordingCommissionTransferFundingGateway(),
            new RecordingCommissionTransferSnapshotGateway('500.00')
        );
    }

    private function insertUser(int $userId, int $parentId, string $balance): void
    {
        $id = (int) DB::table('user_infos')->insertGetId([
            'user_id' => $userId,
            'login_id' => 0,
            'user_name' => 'commission-saga-' . $userId,
            'phone' => '188' . substr((string) $userId, -8),
            'gender' => 1,
            'account_type' => 1,
            'parent_id' => $parentId,
            'family_tree' => $parentId ? $parentId . ',' . $userId : (string) $userId,
            'group_id' => 0,
            'level_id' => 0,
            'comm_rate' => 0,
            'auth_status' => 1,
            'is_agent_confirmed' => 1,
            'is_mt4_enabled' => 1,
            'is_mt4_synced' => 1,
            'is_mt4_readonly' => 0,
            'is_withdrawal_allowed' => 0,
            'is_deposit_allowed' => 0,
            'total_funds' => $balance,
            'used_margin' => 0,
            'avail_margin' => $balance,
            'equity' => $balance,
            'effective_credit' => 0,
            'risk_ratio' => 0,
            'leverage' => 100,
            'created_at' => time(),
            'updated_at' => time(),
            'deleted_at' => null,
        ]);
        $this->createdUserIds[] = $id;
    }

    /** @return array{0:int, 1:int} */
    private function unusedUserPair(): array
    {
        for ($attempt = 0; $attempt < 100; $attempt++) {
            $source = random_int(1000000000, 1999999998);
            $target = $source + 1;
            $ids = [$source, $target];

            if (DB::table('user_infos')->whereIn('user_id', $ids)->exists()) {
                continue;
            }
            if (Schema::hasTable('user_logins')
                && DB::table('user_logins')->whereIn('user_id', $ids)->exists()) {
                continue;
            }
            if (DB::table('commission_transfers')->whereIn('source_user_id', $ids)
                ->orWhereIn('target_user_id', $ids)
                ->exists()) {
                continue;
            }
            if (DB::table('commission_records')->whereIn('agent_id', $ids)
                ->orWhereIn('parent_id', $ids)
                ->exists()) {
                continue;
            }
            if (DB::table('operation_logs')->whereIn('target_user_id', $ids)->exists()) {
                continue;
            }

            return [$source, $target];
        }

        throw new \RuntimeException('Unable to allocate unused commission saga fixture users.');
    }

    private function rememberTransfer(CommissionTransfer $transfer): void
    {
        $transferId = (int) $transfer->id;
        if (!in_array($transferId, $this->createdTransferIds, true)) {
            $this->createdTransferIds[] = $transferId;
        }
        $this->rememberLedgerRows($transferId);

        $orderNumber = trim((string) $transfer->local_order_no);
        if ($orderNumber !== '' && !in_array($orderNumber, $this->createdOrderNumbers, true)) {
            $this->createdOrderNumbers[] = $orderNumber;
        }

        $outboxIds = DB::table('commission_transfer_outbox')
            ->where('commission_transfer_id', $transferId)
            ->pluck('id')
            ->map(static function ($id): int {
                return (int) $id;
            })
            ->all();
        foreach ($outboxIds as $outboxId) {
            if (!in_array($outboxId, $this->createdOutboxIds, true)) {
                $this->createdOutboxIds[] = $outboxId;
            }
        }
    }

    /** @param array<int, string> $cleanupFailures */
    private function cleanupFixture(array &$cleanupFailures): void
    {
        $this->cleanupStep('operation_logs', function (): void {
            if ($this->createdOrderNumbers !== []) {
                DB::table('operation_logs')->whereIn('order_no', $this->createdOrderNumbers)->delete();
            }
        }, $cleanupFailures);
        $this->cleanupStep('commission_records', function (): void {
            foreach ($this->ledgerRowFingerprints as $uniqueId => $expectedFingerprint) {
                if (array_key_exists($uniqueId, $this->initialLedgerFingerprints)) {
                    continue;
                }
                try {
                    $row = DB::table('commission_records')->where('unique_id', $uniqueId)->first();
                    if ($row === null) {
                        continue;
                    }
                    if (MySqlTableFingerprint::digestRows([$row]) !== $expectedFingerprint) {
                        throw new \RuntimeException(
                            'Refusing to delete changed ledger row ' . $uniqueId . '.'
                        );
                    }
                    DB::table('commission_records')->where('unique_id', $uniqueId)->delete();
                } catch (\Throwable $exception) {
                    throw new \RuntimeException(
                        'Ledger cleanup failed for ' . $uniqueId . ': ' . $exception->getMessage(),
                        0,
                        $exception
                    );
                }
            }
        }, $cleanupFailures);
        $this->cleanupStep('commission_transfer_outbox', function (): void {
            if ($this->createdOutboxIds !== []) {
                DB::table('commission_transfer_outbox')->whereIn('id', $this->createdOutboxIds)->delete();
            }
        }, $cleanupFailures);
        $this->cleanupStep('commission_transfers', function (): void {
            if ($this->createdTransferIds !== []) {
                DB::table('commission_transfers')->whereIn('id', $this->createdTransferIds)->delete();
            }
        }, $cleanupFailures);
        $this->cleanupStep('user_infos', function (): void {
            if ($this->createdUserIds !== []) {
                DB::table('user_infos')->whereIn('id', $this->createdUserIds)->delete();
            }
        }, $cleanupFailures);
    }

    /** @param array<int, string> $cleanupFailures */
    private function cleanupStep(string $name, callable $cleanup, array &$cleanupFailures): void
    {
        try {
            $cleanup();
        } catch (\Throwable $exception) {
            $cleanupFailures[] = $name . ': ' . $exception->getMessage();
        }
    }

    private function resetFixtureState(): void
    {

        $this->createdOrderNumbers = [];
        $this->createdLedgerUniqueIds = [];
        $this->createdOutboxIds = [];
        $this->createdTransferIds = [];
        $this->createdUserIds = [];
        $this->initialLedgerFingerprints = [];
        $this->ledgerRowFingerprints = [];
    }

    /** @return array<string, string> */
    private function captureLedgerFingerprints(): array
    {
        $fingerprints = [];
        foreach (DB::table('commission_records')->useWritePdo()->get() as $row) {
            $values = (array) $row;
            $uniqueId = (string) ($values['unique_id'] ?? '');
            if ($uniqueId !== '') {
                $fingerprints[$uniqueId] = MySqlTableFingerprint::digestRows([$row]);
            }
        }

        return $fingerprints;
    }

    private function rememberLedgerRows(int $transferId): void
    {
        foreach ([
            hash('sha256', 'commission-transfer:DBCT-' . $transferId),
            hash('sha256', 'commission-transfer:WBCT-' . $transferId),
        ] as $uniqueId) {
            if (array_key_exists($uniqueId, $this->initialLedgerFingerprints)
                || array_key_exists($uniqueId, $this->ledgerRowFingerprints)) {
                continue;
            }
            $row = DB::table('commission_records')->where('unique_id', $uniqueId)->first();
            if ($row !== null) {
                $this->createdLedgerUniqueIds[] = $uniqueId;
                $this->ledgerRowFingerprints[$uniqueId] = MySqlTableFingerprint::digestRows([$row]);
            }
        }
    }
}

final class RecordingTradePasswordGateway implements TradePasswordGateway
{
    /**
     * verify() 收到的 [userId, password] 调用记录。断言资金命令前确实执行了资金密码校验且目标正确。
     * @var array<int, array{0: int, 1: string}>
     */
    public $calls = [];
    /**
     * 预设的验证结果队列，逐次弹出；为空时默认返回验证成功。
     * 用例借它构造"资金密码错误"等失败分支。
     * @var array<int, TradePasswordVerificationResult>
     */
    public $results = [];

    public function verify(int $userId, string $password): TradePasswordVerificationResult
    {
        $this->calls[] = [$userId, $password];

        return array_shift($this->results) ?: TradePasswordVerificationResult::verified();
    }
}

final class RecordingCommissionTransferFundingGateway implements CommissionTransferFundingGateway
{
    /**
     * 记录 withdraw/deposit/compensate 的调用序列 [动作, userId, 金额, 备注]。
     * 断言 saga 发出的资金命令顺序、金额与备注完全符合预期（如失败补偿命令）。
     * @var array<int, array{0: string, 1: int, 2: string, 3: string}>
     */
    public $calls = [];
    /**
     * withdraw 的预设结果队列，逐次弹出；为空时默认返回已处理（单号 700001）。
     * 用例借它构造转出失败等场景。
     * @var array<int, CommissionTransferCommandResult>
     */
    public $withdrawResults = [];
    /**
     * deposit 的预设结果队列，逐次弹出；为空时默认返回已处理（单号 700002）。
     * @var array<int, CommissionTransferCommandResult>
     */
    public $depositResults = [];
    /**
     * compensate 的预设结果队列，逐次弹出；为空时默认返回已处理（单号 700003）。
     * 用例借它构造补偿也失败的场景，验证 saga 的最终状态与日志。
     * @var array<int, CommissionTransferCommandResult>
     */
    public $compensationResults = [];

    public function withdraw(int $userId, string $amount, string $comment): CommissionTransferCommandResult
    {
        $this->calls[] = ['withdraw', $userId, $amount, $comment];

        return array_shift($this->withdrawResults) ?: CommissionTransferCommandResult::processed('700001');
    }

    public function deposit(int $userId, string $amount, string $comment): CommissionTransferCommandResult
    {
        $this->calls[] = ['deposit', $userId, $amount, $comment];

        return array_shift($this->depositResults) ?: CommissionTransferCommandResult::processed('700002');
    }

    public function compensate(int $userId, string $amount, string $comment): CommissionTransferCommandResult
    {
        $this->calls[] = ['compensate', $userId, $amount, $comment];

        return array_shift($this->compensationResults) ?: CommissionTransferCommandResult::processed('700003');
    }
}

final class RecordingCommissionTransferSnapshotGateway implements CommissionTransferAccountSnapshotGateway
{
    /**
     * snapshot() 收到的 userId 序列。断言余额快照的读取次数与目标用户。
     * @var array<int, int>
     */
    public $calls = [];
    /**
     * 预设余额：字符串时对所有用户返回同一值；数组时按 userId 查找。
     * 查不到时返回 rejected（snapshot_fixture_missing），用于构造快照缺失的失败场景。
     * @var string|array<int, string>
     */
    private $balances;

    /** @param string|array<int, string> $balances */
    public function __construct($balances)
    {
        $this->balances = $balances;
    }

    public function snapshot(int $userId): CommissionTransferAccountSnapshotResult
    {
        $this->calls[] = $userId;

        $balance = is_array($this->balances)
            ? ($this->balances[$userId] ?? null)
            : $this->balances;
        if ($balance === null) {
            return CommissionTransferAccountSnapshotResult::rejected('snapshot_fixture_missing');
        }

        return CommissionTransferAccountSnapshotResult::confirmed($balance);
    }
}
