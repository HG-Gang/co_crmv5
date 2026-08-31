<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/02
 * Time: 01:44
 */

/**
 * 后台代理佣金接口 agent_id 严格校验闭包测试。
 *
 * 文件功能：
 * - 验证 POST /api/admin/updateAgentCommission 对非严格整数 agent_id（如 {id}abc）返回校验失败，且不修改 user_infos.comm_rate。
 * - 验证 docs/admin-backend-blade-permission-final-checklist.md 最终清单记录了本边界（第 272 项）。
 *
 * 适用场景：
 * - 后台代理管理模块更新佣金入口的参数严格校验回归测试。
 *
 * 入参例子：
 * - POST /api/admin/updateAgentCommission
 *   {
 *     "agent_id": "98727201abc",
 *     "comm_rate": 0.0
 *   }
 *
 * 方法功能：
 * - test_update_agent_commission_rejects_non_strict_agent_id_without_writing_user_info：非严格 agent_id 被拒，断言原佣金比例保持不变。
 * - test_final_checklist_records_admin_agent_commission_agent_id_validation_boundary：校验最终清单文档包含第 272 项边界记录。
 *
 * 返回值：
 * - 校验失败时接口返回 code=VALIDATION_FAILED；断言失败时抛出 PHPUnit 断言异常。
 *
 * 异常或失败场景：
 * - 若非严格 agent_id 被接受并写入佣金，测试断言失败。
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
use Tests\TestCase;

class AdminAgentCommissionAgentIdValidationClosureModuleTest extends TestCase
{
    use DatabaseTransactions;

    /**
     * 非严格 agent_id 更新佣金：断言校验失败且原佣金比例保持不变。
     *
     * @return void
     */
    public function test_update_agent_commission_rejects_non_strict_agent_id_without_writing_user_info(): void
    {
        $admin = $this->ensureSuperAdmin();
        $agentUserId = 98727201;
        $originalCommissionRate = 1.0;
        $this->createAgent($agentUserId, $originalCommissionRate);

        $response = $this->withoutMiddleware([AdminAuthenticate::class, JwtAuthMiddleware::class, SingleSignOn::class, CheckPermission::class])
            ->actingAs($admin, 'admin')
            ->post('/api/admin/updateAgentCommission', [
                'agent_id' => $agentUserId . 'abc',
                'comm_rate' => 0.0,
            ]);

        $response->assertOk()
            ->assertJsonPath('code', ResponseCode::VALIDATION_FAILED);

        $storedCommissionRate = (float) DB::table('user_infos')->where('user_id', $agentUserId)->value('comm_rate');
        $this->assertEqualsWithDelta($originalCommissionRate, $storedCommissionRate, 0.0001);
    }

    /**
     * 校验最终清单文档第 272 项记录了佣金接口 agent_id 校验边界。
     *
     * @return void
     */
    public function test_final_checklist_records_admin_agent_commission_agent_id_validation_boundary(): void
    {
        $checklist = file_get_contents(base_path('docs/admin-backend-blade-permission-final-checklist.md')) ?: '';

        $this->assertStringContainsString('## 272.', $checklist);
        $this->assertStringContainsString('AgentController::updateCommission', $checklist);
        $this->assertStringContainsString('/api/admin/updateAgentCommission', $checklist);
        $this->assertStringContainsString('agent_id', $checklist);
        $this->assertStringContainsString('user_infos.user_id', $checklist);
        $this->assertStringContainsString('user_infos.comm_rate', $checklist);
        $this->assertStringContainsString('AdminAgentCommissionAgentIdValidationClosureModuleTest', $checklist);
    }

    private function ensureSuperAdmin(): Admin
    {
        $now = time();

        DB::table('admins')->updateOrInsert(
            ['id' => 1],
            [
                'username' => 'admin-agent-commission-agent-id-super',
                'email' => 'admin-agent-commission-agent-id-super@example.test',
                'password' => bcrypt('password'),
                'status' => 1,
                'created_at' => $now,
                'updated_at' => $now,
                'deleted_at' => null,
            ]
        );

        return Admin::query()->findOrFail(1);
    }

    private function createAgent(int $userId, float $commissionRate): void
    {
        $now = time();

        DB::table('user_infos')->where('user_id', $userId)->delete();
        DB::table('user_logins')->where('user_id', $userId)->delete();

        $loginId = DB::table('user_logins')->insertGetId([
            'user_id' => $userId,
            'email' => 'admin-agent-commission-agent-id-' . $userId . '@example.test',
            'password' => bcrypt('password'),
            'account_type' => 1,
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
            'user_name' => 'Admin Agent Commission Agent Id',
            'phone' => '188272' . substr((string) $userId, -5),
            'account_type' => 1,
            'parent_id' => 0,
            'level_id' => 1,
            'comm_rate' => $commissionRate,
            'auth_status' => 1,
            'created_at' => $now,
            'updated_at' => $now,
            'deleted_at' => null,
        ]);
    }
}
