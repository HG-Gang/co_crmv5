<?php

/**
 * Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/09/03
 * Time: 14:30
 */

namespace App\Http\Controllers\CrmUi\Front;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

/**
 * 前台 CrmUI 应用控制器。
 *
 * 文件功能：
 * - 承载前台 crmui 新 UI 的页面入口（index/login/register/forgotPassword/bigNumberLogin/show）。
 * - 通过 navGroups() 输出前台侧边栏导航结构（含 Lucide 图标名），供布局模板渲染。
 *
 * 设计说明：
 * - 本控制器是「页面壳」：路由以 {path?} 捕获任意路径，show() 把 pages() 中的页面 definition 解析为页面配置注入 Blade，
 *   前端再按 apiUrl/apiMethod 拉取真实数据并渲染，因此本控制器不做任何业务查询。
 * - 前台、后台与大代理各只有 3 个页面壳控制器（Front\PageController、Front\BigAgentPageController、Admin\PageController），
 *   全部页面由 definition 配置数组 + 通用 Blade 视图承载，这是本目录文件少的原因。
 * - canonicalPath() 负责把旧别名与详情路径归一化为标准页面 key，未知路径回退 dashboard 兜底渲染。
 *
 * 适用场景：
 * - 普通用户登录后的前台页面：工作台、个人资料、账户、资金、交易、代理商、礼品与新闻。
 *
 * 入参例子：
 * - GET /front-crmui/dashboard -> show(Request, 'dashboard')
 * - GET /front-crmui/agent/sub -> show(Request, 'agent/sub')
 *
 * 返回值：
 * - index() 返回重定向到 dashboard 页面；
 * - show() 返回对应模块的 Blade 视图（含导航与权限配置）；
 * - 未知路径返回 404 视图。
 *
 * 异常或失败场景：
 * - 未登录访问受保护页面时由 legacy.front.auth 中间件重定向到登录页。
 */
class PageController extends Controller
{
    /**
     * 入口根路径统一重定向到前台工作台。
     *
     * @return \Illuminate\Http\RedirectResponse 指向 /front-crmui/dashboard。
     */
    public function index()
    {
        return redirect()->route('front_crmui_app', ['path' => 'dashboard']);
    }

    /**
     * 渲染前台登录页。
     *
     * @return \Illuminate\Contracts\View\View 登录表单页，提交到 front_api_auth_login。
     */
    public function login()
    {
        return view('front_crmui::auth.login', [
            'page' => $this->authPage('front-login', 'login', 'front_api_auth_login'),
        ]);
    }

    /**
     * 渲染前台注册页，可选携带邀请人 ID。
     *
     * @param Request $request 当前请求，用于读取账户类型与返佣模式邀请上下文。
     * @param string|null $inviterId 可选邀请人标识，原样透传给页面，不参与后端身份判定。
     * @return \Illuminate\Contracts\View\View 注册表单页，提交到 front_api_auth_register。
     */
    public function register(Request $request, $inviterId = null)
    {
        $accountType = (int) $request->query('account_type', 2);
        if (!in_array($accountType, [1, 2], true)) {
            $accountType = 2;
        }

        return view('front_crmui::auth.register', [
            'inviterId' => $inviterId ?: $request->query('inviter_id', ''),
            'accountType' => $accountType,
            'commissionMode' => trim((string) $request->query(
                'commission_mode',
                $request->query('comm_type', '')
            )),
            'page' => $this->authPage('front-register', 'register', 'front_api_auth_register'),
        ]);
    }

    /**
     * 渲染前台忘记密码页。
     *
     * @return \Illuminate\Contracts\View\View 密码重置表单页，提交到 front_api_auth_password_reset。
     */
    public function forgotPassword()
    {
        return view('front_crmui::auth.forgot-password', [
            'page' => $this->authPage('front-forgot-password', 'forgot_password', 'front_api_auth_password_reset'),
        ]);
    }

    /**
     * 渲染前台大客户号登录页。
     *
     * @return \Illuminate\Contracts\View\View 大客户号登录表单页，提交到 front_api_auth_big_number_login。
     */
    public function bigNumberLogin()
    {
        return view('front_crmui::auth.big-number-login', [
            'page' => $this->authPage('front-big-number-login', 'big_number_login', 'front_api_auth_big_number_login'),
        ]);
    }

    /**
     * 按 {path?} 路由渲染对应页面壳。
     *
     * 阶段：定义解析（别名/详情路径归一化、definition 兜底）→ 请求参数合入（frame 等展示标记）→ 渲染。
     *
     * @param Request $request 当前页面请求，仅用于读取 frame 等展示参数，不参与业务数据。
     * @param string $path 页面路径，默认 dashboard。
     * @return \Illuminate\Contracts\View\View 对应模块的页面壳视图。
     */
    public function show(Request $request, $path = 'dashboard')
    {
        // 定义解析阶段：旧别名与详情路由先归一化为标准页面 key，未知路径回退 dashboard 兜底渲染。
        $path = $this->canonicalPath(trim($path ?: 'dashboard', '/'));
        $pages = $this->pages();
        $definition = $pages[$path] ?? $pages['dashboard'];
        $page = $this->page($path, $definition);

        // 代理层级下钻只允许从两个列表页继承严格正整数 parent_id，并始终锁定直属范围。
        if (in_array($path, ['agent/sub', 'agent/customers'], true)) {
            $parentId = filter_var($request->query('parent_id'), FILTER_VALIDATE_INT, [
                'options' => ['min_range' => 1],
            ]);

            if ($parentId !== false) {
                $page['defaultFilters'] = array_merge($page['defaultFilters'] ?? [], [
                    'parent_id' => $parentId,
                    'direct_only' => 1,
                ]);
            }
        }

        // 请求参数合入阶段：iframe 内嵌标记原样透传给前端布局，页面数据仍由前端按 apiUrl 拉取。
        if ($request->boolean('frame')) {
            $page['frame'] = true;
        }
        $page['renderFamily'] = $this->renderFamily($request);

        // 渲染阶段：只输出页面壳配置与导航，不在此处查询任何业务数据。
        return view('front_crmui::' . $definition['view'], [
            'page' => $page,
            'navGroups' => $this->navGroups(),
        ]);
    }

