<?php

namespace App\Http\Controllers\Front;

use App\Models\AgentDescendant;
use App\Models\AgentLevel;
use App\Models\UserInfo;
use App\Models\UserTrade;
use App\Constants\ResponseCode;
use App\Support\FrontLegacyData;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Front Position Management Controller
 * 前台持仓管理控制器
 * 
 * Provides position summary, sub-agent summaries, and trade details.
 * 提供持仓概览、下级代理摘要及交易详情。
 */
class PositionController extends FrontBaseController
{
    /**
     * Position summary with date range filter
     * 带日期范围筛选的持仓概览
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function positionSummary(Request $request): JsonResponse
    {
        $userLogin = $request->user('user');
        $agentId = (int) $userLogin->user_id;
        $targetId = $this->resolveSummaryTargetId($request, $agentId);

        if ($targetId === false) {
            return $this->error(__('response.permission_denied'), ResponseCode::PERMISSION_DENIED);
        }

        if (!$targetId || $targetId === $agentId) {
            return $this->positionSummary2Search($request, $agentId);
        }

        $summaryAgentIds = array_merge([$targetId], $this->directAgentIds($targetId));

        $query = UserInfo::with(['login', 'level'])
            ->where('account_type', 1)
            ->whereIn('user_id', array_values(array_unique($summaryAgentIds)));

        if ($request->filled('userName')) {
            $query->where('user_name', 'like', '%' . $request->input('userName') . '%');
        }

        $summary = $query->orderBy('user_id')
            ->paginate(FrontLegacyData::perPage($request))
            ->through(function (UserInfo $user) use ($request, $targetId) {
                $scopeIds = $this->summaryScopeIds((int) $user->user_id, (int) $targetId);

                return array_merge(
                    FrontLegacyData::userBasicAlias($user),
                    $this->agentLevelPayload($user),
                    FrontLegacyData::financialSummaryForIds($scopeIds, $request),
                    [
                        'account_type' => $user->account_type,
                        'open_count' => UserTrade::whereIn('user_id', $scopeIds)
                            ->where('close_time', '1970-01-01 00:00:00')
                            ->count(),
                        'can_drill' => (int) $user->account_type === 1,
                        'target_id' => $targetId,
                        'userPId' => $user->user_id,
                        'searchtype' => $targetId ? 'subAgentsSearch' : 'autoSearch',
                    ]
                );
            });

        $userIds = $summary->getCollection()->pluck('user_id')->map(function ($id) {
            return (int) $id;
        })->all();
        $totalRow = FrontLegacyData::financialTotalRowForUserIds($userIds, $request, 'user_id');
        $totalRow['total_rebate'] = $totalRow['fy_money'] ?? 0;

        return $this->success([
            'chain' => $this->summaryChain($agentId, $targetId ? (int) $targetId : $agentId),
            'list' => $summary,
            'totalRow' => $totalRow,
            'summary' => $totalRow,
        ], 'response.query_success', ResponseCode::SUCCESS);
    }

    /**
     * Strict port of old User\\PositionSummary2Controller@positionSummary2Search.
     * It returns current login user's own MT4-style summary row and never mock data.
     */
    private function positionSummary2Search(Request $request, int $agentId): JsonResponse
    {
        $user = UserInfo::select('user_id', 'user_name')->where('user_id', $agentId)->first();

        if (!$user) {
            return $this->success([
                'count' => 0,
                'data' => [],
                'rows' => [],
                'list' => ['data' => []],
                'totalRow' => [],
                'summary' => [],
            ], 'response.query_success', ResponseCode::SUCCESS);
        }

        $sumData = $this->selfLoginIdSumData($request, $agentId);
        $row = array_merge([
            'user_id' => (int) $user->user_id,
            'user_name' => $user->user_name,
            'symbol' => 'ALL',
            'open_count' => UserTrade::where('user_id', $agentId)
                ->where('close_time', '1970-01-01 00:00:00')
                ->count(),
            'floating_profit' => $this->floatingProfitForUser($agentId),
            'total' => 1,
        ], $sumData);

        $row['volume'] = $row['total_volume'];
        $row['profit'] = $row['total_profit'];
        $row['commission'] = $row['total_comm'];
        $row['total_rebate'] = 0;

        return $this->success([
            'count' => 1,
            'data' => [$row],
            'rows' => [$row],
            'list' => [
                'current_page' => 1,
                'data' => [$row],
                'from' => 1,
                'last_page' => 1,
                'per_page' => FrontLegacyData::perPage($request),
                'to' => 1,
                'total' => 1,
            ],
            'totalRow' => $sumData,
            'summary' => $sumData,
            'chain' => $this->summaryChain($agentId, $agentId),
        ], 'response.query_success', ResponseCode::SUCCESS);
    }

