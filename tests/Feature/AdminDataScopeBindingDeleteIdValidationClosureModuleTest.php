<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/02
 * Time: 01:45
 */

/**
 * 文件功能：验证管理员-代理绑定删除接口（deleteAdminAgentBinding）对请求体 id
 *           的严格校验，非法 id 不得删除绑定，并核对最终检查清单文档。
 *
 * 适用场景：后台 /api/admin/deleteAdminAgentBinding 接口的输入校验回归测试。
 *
 * 入参例子：
 * - POST /api/admin/deleteAdminAgentBinding：{id}
 *
 * 返回值：
 * - id 带非数字后缀时返回 code=VALIDATION_FAILED，绑定记录保持原样。
 *
 * 异常或失败场景：
 * - 非严格数字 id（如 '{id}abc'）时校验失败，不做任何删除。
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

class AdminDataScopeBindingDeleteIdValidationClosureModuleTest extends TestCase
{
    use DatabaseTransactions;

    // 删除绑定接口应拒绝非严格 id 且不删除绑定记录。
    public function test_delete_admin_agent_binding_rejects_non_strict_id_without_deleting_binding(): void
    {
        $actor = $this->ensureSuperAdmin();
        $agentId = 982803;
        $this->ensureAgent($agentId);
        $bindingId = $this->createAdminAgentBinding((int) $actor->id, $agentId);

        $response = $this->withoutMiddleware([AdminAuthenticate::class, JwtAuthMiddleware::class, SingleSignOn::class, CheckPermission::class])
            ->actingAs($actor, 'admin')
            ->post('/api/admin/deleteAdminAgentBinding', [
                'id' => $bindingId . 'abc',
            ]);

        $response->assertOk()
            ->assertJsonPath('code', ResponseCode::VALIDATION_FAILED);

        $this->assertDatabaseHas('admin_agent_bindings', [
            'id' => $bindingId,
            'admin_id' => (int) $actor->id,
            'agent_id' => $agentId,
            'status' => 1,
            'deleted_at' => null,
        ]);
    }

    // 核对最终检查清单文档记录了数据范围绑定删除 id 校验边界。
    public function test_final_checklist_records_data_scope_binding_delete_id_validation_boundary(): void
    {
        $checklist = file_get_contents(base_path('docs/admin-backend-blade-permission-final-checklist.md')) ?: '';

        $this->assertStringContainsString('## 298.', $checklist);
        $this->assertStringContainsString('DataScopeController::deleteAdminAgentBinding', $checklist);
        $this->assertStringContainsString('/api/admin/deleteAdminAgentBinding', $checklist);
        $this->assertStringContainsString('admin_agent_bindings.id', $checklist);
        $this->assertStringContainsString('AdminDataScopeBindingDeleteIdValidationClosureModuleTest', $checklist);
    }

    private function ensureSuperAdmin(): Admin
    {
        $now = time();

        DB::table('admins')->updateOrInsert(
            ['id' => 1],
            [
                'username' => 'admin-data-scope-binding-delete-super',
                'email' => 'admin-data-scope-binding-delete-super@example.test',
                'password' => Hash::make('password'),
                'status' => 1,
                'created_at' => $now,
                'updated_at' => $now,
                'deleted_at' => null,
            ]
        );

        return Admin::query()->findOrFail(1);
    }

    private function ensureAgent(int $agentId): void
    {
        $now = time();

        DB::table('user_infos')->updateOrInsert(
            ['user_id' => $agentId],
            [
                'login_id' => 0,
                'user_name' => 'Data Scope Bound Agent',
                'phone' => '',
                'gender' => 1,
                'account_type' => 1,
                'parent_id' => 0,
                'family_tree' => (string) $agentId,
                'total_funds' => 0,
                'equity' => 0,
                'effective_credit' => 0,
                'created_at' => $now,
                'updated_at' => $now,
                'deleted_at' => null,
            ]
        );
    }

    private function createAdminAgentBinding(int $adminId, int $agentId): int
    {
        $now = time();

        return (int) DB::table('admin_agent_bindings')->insertGetId([
            'admin_id' => $adminId,
            'agent_id' => $agentId,
            'binding_type' => 'primary',
            'status' => 1,
            'created_at' => $now,
            'updated_at' => $now,
            'deleted_at' => null,
        ]);
    }
}
