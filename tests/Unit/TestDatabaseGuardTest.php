<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/17
 * Time: 22:15
 */

declare(strict_types=1);

namespace Tests\Unit;

use Illuminate\Config\Repository;
use Illuminate\Container\Container;
use Illuminate\Foundation\Bootstrap\RegisterProviders;
use Illuminate\Support\Facades\Facade;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Tests\Support\TestDatabaseGuard;

/**
 * 测试数据库身份门禁契约。
 *
 * 文件功能：
 * - 约束 PHPUnit 只能使用专用 MySQL 测试库，防止测试夹具写入正式库或旧项目源库。
 * - 约束测试启动时 MT4 总开关和用户同步开关均关闭，避免测试触发外部同步。
 *
 * 安全边界：
 * - 本测试只验证纯值判断，不引导 Laravel，也不建立任何数据库或 MT4 连接。
 */
final class TestDatabaseGuardTest extends TestCase
{
    /**
     * 门禁实现必须能够由 Composer 自动加载。
     *
     * @return void 类存在时断言通过；缺少门禁实现时明确失败。
     */
    public function test_guard_contract_exists(): void
    {
        $this->assertTrue(class_exists(TestDatabaseGuard::class));
    }

    /**
     * 门禁公开接口必须保持固定，供 Laravel 测试启动链统一调用。
     *
     * @return void 方法存在时断言通过；接口缺失或误改名称时明确失败。
     */
    public function test_guard_exposes_assert_safe_contract(): void
    {
        $this->assertTrue(method_exists(TestDatabaseGuard::class, 'assertSafe'));
    }

    /**
     * 精确的专用测试身份应通过门禁。
     *
     * @return void 未抛异常且执行到末尾时表示允许配置有效。
     */
    public function test_guard_allows_only_the_dedicated_mysql_test_database(): void
    {
        TestDatabaseGuard::assertSafe(
            'testing',
            'mysql',
            'co_crmv5_test',
            '127.0.0.1',
            '3307',
            '',
            '',
            false,
            false
        );

        $this->assertTrue(true);
    }

    /**
     * PHPUnit 配置和 Laravel 启动链必须共同接入安全门禁。
     *
     * @return void XML 与启动源码同时满足约束时通过。
     */
    public function test_phpunit_bootstrap_forces_and_verifies_the_safe_identity(): void
    {
        $projectRoot = dirname(__DIR__, 2);
        $bootstrapSource = (string) file_get_contents($projectRoot . '/tests/CreatesApplication.php');
        $xml = simplexml_load_file($projectRoot . '/phpunit.xml');

        $this->assertNotFalse($xml, 'phpunit.xml 必须是可解析的 XML。');
        $this->assertStringContainsString('TestDatabaseGuard::registerBeforeProviders(', $bootstrapSource);
        $this->assertStringNotContainsString('(bool) config(', $bootstrapSource);

        $serverValues = [];
        foreach ($xml->php->server as $server) {
            $serverValues[(string) $server['name']] = [
                'value' => (string) $server['value'],
                'force' => (string) $server['force'],
            ];
        }

        $this->assertSame(['value' => 'mysql', 'force' => 'true'], $serverValues['DB_CONNECTION'] ?? null);
        $this->assertSame(['value' => '127.0.0.1', 'force' => 'true'], $serverValues['DB_HOST'] ?? null);
        $this->assertSame(['value' => '3307', 'force' => 'true'], $serverValues['DB_PORT'] ?? null);
        $this->assertSame(['value' => '', 'force' => 'true'], $serverValues['DB_SOCKET'] ?? null);
        $this->assertSame(['value' => '', 'force' => 'true'], $serverValues['DATABASE_URL'] ?? null);
        $this->assertSame(['value' => 'co_crmv5_test', 'force' => 'true'], $serverValues['DB_DATABASE'] ?? null);
        $this->assertSame(['value' => 'false', 'force' => 'true'], $serverValues['MT4_ENABLED'] ?? null);
        $this->assertSame(['value' => 'false', 'force' => 'true'], $serverValues['MT4_USER_SYNC_ENABLED'] ?? null);
    }

    /**
     * 门禁必须在 Laravel 注册业务服务提供者前失败关闭。
     *
     * @return void 危险主机触发异常且后续监听器未执行时通过。
     */
    public function test_guard_rejects_unsafe_configuration_before_provider_registration(): void
    {
        $previousContainer = Container::getInstance();
        $previousFacadeApplication = Facade::getFacadeApplication();

        try {
            $application = require dirname(__DIR__, 2) . '/bootstrap/app.php';
            $application->instance('env', 'testing');
            $application->instance('config', new Repository([
                'database' => [
                    'default' => 'mysql',
                    'connections' => [
                        'mysql' => [
                            'driver' => 'mysql',
                            'host' => '192.0.2.10',
                            'port' => '3307',
                            'database' => 'co_crmv5_test',
                            'unix_socket' => '',
                            'url' => '',
                        ],
                    ],
                ],
                'mt4' => [
                    'enabled' => false,
                    'user_sync_enabled' => false,
                ],
            ]));

            $providerRegistrationReached = false;
            TestDatabaseGuard::registerBeforeProviders($application);
            $application['events']->listen(
                'bootstrapping: ' . RegisterProviders::class,
                static function () use (&$providerRegistrationReached): void {
                    $providerRegistrationReached = true;
                }
            );

            try {
                $application['events']->dispatch('bootstrapping: ' . RegisterProviders::class, [$application]);
                $this->fail('危险配置必须在服务提供者注册前抛出异常。');
            } catch (RuntimeException $exception) {
                $this->assertStringContainsString('192.0.2.10', $exception->getMessage());
                $this->assertFalse($providerRegistrationReached);
            }
        } finally {
            // bootstrap/app.php 会替换进程级容器；恢复原状态，避免后续纯 Unit 读取半初始化 Laravel 服务。
            Facade::clearResolvedInstances();
            Facade::setFacadeApplication($previousFacadeApplication);
            Container::setInstance($previousContainer);
        }
    }

