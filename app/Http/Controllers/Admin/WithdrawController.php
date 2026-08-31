<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/28
 * Time: 01:16
 */

namespace App\Http\Controllers\Admin;

use App\Jobs\RefundWithdrawFunding;
use App\Models\WithdrawRecord;
use App\Models\WithdrawSettlementOutbox;
use App\Models\UserTrade;
use App\Services\AdminDataScopeService;
use App\Services\WithdrawRecordQueryService;
use App\Constants\ResponseCode;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Throwable;

/**
 * 后台出金管理控制器。
 *
 * 文件功能：
 * - 负责后台出金申请列表、详情、标记处理中、标记完成和拒绝出金。
 * - 出金记录的数据范围以 withdraw_records.user_id 为归属字段，通过 AdminDataScopeService 限制不同管理员可见用户。
 * - status 是出金处理状态字段：0=待处理，1=处理中，2=已完成，3=已拒绝或失败。
 */
class WithdrawController extends AdminBaseController
{
    /**
     * 后台数据范围服务。
     *
     * @var AdminDataScopeService
     */
    protected $adminDataScopeService;

    /**
     * 出金只读查询服务：承载出金申请列表/详情的分页、筛选与数据范围套用，控制器只做编排；
     * 缺失时列表无法按统一口径出数（状态筛选、user_id 归属过滤都要重写在控制器里）。
     * 构造函数允许为 null 时现场组装，仅为兼容未走容器的直接实例化调用方。
     *
     * @var WithdrawRecordQueryService
     */
    protected $withdrawRecordQueryService;

    /**
     * 构造函数。
     *
     * @param AdminDataScopeService $adminDataScopeService 后台数据范围服务，用于按管理员角色配置限制出金列表可见用户。
     * @param WithdrawRecordQueryService|null $withdrawRecordQueryService 出金只读查询服务；可选参数保留现有直接实例化测试兼容性。
     */
    public function __construct(
        AdminDataScopeService $adminDataScopeService,
        ?WithdrawRecordQueryService $withdrawRecordQueryService = null
    ) {
        $this->adminDataScopeService = $adminDataScopeService;
        $this->withdrawRecordQueryService = $withdrawRecordQueryService
            ?: new WithdrawRecordQueryService($adminDataScopeService);
    }

    /**
     * 获取出金申请列表。
     *
     * index() 参数说明：
     * - page 表示当前页码，默认第 1 页。
     * - per_page 表示每页数量，默认 15 条。
     * - status 表示出金处理状态，0=待处理，1=处理中，2=已完成，3=已拒绝或失败。
     * - user_id 表示业务用户 ID，对应 withdraw_records.user_id。
     * - local_order_no 表示本地出金订单号，用于按订单号模糊搜索。
     *
     * 返回结构说明：
     * - 保留 Laravel paginator 的全部键（data/total/current_page…），历史调用方零改造。
     * - 额外追加 summary：当前筛选条件下的出金统计，供后台出金页的独立统计区块展示。
     *   summary 与列表共用同一份筛选查询，因此卡片数字与表格行口径完全一致。
     *
     * @param Request $request 当前 HTTP 请求对象，承载分页参数、筛选条件和 admin guard 登录管理员。
     * @return \Illuminate\Http\JsonResponse 返回分页出金申请列表。
     */
    public function index(Request $request)
    {
        if ($validationError = $this->validateIndexFilters($request)) {
            return $validationError;
        }

        $page = (int) $request->input('page', 1);
        $perPage = (int) $request->input('per_page', 15);
        $filters = $request->only([
            'status',
            'user_id',
            'local_order_no',
            'mt4_ticket',
            'start_date',
            'end_date',
        ]);
        $query = $this->withdrawRecordQueryService->query($request->user('admin'), $filters);

        $summary = $this->withdrawSummaryFor(clone $query);
        $withdrawals = $query->orderByDesc('created_at')->paginate($perPage, ['*'], 'page', $page);

        return $this->success(
            array_merge($withdrawals->toArray(), ['summary' => $summary]),
            __('admin.withdrawal_list_fetched')
        );
    }

