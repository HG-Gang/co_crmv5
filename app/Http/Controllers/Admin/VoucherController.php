<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/16
 * Time: 02:23
 */

namespace App\Http\Controllers\Admin;

use App\Models\VoucherInfo;
use App\Constants\ResponseCode;
use App\Services\AdminDataScopeService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/**
 * 后台凭证管理控制器。
 *
 * 文件功能：
 * - 负责后台查看凭证提交记录、审核通过凭证和拒绝凭证。
 * - 数据来源为 voucher_infos 表，凭证归属以上传用户 user_id 为准。
 *
 * 功能逻辑说明：
 * - review_status 是凭证审核状态字段：0=待审核，1=审核通过，2=审核拒绝。
 * - 拒绝凭证时会把前端提交的 reason 写入 voucher_infos.review_message，供用户和后台复查原因。
 *
 * 安全边界：
 * - 列表、通过、拒绝统一按凭证归属用户 user_id 套用 AdminDataScopeService 数据范围，越权返回 PERMISSION_DENIED。
 * - 只有 review_status=0 的待审核凭证允许处理，已处理的凭证拒绝重复审核。
 */
class VoucherController extends AdminBaseController
{
    /**
     * 后台数据范围服务：凭证列表与审核动作按凭证归属用户 user_id 套用可见范围；
     * 缺失时管理员可审核数据范围外用户提交的凭证，越权后果是直接改变他人客户的审核状态。
     *
     * @var AdminDataScopeService
     */
    private $adminDataScopeService;

    /**
     * 注入数据范围服务。
     *
     * @param AdminDataScopeService $adminDataScopeService 数据范围过滤服务,列表与审核动作按凭证归属用户 user_id 统一套用。
     */
    public function __construct(AdminDataScopeService $adminDataScopeService)
    {
        $this->adminDataScopeService = $adminDataScopeService;
    }

    /**
     * 获取凭证提交列表。
     *
     * index() 参数说明：
     * - page 表示当前页码，默认第 1 页。
     * - per_page 表示每页数量，默认 15 条。
     * - review_status 表示凭证审核状态，0=待审核，1=审核通过，2=审核拒绝。
     *
     * @param Request $request 当前 HTTP 请求对象，承载分页参数和审核状态筛选条件。
     * @return \Illuminate\Http\JsonResponse 返回分页凭证提交列表，包含关联用户信息。
     */
    public function index(Request $request)
    {
        if ($reviewStatusError = $this->validateReviewStatusFilter($request)) {
            return $reviewStatusError;
        }

        if ($userIdError = $this->validateUserIdFilter($request)) {
            return $userIdError;
        }

        if ($dateError = $this->validateDateFilters($request)) {
            return $dateError;
        }

        $page = $request->input('page', 1);
        $perPage = $request->input('per_page', 15);

        $admin = $request->user('admin');
        if (!$admin) {
            return $this->error(__('response.permission_denied'), ResponseCode::PERMISSION_DENIED);
        }

        $query = VoucherInfo::query()->with('user');
        // 凭证归属以上传用户 user_id 为准，列表和审核动作必须使用同一范围口径。
        $query = $this->adminDataScopeService->apply($query, $admin, 'user', 'user_id');

        if ($request->filled('review_status')) {
            $query->where('review_status', $request->review_status);
        }

        if ($request->filled('user_id')) {
            $query->where('user_id', (int) $request->input('user_id'));
        }

        if ($request->filled('start_date')) {
            $query->where('created_at', '>=', strtotime($request->input('start_date') . ' 00:00:00'));
        }

        if ($request->filled('end_date')) {
            $query->where('created_at', '<=', strtotime($request->input('end_date') . ' 23:59:59'));
        }

        $vouchers = $query->orderByDesc('created_at')->paginate($perPage, ['*'], 'page', $page);

        return $this->success($vouchers, __('admin.vouchers_fetched'));
    }

    /**
     * 审核通过凭证。
     *
     * approve() 参数说明：
     * - $id 表示 voucher_infos 表主键，用于定位待审核凭证记录。
     * - review_status=1 表示审核通过。
     * - 只有 review_status=0 的待审核凭证允许通过，避免重复处理。
     *
     * @param int|string $id voucher_infos 表主键。
     * @return \Illuminate\Http\JsonResponse 审核通过结果响应。
     */
    public function approve(Request $request, $id)
    {
        try {
            if ($routeIdError = $this->validateVoucherRouteId($id)) {
                return $routeIdError;
            }

            $id = (int) $id;
            $voucher = VoucherInfo::find($id);
            if (!$voucher || $voucher->review_status != 0) {
                return $this->error(__('admin.voucher_not_found_or_processed'), ResponseCode::DATA_NOT_FOUND);
            }

            $admin = $request->user('admin');
            if (!$admin || !$this->adminDataScopeService->canAccessUser($admin, $voucher->user_id, 'user')) {
                return $this->error(__('response.permission_denied'), ResponseCode::PERMISSION_DENIED);
            }

            $updated = VoucherInfo::query()
                ->whereKey($id)
                ->where('review_status', 0)
                ->update([
                    'review_status' => 1,
                    'review_message' => '',
                    'updated_by' => (string) ($admin->username ?? $admin->id),
                    'updated_at' => time(),
                ]);

            if (!$updated) {
                return $this->error(__('admin.voucher_not_found_or_processed'), ResponseCode::DATA_NOT_FOUND);
            }

            return $this->success([], __('admin.voucher_approved'));
        } catch (\Exception $e) {
            return $this->serverErrorResponse();
        }
    }

