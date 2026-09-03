<?php

/**
 * Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/09/03
 * Time: 14:30
 */

namespace App\Http\Controllers\Front;

use App\Models\AgentLevel;
use App\Models\CommissionRecord;
use App\Models\UserInfo;
use App\Models\UserTrade;
use App\Constants\ResponseCode;
use App\Support\FrontLegacyData;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * 前台持仓管理控制器。
 *
 * 文件功能：
 * - 处理持仓汇总、本人 MT4 汇总、下级代理汇总、交易明细、旧前台搜索入口、代理关系权限校验和品种分类统计。
 * - 新接口与旧前台 `user/position/*` 入口共用本控制器，代理持仓汇总从 user_infos、agent_descendants、user_trades 和 symbol_prices 等真实数据表读取。
 * - 代理钻取必须先通过 agent_descendants 校验上下级关系，避免普通代理通过 user_id、userPId 或 target_id 越权查看其他代理网络。
 */
class PositionController extends FrontBaseController
{
    /**
     * 旧 MT4 入金统计允许的备注代码。
     *
     * 业务边界：
     * - 与旧项目 Abstract_Basic_Controller::sumDepositKeyWord 的 REGEXP 白名单逐项保持一致。
     * - DBCN 是返佣入账，不属于旧“本人余额入金”统计，故意不放入该列表。
     * - 仅允许固定代码可避免普通文本包含 deposit、credit 等单词时被误计入客户净入金。
     *
     * @var array<int, string> 旧 MT4 入金和出金退回的机器备注前缀。
     */
    private const LEGACY_DEPOSIT_COMMENT_CODES = [
        'DBAA', 'DBCT', 'DBGN', 'DBMN', 'DBPA', 'DBPN',
        'DBSN', 'DBTN', 'DBUN', 'DBZN', 'DBAD', 'WBIR',
    ];

    /**
     * 旧 MT4 出金统计允许的备注代码。
     *
     * 业务边界：
     * - 与旧项目 Abstract_Basic_Controller::sumWithdrawalKeyWord 的 REGEXP 白名单逐项保持一致。
     * - DBZR 是清零存入退回，在旧业务中是负金额出金，必须保留在本列表。
     *
     * @var array<int, string> 旧 MT4 出金和清零退回的机器备注前缀。
     */
    private const LEGACY_WITHDRAWAL_COMMENT_CODES = [
        'WBAA', 'WBCN', 'WBCT', 'WBHN', 'WBIN',
        'WBPN', 'WBSN', 'WBTN', 'WBAD', 'DBZR',
    ];

    /**
     * 旧 MT4 代理返佣统计允许的备注代码。
     *
     * 业务边界：
     * - 旧项目 sumCommissionRebateKeyWord 只接受 DBCN，不能用 commission_records 替代。
     * - 代理持仓汇总的净入金需包含该余额类返佣，因此该代码与普通入金代码分开维护。
     *
     * @var array<int, string> 旧 MT4 返点余额变动的机器备注前缀。
     */
    private const LEGACY_REBATE_COMMENT_CODES = ['DBCN'];

    /**
     * positionSummary 用于返回当前代理可见的持仓汇总。
     *
     * 参数和变量含义：
     * - $request：当前 HTTP 请求对象，承载 searchtype、userId、userName、startdate、enddate、symbol 等筛选参数。
     * - agentId 表示当前前台登录代理业务用户 ID，来自 user guard 或旧前台 session。
     * - searchType 表示旧页面查询类型；autoSearch 返回根代理网络，clickSearch 与 subAgentsSearch 分派给对应闭环入口。
     * - userId 表示现代页面传入的指定代理交易账号，存在时必须复用 clickSearch 的权限校验和汇总口径。
     *
     * @param Request $request 当前 HTTP 请求对象。
     * @return JsonResponse 当前代理可见的持仓汇总响应。
     */
    public function positionSummary(Request $request): JsonResponse
    {
        $userLogin = $this->legacyFrontUserLogin($request);
        if (!$userLogin) {
            return $this->legacyFrontAuthError($request);
        }

        if ((int) $userLogin->account_type !== 1) {
            return $this->error(__('response.permission_denied'), ResponseCode::PERMISSION_DENIED);
        }

        $agentId = (int) $userLogin->user_id;
        $searchType = (string) $request->input('searchtype', 'autoSearch');

        // 入口分派阶段：旧页面查询类型与目标代理参数统一走各自的闭环入口，保证权限校验与汇总口径一致。
        if ($searchType === 'subAgentsSearch') {
            return $this->subPositionSummary($request);
        }
        if ($searchType === 'clickSearch') {
            return $this->clickSearch($request);
        }
        if ($this->requestedSummaryAgentId($request) !== null) {
            return $this->clickSearch($request);
        }
        if ($request->filled('userName')) {
            // 姓名筛选仅在当前代理后代代理集合内执行，避免页面搜索条件绕过代理树权限。
            $matchedAgentIds = UserInfo::where('account_type', 1)
                ->whereIn('user_id', FrontLegacyData::userScopeIds($agentId, true, 1))
                ->where('user_name', 'like', '%' . $request->input('userName') . '%')
                ->pluck('user_id')
                ->map(function ($id) {
                    return (int) $id;
                })
                ->all();

            return $this->legacyAgentSummaryResponse(
                $request,
                $agentId,
                $matchedAgentIds,
                $agentId,
                'clickSearch'
            );
        }

        return $this->legacyAgentSummaryResponse(
            $request,
            $agentId,
            [$agentId],
            $agentId,
            'autoSearch'
        );
    }

