<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/02
 * Time: 08:56
 */

/**
 * MT4 交易密码验证结果值对象。
 *
 * 文件功能：
 * - 封装 MT4 交易密码验证结果，支持四种状态：已验证（verified）、可重试未发送（retryable_not_sent）、未知（unknown）、已拒绝（rejected）。
 * - 实例不可变：状态与错误码在构造时固定，只允许通过工厂方法创建。
 *
 * 状态语义：
 * - verified：密码与账户匹配，可继续后续资金步骤。
 * - retryable_not_sent：请求未送达，可安全重试。
 * - unknown：验证结果不确定（超时/写失败/串户），禁止自动重试，转人工。
 * - rejected：密码错误等业务性拒绝，进入终态。
 *
 * 适用场景：
 * - 佣金划转流程的安全验证阶段，校验用户提交的 MT4 交易密码是否有效。
 *
 * 入参例子：
 * - TradePasswordVerificationResult::verified()
 * - TradePasswordVerificationResult::rejected("wrong_password")
 *
 * 返回值：
 * - 成功时 status() 返回 "verified"。
 * - 失败时返回对应的失败状态与错误码。
 *
 * 异常或失败场景：
 * - retryableNotSent / unknown / rejected 工厂方法传入空错误码时抛出 InvalidArgumentException。
 */

declare(strict_types=1);

namespace App\Services\CommissionTransfer;

use InvalidArgumentException;

final class TradePasswordVerificationResult
{
    /** @var string 验证状态：verified/retryable_not_sent/unknown/rejected。 */
    private $status;

    /** @var string|null 失败状态时的错误码（仅失败状态有值）。 */
    private $errorCode;

    /**
     * 私有构造：保证只能通过工厂方法创建，且状态与错误码在创建后不可变。
     *
     * @param string $status 验证状态。
     * @param string|null $errorCode 失败错误码。
     */
    private function __construct(string $status, string $errorCode = null)
    {
        $this->status = $status;
        $this->errorCode = $errorCode;
    }

    /**
     * 创建已验证结果：密码与账户匹配。
     *
     * @return self 已验证结果对象。
     */
    public static function verified(): self
    {
        return new self('verified', null);
    }

    /**
     * 创建可重试未发送结果：请求未送达 MT4，可安全重试。
     *
     * @param string $errorCode 错误码。
     * @return self 可重试未发送结果对象。
     * @throws InvalidArgumentException 错误码为空时抛出。
     */
    public static function retryableNotSent(string $errorCode): self
    {
        return new self('retryable_not_sent', self::requiredErrorCode($errorCode));
    }

    /**
     * 创建未知结果：验证结果不确定，需人工核对。
     *
     * @param string $errorCode 错误码。
     * @return self 未知结果对象。
     * @throws InvalidArgumentException 错误码为空时抛出。
     */
    public static function unknown(string $errorCode): self
    {
        return new self('unknown', self::requiredErrorCode($errorCode));
    }

    /**
     * 创建已拒绝结果：密码错误等业务性拒绝。
     *
     * @param string $errorCode 错误码。
     * @return self 已拒绝结果对象。
     * @throws InvalidArgumentException 错误码为空时抛出。
     */
    public static function rejected(string $errorCode): self
    {
        return new self('rejected', self::requiredErrorCode($errorCode));
    }

    /**
     * 当前验证状态。
     *
     * @return string verified / retryable_not_sent / unknown / rejected。
     */
    public function status(): string
    {
        return $this->status;
    }

    /**
     * 失败状态的错误码。
     *
     * @return string|null 失败状态为错误码，已验证时为 null。
     */
    public function errorCode(): ?string
    {
        return $this->errorCode;
    }

    /**
     * 校验失败状态必须携带非空错误码，避免无归因的失败掩盖问题。
     *
     * @param string $errorCode 原始错误码。
     * @return string 去除首尾空格后的错误码。
     * @throws InvalidArgumentException 错误码为空时抛出。
     */
    private static function requiredErrorCode(string $errorCode): string
    {
        $errorCode = trim($errorCode);
        if ($errorCode === '') {
            throw new InvalidArgumentException('Trade password failure requires an error code.');
        }

        return $errorCode;
    }
}
