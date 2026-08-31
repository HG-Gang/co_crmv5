<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/01
 * Time: 14:59
 */

/**
 * CommissionTransferManualReconciliationServiceTest
 *
 * 文件功能：
 * - 验证佣金划转人工对账服务：确认完成走可信快照与幂等双腿账本、审计失败整体回滚、补偿/拒绝不改镜像、明细与变更必须人工划转与人工处理 outbox、账本身份冲突回滚。
 * - 输入：HTTP 请求与事务回滚的数据库夹具；输出：PHPUnit 断言结果。
 * - 明确不负责：不覆盖真实 MT4 网关与线上支付/出金通道（由网关契约测试锁定）。
 */

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\OperationLog;
use App\Services\AdminDataScopeService;
use App\Services\CommissionTransfer\CommissionTransferLedgerFinalizer;
use App\Services\CommissionTransfer\CommissionTransferReconciliationPolicy;
use App\Services\CommissionTransfer\CommissionTransferReconciliationService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Tests\Support\MySqlFixtureMutex;
use Tests\Support\MySqlTableFingerprint;
use Tests\TestCase;

final class CommissionTransferManualReconciliationServiceTest extends TestCase
{
    /**
     * 本夹具所有标识（管理员用户名、幂等键、用户名）的统一前缀。
     * 让清理逻辑能按前缀定位本用例产生的行，也避免与库中既有数据重名。
     * @var string
     */
    private const FIXTURE_PREFIX = 'manual-reconcile-fixture-';

    /**
     * 本地订单号前缀（MRCF-）。生成的单号再拼接 uniqid，保证重复运行不与既有 operation_logs 冲突。
     * @var string
     */
    private const ORDER_PREFIX = 'MRCF-';

    /**
     * 夹具写入前各表的 AUTO_INCREMENT 基线（表名 => 自增值或 null）。tearDown 恢复，
     * 防止夹具插入抬高共享库自增计数。
     * @var array<string, int|null>
     */
    private $originalAutoIncrements = [];

    /**
     * 夹具会插入数据的表清单。setUp 据此捕获自增基线，tearDown 恢复。
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
     * 人工对账涉及的转账发起方（佣金来源）业务用户 ID，setUp 随机分配并建夹具行。
     * @var int
     */
    private $sourceUserId;

    /**
     * 人工对账涉及的转账接收方业务用户 ID，与 sourceUserId 组成同一笔转账两端。
     * @var int
     */
    private $targetUserId;

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
     * 夹具创建的业务用户主键清单。tearDown 据此删除 user_infos/user_logins 夹具行。
     * @var array<int, int>
     */
    private $createdUserIds = [];

    /**
     * MySqlFixtureMutex 实例。串行化共享测试库上的夹具准备与清理，避免并行进程互相踩踏。
     * @var MySqlFixtureMutex|null
     */
    private $fixtureMutex;

