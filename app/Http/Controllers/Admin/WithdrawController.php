<?php

namespace App\Http\Controllers\Admin;

use App\Models\WithdrawRecord;
use App\Constants\ResponseCode;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/**
 * Withdrawal Management Controller
 * 出金/提现管理控制器
 */
class WithdrawController extends AdminBaseController
{
    /**
     * List all withdrawal applications
     * 获取所有提现申请列表
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        $page = $request->input('page', 1);
        $perPage = $request->input('per_page', 15);

        $query = WithdrawRecord::query()->with('user');

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        if ($request->filled('local_order_no')) {
            $query->where('local_order_no', 'LIKE', "%{$request->local_order_no}%");
        }

        $withdrawals = $query->orderByDesc('created_at')->paginate($perPage, ['*'], 'page', $page);

        return $this->success($withdrawals, __('admin.withdrawal_list_fetched'));
    }

    /**
     * Get withdrawal detail
     * 获取提现详情
     *
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function show($id)
    {
        $withdraw = WithdrawRecord::with('user')->find($id);
        if (!$withdraw) {
            return $this->error(__('admin.withdrawal_not_found'), ResponseCode::DATA_NOT_FOUND);
        }

        return $this->success($withdraw, __('admin.withdrawal_detail_fetched'));
    }

    /**
     * Mark as processing
     * 标记为处理中
     *
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function process($id)
    {
        try {
            $withdraw = WithdrawRecord::find($id);
            if (!$withdraw || $withdraw->status != 0) {
                return $this->error(__('admin.withdrawal_not_found_or_invalid'), ResponseCode::DATA_NOT_FOUND);
            }

            $withdraw->update(['status' => 1]); // Processing

            return $this->success([], __('admin.withdrawal_processing'));
        } catch (\Exception $e) {
            return $this->error($e->getMessage(), ResponseCode::SERVER_ERROR);
        }
    }

    /**
     * Mark as completed
     * 标记为已完成
     *
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function complete($id)
    {
        try {
            $withdraw = WithdrawRecord::find($id);
            if (!$withdraw || $withdraw->status == 2) {
                return $this->error(__('admin.withdrawal_not_found_or_completed'), ResponseCode::DATA_NOT_FOUND);
            }

            $withdraw->update(['status' => 2]); // Completed

            return $this->success([], __('admin.withdrawal_completed'), ResponseCode::UPDATED);
        } catch (\Exception $e) {
            return $this->error($e->getMessage(), ResponseCode::SERVER_ERROR);
        }
    }

    /**
     * Reject with reason
     * 拒绝提现并附带原因
     *
     * @param Request $request
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function reject(Request $request, $id)
    {
        try {
            $withdraw = WithdrawRecord::find($id);
            if (!$withdraw || $withdraw->status == 2) {
                return $this->error(__('admin.withdrawal_not_found_or_completed'), ResponseCode::DATA_NOT_FOUND);
            }

            $withdraw->update([
                'status' => 3, // Failed/Rejected
                'reject_reason' => $request->input('reason', ''),
            ]);

            return $this->success([], __('admin.withdrawal_rejected'));
        } catch (\Exception $e) {
            return $this->error($e->getMessage(), ResponseCode::SERVER_ERROR);
        }
    }
}
