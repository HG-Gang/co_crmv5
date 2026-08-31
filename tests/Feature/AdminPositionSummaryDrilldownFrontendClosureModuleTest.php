<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/07/28
 * Time: 23:16
 */

/**
 * AdminPositionSummaryDrilldownFrontendClosureModuleTest
 *
 * 文件功能：
 * - 验证持仓汇总钻取前端契约：Layui 暴露旧代理钻取控件、CrmUI 声明本地钻取行动作。
 * - 输入：路由、控制器、Blade/JS、迁移等项目源码文本；输出：PHPUnit 断言结果。
 * - 明确不负责：不覆盖 HTTP 运行时业务流与 MT4 真实网关同步（由集成与功能测试锁定）。
 */

namespace Tests\Feature;

use Tests\Feature\Concerns\ReadsAggregatedLayuiScripts;
use Tests\TestCase;

/**
 * 后台持仓汇总代理钻取前端闭环测试。
 *
 * 文件目的：
 * - 约束旧后台 position_summary_list_v2.blade.php 中点击代理行继续查看直属下级汇总的交互。
 * - 新项目后端已经兼容 searchtype=subAgentsSearch 与 userPId/user_pid，本测试确保 Layui 与 CrmUI 前端也传递这些参数。
 */
class AdminPositionSummaryDrilldownFrontendClosureModuleTest extends TestCase
{
    use ReadsAggregatedLayuiScripts;

    /**
     * Layui 持仓汇总页必须提供代理行钻取、路径返回和表单隐藏参数。
     *
     * @return void
     */
    public function test_layui_position_summary_exposes_legacy_agent_drilldown_controls(): void
    {
        $blade = file_get_contents(resource_path('admin/layui/position-summary/index.blade.php')) ?: '';
        $script = $this->adminLayuiScript('position-summary/index.js');

        $this->assertStringContainsString('id="positionSummaryPath"', $blade);
        $this->assertStringContainsString('name="searchtype"', $blade);
        $this->assertStringContainsString('name="userPId"', $blade);
        $this->assertStringContainsString('data-position-drilldown-root', $blade);

        $this->assertStringContainsString('positionSummaryDrilldown', $script);
        $this->assertStringContainsString('searchtype: \'subAgentsSearch\'', $script);
        $this->assertStringContainsString('userPId: row.user_id', $script);
        $this->assertStringContainsString('positionSummaryPath', $script);
        $this->assertStringContainsString('table.on(\'tool(positionSummaryTable)\'', $script);
    }

    /**
     * CrmUI 兼容后台必须声明本地钻取动作，避免只剩后端能力而页面无法触发。
     *
     * @return void
     */
    public function test_crmui_position_summary_declares_local_drilldown_row_action(): void
    {
        $controller = file_get_contents(app_path('Http/Controllers/CrmUi/Admin/PageController.php')) ?: '';
        $script = file_get_contents(public_path('js/apps/crmui/admin.js')) ?: '';

        $this->assertStringContainsString("'key' => 'position_summary_drilldown'", $controller);
        $this->assertStringContainsString("'local' => true", $controller);
        $this->assertStringContainsString("'payloadName' => 'userPId'", $controller);
        $this->assertStringContainsString("'extraPayload' => ['searchtype' => 'subAgentsSearch']", $controller);

        $this->assertStringContainsString('position_summary_drilldown', $script);
        $this->assertStringContainsString('searchtype', $script);
        $this->assertStringContainsString('userPId', $script);
    }
}
