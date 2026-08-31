<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/18
 * Time: 23:05
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * 新增后台盈利风险查看权限。
 *
 * 文件功能：
 * - 幂等 upsert admin_risk_profit_users 按钮/API 权限（api_route=admin_api_riskProfitUsers），
 *   挂在 admin_risk 页面权限之下；父权限缺失时直接抛异常终止迁移；回滚软删除（status=0）保留主键。
 */
class AddAdminRiskProfitPermission extends Migration
{
    /**
     * 本迁移写入/更新的权限节点 slug（盈利风险查看）。upsert 按 slug 幂等，回滚仅置 status=0
     * 软删除保留主键；改值会脱离 check.permission:admin 的权限点映射，导致风控盈利榜接口 403。
     */
    private const SLUG = 'admin_risk_profit_users';

    public function up()
    {
        $parentId = DB::table('permissions')
            ->where('slug', 'admin_risk')
            ->where('guard_type', 'admin')
            ->value('id');
        if (!$parentId) {
            throw new \RuntimeException('admin_risk parent permission is required.');
        }

        $now = now()->format('Y-m-d H:i:s');
        DB::table('permissions')->updateOrInsert(
            ['slug' => self::SLUG],
            [
                'name' => '查看盈利风险',
                'guard_type' => 'admin',
                'parent_id' => (int) $parentId,
                'type' => 3,
                'icon' => '',
                'sort' => 5,
                'route' => '',
                'api_route' => 'admin_api_riskProfitUsers',
                'status' => 1,
                'created_at' => $now,
                'updated_at' => $now,
                'deleted_at' => null,
            ]
        );
    }

    public function down()
    {
        $now = now()->format('Y-m-d H:i:s');
        DB::table('permissions')
            ->where('slug', self::SLUG)
            ->update([
                'status' => 0,
                'updated_at' => $now,
                'deleted_at' => $now,
            ]);
    }
}
