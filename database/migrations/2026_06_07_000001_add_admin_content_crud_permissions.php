<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/07/09
 * Time: 07:33
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * 新增后台内容与账号类模块 CRUD 按钮/API 权限。
 *
 * 文件功能：
 * - 支付通道、管理员账号、新闻公告属于后台高敏或运营配置资源，新增、编辑、删除入口必须由 permissions 表配置驱动。
 * - Blade 页面通过 data-permission 读取 permissions.slug 控制按钮显隐。
 * - 后端接口通过 check.permission:admin 按 permissions.api_route 做二次鉴权，避免仅隐藏按钮造成越权调用。
 */
class AddAdminContentCrudPermissions extends Migration
{
    /**
     * 执行迁移：写入内容与账号类模块 CRUD 按钮/API 权限。
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
     * 内容与账号类模块 CRUD 权限清单。
     *
     * @return array<int, array{name:string, slug:string, api_route:string}>
     */
    private function permissions()
    {
        return [
            ['name' => '新增支付通道', 'slug' => 'admin_channel_create', 'api_route' => 'admin_api_createChannel'],
            ['name' => '更新支付通道', 'slug' => 'admin_channel_update', 'api_route' => 'admin_api_updateChannel'],
            ['name' => '删除支付通道', 'slug' => 'admin_channel_delete', 'api_route' => 'admin_api_deleteChannel'],
            ['name' => '启停支付通道', 'slug' => 'admin_channel_toggle', 'api_route' => 'admin_api_toggleChannel'],
            ['name' => '新增管理员', 'slug' => 'admin_admin_create', 'api_route' => 'admin_api_createAdmin'],
            ['name' => '更新管理员', 'slug' => 'admin_admin_update', 'api_route' => 'admin_api_updateAdmin'],
            ['name' => '重置管理员密码', 'slug' => 'admin_admin_reset_password', 'api_route' => 'admin_api_resetAdminPassword'],
            ['name' => '删除管理员', 'slug' => 'admin_admin_delete', 'api_route' => 'admin_api_deleteAdmin'],
            ['name' => '新增新闻公告', 'slug' => 'admin_news_create', 'api_route' => 'admin_api_createNews'],
            ['name' => '更新新闻公告', 'slug' => 'admin_news_update', 'api_route' => 'admin_api_updateNews'],
            ['name' => '删除新闻公告', 'slug' => 'admin_news_delete', 'api_route' => 'admin_api_deleteNews'],
            ['name' => '切换新闻发布状态', 'slug' => 'admin_news_toggle', 'api_route' => 'admin_api_toggleNews'],
        ];
    }
}
