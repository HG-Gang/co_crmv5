<?php

/**
 * Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/09/03
 * Time: 14:30
 */

namespace App\Http\Controllers\Common;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Session;

/**
 * 公共语言切换控制器。
 *
 * 文件功能：
 * - 处理前台与后台页面顶部的语言切换请求。
 * - 根据 URL 传入的 locale 参数切换站点语言，并写入 session 供后续请求保持语言选择。
 *
 * 适用场景：
 * - 前台与后台页面点击语言切换按钮时调用。
 * - 路由示例：`GET /lang/{locale}`。
 *
 * 入参例子：
 * - GET /lang/zh-CN：切换到简体中文。
 * - GET /lang/en：切换到英文。
 *
 * 返回值：
 * - 切换成功后重定向回来源页面（redirect()->back()）。
 *
 * 异常或失败场景：
 * - locale 不在白名单（zh-CN、en）时忽略本次切换，不报错，仍重定向回来源页面。
 */
class LanguageController extends Controller
{
    /**
     * 切换当前站点语言。
     *
     * @param Request $request 当前 HTTP 请求对象，仅用于重定向回来源页面。
     * @param string $locale 目标语言标识，只允许 zh-CN（简体中文）和 en（英文）。
     * @return \Illuminate\Http\RedirectResponse 语言切换成功或忽略后重定向回来源页面。
     */
    public function switchLang(Request $request, $locale)
    {
        if (in_array($locale, ['zh-CN', 'en'])) {
            Session::put('locale', $locale);
            App::setLocale($locale);
        }

        return redirect()->back();
    }
}
