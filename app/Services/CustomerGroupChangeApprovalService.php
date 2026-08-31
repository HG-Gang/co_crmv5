<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/12
 * Time: 14:39
 */

namespace App\Services;

use App\Constants\ResponseCode;
use App\Models\Admin;
use App\Models\OperationLog;
use App\Models\TransApplyLog;
use App\Models\UserInfo;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * 旧客户转组审批完成服务。
 *
 * 文件功能：
 * - 完成旧后台客户组别变更申请（trans_apply_log）的审批：用户级缓存锁串行化同一客户的并发审批，
 *   先调 MT4 changeGroup 成功、再本地落库（先远端后本地），并写操作日志与审批结果。
 * - 审批前经 AdminDataScopeService 确认目标客户在管理员可见代理树内；MT4 不可用或返回非 ok 时失败关闭。
 * - 明确不负责：代理确认（agent-confirmation）工作流，二者路由与状态互不复用。
 */
class CustomerGroupChangeApprovalService
{
    /**
     * 客户组别变更审批的用户级互斥锁 TTL（秒），固定 60。覆盖一次“预检 + MT4 changeGroup + 本地落库”
     * 的正常耗时；同一用户并发审批由该锁串行化，锁过期后仍有库内状态校验兜底，
     * 过短会造成并发双写，过长则阻塞其他管理员的正常审批重试。
     *
     * @var int
     */
    private const LOCK_TTL_SECONDS = 60;

    /**
     * MT4 网关管理器：组别变更必须先在 MT4 侧 changeGroup 成功、再写本地（先远端后本地）；
     * 它不可用或返回非 ok 时审批失败关闭（THIRD_PARTY_ERROR），否则 MT4 交易组与本地 group_id 会相互矛盾。
     *
     * @var Mt4ManagerService
     */
    private $mt4Manager;

    /**
     * 后台数据范围服务：审批前确认目标客户落在管理员的可见代理树内；
     * 缺失时越权管理员可以改写他人客户在 MT4 侧的交易组，直接影响其成交与佣金归属。
     *
     * @var AdminDataScopeService
     */
    private $dataScope;

    public function __construct(Mt4ManagerService $mt4Manager, AdminDataScopeService $dataScope)
    {
        $this->mt4Manager = $mt4Manager;
        $this->dataScope = $dataScope;
    }

    /**
     * @return array{code:int,data:array<string,mixed>}
     */
    public function approve(Admin $admin, int $userId, string $ip): array
    {
        return $this->withUserLock($userId, function () use ($admin, $userId, $ip): array {
            try {
                $preflight = DB::transaction(function () use ($admin, $userId): array {
                    return $this->lockedPendingSnapshot($admin, $userId);
                });
            } catch (\Throwable $exception) {
                return $this->result(ResponseCode::SERVER_ERROR);
            }

            if ($preflight['code'] !== ResponseCode::SUCCESS) {
                return $preflight;
            }

            $snapshot = $preflight['data'];
            try {
                $mt4Response = $this->mt4Manager->changeGroup($userId, $snapshot['group_name']);
            } catch (\Throwable $exception) {
                return $this->result(ResponseCode::THIRD_PARTY_ERROR);
            }

            if (!is_array($mt4Response)
                || (string) ($mt4Response['status'] ?? '') !== 'ok'
                || (string) ($mt4Response['err'] ?? '') !== '0') {
                return $this->result(ResponseCode::THIRD_PARTY_ERROR);
            }

            try {
                return DB::transaction(function () use ($admin, $userId, $ip, $snapshot): array {
                    $locked = $this->lockedPendingSnapshot($admin, $userId, (int) $snapshot['application_id']);
                    if ($locked['code'] !== ResponseCode::SUCCESS) {
                        return $locked;
                    }

                    $current = $locked['data'];
                    if ((int) $current['group_id'] !== (int) $snapshot['group_id']
                        || (string) $current['group_name'] !== (string) $snapshot['group_name']) {
                        return $this->result(ResponseCode::DATA_NOT_FOUND);
                    }

                    /** @var UserInfo $user */
                    $user = $current['user'];
                    /** @var TransApplyLog $application */
                    $application = $current['application'];

                    $user->group_id = (int) $snapshot['group_id'];
                    $user->mt4_group = (string) $snapshot['group_name'];
                    $user->updated_by = (int) $admin->id;
                    $user->save();

                    $application->status = 1;
                    $application->updated_by = (string) $admin->username;
                    $application->save();

                    OperationLog::create([
                        'admin_id' => (int) $admin->id,
                        'admin_name' => (string) $admin->username,
                        'target_user_id' => $userId,
                        'order_no' => 'legacy_customer_group_approval:' . $userId,
                        'content' => 'Approved customer group change to [' . $snapshot['group_name'] . '].',
                        'ip' => $ip,
                        'action_type' => 0,
                    ]);

                    return $this->result(ResponseCode::UPDATED);
                });
            } catch (\Throwable $exception) {
                return $this->result(ResponseCode::SERVER_ERROR);
            }
        });
    }

