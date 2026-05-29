<?php

namespace App\Services;

use App\Models\Menu;
use App\Models\Permission;
use Illuminate\Support\Facades\Cache;

/**
 * 菜单服务 | Menu Service
 * 
 * 处理菜单树的生成、过滤及本地化。
 * Handles menu tree generation, filtering, and localization.
 */
class MenuService
{
    /**
     * 获取指定守卫类型的用户菜单树，并按用户权限过滤
     * Get menu tree for a guard type, filtered by user's permissions
     * 
     * @param string $guardType 'admin' or 'front'
     * @param array|null $permissionIds IDs of permissions owned by user
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getUserMenus($guardType, $permissionIds = null)
    {
        $query = Permission::where('guard_type', $guardType)
            ->where('status', 1)
            ->where('parent_id', 0)
            ->with(['children' => function ($q) use ($permissionIds) {
                $q->where('status', 1)->orderBy('sort');
                if ($permissionIds !== null) {
                    $q->whereIn('id', $permissionIds);
                }
            }])
            ->orderBy('sort');
        
        if ($permissionIds !== null) {
            $query->whereIn('id', $permissionIds);
        }
        
        return $query->get();
    }
    
    /**
     * 获取完整的菜单树（用于后台管理，不过滤权限）
     * Get full menu tree for admin management (no permission filter)
     * 
     * @param string $guardType 'admin' or 'front'
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getFullMenuTree($guardType)
    {
        return Permission::where('guard_type', $guardType)
            ->where('parent_id', 0)
            ->with('children')
            ->orderBy('sort')
            ->get();
    }
    
    /**
     * 将 Eloquent 集合转换为树状数组结构
     * Build tree structure from flat list
     * 
     * @param \Illuminate\Support\Collection $menus
     * @param string $locale
     * @return array
     */
    public function buildTree($menus, $locale = 'zh-CN')
    {
        return $menus->map(function ($menu) use ($locale) {
            // 菜单来源是 permissions 表，页面渲染统一依赖 slug、url 和 translation_key。
            // title 只作为后端首屏兜底文本，真正切换语言时由前端 CrmLang 根据 translation_key 重绘。
            $menuKey = 'menu.' . $menu->slug;
            $phpMenuKey = 'menus.' . $menu->slug;
            $translatedTitle = __($phpMenuKey);
            if ($translatedTitle === $phpMenuKey) {
                $translatedTitle = $menu->name ?: $menu->slug;
            }

            $breadcrumbKey = 'breadcrumb.' . $menu->slug;
            $breadcrumb = __($breadcrumbKey);
            if ($breadcrumb === $breadcrumbKey) {
                $breadcrumb = $translatedTitle;
            }

            $item = [
                'id' => $menu->id,
                'slug' => $menu->slug,
                'title' => $translatedTitle,
                'title_en' => $translatedTitle,
                'name' => $menu->name,
                'icon' => $menu->icon,
                'url' => $menu->route ?: 'javascript:;',
                'path' => $menu->route,
                'breadcrumb' => $breadcrumb,
                'translation_key' => $menuKey,
                'breadcrumb_key' => $breadcrumbKey,
                'api_route' => $menu->api_route,
                'type' => $menu->type,
                'sort' => $menu->sort,
                'status' => $menu->status,
                'parent_id' => $menu->parent_id,
            ];
            if ($menu->children && $menu->children->count() > 0) {
                $item['children'] = $this->buildTree($menu->children, $locale);
            }
            return $item;
        })->toArray();
    }
}
