<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/16
 * Time: 04:47
 */

namespace App\Services;

use App\Models\UserInfo;
use App\Models\UserTrade;
use App\Models\CommissionRecord;
use App\Models\SpreadConfig;
use App\Support\FrontLegacyData;
use Illuminate\Support\Facades\DB;

/**
 * 返佣计算服务。
 *
 * 文件功能：
 * - 为前台代理提供实时返佣（未平仓持仓预估返佣）与历史返佣（已平仓结算返佣）计算。
 * - 根据代理等级、点差配置、交易品种分组计算每笔订单的返佣金额。
 *
 * 适用场景：
 * - 前台代理查看"实时返佣"列表（基于当前持仓计算预估返佣）。
 * - 定时任务结算已平仓订单返佣（CMD=6 入金写入 MT4 并记录 user_trades）。
 *
 * 入参例子：
 * - calculateRealTimeCommission(agentId=600123)
 * - calculateSettlementCommission(userId=600456, trades=已平仓订单集合)
 *
 * 返回值：
 * - 成功时返回 ['total' => 返佣总额(float), 'breakdown' => [{trade_id, volume_lots, commission, 上级链条}]]。
 * - 无下级或无代理信息时返回 total=0。
 *
 * 异常或失败场景：
 * - 代理信息不存在时返回 total=0（表示该代理无返佣资格）。
 * - 点差配置缺失时按默认返佣比例计算。
 *
 * 金额与精度：
 * - MT4 原始 volume 以 0.01 为单位，手数 = volume / 100。
 * - 单笔返佣 = 手数 × 点差 × 点差比例 × 相邻代理返佣比例差，对外统一 round(…, 2)。
 * - 相邻层比例差非正时记 0（max(0, …)），不产生负向冲抵。
 */
class CommissionService
{
    /**
     * 实时计算当前代理的返佣（基于未平仓持仓的预估返佣）。
     *
     * @param int $agentId 代理商业务 user_id，用于解析下级用户与读取代理资料。
     * @return array{total: float, breakdown: array<int, array<string, mixed>>} total=返佣总额；无下级、无代理资料或无返佣订单时返回 0。
     */
    public function calculateRealTimeCommission(int $agentId): array
    {
        // 先解析代理可见的全部下级用户，后续持仓按这批用户过滤。
        $descendantIds = FrontLegacyData::userScopeIds($agentId, false);

        // 无下级时不产生任何返佣。
        if (!$descendantIds) {
            return [
                'total' => 0,
                'breakdown' => [],
            ];
        }

        // 只取未平仓持仓计算预估返佣。
        $openPositions = UserTrade::whereIn('user_id', $descendantIds)
            ->open()
            ->get();

        // 代理资料缺失表示该用户不是有效代理，直接返回 0。
        $agentInfo = UserInfo::where('user_id', $agentId)->first();
        if (!$agentInfo) {
            return [
                'total' => 0,
                'breakdown' => [],
            ];
        }

        // 批量读取下单用户资料，避免循环内逐条查询。
        $users = UserInfo::whereIn('user_id', $openPositions->pluck('user_id')->unique()->all())
            ->get()
            ->keyBy('user_id');
        $totalCommission = 0;
        $breakdown = [];

        foreach ($openPositions as $position) {
            $user = $users->get($position->user_id);
            if (!$user) continue;

            // MT4 volume 以 0.01 为单位，转换为手数。
            $volumeLots = $position->volume / 100;
            $positionComm = $this->commissionAmountForTrade($position, $agentInfo, $user);

            // 只汇总正返佣订单，不产生负向冲抵。
            if ($positionComm > 0) {
                $totalCommission += $positionComm;
                $breakdown[] = [
                    'ticket'   => $position->ticket,
                    'user_id'  => $position->user_id,
                    'user_name' => $user->user_name,
                    'symbol'   => $position->symbol,
                    'volume'   => $volumeLots,
                    'commission' => round($positionComm, 2),
                ];
            }
        }

        return [
            'total'     => round($totalCommission, 2),
            'breakdown' => $breakdown,
        ];
    }

