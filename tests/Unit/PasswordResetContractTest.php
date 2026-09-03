<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/06
 * Time: 01:43
 */

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * 全部认证主体统一初始密码契约测试。
 *
 * 文件功能：
 * - 锁定 Laravel 默认用户、前台用户、后台管理员、管理员兼容登录表和大代理五类凭据。
 * - 要求 PHP 命令使用事务批量更新并逐条 Hash::check 验证，SQL 固定哈希也必须对应 abc123。
 * - 禁止旧数据迁移继续复制历史密码或提示用户使用原密码。
 */
final class PasswordResetContractTest extends TestCase
{
    /** @var array<int, string> 需要统一重置密码的全部认证表。 */
    private const CREDENTIAL_TABLES = [
        'users',
        'user_logins',
        'admins',
        'admin_logins',
        'big_agents',
    ];

    /**
     * Laravel 默认用户工厂必须创建 abc123 密码。
     *
     * @return void 工厂不再使用框架默认 password 哈希时无返回值。
     */
    public function test_user_factory_uses_abc123(): void
    {
        $source = $this->source('database/factories/UserFactory.php');

        $this->assertStringContainsString("Hash::make('abc123')", $source);
        $this->assertStringNotContainsString('92IXUNpkjO0rOQ5byMi', $source);
    }

    /**
     * 密码重置命令必须在一个事务内覆盖并验证全部认证表。
     *
     * @return void 五表更新、验证和无人值守选项均存在时无返回值。
     */
    public function test_reset_command_transactionally_updates_and_verifies_every_credential_table(): void
    {
        $source = $this->source('app/Console/Commands/ResetAllPasswords.php');

        $this->assertStringContainsString('DB::transaction', $source);
        $this->assertStringContainsString('Hash::check', $source);
        $this->assertStringContainsString('--force', $source);
        foreach (self::CREDENTIAL_TABLES as $table) {
            $this->assertStringContainsString("'{$table}'", $source, '密码重置命令遗漏认证表：' . $table);
        }
        $this->assertSame(0, preg_match('/[\x{1F300}-\x{1FAFF}\x{2600}-\x{27BF}]/u', $source));
    }

    /**
     * 两个旧数据迁移命令不得把旧密码复制到新库。
     *
     * @return void 迁移只生成 abc123 哈希且不提示原密码时无返回值。
     */
    public function test_migration_commands_never_copy_legacy_passwords(): void
    {
        $legacyCommand = $this->source('app/Console/Commands/MigrateOldDataCommand.php');
        $fullCommand = $this->source('app/Console/Commands/MigrateOldData.php');
        $combined = $legacyCommand . "\n" . $fullCommand;

        $this->assertStringNotContainsString('$user->password', $combined);
        $this->assertStringNotContainsString('$agent->password', $combined);
        $this->assertStringNotContainsString('请使用原密码', $combined);
        $this->assertStringContainsString('abc123', $legacyCommand);
        $this->assertStringContainsString("password:reset-all", $fullCommand);
    }

    /**
     * 旧业务 Seeder 必须忽略旧密码格式并统一生成 abc123。
     *
     * @return void passwordHash 不再保留旧 bcrypt、MD5 或 SHA1 时无返回值。
     */
    public function test_legacy_business_seeder_always_hashes_abc123(): void
    {
        $method = $this->methodSource(
            $this->source('database/seeders/LegacyFrontBusinessDataSeeder.php'),
            'passwordHash'
        );

        $this->assertStringContainsString("Hash::make('abc123')", $method);
        $this->assertStringNotContainsString("strpos(\$value", $method);
        $this->assertStringNotContainsString('return $value', $method);
    }

    /**
     * 完整 SQL 必须对五张认证表写入正确的 bcrypt 哈希。
     *
     * 双口径设计：
     * - 前台表（users, user_logins）：迁移后重置为 123456
     * - 后台表（admins, admin_logins, big_agents）：种子账号标准 abc123
     *
     * @return void 每张表均存在 UPDATE 且哈希验证通过时无返回值。
     */
    public function test_full_sql_resets_every_credential_table_to_correct_password(): void
    {
        $sql = $this->source('database/sql/full_reset_and_migrate.sql');

        // 前台表使用 123456（迁移后重置口径）
        $frontTables = ['users', 'user_logins'];
        // 后台表使用 abc123（种子账号口径）
        $backendTables = ['admins', 'admin_logins', 'big_agents'];

        foreach ($frontTables as $table) {
            $matched = preg_match(
                "/UPDATE\\s+co_crmv5\\.{$table}\\s+SET\\s+password\\s*=\\s*'([^']+)'/i",
                $sql,
                $matches
            );
            $this->assertSame(1, $matched, '完整 SQL 未重置前台认证表：' . $table);
            $this->assertTrue(password_verify('123456', $matches[1]), 'SQL 固定哈希不对应 123456：' . $table);
        }

        foreach ($backendTables as $table) {
            $matched = preg_match(
                "/UPDATE\\s+co_crmv5\\.{$table}\\s+SET\\s+password\\s*=\\s*'([^']+)'/i",
                $sql,
                $matches
            );
            $this->assertSame(1, $matched, '完整 SQL 未重置后台认证表：' . $table);
            $this->assertTrue(password_verify('abc123', $matches[1]), 'SQL 固定哈希不对应 abc123：' . $table);
        }
    }

    /**
     * 读取项目内源码文件。
     *
     * @param string $relativePath 相对项目根目录的路径。
     * @return string 文件内容。
     */
    private function source(string $relativePath): string
    {
        $path = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativePath);
        $this->assertFileExists($path);
        $source = file_get_contents($path);
        $this->assertIsString($source);

        return $source;
    }

    /**
     * 提取指定方法完整源码。
     *
     * @param string $source PHP 源码。
     * @param string $method 方法名称。
     * @return string 方法源码。
     */
    private function methodSource(string $source, string $method): string
    {
        $start = strpos($source, 'function ' . $method . '(');
        $this->assertNotFalse($start, '缺少方法：' . $method);
        $open = strpos($source, '{', $start);
        $this->assertNotFalse($open, '方法缺少左花括号：' . $method);

        $depth = 0;
        for ($offset = $open, $length = strlen($source); $offset < $length; $offset++) {
            if ($source[$offset] === '{') {
                $depth++;
            } elseif ($source[$offset] === '}') {
                $depth--;
                if ($depth === 0) {
                    return substr($source, $start, $offset - $start + 1);
                }
            }
        }

        $this->fail('方法未闭合：' . $method);
    }
}
