<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/07/16
 * Time: 01:20
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * 补齐旧用户组兼容接口的后台按钮权限。
 *
 * 文件功能：
 *
 * 用户组已统一落到 group_configs，但其旧字段兼容 Controller 需要独立命名路由；
 * 本迁移确保 check.permission:admin 能按每个写操作执行后端二次鉴权。
 */
class AddAdminUserGroupCompatibilityPermissions extends Migration
{
    public function up()
    {
        $parentId = (int) DB::table('permissions')
            ->where('guard_type', 'admin')
            ->where('slug', 'admin_group_configs')
            ->value('id');
        $now = now()->format('Y-m-d H:i:s');
        $permissions = [
            ['name' => '查看用户组兼容列表', 'slug' => 'admin_user_group_list', 'api_route' => 'admin_api_userGroupList', 'sort' => 61],
            ['name' => '创建用户组兼容配置', 'slug' => 'admin_user_group_create', 'api_route' => 'admin_api_createUserGroup', 'sort' => 62],
            ['name' => '更新用户组兼容配置', 'slug' => 'admin_user_group_update', 'api_route' => 'admin_api_updateUserGroup', 'sort' => 63],
            ['name' => '删除用户组兼容配置', 'slug' => 'admin_user_group_delete', 'api_route' => 'admin_api_deleteUserGroup', 'sort' => 64],
        ];

        foreach ($permissions as $permission) {
            DB::table('permissions')->updateOrInsert(
                ['slug' => $permission['slug']],
                [
                    'name' => $permission['name'],
                    'guard_type' => 'admin',
                    'parent_id' => $parentId,
                    'type' => 3,
                    'icon' => '',
                    'sort' => $permission['sort'],
                    'route' => '',
                    'api_route' => $permission['api_route'],
                    'status' => 1,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]
            );
        }
    }

    public function down()
    {
        DB::table('permissions')->whereIn('slug', [
            'admin_user_group_list',
            'admin_user_group_create',
            'admin_user_group_update',
            'admin_user_group_delete',
        ])->delete();
    }
}
