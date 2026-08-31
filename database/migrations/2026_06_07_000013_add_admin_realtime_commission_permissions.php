<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/07/05
 * Time: 16:52
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * 新增后台实时返佣权限配置。
 *
 * 文件功能：
 * - 实时返佣页面和列表接口必须由 permissions 表配置驱动，避免页面入口、按钮或 API 写死在前端。
 * - 页面权限通过 `permissions.route=/admin/realtime-commissions` 控制后台菜单可见性。
 * - 列表 API 权限通过 `permissions.api_route=admin_api_realtimeCommissionList` 交给 `check.permission:admin` 做后端鉴权。
 * - 本迁移只维护权限字典，不直接写入 role_permissions；具体角色能否访问仍由后台角色授权界面配置。
 */
class AddAdminRealtimeCommissionPermissions extends Migration
{
    /**
     * 执行迁移：写入实时返佣页面入口和列表 API 权限。
     *
     * @return void
     */
    public function up()
    {
        $pageId = $this->upsertPermission([
            'name' => '实时返佣',
            'slug' => 'admin_realtime_commissions',
            'parent_id' => 0,
            'type' => 1,
            'icon' => 'layui-icon-rmb',
            'sort' => 445,
            'route' => '/admin/realtime-commissions',
            'api_route' => '',
        ]);

        $this->upsertPermission([
            'name' => '查看实时返佣',
            'slug' => 'admin_realtime_commission_list',
            'parent_id' => $pageId,
            'type' => 3,
            'icon' => '',
            'sort' => 10,
            'route' => '',
            'api_route' => 'admin_api_realtimeCommissionList',
        ]);

        $this->upsertPermission([
            'name' => '导出实时返佣',
            'slug' => 'admin_realtime_commission_export',
            'parent_id' => $pageId,
            'type' => 3,
            'icon' => '',
            'sort' => 20,
            'route' => '',
            'api_route' => 'admin_api_exportRealtimeCommissions',
        ]);
    }

    /**
     * 回滚迁移：删除本迁移维护的实时返佣权限节点。
     *
     * @return void
     */
    public function down()
    {
        DB::table('permissions')->whereIn('slug', [
            'admin_realtime_commissions',
            'admin_realtime_commission_list',
            'admin_realtime_commission_export',
        ])->delete();
    }

    /**
     * 写入或更新单条权限配置。
     *
     * 参数说明：
     * - slug：权限唯一标识，供菜单、按钮、角色授权共同使用。
     * - parent_id：父级权限 ID；页面节点为 0，列表接口权限挂到页面节点下。
     * - type：权限类型，1=菜单/页面，3=按钮/API 动作。
     * - route：Blade 页面路径，仅页面节点填写。
     * - api_route：Laravel API 命名路由，仅动作节点填写。
     *
     * @param array<string, mixed> $permission 权限配置数组。
     * @return int permissions.id，用于后续绑定子权限 parent_id。
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
