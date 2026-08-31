<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/02
 * Time: 12:47
 */

declare(strict_types=1);

namespace App\Services\Payment;

use App\Contracts\CreditSettlementGateway;
use App\Services\Mt4ManagerService;
use Throwable;

/**
 * MT4 信用金入金网关。
 *
 * 文件功能：
 * - 调用 MT4 Manager API 对用户执行信用金入金操作。
 * - 解析响应为标准 DepositSettlementResult。
 *
 * 适用场景：
 * - 后台信用金充值（非正常支付渠道充值）时调用。
 *
 * 入参例子：
 * - userId: 10001
 * - amount: '500.00'
 * - comment: 'CREDIT20240801120000'
 *
 * 返回值：
 * - DepositSettlementResult：settled / retryable_not_sent / unknown / rejected。
 */
final class Mt4CreditSettlementGateway implements CreditSettlementGateway
{
    /**
     * MT4 Manager Socket 服务：信用金入金远端命令的唯一执行通道。
     * 远端结果未知（写超时/读超时）时网关必须归类为 unknown 失败关闭，依赖它的响应分类保证不重复发放信用。
     *
     * @var Mt4ManagerService
     */
    private $manager;

    /**
     * 构造信用金入金网关。
     *
     * @param Mt4ManagerService $manager MT4 Manager 服务，负责与远端执行信用金入金调用。
     */
    public function __construct(Mt4ManagerService $manager)
    {
        $this->manager = $manager;
    }

    /**
     * 执行信用金入金并映射为统一结果。
     *
     * @param int $userId 目标 MT4 登录号。
     * @param string $amount 两位小数的入金金额字符串。
     * @param string $comment 入金备注（通常为本地订单号）。
     * @return DepositSettlementResult 状态语义见 DepositSettlementResult：settled / retryable_not_sent / unknown / rejected。
     */
    public function creditIn(int $userId, string $amount, string $comment): DepositSettlementResult
    {
        // 用户与 MT4 同步全局开关：关闭时拒绝执行远端操作（fail-closed，转人工对账）。
        \App\Services\Mt4SyncGate::assertUserSyncEnabled();

        // 传输层异常（连接失败/超时）无法判断远端是否已执行，统一按未知处理，重试前必须先对账。
        try {
            $response = $this->manager->creditIn($userId, $amount, $comment);
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
