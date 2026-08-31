<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/02
 * Time: 12:53
 */

declare(strict_types=1);

namespace App\Services\Payment;

use InvalidArgumentException;

/**
 * 充值结算结果值对象。
 *
 * 文件功能：
 * - 封装充值结算（MT4 入金）调用的返回状态，区分已结算 / 可重试 / 未知 / 拒绝。
 * - 状态语义：settled 已入账；retryable_not_sent 明确未发送可安全重试；unknown 结果不确定必须对账后重试；
 *   rejected 明确拒绝，重试无意义。
 * - 本对象不可变：状态、供应商引用与错误码在构造时固定，后续只能读取。
 *
 * 适用场景：
 * - Mt4DepositSettlementGateway / Mt4CreditSettlementGateway 调用 MT4 入金接口后返回。
 * - 结算出箱扫描器根据 status 决定是否重试或记录失败。
 *
 * 入参例子：
 * - settled('TICKET999')：入金成功。
 * - retryableNotSent('connection_failed')：连接失败可重试。
 * - unknown('read_timeout')：结果不确定。
 * - rejected('account_disabled')：明确拒绝。
 *
 * 返回值：
 * - status(): 'settled' | 'retryable_not_sent' | 'unknown' | 'rejected'
 * - providerReference(): 成功时返回 MT4 票据号，否则为 null。
 */
final class DepositSettlementResult
{
    /**
     * 结算状态：settled / retryable_not_sent / unknown / rejected 四选一。
     * 四态差异是重试安全性的分级：明确未发送可重试、结果不确定必须先对账、明确拒绝重试无意义；
     * 出箱扫描器据此决定是否重试，状态语义被篡改会直接造成重复入金或资金丢失。
     *
     * @var string
     */
    private $status;

    /**
     * MT4 侧结算凭证（票据号），仅 settled 状态非空。是“远端已入账”的唯一外部证据，
     * 供对账与人工核查引用；其他状态强制为 null，防止把无凭证结果当成功。
     *
     * @var string|null
     */
    private $providerReference;

    /**
     * 失败/未知状态的错误码（如 connection_failed、read_timeout），settled 时为 null。
     * 供出箱记录落库 last_error 与日志追溯，不含敏感信息。
     *
     * @var string|null
     */
    private $errorCode;

    /**
     * 私有构造：状态、票据号与错误码在创建时一次性固定，保证结果对象不可变。
     *
     * @param string $status 结算状态（settled / retryable_not_sent / unknown / rejected）。
     * @param string|null $providerReference 仅 settled 状态携带的 MT4 票据号。
     * @param string|null $errorCode 仅失败状态携带的错误码。
     */
    private function __construct(string $status, string $providerReference = null, string $errorCode = null)
    {
        $this->status = $status;
        $this->providerReference = $providerReference;
        $this->errorCode = $errorCode;
    }

    /**
     * 已结算结果：要求非空供应商票据号，为空说明响应数据不可信，由调用方转 unknown 处理。
     *
     * @param string $providerReference MT4 票据号。
     * @return self 状态为 settled 的结果对象。
     * @throws InvalidArgumentException 票据号为空时抛出。
     */
    public static function settled(string $providerReference): self
    {
        $providerReference = trim($providerReference);
        if ($providerReference === '') {
            throw new InvalidArgumentException('Settled result requires a provider reference.');
        }

        return new self('settled', $providerReference, null);
    }

    /**
     * 可重试结果：请求明确未发出（如连接失败），重试不会造成重复入金。
     *
     * @param string $errorCode 非空错误码，用于日志与出箱记录。
     * @return self 状态为 retryable_not_sent 的结果对象。
     * @throws InvalidArgumentException 错误码为空时抛出。
     */
    public static function retryableNotSent(string $errorCode): self
    {
        return new self('retryable_not_sent', null, self::requiredErrorCode($errorCode));
    }

    /**
     * 未知结果：远端可能已执行（超时/写失败），重试前必须先对账，避免重复入金。
     *
     * @param string $errorCode 非空错误码，用于日志与出箱记录。
     * @return self 状态为 unknown 的结果对象。
     * @throws InvalidArgumentException 错误码为空时抛出。
     */
    public static function unknown(string $errorCode): self
    {
        return new self('unknown', null, self::requiredErrorCode($errorCode));
    }

    /**
     * 拒绝结果：远端明确拒绝（如账户禁用），重试无意义，应转人工处理。
     *
     * @param string $errorCode 非空错误码，用于日志与出箱记录。
     * @return self 状态为 rejected 的结果对象。
     * @throws InvalidArgumentException 错误码为空时抛出。
     */
    public static function rejected(string $errorCode): self
    {
        return new self('rejected', null, self::requiredErrorCode($errorCode));
    }

    /**
     * 当前结果状态。
     *
     * @return string 'settled' | 'retryable_not_sent' | 'unknown' | 'rejected'。
     */
    public function status(): string
    {
        return $this->status;
    }

    /**
     * 供应商票据号；仅 settled 状态存在，其余状态为 null。
     *
     * @return string|null MT4 票据号。
     */
    public function providerReference(): ?string
    {
        return $this->providerReference;
    }

    /**
     * 错误码；仅失败状态存在，settled 为 null。
     *
     * @return string|null 用于日志与出箱记录的稳定错误码。
     */
    public function errorCode(): ?string
    {
        return $this->errorCode;
    }

    /**
     * 校验错误码非空：错误码用于日志、出箱记录与对账，缺失时失败关闭。
     *
     * @param string $errorCode 原始错误码。
     * @return string trim 后的错误码。
     * @throws InvalidArgumentException 错误码为空时抛出。
     */
    private static function requiredErrorCode(string $errorCode): string
    {
        $errorCode = trim($errorCode);
        if ($errorCode === '') {
            throw new InvalidArgumentException('Settlement failure result requires an error code.');
        }

        return $errorCode;
    }
}
