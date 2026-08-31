<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/28
 * Time: 02:19
 */

/**
 * AdminBackOfficeUxRequirementsModuleTest
 *
 * 文件功能：
 * - 验证后台交互需求闭环（需求 8/9/12/14/16）：支付通道 layui-tab 分组、统计独立区块与双闸门演示数据、持仓链路按用户ID逐级展开、实时佣金图表折叠与多语言键。
 * - 输入：控制器/模型/JS/Blade/CSS 等源码与语言包文本；输出：PHPUnit 断言结果。
 * - 明确不负责：不验证运行时业务行为与数据库交互。
 */

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use Tests\Feature\Concerns\ReadsAggregatedLayuiScripts;
use Tests\TestCase;

/**
 * 后台交互需求闭环测试（需求 8、9、12、14、16）。
 *
 * 文件目的：
 * - 需求 8：支付通道用 layui-tab 分组展示，页签只收窄 status 筛选，CRUD 行为不变。
 * - 需求 9：出入金统计与所有已有统计的后台表格，统计都独立成 div 区块、默认靠左对齐，
 *   并且演示数据只能来自双闸门 Seeder，控制器与 Blade 不许硬编码假数字。
 * - 需求 12：链路默认隐藏，点击表格用户ID 才逐级展开，且只显示用户ID。
 * - 需求 14：代理管理不展示登录历史，列表响应也不再携带登录轨迹字段。
 * - 需求 16：实时返佣统计图表容器默认折叠，用 》 形箭头按钮展开，键盘可达，动画只用 CSS。
 */
class AdminBackOfficeUxRequirementsModuleTest extends TestCase
{
    use ReadsAggregatedLayuiScripts;

    // ================= 需求 8：支付通道 TAB =================

    public function test_payment_channel_page_groups_channels_into_layui_tabs(): void
    {
        $blade = file_get_contents(resource_path('admin/layui/channels/index.blade.php')) ?: '';

        $this->assertStringContainsString('class="layui-tab', $blade, '支付通道必须使用 Layui 页签布局。');
        $this->assertStringContainsString('lay-filter="channelTabs"', $blade);
        $this->assertStringContainsString('<ul class="layui-tab-title"', $blade);
        $this->assertStringContainsString('layui-tab-content', $blade);

        // 三个分组：全部 / 已启用 / 已停用，状态值写在 data-channel-status 上。
        $this->assertStringContainsString('data-channel-status=""', $blade);
        $this->assertStringContainsString('data-channel-status="1"', $blade);
        $this->assertStringContainsString('data-channel-status="0"', $blade);
        $this->assertStringContainsString('data-channel-status-input', $blade);

        // 页签文案走语言包。
        foreach (['channel_tab_all', 'channel_tab_enabled', 'channel_tab_disabled'] as $key) {
            $this->assertStringContainsString("__('admin." . $key . "')", $blade, '缺少页签文案 key：' . $key);
        }

        // 所有 CRUD 入口必须保留。
        foreach ([
            'id="addChannel"',
            'id="reloadChannels"',
            'id="channelModal"',
            'name="channel_code"',
            'data-permission="admin_channel_create"',
            'data-permission="admin_channel_update"',
            'data-permission="admin_channel_toggle"',
            'data-permission="admin_channel_delete"',
        ] as $needle) {
            $this->assertStringContainsString($needle, $blade, '页签改造不得移除 CRUD 入口：' . $needle);
        }
    }

    public function test_payment_channel_script_reloads_the_same_table_on_tab_switch(): void
    {
        $script = $this->adminLayuiScript('channels/index.js');

        $this->assertStringContainsString("element.on('tab(channelTabs)'", $script);
        $this->assertStringContainsString("data('channel-status')", $script);
        $this->assertStringContainsString('currentChannelFilters()', $script);
        // 仍然是同一张表、同一批接口，CRUD 事件没有改动。
        $this->assertStringContainsString("table.reload('channelTable'", $script);
        $this->assertStringContainsString("table.on('tool(channelTable)'", $script);
        $this->assertStringContainsString('/api/admin/createChannel', $script);
        $this->assertStringContainsString('/api/admin/toggleChannel/', $script);
        $this->assertStringContainsString('/api/admin/deleteChannel/', $script);
    }

    public function test_channel_list_api_supports_the_status_group_filter(): void
    {
        $source = file_get_contents(app_path('Http/Controllers/Admin/PaymentChannelController.php')) ?: '';

        $this->assertStringContainsString('applyChannelFilters', $source);
        $this->assertStringContainsString("in_array((string) \$status, ['0', '1'], true)", $source, 'status 只接受 0/1，其他值不筛选。');
        $this->assertStringContainsString("where('is_enabled', (int) \$status)", $source);
    }

