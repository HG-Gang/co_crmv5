<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/07/26
 * Time: 04:22
 */

/**
 * AdminProductionModuleTest
 *
 * 文件功能：
 * - 验证后台产品/交易品种模块：页面注册、Blade 控件、列表与导出 API 权限、真实 symbol_prices/mt4_trades 表口径、品种维护写接口与操作日志。
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
 * 后台产品/交易品种模块覆盖测试。
 *
 * 功能逻辑说明：
 * - 旧项目 `AdminProductionController` 主要基于 `symbol_prices` 和 `MT4_TRADES` 做交易品种列表与持仓汇总。
 * - 新项目第一阶段以当前真实表 `symbol_prices` 与 `mt4_trades` 为准，提供后台 Blade 页面、只读列表 API、权限表配置和多语言文案。
 * - 当前 MySQL 3307 可能不可用，本测试不读真实数据库，只约束页面、路由、中间件、控制器源码和权限迁移契约。
 */
class AdminProductionModuleTest extends TestCase
{
    /**
     * 产品/交易品种页面必须注册为独立 Blade 路由，避免被后台 Naive 兜底路由接管。
     *
     * @return void
     */
    public function test_production_page_is_registered(): void
    {
        $this->assertTrue(Route::has('admin_page_productions'), 'admin_page_productions 页面路由未注册。');
    }

    /**
     * 产品/交易品种页面必须包含筛选表单、表格容器、汇总卡片和页面脚本。
     *
     * @return void
     */
    public function test_production_page_renders_blade_controls(): void
    {
        $response = $this->get('/admin/productions');

        $response->assertOk();
        $response->assertSee('crm-admin-workbench', false);
        $response->assertSee('id="productionSearchForm"', false);
        $response->assertSee('id="productionTable"', false);
        $response->assertSee('data-summary-field="total_symbols"', false);
        $response->assertSee('name="symbol"', false);
        $response->assertSee('id="exportProductions"', false);
        $response->assertSee('data-permission="admin_production_export"', false);
        $response->assertSee('/js/apps/admin/layui/pages.js', false);
        $response->assertSee("data-layui-page=\"productions/index\"", false);
    }

    /**
     * 产品/交易品种列表 API 必须注册并挂载后台权限中间件。
     *
     * @return void
     */
    public function test_production_api_route_has_permission_middleware(): void
    {
        $this->assertTrue(Route::has('admin_api_productionList'), 'admin_api_productionList API 路由未注册。');
        $this->assertContains(
            'check.permission:admin',
            Route::getRoutes()->getByName('admin_api_productionList')->gatherMiddleware()
        );
    }

    /**
     * 控制器必须读取当前真实表 symbol_prices，并用 mt4_trades 汇总买卖方向、手数和浮动盈亏。
     *
     * @return void
     */
    public function test_production_export_api_route_has_permission_middleware(): void
    {
        $this->assertTrue(Route::has('admin_api_exportProductions'), 'admin_api_exportProductions API route is not registered.');
        $this->assertContains(
            'check.permission:admin',
            Route::getRoutes()->getByName('admin_api_exportProductions')->gatherMiddleware()
        );
    }

    public function test_production_export_endpoint_returns_current_filter_csv(): void
    {
        $admin = Admin::query()->find(1) ?: (Admin::query()->first() ?: Admin::factory()->create());
        $now = time();

        DB::table('symbol_prices')->updateOrInsert(
            ['symbol' => 'XAUPEX'],
            [
                'time' => date('Y-m-d H:i:s', $now),
                'bid' => 1901.12,
                'ask' => 1901.62,
                'low' => 1898.1,
                'high' => 1905.2,
                'direction' => 0,
                'digits' => 2,
                'spread' => 0.5,
                'group_id' => 7,
                'status' => 1,
                'modify_time' => date('Y-m-d H:i:s', $now),
                'created_at' => $now,
                'updated_at' => $now,
                'deleted_at' => null,
            ]
        );

        DB::table('symbol_prices')->updateOrInsert(
            ['symbol' => 'EURPEX'],
            [
                'time' => date('Y-m-d H:i:s', $now),
                'bid' => 1.081,
                'ask' => 1.082,
                'low' => 1.07,
                'high' => 1.09,
                'direction' => 0,
                'digits' => 5,
                'spread' => 0.001,
                'group_id' => 8,
                'status' => 1,
                'modify_time' => date('Y-m-d H:i:s', $now),
                'created_at' => $now,
                'updated_at' => $now,
                'deleted_at' => null,
            ]
        );

        DB::table('mt4_trades')->updateOrInsert(
            ['ticket' => 990710],
            [
                'login' => 982710,
                'symbol' => 'XAUPEX',
                'cmd' => 0,
                'volume' => 2.5,
                'open_price' => 1900,
                'close_price' => null,
                'commission' => 0,
                'swaps' => 0,
                'profit' => 88.66,
                'open_time' => $now - 3600,
                'close_time' => 0,
                'created_at' => $now,
                'updated_at' => $now,
            ]
        );

        DB::table('mt4_trades')->updateOrInsert(
            ['ticket' => 990711],
            [
                'login' => 982711,
                'symbol' => 'XAUPEX',
                'cmd' => 1,
                'volume' => 1.1,
                'open_price' => 1902,
                'close_price' => null,
                'commission' => 0,
                'swaps' => 0,
                'profit' => -12.34,
                'open_time' => $now - 1800,
                'close_time' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ]
        );

        $response = $this->withoutMiddleware([AdminAuthenticate::class, JwtAuthMiddleware::class, SingleSignOn::class, CheckPermission::class])
            ->actingAs($admin, 'admin')
            ->post('/api/admin/exportProductions', ['group_id' => 7]);

        $response->assertOk();
        $this->assertStringContainsString('text/csv', (string) $response->headers->get('content-type'));
        $this->assertStringContainsString('productions_export.csv', (string) $response->headers->get('content-disposition'));

        $content = $response->streamedContent();
        $this->assertStringContainsString('XAUPEX', $content);
        $this->assertStringContainsString('2.5', $content);
        $this->assertStringContainsString('1.1', $content);
        $this->assertStringContainsString('76.32', $content);
        $this->assertStringNotContainsString('EURPEX', $content);
    }

