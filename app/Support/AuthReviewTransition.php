<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/07
 * Time: 15:29
 */

declare(strict_types=1);

namespace App\Support;

use InvalidArgumentException;

/**
 * 实名审核状态机纯函数工具类。
 *
 * 文件功能：
 * - reviewQueueStatuses()：把旧审核列表的 auth_status 筛选值映射为身份证/银行卡状态集合。
 * - normalizeDecisions()/legacyDecisionPayload()：把现代请求契约与旧表单入参分别规范化为
 *   身份证、银行卡两个组件决定（1=通过、2=拒绝），拒绝必带原因，非法输入抛 InvalidArgumentException。
 * - assertReviewableComponents()：拒绝对待审核状态之外的组件重复审核；resolve() 计算最终
 *   组件状态、聚合 user_auth 状态与换卡同步标记。
 * - 全部为纯函数；明确不负责数据库读写与 MT4 交付（AdminAuthReviewProcessor）。
 */
final class AuthReviewTransition
{
    /**
     * 审核决定枚举：通过（1）。来自请求入参 contract 的 decision 值（1=通过、2=拒绝），
     * 与 user_auths 表内的状态值不是同一族——它只表达“管理员做出的动作”，写入前必须经 resolve() 映射为 STATUS_*。
     */
    private const DECISION_APPROVE = 1;

    /**
     * 审核决定枚举：拒绝（2）。与 DECISION_APPROVE 的差异是要求必须携带 reason（否则拒绝无审计依据）；
     * 同族内仅此两个值，其他输入一律抛 InvalidArgumentException。
     */
    private const DECISION_REJECT = 2;

    /**
     * 实名审核结果状态：审核通过（2）。写入 user_auths.id_card_status/bank_status 的最终态之一；
     * 与 DECISION_APPROVE 值相同但语义不同——这是库内状态机的终态，不是请求动作。
     */
    private const STATUS_APPROVED = 2;

    /**
     * 实名审核结果状态：审核拒绝（4）。与 STATUS_APPROVED 同族，二选一写入；
     * 拒绝时同步把 reason 写入对应 remarks 字段，旧列表按 auth_status=2 筛选时即命中该状态。
     */
    private const STATUS_REJECTED = 4;

    /**
     * 返回旧审核列表筛选值对应的身份证和银行卡状态集合。
     *
     * auth_status=1 表示待审核，银行卡换绑状态 3 同样属于待审核；
     * auth_status=2 表示审核未通过；空值同时返回待审核和未通过记录。
     *
     * @param mixed $filter
     * @return array{id_card_statuses: array<int, int>, bank_statuses: array<int, int>}
     */
    public static function reviewQueueStatuses($filter): array
    {
        if ($filter === null || $filter === '') {
            return [
                'id_card_statuses' => [1, 4],
                'bank_statuses' => [1, 3, 4],
            ];
        }

        if (!is_int($filter) && !is_string($filter)) {
            throw new InvalidArgumentException('auth_status must be 1 or 2.');
        }

        if ((string) $filter === '1') {
            return [
                'id_card_statuses' => [1],
                'bank_statuses' => [1, 3],
            ];
        }

        if ((string) $filter === '2') {
            return [
                'id_card_statuses' => [4],
                'bank_statuses' => [4],
            ];
        }

        throw new InvalidArgumentException('auth_status must be 1 or 2.');
    }

