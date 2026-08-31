<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/02
 * Time: 12:50
 */

/**
 * MT4 佣金划转账户快照网关。
 *
 * 文件功能：
 * - 通过 MT4 Manager 服务查询用户账户信息，提取余额并规范化为 CommissionTransferAccountSnapshotResult。
 *
 * 适用场景：
 * - 佣金划转流程中获取源账户或目标账户的余额快照，用于可用资金校验。
 *
 * 入参例子：
 * - snapshot(12345)
 *
 * 返回值：
 * - 成功时返回 CommissionTransferAccountSnapshotResult::confirmed($normalizedBalance)。
 * - 失败时根据错误类型返回 retryable 或 rejected 结果。
 *
 * 异常或失败场景：
 * - 网络异常或 MT4 响应格式不正确时返回 retryable。
 * - 账户 ID 不匹配或不可恢复的业务错误时返回 rejected。
 */

declare(strict_types=1);

namespace App\Services\CommissionTransfer;

use App\Contracts\CommissionTransferAccountSnapshotGateway;
use App\Services\Mt4ManagerService;
use Throwable;

final class Mt4CommissionTransferAccountSnapshotGateway implements CommissionTransferAccountSnapshotGateway
{
    /**
     * MT4 Manager 服务：本网关唯一的远端调用通道，负责账户信息（accountinfo）查询。
     * 快照的可用性完全取决于它——不可用时按失败关闭处理，绝不允许返回伪造余额参与资金校验。
     *
     * @var Mt4ManagerService
     */
    private $manager;

    /**
     * 注入 MT4 Manager 服务。
     *
     * @param Mt4ManagerService $manager MT4 账户信息查询服务,用于读取账户余额快照。
     */
    public function __construct(Mt4ManagerService $manager)
    {
        $this->manager = $manager;
    }

    /**
     * 查询用户 MT4 账户余额快照并归一化为统一结果。
     *
     * 失败语义：网络/格式类错误返回 retryable（未发生资金变动，可重试）；
     * 账户 ID 不匹配或业务性拒绝返回 rejected（转人工）；同步全局开关关闭时抛异常失败关闭。
     *
     * @param int $userId 用户 ID（与 MT4 login 一致）。
     * @return CommissionTransferAccountSnapshotResult 快照结果（confirmed/retryable/rejected）。
     * @throws \App\Services\Mt4SyncDisabledException 同步开关关闭时抛出。
     */
    public function snapshot(int $userId): CommissionTransferAccountSnapshotResult
    {
        // 用户与 MT4 同步全局开关：关闭时拒绝执行远端操作（fail-closed，转人工对账）。
        \App\Services\Mt4SyncGate::assertUserSyncEnabled();

        try {
            $response = $this->manager->getAccountInfo($userId);
        } catch (Throwable $exception) {
            // 网络层异常：资金未被读取，可安全重试。
            return CommissionTransferAccountSnapshotResult::retryable('transport_exception');
        }

        if (!is_array($response)) {
            return CommissionTransferAccountSnapshotResult::retryable('malformed_response');
        }

        // 响应结构非法（status 缺失/非标量）一律按可重试处理，不向业务抛脏数据。
        $status = $response['status'] ?? null;
        if (!is_scalar($status)) {
            return CommissionTransferAccountSnapshotResult::retryable('malformed_response');
        }
        $status = strtolower(trim((string) $status));

        if ($status === 'ok') {
            // ok 分支必须同时满足 err=0、账户 ID 匹配、余额存在且可规范化，缺一不可。
            if (!array_key_exists('err', $response)
                || !is_scalar($response['err'])
                || trim((string) $response['err']) !== '0') {
                return CommissionTransferAccountSnapshotResult::retryable('malformed_response');
            }
            // 防串户：MT4 返回的账户 ID 必须与请求用户一致，否则禁止采用该余额。
            $accountId = $response['account_id'] ?? ($response['acc'] ?? null);
            if (!is_scalar($accountId) || !$this->matchesUserId($accountId, $userId)) {
                return CommissionTransferAccountSnapshotResult::retryable(
                    is_scalar($accountId) ? 'account_mismatch' : 'malformed_response'
                );
            }
            $balance = $response['balance'] ?? ($response['bal'] ?? null);
            if (!is_scalar($balance)) {
                return CommissionTransferAccountSnapshotResult::retryable('malformed_response');
            }
            $normalized = $this->normalizeBalance((string) $balance);
            if ($normalized === null) {
                return CommissionTransferAccountSnapshotResult::retryable('malformed_response');
            }

            return CommissionTransferAccountSnapshotResult::confirmed($normalized);
        }

        // error 分支之外的状态视为响应格式异常，可重试。
        if ($status !== 'error') {
            return CommissionTransferAccountSnapshotResult::retryable('malformed_response');
        }

        // 连接/写失败/超时等瞬时错误可重试；其余业务错误（账户锁定等）拒绝转人工。
        $errorCode = $response['error_code'] ?? ($response['err'] ?? '');
        if (!is_scalar($errorCode) || trim((string) $errorCode) === '') {
            return CommissionTransferAccountSnapshotResult::retryable('malformed_response');
        }
        $errorCode = trim((string) $errorCode);
        if (in_array($errorCode, [
            'connection_failed',
            'write_failed',
            'read_timeout',
            'transport',
            'transport_exception',
            'malformed_response',
        ], true)) {
            return CommissionTransferAccountSnapshotResult::retryable($errorCode);
        }

        return CommissionTransferAccountSnapshotResult::rejected($errorCode);
    }

    /**
     * 校验 MT4 返回的账户 ID 与请求用户一致。
     *
     * @param mixed $value MT4 返回的 account_id/acc。
     * @param int $userId 请求用户 ID。
     * @return bool 完全一致为 true。
     */
    /** @param mixed $value */
    private function matchesUserId($value, int $userId): bool
    {
        $value = trim((string) $value);

        return ctype_digit($value) && (int) $value === $userId;
    }

    /**
     * 规范化余额：允许带正负号的两位小数格式；整数部分最多 16 位（DECIMAL(18,2) 上限）。
     *
     * @param string $value MT4 返回的原始余额。
     * @return string|null 规范化后的余额；格式非法或位数超限返回 null。
     */
    private function normalizeBalance(string $value): ?string
    {
        $value = trim($value);
        if (!preg_match('/^[+-]?[0-9]+(?:\.[0-9]{1,2})?$/D', $value)) {
            return null;
        }
        $negative = substr($value, 0, 1) === '-';
        if ($negative || substr($value, 0, 1) === '+') {
            $value = substr($value, 1);
        }
        [$whole, $fraction] = array_pad(explode('.', $value, 2), 2, '');
        $whole = ltrim($whole, '0') ?: '0';
        if (strlen($whole) > 16) {
            return null;
        }
        $normalized = $whole . '.' . str_pad($fraction, 2, '0');

        return $negative && $normalized !== '0.00' ? '-' . $normalized : $normalized;
    }
}
