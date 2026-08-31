<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/18
 * Time: 23:32
 */

/**
 * AdminRiskIpModuleTest
 *
 * 文件功能：
 * - 验证异常 IP 风控模块：IP 风控 API 路由与权限中间件、控制器基于 user_login_logs、Blade 视图、Layui 表格处理与权限迁移。
 * - 输入：路由、控制器、Blade/JS、迁移等项目源码文本；输出：PHPUnit 断言结果。
 * - 明确不负责：不覆盖 HTTP 运行时业务流与 MT4 真实网关同步（由集成与功能测试锁定）。
 */

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;
use Tests\Feature\Concerns\ReadsAggregatedLayuiScripts;

/**
 * 后台风控异常 IP 第一阶段迁移契约测试。
 *
 * 测试目标：
 * - 旧项目 `FengXianManageController::fengXian_Ipaddress_list` 用于发现同一 IP 登录多个账号的风险。
 * - 新项目当前真实表为 `user_login_logs`，第一阶段必须基于该表实现异常 IP 只读列表。
 * - 当前开发环境 MySQL 3307 不可达，本测试只验证源码、路由、权限迁移、Blade 和 JS 契约。
 */
class AdminRiskIpModuleTest extends TestCase
{
    use ReadsAggregatedLayuiScripts;

    /**
     * 异常 IP 风控 API 必须注册并进入后台权限中间件组。
     *
     * @return void
     */
    public function test_risk_ip_api_route_is_registered_with_permission_middleware(): void
    {
        $this->assertTrue(Route::has('admin_api_riskIpList'), 'admin_api_riskIpList API 路由未注册。');
        $this->assertContains('check.permission:admin', Route::getRoutes()->getByName('admin_api_riskIpList')->gatherMiddleware());
    }

    /**
     * 风控控制器必须读取真实用户登录日志表并返回 records + summary。
     *
     * @return void
     */
    public function test_risk_controller_uses_user_login_logs_for_ip_risk(): void
    {
        // $source：风控控制器源码，用于约束异常 IP 风控不能依赖旧项目 system_login_log。
        $source = file_get_contents(app_path('Http/Controllers/Admin/RiskController.php')) ?: '';

        $this->assertStringContainsString('use App\Models\UserLoginLog;', $source);
        $this->assertStringContainsString('riskIpList', $source);
        $this->assertStringContainsString('UserLoginLog::query()', $source);
        $this->assertStringContainsString('user_login_logs.login_ip', $source);
        $this->assertStringContainsString('distinct_user_count', $source);
        $this->assertStringContainsString('latest_login_at', $source);
        $this->assertStringContainsString('summaryFor', $source);
        $this->assertStringContainsString('records', $source);
        $this->assertStringContainsString('summary', $source);
    }

    /**
     * 风控页面必须包含异常 IP 表格和筛选字段。
     *
     * @return void
     */
    public function test_risk_blade_contains_ip_risk_view(): void
    {
        // $source：后台风控 Blade 页面源码。
        $source = file_get_contents(resource_path('admin/layui/risk/index.blade.php')) ?: '';

        $this->assertStringContainsString('id="riskIpTable"', $source);
        $this->assertStringContainsString("'ipRisk' =>", $source);
        $this->assertStringContainsString('data-mode="{{ $mode }}"', $source);
        $this->assertStringContainsString('name="login_ip"', $source);
        $this->assertStringContainsString('name="min_user_count"', $source);
    }

    /**
     * 风控 JS 必须渲染异常 IP 表格并请求 riskIpList 接口。
     *
     * @return void
     */
    public function test_risk_layui_script_handles_ip_risk_table(): void
    {
        // $source：后台风控 Layui 脚本源码。
        $source = $this->adminLayuiScript('risk/index.js');

        $this->assertStringContainsString('/api/admin/riskIpList', $source);
        $this->assertStringContainsString('riskIpTable', $source);
        $this->assertStringContainsString('ipRisk', $source);
        $this->assertStringContainsString('distinct_user_count', $source);
        $this->assertStringContainsString('login_ip', $source);
    }

    /**
     * 权限迁移必须声明异常 IP 风控接口权限。
     *
     * @return void
     */
    public function test_risk_permission_migration_declares_ip_risk_permission(): void
    {
        // $source：第二批后台模块权限迁移，风控新增接口仍挂在 admin_risk 页面权限下。
        $source = file_get_contents(database_path('migrations/2026_06_06_000005_add_admin_second_batch_module_permissions.php')) ?: '';

        $this->assertStringContainsString('admin_risk_ip_list', $source);
        $this->assertStringContainsString('admin_api_riskIpList', $source);
    }
}
