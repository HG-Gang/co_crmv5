<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/19
 * Time: 22:00
 */

/**
 * AdminDataScopeServiceTest
 *
 * 文件功能：
 * - 验证数据范围服务：custom users 与 agent tree 范围的查询过滤与单条访问检查、parent_id 树回退、孤儿绑定与环路树失败关闭。
 * - 输入：内存构造的服务对象、测试替身与必要的数据库夹具；输出：PHPUnit 断言结果。
 * - 明确不负责：不覆盖 HTTP 路由与控制器接线（由 Feature 契约测试锁定）。
 */

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\AdminAgentBinding;
use App\Models\AgentDescendant;
use App\Models\Role;
use App\Models\RoleDataScope;
use App\Models\UserInfo;
use App\Services\AdminDataScopeService;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
use Tests\Support\MySqlAutoIncrementSnapshot;
use Tests\Support\MySqlFixtureMutex;
use Tests\Support\MySqlTableFingerprint;

/**
 * 后台数据范围服务回归测试。
 *
 * 这些测试验证数据可见范围确实来自数据表配置，而不是控制器写死条件。
 */
class AdminDataScopeServiceTest extends TestCase
{
    /**
     * 本类夹具写入的数据表清单（角色数据范围、代理绑定、管理员、用户）。setUp 据此捕获指纹与自增快照。
     * @var array<int, string>
     */
    private const FIXTURE_TABLES = [
        'role_data_scopes',
        'admin_agent_bindings',
        'agent_descendants',
        'user_infos',
        'admins',
        'roles',
    ];

    /**
     * 夹具创建的角色主键清单（roles）。tearDown 据其删除角色夹具行。
     * @var array<int, int>
     */
    private $createdRoleIds = [];

    /**
     * 夹具创建的后台管理员主键清单（admins）。tearDown 据其删除管理员夹具行。
     * @var array<int, int>
     */
    private $createdAdminIds = [];

    /**
     * 夹具创建的 user_infos 主键清单（代理树节点）。tearDown 据其删除用户行。
     * @var array<int, int>
     */
    private $createdUserInfoIds = [];

