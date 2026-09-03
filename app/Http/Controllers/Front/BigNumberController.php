<?php

/**
 * Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/09/03
 * Time: 14:30
 */

namespace App\Http\Controllers\Front;

use App\Constants\ResponseCode;
use App\Models\BigAgent;
use App\Models\BigAgentLoginLog;
use App\Models\UserInfo;
use App\Models\UserLogin;
use App\Models\UserTrade;
use App\Services\JwtService;
use App\Support\FrontLegacyData;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Validator;

/**
 * 前台大代理控制器。
 *
 * 文件功能：
 * - 兼容旧前台大代理入口 legacy /user/agents/*，同时提供新前台 `/api/front/auth/big-number/login` 登录接口。
 * - 旧入口读取 big_agents 表，使用 big_agents.sub_agent_ids 限定大代理可查看的直属代理范围。
 * - 新 big-number API 读取 user_logins 与 user_infos，只允许 account_type=1 的代理账号登录。
 * - 所有面向用户的登录、禁用、旧密码错误提示都使用 Laravel 多语言 key，避免后端硬编码单一语言。
 *
 * 会话切换与双身份语义：
 * - 旧入口以 big_agents（大代理表）+ Cookie session 认证；新 big-number API 以 user_logins/user_infos + user JWT 认证，
 *   两套身份互不转换，退出只清空当前入口的凭据。
 * - 旧 session 只保存展示字段（不含 password / jwt_token_id），旧接口身份唯一来源是 session 中的 bigAgents。
 * - 新接口只为 account_type=1 的代理账号签发 guard=user、portal=big_number 的 JWT。
 *
 * 安全边界：
 * - 登录失败按“IP + 账号”组合计数限流，超过阈值直接拒绝；连续失败达到 2 次后强制图形验证码。
 * - 图形验证码使用 Cache::pull 一次性消费，防止已用验证码重放；响应不回传明文答案。
 * - 改密成功后作废旧 JWT 并清空大代理会话，旧凭据立即失效。
 * - 大代理可见范围严格受 big_agents.sub_agent_ids 限定，列表接口中的任何 userId 筛选都不能越出该集合。
 */
class BigNumberController extends FrontBaseController
{
    /**
     * JWT 服务实例。
     *
     * @var JwtService
     */
    protected $jwtService;

    /**
     * 构造前台大代理控制器。
     *
     * @param JwtService $jwtService JWT 服务，用于生成 `big_agent` 或 `user` guard 的访问令牌。
     */
    public function __construct(JwtService $jwtService)
    {
        $this->jwtService = $jwtService;
    }

    /**
     * 渲染旧前台大代理登录页。
     *
     * 参数含义：
     * - $request：当前 HTTP 请求对象，读取旧系统传入的 langId 语言编号。
     * - legacyLangId：旧页面语言编号，保留给 Blade 页面兼容历史脚本。
     * - legacyWho：旧页面登录身份标识，bigAgents 表示大代理入口。
     *
     * @param Request $request 当前 HTTP 请求对象。
     * @return \Illuminate\Contracts\View\View
     */
    public function agentsLogin(Request $request)
    {
        return view('front_layui::auth.big-number-login', [
            'legacyLangId' => $request->query('langId', '1'),
            'legacyWho' => 'bigAgents',
            'legacyBigAgentLogin' => true,
        ]);
    }

    /**
     * 处理旧前台大代理登录提交。
     *
     * 业务逻辑说明：
     * - loginUid 表示旧前台提交的大代理登录名，也兼容 email 与 user_id 参数。
     * - loginPassword 表示旧前台提交的大代理登录密码，也兼容 password 参数。
     * - is_enabled 表示大代理账号是否允许登录，禁用账号不能建立 session。
     * - 登录成功后只写入旧页面使用的 `bigAgents` session，不把旧身份转换为普通 user JWT。
     * - 旧页面仍需要 errpsw、notactive、loginStatus 等字段，因此只替换消息来源，不改变 JSON 字段结构。
     *
     * @param Request $request 当前 HTTP 请求对象。
     * @return \Illuminate\Http\JsonResponse
     */
    public function agentsSignIn(Request $request)
    {
        $account = trim((string) $request->input('loginUid', $request->input('email', $request->input('user_id', ''))));
        $password = (string) $request->input('loginPassword', $request->input('password', ''));

        if ($this->isBigAgentLoginRateLimited($request, $account)) {
            return $this->legacyBigAgentRateLimitedResponse();
        }

        if ($account === '' || $password === '') {
            return response()->json([
                'errpsw' => __('auth.password_required'),
                'loginStatus' => 400,
            ]);
        }

        if ($this->bigAgentCaptchaRequired($request, $account)
            && !$this->consumeBigAgentCaptcha($request)) {
            $this->recordBigAgentLoginFailure($request, $account);

            return $this->legacyBigAgentCaptchaErrorResponse();
        }

        $bigAgent = filter_var($account, FILTER_VALIDATE_EMAIL)
            ? BigAgent::where('email', strtolower($account))->first()
            : BigAgent::where('username', $account)->first();

        if (!$bigAgent) {
            return response()->json([
                'notactive' => __('auth.failed'),
                'loginStatus' => 401,
            ]);
        }

        if ((int) $bigAgent->is_enabled !== 1) {
            return response()->json([
                'notactive' => __('auth.account_disabled'),
                'loginStatus' => 403,
            ]);
        }

        if (!Hash::check($password, (string) $bigAgent->password)) {
            $failures = $this->recordBigAgentLoginFailure($request, $account);

            return response()->json([
                'errpsw' => __('auth.failed'),
                'loginStatus' => 404,
                'captcha_required' => $failures >= 2,
            ]);
        }

        // 旧入口的认证载体是 Cookie session；现代 `/api/front/auth/big-number/login`
        // 仍单独签发 user guard JWT，两个入口不能互相替代。
        $request->session()->put('bigAgents', $this->legacyBigAgentPayload($bigAgent));

        BigAgentLoginLog::create([
            'big_agent_id' => $bigAgent->id,
            'login_ip' => $request->ip(),
            'login_at' => date('Y-m-d H:i:s'),
        ]);
        $this->clearBigAgentLoginFailures($request, $account);

        return response()->json([
            'msg' => 'OK',
            'loginStatus' => 200,
            'user' => [
                'id' => $bigAgent->id,
                'email' => $bigAgent->email,
                'username' => $bigAgent->username,
            ],
        ]);
    }

