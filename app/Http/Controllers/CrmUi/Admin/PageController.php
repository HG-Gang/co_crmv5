<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/29
 * Time: 14:31
 */

namespace App\Http\Controllers\CrmUi\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

/**
 * 后台 CrmUI 页面壳控制器。
 *
 * 文件功能：
 * - 承载后台 crmui 全部页面：用户/角色权限/资金/交易/风控/礼品/系统配置等，页面差异集中在 pages() 的 definition 配置数组。
 * - index() 重定向到后台工作台，login() 渲染后台登录页，show({path?}) 按路径解析 definition 并渲染通用视图。
 * - navGroups() 输出后台侧边栏导航结构（含 Lucide 图标名），供布局模板渲染。
 *
 * 设计说明：
 * - 「页面壳 + definition 注入 + 前端 API 拉取」：路由以 {path?} 捕获任意路径，show() 把页面配置（apiUrl、字段、列、操作声明）注入 Blade，
 *   前端再按 apiUrl/apiMethod 拉取真实数据并渲染，本控制器不做任何业务查询。
 * - 前台、后台与大代理各只有 3 个页面壳控制器（Admin\PageController、Front\PageController、Front\BigAgentPageController），
 *   其余页面全部由 definition 配置 + 通用视图承载，因此本目录文件少。
 * - users/{id} 等带参详情路径在 show() 中归一化为 users/{id} 占位 key，再落到对应 detail 页面。
 *
 * 安全边界：
 * - 页面壳只输出路由名、字段与操作声明，不执行查询；真实数据权限由对应 admin_api_* 控制器与权限中间件逐接口校验。
 * - URL 查询参数经 definitionWithRequestDefaults() 合入筛选默认值时，非标量（数组）参数被忽略，避免数组值污染筛选表单。
 * - 页面上的操作声明只是前端入口，动作本身是否允许由后端接口校验，页面声明不授予任何权限。
 */
class PageController extends Controller
{
    /**
     * 后台根路径统一重定向到后台工作台。
     *
     * @return \Illuminate\Http\RedirectResponse 指向 /admin-crmui/dashboard。
     */
    public function index()
    {
        return redirect()->route('admin_crmui_app', ['path' => 'dashboard']);
    }

    /**
     * 渲染后台登录页。
     *
     * @return \Illuminate\Contracts\View\View 后台登录页，提交到 admin_api_login。
     */
    public function login()
    {
        return view('admin_crmui::auth.login', [
            'page' => [
                'key' => 'admin-login',
                'title' => __('crmui.admin.auth.login.title'),
                'subtitle' => __('crmui.admin.auth.login.subtitle'),
                'submitUrl' => route('admin_api_login'),
                'dashboardUrl' => route('admin_crmui_app', ['path' => 'dashboard']),
            ],
        ]);
    }

    /**
     * 按 {path?} 路由渲染后台页面壳。
     *
     * 阶段：定义解析（详情路径归一化、definition 兜底、URL 查询参数合入筛选默认值）→ 请求参数合入（frame 标记）→ 渲染。
     *
     * @param Request $request 当前页面请求，查询参数会被合入页面筛选默认值。
     * @param string $path 页面路径，默认 dashboard。
     * @return \Illuminate\Contracts\View\View 对应模块的页面壳视图。
     */
    public function show(Request $request, $path = 'dashboard')
    {
        // 定义解析阶段：详情路径归一化为占位 key，未知路径回退 dashboard 兜底渲染。
        $path = trim($path ?: 'dashboard', '/');
        $pages = $this->pages();
        $authenticationDetail = null;

        if (preg_match('#^users/\d+$#', $path)) {
            $path = 'users/{id}';
        }

        if (preg_match('#^authentications/([1-9]\d*)/detail/(show|auth)$#', $path, $matches)) {
            $authenticationDetail = [
                'userId' => $matches[1],
                'mode' => $matches[2],
            ];
            $path = 'authentications/{id}/detail/{mode}';
        } elseif (preg_match('#^authentications/[^/]+/detail(?:/.*)?$#', $path)) {
            abort(404);
        }

        // 请求参数合入阶段：URL 查询参数（持仓汇总等本地跳转入口）合并为筛选默认值，非标量参数忽略。
        $definition = $pages[$path] ?? $pages['dashboard'];
        $definition = $this->definitionWithRequestDefaults($definition, $request);
        if (!empty($definition['fixedFilters'])) {
            $definition['defaultFilters'] = array_replace(
                $definition['defaultFilters'] ?? [],
                $definition['fixedFilters']
            );
        }
        $page = $this->page($path, $definition);

        if ($authenticationDetail !== null) {
            $page['authenticationUserId'] = $authenticationDetail['userId'];
            $page['authenticationMode'] = $authenticationDetail['mode'];
            $page['reviewUrl'] = route('admin_api_reviewAuth');
        }

        // iframe 内嵌标记原样透传给前端布局，页面数据仍由前端按 apiUrl 拉取。
        if ($request->boolean('frame')) {
            $page['frame'] = true;
        }

        // 渲染阶段：只输出页面壳配置与导航，不在此处查询任何业务数据。
        return view('admin_crmui::' . $definition['view'], [
            'page' => $page,
            'navGroups' => $this->navGroups(),
        ]);
    }

    /**
     * 将 URL 查询参数同步为 CrmUI 筛选默认值。
     *
     * @param array<string, mixed> $definition 当前页面配置，包含 filters/defaultFilters 等声明。
     * @param Request $request 当前 HTTP 请求，查询参数来自持仓汇总等本地跳转入口。
     * @return array<string, mixed> 返回已合并默认筛选值的页面配置；非标量参数会被忽略，避免数组污染筛选表单。
     */
    private function definitionWithRequestDefaults(array $definition, Request $request): array
    {
        $defaults = $definition['defaultFilters'] ?? [];

        foreach ($definition['filters'] ?? [] as $filter) {
            $name = is_array($filter) ? ($filter['name'] ?? '') : $filter;
            $value = $name ? $request->query($name) : null;

            if ($value === null || is_array($value)) {
                continue;
            }

            $defaults[$name] = (string) $value;
        }

        $definition['defaultFilters'] = $defaults;

        return $definition;
    }

    /**
     * 组装页面壳数据结构,供 Blade 布局与前端脚本渲染。
     *
     * 将 definition 声明转为统一 page 数组:key/path/title/description/apiUrl/apiMethod/formUrl/
     * mode/filters/columns/metrics/formFields/panels/actions/rowActions 等,未声明项按默认值兜底;
     * 页面只携带声明与接口地址,真实数据由前端按 apiUrl 拉取。
     *
     * @param string $path 当前页面路径(已归一化的 {path?} 值)。
     * @param array $definition 该页面的 definition 配置,来源为 pages()。
     * @return array<string, mixed> 页面壳数据结构。
     */
    private function page(string $path, array $definition): array
    {
        $key = $definition['key'];
        $apiRoute = $definition['api'] ?? null;
        $formRoute = $definition['formApi'] ?? null;
        $mode = $definition['mode'] ?? 'table';
        $defaultMetrics = in_array($mode, ['form', 'profile'], true)
            ? []
            : ['total', 'pending', 'completed'];

        return [
            'surface' => 'admin',
            'key' => 'admin.' . $key,
            'path' => $path,
            'title' => __('crmui.admin.pages.' . $key . '.title'),
            'description' => __('crmui.admin.pages.' . $key . '.description'),
            'apiUrl' => $definition['apiUrl'] ?? ($apiRoute ? route($apiRoute) : ''),
            'apiMethod' => $definition['method'] ?? 'POST',
            'defaultRiskMode' => $this->controlledRiskMode($definition['defaultRiskMode'] ?? null),
            'formUrl' => $formRoute ? route($formRoute, $definition['formParameters'] ?? []) : '',
            'formMethod' => $definition['formMethod'] ?? 'POST',
            'formPermission' => $definition['formPermission'] ?? '',
            'mode' => $mode,
            'viewTabs' => $this->viewTabs($definition['viewTabs'] ?? []),
            'filters' => $this->fields($definition['filters'] ?? ['keyword', 'status'], $definition['defaultFilters'] ?? []),
            'columns' => $this->columns($definition['columns'] ?? ['id', 'name', 'status', 'updated_at']),
            'metrics' => $this->metrics($definition['metrics'] ?? $defaultMetrics),
            'formFields' => $this->fields($definition['formFields'] ?? []),
            'panels' => $this->panels($definition['panels'] ?? []),
            'actions' => $this->actions($definition['actions'] ?? ['refresh', 'create', 'export']),
            'rowActions' => $this->rowActions($definition['rowActions'] ?? []),
            'giftRecipientPicker' => !empty($definition['giftRecipientPicker']),
            // 批量操作声明：只有显式声明 batch 的页面才会渲染勾选列与批量弹窗。
            // 未声明时返回空数组，partial 与前端渲染器都按「无批量能力」处理，其余页面行为完全不变。
            'batch' => $this->batch($definition['batch'] ?? null),
            'emptyText' => __('crmui.empty.no_records'),
        ];
    }

    /**
     * 把批量操作声明转成渲染结构；未声明时返回空数组表示该页面没有批量能力。
     *
     * 与 actions() 的区别：批量操作作用于「勾选出的多行」而不是单行或整页，
     * 因此额外需要 sourceStatusField（判定来源状态的行字段）与 transitions（来源状态到目标状态的白名单），
     * 这两项决定了勾选校验与目标状态可选项，缺一不可。
     *
     * url 由 legacyUri 直接拼接而非 route()：批量入口复用旧后台 URI，
     * 该 URI 由 legacy_admin.php 按 legacy-routes.json 动态注册，路由名是 md5 派生值，
     * 不适合在页面声明里硬编码，因此这里按稳定的 URI 路径生成绝对地址。
     *
     * @param array<string, mixed>|null $batch 批量声明；null 表示该页面不启用批量。
     * @return array<string, mixed> 渲染用批量结构；空数组表示不启用。
     */
    private function batch(?array $batch): array
    {
        if ($batch === null || $batch === []) {
            return [];
        }

        return [
            'key' => $batch['key'] ?? 'batch',
            'label' => __('admin.batch_operation'),
            'title' => __('admin.batch_withdraw_title'),
            'url' => url($batch['legacyUri'] ?? ''),
            'method' => $batch['method'] ?? 'POST',
            'permission' => $batch['permission'] ?? '',
            'recordKey' => $batch['recordKey'] ?? 'id',
            // sourceStatusField：勾选校验读取的行字段名；同一批次的该字段值必须一致。
            'sourceStatusField' => $batch['sourceStatusField'] ?? 'status',
            // transitions：来源状态 => 允许的目标状态列表，同时用于禁用弹窗中的非法目标项。
            'transitions' => $batch['transitions'] ?? [],
            // targetStatuses：弹窗中可选的目标状态全集，label 已翻译，禁用与否由 transitions 决定。
            'targetStatuses' => array_map(static function (array $status): array {
                return [
                    'value' => (string) $status['value'],
                    'label' => __($status['label']),
                    // remarkRequired：该目标状态是否强制填写备注，出金拒绝对应后端非空 reason 校验。
                    'remarkRequired' => !empty($status['remarkRequired']),
                ];
            }, $batch['targetStatuses'] ?? []),
        ];
    }

