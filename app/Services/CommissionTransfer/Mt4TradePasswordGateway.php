<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/02
 * Time: 12:53
 */

/**
 * MT4 交易密码验证网关。
 *
 * 文件功能：
 * - 通过 MT4 Manager 服务验证用户的交易密码，并返回 TradePasswordVerificationResult。
 *
 * 适用场景：
 * - 佣金划转流程的安全验证阶段，确保操作者是账户合法持有者。
 *
 * 入参例子：
 * - verify(12345, "user_trade_password")
 *
 * 返回值：
 * - 密码正确时返回 TradePasswordVerificationResult::verified()。
 * - 失败时根据错误类型返回 retryableNotSent / unknown / rejected。
 *
 * 异常或失败场景：
 * - 网络异常或响应格式错误返回 unknown。
 * - 账户 ID 不匹配返回 unknown。
 * - 密码错误等业务拒绝原因返回 rejected。
 */

declare(strict_types=1);

namespace App\Services\CommissionTransfer;

use App\Contracts\TradePasswordGateway;
use App\Services\Mt4ManagerService;
use Throwable;

final class Mt4TradePasswordGateway implements TradePasswordGateway
{
    /**
     * MT4 Manager 服务：提供 verifyPassword 协议调用的底层通道。
     * 交易密码校验的成败分类（verified/rejected/unknown）完全由它的返回决定，
     * 缺失时 Saga 的 verify 步骤无法完成，转账链路必须失败关闭。
     *
     * @var Mt4ManagerService
     */
    private $manager;

    /**
     * 构造 MT4 交易密码验证网关。
     *
     * @param Mt4ManagerService $manager 底层 MT4 Manager 服务，提供 verifyPassword 协议调用。
     */
    public function __construct(Mt4ManagerService $manager)
    {
        $this->manager = $manager;
    }

    /**
     * 验证用户 MT4 交易密码。
     *
     * 失败语义：连接失败可安全重试（retryable_not_sent）；超时/写失败/传输异常及
     * 账户 ID 不匹配返回 unknown（禁止重试，转人工）；密码错误等业务拒绝返回 rejected。
     * 安全说明：密码明文仅作为远端调用参数，不落库、不进日志。
     *
     * @param int $userId 用户 ID（与 MT4 login 一致）。
     * @param string $password 交易密码明文。
     * @return TradePasswordVerificationResult 验证结果。
     * @throws \App\Services\Mt4SyncDisabledException 同步开关关闭时抛出。
     */
    public function verify(int $userId, string $password): TradePasswordVerificationResult
    {
        // 用户与 MT4 同步全局开关：关闭时拒绝执行远端操作（fail-closed，转人工对账）。
        \App\Services\Mt4SyncGate::assertUserSyncEnabled();

        try {
            $response = $this->manager->verifyPassword($userId, $password);
        } catch (Throwable $exception) {
            // 远端异常：验证结果未知，禁止假设验证失败而进入拒绝终态，转人工。
            return TradePasswordVerificationResult::unknown('transport_exception');
        }

        if (!is_array($response)) {
            return TradePasswordVerificationResult::unknown('malformed_response');
        }

        $status = $response['status'] ?? null;
        if (!is_scalar($status)) {
            return TradePasswordVerificationResult::unknown('malformed_response');
        }
        $status = strtolower(trim((string) $status));

        if ($status === 'ok') {
            // ok 分支必须 err=0 且返回 acc 与请求用户一致，防止验证串户。
            if (!array_key_exists('err', $response)
                || !is_scalar($response['err'])
                || trim((string) $response['err']) !== '0'
                || !array_key_exists('acc', $response)
                || !is_scalar($response['acc'])) {
                return TradePasswordVerificationResult::unknown('malformed_response');
            }
            if (!$this->matchesUserId($response['acc'], $userId)) {
                return TradePasswordVerificationResult::unknown('account_mismatch');
            }

            return TradePasswordVerificationResult::verified();
        }

        // error 分支之外的状态视为响应格式异常，结果不确定。
        if ($status !== 'error') {
            return TradePasswordVerificationResult::unknown('malformed_response');
        }

        // connection_failed 明确未送达可重试；写失败/超时/传输异常不确定转人工；其余为业务拒绝。
        $errorCode = $response['error_code'] ?? ($response['err'] ?? '');
        if (!is_scalar($errorCode) || trim((string) $errorCode) === '') {
            return TradePasswordVerificationResult::unknown('malformed_response');
        }
        $errorCode = trim((string) $errorCode);
        if ($errorCode === 'connection_failed') {
            return TradePasswordVerificationResult::retryableNotSent($errorCode);
        }
        if (in_array($errorCode, ['write_failed', 'read_timeout', 'transport', 'transport_exception'], true)) {
            return TradePasswordVerificationResult::unknown($errorCode);
        }

        return TradePasswordVerificationResult::rejected($errorCode);
    }

    /**
     * 校验 MT4 返回的账户 ID 与请求用户一致。
     *
     * @param mixed $value MT4 返回的 acc 字段。
     * @param int $userId 请求用户 ID。
     * @return bool 完全一致为 true。
     */
    /** @param mixed $value */
    private function matchesUserId($value, int $userId): bool
    {
        $value = trim((string) $value);

        return ctype_digit($value) && (int) $value === $userId;
    }
}
