<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/07
 * Time: 17:00
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * 为实名认证详情 API 补充独立后台权限。
 *
 * 文件功能：
 *
 * 详情包含身份证、银行卡和联系方式，不能复用普通列表权限；旧详情入口和现代
 * `admin_api_authDetail` 都必须由同一个 permissions.api_route 权限点保护。
 */
class AddAdminAuthenticationDetailPermission extends Migration
{
    public function up()
    {
        $parentId = (int) DB::table('permissions')
            ->where('guard_type', 'admin')
            ->where('slug', 'admin_authentications')
            ->value('id');

        if ($parentId < 1) {
            throw new RuntimeException('Authentication permission parent is missing.');
        }

        $now = now()->format('Y-m-d H:i:s');
        DB::table('permissions')->updateOrInsert(
            ['slug' => 'admin_auth_detail'],
            [
                'name' => '查看认证详情',
                'guard_type' => 'admin',
                'parent_id' => $parentId,
                'type' => 3,
                'icon' => '',
                'sort' => 25,
                'route' => '',
                'api_route' => 'admin_api_authDetail',
                'status' => 1,
                'deleted_at' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ]
        );
    }

    public function down()
    {
        $now = now()->format('Y-m-d H:i:s');
        DB::table('permissions')
            ->where('slug', 'admin_auth_detail')
            ->update([
                'status' => 0,
                'deleted_at' => $now,
                'updated_at' => $now,
            ]);
    }
}