    /**
     * 结算指定日期区间内已平仓且未结算订单的返佣，并写入 commission_records 汇总记录。
     *
     * 阶段：过滤可结算订单 → 逐笔计算返佣 → 存在正返佣时创建汇总结算记录（settle_status=1 待结算）。
     *
     * @param int $agentId 代理商业务 user_id。
     * @param array<int, string> $dateRange 两元素日期数组 [开始日期, 结束日期]，格式 Y-m-d。
     * @return array<string, mixed> status=success 时附带 record；代理缺失或无返佣时返回 status=no_commission。
     */
    public function calculateSettlement(int $agentId, array $dateRange): array
    {
        // 只结算区间内已平仓且 settlement_status=0 的订单，避免重复入账。
        $descendantIds = FrontLegacyData::userScopeIds($agentId, false);

        $closedPositions = UserTrade::whereIn('user_id', $descendantIds)
            ->closed()
            ->whereBetween('close_time', [$dateRange[0] . ' 00:00:00', $dateRange[1] . ' 23:59:59'])
            ->where('settlement_status', 0)
            ->get();

        // 代理资料缺失视为无返佣资格。
        $agentInfo = UserInfo::where('user_id', $agentId)->first();
        if (!$agentInfo) {
            return ['status' => 'no_commission'];
        }

        $totalCommission = 0;
        $volumeTotal = 0;

        foreach ($closedPositions as $position) {
            $user = UserInfo::where('user_id', $position->user_id)->first();
            if (!$user) continue;

            $volumeLots = $position->volume / 100;
            $positionComm = $this->commissionAmountForTrade($position, $agentInfo, $user);

            // 与实时返佣相同的单笔计算逻辑，只累计正金额与手数。
            if ($positionComm > 0) {
                $totalCommission += $positionComm;
                $volumeTotal += $volumeLots;
            }
        }

        if ($totalCommission > 0) {
            // 按代理与日期区间生成汇总记录；unique_id 追加 time()，同一区间重复结算也会生成独立记录。
            $record = CommissionRecord::create([
                'unique_id'         => md5($agentId . $dateRange[0] . $dateRange[1] . time()),
                'agent_id'          => $agentId,
                'parent_id'         => $agentInfo->parent_id,
                'agent_volume'      => $volumeTotal,
                'commission_amount' => $totalCommission,
                'real_amount'       => $totalCommission,
                'date_range'        => implode(' - ', $dateRange),
                'settle_status'     => 1, // 1=待结算；后台确认后由 settleCommission() 置为 2=已结算。
                'remarks'           => 'DBCN-' . $agentId . '-#' . $dateRange[0] . '-' . $dateRange[1],
                'created_by'        => 'system',
            ]);
            return ['status' => 'success', 'record' => $record];
        }

        return ['status' => 'no_commission'];
    }

    /**
     * 将返佣记录标记为已结算。
     *
     * @param int $recordId commission_records 主键。
     * @return bool true=已更新为已结算；记录不存在或已是已结算状态时返回 false。
     */
    public function settleCommission(int $recordId): bool
    {
        $record = CommissionRecord::find($recordId);
        // 已是终态（2=已结算）的记录不允许重复结算。
        if (!$record || $record->settle_status === 2) {
            return false;
        }

        return $record->update([
            'settle_status' => 2, // 2=已结算终态
            'updated_by'    => 'system'
        ]);
    }