    /**
     * 渲染旧前台大代理首页。
     *
     * @param Request $request 当前 HTTP 请求对象，用于读取 session 中的 bigAgents 登录态。
     * @return \Illuminate\Contracts\View\View
     */
    public function agentsIndex(Request $request)
    {
        return view('front_layui::legacy-big-agent.dashboard', [
            'legacyBigAgent' => $this->currentBigAgentPayload($request),
        ]);
    }

    /**
     * 退出旧前台大代理登录。
     *
     * @param Request $request 当前 HTTP 请求对象，用于清理 session `bigAgents`。
     * @return \Illuminate\Http\RedirectResponse
     */
    public function loginOut(Request $request)
    {
        $sessionAgent = $this->sessionBigAgentData($request);
        $agentId = (int) ($sessionAgent['id'] ?? 0);
        $token = trim((string) ($sessionAgent['jwt_token_id'] ?? ''));

        if ($token === '' && $agentId > 0) {
            $token = trim((string) BigAgent::withTrashed()->whereKey($agentId)->value('jwt_token_id'));
        }
        if ($token !== '') {
            $this->jwtService->invalidateToken($token);
        }
        if ($agentId > 0) {
            BigAgent::withTrashed()->whereKey($agentId)->update(['jwt_token_id' => '']);
        }

        $request->session()->flush();

        if ($request->is('front-crmui/big-agent/logout')) {
            return redirect()->route('front_crmui_big_agent_login');
        }
        if ($request->is('front-naive/big-agent/logout')) {
            return redirect()->route('front_naive_big_agent_login');
        }

        return redirect('/agents/login');
    }

    /**
     * 渲染旧前台大代理控制台首页。
     *
     * @param Request $request 当前 HTTP 请求对象，读取 lang_id 与大代理 session。
     * @return \Illuminate\Contracts\View\View
     */
    public function agentsMainHome(Request $request)
    {
        return view('front_layui::legacy-big-agent.dashboard', [
            'legacyBigAgent' => $this->currentBigAgentPayload($request),
            'legacyLangId' => $request->query('lang_id', 1),
        ]);
    }

    /**
     * 渲染旧前台大代理下级代理列表页。
     *
     * @param Request $request 当前 HTTP 请求对象，用于注入大代理登录态。
     * @return \Illuminate\Contracts\View\View
     */
    public function proxy_agents_list_browse(Request $request)
    {
        return view('front_layui::legacy-big-agent.list', [
            'legacyBigAgent' => $this->currentBigAgentPayload($request),
            'legacyModule' => $this->legacyListModule('proxy'),
        ]);
    }

    /**
     * 渲染旧前台大代理持仓汇总页。
     *
     * @param Request $request 当前 HTTP 请求对象，用于注入大代理登录态。
     * @return \Illuminate\Contracts\View\View
     */
    public function position_agents_summary_browse(Request $request)
    {
        return view('front_layui::legacy-big-agent.list', [
            'legacyBigAgent' => $this->currentBigAgentPayload($request),
            'legacyModule' => $this->legacyListModule('position'),
        ]);
    }

    /**
     * 渲染旧前台大代理已平仓订单页。
     *
     * @param Request $request 当前 HTTP 请求对象，用于注入大代理登录态。
     * @return \Illuminate\Contracts\View\View
     */
    public function big_close_order_browse(Request $request)
    {
        return view('front_layui::legacy-big-agent.list', [
            'legacyBigAgent' => $this->currentBigAgentPayload($request),
            'legacyModule' => $this->legacyListModule('closed'),
        ]);
    }

    /**
     * 渲染旧前台大代理未平仓订单页。
     *
     * @param Request $request 当前 HTTP 请求对象，用于注入大代理登录态。
     * @return \Illuminate\Contracts\View\View
     */
    public function big_open_order_browse(Request $request)
    {
        return view('front_layui::legacy-big-agent.list', [
            'legacyBigAgent' => $this->currentBigAgentPayload($request),
            'legacyModule' => $this->legacyListModule('open'),
        ]);
    }

    /**
     * 渲染旧前台大代理修改密码页。
     *
     * @param Request $request 当前 HTTP 请求对象，用于注入大代理登录态。
     * @return \Illuminate\Contracts\View\View
     */
    public function agents_editpsw_browse(Request $request)
    {
        return view('front_layui::profile.change-password', [
            'legacyBigAgent' => $this->currentBigAgentPayload($request),
        ]);
    }

    /**
     * 查询旧前台大代理直属代理列表。
     *
     * @param Request $request 当前 HTTP 请求对象，承载分页、用户 ID、用户名和状态筛选参数。
     * @return \Illuminate\Http\JsonResponse
     */
    public function bigNumberListSearch(Request $request)
    {
        return $this->legacyAgentListResponse($request, false);
    }

    /**
     * 查询旧前台大代理直属代理及其下级代理列表。
     *
     * @param Request $request 当前 HTTP 请求对象，承载分页、用户 ID、用户名和状态筛选参数。
     * @return \Illuminate\Http\JsonResponse
     */
    public function bigNumberListSearchBySubAgents(Request $request)
    {
        return $this->legacyAgentListResponse($request, true);
    }

    /**
     * 查询旧前台大代理持仓汇总列表。
     *
     * @param Request $request 当前 HTTP 请求对象，承载日期和代理筛选参数。
     * @return \Illuminate\Http\JsonResponse
     */
    public function bigPositionSummarySearch(Request $request)
    {
        return $this->legacyPositionSummaryResponse($request, false);
    }

    /**
     * 查询旧前台大代理下级代理持仓统计。
     *
     * @param Request $request 当前 HTTP 请求对象，承载下级代理筛选参数。
     * @return \Illuminate\Http\JsonResponse
     */
    public function bigSubPositionSummaryStats(Request $request)
    {
        return $this->legacyPositionSummaryResponse($request, true);
    }

    /**
     * 查询旧前台大代理已平仓订单。
     *
     * @param Request $request 当前 HTTP 请求对象，承载 userId、symbol、orderId 和日期筛选参数。
     * @return \Illuminate\Http\JsonResponse
     */
    public function bigCloseOrderSearch(Request $request)
    {
        return $this->legacyOrderListResponse($request, false);
    }