    private function selfLoginIdSumData(Request $request, int $loginId): array
    {
        $startDate = $request->input('startdate', $request->input('date_from')) ?: '2024-01-01';
        $endDate = $request->input('enddate', $request->input('date_to')) ?: now()->format('Y-m-d');
        $closeTime = '1970-01-01 00:00:00';
        $tradeCmds = [0, 1, 2, 3, 4, 5];

        $query = UserTrade::query()->where('user_id', $loginId);
        $this->applyLegacyCloseDateFilter($query, $startDate, $endDate);

        $trades = $query->get(['cmd', 'symbol', 'volume', 'profit', 'commission', 'swaps', 'close_time', 'margin_rate', 'comment']);
        $symbolsByGroup = $this->symbolsByGroup();
        $sum = [
            'total_yuerj' => 0.0,
            'total_yuecj' => 0.0,
            'total_profit' => 0.0,
            'total_comm' => 0.0,
            'total_noble_metal' => 0.0,
            'total_for_exca' => 0.0,
            'total_crud_oil' => 0.0,
            'total_index' => 0.0,
            'total_currency' => 0.0,
            'total_stock' => 0.0,
            'total_volume' => 0.0,
            'total_swaps' => 0.0,
        ];

        foreach ($trades as $trade) {
            $cmd = (int) $trade->cmd;
            $profit = (float) $trade->profit;
            $volume = (float) $trade->volume;
            $comment = (string) $trade->comment;

            if ($cmd === 6 && $profit > 0 && $this->isDepositComment($comment)) {
                $sum['total_yuerj'] += $profit;
            }
            if ($cmd === 6 && $profit < 0 && $this->isWithdrawalComment($comment)) {
                $sum['total_yuecj'] += $profit;
            }

            if (!in_array($cmd, $tradeCmds, true) || !$this->isClosedTrade($trade, $closeTime)) {
                continue;
            }

            $sum['total_profit'] += $profit;
            $sum['total_comm'] += (float) $trade->commission;
            if ((float) $trade->swaps < 0) {
                $sum['total_swaps'] += (float) $trade->swaps;
            }
            $sum['total_volume'] += $volume;

            foreach ([
                1 => 'total_noble_metal',
                2 => 'total_for_exca',
                3 => 'total_crud_oil',
                4 => 'total_index',
                5 => 'total_currency',
                6 => 'total_stock',
            ] as $groupId => $field) {
                if (isset($symbolsByGroup[$groupId][strtoupper((string) $trade->symbol)])) {
                    $sum[$field] += $volume;
                    break;
                }
            }
        }

        return $this->formatPositionSummary2Data($sum);
    }

    private function applyLegacyCloseDateFilter($query, ?string $startDate, ?string $endDate): void
    {
        $startValid = $startDate && strtotime($startDate) !== false;
        $endValid = $endDate && strtotime($endDate) !== false;

        if ($startValid && $endValid) {
            $query->whereBetween('close_time', [date('Y-m-d', strtotime($startDate)) . ' 00:00:00', date('Y-m-d', strtotime($endDate)) . ' 23:59:59']);
            return;
        }
        if ($startValid) {
            $query->where('close_time', '>=', date('Y-m-d', strtotime($startDate)) . ' 23:59:59');
            return;
        }
        if ($endValid) {
            $query->where('close_time', '<', date('Y-m-d', strtotime($endDate)) . ' 00:00:00');
        }
    }

    private function symbolsByGroup(): array
    {
        $groupColumn = Schema::hasColumn('symbol_prices', 'sym_grp_id') ? 'sym_grp_id' : 'group_id';
        $symbolColumn = Schema::hasColumn('symbol_prices', 'sym_symbol') ? 'sym_symbol' : 'symbol';
        $activeColumn = Schema::hasColumn('symbol_prices', 'voided') ? 'voided' : 'status';

        $rows = DB::table('symbol_prices')
            ->select($groupColumn, $symbolColumn)
            ->where($activeColumn, 1)
            ->whereIn($groupColumn, [1, 2, 3, 4, 5, 6])
            ->get();

        $groups = [];
        foreach ($rows as $row) {
            $groups[(int) $row->{$groupColumn}][strtoupper((string) $row->{$symbolColumn})] = true;
        }

        return $groups;
    }

    private function isClosedTrade(UserTrade $trade, string $closeTime): bool
    {
        return (string) $trade->close_time > $closeTime && (float) $trade->margin_rate != 0.0;
    }

    private function isDepositComment(string $comment): bool
    {
        return preg_match('/deposit|入金|balance|dp|charge|credit/i', $comment) === 1;
    }

    private function isWithdrawalComment(string $comment): bool
    {
        return preg_match('/withdraw|出金|wd|取款|提款/i', $comment) === 1;
    }

