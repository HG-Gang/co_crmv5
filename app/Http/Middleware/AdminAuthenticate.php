<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/02
 * Time: 08:50
 */
namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * 后台 Session 鉴权中间件。
 *
 * 文件功能：
 * - 本中间件用于仍然依赖 Laravel Session guard 的后台 Blade 页面保护。
 * - 当前主后台 API 已使用 jwt.auth:admin、sso:admin 和 check.permission:admin；这里保留给页面路由或兼容入口使用。
 * - JSON 请求未登录时返回多语言认证失败文案；普通页面请求未登录时跳转后台登录页。
 *
 * 安全边界：
 * - 未登录请求直接失败关闭：JSON 请求返回 401，页面请求重定向后台登录页，不会进入业务控制器。
 * - 鉴权只依赖 admin guard 会话状态，不记录、不返回任何令牌或用户凭证信息。
 */
class AdminAuthenticate
{
    /**
     * 校验后台管理员是否已通过 admin guard 登录。
     *
     * 参数逻辑说明：
     * - $request 表示当前后台页面请求对象，用于判断是否期望 JSON 响应以及后续路由跳转。
     * - $next 表示通过鉴权后的下一个请求处理闭包，只有 admin guard 已登录时才会继续执行。
     * - expectsJson 表示前端希望接收 JSON 响应，此时不能重定向页面，必须返回可被 JS 识别的状态消息。
     * - admin_page_login 表示后台 Blade 登录页命名路由，普通浏览器页面未登录时跳转到该入口。
     *
     * @param Request $request 当前 HTTP 请求对象，承载请求头、路由和响应类型判断信息。
     * @param Closure $next 鉴权通过后的后续处理闭包。
     * @return mixed 已登录时返回后续响应；未登录时返回 JSON 401 或登录页重定向。
     */
    public function handle(Request $request, Closure $next)
    {
        // 未登录即失败关闭：期望 JSON 的前端请求返回 401，普通浏览器页面重定向后台登录页。
        if (!Auth::guard('admin')->check()) {
            if ($request->expectsJson()) {
                return response()->json(['message' => __('response.auth_failed')], 401);
            }
            return redirect()->route('admin_page_login');
        }
        return $next($request);
    }
}
