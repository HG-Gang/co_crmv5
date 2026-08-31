<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/02
 * Time: 01:46
 */

/**
 * 后台代理拒绝原因去空格校验闭包测试。
 *
 * 文件功能：
 * - 验证 POST /api/admin/rejectAgentConfirmation 对去空格后为空的 reason（如全空格）返回校验失败，且不修改确认状态、不写备注、不写审计日志。
 * - 验证 docs/admin-backend-blade-permission-final-checklist.md 最终清单记录了本边界（第 275 项）。
 *
 * 适用场景：
 * - 后台代理管理模块拒绝代理确认入口的原因参数校验回归测试。
 *
 * 入参例子：
 * - POST /api/admin/rejectAgentConfirmation
 *   {
 *     "agent_id": 98727501,
 *     "reason": "   "
 *   }
 *
 * 方法功能：
 * - test_reject_agent_confirmation_rejects_blank_reason_after_trim_without_writing_state：空白原因被拒，断言确认标志、备注与审计日志均未变化。
 * - test_final_checklist_records_admin_agent_reject_reason_trim_validation_boundary：校验最终清单文档包含第 275 项边界记录。
 *
 * 返回值：
 * - 校验失败时接口返回 code=VALIDATION_FAILED；断言失败时抛出 PHPUnit 断言异常。
 *
 * 异常或失败场景：
 * - 若空白原因被接受并修改代理状态或写入日志，测试断言失败。
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

class AdminAgentRejectReasonTrimValidationClosureModuleTest extends TestCase
{
    use DatabaseTransactions;

    /**
     * 空白拒绝原因：断言校验失败且确认标志、备注与审计日志均未变化。
     *
     * @return void
     */
    public function test_reject_agent_confirmation_rejects_blank_reason_after_trim_without_writing_state(): void
    {
        $admin = $this->ensureSuperAdmin();
        $agentUserId = 98727501;
        $originalRemark = 'Existing review remark';
        $this->createConfirmedAgent($agentUserId, $originalRemark);
        DB::table('operation_logs')->where('order_no', 'agent_confirmation:' . $agentUserId)->delete();

        $response = $this->withoutMiddleware([AdminAuthenticate::class, JwtAuthMiddleware::class, SingleSignOn::class, CheckPermission::class])
            ->actingAs($admin, 'admin')
            ->post('/api/admin/rejectAgentConfirmation', [
                'agent_id' => $agentUserId,
                'reason' => '   ',
            ]);

        $response->assertOk()
            ->assertJsonPath('code', ResponseCode::VALIDATION_FAILED);

        $agentInfo = DB::table('user_infos')->where('user_id', $agentUserId)->first();
        $this->assertSame(1, (int) $agentInfo->is_agent_confirmed);
        $this->assertSame($originalRemark, (string) $agentInfo->remark);
        $this->assertSame(0, DB::table('operation_logs')->where('order_no', 'agent_confirmation:' . $agentUserId)->count());
    }

    /**
     * 校验最终清单文档第 275 项记录了拒绝原因去空格校验边界。
     *
     * @return void
     */
    public function test_final_checklist_records_admin_agent_reject_reason_trim_validation_boundary(): void
    {
        $checklist = file_get_contents(base_path('docs/admin-backend-blade-permission-final-checklist.md')) ?: '';

        $this->assertStringContainsString('## 275.', $checklist);
        $this->assertStringContainsString('AgentController::rejectAgentConfirmation', $checklist);
        $this->assertStringContainsString('/api/admin/rejectAgentConfirmation', $checklist);
        $this->assertStringContainsString('reason', $checklist);
        $this->assertStringContainsString('user_infos.remark', $checklist);
        $this->assertStringContainsString('operation_logs', $checklist);
        $this->assertStringContainsString('AdminAgentRejectReasonTrimValidationClosureModuleTest', $checklist);
    }

    private function ensureSuperAdmin(): Admin
    {
        $now = time();

        DB::table('admins')->updateOrInsert(
            ['id' => 1],
            [
                'username' => 'admin-agent-reject-reason-super',
                'email' => 'admin-agent-reject-reason-super@example.test',
                'password' => bcrypt('password'),
                'status' => 1,
                'created_at' => $now,
                'updated_at' => $now,
                'deleted_at' => null,
            ]
        );

        return Admin::query()->findOrFail(1);
    }

    private function createConfirmedAgent(int $userId, string $remark): void
    {
        $now = time();

        DB::table('user_infos')->where('user_id', $userId)->delete();
        DB::table('user_logins')->where('user_id', $userId)->delete();

        $loginId = DB::table('user_logins')->insertGetId([
            'user_id' => $userId,
            'email' => 'admin-agent-reject-reason-' . $userId . '@example.test',
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
            'user_name' => 'Admin Agent Reject Reason',
            'phone' => '188275' . substr((string) $userId, -5),
            'account_type' => 1,
            'parent_id' => 0,
            'level_id' => 1,
            'comm_rate' => 1,
            'auth_status' => 1,
            'is_agent_confirmed' => 1,
            'remark' => $remark,
            'created_at' => $now,
            'updated_at' => $now,
            'deleted_at' => null,
        ]);
    }
}
