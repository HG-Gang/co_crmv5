<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/02
 * Time: 01:45
 */

/**
 * 后台代理等级存在性校验闭包测试。
 *
 * 文件功能：
 * - 验证 POST /api/admin/updateAgentLevel 更新代理等级时，level 必须指向存在的 agent_levels 记录，不存在的等级返回校验失败且不修改 user_infos.level_id。
 * - 验证 docs/admin-backend-blade-permission-final-checklist.md 最终清单记录了本边界（第 271 项）。
 *
 * 适用场景：
 * - 后台代理管理模块更新代理等级入口的存在性校验回归测试。
 *
 * 入参例子：
 * - POST /api/admin/updateAgentLevel
 *   {
 *     "agent_id": 98727101,
 *     "level": 98727199
 *   }
 *
 * 方法功能：
 * - test_update_agent_level_rejects_missing_agent_level_without_writing_user_info：指向不存在的等级被拒，断言原等级保持不变。
 * - test_final_checklist_records_admin_agent_level_exists_validation_boundary：校验最终清单文档包含第 271 项边界记录。
 *
 * 返回值：
 * - 校验失败时接口返回 code=VALIDATION_FAILED；断言失败时抛出 PHPUnit 断言异常。
 *
 * 异常或失败场景：
 * - 若不存在的等级被接受并写入 user_infos，测试断言失败。
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

class AdminAgentLevelExistsValidationClosureModuleTest extends TestCase
{
    use DatabaseTransactions;

    public function test_update_agent_level_rejects_missing_agent_level_without_writing_user_info(): void
    {
        $admin = $this->ensureSuperAdmin();
        $agentUserId = 98727101;
        $originalLevelId = $this->ensureAgentLevel(927101, 'Original Agent Level');
        $missingLevelId = 98727199;
        $this->createAgent($agentUserId, $originalLevelId);

        DB::table('agent_levels')->where('id', $missingLevelId)->delete();

        $response = $this->withoutMiddleware([AdminAuthenticate::class, JwtAuthMiddleware::class, SingleSignOn::class, CheckPermission::class])
            ->actingAs($admin, 'admin')
            ->post('/api/admin/updateAgentLevel', [
                'agent_id' => $agentUserId,
                'level' => $missingLevelId,
            ]);

        $response->assertOk()
            ->assertJsonPath('code', ResponseCode::VALIDATION_FAILED);

        $this->assertSame($originalLevelId, (int) DB::table('user_infos')->where('user_id', $agentUserId)->value('level_id'));
    }

    public function test_final_checklist_records_admin_agent_level_exists_validation_boundary(): void
    {
        $checklist = file_get_contents(base_path('docs/admin-backend-blade-permission-final-checklist.md')) ?: '';

        $this->assertStringContainsString('## 271.', $checklist);
        $this->assertStringContainsString('AgentController::updateLevel', $checklist);
        $this->assertStringContainsString('/api/admin/updateAgentLevel', $checklist);
        $this->assertStringContainsString('agent_levels.id', $checklist);
        $this->assertStringContainsString('user_infos.level_id', $checklist);
        $this->assertStringContainsString('AdminAgentLevelExistsValidationClosureModuleTest', $checklist);
    }

    private function ensureSuperAdmin(): Admin
    {
        $now = time();

        DB::table('admins')->updateOrInsert(
            ['id' => 1],
            [
                'username' => 'admin-agent-level-exists-super',
                'email' => 'admin-agent-level-exists-super@example.test',
                'password' => bcrypt('password'),
                'status' => 1,
                'created_at' => $now,
                'updated_at' => $now,
                'deleted_at' => null,
            ]
        );

        return Admin::query()->findOrFail(1);
    }

    private function ensureAgentLevel(int $levelCode, string $name): int
    {
        $now = time();

        DB::table('agent_levels')->where('level_code', $levelCode)->delete();

        return (int) DB::table('agent_levels')->insertGetId([
            'level_code' => $levelCode,
            'name' => $name,
            'max_commission' => 85,
            'min_commission' => 50,
            'user_commission' => 10,
            'created_at' => $now,
            'updated_at' => $now,
            'deleted_at' => null,
        ]);
    }

    private function createAgent(int $userId, int $levelId): void
    {
        $now = time();

        DB::table('user_infos')->where('user_id', $userId)->delete();
        DB::table('user_logins')->where('user_id', $userId)->delete();

        $loginId = DB::table('user_logins')->insertGetId([
            'user_id' => $userId,
            'email' => 'admin-agent-level-exists-' . $userId . '@example.test',
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
            'user_name' => 'Admin Agent Level Exists',
            'phone' => '188271' . substr((string) $userId, -5),
            'account_type' => 1,
            'parent_id' => 0,
            'level_id' => $levelId,
            'comm_rate' => 10,
            'auth_status' => 1,
            'created_at' => $now,
            'updated_at' => $now,
            'deleted_at' => null,
        ]);
    }
}
