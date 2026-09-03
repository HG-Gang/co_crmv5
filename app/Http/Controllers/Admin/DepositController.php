<?php

/**
 * Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/09/03
 * Time: 14:30
 */

namespace App\Http\Controllers\Admin;

use App\Jobs\SettleDepositPayment;
use App\Models\DepositRecord;
use App\Models\PaymentSettlementOutbox;
use App\Models\UserTrade;
use App\Services\AdminDataScopeService;
use App\Constants\ResponseCode;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Throwable;

/**
 * 后台入金管理控制器。
 *
 * 文件功能：
 * - 数据来源为 deposit_records 表，负责后台入金记录列表、详情、审核通过、审核驳回和旧导入兼容入口。
 * - user 关系用于补充入金所属业务用户资料，页面按钮权限由 permissions.slug 控制，接口权限由 permissions.api_route 控制。
 * - AdminDataScopeService 用于限制不同管理员可查看和审核的入金数据范围，避免越权查看其他代理链路下的客户资金记录。
 *
 * 状态机：
 * - status 字段：01=待处理，02=已审核通过（status=02 表示入金已审核通过），09=已驳回；settlement_status：pending=待结算、processing=结算中、settled=已结算、unknown=未知。
 * - 审核通过不直接写 status=02：先置 payment_status=success、settlement_status=pending，并向 payment_settlement_outbox
 *   写入结算出箱事件，由 SettleDepositPayment/扫描器完成 MT4 结算后再推进结算状态；结算落地前不伪造“已通过”，避免资金口径失真。
 * - 审核驳回只写 status=09 与备注，不触发结算；已通过（02/settled）或结算中（processing/unknown）的记录拒绝重复审核。
 * - 失败语义：approve/reject 参数校验或状态冲突返回明确业务错误码，未知异常统一返回 SERVER_ERROR。
 */
class DepositController extends AdminBaseController
{
    /**
     * 后台数据范围服务。
     *
     * 参数逻辑说明：
     * - AdminDataScopeService 用于限制不同管理员可查看和审核的入金数据范围。
     *
     * @var AdminDataScopeService
     */
    protected $adminDataScopeService;

    /**
     * 构造函数。
     *
     * @param AdminDataScopeService $adminDataScopeService 后台数据范围服务，用于按管理员角色配置限制入金列表可见用户。
     */
    public function __construct(AdminDataScopeService $adminDataScopeService)
    {
        $this->adminDataScopeService = $adminDataScopeService;
    }

    /**
     * 获取后台入金记录列表。
     *
     * 参数逻辑说明：
     * - page 表示当前页码，默认第 1 页。
     * - per_page 表示每页数量，默认 15 条。
     * - status 表示入金审核状态，空值表示不限制状态。
     * - user_id 表示入金所属业务用户ID，对应 deposit_records.user_id。
     * - local_order_no 表示本地入金订单号，对应 deposit_records.local_order_no，使用模糊匹配。
     *
     * 返回结构说明：
     * - 保留 Laravel paginator 的全部键（data/total/current_page…），历史调用方零改造。
     * - 额外追加 summary：当前筛选条件下的入金统计，供后台入金页的独立统计区块展示。
     *   summary 与列表共用同一份筛选查询，因此卡片数字与表格行口径完全一致。
     *
     * @param Request $request HTTP 请求对象，承载 page、per_page、status、user_id、local_order_no 和当前 admin guard 用户。
     * @return \Illuminate\Http\JsonResponse 入金分页列表响应。
     */
    public function index(Request $request)
    {
        if ($userIdError = $this->validateUserIdFilter($request)) {
            return $userIdError;
        }

        $status = null;
        if ($statusError = $this->validateAndNormalizeStatusFilter($request, $status)) {
            return $statusError;
        }

        $page = $request->input('page', 1);
        $perPage = $request->input('per_page', 15);

        $query = DepositRecord::query()->with('user');
        if ($request->user('admin')) {
            $query = $this->adminDataScopeService->apply($query, $request->user('admin'), 'deposit', 'user_id');
        }

        if ($status !== null) {
            $query->where('status', $status);
        }

        if ($request->filled('user_id')) {
            $query->where('user_id', (int) $request->input('user_id'));
        }

        if ($request->filled('local_order_no')) {
            $query->where('local_order_no', 'LIKE', "%{$request->local_order_no}%");
        }

        $summary = $this->depositSummaryFor(clone $query);
        $deposits = $query->orderByDesc('created_at')->paginate($perPage, ['*'], 'page', $page);

        return $this->success(
            array_merge($deposits->toArray(), ['summary' => $summary]),
            __('admin.deposit_list_fetched')
        );
    }

