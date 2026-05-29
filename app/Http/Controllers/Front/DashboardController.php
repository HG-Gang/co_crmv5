<?php

namespace App\Http\Controllers\Front;

use App\Models\UserInfo;
use App\Models\DepositRecord;
use App\Models\WithdrawRecord;
use App\Models\AgentDescendant;
use App\Models\CommissionRecord;
use App\Models\SystemConfig;
use App\Models\UserTrade;
use App\Models\News;
use App\Services\FamilyTreeService;
use App\Constants\ResponseCode;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

/**
 * Front Dashboard Controller
 * 前台仪表盘控制器
 * 
 * Provides dashboard views and account summary data.
 * 提供仪表盘视图和账户摘要数据。
 */
class DashboardController extends FrontBaseController
{
    protected $familyTreeService;

    public function __construct(FamilyTreeService $familyTreeService)
    {
        $this->familyTreeService = $familyTreeService;
    }

    /**
     * Dashboard view
     * 仪表盘视图
     * 
     * @return \Illuminate\View\View
     */
    public function index()
    {
        // 第六次需求统一前台登录后首页为 Layui Blade 模板。
        return view('front_layui::dashboard.index');
    }

    /**
     * Account summary data
     * 账户摘要数据
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function dashboardData(Request $request): JsonResponse
    {
        $userLogin = $request->user('user');
        if (!$userLogin) {
            return $this->error('auth.unauthorized', ResponseCode::INVALID_CREDENTIALS);
        }

        $userInfo = $userLogin->userInfo;
        if (!$userInfo) {
            return $this->error('auth.user_info_not_found', ResponseCode::INTERNAL_ERROR);
        }

        $userId = (int) $userInfo->user_id;
        $isAgent = $userInfo->isAgent();
        $monthStart = time() - 30 * 86400;
        $monthStartDateTime = date('Y-m-d H:i:s', $monthStart);

        // 代理首页需要看自己网络下所有直接/间接下属的数据；客户首页只看自己的数据。
        // scopeUserIds 用于充值、出金、交易聚合，commission 仍然按 agent_id 统计代理本人返佣。
        $descendantIds = [];
        if ($isAgent) {
            $descendantIds = AgentDescendant::where('agent_id', $userId)->pluck('descendant_id')->toArray();
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
            ? (float) CommissionRecord::where('agent_id', $userId)->where('created_at', '>=', $monthStart)->sum('commission_amount')
            : 0.0;

        $monthlyDeposits = DepositRecord::whereIn('user_id', $scopeUserIds)
            ->where('created_at', '>=', $monthStart)
            ->sum('amount');
        $monthlyWithdraws = WithdrawRecord::whereIn('user_id', $scopeUserIds)
            ->where('created_at', '>=', $monthStart)
            ->sum('apply_amount');

        // Trading records preserve MT4 open_time/close_time.  The "last month"
        // order widgets therefore use trade times, while deposits/withdraws use
        // the local record created_at timestamp.
        $monthlyOpenOrders = UserTrade::whereIn('user_id', $scopeUserIds)
            ->where('open_time', '>=', $monthStartDateTime)
            ->where('close_time', '1970-01-01 00:00:00')
            ->count();
        $monthlyClosedOrders = UserTrade::whereIn('user_id', $scopeUserIds)
            ->where('close_time', '>=', $monthStartDateTime)
            ->where('close_time', '>', '1970-01-01 00:00:00')
            ->count();
        $currentOpenOrders = UserTrade::whereIn('user_id', $scopeUserIds)
            ->where('close_time', '1970-01-01 00:00:00')
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
                'share_url'       => $isAgent ? url('/front/register/' . $userId) : '',
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
                'from' => date('Y-m-d', $monthStart),
                'to'   => date('Y-m-d'),
            ],
        ];

        return $this->success($data, 'response.query_success');
    }

    /**
     * Get first configured value from possible old/new keys.
     * 老项目配置键名不完全统一，这里按新旧候选键顺序取第一个有效值，缺失时返回默认值。
     *
     * @param array $keys
     * @param mixed $default
     * @return mixed
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

    private function isObsoleteVersionProbe(string $value): bool
    {
        $normalized = strtolower(trim($value));

        return $normalized === ''
            || strpos($normalized, 'xapi.yhchj.com/version') !== false
            || preg_match('#/version(?:[/?\#].*)?$#', $normalized) === 1;
    }

    private function registerShareUrls(int $userId, bool $isAgent): array
    {
        if (!$isAgent) {
            return [];
        }

        $base = url('/front/register/' . $userId);

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

}
