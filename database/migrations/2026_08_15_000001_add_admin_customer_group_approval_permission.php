<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/15
 * Time: 22:11
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * 为旧客户转组审批接口补充独立后台权限。
 *
 * 文件功能：
 * - 幂等 upsert admin_customer_group_approval 按钮/API 权限（api_route=admin_api_customerGroupApproval），
 *   供 check.permission:admin 对旧 Customer 分组审批接口做服务端二次鉴权；回滚删除该权限行。
 */
class AddAdminCustomerGroupApprovalPermission extends Migration
{
    public function up(): void
    {
        $permission = $this->permission();

        DB::table('permissions')->updateOrInsert(
            ['slug' => $permission['slug']],
            [
                'name' => $permission['name'],
                'guard_type' => 'admin',
                'parent_id' => 0,
                'type' => 3,
                'icon' => '',
                'sort' => 40,
                'route' => '',
                'api_route' => $permission['api_route'],
                'status' => 1,
                'deleted_at' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );
    }

    public function down(): void
    {
        DB::table('permissions')->where('slug', $this->permission()['slug'])->delete();
    }

    /**
     * @return array{name:string,slug:string,api_route:string}
     */
    private function permission(): array
    {
        return [
            'name' => 'Customer 分组审批',
            'slug' => 'admin_customer_group_approval',
            'api_route' => 'admin_api_customerGroupApproval',
        ];
    }
}
