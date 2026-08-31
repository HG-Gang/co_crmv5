<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/07/31
 * Time: 22:21
 */

namespace Tests\Feature;

use Tests\Feature\Concerns\ReadsAggregatedLayuiScripts;
use Tests\TestCase;

/**
 * 后台持仓汇总风险联动闭环测试。
 *
 * 文件功能：
 * - 约束后台持仓汇总行必须可以跳转到风控中心，并携带当前行用户与日期筛选。
 * - 约束风控中心页面必须从 URL 查询参数初始化筛选表单和默认视图。
 * - 约束 CrmUI 后台同样提供本地风险联动动作，避免只有 Layui 入口可用。
 *
 * 返回结果：
 * - 测试通过表示页面入口、脚本参数链、CrmUI 动作声明和迁移文档证据已经闭环。
 * - 测试失败表示持仓汇总到风控中心仍缺少可追溯入口，不能声明该模块深层迁移完成。
 */
class AdminPositionSummaryRiskDrilldownClosureModuleTest extends TestCase
{
    use ReadsAggregatedLayuiScripts;

    /**
     * Layui 持仓汇总必须提供风险联动动作，并把当前筛选传给风控中心页面。
     *
     * @return void
     */
    public function test_layui_position_summary_links_rows_to_risk_filters(): void
    {
        $blade = file_get_contents(resource_path('admin/layui/position-summary/index.blade.php')) ?: '';
        $script = $this->adminLayuiScript('position-summary/index.js');
        $riskBlade = $this->get('/admin/risk?user_id=9001&start_date=2026-07-01&end_date=2026-07-28&mode=positions')
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('positionSummaryRiskDetail', $script);
        $this->assertStringContainsString('lay-event="positionSummaryRiskDetail"', $script);
        $this->assertStringContainsString('admin_page_risk', $script);
        $this->assertStringContainsString('user_id: row.user_id', $script);
        $this->assertStringContainsString('start_date: filters.start_date', $script);
        $this->assertStringContainsString('end_date: filters.end_date', $script);
        $this->assertStringContainsString('mode: \'positions\'', $script);
        $this->assertStringContainsString('positionSummaryRiskDetailUrl', $script);

        $this->assertStringContainsString('data-position-risk-detail-root', $blade);
        $this->assertStringContainsString('data-default-risk-user-id="9001"', $riskBlade);
        $this->assertStringContainsString('data-default-risk-start-date="2026-07-01"', $riskBlade);
        $this->assertStringContainsString('data-default-risk-end-date="2026-07-28"', $riskBlade);
        $this->assertStringContainsString('data-default-risk-mode="positions"', $riskBlade);
    }

    /**
     * 风控中心页面必须读取 URL 默认筛选，确保从汇总行跳转后立即进入对应用户风险视图。
     *
     * @return void
     */
    public function test_risk_page_script_applies_default_query_filters_and_mode(): void
    {
        $script = $this->adminLayuiScript('risk/index.js');

        $this->assertStringContainsString('applyDefaultRiskQueryFilters', $script);
        $this->assertStringContainsString('data-default-risk-user-id', $script);
        $this->assertStringContainsString('currentRiskMode()', $script);
        $this->assertStringContainsString('setMode(defaultMode)', $script);
        $this->assertStringContainsString('reloadCurrentTable(currentRiskFilters())', $script);
    }

    /**
     * CrmUI 后台持仓汇总也必须声明风险联动动作，并由本地脚本跳转到 CrmUI 风控页。
     *
     * @return void
     */
    public function test_crmui_position_summary_declares_risk_drilldown_action(): void
    {
        $controller = file_get_contents(app_path('Http/Controllers/CrmUi/Admin/PageController.php')) ?: '';
        $script = file_get_contents(public_path('js/apps/crmui/admin.js')) ?: '';
        $audit = file_get_contents(base_path('docs/admin-legacy-migration-gap-audit.md')) ?: '';
        $checklist = file_get_contents(base_path('docs/admin-backend-blade-permission-final-checklist.md')) ?: '';

        $this->assertStringContainsString("'key' => 'position_summary_risk'", $controller);
        $this->assertStringContainsString("'local' => true", $controller);
        $this->assertStringContainsString("'extraPayload' => ['mode' => 'positions']", $controller);
        $this->assertStringContainsString('position_summary_risk', $script);
        $this->assertStringContainsString('positionSummaryRiskDetail', $script);
        $this->assertStringContainsString('/admin-crmui/risk', $script);
        $this->assertStringContainsString('searchParams.set(\'user_id\'', $script);

        $this->assertStringContainsString('风险联动已通过 `position_summary_risk`', $audit);
        $this->assertStringContainsString('AdminPositionSummaryRiskDrilldownClosureModuleTest', $checklist);
    }
}
