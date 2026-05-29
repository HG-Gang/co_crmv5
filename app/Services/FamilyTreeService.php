<?php

namespace App\Services;

use App\Models\UserInfo;
use App\Models\AgentDescendant;
use App\Models\UserTrade;
use App\Models\CommissionRecord;
use Illuminate\Support\Facades\DB;

class FamilyTreeService
{
    /**
     * Get the full ancestor chain for a user
     * 获取用户的所有祖先链
     *
     * @param int $userId
     * @return array
     */
    public function getAncestors(int $userId): array
    {
        $userInfo = UserInfo::where('user_id', $userId)->first();
        if (!$userInfo || empty($userInfo->family_tree)) {
            return [];
        }

        $treeIds = array_map('intval', explode(',', $userInfo->family_tree));
        // Remove self from the chain
        array_pop($treeIds);

        if (empty($treeIds)) {
            return [];
        }

        return UserInfo::whereIn('user_id', $treeIds)
            ->orderByRaw("FIELD(user_id, " . implode(',', $treeIds) . ")")
            ->get()
            ->toArray();
    }

    /**
     * Get all direct children of an agent
     * 获取代理商的所有直接下属
     *
     * @param int $agentId
     * @return array
     */
    public function getDirectChildren(int $agentId): array
    {
        return UserInfo::where('parent_id', $agentId)
            ->get()
            ->toArray();
    }

    /**
     * Get all descendants (direct + indirect) of an agent
     * 获取代理商的所有后代（直接+间接）
     *
     * @param int $agentId
     * @return array
     */
    public function getAllDescendants(int $agentId): array
    {
        return AgentDescendant::where('agent_id', $agentId)
            ->with('descendant')
            ->get()
            ->toArray();
    }

    /**
     * Get agent's sub-agent statistics
     * 获取代理商的下级代理统计信息
     *
     * @param int $agentId
     * @return array
     */
    public function getSubAgentStats(int $agentId): array
    {
        $directAgents = AgentDescendant::where('agent_id', $agentId)->where('is_direct', 1)->where('descendant_type', 1)->count();
        $allAgents = AgentDescendant::where('agent_id', $agentId)->where('descendant_type', 1)->count();
        $directCustomers = AgentDescendant::where('agent_id', $agentId)->where('is_direct', 1)->where('descendant_type', 2)->count();
        $allCustomers = AgentDescendant::where('agent_id', $agentId)->where('descendant_type', 2)->count();

        return [
            'direct_agents'      => $directAgents,
            'indirect_agents'    => $allAgents - $directAgents,
            'total_agents'       => $allAgents,
            'direct_customers'   => $directCustomers,
            'indirect_customers' => $allCustomers - $directCustomers,
            'total_customers'    => $allCustomers,
        ];
    }

    /**
     * Get comprehensive statistics for an agent within a date range
     * 获取代理商在指定日期范围内的综合统计数据
     *
     * @param int $agentId
     * @param string|null $dateFrom
     * @param string|null $dateTo
     * @return array
     */
    public function getAgentStats(int $agentId, ?string $dateFrom = null, ?string $dateTo = null): array
    {
        $descendantIds = AgentDescendant::where('agent_id', $agentId)->pluck('descendant_id');
        
        $tradeQuery = UserTrade::whereIn('user_id', $descendantIds);
        $commissionQuery = CommissionRecord::where('agent_id', $agentId);
        $userQuery = UserInfo::whereIn('user_id', $descendantIds);

        if ($dateFrom) {
            $tradeQuery->where('created_at', '>=', $dateFrom);
            $commissionQuery->where('created_at', '>=', $dateFrom);
            $userQuery->where('created_at', '>=', $dateFrom);
        }
        if ($dateTo) {
            $tradeQuery->where('created_at', '<=', $dateTo);
            $commissionQuery->where('created_at', '<=', $dateTo);
            $userQuery->where('created_at', '<=', $dateTo);
        }

        $tradeStats = $tradeQuery->selectRaw('SUM(volume) as total_volume, SUM(profit) as total_profit, COUNT(DISTINCT user_id) as active_users')->first();
        // commission_records 新表字段是 commission_amount，旧 commission 字段不存在；统一按新字段汇总。
        $totalCommission = $commissionQuery->sum('commission_amount');
        $newUsers = $userQuery->count();

        return [
            'total_volume'      => $tradeStats->total_volume ?? 0,
            'total_profit'      => $tradeStats->total_profit ?? 0,
            'total_commission'  => $totalCommission ?? 0,
            'active_users'      => $tradeStats->active_users ?? 0,
            'new_registrations' => $newUsers,
        ];
    }

