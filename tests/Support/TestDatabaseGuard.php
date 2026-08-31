<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/17
 * Time: 22:06
 */

declare(strict_types=1);

namespace Tests\Support;

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Bootstrap\RegisterProviders;
use RuntimeException;

/**
 * PHPUnit 数据库与外部同步身份门禁。
 *
 * 文件功能：
 * - 在 Laravel 注册业务服务提供者前核对环境、数据库端点和 MT4 开关。
 * - 把门禁挂载到 RegisterProviders 启动事件，阻止危险提供者提前访问外部资源。
 *
 * 安全边界：
 * - 本类只判断已经加载的配置值，不建立数据库或 MT4 连接。
 * - 任何不符合专用测试身份的配置都必须抛出异常，不能静默回退。
 */
final class TestDatabaseGuard
{
    /**
     * 只允许 testing 环境：任何非测试环境执行测试都会直接抛异常，防止测试夹具污染生产/开发库。
     */
    private const ALLOWED_ENVIRONMENT = 'testing';

    /**
     * 只允许 mysql 驱动：夹具契约按 MySQL 的建议锁与 information_schema 编写，其他驱动行为不可预期。
     */
    private const ALLOWED_DRIVER = 'mysql';

    /**
     * 只允许专用测试库 co_crmv5_test：业务库名一律拒绝，防止测试数据写入真实库。
     */
    private const ALLOWED_DATABASE = 'co_crmv5_test';

    /**
     * 只允许本机回环地址：防止 .env 配置漂移后测试流量误连远程数据库。
     */
    private const ALLOWED_HOST = '127.0.0.1';

    /**
     * 只允许测试专用端口 3307：3306 等常见业务端口一律拒绝，端口配错的代价是清空真实业务库。
     */
    private const ALLOWED_PORT = '3307';

    /**
     * 在业务服务提供者注册前挂载测试身份门禁。
     *
     * @param Application $application 尚未执行 Console Kernel bootstrap 的应用实例。
     * @return void 监听器注册完成时无返回值；实际核对在 RegisterProviders 前执行。
     *
     * @throws RuntimeException 默认连接名无效，或监听器执行时任一安全边界不符合时抛出。
     */
    public static function registerBeforeProviders(Application $application): void
    {
        $application->beforeBootstrapping(
            RegisterProviders::class,
            static function (Application $application): void {
                $config = $application->make('config');
                $connectionName = $config->get('database.default');
                if (!is_string($connectionName) || $connectionName === '') {
                    throw new RuntimeException('PHPUnit 默认数据库连接名称不安全。');
                }

                // LoadConfiguration 已完成而 RegisterProviders 尚未开始，此时核对的是提供者真正将读取的配置。
                $connection = 'database.connections.' . $connectionName;
                self::assertSafe(
                    $application->environment(),
                    $config->get($connection . '.driver'),
                    $config->get($connection . '.database'),
                    $config->get($connection . '.host'),
                    $config->get($connection . '.port'),
                    $config->get($connection . '.unix_socket'),
                    $config->get($connection . '.url'),
                    $config->get('mt4.enabled'),
                    $config->get('mt4.user_sync_enabled')
                );
            }
        );
    }

