<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/02
 * Time: 12:57
 */

declare(strict_types=1);

namespace App\Services\Risk;

use App\Contracts\RiskForceCloseGateway;
use App\Services\Mt4ManagerService;
use Throwable;

/**
 * MT4 风控强制平仓网关。
 *
 * 文件功能：
 * - 调用 MT4 Manager API 对指定持仓执行强制平仓。
 * - 解析 MT4 返回的原始响应，映射为标准 RiskForceCloseResult。
 *
 * 适用场景：
 * - 风控任务检测到用户风险率低于阈值时，通过此网关发出强平指令。
 *
 * 入参例子：
 * - login: 10001（MT4 登录号）。
 * - ticket: 888888（持仓票据号）。
 * - comment: 'risk_force_close'（平仓备注）。
 *
 * 返回值：
 * - RiskForceCloseResult：closed / retryable_not_sent / unknown / rejected。
 *
 * 异常或失败场景：
 * - 网络异常：返回 unknown('transport_exception')。
 * - 响应格式非法：返回 unknown('malformed_response')。
 * - connection_failed 错误码：返回 retryable_not_sent。
 * - write_failed / read_timeout：返回 unknown。
 * - 其他明确错误：返回 rejected。
 */
final class Mt4RiskForceCloseGateway implements RiskForceCloseGateway
{
    private Mt4ManagerService $manager;

    /**
     * 构造 MT4 风控强制平仓网关。
     *
     * @param Mt4ManagerService $manager MT4 管理服务，提供远端强制平仓命令。
     */
    public function __construct(Mt4ManagerService $manager)
    {
        $this->manager = $manager;
    }

    /**
     * 执行强制平仓并映射为统一结果。
     *
     * @param int $login 目标 MT4 登录号。
     * @param int $ticket 待平仓持仓票据号。
     * @param string $comment 平仓备注。
     * @return RiskForceCloseResult 状态语义见 RiskForceCloseResult：closed / retryable_not_sent / unknown / rejected。
     */
    public function close(int $login, int $ticket, string $comment): RiskForceCloseResult
    {
        // 传输层异常无法判断远端是否已平仓，统一按未知处理，重试前必须先对账，避免重复平仓指令。
        try {
            $response = $this->manager->closeOrder($login, $ticket, $comment);
        } catch (Throwable $exception) {
            return RiskForceCloseResult::unknown('transport_exception');
        }

        // 响应不是数组说明协议被破坏，按未知处理，防止伪造成功。
        if (!is_array($response)) {
            return RiskForceCloseResult::unknown('malformed_response');
        }

        $rawStatus = $response['status'] ?? null;
        $rawErrorCode = $response['error_code'] ?? '';
        if (!is_scalar($rawStatus) || !is_scalar($rawErrorCode)) {
            return RiskForceCloseResult::unknown('malformed_response');
        }

        $status = strtolower(trim((string) $rawStatus));
        $errorCode = trim((string) $rawErrorCode);
        // 状态只接受 ok/error，其余一律视为协议异常。
        if ($status === '' || !in_array($status, ['ok', 'error'], true)) {
            return RiskForceCloseResult::unknown('malformed_response');
        }

        if ($status === 'ok') {
            // 平仓成功时的票据引用：优先取响应 ticket，其次 data[0]，最后回退请求票据号本身。
            if (array_key_exists('ticket', $response)) {
                $referenceRaw = $response['ticket'];
            } elseif (isset($response['data']) && is_array($response['data'])) {
                $referenceRaw = $response['data'][0] ?? $ticket;
            } else {
                $referenceRaw = $ticket;
            }

            if (!is_scalar($referenceRaw)) {
                return RiskForceCloseResult::unknown('malformed_response');
            }

            $reference = trim((string) $referenceRaw);
            // 引用为空说明响应不可信；closed() 内部会将空引用转为 unknown(invalid_provider_reference)。
            if ($reference === '') {
                return RiskForceCloseResult::unknown('invalid_provider_reference');
            }

            return RiskForceCloseResult::closed($reference);
        }

        // 错误码分类：连接失败明确未发送可安全重试；写失败/超时/传输异常结果不确定；其余为明确拒绝。
        if ($errorCode === 'connection_failed') {
            return RiskForceCloseResult::retryableNotSent($errorCode);
        }
        if (in_array($errorCode, ['write_failed', 'read_timeout', 'transport_exception'], true)) {
            return RiskForceCloseResult::unknown($errorCode);
        }

        return RiskForceCloseResult::rejected($errorCode !== '' ? $errorCode : 'provider_rejected');
    }
}
