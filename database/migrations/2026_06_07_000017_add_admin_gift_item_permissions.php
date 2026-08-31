<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/07/07
 * Time: 01:51
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * 补齐后台礼品配置模块权限。
 *
 * 文件功能：
 * - 幂等写入 admin_gifts 页面菜单及其查看/新增/更新/删除四个按钮 API 权限（permissions 表），
 *   供 check.permission:admin 按接口二次鉴权；回滚只删除按钮权限行，保留页面菜单。
 */
class AddAdminGiftItemPermissions extends Migration
{
    public function up()
    {
        $now = now()->format('Y-m-d H:i:s');
        $pageId = (int) DB::table('permissions')->where('slug', 'admin_gifts')->value('id');

        if ($pageId <= 0) {
            DB::table('permissions')->updateOrInsert(
                ['slug' => 'admin_gifts'],
                [
                    'name' => '礼品发放/发货',
                    'guard_type' => 'admin',
                    'parent_id' => 0,
                    'type' => 1,
                    'icon' => 'layui-icon-gift',
                    'sort' => 390,
                    'route' => '/admin/gifts',
                    'api_route' => '',
                    'status' => 1,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]
            );
            $pageId = (int) DB::table('permissions')->where('slug', 'admin_gifts')->value('id');
        }

        foreach ([
            ['name' => '查看礼品配置', 'slug' => 'admin_gift_items', 'sort' => 60, 'api_route' => 'admin_api_giftItemList'],
            ['name' => '新增礼品配置', 'slug' => 'admin_gift_item_create', 'sort' => 70, 'api_route' => 'admin_api_createGiftItem'],
            ['name' => '更新礼品配置', 'slug' => 'admin_gift_item_update', 'sort' => 80, 'api_route' => 'admin_api_updateGiftItem'],
            ['name' => '删除礼品配置', 'slug' => 'admin_gift_item_delete', 'sort' => 90, 'api_route' => 'admin_api_deleteGiftItem'],
        ] as $permission) {
            DB::table('permissions')->updateOrInsert(
                ['slug' => $permission['slug']],
                [
                    'name' => $permission['name'],
                    'guard_type' => 'admin',
                    'parent_id' => $pageId,
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

    public function down()
    {
        DB::table('permissions')->whereIn('slug', [
            'admin_gift_items',
            'admin_gift_item_create',
            'admin_gift_item_update',
            'admin_gift_item_delete',
        ])->delete();
    }
}
