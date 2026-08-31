<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/02
 * Time: 08:50
 */

/**
 * 佣金划转余额规范化工具。
 *
 * 文件功能：
 * - 将余额字符串规范化为两位小数的标准格式（DECIMAL），并校验整数部分不超过 16 位。
 *
 * 适用场景：
 * - 佣金划转流程中，对 MT4 返回的余额字段进行格式统一，确保数值精度一致。
 *
 * 入参例子：
 * - value: "1234.5" -> 返回 "1234.50"
 *
 * 返回值：
 * - 成功时返回形如 "1234.50" 的规范化余额字符串。
 * - 失败时（格式不符或位数超限）返回 null。
 *
 * 异常或失败场景：
 * - 输入不符合纯数字加可选两位小数的格式时返回 null。
 * - 整数部分超过 16 位时返回 null。
 */

declare(strict_types=1);

namespace App\Services\CommissionTransfer;

final class CommissionTransferBalanceNormalizer
{
    /**
     * 规范化余额字符串。
     *
     * @param string $value MT4 返回的原始余额，如 "1234.5"。
     * @return string|null 成功时返回两位小数标准格式（如 "1234.50"）；格式不符或整数部分超过 16 位时返回 null（失败关闭，由调用方决定拒绝或重试）。
     */
    public function normalize(string $value): ?string
    {
        // 只接受纯数字加可选一位或两位小数；带符号、科学计数或超精度输入一律拒绝，避免脏数据进入记账。
        $value = trim($value);
        if (!preg_match('/^[0-9]+(?:\.[0-9]{1,2})?$/D', $value)) {
            return null;
        }

        // 去掉前导零并限制整数位数，对齐 DECIMAL(18,2) 上限，防止溢出数据库字段。
        [$whole, $fraction] = array_pad(explode('.', $value, 2), 2, '');
        $whole = ltrim($whole, '0') ?: '0';
        if (strlen($whole) > 16) {
            return null;
        }

        // 小数部分不足两位时补零，保证所有调用方拿到统一精度的字符串。
        return $whole . '.' . str_pad($fraction, 2, '0');
    }
}
