<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/02
 * Time: 01:46
 */

/**
 * 后台代理统计模块测试。
 *
 * 文件功能：
 * - 验证代理统计命名路由 admin_api_agentStatsList 存在且挂载权限中间件。
 * - 验证统计接口返回真实 level_id（映射为 group_id）、直接子代理/子客户数量（mun、user_mun）与余额/净值汇总。
 * - 验证按 created_at 日期范围筛选统计列表。
 * - 验证从 user_trades 汇总旧版佣金字段（fy_money、rj_money、qk_money 等）。
 * - 验证控制器源码与前端配置使用 user_infos.level_id 而非旧版 agent_level 字段。
 *
 * 适用场景：
 * - 后台代理管理模块代理统计列表的字段口径、筛选与前后端一致性回归测试。
 *
 * 入参例子：
 * - POST /api/admin/agentStatsList
 *   {
 *     "form": 1,
 *     "user_id": 985701,
 *     "per_page": 5
 *   }
 *
 * 方法功能：
 * - test_agent_stats_api_route_has_permission_middleware：校验统计路由注册与权限中间件。
 * - test_agent_stats_endpoint_uses_real_level_id_and_returns_direct_counts：断言 group_id、直接计数与 BALANCE/EQUITY 汇总。
 * - test_agent_stats_endpoint_filters_agents_by_created_date_range：断言按日期范围筛选只返回范围内代理。
 * - test_agent_stats_endpoint_exposes_legacy_money_fields_from_user_trades：断言佣金字段从 user_trades 按 comment 汇总。
 * - test_agent_stats_source_targets_level_id_not_legacy_agent_level：检查控制器源码使用 level_id 别名 group_id。
 * - test_agent_stats_frontend_configs_are_exposed：检查 blade、pages.js、CrmUi 统计配置。
 * - test_agent_stats_permission_migrations_declare_required_permission：检查操作权限迁移与跟进迁移声明统计权限。
 *
 * 返回值：
 * - 统计成功返回 code=SUCCESS；断言失败时抛出 PHPUnit 断言异常。
 *
 * 异常或失败场景：
 * - 若统计字段口径错误、筛选失效或前端配置缺失，测试断言失败。
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
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class AdminAgentStatsModuleTest extends TestCase
{
    use DatabaseTransactions;

    /**
     * 校验统计命名路由已注册且挂载 check.permission:admin 中间件。
     *
     * @return void
     */
    public function test_agent_stats_api_route_has_permission_middleware(): void
    {
        $this->assertTrue(Route::has('admin_api_agentStatsList'), 'admin_api_agentStatsList API route is not registered.');
        $this->assertContains(
            'check.permission:admin',
            Route::getRoutes()->getByName('admin_api_agentStatsList')->gatherMiddleware()
        );
    }

    /**
     * 统计接口：断言真实 level_id（group_id）、直接计数与 BALANCE/EQUITY 汇总。
     *
     * @return void
     */
    public function test_agent_stats_endpoint_uses_real_level_id_and_returns_direct_counts(): void
    {
        $admin = $this->ensureSuperAdmin();
        $now = time();
        $agentUserId = 985701;
        $levelId = 4;

        $this->upsertAgentStatsFixture($agentUserId, 'Stats Root Agent', 1, 0, $levelId, 1200.5, 1300.75, $now - 300);
        $this->upsertAgentStatsFixture(985702, 'Stats Child Agent', 1, $agentUserId, 2, 200, 210, $now - 200);
        $this->upsertAgentStatsFixture(985703, 'Stats Child Customer', 2, $agentUserId, 0, 100, 110, $now - 100);
        $this->upsertAgentStatsFixture(985704, 'Stats Other Agent', 1, 0, 3, 999, 999, $now - 50);

        $response = $this->withoutMiddleware([AdminAuthenticate::class, JwtAuthMiddleware::class, SingleSignOn::class, CheckPermission::class])
            ->actingAs($admin, 'admin')
            ->post('/api/admin/agentStatsList', [
                'form' => 1,
                'user_id' => $agentUserId,
                'per_page' => 5,
            ]);

        $response->assertOk();
        $response->assertJsonPath('code', ResponseCode::SUCCESS);

        $this->assertSame(1, (int) $response->json('data.count'));
        $this->assertSame($agentUserId, (int) $response->json('data.data.0.user_id'));
        $this->assertSame($levelId, (int) $response->json('data.data.0.group_id'));
        $this->assertSame(1, (int) $response->json('data.data.0.mun'));
        $this->assertSame(1, (int) $response->json('data.data.0.user_mun'));
        $this->assertSame('1200.50', $response->json('data.data.0.BALANCE'));
        $this->assertSame('1300.75', $response->json('data.data.0.EQUITY'));
        $this->assertSame('1200.50', $response->json('data.totalRow.BALANCE'));
        $this->assertSame('1300.75', $response->json('data.totalRow.EQUITY'));
    }

    /**
     * 统计接口按 created_at 日期范围筛选：断言只返回范围内代理。
     *
     * @return void
     */
    public function test_agent_stats_endpoint_filters_agents_by_created_date_range(): void
    {
        $admin = $this->ensureSuperAdmin();
        $inRangeAgentId = 985711;
        $outsideAgentId = 985712;

        $this->upsertAgentStatsFixture(
            $inRangeAgentId,
            'Stats Date Range Agent',
            1,
            0,
            3,
            510,
            520,
            strtotime('2026-01-15 12:00:00')
        );
        $this->upsertAgentStatsFixture(
            $outsideAgentId,
            'Stats Date Range Outside Agent',
            1,
            0,
            3,
            910,
            920,
            strtotime('2025-12-31 12:00:00')
        );

        $response = $this->withoutMiddleware([AdminAuthenticate::class, JwtAuthMiddleware::class, SingleSignOn::class, CheckPermission::class])
            ->actingAs($admin, 'admin')
            ->post('/api/admin/agentStatsList', [
                'form' => 1,
                'user_name' => 'Stats Date Range',
                'start_date' => '2026-01-01',
                'end_date' => '2026-01-31',
                'per_page' => 10,
            ]);

        $response->assertOk();
        $response->assertJsonPath('code', ResponseCode::SUCCESS);

        $this->assertSame(1, (int) $response->json('data.count'));
        $this->assertSame($inRangeAgentId, (int) $response->json('data.data.0.user_id'));
        $this->assertSame('510.00', $response->json('data.totalRow.BALANCE'));
        $this->assertSame('520.00', $response->json('data.totalRow.EQUITY'));
    }

    /**
     * 统计接口：断言旧版佣金字段按 user_trades.comment 汇总返回。
     *
     * @return void
     */
    public function test_agent_stats_endpoint_exposes_legacy_money_fields_from_user_trades(): void
    {
        $admin = $this->ensureSuperAdmin();
        $agentUserId = 985721;

        $this->upsertAgentStatsFixture($agentUserId, 'Stats Money Agent', 1, 0, 3, 610, 620, time());
        $this->insertUserTrade($agentUserId, 98572101, 6, 12.34, 'agent rebate -FY');
        $this->insertUserTrade($agentUserId, 98572102, 6, 56.78, 'Deposit approved');
        $this->insertUserTrade($agentUserId, 98572103, 6, -9.87, 'Withdrawal approved');

        $response = $this->withoutMiddleware([AdminAuthenticate::class, JwtAuthMiddleware::class, SingleSignOn::class, CheckPermission::class])
            ->actingAs($admin, 'admin')
            ->post('/api/admin/agentStatsList', [
                'form' => 1,
                'user_id' => $agentUserId,
                'per_page' => 5,
            ]);

        $response->assertOk();
        $response->assertJsonPath('code', ResponseCode::SUCCESS);

        $this->assertSame('12.34', $response->json('data.data.0.money.total_fy'));
        $this->assertSame('56.78', $response->json('data.data.0.money.total_rj'));
        $this->assertSame('-9.87', $response->json('data.data.0.money.total_qk'));
        $this->assertSame('12.34', $response->json('data.data.0.fy_money'));
        $this->assertSame('56.78', $response->json('data.data.0.rj_money'));
        $this->assertSame('-9.87', $response->json('data.data.0.qk_money'));
        $this->assertSame('12.34', $response->json('data.totalRow.fy_money'));
        $this->assertSame('56.78', $response->json('data.totalRow.rj_money'));
        $this->assertSame('-9.87', $response->json('data.totalRow.qk_money'));
        $this->assertSame('12.34', $response->json('data.totalRow.all_total_fy'));
        $this->assertSame('56.78', $response->json('data.totalRow.all_total_rj'));
        $this->assertSame('-9.87', $response->json('data.totalRow.all_total_qk'));
    }

    /**
     * 检查控制器源码使用 user_infos.level_id 别名 group_id 而非旧版 agent_level。
     *
     * @return void
     */
    public function test_agent_stats_source_targets_level_id_not_legacy_agent_level(): void
    {
        $source = file_get_contents(app_path('Http/Controllers/Admin/AgentController.php')) ?: '';

        $this->assertStringContainsString('user_infos.level_id as group_id', $source);
        $this->assertStringNotContainsString('user_infos.agent_level as group_id', $source);
    }

    /**
     * 检查 blade、pages.js、CrmUi PageController 暴露统计按钮、日期筛选与路由配置。
     *
     * @return void
     */
    public function test_agent_stats_frontend_configs_are_exposed(): void
    {
        $blade = file_get_contents(resource_path('admin/layui/agents/index.blade.php')) ?: '';
        $layui = file_get_contents(public_path('js/apps/admin/layui/pages.js')) ?: '';
        $crmui = file_get_contents(app_path('Http/Controllers/CrmUi/Admin/PageController.php')) ?: '';

        $this->assertStringContainsString('id="loadAgentStats"', $blade);
        $this->assertStringContainsString('data-permission="admin_agent_stats"', $blade);
        $this->assertStringContainsString('id="agentStartDate"', $blade);
        $this->assertStringContainsString('name="start_date"', $blade);
        $this->assertStringContainsString('id="agentEndDate"', $blade);
        $this->assertStringContainsString('name="end_date"', $blade);
        $this->assertStringContainsString('/api/admin/agentStatsList', $layui);
        $this->assertStringContainsString("laydate.render({elem: '#agentStartDate'", $layui);
        $this->assertStringContainsString("laydate.render({elem: '#agentEndDate'", $layui);
        $this->assertStringContainsString("'route' => 'admin_api_agentStatsList'", $crmui);
    }

    /**
     * 检查操作权限迁移与跟进迁移声明代理统计权限。
     *
     * @return void
     */
    public function test_agent_stats_permission_migrations_declare_required_permission(): void
    {
        $operationMigration = file_get_contents(database_path('migrations/2026_06_07_000003_add_admin_agent_operation_permissions.php')) ?: '';
        $followUpPath = database_path('migrations/2026_07_07_000005_add_admin_agent_stats_permission.php');

        $this->assertStringContainsString('admin_agent_stats', $operationMigration);
        $this->assertStringContainsString('admin_api_agentStatsList', $operationMigration);
        $this->assertFileExists($followUpPath, 'Existing databases need a follow-up migration for the agent stats permission.');

        $followUpMigration = file_get_contents($followUpPath) ?: '';
        $this->assertStringContainsString('admin_agent_stats', $followUpMigration);
        $this->assertStringContainsString('admin_api_agentStatsList', $followUpMigration);
    }

    private function ensureSuperAdmin(): Admin
    {
        $now = time();

        DB::table('admins')->updateOrInsert(
            ['id' => 1],
            [
                'username' => 'agent-stats-admin',
                'email' => 'agent-stats-admin@example.test',
                'password' => bcrypt('password'),
                'status' => 1,
                'created_at' => $now,
                'updated_at' => $now,
                'deleted_at' => null,
            ]
        );

        return Admin::query()->findOrFail(1);
    }

    private function upsertAgentStatsFixture(
        int $userId,
        string $userName,
        int $accountType,
        int $parentId,
        int $levelId,
        float $totalFunds,
        float $equity,
        int $now
    ): void {
        DB::table('user_infos')->where('user_id', $userId)->delete();
        DB::table('user_logins')->where('user_id', $userId)->delete();
        DB::table('user_trades')->where('user_id', $userId)->delete();

        $loginId = DB::table('user_logins')->insertGetId([
            'user_id' => $userId,
            'email' => 'agent-stats-' . $userId . '@example.test',
            'password' => bcrypt('password'),
            'account_type' => $accountType,
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
            'phone' => '1760000' . substr((string) $userId, -4),
            'account_type' => $accountType,
            'parent_id' => $parentId,
            'level_id' => $levelId,
            'comm_rate' => 0.2,
            'auth_status' => 1,
            'total_funds' => $totalFunds,
            'equity' => $equity,
            'created_at' => $now,
            'updated_at' => $now,
            'deleted_at' => null,
        ]);
    }

    private function insertUserTrade(int $userId, int $ticket, int $cmd, float $profit, string $comment): void
    {
        $now = time();

        DB::table('user_trades')->where('ticket', $ticket)->delete();
        DB::table('user_trades')->insert([
            'user_id' => $userId,
            'ticket' => $ticket,
            'symbol' => '',
            'digits' => 2,
            'cmd' => $cmd,
            'volume' => 0,
            'open_time' => date('Y-m-d H:i:s', $now),
            'open_price' => 0,
            'stop_loss' => 0,
            'take_profit' => 0,
            'close_time' => date('Y-m-d H:i:s', $now),
            'expiration' => null,
            'reason' => 0,
            'conv_rate1' => 0,
            'conv_rate2' => 0,
            'commission' => 0,
            'commission_agent' => 0,
            'swaps' => 0,
            'close_price' => 0,
            'profit' => $profit,
            'taxes' => 0,
            'comment' => $comment,
            'internal_id' => 0,
            'margin_rate' => 0,
            'timestamp_val' => $now,
            'magic' => 0,
            'gw_volume' => 0,
            'gw_open_price' => 0,
            'gw_close_price' => 0,
            'modify_time' => date('Y-m-d H:i:s', $now),
            'settlement_status' => 0,
            'settled_at' => null,
            'created_at' => $now,
            'updated_at' => $now,
            'deleted_at' => null,
        ]);
    }
}
