<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/02
 * Time: 12:53
 */

/**
 * MT4 出金退款网关。
 *
 * 文件功能：
 * - 通过 MT4 Manager 服务执行出金退款（通过 deposit 方式将资金退还至用户账户），并返回 WithdrawalFundingResult。
 *
 * 适用场景：
 * - 出金失败或被拒绝后需要将资金退回用户 MT4 账户的场景。
 *
 * 入参例子：
 * - refund(12345, "500.00", "出金退款-订单号XXX")
 *
 * 返回值：
 * - 成功时返回 WithdrawalFundingResult::debited($ticket)。
 * - 失败时根据错误类型返回 retryableNotSent / unknown / rejected。
 *
 * 异常或失败场景：
 * - 网络异常或响应格式错误返回 unknown。
 * - 连接失败返回 retryableNotSent。
 * - ticket 无效或业务拒绝时返回 unknown / rejected。
 */

declare(strict_types=1);

namespace App\Services\Withdrawal;

use App\Contracts\WithdrawalRefundGateway;
use App\Services\Mt4ManagerService;
use Throwable;

final class Mt4WithdrawalRefundGateway implements WithdrawalRefundGateway
{
    /**
     * MT4 Manager 服务：出金退款（deposit 方式退回资金）远端命令的唯一执行通道。
     * 退款是资金回补操作，依赖其结果分类决定出箱记录走向；不可用时必须失败关闭，
     * 不得伪造退款成功造成账实不符。
     *
     * @var Mt4ManagerService
     */
    private $manager;

    /**
     * 构造 MT4 出金退款网关。
     *
     * @param Mt4ManagerService $manager 底层 MT4 Manager 服务，提供 deposit 协议调用。
     */
    public function __construct(Mt4ManagerService $manager)
    {
        $this->manager = $manager;
    }

    /**
     * 执行出金退款（通过 MT4 deposit 将资金退还至用户账户）。
     *
     * 失败语义：connection_failed 明确未送达可重试；写失败/超时/传输异常返回 unknown
     * （退款是否生效未知，禁止重试，转人工核对）；业务拒绝返回 rejected。
     *
     * @param int $userId 退款接收用户 ID（与 MT4 login 一致）。
     * @param string $amount 退款金额（两位小数字符串）。
     * @param string $comment 退款备注（写入手单注释）。
     * @return WithdrawalFundingResult debited / retryable_not_sent / unknown / rejected。
     * @throws \App\Services\Mt4SyncDisabledException 同步开关关闭时抛出。
     */
    public function refund(int $userId, string $amount, string $comment): WithdrawalFundingResult
    {
        // 用户与 MT4 同步全局开关：关闭时拒绝执行远端操作（fail-closed，转人工对账）。
        \App\Services\Mt4SyncGate::assertUserSyncEnabled();

        try {
            $response = $this->manager->deposit($userId, $amount, $comment);
        } catch (Throwable $exception) {
            // 远端异常：退款是否生效未知，按不确定处理。
            return WithdrawalFundingResult::unknown('transport_exception');
        }

        if (!is_array($response)) {
            return WithdrawalFundingResult::unknown('malformed_response');
        }
        $status = $response['status'] ?? null;
        $errorCode = $response['error_code'] ?? '';
        if (!is_scalar($status) || !is_scalar($errorCode)) {
            return WithdrawalFundingResult::unknown('malformed_response');
        }
        $status = strtolower(trim((string) $status));
        $errorCode = trim((string) $errorCode);
        if ($status === 'ok') {
            // ok 分支取 ticket 作为退款凭证（兼容 data[0] 包装）；必须为正整数，否则转人工。
            $ticket = array_key_exists('ticket', $response)
                ? $response['ticket']
                : (isset($response['data']) && is_array($response['data']) ? ($response['data'][0] ?? null) : null);
            if (!is_scalar($ticket)) {
                return WithdrawalFundingResult::unknown('malformed_response');
            }
            $reference = trim((string) $ticket);
            if ($reference === '' || !ctype_digit($reference) || (int) $reference <= 0) {
                return WithdrawalFundingResult::unknown('invalid_provider_reference');
            }

            return WithdrawalFundingResult::debited($reference);
        }
        if ($status !== 'error') {
            return WithdrawalFundingResult::unknown('malformed_response');
        }
        // 明确未送达才可重试；写失败/超时/传输异常不确定转人工；其余为业务拒绝。
        if ($errorCode === 'connection_failed') {
            return WithdrawalFundingResult::retryableNotSent($errorCode);
        }
        if (in_array($errorCode, ['write_failed', 'read_timeout', 'transport', 'transport_exception'], true)) {
            return WithdrawalFundingResult::unknown($errorCode);
        }

        return WithdrawalFundingResult::rejected($errorCode !== '' ? $errorCode : 'provider_rejected');
    }
}
