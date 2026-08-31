<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/16
 * Time: 01:32
 */

namespace App\Services;

use App\Models\Admin;
use App\Models\SymbolPrice;
use App\Models\UserInfo;
use App\Models\UserTrade;

/**
 * 旧后台客户搜索服务。
 *
 * 文件功能：
 * - 对齐旧项目客户列表搜索：按注册时间段、用户名、用户 ID/身份证号与实名状态筛选
 *   account_type=2 的普通客户，输出 rows/total/summary 结构。
 * - 搜索结果强制叠加 AdminDataScopeService 数据范围，并按 UserStatisticsService 统一口径
 *   补齐交易/资金统计列与分类手数汇总；directCustomers() 复用同一口径输出直属客户页。
 * - 明确不负责：写操作与导出文件生成。
 */
class LegacyAdminCustomerSearchService
{
    /**
     * 后台数据范围服务：客户搜索结果必须限定在管理员的可见代理树内，
     * 缺失时旧后台搜索会把任意客户资料（含实名、资金字段）暴露给越权管理员。
     *
     * @var AdminDataScopeService
     */
    private $adminDataScopeService;

    /**
     * 用户交易统计服务：为搜索结果行补齐旧后台所需的交易/资金统计列，口径全项目唯一；
     * 缺失时各行统计无法填充或需要控制器重复实现，两处口径漂移会直接造成报表数字不一致。
     *
     * @var UserStatisticsService
     */
    private $userStatisticsService;

    public function __construct(
        AdminDataScopeService $adminDataScopeService,
        UserStatisticsService $userStatisticsService
    ) {
        $this->adminDataScopeService = $adminDataScopeService;
        $this->userStatisticsService = $userStatisticsService;
    }

    /**
     * @param array<string, mixed> $payload
     * @return array{rows: array<int, array<string, mixed>>, total: int, summary: array<string, mixed>}
     */
    public function search(array $payload, Admin $admin): array
    {
        $page = max(1, (int) ($payload['page'] ?? 1));
        $perPage = (int) ($payload['rows'] ?? ($payload['limit'] ?? 15));
        $perPage = max(1, min(100, $perPage));
        $startDate = trim((string) ($payload['start_date'] ?? ''));
        $startDate = $startDate === '' ? '2024-01-01' : $startDate;
        $endDate = trim((string) ($payload['end_date'] ?? ''));
        $endDate = $endDate === '' ? date('Y-m-d') : $endDate;

        $query = UserInfo::query()
            ->with('auth')
            ->where('user_infos.account_type', 2)
            ->whereBetween('user_infos.created_at', [
                strtotime($startDate . ' 00:00:00'),
                strtotime($endDate . ' 23:59:59'),
            ]);

        if (isset($payload['user_name']) && trim((string) $payload['user_name']) !== '') {
            $query->where('user_infos.user_name', 'like', '%' . trim((string) $payload['user_name']) . '%');
        }

        if (isset($payload['user_id']) && trim((string) $payload['user_id']) !== '') {
            $userId = trim((string) $payload['user_id']);
            $query->where(function ($filter) use ($userId): void {
                $filter->where('user_infos.user_id', 'like', '%' . $userId . '%')
                    ->orWhereHas('auth', function ($authQuery) use ($userId): void {
                        $authQuery->where('user_auths.id_card_no', 'like', '%' . $userId . '%');
                    });
            });
        }

        if (array_key_exists('userstatus', $payload) && $payload['userstatus'] !== null
            && $payload['userstatus'] !== '') {
            $status = (int) $payload['userstatus'];
            $query->where('user_infos.auth_status', $status === 4 ? 3 : $status);
        }

        $this->adminDataScopeService->apply($query, $admin, 'user', 'user_infos.user_id');

        $allUserIds = (clone $query)
            ->pluck('user_infos.user_id')
            ->map(static function ($userId): int {
                return (int) $userId;
            })
            ->all();
        $users = $query
            ->orderByDesc('user_infos.created_at')
            ->orderByDesc('user_infos.user_id')
            ->forPage($page, $perPage)
            ->get();
        $pageUserIds = $users->pluck('user_id')->map(static function ($userId): int {
            return (int) $userId;
        })->all();
        $statistics = $this->userStatisticsService->getBatchUserStatistics($pageUserIds, null, null);
        $categoryStatistics = $this->categoryStatistics($allUserIds);
        $rows = $this->rowsForUsers($users, $statistics, $categoryStatistics);

        $summary = empty($allUserIds)
            ? self::emptySummary()
            : self::normalizeSummary(
                $this->userStatisticsService->getSummaryStatistics($allUserIds, null, null),
                self::summarizeCategories($categoryStatistics)
            );

        return [
            'rows' => $rows,
            'total' => count($allUserIds),
            'summary' => $summary,
        ];
    }

