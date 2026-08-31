<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/16
 * Time: 03:34
 */

namespace App\Http\Controllers\Admin;

use App\Contracts\DepositRefundGateway;
use App\Contracts\DepositSettlementGateway;
use App\Constants\ResponseCode;
use App\Models\DepositImport;
use App\Models\UserInfo;
use App\Models\WithdrawImport;
use App\Services\AdminDataScopeService;
use App\Services\Payment\DepositSettlementResult;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Throwable;

/**
 * 后台批量入金/出金导入控制器。
 *
 * 功能逻辑说明：
 * - 旧项目 `BatchAmountController` 同时承担批量入金和批量出金导入，本控制器先接入新项目已经存在的
 *   `deposit_imports` 与 `withdraw_imports` 两张真实导入表。
 * - 当前实现覆盖“导入记录列表 + 手工新增导入记录 + 失败记录重试 + 待处理记录真实 MT4 同步”最小闭环。
 * - 所有列表查询都会调用 `AdminDataScopeService`，确保不同后台管理员只能看到自己数据范围内的用户导入记录。
 * - 页面按钮权限由 `permissions.slug` 控制，接口访问权限由 `permissions.api_route` 与 `check.permission:admin` 控制。
 *
 * 文件功能：
 * - 批量入金/出金导入记录的列表、新增（手工或 CSV）、失败重试、单条 MT4 同步、模板下载与导出。
 * - 输入 user_id/amount/batch_no/CSV 文件等；输出导入记录分页列表或同步结果。
 *
 * 状态机（is_synced）：
 * - 0=待处理，1=已同步，2=同步失败，3=处理中（claim 中间态；超过 SYNC_PROCESSING_STALE_SECONDS 未完成可被重新认领）。
 * - 重试只把失败或陈旧处理中记录放回 0，不伪造 MT4 同步结果。
 * - 同步前原子 claim（update 影响 1 行才取得所有权），防止并发重复向 MT4 提交同一记录。
 *
 * 失败语义：
 * - MT4 网关异常统一转 DepositSettlementResult::unknown，绝不伪造成功；同步失败返回 MT4_SYNC_FAILED 并带回记录详情。
 * - 同步结果写回：明确 settled 落 1 并保存订单号；retryable_not_sent 回 0 等待重试；其余落 2 保留错误码。
 */
class BatchAmountImportController extends AdminBaseController
{
    /**
     * is_synced 状态机的“处理中”枚举值。与文件头状态机约定一致：0=待处理、1=已同步、2=失败、3=处理中。
     * claim 原子更新必须以该值为目标态，其他状态不得直接进入 3，否则并发重试保护失效。
     *
     * @var int
     */
    private const SYNC_PROCESSING = 3;

    /**
     * 处理中状态的陈旧阈值（秒），固定 300 = 5 分钟。worker 崩溃或请求中断导致记录滞留在 3 时，
     * 超过该阈值即可被重新认领（回 0 后重试）；过短会与正常同步耗时冲突造成并发重复提交，过长则拖延故障恢复。
     *
     * @var int
     */
    private const SYNC_PROCESSING_STALE_SECONDS = 300;

    /**
     * 后台数据范围服务。
     *
     * @var AdminDataScopeService
     */
    private $adminDataScopeService;

    /**
     * 入金结算网关：待处理导入记录的“真实 MT4 同步”入口，负责把入金写入 MT4 账户。
     * 网关不可用时同步接口必须失败关闭（记录留在待处理/失败态），不允许伪造同步成功。
     *
     * @var DepositSettlementGateway
     */
    private $depositSettlementGateway;

    /**
     * 入金退款网关：负责对已同步导入记录执行 MT4 侧资金回退。
     * 与结算网关成对存在；缺失时退款入口只能拒绝，不得用结算网关替代以避免状态机错乱。
     *
     * @var DepositRefundGateway
     */
    private $depositRefundGateway;

    /**
     * 构造函数。
     *
     * @param AdminDataScopeService $adminDataScopeService 数据范围服务，用于按管理员角色和代理绑定过滤导入记录。
     */
    public function __construct(
        AdminDataScopeService $adminDataScopeService,
        DepositSettlementGateway $depositSettlementGateway,
        DepositRefundGateway $depositRefundGateway
    )
    {
        $this->adminDataScopeService = $adminDataScopeService;
        $this->depositSettlementGateway = $depositSettlementGateway;
        $this->depositRefundGateway = $depositRefundGateway;
    }

    /**
     * 查询批量入金导入记录列表。
     *
     * @param Request $request 当前请求对象；支持 page、per_page、limit、user_id、batch_no、is_synced 等筛选参数。
     * @return \Illuminate\Http\JsonResponse
     */
    public function depositImportList(Request $request)
    {
        if ($filterError = $this->validateNumericFilters($request)) {
            return $filterError;
        }

        $query = DepositImport::query()->with('user');
        $this->applyCommonFilters($query, $request);
        $this->applyDataScope($query, $request, 'deposit');

        return $this->success(
            $this->paginateImportQuery($query, $request),
            __('admin.deposit_imports_fetched')
        );
    }

