<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/17
 * Time: 01:31
 */

/**
 * AdminPageMenuPermissionCoverageTest
 *
 * 文件功能：
 * - 验证后台 Blade 菜单页面在数据库 permissions 表中拥有唯一的页面路由权限。
 * - 输入：权限/结构迁移类与测试数据库；输出：PHPUnit 断言结果。
 * - 明确不负责：不验证 check.permission 中间件的运行时鉴权与按钮渲染（由模块契约测试锁定）。
 */

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * 后台 Blade 页面菜单权限覆盖测试。
 *
 * 功能逻辑说明：
 * - 用户要求后台所有菜单必须可控，且权限数据必须从数据表配置得到。
 * - 本测试直接读取真实 DB 的 permissions 表，验证后台 Blade 页面路由是否都有唯一启用的菜单权限。
 * - 登录页、Naive 兜底页、个人资料页、用户详情页和认证详情页不是左侧菜单入口，因此列入页面权限白名单。
 */
class AdminPageMenuPermissionCoverageTest extends TestCase
{
    /**
     * 后台 Blade 菜单页面必须在真实 permissions 表中存在唯一启用 route 配置。
     *
     * 参数含义：
     * - $pageRoutes：当前 Laravel 已注册的 admin_page_* 页面路由，key 为路由名，value 为访问路径。
     * - $ignoredRouteNames：不作为左侧菜单入口控制的后台页面路由名白名单。
     * - $permissionRoutes：真实 DB 中 guard_type=admin 且 status=1 的权限 route 统计结果。
     * - $missingRoutes：已注册页面中缺少 permissions.route 配置的页面集合。
     * - $duplicateRoutes：真实 DB 中同一个页面 route 被多条启用权限重复配置的集合。
     *
     * @return void
     */
    public function test_admin_blade_menu_pages_have_unique_database_permission_routes(): void
    {
        $pageRoutes = $this->collectAdminBladePageRoutes();
        $ignoredRouteNames = [
            'admin_page_login',
            'admin_page_modern_app',
            'admin_page_profile_edit',
            'admin_page_profile_change_password',
            'admin_page_users_detail',
            'admin_page_authentication_detail',
            'admin_page_authentication_detail_invalid',
        ];

        $permissionRoutes = DB::table('permissions')
            ->select('route', DB::raw('COUNT(*) as total'), DB::raw('GROUP_CONCAT(slug ORDER BY id SEPARATOR ",") as slugs'))
            ->where('guard_type', 'admin')
            ->where('status', 1)
            ->whereNotNull('route')
            ->where('route', '<>', '')
            ->where('slug', 'not like', 'test\_%')
            ->groupBy('route')
            ->get()
            ->keyBy('route');

        $missingRoutes = [];
        foreach ($pageRoutes as $routeName => $path) {
            if (in_array($routeName, $ignoredRouteNames, true)) {
                continue;
            }

            if (! $permissionRoutes->has($path)) {
                $missingRoutes[$routeName] = $path;
            }
        }

        $duplicateRoutes = $permissionRoutes
            ->filter(function ($row) {
                return (int) $row->total > 1;
            })
            ->map(function ($row) {
                return [
                    'total' => (int) $row->total,
                    'slugs' => explode(',', (string) $row->slugs),
                ];
            })
            ->all();

        $this->assertSame([], $missingRoutes, '以下后台 Blade 菜单页面缺少真实 DB permissions.route 配置。');
        $this->assertSame([], $duplicateRoutes, '以下后台 Blade 菜单页面在真实 DB 中存在重复启用 permissions.route 配置。');
    }

    /**
     * 收集当前项目已注册的后台 Blade 页面路由。
     *
     * 返回值说明：
     * - key：Laravel 命名路由，例如 admin_page_dashboard。
     * - value：浏览器访问路径，例如 /admin/dashboard。
     *
     * @return array<string, string>
     */
    private function collectAdminBladePageRoutes(): array
    {
        $routes = [];

        foreach (Route::getRoutes() as $route) {
            $routeName = $route->getName();

            if (! $routeName || strpos($routeName, 'admin_page_') !== 0) {
                continue;
            }

            $routes[$routeName] = '/' . $route->uri();
        }

        asort($routes);

        return $routes;
    }
}
