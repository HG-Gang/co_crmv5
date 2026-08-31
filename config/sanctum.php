<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/02
 * Time: 01:27
 */

/**
 * Laravel Sanctum（SPA/API 认证）配置。
 *
 * 配置用途：
 * - 定义无状态 API 认证的状态域、守卫、令牌有效期与中间件覆盖。
 * - 项目登录主链路使用 JWT + SSO（见 config/jwt.php），Sanctum 保留框架默认能力备用。
 *
 * 注意：
 * - stateful 列表决定哪些域名走 Cookie 会话认证，需与实际部署域名保持一致；
 * - expiration=null 表示个人访问令牌永不过期，如开放第三方令牌需评估风险。
 */

return [

    /*
    |--------------------------------------------------------------------------
    | Stateful Domains
    |--------------------------------------------------------------------------
    |
    | Requests from the following domains / hosts will receive stateful API
    | authentication cookies. Typically, these should include your local
    | and production domains which access your API via a frontend SPA.
    |
    */

    // 走 Cookie 状态化认证的域名列表（默认含 localhost 与 APP_URL 主机名，逗号分隔）。
    'stateful' => explode(',', env('SANCTUM_STATEFUL_DOMAINS', sprintf(
        '%s%s',
        'localhost,localhost:3000,127.0.0.1,127.0.0.1:8000,::1',
        env('APP_URL') ? ','.parse_url(env('APP_URL'), PHP_URL_HOST) : ''
    ))),

    /*
    |--------------------------------------------------------------------------
    | Sanctum Guards
    |--------------------------------------------------------------------------
    |
    | This array contains the authentication guards that will be checked when
    | Sanctum is trying to authenticate a request. If none of these guards
    | are able to authenticate the request, Sanctum will use the bearer
    | token that's present on an incoming request for authentication.
    |
    */

    // Sanctum 认证时优先检查的守卫（web 会话），失败再回退 Bearer token。
    'guard' => ['web'],

    /*
    |--------------------------------------------------------------------------
    | Expiration Minutes
    |--------------------------------------------------------------------------
    |
    | This value controls the number of minutes until an issued token will be
    | considered expired. If this value is null, personal access tokens do
    | not expire. This won't tweak the lifetime of first-party sessions.
    |
    */

    // 个人访问令牌有效期（分钟）：null=永不过期（默认）。
    'expiration' => null,

    /*
    |--------------------------------------------------------------------------
    | Sanctum Middleware
    |--------------------------------------------------------------------------
    |
    | When authenticating your first-party SPA with Sanctum you may need to
    | customize some of the middleware Sanctum uses while processing the
    | request. You may change the middleware listed below as required.
    |
    */

    // Sanctum 中间件覆盖：复用项目 CSRF 校验与 Cookie 加密中间件。
    'middleware' => [
        'verify_csrf_token' => App\Http\Middleware\VerifyCsrfToken::class, // CSRF 校验中间件绑定。
        'encrypt_cookies' => App\Http\Middleware\EncryptCookies::class, // Cookie 加密中间件绑定。
    ],

];
