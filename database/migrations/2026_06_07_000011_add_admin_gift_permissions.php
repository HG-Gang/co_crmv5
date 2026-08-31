<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/07/07
 * Time: 01:10
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * 新增后台礼品发放/发货权限。
 *
 * 文件功能：
 * - 礼品后台页面包含发货记录、可发放地址和发放动作，页面入口和每个接口都必须来自 permissions 表配置。
 * - 页面权限通过 permissions.route=/admin/gifts 控制菜单可见性。
 * - API 权限通过 permissions.api_route 交给 check.permission:admin 做后端鉴权。
 * - 导出权限绑定真实 CSV 导出接口，避免前端只有按钮但没有可验证下载闭环。
 */
class AddAdminGiftPermissions extends Migration
{
    /**
     * 执行迁移：写入礼品页面、发货列表、地址列表、发放和导出权限。
     *
     * @return void
     */
    public function up()
    {
        $pageId = $this->upsertPermission([
            'name' => '礼品发放/发货',
            'slug' => 'admin_gifts',
            'parent_id' => 0,
            'type' => 1,
            'icon' => 'layui-icon-gift',
            'sort' => 390,
            'route' => '/admin/gifts',
            'api_route' => '',
        ]);

        foreach ([
            ['name' => '查看礼品发货列表', 'slug' => 'admin_gift_shipments', 'sort' => 10, 'api_route' => 'admin_api_giftShipmentList'],
            ['name' => '查看可发放地址', 'slug' => 'admin_gift_addresses', 'sort' => 20, 'api_route' => 'admin_api_giftAddressList'],
            ['name' => '发放礼品', 'slug' => 'admin_gift_send', 'sort' => 30, 'api_route' => 'admin_api_sendGift'],
            ['name' => '导出礼品发货列表', 'slug' => 'admin_gift_export', 'sort' => 40, 'api_route' => 'admin_api_exportGiftShipments'],
            ['name' => '更新礼品物流状态', 'slug' => 'admin_gift_update_shipment', 'sort' => 50, 'api_route' => 'admin_api_updateGiftShipment'],
        ] as $permission) {
            $this->upsertPermission([
                'name' => $permission['name'],
                'slug' => $permission['slug'],
                'parent_id' => $pageId,
                'type' => 3,
                'icon' => '',
                'sort' => $permission['sort'],
                'route' => '',
                'api_route' => $permission['api_route'],
            ]);
        }
    }

    /**
     * 回滚迁移：删除本迁移维护的礼品权限节点。
     *
     * @return void
     */
    public function down()
    {
        DB::table('permissions')->whereIn('slug', [
            'admin_gifts',
            'admin_gift_shipments',
            'admin_gift_addresses',
            'admin_gift_send',
            'admin_gift_export',
            'admin_gift_update_shipment',
        ])->delete();
    }

    /**
     * 写入或更新单条权限配置。
     *
     * 参数逻辑说明：
     * - slug：权限唯一标识，页面、按钮和角色授权都使用这个稳定 key。
     * - parent_id：父权限 ID，页面节点为 0，列表/地址/发放/导出动作挂在页面节点下。
     * - type：权限类型，1=菜单/页面，3=按钮/API 动作。
     * - route：Blade 页面路径，只给页面权限填写。
     * - api_route：Laravel 命名 API 路由，只给接口动作权限填写。
     *
     * @param array<string, mixed> $permission 权限配置数组。
     * @return int permissions.id，用于绑定子权限 parent_id。
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
}
