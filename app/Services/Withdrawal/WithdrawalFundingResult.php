<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/02
 * Time: 08:57
 */

declare(strict_types=1);

namespace App\Services\Withdrawal;

use InvalidArgumentException;

/**
 * 提现扣款结果值对象。
 *
 * 文件功能：
 * - 封装 MT4 提现扣款调用的返回状态，区分已扣款 / 可重试 / 未知 / 拒绝。
 * - 实例不可变：状态与凭证/错误码在构造时固定，只允许通过工厂方法创建。
 *
 * 状态语义：
 * - debited：MT4 已扣款并返回 ticket，后续必须进入结算/退款路径。
 * - retryable_not_sent：请求未送达，可安全重试。
 * - unknown：扣款结果不确定，禁止重试，需人工核对 ticket。
 * - rejected：业务侧明确拒绝，进入拒绝终态或退款路径。
 *
 * 适用场景：
 * - Mt4WithdrawalFundingGateway 调用 MT4 出金接口后返回。
 * - 提现结算扫描器根据 status 决定后续处理路径。
 *
 * 入参例子：
 * - debited('TICKET777')：扣款成功。
 * - retryableNotSent('connection_failed')：连接失败可重试。
 * - unknown('read_timeout')：结果不确定。
 * - rejected('insufficient_balance')：余额不足明确拒绝。
 */
final class WithdrawalFundingResult
{
    /** @var string 结果状态：debited/retryable_not_sent/unknown/rejected。 */
    private $status;

    /** @var string|null MT4 ticket（仅 debited 有值）。 */
    private $providerReference;

    /** @var string|null 失败错误码（仅失败状态有值）。 */
    private $errorCode;

    /**
     * 私有构造：保证只能通过工厂方法创建，且状态与数据在创建后不可变。
     *
     * @param string $status 结果状态。
     * @param string|null $providerReference MT4 ticket。
     * @param string|null $errorCode 失败错误码。
     */
    private function __construct(string $status, string $providerReference = null, string $errorCode = null)
    {
        $this->status = $status;
        $this->providerReference = $providerReference;
        $this->errorCode = $errorCode;
    }

    /**
     * 创建已扣款结果：MT4 已扣款并返回 ticket。
     *
     * @param string $providerReference MT4 ticket，必须非空。
     * @return self 已扣款结果对象。
     * @throws InvalidArgumentException ticket 为空时抛出。
     */
    public static function debited(string $providerReference): self
    {
        $providerReference = trim($providerReference);
        if ($providerReference === '') {
            throw new InvalidArgumentException('Debited result requires a provider reference.');
        }

        return new self('debited', $providerReference, null);
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
        return new self('retryable_not_sent', null, self::requiredErrorCode($errorCode));
    }

    /**
     * 创建未知结果：扣款结果不确定，需人工核对。
     *
     * @param string $errorCode 错误码。
     * @return self 未知结果对象。
     * @throws InvalidArgumentException 错误码为空时抛出。
     */
    public static function unknown(string $errorCode): self
    {
        return new self('unknown', null, self::requiredErrorCode($errorCode));
    }

    /**
     * 创建已拒绝结果：业务侧明确拒绝扣款。
     *
     * @param string $errorCode 错误码。
     * @return self 已拒绝结果对象。
     * @throws InvalidArgumentException 错误码为空时抛出。
     */
    public static function rejected(string $errorCode): self
    {
        return new self('rejected', null, self::requiredErrorCode($errorCode));
    }

    /**
     * 当前结果状态。
     *
     * @return string debited / retryable_not_sent / unknown / rejected。
     */
    public function status(): string
    {
        return $this->status;
    }

    /**
     * MT4 扣款 ticket。
     *
     * @return string|null 已扣款时为 ticket，其余为 null。
     */
    public function providerReference(): ?string
    {
        return $this->providerReference;
    }

    /**
     * 失败错误码。
     *
     * @return string|null 失败状态为错误码，已扣款时为 null。
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
            throw new InvalidArgumentException('Withdrawal funding failure requires an error code.');
        }

        return $errorCode;
    }
}
