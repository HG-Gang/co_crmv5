<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/06
 * Time: 01:16
 */

declare(strict_types=1);

namespace App\Services\Registration;

use App\Contracts\UserMt4ProvisioningGateway;
use App\Models\UserInfo;
use App\Models\UserLogin;
use App\Models\UserMt4ProvisioningOutbox;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

/**
 * MT4 用户开通处理器。
 *
 * 文件功能：
 * - 从出箱表中取出待处理的开通任务，调用 MT4 网关创建或对账用户账户。
 * - 成功后更新本地 UserLogin / UserInfo 的 MT4 同步状态。
 * - 支持自动重试（可重试错误时回退等待）和手动人工对账（对账次数耗尽时）。
 *
 * 适用场景：
 * - 用户注册后，出箱扫描器调用 process() 自动开通 MT4 账户。
 * - 开通失败且设备不可重试时进入 unknown 状态，后续以 reconcile 模式查询 MT4 实际状态。
 * - 对账失败达上限后标记为 manual_reconcile_required，需管理员介入。
 *
 * 入参例子：
 * - outboxId: 出箱记录 ID。
 *
 * 返回值：
 * - 'processed'：开通成功或本地状态已一致。
 * - 'retryable'：可稍后重试。
 * - 'unknown'：结果不确定，进入对账模式。
 * - 'rejected'：明确被拒绝。
 * - 'manual_reconcile_required'：需人工处理。
 * - 'missing'：出箱记录不存在。
 *
 * 异常或失败场景：
 * - 网关调用异常：捕获后按 transport_exception 处理为 unknown。
 * - payload 解密失败：标记为 rejected。
 * - 本地用户数据缺失：标记为 rejected。
 * - payload 过期（超过 TTL）：标记为 manual_reconcile_required。
 */
final class UserMt4ProvisioningProcessor
{
    /**
     * 出箱认领锁的陈旧阈值（分钟），固定 5。processing 且 locked_at 超过该值视为执行方崩溃可重新认领；
     * 过短会与正常开户耗时冲突造成并发重复开户，过长则拖延故障恢复。
     */
    private const STALE_CLAIM_MINUTES = 5;

    /**
     * 开户模式的最大尝试次数，固定 3。开户是真实创建 MT4 账号的操作，
     * 达到上限后停止自动重试（宁可转人工/对账），避免反复下发开户指令产生多个账号。
     */
    private const MAX_PROVISION_ATTEMPTS = 3;

    /**
     * 对账模式的最大尝试次数，固定 3。对账只读不产生副作用，但与开户共享人工介入上限：
     * 超过 3 次仍未确认 MT4 实际状态即标记 manual_reconcile_required，转管理员核对。
     */
    private const MAX_RECONCILIATION_ATTEMPTS = 3;

    /**
     * 出箱载荷（含加密明文密码）的有效期（秒），固定 86400 = 24 小时。
     * 超期载荷不再执行开户（manual_reconcile_required），限制明文密码的可用窗口；
     * 放宽该值等于延长敏感载荷暴露期。
     */
    private const PAYLOAD_TTL_SECONDS = 86400;

    /**
     * MT4 开户网关：创建与对账 MT4 账户的唯一执行通道。
     * 处理器完全依赖它返回的统一结果分类推进状态机；网关缺失或返回语义错误会让 unknown 误判为成功，造成无账号可用的用户。
     *
     * @var UserMt4ProvisioningGateway
     */
    private $gateway;

    /**
     * 构造 MT4 用户开通处理器。
     *
     * @param UserMt4ProvisioningGateway $gateway 负责创建与对账 MT4 账户的网关实现。
     */
    public function __construct(UserMt4ProvisioningGateway $gateway)
    {
        $this->gateway = $gateway;
    }

