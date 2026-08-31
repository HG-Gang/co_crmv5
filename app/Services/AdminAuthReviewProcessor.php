<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/07
 * Time: 19:13
 */

declare(strict_types=1);

namespace App\Services;

use App\Models\AdminAuthReviewOutbox;
use App\Models\OperationLog;
use App\Models\UserAuth;
use App\Models\UserInfo;
use App\Support\AuthReviewTransition;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

/**
 * 管理员实名审核处理器。
 *
 * 文件功能：
 * - submit()：把审核决定（身份证/银行卡组件决定）经 AuthReviewTransition 规范化后加密写入
 *   admin_auth_review_outboxes 出箱表，返回审核受理结果。
 * - process()：消费一条审核意图，更新 user_auths/user_infos 的审核状态，并经 Mt4ManagerService
 *   把审核结果交付 MT4；交付失败进入可重试状态并记录 last_error_code，避免本地与 MT4 状态漂移。
 * - 明确不负责：审核决定本身的字段映射规则（AuthReviewTransition）。
 */
class AdminAuthReviewProcessor
{
    /**
     * outbox 认领锁的陈旧阈值（分钟），固定 5。status=processing 且 locked_at 早于该阈值的记录
     * 视为执行方已崩溃，可被重新认领投递；过短会与正常审核耗时冲突造成并发重复审核，过长则延长故障恢复。
     *
     * @var int
     */
    private const STALE_CLAIM_MINUTES = 5;

    /**
     * MT4 网关管理器：审核动作在 MT4 侧真实生效的唯一通道（本地审核数据必须与远端一致）。
     * 本服务采用“本地落库成功后同步投递/处理 outbox”的持久化语义；MT4 不可用时结果按
     * retryable/失败关闭落库，绝不伪造审核成功，缺失时整个审核流程不可用。
     *
     * @var Mt4ManagerService
     */
    private $mt4Manager;

    public function __construct(Mt4ManagerService $mt4Manager)
    {
        $this->mt4Manager = $mt4Manager;
    }

    /**
     * Persist a local review or create and synchronously process an MT4 outbox intent.
     *
     * @param array<string, int|string> $decisions
     * @param array<string, int|string> $context
     * @return array<string, int|string|null>
     */
    public function submit(int $userId, array $decisions, array $context): array
    {
        try {
            $prepared = DB::transaction(function () use ($userId, $decisions, $context): array {
                $auth = UserAuth::where('user_id', $userId)->lockForUpdate()->first();
                if (!$auth) {
                    return ['status' => 'missing'];
                }

                // Serialize every review on user_auths before observing its active intent.
                $active = AdminAuthReviewOutbox::where('active_user_id', $userId)
                    ->lockForUpdate()
                    ->first();
                if ($active) {
                    return [
                        'status' => 'conflict',
                        'outbox_id' => (int) $active->id,
                    ];
                }

                $current = $this->authReviewCurrent($auth);
                try {
                    AuthReviewTransition::assertReviewableComponents($current, $decisions);
                    $transition = AuthReviewTransition::resolve($current, $decisions);
                } catch (InvalidArgumentException $exception) {
                    return ['status' => 'conflict'];
                }

                if (!$transition['bank_sync_required']) {
                    $this->persistTransition($auth, $userId, $transition, $decisions, $context);

                    return ['status' => 'processed'];
                }

                $payload = AdminAuthReviewPayload::encrypt([
                    'user_id' => $userId,
                    'decisions' => $decisions,
                    'status_label' => (string) ($context['status_label'] ?? 'component'),
                    'id_card_decision_label' => (string) ($context['id_card_decision_label'] ?? 'none'),
                    'bank_decision_label' => (string) ($context['bank_decision_label'] ?? 'none'),
                ]);
                $outbox = AdminAuthReviewOutbox::create([
                    'user_id' => $userId,
                    'active_user_id' => $userId,
                    'admin_id' => (int) $context['admin_id'],
                    'admin_name' => (string) $context['admin_name'],
                    'request_ip' => (string) ($context['request_ip'] ?? ''),
                    'status' => 'pending',
                    'attempts' => 0,
                    'payload_ciphertext' => $payload['ciphertext'],
                    'payload_hash' => $payload['hash'],
                    'auth_snapshot_hash' => AdminAuthReviewPayload::snapshotHash($current),
                    'available_at' => null,
                    'locked_at' => null,
                    'processed_at' => null,
                    'last_error_code' => null,
                ]);

                return [
                    'status' => 'pending',
                    'outbox_id' => (int) $outbox->id,
                ];
            }, 3);
        } catch (QueryException $exception) {
            if ($this->isDuplicateActiveReview($exception)) {
                return ['status' => 'conflict'];
            }

            throw $exception;
        }

        if ($prepared['status'] !== 'pending') {
            return $prepared;
        }

        return $this->process((int) $prepared['outbox_id']);
    }

