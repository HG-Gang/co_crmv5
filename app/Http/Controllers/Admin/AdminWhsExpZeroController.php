<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/19
 * Time: 16:26
 */

namespace App\Http\Controllers\Admin;

use App\Contracts\DepositSettlementGateway;
use App\Models\OperationLog;
use App\Models\UserInfo;
use App\Models\UserTrade;
use App\Models\WhsExpZero;
use App\Services\AdminDataScopeService;
use App\Services\Payment\DepositSettlementResult;
use App\Constants\ResponseCode;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Throwable;

/**
 * 后台仓位清零管理控制器。
 *
 * 功能逻辑说明：
 * - 本控制器负责处理爆仓用户的余额清零操作。
 * - 当用户余额为负且没有持仓时，可以执行清零操作。
 * - 从旧项目AdminWhsExpZeroController迁移核心业务逻辑：先按信用与负余额计算补入金额，再调用 MT4 入金，成功后落库并回写本地余额镜像。
 * - 清零操作会记录到whs_exp_zeros表用于审计；MT4 失败时 fail-closed，不把余额伪造成已清零。
 *
 * 文件功能：
 * - 清零候选列表、旧入口批量预登记、执行一键清零、清零记录列表。
 * - 输入 user_id/status 等；输出清零记录与前端兼容字段（status 0=处理中，1=待处理，2=已完成，3=失败）。
 *
 * 适用场景：
 * - 后台"仓位清零"页面；清零前必须满足余额为负且无未平仓持仓的条件。
 *
 * 安全边界：
 * - 数据范围：列表与清零均按 AdminDataScopeService 限制可见/可操作用户。
 * - fail-closed：资金操作先原子认领为 status=0，MT4 明确 settled 后才置 2 并回写 total_funds=0；其余情况置 3，余额保持原状。
 * - 不入金任何敏感值；md5_key 仅作为记录快照指纹，不承载密钥语义。
 */
class AdminWhsExpZeroController extends AdminBaseController
{
    /**
     * 后台数据范围服务。
     *
     * @var AdminDataScopeService
     */
    protected $adminDataScopeService;

    /**
     * MT4 入金结算网关。
     *
     * @var DepositSettlementGateway
     */
    protected $depositSettlementGateway;

    /**
     * 构造函数。
     *
     * @param AdminDataScopeService $adminDataScopeService 数据范围服务。
     * @param DepositSettlementGateway $depositSettlementGateway MT4 入金网关，用于把负余额补到 0。
     */
    public function __construct(
        AdminDataScopeService $adminDataScopeService,
        DepositSettlementGateway $depositSettlementGateway
    ) {
        $this->adminDataScopeService = $adminDataScopeService;
        $this->depositSettlementGateway = $depositSettlementGateway;
    }

    /**
     * 校验可选的 user_id 筛选参数。
     *
     * @param Request $request 当前请求对象，读取 user_id。
     * @param bool $required true 表示必填（清零操作），false 表示可选（列表筛选）。
     * @return \Illuminate\Http\JsonResponse|null user_id 非法时返回统一错误响应，否则返回 null。
     */
    private function validateUserId(Request $request, bool $required = false)
    {
        if (!$required && !$request->filled('user_id')) {
            return null;
        }

        $validator = Validator::make(['user_id' => $request->input('user_id')], [
            'user_id' => ($required ? 'required|' : '') . 'integer',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), ResponseCode::VALIDATION_FAILED);
        }

