<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/07
 * Time: 11:56
 */

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * 测试数据库准备器与 PHPUnit 运行器的静态安全契约。
 *
 * 文件功能：
 * - 固定三个脚本只能面向隔离测试库，并关闭所有 MT4 外部同步入口。
 * - 验证全量运行器按“准备数据库、迁移、测试”的顺序失败关闭。
 * - 验证逐文件运行器同时依据进程退出码和 PHPUnit 摘要分类结果。
 *
 * 安全边界：
 * - 本测试只读取脚本文本，不连接数据库，也不执行迁移或测试运行器。
 * - 任一关键契约缺失都直接失败，防止脚本误指向非测试环境后继续执行。
 */
final class TestRunnerContractTest extends TestCase
{
    /**
     * 被契约校验的运行器脚本相对路径全集：测试库准备脚本、全量串行运行器、逐文件运行器。
     * 契约统一断言脚本只指向 co_crmv5_test 并关闭 MT4 外部同步；新增测试脚本必须登记于此才纳入门禁。
     *
     * @var array<int, string>
     */
    private const SCRIPT_PATHS = [
        'scripts/prepare-test-database.php',
        'scripts/run-full-serial.ps1',
        'scripts/run-tests-one-by-one.ps1',
    ];

    public function test_all_database_tools_only_name_the_exact_isolated_database(): void
    {
        foreach (self::SCRIPT_PATHS as $relativePath) {
            $source = $this->source($relativePath);
            // 中文注释标准 v0.0.3 的 PhpStorm 头部块是文件元数据，不属于可执行脚本逻辑；
            // 扫描前剥离首个 PHPDoc 块，避免 "Project name co_crmv5." 被误判为生产库名引用。
            $source = (string) preg_replace('/^<\?php\s*\/\*\*.*?\*\/\s*/s', "<?php\n", $source, 1);
            preg_match_all('/\bco_crmv5(?:_[a-z0-9]+)?\b/i', $source, $matches);

            $this->assertSame(
                ['co_crmv5_test'],
                array_values(array_unique($matches[0])),
                $relativePath . ' 只能出现精确测试库名。'
            );
            $this->assertStringNotContainsString('co_crmv5_qa', $source);
        }
    }

    public function test_database_preparer_loads_env_and_creates_only_the_whitelisted_database(): void
    {
        $source = $this->source('scripts/prepare-test-database.php');

        $this->assertStringContainsString('Dotenv::createImmutable', $source);
        $this->assertStringContainsString("['co_crmv5_test']", $source);
        $this->assertStringContainsString('in_array(', $source);
        $this->assertStringContainsString(', true)', $source);
        $this->assertStringContainsString('mysql:host=', $source);
        $this->assertStringNotContainsString('dbname=', $source);
        $this->assertStringContainsString('CREATE DATABASE IF NOT EXISTS', $source);
        $this->assertStringContainsString('exit(1)', $source);
        $this->assertStringNotContainsString('getMessage()', $source);
    }

    /**
     * 数据库准备器必须拒绝已确认本地实例之外的主机或端口。
     */
    public function test_database_preparer_is_locked_to_the_confirmed_local_instance(): void
    {
        $source = $this->source('scripts/prepare-test-database.php');

        $this->assertStringContainsString('$host !== \'127.0.0.1\'', $source);
        $this->assertStringContainsString('$port !== \'3307\'', $source);
        $this->assertStringContainsString("'DB_PORT', '3307'", $source);
    }

    /**
     * 重复且包含凭据默认值的旧准备器必须移除，避免出现第二套数据库目标规则。
     */
    public function test_obsolete_database_provisioner_is_removed(): void
    {
        $this->assertFileDoesNotExist(
            dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'scripts' . DIRECTORY_SEPARATOR . 'provision_test_db.php'
        );
    }

