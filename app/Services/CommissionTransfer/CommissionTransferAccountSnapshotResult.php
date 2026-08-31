<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/02
 * Time: 08:50
 */

/**
 * 佣金划转账户快照结果值对象。
 *
 * 文件功能：
 * - 封装 MT4 账户余额快照的查询结果，支持三种状态：已确认（confirmed）、可重试（retryable）、已拒绝（rejected）。
 * - 实例不可变：状态与余额/错误码在构造时固定，只允许通过工厂方法创建。
 *
 * 状态语义：
 * - confirmed：余额已成功读取并规范化，balance() 可安全用于资金校验。
 * - retryable：读取未成功且未发生资金变动，可安全重试。
 * - rejected：账户不可用或业务上不可重试，需转入人工处理。
 *
 * 适用场景：
 * - 佣金划转流程中获取源账户或目标账户余额快照，为资金操作前的校验提供数据。
 *
 * 入参例子：
 * - CommissionTransferAccountSnapshotResult::confirmed("15000.00")
 * - CommissionTransferAccountSnapshotResult::retryable("read_timeout")
 *
 * 返回值：
 * - 成功时返回 status() 为 "confirmed" 的结果，balance() 返回余额字符串。
 * - 失败时返回对应的可重试或拒绝结果对象。
 *
 * 异常或失败场景：
 * - retryable/rejected 工厂方法传入空错误码时抛出 InvalidArgumentException。
 */

declare(strict_types=1);

namespace App\Services\CommissionTransfer;

use InvalidArgumentException;

final class CommissionTransferAccountSnapshotResult
{
    /**
     * 快照状态：confirmed / retryable / rejected 三选一。
     * 三态差异：confirmed 可安全用 balance() 做资金校验；retryable 表示未发生资金变动可重试；
     * rejected 表示账户不可用或业务上不可重试、必须转人工。对象不可变，状态一经构造不可改写。
     *
     * @var string
     */
    private $status;

    /**
     * 规范化后的余额（两位小数字符串），仅 status=confirmed 时非空。
     * 失败状态强制为 null，防止把未读取成功的余额误用于资金校验。
     *
     * @var string|null
     */
    private $balance;

    /**
     * 失败时的错误码（如 read_timeout），仅 retryable/rejected 状态非空；confirmed 时为 null。
     * 供 Saga 日志与对账证据区分“为什么没拿到余额”，不携带敏感信息。
     *
     * @var string|null
     */
    private $errorCode;

    /**
     * 私有构造：保证只能通过工厂方法创建，且状态与数据在创建后不可变。
     *
     * @param string $status confirmed / retryable / rejected。
     * @param string|null $balance 已确认时的余额（两位小数字符串），失败状态为 null。
     * @param string|null $errorCode 失败状态时的错误码，已确认时为 null。
     */
    private function __construct(string $status, string $balance = null, string $errorCode = null)
    {
        $this->status = $status;
        $this->balance = $balance;
        $this->errorCode = $errorCode;
    }

    /**
     * 创建已确认快照：余额已从 MT4 成功读取并规范化。
     *
     * @param string $balance 两位小数规范化余额，如 "15000.00"。
     * @return self 已确认结果对象。
     */
    public static function confirmed(string $balance): self
    {
        return new self('confirmed', $balance, null);
    }

    /**
     * 创建可重试快照：读取未成功，但可安全重试（不存在已生效的资金变动）。
     *
     * @param string $errorCode 错误码，供调用方记录重试原因。
     * @return self 可重试结果对象。
     * @throws InvalidArgumentException 错误码为空时抛出。
     */
    public static function retryable(string $errorCode): self
    {
        return new self('retryable', null, self::requiredErrorCode($errorCode));
    }

    /**
     * 创建已拒绝快照：账户不可用或业务上不可重试。
     *
     * @param string $errorCode 错误码，供调用方记录拒绝原因。
     * @return self 已拒绝结果对象。
     * @throws InvalidArgumentException 错误码为空时抛出。
     */
    public static function rejected(string $errorCode): self
    {
        return new self('rejected', null, self::requiredErrorCode($errorCode));
    }

    /**
     * 当前快照状态。
     *
     * @return string confirmed / retryable / rejected。
     */
    public function status(): string
    {
        return $this->status;
    }

    /**
     * 已确认快照的余额。
     *
     * @return string|null 已确认时为两位小数字符串，失败状态为 null。
     */
    public function balance(): ?string
    {
        return $this->balance;
    }

    /**
     * 失败状态的错误码。
     *
     * @return string|null 失败状态为错误码，已确认时为 null。
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
            throw new InvalidArgumentException('Account snapshot failure requires an error code.');
        }

        return $errorCode;
    }
}
