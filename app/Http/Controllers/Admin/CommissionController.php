<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/02
 * Time: 09:09
 */

namespace App\Http\Controllers\Admin;

use App\Models\CommissionRecord;
use App\Models\Admin;
use App\Services\AdminDataScopeService;
use App\Services\CommissionTransfer\CommissionTransferReconciliationService;
use App\Constants\ResponseCode;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/**
 * 后台返佣结算控制器。
 *
 * 文件功能：
 * - 负责后台返佣记录列表、详情、单笔结算和批量结算。
 * - 返佣记录权限以 commission_records.agent_id 为归属字段，通过 AdminDataScopeService 限制不同管理员可见代理范围。
 * - settle_status 是返佣结算状态字段，结算接口只把未结算记录更新为已结算，不直接计算返佣金额。
 *
 * 适用场景：
 * - 后台返佣管理页面（列表/详情/单笔结算/批量结算）。
 * - 佣金转账人工对账页面（对账案例列表、案例详情、提交对账决定）。
 */
class CommissionController extends AdminBaseController
{
    /**
     * 后台数据范围服务。
     *
     * @var AdminDataScopeService
     */
    protected $adminDataScopeService;

    /**
     * 佣金转账人工对账服务：驱动转账 Saga 对账案例的列表、详情与决定提交（放行/人工处理）。
     * 本控制器只做编排，状态机与失败关闭语义都封装在该服务内；缺失时对账页面所有入口不可用，
     * 也不允许绕过它直接改 commission_transfers 状态。
     *
     * @var CommissionTransferReconciliationService
     */
    protected $commissionTransferReconciliationService;

    /**
     * 构造函数。
     *
     * @param AdminDataScopeService $adminDataScopeService 后台数据范围服务，用于按管理员角色配置限制返佣列表可见代理。
     */
    public function __construct(
        AdminDataScopeService $adminDataScopeService,
        CommissionTransferReconciliationService $commissionTransferReconciliationService
    )
    {
        $this->adminDataScopeService = $adminDataScopeService;
        $this->commissionTransferReconciliationService = $commissionTransferReconciliationService;
    }