    /**
     * 汇总当前筛选条件下的入金统计。
     *
     * 金额精度说明：
     * - 先把每行金额 CAST 成 DECIMAL(18,2) 再 SUM，让 MySQL 在十进制域内累加，
     *   PHP 侧拿到精确十进制字符串，避免 float 累加误差。
     *
     * 字段说明：
     * - total_records：命中记录数。
     * - total_deposit_amount：申请入金金额合计（deposit_records.amount）。
     * - total_actual_amount：实际到账金额合计（deposit_records.actual_amount）。
     * - approved_records：其中审核通过（status='02'）的记录数。
     *
     * @param \Illuminate\Database\Eloquent\Builder $query 已套用数据范围和筛选条件的入金查询。
     * @return array<string, mixed> 独立统计区块使用的汇总数据。
     */
    private function depositSummaryFor($query): array
    {
        $row = (clone $query)
            ->selectRaw(
                'COUNT(*) AS total_records,'
                . ' CAST(COALESCE(SUM(CAST(amount AS DECIMAL(18,2))), 0) AS DECIMAL(18,2)) AS total_deposit_amount,'
                . ' CAST(COALESCE(SUM(CAST(actual_amount AS DECIMAL(18,2))), 0) AS DECIMAL(18,2)) AS total_actual_amount,'
                . " SUM(CASE WHEN status = '02' THEN 1 ELSE 0 END) AS approved_records"
            )
            ->first();

        return [
            'total_records' => $row === null ? 0 : (int) $row->total_records,
            'total_deposit_amount' => $row === null ? '0.00' : (string) $row->total_deposit_amount,
            'total_actual_amount' => $row === null ? '0.00' : (string) $row->total_actual_amount,
            'approved_records' => $row === null ? 0 : (int) $row->approved_records,
        ];
    }

    /**
     * 获取入金详情。
     *
     * 参数逻辑说明：
     * - id 表示 deposit_records.id，可来自请求体；$id 为兼容后续 REST 路由保留的可选路径参数。
     * - user_id 表示入金所属业务用户ID，数据范围判断以记录中的 user_id 为准。
     * - 读取详情后必须执行 denyDepositAccessIfNeeded，避免管理员越权查看未授权客户的入金记录。
     *
     * @param Request $request HTTP 请求对象，承载 id 和当前 admin guard 用户。
     * @param int|null $id 可选路径参数中的 deposit_records.id。
     * @return \Illuminate\Http\JsonResponse 入金详情响应。
     */
    public function show(Request $request, $id = null)
    {
        // 当前后台路由为 POST /depositDetail，默认从请求体读取 id；保留可选路由参数用于兼容后续 REST 写法。
        $rawDepositId = $id ?: $request->input('id');
        if ($depositIdError = $this->validateDepositId($rawDepositId)) {
            return $depositIdError;
        }

        $depositId = (int) $rawDepositId;
        $deposit = DepositRecord::with('user')->find($depositId);
        if (!$deposit) {
            return $this->error(__('admin.deposit_not_found'), ResponseCode::DATA_NOT_FOUND);
        }

        $accessDenied = $this->denyDepositAccessIfNeeded($request, $deposit);
        if ($accessDenied) {
            return $accessDenied;
        }

        return $this->success($deposit, __('admin.deposit_detail_fetched'));
    }

