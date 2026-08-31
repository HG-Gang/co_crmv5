<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/06/06
 * Time: 23:55
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * 新增后台配置类模块 CRUD 按钮/API 权限。
 *
 * 文件功能：
 * - 代理等级、分组配置属于后台基础配置，新增、编辑、删除入口必须来自 permissions 表配置。
 * - Blade 页面通过 data-permission 读取 permissions.slug 控制按钮显隐。
 * - 后端接口通过 check.permission:admin 按 permissions.api_route 做二次鉴权。
 */
class AddAdminConfigCrudPermissions extends Migration
{
    /**
     * 执行迁移：写入配置类模块 CRUD 按钮/API 权限。
     *
     * @return void
     */
    public function up()
    {
        foreach ($this->permissions() as $index => $permission) {
            $this->upsertPermission($permission, ($index + 1) * 10);
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
     * 写入或更新单条权限配置。
     *
     * 参数说明：
     * - name：权限显示名称，用于权限管理界面展示。
     * - slug：稳定权限标识，Blade data-permission 和角色授权都依赖该值。
     * - api_route：Laravel 后台 API 命名路由，check.permission:admin 使用该值匹配接口权限。
     * - sort：同级排序值，仅影响权限管理树展示顺序。
     *
     * @param array<string, string> $permission 权限配置。
     * @param int $sort 排序值。
     * @return void
     */
    private function upsertPermission(array $permission, $sort)
    {
        $now = now()->format('Y-m-d H:i:s');

        DB::table('permissions')->updateOrInsert(
            ['slug' => $permission['slug']],
            [
                'name' => $permission['name'],
                'guard_type' => 'admin',
                'parent_id' => 0,
                'type' => 3,
                'icon' => '',
                'sort' => $sort,
                'route' => '',
                'api_route' => $permission['api_route'],
                'status' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ]
        );
    }

    /**
     * 配置类模块 CRUD 权限清单。
     *
     * @return array<int, array{name:string, slug:string, api_route:string}>
     */
    private function permissions()
    {
        return [
            ['name' => '新增代理等级', 'slug' => 'admin_agent_level_create', 'api_route' => 'admin_api_createAgentLevel'],
            ['name' => '更新代理等级', 'slug' => 'admin_agent_level_update', 'api_route' => 'admin_api_updateAgentLevel2'],
            ['name' => '删除代理等级', 'slug' => 'admin_agent_level_delete', 'api_route' => 'admin_api_deleteAgentLevel'],
            ['name' => '新增组别配置', 'slug' => 'admin_group_config_create', 'api_route' => 'admin_api_createGroupConfig'],
            ['name' => '更新组别配置', 'slug' => 'admin_group_config_update', 'api_route' => 'admin_api_updateGroupConfig'],
            ['name' => '删除组别配置', 'slug' => 'admin_group_config_delete', 'api_route' => 'admin_api_deleteGroupConfig'],
        ];
    }
}
