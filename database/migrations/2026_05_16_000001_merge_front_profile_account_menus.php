<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

class MergeFrontProfileAccountMenus extends Migration
{
    public function up()
    {
        DB::table('permissions')->where('slug', 'front_profile')->update([
            'name' => '个人中心',
            'route' => '/front/profile',
            'api_route' => 'front_api_profileInfo',
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
            'api_route' => 'front_api_accountInfo',
            'type' => 2,
            'icon' => 'layui-icon layui-icon-about',
            'status' => 1,
            'updated_at' => now(),
        ]);

        DB::table('permissions')->where('slug', 'front_account_balance')->update([
            'status' => 0,
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
