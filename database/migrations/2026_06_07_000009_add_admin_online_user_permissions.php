<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/07/07
 * Time: 03:02
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * 新增后台在线用户权限。
 *
 * 文件功能：
 * - 在线用户页面读取 user_onlines 最近活跃记录，属于后台审计类页面，页面入口和列表接口都必须来自 permissions 表。
 * - 页面权限通过 permissions.route=/admin/online-users 控制菜单可见性。
 * - API 权限通过 permissions.api_route=admin_api_onlineUserList 交给 check.permission:admin 做后端鉴权。
 * - 本迁移只维护权限字典，不直接写入 role_permissions，具体角色是否能看仍由后台权限分配界面决定。
 */
class AddAdminOnlineUserPermissions extends Migration
{
    /**
     * 执行迁移：写入在线用户页面权限和只读列表 API 权限。
     *
     * @return void
     */
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
            'name' => '查看在线用户',
            'slug' => 'admin_online_user_list',
            'parent_id' => $pageId,
            'type' => 3,
            'icon' => '',
            'sort' => 10,
            'route' => '',
            'api_route' => 'admin_api_onlineUserList',
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

    /**
     * 回滚迁移：删除本迁移维护的在线用户权限节点。
     *
     * @return void
     */
    public function down()
    {
        DB::table('permissions')->whereIn('slug', [
            'admin_online_users',
            'admin_online_user_list',
            'admin_online_user_force_offline',
        ])->delete();
    }

    /**
     * 写入或更新单条权限配置。
     *
     * 参数逻辑说明：
     * - slug：权限唯一标识，页面、按钮和角色授权都使用这个稳定 key。
     * - parent_id：父权限 ID，页面节点为 0，列表动作挂在页面节点下。
     * - type：权限类型，1=菜单/页面，3=按钮/API 动作。
     * - route：Blade 页面路径，只给页面权限填写。
     * - api_route：Laravel 命名 API 路由，只给动作权限填写。
     *
     * @param array<string, mixed> $permission 权限配置数组。
     * @return int permissions.id，用于绑定子权限 parent_id。
     */
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