    /**
     * 汇总当前筛选条件下的出金统计。
     *
     * 金额精度说明：
     * - 先把每行金额 CAST 成 DECIMAL(18,2) 再 SUM，让 MySQL 在十进制域内累加，
     *   PHP 侧拿到精确十进制字符串，避免 float 累加误差。
     *
     * 字段说明：
     * - total_records：命中记录数。
     * - total_withdraw_amount：申请出金金额合计（withdraw_records.apply_amount）。
     * - total_actual_amount：实际出金金额合计（withdraw_records.actual_amount）。
     * - total_fee：手续费合计（withdraw_records.fee）。
     * - completed_records：其中已完成（status=2）的记录数。
     *
     * @param \Illuminate\Database\Eloquent\Builder $query 已套用数据范围和筛选条件的出金查询。
     * @return array<string, mixed> 独立统计区块使用的汇总数据。
     */
    private function withdrawSummaryFor($query): array
    {
        $row = (clone $query)
            ->selectRaw(
                'COUNT(*) AS total_records,'
                . ' CAST(COALESCE(SUM(CAST(apply_amount AS DECIMAL(18,2))), 0) AS DECIMAL(18,2)) AS total_withdraw_amount,'
                . ' CAST(COALESCE(SUM(CAST(actual_amount AS DECIMAL(18,2))), 0) AS DECIMAL(18,2)) AS total_actual_amount,'
                . ' CAST(COALESCE(SUM(CAST(fee AS DECIMAL(18,2))), 0) AS DECIMAL(18,2)) AS total_fee,'
                . ' SUM(CASE WHEN status = 2 THEN 1 ELSE 0 END) AS completed_records'
            )
            ->first();

        return [
            'total_records' => $row === null ? 0 : (int) $row->total_records,
            'total_withdraw_amount' => $row === null ? '0.00' : (string) $row->total_withdraw_amount,
            'total_actual_amount' => $row === null ? '0.00' : (string) $row->total_actual_amount,
            'total_fee' => $row === null ? '0.00' : (string) $row->total_fee,
            'completed_records' => $row === null ? 0 : (int) $row->completed_records,
        ];
    }

    /**
     * 获取出金详情。
     *
     * show() 参数说明：
     * - $request 当前 HTTP 请求对象；旧后台 POST 入口可从请求体读取 id。
     * - $id 表示 withdraw_records 表主键；为空时兼容从请求体读取。
     * - 读取详情后仍会调用 denyWithdrawAccessIfNeeded() 校验管理员数据范围。
     *
     * @param Request $request 当前 HTTP 请求对象，承载 id 和 admin guard 登录管理员。
     * @param int|string|null $id withdraw_records 表主键，可为空以兼容旧 POST 请求体参数。
     * @return \Illuminate\Http\JsonResponse 出金详情响应。
     */
    public function show(Request $request, $id = null)
    {
        // 当前后台路由若接入详情接口时，可从请求体读取 id；保留可选路由参数用于兼容 REST 写法。
        $rawWithdrawId = $id ?: $request->input('id');
        if ($withdrawIdError = $this->validateWithdrawId($rawWithdrawId)) {
            return $withdrawIdError;
        }

        $withdrawId = (int) $rawWithdrawId;
        $withdraw = WithdrawRecord::with('user')->find($withdrawId);
        if (!$withdraw) {
            return $this->error(__('admin.withdrawal_not_found'), ResponseCode::DATA_NOT_FOUND);
        }

        $accessDenied = $this->denyWithdrawAccessIfNeeded($request, $withdraw);
        if ($accessDenied) {
            return $accessDenied;
        }

        return $this->success($withdraw, __('admin.withdrawal_detail_fetched'));
    }

    /**
     * 将出金申请标记为处理中。
     *
     * process() 参数说明：
     * - $request 当前 HTTP 请求对象；旧后台 POST /withdrawProcess 默认从请求体读取 id。
     * - $id 表示 withdraw_records 表主键。
     * - status=1 表示处理中；只有 status=0 的待处理出金可以进入处理中状态。
     *
     * @param Request $request 当前 HTTP 请求对象，承载 id 和 admin guard 登录管理员。
     * @param int|string|null $id withdraw_records 表主键，可为空以兼容旧 POST 请求体参数。
     * @return \Illuminate\Http\JsonResponse 标记处理中结果响应。
     */
    public function process(Request $request, $id = null)
    {
        try {
            // 当前后台路由为 POST /withdrawProcess，默认从请求体读取 id。
            $rawWithdrawId = $id ?: $request->input('id');
            if ($withdrawIdError = $this->validateWithdrawId($rawWithdrawId)) {
                return $withdrawIdError;
            }

            $withdrawId = (int) $rawWithdrawId;
            $response = DB::transaction(function () use ($withdrawId, $request) {
                $locked = WithdrawRecord::whereKey($withdrawId)->lockForUpdate()->first();
                if (!$locked || (int) $locked->status !== 0 || $locked->funding_status !== 'debited') {
                    return $this->error(__('admin.withdrawal_not_found_or_invalid'), ResponseCode::DATA_NOT_FOUND);
                }

                $accessDenied = $this->denyWithdrawAccessIfNeeded($request, $locked);
                if ($accessDenied) {
                    return $accessDenied;
                }

                $locked->status = 1;
                $locked->updated_by = (string) optional($request->user('admin'))->getKey();
                $locked->saveOrFail();
            }, 3);

            if ($response !== null) {
                return $response;
            }

            return $this->success([], __('admin.withdrawal_processing'));
        } catch (\Exception $e) {
            return $this->serverErrorResponse();
        }
    }