    /**
     * approve 用于审核通过入金记录。
     *
     * 参数逻辑说明：
     * - id 表示 deposit_records.id，默认从请求体读取。
     * - 已通过判定为 status=02 或 settlement_status=settled；结算中（processing/unknown）不允许重复审核。
     * - 通过后只写 payment_status=success、settlement_status=pending 并创建结算出箱事件，不直接写 status=02。
     * - payment_time 表示审核通过时间，写入当前服务器时间；updated_by 表示执行审核动作的后台管理员标识。
     *
     * @param Request $request HTTP 请求对象，承载 id 和当前 admin guard 用户。
     * @param int|null $id 可选路径参数中的 deposit_records.id。
     * @return \Illuminate\Http\JsonResponse 入金审核通过响应。
     */
    public function approve(Request $request, $id = null)
    {
        try {
            // 当前后台路由为 POST /depositApprove，默认从请求体读取 id。
            $rawDepositId = $id ?: $request->input('id');
            if ($depositIdError = $this->validateDepositId($rawDepositId)) {
                return $depositIdError;
            }

            $depositId = (int) $rawDepositId;
            $deposit = DepositRecord::find($depositId);
            if (!$deposit) {
                return $this->error(__('admin.deposit_not_found'), ResponseCode::DATA_NOT_FOUND);
            }

            $accessDenied = $this->denyDepositAccessIfNeeded($request, $deposit);
            if ($accessDenied) {
                return $accessDenied;
            }

            // 事务外先做数据范围与状态预检，避免无谓加锁；行锁后的终检在事务内再执行一次。
            if ((string) $deposit->status === '02' || (string) $deposit->settlement_status === 'settled') {
                return $this->error(__('admin.deposit_already_approved'), ResponseCode::OPERATION_NOT_ALLOWED);
            }

            $settlementStatus = strtolower(trim((string) ($deposit->settlement_status ?? '')));
            if (in_array($settlementStatus, ['processing', 'unknown'], true)) {
                return $this->error(__('admin.deposit_settlement_in_progress'), ResponseCode::OPERATION_NOT_ALLOWED);
            }

            $dispatchOutboxId = null;
            DB::transaction(function () use ($deposit, $request, &$dispatchOutboxId) {
                /** @var DepositRecord $locked */
                $locked = DepositRecord::query()->whereKey($deposit->id)->lockForUpdate()->firstOrFail();

                if ((string) $locked->status === '02' || (string) $locked->settlement_status === 'settled') {
                    throw new \RuntimeException('deposit_already_approved');
                }

                $lockedSettlement = strtolower(trim((string) ($locked->settlement_status ?? '')));
                if (in_array($lockedSettlement, ['processing', 'unknown'], true)) {
                    throw new \RuntimeException('deposit_settlement_in_progress');
                }

                // 无出箱哈希时按本地订单要素生成防重哈希；出箱表以 payload_hash 去重，保证同单只入队一次结算。
                $payloadHash = trim((string) ($locked->provider_payload_hash ?? ''));
                if ($payloadHash === '') {
                    $payloadHash = hash(
                        'sha256',
                        (string) $locked->local_order_no . '|' . (string) $locked->amount . '|admin_manual'
                    );
                    $locked->provider_payload_hash = $payloadHash;
                }

                // 与支付回调成功路径对齐：先标记支付成功并进入待结算，再入队 MT4 结算；结算落地前不伪造 status=02。
                $locked->payment_status = 'success';
                $locked->settlement_status = 'pending';
                $locked->payment_time = now();
                $locked->updated_by = (string) (auth('admin')->id() ?? auth()->id() ?? 'admin');
                $locked->saveOrFail();

                // 以 event_type+deposit_record_id 唯一键创建出箱事件；pending/retryable 状态才允许重新派发，避免重复结算。
                $outbox = PaymentSettlementOutbox::firstOrCreate(
                    ['event_type' => 'deposit_settlement', 'deposit_record_id' => $locked->id],
                    [
                        'local_order_no' => (string) $locked->local_order_no,
                        'status' => 'pending',
                        'attempts' => 0,
                        'payload_hash' => $payloadHash,
                        'available_at' => now(),
                    ]
                );

                if (in_array((string) $outbox->status, ['pending', 'retryable'], true) || $outbox->wasRecentlyCreated) {
                    if ((string) $outbox->status !== 'pending') {
                        $outbox->status = 'pending';
                        $outbox->available_at = now();
                        $outbox->locked_at = null;
                        $outbox->last_error_code = null;
                        $outbox->saveOrFail();
                    }
                    $dispatchOutboxId = (int) $outbox->id;
                }
            });

            // 事务提交后再派发结算任务；投递失败仅记录日志，由出箱扫描器兜底重试。
            if ($dispatchOutboxId !== null) {
                try {
                    SettleDepositPayment::dispatch($dispatchOutboxId)->afterCommit();
                } catch (Throwable $exception) {
                    Log::error('Admin deposit approve settlement dispatch failed; scanner will retry.', [
                        'outbox_id' => $dispatchOutboxId,
                        'exception_class' => get_class($exception),
                    ]);
                }
            }

            return $this->success([], __('admin.deposit_approved'));
        } catch (\RuntimeException $e) {
            if ($e->getMessage() === 'deposit_already_approved') {
                return $this->error(__('admin.deposit_already_approved'), ResponseCode::OPERATION_NOT_ALLOWED);
            }
            if ($e->getMessage() === 'deposit_settlement_in_progress') {
                return $this->error(__('admin.deposit_settlement_in_progress'), ResponseCode::OPERATION_NOT_ALLOWED);
            }

            return $this->serverErrorResponse();
        } catch (\Exception $e) {
            return $this->serverErrorResponse();
        }
    }

