<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/06/08
 * Time: 02:04
 */

/**
 * FrontMenuPermissionRouteConsistencyTest
 *
 * 文件功能：
 * - 验证前台菜单配置的 api_route 都存在于当前路由表，缺失的命名路由被一次性收集输出。
 * - 输入：路由、控制器、Blade/JS、迁移等项目源码文本；输出：PHPUnit 断言结果。
 * - 明确不负责：不覆盖 HTTP 运行时业务流与 MT4 真实网关同步（由集成与功能测试锁定）。
 */

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use ReflectionClass;
use Tests\TestCase;

/**
 * 前台菜单权限配置与真实路由一致性测试。
 *
 * 功能逻辑说明：
 * - 前台 Layui 菜单由 /api/front/navigation/menus 返回，菜单数据最终来自 permissions 表。
 * - permissions.api_route 保存该菜单主要读取或操作接口的 Laravel 命名路由。
 * - 本测试直接读取前台菜单修复迁移中的配置，确保每个 api_route 都能在当前路由表中找到，避免菜单授权成功但页面接口不存在。
 */
class FrontMenuPermissionRouteConsistencyTest extends TestCase
{
    /**
     * 前台菜单配置中的 api_route 必须全部对应真实 Laravel 命名路由。
     *
     * 参数逻辑说明：
     * - $menu：迁移里定义的单个父级菜单配置，包含 slug、route、api_route 与 children。
     * - $child：迁移里定义的子菜单配置，子菜单通常对应真实 Blade 页面与主要业务接口。
     * - api_route 为空表示目录型菜单没有独立接口，允许跳过。
     *
     * @return void
     */
    public function test_front_menu_api_routes_exist_in_current_route_table(): void
    {
        $migrationPath = database_path('migrations/2026_06_07_000014_fix_default_admin_and_front_menu_roles.php');

        $this->assertFileExists($migrationPath, '前台菜单角色修复迁移文件不存在。');

        require_once $migrationPath;

        $reflection = new ReflectionClass(\FixDefaultAdminAndFrontMenuRoles::class);
        $method = $reflection->getMethod('frontMenuTree');
        $method->setAccessible(true);

        $menus = $method->invoke(new \FixDefaultAdminAndFrontMenuRoles());
        $missingRoutes = [];

        foreach ($menus as $menu) {
            $this->collectMissingApiRoutes($menu, $missingRoutes);
            foreach (($menu['children'] ?? []) as $child) {
                $this->collectMissingApiRoutes($child, $missingRoutes);
            }
        }

        $this->assertSame([], $missingRoutes, '前台菜单 permissions.api_route 存在未注册的命名路由。');
    }

    /**
     * 收集单条菜单配置中不存在的 api_route。
     *
     * 参数逻辑说明：
     * - $permission：单条权限配置数组，slug 表示权限标识，api_route 表示后端命名路由。
     * - $missingRoutes：按引用累积缺失结果，便于一次性输出所有错误配置。
     *
     * @param array<string, mixed> $permission 单条前台菜单权限配置。
     * @param array<int, string> $missingRoutes 缺失路由描述列表。
     * @return void
     */
    private function collectMissingApiRoutes(array $permission, array &$missingRoutes): void
    {
        $apiRoute = (string) ($permission['api_route'] ?? '');

        if ($apiRoute === '') {
            return;
        }

        if (!Route::has($apiRoute)) {
            $missingRoutes[] = ($permission['slug'] ?? 'unknown') . ' => ' . $apiRoute;
        }
    }
}