    /**
     * 任一身份或外部同步边界不符合时必须失败关闭。
     *
     * @param string $environment Laravel 运行环境测试值。
     * @param string $driver 数据库驱动测试值。
     * @param string $database 数据库名称测试值。
     * @param mixed $host 数据库主机测试值。
     * @param mixed $port 数据库端口测试值。
     * @param mixed $socket 数据库 socket 测试值。
     * @param mixed $url 数据库 URL 覆盖值。
     * @param mixed $mt4Enabled MT4 远端连接开关测试值。
     * @param mixed $mt4UserSyncEnabled 用户同步开关测试值。
     * @param string $messageFragment 预期错误中用于定位危险配置的片段。
     * @return void 抛出带配置定位信息的 RuntimeException 时通过。
     *
     * @dataProvider unsafeConfigurationProvider
     */
    public function test_guard_rejects_every_unsafe_configuration(
        string $environment,
        string $driver,
        string $database,
        $host,
        $port,
        $socket,
        $url,
        $mt4Enabled,
        $mt4UserSyncEnabled,
        string $messageFragment
    ): void {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage($messageFragment);

        TestDatabaseGuard::assertSafe(
            $environment,
            $driver,
            $database,
            $host,
            $port,
            $socket,
            $url,
            $mt4Enabled,
            $mt4UserSyncEnabled
        );
    }

    /**
     * 提供所有必须拒绝的配置边界，近似库名不能通过后缀或模糊匹配。
     *
     * @return array<string, array{mixed, mixed, mixed, mixed, mixed, mixed, mixed, mixed, mixed, string}> 危险配置及错误定位片段。
     */
    public function unsafeConfigurationProvider(): array
    {
        return [
            '非 testing 环境' => ['local', 'mysql', 'co_crmv5_test', '127.0.0.1', '3307', '', '', false, false, 'local'],
            '正式业务库' => ['testing', 'mysql', 'co_crmv5', '127.0.0.1', '3307', '', '', false, false, 'co_crmv5'],
            '旧项目源库' => ['testing', 'mysql', 'hank_zl_data', '127.0.0.1', '3307', '', '', false, false, 'hank_zl_data'],
            '近似 QA 库名' => ['testing', 'mysql', 'co_crmv5_qa', '127.0.0.1', '3307', '', '', false, false, 'co_crmv5_qa'],
            '错误数据库驱动' => ['testing', 'sqlite', 'co_crmv5_test', '127.0.0.1', '3307', '', '', false, false, 'sqlite'],
            '远端数据库主机' => ['testing', 'mysql', 'co_crmv5_test', '192.0.2.10', '3307', '', '', false, false, '192.0.2.10'],
            '错误数据库端口' => ['testing', 'mysql', 'co_crmv5_test', '127.0.0.1', '3306', '', '', false, false, '3306'],
            '非空数据库 socket' => ['testing', 'mysql', 'co_crmv5_test', '127.0.0.1', '3307', '/tmp/mysql.sock', '', false, false, 'socket'],
            '数据库 URL 覆盖' => ['testing', 'mysql', 'co_crmv5_test', '127.0.0.1', '3307', '', 'mysql://remote.example/app', false, false, 'DATABASE_URL'],
            'MT4 远端连接开启' => ['testing', 'mysql', 'co_crmv5_test', '127.0.0.1', '3307', '', '', true, false, 'MT4_ENABLED'],
            'MT4 用户同步开启' => ['testing', 'mysql', 'co_crmv5_test', '127.0.0.1', '3307', '', '', false, true, 'MT4_USER_SYNC_ENABLED'],
            'MT4 总开关缺失' => ['testing', 'mysql', 'co_crmv5_test', '127.0.0.1', '3307', '', '', null, false, 'MT4_ENABLED'],
            'MT4 用户开关缺失' => ['testing', 'mysql', 'co_crmv5_test', '127.0.0.1', '3307', '', '', false, null, 'MT4_USER_SYNC_ENABLED'],
            'MT4 总开关字符串伪关闭' => ['testing', 'mysql', 'co_crmv5_test', '127.0.0.1', '3307', '', '', 'false', false, 'MT4_ENABLED'],
            'MT4 用户开关整数伪关闭' => ['testing', 'mysql', 'co_crmv5_test', '127.0.0.1', '3307', '', '', false, 0, 'MT4_USER_SYNC_ENABLED'],
        ];
    }
}
