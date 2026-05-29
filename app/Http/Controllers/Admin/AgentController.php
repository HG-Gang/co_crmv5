<?php

namespace App\Http\Controllers\Admin;

use App\Models\UserInfo;
use App\Models\AgentDescendant;
use App\Constants\ResponseCode;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/**
 * Agent Management Controller
 * 代理管理控制器
 */
class AgentController extends AdminBaseController
{
    /**
     * List agents only (account_type=1)
     * 获取所有代理列表 (account_type=1)
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        $page = $request->input('page', 1);
        $perPage = $request->input('per_page', 15);

        $query = UserInfo::where('account_type', 1)->with('login');

        if ($request->filled('agent_id')) {
            $query->where('user_id', $request->agent_id);
        }

        if ($request->filled('user_name')) {
            $query->where('user_name', 'LIKE', "%{$request->user_name}%");
        }

        $agents = $query->orderByDesc('user_id')->paginate($perPage, ['*'], 'page', $page);

        return $this->success($agents, __('admin.agent_list_fetched'));
    }

    /**
     * Get agent detail with hierarchy info
     * 获取代理详情及其层级信息
     *
     * @param int $agentId
     * @return \Illuminate\Http\JsonResponse
     */
    public function show($agentId)
    {
        $agent = UserInfo::with(['login', 'level'])->where('user_id', $agentId)->where('account_type', 1)->first();
        if (!$agent) {
            return $this->error(__('admin.agent_not_found'), ResponseCode::USER_NOT_FOUND);
        }

        return $this->success($agent, __('admin.agent_detail_fetched'));
    }

    /**
     * Get all direct/indirect sub-agents and customers
     * 获取所有直接和间接下级代理及客户
     *
     * @param int $agentId
     * @return \Illuminate\Http\JsonResponse
     */
    public function descendants($agentId)
    {
        try {
            $descendants = AgentDescendant::where('agent_id', $agentId)
                ->with(['descendantInfo'])
                ->get();

            return $this->success($descendants, __('admin.agent_descendants_fetched'));
        } catch (\Exception $e) {
            return $this->error($e->getMessage(), ResponseCode::SERVER_ERROR);
        }
    }

    /**
     * Update agent level
     * 更新代理等级
     *
     * @param Request $request
     * @param int $agentId
     * @return \Illuminate\Http\JsonResponse
     */
    public function updateLevel(Request $request, $agentId)
    {
        try {
            $agent = UserInfo::where('user_id', $agentId)->where('account_type', 1)->first();
            if (!$agent) {
                return $this->error(__('admin.agent_not_found'), ResponseCode::USER_NOT_FOUND);
            }

            $validator = Validator::make($request->all(), [
                'level' => 'required|integer',
            ]);

            if ($validator->fails()) {
                return $this->error($validator->errors()->first(), ResponseCode::VALIDATION_FAILED);
            }

            $agent->update(['agent_level' => $request->level]);

            return $this->success([], __('admin.agent_level_updated'));
        } catch (\Exception $e) {
            return $this->error($e->getMessage(), ResponseCode::SERVER_ERROR);
        }
    }

    /**
     * Update agent commission rate
     * 更新代理佣金率
     *
     * @param Request $request
     * @param int $agentId
     * @return \Illuminate\Http\JsonResponse
     */
    public function updateCommission(Request $request, $agentId)
    {
        try {
            $agent = UserInfo::where('user_id', $agentId)->where('account_type', 1)->first();
            if (!$agent) {
                return $this->error(__('admin.agent_not_found'), ResponseCode::USER_NOT_FOUND);
            }

            $validator = Validator::make($request->all(), [
                'comm_rate' => 'required|numeric|min:0|max:1',
            ]);

            if ($validator->fails()) {
                return $this->error($validator->errors()->first(), ResponseCode::VALIDATION_FAILED);
            }

            $agent->update(['comm_rate' => $request->comm_rate]);

            return $this->success([], __('admin.agent_commission_updated'));
        } catch (\Exception $e) {
            return $this->error($e->getMessage(), ResponseCode::SERVER_ERROR);
        }
    }
}
