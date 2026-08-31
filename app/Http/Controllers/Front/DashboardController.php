<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/28
 * Time: 00:50
 */

namespace App\Http\Controllers\Front;

use App\Models\UserInfo;
use App\Models\DepositRecord;
use App\Models\WithdrawRecord;
use App\Models\CommissionRecord;
use App\Models\SystemConfig;
use App\Models\UserTrade;
use App\Models\News;
use App\Services\FamilyTreeService;
use App\Constants\ResponseCode;
use App\Support\FrontLegacyData;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Validator;

/**
 * 前台仪表盘控制器。
 *
 * 文件功能：
 * - 处理前台首页 Blade 视图、账户摘要、代理层级统计、入金出金交易月度统计、新闻公告、旧前台热点新闻和礼品提示状态。
 * - 新版 Layui/Naive 首页通过 `GET /api/front/dashboard` 读取统一统计数据。
 * - 旧前台仍通过 `user/front/message`、`user/main/hot/news`、`user/main/hot/newsV2` 和 `user/main/hasShowGiftTips` 调用兼容入口。
 * - 新闻标题优先读取 news_langs 当前语言记录，缺失时回退 news 主表标题，保证后端接口支持多语言展示。
 *
 * 数据范围：
 * - 客户首页只统计本人账户数据；代理首页统计本人 + 全部直接/间接下级的聚合数据（scopeUserIds）。
 * - 返佣（commission_records）始终按当前用户自己的 agent_id 统计，不随可见范围放大。
 * - 交易订单统计按 MT4 open_time/close_time 计算；入金、出金按本地 created_at 时间戳计算。
 */
class DashboardController extends FrontBaseController
{
    /**
     * 代理层级统计服务实例。
     *
     * 参数含义：
     * - familyTreeService 表示代理层级统计服务，用于计算直属代理、间接代理、直属客户和间接客户数量。
     *
     * @var FamilyTreeService
     */
    protected $familyTreeService;

    /**
     * 构造前台仪表盘控制器。
     *
     * @param FamilyTreeService $familyTreeService 代理层级统计服务，用于生成首页代理网络统计。
     */
    public function __construct(FamilyTreeService $familyTreeService)
    {
        $this->familyTreeService = $familyTreeService;
    }

    /**
     * index 用于渲染前台 Layui 仪表盘 Blade 页面。
     *
     * 逻辑说明：
     * - 页面只负责输出 Blade 外壳和前端容器，真实统计数据由 dashboardData 接口返回。
     *
     * @return \Illuminate\View\View 前台 Layui 仪表盘页面。
     */
    public function index()
    {
        // 前台登录后首页统一使用 Layui Blade 模板，避免旧视图和新视图入口分裂。
        return view('front_layui::dashboard.index_v2');
    }

