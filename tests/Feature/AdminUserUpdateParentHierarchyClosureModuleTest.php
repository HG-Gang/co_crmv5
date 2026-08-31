<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/07/29
 * Time: 00:41
 */

/**
 * AdminUserUpdateParentHierarchyClosureModuleTest
 *
 * 文件功能：
 * - 验证后台用户父级层级变更闭环：MT4 同步成功后原子写本地层级、MT4 拒绝失败关闭、层级失败补偿 MT4 并回滚数据库、现代 parent_id 字段被资料更新忽略。
 * - 输入：HTTP 请求与事务回滚的数据库夹具；输出：PHPUnit 断言结果。
 * - 明确不负责：不覆盖真实 MT4 网关与线上支付/出金通道（由网关契约测试锁定）。
 */

namespace Tests\Feature;

use App\Constants\ResponseCode;
use App\Http\Middleware\AdminAuthenticate;
use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\JwtAuthMiddleware;
use App\Http\Middleware\SingleSignOn;
use App\Models\Admin;
use App\Services\AdminDataScopeService;
use App\Services\FamilyTreeService;
use App\Services\Mt4ManagerService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use RuntimeException;
use Tests\TestCase;

/**
 * 后台普通客户上级代理变更闭环测试。
 *
 * 功能逻辑说明：
 * - 旧项目 CustomerController::cust_save_info 使用 userparentId 修改普通客户直属上级。
 * - 新项目必须先同步 MT4 zip/cny，再原子更新 parent_id、family_tree、agent_descendants 和操作日志。
 * - 只有旧字段 userparentId 可以进入敏感层级分支，现代 parent_id 继续由资料编辑白名单拒绝。
 * - MT4 失败、非法上级、越过管理员数据范围或本地事务失败时，不能留下半完成的客户归属。
 */
class AdminUserUpdateParentHierarchyClosureModuleTest extends TestCase
{
    use DatabaseTransactions;

    /**
     * 验证旧字段成功迁移客户层级并重建代理闭包关系。
     *
     * @return void
     */
    public function test_legacy_parent_change_syncs_mt4_before_atomic_local_hierarchy_write(): void
    {
        $fixture = $this->seedHierarchyFixture();
        $calls = [];
        $this->bindHierarchyMt4($calls, true);

        $response = $this->withoutAdminMiddleware()
            ->actingAs($fixture['admin'], 'admin')
            ->postJson('/api/admin/updateUser', [
                'data' => [
                    'userId' => $fixture['customer_id'],
                    'username' => 'Moved Hierarchy Customer',
                    'userparentId' => $fixture['new_agent_id'],
                ],
            ]);

        $response->assertOk()
            ->assertJsonPath('code', ResponseCode::UPDATED);

        $this->assertSame([
            [
                'user_id' => $fixture['customer_id'],
                'parent_id' => $fixture['new_agent_id'],
                'relationship_code' => $fixture['new_relationship_code'],
                'before_parent_id' => $fixture['old_agent_id'],
                'before_family_tree' => $fixture['old_agent_id'] . ',' . $fixture['customer_id'],
            ],
        ], $calls, 'MT4 层级同步必须发生在本地 parent_id/family_tree 写入之前。');

        $this->assertDatabaseHas('user_infos', [
            'user_id' => $fixture['customer_id'],
            'user_name' => 'Moved Hierarchy Customer',
            'parent_id' => $fixture['new_agent_id'],
            'family_tree' => $fixture['new_root_id'] . ',' . $fixture['new_agent_id'] . ',' . $fixture['customer_id'],
        ]);
        $this->assertDatabaseMissing('agent_descendants', [
            'agent_id' => $fixture['old_agent_id'],
            'descendant_id' => $fixture['customer_id'],
            'deleted_at' => null,
        ]);
        $this->assertDatabaseHas('agent_descendants', [
            'agent_id' => $fixture['new_root_id'],
            'descendant_id' => $fixture['customer_id'],
            'descendant_type' => 2,
            'is_direct' => 0,
            'depth' => 2,
            'deleted_at' => null,
        ]);
        $this->assertDatabaseHas('agent_descendants', [
            'agent_id' => $fixture['new_agent_id'],
            'descendant_id' => $fixture['customer_id'],
            'descendant_type' => 2,
            'is_direct' => 1,
            'depth' => 1,
            'deleted_at' => null,
        ]);

        $log = DB::table('operation_logs')
            ->where('order_no', 'user_update:' . $fixture['customer_id'])
            ->first();
        $this->assertNotNull($log);
        $this->assertStringContainsString(
            'parent_id:' . $fixture['old_agent_id'] . '->' . $fixture['new_agent_id'],
            (string) $log->content
        );
        $this->assertStringContainsString('family_tree:', (string) $log->content);
    }

