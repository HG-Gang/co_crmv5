<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/06
 * Time: 00:35
 */

/**
 * 初始化账号统一密码契约测试。
 *
 * 文件功能：
 * - 覆盖全新库迁移、总 Seeder、演示数据 Seeder 与调试数据命令中的固定账号来源。
 * - 要求每个来源明确使用 abc123，并拒绝历史默认密码重新进入初始化链路。
 *
 * 边界说明：
 * - 本测试只校验项目内固定初始密码，不限制注册、改密和测试夹具自行传入的动态密码。
 * - 数据迁移结束后的全量密码重置由 SQL 与 Laravel 命令另行执行和验证。
 */

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class InitialPasswordContractTest extends TestCase
{
    /** @var array<int, string> 已废止且不得继续出现在初始化入口中的账号密码片段。 */
    private const FORBIDDEN_PASSWORD_FRAGMENTS = [
        'Admin@123456',
        'admin123',
        'agent123',
        'customer123',
        'password123',
        "Hash::make(\$value !== '' ? \$value : '123456')",
    ];

    /**
     * 验证每个固定账号初始化入口只声明统一密码 abc123。
     *
     * @dataProvider initialPasswordSourceProvider
     *
     * @param string $relativePath 相对项目根目录的初始化源码路径。
     * @return void 文件声明 abc123 且不含历史默认密码时无返回值。
     */
    public function test_initial_account_sources_use_only_abc123(string $relativePath): void
    {
        $absolutePath = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR
            . str_replace('/', DIRECTORY_SEPARATOR, $relativePath);
        $source = file_get_contents($absolutePath);

        $this->assertIsString($source, '无法读取初始化密码来源：' . $relativePath);
        $this->assertStringContainsString('abc123', $source, '初始化入口必须明确声明统一密码 abc123：' . $relativePath);

        foreach (self::FORBIDDEN_PASSWORD_FRAGMENTS as $forbiddenFragment) {
            $this->assertStringNotContainsString(
                $forbiddenFragment,
                $source,
                '初始化入口仍包含已废止账号密码片段 ' . $forbiddenFragment . '：' . $relativePath
            );
        }
    }

    /**
     * 返回所有会创建固定前后台账号或输出其登录凭据的源码文件。
     *
     * @return array<string, array{0: string}> 数据集名称到相对源码路径的映射。
     */
    public function initialPasswordSourceProvider(): array
    {
        return [
            '总数据库 Seeder' => ['database/seeders/DatabaseSeeder.php'],
            '初始数据 Seeder' => ['database/seeders/InitialDataSeeder.php'],
            '前台演示数据 Seeder' => ['database/seeders/FrontDemoDataSeeder.php'],
            '旧业务数据 Seeder' => ['database/seeders/LegacyFrontBusinessDataSeeder.php'],
            '调试数据 Seeder' => ['database/seeders/DebugDataSeeder.php'],
            '固定前台代理迁移' => ['database/migrations/2026_05_28_000001_ensure_front_test_agent_login.php'],
            '默认后台管理员迁移' => ['database/migrations/2026_06_07_000014_fix_default_admin_and_front_menu_roles.php'],
            '固定邀请代理迁移' => ['database/migrations/2026_08_01_000002_ensure_front_inviter_test_agent.php'],
            '调试数据生成命令' => ['app/Console/Commands/GenerateDebugData.php'],
            '根目录兼容播种脚本' => ['_seed.php'],
            '公开诊断脚本' => ['public/diag.php'],
        ];
    }
}
