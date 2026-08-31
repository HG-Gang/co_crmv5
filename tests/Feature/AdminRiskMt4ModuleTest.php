<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/07/29
 * Time: 02:02
 */

namespace Tests\Feature;

use Tests\TestCase;
use Tests\Feature\Concerns\ReadsAggregatedLayuiScripts;

/**
 * 后台风控 MT4 第一阶段迁移契约测试。
 *
 * 测试目标：
 * - 旧项目 `FengXianManageController` 的风控持仓读取 MT4_TRADES，并计算盈利风险值。
 * - 新项目当前真实表为 `mt4_trades`、`mt4_users`、`user_infos`，因此第一阶段必须基于这些真实表做只读风控列表。
 * - 本文件保留控制器、Blade 和 JS 的静态契约；账号映射运行时结果由 `AdminRiskTradeAccountMappingClosureModuleTest` 独立验证。
 */
class AdminRiskMt4ModuleTest extends TestCase
{
    use ReadsAggregatedLayuiScripts;

    /**
     * 风控控制器必须使用真实 MT4 表和后台数据范围服务。
     *
     * @return void
     */
    public function test_risk_controller_uses_mt4_tables_and_data_scope(): void
    {
        // $source：风控控制器源码，用于确认不再读取旧的 user_trades 占位模型。
        $source = file_get_contents(app_path('Http/Controllers/Admin/RiskController.php')) ?: '';

        $this->assertStringContainsString('use App\Models\Mt4Trade;', $source);
        $this->assertStringContainsString('use App\Models\Mt4User;', $source);
        $this->assertStringContainsString('use App\Models\UserInfo;', $source);
        $this->assertStringContainsString('use App\Services\AdminDataScopeService;', $source);
        $this->assertStringNotContainsString('use App\Models\UserTrade;', $source);
        $this->assertStringContainsString('Mt4Trade::query()', $source);
        $this->assertStringContainsString('Mt4User::query()', $source);
        $this->assertStringContainsString('applyDataScope', $source);
        $this->assertStringContainsString("on('user_infos.mt4_code', '=', 'mt4_trades.login')", $source);
        $this->assertStringContainsString("where('user_infos.user_id', (int) \$request->input('user_id'))", $source);
        $this->assertStringContainsString('$this->adminDataScopeService->apply($query, $admin, \'trade\', \'user_infos.user_id\');', $source);
        $this->assertStringNotContainsString("leftJoin('user_infos', 'user_infos.user_id', '=', 'mt4_trades.login')", $source);
    }

    /**
     * 风控接口不能继续返回空数组占位，必须返回 records + summary 结构。
     *
     * @return void
     */
    public function test_risk_controller_returns_records_and_summary_contract(): void
    {
        // $source：用于验证 positions 与 marginCalls 都有分页记录和汇总数据结构。
        $source = file_get_contents(app_path('Http/Controllers/Admin/RiskController.php')) ?: '';

        $this->assertStringContainsString('risk_value', $source);
        $this->assertStringContainsString('margin_level', $source);
        $this->assertStringContainsString('summaryFor', $source);
        $this->assertStringContainsString('paginateQuery', $source);
        $this->assertStringContainsString('records', $source);
        $this->assertStringContainsString('summary', $source);
        $this->assertStringNotContainsString('$riskUsers = [];', $source);
    }

    /**
     * 风控 Blade 页面必须提供风险持仓和追保预警两个表格容器。
     *
     * @return void
     */
    public function test_risk_blade_contains_position_and_margin_call_views(): void
    {
        // $source：后台风控 Blade 页面源码。
        $source = file_get_contents(resource_path('admin/layui/risk/index.blade.php')) ?: '';

        $this->assertStringContainsString('id="riskSummaryCards"', $source);
        $this->assertStringContainsString('id="riskSearchForm"', $source);
        $this->assertStringContainsString('id="riskTable"', $source);
        $this->assertStringContainsString('id="marginCallTable"', $source);
        $this->assertStringContainsString('name="user_id"', $source);
        $this->assertStringContainsString('name="ticket"', $source);
        $this->assertStringContainsString('name="symbol"', $source);
    }

    /**
     * 风控 JS 必须解析 records + summary，并能在风险持仓和追保预警之间切换。
     *
     * @return void
     */
    public function test_risk_layui_script_handles_summary_and_margin_calls(): void
    {
        // $source：后台风控 Layui 脚本源码。
        $source = $this->adminLayuiScript('risk/index.js');

        $this->assertStringContainsString('updateRiskSummaryCards', $source);
        $this->assertStringContainsString('response.data.records', $source);
        $this->assertStringContainsString('response.data.summary', $source);
        $this->assertStringContainsString('/api/admin/riskPositions', $source);
        $this->assertStringContainsString('/api/admin/riskMarginCalls', $source);
        $this->assertStringContainsString('marginCallTable', $source);
        $this->assertStringContainsString('risk_value', $source);
        $this->assertStringContainsString('margin_level', $source);
    }
}
