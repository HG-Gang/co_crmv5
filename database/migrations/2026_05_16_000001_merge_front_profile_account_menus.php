<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/02
 * Time: 01:31
 */

/**
 * 合并前台“我的资料/账户”相关菜单权限。
 *
 * 文件功能：
 * - 将分散的前台资料/账户菜单权限合并为统一入口，清理重复权限字典并保持角色绑定。
 *
 * 字段语义：
 * - 仅操作 permissions/role_permissions 字典数据，不涉及业务表结构；
 * - 回滚时恢复被合并的旧菜单权限（幂等处理）。
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

class MergeFrontProfileAccountMenus extends Migration
{
    public function up()
    {
        DB::table('permissions')->where('slug', 'front_profile')->update([
            'name' => '个人中心',
            'route' => '/front/profile',
            'api_route' => 'front_api_profile',
            'type' => 2,
            'icon' => 'layui-icon layui-icon-username',
            'status' => 1,
            'updated_at' => now(),
        ]);

        DB::table('permissions')
            ->whereIn('slug', ['front_profile_info', 'front_profile_edit', 'front_change_pwd', 'front_change_email'])
            ->update([
                'status' => 0,
                'updated_at' => now(),
            ]);

        DB::table('permissions')->where('slug', 'front_account')->update([
            'name' => '账户管理',
            'type' => 1,
            'route' => '',
            'api_route' => '',
            'icon' => 'layui-icon layui-icon-template-1',
            'status' => 1,
            'updated_at' => now(),
        ]);

        DB::table('permissions')->where('slug', 'front_account_info')->update([
            'name' => '账户综合',
            'route' => '/front/account/info',
            'api_route' => 'front_api_account_profile',
            'type' => 2,
            'icon' => 'layui-icon layui-icon-about',
            'status' => 1,
            'updated_at' => now(),
        ]);

        DB::table('permissions')->where('slug', 'front_account_balance')->update([
            'name' => '账户余额',
            'route' => '/front/account/balance',
            'api_route' => 'front_api_account_balance',
            'type' => 2,
            'icon' => 'layui-icon layui-icon-rmb',
            'status' => 1,
            'updated_at' => now(),
        ]);
    }

    public function down()
    {
        DB::table('permissions')->where('slug', 'front_profile')->update([
            'route' => '',
            'api_route' => '',
            'type' => 1,
            'status' => 1,
            'updated_at' => now(),
        ]);

        DB::table('permissions')
            ->whereIn('slug', ['front_profile_info', 'front_profile_edit', 'front_change_pwd', 'front_change_email'])
            ->update([
                'status' => 1,
                'updated_at' => now(),
            ]);

        DB::table('permissions')->where('slug', 'front_account_balance')->update([
            'status' => 1,
            'updated_at' => now(),
        ]);
    }
}
