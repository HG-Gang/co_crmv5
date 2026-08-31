<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/06/09
 * Time: 01:23
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * 修复后台 Blade 页面菜单权限 route 配置。
 *
 * 文件功能：
 * - 后台左侧菜单和页面入口必须由 permissions 表配置驱动，不能只依赖 Blade 路由存在。
 * - 本迁移补齐控制台、角色管理、权限管理、菜单管理四个核心后台页面菜单权限。
 * - 本迁移合并历史重复生成的 /admin/users 菜单权限，保留最早启用记录并迁移 role_permissions 授权关系。
 */
class FixAdminPageMenuPermissionRoutes extends Migration
{
    /**
     * 执行迁移：补齐后台核心页面权限并合并重复菜单 route。
     *
     * @return void
     */
    public function up()
    {
        foreach ($this->corePageMenus() as $menu) {
            $this->upsertAdminMenuPermission($menu);
        }

        $this->mergeDuplicateAdminRoute('/admin/users');
    }

    /**
     * 回滚迁移：禁用本迁移补齐的核心页面权限，不恢复历史重复用户菜单。
     *
     * 逻辑边界：
     * - 重复 /admin/users 权限合并属于数据修复，回滚时不重新制造重复菜单。
     * - 只禁用本迁移明确维护的 slug，避免误删后续在后台手工调整的权限字典。
     *
     * @return void
     */
    public function down()
    {
        DB::table('permissions')
            ->where('guard_type', 'admin')
            ->whereIn('slug', array_column($this->corePageMenus(), 'slug'))
            ->update([
                'status' => 0,
                'updated_at' => now()->format('Y-m-d H:i:s'),
            ]);
    }

    /**
     * 写入或更新单条后台菜单权限。
     *
     * 参数含义：
     * - $menu：后台菜单权限配置数组。
     * - slug：稳定权限标识，菜单接口、按钮显隐和角色授权都依赖它。
     * - route：后台 Blade 页面路径，用于左侧菜单跳转和页面权限覆盖审计。
     * - api_route：该页面主要读取接口的 Laravel 命名路由；纯菜单页可为空。
     *
     * @param array<string, mixed> $menu 单条后台菜单权限配置。
     * @return void
     */
    private function upsertAdminMenuPermission(array $menu)
    {
        $now = now()->format('Y-m-d H:i:s');

        DB::table('permissions')->updateOrInsert(
            ['slug' => $menu['slug'], 'guard_type' => 'admin'],
            [
                'name' => $menu['name'],
                'parent_id' => 0,
                'type' => 1,
                'icon' => $menu['icon'],
                'sort' => (int) $menu['sort'],
                'route' => $menu['route'],
                'api_route' => $menu['api_route'],
                'status' => 1,
                'created_at' => $now,
                'updated_at' => $now,
                'deleted_at' => null,
            ]
        );
    }

    /**
     * 合并指定后台页面 route 的重复权限记录。
     *
     * 处理逻辑：
     * - $route：待合并的后台页面路径，例如 /admin/users。
     * - $keptPermission：同 route 下最早启用的 permissions 记录，作为保留权限。
     * - $duplicateIds：除保留记录外的重复 permissions.id 列表。
     * - role_permissions：先把重复记录上的角色授权迁移到保留记录，再禁用重复权限。
     *
     * @param string $route 后台页面 route。
     * @return void
     */
    private function mergeDuplicateAdminRoute($route)
    {
        $permissions = DB::table('permissions')
            ->where('guard_type', 'admin')
            ->where('status', 1)
            ->where('route', $route)
            ->orderBy('id')
            ->get(['id', 'slug']);

        if ($permissions->count() <= 1) {
            return;
        }

        $keptPermission = $permissions->first();
        $duplicateIds = $permissions->slice(1)->pluck('id')->map(function ($id) {
            return (int) $id;
        })->values()->all();

        $duplicateGrants = DB::table('role_permissions')
            ->whereIn('permission_id', $duplicateIds)
            ->get(['role_id']);

        foreach ($duplicateGrants as $grant) {
            DB::table('role_permissions')->updateOrInsert(
                [
                    'role_id' => (int) $grant->role_id,
                    'permission_id' => (int) $keptPermission->id,
                ],
                [
                    'created_at' => time(),
                    'updated_at' => time(),
                    'deleted_at' => null,
                ]
            );
        }

        DB::table('role_permissions')->whereIn('permission_id', $duplicateIds)->delete();

        DB::table('permissions')
            ->whereIn('id', $duplicateIds)
            ->update([
                'status' => 0,
                'deleted_at' => now()->format('Y-m-d H:i:s'),
                'updated_at' => now()->format('Y-m-d H:i:s'),
            ]);
    }

    /**
     * 后台核心页面菜单权限配置。
     *
     * 返回值字段说明：
     * - name：后台菜单显示名称。
     * - slug：权限稳定标识。
     * - icon：Layui 图标类名，用于 Blade 侧栏展示。
     * - sort：菜单排序值，数值越小越靠前。
     * - route：后台 Blade 页面路径。
     * - api_route：页面主要接口命名路由；控制台绑定 dashboardData，角色/权限/菜单绑定对应树或列表接口。
     *
     * @return array<int, array{name:string, slug:string, icon:string, sort:int, route:string, api_route:string}>
     */
    private function corePageMenus()
    {
        return [
            [
                'name' => '控制台',
                'slug' => 'admin_dashboard',
                'icon' => 'layui-icon-console',
                'sort' => 1,
                'route' => '/admin/dashboard',
                'api_route' => 'admin_api_dashboardData',
            ],
            [
                'name' => '角色管理',
                'slug' => 'admin_roles',
                'icon' => 'layui-icon-group',
                'sort' => 30,
                'route' => '/admin/roles',
                'api_route' => 'admin_api_roleList',
            ],
            [
                'name' => '权限管理',
                'slug' => 'admin_permissions',
                'icon' => 'layui-icon-auz',
                'sort' => 31,
                'route' => '/admin/permissions',
                'api_route' => 'admin_api_permissionTree',
            ],
            [
                'name' => '菜单管理',
                'slug' => 'admin_menus',
                'icon' => 'layui-icon-spread-left',
                'sort' => 32,
                'route' => '/admin/menus',
                'api_route' => 'admin_api_menuTree',
            ],
        ];
    }
}
