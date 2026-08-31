<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/02
 * Time: 08:50
 */

/**
 * 佣金划转 MT4 指令执行结果值对象。
 *
 * 文件功能：
 * - 封装 MT4 出金/入金/补偿指令的执行结果，支持四种状态：已处理（processed）、可重试未发送（retryable_not_sent）、未知（unknown）、已拒绝（rejected）。
 * - 实例不可变：状态与凭证/错误码在构造时固定，只允许通过工厂方法创建。
 *
 * 状态语义：
 * - processed：MT4 已受理并返回 ticket，资金已发生变动，后续必须完成对账。
 * - retryable_not_sent：请求未送达 MT4，可安全重试，不存在重复扣款风险。
 * - unknown：结果不确定（超时/写失败等），禁止直接重试，需人工核对 ticket。
 * - rejected：业务侧明确拒绝，进入终态或补偿分支。
 *
 * 适用场景：
 * - 佣金划转 Saga 流程中，资金网关将 MT4 指令响应映射为统一的结果对象，供 Saga 协调器做补偿与重试决策。
 *
 * 入参例子：
 * - CommissionTransferCommandResult::processed("12345")
 * - CommissionTransferCommandResult::rejected("insufficient_funds")
 *
 * 返回值：
 * - 成功时返回 CommissionTransferCommandResult 对象，status() 为 "processed"，providerReference() 为 MT4 ticket 编号。
 * - 失败时根据错误类型返回对应的失败状态对象。
 *
 * 异常或失败场景：
 * - processed 工厂方法传入非正整数引用时抛出 InvalidArgumentException。
 * - 失败状态工厂方法传入空错误码时抛出 InvalidArgumentException。
 */

declare(strict_types=1);

namespace App\Services\CommissionTransfer;

use InvalidArgumentException;

final class CommissionTransferCommandResult
{
    /** @var string 结果状态：processed/retryable_not_sent/unknown/rejected。 */
    private $status;

    /** @var string|null MT4 凭证引用（仅 processed 有值）。 */
    private $providerReference;

    /** @var string|null 失败错误码（仅失败状态有值）。 */
    private $errorCode;

    /**
     * 私有构造：保证只能通过工厂方法创建，且状态与数据在创建后不可变。
     *
     * @param string $status 结果状态。
     * @param string|null $providerReference MT4 凭证引用（仅 processed 有值）。
     * @param string|null $errorCode 失败错误码（仅失败状态有值）。
     */
    private function __construct(string $status, string $providerReference = null, string $errorCode = null)
    {
        $this->status = $status;
        $this->providerReference = $providerReference;
        $this->errorCode = $errorCode;
    }

    /**
     * 创建已处理结果。
     *
     * @param string $providerReference MT4 凭证（正整数 ticket）。
     * @return self 已处理结果对象。
     * @throws InvalidArgumentException 凭证为空或非正整数时抛出。
     */
    public static function processed(string $providerReference): self
    {
        $reference = trim($providerReference);
        if ($reference === '' || !ctype_digit($reference) || (int) $reference <= 0) {
            throw new InvalidArgumentException('A processed MT4 command requires a positive reference.');
        }

        return new self('processed', $reference, null);
    }

    /**
     * 创建可重试未发送结果（连接失败等可安全重试场景）。
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
     * 创建未知结果（结果不确定，需人工核对）。
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
     * 创建已拒绝结果（业务侧明确拒绝）。
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
     * 获取结果状态。
     *
     * @return string processed/retryable_not_sent/unknown/rejected。
     */
    public function status(): string
    {
        return $this->status;
    }

    /**
     * 获取 MT4 凭证引用。
     *
     * @return string|null 已处理时为 ticket 编号，否则为 null。
     */
    public function providerReference(): ?string
    {
        return $this->providerReference;
    }

    /**
     * 获取失败错误码。
     *
     * @return string|null 失败时为错误码，成功时为 null。
     */
    public function errorCode(): ?string
    {
        return $this->errorCode;
    }

    /**
     * 校验并规范化错误码（禁止为空）。
     *
     * @param string $errorCode 原始错误码。
     * @return string 去空格后的错误码。
     * @throws InvalidArgumentException 错误码为空时抛出。
     */
    private static function requiredErrorCode(string $errorCode): string
    {
        $errorCode = trim($errorCode);
        if ($errorCode === '') {
            throw new InvalidArgumentException('A failed MT4 command requires an error code.');
        }

        return $errorCode;
    }
}
