<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/07/04
 * Time: 15:35
 */

/**
 * AdminRightsSummaryManualConfirmModuleTest
 *
 * 文件功能：
 * - 验证权益汇总手动确认闭环：确认 API 路由与权限中间件、控制器逻辑声明、Blade 控件、Layui 脚本调用与权限迁移。
 * - 输入：路由、控制器、Blade/JS、迁移等项目源码文本；输出：PHPUnit 断言结果。
 * - 明确不负责：不覆盖 HTTP 运行时业务流与 MT4 真实网关同步（由集成与功能测试锁定）。
 */

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;
use Tests\Feature\Concerns\ReadsAggregatedLayuiScripts;

/**
 * 后台权益汇总手动确认契约测试。
 *
 * 功能逻辑说明：
 * - 旧项目 RightsSummaryController::ManualConfirmWithdrawOrdeposit 允许管理员把权益结算记录手动置为已处理。
 * - 新项目第一阶段不模拟 MT4 自动入出金，只补充可安全落库的手动确认入口。
 * - 手动确认必须仍然走 permissions.api_route + check.permission:admin，页面按钮也必须声明 data-permission。
 */
class AdminRightsSummaryManualConfirmModuleTest extends TestCase
{
    use ReadsAggregatedLayuiScripts;

    /**
     * 手动确认 API 必须注册在后台受保护路由组中，并挂载权限中间件。
     *
     * @return void
     */
    public function test_manual_confirm_api_route_is_registered_with_permission_middleware(): void
    {
        $this->assertTrue(
            Route::has('admin_api_manualConfirmRightsSettlement'),
            'admin_api_manualConfirmRightsSettlement API 路由未注册。'
        );

        $this->assertContains(
            'check.permission:admin',
            Route::getRoutes()->getByName('admin_api_manualConfirmRightsSettlement')->gatherMiddleware()
        );
    }

    /**
     * 控制器必须声明手动确认逻辑，并基于真实 rights_settlements 表更新待处理记录。
     *
     * @return void
     */
    public function test_rights_summary_controller_declares_manual_confirm_logic(): void
    {
        // $source：权益汇总控制器源码，用于确认手动确认不是只有前端按钮，没有后端落库逻辑。
        $source = file_get_contents(app_path('Http/Controllers/Admin/RightsSummaryController.php')) ?: '';

        $this->assertStringContainsString('manualConfirmRightsSettlement', $source);
        $this->assertStringContainsString('rights_settlements', $source);
        $this->assertStringContainsString('manual_confirm_reason', $source);
        $this->assertStringContainsString('rights_settlement_confirmed', $source);
        $this->assertStringContainsString('rights_settlement_only_pending', $source);
        $this->assertStringContainsString('AdminDataScopeService', $source);
    }

    /**
     * Blade 页面必须声明手动确认按钮模板和弹窗表单参数。
     *
     * @return void
     */
    public function test_rights_summary_blade_page_contains_manual_confirm_controls(): void
    {
        $source = file_get_contents(resource_path('admin/layui/rights-summary/index.blade.php')) ?: '';

        $this->assertStringContainsString('id="rightsSummaryActions"', $source);
        $this->assertStringContainsString('lay-event="manualConfirmRightsSettlement"', $source);
        $this->assertStringContainsString('data-permission="admin_rights_summary_manual_confirm"', $source);
        $this->assertStringContainsString('name="manual_confirm_reason"', $source);
    }

    /**
     * Layui 脚本必须调用手动确认 API，并在成功后刷新当前表格。
     *
     * @return void
     */
    public function test_rights_summary_layui_script_calls_manual_confirm_api(): void
    {
        $source = $this->adminLayuiScript('rights-summary/index.js');

        $this->assertStringContainsString('/api/admin/manualConfirmRightsSettlement/', $source);
        $this->assertStringContainsString('manualConfirmRightsSettlement', $source);
        $this->assertStringContainsString('rightsSummaryTable', $source);
    }

    /**
     * 权限迁移必须声明手动确认动作权限，并绑定到后台 API 命名路由。
     *
     * @return void
     */
    public function test_rights_summary_permission_migration_declares_manual_confirm_permission(): void
    {
        $source = file_get_contents(database_path('migrations/2026_06_07_000007_add_admin_rights_summary_permissions.php')) ?: '';

        $this->assertStringContainsString('admin_rights_summary_manual_confirm', $source);
        $this->assertStringContainsString('admin_api_manualConfirmRightsSettlement', $source);
    }
}
