<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/28
 * Time: 01:53
 */

namespace Tests\Feature;

use Tests\TestCase;

/**
 * 本轮改造过的后台页面渲染冒烟测试。
 *
 * 文件目的：
 * - 需求 8/9/12/16 都改了 Blade 结构，本文件确认这些页面仍能被服务端正常渲染，
 *   并且新增的容器、页签与折叠开关真的出现在最终 HTML 里（不是只存在于模板源码）。
 * - 与静态断言互补：静态断言看模板文本，本文件看 Blade 编译后的真实输出。
 */
class AdminModifiedPagesRenderSmokeTest extends TestCase
{
    /**
     * 页面渲染断言清单。
     *
     * @return array<string, array{uri:string, needles:array<int, string>}>
     */
    public static function modifiedPageProvider(): array
    {
        return [
            'channels' => ['uri' => '/admin/channels', 'needles' => [
                'class="layui-tab',
                'lay-filter="channelTabs"',
                'data-channel-status-input',
                'crm-admin-stats-block',
                'id="addChannel"',
                'id="channelModal"',
            ]],
            'realtime-commissions' => ['uri' => '/admin/realtime-commissions', 'needles' => [
                'id="realtimeCommissionSummary"',
                'crm-admin-stats-block',
                'id="realtimeCommissionCharts"',
                'crm-collapse-toggle',
                'aria-expanded="false"',
                'crm-collapse-chevron',
                'id="rebateRecordsChart"',
                '/js/vendor/echarts/echarts.common.min.js',
            ]],
            'position-summary' => ['uri' => '/admin/position-summary', 'needles' => [
                'id="positionSummaryChain"',
                'data-position-chain',
                'data-position-chain-nodes',
                'id="positionSummaryCards"',
                'crm-admin-stats-block',
            ]],
            'deposits' => ['uri' => '/admin/deposits', 'needles' => [
                'id="depositSummaryCards"',
                'crm-admin-stats-block',
                'data-summary-field="total_deposit_amount"',
            ]],
            'withdrawals' => ['uri' => '/admin/withdrawals', 'needles' => [
                'id="withdrawSummaryCards"',
                'crm-admin-stats-block',
                'data-summary-field="total_withdraw_amount"',
            ]],
            'trades' => ['uri' => '/admin/trades', 'needles' => [
                'id="tradeSummaryCards"',
                'crm-admin-stats-block',
            ]],
            'risk' => ['uri' => '/admin/risk', 'needles' => [
                'id="riskSummaryCards"',
                'crm-admin-stats-block',
            ]],
            'rights-summary' => ['uri' => '/admin/rights-summary', 'needles' => [
                'id="rightsSummaryCards"',
                'crm-admin-stats-block',
            ]],
            'productions' => ['uri' => '/admin/productions', 'needles' => [
                'id="productionSummaryCards"',
                'crm-admin-stats-block',
            ]],
            'agents' => ['uri' => '/admin/agents', 'needles' => [
                'id="agentTable"',
            ]],
        ];
    }

    /**
     * @dataProvider modifiedPageProvider
     * @param string $uri 页面路径。
     * @param array<int, string> $needles 必须出现在渲染结果里的片段。
     * @return void
     */
    public function test_modified_admin_pages_render_with_their_new_containers(string $uri, array $needles): void
    {
        $html = $this->get($uri)->assertOk()->getContent();

        foreach ($needles as $needle) {
            $this->assertStringContainsString($needle, $html, $uri . ' 渲染结果缺少：' . $needle);
        }

        // 统计区块必须落在表格所在 layui-card 之后，确保视觉上独立于表格。
        if (in_array('crm-admin-stats-block', $needles, true)) {
            $this->assertStringContainsString('crm-admin-stats-heading', $html, $uri . ' 统计区块缺少标题区。');
        }
    }

    /**
     * 代理管理页面自身的内容区不允许出现登录历史（需求 14）。
     *
     * 断言范围说明：
     * - 只截取代理管理自己的内容区（筛选表单到页面标记之间）。
     * - 后台布局壳会输出全站命名路由清单（crm-routes-manifest），里面包含前台代理模块
     *   仍在使用的 loginHistorySearch 路由；那是共享路由字典，不是代理管理页的展示内容，
     *   因此不能把整页 HTML 当作断言范围，否则会把布局壳误判成业务展示。
     *
     * @return void
     */
    public function test_agent_page_content_region_contains_no_login_history(): void
    {
        $html = $this->get('/admin/agents')->assertOk()->getContent();

        // 内容区 = 代理筛选表单 .. 代理佣金弹窗提交按钮；再往后就是布局壳的页脚与脚本区。
        $start = strpos($html, 'id="agentSearchForm"');
        $end = strpos($html, 'lay-filter="saveAgentCommissionUpdate"');
        $this->assertNotFalse($start, '未找到代理管理内容区起点。');
        $this->assertNotFalse($end, '未找到代理管理内容区终点。');
        $this->assertGreaterThan($start, $end);

        $content = substr($html, $start, $end - $start);

        foreach (['last_login_ip', 'last_login_at', 'loginHistory', 'login_history', '登录历史', '登录记录'] as $needle) {
            $this->assertStringNotContainsString($needle, $content, '代理管理内容区出现登录历史：' . $needle);
        }
    }
}