    /**
     * Get full network tree structure with children
     * 获取包含子节点的完整网络树结构
     *
     * @param int $agentId
     * @return array
     */
    public function getNetworkTree(int $agentId): array
    {
        $root = UserInfo::where('user_id', $agentId)->first();
        if (!$root) return [];

        $allDescendants = AgentDescendant::where('agent_id', $agentId)
            ->with('descendant')
            ->get();

        $tree = [];
        $lookup = [];

        $rootNode = [
            'user_id'        => $root->user_id,
            'user_name'      => $root->user_name,
            'account_type'   => $root->account_type,
            'children'       => [],
            'children_count' => 0
        ];
        
        $lookup[$agentId] = &$rootNode;
        $tree[] = &$rootNode;

        foreach ($allDescendants as $d) {
            if (!$d->descendant) continue;
            $node = [
                'user_id'        => $d->descendant->user_id,
                'user_name'      => $d->descendant->user_name,
                'account_type'   => $d->descendant->account_type,
                'children'       => [],
                'children_count' => 0
            ];
            $lookup[$d->descendant->user_id] = $node;
        }

        foreach ($allDescendants as $d) {
            if (!$d->descendant) continue;
            $parentId = $d->descendant->parent_id;
            if (isset($lookup[$parentId])) {
                $lookup[$parentId]['children'][] = &$lookup[$d->descendant->user_id];
                $lookup[$parentId]['children_count']++;
            }
        }

        return $tree;
    }

    /**
     * Rebuild family_tree for a user and all their descendants
     * 为用户及其所有后代重建 family_tree
     *
     * @param int $userId
     * @return void
     */
    public function rebuildFamilyTree(int $userId): void
    {
        DB::transaction(function () use ($userId) {
            $userInfo = UserInfo::where('user_id', $userId)->first();
            if (!$userInfo) return;

            // Rebuild this user's tree
            if ($userInfo->parent_id) {
                $parentInfo = UserInfo::where('user_id', $userInfo->parent_id)->first();
                $newTree = $parentInfo
                    ? $parentInfo->family_tree . ',' . $userId
                    : (string) $userId;
            } else {
                $newTree = (string) $userId;
            }

            $userInfo->update(['family_tree' => $newTree]);

            // Recursively rebuild children
            $children = UserInfo::where('parent_id', $userId)->get();
            foreach ($children as $child) {
                $this->rebuildFamilyTree($child->user_id);
            }
        });
    }

    /**
     * Rebuild agent_descendants table for a specific agent
     * 为特定代理商重建 agent_descendants 表
     *
     * @param int $agentId
     * @return void
     */
    public function rebuildDescendants(int $agentId): void
    {
        DB::transaction(function () use ($agentId) {
            // Delete existing records
            AgentDescendant::where('agent_id', $agentId)->delete();

            // Find all users whose family_tree contains this agent
            $descendants = UserInfo::where('family_tree', 'LIKE', '%,' . $agentId . ',%')
                ->orWhere('family_tree', 'LIKE', $agentId . ',%')
                ->where('user_id', '!=', $agentId)
                ->get();

            foreach ($descendants as $desc) {
                $treeIds = array_map('intval', explode(',', $desc->family_tree));
                $agentPos = array_search($agentId, $treeIds);
                $descPos = array_search($desc->user_id, $treeIds);

                if ($agentPos === false || $descPos === false) continue;

                $depth = $descPos - $agentPos;
                $isDirect = ($desc->parent_id === $agentId) ? 1 : 0;

                AgentDescendant::create([
                    'agent_id'        => $agentId,
                    'descendant_id'   => $desc->user_id,
                    'descendant_type' => $desc->account_type,
                    'is_direct'       => $isDirect,
                    'depth'           => $depth,
                ]);
            }
        });
    }
}