    /**
     * 后台全部页面的 definition 配置中心。
     *
     * 数组 key 是路由 {path?} 值,value 是该页面的 view/key/api/filters/columns/formFields/actions/rowActions 等声明;
     * show() 按路径查表,未命中时回退 dashboard。带参详情路径统一使用 users/{id} 占位 key。
     *
     * @return array<string, array<string, mixed>> path => 页面声明。
     */
    private function pages(): array
    {
        return [
            'dashboard' => ['view' => 'dashboard.index', 'key' => 'dashboard', 'api' => 'admin_api_dashboardData', 'mode' => 'dashboard', 'filters' => ['keyword'], 'columns' => ['name', 'amount', 'status', 'updated_at'], 'metrics' => ['users', 'deposits', 'withdrawals']],
            'users' => ['view' => 'users.index', 'key' => 'users', 'api' => 'admin_api_userList', 'filters' => ['user_id', 'email', 'user_name', 'start_date', 'end_date', ['name' => 'account_type', 'type' => 'select', 'options' => [['value' => 1, 'label' => 'agent_account'], ['value' => 2, 'label' => 'client_account']]]], 'columns' => ['user_id', 'user_name', 'email', 'phone', 'total_yuerj', 'total_yuecj', 'total_net_worth', 'total_comm', 'total_profit', 'total_volume', 'total_swaps', 'auth_status', 'created_at'], 'actions' => $this->exportActions('admin_api_exportUsers', 'users_export.csv'), 'rowActions' => [
                ['key' => 'detail', 'local' => true],
                ['key' => 'change_status', 'route' => 'admin_api_changeUserStatus', 'permission' => 'admin_user_status', 'fields' => [['name' => 'is_enabled', 'type' => 'select', 'options' => [['value' => 1, 'label' => 'enabled'], ['value' => 0, 'label' => 'disabled']]]], 'recordKey' => 'user_id', 'payloadName' => 'user_id'],
                ['key' => 'review_auth', 'route' => 'admin_api_reviewAuth', 'permission' => 'admin_user_review_auth', 'fields' => $this->authReviewFields(), 'recordKey' => 'user_id', 'payloadName' => 'user_id'],
            ]],
            'users/{id}' => ['view' => 'users.detail', 'key' => 'user_detail', 'api' => 'admin_api_userDetail', 'mode' => 'profile', 'columns' => ['user_id', 'name', 'email', 'phone', 'status']],
            'authentications/{id}/detail/{mode}' => ['view' => 'authentications.detail', 'key' => 'authentication_detail', 'api' => 'admin_api_authDetail', 'mode' => 'profile'],
            'roles' => ['view' => 'roles.index', 'key' => 'roles', 'api' => 'admin_api_roleList', 'apiUrl' => url('/api/admin/roles'), 'formApi' => 'admin_api_createRole', 'formPermission' => 'admin_role_create', 'formFields' => $this->roleFields(), 'columns' => ['id', 'name', 'guard_type', 'description'], 'rowActions' => [
                ['key' => 'assign_permissions', 'route' => 'admin_api_assignPermissions', 'permission' => 'admin_role_assign_permissions', 'fields' => [['name' => 'permissions', 'type' => 'permission_tree', 'label' => 'permissions']], 'permissionTreeApi' => 'admin_api_permissionTree', 'recordKey' => 'id', 'payloadName' => 'role_id'],
                ['key' => 'update', 'route' => 'admin_api_updateRole', 'permission' => 'admin_role_update', 'fields' => $this->roleFields()],
                ['key' => 'delete', 'route' => 'admin_api_deleteRole', 'permission' => 'admin_role_delete', 'variant' => 'danger'],
            ]],
            'permissions' => ['view' => 'permissions.index', 'key' => 'permissions', 'api' => 'admin_api_permissionTree', 'formApi' => 'admin_api_createPermission', 'formPermission' => 'admin_permission_create', 'formFields' => ['parent_id', 'name', 'slug', ['name' => 'guard_type', 'type' => 'select', 'options' => ['admin', 'front']], ['name' => 'type', 'type' => 'select', 'options' => [['value' => 1, 'label' => 'permission_directory'], ['value' => 2, 'label' => 'permission_page'], ['value' => 3, 'label' => 'permission_action']]], 'api_route', 'route', 'icon', 'sort', ['name' => 'status', 'type' => 'select', 'options' => [['value' => 1, 'label' => 'enabled'], ['value' => 0, 'label' => 'disabled']]]], 'rowActions' => [
                ['key' => 'update', 'route' => 'admin_api_updatePermission', 'permission' => 'admin_permission_update', 'fields' => ['parent_id', 'name', 'slug', ['name' => 'guard_type', 'type' => 'select', 'options' => ['admin', 'front']], ['name' => 'type', 'type' => 'select', 'options' => [['value' => 1, 'label' => 'permission_directory'], ['value' => 2, 'label' => 'permission_page'], ['value' => 3, 'label' => 'permission_action']]], 'api_route', 'route', 'icon', 'sort', ['name' => 'status', 'type' => 'select', 'options' => [['value' => 1, 'label' => 'enabled'], ['value' => 0, 'label' => 'disabled']]]]],
                ['key' => 'delete', 'route' => 'admin_api_deletePermission', 'permission' => 'admin_permission_delete', 'variant' => 'danger'],
            ]],
            'menus' => ['view' => 'menus.index', 'key' => 'menus', 'api' => 'admin_api_menuTree', 'formApi' => 'admin_api_createMenu', 'formFields' => $this->menuFields()],
            'data-scopes' => ['view' => 'data-scopes.index', 'key' => 'data_scopes', 'api' => 'admin_api_roleDataScopeList', 'formApi' => 'admin_api_saveRoleDataScope', 'formFields' => $this->dataScopeFields(), 'columns' => ['id', 'name', 'guard_type', 'data_scope.scope_type', 'data_scope.agent_ids', 'data_scope.user_ids', 'admin_id', 'agent_id', 'binding_type', 'status'], 'viewTabs' => [
                ['key' => 'role_scopes', 'label' => 'role_scopes', 'api' => 'admin_api_roleDataScopeList', 'method' => 'POST'],
                ['key' => 'admin_agent_bindings', 'label' => 'admin_agent_bindings', 'api' => 'admin_api_adminAgentBindingList', 'method' => 'POST'],
            ], 'rowActions' => [
                ['key' => 'save_scope', 'route' => 'admin_api_saveRoleDataScope', 'fields' => $this->dataScopeFields(), 'recordKey' => 'id', 'payloadName' => 'role_id', 'view' => 'role_scopes'],
                ['key' => 'save_binding', 'route' => 'admin_api_saveAdminAgentBinding', 'fields' => $this->adminAgentBindingFields(), 'view' => 'admin_agent_bindings'],
                ['key' => 'delete_binding', 'route' => 'admin_api_deleteAdminAgentBinding', 'variant' => 'danger', 'recordKey' => 'id', 'payloadName' => 'id', 'view' => 'admin_agent_bindings'],
            ]],
            'agents' => ['view' => 'agents.index', 'key' => 'agents', 'api' => 'admin_api_agentList', 'filters' => ['agent_id', 'user_name', 'start_date', 'end_date'], 'columns' => ['user_id', 'user_name', 'level_id', 'comm_rate', 'auth_status'], 'actions' => $this->exportActions('admin_api_exportAgents', 'agents_export.csv'), 'rowActions' => [
                ['key' => 'descendants', 'route' => 'admin_api_agentDescendants', 'permission' => 'admin_agent_descendants', 'recordKey' => 'user_id', 'payloadName' => 'agent_id'],
                ['key' => 'agent_stats', 'route' => 'admin_api_agentStatsList', 'permission' => 'admin_agent_stats', 'recordKey' => 'user_id', 'payloadName' => 'user_id'],
                ['key' => 'confirm_agent', 'route' => 'admin_api_confirmAgent', 'permission' => 'admin_agent_confirm', 'recordKey' => 'user_id', 'payloadName' => 'agent_id'],
                ['key' => 'reject_confirmation', 'route' => 'admin_api_rejectAgentConfirmation', 'permission' => 'admin_agent_reject_confirmation', 'fields' => [['name' => 'reason', 'type' => 'textarea']], 'recordKey' => 'user_id', 'payloadName' => 'agent_id', 'variant' => 'danger'],
                ['key' => 'update_level', 'route' => 'admin_api_updateAgentLevel', 'permission' => 'admin_agent_update_level', 'fields' => ['level'], 'recordKey' => 'user_id', 'payloadName' => 'agent_id'],
                ['key' => 'update_commission', 'route' => 'admin_api_updateAgentCommission', 'permission' => 'admin_agent_update_commission', 'fields' => [['name' => 'comm_rate', 'label' => 'commission_rate']], 'recordKey' => 'user_id', 'payloadName' => 'agent_id'],
            ]],
            'online-users' => ['view' => 'online-users.index', 'key' => 'online_users', 'api' => 'admin_api_onlineUserList', 'filters' => ['user_id', 'ip_address'], 'columns' => ['id', 'user_id', 'user_name', 'ip_address', 'last_activity', 'updated_at'], 'rowActions' => [
                ['key' => 'force_offline', 'route' => 'admin_api_forceOfflineUser', 'params' => ['id' => '__ID__'], 'variant' => 'danger'],
            ]],
            'authentications' => ['view' => 'authentications.index', 'key' => 'authentications', 'api' => 'admin_api_authPendingList', 'filters' => ['user_id', 'user_name', ['name' => 'auth_status', 'type' => 'select', 'options' => [['value' => 1, 'label' => 'auth_pending'], ['value' => 2, 'label' => 'auth_rejected']]], 'start_date', 'end_date'], 'columns' => ['user_id', 'user_name', 'id_card_no', 'id_card_status', 'review_bank_no', 'review_bank_name', 'review_bank_addr', 'bank_status', 'created_at'], 'viewTabs' => [
                ['key' => 'pending', 'label' => 'pending_auth', 'api' => 'admin_api_authPendingList', 'method' => 'POST', 'permission' => 'admin_auth_pending_list'],
                ['key' => 'certified', 'label' => 'certified_auth', 'api' => 'admin_api_authCertifiedList', 'method' => 'POST', 'permission' => 'admin_auth_certified_list'],
            ], 'rowActions' => [
                ['key' => 'review', 'route' => 'admin_api_reviewAuth', 'permission' => 'admin_user_review_auth', 'fields' => $this->authReviewFields(), 'recordKey' => 'user_id', 'payloadName' => 'user_id'],
            ]],
            'productions' => ['view' => 'productions.index', 'key' => 'productions', 'api' => 'admin_api_productionList', 'formApi' => 'admin_api_createProduction', 'formFields' => ['symbol', 'bid', 'ask', 'low', 'high', 'digits', 'spread', 'group_id', ['name' => 'status', 'type' => 'select', 'options' => [['value' => 1, 'label' => 'enabled'], ['value' => 0, 'label' => 'disabled']]]], /* 产量列必须与 Layui 家族同构：除品种行情字段外，还要展示旧产量报表的
                   买入/卖出均价、双向手数、净持仓与浮动盈亏。这些值由 admin_api_productionList
                   的同一份聚合查询产出，缺列会让 CrmUI 看不到旧后台已有的持仓口径数据。 */
                'columns' => ['id', 'symbol', 'bid', 'ask', 'spread', 'group_id', 'status', 'avg_buy_price', 'total_buy_volume', 'avg_sell_price', 'total_sell_volume', 'net_volume', 'float_profit_loss', 'modify_time'], 'actions' => $this->exportActions('admin_api_exportProductions', 'productions_export.csv'), 'rowActions' => [
                ['key' => 'update', 'route' => 'admin_api_updateProduction', 'params' => ['id' => '__ID__'], 'fields' => ['symbol', 'bid', 'ask', 'low', 'high', 'digits', 'spread', 'group_id', ['name' => 'status', 'type' => 'select', 'options' => [['value' => 1, 'label' => 'enabled'], ['value' => 0, 'label' => 'disabled']]]]],
                ['key' => 'delete', 'route' => 'admin_api_deleteProduction', 'params' => ['id' => '__ID__'], 'variant' => 'danger'],
            ]],
            'gifts' => ['view' => 'gifts.index', 'key' => 'gifts', 'api' => 'admin_api_giftShipmentList', 'filters' => ['user_id', 'gift_name', 'recipient_name', 'start_date', 'end_date'], 'columns' => ['id', 'user_id', 'gift_name', 'gift_quantity', 'recipient_name', 'recipient_phone', 'recipient_address', 'tracking_number', 'status', 'sender_name', 'admin_name', 'shipped_at'], 'actions' => $this->exportActions('admin_api_exportGiftShipments', 'gift_shipments_export.csv'), 'rowActions' => [
                ['key' => 'update_shipment', 'route' => 'admin_api_updateGiftShipment', 'params' => ['id' => '__ID__'], 'fields' => $this->giftShipmentStatusFields()],
            ]],
            'gift-items' => ['view' => 'gifts.index', 'key' => 'gift_items', 'api' => 'admin_api_giftItemList', 'formApi' => 'admin_api_createGiftItem', 'formFields' => $this->giftItemFields(), 'filters' => ['name', 'points_cost', 'status'], 'columns' => ['id', 'name', 'points_cost', 'stock_quantity', 'status', 'image_url', 'updated_at'], 'rowActions' => [
                ['key' => 'update', 'route' => 'admin_api_updateGiftItem', 'params' => ['id' => '__ID__'], 'fields' => $this->giftItemFields()],
                ['key' => 'delete', 'route' => 'admin_api_deleteGiftItem', 'params' => ['id' => '__ID__'], 'variant' => 'danger'],
            ]],
            'gift-addresses' => ['view' => 'gifts.index', 'key' => 'gift_addresses', 'api' => 'admin_api_giftAddressList', 'formApi' => 'admin_api_sendGift', 'formFields' => $this->giftAddressSendFields(), 'giftRecipientPicker' => true, 'filters' => ['user_id', 'recipient_name', 'recipient_phone', ['name' => 'is_default', 'type' => 'select', 'options' => [['value' => 1, 'label' => 'yes'], ['value' => 0, 'label' => 'no']]]], 'defaultFilters' => ['is_default' => 1], 'columns' => ['id', 'user_id', 'user_name', 'recipient_name', 'recipient_phone', 'recipient_address', 'is_default', 'updated_at'], 'rowActions' => [
                ['key' => 'select_gift_recipient', 'local' => true, 'recordKey' => 'id', 'payloadName' => 'address_id'],
            ]],
            // 入金统计指标（需求 9）：键名与 admin_api_depositList 返回的 summary 字段同名，
            // renderMetrics 会直接从 data.summary 取值，指标区独立于表格展示。
            'deposits' => ['view' => 'deposits.index', 'key' => 'deposits', 'api' => 'admin_api_depositList', 'filters' => ['local_order_no', 'user_id', ['name' => 'status', 'type' => 'select', 'options' => [['value' => '01', 'label' => 'deposit_pending'], ['value' => '02', 'label' => 'deposit_approved'], ['value' => '09', 'label' => 'deposit_rejected']]]], 'columns' => ['local_order_no', 'user_id', 'amount', 'actual_amount', 'status', 'created_at'], 'metrics' => ['total_records', 'total_deposit_amount', 'total_actual_amount', 'approved_records'], 'rowActions' => [
                ['key' => 'detail', 'route' => 'admin_api_depositDetail'],
                ['key' => 'approve', 'route' => 'admin_api_depositApprove'],
                ['key' => 'reject', 'route' => 'admin_api_depositReject', 'variant' => 'danger', 'fields' => [['name' => 'reason', 'type' => 'textarea', 'label' => 'reject_reason']]],
            ]],
            'deposit-imports' => ['view' => 'deposit-imports.index', 'key' => 'deposit_imports', 'api' => 'admin_api_depositImportList', 'formApi' => 'admin_api_createDepositImport', 'filters' => ['user_id', 'batch_no', ['name' => 'is_synced', 'type' => 'select', 'options' => $this->importSyncStatusOptions()]], 'columns' => $this->amountImportColumns(), 'formFields' => $this->amountImportFields(), 'actions' => $this->importActions('admin_api_depositImportTemplate', 'admin_api_exportDepositImports', 'deposit_import_template.csv', 'deposit_imports_export.csv', 'admin_api_createDepositImport', 'admin_batch_deposit_import_create'), 'rowActions' => [
                ['key' => 'sync_import', 'route' => 'admin_api_syncDepositImport', 'params' => ['id' => '__ID__'], 'recordKey' => 'id', 'payloadName' => 'id', 'confirm' => 'sync_import'],
                ['key' => 'retry_import', 'route' => 'admin_api_retryDepositImport', 'params' => ['id' => '__ID__'], 'recordKey' => 'id', 'payloadName' => 'id', 'confirm' => 'retry_import'],
            ]],
            'withdrawals' => $this->withdrawalPage(),
            'withdraw/pending' => $this->withdrawalPage(0),
            'withdraw/processing' => $this->withdrawalPage(1),
            'withdraw/completed' => $this->withdrawalPage(2),
            'withdraw/failed' => $this->withdrawalPage(3),
            'withdraw-imports' => ['view' => 'withdraw-imports.index', 'key' => 'withdraw_imports', 'api' => 'admin_api_withdrawImportList', 'formApi' => 'admin_api_createWithdrawImport', 'filters' => ['user_id', 'batch_no', ['name' => 'is_synced', 'type' => 'select', 'options' => $this->importSyncStatusOptions()]], 'columns' => $this->amountImportColumns(), 'formFields' => $this->amountImportFields(), 'actions' => $this->importActions('admin_api_withdrawImportTemplate', 'admin_api_exportWithdrawImports', 'withdraw_import_template.csv', 'withdraw_imports_export.csv', 'admin_api_createWithdrawImport', 'admin_batch_withdraw_import_create'), 'rowActions' => [
                ['key' => 'sync_import', 'route' => 'admin_api_syncWithdrawImport', 'params' => ['id' => '__ID__'], 'recordKey' => 'id', 'payloadName' => 'id', 'confirm' => 'sync_import'],
                ['key' => 'retry_import', 'route' => 'admin_api_retryWithdrawImport', 'params' => ['id' => '__ID__'], 'recordKey' => 'id', 'payloadName' => 'id', 'confirm' => 'retry_import'],
            ]],
            'withdraw-flows' => ['view' => 'withdraw-flows.index', 'key' => 'withdraw_flows', 'api' => 'admin_api_withdrawFlowList', 'filters' => ['user_id', 'ticket', 'withdraw_source', 'start_date', 'end_date'], 'columns' => ['ticket', 'login', 'user_name', 'profit', 'flow_source_name', 'comment', 'close_time'], 'actions' => $this->exportActions('admin_api_exportWithdrawFlows', 'withdraw_flows_export.csv')],
            'undeposit-flows' => ['view' => 'undeposit-flows.index', 'key' => 'undeposit_flows', 'api' => 'admin_api_undepositFlowList', 'columns' => ['order_no', 'user_id', 'amount', 'follow_status_name', 'pending_days', 'status', 'created_at'], 'actions' => $this->exportActions('admin_api_exportUndepositFlows', 'undeposit_flows_export.csv')],
            'never-deposit-users' => ['view' => 'undeposit-flows.index', 'key' => 'never_deposit_users', 'api' => 'admin_api_neverDepositUserList', 'filters' => ['user_id', 'user_name', 'start_date', 'end_date', 'min_days'], 'columns' => ['user_id', 'user_name', 'phone', 'email', 'parent_id', 'register_date', 'never_deposit_days']],
            'rights-summary' => ['view' => 'rights-summary.index', 'key' => 'rights_summary', 'api' => 'admin_api_rightsSummaryList', 'filters' => ['user_id', 'login', 'user_name', 'mt4_group', 'min_equity', 'max_equity'], 'columns' => ['user_id', 'balance', 'equity', 'margin', 'margin_free', 'leverage', 'settlement_amount', 'settlement_status', 'updated_at'], 'actions' => $this->exportActions('admin_api_exportRightsSummary', 'rights_summary_export.csv'), 'rowActions' => [
                ['key' => 'manual_confirm', 'route' => 'admin_api_manualConfirmRightsSettlement', 'params' => ['id' => '__ID__'], 'recordKey' => 'settlement_id', 'payloadName' => 'settlement_id', 'fields' => [['name' => 'manual_confirm_reason', 'type' => 'textarea']]],
            ]],
            'position-summary' => ['view' => 'position-summary.index', 'key' => 'position_summary', 'api' => 'admin_api_positionSummaryList', 'filters' => ['user_id', 'user_name', 'parent_id', 'account_type', 'start_date', 'end_date'], 'columns' => $this->positionSummaryColumns(), 'metrics' => $this->positionSummaryMetrics(), 'actions' => $this->exportActions('admin_api_exportPositionSummary', 'position_summary_export.csv'), 'rowActions' => [
                // 旧后台代理行钻取：CrmUI 点击后在本页重载，并向 PositionSummaryController 传入 subAgentsSearch 与父代理 ID。
                ['key' => 'position_summary_drilldown', 'local' => true, 'recordKey' => 'user_id', 'payloadName' => 'userPId', 'extraPayload' => ['searchtype' => 'subAgentsSearch']],
                // 交易明细下钻：跳到 CrmUI 交易订单页，并把当前行 user_id 与页面日期筛选继续向下传递。
                ['key' => 'position_summary_trades', 'local' => true, 'recordKey' => 'user_id', 'payloadName' => 'user_id', 'extraPayload' => ['mode' => 'all']],
                // 风险联动下钻：跳到 CrmUI 风控页，业务 user_id 由 RiskController 再映射为真实 MT4 登录号。
                ['key' => 'position_summary_risk', 'local' => true, 'recordKey' => 'user_id', 'payloadName' => 'user_id', 'extraPayload' => ['mode' => 'positions']],
            ]],
            'commissions' => ['view' => 'commissions.index', 'key' => 'commissions', 'api' => 'admin_api_commissionList', 'apiUrl' => url('/api/admin/commissions'), 'filters' => ['agent_id', ['name' => 'settle_status', 'type' => 'select', 'options' => [['value' => 1, 'label' => 'pending'], ['value' => 2, 'label' => 'settled']]]], 'viewTabs' => [
                ['key' => 'commission_records', 'label' => 'commission_records', 'api' => 'admin_api_commissionList', 'method' => 'POST'],
                ['key' => 'commission_transfer_reconciliation', 'label' => 'commission_transfer_reconciliation', 'api' => 'admin_api_commissionTransferReconciliationList', 'method' => 'POST', 'permission' => 'admin_commission_transfer_reconciliation_list'],
            ], 'columns' => ['id', 'agent_id', 'user_id', 'commission_amount', 'settle_status', 'source_user_id', 'target_user_id', 'amount', 'status', 'last_error_code', 'created_at'], 'actions' => ['refresh'], 'rowActions' => [
                ['key' => 'settle', 'route' => 'admin_api_commissionSettle', 'recordKey' => 'id', 'payloadName' => 'id', 'view' => 'commission_records', 'permission' => 'admin_commission_settle'],
                ['key' => 'detail', 'route' => 'admin_api_commissionTransferReconciliationDetail', 'params' => ['transfer' => '__ID__'], 'recordKey' => 'id', 'payloadName' => 'id', 'method' => 'GET', 'view' => 'commission_transfer_reconciliation', 'permission' => 'admin_commission_transfer_reconciliation_detail'],
                ['key' => 'reconcile_transfer', 'route' => 'admin_api_commissionTransferReconcile', 'params' => ['transfer' => '__ID__'], 'recordKey' => 'id', 'payloadName' => 'transfer', 'method' => 'POST', 'view' => 'commission_transfer_reconciliation', 'permission' => 'admin_commission_transfer_reconcile', 'fields' => [
                    ['name' => 'decision', 'type' => 'select', 'label' => 'decision', 'options' => [['value' => 'confirmed_completed', 'label' => 'confirmed_completed'], ['value' => 'confirmed_compensated', 'label' => 'confirmed_compensated'], ['value' => 'confirmed_rejected', 'label' => 'confirmed_rejected']]],
                    ['name' => 'external_reference', 'type' => 'text', 'label' => 'external_reference'],
                    ['name' => 'withdraw_status', 'type' => 'select', 'label' => 'withdraw_status', 'options' => [['value' => 'confirmed_not_processed', 'label' => 'confirmed_not_processed'], ['value' => 'confirmed_processed', 'label' => 'confirmed_processed'], ['value' => 'confirmed_rejected', 'label' => 'confirmed_rejected']]],
                    ['name' => 'withdraw_reference', 'type' => 'text', 'label' => 'withdraw_reference'],
                    ['name' => 'deposit_status', 'type' => 'select', 'label' => 'deposit_status', 'options' => [['value' => 'confirmed_not_processed', 'label' => 'confirmed_not_processed'], ['value' => 'confirmed_processed', 'label' => 'confirmed_processed'], ['value' => 'confirmed_rejected', 'label' => 'confirmed_rejected']]],
                    ['name' => 'deposit_reference', 'type' => 'text', 'label' => 'deposit_reference'],
                    ['name' => 'compensation_status', 'type' => 'select', 'label' => 'compensation_status', 'options' => [['value' => 'confirmed_not_processed', 'label' => 'confirmed_not_processed'], ['value' => 'confirmed_processed', 'label' => 'confirmed_processed'], ['value' => 'confirmed_rejected', 'label' => 'confirmed_rejected']]],
                    ['name' => 'compensation_reference', 'type' => 'text', 'label' => 'compensation_reference'],
                    ['name' => 'source_balance_after', 'type' => 'text', 'label' => 'source_balance_after'],
                    ['name' => 'target_balance_after', 'type' => 'text', 'label' => 'target_balance_after'],
                ]],
            ]],
            // 实时返佣展示旧 COMMENT 识别结果和 modify_time，便于财务核对返佣来源与 MT4 入账时间。
            'realtime-commissions' => ['view' => 'realtime-commissions.index', 'key' => 'realtime_commissions', 'api' => 'admin_api_realtimeCommissionList', 'columns' => ['ticket', 'login', 'symbol', 'profit', 'rebate_source_name', 'comment', 'modify_time'], 'actions' => $this->exportActions('admin_api_exportRealtimeCommissions', 'realtime_commissions_export.csv')],
            'credit-imports' => ['view' => 'credit-imports.index', 'key' => 'credit_imports', 'api' => 'admin_api_creditImportList', 'formApi' => 'admin_api_createCreditImport', 'filters' => ['user_id', 'batch_no', ['name' => 'credit_type', 'type' => 'select', 'options' => $this->creditTypeOptions()], ['name' => 'is_synced', 'type' => 'select', 'options' => $this->importSyncStatusOptions()]], 'columns' => $this->creditImportColumns(), 'formFields' => $this->creditImportFields(), 'actions' => $this->importActions('admin_api_creditImportTemplate', 'admin_api_exportCreditImports', 'credit_import_template.csv', 'credit_imports_export.csv', 'admin_api_createCreditImport', 'admin_batch_credit_import_create'), 'rowActions' => [
                ['key' => 'sync_import', 'route' => 'admin_api_syncCreditImport', 'params' => ['id' => '__ID__'], 'recordKey' => 'id', 'payloadName' => 'id', 'confirm' => 'sync_import'],
                ['key' => 'retry_import', 'route' => 'admin_api_retryCreditImport', 'params' => ['id' => '__ID__'], 'recordKey' => 'id', 'payloadName' => 'id', 'confirm' => 'retry_import'],
            ]],
            'agent-levels' => ['view' => 'agent-levels.index', 'key' => 'agent_levels', 'api' => 'admin_api_agentLevelList', 'apiUrl' => url('/api/admin/agent-levels'), 'formApi' => 'admin_api_createAgentLevel', 'formFields' => $this->agentLevelFields(), 'columns' => ['id', 'level_code', 'name', 'max_commission', 'min_commission', 'user_commission'], 'rowActions' => [
                ['key' => 'update', 'route' => 'admin_api_updateAgentLevel2', 'params' => ['id' => '__ID__'], 'fields' => $this->agentLevelFields()],
                ['key' => 'delete', 'route' => 'admin_api_deleteAgentLevel', 'params' => ['id' => '__ID__'], 'variant' => 'danger'],
            ]],
            'group-configs' => ['view' => 'group-configs.index', 'key' => 'group_configs', 'api' => 'admin_api_groupConfigList', 'apiUrl' => url('/api/admin/group-configs'), 'formApi' => 'admin_api_createGroupConfig', 'formPermission' => 'admin_group_config_create', 'formFields' => $this->groupConfigFields(), 'columns' => ['id', 'name', 'radix', 'category', 'is_enabled', 'updated_at'], 'rowActions' => [
                ['key' => 'update', 'route' => 'admin_api_updateGroupConfig', 'permission' => 'admin_group_config_update', 'params' => ['id' => '__ID__'], 'fields' => $this->groupConfigFields()],
                ['key' => 'delete', 'route' => 'admin_api_deleteGroupConfig', 'permission' => 'admin_group_config_delete', 'params' => ['id' => '__ID__'], 'variant' => 'danger'],
            ]],
            'system-configs' => ['view' => 'system-configs.index', 'key' => 'system_configs', 'api' => 'admin_api_systemConfigList', 'formApi' => 'admin_api_updateSystemConfig', 'formFields' => $this->systemConfigFields()],
            /* 汇率与出金手续费配置。
               formFields 的字段名必须与 system_configs.key 完全一致：fields() 原样把 key 用作
               表单 name，而 admin_api_updateExchangeRate 按 sys_deposit_rate / sys_draw_rate
               做 required 校验。此前这里写的是 deposit_rate / withdraw_rate，
               提交必然因缺少必填字段而返回 VALIDATION_FAILED —— 即该页保存从未成功过。
               手续费三项：withdrawal_fee_enabled 为总开关（select 0/1，避免 checkbox 未勾选
               时字段整体缺失导致无法关闭），另两项为金额。 */
            'exchange-rates' => ['view' => 'exchange-rates.index', 'key' => 'exchange_rates', 'api' => 'admin_api_exchangeRateInfo', 'formApi' => 'admin_api_updateExchangeRate', 'formFields' => [
                ['name' => 'sys_deposit_rate', 'type' => 'number', 'required' => true],
                ['name' => 'sys_draw_rate', 'type' => 'number', 'required' => true],
                ['name' => 'withdrawal_fee_enabled', 'type' => 'select', 'options' => [
                    ['value' => 1, 'label' => 'fee_charge_on'],
                    ['value' => 0, 'label' => 'fee_charge_off'],
                ]],
                ['name' => 'withdrawal_fixed_fee_usd', 'type' => 'number'],
                ['name' => 'withdrawal_fee_rate', 'type' => 'number'],
            ]],
            // 支付通道：按启用状态分成三个页签展示（需求 8）。三个页签共用 admin_api_channelList，
            // 只用固定 status 查询参数收窄结果集，新增/编辑/启停/删除动作保持不变。
            'channels' => ['view' => 'channels.index', 'key' => 'channels', 'api' => 'admin_api_channelList', 'apiUrl' => url('/api/admin/channels'), 'formApi' => 'admin_api_createChannel', 'formFields' => $this->paymentChannelFields(), 'columns' => ['id', 'name', 'channel_code', 'exchange_rate', 'is_enabled', 'sort'], 'viewTabs' => [
                ['key' => 'channel_all', 'label' => 'channel_tab_all', 'api' => 'admin_api_channelList', 'method' => 'POST'],
                ['key' => 'channel_enabled', 'label' => 'channel_tab_enabled', 'api' => 'admin_api_channelList', 'method' => 'POST', 'query' => ['status' => 1]],
                ['key' => 'channel_disabled', 'label' => 'channel_tab_disabled', 'api' => 'admin_api_channelList', 'method' => 'POST', 'query' => ['status' => 0]],
            ], 'rowActions' => [
                ['key' => 'update', 'route' => 'admin_api_updateChannel', 'params' => ['id' => '__ID__'], 'fields' => $this->paymentChannelFields()],
                ['key' => 'toggle', 'route' => 'admin_api_toggleChannel', 'params' => ['id' => '__ID__'], 'recordKey' => 'id', 'payloadName' => 'id'],
                ['key' => 'delete', 'route' => 'admin_api_deleteChannel', 'params' => ['id' => '__ID__'], 'variant' => 'danger'],
            ]],
            'admins' => ['view' => 'admins.index', 'key' => 'admins', 'api' => 'admin_api_adminList', 'apiUrl' => url('/api/admin/admins'), 'formApi' => 'admin_api_createAdmin', 'formPermission' => 'admin_admin_create', 'formFields' => ['username', ['name' => 'email', 'type' => 'email'], 'password', 'mobile', ['name' => 'role_id', 'type' => 'number'], ['name' => 'status', 'type' => 'select', 'options' => [['value' => 1, 'label' => 'enabled'], ['value' => 0, 'label' => 'disabled']]]], 'columns' => ['id', 'username', 'email', 'mobile', 'role_id', 'status', 'created_at'], 'rowActions' => [
                ['key' => 'update', 'route' => 'admin_api_updateAdmin', 'permission' => 'admin_admin_update', 'params' => ['id' => '__ID__'], 'fields' => ['username', ['name' => 'email', 'type' => 'email'], 'password', 'mobile', ['name' => 'role_id', 'type' => 'number'], ['name' => 'status', 'type' => 'select', 'options' => [['value' => 1, 'label' => 'enabled'], ['value' => 0, 'label' => 'disabled']]]]],
                ['key' => 'reset_password', 'route' => 'admin_api_resetAdminPassword', 'permission' => 'admin_admin_reset_password', 'params' => ['id' => '__ID__'], 'fields' => [['name' => 'password', 'type' => 'password']]],
                ['key' => 'delete', 'route' => 'admin_api_deleteAdmin', 'permission' => 'admin_admin_delete', 'params' => ['id' => '__ID__'], 'variant' => 'danger'],
            ]],
            'news' => ['view' => 'news.index', 'key' => 'news', 'api' => 'admin_api_newsList', 'apiUrl' => url('/api/admin/news'), 'formApi' => 'admin_api_createNews', 'formPermission' => 'admin_news_create', 'formFields' => $this->newsFields(), 'filters' => ['title', 'start_date', 'end_date', ['name' => 'is_published', 'type' => 'select', 'options' => [['value' => 1, 'label' => 'published'], ['value' => 0, 'label' => 'unpublished']]]], 'columns' => ['id', 'title', 'is_published', 'created_at'], 'actions' => ['refresh', ['key' => 'create', 'permission' => 'admin_news_create']], 'rowActions' => [
                ['key' => 'update', 'route' => 'admin_api_updateNews', 'permission' => 'admin_news_update', 'params' => ['id' => '__ID__'], 'fields' => $this->newsFields()],
                ['key' => 'toggle', 'route' => 'admin_api_toggleNews', 'permission' => 'admin_news_toggle', 'params' => ['id' => '__ID__'], 'recordKey' => 'id', 'payloadName' => 'id'],
                ['key' => 'delete', 'route' => 'admin_api_deleteNews', 'permission' => 'admin_news_delete', 'params' => ['id' => '__ID__'], 'variant' => 'danger'],
            ]],
            'vouchers' => ['view' => 'vouchers.index', 'key' => 'vouchers', 'api' => 'admin_api_voucherList', 'filters' => [['name' => 'review_status', 'type' => 'select', 'options' => [['value' => 0, 'label' => 'pending'], ['value' => 1, 'label' => 'approved'], ['value' => 2, 'label' => 'rejected']]]], 'columns' => ['images', 'user_id', 'review_status', 'review_message', 'created_at'], 'rowActions' => [
                ['key' => 'approve', 'route' => 'admin_api_voucherApprove', 'params' => ['id' => '__ID__'], 'recordKey' => 'id', 'payloadName' => 'id'],
                ['key' => 'reject', 'route' => 'admin_api_voucherReject', 'params' => ['id' => '__ID__'], 'variant' => 'danger', 'fields' => [['name' => 'reason', 'type' => 'textarea', 'label' => 'reject_reason']], 'recordKey' => 'id', 'payloadName' => 'id'],
            ]],
            'risk' => $this->riskPageDefinition(),
            'risk/profit' => $this->riskPageDefinition('profit'),
            'risk/positions' => $this->riskPageDefinition('positions'),
            'risk/ip-risk' => $this->riskPageDefinition('ipRisk'),
            'whs-exp-zero' => ['view' => 'whs-exp-zero.index', 'key' => 'whs_exp_zero', 'api' => 'admin_api_whsExpZeroList', 'filters' => ['user_id', 'user_name', ['name' => 'status', 'type' => 'select', 'options' => [['value' => 0, 'label' => 'zero_processing'], ['value' => 1, 'label' => 'zero_pending'], ['value' => 2, 'label' => 'zero_completed'], ['value' => 3, 'label' => 'zero_failed']]], 'start_date', 'end_date'], 'columns' => ['userId', 'userName', 'userBalance', 'userCredit', 'needZeroAmount', 'id', 'user_id', 'user_name', 'balance_before', 'credit_amount', 'zero_amount', 'status_name', 'fail_reason', 'created_at', 'processed_at'], 'viewTabs' => [
                ['key' => 'zero_candidates', 'label' => 'zero_candidates', 'api' => 'admin_api_whsExpZeroList', 'method' => 'POST'],
                ['key' => 'zero_records', 'label' => 'zero_records', 'api' => 'admin_api_whsExpZeroRecords', 'method' => 'POST', 'permission' => 'admin_whs_exp_zero_records'],
            ], 'rowActions' => [
                ['key' => 'one_key_zero', 'route' => 'admin_api_whsExpZero', 'permission' => 'admin_whs_exp_zero', 'variant' => 'danger', 'recordKey' => 'userId', 'payloadName' => 'user_id', 'view' => 'zero_candidates'],
            ]],
            'blacklist' => ['view' => 'blacklist.index', 'key' => 'blacklist', 'api' => 'admin_api_blacklistList', 'formApi' => 'admin_api_createBlacklist', 'formPermission' => 'admin_blacklist_create', 'filters' => ['keyword'], 'columns' => ['name', 'id_card', 'email', 'phone'], 'formFields' => ['name', 'id_card', ['name' => 'email', 'type' => 'email'], 'phone', 'remark'], 'rowActions' => [
                ['key' => 'update', 'route' => 'admin_api_updateBlacklist', 'permission' => 'admin_blacklist_update', 'params' => ['id' => '__ID__'], 'fields' => ['name', 'id_card', ['name' => 'email', 'type' => 'email'], 'phone', 'remark']],
                ['key' => 'delete', 'route' => 'admin_api_deleteBlacklist', 'permission' => 'admin_blacklist_delete', 'params' => ['id' => '__ID__'], 'variant' => 'danger'],
            ]],
            'cancel-applies' => ['view' => 'cancel-applies.index', 'key' => 'cancel_applies', 'api' => 'admin_api_cancelApplyList', 'filters' => ['user_id', ['name' => 'status', 'type' => 'select', 'options' => [['value' => 0, 'label' => 'pending'], ['value' => 1, 'label' => 'approved'], ['value' => -1, 'label' => 'rejected']]], 'start_date', 'end_date'], 'defaultFilters' => ['status' => 0], 'columns' => ['user_id', 'user_name', 'balance', 'open_positions', 'cancel_remark', 'reject_reason', 'status', 'created_at'], 'rowActions' => [
                ['key' => 'approve', 'route' => 'admin_api_cancelApplyApprove', 'permission' => 'admin_cancel_apply_approve', 'params' => ['id' => '__ID__'], 'fields' => [['name' => 'reason', 'type' => 'textarea', 'label' => 'review_remark', 'required' => true, 'maxlength' => 500]], 'visibleWhen' => ['status' => 0], 'recordKey' => 'id', 'payloadName' => 'id'],
                ['key' => 'reject', 'route' => 'admin_api_cancelApplyReject', 'permission' => 'admin_cancel_apply_reject', 'params' => ['id' => '__ID__'], 'variant' => 'danger', 'fields' => [['name' => 'reason', 'type' => 'textarea', 'label' => 'review_remark', 'required' => true, 'maxlength' => 500]], 'visibleWhen' => ['status' => 0], 'recordKey' => 'id', 'payloadName' => 'id'],
            ]],
            // 交易订单：保留旧后台强平筛选与 COMMENT 字段，CrmUI 统一渲染全部交易、当前持仓和历史平仓三个接口。
            'trades' => ['view' => 'trades.index', 'key' => 'trades', 'api' => 'admin_api_tradeList', 'filters' => ['user_id', 'ticket', 'symbol', 'start_date', 'end_date', ['name' => 'is_coercion', 'type' => 'select', 'options' => [['value' => 'Yes', 'label' => 'force_close_order'], ['value' => 'No', 'label' => 'non_force_close_order']]], ['name' => 'orderType', 'type' => 'select', 'options' => [['value' => 'real_disk', 'label' => 'real_disk'], ['value' => 'test_disk', 'label' => 'test_disk']]]], 'columns' => ['login', 'ticket', 'symbol', 'cmd', 'volume', 'commission', 'swaps', 'profit', 'comment', 'ordercomment', 'open_time', 'close_time', 'modify_time'], 'metrics' => ['total_orders', 'total_volume', 'total_profit', 'total_swaps', 'total_commission'], 'actions' => $this->exportActions('admin_api_exportClosedPositions', 'closed_positions_export.csv'), 'viewTabs' => [
                ['key' => 'all', 'label' => 'all_trades', 'api' => 'admin_api_tradeList', 'method' => 'POST'],
                ['key' => 'open', 'label' => 'open_positions', 'api' => 'admin_api_openPositions', 'method' => 'POST'],
                ['key' => 'closed', 'label' => 'closed_positions', 'api' => 'admin_api_closedPositions', 'method' => 'POST'],
            ]],
            'big-agents' => ['view' => 'big-agents.index', 'key' => 'big_agents', 'api' => 'admin_api_bigAgentList', 'formApi' => 'admin_api_createBigAgent', 'formPermission' => 'admin_big_agent_create', 'formFields' => $this->bigAgentFields(), 'columns' => ['id', 'username', 'email', 'sub_agent_ids', 'is_enabled', 'created_at'], 'rowActions' => [
                ['key' => 'update', 'route' => 'admin_api_updateBigAgent', 'permission' => 'admin_big_agent_update', 'params' => ['id' => '__ID__'], 'fields' => $this->bigAgentFields()],
                ['key' => 'delete', 'route' => 'admin_api_deleteBigAgent', 'permission' => 'admin_big_agent_delete', 'params' => ['id' => '__ID__'], 'variant' => 'danger'],
            ]],
            'profile/edit' => ['view' => 'profile.edit', 'key' => 'profile_edit', 'api' => 'admin_api_profileInfo', 'formApi' => 'admin_api_updateProfile', 'mode' => 'form', 'formFields' => ['email', 'mobile']],
            'profile/change-password' => ['view' => 'profile.change-password', 'key' => 'profile_password', 'formApi' => 'admin_api_changePassword', 'mode' => 'form', 'formFields' => ['old_password', 'password', 'password_confirmation']],
        ];
    }

