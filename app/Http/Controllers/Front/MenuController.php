<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/02
 * Time: 09:12
 */

namespace App\Http\Controllers\Front;

use App\Services\MenuService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

/**
 * 前台菜单控制器
 *
 * 文件功能：
 * - 负责 `GET /api/front/navigation/menus` 与 `GET /api/front/menus` 的菜单树返回。
 * - 根据当前登录用户的角色读取 role_permissions，再交给 MenuService 按 permissions.id 过滤菜单。
 * - 菜单标题由 MenuService 通过语言包生成，保证 Layui 与 Blade 页面能展示当前语言的可读菜单文案。
 */
class MenuController extends FrontBaseController
{
    /**
     * 菜单服务实例。
     *
     * 参数含义：
     * - $menuService：统一处理菜单查询、权限过滤和树形结构转换的服务。
     *
     * @var MenuService
     */
    protected $menuService;

    /**
     * 构造前台菜单控制器。
     *
     * @param MenuService $menuService 菜单服务，用于生成当前用户可见的前台菜单树。
     */
    public function __construct(MenuService $menuService)
    {
        $this->menuService = $menuService;
    }

    /**
     * 获取当前前台用户的授权菜单树。
     *
     * 业务逻辑说明：
     * - agent_role 和 customer_role 都从 roles、role_permissions、permissions 表读取菜单配置。
     * - 超级权限角色可通过 `*` 权限查看全部前台菜单；普通角色只返回已授权的 permissions.id。
     * - 返回值：统一 JSON 响应，data 为当前用户可见的菜单树，供 Layui 侧栏和 Blade 页面渲染。
     *
     * 参数含义：
     * - $request：当前 HTTP 请求对象，用于读取 user 守卫下的登录用户。
     * - $permissionIds：当前前台角色拥有的 permissions.id 列表；null 表示不按角色过滤。
     *
     * @param Request $request 当前 HTTP 请求对象。
     * @return JsonResponse 当前用户授权菜单树响应。
     */
    public function userMenus(Request $request): JsonResponse
    {
        $user = $request->user('user');
        $permissionIds = null;
        
        // 超级权限角色（hasPermission('*')）不按 permissions 过滤，直接返回全部前台菜单；普通角色只返回已授权的 permissions.id。
        if ($user && $user->role && !$user->role->hasPermission('*')) {
            $permissionIds = $user->role->permissionsRelation()->pluck('permissions.id')->toArray();
        }

        $menus = $this->menuService->getUserMenus('front', $permissionIds);
        $tree = $this->menuService->buildTree($menus, app()->getLocale());

        return $this->success($tree);
    }
}