    /**
     * 处理一条出箱任务：抢占 → 调用网关 → 按结果落库，返回统一状态码。
     *
     * @param int $outboxId 出箱记录 ID。
     * @return string 'processed' / 'retryable' / 'unknown' / 'rejected' / 'manual_reconcile_required' / 'missing'。
     */
    public function process(int $outboxId): string
    {
        // 事务内抢占任务并返回 claim 数据；抢占不到（已被处理/未到期）时返回当前状态。
        $claim = $this->claim($outboxId);
        if ($claim === null) {
            return $this->currentStatus($outboxId);
        }

        // 网关调用放在事务外，避免远端超时长期占用数据库行锁。
        try {
            $result = $this->callGateway($claim);
        } catch (Throwable $exception) {
            $this->logError('MT4 provisioning processor gateway exception.', [
                'exception_class' => get_class($exception),
                'outbox_id' => $outboxId,
                'mode' => $claim['mode'],
                'attempt' => (int) $claim['attempt'],
            ]);
            // 意外异常按未知处理：远端状态不可知，宁可走对账也不重复开户。
            $result = UserMt4ProvisioningResult::unknown('transport_exception');
        }

        if ($result->status() === 'processed') {
            try {
                $finalized = $this->finalizeProcessed($outboxId, $claim['attempt'], $result);
            } catch (Throwable $exception) {
                // 远端已成功但本地提交失败：进入对账模式复核，防止重复开户。
                $this->logError('MT4 provisioning processor finalize exception.', [
                    'exception_class' => get_class($exception),
                    'outbox_id' => $outboxId,
                    'mode' => $claim['mode'],
                    'attempt' => (int) $claim['attempt'],
                ]);
                return $this->recordLocalCommitFailure($outboxId, $claim['attempt']);
            }

            return $finalized ? 'processed' : $this->currentStatus($outboxId);
        }

        // 对账模式只接受对账语义的结果；可重试与未知均落回 unknown，拒绝/人工则转人工处理。
        if ($claim['mode'] === 'reconcile') {
            if ($result->status() === 'retryable_not_sent') {
                return $this->recordReconciliationRetryable($outboxId, $claim['attempt'], $result->errorCode());
            }
            if ($result->status() === 'unknown') {
                return $this->recordReconciliationUnknown($outboxId, $claim['attempt'], $result->errorCode());
            }
            if (in_array($result->status(), ['rejected', 'manual_reconcile_required'], true)) {
                return $this->recordManualReconciliation($outboxId, $claim['attempt'], $result->errorCode());
            }

            throw new RuntimeException('Unsupported MT4 reconciliation result.');
        }

        // 开户模式按结果分路：可重试退避、未知转对账、拒绝终态、人工介入。
        if ($result->status() === 'retryable_not_sent') {
            return $this->recordRetryable($outboxId, $claim['attempt'], $result->errorCode());
        }
        if ($result->status() === 'unknown') {
            return $this->recordUnknown($outboxId, $claim['attempt'], $result->errorCode());
        }
        if ($result->status() === 'rejected') {
            return $this->recordRejected($outboxId, $claim['attempt'], $result->errorCode());
        }
        if ($result->status() === 'manual_reconcile_required') {
            return $this->recordManualReconciliation($outboxId, $claim['attempt'], $result->errorCode());
        }

        throw new RuntimeException('Unsupported MT4 provisioning result.');
    }

    /**
     * 重试退避时间表（秒）：第 1 次等 60 秒，第 2 次等 300 秒；由出箱扫描器调用。
     *
     * @return array<int, int> 各次重试的等待秒数。
     */
    public function retrySchedule(): array
    {
        return [60, 300];
    }

    /**
     * 按 claim 模式调用对应网关方法。
     *
     * @param array<string, mixed> $claim claim() 返回的任务快照。
     * @return UserMt4ProvisioningResult 网关分类结果。
     */
    private function callGateway(array $claim): UserMt4ProvisioningResult
    {
        if ($claim['mode'] === 'reconcile') {
            return $this->gateway->reconcile($claim['user_id'], $claim['expected_group']);
        }

        return $this->gateway->provision($claim['payload']);
    }

