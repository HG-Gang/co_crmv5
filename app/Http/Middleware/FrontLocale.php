<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/02
 * Time: 08:51
 */
namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;

/**
 * 前台语言区域设置中间件。
 *
 * 文件功能：
 * - 从会话（Session）中读取当前语言区域设置，默认值为配置文件中的 app.locale（zh_CN）。
 * - 将语言区域应用到应用程序实例。
 *
 * 适用场景：
 * - 前台页面路由组中使用，用于保持用户的语言偏好。
 *
 * 入参例子：
 * - 直接读取 Session 中的 locale 值，无需请求参数。
 *
 * 返回值：
 * - 通过时继续请求链。
 * - 不通过时始终通过（该中间件不拦截请求）。
 */
class FrontLocale
{
    /**
     * 从 Session 读取前台语言偏好并应用到当前应用实例。
     *
     * 语言区域只来自 Session（默认取配置 app.locale），不读取请求参数，避免客户端随意切换前台语言。
     *
     * @param Request $request 当前请求对象。
     * @param Closure $next 后续处理闭包。
     * @return mixed 始终继续请求链（本中间件不拦截请求）。
     */
    public function handle(Request $request, Closure $next)
    {
        $locale = session('locale', config('app.locale', 'zh_CN'));
        App::setLocale($locale);
        return $next($request);
    }
}