    /**
     * reject 用于驳回入金记录。
     *
     * 参数逻辑说明：
     * - id 表示 deposit_records.id，默认从请求体读取。
     * - status=09 表示入金审核驳回或失败，写入后该记录不再作为已通过入金处理。
     * - reason 表示驳回原因，写入 deposit_records.remarks，允许为空字符串。
     * - 已经 status=02 的入金记录不允许驳回，避免已通过资金记录被回退。
     *
     * @param Request $request HTTP 请求对象，承载 id、reason 和当前 admin guard 用户。
     * @param int|null $id 可选路径参数中的 deposit_records.id。
     * @return \Illuminate\Http\JsonResponse 入金驳回响应。
     */
    public function reject(Request $request, $id = null)
    {
        try {
            // 当前后台路由为 POST /depositReject，默认从请求体读取 id。
            $rawDepositId = $id ?: $request->input('id');
            if ($depositIdError = $this->validateDepositId($rawDepositId)) {
                return $depositIdError;
            }

            $depositId = (int) $rawDepositId;
            $deposit = DepositRecord::find($depositId);
            if (!$deposit) {
                return $this->error(__('admin.deposit_not_found'), ResponseCode::DATA_NOT_FOUND);
            }

            $accessDenied = $this->denyDepositAccessIfNeeded($request, $deposit);
            if ($accessDenied) {
                return $accessDenied;
            }

            if ($deposit->status == '02') {
                return $this->error(__('admin.deposit_already_approved'), ResponseCode::OPERATION_NOT_ALLOWED);
            }

            // 驳回只改本地状态与备注，不写结算出箱；已通过(status=02)记录已在上面拦截，避免资金状态被回退。
            $deposit->update([
                'status' => '09', // status=09 表示入金审核驳回或失败。
                'remarks' => $request->input('reason', ''),
                'updated_by' => auth()->id() ?? 'admin',
            ]);

            return $this->success([], __('admin.deposit_rejected'));
        } catch (\Exception $e) {
            return $this->serverErrorResponse();
        }
    }

    /**
     * 旧入金导入兼容入口。
     *
     * import() 参数说明：
     * - Request $request 当前 HTTP 请求对象，旧接口用于接收 CSV 上传文件或单条导入参数。
     * - 复用 BatchAmountImportController::createDepositImport，确保旧入口和新批量入金导入模块共用同一套校验与落库逻辑。
     * - 旧入口不再返回占位成功，避免调用方误以为导入已经完成但没有写入 deposit_imports。
     *
     * @param Request $request 当前 HTTP 请求对象，承载旧导入入口可能提交的上传文件和筛选参数。
     * @return \Illuminate\Http\JsonResponse 返回真实批量入金导入处理结果。
     */
    public function import(Request $request)
    {
        return app(BatchAmountImportController::class)->createDepositImport($request);
    }

