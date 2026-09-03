<?php

/**
 * Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/09/03
 * Time: 14:30
 */

namespace App\Http\Controllers\Admin;

use App\Contracts\CreditSettlementGateway;
use App\Constants\ResponseCode;
use App\Models\CreditImport;
use App\Models\UserInfo;
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
 * 后台批量信用导入控制器。
 *
 * 功能逻辑说明：
 * - 旧项目 `BatchCreditController` 负责信用额度批量导入、导入记录查询和失败重试。
 * - 本控制器先接入新项目真实表 `credit_imports`，补齐列表查询和手工新增导入记录闭环。
 * - 当前实现覆盖“导入记录列表 + 手工新增导入记录 + 失败记录重试 + 待处理记录真实 MT4 信用同步”闭环。
 * - 所有接口仍由 `permissions.api_route` 与 `check.permission:admin` 鉴权，列表继续使用 `AdminDataScopeService` 控制可见数据。
 *
 * 文件功能：
 * - 批量信用导入记录的列表、新增（手工或 CSV）、失败重试、单条 MT4 信用同步、模板下载与导出。
 * - 输入 user_id/credit_type/amount/batch_no/CSV 文件等；输出信用导入记录分页列表或同步结果。
 *
 * 状态机（is_synced）：
 * - 0=待处理，1=已同步，2=同步失败，3=处理中（claim 中间态；超过 SYNC_PROCESSING_STALE_SECONDS 未完成可被重新认领）。
 * - 重试只把失败或陈旧处理中记录放回 0，不伪造 MT4 信用同步结果。
 * - 同步前原子 claim（update 影响 1 行才取得所有权），防止并发重复向 MT4 提交同一信用记录。
 *
 * 失败语义：
 * - MT4 网关异常统一转 DepositSettlementResult::unknown，绝不伪造成功；同步失败返回 MT4_SYNC_FAILED 并带回记录详情。
 * - 同步结果写回：明确 settled 落 1 并保存订单号；retryable_not_sent 回 0 等待重试；其余落 2 保留错误码。
 */
