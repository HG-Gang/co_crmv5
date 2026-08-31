<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/02
 * Time: 01:45
 */

/**
 * 文件功能：验证管理员-代理绑定列表接口（adminAgentBindingList）对 admin_id
 *           筛选值的严格校验，并核对最终检查清单文档。
 *
 * 适用场景：后台 /api/admin/adminAgentBindingList 接口的筛选参数校验回归测试。
 *
 * 入参例子：
 * - POST /api/admin/adminAgentBindingList：{admin_id}
 *
 * 返回值：
 * - admin_id 带非数字后缀时返回 code=VALIDATION_FAILED。
 *
 * 异常或失败场景：
 * - 非严格数字 admin_id（如 '{id}abc'）时校验失败，不返回绑定数据。
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

class AdminDataScopeBindingListAdminIdValidationClosureModuleTest extends TestCase
{
    use DatabaseTransactions;

    // 绑定列表接口应拒绝非严格 admin_id 筛选值。
    public function test_admin_agent_binding_list_rejects_non_strict_admin_id_filter(): void
    {
        $actor = $this->ensureSuperAdmin();
        $agentId = 982804;
        $this->ensureAgent($agentId);
        $this->createAdminAgentBinding((int) $actor->id, $agentId);

        $response = $this->withoutMiddleware([AdminAuthenticate::class, JwtAuthMiddleware::class, SingleSignOn::class, CheckPermission::class])
            ->actingAs($actor, 'admin')
            ->post('/api/admin/adminAgentBindingList', [
                'admin_id' => $actor->id . 'abc',
            ]);

        $response->assertOk()
            ->assertJsonPath('code', ResponseCode::VALIDATION_FAILED);
    }

    // 核对最终检查清单文档记录了绑定列表 admin_id 校验边界。
    public function test_final_checklist_records_data_scope_binding_list_admin_id_validation_boundary(): void
    {
        $checklist = file_get_contents(base_path('docs/admin-backend-blade-permission-final-checklist.md')) ?: '';

        $this->assertStringContainsString('## 299.', $checklist);
        $this->assertStringContainsString('DataScopeController::adminAgentBindingList', $checklist);
        $this->assertStringContainsString('/api/admin/adminAgentBindingList', $checklist);
        $this->assertStringContainsString('admin_agent_bindings.admin_id', $checklist);
        $this->assertStringContainsString('AdminDataScopeBindingListAdminIdValidationClosureModuleTest', $checklist);
    }

    private function ensureSuperAdmin(): Admin
    {
        $now = time();

        DB::table('admins')->updateOrInsert(
            ['id' => 1],
            [
                'username' => 'admin-data-scope-binding-list-super',
                'email' => 'admin-data-scope-binding-list-super@example.test',
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
                'user_name' => 'Data Scope Listed Agent',
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

    private function createAdminAgentBinding(int $adminId, int $agentId): void
    {
        $now = time();

        DB::table('admin_agent_bindings')->insert([
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
