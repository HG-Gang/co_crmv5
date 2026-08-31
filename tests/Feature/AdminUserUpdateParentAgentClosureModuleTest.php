<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/07/29
 * Time: 01:12
 */

/**
 * AdminUserUpdateParentAgentClosureModuleTest
 *
 * 文件功能：
 * - 验证后台用户更新上级代理边界：代理挂靠变更拒绝部分写入、非代理上级与后代环被拒绝、可迁移至平台根并清除代理闭包。
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
use App\Services\Mt4ManagerService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * 后台普通用户资料编辑上级代理调整闭环测试。
 *
 * 功能逻辑说明：
 * - 旧项目 CustomerController::cust_save_info 使用 data.userparentId 保存用户直属上级代理。
 * - 新项目不能开放现代敏感字段 parent_id 直接写入，只兼容旧 Blade 字段 userparentId。
 * - 普通资料入口只允许移动普通客户；代理商及其子树必须由代理专用流程处理，避免两套入口同时修改代理树。
 * - 客户上级调整必须同步 MT4 与本地 family_tree、agent_descendants，避免代理数据范围与返佣范围残留旧关系。
 */
class AdminUserUpdateParentAgentClosureModuleTest extends TestCase
{
    use DatabaseTransactions;

    /**
     * 验证普通资料入口拒绝移动代理商及其整棵子树。
     *
     * @return void 代理商归属、下级家谱、闭包关系和审计日志都必须保持旧值。
     */
    public function test_admin_user_update_rejects_agent_parent_move_without_partial_write(): void
    {
        $admin = $this->ensureSuperAdmin();
        $rootAgentId = 98727710;
        $oldParentId = 98727711;
        $newParentId = 98727712;
        $targetAgentId = 98727713;
        $childCustomerId = 98727714;

        $this->seedTreeForParentMove($rootAgentId, $oldParentId, $newParentId, $targetAgentId, $childCustomerId);

        $response = $this->withoutMiddleware([AdminAuthenticate::class, JwtAuthMiddleware::class, SingleSignOn::class, CheckPermission::class])
            ->actingAs($admin, 'admin')
            ->postJson('/api/admin/updateUser', [
                'data' => [
                    'userId' => $targetAgentId,
                    'username' => 'Moved Target Agent',
                    'userparentId' => (string) $newParentId,
                ],
            ]);

        $response->assertOk()
            ->assertJsonPath('code', ResponseCode::VALIDATION_FAILED);

        $this->assertDatabaseHas('user_infos', [
            'user_id' => $targetAgentId,
            'user_name' => 'Before Parent Move',
            'parent_id' => $oldParentId,
            'family_tree' => $rootAgentId . ',' . $oldParentId . ',' . $targetAgentId,
        ]);
        $this->assertDatabaseHas('user_infos', [
            'user_id' => $childCustomerId,
            'parent_id' => $targetAgentId,
            'family_tree' => $rootAgentId . ',' . $oldParentId . ',' . $targetAgentId . ',' . $childCustomerId,
        ]);

        $this->assertDatabaseHas('agent_descendants', [
            'agent_id' => $oldParentId,
            'descendant_id' => $targetAgentId,
            'descendant_type' => 1,
            'is_direct' => 1,
            'depth' => 1,
        ]);
        $this->assertDatabaseHas('agent_descendants', [
            'agent_id' => $oldParentId,
            'descendant_id' => $childCustomerId,
            'descendant_type' => 2,
            'is_direct' => 0,
            'depth' => 2,
        ]);
        $this->assertDatabaseMissing('agent_descendants', [
            'agent_id' => $newParentId,
            'descendant_id' => $targetAgentId,
        ]);
        $this->assertDatabaseMissing('agent_descendants', [
            'agent_id' => $newParentId,
            'descendant_id' => $childCustomerId,
        ]);

        $this->assertSame(
            0,
            DB::table('operation_logs')->where('order_no', 'user_update:' . $targetAgentId)->count(),
            '代理商迁移被拒绝后不能留下资料更新审计日志。'
        );
    }

    public function test_admin_user_update_rejects_non_agent_parent_without_partial_write(): void
    {
        $admin = $this->ensureSuperAdmin();
        $rootAgentId = 98727720;
        $targetCustomerId = 98727721;
        $nonAgentParentId = 98727722;

        $this->seedUser($rootAgentId, 'Root Agent For Invalid Parent', 1, 0, (string) $rootAgentId);
        $this->seedUser($targetCustomerId, 'Before Invalid Parent', 2, $rootAgentId, $rootAgentId . ',' . $targetCustomerId);
        $this->seedUser($nonAgentParentId, 'Not An Agent Parent', 2, $rootAgentId, $rootAgentId . ',' . $nonAgentParentId);

        $response = $this->withoutMiddleware([AdminAuthenticate::class, JwtAuthMiddleware::class, SingleSignOn::class, CheckPermission::class])
            ->actingAs($admin, 'admin')
            ->patch('/api/admin/users/' . $targetCustomerId, [
                'user_name' => 'Should Not Persist Parent',
                'data' => [
                    'userparentId' => (string) $nonAgentParentId,
                ],
            ]);

        $response->assertOk()
            ->assertJsonPath('code', ResponseCode::VALIDATION_FAILED);

        $this->assertDatabaseHas('user_infos', [
            'user_id' => $targetCustomerId,
            'user_name' => 'Before Invalid Parent',
            'parent_id' => $rootAgentId,
            'family_tree' => $rootAgentId . ',' . $targetCustomerId,
        ]);
        $this->assertSame(0, DB::table('operation_logs')->where('order_no', 'user_update:' . $targetCustomerId)->count());
    }

