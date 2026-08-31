<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/12
 * Time: 11:49
 */

/**
 * AdminLegacyAgentEditInfoClosureModuleTest
 *
 * 文件功能：
 * - 验证旧后台代理编辑入口 agents/agents_edit_info/{uid} 闭环：落页到现代代理列表外壳并以 data-legacy-uid 透传，未登录请求被拒绝。
 * - 输入：HTTP 请求与事务回滚的数据库夹具；输出：PHPUnit 断言结果。
 * - 明确不负责：不覆盖真实 MT4 网关与线上支付/出金通道（由网关契约测试锁定）。
 */

namespace Tests\Feature;

use App\Models\Admin;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * 后台遗留"代理编辑页"入口 agents/agents_edit_info/{uid} 闭环测试。
 *
 * 文件目的：
 * - 锁定旧后台 AgentControllerV3@agents_edit_info 的迁移行为：旧 GET 页面导航
 *   落页到现代代理列表 admin_layui::agents.index（data-layui-page=agents/index），
 *   且 {uid} 路由参数以 data-legacy-uid 透传给页面，供前端定位目标代理行。
 * - 编辑提交本身由 agents_edit_save -> admin_api_updateAgentLevel 承接（另有测试）。
 */
class AdminLegacyAgentEditInfoClosureModuleTest extends TestCase
{
    use DatabaseTransactions;

    public function test_legacy_agents_edit_info_renders_agents_shell_with_uid(): void
    {
        $admin = Admin::query()->findOrFail(1);
        $agentId = $this->unusedUserId();
        $this->seedAgent($agentId);

        $this->actingAs($admin, 'admin')
            ->getJson('/index/admin/agents/agents_edit_info/' . $agentId)
            ->assertOk()
            ->assertViewIs('admin_layui::agents.index')
            ->assertSee('data-layui-page="agents/index"', false)
            ->assertSee('id="agentTable"', false)
            ->assertSee('data-legacy-uid', false)
            ->assertSee((string) $agentId, false);
    }

    public function test_legacy_agents_edit_info_rejects_unauthenticated_requests(): void
    {
        $this->getJson('/index/admin/agents/agents_edit_info/987401')
            ->assertUnauthorized();
    }

    private function seedAgent(int $userId): void
    {
        $now = time();
        $loginId = DB::table('user_logins')->insertGetId([
            'user_id' => $userId,
            'email' => 'legacy-agent-edit-info-' . $userId . '@example.test',
            'password' => Hash::make('password'),
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
            'user_name' => 'Legacy edit info agent',
            'account_type' => 1,
            'parent_id' => 0,
            'family_tree' => (string) $userId,
            'created_at' => $now,
            'updated_at' => $now,
            'deleted_at' => null,
        ]);
    }

    private function unusedUserId(): int
    {
        for ($attempt = 0; $attempt < 100; $attempt++) {
            $candidate = random_int(1200000000, 1900000000);
            if (!DB::table('user_logins')->where('user_id', $candidate)->exists()
                && !DB::table('user_infos')->where('user_id', $candidate)->exists()) {
                return $candidate;
            }
        }

        throw new \RuntimeException('Unable to allocate a legacy agent edit-info fixture ID.');
    }
}