    /**
     * 验证 MT4 未明确成功时所有本地字段和闭包关系保持原值。
     *
     * @return void
     */
    public function test_parent_change_fails_closed_when_mt4_rejects_hierarchy_update(): void
    {
        $fixture = $this->seedHierarchyFixture();
        $calls = [];
        $this->bindHierarchyMt4($calls, false);

        $response = $this->withoutAdminMiddleware()
            ->actingAs($fixture['admin'], 'admin')
            ->patch('/api/admin/users/' . $fixture['customer_id'], [
                'user_name' => 'Must Not Persist After Mt4 Failure',
                'userparentId' => $fixture['new_agent_id'],
            ]);

        $response->assertOk()
            ->assertJsonPath('code', ResponseCode::MT4_SYNC_FAILED);

        $this->assertCount(1, $calls);
        $this->assertDatabaseHas('user_infos', [
            'user_id' => $fixture['customer_id'],
            'user_name' => 'Hierarchy Customer',
            'parent_id' => $fixture['old_agent_id'],
            'family_tree' => $fixture['old_agent_id'] . ',' . $fixture['customer_id'],
        ]);
        $this->assertDatabaseHas('agent_descendants', [
            'agent_id' => $fixture['old_agent_id'],
            'descendant_id' => $fixture['customer_id'],
            'deleted_at' => null,
        ]);
        $this->assertSame(0, DB::table('operation_logs')->where('order_no', 'user_update:' . $fixture['customer_id'])->count());
    }

    /**
     * 验证普通客户不能作为上级代理，且失败前不会调用 MT4 或写入基础资料。
     *
     * @return void
     */
    public function test_parent_change_rejects_non_agent_parent_without_partial_write(): void
    {
        $fixture = $this->seedHierarchyFixture();
        $calls = [];
        $this->bindHierarchyMt4($calls, true);

        $response = $this->withoutAdminMiddleware()
            ->actingAs($fixture['admin'], 'admin')
            ->postJson('/api/admin/updateUser', [
                'data' => [
                    'userId' => $fixture['customer_id'],
                    'username' => 'Must Not Persist Invalid Parent',
                    'userparentId' => $fixture['other_customer_id'],
                ],
            ]);

        $response->assertOk()
            ->assertJsonPath('code', ResponseCode::VALIDATION_FAILED);

        $this->assertSame([], $calls);
        $this->assertDatabaseHas('user_infos', [
            'user_id' => $fixture['customer_id'],
            'user_name' => 'Hierarchy Customer',
            'parent_id' => $fixture['old_agent_id'],
        ]);
    }

    /**
     * 验证管理员不能把客户转移给数据范围外的代理。
     *
     * @return void
     */
    public function test_parent_change_rejects_agent_outside_admin_data_scope(): void
    {
        $fixture = $this->seedHierarchyFixture();
        $calls = [];
        $this->bindHierarchyMt4($calls, true);
        $deniedAgentId = $fixture['new_agent_id'];

        $this->app->instance(AdminDataScopeService::class, new class($deniedAgentId) extends AdminDataScopeService {
            /**
             * 数据范围替身拒绝返回的代理 ID。验证更换父级到无权代理树时接口拒绝。
             * @var int
             */
            private $deniedAgentId;

            public function __construct(int $deniedAgentId)
            {
                $this->deniedAgentId = $deniedAgentId;
            }

            /**
             * 测试替身只拒绝目标上级代理，目标普通客户仍允许访问。
             *
             * @param Admin $admin 当前后台管理员。
             * @param int|string $userId 业务用户 ID。
             * @param string $targetType user=普通客户，agent=代理商。
             * @return bool true=允许，false=拒绝。
             */
            public function canAccessUser(Admin $admin, $userId, $targetType = 'user')
            {
                return !($targetType === 'agent' && (int) $userId === $this->deniedAgentId);
            }
        });

        $response = $this->withoutAdminMiddleware()
            ->actingAs($fixture['admin'], 'admin')
            ->postJson('/api/admin/updateUser', [
                'data' => [
                    'userId' => $fixture['customer_id'],
                    'userparentId' => $fixture['new_agent_id'],
                ],
            ]);

        $response->assertOk()
            ->assertJsonPath('code', ResponseCode::PERMISSION_DENIED);
        $this->assertSame([], $calls);
        $this->assertDatabaseHas('user_infos', [
            'user_id' => $fixture['customer_id'],
            'parent_id' => $fixture['old_agent_id'],
        ]);
    }

