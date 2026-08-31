<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/18
 * Time: 16:48
 */

namespace App\Http\Controllers\CrmUi\Front;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

/**
 * 大代理 CrmUI 页面壳控制器。
 *
 * 文件功能：
 * - 大代理专属入口：login / dashboard / show({path?})，所有页面复用 front_crmui::big-agent.* 视图。
 * - 页面配置来自 definitions()，endpoint 全部指向 legacy_user_agents_* 旧大代理接口，legacyResponse=true 兼容其响应结构。
 *
 * 设计说明：
 * - 与前台/后台 CrmUi 一样是「页面壳 + definition 注入 + 前端 API 拉取」：路由 {path?} 捕获路径，
 *   show() 解析 definitions() 后注入 Blade，数据由前端按 apiUrl 拉取，本控制器不做业务查询。
 * - 登录态为大代理（big-agent）会话，与普通前台用户 JWT 分离；普通用户可见范围与大代理可见范围不同，不能混用前台页面配置。
 *
 * 安全边界：
 * - 页面壳只输出路由名、字段与操作声明，不执行查询；数据权限由 legacy_user_agents_* 接口与登录态中间件负责。
 * - profile/password 与旧大代理页共用同一表单端点与字段契约，密码校验在提交接口失败关闭。
 */
class BigAgentPageController extends Controller
{
    /**
     * 渲染大代理登录页。
     *
     * 提交与验证码地址指向旧大代理登录路由，登录成功进入大代理工作台。
     *
     * @return \Illuminate\Contracts\View\View 大代理登录页。
     */
    public function login(Request $request)
    {
        $family = $this->renderFamily($request);

        return view('front_crmui::big-agent.login', [
            'page' => [
                'title' => __('crmui.front.pages.big_agent_dashboard.title'),
                'subtitle' => __('crmui.front.auth.big_number_login.subtitle'),
                'submitUrl' => route('legacy_user_agents_sign_in'),
                'captchaUrl' => route('legacy_user_agents_captcha'),
                'dashboardUrl' => route($this->routeName($family, 'dashboard')),
                'renderFamily' => $family,
                'loginUrl' => route($this->routeName($family, 'login')),
            ],
        ]);
    }

    /**
     * 渲染大代理工作台首页。
     *
     * @return \Illuminate\Contracts\View\View 大代理工作台页面壳。
     */
    public function dashboard(Request $request)
    {
        $family = $this->renderFamily($request);

        return view('front_crmui::big-agent.dashboard', [
            'page' => $this->page('dashboard', $this->definitions()['dashboard'], $family),
            'navGroups' => $this->navGroups(),
        ]);
    }

    /**
     * 按 {path?} 路由渲染大代理子页面。
     *
     * 阶段：路径归一化 → 定义解析（未知路径回退 proxy/list）→ 请求参数合入（userId 预选）→ 渲染。
     *
     * @param Request $request 当前页面请求，proxy/descendants 与 position/descendants 读取 userId 预选查询条件。
     * @param string $path 页面路径，默认 proxy/list。
     * @return \Illuminate\Contracts\View\View 大代理列表/表单页面壳。
     */
    public function show(Request $request, $path = 'proxy/list')
    {
        // 定义解析阶段：旧别名路径归一化，未知路径回退 proxy/list 兜底渲染。
        $path = $this->canonicalPath(trim((string) $path, '/'));
        $definitions = $this->definitions();
        $definition = $definitions[$path] ?? $definitions['proxy/list'];
        $family = $this->renderFamily($request);
        $page = $this->page($path, $definition, $family);

        // 请求参数合入阶段：子代理/下级持仓页面把 URL 上的 userId 作为初始筛选值注入页面壳。
        if ($path === 'proxy/descendants' || $path === 'position/descendants') {
            $page['filterValues']['userPId'] = (string) $request->query('userId', '');
        }

        // 渲染阶段：只输出页面壳配置与导航，数据由前端按 apiUrl 拉取。
        return view('front_crmui::big-agent.' . $definition['view'], [
            'page' => $page,
            'navGroups' => $this->navGroups(),
        ]);
    }

