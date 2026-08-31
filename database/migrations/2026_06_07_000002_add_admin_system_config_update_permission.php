<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/06/07
 * Time: 00:18
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * 新增后台系统配置更新按钮/API 权限。
 *
 * 文件功能：
 * - 系统配置属于后台高敏配置，编辑入口必须来自 permissions 表配置。
 * - Blade 行内编辑按钮使用 admin_system_config_update 控制显隐。
 * - updateSystemConfig 接口继续由 check.permission:admin 根据 api_route 做服务端鉴权。
 */
class AddAdminSystemConfigUpdatePermission extends Migration
{
    /**
     * 执行迁移：写入系统配置更新权限。
     *
     * @return void
     */
    public function up()
    {
        $now = now()->format('Y-m-d H:i:s');

        DB::table('permissions')->updateOrInsert(
            ['slug' => 'admin_system_config_update'],
            [
                'name' => '更新系统配置',
                'guard_type' => 'admin',
                'parent_id' => 0,
                'type' => 3,
                'icon' => '',
                'sort' => 10,
                'route' => '',
                'api_route' => 'admin_api_updateSystemConfig',
                'status' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ]
        );
    }

    /**
     * 回滚迁移：删除本迁移维护的权限节点。
     *
     * @return void
     */
    public function down()
    {
        DB::table('permissions')->where('slug', 'admin_system_config_update')->delete();
    }
}
