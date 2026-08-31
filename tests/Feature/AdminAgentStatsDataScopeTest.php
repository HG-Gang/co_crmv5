<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/16
 * Time: 23:47
 */

/**
 * 后台代理统计接口数据范围（data scope）测试。
 *
 * 文件功能：
 * - 验证受限管理员调用 /api/admin/agentStatsList 时只能看到其绑定代理树及其后代的数据，汇总金额只统计可见范围。
 * - 验证按 user_id 直查/下钻其它代理树时返回空结果，防止越权读取。
 * - 验证自定义用户范围（custom_users）下直接子代理数量只统计范围内可见子级。
 *
 * 适用场景：
 * - 后台代理统计模块的数据权限隔离回归测试，覆盖角色数据范围绑定与汇总金额口径。
 *
 * 入参例子：
 * - POST /api/admin/agentStatsList
 *   {
 *     "form": 1,
 *     "user_name": "{name_prefix}",
 *     "per_page": 20
 *   }
 *
 * 方法功能：
 * - test_restricted_admin_only_receives_bound_agent_tree_and_scoped_stats：受限管理员只返回绑定树成员，汇总金额仅含可见树数据。
 * - test_restricted_admin_cannot_select_an_agent_from_another_tree_by_user_id：按 user_id 直查其它树成员返回空列表与零汇总。
 * - test_restricted_admin_cannot_drill_into_another_tree_by_user_id：按 user_id 下钻其它树返回空列表。
 * - test_direct_counts_only_include_children_visible_to_custom_users_scope：custom_users 范围下直接子级数量与佣金只统计可见子级。
 *
 * 返回值：
 * - 接口成功返回 code=SUCCESS；断言失败时抛出 PHPUnit 断言异常。
 *
 * 异常或失败场景：
 * - 若受限管理员能看到范围外数据、金额汇总包含隐藏树或计数包含不可见子级，测试断言失败。
 */

declare(strict_types=1);

namespace Tests\Feature;

use App\Constants\ResponseCode;
use App\Http\Middleware\AdminAuthenticate;
use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\JwtAuthMiddleware;
use App\Http\Middleware\SingleSignOn;
use App\Models\Admin;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Tests\Support\MySqlAutoIncrementSnapshot;
use Tests\Support\MySqlFixtureMutex;
use Tests\Support\MySqlTableFingerprint;
use Tests\TestCase;

final class AdminAgentStatsDataScopeTest extends TestCase
{
    /**
     * 本类夹具写入/断言的全部数据表（角色、数据范围、代理绑定、用户与成交）。
     * setUp 据此捕获表指纹与 AUTO_INCREMENT 快照，tearDown 据此校验清理是否彻底。
     * @var array<int, string>
     */
    private const FIXTURE_TABLES = [
        'roles',
        'role_data_scopes',
        'admins',
        'admin_agent_bindings',
        'user_logins',
        'user_infos',
        'agent_descendants',
        'user_trades',
    ];

    /**
     * tearDown 删除夹具行的顺序，按外键依赖自叶子表到父表排列，保证删除不留孤儿行。
     * @var array<int, string>
     */
    private const CLEANUP_ORDER = [
        'user_trades',
        'agent_descendants',
        'user_infos',
        'user_logins',
        'admin_agent_bindings',
        'role_data_scopes',
        'admins',
        'roles',
    ];

    /**
     * 表名 => 本用例插入的主键列表。tearDown 按 CLEANUP_ORDER 逐表删除夹具行。
     * @var array<string, array<int, int>>
     */
    private $createdRowIds = [];

    /**
     * 已被本用例占用的 user_id 集合。分配新夹具用户时跳过这些值，避免用例内撞号。
     * @var array<int, int>
     */
    private $reservedUserIds = [];

    /**
     * 已被本用例占用的 MT4 ticket 集合。分配新订单时跳过，避免同一用例内重复订单号。
     * @var array<int, int>
     */
    private $reservedTickets = [];

    /**
     * setUp 生成的随机十六进制令牌，用于拼接唯一用户名/订单前缀，避免重复运行或并行时唯一键冲突。
     * @var string
     */
    private $fixtureToken = '';

    /**
     * setUp 捕获的 MySqlAutoIncrementSnapshot 实例；tearDown 调用 restore() 还原各表自增值。null 表示尚未捕获。
     * @var MySqlAutoIncrementSnapshot|null
     */
    private $autoIncrementSnapshot;

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