    private function formatPositionSummary2Data(array $data): array
    {
        $totalYuerj = (float) $data['total_yuerj'];
        $totalYuecj = (float) $data['total_yuecj'];

        return [
            'total_yuerj' => number_format($totalYuerj, 2, '.', ''),
            'total_yuecj' => number_format($totalYuecj, 2, '.', ''),
            'total_profit' => number_format((float) $data['total_profit'], 2, '.', ''),
            'total_comm' => number_format(abs((float) $data['total_comm']), 2, '.', ''),
            'total_net_worth' => number_format($totalYuerj - abs($totalYuecj), 2, '.', ''),
            'total_noble_metal' => number_format(((float) $data['total_noble_metal']) / 100, 2, '.', ''),
            'total_for_exca' => number_format(((float) $data['total_for_exca']) / 100, 2, '.', ''),
            'total_crud_oil' => number_format(((float) $data['total_crud_oil']) / 100, 2, '.', ''),
            'total_index' => number_format(((float) $data['total_index']) / 100, 2, '.', ''),
            'total_currency' => number_format(((float) $data['total_currency']) / 100, 2, '.', ''),
            'total_stock' => number_format(((float) $data['total_stock']) / 100, 2, '.', ''),
            'total_volume' => number_format(((float) $data['total_volume']) / 100, 2, '.', ''),
            'total_swaps' => number_format((float) $data['total_swaps'], 2, '.', ''),
        ];
    }

    private function floatingProfitForUser(int $userId): string
    {
        $value = UserTrade::where('user_id', $userId)
            ->where('close_time', '1970-01-01 00:00:00')
            ->sum('profit');

        return number_format((float) $value, 2, '.', '');
    }

    private function agentLevelPayload(UserInfo $user): array
    {
        $level = $user->relationLoaded('level') ? $user->level : AgentLevel::find($user->level_id);
        $rank = (int) ($level->level_code ?? $user->level_id ?? 5);

        if ($rank < 1) {
            $rank = 5;
        }
        if ($rank > 5) {
            $rank = 5;
        }

        return [
            'agent_level_rank' => $rank,
            'agent_level_name' => $level->name ?? ('Level ' . $rank),
        ];
    }

    private function summaryChain(int $agentId, int $targetId): array
    {
        $target = UserInfo::with('level')->where('user_id', $targetId)->first();
        $ids = [];

        if (!$target) {
            return [];
        }

        if ($target->family_tree) {
            $ids = array_values(array_filter(array_map('intval', explode(',', (string) $target->family_tree))));
        }
        if (!in_array($targetId, $ids, true)) {
            $ids[] = $targetId;
        }

        $rootIndex = array_search($agentId, $ids, true);
        if ($rootIndex !== false) {
            $ids = array_slice($ids, $rootIndex);
        } elseif ($agentId !== $targetId) {
            array_unshift($ids, $agentId);
        }

        $users = UserInfo::with('level')
            ->whereIn('user_id', array_values(array_unique($ids)))
            ->get()
            ->keyBy('user_id');

        return collect($ids)
            ->unique()
            ->map(function (int $id) use ($users) {
                $user = $users->get($id);
                if (!$user) {
                    return null;
                }

                return array_merge([
                    'user_id' => $user->user_id,
                    'user_name' => $user->user_name,
                ], $this->agentLevelPayload($user));
            })
            ->filter()
            ->values()
            ->all();
    }

    private function resolveSummaryTargetId(Request $request, int $agentId): int|false|null
    {
        $targetId = $request->input('userPId', $request->input('target_id'));
        $requestedUserId = FrontLegacyData::requestedUserId($request);

        if ($requestedUserId !== null) {
            $targetId = $requestedUserId;
        }
        if ($targetId === null || $targetId === '') {
            return null;
        }

        $targetId = (int) $targetId;
        if ($targetId === $agentId) {
            return $targetId;
        }

        $isAllowedAgent = AgentDescendant::where('agent_id', $agentId)
            ->where('descendant_id', $targetId)
            ->where('descendant_type', 1)
            ->exists();

        return $isAllowedAgent ? $targetId : false;
    }

    private function directAgentIds(int $agentId): array
    {
        $ids = AgentDescendant::where('agent_id', $agentId)
            ->where('descendant_type', 1)
            ->where('is_direct', 1)
            ->pluck('descendant_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        if (!$ids) {
            $ids = UserInfo::where('parent_id', $agentId)
                ->where('account_type', 1)
                ->pluck('user_id')
                ->map(fn ($id) => (int) $id)
                ->all();
        }

        return array_values(array_unique($ids));
    }