    /**
     * 验证本地闭包关系写入异常时，MT4 会在返回失败前补偿回旧上级。
     *
     * @return void
     */
    public function test_local_hierarchy_failure_compensates_mt4_and_rolls_back_database(): void
    {
        $fixture = $this->seedHierarchyFixture();
        $calls = [];
        $this->bindHierarchyMt4($calls, true);
        $this->app->instance(FamilyTreeService::class, new class extends FamilyTreeService {
            /**
             * 模拟闭包关系写入失败，用于验证本地事务回滚和 MT4 补偿。
             *
             * @param int $userId 目标普通客户 ID。
             * @param int $accountType 目标账号类型。
             * @param array<int, int> $ancestorIds 新祖先代理 ID。
             * @param int $parentId 新直属上级代理 ID。
             * @return void
             */
            public function syncCustomerDescendantRelations(int $userId, int $accountType, array $ancestorIds, int $parentId): void
            {
                throw new RuntimeException('forced hierarchy persistence failure');
            }
        });

        $response = $this->withoutAdminMiddleware()
            ->actingAs($fixture['admin'], 'admin')
            ->postJson('/api/admin/updateUser', [
                'data' => [
                    'userId' => $fixture['customer_id'],
                    'userparentId' => $fixture['new_agent_id'],
                ],
            ]);

        $response->assertOk()
            ->assertJsonPath('code', ResponseCode::INTERNAL_ERROR);

        $this->assertCount(2, $calls, '第一次调用写新层级，第二次调用必须补偿回旧层级。');
        $this->assertSame($fixture['new_agent_id'], $calls[0]['parent_id']);
        $this->assertSame($fixture['old_agent_id'], $calls[1]['parent_id']);
        $this->assertSame($fixture['old_relationship_code'], $calls[1]['relationship_code']);
        $this->assertDatabaseHas('user_infos', [
            'user_id' => $fixture['customer_id'],
            'parent_id' => $fixture['old_agent_id'],
            'family_tree' => $fixture['old_agent_id'] . ',' . $fixture['customer_id'],
        ]);
        $this->assertDatabaseHas('agent_descendants', [
            'agent_id' => $fixture['old_agent_id'],
            'descendant_id' => $fixture['customer_id'],
            'deleted_at' => null,
        ]);
    }

    /**
     * 验证现代 parent_id 字段不能绕过旧字段兼容边界修改客户归属。
     *
     * @return void
     */
    public function test_modern_parent_id_field_remains_ignored_by_profile_update(): void
    {
        $fixture = $this->seedHierarchyFixture();
        $calls = [];
        $this->bindHierarchyMt4($calls, true);

        $response = $this->withoutAdminMiddleware()
            ->actingAs($fixture['admin'], 'admin')
            ->patch('/api/admin/users/' . $fixture['customer_id'], [
                'user_name' => 'Modern Parent Field Ignored',
                'parent_id' => $fixture['new_agent_id'],
            ]);

        $response->assertOk()
            ->assertJsonPath('code', ResponseCode::UPDATED);
        $this->assertSame([], $calls);
        $this->assertDatabaseHas('user_infos', [
            'user_id' => $fixture['customer_id'],
            'user_name' => 'Modern Parent Field Ignored',
            'parent_id' => $fixture['old_agent_id'],
            'family_tree' => $fixture['old_agent_id'] . ',' . $fixture['customer_id'],
        ]);
    }

