<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/02
 * Time: 05:10
 */

/**
 * AdminRealtimeCommissionModuleTest
 *
 * 文件功能：
 * - 验证后台实时返佣模块：页面/路由权限、按当前筛选导出 CSV、旧返佣 comment 关键词与顺序过滤、mt4_trades 与数据范围口径及权限迁移。
 * - 输入：HTTP 请求与事务回滚的数据库夹具；输出：PHPUnit 断言结果。
 * - 明确不负责：不覆盖真实 MT4 网关与线上支付/出金通道（由网关契约测试锁定）。
 */

namespace Tests\Feature;

use App\Http\Middleware\AdminAuthenticate;
use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\JwtAuthMiddleware;
use App\Http\Middleware\SingleSignOn;
use App\Models\Admin;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * 后台实时返佣模块契约测试。
 *
 * 功能逻辑说明：
 * - 旧项目实时返佣读取 MT4 余额类交易并按返佣关键词区分来源。
 * - 新项目当前真实表 `mt4_trades` 已补齐 comment 与 modify_time，本测试约束后台列表必须恢复旧 COMMENT 返佣识别口径。
 * - 测试使用受控夹具验证页面、路由、权限字典、控制器真实数据源、数据范围接入和当前筛选 CSV 导出闭环。
 */
class AdminRealtimeCommissionModuleTest extends TestCase
{
    /**
     * 实时返佣页面必须注册为后台独立 Blade 路由。
     *
     * @return void
     */
    public function test_realtime_commission_page_is_registered(): void
    {
        $this->assertTrue(Route::has('admin_page_realtime_commissions'), 'admin_page_realtime_commissions 页面路由未注册。');
    }

    /**
     * 实时返佣页面必须包含查询表单、汇总区、表格容器和页面脚本。
     *
     * @return void
     */
    public function test_realtime_commission_page_renders_blade_controls(): void
    {
        $response = $this->get('/admin/realtime-commissions');

        $response->assertOk();
        $response->assertSee('crm-admin-workbench', false);
        $response->assertSee('id="realtimeCommissionSearchForm"', false);
        $response->assertSee('id="realtimeCommissionSummary"', false);
        $response->assertSee('id="realtimeCommissionTable"', false);
        $response->assertSee('id="exportRealtimeCommissions"', false);
        $response->assertSee('data-permission="admin_realtime_commission_export"', false);
        $response->assertSee('/js/apps/admin/layui/pages.js', false);
        $response->assertSee("data-layui-page=\"realtime-commissions/index\"", false);
    }

    /**
     * 实时返佣列表 API 必须挂载后台 JWT、SSO 和权限中间件。
     *
     * @return void
     */
    public function test_realtime_commission_api_route_has_permission_middleware(): void
    {
        $this->assertTrue(Route::has('admin_api_realtimeCommissionList'), 'admin_api_realtimeCommissionList API 路由未注册。');

        $this->assertContains(
            'check.permission:admin',
            Route::getRoutes()->getByName('admin_api_realtimeCommissionList')->gatherMiddleware()
        );
    }

    /**
     * 实时返佣控制器必须读取真实 `mt4_trades` 表并套用后台数据范围。
     *
     * @return void
     */
    public function test_realtime_commission_export_api_route_has_permission_middleware(): void
    {
        $this->assertTrue(Route::has('admin_api_exportRealtimeCommissions'), 'admin_api_exportRealtimeCommissions API route is not registered.');
        $this->assertContains(
            'check.permission:admin',
            Route::getRoutes()->getByName('admin_api_exportRealtimeCommissions')->gatherMiddleware()
        );
    }

