<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/02
 * Time: 01:45
 */

/**
 * 后台代理导出模块测试。
 *
 * 文件功能：
 * - 验证代理导出命名路由 admin_api_exportAgents 存在且挂载权限中间件。
 * - 验证导出接口按当前筛选条件返回 CSV（含表头、字段值与内容 disposition）。
 * - 验证代理列表与导出接口均支持 created_at 起止日期范围筛选。
 * - 验证前端导出按钮、权限迁移与 CrmUi 导出配置均已接线。
 *
 * 适用场景：
 * - 后台代理管理模块代理导出与日期筛选的回归测试。
 *
 * 入参例子：
 * - POST /api/admin/exportAgents
 *   {
 *     "user_name": "Alpha"
 *   }
 * - POST /api/admin/agents
 *   {
 *     "user_name": "Agent Date Filter",
 *     "start_date": "2026-02-01",
 *     "end_date": "2026-02-28",
 *     "per_page": 10
 *   }
 *
 * 方法功能：
 * - test_agent_export_api_route_has_permission_middleware：校验导出路由注册与权限中间件。
 * - test_agent_export_endpoint_returns_current_filter_csv：按姓名筛选导出，断言 CSV 表头与命中/未命中数据。
 * - test_agent_list_endpoint_filters_by_created_date_range：列表按日期范围筛选，断言只返回范围内代理。
 * - test_agent_export_endpoint_filters_by_created_date_range：导出按日期范围筛选，断言 CSV 只含范围内代理。
 * - test_agent_export_frontend_configs_are_exposed：检查 blade、pages.js、CrmUi 导出配置。
 * - test_agent_export_permission_migrations_declare_required_permission：检查操作权限迁移与跟进迁移声明导出权限。
 *
 * 返回值：
 * - 导出成功返回 text/csv 流式响应，列表成功返回 code=SUCCESS；断言失败时抛出 PHPUnit 断言异常。
 *
 * 异常或失败场景：
 * - 若导出包含范围外数据、缺少表头或前端权限缺失，测试断言失败。
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

class AdminAgentExportModuleTest extends TestCase
{
    use DatabaseTransactions;

    /**
     * 校验代理导出命名路由已注册且挂载 check.permission:admin 中间件。
     *
     * @return void
     */
    public function test_agent_export_api_route_has_permission_middleware(): void
    {
        $this->assertTrue(Route::has('admin_api_exportAgents'), 'admin_api_exportAgents API route is not registered.');
        $this->assertContains(
            'check.permission:admin',
            Route::getRoutes()->getByName('admin_api_exportAgents')->gatherMiddleware()
        );
    }

    /**
     * 按姓名筛选导出代理：断言 CSV 表头、命中记录与未命中记录的内容。
     *
     * @return void
     */
    public function test_agent_export_endpoint_returns_current_filter_csv(): void
    {
        $admin = $this->ensureSuperAdmin();
        $now = time();

        $this->upsertAgentExportFixture(985401, 'Export Agent Alpha', 'agent-alpha@example.test', 1, $now);
        $this->upsertAgentExportFixture(985402, 'Export Agent Beta', 'agent-beta@example.test', 1, $now);
        $this->upsertAgentExportFixture(985403, 'Export Agent Customer', 'agent-customer@example.test', 2, $now);

        $response = $this->withoutMiddleware([AdminAuthenticate::class, JwtAuthMiddleware::class, SingleSignOn::class, CheckPermission::class])
            ->actingAs($admin, 'admin')
            ->post('/api/admin/exportAgents', [
                'user_name' => 'Alpha',
            ]);

        $response->assertOk();
        $this->assertStringContainsString('text/csv', (string) $response->headers->get('content-type'));
        $this->assertStringContainsString('agents_export.csv', (string) $response->headers->get('content-disposition'));

        $content = $response->streamedContent();
        $this->assertStringContainsString('user_id,user_name,email,phone,parent_id,level_id,comm_rate,auth_status,total_funds,equity,created_at', $content);
        $this->assertStringContainsString('985401', $content);
        $this->assertStringContainsString('"Export Agent Alpha"', $content);
        $this->assertStringContainsString('agent-alpha@example.test', $content);
        $this->assertStringNotContainsString('985402', $content);
        $this->assertStringNotContainsString('985403', $content);
        $this->assertStringNotContainsString('agent-customer@example.test', $content);
    }

    /**
     * 代理列表按 created_at 起止日期筛选：断言只返回范围内代理。
     *
     * @return void
     */
    public function test_agent_list_endpoint_filters_by_created_date_range(): void
    {
        $admin = $this->ensureSuperAdmin();
        $inRangeAgentId = 985411;
        $outsideAgentId = 985412;

        $this->upsertAgentExportFixture(
            $inRangeAgentId,
            'Agent Date Filter In Range',
            'agent-date-in@example.test',
            1,
            strtotime('2026-02-10 10:00:00')
        );
        $this->upsertAgentExportFixture(
            $outsideAgentId,
            'Agent Date Filter Outside Range',
            'agent-date-out@example.test',
            1,
            strtotime('2026-03-10 10:00:00')
        );

        $response = $this->withoutMiddleware([AdminAuthenticate::class, JwtAuthMiddleware::class, SingleSignOn::class, CheckPermission::class])
            ->actingAs($admin, 'admin')
            ->post('/api/admin/agents', [
                'user_name' => 'Agent Date Filter',
                'start_date' => '2026-02-01',
                'end_date' => '2026-02-28',
                'per_page' => 10,
            ]);

        $response->assertOk();
        $response->assertJsonPath('code', ResponseCode::SUCCESS);
        $response->assertJsonPath('data.total', 1);
        $this->assertSame($inRangeAgentId, (int) $response->json('data.data.0.user_id'));
    }

    /**
     * 代理导出按 created_at 起止日期筛选：断言 CSV 只含范围内代理。
     *
     * @return void
     */
    public function test_agent_export_endpoint_filters_by_created_date_range(): void
    {
        $admin = $this->ensureSuperAdmin();

        $this->upsertAgentExportFixture(
            985421,
            'Export Date Filter In Range',
            'export-date-in@example.test',
            1,
            strtotime('2026-04-10 10:00:00')
        );
        $this->upsertAgentExportFixture(
            985422,
            'Export Date Filter Outside Range',
            'export-date-out@example.test',
            1,
            strtotime('2026-05-10 10:00:00')
        );

        $response = $this->withoutMiddleware([AdminAuthenticate::class, JwtAuthMiddleware::class, SingleSignOn::class, CheckPermission::class])
            ->actingAs($admin, 'admin')
            ->post('/api/admin/exportAgents', [
                'user_name' => 'Export Date Filter',
                'start_date' => '2026-04-01',
                'end_date' => '2026-04-30',
            ]);

        $response->assertOk();
        $content = $response->streamedContent();

        $this->assertStringContainsString('985421', $content);
        $this->assertStringContainsString('export-date-in@example.test', $content);
        $this->assertStringNotContainsString('985422', $content);
        $this->assertStringNotContainsString('export-date-out@example.test', $content);
    }

    /**
     * 检查 blade、pages.js、CrmUi PageController 暴露导出按钮与导出路由配置。
     *
     * @return void
     */
    public function test_agent_export_frontend_configs_are_exposed(): void
    {
        $blade = file_get_contents(resource_path('admin/layui/agents/index.blade.php')) ?: '';
        $layui = file_get_contents(public_path('js/apps/admin/layui/pages.js')) ?: '';
        $crmui = file_get_contents(app_path('Http/Controllers/CrmUi/Admin/PageController.php')) ?: '';

        $this->assertStringContainsString('id="exportAgents"', $blade);
        $this->assertStringContainsString('data-permission="admin_agent_export"', $blade);
        $this->assertStringContainsString('/api/admin/exportAgents', $layui);
        $this->assertStringContainsString("exportActions('admin_api_exportAgents', 'agents_export.csv')", $crmui);
    }

    /**
     * 检查操作权限迁移与跟进迁移声明代理导出权限。
     *
     * @return void
     */
    public function test_agent_export_permission_migrations_declare_required_permission(): void
    {
        $operationMigration = file_get_contents(database_path('migrations/2026_06_07_000003_add_admin_agent_operation_permissions.php')) ?: '';
        $followUpPath = database_path('migrations/2026_07_07_000003_add_admin_agent_export_permission.php');

        $this->assertStringContainsString('admin_agent_export', $operationMigration);
        $this->assertStringContainsString('admin_api_exportAgents', $operationMigration);
        $this->assertFileExists($followUpPath, 'Existing databases need a follow-up migration for the new agent export permission.');

        $followUpMigration = file_get_contents($followUpPath) ?: '';
        $this->assertStringContainsString('admin_agent_export', $followUpMigration);
        $this->assertStringContainsString('admin_api_exportAgents', $followUpMigration);
    }

    private function ensureSuperAdmin(): Admin
    {
        $now = time();

        DB::table('admins')->updateOrInsert(
            ['id' => 1],
            [
                'username' => 'agent-export-admin',
                'email' => 'agent-export-admin@example.test',
                'password' => bcrypt('password'),
                'status' => 1,
                'created_at' => $now,
                'updated_at' => $now,
                'deleted_at' => null,
            ]
        );

        return Admin::query()->findOrFail(1);
    }

    private function upsertAgentExportFixture(int $userId, string $userName, string $email, int $accountType, int $now): void
    {
        DB::table('user_infos')->where('user_id', $userId)->delete();
        DB::table('user_logins')->where('email', $email)->orWhere('user_id', $userId)->delete();

        $loginId = DB::table('user_logins')->insertGetId([
            'user_id' => $userId,
            'email' => $email,
            'password' => bcrypt('password'),
            'account_type' => $accountType,
            'is_enabled' => 1,
            'is_cancelled' => 0,
            'source_type' => 0,
            'created_at' => $now,
            'updated_at' => $now,
            'deleted_at' => null,
        ]);

        DB::table('user_infos')->updateOrInsert(
            ['user_id' => $userId],
            [
                'login_id' => $loginId,
                'user_name' => $userName,
                'phone' => '1770000' . substr((string) $userId, -4),
                'account_type' => $accountType,
                'parent_id' => 0,
                'level_id' => 2,
                'comm_rate' => 0.25,
                'auth_status' => 1,
                'total_funds' => 250,
                'equity' => 260,
                'created_at' => $now,
                'updated_at' => $now,
                'deleted_at' => null,
            ]
        );
    }
}
