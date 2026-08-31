<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/07/12
 * Time: 23:30
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * 新增后台批量入金/出金导入权限配置。
 *
 * 文件功能：
 * - permissions 表是后台菜单、页面、按钮和接口鉴权的唯一数据表配置来源。
 * - 本迁移写入批量入金导入、批量出金导入两个页面节点，以及列表/新增两个动作节点。
 * - role_permissions 是否授权给具体角色，不在本迁移中处理，仍由后台角色权限配置界面维护。
 */
class AddAdminBatchAmountImportPermissions extends Migration
{
    /**
     * 执行迁移：写入页面权限和动作权限。
     *
     * @return void
     */
    public function up()
    {
        foreach ($this->modules() as $moduleIndex => $module) {
            $pageId = $this->upsertPermission([
                'name' => $module['name'],
                'slug' => $module['slug'],
                'parent_id' => 0,
                'type' => 1,
                'icon' => $module['icon'],
                'sort' => 360 + (($moduleIndex + 1) * 10),
                'route' => $module['route'],
                'api_route' => '',
            ]);

            foreach ($module['actions'] as $actionIndex => $action) {
                $this->upsertPermission([
                    'name' => $action['name'],
                    'slug' => $action['slug'],
                    'parent_id' => $pageId,
                    'type' => 3,
                    'icon' => '',
                    'sort' => ($actionIndex + 1) * 10,
                    'route' => '',
                    'api_route' => $action['api_route'],
                ]);
            }
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
     * 写入或更新权限配置。
     *
     * 参数说明：
     * - slug：权限稳定标识，前端 data-permission 和角色授权都使用该值。
     * - type：权限类型，1=页面/菜单，3=按钮/API 动作。
     * - route：Blade 页面访问路径，仅页面节点填写。
     * - api_route：Laravel 后台 API 命名路由，仅动作节点填写，用于 check.permission:admin。
     *
     * @param array<string, mixed> $permission 权限配置数组。
     * @return int permissions.id，用于作为子权限 parent_id。
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
     * 返回本模块权限配置。
     *
     * @return array<int, array<string, mixed>> 页面节点和动作节点配置。
     */
    private function modules()
    {
        return [
            [
                'name' => '批量入金导入',
                'slug' => 'admin_deposit_imports',
                'route' => '/admin/deposit-imports',
                'icon' => 'layui-icon-upload-drag',
                'actions' => [
                    ['name' => '查看批量入金导入', 'slug' => 'admin_batch_deposit_import_list', 'api_route' => 'admin_api_depositImportList'],
                    ['name' => '新增批量入金导入', 'slug' => 'admin_batch_deposit_import_create', 'api_route' => 'admin_api_createDepositImport'],
                    ['name' => '下载批量入金导入模板', 'slug' => 'admin_batch_deposit_import_template', 'api_route' => 'admin_api_depositImportTemplate'],
                    ['name' => '导出批量入金导入记录', 'slug' => 'admin_batch_deposit_import_export', 'api_route' => 'admin_api_exportDepositImports'],
                    ['name' => '重试批量入金导入', 'slug' => 'admin_batch_deposit_import_retry', 'api_route' => 'admin_api_retryDepositImport'],
                    ['name' => '同步批量入金导入', 'slug' => 'admin_batch_deposit_import_sync', 'api_route' => 'admin_api_syncDepositImport'],
                ],
            ],
            [
                'name' => '批量出金导入',
                'slug' => 'admin_withdraw_imports',
                'route' => '/admin/withdraw-imports',
                'icon' => 'layui-icon-export',
                'actions' => [
                    ['name' => '查看批量出金导入', 'slug' => 'admin_batch_withdraw_import_list', 'api_route' => 'admin_api_withdrawImportList'],
                    ['name' => '新增批量出金导入', 'slug' => 'admin_batch_withdraw_import_create', 'api_route' => 'admin_api_createWithdrawImport'],
                    ['name' => '下载批量出金导入模板', 'slug' => 'admin_batch_withdraw_import_template', 'api_route' => 'admin_api_withdrawImportTemplate'],
                    ['name' => '导出批量出金导入记录', 'slug' => 'admin_batch_withdraw_import_export', 'api_route' => 'admin_api_exportWithdrawImports'],
                    ['name' => '重试批量出金导入', 'slug' => 'admin_batch_withdraw_import_retry', 'api_route' => 'admin_api_retryWithdrawImport'],
                    ['name' => '同步批量出金导入', 'slug' => 'admin_batch_withdraw_import_sync', 'api_route' => 'admin_api_syncWithdrawImport'],
                ],
            ],
        ];
    }

    /**
     * 返回本迁移维护的所有权限 slug。
     *
     * @return array<int, string> 权限标识数组。
     */
    private function allSlugs()
    {
        $slugs = [];
        foreach ($this->modules() as $module) {
            $slugs[] = $module['slug'];
            foreach ($module['actions'] as $action) {
                $slugs[] = $action['slug'];
            }
        }

        return $slugs;
    }
}