    /**
     * 核对当前测试进程是否具备唯一允许的本地安全身份。
     *
     * @param mixed $environment Laravel 已解析的运行环境。
     * @param mixed $driver 默认数据库连接对应的驱动名称。
     * @param mixed $database 默认数据库连接对应的数据库名称。
     * @param mixed $host 默认数据库连接对应的主机。
     * @param mixed $port 默认数据库连接对应的端口。
     * @param mixed $socket 默认数据库连接对应的 Unix socket。
     * @param mixed $url 可能覆盖分项连接配置的 DATABASE_URL。
     * @param mixed $mt4Enabled MT4 远端连接总开关的原始配置值。
     * @param mixed $mt4UserSyncEnabled 用户维度 MT4 同步开关的原始配置值。
     * @return void 配置安全时无返回值；危险配置将在行为实现中失败关闭。
     *
     * @throws RuntimeException 任一配置不是唯一允许值时抛出，异常不包含数据库凭据。
     */
    public static function assertSafe(
        $environment,
        $driver,
        $database,
        $host,
        $port,
        $socket,
        $url,
        $mt4Enabled,
        $mt4UserSyncEnabled
    ): void {
        // 环境不是 testing 时不允许继续，避免本地或生产配置被测试生命周期重建。
        if ($environment !== self::ALLOWED_ENVIRONMENT) {
            throw new RuntimeException(
                'PHPUnit 环境身份不安全：实际环境为 ' . self::describeValue($environment) . '，仅允许 testing。'
            );
        }

        // 本项目闭环测试依赖真实 MySQL 语义；驱动漂移会掩盖 DDL、锁和索引问题。
        if ($driver !== self::ALLOWED_DRIVER) {
            throw new RuntimeException(
                'PHPUnit 数据库驱动不安全：实际驱动为 ' . self::describeValue($driver) . '，仅允许 mysql。'
            );
        }

        // 数据库名称采用精确白名单，正式库、旧库及近似名称都必须失败关闭。
        if ($database !== self::ALLOWED_DATABASE) {
            throw new RuntimeException(
                'PHPUnit 数据库身份不安全：实际数据库为 ' . self::describeValue($database)
                . '，仅允许 ' . self::ALLOWED_DATABASE . '。'
            );
        }

        // 库名相同并不能证明实例安全；主机和端口必须锁定用户明确指定的本地 MySQL。
        if ($host !== self::ALLOWED_HOST) {
            throw new RuntimeException(
                'PHPUnit 数据库主机不安全：实际主机为 ' . self::describeValue($host)
                . '，仅允许 ' . self::ALLOWED_HOST . '。'
            );
        }

        if ($port !== self::ALLOWED_PORT) {
            throw new RuntimeException(
                'PHPUnit 数据库端口不安全：实际端口为 ' . self::describeValue($port)
                . '，仅允许 ' . self::ALLOWED_PORT . '。'
            );
        }

        // socket 或 DATABASE_URL 会绕过已核对的 TCP 分项配置，因此测试进程必须显式清空两者。
        if ($socket !== '') {
            throw new RuntimeException(
                'PHPUnit 数据库 socket 不安全：实际值为 ' . self::describeValue($socket) . '。'
            );
        }

        if ($url !== '' && $url !== null) {
            throw new RuntimeException('PHPUnit 安全门禁拒绝非空 DATABASE_URL。');
        }

        // 必须是布尔 false；null、0 和字符串 false 都不能替代明确关闭状态。
        if ($mt4Enabled !== false) {
            throw new RuntimeException(
                'PHPUnit 安全门禁要求 MT4_ENABLED 为布尔 false，实际为 '
                . self::describeValue($mt4Enabled) . '。'
            );
        }

        // 用户同步开关也必须关闭；Outbox 状态机由测试按需显式配置和本地替身验证。
        if ($mt4UserSyncEnabled !== false) {
            throw new RuntimeException(
                'PHPUnit 安全门禁要求 MT4_USER_SYNC_ENABLED 为布尔 false，实际为 '
                . self::describeValue($mt4UserSyncEnabled) . '。'
            );
        }
    }

    /**
     * 生成不包含连接凭据的配置诊断文本。
     *
     * @param mixed $value 已读取的单项安全配置。
     * @return string 标量值或类型名称；数组和对象不会展开内部内容。
     */
    private static function describeValue($value): string
    {
        if ($value === null) {
            return 'null';
        }

        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if (is_string($value)) {
            return $value === '' ? '空字符串' : $value;
        }

        if (is_int($value) || is_float($value)) {
            return (string) $value;
        }

        return get_debug_type($value);
    }
}