    /**
     * 构建 canonical 或固定单模式风控页定义。
     *
     * @param string|null $fixedMode 固定模式；null 表示 canonical 四页签页面。
     * @return array<string, mixed>
     */
    private function riskPageDefinition(?string $fixedMode = null): array
    {
        $modes = [
            'profit' => [
                'key' => 'profit',
                'label' => 'profit_risk',
                'api' => 'admin_api_riskProfitUsers',
                'permission' => 'admin_risk_profit_users',
                'columns' => [
                    'user_id', 'user_name', 'mt4_login', 'mt4_name', 'mt4_balance',
                    'mt4_equity', 'total_comm', 'total_volume', 'total_swaps',
                    'total_profit', 'total_net_worth', 'feng_xian_val', 'mt4_regdate',
                ],
            ],
            'positions' => [
                'key' => 'positions',
                'label' => 'risk_positions',
                'api' => 'admin_api_riskPositions',
                'permission' => 'admin_risk_positions',
                'columns' => [
                    'login', 'user_name', 'ticket', 'symbol', 'volume', 'commission',
                    'profit', 'risk_value', 'open_time',
                ],
            ],
            'marginCalls' => [
                'key' => 'margin_calls',
                'label' => 'margin_calls',
                'api' => 'admin_api_riskMarginCalls',
                'permission' => 'admin_risk_margin_calls',
                'columns' => [
                    'login', 'user_id', 'user_name', 'group', 'balance', 'equity',
                    'margin', 'margin_free', 'margin_level', 'leverage',
                ],
            ],
            'ipRisk' => [
                'key' => 'ip_risk',
                'label' => 'risk_ip',
                'api' => 'admin_api_riskIpList',
                'permission' => 'admin_risk_ip_list',
                'columns' => [
                    'login_ip', 'distinct_user_count', 'login_count',
                    'latest_login_at', 'sample_user_name',
                ],
            ],
        ];
        $selectedMode = isset($modes[$fixedMode]) ? $fixedMode : 'positions';
        $visibleModes = $fixedMode === null ? $modes : [$selectedMode => $modes[$selectedMode]];
        $rowActions = [
            ['key' => 'force_close', 'route' => 'admin_api_riskForceClose', 'permission' => 'admin_risk_force_close', 'params' => ['id' => '__ID__'], 'variant' => 'danger', 'recordKey' => 'force_close_id', 'payloadName' => 'id', 'view' => 'positions'],
            ['key' => 'ip_detail', 'route' => 'admin_api_riskIpDetail', 'permission' => 'admin_risk_ip_detail', 'fields' => ['login_ip'], 'recordKey' => 'login_ip', 'payloadName' => 'login_ip', 'view' => 'ip_risk'],
        ];

        if ($fixedMode !== null) {
            $activeView = $modes[$selectedMode]['key'];
            $rowActions = array_values(array_filter($rowActions, static function (array $action) use ($activeView): bool {
                return ($action['view'] ?? '') === $activeView;
            }));
        }

        return [
            'view' => 'risk.index',
            'key' => 'risk',
            'api' => $modes[$selectedMode]['api'],
            'defaultRiskMode' => $selectedMode,
            'filters' => [
                'user_id',
                'user_name',
                'ticket',
                'symbol',
                ['name' => 'order_type', 'type' => 'select', 'options' => [
                    ['value' => 'real_disk', 'label' => 'real_disk'],
                    ['value' => 'test_disk', 'label' => 'test_disk'],
                ]],
                'start_date',
                'end_date',
                'login',
                'max_margin_level',
                'login_ip',
                'min_user_count',
            ],
            'columns' => $modes[$selectedMode]['columns'],
            'metrics' => ['total_records', 'total_profit', 'total_volume', 'total_risk_value', 'total_margin'],
            'viewTabs' => array_map(static function (array $mode): array {
                return $mode + ['method' => 'POST'];
            }, array_values($visibleModes)),
            'rowActions' => $rowActions,
        ];
    }