    /**
     * 返回某笔订单在指定代理视角下的逐层返佣明细。
     *
     * 阶段：校验查看者身份 → 校验交易人在其链内（防越权）→ 逐层计算比例差 → 优先回显已入账账本金额。
     *
     * @param UserTrade $trade 目标交易订单。
     * @param int $viewerAgentId 当前查看的代理业务 user_id。
     * @return array<int, array<string, mixed>> 逐层返佣行；非代理身份、交易人缺失或不在其链内时返回空数组。
     */
    public function orderCommissionDetails(UserTrade $trade, int $viewerAgentId): array
    {
        // 只有代理身份能查看返佣明细。
        $viewer = UserInfo::where('user_id', $viewerAgentId)->first();
        if (!$viewer || (int) $viewer->account_type !== 1) {
            return [];
        }

        $trader = $trade->relationLoaded('user')
            ? $trade->user
            : UserInfo::where('user_id', $trade->user_id)->first();

        if (!$trader) {
            return [];
        }

        // 交易人不在该代理的上游链中时无权查看，防止跨代理越权读取返佣。
        $chainIds = $this->familyChainIds($trader);
        $viewerIndex = array_search($viewerAgentId, $chainIds, true);
        if ($viewerIndex === false) {
            return [];
        }

        // 链上只取代理节点参与返佣分配。
        $agents = UserInfo::with('level')
            ->whereIn('user_id', $chainIds)
            ->where('account_type', 1)
            ->get()
            ->keyBy('user_id');

        $rows = [];
        $payableRows = [];
        $volumeLots = FrontLegacyData::lots($trade->volume);

        for ($index = $viewerIndex; $index < count($chainIds) - 1; $index++) {
            $agentId = (int) $chainIds[$index];
            /** @var UserInfo|null $agent */
            $agent = $agents->get($agentId);
            if (!$agent) {
                continue;
            }

            $next = $this->chainNode($chainIds[$index + 1], $agents, $trader);
            // 相邻层比例差为负时记 0，不产生倒扣。
            $rateDiff = max(0, ((float) $agent->comm_rate - (float) ($next->comm_rate ?? 0)) / 100);
            [$spread, $spreadRatio] = $this->spreadForAgent($agent);
            $calculatedAmount = $volumeLots * $spread * $spreadRatio * $rateDiff;

            // 已入账的订单优先回显账本金额，避免界面与账本口径不一致。
            $record = CommissionRecord::where('mt4_order_id', $trade->ticket)
                ->where('agent_id', $agentId)
                ->orderByDesc('created_at')
                ->first();

            $amount = $record ? (float) $record->commission_amount : $calculatedAmount;
            $settleStatus = (int) ($record->settle_status ?? 1);
            $row = [
                'agent_id' => $agentId,
                'user_id' => $agentId,
                'agent_name' => (string) $agent->user_name,
                'user_name' => (string) $agent->user_name,
                'agent_level' => $agent->level->name ?? ('Level ' . $this->agentRank($agent)),
                'agent_level_rank' => $this->agentRank($agent),
                'commission_amount' => FrontLegacyData::money($amount),
                'rebate_ratio' => round($rateDiff * 100, 2) . '%',
                'rebate_ratio_value' => round($rateDiff * 100, 2),
                'spread' => FrontLegacyData::money($spread),
                'spread_ratio' => round((float) $spreadRatio, 4),
                'volume_lots' => $volumeLots,
                'rebate_time' => $record ? FrontLegacyData::dateTime($record->updated_at ?: $record->created_at) : '',
                'settle_status' => $settleStatus,
                'settle_status_text' => $this->settleStatusText($settleStatus),
                'is_paid' => $settleStatus === 2 ? 1 : 0,
            ];

            $rows[] = $row;
            if ($record || $amount > 0) {
                $payableRows[] = $row;
            }
        }

        // 有实际金额或已入账的层级行优先返回；否则返回完整层级行供查看。
        return $payableRows ?: $rows;
    }

    /**
     * 计算单个交易给指定代理带来的返佣金额。
     *
     * 公式：手数 × 点差 × 点差比例 ×（当前代理比例 − 直属下级比例），差额非正时返回 0.00。
     *
     * @param UserTrade $trade 目标交易。
     * @param UserInfo $agent 收款代理。
     * @param UserInfo|null $trader 交易用户；缺省时按订单 user_id 查询。
     * @return float 该代理的返佣金额。
     */
    private function commissionAmountForTrade(UserTrade $trade, UserInfo $agent, UserInfo $trader = null): float
    {
        $trader = $trader ?: UserInfo::where('user_id', $trade->user_id)->first();
        if (!$trader) {
            return 0.0;
        }

        $chainIds = $this->familyChainIds($trader);
        $agentIndex = array_search((int) $agent->user_id, $chainIds, true);
        // 代理不在交易人链中，或已是最底层（无下级可返），返回 0。
        if ($agentIndex === false || !isset($chainIds[$agentIndex + 1])) {
            return 0.0;
        }

        $nextId = (int) $chainIds[$agentIndex + 1];
        // 直属下级是交易人时直接取交易人比例，否则查库读取。
        $nextRate = $nextId === (int) $trader->user_id
            ? (float) $trader->comm_rate
            : (float) UserInfo::where('user_id', $nextId)->value('comm_rate');

        [$spreadValue, $spreadRatio] = $this->spreadForAgent($agent);
        $volumeLots = $trade->volume / 100;
        // 相邻层比例差非正时记 0，不产生负向冲抵。
        $rateDiff = max(0, ((float) $agent->comm_rate - $nextRate) / 100);

        return $volumeLots * $spreadValue * $spreadRatio * $rateDiff;
    }

