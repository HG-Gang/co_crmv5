<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/06/07
 * Time: 21:08
 */

/**
 * AdminBladeButtonPermissionCoverageTest
 *
 * 文件功能：
 * - 验证后台 Blade 按钮声明的权限 slug 均由迁移写入 permissions 表且统一使用 admin 前缀。
 * - 输入：路由、控制器、Blade/JS、迁移等项目源码文本；输出：PHPUnit 断言结果。
 * - 明确不负责：不覆盖 HTTP 运行时业务流与 MT4 真实网关同步（由集成与功能测试锁定）。
 */

namespace Tests\Feature;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use Tests\TestCase;

/**
 * 后台 Blade 按钮权限覆盖测试。
 *
 * 功能逻辑说明：
 * - 后台页面按钮通过 `data-permission` 绑定 `permissions.slug`，用于 Layui/Blade 根据 `/api/admin/menus`
 *   返回的授权结果隐藏无权限按钮。
 * - 真正安全边界仍在后端 `check.permission:admin` 中间件，本测试只验证页面按钮 slug 已在迁移中声明，
 *   避免出现“页面写了按钮权限，但数据库权限字典永远没有该 slug”的配置断层。
 * - 本测试不连接真实 MySQL，只读取 Blade 与 migration 源码，适合当前 3307 数据库不可用时继续做静态质量约束。
 */
class AdminBladeButtonPermissionCoverageTest extends TestCase
{
    /**
     * 后台 Blade 中出现的 data-permission 必须能在权限迁移中找到对应 slug。
     *
     * 参数含义：
     * - $buttonPermissions：从后台 Blade 文件提取到的按钮权限 slug 列表，key 为 slug，value 为出现位置。
     * - $migrationSource：所有迁移源码拼接内容，用于确认权限 slug 是否被写入权限字典迁移。
     * - $slug：单个按钮权限 slug，例如 admin_role_create。
     * - $sourceFile：该按钮权限 slug 首次出现的 Blade 文件路径，用于断言失败时定位页面。
     *
     * @return void
     */
    public function test_admin_blade_button_permissions_are_declared_by_migrations(): void
    {
        $buttonPermissions = $this->collectBladeButtonPermissions();
        $migrationSource = $this->readMigrationSource();

        $this->assertNotEmpty($buttonPermissions, '后台 Blade 页面必须存在 data-permission 按钮权限标识。');

        foreach ($buttonPermissions as $slug => $sourceFile) {
            $this->assertStringContainsString(
                "'slug' => '" . $slug . "'",
                $migrationSource,
                $slug . ' 在 Blade 中使用，但未在权限迁移中声明。来源文件：' . $sourceFile
            );
        }
    }

    /**
     * 后台 Blade 按钮权限 slug 必须使用 admin_ 前缀。
     *
     * 参数含义：
     * - $buttonPermissions：从后台 Blade 文件提取到的按钮权限 slug 列表。
     * - $slug：单个按钮权限 slug，用于确认它属于后台 guard 的命名空间。
     *
     * @return void
     */
    public function test_admin_blade_button_permissions_use_admin_prefix(): void
    {
        foreach ($this->collectBladeButtonPermissions() as $slug => $sourceFile) {
            $this->assertStringStartsWith('admin_', $slug, $slug . ' 必须使用 admin_ 前缀。来源文件：' . $sourceFile);
        }
    }

    /**
     * 收集后台 Blade 页面中的按钮权限 slug。
     *
     * @return array<string, string> key=permissions.slug，value=首次出现该 slug 的 Blade 文件路径。
     */
    private function collectBladeButtonPermissions(): array
    {
        $permissions = [];
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(resource_path('admin/layui')));

        /** @var SplFileInfo $file */
        foreach ($iterator as $file) {
            if (! $file->isFile() || substr($file->getFilename(), -10) !== '.blade.php') {
                continue;
            }

            $content = file_get_contents($file->getPathname());
            preg_match_all('/data-permission="([^"]+)"/', $content, $matches);

            foreach ($matches[1] as $slug) {
                if (! isset($permissions[$slug])) {
                    $permissions[$slug] = $file->getPathname();
                }
            }
        }

        ksort($permissions);

        return $permissions;
    }

    /**
     * 读取全部迁移源码。
     *
     * @return string 所有 migration 文件内容拼接结果。
     */
    private function readMigrationSource(): string
    {
        $source = '';
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(database_path('migrations')));

        /** @var SplFileInfo $file */
        foreach ($iterator as $file) {
            if (! $file->isFile() || substr($file->getFilename(), -4) !== '.php') {
                continue;
            }

            $source .= "\n" . file_get_contents($file->getPathname());
        }

        return $source;
    }
}
