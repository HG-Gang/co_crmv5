<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/18
 * Time: 18:47
 */

namespace App\Http\Middleware;

use App\Constants\ResponseCode;
use App\Http\Controllers\Admin\LegacyAdminController;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/**
 * 旧版管理后台身份认证中间件。
 *
 * 文件功能：
 * - 支持旧版 Session Guard 和 JWT Bearer Token 两种认证方式。
 * - 通过检查 admin guard 的 Session 状态，或验证 Bearer Token 后执行 JWT 认证和 SSO 认证。
 * - 认证通过后，进一步检查请求路由对应的权限。
 *
 * 适用场景：
 * - 旧版管理后台兼容路由组，处理从传统 Session 认证迁移到 JWT 认证的过渡阶段。
 *
 * 入参例子：
 * - Session 认证：通过 Admin Guard 检查登录状态。
 * - JWT 认证：从请求头 Authorization: Bearer <token> 提取令牌进行验证。
 *
 * 返回值：
 * - 通过时继续请求链，并在请求属性中注入 legacy_admin_auth_mode 和 permission_route_name。
 * - 不通过时，AJAX/JSON 请求返回 401 或 403 JSON 响应；普通请求重定向到管理后台登录页面。
 *
 * 安全边界：
 * - Session 与 JWT 两条通道都未认证时失败关闭：AJAX/JSON 返回 401，页面请求重定向后台登录页。
 * - JWT 通道必须先经 JwtAuthMiddleware 验签并经 SingleSignOn 校验，本中间件不自行验签。
 * - 权限路由名缺失或未命中权限表时返回 403 失败关闭，不放行到业务控制器；token 与载荷不写入日志。
 */
class LegacyAdminAuthenticate
{
    /**
     * 处理旧版后台请求认证：优先 Session 通道，其次 JWT+SSO 通道，两条通道均未通过时按请求类型失败关闭。
     *
     * @param Request $request 当前请求对象。
     * @param Closure $next 认证与授权通过后的后续处理闭包。
     * @return mixed 认证通过返回后续响应；未认证时返回 401/403 JSON 或重定向后台登录页。
     */
    public function handle(Request $request, Closure $next)
    {
        // 优先 Session 通道：admin guard 已登录时标记 session 模式，直接进入权限校验。
        if (Auth::guard('admin')->check()) {
            $request->attributes->set('legacy_admin_auth_mode', 'session');

            return $this->authorize($request, $next);
        }

        // 无 Session 时回退 JWT 通道：先由 JwtAuthMiddleware 验签，再经 SingleSignOn 确认当前 token 仍有效。
        if ($request->bearerToken()) {
            return app(JwtAuthMiddleware::class)->handle(
                $request,
                function (Request $authenticatedRequest) use ($next) {
                    return app(SingleSignOn::class)->handle(
                        $authenticatedRequest,
                        function (Request $ssoRequest) use ($next) {
                            $ssoRequest->attributes->set('legacy_admin_auth_mode', 'jwt');

                            return $this->authorize($ssoRequest, $next);
                        },
                        'admin'
                    );
                },
                'admin'
            );
        }

        // 两条通道均未通过时失败关闭：AJAX/JSON 请求返回 401 统一错误，浏览器页面重定向后台登录页。
        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'code' => ResponseCode::AUTH_FAILED,
                'message' => __('response.auth_failed'),
                'data' => (object) [],
            ], 401);
        }

        return redirect()->route('admin_page_login');
    }

    /**
     * 解析旧版路由对应的权限路由名并委托 CheckPermission 完成授权。
     *
     * @param Request $request 当前请求对象。
     * @param Closure $next 授权通过后的后续处理闭包。
     * @return mixed 授权通过返回后续响应；无法确定权限路由名时返回 403 失败关闭。
     */
    private function authorize(Request $request, Closure $next)
    {
        $route = $request->route();
        // 旧路由的权限名优先取路由 defaults 中的 legacy_permission_route，无法解析时按失败关闭处理。
        $permissionRoute = $route
            ? ($route->defaults['legacy_permission_route'] ?? null)
            : null;
        $legacyUri = $route ? $route->uri() : trim($request->path(), '/');
        // 个别旧路由 URI 与权限表记录不一致，需从控制器侧按请求参数解析对应权限路由名。
        if (in_array($legacyUri, [
            'index/admin/auth/voucherReviewSave',
            'index/admin/cancel/update_cancel',
            'index/admin/amount/batchWithdrawApply',
            'index/admin/amount/order_status',
        ], true)) {
            $permissionRoute = LegacyAdminController::permissionRouteForLegacyRequest($request);
        }

        // 权限路由名缺失或为空时不能放行，返回 403，避免未授权接口被旧路径绕过。
        if (!is_string($permissionRoute) || $permissionRoute === '') {
            return response()->json([
                'code' => ResponseCode::PERMISSION_DENIED,
                'message' => __('response.permission_denied'),
                'data' => (object) [],
            ], 403);
        }

        $request->attributes->set('permission_route_name', $permissionRoute);

        // 权限路由名注入请求属性后，交由 CheckPermission 按该名称完成最终授权。
        $authorized = false;
        $response = app(CheckPermission::class)->handle(
            $request,
            static function (Request $authorizedRequest) use (&$authorized, $next) {
                $authorized = true;

                return $next($authorizedRequest);
            },
            'admin',
            $permissionRoute
        );

        if (!$authorized
            && ($legacyUri === 'index/admin/amount/order_status'
                || strpos($legacyUri, 'index/admin/withdraw/') === 0
                || strpos($legacyUri, 'index/admin/fengXian/') === 0)
            && $response instanceof SymfonyResponse) {
            $response->setStatusCode(403);
        }

        return $response;
    }
}
