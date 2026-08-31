<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/02
 * Time: 09:15
 */

namespace App\Services;

use App\Models\Permission;

/**
 * 菜单服务。
 *
 * 处理菜单树的生成、过滤及本地化。
 *
 * 文件功能：
 * - 统一从 permissions 表读取前台和后台菜单，不再维护第二套静态菜单来源。
 * - 根据角色拥有的 `permissions.id` 过滤可见菜单，保证 Layui、Blade 和接口层看到同一套权限配置。
 * - 构造接口需要的树状数组，并通过 `menus.php`、`breadcrumb.php` 语言包返回可读多语言标题。
 *
 * 输入输出：
 * - 输入：守卫类型（admin/front）与角色权限 ID 列表；输出：过滤后的菜单树（含多语言标题与路由）。
 * - 本服务只读 permissions 表，不负责权限判定本身；null 权限列表表示超级管理员全量返回。
 */
class MenuService
{
    /**
     * 获取指定守卫类型的用户菜单树，并按角色权限过滤。
     *
     * 参数含义：
     * - $guardType 表示守卫类型，admin 表示后台管理员菜单，front 表示前台用户菜单。
     * - $permissionIds 表示当前角色拥有的 permissions.id 列表；null 表示超级管理员或无需过滤，返回该守卫下全部启用菜单。
     *
     * 返回值：
     * - Eloquent 菜单集合，仅包含顶级菜单；子菜单通过 children 关系预加载，并按 sort 排序。
     *
     * @param string $guardType 守卫类型：admin=后台管理员，front=前台用户。
     * @param array<int, int>|null $permissionIds 当前角色拥有的权限 ID 列表。
     * @return \Illuminate\Database\Eloquent\Collection<int, \App\Models\Permission>
     */
    public function getUserMenus($guardType, $permissionIds = null)
    {
        $query = Permission::where('guard_type', $guardType)
            ->where('status', 1)
            ->where('parent_id', 0)
            ->with(['children' => function ($q) use ($permissionIds) {
                $q->where('status', 1)->orderBy('sort');
                if ($permissionIds !== null) {
                    // $permissionIds 表示当前角色直接拥有的权限 ID。子菜单必须继续按授权过滤，避免未授权页面被展示。
                    $q->whereIn('id', $permissionIds);
                }
            }])
            ->orderBy('sort');
        
        if ($permissionIds !== null) {
            // 父级菜单通常只是分组容器。当前台代理商角色只授权 front_agent_sub 等子页面时，
            // 必须保留 front_agent 父级，否则 Layui 侧栏没有容器承载子菜单，看起来就像菜单丢失。
            $query->where(function ($q) use ($permissionIds) {
                $q->whereIn('id', $permissionIds)
                    ->orWhereHas('children', function ($childQuery) use ($permissionIds) {
                        $childQuery->where('status', 1)
                            ->whereIn('id', $permissionIds);
                    });
            });
        }
        
        return $query->get();
    }
    
    /**
     * 获取完整菜单树，供后台菜单管理页面维护权限字典。
     *
     * 参数含义：
     * - $guardType 表示守卫类型，admin 表示后台菜单权限，front 表示前台菜单权限。
     *
     * 返回值：
     * - 不按角色过滤的顶级菜单集合，用于菜单管理界面展示完整配置。
     *
     * @param string $guardType 守卫类型：admin=后台，front=前台。
     * @return \Illuminate\Database\Eloquent\Collection<int, \App\Models\Permission>
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
     * 将 Eloquent 菜单集合转换为前端可直接渲染的树状数组。
     *
     * 参数含义：
     * - $menus 表示菜单 Eloquent 集合，通常来自 getUserMenus 或 getFullMenuTree。
     * - $locale 表示当前语言标识，例如 zh-CN、en；保留该参数用于后续扩展显式语言切换。
     *
     * 返回值：
     * - 菜单树数组；每个节点包含 slug、title、url、translation_key、breadcrumb_key 和 children 等字段。
     *
     * @param \Illuminate\Support\Collection<int, \App\Models\Permission> $menus 菜单集合。
     * @param string $locale 当前语言标识。
     * @return array<int, array<string, mixed>>
     */
    public function buildTree($menus, $locale = 'zh-CN')
    {
        return $menus->map(function ($menu) use ($locale) {
            // 菜单来源是 permissions 表，页面渲染统一依赖 slug、url 和 translation_key。
            // title 只作为后端首屏兜底文本，真正切换语言时由前端 CrmLang 根据 translation_key 重绘。
            $menuKey = 'menu.' . $menu->slug;
            $phpMenuKey = 'menus.' . $menu->slug;
            $translatedTitle = __($phpMenuKey);
            // 语言包缺失时回退到 permissions 表配置的名称或 slug，保证菜单不会出现空白标题。
            if ($translatedTitle === $phpMenuKey) {
                $translatedTitle = $menu->name ?: $menu->slug;
            }

            $breadcrumbKey = 'breadcrumb.' . $menu->slug;
            $breadcrumb = __($breadcrumbKey);
            // 面包屑语言包缺失时回退到菜单标题。
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
                // 无路由的菜单项输出占位链接，避免前端渲染空 href 导致点击跳转异常。
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
