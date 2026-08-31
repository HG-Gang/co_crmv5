<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/16
 * Time: 23:33
 */

/**
 * 后台代理等级更新字段模块测试。
 *
 * 文件功能：
 * - 验证 POST /api/admin/updateAgentLevel 更新代理等级时写入真实的 user_infos.level_id 字段。
 * - 验证控制器源码写入 level_id 而非旧版 agent_level 字段。
 * - 验证前端配置（blade、pages.js、CrmUi）均使用真实 level_id 字段。
 *
 * 适用场景：
 * - 后台代理管理模块更新代理等级功能的字段落库与前后端一致性回归测试。
 *
 * 入参例子：
 * - POST /api/admin/updateAgentLevel
 *   {
 *     "agent_id": 985501,
 *     "level": 4
 *   }
 *
 * 方法功能：
 * - test_update_agent_level_writes_real_level_id_field：更新等级并断言 user_infos.level_id 落库为 4。
 * - test_agent_level_update_source_targets_level_id_not_legacy_agent_level：检查控制器源码包含 level_id 更新且不含旧版 agent_level 更新。
 * - test_agent_level_frontend_configs_use_real_level_id_field：检查 blade、pages.js、CrmUi 使用 level_id 字段。
 *
 * 返回值：
 * - 更新成功接口返回 code=SUCCESS；断言失败时抛出 PHPUnit 断言异常。
 *
 * 异常或失败场景：
 * - 若后端仍写旧版 agent_level 字段或前端未映射 level_id，测试断言失败。
 */

namespace Tests\Feature;

use App\Http\Middleware\AdminAuthenticate;
use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\JwtAuthMiddleware;
use App\Http\Middleware\SingleSignOn;
use App\Models\Admin;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AdminAgentLevelUpdateFieldModuleTest extends TestCase
{
    use DatabaseTransactions;

    public function test_update_agent_level_writes_real_level_id_field(): void
    {
        $admin = $this->ensureSuperAdmin();
        $now = time();
        $agentUserId = 985501;
        $levelId = $this->ensureAgentLevel(4);

        $this->upsertAgentFixture($agentUserId, 1, $now);

        $response = $this->withoutMiddleware([AdminAuthenticate::class, JwtAuthMiddleware::class, SingleSignOn::class, CheckPermission::class])
            ->actingAs($admin, 'admin')
            ->post('/api/admin/updateAgentLevel', [
                'agent_id' => $agentUserId,
                'level' => $levelId,
            ]);

        $response->assertOk();

        $this->assertDatabaseHas('user_infos', [
            'user_id' => $agentUserId,
            'account_type' => 1,
            'level_id' => $levelId,
        ]);
    }

    public function test_agent_level_update_source_targets_level_id_not_legacy_agent_level(): void
    {
        $source = file_get_contents(app_path('Http/Controllers/Admin/AgentController.php')) ?: '';

        $this->assertStringContainsString("\$agent->update(['level_id' => \$request->level]);", $source);
        $this->assertStringNotContainsString("\$agent->update(['agent_level' => \$request->level]);", $source);
    }

    public function test_agent_level_frontend_configs_use_real_level_id_field(): void
    {
        $blade = file_get_contents(resource_path('admin/layui/agents/index.blade.php')) ?: '';
        $layui = file_get_contents(public_path('js/apps/admin/layui/pages.js')) ?: '';
        $crmui = file_get_contents(app_path('Http/Controllers/CrmUi/Admin/PageController.php')) ?: '';

        $this->assertStringContainsString('level 写入 user_infos.level_id', $blade);
        $this->assertStringContainsString("{field: 'level_id', title: CrmLang.t('admin.agentLevel')", $layui);
        $this->assertStringContainsString("level: row.level_id || row.agent_level || ''", $layui);
        $this->assertStringContainsString("'columns' => ['user_id', 'user_name', 'level_id', 'comm_rate', 'auth_status']", $crmui);
    }

    private function ensureSuperAdmin(): Admin
    {
        $now = time();

        DB::table('admins')->updateOrInsert(
            ['id' => 1],
            [
                'username' => 'agent-level-admin',
                'email' => 'agent-level-admin@example.test',
                'password' => bcrypt('password'),
                'status' => 1,
                'created_at' => $now,
                'updated_at' => $now,
                'deleted_at' => null,
            ]
        );

        return Admin::query()->findOrFail(1);
    }

    private function upsertAgentFixture(int $userId, int $levelId, int $now): void
    {
        DB::table('user_infos')->where('user_id', $userId)->delete();
        DB::table('user_logins')->where('user_id', $userId)->delete();

        $loginId = DB::table('user_logins')->insertGetId([
            'user_id' => $userId,
            'email' => 'agent-level-' . $userId . '@example.test',
            'password' => bcrypt('password'),
            'account_type' => 1,
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
            'user_name' => 'Level Update Agent',
            'phone' => '17600005501',
            'account_type' => 1,
            'parent_id' => 0,
            'level_id' => $levelId,
            'comm_rate' => 0.2,
            'auth_status' => 1,
            'created_at' => $now,
            'updated_at' => $now,
            'deleted_at' => null,
        ]);
    }

    private function ensureAgentLevel(int $levelCode): int
    {
        $now = time();
        $levelId = DB::table('agent_levels')->where('level_code', $levelCode)->value('id');

        if ($levelId) {
            return (int) $levelId;
        }

        return (int) DB::table('agent_levels')->insertGetId([
            'level_code' => $levelCode,
            'name' => 'Level ' . $levelCode,
            'max_commission' => 85,
            'min_commission' => 50,
            'user_commission' => 10,
            'created_at' => $now,
            'updated_at' => $now,
            'deleted_at' => null,
        ]);
    }
}
