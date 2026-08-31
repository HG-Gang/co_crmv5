<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/02
 * Time: 12:57
 */

declare(strict_types=1);

namespace App\Services\Payment;

use App\Contracts\DepositRefundGateway;
use App\Services\Mt4ManagerService;
use Throwable;

/**
 * MT4 充值退款网关。
 *
 * 文件功能：
 * - 调用 MT4 Manager API 对用户执行出金退款操作（支付回调为 refunded 时）。
 *
 * 适用场景：
 * - 支付网关回调通知退款后，退款出箱扫描器调用此网关从 MT4 账户扣回金额。
 *
 * 入参例子：
 * - userId: 10001
 * - amount: '100.00'
 * - comment: 'REFUND_DEP20240801120000'
 *
 * 返回值：
 * - DepositSettlementResult：settled / retryable_not_sent / unknown / rejected。
 */
final class Mt4DepositRefundGateway implements DepositRefundGateway
{
    /**
     * MT4 Manager Socket 服务：充值退款（从 MT4 账户扣回金额）远端命令的唯一执行通道。
     * 退款是资金回退操作，依赖它的结果分类（settled/unknown/...）决定出箱记录走向；不可用时必须失败关闭。
     *
     * @var Mt4ManagerService
     */
    private $manager;

    /**
     * 构造 MT4 充值退款网关。
     *
     * @param Mt4ManagerService $manager MT4 管理服务，提供远端出金退款命令。
     */
    public function __construct(Mt4ManagerService $manager)
    {
        $this->manager = $manager;
    }

    /**
     * 执行充值退款（出金）并映射为统一结果。
     *
     * @param int $userId 目标 MT4 登录号。
     * @param string $amount 两位小数的退款金额字符串。
     * @param string $comment 退款备注（通常为本地订单号）。
     * @return DepositSettlementResult 状态语义见 DepositSettlementResult：settled / retryable_not_sent / unknown / rejected。
     */
    public function refund(int $userId, string $amount, string $comment): DepositSettlementResult
    {
        // 用户与 MT4 同步全局开关：关闭时拒绝执行远端操作（fail-closed，转人工对账）。
        \App\Services\Mt4SyncGate::assertUserSyncEnabled();

        // 传输层异常无法判断远端是否已扣款，统一按未知处理；退款不能盲目重试，否则可能重复扣款。
        try {
            $response = $this->manager->withdrawal($userId, $amount, $comment);
        } catch (Throwable $exception) {
            return DepositSettlementResult::unknown('transport_exception');
        }

        // 响应不是数组说明协议被破坏，同样按未知处理，防止伪造成功。
        if (!is_array($response)) {
            return DepositSettlementResult::unknown('malformed_response');
        }

        $status = strtolower(trim((string) ($response['status'] ?? '')));
        $errorCode = trim((string) ($response['error_code'] ?? ''));
        // status=ok 时必须拿到正整数的票据号才算真正扣款；引用缺失或非法视为未知，不能伪造成功。
        if ($status === 'ok') {
            $reference = trim((string) ($response['ticket'] ?? ($response['data'][0] ?? '')));
            if ($reference === '' || !ctype_digit($reference) || (int) $reference <= 0) {
                return DepositSettlementResult::unknown('invalid_provider_reference');
            }

            return DepositSettlementResult::settled($reference);
        }

        // 错误码分类：连接失败明确未发送可安全重试；写失败/超时结果不确定；其余为明确拒绝。
        if ($errorCode === 'connection_failed') {
            return DepositSettlementResult::retryableNotSent($errorCode);
        }
        if (in_array($errorCode, ['write_failed', 'read_timeout'], true)) {
            return DepositSettlementResult::unknown($errorCode);
        }

        return DepositSettlementResult::rejected($errorCode !== '' ? $errorCode : 'provider_rejected');
    }
}
