<?php

namespace App\Http\Middleware;

use Closure;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

/**
 * Single Sign-On (SSO) Middleware
 * 单点登录（SSO）中间件
 */
class SingleSignOn
{
    use ApiResponse;

    /**
     * Handle an incoming request.
     * 处理进入请求
     *
     * @param Request $request 请求对象
     * @param Closure $next 下一个处理程序
     * @return mixed
     */
    public function handle(Request $request, Closure $next, $guard = null)
    {
        $payload = $request->attributes->get('jwt_payload');

        if (!$payload || !isset($payload->jti) || !isset($payload->sub) || !isset($payload->guard)) {
            // Should not happen if JwtAuthMiddleware ran before
            return $this->error('Incomplete JWT payload', 4001);
        }

        $cacheKey = "sso:{$payload->guard}:{$payload->sub}";
        $activeJti = Cache::get($cacheKey);

        // Check if current token's JTI is the active one
        // 检查当前令牌的 JTI 是否为有效 JTI
        if ($activeJti && $activeJti !== $payload->jti) {
            return $this->error('Account logged in elsewhere', 4003);
        }

        return $next($request);
    }
}
