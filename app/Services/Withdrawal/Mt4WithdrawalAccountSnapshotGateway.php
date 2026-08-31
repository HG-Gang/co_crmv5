<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/02
 * Time: 12:57
 */

/**
 * MT4 出金账户快照网关。
 *
 * 文件功能：
 * - 通过 MT4 Manager 服务获取用户账户的 balance 与 freeMargin，构造 WithdrawalAccountSnapshot 对象。
 *
 * 适用场景：
 * - 出金流程中获取用户账户资金快照，用于后续的出金额度校验。
 *
 * 入参例子：
 * - snapshot(12345)
 *
 * 返回值：
 * - 成功时返回 WithdrawalAccountSnapshot 对象。
 * - 失败时抛出 DomainException。
 *
 * 异常或失败场景：
 * - MT4 返回非 OK 状态或缺少必需字段时抛出 DomainException("snapshot_unavailable")。
 * - 网络异常或响应解析失败时抛出 DomainException。
 */

declare(strict_types=1);

namespace App\Services\Withdrawal;

use App\Contracts\WithdrawalAccountSnapshotGateway;
use App\Services\Mt4ManagerService;
use DomainException;
use Throwable;

final class Mt4WithdrawalAccountSnapshotGateway implements WithdrawalAccountSnapshotGateway
{
    /**
     * MT4 Manager 服务：账户 balance/freeMargin 快照的唯一远端来源。
     * 出金额度校验完全建立在该快照上——快照不可用时必须抛 DomainException 失败关闭，
     * 绝不允许以本地缓存余额放行提现，否则可能超扣用户真实资金。
     *
     * @var Mt4ManagerService
     */
    private $manager;

    /**
     * 构造快照网关。
     *
     * @param Mt4ManagerService $manager MT4 Manager 服务，用于获取账户 balance 与 freeMargin。
     */
    public function __construct(Mt4ManagerService $manager)
    {
        $this->manager = $manager;
    }

    /**
     * 查询用户 MT4 账户快照（balance / freeMargin）。
     *
     * 失败语义：任何异常或响应缺少必需字段都以 DomainException 抛出（失败关闭），
     * 由调用方转成业务错误，保证出金流程不会在无快照情况下继续。
     *
     * @param int $userId 用户 ID（与 MT4 login 一致）。
     * @return WithdrawalAccountSnapshot 余额与可用保证金快照。
     * @throws DomainException 同步开关关闭、网络异常、响应非法或字段缺失时抛出。
     */
    public function snapshot(int $userId): WithdrawalAccountSnapshot
    {
        // 用户与 MT4 同步全局开关：关闭时拒绝执行远端操作（fail-closed，转人工对账）。
        \App\Services\Mt4SyncGate::assertUserSyncEnabled();

        try {
            $response = $this->manager->getAccountInfo($userId);
        } catch (Throwable $exception) {
            // 网络异常：快照不可得，抛出而非返回空数据，防止用脏余额放行提现。
            throw new DomainException('snapshot_unavailable', 0, $exception);
        }

        // 响应必须为 ok 且同时含 balance / free_margin 字符串字段，缺一不可。
        if (!is_array($response)
            || strtolower(trim((string) ($response['status'] ?? ''))) !== 'ok'
            || !array_key_exists('balance', $response)
            || !array_key_exists('free_margin', $response)
            || !is_string($response['balance'])
            || !is_string($response['free_margin'])) {
            throw new DomainException('snapshot_unavailable');
        }

        try {
            return new WithdrawalAccountSnapshot($response['balance'], $response['free_margin']);
        } catch (Throwable $exception) {
            // 快照值对象构造校验失败（金额格式非法），同样按不可用处理。
            throw new DomainException('snapshot_unavailable', 0, $exception);
        }
    }
}
