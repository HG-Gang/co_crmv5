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
 * 后台持仓汇总交易明细下钻闭环测试。
 *
 * 文件功能：
 * - 约束后台持仓汇总行必须可以跳转到交易订单页，并携带当前行用户与日期筛选。
 * - 约束交易订单页必须从 URL 查询参数初始化筛选表单和订单模式。
 * - 约束 CrmUI 后台同样提供本地交易下钻动作，避免只有 Layui 有入口。
 *
 * 返回结果：
 * - 测试通过表示页面入口、脚本参数链、CrmUI 动作声明和迁移文档证据已经闭环。
 * - 测试失败表示交易明细下钻仍是审计缺口，不能声称持仓汇总深层迁移完成。
 */
class AdminPositionSummaryTradeDetailDrilldownClosureModuleTest extends TestCase
{
    use ReadsAggregatedLayuiScripts;

    /**
     * Layui 持仓汇总必须提供交易明细下钻动作，并把当前筛选传给交易订单页。
     *
     * @return void
     */
    public function test_layui_position_summary_links_rows_to_trade_detail_filters(): void
    {
        $blade = file_get_contents(resource_path('admin/layui/position-summary/index.blade.php')) ?: '';
        $script = $this->adminLayuiScript('position-summary/index.js');
        $tradeBlade = $this->get('/admin/trades?user_id=9001&start_date=2026-07-01&end_date=2026-07-28&mode=closed')
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('positionSummaryTradeDetail', $script);
        $this->assertStringContainsString('lay-event="positionSummaryTradeDetail"', $script);
        $this->assertStringContainsString('admin_page_trades', $script);
        $this->assertStringContainsString('user_id: row.user_id', $script);
        $this->assertStringContainsString('start_date: filters.start_date', $script);
        $this->assertStringContainsString('end_date: filters.end_date', $script);
        $this->assertStringContainsString('mode: \'all\'', $script);
        $this->assertStringContainsString('positionSummaryTradeDetailUrl', $script);

        $this->assertStringContainsString('data-position-trade-detail-root', $blade);
        $this->assertStringContainsString('data-default-trade-user-id="9001"', $tradeBlade);
        $this->assertStringContainsString('data-default-trade-start-date="2026-07-01"', $tradeBlade);
        $this->assertStringContainsString('data-default-trade-end-date="2026-07-28"', $tradeBlade);
        $this->assertStringContainsString('data-default-trade-mode="closed"', $tradeBlade);
    }

    /**
     * 交易订单页必须读取 URL 默认筛选，确保从汇总行跳转后立即进入对应用户明细。
     *
     * @return void
     */
    public function test_trade_page_script_applies_default_query_filters_and_mode(): void
    {
        $script = $this->adminLayuiScript('trades/index.js');

        $this->assertStringContainsString('applyDefaultTradeQueryFilters', $script);
        $this->assertStringContainsString('data-default-trade-user-id', $script);
        $this->assertStringContainsString('currentTradeMode()', $script);
        $this->assertStringContainsString('currentApiUrl = tradeModeUrls[defaultMode] || tradeModeUrls.all', $script);
        $this->assertStringContainsString('table.reload(\'tradeTable\', {url: currentApiUrl, where: currentTradeFilters(), page: {curr: 1}})', $script);
    }

    /**
     * CrmUI 后台持仓汇总也必须声明交易明细下钻动作，并由本地脚本跳转到 CrmUI 交易页。
     *
     * @return void
     */
    public function test_crmui_position_summary_declares_trade_detail_drilldown_action(): void
    {
        $controller = file_get_contents(app_path('Http/Controllers/CrmUi/Admin/PageController.php')) ?: '';
        $script = file_get_contents(public_path('js/apps/crmui/admin.js')) ?: '';
        $audit = file_get_contents(base_path('docs/admin-legacy-migration-gap-audit.md')) ?: '';
        $checklist = file_get_contents(base_path('docs/admin-backend-blade-permission-final-checklist.md')) ?: '';

        $this->assertStringContainsString("'key' => 'position_summary_trades'", $controller);
        $this->assertStringContainsString("'local' => true", $controller);
        $this->assertStringContainsString("'extraPayload' => ['mode' => 'all']", $controller);
        $this->assertStringContainsString('position_summary_trades', $script);
        $this->assertStringContainsString('positionSummaryTradeDetail', $script);
        $this->assertStringContainsString('/admin-crmui/trades', $script);
        $this->assertStringContainsString('searchParams.set(\'user_id\'', $script);

        $this->assertStringContainsString('交易明细下钻已通过 `position_summary_trades`', $audit);
        $this->assertStringContainsString('AdminPositionSummaryTradeDetailDrilldownClosureModuleTest', $checklist);
    }
}