    /**
     * 查询旧前台大代理未平仓订单。
     *
     * @param Request $request 当前 HTTP 请求对象，承载 userId、symbol、orderId 和日期筛选参数。
     * @return \Illuminate\Http\JsonResponse
     */
    public function bigOpenOrderSearch(Request $request)
    {
        return $this->legacyOrderListResponse($request, true);
    }

    /**
     * 保存旧前台大代理密码修改。
     *
     * 参数含义：
     * - old_password / oldPassword / old_psw：旧密码，传入时必须与 big_agents.password 匹配。
     * - password / new_password / newPassword：新密码，不能为空。
     * - errorType：旧前台识别错误类型的兼容字段，AUTH 表示未登录，PARAM 表示缺少新密码，OLD_PASSWORD 表示旧密码错误。
     *
     * @param Request $request 当前 HTTP 请求对象。
     * @return \Illuminate\Http\JsonResponse
     */
    public function changePasswordSave(Request $request)
    {
        return $this->saveLegacyBigAgentPassword($request, false);
    }

    /**
     * 兼容旧大代理资料页的密码保存入口。
     *
     * 旧页面提交 olduserpsw、newuserpsw、confirmuserpsw，并依赖 SUCCESS/FAIL
     * 响应；身份始终来自 bigAgents session，不读取请求中的用户 ID。
     *
     * @param Request $request 当前 HTTP 请求对象。
     * @return \Illuminate\Http\JsonResponse
     */
    public function agentsEditPasswordSave(Request $request)
    {
        return $this->saveLegacyBigAgentPassword($request, true);
    }

    /**
     * 新前台 big-number API 登录。
     *
     * 业务逻辑说明：
     * - email 表示代理登录邮箱，user_id 表示代理业务用户 ID，两者至少传入一个。
     * - password 表示登录密码，用于校验 user_logins.password。
     * - 只有 user_infos.account_type=1 的代理账号允许进入 big-number 入口，普通客户返回权限不足。
     *
     * @param Request $request 当前 HTTP 请求对象。
     * @return \Illuminate\Http\JsonResponse
     */
    public function login(Request $request)
    {
        $account = trim((string) $request->input('email', $request->input('user_id', '')));
        if ($this->isBigAgentLoginRateLimited($request, $account)) {
            return $this->error('response.rate_limited', ResponseCode::RATE_LIMITED);
        }

        $validator = Validator::make($request->all(), [
            'email' => 'required_without:user_id',
            'user_id' => 'required_without:email',
            'password' => 'required',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), ResponseCode::VALIDATION_FAILED);
        }

        if ($this->bigAgentCaptchaRequired($request, $account)
            && !$this->consumeBigAgentCaptcha($request)) {
            $this->recordBigAgentLoginFailure($request, $account);

            return $this->error(__('auth.invalid_captcha'), ResponseCode::AUTH_FAILED, [
                'captcha_required' => true,
            ]);
        }

        $login = $request->filled('email')
            ? UserLogin::where('email', $request->input('email'))->first()
            : UserLogin::where('user_id', $request->input('user_id'))->first();

        // 账号不存在、密码错误与账号禁用返回同一失败文案，避免泄漏账号存在性。
        if (!$login || !Hash::check($request->input('password'), $login->password) || !$login->isActive()) {
            $failures = $this->recordBigAgentLoginFailure($request, $account);

            return $this->error(__('auth.failed'), ResponseCode::AUTH_FAILED, [
                'captcha_required' => $failures >= 2,
            ]);
        }

        // 大数字入口只接受代理账号（account_type=1），普通客户即使凭据正确也拒绝签发令牌。
        $info = UserInfo::where('user_id', $login->user_id)->first();
        if (!$info || (int) $info->account_type !== 1) {
            return $this->error('response.permission_denied', ResponseCode::PERMISSION_DENIED);
        }

        $token = $this->jwtService->generateToken([
            'sub' => $login->id,
            'guard' => 'user',
            'portal' => 'big_number',
        ]);
        $this->clearBigAgentLoginFailures($request, $account);