    public function test_realtime_commission_export_endpoint_returns_current_filter_csv(): void
    {
        $admin = Admin::query()->find(1) ?: (Admin::query()->first() ?: Admin::factory()->create());
        $now = time();
        $userId = 982731;

        DB::table('mt4_trades')->updateOrInsert(
            ['ticket' => 990731],
            [
                'login' => $userId,
                'symbol' => 'REBATECSV',
                'cmd' => 6,
                'volume' => 0,
                'open_price' => 0,
                'close_price' => 0,
                'commission' => 0,
                'swaps' => 0,
                'profit' => 45.67,
                'open_time' => $now - 7200,
                'close_time' => $now - 3600,
                'comment' => 'DBCN982731-#990731',
                'modify_time' => $now - 3600,
                'created_at' => $now,
                'updated_at' => $now,
            ]
        );

        DB::table('mt4_trades')->updateOrInsert(
            ['ticket' => 990732],
            [
                'login' => $userId + 1,
                'symbol' => 'OTHERCSV',
                'cmd' => 6,
                'volume' => 0,
                'open_price' => 0,
                'close_price' => 0,
                'commission' => 0,
                'swaps' => 0,
                'profit' => 98.76,
                'open_time' => $now - 7200,
                'close_time' => $now - 3600,
                'comment' => 'DBCN982732-#990732',
                'modify_time' => $now - 3600,
                'created_at' => $now,
                'updated_at' => $now,
            ]
        );

        $response = $this->withoutMiddleware([AdminAuthenticate::class, JwtAuthMiddleware::class, SingleSignOn::class, CheckPermission::class])
            ->actingAs($admin, 'admin')
            ->post('/api/admin/exportRealtimeCommissions', ['user_id' => $userId]);

        $response->assertOk();
        $this->assertStringContainsString('text/csv', (string) $response->headers->get('content-type'));
        $this->assertStringContainsString('realtime_commissions_export.csv', (string) $response->headers->get('content-disposition'));

        $content = $response->streamedContent();
        $this->assertStringContainsString('990731', $content);
        $this->assertStringContainsString('REBATECSV', $content);
        $this->assertStringContainsString('DBCN982731-#990731', $content);
        $this->assertStringContainsString('45.67', $content);
        $this->assertStringNotContainsString('990732', $content);
    }

    /**
     * 实时返佣必须恢复旧项目 COMMENT 精确识别，普通入金类正向余额记录不能混入返佣列表。
     *
     * @return void
     */
    public function test_realtime_commissions_use_legacy_comment_keywords_and_comment_order_filter(): void
    {
        $admin = Admin::query()->find(1) ?: (Admin::query()->first() ?: Admin::factory()->create());
        $now = time();
        $userId = 982741;

        DB::table('mt4_trades')->updateOrInsert(
            ['ticket' => 990741],
            [
                'login' => $userId,
                'symbol' => 'REBATEKEY',
                'cmd' => 6,
                'volume' => 0,
                'open_price' => 0,
                'close_price' => 0,
                'commission' => 0,
                'swaps' => 0,
                'profit' => 12.34,
                'open_time' => $now - 7200,
                'close_time' => $now - 3600,
                'comment' => 'DBCN-982741-#SOURCE-880001',
                'modify_time' => $now - 1800,
                'created_at' => $now,
                'updated_at' => $now,
            ]
        );

        DB::table('mt4_trades')->updateOrInsert(
            ['ticket' => 990742],
            [
                'login' => $userId,
                'symbol' => 'DEPOSITKEY',
                'cmd' => 6,
                'volume' => 0,
                'open_price' => 0,
                'close_price' => 0,
                'commission' => 0,
                'swaps' => 0,
                'profit' => 88.88,
                'open_time' => $now - 7200,
                'close_time' => $now - 3600,
                'comment' => 'Deposit SOURCE-880001',
                'modify_time' => $now - 1700,
                'created_at' => $now,
                'updated_at' => $now,
            ]
        );

        DB::table('mt4_trades')->updateOrInsert(
            ['ticket' => 990743],
            [
                'login' => $userId,
                'symbol' => 'LEGACYFY',
                'cmd' => 6,
                'volume' => 0,
                'open_price' => 0,
                'close_price' => 0,
                'commission' => 0,
                'swaps' => 0,
                'profit' => 5.50,
                'open_time' => $now - 7200,
                'close_time' => $now - 3600,
                'comment' => 'SOURCE-880002-FY',
                'modify_time' => $now - 1600,
                'created_at' => $now,
                'updated_at' => $now,
            ]
        );

        $listResponse = $this->withoutMiddleware([AdminAuthenticate::class, JwtAuthMiddleware::class, SingleSignOn::class, CheckPermission::class])
            ->actingAs($admin, 'admin')
            ->post('/api/admin/realtimeCommissionList', [
                'user_id' => $userId,
                'order_id' => 'SOURCE-880001',
            ]);

        $listResponse->assertOk();
        $listResponse->assertJsonPath('data.summary.total_records', 1);
        $listResponse->assertJsonPath('data.summary.total_profit', 12.34);
        $listResponse->assertJsonPath('data.records.data.0.ticket', 990741);
        $listResponse->assertJsonPath('data.records.data.0.comment', 'DBCN-982741-#SOURCE-880001');
        $listResponse->assertJsonPath('data.records.data.0.modify_time', $now - 1800);
        $listResponse->assertJsonPath('data.records.data.0.rebate_source', 'legacy_dbcn');
        $listResponse->assertJsonPath('data.records.data.0.rebate_source_name', '账户返佣');

        $allResponse = $this->withoutMiddleware([AdminAuthenticate::class, JwtAuthMiddleware::class, SingleSignOn::class, CheckPermission::class])
            ->actingAs($admin, 'admin')
            ->post('/api/admin/realtimeCommissionList', ['user_id' => $userId]);

        $allResponse->assertOk();
        $tickets = collect($allResponse->json('data.records.data'))->pluck('ticket')->all();
        $this->assertContains(990741, $tickets);
        $this->assertContains(990743, $tickets);
        $this->assertNotContains(990742, $tickets);

        $exportResponse = $this->withoutMiddleware([AdminAuthenticate::class, JwtAuthMiddleware::class, SingleSignOn::class, CheckPermission::class])
            ->actingAs($admin, 'admin')
            ->post('/api/admin/exportRealtimeCommissions', [
                'user_id' => $userId,
                'ticket' => 'SOURCE-880001',
            ]);

        $exportResponse->assertOk();
        $content = $exportResponse->streamedContent();
        $this->assertStringContainsString('rebate_source', $content);
        $this->assertStringContainsString('comment', $content);
        $this->assertStringContainsString('modify_time', $content);
        $this->assertStringContainsString('DBCN-982741-#SOURCE-880001', $content);
        $this->assertStringNotContainsString('Deposit SOURCE-880001', $content);
    }