class BatchCreditImportController extends AdminBaseController
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
     * 信用结算网关：待处理信用导入记录的“真实 MT4 信用同步”入口，负责向 MT4 账户写入/调整信用额度。
     * 网关不可用时同步接口必须失败关闭，记录保持待处理或失败态，绝不伪造同步成功。
     *
     * @var CreditSettlementGateway
     */
    private $creditSettlementGateway;

    /**
     * 构造函数。
     *
     * @param AdminDataScopeService $adminDataScopeService 数据范围服务，用于按管理员角色和绑定代理过滤信用导入记录。
     */
    public function __construct(
        AdminDataScopeService $adminDataScopeService,
        CreditSettlementGateway $creditSettlementGateway
    )
    {
        $this->adminDataScopeService = $adminDataScopeService;
        $this->creditSettlementGateway = $creditSettlementGateway;
    }

    /**
     * 查询批量信用导入记录列表。
     *
     * @param Request $request 当前请求对象；支持 page、per_page、limit、user_id、batch_no、credit_type、is_synced 等筛选参数。
     * @return \Illuminate\Http\JsonResponse
     */
    public function creditImportList(Request $request)
    {
        if ($filterError = $this->validateNumericFilters($request)) {
            return $filterError;
        }

        $query = CreditImport::query()->with('user');
        $this->applyFilters($query, $request);
        $this->applyDataScope($query, $request);

        return $this->success(
            $this->paginateImportQuery($query, $request),
            __('admin.credit_imports_fetched')
        );
    }

    /**
     * 新增单条批量信用导入记录。
     *
     * @param Request $request 当前请求对象；user_id 表示业务用户 ID，credit_type 表示信用类型，amount 表示信用额度金额。
     * @return \Illuminate\Http\JsonResponse
     */
    public function createCreditImport(Request $request)
    {
        if ($this->hasImportFile($request)) {
            return $this->importCsvRecords($request);
        }

        $validator = $this->makeImportValidator($request);
        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), ResponseCode::VALIDATION_FAILED);
        }

        $record = CreditImport::create($this->buildImportPayload($request));

        return $this->success($record, __('admin.credit_import_created'), ResponseCode::CREATED);
    }

    /**
     * 重试失败的批量信用导入记录。
     *
     * 参数逻辑说明：
     * - id：`credit_imports.id` 主键，表示需要重新放回待处理队列的信用导入记录。
     * - 只有 `is_synced=2` 的失败记录允许重试，避免待处理或成功记录重复进入信用同步队列。
     * - 重试动作只把 `is_synced` 改回 0 并清空 `fail_reason`，不伪造 MT4 信用同步结果。
     *
     * @param Request $request 当前请求对象，用于读取登录管理员并执行数据范围校验。
     * @param int|string $id 批量信用导入记录主键。
     * @return \Illuminate\Http\JsonResponse
     */
    public function retryCreditImport(Request $request, $id)
    {
        if ($routeIdError = $this->validateCreditImportRouteId($id)) {
            return $routeIdError;
        }

        $query = CreditImport::query()->where('id', (int) $id);
        $this->applyDataScope($query, $request);

        $record = $query->first();
        if (!$record) {
            return $this->error(__('admin.import_record_not_found'), ResponseCode::DATA_NOT_FOUND);
        }

        $syncStatus = (int) $record->is_synced;
        $isFailed = $syncStatus === 2;
        $isStaleProcessing = $this->isStaleProcessingCreditImport($record);
        if (!$isFailed && !$isStaleProcessing) {
            return $this->error(__('admin.import_retry_only_failed'), ResponseCode::OPERATION_NOT_ALLOWED);
        }

        $record->update([
            'is_synced' => 0,
            'fail_reason' => '',
            'updated_by' => (int) (auth('admin')->id() ?: 0),
        ]);

        return $this->success($record->fresh(), __('admin.credit_import_retry_success'));
    }

    /**
     * 同步单条待处理批量信用导入记录到 MT4。
     *
     * 参数逻辑说明：
     * - id：`credit_imports.id` 主键，表示本次要同步的单条信用导入记录。
     * - 只有 `is_synced=0` 的待处理记录允许发起真实 MT4 信用入账，避免成功或失败记录重复同步。
     * - 同步前先按后台管理员数据范围过滤，随后短暂 claim 为内部处理中状态 3，返回前必须落回 0/1/2。
     *
     * @param Request $request 当前请求对象，用于读取登录管理员并执行数据范围校验。
     * @param int|string $id 批量信用导入记录主键。
     * @return \Illuminate\Http\JsonResponse
     */
    public function syncCreditImport(Request $request, $id)
    {
        if ($routeIdError = $this->validateCreditImportRouteId($id)) {
            return $routeIdError;
        }

        $query = CreditImport::query()->where('id', (int) $id);
        $this->applyDataScope($query, $request);

        $record = $query->first();
        if (!$record) {
            return $this->error(__('admin.import_record_not_found'), ResponseCode::DATA_NOT_FOUND);
        }

        $syncStatus = (int) $record->is_synced;
        $isPending = $syncStatus === 0;
        $isStaleProcessing = $this->isStaleProcessingCreditImport($record);
        if (!$isPending && !$isStaleProcessing) {
            return $this->error(__('admin.import_sync_only_pending'), ResponseCode::OPERATION_NOT_ALLOWED);
        }

        // 原子 claim：把记录置为处理中状态 3；update 影响行数为 1 才表示本请求取得所有权，防止并发重复提交。
        if (!$this->claimPendingCreditImportForSync($record)) {
            return $this->error(__('admin.import_sync_only_pending'), ResponseCode::OPERATION_NOT_ALLOWED);
        }

        $record->refresh();
        try {
            // 网关调用异常统一转 unknown 结果，由 finishCreditImportSync 按失败落库，不向业务层抛裸异常。
            $result = $this->creditSettlementGateway->creditIn(
                (int) $record->user_id,
                (string) $record->amount,
                $this->importSyncComment($record)
            );
        } catch (Throwable $exception) {
            $result = DepositSettlementResult::unknown('gateway_exception');
        }

        return $this->finishCreditImportSync($record, $result);
    }

    /**
     * 校验信用导入路由 ID，避免非严格数字字符串命中 credit_imports.id。
     *
     * @param int|string $id 路由参数中的信用导入记录主键。
     * @return \Illuminate\Http\JsonResponse|null ID 非法时返回统一错误响应，否则返回 null。
     */
    private function validateCreditImportRouteId($id)
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
     * 下载批量信用导入 CSV 模板。
     *
     * @return \Symfony\Component\HttpFoundation\StreamedResponse
     */
    public function creditImportTemplate()
    {
        return $this->csvDownload('credit_import_template.csv', [
            ['user_id', 'user_name', 'mt4_login', 'credit_type', 'amount', 'batch_no', 'mt4_order_id', 'remarks'],
            ['10001', 'demo user', '10001', '2', '100.00', 'CRD-' . date('Ymd') . '-001', '0', 'optional remark'],
        ]);
    }

    /**
     * 导出当前筛选条件下的批量信用导入记录。
     *
     * @param Request $request 当前请求对象，支持 user_id、batch_no、credit_type、is_synced 等列表筛选参数。
     * @return \Symfony\Component\HttpFoundation\StreamedResponse
     */
    public function exportCreditImports(Request $request)
    {
        if ($filterError = $this->validateNumericFilters($request)) {
            return $filterError;
        }

        $query = CreditImport::query();
        $this->applyFilters($query, $request);
        $this->applyDataScope($query, $request);

        return $this->exportImportRecords($query, 'credit_imports_export.csv');
    }

    /**
     * 校验信用导入列表的数字筛选参数必须为整数。
     *
     * @param Request $request 当前请求对象，承载 user_id/credit_type/is_synced 筛选参数。
     * @return \Illuminate\Http\JsonResponse|null 任一已填筛选参数非法即返回错误响应；未填或通过时返回 null。
     */
    private function validateNumericFilters(Request $request)
    {
        $rules = [];

        foreach (['user_id', 'credit_type', 'is_synced'] as $field) {
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
     * 追加批量信用导入列表筛选条件。
     *
     * @param Builder $query 信用导入记录查询对象。
     * @param Request $request 当前请求对象，用于读取筛选参数。
     * @return void
     */
    private function applyFilters(Builder $query, Request $request): void
    {
        if ($request->filled('user_id')) {
            $query->where('user_id', (int) $request->input('user_id'));
        }

        if ($request->filled('batch_no')) {
            $query->where('batch_no', 'LIKE', '%' . $request->input('batch_no') . '%');
        }

        if ($request->filled('credit_type')) {
            $query->where('credit_type', (int) $request->input('credit_type'));
        }

        if ($request->filled('is_synced')) {
            $query->where('is_synced', (int) $request->input('is_synced'));
        }
    }

    /**
     * 按当前后台管理员的数据范围过滤信用导入记录。
     *
     * @param Builder $query 信用导入记录查询对象。
     * @param Request $request 当前请求对象，用于读取 admin guard 下的登录管理员。
     * @return void
     */
    private function applyDataScope(Builder $query, Request $request): void
    {
        $admin = $request->user('admin');
        if (!$admin) {
            return;
        }

        $this->adminDataScopeService->apply($query, $admin, 'user', 'user_id');
    }

    /**
     * 在真实 MT4 信用同步前 claim 待处理记录，避免同一记录被并发重复提交。
     *
     * @param Model $record 信用导入记录。
     * @return bool true=本请求成功取得该待处理记录。
     */
    private function claimPendingCreditImportForSync(Model $record): bool
    {
        $staleBefore = time() - self::SYNC_PROCESSING_STALE_SECONDS;

        return $record->newQuery()
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
            ]) === 1;
    }

    /**
     * 判断信用导入记录是否卡在处理中状态且超过陈旧阈值（可被重新认领）。
     *
     * @param Model $record 信用导入记录。
     * @return bool true=处理中超时，可被重试或同步重新认领。
     */
    private function isStaleProcessingCreditImport(Model $record): bool
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
     * 将 MT4 信用同步结果写回信用导入记录。
     *
     * @param Model $record 已经被本请求 claim 的信用导入记录。
     * @param DepositSettlementResult $result MT4 gateway 返回的闭合状态结果。
     * @return \Illuminate\Http\JsonResponse
     */
    private function finishCreditImportSync(Model $record, DepositSettlementResult $result)
    {
        // 仅当 MT4 明确 settled 才落已同步并保存订单号；其余一律失败关闭。
        if ($result->status() === 'settled') {
            $record->update([
                'is_synced' => 1,
                'mt4_order_id' => (int) $result->providerReference(),
                'fail_reason' => '',
                'updated_by' => (int) (auth('admin')->id() ?: 0),
            ]);

            return $this->success($record->fresh(), __('admin.credit_import_sync_success'));
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
     * 构造发给 MT4 的信用同步备注。
     *
     * @param Model $record 信用导入记录。
     * @return string MT4 comment；优先使用导入备注，备注为空时退回批次号便于审计追踪。
     */
    private function importSyncComment(Model $record): string
    {
        $remarks = trim((string) $record->remarks);

        return $remarks !== '' ? $remarks : (string) $record->batch_no;
    }

    /**
     * 分页返回信用导入记录。
     *
     * @param Builder $query 信用导入记录查询对象。
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
     * 创建信用导入记录参数验证器。
     *
     * 参数含义：
     * - user_id：业务用户 ID，必须存在于 `user_infos.user_id`。
     * - credit_type：信用类型，1=临时信用，2=永久信用，3=奖励信用，4=其他信用。
     * - amount：本次信用额度调整金额，必须大于 0。
     * - batch_no：导入批次号，用于追踪同一批导入记录。
     * - is_synced：同步状态，0=待处理，1=已同步，2=同步失败。
     *
     * @param Request $request 当前请求对象。
     * @return \Illuminate\Contracts\Validation\Validator
     */
    private function makeImportValidator(Request $request)
    {
        return $this->makeImportDataValidator($request->all());
    }

    /**
     * 创建信用导入记录参数验证器。
     *
     * @param array<string, mixed> $data 单条信用导入字段；可来自手工表单，也可来自 CSV 文件中的一行。
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
            'credit_type' => 'required|integer|in:1,2,3,4',
            'amount' => 'required|numeric|min:0.01',
            'batch_no' => 'required|string|max:100',
            'mt4_order_id' => 'nullable|integer|min:0',
            'is_synced' => 'nullable|integer|in:0,1,2',
            'fail_reason' => 'nullable|string|max:500',
            'remarks' => 'nullable|string|max:1000',
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
     * 构造信用导入记录写入数据。
     *
     * @param Request $request 当前请求对象，字段直接对应 `credit_imports` 表列。
     * @return array<string, mixed> 可直接写入 `credit_imports` 的字段集合。
     */
    private function buildImportPayload(Request $request): array
    {
        return $this->buildImportPayloadFromArray($request->all());
    }

    /**
     * 构造信用导入记录写入数据。
     *
     * @param array<string, mixed> $data 已通过校验的信用导入字段。
     * @return array<string, mixed> 可直接写入 credit_imports 的字段集合。
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
            'credit_type' => (int) ($data['credit_type'] ?? 0),
            'amount' => (string) ($data['amount'] ?? ''),
            'batch_no' => (string) ($data['batch_no'] ?? ''),
            'mt4_order_id' => (int) ($data['mt4_order_id'] ?? 0),
            'is_synced' => (int) ($data['is_synced'] ?? 0),
            'fail_reason' => (string) ($data['fail_reason'] ?? ''),
            'remarks' => (string) ($data['remarks'] ?? ''),
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
     * 解析 CSV 并批量写入信用导入队列。
     *
     * @param Request $request 当前请求对象。
     * @return \Illuminate\Http\JsonResponse
     */
    private function importCsvRecords(Request $request)
    {
        $file = $this->importFile($request);
        if (!$file || !$file->isValid()) {
            return $this->error(__('validation.file'), ResponseCode::VALIDATION_FAILED);
        }

        $rows = $this->parseCsvRows($file);
        if (empty($rows)) {
            return $this->error(__('validation.required', ['attribute' => 'file']), ResponseCode::VALIDATION_FAILED);
        }

        // 先逐行校验全部数据，任一行非法则整体拒绝，避免批量导入一半后才发现错误行。
        foreach ($rows as $index => $row) {
            $validator = $this->makeImportDataValidator($row);
            if ($validator->fails()) {
                return $this->error('CSV row ' . ($index + 2) . ': ' . $validator->errors()->first(), ResponseCode::VALIDATION_FAILED);
            }
        }

        // 全部行通过校验后在单个事务内批量落库，任一写入失败整体回滚。
        $created = DB::transaction(function () use ($rows) {
            return collect($rows)->map(function (array $row) {
                return CreditImport::create($this->buildImportPayloadFromArray($row));
            })->values();
        });

        return $this->success(['created' => $created->count(), 'records' => $created], __('admin.credit_import_created'), ResponseCode::CREATED);
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
     * 导出信用导入记录为 CSV。
     *
     * @param Builder $query 已追加筛选和数据范围的信用导入查询对象。
     * @param string $fileName 下载文件名。
     * @return \Symfony\Component\HttpFoundation\StreamedResponse
     */
    private function exportImportRecords(Builder $query, string $fileName)
    {
        $rows = [
            ['id', 'user_id', 'user_name', 'credit_type', 'amount', 'batch_no', 'mt4_order_id', 'is_synced', 'fail_reason', 'remarks', 'created_at'],
        ];

        $query->orderByDesc('created_at')->limit(5000)->get()->each(function ($record) use (&$rows) {
            $rows[] = [
                $record->id,
                $record->user_id,
                $record->user_name,
                $record->credit_type,
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
