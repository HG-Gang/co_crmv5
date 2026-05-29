<?php

namespace App\Http\Controllers\Front;

use App\Models\DepositRecord;
use App\Models\WithdrawRecord;
use App\Models\CommissionRecord;
use App\Models\UserInfo;
use App\Constants\ResponseCode;
use App\Support\FrontLegacyData;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

/**
 * Front Account Flow Controller
 * 前台账户流水控制器
 * 
 * Lists all account transactions including deposits, withdrawals, and commissions.
 * 列出所有账户交易，包括充值、提现和佣金。
 */
class FlowController extends FrontBaseController
{
    /**
     * List all account transactions (deposits, withdrawals, commissions)
     * 获取所有账户交易流水列表
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function accountFlow(Request $request): JsonResponse
    {
        $userLogin = $request->user('user');
        $userInfo = UserInfo::where('user_id', $userLogin->user_id)->first();

        if (!$userInfo) {
            return $this->error(__('auth.user_info_not_found'), ResponseCode::USER_NOT_FOUND);
        }

        $agentId = (int) $userInfo->user_id;
        $flowType = $request->input('flow_type', $request->input('flowType', 'all'));
        $dateFrom = $request->filled('date_from') ? strtotime($request->input('date_from') . ' 00:00:00') : null;
        $dateTo = $request->filled('date_to') ? strtotime($request->input('date_to') . ' 23:59:59') : null;

        if (!$dateFrom) {
            $dateFrom = FrontLegacyData::timestampFrom($request);
        }
        if (!$dateTo) {
            $dateTo = FrontLegacyData::timestampTo($request);
        }

        if ($flowType !== 'all') {
            return $this->success($this->typedFlow($request, $agentId, $flowType), 'response.query_success');
        }

        // Query deposits
        $deposits = DB::table('deposit_records')
            ->select('user_id', 'user_name', 'amount', 'actual_amount', 'remarks', 'created_at', DB::raw("'deposit' as type"), 'local_order_no as order_no', 'status')
            ->where('user_id', $agentId);

        // Query withdrawals
        $withdrawals = DB::table('withdraw_records')
            ->select('user_id', 'user_name', 'apply_amount as amount', 'actual_amount', 'reject_reason as remarks', 'created_at', DB::raw("'withdraw' as type"), 'local_order_no as order_no', 'status')
            ->where('user_id', $agentId);

        // Query commissions.
        // commission_records uses the rebuilt schema field commission_amount, so it is aliased
        // to amount here to keep the front account-flow response consistent with deposits and withdrawals.
        $commissions = DB::table('commission_records')
            ->select('agent_id as user_id', DB::raw("'' as user_name"), 'commission_amount as amount', 'real_amount as actual_amount', 'remarks', 'created_at', DB::raw("'commission' as type"), DB::raw("NULL as order_no"), DB::raw("'02' as status")) // Assume 02 is completed
            ->where('agent_id', $agentId);

        // All three source tables use integer timestamps, so one timestamp
        // range is applied to each subquery before unioning the final flow list.
        if ($dateFrom) {
            $deposits->where('created_at', '>=', $dateFrom);
            $withdrawals->where('created_at', '>=', $dateFrom);
            $commissions->where('created_at', '>=', $dateFrom);
        }
        if ($dateTo) {
            $deposits->where('created_at', '<=', $dateTo);
            $withdrawals->where('created_at', '<=', $dateTo);
            $commissions->where('created_at', '<=', $dateTo);
        }

        $depositTotal = FrontLegacyData::depositTotalRow($deposits);
        $withdrawTotal = FrontLegacyData::withdrawTotalRow($withdrawals);
        $commissionTotal = FrontLegacyData::commissionTotalRow($commissions);
        $totalRow = [
            'order_no' => 'total',
            'user_id' => 'total',
            'amount' => FrontLegacyData::money(($depositTotal['amount'] ?? 0) + ($withdrawTotal['apply_amount'] ?? 0) + ($commissionTotal['commission_amount'] ?? 0)),
            'actual_amount' => FrontLegacyData::money(($depositTotal['actual_amount'] ?? 0) + ($withdrawTotal['actual_amount'] ?? 0) + ($commissionTotal['real_amount'] ?? 0)),
        ];

        // Combine and paginate
        $query = $deposits->union($withdrawals)->union($commissions);

        $results = DB::query()->fromSub($query, 'flows')
            ->orderBy('created_at', 'desc')
            ->paginate(FrontLegacyData::perPage($request));

        return $this->success(
            FrontLegacyData::paginatedListResponse($results, $totalRow),
            'response.query_success'
        );
    }

    private function typedFlow(Request $request, int $agentId, string $flowType)
    {
        $scope = $this->flowScopeUserIds($agentId, $flowType);
        $isWithdraw = in_array($flowType, ['withdraw', 'withdraw_apply', 'direct_withdraw', 'direct_agents_withdraw'], true);
        $query = $isWithdraw ? DB::table('withdraw_records') : DB::table('deposit_records');

        if ($scope) {
            $query->whereIn('user_id', $scope);
        } else {
            $query->whereRaw('1 = 0');
        }

        $requestedUserId = FrontLegacyData::requestedUserId($request);
        if ($requestedUserId !== null) {
            if (in_array($requestedUserId, $scope, true)) {
                $query->where('user_id', $requestedUserId);
            } else {
                $query->whereRaw('1 = 0');
            }
        }

        FrontLegacyData::applyCreatedAtFilter($query, $request);

        if ($isWithdraw && $request->filled('withdraw_source')) {
            $query->where('bank_name', 'like', '%' . $request->input('withdraw_source') . '%');
        }

        $totalRow = $isWithdraw
            ? FrontLegacyData::withdrawTotalRow($query)
            : FrontLegacyData::depositTotalRow($query);

        $paginator = $query->orderBy('created_at', 'desc')
            ->paginate(FrontLegacyData::perPage($request));

        $paginator->getCollection()->transform(function ($row) use ($isWithdraw, $flowType) {
            if ($isWithdraw) {
                return [
                    'order_no' => $row->local_order_no,
                    'userId' => $row->user_id,
                    'userName' => $row->user_name,
                    'withdrawalType' => FrontLegacyData::withdrawStatusText($row->status),
                    'withdrawalType2' => $row->bank_name,
                    'withdrawalActProfit' => FrontLegacyData::money($row->actual_amount ?: $row->apply_amount),
                    'withdrawalDate' => FrontLegacyData::dateTime($row->created_at),
                    'applyamount' => FrontLegacyData::money($row->apply_amount),
                    'actdraw' => FrontLegacyData::money($row->actual_amount),
                    'drawpoundage' => FrontLegacyData::money($row->fee),
                    'drawrate' => $row->exchange_rate,
                    'drawbankno' => $row->bank_no,
                    'drawbankclass' => $row->bank_name,
                    'applystatus' => FrontLegacyData::withdrawStatusText($row->status),
                    'applyremark' => $row->reject_reason,
                    'rec_crt_date' => FrontLegacyData::dateTime($row->created_at),
                    'directdrawalComment' => $row->reject_reason,
                    'directdrawalActProfit' => FrontLegacyData::money($row->actual_amount ?: $row->apply_amount),
                    'directdrawalModifyTime' => FrontLegacyData::dateTime($row->updated_at ?: $row->created_at),
                    'flow_type' => $flowType,
                ];
            }

            return [
                'order_no' => $row->local_order_no,
                'userId' => $row->user_id,
                'depositType' => $row->channel_name,
                'depositComment' => $row->remarks,
                'depositActProfit' => FrontLegacyData::money($row->actual_amount ?: $row->amount),
                'modify_time' => FrontLegacyData::dateTime($row->payment_time ?: $row->updated_at ?: $row->created_at),
                'directType' => $row->channel_name,
                'directProfit' => FrontLegacyData::money($row->actual_amount ?: $row->amount),
                'directComment' => $row->remarks,
                'directModifyTime' => FrontLegacyData::dateTime($row->payment_time ?: $row->updated_at ?: $row->created_at),
                'flow_type' => $flowType,
            ];
        });

        return FrontLegacyData::paginatedListResponse($paginator, $totalRow);
    }

    private function flowScopeUserIds(int $agentId, string $flowType): array
    {
        if (in_array($flowType, ['deposit', 'withdraw', 'withdraw_apply'], true)) {
            return [$agentId];
        }

        if (in_array($flowType, ['direct_deposit', 'direct_withdraw'], true)) {
            return FrontLegacyData::userScopeIds($agentId, false, 2, true);
        }

        if (in_array($flowType, ['direct_agents_deposit', 'direct_agents_withdraw'], true)) {
            return FrontLegacyData::userScopeIds($agentId, false, 1, true);
        }

        return FrontLegacyData::userScopeIds($agentId, true);
    }
}