    public function test_admin_user_update_rejects_descendant_as_parent_to_prevent_cycle(): void
    {
        $admin = $this->ensureSuperAdmin();
        $rootAgentId = 98727730;
        $targetAgentId = 98727731;
        $childAgentId = 98727732;

        $this->seedUser($rootAgentId, 'Root Agent For Cycle', 1, 0, (string) $rootAgentId);
        $this->seedUser($targetAgentId, 'Before Cycle Parent', 1, $rootAgentId, $rootAgentId . ',' . $targetAgentId);
        $this->seedUser($childAgentId, 'Child Agent Cycle', 1, $targetAgentId, $rootAgentId . ',' . $targetAgentId . ',' . $childAgentId);

        $response = $this->withoutMiddleware([AdminAuthenticate::class, JwtAuthMiddleware::class, SingleSignOn::class, CheckPermission::class])
            ->actingAs($admin, 'admin')
            ->patch('/api/admin/users/' . $targetAgentId, [
                'user_name' => 'Should Not Persist Cycle',
                'data' => [
                    'userparentId' => (string) $childAgentId,
                ],
            ]);

        $response->assertOk()
            ->assertJsonPath('code', ResponseCode::VALIDATION_FAILED);

        $this->assertDatabaseHas('user_infos', [
            'user_id' => $targetAgentId,
            'user_name' => 'Before Cycle Parent',
            'parent_id' => $rootAgentId,
            'family_tree' => $rootAgentId . ',' . $targetAgentId,
        ]);
        $this->assertSame(0, DB::table('operation_logs')->where('order_no', 'user_update:' . $targetAgentId)->count());
    }

    public function test_admin_user_update_can_move_user_to_platform_root_and_clear_agent_closure(): void
    {
        $admin = $this->ensureSuperAdmin();
        $rootAgentId = 98727740;
        $targetCustomerId = 98727741;

        $this->seedUser($rootAgentId, 'Root Agent For Platform', 1, 0, (string) $rootAgentId);
        $this->seedUser($targetCustomerId, 'Before Platform Root', 2, $rootAgentId, $rootAgentId . ',' . $targetCustomerId);
        $this->seedClosure($rootAgentId, $targetCustomerId, 2, 1, 1);
        $this->bindHierarchyMt4Success();

        $response = $this->withoutMiddleware([AdminAuthenticate::class, JwtAuthMiddleware::class, SingleSignOn::class, CheckPermission::class])
            ->actingAs($admin, 'admin')
            ->patch('/api/admin/users/' . $targetCustomerId, [
                'data' => [
                    'userparentId' => '0',
                ],
            ]);

        $response->assertOk()
            ->assertJsonPath('code', ResponseCode::UPDATED);

        $this->assertDatabaseHas('user_infos', [
            'user_id' => $targetCustomerId,
            'parent_id' => 0,
            'family_tree' => (string) $targetCustomerId,
        ]);
        $this->assertSame(0, DB::table('agent_descendants')->where('descendant_id', $targetCustomerId)->count());
    }

    public function test_final_checklist_records_admin_user_update_parent_agent_boundary(): void
    {
        $checklist = file_get_contents(base_path('docs/admin-backend-blade-permission-final-checklist.md')) ?: '';

        $this->assertStringContainsString('AdminUserController::updateUser', $checklist);
        $this->assertStringContainsString('userparentId', $checklist);
        $this->assertStringContainsString('user_infos.parent_id', $checklist);
        $this->assertStringContainsString('user_infos.family_tree', $checklist);
        $this->assertStringContainsString('agent_descendants', $checklist);
        $this->assertStringContainsString('AdminUserUpdateParentAgentClosureModuleTest', $checklist);
    }

    private function ensureSuperAdmin(): Admin
    {
        $now = time();

        DB::table('admins')->updateOrInsert(
            ['id' => 1],
            [
                'username' => 'admin-user-update-parent-super',
                'email' => 'admin-user-update-parent-super@example.test',
                'password' => Hash::make('password'),
                'status' => 1,
                'created_at' => $now,
                'updated_at' => $now,
                'deleted_at' => null,
            ]
        );

        return Admin::query()->findOrFail(1);
    }

