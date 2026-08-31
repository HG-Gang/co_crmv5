<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/19
 * Time: 00:07
 */

/**
 * AdminLegacyRiskUiClosureModuleTest
 *
 * 文件功能：
 * - 验证旧风控页面模式与共享风控 UI 边界：固定模式与真实筛选契约、Layui/CrmUI 页签切换与受控默认模式、分页解包与错误清理、动态 IP 标题转义、双端强平动作仅使用映射的 MT4 持仓标识。
 * - 输入：HTTP 请求与事务回滚的数据库夹具；输出：PHPUnit 断言结果。
 * - 明确不负责：不覆盖真实 MT4 网关与线上支付/出金通道（由网关契约测试锁定）。
 */

namespace Tests\Feature;

use App\Models\Admin;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Feature\Concerns\ExecutesJavascriptScenarios;
use Tests\Feature\Concerns\ReadsAggregatedLayuiScripts;
use Tests\TestCase;

/**
 * Locks the legacy FengXian page mode and shared risk UI boundary.
 */
class AdminLegacyRiskUiClosureModuleTest extends TestCase
{
    use DatabaseTransactions;
    use ExecutesJavascriptScenarios;
    use ReadsAggregatedLayuiScripts;

    /**
     * @dataProvider dedicatedRiskPageProvider
     */
    public function test_legacy_risk_page_has_a_fixed_mode_and_real_filter_contract(
        string $uri,
        string $expectedMode,
        array $fields
    ): void {
        $admin = Admin::query()->findOrFail(1);

        $response = $this->actingAs($admin, 'admin')
            ->get('/' . $uri . '?mode=marginCalls')
            ->assertOk()
            ->assertViewIs('admin_layui::risk.index')
            ->assertViewHas('defaultRiskMode', $expectedMode)
            ->assertSee('data-default-risk-mode="' . $expectedMode . '"', false)
            ->assertSee('data-fixed-risk-mode="' . $expectedMode . '"', false)
            ->assertSee('role="tablist"', false)
            ->assertSee('role="tab"', false)
            ->assertSee('role="tabpanel"', false)
            ->assertSee('aria-controls="risk-panel-' . $expectedMode . '"', false)
            ->assertSee('aria-selected="true"', false);

        $this->assertSame(1, substr_count($response->getContent(), 'class="layui-btn layui-btn-primary risk-mode'));
        $this->assertSame(1, substr_count($response->getContent(), 'data-mode="'));
        foreach (array_diff(['profit', 'positions', 'marginCalls', 'ipRisk'], [$expectedMode]) as $otherMode) {
            $response->assertDontSee('data-mode="' . $otherMode . '"', false);
        }

        foreach ($fields as $field) {
            $response
                ->assertSee('name="' . $field . '"', false)
                ->assertSee('id="risk-filter-' . str_replace('_', '-', $field) . '"', false)
                ->assertSee('for="risk-filter-' . str_replace('_', '-', $field) . '"', false);
        }

        if ($expectedMode === 'profit') {
            $response
                ->assertSee('data-mode="profit"', false)
                ->assertSee('id="profitRiskTable"', false);
        }

        $content = strtolower($response->getContent());
        foreach (['mockwhenempty', 'data-mock-when-empty', 'mockrows', 'mocksummary', 'rendermockdata'] as $marker) {
            $this->assertStringNotContainsString($marker, $content, $uri);
        }
    }

    public function test_canonical_layui_risk_page_keeps_switchable_tabs_and_rejects_unknown_mode(): void
    {
        $admin = Admin::query()->findOrFail(1);

        $response = $this->actingAs($admin, 'admin')
            ->get('/admin/risk?mode=not-a-risk-mode')
            ->assertOk()
            ->assertSee('data-default-risk-mode="positions"', false)
            ->assertSee('data-fixed-risk-mode=""', false)
            ->assertSee('role="tablist"', false);

        foreach (['profit', 'positions', 'marginCalls', 'ipRisk'] as $mode) {
            $response->assertSee('data-mode="' . $mode . '"', false);
        }
    }

