<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/22
 * Time: 01:54
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * 补齐受保护后台 API 的权限字典缺口。
 *
 * 文件功能：
 * - 已上线环境可能已经执行过早期模块迁移，后续新增的按钮/API 权限不会自动写入。
 * - 本迁移以幂等方式维护新增权限行，确保 check.permission:admin 能按 permissions.api_route 授权。
 * - down 只软停用本迁移维护的权限，保留 ID 和角色授权关系，方便重新 up 恢复。
 */
class EnsureProtectedAdminRoutePermissions extends Migration
{
    /**
     * 写入或恢复受保护后台 API 权限。
     *
     * @return void
     */
    public function up()
    {
        $now = now()->format('Y-m-d H:i:s');

        foreach ($this->permissions() as $permission) {
            DB::table('permissions')->updateOrInsert(
                ['slug' => $permission['slug']],
                [
                    'name' => $permission['name'],
                    'guard_type' => 'admin',
                    'parent_id' => $this->parentId($permission['parent']),
                    'type' => $permission['type'],
                    'icon' => $permission['icon'],
                    'sort' => $permission['sort'],
                    'route' => $permission['route'],
                    'api_route' => $permission['api_route'],
                    'status' => 1,
                    'deleted_at' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]
            );
        }
    }

    /**
     * 软停用本迁移维护的权限行，不删除 ID 和角色绑定。
     *
     * @return void
     */
    public function down()
    {
        $now = now()->format('Y-m-d H:i:s');

        DB::table('permissions')
            ->whereIn('slug', array_column($this->permissions(), 'slug'))
            ->update([
                'status' => 0,
                'deleted_at' => $now,
                'updated_at' => $now,
            ]);
    }

    private function parentId(string $parent): int
    {
        if ($parent === '') {
            return 0;
        }

        $query = DB::table('permissions')
            ->where('guard_type', 'admin')
            ->where('status', 1)
            ->whereNull('deleted_at');

        if (strpos($parent, '/') === 0) {
            return (int) $query
                ->where('route', $parent)
                ->orderBy('id')
                ->value('id');
        }

        return (int) $query->where('slug', $parent)->value('id');
    }

