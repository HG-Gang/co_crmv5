<?php

namespace App\Http\Controllers\Admin;

use App\Models\CommissionRecord;
use App\Constants\ResponseCode;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/**
 * Commission Settlement Controller
 * 佣金结算控制器
 */
class CommissionController extends AdminBaseController
{
    /**
     * List commission settlement records
     * 获取佣金结算记录列表
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        $page = $request->input('page', 1);
        $perPage = $request->input('per_page', 15);

        $query = CommissionRecord::query()->with(['agent', 'parent']);

        if ($request->filled('agent_id')) {
            $query->where('agent_id', $request->agent_id);
        }

        if ($request->filled('settle_status')) {
            $query->where('settle_status', $request->settle_status);
        }

        $records = $query->orderByDesc('created_at')->paginate($perPage, ['*'], 'page', $page);

        return $this->success($records, __('admin.commission_list_fetched'));
    }

    /**
     * Get commission detail
     * 获取佣金结算详情
     *
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function show($id)
    {
        $record = CommissionRecord::with(['agent', 'parent'])->find($id);
        if (!$record) {
            return $this->error(__('admin.commission_record_not_found'), ResponseCode::DATA_NOT_FOUND);
        }

        return $this->success($record, __('admin.commission_detail_fetched'));
    }

    /**
     * Settle single commission record
     * 结算单条佣金记录
     *
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function settle($id)
    {
        try {
            $record = CommissionRecord::find($id);
            if (!$record || $record->settle_status == 2) {
                return $this->error(__('admin.commission_record_not_found_or_settled'), ResponseCode::DATA_NOT_FOUND);
            }

            $record->update(['settle_status' => 2]); // Settled

            return $this->success([], __('admin.commission_settled'));
        } catch (\Exception $e) {
            return $this->error($e->getMessage(), ResponseCode::SERVER_ERROR);
        }
    }

    /**
     * Batch settle multiple records
     * 批量结算多条佣金记录
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function batchSettle(Request $request)
    {
        try {
            $ids = $request->input('ids', []);
            if (empty($ids)) {
                return $this->error(__('admin.no_ids_provided'), ResponseCode::VALIDATION_FAILED);
            }

            CommissionRecord::whereIn('id', $ids)->where('settle_status', 1)->update(['settle_status' => 2]);

            return $this->success([], __('admin.batch_settlement_completed'), ResponseCode::BATCH_SUCCESS);
        } catch (\Exception $e) {
            return $this->error($e->getMessage(), ResponseCode::SERVER_ERROR);
        }
    }
}