    public function test_canonical_crmui_risk_page_keeps_switchable_tabs_and_controlled_default_mode(): void
    {
        $response = $this->get('/admin-crmui/risk?mode=not-a-risk-mode')
            ->assertOk()
            ->assertViewIs('admin_crmui::risk.index')
            ->assertViewHas('page', function (array $page): bool {
                $permissions = [];
                foreach ($page['viewTabs'] ?? [] as $tab) {
                    $permissions[$tab['key']] = $tab['permission'] ?? null;
                }
                $actionViews = [];
                $actionPermissions = [];
                foreach ($page['rowActions'] ?? [] as $action) {
                    $actionViews[$action['key']] = $action['view'] ?? null;
                    $actionPermissions[$action['key']] = $action['permission'] ?? null;
                }

                return ($page['defaultRiskMode'] ?? null) === 'positions'
                    && $permissions === [
                        'profit' => 'admin_risk_profit_users',
                        'positions' => 'admin_risk_positions',
                        'margin_calls' => 'admin_risk_margin_calls',
                        'ip_risk' => 'admin_risk_ip_list',
                    ]
                    && ($actionViews['force_close'] ?? null) === 'positions'
                    && ($actionViews['ip_detail'] ?? null) === 'ip_risk'
                    && ($actionPermissions['force_close'] ?? null) === 'admin_risk_force_close'
                    && ($actionPermissions['ip_detail'] ?? null) === 'admin_risk_ip_detail';
            })
            ->assertSee('role="tablist"', false)
            ->assertSee('role="tab"', false)
            ->assertSee('role="tabpanel"', false)
            ->assertSee('aria-selected="true"', false);

        $this->assertStringNotContainsString('mockRows', $response->getContent());
    }

    public function test_crmui_risk_tabs_expose_mode_specific_columns(): void
    {
        $expectedColumns = [
            'profit' => [
                'user_id', 'user_name', 'mt4_login', 'mt4_name', 'mt4_balance',
                'mt4_equity', 'total_comm', 'total_volume', 'total_swaps',
                'total_profit', 'total_net_worth', 'feng_xian_val', 'mt4_regdate',
            ],
            'positions' => [
                'login', 'user_name', 'ticket', 'symbol', 'volume', 'commission',
                'profit', 'risk_value', 'open_time',
            ],
            'margin_calls' => [
                'login', 'user_id', 'user_name', 'group', 'balance', 'equity',
                'margin', 'margin_free', 'margin_level', 'leverage',
            ],
            'ip_risk' => [
                'login_ip', 'distinct_user_count', 'login_count',
                'latest_login_at', 'sample_user_name',
            ],
        ];

        $this->get('/admin-crmui/risk')
            ->assertOk()
            ->assertViewHas('page', function (array $page) use ($expectedColumns): bool {
                $actualColumns = [];
                foreach ($page['viewTabs'] ?? [] as $tab) {
                    $actualColumns[$tab['key']] = array_column($tab['columns'] ?? [], 'key');
                }

                return $actualColumns === $expectedColumns
                    && array_column($page['columns'] ?? [], 'key') === $expectedColumns['positions'];
            })
            ->assertSee('data-columns=', false);
    }

    public function test_crmui_tabs_without_column_overrides_keep_the_existing_action_header(): void
    {
        $script = (string) file_get_contents(public_path('js/apps/crmui/admin.js'));
        $functionStart = strpos($script, 'function applyViewColumns($page, columns)');
        $functionEnd = strpos($script, "\n    }", $functionStart === false ? 0 : $functionStart);

        $this->assertNotFalse($functionStart);
        $this->assertNotFalse($functionEnd);
        $function = substr($script, $functionStart, $functionEnd - $functionStart);
        $emptyGuard = strpos($function, 'if (!columns.length)');
        $detach = strpos($function, ".find('[data-crmui-action-column]').detach()");

        $this->assertNotFalse($emptyGuard);
        $this->assertNotFalse($detach);
        $this->assertLessThan($detach, $emptyGuard);
    }

