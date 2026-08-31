<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/06/07
 * Time: 21:12
 */

/**
 * AdminBladeButtonPermissionRouteCoverageTest
 *
 * 文件功能：
 * - 验证后台 Blade 按钮权限对应的 API 路由都已注册并处于权限保护之下。
 * - 输入：路由、控制器、Blade/JS、迁移等项目源码文本；输出：PHPUnit 断言结果。
 * - 明确不负责：不覆盖 HTTP 运行时业务流与 MT4 真实网关同步（由集成与功能测试锁定）。
 */

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use Tests\TestCase;

/**
 * 后台 Blade 按钮权限与后端路由覆盖测试。
 *
 * 功能逻辑说明：
 * - `data-permission` 只负责前端按钮显隐，真正安全边界必须落到 `permissions.api_route` 与
 *   `check.permission:admin` 中间件。
 * - 本测试从 Blade 提取按钮权限 slug，从迁移源码提取对应 api_route，再校验命名路由存在且挂载后台权限中间件。
 * - 本测试不连接真实 MySQL，适合在数据库不可用时继续验证权限链路的代码配置完整性。
 */
class AdminBladeButtonPermissionRouteCoverageTest extends TestCase
{
    /**
     * 后台 Blade 按钮权限必须绑定非空 api_route，且该路由必须挂载后台权限中间件。
     *
     * 参数含义：
     * - $buttonPermissions：Blade 中出现的按钮权限 slug 列表，key 为 permissions.slug，value 为来源文件。
     * - $permissionApiRoutes：迁移源码中声明的 slug 与 api_route 映射。
     * - $slug：单个按钮权限 slug，例如 admin_role_create。
     * - $apiRoute：Laravel 后台 API 命名路由，例如 admin_api_createRole。
     *
     * @return void
     */
    public function test_admin_blade_button_permissions_have_protected_api_routes(): void
    {
        $buttonPermissions = $this->collectBladeButtonPermissions();
        $permissionApiRoutes = $this->collectPermissionApiRoutes();

        $this->assertNotEmpty($buttonPermissions, '后台 Blade 页面必须存在 data-permission 按钮权限标识。');

        foreach ($buttonPermissions as $slug => $sourceFile) {
            $this->assertArrayHasKey($slug, $permissionApiRoutes, $slug . ' 未在迁移中声明 api_route。来源文件：' . $sourceFile);

            $apiRoute = $permissionApiRoutes[$slug];

            $this->assertNotSame('', $apiRoute, $slug . ' 是按钮权限，必须绑定非空 api_route。来源文件：' . $sourceFile);
            $this->assertTrue(Route::has($apiRoute), $slug . ' 绑定的命名路由不存在：' . $apiRoute);
            $this->assertContains(
                'check.permission:admin',
                Route::getRoutes()->getByName($apiRoute)->gatherMiddleware(),
                $slug . ' 绑定的命名路由未挂载 check.permission:admin：' . $apiRoute
            );
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
     * 从迁移源码中收集权限 slug 与 api_route 映射。
     *
     * @return array<string, string> key=permissions.slug，value=对应 Laravel 命名 API 路由。
     */
    private function collectPermissionApiRoutes(): array
    {
        $routes = [];
        $source = $this->readMigrationSource();

        preg_match_all(
            "/'slug'\\s*=>\\s*'([^']+)'[\\s\\S]{0,500}?'api_route'\\s*=>\\s*'([^']*)'/",
            $source,
            $matches,
            PREG_SET_ORDER
        );

        foreach ($matches as $match) {
            $slug = $match[1];
            $apiRoute = $match[2];

            if ($apiRoute !== '' || ! isset($routes[$slug])) {
                $routes[$slug] = $apiRoute;
            }
        }

        return $routes;
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
