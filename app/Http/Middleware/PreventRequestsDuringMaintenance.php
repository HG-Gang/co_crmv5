<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/02
 * Time: 08:52
 */

namespace App\Http\Middleware;

use Illuminate\Foundation\Http\Middleware\PreventRequestsDuringMaintenance as Middleware;

/**
 * 维护模式下请求拦截中间件。
 *
 * 文件功能：
 * - 继承 Laravel 默认的维护模式中间件，在应用处于维护状态时拦截所有 HTTP 请求。
 * - 可配置排除列表（$except）来指定维护期间仍可访问的 URI 路径。
 *
 * 适用场景：
 * - 全局 Web 路由组中使用，确保维护期间系统的安全性和可控性。
 *
 * 入参例子：
 * - 无需请求参数，通过检查应用是否执行了 php artisan down 命令来判断是否拦截。
 *
 * 返回值：
 * - 通过时继续请求链。
 * - 不通过时返回 503 Service Unavailable 响应。
 */
class PreventRequestsDuringMaintenance extends Middleware
{
    /**
     * 维护模式下仍可访问的 URI 路径列表。
     *
     * @var array<int, string>
     */
    protected $except = [
        //
    ];
}