    public function test_production_controller_uses_real_symbol_and_trade_tables(): void
    {
        $controllerPath = app_path('Http/Controllers/Admin/ProductionController.php');

        $this->assertFileExists($controllerPath, 'ProductionController 控制器不存在。');
        $source = file_get_contents($controllerPath);

        $this->assertStringContainsString('SymbolPrice::query()', $source);
        $this->assertStringContainsString('mt4_trades', $source);
        $this->assertStringContainsString('leftJoin', $source);
        $this->assertStringContainsString('total_buy_volume', $source);
        $this->assertStringContainsString('net_volume', $source);
    }

    /**
     * 权限迁移必须声明页面权限和列表 API 权限，保证鉴权仍来自 permissions 表配置。
     *
     * @return void
     */
    public function test_production_permission_migration_declares_required_permissions(): void
    {
        $migrationPath = database_path('migrations/2026_06_07_000010_add_admin_production_permissions.php');

        $this->assertFileExists($migrationPath, '产品/交易品种权限迁移文件不存在。');
        $source = file_get_contents($migrationPath);

        foreach ([
            'admin_productions',
            'admin_production_list',
            'admin_production_export',
            'admin_api_productionList',
            'admin_api_exportProductions',
            '/admin/productions',
        ] as $expected) {
            $this->assertStringContainsString($expected, $source);
        }
    }
    public function test_production_write_api_routes_have_permission_middleware(): void
    {
        foreach ([
            'admin_api_createProduction',
            'admin_api_updateProduction',
            'admin_api_deleteProduction',
        ] as $routeName) {
            $this->assertTrue(Route::has($routeName), $routeName . ' API route is not registered.');
            $this->assertContains(
                'check.permission:admin',
                Route::getRoutes()->getByName($routeName)->gatherMiddleware(),
                $routeName . ' API route is missing admin permission middleware.'
            );
        }
    }

    public function test_production_controller_exposes_symbol_maintenance_actions(): void
    {
        $source = file_get_contents(app_path('Http/Controllers/Admin/ProductionController.php')) ?: '';

        foreach ([
            'public function createProduction',
            'public function updateProduction',
            'public function deleteProduction',
            'SymbolPrice::create',
            'SymbolPrice::findOrFail',
            "'symbol' => ['required', 'string', 'max:16'",
            "'group_id' => ['required', 'integer'",
            "'status' => ['required', 'integer'",
            "'modify_time' => ['nullable', 'date'",
        ] as $expected) {
            $this->assertStringContainsString($expected, $source);
        }
    }

    public function test_production_permission_migration_declares_write_permissions(): void
    {
        $source = file_get_contents(database_path('migrations/2026_06_07_000010_add_admin_production_permissions.php')) ?: '';

        foreach ([
            'admin_production_create',
            'admin_production_update',
            'admin_production_delete',
            'admin_api_createProduction',
            'admin_api_updateProduction',
            'admin_api_deleteProduction',
        ] as $expected) {
            $this->assertStringContainsString($expected, $source);
        }
    }