    /**
     * 新增单条批量入金导入记录。
     *
     * @param Request $request 当前请求对象；user_id 表示业务用户 ID，amount 表示入金金额，batch_no 表示导入批次号。
     * @return \Illuminate\Http\JsonResponse
     */
    public function createDepositImport(Request $request)
    {
        if ($this->hasImportFile($request)) {
            return $this->importCsvRecords($request, DepositImport::class, __('admin.deposit_import_created'));
        }

        $validator = $this->makeImportValidator($request);
        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), ResponseCode::VALIDATION_FAILED);
        }

        $record = DepositImport::create($this->buildImportPayload($request));

        return $this->success($record, __('admin.deposit_import_created'), ResponseCode::CREATED);
    }

    /**
     * 重试失败的批量入金导入记录。
     *
     * 参数逻辑说明：
     * - id：`deposit_imports.id` 主键，表示需要重新放回待处理队列的导入记录。
     * - 只有 `is_synced=2` 的失败记录允许重试，避免成功或待处理记录被重复提交。
     * - 重试不会伪造 MT4 同步结果，只把 `is_synced` 改回 0，并清空 `fail_reason` 等待后续真实同步流程处理。
     *
     * @param Request $request 当前请求对象，用于读取登录管理员并执行数据范围校验。
     * @param int|string $id 批量入金导入记录主键。
     * @return \Illuminate\Http\JsonResponse
     */
    public function retryDepositImport(Request $request, $id)
    {
        if ($routeIdError = $this->validateImportRouteId($id)) {
            return $routeIdError;
        }

        return $this->retryImportRecord(
            DepositImport::query(),
            $request,
            (int) $id,
            'deposit',
            __('admin.deposit_import_retry_success')
        );
    }

    /**
     * 同步单条待处理批量入金导入记录到 MT4。
     *
     * 只允许 is_synced=0 的待处理记录或超时未完成的处理中记录发起同步；同步前先按数据范围校验记录归属。
     *
     * @param Request $request 当前请求对象，用于数据范围校验。
     * @param int|string $id deposit_imports.id。
     * @return \Illuminate\Http\JsonResponse 同步成功返回更新后记录；失败返回 MT4_SYNC_FAILED。
     */
    public function syncDepositImport(Request $request, $id)
    {
        if ($routeIdError = $this->validateImportRouteId($id)) {
            return $routeIdError;
        }

        return $this->syncImportRecord(
            DepositImport::query(),
            $request,
            (int) $id,
            'deposit',
            function (Model $record) {
                return $this->depositSettlementGateway->deposit(
                    (int) $record->user_id,
                    (string) $record->amount,
                    $this->importSyncComment($record)
                );
            },
            __('admin.deposit_import_sync_success')
        );
    }

    /**
     * 查询批量出金导入记录列表。
     *
     * @param Request $request 当前请求对象；支持 page、per_page、limit、user_id、batch_no、is_synced 等筛选参数。
     * @return \Illuminate\Http\JsonResponse
     */
    public function withdrawImportList(Request $request)
    {
        if ($filterError = $this->validateNumericFilters($request)) {
            return $filterError;
        }

        $query = WithdrawImport::query()->with('user');
        $this->applyCommonFilters($query, $request);
        $this->applyDataScope($query, $request, 'withdraw');

        return $this->success(
            $this->paginateImportQuery($query, $request),
            __('admin.withdraw_imports_fetched')
        );
    }

    /**
     * 新增单条批量出金导入记录。
     *
     * @param Request $request 当前请求对象；user_id 表示业务用户 ID，amount 表示出金金额，batch_no 表示导入批次号。
     * @return \Illuminate\Http\JsonResponse
     */
    public function createWithdrawImport(Request $request)
    {
        if ($this->hasImportFile($request)) {
            return $this->importCsvRecords($request, WithdrawImport::class, __('admin.withdraw_import_created'));
        }

        $validator = $this->makeImportValidator($request);
        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), ResponseCode::VALIDATION_FAILED);
        }

        $record = WithdrawImport::create($this->buildImportPayload($request));

        return $this->success($record, __('admin.withdraw_import_created'), ResponseCode::CREATED);
    }

    /**
     * 重试失败的批量出金导入记录。
     *
     * 参数逻辑说明：
     * - id：`withdraw_imports.id` 主键，表示需要重新放回待处理队列的出金导入记录。
     * - 只有 `is_synced=2` 的失败记录允许重试，避免对待处理或成功记录重复发起后续资金处理。
     * - 重试动作只修正导入队列状态，不直接执行出金或 MT4 同步，确保资金动作仍由后续真实同步链路审计。
     *
     * @param Request $request 当前请求对象，用于读取登录管理员并执行数据范围校验。
     * @param int|string $id 批量出金导入记录主键。
     * @return \Illuminate\Http\JsonResponse
     */
    public function retryWithdrawImport(Request $request, $id)
    {
        if ($routeIdError = $this->validateImportRouteId($id)) {
            return $routeIdError;
        }

        return $this->retryImportRecord(
            WithdrawImport::query(),
            $request,
            (int) $id,
            'withdraw',
            __('admin.withdraw_import_retry_success')
        );
    }

    /**
     * 同步单条待处理批量出金导入记录到 MT4。
     *
     * 只允许 is_synced=0 的待处理记录或超时未完成的处理中记录发起同步；同步前先按数据范围校验记录归属。
     *
     * @param Request $request 当前请求对象，用于数据范围校验。
     * @param int|string $id withdraw_imports.id。
     * @return \Illuminate\Http\JsonResponse 同步成功返回更新后记录；失败返回 MT4_SYNC_FAILED。
     */
    public function syncWithdrawImport(Request $request, $id)
    {
        if ($routeIdError = $this->validateImportRouteId($id)) {
            return $routeIdError;
        }

        return $this->syncImportRecord(
            WithdrawImport::query(),
            $request,
            (int) $id,
            'withdraw',
            function (Model $record) {
                return $this->depositRefundGateway->refund(
                    (int) $record->user_id,
                    (string) $record->amount,
                    $this->importSyncComment($record)
                );
            },
            __('admin.withdraw_import_sync_success')
        );
    }

    /**
     * 下载批量入金导入 CSV 模板。
     *
     * @return \Symfony\Component\HttpFoundation\StreamedResponse
     */
    public function depositImportTemplate()
    {
        return $this->csvDownload('deposit_import_template.csv', [
            ['user_id', 'user_name', 'mt4_login', 'amount', 'batch_no', 'mt4_order_id', 'remarks'],
            ['10001', 'demo user', '10001', '100.00', 'DEP-' . date('Ymd') . '-001', '0', 'optional remark'],
        ]);
    }

    /**
     * 导出当前筛选条件下的批量入金导入记录。
     *
     * @param Request $request 当前请求对象，支持 user_id、batch_no、is_synced 等列表筛选参数。
     * @return \Symfony\Component\HttpFoundation\StreamedResponse
     */
    public function exportDepositImports(Request $request)
    {
        if ($filterError = $this->validateNumericFilters($request)) {
            return $filterError;
        }

        $query = DepositImport::query();
        $this->applyCommonFilters($query, $request);
        $this->applyDataScope($query, $request, 'deposit');

        return $this->exportImportRecords($query, 'deposit_imports_export.csv');
    }

    /**
     * 下载批量出金导入 CSV 模板。
     *
     * @return \Symfony\Component\HttpFoundation\StreamedResponse
     */
    public function withdrawImportTemplate()
    {
        return $this->csvDownload('withdraw_import_template.csv', [
            ['user_id', 'user_name', 'mt4_login', 'amount', 'batch_no', 'mt4_order_id', 'remarks'],
            ['10001', 'demo user', '10001', '100.00', 'WDR-' . date('Ymd') . '-001', '0', 'optional remark'],
        ]);
    }

    /**
     * 导出当前筛选条件下的批量出金导入记录。
     *
     * @param Request $request 当前请求对象，支持 user_id、batch_no、is_synced 等列表筛选参数。
     * @return \Symfony\Component\HttpFoundation\StreamedResponse
     */
    public function exportWithdrawImports(Request $request)
    {
        if ($filterError = $this->validateNumericFilters($request)) {
            return $filterError;
        }

        $query = WithdrawImport::query();
        $this->applyCommonFilters($query, $request);
        $this->applyDataScope($query, $request, 'withdraw');

        return $this->exportImportRecords($query, 'withdraw_imports_export.csv');
    }

    /**
     * 校验列表数字筛选参数必须为整数。
     *
     * @param Request $request 当前请求对象，读取 user_id、is_synced 参数。
     * @return \Illuminate\Http\JsonResponse|null 校验失败返回统一错误响应，未传或通过时返回 null。
     */
    private function validateNumericFilters(Request $request)
    {
        $rules = [];

        foreach (['user_id', 'is_synced'] as $field) {
            if ($request->filled($field)) {
                $rules[$field] = 'integer';
            }
        }

        if ($rules === []) {
            return null;
        }

        $validator = Validator::make($request->only(array_keys($rules)), $rules);

        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), ResponseCode::VALIDATION_FAILED);
        }

        return null;
    }

    /**
     * 追加导入记录公共筛选条件。
     *
     * @param Builder $query 导入记录查询对象，调用方传入入金或出金导入模型查询。
     * @param Request $request 当前请求对象，读取 user_id、batch_no、is_synced 参数。
     * @return void
     */
    private function applyCommonFilters(Builder $query, Request $request): void
    {
        if ($request->filled('user_id')) {
            $query->where('user_id', (int) $request->input('user_id'));
        }

        if ($request->filled('user_name')) {
            $query->where('user_name', (string) $request->input('user_name'));
        }

        if ($request->filled('batch_no')) {
            if (in_array($request->header('X-Legacy-Admin-Route'), [
                'index/admin/amount/depositImportSearch',
                'index/admin/amount/withdrawImportSearch',
            ], true)) {
                $query->where('batch_no', (string) $request->input('batch_no'));
            } else {
                $query->where('batch_no', 'LIKE', '%' . $request->input('batch_no') . '%');
            }
        }

        if ($request->filled('is_synced')) {
            $query->where('is_synced', (int) $request->input('is_synced'));
        }

        if ($request->filled('start_date')) {
            $query->where('updated_at', '>=', strtotime((string) $request->input('start_date') . ' 00:00:00'));
        }

        if ($request->filled('end_date')) {
            $query->where('updated_at', '<=', strtotime((string) $request->input('end_date') . ' 23:59:59'));
        }
    }

    /**
     * 按当前后台管理员的数据范围过滤导入记录。
     *
     * @param Builder $query 导入记录查询对象。
     * @param Request $request 当前请求对象，用于读取 admin guard 下的登录管理员。
     * @param string $targetType 业务目标类型；deposit 表示入金导入，withdraw 表示出金导入。
     * @return void
     */
    private function applyDataScope(Builder $query, Request $request, string $targetType): void
    {
        $admin = $request->user('admin');
        if (!$admin) {
            return;
        }

        $this->adminDataScopeService->apply($query, $admin, $targetType, 'user_id');
    }

    /**
     * 执行导入失败记录重试。
     *
     * 参数逻辑说明：
     * - $query：入金或出金导入模型查询对象，调用方决定真实数据表。
     * - $request：当前请求对象，用于按管理员角色和代理绑定裁剪数据范围。
     * - $id：导入记录主键，只允许定位单条记录。
     * - $targetType：数据范围目标类型，deposit=入金导入，withdraw=出金导入。
     * - $message：重试成功后的多语言提示文案。
     *
     * @param Builder $query 入金或出金导入记录查询对象。
     * @param Request $request 当前请求对象。
     * @param int $id 导入记录主键。
     * @param string $targetType 数据范围目标类型。
     * @param string $message 成功提示文案。
     * @return \Illuminate\Http\JsonResponse
     */
    private function retryImportRecord(Builder $query, Request $request, int $id, string $targetType, string $message)
    {
        $query->where('id', $id);
        $this->applyDataScope($query, $request, $targetType);

        $record = $query->first();
        if (!$record) {
            return $this->error(__('admin.import_record_not_found'), ResponseCode::DATA_NOT_FOUND);
        }

        $syncStatus = (int) $record->is_synced;
        $isFailed = $syncStatus === 2;
        $isStaleProcessing = $this->isStaleProcessingImport($record);
        if (!$isFailed && !$isStaleProcessing) {
            return $this->error(__('admin.import_retry_only_failed'), ResponseCode::OPERATION_NOT_ALLOWED);
        }

        // 只把失败/陈旧处理中记录放回待处理队列；重试不直接执行资金动作，真实同步仍由后续 sync 链路完成。
        $record->update([
            'is_synced' => 0,
            'fail_reason' => '',
            'updated_by' => (int) (auth('admin')->id() ?: 0),
        ]);

        return $this->success($record->fresh(), $message);
    }

    /**
     * 执行单条导入记录的 MT4 同步状态机。
     *
     * 步骤：数据范围校验 -> 状态检查（仅待处理或陈旧处理中）-> 原子 claim -> 网关调用 -> 结果写回。
     *
     * @param Builder $query 入金或出金导入模型查询。
     * @param Request $request 当前请求对象。
     * @param int $id 导入记录主键。
     * @param string $targetType 数据范围目标类型。
     * @param callable $gatewayCall 发起外部 MT4 请求的回调。
     * @param string $successMessage 成功响应文案。
     * @return \Illuminate\Http\JsonResponse
     */
    private function syncImportRecord(
        Builder $query,
        Request $request,
        int $id,
        string $targetType,
        callable $gatewayCall,
        string $successMessage
    ) {
        $query->where('id', $id);
        $this->applyDataScope($query, $request, $targetType);

        $record = $query->first();
        if (!$record) {
            return $this->error(__('admin.import_record_not_found'), ResponseCode::DATA_NOT_FOUND);
        }

        $syncStatus = (int) $record->is_synced;
        $isPending = $syncStatus === 0;
        $isStaleProcessing = $this->isStaleProcessingImport($record);
        if (!$isPending && !$isStaleProcessing) {
            return $this->error(__('admin.import_sync_only_pending'), ResponseCode::OPERATION_NOT_ALLOWED);
        }

        // 原子 claim：把记录置为处理中状态 3；update 影响行数为 1 才表示本请求取得所有权，防止并发重复提交。
        if (!$this->claimPendingImportForSync($record)) {
            return $this->error(__('admin.import_sync_only_pending'), ResponseCode::OPERATION_NOT_ALLOWED);
        }

        $record->refresh();
        try {
            // 网关调用异常统一转 unknown 结果，由 finishImportSync 按失败落库，不向业务层抛裸异常。
            $result = $gatewayCall($record);
        } catch (Throwable $exception) {
            $result = DepositSettlementResult::unknown('gateway_exception');
        }

        return $this->finishImportSync($record, $result, $successMessage);
    }

    /**
     * 在外部 MT4 调用前认领待处理（或超时未完成的处理中）导入记录。
     *
     * 只允许 is_synced=0 或 is_synced=3 且 updated_at 超过陈旧窗口的记录被认领。
     *
     * @param Model $record 准备同步的导入记录。
     * @return bool true=本请求取得该记录的所有权。
     */
    private function claimPendingImportForSync(Model $record): bool
    {
        $staleBefore = time() - self::SYNC_PROCESSING_STALE_SECONDS;
        $updated = $record->newQuery()
            ->whereKey($record->getKey())
            ->where(function ($query) use ($staleBefore) {
                $query->where('is_synced', 0)
                    ->orWhere(function ($processing) use ($staleBefore) {
                        $processing->where('is_synced', self::SYNC_PROCESSING)
                            ->where('updated_at', '<=', $staleBefore);
                    });
            })
            ->update([
                'is_synced' => self::SYNC_PROCESSING,
                'fail_reason' => 'processing',
                'updated_by' => (int) (auth('admin')->id() ?: 0),
                'updated_at' => time(),
            ]);

        return $updated === 1;
    }

    /**
     * 判断导入记录是否卡在处理中状态且超过陈旧阈值（可被重新认领）。
     *
     * @param Model $record 导入记录。
     * @return bool true=处理中超时，可被重试或同步重新认领。
     */
    private function isStaleProcessingImport(Model $record): bool
    {
        if ((int) $record->is_synced !== self::SYNC_PROCESSING) {
            return false;
        }

        $updatedAt = $record->updated_at;
        if ($updatedAt instanceof \DateTimeInterface) {
            $updatedTs = $updatedAt->getTimestamp();
        } else {
            $updatedTs = (int) $updatedAt;
        }

        return $updatedTs > 0 && $updatedTs <= (time() - self::SYNC_PROCESSING_STALE_SECONDS);
    }

    /**
     * 将 MT4 同步结果写回导入记录并返回响应。
     *
     * @param Model $record 已被本请求 claim 的导入记录。
     * @param DepositSettlementResult $result 网关返回的闭合状态结果。
     * @param string $successMessage 成功响应文案。
     * @return \Illuminate\Http\JsonResponse
     */
    private function finishImportSync(Model $record, DepositSettlementResult $result, string $successMessage)
    {
        // 仅当 MT4 明确 settled 才落已同步并保存订单号；其余一律失败关闭。
        if ($result->status() === 'settled') {
            $record->update([
                'is_synced' => 1,
                'mt4_order_id' => (int) $result->providerReference(),
                'fail_reason' => '',
                'updated_by' => (int) (auth('admin')->id() ?: 0),
            ]);

            return $this->success($record->fresh(), $successMessage);
        }

        // 未确认成功：可重试且未发出（retryable_not_sent）回 0 等待重试，其余落 2 失败并保留错误码。
        $errorCode = (string) $result->errorCode();
        $record->update([
            'is_synced' => $result->status() === 'retryable_not_sent' ? 0 : 2,
            'mt4_order_id' => 0,
            'fail_reason' => $errorCode,
            'updated_by' => (int) (auth('admin')->id() ?: 0),
        ]);

        return $this->error(__('response.mt4_sync_failed'), ResponseCode::MT4_SYNC_FAILED, $record->fresh());
    }

    /**
     * 构造发给 MT4 的同步备注。
     *
     * @param Model $record 正在同步的导入记录。
     * @return string MT4 comment；优先使用导入备注，备注为空时退回批次号便于审计追踪。
     */
    private function importSyncComment(Model $record): string
    {
        $remarks = trim((string) $record->remarks);

        return $remarks !== '' ? $remarks : (string) $record->batch_no;
    }

    /**
     * 校验导入记录路由 ID，避免非严格数字字符串命中导入表主键。
     *
     * @param int|string $id 路由参数中的导入记录主键。
     * @return \Illuminate\Http\JsonResponse|null ID 非法时返回统一错误响应，否则返回 null。
     */
    private function validateImportRouteId($id)
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
     * 分页返回导入记录。
     *
     * @param Builder $query 导入记录查询对象。
     * @param Request $request 当前请求对象；page 为页码，per_page/limit 为每页数量。
     * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator
     */
    private function paginateImportQuery(Builder $query, Request $request)
    {
        $page = (int) $request->input('page', 1);
        $perPage = (int) $request->input('per_page', $request->input('limit', 15));

        return $query->orderByDesc('created_at')->paginate($perPage, ['*'], 'page', $page);
    }

    /**
     * 创建导入记录参数验证器。
     *
     * @param Request $request 当前请求对象。
     * @return \Illuminate\Contracts\Validation\Validator
     */
    private function makeImportValidator(Request $request)
    {
        return $this->makeImportDataValidator($request->all());
    }

    /**
     * 创建入金/出金导入记录参数验证器。
     *
     * @param array<string, mixed> $data 单条导入记录字段；可来自手工表单，也可来自 CSV 文件中的一行。
     * @return \Illuminate\Contracts\Validation\Validator
     */
    private function makeImportDataValidator(array $data)
    {
        $validator = Validator::make($data, [
            'user_id' => 'required|integer|exists:user_infos,user_id',
            'user_name' => 'nullable|string|max:200',
            'mt4_login' => 'nullable|integer|min:0',
            'mt4_code' => 'nullable|integer|min:0',
            'login' => 'nullable|integer|min:0',
            'amount' => 'required|numeric|min:0.01',
            'remarks' => 'nullable|string|max:500',
            'mt4_order_id' => 'nullable|integer|min:0',
            'batch_no' => 'required|string|max:100',
            'is_synced' => 'nullable|integer|in:0,1,2',
            'fail_reason' => 'nullable|string|max:500',
        ]);

        $validator->after(function ($validator) use ($data) {
            $this->validateMt4LoginMatchesUser($validator, $data);
        });

        return $validator;
    }

    /**
     * 校验 CSV 可选 MT4 登录账号必须属于当前业务用户。
     *
     * @param \Illuminate\Contracts\Validation\Validator $validator 当前行验证器，用于追加字段错误。
     * @param array<string, mixed> $data 单条手工或 CSV 导入数据。
     * @return void
     */
    private function validateMt4LoginMatchesUser($validator, array $data): void
    {
        if ($validator->errors()->has('user_id') || $validator->errors()->has('mt4_login') || $validator->errors()->has('mt4_code') || $validator->errors()->has('login')) {
            return;
        }

        $mt4Login = $this->importMt4Login($data);
        if ($mt4Login === null) {
            return;
        }

        $userMt4Code = UserInfo::query()
            ->where('user_id', (int) ($data['user_id'] ?? 0))
            ->value('mt4_code');

        if ($userMt4Code !== null && (int) $userMt4Code !== $mt4Login) {
            $validator->errors()->add('mt4_login', __('admin.import_mt4_login_mismatch'));
        }
    }

    /**
     * 从兼容字段中读取导入文件声明的 MT4 登录账号。
     *
     * @param array<string, mixed> $data 单条手工或 CSV 导入数据。
     * @return int|null 有填写时返回账号数字，未填写时返回 null 并保持旧导入兼容。
     */
    private function importMt4Login(array $data): ?int
    {
        foreach (['mt4_login', 'mt4_code', 'login'] as $field) {
            if (array_key_exists($field, $data) && trim((string) $data[$field]) !== '') {
                return (int) $data[$field];
            }
        }

        return null;
    }

    /**
     * 构造导入记录写入数据。
     *
     * @param Request $request 当前请求对象，字段直接对应导入表列。
     * @return array<string, mixed> 可直接写入 deposit_imports 或 withdraw_imports 的字段集合。
     */
    private function buildImportPayload(Request $request): array
    {
        return $this->buildImportPayloadFromArray($request->all());
    }

    /**
     * 构造入金/出金导入记录写入数据。
     *
     * @param array<string, mixed> $data 已通过校验的导入字段。
     * @return array<string, mixed> 可直接写入 deposit_imports 或 withdraw_imports 的字段集合。
     */
    private function buildImportPayloadFromArray(array $data): array
    {
        $userId = (int) ($data['user_id'] ?? 0);
        $userName = $data['user_name'] ?? '';

        if (!$userName) {
            $userName = (string) UserInfo::query()->where('user_id', $userId)->value('user_name');
        }

        return [
            'user_id' => $userId,
            'user_name' => $userName ?: '',
            'amount' => (string) ($data['amount'] ?? ''),
            'remarks' => (string) ($data['remarks'] ?? ''),
            'mt4_order_id' => (int) ($data['mt4_order_id'] ?? 0),
            'batch_no' => (string) ($data['batch_no'] ?? ''),
            'is_synced' => (int) ($data['is_synced'] ?? 0),
            'fail_reason' => (string) ($data['fail_reason'] ?? ''),
            'created_by' => (int) (auth('admin')->id() ?: 0),
            'updated_by' => (int) (auth('admin')->id() ?: 0),
        ];
    }

    /**
     * 判断请求是否携带 CSV 导入文件。
     *
     * @param Request $request 当前请求对象；兼容 file/import_file/csv_file 三种字段名。
     * @return bool true=按文件导入处理，false=按旧的单条手工新增处理。
     */
    private function hasImportFile(Request $request): bool
    {
        return $this->importFile($request) !== null;
    }

    /**
     * 读取上传的 CSV 文件对象。
     *
     * @param Request $request 当前请求对象。
     * @return UploadedFile|null 上传文件；不存在时返回 null。
     */
    private function importFile(Request $request): ?UploadedFile
    {
        foreach (['file', 'import_file', 'csv_file'] as $name) {
            if ($request->hasFile($name)) {
                return $request->file($name);
            }
        }

        return null;
    }

    /**
     * 解析 CSV 并批量写入入金/出金导入队列。
     *
     * @param Request $request 当前请求对象。
     * @param class-string<DepositImport|WithdrawImport> $modelClass 写入的导入模型类。
     * @param string $message 成功提示文案。
     * @return \Illuminate\Http\JsonResponse
     */
    private function importCsvRecords(Request $request, string $modelClass, string $message)
    {
        $file = $this->importFile($request);
        if (!$file || !$file->isValid()) {
            return $this->error(__('validation.file'), ResponseCode::VALIDATION_FAILED);
        }

        $rows = $this->parseCsvRows($file);
        if (empty($rows)) {
            return $this->error(__('validation.required', ['attribute' => 'file']), ResponseCode::VALIDATION_FAILED);
        }

        if ($this->isLegacyAmountImportUpload($request)) {
            $rows = $this->normalizeLegacyImportRows($rows, $modelClass);
        }

        // 先逐行校验全部数据，任一行非法则整体拒绝，避免批量导入一半后才发现错误行。
        foreach ($rows as $index => $row) {
            $validator = $this->makeImportDataValidator($row);
            if ($validator->fails()) {
                return $this->error('CSV row ' . ($index + 2) . ': ' . $validator->errors()->first(), ResponseCode::VALIDATION_FAILED);
            }
        }

        // 全部行通过校验后在单个事务内批量落库，任一写入失败整体回滚。
        $created = DB::transaction(function () use ($rows, $modelClass) {
            return collect($rows)->map(function (array $row) use ($modelClass) {
                return $modelClass::create($this->buildImportPayloadFromArray($row));
            })->values();
        });

        if ($this->isLegacyAmountImportUpload($request)) {
            $syncSuccess = 0;
            $syncFailed = 0;
            $targetType = $modelClass === DepositImport::class ? 'deposit' : 'withdraw';

            foreach ($created as $record) {
                $syncResponse = $this->syncImportRecord(
                    $modelClass::query(),
                    $request,
                    (int) $record->getKey(),
                    $targetType,
                    function (Model $pending) use ($targetType) {
                        if ($targetType === 'deposit') {
                            return $this->depositSettlementGateway->deposit(
                                (int) $pending->user_id,
                                (string) $pending->amount,
                                $this->importSyncComment($pending)
                            );
                        }

                        return $this->depositRefundGateway->refund(
                            (int) $pending->user_id,
                            (string) $pending->amount,
                            $this->importSyncComment($pending)
                        );
                    },
                    $message
                );
                $syncPayload = $syncResponse->getData(true);
                if ((int) ($syncPayload['code'] ?? ResponseCode::SERVER_ERROR) === ResponseCode::SUCCESS) {
                    $syncSuccess++;
                } else {
                    $syncFailed++;
                }
            }

            return $this->success([
                'created' => $created->count(),
                'sync_success' => $syncSuccess,
                'sync_failed' => $syncFailed,
                'records' => $created->map->fresh()->values(),
            ], $message, ResponseCode::CREATED);
        }

        return $this->success(['created' => $created->count(), 'records' => $created], $message, ResponseCode::CREATED);
    }

    private function isLegacyAmountImportUpload(Request $request): bool
    {
        return in_array($request->header('X-Legacy-Admin-Route'), [
            'index/admin/amount/depositImportExcel',
            'index/admin/amount/withdrawImportExcel',
        ], true);
    }

    /**
     * 将旧 Excel/CSV 表头映射到现代导入字段，并为旧文件生成批次号。
     * 旧项目的文件没有 batch_no，且同步状态字段叫 is_sync_succ。
     *
     * @param array<int, array<string, mixed>> $rows
     * @param class-string<DepositImport|WithdrawImport> $modelClass
     * @return array<int, array<string, mixed>>
     */
    private function normalizeLegacyImportRows(array $rows, string $modelClass): array
    {
        $prefix = $modelClass === DepositImport::class ? 'LEGACY-DEP-' : 'LEGACY-WDR-';
        $prefix .= date('YmdHis') . '-' . (int) (auth('admin')->id() ?: 0);

        foreach ($rows as $index => &$row) {
            if (array_key_exists('userId', $row) && !array_key_exists('user_id', $row)) {
                $row['user_id'] = $row['userId'];
            }
            if (array_key_exists('username', $row) && !array_key_exists('user_name', $row)) {
                $row['user_name'] = $row['username'];
            }
            if (array_key_exists('remark', $row) && !array_key_exists('remarks', $row)) {
                $row['remarks'] = $row['remark'];
            }
            if (array_key_exists('is_sync_succ', $row) && !array_key_exists('is_synced', $row)) {
                $row['is_synced'] = $row['is_sync_succ'];
            }
            if (!array_key_exists('batch_no', $row) || trim((string) $row['batch_no']) === '') {
                $row['batch_no'] = $prefix . '-' . ($index + 1);
            }
            if (!array_key_exists('mt4_order_id', $row) || trim((string) $row['mt4_order_id']) === '') {
                $row['mt4_order_id'] = 0;
            }
        }
        unset($row);

        return $rows;
    }

    /**
     * 将 CSV 文件解析为关联数组行。
     *
     * @param UploadedFile $file 上传的 CSV 文件。
     * @return array<int, array<string, mixed>> CSV 数据行集合。
     */
    private function parseCsvRows(UploadedFile $file): array
    {
        $handle = fopen($file->getRealPath(), 'r');
        if (!$handle) {
            return [];
        }

        $header = fgetcsv($handle);
        if (!$header) {
            fclose($handle);
            return [];
        }

        $header = array_map(function ($value) {
            return trim((string) $value, "\xEF\xBB\xBF \t\n\r\0\x0B");
        }, $header);
        $rows = [];

        // 按表头把每行映射为关联数组；去掉 UTF-8 BOM 并跳过空行。
        while (($line = fgetcsv($handle)) !== false) {
            if ($this->isEmptyCsvLine($line)) {
                continue;
            }

            $row = [];
            foreach ($header as $index => $name) {
                if ($name === '') {
                    continue;
                }
                $row[$name] = isset($line[$index]) ? trim((string) $line[$index]) : '';
            }
            $rows[] = $row;
        }

        fclose($handle);

        return $rows;
    }

    /**
     * 判断 CSV 行是否为空行。
     *
     * @param array<int, mixed> $line fgetcsv 返回的原始行。
     * @return bool true=空行，应跳过。
     */
    private function isEmptyCsvLine(array $line): bool
    {
        foreach ($line as $value) {
            if (trim((string) $value) !== '') {
                return false;
            }
        }

        return true;
    }

    /**
     * 导出入金/出金导入记录为 CSV。
     *
     * @param Builder $query 已经追加筛选和数据范围的导入记录查询对象。
     * @param string $fileName 下载文件名。
     * @return \Symfony\Component\HttpFoundation\StreamedResponse
     */
    private function exportImportRecords(Builder $query, string $fileName)
    {
        $rows = [
            ['id', 'user_id', 'user_name', 'amount', 'batch_no', 'mt4_order_id', 'is_synced', 'fail_reason', 'remarks', 'created_at'],
        ];

        $query->orderByDesc('created_at')->limit(5000)->get()->each(function ($record) use (&$rows) {
            $rows[] = [
                $record->id,
                $record->user_id,
                $record->user_name,
                $record->amount,
                $record->batch_no,
                $record->mt4_order_id,
                $record->is_synced,
                $record->fail_reason,
                $record->remarks,
                $record->created_at,
            ];
        });

        return $this->csvDownload($fileName, $rows);
    }

    /**
     * 生成 CSV 下载响应。
     *
     * @param string $fileName 下载文件名。
     * @param array<int, array<int, mixed>> $rows CSV 行数据。
     * @return \Symfony\Component\HttpFoundation\StreamedResponse
     */
    private function csvDownload(string $fileName, array $rows)
    {
        return response()->streamDownload(function () use ($rows) {
            $handle = fopen('php://output', 'w');
            foreach ($rows as $row) {
                fputcsv($handle, $row);
            }
            fclose($handle);
        }, $fileName, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }
}
