<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/01
 * Time: 15:48
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * 对齐后台“用户管理”页面权限行。
 *
 * 文件功能：
 * - 全新数据库 migrate:fresh --seed 后，DatabaseSeeder 会写入旧的
 *   user_management(/users) 行；而现代后台页面路由是 /admin/users，
 *   对应权限字符串 admin_api_userList。
 * - 本迁移把遗留行升级为现代值；已存在现代行时只补全字段；
 *   存在重复启用行时停用非规范行，保证页面权限唯一。
 *
 * 入参例子：
 * - 无入参，迁移执行时自动对齐。
 *
 * 返回值：
 * - up 执行后权限表必然存在唯一一条
 *   route=/admin/users、api_route=admin_api_userList 的启用行。
 * - down 不回滚数据字典（权限行属于业务数据，不做破坏性回滚）。
 */
class EnsureAdminUsersPagePermission extends Migration
{
    /**
     * 对齐后台用户管理权限行。
     *
     * @return void 无返回值；执行后权限表满足页面权限唯一性约束。
     */
    public function up()
    {
        if (!Schema::hasTable('permissions')) {
            return;
        }

        $now = date('Y-m-d H:i:s');
        $values = [
            'name' => '用户管理',
            'slug' => 'admin_users_6a23fb27413fd',
            'guard_type' => 'admin',
            'parent_id' => 0,
            'type' => 1,
            'icon' => null,
            'sort' => 1,
            'route' => '/admin/users',
            'api_route' => 'admin_api_userList',
            'status' => 1,
            'updated_at' => $now,
        ];

        // 优先升级历史遗留行（slug=user_management），保证旧库与新库行为一致。
        $legacy = DB::table('permissions')
            ->where('guard_type', 'admin')
            ->where('slug', 'user_management')
            ->first();
        if ($legacy) {
            DB::table('permissions')->where('id', $legacy->id)->update($values);
            $canonicalId = (int) $legacy->id;
        } else {
            // 已存在现代行时只补全字段，避免重复插入。
            $modern = DB::table('permissions')
                ->where('guard_type', 'admin')
                ->where('route', '/admin/users')
                ->where('api_route', 'admin_api_userList')
                ->first();
            if ($modern) {
                DB::table('permissions')->where('id', $modern->id)->update($values);
                $canonicalId = (int) $modern->id;
            } else {
                $canonicalId = (int) DB::table('permissions')->insertGetId(
                    array_merge($values, ['created_at' => $now])
                );
            }
        }

        // 停用其它同路由的启用行，保证页面权限唯一。
        DB::table('permissions')
            ->where('guard_type', 'admin')
            ->where('route', '/admin/users')
            ->where('id', '<>', $canonicalId)
            ->where('status', 1)
            ->update(['status' => 0, 'updated_at' => $now]);
    }

    /**
     * 回滚策略：权限行属于数据字典，不回滚。
     *
     * @return void 无返回值。
     */
    public function down()
    {
        // 数据字典不做破坏性回滚；如需回退请手工调整 permissions 表。
    }
}
