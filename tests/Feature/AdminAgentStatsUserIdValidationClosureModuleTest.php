<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/02
 * Time: 01:46
 */

/**
 * 后台代理统计接口 user_id 严格校验闭包测试。
 *
 * 文件功能：
 * - 验证 POST /api/admin/agentStatsList 对非严格整数 user_id（如 {id}abc）返回校验失败，且不返回代理统计数据。
 * - 验证 docs/admin-backend-blade-permission-final-checklist.md 最终清单记录了本边界（第 278 项）。
 *
 * 适用场景：
 * - 后台代理管理模块代理统计入口的参数严格校验回归测试。
 *
 * 入参例子：
 * - POST /api/admin/agentStatsList
 *   {
 *     "form": 1,
 *     "user_id": "98727801abc",
 *     "per_page": 5
 *   }
 *
 * 方法功能：
 * - test_agent_stats_rejects_non_strict_user_id_without_returning_agent_row：非严格 user_id 被拒，断言响应不含代理姓名。
 * - test_final_checklist_records_admin_agent_stats_user_id_validation_boundary：校验最终清单文档包含第 278 项边界记录。
 *
 * 返回值：
 * - 校验失败时接口返回 code=VALIDATION_FAILED；断言失败时抛出 PHPUnit 断言异常。
 *
 * 异常或失败场景：
 * - 若非严格 user_id 被接受并返回统计数据，测试断言失败。
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

class AdminAgentStatsUserIdValidationClosureModuleTest extends TestCase
{
    use DatabaseTransactions;

    /**
     * 非严格 user_id 查询统计：断言校验失败且响应不含代理姓名。
     *
     * @return void
     */
    public function test_agent_stats_rejects_non_strict_user_id_without_returning_agent_row(): void
    {
        $admin = $this->ensureSuperAdmin();
        $agentUserId = 98727801;
        $agentName = 'Admin Agent Stats Strict User Id';

        $this->createAgent($agentUserId, $agentName);

        $response = $this->withoutMiddleware([AdminAuthenticate::class, JwtAuthMiddleware::class, SingleSignOn::class, CheckPermission::class])
            ->actingAs($admin, 'admin')
            ->post('/api/admin/agentStatsList', [
                'form' => 1,
                'user_id' => $agentUserId . 'abc',
                'per_page' => 5,
            ]);

        $response->assertOk()
            ->assertJsonPath('code', ResponseCode::VALIDATION_FAILED);

        $this->assertStringNotContainsString($agentName, $response->getContent());
    }

    /**
     * 校验最终清单文档第 278 项记录了统计接口 user_id 校验边界。
     *
     * @return void
     */
    public function test_final_checklist_records_admin_agent_stats_user_id_validation_boundary(): void
    {
        $checklist = file_get_contents(base_path('docs/admin-backend-blade-permission-final-checklist.md')) ?: '';

        $this->assertStringContainsString('## 278.', $checklist);
        $this->assertStringContainsString('AgentController::listWithStats', $checklist);
        $this->assertStringContainsString('/api/admin/agentStatsList', $checklist);
        $this->assertStringContainsString('user_id', $checklist);
        $this->assertStringContainsString('user_infos.user_id', $checklist);
        $this->assertStringContainsString('AdminAgentStatsUserIdValidationClosureModuleTest', $checklist);
    }

    private function ensureSuperAdmin(): Admin
    {
        $now = time();

        DB::table('admins')->updateOrInsert(
            ['id' => 1],
            [
                'username' => 'admin-agent-stats-user-id-super',
                'email' => 'admin-agent-stats-user-id-super@example.test',
                'password' => bcrypt('password'),
                'status' => 1,
                'created_at' => $now,
                'updated_at' => $now,
                'deleted_at' => null,
            ]
        );

        return Admin::query()->findOrFail(1);
    }

    private function createAgent(int $userId, string $userName): void
    {
        $now = time();

        DB::table('user_trades')->where('user_id', $userId)->delete();
        DB::table('user_infos')->where('user_id', $userId)->delete();
        DB::table('user_logins')->where('user_id', $userId)->delete();

        $loginId = DB::table('user_logins')->insertGetId([
            'user_id' => $userId,
            'email' => 'admin-agent-stats-user-id-' . $userId . '@example.test',
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
            'user_name' => $userName,
            'phone' => '188278' . substr((string) $userId, -5),
            'account_type' => 1,
            'parent_id' => 0,
            'level_id' => 1,
            'comm_rate' => 1,
            'auth_status' => 1,
            'total_funds' => 278.01,
            'equity' => 278.02,
            'created_at' => $now,
            'updated_at' => $now,
            'deleted_at' => null,
        ]);
    }
}
