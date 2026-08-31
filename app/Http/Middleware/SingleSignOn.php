<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/02
 * Time: 08:52
 */

namespace App\Http\Middleware;

use Closure;
use App\Constants\ResponseCode;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

/**
 * 单点登录（SSO）校验中间件。
 *
 * 文件功能：
 * - 读取 JwtAuthMiddleware 写入的 jwt_payload，校验其包含 jti、sub、guard 完整字段。
 * - 以 sso:{guard}:{sub} 为键读取 Cache 中的当前有效 jti，与请求 token 的 jti 一致才放行。
 *
 * 安全边界：
 * - 载荷缺失或字段不完整时按认证失败处理，不进入业务控制器。
 * - Cache 无记录或 jti 不一致表示已退出或被后台强制下线，返回 SSO 冲突错误。
 * - Cache 不可用时异常上抛由全局处理兜底，任何情况下都不把 SSO 校验当作未发生而放行。
 */
class SingleSignOn
{
    use ApiResponse;

    /**
     * 校验当前 token 是否为该登录主体的最新有效会话。
     *
     * @param Request $request 当前请求对象，需携带 JwtAuthMiddleware 写入的 jwt_payload。
     * @param Closure $next 校验通过后的后续处理闭包。
     * @param string|null $guard 可选守卫参数，兼容调用方显式传参；实际校验仍以载荷中的 guard 为准。
     * @return mixed 校验通过返回后续响应；失败返回 SSO 冲突或认证失败统一 JSON 错误（失败关闭）。
     */
    public function handle(Request $request, Closure $next, $guard = null)
    {
        $payload = $request->attributes->get('jwt_payload');

        if (!$payload || !isset($payload->jti) || !isset($payload->sub) || !isset($payload->guard)) {
            // jwt.auth 应先写入完整载荷；缺失时按认证失败处理，避免不完整 token 继续进入业务控制器。
            return $this->error(__('response.auth_failed'), ResponseCode::AUTH_FAILED);
        }

        // $cacheKey 表示当前登录主体的唯一有效会话。forceOffline/logout 会清理该键，缺失时必须视为会话已失效。
        $cacheKey = "sso:{$payload->guard}:{$payload->sub}";
        $activeJti = Cache::get($cacheKey);

        // 只有缓存中的 jti 与当前 token 完全一致才允许继续访问；缓存缺失表示已退出或被后台强制下线。
        if (!$activeJti || $activeJti !== $payload->jti) {
            return $this->error(__('response.sso_conflict'), ResponseCode::SSO_CONFLICT);
        }

        return $next($request);
    }
}