    /**
     * 把旧后台别名与带参详情路径归一化为标准页面 key。
     *
     * 旧 CrmUI 升级前的 URL(如 order/open2)映射到新标准路径;
     * gift/address/info/{id} 等带参详情路径去掉尾部参数,统一落到对应列表页,由前端按行内数据继续下钻。
     *
     * @param string $path 归一化前的页面路径(已去除首尾斜杠)。
     * @return string 标准页面 key;无匹配时原样返回。
     */
    private function canonicalPath(string $path): string
    {
        $aliases = [
            'order/open2' => 'order/open',
            'order/closed2' => 'order/closed',
            'position/comm-summary' => 'position/summary',
            'position/comm-summary-v2' => 'position/summary',
            'account/voucher/browse' => 'account/voucher',
            'gift/address/add' => 'gift/address',
        ];

        if (isset($aliases[$path])) {
            return $aliases[$path];
        }
        if (preg_match('#^gift/address/info/\d+$#', $path)) {
            return 'gift/address';
        }
        if (preg_match('#^agent/customers/\d+$#', $path)) {
            return 'agent/customers';
        }
        if (preg_match('#^agent/group-change/\d+$#', $path)) {
            return 'agent/group-change';
        }
        if (preg_match('#^commission/transfer/\d+$#', $path)) {
            return 'commission/transfer';
        }
        if (preg_match('#^position/summary/(?:detail|deatil)/\d+$#', $path)) {
            return 'position/summary';
        }
        if (preg_match('#^order/open/detail/[^/]+$#', $path)) {
            return 'order/open';
        }
        if (preg_match('#^order/closed/detail/[^/]+$#', $path)) {
            return 'order/closed';
        }
        if (preg_match('#^commission/realtime/detail/[^/]+$#', $path)) {
            return 'commission/realtime';
        }
        if (preg_match('#^agent/customer-detail/[^/]+/\d+$#', $path)) {
            return 'agent/customers';
        }
        if (preg_match('#^news/detail/\d+$#', $path)) {
            return 'news';
        }

        return $path;
    }

    /**
     * 组装认证类页面(登录/注册/忘记密码/大客户号登录)的公共 page 数据。
     *
     * 提供标题、提交地址与各认证入口链接,submitUrl 由调用方指定路由名生成。
     *
     * @param string $key 页面唯一 key,注入 page['key']。
     * @param string $translationKey 翻译 key,拼成 crmui.front.auth.<key>.title/subtitle。
     * @param string $submitRoute 表单提交接口路由名。
     * @return array<string, string> 认证页面数据。
     */
    private function authPage(string $key, string $translationKey, string $submitRoute): array
    {
        return [
            'key' => $key,
            'title' => __('crmui.front.auth.' . $translationKey . '.title'),
            'subtitle' => __('crmui.front.auth.' . $translationKey . '.subtitle'),
            'submitUrl' => route($submitRoute),
            'dashboardUrl' => route('front_crmui_app', ['path' => 'dashboard']),
            'loginUrl' => route('front_crmui_login'),
            'registerUrl' => route('front_crmui_register'),
            'forgotPasswordUrl' => route('front_crmui_forgot_password'),
        ];
    }

    /**
     * Identify which visual family requested this shared server-rendered shell.
     */
    private function renderFamily(Request $request): string
    {
        return $request->is('front-naive') || $request->is('front-naive/*') ? 'naive' : 'crmui';
    }