    private function directDescendantIds(int $agentId): array
    {
        $ids = AgentDescendant::where('agent_id', $agentId)
            ->where('is_direct', 1)
            ->pluck('descendant_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        if (!$ids) {
            $ids = UserInfo::where('parent_id', $agentId)
                ->pluck('user_id')
                ->map(fn ($id) => (int) $id)
                ->all();
        }

        return array_values(array_unique($ids));
    }

    private function summaryScopeIds(int $rowAgentId, ?int $targetId): array
    {
        if ($targetId !== null && $rowAgentId === $targetId) {
            return array_values(array_unique(array_merge([$rowAgentId], $this->directDescendantIds($rowAgentId))));
        }

        return FrontLegacyData::userScopeIds($rowAgentId, true);
    }

    /**
     * Search position summary with filters
     * 带筛选的持仓搜索
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function search(Request $request): JsonResponse
    {
        $userLogin = $request->user('user');
        $agentId = (int) $userLogin->user_id;

        $dateFrom = $request->input('date_from');
        $dateTo = $request->input('date_to');
        $symbol = $request->input('symbol');

        // Get all descendants and self IDs
        $allDescendantIds = AgentDescendant::where('agent_id', $agentId)->pluck('descendant_id')->toArray();
        $allDescendantIds[] = $agentId;

        $query = UserTrade::whereIn('user_trades.user_id', $allDescendantIds)
            ->join('user_infos', 'user_trades.user_id', '=', 'user_infos.user_id')
            ->selectRaw('user_trades.user_id, user_infos.user_name, SUM(volume) as total_volume, SUM(profit) as total_profit, COUNT(*) as count')
            ->groupBy('user_trades.user_id', 'user_infos.user_name');

        if ($dateFrom) $query->where('close_time', '>=', $dateFrom . ' 00:00:00');
        if ($dateTo) $query->where('close_time', '<=', $dateTo . ' 23:59:59');
        if ($symbol) $query->where('symbol', $symbol);

        $results = $query->paginate(FrontLegacyData::perPage($request));

        return $this->success($results, __('response.query_success'), ResponseCode::SUCCESS);
    }

    /**
     * Search sub-user position summary
     * 下级用户持仓搜索
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function subPositionSummary(Request $request): JsonResponse
    {
        $userLogin = $request->user('user');
        $agentId = (int) $userLogin->user_id;
        $subAgents = FrontLegacyData::userScopeIds($agentId, false, 1);
        $query = UserInfo::with('login')->whereIn('user_id', $subAgents);
        
        if ($request->has('user_name')) {
            $query->where('user_name', 'like', '%' . $request->user_name . '%');
        }

        $results = $query->paginate(FrontLegacyData::perPage($request))
            ->through(function (UserInfo $user) use ($request) {
                return array_merge(
                    FrontLegacyData::userBasicAlias($user),
                    FrontLegacyData::userFinancialSummary($user, $request, true)
                );
            });

        return $this->success($results, __('response.query_success'), ResponseCode::SUCCESS);
    }

    /**
     * Show trade details for a specific user
     * 特定用户的交易详情
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function positionDetail(Request $request): JsonResponse
    {
        $targetUserId = $request->input('user_id');
        $userLogin = $request->user('user');
        $agentId = $userLogin->user_id;

        if (!$targetUserId) {
            return $this->error(__('response.validation_failed'), ResponseCode::VALIDATION_FAILED);
        }

        // Verify the user is in current agent's network
        $isDescendant = AgentDescendant::where('agent_id', $agentId)->where('descendant_id', $targetUserId)->exists();
        if (!$isDescendant && $targetUserId != $agentId) {
            return $this->error(__('response.permission_denied'), ResponseCode::PERMISSION_DENIED);
        }

        $query = UserTrade::where('user_id', $targetUserId);
        
        if ($request->has('symbol')) $query->where('symbol', 'like', '%' . $request->input('symbol') . '%');
        if ($request->has('ticket')) $query->where('ticket', $request->input('ticket'));
        if ($request->has('orderId')) $query->where('ticket', $request->input('orderId'));
        FrontLegacyData::applyDateTimeFilter($query, $request, 'open_time');
        if ($request->has('status')) {
             // 1: Closed, 0: Open
             if ($request->status == 1) {
                 $query->where('close_time', '>', '1970-01-01 00:00:00');
             } else {
                 $query->where('close_time', '1970-01-01 00:00:00');
             }
        }

        $trades = $query->orderBy('close_time', 'desc')
            ->orderBy('open_time', 'desc')
            ->paginate(FrontLegacyData::perPage($request))
            ->through(function (UserTrade $trade) {
                return FrontLegacyData::tradeAliasRow($trade);
            });

        return $this->success($trades, __('response.query_success'), ResponseCode::SUCCESS);
    }

    /**
     * Legacy method for clickSearch
     */
    public function clickSearch(Request $request): JsonResponse
    {
        return $this->positionDetail($request);
    }
}
