<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/02
 * Time: 13:00
 */

/**
 * MT4 佣金划转资金操作网关。
 *
 * 文件功能：
 * - 通过 MT4 Manager 服务执行出金（withdraw）、入金（deposit）和补偿（compensate）操作，并返回统一的 CommissionTransferCommandResult。
 *
 * 适用场景：
 * - 佣金划转 Saga 流程中的资金流转阶段，需要从源账户扣款并存入目标账户。
 *
 * 入参例子：
 * - withdraw(12345, "100.00", "佣金划转-出金")
 * - deposit(67890, "100.00", "佣金划转-入金")
 *
 * 返回值：
 * - 成功时返回 CommissionTransferCommandResult::processed($ticket)。
 * - 失败时根据错误类型返回 retryableNotSent / unknown / rejected。
 *
 * 异常或失败场景：
 * - 网络异常（transport_exception）返回 unknown。
 * - 连接失败（connection_failed）返回 retryableNotSent。
 * - 业务拒绝或 ticket 无效时返回 rejected / unknown。
 */

declare(strict_types=1);

namespace App\Services\CommissionTransfer;

use App\Contracts\CommissionTransferFundingGateway;
use App\Services\Mt4ManagerService;
use Throwable;

final class Mt4CommissionTransferFundingGateway implements CommissionTransferFundingGateway
{
    /**
     * MT4 Manager 服务：封装出金/入金/补偿三类远端资金指令的协议调用。
     * 资金指令真实生效与否由它判定，unknown/processed/rejected 的分类直接决定 Saga 走重试还是人工；
     * 该依赖不可用时必须失败关闭，不得伪造资金结果。
     *
     * @var Mt4ManagerService
     */
    private $manager;

    /**
     * 构造函数注入 MT4 Manager 服务。
     *
     * @param Mt4ManagerService $manager 封装 MT4 出入金远端操作的 Manager 服务。
     */
    public function __construct(Mt4ManagerService $manager)
    {
        $this->manager = $manager;
    }

    /**
     * 执行 MT4 出金（佣金划转源账户扣款）。
     *
     * @param int $userId 源用户 ID（划转方代理商）。
     * @param string $amount 出金金额（十进制字符串）。
     * @param string $comment 出金备注（写入手单注释）。
     * @return CommissionTransferCommandResult 命令结果（processed/rejected/unknown/retryableNotSent）。
     * @throws Mt4SyncDisabledException 用户与 MT4 同步全局开关关闭时抛出。
     */
    public function withdraw(int $userId, string $amount, string $comment): CommissionTransferCommandResult
    {
        // 用户与 MT4 同步全局开关：关闭时拒绝执行远端操作（fail-closed，转人工对账）。
        \App\Services\Mt4SyncGate::assertUserSyncEnabled();

        return $this->command(function () use ($userId, $amount, $comment) {
            return $this->manager->withdrawal($userId, $amount, $comment);
        });
    }

    /**
     * 执行 MT4 入金（佣金划转目标账户入账）。
     *
     * @param int $userId 目标用户 ID（划转接收方直属代理/客户）。
     * @param string $amount 入金金额（十进制字符串）。
     * @param string $comment 入金备注（写入手单注释）。
     * @return CommissionTransferCommandResult 命令结果（processed/rejected/unknown/retryableNotSent）。
     * @throws Mt4SyncDisabledException 用户与 MT4 同步全局开关关闭时抛出。
     */
    public function deposit(int $userId, string $amount, string $comment): CommissionTransferCommandResult
    {
        // 用户与 MT4 同步全局开关：关闭时拒绝执行远端操作（fail-closed，转人工对账）。
        \App\Services\Mt4SyncGate::assertUserSyncEnabled();

        return $this->command(function () use ($userId, $amount, $comment) {
            return $this->manager->deposit($userId, $amount, $comment);
        });
    }

    /**
     * 执行 MT4 补偿入金（Saga 异常分支：向目标账户补款）。
     *
     * 说明：补偿语义上与入金一致，复用 deposit 实现。
     *
     * @param int $userId 补偿接收用户 ID。
     * @param string $amount 补偿金额（十进制字符串）。
     * @param string $comment 补偿备注。
     * @return CommissionTransferCommandResult 命令结果（processed/rejected/unknown/retryableNotSent）。
     * @throws Mt4SyncDisabledException 用户与 MT4 同步全局开关关闭时抛出。
     */
    public function compensate(int $userId, string $amount, string $comment): CommissionTransferCommandResult
    {
        // 用户与 MT4 同步全局开关：关闭时拒绝执行远端操作（fail-closed，转人工对账）。
        \App\Services\Mt4SyncGate::assertUserSyncEnabled();

        return $this->deposit($userId, $amount, $comment);
    }

    /**
     * 统一执行 MT4 命令并归一化响应。
     *
     * 逻辑说明：
     * - 调用闭包执行远端操作；
     * - status=ok 时提取 ticket 作为凭证，返回 processed；
     * - connection_failed 返回 retryableNotSent（可安全重试）；
     * - write_failed/read_timeout/transport 等不确定性错误返回 unknown（转人工）；
     * - 其余业务错误返回 rejected。
     *
     * @param callable $call 远端操作闭包。
     * @return CommissionTransferCommandResult 归一化后的命令结果。
     */
    private function command(callable $call): CommissionTransferCommandResult
    {
        try {
            $response = $call();
        } catch (Throwable $exception) {
            // 远端抛出异常：请求是否已送达未知，必须按不确定处理（unknown），禁止直接重试。
            return CommissionTransferCommandResult::unknown('transport_exception');
        }

        if (!is_array($response)) {
            return CommissionTransferCommandResult::unknown('malformed_response');
        }

        $status = $response['status'] ?? null;
        $errorCode = $response['error_code'] ?? '';
        if (!is_scalar($status) || !is_scalar($errorCode)) {
            return CommissionTransferCommandResult::unknown('malformed_response');
        }
        $status = strtolower(trim((string) $status));
        $errorCode = trim((string) $errorCode);
        if ($status === 'ok') {
            // ok 分支取 ticket 作为资金凭证（兼容 data[0] 包装）；凭证缺失视为响应损坏。
            $reference = $response['ticket'] ?? null;
            if ($reference === null && isset($response['data']) && is_array($response['data'])) {
                $reference = $response['data'][0] ?? null;
            }
            if (!is_scalar($reference)) {
                return CommissionTransferCommandResult::unknown('malformed_response');
            }

            try {
                return CommissionTransferCommandResult::processed((string) $reference);
            } catch (\InvalidArgumentException $exception) {
                // ticket 非正整数：可能是服务端返回了错误字段，结果不可信，转人工核对。
                return CommissionTransferCommandResult::unknown('invalid_provider_reference');
            }
        }
        if ($status !== 'error') {
            return CommissionTransferCommandResult::unknown('malformed_response');
        }
        if ($errorCode === 'connection_failed') {
            // 明确未送达：可安全重试，不存在重复扣款风险。
            return CommissionTransferCommandResult::retryableNotSent($errorCode);
        }
        if (in_array($errorCode, ['write_failed', 'read_timeout', 'malformed_response', 'transport', 'transport_exception'], true)) {
            // 写失败/超时等：指令可能已执行，结果不确定，转人工。
            return CommissionTransferCommandResult::unknown($errorCode);
        }

        return CommissionTransferCommandResult::rejected($errorCode !== '' ? $errorCode : 'provider_rejected');
    }
}