    /**
     * 组装前台页面壳数据结构,供 Blade 布局与前端脚本渲染。
     *
     * 与后台版相比额外支持 verificationApi/verificationCodeApi/optionsApi(验证码与下拉选项接口)
     * 与 listKey(接口返回列表字段名);未声明项按默认值兜底,数据由前端按 apiUrl 拉取。
     *
     * @param string $path 当前页面路径(已归一化的标准 key)。
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
            'surface' => 'front',
            'key' => 'front.' . $key,
            'path' => $path,
            'title' => __('crmui.front.pages.' . $key . '.title'),
            'description' => __('crmui.front.pages.' . $key . '.description'),
            'apiUrl' => $apiRoute ? route($apiRoute) : '',
            'apiMethod' => $definition['method'] ?? 'GET',
            'formUrl' => $formRoute ? route($formRoute) : '',
            'formMethod' => $definition['formMethod'] ?? 'POST',
            'verificationUrl' => isset($definition['verificationApi']) ? route($definition['verificationApi']) : '',
            'verificationCodeUrl' => isset($definition['verificationCodeApi']) ? route($definition['verificationCodeApi']) : '',
            'optionsUrl' => isset($definition['optionsApi']) ? route($definition['optionsApi']) : '',
            'listKey' => $definition['listKey'] ?? '',
            'defaultFilters' => $definition['defaultFilters'] ?? [],
            'timeline' => $definition['timeline'] ?? '',
            'chartGroups' => $definition['chartGroups'] ?? [],
            'mode' => $mode,
            'viewTabs' => $this->viewTabs($definition['viewTabs'] ?? []),
            'filters' => $this->fields($definition['filters'] ?? ['keyword', 'date_from', 'date_to']),
            'columns' => $this->columns($definition['columns'] ?? ['id', 'status', 'amount', 'created_at']),
            'metrics' => $this->metrics($definition['metrics'] ?? $defaultMetrics),
            'formFields' => $this->fields($definition['formFields'] ?? []),
            'panels' => $this->panels($definition['panels'] ?? []),
            'actions' => $this->actions($definition['actions'] ?? ['refresh', 'export']),
            'rowActions' => $this->rowActions($definition['rowActions'] ?? []),
            'emptyText' => __('crmui.empty.no_records'),
        ];
    }

    /**
     * 前台全部页面的 definition 配置中心。
     *
     * 数组 key 是归一化后的标准页面路径,value 是该页面的 view/key/api/filters/columns/formFields/
     * viewTabs/rowActions 等声明;show() 按路径查表,未命中时回退 dashboard。
     *
     * @return array<string, array<string, mixed>> path => 页面声明。
     */
    private function pages(): array
    {
        return [
            'dashboard' => ['view' => 'dashboard.index', 'key' => 'dashboard', 'api' => 'front_api_dashboard', 'mode' => 'dashboard', 'filters' => ['keyword'], 'columns' => ['name', 'amount', 'status', 'updated_at'], 'metrics' => ['balance', 'equity', 'margin']],
            'profile' => ['view' => 'profile.index', 'key' => 'profile', 'api' => 'front_api_profile', 'mode' => 'profile', 'filters' => ['keyword'], 'columns' => ['user_id', 'user_name', 'email', 'phone', 'auth_status'], 'panels' => [
                ['key' => 'basic-profile', 'title' => 'basic_profile', 'api' => 'front_api_profile_update', 'method' => 'PATCH', 'fields' => ['user_name', 'phone', 'id_card_no', ['name' => 'gender', 'type' => 'select', 'options' => [['value' => 1, 'label' => 'male'], ['value' => 2, 'label' => 'female']]], 'address']],
                ['key' => 'avatar', 'title' => 'avatar_upload', 'api' => 'front_api_profile_avatar', 'fields' => [['name' => 'avatar', 'type' => 'file']]],
                ['key' => 'identity', 'title' => 'identity_audit', 'api' => 'front_api_profile_identity', 'fields' => ['id_card_no', ['name' => 'id_card_front', 'type' => 'file'], ['name' => 'id_card_back', 'type' => 'file']]],
                ['key' => 'bank-card', 'title' => 'bank_card', 'api' => 'front_api_profile_bank_card', 'fields' => ['bank_name', 'bank_no', 'bank_addr', ['name' => 'bank_card_img', 'type' => 'file'], ['name' => 'bank_card_back_img', 'label' => 'bank_card_img_back', 'type' => 'file']]],
                ['key' => 'bank-card-change', 'title' => 'bank_card_change', 'api' => 'front_api_profile_bank_card_change', 'fields' => ['verify_phone', ['name' => 'verify_email', 'label' => 'current_email'], 'bank_name', 'bank_no', 'bank_addr', ['name' => 'bank_card_img', 'type' => 'file'], ['name' => 'bank_card_back_img', 'label' => 'bank_card_img_back', 'type' => 'file']]],
                ['key' => 'email', 'title' => 'email_update', 'api' => 'front_api_profile_email', 'fields' => ['verify_phone', 'current_email', 'new_email']],
                ['key' => 'phone', 'title' => 'phone_update', 'api' => 'front_api_profile_phone', 'fields' => ['verify_phone', ['name' => 'verify_email', 'label' => 'current_email'], 'new_phone']],
                ['key' => 'contact', 'title' => 'contact_update', 'api' => 'front_api_profile_contact_info', 'fields' => [['name' => 'type', 'type' => 'select', 'options' => [['value' => 'phone', 'label' => 'phone'], ['value' => 'email', 'label' => 'email']]], 'password', 'current_email', 'new_email', 'verify_phone', 'new_phone']],
            ]],
            'profile/edit' => ['view' => 'profile.edit', 'key' => 'profile_edit', 'api' => 'front_api_profile', 'formApi' => 'front_api_profile_update', 'formMethod' => 'PATCH', 'mode' => 'form', 'formFields' => ['user_name', 'phone', 'id_card_no', ['name' => 'gender', 'type' => 'select', 'options' => [['value' => 1, 'label' => 'male'], ['value' => 2, 'label' => 'female']]], 'address']],
            'profile/change-password' => ['view' => 'profile.change-password', 'key' => 'profile_password', 'formApi' => 'front_api_profile_password', 'mode' => 'form', 'formFields' => ['old_password', 'password', 'password_confirmation']],
            'profile/change-email' => ['view' => 'profile.change-email', 'key' => 'profile_email', 'api' => 'front_api_profile', 'formApi' => 'front_api_profile_email', 'mode' => 'form', 'formFields' => ['verify_phone', 'current_email', 'new_email']],
            'account/info' => ['view' => 'account.info', 'key' => 'account_info', 'api' => 'front_api_account_profile', 'mode' => 'profile', 'columns' => ['account', 'balance', 'credit', 'status']],
            'account/balance' => ['view' => 'account.balance', 'key' => 'account_balance', 'api' => 'front_api_account_balance', 'mode' => 'dashboard', 'columns' => ['balance', 'credit', 'margin', 'free_margin']],
            'account/voucher' => ['view' => 'account.voucher', 'key' => 'account_voucher', 'api' => 'front_api_account_vouchers', 'formApi' => 'front_api_account_voucher_submissions', 'formFields' => [['name' => 'images[]', 'label' => 'voucher_images', 'type' => 'file', 'accept' => 'image/*', 'multiple' => true], ['name' => 'remarks', 'type' => 'textarea']], 'columns' => ['user_id', 'remarks', 'review_msg', 'review_status', 'rec_crt_date', 'rec_upd_date']],
            'account/cancel' => ['view' => 'account.cancel', 'key' => 'account_cancel', 'api' => 'front_api_account_cancellation', 'formApi' => 'front_api_account_cancellation_applications', 'verificationApi' => 'front_api_profile_verification_cancellation_checks', 'verificationCodeApi' => 'front_api_profile_verification_cancellation_verification_codes', 'formFields' => [['name' => 'userIdcardNo', 'label' => 'id_card_no'], ['name' => 'userphoneNo', 'label' => 'phone'], ['name' => 'useremail', 'label' => 'email', 'type' => 'email'], ['name' => 'userverfcode', 'label' => 'email_code', 'type' => 'verification_code'], ['name' => 'password', 'label' => 'password', 'type' => 'password']], 'columns' => ['status', 'cancel_remark', 'reject_reason', 'created_at', 'updated_at']],
            'deposit' => ['view' => 'deposit.index', 'key' => 'deposit', 'api' => 'front_api_deposits_history', 'optionsApi' => 'front_api_deposits_form_options', 'formApi' => 'front_api_deposits_submissions', 'filters' => [['name' => 'status', 'type' => 'select', 'options' => [['value' => '01', 'label' => 'deposit_pending'], ['value' => '02', 'label' => 'deposit_approved'], ['value' => '09', 'label' => 'deposit_rejected']]], 'date_from', 'date_to'], 'formFields' => ['amount', ['name' => 'channel', 'type' => 'select'], 'remark', ['name' => 'deposit_amt_usd', 'type' => 'hidden'], ['name' => 'deposit_pay_amt_rmb', 'type' => 'hidden'], ['name' => 'pay_channel', 'type' => 'hidden'], ['name' => 'passageway', 'type' => 'hidden']], 'columns' => ['order_no', 'amount', 'actual_amount', 'payment_channel', 'status', 'created_at']],
            'withdraw' => ['view' => 'withdraw.index', 'key' => 'withdraw', 'api' => 'front_api_withdrawals_history', 'optionsApi' => 'front_api_withdrawals_form_options', 'formApi' => 'front_api_withdrawals_submissions', 'filters' => [['name' => 'status', 'type' => 'select', 'options' => [['value' => 0, 'label' => 'withdraw_pending'], ['value' => 1, 'label' => 'withdraw_processing'], ['value' => 2, 'label' => 'withdraw_completed'], ['value' => 3, 'label' => 'withdraw_rejected']]], 'date_from', 'date_to'], 'formFields' => ['amount', 'password', ['name' => 'agree', 'type' => 'checkbox', 'label' => 'agree_terms']], 'columns' => ['order_no', 'apply_amount', 'fee', 'status', 'created_at']],
            'flow' => ['view' => 'flow.index', 'key' => 'flow', 'api' => 'front_api_flows_account', 'filters' => [['name' => 'flow_type', 'type' => 'select', 'options' => [['value' => 'all', 'label' => 'all_flows'], ['value' => 'deposit', 'label' => 'deposit_flow'], ['value' => 'withdraw', 'label' => 'withdraw_flow'], ['value' => 'withdraw_apply', 'label' => 'withdraw_apply_flow'], ['value' => 'direct_deposit', 'label' => 'direct_deposit_flow'], ['value' => 'direct_withdraw', 'label' => 'direct_withdraw_flow'], ['value' => 'direct_agents_deposit', 'label' => 'direct_agents_deposit_flow'], ['value' => 'direct_agents_withdraw', 'label' => 'direct_agents_withdraw_flow']]], 'date_from', 'date_to'], 'columns' => ['order_no', 'flow_type', 'amount', 'status', 'created_at'], 'viewTabs' => [
                ['key' => 'account', 'label' => 'account_flow', 'api' => 'front_api_flows_account', 'method' => 'GET'],
                ['key' => 'deposits', 'label' => 'deposit_flow', 'api' => 'front_api_flows_deposits', 'method' => 'GET'],
                ['key' => 'withdrawals', 'label' => 'withdraw_flow', 'api' => 'front_api_flows_withdrawals', 'method' => 'GET'],
                ['key' => 'withdrawal_applications', 'label' => 'withdraw_apply_flow', 'api' => 'front_api_flows_withdrawal_applications', 'method' => 'GET'],
                ['key' => 'direct_deposits', 'label' => 'direct_deposit_flow', 'api' => 'front_api_flows_direct_deposits', 'method' => 'GET'],
                ['key' => 'direct_withdrawals', 'label' => 'direct_withdraw_flow', 'api' => 'front_api_flows_direct_withdrawals', 'method' => 'GET'],
                ['key' => 'direct_agent_deposits', 'label' => 'direct_agents_deposit_flow', 'api' => 'front_api_flows_direct_agent_deposits', 'method' => 'GET'],
                ['key' => 'direct_agent_withdrawals', 'label' => 'direct_agents_withdraw_flow', 'api' => 'front_api_flows_direct_agent_withdrawals', 'method' => 'GET'],
            ]],
            'position/summary' => [
                'view' => 'position.summary',
                'key' => 'position_summary',
                'api' => 'front_api_positions_summary',
                'filters' => ['userId', 'userName', ['name' => 'symbol', 'label' => 'symbol', 'type' => 'select', 'dynamicOptions' => 'symbols'], ['name' => 'startdate', 'label' => 'date_from'], ['name' => 'enddate', 'label' => 'date_to']],
                'columns' => [
                    'user_id',
                    'agent_level_name',
                    'user_name',
                    ['key' => 'total_yuerj', 'label' => 'total_deposit'],
                    ['key' => 'total_yuecj', 'label' => 'total_withdraw'],
                    'total_rebate',
                    ['key' => 'total_net_worth', 'label' => 'net_worth'],
                    ['key' => 'total_comm', 'label' => 'commission'],
                    'total_profit',
                    ['key' => 'total_noble_metal', 'label' => 'noble_metal'],
                    ['key' => 'total_for_exca', 'label' => 'forex'],
                    ['key' => 'total_crud_oil', 'label' => 'crude_oil'],
                    ['key' => 'total_index', 'label' => 'index_products'],
                    ['key' => 'total_currency', 'label' => 'currency_products'],
                    ['key' => 'total_stock', 'label' => 'stock_products'],
                    'total_volume',
                    ['key' => 'total_swaps', 'label' => 'swaps'],
                ],
            ],
            'position/summary2' => [
                'view' => 'position.summary',
                'key' => 'position_self_summary',
                'api' => 'front_api_positions_self_summary',
                'filters' => [
                    ['name' => 'date_from', 'label' => 'date_from'],
                    ['name' => 'date_to', 'label' => 'date_to'],
                ],
                'columns' => [
                    'user_id',
                    'user_name',
                    ['key' => 'total_yuerj', 'label' => 'total_deposit'],
                    ['key' => 'total_yuecj', 'label' => 'total_withdraw'],
                    ['key' => 'total_net_worth', 'label' => 'net_worth'],
                    ['key' => 'total_comm', 'label' => 'commission'],
                    'total_profit',
                    ['key' => 'total_noble_metal', 'label' => 'noble_metal'],
                    ['key' => 'total_for_exca', 'label' => 'forex'],
                    ['key' => 'total_crud_oil', 'label' => 'crude_oil'],
                    ['key' => 'total_index', 'label' => 'index_products'],
                    ['key' => 'total_currency', 'label' => 'currency_products'],
                    ['key' => 'total_stock', 'label' => 'stock_products'],
                    'total_volume',
                    ['key' => 'total_swaps', 'label' => 'swaps'],
                ],
            ],
            'order/open' => [
                'view' => 'order.open',
                'key' => 'open_orders',
                'api' => 'front_api_orders_open',
                'filters' => ['userId', 'orderId', ['name' => 'symbol', 'label' => 'symbol', 'type' => 'select', 'dynamicOptions' => 'symbols'], ['name' => 'startdate', 'label' => 'date_from'], ['name' => 'enddate', 'label' => 'date_to']],
                'columns' => [
                    ['key' => 'ticket', 'action' => 'showOrderInfo', 'recordKey' => 'ticket'],
                    ['key' => 'login', 'label' => 'user_id'],
                    'symbol',
                    ['key' => 'cmd', 'label' => 'order_cmd'],
                    ['key' => 'volume_lots', 'label' => 'volume'],
                    ['key' => 'sl', 'label' => 'stop_loss'],
                    ['key' => 'tp', 'label' => 'take_profit'],
                    'commission',
                    'profit',
                    'swaps',
                    'open_price',
                    'open_time',
                ],
            ],
            'order/closed' => [
                'view' => 'order.closed',
                'key' => 'closed_orders',
                'api' => 'front_api_orders_closed',
                'filters' => ['userId', 'orderId', ['name' => 'symbol', 'label' => 'symbol', 'type' => 'select', 'dynamicOptions' => 'symbols'], ['name' => 'is_coercion', 'label' => 'force_close', 'type' => 'select', 'options' => [['value' => 'Yes', 'label' => 'yes'], ['value' => 'No', 'label' => 'no']]], ['name' => 'startdate', 'label' => 'date_from'], ['name' => 'enddate', 'label' => 'date_to']],
                'columns' => [
                    ['key' => 'ticket', 'action' => 'showOrderInfo', 'recordKey' => 'ticket'],
                    ['key' => 'login', 'label' => 'user_id'],
                    'symbol',
                    ['key' => 'cmd', 'label' => 'order_cmd'],
                    ['key' => 'volume_lots', 'label' => 'volume'],
                    ['key' => 'sl', 'label' => 'stop_loss'],
                    ['key' => 'tp', 'label' => 'take_profit'],
                    'commission',
                    'profit',
                    'swaps',
                    'open_price',
                    'close_price',
                    'close_time',
                ],
            ],
            'agent/sub' => [
                'view' => 'agent.sub',
                'key' => 'agent_sub',
                'api' => 'front_api_agents_direct',
                'filters' => ['keyword', 'user_id'],
                'columns' => ['user_id', 'name', 'level', 'status', 'created_at'],
                'rowActions' => [
                    ['key' => 'direct_agents', 'href' => route('front_crmui_app', ['path' => 'agent/sub']) . '?parent_id=__ID__&direct_only=1', 'recordKey' => 'user_id'],
                    ['key' => 'direct_customers', 'href' => route('front_crmui_app', ['path' => 'agent/customers']) . '?parent_id=__ID__&direct_only=1', 'recordKey' => 'user_id'],
                ],
            ],
            'agent/customers' => [
                'view' => 'agent.customers',
                'key' => 'agent_customers',
                'api' => 'front_api_agents_direct_customers',
                'filters' => ['keyword', 'user_id'],
                'columns' => ['user_id', 'name', 'email', 'status', 'created_at'],
                'rowActions' => [
                    [
                        'key' => 'commission_transfer',
                        'route' => 'front_api_commissions_transfers',
                        'method' => 'POST',
                        'recordKey' => 'user_id',
                        'payloadName' => 'sub_agent_id',
                        'fields' => [
                            ['name' => 'sub_agent_id', 'type' => 'hidden'],
                            ['name' => 'target_user_preview', 'label' => 'target_user_id', 'type' => 'readonly', 'source' => 'user_id'],
                            'amount',
                            ['name' => 'password', 'type' => 'password'],
                            ['name' => 'remark', 'type' => 'textarea'],
                        ],
                    ],
                    [
                        'key' => 'group_change',
                        'route' => 'front_api_agents_group_change_applications',
                        'method' => 'POST',
                        'recordKey' => 'user_id',
                        'payloadName' => 'target_user_id',
                        'fields' => [
                            ['name' => 'target_user_id', 'type' => 'hidden'],
                            ['name' => 'target_user_preview', 'label' => 'target_user_id', 'type' => 'readonly', 'source' => 'user_id'],
                            ['name' => 'new_group_id', 'type' => 'select', 'dynamicOptions' => 'available_groups'],
                            ['name' => 'reason', 'type' => 'textarea'],
                        ],
                    ],
                ],
            ],
            'agent/confirm-level' => [
                'view' => 'agent.confirm-level',
                'key' => 'agent_confirm_level',
                'api' => 'front_api_agents_level_confirmation',
                'filters' => ['userId', 'date_from', 'date_to'],
                'columns' => ['userId', 'userName', 'userEmail', 'userPhone', 'agent_level_name', ['key' => 'userGroupId', 'format' => 'agentLevelSelect'], 'rec_crt_date'],
                'rowActions' => [
                    ['key' => 'confirm_level', 'route' => 'front_api_agents_level_confirmation_changes', 'method' => 'POST', 'recordKey' => 'userId', 'payloadName' => 'userId'],
                ],
            ],
            'agent/group-change' => [
                'view' => 'agent.group-change',
                'key' => 'agent_group_change',
                'api' => 'front_api_agents_group_changes',
                'optionsApi' => 'front_api_agents_group_changes',
                'formApi' => 'front_api_agents_group_change_applications',
                'filters' => ['userId', 'groupId', ['name' => 'startdate', 'label' => 'date_from'], ['name' => 'enddate', 'label' => 'date_to']],
                'formFields' => ['target_user_id', ['name' => 'new_group_id', 'type' => 'select'], ['name' => 'reason', 'type' => 'textarea']],
                'columns' => ['trans_uid', ['key' => 'trans_type_gid', 'label' => 'group_name'], 'trans_apply_status', 'trans_apply_reason', 'rec_crt_date'],
            ],
            'commission/realtime' => [
                'view' => 'commission.realtime',
                'key' => 'commission_realtime',
                'api' => 'front_api_commissions_realtime',
                'filters' => ['userId', 'orderId', ['name' => 'startdate', 'label' => 'date_from'], ['name' => 'enddate', 'label' => 'date_to']],
                'columns' => [
                    'ticket',
                    ['key' => 'login', 'label' => 'user_id'],
                    ['key' => 'volume_lots', 'label' => 'volume'],
                    'current_commission_amount',
                    'current_commission_status_text',
                    'rebate_ratio',
                    'profit_gain',
                    'profit_loss',
                    'profit_net',
                    'commission_updated_at',
                    'order_created_at',
                    'order_closed_at',
                    'comment',
                    'modify_time',
                ],
                'rowActions' => [
                    ['key' => 'detail', 'route' => 'front_api_commissions_realtime', 'method' => 'GET', 'recordKey' => 'ticket', 'payloadName' => 'orderId', 'payload' => ['detail_commission' => 1, 'per_page' => 1]],
                ],
            ],
            'commission/history' => [
                'view' => 'commission.history',
                'key' => 'commission_history',
                'api' => 'front_api_commissions_history',
                'filters' => ['orderId', ['name' => 'startdate', 'label' => 'date_from'], ['name' => 'enddate', 'label' => 'date_to']],
                'metrics' => ['commission_amount', 'returned_amount', 'real_amount', 'agent_volume'],
                'chartGroups' => $this->commissionHistoryChartGroups(),
                'columns' => [
                    'unique_id',
                    ['key' => 'agent_id', 'label' => 'user_id'],
                    'order_no',
                    ['key' => 'commission_amount', 'label' => 'commission'],
                    'returned_amount',
                    'real_amount',
                    'settle_status_text',
                    ['key' => 'comment', 'label' => 'remark'],
                    'data_type',
                    'created_time',
                    'modify_time',
                ],
            ],
            'commission/transfer' => [
                'view' => 'commission.transfer',
                'key' => 'commission_transfer',
                'api' => 'front_api_commissions_history',
                'formApi' => 'front_api_commissions_transfers',
                'defaultFilters' => ['dataType' => 'transfer'],
                'filters' => ['orderId', 'date_from', 'date_to'],
                'formFields' => [
                    ['name' => 'sub_agent_id', 'label' => 'sub_agent_id', 'type' => 'select', 'dynamicOptions' => 'direct_agents'],
                    'amount',
                    ['name' => 'password', 'type' => 'password'],
                    ['name' => 'idempotency_key', 'type' => 'hidden'],
                    ['name' => 'remark', 'type' => 'textarea'],
                ],
                'metrics' => ['commission_amount', 'real_amount'],
                'chartGroups' => $this->commissionTransferChartGroups(),
                'columns' => ['unique_id', 'agent_id', 'commission_amount', 'real_amount', 'settle_status_text', 'data_type', 'remarks', 'created_time'],
            ],
            'gift/address' => [
                'view' => 'gift.address',
                'key' => 'gift_address',
                'api' => 'front_api_gift_addresses_index',
                'formApi' => 'front_api_gift_addresses_store',
                'filters' => ['recipient_name', 'recipient_phone', ['name' => 'is_default', 'type' => 'select', 'options' => [['value' => 1, 'label' => 'yes'], ['value' => 0, 'label' => 'no']]]],
                'formFields' => ['recipient_name', 'recipient_phone', ['name' => 'recipient_address', 'type' => 'textarea'], ['name' => 'is_default', 'type' => 'checkbox']],
                'columns' => ['recipient_name', 'recipient_phone', 'recipient_address', 'is_default'],
                'rowActions' => [
                    ['key' => 'update', 'route' => 'front_api_gift_addresses_update', 'params' => ['address' => '__ID__'], 'method' => 'PATCH', 'fields' => ['recipient_name', 'recipient_phone', ['name' => 'recipient_address', 'type' => 'textarea'], ['name' => 'is_default', 'type' => 'checkbox']]],
                    ['key' => 'set_default', 'route' => 'front_api_gift_addresses_update', 'params' => ['address' => '__ID__'], 'method' => 'PATCH', 'payload' => ['is_default' => 1], 'confirm' => 'set_default'],
                    ['key' => 'delete', 'route' => 'front_api_gift_addresses_destroy', 'params' => ['address' => '__ID__'], 'method' => 'DELETE', 'variant' => 'danger'],
                ],
            ],
            'gift/list' => [
                'view' => 'gift.list',
                'key' => 'gift_list',
                'api' => 'front_api_gifts',
                'listKey' => 'shipped_gifts',
                'filters' => ['recipient_name', 'gift_name', ['name' => 'startdate', 'label' => 'date_from'], ['name' => 'enddate', 'label' => 'date_to']],
                'columns' => ['gift_name', 'recipient_name', 'recipient_phone', 'recipient_address', 'sender_name', 'gift_quantity', 'remark', 'shipped_at'],
            ],
            'news' => [
                'view' => 'news.index',
                'key' => 'news',
                'api' => 'front_api_news',
                'listKey' => 'news',
                'timeline' => 'news',
                'filters' => [['name' => 'startdate', 'label' => 'date_from'], ['name' => 'enddate', 'label' => 'date_to']],
                'columns' => ['news_id', 'news_title', 'rec_crt_date'],
            ],
        ];
    }

