<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/28
 * Time: 00:45
 */

/**
 * 新增后台「客户资料统计」与「实时返佣统计图表」两个接口的权限字典。
 *
 * 文件功能：
 * - admin_api_customerStatistics 支撑「点击用户名查看用户信息」详情页的出入金、返佣、开关订单数与近 7/15/30 天盈亏图表。
 * - admin_api_realtimeCommissionStatistics 支撑实时返佣统计模块的折叠图表容器。
 * - 两个接口都挂载 check.permission:admin，因此必须在 permissions 表里有启用的 api_route 行，
 *   否则 AdminProtectedRoutePermissionClosureModuleTest 的受保护路由闭环会失败。
 * - 本迁移只维护权限字典，不写 role_permissions；具体角色能否访问仍由后台角色授权界面配置。
 */

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

class AddAdminCustomerStatisticsAndRebateChartPermissions extends Migration
{
    /**
     * 本迁移维护的权限节点。
     *
     * 字段说明：
     * - slug：权限唯一标识。
     * - api_route：Laravel API 命名路由，交给 check.permission:admin 做后端鉴权。
     * - parent：父级权限 slug；挂到对应业务页面节点下，便于后台授权界面按模块勾选。
     *
     * @return array<int, array{name:string, slug:string, api_route:string, parent:string, sort:int}>
     */
    private function permissions(): array
    {
        return [
            [
                'name' => '查看客户资料统计',
                'slug' => 'admin_customer_statistics',
                'api_route' => 'admin_api_customerStatistics',
                'parent' => 'admin_users_6a23fb27413fd',
                'sort' => 60,
            ],
            [
                'name' => '查看实时返佣统计图表',
                'slug' => 'admin_realtime_commission_statistics',
                'api_route' => 'admin_api_realtimeCommissionStatistics',
                'parent' => 'admin_realtime_commissions',
                'sort' => 30,
            ],
        ];
    }

    /**
     * 执行迁移：写入两个统计接口权限。
     *
     * @return void
     */
    public function up(): void
    {
        $now = now()->format('Y-m-d H:i:s');

        foreach ($this->permissions() as $permission) {
            DB::table('permissions')->updateOrInsert(
                ['slug' => $permission['slug']],
                [
                    'name' => $permission['name'],
                    'guard_type' => 'admin',
                    'parent_id' => $this->parentId($permission['parent']),
                    'type' => 3,
                    'icon' => '',
                    'sort' => $permission['sort'],
                    'route' => '',
                    'api_route' => $permission['api_route'],
                    'status' => 1,
                    'deleted_at' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]
            );
        }
    }

    /**
     * 回滚迁移：软停用本迁移维护的权限节点。
     *
     * 逻辑说明：
     * - 与 2026_07_19_000001_ensure_protected_admin_route_permissions 一致采用软停用，
     *   保留 permissions.id，避免 role_permissions 里已授权的引用直接失效。
     *
     * @return void
     */
    public function down(): void
    {
        $now = now()->format('Y-m-d H:i:s');

        DB::table('permissions')
            ->whereIn('slug', array_column($this->permissions(), 'slug'))
            ->update([
                'status' => 0,
                'deleted_at' => $now,
                'updated_at' => $now,
            ]);
    }

    /**
     * 解析父级权限 ID。
     *
     * @param string $slug 父级权限 slug；解析不到时返回 0（挂到根级）。
     * @return int permissions.id 或 0。
     */
    private function parentId(string $slug): int
    {
        if ($slug === '') {
            return 0;
        }

        return (int) DB::table('permissions')
            ->where('guard_type', 'admin')
            ->where('slug', $slug)
            ->value('id');
    }
}
