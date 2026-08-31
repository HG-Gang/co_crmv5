<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/02
 * Time: 12:50
 */

declare(strict_types=1);

namespace App\Services\Withdrawal;

use App\Contracts\WithdrawalFundingGateway;
use App\Services\Mt4ManagerService;
use Throwable;

/**
 * MT4 提现扣款网关。
 *
 * 文件功能：
 * - 调用 MT4 Manager API 执行用户出金操作。
 * - 解析 MT4 原始响应为标准 WithdrawalFundingResult。
 *
 * 适用场景：
 * - 提现结算出箱扫描器调用此网关将用户 MT4 账户余额扣除。
 *
 * 入参例子：
 * - userId: 10001
 * - amount: '990.00'（扣除手续费后的实际金额）
 * - comment: 'WDR20240801120000'
 *
 * 返回值：
 * - WithdrawalFundingResult：debited / retryable_not_sent / unknown / rejected。
 *
 * 异常或失败场景：
 * - 网络异常：未知结果（transport_exception）。
 * - 响应非法：未知结果（malformed_response / invalid_provider_reference）。
 * - connection_failed：可重试。
 * - write_failed / read_timeout / transport_exception：未知。
 * - 其他错误：明确拒绝。
 */
final class Mt4WithdrawalFundingGateway implements WithdrawalFundingGateway
{
    /**
     * MT4 Manager 服务：提现扣款远端命令的唯一执行通道。
     * 其响应分类（connection_failed=可重试、写读异常=unknown、其他=拒绝）驱动出箱状态机；
     * unknown 必须按结果不确定处理（转对账），分类错误会造成重复扣款或资金丢失。
     *
     * @var Mt4ManagerService
     */
    private $manager;

    /**
     * 注入 MT4 Manager 服务。
     *
     * @param Mt4ManagerService $manager MT4 账户资金操作服务,用于执行提现扣款。
     */
    public function __construct(Mt4ManagerService $manager)
    {
        $this->manager = $manager;
    }

    /**
     * 执行 MT4 提现扣款并归一化结果。
     *
     * 失败语义：connection_failed 明确未送达可重试；写失败/超时/传输异常返回 unknown
     * （可能已扣款，禁止重试，由扫描器转人工）；业务拒绝返回 rejected。
     *
     * @param int $userId 用户 ID（与 MT4 login 一致）。
     * @param string $amount 扣款金额（两位小数字符串）。
     * @param string $comment 出金备注（如本地订单号，写入手单）。
     * @return WithdrawalFundingResult debited / retryable_not_sent / unknown / rejected。
     * @throws \App\Services\Mt4SyncDisabledException 同步开关关闭时抛出。
     */
    public function withdraw(int $userId, string $amount, string $comment): WithdrawalFundingResult
    {
        // 用户与 MT4 同步全局开关：关闭时拒绝执行远端操作（fail-closed，转人工对账）。
        \App\Services\Mt4SyncGate::assertUserSyncEnabled();

        try {
            $response = $this->manager->withdrawal($userId, $amount, $comment);
        } catch (Throwable $exception) {
            // 远端异常：扣款是否已生效未知，按不确定处理，禁止重试。
            return WithdrawalFundingResult::unknown('transport_exception');
        }

        if (!is_array($response)) {
            return WithdrawalFundingResult::unknown('malformed_response');
        }

        // 响应 status/error_code 必须为标量且 status 只能是 ok/error，否则视为损坏。
        $rawStatus = $response['status'] ?? null;
        $rawErrorCode = $response['error_code'] ?? '';
        if (!is_scalar($rawStatus) || !is_scalar($rawErrorCode)) {
            return WithdrawalFundingResult::unknown('malformed_response');
        }
        $status = strtolower(trim((string) $rawStatus));
        $errorCode = trim((string) $rawErrorCode);
        if ($status === '' || !in_array($status, ['ok', 'error'], true)) {
            return WithdrawalFundingResult::unknown('malformed_response');
        }
        if ($status === 'ok') {
            // ok 分支取 ticket 作为扣款凭证（兼容 data[0] 包装）；
            // ticket 必须为正整数，否则结果不可信，转人工核对。
            if (array_key_exists('ticket', $response)) {
                $ticket = $response['ticket'];
            } elseif (isset($response['data']) && is_array($response['data'])) {
                $ticket = $response['data'][0] ?? null;
            } else {
                return WithdrawalFundingResult::unknown('malformed_response');
            }
            if (!is_scalar($ticket)) {
                return WithdrawalFundingResult::unknown('malformed_response');
            }
            $reference = trim((string) $ticket);
            if ($reference === '' || !ctype_digit($reference) || (int) $reference <= 0) {
                return WithdrawalFundingResult::unknown('invalid_provider_reference');
            }

            return WithdrawalFundingResult::debited($reference);
        }

        // 明确未送达才可重试；写失败/超时等不确定错误转人工；其余为业务拒绝。
        if ($errorCode === 'connection_failed') {
            return WithdrawalFundingResult::retryableNotSent($errorCode);
        }
        if (in_array($errorCode, ['write_failed', 'read_timeout', 'transport_exception'], true)) {
            return WithdrawalFundingResult::unknown($errorCode);
        }

        return WithdrawalFundingResult::rejected($errorCode !== '' ? $errorCode : 'provider_rejected');
    }
}
