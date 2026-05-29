<?php

namespace App\Http\Controllers\Front;

use App\Models\AgentDescendant;
use App\Models\AgentLevel;
use App\Models\CommissionRecord;
use App\Models\DepositRecord;
use App\Models\GroupConfig;
use App\Models\SystemConfig;
use App\Models\TransApplyLog;
use App\Models\UserInfo;
use App\Models\UserLoginLog;
use App\Models\UserTrade;
use App\Models\WithdrawRecord;
use App\Services\FamilyTreeService;
use App\Constants\ResponseCode;
use App\Support\FrontLegacyData;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;

/**
 * Front Agent Management Controller
 * 前台代理管理控制器
 * 
 * Provides sub-agent and customer lists, and statistics for agents.
 * 为代理商提供下级代理、客户列表及统计数据。
 */
class AgentController extends FrontBaseController
{
    protected $familyTreeService;

    public function __construct(FamilyTreeService $familyTreeService)
    {
        $this->familyTreeService = $familyTreeService;
    }

    /**
     * List all sub-agents (direct and indirect)
     * 获取所有下级代理列表（直属和非直属）
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function subList(Request $request): JsonResponse
    {
        $user = $request->user('user');
        $agentId = $user->user_id;

        $query = AgentDescendant::where('agent_id', $agentId)
            ->where('descendant_type', 1) // 1=agent
            ->with(['descendant']);
        
        if ($request->has('direct_only') && $request->direct_only == 1) {
            $query->where('is_direct', 1);
        }

        if ($request->filled('user_name')) {
            $query->whereHas('descendant', function($q) use ($request) {
                $q->where('user_name', 'like', '%' . $request->user_name . '%');
            });
        }

        if ($request->filled('userId')) {
            $query->where('descendant_id', (int) $request->input('userId'));
        }
        if ($request->filled('username')) {
            $query->whereHas('descendant', function ($q) use ($request) {
                $q->where('user_name', 'like', '%' . $request->input('username') . '%');
            });
        }
        if ($request->filled('userstatus')) {
            $query->whereHas('descendant', function ($q) use ($request) {
                $q->where('auth_status', (int) $request->input('userstatus'));
            });
        }
        FrontLegacyData::applyCreatedAtFilter($query, $request);

        $descendantIds = (clone $query)->pluck('descendant_id')->map(function ($id) {
            return (int) $id;
        })->all();
        $totalRow = FrontLegacyData::financialTotalRowForUserIds($descendantIds, $request, 'user_id');

        $list = $query->paginate(FrontLegacyData::perPage($request));

        // Add hierarchy and trade stats for each agent
        $list->through(function ($d) {
            if ($d->descendant) {
                $hierarchyStats = $this->familyTreeService->getSubAgentStats($d->descendant_id);
                $tradeStats = $this->familyTreeService->getAgentStats($d->descendant_id);
                $financialStats = FrontLegacyData::userFinancialSummary($d->descendant, request(), true);
                return array_merge(
                    FrontLegacyData::userBasicAlias($d->descendant),
                    [
                        'depth' => $d->depth,
                        'is_direct' => $d->is_direct,
                        'descendant' => $d->descendant,
                        'stats' => array_merge($hierarchyStats, $tradeStats),
                        'agentsTotal' => (int) $hierarchyStats['total_agents'],
                        'accountTotal' => (int) $hierarchyStats['total_customers'],
                        'can_drill_agents' => (int) $hierarchyStats['total_agents'] > 0,
                        'can_drill_customers' => (int) $hierarchyStats['total_customers'] > 0,
                        'is_directly_sub' => (int) $d->is_direct === 1,
                    ],
                    $financialStats
                );
            }
            return $d;
        });

        return $this->success(
            FrontLegacyData::paginatedListResponse($list, $totalRow),
            'response.query_success',
            ResponseCode::SUCCESS
        );
    }

    /**
     * List all customers (direct and indirect)
     * 获取所有下级客户列表（直属和非直属）
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function customerList(Request $request): JsonResponse
    {
        $user = $request->user('user');
        $agentId = $user->user_id;

        $query = AgentDescendant::where('agent_id', $agentId)
            ->where('descendant_type', 2) // 2=customer
            ->with(['descendant']);
        
        if ($request->has('direct_only') && $request->direct_only == 1) {
            $query->where('is_direct', 1);
        }

        if ($request->filled('user_name')) {
            $query->whereHas('descendant', function($q) use ($request) {
                $q->where('user_name', 'like', '%' . $request->user_name . '%');
            });
        }

        if ($request->filled('userId')) {
            $query->where('descendant_id', (int) $request->input('userId'));
        }
        if ($request->filled('username')) {
            $query->whereHas('descendant', function ($q) use ($request) {
                $q->where('user_name', 'like', '%' . $request->input('username') . '%');
            });
        }
        if ($request->filled('userstatus')) {
            $query->whereHas('descendant', function ($q) use ($request) {
                $q->where('auth_status', (int) $request->input('userstatus'));
            });
        }
        FrontLegacyData::applyCreatedAtFilter($query, $request);

        $descendantIds = (clone $query)->pluck('descendant_id')->map(function ($id) {
            return (int) $id;
        })->all();
        $totalRow = FrontLegacyData::financialTotalRowForUserIds($descendantIds, $request, 'mt4_login');

        $list = $query->paginate(FrontLegacyData::perPage($request))
            ->through(function ($d) use ($request) {
                if (!$d->descendant) {
                    return $d;
                }

                return array_merge(
                    FrontLegacyData::userBasicAlias($d->descendant),
                    [
                        'depth' => $d->depth,
                        'is_direct' => $d->is_direct,
                        'descendant' => $d->descendant,
                        'comm_trans' => '',
                        'change_group' => '',
                    ],
                    FrontLegacyData::userFinancialSummary($d->descendant, $request, false)
                );
            });

        return $this->success(
            FrontLegacyData::paginatedListResponse($list, $totalRow),
            'response.query_success',
            ResponseCode::SUCCESS
        );
    }

    /**
     * Get agent statistics
     * 获取代理统计数据
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function statistics(Request $request): JsonResponse
    {
        $user = $request->user('user');
        $agentId = $user->user_id;
        
        $dateFrom = $request->input('date_from');
        $dateTo = $request->input('date_to');
        
        $stats = $this->familyTreeService->getAgentStats($agentId, $dateFrom, $dateTo);
        $hierarchy = $this->familyTreeService->getSubAgentStats($agentId);
        
        return $this->success(array_merge($stats, $hierarchy), 'response.query_success');
    }

    public function userDetail(Request $request): JsonResponse
    {
        $targetUserId = (int) $request->input('user_id', $request->input('userId'));
        if ($targetUserId <= 0) {
            return $this->error(__('response.validation_failed'), ResponseCode::VALIDATION_FAILED);
        }

        $currentUserId = (int) $request->user('user')->user_id;
        if (!$this->canViewUser($currentUserId, $targetUserId)) {
            return $this->error(__('response.permission_denied'), ResponseCode::PERMISSION_DENIED);
        }

        $user = UserInfo::with(['login', 'level', 'groupConfig', 'parent', 'auth'])
            ->where('user_id', $targetUserId)
            ->first();
        if (!$user) {
            return $this->error(__('response.user_not_found'), ResponseCode::USER_NOT_FOUND);
        }

        $rank = (int) ($user->level->level_code ?? $user->level_id ?? 5);
        if ($rank < 1 || $rank > 5) {
            $rank = 5;
        }
        $closedTrades = UserTrade::where('user_id', $targetUserId)->where('close_time', '>', '1971-01-01 00:00:00');
        $openTrades = UserTrade::where('user_id', $targetUserId)->where('close_time', '<=', '1971-01-01 00:00:00');

        return $this->success(array_merge(FrontLegacyData::userBasicAlias($user), [
            'account_type_text' => (int) $user->account_type === 1 ? __('register.agent') : __('register.customer'),
            'agent_level_rank' => $rank,
            'agent_level_name' => $user->level->name ?? ('Level ' . $rank),
            'group_name' => $user->groupConfig->name ?? '',
            'parent_name' => $user->parent->user_name ?? '',
            'country' => $user->country,
            'state' => $user->state,
            'city' => $user->city,
            'address' => $user->address,
            'id_card_no' => FrontLegacyData::maskIdCard((string) ($user->auth->id_card_no ?? '')),
            'auth_status_text' => (int) $user->auth_status === 1 ? __('front.status_verified') : __('front.status_unverified'),
            'total_deposit' => DepositRecord::where('user_id', $targetUserId)->sum('amount'),
            'total_withdraw' => WithdrawRecord::where('user_id', $targetUserId)->sum('apply_amount'),
            'total_rebate' => CommissionRecord::where('agent_id', $targetUserId)->sum('commission_amount'),
            'open_order_count' => (clone $openTrades)->count(),
            'closed_order_count' => (clone $closedTrades)->count(),
            'profit_7d' => (clone $closedTrades)->where('close_time', '>=', now()->subDays(7))->sum('profit'),
            'profit_15d' => (clone $closedTrades)->where('close_time', '>=', now()->subDays(15))->sum('profit'),
            'profit_30d' => (clone $closedTrades)->where('close_time', '>=', now()->subDays(30))->sum('profit'),
        ]), __('response.query_success'), ResponseCode::SUCCESS);
    }

    public function userLoginHistory(Request $request): JsonResponse
    {
        $targetUserId = (int) $request->input('user_id', $request->input('userId'));
        if ($targetUserId <= 0) {
            return $this->error(__('response.validation_failed'), ResponseCode::VALIDATION_FAILED);
        }

        $currentUserId = (int) $request->user('user')->user_id;
        if (!$this->canViewUser($currentUserId, $targetUserId)) {
            return $this->error(__('response.permission_denied'), ResponseCode::PERMISSION_DENIED);
        }

        $logs = UserLoginLog::where('user_id', $targetUserId)
            ->orderBy('created_at', 'desc')
            ->limit(20)
            ->get()
            ->map(function (UserLoginLog $log) {
                return [
                    'login_ip' => $log->login_ip,
                    'ip_location' => $log->ip_location,
                    'user_agent' => $log->user_agent,
                    'created_at' => FrontLegacyData::dateTime($log->created_at),
                ];
            })
            ->values();

        return $this->success([
            'user_id' => $targetUserId,
            'list' => $logs,
        ], __('response.query_success'), ResponseCode::SUCCESS);
    }

    private function canViewUser(int $currentUserId, int $targetUserId): bool
    {
        if ($currentUserId === $targetUserId) {
            return true;
        }

        return AgentDescendant::where('agent_id', $currentUserId)
            ->where('descendant_id', $targetUserId)
            ->exists();
    }

    /**
     * View/confirm agent level
     * 查看/确认代理等级信息
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function confirmLevel(Request $request): JsonResponse
    {
        $user = $request->user('user');
        $userInfo = UserInfo::where('user_id', $user->user_id)->first();

        if (!$userInfo) {
            return $this->error(__('auth.user_info_not_found'), ResponseCode::USER_NOT_FOUND);
        }

        $level = AgentLevel::find($userInfo->level_id);
        
        $summary = [
            'current_level'     => $level,
            'is_confirmed'      => $userInfo->is_agent_confirmed,
            'commission_rate'   => $userInfo->comm_rate,
            'available_levels'  => AgentLevel::orderBy('level_code')->get(),
        ];

        $agentIds = AgentDescendant::where('agent_id', (int) $userInfo->user_id)
            ->where('descendant_type', 1)
            ->where('is_direct', 1)
            ->pluck('descendant_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        if (!$agentIds) {
            $agentIds = UserInfo::where('parent_id', (int) $userInfo->user_id)
                ->where('account_type', 1)
                ->pluck('user_id')
                ->map(fn ($id) => (int) $id)
                ->all();
        }

        $listQuery = UserInfo::with(['login', 'level'])
            ->whereIn('user_id', array_values(array_unique($agentIds)))
            ->where('account_type', 1)
            ->where('is_agent_confirmed', 0);

        if ($request->filled('userId')) {
            $listQuery->where('user_id', (int) $request->input('userId'));
        }
        FrontLegacyData::applyCreatedAtFilter($listQuery, $request);

        $levels = AgentLevel::orderBy('level_code')->get();
        $list = $listQuery->orderBy('user_id')
            ->paginate(FrontLegacyData::perPage($request))
            ->through(function (UserInfo $agent) use ($levels) {
                $row = FrontLegacyData::userBasicAlias($agent);
                $rank = (int) ($agent->level->level_code ?? $agent->level_id ?? 5);
                if ($rank < 1 || $rank > 5) {
                    $rank = 5;
                }
                $currentRate = (float) $agent->comm_rate;

                $row['level_id'] = (int) $agent->level_id;
                $row['comm_rate'] = $currentRate;
                $row['agent_level_rank'] = $rank;
                $row['agent_level_name'] = $agent->level->name ?? ('Level ' . $rank);
                $currentLevelId = (int) $agent->level_id;
                $row['range_list'] = $levels->map(function ($level) use ($agent, $currentLevelId) {
                    $rate = (float) ($level->user_commission ?: $agent->comm_rate);
                    return [
                        'level_id' => (int) $level->id,
                        'level_name' => $level->name,
                        'prop' => $rate,
                        'user_min_prop' => (float) $agent->comm_rate,
                        'extra_val' => 0,
                        'def_gid' => $currentLevelId,
                        'choice_gid' => (int) $level->id,
                        'selected' => $currentLevelId > 0
                            ? (int) $level->id === $currentLevelId
                            : (string) $rate === (string) (float) $agent->comm_rate,
                    ];
                })->values();

                return $row;
            });

        return $this->success([
            'summary' => $summary,
            'list' => $list,
        ], 'response.query_success');
    }

    public function confirmLevelChange(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'userId' => 'required|integer',
            'comm_prop' => 'required|numeric',
            'agent_gId' => 'required|integer',
            'extra_val' => 'nullable|numeric',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), ResponseCode::VALIDATION_FAILED);
        }

        $user = $request->user('user');
        $agentId = (int) $user->user_id;
        $targetUserId = (int) $request->input('userId');
        $isSubAgent = AgentDescendant::where('agent_id', $agentId)
            ->where('descendant_id', $targetUserId)
            ->where('descendant_type', 1)
            ->where('is_direct', 1)
            ->exists();

        if (!$isSubAgent) {
            $isSubAgent = UserInfo::where('parent_id', $agentId)
                ->where('user_id', $targetUserId)
                ->where('account_type', 1)
                ->exists();
        }

        if (!$isSubAgent) {
            return $this->error(__('response.permission_denied'), ResponseCode::PERMISSION_DENIED);
        }

        $level = AgentLevel::find((int) $request->input('agent_gId'));
        if (!$level) {
            return $this->error(__('response.validation_failed'), ResponseCode::VALIDATION_FAILED);
        }

        $payload = [
            'is_agent_confirmed' => 1,
            'comm_rate' => (float) $request->input('comm_prop') + (float) $request->input('extra_val', 0),
            'level_id' => (int) $level->id,
        ];

        UserInfo::where('user_id', $targetUserId)->update($payload);

        return $this->success([], __('response.success'));
    }

    public function groupChangeList(Request $request): JsonResponse
    {
        $user = $request->user('user');
        $query = TransApplyLog::query()->where('applicant_id', (int) $user->user_id);

        if ($request->filled('userId')) {
            $query->where('user_id', (int) $request->input('userId'));
        }
        if ($request->filled('groupId')) {
            $query->where('group_id', (int) $request->input('groupId'));
        }
        FrontLegacyData::applyCreatedAtFilter($query, $request);

        $list = $query->orderBy('created_at', 'desc')
            ->paginate(FrontLegacyData::perPage($request))
            ->through(function (TransApplyLog $log) {
                return [
                    'id' => $log->id,
                    'trans_uid' => $log->user_id,
                    'trans_type_gid' => $log->group_name ?: $log->group_id,
                    'trans_apply_status' => $log->status,
                    'trans_apply_reason' => $log->apply_reason ?: $log->reject_reason,
                    'rec_crt_date' => FrontLegacyData::dateTime($log->created_at),
                    'rec_upd_date' => FrontLegacyData::dateTime($log->updated_at),
                ];
            });

        return $this->success($list, __('response.query_success'), ResponseCode::SUCCESS);
    }

    /**
     * Request customer group change
     * 申请更改客户所在交易组
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function groupChange(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'target_user_id' => 'required|integer',
            'new_group_id'   => 'required|integer',
            'reason'         => 'nullable|string|max:500',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), ResponseCode::VALIDATION_FAILED);
        }

        $user = $request->user('user');
        $agentId = $user->user_id;
        $targetUserId = (int) $request->target_user_id;
        $newGroupId = (int) $request->new_group_id;

        // Verify the target exists before creating the application.  The old CRM
        // accepted only users under the current agent tree, and this check keeps
        // the same ownership boundary in the rebuilt front office.
        $targetInfo = UserInfo::where('user_id', $targetUserId)->first();
        if (!$targetInfo) {
            return $this->error(__('response.user_not_found'), ResponseCode::USER_NOT_FOUND);
        }

        // Confirm the requested group is a real enabled group in the new schema.
        // trans_apply_logs stores both id and name so reviewers can see the
        // requested target even if the group name changes later.
        $group = GroupConfig::where('id', $newGroupId)->where('is_enabled', 1)->first();
        if (!$group) {
            return $this->error(__('response.invalid_group'), ResponseCode::VALIDATION_FAILED);
        }

        // Verify target user is descendant.
        $isDescendant = AgentDescendant::where('agent_id', $agentId)->where('descendant_id', $targetUserId)->exists();
        if (!$isDescendant && $targetUserId != $agentId) {
            return $this->error(__('response.permission_denied'), ResponseCode::PERMISSION_DENIED);
        }

        $operatorName = $user->userInfo ? $user->userInfo->user_name : (string) $agentId;
        $reason = $request->input('reason', '');

        // Base columns follow the current co_crmv5 table.  Optional columns added
        // from the old hank_zl_data transfer-apply structure are filled only when
        // present, so the API remains compatible while migrations are being rolled
        // out between environments.
        $applyData = [
            'user_id'        => $targetUserId,
            'group_id'       => $newGroupId,
            'group_name'     => $group->name,
            'applicant_id'   => $agentId,
            'applicant_name' => $operatorName,
            'status'         => 0,
            'reject_reason'  => '',
            'created_by'     => $operatorName,
        ];

        if (Schema::hasColumn('trans_apply_logs', 'origin_group_id')) {
            $applyData['origin_group_id'] = (int) $targetInfo->group_id;
        }
        if (Schema::hasColumn('trans_apply_logs', 'apply_reason')) {
            $applyData['apply_reason'] = $reason;
        } else {
            // Older deployments did not have a dedicated application-reason field.
            // Keeping the reason here prevents data loss until the migration runs.
            $applyData['reject_reason'] = $reason;
        }

        $apply = TransApplyLog::create($applyData);

        return $this->success($apply, __('response.success'));
    }
}