    /**
     * Return the old AgentControllerV3 direct-customer page data for one agent.
     *
     * The old page intentionally has no date or pagination filter: it displays
     * every active ordinary customer whose direct parent is the requested agent.
     * The same admin scope and local trade statistics as the customer list apply.
     *
     * @return array{rows: array<int, array<string, mixed>>, total: int, summary: array<string, mixed>}
     */
    public function directCustomers(int $parentId, Admin $admin): array
    {
        $query = UserInfo::query()
            ->with('auth')
            ->where('user_infos.account_type', 2)
            ->where('user_infos.parent_id', $parentId);

        $this->adminDataScopeService->apply($query, $admin, 'user', 'user_infos.user_id');

        $users = $query
            ->orderByDesc('user_infos.created_at')
            ->orderByDesc('user_infos.user_id')
            ->get();
        $userIds = $users->pluck('user_id')->map(static function ($userId): int {
            return (int) $userId;
        })->all();
        $statistics = $this->userStatisticsService->getBatchUserStatistics($userIds, null, null);
        $categoryStatistics = $this->categoryStatistics($userIds);

        return [
            'rows' => $this->rowsForUsers($users, $statistics, $categoryStatistics),
            'total' => count($userIds),
            'summary' => empty($userIds)
                ? self::emptySummary()
                : self::normalizeSummary(
                    $this->userStatisticsService->getSummaryStatistics($userIds, null, null),
                    self::summarizeCategories($categoryStatistics)
                ),
        ];
    }

    /**
     * @param \Illuminate\Support\Collection<int, UserInfo> $users
     * @param array<int, array<string, mixed>> $statistics
     * @param array<int, array<string, float>> $categoryStatistics
     * @return array<int, array<string, mixed>>
     */
    private function rowsForUsers($users, array $statistics, array $categoryStatistics): array
    {
        return $users
            ->map(static function (UserInfo $user) use ($statistics, $categoryStatistics): array {
                $createdAt = (int) $user->getRawOriginal('created_at');
                $userId = (int) $user->user_id;

                $base = [
                    'user_id' => $userId,
                    'user_name' => (string) $user->user_name,
                    'parent_id' => (int) $user->parent_id,
                    'trans_mode' => (int) $user->trading_mode,
                    'mt4_code' => (int) $user->mt4_code,
                    'user_money' => self::money($user->total_funds),
                    'cust_eqy' => self::money($user->equity),
                    'mt4_grp' => (string) $user->mt4_group,
                    'user_status' => (int) $user->auth_status,
                    'voided' => (int) $user->is_mt4_synced === 1 ? 1 : 2,
                    'IDcard_status' => (int) optional($user->auth)->id_card_status,
                    'bank_status' => (int) optional($user->auth)->bank_status,
                    'rec_crt_date' => $createdAt > 0 ? date('Y-m-d H:i:s', $createdAt) : '',
                    'mt4_login' => (int) $user->user_id,
                    'mt4_name' => (string) $user->user_name,
                    'mt4_balance' => self::money($user->total_funds),
                    'mt4_equity' => self::money($user->equity),
                    'mt4MarginLevel' => self::money($user->risk_ratio),
                ];

                return array_merge(
                    $base,
                    self::normalizeRowStatistics(array_merge(
                        $statistics[$userId] ?? [],
                        $categoryStatistics[$userId] ?? []
                    ))
                );
            })
            ->values()
            ->all();
    }

    /** @param array<string, mixed> $statistics */
    private static function normalizeRowStatistics(array $statistics): array
    {
        return [
            'total_comm' => self::money($statistics['total_comm'] ?? 0),
            'total_yuerj' => self::money($statistics['total_yuerj'] ?? 0),
            'total_yuecj' => self::negativeMoney($statistics['total_yuecj'] ?? 0),
            'total_volume' => (float) ($statistics['total_volume'] ?? 0),
            'total_swaps' => self::money($statistics['total_swaps'] ?? 0),
            'total_profit' => self::money($statistics['total_profit'] ?? 0),
            'total_noble_metal' => (float) ($statistics['total_noble_metal'] ?? 0),
            'total_for_exca' => (float) ($statistics['total_for_exca'] ?? 0),
            'total_crud_oil' => (float) ($statistics['total_crud_oil'] ?? 0),
            'total_index' => (float) ($statistics['total_index'] ?? 0),
            'total_currency' => (float) ($statistics['total_currency'] ?? 0),
            'total_stock' => (float) ($statistics['total_stock'] ?? 0),
            'total_net_worth' => self::money($statistics['total_net_worth'] ?? 0),
        ];
    }

