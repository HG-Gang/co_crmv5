<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/02
 * Time: 12:48
 */

/**
 * MT4 用户开户结果值对象。
 *
 * 文件功能：
 * - 封装 MT4 用户开户或对账的结果，支持五种状态：已处理（processed）、可重试未发送（retryable_not_sent）、未知（unknown）、已拒绝（rejected）、需人工对账（manual_reconcile_required）。
 *
 * 适用场景：
 * - 用户注册与对账流程中，将 MT4 服务响应映射为统一结果对象，供上层业务或 Saga 流程做决策。
 *
 * 入参例子：
 * - UserMt4ProvisioningResult::processed("TICKET123")
 * - UserMt4ProvisioningResult::manualReconcileRequired("account_group_mismatch")
 *
 * 返回值：
 * - 成功时 status() 返回 "processed"，providerReference() 返回 MT4 ticket。
 * - 失败时返回对应状态与错误码。
 *
 * 异常或失败场景：
 * - retryableNotSent / unknown / rejected / manualReconcileRequired 工厂方法传入空错误码时抛出 InvalidArgumentException。
 */

declare(strict_types=1);

namespace App\Services\Registration;

use InvalidArgumentException;

final class UserMt4ProvisioningResult
{
    /**
     * 结果状态：processed / retryable_not_sent / unknown / rejected / manual_reconcile_required 五选一。
     * 五态差异决定出箱记录走向——明确未发送可重试、结果不确定转对账、明确拒绝终止、
     * 对账耗尽转人工；状态语义被破坏会直接导致重复开户或用户无 MT4 账号。
     *
     * @var string
     */
    private $status;

    /**
     * MT4 票据号，仅 processed 状态可能存在（对账成功可无 ticket）。
     * 是“远端已开户”的外部证据，供对账与人工核查引用。
     *
     * @var string|null
     */
    private $providerReference;

    /**
     * 稳定错误码（如 transport_exception、account_group_mismatch），仅失败状态存在、processed 时为 null。
     * 供出箱记录落库 last_error 与日志追溯，不携带敏感信息。
     *
     * @var string|null
     */
    private $errorCode;

    /**
     * 私有构造：只能通过各工厂方法创建，保证状态字段不可变。
     *
     * @param string $status 结果状态标识。
     * @param string|null $providerReference MT4 票据号，仅 processed 存在。
     * @param string|null $errorCode 稳定错误码，仅失败状态存在。
     */
    private function __construct(string $status, string $providerReference = null, string $errorCode = null)
    {
        $this->status = $status;
        $this->providerReference = $providerReference;
        $this->errorCode = $errorCode;
    }

    /**
     * 已处理结果：开户或对账成功；providerReference 可选（对账可能无 ticket）。
     *
     * @param string|null $providerReference MT4 票据号；空值视为无引用。
     * @return self 状态为 processed 的结果对象。
     */
    public static function processed(string $providerReference = null): self
    {
        $providerReference = $providerReference === null ? null : trim($providerReference);

        return new self('processed', $providerReference === '' ? null : $providerReference, null);
    }

    /**
     * 可重试结果：请求明确未发出（如连接失败），重试不会造成重复开户。
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
     * 未知结果：远端可能已执行（超时/写失败），重试前必须先对账，避免重复开户。
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
     * 拒绝结果：远端明确拒绝（如账户禁用、分组不匹配），重试无意义，应转人工处理。
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
     * 需人工对账结果：自动重试无法恢复（次数耗尽/负载过期等），等待管理员介入。
     *
     * @param string $errorCode 非空错误码，用于日志与出箱记录。
     * @return self 状态为 manual_reconcile_required 的结果对象。
     * @throws InvalidArgumentException 错误码为空时抛出。
     */
    public static function manualReconcileRequired(string $errorCode): self
    {
        return new self('manual_reconcile_required', null, self::requiredErrorCode($errorCode));
    }

    /**
     * 当前结果状态。
     *
     * @return string 'processed' | 'retryable_not_sent' | 'unknown' | 'rejected' | 'manual_reconcile_required'。
     */
    public function status(): string
    {
        return $this->status;
    }

    /**
     * 供应商票据号；仅 processed 状态且网关返回 ticket 时存在，其余为 null。
     *
     * @return string|null MT4 票据号。
     */
    public function providerReference(): ?string
    {
        return $this->providerReference;
    }

    /**
     * 错误码；仅失败状态存在，processed 为 null。
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
            throw new InvalidArgumentException('MT4 provisioning failure requires an error code.');
        }

        return $errorCode;
    }
}