    /**
     * 抢占出箱任务：事务内行锁读取，校验状态与到期时间后返回任务快照；不可处理时返回 null。
     *
     * @param int $outboxId 出箱记录 ID。
     * @return array{mode: string, user_id: int, expected_group: string, payload: array<string, mixed>, attempt: int}|null
     */
    private function claim(int $outboxId): ?array
    {
        return DB::transaction(function () use ($outboxId): ?array {
            $outbox = UserMt4ProvisioningOutbox::whereKey($outboxId)->lockForUpdate()->first();
            if (!$outbox) {
                return null;
            }
            // processing 且锁超时（STALE_CLAIM_MINUTES=5 分钟）说明上一执行者异常退出，回收该 claim 并转 unknown。
            if ($outbox->status === 'processing') {
                if ($outbox->locked_at === null
                    || $outbox->locked_at->lte(now()->subMinutes(self::STALE_CLAIM_MINUTES))) {
                    $this->markStaleClaimUnknown($outbox);
                }

                return null;
            }
            // 终态任务不再处理；顺带清理遗留的敏感密文，缩短敏感数据留存时间。
            if (in_array($outbox->status, ['processed', 'rejected', 'manual_reconcile_required'], true)) {
                if ($outbox->payload_ciphertext !== null || $outbox->payload_hash !== null) {
                    $this->clearPayload($outbox);
                    $outbox->saveOrFail();
                }

                return null;
            }
            // available_at 在未来表示尚未到重试时间，本次不处理。
            if ($outbox->available_at !== null && $outbox->available_at->isFuture()) {
                return null;
            }

            // unknown 状态进入对账模式；对账次数达上限直接转人工，不再发起远端请求。
            $mode = $outbox->status === 'unknown' ? 'reconcile' : 'provision';
            if ($mode === 'reconcile'
                && (int) $outbox->reconciliation_attempts >= self::MAX_RECONCILIATION_ATTEMPTS) {
                $outbox->status = 'manual_reconcile_required';
                $outbox->processed_at = now();
                $outbox->available_at = null;
                $outbox->locked_at = null;
                $outbox->last_error_code = 'reconciliation_attempts_exhausted';
                $this->clearPayload($outbox);
                $outbox->saveOrFail();

                return null;
            }
            // 开户负载超过 TTL（86400 秒）不再执行远端开户，转人工，防止用过期密码建户。
            if ($mode === 'provision' && $this->payloadExpired($outbox)) {
                $this->markManual($outbox, 'provision_payload_expired');

                return null;
            }

            // 开户模式需要解密负载并做身份/密码校验；任一失败即拒绝该任务（fail-closed）。
            $payload = [];
            if ($mode === 'provision') {
                try {
                    $payload = UserMt4ProvisioningPayload::decrypt(
                        (string) $outbox->payload_ciphertext,
                        (string) $outbox->payload_hash
                    );
                } catch (Throwable $exception) {
                    $this->logError('MT4 provisioning processor payload decrypt exception.', [
                        'exception_class' => get_class($exception),
                        'outbox_id' => (int) $outbox->id,
                        'mode' => 'provision',
                        'attempt' => (int) $outbox->attempts + 1,
                    ]);
                    $this->rejectOutbox($outbox, 'payload_decrypt_failed');

                    return null;
                }
                // 负载中的 user_id 必须与出箱记录一致，防止密文被替换后给错误用户开户。
                if ((int) ($payload['user_id'] ?? 0) !== (int) $outbox->user_id) {
                    $this->rejectOutbox($outbox, 'payload_identity_mismatch');

                    return null;
                }
                // 开户必须带密码；缺失即拒绝，不向 MT4 发送无密码开户请求。
                if (!isset($payload['password']) || !is_string($payload['password']) || $payload['password'] === '') {
                    $this->rejectOutbox($outbox, 'payload_password_missing');

                    return null;
                }
            }

            // 本地用户数据必须存在且归属一致；否则任务数据已损坏，直接拒绝。
            $login = UserLogin::whereKey($outbox->user_login_id)->lockForUpdate()->first();
            $info = UserInfo::whereKey($outbox->user_info_id)->lockForUpdate()->first();
            if (!$login || !$info || (int) $login->user_id !== (int) $outbox->user_id
                || (int) $info->user_id !== (int) $outbox->user_id) {
                $this->rejectOutbox($outbox, 'local_user_missing');

                return null;
            }
            // 幂等短路：本地已同步且账户已启用，说明任务已完成，直接置 processed 并清理密文。
            if ((int) $login->is_enabled === 1
                && (int) $info->is_mt4_synced === 1
                && (int) $info->is_mt4_enabled === 1
                && (int) $info->mt4_code === (int) $outbox->user_id) {
                $outbox->status = 'processed';
                $outbox->processed_at = now();
                $outbox->available_at = null;
                $outbox->locked_at = null;
                $outbox->last_error_code = null;
                $this->clearPayload($outbox);
                $outbox->saveOrFail();

                return null;
            }

            // 抢占成功：置 processing、累加尝试次数并记录锁时间；对账模式不再需要密文。
            $outbox->status = 'processing';
            $outbox->attempts = (int) $outbox->attempts + 1;
            $outbox->available_at = null;
            $outbox->locked_at = now();
            $outbox->processed_at = null;
            $outbox->last_error_code = null;
            if ($mode === 'reconcile') {
                $this->clearPayload($outbox);
            }
            $outbox->saveOrFail();

            return [
                'mode' => $mode,
                'user_id' => (int) $outbox->user_id,
                'expected_group' => trim((string) ($info->mt4_group ?: $info->original_group)),
                'payload' => $payload,
                'attempt' => (int) $outbox->attempts,
            ];
        }, 3);
    }