    /**
     * @param array<string, mixed> $statistics
     * @param array<string, float> $categories
     */
    private static function normalizeSummary(array $statistics, array $categories): array
    {
        $summary = self::emptySummary();
        $summary['mt4_balance'] = self::money($statistics['search_total_bal'] ?? 0);
        $summary['mt4_equity'] = self::money($statistics['search_total_eqy'] ?? 0);
        $summary['total_yuerj'] = self::money($statistics['search_total_yuerj'] ?? 0);
        $summary['total_yuecj'] = self::negativeMoney($statistics['search_total_yuecj'] ?? 0);
        $summary['total_net_worth'] = self::money($statistics['search_total_net_worth'] ?? 0);
        $summary['total_comm'] = self::money($statistics['search_total_comm'] ?? 0);
        $summary['total_profit'] = self::money($statistics['search_total_profit'] ?? 0);
        $summary['total_noble_metal'] = $categories['total_noble_metal'];
        $summary['total_for_exca'] = $categories['total_for_exca'];
        $summary['total_crud_oil'] = $categories['total_crud_oil'];
        $summary['total_index'] = $categories['total_index'];
        $summary['total_currency'] = $categories['total_currency'];
        $summary['total_stock'] = $categories['total_stock'];
        $summary['total_volume'] = (float) ($statistics['search_total_volume'] ?? 0);
        $summary['total_swaps'] = self::money($statistics['search_total_swaps'] ?? 0);

        return $summary;
    }

    /** @return array<int, array<string, float>> */
    private function categoryStatistics(array $userIds): array
    {
        if (empty($userIds)) {
            return [];
        }

        $volumes = UserTrade::query()
            ->selectRaw('user_id, symbol, SUM(volume) as total_volume')
            ->whereIn('user_id', $userIds)
            ->whereIn('cmd', [0, 1, 2, 3, 4, 5])
            ->where('close_time', '>', UserStatisticsService::MIN_CLOSE_TIME)
            ->where('margin_rate', '<>', 0)
            ->groupBy('user_id', 'symbol')
            ->get();
        $groups = SymbolPrice::query()
            ->where('status', 1)
            ->whereIn('symbol', $volumes->pluck('symbol')->unique()->all())
            ->pluck('group_id', 'symbol');
        $fields = [
            1 => 'total_noble_metal',
            2 => 'total_for_exca',
            3 => 'total_crud_oil',
            4 => 'total_index',
            5 => 'total_currency',
            6 => 'total_stock',
        ];
        $statistics = [];

        foreach ($volumes as $volume) {
            $groupId = (int) ($groups[(string) $volume->symbol] ?? 0);
            if (!isset($fields[$groupId])) {
                continue;
            }

            $userId = (int) $volume->user_id;
            if (!isset($statistics[$userId])) {
                $statistics[$userId] = self::emptyCategories();
            }
            $statistics[$userId][$fields[$groupId]] += (float) $volume->total_volume / 100;
        }

        return $statistics;
    }

    /** @param array<int, array<string, float>> $statistics */
    private static function summarizeCategories(array $statistics): array
    {
        $summary = self::emptyCategories();
        foreach ($statistics as $row) {
            foreach ($summary as $field => $value) {
                $summary[$field] += (float) ($row[$field] ?? 0);
            }
        }

        return $summary;
    }

    /** @return array<string, float> */
    private static function emptyCategories(): array
    {
        return [
            'total_noble_metal' => 0.0,
            'total_for_exca' => 0.0,
            'total_crud_oil' => 0.0,
            'total_index' => 0.0,
            'total_currency' => 0.0,
            'total_stock' => 0.0,
        ];
    }

    /** @return array<string, mixed> */
    private static function emptySummary(): array
    {
        return [
            'mt4_login' => trans('systemlanguage.total'),
            'user_name' => '',
            'mt4MarginLevel' => '',
            'mt4_balance' => '0.00',
            'mt4_equity' => '0.00',
            'total_yuerj' => '0.00',
            'total_yuecj' => '0.00',
            'total_net_worth' => '0.00',
            'total_comm' => '0.00',
            'total_profit' => '0.00',
            'total_noble_metal' => 0,
            'total_for_exca' => 0,
            'total_crud_oil' => 0,
            'total_index' => 0,
            'total_currency' => 0,
            'total_stock' => 0,
            'total_volume' => 0,
            'total_swaps' => '0.00',
            'rec_crt_date' => '',
        ];
    }

    /** @param mixed $value */
    private static function money($value): string
    {
        return number_format((float) ($value ?? 0), 2, '.', '');
    }

    /** @param mixed $value */
    private static function negativeMoney($value): string
    {
        $absolute = abs((float) ($value ?? 0));

        return $absolute > 0 ? '-' . self::money($absolute) : '0.00';
    }
}