    /**
     * Chart groups for commission history analytics. The fields mirror the
     * data contract returned by Front\CommissionController::commissionHistoryAnalytics().
     *
     * @return array<int, array<string, mixed>>
     */
    private function commissionHistoryChartGroups(): array
    {
        return [
            [
                'target' => 'commissionTrendChart',
                'title' => 'front.commission_trend',
                'defaultType' => 'bar',
                'fields' => [
                    ['key' => 'analytics.ranges.3.commission_amount', 'label' => 'front.last_3_days'],
                    ['key' => 'analytics.ranges.7.commission_amount', 'label' => 'front.last_7_days'],
                    ['key' => 'analytics.ranges.15.commission_amount', 'label' => 'front.last_15_days'],
                    ['key' => 'analytics.ranges.30.commission_amount', 'label' => 'front.last_30_days'],
                ],
            ],
            [
                'target' => 'commissionGenderChart',
                'title' => 'front.commission_gender_count_profile',
                'defaultType' => 'pie',
                'fields' => [
                    ['key' => 'analytics.gender.male.count_percentage', 'label' => 'register.male'],
                    ['key' => 'analytics.gender.female.count_percentage', 'label' => 'register.female'],
                    ['key' => 'analytics.gender.unknown.count_percentage', 'label' => 'response.unknown'],
                ],
            ],
            [
                'target' => 'commissionGenderAmountChart',
                'title' => 'front.commission_gender_amount_profile',
                'defaultType' => 'pie',
                'fields' => [
                    ['key' => 'analytics.gender.male.commission_amount', 'label' => 'register.male'],
                    ['key' => 'analytics.gender.female.commission_amount', 'label' => 'register.female'],
                    ['key' => 'analytics.gender.unknown.commission_amount', 'label' => 'response.unknown'],
                ],
            ],
        ];
    }