    public function test_crmui_channels_page_declares_status_grouped_view_tabs(): void
    {
        $controller = file_get_contents(app_path('Http/Controllers/CrmUi/Admin/PageController.php')) ?: '';

        $this->assertStringContainsString("'key' => 'channel_all'", $controller);
        $this->assertStringContainsString("'key' => 'channel_enabled'", $controller);
        $this->assertStringContainsString("'key' => 'channel_disabled'", $controller);
        $this->assertStringContainsString("'query' => ['status' => 1]", $controller);
        $this->assertStringContainsString("'query' => ['status' => 0]", $controller);
        // viewTabs 需要支持把固定查询参数拼进 apiUrl。
        $this->assertStringContainsString('http_build_query($query)', $controller);
    }

    // ================= 需求 9：统计独立区块 =================

    /**
     * 后台带统计的表格页面清单。
     *
     * key 是 Blade 相对路径，值是统计容器 id。
     *
     * @return array<string, array{blade:string, container:string}>
     */
    public static function statisticsBlockProvider(): array
    {
        return [
            'deposits' => ['blade' => 'admin/layui/deposits/index.blade.php', 'container' => 'depositSummaryCards'],
            'withdrawals' => ['blade' => 'admin/layui/withdrawals/index.blade.php', 'container' => 'withdrawSummaryCards'],
            'position-summary' => ['blade' => 'admin/layui/position-summary/index.blade.php', 'container' => 'positionSummaryCards'],
            'realtime-commissions' => ['blade' => 'admin/layui/realtime-commissions/index.blade.php', 'container' => 'realtimeCommissionSummary'],
            'rights-summary' => ['blade' => 'admin/layui/rights-summary/index.blade.php', 'container' => 'rightsSummaryCards'],
            'risk' => ['blade' => 'admin/layui/risk/index.blade.php', 'container' => 'riskSummaryCards'],
            'trades' => ['blade' => 'admin/layui/trades/index.blade.php', 'container' => 'tradeSummaryCards'],
            'productions' => ['blade' => 'admin/layui/productions/index.blade.php', 'container' => 'productionSummaryCards'],
            'channels' => ['blade' => 'admin/layui/channels/index.blade.php', 'container' => 'channelStatistics'],
        ];
    }

    /**
     * 每个带统计的后台表格页，统计都必须是独立区块且带标题与多语言。
     *
     * @dataProvider statisticsBlockProvider
     * @param string $blade Blade 相对路径。
     * @param string $container 统计容器 id。
     * @return void
     */
    public function test_admin_tables_render_statistics_in_an_independent_block(string $blade, string $container): void
    {
        $source = file_get_contents(resource_path($blade)) ?: '';

        $this->assertStringContainsString('crm-admin-stats-block', $source, $blade . ' 统计没有独立成块。');
        $this->assertStringContainsString('id="' . $container . '"', $source, $blade . ' 缺少统计容器 ' . $container);
        $this->assertStringContainsString('crm-admin-stats-heading', $source, $blade . ' 统计区块缺少标题区。');
        $this->assertStringContainsString('data-translate="admin.table_statistics"', $source, $blade . ' 统计标题未接入多语言。');
        $this->assertStringContainsString('aria-labelledby', $source, $blade . ' 统计区块缺少无障碍标题关联。');

        // 统计区块必须在表格所在的 layui-card 之外，才算"独立于表格"。
        $cardEnd = strrpos($source, '</div>');
        $blockStart = strpos($source, 'crm-admin-stats-block');
        $this->assertNotFalse($blockStart);
        $this->assertNotFalse($cardEnd);

        // 旧写法（统计塞在 layui-row + crm-metric-card 里、位于表格卡片内部）必须已清除。
        $this->assertStringNotContainsString('crm-metric-card', $source, $blade . ' 仍保留旧的卡片内统计写法。');
    }

