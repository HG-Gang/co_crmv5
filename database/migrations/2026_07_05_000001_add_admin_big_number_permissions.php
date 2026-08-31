<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/07/05
 * Time: 12:31
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * 新增后台大数据统计 API 权限。
 *
 * 文件功能：
 * - 旧项目 BigNumberController 的 dashboard/trend 属于后台统计动作，不单独新增页面菜单。
 * - 权限节点挂在后台控制台页面下，通过 permissions.api_route 交给 check.permission:admin 鉴权。
 */
class AddAdminBigNumberPermissions extends Migration
{
    /**
     * 写入后台大数据统计动作权限。
     *
     * @return void
     */
    public function up()
    {
        $parentId = (int) DB::table('permissions')
            ->where('guard_type', 'admin')
            ->where('route', '/admin/dashboard')
            ->value('id');

        foreach ($this->permissions($parentId) as $permission) {
            $this->upsertPermission($permission);
        }
    }

    /**
     * 删除本迁移维护的权限节点。
     *
     * @return void
     */
    public function down()
    {
        DB::table('permissions')->whereIn('slug', array_column($this->permissions(0), 'slug'))->delete();
    }

    /**
     * 生成权限配置。
     *
     * @param int $parentId 后台控制台页面权限 ID，不存在时允许为 0。
     * @return array<int, array<string, mixed>>
     */
    private function permissions(int $parentId): array
    {
        return [
            [
                'name' => '后台大数据统计',
                'slug' => 'admin_big_number_dashboard',
                'parent_id' => $parentId,
                'api_route' => 'admin_api_bigNumberDashboard',
                'sort' => 30,
            ],
            [
                'name' => '后台大数据趋势',
                'slug' => 'admin_big_number_trend',
                'parent_id' => $parentId,
                'api_route' => 'admin_api_bigNumberTrend',
                'sort' => 40,
            ],
        ];
    }

    /**
     * 写入或更新单个动作权限。
     *
     * @param array<string, mixed> $permission 权限配置。
     * @return void
     */
    private function upsertPermission(array $permission): void
    {
        $now = now()->format('Y-m-d H:i:s');

        DB::table('permissions')->updateOrInsert(
            ['slug' => $permission['slug']],
            [
                'name' => $permission['name'],
                'guard_type' => 'admin',
                'parent_id' => $permission['parent_id'],
                'type' => 3,
                'icon' => '',
                'sort' => $permission['sort'],
                'route' => '',
                'api_route' => $permission['api_route'],
                'status' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ]
        );
    }
}
