<?php

namespace App\Http\Middleware;

use Closure;
use App\Services\JwtService;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Exception;

/**
 * JWT Authentication Middleware
 * JWT 鉴权中间件
 */
class JwtAuthMiddleware
{
    use ApiResponse;

    /**
     * @var JwtService
     */
    protected $jwtService;

    public function __construct(JwtService $jwtService)
    {
        $this->jwtService = $jwtService;
    }

    /**
     * Handle an incoming request.
     * 处理进入请求
     *
     * @param Request $request 请求对象
     * @param Closure $next 下一个处理程序
     * @param string $guard Guard name (user or admin)
     * @return mixed
     */
    public function handle(Request $request, Closure $next, $guard = 'user')
    {
        $header = $request->header('Authorization');
        if (!$header || !preg_match('/Bearer\s+(.*)$/i', $header, $matches)) {
            return $this->error('Authorization token not found', 4001);
        }

        $token = $matches[1];

        try {
            $payload = $this->jwtService->parseToken($token);

            // Decode payload guard if not explicitly provided
            // 如果未明确提供 guard，则从载荷中读取
            $decodedGuard = $payload->guard ?? 'user';
            
            // Use provided guard or fallback to decoded guard
            // 使用提供的 guard 或回退到载荷中的 guard
            $guard = $guard ?: $decodedGuard;
            
            // Set the authenticated user
            // 设置已认证的用户
            Auth::shouldUse($guard);
            $user = Auth::guard($guard)->getProvider()->retrieveById($payload->sub);

            if (!$user) {
                return $this->error('User not found', 4001);
            }

            Auth::guard($guard)->setUser($user);

            // Attach to request for potential use in next middleware/controller
            $request->attributes->set('jwt_payload', $payload);
            $request->attributes->set('jwt_guard', $guard);
            $request->attributes->set('jwt_token', $token);

            return $next($request);
        } catch (Exception $e) {
            return $this->error($e->getMessage(), 4001);
        }
    }
}