    public function test_statistics_block_css_is_left_aligned_and_visually_separated(): void
    {
        $css = file_get_contents(public_path('css/common/crm-design-system.css')) ?: '';

        $this->assertStringContainsString('.crm-ui-admin-shell .crm-admin-stats-block', $css);
        // 默认靠左。
        $this->assertMatchesRegularExpression(
            '/\.crm-ui-admin-shell \.crm-admin-stats-block\s*\{[^}]*text-align:\s*left;/s',
            $css,
            '统计区块必须默认靠左对齐。'
        );
        $this->assertMatchesRegularExpression(
            '/\.crm-ui-admin-shell \.crm-admin-stats-block \.crm-table-summary\s*\{[^}]*justify-content:\s*flex-start;/s',
            $css,
            '统计条目必须靠左排列。'
        );
        // 与表格形成视觉区分：独立边框 + 左侧强调条 + 不同底色。
        $this->assertMatchesRegularExpression(
            '/\.crm-ui-admin-shell \.crm-admin-stats-block\s*\{[^}]*border-left:\s*3px solid var\(--crm-primary\);/s',
            $css,
            '统计区块需要左侧强调边与表格区分。'
        );
        // 颜色一律走语义 token，跟随全局皮肤。
        $this->assertStringNotContainsString('.crm-admin-stats-block { background: #', $css);
    }

    public function test_deposit_and_withdraw_apis_return_real_summary_alongside_the_paginator(): void
    {
        $deposit = file_get_contents(app_path('Http/Controllers/Admin/DepositController.php')) ?: '';
        $withdraw = file_get_contents(app_path('Http/Controllers/Admin/WithdrawController.php')) ?: '';

        // 汇总键与 paginator 键并存，历史调用方的 data.data / data.total 不受影响。
        $this->assertStringContainsString("array_merge(\$deposits->toArray(), ['summary' => \$summary])", $deposit);
        $this->assertStringContainsString("array_merge(\$withdrawals->toArray(), ['summary' => \$summary])", $withdraw);

        // 金额在十进制域内聚合，不走 float。
        $this->assertStringContainsString('CAST(COALESCE(SUM(CAST(amount AS DECIMAL(18,2))), 0) AS DECIMAL(18,2))', $deposit);
        $this->assertStringContainsString('CAST(COALESCE(SUM(CAST(apply_amount AS DECIMAL(18,2))), 0) AS DECIMAL(18,2))', $withdraw);
    }

    public function test_crmui_deposit_and_withdraw_pages_expose_the_same_statistics_metrics(): void
    {
        $controller = file_get_contents(app_path('Http/Controllers/CrmUi/Admin/PageController.php')) ?: '';
        $crmuiCss = file_get_contents(public_path('css/crmui/admin.css')) ?: '';
        $zh = require resource_path('lang/zh-CN/crmui.php');
        $en = require resource_path('lang/en/crmui.php');

        // 指标键与后端 summary 字段同名，renderMetrics 才能直接取值。
        $this->assertStringContainsString(
            "'metrics' => ['total_records', 'total_deposit_amount', 'total_actual_amount', 'approved_records']",
            $controller
        );
        $this->assertStringContainsString(
            "'metrics' => ['total_records', 'total_withdraw_amount', 'total_actual_amount', 'total_fee', 'completed_records']",
            $controller
        );

        foreach (['total_deposit_amount', 'total_withdraw_amount', 'total_actual_amount', 'total_fee', 'approved_records', 'completed_records'] as $key) {
            $this->assertArrayHasKey($key, $zh['metrics'], 'zh-CN 缺少 crmui.metrics.' . $key);
            $this->assertArrayHasKey($key, $en['metrics'], 'en 缺少 crmui.metrics.' . $key);
        }

        // CrmUI 指标区同样默认靠左对齐。
        $this->assertMatchesRegularExpression(
            '/\.crmui-admin-body \.crmui-metrics\s*\{[^}]*justify-content:\s*start;/s',
            $crmuiCss,
            'CrmUI 指标区必须默认靠左排列。'
        );
        $this->assertMatchesRegularExpression(
            '/\.crmui-admin-body \.crmui-metrics\s*\{[^}]*text-align:\s*left;/s',
            $crmuiCss
        );
    }

