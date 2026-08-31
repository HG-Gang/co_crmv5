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
 * 新增后台代理操作按钮/API 权限。
 *
 * 文件功能：
 * - 代理等级和佣金直接影响代理业务结算，必须由 permissions 表配置驱动。
 * - Blade 页面通过 data-permission 控制按钮显隐，接口仍由 check.permission:admin 做服务端鉴权。
 */
class AddAdminAgentOperationPermissions extends Migration
{
    /**
     * 执行迁移：写入代理操作按钮/API 权限。
     *
     * @return void
     */
    public function up()
    {
        foreach ($this->permissions() as $index => $permission) {
            $now = now()->format('Y-m-d H:i:s');

            DB::table('permissions')->updateOrInsert(
                ['slug' => $permission['slug']],
                [
                    'name' => $permission['name'],
                    'guard_type' => 'admin',
                    'parent_id' => 0,
                    'type' => 3,
                    'icon' => '',
                    'sort' => ($index + 1) * 10,
                    'route' => '',
                    'api_route' => $permission['api_route'],
                    'status' => 1,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]
            );
        }
    }

    /**
     * 回滚迁移：删除本迁移维护的权限节点。
     *
     * @return void
     */
    public function down()
    {
        DB::table('permissions')->whereIn('slug', array_column($this->permissions(), 'slug'))->delete();
    }

    /**
     * 代理操作权限清单。
     *
     * @return array<int, array{name:string, slug:string, api_route:string}>
     */
    private function permissions()
    {
        return [
            ['name' => '更新代理等级', 'slug' => 'admin_agent_update_level', 'api_route' => 'admin_api_updateAgentLevel'],
            ['name' => '更新代理佣金', 'slug' => 'admin_agent_update_commission', 'api_route' => 'admin_api_updateAgentCommission'],
            ['name' => '导出代理列表', 'slug' => 'admin_agent_export', 'api_route' => 'admin_api_exportAgents'],
            ['name' => '查看代理统计', 'slug' => 'admin_agent_stats', 'api_route' => 'admin_api_agentStatsList'],
            ['name' => '确认代理', 'slug' => 'admin_agent_confirm', 'api_route' => 'admin_api_confirmAgent'],
            ['name' => '拒绝代理确认', 'slug' => 'admin_agent_reject_confirmation', 'api_route' => 'admin_api_rejectAgentConfirmation'],
        ];
    }
}
