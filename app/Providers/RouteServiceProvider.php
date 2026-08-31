<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/02
 * Time: 09:10
 */

/**
 * 路由服务提供者。
 *
 * 文件功能：
 * - 注册前台 API（api/front）、后台 API（api/admin）、Web 页面与代理商模块四组路由。
 * - 配置 'api' 限流器：登录用户按用户 ID 限流，未登录用户按 IP 限流，每分钟 60 次。
 * - 应用启动完成后注册旧项目命名路由别名与 Blade 路由别名，兼容旧页面跳转。
 *
 * 适用场景：
 * - 应用启动时自动加载；新增路由文件时在此挂载。
 *
 * 方法功能：
 * - boot()：调用 configureRateLimiting() 配置限流，并加载 front.php / admin.php / web.php / agent.php 路由文件。
 * - configureRateLimiting()：为 'api' 限流器定义按用户或 IP 的每分钟 60 次限制。
 *
 * 返回值：
 * - 所有方法均无业务返回值。
 *
 * 异常或失败场景：
 * - 路由文件不存在或存在重复路由定义时在启动阶段抛出异常。
 */
namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;

class RouteServiceProvider extends ServiceProvider
{
    /**
     * 控制器根命名空间：路由文件按子目录自动映射到
     * App\Http\Controllers\Front / Admin 等子命名空间。
     */
    protected $namespace = 'App\\Http\\Controllers';

    /**
     * 启动阶段：配置 API 限流并加载四组路由文件
     * （api/front、api/admin、web、agent），
     * 应用完全启动后再注册旧项目命名路由与 Blade 路由别名。
     *
     * @return void 无返回值。
     */
    public function boot()
    {
        $this->configureRateLimiting();
        $this->routes(function () {
            // 前台 API：api/front 前缀，控制器命名空间 App\Http\Controllers\Front。
            Route::prefix('api/front')
                ->middleware('api')
                ->namespace($this->namespace . '\\Front')
                ->group(base_path('routes/front.php'));

            // 后台 API：api/admin 前缀，控制器命名空间 App\Http\Controllers\Admin。
            Route::prefix('api/admin')
                ->middleware('api')
                ->namespace($this->namespace . '\\Admin')
                ->group(base_path('routes/admin.php'));

            // Web 页面路由：走 web 中间件（会话、CSRF），默认控制器命名空间。
            Route::middleware('web')
                ->namespace($this->namespace)
                ->group(base_path('routes/web.php'));

            // 代理商模块独立路由：与 web 路由同组加载，文件内显式声明前缀与命名空间。
            Route::middleware('web')
                ->namespace($this->namespace)
                ->group(base_path('routes/agent.php'));
        });

        $this->booted(function () {
            $this->app->booted(function () {
                // 全部路由注册完成后才挂旧项目命名路由别名与 Blade 别名，
                // 保证别名指向的路由名已存在，避免跳转时解析失败。
                if (function_exists('crm_register_legacy_named_route_aliases')) {
                    crm_register_legacy_named_route_aliases();
                }

                if (function_exists('crm_register_blade_route_aliases')) {
                    crm_register_blade_route_aliases();
                }
            });
        });
    }

    /**
     * 配置 'api' 限流器：登录用户按用户 ID 限流、未登录用户按 IP 限流，
     * 均为每分钟 60 次，防止接口被批量调用打爆。
     *
     * @return void 无返回值。
     */
    protected function configureRateLimiting()
    {
        RateLimiter::for('api', function (Request $request) {
            $user = $request->user();
            // 已登录按用户维度计数（同用户多 IP 共享额度），未登录按 IP 兜底。
            return Limit::perMinute(60)->by($user ? $user->id : $request->ip());
        });
    }
}