        try {
            $this->fixtureToken = bin2hex(random_bytes(8));
            $this->fixtureMutex = new MySqlFixtureMutex();
            $this->fixtureMutex->acquire();
            $this->tableFingerprints = MySqlTableFingerprint::capture(self::FIXTURE_TABLES);
            $this->autoIncrementSnapshot = MySqlAutoIncrementSnapshot::capture(self::FIXTURE_TABLES);
        } catch (\Throwable $exception) {
            $this->abortFixtureSetup($exception);
        }
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
                $after = MySqlTableFingerprint::capture(self::FIXTURE_TABLES);
                if ($after !== $this->tableFingerprints) {
                    // 输出具体差异表与差异维度，便于定位是残留数据还是自增序列未还原。
                    $diffs = [];
                    foreach (self::FIXTURE_TABLES as $table) {
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
                    $cleanupFailures[] = 'table_fingerprint_mismatch(' . implode(' | ', $diffs) . ')';
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
                'Admin agent stats data-scope fixture teardown failures: ' . implode(' | ', $cleanupFailures)
            );
        }
    }

    /**
     * 受限管理员只返回绑定代理树成员，汇总金额仅含可见树数据。
     *
     * @return void
     */
    public function test_restricted_admin_only_receives_bound_agent_tree_and_scoped_stats(): void
    {
        $fixture = $this->createAgentTreeFixture();

        $response = $this->postAgentStats($fixture['admin'], [
            'form' => 1,
            'user_name' => $fixture['name_prefix'],
            'per_page' => 20,
        ]);

        $response->assertOk()->assertJsonPath('code', ResponseCode::SUCCESS);

        $rows = collect($response->json('data.data'));
        $actualIds = $rows->pluck('user_id')->map(function ($userId): int {
            return (int) $userId;
        })->sort()->values()->all();
        $expectedIds = [$fixture['visible_root_id'], $fixture['visible_child_id']];
        sort($expectedIds);

        $this->assertSame($expectedIds, $actualIds);
        $this->assertSame(2, (int) $response->json('data.count'));

        $rootRow = $rows->firstWhere('user_id', $fixture['visible_root_id']);
        $this->assertNotNull($rootRow);
        $this->assertSame(1, (int) $rootRow['mun']);
        $this->assertSame(1, (int) $rootRow['user_mun']);

        $this->assertSame('303.30', $response->json('data.totalRow.BALANCE'));
        $this->assertSame('333.30', $response->json('data.totalRow.EQUITY'));
        $this->assertSame('10.01', $response->json('data.totalRow.fy_money'));
        $this->assertSame('20.02', $response->json('data.totalRow.rj_money'));
        $this->assertSame('-3.03', $response->json('data.totalRow.qk_money'));

        $this->assertStringNotContainsString($fixture['hidden_root_name'], $response->getContent());
        $this->assertStringNotContainsString($fixture['hidden_child_name'], $response->getContent());
        $this->assertStringNotContainsString('900.09', $response->getContent());
    }

    /**
     * 受限管理员按 user_id 直查其它代理树成员：断言返回空列表与零汇总。
     *
     * @return void
     */
    public function test_restricted_admin_cannot_select_an_agent_from_another_tree_by_user_id(): void
    {
        $fixture = $this->createAgentTreeFixture();

        $response = $this->postAgentStats($fixture['admin'], [
            'form' => 1,
            'user_id' => $fixture['hidden_root_id'],
            'per_page' => 5,
        ]);

        $response->assertOk()->assertJsonPath('code', ResponseCode::SUCCESS);
        $this->assertSame([], $response->json('data.data'));
        $this->assertSame(0, (int) $response->json('data.count'));
        $this->assertSame('0.00', $response->json('data.totalRow.BALANCE'));
        $this->assertStringNotContainsString($fixture['hidden_root_name'], $response->getContent());
    }

    /**
     * 受限管理员按 user_id 下钻其它代理树：断言返回空列表。
     *
     * @return void
     */
    public function test_restricted_admin_cannot_drill_into_another_tree_by_user_id(): void
    {
        $fixture = $this->createAgentTreeFixture();

        $response = $this->postAgentStats($fixture['admin'], [
            'user_id' => $fixture['hidden_root_id'],
            'per_page' => 5,
        ]);

        $response->assertOk()->assertJsonPath('code', ResponseCode::SUCCESS);
        $this->assertSame([], $response->json('data.data'));
        $this->assertSame(0, (int) $response->json('data.count'));
        $this->assertStringNotContainsString($fixture['hidden_child_name'], $response->getContent());
    }

    /**
     * custom_users 范围下：直接子级数量与佣金汇总只统计范围内可见子级。
     *
     * @return void
     */
    public function test_direct_counts_only_include_children_visible_to_custom_users_scope(): void
    {
        $rootId = $this->unusedUserId();
        $hiddenAgentId = $this->unusedUserId();
        $hiddenCustomerId = $this->unusedUserId();
        $admin = $this->createRestrictedAdmin('custom_users', [$rootId]);
        $now = time();

        $this->createUser($rootId, 'Scoped root ' . $this->fixtureToken, 1, 0, 44.44, 55.55, $now - 30);
        $this->createUser($hiddenAgentId, 'Unscoped child agent ' . $this->fixtureToken, 1, $rootId, 9000, 9100, $now - 20);
        $this->createUser($hiddenCustomerId, 'Unscoped child customer ' . $this->fixtureToken, 2, $rootId, 9200, 9300, $now - 10);
        $this->createTrade($rootId, 4.44, 'agent rebate -FY');
        $this->createTrade($hiddenAgentId, 944.44, 'agent rebate -FY');

        $response = $this->postAgentStats($admin, [
            'form' => 1,
            'user_id' => $rootId,
            'per_page' => 5,
        ]);

        $response->assertOk()->assertJsonPath('code', ResponseCode::SUCCESS);
        $this->assertSame($rootId, (int) $response->json('data.data.0.user_id'));
        $this->assertSame(0, (int) $response->json('data.data.0.mun'));
        $this->assertSame(0, (int) $response->json('data.data.0.user_mun'));
        $this->assertSame('4.44', $response->json('data.totalRow.fy_money'));
        $this->assertStringNotContainsString('944.44', $response->getContent());
    }

    /** @return array<string, mixed> */
    private function createAgentTreeFixture(): array
    {
        $visibleRootId = $this->unusedUserId();
        $visibleChildId = $this->unusedUserId();
        $visibleCustomerId = $this->unusedUserId();
        $hiddenRootId = $this->unusedUserId();
        $hiddenChildId = $this->unusedUserId();
        $hiddenCustomerId = $this->unusedUserId();
        $admin = $this->createRestrictedAdmin('agent_tree');
        $now = time();
        $namePrefix = 'Agent scope ' . $this->fixtureToken;
        $hiddenRootName = $namePrefix . ' hidden root';
        $hiddenChildName = $namePrefix . ' hidden child';

        $this->createUser($visibleRootId, $namePrefix . ' visible root', 1, 0, 101.10, 111.10, $now - 60);
        $this->createUser($visibleChildId, $namePrefix . ' visible child', 1, $visibleRootId, 202.20, 222.20, $now - 50);
        $this->createUser($visibleCustomerId, $namePrefix . ' visible customer', 2, $visibleRootId, 1, 1, $now - 40);
        $this->createUser($hiddenRootId, $hiddenRootName, 1, 0, 4000.40, 5000.50, $now - 30);
        $this->createUser($hiddenChildId, $hiddenChildName, 1, $hiddenRootId, 6000.60, 7000.70, $now - 20);
        $this->createUser($hiddenCustomerId, $namePrefix . ' hidden customer', 2, $hiddenRootId, 1, 1, $now - 10);

        $this->createDescendant($visibleRootId, $visibleChildId, 1);
        $this->createDescendant($visibleRootId, $visibleCustomerId, 2);
        $this->createDescendant($hiddenRootId, $hiddenChildId, 1);
        $this->createDescendant($hiddenRootId, $hiddenCustomerId, 2);
        $this->createBinding((int) $admin->id, $visibleRootId);

        $this->createTrade($visibleRootId, 10.01, 'agent rebate -FY');
        $this->createTrade($visibleChildId, 20.02, 'Deposit approved');
        $this->createTrade($visibleChildId, -3.03, 'Withdrawal approved');
        $this->createTrade($hiddenRootId, 900.09, 'agent rebate -FY');
        $this->createTrade($hiddenChildId, 800.08, 'Deposit approved');

        return [
            'admin' => $admin,
            'name_prefix' => $namePrefix,
            'visible_root_id' => $visibleRootId,
            'visible_child_id' => $visibleChildId,
            'hidden_root_id' => $hiddenRootId,
            'hidden_root_name' => $hiddenRootName,
            'hidden_child_name' => $hiddenChildName,
        ];
    }

    /** @param array<int, int> $userIds */
    private function createRestrictedAdmin(string $scopeType, array $userIds = []): Admin
    {
        $now = time();
        $roleId = DB::table('roles')->insertGetId([
            'name' => 'agent-stats-scope-' . $this->fixtureToken . '-' . count($this->createdRowIds),
            'guard_type' => 'admin',
            'description' => 'Agent stats data-scope test role',
            'permissions' => json_encode([]),
            'status' => 1,
            'created_at' => $now,
            'updated_at' => $now,
            'deleted_at' => null,
        ]);
        $this->recordRowId('roles', $roleId);

        $scopeId = DB::table('role_data_scopes')->insertGetId([
            'role_id' => $roleId,
            'scope_type' => $scopeType,
            'agent_ids' => null,
            'user_ids' => $userIds === [] ? null : json_encode($userIds),
            'status' => 1,
            'created_at' => $now,
            'updated_at' => $now,
            'deleted_at' => null,
        ]);
        $this->recordRowId('role_data_scopes', $scopeId);

        $adminId = $this->insertAdmin($roleId, $now);
        if ($adminId === 1) {
            $adminId = $this->insertAdmin($roleId, $now);
        }

        return Admin::query()->findOrFail($adminId);
    }

    private function insertAdmin(int $roleId, int $now): int
    {
        $sequence = count($this->createdRowIds['admins'] ?? []);
        $adminId = DB::table('admins')->insertGetId([
            'username' => 'agent-stats-scope-' . $this->fixtureToken . '-' . $sequence,
            'email' => 'agent-stats-scope-' . $this->fixtureToken . '-' . $sequence . '@example.test',
            'password' => bcrypt('password'),
            'role_id' => $roleId,
            'status' => 1,
            'created_at' => $now,
            'updated_at' => $now,
            'deleted_at' => null,
        ]);
        $this->recordRowId('admins', $adminId);

        return $adminId;
    }

    private function createBinding(int $adminId, int $agentId): void
    {
        $now = time();
        $id = DB::table('admin_agent_bindings')->insertGetId([
            'admin_id' => $adminId,
            'agent_id' => $agentId,
            'binding_type' => 'primary',
            'status' => 1,
            'created_at' => $now,
            'updated_at' => $now,
            'deleted_at' => null,
        ]);
        $this->recordRowId('admin_agent_bindings', $id);
    }

    private function createUser(
        int $userId,
        string $userName,
        int $accountType,
        int $parentId,
        float $balance,
        float $equity,
        int $createdAt
    ): void {
        $loginId = DB::table('user_logins')->insertGetId([
            'user_id' => $userId,
            'email' => 'agent-stats-scope-' . $this->fixtureToken . '-' . $userId . '@example.test',
            'password' => bcrypt('password'),
            'account_type' => $accountType,
            'is_enabled' => 1,
            'is_cancelled' => 0,
            'source_type' => 0,
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
            'deleted_at' => null,
        ]);
        $this->recordRowId('user_logins', $loginId);

        $userInfoId = DB::table('user_infos')->insertGetId([
            'user_id' => $userId,
            'login_id' => $loginId,
            'user_name' => $userName,
            'phone' => '188' . substr((string) $userId, -8),
            'account_type' => $accountType,
            'parent_id' => $parentId,
            'level_id' => $accountType === 1 ? 1 : 0,
            'comm_rate' => 1,
            'auth_status' => 1,
            'total_funds' => $balance,
            'equity' => $equity,
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
            'deleted_at' => null,
        ]);
        $this->recordRowId('user_infos', $userInfoId);
    }

    private function createDescendant(int $agentId, int $descendantId, int $descendantType): void
    {
        $now = time();
        $id = DB::table('agent_descendants')->insertGetId([
            'agent_id' => $agentId,
            'descendant_id' => $descendantId,
            'descendant_type' => $descendantType,
            'is_direct' => 1,
            'depth' => 1,
            'created_at' => $now,
            'updated_at' => $now,
            'deleted_at' => null,
        ]);
        $this->recordRowId('agent_descendants', $id);
    }

    private function createTrade(int $userId, float $profit, string $comment): void
    {
        $now = time();
        $ticket = $this->unusedTicket();
        $id = DB::table('user_trades')->insertGetId([
            'user_id' => $userId,
            'ticket' => $ticket,
            'symbol' => '',
            'digits' => 2,
            'cmd' => 6,
            'volume' => 0,
            'open_time' => date('Y-m-d H:i:s', $now),
            'open_price' => 0,
            'stop_loss' => 0,
            'take_profit' => 0,
            'close_time' => date('Y-m-d H:i:s', $now),
            'expiration' => null,
            'reason' => 0,
            'conv_rate1' => 0,
            'conv_rate2' => 0,
            'commission' => 0,
            'commission_agent' => 0,
            'swaps' => 0,
            'close_price' => 0,
            'profit' => $profit,
            'taxes' => 0,
            'comment' => $comment,
            'internal_id' => 0,
            'margin_rate' => 0,
            'timestamp_val' => $now,
            'magic' => 0,
            'gw_volume' => 0,
            'gw_open_price' => 0,
            'gw_close_price' => 0,
            'modify_time' => date('Y-m-d H:i:s', $now),
            'settlement_status' => 0,
            'settled_at' => null,
            'created_at' => $now,
            'updated_at' => $now,
            'deleted_at' => null,
        ]);
        $this->recordRowId('user_trades', $id);
    }

    private function postAgentStats(Admin $admin, array $payload): TestResponse
    {
        return $this->withoutMiddleware([
            AdminAuthenticate::class,
            JwtAuthMiddleware::class,
            SingleSignOn::class,
            CheckPermission::class,
        ])->actingAs($admin, 'admin')->post('/api/admin/agentStatsList', $payload);
    }

    private function unusedUserId(): int
    {
        for ($attempt = 0; $attempt < 200; $attempt++) {
            $candidate = random_int(1000000000, 1900000000);
            if (in_array($candidate, $this->reservedUserIds, true)) {
                continue;
            }

            $used = DB::table('user_infos')->where('user_id', $candidate)->exists()
                || DB::table('user_logins')->where('user_id', $candidate)->exists()
                || DB::table('user_trades')->where('user_id', $candidate)->exists()
                || DB::table('admin_agent_bindings')->where('agent_id', $candidate)->exists()
                || DB::table('agent_descendants')
                    ->where('agent_id', $candidate)
                    ->orWhere('descendant_id', $candidate)
                    ->exists();

            if (!$used) {
                $this->reservedUserIds[] = $candidate;

                return $candidate;
            }
        }

        throw new \RuntimeException('Unable to allocate an unused agent stats fixture user ID.');
    }

    private function unusedTicket(): int
    {
        for ($attempt = 0; $attempt < 200; $attempt++) {
            $candidate = random_int(1000000000, 1900000000);
            if (in_array($candidate, $this->reservedTickets, true)) {
                continue;
            }
            if (DB::table('user_trades')->where('ticket', $candidate)->exists()) {
                continue;
            }

            $this->reservedTickets[] = $candidate;

            return $candidate;
        }

        throw new \RuntimeException('Unable to allocate an unused agent stats fixture ticket.');
    }

    private function recordRowId(string $table, int $id): void
    {
        $this->createdRowIds[$table][] = $id;
    }

    /** @param array<int, string> $cleanupFailures */
    private function cleanupFixture(array &$cleanupFailures): void
    {
        foreach (self::CLEANUP_ORDER as $table) {
            $ids = $this->createdRowIds[$table] ?? [];
            if ($ids === []) {
                continue;
            }

            try {
                DB::table($table)->whereIn('id', $ids)->delete();
            } catch (\Throwable $exception) {
                $cleanupFailures[] = $table . ': ' . $exception->getMessage();
            }
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
                'Admin agent stats data-scope fixture setup failed: ' . implode(' | ', $failures),
                0,
                $cause
            );
        }

        throw $cause;
    }

    private function resetFixtureState(): void
    {
        $this->createdRowIds = [];
        $this->reservedUserIds = [];
        $this->reservedTickets = [];
        $this->fixtureToken = '';
        $this->autoIncrementSnapshot = null;
        $this->fixtureMutex = null;
        $this->tableFingerprints = [];
    }
}
