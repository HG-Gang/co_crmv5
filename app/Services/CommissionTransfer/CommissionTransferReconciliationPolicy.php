<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/02
 * Time: 12:57
 */

declare(strict_types=1);

namespace App\Services\CommissionTransfer;

/**
 * 佣金转账对账策略。
 *
 * 文件功能：
 * - 根据管理员提交的对账证据和转账原始步骤，评估对账决策是否合法。
 * - 支持三种决策：confirmed_completed（全部成功）、confirmed_compensated（已补偿）、confirmed_rejected（明确拒绝）。
 *
 * 适用场景：
 * - CommissionTransferReconciliationService 在管理员提交对账证据后调用 evaluate() 验证。
 *
 * 入参例子：
 * - originStep: 'withdraw'（转账卡住的步骤）。
 * - evidence: ['decision' => 'confirmed_completed', 'withdraw_status' => 'confirmed_processed', ...]。
 *
 * 返回值：
 * - CommissionTransferReconciliationDecision：allowed 或 denied。
 */
final class CommissionTransferReconciliationPolicy
{
    /**
     * 人工对账可接受的手动起源步骤全集（Saga 的 verify/limit/withdraw/deposit/compensate/accountinfo/finalize）。
     * 起源步骤决定“该步骤之后哪些资金动作可能已发生”，进而约束允许的决策组合；
     * 集合外的步骤值直接 denied，防止对未知中断点做出错误裁决。
     *
     * @var array<int, string>
     */
    private const ORIGIN_STEPS = [
        'verify', 'limit', 'withdraw', 'deposit', 'compensate', 'accountinfo', 'finalize',
    ];

    /**
     * 管理员可提交的资金步骤确认状态全集：processed=确认已处理、rejected=确认被拒、not_processed=确认未处理。
     * 三者决定证据组合能否推出 completed/compensated/rejected 决策；集合外的值一律 denied，保证裁决建立在明确事实上。
     *
     * @var array<int, string>
     */
    private const FUNDING_STATUSES = [
        'confirmed_processed', 'confirmed_rejected', 'confirmed_not_processed',
    ];

    /**
     * 余额规范化器：把证据中的余额文本统一为两位小数字符串后再与存储值比对。
     * 规范化前置是证据比对正确的前提，缺失或口径不同会造成合法证据被误判冲突。
     *
     * @var CommissionTransferBalanceNormalizer
     */
    private $balanceNormalizer;

    /**
     * 构造对账策略。
     *
     * @param CommissionTransferBalanceNormalizer|null $balanceNormalizer 余额规范化器，缺省时创建默认实现。
     */
    public function __construct(CommissionTransferBalanceNormalizer $balanceNormalizer = null)
    {
        $this->balanceNormalizer = $balanceNormalizer ?: new CommissionTransferBalanceNormalizer();
    }

    /**
     * 评估对账证据并给出决策。
     *
     * 决策语义：
     * - confirmed_completed：出金/入金均确认已处理，证据补全后终态 completed。
     * - confirmed_compensated：出金已处理、入金未处理且补偿已处理，终态 compensated。
     * - confirmed_rejected：出金/入金/补偿均未处理，终态 rejected。
     * 任何证据缺失、引用非法或与起源步骤不匹配都会返回 denied（失败关闭），
     * 调用方不得依据错误决策继续记账。
     *
     * @param string $originStep 转账卡住的步骤（manual_origin_step）。
     * @param array<string, mixed> $evidence 对账证据（decision/withdraw_status/deposit_status/compensation_status 及各引用、余额）。
     * @return CommissionTransferReconciliationDecision allowed 或 denied。
     */
    /** @param array<string, mixed> $evidence */
    public function evaluate(string $originStep, array $evidence): CommissionTransferReconciliationDecision
    {
        // 起源步骤必须是已知 Saga 步骤，否则无法判定恢复路径。
        if (!in_array($originStep, self::ORIGIN_STEPS, true)) {
            return CommissionTransferReconciliationDecision::denied('unknown_manual_origin_step');
        }

        // 三个资金命令的状态必须是已确认枚举值，且引用非空时格式合法；
        // 先做全局校验，避免单个决策分支重复处理公共证据。
        foreach (['withdraw', 'deposit', 'compensation'] as $command) {
            if (!in_array($evidence[$command . '_status'] ?? null, self::FUNDING_STATUSES, true)) {
                return CommissionTransferReconciliationDecision::denied('invalid_' . $command . '_status');
            }
            $reference = $evidence[$command . '_reference'] ?? null;
            if (!$this->isBlank($reference) && !$this->isValidReference($reference)) {
                return CommissionTransferReconciliationDecision::denied('invalid_' . $command . '_reference');
            }
        }

        $decision = (string) ($evidence['decision'] ?? '');
        if ($decision === 'confirmed_completed') {
            return $this->completed($originStep, $evidence);
        }
        if ($decision === 'confirmed_compensated') {
            return $this->compensated($originStep, $evidence);
        }
        if ($decision === 'confirmed_rejected') {
            return $this->rejected($originStep, $evidence);
        }

        return CommissionTransferReconciliationDecision::denied('invalid_decision');
    }

