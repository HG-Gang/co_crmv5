<?php

/**
 * Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/09/03
 * Time: 14:30
 */

namespace App\Http\Controllers\Admin;

use App\Constants\ResponseCode;
use App\Models\Permission;
use App\Services\MenuService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;

/**
 * 后台菜单管理控制器
 *
 * 文件功能：
 * - 提供后台菜单树读取、当前管理员菜单与按钮权限返回，以及菜单权限字典的新增、更新、删除。
 *
 * 功能逻辑说明：
 * - 后台页面菜单和按钮都来自 `permissions` 表与 `role_permissions` 授权关系，Blade/JS 不维护第二套权限来源。
 * - 前端按钮控制只是体验优化，后端接口仍由 check.permission:admin 再次校验，避免只靠隐藏按钮保护敏感操作。
 *
 * 安全边界：
 * - 超级管理员（admin.id=1 或角色名 super_admin）直接放行全部 admin 权限，不读取 role_permissions。
 * - 普通角色只返回其角色已授权且 status=1 的菜单和按钮 slug，避免页面泄漏未授权入口。
 */
class MenuController extends AdminBaseController
{
    /**
     * 菜单服务实例。
     *
     * @var MenuService
     */
    protected $menuService;

    /**
     * 构造后台菜单控制器。
     *
     * @param MenuService $menuService 菜单服务，用于生成后台管理端需要的菜单树。
     */
    public function __construct(MenuService $menuService)
    {
        $this->menuService = $menuService;
    }

    /**
     * 获取用于后台菜单管理页面的完整菜单树。
     *
     * 参数含义：
     * - $request：当前 HTTP 请求对象，用于读取 guard_type 参数。
     * - $guardType：菜单所属守卫类型，admin 表示后台菜单，front 表示前台菜单。
     *
     * 返回值：
     * - 统一 JSON 响应，data 为不按角色过滤的完整菜单树，供菜单管理页面维护权限字典。
     *
     * @param Request $request 当前 HTTP 请求对象。
     * @return JsonResponse 完整菜单树响应。
     */
    public function menuTree(Request $request): JsonResponse
    {
        $guardType = $request->input('guard_type', 'admin');
        $menus = $this->menuService->getFullMenuTree($guardType);
        $tree = $this->menuService->buildTree($menus, app()->getLocale());
        
        return $this->success($tree);
    }

    /**
     * 获取当前管理员授权菜单与按钮权限。
     *
     * 逻辑说明：
     * - menus 用于 Blade/Layui/JS 渲染后台导航菜单。
     * - permissions 返回当前管理员拥有的 permissions.slug 数组，用于前端控制按钮显示。
     * - 前端按钮控制只是体验优化，后端接口仍由 check.permission:admin 再次校验。
     *
     * 参数含义：
     * - $request：当前请求对象，用于读取 admin 守卫下的登录管理员。
     * - $permissionIds：当前管理员角色拥有的 permissions.id 列表，用于过滤菜单树。
     * - $permissionSlugs：当前管理员角色拥有的 permissions.slug 列表，用于 Blade/JS 控制按钮显示。
     *
     * @param Request $request 当前请求对象。
     * @return JsonResponse 统一 JSON 响应，data.menus 为菜单树，data.permissions 为权限 slug 数组。
     */
    public function adminMenus(Request $request): JsonResponse
    {
        $admin = $request->user('admin');
        $permissionIds = null;
        $permissionSlugs = [];

        // 普通角色：只取角色已授权且启用的 admin 权限，菜单树和按钮 slug 都以此为准。
        if ($admin->role && !$this->isSuperAdmin($admin)) {
            $rolePermissions = $admin->role->permissionsRelation()
                ->where('permissions.guard_type', 'admin')
                ->where('permissions.status', 1)
                ->get(['permissions.id', 'permissions.slug']);

            $permissionIds = $rolePermissions->pluck('id')->toArray();
            $permissionSlugs = $rolePermissions->pluck('slug')->toArray();
        }

        $menus = $this->menuService->getUserMenus('admin', $permissionIds);
        $tree = $this->menuService->buildTree($menus, app()->getLocale());

        // 超管不受 role_permissions 约束，直接放行全部启用的 admin 权限 slug。
        if ($this->isSuperAdmin($admin)) {
            $permissionSlugs = Permission::where('guard_type', 'admin')
                ->where('status', 1)
                ->pluck('slug')
                ->toArray();
        }

        return $this->success([
            'menus' => $tree,
            'permissions' => $permissionSlugs,
            'admin_name' => $admin->username ?: $admin->name,
        ]);
    }

