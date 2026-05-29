<?php

namespace App\Http\Controllers\Front;

use App\Models\CommissionRecord;
use App\Models\UserInfo;
use App\Models\AgentDescendant;
use App\Services\CommissionService;
use App\Constants\ResponseCode;
use App\Support\FrontLegacyData;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Front Commission Management Controller
 * 前台返佣管理控制器
 * 
 * Handles real-time commission calculation, history, and transfers.
 * 处理实时返佣计算、历史记录及返佣转账。
 */
class CommissionController extends FrontBaseController
{
    protected $commissionService;

    public function __construct(CommissionService $commissionService)
    {
        $this->commissionService = $commissionService;
    }

    /**
     * 计算当前代理的实时佣金 | Calculate real-time commission for current agent
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function realTime(Request $request): JsonResponse
    {
        $userLogin = $request->user('user');
        $agentId = $userLogin->user_id;

        $descendantIds = FrontLegacyData::userScopeIds((int) $agentId, false);
        $query = \App\Models\UserTrade::whereIn('user_id', $descendantIds)
            ->with(['user.login', 'user.level'])
            ->where('close_time', '1970-01-01 00:00:00');

        FrontLegacyData::applyDateTimeFilter($query, $request, 'open_time');
        if ($request->filled('userId')) {
            $query->where('user_id', (int) $request->input('userId'));
        }
        if ($request->filled('orderId')) {
            $query->where('ticket', $request->input('orderId'));
        }

        $totalQuery = clone $query;
        $totalRow = FrontLegacyData::rebateTotalRow($totalQuery);

        $commissionDetails = (bool) $request->boolean('detail_commission', false);
        $list = $query->orderBy('open_time', 'desc')
            ->paginate(FrontLegacyData::perPage($request))
            ->through(function ($trade) use ($agentId, $commissionDetails) {
                $row = FrontLegacyData::tradeAliasRow($trade);
                $row['modify_time'] = $row['modify_time'] ?: $row['open_time'];
                $profit = (float) ($row['profit'] ?? 0);
                $row['profit_gain'] = FrontLegacyData::money(max($profit, 0));
                $row['profit_loss'] = FrontLegacyData::money(abs(min($profit, 0)));
                $row['profit_net'] = FrontLegacyData::money($row['profit_gain'] - $row['profit_loss']);
                $row['user_info'] = $this->userDetail($trade->user);
                $row['commission_details'] = $commissionDetails ? $this->commissionService->orderCommissionDetails($trade, (int) $agentId) : [];

                return $row;
            });

        $rows = $list->getCollection();
        $profitGain = FrontLegacyData::money($rows->sum('profit_gain'));
        $profitLoss = FrontLegacyData::money($rows->sum('profit_loss'));
        $profitNet = FrontLegacyData::money($profitGain - $profitLoss);

        $comm = [
            'total' => $totalRow['total_commission'] ?? 0,
        ];
        $comm['total_commission'] = $totalRow['total_commission'] ?? 0;
        $comm['total_volume'] = $rows->sum('volume_lots');
        $comm['profit_gain'] = $profitGain;
        $comm['profit_loss'] = $profitLoss;
        $comm['profit_net'] = $profitNet;
        $comm['total_profit'] = $profitNet;
        $comm['list'] = $list;
        $comm['totalRow'] = array_merge($totalRow, [
            'total_commission' => $comm['total_commission'] ?? 0,
            'total_volume' => $comm['total_volume'] ?? 0,
            'profit_gain' => $comm['profit_gain'] ?? 0,
            'profit_loss' => $comm['profit_loss'] ?? 0,
            'profit_net' => $comm['profit_net'] ?? 0,
        ]);
        $comm['summary'] = $comm['totalRow'];

        return $this->success($comm, 'response.query_success', ResponseCode::SUCCESS);
    }

    /**
     * 获取已结算佣金历史 | Get settled commission history
     * 
     * @param Request $request
     * @return JsonResponse
     */
    private function userDetail(?UserInfo $user): array
    {
        if (!$user) {
            return [];
        }

        $level = $user->relationLoaded('level') ? $user->level : $user->level;
        $rank = (int) ($level->level_code ?? $user->level_id ?? 5);
        if ($rank < 1 || $rank > 5) {
            $rank = 5;
        }

        return array_merge(FrontLegacyData::userBasicAlias($user), [
            'account_type_text' => (int) $user->account_type === 1 ? __('register.agent') : __('register.customer'),
            'agent_level_rank' => $rank,
            'agent_level_name' => $level->name ?? ('Level ' . $rank),
        ]);
    }

