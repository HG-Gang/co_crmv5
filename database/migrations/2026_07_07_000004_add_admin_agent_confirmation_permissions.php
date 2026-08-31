<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/07/07
 * Time: 05:36
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * 文件功能：为已有环境补充后台代理确认/拒绝确认按钮 API 权限。
 */
class AddAdminAgentConfirmationPermissions extends Migration
{
    /**
     * 执行迁移：写入代理确认相关权限。
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
                    'sort' => 40 + ($index * 10),
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
     * 回滚迁移：删除本迁移补充的代理确认权限。
     *
     * @return void
     */
    public function down()
    {
        DB::table('permissions')->whereIn('slug', array_column($this->permissions(), 'slug'))->delete();
    }

    /**
     * 代理确认权限清单。
     *
     * @return array<int, array{name:string, slug:string, api_route:string}>
     */
    private function permissions()
    {
        return [
            ['name' => '确认代理', 'slug' => 'admin_agent_confirm', 'api_route' => 'admin_api_confirmAgent'],
            ['name' => '拒绝代理确认', 'slug' => 'admin_agent_reject_confirmation', 'api_route' => 'admin_api_rejectAgentConfirmation'],
        ];
    }
}
