<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/07/04
 * Time: 15:35
 */

/**
 * AdminRiskIpDetailModuleTest
 *
 * 文件功能：
 * - 验证异常 IP 登录明细模块：明细 API 路由与权限中间件、控制器基于真实表构建明细、Blade 视图、Layui 表格处理与权限迁移。
 * - 输入：路由、控制器、Blade/JS、迁移等项目源码文本；输出：PHPUnit 断言结果。
 * - 明确不负责：不覆盖 HTTP 运行时业务流与 MT4 真实网关同步（由集成与功能测试锁定）。
 */

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;
use Tests\Feature\Concerns\ReadsAggregatedLayuiScripts;

/**
 * 后台风控异常 IP 详情迁移契约测试。
 *
 * 测试目标：
 * - 旧项目 `FengXianManageController::fengXian_Ipaddress_detail` 可按登录 IP 展开账号明细。
 * - 新项目必须提供独立的 `riskIpDetail` API，并继续通过 `permissions.api_route` 做后台接口鉴权。
 * - 当前本地 MySQL 3307 不可达，本测试只验证源码、路由、权限迁移、Blade 和 JS 契约。
 */
class AdminRiskIpDetailModuleTest extends TestCase
{
    use ReadsAggregatedLayuiScripts;

    /**
     * 异常 IP 详情 API 必须注册并挂后台权限中间件。
     *
     * @return void
     */
    public function test_risk_ip_detail_api_route_is_registered_with_permission_middleware(): void
    {
        $this->assertTrue(Route::has('admin_api_riskIpDetail'), 'admin_api_riskIpDetail API 路由未注册。');
        $this->assertContains('check.permission:admin', Route::getRoutes()->getByName('admin_api_riskIpDetail')->gatherMiddleware());
    }

    /**
     * 风控控制器必须读取真实登录日志、用户资料和交易表生成 IP 详情。
     *
     * @return void
     */
    public function test_risk_controller_builds_ip_detail_from_real_tables(): void
    {
        // $source：风控控制器源码，用于约束详情接口不能依赖旧项目 system_login_log。
        $source = file_get_contents(app_path('Http/Controllers/Admin/RiskController.php')) ?: '';

        $this->assertStringContainsString('riskIpDetail', $source);
        $this->assertStringContainsString('baseRiskIpDetailQuery', $source);
        $this->assertStringContainsString('UserLoginLog::query()', $source);
        $this->assertStringContainsString('user_login_logs.login_ip', $source);
        $this->assertStringContainsString('user_infos.parent_id', $source);
        $this->assertStringContainsString('open_order_count', $source);
        $this->assertStringContainsString('closed_order_count', $source);
        $this->assertStringContainsString('total_deposit', $source);
        $this->assertStringContainsString('total_withdraw', $source);
    }

    /**
     * 风控页面必须包含异常 IP 详情按钮和详情表格容器。
     *
     * @return void
     */
    public function test_risk_blade_contains_ip_detail_view(): void
    {
        // $source：后台风控 Blade 页面源码。
        $source = file_get_contents(resource_path('admin/layui/risk/index.blade.php')) ?: '';

        $this->assertStringContainsString('id="riskIpActions"', $source);
        $this->assertStringContainsString('lay-event="ipDetail"', $source);
        $this->assertStringContainsString('data-permission="admin_risk_ip_detail"', $source);
        $this->assertStringContainsString('id="riskIpDetailDialog"', $source);
        $this->assertStringContainsString('id="riskIpDetailTable"', $source);
    }

    /**
     * 风控 JS 必须能从异常 IP 列表打开详情弹层并请求详情 API。
     *
     * @return void
     */
    public function test_risk_layui_script_handles_ip_detail_table(): void
    {
        // $source：后台风控 Layui 脚本源码。
        $source = $this->adminLayuiScript('risk/index.js');

        $this->assertStringContainsString('/api/admin/riskIpDetail', $source);
        $this->assertStringContainsString('riskIpDetailTable', $source);
        $this->assertStringContainsString('ipDetail', $source);
        $this->assertStringContainsString('login_ip', $source);
        $this->assertStringContainsString('open_order_count', $source);
        $this->assertStringContainsString('closed_order_count', $source);
        $this->assertStringContainsString('total_deposit', $source);
        $this->assertStringContainsString('total_withdraw', $source);
    }

    /**
     * 权限迁移必须声明异常 IP 详情接口权限。
     *
     * @return void
     */
    public function test_risk_permission_migration_declares_ip_detail_permission(): void
    {
        // $source：第二批后台模块权限迁移，异常 IP 详情仍挂在 admin_risk 页面权限下。
        $source = file_get_contents(database_path('migrations/2026_06_06_000005_add_admin_second_batch_module_permissions.php')) ?: '';

        $this->assertStringContainsString('admin_risk_ip_detail', $source);
        $this->assertStringContainsString('admin_api_riskIpDetail', $source);
    }
}
