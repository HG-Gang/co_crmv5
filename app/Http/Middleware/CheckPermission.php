<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/02
 * Time: 08:51
 */

namespace App\Http\Middleware;

use Closure;
use App\Traits\ApiResponse;
use App\Constants\ResponseCode;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\Permission;

/**
 * 后台接口权限检查中间件。
 *
 * 文件功能：
 * - JWT 中间件负责确认“是谁”，SSO 中间件负责确认“当前 token 是否仍有效”。
 * - 本中间件负责确认“当前登录主体能不能访问当前接口”，真正安全边界必须落到这里。
 * - 前端隐藏按钮不是安全边界，所有敏感接口仍必须通过 permissions.api_route 与 role_permissions 校验。
 * - permissions.guard_type 用于隔离后台 admin 权限与前台 front 权限，避免两端角色互相授权。
 *
 * 安全边界：
 * - 未登录、路由名缺失、无角色绑定、权限记录不存在或角色不拥有该权限时，一律失败关闭并返回统一错误。
 * - 白名单与超级管理员只跳过权限表校验，不跳过 JWT 登录认证与 SSO 单点登录校验。
 * - 权限判定只以 permissions 表与角色关联记录为准，前端隐藏按钮不作为安全依据。
 */
class CheckPermission
{
    use ApiResponse;

    /**
     * 处理接口权限校验。
     *
     * 参数含义：
     * - $request：当前 HTTP 请求对象，用于读取当前 Laravel 命名路由和当前登录主体。
     * - $next：下一个中间件或控制器闭包，权限通过后才会继续执行。
     * - $guardType：权限守卫类型，admin=后台管理员，front=前台用户。
     * - $routeName：当前 Laravel 命名路由，例如 admin_api_userList，会用于匹配 permissions.api_route。
     *
     * 鉴权顺序：
     * - 未登录时直接返回 response.auth_failed 多语言消息。
     * - 白名单接口只要求登录和 SSO 有效，例如菜单、个人资料、退出登录和刷新 token。
     * - 超级管理员只跳过权限表校验，不跳过 JWT 登录认证和 SSO 单点登录校验。
     * - 普通角色必须先存在 roles 绑定，再通过 permissions.guard_type + permissions.api_route 找到启用权限。
     * - 最终通过 role_permissions 判断当前角色是否拥有该 permissions.id。
     *
     * @param Request $request 当前 HTTP 请求对象。
     * @param Closure $next 下一个中间件或控制器闭包。
     * @param string $guardType 权限守卫类型。
     * @return mixed 允许访问时返回后续响应，无权限时返回统一 JSON 错误。
     */
    public function handle(Request $request, Closure $next, $guardType = 'admin', $routeNameOverride = null)
    {
        $user = Auth::guard($guardType)->user();

        // 未登录直接失败关闭，避免匿名请求继续探测权限表结构。
        if (!$user) {
            return $this->error(__('response.auth_failed'), ResponseCode::AUTH_FAILED);
        }

        // 权限路由名只取中间件显式注入或 Laravel 命名路由，不以不可信的请求输入为准。
        $routeName = $routeNameOverride
            ?: $request->attributes->get('permission_route_name')
            ?: optional($request->route())->getName();
        if (!$routeName) {
            return $this->error(__('response.permission_denied'), ResponseCode::PERMISSION_DENIED);
        }

        // 白名单接口只要求登录和 SSO 有效，不要求独立授权记录（如菜单、个人资料、退出、刷新 token）。
        if ($this->isPermissionWhiteRoute($routeName)) {
            return $next($request);
        }

        // 超级管理员只跳过权限表校验；登录认证与 SSO 校验仍在更外层完成，此处不重复放行匿名请求。
        if ($this->isSuperAdmin($user)) {
            return $next($request);
        }

        // 无角色绑定视为无任何权限，失败关闭。
        if (!$user->role) {
            return $this->error(__('response.permission_denied'), ResponseCode::PERMISSION_DENIED);
        }

        // 按 guard_type + api_route + 启用状态定位唯一权限记录，guard_type 隔离后台与前台授权。
        $permission = Permission::where('guard_type', $guardType)
            ->where('api_route', $routeName)
            ->where('status', 1)
            ->first();

        if (!$permission) {
            return $this->error(__('response.permission_denied'), ResponseCode::PERMISSION_DENIED);
        }

        // 最终以角色关联的权限 id 判断是否真正拥有该接口权限。
        $hasPermission = $user->role->permissionsRelation()
            ->where('permissions.id', $permission->id)
            ->where('permissions.status', 1)
            ->exists();

        if (!$hasPermission) {
            return $this->error(__('response.permission_denied'), ResponseCode::PERMISSION_DENIED);
        }

        return $next($request);
    }

    /**
     * 判断当前路由是否为登录后基础白名单。
     *
     * 逻辑说明：
     * - 白名单接口只要求登录和 SSO 有效，不要求角色拥有独立 permissions.api_route。
     * - 菜单接口必须在白名单内，否则首次进入后台时普通管理员无法加载自己的授权菜单。
     * - 个人资料、改密、头像、退出登录和刷新 token 属于当前账号基础能力，不作为业务菜单按钮授权。
     *
     * @param string $routeName Laravel 路由名称，例如 admin_api_menus。
     * @return bool true=只要求登录即可访问，false=必须继续检查 permissions 表授权。
     */
    private function isPermissionWhiteRoute($routeName)
    {
        return in_array($routeName, [
            'admin_api_logout',
            'admin_api_refreshToken',
            'admin_api_menus',
            'admin_api_profileInfo',
            'admin_api_updateProfile',
            'admin_api_changePassword',
            'admin_api_uploadAvatar',
            'front_api_auth_logout',
            'front_api_auth_token_refresh',
            'front_api_menus',
            'front_api_profile',
            'front_api_profile_update',
            'front_api_profile_password',
            'front_api_profile_avatar',
        ], true);
    }

    /**
     * 判断登录主体是否为超级管理员。
     *
     * 参数含义：
     * - $user：当前登录主体，后台通常为 App\Models\Admin，前台扩展时可为前台用户模型。
     *
     * 逻辑说明：
     * - admins.id=1 或角色名 super_admin 视为超级管理员。
     * - 超级管理员只跳过权限表校验，仍必须先通过登录认证和 SSO 校验。
     *
     * @param mixed $user 当前登录主体。
     * @return bool true=超级管理员，false=普通角色。
     */
    private function isSuperAdmin($user)
    {
        return (int) $user->id === 1 || ($user->role && $user->role->name === 'super_admin');
    }
}