    /**
     * Chart groups for transfer-only commission history analytics.
     *
     * @return array<int, array<string, mixed>>
     */
    private function commissionTransferChartGroups(): array
    {
        return [
            [
                'target' => 'commissionTransferTrendChart',
                'title' => 'front.commission_transfer_trend',
                'defaultType' => 'bar',
                'fields' => [
                    ['key' => 'analytics.ranges.3.commission_amount', 'label' => 'front.last_3_days'],
                    ['key' => 'analytics.ranges.7.commission_amount', 'label' => 'front.last_7_days'],
                    ['key' => 'analytics.ranges.15.commission_amount', 'label' => 'front.last_15_days'],
                    ['key' => 'analytics.ranges.30.commission_amount', 'label' => 'front.last_30_days'],
                ],
            ],
            [
                'target' => 'commissionTransferGenderChart',
                'title' => 'front.commission_transfer_gender_profile',
                'defaultType' => 'pie',
                'fields' => [
                    ['key' => 'analytics.gender.male.count_percentage', 'label' => 'register.male'],
                    ['key' => 'analytics.gender.female.count_percentage', 'label' => 'register.female'],
                    ['key' => 'analytics.gender.unknown.count_percentage', 'label' => 'response.unknown'],
                ],
            ],
            [
                'target' => 'commissionTransferGenderAmountChart',
                'title' => 'front.commission_transfer_amount_profile',
                'defaultType' => 'pie',
                'fields' => [
                    ['key' => 'analytics.gender.male.commission_amount', 'label' => 'register.male'],
                    ['key' => 'analytics.gender.female.commission_amount', 'label' => 'register.female'],
                    ['key' => 'analytics.gender.unknown.commission_amount', 'label' => 'response.unknown'],
                ],
            ],
        ];
    }