    /**
     * 拒绝凭证。
     *
     * reject() 参数说明：
     * - $request 当前 HTTP 请求对象，承载 reason 拒绝原因。
     * - $id 表示 voucher_infos 表主键，用于定位待审核凭证记录。
     * - reason 表示拒绝原因。
     * - review_message 表示审核备注，拒绝时写入 reason 内容。
     * - review_status=2 表示审核拒绝。
     *
     * 失败语义：
     * - 记录不存在或已处理返回 DATA_NOT_FOUND；越权访问返回 PERMISSION_DENIED；其余异常统一转为服务端错误。
     *
     * @param Request $request 当前 HTTP 请求对象，承载拒绝原因。
     * @param int|string $id voucher_infos 表主键。
     * @return \Illuminate\Http\JsonResponse 拒绝结果响应。
     */
    public function reject(Request $request, $id)
    {
        try {
            if ($routeIdError = $this->validateVoucherRouteId($id)) {
                return $routeIdError;
            }

            $id = (int) $id;
            $voucher = VoucherInfo::find($id);
            if (!$voucher || $voucher->review_status != 0) {
                return $this->error(__('admin.voucher_not_found_or_processed'), ResponseCode::DATA_NOT_FOUND);
            }

            $admin = $request->user('admin');
            if (!$admin || !$this->adminDataScopeService->canAccessUser($admin, $voucher->user_id, 'user')) {
                return $this->error(__('response.permission_denied'), ResponseCode::PERMISSION_DENIED);
            }

            $reason = trim((string) $request->input('reason', ''));
            $validator = Validator::make(['reason' => $reason], [
                'reason' => 'required|string|max:2000',
            ]);
            if ($validator->fails()) {
                return $this->error($validator->errors()->first(), ResponseCode::VALIDATION_FAILED);
            }

            $updated = VoucherInfo::query()
                ->whereKey($id)
                ->where('review_status', 0)
                ->update([
                    'review_status' => 2,
                    'review_message' => $reason,
                    'updated_by' => (string) ($admin->username ?? $admin->id),
                    'updated_at' => time(),
                ]);

            if (!$updated) {
                return $this->error(__('admin.voucher_not_found_or_processed'), ResponseCode::DATA_NOT_FOUND);
            }

            return $this->success([], __('admin.voucher_rejected'));
        } catch (\Exception $e) {
            return $this->serverErrorResponse();
        }
    }

    /**
     * 校验凭证路由主键，必须为整数。
     *
     * @param mixed $id voucher_infos.id。
     * @return \Illuminate\Http\JsonResponse|null 非法时返回参数错误响应，合法时返回 null。
     */
    private function validateVoucherRouteId($id)
    {
        $validator = Validator::make(['id' => $id], [
            'id' => 'required|integer',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), ResponseCode::VALIDATION_FAILED);
        }

        return null;
    }

    /**
     * 校验 review_status 列表筛选参数，只允许 0/1/2 三种审核状态。
     *
     * @param Request $request 当前请求对象，读取 review_status。
     * @return \Illuminate\Http\JsonResponse|null 非法时返回参数错误响应，未传或合法时返回 null。
     */
    private function validateReviewStatusFilter(Request $request)
    {
        if (!$request->filled('review_status')) {
            return null;
        }

        $validator = Validator::make(['review_status' => $request->input('review_status')], [
            'review_status' => 'integer|in:0,1,2',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), ResponseCode::VALIDATION_FAILED);
        }

        return null;
    }

    /**
     * 旧 voucherInfoSearch 的 userId 过滤必须是严格正整数，避免字符串截断或负数绕过。
     */
    private function validateUserIdFilter(Request $request)
    {
        if (!$request->filled('user_id')) {
            return null;
        }

        $validator = Validator::make(['user_id' => $request->input('user_id')], [
            'user_id' => 'integer|min:1',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), ResponseCode::VALIDATION_FAILED);
        }

        return null;
    }

    /**
     * 旧页面提交 startdate/enddate 后由兼容层转换为日期字段；日期必须是完整 Y-m-d。
     */
    private function validateDateFilters(Request $request)
    {
        $dates = [];
        foreach (['start_date', 'end_date'] as $field) {
            if (!$request->filled($field)) {
                continue;
            }

            $value = (string) $request->input($field);
            $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $value);
            $errors = \DateTimeImmutable::getLastErrors();
            if ($date === false
                || ($errors !== false && ($errors['warning_count'] > 0 || $errors['error_count'] > 0))
                || $date->format('Y-m-d') !== $value
            ) {
                return $this->error(__('response.validation_failed'), ResponseCode::VALIDATION_FAILED, [
                    'field' => $field,
                ]);
            }

            $dates[$field] = $date;
        }

        if (isset($dates['start_date'], $dates['end_date'])
            && $dates['start_date'] > $dates['end_date']) {
            return $this->error(__('response.validation_failed'), ResponseCode::VALIDATION_FAILED, [
                'field' => 'date_range',
            ]);
        }

        return null;
    }
}