    /**
     * 提交开户成功结果：二次校验 claim 归属后更新本地用户状态并置出箱为 processed。
     *
     * @param int $outboxId 出箱记录 ID。
     * @param int $claimAttempt claim 时记录的尝试次数。
     * @param UserMt4ProvisioningResult $result 网关已确认的成功结果。
     * @return bool 提交成功返回 true；claim 已失效（被并发者处理）返回 false。
     */
    private function finalizeProcessed(
        int $outboxId,
        int $claimAttempt,
        UserMt4ProvisioningResult $result
    ): bool {
        return DB::transaction(function () use ($outboxId, $claimAttempt, $result): bool {
            $outbox = UserMt4ProvisioningOutbox::whereKey($outboxId)->lockForUpdate()->first();
            // 提交前必须确认 claim 仍属于本次执行，防止并发任务覆盖他人写入。
            if (!$outbox || !$this->ownsClaim($outbox, $claimAttempt)) {
                return false;
            }

            $login = UserLogin::whereKey($outbox->user_login_id)->lockForUpdate()->firstOrFail();
            $info = UserInfo::whereKey($outbox->user_info_id)->lockForUpdate()->firstOrFail();
            // 本地用户归属与出箱记录不一致说明数据损坏，抛出后由上层转入对账。
            if ((int) $login->user_id !== (int) $outbox->user_id
                || (int) $info->user_id !== (int) $outbox->user_id) {
                throw new RuntimeException('MT4 provisioning local identity mismatch.');
            }

            // 开户成功后本地标记启用与已同步，MT4 登录号与用户 ID 对齐。
            $login->is_enabled = 1;
            $login->saveOrFail();
            $info->is_mt4_synced = 1;
            $info->is_mt4_enabled = 1;
            $info->mt4_code = (int) $outbox->user_id;
            $info->saveOrFail();

            // 出箱置终态并保存供应商票据号，清理敏感密文。
            $outbox->status = 'processed';
            $outbox->provider_reference = $result->providerReference();
            $outbox->processed_at = now();
            $outbox->available_at = null;
            $outbox->locked_at = null;
            $outbox->last_error_code = null;
            $this->clearPayload($outbox);
            $outbox->saveOrFail();

            return true;
        }, 3);
    }

    /**
     * 记录开户可重试：退避重试；负载过期或尝试次数达上限时转人工。
     *
     * @param int $outboxId 出箱记录 ID。
     * @param int $claimAttempt claim 时记录的尝试次数。
     * @param string|null $errorCode 网关错误码。
     * @return string 更新后的状态码。
     */
    private function recordRetryable(int $outboxId, int $claimAttempt, string $errorCode = null): string
    {
        return DB::transaction(function () use ($outboxId, $claimAttempt, $errorCode): string {
            $outbox = UserMt4ProvisioningOutbox::whereKey($outboxId)->lockForUpdate()->first();
            if (!$outbox) {
                return 'missing';
            }
            if (!$this->ownsClaim($outbox, $claimAttempt)) {
                return (string) $outbox->status;
            }

            if ($this->payloadExpired($outbox)) {
                return $this->markManual($outbox, 'provision_payload_expired');
            }
            if ((int) $outbox->attempts >= self::MAX_PROVISION_ATTEMPTS) {
                return $this->markManual($outbox, 'provision_retry_attempts_exhausted');
            }

            $outbox->status = 'retryable';
            $outbox->available_at = now()->addSeconds(60 * max(1, min((int) $outbox->attempts, 10)));
            $outbox->locked_at = null;
            $outbox->processed_at = null;
            $outbox->last_error_code = $errorCode ?: 'connection_failed';
            $outbox->saveOrFail();

            return 'retryable';
        }, 3);
    }

