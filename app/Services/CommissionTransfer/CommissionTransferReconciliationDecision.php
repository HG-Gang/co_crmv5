<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/02
 * Time: 08:51
 */

declare(strict_types=1);

namespace App\Services\CommissionTransfer;

/**
 * 佣金转账对账决策结果值对象。
 *
 * 文件功能：
 * - 封装对账策略（CommissionTransferReconciliationPolicy）的评估结果。
 * - 判断是否允许执行、返回终态状态或拒绝原因码。
 * - 实例不可变：允许与否、终态与错误码在构造时固定，只允许通过工厂方法创建。
 *
 * 状态语义：
 * - allowed：证据合法，可执行对应终态（completed / compensated / rejected）。
 * - denied：证据非法，附带拒绝原因码，调用方不得继续执行任何记账动作（失败关闭）。
 *
 * 适用场景：
 * - 管理员对账提交证据后，策略评估生成此对象，由 ReconciliationService 据此执行或拒绝。
 *
 * 入参例子：
 * - allowed('completed')：允许执行，终态为 completed。
 * - denied('invalid_origin_step')：拒绝，附错误码。
 *
 * 返回值：
 * - isAllowed(): bool
 * - terminalStatus(): 'completed' | 'compensated' | 'rejected' | null
 * - errorCode(): string | null
 */
final class CommissionTransferReconciliationDecision
{
    /** @var string|null 允许时的终态；拒绝时为 null。 */
    private $terminalStatus;

    /** @var string|null 拒绝原因码；允许时为 null。 */
    private $errorCode;

    /**
     * 私有构造：只允许通过 allowed/denied 工厂创建，保证决策语义单一来源。
     *
     * @param string|null $terminalStatus 终态（completed/compensated/rejected）。
     * @param string|null $errorCode 拒绝原因码。
     */
    private function __construct(string $terminalStatus = null, string $errorCode = null)
    {
        $this->terminalStatus = $terminalStatus;
        $this->errorCode = $errorCode;
    }

    /**
     * 创建允许决策：证据通过全部校验，可执行指定终态。
     *
     * @param string $terminalStatus 终态：completed / compensated / rejected。
     * @return self 允许决策对象。
     */
    public static function allowed(string $terminalStatus): self
    {
        return new self($terminalStatus, null);
    }

    /**
     * 创建拒绝决策：证据非法，禁止执行任何记账动作。
     *
     * @param string $errorCode 拒绝原因码。
     * @return self 拒绝决策对象。
     */
    public static function denied(string $errorCode): self
    {
        return new self(null, $errorCode);
    }

    /**
     * 决策是否允许执行。
     *
     * @return bool 允许为 true（此时 terminalStatus() 非 null）。
     */
    public function isAllowed(): bool
    {
        return $this->terminalStatus !== null;
    }

    /**
     * 允许时的终态。
     *
     * @return string|null completed / compensated / rejected；拒绝决策为 null。
     */
    public function terminalStatus(): ?string
    {
        return $this->terminalStatus;
    }

    /**
     * 拒绝时的原因码。
     *
     * @return string|null 拒绝决策为原因码；允许决策为 null。
     */
    public function errorCode(): ?string
    {
        return $this->errorCode;
    }
}