    /**
     * Normalize the modern request contract without collapsing component decisions.
     *
     * @param array<string, mixed> $payload
     * @return array<string, int|string>
     */
    public static function normalizeDecisions(array $payload): array
    {
        $hasStatus = self::hasValue($payload, 'status');
        $hasIdCardDecision = self::hasValue($payload, 'id_card_decision');
        $hasBankDecision = self::hasValue($payload, 'bank_decision');

        if ($hasStatus && ($hasIdCardDecision || $hasBankDecision)) {
            throw new InvalidArgumentException('status cannot be combined with component decisions.');
        }

        if ($hasStatus) {
            $decision = self::decision($payload['status'], 'status');
            $reason = (string) ($payload['reason'] ?? '');
            if ($decision === self::DECISION_REJECT) {
                $reason = trim($reason);
                if ($reason === '') {
                    throw new InvalidArgumentException('reason is required for rejection.');
                }
            }

            return [
                'id_card_decision' => $decision,
                'id_card_reason' => $reason,
                'bank_decision' => $decision,
                'bank_reason' => $reason,
            ];
        }

        if (!$hasIdCardDecision && !$hasBankDecision) {
            throw new InvalidArgumentException('At least one auth review decision is required.');
        }

        $normalized = [];
        $sharedReason = (string) ($payload['reason'] ?? '');
        if ($hasIdCardDecision) {
            $decision = self::decision($payload['id_card_decision'], 'id_card_decision');
            $reason = array_key_exists('id_card_reason', $payload)
                ? (string) $payload['id_card_reason']
                : $sharedReason;
            if ($decision === self::DECISION_REJECT) {
                $reason = trim($reason);
                if ($reason === '') {
                    throw new InvalidArgumentException('id_card_reason is required for rejection.');
                }
            }

            $normalized['id_card_decision'] = $decision;
            $normalized['id_card_reason'] = $reason;
        }
        if ($hasBankDecision) {
            $decision = self::decision($payload['bank_decision'], 'bank_decision');
            $reason = array_key_exists('bank_reason', $payload)
                ? (string) $payload['bank_reason']
                : $sharedReason;
            if ($decision === self::DECISION_REJECT) {
                $reason = trim($reason);
                if ($reason === '') {
                    throw new InvalidArgumentException('bank_reason is required for rejection.');
                }
            }

            $normalized['bank_decision'] = $decision;
            $normalized['bank_reason'] = $reason;
        }

        return $normalized;
    }

    /**
     * Reject decisions for components that are no longer awaiting review.
     *
     * @param array<string, mixed> $current
     * @param array<string, int|string> $decisions
     */
    public static function assertReviewableComponents(array $current, array $decisions): void
    {
        if (array_key_exists('id_card_decision', $decisions)
            && !self::statusIsOneOf($current['id_card_status'] ?? null, [1])
        ) {
            throw new InvalidArgumentException('The ID card component is not reviewable.');
        }

        if (array_key_exists('bank_decision', $decisions)
            && !self::statusIsOneOf($current['bank_status'] ?? null, [1, 3])
        ) {
            throw new InvalidArgumentException('The bank component is not reviewable.');
        }
    }

    /**
     * Convert the old form flags into the component decisions the old controller applied.
     *
     * @param array<string, mixed> $payload
     * @return array<string, int|string>
     */
    public static function legacyDecisionPayload(array $payload): array
    {
        $normalized = [];
        if (self::legacyStatusIsActive($payload['userIdcard_status'] ?? null, ['1'])) {
            if (!array_key_exists('idcard_auth', $payload) || !is_scalar($payload['idcard_auth'])) {
                throw new InvalidArgumentException('idcard_auth is required for an active ID card review.');
            }

            $normalized['id_card_decision'] = (string) $payload['idcard_auth'] === '0'
                ? self::DECISION_APPROVE
                : self::DECISION_REJECT;
            $normalized['id_card_reason'] = (string) ($payload['idcard_reason'] ?? '');
        }

        if (self::legacyStatusIsActive($payload['userbank_status'] ?? null, ['1', '3'])) {
            if (!array_key_exists('bank_auth', $payload) || !is_scalar($payload['bank_auth'])) {
                throw new InvalidArgumentException('bank_auth is required for an active bank review.');
            }

            $normalized['bank_decision'] = (string) $payload['bank_auth'] === '0'
                ? self::DECISION_APPROVE
                : self::DECISION_REJECT;
            $normalized['bank_reason'] = (string) ($payload['bank_reason'] ?? '');
        }

        if ($normalized === []) {
            throw new InvalidArgumentException('The legacy request has no active auth component.');
        }

        return $normalized;
    }