    /**
     * 生成提现列表页 definition,可按状态生成带默认筛选的分页。
     *
     * 汇总入口 withdrawals 不传 status;withdraw/{pending|processing|completed|failed} 传入对应状态值,
     * 使页面打开即按该状态筛选。
     *
     * @param int|null $status 提现状态值(0 待处理/1 处理中/2 已完成/3 已拒绝),null 表示不做默认筛选。
     * @return array<string, mixed> 提现页 definition。
     */
    private function withdrawalPage(int $status = null): array
    {
        $statusFilter = $status === null
            ? ['name' => 'status', 'type' => 'select', 'options' => [['value' => 0, 'label' => 'withdraw_pending'], ['value' => 1, 'label' => 'withdraw_processing'], ['value' => 2, 'label' => 'withdraw_completed'], ['value' => 3, 'label' => 'withdraw_rejected']]]
            : ['name' => 'status', 'type' => 'hidden'];
        $page = [
            'view' => 'withdrawals.index',
            'key' => 'withdrawals',
            'api' => 'admin_api_withdrawList',
            'apiUrl' => url('/api/admin/withdrawals'),
            'filters' => ['local_order_no', 'mt4_ticket', 'user_id', 'start_date', 'end_date', $statusFilter],
            'columns' => ['local_order_no', 'mt4_ticket', 'user_id', 'user_name', 'apply_amount', 'actual_amount', 'fee', 'status', 'reject_reason', 'created_at'],
            // 出金统计指标（需求 9）：键名与 admin_api_withdrawList 返回的 summary 字段同名，
            // renderMetrics 会直接从 data.summary 取值，指标区独立于表格展示。
            'metrics' => ['total_records', 'total_withdraw_amount', 'total_actual_amount', 'total_fee', 'completed_records'],
            'actions' => $this->exportActions('admin_api_exportWithdrawals', 'withdrawals_export.csv'),
            'rowActions' => [
                ['key' => 'detail', 'local' => true],
                ['key' => 'process', 'route' => 'admin_api_withdrawProcess', 'permission' => 'admin_withdraw_process', 'recordKey' => 'id', 'payloadName' => 'id'],
                ['key' => 'complete', 'route' => 'admin_api_withdrawComplete', 'permission' => 'admin_withdraw_complete', 'recordKey' => 'id', 'payloadName' => 'id'],
                ['key' => 'reject', 'route' => 'admin_api_withdrawReject', 'permission' => 'admin_withdraw_reject', 'recordKey' => 'id', 'payloadName' => 'id', 'variant' => 'danger', 'fields' => [['name' => 'reason', 'type' => 'textarea', 'label' => 'reject_reason']]],
            ],
            // 出金批量审核声明：对齐旧后台四个出金状态页各自的「批量操作」入口。
            // 仅声明了 batch 键的页面才会渲染勾选列与批量弹窗，其余 CrmUI 页面完全走原路径。
            'batch' => $this->withdrawBatchDefinition(),
        ];

        if ($status !== null) {
            $page['fixedFilters'] = ['status' => (string) $status];
        }

        return $page;
    }

