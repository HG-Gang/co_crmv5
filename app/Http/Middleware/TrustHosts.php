<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/02
 * Time: 08:53
 */

namespace App\Http\Middleware;

use Illuminate\Http\Middleware\TrustHosts as Middleware;

/**
 * 可信域名中间件。
 *
 * 文件功能：
 * - 继承 Laravel 默认的 TrustHosts 中间件，配置应用允许的可信主机名模式。
 * - 当前配置为信任应用 URL 的所有子域名。
 *
 * 适用场景：
 * - 全局路由组，防止 Host 头攻击，确保只处理来自可信域名的请求。
 *
 * 入参例子：
 * - 检查 HTTP 请求头中的 Host 字段是否匹配 hosts() 方法返回的可信模式列表。
 *
 * 返回值：
 * - 通过时继续请求链。
 * - 不通过时返回错误响应（Host 不匹配时拒绝请求）。
 */
class TrustHosts extends Middleware
{
    /**
     * 返回可信主机名模式列表。
     *
     * @return array<int, string|null> 当前信任应用 URL 的所有子域名。
     */
    public function hosts()
    {
        return [
            $this->allSubdomainsOfApplicationUrl(),
        ];
    }
}