    /**
     * 判断当前管理员是否为超级管理员。
     *
     * 参数含义：
     * - $admin：当前登录管理员模型，通常为 App\Models\Admin。
     *
     * @param mixed $admin 当前登录管理员模型。
     * @return bool true=超级管理员，可查看全部菜单和按钮；false=普通角色，按 role_permissions 过滤。
     */
    private function isSuperAdmin($admin)
    {
        return (int) $admin->id === 1 || ($admin->role && $admin->role->name === 'super_admin');
    }

    /**
     * 新增菜单权限字典记录。
     *
     * 参数含义：
     * - $request：当前 HTTP 请求对象，包含 title、slug、icon、url/path、api_route、parent_id、guard_type、type、sort、status。
     * - $guardType：菜单所属守卫类型，admin=后台菜单，front=前台菜单。
     * - $slug：最终写入 permissions.slug 的稳定权限标识，同时作为前端多语言 key 的基础。
     *
     * @param Request $request 当前 HTTP 请求对象。
     * @return JsonResponse 新增后的菜单权限记录。
     */
    public function createMenu(Request $request): JsonResponse
    {
        $data = $request->validate([
            'title' => 'required|string|max:100',
            'slug' => 'nullable|string|max:100',
            'icon' => 'nullable|string|max:100',
            'url' => 'nullable|string|max:255',
            'path' => 'nullable|string|max:255',
            'api_route' => 'nullable|string|max:255',
            'parent_id' => 'nullable|integer',
            'guard_type' => 'nullable|in:admin,front',
            'type' => 'nullable|integer',
            'sort' => 'nullable|integer',
            'status' => 'nullable|boolean',
        ]);

        // 当前前后台菜单树实际读取 permissions 表，因此菜单管理直接维护 type=1 的权限记录。
        // url/path 都兼容旧页面字段，保存时统一落到 route 字段，避免菜单展示和管理分离。
        $guardType = isset($data['guard_type']) ? $data['guard_type'] : 'admin';
        $slug = isset($data['slug']) && $data['slug'] ? $data['slug'] : $this->makeMenuSlug($data['title'], $guardType);
        $menu = Permission::create([
            'parent_id' => isset($data['parent_id']) ? (int) $data['parent_id'] : 0,
            'name' => $data['title'],
            'slug' => $slug,
            'api_route' => isset($data['api_route']) ? $data['api_route'] : '',
            'route' => isset($data['path']) && $data['path'] ? $data['path'] : (isset($data['url']) ? $data['url'] : ''),
            'icon' => isset($data['icon']) ? $data['icon'] : '',
            'type' => isset($data['type']) ? (int) $data['type'] : 1,
            'guard_type' => $guardType,
            'sort' => isset($data['sort']) ? (int) $data['sort'] : 0,
            'status' => array_key_exists('status', $data) ? (int) $data['status'] : 1,
        ]);
        return $this->success($menu, __('admin.menu_created'));
    }

    /**
     * 更新菜单权限字典记录。
     *
     * 参数含义：
     * - $request：当前 HTTP 请求对象，必须携带 id，并可选提交 title、slug、icon、url/path、api_route、parent_id、guard_type、type、sort、status。
     * - $id：待更新的 permissions.id。
     * - $update：映射到 permissions 表真实字段的更新数组，只包含页面实际提交的字段。
     *
     * @param Request $request 当前 HTTP 请求对象。
     * @return JsonResponse 更新后的菜单权限记录。
     */
    public function updateMenu(Request $request): JsonResponse
    {
        if ($menuIdError = $this->validateMenuId($request->input('id'))) {
            return $menuIdError;
        }

        $id = (int) $request->input('id');
        $menu = Permission::findOrFail($id);
        
        $data = $request->validate([
            'title' => 'nullable|string|max:100',
            'slug' => 'nullable|string|max:100',
            'icon' => 'nullable|string|max:100',
            'url' => 'nullable|string|max:255',
            'path' => 'nullable|string|max:255',
            'api_route' => 'nullable|string|max:255',
            'parent_id' => 'nullable|integer',
            'guard_type' => 'nullable|in:admin,front',
            'type' => 'nullable|integer',
            'sort' => 'nullable|integer',
            'status' => 'nullable|boolean',
        ]);

        // 只更新页面提交的字段，并把管理页字段映射回 permissions 表真实列名。
        $update = [];
        if (isset($data['title'])) $update['name'] = $data['title'];
        if (isset($data['slug']) && $data['slug']) $update['slug'] = $this->makeMenuSlug($data['slug'], isset($data['guard_type']) ? $data['guard_type'] : $menu->guard_type, $menu->id);
        if (array_key_exists('icon', $data)) $update['icon'] = $data['icon'];
        if (array_key_exists('api_route', $data)) $update['api_route'] = $data['api_route'];
        if (array_key_exists('path', $data) || array_key_exists('url', $data)) {
            $update['route'] = isset($data['path']) && $data['path'] ? $data['path'] : (isset($data['url']) ? $data['url'] : '');
        }
        if (array_key_exists('parent_id', $data)) $update['parent_id'] = (int) $data['parent_id'];
        if (array_key_exists('guard_type', $data)) $update['guard_type'] = $data['guard_type'];
        if (array_key_exists('type', $data)) $update['type'] = (int) $data['type'];
        if (array_key_exists('sort', $data)) $update['sort'] = (int) $data['sort'];
        if (array_key_exists('status', $data)) $update['status'] = (int) $data['status'];

        $menu->update($update);
        return $this->success($menu, __('admin.menu_updated'));
    }

