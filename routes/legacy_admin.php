<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/02
 * Time: 01:26
 */

/**
 * 旧项目后台路由桥接文件。
 *
 * 文件功能：
 * - 读取 storage/app/audits/legacy-routes.json 中的旧项目路由清单（URI 与 HTTP 方法）。
 * - 仅登记 index 开头的旧后台页面 URI，统一转发到 LegacyAdminController::handle 渲染旧页面。
 * - 登录、验证码等公开入口不加鉴权；其余旧页面路由挂载 legacy.admin.auth 中间件，
 *   并通过 legacy_permission_route 默认参数绑定旧 URI 对应的后台权限 slug，供权限中间件二次鉴权。
 *
 * 适用场景：
 * - 旧项目后台页面（index/admin/...）迁移过渡期，保证旧链接仍可访问。
 *
 * 运行方式：
 * - 路由文件由框架自动加载；legacy-routes.json 缺失或非法时函数直接返回，不影响其他路由。
 */

use App\Http\Controllers\Admin\LegacyAdminController;
use Illuminate\Support\Facades\Route;

if (! function_exists('crm_register_legacy_admin_routes')) {
    function crm_register_legacy_admin_routes(): void
    {
        $inventoryPath = storage_path('app/audits/legacy-routes.json');
        if (! is_file($inventoryPath)) {
            return;
        }

        $routes = json_decode((string) file_get_contents($inventoryPath), true);
        if (! is_array($routes)) {
            return;
        }

        foreach ($routes as $legacyRoute) {
            $uri = ltrim((string) ($legacyRoute['uri'] ?? ''), '/');
            if ($uri !== 'index' && strpos($uri, 'index/') !== 0) {
                continue;
            }

            $methods = array_values(array_diff(array_map('strtoupper', $legacyRoute['methods'] ?? []), ['HEAD']));
            if ($methods === []) {
                continue;
            }

            $route = Route::match($methods, $uri, [LegacyAdminController::class, 'handle'])
                ->name('legacy_admin_' . substr(md5($uri . '|' . implode(',', $methods)), 0, 16));

            if (! in_array($uri, [
                'index/admin/login',
                'index/admin/captcha',
                'index/admin/logon',
                'index/admin/logout',
            ], true)) {
                $route->middleware('legacy.admin.auth');
                $permissionRoute = LegacyAdminController::permissionRouteForLegacyUri($uri);
                if ($permissionRoute) {
                    $route->defaults('legacy_permission_route', $permissionRoute);
                }
            }
        }
    }
}

crm_register_legacy_admin_routes();