    /**
     * 生成旧前台代理持仓汇总响应。
     *
     * 处理步骤：
     * - 先锁定本次允许展示的代理行，行数据只能来自已完成权限校验的代理 ID。
     * - 批量读取这些代理完整下级网络内的 MT4 交易，再用旧备注、平仓和品种组规则计算每一行。
     * - 汇总行基于全部匹配代理行生成，避免分页时将当前页小计误返回为全部合计。
     *
     * @param Request $request 当前 HTTP 请求，读取日期、品种、姓名和分页筛选条件。
     * @param int $loginAgentId 当前登录代理业务用户 ID。
     * @param array<int, int|string> $summaryAgentIds 本次表格允许展示的代理业务用户 ID。
     * @param int $targetId 当前钻取目标代理 ID，用于返回页面面包屑与行内下钻参数。
     * @param string $searchType 旧页面查询类型，例如 autoSearch、subAgentsSearch 或 clickSearch。
     * @return JsonResponse 含 list、totalRow、summary 和 chain 的旧前台兼容响应。
     */
    private function legacyAgentSummaryResponse(
        Request $request,
        int $loginAgentId,
        array $summaryAgentIds,
        int $targetId,
        string $searchType
    ): JsonResponse {
        $ids = array_values(array_unique(array_filter(array_map('intval', $summaryAgentIds))));
        $query = UserInfo::with(['login', 'level'])
            ->where('account_type', 1)
            ->whereIn('user_id', $ids);

        if ($request->filled('userName')) {
            $query->where('user_name', 'like', '%' . $request->input('userName') . '%');
        }

        $allAgents = (clone $query)->orderBy('user_id')->get();
        $summaryRows = $this->legacyAgentSummaryRows($allAgents, $request);
        $summary = $query->orderBy('user_id')
            ->paginate(FrontLegacyData::perPage($request))
            ->through(function (UserInfo $user) use ($summaryRows, $targetId, $searchType) {
                $userId = (int) $user->user_id;
                $summaryRow = $summaryRows[$userId] ?? $this->emptyLegacyAgentSummaryRow();

                return array_merge(
                    FrontLegacyData::userBasicAlias($user),
                    $this->agentLevelPayload($user),
                    $summaryRow,
                    [
                        // user_infos.user_id 在部分旧库驱动中会序列化为字符串，接口统一返回整数交易账号。
                        'user_id' => $userId,
                        'account_type' => (int) $user->account_type,
                        'can_drill' => true,
                        'target_id' => $targetId,
                        'userPId' => $userId,
                        'searchtype' => $searchType,
                    ]
                );
            });

        $totalRow = $this->sumLegacyAgentSummaryRows($summaryRows);

        return $this->success([
            'chain' => $this->summaryChain($loginAgentId, $targetId),
            'list' => $summary,
            'totalRow' => $totalRow,
            'summary' => $totalRow,
        ], 'response.query_success', ResponseCode::SUCCESS);
    }

    /**
     * 批量计算代理行对应的旧 MT4 持仓汇总。
     *
     * 设计原因：
     * - 先合并所有代理下级范围，只查询一次 user_trades，避免每行执行一套交易查询造成分页 N+1 问题。
     * - 每个代理行仍使用独立的完整后代范围，保证根代理、下钻代理和搜索代理的汇总口径相同。
     *
     * @param \Illuminate\Support\Collection<int, UserInfo> $agents 本次需要输出的代理资料集合。
     * @param Request $request 当前 HTTP 请求，提供日期和品种筛选条件。
     * @return array<int, array<string, string>> 以代理用户 ID 为键的旧前台金额汇总行。
     */
    private function legacyAgentSummaryRows($agents, Request $request): array
    {
        if ($agents->isEmpty()) {
            return [];
        }

        $scopesByAgent = [];
        $allScopedUserIds = [];

        foreach ($agents as $agent) {
            $agentId = (int) $agent->user_id;
            $scopeIds = FrontLegacyData::userScopeIds($agentId, true);
            $scopesByAgent[$agentId] = $scopeIds;
            $allScopedUserIds = array_merge($allScopedUserIds, $scopeIds);
        }

        $tradesByUser = $this->legacyAgentPositionTrades($allScopedUserIds, $request)
            ->groupBy(function (UserTrade $trade) {
                return (int) $trade->user_id;
            });
        $symbolsByGroup = $this->symbolsByGroup();
        $rows = [];

        foreach ($scopesByAgent as $agentId => $scopeIds) {
            $scopeTrades = [];
            foreach ($scopeIds as $scopeId) {
                foreach ($tradesByUser->get((int) $scopeId, []) as $trade) {
                    $scopeTrades[] = $trade;
                }
            }

            $rows[$agentId] = $this->formatLegacyAgentSummaryData(
                $this->summarizeLegacyAgentTrades($scopeTrades, $symbolsByGroup)
            );
        }

        return $rows;
    }