    /**
     * Process one durable MT4-backed review intent.
     *
     * @return array<string, int|string|null>
     */
    public function process(int $outboxId): array
    {
        $claim = $this->claim($outboxId);
        if ($claim['status'] !== 'claimed') {
            return $claim;
        }

        try {
            $mt4Result = $this->mt4Manager->updateComment(
                (int) $claim['user_id'],
                (string) $claim['comment']
            );
            if (!is_array($mt4Result)) {
                $mt4Result = [
                    'status' => 'error',
                    'error_code' => 'unexpected_response',
                ];
            }
        } catch (Mt4SyncDisabledException $exception) {
            $mt4Result = [
                'status' => 'error',
                'error_code' => 'mt4_sync_disabled',
            ];
        } catch (InvalidArgumentException $exception) {
            $mt4Result = [
                'status' => 'error',
                'error_code' => 'invalid_mt4_comment',
            ];
        } catch (Throwable $exception) {
            $this->logError('Admin authentication review MT4 call failed.', [
                'outbox_id' => $outboxId,
                'user_id' => (int) $claim['user_id'],
                'attempt' => (int) $claim['attempt'],
                'exception_class' => get_class($exception),
            ]);
            $mt4Result = [
                'status' => 'error',
                'error_code' => 'transport_exception',
            ];
        }

        $classified = $this->classifyMt4Result($mt4Result);
        if ($classified['status'] === 'processed') {
            try {
                if ($this->finalizeProcessed($outboxId, (int) $claim['attempt'])) {
                    return [
                        'status' => 'processed',
                        'outbox_id' => $outboxId,
                        'error_code' => null,
                    ];
                }

                return $this->currentResult($outboxId);
            } catch (Throwable $exception) {
                $this->logError('Admin authentication review local finalization failed.', [
                    'outbox_id' => $outboxId,
                    'user_id' => (int) $claim['user_id'],
                    'attempt' => (int) $claim['attempt'],
                    'exception_class' => get_class($exception),
                ]);

                return $this->recordUnknown(
                    $outboxId,
                    (int) $claim['attempt'],
                    'local_commit_after_external_success_failed'
                );
            }
        }

        if ($classified['status'] === 'retryable') {
            return $this->recordRetryable(
                $outboxId,
                (int) $claim['attempt'],
                (string) $classified['error_code']
            );
        }
        if ($classified['status'] === 'unknown') {
            return $this->recordUnknown(
                $outboxId,
                (int) $claim['attempt'],
                (string) $classified['error_code']
            );
        }

        return $this->recordRejected(
            $outboxId,
            (int) $claim['attempt'],
            (string) $classified['error_code']
        );
    }