    /**
     * 前台侧边栏导航分组结构:概览/资金/交易/代理/系统,每组含标题与条目(路径 + Lucide 图标名)。
     *
     * @return array<int, array{title: string, items: array<int, array{label: string, path: string, icon: string}>}> 导航分组。
     */
    private function navGroups(): array
    {
        return [
            ['title' => __('crmui.nav.overview'), 'items' => [
                ['label' => __('crmui.front.pages.dashboard.title'), 'path' => 'dashboard', 'icon' => 'gauge'],
                ['label' => __('crmui.front.pages.profile.title'), 'path' => 'profile', 'icon' => 'user-round'],
                ['label' => __('crmui.front.pages.account_info.title'), 'path' => 'account/info', 'icon' => 'circle-user-round'],
                ['label' => __('crmui.front.pages.account_balance.title'), 'path' => 'account/balance', 'icon' => 'wallet-cards'],
            ]],
            ['title' => __('crmui.nav.funds'), 'items' => [
                ['label' => __('crmui.front.pages.account_voucher.title'), 'path' => 'account/voucher', 'icon' => 'receipt-text'],
                ['label' => __('crmui.front.pages.account_cancel.title'), 'path' => 'account/cancel', 'icon' => 'user-round-x'],
                ['label' => __('crmui.front.pages.deposit.title'), 'path' => 'deposit', 'icon' => 'circle-plus'],
                ['label' => __('crmui.front.pages.withdraw.title'), 'path' => 'withdraw', 'icon' => 'circle-minus'],
                ['label' => __('crmui.front.pages.flow.title'), 'path' => 'flow', 'icon' => 'list'],
            ]],
            ['title' => __('crmui.nav.trading'), 'items' => [
                ['label' => __('crmui.front.pages.position_summary.title'), 'path' => 'position/summary', 'icon' => 'table-2'],
                ['label' => __('crmui.front.pages.open_orders.title'), 'path' => 'order/open', 'icon' => 'circle-play'],
                ['label' => __('crmui.front.pages.closed_orders.title'), 'path' => 'order/closed', 'icon' => 'history'],
            ]],
            ['title' => __('crmui.nav.agent'), 'items' => [
                ['label' => __('crmui.front.pages.agent_sub.title'), 'path' => 'agent/sub', 'icon' => 'network'],
                ['label' => __('crmui.front.pages.agent_customers.title'), 'path' => 'agent/customers', 'icon' => 'users-round'],
                ['label' => __('crmui.front.pages.agent_confirm_level.title'), 'path' => 'agent/confirm-level', 'icon' => 'badge-check'],
                ['label' => __('crmui.front.pages.agent_group_change.title'), 'path' => 'agent/group-change', 'icon' => 'arrow-left-right'],
                ['label' => __('crmui.front.pages.commission_realtime.title'), 'path' => 'commission/realtime', 'icon' => 'zap'],
                ['label' => __('crmui.front.pages.commission_history.title'), 'path' => 'commission/history', 'icon' => 'calendar-days'],
                ['label' => __('crmui.front.pages.commission_transfer.title'), 'path' => 'commission/transfer', 'icon' => 'send'],
            ]],
            ['title' => __('crmui.nav.system'), 'items' => [
                ['label' => __('crmui.front.pages.gift_address.title'), 'path' => 'gift/address', 'icon' => 'map-pin'],
                ['label' => __('crmui.front.pages.gift_list.title'), 'path' => 'gift/list', 'icon' => 'gift'],
                ['label' => __('crmui.front.pages.news.title'), 'path' => 'news', 'icon' => 'bell'],
            ]],
        ];
    }

