<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/07/07
 * Time: 04:52
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * 文件功能：为已有环境补充后台代理列表导出按钮/API 权限。
 */
class AddAdminAgentExportPermission extends Migration
{
    /**
     * 执行迁移：写入 admin_agent_export 权限。
     *
     * @return void
     */
    public function up()
    {
        DB::table('permissions')->updateOrInsert(
            ['slug' => 'admin_agent_export'],
            [
                'name' => '导出代理列表',
                'guard_type' => 'admin',
                'parent_id' => 0,
                'type' => 3,
                'icon' => '',
                'sort' => 30,
                'route' => '',
                'api_route' => 'admin_api_exportAgents',
                'status' => 1,
                'created_at' => now()->format('Y-m-d H:i:s'),
                'updated_at' => now()->format('Y-m-d H:i:s'),
            ]
        );
    }

    /**
     * 回滚迁移：删除本迁移补充的代理导出权限。
     *
     * @return void
     */
    public function down()
    {
        DB::table('permissions')
            ->where('slug', 'admin_agent_export')
            ->where('api_route', 'admin_api_exportAgents')
            ->delete();
    }
}