    /**
     * 记录开户未知：结果不确定，转 unknown 进入对账模式；密文不再保留（对账不需要）。
     *
     * @param int $outboxId 出箱记录 ID。
     * @param int $claimAttempt claim 时记录的尝试次数。
     * @param string|null $errorCode 网关错误码。
     * @return string 更新后的状态码。
     */
    private function recordUnknown(int $outboxId, int $claimAttempt, string $errorCode = null): string
    {
        return DB::transaction(function () use ($outboxId, $claimAttempt, $errorCode): string {
            $outbox = UserMt4ProvisioningOutbox::whereKey($outboxId)->lockForUpdate()->first();
            if (!$outbox) {
                return 'missing';
            }
            if (!$this->ownsClaim($outbox, $claimAttempt)) {
                return (string) $outbox->status;
            }

            $outbox->status = 'unknown';
            $outbox->available_at = now()->addSeconds(60 * max(1, min((int) $outbox->attempts, 10)));
            $outbox->locked_at = null;
            $outbox->processed_at = null;
            $outbox->last_error_code = $errorCode ?: 'unknown_result';
            $this->clearPayload($outbox);
            $outbox->saveOrFail();

            return 'unknown';
        }, 3);
    }

    /**
     * 记录开户明确拒绝：置终态并清理密文，不再重试。
     *
     * @param int $outboxId 出箱记录 ID。
     * @param int $claimAttempt claim 时记录的尝试次数。
     * @param string|null $errorCode 网关错误码。
     * @return string 更新后的状态码。
     */
    private function recordRejected(int $outboxId, int $claimAttempt, string $errorCode = null): string
    {
        return DB::transaction(function () use ($outboxId, $claimAttempt, $errorCode): string {
            $outbox = UserMt4ProvisioningOutbox::whereKey($outboxId)->lockForUpdate()->first();
            if (!$outbox) {
                return 'missing';
            }
            if (!$this->ownsClaim($outbox, $claimAttempt)) {
                return (string) $outbox->status;
            }

            $outbox->status = 'rejected';
            $outbox->processed_at = now();
            $outbox->available_at = null;
            $outbox->locked_at = null;
            $outbox->last_error_code = $errorCode ?: 'provider_rejected';
            $this->clearPayload($outbox);
            $outbox->saveOrFail();

            return 'rejected';
        }, 3);
    }

    /**
     * 记录对账可重试：对账阶段连接失败累加对账次数并短暂退避，达到上限后转人工。
     *
     * @param int $outboxId 出箱记录 ID。
     * @param int $claimAttempt claim 时记录的尝试次数。
     * @param string|null $errorCode 网关错误码。
     * @return string 更新后的状态码（unknown 或 manual_reconcile_required）。
     */
    private function recordReconciliationRetryable(
        int $outboxId,
        int $claimAttempt,
        string $errorCode = null
    ): string {
        return DB::transaction(function () use ($outboxId, $claimAttempt, $errorCode): string {
            $outbox = UserMt4ProvisioningOutbox::whereKey($outboxId)->lockForUpdate()->first();
            if (!$outbox) {
                return 'missing';
            }
            if (!$this->ownsClaim($outbox, $claimAttempt)) {
                return (string) $outbox->status;
            }

            $outbox->reconciliation_attempts = (int) $outbox->reconciliation_attempts + 1;
            $outbox->locked_at = null;
            $outbox->processed_at = null;
            $outbox->last_error_code = $errorCode ?: 'connection_failed';
            $this->clearPayload($outbox);
            if ((int) $outbox->reconciliation_attempts >= self::MAX_RECONCILIATION_ATTEMPTS) {
                $outbox->status = 'manual_reconcile_required';
                $outbox->available_at = null;
                $outbox->processed_at = now();
            } else {
                $outbox->status = 'unknown';
                $outbox->available_at = now()->addSeconds(
                    60 * max(1, min((int) $outbox->reconciliation_attempts, 10))
                );
            }
            $outbox->saveOrFail();

            return (string) $outbox->status;
        }, 3);
    }

