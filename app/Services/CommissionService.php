<?php

namespace App\Services;

use App\Models\UserInfo;
use App\Models\UserTrade;
use App\Models\CommissionRecord;
use App\Models\SpreadConfig;
use App\Support\FrontLegacyData;
use Illuminate\Support\Facades\DB;

class CommissionService
{
    /**
     * Calculate real-time commission for current agent
     */
    public function calculateRealTimeCommission(int $agentId): array
    {
        $descendantIds = DB::table('agent_descendants')
            ->where('agent_id', $agentId)
            ->pluck('descendant_id');

        // Open positions (close_time = '1970-01-01 00:00:00')
        $openPositions = UserTrade::whereIn('user_id', $descendantIds)
            ->where('close_time', '1970-01-01 00:00:00')
            ->get();

        $agentInfo = UserInfo::where('user_id', $agentId)->first();
        $totalCommission = 0;
        $breakdown = [];

        foreach ($openPositions as $position) {
            $user = UserInfo::where('user_id', $position->user_id)->first();
            if (!$user) continue;

            $volumeLots = $position->volume / 100;
            [$spreadValue, $spreadRatio] = $this->spreadForAgent($agentInfo);

            $commissionPerLot = $spreadValue * $spreadRatio;
            
            // Calculate commission based on: agent_comm_rate - sub_user_comm_rate
            // If the user is a direct sub-agent, use their rate. 
            // If user is further down, we need to find the rate difference at each level.
            // Simplified logic: agent gets difference between their rate and the sub-user's rate.
            $commDiff = ($agentInfo->comm_rate - $user->comm_rate) / 100;
            $positionComm = $volumeLots * $commissionPerLot * $commDiff;

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
     * Calculate settlement summary for date range
     */
    public function calculateSettlement(int $agentId, array $dateRange): array
    {
        $descendantIds = DB::table('agent_descendants')
            ->where('agent_id', $agentId)
            ->pluck('descendant_id');

        $closedPositions = UserTrade::whereIn('user_id', $descendantIds)
            ->whereBetween('close_time', [$dateRange[0] . ' 00:00:00', $dateRange[1] . ' 23:59:59'])
            ->where('settlement_status', 0)
            ->get();

        $agentInfo = UserInfo::where('user_id', $agentId)->first();
        $totalCommission = 0;
        $volumeTotal = 0;

        foreach ($closedPositions as $position) {
            $user = UserInfo::where('user_id', $position->user_id)->first();
            if (!$user) continue;

            $volumeLots = $position->volume / 100;
            [$spreadValue, $spreadRatio] = $this->spreadForAgent($agentInfo);

            $commissionPerLot = $spreadValue * $spreadRatio;
            $commDiff = ($agentInfo->comm_rate - $user->comm_rate) / 100;
            $positionComm = $volumeLots * $commissionPerLot * $commDiff;

            if ($positionComm > 0) {
                $totalCommission += $positionComm;
                $volumeTotal += $volumeLots;
                
                // Usually we'd create individual records or one aggregated record
            }
        }

        if ($totalCommission > 0) {
            $record = CommissionRecord::create([
                'unique_id'         => md5($agentId . $dateRange[0] . $dateRange[1] . time()),
                'agent_id'          => $agentId,
                'parent_id'         => $agentInfo->parent_id,
                'agent_volume'      => $volumeTotal,
                'commission_amount' => $totalCommission,
                'real_amount'       => $totalCommission,
                'date_range'        => implode(' - ', $dateRange),
                'settle_status'     => 1, // Pending
                'remarks'           => 'DBCN-' . $agentId . '-#' . $dateRange[0] . '-' . $dateRange[1],
                'created_by'        => 'system',
            ]);
            return ['status' => 'success', 'record' => $record];
        }

        return ['status' => 'no_commission'];
    }

    /**
     * Settle a commission record
     */
    public function settleCommission(int $recordId): bool
    {
        $record = CommissionRecord::find($recordId);
        if (!$record || $record->settle_status === 2) {
            return false;
        }

        return $record->update([
            'settle_status' => 2, // Settled
            'updated_by'    => 'system'
        ]);
    }

    public function orderCommissionDetails(UserTrade $trade, int $viewerAgentId): array
    {
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

        $chainIds = $this->familyChainIds($trader);
        $viewerIndex = array_search($viewerAgentId, $chainIds, true);
        if ($viewerIndex === false) {
            return [];
        }

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
            $rateDiff = max(0, ((float) $agent->comm_rate - (float) ($next->comm_rate ?? 0)) / 100);
            [$spread, $spreadRatio] = $this->spreadForAgent($agent);
            $calculatedAmount = $volumeLots * $spread * $spreadRatio * $rateDiff;

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

        return $payableRows ?: $rows;
    }

    private function chainNode($userId, $agents, UserInfo $trader): ?UserInfo
    {
        $userId = (int) $userId;
        if ($userId === (int) $trader->user_id) {
            return $trader;
        }

        return $agents->get($userId) ?: UserInfo::where('user_id', $userId)->first();
    }

    private function familyChainIds(UserInfo $user): array
    {
        $ids = array_values(array_filter(array_map('intval', explode(',', (string) $user->family_tree))));

        if (!$ids) {
            $ids = [(int) $user->user_id];
            $parentId = (int) $user->parent_id;
            while ($parentId > 0 && !in_array($parentId, $ids, true)) {
                array_unshift($ids, $parentId);
                $parentId = (int) UserInfo::where('user_id', $parentId)->value('parent_id');
            }
        }

        if ((int) end($ids) !== (int) $user->user_id) {
            $ids[] = (int) $user->user_id;
        }

        return array_values(array_unique($ids));
    }

    private function spreadForAgent(?UserInfo $agent): array
    {
        if (!$agent) {
            return [0.0, 1.0];
        }

        $config = SpreadConfig::where('agent_group_id', $agent->group_id)
            ->where('status', 1)
            ->first();
        if (!$config) {
            $config = SpreadConfig::where('agent_group_id', $agent->group_id)->first();
        }

        $spread = (float) ($config->spread ?? 0);
        if ($spread <= 0) {
            $spread = (float) DB::table('group_configs')->where('id', $agent->group_id)->value('radix');
        }

        $spreadRatio = (float) ($config->spread_ratio ?? 1);
        if ($spreadRatio <= 0) {
            $spreadRatio = 1.0;
        }

        return [$spread, $spreadRatio];
    }

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

    private function settleStatusText(int $status): string
    {
        return $status === 2
            ? __('front.status_settled')
            : __('front.status_pending_settle');
    }
}
