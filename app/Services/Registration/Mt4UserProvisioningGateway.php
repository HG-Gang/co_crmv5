<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/06
 * Time: 01:04
 */

/**
 * MT4 用户开户与对账网关。
 *
 * 文件功能：
 * - 通过 MT4 Manager 服务完成用户开户（provision）与账户信息对账（reconcile），返回统一的 UserMt4ProvisioningResult。
 *
 * 适用场景：
 * - 用户注册后需要同步在 MT4 交易系统中创建账户，以及后续需要校验 MT4 账户状态是否与系统记录一致的场景。
 *
 * 入参例子：
 * - provision(["user_id" => "12345", "group" => "default", ...])
 * - reconcile(12345, "default")
 *
 * 返回值：
 * - 成功时返回 UserMt4ProvisioningResult::processed($ticket)。
 * - 失败时根据错误类型返回 retryableNotSent / unknown / rejected。
 *
 * 异常或失败场景：
 * - 用户 ID 无效时返回 rejected("payload_identity_invalid")。
 * - 网络异常返回 unknown("transport_exception")。
 * - 账户 ID 不匹配、账户禁用、分组不匹配等业务拒绝时返回 rejected。
 */

declare(strict_types=1);

namespace App\Services\Registration;

use App\Contracts\UserMt4ProvisioningGateway;
use App\Services\Mt4ManagerService;
use App\Services\Mt4SyncGate;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use Throwable;

final class Mt4UserProvisioningGateway implements UserMt4ProvisioningGateway
{
    /**
     * MT4 Manager Socket 服务：registerUser/getAccountInfo 协议调用的唯一远端通道。
     * 开户结果分类（processed/unknown/rejected）完全由它的响应决定，unknown 必须失败关闭转对账，
     * 不可用时整个开通流程停摆而不是放行未开户账号。
     *
     * @var Mt4ManagerService
     */
    private $manager;

    /**
     * 可选的结构化日志接收器（参数：级别、消息、脱敏上下文）。生产为空时回退 Laravel Log；
     * 纯单元测试注入内存接收器以隔离全局容器，保证网关日志断言不依赖 Log 门面。
     *
     * @var callable|null
     */
    private $logReceiver;

    /**
     * 构造 MT4 用户开户网关。
     *
     * @param Mt4ManagerService $manager 底层 MT4 Manager 服务，提供 registerUser/getAccountInfo 协议调用。
     * @param callable|null $logReceiver 可选结构化日志接收器，参数依次为级别、消息和脱敏上下文；
     *        生产环境为空时使用 Laravel Log，纯单元测试传入内存接收器以隔离全局容器。
     */
    public function __construct(
        Mt4ManagerService $manager,
        callable $logReceiver = null
    ) {
        $this->manager = $manager;
        $this->logReceiver = $logReceiver;
    }

    /**
     * 执行 MT4 用户开户：校验身份后调用 Manager 注册接口，并把响应分类为统一结果。
     *
     * @param array<string, mixed> $payload 开户负载（user_id / group / password 等，已由上游加密存储）。
     * @return UserMt4ProvisioningResult 状态语义见 UserMt4ProvisioningResult。
     */
    public function provision(array $payload): UserMt4ProvisioningResult
    {
        // 用户与 MT4 同步全局开关：关闭时拒绝执行远端操作（fail-closed，转人工对账）。
        Mt4SyncGate::assertUserSyncEnabled();

        // user_id 必须是正整数：远端按 ID 建户，非法 ID 直接拒绝，防止把畸形身份发给 MT4。
        $userId = $payload['user_id'] ?? null;
        if (!is_scalar($userId)
            || preg_match('/^\d+$/D', trim((string) $userId)) !== 1
            || (int) $userId <= 0) {
            return UserMt4ProvisioningResult::rejected('payload_identity_invalid');
        }

        try {
            $response = $this->manager->registerUser($payload);
        } catch (InvalidArgumentException $exception) {
            // 协议层参数非法（如 group 格式错误）：属参数级拒绝，重试无意义，记 warning 供排查。
            $this->logWarning('MT4 provisioning gateway protocol exception.', [
                'exception_class' => get_class($exception),
                'mode' => 'provision',
            ]);
            return UserMt4ProvisioningResult::rejected('invalid_protocol_value');
        } catch (Throwable $exception) {
            // 传输层异常无法判断远端是否已开户，按未知处理，重试前必须先对账，防止重复开户。
            $this->logError('MT4 provisioning gateway transport exception.', [
                'exception_class' => get_class($exception),
                'mode' => 'provision',
            ]);
            return UserMt4ProvisioningResult::unknown('transport_exception');
        }

        return $this->classify($response, false, (int) $userId);
    }

