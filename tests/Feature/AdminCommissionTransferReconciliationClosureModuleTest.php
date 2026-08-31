<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/17
 * Time: 00:43
 */

/**
 * AdminCommissionTransferReconciliationClosureModuleTest
 *
 * 文件功能：
 * - 验证佣金划转人工对账闭环：路由权限与对账字段迁移、管理员数据范围限定、人工状态白名单与外部参考号、compare-and-set 写审计且不代替 funding gateway。
 * - 输入：HTTP 请求与事务回滚的数据库夹具；输出：PHPUnit 断言结果。
 * - 明确不负责：不覆盖真实 MT4 网关与线上支付/出金通道（由网关契约测试锁定）。
 */

declare(strict_types=1);

namespace Tests\Feature;

use App\Constants\ResponseCode;
use App\Contracts\CommissionTransferFundingGateway;
use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\JwtAuthMiddleware;
use App\Http\Middleware\SingleSignOn;
use App\Models\Admin;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;
use Tests\Support\MySqlFixtureMutex;
use Tests\Support\MySqlAutoIncrementSnapshot;
use Tests\Support\MySqlTableFingerprint;

/**
 * Administrator closure for transfers whose external result is unknown.
 *
 * The test intentionally exercises the real HTTP/controller/service path. A
 * funding gateway is bound to a throwing factory in the mutation test so a
 * reconciliation can never accidentally issue another money command.
 */
