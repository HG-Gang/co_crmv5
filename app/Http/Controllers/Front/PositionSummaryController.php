<?php

namespace App\Http\Controllers\Front;

use App\Models\AgentDescendant;
use App\Models\UserInfo;
use App\Models\UserTrade;
use App\Constants\ResponseCode;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class PositionSummaryController extends FrontBaseController
{
    /**
     * 当前代理下级持仓概览 | Return position summary overview for current agent
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        $userLogin = $request->user('user');
        $agentId = $userLogin->user_id;

        // 获取直属下级（代理或客户） | Get direct descendants (agents or customers)
        $descendants = AgentDescendant::where('agent_id', $agentId)
            ->where('is_direct', 1)
            ->with(['descendant' => function($q) {
                $q->select('user_id', 'user_name', 'account_type');
            }])
            ->get();

        $summary = [];
        foreach ($descendants as $d) {
            if (!$d->descendant) continue;

            // 获取该下级节点及其所有后代的ID | Get this descendant and all their own descendants' IDs
            $subDescendantIds = AgentDescendant::where('agent_id', $d->descendant_id)->pluck('descendant_id')->toArray();
            $allNodeIds = array_merge([$d->descendant_id], $subDescendantIds);

            // 统计该节点的合计持仓（仅未平仓订单）| Aggregate positions for this node (open trades only)
            $stats = UserTrade::whereIn('user_id', $allNodeIds)
                ->selectRaw('SUM(volume) as total_volume, SUM(profit) as total_profit, COUNT(*) as open_positions_count')
                ->where('close_time', '1970-01-01 00:00:00')
                ->first();

            $summary[] = [
                'user_id'      => $d->descendant_id,
                'user_name'    => $d->descendant->user_name,
                'account_type' => $d->descendant->account_type,
                'total_volume' => $stats->total_volume ?? 0,
                'total_profit' => $stats->total_profit ?? 0,
                'open_count'   => $stats->open_positions_count ?? 0,
            ];
        }

        return $this->success($summary, __('response.query_success'), ResponseCode::SUCCESS);
    }

    /**
     * 带筛选的持仓搜索 | Search position summary with filters
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function search(Request $request): JsonResponse
    {
        $userLogin = $request->user('user');
        $agentId = $userLogin->user_id;

        $dateFrom = $request->input('date_from');
        $dateTo = $request->input('date_to');
        $symbol = $request->input('symbol');

        // 获取所有后代及自身ID | Get all descendants and self IDs
        $allDescendantIds = AgentDescendant::where('agent_id', $agentId)->pluck('descendant_id')->toArray();
        $allDescendantIds[] = $agentId;

        $query = UserTrade::whereIn('user_trades.user_id', $allDescendantIds)
            ->join('user_infos', 'user_trades.user_id', '=', 'user_infos.user_id')
            ->selectRaw('user_trades.user_id, user_infos.user_name, SUM(volume) as total_volume, SUM(profit) as total_profit, COUNT(*) as count')
            ->groupBy('user_trades.user_id', 'user_infos.user_name');

        if ($dateFrom) $query->where('close_time', '>=', $dateFrom . ' 00:00:00');
        if ($dateTo) $query->where('close_time', '<=', $dateTo . ' 23:59:59');
        if ($symbol) $query->where('symbol', $symbol);

        $results = $query->paginate($request->input('per_page', 15));

        return $this->success($results, __('response.query_success'), ResponseCode::SUCCESS);
    }

    /**
     * 下级代理持仓搜索 | Search sub-agent position summary
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function subSearch(Request $request): JsonResponse
    {
        $userLogin = $request->user('user');
        $agentId = $userLogin->user_id;

        // 获取所有下级代理 | Get all sub-agents
        $subAgents = AgentDescendant::where('agent_id', $agentId)
            ->where('descendant_type', 1)
            ->pluck('descendant_id');

        $query = UserInfo::whereIn('user_id', $subAgents);
        
        if ($request->has('user_name')) {
            $query->where('user_name', 'like', '%' . $request->user_name . '%');
        }

        $results = $query->paginate($request->input('per_page', 15));

        return $this->success($results, __('response.query_success'), ResponseCode::SUCCESS);
    }

    /**
     * 特定用户的交易详情（点击搜索） | Show trade details for a specific user
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function clickSearch(Request $request): JsonResponse
    {
        $targetUserId = $request->input('user_id');
        $userLogin = $request->user('user');
        $agentId = $userLogin->user_id;

        if (!$targetUserId) {
            return $this->error(__('response.validation_failed'), ResponseCode::VALIDATION_FAILED);
        }

        // 验证用户是否在当前代理的网络中 | Verify the user is in current agent's network
        $isDescendant = AgentDescendant::where('agent_id', $agentId)->where('descendant_id', $targetUserId)->exists();
        if (!$isDescendant && $targetUserId != $agentId) {
            return $this->error(__('response.permission_denied'), ResponseCode::PERMISSION_DENIED);
        }

        $query = UserTrade::where('user_id', $targetUserId);
        
        if ($request->has('symbol')) $query->where('symbol', $request->input('symbol'));
        if ($request->has('ticket')) $query->where('ticket', $request->input('ticket'));
        if ($request->has('status')) {
             // 1: 已平仓, 0: 未平仓
             if ($request->status == 1) {
                 $query->where('close_time', '>', '1970-01-01 00:00:00');
             } else {
                 $query->where('close_time', '1970-01-01 00:00:00');
             }
        }

        $trades = $query->orderBy('close_time', 'desc')
            ->orderBy('open_time', 'desc')
            ->paginate($request->input('per_page', 15));

        return $this->success($trades, __('response.query_success'), ResponseCode::SUCCESS);
    }
}