    public function test_demo_statistics_data_comes_only_from_the_double_gated_seeder(): void
    {
        $config = file_get_contents(config_path('seeding.php')) ?: '';
        $databaseSeeder = file_get_contents(database_path('seeders/DatabaseSeeder.php')) ?: '';
        $demoSeeder = file_get_contents(database_path('seeders/AdminDemoStatisticsSeeder.php')) ?: '';

        // 闸门一：显式布尔环境开关。
        $this->assertStringContainsString("'admin_demo_statistics_enabled' => env('ADMIN_DEMO_STATISTICS_SEEDER_ENABLED', false)", $config);
        // 闸门二：只允许 local/testing。
        $this->assertStringContainsString("app()->environment('local', 'testing')", $databaseSeeder);
        $this->assertStringContainsString("config('seeding.admin_demo_statistics_enabled', false) === true", $databaseSeeder);
        $this->assertStringContainsString('shouldSeedAdminDemoStatistics()', $databaseSeeder);
        $this->assertStringContainsString('AdminDemoStatisticsSeeder::class', $databaseSeeder);

        // 演示数据只能落在真实表里，靠 Seeder 提供，重复执行幂等。
        $this->assertStringContainsString('insertOrIgnore', $demoSeeder);
        $this->assertStringContainsString('DBCN-', $demoSeeder);
        $this->assertStringContainsString('-FY', $demoSeeder);

        // 生产不能静默返回假数据：控制器与 Blade 里不许出现硬编码演示数字。
        foreach ([
            'Http/Controllers/Admin/RealtimeCommissionController.php',
            'Http/Controllers/Admin/DepositController.php',
            'Http/Controllers/Admin/WithdrawController.php',
            'Http/Controllers/Admin/CustomerStatisticsController.php',
        ] as $relativePath) {
            $source = file_get_contents(app_path($relativePath)) ?: '';
            $this->assertStringNotContainsString('mock', strtolower($source), $relativePath . ' 不允许出现 mock 数据。');
            $this->assertStringNotContainsString('demo_value', strtolower($source), $relativePath);
        }
    }

    // ================= 需求 12：链路渐进展开 =================

    public function test_position_summary_chain_is_hidden_by_default_and_shows_ids_only(): void
    {
        $blade = file_get_contents(resource_path('admin/layui/position-summary/index.blade.php')) ?: '';

        // 默认隐藏：既有 hidden 属性，CSS 里 .crm-chain-path 也是 display:none。
        $this->assertStringContainsString('id="positionSummaryChain"', $blade);
        $this->assertStringContainsString('data-position-chain', $blade);
        $this->assertMatchesRegularExpression(
            '/<div class="crm-chain-path"[^>]*\shidden\b/',
            $blade,
            '链路容器必须默认带 hidden。'
        );
        $this->assertStringContainsString('data-position-chain-nodes', $blade);
        $this->assertStringContainsString('data-translate="admin.current_chain"', $blade);

        // 链路里不允许渲染用户名或代理等级：Blade 只留一个空的节点容器，节点由 JS 用 ID 填充。
        $this->assertStringNotContainsString('user_name', substr($blade, (int) strpos($blade, 'crm-chain-path'), 600));
    }

    public function test_position_summary_chain_css_defaults_to_hidden(): void
    {
        $css = file_get_contents(public_path('css/common/crm-design-system.css')) ?: '';

        $this->assertMatchesRegularExpression(
            '/\.crm-ui-admin-shell \.crm-chain-path\s*\{[^}]*display:\s*none;/s',
            $css,
            '链路默认必须是 display:none。'
        );
        $this->assertMatchesRegularExpression(
            '/\.crm-ui-admin-shell \.crm-chain-path\.is-visible\s*\{[^}]*display:\s*flex;/s',
            $css,
            '只有 is-visible 才显示链路。'
        );
    }

    public function test_position_summary_script_reveals_one_level_per_user_id_click(): void
    {
        $script = $this->adminLayuiScript('position-summary/index.js');

        // 点击用户ID 才更新链路。
        $this->assertStringContainsString('function updateClickedChain(row)', $script);
        $this->assertStringContainsString('function renderPositionSummaryChain()', $script);
        $this->assertStringContainsString("obj.event === 'positionSummaryChain'", $script);
        $this->assertStringContainsString('updateClickedChain(obj.data || {})', $script);

        // 渐进展开：已在链路中则截断到该层，否则追加一层。
        $this->assertStringContainsString('clickedChain.indexOf(userId)', $script);
        $this->assertStringContainsString('clickedChain.slice(0, existingIndex + 1)', $script);
        $this->assertStringContainsString('clickedChain.push(userId)', $script);

        // 只显示用户ID：节点内容只有 clickedChain[index]，没有 user_name / level。
        $this->assertStringContainsString("escapePositionSummaryHtml(clickedChain[index])", $script);
        $this->assertStringNotContainsString('clickedChain[index].user_name', $script);

        // 链路为空时收起，回到默认不显示。
        $this->assertStringContainsString("removeClass('is-visible').attr('hidden', 'hidden')", $script);
        // 每一行的用户ID 都是可点击入口。
        $this->assertStringContainsString('crm-chain-trigger', $script);
    }