    /**
     * 把字段声明(items)转成渲染结构:推导 label/type,并补齐文件上传的 accept/multiple 默认值。
     *
     * 类型推断规则:日期类 key(含旧 startdate/enddate)推断为 date,含 password 的 key 推断为 password,
     * isNumberField 白名单内的 key 推断为 number,其余为 text;显式声明的 type 优先。
     *
     * @param array<int, mixed> $items 字段声明,字符串或 ['name'=>..,'type'=>..,'options'=>..] 数组。
     * @return array<int, array<string, mixed>> 渲染用字段数组。
     */
    private function fields(array $items): array
    {
        return array_map(function ($item) {
            $key = is_array($item) ? $item['name'] : $item;
            $labelKey = is_array($item) ? ($item['label'] ?? $key) : $key;
            $type = is_array($item) && isset($item['type'])
                ? $item['type']
                : (in_array($key, ['date_from', 'date_to', 'start_date', 'end_date', 'startdate', 'enddate'], true) ? 'date' : (strpos($key, 'password') !== false ? 'password' : ($this->isNumberField($key) ? 'number' : 'text')));
            $label = $this->fieldLabel($labelKey);

            return [
                'name' => $key,
                'label' => $label,
                'type' => $type,
                'placeholder' => $label,
                'accept' => is_array($item) ? ($item['accept'] ?? 'image/*,.pdf') : 'image/*,.pdf',
                'multiple' => is_array($item) ? (bool) ($item['multiple'] ?? false) : false,
                'options' => is_array($item) ? $this->options($item['options'] ?? []) : [],
                'dynamicOptions' => is_array($item) ? ($item['dynamicOptions'] ?? '') : '',
                'source' => is_array($item) ? ($item['source'] ?? '') : '',
            ];
        }, $items);
    }