    /**
     * 裁决"全部成功"：要求出金/入金均已处理、补偿未处理，并补齐双方余额快照。
     *
     * 仅适用于已推进到 withdraw 之后的步骤；early 步骤（verify/limit）没有资金事实可确认，拒绝该决策。
     *
     * @param string $originStep 转账卡住的步骤。
     * @param array<string, mixed> $evidence 对账证据。
     * @return CommissionTransferReconciliationDecision allowed('completed') 或 denied。
     */
    /** @param array<string, mixed> $evidence */
    private function completed(
        string $originStep,
        array $evidence
    ): CommissionTransferReconciliationDecision {
        if (!in_array($originStep, ['withdraw', 'deposit', 'accountinfo', 'finalize'], true)) {
            return CommissionTransferReconciliationDecision::denied('decision_not_allowed_for_origin_step');
        }
        // 终态 completed 意味着外部资金已双向成功：余额证据不可缺，且必须能规范化为两位小数。
        if (($evidence['withdraw_status'] ?? null) !== 'confirmed_processed') {
            return CommissionTransferReconciliationDecision::denied('withdraw_must_be_processed');
        }
        if (($evidence['deposit_status'] ?? null) !== 'confirmed_processed') {
            return CommissionTransferReconciliationDecision::denied('deposit_must_be_processed');
        }
        if (($evidence['compensation_status'] ?? null) !== 'confirmed_not_processed') {
            return CommissionTransferReconciliationDecision::denied('compensation_must_not_be_processed');
        }
        if ($this->isBlank($evidence['withdraw_reference'] ?? null)) {
            return CommissionTransferReconciliationDecision::denied('withdraw_reference_required');
        }
        if ($this->isBlank($evidence['deposit_reference'] ?? null)) {
            return CommissionTransferReconciliationDecision::denied('deposit_reference_required');
        }
        if ($this->isBlank($evidence['source_balance_after'] ?? null)) {
            return CommissionTransferReconciliationDecision::denied('source_balance_after_required');
        }
        if ($this->balanceNormalizer->normalize((string) $evidence['source_balance_after']) === null) {
            return CommissionTransferReconciliationDecision::denied('invalid_source_balance_after');
        }
        if ($this->isBlank($evidence['target_balance_after'] ?? null)) {
            return CommissionTransferReconciliationDecision::denied('target_balance_after_required');
        }
        if ($this->balanceNormalizer->normalize((string) $evidence['target_balance_after']) === null) {
            return CommissionTransferReconciliationDecision::denied('invalid_target_balance_after');
        }

        return CommissionTransferReconciliationDecision::allowed('completed');
    }