    /**
     * 出金批量审核声明，对齐旧后台四个出金状态页各自的「批量操作」入口。
     *
     * 复用旧 URI index/admin/amount/batchWithdrawApply 而不新增现代端点的原因：
     * - 该入口已具备 Session 与 JWT 双认证通道（LegacyAdminAuthenticate），CrmUI 的 JWT 可直接使用；
     * - 授权按 payload.status 动态改判为 admin_api_withdrawProcess/Complete/Reject，
     *   与单条操作共用同一批权限记录，无需新增 permissions 行，也就不动生产权限表；
     * - 后端逐条转发到现代出金状态机，行锁、资金状态、退款 outbox 规则全部保持单一事实源。
     *
     * transitions 与 remarkRequired 复刻旧页面 updateRadioButtons 的约束：
     * 来源 0（待处理）可流转到 1/2/3，来源 1（处理中）只能到 2/3；
     * 目标 3（拒绝）必须填备注，否则后端 reject() 会因 reason 为空而整批失败。
     *
     * @return array<string, mixed> 批量声明，交由 batch() 归一化为渲染结构。
     */
    private function withdrawBatchDefinition(): array
    {
        return [
            'key' => 'batch_withdraw',
            'legacyUri' => '/index/admin/amount/batchWithdrawApply',
            'method' => 'POST',
            // 权限 slug 只用于前端隐藏无处理权限的管理员；真正的授权由后端按目标状态动态改判。
            'permission' => 'admin_withdraw_process',
            'recordKey' => 'id',
            'sourceStatusField' => 'status',
            'transitions' => [
                '0' => ['1', '2', '3'],
                '1' => ['2', '3'],
            ],
            'targetStatuses' => [
                ['value' => 1, 'label' => 'admin.processing'],
                ['value' => 2, 'label' => 'admin.completed'],
                ['value' => 3, 'label' => 'admin.rejected', 'remarkRequired' => true],
            ],
        ];
    }

