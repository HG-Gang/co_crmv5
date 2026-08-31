<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/02
 * Time: 01:45
 */

/**
 * 后台代理确认通过后清空拒绝备注闭包测试。
 *
 * 文件功能：
 * - 验证 POST /api/admin/confirmAgent 确认代理通过时，将 is_agent_confirmed 置 1 并清空之前拒绝时写入的 remark。
 * - 验证 docs/admin-backend-blade-permission-final-checklist.md 最终清单记录了本边界（第 274 项）。
 *
 * 适用场景：
 * - 后台代理管理模块代理确认入口的字段状态回归测试。
 *
 * 入参例子：
 * - POST /api/admin/confirmAgent
 *   {
 *     "agent_id": 98727401
 *   }
 *
 * 方法功能：
 * - test_confirm_agent_clears_previous_rejection_remark_when_approval_succeeds：预先构造带拒绝备注的代理，确认后断言确认标志为 1 且备注被清空。
 * - test_final_checklist_records_admin_agent_confirmation_approve_remark_boundary：校验最终清单文档包含第 274 项边界记录。
 *
 * 返回值：
 * - 确认成功返回 code=UPDATED；断言失败时抛出 PHPUnit 断言异常。
 *
 * 异常或失败场景：
 * - 若确认通过后未清空历史拒绝备注，测试断言失败。
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

class AdminAgentConfirmationApproveRemarkClosureModuleTest extends TestCase
{
    use DatabaseTransactions;

    /**
     * 预先构造带拒绝备注的代理，确认通过后断言确认标志为 1 且备注被清空。
     *
     * @return void
     */
    public function test_confirm_agent_clears_previous_rejection_remark_when_approval_succeeds(): void
    {
        $admin = $this->ensureSuperAdmin();
        $agentUserId = 98727401;
        $this->createRejectedAgent($agentUserId, 'Previous rejection reason');

        $response = $this->withoutMiddleware([AdminAuthenticate::class, JwtAuthMiddleware::class, SingleSignOn::class, CheckPermission::class])
            ->actingAs($admin, 'admin')
            ->post('/api/admin/confirmAgent', [
                'agent_id' => $agentUserId,
            ]);

        $response->assertOk()
            ->assertJsonPath('code', ResponseCode::UPDATED);

        $agentInfo = DB::table('user_infos')->where('user_id', $agentUserId)->first();

        $this->assertSame(1, (int) $agentInfo->is_agent_confirmed);
        $this->assertSame('', (string) $agentInfo->remark);
    }

    /**
     * 校验最终清单文档第 274 项记录了确认通过清空备注边界。
     *
     * @return void
     */
    public function test_final_checklist_records_admin_agent_confirmation_approve_remark_boundary(): void
    {
        $checklist = file_get_contents(base_path('docs/admin-backend-blade-permission-final-checklist.md')) ?: '';

        $this->assertStringContainsString('## 274.', $checklist);
        $this->assertStringContainsString('AgentController::confirmAgent', $checklist);
        $this->assertStringContainsString('/api/admin/confirmAgent', $checklist);
        $this->assertStringContainsString('is_agent_confirmed', $checklist);
        $this->assertStringContainsString('user_infos.remark', $checklist);
        $this->assertStringContainsString('AdminAgentConfirmationApproveRemarkClosureModuleTest', $checklist);
    }

    private function ensureSuperAdmin(): Admin
    {
        $now = time();

        DB::table('admins')->updateOrInsert(
            ['id' => 1],
            [
                'username' => 'admin-agent-confirm-remark-super',
                'email' => 'admin-agent-confirm-remark-super@example.test',
                'password' => bcrypt('password'),
                'status' => 1,
                'created_at' => $now,
                'updated_at' => $now,
                'deleted_at' => null,
            ]
        );

        return Admin::query()->findOrFail(1);
    }

    private function createRejectedAgent(int $userId, string $remark): void
    {
        $now = time();

        DB::table('user_infos')->where('user_id', $userId)->delete();
        DB::table('user_logins')->where('user_id', $userId)->delete();

        $loginId = DB::table('user_logins')->insertGetId([
            'user_id' => $userId,
            'email' => 'admin-agent-confirm-remark-' . $userId . '@example.test',
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
            'user_name' => 'Admin Agent Confirm Remark',
            'phone' => '188274' . substr((string) $userId, -5),
            'account_type' => 1,
            'parent_id' => 0,
            'level_id' => 1,
            'comm_rate' => 1,
            'auth_status' => 1,
            'is_agent_confirmed' => 0,
            'remark' => $remark,
            'created_at' => $now,
            'updated_at' => $now,
            'deleted_at' => null,
        ]);
    }
}
