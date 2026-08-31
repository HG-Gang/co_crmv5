<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/02
 * Time: 01:31
 */

/**
 * 新增后台用户导出按钮/API 权限。
 *
 * 文件功能：
 * - 向 permissions 表写入用户列表导出权限，并绑定默认角色。
 *
 * 字段语义：
 * - 仅操作 permissions/role_permissions 字典数据；回滚时删除本迁移写入的权限节点。
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

class AddAdminUserExportPermission extends Migration
{
    public function up()
    {
        $now = now()->format('Y-m-d H:i:s');

        DB::table('permissions')->updateOrInsert(
            ['slug' => 'admin_user_export'],
            [
                'name' => '导出用户列表',
                'guard_type' => 'admin',
                'parent_id' => 0,
                'type' => 3,
                'icon' => '',
                'sort' => 15,
                'route' => '',
                'api_route' => 'admin_api_exportUsers',
                'status' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ]
        );
    }

    public function down()
    {
        DB::table('permissions')
            ->where('slug', 'admin_user_export')
            ->where('api_route', 'admin_api_exportUsers')
            ->delete();
    }
}