    /**
     * Get settled commission history.
     */
    public function history(Request $request): JsonResponse
    {
        $userLogin = $request->user('user');
        $agentId = $userLogin->user_id;

        $dateFrom = $request->input('date_from');
        $dateTo = $request->input('date_to');

        $query = CommissionRecord::where('agent_id', $agentId);

        // created_at is stored as a 10-digit Unix timestamp in the rebuilt
        // schema, so date filters must be converted before querying.
        if (!$dateFrom) $dateFrom = FrontLegacyData::dateFrom($request);
        if (!$dateTo) $dateTo = FrontLegacyData::dateTo($request);
        if ($dateFrom) $query->where('created_at', '>=', strtotime($dateFrom . ' 00:00:00'));
        if ($dateTo) $query->where('created_at', '<=', strtotime($dateTo . ' 23:59:59'));
        if ($request->filled('orderId')) {
            $query->where('mt4_order_id', (int) $request->input('orderId'));
        }
        if ($request->filled('dataType')) {
            $query->where('data_type', $request->input('dataType'));
        }

        $totalRow = FrontLegacyData::commissionTotalRow($query);

        $records = $query->orderBy('created_at', 'desc')
            ->paginate(FrontLegacyData::perPage($request))
            ->through(function (CommissionRecord $record) {
                $row = $record->toArray();
                $row['profit'] = FrontLegacyData::money($record->commission_amount);
                $row['commission_amount'] = FrontLegacyData::money($record->commission_amount);
                $row['returned_amount'] = FrontLegacyData::money($record->returned_amount);
                $row['real_amount'] = FrontLegacyData::money($record->real_amount);
                $row['agent_profit'] = FrontLegacyData::money($record->agent_profit);
                $row['agent_volume'] = FrontLegacyData::lots($record->agent_volume);
                $row['comment'] = $record->remarks;
                $row['order_no'] = $record->mt4_order_id ?: '';
                $row['settle_status_text'] = (int) $record->settle_status === 2
                    ? __('front.status_settled')
                    : __('front.status_pending_settle');
                $row['created_time'] = FrontLegacyData::dateTime($record->created_at);
                $row['modify_time'] = FrontLegacyData::dateTime($record->updated_at ?: $record->created_at);

                return $row;
            });

        return $this->success(
            FrontLegacyData::paginatedListResponse($records, $totalRow),
            __('response.query_success'),
            ResponseCode::SUCCESS
        );
    }

    /**
     * Commission transfer to sub-agent
     * 佣金转账给下级代理
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function transfer(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'sub_agent_id' => 'required|integer',
            'amount'       => 'required|numeric|min:0.01',
            'remark'       => 'nullable|string|max:500',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), ResponseCode::VALIDATION_FAILED);
        }

        $userLogin = $request->user('user');
        $agentId = $userLogin->user_id;
        $subAgentId = $request->input('sub_agent_id');
        $amount = (float)$request->input('amount');

        // Verify sub-agent belongs to current agent
        $isSubAgent = AgentDescendant::where('agent_id', $agentId)
            ->where('descendant_id', $subAgentId)
            ->where('descendant_type', 1)
            ->exists();
        
        if (!$isSubAgent) {
            return $this->error(__('response.permission_denied'), ResponseCode::PERMISSION_DENIED);
        }

        $agentInfo = UserInfo::where('user_id', $agentId)->first();
        if (!$agentInfo) {
            return $this->error(__('auth.user_info_not_found'), ResponseCode::USER_NOT_FOUND);
        }
        if ($agentInfo->total_funds < $amount) {
            return $this->error(__('response.insufficient_balance'), ResponseCode::INSUFFICIENT_BALANCE);
        }

        $createdBy = $userLogin->userInfo ? $userLogin->userInfo->user_name : (string)$agentId;

        // Handle transfer (update both account balances and write a commission flow record).
        // The rebuilt commission_records table stores monetary values in commission_amount / real_amount,
        // so transfer data is written into those fields instead of non-existent legacy amount/type columns.
        try {
            DB::transaction(function () use ($agentId, $subAgentId, $amount, $request, $createdBy) {
                $orderNo = 'CT' . date('YmdHis') . Str::upper(Str::random(6));
                $remark = trim((string) $request->input('remark', ''));
                // Deduct from agent
                UserInfo::where('user_id', $agentId)->decrement('total_funds', $amount);
                // Add to sub-agent
                UserInfo::where('user_id', $subAgentId)->increment('total_funds', $amount);
                
                // Receiver deposit side: DBCT, sender withdrawal side: WBCT.
                CommissionRecord::create([
                    'unique_id'          => md5('DBCT-' . $agentId . '-' . $subAgentId . '-' . $orderNo),
                    'agent_id'           => $subAgentId,
                    'parent_id'          => $agentId,
                    'commission_amount'  => $amount,
                    'returned_amount'    => $amount,
                    'real_amount'        => $amount,
                    'settle_status'      => 2,
                    'data_type'          => 'transfer',
                    'manual_reason'      => $remark,
                    'remarks'            => 'DBCT-' . $subAgentId . '-#' . $orderNo . ($remark !== '' ? ';' . $remark : ''),
                    'created_by'         => $createdBy,
                ]);

                CommissionRecord::create([
                    'unique_id'          => md5('WBCT-' . $agentId . '-' . $subAgentId . '-' . $orderNo),
                    'agent_id'           => $agentId,
                    'parent_id'          => $subAgentId,
                    'commission_amount'  => -$amount,
                    'returned_amount'    => -$amount,
                    'real_amount'        => -$amount,
                    'settle_status'      => 2,
                    'data_type'          => 'transfer',
                    'manual_reason'      => $remark,
                    'remarks'            => 'WBCT-' . $agentId . '-#' . $orderNo . ($remark !== '' ? ';' . $remark : ''),
                    'created_by'         => $createdBy,
                ]);
            });
            
            return $this->success([], 'response.success');
        } catch (\Exception $e) {
            return $this->error($e->getMessage(), ResponseCode::INTERNAL_ERROR);
        }
    }
}
