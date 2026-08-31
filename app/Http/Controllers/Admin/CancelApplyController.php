<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/19
 * Time: 22:30
 */

namespace App\Http\Controllers\Admin;

use App\Constants\ResponseCode;
use App\Models\CancelApply;
use App\Models\OperationLog;
use App\Models\UserInfo;
use App\Models\UserLogin;
use App\Services\AdminCancelApplyQueryService;
use App\Services\AdminDataScopeService;
use App\Services\Mt4ManagerService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Throwable;

/**
 * 后台注销申请管理控制器。
 *
 * 文件功能：
 * - 负责后台查看、通过和拒绝客户账号注销申请。
 * - 审核时通过数据库行锁串行化同一申请，避免两个管理员并发重复调用 MT4 或覆盖审核状态。
 * - 通过注销申请前先调用 MT4 lockUser 禁用交易；成功后才标记 is_cancelled、禁用登录、禁止出金并软删除资料。
 * - 拒绝注销申请时，仅对前台申请阶段已锁定的账号调用 unlockUser，成功后恢复登录、交易和出金能力。
 * - 远端成功但本地事务失败时执行反向补偿，避免 MT4 与本地数据库处于相反状态。
 * - MT4 失败时 fail-closed，不改本地账号状态（对齐旧项目“无法连接则审核失败”语义）。
 * - 注销申请状态由 cancel_applies.status 表示：0=待处理，1=已通过，-1=已拒绝。
 * - 审核通过和拒绝都会写入 operation_logs，记录管理员、目标用户、状态变化和拒绝原因。
 *
 * 适用场景：
 * - 旧后台 POST 入口：cancelApplyList、cancelApplyApprove/{id}、cancelApplyReject/{id}。
 * - 输入为分页/状态筛选参数或审核动作主键与拒绝原因；输出为注销申请列表或审核结果。
 * - 审核类接口在 MT4 不可用时 fail-closed，不修改本地账号状态（对齐旧项目“无法连接则审核失败”语义）。
 */
class CancelApplyController extends AdminBaseController
{
    /**
     * MT4 网关管理器：注销审核通过前用 lockUser 禁用交易、拒绝时用 unlockUser 解锁。
     * 本控制器采用“先远端成功、再写本地”顺序并在本地失败时反向补偿；该依赖不可用时
     * 必须失败关闭（不改本地账号状态），否则会出现 MT4 与本地库互相矛盾的资金/交易状态。
     *
     * @var Mt4ManagerService
     */
    private $mt4Manager;

    /**
     * 后台数据范围服务：限制管理员可见与可审核的注销申请范围；
     * 缺失时任何管理员都能审核任意用户的注销，越权后果是停用/解锁他人数据范围内的账号。
     *
     * @var AdminDataScopeService
     */
    private $adminDataScopeService;

    /**
     * 注销申请查询服务：承载申请列表的分页、状态筛选与数据范围套用，控制器只做编排。
     * 列表口径（软删除资料、已注销用户展示规则）集中在此服务内，缺失时控制器无法按统一口径出列表。
     *
     * @var AdminCancelApplyQueryService
     */
    private $cancelApplyQueryService;

    /**
     * 构造注销申请管理控制器。
     *
     * @param Mt4ManagerService $mt4Manager MT4 Manager 服务，用于远端锁号/解锁交易账号。
     * @param AdminDataScopeService $adminDataScopeService 数据范围服务，限制管理员可见与可审核的注销申请。
     */
    public function __construct(
        Mt4ManagerService $mt4Manager,
        AdminDataScopeService $adminDataScopeService,
        AdminCancelApplyQueryService $cancelApplyQueryService
    )
    {
        $this->mt4Manager = $mt4Manager;
        $this->adminDataScopeService = $adminDataScopeService;
        $this->cancelApplyQueryService = $cancelApplyQueryService;
    }