    /**
     * 将出金申请标记为已完成。
     *
     * complete() 参数说明：
     * - $request 当前 HTTP 请求对象；旧后台 POST /withdrawComplete 默认从请求体读取 id。
     * - $id 表示 withdraw_records 表主键。
     * - status=2 表示已完成；已完成记录不允许重复完成。
     *
     * @param Request $request 当前 HTTP 请求对象，承载 id 和 admin guard 登录管理员。
     * @param int|string|null $id withdraw_records 表主键，可为空以兼容旧 POST 请求体参数。
     * @return \Illuminate\Http\JsonResponse 标记完成结果响应。
     */
    public function complete(Request $request, $id = null)
    {
        try {
            // 当前后台路由为 POST /withdrawComplete，默认从请求体读取 id。
            $rawWithdrawId = $id ?: $request->input('id');
            if ($withdrawIdError = $this->validateWithdrawId($rawWithdrawId)) {
                return $withdrawIdError;
            }

            $withdrawId = (int) $rawWithdrawId;
            $response = DB::transaction(function () use ($withdrawId, $request) {
                $locked = WithdrawRecord::whereKey($withdrawId)->lockForUpdate()->first();
                if (!$locked || (int) $locked->status !== 1 || $locked->funding_status !== 'debited') {
                    return $this->error(__('admin.withdrawal_not_found_or_completed'), ResponseCode::DATA_NOT_FOUND);
                }

                $accessDenied = $this->denyWithdrawAccessIfNeeded($request, $locked);
                if ($accessDenied) {
                    return $accessDenied;
                }

                // Admin completion is the terminal audit state after MT4 debit has already succeeded.
                $locked->status = 2;
                $locked->funding_status = 'completed';
                $locked->funding_error_code = null;
                $locked->updated_by = (string) optional($request->user('admin'))->getKey();
                $locked->saveOrFail();
            }, 3);

            if ($response !== null) {
                return $response;
            }

            return $this->success([], __('admin.withdrawal_completed'), ResponseCode::UPDATED);
        } catch (\Exception $e) {
            return $this->serverErrorResponse();
        }
    }

