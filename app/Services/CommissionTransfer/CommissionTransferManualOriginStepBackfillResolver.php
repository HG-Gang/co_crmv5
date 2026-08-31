<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/02
 * Time: 08:51
 */

/**
 * 佣金划转手动来源步骤回填解析器。
 *
 * 文件功能：
 * - 当 Saga 步骤标识不清晰时，根据当前步骤和错误码解析出实际的起源步骤，用于手动干预入口的回填。
 *
 * 适用场景：
 * - 佣金划转 Saga 进入手动处理状态时，需要确定是哪一步触发了人工介入，以便正确地恢复或重试。
 *
 * 入参例子：
 * - resolve("manual", "withdraw_result_unknown") -> "withdraw"
 * - resolve("deposit", null) -> "deposit"
 *
 * 返回值：
 * - 返回 Saga 步骤名称字符串（verify / limit / withdraw / deposit / compensate / accountinfo / finalize 或 "unknown"）。
 *
 * 异常或失败场景：
 * - 当前步骤和错误码均无法匹配任何已知步骤时，返回 "unknown"。
 */

declare(strict_types=1);

namespace App\Services\CommissionTransfer;

final class CommissionTransferManualOriginStepBackfillResolver
{
    /**
     * Saga 全部合法步骤；直接命中时无需靠错误码推断起源步骤。
     */
    private const SAGA_STEPS = [
        'verify', 'limit', 'withdraw', 'deposit', 'compensate', 'accountinfo', 'finalize',
    ];

    /**
     * 已知错误码到起源步骤的映射：错误码只可能产生于对应步骤，
     * 用于填补因状态流转异常而缺失的 manual_origin_step。
     */
    private const ERROR_ORIGINS = [
        'payload_decrypt_failed' => 'verify',
        'withdraw_result_unknown' => 'withdraw',
        'deposit_result_unknown' => 'deposit',
        'compensation_result_uncertain' => 'compensate',
        'accountinfo_rejected' => 'accountinfo',
        'source_accountinfo_rejected' => 'accountinfo',
        'target_accountinfo_rejected' => 'accountinfo',
    ];

    /**
     * 解析转账的起源步骤。
     *
     * @param string $currentStep 转账当前步骤。
     * @param string|null $errorCode 触发人工介入的错误码，可为 null。
     * @return string 合法的 Saga 步骤名；当前步骤非法且错误码无映射时返回 "unknown"（失败关闭，禁止猜测）。
     */
    public function resolve(string $currentStep, string $errorCode = null): string
    {
        // 当前步骤本身合法时直接采纳，错误码仅用于步骤缺失时的回填。
        $currentStep = trim($currentStep);
        if (in_array($currentStep, self::SAGA_STEPS, true)) {
            return $currentStep;
        }

        return self::ERROR_ORIGINS[trim((string) $errorCode)] ?? 'unknown';
    }
}