    public function test_crmui_risk_paginator_unwraps_nested_records_and_uses_the_server_total(): void
    {
        $script = (string) file_get_contents(public_path('js/apps/crmui/admin.js'));
        $dataFromResponse = $this->extractCrmUiFunction($script, 'dataFromResponse');
        $rowsFromResponse = $this->extractCrmUiFunction($script, 'rowsFromResponse');
        $totalFromResponse = $this->extractCrmUiFunction($script, 'totalFromResponse');

        $result = $this->executeJavascriptJson(<<<JS
'use strict';
var $ = {isArray: Array.isArray};
{$dataFromResponse}
{$rowsFromResponse}
{$totalFromResponse}
var response = {
    code: 1000,
    data: {
        records: {data: [{id: 71}, {id: 72}], total: 47, current_page: 2},
        summary: {total_records: 47}
    }
};
var rows = rowsFromResponse(response);
console.log(JSON.stringify({ids: rows.map(function(row) { return row.id; }), total: totalFromResponse(response, rows)}));
JS
        );

        $this->assertSame([71, 72], $result['ids']);
        $this->assertSame(47, $result['total']);
    }

    public function test_crmui_risk_load_states_clear_rows_before_requests_and_on_current_errors(): void
    {
        $response = $this->get('/admin-crmui/risk')->assertOk();
        $script = (string) file_get_contents(public_path('js/apps/crmui/admin.js'));

        $response
            ->assertSee('data-loading-text="', false)
            ->assertSee('data-error-text="', false);
        $this->assertStringContainsString("renderTableState(\$page, 'loading'", $script);
        $this->assertStringContainsString("renderTableState(\$page, 'error'", $script);
        $this->assertStringContainsString("renderTableState(\$page, 'empty'", $script);
    }

    public function test_risk_ui_escapes_dynamic_ip_titles_and_uses_accessible_action_targets(): void
    {
        $blade = (string) file_get_contents(resource_path('admin/layui/risk/index.blade.php'));
        $layui = (string) file_get_contents(public_path('js/apps/admin/layui/pages.js'));
        $layuiStyles = (string) file_get_contents(public_path('css/layui/visual-c.css'));
        $crmUiStyles = (string) file_get_contents(public_path('css/crmui/visual-c.css'));

        $this->assertStringContainsString('escapeRiskDialogTitle(currentRiskIp)', $layui);
        $this->assertStringContainsString('function escapeRiskDialogTitle(value)', $layui);
        $this->assertStringContainsString('risk-action-button', $blade);
        $this->assertStringContainsString('.risk-action-button', $layuiStyles);
        $this->assertMatchesRegularExpression('/\.risk-action-button[^}]*min-height:\s*44px/s', $layuiStyles);
        $this->assertMatchesRegularExpression('/\.crmui-row-button[^}]*min-height:\s*44px/s', $crmUiStyles);
    }

    public function test_layui_ip_detail_columns_have_bilingual_admin_labels(): void
    {
        foreach (['en', 'zh-CN'] as $locale) {
            $translations = require resource_path('lang/' . $locale . '/admin.php');
            foreach ([
                'register_time', 'open_order_count', 'closed_order_count',
                'total_deposit', 'total_withdraw',
            ] as $key) {
                $this->assertArrayHasKey($key, $translations, $locale . ' admin.' . $key);
                $this->assertNotSame('', trim((string) $translations[$key]), $locale . ' admin.' . $key);
            }
        }
    }

    public function test_crmui_risk_filters_cover_profit_identity_and_position_account_type(): void
    {
        $this->get('/admin-crmui/risk')
            ->assertOk()
            ->assertViewHas('page', function (array $page): bool {
                $filters = array_column($page['filters'] ?? [], null, 'name');
                $orderType = $filters['order_type'] ?? [];

                return isset($filters['user_id'], $filters['user_name'], $filters['ticket'])
                    && isset($filters['start_date'], $filters['end_date'])
                    && ($orderType['type'] ?? null) === 'select'
                    && array_column($orderType['options'] ?? [], 'value') === ['real_disk', 'test_disk'];
            });
    }

