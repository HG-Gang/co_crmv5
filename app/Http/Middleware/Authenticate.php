<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/02
 * Time: 08:51
 */

namespace App\Http\Middleware;

use Illuminate\Auth\Middleware\Authenticate as Middleware;

/**
 * 用户身份验证中间件。
 *
 * 文件功能：
 * - 继承 Laravel 默认的身份验证中间件。
 * - 对未通过身份验证的用户，非 JSON 请求重定向到前台登录页面。
 *
 * 适用场景：
 * - 所有需要登录验证的前台页面路由组。
 *
 * 入参例子：
 * - 通过检查当前请求的 Session 或 Token 进行身份验证，验证失败时调用 redirectTo 方法获取重定向地址。
 *
 * 返回值：
 * - 通过时继续请求链。
 * - 不通过时，JSON 请求返回 401 响应，普通请求重定向到前台登录页面。
 */
class Authenticate extends Middleware
{
    /**
     * 获取未认证用户的重定向地址。
     *
     * @param \Illuminate\Http\Request $request 当前请求对象。
     * @return string|null 非 JSON 请求返回前台登录页路由；JSON 请求返回 null，交由框架返回 401。
     */
    protected function redirectTo($request)
    {
        if (! $request->expectsJson()) {
            return route('front_page_login');
        }
    }
}