        return null;
    }

    /**
     * 校验可选的清零记录状态筛选参数。
     *
     * @param Request $request 当前请求对象，读取 status。
     * @return \Illuminate\Http\JsonResponse|null status 非法时返回统一错误响应，否则返回 null。
     */
    private function validateRecordStatus(Request $request)
    {
        if (!$request->filled('status')) {
            return null;
        }

        $validator = Validator::make(['status' => $request->input('status')], [
            'status' => 'integer|in:0,1,2,3',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), ResponseCode::VALIDATION_FAILED);
        }

        return null;
    }

    /**
     * 校验候选或记录列表的分页、名称和日期筛选。
     *
     * @param Request $request 当前请求。
     * @param bool $records true 时同时校验记录状态和日期。
     * @return \Illuminate\Http\JsonResponse|null
     */
    private function validateListFilters(Request $request, bool $records = false)
    {
        $rules = [
            'page' => 'sometimes|integer|min:1',
            'per_page' => 'sometimes|integer|between:1,100',
            'limit' => 'sometimes|integer|between:1,100',
            'user_id' => 'sometimes|nullable|integer|min:1',
            'user_name' => 'sometimes|nullable|string|max:100',
        ];

        if ($records) {
            $rules['status'] = 'sometimes|nullable|integer|in:0,1,2,3';
            $rules['start_date'] = 'sometimes|nullable|date_format:Y-m-d';
            $rules['end_date'] = 'sometimes|nullable|date_format:Y-m-d';
        }

        $validator = Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), ResponseCode::VALIDATION_FAILED);
        }

        $startDate = trim((string) $request->input('start_date', ''));
        $endDate = trim((string) $request->input('end_date', ''));
        if ($records && $startDate !== '' && $endDate !== '' && $endDate < $startDate) {
            return $this->error(__('response.validation_failed'), ResponseCode::VALIDATION_FAILED);
        }

        return null;
    }

    /** @return \Illuminate\Database\Eloquent\Builder */
    private function candidateQuery(Request $request)
    {
        $query = UserInfo::query()
            ->select([
                'user_infos.user_id as userId',
                'user_infos.user_name as userName',
                'user_infos.total_funds as userBalance',
                'user_infos.effective_credit as userCredit',
            ])
            ->where('user_infos.total_funds', '<', 0)
            ->where('user_infos.account_type', 2)
            ->whereNotExists(function ($subQuery) {
                $subQuery->select(DB::raw(1))
                    ->from('user_trades')
                    ->whereColumn('user_trades.user_id', 'user_infos.user_id')
                    ->whereIn('user_trades.cmd', [0, 1, 2, 3, 4, 5])
                    ->where(function ($q) {
                        $q->whereNull('user_trades.close_time')
                            ->orWhere('user_trades.close_time', 0)
                            ->orWhere('user_trades.close_time', '1970-01-01 00:00:00')
                            ->orWhere('user_trades.close_time', '1970-01-02 00:00:00');
                    });
            })
            ->whereNotExists(function ($subQuery) {
                $subQuery->select(DB::raw(1))
                    ->from('whs_exp_zeros')
                    ->whereColumn('whs_exp_zeros.user_id', 'user_infos.user_id')
                    ->whereIn('whs_exp_zeros.status', [0, 1])
                    ->whereNull('whs_exp_zeros.deleted_at');
            });

        if ($request->filled('user_id')) {
            $query->where('user_infos.user_id', (int) $request->input('user_id'));
        }
        if ($request->filled('user_name')) {
            $query->where('user_infos.user_name', 'like', '%' . trim((string) $request->input('user_name')) . '%');
        }

        $admin = $request->user('admin');
        if ($admin) {
            $this->adminDataScopeService->apply($query, $admin, 'user', 'user_id');
        }

        return $query;
    }

    /** @return \Illuminate\Database\Eloquent\Builder */
    private function openPositionQuery(int $userId)
    {
        return UserTrade::query()
            ->where('user_id', $userId)
            ->whereIn('cmd', [0, 1, 2, 3, 4, 5])
            ->where(function ($query) {
                $query->whereNull('close_time')
                    ->orWhere('close_time', 0)
                    ->orWhere('close_time', '1970-01-01 00:00:00')
                    ->orWhere('close_time', '1970-01-02 00:00:00');
            });
    }

    /**
     * 获取需要清零的用户列表（余额为负且无持仓的用户）。
     *
     * zeroList() 参数说明：
     * - page：当前页码。
     * - per_page/limit：每页数量。
     *
     * 功能逻辑说明：
     * - 查询余额为负的用户（total_funds < 0）。
     * - 排除有持仓的用户（在user_trades表中无CMD=0-5且close_time为空或旧MT4未平仓时间的记录）。
     * - 排除已经提交清零申请且待处理的用户。
     * - 这些用户可以执行一键清零操作。
     *
     * @param Request $request 当前HTTP请求对象。
     * @return \Illuminate\Http\JsonResponse 返回需要清零的用户列表。
     */
    public function zeroList(Request $request)
    {
        try {
            if ($validationError = $this->validateListFilters($request)) {
                return $validationError;
            }

            // 读取分页参数，per_page 与 Layui 的 limit 双兼容。
            $page = (int) $request->input('page', 1);
            $perPage = (int) $request->input('per_page', $request->input('limit', 15));

            $query = $this->candidateQuery($request);

            // 负余额越多的用户越靠前，便于优先处理风险更大的账户。
            $users = $query->orderBy('user_infos.total_funds', 'asc')
                ->paginate($perPage, ['*'], 'page', $page);

            // 格式化金额为两位小数字符串，并给出本次需要的补入金额 needZeroAmount。
            $usersData = collect($users->items())->map(function ($user) {
                return [
                    'userId' => (int) $user->userId,
                    'userName' => $user->userName,
                    'userBalance' => number_format($user->userBalance, 2, '.', ''),
                    'userCredit' => number_format($user->userCredit, 2, '.', ''),
                    'needZeroAmount' => number_format(abs($user->userBalance), 2, '.', ''),
                ];
            })->toArray();

            // 返回格式化列表与总数。
            return $this->success([
                'data' => $usersData,
                'count' => $users->total(),
            ], __('admin.zero_list_fetched'));

        } catch (\Exception $e) {
            \Log::error('AdminWhsExpZeroController.zeroList error: ' . $e->getMessage());
            return $this->serverErrorResponse();
        }
    }

    /**
     * 扫描当前管理员范围内的清零候选并创建待处理记录。
     *
     * @param Request $request 当前请求，可选 user_id/user_name 用于收窄扫描范围。
     * @return \Illuminate\Http\JsonResponse
     */
    public function scanCandidates(Request $request)
    {
        try {
            if ($validationError = $this->validateListFilters($request)) {
                return $validationError;
            }

            $candidateIds = $this->candidateQuery($request)
                ->orderBy('user_infos.user_id')
                ->get()
                ->pluck('userId')
                ->map(static fn ($id): int => (int) $id)
                ->all();
            $admin = $request->user('admin');
            $adminId = (string) ($admin ? $admin->id : (auth('admin')->id() ?? ''));
            $created = [];

            foreach ($candidateIds as $userId) {
                $record = DB::transaction(function () use ($userId, $admin, $adminId) {
                    $user = UserInfo::query()->where('user_id', $userId)->lockForUpdate()->first();
                    if (!$user || (int) $user->account_type !== 2 || (float) $user->total_funds >= 0) {
                        return null;
                    }
                    if ($admin && !$this->adminDataScopeService->canAccessRecord(
                        $admin,
                        $userId,
                        $user->created_by,
                        'user'
                    )) {
                        return null;
                    }
                    if ($this->openPositionQuery($userId)->lockForUpdate()->exists()) {
                        return null;
                    }
                    if (WhsExpZero::query()->where('user_id', $userId)
                        ->whereIn('status', [0, 1])->whereNull('deleted_at')->lockForUpdate()->exists()) {
                        return null;
                    }

                    $now = time();

                    return WhsExpZero::create([
                        'user_id' => $userId,
                        'user_name' => (string) $user->user_name,
                        'balance' => round((float) $user->total_funds, 2),
                        'credit' => round((float) $user->effective_credit, 2),
                        'status' => 1,
                        'md5_key' => strtoupper(md5((string) $userId)),
                        'created_by' => $adminId,
                        'updated_by' => $adminId,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                });

                if ($record) {
                    $created[] = $this->formatZeroRecord($record);
                }
            }

            return $this->success([
                'created_count' => count($created),
                'records' => $created,
            ], __('admin.zero_record_list_fetched'));
        } catch (Throwable $exception) {
            \Log::error('AdminWhsExpZeroController.scanCandidates failed', [
                'exception' => get_class($exception),
                'message' => $exception->getMessage(),
            ]);

            return $this->serverErrorResponse();
        }
    }

    /**
     * 执行一键清零操作。
     *
     * oneKeyZero() 参数说明：
     * - user_id：用户ID。
     *
     * 功能逻辑说明：
     * - 验证用户余额是否为负、是否无持仓、是否已有待处理清零记录。
     * - 按旧项目口径计算 MT4 入金金额：信用足以覆盖负余额时入金 abs(balance)，否则入金 abs(balance)-credit。
     * - 先落 status=1 清零记录，再调用 DepositSettlementGateway；成功后 status=2 并回写本地 total_funds=0；失败 status=3 fail-closed。
     *
     * @param Request $request 当前HTTP请求对象。
     * @return \Illuminate\Http\JsonResponse 返回清零结果。
     */
    public function oneKeyZero(Request $request)
    {
        try {
            if ($userIdError = $this->validateUserId($request, true)) {
                return $userIdError;
            }

            $userId = (int) $request->input('user_id');
            $admin = $request->user('admin');

            $adminId = (string) ($admin ? $admin->id : (auth('admin')->id() ?? ''));
            $claim = DB::transaction(function () use ($userId, $admin, $adminId) {
                $user = UserInfo::query()->where('user_id', $userId)->lockForUpdate()->first();
                if (!$user || ($admin && !$this->adminDataScopeService->canAccessRecord(
                    $admin,
                    $userId,
                    $user->created_by,
                    'user'
                ))) {
                    return ['error_code' => ResponseCode::DATA_NOT_FOUND, 'error_message' => __('admin.user_not_found')];
                }
                if ((int) $user->account_type !== 2) {
                    return [
                        'error_code' => ResponseCode::OPERATION_NOT_ALLOWED,
                        'error_message' => __('admin.whs_zero_customer_only'),
                    ];
                }
                if ((float) $user->total_funds >= 0) {
                    return ['error_code' => ResponseCode::OPERATION_NOT_ALLOWED, 'error_message' => __('admin.whs_zero_balance_not_negative')];
                }
                if ($this->openPositionQuery($userId)->lockForUpdate()->exists()) {
                    return ['error_code' => ResponseCode::OPERATION_NOT_ALLOWED, 'error_message' => __('admin.whs_zero_has_open_position')];
                }

                $processing = WhsExpZero::query()->where('user_id', $userId)
                    ->where('status', 0)->whereNull('deleted_at')->lockForUpdate()->first();
                if ($processing) {
                    return ['error_code' => ResponseCode::DATA_ALREADY_EXISTS, 'error_message' => __('admin.whs_zero_pending_exists')];
                }

                $balance = round((float) $user->total_funds, 2);
                $credit = round((float) $user->effective_credit, 2);
                $absBalance = abs($balance);
                $depositAmount = $credit >= $absBalance
                    ? $absBalance
                    : round($absBalance - $credit, 2);
                if ($depositAmount <= 0) {
                    return ['error_code' => ResponseCode::OPERATION_NOT_ALLOWED, 'error_message' => __('admin.whs_zero_invalid_amount')];
                }

                $now = time();
                $record = WhsExpZero::query()->where('user_id', $userId)
                    ->where('status', 1)->whereNull('deleted_at')->lockForUpdate()->latest('id')->first();
                $snapshot = [
                    'user_name' => (string) $user->user_name,
                    'balance' => $balance,
                    'credit' => $credit,
                    'status' => 0,
                    'updated_by' => $adminId,
                    'updated_at' => $now,
                ];
                if ($record) {
                    $record->update($snapshot);
                } else {
                    $record = WhsExpZero::create($snapshot + [
                        'user_id' => $userId,
                        'md5_key' => md5($userId . '|' . $balance . '|' . $credit . '|' . $absBalance . '|' . $now),
                        'created_by' => $adminId,
                        'created_at' => $now,
                    ]);
                }

                return [
                    'record' => $record,
                    'deposit_amount' => number_format($depositAmount, 2, '.', ''),
                ];
            });

            if (isset($claim['error_code'])) {
                return $this->error($claim['error_message'], (int) $claim['error_code']);
            }

            /** @var WhsExpZero $record */
            $record = $claim['record'];
            $depositAmountText = (string) $claim['deposit_amount'];
            $comment = \App\Constants\Mt4RemarkCodes::WHS_ZERO . $userId;

            try {
                $result = $this->depositSettlementGateway->deposit($userId, $depositAmountText, $comment);
            } catch (Throwable $exception) {
                $result = DepositSettlementResult::unknown('gateway_exception');
            }

            if ($result->status() !== 'settled') {
                WhsExpZero::query()->whereKey($record->id)->where('status', 0)->update([
                    'status' => 3, 'updated_by' => $adminId, 'updated_at' => time(),
                ]);

                return $this->error(__('response.mt4_sync_failed'), ResponseCode::MT4_SYNC_FAILED, [
                    'user_id' => $userId,
                    'status' => 3,
                    'error_code' => $result->errorCode(),
                    'deposit_amount' => $depositAmountText,
                ]);
            }

            $adminName = $admin ? (string) $admin->username : '';
            $clientIp = $request->ip() ?: '';
            DB::transaction(function () use ($record, $userId, $adminId, $adminName, $clientIp, $result, $depositAmountText) {
                $lockedRecord = WhsExpZero::query()->whereKey($record->id)->lockForUpdate()->firstOrFail();
                $lockedRecord->update([
                    'status' => 2,
                    'updated_by' => $adminId,
                    'updated_at' => time(),
                ]);

                UserInfo::where('user_id', $userId)->update([
                    'total_funds' => 0,
                    'updated_at' => time(),
                ]);

                OperationLog::create([
                    'admin_id' => (int) ($adminId !== '' ? $adminId : 0),
                    'admin_name' => $adminName,
                    'target_user_id' => $userId,
                    'order_no' => 'whs_exp_zero:' . $record->id,
                    'content' => sprintf(
                        'whs_exp_zero; user_id:%s; deposit_amount:%s; provider_reference:%s; balance_before:%s; credit:%s',
                        $userId,
                        $depositAmountText,
                        (string) $result->providerReference(),
                        (string) $lockedRecord->balance,
                        (string) $lockedRecord->credit
                    ),
                    'ip' => $clientIp,
                    'action_type' => 3,
                ]);
            });

            $payload = $this->formatZeroRecord($record->fresh());
            $payload['provider_reference'] = $result->providerReference();
            $payload['deposit_amount'] = $depositAmountText;

            return $this->success($payload, __('admin.whs_zero_completed'), ResponseCode::SUCCESS);
        } catch (Throwable $e) {
            \Log::error('AdminWhsExpZeroController.oneKeyZero failed', [
                'exception' => get_class($e),
                'message' => $e->getMessage(),
                'user_id' => $request->input('user_id'),
            ]);
            return $this->serverErrorResponse();
        }
    }

    /**
     * 获取清零记录列表。
     *
     * recordList() 参数说明：
     * - page：当前页码。
     * - per_page/limit：每页数量。
     * - status：记录状态，1=待处理，2=已完成，3=失败。
     * - user_id：用户ID筛选。
     *
     * @param Request $request 当前HTTP请求对象。
     * @return \Illuminate\Http\JsonResponse 返回清零记录列表。
     */
    public function recordList(Request $request)
    {
        try {
            if ($validationError = $this->validateListFilters($request, true)) {
                return $validationError;
            }

            // 读取分页参数，per_page 与 limit 双兼容。
            $page = (int) $request->input('page', 1);
            $perPage = (int) $request->input('per_page', $request->input('limit', 15));

            // 构建清零记录查询并预加载用户资料。
            $query = WhsExpZero::query()
                ->with('user');

            // 应用可选的 status 与 user_id 筛选。
            if ($request->filled('status')) {
                $query->where('status', $request->status);
            }

            if ($request->filled('user_id')) {
                $query->where('user_id', (int) $request->input('user_id'));
            }

            if ($request->filled('user_name')) {
                $query->where('user_name', 'like', '%' . trim((string) $request->input('user_name')) . '%');
            }

            if ($request->filled('start_date')) {
                $query->where('created_at', '>=', strtotime((string) $request->input('start_date') . ' 00:00:00'));
            }

            if ($request->filled('end_date')) {
                $query->where('created_at', '<=', strtotime((string) $request->input('end_date') . ' 23:59:59'));
            }

            // 应用管理员数据范围，限制可见记录。
            $admin = $request->user('admin');
            if ($admin) {
                $this->adminDataScopeService->apply($query, $admin, 'user', 'user_id');
            }

            // 按创建时间倒序分页，最新操作排前。
            $records = $query->orderByDesc('created_at')
                ->paginate($perPage, ['*'], 'page', $page);

            // 格式化为前端兼容字段结构。
            $recordsData = collect($records->items())->map(function (WhsExpZero $record) {
                return $this->formatZeroRecord($record);
            })->toArray();

            // 返回格式化列表与总数。
            return $this->success([
                'data' => $recordsData,
                'count' => $records->total(),
            ], __('admin.zero_record_list_fetched'));

        } catch (Throwable $e) {
            \Log::error('AdminWhsExpZeroController.recordList failed', [
                'exception' => get_class($e),
                'message' => $e->getMessage(),
            ]);
            return $this->serverErrorResponse();
        }
    }

    /**
     * 获取状态名称。
     *
     * @param int $status 状态值。
     * @return string 状态名称。
     */
    private function getStatusName(int $status): string
    {
        $statusMap = [
            0 => '处理中',
            1 => '待处理',
            2 => '已清零',
            3 => '失败',
        ];

        return $statusMap[$status] ?? '未知';
    }

    /**
     * 将 whs_exp_zeros 真实字段转换成前端兼容字段。
     *
     * @param WhsExpZero $record 清零记录。
     * @return array<string, mixed>
     */
    private function formatZeroRecord(WhsExpZero $record): array
    {
        $balance = (float) $record->balance;
        $credit = (float) $record->credit;
        $createdAt = $this->timestampValue($record->created_at);
        $updatedAt = $this->timestampValue($record->updated_at);

        return [
            'id' => $record->id,
            'user_id' => (int) $record->user_id,
            'user_name' => (string) $record->user_name,
            'balance_before' => number_format($balance, 2, '.', ''),
            'credit_amount' => number_format($credit, 2, '.', ''),
            'zero_amount' => number_format(abs($balance), 2, '.', ''),
            'status' => (int) $record->status,
            'status_name' => $this->getStatusName((int) $record->status),
            'fail_reason' => '',
            'created_at' => $createdAt > 0 ? date('Y-m-d H:i:s', $createdAt) : '',
            'processed_at' => in_array((int) $record->status, [0, 1], true) || $updatedAt <= 0
                ? ''
                : date('Y-m-d H:i:s', $updatedAt),
        ];
    }

    /**
     * 兼容 Eloquent 日期对象和当前项目的 10 位整数时间戳。
     *
     * @param mixed $value 时间字段值。
     * @return int
     */
    private function timestampValue($value): int
    {
        if ($value instanceof \DateTimeInterface) {
            return $value->getTimestamp();
        }

        return (int) $value;
    }
}