    /**
     * 分页查询待人工对账的佣金转账案例。
     *
     * @param Request $request 当前请求对象，承载 page、per_page 分页参数；admin guard 下必须为 Admin 实例。
     * @return \Illuminate\Http\JsonResponse 对账案例分页列表；管理员未登录时返回 AUTH_FAILED。
     */
    public function reconciliationCases(Request $request)
    {
        $admin = $request->user('admin');
        if (!$admin instanceof Admin) {
            return $this->error(__('response.auth_failed'), ResponseCode::AUTH_FAILED);
        }

        $validator = Validator::make($request->all(), [
            'page' => 'nullable|integer|min:1',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);
        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), ResponseCode::VALIDATION_FAILED);
        }

        $cases = $this->commissionTransferReconciliationService->cases(
            $admin,
            (int) $request->input('page', 1),
            (int) $request->input('per_page', 15)
        );

        return $this->success($cases);
    }

    /**
     * 查询单笔佣金转账对账案例详情。
     *
     * @param Request $request 当前请求对象，用于读取 admin guard 登录管理员。
     * @param int|string $transfer 佣金转账记录 ID，对应 commission_transfers.id。
     * @return \Illuminate\Http\JsonResponse 对账详情响应；记录不存在或越权时由 reconciliationResultResponse 映射对应错误码。
     */
    public function reconciliationCase(Request $request, $transfer)
    {
        $admin = $request->user('admin');
        if (!$admin instanceof Admin) {
            return $this->error(__('response.auth_failed'), ResponseCode::AUTH_FAILED);
        }

        $result = $this->commissionTransferReconciliationService->detail($admin, (int) $transfer);

        return $this->reconciliationResultResponse($result);
    }

    /**
     * 提交佣金转账人工对账决定与证据。
     *
     * 兼容语义：
     * - decision 必须为服务定义的决策状态；withdraw/deposit/compensation 三段资金结果与可选引用号均需提交。
     * - source_balance_after/target_balance_after 为对账后余额快照，可为空。
     *
     * @param Request $request 当前请求对象，承载对账决定与证据字段，来源 IP 写入审计日志。
     * @param int|string $transfer 佣金转账记录 ID，对应 commission_transfers.id。
     * @return \Illuminate\Http\JsonResponse 对账结果响应；证据非法、记录不存在或越权时返回对应错误码。
     */
    public function reconcileTransfer(Request $request, $transfer)
    {
        $admin = $request->user('admin');
        if (!$admin instanceof Admin) {
            return $this->error(__('response.auth_failed'), ResponseCode::AUTH_FAILED);
        }

        // 统一规范化可选字段（空串转 null）后再整体校验，避免空字符串绕过 nullable 规则。
        $payload = [
            'decision' => trim((string) $request->input('decision', '')),
            'external_reference' => trim((string) $request->input('external_reference', '')),
            'withdraw_status' => trim((string) $request->input('withdraw_status', '')),
            'withdraw_reference' => $this->trimNullable($request->input('withdraw_reference')),
            'deposit_status' => trim((string) $request->input('deposit_status', '')),
            'deposit_reference' => $this->trimNullable($request->input('deposit_reference')),
            'compensation_status' => trim((string) $request->input('compensation_status', '')),
            'compensation_reference' => $this->trimNullable($request->input('compensation_reference')),
            'source_balance_after' => $this->trimNullable($request->input('source_balance_after')),
            'target_balance_after' => $this->trimNullable($request->input('target_balance_after')),
        ];
        $fundingStatuses = implode(',', CommissionTransferReconciliationService::fundingStatuses());
        $validator = Validator::make($payload, [
            'decision' => 'required|string|in:' . implode(',', CommissionTransferReconciliationService::decisionStatuses()),
            'external_reference' => 'required|string|max:100',
            'withdraw_status' => 'required|string|in:' . $fundingStatuses,
            'withdraw_reference' => 'nullable|string|max:100',
            'deposit_status' => 'required|string|in:' . $fundingStatuses,
            'deposit_reference' => 'nullable|string|max:100',
            'compensation_status' => 'required|string|in:' . $fundingStatuses,
            'compensation_reference' => 'nullable|string|max:100',
            'source_balance_after' => 'nullable|string|max:32',
            'target_balance_after' => 'nullable|string|max:32',
        ]);
        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), ResponseCode::VALIDATION_FAILED);
        }

        // 数据范围与状态机校验由服务内部完成，控制器只做字段形状校验，不在此重复业务判断。
        $result = $this->commissionTransferReconciliationService->reconcile(
            $admin,
            (int) $transfer,
            [
                'decision' => $payload['decision'],
                'withdraw_status' => $payload['withdraw_status'],
                'withdraw_reference' => $payload['withdraw_reference'],
                'deposit_status' => $payload['deposit_status'],
                'deposit_reference' => $payload['deposit_reference'],
                'compensation_status' => $payload['compensation_status'],
                'compensation_reference' => $payload['compensation_reference'],
                'source_balance_after' => $payload['source_balance_after'],
                'target_balance_after' => $payload['target_balance_after'],
            ],
            $payload['external_reference'],
            (string) $request->ip()
        );

        return $this->reconciliationResultResponse($result);
    }

    /**
     * 将服务返回的 result 码映射为 HTTP 响应。
     *
     * @param array{result:string, transfer?:\App\Models\CommissionTransfer} $result 服务返回结果，result 取值为 ok/not_found/forbidden/invalid_decision/invalid_evidence。
     * @return \Illuminate\Http\JsonResponse ok 时返回转账记录，其余按失败语义映射到对应错误码。
     */
    private function reconciliationResultResponse(array $result)
    {
        if ($result['result'] === 'ok') {
            return $this->success($result['transfer']);
        }
        if ($result['result'] === 'not_found') {
            return $this->error(__('response.data_not_found'), ResponseCode::DATA_NOT_FOUND);
        }
        if ($result['result'] === 'forbidden') {
            return $this->error(__('response.permission_denied'), ResponseCode::PERMISSION_DENIED);
        }
        if (in_array($result['result'], ['invalid_decision', 'invalid_evidence'], true)) {
            return $this->error(
                $result['error_code'] ?? __('response.validation_failed'),
                ResponseCode::VALIDATION_FAILED
            );
        }

        return $this->error(__('response.operation_not_allowed'), ResponseCode::OPERATION_NOT_ALLOWED);
    }

    /**
     * 规范化可选字符串参数。
     *
     * @param mixed $value 请求中的可选引用号或余额快照，可能为 null。
     * @return string|null null 或 trim 后为空串的输入统一归一为 null，避免空字符串作为“有值”写入对账证据。
     */
    private function trimNullable($value): ?string
    {
        if ($value === null) {
            return null;
        }
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    /**
     * 获取返佣结算记录列表。
     *
     * index() 参数说明：
     * - page 表示当前页码，默认第 1 页。
     * - per_page 表示每页数量，默认 15 条。
     * - agent_id 表示返佣所属代理用户 ID，对应 commission_records.agent_id。
     * - settle_status 表示结算状态，1=待结算，2=已结算。
     * - 当前管理员登录后会先通过 AdminDataScopeService 追加代理数据范围，避免列表展示越权返佣记录。
     *
     * @param Request $request 当前 HTTP 请求对象，承载分页参数、筛选条件和 admin guard 登录管理员。
     * @return \Illuminate\Http\JsonResponse 返回返佣分页列表，包含 agent 与 parent 关联信息。
     */
    public function index(Request $request)
    {
        if ($agentIdError = $this->validateAgentIdFilter($request)) {
            return $agentIdError;
        }
        if ($settleStatusError = $this->validateSettleStatusFilter($request)) {
            return $settleStatusError;
        }

        $page = $request->input('page', 1);
        $perPage = $request->input('per_page', 15);

        $query = CommissionRecord::query()->with(['agent', 'parent']);
        // 先叠加管理员代理数据范围，再应用筛选条件，保证越权记录在任何筛选组合下都不可见。
        if ($request->user('admin')) {
            $query = $this->adminDataScopeService->apply($query, $request->user('admin'), 'agent', 'agent_id');
        }

        if ($request->filled('agent_id')) {
            $query->where('agent_id', (int) $request->input('agent_id'));
        }

        if ($request->filled('settle_status')) {
            $query->where('settle_status', (int) $request->input('settle_status'));
        }

        $records = $query->orderByDesc('created_at')->paginate($perPage, ['*'], 'page', $page);

        return $this->success($records, __('admin.commission_list_fetched'));
    }

    /**
     * 获取返佣结算详情。
     *
     * show() 参数说明：
     * - $request 当前 HTTP 请求对象；旧后台 POST 入口可从请求体读取 id。
     * - $id 表示返佣记录主键，对应 commission_records.id；为空时兼容从请求体读取。
     * - 读取详情后仍会调用 denyCommissionAccessIfNeeded() 校验当前管理员是否可访问该代理返佣。
     *
     * @param Request $request 当前 HTTP 请求对象，承载 id 和 admin guard 登录管理员。
     * @param int|string|null $id 返佣记录主键，可为空以兼容旧 POST 请求体参数。
     * @return \Illuminate\Http\JsonResponse 返佣详情响应。
     */
    public function show(Request $request, $id = null)
    {
        // 当前后台详情接口若接入路由时，可从请求体读取 id；保留可选路由参数用于兼容 REST 写法。
        $rawCommissionId = $id ?: $request->input('id');
        if ($commissionIdError = $this->validateCommissionId($rawCommissionId)) {
            return $commissionIdError;
        }

        $commissionId = (int) $rawCommissionId;
        $record = CommissionRecord::with(['agent', 'parent'])->find($commissionId);
        if (!$record) {
            return $this->error(__('admin.commission_record_not_found'), ResponseCode::DATA_NOT_FOUND);
        }

        $accessDenied = $this->denyCommissionAccessIfNeeded($request, $record);
        if ($accessDenied) {
            return $accessDenied;
        }

        return $this->success($record, __('admin.commission_detail_fetched'));
    }

    /**
     * 结算单条返佣记录。
     *
     * settle() 参数说明：
     * - $request 当前 HTTP 请求对象；旧后台 POST /commissionSettle 默认从请求体读取 id。
     * - $id 表示返佣记录主键，对应 commission_records.id。
     * - settle_status=2 表示已结算；已结算记录不允许重复结算。
     *
     * @param Request $request 当前 HTTP 请求对象，承载 id 和 admin guard 登录管理员。
     * @param int|string|null $id 返佣记录主键，可为空以兼容旧 POST 请求体参数。
     * @return \Illuminate\Http\JsonResponse 单笔返佣结算结果响应。
     */
    public function settle(Request $request, $id = null)
    {
        try {
            // 当前后台路由为 POST /commissionSettle，默认从请求体读取 id。
            $rawCommissionId = $id ?: $request->input('id');
            if ($commissionIdError = $this->validateCommissionId($rawCommissionId)) {
                return $commissionIdError;
            }

            $commissionId = (int) $rawCommissionId;
            $record = CommissionRecord::find($commissionId);
            if (!$record || $record->settle_status == 2) {
                return $this->error(__('admin.commission_record_not_found_or_settled'), ResponseCode::DATA_NOT_FOUND);
            }

            $accessDenied = $this->denyCommissionAccessIfNeeded($request, $record);
            if ($accessDenied) {
                return $accessDenied;
            }

            // settle_status=2 表示已结算；这里只更新状态，不重新计算返佣金额。
            $record->update(['settle_status' => 2]);

            return $this->success([], __('admin.commission_settled'));
        } catch (\Exception $e) {
            return $this->serverErrorResponse();
        }
    }

    /**
     * 批量结算多条返佣记录。
     *
     * batchSettle() 参数说明：
     * - ids 表示待批量结算的返佣记录 ID 数组，对应 commission_records.id。
     * - 批量结算前逐条校验数据范围，任何一条记录越权都会终止本次批量操作。
     * - 只更新 settle_status=1 的待结算记录，避免重复更新已结算记录。
     *
     * @param Request $request 当前 HTTP 请求对象，承载 ids 数组和 admin guard 登录管理员。
     * @return \Illuminate\Http\JsonResponse 批量结算结果响应。
     */
    public function batchSettle(Request $request)
    {
        try {
            $ids = $request->input('ids', []);
            if (empty($ids)) {
                return $this->error(__('admin.no_ids_provided'), ResponseCode::VALIDATION_FAILED);
            }

            // 批量结算前逐条校验数据范围，避免混入越权记录后被一次性更新。
            $records = CommissionRecord::whereIn('id', $ids)->get();
            foreach ($records as $record) {
                $accessDenied = $this->denyCommissionAccessIfNeeded($request, $record);
                if ($accessDenied) {
                    return $accessDenied;
                }
            }

            // 权限校验通过后一次批量更新；只影响 settle_status=1 的待结算记录，避免覆盖已结算状态。
            CommissionRecord::whereIn('id', $ids)->where('settle_status', 1)->update(['settle_status' => 2]);

            return $this->success([], __('admin.batch_settlement_completed'), ResponseCode::BATCH_SUCCESS);
        } catch (\Exception $e) {
            return $this->serverErrorResponse();
        }
    }

    /**
     * 按当前后台管理员的数据范围判断是否可以访问指定返佣记录。
     *
     * denyCommissionAccessIfNeeded() 参数说明：
     * - $request 当前 HTTP 请求对象，用于读取 admin guard 下的登录管理员。
     * - $record 表示待访问或待结算的返佣记录。
     * - agent_id 作为数据范围判断字段，交给 AdminDataScopeService 判断当前管理员是否可访问该代理。
     *
     * @param Request $request 当前请求对象，用于读取 admin guard 下的登录管理员。
     * @param CommissionRecord $record 返佣记录；权限归属以记录中的 agent_id 业务代理ID为准。
     * @return \Illuminate\Http\JsonResponse|null 返回 JsonResponse 表示拒绝访问；返回 null 表示允许继续执行业务逻辑。
     */
    private function denyCommissionAccessIfNeeded(Request $request, CommissionRecord $record)
    {
        $admin = $request->user('admin');
        if (!$admin) {
            return null;
        }

        if ($this->adminDataScopeService->canAccessUser($admin, $record->agent_id, 'agent')) {
            return null;
        }

        return $this->error(__('response.permission_denied'), ResponseCode::PERMISSION_DENIED);
    }

    /**
     * 校验列表 agent_id 筛选参数必须为整数。
     *
     * @param Request $request 当前请求对象。
     * @return \Illuminate\Http\JsonResponse|null 校验失败返回统一错误响应，未传或通过时返回 null。
     */
    private function validateAgentIdFilter(Request $request)
    {
        if (!$request->filled('agent_id')) {
            return null;
        }

        $validator = Validator::make(['agent_id' => $request->input('agent_id')], [
            'agent_id' => 'integer',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), ResponseCode::VALIDATION_FAILED);
        }

        return null;
    }

    /**
     * 校验返佣列表结算状态筛选值。
     *
     * 参数逻辑说明：
     * - settle_status 对应 commission_records.settle_status，只允许 1=待结算、2=已结算。
     * - 这里使用字符串精确枚举，避免 Laravel in 规则或 PHP 类型转换把 1abc、01 等非法值当成合法数字前缀。
     * - 返回 null 表示允许继续拼接查询；返回 JsonResponse 表示参数失败并停止读取返佣记录。
     *
     * @param Request $request 当前 HTTP 请求对象，承载 settle_status 筛选参数。
     * @return \Illuminate\Http\JsonResponse|null 返回校验失败响应或允许继续执行。
     */
    private function validateSettleStatusFilter(Request $request)
    {
        if (!$request->filled('settle_status')) {
            return null;
        }

        $allowedStatuses = ['1' => true, '2' => true];
        if (array_key_exists((string) $request->input('settle_status'), $allowedStatuses)) {
            return null;
        }

        return $this->error(__('response.validation_failed'), ResponseCode::VALIDATION_FAILED);
    }

    /**
     * 校验详情/结算主键必须为整数，避免字符串被强制转换后误操作其他返佣记录。
     *
     * @param mixed $id 返佣记录主键原始值。
     * @return \Illuminate\Http\JsonResponse|null 校验失败返回统一错误响应，通过时返回 null。
     */
    private function validateCommissionId($id)
    {
        $validator = Validator::make(['id' => $id], [
            'id' => 'required|integer',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), ResponseCode::VALIDATION_FAILED);
        }

        return null;
    }
}