    /**
     * 已被本用例占用的 user_id 集合。分配新夹具用户时跳过这些值，避免用例内撞号。
     * @var array<int, int>
     */
    private $reservedUserIds = [];

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
            $this->fixtureMutex = new MySqlFixtureMutex();
            $this->fixtureMutex->acquire();
            $this->tableFingerprints = MySqlTableFingerprint::capture(self::FIXTURE_TABLES);
            $this->autoIncrementSnapshot = MySqlAutoIncrementSnapshot::capture(self::FIXTURE_TABLES);
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
                'Admin data-scope fixture setup failed: ' . implode(' | ', $failures),
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
                'Admin data-scope fixture teardown failures: ' . implode(' | ', $cleanupFailures)
            );
        }
    }

    /**
     * custom_users 范围只允许查看配置的用户ID集合。
     *
     * @return void
     */
    public function test_custom_users_scope_limits_query_to_configured_user_ids(): void
    {
        $role = $this->createRole('指定用户范围');
        $admin = $this->createAdmin($role);
        $visibleUserId = $this->unusedUserId();
        $hiddenUserId = $this->unusedUserId();

        RoleDataScope::create([
            'role_id' => $role->id,
            'scope_type' => 'custom_users',
            'user_ids' => [$visibleUserId],
            'status' => 1,
        ]);

        $this->createUserInfo($visibleUserId, '可见客户');
        $this->createUserInfo($hiddenUserId, '不可见客户');

        $query = (new AdminDataScopeService())->apply(UserInfo::query(), $admin->fresh('role'), 'user', 'user_id');
        $visibleUserIds = $query->orderBy('user_id')->pluck('user_id')->toArray();

        $this->assertSame([$visibleUserId], array_map('intval', $visibleUserIds));
    }

    /**
     * agent_tree 范围只允许查看管理员绑定代理树下的客户。
     *
     * @return void
     */
    public function test_agent_tree_scope_limits_query_to_bound_agent_descendants(): void
    {
        $role = $this->createRole('代理树范围');
        $admin = $this->createAdmin($role);
        $boundAgentId = $this->unusedUserId();
        $otherAgentId = $this->unusedUserId();
        $visibleCustomerId = $this->unusedUserId();
        $hiddenCustomerId = $this->unusedUserId();

        $this->createUserInfo($boundAgentId, 'bound-agent', 1, 0);
        $this->createUserInfo($otherAgentId, 'other-agent', 1, 0);
        DB::table('user_infos')->where('user_id', $visibleCustomerId)->update(['parent_id' => $boundAgentId]);
        DB::table('user_infos')->where('user_id', $hiddenCustomerId)->update(['parent_id' => $otherAgentId]);

        RoleDataScope::create([
            'role_id' => $role->id,
            'scope_type' => 'agent_tree',
            'status' => 1,
        ]);

        AdminAgentBinding::create([
            'admin_id' => $admin->id,
            'agent_id' => $boundAgentId,
            'binding_type' => 'primary',
            'status' => 1,
        ]);

        AgentDescendant::create([
            'agent_id' => $boundAgentId,
            'descendant_id' => $visibleCustomerId,
            'descendant_type' => 2,
            'is_direct' => 1,
            'depth' => 1,
        ]);

        AgentDescendant::create([
            'agent_id' => $otherAgentId,
            'descendant_id' => $hiddenCustomerId,
            'descendant_type' => 2,
            'is_direct' => 1,
            'depth' => 1,
        ]);

        $this->createUserInfo($visibleCustomerId, '绑定代理客户');
        $this->createUserInfo($hiddenCustomerId, '其他代理客户');

        DB::table('user_infos')->where('user_id', $visibleCustomerId)->update(['parent_id' => $boundAgentId]);
        DB::table('user_infos')->where('user_id', $hiddenCustomerId)->update(['parent_id' => $otherAgentId]);
        $query = (new AdminDataScopeService())->apply(UserInfo::query(), $admin->fresh('role'), 'user', 'user_id');
        $visibleUserIds = $query->orderBy('user_id')->pluck('user_id')->toArray();
        $expectedUserIds = [$boundAgentId, $visibleCustomerId];
        sort($expectedUserIds);

        $this->assertSame(
            $expectedUserIds,
            array_map('intval', $visibleUserIds)
        );
    }

    /**
     * custom_users 范围必须同时限制单条用户访问。
     *
     * @return void
     */
    public function test_custom_users_scope_checks_single_user_access(): void
    {
        $role = $this->createRole('单条用户范围');
        $admin = $this->createAdmin($role);
        $visibleUserId = $this->unusedUserId();
        $hiddenUserId = $this->unusedUserId();

        RoleDataScope::create([
            'role_id' => $role->id,
            'scope_type' => 'custom_users',
            'user_ids' => [$visibleUserId],
            'status' => 1,
        ]);

        $service = new AdminDataScopeService();

        $this->assertTrue($service->canAccessUser($admin->fresh('role'), $visibleUserId, 'user'));
        $this->assertFalse($service->canAccessUser($admin->fresh('role'), $hiddenUserId, 'user'));
    }

    /**
     * agent_tree 范围必须允许访问绑定代理树下的客户，拒绝其它代理树客户。
     *
     * @return void
     */
    public function test_agent_tree_scope_checks_single_descendant_access(): void
    {
        $role = $this->createRole('单条代理树范围');
        $admin = $this->createAdmin($role);
        $boundAgentId = $this->unusedUserId();
        $visibleCustomerId = $this->unusedUserId();
        $hiddenCustomerId = $this->unusedUserId();

        $this->createUserInfo($boundAgentId, 'bound-agent', 1, 0);
        $this->createUserInfo($visibleCustomerId, 'visible-customer', 2, $boundAgentId);
        $this->createUserInfo($hiddenCustomerId, 'hidden-customer', 2, 0);

        RoleDataScope::create([
            'role_id' => $role->id,
            'scope_type' => 'agent_tree',
            'status' => 1,
        ]);

        AdminAgentBinding::create([
            'admin_id' => $admin->id,
            'agent_id' => $boundAgentId,
            'binding_type' => 'primary',
            'status' => 1,
        ]);

        AgentDescendant::create([
            'agent_id' => $boundAgentId,
            'descendant_id' => $visibleCustomerId,
            'descendant_type' => 2,
            'is_direct' => 1,
            'depth' => 1,
        ]);

        $service = new AdminDataScopeService();

        $this->assertTrue($service->canAccessUser($admin->fresh('role'), $visibleCustomerId, 'user'));
        $this->assertFalse($service->canAccessUser($admin->fresh('role'), $hiddenCustomerId, 'user'));
    }

    /**
     * 创建测试角色。
     *
     * @param string $namePrefix 角色名称前缀，用于区分测试数据。
     * @return Role
     */
    /**
     * agent_tree scope must also support imported rows that only have user_infos.parent_id.
     *
     * @return void
     */
    public function test_agent_tree_scope_uses_parent_id_tree_when_descendant_rows_are_missing(): void
    {
        $role = $this->createRole('parent-id-agent-tree-scope');
        $admin = $this->createAdmin($role);
        $boundAgentId = $this->unusedUserId();
        $subAgentId = $this->unusedUserId();
        $visibleCustomerId = $this->unusedUserId();
        $hiddenCustomerId = $this->unusedUserId();
        $hiddenParentId = $this->unusedUserId();

        RoleDataScope::create([
            'role_id' => $role->id,
            'scope_type' => 'agent_tree',
            'status' => 1,
        ]);

        AdminAgentBinding::create([
            'admin_id' => $admin->id,
            'agent_id' => $boundAgentId,
            'binding_type' => 'primary',
            'status' => 1,
        ]);

        $this->createUserInfo($boundAgentId, 'parent-only-root-agent', 1, 0);
        $this->createUserInfo($subAgentId, 'parent-only-sub-agent', 1, $boundAgentId);
        $this->createUserInfo($visibleCustomerId, 'parent-only-visible-customer', 2, $subAgentId);
        $this->createUserInfo($hiddenCustomerId, 'parent-only-hidden-customer', 2, $hiddenParentId);

        $this->assertFalse(AgentDescendant::where('agent_id', $boundAgentId)->exists());

        $query = (new AdminDataScopeService())->apply(UserInfo::query(), $admin->fresh('role'), 'user', 'user_id');
        $visibleUserIds = $query->orderBy('user_id')->pluck('user_id')->toArray();
        $expectedUserIds = [$boundAgentId, $subAgentId, $visibleCustomerId];
        sort($expectedUserIds);

        $this->assertSame($expectedUserIds, array_map('intval', $visibleUserIds));
    }

    /**
     * 业务数据范围必须覆盖绑定代理自身、所有层级下级代理，以及这些代理名下的普通客户。
     *
     * @return void
     */
    public function test_agent_tree_business_scope_includes_agents_and_customers_at_every_depth(): void
    {
        $role = $this->createRole('完整代理树业务范围');
        $admin = $this->createAdmin($role);
        $rootAgentId = $this->unusedUserId();
        $childAgentId = $this->unusedUserId();
        $grandchildAgentId = $this->unusedUserId();
        $directCustomerId = $this->unusedUserId();
        $nestedCustomerId = $this->unusedUserId();
        $hiddenCustomerId = $this->unusedUserId();

        RoleDataScope::create([
            'role_id' => $role->id,
            'scope_type' => 'agent_tree',
            'status' => 1,
        ]);

        AdminAgentBinding::create([
            'admin_id' => $admin->id,
            'agent_id' => $rootAgentId,
            'binding_type' => 'primary',
            'status' => 1,
        ]);

        $this->createUserInfo($rootAgentId, '范围根代理', 1, 0);
        $this->createUserInfo($childAgentId, '范围直属代理', 1, $rootAgentId);
        $this->createUserInfo($grandchildAgentId, '范围间接代理', 1, $childAgentId);
        $this->createUserInfo($directCustomerId, '范围直属客户', 2, $rootAgentId);
        $this->createUserInfo($nestedCustomerId, '范围间接客户', 2, $grandchildAgentId);
        $this->createUserInfo($hiddenCustomerId, '范围外客户', 2, 0);

        foreach ([
            [$childAgentId, 1, 1, 1],
            [$grandchildAgentId, 1, 0, 2],
            [$directCustomerId, 2, 1, 1],
            [$nestedCustomerId, 2, 0, 3],
        ] as [$descendantId, $descendantType, $isDirect, $depth]) {
            AgentDescendant::create([
                'agent_id' => $rootAgentId,
                'descendant_id' => $descendantId,
                'descendant_type' => $descendantType,
                'is_direct' => $isDirect,
                'depth' => $depth,
            ]);
        }

        $service = new AdminDataScopeService();
        $query = $service->apply(UserInfo::query(), $admin->fresh('role'), 'trade', 'user_id');
        $visibleUserIds = array_map('intval', $query->orderBy('user_id')->pluck('user_id')->toArray());
        $expectedUserIds = [
            $rootAgentId,
            $childAgentId,
            $grandchildAgentId,
            $directCustomerId,
            $nestedCustomerId,
        ];
        sort($expectedUserIds);

        $this->assertSame($expectedUserIds, $visibleUserIds);
        $this->assertTrue($service->canAccessUser($admin->fresh('role'), $grandchildAgentId, 'trade'));
        $this->assertTrue($service->canAccessUser($admin->fresh('role'), $nestedCustomerId, 'trade'));
        $this->assertFalse($service->canAccessUser($admin->fresh('role'), $hiddenCustomerId, 'trade'));
    }

    /**
     * Single-record access must use the same parent_id-only tree fallback.
     *
     * @return void
     */
    public function test_agent_tree_scope_checks_parent_id_descendant_access_when_descendant_rows_are_missing(): void
    {
        $role = $this->createRole('parent-id-single-access-scope');
        $admin = $this->createAdmin($role);
        $boundAgentId = $this->unusedUserId();
        $visibleCustomerId = $this->unusedUserId();
        $hiddenCustomerId = $this->unusedUserId();
        $hiddenParentId = $this->unusedUserId();

        RoleDataScope::create([
            'role_id' => $role->id,
            'scope_type' => 'agent_tree',
            'status' => 1,
        ]);

        AdminAgentBinding::create([
            'admin_id' => $admin->id,
            'agent_id' => $boundAgentId,
            'binding_type' => 'primary',
            'status' => 1,
        ]);

        $this->createUserInfo($boundAgentId, 'parent-only-access-root-agent', 1, 0);
        $this->createUserInfo($visibleCustomerId, 'parent-only-access-customer', 2, $boundAgentId);
        $this->createUserInfo($hiddenCustomerId, 'parent-only-denied-customer', 2, $hiddenParentId);

        $this->assertFalse(AgentDescendant::where('agent_id', $boundAgentId)->exists());

        $service = new AdminDataScopeService();

        $this->assertTrue($service->canAccessUser($admin->fresh('role'), $visibleCustomerId, 'user'));
        $this->assertFalse($service->canAccessUser($admin->fresh('role'), $hiddenCustomerId, 'user'));
    }

    /**
     * A binding without a root row or parent_id children is not a valid agent scope.
     *
     * @return void
     */
    public function test_agent_tree_scope_fails_closed_for_orphan_bound_agent_id(): void
    {
        $role = $this->createRole('orphan-bound-agent-scope');
        $admin = $this->createAdmin($role);
        $orphanAgentId = $this->unusedUserId();

        RoleDataScope::create([
            'role_id' => $role->id,
            'scope_type' => 'agent_tree',
            'status' => 1,
        ]);

        AdminAgentBinding::create([
            'admin_id' => $admin->id,
            'agent_id' => $orphanAgentId,
            'binding_type' => 'primary',
            'status' => 1,
        ]);

        $probe = DB::query()->fromSub(
            DB::query()->selectRaw('? as user_id', [$orphanAgentId]),
            'scope_probe'
        );
        $visibleUserIds = (new AdminDataScopeService())
            ->apply($probe, $admin->fresh('role'), 'user', 'user_id')
            ->pluck('user_id')
            ->map('intval')
            ->toArray();

        $this->assertSame([], $visibleUserIds);
        $this->assertFalse((new AdminDataScopeService())->canAccessUser(
            $admin->fresh('role'),
            $orphanAgentId,
            'user'
        ));
    }

    /**
     * A cycle invalidates the complete configured agent scope, including its root.
     *
     * @return void
     */
    public function test_agent_tree_scope_fails_closed_for_cyclic_parent_tree(): void
    {
        $role = $this->createRole('cyclic-agent-tree-scope');
        $admin = $this->createAdmin($role);
        $rootAgentId = $this->unusedUserId();
        $childAgentId = $this->unusedUserId();

        RoleDataScope::create([
            'role_id' => $role->id,
            'scope_type' => 'agent_tree',
            'status' => 1,
        ]);

        AdminAgentBinding::create([
            'admin_id' => $admin->id,
            'agent_id' => $rootAgentId,
            'binding_type' => 'primary',
            'status' => 1,
        ]);

        $this->createUserInfo($rootAgentId, 'cyclic-root-agent', 1, $childAgentId);
        $this->createUserInfo($childAgentId, 'cyclic-child-agent', 1, $rootAgentId);

        $service = new AdminDataScopeService();
        $visibleUserIds = $service
            ->apply(UserInfo::query(), $admin->fresh('role'), 'user', 'user_id')
            ->pluck('user_id')
            ->map('intval')
            ->toArray();

        $this->assertSame([], $visibleUserIds);
        $this->assertFalse($service->canAccessUser($admin->fresh('role'), $rootAgentId, 'user'));
    }

    /**
     * A valid agent root remains accessible when it has no descendants.
     *
     * @return void
     */
    public function test_agent_tree_scope_allows_valid_agent_root_without_descendants(): void
    {
        $role = $this->createRole('empty-valid-agent-tree-scope');
        $admin = $this->createAdmin($role);
        $rootAgentId = $this->unusedUserId();

        RoleDataScope::create([
            'role_id' => $role->id,
            'scope_type' => 'agent_tree',
            'status' => 1,
        ]);

        AdminAgentBinding::create([
            'admin_id' => $admin->id,
            'agent_id' => $rootAgentId,
            'binding_type' => 'primary',
            'status' => 1,
        ]);

        $this->createUserInfo($rootAgentId, 'empty-valid-root-agent', 1, 0);

        $service = new AdminDataScopeService();
        $visibleUserIds = $service
            ->apply(UserInfo::query(), $admin->fresh('role'), 'user', 'user_id')
            ->pluck('user_id')
            ->map('intval')
            ->toArray();

        $this->assertSame([$rootAgentId], $visibleUserIds);
        $this->assertTrue($service->canAccessUser($admin->fresh('role'), $rootAgentId, 'user'));
    }

    /**
     * Agent scopes reject an existing customer row configured as their root.
     *
     * @return void
     */
    public function test_agent_tree_scope_fails_closed_when_bound_root_is_not_an_agent(): void
    {
        $role = $this->createRole('customer-bound-root-scope');
        $admin = $this->createAdmin($role);
        $customerId = $this->unusedUserId();

        RoleDataScope::create([
            'role_id' => $role->id,
            'scope_type' => 'agent_tree',
            'status' => 1,
        ]);

        AdminAgentBinding::create([
            'admin_id' => $admin->id,
            'agent_id' => $customerId,
            'binding_type' => 'primary',
            'status' => 1,
        ]);

        $this->createUserInfo($customerId, 'invalid-customer-root', 2, 0);

        $service = new AdminDataScopeService();
        $visibleUserIds = $service
            ->apply(UserInfo::query(), $admin->fresh('role'), 'user', 'user_id')
            ->pluck('user_id')
            ->map('intval')
            ->toArray();

        $this->assertSame([], $visibleUserIds);
        $this->assertFalse($service->canAccessUser($admin->fresh('role'), $customerId, 'user'));
    }

    private function createRole(string $namePrefix): Role
    {
        $role = Role::create([
            'name' => $namePrefix . '-' . uniqid(),
            'guard_type' => 'admin',
            'description' => '数据范围测试角色',
            'permissions' => [],
            'status' => 1,
        ]);
        $this->createdRoleIds[] = (int) $role->id;

        return $role;
    }

    /**
     * 创建测试管理员。
     *
     * @param Role $role 管理员绑定的角色。
     * @return Admin
     */
    private function createAdmin(Role $role): Admin
    {
        $admin = Admin::create([
            'username' => 'scope-admin-' . uniqid(),
            'password' => 'secret',
            'role_id' => $role->id,
            'status' => 1,
        ]);
        $this->createdAdminIds[] = (int) $admin->id;

        return $admin;
    }

    /**
     * 创建最小用户资料记录。
     *
     * @param int $userId 业务用户ID。
     * @param string $userName 用户名称。
     * @return UserInfo
     */
    private function createUserInfo(
        int $userId,
        string $userName,
        int $accountType = 2,
        int $parentId = 0
    ): UserInfo
    {
        $userInfo = UserInfo::create([
            'user_id' => $userId,
            'login_id' => $userId,
            'user_name' => $userName,
            'account_type' => $accountType,
            'parent_id' => $parentId,
        ]);
        $this->createdUserInfoIds[] = (int) $userInfo->id;

        return $userInfo;
    }

    private function unusedUserId(): int
    {
        for ($attempt = 0; $attempt < 200; $attempt++) {
            $candidate = random_int(1000000000, 1999999999);
            if (in_array($candidate, $this->reservedUserIds, true)) {
                continue;
            }
            if (DB::table('user_infos')
                ->where('user_id', $candidate)
                ->orWhere('login_id', $candidate)
                ->exists()) {
                continue;
            }
            if (DB::table('admin_agent_bindings')->where('agent_id', $candidate)->exists()) {
                continue;
            }
            if (DB::table('agent_descendants')
                ->where('agent_id', $candidate)
                ->orWhere('descendant_id', $candidate)
                ->exists()) {
                continue;
            }

            $this->reservedUserIds[] = $candidate;

            return $candidate;
        }

        throw new \RuntimeException('Unable to allocate an unused admin data-scope fixture user.');
    }

    /** @param array<int, string> $cleanupFailures */
    private function cleanupFixture(array &$cleanupFailures): void
    {
        $this->cleanupStep('role_data_scopes', function (): void {
            if ($this->createdRoleIds !== []) {
                DB::table('role_data_scopes')->whereIn('role_id', $this->createdRoleIds)->delete();
            }
        }, $cleanupFailures);
        $this->cleanupStep('admin_agent_bindings', function (): void {
            if ($this->createdAdminIds !== []) {
                DB::table('admin_agent_bindings')->whereIn('admin_id', $this->createdAdminIds)->delete();
            }
        }, $cleanupFailures);
        $this->cleanupStep('agent_descendants', function (): void {
            if ($this->reservedUserIds !== []) {
                DB::table('agent_descendants')
                    ->where(function ($query): void {
                        $query->whereIn('agent_id', $this->reservedUserIds)
                            ->orWhereIn('descendant_id', $this->reservedUserIds);
                    })
                    ->delete();
            }
        }, $cleanupFailures);
        $this->cleanupStep('user_infos', function (): void {
            if ($this->createdUserInfoIds !== []) {
                DB::table('user_infos')->whereIn('id', $this->createdUserInfoIds)->delete();
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
        $this->createdRoleIds = [];
        $this->createdAdminIds = [];
        $this->createdUserInfoIds = [];
        $this->reservedUserIds = [];
        $this->autoIncrementSnapshot = null;
        $this->fixtureMutex = null;
        $this->tableFingerprints = [];
    }
}
