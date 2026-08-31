<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/02
 * Time: 01:31
 */

/**
 * 新增后台在线用户强制下线按钮/API 权限。
 *
 * 文件功能：
 * - 向 permissions 表写入在线用户强制下线动作权限，并绑定默认角色。
 *
 * 字段语义：
 * - 仅操作 permissions/role_permissions 字典数据；回滚时删除本迁移写入的权限节点。
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

class AddAdminOnlineUserForceOfflinePermission extends Migration
{
    public function up()
    {
        $pageId = $this->upsertPermission([
            'name' => '在线用户',
            'slug' => 'admin_online_users',
            'parent_id' => 0,
            'type' => 1,
            'icon' => 'layui-icon-username',
            'sort' => 370,
            'route' => '/admin/online-users',
            'api_route' => '',
        ]);

        $this->upsertPermission([
            'name' => '强制下线在线记录',
            'slug' => 'admin_online_user_force_offline',
            'parent_id' => $pageId,
            'type' => 3,
            'icon' => '',
            'sort' => 20,
            'route' => '',
            'api_route' => 'admin_api_forceOfflineUser',
        ]);
    }

    public function down()
    {
        DB::table('permissions')
            ->where('slug', 'admin_online_user_force_offline')
            ->delete();
    }

    private function upsertPermission(array $permission)
    {
        $now = now()->format('Y-m-d H:i:s');

        DB::table('permissions')->updateOrInsert(
            ['slug' => $permission['slug']],
            [
                'name' => $permission['name'],
                'guard_type' => 'admin',
                'parent_id' => $permission['parent_id'],
                'type' => $permission['type'],
                'icon' => $permission['icon'],
                'sort' => $permission['sort'],
                'route' => $permission['route'],
                'api_route' => $permission['api_route'],
                'status' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ]
        );

        return (int) DB::table('permissions')->where('slug', $permission['slug'])->value('id');
    }
}
