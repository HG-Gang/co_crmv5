<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/02
 * Time: 08:53
 */

namespace App\Http\Middleware;

use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken as Middleware;

/**
 * CSRF 令牌验证中间件。
 *
 * 文件功能：
 * - 继承 Laravel 默认的 CSRF 验证中间件，防止跨站请求伪造攻击。
 * - 配置排除列表（$except），指定不需要 CSRF 验证的 URI 路径（主要是第三方支付回调通知接口）。
 *
 * 适用场景：
 * - 所有 POST、PUT、PATCH、DELETE 等有副作用的 Web 路由。
 *
 * 入参例子：
 * - 验证请求中的 _token 字段或 X-CSRF-TOKEN 请求头与 Session 中的 CSRF Token 是否一致。
 *
 * 返回值：
 * - 通过时继续请求链。
 * - 不通过时返回 419 Token Mismatch 响应。
 *
 * 安全边界：
 * - 排除列表是窄白名单，仅覆盖第三方支付回调 URI；其余所有副作用请求保持默认 CSRF 校验。
 * - 校验失败（token 缺失或不匹配）返回 419 失败关闭，不会绕过校验进入业务。
 */
class VerifyCsrfToken extends Middleware
{
    /**
     * 免除 CSRF 校验的 URI 列表。
     *
     * 白名单只放行第三方支付回调：回调方无法持有本站 Session 的 CSRF token，因此必须例外。
     * 除回调外的接口一律保留默认 CSRF 校验。
     *
     * @var array<int, string>
     */
    protected $except = [
        'user/deposit_notfiy',
        'user/deposit_notfiy2',
        'user/deposit_tigerpay_notify',
        'user/deposit_wppay_notify',
        'user/deposit_exlink_bbnotify',
        'user/deposit_exlink_fbnotify',
        'user/deposit_btb_notify',
        'user/deposit_passto_notify',
        'user/deposit_switch_notify',
        'user/deposit_notfiy_otc',
        'user/withdraw_notfiy_otc',
        'user/withdraw_verify_otc',
    ];
}