    /**
     * Resolve local field updates and the aggregate user authentication status.
     *
     * @param array<string, mixed> $current
     * @param array<string, int|string> $decisions
     * @return array<string, mixed>
     */
    public static function resolve(array $current, array $decisions): array
    {
        $finalIdCardStatus = (int) ($current['id_card_status'] ?? 0);
        $finalBankStatus = (int) ($current['bank_status'] ?? 0);
        $bankSyncNo = (string) ($current['bank_no'] ?? '');
        $bankSyncName = (string) ($current['bank_name'] ?? '');
        $updates = [];

        if (array_key_exists('id_card_decision', $decisions)) {
            $idCardApproved = (int) $decisions['id_card_decision'] === self::DECISION_APPROVE;
            $finalIdCardStatus = $idCardApproved ? self::STATUS_APPROVED : self::STATUS_REJECTED;
            $updates['id_card_status'] = $finalIdCardStatus;
            $updates['id_card_remarks'] = $idCardApproved ? '' : (string) ($decisions['id_card_reason'] ?? '');
        }

        $bankSyncRequired = false;
        if (array_key_exists('bank_decision', $decisions)) {
            $bankApproved = (int) $decisions['bank_decision'] === self::DECISION_APPROVE;
            $finalBankStatus = $bankApproved ? self::STATUS_APPROVED : self::STATUS_REJECTED;
            $updates['bank_status'] = $finalBankStatus;
            $updates['bank_remarks'] = $bankApproved ? '' : (string) ($decisions['bank_reason'] ?? '');
            if ($bankApproved) {
                if ((int) ($current['bank_status'] ?? 0) === 3) {
                    $bankFields = [
                        'bank_no' => 'bank_no_tmp',
                        'bank_name' => 'bank_name_tmp',
                        'bank_addr' => 'bank_addr_tmp',
                        'bank_card_img' => 'bank_card_img_tmp',
                        'bank_card_back_img' => 'bank_card_back_img_tmp',
                    ];
                    foreach ($bankFields as $approvedField => $temporaryField) {
                        $temporaryValue = (string) ($current[$temporaryField] ?? '');
                        $updates[$approvedField] = $temporaryValue !== ''
                            ? $temporaryValue
                            : (string) ($current[$approvedField] ?? '');
                        $updates[$temporaryField] = '';
                    }

                    $bankSyncNo = $updates['bank_no'];
                    $bankSyncName = $updates['bank_name'];
                }

                $updates['is_bank_synced'] = 1;
                $bankSyncRequired = true;
            }
        }

        if ($finalIdCardStatus === self::STATUS_APPROVED && $finalBankStatus === self::STATUS_APPROVED) {
            $userAuthStatus = 1;
        } elseif ($finalIdCardStatus === self::STATUS_REJECTED || $finalBankStatus === self::STATUS_REJECTED) {
            $userAuthStatus = 2;
        } else {
            $userAuthStatus = 0;
        }

        return [
            'auth_updates' => $updates,
            'final_id_card_status' => $finalIdCardStatus,
            'final_bank_status' => $finalBankStatus,
            'user_auth_status' => $userAuthStatus,
            'bank_sync_required' => $bankSyncRequired,
            'bank_sync_no' => $bankSyncNo,
            'bank_sync_name' => $bankSyncName,
        ];
    }

    /**
     * @param array<string, mixed> $payload
     */
    private static function hasValue(array $payload, string $key): bool
    {
        return array_key_exists($key, $payload) && $payload[$key] !== null && $payload[$key] !== '';
    }

    /**
     * @param mixed $value
     */
    private static function decision($value, string $field): int
    {
        $normalized = is_int($value)
            ? (string) $value
            : (is_string($value) ? trim($value) : '');
        if ($normalized !== '1' && $normalized !== '2') {
            throw new InvalidArgumentException($field . ' must be 1 or 2.');
        }

        return (int) $normalized;
    }

    /**
     * @param mixed $value
     * @param array<int, int> $allowedStatuses
     */
    private static function statusIsOneOf($value, array $allowedStatuses): bool
    {
        if (is_int($value)) {
            return in_array($value, $allowedStatuses, true);
        }

        if (!is_string($value)) {
            return false;
        }

        return in_array($value, array_map('strval', $allowedStatuses), true);
    }

    /**
     * @param mixed $value
     * @param array<int, string> $activeStatuses
     */
    private static function legacyStatusIsActive($value, array $activeStatuses): bool
    {
        if (!is_int($value) && !is_string($value)) {
            return false;
        }

        return in_array((string) $value, $activeStatuses, true);
    }
}