    /**
     * @return array<string, int|string|null>
     */
    private function claim(int $outboxId): array
    {
        $identity = AdminAuthReviewOutbox::whereKey($outboxId)->first();
        if (!$identity) {
            return ['status' => 'missing', 'outbox_id' => $outboxId];
        }
        $userId = (int) $identity->user_id;

        return DB::transaction(function () use ($outboxId, $userId): array {
            $auth = UserAuth::where('user_id', $userId)->lockForUpdate()->first();
            $outbox = AdminAuthReviewOutbox::whereKey($outboxId)->lockForUpdate()->first();
            if (!$outbox) {
                return ['status' => 'missing', 'outbox_id' => $outboxId];
            }
            if ((int) $outbox->user_id !== $userId) {
                return $this->markUnknown($outbox, 'outbox_user_changed');
            }

            if ($outbox->status === 'processing') {
                if ($outbox->locked_at === null
                    || $outbox->locked_at->lte(now()->subMinutes(self::STALE_CLAIM_MINUTES))) {
                    return $this->markUnknown($outbox, 'stale_processing_claim');
                }

                return $this->outboxResult($outbox);
            }
            if (in_array($outbox->status, ['processed', 'rejected', 'unknown'], true)) {
                return $this->outboxResult($outbox);
            }
            if (!in_array($outbox->status, ['pending', 'retryable'], true)) {
                return $this->rejectOutbox($outbox, 'invalid_outbox_status');
            }
            if ($outbox->available_at !== null && $outbox->available_at->isFuture()) {
                return $this->outboxResult($outbox);
            }

            try {
                $payload = AdminAuthReviewPayload::decrypt(
                    (string) $outbox->payload_ciphertext,
                    (string) $outbox->payload_hash
                );
            } catch (Throwable $exception) {
                return $this->rejectOutbox($outbox, 'payload_verification_failed');
            }

            $decisions = $payload['decisions'] ?? null;
            if ((int) ($payload['user_id'] ?? 0) !== (int) $outbox->user_id || !is_array($decisions)) {
                return $this->rejectOutbox($outbox, 'payload_identity_mismatch');
            }

            if (!$auth) {
                return $this->rejectOutbox($outbox, 'auth_record_missing');
            }

            $current = $this->authReviewCurrent($auth);
            if (!hash_equals(
                (string) $outbox->auth_snapshot_hash,
                AdminAuthReviewPayload::snapshotHash($current)
            )) {
                return $this->rejectOutbox($outbox, 'auth_snapshot_changed');
            }

            try {
                AuthReviewTransition::assertReviewableComponents($current, $decisions);
                $transition = AuthReviewTransition::resolve($current, $decisions);
            } catch (InvalidArgumentException $exception) {
                return $this->rejectOutbox($outbox, 'review_transition_invalid');
            }
            if (!$transition['bank_sync_required']) {
                return $this->rejectOutbox($outbox, 'bank_sync_not_required');
            }

            $outbox->status = 'processing';
            $outbox->attempts = (int) $outbox->attempts + 1;
            $outbox->available_at = null;
            $outbox->locked_at = now();
            $outbox->processed_at = null;
            $outbox->last_error_code = null;
            $outbox->saveOrFail();

            return [
                'status' => 'claimed',
                'outbox_id' => (int) $outbox->id,
                'user_id' => (int) $outbox->user_id,
                'attempt' => (int) $outbox->attempts,
                'comment' => trim((string) $transition['bank_sync_no'])
                    . '|'
                    . trim((string) $transition['bank_sync_name'])
                    . '|审核通过',
            ];
        }, 3);
    }

    private function finalizeProcessed(int $outboxId, int $claimAttempt): bool
    {
        $identity = AdminAuthReviewOutbox::whereKey($outboxId)->first();
        if (!$identity) {
            return false;
        }
        $userId = (int) $identity->user_id;

        return DB::transaction(function () use ($outboxId, $claimAttempt, $userId): bool {
            $auth = UserAuth::where('user_id', $userId)->lockForUpdate()->first();
            $outbox = AdminAuthReviewOutbox::whereKey($outboxId)->lockForUpdate()->first();
            if (!$outbox || !$this->ownsClaim($outbox, $claimAttempt)) {
                return false;
            }
            if ((int) $outbox->user_id !== $userId) {
                throw new RuntimeException('Authentication review outbox user changed after MT4 success.');
            }
            if (!$auth) {
                throw new RuntimeException('Authentication review record missing after MT4 success.');
            }

            $payload = AdminAuthReviewPayload::decrypt(
                (string) $outbox->payload_ciphertext,
                (string) $outbox->payload_hash
            );
            $decisions = $payload['decisions'] ?? null;
            if ((int) ($payload['user_id'] ?? 0) !== (int) $outbox->user_id || !is_array($decisions)) {
                throw new RuntimeException('Authentication review outbox payload identity mismatch.');
            }

            $current = $this->authReviewCurrent($auth);
            if (!hash_equals(
                (string) $outbox->auth_snapshot_hash,
                AdminAuthReviewPayload::snapshotHash($current)
            )) {
                throw new RuntimeException('Authentication review snapshot changed after MT4 success.');
            }

            AuthReviewTransition::assertReviewableComponents($current, $decisions);
            $transition = AuthReviewTransition::resolve($current, $decisions);
            if (!$transition['bank_sync_required']) {
                throw new RuntimeException('Authentication review no longer requires bank synchronization.');
            }

            $this->persistTransition($auth, (int) $outbox->user_id, $transition, $decisions, [
                'admin_id' => (int) $outbox->admin_id,
                'admin_name' => (string) $outbox->admin_name,
                'request_ip' => (string) $outbox->request_ip,
                'status_label' => (string) ($payload['status_label'] ?? 'component'),
                'id_card_decision_label' => (string) ($payload['id_card_decision_label'] ?? 'none'),
                'bank_decision_label' => (string) ($payload['bank_decision_label'] ?? 'none'),
                'outbox_id' => (int) $outbox->id,
            ]);

            $outbox->status = 'processed';
            $outbox->active_user_id = null;
            $outbox->available_at = null;
            $outbox->locked_at = null;
            $outbox->processed_at = now();
            $outbox->last_error_code = null;
            $this->clearSensitivePayload($outbox);
            $outbox->saveOrFail();

            return true;
        }, 3);
    }