    /**
     * 拒绝出金申请并记录原因。
     *
     * reject() 参数说明：
     * - $request 当前 HTTP 请求对象，承载 reason 拒绝原因。
     * - $id 表示 withdraw_records 表主键。
     * - reason 表示拒绝原因，写入 withdraw_records.reject_reason。
     * - status=3 表示已拒绝或失败。
     *
     * @param Request $request 当前 HTTP 请求对象，承载 id、reason 和 admin guard 登录管理员。
     * @param int|string|null $id withdraw_records 表主键，可为空以兼容旧 POST 请求体参数。
     * @return \Illuminate\Http\JsonResponse 拒绝出金结果响应。
     */
    public function reject(Request $request, $id = null)
    {
        try {
            // 当前后台路由为 POST /withdrawReject，默认从请求体读取 id。
            $rawWithdrawId = $id ?: $request->input('id');
            if ($withdrawIdError = $this->validateWithdrawId($rawWithdrawId)) {
                return $withdrawIdError;
            }

            $withdrawId = (int) $rawWithdrawId;
            $reason = trim((string) $request->input('reason', ''));
            if ($reason === '' || mb_strlen($reason, 'UTF-8') > 500) {
                return $this->error(__('response.validation_failed'), ResponseCode::VALIDATION_FAILED);
            }
            $refundOutboxId = null;
            $response = DB::transaction(function () use ($withdrawId, $reason, $request, &$refundOutboxId) {
                $locked = WithdrawRecord::whereKey($withdrawId)->lockForUpdate()->first();
                if (!$locked || (int) $locked->status === 2
                    || in_array($locked->funding_status, ['unknown', 'refund_unknown', 'completed', 'refunded', 'refund_rejected'], true)) {
                    return $this->error(__('admin.withdrawal_not_found_or_completed'), ResponseCode::DATA_NOT_FOUND);
                }

                $accessDenied = $this->denyWithdrawAccessIfNeeded($request, $locked);
                if ($accessDenied) {
                    return $accessDenied;
                }

                $locked->reject_reason = $reason;
                $locked->updated_by = (string) optional($request->user('admin'))->getKey();
                $nowTs = time();
                if (in_array($locked->funding_status, ['pending', 'retryable'], true)) {
                    // outbox timestamps are 10-digit unix ints in this schema.
                    WithdrawSettlementOutbox::where('withdraw_record_id', $locked->id)
                        ->where('event_type', 'withdraw_debit')
                        ->update([
                            'status' => 'cancelled',
                            'processed_at' => $nowTs,
                            'last_error_code' => 'admin_rejected',
                        ]);
                    $locked->status = 3;
                    $locked->funding_status = 'cancelled';
                    $locked->funding_error_code = 'admin_rejected';
                } elseif ($locked->funding_status === 'processing') {
                    // Debit is in-flight: block refund until debit job resolves, then scanner/job unblocks it.
                    WithdrawSettlementOutbox::firstOrCreate(
                        ['event_type' => 'withdraw_refund', 'withdraw_record_id' => $locked->id],
                        [
                            'local_order_no' => $locked->local_order_no,
                            'status' => 'blocked',
                            'attempts' => 0,
                            'payload_hash' => $locked->funding_payload_hash,
                        ]
                    );
                } elseif ($locked->funding_status === 'debited') {
                    $refund = WithdrawSettlementOutbox::firstOrCreate(
                        ['event_type' => 'withdraw_refund', 'withdraw_record_id' => $locked->id],
                        [
                            'local_order_no' => $locked->local_order_no,
                            'status' => 'pending',
                            'attempts' => 0,
                            'payload_hash' => $locked->funding_payload_hash,
                            'available_at' => $nowTs,
                        ]
                    );
                    if (in_array((string) $refund->status, ['pending', 'retryable'], true) || $refund->wasRecentlyCreated) {
                        if ((string) $refund->status !== 'pending') {
                            $refund->status = 'pending';
                            $refund->available_at = $nowTs;
                            $refund->locked_at = null;
                            $refund->last_error_code = null;
                            $refund->saveOrFail();
                        }
                        $refundOutboxId = (int) $refund->id;
                    }
                    $locked->funding_status = 'refund_pending';
                } else {
                    throw new \RuntimeException('withdrawal_state_not_rejectable');
                }
                $locked->saveOrFail();
            }, 3);

            if ($response !== null) {
                return $response;
            }

            if ($refundOutboxId !== null) {
                try {
                    RefundWithdrawFunding::dispatch($refundOutboxId)->afterCommit();
                } catch (Throwable $exception) {
                    Log::error('Admin withdraw reject refund dispatch failed; scanner will retry.', [
                        'outbox_id' => $refundOutboxId,
                        'exception_class' => get_class($exception),
                    ]);
                }
            }

            return $this->success([], __('admin.withdrawal_rejected'));
        } catch (\Exception $e) {
            return $this->serverErrorResponse();
        }
    }

    /**
     * 按当前后台管理员的数据范围判断是否可以访问指定出金记录。
     *
     * denyWithdrawAccessIfNeeded() 参数说明：
     * - $request 当前 HTTP 请求对象，用于读取 admin guard 下的登录管理员。
     * - $withdraw 表示待查看或待处理的出金记录。
     * - user_id 与 created_by 一并交给 AdminDataScopeService，使 created 范围与列表查询保持一致。
     *
     * @param Request $request 当前请求对象，用于读取 admin guard 下的登录管理员。
     * @param WithdrawRecord $withdraw 出金记录；权限归属以记录中的 user_id 业务用户ID为准。
     * @return \Illuminate\Http\JsonResponse|null 返回 JsonResponse 表示拒绝访问；返回 null 表示允许继续执行业务逻辑。
     */
    private function denyWithdrawAccessIfNeeded(Request $request, WithdrawRecord $withdraw)
    {
        $admin = $request->user('admin');
        if (!$admin) {
            return $this->error(__('response.permission_denied'), ResponseCode::PERMISSION_DENIED);
        }

        if ($this->adminDataScopeService->canAccessRecord(
            $admin,
            $withdraw->user_id,
            $withdraw->created_by,
            'withdraw'
        )) {
            return null;
        }

        return $this->error(__('response.permission_denied'), ResponseCode::PERMISSION_DENIED);
    }