    /**
     * 导入类页面通用操作声明:刷新、下载模板、导出、新建导入、CSV 批量导入。
     *
     * @param string $templateRoute 模板下载路由名。
     * @param string $exportRoute 导出路由名。
     * @param string $templateFileName 模板下载文件名。
     * @param string $exportFileName 导出文件名。
     * @param string $createRoute CSV 批量导入端点路由名（与单条新增共用同一后端契约）。
     * @param string $createPermission CSV 批量导入按钮的权限 slug。
     * @return array<int, mixed> CrmUI 页面级操作声明列表。
     */
    private function importActions(string $templateRoute, string $exportRoute, string $templateFileName, string $exportFileName, string $createRoute = '', string $createPermission = ''): array
    {
        return [
            'refresh',
            ['key' => 'template', 'route' => $templateRoute, 'fileName' => $templateFileName],
            ['key' => 'export', 'route' => $exportRoute, 'fileName' => $exportFileName],
            'create',
            ['key' => 'import', 'route' => $createRoute, 'permission' => $createPermission],
        ];
    }

    /**
     * 入金/出金导入列表列,两个导入页面共用同一套列结构。
     *
     * @return array<int, string> CrmUI 表格列 key。
     */
    private function amountImportColumns(): array
    {
        return ['id', 'user_id', 'user_name', 'amount', 'batch_no', 'mt4_order_id', 'is_synced', 'fail_reason', 'created_at'];
    }

    /**
     * 授信导入列表列,较金额导入多出 credit_type 列。
     *
     * @return array<int, string> CrmUI 表格列 key。
     */
    private function creditImportColumns(): array
    {
        return ['id', 'user_id', 'user_name', 'credit_type', 'amount', 'batch_no', 'mt4_order_id', 'is_synced', 'fail_reason', 'created_at'];
    }

    /**
     * 后台持仓汇总页面展示列。
     *
     * 这些字段对应 PositionSummaryController 返回的代理树汇总结果：
     * 用户基础字段用于定位代理/客户，total_* 字段用于展示该行用户及其可汇总下级的 MT4 交易聚合。
     *
     * @return array<int, string> CrmUI 表格列 key，顺序同时决定页面表头展示顺序。
     */
    private function positionSummaryColumns(): array
    {
        return [
            'user_id',
            'user_name',
            'parent_id',
            'account_type',
            'mt4_group',
            'mt4_login',
            'mt4_name',
            'mt4_account_group',
            'mt4_balance',
            'mt4_equity',
            'mt4_margin',
            'mt4_margin_free',
            'mt4_leverage',
            'mt4_registered_at',
            'mt4_snapshot_at',
            'total_orders',
            'total_volume',
            'total_profit',
            'total_comm',
            'total_swaps',
            'total_noble_metal',
            'total_for_exca',
            'total_crud_oil',
            'total_index',
            'total_currency',
            'total_stock',
        ];
    }

    /**
     * 后台持仓汇总页顶部指标字段。
     *
     * 仅使用后端 summary 中真实计算出的总账号、订单、手数、盈亏、手续费和库存费，
     * 避免沿用通用 total/pending/completed 指标造成业务含义错误。
     *
     * @return array<int, string> CrmUI 指标 key，页面脚本会按 key 从接口 summary 中取值。
     */
    private function positionSummaryMetrics(): array
    {
        return ['total_accounts', 'total_mt4_accounts', 'total_balance', 'total_equity', 'total_margin', 'total_margin_free', 'total_orders', 'total_volume', 'total_profit', 'total_comm', 'total_swaps'];
    }

    /**
     * 导入同步状态筛选选项:0 待同步 / 1 已同步 / 2 同步失败。
     *
     * @return array<int, array{value: int, label: string}> 筛选下拉选项,label 为翻译 key。
     */
    private function importSyncStatusOptions(): array
    {
        return [
            ['value' => 0, 'label' => 'import_pending'],
            ['value' => 1, 'label' => 'import_synced'],
            ['value' => 2, 'label' => 'import_failed'],
        ];
    }

    /**
     * 授信类型选项:临时授信/永久授信/赠金授信/其他。
     *
     * @return array<int, array{value: int, label: string}> 下拉选项,label 为翻译 key。
     */
    private function creditTypeOptions(): array
    {
        return [
            ['value' => 1, 'label' => 'credit_type_temporary'],
            ['value' => 2, 'label' => 'credit_type_permanent'],
            ['value' => 3, 'label' => 'credit_type_bonus'],
            ['value' => 4, 'label' => 'credit_type_other'],
        ];
    }