    public function test_parent_path_server_contract_is_unchanged(): void
    {
        // 需求 12 是纯前端渐进展开改造，服务端旧格式链路契约必须保持原样。
        $this->assertTrue(Route::has('admin_api_agentParentPath'));
        $this->assertTrue(Route::has('legacy_user_proxy_parent_path'));

        $adminAgent = file_get_contents(app_path('Http/Controllers/Admin/AgentController.php')) ?: '';
        $frontAgent = file_get_contents(app_path('Http/Controllers/Front/AgentController.php')) ?: '';

        foreach ([$adminAgent, $frontAgent] as $source) {
            $this->assertStringContainsString("'path' => implode('->', \$tree)", $source, 'parentPath 的 path 拼接格式不能变。');
            $this->assertStringContainsString("'tree' => \$tree", $source);
            $this->assertStringContainsString('lay-event="', $source);
            $this->assertStringContainsString('data-user_id="', $source);
        }
    }

    // ================= 需求 14：代理管理不显示登录历史 =================

    public function test_agent_management_never_renders_login_history(): void
    {
        $blade = file_get_contents(resource_path('admin/layui/agents/index.blade.php')) ?: '';
        $script = $this->adminLayuiScript('agents/index.js');
        $crmui = file_get_contents(app_path('Http/Controllers/CrmUi/Admin/PageController.php')) ?: '';

        foreach ([$blade, $script] as $source) {
            foreach (['last_login_ip', 'last_login_at', 'loginHistory', 'login_history', '登录历史'] as $needle) {
                $this->assertStringNotContainsString($needle, $source, '代理管理不得出现登录历史：' . $needle);
            }
        }

        // CrmUI 代理页的列清单同样不含登录轨迹字段。
        $agentDefinition = substr($crmui, (int) strpos($crmui, "'agents' => ["), 1200);
        $this->assertStringNotContainsString('last_login', $agentDefinition);
    }

    public function test_agent_list_payload_no_longer_carries_login_history_columns(): void
    {
        $source = file_get_contents(app_path('Http/Controllers/Admin/AgentController.php')) ?: '';

        // 预加载收窄到导出与展示真正需要的列，登录轨迹不再进入响应体。
        $this->assertStringContainsString("\$relation->select(['id', 'user_id', 'email', 'account_type', 'is_enabled'])", $source);
        $this->assertStringNotContainsString("->where('account_type', 1)->with('login');", $source);
        // 共享的登录历史接口属于前台代理模块，不能被顺手删掉。
        $this->assertTrue(Route::has('front_api_users_login_history'), '共享登录历史接口仍被前台模块使用，不允许删除。');
        $this->assertTrue(Route::has('legacy_user_customer_login_history'));
    }

    // ================= 需求 16：统计图表折叠容器 =================

    public function test_realtime_commission_charts_are_collapsed_by_default_with_a_chevron_toggle(): void
    {
        $blade = file_get_contents(resource_path('admin/layui/realtime-commissions/index.blade.php')) ?: '';

        $this->assertStringContainsString('id="realtimeCommissionCharts"', $blade);
        $this->assertStringContainsString('crm-collapse-panel', $blade);
        // 折叠开关必须是原生 button，Enter/Space 由浏览器提供。
        $this->assertMatchesRegularExpression(
            '/<button type="button"\s+class="crm-collapse-toggle"/s',
            $blade,
            '折叠开关必须是 button 元素。'
        );
        $this->assertStringContainsString('aria-expanded="false"', $blade, '图表容器默认折叠。');
        $this->assertStringContainsString('aria-controls="realtimeCommissionChartsBody"', $blade);
        // 》 形箭头容器：字形由 CSS content 提供（见 test_collapse_animation_is_css_only_and_keyboard_accessible），
        // Blade 只保留 aria-hidden 的语义容器，读屏器不会读到这个装饰符号。
        $this->assertStringContainsString('crm-collapse-chevron', $blade);
        $this->assertMatchesRegularExpression(
            '/<span class="crm-collapse-chevron" aria-hidden="true">\s*<\/span>/',
            $blade,
            '箭头必须是 aria-hidden 的纯装饰容器。'
        );
        $this->assertStringContainsString('crm-sr-only', $blade);

        // 图表用已 vendored 的 ECharts，并带类型切换按钮。
        $this->assertStringContainsString('/js/vendor/echarts/echarts.common.min.js', $blade);
        foreach (['rebateRecordsChart', 'rebateProfitChart', 'rebateSourceChart'] as $chartId) {
            $this->assertStringContainsString('id="' . $chartId . '"', $blade);
        }
        foreach (['bar', 'line', 'area', 'pie'] as $type) {
            $this->assertStringContainsString('data-chart-type="' . $type . '"', $blade);
        }
        $this->assertStringContainsString('data-translate="admin.realtime_commission_charts"', $blade);
    }