    /**
     * 记录对账未知：累加对账次数并按退避公式排期；次数达上限转人工复核。
     *
     * @param int $outboxId 出箱记录 ID。
     * @param int $claimAttempt claim 时记录的尝试次数。
     * @param string|null $errorCode 网关错误码。
     * @return string 更新后的状态码（unknown 或 manual_reconcile_required）。
     */
    private function recordReconciliationUnknown(
        int $outboxId,
        int $claimAttempt,
        string $errorCode = null
    ): string {
        return DB::transaction(function () use ($outboxId, $claimAttempt, $errorCode): string {
            $outbox = UserMt4ProvisioningOutbox::whereKey($outboxId)->lockForUpdate()->first();
            if (!$outbox) {
                return 'missing';
            }
            if (!$this->ownsClaim($outbox, $claimAttempt)) {
                return (string) $outbox->status;
            }

            $outbox->reconciliation_attempts = (int) $outbox->reconciliation_attempts + 1;
            $outbox->available_at = null;
            $outbox->locked_at = null;
            $outbox->processed_at = null;
            $outbox->last_error_code = $errorCode ?: 'reconciliation_unknown';
            $this->clearPayload($outbox);
            if ((int) $outbox->reconciliation_attempts >= self::MAX_RECONCILIATION_ATTEMPTS) {
                $outbox->status = 'manual_reconcile_required';
                $outbox->processed_at = now();
            } else {
                $outbox->status = 'unknown';
                $outbox->available_at = now()->addSeconds(
                    60 * max(1, min((int) $outbox->reconciliation_attempts, 10))
                );
            }
            $outbox->saveOrFail();

            return (string) $outbox->status;
        }, 3);
    }

    /**
     * 记录需人工对账：对账或拒绝结果转 manual_reconcile_required 终态。
     *
     * @param int $outboxId 出箱记录 ID。
     * @param int $claimAttempt claim 时记录的尝试次数。
     * @param string|null $errorCode 网关错误码。
     * @return string 更新后的状态码（manual_reconcile_required）。
     */
    private function recordManualReconciliation(
        int $outboxId,
        int $claimAttempt,
        string $errorCode = null
    ): string {
        return DB::transaction(function () use ($outboxId, $claimAttempt, $errorCode): string {
            $outbox = UserMt4ProvisioningOutbox::whereKey($outboxId)->lockForUpdate()->first();
            if (!$outbox) {
                return 'missing';
            }
            if (!$this->ownsClaim($outbox, $claimAttempt)) {
                return (string) $outbox->status;
            }

            return $this->markManual($outbox, $errorCode ?: 'manual_reconcile_required');
        }, 3);
    }

    /**
     * 记录本地提交失败：远端已开户但本地落库失败，进入对账模式复核，防止重复开户。
     *
     * @param int $outboxId 出箱记录 ID。
     * @param int $claimAttempt claim 时记录的尝试次数。
     * @return string 更新后的状态码（unknown 或 manual_reconcile_required）。
     */
    private function recordLocalCommitFailure(int $outboxId, int $claimAttempt): string
    {
        return DB::transaction(function () use ($outboxId, $claimAttempt): string {
            $outbox = UserMt4ProvisioningOutbox::whereKey($outboxId)->lockForUpdate()->first();
            if (!$outbox) {
                return 'missing';
            }
            if (!$this->ownsClaim($outbox, $claimAttempt)) {
                return (string) $outbox->status;
            }

            $outbox->reconciliation_attempts = (int) $outbox->reconciliation_attempts + 1;
            $outbox->processed_at = null;
            $outbox->available_at = null;
            $outbox->locked_at = null;
            $outbox->last_error_code = 'local_commit_after_external_success_failed';
            $this->clearPayload($outbox);
            if ((int) $outbox->reconciliation_attempts >= self::MAX_RECONCILIATION_ATTEMPTS) {
                $outbox->status = 'manual_reconcile_required';
                $outbox->processed_at = now();
            } else {
                $outbox->status = 'unknown';
                $outbox->available_at = now()->addSeconds(
                    60 * max(1, min((int) $outbox->reconciliation_attempts, 10))
                );
            }
            $outbox->saveOrFail();

            return (string) $outbox->status;
        }, 3);
    }

    /**
     * 回收超时 claim：上一执行者异常退出（锁超过 5 分钟未释放），任务转 unknown 重新排期。
     *
     * @param UserMt4ProvisioningOutbox $outbox 处于 processing 状态且锁超时的出箱记录。
     */
    private function markStaleClaimUnknown(UserMt4ProvisioningOutbox $outbox): void
    {
        $outbox->status = 'unknown';
        $outbox->available_at = now()->addSeconds(60);
        $outbox->locked_at = null;
        $outbox->processed_at = null;
        $outbox->last_error_code = 'stale_processing_claim';
        $this->clearPayload($outbox);
        $outbox->saveOrFail();
    }