final class AdminCommissionTransferReconciliationClosureModuleTest extends TestCase
{
    /**
     * 被测迁移文件路径：为佣金转账对账模块新增三个后台权限。
     * 用例先执行该迁移，再断言权限、路由与管理端操作可用；tearDown 回滚迁移并清理权限数据。
     * @var string
     */
    private const MIGRATION = 'database/migrations/2026_07_19_000004_add_commission_transfer_reconcile_permissions.php';

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
        'admins',
        'roles',
        'role_permissions',
        'role_data_scopes',
    ];

    /**
     * 对账列表接口的路由名；用例经 route() 生成 URL 验证列表查询、鉴权与数据范围。
     * @var string
     */
    private const ROUTE_LIST = 'admin_api_commissionTransferReconciliationList';

    /**
     * 对账详情接口的路由名；用于验证单笔对账单的读取与鉴权。
     * @var string
     */
    private const ROUTE_DETAIL = 'admin_api_commissionTransferReconciliationDetail';

    /**
     * 执行对账接口的路由名；用于验证人工对账写路径及其失败关闭语义。
     * @var string
     */
    private const ROUTE_RECONCILE = 'admin_api_commissionTransferReconcile';

    /**
     * 列表接口所需权限 slug，由被测迁移写入。夹具据此为角色授权以通过 CheckPermission 中间件。
     * @var string
     */
    private const PERMISSION_LIST = 'admin_commission_transfer_reconciliation_list';

    /**
     * 详情接口所需权限 slug，与 ROUTE_DETAIL 一一对应。
     * @var string
     */
    private const PERMISSION_DETAIL = 'admin_commission_transfer_reconciliation_detail';

    /**
     * 执行对账接口所需权限 slug，与 ROUTE_RECONCILE 一一对应。
     * @var string
     */
    private const PERMISSION_RECONCILE = 'admin_commission_transfer_reconcile';

    /**
     * 夹具创建的业务用户主键清单（user_infos/user_logins）。tearDown 据此删除夹具行，防止污染共享库。
     * @var array<int, int>
     */
    private $createdUserIds = [];

    /**
     * unusedUserPair 已随机占用的 source/target 用户号。分配新用户对时跳过这些值，
     * 避免同一用例内重复分配同一号段。
     * @var array<int, int>
     */
    private $reservedUserIds = [];

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
     * 用例触发生成的 operation_logs.order_no 清单。tearDown 按单号删除操作日志夹具行。
     * @var array<int, string>
     */
    private $createdOrderNumbers = [];

    /**
     * 夹具创建的后台管理员主键清单（admins）。tearDown 据其删除管理员夹具行。
     * @var array<int, int>
     */
    private $createdAdminIds = [];

    /**
     * 夹具创建的角色主键清单（roles）。tearDown 据其删除角色。
     * @var array<int, int>
     */
    private $createdRoleIds = [];

    /**
     * 夹具创建的 role_permissions 主键清单（角色-权限绑定）。tearDown 据其解除绑定。
     * @var array<int, int>
     */
    private $createdRolePermissionIds = [];

    /**
     * 夹具创建的 role_data_scopes 主键清单（角色数据范围绑定）。tearDown 据其删除绑定。
     * @var array<int, int>
     */
    private $createdRoleDataScopeIds = [];

    /**
     * setUp 捕获的 MySqlAutoIncrementSnapshot 实例；tearDown 调用 restore() 把各表自增值还原，
     * 消除夹具插入对共享库的影响。null 表示捕获失败或尚未执行。
     * @var MySqlAutoIncrementSnapshot|null
     */
    private $autoIncrementSnapshot;

    /**
     * MySqlFixtureMutex 实例。共享测试库被多个进程使用，必须持互斥锁串行化夹具准备与清理，
     * 避免并行运行时互相踩踏；tearDown 释放（必要时断连兜底）。
     * @var MySqlFixtureMutex|null
     */
    private $fixtureMutex;

    /**
     * setUp 捕获的各表行指纹基线。tearDown 重新捕获并比对，任何不一致都说明夹具清理不彻底，
     * 测试将以失败告终而不是静默泄漏数据。
     * @var array<string, array<string, int|string>>
     */
    private $tableFingerprints = [];

    /**
     * 用例开始前 commission_records 已有行的指纹（unique_id => digest）。清理时跳过这些行，
     * 保证只删除本用例产生的流水，不误删共享库既有数据。
     * @var array<string, string>
     */
    private $initialLedgerFingerprints = [];

    /**
     * 用例过程中记录的佣金流水行指纹（unique_id => digest）。删除前重新计算比对，
     * 行被外部改动时拒绝删除并报错，防止掩盖并发写入问题。
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
                'Admin reconciliation fixture setup failed: ' . implode(' | ', $failures),
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
                    $cleanupFailures[] = 'table_fingerprint_mismatch: '
                        . json_encode(
                            $this->fingerprintDifferences($this->tableFingerprints, $after),
                            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
                        );
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
                'Admin reconciliation fixture teardown failures: ' . implode(' | ', $cleanupFailures)
            );
        }
    }

    public function test_reconciliation_routes_require_authentication_and_exact_permissions(): void
    {
        foreach ([self::ROUTE_LIST, self::ROUTE_DETAIL, self::ROUTE_RECONCILE] as $name) {
            $this->assertTrue(Route::has($name), $name . ' is not registered.');
            $route = Route::getRoutes()->getByName($name);
            $this->assertContains('jwt.auth:admin', $route->gatherMiddleware(), $name);
            $this->assertContains('sso:admin', $route->gatherMiddleware(), $name);
            $this->assertContains('check.permission:admin', $route->gatherMiddleware(), $name);
        }

        $response = $this->postJson('/api/admin/commission-transfers/reconciliation-cases');

        $response->assertOk()->assertJsonPath('code', ResponseCode::TOKEN_MISSING);
    }

    public function test_permission_migration_declares_unique_route_permissions_and_reconciliation_columns(): void
    {
        $source = file_exists(base_path(self::MIGRATION))
            ? (string) file_get_contents(base_path(self::MIGRATION))
            : '';

        $this->assertNotSame('', $source, 'The follow-up reconciliation migration is required.');

        foreach ([
            'reconcile_decision',
            'reconcile_external_reference',
            'reconciled_by',
            'reconciled_at',
            self::PERMISSION_LIST,
            self::PERMISSION_DETAIL,
            self::PERMISSION_RECONCILE,
            self::ROUTE_LIST,
            self::ROUTE_DETAIL,
            self::ROUTE_RECONCILE,
            'duplicate',
        ] as $needle) {
            $this->assertStringContainsString($needle, $source, $needle . ' migration contract is missing.');
        }

        $this->assertStringNotContainsString('dropColumn', $source);
        $this->assertStringNotContainsString("delete()", $source);
    }

    public function test_list_and_detail_are_limited_by_admin_data_scope(): void
    {
        $this->assertReconciliationSchema();
        [$sourceUserId, $targetUserId] = $this->unusedUserPair();
        [$hiddenSourceUserId, $hiddenTargetUserId] = $this->unusedUserPair();
        $admin = $this->createAdminWithPermissions(
            [self::ROUTE_LIST, self::ROUTE_DETAIL, self::ROUTE_RECONCILE],
            'custom_agents',
            [$sourceUserId]
        );
        $visibleId = $this->createTransfer($sourceUserId, $targetUserId, 'manual_reconcile_required', 'visible');
        $hiddenId = $this->createTransfer($hiddenSourceUserId, $hiddenTargetUserId, 'manual_reconcile_required', 'hidden');

        $list = $this->asAdmin($admin)->postJson('/api/admin/commission-transfers/reconciliation-cases');
        $list->assertOk()->assertJsonPath('code', ResponseCode::SUCCESS);
        $listedIds = collect($list->json('data.data'))->pluck('id')->map(static fn ($id): int => (int) $id)->all();
        $this->assertContains($visibleId, $listedIds);
        $this->assertNotContains($hiddenId, $listedIds);

        $this->asAdmin($admin)
            ->getJson('/api/admin/commission-transfers/reconciliation-cases/' . $visibleId)
            ->assertOk()
            ->assertJsonPath('data.id', $visibleId);

        $this->asAdmin($admin)
            ->getJson('/api/admin/commission-transfers/reconciliation-cases/' . $hiddenId)
            ->assertOk()
            ->assertJsonPath('code', ResponseCode::PERMISSION_DENIED);
    }

    public function test_reconcile_mutation_enforces_admin_data_scope_without_hidden_writes(): void
    {
        $this->assertReconciliationSchema();
        [$visibleSource, $visibleTarget] = $this->unusedUserPair();
        [$hiddenSource, $hiddenTarget] = $this->unusedUserPair();
        $admin = $this->createAdminWithPermissions(
            [self::ROUTE_LIST, self::ROUTE_DETAIL, self::ROUTE_RECONCILE],
            'custom_agents',
            [$visibleSource]
        );
        $visibleId = $this->createTransfer(
            $visibleSource,
            $visibleTarget,
            'manual_reconcile_required',
            'scope-visible'
        );
        $hiddenId = $this->createTransfer(
            $hiddenSource,
            $hiddenTarget,
            'manual_reconcile_required',
            'scope-hidden'
        );
        $hiddenTransferBefore = (array) DB::table('commission_transfers')->where('id', $hiddenId)->first();
        $hiddenOutboxBefore = (array) DB::table('commission_transfer_outbox')
            ->where('commission_transfer_id', $hiddenId)
            ->where('event_type', 'process')
            ->first();
        $hiddenUsersBefore = DB::table('user_infos')
            ->whereIn('user_id', [$hiddenSource, $hiddenTarget])
            ->orderBy('user_id')
            ->get()
            ->map(static function ($row): array {
                return (array) $row;
            })
            ->all();

        $visibleReference = 'scope-visible-' . $visibleSource;
        $this->asAdmin($admin)->postJson(
            $this->reconcileUri($visibleId),
            $this->reconciliationPayload([
                'external_reference' => $visibleReference,
                'withdraw_reference' => 'scope-withdraw-' . $visibleSource,
                'deposit_reference' => 'scope-deposit-' . $visibleSource,
            ])
        )->assertOk()->assertJsonPath('code', ResponseCode::SUCCESS);
        $this->rememberLedgerRows($visibleId);

        $this->asAdmin($admin)->postJson(
            $this->reconcileUri($hiddenId),
            $this->reconciliationPayload([
                'external_reference' => 'scope-hidden-' . $hiddenSource,
                'withdraw_reference' => 'scope-hidden-withdraw-' . $hiddenSource,
                'deposit_reference' => 'scope-hidden-deposit-' . $hiddenSource,
            ])
        )->assertOk()->assertJsonPath('code', ResponseCode::PERMISSION_DENIED);

        $this->assertSame(
            $hiddenTransferBefore,
            (array) DB::table('commission_transfers')->where('id', $hiddenId)->first()
        );
        $this->assertSame(
            $hiddenOutboxBefore,
            (array) DB::table('commission_transfer_outbox')
                ->where('commission_transfer_id', $hiddenId)
                ->where('event_type', 'process')
                ->first()
        );
        $this->assertSame(
            $hiddenUsersBefore,
            DB::table('user_infos')
                ->whereIn('user_id', [$hiddenSource, $hiddenTarget])
                ->orderBy('user_id')
                ->get()
                ->map(static function ($row): array {
                    return (array) $row;
                })
                ->all()
        );
        $hiddenLedgerIds = $this->ledgerUniqueIds($hiddenId);
        $this->assertSame(0, DB::table('commission_records')->whereIn('unique_id', $hiddenLedgerIds)->count());
        $this->assertSame(0, DB::table('operation_logs')
            ->where('order_no', DB::table('commission_transfers')->where('id', $hiddenId)->value('local_order_no'))
            ->count());
    }

    public function test_reconcile_requires_manual_status_whitelisted_decision_and_external_reference(): void
    {
        $this->assertReconciliationSchema();
        $admin = $this->ensureSuperAdmin();
        [$pendingSource, $pendingTarget] = $this->unusedUserPair();
        [$manualSource, $manualTarget] = $this->unusedUserPair();
        $pendingId = $this->createTransfer($pendingSource, $pendingTarget, 'pending', 'pending');
        $manualId = $this->createTransfer($manualSource, $manualTarget, 'manual_reconcile_required', 'validation');

        $this->asAdmin($admin, true)
            ->postJson($this->reconcileUri($pendingId), $this->reconciliationPayload([
                'external_reference' => 'audit-pending',
            ]))
            ->assertOk()
            ->assertJsonPath('code', ResponseCode::OPERATION_NOT_ALLOWED);

        foreach ([
            ['decision' => '', 'external_reference' => 'audit-missing-decision'],
            ['decision' => 'unknown', 'external_reference' => 'audit-unknown-decision'],
            ['decision' => 'confirmed_completed', 'external_reference' => ''],
        ] as $payload) {
            $this->asAdmin($admin, true)
                ->postJson($this->reconcileUri($manualId), $payload)
                ->assertOk()
                ->assertJsonPath('code', ResponseCode::VALIDATION_FAILED);
        }

        $this->assertSame('manual_reconcile_required', (string) DB::table('commission_transfers')->where('id', $manualId)->value('status'));
    }

    public function test_reconcile_uses_compare_and_set_writes_audit_and_never_resolves_funding_gateway(): void
    {
        $this->assertReconciliationSchema();
        $admin = $this->ensureSuperAdmin();
        [$sourceUserId, $targetUserId] = $this->unusedUserPair();
        $transferId = $this->createTransfer($sourceUserId, $targetUserId, 'manual_reconcile_required', 'cas');
        $withdrawReference = 'withdraw-ticket-' . $sourceUserId;
        $depositReference = 'deposit-ticket-' . $sourceUserId;
        $externalReference = 'provider-audit-' . $sourceUserId;

        $this->app->bind(CommissionTransferFundingGateway::class, static function (): CommissionTransferFundingGateway {
            throw new \RuntimeException('funding gateway must not be resolved during reconciliation');
        });

        $first = $this->asAdmin($admin, true)->postJson($this->reconcileUri($transferId), $this->reconciliationPayload([
            'external_reference' => $externalReference,
            'withdraw_reference' => $withdrawReference,
            'deposit_reference' => $depositReference,
        ]));
        $first->assertOk()
            ->assertJsonPath('code', ResponseCode::SUCCESS)
            ->assertJsonPath('data.status', 'completed')
            ->assertJsonPath('data.reconcile_decision', 'confirmed_completed')
            ->assertJsonPath('data.reconcile_external_reference', $externalReference);
        $this->rememberLedgerRows($transferId);

        $row = DB::table('commission_transfers')->where('id', $transferId)->first();
        $this->assertSame('completed', (string) $row->status);
        $this->assertSame('completed', (string) $row->current_step);
        $this->assertSame('confirmed_completed', (string) $row->reconcile_decision);
        $this->assertSame($externalReference, (string) $row->reconcile_external_reference);
        $this->assertSame((int) $admin->id, (int) $row->reconciled_by);
        $this->assertNotNull($row->reconciled_at);

        $log = DB::table('operation_logs')
            ->where('admin_id', $admin->id)
            ->where('order_no', $row->local_order_no)
            ->latest('id')
            ->first();
        $this->assertNotNull($log);
        $content = json_decode((string) $log->content, true);
        $this->assertIsArray($content);
        $this->assertSame('commission_transfer_reconcile', $content['a'] ?? null);
        $this->assertSame('manual_reconcile_required', $content['b']['s'] ?? null);
        $this->assertSame('completed', $content['n']['s'] ?? null);
        $this->assertSame('confirmed_completed', $content['f']['d'] ?? null);
        $this->assertSame($externalReference, $content['ref'] ?? null);
        $this->assertLessThanOrEqual(1000, strlen((string) $log->content));

        $second = $this->asAdmin($admin, true)->postJson($this->reconcileUri($transferId), $this->reconciliationPayload([
            'decision' => 'confirmed_rejected',
            'external_reference' => 'provider-audit-second',
            'withdraw_status' => 'confirmed_rejected',
            'withdraw_reference' => 'withdraw-rejected-second',
            'deposit_status' => 'confirmed_not_processed',
            'deposit_reference' => null,
            'source_balance_after' => null,
            'target_balance_after' => null,
        ]));
        $second->assertOk()->assertJsonPath('code', ResponseCode::OPERATION_NOT_ALLOWED);
        $this->assertSame(1, DB::table('operation_logs')->where('order_no', $row->local_order_no)->count());
        $this->assertSame('confirmed_completed', (string) DB::table('commission_transfers')->where('id', $transferId)->value('reconcile_decision'));
    }

    public function test_two_independent_workers_reconcile_one_manual_case_once(): void
    {
        $this->assertReconciliationSchema();
        $admin = $this->ensureSuperAdmin();
        [$sourceUserId, $targetUserId] = $this->unusedUserPair();
        $transferId = $this->createTransfer(
            $sourceUserId,
            $targetUserId,
            'manual_reconcile_required',
            'cas-race'
        );
        $evidence = $this->reconciliationPayload([
            'withdraw_reference' => 'race-withdraw-' . $sourceUserId,
            'deposit_reference' => 'race-deposit-' . $sourceUserId,
        ]);

        $results = $this->runConcurrentReconciliations(
            (int) $admin->id,
            $transferId,
            [
                'decision' => $evidence['decision'],
                'withdraw_status' => $evidence['withdraw_status'],
                'withdraw_reference' => $evidence['withdraw_reference'],
                'deposit_status' => $evidence['deposit_status'],
                'deposit_reference' => $evidence['deposit_reference'],
                'compensation_status' => $evidence['compensation_status'],
                'compensation_reference' => $evidence['compensation_reference'],
                'source_balance_after' => $evidence['source_balance_after'],
                'target_balance_after' => $evidence['target_balance_after'],
            ]
        );
        $this->rememberLedgerRows($transferId);

        $resultNames = array_map(static function (array $result): string {
            return (string) ($result['result'] ?? 'missing');
        }, $results);
        sort($resultNames);
        $this->assertSame(['not_allowed', 'ok'], $resultNames);
        $this->assertSame('completed', (string) DB::table('commission_transfers')->where('id', $transferId)->value('status'));
        $this->assertSame('completed', (string) DB::table('commission_transfer_outbox')
            ->where('commission_transfer_id', $transferId)
            ->where('event_type', 'process')
            ->value('status'));
        $this->assertSame(2, DB::table('commission_records')
            ->whereIn('unique_id', $this->ledgerUniqueIds($transferId))
            ->count());
        $this->assertSame(1, DB::table('operation_logs')
            ->where('order_no', DB::table('commission_transfers')->where('id', $transferId)->value('local_order_no'))
            ->count());
    }

    public function test_service_contract_keeps_scope_cas_audit_and_gateway_boundary_explicit(): void
    {
        $servicePath = app_path('Services/CommissionTransfer/CommissionTransferReconciliationService.php');
        $controllerPath = app_path('Http/Controllers/Admin/CommissionController.php');
        $this->assertFileExists($servicePath);
        $service = (string) file_get_contents($servicePath);
        $controller = (string) file_get_contents($controllerPath);

        foreach ([
            'AdminDataScopeService',
            'DB::transaction',
            'manual_reconcile_required',
            'OperationLog',
            'reconcile_external_reference',
            'where',
        ] as $needle) {
            $this->assertStringContainsString($needle, $service . $controller, $needle . ' is missing from reconciliation implementation.');
        }

        $this->assertStringNotContainsString('CommissionTransferFundingGateway', $service);
        $this->assertStringNotContainsString('->withdraw(', $service);
        $this->assertStringNotContainsString('->deposit(', $service);
        $this->assertStringNotContainsString('->compensate(', $service);
    }

    public function test_admin_commission_surfaces_expose_reconciliation_list_detail_and_decision_controls(): void
    {
        $blade = (string) file_get_contents(resource_path('admin/layui/commissions/index.blade.php'));
        $layui = (string) file_get_contents(public_path('js/apps/admin/layui/pages.js'));
        $pageController = (string) file_get_contents(app_path('Http/Controllers/CrmUi/Admin/PageController.php'));
        $naive = (string) file_get_contents(public_path('js/apps/admin/layui/pages.js'));

        foreach ([
            'commissionTransferReconciliationTable',
            'admin_commission_transfer_reconciliation_list',
            'admin_commission_transfer_reconcile',
            'reconciliation-cases',
            'external_reference',
            'confirmed_completed',
            'confirmed_compensated',
            'confirmed_rejected',
            'withdraw_status',
            'withdraw_reference',
            'deposit_status',
            'deposit_reference',
            'compensation_status',
            'compensation_reference',
            'source_balance_after',
            'target_balance_after',
        ] as $needle) {
            $this->assertStringContainsString($needle, $blade . $layui . $pageController . $naive, $needle . ' UI contract is missing.');
        }
    }

    private function assertReconciliationSchema(): void
    {
        $this->assertTrue(Schema::hasTable('commission_transfers'));
        foreach (['manual_origin_step', 'reconcile_decision', 'reconcile_external_reference', 'reconcile_evidence', 'reconciled_by', 'reconciled_at'] as $column) {
            $this->assertTrue(Schema::hasColumn('commission_transfers', $column), $column . ' schema column is missing.');
        }
    }

    /** @return array<int, string> */
    private function permissionSlugs(): array
    {
        return [self::PERMISSION_LIST, self::PERMISSION_DETAIL, self::PERMISSION_RECONCILE];
    }

    private function createAdminWithPermissions(array $routes, string $scopeType, array $agentIds): Admin
    {
        $now = time();
        $roleId = (int) DB::table('roles')->insertGetId([
            'name' => 'commission-reconcile-' . uniqid(),
            'guard_type' => 'admin',
            'description' => 'test role',
            'permissions' => json_encode([]),
            'status' => 1,
            'created_at' => $now,
            'updated_at' => $now,
            'deleted_at' => null,
        ]);
        $this->createdRoleIds[] = $roleId;

        foreach ($routes as $routeName) {
            $permissionId = DB::table('permissions')->where('api_route', $routeName)->value('id');
            $this->assertNotNull($permissionId, $routeName . ' permission is not seeded.');
            $rolePermissionId = (int) DB::table('role_permissions')->insertGetId([
                'role_id' => $roleId,
                'permission_id' => (int) $permissionId,
                'created_at' => $now,
                'updated_at' => $now,
                'deleted_at' => null,
            ]);
            $this->createdRolePermissionIds[] = $rolePermissionId;
        }

        $roleDataScopeId = (int) DB::table('role_data_scopes')->insertGetId([
            'role_id' => $roleId,
            'scope_type' => $scopeType,
            'agent_ids' => json_encode(array_values($agentIds)),
            'user_ids' => json_encode([]),
            'status' => 1,
            'created_at' => $now,
            'updated_at' => $now,
            'deleted_at' => null,
        ]);
        $this->createdRoleDataScopeIds[] = $roleDataScopeId;

        $adminId = (int) DB::table('admins')->insertGetId([
            'role_id' => $roleId,
            'username' => 'commission-reconcile-admin-' . uniqid(),
            'email' => uniqid() . '@example.test',
            'password' => Hash::make('password'),
            'status' => 1,
            'created_at' => $now,
            'updated_at' => $now,
            'deleted_at' => null,
        ]);
        $this->createdAdminIds[] = $adminId;

        return Admin::query()->findOrFail($adminId);
    }

    private function ensureSuperAdmin(): Admin
    {
        return $this->createAdminWithPermissions([], 'all', []);
    }

    private function createTransfer(int $sourceUserId, int $targetUserId, string $status, string $suffix): int
    {
        $now = time();
        $key = 'admin-reconcile-' . $suffix . '-' . uniqid();

        $this->ensureUser($sourceUserId, 0, '1000.00');
        $this->ensureUser($targetUserId, $sourceUserId, '100.00');

        $transferId = (int) DB::table('commission_transfers')->insertGetId([
            'local_order_no' => 'CTR-' . strtoupper($suffix) . '-' . uniqid(),
            'source_user_id' => $sourceUserId,
            'target_user_id' => $targetUserId,
            'request_purpose' => 'front_commission_transfer',
            'idempotency_key' => $key,
            'payload_hash' => hash('sha256', $key),
            'payload_ciphertext' => null,
            'amount' => 25.00,
            'remark' => 'reconciliation test',
            'status' => $status,
            'current_step' => $status === 'manual_reconcile_required' ? 'deposit' : $status,
            'manual_origin_step' => $status === 'manual_reconcile_required' ? 'deposit' : null,
            'reservation_status' => 'not_required',
            'small_limit_day' => null,
            'small_limit_key' => null,
            'withdraw_ticket' => null,
            'deposit_ticket' => null,
            'compensation_ticket' => null,
            'source_balance_after' => null,
            'target_balance_after' => null,
            'attempts' => 0,
            'available_at' => null,
            'locked_at' => null,
            'processed_at' => $status === 'manual_reconcile_required' ? $now : null,
            'provider_reference' => null,
            'last_error_code' => $status === 'manual_reconcile_required' ? 'deposit_result_unknown' : null,
            'last_error_message' => null,
            'reconcile_decision' => null,
            'reconcile_external_reference' => null,
            'reconcile_evidence' => null,
            'reconciled_by' => null,
            'reconciled_at' => null,
            'created_at' => $now,
            'updated_at' => $now,
            'deleted_at' => null,
        ]);
        $this->createdTransferIds[] = $transferId;

        $outboxId = (int) DB::table('commission_transfer_outbox')->insertGetId([
            'commission_transfer_id' => $transferId,
            'event_type' => 'process',
            'status' => $status === 'manual_reconcile_required' ? 'manual_reconcile_required' : 'pending',
            'attempts' => 0,
            'payload_hash' => hash('sha256', $key),
            'available_at' => null,
            'locked_at' => null,
            'processed_at' => $status === 'manual_reconcile_required' ? $now : null,
            'provider_reference' => null,
            'last_error_code' => $status === 'manual_reconcile_required' ? 'deposit_result_unknown' : null,
            'created_at' => $now,
            'updated_at' => $now,
            'deleted_at' => null,
        ]);
        $this->createdOutboxIds[] = $outboxId;

        $orderNumber = (string) DB::table('commission_transfers')
            ->where('id', $transferId)
            ->value('local_order_no');
        if ($orderNumber !== '' && !in_array($orderNumber, $this->createdOrderNumbers, true)) {
            $this->createdOrderNumbers[] = $orderNumber;
        }

        return $transferId;
    }

    private function ensureUser(int $userId, int $parentId, string $balance): void
    {
        if (DB::table('user_infos')->where('user_id', $userId)->exists()) {
            throw new \RuntimeException('Commission reconciliation fixture user is not isolated.');
        }

        $id = (int) DB::table('user_infos')->insertGetId([
            'user_id' => $userId,
            'login_id' => 0,
            'user_name' => 'commission-reconcile-' . $userId,
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

            if (array_intersect($ids, $this->reservedUserIds) !== []) {
                continue;
            }
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

            $this->reservedUserIds[] = $source;
            $this->reservedUserIds[] = $target;

            return [$source, $target];
        }

        throw new \RuntimeException('Unable to allocate unused admin reconciliation fixture users.');
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
        $this->cleanupStep('role_permissions', function (): void {
            if ($this->createdRolePermissionIds !== []) {
                DB::table('role_permissions')->whereIn('id', $this->createdRolePermissionIds)->delete();
            }
        }, $cleanupFailures);
        $this->cleanupStep('role_data_scopes', function (): void {
            if ($this->createdRoleDataScopeIds !== []) {
                DB::table('role_data_scopes')->whereIn('id', $this->createdRoleDataScopeIds)->delete();
            }
        }, $cleanupFailures);
        $this->cleanupStep('admins', function (): void {
            if ($this->createdAdminIds !== []) {
                DB::table('admins')->whereIn('id', $this->createdAdminIds)->delete();
            }
        }, $cleanupFailures);
        $this->cleanupStep('roles', function (): void {
            if ($this->createdRoleIds !== []) {
                DB::table('roles')->whereIn('id', $this->createdRoleIds)->delete();
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
        $this->createdRolePermissionIds = [];
        $this->createdRoleDataScopeIds = [];
        $this->createdAdminIds = [];
        $this->createdRoleIds = [];
        $this->createdUserIds = [];
        $this->reservedUserIds = [];
        $this->initialLedgerFingerprints = [];
        $this->ledgerRowFingerprints = [];
    }

    /**
     * 保留发生变化的表及其前后指纹，避免清理失败只报告一个无法定位的布尔结果。
     *
     * @param array<string, array<string, int|string>> $before
     * @param array<string, array<string, int|string>> $after
     * @return array<string, array<string, array<string, int|string>|null>>
     */
    private function fingerprintDifferences(array $before, array $after): array
    {
        $differences = [];
        foreach (array_unique(array_merge(array_keys($before), array_keys($after))) as $table) {
            $beforeFingerprint = $before[$table] ?? null;
            $afterFingerprint = $after[$table] ?? null;
            if ($beforeFingerprint === $afterFingerprint) {
                continue;
            }

            $differences[$table] = [
                'before' => $beforeFingerprint,
                'after' => $afterFingerprint,
            ];
        }

        return $differences;
    }

    /** @return array<int, string> */
    private function ledgerUniqueIds(int $transferId): array
    {
        return [
            hash('sha256', 'commission-transfer:DBCT-' . $transferId),
            hash('sha256', 'commission-transfer:WBCT-' . $transferId),
        ];
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
        foreach ($this->ledgerUniqueIds($transferId) as $uniqueId) {
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

    /**
     * @param array<string, mixed> $evidence
     * @return array<int, array<string, mixed>>
     */
    private function runConcurrentReconciliations(
        int $adminId,
        int $transferId,
        array $evidence
    ): array {
        $directory = sys_get_temp_dir()
            . DIRECTORY_SEPARATOR . 'commission-reconcile-race-' . bin2hex(random_bytes(6));
        if (!mkdir($directory, 0700, true) && !is_dir($directory)) {
            throw new \RuntimeException('Unable to create reconciliation race directory.');
        }

        $worker = base_path('tests/Support/commission_transfer_reconciliation_worker.php');
        $workerEnvironment = $this->reconciliationWorkerEnvironment();
        $processes = [];
        $paths = [];
        try {
            foreach (['worker-a', 'worker-b'] as $name) {
                $paths[$name] = [
                    'ready' => $directory . DIRECTORY_SEPARATOR . $name . '.ready',
                    'result' => $directory . DIRECTORY_SEPARATOR . $name . '.result',
                    'stdout' => $directory . DIRECTORY_SEPARATOR . $name . '.stdout',
                    'stderr' => $directory . DIRECTORY_SEPARATOR . $name . '.stderr',
                ];
                $payload = [
                    'ready' => $paths[$name]['ready'],
                    'go' => $directory . DIRECTORY_SEPARATOR . 'go',
                    'result' => $paths[$name]['result'],
                    'admin_id' => $adminId,
                    'transfer_id' => $transferId,
                    'evidence' => $evidence,
                    'external_reference' => 'race-' . $name . '-' . $transferId,
                ];
                $process = proc_open(
                    [
                        PHP_BINARY,
                        $worker,
                        base64_encode(json_encode($payload, JSON_THROW_ON_ERROR)),
                    ],
                    [
                        0 => ['pipe', 'r'],
                        1 => ['file', $paths[$name]['stdout'], 'wb'],
                        2 => ['file', $paths[$name]['stderr'], 'wb'],
                    ],
                    $pipes,
                    base_path(),
                    $workerEnvironment
                );
                $this->assertIsResource($process, 'Unable to start ' . $name . '.');
                if (isset($pipes[0]) && is_resource($pipes[0])) {
                    fclose($pipes[0]);
                }
                $processes[$name] = $process;
            }

            $deadline = microtime(true) + 10.0;
            while (microtime(true) < $deadline) {
                $ready = true;
                foreach ($paths as $path) {
                    if (!is_file($path['ready'])) {
                        $ready = false;
                        break;
                    }
                }
                if ($ready) {
                    break;
                }
                usleep(10000);
            }
            foreach ($paths as $name => $path) {
                $this->assertFileExists($path['ready'], $name . ' did not reach the race barrier.');
            }
            file_put_contents($directory . DIRECTORY_SEPARATOR . 'go', 'go', LOCK_EX);

            $results = [];
            foreach ($processes as $name => $process) {
                $exitCode = $this->waitForWorkerExit($process, $name, 15.0);
                $processes[$name] = null;
                $this->assertSame(
                    0,
                    $exitCode,
                    $name . ' failed: ' . (string) @file_get_contents($paths[$name]['stderr'])
                );
                $raw = trim((string) @file_get_contents($paths[$name]['result']));
                $decoded = json_decode($raw, true);
                $this->assertIsArray($decoded, $name . ' returned invalid JSON.');
                $results[] = $decoded;
            }

            return $results;
        } finally {
            foreach ($processes as $name => $process) {
                if (is_resource($process)) {
                    $this->terminateWorker($process, (string) $name);
                }
            }
            foreach (glob($directory . DIRECTORY_SEPARATOR . '*') ?: [] as $path) {
                @unlink($path);
            }
            @rmdir($directory);
        }
    }

    /** @return array<string, string> */
    private function reconciliationWorkerEnvironment(): array
    {
        $connection = (string) config('database.default');
        $databaseConfig = config('database.connections.' . $connection);
        $databaseName = (string) DB::getDatabaseName();

        if (!is_array($databaseConfig) || $databaseName !== 'co_crmv5_test') {
            throw new \RuntimeException('Reconciliation workers may only use co_crmv5_test.');
        }

        $environment = getenv();
        if (!is_array($environment)) {
            $environment = [];
        }

        return array_merge($environment, [
            'APP_ENV' => 'testing',
            'DB_CONNECTION' => $connection,
            'DB_HOST' => (string) ($databaseConfig['host'] ?? ''),
            'DB_PORT' => (string) ($databaseConfig['port'] ?? ''),
            'DB_SOCKET' => (string) ($databaseConfig['unix_socket'] ?? ''),
            'DB_DATABASE' => $databaseName,
            'DB_USERNAME' => (string) ($databaseConfig['username'] ?? ''),
            'DB_PASSWORD' => (string) ($databaseConfig['password'] ?? ''),
            'CACHE_DRIVER' => 'array',
            'SESSION_DRIVER' => 'array',
            'QUEUE_CONNECTION' => 'sync',
            'MT4_ENABLED' => 'false',
            'MT4_USER_SYNC_ENABLED' => 'false',
        ]);
    }

    /** @return int */
    private function waitForWorkerExit($process, string $name, float $timeout): int
    {
        $deadline = microtime(true) + $timeout;
        while (microtime(true) < $deadline) {
            $status = proc_get_status($process);
            if (!is_array($status) || !($status['running'] ?? false)) {
                return proc_close($process);
            }
            usleep(10000);
        }

        $this->terminateWorker($process, $name);
        $this->fail($name . ' exceeded the worker exit deadline.');
    }

    private function terminateWorker($process, string $name): void
    {
        $status = proc_get_status($process);
        if (is_array($status) && ($status['running'] ?? false)) {
            @proc_terminate($process);
        }

        $deadline = microtime(true) + 2.0;
        while (microtime(true) < $deadline) {
            $status = proc_get_status($process);
            if (!is_array($status) || !($status['running'] ?? false)) {
                proc_close($process);

                return;
            }
            usleep(10000);
        }

        @proc_terminate($process);
        @proc_close($process);
    }

    private function reconciliationPayload(array $overrides = []): array
    {
        return array_merge([
            'decision' => 'confirmed_completed',
            'external_reference' => 'commission-reconciliation-case',
            'withdraw_status' => 'confirmed_processed',
            'withdraw_reference' => 'withdraw-ticket-981731',
            'deposit_status' => 'confirmed_processed',
            'deposit_reference' => 'deposit-ticket-981731',
            'compensation_status' => 'confirmed_not_processed',
            'compensation_reference' => null,
            'source_balance_after' => '975.00',
            'target_balance_after' => '125.00',
        ], $overrides);
    }

    private function reconcileUri(int $transferId): string
    {
        return '/api/admin/commission-transfers/reconciliation-cases/' . $transferId . '/decisions';
    }

    private function asAdmin(Admin $admin, bool $bypassPermission = false)
    {
        $middleware = [JwtAuthMiddleware::class, SingleSignOn::class];
        if ($bypassPermission) {
            $middleware[] = CheckPermission::class;
        }

        return $this->withoutMiddleware($middleware)->actingAs($admin, 'admin');
    }
}
