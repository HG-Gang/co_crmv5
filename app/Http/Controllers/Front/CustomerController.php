<?php

namespace App\Http\Controllers\Front;

use App\Models\AgentDescendant;
use App\Models\UserInfo;
use App\Models\UserTrade;
use App\Constants\ResponseCode;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class CustomerController extends FrontBaseController
{
    /**
     * 获取当前代理的所有客户（直属或非直属） | List current agent's direct and indirect customers
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function myCustomers(Request $request): JsonResponse
    {
        $userLogin = $request->user('user');
        $agentId = $userLogin->user_id;

        $query = AgentDescendant::where('agent_id', $agentId)
            ->where('descendant_type', 2)
            ->with(['descendant' => function($q) {
                $q->select('user_id', 'user_name', 'account_type', 'parent_id', 'created_at');
            }]);

        if ($request->has('direct_only') && $request->direct_only == 1) {
            $query->where('is_direct', 1);
        }

        if ($request->has('user_name')) {
            $query->whereHas('descendant', function($q) use ($request) {
                $q->where('user_name', 'like', '%' . $request->user_name . '%');
            });
        }

        $customers = $query->paginate($request->input('per_page', 15));

        // 为每个客户添加交易统计 | Add trade stats for each customer
        $customers->through(function ($d) {
            if ($d->descendant) {
                $stats = UserTrade::where('user_id', $d->descendant_id)
                    ->selectRaw('SUM(volume) as total_volume, SUM(profit) as total_profit, COUNT(*) as trade_count')
                    ->first();
                $d->trade_stats = $stats;
            }
            return $d;
        });

        return $this->success($customers, __('response.query_success'), ResponseCode::SUCCESS);
    }

    /**
     * 客户统计摘要 | Customer statistics summary
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function stats(Request $request): JsonResponse
    {
        $userLogin = $request->user('user');
        $agentId = $userLogin->user_id;

        $descendantIds = AgentDescendant::where('agent_id', $agentId)
            ->where('descendant_type', 2)
            ->pluck('descendant_id');

        $totalCustomers = count($descendantIds);
        
        // 统计活跃客户（近一个月有交易） | Active customers (traded in last month)
        $activeCount = UserTrade::whereIn('user_id', $descendantIds)
            ->where('close_time', '>', now()->subMonth())
            ->distinct('user_id')
            ->count();
            
        // 合计交易量 | Total volume
        $totalVolume = UserTrade::whereIn('user_id', $descendantIds)->sum('volume');

        return $this->success([
            'total_customers'    => $totalCustomers,
            'active_customers'   => $activeCount,
            'inactive_customers' => $totalCustomers - $activeCount,
            'total_volume'       => $totalVolume,
        ], __('response.query_success'), ResponseCode::SUCCESS);
    }
}