    /**
     * 验证最终中文清单记录了本闭环的真实执行链路。
     *
     * @return void
     */
    public function test_final_checklist_records_parent_hierarchy_closure(): void
    {
        $checklist = file_get_contents(base_path('docs/admin-backend-blade-permission-final-checklist.md')) ?: '';

        $this->assertStringContainsString('## 376.', $checklist);
        $this->assertStringContainsString('userparentId', $checklist);
        $this->assertStringContainsString('Mt4ManagerService::updateUserHierarchy', $checklist);
        $this->assertStringContainsString('FamilyTreeService::syncCustomerDescendantRelations', $checklist);
        $this->assertStringContainsString('agent_descendants', $checklist);
        $this->assertStringContainsString('AdminUserUpdateParentHierarchyClosureModuleTest', $checklist);
    }

    /**
     * 返回关闭后台认证、JWT、单点登录和权限中间件后的测试客户端。
     *
     * @return $this
     */
    private function withoutAdminMiddleware()
    {
        return $this->withoutMiddleware([
            AdminAuthenticate::class,
            JwtAuthMiddleware::class,
            SingleSignOn::class,
            CheckPermission::class,
        ]);
    }

    /**
     * 创建两棵代理链和一个待迁移普通客户。
     *
     * @return array<string, mixed> 测试用户 ID、管理员和期望关系码。
     */
    private function seedHierarchyFixture(): array
    {
        $now = time();
        $admin = $this->ensureSuperAdmin();
        $oldAgentId = 98727701;
        $newRootId = 98727702;
        $newAgentId = 98727703;
        $customerId = 98727704;
        $otherCustomerId = 98727705;
        $levelOneId = $this->ensureAgentLevel(1, 'Hierarchy Level One');
        $levelTwoId = $this->ensureAgentLevel(2, 'Hierarchy Level Two');

        foreach ([$oldAgentId, $newRootId, $newAgentId, $customerId, $otherCustomerId] as $userId) {
            DB::table('agent_descendants')->where('agent_id', $userId)->orWhere('descendant_id', $userId)->delete();
            DB::table('operation_logs')->where('order_no', 'user_update:' . $userId)->delete();
            DB::table('user_infos')->where('user_id', $userId)->delete();
            DB::table('user_logins')->where('user_id', $userId)->delete();
        }

        $this->insertUser($oldAgentId, 'Old Direct Agent', 1, 0, (string) $oldAgentId, $levelOneId, $now);
        $this->insertUser($newRootId, 'New Root Agent', 1, 0, (string) $newRootId, $levelOneId, $now);
        $this->insertUser($newAgentId, 'New Direct Agent', 1, $newRootId, $newRootId . ',' . $newAgentId, $levelTwoId, $now);
        $this->insertUser($customerId, 'Hierarchy Customer', 2, $oldAgentId, $oldAgentId . ',' . $customerId, 0, $now);
        $this->insertUser($otherCustomerId, 'Invalid Parent Customer', 2, $oldAgentId, $oldAgentId . ',' . $otherCustomerId, 0, $now);

        DB::table('agent_descendants')->insert([
            'agent_id' => $oldAgentId,
            'descendant_id' => $customerId,
            'descendant_type' => 2,
            'is_direct' => 1,
            'depth' => 1,
            'created_at' => $now,
            'updated_at' => $now,
            'deleted_at' => null,
        ]);

        return [
            'admin' => $admin,
            'old_agent_id' => $oldAgentId,
            'new_root_id' => $newRootId,
            'new_agent_id' => $newAgentId,
            'customer_id' => $customerId,
            'other_customer_id' => $otherCustomerId,
            'old_relationship_code' => $oldAgentId . '0000000000000000',
            'new_relationship_code' => $newRootId . $newAgentId . '000000000000',
        ];
    }

    /**
     * 创建或复用代理等级。
     *
     * @param int $levelCode 旧项目代理等级码。
     * @param string $name 等级名称。
     * @return int agent_levels.id。
     */
    private function ensureAgentLevel(int $levelCode, string $name): int
    {
        $now = time();
        DB::table('agent_levels')->updateOrInsert(
            ['level_code' => $levelCode],
            [
                'name' => $name,
                'max_commission' => 100,
                'min_commission' => 0,
                'user_commission' => 0,
                'created_at' => $now,
                'updated_at' => $now,
                'deleted_at' => null,
            ]
        );

        return (int) DB::table('agent_levels')->where('level_code', $levelCode)->value('id');
    }

