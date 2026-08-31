<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/07/04
 * Time: 18:10
 */

/**
 * AdminSecondBatchModuleCoverageTest
 *
 * 文件功能：
 * - 验证第二批后台模块：页面路由与 Blade 外壳、API 路由注册，以及交易/风控控制器依赖的开仓/平仓查询作用域存在。
 * - 输入：路由、控制器、Blade/JS、迁移等项目源码文本；输出：PHPUnit 断言结果。
 * - 明确不负责：不覆盖 HTTP 运行时业务流与 MT4 真实网关同步（由集成与功能测试锁定）。
 */

namespace Tests\Feature;

use App\Models\UserTrade;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * 后台第二批业务模块覆盖测试。
 *
 * 测试目标：
 * - 继续推进 plan.md 中未 Blade 化的后台模块。
 * - 控制器已有的业务能力必须注册后台 API 路由，供 permissions.api_route 配置鉴权。
 * - 交易/风控控制器依赖的 UserTrade::open()/closed() 查询作用域必须存在。
 */
class AdminSecondBatchModuleCoverageTest extends TestCase
{
    /**
     * 第二批必须 Blade 化的后台模块。
     *
     * @return array<string, array{path:string, route:string, table:string, script:string}>
     */
    public static function bladeModuleProvider(): array
    {
        return [
            'vouchers' => ['/admin/vouchers', 'admin_page_vouchers', 'voucherTable', '/js/apps/admin/layui/vouchers/index.js'],
            'risk' => ['/admin/risk', 'admin_page_risk', 'riskTable', '/js/apps/admin/layui/risk/index.js'],
            'blacklist' => ['/admin/blacklist', 'admin_page_blacklist', 'blacklistTable', '/js/apps/admin/layui/blacklist/index.js'],
            'cancel-applies' => ['/admin/cancel-applies', 'admin_page_cancel_applies', 'cancelApplyTable', '/js/apps/admin/layui/cancel-applies/index.js'],
            'trades' => ['/admin/trades', 'admin_page_trades', 'tradeTable', '/js/apps/admin/layui/trades/index.js'],
            'big-agents' => ['/admin/big-agents', 'admin_page_big_agents', 'bigAgentTable', '/js/apps/admin/layui/big-agents/index.js'],
        ];
    }

    /**
     * 第二批模块需要注册的后台 API 路由。
     *
     * @return array<string, array{route:string}>
     */
    public static function apiRouteProvider(): array
    {
        return [
            'voucher-list' => ['admin_api_voucherList'],
            'voucher-approve' => ['admin_api_voucherApprove'],
            'voucher-reject' => ['admin_api_voucherReject'],
            'risk-positions' => ['admin_api_riskPositions'],
            'risk-margin-calls' => ['admin_api_riskMarginCalls'],
            'risk-force-close' => ['admin_api_riskForceClose'],
            'blacklist-list' => ['admin_api_blacklistList'],
            'blacklist-create' => ['admin_api_createBlacklist'],
            'blacklist-update' => ['admin_api_updateBlacklist'],
            'blacklist-delete' => ['admin_api_deleteBlacklist'],
            'cancel-apply-list' => ['admin_api_cancelApplyList'],
            'cancel-apply-approve' => ['admin_api_cancelApplyApprove'],
            'cancel-apply-reject' => ['admin_api_cancelApplyReject'],
            'trade-list' => ['admin_api_tradeList'],
            'trade-open' => ['admin_api_openPositions'],
            'trade-closed' => ['admin_api_closedPositions'],
            'trade-summary' => ['admin_api_tradeSummary'],
            'big-agent-list' => ['admin_api_bigAgentList'],
            'big-agent-create' => ['admin_api_createBigAgent'],
            'big-agent-update' => ['admin_api_updateBigAgent'],
            'big-agent-delete' => ['admin_api_deleteBigAgent'],
        ];
    }

    /**
     * 第二批后台业务模块必须注册独立 Blade 页面路由。
     *
     * @dataProvider bladeModuleProvider
     *
     * @param string $path 页面访问路径。
     * @param string $route 页面路由名称。
     * @param string $table 页面主表格 DOM ID。
     * @param string $script 页面专属 JS 路径。
     * @return void
     */
    public function test_second_batch_module_routes_are_registered(string $path, string $route, string $table, string $script): void
    {
        $this->assertTrue(Route::has($route), $route . ' 页面路由未注册。');
    }

    /**
     * 第二批后台业务模块必须由 Blade 外壳渲染并加载模块 JS。
     *
     * @dataProvider bladeModuleProvider
     *
     * @param string $path 页面访问路径。
     * @param string $route 页面路由名称。
     * @param string $table 页面主表格 DOM ID。
     * @param string $script 页面专属 JS 路径。
     * @return void
     */
    public function test_second_batch_module_pages_render_blade_shell(string $path, string $route, string $table, string $script): void
    {
        $response = $this->get($path);

        $response->assertOk();
        $response->assertSee('crm-admin-workbench', false);
        $response->assertSee('data-render-mode="blade"', false);
        $response->assertSee('id="' . $table . '"', false);
        $module = preg_replace('#^/js/apps/admin/layui/(.+)\.js$#', '$1', $script);
        $response->assertSee('/js/apps/admin/layui/pages.js', false);
        $response->assertSee("data-layui-page=\"" . $module . "\"", false);
    }

    /**
     * 第二批控制器已有能力必须注册后台 API 路由，供权限表按 api_route 配置鉴权。
     *
     * @dataProvider apiRouteProvider
     *
     * @param string $route 后台 API 命名路由。
     * @return void
     */
    public function test_second_batch_api_routes_are_registered(string $route): void
    {
        $this->assertTrue(Route::has($route), $route . ' API 路由未注册。');
        $this->assertContains('check.permission:admin', Route::getRoutes()->getByName($route)->gatherMiddleware());
    }

    /**
     * 交易和风控控制器依赖的开仓/平仓查询作用域必须存在。
     *
     * @return void
     */
    public function test_user_trade_has_open_and_closed_scopes(): void
    {
        $this->assertTrue(method_exists(UserTrade::class, 'scopeOpen'));
        $this->assertTrue(method_exists(UserTrade::class, 'scopeClosed'));
    }
}
