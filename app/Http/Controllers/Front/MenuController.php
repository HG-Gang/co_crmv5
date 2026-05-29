<?php

namespace App\Http\Controllers\Front;

use App\Services\MenuService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

/**
 * Front Menu Controller
 * 前台菜单控制器
 */
class MenuController extends FrontBaseController
{
    protected $menuService;

    public function __construct(MenuService $menuService)
    {
        $this->menuService = $menuService;
    }

    /**
     * Get authorized menu tree for current user
     * 获取当前用户的授权菜单树
     */
    public function userMenus(Request $request): JsonResponse
    {
        $user = $request->user('user');
        $permissionIds = null;
        
        if ($user && $user->role && !$user->role->hasPermission('*')) {
            $permissionIds = $user->role->permissionsRelation()->pluck('permissions.id')->toArray();
        }

        $menus = $this->menuService->getUserMenus('front', $permissionIds);
        $tree = $this->menuService->buildTree($menus, app()->getLocale());

        return $this->success($tree);
    }
}
