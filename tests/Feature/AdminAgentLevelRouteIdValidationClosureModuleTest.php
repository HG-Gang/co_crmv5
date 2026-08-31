<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/02
 * Time: 01:45
 */

/**
 * 后台代理等级接口路由 id 严格校验闭包测试。
 *
 * 文件功能：
 * - 验证更新（updateAgentLevel2/{id}）、删除（deleteAgentLevel/{id}）代理等级接口对非严格整数路由 id（如 {id}abc）返回校验失败，且不修改/不删除等级。
 * - 验证 docs/admin-backend-blade-permission-final-checklist.md 最终清单记录了本边界（第 286 项）。
 *
 * 适用场景：
 * - 后台代理等级配置模块更新与删除入口的路由参数严格校验回归测试。
 *
 * 入参例子：
 * - POST /api/admin/updateAgentLevel2/{targetId}abc
 *   {
 *     "level_code": 928602,
 *     "name": "Route Id Level Updated",
 *     "max_commission": 90,
 *     "min_commission": 80,
 *     "user_commission": 70
 *   }
 *
 * 方法功能：
 * - test_update_agent_level_rejects_non_strict_route_id_without_changing_level：非严格路由 id 更新被拒，断言等级字段不变。
 * - test_delete_agent_level_rejects_non_strict_route_id_without_deleting_level：非严格路由 id 删除被拒，断言等级未被软删除。
 * - test_final_checklist_records_agent_level_route_id_validation_boundary：校验最终清单文档包含第 286 项边界记录。
 *
 * 返回值：
 * - 校验失败时接口返回 code=VALIDATION_FAILED；断言失败时抛出 PHPUnit 断言异常。
 *
 * 异常或失败场景：
 * - 若非严格路由 id 被接受并执行写操作，测试断言失败。
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

class AdminAgentLevelRouteIdValidationClosureModuleTest extends TestCase
{
    use DatabaseTransactions;

    public function test_update_agent_level_rejects_non_strict_route_id_without_changing_level(): void
    {
        $actor = $this->ensureSuperAdmin();
        $targetId = $this->createManagedAgentLevel(928601, 'Route Id Level Original');

        $response = $this->withoutMiddleware([AdminAuthenticate::class, JwtAuthMiddleware::class, SingleSignOn::class, CheckPermission::class])
            ->actingAs($actor, 'admin')
            ->post('/api/admin/updateAgentLevel2/' . $targetId . 'abc', [
                'level_code' => 928602,
                'name' => 'Route Id Level Updated',
                'max_commission' => 90,
                'min_commission' => 80,
                'user_commission' => 70,
            ]);

        $response->assertOk()
            ->assertJsonPath('code', ResponseCode::VALIDATION_FAILED);

        $level = DB::table('agent_levels')->where('id', $targetId)->first();

        $this->assertSame(928601, (int) $level->level_code);
        $this->assertSame('Route Id Level Original', (string) $level->name);
        $this->assertSame(50, (int) $level->max_commission);
        $this->assertSame(40, (int) $level->min_commission);
        $this->assertSame(30, (int) $level->user_commission);
    }

    public function test_delete_agent_level_rejects_non_strict_route_id_without_deleting_level(): void
    {
        $actor = $this->ensureSuperAdmin();
        $targetId = $this->createManagedAgentLevel(928603, 'Route Id Level Delete');

        $response = $this->withoutMiddleware([AdminAuthenticate::class, JwtAuthMiddleware::class, SingleSignOn::class, CheckPermission::class])
            ->actingAs($actor, 'admin')
            ->post('/api/admin/deleteAgentLevel/' . $targetId . 'abc');

        $response->assertOk()
            ->assertJsonPath('code', ResponseCode::VALIDATION_FAILED);

        $level = DB::table('agent_levels')->where('id', $targetId)->first();

        $this->assertNotNull($level);
        $this->assertNull($level->deleted_at);
        $this->assertSame('Route Id Level Delete', (string) $level->name);
    }

    public function test_final_checklist_records_agent_level_route_id_validation_boundary(): void
    {
        $checklist = file_get_contents(base_path('docs/admin-backend-blade-permission-final-checklist.md')) ?: '';

        $this->assertStringContainsString('## 286.', $checklist);
        $this->assertStringContainsString('AgentLevelController::update', $checklist);
        $this->assertStringContainsString('AgentLevelController::destroy', $checklist);
        $this->assertStringContainsString('/api/admin/updateAgentLevel2/{id}', $checklist);
        $this->assertStringContainsString('/api/admin/deleteAgentLevel/{id}', $checklist);
        $this->assertStringContainsString('agent_levels.id', $checklist);
        $this->assertStringContainsString('AdminAgentLevelRouteIdValidationClosureModuleTest', $checklist);
    }

    private function ensureSuperAdmin(): Admin
    {
        $now = time();

        DB::table('admins')->updateOrInsert(
            ['id' => 1],
            [
                'username' => 'admin-agent-level-route-id-super',
                'email' => 'admin-agent-level-route-id-super@example.test',
                'password' => Hash::make('password'),
                'status' => 1,
                'created_at' => $now,
                'updated_at' => $now,
                'deleted_at' => null,
            ]
        );

        return Admin::query()->findOrFail(1);
    }

    private function createManagedAgentLevel(int $levelCode, string $name): int
    {
        $now = time();

        DB::table('agent_levels')->where('level_code', $levelCode)->delete();

        return (int) DB::table('agent_levels')->insertGetId([
            'level_code' => $levelCode,
            'name' => $name,
            'max_commission' => 50,
            'min_commission' => 40,
            'user_commission' => 30,
            'created_at' => $now,
            'updated_at' => $now,
            'deleted_at' => null,
        ]);
    }
}