    /**
     * 把旧路径别名归一化为 definitions() 的标准 key。
     *
     * @param string $path 去掉首尾斜杠的页面路径。
     * @return string 归一化后的标准页面 key；未知路径原样返回交给 show() 兜底。
     */
    private function canonicalPath(string $path): string
    {
        return [
            'proxy' => 'proxy/list',
            'position' => 'position/summary',
            'open' => 'orders/open',
            'closed' => 'orders/closed',
            'password' => 'profile/password',
        ][$path] ?? $path;
    }

    /** @return array<string, array<string, mixed>> */
    private function definitions(): array
    {
        $agentColumns = ['user_id', 'user_name', 'agentsTotal', 'accountTotal', 'user_money', 'cust_eqy', 'fy_money', 'rj_money', 'qk_money', 'group_comm_prop', 'rec_crt_date'];
        $positionColumns = ['user_id', 'user_name', 'user_money', 'cust_eqy', 'total_rj', 'total_fy', 'total_qk', 'total_net_worth', 'total_profit', 'total_volume'];
        $orderColumns = ['ticket', 'login', 'symbol', 'cmd_text', 'volume_lots', 'commission', 'profit', 'swaps', 'open_price', 'open_time'];

        return [
            'dashboard' => ['view' => 'dashboard', 'key' => 'big_agent_dashboard'],
            'proxy/list' => ['view' => 'list', 'key' => 'big_agent_proxy_list', 'endpoint' => 'legacy_user_agents_proxy_search', 'filters' => ['userId', 'username', 'userstatus', 'startdate', 'enddate'], 'columns' => $agentColumns, 'descendantPath' => 'proxy/descendants'],
            'proxy/descendants' => ['view' => 'list', 'key' => 'big_agent_proxy_descendants', 'endpoint' => 'legacy_user_agents_proxy_search_by_sub', 'filters' => ['userPId', 'username', 'userstatus', 'startdate', 'enddate'], 'columns' => $agentColumns],
            'position/summary' => ['view' => 'list', 'key' => 'big_agent_position_summary', 'endpoint' => 'legacy_user_agents_position_search', 'filters' => ['userId', 'username', $this->symbolFilter(), 'startdate', 'enddate'], 'columns' => $positionColumns, 'descendantPath' => 'position/descendants'],
            'position/descendants' => ['view' => 'list', 'key' => 'big_agent_position_descendants', 'endpoint' => 'legacy_user_agents_position_sub_search', 'filters' => ['userPId', 'username', $this->symbolFilter(), 'startdate', 'enddate'], 'columns' => $positionColumns],
            'orders/open' => ['view' => 'list', 'key' => 'big_agent_open_orders', 'endpoint' => 'legacy_user_agents_open_order_search', 'filters' => ['userId', 'orderUserId', 'orderId', $this->symbolFilter(), 'startdate', 'enddate'], 'columns' => $orderColumns],
            'orders/closed' => ['view' => 'list', 'key' => 'big_agent_closed_orders', 'endpoint' => 'legacy_user_agents_close_order_search', 'filters' => ['userId', 'orderUserId', 'orderId', $this->symbolFilter(), 'startdate', 'enddate'], 'columns' => array_merge($orderColumns, ['close_price', 'close_time'])],
            // Keep the CrmUI form on the same endpoint and field contract as the old big-agent page.
            'profile/password' => ['view' => 'password', 'key' => 'big_agent_password', 'formEndpoint' => 'legacy_user_agents_change_password', 'formFields' => ['old_password', 'password', 'password_confirmation']],
        ];
    }

