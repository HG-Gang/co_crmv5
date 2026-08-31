<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/02
 * Time: 08:53
 */

namespace App\Http\Middleware;

use Illuminate\Http\Middleware\TrustProxies as Middleware;
use Illuminate\Http\Request;

/**
 * 反向代理信任中间件。
 *
 * 文件功能：
 * - 继承 Laravel 默认的 TrustProxies 中间件，配置应用信任的反向代理服务器。
 * - 设置代理头映射规则，用于正确获取客户端的真实 IP、协议（HTTP/HTTPS）、端口和域名等信息。
 *
 * 适用场景：
 * - 全局路由组，适用于部署在负载均衡器、CDN 等反向代理后方的应用。
 *
 * 入参例子：
 * - 通过 X-Forwarded-For、X-Forwarded-Host、X-Forwarded-Port、X-Forwarded-Proto 等代理头解析客户端真实信息。
 *
 * 返回值：
 * - 通过时继续请求链。
 * - 不通过时始终通过（该中间件不拦截请求）。
 */
class TrustProxies extends Middleware
{
    /**
     * 受信代理列表。
     *
     * 当前未配置（null）时不信任任何代理：X-Forwarded-* 等代理头不生效，
     * 客户端 IP 取直连对端，安全边界收紧（部署在反向代理后时需显式配置）。
     *
     * @var array<int, string>|string|null
     */
    protected $proxies;

    /**
     * 用于识别代理转发信息的 Header 位掩码。
     *
     * 仅当代理在受信列表中时，以下 X-Forwarded-* 头才会被用于推导真实客户端信息。
     *
     * @var int
     */
    protected $headers =
        Request::HEADER_X_FORWARDED_FOR |
        Request::HEADER_X_FORWARDED_HOST |
        Request::HEADER_X_FORWARDED_PORT |
        Request::HEADER_X_FORWARDED_PROTO |
        Request::HEADER_X_FORWARDED_AWS_ELB;
}