    /**
     * setUp 捕获的各表行指纹基线。tearDown 重新捕获比对，不一致即夹具泄漏，测试失败上报。
     * @var array<string, array<string, int|string>>
     */
    private $tableFingerprints = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->fixtureMutex = new MySqlFixtureMutex();
        $this->fixtureMutex->acquire();
        $this->tableFingerprints = MySqlTableFingerprint::capture(self::AUTO_INCREMENT_TABLES);
        foreach (['manual_origin_step', 'reconcile_evidence'] as $column) {
            $this->assertTrue(Schema::hasColumn('commission_transfers', $column), $column . ' migration missing.');
        }
    }

    protected function tearDown(): void
    {
        $failures = [];
        try {
            $after = MySqlTableFingerprint::capture(self::AUTO_INCREMENT_TABLES);
            if ($after !== $this->tableFingerprints) {
                // 输出具体差异表与差异维度，便于定位是残留数据、结构还是自增序列未还原。
                $diffs = [];
                foreach (self::AUTO_INCREMENT_TABLES as $table) {
                    $before = $this->tableFingerprints[$table] ?? null;
                    $current = $after[$table] ?? null;
                    if ($before === null || $current === null || $before === $current) {
                        continue;
                    }
                    $delta = [];
                    foreach (array_keys($before) as $dimension) {
                        $old = $before[$dimension] ?? null;
                        $new = $current[$dimension] ?? null;
                        if ($old !== $new) {
                            $delta[] = $dimension . ':' . (is_scalar($old) ? $old : '?') . '->'
                                . (is_scalar($new) ? $new : '?');
                        }
                    }
                    $diffs[] = $table . '{' . implode(',', $delta) . '}';
                }
                $failures[] = 'table_fingerprint_mismatch(' . implode(' | ', $diffs) . ')';
            }
        } catch (\Throwable $exception) {
            $failures[] = 'table_fingerprint_capture: ' . $exception->getMessage();
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
        $this->fixtureMutex = null;
        $this->tableFingerprints = [];
        if ($failures !== []) {
            throw new RuntimeException(
                'Commission reconciliation fixture teardown failures: ' . implode(' | ', $failures)
            );
        }
    }

    public function test_confirmed_completed_uses_trusted_snapshots_and_idempotent_two_leg_ledger(): void
    {
        $this->withFixture(function (): void {
            $admin = $this->superAdmin();
            $transferId = $this->insertManualTransfer('withdraw', 'complete');

            $result = $this->service()->reconcile(
                $admin,
                $transferId,
                $this->evidence([
                    'decision' => 'confirmed_completed',
                    'withdraw_status' => 'confirmed_processed',
                    'withdraw_reference' => '810001',
                    'deposit_status' => 'confirmed_processed',
                    'deposit_reference' => '810002',
                    'source_balance_after' => '875.00',
                    'target_balance_after' => '225.00',
                ]),
                'case-complete',
                '127.0.0.1'
            );

            $this->assertSame('ok', $result['result']);
            $this->assertSame(875.0, $this->userBalance($this->sourceUserId));
            $this->assertSame(225.0, $this->userBalance($this->targetUserId));
            $this->assertSame(2, $this->transferLedgerQuery($transferId)->count());

            $transfer = DB::table('commission_transfers')->where('id', $transferId)->first();
            $this->assertSame('completed', (string) $transfer->status);
            $this->assertSame('completed', (string) $transfer->current_step);
            $this->assertSame('withdraw', (string) $transfer->manual_origin_step);
            $this->assertSame('confirmed_completed', (string) $transfer->reconcile_decision);
            $this->assertSame('case-complete', (string) $transfer->reconcile_external_reference);
            $this->assertNotNull($transfer->reconcile_evidence);
            $this->assertSame('completed', (string) DB::table('commission_transfer_outbox')
                ->where('commission_transfer_id', $transferId)
                ->where('event_type', 'process')
                ->value('status'));

            $log = DB::table('operation_logs')->where('order_no', $transfer->local_order_no)->first();
            $this->assertNotNull($log);
            $this->assertLessThanOrEqual(1000, strlen((string) $log->content));

            $replay = $this->service()->reconcile(
                $admin,
                $transferId,
                $this->evidence(['decision' => 'confirmed_rejected']),
                'case-replay',
                '127.0.0.1'
            );
            $this->assertSame('not_allowed', $replay['result']);
            $this->assertSame(2, $this->transferLedgerQuery($transferId)->count());
        });
    }

    public function test_audit_log_failure_rolls_back_balances_ledger_and_transfer_state(): void
    {
        $this->withFixture(function (): void {
            $transferId = $this->insertManualTransfer('withdraw', 'late-audit-failure');
            $beforeUsers = DB::table('user_infos')
                ->whereIn('user_id', [$this->sourceUserId, $this->targetUserId])
                ->orderBy('user_id')
                ->get()
                ->map(static function ($row): array {
                    return (array) $row;
                })
                ->all();
            $beforeTransfer = (array) DB::table('commission_transfers')->where('id', $transferId)->first();
            $beforeOutbox = (array) DB::table('commission_transfer_outbox')
                ->where('commission_transfer_id', $transferId)
                ->where('event_type', 'process')
                ->first();
            $orderNo = (string) ($beforeTransfer['local_order_no'] ?? '');

            OperationLog::creating(function (OperationLog $log) use ($orderNo): void {
                if ((string) $log->order_no === $orderNo) {
                    throw new RuntimeException('forced_operation_log_failure');
                }
            });

            try {
                $this->service()->reconcile(
                    $this->superAdmin(),
                    $transferId,
                    $this->evidence([
                        'decision' => 'confirmed_completed',
                        'withdraw_status' => 'confirmed_processed',
                        'withdraw_reference' => '860001',
                        'deposit_status' => 'confirmed_processed',
                        'deposit_reference' => '860002',
                        'source_balance_after' => '875.00',
                        'target_balance_after' => '225.00',
                    ]),
                    'case-late-audit-failure',
                    '127.0.0.1'
                );
                $this->fail('Expected the audit log failure to abort reconciliation.');
            } catch (RuntimeException $exception) {
                $this->assertSame('forced_operation_log_failure', $exception->getMessage());
            } finally {
                OperationLog::flushEventListeners();
            }

            $afterUsers = DB::table('user_infos')
                ->whereIn('user_id', [$this->sourceUserId, $this->targetUserId])
                ->orderBy('user_id')
                ->get()
                ->map(static function ($row): array {
                    return (array) $row;
                })
                ->all();
            $afterTransfer = (array) DB::table('commission_transfers')->where('id', $transferId)->first();
            $afterOutbox = (array) DB::table('commission_transfer_outbox')
                ->where('commission_transfer_id', $transferId)
                ->where('event_type', 'process')
                ->first();

            $this->assertSame($beforeUsers, $afterUsers);
            $this->assertSame($beforeTransfer, $afterTransfer);
            $this->assertSame($beforeOutbox, $afterOutbox);
            $this->assertSame(0, $this->transferLedgerQuery($transferId)->count());
            $this->assertSame(0, DB::table('operation_logs')->where('order_no', $orderNo)->count());
        });
    }

    /** @dataProvider nonLedgerTerminalProvider */
    public function test_compensated_and_rejected_do_not_change_mirrors_or_create_transfer_records(
        string $originStep,
        array $evidence,
        string $terminalStatus
    ): void {
        $this->withFixture(function () use ($originStep, $evidence, $terminalStatus): void {
            $transferId = $this->insertManualTransfer($originStep, $terminalStatus);

            $result = $this->service()->reconcile(
                $this->superAdmin(),
                $transferId,
                $evidence,
                'case-' . $terminalStatus,
                '127.0.0.1'
            );

            $this->assertSame('ok', $result['result']);
            $this->assertSame(1000.0, $this->userBalance($this->sourceUserId));
            $this->assertSame(100.0, $this->userBalance($this->targetUserId));
            $this->assertSame(0, $this->transferLedgerQuery($transferId)->count());
            $this->assertSame($terminalStatus, (string) DB::table('commission_transfers')
                ->where('id', $transferId)
                ->value('status'));
            $this->assertSame('completed', (string) DB::table('commission_transfer_outbox')
                ->where('commission_transfer_id', $transferId)
                ->where('event_type', 'process')
                ->value('status'));
        });
    }

    public static function nonLedgerTerminalProvider(): array
    {
        return [
            'compensated' => [
                'withdraw',
                self::evidenceStatic([
                    'decision' => 'confirmed_compensated',
                    'withdraw_status' => 'confirmed_processed',
                    'withdraw_reference' => '820001',
                    'deposit_status' => 'confirmed_not_processed',
                    'compensation_status' => 'confirmed_processed',
                    'compensation_reference' => '820003',
                ]),
                'compensated',
            ],
            'rejected' => [
                'withdraw',
                self::evidenceStatic([
                    'decision' => 'confirmed_rejected',
                    'withdraw_status' => 'confirmed_rejected',
                    'withdraw_reference' => '830001',
                ]),
                'rejected',
            ],
        ];
    }

    public function test_detail_and_mutation_require_manual_transfer_and_manual_process_outbox(): void
    {
        $this->withFixture(function (): void {
            $admin = $this->superAdmin();
            $transferId = $this->insertManualTransfer('withdraw', 'guard');
            $service = $this->service();
            $this->assertSame('ok', $service->detail($admin, $transferId)['result']);
            $this->assertTrue($service->cases($admin, 1, 100)->getCollection()->contains('id', $transferId));

            DB::table('commission_transfer_outbox')
                ->where('commission_transfer_id', $transferId)
                ->where('event_type', 'process')
                ->update(['status' => 'pending']);
            $this->assertFalse($service->cases($admin, 1, 100)->getCollection()->contains('id', $transferId));
            $this->assertSame('not_allowed', $service->reconcile(
                $admin,
                $transferId,
                $this->evidence(['decision' => 'confirmed_rejected']),
                'case-guard',
                '127.0.0.1'
            )['result']);

            DB::table('commission_transfers')->where('id', $transferId)->update(['status' => 'completed']);
            $this->assertSame('not_allowed', $service->detail($admin, $transferId)['result']);
        });
    }

    /** @dataProvider ledgerConflictProvider */
    public function test_ledger_identity_conflict_rolls_back_all_local_state(string $conflictLeg): void
    {
        $this->withFixture(function () use ($conflictLeg): void {
            $transferId = $this->insertManualTransfer('withdraw', 'conflict-' . strtolower($conflictLeg));
            $conflictUniqueId = $this->ledgerUniqueId($transferId, $conflictLeg);
            DB::table('commission_records')->insert([
                'unique_id' => $conflictUniqueId,
                'agent_id' => $this->sourceUserId,
                'parent_id' => $this->targetUserId,
                'commission_amount' => 1,
                'returned_amount' => 1,
                'real_amount' => 1,
                'mt4_order_id' => 0,
                'settle_status' => 2,
                'data_type' => 'transfer',
                'created_at' => time(),
                'updated_at' => time(),
            ]);
            $beforeUsers = DB::table('user_infos')
                ->whereIn('user_id', [$this->sourceUserId, $this->targetUserId])
                ->orderBy('user_id')
                ->get()
                ->map(static function ($row): array {
                    return (array) $row;
                })
                ->all();
            $beforeTransfer = (array) DB::table('commission_transfers')->where('id', $transferId)->first();
            $beforeOutbox = (array) DB::table('commission_transfer_outbox')
                ->where('commission_transfer_id', $transferId)
                ->where('event_type', 'process')
                ->first();
            $beforeLedger = (array) DB::table('commission_records')->where('unique_id', $conflictUniqueId)->first();

            try {
                $this->service()->reconcile(
                    $this->superAdmin(),
                    $transferId,
                    $this->evidence([
                        'decision' => 'confirmed_completed',
                        'withdraw_status' => 'confirmed_processed',
                        'withdraw_reference' => '840001',
                        'deposit_status' => 'confirmed_processed',
                        'deposit_reference' => '840002',
                        'source_balance_after' => '875.00',
                        'target_balance_after' => '225.00',
                    ]),
                    'case-conflict',
                    '127.0.0.1'
                );
                $this->fail('Expected immutable ledger identity conflict.');
            } catch (RuntimeException $exception) {
                $this->assertSame('commission_transfer_ledger_identity_conflict', $exception->getMessage());
            }

            $afterUsers = DB::table('user_infos')
                ->whereIn('user_id', [$this->sourceUserId, $this->targetUserId])
                ->orderBy('user_id')
                ->get()
                ->map(static function ($row): array {
                    return (array) $row;
                })
                ->all();
            $afterTransfer = (array) DB::table('commission_transfers')->where('id', $transferId)->first();
            $afterOutbox = (array) DB::table('commission_transfer_outbox')
                ->where('commission_transfer_id', $transferId)
                ->where('event_type', 'process')
                ->first();
            $afterLedger = (array) DB::table('commission_records')->where('unique_id', $conflictUniqueId)->first();

            $this->assertSame($beforeUsers, $afterUsers);
            $this->assertSame($beforeTransfer, $afterTransfer);
            $this->assertSame($beforeOutbox, $afterOutbox);
            $this->assertSame($beforeLedger, $afterLedger);
            $this->assertSame(1, $this->transferLedgerQuery($transferId)->count());
            $this->assertSame(0, DB::table('operation_logs')
                ->whereIn('order_no', $this->createdOrderNumbers)
                ->count());
        });
    }

    public static function ledgerConflictProvider(): array
    {
        return [
            'deposit leg conflict' => ['DBCT'],
            'withdraw leg conflict' => ['WBCT'],
        ];
    }

    public function test_fixture_cleanup_restores_all_auto_increment_values(): void
    {
        $before = $this->readAutoIncrements();

        $this->withFixture(function (): void {
            $transferId = $this->insertManualTransfer('withdraw', 'auto-increment');
            $this->service()->reconcile(
                $this->superAdmin(),
                $transferId,
                $this->evidence([
                    'decision' => 'confirmed_completed',
                    'withdraw_status' => 'confirmed_processed',
                    'withdraw_reference' => '850001',
                    'deposit_status' => 'confirmed_processed',
                    'deposit_reference' => '850002',
                    'source_balance_after' => '875.00',
                    'target_balance_after' => '225.00',
                ]),
                'case-auto-increment',
                '127.0.0.1'
            );
        });

        $this->assertSame($before, $this->readAutoIncrements());
    }

    private function service(): CommissionTransferReconciliationService
    {
        return new CommissionTransferReconciliationService(
            new AdminDataScopeService(),
            new CommissionTransferReconciliationPolicy(),
            new CommissionTransferLedgerFinalizer()
        );
    }

    private function superAdmin(): Admin
    {
        $admin = new Admin(['username' => self::FIXTURE_PREFIX . 'admin']);
        $admin->id = 1;
        $admin->exists = true;

        return $admin;
    }

    private function insertManualTransfer(string $originStep, string $suffix): int
    {
        $now = time();
        $key = self::FIXTURE_PREFIX . $suffix . '-' . uniqid('', true);
        $localOrderNo = self::ORDER_PREFIX . strtoupper($suffix) . '-' . uniqid();
        $transferId = (int) DB::table('commission_transfers')->insertGetId([
            'local_order_no' => $localOrderNo,
            'source_user_id' => $this->sourceUserId,
            'target_user_id' => $this->targetUserId,
            'request_purpose' => 'front_commission_transfer',
            'idempotency_key' => $key,
            'payload_hash' => hash('sha256', $key),
            'amount' => 125,
            'remark' => 'manual reconciliation test',
            'status' => 'manual_reconcile_required',
            'current_step' => $originStep,
            'manual_origin_step' => $originStep,
            'reservation_status' => 'not_required',
            'attempts' => 1,
            'processed_at' => $now,
            'last_error_code' => $originStep . '_result_unknown',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $outboxId = (int) DB::table('commission_transfer_outbox')->insertGetId([
            'commission_transfer_id' => $transferId,
            'event_type' => 'process',
            'status' => 'manual_reconcile_required',
            'attempts' => 1,
            'payload_hash' => hash('sha256', $key),
            'processed_at' => $now,
            'last_error_code' => $originStep . '_result_unknown',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $this->createdTransferIds[] = $transferId;
        $this->createdOutboxIds[] = $outboxId;
        $this->createdOrderNumbers[] = $localOrderNo;
        $this->createdLedgerUniqueIds[] = $this->ledgerUniqueId($transferId, 'DBCT');
        $this->createdLedgerUniqueIds[] = $this->ledgerUniqueId($transferId, 'WBCT');

        return $transferId;
    }

    private function insertUser(int $userId, int $parentId, string $balance): void
    {
        DB::table('user_infos')->insert([
            'user_id' => $userId,
            'login_id' => 0,
            'user_name' => self::FIXTURE_PREFIX . $userId,
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
        ]);
        $this->createdUserIds[] = $userId;
    }

    private function withFixture(callable $test): void
    {
        $this->snapshotAutoIncrements();

        try {
            [$this->sourceUserId, $this->targetUserId] = $this->unusedUserPair();
            $this->insertUser($this->sourceUserId, 0, '1000.00');
            $this->insertUser($this->targetUserId, $this->sourceUserId, '100.00');
            $test();
        } finally {
            try {
                $this->cleanupFixture();
            } finally {
                try {
                    $this->restoreAutoIncrements();
                } finally {
                    $this->originalAutoIncrements = [];
                }
            }
        }
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

        throw new RuntimeException('Unable to allocate unused commission reconciliation fixture users.');
    }

    private function cleanupFixture(): void
    {
        if ($this->createdOrderNumbers !== []) {
            DB::table('operation_logs')->whereIn('order_no', $this->createdOrderNumbers)->delete();
        }
        if ($this->createdLedgerUniqueIds !== []) {
            DB::table('commission_records')->whereIn('unique_id', $this->createdLedgerUniqueIds)->delete();
        }
        if ($this->createdOutboxIds !== []) {
            DB::table('commission_transfer_outbox')->whereIn('id', $this->createdOutboxIds)->delete();
        }
        if ($this->createdTransferIds !== []) {
            DB::table('commission_transfers')->whereIn('id', $this->createdTransferIds)->delete();
        }
        if ($this->createdUserIds !== []) {
            DB::table('user_infos')->whereIn('user_id', $this->createdUserIds)->delete();
        }

        $this->createdTransferIds = [];
        $this->createdOutboxIds = [];
        $this->createdLedgerUniqueIds = [];
        $this->createdOrderNumbers = [];
        $this->createdUserIds = [];
    }

    private function snapshotAutoIncrements(): void
    {
        if ($this->originalAutoIncrements !== []) {
            throw new RuntimeException('Commission reconciliation fixture AUTO_INCREMENT snapshot already exists.');
        }

        $this->originalAutoIncrements = $this->readAutoIncrements();
    }

    /** @return array<string, int|null> */
    private function readAutoIncrements(): array
    {
        $connection = DB::connection();
        $connection->statement('SET SESSION information_schema_stats_expiry = 0');
        $snapshot = [];
        foreach (self::AUTO_INCREMENT_TABLES as $table) {
            $row = $connection->selectOne(
                'SELECT AUTO_INCREMENT AS auto_increment FROM information_schema.TABLES '
                . 'WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?',
                [$table],
                false
            );
            if (!$row || $row->auto_increment === null) {
                throw new RuntimeException('Unable to read ' . $table . ' AUTO_INCREMENT.');
            }
            $value = (string) $row->auto_increment;
            if (!ctype_digit($value) || (int) $value < 1) {
                throw new RuntimeException('Invalid ' . $table . ' AUTO_INCREMENT value: ' . $value . '.');
            }
            $snapshot[$table] = (int) $value;
        }

        return $snapshot;
    }

    private function restoreAutoIncrements(): void
    {
        if ($this->originalAutoIncrements === []) {
            throw new RuntimeException('Commission reconciliation fixture AUTO_INCREMENT snapshot is unavailable.');
        }

        $connection = DB::connection();
        if ($connection->transactionLevel() !== 0) {
            throw new RuntimeException(
                'Refusing commission reconciliation fixture AUTO_INCREMENT restore at transaction level '
                . $connection->transactionLevel() . '.'
            );
        }
        $connection->statement('SET SESSION information_schema_stats_expiry = 0');

        foreach (self::AUTO_INCREMENT_TABLES as $table) {
            $expected = $this->originalAutoIncrements[$table] ?? null;
            if ($expected === null) {
                throw new RuntimeException('Missing ' . $table . ' AUTO_INCREMENT snapshot.');
            }
            $maxRow = $connection->selectOne('SELECT MAX(id) AS max_id FROM `' . $table . '`', [], false);
            $maxId = $maxRow && $maxRow->max_id !== null ? (int) $maxRow->max_id : 0;
            if ($maxId >= $expected) {
                throw new RuntimeException(
                    'Refusing to lower ' . $table . ' AUTO_INCREMENT: MAX(id)=' . $maxId
                    . ' is not below original=' . $expected . '.'
                );
            }
        }

        foreach (self::AUTO_INCREMENT_TABLES as $table) {
            $expected = (int) $this->originalAutoIncrements[$table];
            $connection->statement('ALTER TABLE `' . $table . '` AUTO_INCREMENT = ' . $expected);
        }

        $actual = $this->readAutoIncrements();
        foreach (self::AUTO_INCREMENT_TABLES as $table) {
            $expected = (int) $this->originalAutoIncrements[$table];
            if (($actual[$table] ?? null) !== $expected) {
                throw new RuntimeException(
                    $table . ' AUTO_INCREMENT restore mismatch: expected=' . $expected
                    . ', actual=' . (string) ($actual[$table] ?? 'null') . '.'
                );
            }
        }
    }

    private function ledgerUniqueId(int $transferId, string $leg): string
    {
        return hash('sha256', 'commission-transfer:' . $leg . '-' . $transferId);
    }

    private function transferLedgerQuery(int $transferId)
    {
        return DB::table('commission_records')->whereIn('unique_id', [
            $this->ledgerUniqueId($transferId, 'DBCT'),
            $this->ledgerUniqueId($transferId, 'WBCT'),
        ]);
    }

    private function userBalance(int $userId): float
    {
        return (float) DB::table('user_infos')->where('user_id', $userId)->value('total_funds');
    }

    private function evidence(array $overrides): array
    {
        return self::evidenceStatic($overrides);
    }

    private static function evidenceStatic(array $overrides): array
    {
        return array_merge([
            'decision' => '',
            'withdraw_status' => 'confirmed_not_processed',
            'withdraw_reference' => null,
            'deposit_status' => 'confirmed_not_processed',
            'deposit_reference' => null,
            'compensation_status' => 'confirmed_not_processed',
            'compensation_reference' => null,
            'source_balance_after' => null,
            'target_balance_after' => null,
        ], $overrides);
    }
}
