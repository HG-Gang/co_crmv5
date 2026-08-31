<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/07/12
 * Time: 23:58
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * 新增后台批量信用导入权限配置。
 *
 * 文件功能：
 * - 批量信用导入页面、列表接口和新增接口必须由 `permissions` 表配置驱动。
 * - 页面按钮使用 `permissions.slug` 做前端显示控制，接口使用 `permissions.api_route` 做后端鉴权。
 * - 本迁移只维护权限字典，不直接给任何角色授权，角色授权仍由 `role_permissions` 配置决定。
 */
class AddAdminBatchCreditImportPermissions extends Migration
{
    /**
     * 执行迁移：写入批量信用导入页面和动作权限。
     *
     * @return void
     */
    public function up()
    {
        $pageId = $this->upsertPermission([
            'name' => '批量信用导入',
            'slug' => 'admin_credit_imports',
            'parent_id' => 0,
            'type' => 1,
            'icon' => 'layui-icon-dollar',
            'sort' => 390,
            'route' => '/admin/credit-imports',
            'api_route' => '',
        ]);

        foreach ($this->actions() as $index => $action) {
            $this->upsertPermission([
                'name' => $action['name'],
                'slug' => $action['slug'],
                'parent_id' => $pageId,
                'type' => 3,
                'icon' => '',
                'sort' => ($index + 1) * 10,
                'route' => '',
                'api_route' => $action['api_route'],
            ]);
        }
    }

    /**
     * 回滚迁移：删除本迁移维护的权限节点。
     *
     * @return void
     */
    public function down()
    {
        DB::table('permissions')->whereIn('slug', $this->allSlugs())->delete();
    }

    /**
     * 写入或更新单条权限配置。
     *
     * 参数说明：
     * - slug：权限唯一标识，用于前端按钮控制和角色授权。
     * - type：权限类型，1=页面/菜单，3=按钮/API 动作。
     * - route：Blade 页面访问路径，仅页面节点填写。
     * - api_route：Laravel 后台 API 命名路由，仅动作节点填写。
     *
     * @param array<string, mixed> $permission 权限配置数组。
     * @return int permissions.id，用于绑定子权限 parent_id。
     */
    private function upsertPermission(array $permission)
    {
        $now = now()->format('Y-m-d H:i:s');

        DB::table('permissions')->updateOrInsert(
            ['slug' => $permission['slug']],
            [
                'name' => $permission['name'],
                'guard_type' => 'admin',
                'parent_id' => $permission['parent_id'],
                'type' => $permission['type'],
                'icon' => $permission['icon'],
                'sort' => $permission['sort'],
                'route' => $permission['route'],
                'api_route' => $permission['api_route'],
                'status' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ]
        );

        return (int) DB::table('permissions')->where('slug', $permission['slug'])->value('id');
    }

    /**
     * 返回批量信用导入动作权限。
     *
     * @return array<int, array{name:string, slug:string, api_route:string}>
     */
    private function actions()
    {
        return [
            ['name' => '查看批量信用导入', 'slug' => 'admin_batch_credit_import_list', 'api_route' => 'admin_api_creditImportList'],
            ['name' => '新增批量信用导入', 'slug' => 'admin_batch_credit_import_create', 'api_route' => 'admin_api_createCreditImport'],
            ['name' => '下载批量信用导入模板', 'slug' => 'admin_batch_credit_import_template', 'api_route' => 'admin_api_creditImportTemplate'],
            ['name' => '导出批量信用导入记录', 'slug' => 'admin_batch_credit_import_export', 'api_route' => 'admin_api_exportCreditImports'],
            ['name' => '重试批量信用导入', 'slug' => 'admin_batch_credit_import_retry', 'api_route' => 'admin_api_retryCreditImport'],
            ['name' => '同步批量信用导入', 'slug' => 'admin_batch_credit_import_sync', 'api_route' => 'admin_api_syncCreditImport'],
        ];
    }

    /**
     * 返回本迁移维护的全部权限 slug。
     *
     * @return array<int, string>
     */
    private function allSlugs()
    {
        return array_merge(['admin_credit_imports'], array_column($this->actions(), 'slug'));
    }
}