    /**
     * @param array<string, mixed> $result
     * @return array{status: string, error_code: string|null}
     */
    private function classifyMt4Result(array $result): array
    {
        if (strtolower(trim((string) ($result['status'] ?? 'error'))) === 'ok') {
            return ['status' => 'processed', 'error_code' => null];
        }

        $errorCode = trim((string) ($result['error_code'] ?? 'provider_rejected'));
        if ($errorCode === '') {
            $errorCode = 'provider_rejected';
        }
        if (in_array($errorCode, ['connection_failed', 'mt4_sync_disabled'], true)) {
            return ['status' => 'retryable', 'error_code' => $errorCode];
        }
        if (in_array($errorCode, [
            'write_failed',
            'read_timeout',
            'malformed_response',
            'transport',
            'transport_exception',
            'unexpected_response',
        ], true)) {
            return ['status' => 'unknown', 'error_code' => $errorCode];
        }

        return ['status' => 'rejected', 'error_code' => $errorCode];
    }

    /**
     * @return array<string, int|string|null>
     */
    private function recordRetryable(int $outboxId, int $claimAttempt, string $errorCode): array
    {
        return DB::transaction(function () use ($outboxId, $claimAttempt, $errorCode): array {
            $outbox = AdminAuthReviewOutbox::whereKey($outboxId)->lockForUpdate()->first();
            if (!$outbox) {
                return ['status' => 'missing', 'outbox_id' => $outboxId];
            }
            if (!$this->ownsClaim($outbox, $claimAttempt)) {
                return $this->outboxResult($outbox);
            }

            $outbox->status = 'retryable';
            $outbox->available_at = now()->addSeconds(60 * max(1, min((int) $outbox->attempts, 15)));
            $outbox->locked_at = null;
            $outbox->processed_at = null;
            $outbox->last_error_code = $errorCode;
            $outbox->saveOrFail();

            return $this->outboxResult($outbox);
        }, 3);
    }

    /**
     * @return array<string, int|string|null>
     */
    private function recordUnknown(int $outboxId, int $claimAttempt, string $errorCode): array
    {
        return DB::transaction(function () use ($outboxId, $claimAttempt, $errorCode): array {
            $outbox = AdminAuthReviewOutbox::whereKey($outboxId)->lockForUpdate()->first();
            if (!$outbox) {
                return ['status' => 'missing', 'outbox_id' => $outboxId];
            }
            if (!$this->ownsClaim($outbox, $claimAttempt)) {
                return $this->outboxResult($outbox);
            }

            return $this->markUnknown($outbox, $errorCode);
        }, 3);
    }

    /**
     * @return array<string, int|string|null>
     */
    private function recordRejected(int $outboxId, int $claimAttempt, string $errorCode): array
    {
        return DB::transaction(function () use ($outboxId, $claimAttempt, $errorCode): array {
            $outbox = AdminAuthReviewOutbox::whereKey($outboxId)->lockForUpdate()->first();
            if (!$outbox) {
                return ['status' => 'missing', 'outbox_id' => $outboxId];
            }
            if (!$this->ownsClaim($outbox, $claimAttempt)) {
                return $this->outboxResult($outbox);
            }

            return $this->rejectOutbox($outbox, $errorCode);
        }, 3);
    }

    /**
     * @param object $auth
     * @param array<string, mixed> $transition
     * @param array<string, int|string> $decisions
     * @param array<string, int|string> $context
     */
    private function persistTransition(
        $auth,
        int $userId,
        array $transition,
        array $decisions,
        array $context
    ): void {
        $beforeIdCardStatus = (int) $auth->id_card_status;
        $beforeBankStatus = (int) $auth->bank_status;
        $auth->update($transition['auth_updates']);

        $userInfo = UserInfo::where('user_id', $userId)->lockForUpdate()->first();
        if (!$userInfo) {
            throw new RuntimeException('Authentication review user info record is missing.');
        }
        $userInfo->update([
            'auth_status' => $transition['user_auth_status'],
        ]);

        $orderNo = 'auth_review:' . $userId;
        if (isset($context['outbox_id'])) {
            $orderNo .= ':' . (int) $context['outbox_id'];
        }
        $content = sprintf(
            'Review auth user_id:%s; status:%s; id_card_decision:%s; bank_decision:%s; id_card_status:%s->%s; bank_status:%s->%s; auth_status:%s; id_card_reason:%s; bank_reason:%s',
            $userId,
            (string) ($context['status_label'] ?? 'component'),
            (string) ($context['id_card_decision_label'] ?? 'none'),
            (string) ($context['bank_decision_label'] ?? 'none'),
            $beforeIdCardStatus,
            $transition['final_id_card_status'],
            $beforeBankStatus,
            $transition['final_bank_status'],
            $transition['user_auth_status'],
            (string) ($decisions['id_card_reason'] ?? ''),
            (string) ($decisions['bank_reason'] ?? '')
        );
        OperationLog::create([
            'admin_id' => (int) $context['admin_id'],
            'admin_name' => (string) $context['admin_name'],
            'target_user_id' => $userId,
            'order_no' => $orderNo,
            'content' => Str::limit($content, 1000, ''),
            'ip' => (string) ($context['request_ip'] ?? ''),
            'action_type' => 0,
        ]);
    }