    /**
     * 校验 user_id 筛选参数必须为整数（可选参数，未传时直接放行）。
     *
     * @param Request $request 当前请求对象，读取可选的 user_id。
     * @return \Illuminate\Http\JsonResponse|null 参数非法时返回校验失败响应；合法或未传时返回 null。
     */
    private function validateIndexFilters(Request $request)
    {
        $validator = Validator::make($request->only([
            'status',
            'user_id',
            'local_order_no',
            'mt4_ticket',
            'start_date',
            'end_date',
            'page',
            'per_page',
        ]), [
            'status' => 'nullable|integer|in:0,1,2,3',
            'user_id' => 'nullable|integer|min:1',
            'local_order_no' => 'nullable|string|max:200',
            'mt4_ticket' => 'nullable|string|max:100',
            'start_date' => 'nullable|date_format:Y-m-d',
            'end_date' => 'nullable|date_format:Y-m-d|after_or_equal:start_date',
            'page' => 'nullable|integer|min:1',
            'per_page' => 'nullable|integer|between:1,100',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), ResponseCode::VALIDATION_FAILED);
        }

        return null;
    }
    /**
     * 校验出金记录主键必须为必填整数。
     *
     * @param mixed $id 出金记录主键，来自路由参数或请求体。
     * @return \Illuminate\Http\JsonResponse|null 非法时返回校验失败响应；合法时返回 null。
     */
    private function validateWithdrawId($id)
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
     * 获取提现流水列表（从旧项目WithdrawFlowController迁移）。
     *
     * withdrawFlow() 参数说明：
     * - page：当前页码，默认第1页。
     * - per_page/limit：每页数量，兼容Layui的limit参数，默认15条。
     * - user_id：用户ID，筛选指定用户的提现记录。
     * - withdraw_id：提现订单号，模糊匹配。
     * - withdraw_source：提现来源，筛选提现类型。
     * - deposit_startdate：提现开始日期，默认2024-01-01。
     * - deposit_enddate：提现结束日期，默认当前日期。
     *
     * 功能逻辑说明：
     * - 查询user_trades表中CMD=6且PROFIT<0的记录（余额减少记录）。
     * - 根据COMMENT字段判断提现来源（取款、佣金转出等）。
     * - 计算提现总金额汇总。
     * - 支持按提现来源、日期范围筛选。
     *
     * @param Request $request 当前HTTP请求对象。
     * @return \Illuminate\Http\JsonResponse 返回提现流水列表及汇总数据。
     */
    public function withdrawFlow(Request $request)
    {
        try {
            // 步骤1：获取分页参数
            $page = (int) $request->input('page', 1);
            $perPage = (int) $request->input('per_page', $request->input('limit', 15));

            // 步骤2：获取筛选条件
            $userId = $request->input('user_id');
            $withdrawId = $request->input('withdraw_id');
            $withdrawSource = $request->input('withdraw_source'); // 提现来源
            $startDate = $request->input('deposit_startdate', '2024-01-01');
            $endDate = $request->input('deposit_enddate', date('Y-m-d'));

            // 步骤3：构建提现关键词正则表达式
            // 提现来源包括：取款(-QK)、佣金转出(-ZH)、佣金转出退回(-TH)等
            $withdrawKeywords = $this->getWithdrawKeywords();

            // 步骤4：构建基础查询
            $query = UserTrade::query()
                ->select([
                    'user_trades.id as order_no',
                    'user_trades.user_id as userId',
                    'user_trades.profit as directProfit',
                    'user_trades.comment as directType',
                    'user_trades.comment as directComment',
                    'user_trades.open_time as directModifyTime',
                    'user_trades.close_time as directCloseTime',
                ])
                ->where('user_trades.open_price', 0) // 开仓价格为0表示余额调整
                ->where('user_trades.profit', '<', 0) // PROFIT<0表示出金
                ->where('user_trades.cmd', 6); // CMD=6表示余额调整

            // 步骤5：应用提现来源筛选
            if (!empty($withdrawSource)) {
                // 如果指定了提现来源，精确匹配
                $query->where('user_trades.comment', 'like', '%' . $withdrawSource . '%');
            } else {
                // 否则使用正则匹配所有提现关键词
                $query->whereRaw("user_trades.comment REGEXP ?", [$withdrawKeywords]);
            }

            // 步骤6：应用其他筛选条件
            if ($userId) {
                $query->where('user_trades.user_id', $userId);
            }

            if ($withdrawId) {
                $query->where('user_trades.id', 'like', '%' . $withdrawId . '%');
            }

            // 步骤7：应用日期筛选
            if ($startDate && $endDate) {
                $query->whereBetween('user_trades.open_time', [
                    $startDate . ' 00:00:00',
                    $endDate . ' 23:59:59'
                ]);
            }

            // 步骤8：计算总数（用于分页）
            $total = $query->count();

            // 步骤9：执行分页查询
            $withdrawals = $query->orderByDesc('user_trades.open_time')
                ->skip(($page - 1) * $perPage)
                ->take($perPage)
                ->get();

            // 步骤10：补充用户信息
            $withdrawalsData = $withdrawals->map(function ($withdraw) {
                // 查询用户信息
                $userInfo = DB::table('user_infos')
                    ->where('user_id', $withdraw->userId)
                    ->first();

                return [
                    'order_no' => $withdraw->order_no,
                    'userId' => $withdraw->userId,
                    'username' => $userInfo->user_name ?? '',
                    'directProfit' => number_format($withdraw->directProfit, 2, '.', ''),
                    'directType' => $withdraw->directType,
                    'directComment' => $withdraw->directComment,
                    'directCloseTime' => $withdraw->directCloseTime,
                ];
            })->toArray();

            // 步骤11：计算提现总金额
            $totalAmount = UserTrade::query()
                ->where('open_price', 0)
                ->where('profit', '<', 0)
                ->where('cmd', 6)
                ->when(!empty($withdrawSource), function ($q) use ($withdrawSource) {
                    $q->where('comment', 'like', '%' . $withdrawSource . '%');
                }, function ($q) use ($withdrawKeywords) {
                    $q->whereRaw("comment REGEXP ?", [$withdrawKeywords]);
                })
                ->when($userId, function ($q) use ($userId) {
                    $q->where('user_id', $userId);
                })
                ->when($withdrawId, function ($q) use ($withdrawId) {
                    $q->where('id', 'like', '%' . $withdrawId . '%');
                })
                ->when($startDate && $endDate, function ($q) use ($startDate, $endDate) {
                    $q->whereBetween('open_time', [
                        $startDate . ' 00:00:00',
                        $endDate . ' 23:59:59'
                    ]);
                })
                ->sum('profit');

            // 步骤12：构建汇总行
            $footer = [
                'order_no' => trans('systemlanguage.total'),
                'userId' => '',
                'username' => '',
                'directProfit' => number_format($totalAmount, 2, '.', ''),
                'directType' => '',
                'directComment' => '',
                'directCloseTime' => '',
            ];

            // 步骤13：返回数据（兼容Layui表格格式）
            return $this->success([
                'data' => $withdrawalsData,
                'count' => $total,
                'footer' => [$footer], // Layui表格的footer格式
            ], __('admin.withdrawal_list_fetched'));

        } catch (\Exception $e) {
            \Log::error('WithdrawController.withdrawFlow error: ' . $e->getMessage());
            \Log::error($e->getTraceAsString());
            return $this->serverErrorResponse();
        }
    }

    /**
     * 获取提现关键词正则表达式。
     *
     * 功能说明：
     * - 定义所有提现相关的关键词，用于从user_trades的comment字段筛选提现记录。
     * - 包括：取款、佣金转出、佣金转出退回等。
     *
     * @return string 提现关键词正则表达式。
     */
    private function getWithdrawKeywords(): string
    {
        // 提现关键词列表
        $keywords = [
            '-ZH',          // 佣金转出
            '-TH',          // 佣金转出退回
            '-QK',          // 取款
            'WBIN',         // 取款
            'DBCT',         // 佣金转出
            'DBCR',         // 佣金转出退回
            'WBAD',         // 平台出金
            'Withdrawal',   // 提现（英文）
            '出金',          // 出金（中文）
            '取款',          // 取款（中文）
        ];

        // 构建正则表达式
        return '(' . implode('|', array_map(function ($keyword) {
            return preg_quote($keyword, '/');
        }, $keywords)) . ')';
    }
}
