<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/07/29
 * Time: 01:47
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * 新增后台入金流水列表与导出 API 权限。
 *
 * 文件功能：
 * - 为 `admin_api_depositFlowList` 写入 `admin_deposit_flow_list` 权限行。
 * - 为 `admin_api_exportDepositFlows` 写入 `admin_deposit_flow_export` 权限行。
 * - 解决旧后台资金流水查询和导出已改为 MT4 流水口径后，普通管理员无法通过 permissions.api_route 授权的问题。
 * - 迁移使用 updateOrInsert，重复执行只更新权限配置，不创建重复数据。
 */
class AddAdminDepositFlowListPermission extends Migration
{
    /**
     * 写入入金流水列表和导出权限。
     *
     * @return void
     */
    public function up()
    {
        $now = now()->format('Y-m-d H:i:s');
        $parentId = (int) DB::table('permissions')
            ->where('guard_type', 'admin')
            ->where('slug', 'admin_deposits')
            ->where('status', 1)
            ->whereNull('deleted_at')
            ->value('id');

        DB::table('permissions')->updateOrInsert(
            ['slug' => 'admin_deposit_flow_list'],
            [
                'name' => '查看入金流水',
                'guard_type' => 'admin',
                'parent_id' => $parentId,
                'type' => 3,
                'icon' => '',
                'sort' => 35,
                'route' => '',
                'api_route' => 'admin_api_depositFlowList',
                'status' => 1,
                'deleted_at' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ]
        );

        DB::table('permissions')->updateOrInsert(
            ['slug' => 'admin_deposit_flow_export'],
            [
                'name' => '导出入金流水',
                'guard_type' => 'admin',
                'parent_id' => $parentId,
                'type' => 3,
                'icon' => '',
                'sort' => 45,
                'route' => '',
                'api_route' => 'admin_api_exportDepositFlows',
                'status' => 1,
                'deleted_at' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ]
        );
    }

    /**
     * 软停用入金流水列表和导出权限。
     *
     * @return void
     */
    public function down()
    {
        DB::table('permissions')
            ->whereIn('slug', ['admin_deposit_flow_list', 'admin_deposit_flow_export'])
            ->update([
                'status' => 0,
                'deleted_at' => now()->format('Y-m-d H:i:s'),
                'updated_at' => now()->format('Y-m-d H:i:s'),
            ]);
    }
}