    /**
     * dashboardData 用于返回当前前台用户首页账户摘要数据。
     *
     * 参数含义：
     * - $request 表示当前 HTTP 请求对象，user guard 中的 userLogin 表示 user_logins 登录账号。
     * - userInfo 表示 user_infos 业务资料，提供 user_id、account_type、资金字段和返佣比例。
     * - periodDays 表示前端选择的 7、15 或 30 天统计周期，默认 30 天。
     * - periodStart 表示当前统计周期起点的 Unix 时间戳；沿用月度口径的历史别名描述时，monthStart 表示最近 30 天统计窗口的 Unix 时间戳。
     * - scopeUserIds 表示当前首页统计允许聚合的业务用户 ID 列表；代理包含自己和所有后代，客户只包含自己。
     * - descendantIds 表示当前代理名下所有后代用户 ID，用于代理首页聚合入金、出金和交易数据。
     * - monthlyDeposits 表示最近 30 天入金金额汇总，实际窗口跟随 periodDays 变化，数据来源 deposit_records.amount。
     * - monthlyWithdraws 表示最近 30 天出金申请金额汇总，实际窗口跟随 periodDays 变化，数据来源 withdraw_records.apply_amount。
     * - monthlyOpenOrders 表示最近 30 天新开仓订单数量，实际窗口跟随 periodDays 变化，数据来源 mt4 交易重建表 open_time。
     * - monthlyClosedOrders 表示最近 30 天平仓订单数量，实际窗口跟随 periodDays 变化，数据来源 mt4 交易重建表 close_time。
     * - news 表示首页展示的最新公告列表，标题和内容优先从 news_langs 当前语言记录读取。
     * - share_urls 表示代理专属注册链接集合，客户账号返回空数组。
     * - downloads 表示 PC 和移动端下载地址配置，来源 system_configs 新旧候选键。
     *
     * @param Request $request HTTP 请求对象，承载当前前台登录用户和 X-Locale 语言头。
     * @return JsonResponse 首页账户摘要、统计卡片、下载配置、新闻公告和统计周期响应。
     */
    public function dashboardData(Request $request): JsonResponse
    {
        $userLogin = $request->user('user');
        if (!$userLogin) {
            return $this->error('auth.unauthorized', ResponseCode::INVALID_CREDENTIALS);
        }

        $userInfo = $this->legacyFrontUserInfo($request);
        if (!$userInfo) {
            return $this->error('auth.user_info_not_found', ResponseCode::INTERNAL_ERROR);
        }

        $validator = Validator::make($request->only('days'), [
            'days' => 'sometimes|integer|in:7,15,30',
        ]);
        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), ResponseCode::VALIDATION_FAILED);
        }

        $userId = (int) $userInfo->user_id;
        $isAgent = $userInfo->isAgent();
        $periodDays = (int) $request->input('days', 30);
        $periodStart = time() - $periodDays * 86400;
        $periodStartDateTime = date('Y-m-d H:i:s', $periodStart);

        // 代理首页需要看自己网络下所有直接/间接下属的数据；客户首页只看自己的数据。
        // scopeUserIds 用于充值、出金、交易聚合，commission 仍然按 agent_id 统计代理本人返佣。
        $descendantIds = [];
        if ($isAgent) {
            $descendantIds = FrontLegacyData::userScopeIds($userId, false);
        }
        $scopeUserIds = array_values(array_unique(array_merge([$userId], $descendantIds)));

        $hierarchyStats = $isAgent
            ? $this->familyTreeService->getSubAgentStats($userId)
            : [
                'direct_agents' => 0,
                'indirect_agents' => 0,
                'total_agents' => 0,
                'direct_customers' => 0,
                'indirect_customers' => 0,
                'total_customers' => 0,
            ];

        $totalCommission = $isAgent
            ? (float) CommissionRecord::where('agent_id', $userId)->sum('commission_amount')
            : 0.0;
        $monthCommission = $isAgent
            ? (float) CommissionRecord::where('agent_id', $userId)->where('created_at', '>=', $periodStart)->sum('commission_amount')
            : 0.0;

        $monthlyDeposits = DepositRecord::whereIn('user_id', $scopeUserIds)
            ->where('created_at', '>=', $periodStart)
            ->sum('amount');
        $monthlyWithdraws = WithdrawRecord::whereIn('user_id', $scopeUserIds)
            ->where('created_at', '>=', $periodStart)
            ->sum('apply_amount');

        // 交易记录保留 MT4 open_time/close_time：首页周期订单统计按真实交易时间计算；
        // 入金和出金属于本地业务记录，继续按本地 created_at 时间戳计算。
        $monthlyOpenOrders = UserTrade::whereIn('user_id', $scopeUserIds)
            ->where('open_time', '>=', $periodStartDateTime)
            ->open()
            ->count();
        $monthlyClosedOrders = UserTrade::whereIn('user_id', $scopeUserIds)
            ->where('close_time', '>=', $periodStartDateTime)
            ->closed()
            ->count();
        $currentOpenOrders = UserTrade::whereIn('user_id', $scopeUserIds)
            ->open()
            ->count();
        $locale = $request->header('X-Locale', app()->getLocale());
        $news = News::published()
            ->orderByDesc('created_at')
            ->limit(5)
            ->get(['id', 'title', 'content', 'author_name', 'created_at'])
            ->map(function (News $item) use ($locale) {
                $lang = DB::table('news_langs')
                    ->where('news_id', $item->id)
                    ->where('lang_code', $locale)
                    ->whereNull('deleted_at')
                    ->first();
                $createdAt = $item->created_at;

                return [
                    'id' => $item->id,
                    'title' => $lang && $lang->title ? $lang->title : $item->title,
                    'content' => $lang && $lang->content ? $lang->content : $item->content,
                    'author_name' => $item->author_name,
                    'created_at' => is_numeric($createdAt) ? date('Y-m-d H:i:s', (int) $createdAt) : (string) $createdAt,
                ];
            })
            ->values();

        $data = [
            'user' => [
                'user_id'      => $userInfo->user_id,
                'user_name'    => $userInfo->user_name,
                'account_type' => $userInfo->account_type,
                'email'        => $userLogin->email,
                'title'        => $isAgent ? __('front.vip_agent') : __('front.vip_customer'),
            ],
            'profile' => [
                'share_url'       => $isAgent ? route('front_page_register', ['inviter_id' => $userId]) : '',
                'share_urls'      => $this->registerShareUrls($userId, $isAgent),
                'commission_rate' => (float) $userInfo->comm_rate,
                'total_funds'     => (float) $userInfo->total_funds,
                'equity'          => (float) $userInfo->equity,
                'effective_credit'=> (float) $userInfo->effective_credit,
            ],
            'downloads' => [
                'pc' => [
                    'label' => __('front.pc_download'),
                    'url'   => $this->configValue(['download_pc_url', 'pc_download_url', 'client_pc_download_url'], '#'),
                ],
                'mobile' => [
                    'label' => __('front.mobile_download'),
                    'url'   => $this->configValue(['download_mobile_url', 'mobile_download_url', 'app_download_url'], '#'),
                ],
            ],
            'stats' => [
                'direct_agents'       => (int) $hierarchyStats['direct_agents'],
                'indirect_agents'     => (int) $hierarchyStats['indirect_agents'],
                'total_agents'        => (int) $hierarchyStats['total_agents'],
                'direct_customers'    => (int) $hierarchyStats['direct_customers'],
                'indirect_customers'  => (int) $hierarchyStats['indirect_customers'],
                'total_customers'     => (int) $hierarchyStats['total_customers'],
                'total_commission'    => $totalCommission,
                'account_balance'     => (float) $userInfo->total_funds,
                'monthly_deposit'     => (float) $monthlyDeposits,
                'monthly_withdraw'    => (float) $monthlyWithdraws,
                'monthly_open_orders' => (int) $monthlyOpenOrders,
                'monthly_closed_orders' => (int) $monthlyClosedOrders,
                'open_orders_count' => (int) $currentOpenOrders,
                'monthly_commission'  => $monthCommission,
            ],
            'news' => $news,
            'period' => [
                'days' => $periodDays,
                'from' => date('Y-m-d', $periodStart),
                'to'   => date('Y-m-d'),
            ],
            // 日粒度趋势序列：供首页趋势图（出入金/订单/盈亏/返佣）消费，零填充保证 X 轴日期连续。
            'series' => $this->dailySeries($scopeUserIds, $userId, $isAgent, $periodDays),
        ];

        return $this->success($data, 'response.query_success');
    }

    /**
     * frontMsg 用于兼容旧前台消息面板入口。
     *
     * 参数含义：
     * - $request 表示当前 HTTP 请求对象；旧页面只需要一个可渲染的消息面板占位 HTML。
     *
     * @param Request $request HTTP 请求对象。
     * @return \Illuminate\Http\Response 空消息面板 HTML 响应。
     */
    public function frontMsg(Request $request)
    {
        return response('<div class="front-message-panel"></div>');
    }

    /**
     * hotNews 用于兼容旧前台首页热点新闻 HTML 列表接口。
     *
     * 参数含义：
     * - page 表示旧前台请求的页码，最小为 1。
     * - X-Locale 表示当前语言，决定新闻标题优先读取哪条 news_langs 记录。
     *
     * @param Request $request HTTP 请求对象，承载 page 和 X-Locale。
     * @return JsonResponse 旧前台 code/msg/page/count/dataHtml 结构响应。
     */
    public function hotNews(Request $request): JsonResponse
    {
        $validator = Validator::make($request->only('page'), [
            'page' => 'sometimes|integer|min:1',
        ]);
        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), ResponseCode::VALIDATION_FAILED);
        }

        $page = (int) $request->input('page', 1);
        $limit = 4;
        $locale = $request->header('X-Locale', app()->getLocale());
        $query = News::published()->orderByDesc('created_at');
        $total = (clone $query)->count();
        $items = $query->forPage($page, $limit)->get();
        $html = $items->map(function (News $item) use ($locale) {
            $title = $this->localizedNewsTitle($item, $locale);
            $url = route('legacy_user_news_detail', ['newsId' => (int) $item->id]);
            return '<li style="line-height:20px;"><a href="' . e($url) . '" target="_blank">' . e($title) . '</a></li>';
        })->implode('');

        return response()->json([
            'code' => 0,
            'msg' => 'success',
            'page' => $page,
            'count' => $total,
            'dataHtml' => $html,
        ]);
    }

    /**
     * hotNewsV2 用于兼容旧前台注册页热点新闻表格接口。
     *
     * 参数含义：
     * - page 表示当前页码，最小为 1。
     * - 每页数量固定为旧项目的 10 条，避免旧 Layui 表格分页口径变化。
     * - lang_id=1 表示中文并返回 lang_name=zh-cn，lang_id=2 表示英文并返回 lang_name=en。
     *
     * @param Request $request HTTP 请求对象，承载 page 和 lang_id。
     * @return JsonResponse 旧前台表格结构响应，data 为新闻行数组，totalRow 保留为空数组。
     */
    public function hotNewsV2(Request $request): JsonResponse
    {
        $validator = Validator::make($request->only(['page', 'lang_id']), [
            'page' => 'sometimes|integer|min:1',
            'lang_id' => 'sometimes|integer|in:1,2',
        ]);
        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), ResponseCode::VALIDATION_FAILED);
        }

        $page = (int) $request->input('page', 1);
        $limit = 10;
        $langId = (int) $request->input('lang_id', 1);
        $locale = $langId === 1 ? 'zh-CN' : 'en';
        $legacyLangName = $langId === 1 ? 'zh-cn' : 'en';
        $query = News::published()->orderByDesc('created_at');
        $total = (clone $query)->count();
        $rows = $query->forPage($page, $limit)->get()->map(function (News $item) use ($locale, $legacyLangName) {
            $title = $this->localizedNewsTitle($item, $locale);
            if (mb_strlen($title, 'UTF-8') > 40) {
                $title = mb_substr($title, 0, 40, 'UTF-8') . '...';
            }
            $url = route('legacy_user_news_detail', ['newsId' => (int) $item->id]);

            return [
                'title' => $title,
                'link_url' => '<span><a href="' . e($url) . '" target="_blank">' . e($title) . '</a></span>',
                'lang_name' => $legacyLangName,
                'aid' => (int) $item->id,
                'update_time' => is_numeric($item->updated_at) ? date('Y-m-d H:i:s', (int) $item->updated_at) : (string) $item->updated_at,
            ];
        })->values();

        return response()->json([
            'code' => 200,
            'msg' => 'Request data successful.',
            'count' => $total,
            'data' => $rows,
            'totalRow' => [],
        ]);
    }

    /**
     * 返回旧注册页使用的公开热点新闻原始数组契约。
     */
    public function registerHotNews(Request $request): JsonResponse
    {
        $validator = Validator::make($request->only(['page', 'limit', 'lang_id']), [
            'page' => 'sometimes|integer|min:1',
            'limit' => 'sometimes|integer|between:1,50',
            'lang_id' => 'sometimes|integer|in:1,2',
        ]);
        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), ResponseCode::VALIDATION_FAILED);
        }

        $page = (int) $request->input('page', 1);
        $limit = (int) $request->input('limit', 4);
        $langId = (int) $request->input('lang_id', 1);
        $locale = $langId === 1 ? 'zh-CN' : 'en';
        $rows = News::published()
            ->orderByDesc('created_at')
            ->forPage($page, $limit)
            ->get()
            ->map(function (News $item) use ($locale) {
                return [
                    'aid' => (int) $item->id,
                    'title' => $this->localizedNewsTitle($item, $locale),
                    'update_time' => is_numeric($item->updated_at) ? date('Y-m-d H:i:s', (int) $item->updated_at) : (string) $item->updated_at,
                ];
            })
            ->values()
            ->all();

        return response()->json($rows);
    }

    /**
     * hasShowGiftTips 用于记录当前用户已查看礼品提示。
     *
     * 参数含义：
     * - $request 表示当前 HTTP 请求对象，user guard 中的用户 ID 用于生成 gift_tips_shown_{user_id} 缓存键。
     *
     * @param Request $request HTTP 请求对象。
     * @return JsonResponse 旧前台成功结构响应。
     */
    public function hasShowGiftTips(Request $request): JsonResponse
    {
        $userId = $this->legacyFrontUserId($request);
        Cache::forever('gift_tips_shown_' . $userId, 1);

        return response()->json([
            'code' => ResponseCode::SUCCESS,
            'msg' => 'SUCCESS',
            'data' => [],
        ]);
    }

    /**
     * localizedNewsTitle 用于按当前语言读取新闻标题。
     *
     * 参数含义：
     * - $item 表示 news 主表记录。
     * - $locale 表示当前语言标识，例如 zh-CN 或 en。
     *
     * @param News $item 新闻主表记录。
     * @param string $locale 当前语言标识。
     * @return string 多语言标题；语言记录缺失时返回 news.title。
     */
    private function localizedNewsTitle(News $item, string $locale): string
    {
        $lang = DB::table('news_langs')
            ->where('news_id', $item->id)
            ->where('lang_code', $locale)
            ->whereNull('deleted_at')
            ->first();

        return $lang && $lang->title ? $lang->title : (string) $item->title;
    }

    /**
     * configValue 用于从新旧系统配置键中读取第一个有效值。
     *
     * 参数含义：
     * - $keys 表示按优先级排列的新旧配置键名，例如 download_pc_url、pc_download_url。
     * - $default 表示所有候选键都为空或无效时返回的默认值。
     *
     * @param array<int, string> $keys 系统配置候选键列表。
     * @param mixed $default 默认返回值。
     * @return mixed 第一个有效配置值或默认值。
     */
    private function configValue(array $keys, $default = '')
    {
        foreach ($keys as $key) {
            $value = SystemConfig::getVal($key, null);
            if ($value !== null && $value !== '') {
                $value = trim((string) $value);
                if (!$this->isObsoleteVersionProbe($value)) {
                    return $value;
                }
            }
        }

        return $default;
    }

    /**
     * isObsoleteVersionProbe 用于过滤旧版本探测地址。
     *
     * 参数含义：
     * - $value 表示系统配置中保存的下载地址候选值。
     *
     * @param string $value 下载地址候选值。
     * @return bool true=旧版本探测地址或空值，不应作为下载地址；false=可用配置值。
     */
    private function isObsoleteVersionProbe(string $value): bool
    {
        $normalized = strtolower(trim($value));

        return $normalized === ''
            || strpos($normalized, 'xapi.yhchj.com/version') !== false
            || preg_match('#/version(?:[/?\#].*)?$#', $normalized) === 1;
    }

    /**
     * registerShareUrls 用于生成代理邀请注册链接集合。
     *
     * 参数含义：
     * - $userId 表示当前前台业务用户 ID，用作注册页 inviter_id。
     * - $isAgent 表示当前用户是否为代理；只有代理账号才返回邀请注册链接。
     *
     * @param int $userId 当前业务用户 ID。
     * @param bool $isAgent true=代理账号，false=普通客户账号。
     * @return array<int, array{label_key: string, url: string}> 注册入口多语言 key 与 URL 列表。
     */
    private function registerShareUrls(int $userId, bool $isAgent): array
    {
        if (!$isAgent) {
            return [];
        }

        $base = route('front_page_register', ['inviter_id' => $userId]);

        return [
            [
                'label_key' => 'front.register_agent',
                'url' => $base . '?account_type=1',
            ],
            [
                'label_key' => 'front.register_agent_zero',
                'url' => $base . '?account_type=1&commission_mode=A',
            ],
            [
                'label_key' => 'front.register_member',
                'url' => $base . '?account_type=2',
            ],
            [
                'label_key' => 'front.register_member_zero',
                'url' => $base . '?account_type=2&commission_mode=A',
            ],
        ];
    }

    /**
     * dailySeries 用于生成首页趋势图所需的日粒度真实统计序列。
     *
     * 逻辑说明：
     * - 日期轴由 PHP 以本地时区自今日零点向前推 N 天生成，并对全部指标零填充，保证无数据日仍占位、X 轴连续。
     * - 入金/出金/返佣的时间列是 Unix 时间戳，按「(时间戳 - 今日零点) / 86400 下取整」在 SQL 内做整数分桶；
     *   该算法不依赖数据库会话时区，避免 FROM_UNIXTIME 在 MySQL 时区与 PHP 时区不一致时把行错分到相邻日。
     * - 订单/盈亏的 open_time、close_time 是 DATETIME 墙钟字符串，与 PHP 日期同轴，故用 DATEDIFF 分桶。
     * - 聚合口径与首页汇总指标严格一致：资金与订单按可见用户范围，返佣仅按当前代理本人。
     *
     * @param array $scopeUserIds 可见用户 ID 集合，用于资金与订单聚合。
     * @param int   $userId       当前用户 ID，用于返佣聚合。
     * @param bool  $isAgent      是否代理账户，非代理不统计返佣。
     * @param int   $periodDays   统计天数，仅允许 7/15/30。
     * @return array 含 dates 与六个等长指标数组的序列结构。
     */
    private function dailySeries(array $scopeUserIds, int $userId, bool $isAgent, int $periodDays): array
    {
        $todayMidnight = strtotime('today');
        $dates = [];
        $zero = [];

        // 日期轴：索引 0 为最早一天，末位为今天。
        for ($offset = $periodDays - 1; $offset >= 0; $offset--) {
            $dates[] = date('Y-m-d', $todayMidnight - $offset * 86400);
            $zero[] = 0;
        }

        $buckets = [
            'deposit' => $zero,
            'withdraw' => $zero,
            'commission' => $zero,
            'open_orders' => $zero,
            'closed_orders' => $zero,
            'profit' => $zero,
        ];

        $lastIndex = $periodDays - 1;
        // SQL 返回的 day_offset 为「距今天的天数差」：0=今天，-1=昨天。映射为日期轴下标。
        $put = function (array &$target, $dayOffset, $value) use ($lastIndex) {
            $index = $lastIndex + (int) $dayOffset;
            if ($index >= 0 && $index <= $lastIndex) {
                $target[$index] = $value;
            }
        };

        // created_at 为 BIGINT UNSIGNED：必须先 CAST 为 SIGNED，否则早于今日零点的行会触发无符号下溢（SQLSTATE 22003）。
        $unixOffset = 'FLOOR((CAST(created_at AS SIGNED) - ' . $todayMidnight . ') / 86400) as day_offset';
        $periodStart = $todayMidnight - $lastIndex * 86400;
        $periodStartDateTime = date('Y-m-d H:i:s', $periodStart);

        $deposits = DepositRecord::whereIn('user_id', $scopeUserIds)
            ->where('created_at', '>=', $periodStart)
            ->selectRaw($unixOffset)
            ->selectRaw('SUM(amount) as total')
            ->groupBy('day_offset')
            ->get();
        foreach ($deposits as $row) {
            $put($buckets['deposit'], $row->day_offset, (float) $row->total);
        }

        $withdraws = WithdrawRecord::whereIn('user_id', $scopeUserIds)
            ->where('created_at', '>=', $periodStart)
            ->selectRaw($unixOffset)
            ->selectRaw('SUM(apply_amount) as total')
            ->groupBy('day_offset')
            ->get();
        foreach ($withdraws as $row) {
            $put($buckets['withdraw'], $row->day_offset, (float) $row->total);
        }

        if ($isAgent) {
            $commissions = CommissionRecord::where('agent_id', $userId)
                ->where('created_at', '>=', $periodStart)
                ->selectRaw($unixOffset)
                ->selectRaw('SUM(commission_amount) as total')
                ->groupBy('day_offset')
                ->get();
            foreach ($commissions as $row) {
                $put($buckets['commission'], $row->day_offset, (float) $row->total);
            }
        }

        $openRows = UserTrade::whereIn('user_id', $scopeUserIds)
            ->where('open_time', '>=', $periodStartDateTime)
            ->selectRaw('DATEDIFF(open_time, CURDATE()) as day_offset')
            ->selectRaw('COUNT(*) as total')
            ->groupBy('day_offset')
            ->get();
        foreach ($openRows as $row) {
            $put($buckets['open_orders'], $row->day_offset, (int) $row->total);
        }

        $closedRows = UserTrade::whereIn('user_id', $scopeUserIds)
            ->where('close_time', '>=', $periodStartDateTime)
            ->closed()
            ->selectRaw('DATEDIFF(close_time, CURDATE()) as day_offset')
            ->selectRaw('COUNT(*) as total')
            ->selectRaw('SUM(profit) as profit_total')
            ->groupBy('day_offset')
            ->get();
        foreach ($closedRows as $row) {
            $put($buckets['closed_orders'], $row->day_offset, (int) $row->total);
            $put($buckets['profit'], $row->day_offset, (float) $row->profit_total);
        }

        return array_merge(['dates' => $dates], $buckets);
    }

}
