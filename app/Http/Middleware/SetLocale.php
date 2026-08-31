<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/02
 * Time: 12:47
 */

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Session;

/**
 * 语言区域自动检测与设置中间件。
 *
 * 文件功能：
 * - 按优先级自动检测并设置语言区域：请求头 X-Locale > URL 查询参数 locale > Session > 浏览器 Accept-Language > 配置文件默认值。
 * - 支持 zh-CN 和 en 两种语言。
 * - 将识别到的语言区域存储到 Session 中以备后续请求使用。
 *
 * 适用场景：
 * - 全局路由组，确保前后端请求都能正确获取用户的语言偏好。
 *
 * 入参例子：
 * - API 请求：通过请求头 X-Locale: zh-CN 指定语言。
 * - 页面切换：通过 URL 查询参数 ?locale=en 切换语言。
 * - 无指定时：自动检测浏览器 Accept-Language 请求头。
 *
 * 返回值：
 * - 通过时继续请求链。
 * - 不通过时始终通过（不支持的语言保持默认值不变）。
 */
class SetLocale
{
    /**
     * 按优先级检测并设置应用语言区域。
     *
     * 优先级：X-Locale 请求头 > locale 查询参数 > Session > 浏览器 Accept-Language > 配置默认值；
     * 非白名单语言保持默认值不变，防止任意值写入 Session。
     *
     * @param Request $request 当前 HTTP 请求对象。
     * @param Closure $next 下一个中间件或控制器闭包。
     * @return mixed 语言设置完成后继续请求链。
     */
    public function handle(Request $request, Closure $next)
    {
        $sessionLocale = $request->hasSession() ? Session::get('locale') : null;

        // 语言优先级：JS/API 请求头 X-Locale > URL 查询参数 locale > Session > 浏览器 Accept-Language > 配置默认值。
        // Blade 页面用 ?locale=zh-CN|en 整页切换语言，API 请求继续使用 X-Locale 请求头。
        $locale = $request->header('X-Locale')
            ?? $request->query('locale')
            ?? $sessionLocale
            ?? $this->getBrowserLocale($request)
            ?? config('app.locale');

        // 只接受 zh-CN 与 en 白名单语言；其它值保持默认语言不变，避免任意值写入 Session。
        if (in_array($locale, ['zh-CN', 'en'])) {
            App::setLocale($locale);
            if ($request->hasSession()) {
                Session::put('locale', $locale);
            }
        }

        return $next($request);
    }

    /**
     * 从浏览器 Accept-Language 请求头粗略识别语言。
     *
     * @param Request $request 当前请求对象。
     * @return string|null 命中 zh 返回 zh-CN，命中 en 返回 en；均未命中返回 null（走配置默认值）。
     */
    protected function getBrowserLocale(Request $request): ?string
    {
        $acceptLang = $request->header('Accept-Language', '');
        if (strpos($acceptLang, 'zh') !== false) {
            return 'zh-CN';
        }
        if (strpos($acceptLang, 'en') !== false) {
            return 'en';
        }
        return null;
    }
}