    /**
     * @return array<int, array{name:string, slug:string, api_route:string, parent:string, type:int, icon:string, sort:int, route:string}>
     */
    private function permissions(): array
    {
        return [
            [
                'name' => '查看代理详情',
                'slug' => 'admin_agent_detail',
                'api_route' => 'admin_api_agentDetail',
                'parent' => 'admin_agents',
                'type' => 3,
                'icon' => '',
                'sort' => 30,
                'route' => '',
            ],
            [
                'name' => '查看代理上级链路',
                'slug' => 'admin_agent_parent_path',
                'api_route' => 'admin_api_agentParentPath',
                'parent' => 'admin_agents',
                'type' => 3,
                'icon' => '',
                'sort' => 35,
                'route' => '',
            ],
            [
                'name' => '启停管理员',
                'slug' => 'admin_admin_status',
                'api_route' => 'admin_api_changeAdminStatus',
                'parent' => 'admin_admins',
                'type' => 3,
                'icon' => '',
                'sort' => 20,
                'route' => '',
            ],
            [
                'name' => '启停大代理',
                'slug' => 'admin_big_agent_status',
                'api_route' => 'admin_api_changeBigAgentStatus',
                'parent' => 'admin_big_agents',
                'type' => 3,
                'icon' => '',
                'sort' => 50,
                'route' => '',
            ],
            [
                'name' => '创建代理',
                'slug' => 'admin_agent_create',
                'api_route' => 'admin_api_createAgent',
                'parent' => 'admin_agents',
                'type' => 3,
                'icon' => '',
                'sort' => 40,
                'route' => '',
            ],
            [
                'name' => '创建权限',
                'slug' => 'admin_permission_create',
                'api_route' => 'admin_api_createPermission',
                'parent' => 'admin_permissions',
                'type' => 3,
                'icon' => '',
                'sort' => 20,
                'route' => '',
            ],
            [
                'name' => '创建产品品种',
                'slug' => 'admin_production_create',
                'api_route' => 'admin_api_createProduction',
                'parent' => 'admin_productions',
                'type' => 3,
                'icon' => '',
                'sort' => 20,
                'route' => '',
            ],
            [
                'name' => '创建用户',
                'slug' => 'admin_user_create',
                'api_route' => 'admin_api_createUser',
                'parent' => '/admin/users',
                'type' => 3,
                'icon' => '',
                'sort' => 20,
                'route' => '',
            ],
            [
                'name' => '删除菜单',
                'slug' => 'admin_menu_delete',
                'api_route' => 'admin_api_deleteMenu',
                'parent' => 'admin_menus',
                'type' => 3,
                'icon' => '',
                'sort' => 30,
                'route' => '',
            ],
            [
                'name' => '删除权限',
                'slug' => 'admin_permission_delete',
                'api_route' => 'admin_api_deletePermission',
                'parent' => 'admin_permissions',
                'type' => 3,
                'icon' => '',
                'sort' => 40,
                'route' => '',
            ],
            [
                'name' => '删除产品品种',
                'slug' => 'admin_production_delete',
                'api_route' => 'admin_api_deleteProduction',
                'parent' => 'admin_productions',
                'type' => 3,
                'icon' => '',
                'sort' => 40,
                'route' => '',
            ],
            [
                'name' => '查看入金流水',
                'slug' => 'admin_deposit_flow_list',
                'api_route' => 'admin_api_depositFlowList',
                'parent' => 'admin_deposits',
                'type' => 3,
                'icon' => '',
                'sort' => 35,
                'route' => '',
            ],
            [
                'name' => '导出入金流水',
                'slug' => 'admin_deposit_flow_export',
                'api_route' => 'admin_api_exportDepositFlows',
                'parent' => 'admin_deposits',
                'type' => 3,
                'icon' => '',
                'sort' => 45,
                'route' => '',
            ],
            [
                'name' => '查看入金详情',
                'slug' => 'admin_deposit_detail',
                'api_route' => 'admin_api_depositDetail',
                'parent' => 'admin_deposits',
                'type' => 3,
                'icon' => '',
                'sort' => 40,
                'route' => '',
            ],
            [
                'name' => '导出入金记录',
                'slug' => 'admin_deposit_export',
                'api_route' => 'admin_api_exportDeposits',
                'parent' => 'admin_deposits',
                'type' => 3,
                'icon' => '',
                'sort' => 50,
                'route' => '',
            ],
            [
                'name' => '导出礼品发货记录',
                'slug' => 'admin_gift_export',
                'api_route' => 'admin_api_exportGiftShipments',
                'parent' => 'admin_gifts',
                'type' => 3,
                'icon' => '',
                'sort' => 40,
                'route' => '',
            ],
            [
                'name' => '导出持仓汇总',
                'slug' => 'admin_position_summary_export',
                'api_route' => 'admin_api_exportPositionSummary',
                'parent' => 'admin_position_summary',
                'type' => 3,
                'icon' => '',
                'sort' => 20,
                'route' => '',
            ],
            [
                'name' => '导出产品品种',
                'slug' => 'admin_production_export',
                'api_route' => 'admin_api_exportProductions',
                'parent' => 'admin_productions',
                'type' => 3,
                'icon' => '',
                'sort' => 50,
                'route' => '',
            ],
            [
                'name' => '导出实时返佣',
                'slug' => 'admin_realtime_commission_export',
                'api_route' => 'admin_api_exportRealtimeCommissions',
                'parent' => 'admin_realtime_commissions',
                'type' => 3,
                'icon' => '',
                'sort' => 20,
                'route' => '',
            ],
            [
                'name' => '导出出金记录',
                'slug' => 'admin_withdraw_export',
                'api_route' => 'admin_api_exportWithdrawals',
                'parent' => 'admin_withdrawals',
                'type' => 3,
                'icon' => '',
                'sort' => 50,
                'route' => '',
            ],
            [
                'name' => '操作日志',
                'slug' => 'admin_operation_logs',
                'api_route' => 'admin_api_operationLogs',
                'parent' => '',
                'type' => 1,
                'icon' => 'layui-icon-log',
                'sort' => 375,
                'route' => '/admin/operation-logs',
            ],
            [
                'name' => '重置用户密码',
                'slug' => 'admin_user_reset_password',
                'api_route' => 'admin_api_resetUserPassword',
                'parent' => '/admin/users',
                'type' => 3,
                'icon' => '',
                'sort' => 60,
                'route' => '',
            ],
            [
                'name' => '更新礼品物流状态',
                'slug' => 'admin_gift_update_shipment',
                'api_route' => 'admin_api_updateGiftShipment',
                'parent' => 'admin_gifts',
                'type' => 3,
                'icon' => '',
                'sort' => 50,
                'route' => '',
            ],
            [
                'name' => '更新产品品种',
                'slug' => 'admin_production_update',
                'api_route' => 'admin_api_updateProduction',
                'parent' => 'admin_productions',
                'type' => 3,
                'icon' => '',
                'sort' => 30,
                'route' => '',
            ],
            [
                'name' => '更新用户',
                'slug' => 'admin_user_update',
                'api_route' => 'admin_api_updateUser',
                'parent' => '/admin/users',
                'type' => 3,
                'icon' => '',
                'sort' => 30,
                'route' => '',
            ],
            [
                'name' => '上传文件',
                'slug' => 'admin_upload_file',
                'api_route' => 'admin_api_uploadFile',
                'parent' => '',
                'type' => 3,
                'icon' => '',
                'sort' => 900,
                'route' => '',
            ],
            [
                'name' => '查看用户详情',
                'slug' => 'admin_user_detail',
                'api_route' => 'admin_api_userDetail',
                'parent' => '/admin/users',
                'type' => 3,
                'icon' => '',
                'sort' => 40,
                'route' => '',
            ],
            [
                'name' => '查看仓位清零记录',
                'slug' => 'admin_whs_exp_zero_records',
                'api_route' => 'admin_api_whsExpZeroRecords',
                'parent' => 'admin_page_whs_exp_zero',
                'type' => 3,
                'icon' => '',
                'sort' => 20,
                'route' => '',
            ],
        ];
    }
}
