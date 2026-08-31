<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/02
 * Time: 08:51
 */

namespace App\Http\Middleware;

use Illuminate\Cookie\Middleware\EncryptCookies as Middleware;

/**
 * Cookie 加密中间件。
 *
 * 文件功能：
 * - 继承 Laravel 默认的 Cookie 加密中间件，对发送到浏览器的 Cookie 值自动加密。
 * - 可配置排除列表（$except）来指定不需要加密的 Cookie 名称。
 *
 * 适用场景：
 * - Web 路由组中使用，确保 Cookie 数据在客户端的安全传输和存储。
 *
 * 入参例子：
 * - 拦截响应中的 Set-Cookie 头，对 Cookie 值进行加密处理；请求时自动解密。
 *
 * 返回值：
 * - 通过时继续请求链。
 * - 不通过时始终通过（加密失败可能抛出异常）。
 */
class EncryptCookies extends Middleware
{
    /**
     * 不需要加密的 Cookie 名称列表。
     *
     * @var array<int, string>
     */
    protected $except = [
        //
    ];
}
