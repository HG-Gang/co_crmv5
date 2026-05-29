<?php

namespace App\Http\Controllers\Admin;

use App\Models\DepositRecord;
use App\Constants\ResponseCode;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/**
 * Deposit Management Controller
 * 入金管理控制器
 */
class DepositController extends AdminBaseController
{
    /**
     * List all deposit records
     * 获取所有入金记录列表
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        $page = $request->input('page', 1);
        $perPage = $request->input('per_page', 15);

        $query = DepositRecord::query()->with('user');

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        if ($request->filled('local_order_no')) {
            $query->where('local_order_no', 'LIKE', "%{$request->local_order_no}%");
        }

        $deposits = $query->orderByDesc('created_at')->paginate($perPage, ['*'], 'page', $page);

        return $this->success($deposits, __('admin.deposit_list_fetched'));
    }

    /**
     * Get deposit detail
     * 获取入金详情
     *
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function show($id)
    {
        $deposit = DepositRecord::with('user')->find($id);
        if (!$deposit) {
            return $this->error(__('admin.deposit_not_found'), ResponseCode::DATA_NOT_FOUND);
        }

        return $this->success($deposit, __('admin.deposit_detail_fetched'));
    }

    /**
     * Approve deposit
     * 审核通过入金
     *
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function approve($id)
    {
        try {
            $deposit = DepositRecord::find($id);
            if (!$deposit) {
                return $this->error(__('admin.deposit_not_found'), ResponseCode::DATA_NOT_FOUND);
            }

            if ($deposit->status == '02') {
                return $this->error(__('admin.deposit_already_approved'), ResponseCode::OPERATION_NOT_ALLOWED);
            }

            $deposit->update([
                'status' => '02',
                'payment_time' => now(),
                'updated_by' => auth()->id() ?? 'admin',
            ]);

            // Further logic to update user balance can be added here
            // 增加用户余额逻辑

            return $this->success([], __('admin.deposit_approved'));
        } catch (\Exception $e) {
            return $this->error($e->getMessage(), ResponseCode::SERVER_ERROR);
        }
    }

    /**
     * Reject deposit
     * 拒绝入金
     *
     * @param Request $request
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function reject(Request $request, $id)
    {
        try {
            $deposit = DepositRecord::find($id);
            if (!$deposit) {
                return $this->error(__('admin.deposit_not_found'), ResponseCode::DATA_NOT_FOUND);
            }

            if ($deposit->status == '02') {
                return $this->error(__('admin.deposit_already_approved'), ResponseCode::OPERATION_NOT_ALLOWED);
            }

            $deposit->update([
                'status' => '09', // Failed/Rejected
                'remarks' => $request->input('reason', ''),
                'updated_by' => auth()->id() ?? 'admin',
            ]);

            return $this->success([], __('admin.deposit_rejected'));
        } catch (\Exception $e) {
            return $this->error($e->getMessage(), ResponseCode::SERVER_ERROR);
        }
    }

    /**
     * Batch import deposits
     * 批量导入入金
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function import(Request $request)
    {
        // Batch import logic placeholder (CSV/Excel upload handling)
        // 批量导入逻辑占位 (处理 CSV/Excel 上传)

        return $this->success([], 'Import feature coming soon');
    }
}