    /**
     * 获取注销申请列表。
     *
     * index() 参数说明：
     * - page 表示当前页码，默认第 1 页。
     * - per_page 表示每页数量，默认 15 条。
     * - status 表示注销申请处理状态，0=待处理，1=已通过，-1=已拒绝。
     *
     * @param Request $request 当前 HTTP 请求对象，承载分页参数和状态筛选条件。
     * @return \Illuminate\Http\JsonResponse 返回分页注销申请列表，包含关联用户信息。
     */
    public function index(Request $request)
    {
        $admin = $request->user('admin');
        if (!$admin) {
            return $this->error(__('response.permission_denied'), ResponseCode::PERMISSION_DENIED);
        }

        $filters = [];
        foreach (['page', 'per_page', 'user_id', 'status', 'start_date', 'end_date'] as $field) {
            if ($request->filled($field)) {
                $filters[$field] = $request->input($field);
            }
        }
        $validator = Validator::make($filters, [
            'page' => 'sometimes|integer|min:1',
            'per_page' => 'sometimes|integer|min:1|max:100',
            'user_id' => 'sometimes|integer|min:1',
            'status' => 'sometimes|integer|in:-1,0,1',
            'start_date' => 'sometimes|date_format:Y-m-d',
            'end_date' => 'sometimes|date_format:Y-m-d|after_or_equal:start_date',
        ]);
        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), ResponseCode::VALIDATION_FAILED);
        }

        $applies = $this->cancelApplyQueryService->paginate($admin, $validator->validated());

        return $this->success($applies, __('admin.cancel_applies_fetched'));
    }

    /**
     * 审核通过注销申请。
     *
     * approve() 参数说明：
     * - $id 表示 cancel_applies 表主键，用于定位待处理注销申请。
     * - status=1 表示注销申请已通过。
     * - is_cancelled 表示用户已注销，写入 user_logins.is_cancelled。
     * - delete() 执行用户软删除；如果 UserInfo 未启用 SoftDeletes，则按模型默认删除行为执行。
     *
     * @param Request $request 当前 HTTP 请求对象，用于读取 admin guard 下的审核管理员和来源 IP。
     * @param int|string $id cancel_applies 表主键。
     * @return \Illuminate\Http\JsonResponse 审核通过结果响应。
     */
    public function approve(Request $request, $id)
    {
        // 标记本次审核是否新锁定 MT4；仅新锁定场景需要事务回滚后反向解锁补偿（历史待审数据可能未锁）。
        $shouldUnlockAfterRollback = false;
        $userId = 0;

        try {
            if ($routeIdError = $this->validateCancelApplyRouteId($id)) {
                return $routeIdError;
            }

            $id = (int) $id;
            $admin = $request->user('admin');
            if (!$admin) {
                return $this->error(__('response.permission_denied'), ResponseCode::PERMISSION_DENIED);
            }

            $reviewRemark = trim((string) $request->input('reason', ''));
            $remarkValidator = Validator::make(['reason' => $reviewRemark], [
                'reason' => 'nullable|string|max:500',
            ]);
            if ($remarkValidator->fails()) {
                return $this->error($remarkValidator->errors()->first(), ResponseCode::VALIDATION_FAILED);
            }

            $apply = CancelApply::find($id);
            if (!$apply || (int) $apply->status !== 0) {
                return $this->error(__('admin.cancel_apply_not_found_or_processed'), ResponseCode::DATA_NOT_FOUND);
            }

            if (!$this->adminDataScopeService->canAccessRecord($admin, $apply->user_id, $apply->created_by, 'user')) {
                return $this->error(__('response.permission_denied'), ResponseCode::PERMISSION_DENIED);
            }

            $userId = (int) $apply->user_id;
            // 事务内完成行锁、MT4 锁号与本地状态写入；事务外统一按结果分支处理“已被处理/MT4 失败”语义。
            $reviewResult = DB::transaction(function () use (
                $request,
                $admin,
                $id,
                $userId,
                $reviewRemark,
                &$shouldUnlockAfterRollback
            ): array {
                // 与前台申请保持 user_infos -> cancel_applies 的加锁顺序，降低交叉审核产生死锁的概率。
                $user = UserInfo::query()
                    ->where('user_id', $userId)
                    ->lockForUpdate()
                    ->first();
                $lockedApply = CancelApply::query()
                    ->whereKey($id)
                    ->lockForUpdate()
                    ->first();

                // 行锁后再次确认申请仍为待处理；已被并发管理员处理时返回标记，由外层转为“已被处理”错误，避免重复调用 MT4。
                if (!$lockedApply || (int) $lockedApply->status !== 0) {
                    return ['status' => 'processed'];
                }

                $wasAlreadyLocked = $this->isLocallyLockedForCancellation($user);
                $mt4Result = $this->executeMt4Action('lock', $userId);
                if (!$this->isMt4Success($mt4Result)) {
                    return ['status' => 'mt4_failed', 'mt4_result' => $mt4Result];
                }

                // 历史待审数据可能未经过前台锁号；仅这种本次新锁定场景需要在事务回滚后解锁补偿。
                $shouldUnlockAfterRollback = !$wasAlreadyLocked;
                $beforeStatus = (int) $lockedApply->status;
                $lockedApply->update([
                    'status' => 1,
                    'updated_by' => (string) $admin->id,
                ]);

                UserLogin::where('user_id', $userId)->update([
                    'is_cancelled' => 1,
                    'is_enabled' => 0,
                ]);

                if ($user) {
                    $user->is_mt4_enabled = 0;
                    $user->is_mt4_readonly = 1;
                    $user->is_withdrawal_allowed = 1;
                    $user->save();
                    $user->delete();
                }

                $this->writeCancelApplyOperationLog($request, $lockedApply, $admin, 'approve', $beforeStatus, 1, $reviewRemark);

                return ['status' => 'success'];
            });

            if (($reviewResult['status'] ?? '') === 'processed') {
                return $this->error(__('admin.cancel_apply_not_found_or_processed'), ResponseCode::DATA_NOT_FOUND);
            }
            if (($reviewResult['status'] ?? '') === 'mt4_failed') {
                return $this->mt4FailureResponse($userId, (array) ($reviewResult['mt4_result'] ?? []));
            }

            return $this->success([], __('admin.cancel_approved'));
        } catch (Throwable $exception) {
            // 本地事务回滚后执行反向补偿；补偿失败只记 critical 日志，仍返回 SERVER_ERROR，不伪造成功。
            if ($shouldUnlockAfterRollback && $userId > 0) {
                $this->compensateMt4Action('unlock', $userId, 'approve_local_rollback');
            }
            Log::error('Cancel apply approval failed and local transaction was rolled back.', [
                'apply_id' => (int) $id,
                'user_id' => $userId,
                'exception_class' => get_class($exception),
            ]);

            return $this->serverErrorResponse();
        }
    }

    /**
     * 拒绝注销申请。
     *
     * reject() 参数说明：
     * - $request 当前 HTTP 请求对象，承载 reason 拒绝原因。
     * - $id 表示 cancel_applies 表主键，用于定位待处理注销申请。
     * - reason 表示拒绝原因，写入 cancel_applies.reject_reason。
     * - status=-1 表示注销申请已拒绝。
     *
     * @param Request $request 当前 HTTP 请求对象，承载拒绝原因、admin guard 管理员和来源 IP。
     * @param int|string $id cancel_applies 表主键。
     * @return \Illuminate\Http\JsonResponse 拒绝结果响应。
     */
    public function reject(Request $request, $id)
    {
        // 标记本次审核是否实际执行了远端解锁；仅该场景需要事务回滚后重新锁号补偿。
        $shouldRelockAfterRollback = false;
        $userId = 0;

        try {
            if ($routeIdError = $this->validateCancelApplyRouteId($id)) {
                return $routeIdError;
            }

            $id = (int) $id;
            $admin = $request->user('admin');
            if (!$admin) {
                return $this->error(__('response.permission_denied'), ResponseCode::PERMISSION_DENIED);
            }

            $reason = trim((string) $request->input('reason', ''));
            $reasonValidator = Validator::make(['reason' => $reason], [
                'reason' => 'required|string|max:500',
            ]);
            if ($reasonValidator->fails()) {
                return $this->error($reasonValidator->errors()->first(), ResponseCode::VALIDATION_FAILED);
            }

            $apply = CancelApply::find($id);
            if (!$apply || (int) $apply->status !== 0) {
                return $this->error(__('admin.cancel_apply_not_found_or_processed'), ResponseCode::DATA_NOT_FOUND);
            }

            if (!$this->adminDataScopeService->canAccessRecord($admin, $apply->user_id, $apply->created_by, 'user')) {
                return $this->error(__('response.permission_denied'), ResponseCode::PERMISSION_DENIED);
            }

            $userId = (int) $apply->user_id;
            // 事务内完成行锁、远端解锁与本地状态恢复；事务外统一按结果分支处理“已被处理/MT4 失败”语义。
            $reviewResult = DB::transaction(function () use (
                $request,
                $admin,
                $id,
                $userId,
                $reason,
                &$shouldRelockAfterRollback
            ): array {
                $user = UserInfo::query()
                    ->where('user_id', $userId)
                    ->lockForUpdate()
                    ->first();
                $lockedApply = CancelApply::query()
                    ->whereKey($id)
                    ->lockForUpdate()
                    ->first();

                // 行锁后再次确认申请仍为待处理，避免并发审核下对已处理申请重复执行解锁或状态覆盖。
                if (!$lockedApply || (int) $lockedApply->status !== 0) {
                    return ['status' => 'processed'];
                }

                // 只有本地三个限制标志完整表明“前台已锁号”时才调用远端解锁，兼容历史待审记录。
                if ($this->isLocallyLockedForCancellation($user)) {
                    $mt4Result = $this->executeMt4Action('unlock', $userId);
                    if (!$this->isMt4Success($mt4Result)) {
                        return ['status' => 'mt4_failed', 'mt4_result' => $mt4Result];
                    }
                    $shouldRelockAfterRollback = true;
                }

                $beforeStatus = (int) $lockedApply->status;
                $lockedApply->update([
                    'status' => -1,
                    'reject_reason' => $reason,
                    'updated_by' => (string) $admin->id,
                ]);

                UserLogin::where('user_id', $userId)->update([
                    'is_cancelled' => 0,
                    'is_enabled' => 1,
                ]);

                if ($user) {
                    $user->is_mt4_enabled = 1;
                    $user->is_mt4_readonly = 0;
                    $user->is_withdrawal_allowed = 0;
                    $user->save();
                }

                $this->writeCancelApplyOperationLog($request, $lockedApply, $admin, 'reject', $beforeStatus, -1, $reason);

                return ['status' => 'success'];
            });

            if (($reviewResult['status'] ?? '') === 'processed') {
                return $this->error(__('admin.cancel_apply_not_found_or_processed'), ResponseCode::DATA_NOT_FOUND);
            }
            if (($reviewResult['status'] ?? '') === 'mt4_failed') {
                return $this->mt4FailureResponse($userId, (array) ($reviewResult['mt4_result'] ?? []));
            }

            return $this->success([], __('admin.cancel_rejected'));
        } catch (Throwable $exception) {
            // 本地事务回滚后重新锁号补偿；补偿失败只记 critical 日志，仍返回 SERVER_ERROR，不伪造成功。
            if ($shouldRelockAfterRollback && $userId > 0) {
                $this->compensateMt4Action('lock', $userId, 'reject_local_rollback');
            }
            Log::error('Cancel apply rejection failed and local transaction was rolled back.', [
                'apply_id' => (int) $id,
                'user_id' => $userId,
                'exception_class' => get_class($exception),
            ]);

            return $this->serverErrorResponse();
        }
    }

    /**
     * 校验注销申请路由主键。
     *
     * 失败场景：
     * - id 缺失或不是整数时返回 VALIDATION_FAILED，避免字符串被强制转换后误审其他申请。
     *
     * @param mixed $id cancel_applies 表主键原始路由值。
     * @return \Illuminate\Http\JsonResponse|null 校验失败返回统一错误响应，通过时返回 null。
     */
    private function validateCancelApplyRouteId($id)
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
     * 判断本地资料是否完整处于销户申请锁定状态。
     *
     * @param UserInfo|null $user 用户业务资料；历史申请可能没有对应资料。
     * @return bool true 表示 MT4 已禁用、账号只读且禁止出金，拒绝审核前需要解锁远端。
     */
    private function isLocallyLockedForCancellation(UserInfo $user = null): bool
    {
        return $user !== null
            && (int) $user->is_mt4_enabled === 0
            && (int) $user->is_mt4_readonly === 1
            && (int) $user->is_withdrawal_allowed === 1;
    }

    /**
     * 执行 MT4 锁号或解锁动作，并把异常或非数组结果规范化为失败结果。
     *
     * @param string $action 远端动作；lock=锁号，unlock=解锁。
     * @param int $userId 目标业务用户 ID。
     * @return array<string, mixed> MT4 规范化结果，status=ok 且 err 缺省或为 0 才表示成功。
     */
    private function executeMt4Action(string $action, int $userId): array
    {
        try {
            $result = $action === 'unlock'
                ? $this->mt4Manager->unlockUser($userId)
                : $this->mt4Manager->lockUser($userId);

            if (is_array($result)) {
                return $result;
            }

            return [
                'status' => 'error',
                'error_code' => 'malformed_response',
            ];
        } catch (Throwable $exception) {
            Log::error('Cancel apply MT4 action threw an exception.', [
                'action' => $action,
                'user_id' => $userId,
                'exception_class' => get_class($exception),
            ]);

            return [
                'status' => 'error',
                'error_code' => 'transport_exception',
            ];
        }
    }

    /**
     * 严格判断 MT4 是否明确执行成功。
     *
     * @param array<string, mixed> $result MT4 服务返回结果。
     * @return bool 仅 status=ok 且 err 字段缺省或等于 0 时返回 true，其余情况均失败关闭。
     */
    private function isMt4Success(array $result): bool
    {
        if (strtolower(trim((string) ($result['status'] ?? ''))) !== 'ok') {
            return false;
        }

        return !array_key_exists('err', $result)
            || trim((string) $result['err']) === '0';
    }

    /**
     * 返回统一 MT4 同步失败响应。
     *
     * @param int $userId 远端操作目标业务用户 ID。
     * @param array<string, mixed> $result MT4 原始规范化结果。
     * @return \Illuminate\Http\JsonResponse 返回 MT4_SYNC_FAILED，并附带可审计错误码。
     */
    private function mt4FailureResponse(int $userId, array $result)
    {
        $errorCode = trim((string) ($result['error_code'] ?? ($result['err'] ?? 'provider_rejected')));

        return $this->error(__('response.mt4_sync_failed'), ResponseCode::MT4_SYNC_FAILED, [
            'user_id' => $userId,
            'error_code' => $errorCode !== '' ? $errorCode : 'provider_rejected',
        ]);
    }

    /**
     * 在本地事务回滚后执行方向相反的 MT4 补偿动作。
     *
     * @param string $action 补偿动作；通过回滚使用 unlock，拒绝回滚使用 lock。
     * @param int $userId 需要恢复远端状态的业务用户 ID。
     * @param string $reason 补偿原因，用于服务端日志定位分布式事务失败点。
     * @return void 补偿失败只记录高优先级日志，原业务仍返回 SERVER_ERROR，不能伪造成功。
     */
    private function compensateMt4Action(string $action, int $userId, string $reason): void
    {
        $result = $this->executeMt4Action($action, $userId);
        if ($this->isMt4Success($result)) {
            return;
        }

        Log::critical('Cancel apply MT4 compensation failed.', [
            'action' => $action,
            'reason' => $reason,
            'user_id' => $userId,
            'error_code' => (string) ($result['error_code'] ?? ($result['err'] ?? 'provider_rejected')),
        ]);
    }

    /**
     * 写入注销申请审核操作日志。
     *
     * @param Request $request 当前 HTTP 请求对象，用于记录来源 IP。
     * @param CancelApply $apply 注销申请记录。
     * @param object $admin 当前审核管理员。
     * @param string $action 审核动作，approve=通过，reject=拒绝。
     * @param int $beforeStatus 审核前状态。
     * @param int $afterStatus 审核后状态。
     * @param string $reason 拒绝原因；通过时为空字符串。
     * @return void
     */
    private function writeCancelApplyOperationLog(Request $request, CancelApply $apply, $admin, string $action, int $beforeStatus, int $afterStatus, string $reason): void
    {
        OperationLog::create([
            'admin_id' => $admin->id,
            'admin_name' => $admin->username,
            'target_user_id' => (int) $apply->user_id,
            'order_no' => 'cancel_apply:' . $apply->id,
            'content' => sprintf(
                'Review cancel apply id:%s; action:%s; user_id:%s; status:%s->%s; reason:%s',
                $apply->id,
                $action,
                $apply->user_id,
                $beforeStatus,
                $afterStatus,
                $reason
            ),
            'ip' => $request->ip() ?: '',
            'action_type' => 0,
        ]);
    }
}