    public function test_realtime_commission_controller_uses_mt4_trades_and_data_scope(): void
    {
        $controllerPath = app_path('Http/Controllers/Admin/RealtimeCommissionController.php');

        $this->assertFileExists($controllerPath, 'RealtimeCommissionController 控制器不存在。');
        $source = file_get_contents($controllerPath);

        foreach ([
            'Mt4Trade::query()',
            'cmd',
            'profit',
            'close_time',
            'AdminDataScopeService',
            'applyDataScope',
        ] as $expected) {
            $this->assertStringContainsString($expected, $source);
        }
    }

    /**
     * 实时返佣权限迁移必须声明页面入口和列表接口权限。
     *
     * @return void
     */
    public function test_realtime_commission_permission_migration_declares_required_permissions(): void
    {
        $migrationPath = database_path('migrations/2026_06_07_000013_add_admin_realtime_commission_permissions.php');

        $this->assertFileExists($migrationPath, '实时返佣权限迁移文件不存在。');
        $source = file_get_contents($migrationPath);

        foreach ([
            'admin_realtime_commissions',
            'admin_realtime_commission_list',
            'admin_realtime_commission_export',
            'admin_api_realtimeCommissionList',
            'admin_api_exportRealtimeCommissions',
            '/admin/realtime-commissions',
        ] as $expected) {
            $this->assertStringContainsString($expected, $source);
        }
    }

    public function test_realtime_commission_ui_surfaces_expose_export_action(): void
    {
        $layui = file_get_contents(resource_path('admin/layui/realtime-commissions/index.blade.php')) ?: '';
        $layuiJs = file_get_contents(public_path('js/apps/admin/layui/pages.js')) ?: '';
        $crmui = file_get_contents(app_path('Http/Controllers/CrmUi/Admin/PageController.php')) ?: '';

        foreach ([
            'id="exportRealtimeCommissions"',
            'data-permission="admin_realtime_commission_export"',
        ] as $expected) {
            $this->assertStringContainsString($expected, $layui);
        }

        $this->assertStringContainsString('/api/admin/exportRealtimeCommissions', $layuiJs);
        $this->assertStringContainsString("exportActions('admin_api_exportRealtimeCommissions', 'realtime_commissions_export.csv')", $crmui);
    }
}
