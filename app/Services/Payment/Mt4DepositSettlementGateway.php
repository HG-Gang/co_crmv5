<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/02
 * Time: 13:00
 */

declare(strict_types=1);

namespace App\Services\Payment;

use App\Contracts\DepositSettlementGateway;
use App\Services\Mt4ManagerService;
use Throwable;

/**
 * MT4 充值结算网关。
 *
 * 文件功能：
 * - 调用 MT4 Manager API 执行用户入金操作。
 * - 解析 MT4 原始响应为标准 DepositSettlementResult。
 *
 * 适用场景：
 * - 支付回调成功后，结算出箱扫描器调用此网关将充值金额记入用户 MT4 账户。
 *
 * 入参例子：
 * - userId: 10001（MT4 登录号）。
 * - amount: '100.00'（两位小数字符串）。
 * - comment: 'DEP20240801120000'（本地订单号）。
 *
 * 返回值：
 * - DepositSettlementResult：settled / retryable_not_sent / unknown / rejected。
 *
 * 异常或失败场景：
 * - 网络异常：未知结果（transport_exception）。
 * - 响应非法：未知结果（malformed_response / invalid_provider_reference）。
 * - connection_failed：可重试。
 * - write_failed / read_timeout：未知。
 * - 其他错误：明确拒绝。
 */
final class Mt4DepositSettlementGateway implements DepositSettlementGateway
{
    /**
     * MT4 Manager Socket 服务：充值入金远端命令的唯一执行通道，负责与旧协议接口通信。
     * 其响应分类（connection_failed=可重试、write/read 异常=unknown、其他=拒绝）是结算出箱状态机的输入；
     * 分类错误会造成重复入金或丢单，因此不可用时必须失败关闭而非伪造结果。
     *
     * @var Mt4ManagerService
     */
    private $manager;

    /**
     * 构造 MT4 充值结算网关。
     *
     * @param Mt4ManagerService $manager 底层 MT4 Manager Socket 服务，负责与旧协议接口通信。
     */
    public function __construct(Mt4ManagerService $manager)
    {
        $this->manager = $manager;
    }

    /**
     * 执行充值入金并映射为统一结果。
     *
     * @param int $userId 目标 MT4 登录号。
     * @param string $amount 两位小数的入金金额字符串。
     * @param string $comment 入金备注（通常为本地订单号）。
     * @return DepositSettlementResult 状态语义见 DepositSettlementResult：settled / retryable_not_sent / unknown / rejected。
     */
    public function deposit(int $userId, string $amount, string $comment): DepositSettlementResult
    {
        // 用户与 MT4 同步全局开关：关闭时拒绝执行远端操作（fail-closed，转人工对账）。
        \App\Services\Mt4SyncGate::assertUserSyncEnabled();

        // 传输层异常无法判断远端是否已入账，统一按未知处理，重试前必须先对账，避免重复入金。
        try {
            $response = $this->manager->deposit($userId, $amount, $comment);
        } catch (Throwable $exception) {
            return DepositSettlementResult::unknown('transport_exception');
        }

        // 响应不是数组说明协议被破坏，同样按未知处理，防止伪造成功。
        if (!is_array($response)) {
            return DepositSettlementResult::unknown('malformed_response');
        }

        $status = strtolower(trim((string) ($response['status'] ?? '')));
        $errorCode = trim((string) ($response['error_code'] ?? ''));
        // status=ok 时必须拿到正整数的票据号才算真正入账；引用缺失或非法视为未知，不能伪造成功。
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