    public function test_production_ui_surfaces_expose_symbol_maintenance_actions(): void
    {
        $layui = file_get_contents(resource_path('admin/layui/productions/index.blade.php')) ?: '';
        $layuiJs = file_get_contents(public_path('js/apps/admin/layui/pages.js')) ?: '';
        $crmui = file_get_contents(app_path('Http/Controllers/CrmUi/Admin/PageController.php')) ?: '';

        foreach ([
            'id="openProductionCreate"',
            'id="productionFormModal"',
            'id="productionForm"',
            'id="exportProductions"',
            'data-permission="admin_production_export"',
            'name="symbol"',
            'name="bid"',
            'name="ask"',
            'name="group_id"',
        ] as $expected) {
            $this->assertStringContainsString($expected, $layui);
        }

        foreach ([
            '/api/admin/createProduction',
            '/api/admin/updateProduction',
            '/api/admin/deleteProduction',
            '/api/admin/exportProductions',
            "table.on('tool(productionTable)'",
        ] as $expected) {
            $this->assertStringContainsString($expected, $layuiJs);
        }

        $this->assertStringContainsString("exportActions('admin_api_exportProductions', 'productions_export.csv')", $crmui);
        $this->assertStringContainsString("'formApi' => 'admin_api_createProduction'", $crmui);
        $this->assertStringContainsString("'route' => 'admin_api_updateProduction'", $crmui);
        $this->assertStringContainsString("'route' => 'admin_api_deleteProduction'", $crmui);

    }

    public function test_production_write_apis_create_operation_logs(): void
    {
        $admin = Admin::query()->find(1) ?: (Admin::query()->first() ?: Admin::factory()->create());
        $symbol = 'AUDIT' . random_int(100000, 999999);
        $now = date('Y-m-d H:i:s');

        $createResponse = $this->withoutMiddleware([AdminAuthenticate::class, JwtAuthMiddleware::class, SingleSignOn::class, CheckPermission::class])
            ->actingAs($admin, 'admin')
            ->post('/api/admin/createProduction', [
                'symbol' => $symbol,
                'time' => $now,
                'bid' => 1.11,
                'ask' => 1.13,
                'low' => 1.10,
                'high' => 1.20,
                'direction' => 0,
                'digits' => 4,
                'spread' => 0.02,
                'group_id' => 66,
                'status' => 1,
                'modify_time' => $now,
            ]);

        $createResponse->assertOk();
        $productionId = (int) $createResponse->json('data.id');

        $createLog = DB::table('operation_logs')
            ->where('admin_id', $admin->id)
            ->where('order_no', 'production:' . $productionId)
            ->where('content', 'LIKE', '%Create production%')
            ->orderByDesc('id')
            ->first();

        $this->assertNotNull($createLog, 'createProduction must create an operation_logs audit record.');
        $this->assertSame($admin->username, $createLog->admin_name);
        $this->assertNull($createLog->target_user_id);
        $this->assertSame(0, (int) $createLog->action_type);
        $this->assertNotSame('', (string) $createLog->ip);
        $this->assertStringContainsString('symbol:' . $symbol, $createLog->content);
        $this->assertStringContainsString('group_id:66', $createLog->content);
        $this->assertStringContainsString('status:1', $createLog->content);

        $updateResponse = $this->withoutMiddleware([AdminAuthenticate::class, JwtAuthMiddleware::class, SingleSignOn::class, CheckPermission::class])
            ->actingAs($admin, 'admin')
            ->post('/api/admin/updateProduction/' . $productionId, [
                'symbol' => $symbol,
                'time' => $now,
                'bid' => 1.22,
                'ask' => 1.24,
                'low' => 1.21,
                'high' => 1.30,
                'direction' => 0,
                'digits' => 4,
                'spread' => 0.02,
                'group_id' => 67,
                'status' => 0,
                'modify_time' => $now,
            ]);

        $updateResponse->assertOk();

        $updateLog = DB::table('operation_logs')
            ->where('admin_id', $admin->id)
            ->where('order_no', 'production:' . $productionId)
            ->where('content', 'LIKE', '%Update production%')
            ->orderByDesc('id')
            ->first();

        $this->assertNotNull($updateLog, 'updateProduction must create an operation_logs audit record.');
        $this->assertStringContainsString('symbol:' . $symbol, $updateLog->content);
        $this->assertStringContainsString('bid:1.11->1.22', $updateLog->content);
        $this->assertStringContainsString('ask:1.13->1.24', $updateLog->content);
        $this->assertStringContainsString('group_id:66->67', $updateLog->content);
        $this->assertStringContainsString('status:1->0', $updateLog->content);

        $deleteResponse = $this->withoutMiddleware([AdminAuthenticate::class, JwtAuthMiddleware::class, SingleSignOn::class, CheckPermission::class])
            ->actingAs($admin, 'admin')
            ->post('/api/admin/deleteProduction/' . $productionId);

        $deleteResponse->assertOk();

        $deleteLog = DB::table('operation_logs')
            ->where('admin_id', $admin->id)
            ->where('order_no', 'production:' . $productionId)
            ->where('content', 'LIKE', '%Delete production%')
            ->orderByDesc('id')
            ->first();

        $this->assertNotNull($deleteLog, 'deleteProduction must create an operation_logs audit record.');
        $this->assertStringContainsString('symbol:' . $symbol, $deleteLog->content);
        $this->assertStringContainsString('status:0', $deleteLog->content);
    }
}
