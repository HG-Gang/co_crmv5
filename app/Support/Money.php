<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/02
 * Time: 09:03
 */

/**
 * 金额值对象（精确十进制）。
 *
 * 文件功能：
 * - 以字符串形式保存金额，校验纯数字 + 可选两位小数格式，整数部分最多 16 位（对齐 DECIMAL(18,2)）。
 * - fromDecimalString() 同时校验金额必须大于 0 且落在配置的 [minimum, maximum] 区间内。
 * - multiplyByRate() 使用 BCMath 按汇率（最多 8 位小数，DECIMAL(18,8)）精确相乘，并按第三位四舍五入到两位小数。
 *
 * 适用场景：
 * - 充值/提现等涉及金额边界与汇率换算的入口参数校验，避免浮点误差。
 *
 * 入参例子：
 * - Money::fromDecimalString('1000.00', '1.00', '100000.00')
 * - $money->multiplyByRate('7.12345678')
 *
 * 返回值：
 * - fromDecimalString() 返回 Money 实例。
 * - toDecimalString() 返回规范化的两位小数字符串，如 "1000.00"。
 * - multiplyByRate() 返回两位小数的乘积字符串，如 "7123.46"。
 *
 * 异常或失败场景：
 * - 输入非纯数字格式、整数位超限、金额非正或超出配置区间时抛出 InvalidArgumentException。
 * - 汇率非正或超 8 位小数时抛出 InvalidArgumentException；BCMath 扩展缺失时抛出 LogicException。
 * - 配置区间最小值大于最大值时抛出 InvalidArgumentException。
 */

declare(strict_types=1);

namespace App\Support;

use InvalidArgumentException;
use LogicException;

final class Money
{
    /** @var string 规范化后的两位小数字符串，不可变。 */
    private $decimal;

    /**
     * 私有构造：只能通过 fromDecimalString() 创建，保证金额不可变。
     *
     * @param string $decimal 规范化后的两位小数金额。
     */
    private function __construct(string $decimal)
    {
        $this->decimal = $decimal;
    }

    /**
     * 从十进制字符串创建金额并校验边界。
     *
     * 校验语义：金额必须 > 0 且落在配置的 [minimum, maximum] 区间内；
     * 区间本身非法（最小 > 最大）或金额越界都失败关闭。
     *
     * @param string $value 金额字符串，如 '1000.00'。
     * @param string $minimum 允许的最小金额。
     * @param string $maximum 允许的最大金额。
     * @return self 规范化后的 Money 实例。
     * @throws InvalidArgumentException 格式/范围非法时抛出。
     */
    public static function fromDecimalString(string $value, string $minimum, string $maximum): self
    {
        $money = new self(self::normalize($value, 2));
        $minimumMoney = new self(self::normalize($minimum, 2));
        $maximumMoney = new self(self::normalize($maximum, 2));

        if (self::compare($minimumMoney->decimal, $maximumMoney->decimal) > 0) {
            throw new InvalidArgumentException('The configured money range is invalid.');
        }
        if (self::compare($money->decimal, '0.00') <= 0) {
            throw new InvalidArgumentException('Money must be greater than zero.');
        }
        if (self::compare($money->decimal, $minimumMoney->decimal) < 0
            || self::compare($money->decimal, $maximumMoney->decimal) > 0) {
            throw new InvalidArgumentException('Money is outside the configured range.');
        }

        return $money;
    }

    /**
     * 输出规范化金额。
     *
     * @return string 两位小数字符串，如 "1000.00"。
     */
    public function toDecimalString(): string
    {
        return $this->decimal;
    }

    /**
     * 按汇率精确相乘。
     *
     * BCMath 精度语义：以 scale=3 相乘后按第三位四舍五入（+0.005 截位）到两位，
     * 全程字符串运算，不经过浮点，保证金额计算可复现。
     *
     * @param string $rate 汇率，最多 8 位小数（DECIMAL(18,8)），必须 > 0。
     * @return string 两位小数的乘积字符串。
     * @throws InvalidArgumentException 汇率格式非法或非正时抛出。
     * @throws LogicException BCMath 扩展缺失时抛出（失败关闭，不退回浮点计算）。
     */
    public function multiplyByRate(string $rate): string
    {
        if (!function_exists('bcmul') || !function_exists('bcadd')) {
            throw new LogicException('BCMath is required for exact payment amount calculation.');
        }

        $normalizedRate = self::normalize($rate, 8);
        if (self::compareScaled($normalizedRate, '0.00000000', 8) <= 0) {
            throw new InvalidArgumentException('Exchange rate must be greater than zero.');
        }

        // scale=3 乘积的末位即第三位小数：>=5 进位 +0.01，否则直接截掉。
        $product = bcmul($this->decimal, $normalizedRate, 3);
        $rounded = substr($product, -1) >= '5'
            ? bcadd(substr($product, 0, -1), '0.01', 2)
            : substr($product, 0, -1);

        return self::normalize($rounded, 2);
    }

    /**
     * 规范化十进制字符串：限定位数与整数位数（金额 16 位 / 汇率 10 位）。
     *
     * @param string $value 原始字符串。
     * @param int $scale 允许的小数位数（2=金额，8=汇率）。
     * @return string 补齐小数位后的规范化字符串。
     * @throws InvalidArgumentException 格式非法或整数位超限时抛出。
     */
    private static function normalize(string $value, int $scale): string
    {
        $pattern = $scale === 2
            ? '/^[0-9]+(?:\.[0-9]{1,2})?$/'
            : '/^[0-9]+(?:\.[0-9]{1,8})?$/';
        if (!preg_match($pattern, $value)) {
            throw new InvalidArgumentException('Money must be a plain decimal string.');
        }

        [$whole, $fraction] = array_pad(explode('.', $value, 2), 2, '');
        $whole = ltrim($whole, '0');
        if ($whole === '') {
            $whole = '0';
        }
        $maximumWholeDigits = $scale === 2 ? 16 : 10;
        if (strlen($whole) > $maximumWholeDigits) {
            throw new InvalidArgumentException(
                $scale === 2
                    ? 'Money exceeds DECIMAL(18,2).'
                    : 'Exchange rate exceeds DECIMAL(18,8).'
            );
        }

        return $whole . '.' . str_pad($fraction, $scale, '0');
    }

    /**
     * 金额比较（scale=2 的便捷封装）。
     *
     * @param string $left 左侧金额。
     * @param string $right 右侧金额。
     * @return int -1/0/1。
     */
    private static function compare(string $left, string $right): int
    {
        return self::compareScaled($left, $right, 2);
    }

    /**
     * 指定精度比较：剥离小数点后按位数与字典序比较，等价于整数比较，避免浮点误差。
     *
     * @param string $left 左侧值。
     * @param string $right 右侧值。
     * @param int $scale 比较精度。
     * @return int -1/0/1。
     */
    private static function compareScaled(string $left, string $right, int $scale): int
    {
        $leftDigits = str_replace('.', '', self::normalize($left, $scale));
        $rightDigits = str_replace('.', '', self::normalize($right, $scale));
        $leftDigits = ltrim($leftDigits, '0') ?: '0';
        $rightDigits = ltrim($rightDigits, '0') ?: '0';

        if (strlen($leftDigits) !== strlen($rightDigits)) {
            return strlen($leftDigits) <=> strlen($rightDigits);
        }

        return strcmp($leftDigits, $rightDigits) <=> 0;
    }
}
