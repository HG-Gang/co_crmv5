<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/06/06
 * Time: 20:41
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * 新增后台数据范围管理菜单和接口权限。
 *
 * 文件功能：
 * - permissions 表是后台菜单、按钮和接口权限的统一配置来源。
 * - 本迁移把“数据范围管理”页面和对应 API 写入权限表，后续角色授权只需要维护 role_permissions。
 * - 超级管理员仍可直接访问；普通管理员必须拥有对应权限记录后才能进入页面和调用接口。
 */
class AddAdminDataScopePermissions extends Migration
{
    /**
     * 执行迁移：写入页面菜单节点和接口按钮权限节点。
     *
     * @return void
     */
    public function up()
    {
        $now = now()->format('Y-m-d H:i:s');
        $parentId = $this->findSystemParentId();

        $pageId = DB::table('permissions')->insertGetId([
            'name' => '数据范围管理',
            'slug' => 'admin_data_scopes',
            'guard_type' => 'admin',
            'parent_id' => $parentId,
            'type' => 1,
            'icon' => 'layui-icon-vercode',
            'sort' => 45,
            'route' => '/admin/data-scopes',
            'api_route' => '',
            'status' => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $permissions = [
            ['name' => '查看角色数据范围', 'slug' => 'admin_data_scope_role_list', 'api_route' => 'admin_api_roleDataScopeList'],
            ['name' => '保存角色数据范围', 'slug' => 'admin_data_scope_role_save', 'api_route' => 'admin_api_saveRoleDataScope'],
            ['name' => '查看管理员代理绑定', 'slug' => 'admin_data_scope_binding_list', 'api_route' => 'admin_api_adminAgentBindingList'],
            ['name' => '保存管理员代理绑定', 'slug' => 'admin_data_scope_binding_save', 'api_route' => 'admin_api_saveAdminAgentBinding'],
            ['name' => '删除管理员代理绑定', 'slug' => 'admin_data_scope_binding_delete', 'api_route' => 'admin_api_deleteAdminAgentBinding'],
        ];

        foreach ($permissions as $index => $permission) {
            DB::table('permissions')->insert([
                'name' => $permission['name'],
                'slug' => $permission['slug'],
                'guard_type' => 'admin',
                'parent_id' => $pageId,
                'type' => 3,
                'icon' => '',
                'sort' => ($index + 1) * 10,
                'route' => '',
                'api_route' => $permission['api_route'],
                'status' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    /**
     * 回滚迁移：删除本迁移创建的菜单和接口权限节点。
     *
     * @return void
     */
    public function down()
    {
        DB::table('permissions')
            ->whereIn('slug', [
                'admin_data_scopes',
                'admin_data_scope_role_list',
                'admin_data_scope_role_save',
                'admin_data_scope_binding_list',
                'admin_data_scope_binding_save',
                'admin_data_scope_binding_delete',
            ])
            ->delete();
    }

    /**
     * 查找系统管理父级菜单。
     *
     * @return int 返回 permissions.id；如果历史数据没有系统管理父级，则返回 0，数据范围管理显示为顶级菜单。
     */
    private function findSystemParentId()
    {
        $parent = DB::table('permissions')
            ->where('guard_type', 'admin')
            ->whereIn('slug', ['admin_system', 'system', 'admin_system_management'])
            ->orderBy('id')
            ->first();

        return $parent ? (int) $parent->id : 0;
    }
}