    /**
     * 创建代理或普通客户的登录账号和业务资料。
     *
     * @param int $userId 业务用户 ID。
     * @param string $name 用户名称。
     * @param int $accountType 1=代理，2=普通客户。
     * @param int $parentId 直属上级代理 ID。
     * @param string $familyTree 完整家谱链。
     * @param int $levelId 代理等级主键，普通客户传 0。
     * @param int $now 当前 Unix 时间戳。
     * @return void
     */
    private function insertUser(
        int $userId,
        string $name,
        int $accountType,
        int $parentId,
        string $familyTree,
        int $levelId,
        int $now
    ): void {
        $loginId = DB::table('user_logins')->insertGetId([
            'user_id' => $userId,
            'email' => 'admin-parent-hierarchy-' . $userId . '@example.test',
            'password' => Hash::make('password'),
            'account_type' => $accountType,
            'role_id' => 0,
            'is_enabled' => 1,
            'is_cancelled' => 0,
            'source_type' => 0,
            'created_at' => $now,
            'updated_at' => $now,
            'deleted_at' => null,
        ]);

        DB::table('user_infos')->insert([
            'user_id' => $userId,
            'login_id' => $loginId,
            'user_name' => $name,
            'phone' => '188' . substr((string) $userId, -8),
            'gender' => 1,
            'level_id' => $levelId,
            'account_type' => $accountType,
            'parent_id' => $parentId,
            'family_tree' => $familyTree,
            'auth_status' => 0,
            'mt4_group' => 'HIERARCHY-GROUP',
            'leverage' => 100,
            'created_at' => $now,
            'updated_at' => $now,
            'deleted_at' => null,
        ]);
    }

    /**
     * 绑定可记录 MT4 层级调用的测试替身。
     *
     * @param array<int, array<string, mixed>> $calls MT4 调用记录。
     * @param bool $ok true=明确成功，false=模拟远端拒绝。
     * @return void
     */
    private function bindHierarchyMt4(array &$calls, bool $ok): void
    {
        $this->app->instance(Mt4ManagerService::class, new class($calls, $ok) extends Mt4ManagerService {
            /**
             * MT4 替身的调用捕获表。记录 updateUserTradingProfile 收到的入参，断言层级变更触发的同步指令。
             * @var array<int, array<string, mixed>>
             */
            private $calls;
            /**
             * MT4 替身的成功开关。false 返回连接失败，驱动 MT4 同步失败时父级变更回滚的分支。
             * @var bool
             */
            private $ok;

            public function __construct(array &$calls, bool $ok)
            {
                $this->calls = &$calls;
                $this->ok = $ok;
                parent::__construct('127.0.0.1', 0, 'k', '1', 1);
            }

            public function updateUserHierarchy($userId, $parentId, string $relationshipCode)
            {
                $before = DB::table('user_infos')->where('user_id', (int) $userId)->first();
                $this->calls[] = [
                    'user_id' => (int) $userId,
                    'parent_id' => (int) $parentId,
                    'relationship_code' => $relationshipCode,
                    'before_parent_id' => (int) $before->parent_id,
                    'before_family_tree' => (string) $before->family_tree,
                ];

                return $this->ok
                    ? ['status' => 'ok', 'err' => '0', 'message' => 'updated', 'data' => []]
                    : ['status' => 'error', 'error_code' => 'provider_rejected', 'message' => 'rejected', 'data' => []];
            }
        });
    }

    /**
     * 创建后台超级管理员。
     *
     * @return Admin 当前测试管理员。
     */
    private function ensureSuperAdmin(): Admin
    {
        $now = time();
        DB::table('admins')->updateOrInsert(
            ['id' => 1],
            [
                'username' => 'admin-parent-hierarchy-super',
                'email' => 'admin-parent-hierarchy-super@example.test',
                'password' => Hash::make('password'),
                'status' => 1,
                'created_at' => $now,
                'updated_at' => $now,
                'deleted_at' => null,
            ]
        );

        return Admin::query()->findOrFail(1);
    }
}
