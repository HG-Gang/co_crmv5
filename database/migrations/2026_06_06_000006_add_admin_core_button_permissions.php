<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/07/07
 * Time: 04:19
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * 新增后台核心模块按钮/API 权限。
 *
 * 文件功能：
 * - 用户、角色、权限、菜单是后台鉴权体系的基础模块，按钮权限必须来自 permissions 表。
 * - Blade 页面通过 data-permission 引用 permissions.slug，菜单接口返回当前管理员拥有的 slug 后统一控制按钮显示。
 * - 后端接口仍通过 check.permission:admin 中间件按 api_route 二次校验。
 */
class AddAdminCoreButtonPermissions extends Migration
{
    /**
     * 执行迁移：写入核心按钮/API 权限节点。
     *
     * @return void
     */
    public function up()
    {
        foreach ($this->permissions() as $index => $permission) {
            $this->upsertPermission($permission, ($index + 1) * 10);
        }
    }

    /**
     * 回滚迁移：删除本迁移维护的权限节点。
     *
     * @return void
     */
    public function down()
    {
        DB::table('permissions')->whereIn('slug', array_column($this->permissions(), 'slug'))->delete();
    }

    /**
     * 写入或更新单条核心权限配置。
     *
     * 参数说明：
     * - name：权限显示名称。
     * - slug：稳定权限标识，Blade data-permission 与 role_permissions 都依赖它。
     * - api_route：Laravel 后台 API 命名路由，check.permission:admin 用它匹配接口权限。
     *
     * @param array<string, string> $permission 权限配置。
     * @param int $sort 排序值。
     * @return void
     */
    private function upsertPermission(array $permission, $sort)
    {
        $now = now()->format('Y-m-d H:i:s');

        DB::table('permissions')->updateOrInsert(
            ['slug' => $permission['slug']],
            [
                'name' => $permission['name'],
                'guard_type' => 'admin',
                'parent_id' => 0,
                'type' => 3,
                'icon' => '',
                'sort' => $sort,
                'route' => '',
                'api_route' => $permission['api_route'],
                'status' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ]
        );
    }

    /**
     * 核心按钮/API 权限配置。
     *
     * @return array<int, array{name:string, slug:string, api_route:string}>
     */
    private function permissions()
    {
        return [
            ['name' => '修改用户状态', 'slug' => 'admin_user_status', 'api_route' => 'admin_api_changeUserStatus'],
            ['name' => '导出用户列表', 'slug' => 'admin_user_export', 'api_route' => 'admin_api_exportUsers'],
            ['name' => '创建角色', 'slug' => 'admin_role_create', 'api_route' => 'admin_api_createRole'],
            ['name' => '更新角色', 'slug' => 'admin_role_update', 'api_route' => 'admin_api_updateRole'],
            ['name' => '删除角色', 'slug' => 'admin_role_delete', 'api_route' => 'admin_api_deleteRole'],
            ['name' => '分配角色权限', 'slug' => 'admin_role_assign_permissions', 'api_route' => 'admin_api_assignPermissions'],
            ['name' => '更新权限', 'slug' => 'admin_permission_update', 'api_route' => 'admin_api_updatePermission'],
            ['name' => '创建菜单', 'slug' => 'admin_menu_create', 'api_route' => 'admin_api_createMenu'],
            ['name' => '更新菜单', 'slug' => 'admin_menu_update', 'api_route' => 'admin_api_updateMenu'],
        ];
    }
}