    /**
     * 读取参与代理持仓汇总的旧 MT4 交易。
     *
     * @param array<int, int|string> $userIds 已授权代理网络内的业务用户 ID。
     * @param Request $request 当前 HTTP 请求，支持 startdate/enddate 与 symbol 筛选。
     * @return \Illuminate\Support\Collection<int, UserTrade> 日期和品种过滤后的真实 MT4 交易集合。
     */
    private function legacyAgentPositionTrades(array $userIds, Request $request)
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $userIds))));
        if ($ids === []) {
            return collect();
        }

        $startDate = $request->input('startdate', $request->input('date_from')) ?: '2024-01-01';
        $endDate = $request->input('enddate', $request->input('date_to')) ?: now()->format('Y-m-d');
        $query = UserTrade::query()->whereIn('user_id', $ids);

        $this->applyLegacyCloseDateFilter($query, $startDate, $endDate);
        FrontLegacyData::applySymbolFilter($query, $request);

        return $query->get([
            'user_id', 'cmd', 'symbol', 'volume', 'profit', 'commission', 'swaps', 'close_time', 'margin_rate', 'comment',
        ]);
    }

    /**
     * 按旧项目规则汇总一组 MT4 交易。
     *
     * 计算规则：
     * - CMD=6 的固定入金、出金和 DBCN 返点备注分别计入余额字段。
     * - CMD=0 至 5 且 close_time 大于 1970-01-01、margin_rate 非零时才视为有效平仓交易。
     * - 品种成交量按启用品种组 1 至 6 分类，原始 volume 除以 100 的转换由格式化方法统一完成。
     *
     * @param iterable<UserTrade> $trades 当前代理网络内的交易集合。
     * @param array<int, array<string, bool>> $symbolsByGroup 启用品种分组索引。
     * @return array<string, float> 未格式化的旧 MT4 汇总金额和原始成交量。
     */
    private function summarizeLegacyAgentTrades(iterable $trades, array $symbolsByGroup): array
    {
        $sum = [
            'total_yuerj' => 0.0,
            'total_yuecj' => 0.0,
            'total_rebate' => 0.0,
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
        $tradeCmds = [0, 1, 2, 3, 4, 5];
        $closeTime = '1970-01-01 00:00:00';

        foreach ($trades as $trade) {
            $cmd = (int) $trade->cmd;
            $profit = (float) $trade->profit;
            $comment = (string) $trade->comment;

            if ($cmd === 6 && $profit > 0 && $this->isDepositComment($comment)) {
                $sum['total_yuerj'] += $profit;
            }
            if ($cmd === 6 && $profit < 0 && $this->isWithdrawalComment($comment)) {
                $sum['total_yuecj'] += $profit;
            }
            if ($cmd === 6 && $this->isRebateComment($comment)) {
                $sum['total_rebate'] += $profit;
            }
            if (!in_array($cmd, $tradeCmds, true) || !$this->isClosedTrade($trade, $closeTime)) {
                continue;
            }

            $volume = (float) $trade->volume;
            $sum['total_profit'] += $profit;
            $sum['total_comm'] += (float) $trade->commission;
            $sum['total_volume'] += $volume;
            if ((float) $trade->swaps < 0) {
                $sum['total_swaps'] += (float) $trade->swaps;
            }

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

        return $sum;
    }

    /**
     * 格式化代理持仓汇总字段。
     *
     * @param array<string, float|int|string> $data 原始旧 MT4 汇总字段，必须包含 total_rebate。
     * @return array<string, string> 页面可直接展示的两位小数字符串字段。
     */
    private function formatLegacyAgentSummaryData(array $data): array
    {
        $formatted = $this->formatPositionSummary2Data($data);
        $totalYuerj = (float) $data['total_yuerj'];
        $totalYuecj = (float) $data['total_yuecj'];
        $totalRebate = (float) $data['total_rebate'];

        $formatted['total_rebate'] = number_format($totalRebate, 2, '.', '');
        $formatted['total_net_worth'] = number_format($totalYuerj - abs($totalYuecj) + $totalRebate, 2, '.', '');

        return $formatted;
    }

    /**
     * 创建零值代理持仓汇总行。
     *
     * @return array<string, string> 没有 MT4 交易时仍完整返回旧页面依赖的全部统计字段。
     */
    private function emptyLegacyAgentSummaryRow(): array
    {
        return $this->formatLegacyAgentSummaryData([
            'total_yuerj' => 0,
            'total_yuecj' => 0,
            'total_rebate' => 0,
            'total_profit' => 0,
            'total_comm' => 0,
            'total_noble_metal' => 0,
            'total_for_exca' => 0,
            'total_crud_oil' => 0,
            'total_index' => 0,
            'total_currency' => 0,
            'total_stock' => 0,
            'total_volume' => 0,
            'total_swaps' => 0,
        ]);
    }

    /**
     * 汇总全部代理行，生成与旧 Layui totalRow 对齐的合计数据。
     *
     * @param array<int, array<string, string>> $summaryRows 以代理业务用户 ID 为键的完整汇总行。
     * @return array<string, string> 包含“合计”标识及所有金额字段的总计行。
     */
    private function sumLegacyAgentSummaryRows(array $summaryRows): array
    {
        $total = [
            'total_yuerj' => 0.0,
            'total_yuecj' => 0.0,
            'total_rebate' => 0.0,
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

        foreach ($summaryRows as $summaryRow) {
            foreach ($total as $field => $value) {
                $total[$field] = $value + (float) ($summaryRow[$field] ?? 0);
            }
        }

        return array_merge([
            'user_id' => '',
            'user_name' => __('systemlanguage.total'),
        ], $this->formatLegacyAgentSummaryData($total));
    }

    /**
     * 返回当前登录账户本人的 MT4 汇总。
     *
     * 现代 REST 页面与旧 Layui 页面必须共用同一聚合口径，避免形成第二数据源。
     *
     * @param Request $request 当前 HTTP 请求对象。
     * @return JsonResponse 本人 MT4 汇总响应。
     */
    public function selfSummary(Request $request): JsonResponse
    {
        return $this->positionSummary2Search($request);
    }

    /**
     * positionSummary2Search 用于兼容旧前台本人 MT4 汇总入口。
     *
     * 参数和变量含义：
     * - $request：当前 HTTP 请求对象，承载 startdate、enddate、date_from、date_to、symbol 等筛选参数。
     * - $agentId：可选代理业务用户 ID；为空时从当前登录用户解析，非空时由 positionSummary 复用。
     * - $sumData：本人 MT4 汇总数据，包含入金、出金、盈亏、手续费、库存费、品种手数和总手数。
     * - $row：旧前台表格展示行，保留 volume、profit、commission 等历史字段别名。
     *
     * @param Request $request 当前 HTTP 请求对象。
     * @param int|null $agentId 可选代理业务用户 ID。
     * @return JsonResponse 本人 MT4 汇总响应。
     */
    public function positionSummary2Search(Request $request, int $agentId = null): JsonResponse
    {
        if ($agentId === null) {
            $agentId = $this->legacyFrontUserId($request);
            if ($agentId <= 0) {
                return $this->legacyFrontAuthError($request);
            }
        }

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
        $sumData['total_rebate'] = $this->totalRebateForScope([$agentId], $request);
        $row = array_merge([
            'user_id' => (int) $user->user_id,
            'user_name' => $user->user_name,
            'symbol' => 'ALL',
            'open_count' => $this->openCountForUser($agentId, $request),
            'floating_profit' => $this->floatingProfitForUser($agentId, $request),
            'total' => 1,
        ], $sumData);

        $row['volume'] = $row['total_volume'];
        $row['profit'] = $row['total_profit'];
        $row['commission'] = $row['total_comm'];

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

    /**
     * totalRebateForScope 用于按代理 ID 汇总真实返佣记录。
     *
     * 参数含义：
     * - $userIds：需要统计返佣的代理业务用户 ID 列表，对应 commission_records.agent_id。
     * - $request：当前 HTTP 请求对象，用于复用 startdate、enddate 等创建时间筛选。
     *
     * @param array<int, int|string> $userIds 代理业务用户 ID 列表。
     * @param Request $request 当前 HTTP 请求对象。
     * @return string 格式化后的返佣金额。
     */
    private function totalRebateForScope(array $userIds, Request $request): string
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $userIds))));
        if (!$ids) {
            return FrontLegacyData::money(0);
        }

        $query = CommissionRecord::whereIn('agent_id', $ids);
        FrontLegacyData::applyCreatedAtFilter($query, $request);

        return FrontLegacyData::money($query->sum('commission_amount'));
    }

    /**
     * selfLoginIdSumData 用于聚合本人 MT4 入金、出金、盈亏、手续费、库存费和品种手数。
     *
     * 参数和变量含义：
     * - $request：当前 HTTP 请求对象，承载日期与交易品种筛选条件。
     * - $loginId：当前用户 MT4/业务登录 ID，对应 user_trades.user_id。
     * - $startDate/$endDate：旧前台平仓时间筛选范围，优先读取 startdate/enddate，兼容 date_from/date_to。
     * - $symbolsByGroup：按 symbol_prices 品种组整理的可统计交易品种集合。
     * - $sum：旧前台本人汇总字段集合，最终会交给 formatPositionSummary2Data 统一格式化。
     *
     * @param Request $request 当前 HTTP 请求对象。
     * @param int $loginId 当前用户 MT4/业务登录 ID。
     * @return array<string, string> 旧前台本人持仓汇总字段。
     */
    private function selfLoginIdSumData(Request $request, int $loginId): array
    {
        $startDate = $request->input('startdate', $request->input('date_from')) ?: '2024-01-01';
        $endDate = $request->input('enddate', $request->input('date_to')) ?: now()->format('Y-m-d');
        $closeTime = '1970-01-01 00:00:00';
        $tradeCmds = [0, 1, 2, 3, 4, 5];

        $query = UserTrade::query()->where('user_id', $loginId);
        $this->applyLegacyCloseDateFilter($query, $startDate, $endDate);
        FrontLegacyData::applySymbolFilter($query, $request);

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

    /**
     * applyLegacyCloseDateFilter 用于兼容旧前台平仓时间筛选。
     *
     * 参数含义：
     * - $query：交易查询构造器，调用方已限定 user_id 或可见用户范围。
     * - $startDate：开始日期字符串，合法时追加 close_time 起始条件。
     * - $endDate：结束日期字符串，合法时追加 close_time 截止条件。
     *
     * @param mixed $query UserTrade 查询构造器。
     * @param string|null $startDate 开始日期。
     * @param string|null $endDate 结束日期。
     * @return void
     */
    private function applyLegacyCloseDateFilter($query, string $startDate = null, string $endDate = null): void
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

    /**
     * symbolsByGroup 用于按 symbol_prices 品种组读取可统计交易品种。
     *
     * 字段兼容说明：
     * - groupColumn 表示品种组字段，兼容 sym_grp_id 与 group_id。
     * - symbolColumn 表示品种代码字段，兼容 sym_symbol 与 symbol。
     * - activeColumn 表示启用状态字段，兼容 voided 与 status。
     *
     * @return array<int, array<string, bool>> 按品种组 ID 和大写 symbol 索引的品种集合。
     */
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

    /**
     * isClosedTrade 用于判断 MT4 订单是否已平仓。
     *
     * @param UserTrade $trade 交易记录模型，读取 close_time 与 margin_rate 判断平仓状态。
     * @param string $closeTime 旧系统默认未平仓时间边界，例如 1970-01-01 00:00:00。
     * @return bool true=已平仓，false=仍为持仓或无效交易。
     */
    private function isClosedTrade(UserTrade $trade, string $closeTime): bool
    {
        return (string) $trade->close_time > $closeTime && (float) $trade->margin_rate != 0.0;
    }

    /**
     * isDepositComment 用于识别 MT4 入金备注。
     *
     * @param string $comment MT4 余额类交易备注。
     * @return bool true=备注命中旧 MT4 入金代码白名单。
     */
    private function isDepositComment(string $comment): bool
    {
        return $this->matchesLegacyBalanceComment($comment, self::LEGACY_DEPOSIT_COMMENT_CODES);
    }

    /**
     * isWithdrawalComment 用于识别 MT4 出金备注。
     *
     * @param string $comment MT4 余额类交易备注。
     * @return bool true=备注命中旧 MT4 出金代码白名单。
     */
    private function isWithdrawalComment(string $comment): bool
    {
        return $this->matchesLegacyBalanceComment($comment, self::LEGACY_WITHDRAWAL_COMMENT_CODES);
    }

    /**
     * isRebateComment 用于识别旧 MT4 代理返点备注。
     *
     * @param string $comment MT4 余额类交易备注。
     * @return bool true 表示命中 DBCN 返点代码，应计入 total_rebate 与代理净入金。
     */
    private function isRebateComment(string $comment): bool
    {
        return $this->matchesLegacyBalanceComment($comment, self::LEGACY_REBATE_COMMENT_CODES);
    }

    /**
     * 用旧 MySQL REGEXP 的固定代码语义识别 MT4 余额变动备注。
     *
     * @param string $comment MT4 原始备注，通常以旧机器代码和连字符开头。
     * @param array<int, string> $codes 当前入金或出金统计允许的旧代码集合。
     * @return bool 命中任一旧代码时返回 true；普通说明文字和未知代码返回 false。
     */
    private function matchesLegacyBalanceComment(string $comment, array $codes): bool
    {
        if ($comment === '' || $codes === []) {
            return false;
        }

        // 旧 SQL 使用 "DBAA|DBCT|..." REGEXP，不要求代码必须位于备注开头；这里保持同一匹配边界。
        $pattern = '/' . implode('|', array_map(static function (string $code): string {
            return preg_quote($code, '/');
        }, $codes)) . '/i';

        return preg_match($pattern, $comment) === 1;
    }

    /**
     * formatPositionSummary2Data 用于格式化旧前台本人汇总字段。
     *
     * 参数含义：
     * - $data：selfLoginIdSumData 聚合出的原始数值数组，包含 total_yuerj、total_yuecj、total_profit 等字段。
     *
     * @param array<string, float|int|string> $data 原始汇总字段。
     * @return array<string, string> 旧前台表格需要的两位小数字符串字段。
     */
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

    /**
     * openCountForUser 用于统计单个用户当前持仓数量。
     *
     * @param int $userId 业务用户 ID，对应 user_trades.user_id。
     * @param Request $request 当前 HTTP 请求对象，用于复用品种筛选。
     * @return int 当前持仓订单数量。
     */
    private function openCountForUser(int $userId, Request $request): int
    {
        return $this->openCountForScope([$userId], $request);
    }

    /**
     * openCountForScope 用于统计指定用户集合当前持仓数量。
     *
     * 参数含义：
     * - $userIds：需要统计持仓的业务用户 ID 列表。
     * - $request：当前 HTTP 请求对象，用于复用品种筛选。
     *
     * @param array<int, int|string> $userIds 业务用户 ID 列表。
     * @param Request $request 当前 HTTP 请求对象。
     * @return int 当前持仓订单数量。
     */
    private function openCountForScope(array $userIds, Request $request): int
    {
        $query = UserTrade::whereIn('user_id', array_values(array_unique(array_map('intval', $userIds))))
            ->open();

        FrontLegacyData::applySymbolFilter($query, $request);

        return $query->count();
    }

    /**
     * floatingProfitForUser 用于统计当前用户浮动盈亏。
     *
     * @param int $userId 业务用户 ID，对应 user_trades.user_id。
     * @param Request $request 当前 HTTP 请求对象，用于复用品种筛选。
     * @return string 两位小数字符串格式的浮动盈亏。
     */
    private function floatingProfitForUser(int $userId, Request $request): string
    {
        $value = UserTrade::where('user_id', $userId)
            ->open();

        FrontLegacyData::applySymbolFilter($value, $request);

        return number_format((float) $value->sum('profit'), 2, '.', '');
    }

    /**
     * agentLevelPayload 用于返回代理等级展示字段。
     *
     * 参数含义：
     * - $user：代理业务资料模型，读取 level 关联或 level_id 生成等级名称与等级序号。
     * - $rank：前台展示用等级序号，限制在 1 到 5 之间，避免异常 level_code 破坏页面样式。
     *
     * @param UserInfo $user 代理业务资料模型。
     * @return array<string, int|string> 代理等级展示字段。
     */
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

    /**
     * summaryChain 用于返回当前钻取层级链路。
     *
     * 参数含义：
     * - $agentId：当前登录代理业务用户 ID，作为链路根节点。
     * - $targetId：当前钻取目标代理业务用户 ID，作为链路末端节点。
     * - $ids：从 family_tree 和目标节点整理出的代理链路 ID 集合。
     *
     * @param int $agentId 当前登录代理业务用户 ID。
     * @param int $targetId 当前钻取目标代理业务用户 ID。
     * @return array<int, array<string, mixed>> 面包屑式代理链路。
     */
    private function summaryChain(int $agentId, int $targetId): array
    {
        $target = UserInfo::with('level')->where('user_id', $targetId)->first();
        if (!$target) {
            return [];
        }

        $ids = $this->parentSummaryChainIds($target);

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

    /**
     * 按 parent_id 向上补齐缺少 family_tree 时的持仓汇总面包屑链路。
     *
     * @param UserInfo $target 当前钻取目标代理资料。
     * @return array<int, int> 从上级根节点到目标代理的业务用户 ID 链。
     */
    private function parentSummaryChainIds(UserInfo $target): array
    {
        $ids = [(int) $target->user_id];
        $parentId = (int) $target->parent_id;
        $visited = [(int) $target->user_id => true];
        $depth = 0;

        while ($parentId > 0) {
            if (isset($visited[$parentId]) || $depth >= UserInfo::MAX_HIERARCHY_DEPTH) {
                return [];
            }

            $parent = UserInfo::where('user_id', $parentId)
                ->first(['user_id', 'parent_id', 'account_type']);
            if (!$parent || (int) $parent->account_type !== 1) {
                return [];
            }

            $ids[] = $parentId;
            $visited[$parentId] = true;
            $parentId = (int) $parent->parent_id;
            $depth++;
        }

        return array_reverse($ids);
    }

    /**
     * directAgentIds 用于读取直属代理 ID。
     *
     * 参数含义：
     * - $agentId：父级代理业务用户 ID。
     * - 优先读取 agent_descendants 中 descendant_type=1 且 is_direct=1 的代理关系。
     * - 当关系表缺失时回退 user_infos.parent_id，兼容旧项目导入数据。
     *
     * @param int $agentId 父级代理业务用户 ID。
     * @return array<int, int> 直属代理业务用户 ID 列表。
     */
    private function directAgentIds(int $agentId): array
    {
        return FrontLegacyData::userScopeIds($agentId, false, 1, true);
    }

    /**
     * search 用于兼容旧前台带筛选持仓搜索。
     *
     * 参数和变量含义：
     * - $request：当前 HTTP 请求对象，承载 date_from、date_to、symbol 等筛选条件。
     * - agentId 表示当前前台登录代理业务用户 ID。
     * - allDescendantIds 表示当前代理及全部后代用户 ID，用于限制统计范围。
     *
     * @param Request $request 当前 HTTP 请求对象。
     * @return JsonResponse 按用户聚合的持仓搜索结果。
     */
    public function search(Request $request): JsonResponse
    {
        $agentId = $this->legacyFrontUserId($request);
        if ($agentId <= 0) {
            return $this->legacyFrontAuthError($request);
        }

        $dateFrom = $request->input('date_from');
        $dateTo = $request->input('date_to');

        // allDescendantIds：当前代理和全部后代用户 ID，旧前台搜索只能在这个集合内聚合持仓。
        $allDescendantIds = FrontLegacyData::userScopeIds($agentId, true);

        $query = UserTrade::whereIn('user_trades.user_id', $allDescendantIds)
            ->join('user_infos', 'user_trades.user_id', '=', 'user_infos.user_id')
            ->selectRaw('user_trades.user_id, user_infos.user_name, SUM(volume) as total_volume, SUM(profit) as total_profit, COUNT(*) as count')
            ->groupBy('user_trades.user_id', 'user_infos.user_name');

        if ($dateFrom) $query->where('close_time', '>=', $dateFrom . ' 00:00:00');
        if ($dateTo) $query->where('close_time', '<=', $dateTo . ' 23:59:59');
        FrontLegacyData::applySymbolFilter($query, $request);

        $results = $query->paginate(FrontLegacyData::perPage($request));

        return $this->success($results, __('response.query_success'), ResponseCode::SUCCESS);
    }

    /**
     * subPositionSummary 用于返回当前代理下级用户持仓汇总。
     *
     * 参数和变量含义：
     * - $request：当前 HTTP 请求对象，承载 user_name 和分页参数。
     * - agentId 表示当前前台登录代理业务用户 ID。
     * - subAgents 表示当前代理可见的下级代理 ID 列表。
     *
     * @param Request $request 当前 HTTP 请求对象。
     * @return JsonResponse 下级代理持仓汇总响应。
     */
    public function subPositionSummary(Request $request): JsonResponse
    {
        $userLogin = $this->legacyFrontUserLogin($request);
        if (!$userLogin) {
            return $this->legacyFrontAuthError($request);
        }
        if ((int) $userLogin->account_type !== 1) {
            return $this->error(__('response.permission_denied'), ResponseCode::PERMISSION_DENIED);
        }

        $agentId = (int) $userLogin->user_id;
        // 现代直属代理接口不会提供 userPId；缺省时以当前登录代理为下钻父节点。
        $parentId = (int) $request->input('userPId', $request->input('target_id', $agentId));

        $allowedAgentIds = FrontLegacyData::userScopeIds($agentId, true, 1);
        if (!in_array($parentId, $allowedAgentIds, true)) {
            return $this->error(__('response.permission_denied'), ResponseCode::PERMISSION_DENIED);
        }

        $parent = UserInfo::where('user_id', $parentId)->where('account_type', 1)->first();
        if (!$parent) {
            return $this->error(__('response.validation_failed'), ResponseCode::VALIDATION_FAILED);
        }

        return $this->legacyAgentSummaryResponse(
            $request,
            $agentId,
            $this->directAgentIds($parentId),
            $parentId,
            'subAgentsSearch'
        );
    }

    /**
     * positionDetail 用于返回指定用户交易明细。
     *
     * 参数和变量含义：
     * - $request：当前 HTTP 请求对象，承载 user_id、ticket、orderId、status、symbol 和时间筛选。
     * - targetUserId 表示被查看交易明细的业务用户 ID。
     * - agentId 表示当前前台登录代理业务用户 ID。
     * - status 表示订单状态，1=历史平仓，0=当前持仓。
     *
     * @param Request $request 当前 HTTP 请求对象。
     * @return JsonResponse 指定用户交易明细响应。
     */
    public function positionDetail(Request $request): JsonResponse
    {
        $targetUserId = $request->input('user_id');

        if (!$targetUserId) {
            return $this->error(__('response.validation_failed'), ResponseCode::VALIDATION_FAILED);
        }
        $userLogin = $this->legacyFrontUserLogin($request);
        if (!$userLogin) {
            return $this->legacyFrontAuthError($request);
        }

        $agentId = (int) $userLogin->user_id;
        $isAgent = (int) $userLogin->account_type === 1;
        if (!$isAgent && (int) $targetUserId !== $agentId) {
            return $this->error(__('response.permission_denied'), ResponseCode::PERMISSION_DENIED);
        }
        if ($isAgent) {
            // isDescendant：校验目标用户是否属于当前代理网络，防止通过 user_id 越权查看其他用户交易。
            $isDescendant = in_array((int) $targetUserId, FrontLegacyData::userScopeIds($agentId, false), true);
            if (!$isDescendant && (int) $targetUserId !== $agentId) {
                return $this->error(__('response.permission_denied'), ResponseCode::PERMISSION_DENIED);
            }
        }

        $query = UserTrade::where('user_id', $targetUserId);
        
        FrontLegacyData::applySymbolFilter($query, $request);
        if ($request->has('ticket')) $query->where('ticket', $request->input('ticket'));
        if ($request->has('orderId')) $query->where('ticket', $request->input('orderId'));
        FrontLegacyData::applyDateTimeFilter($query, $request, 'open_time');
        if ($request->has('status')) {
             // status：1 表示历史平仓，0 表示当前持仓，兼容旧前台点击明细筛选。
             if ($request->status == 1) {
                 $query->closed();
             } else {
                 $query->open();
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
     * clickSearch 用于兼容旧前台按交易账号搜索代理持仓汇总入口。
     *
     * 参数和权限规则：
     * - userId 或 user_id 为空时回退当前登录代理，保持旧页面空条件搜索行为。
     * - 指定目标必须是当前代理本人或其后代代理，普通客户和外部代理不能作为代理汇总根节点。
     * - 返回值始终为代理汇总 list，不能错误委托给 positionDetail 返回订单 ticket 明细。
     *
     * @param Request $request 当前 HTTP 请求对象，承载 userId、userName、日期和分页参数。
     * @return JsonResponse 指定且已授权代理节点的旧 MT4 汇总响应。
     */
    public function clickSearch(Request $request): JsonResponse
    {
        $userLogin = $this->legacyFrontUserLogin($request);
        if (!$userLogin) {
            return $this->legacyFrontAuthError($request);
        }
        if ((int) $userLogin->account_type !== 1) {
            return $this->error(__('response.permission_denied'), ResponseCode::PERMISSION_DENIED);
        }

        $agentId = (int) $userLogin->user_id;
        $targetId = $this->requestedSummaryAgentId($request) ?? $agentId;
        $allowedAgentIds = FrontLegacyData::userScopeIds($agentId, true, 1);

        // 权限校验：目标代理必须是当前代理本人或其后代，防止用 userId/target_id 越权汇总外部代理网络。
        if (!in_array($targetId, $allowedAgentIds, true)) {
            return $this->error(__('response.permission_denied'), ResponseCode::PERMISSION_DENIED);
        }

        // 目标必须是代理账号（account_type=1），普通客户不能作为代理汇总根节点。
        $target = UserInfo::where('user_id', $targetId)->where('account_type', 1)->first();
        if (!$target) {
            return $this->error(__('response.validation_failed'), ResponseCode::VALIDATION_FAILED);
        }

        return $this->legacyAgentSummaryResponse(
            $request,
            $agentId,
            [$targetId],
            $targetId,
            'clickSearch'
        );
    }

    /**
     * 读取持仓汇总请求中的目标代理 ID。
     *
     * 兼容边界：
     * - 旧 Blade 使用 userId 或 user_id，现代接口使用 target_id。
     * - 三种参数在此统一解析，保证入口分派与 clickSearch 权限校验读取同一个目标，避免页面显示根代理但面包屑指向下级代理。
     *
     * @param Request $request 当前 HTTP 请求对象。
     * @return int|null 已提交的目标代理业务用户 ID；未提交任何目标时返回 null，由调用方回退当前登录代理。
     */
    private function requestedSummaryAgentId(Request $request): ?int
    {
        $legacyTargetId = FrontLegacyData::requestedUserId($request);
        if ($legacyTargetId !== null) {
            return $legacyTargetId;
        }

        return $request->filled('target_id') ? (int) $request->input('target_id') : null;
    }
}
