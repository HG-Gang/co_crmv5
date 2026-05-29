<?php

namespace App\Http\Controllers\Front;

use App\Constants\ResponseCode;
use App\Models\CancelApply;
use App\Models\UserTrade;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;

/**
 * Front account cancellation controller.
 *
 * This controller rebuilds the old front-office cancellation workflow:
 * users submit a cancellation request, the system blocks unsafe requests when
 * funds or open positions still exist, and admins later approve or reject it.
 */
class CancelController extends FrontBaseController
{
    /**
     * Submit an account cancellation request for the current front user.
     *
     * The request is stored in cancel_applies with status 0 (pending).  The
     * optional reason is saved to cancel_remark when the new migration exists,
     * matching hank_zl_data.cancel_apply.cancel_remark from the old project.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function apply(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'reason' => 'nullable|string|max:500',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), ResponseCode::VALIDATION_FAILED);
        }

        $userLogin = $request->user('user');
        $userInfo = $userLogin->userInfo;

        if (!$userInfo) {
            return $this->error(__('auth.user_info_not_found'), ResponseCode::USER_NOT_FOUND);
        }

        // Prevent duplicate pending requests.  Admin actions change status away
        // from 0, so historical approved/rejected rows do not block a new request.
        $exists = CancelApply::where('user_id', $userInfo->user_id)
            ->where('status', 0)
            ->exists();
        if ($exists) {
            return $this->error(__('response.cancel_apply_exists'), ResponseCode::CANCEL_APPLY_EXISTS);
        }

        // Open orders must be closed before cancellation, matching the old CRM's
        // risk rule that a live trading account cannot be removed.
        $hasOpen = UserTrade::where('user_id', $userInfo->user_id)
            ->where('close_time', '1970-01-01 00:00:00')
            ->exists();
        if ($hasOpen) {
            return $this->error(__('response.risk_rate_exceeded'), ResponseCode::RISK_RATE_EXCEEDED);
        }

        // The rebuilt schema stores old user_money/equity style balances as
        // total_funds and equity.  Both must be zero to avoid orphaning funds.
        $totalFunds = (float) $userInfo->total_funds;
        $equity = (float) $userInfo->equity;
        if ($totalFunds > 0 || $equity > 0) {
            return $this->error(__('response.operation_not_allowed'), ResponseCode::OPERATION_NOT_ALLOWED);
        }

        $reason = $request->input('reason', '');
        $applyData = [
            'user_id'    => $userInfo->user_id,
            'user_name'  => $userInfo->user_name,
            'status'     => 0,
            'created_by' => $userInfo->user_name,
        ];

        if (Schema::hasColumn('cancel_applies', 'cancel_remark')) {
            $applyData['cancel_remark'] = $reason;
            $applyData['reject_reason'] = '';
        } else {
            // Compatibility fallback for databases that have not run the
            // cancel_remark migration yet.
            $applyData['reject_reason'] = $reason;
        }

        $apply = CancelApply::create($applyData);

        return $this->success($apply, __('response.success'), ResponseCode::SUCCESS);
    }

    /**
     * Return the latest cancellation application for the current front user.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function status(Request $request): JsonResponse
    {
        $userLogin = $request->user('user');
        $userInfo = $userLogin->userInfo;

        if (!$userInfo) {
            return $this->error(__('auth.user_info_not_found'), ResponseCode::USER_NOT_FOUND);
        }

        $apply = CancelApply::where('user_id', $userInfo->user_id)
            ->orderBy('id', 'desc')
            ->first();

        return $this->success($apply, __('response.success'), ResponseCode::SUCCESS);
    }
}
