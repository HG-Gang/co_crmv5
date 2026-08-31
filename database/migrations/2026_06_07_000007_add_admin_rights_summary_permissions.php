<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/07/05
 * Time: 16:04
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * 新增后台权益汇总权限配置。
 *
 * 文件功能：
 * - 权益汇总属于旧项目 P0 财务统计模块，页面入口和 API 必须来自 `permissions` 表配置。
 * - 页面权限使用 `permissions.route` 控制菜单可见性。
 * - 列表 API 权限使用 `permissions.api_route` 交给 `check.permission:admin` 后端鉴权。
 * - 本迁移只维护权限字典，不直接给任何角色授权；具体角色可见性仍由 `role_permissions` 配置决定。
 */
class AddAdminRightsSummaryPermissions extends Migration
{
    /**
     * 执行迁移：写入权益汇总页面和列表 API 权限。
     *
     * @return void
     */
    public function up()
    {
        $pageId = $this->upsertPermission([
            'name' => '权益汇总',
            'slug' => 'admin_rights_summary',
            'parent_id' => 0,
            'type' => 1,
            'icon' => 'layui-icon-chart-screen',
            'sort' => 430,
            'route' => '/admin/rights-summary',
            'api_route' => '',
        ]);

        $this->upsertPermission([
            'name' => '查看权益汇总',
            'slug' => 'admin_rights_summary_list',
            'parent_id' => $pageId,
            'type' => 3,
            'icon' => '',
            'sort' => 10,
            'route' => '',
            'api_route' => 'admin_api_rightsSummaryList',
        ]);

        $this->upsertPermission([
            'name' => '手动确认权益结算',
            'slug' => 'admin_rights_summary_manual_confirm',
            'parent_id' => $pageId,
            'type' => 3,
            'icon' => '',
            'sort' => 20,
            'route' => '',
            'api_route' => 'admin_api_manualConfirmRightsSettlement',
        ]);

        $this->upsertPermission([
            'name' => '导出权益汇总',
            'slug' => 'admin_rights_summary_export',
            'parent_id' => $pageId,
            'type' => 3,
            'icon' => '',
            'sort' => 30,
            'route' => '',
            'api_route' => 'admin_api_exportRightsSummary',
        ]);
    }

    /**
     * 回滚迁移：删除本迁移维护的权限节点。
     *
     * @return void
     */
    public function down()
    {
        DB::table('permissions')->whereIn('slug', [
            'admin_rights_summary',
            'admin_rights_summary_list',
            'admin_rights_summary_manual_confirm',
            'admin_rights_summary_export',
        ])->delete();
    }

    /**
     * 写入或更新单条权限配置。
     *
     * 参数逻辑说明：
     * - slug：权限唯一标识，供菜单、按钮和角色授权共同使用。
     * - parent_id：父级权限 ID，页面节点为 0，动作节点绑定到页面节点。
     * - type：权限类型，1=页面/菜单，3=按钮/API 动作。
     * - route：Blade 页面访问路径，仅页面节点填写。
     * - api_route：Laravel 后台 API 命名路由，仅动作节点填写。
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
