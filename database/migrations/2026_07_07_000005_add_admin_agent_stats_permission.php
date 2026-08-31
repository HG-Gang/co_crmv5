<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/07/07
 * Time: 05:55
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * 文件功能：为已有环境补充后台代理统计按钮 API 权限。
 */
class AddAdminAgentStatsPermission extends Migration
{
    /**
     * 执行迁移：写入 admin_agent_stats 权限。
     *
     * @return void
     */
    public function up()
    {
        DB::table('permissions')->updateOrInsert(
            ['slug' => 'admin_agent_stats'],
            [
                'name' => '查看代理统计',
                'guard_type' => 'admin',
                'parent_id' => 0,
                'type' => 3,
                'icon' => '',
                'sort' => 60,
                'route' => '',
                'api_route' => 'admin_api_agentStatsList',
                'status' => 1,
                'created_at' => now()->format('Y-m-d H:i:s'),
                'updated_at' => now()->format('Y-m-d H:i:s'),
            ]
        );
    }

    /**
     * 回滚迁移：删除本迁移补充的代理统计权限。
     *
     * @return void
     */
    public function down()
    {
        DB::table('permissions')
            ->where('slug', 'admin_agent_stats')
            ->where('api_route', 'admin_api_agentStatsList')
            ->delete();
    }
}