    /**
     * 构造一个旧上级、新上级、目标代理和目标下级客户的代理树，用于验证移动后闭包表会被重建。
     */
    private function seedTreeForParentMove(
        int $rootAgentId,
        int $oldParentId,
        int $newParentId,
        int $targetAgentId,
        int $childCustomerId
    ): void {
        $this->seedUser($rootAgentId, 'Root Agent Parent Move', 1, 0, (string) $rootAgentId);
        $this->seedUser($oldParentId, 'Old Parent Agent', 1, $rootAgentId, $rootAgentId . ',' . $oldParentId);
        $this->seedUser($newParentId, 'New Parent Agent', 1, $rootAgentId, $rootAgentId . ',' . $newParentId);
        $this->seedUser($targetAgentId, 'Before Parent Move', 1, $oldParentId, $rootAgentId . ',' . $oldParentId . ',' . $targetAgentId);
        $this->seedUser($childCustomerId, 'Child Customer Parent Move', 2, $targetAgentId, $rootAgentId . ',' . $oldParentId . ',' . $targetAgentId . ',' . $childCustomerId);

        $this->seedClosure($rootAgentId, $oldParentId, 1, 1, 1);
        $this->seedClosure($rootAgentId, $newParentId, 1, 1, 1);
        $this->seedClosure($rootAgentId, $targetAgentId, 1, 0, 2);
        $this->seedClosure($oldParentId, $targetAgentId, 1, 1, 1);
        $this->seedClosure($rootAgentId, $childCustomerId, 2, 0, 3);
        $this->seedClosure($oldParentId, $childCustomerId, 2, 0, 2);
        $this->seedClosure($targetAgentId, $childCustomerId, 2, 1, 1);
    }

    /**
     * 创建测试用户资料，同时清理同 user_id 的登录、资料、闭包和审计记录，避免样例之间互相污染。
     */
    private function seedUser(int $userId, string $userName, int $accountType, int $parentId, string $familyTree): void
    {
        $now = time();

        DB::table('operation_logs')->where('order_no', 'user_update:' . $userId)->delete();
        DB::table('agent_descendants')->where('agent_id', $userId)->orWhere('descendant_id', $userId)->delete();
        DB::table('user_infos')->where('user_id', $userId)->delete();
        DB::table('user_logins')->where('user_id', $userId)->delete();

        $loginId = DB::table('user_logins')->insertGetId([
            'user_id' => $userId,
            'email' => 'admin-user-update-parent-' . $userId . '@example.test',
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
            'user_name' => $userName,
            'phone' => '188277' . substr((string) $userId, -5),
            'gender' => 1,
            'level_id' => $accountType === 1 ? $this->ensureAgentLevel() : 0,
            'account_type' => $accountType,
            'parent_id' => $parentId,
            'family_tree' => $familyTree,
            'auth_status' => 0,
            'mt4_group' => $accountType === 1 ? 'AGENT-GROUP' : 'CUSTOMER-GROUP',
            'leverage' => 100,
            'is_mt4_enabled' => 1,
            'is_mt4_readonly' => 0,
            'created_at' => $now,
            'updated_at' => $now,
            'deleted_at' => null,
        ]);
    }

    /**
     * 创建可生成旧 MT4 五段关系码的一级代理等级。
     *
     * @return int agent_levels.id；代理测试夹具写入该主键后可真实解析 level_code=1。
     */
    private function ensureAgentLevel(): int
    {
        $now = time();
        DB::table('agent_levels')->updateOrInsert(
            ['level_code' => 1],
            [
                'name' => 'Parent Closure Level One',
                'max_commission' => 100,
                'min_commission' => 0,
                'user_commission' => 0,
                'created_at' => $now,
                'updated_at' => $now,
                'deleted_at' => null,
            ]
        );

        return (int) DB::table('agent_levels')->where('level_code', 1)->value('id');
    }

    /**
     * 绑定明确成功的 MT4 层级服务替身。
     *
     * @return void 平台根客户用例由此验证远端成功后的本地闭包清理，不依赖测试环境是否能连接真实 MT4。
     */
    private function bindHierarchyMt4Success(): void
    {
        $this->app->instance(Mt4ManagerService::class, new class extends Mt4ManagerService {
            public function __construct()
            {
                parent::__construct('127.0.0.1', 0, 'test-key', 'test-version', 1);
            }

            /**
             * 模拟旧 update_user 已明确写入 zip/cny。
             *
             * @param int|string $userId 目标客户账号。
             * @param int|string $parentId 新直属上级，平台根场景为 0。
             * @param string $relationshipCode 五段代理关系码。
             * @return array<string, mixed> status=ok 且 err=0 表示远端成功。
             */
            public function updateUserHierarchy($userId, $parentId, string $relationshipCode)
            {
                return ['status' => 'ok', 'err' => '0', 'data' => []];
            }
        });
    }

    private function seedClosure(int $agentId, int $descendantId, int $descendantType, int $isDirect, int $depth): void
    {
        $now = time();

        DB::table('agent_descendants')->updateOrInsert(
            [
                'agent_id' => $agentId,
                'descendant_id' => $descendantId,
            ],
            [
                'descendant_type' => $descendantType,
                'is_direct' => $isDirect,
                'depth' => $depth,
                'created_at' => $now,
                'updated_at' => $now,
                'deleted_at' => null,
            ]
        );
    }
}
