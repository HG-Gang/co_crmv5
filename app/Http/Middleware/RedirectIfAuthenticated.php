<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/02
 * Time: 08:52
 */

namespace App\Http\Middleware;

use App\Providers\RouteServiceProvider;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * 已认证用户重定向中间件。
 *
 * 文件功能：
 * - 检测当前用户是否已经通过身份认证。
 * - 对已登录用户，重定向到首页（RouteServiceProvider::HOME），避免重复访问登录或注册页面。
 *
 * 适用场景：
 * - 登录页面、注册页面等仅限未登录用户访问的路由组。
 *
 * 入参例子：
 * - 支持传入守卫（guards）列表参数，若未指定则默认检查 null guard。
 *
 * 返回值：
 * - 通过时继续请求链（用户未登录时继续访问）。
 * - 不通过时返回重定向到首页的响应（用户已登录时跳转）。
 */
class RedirectIfAuthenticated
{
    /**
     * 处理已登录用户的重定向。
     *
     * 任一指定守卫已登录时重定向到首页，避免重复访问登录/注册页；全部守卫未登录时继续请求链。
     *
     * @param \Illuminate\Http\Request $request 当前请求对象。
     * @param \Closure(\Illuminate\Http\Request): (\Illuminate\Http\Response|\Illuminate\Http\RedirectResponse) $next 后续处理闭包。
     * @param string|null ...$guards 需要检查的守卫列表；为空时仅检查默认守卫。
     * @return \Illuminate\Http\Response|\Illuminate\Http\RedirectResponse 全部未登录时继续请求链；任一已登录则重定向首页。
     */
    public function handle(Request $request, Closure $next, ...$guards)
    {
        $guards = empty($guards) ? [null] : $guards;

        foreach ($guards as $guard) {
            if (Auth::guard($guard)->check()) {
                return redirect(RouteServiceProvider::HOME);
            }
        }

        return $next($request);
    }
}