    /**
     * 拒绝出箱任务：置 rejected 终态并清理密文；用于负载损坏、身份不符等不可重试场景。
     *
     * @param UserMt4ProvisioningOutbox $outbox 待拒绝的出箱记录。
     * @param string $errorCode 拒绝原因错误码。
     */
    private function rejectOutbox(UserMt4ProvisioningOutbox $outbox, string $errorCode): void
    {
        $outbox->status = 'rejected';
        $outbox->processed_at = now();
        $outbox->available_at = null;
        $outbox->locked_at = null;
        $outbox->last_error_code = $errorCode;
        $this->clearPayload($outbox);
        $outbox->saveOrFail();
    }

    /**
     * 标记需人工处理并清理密文；用于尝试次数耗尽、负载过期等无法自动恢复的场景。
     *
     * @param UserMt4ProvisioningOutbox $outbox 待标记的出箱记录。
     * @param string $errorCode 人工介入原因错误码。
     * @return string 恒为 manual_reconcile_required。
     */
    private function markManual(UserMt4ProvisioningOutbox $outbox, string $errorCode): string
    {
        $outbox->status = 'manual_reconcile_required';
        $outbox->processed_at = now();
        $outbox->available_at = null;
        $outbox->locked_at = null;
        $outbox->last_error_code = $errorCode;
        $this->clearPayload($outbox);
        $outbox->saveOrFail();

        return 'manual_reconcile_required';
    }

    /**
     * 清理出箱记录的敏感密文与哈希，缩短敏感数据留存时间。
     *
     * @param UserMt4ProvisioningOutbox $outbox 待清理的出箱记录（调用方负责保存）。
     */
    private function clearPayload(UserMt4ProvisioningOutbox $outbox): void
    {
        $outbox->payload_ciphertext = null;
        $outbox->payload_hash = null;
    }

    /**
     * 判断开户负载是否超过有效期（PAYLOAD_TTL_SECONDS=86400 秒）。
     *
     * @param UserMt4ProvisioningOutbox $outbox 出箱记录。
     * @return bool 超期或时间戳无法证明有效时返回 true。
     */
    private function payloadExpired(UserMt4ProvisioningOutbox $outbox): bool
    {
        $createdAt = $outbox->getRawOriginal('created_at');

        $isIntegerTimestamp = is_int($createdAt)
            || (is_string($createdAt) && preg_match('/^\d+$/D', $createdAt) === 1);
        if (!$isIntegerTimestamp) {
            return true;
        }

        $createdAtTimestamp = (int) $createdAt;
        $now = time();

        return $createdAtTimestamp <= 0
            || $createdAtTimestamp > $now
            || $createdAtTimestamp <= $now - self::PAYLOAD_TTL_SECONDS;
    }

    /**
     * 校验 claim 归属：只有 status=processing 且 attempts 与 claim 一致的执行者才能提交结果。
     *
     * 防止并发扫描器之间互相覆盖对方的处理结果。
     *
     * @param UserMt4ProvisioningOutbox $outbox 出箱记录。
     * @param int $claimAttempt claim 时记录的尝试次数。
     * @return bool 归属本次执行返回 true。
     */
    private function ownsClaim(UserMt4ProvisioningOutbox $outbox, int $claimAttempt): bool
    {
        return $outbox->status === 'processing' && (int) $outbox->attempts === $claimAttempt;
    }

    /**
     * 读取出箱记录当前状态；记录不存在返回 'missing'。
     *
     * @param int $outboxId 出箱记录 ID。
     * @return string 当前状态码。
     */
    private function currentStatus(int $outboxId): string
    {
        $status = UserMt4ProvisioningOutbox::whereKey($outboxId)->value('status');

        return $status === null ? 'missing' : (string) $status;
    }

    /**
     * 记录错误日志；Laravel 容器不可用（脱离框架调用）时静默跳过，不抛异常。
     *
     * @param string $message 日志消息。
     * @param array<string, mixed> $context 结构化上下文（outbox_id / mode / attempt 等，不含敏感值）。
     */
    private function logError(string $message, array $context): void
    {
        if (!function_exists('app')) {
            return;
        }

        $application = app();
        if (!is_object($application)
            || !method_exists($application, 'bound')
            || !$application->bound('log')) {
            return;
        }

        Log::error($message, $context);
    }
}