    /**
     * denyDepositAccessIfNeeded 用于按当前管理员数据范围判断是否允许访问指定入金记录。
     *
     * 参数逻辑说明：
     * - $request 表示当前 HTTP 请求对象，用于读取 admin guard 下的登录管理员。
     * - $deposit 表示入金记录，权限归属以 deposit_records.user_id 业务用户ID为准。
     * - canAccessUser 返回 false 时直接返回权限不足响应，避免后续详情、审核通过或驳回逻辑继续执行。
     *
     * @param Request $request 当前请求对象，用于读取 admin guard 下的登录管理员。
     * @param DepositRecord $deposit 入金记录；权限归属以记录中的 user_id 业务用户ID为准。
     * @return \Illuminate\Http\JsonResponse|null 返回 JsonResponse 表示拒绝访问；返回 null 表示允许继续执行业务逻辑。
     */
    private function denyDepositAccessIfNeeded(Request $request, DepositRecord $deposit)
    {
        $admin = $request->user('admin');
        if (!$admin) {
            return null;
        }

        if ($this->adminDataScopeService->canAccessUser($admin, $deposit->user_id, 'user')) {
            return null;
        }

        return $this->error(__('response.permission_denied'), ResponseCode::PERMISSION_DENIED);
    }

    /**
     * 校验列表 user_id 筛选参数必须为整数。
     *
     * @param Request $request 当前请求对象。
     * @return \Illuminate\Http\JsonResponse|null 校验失败返回统一错误响应，未传或通过时返回 null。
     */
    private function validateUserIdFilter(Request $request)
    {
        if (!$request->filled('user_id')) {
            return null;
        }

        $validator = Validator::make(['user_id' => $request->input('user_id')], [
            'user_id' => 'integer',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), ResponseCode::VALIDATION_FAILED);
        }