    /**
     * 执行 MT4 账户对账：读取远端账户信息并与期望分组比对，用于未知结果的复核。
     *
     * @param int $userId 目标 MT4 登录号。
     * @param string|null $expectedGroup 期望分组；不匹配时返回 rejected('account_group_mismatch')。
     * @return UserMt4ProvisioningResult 状态语义见 UserMt4ProvisioningResult。
     */
    public function reconcile(int $userId, string $expectedGroup = null): UserMt4ProvisioningResult
    {
        // 用户与 MT4 同步全局开关：关闭时拒绝执行远端操作（fail-closed，转人工对账）。
        Mt4SyncGate::assertUserSyncEnabled();

        if ($userId <= 0) {
            return UserMt4ProvisioningResult::rejected('account_identity_invalid');
        }

        try {
            $response = $this->manager->getAccountInfo($userId);
        } catch (Throwable $exception) {
            // 对账也走 fail-closed：远端不可达时记录错误并返回未知，不伪造"账户不存在"的结论。
            $this->logError('MT4 provisioning gateway transport exception.', [
                'exception_class' => get_class($exception),
                'mode' => 'reconcile',
            ]);
            return UserMt4ProvisioningResult::unknown('transport_exception');
        }

        return $this->classify($response, true, $userId, $expectedGroup);
    }

    /**
     * 将 MT4 原始响应分类为统一结果；任何结构异常都按 unknown（malformed_response）处理，不伪造成功。
     *
     * @param mixed $response MT4 Manager 原始响应。
     * @param bool $requiresAccountBalance 对账模式为 true：要求 balance / is_enabled / group 等账户字段合法。
     * @param int|null $expectedAccountId 期望账户 ID；非空时校验响应中的账户身份。
     * @param string|null $expectedGroup 期望分组；对账模式非空时校验。
     * @return UserMt4ProvisioningResult 分类后的统一结果。
     */
    private function classify(
        $response,
        bool $requiresAccountBalance,
        int $expectedAccountId = null,
        string $expectedGroup = null
    ): UserMt4ProvisioningResult
    {
        if (!is_array($response)) {
            return UserMt4ProvisioningResult::unknown('malformed_response');
        }

        $rawStatus = $response['status'] ?? null;
        $rawErrorCode = $response['error_code'] ?? '';
        // 结构字段必须为标量；错误码超长（>100 字符）视为异常数据，防止异常值进入日志与出箱表。
        if (!is_scalar($rawStatus) || !is_scalar($rawErrorCode)) {
            return UserMt4ProvisioningResult::unknown('malformed_response');
        }

        $status = strtolower(trim((string) $rawStatus));
        $errorCode = trim((string) $rawErrorCode);
        if (mb_strlen($errorCode, 'UTF-8') > 100) {
            return UserMt4ProvisioningResult::unknown('malformed_response');
        }
        // 状态只接受 ok/error，其余一律视为协议异常。
        if (!in_array($status, ['ok', 'error'], true)) {
            return UserMt4ProvisioningResult::unknown('malformed_response');
        }
        if ($status === 'ok') {
            // ok 时必须同时满足 err=0，防止"状态 ok 但内部错误"的半成功响应被误判成功。
            if (!array_key_exists('err', $response)
                || !is_scalar($response['err'])
                || trim((string) $response['err']) !== '0') {
                return UserMt4ProvisioningResult::unknown('malformed_response');
            }
            // 对账模式要求余额字段合法（数字串），缺失或畸形视为未知。
            if ($requiresAccountBalance) {
                $balance = $response['balance'] ?? null;
                if (!is_scalar($balance)
                    || !preg_match('/^-?\d+(?:\.\d+)?$/D', trim((string) $balance))) {
                    return UserMt4ProvisioningResult::unknown('malformed_response');
                }
            }
            if ($expectedAccountId !== null) {
                // 开户响应用 acc 字段、对账响应用 account_id 字段标识账户；必须是正整数。
                $accountId = $requiresAccountBalance
                    ? ($response['account_id'] ?? null)
                    : ($response['acc'] ?? null);
                if (!is_scalar($accountId)
                    || preg_match('/^\d+$/D', trim((string) $accountId)) !== 1) {
                    return UserMt4ProvisioningResult::unknown('malformed_response');
                }
                // 远端账户 ID 与期望不一致是明确拒绝，防止 A 用户拿到 B 账户。
                if ((string) (int) $accountId !== (string) $expectedAccountId) {
                    return UserMt4ProvisioningResult::rejected('account_identity_mismatch');
                }

                if ($requiresAccountBalance) {
                    // 账户必须启用；is_enabled 只接受 '0'/'1'，禁用即明确拒绝。
                    $enabled = $response['is_enabled'] ?? null;
                    if (!is_scalar($enabled)) {
                        return UserMt4ProvisioningResult::unknown('malformed_response');
                    }
                    $enabledValue = trim((string) $enabled);
                    if (!in_array($enabledValue, ['0', '1'], true)) {
                        return UserMt4ProvisioningResult::unknown('malformed_response');
                    }
                    if ($enabledValue !== '1') {
                        return UserMt4ProvisioningResult::rejected('account_disabled');
                    }

                    // 分组非空且与期望一致；不匹配说明用户被建到错误分组，需人工修正。
                    $group = $response['group'] ?? null;
                    if (!is_scalar($group) || trim((string) $group) === '') {
                        return UserMt4ProvisioningResult::unknown('malformed_response');
                    }
                    if ($expectedGroup !== null
                        && trim((string) $group) !== trim((string) $expectedGroup)) {
                        return UserMt4ProvisioningResult::rejected('account_group_mismatch');
                    }
                }
            }
            // 票据号可选（对账可能无 ticket）；存在时必须为短标量，防止超长值进入出箱表。
            $reference = $response['ticket'] ?? null;
            if ($reference !== null && !is_scalar($reference)) {
                return UserMt4ProvisioningResult::unknown('malformed_response');
            }
            if ($reference !== null && mb_strlen((string) $reference, 'UTF-8') > 100) {
                return UserMt4ProvisioningResult::unknown('malformed_response');
            }

            return UserMt4ProvisioningResult::processed($reference === null ? null : (string) $reference);
        }
        // 错误码分类：连接失败明确未发送可安全重试；写失败/超时/传输异常/畸形响应结果不确定。
        if ($errorCode === 'connection_failed') {
            return UserMt4ProvisioningResult::retryableNotSent($errorCode);
        }
        if (in_array(
            $errorCode,
            ['write_failed', 'read_timeout', 'transport_exception', 'malformed_response'],
            true
        )) {
            return UserMt4ProvisioningResult::unknown($errorCode);
        }

        // 空错误码无法分类，按未知处理；其余明确错误码视为拒绝。
        if ($errorCode === '') {
            return UserMt4ProvisioningResult::unknown('malformed_response');
        }

        return UserMt4ProvisioningResult::rejected($errorCode);
    }