    /**
     * @param object $auth
     * @return array<string, int|string>
     */
    private function authReviewCurrent($auth): array
    {
        return [
            'id_card_status' => $auth->id_card_status,
            'id_card_remarks' => (string) $auth->id_card_remarks,
            'bank_no' => (string) $auth->bank_no,
            'bank_no_tmp' => (string) $auth->bank_no_tmp,
            'bank_name' => (string) $auth->bank_name,
            'bank_name_tmp' => (string) $auth->bank_name_tmp,
            'bank_addr' => (string) $auth->bank_addr,
            'bank_addr_tmp' => (string) $auth->bank_addr_tmp,
            'bank_card_img' => (string) $auth->bank_card_img,
            'bank_card_img_tmp' => (string) $auth->bank_card_img_tmp,
            'bank_card_back_img' => (string) $auth->bank_card_back_img,
            'bank_card_back_img_tmp' => (string) $auth->bank_card_back_img_tmp,
            'bank_status' => $auth->bank_status,
            'bank_remarks' => (string) $auth->bank_remarks,
            'is_bank_synced' => (int) $auth->is_bank_synced,
        ];
    }

    /**
     * @param object $outbox
     * @return array<string, int|string|null>
     */
    private function markUnknown($outbox, string $errorCode): array
    {
        $outbox->status = 'unknown';
        $outbox->available_at = null;
        $outbox->locked_at = null;
        $outbox->processed_at = null;
        $outbox->last_error_code = $errorCode;
        $this->clearSensitivePayload($outbox);
        $outbox->saveOrFail();

        return $this->outboxResult($outbox);
    }

    /**
     * @param object $outbox
     * @return array<string, int|string|null>
     */
    private function rejectOutbox($outbox, string $errorCode): array
    {
        $outbox->status = 'rejected';
        $outbox->active_user_id = null;
        $outbox->available_at = null;
        $outbox->locked_at = null;
        $outbox->processed_at = now();
        $outbox->last_error_code = $errorCode;
        $this->clearSensitivePayload($outbox);
        $outbox->saveOrFail();

        return $this->outboxResult($outbox);
    }

    /** @param object $outbox */
    private function clearSensitivePayload($outbox): void
    {
        $outbox->payload_ciphertext = null;
        $outbox->payload_hash = null;
        $outbox->auth_snapshot_hash = null;
    }

    /** @param object $outbox */
    private function ownsClaim($outbox, int $claimAttempt): bool
    {
        return $outbox->status === 'processing' && (int) $outbox->attempts === $claimAttempt;
    }

    /**
     * @param object $outbox
     * @return array<string, int|string|null>
     */
    private function outboxResult($outbox): array
    {
        return [
            'status' => (string) $outbox->status,
            'outbox_id' => (int) $outbox->id,
            'error_code' => $outbox->last_error_code === null
                ? null
                : (string) $outbox->last_error_code,
        ];
    }

    /**
     * @return array<string, int|string|null>
     */
    private function currentResult(int $outboxId): array
    {
        $outbox = AdminAuthReviewOutbox::whereKey($outboxId)->first();
        if (!$outbox) {
            return ['status' => 'missing', 'outbox_id' => $outboxId];
        }

        return $this->outboxResult($outbox);
    }

    private function isDuplicateActiveReview(QueryException $exception): bool
    {
        $driverCode = (int) ($exception->errorInfo[1] ?? 0);

        return $driverCode === 1062
            && strpos($exception->getMessage(), 'admin_auth_review_outboxes_active_user_unique') !== false;
    }

    /**
     * @param array<string, int|string> $context
     */
    private function logError(string $message, array $context): void
    {
        try {
            Log::error($message, $context);
        } catch (Throwable $exception) {
            // Persistence state remains authoritative when logging is unavailable.
        }
    }
}