    /**
     * 删除菜单权限字典记录。
     *
     * 参数含义：
     * - $request：当前 HTTP 请求对象，必须携带 id。
     * - $id：待删除的 permissions.id。
     * - $menu：待删除的权限记录；如果仍有子菜单，则拒绝删除，避免产生孤儿菜单。
     *
     * @param Request $request 当前 HTTP 请求对象。
     * @return JsonResponse 删除结果响应。
     */
    public function deleteMenu(Request $request): JsonResponse
    {
        if ($menuIdError = $this->validateMenuId($request->input('id'))) {
            return $menuIdError;
        }

        $id = (int) $request->input('id');
        $menu = Permission::findOrFail($id);
        
        if (Permission::where('parent_id', $id)->count() > 0) {
            return $this->error(__('admin.menu_has_children'));
        }
        
        $menu->delete();
        return $this->success(null, __('admin.menu_deleted'));
    }

    /**
     * 菜单 slug 是前端多语言 key 的基础；新增菜单没有传 slug 时，根据标题生成并保证不重复。
     *
     * 参数含义：
     * - $title：菜单标题或页面提交的 slug 原始值，用于生成稳定标识。
     * - $guardType：菜单所属守卫类型，用作 slug 前缀，避免前后台菜单标识冲突。
     * - $ignoreId：更新菜单时需要排除的当前 permissions.id，避免把自身 slug 判断为重复。
     *
     * @param string $title 菜单标题或原始 slug。
     * @param string $guardType 守卫类型：admin 或 front。
     * @param int|null $ignoreId 更新时排除的当前记录 ID。
     * @return string 唯一菜单 slug。
     */
    private function makeMenuSlug($title, $guardType, $ignoreId = null)
    {
        $base = strtolower(preg_replace('/[^A-Za-z0-9_]+/', '_', $title));
        $base = trim($base, '_');
        if ($base === '') {
            $base = $guardType . '_menu';
        }
        if (strpos($base, $guardType . '_') !== 0) {
            $base = $guardType . '_' . $base;
        }

        $slug = $base;
        $index = 1;
        while ($this->slugExists($slug, $ignoreId)) {
            $slug = $base . '_' . $index;
            $index++;
        }

        return $slug;
    }

    /**
     * 更新菜单时会排除当前记录，确保 slug 唯一但允许自身保持不变。
     *
     * 参数含义：
     * - $slug：需要检测的 permissions.slug。
     * - $ignoreId：更新菜单时排除的当前 permissions.id。
     *
     * @param string $slug 待检测 slug。
     * @param int|null $ignoreId 需要排除的记录 ID。
     * @return bool true=已存在，false=不存在。
     */
    private function slugExists($slug, $ignoreId = null)
    {
        $query = Permission::where('slug', $slug);
        if ($ignoreId) {
            $query->where('id', '!=', $ignoreId);
        }

        return $query->exists();
    }

    /**
     * 校验菜单主键，必须为整数。
     *
     * @param mixed $id permissions.id。
     * @return \Illuminate\Http\JsonResponse|null 非法时返回参数错误响应，合法时返回 null。
     */
    private function validateMenuId($id)
    {
        $validator = Validator::make(['id' => $id], [
            'id' => 'required|integer',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), ResponseCode::VALIDATION_FAILED);
        }

        return null;
    }
}