        return null;
    }

    /**
     * 校验并归一化列表 status 筛选值。
     *
     * 旧页面传 0/1/2，新页面传 01/02/09，这里统一映射为入库状态，保证新旧入口筛选口径一致。
     *
     * @param Request $request 当前请求对象。
     * @param string|null &$normalizedStatus 引用传出归一化后的状态值；未传 status 时为 null。
     * @return \Illuminate\Http\JsonResponse|null 非法状态值返回统一校验错误，否则返回 null。
     */
    private function validateAndNormalizeStatusFilter(Request $request, &$normalizedStatus)
    {
        $normalizedStatus = null;
        if (!$request->filled('status')) {
            return null;
        }

        $statusMap = [
            '0' => '01',
            '1' => '02',
            '2' => '09',
            '01' => '01',
            '02' => '02',
            '09' => '09',
        ];

        $rawStatus = $request->input('status');
        if ((!is_string($rawStatus) && !is_int($rawStatus))
            || !array_key_exists((string) $rawStatus, $statusMap)) {
            return $this->error(
                __('validation.in', ['attribute' => 'status']),
                ResponseCode::VALIDATION_FAILED
            );
        }

        $normalizedStatus = $statusMap[(string) $rawStatus];

        return null;
    }

    /**
     * 校验入金记录主键必须为整数，避免字符串被强制转换后误操作其他记录。
     *
     * @param mixed $id deposit_records.id 原始请求值。
     * @return \Illuminate\Http\JsonResponse|null 校验失败返回统一错误响应，通过时返回 null。
     */
    private function validateDepositId($id)
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
     * 获取入金流水列表（从旧项目DepositAmountController迁移）。
     *
     * depositFlow() 参数说明：
     * - page：当前页码，默认第1页。
     * - per_page/limit：每页数量，兼容Layui的limit参数，默认15条。
     * - user_id：用户ID，筛选指定用户的入金记录。
     * - deposit_id：入金订单号，模糊匹配。
     * - direct_deposit_source：入金来源，筛选入金类型（充值、返佣、转账等）。
     * - deposit_startdate：入金开始日期，默认2024-01-01。
     * - deposit_enddate：入金结束日期，默认当前日期。
     * - deposit_channel：入金渠道筛选。
     *
     * 功能逻辑说明：
     * - 查询user_trades表中CMD=6且PROFIT>0的记录（余额增加记录）。
     * - 根据COMMENT字段判断入金来源（充值、返佣、转账、出金退回等）。
     * - 关联deposit_records表获取实际支付金额和渠道信息。
     * - 计算入金总金额汇总。
     * - 支持按入金来源、渠道、日期范围筛选。
     *
     * @param Request $request 当前HTTP请求对象。
     * @return \Illuminate\Http\JsonResponse 返回入金流水列表及汇总数据。
     */
    public function depositFlow(Request $request)
    {
        try {
            // 读取分页与筛选参数；入金来源码与渠道决定后续口径过滤分支。
            $page = (int) $request->input('page', 1);
            $perPage = (int) $request->input('per_page', $request->input('limit', 15));

            $userId = $request->input('user_id');
            $depositId = $request->input('deposit_id');
            $depositSource = $request->input('direct_deposit_source');
            $startDate = $request->input('deposit_startdate', '2024-01-01');
            $endDate = $request->input('deposit_enddate', date('Y-m-d'));
            $depositChannel = $request->input('deposit_channel');

            // 未指定来源码时用旧项目入金关键字正则过滤，避免普通正向调账误入入金流水。
            $depositKeywords = $this->getDepositKeywords();

            // 基础口径：CMD=6 余额调整、开仓价为 0、盈利大于 0 视为入金余额记录。
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
                ->where('user_trades.profit', '>', 0) // PROFIT>0表示入金
                ->where('user_trades.cmd', 6); // CMD=6表示余额调整

            // 指定来源码时按关键词筛选，否则走全量入金正则。
            if (!empty($depositSource)) {
                $query->where('user_trades.comment', 'like', '%' . $depositSource . '%');
            } else {
                $query->whereRaw("user_trades.comment REGEXP ?", [$depositKeywords]);
            }

            // 用户 ID 精确匹配，订单号模糊匹配。
            if ($userId) {
                $query->where('user_trades.user_id', $userId);
            }

            if ($depositId) {
                $query->where('user_trades.id', 'like', '%' . $depositId . '%');
            }

            // 有渠道筛选或平台批量入金(DBUN)时，需关联已审核通过入金记录取通道与时间口径；否则直接用交易关闭时间过滤。
            if ($depositChannel || $depositSource == 'DBUN') {
                $query->join('deposit_records', function ($join) use ($depositChannel) {
                    $join->on('deposit_records.mt4_ticket', '=', 'user_trades.ticket')
                        ->where('deposit_records.status', '02'); // 只关联已审核通过的入金记录。

                    if (!empty($depositChannel)) {
                        $join->where('deposit_records.channel', $depositChannel);
                    }
                });

                if ($startDate && $endDate) {
                    $query->whereBetween('deposit_records.updated_at', [
                        strtotime($startDate . ' 00:00:00'),
                        strtotime($endDate . ' 23:59:59')
                    ]);
                }

                $query->addSelect([
                    'deposit_records.id as dep_id',
                    'deposit_records.local_order_no as dep_mt4_id',
                    'deposit_records.channel as dep_channel',
                    'deposit_records.channel_order_no as dep_channel_no',
                    'deposit_records.created_at as rec_crt_date',
                    'deposit_records.updated_at as rec_upd_date',
                ]);
            } else {
                if ($startDate && $endDate) {
                    $query->whereBetween('user_trades.close_time', [
                        $startDate . ' 00:00:00',
                        $endDate . ' 23:59:59'
                    ]);
                }
            }

            // 统计总数供分页使用，汇总金额在下方按同一筛选口径独立统计。
            $total = $query->count();

            $deposits = $query->orderByDesc('user_trades.close_time')
                ->skip(($page - 1) * $perPage)
                ->take($perPage)
                ->get();

            // 逐行补齐旧表格所需字段：已支付入金的实际到账金额、通道与用户名。
            $depositsData = $deposits->map(function ($deposit) {
                $depositRecord = DB::table('deposit_records')
                    ->where('mt4_ticket', $deposit->order_no)
                    ->where('status', '02')
                    ->first();

                $userInfo = DB::table('user_infos')
                    ->where('user_id', $deposit->userId)
                    ->first();

                return [
                    'order_no' => $deposit->order_no,
                    'userId' => $deposit->userId,
                    'username' => $userInfo->user_name ?? '',
                    'directProfit' => number_format($deposit->directProfit, 2, '.', ''),
                    'depamount' => $depositRecord ? number_format($depositRecord->amount, 2, '.', '') : '0.00',
                    'directType' => $deposit->directType,
                    'directComment' => $deposit->directComment,
                    'depoutTrande' => $depositRecord->third_party_order_no ?? '',
                    'dep_channel' => $depositRecord->channel ?? '',
                    'dep_channel_no' => $depositRecord->channel_order_no ?? '',
                    'directCloseTime' => $deposit->directCloseTime,
                ];
            })->toArray();

            // 汇总金额按与列表相同的关键词与筛选口径统计，供页脚合计展示。
            $totalAmount = UserTrade::query()
                ->where('open_price', 0)
                ->where('profit', '>', 0)
                ->where('cmd', 6)
                ->when(!empty($depositSource), function ($q) use ($depositSource) {
                    $q->where('comment', 'like', '%' . $depositSource . '%');
                }, function ($q) use ($depositKeywords) {
                    $q->whereRaw("comment REGEXP ?", [$depositKeywords]);
                })
                ->when($userId, function ($q) use ($userId) {
                    $q->where('user_id', $userId);
                })
                ->when($depositId, function ($q) use ($depositId) {
                    $q->where('id', 'like', '%' . $depositId . '%');
                })
                ->when($startDate && $endDate, function ($q) use ($startDate, $endDate) {
                    $q->whereBetween('close_time', [
                        $startDate . ' 00:00:00',
                        $endDate . ' 23:59:59'
                    ]);
                })
                ->sum('profit');

            // 页脚汇总行只放合计金额，其余字段留空以对齐 Layui 表格结构。
            $footer = [
                'order_no' => trans('systemlanguage.total'),
                'userId' => '',
                'username' => '',
                'directProfit' => number_format($totalAmount, 2, '.', ''),
                'depamount' => '',
                'directType' => '',
                'directComment' => '',
                'depoutTrande' => '',
                'directCloseTime' => '',
            ];

            // 返回兼容 Layui 表格的 data/count/footer 结构。
            return $this->success([
                'data' => $depositsData,
                'count' => $total,
                'footer' => [$footer], // Layui表格的footer格式
            ], __('admin.deposit_list_fetched'));

        } catch (\Exception $e) {
            \Log::error('DepositController.depositFlow error: ' . $e->getMessage());
            \Log::error($e->getTraceAsString());
            return $this->serverErrorResponse();
        }
    }

    /**
     * 获取入金关键词正则表达式。
     *
     * 功能说明：
     * - 定义所有入金相关的关键词，用于从user_trades的comment字段筛选入金记录。
     * - 包括：充值、返佣、佣金转户、批量入金、出金退回、平台入金等。
     *
     * @return string 入金关键词正则表达式。
     */
    private function getDepositKeywords(): string
    {
        $keywords = [
            '-ZH',      // 佣金转户
            '-TH',      // 佣金转户退回
            '-CZ',      // 充值
            '-FY',      // 返佣
            '-RJ',      // 批量入金
            '-CJTH',    // 出金失败退回
            'DBAD',     // 平台入金
            'DBUN',     // 批量入金
            'DBCT',     // 佣金转户
            'DBCR',     // 佣金转户退回
            'DBCN',     // 返佣
            'WBIR',     // 出金失败退回
            'Adj',      // 调整
            'Deposit',  // 入金（英文）
            '入金',      // 入金（中文）
            '充值',      // 充值（中文）
        ];

        return '(' . implode('|', array_map(function ($keyword) {
            return preg_quote($keyword, '/');
        }, $keywords)) . ')';
    }
}