    /**
     * 入金/出金导入表单字段:用户、金额、批次号、MT4 订单号与备注。
     *
     * @return array<int, mixed> 表单字段声明。
     */
    private function amountImportFields(): array
    {
        return [
            'user_id',
            'user_name',
            ['name' => 'amount', 'type' => 'number'],
            'batch_no',
            ['name' => 'mt4_order_id', 'type' => 'number'],
            ['name' => 'remarks', 'type' => 'textarea'],
        ];
    }

    /**
     * 授信导入表单字段,额外包含 credit_type 类型选择。
     *
     * @return array<int, mixed> 表单字段声明。
     */
    private function creditImportFields(): array
    {
        return [
            'user_id',
            'user_name',
            ['name' => 'credit_type', 'type' => 'select', 'options' => $this->creditTypeOptions()],
            ['name' => 'amount', 'type' => 'number'],
            'batch_no',
            ['name' => 'mt4_order_id', 'type' => 'number'],
            ['name' => 'remarks', 'type' => 'textarea'],
        ];
    }

    /**
     * 实名认证组件审核字段，身份证与银行卡分别提交结论和拒绝原因。
     *
     * @return array<int, mixed> 表单字段声明。
     */
    private function authReviewFields(): array
    {
        $decisions = [
            ['value' => 1, 'label' => 'approved'],
            ['value' => 2, 'label' => 'rejected'],
        ];

        return [
            ['name' => 'id_card_decision', 'type' => 'select', 'label' => 'id_card_decision', 'options' => $decisions],
            ['name' => 'id_card_reason', 'type' => 'textarea', 'label' => 'id_card_reason', 'maxlength' => 500],
            ['name' => 'bank_decision', 'type' => 'select', 'label' => 'bank_decision', 'options' => $decisions],
            ['name' => 'bank_reason', 'type' => 'textarea', 'label' => 'bank_reason', 'maxlength' => 500],
        ];
    }

    /**
     * 角色创建/编辑共用表单字段:名称、guard 类型、描述与状态。
     *
     * @return array<int, mixed> 表单字段声明。
     */
    private function roleFields(): array
    {
        return [
            'name',
            ['name' => 'guard_type', 'type' => 'select', 'options' => ['admin', 'front']],
            ['name' => 'description', 'type' => 'textarea'],
            ['name' => 'status', 'type' => 'select', 'options' => [
                ['value' => 1, 'label' => 'enabled'],
                ['value' => 0, 'label' => 'disabled'],
            ]],
        ];
    }

    /**
     * 菜单(权限节点)创建表单字段,type 区分目录/页面/操作三类节点。
     *
     * @return array<int, mixed> 表单字段声明。
     */
    private function menuFields(): array
    {
        return [
            ['name' => 'title', 'label' => 'title'],
            'slug',
            'icon',
            'path',
            'api_route',
            ['name' => 'parent_id', 'type' => 'number'],
            ['name' => 'guard_type', 'type' => 'select', 'options' => ['admin', 'front']],
            ['name' => 'type', 'type' => 'select', 'options' => [
                ['value' => 1, 'label' => 'permission_directory'],
                ['value' => 2, 'label' => 'permission_page'],
                ['value' => 3, 'label' => 'permission_action'],
            ]],
            ['name' => 'sort', 'type' => 'number'],
            ['name' => 'status', 'type' => 'select', 'options' => [
                ['value' => 1, 'label' => 'enabled'],
                ['value' => 0, 'label' => 'disabled'],
            ]],
        ];
    }

    /**
     * 角色数据范围保存表单字段,scope_type 决定可见数据范围(全部/本人/本人创建/代理树/指定代理与用户)。
     *
     * @return array<int, mixed> 表单字段声明。
     */
    private function dataScopeFields(): array
    {
        return [
            ['name' => 'role_id', 'type' => 'number'],
            ['name' => 'scope_type', 'type' => 'select', 'options' => ['all', 'self', 'created', 'agent_tree', 'custom_agents', 'custom_users']],
            ['name' => 'agent_ids', 'type' => 'textarea'],
            ['name' => 'user_ids', 'type' => 'textarea'],
            ['name' => 'status', 'type' => 'select', 'options' => [
                ['value' => 1, 'label' => 'enabled'],
                ['value' => 0, 'label' => 'disabled'],
            ]],
        ];
    }

    /**
     * 管理员-代理绑定表单字段,binding_type 区分主代理/附加代理。
     *
     * @return array<int, mixed> 表单字段声明。
     */
    private function adminAgentBindingFields(): array
    {
        return [
            ['name' => 'admin_id', 'type' => 'number'],
            ['name' => 'agent_id', 'type' => 'number'],
            ['name' => 'binding_type', 'type' => 'select', 'options' => ['primary', 'extra']],
            ['name' => 'status', 'type' => 'select', 'options' => [
                ['value' => 1, 'label' => 'enabled'],
                ['value' => 0, 'label' => 'disabled'],
            ]],
        ];
    }

    /**
     * 大代理创建/编辑表单字段:账号、邮箱、子代理列表、密码与启用状态。
     *
     * @return array<int, mixed> 表单字段声明。
     */
    private function bigAgentFields(): array
    {
        return [
            'username',
            'email',
            'sub_agent_ids',
            'password',
            ['name' => 'is_enabled', 'type' => 'checkbox'],
        ];
    }

    /**
     * 系统配置表单字段:配置 key、value(文本域)、分组与描述。
     *
     * @return array<int, mixed> 表单字段声明。
     */
    private function systemConfigFields(): array
    {
        return [
            'key',
            ['name' => 'value', 'type' => 'textarea'],
            'group',
            ['name' => 'description', 'type' => 'textarea'],
        ];
    }

    /**
     * 礼品物流状态更新表单字段:状态(待发货/已发货/运输中/已签收/异常)、运单号与备注。
     *
     * @return array<int, mixed> 表单字段声明。
     */
    private function giftShipmentStatusFields(): array
    {
        return [
            ['name' => 'status', 'type' => 'select', 'options' => [
                ['value' => 0, 'label' => 'gift_status_pending'],
                ['value' => 1, 'label' => 'gift_status_shipped'],
                ['value' => 2, 'label' => 'gift_status_in_transit'],
                ['value' => 3, 'label' => 'gift_status_delivered'],
                ['value' => 4, 'label' => 'gift_status_exception'],
            ]],
            'tracking_number',
            ['name' => 'remark', 'type' => 'textarea'],
        ];
    }

    /**
     * 从地址簿选收件人的发货表单字段。
     *
     * recipients_payload 为隐藏字段,由前端地址选择器(giftRecipientPicker)选中的收件人数据填充后提交。
     *
     * @return array<int, mixed> 表单字段声明。
     */
    private function giftAddressSendFields(): array
    {
        return [
            'sender_name',
            'gift_name',
            ['name' => 'gift_quantity', 'type' => 'number'],
            'tracking_number',
            ['name' => 'remark', 'type' => 'textarea'],
            ['name' => 'recipients_payload', 'label' => 'recipient_name', 'type' => 'hidden'],
        ];
    }

    /**
     * 礼品商品表单字段:名称、描述、积分成本、库存与状态。
     *
     * @return array<int, mixed> 表单字段声明。
     */
    private function giftItemFields(): array
    {
        return [
            'name',
            ['name' => 'description', 'type' => 'textarea'],
            ['name' => 'points_cost', 'type' => 'number'],
            ['name' => 'stock_quantity', 'type' => 'number'],
            ['name' => 'status', 'type' => 'select', 'options' => [
                ['value' => 1, 'label' => 'enabled'],
                ['value' => 0, 'label' => 'disabled'],
            ]],
            'image_url',
        ];
    }

    /**
     * 代理等级表单字段:等级代码、名称、佣金上下限与用户佣金比例。
     *
     * @return array<int, mixed> 表单字段声明。
     */
    private function agentLevelFields(): array
    {
        return [
            ['name' => 'level_code', 'type' => 'number'],
            'name',
            ['name' => 'max_commission', 'type' => 'number'],
            ['name' => 'min_commission', 'type' => 'number'],
            ['name' => 'user_commission', 'type' => 'number'],
        ];
    }

    /**
     * 支付渠道表单字段:名称、渠道码、汇率、启用状态、排序与渠道配置。
     *
     * @return array<int, mixed> 表单字段声明。
     */
    private function paymentChannelFields(): array
    {
        return [
            'name',
            'channel_code',
            ['name' => 'exchange_rate', 'type' => 'number'],
            ['name' => 'is_enabled', 'type' => 'checkbox'],
            ['name' => 'sort', 'type' => 'number'],
            ['name' => 'config', 'type' => 'textarea'],
        ];
    }

    /**
     * 账号组配置表单字段:组名、基数、账号类别、是否返佣/默认/ECN 及启用状态。
     *
     * @return array<int, mixed> 表单字段声明。
     */
    private function groupConfigFields(): array
    {
        return [
            ['name' => 'group_name', 'label' => 'group_name'],
            ['name' => 'radix', 'type' => 'number'],
            ['name' => 'category', 'type' => 'select', 'options' => [
                ['value' => 1, 'label' => 'agent_account'],
                ['value' => 2, 'label' => 'client_account'],
            ]],
            ['name' => 'has_commission', 'type' => 'checkbox'],
            ['name' => 'is_enabled', 'type' => 'checkbox'],
            ['name' => 'is_ecn', 'type' => 'checkbox'],
            ['name' => 'is_default', 'type' => 'checkbox'],
        ];
    }

    /**
     * 公告表单字段:标题、内容与发布状态。
     *
     * @return array<int, mixed> 表单字段声明。
     */
    private function newsFields(): array
    {
        return [
            'title',
            ['name' => 'content', 'type' => 'textarea'],
            ['name' => 'is_published', 'type' => 'select', 'options' => [
                ['value' => 1, 'label' => 'published'],
                ['value' => 0, 'label' => 'unpublished'],
            ]],
        ];
    }

    /**
     * 导出类页面通用操作声明:刷新 + 导出(携带导出文件名)。
     *
     * @param string $exportRoute 导出接口路由名。
     * @param string $exportFileName 导出文件名。
     * @return array<int, mixed> CrmUI 页面级操作声明列表。
     */
    private function exportActions(string $exportRoute, string $exportFileName): array
    {
        return [
            'refresh',
            ['key' => 'export', 'route' => $exportRoute, 'fileName' => $exportFileName],
        ];
    }

