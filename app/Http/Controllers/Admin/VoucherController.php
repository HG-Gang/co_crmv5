<?php

namespace App\Http\Controllers\Admin;

use App\Models\VoucherInfo;
use App\Constants\ResponseCode;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/**
 * Voucher Management Controller
 * 凭证管理控制器
 */
class VoucherController extends AdminBaseController
{
    /**
     * List voucher submissions
     * 获取凭证提交列表
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        $page = $request->input('page', 1);
        $perPage = $request->input('per_page', 15);

        $query = VoucherInfo::query()->with('user');

        if ($request->filled('review_status')) {
            $query->where('review_status', $request->review_status);
        }

        $vouchers = $query->orderByDesc('created_at')->paginate($perPage, ['*'], 'page', $page);

        return $this->success($vouchers, __('admin.vouchers_fetched'));
    }

    /**
     * Approve voucher
     * 审核通过凭证
     *
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function approve($id)
    {
        try {
            $voucher = VoucherInfo::find($id);
            if (!$voucher || $voucher->review_status != 0) {
                return $this->error(__('admin.voucher_not_found_or_processed'), ResponseCode::DATA_NOT_FOUND);
            }

            $voucher->update(['review_status' => 1]);

            return $this->success([], __('admin.voucher_approved'));
        } catch (\Exception $e) {
            return $this->error($e->getMessage(), ResponseCode::SERVER_ERROR);
        }
    }

    /**
     * Reject voucher
     * 拒绝凭证
     *
     * @param Request $request
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function reject(Request $request, $id)
    {
        try {
            $voucher = VoucherInfo::find($id);
            if (!$voucher || $voucher->review_status != 0) {
                return $this->error(__('admin.voucher_not_found_or_processed'), ResponseCode::DATA_NOT_FOUND);
            }

            $voucher->update([
                'review_status' => 2,
                'review_message' => $request->input('reason', ''),
            ]);

            return $this->success([], __('admin.voucher_rejected'));
        } catch (\Exception $e) {
            return $this->error($e->getMessage(), ResponseCode::SERVER_ERROR);
        }
    }
}