    public function test_full_runner_is_portable_isolated_and_fails_closed_in_order(): void
    {
        $source = $this->source('scripts/run-full-serial.ps1');

        $this->assertPortableRunner($source);
        $this->assertIsolatedEnvironment($source);
        $this->assertStringContainsString('prepare-test-database.php', $source);
        $this->assertStringContainsString('migrate:fresh', $source);
        $this->assertStringContainsString('--seed', $source);
        $this->assertStringContainsString('--force', $source);
        $this->assertStringContainsString('vendor/bin/phpunit', $source);
        $this->assertStringContainsString('1>>', $source);
        $this->assertStringContainsString('2>>', $source);
        $this->assertStringContainsString('.exit', $source);

        $prepare = strpos($source, 'prepare-test-database.php');
        $migration = strpos($source, 'migrate:fresh');
        $phpunit = strpos($source, 'vendor/bin/phpunit');
        $this->assertIsInt($prepare);
        $this->assertIsInt($migration);
        $this->assertIsInt($phpunit);
        $this->assertLessThan($migration, $prepare, '必须先准备测试库，再执行迁移。');
        $this->assertLessThan($phpunit, $migration, '迁移成功后才允许启动 PHPUnit。');
        $this->assertGreaterThanOrEqual(3, substr_count($source, 'exit $'), '每个子进程失败都必须显式传递退出码。');
    }

    public function test_per_file_runner_only_discovers_test_files_and_classifies_process_results(): void
    {
        $source = $this->source('scripts/run-tests-one-by-one.ps1');

        $this->assertPortableRunner($source);
        $this->assertIsolatedEnvironment($source);
        $this->assertStringContainsString("-Filter '*Test.php'", $source);
        $this->assertStringNotContainsString("-Filter '*.php'", $source);
        $this->assertStringContainsString('$LASTEXITCODE', $source);
        $this->assertStringContainsString('$stdout', $source);
        $this->assertStringContainsString('$stderr', $source);
        $this->assertStringContainsString('$testExit -eq 0', $source);
        $this->assertStringContainsString('$testExit -ne 0', $source);
        $this->assertStringContainsString('FAILURES!', $source);
        $this->assertStringContainsString('ERRORS!', $source);
        $this->assertStringContainsString('$status = \'CRASH\'', $source);
        $this->assertStringContainsString('exit 1', $source);
    }

    /**
     * 断言 PowerShell 运行器不依赖开发者机器上的绝对路径。
     *
     * @param string $source 运行器完整源码。
     * @return void 路径可从当前 PHP 命令和脚本目录解析时无返回值，否则断言失败。
     */
    private function assertPortableRunner(string $source): void
    {
        $this->assertStringContainsString('Get-Command php', $source);
        $this->assertStringContainsString('Select-Object -First 1', $source);
        $this->assertStringContainsString('$PSScriptRoot', $source);
        $this->assertDoesNotMatchRegularExpression('/[A-Za-z]:\\\\/', $source);
    }

    /**
     * 断言运行器显式覆盖测试环境和外部同步开关。
     *
     * @param string $source 运行器完整源码。
     * @return void 隔离变量全部固定时无返回值，否则断言失败。
     */
    private function assertIsolatedEnvironment(string $source): void
    {
        foreach ([
            '$env:APP_ENV = \'testing\'',
            '$env:DATABASE_URL = \'\'',
            '$env:DB_CONNECTION = \'mysql\'',
            '$env:DB_HOST = \'127.0.0.1\'',
            '$env:DB_PORT = \'3307\'',
            '$env:DB_SOCKET = \'\'',
            '$env:DB_DATABASE = \'co_crmv5_test\'',
            '$env:MT4_ENABLED = \'false\'',
            '$env:MT4_USER_SYNC_ENABLED = \'false\'',
        ] as $assignment) {
            $this->assertStringContainsString($assignment, $source);
        }
    }

    /**
     * 读取参与契约审查的仓库脚本。
     *
     * @param string $relativePath 相对项目根目录的脚本路径。
     * @return string 文件完整内容；文件不存在或不可读时直接失败，禁止用空内容绕过契约。
     */
    private function source(string $relativePath): string
    {
        $path = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativePath);
        $this->assertFileExists($path);
        $source = file_get_contents($path);
        $this->assertIsString($source);

        return $source;
    }
}
