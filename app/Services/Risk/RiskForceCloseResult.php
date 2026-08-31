<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/02
 * Time: 13:00
 */

declare(strict_types=1);

namespace App\Services\Risk;

/**
 * 风控强制平仓结果值对象。
 *
 * 文件功能：
 * - 封装 MT4 风控强平调用的返回状态，区分成功 / 可重试 / 未知 / 拒绝四种结果。
 * - 提供统一的状态查询方法供调用方判断后续处理路径。
 *
 * 适用场景：
 * - Mt4RiskForceCloseGateway 调用 MT4 强平接口后构造此对象返回。
 * - 风控任务根据 result.status 决定是否重试或记录为失败。
 *
 * 入参例子：
 * - closed('TICKET123456')：强平成功，返回单号。
 * - retryableNotSent('connection_failed')：连接失败，可重试。
 * - unknown('read_timeout')：结果不确定。
 * - rejected('account_disabled')：明确拒绝。
 *
 * 返回值：
 * - status(): 'closed' | 'retryable_not_sent' | 'unknown' | 'rejected'
 * - providerReference(): 强平成功时返回 MT4 票据号，否则为 null。
 * - errorCode(): 失败时返回错误码，成功时为 null。
 */
final class RiskForceCloseResult
{
    private string $status;
    private ?string $providerReference;
    private ?string $errorCode;

    /**
     * 私有构造函数：禁止外部直接实例化，只能通过四个工厂方法创建。
     *
     * @param string $status 结果状态，closed/retryable_not_sent/unknown/rejected 之一。
     * @param string|null $providerReference 平仓票据引用，仅 closed 状态非空。
     * @param string|null $errorCode 错误码，仅失败状态非空。
     */
    private function __construct(string $status, string $providerReference = null, string $errorCode = null)
    {
        $this->status = $status;
        $this->providerReference = $providerReference;
        $this->errorCode = $errorCode;
    }

    /**
     * 已平仓结果：要求非空票据引用；空引用不伪造成功，转为 unknown(invalid_provider_reference)。
     *
     * @param string $providerReference 平仓票据引用。
     * @return self 状态为 closed 或 unknown 的结果对象。
     */
    public static function closed(string $providerReference): self
    {
        $reference = trim($providerReference);
        if ($reference === '') {
            return self::unknown('invalid_provider_reference');
        }

        return new self('closed', $reference, null);
    }

    /**
     * 可重试结果：请求明确未发出（如连接失败），重试不会造成重复平仓。
     *
     * @param string $errorCode 错误码，用于日志与出箱记录。
     * @return self 状态为 retryable_not_sent 的结果对象。
     */
    public static function retryableNotSent(string $errorCode): self
    {
        return new self('retryable_not_sent', null, self::requiredErrorCode($errorCode));
    }

    /**
     * 未知结果：远端可能已执行（超时/写失败），重试前必须先对账，避免重复指令。
     *
     * @param string $errorCode 错误码，用于日志与出箱记录。
     * @return self 状态为 unknown 的结果对象。
     */
    public static function unknown(string $errorCode): self
    {
        return new self('unknown', null, self::requiredErrorCode($errorCode));
    }

    /**
     * 拒绝结果：远端明确拒绝（如账户禁用），重试无意义，应转人工处理。
     *
     * @param string $errorCode 错误码，用于日志与出箱记录。
     * @return self 状态为 rejected 的结果对象。
     */
    public static function rejected(string $errorCode): self
    {
        return new self('rejected', null, self::requiredErrorCode($errorCode));
    }

    /**
     * 当前结果状态。
     *
     * @return string 'closed' | 'retryable_not_sent' | 'unknown' | 'rejected'。
     */
    public function status(): string
    {
        return $this->status;
    }

    /**
     * 平仓票据引用；仅 closed 状态存在，其余为 null。
     *
     * @return string|null MT4 票据号。
     */
    public function providerReference(): ?string
    {
        return $this->providerReference;
    }

    /**
     * 错误码；仅失败状态存在，closed 为 null。
     *
     * @return string|null 用于日志与出箱记录的稳定错误码。
     */
    public function errorCode(): ?string
    {
        return $this->errorCode;
    }

    /**
     * 便捷判断：结果是否为已平仓。
     *
     * @return bool 状态为 closed 返回 true。
     */
    public function isClosed(): bool
    {
        return $this->status === 'closed';
    }

    /**
     * 归一化错误码：空错误码回退为 'provider_rejected'。
     *
     * 与其它结果对象不同，这里不回抛异常：强平调用方需要兜底状态来继续决策，回退码保证错误码字段恒非空。
     *
     * @param string $errorCode 原始错误码。
     * @return string 非空错误码。
     */
    private static function requiredErrorCode(string $errorCode): string
    {
        $code = trim($errorCode);

        return $code !== '' ? $code : 'provider_rejected';
    }
}
