<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/02
 * Time: 01:45
 */

/**
 * 文件功能：验证角色数据范围保存接口（saveRoleDataScope）对 agent_ids、user_ids
 *           列表的严格校验，非法列表不得写入范围，并核对最终检查清单文档。
 *
 * 适用场景：后台 /api/admin/saveRoleDataScope 接口的输入校验回归测试。
 *
 * 入参例子：
 * - POST /api/admin/saveRoleDataScope：{role_id, scope_type, agent_ids, user_ids, status}
 *
 * 返回值：
 * - agent_ids/user_ids 带非数字后缀时返回 code=VALIDATION_FAILED，
 *   role_data_scopes 不写入任何记录。
 *
 * 异常或失败场景：
 * - 非严格数字 ID 列表（如 '982805abc'）时校验失败，不落库。
 */

namespace Tests\Feature;

use App\Constants\ResponseCode;
use App\Http\Middleware\AdminAuthenticate;
use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\JwtAuthMiddleware;
use App\Http\Middleware\SingleSignOn;
use App\Models\Admin;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminDataScopeRoleIdListValidationClosureModuleTest extends TestCase
{
    use DatabaseTransactions;

    // 保存角色数据范围时应拒绝非严格 agent_ids 且不写入范围。
    public function test_save_role_data_scope_rejects_non_strict_agent_ids_without_writing_scope(): void
    {
        $actor = $this->ensureSuperAdmin();
        $roleId = $this->createAdminRole('data-scope-agent-list-role');
        $agentId = 982805;

        $response = $this->withoutMiddleware([AdminAuthenticate::class, JwtAuthMiddleware::class, SingleSignOn::class, CheckPermission::class])
            ->actingAs($actor, 'admin')
            ->post('/api/admin/saveRoleDataScope', [
                'role_id' => $roleId,
                'scope_type' => 'custom_agents',
                'agent_ids' => $agentId . 'abc',
                'user_ids' => '',
                'status' => 1,
            ]);

        $response->assertOk()
            ->assertJsonPath('code', ResponseCode::VALIDATION_FAILED);

        $this->assertDatabaseMissing('role_data_scopes', [
            'role_id' => $roleId,
        ]);
    }

    // 保存角色数据范围时应拒绝非严格 user_ids 且不写入范围。
    public function test_save_role_data_scope_rejects_non_strict_user_ids_without_writing_scope(): void
    {
        $actor = $this->ensureSuperAdmin();
        $roleId = $this->createAdminRole('data-scope-user-list-role');
        $userId = 10001;

        $response = $this->withoutMiddleware([AdminAuthenticate::class, JwtAuthMiddleware::class, SingleSignOn::class, CheckPermission::class])
            ->actingAs($actor, 'admin')
            ->post('/api/admin/saveRoleDataScope', [
                'role_id' => $roleId,
                'scope_type' => 'custom_users',
                'agent_ids' => '',
                'user_ids' => $userId . 'abc',
                'status' => 1,
            ]);

        $response->assertOk()
            ->assertJsonPath('code', ResponseCode::VALIDATION_FAILED);

        $this->assertDatabaseMissing('role_data_scopes', [
            'role_id' => $roleId,
        ]);
    }

    // 核对最终检查清单文档记录了数据范围 ID 列表校验边界。
    public function test_final_checklist_records_data_scope_role_id_list_validation_boundary(): void
    {
        $checklist = file_get_contents(base_path('docs/admin-backend-blade-permission-final-checklist.md')) ?: '';

        $this->assertStringContainsString('## 300.', $checklist);
        $this->assertStringContainsString('DataScopeController::saveRoleDataScope', $checklist);
        $this->assertStringContainsString('/api/admin/saveRoleDataScope', $checklist);
        $this->assertStringContainsString('role_data_scopes.agent_ids', $checklist);
        $this->assertStringContainsString('role_data_scopes.user_ids', $checklist);
        $this->assertStringContainsString('AdminDataScopeRoleIdListValidationClosureModuleTest', $checklist);
    }

    private function ensureSuperAdmin(): Admin
    {
        $now = time();

        DB::table('admins')->updateOrInsert(
            ['id' => 1],
            [
                'username' => 'admin-data-scope-role-id-list-super',
                'email' => 'admin-data-scope-role-id-list-super@example.test',
                'password' => Hash::make('password'),
                'status' => 1,
                'created_at' => $now,
                'updated_at' => $now,
                'deleted_at' => null,
            ]
        );

        return Admin::query()->findOrFail(1);
    }

    private function createAdminRole(string $name): int
    {
        $now = time();

        return (int) DB::table('roles')->insertGetId([
            'name' => $name . '-' . uniqid(),
            'guard_type' => 'admin',
            'description' => 'Data scope id list validation role',
            'permissions' => json_encode([]),
            'status' => 1,
            'created_at' => $now,
            'updated_at' => $now,
            'deleted_at' => null,
        ]);
    }
}