    /**
     * @dataProvider dedicatedCrmUiRiskPageProvider
     */
    public function test_dedicated_crmui_risk_definition_exposes_only_its_fixed_tab_and_actions(
        string $path,
        string $mode,
        string $tabKey,
        string $permission,
        array $actionKeys,
        array $actionPermissions,
        array $actionRecordKeys
    ): void {
        $this->get('/admin-crmui/' . $path)
            ->assertOk()
            ->assertViewIs('admin_crmui::risk.index')
            ->assertViewHas('page', function (array $page) use ($mode, $tabKey, $permission, $actionKeys, $actionPermissions, $actionRecordKeys): bool {
                $tabs = $page['viewTabs'] ?? [];

                return ($page['defaultRiskMode'] ?? null) === $mode
                    && count($tabs) === 1
                    && ($tabs[0]['key'] ?? null) === $tabKey
                    && ($tabs[0]['permission'] ?? null) === $permission
                    && array_column($page['rowActions'] ?? [], 'key') === $actionKeys
                    && array_column($page['rowActions'] ?? [], 'permission', 'key') === $actionPermissions
                    && array_column($page['rowActions'] ?? [], 'recordKey', 'key') === $actionRecordKeys;
            });
    }

    public static function dedicatedCrmUiRiskPageProvider(): array
    {
        return [
            'profit' => ['risk/profit', 'profit', 'profit', 'admin_risk_profit_users', [], [], []],
            'positions' => ['risk/positions', 'positions', 'positions', 'admin_risk_positions', ['force_close'], ['force_close' => 'admin_risk_force_close'], ['force_close' => 'force_close_id']],
            'ip risk' => ['risk/ip-risk', 'ipRisk', 'ip_risk', 'admin_risk_ip_list', ['ip_detail'], ['ip_detail' => 'admin_risk_ip_detail'], ['ip_detail' => 'login_ip']],
        ];
    }

    public function test_double_ui_force_close_actions_use_only_the_mapped_mt4_trade_identity(): void
    {
        $blade = (string) file_get_contents(resource_path('admin/layui/risk/index.blade.php'));
        $layui = (string) file_get_contents(public_path('js/apps/admin/layui/pages.js'));
        $crmui = (string) file_get_contents(public_path('js/apps/crmui/admin.js'));

        $this->assertStringContainsString('d.force_close_id', $blade);
        $this->assertStringContainsString('obj.data.force_close_id', $layui);
        $this->assertStringNotContainsString("'/api/admin/riskForceClose/' + encodeURIComponent(obj.data.id)", $layui);
        $this->assertStringContainsString("actionKey === 'force_close' && !row.force_close_id", $crmui);
    }

    public function test_crmui_ip_detail_action_opens_a_read_only_adaptive_detail_table(): void
    {
        $response = $this->get('/admin-crmui/risk/ip-risk')
            ->assertOk()
            ->assertSee('data-crmui-ip-detail-modal', false)
            ->assertSee('data-crmui-ip-detail-body', false)
            ->assertSee('class="crmui-modal-panel crmui-modal-panel--wide"', false)
            ->assertSee('role="dialog"', false)
            ->assertSee('aria-modal="true"', false)
            ->assertSee('data-crmui-ip-detail-close', false);

        foreach ([
            'login_ip', 'user_id', 'user_name', 'login_count', 'latest_login_at',
            'open_order_count', 'closed_order_count', 'total_deposit', 'total_withdraw',
        ] as $column) {
            $response->assertSee('data-key="' . $column . '"', false);
        }

        $script = (string) file_get_contents(public_path('js/apps/crmui/admin.js'));
        $styles = (string) file_get_contents(public_path('css/crmui/visual-c.css'));

        $this->assertStringContainsString("if (actionKey === 'ip_detail')", $script);
        $this->assertStringContainsString('openIpRiskDetail($button, row)', $script);
        $this->assertStringContainsString('data: {login_ip: loginIp}', $script);
        $this->assertStringContainsString('rowsFromResponse(response)', $script);
        $this->assertStringContainsString('.crmui-modal-panel--wide', $styles);
        $this->assertStringContainsString('width: min(1120px, calc(100vw - 24px));', $styles);
    }

