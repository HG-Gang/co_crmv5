<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/02
 * Time: 08:56
 */

/**
 * 出金账户快照值对象。
 *
 * 文件功能：
 * - 封装 MT4 账户的余额（balance）与可用保证金（freeMargin）快照，并提供 available() 方法使用 BCMath 计算可出金额度。
 * - 实例不可变：构造时完成规范化，balance() / freeMargin() 返回固定值。
 *
 * 精度语义：所有金额以字符串保存并使用 BCMath（scale=2）比较，避免浮点误差；
 * 可出金额度 = min(balance, freeMargin)，任一非正时为 "0.00"。
 *
 * 适用场景：
 * - 出金流程中，获取用户账户资金状况，计算实际可提取金额。
 *
 * 入参例子：
 * - new WithdrawalAccountSnapshot("15000.00", "12000.00")
 *
 * 返回值：
 * - available() 返回 min(balance, freeMargin) 且非负的两位小数字符串。
 *
 * 异常或失败场景：
 * - 构造函数输入格式不符（非纯数字带可选符号的两位小数）时抛出 InvalidArgumentException。
 * - 整数部分超过 16 位时抛出 InvalidArgumentException。
 * - BCMath 扩展不可用时 available() 抛出 LogicException。
 */

declare(strict_types=1);

namespace App\Services\Withdrawal;

use InvalidArgumentException;
use LogicException;

final class WithdrawalAccountSnapshot
{
    /**
     * 账户余额（构造时已规范化为带符号两位小数、整数部分最多 16 位的字符串）。
     * 可出金额度 = min(balance, freeMargin) 的第一输入；字符串 + BCMath 存储避免浮点误差导致多放款。
     *
     * @var string
     */
    private $balance;

    /**
     * 可用保证金（构造时已规范化，口径同 balance）。与 balance 取 min 后得到可出金额度，
     * 持仓占用保证金越高该值越小，防止单看余额把保证金也提走导致爆仓。
     *
     * @var string
     */
    private $freeMargin;

    /**
     * 构造快照：双方金额先规范化（带符号两位小数、整数部分最多 16 位）。
     *
     * @param string $balance 账户余额，如 "15000.00"。
     * @param string $freeMargin 可用保证金，如 "12000.00"。
     * @throws InvalidArgumentException 金额格式非法或超 DECIMAL(18,2) 范围时抛出。
     */
    public function __construct(string $balance, string $freeMargin)
    {
        $this->balance = self::normalize($balance);
        $this->freeMargin = self::normalize($freeMargin);
    }

    /**
     * 账户余额（规范化后）。
     *
     * @return string 两位小数字符串。
     */
    public function balance(): string
    {
        return $this->balance;
    }

    /**
     * 可用保证金（规范化后）。
     *
     * @return string 两位小数字符串。
     */
    public function freeMargin(): string
    {
        return $this->freeMargin;
    }

    /**
     * 计算可出金额度。
     *
     * 语义：取 balance 与 freeMargin 的较小值；两者任一 ≤ 0 时返回 "0.00"。
     * 依赖 BCMath（scale=2）保证比较精确。
     *
     * @return string 两位小数的可出金额度。
     * @throws LogicException BCMath 扩展不可用时抛出（失败关闭，不退回浮点计算）。
     */
    public function available(): string
    {
        if (!function_exists('bccomp')) {
            throw new LogicException('BCMath is required for withdrawal snapshot calculations.');
        }
        if (bccomp($this->balance, '0.00', 2) <= 0 || bccomp($this->freeMargin, '0.00', 2) <= 0) {
            return '0.00';
        }

        return bccomp($this->balance, $this->freeMargin, 2) <= 0
            ? $this->balance
            : $this->freeMargin;
    }

    /**
     * 规范化金额：仅接受带可选正负号的纯数字两位小数；剥离符号后去除前导零并校验 16 位整数上限。
     *
     * @param string $value 原始金额。
     * @return string 规范化后的两位小数字符串（负零归并为 "0.00"）。
     * @throws InvalidArgumentException 格式非法或位数超限时抛出。
     */
    private static function normalize(string $value): string
    {
        if (!preg_match('/^[+-]?[0-9]+(?:\.[0-9]{1,2})?$/', $value)) {
            throw new InvalidArgumentException('Snapshot amount must be a plain signed decimal string.');
        }

        $negative = substr($value, 0, 1) === '-';
        if ($negative || substr($value, 0, 1) === '+') {
            $value = substr($value, 1);
        }
        [$whole, $fraction] = array_pad(explode('.', $value, 2), 2, '');
        $whole = ltrim($whole, '0');
        if ($whole === '') {
            $whole = '0';
        }
        if (strlen($whole) > 16) {
            throw new InvalidArgumentException('Snapshot amount exceeds DECIMAL(18,2).');
        }

        $normalized = $whole . '.' . str_pad($fraction, 2, '0');
        if ($normalized === '0.00') {
            return $normalized;
        }

        return ($negative ? '-' : '') . $normalized;
    }
}
