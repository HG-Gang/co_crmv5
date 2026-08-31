<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/06
 * Time: 01:30
 */

namespace App\Services;

use RuntimeException;

/**
 * 用户与 MT4 同步全局开关门控。
 *
 * 文件功能：
 * - 提供“用户 ↔ MT4 同步”的全局开关判断（config/mt4.php 的 user_sync_enabled）。
 * - 所有用户维度 MT4 同步入口（开户预配、资料同步、出入金/信用结算、佣金转账、
 *   销户锁号、账户类型切换等）在动作前统一调用本门控：
 *   开关关闭时抛 Mt4SyncDisabledException（fail-closed），调用方转为
 *   Outbox 失败 + 人工对账，不执行任何 MT4 远端操作。
 *
 * 适用场景：
 * - 运维需要临时/长期停用“用户与 MT4 同步”时，通过环境变量 MT4_USER_SYNC_ENABLED=false
 *   或 config/mt4.php 一键关闭，无需改动任何业务代码。
 *
 * 入参例子：
 * - Mt4SyncGate::userSyncEnabled() -> true / false
 * - Mt4SyncGate::assertUserSyncEnabled() -> 通过或抛异常
 *
 * 返回值：
 * - userSyncEnabled(): bool 当前是否允许用户与 MT4 同步。
 * - assertUserSyncEnabled(): void 允许时无返回，禁止时抛 Mt4SyncDisabledException。
 *
 * 异常或失败场景：
 * - 开关关闭时 assertUserSyncEnabled() 抛出 Mt4SyncDisabledException，
 *   调用方必须捕获并按“本地为准 + 转人工”处理，禁止忽略。
 */
final class Mt4SyncGate
{
    /**
     * 用户与 MT4 同步是否启用。
     *
     * 说明：纯单元测试或脚本未引导 Laravel 容器时，无法证明同步已获授权，
     * 因此必须默认关闭；Feature 测试与运行时按 config('mt4.user_sync_enabled') 生效。
     *
     * @return bool true=启用；false=已全局关闭。
     */
    public static function userSyncEnabled(): bool
    {
        try {
            return config('mt4.user_sync_enabled', false) === true;
        } catch (\Throwable $e) {
            // 配置容器不可用时无法确认授权，必须 fail-closed，禁止意外建立远端连接。
            return false;
        }
    }

    /**
     * 判断当前是否具备执行远端用户同步的完整授权。
     *
     * MT4 连接总开关与用户同步业务开关必须同时为严格布尔 true；任一关闭、缺失或
     * 类型异常都返回 false，供注册、Outbox 扫描器等调度入口统一进入本地模式。
     *
     * @return bool true=允许远端用户同步；false=仅保留本地业务逻辑。
     */
    public static function remoteUserSyncEnabled(): bool
    {
        try {
            return config('mt4.enabled', false) === true && self::userSyncEnabled();
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * 断言用户与 MT4 同步可用；不可用时抛异常。
     *
     * @return void 可用时不返回值。
     *
     * @throws Mt4SyncDisabledException 全局开关关闭时抛出。
     */
    public static function assertUserSyncEnabled(): void
    {
        if (! self::userSyncEnabled()) {
            throw new Mt4SyncDisabledException('用户与 MT4 同步已由全局开关关闭（MT4_USER_SYNC_ENABLED=false）。');
        }
    }
}

/**
 * 用户与 MT4 同步被全局开关禁用时抛出。
 */
class Mt4SyncDisabledException extends RuntimeException
{
}