    public function test_layui_risk_modes_execute_only_the_active_data_source_and_load_tabs_on_demand(): void
    {
        $module = $this->adminLayuiScript('risk/index.js');
        $this->assertNotSame('', $module);
        $moduleJson = json_encode($module, JSON_THROW_ON_ERROR);

        $result = $this->executeJavascriptJson(<<<JS
'use strict';
const vm = require('vm');
const moduleSource = {$moduleJson};

function runScenario(defaultMode, fixedMode, clickModes, exerciseFilters, exerciseIpDetail, deferredMode) {
    const network = [];
    const tableConfigs = Object.create(null);
    const tableDisplays = Object.create(null);
    const clickHandlers = Object.create(null);
    const formHandlers = Object.create(null);
    const resetHandlers = Object.create(null);
    const tableHandlers = Object.create(null);
    const renderCounts = Object.create(null);
    const reloadCounts = Object.create(null);
    const deferredResponses = [];
    const buttons = ['profit', 'positions', 'marginCalls', 'ipRisk'].map(mode => ({
        mode,
        attrs: {'data-mode': mode, 'aria-selected': 'false'},
        getAttribute(name) { return this.attrs[name]; }
    }));
    const marker = {attrs: {
        'data-default-risk-mode': defaultMode,
        'data-fixed-risk-mode': fixedMode || ''
    }};
    const nodes = Object.create(null);
    ['profitRiskTable', 'riskTable', 'marginCallTable', 'riskIpTable', 'riskIpDetailTable'].forEach(id => {
        nodes[id] = {parentNode: {style: {display: ''}}};
    });
    nodes.reloadRisk = {onclick: null};
    function textNode(initialValue) {
        let value = String(initialValue);

        return {
            get innerText() { return value; },
            set innerText(nextValue) { value = String(nextValue); }
        };
    }
    const summaries = {
        total_records: textNode('88'),
        total_profit: textNode('88.00'),
        total_volume: textNode('88.00'),
        total_risk_value: textNode('88.00'),
        total_margin: textNode('88.00')
    };

    function collection(items) {
        return {
            first() { return collection(items.slice(0, 1)); },
            attr(name, value) {
                if (value === undefined) {
                    return items[0] && items[0].attrs ? items[0].attrs[name] : undefined;
                }
                items.forEach(item => {
                    item.attrs = item.attrs || {};
                    item.attrs[name] = value;
                });
                return this;
            },
            on(event, handler) {
                items.forEach(item => {
                    if (item.mode) {
                        clickHandlers[item.mode] = handler;
                    } else if (item.selector) {
                        resetHandlers[item.selector + ':' + event] = handler;
                    }
                });
                return this;
            },
            each(callback) {
                items.forEach((item, index) => callback.call(item, index, item));
                return this;
            },
            toggleClass() { return this; },
            val() { return this; }
        };
    }

    function jquery(selector) {
        if (selector === marker || buttons.includes(selector)) {
            return collection([selector]);
        }
        if (selector === '[data-layui-page="risk/index"]') {
            return collection([marker]);
        }
        if (selector === '.risk-mode') {
            return collection(buttons);
        }
        if (selector === '#resetRiskSearch') {
            return collection([{selector: '#resetRiskSearch'}]);
        }
        if (selector === '#riskIpDetailDialog') {
            return collection([{selector: '#riskIpDetailDialog'}]);
        }
        return collection([]);
    }

    function responseFor(id) {
        return {
            code: 1000,
            message: '',
            data: {
                records: {total: 1, data: [{id: 1}]},
                summary: {
                    total_records: id === 'riskTable' ? 9 : 7,
                    total_profit: id === 'riskTable' ? 99 : 77,
                    total_volume: 66,
                    total_risk_value: 55,
                    total_margin: 44
                }
            }
        };
    }

    const table = {
        render(config) {
            tableConfigs[config.id] = config;
            renderCounts[config.id] = (renderCounts[config.id] || 0) + 1;
            if (config.url) {
                network.push(config.url);
                if (config.parseData) {
                    const tableModes = {profitRiskTable: 'profit', riskTable: 'positions', marginCallTable: 'marginCalls', riskIpTable: 'ipRisk'};
                    if (deferredMode && tableModes[config.id] === deferredMode) {
                        deferredResponses.push(() => config.parseData(responseFor(config.id)));
                    } else {
                        config.parseData(responseFor(config.id));
                    }
                }
            }
            return config;
        },
        reload(id) {
            const config = tableConfigs[id];
            reloadCounts[id] = (reloadCounts[id] || 0) + 1;
            if (config && config.url) {
                network.push(config.url);
                if (config.parseData) {
                    config.parseData(responseFor(id));
                }
            }
        },
        on(name, handler) { tableHandlers[name] = handler; }
    };
    const form = {
        render() {},
        on(name, handler) { formHandlers[name] = handler; }
    };
    const registry = Object.create(null);
    const documentObject = {
        getElementById(id) { return nodes[id]; },
        querySelector(selector) {
            const match = selector.match(/^\[data-summary-field="([^"]+)"\]$/);
            return match ? summaries[match[1]] || null : null;
        }
    };
    const sandbox = {
        registry,
        once: factory => factory,
        layui: {
            table,
            form,
            layer: {
                msg() {},
                open(config) {
                    if (config.success) {
                        config.success();
                    }
                }
            },
            jquery,
            use(_dependencies, callback) { callback(); }
        },
        CrmLang: {switchUI() {}, t(key) { return key; }},
        CrmTable: {layuiConfig(_guard, config) { return config; }},
        CrmAjax: {request() {}},
        serializeForm() { return {}; },
        document: documentObject,
        window: {setTimeout(callback) { callback(); }},
        Number,
        parseFloat,
        parseInt,
        encodeURIComponent,
        console
    };

    vm.runInNewContext(moduleSource, sandbox, {filename: 'risk-index.js'});
    registry['risk/index']();

    const initialNetwork = network.slice();
    const afterClicks = [];
    clickModes.forEach(mode => {
        clickHandlers[mode].call(buttons.find(button => button.mode === mode));
        afterClicks.push(network.slice());
    });

    let afterSearch = network.slice();
    let afterReset = network.slice();
    if (exerciseFilters) {
        formHandlers['submit(searchRisk)']({field: {}});
        afterSearch = network.slice();
        resetHandlers['#resetRiskSearch:click']();
        afterReset = network.slice();
    }

    const afterIpDetail = [];
    if (exerciseIpDetail) {
        tableHandlers['tool(riskIpTable)']({event: 'ipDetail', data: {login_ip: '192.168.1.1'}});
        afterIpDetail.push(network.slice());
        tableHandlers['tool(riskIpTable)']({event: 'ipDetail', data: {login_ip: '192.168.1.2'}});
        afterIpDetail.push(network.slice());
    }

    deferredResponses.forEach(callback => callback());

    buttons.forEach(button => {
        if (button.attrs['aria-selected'] === 'true') {
            tableDisplays.activeMode = button.mode;
        }
    });
    ['profitRiskTable', 'riskTable', 'marginCallTable', 'riskIpTable', 'riskIpDetailTable'].forEach(id => {
        tableDisplays[id] = nodes[id].parentNode.style.display;
    });

    return {
        initialNetwork,
        afterClicks,
        afterSearch,
        afterReset,
        afterIpDetail,
        tableDisplays,
        summaries: Object.fromEntries(Object.entries(summaries).map(([key, node]) => [key, node.innerText])),
        renderCounts,
        reloadCounts
    };
}

console.log(JSON.stringify({
    fixedProfit: runScenario('profit', 'profit', ['positions'], true, false),
    fixedPositions: runScenario('positions', 'positions', ['ipRisk'], false, false),
    fixedIpRisk: runScenario('ipRisk', 'ipRisk', ['marginCalls'], true, true),
    canonical: runScenario('positions', '', ['marginCalls', 'ipRisk'], false, false),
    profitRoundTrip: runScenario('profit', '', ['positions', 'profit'], false, false),
    profitLateResponse: runScenario('positions', '', ['profit'], false, false, 'positions')
}));
JS);

        $this->assertSame('profit', $result['fixedProfit']['tableDisplays']['activeMode']);
        $this->assertSame('', $result['fixedProfit']['tableDisplays']['profitRiskTable']);
        $this->assertSame('none', $result['fixedProfit']['tableDisplays']['riskTable']);
        $this->assertSame(['/api/admin/riskProfitUsers'], $result['fixedProfit']['initialNetwork']);
        $this->assertSame(['/api/admin/riskProfitUsers'], $result['fixedProfit']['afterClicks'][0]);
        $this->assertSame(
            ['/api/admin/riskProfitUsers', '/api/admin/riskProfitUsers'],
            $result['fixedProfit']['afterSearch']
        );
        $this->assertSame(
            ['/api/admin/riskProfitUsers', '/api/admin/riskProfitUsers', '/api/admin/riskProfitUsers'],
            $result['fixedProfit']['afterReset']
        );

        $this->assertSame(['/api/admin/riskPositions'], $result['fixedPositions']['initialNetwork']);
        $this->assertSame(['/api/admin/riskPositions'], $result['fixedPositions']['afterClicks'][0]);
        $this->assertSame(['/api/admin/riskIpList'], $result['fixedIpRisk']['initialNetwork']);
        $this->assertSame(
            ['/api/admin/riskIpList', '/api/admin/riskIpList'],
            $result['fixedIpRisk']['afterSearch']
        );
        $this->assertSame(
            ['/api/admin/riskIpList', '/api/admin/riskIpList', '/api/admin/riskIpList'],
            $result['fixedIpRisk']['afterReset']
        );
        $this->assertSame(1, $result['fixedIpRisk']['renderCounts']['riskIpDetailTable']);
        $this->assertSame(1, $result['fixedIpRisk']['reloadCounts']['riskIpDetailTable']);
        $secondIpOpenNetwork = $result['fixedIpRisk']['afterIpDetail'][1];
        $this->assertSame('/api/admin/riskIpDetail', $secondIpOpenNetwork[array_key_last($secondIpOpenNetwork)]);

        $this->assertSame(['/api/admin/riskPositions'], $result['canonical']['initialNetwork']);
        $this->assertSame(
            ['/api/admin/riskPositions', '/api/admin/riskMarginCalls'],
            $result['canonical']['afterClicks'][0]
        );
        $this->assertSame(
            ['/api/admin/riskPositions', '/api/admin/riskMarginCalls', '/api/admin/riskIpList'],
            $result['canonical']['afterClicks'][1]
        );
        $this->assertSame('7', $result['profitRoundTrip']['summaries']['total_records']);
        $this->assertSame('77.00', $result['profitRoundTrip']['summaries']['total_profit']);
        $this->assertSame('66.00', $result['profitRoundTrip']['summaries']['total_volume']);
        $this->assertSame('55.00', $result['profitRoundTrip']['summaries']['total_risk_value']);
        $this->assertSame('44.00', $result['profitRoundTrip']['summaries']['total_margin']);
        $this->assertSame('7', $result['profitLateResponse']['summaries']['total_records']);
        $this->assertSame('77.00', $result['profitLateResponse']['summaries']['total_profit']);
        $this->assertSame('66.00', $result['profitLateResponse']['summaries']['total_volume']);
        $this->assertSame('55.00', $result['profitLateResponse']['summaries']['total_risk_value']);
        $this->assertSame('44.00', $result['profitLateResponse']['summaries']['total_margin']);
    }

    public static function dedicatedRiskPageProvider(): array
    {
        return [
            'profit' => [
                'index/admin/fengXian/profit_list',
                'profit',
                ['user_id', 'user_name', 'start_date', 'end_date'],
            ],
            'positions' => [
                'index/admin/fengXian/position_list',
                'positions',
                ['user_id', 'ticket', 'symbol', 'order_type', 'start_date', 'end_date'],
            ],
            'ip risk' => [
                'index/admin/fengXian/Ipaddress_list',
                'ipRisk',
                ['login_ip', 'min_user_count', 'start_date', 'end_date'],
            ],
        ];
    }

    private function extractCrmUiFunction(string $source, string $name): string
    {
        $start = strpos($source, 'function ' . $name . '(');
        $this->assertNotFalse($start, $name . ' must exist');
        $next = strpos($source, "\n    function ", $start + 1);

        return substr($source, $start, $next === false ? null : $next - $start);
    }
}
