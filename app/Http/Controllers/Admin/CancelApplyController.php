<?php

namespace App\Http\Controllers\Admin;

use App\Models\CancelApply;
use App\Models\UserInfo;
use App\Constants\ResponseCode;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/**
 * Account Cancellation Management Controller
 * 账号注销管理控制器
 */
class CancelApplyController extends AdminBaseController
{
    /**
     * List cancel applications
     * 获取注销申请列表
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        $page = $request->input('page', 1);
        $perPage = $request->input('per_page', 15);

        $query = CancelApply::query()->with('user');

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $applies = $query->orderByDesc('created_at')->paginate($perPage, ['*'], 'page', $page);

        return $this->success($applies, __('admin.cancel_applies_fetched'));
    }

    /**
     * Approve cancellation
     * 审核通过注销申请 (软删除用户)
     *
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function approve($id)
    {
        try {
            $apply = CancelApply::find($id);
            if (!$apply || $apply->status != 0) {
                return $this->error(__('admin.cancel_apply_not_found_or_processed'), ResponseCode::DATA_NOT_FOUND);
            }

            $apply->update(['status' => 1]);

            $user = UserInfo::where('user_id', $apply->user_id)->first();
            if ($user) {
                $user->update(['is_cancelled' => 1]);
                $user->delete(); // Soft delete
            }

            return $this->success([], __('admin.cancel_approved'));
        } catch (\Exception $e) {
            return $this->error($e->getMessage(), ResponseCode::SERVER_ERROR);
        }
    }

    /**
     * Reject cancellation
     * 拒绝注销申请
     *
     * @param Request $request
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function reject(Request $request, $id)
    {
        try {
            $apply = CancelApply::find($id);
            if (!$apply || $apply->status != 0) {
                return $this->error(__('admin.cancel_apply_not_found_or_processed'), ResponseCode::DATA_NOT_FOUND);
            }

            $apply->update([
                'status' => -1,
                'reject_reason' => $request->input('reason', ''),
            ]);

            return $this->success([], __('admin.cancel_rejected'));
        } catch (\Exception $e) {
            return $this->error($e->getMessage(), ResponseCode::SERVER_ERROR);
        }
    }
}