    /**
     * 裁决"已补偿"：出金已处理、入金未成功且补偿已处理，资金已退回源账户。
     *
     * 仅适用于出金/入金/补偿步骤；入金为已拒绝时必须提供入金引用（证明拒绝真实发生）。
     *
     * @param string $originStep 转账卡住的步骤。
     * @param array<string, mixed> $evidence 对账证据。
     * @return CommissionTransferReconciliationDecision allowed('compensated') 或 denied。
     */
    /** @param array<string, mixed> $evidence */
    private function compensated(
        string $originStep,
        array $evidence
    ): CommissionTransferReconciliationDecision {
        if (!in_array($originStep, ['withdraw', 'deposit', 'compensate'], true)) {
            return CommissionTransferReconciliationDecision::denied('decision_not_allowed_for_origin_step');
        }
        if (($evidence['withdraw_status'] ?? null) !== 'confirmed_processed') {
            return CommissionTransferReconciliationDecision::denied('withdraw_must_be_processed');
        }
        // 入金只能是明确拒绝或确认未发送，二者都表示目标账户未收到资金。
        if (!in_array(
            $evidence['deposit_status'] ?? null,
            ['confirmed_rejected', 'confirmed_not_processed'],
            true
        )) {
            return CommissionTransferReconciliationDecision::denied('deposit_must_not_be_processed');
        }
        if (($evidence['compensation_status'] ?? null) !== 'confirmed_processed') {
            return CommissionTransferReconciliationDecision::denied('compensation_must_be_processed');
        }
        if ($this->isBlank($evidence['withdraw_reference'] ?? null)) {
            return CommissionTransferReconciliationDecision::denied('withdraw_reference_required');
        }
        if (($evidence['deposit_status'] ?? null) === 'confirmed_rejected'
            && $this->isBlank($evidence['deposit_reference'] ?? null)) {
            return CommissionTransferReconciliationDecision::denied('deposit_reference_required');
        }
        if ($this->isBlank($evidence['compensation_reference'] ?? null)) {
            return CommissionTransferReconciliationDecision::denied('compensation_reference_required');
        }

        return CommissionTransferReconciliationDecision::allowed('compensated');
    }

    /**
     * 裁决"明确拒绝"：任何资金命令都未处理，转账可安全终止。
     *
     * 仅适用于 verify/limit/withdraw 早期步骤；出金状态必须被确认为未处理或已拒绝，
     * 未知（unknown）状态意味着可能已扣款，禁止直接拒绝（失败关闭）。
     *
     * @param string $originStep 转账卡住的步骤。
     * @param array<string, mixed> $evidence 对账证据。
     * @return CommissionTransferReconciliationDecision allowed('rejected') 或 denied。
     */
    /** @param array<string, mixed> $evidence */
    private function rejected(
        string $originStep,
        array $evidence
    ): CommissionTransferReconciliationDecision {
        if (!in_array($originStep, ['verify', 'limit', 'withdraw'], true)) {
            return CommissionTransferReconciliationDecision::denied('decision_not_allowed_for_origin_step');
        }
        if (($evidence['withdraw_status'] ?? null) === 'confirmed_processed') {
            return CommissionTransferReconciliationDecision::denied('withdraw_must_not_be_processed');
        }
        if (!in_array(
            $evidence['withdraw_status'] ?? null,
            ['confirmed_rejected', 'confirmed_not_processed'],
            true
        )) {
            return CommissionTransferReconciliationDecision::denied('withdraw_status_unconfirmed');
        }
        if (($evidence['deposit_status'] ?? null) !== 'confirmed_not_processed') {
            return CommissionTransferReconciliationDecision::denied('deposit_must_not_be_processed');
        }
        if (($evidence['compensation_status'] ?? null) !== 'confirmed_not_processed') {
            return CommissionTransferReconciliationDecision::denied('compensation_must_not_be_processed');
        }

        return CommissionTransferReconciliationDecision::allowed('rejected');
    }

    /**
     * 判断证据值是否为空白。
     *
     * 非字符串或去除首尾空白后为空都视为空白，防止数组/对象等非法证据进入后续引用校验。
     *
     * @param mixed $value 对账证据字段值。
     * @return bool true 表示空白或类型非法。
     */
    /** @param mixed $value */
    private function isBlank($value): bool
    {
        return !is_string($value) || trim($value) === '';
    }

    /**
     * 校验引用值格式：仅允许非空字符串且不超过 100 字符（对齐库字段上限）。
     *
     * @param mixed $value 待校验引用。
     * @return bool 合法为 true。
     */
    /** @param mixed $value */
    private function isValidReference($value): bool
    {
        return is_string($value) && mb_strlen(trim($value), 'UTF-8') <= 100;
    }
}
