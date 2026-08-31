<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/02
 * Time: 01:31
 */

/**
 * 修复 WHS 体验零值与出金别名权限的 slug 映射。
 *
 * 文件功能：
 * - 修正 whs_exp_zero 与 withdraw 相关权限的 api_route/slug 别名，保证
 *   check.permission:admin 二次鉴权与前端按钮显隐一致。
 *
 * 字段语义：
 * - 仅更新 permissions 字典数据（slug/api_route/name），不改业务表结构；
 * - 回滚恢复原别名映射。
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Repair admin Blade page and button permission dictionary entries.
 *
 * The affected pages already have Blade routes and protected API routes. This
 * migration fills the missing permissions rows so menu visibility and button
 * authorization use the same permissions table source of truth.
 */
class FixAdminWhsExpZeroAndWithdrawAliasPermissions extends Migration
{
    /**
     * Write missing page permissions and the WHS zero action permission.
     *
     * @return void
     */
    public function up()
    {
        foreach ($this->pages() as $index => $page) {
            $this->upsertPermission([
                'name' => $page['name'],
                'slug' => $page['slug'],
                'parent_id' => 0,
                'type' => 1,
                'icon' => $page['icon'],
                'sort' => 520 + ($index * 10),
                'route' => $page['route'],
                'api_route' => $page['api_route'],
            ]);
        }

        $pageId = (int) DB::table('permissions')->where('slug', 'admin_page_whs_exp_zero')->value('id');

        $this->upsertPermission([
            'name' => 'Position zero action',
            'slug' => 'admin_whs_exp_zero',
            'parent_id' => $pageId,
            'type' => 3,
            'icon' => '',
            'sort' => 10,
            'route' => '',
            'api_route' => 'admin_api_whsExpZero',
        ]);
    }

    /**
     * Remove only the permission rows owned by this repair migration.
     *
     * @return void
     */
    public function down()
    {
        DB::table('permissions')->whereIn('slug', $this->allSlugs())->delete();
    }

    /**
     * Insert or update a permission row.
     *
     * @param array<string, mixed> $permission
     * @return int
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
                'deleted_at' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ]
        );

        return (int) DB::table('permissions')->where('slug', $permission['slug'])->value('id');
    }

    /**
     * Page permissions repaired by this migration.
     *
     * @return array<int, array<string, string>>
     */
    private function pages()
    {
        return [
            [
                'name' => 'Position zero',
                'slug' => 'admin_page_whs_exp_zero',
                'route' => '/admin/whs-exp-zero',
                'api_route' => 'admin_api_whsExpZeroList',
                'icon' => 'layui-icon-chart-screen',
            ],
            [
                'name' => 'Withdraw pending',
                'slug' => 'admin_withdraw_pending',
                'route' => '/admin/withdraw/pending',
                'api_route' => 'admin_api_withdrawList',
                'icon' => 'layui-icon-transfer',
            ],
            [
                'name' => 'Withdraw processing',
                'slug' => 'admin_withdraw_processing',
                'route' => '/admin/withdraw/processing',
                'api_route' => 'admin_api_withdrawList',
                'icon' => 'layui-icon-transfer',
            ],
            [
                'name' => 'Withdraw completed',
                'slug' => 'admin_withdraw_completed',
                'route' => '/admin/withdraw/completed',
                'api_route' => 'admin_api_withdrawList',
                'icon' => 'layui-icon-transfer',
            ],
            [
                'name' => 'Withdraw failed',
                'slug' => 'admin_withdraw_failed',
                'route' => '/admin/withdraw/failed',
                'api_route' => 'admin_api_withdrawList',
                'icon' => 'layui-icon-transfer',
            ],
        ];
    }

    /**
     * @return array<int, string>
     */
    private function allSlugs()
    {
        return array_merge(array_column($this->pages(), 'slug'), ['admin_whs_exp_zero']);
    }
}
