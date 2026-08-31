<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/02
 * Time: 01:26
 */

/**
 * HTTP 内核类。
 *
 * 文件功能：
 * - 集中声明应用全局 HTTP 中间件栈（$middleware），每个请求都会依次执行。
 * - 声明路由中间件组（$middlewareGroups）：web 组用于页面请求，api 组用于接口请求。
 * - 声明路由级中间件别名（$routeMiddleware），供路由文件按名称引用。
 *
 * 适用场景：
 * - 新增全局中间件时修改 $middleware，例如 TrustProxies、CORS、维护模式拦截。
 * - 新增路由中间件组或调整 web/api 组内中间件顺序时修改 $middlewareGroups。
 * - 新增可按名称引用的中间件时修改 $routeMiddleware。
 *
 * 本类不接收业务入参，无业务返回值；中间件配置错误时会在请求阶段抛出中间件解析异常。
 */
namespace App\Http;

use Illuminate\Foundation\Http\Kernel as HttpKernel;

class Kernel extends HttpKernel
{
    /**
     * 全局 HTTP 中间件栈：每个进入应用的请求（不分 web/api）都会按序执行。
     * 承担代理可信声明、CORS、维护模式拦截、请求体大小校验、字符串 trim 与空串转 null
     * 等请求级预处理；此处缺失或顺序颠倒会导致后续路由拿到未归一化的输入或暴露维护期入口。
     *
     * @var array<int, class-string|string>
     */
    protected $middleware = [
        // \App\Http\Middleware\TrustHosts::class,
        \App\Http\Middleware\TrustProxies::class,
        \Fruitcake\Cors\HandleCors::class,
        \App\Http\Middleware\PreventRequestsDuringMaintenance::class,
        \Illuminate\Foundation\Http\Middleware\ValidatePostSize::class,
        \App\Http\Middleware\TrimStrings::class,
        \Illuminate\Foundation\Http\Middleware\ConvertEmptyStringsToNull::class,
    ];

    /**
     * 路由中间件分组注册：web 组服务页面请求（请求链路追踪、Cookie 加解密、会话、CSRF、多语言），
     * api 组服务接口请求（追踪、多语言、限流、路由绑定）；分组名即路由文件中 ->middleware('web'|'api') 的引用键，
     * 调整组内顺序会改变会话与 CSRF 的生效时机，属破坏性变更。
     *
     * @var array<string, array<int, class-string|string>>
     */
    protected $middlewareGroups = [
        'web' => [
            \App\Http\Middleware\RequestTraceMiddleware::class,
            \App\Http\Middleware\EncryptCookies::class,
            \Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse::class,
            \Illuminate\Session\Middleware\StartSession::class,
            // \Illuminate\Session\Middleware\AuthenticateSession::class,
            \Illuminate\View\Middleware\ShareErrorsFromSession::class,
            \App\Http\Middleware\VerifyCsrfToken::class,
            \App\Http\Middleware\SetLocale::class,
            \Illuminate\Routing\Middleware\SubstituteBindings::class,
        ],

        'api' => [
            // \Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful::class,
            \App\Http\Middleware\RequestTraceMiddleware::class,
            \App\Http\Middleware\SetLocale::class,
            'throttle:api',
            \Illuminate\Routing\Middleware\SubstituteBindings::class,
        ],
    ];

    /**
     * 路由级中间件别名注册表：键（如 jwt.auth、check.permission、legacy.admin.auth）
     * 是路由文件中按名称引用的契约，值指向具体中间件类；别名改名或删除会让引用它的路由直接抛出解析异常。
     *
     * @var array<string, class-string|string>
     */
    protected $routeMiddleware = [
        'auth' => \App\Http\Middleware\Authenticate::class,
        'auth.basic' => \Illuminate\Auth\Middleware\AuthenticateWithBasicAuth::class,
        'cache.headers' => \Illuminate\Http\Middleware\SetCacheHeaders::class,
        'can' => \Illuminate\Auth\Middleware\Authorize::class,
        'guest' => \App\Http\Middleware\RedirectIfAuthenticated::class,
        'password.confirm' => \Illuminate\Auth\Middleware\RequirePassword::class,
        'signed' => \Illuminate\Routing\Middleware\ValidateSignature::class,
        'throttle' => \Illuminate\Routing\Middleware\ThrottleRequests::class,
        'verified' => \Illuminate\Auth\Middleware\EnsureEmailIsVerified::class,
        'jwt.auth' => \App\Http\Middleware\JwtAuthMiddleware::class,
        'sso' => \App\Http\Middleware\SingleSignOn::class,
        'check.permission' => \App\Http\Middleware\CheckPermission::class,
        'legacy.admin.auth' => \App\Http\Middleware\LegacyAdminAuthenticate::class,
        'set.locale' => \App\Http\Middleware\SetLocale::class,
        'legacy.front.auth' => \App\Http\Middleware\LegacyFrontAuthenticate::class,
    ];
}