    /** @param array<string, mixed> $definition */
    private function page(string $path, array $definition, string $family): array
    {
        $key = (string) $definition['key'];
        $fields = function (array $items, bool $password = false): array {
            return array_map(function ($item) use ($password): array {
                $config = is_array($item) ? $item : ['name' => (string) $item];
                $name = (string) $config['name'];
                $label = $this->fieldLabel($name);
                $type = $password
                    ? 'password'
                    : ($config['type'] ?? (in_array($name, ['startdate', 'enddate'], true) ? 'date' : 'text'));

                return array_merge([
                    'name' => $name,
                    'label' => $label,
                    'placeholder' => $label,
                    'type' => $type,
                    'options' => [],
                ], $config);
            }, $items);
        };
        $rowActions = [];
        if (!empty($definition['descendantPath'])) {
            $rowActions[] = ['key' => 'descendants', 'label' => __('crmui.actions.descendants'), 'href' => route($this->routeName($family, 'app'), ['path' => $definition['descendantPath']]) . '?userId=__ID__', 'recordKey' => 'user_id'];
        }

        return [
            'surface' => 'big_agent',
            'session' => 'big-agent',
            'key' => 'front.' . $key,
            'path' => $path,
            'renderFamily' => $family,
            'routeNames' => [
                'login' => $this->routeName($family, 'login'),
                'logout' => $this->routeName($family, 'logout'),
                'dashboard' => $this->routeName($family, 'dashboard'),
                'app' => $this->routeName($family, 'app'),
            ],
            'title' => __('crmui.front.pages.' . $key . '.title'),
            'description' => __('crmui.front.pages.' . $key . '.description'),
            'apiUrl' => isset($definition['endpoint']) ? route((string) $definition['endpoint']) : '',
            'apiMethod' => 'POST',
            'legacyResponse' => true,
            'filterValues' => [],
            'filters' => $fields($definition['filters'] ?? []),
            'columns' => array_map(fn (string $item): array => ['key' => $item, 'label' => $this->fieldLabel($item)], $definition['columns'] ?? []),
            'metrics' => [],
            'actions' => [['key' => 'refresh', 'label' => __('crmui.actions.refresh')]],
            'rowActions' => $rowActions,
            'formFields' => $fields($definition['formFields'] ?? [], true),
            'formUrl' => isset($definition['formEndpoint']) ? route((string) $definition['formEndpoint']) : '',
            'formMethod' => 'POST',
            'successUrl' => isset($definition['formEndpoint']) ? route($this->routeName($family, 'login')) : '',
            'mode' => isset($definition['formEndpoint']) ? 'form' : 'table',
            'emptyText' => __('crmui.empty.no_records'),
        ];
    }

    private function renderFamily(Request $request): string
    {
        return $request->is('front-naive/big-agent') || $request->is('front-naive/big-agent/*')
            ? 'naive'
            : 'crmui';
    }

    private function routeName(string $family, string $page): string
    {
        return 'front_' . ($family === 'naive' ? 'naive' : 'crmui') . '_big_agent_' . $page;
    }

    /**
     * 取字段的多语言标签；语言包无对应键时回退为字段名本身。
     *
     * @param string $key 页面字段 key，例如 user_id、startdate。
     * @return string 多语言标签或原字段名。
     */
    private function fieldLabel(string $key): string
    {
        $translationKey = 'crmui.fields.' . $key;
        $label = __($translationKey);

        return $label === $translationKey ? $key : $label;
    }

    /** @return array<string, string> */
    private function symbolFilter(): array
    {
        return [
            'name' => 'symbol',
            'type' => 'select',
            'dynamicOptions' => 'bigAgentSymbols',
        ];
    }

    /** @return array<int, array<string, mixed>> */
    private function navGroups(): array
    {
        return [[
            'title' => __('crmui.common.big_agent_console'),
            'items' => [
                ['label' => __('crmui.front.pages.big_agent_dashboard.title'), 'path' => 'dashboard', 'icon' => 'gauge'],
                ['label' => __('crmui.front.pages.big_agent_proxy_list.title'), 'path' => 'proxy/list', 'icon' => 'network'],
                ['label' => __('crmui.front.pages.big_agent_position_summary.title'), 'path' => 'position/summary', 'icon' => 'table-2'],
                ['label' => __('crmui.front.pages.big_agent_open_orders.title'), 'path' => 'orders/open', 'icon' => 'circle-play'],
                ['label' => __('crmui.front.pages.big_agent_closed_orders.title'), 'path' => 'orders/closed', 'icon' => 'history'],
                ['label' => __('crmui.front.pages.big_agent_password.title'), 'path' => 'profile/password', 'icon' => 'key-round'],
            ],
        ]];
    }
}
