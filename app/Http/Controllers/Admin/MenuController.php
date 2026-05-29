<?php

namespace App\Http\Controllers\Admin;

use App\Models\Permission;
use App\Services\MenuService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

/**
 * Admin Menu Management Controller
 * 后台菜单管理控制器
 */
class MenuController extends AdminBaseController
{
    protected $menuService;

    public function __construct(MenuService $menuService)
    {
        $this->menuService = $menuService;
    }

    /**
     * Get full menu tree for admin management
     * 获取用于管理的完整菜单树
     */
    public function menuTree(Request $request): JsonResponse
    {
        $guardType = $request->input('guard_type', 'admin');
        $menus = $this->menuService->getFullMenuTree($guardType);
        $tree = $this->menuService->buildTree($menus, app()->getLocale());
        
        return $this->success($tree);
    }

    /**
     * Get authorized menus for current admin
     * 获取当前管理员的授权菜单
     */
    public function adminMenus(Request $request): JsonResponse
    {
        $admin = $request->user('admin');
        $permissionIds = null;
        
        if ($admin->role && !$admin->role->hasPermission('*')) {
            $permissionIds = $admin->role->permissionsRelation()->pluck('permissions.id')->toArray();
        }

        $menus = $this->menuService->getUserMenus('admin', $permissionIds);
        $tree = $this->menuService->buildTree($menus, app()->getLocale());

        return $this->success([
            'menus' => $tree,
            'admin_name' => $admin->username ?: $admin->name,
        ]);
    }

    /**
     * Create new menu
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
        return $this->success($menu, 'Menu created successfully');
    }

    /**
     * Update menu
     */
    public function updateMenu(Request $request): JsonResponse
    {
        $id = $request->input('id');
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
        return $this->success($menu, 'Menu updated successfully');
    }

    /**
     * Delete menu
     */
    public function deleteMenu(Request $request): JsonResponse
    {
        $id = $request->input('id');
        $menu = Permission::findOrFail($id);
        
        if (Permission::where('parent_id', $id)->count() > 0) {
            return $this->error('Cannot delete menu with sub-menus');
        }
        
        $menu->delete();
        return $this->success(null, 'Menu deleted successfully');
    }

    /**
     * Build a unique menu slug.
     * 菜单 slug 是前端多语言 key 的基础；新增菜单没有传 slug 时，根据标题生成并保证不重复。
     *
     * @param string $title
     * @param string $guardType
     * @param int|null $ignoreId
     * @return string
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
     * Check whether a slug already exists.
     * 更新菜单时会排除当前记录，确保 slug 唯一但允许自身保持不变。
     *
     * @param string $slug
     * @param int|null $ignoreId
     * @return bool
     */
    private function slugExists($slug, $ignoreId = null)
    {
        $query = Permission::where('slug', $slug);
        if ($ignoreId) {
            $query->where('id', '!=', $ignoreId);
        }

        return $query->exists();
    }
}