    /**
     * 解析链路中下一个节点的代理资料。
     *
     * 直属下级是交易人时直接复用已加载的 $trader,避免重复查库;否则优先取批量加载的 agents 集合,缺失时回退单条查询。
     *
     * @param mixed $userId 链路中的下一个 user_id。
     * @param \Illuminate\Support\Collection<int, UserInfo> $agents 已批量加载的链上代理集合。
     * @param UserInfo $trader 交易人资料。
     * @return UserInfo|null 命中节点资料;查询不到时返回 null。
     */
    private function chainNode($userId, $agents, UserInfo $trader): ?UserInfo
    {
        $userId = (int) $userId;
        if ($userId === (int) $trader->user_id) {
            return $trader;
        }

        return $agents->get($userId) ?: UserInfo::where('user_id', $userId)->first();
    }

    /**
     * 计算用户从最上层祖先到自身的代理链 user_id 序列。
     *
     * 始终沿 parent_id 逐级向上回溯；family_tree 是派生快照，不能决定返佣归属。
     *
     * @param UserInfo $user 目标用户。
     * @return array<int, int> 从最上层祖先到自身、无重复的 user_id 数组。
     */
    private function familyChainIds(UserInfo $user): array
    {
        $userId = (int) $user->user_id;
        $ids = [$userId];
        $visited = [$userId => true];
        $parentId = (int) $user->parent_id;

        while ($parentId > 0) {
            if (isset($visited[$parentId]) || count($ids) - 1 >= UserInfo::MAX_HIERARCHY_DEPTH) {
                return [];
            }
            $visited[$parentId] = true;

            $parent = UserInfo::where('user_id', $parentId)->first(['user_id', 'parent_id', 'account_type']);
            if (!$parent || (int) $parent->account_type !== 1) {
                return [];
            }

            array_unshift($ids, (int) $parent->user_id);
            $parentId = (int) $parent->parent_id;
        }

        return $ids;
    }

    /**
     * 读取代理组的点差与点差比例。
     *
     * 回退顺序：启用配置 → 任意状态配置 → 组基数（group_configs.radix）；比例无效时按 1.0 处理。
     *
     * @param UserInfo|null $agent 目标代理；null 时返回零配置。
     * @return array{0: float, 1: float} [点差值, 点差比例]。
     */
    private function spreadForAgent(UserInfo $agent = null): array
    {
        if (!$agent) {
            return [0.0, 1.0];
        }

        $config = SpreadConfig::where('agent_group_id', $agent->group_id)
            ->where('status', 1)
            ->first();
        // 启用配置缺失时回退到同组任意状态配置，保持旧项目口径。
        if (!$config) {
            $config = SpreadConfig::where('agent_group_id', $agent->group_id)->first();
        }

        $spread = (float) ($config->spread ?? 0);
        // 组基数与点差同单位，作为点差缺失时的历史兼容回退。
        if ($spread <= 0) {
            $spread = (float) DB::table('group_configs')->where('id', $agent->group_id)->value('radix');
        }

        $spreadRatio = (float) ($config->spread_ratio ?? 1);
        if ($spreadRatio <= 0) {
            $spreadRatio = 1.0;
        }

        return [$spread, $spreadRatio];
    }

    /**
     * 把代理等级映射到 1~5 档位。
     *
     * @param UserInfo $agent 目标代理。
     * @return int 等级档位，缺失或越界时回退为 5。
     */
    private function agentRank(UserInfo $agent): int
    {
        $rank = (int) ($agent->level->level_code ?? $agent->level_id ?? 5);
        if ($rank < 1) {
            return 5;
        }
        if ($rank > 5) {
            return 5;
        }

        return $rank;
    }

    /**
     * 结算状态文案。
     *
     * @param int $status 1=待结算，2=已结算。
     * @return string 语言包文案。
     */
    private function settleStatusText(int $status): string
    {
        return $status === 2
            ? __('front.status_settled')
            : __('front.status_pending_settle');
    }
}
