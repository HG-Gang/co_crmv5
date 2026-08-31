<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/02
 * Time: 01:45
 */

/**
 * 后台代理等级佣金字段严格校验闭包测试。
 *
 * 文件功能：
 * - 验证创建代理等级时 max_commission 等佣金字段必须为严格数值，非法值返回校验失败且不写入等级。
 * - 验证更新代理等级（updateAgentLevel2）时旧版 commission_rate 字段同样严格校验，非法值不修改等级。
 * - 验证 docs/admin-backend-blade-permission-final-checklist.md 最终清单记录了本边界（第 287 项）。
 *
 * 适用场景：
 * - 后台代理等级配置模块创建与更新入口的佣金字段校验回归测试。
 *
 * 入参例子：
 * - POST /api/admin/createAgentLevel
 *   {
 *     "level_code": 928701,
 *     "name": "Invalid Commission Level",
 *     "max_commission": "50abc",
 *     "min_commission": 40,
 *     "user_commission": 30
 *   }
 *
 * 方法功能：
 * - test_create_agent_level_rejects_non_strict_commission_value_without_writing_level：创建时非严格佣金值被拒，断言等级未写入。
 * - test_update_agent_level_rejects_non_strict_legacy_commission_rate_without_changing_level：更新时旧版佣金率非法被拒，断言等级字段不变。
 * - test_final_checklist_records_agent_level_commission_validation_boundary：校验最终清单文档包含第 287 项边界记录。
 *
 * 返回值：
 * - 校验失败时接口返回 code=VALIDATION_FAILED；断言失败时抛出 PHPUnit 断言异常。
 *
 * 异常或失败场景：
 * - 若非法佣金值被接受并写入等级，测试断言失败。
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

class AdminAgentLevelCommissionValidationClosureModuleTest extends TestCase
{
    use DatabaseTransactions;

    /**
     * 创建代理等级时传非严格佣金值：断言校验失败且等级未写入。
     *
     * @return void
     */
    public function test_create_agent_level_rejects_non_strict_commission_value_without_writing_level(): void
    {
        $actor = $this->ensureSuperAdmin();

        DB::table('agent_levels')->where('level_code', 928701)->delete();

        $response = $this->withoutMiddleware([AdminAuthenticate::class, JwtAuthMiddleware::class, SingleSignOn::class, CheckPermission::class])
            ->actingAs($actor, 'admin')
            ->post('/api/admin/createAgentLevel', [
                'level_code' => 928701,
                'name' => 'Invalid Commission Level',
                'max_commission' => '50abc',
                'min_commission' => 40,
                'user_commission' => 30,
            ]);

        $response->assertOk()
            ->assertJsonPath('code', ResponseCode::VALIDATION_FAILED);

        $this->assertFalse(DB::table('agent_levels')->where('level_code', 928701)->exists());
    }

    /**
     * 更新代理等级时传非严格旧版佣金率：断言校验失败且等级字段不变。
     *
     * @return void
     */
    public function test_update_agent_level_rejects_non_strict_legacy_commission_rate_without_changing_level(): void
    {
        $actor = $this->ensureSuperAdmin();
        $targetId = $this->createManagedAgentLevel(928702, 'Legacy Commission Level');

        $response = $this->withoutMiddleware([AdminAuthenticate::class, JwtAuthMiddleware::class, SingleSignOn::class, CheckPermission::class])
            ->actingAs($actor, 'admin')
            ->post('/api/admin/updateAgentLevel2/' . $targetId, [
                'level_code' => 928702,
                'name' => 'Legacy Commission Level Updated',
                'max_commission' => 55,
                'min_commission' => 45,
                'commission_rate' => '30abc',
            ]);

        $response->assertOk()
            ->assertJsonPath('code', ResponseCode::VALIDATION_FAILED);

        $level = DB::table('agent_levels')->where('id', $targetId)->first();

        $this->assertSame('Legacy Commission Level', (string) $level->name);
        $this->assertSame(50, (int) $level->max_commission);
        $this->assertSame(40, (int) $level->min_commission);
        $this->assertSame(30, (int) $level->user_commission);
    }

    /**
     * 校验最终清单文档第 287 项记录了代理等级佣金校验边界。
     *
     * @return void
     */
    public function test_final_checklist_records_agent_level_commission_validation_boundary(): void
    {
        $checklist = file_get_contents(base_path('docs/admin-backend-blade-permission-final-checklist.md')) ?: '';

        $this->assertStringContainsString('## 287.', $checklist);
        $this->assertStringContainsString('AgentLevelController::normalizePayload', $checklist);
        $this->assertStringContainsString('/api/admin/createAgentLevel', $checklist);
        $this->assertStringContainsString('/api/admin/updateAgentLevel2/{id}', $checklist);
        $this->assertStringContainsString('max_commission', $checklist);
        $this->assertStringContainsString('commission_rate', $checklist);
        $this->assertStringContainsString('agent_levels.user_commission', $checklist);
        $this->assertStringContainsString('AdminAgentLevelCommissionValidationClosureModuleTest', $checklist);
    }

    private function ensureSuperAdmin(): Admin
    {
        $now = time();

        DB::table('admins')->updateOrInsert(
            ['id' => 1],
            [
                'username' => 'admin-agent-level-commission-super',
                'email' => 'admin-agent-level-commission-super@example.test',
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
