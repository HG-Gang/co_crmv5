<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/06/09
 * Time: 01:27
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * 合并后台启用状态下重复的页面权限 route。
 *
 * 文件功能：
 * - 后台菜单页面要求一个 route 只对应一条启用权限，避免角色授权和菜单树出现重复节点。
 * - 历史测试或手工配置可能继续生成随机 slug 的重复页面权限，本迁移按 route 统一合并。
 * - 合并时保留最早的启用权限记录，把重复记录上的 role_permissions 授权迁移到保留记录。
 */
class MergeDuplicateAdminPermissionRoutes extends Migration
{
    /**
     * 执行迁移：扫描并合并所有后台启用重复 route。
     *
     * @return void
     */
    public function up()
    {
        $duplicateRoutes = DB::table('permissions')
            ->select('route')
            ->where('guard_type', 'admin')
            ->where('status', 1)
            ->whereNotNull('route')
            ->where('route', '<>', '')
            ->groupBy('route')
            ->havingRaw('COUNT(*) > 1')
            ->pluck('route')
            ->toArray();

        foreach ($duplicateRoutes as $route) {
            $this->mergeDuplicateAdminRoute($route);
        }
    }

    /**
     * 回滚迁移：不恢复重复权限。
     *
     * 逻辑边界：
     * - 本迁移属于真实 DB 数据清理，回滚时不应该重新制造重复菜单权限。
     * - 如需恢复某个权限，应通过后台权限管理页面重新创建唯一 route 的权限配置。
     *
     * @return void
     */
    public function down()
    {
        // 数据清理型迁移不执行反向恢复，避免再次生成重复菜单 route。
    }

    /**
     * 合并单个后台页面 route 的重复启用权限。
     *
     * 参数含义：
     * - $route：后台页面路径，例如 /admin/users。
     * - $keptPermission：该 route 下最早启用的权限记录，作为最终保留节点。
     * - $duplicateIds：需要禁用的重复权限 ID 列表。
     * - role_permissions：角色授权中间表，重复权限上的授权会迁移到保留权限。
     *
     * @param string $route 后台页面 route。
     * @return void
     */
    private function mergeDuplicateAdminRoute($route)
    {
        $permissions = DB::table('permissions')
            ->where('guard_type', 'admin')
            ->where('status', 1)
            ->where('route', $route)
            ->orderBy('id')
            ->get(['id']);

        if ($permissions->count() <= 1) {
            return;
        }

        $keptPermission = $permissions->first();
        $duplicateIds = $permissions->slice(1)->pluck('id')->map(function ($id) {
            return (int) $id;
        })->values()->all();

        $duplicateGrants = DB::table('role_permissions')
            ->whereIn('permission_id', $duplicateIds)
            ->get(['role_id']);

        foreach ($duplicateGrants as $grant) {
            DB::table('role_permissions')->updateOrInsert(
                [
                    'role_id' => (int) $grant->role_id,
                    'permission_id' => (int) $keptPermission->id,
                ],
                [
                    'created_at' => time(),
                    'updated_at' => time(),
                    'deleted_at' => null,
                ]
            );
        }

        DB::table('role_permissions')->whereIn('permission_id', $duplicateIds)->delete();

        DB::table('permissions')
            ->whereIn('id', $duplicateIds)
            ->update([
                'status' => 0,
                'deleted_at' => now()->format('Y-m-d H:i:s'),
                'updated_at' => now()->format('Y-m-d H:i:s'),
            ]);
    }
}