    /**
     * 后台侧边栏导航分组结构:概览/资金/交易/系统,每组含标题与条目(路径 + Lucide 图标名)。
     *
     * @return array<int, array{title: string, items: array<int, array{label: string, path: string, icon: string}>}> 导航分组。
     */
    private function navGroups(): array
    {
        return [
            ['title' => __('crmui.nav.overview'), 'items' => [
                ['label' => __('crmui.admin.pages.dashboard.title'), 'path' => 'dashboard', 'icon' => 'gauge'],
                ['label' => __('crmui.admin.pages.users.title'), 'path' => 'users', 'icon' => 'users'],
                ['label' => __('crmui.admin.pages.agents.title'), 'path' => 'agents', 'icon' => 'users-round'],
                ['label' => __('crmui.admin.pages.gifts.title'), 'path' => 'gifts', 'icon' => 'truck'],
                ['label' => __('crmui.admin.pages.gift_addresses.title'), 'path' => 'gift-addresses', 'icon' => 'map-pin'],
                ['label' => __('crmui.admin.pages.gift_items.title'), 'path' => 'gift-items', 'icon' => 'gift'],
            ]],
            ['title' => __('crmui.nav.funds'), 'items' => [
                ['label' => __('crmui.admin.pages.deposits.title'), 'path' => 'deposits', 'icon' => 'banknote'],
                ['label' => __('crmui.admin.pages.withdrawals.title'), 'path' => 'withdrawals', 'icon' => 'wallet'],
                ['label' => __('crmui.admin.pages.vouchers.title'), 'path' => 'vouchers', 'icon' => 'receipt-text'],
                ['label' => __('crmui.admin.pages.never_deposit_users.title'), 'path' => 'never-deposit-users', 'icon' => 'circle-user-round'],
            ]],
            ['title' => __('crmui.nav.trading'), 'items' => [
                ['label' => __('crmui.admin.pages.trades.title'), 'path' => 'trades', 'icon' => 'chart-no-axes-column-increasing'],
                ['label' => __('crmui.admin.pages.position_summary.title'), 'path' => 'position-summary', 'icon' => 'table-2'],
                ['label' => __('crmui.admin.pages.risk.title'), 'path' => 'risk', 'icon' => 'shield-alert'],
                ['label' => __('crmui.admin.pages.whs_exp_zero.title'), 'path' => 'whs-exp-zero', 'icon' => 'zap'],
            ]],
            ['title' => __('crmui.nav.system'), 'items' => [
                ['label' => __('crmui.admin.pages.roles.title'), 'path' => 'roles', 'icon' => 'user-cog'],
                ['label' => __('crmui.admin.pages.permissions.title'), 'path' => 'permissions', 'icon' => 'key-round'],
                ['label' => __('crmui.admin.pages.system_configs.title'), 'path' => 'system-configs', 'icon' => 'settings'],
            ]],
        ];
    }

    /**
     * 把字段声明(items)转成渲染结构:推导 label/type,并把默认值注入 value。
     *
     * 类型推断规则:日期类 key 推断为 date,含 password 的 key 推断为 password,
     * isNumberField 白名单内的 key 推断为 number,其余为 text;显式声明的 type 优先。
     *
     * @param array<int, mixed> $items 字段声明,字符串或 ['name'=>..,'type'=>..,'options'=>..] 数组。
     * @param array<string, mixed> $values 默认值映射,按 name 注入 value。
     * @return array<int, array<string, mixed>> 渲染用字段数组。
     */
    private function fields(array $items, array $values = []): array
    {
        return array_map(function ($item) use ($values) {
            $key = is_array($item) ? $item['name'] : $item;
            $labelKey = is_array($item) ? ($item['label'] ?? $key) : $key;
            $type = is_array($item) && isset($item['type'])
                ? $item['type']
                : (in_array($key, ['date_from', 'date_to', 'start_date', 'end_date'], true) ? 'date' : (strpos($key, 'password') !== false ? 'password' : ($this->isNumberField($key) ? 'number' : 'text')));
            $label = $this->fieldLabel($labelKey);

            return [
                'name' => $key,
                'label' => $label,
                'type' => $type,
                'value' => (string) ($values[$key] ?? ''),
                'placeholder' => $label,
                'options' => is_array($item) ? $this->options($item['options'] ?? []) : [],
                'required' => is_array($item) && !empty($item['required']),
                'maxlength' => is_array($item) && isset($item['maxlength']) ? (int) $item['maxlength'] : null,
            ];
        }, $items);
    }

    /**
     * 解析字段显示名:优先 crmui.fields.<key>,其次 crmui.common.<key>,均无翻译时原样返回 key。
     *
     * @param string $key 字段名。
     * @return string 翻译后的显示名。
     */
    private function fieldLabel(string $key): string
    {
        $fieldKey = 'crmui.fields.' . $key;
        $label = __($fieldKey);

        if ($label !== $fieldKey) {
            return $label;
        }

        $commonKey = 'crmui.common.' . $key;
        $label = __($commonKey);

        return $label === $commonKey ? $key : $label;
    }

    /**
     * 判断字段 key 是否属于数字类型白名单,供 fields() 自动推断 type=number。
     *
     * @param string $key 字段名。
     * @return bool 是否按数字输入框渲染。
     */
    private function isNumberField(string $key): bool
    {
        return in_array($key, [
            'amount',
            'apply_amount',
            'actual_amount',
            'balance',
            'comm_rate',
            'commission_rate',
            'credit',
            'deposit_rate',
            'equity',
            'fee',
            'free_margin',
            'level',
            'margin',
            'max_margin_level',
            'min_user_count',
            'min_days',
            'points',
            'points_cost',
            'profit',
            'sort',
            'stock_quantity',
            'volume',
            'withdraw_rate',
        ], true);
    }

    /**
     * 把选项声明(字符串或 [value,label])转成渲染结构,label 按 crmui.options.<label> 翻译。
     *
     * @param array<int, mixed> $items 选项声明。
     * @return array<int, array{value: string, label: string}> 渲染用选项数组。
     */
    private function options(array $items): array
    {
        return array_map(function ($item) {
            if (is_array($item)) {
                $value = (string) ($item['value'] ?? '');
                $labelKey = (string) ($item['label'] ?? $value);
            } else {
                $value = (string) $item;
                $labelKey = $value;
            }

            $translationKey = 'crmui.options.' . $labelKey;
            $label = __($translationKey);

            return [
                'value' => $value,
                'label' => $label === $translationKey ? $labelKey : $label,
            ];
        }, $items);
    }

    /**
     * 把面板声明转成渲染结构:key/title/apiUrl/method/fields,数据由前端按 apiUrl 拉取。
     *
     * @param array<int, array<string, mixed>> $panels 面板声明。
     * @return array<int, array<string, mixed>> 渲染用面板数组。
     */
    private function panels(array $panels): array
    {
        return array_map(function ($panel) {
            return [
                'key' => $panel['key'],
                'title' => __('crmui.panels.' . $panel['title']),
                'apiUrl' => route($panel['api']),
                'method' => $panel['method'] ?? 'POST',
                'fields' => $this->fields($panel['fields'] ?? []),
            ];
        }, $panels);
    }

    /**
     * 把页签声明转成渲染结构:key/label/apiUrl/method 与所需权限标记。
     *
     * 参数逻辑说明：
     * - api：页签对应的命名路由；多数模块的页签各自指向不同接口。
     * - query：可选的固定查询参数；用于同一个接口按字段值分组的页签（例如支付通道按 is_enabled 分组），
     *   参数会拼进 apiUrl，前端切换页签时直接按新地址拉取，不需要额外的分支逻辑。
     *
     * @param array<int, array<string, mixed>> $tabs 页签声明。
     * @return array<int, array<string, mixed>> 渲染用页签数组。
     */
    private function viewTabs(array $tabs): array
    {
        return array_map(function ($tab) {
            $apiUrl = route($tab['api']);
            $query = $tab['query'] ?? [];
            if ($query !== []) {
                $apiUrl .= (strpos($apiUrl, '?') === false ? '?' : '&') . http_build_query($query);
            }

            return [
                'key' => $tab['key'],
                'label' => __('crmui.tabs.' . $tab['label']),
                'apiUrl' => $apiUrl,
                'method' => $tab['method'] ?? 'POST',
                'permission' => $tab['permission'] ?? '',
                'columns' => $this->columns($tab['columns'] ?? []),
            ];
        }, $tabs);
    }

    /**
     * Restricts risk defaults to modes backed by the shared risk page contract.
     */
    private function controlledRiskMode($mode): ?string
    {
        return is_string($mode) && in_array($mode, ['profit', 'positions', 'ipRisk'], true)
            ? $mode
            : null;
    }

    /**
     * 把操作声明转成完整渲染结构,字符串快捷方式自动补全为关联数组。
     *
     * 声明缺失时按默认值兜底:method=POST、variant=default、confirm 文案取 crmui.confirms.<key>;
     * 输出 url(带路由参数)、字段、记录主键、额外 payload 与本地操作标记,权限标记仅用于前端显隐。
     *
     * @param array<int, mixed> $actions 操作声明列表。
     * @return array<int, array<string, mixed>> 渲染用操作数组。
     */
    private function actions(array $actions): array
    {
        return array_map(function ($action) {
            // 字符串快捷方式（如 'refresh'/'create'）自动补全为完整关联数组。
            if (is_string($action)) {
                $action = [
                    'key' => $action,
                    'variant' => 'default',
                    'method' => 'POST',
                    'fields' => [],
                    'recordKey' => 'id',
                    'payloadName' => 'id',
                    'permission' => '',
                    'extraPayload' => [],
                    'view' => '',
                ];
            }
            $route = $action['route'] ?? null;
            $fields = $this->fields($action['fields'] ?? []);

            return [
                'key' => $action['key'],
                'label' => __('crmui.actions.' . $action['key']),
                'url' => $route ? route($route, $action['params'] ?? []) : '',
                'method' => $action['method'] ?? 'POST',
                'variant' => $action['variant'] ?? 'default',
                'confirm' => __('crmui.confirms.' . ($action['confirm'] ?? $action['key'])),
                'fields' => $fields,
                'fieldRules' => array_map(static function (array $field): array {
                    return [
                        'name' => $field['name'],
                        'required' => $field['required'],
                        'maxlength' => $field['maxlength'],
                    ];
                }, $fields),
                'recordKey' => $action['recordKey'] ?? ($action['payloadKey'] ?? 'id'),
                'payloadName' => $action['payloadName'] ?? ($action['payloadKey'] ?? 'id'),
                'permissionTreeUrl' => isset($action['permissionTreeApi']) ? route($action['permissionTreeApi']) : '',
                'permission' => $action['permission'] ?? '',
                'local' => !empty($action['local']),
                // extraPayload：给本地行操作补充固定参数，例如持仓汇总旧钻取模式的 searchtype=subAgentsSearch。
                'extraPayload' => $action['extraPayload'] ?? [],
                'view' => $action['view'] ?? '',
                'visibleWhen' => $action['visibleWhen'] ?? [],
            ];
        }, $actions);
    }

    /**
     * 列 key 列表转成 {key,label} 结构,label 按 crmui.fields.<key> 翻译。
     *
     * @param array<int, string> $keys 列 key 列表。
     * @return array<int, array{key: string, label: string}> 渲染用列数组。
     */
    private function columns(array $keys): array
    {
        return array_map(function ($key) {
            return ['key' => $key, 'label' => __('crmui.fields.' . $key)];
        }, $keys);
    }

    /**
     * 指标 key 转成 {key,label,value} 结构,value 以 '--' 占位,由前端拉取接口数据后填充。
     *
     * @param array<int, string> $keys 指标 key 列表。
     * @return array<int, array{key: string, label: string, value: string}> 渲染用指标数组。
     */
    private function metrics(array $keys): array
    {
        return array_map(function ($key) {
            return ['key' => $key, 'label' => __('crmui.metrics.' . $key), 'value' => '--'];
        }, $keys);
    }

    /**
     * 行操作声明,直接复用 actions() 统一处理字符串快捷方式与默认键。
     *
     * @param array<int, mixed> $keys 行操作声明列表。
     * @return array<int, array<string, mixed>> 渲染用操作数组。
     */
    private function rowActions(array $keys): array
    {
        // 复用 actions()：统一处理字符串快捷方式与完整默认键（key/variant/method/fields 等）。
        return $this->actions($keys);
    }
}
