<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/02
 * Time: 13:09
 */

namespace App\Http\Middleware;

use Closure;
use App\Services\JwtService;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Constants\ResponseCode;
use Exception;

/**
 * JWT Bearer 鉴权中间件（前台 user 与后台 admin 共用）。
 *
 * 文件功能：
 * - 从 Authorization: Bearer {token} 提取 JWT 并解析验证（签名、有效期、黑名单）。
 * - 按解析出的 sub 还原登录主体，固定 Auth guard，并将结果挂载到请求属性供后续中间件复用。
 * - 显式传入的 guard 优先，未传入时兼容 token 载荷中的 guard（user/admin 双入口）。
 *
 * 安全边界：
 * - 任一步失败（缺头、格式不符、解析失败、主体不存在）都直接失败关闭，不进入业务控制器。
 * - token 只存放在请求属性中，不写入日志与接口响应。
 * - 认证失败统一返回多语言 auth_failed 文案，不向客户端泄漏过期、签名错误等具体原因。
 */
class JwtAuthMiddleware
{
    use ApiResponse;

    /**
     * JWT 服务：负责 token 的生成、解析与黑名单失效校验。中间件自身不持有任何密钥与解析逻辑，
     * 鉴权成败完全取决于它；该依赖不可用或被替换为宽松实现时，Bearer 鉴权会整体失效或放行伪造 token，
     * 因此构造函数强制注入，不存在默认值。
     *
     * @var JwtService
     */
    protected $jwtService;

    /**
     * 注入 JWT 服务。
     *
     * @param JwtService $jwtService 负责 JWT 生成、解析与失效的服务实例。
     */
    public function __construct(JwtService $jwtService)
    {
        $this->jwtService = $jwtService;
    }

    /**
     * 处理进入请求：解析并验证 JWT，建立已认证用户后继续请求链。
     *
     * @param Request $request 当前请求对象，携带 Authorization 请求头。
     * @param Closure $next 鉴权通过后的后续处理闭包。
     * @param string $guard 显式指定守卫，user=前台用户，admin=后台管理员；为空时回退到 token 载荷中的 guard。
     * @return mixed 认证成功返回后续响应；失败返回统一 JSON 错误（失败关闭）。
     */
    public function handle(Request $request, Closure $next, $guard = 'user')
    {
        // $header 表示 Authorization 请求头；仅接受 Bearer 格式，格式不符直接失败关闭，避免歧义输入进入解析。
        $header = $request->header('Authorization');
        if (!$header || !preg_match('/Bearer\s+(.*)$/i', $header, $matches)) {
            return $this->error(__('response.token_missing'), ResponseCode::TOKEN_MISSING);
        }

        // $token 表示 Bearer 后面的 JWT 字符串，只用于本次请求的解析与鉴权，不写入接口响应。
        $token = $matches[1];

        try {
            // $payload 表示 JWT 解析后的载荷（签名、有效期、黑名单已校验），任何失败统一进入 catch 兜底。
            $payload = $this->jwtService->parseToken($token);

            // $decodedGuard 表示令牌载荷中的守卫类型，用于兼容前台 user 与后台 admin 双入口。
            $decodedGuard = $payload->guard ?? 'user';
            
            // $guard 表示当前认证守卫：显式传入的 guard 优先，未传入时使用载荷中的守卫类型。
            $guard = $guard ?: $decodedGuard;
            
            // 固定当前守卫后按 sub 还原登录主体，保证 Auth::user() 落在正确的守卫上。
            Auth::shouldUse($guard);
            $user = Auth::guard($guard)->getProvider()->retrieveById($payload->sub);

            if (!$user) {
                // 载荷有效但主体已不存在（如账号已删除）时失败关闭，不进入业务。
                return $this->error(__('response.user_not_found'), ResponseCode::USER_NOT_FOUND);
            }

            Auth::guard($guard)->setUser($user);

            // 解析结果挂载到请求属性，供 SSO、权限等后续中间件复用，避免二次解析 token。
            $request->attributes->set('jwt_payload', $payload);
            $request->attributes->set('jwt_guard', $guard);
            $request->attributes->set('jwt_token', $token);

            return $next($request);
        } catch (Exception $e) {
            // 认证失败统一返回 auth_failed，不区分过期、签名错误或黑名单等具体原因，避免向客户端泄漏校验细节。
            return $this->error(__('response.auth_failed'), ResponseCode::AUTH_FAILED);
        }
    }
}