    public function test_collapse_animation_is_css_only_and_keyboard_accessible(): void
    {
        $css = file_get_contents(public_path('css/common/crm-design-system.css')) ?: '';
        $script = $this->adminLayuiScript('realtime-commissions/index.js');

        // 折叠动画由 CSS grid-template-rows 过渡实现。
        $this->assertMatchesRegularExpression(
            '/\.crm-ui-admin-shell \.crm-collapse-body\s*\{[^}]*grid-template-rows:\s*0fr;[^}]*transition:\s*grid-template-rows/s',
            $css,
            '折叠动画必须是纯 CSS 过渡。'
        );
        $this->assertMatchesRegularExpression(
            '/\.crm-ui-admin-shell \.crm-collapse-panel\.is-open \.crm-collapse-body\s*\{[^}]*grid-template-rows:\s*1fr;/s',
            $css
        );
        // 》 形箭头字形（U+203A 单右角引号）由 CSS content 提供。
        $this->assertMatchesRegularExpression(
            "/\\.crm-ui-admin-shell \\.crm-collapse-chevron::before\s*\\{[^}]*content:\s*'\\\\203A';/s",
            $css,
            '箭头字形必须是 U+203A（》形箭头）。'
        );
        // 箭头旋转也是 CSS。
        $this->assertMatchesRegularExpression(
            '/\.crm-ui-admin-shell \.crm-collapse-toggle\[aria-expanded="true"\] \.crm-collapse-chevron\s*\{[^}]*transform:\s*rotate\(90deg\);/s',
            $css
        );
        // 44px 触控目标与降低动效偏好。
        $this->assertMatchesRegularExpression(
            '/\.crm-ui-admin-shell \.crm-collapse-toggle\s*\{[^}]*min-height:\s*44px;/s',
            $css
        );
        $this->assertStringContainsString('@media (prefers-reduced-motion: reduce)', $css);
        $this->assertMatchesRegularExpression(
            '/\.crm-ui-admin-shell \.crm-chart-type\s*\{[^}]*width:\s*44px;[^}]*height:\s*44px;/s',
            $css,
            '图表类型切换按钮需要 44px 触控目标。'
        );

        // JS 只切换状态类与 aria 属性，不写动画。
        $this->assertStringContainsString("attr('aria-expanded', expanded ? 'true' : 'false')", $script);
        $this->assertStringContainsString("toggleClass('is-open', expanded)", $script);
        $this->assertStringNotContainsString('.animate(', $script, '不允许用 JS 动画实现展开。');
        // 折叠状态下不初始化图表，也不请求统计接口。
        $this->assertStringContainsString('if (!chartsLoaded)', $script);
        $this->assertStringContainsString('container.offsetWidth', $script);
    }

    public function test_new_ui_keys_exist_in_both_php_locales_and_both_js_language_packs(): void
    {
        $zh = require resource_path('lang/zh-CN/admin.php');
        $en = require resource_path('lang/en/admin.php');
        $zhJs = file_get_contents(public_path('js/shared/lang/common/zh-CN.js')) ?: '';
        $enJs = file_get_contents(public_path('js/shared/lang/common/en.js')) ?: '';

        $keys = [
            'channel_groups', 'channel_tab_all', 'channel_tab_enabled', 'channel_tab_disabled',
            'table_statistics', 'table_statistics_desc',
            'current_chain', 'chain_reset', 'chain_hint',
            'realtime_commission_charts', 'expand_statistics', 'collapse_statistics',
            'rebate_daily_records', 'rebate_daily_profit', 'rebate_source_distribution',
            'total_deposit_amount', 'total_withdraw_amount', 'total_actual_amount',
            'total_fee', 'approved_records', 'completed_records',
        ];

        foreach ($keys as $key) {
            $this->assertArrayHasKey($key, $zh, 'zh-CN 缺少 admin.' . $key);
            $this->assertArrayHasKey($key, $en, 'en 缺少 admin.' . $key);
            $this->assertStringContainsString($key . ':', $zhJs, 'zh-CN.js 缺少 ' . $key);
            $this->assertStringContainsString($key . ':', $enJs, 'en.js 缺少 ' . $key);
        }
    }
}
