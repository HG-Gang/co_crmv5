<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/07/05
 * Time: 16:03
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * 新增后台持仓汇总页面与接口权限。
 *
 * 文件功能：
 * - 持仓汇总属于旧项目后台交易统计 P0 缺口，页面入口和列表接口必须来自 permissions 表配置。
 * - 页面权限通过 permissions.route=/admin/position-summary 控制后台菜单可见性。
 * - 列表接口权限通过 permissions.api_route=admin_api_positionSummaryList 交给 check.permission:admin 做后端鉴权。
 * - 本迁移只维护权限字典，不直接写入 role_permissions；具体角色能否访问仍由后台角色授权界面配置。
 */
class AddAdminPositionSummaryPermissions extends Migration
{
    /**
     * 执行迁移：写入持仓汇总页面权限和列表 API 权限。
     *
     * @return void
     */
    public function up()
    {
        $pageId = $this->upsertPermission([
            'name' => '持仓汇总',
            'slug' => 'admin_position_summary',
            'parent_id' => 0,
            'type' => 1,
            'route' => '/admin/position-summary',
            'api_route' => '',
            'icon' => 'layui-icon layui-icon-chart-screen',
            'sort' => 315,
        ]);

        $this->upsertPermission([
            'name' => '查看持仓汇总',
            'slug' => 'admin_position_summary_list',
            'parent_id' => $pageId,
            'type' => 3,
            'route' => '',
            'api_route' => 'admin_api_positionSummaryList',
            'icon' => '',
            'sort' => 10,
        ]);

        $this->upsertPermission([
            'name' => '导出持仓汇总',
            'slug' => 'admin_position_summary_export',
            'parent_id' => $pageId,
            'type' => 3,
            'route' => '',
            'api_route' => 'admin_api_exportPositionSummary',
            'icon' => '',
            'sort' => 20,
        ]);
    }

    /**
     * 回滚迁移：删除本迁移维护的权限字典节点。
     *
     * @return void
     */
    public function down()
    {
        DB::table('permissions')->whereIn('slug', [
            'admin_position_summary',
            'admin_position_summary_list',
            'admin_position_summary_export',
        ])->delete();
    }

    /**
     * 写入或更新单条权限配置。
     *
     * 参数含义：
     * - name：权限中文显示名，用于权限管理页面展示。
     * - slug：稳定权限标识，Blade 菜单、JS 按钮和 role_permissions 授权共用。
     * - parent_id：父级权限 ID；页面节点为 0，接口节点挂在页面节点下。
     * - type：权限类型，1=菜单/页面，3=接口/按钮动作。
     * - route：Blade 页面访问路径，仅页面节点填写。
     * - api_route：Laravel API 命名路由，仅接口节点填写。
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
                'deleted_at' => null,
            ]
        );

        return (int) DB::table('permissions')->where('slug', $permission['slug'])->value('id');
    }
}
