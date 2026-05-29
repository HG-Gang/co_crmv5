<?php

namespace App\Support;

use App\Models\AgentDescendant;
use App\Models\CommissionRecord;
use App\Models\DepositRecord;
use App\Models\UserInfo;
use App\Models\UserTrade;
use App\Models\WithdrawRecord;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class FrontLegacyData
{
    public static function perPage(Request $request, int $default = 15): int
    {
        $perPage = (int) $request->input('per_page', $request->input('limit', $default));

        return max(1, min($perPage, 100));
    }

    public static function dateFrom(Request $request): ?string
    {
        $value = $request->input('date_from', $request->input('startdate'));

        return $value ? trim((string) $value) : null;
    }

    public static function dateTo(Request $request): ?string
    {
        $value = $request->input('date_to', $request->input('enddate'));

        return $value ? trim((string) $value) : null;
    }

    public static function timestampFrom(Request $request): ?int
    {
        $date = self::dateFrom($request);

        return $date ? strtotime($date . ' 00:00:00') : null;
    }

    public static function timestampTo(Request $request): ?int
    {
        $date = self::dateTo($request);

        return $date ? strtotime($date . ' 23:59:59') : null;
    }

    public static function userScopeIds(int $userId, bool $includeSelf = true, ?int $descendantType = null, ?bool $directOnly = null): array
    {
        $query = AgentDescendant::where('agent_id', $userId);

        if ($descendantType !== null) {
            $query->where('descendant_type', $descendantType);
        }
        if ($directOnly !== null) {
            $query->where('is_direct', $directOnly ? 1 : 0);
        }

        $ids = $query->pluck('descendant_id')->map(function ($id) {
            return (int) $id;
        })->all();

        if ($includeSelf) {
            $ids[] = $userId;
        }

        return array_values(array_unique($ids));
    }

    public static function requestedUserId(Request $request): ?int
    {
        $value = $request->input('userId', $request->input('user_id'));

        return $value !== null && $value !== '' ? (int) $value : null;
    }

    public static function applyAllowedUserFilter($query, Request $request, int $currentUserId, string $column = 'user_id', bool $includeDescendants = true): void
    {
        $allowedIds = $includeDescendants ? self::userScopeIds($currentUserId, true) : [$currentUserId];
        $requestedUserId = self::requestedUserId($request);

        if ($requestedUserId !== null) {
            if (in_array($requestedUserId, $allowedIds, true)) {
                $query->where($column, $requestedUserId);
                return;
            }

            $query->whereRaw('1 = 0');
            return;
        }

        $query->whereIn($column, $allowedIds);
    }

    public static function applyCreatedAtFilter($query, Request $request, string $column = 'created_at'): void
    {
        $from = self::timestampFrom($request);
        $to = self::timestampTo($request);

        if ($from) {
            $query->where($column, '>=', $from);
        }
        if ($to) {
            $query->where($column, '<=', $to);
        }
    }

    public static function applyDateTimeFilter($query, Request $request, string $column): void
    {
        $from = self::dateFrom($request);
        $to = self::dateTo($request);

        if ($from) {
            $query->where($column, '>=', $from . ' 00:00:00');
        }
        if ($to) {
            $query->where($column, '<=', $to . ' 23:59:59');
        }
    }

    public static function tradeAliasRow(UserTrade $trade): array
    {
        return [
            'id' => $trade->id,
            'ticket' => $trade->ticket,
            'login' => $trade->user_id,
            'user_id' => $trade->user_id,
            'symbol' => $trade->symbol,
            'digits' => $trade->digits,
            'cmd' => $trade->cmd,
            'cmd_text' => self::cmdText((int) $trade->cmd),
            'volume' => $trade->volume,
            'volume_lots' => self::lots($trade->volume),
            'sl' => $trade->stop_loss,
            'tp' => $trade->take_profit,
            'stop_loss' => $trade->stop_loss,
            'take_profit' => $trade->take_profit,
            'commission' => self::money($trade->commission),
            'profit' => self::money($trade->profit),
            'swaps' => self::money($trade->swaps),
            'open_price' => $trade->open_price,
            'close_price' => $trade->close_price,
            'open_time' => self::dateTime($trade->open_time),
            'close_time' => self::dateTime($trade->close_time),
            'comment' => $trade->comment,
            'modify_time' => self::dateTime($trade->modify_time),
            'reason' => $trade->reason,
        ];
    }

    public static function userBasicAlias(?UserInfo $user): array
    {
        if (!$user) {
            return [];
        }

        return [
            'userId' => $user->user_id,
            'user_id' => $user->user_id,
            'mt4_login' => $user->user_id,
            'userName' => $user->user_name,
            'user_name' => $user->user_name,
            'userSex' => ((int) $user->gender === 2) ? 'Female' : 'Male',
            'gender' => (int) $user->gender,
            'userEmail' => self::maskEmail($user->login ? (string) $user->login->email : ''),
            'email' => self::maskEmail($user->login ? (string) $user->login->email : ''),
            'last_login_ip' => $user->login ? $user->login->last_login_ip : '',
            'last_login_at' => $user->login ? self::dateTime($user->login->last_login_at) : '',
            'login_history_label' => __('common.detail'),
            'userPhone' => self::maskPhone((string) $user->phone),
            'phone' => self::maskPhone((string) $user->phone),
            'userGroupId' => $user->group_id,
            'group_id' => $user->group_id,
            'account_type' => $user->account_type,
            'parent_id' => $user->parent_id,
            'userStatus' => $user->auth_status,
            'created_at' => self::dateTime($user->created_at),
            'rec_crt_date' => self::dateTime($user->created_at),
            'commprop' => (float) $user->comm_rate,
            'mt4_balance' => self::money($user->total_funds),
            'user_money' => self::money($user->total_funds),
            'cust_eqy' => self::money($user->equity),
            'mt4MarginLevel' => self::money($user->risk_ratio),
        ];
    }

    public static function maskEmail(string $value): string
    {
        if ($value === '' || strpos($value, '@') === false) {
            return $value;
        }

        [$name, $domain] = explode('@', $value, 2);
        $visible = mb_substr($name, 0, min(2, mb_strlen($name)));

        return $visible . '***@' . $domain;
    }

    public static function maskPhone(string $value): string
    {
        if ($value === '') {
            return '';
        }

        return strlen($value) >= 7
            ? substr($value, 0, 3) . '****' . substr($value, -4)
            : substr($value, 0, 1) . '***';
    }

    public static function maskIdCard(string $value): string
    {
        if ($value === '') {
            return '';
        }

        return strlen($value) > 8
            ? substr($value, 0, 4) . str_repeat('*', max(4, strlen($value) - 8)) . substr($value, -4)
            : substr($value, 0, 1) . '***';
    }

    public static function userFinancialSummary(UserInfo $user, Request $request, bool $includeDescendants = false): array
    {
        $ids = $includeDescendants ? self::userScopeIds((int) $user->user_id, true) : [(int) $user->user_id];

        return self::financialSummaryForIds($ids, $request);
    }

    public static function financialSummaryForIds(array $ids, Request $request): array
    {
        $ids = array_values(array_unique(array_map('intval', $ids)));
        $fromTs = self::timestampFrom($request);
        $toTs = self::timestampTo($request);
        $fromDate = self::dateFrom($request);
        $toDate = self::dateTo($request);

        $depositQuery = DepositRecord::whereIn('user_id', $ids);
        $withdrawQuery = WithdrawRecord::whereIn('user_id', $ids);
        $tradeQuery = UserTrade::whereIn('user_id', $ids);
        $commissionQuery = CommissionRecord::whereIn('agent_id', $ids);

        if ($fromTs) {
            $depositQuery->where('created_at', '>=', $fromTs);
            $withdrawQuery->where('created_at', '>=', $fromTs);
            $commissionQuery->where('created_at', '>=', $fromTs);
        }
        if ($toTs) {
            $depositQuery->where('created_at', '<=', $toTs);
            $withdrawQuery->where('created_at', '<=', $toTs);
            $commissionQuery->where('created_at', '<=', $toTs);
        }
        if ($fromDate) {
            $tradeQuery->where('open_time', '>=', $fromDate . ' 00:00:00');
        }
        if ($toDate) {
            $tradeQuery->where('open_time', '<=', $toDate . ' 23:59:59');
        }

        $tradeRows = (clone $tradeQuery)
            ->select('symbol', 'volume', 'profit', 'commission', 'swaps')
            ->get();
        $categoryTotals = self::categoryVolumeTotals($tradeRows);
        $equity = UserInfo::whereIn('user_id', $ids)->sum('equity');
        $summary = [
            'total_yuerj' => self::money($depositQuery->sum('amount')),
            'total_yuecj' => self::money($withdrawQuery->sum('apply_amount')),
            'total_net_worth' => self::money($equity),
            'total_comm' => self::money($tradeRows->sum('commission')),
            'total_profit' => self::money($tradeRows->sum('profit')),
            'total_volume' => self::lots($tradeRows->sum('volume')),
            'total_swaps' => self::money($tradeRows->sum('swaps')),
            'fy_money' => self::money($commissionQuery->sum('commission_amount')),
            'rj_money' => self::money((clone $depositQuery)->sum('amount')),
            'qk_money' => self::money((clone $withdrawQuery)->sum('apply_amount')),
        ];

        $summary['total_rebate'] = $summary['fy_money'];

        return array_merge($summary, $categoryTotals);
    }

    public static function categoryVolumeTotals($trades): array
    {
        $symbols = $trades->pluck('symbol')->filter()->unique()->values()->all();
        $configuredGroups = [];

        if ($symbols) {
            $configuredGroups = DB::table('symbol_prices')
                ->whereIn('symbol', $symbols)
                ->select('symbol', DB::raw('MAX(group_id) as group_id'))
                ->groupBy('symbol')
                ->pluck('group_id', 'symbol')
                ->all();
        }

        $totals = [
            'total_noble_metal' => 0.0,
            'total_for_exca' => 0.0,
            'total_crud_oil' => 0.0,
            'total_index' => 0.0,
            'total_currency' => 0.0,
            'total_stock' => 0.0,
        ];

        foreach ($trades as $trade) {
            $volume = ((float) $trade->volume) / 100;
            $groupId = $configuredGroups[$trade->symbol] ?? null;
            $bucket = self::bucketForSymbol((string) $trade->symbol, $groupId ? (int) $groupId : null);
            $totals[$bucket] += $volume;
        }

        foreach ($totals as $key => $value) {
            $totals[$key] = round($value, 2);
        }

        return $totals;
    }

    private static function bucketForSymbol(string $symbol, ?int $groupId): string
    {
        if ($groupId === 1) return 'total_noble_metal';
        if ($groupId === 2) return 'total_for_exca';
        if ($groupId === 3) return 'total_crud_oil';
        if ($groupId === 4) return 'total_index';
        if ($groupId === 5) return 'total_currency';
        if ($groupId === 6) return 'total_stock';

        $upper = strtoupper($symbol);
        if (strpos($upper, 'XAU') !== false || strpos($upper, 'XAG') !== false || strpos($upper, 'GOLD') !== false || strpos($upper, 'SILVER') !== false) {
            return 'total_noble_metal';
        }
        if (strpos($upper, 'OIL') !== false || strpos($upper, 'WTI') !== false || strpos($upper, 'BRENT') !== false || strpos($upper, 'XTI') !== false) {
            return 'total_crud_oil';
        }
        if (preg_match('/(US30|NAS|SPX|DAX|GER|UK100|JP225|HK50|INDEX)/', $upper)) {
            return 'total_index';
        }
        if (preg_match('/^[A-Z]{6}$/', $upper)) {
            return 'total_for_exca';
        }
        if (preg_match('/(BTC|ETH|USDT|CRYPTO)/', $upper)) {
            return 'total_currency';
        }

        return 'total_stock';
    }

    public static function cmdText(int $cmd): string
    {
        $map = [
            0 => 'Buy',
            1 => 'Sell',
            2 => 'Buy Limit',
            3 => 'Sell Limit',
            4 => 'Buy Stop',
            5 => 'Sell Stop',
        ];

        return $map[$cmd] ?? (string) $cmd;
    }

    public static function lots($volume): float
    {
        return round(((float) $volume) / 100, 2);
    }

    public static function money($value): float
    {
        return round((float) $value, 2);
    }

    public static function dateTime($value): string
    {
        if (!$value) {
            return '';
        }

        if (is_numeric($value)) {
            $timestamp = (int) $value;
            return $timestamp > 0 ? date('Y-m-d H:i:s', $timestamp) : '';
        }

        $value = (string) $value;
        if ($value === '1970-01-01 00:00:00') {
            return '';
        }

        return $value;
    }

    /**
     * Wrap a paginator with legacy totalRow/summary keys expected by front tables.
     */
    public static function paginatedListResponse($paginator, array $totalRow = [], array $extra = []): array
    {
        $payload = array_merge($extra, [
            'list' => $paginator,
            'totalRow' => $totalRow,
            'summary' => $totalRow,
        ]);

        return $payload;
    }

    /**
     * Aggregate financial columns for a set of business user IDs.
     */
    public static function financialTotalRowForUserIds(array $userIds, Request $request, string $labelKey = 'mt4_login'): array
    {
        $userIds = array_values(array_unique(array_map('intval', $userIds)));
        if (!$userIds) {
            return [$labelKey => 'total'];
        }

        $summary = self::financialSummaryForIds($userIds, $request);
        $summary[$labelKey] = 'total';
        $summary['user_id'] = 'total';
        $summary['user_name'] = '';
        $summary['mt4_balance'] = self::money(UserInfo::whereIn('user_id', $userIds)->sum('total_funds'));
        $summary['cust_eqy'] = self::money(UserInfo::whereIn('user_id', $userIds)->sum('equity'));

        return $summary;
    }

    public static function depositTotalRow($query): array
    {
        $row = self::aggregateQuery($query)
            ->selectRaw('COALESCE(SUM(amount), 0) as amount_sum')
            ->selectRaw('COALESCE(SUM(CASE WHEN actual_amount IS NULL OR actual_amount = 0 THEN amount ELSE actual_amount END), 0) as actual_sum')
            ->first();

        return [
            'order_no' => 'total',
            'userId' => 'total',
            'amount' => self::money($row->amount_sum ?? 0),
            'actual_amount' => self::money($row->actual_sum ?? 0),
            'depositActProfit' => self::money($row->actual_sum ?? 0),
            'directProfit' => self::money($row->actual_sum ?? 0),
        ];
    }

    public static function withdrawTotalRow($query): array
    {
        $row = self::aggregateQuery($query)
            ->selectRaw('COALESCE(SUM(apply_amount), 0) as apply_sum')
            ->selectRaw('COALESCE(SUM(CASE WHEN actual_amount IS NULL OR actual_amount = 0 THEN apply_amount ELSE actual_amount END), 0) as actual_sum')
            ->selectRaw('COALESCE(SUM(fee), 0) as fee_sum')
            ->first();

        return [
            'order_no' => 'total',
            'userId' => 'total',
            'apply_amount' => self::money($row->apply_sum ?? 0),
            'actual_amount' => self::money($row->actual_sum ?? 0),
            'fee' => self::money($row->fee_sum ?? 0),
            'withdrawalActProfit' => self::money($row->actual_sum ?? 0),
            'applyamount' => self::money($row->apply_sum ?? 0),
            'actdraw' => self::money($row->actual_sum ?? 0),
            'drawpoundage' => self::money($row->fee_sum ?? 0),
            'directdrawalActProfit' => self::money($row->actual_sum ?? 0),
        ];
    }

    public static function commissionTotalRow($query): array
    {
        $row = self::aggregateQuery($query)
            ->selectRaw('COALESCE(SUM(commission_amount), 0) as commission_sum')
            ->selectRaw('COALESCE(SUM(returned_amount), 0) as returned_sum')
            ->selectRaw('COALESCE(SUM(real_amount), 0) as real_sum')
            ->selectRaw('COALESCE(SUM(agent_profit), 0) as profit_sum')
            ->selectRaw('COALESCE(SUM(agent_volume), 0) as volume_sum')
            ->first();

        return [
            'unique_id' => 'total',
            'agent_id' => 'total',
            'commission_amount' => self::money($row->commission_sum ?? 0),
            'returned_amount' => self::money($row->returned_sum ?? 0),
            'real_amount' => self::money($row->real_sum ?? 0),
            'profit' => self::money($row->commission_sum ?? 0),
            'agent_profit' => self::money($row->profit_sum ?? 0),
            'agent_volume' => self::lots($row->volume_sum ?? 0),
        ];
    }

    public static function depositStatusText($status): string
    {
        $status = (string) $status;

        if ($status === '02' || $status === '2') {
            return __('front.status_completed');
        }
        if ($status === '03' || $status === '3') {
            return __('front.status_rejected');
        }

        return __('front.status_unpaid');
    }

    public static function withdrawStatusText($status): string
    {
        $status = (string) $status;

        if ($status === '2') {
            return __('front.status_approved');
        }
        if ($status === '3') {
            return __('front.status_rejected');
        }

        return __('front.status_pending');
    }

    private static function aggregateQuery($query)
    {
        $clone = clone $query;
        $base = method_exists($clone, 'getQuery') ? $clone->getQuery() : $clone;

        foreach (['columns', 'orders', 'limit', 'offset', 'groups', 'havings'] as $property) {
            if (property_exists($base, $property)) {
                $base->{$property} = null;
            }
        }

        return $clone;
    }

    /**
     * Footer totals for open/closed order lists (legacy Layui totalRow).
     */
    public static function tradeOrderTotalRow($query): array
    {
        $rows = (clone $query)->get(['volume', 'commission', 'profit', 'swaps']);

        return [
            'ticket' => 'total',
            'symbol' => 'total',
            'volume' => self::lots($rows->sum('volume')),
            'volume_lots' => self::lots($rows->sum('volume')),
            'commission' => self::money($rows->sum('commission')),
            'profit' => self::money($rows->sum('profit')),
            'swaps' => self::money($rows->sum('swaps')),
        ];
    }

    /**
     * Footer totals for realtime rebate list.
     */
    public static function rebateTotalRow($query): array
    {
        return [
            'ticket' => 'total',
            'profit' => self::money((clone $query)->sum('profit')),
            'total_commission' => self::money((clone $query)->sum('commission_agent')),
            'total_volume' => self::lots((clone $query)->sum('volume')),
        ];
    }
}