    /**
     * 解析字段显示名,按 crmui.fields.<key> → crmui.common.<key> → front.<key> 顺序查找翻译,均无翻译时原样返回 key。
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

        if ($label !== $commonKey) {
            return $label;
        }

        $frontKey = 'front.' . $key;
        $label = __($frontKey);

        return $label === $frontKey ? $key : $label;
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
            'credit',
            'equity',
            'fee',
            'free_margin',
            'margin',
            'points',
            'profit',
            'volume',
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
     * 把页签声明转成渲染结构:key/label/apiUrl/method(默认 GET)。
     *
     * @param array<int, array<string, mixed>> $tabs 页签声明。
     * @return array<int, array<string, mixed>> 渲染用页签数组。
     */
    private function viewTabs(array $tabs): array
    {
        return array_map(function ($tab) {
            return [
                'key' => $tab['key'],
                'label' => __('crmui.tabs.' . $tab['label']),
                'apiUrl' => route($tab['api']),
                'method' => $tab['method'] ?? 'GET',
            ];
        }, $tabs);
    }

    /**
     * 把行操作声明转成完整渲染结构。
     *
     * 输出 url(带路由参数)、method、variant、confirm 文案、字段、固定 payload 与记录主键,
     * 缺省项按默认值兜底;local 标记表示前端本地跳转而非接口调用。
     *
     * @param array<int, mixed> $actions 行操作声明列表。
     * @return array<int, array<string, mixed>> 渲染用操作数组。
     */
    private function rowActions(array $actions): array
    {
        return array_map(function ($action) {
            $route = $action['route'] ?? null;

            return [
                'key' => $action['key'],
                'label' => __('crmui.actions.' . $action['key']),
                'href' => $action['href'] ?? '',
                'url' => $route ? route($route, $action['params'] ?? []) : '',
                'method' => $action['method'] ?? 'POST',
                'variant' => $action['variant'] ?? 'default',
                'confirm' => __('crmui.confirms.' . ($action['confirm'] ?? $action['key'])),
                'fields' => $this->fields($action['fields'] ?? []),
                'payload' => $action['payload'] ?? [],
                'recordKey' => $action['recordKey'] ?? ($action['payloadKey'] ?? 'id'),
                'payloadName' => $action['payloadName'] ?? ($action['payloadKey'] ?? 'id'),
                'local' => !empty($action['local']),
            ];
        }, $actions);
    }

    /**
     * 列声明转成渲染结构,label 按字段翻译;数组列可携带 format 供前端按格式器渲染(如 agentLevelSelect)。
     *
     * @param array<int, mixed> $keys 列 key 或 ['key'=>..,'label'=>..,'format'=>..] 数组。
     * @return array<int, array<string, mixed>> 渲染用列数组。
     */
    private function columns(array $keys): array
    {
        return array_map(function ($column) {
            $key = is_array($column) ? ($column['key'] ?? '') : $column;
            $labelKey = is_array($column) ? ($column['label'] ?? $key) : $key;
            $result = ['key' => $key, 'label' => $this->fieldLabel($labelKey)];

            if (is_array($column) && isset($column['format'])) {
                $result['format'] = $column['format'];
            }
            if (is_array($column) && isset($column['action'])) {
                $result['action'] = $column['action'];
                $result['recordKey'] = $column['recordKey'] ?? $key;
            }

            return $result;
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
     * 页面级操作声明(字符串 key 列表)转成渲染结构,label 按 crmui.actions.<key> 翻译。
     *
     * @param array<int, string> $keys 操作 key 列表。
     * @return array<int, array{key: string, label: string}> 渲染用操作数组。
     */
    private function actions(array $keys): array
    {
        return array_map(function ($key) {
            return ['key' => $key, 'label' => __('crmui.actions.' . $key)];
        }, $keys);
    }
}
