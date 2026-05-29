<?php

namespace App\Http\Controllers\Admin;

use App\Models\PaymentChannel;
use App\Constants\ResponseCode;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/**
 * Payment Channel Management Controller
 * 支付渠道管理控制器
 */
class PaymentChannelController extends AdminBaseController
{
    /**
     * List all payment channels
     * 获取支付渠道列表
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        $page = $request->input('page', 1);
        $perPage = $request->input('per_page', $request->input('limit', 15));

        $channels = PaymentChannel::query()->orderBy('sort')->paginate($perPage, ['*'], 'page', $page);

        return $this->success($channels, __('admin.payment_channels_fetched'));
    }

    /**
     * Create payment channel
     * 创建支付渠道
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request)
    {
        try {
            // Accept the legacy front/admin field name and persist only the
            // real table column.  This keeps older JS callers compatible.
            if ($request->filled('channel_name') && !$request->filled('name')) {
                $request->merge(['name' => $request->input('channel_name')]);
            }

            $validator = Validator::make($request->all(), [
                'name' => 'required|string|max:100',
                'channel_code' => 'required|string|max:50|unique:payment_channels',
                'exchange_rate' => 'sometimes|numeric|min:0',
                'is_enabled' => 'sometimes|boolean',
                'sort' => 'sometimes|integer',
            ]);

            if ($validator->fails()) {
                return $this->error($validator->errors()->first(), ResponseCode::VALIDATION_FAILED);
            }

            $channel = PaymentChannel::create($request->only([
                'name', 'channel_code', 'exchange_rate', 'is_enabled', 'sort', 'config'
            ]));
            return $this->success($channel, __('admin.payment_channel_created'), ResponseCode::CREATED);
        } catch (\Exception $e) {
            return $this->error($e->getMessage(), ResponseCode::SERVER_ERROR);
        }
    }

    /**
     * Update payment channel
     * 更新支付渠道
     *
     * @param Request $request
     * @param int|null $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(Request $request, $id = null)
    {
        try {
            $id = $id ?: $request->input('id');
            $channel = PaymentChannel::find($id);
            if (!$channel) {
                return $this->error(__('admin.payment_channel_not_found'), ResponseCode::DATA_NOT_FOUND);
            }

            // Older forms may submit channel_name.  Normalize it to the actual
            // payment_channels.name column before validation/update.
            if ($request->filled('channel_name') && !$request->filled('name')) {
                $request->merge(['name' => $request->input('channel_name')]);
            }

            $validator = Validator::make($request->all(), [
                'name' => 'required|string|max:100',
                'channel_code' => 'required|string|max:50|unique:payment_channels,channel_code,' . $id,
                'exchange_rate' => 'sometimes|numeric|min:0',
                'is_enabled' => 'sometimes|boolean',
                'sort' => 'sometimes|integer',
            ]);

            if ($validator->fails()) {
                return $this->error($validator->errors()->first(), ResponseCode::VALIDATION_FAILED);
            }

            $channel->update($request->only([
                'name', 'channel_code', 'exchange_rate', 'is_enabled', 'sort', 'config'
            ]));
            return $this->success($channel, __('admin.payment_channel_updated'), ResponseCode::UPDATED);
        } catch (\Exception $e) {
            return $this->error($e->getMessage(), ResponseCode::SERVER_ERROR);
        }
    }

    /**
     * Delete payment channel
     * 删除支付渠道
     *
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy($id)
    {
        try {
            $channel = PaymentChannel::find($id);
            if (!$channel) {
                return $this->error(__('admin.payment_channel_not_found'), ResponseCode::DATA_NOT_FOUND);
            }

            $channel->delete();
            return $this->success([], __('admin.payment_channel_deleted'), ResponseCode::DELETED);
        } catch (\Exception $e) {
            return $this->error($e->getMessage(), ResponseCode::SERVER_ERROR);
        }
    }

    /**
     * Toggle enable/disable status
     * 切换启用/禁用状态
     *
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function toggleEnable($id)
    {
        try {
            $channel = PaymentChannel::find($id);
            if (!$channel) {
                return $this->error(__('admin.payment_channel_not_found'), ResponseCode::DATA_NOT_FOUND);
            }

            $channel->update(['is_enabled' => !$channel->is_enabled]);
            return $this->success([], __('admin.status_toggled'));
        } catch (\Exception $e) {
            return $this->error($e->getMessage(), ResponseCode::SERVER_ERROR);
        }
    }
}