    /**
     * 记录警告日志；Log 门面不可用时静默跳过，保证纯命令行/测试环境不因日志组件缺失而崩溃。
     *
     * @param string $message 警告消息。
     * @param array<string, mixed> $context 结构化上下文，禁止包含密码等敏感值。
     * @return void
     */
    /** @param array<string, mixed> $context */
    private function logWarning(string $message, array $context): void
    {
        if ($this->logReceiver !== null) {
            ($this->logReceiver)('warning', $message, $context);

            return;
        }

        if (!$this->loggerIsAvailable()) {
            return;
        }

        Log::warning($message, $context);
    }

    /**
     * 记录错误日志；Log 门面不可用时静默跳过，保证纯命令行/测试环境不因日志组件缺失而崩溃。
     *
     * @param string $message 错误消息。
     * @param array<string, mixed> $context 结构化上下文，禁止包含密码等敏感值。
     * @return void
     */
    /** @param array<string, mixed> $context */
    private function logError(string $message, array $context): void
    {
        if ($this->logReceiver !== null) {
            ($this->logReceiver)('error', $message, $context);

            return;
        }

        if (!$this->loggerIsAvailable()) {
            return;
        }

        Log::error($message, $context);
    }

    /**
     * 判断 Laravel Log 门面是否可用。
     *
     * 未启动完整应用容器（如单元测试或脚本）时 app() 可能不可用，
     * 此时跳过日志写入而不抛异常。
     *
     * @return bool true=可安全调用 Log::warning/Log::error。
     */
    private function loggerIsAvailable(): bool
    {
        if (!function_exists('app')) {
            return false;
        }

        $application = app();

        return is_object($application)
            && method_exists($application, 'bound')
            && $application->bound('log');
    }
}