        return $this->success([
            'access_token' => $token,
            'user' => [
                'user_id' => (int) $login->user_id,
                'email' => $login->email,
            ],
        ], 'auth.login_success', ResponseCode::SUCCESS);
    }

    /**
     * 生成 SVG 图形验证码；验证码答案只写入缓存，响应中不能回传明文。
     */
    public function captcha(Request $request)
    {
        $key = preg_replace('/[^a-zA-Z0-9_-]/', '', (string) $request->query('key', ''));
        if ($key === '') {
            $key = bin2hex(random_bytes(16));
        }

        if ($request->hasSession()) {
            $request->session()->put('big_agent_captcha_key', $key);
        }

        $code = strtoupper(substr(str_shuffle('ABCDEFGHJKLMNPQRSTUVWXYZ23456789'), 0, 5));
        Cache::put($this->bigAgentCaptchaCacheKey($key), $code, now()->addMinutes(10));

        $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="132" height="44" viewBox="0 0 132 44">'
            . '<rect width="132" height="44" fill="#f8fafc"/>'
            . '<path d="M6 12 C30 38, 60 4, 126 30" stroke="#cbd5e1" fill="none" stroke-width="2"/>'
            . '<path d="M10 32 C42 6, 78 42, 122 12" stroke="#dbeafe" fill="none" stroke-width="2"/>'
            . '<text x="18" y="30" font-family="Arial, sans-serif" font-size="22" font-weight="700" letter-spacing="4" fill="#1f2937">'
            . e($code)
            . '</text></svg>';

        return response($svg, 200, [
            'Content-Type' => 'image/svg+xml',
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
        ]);
    }

    /**
     * agentSubList 用于新前台 big-number API 查询当前代理直属下级代理。
     *
     * 参数含义：
     * - $request：当前 HTTP 请求对象，读取 user guard 登录账号和 userId 筛选参数。
     * - userId：可选的下级代理业务用户 ID 精确筛选。
     *
     * @param Request $request 当前 HTTP 请求对象。
     * @return \Illuminate\Http\JsonResponse
     */
    public function agentSubList(Request $request)
    {
        $user = $request->user('user');
        $query = UserInfo::query()
            ->where('parent_id', (int) $user->user_id)
            ->where('account_type', 1);

        if ($request->filled('userId')) {
            $query->where('user_id', (int) $request->input('userId'));
        }

        $userIds = (clone $query)->pluck('user_id')->map(function ($id) {
            return (int) $id;
        })->all();
        $totalRow = FrontLegacyData::financialTotalRowForUserIds($userIds, $request, 'user_id');

        $list = $query->orderBy('user_id')
            ->paginate(FrontLegacyData::perPage($request))
            ->through(function (UserInfo $agent) use ($request) {
                return array_merge(
                    FrontLegacyData::userBasicAlias($agent),
                    FrontLegacyData::userFinancialSummary($agent, $request, true)
                );
            });

        return $this->success(
            FrontLegacyData::paginatedListResponse($list, $totalRow),
            'response.query_success',
            ResponseCode::SUCCESS
        );
    }

    /**
     * 返回当前旧前台大代理 session 数据。
     *
     * @param Request $request 当前 HTTP 请求对象，用于读取 session `bigAgents`。
     * @return array 大代理数组；未登录时返回空数组。
     */
    private function currentBigAgentPayload(Request $request): array
    {
        $bigAgent = $this->currentBigAgent($request);

        return $bigAgent ? $this->legacyBigAgentPayload($bigAgent) : [];
    }

    /**
     * 只向 legacy 页面暴露展示字段，避免把 password/jwt_token_id 写入 HTML。
     */
    private function legacyBigAgentPayload(BigAgent $bigAgent): array
    {
        return [
            'id' => (int) $bigAgent->id,
            'username' => (string) $bigAgent->username,
            'email' => (string) $bigAgent->email,
            'sub_agent_ids' => (string) $bigAgent->sub_agent_ids,
        ];
    }

    /**
     * 为旧大代理页面生成固定的 endpoint、筛选字段和列定义。
     * 页面只消费这些旧 POST，避免误把现代 API 的 `data` 响应当成根级 rows。
     *
     * @return array<string, mixed>
     */
    private function legacyListModule(string $type): array
    {
        $modules = [
            'proxy' => [
                'title' => 'front.sub_agents',
                'endpoint' => '/user/agents/proxy/proxySearch',
                'childEndpoint' => '/user/agents/proxy/proxySearchBySub',
                'filters' => [
                    ['name' => 'userId', 'label' => 'front.user_id'],
                    ['name' => 'username', 'label' => 'front.user_name'],
                    ['name' => 'userstatus', 'label' => 'front.auth_status'],
                    ['name' => 'startdate', 'label' => 'front.date_from', 'type' => 'date'],
                    ['name' => 'enddate', 'label' => 'front.date_to', 'type' => 'date'],
                ],
                'columns' => [
                    ['key' => 'user_id', 'label' => 'front.user_id'],
                    ['key' => 'user_name', 'label' => 'front.user_name'],
                    ['key' => 'agentsTotal', 'label' => 'front.agent_count'],
                    ['key' => 'accountTotal', 'label' => 'front.customer_count'],
                    ['key' => 'user_money', 'label' => 'front.balance'],
                    ['key' => 'cust_eqy', 'label' => 'front.customer_equity'],
                    ['key' => 'fy_money', 'label' => 'front.total_rebate'],
                    ['key' => 'rj_money', 'label' => 'front.total_deposit'],
                    ['key' => 'qk_money', 'label' => 'front.total_withdraw'],
                    ['key' => 'group_comm_prop', 'label' => 'front.commission_rate'],
                    ['key' => 'rec_crt_date', 'label' => 'common.created_at'],
                ],
            ],
            'position' => [
                'title' => 'front.position_summary',
                'endpoint' => '/user/agents/position/positionSummarySearch',
                'childEndpoint' => '/user/agents/position/subAgentsListSearch',
                'filters' => [
                    ['name' => 'userId', 'label' => 'front.user_id'],
                    ['name' => 'username', 'label' => 'front.user_name'],
                    ['name' => 'symbol', 'label' => 'front.symbol', 'type' => 'select', 'endpoint' => '/user/agents/trade-symbols'],
                    ['name' => 'startdate', 'label' => 'front.date_from', 'type' => 'date'],
                    ['name' => 'enddate', 'label' => 'front.date_to', 'type' => 'date'],
                ],
                'columns' => [
                    ['key' => 'user_id', 'label' => 'front.user_id'],
                    ['key' => 'user_name', 'label' => 'front.user_name'],
                    ['key' => 'user_money', 'label' => 'front.balance'],
                    ['key' => 'cust_eqy', 'label' => 'front.customer_equity'],
                    ['key' => 'total_rj', 'label' => 'front.total_deposit'],
                    ['key' => 'total_fy', 'label' => 'front.total_rebate'],
                    ['key' => 'total_qk', 'label' => 'front.total_withdraw'],
                    ['key' => 'total_net_worth', 'label' => 'front.net_worth'],
                    ['key' => 'total_profit', 'label' => 'front.total_profit'],
                    ['key' => 'total_volume', 'label' => 'front.total_volume'],
                ],
            ],
            'open' => [
                'title' => 'front.open_orders',
                'endpoint' => '/user/agents/open/openOrderSearch',
                'childEndpoint' => '',
                'filters' => $this->legacyOrderFilters(),
                'columns' => $this->legacyOrderColumns(true),
            ],
            'closed' => [
                'title' => 'front.closed_orders',
                'endpoint' => '/user/agents/close/closeOrderSearch',
                'childEndpoint' => '',
                'filters' => $this->legacyOrderFilters(),
                'columns' => $this->legacyOrderColumns(false),
            ],
        ];

        return $modules[$type] ?? $modules['proxy'];
    }

    /** @return array<int, array<string, string>> */
    private function legacyOrderFilters(): array
    {
        return [
            ['name' => 'userId', 'label' => 'front.user_id'],
            ['name' => 'orderUserId', 'label' => 'front.user_id'],
            ['name' => 'orderId', 'label' => 'front.order_no'],
            ['name' => 'symbol', 'label' => 'front.symbol', 'type' => 'select', 'endpoint' => '/user/agents/trade-symbols'],
            ['name' => 'startdate', 'label' => 'front.date_from', 'type' => 'date'],
            ['name' => 'enddate', 'label' => 'front.date_to', 'type' => 'date'],
        ];
    }

    /** @return array<int, array<string, string>> */
    private function legacyOrderColumns(bool $open): array
    {
        $columns = [
            ['key' => 'ticket', 'label' => 'front.ticket'],
            ['key' => 'login', 'label' => 'front.user_id'],
            ['key' => 'symbol', 'label' => 'front.symbol'],
            ['key' => 'cmd_text', 'label' => 'front.order_cmd'],
            ['key' => 'volume_lots', 'label' => 'front.volume'],
            ['key' => 'commission', 'label' => 'front.commission'],
            ['key' => 'profit', 'label' => 'front.profit'],
            ['key' => 'swaps', 'label' => 'front.swaps'],
            ['key' => 'open_price', 'label' => 'front.open_price'],
            ['key' => 'open_time', 'label' => 'front.open_time'],
        ];

        if ($open) {
            return $columns;
        }

        $columns[] = ['key' => 'close_price', 'label' => 'front.close_price'];
        $columns[] = ['key' => 'close_time', 'label' => 'front.close_time'];

        return $columns;
    }

    /**
     * 保存旧大代理密码，并保留历史页面依赖的 msg/data/code 协议。
     */
    private function saveLegacyBigAgentPassword(Request $request, bool $requireConfirmation)
    {
        $bigAgent = $this->currentBigAgent($request);
        if (!$bigAgent) {
            return $this->legacyBigAgentPasswordResponse(
                1010,
                __('response.user_not_found'),
                'userNotFound',
                'userId',
                'AUTH',
                $requireConfirmation
            );
        }

        $input = $request->all();
        $oldPassword = $this->firstPasswordInput($input, ['olduserpsw', 'old_password', 'oldPassword', 'old_psw']) ?? '';
        $newPassword = $this->firstPasswordInput($input, ['newuserpsw', 'password', 'new_password', 'newPassword']) ?? '';
        $confirmPassword = $this->firstPasswordInput($input, ['confirmuserpsw', 'password_confirmation', 'confirm_password', 'confirmPassword']);
        $usesLegacyFields = array_key_exists('olduserpsw', $input)
            || array_key_exists('newuserpsw', $input)
            || array_key_exists('confirmuserpsw', $input);

        if ($oldPassword === '' || !Hash::check($oldPassword, (string) $bigAgent->password)) {
            return $this->legacyBigAgentPasswordResponse(
                1011,
                __('auth.old_password_error'),
                'localpswerr',
                'olduserpsw',
                'OLD_PASSWORD',
                $requireConfirmation
            );
        }
        if ($newPassword === '') {
            return $this->legacyBigAgentPasswordResponse(
                1000,
                __('response.validation_failed'),
                'newuserpsw',
                'newuserpsw',
                'PARAM',
                $requireConfirmation
            );
        }
        // 旧页面会在浏览器端阻止弱密码和复用旧密码；后端必须再次校验，
        // 防止绕过 JavaScript 的请求把不可接受的凭证写入 big_agents。
        if (strlen($newPassword) < 6 || hash_equals($oldPassword, $newPassword)) {
            return $this->legacyBigAgentPasswordResponse(
                1000,
                __('response.validation_failed'),
                'newuserpsw',
                'newuserpsw',
                'PARAM',
                $requireConfirmation
            );
        }
        if (($requireConfirmation || $usesLegacyFields || $confirmPassword !== null)
            && ($confirmPassword === null || $newPassword !== $confirmPassword)) {
            return $this->legacyBigAgentPasswordResponse(
                1000,
                __('response.validation_failed'),
                'confirmuserpsw',
                'confirmuserpsw',
                'PARAM',
                $requireConfirmation
            );
        }

        $sessionAgent = $this->sessionBigAgentData($request);
        $token = trim((string) ($sessionAgent['jwt_token_id'] ?? ''));
        if ($token === '') {
            $token = trim((string) ($bigAgent->jwt_token_id ?? ''));
        }
        $bigAgent->password = Hash::make($newPassword);
        $bigAgent->jwt_token_id = '';

        if (!$bigAgent->save()) {
            return $this->legacyBigAgentPasswordResponse(
                1000,
                __('response.server_error'),
                'neterr',
                'nocol',
                'SYSTEM',
                $requireConfirmation
            );
        }

        if ($token !== '') {
            // 改密成功后作废已保存的旧令牌并清空大代理会话，旧凭据立即失效。
            $this->jwtService->invalidateToken($token);
        }
        $request->session()->flush();

        return response()->json([
            'msg' => 'SUCCESS',
            'data' => [],
            'code' => 0,
            'err' => 'noerr',
            'col' => 'nocol',
            'loginStatus' => 200,
        ]);
    }

    /**
     * @param array<string, mixed> $input
     * @param array<int, string> $keys
     */
    private function firstPasswordInput(array $input, array $keys): ?string
    {
        foreach ($keys as $key) {
            if (array_key_exists($key, $input)) {
                return (string) $input[$key];
            }
        }

        return null;
    }

    /**
     * @return array<string, mixed>
     */
    private function sessionBigAgentData(Request $request): array
    {
        $sessionAgent = $request->session()->get('bigAgents', []);
        if (is_array($sessionAgent)) {
            return $sessionAgent;
        }
        if (is_object($sessionAgent)) {
            return method_exists($sessionAgent, 'toArray')
                ? $sessionAgent->toArray()
                : get_object_vars($sessionAgent);
        }

        return [];
    }

    /**
     * 将失败计数与限流状态限定在 IP 和账号组合内，避免同账号在其他 IP 被误锁。
     */
    private function bigAgentLoginKey(Request $request, string $account): string
    {
        return 'front_big_agent_login_' . sha1(strtolower(trim($account)) . '|' . $request->ip());
    }

    /**
     * 生成验证码缓存键。
     *
     * 对客户端传入的 key 做 sha1 哈希后拼接固定前缀，避免原始 key 直接作为 Cache 键被注入或碰撞。
     *
     * @param string $key 客户端提交的验证码标识或服务端随机生成的原始 key。
     * @return string 用于 Cache 存储的完整缓存键。
     */
    private function bigAgentCaptchaCacheKey(string $key): string
    {
        return 'front_big_agent_captcha_' . sha1($key);
    }

    /**
     * 判断当前 IP 与账号组合是否已触发图形验证码门槛。
     *
     * 连续登录失败达到 2 次后强制要求验证码，用于延缓暴力破解；阈值与限流计数共用同一缓存键。
     *
     * @param Request $request 当前 HTTP 请求对象，用于获取客户端 IP。
     * @param string $account 登录账号，参与限流键计算。
     * @return bool true=需要先通过图形验证码，false=尚未达到门槛。
     */
    private function bigAgentCaptchaRequired(Request $request, string $account): bool
    {
        return RateLimiter::attempts($this->bigAgentLoginKey($request, $account)) >= 2;
    }

    /**
     * 判断当前 IP 与账号组合是否超过登录尝试上限。
     *
     * 上限为 8 次；达到上限后直接拒绝登录，防止对同一账号的暴力猜测。
     *
     * @param Request $request 当前 HTTP 请求对象，用于获取客户端 IP。
     * @param string $account 登录账号，参与限流键计算。
     * @return bool true=已被限流禁止登录，false=允许继续尝试。
     */
    private function isBigAgentLoginRateLimited(Request $request, string $account): bool
    {
        return RateLimiter::tooManyAttempts($this->bigAgentLoginKey($request, $account), 8);
    }

    /**
     * 记录一次大代理登录失败。
     *
     * 失败计数在 600 秒内有效，返回累计失败次数供调用方决定是否要求图形验证码。
     *
     * @param Request $request 当前 HTTP 请求对象，用于获取客户端 IP。
     * @param string $account 登录账号，参与限流键计算。
     * @return int 累计失败次数（含本次）。
     */
    private function recordBigAgentLoginFailure(Request $request, string $account): int
    {
        $key = $this->bigAgentLoginKey($request, $account);
        RateLimiter::hit($key, 600);

        return RateLimiter::attempts($key);
    }

    /**
     * 登录成功后清除该 IP 与账号组合的失败计数。
     *
     * 避免已通过认证的账号继续被历史失败次数拖累而要求验证码。
     *
     * @param Request $request 当前 HTTP 请求对象，用于获取客户端 IP。
     * @param string $account 登录账号，参与限流键计算。
     * @return void
     */
    private function clearBigAgentLoginFailures(Request $request, string $account): void
    {
        RateLimiter::clear($this->bigAgentLoginKey($request, $account));
    }

    /**
     * 先消费缓存验证码再比较，防止任何已提交的有效验证码被重放。
     */
    private function consumeBigAgentCaptcha(Request $request): bool
    {
        $key = trim((string) $request->input('captcha_key', $request->input('captchaKey', '')));
        if ($key === '' && $request->hasSession()) {
            $key = trim((string) $request->session()->get('big_agent_captcha_key', ''));
        }
        $input = strtoupper(trim((string) $request->input('captcha_code', $request->input('cptcode', ''))));

        if ($key === '' || $input === '') {
            return false;
        }

        $cacheKey = $this->bigAgentCaptchaCacheKey($key);
        $expected = Cache::pull($cacheKey);

        return is_string($expected) && hash_equals(strtoupper($expected), $input);
    }

    /**
     * 生成旧前台验证码错误响应。
     *
     * 保持旧页面依赖的 errcptcode/loginStatus/captcha_required 字段结构，并标记后续仍需验证码。
     *
     * @return \Illuminate\Http\JsonResponse 旧前台验证码失败响应。
     */
    private function legacyBigAgentCaptchaErrorResponse()
    {
        return response()->json([
            'errcptcode' => __('auth.invalid_captcha'),
            'loginStatus' => 400,
            'captcha_required' => true,
        ]);
    }

    /**
     * 生成旧前台登录限流响应。
     *
     * 同时携带现代 code/message 与旧 loginStatus=429，保证新旧调用方都能识别被限流。
     *
     * @return \Illuminate\Http\JsonResponse 旧前台限流失败响应。
     */
    private function legacyBigAgentRateLimitedResponse()
    {
        return response()->json([
            'code' => ResponseCode::RATE_LIMITED,
            'message' => __('response.rate_limited'),
            'loginStatus' => 429,
        ]);
    }

    /**
     * 生成旧大代理密码修改统一响应。
     *
     * 旧页面依赖 msg/data/code/err/col/errorType 固定结构；msg 为 FAIL 时表示业务失败，否则为 error。
     *
     * @param int $code 旧前台业务状态码，0=成功，非 0=失败。
     * @param string $message 多语言提示文案。
     * @param string $err 旧前台错误码。
     * @param string $col 旧前台高亮字段名。
     * @param string $errorType 旧页面识别错误类型的兼容字段，取值 AUTH/PARAM/OLD_PASSWORD/SYSTEM。
     * @param bool $useFailMessage true 时 msg 输出 FAIL，false 时输出 error。
     * @return \Illuminate\Http\JsonResponse 旧前台密码修改响应。
     */
    private function legacyBigAgentPasswordResponse(
        int $code,
        string $message,
        string $err,
        string $col,
        string $errorType,
        bool $useFailMessage
    ) {
        return response()->json([
            'msg' => $useFailMessage ? 'FAIL' : 'error',
            'data' => [],
            'code' => $code,
            'err' => $err,
            'col' => $col,
            'errorType' => $errorType,
            'message' => $message,
        ]);
    }

    /**
     * 解析当前旧前台大代理模型。
     *
     * 参数含义：
     * - bigAgents：旧前台登录成功后写入 session 的大代理数组，也是旧 Ajax 接口唯一可信身份来源。
     *
     * @param Request $request 当前 HTTP 请求对象。
     * @return BigAgent|null 当前大代理模型；无法识别时返回 null。
     */
    private function currentBigAgent(Request $request): ?BigAgent
    {
        $sessionAgent = $this->sessionBigAgentData($request);
        $agentId = (int) ($sessionAgent['id'] ?? 0);

        if ($agentId > 0) {
            return BigAgent::query()
                ->whereKey($agentId)
                ->where('is_enabled', 1)
                ->first();
        }

        return null;
    }

    /**
     * 计算当前大代理可查看的代理 ID 集合。
     *
     * 参数含义：
     * - $request：当前 HTTP 请求对象，用于读取大代理登录态。
     * - includeDescendants 表示是否把直属代理的下级代理一并纳入查询范围。
     * - sub_agent_ids 表示大代理可查看的直属代理 user_id 集合，来源于 big_agents.sub_agent_ids。
     *
     * @param Request $request 当前 HTTP 请求对象。
     * @param bool $includeDescendants 是否包含直属代理的下级代理。
     * @return array<int> 可查看代理业务用户 ID 列表。
     */
    private function subAgentIdsForRequest(Request $request, bool $includeDescendants): array
    {
        $bigAgent = $this->currentBigAgent($request);
        if (!$bigAgent) {
            return [];
        }

        $subAgentIds = collect(explode(',', (string) $bigAgent->sub_agent_ids))
            ->map(function ($id) {
                return (int) trim($id);
            })
            ->filter()
            ->unique()
            ->values()
            ->all();

        if (!$includeDescendants || !$subAgentIds) {
            return $subAgentIds;
        }

        $scope = FrontLegacyData::strictAgentNetworkIdsOrNull($subAgentIds);
        if ($scope === null) {
            return [];
        }

        return $scope['agent_ids'];
    }

    /**
     * 补齐旧大代理列表默认日期范围。
     *
     * 旧控制器在未传日期时固定查询 2024-01-01 至当天；只补空值，显式传入的
     * date_from/date_to 或 startdate/enddate 继续由 FrontLegacyData 统一解析。
     */
    private function ensureLegacyDefaultDateRange(Request $request): void
    {
        if (FrontLegacyData::dateFrom($request) === null) {
            $request->merge(['startdate' => '2024-01-01']);
        }
        if (FrontLegacyData::dateTo($request) === null) {
            $request->merge(['enddate' => date('Y-m-d')]);
        }
    }

    /**
     * legacyAgentListResponse 用于旧前台大代理代理列表接口。
     *
     * 参数含义：
     * - $request：当前 HTTP 请求对象，承载 page、limit、userId、username、userstatus 和日期筛选参数。
     * - includeDescendants 表示是否把直属代理的下级代理一并纳入查询范围。
     * - rows/total/footer：旧前台表格插件要求的固定返回结构。
     *
     * @param Request $request 当前 HTTP 请求对象。
     * @param bool $includeDescendants 是否包含下级代理网络。
     * @return \Illuminate\Http\JsonResponse
     */
    private function legacyAgentListResponse(Request $request, bool $includeDescendants)
    {
        $this->ensureLegacyDefaultDateRange($request);

        // 可见范围严格来自 big_agents.sub_agent_ids 配置（含可选后代），请求中的筛选参数只能在其内收窄。
        $configuredAgentIds = $this->subAgentIdsForRequest($request, false);
        $agentIds = $configuredAgentIds;
        $parentId = null;
        if ($includeDescendants && $request->filled('userPId')) {
            $parentId = (int) $request->input('userPId');
            $allowedAgentIds = $this->subAgentIdsForRequest($request, true);
            if (!in_array($parentId, $allowedAgentIds, true)) {
                return response()->json(['rows' => [], 'total' => 0, 'footer' => []]);
            }

            $agentIds = FrontLegacyData::userScopeIds($parentId, false, 1, true);
        }
        if (!$agentIds) {
            return response()->json([
                'rows' => [],
                'total' => 0,
                'footer' => [],
            ]);
        }

        $query = UserInfo::with(['login', 'level'])
            ->where('account_type', 1)
            ->whereIn('user_id', $agentIds);

        $selectedAgentId = FrontLegacyData::requestedUserId($request);
        if ($includeDescendants && $parentId !== null && $selectedAgentId === $parentId) {
            $selectedAgentId = null;
        }
        if ($selectedAgentId !== null) {
            if (!in_array($selectedAgentId, $agentIds, true)) {
                return response()->json(['rows' => [], 'total' => 0, 'footer' => []]);
            }
            $query->where('user_id', $selectedAgentId);
        }
        if ($request->filled('username')) {
            $query->where('user_name', 'like', '%' . $request->input('username') . '%');
        }
        if ($request->filled('userstatus')) {
            $query->where('auth_status', (int) $request->input('userstatus'));
        }

        FrontLegacyData::applyCreatedAtFilter($query, $request);

        $filteredAgentIds = (clone $query)->pluck('user_id')->map(function ($id) {
            return (int) $id;
        })->all();
        $financialSummaries = FrontLegacyData::legacyMt4AgentFinancialSummariesByUserIds($filteredAgentIds);
        $total = count($filteredAgentIds);
        $pageAgents = $query->orderBy('user_id')
            ->forPage((int) $request->input('page', 1), FrontLegacyData::perPage($request))
            ->get();
        $pageAgentIds = $pageAgents->pluck('user_id')->map(function ($id) {
            return (int) $id;
        })->all();
        $childCounts = UserInfo::query()
            ->whereIn('parent_id', $pageAgentIds)
            ->whereIn('account_type', [1, 2])
            ->selectRaw('parent_id, account_type, COUNT(*) AS aggregate')
            ->groupBy('parent_id', 'account_type')
            ->get()
            ->mapWithKeys(function (UserInfo $row) {
                return [
                    ((int) $row->parent_id) . ':' . ((int) $row->account_type) => (int) $row->aggregate,
                ];
            });

        $rows = $pageAgents
            ->map(function (UserInfo $agent) use ($childCounts, $financialSummaries) {
                return array_merge(
                    FrontLegacyData::userBasicAlias($agent),
                    $financialSummaries[(int) $agent->user_id],
                    [
                        'id' => (int) $agent->id,
                        'sub_ag_id' => (int) $agent->user_id,
                        'sub_ag_name' => (string) $agent->user_name,
                        'agentsTotal' => (int) $childCounts->get(((int) $agent->user_id) . ':1', 0),
                        'accountTotal' => (int) $childCounts->get(((int) $agent->user_id) . ':2', 0),
                    ]
                );
            })
            ->values()
            ->all();

        $footer = array_merge(FrontLegacyData::legacyMt4PositionTotalRow(array_values($financialSummaries)), [
            'user_id' => __('systemlanguage.total'),
            'user_name' => '',
            'agentsTotal' => '',
            'accountTotal' => '',
            'group_comm_prop' => '',
            'rec_crt_date' => '',
        ]);

        return response()->json([
            'rows' => $rows,
            'total' => $total,
            'footer' => [$footer],
        ]);
    }

    /**
     * 生成旧大代理持仓汇总响应。
     *
     * 主查询以 big_agents.sub_agent_ids 中的直属代理为行；下钻查询以 userPId 的直属代理为行。
     * 每行统计范围包含该代理本人和全部后代用户，金额口径统一读取 user_trades。
     */
    private function legacyPositionSummaryResponse(Request $request, bool $childSearch)
    {
        $configuredAgentIds = $this->subAgentIdsForRequest($request, false);
        if ($configuredAgentIds === []) {
            return response()->json(['rows' => [], 'total' => 0, 'footer' => []]);
        }

        $rowAgentIds = $configuredAgentIds;
        $parentId = null;
        if ($childSearch && $request->filled('userPId')) {
            $parentId = (int) $request->input('userPId');
            $allowedAgentIds = $this->subAgentIdsForRequest($request, true);
            if (!in_array($parentId, $allowedAgentIds, true)) {
                return response()->json(['rows' => [], 'total' => 0, 'footer' => []]);
            }

            $rowAgentIds = FrontLegacyData::userScopeIds($parentId, false, 1, true);
        }

        $selectedAgentId = FrontLegacyData::requestedUserId($request);
        if ($childSearch && $parentId !== null && $selectedAgentId === $parentId) {
            $selectedAgentId = null;
        }
        if ($selectedAgentId !== null) {
            if (!in_array($selectedAgentId, $rowAgentIds, true)) {
                return response()->json(['rows' => [], 'total' => 0, 'footer' => []]);
            }
            $rowAgentIds = [$selectedAgentId];
        }

        $query = UserInfo::with(['login', 'level'])
            ->where('account_type', 1)
            ->whereIn('user_id', $rowAgentIds);
        $userName = trim((string) $request->input('username', $request->input('userName', '')));
        if ($userName !== '') {
            $query->where('user_name', 'like', '%' . $userName . '%');
        }

        $allAgents = (clone $query)->orderBy('user_id')->get();
        $total = $allAgents->count();
        $pageAgents = $query->orderBy('user_id')
            ->forPage((int) $request->input('page', 1), FrontLegacyData::perPage($request))
            ->get();

        $scopes = FrontLegacyData::userScopesForAgentIds(
            $allAgents->pluck('user_id')->map(static function ($id): int {
                return (int) $id;
            })->all(),
            true
        );
        $summaries = FrontLegacyData::legacyMt4PositionSummariesForScopes($scopes, $request);
        $allSummaryRows = array_values($summaries);

        $rows = $pageAgents->map(function (UserInfo $agent) use ($summaries) {
            $summary = $summaries[(int) $agent->user_id];

            return array_merge(
                FrontLegacyData::userBasicAlias($agent),
                $summary,
                [
                    'id' => (int) $agent->id,
                    'sub_ag_id' => (int) $agent->user_id,
                    'sub_ag_name' => (string) $agent->user_name,
                ]
            );
        })->values()->all();

        return response()->json([
            'rows' => $rows,
            'total' => $total,
            'footer' => [FrontLegacyData::legacyMt4PositionTotalRow($allSummaryRows)],
        ]);
    }

    /**
     * legacyOrderListResponse 用于旧前台大代理已平仓和未平仓订单接口。
     *
     * 参数含义：
     * - $request：当前 HTTP 请求对象，承载 userId、symbol、orderId 和日期筛选参数。
     * - open 表示是否查询未平仓订单，true 使用 UserTrade::open，false 使用 UserTrade::closed。
     * - descendant_type=2 表示客户节点，订单列表只能查询当前大代理可见代理网络下的客户订单。
     *
     * @param Request $request 当前 HTTP 请求对象。
     * @param bool $open 是否查询未平仓订单。
     * @return \Illuminate\Http\JsonResponse
     */
    private function legacyOrderListResponse(Request $request, bool $open)
    {
        $this->ensureLegacyDefaultDateRange($request);

        $configuredAgentIds = $this->subAgentIdsForRequest($request, false);
        if (!$configuredAgentIds) {
            return response()->json([
                'rows' => [],
                'total' => 0,
                'footer' => [],
            ]);
        }

        // 旧页面的 userId 是代理筛选，不是订单客户 ID；先收窄代理网络，再解析其客户。
        $selectedAgentId = (int) $request->input('userId', 0);
        $scope = FrontLegacyData::strictAgentNetworkIdsOrNull(
            $configuredAgentIds,
            $selectedAgentId > 0 ? $selectedAgentId : null
        );
        if ($scope === null || $scope['agent_ids'] === []) {
            return response()->json(['rows' => [], 'total' => 0, 'footer' => []]);
        }
        if ($selectedAgentId > 0 && !in_array($selectedAgentId, $scope['agent_ids'], true)) {
            return response()->json(['rows' => [], 'total' => 0, 'footer' => []]);
        }
        $customerIds = $scope['customer_ids'];

        $query = UserTrade::query()
            ->whereIn('user_id', array_values(array_unique($customerIds)))
            ->whereIn('cmd', [0, 1, 2, 3, 4, 5])
            ->where('margin_rate', '<>', 0);
        if ($open) {
            $query->open();
            FrontLegacyData::applyDateTimeFilter($query, $request, 'open_time');
        } else {
            $query->closed();
            FrontLegacyData::applyDateTimeFilter($query, $request, 'close_time');
        }

        $orderUserId = (int) $request->input('orderUserId', $request->input('order_user_id', 0));
        if ($orderUserId > 0) {
            $query->where('user_id', $orderUserId);
        }
        FrontLegacyData::applySymbolFilter($query, $request);
        if ($request->filled('orderId')) {
            $query->where('ticket', $request->input('orderId'));
        }

        $total = (clone $query)->count();
        $footer = FrontLegacyData::tradeOrderTotalRow(clone $query);
        $rows = $query->orderBy($open ? 'open_time' : 'close_time', 'desc')
            ->forPage((int) $request->input('page', 1), FrontLegacyData::perPage($request))
            ->get()
            ->map(function (UserTrade $trade) {
                return FrontLegacyData::tradeAliasRow($trade);
            })
            ->values()
            ->all();

        return response()->json([
            'rows' => $rows,
            'total' => $total,
            'footer' => [$footer],
        ]);
    }
}