    /**
     * @return array{code:int,data:array<string,mixed>}
     */
    public function reject(Admin $admin, int $userId, string $reason, string $ip): array
    {
        return $this->withUserLock($userId, function () use ($admin, $userId, $reason, $ip): array {
            try {
                return DB::transaction(function () use ($admin, $userId, $reason, $ip): array {
                    $locked = $this->lockedPendingSnapshot($admin, $userId);
                    if ($locked['code'] !== ResponseCode::SUCCESS) {
                        return $locked;
                    }

                    /** @var TransApplyLog $application */
                    $application = $locked['data']['application'];
                    $application->status = -1;
                    $application->reject_reason = $reason;
                    $application->updated_by = (string) $admin->username;
                    $application->save();

                    OperationLog::create([
                        'admin_id' => (int) $admin->id,
                        'admin_name' => (string) $admin->username,
                        'target_user_id' => $userId,
                        'order_no' => 'legacy_customer_group_rejection:' . $userId,
                        'content' => 'Rejected customer group change to ['
                            . $locked['data']['group_name'] . ']: ' . $reason,
                        'ip' => $ip,
                        'action_type' => 0,
                    ]);

                    return $this->result(ResponseCode::UPDATED);
                });
            } catch (\Throwable $exception) {
                return $this->result(ResponseCode::SERVER_ERROR);
            }
        });
    }

    /**
     * Lock and validate both local records. The MT4 call is deliberately made
     * after this transaction has committed and released its row locks.
     *
     * @return array{code:int,data:array<string,mixed>}
     */
    private function lockedPendingSnapshot(Admin $admin, int $userId, ?int $applicationId = null): array
    {
        $user = UserInfo::query()->where('user_id', $userId)->lockForUpdate()->first();

        if (!$user) {
            return $this->result(ResponseCode::DATA_NOT_FOUND);
        }
        if ((int) $user->account_type !== 2) {
            return $this->result(ResponseCode::USER_NOT_FOUND);
        }
        if (!$this->dataScope->canAccessUser($admin, $userId, 'user')) {
            return $this->result(ResponseCode::PERMISSION_DENIED);
        }

        $applicationQuery = TransApplyLog::query()
            ->where('user_id', $userId)
            ->where('status', 0);
        if ($applicationId !== null) {
            $applicationQuery->whereKey($applicationId);
        }
        $application = $applicationQuery->orderBy('id')->lockForUpdate()->first();

        if (!$application) {
            return $this->result(ResponseCode::DATA_NOT_FOUND);
        }

        $groupId = (int) $application->group_id;
        $groupName = trim((string) $application->group_name);
        if ($groupId <= 0 || $groupName === '') {
            return $this->result(ResponseCode::DATA_NOT_FOUND);
        }

        return $this->result(ResponseCode::SUCCESS, [
            'application_id' => (int) $application->id,
            'group_id' => $groupId,
            'group_name' => $groupName,
            'user' => $user,
            'application' => $application,
        ]);
    }

    /**
     * @param callable():array{code:int,data:array<string,mixed>} $callback
     * @return array{code:int,data:array<string,mixed>}
     */
    private function withUserLock(int $userId, callable $callback): array
    {
        $databaseLockName = 'customer_group_change_approval:' . $userId;
        $databaseLockAcquired = false;

        try {
            $lock = Cache::lock('admin_customer_group_change_approval:' . $userId, self::LOCK_TTL_SECONDS);
            if (!$lock->get()) {
                return $this->result(ResponseCode::RATE_LIMITED);
            }

            $databaseLock = DB::selectOne('SELECT GET_LOCK(?, 0) AS acquired', [$databaseLockName]);
            $databaseLockAcquired = (int) ($databaseLock->acquired ?? 0) === 1;
            if (!$databaseLockAcquired) {
                $lock->release();

                return $this->result(ResponseCode::RATE_LIMITED);
            }
        } catch (\Throwable $exception) {
            if (isset($lock)) {
                try {
                    $lock->release();
                } catch (\Throwable $releaseException) {
                    // Cache lock expiry remains the fail-safe.
                }
            }

            return $this->result(ResponseCode::SERVER_ERROR);
        }

        try {
            return $callback();
        } finally {
            if ($databaseLockAcquired) {
                try {
                    DB::selectOne('SELECT RELEASE_LOCK(?) AS released', [$databaseLockName]);
                } catch (\Throwable $exception) {
                    // The connection-scoped lock is released when the request connection closes.
                }
            }
            try {
                $lock->release();
            } catch (\Throwable $exception) {
                // The business result is already final; lock expiry remains the fail-safe.
            }
        }
    }

    /**
     * @param array<string,mixed> $data
     * @return array{code:int,data:array<string,mixed>}
     */
    private function result(int $code, array $data = []): array
    {
        return ['code' => $code, 'data' => $data];
    }
}
