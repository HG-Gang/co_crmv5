<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/02
 * Time: 01:45
 */

/**
 * 后台代理后代接口 agent_id 严格校验闭包测试。
 *
 * 文件功能：
 * - 验证 POST /api/admin/agentDescendants 对非严格整数 agent_id（如 {id}abc）返回校验失败，且不返回代理树数据。
 * - 验证 docs/admin-backend-blade-permission-final-checklist.md 最终清单记录了本边界（第 277 项）。
 *
 * 适用场景：
 * - 后台代理管理模块后代列表入口的参数严格校验回归测试。
 *
 * 入参例子：
 * - POST /api/admin/agentDescendants
 *   {
 *     "agent_id": "98727701abc"
 *   }
 *
 * 方法功能：
 * - test_agent_descendants_rejects_non_strict_agent_id_without_returning_tree_rows：非严格 agent_id 被拒，断言响应不含子代理信息。
 * - test_final_checklist_records_admin_agent_descendants_agent_id_validation_boundary：校验最终清单文档包含第 277 项边界记录。
 *
 * 返回值：
 * - 校验失败时接口返回 code=VALIDATION_FAILED；断言失败时抛出 PHPUnit 断言异常。
 *
 * 异常或失败场景：
 * - 若非严格 agent_id 被接受并返回树数据，测试断言失败。
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

class AdminAgentDescendantsAgentIdValidationClosureModuleTest extends TestCase
{
    use DatabaseTransactions;

    /**
     * 非严格 agent_id 查询后代：断言校验失败且响应不含子代理信息。
     *
     * @return void
     */
    public function test_agent_descendants_rejects_non_strict_agent_id_without_returning_tree_rows(): void
    {
        $admin = $this->ensureSuperAdmin();
        $rootAgentId = 98727701;
        $childAgentId = 98727702;
        $childName = 'Admin Agent Descendant Child';

        $this->deleteAgentDescendantRows([$rootAgentId, $childAgentId]);
        $this->createUserInfo($rootAgentId, 'Admin Agent Descendant Root', 1, 0);
        $this->createUserInfo($childAgentId, $childName, 1, $rootAgentId);

        $response = $this->withoutMiddleware([AdminAuthenticate::class, JwtAuthMiddleware::class, SingleSignOn::class, CheckPermission::class])
            ->actingAs($admin, 'admin')
            ->post('/api/admin/agentDescendants', [
                'agent_id' => $rootAgentId . 'abc',
            ]);

        $response->assertOk()
            ->assertJsonPath('code', ResponseCode::VALIDATION_FAILED);

        $this->assertStringNotContainsString($childName, $response->getContent());
    }

    /**
     * 校验最终清单文档第 277 项记录了后代接口 agent_id 校验边界。
     *
     * @return void
     */
    public function test_final_checklist_records_admin_agent_descendants_agent_id_validation_boundary(): void
    {
        $checklist = file_get_contents(base_path('docs/admin-backend-blade-permission-final-checklist.md')) ?: '';

        $this->assertStringContainsString('## 277.', $checklist);
        $this->assertStringContainsString('AgentController::descendants', $checklist);
        $this->assertStringContainsString('/api/admin/agentDescendants', $checklist);
        $this->assertStringContainsString('agent_id', $checklist);
        $this->assertStringContainsString('user_infos.parent_id', $checklist);
        $this->assertStringContainsString('AdminAgentDescendantsAgentIdValidationClosureModuleTest', $checklist);
    }

    private function ensureSuperAdmin(): Admin
    {
        $now = time();

        DB::table('admins')->updateOrInsert(
            ['id' => 1],
            [
                'username' => 'admin-agent-desc-agent-id-super',
                'email' => 'admin-agent-desc-agent-id-super@example.test',
                'password' => bcrypt('password'),
                'status' => 1,
                'created_at' => $now,
                'updated_at' => $now,
                'deleted_at' => null,
            ]
        );

        return Admin::query()->findOrFail(1);
    }

    private function createUserInfo(int $userId, string $userName, int $accountType, int $parentId): void
    {
        $now = time();

        DB::table('user_infos')->where('user_id', $userId)->delete();
        DB::table('user_logins')->where('user_id', $userId)->delete();

        $loginId = DB::table('user_logins')->insertGetId([
            'user_id' => $userId,
            'email' => 'admin-agent-desc-agent-id-' . $userId . '@example.test',
            'password' => bcrypt('password'),
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
            'account_type' => $accountType,
            'parent_id' => $parentId,
            'level_id' => $accountType === 1 ? 1 : 0,
            'comm_rate' => $accountType === 1 ? 1 : 0,
            'auth_status' => 1,
            'created_at' => $now,
            'updated_at' => $now,
            'deleted_at' => null,
        ]);
    }

    /**
     * @param array<int, int> $userIds
     */
    private function deleteAgentDescendantRows(array $userIds): void
    {
        DB::table('agent_descendants')
            ->whereIn('agent_id', $userIds)
            ->orWhereIn('descendant_id', $userIds)
            ->delete();
    }
}
