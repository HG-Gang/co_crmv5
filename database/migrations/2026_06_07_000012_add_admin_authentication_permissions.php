<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/06/07
 * Time: 17:33
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * 新增后台实名认证审核权限。
 *
 * 文件功能：
 * - 认证审核页面、待审列表、已审列表和审核动作都必须来自 permissions 表配置。
 * - 页面权限通过 `permissions.route=/admin/authentications` 控制菜单可见性。
 * - API 权限通过 `permissions.api_route` 交给 `check.permission:admin` 做后端鉴权。
 * - 本迁移只维护权限字典，不直接写入 role_permissions；具体角色能否访问仍由后台角色授权界面配置。
 */
class AddAdminAuthenticationPermissions extends Migration
{
    /**
     * 执行迁移：写入认证审核页面和接口权限。
     *
     * @return void
     */
    public function up()
    {
        $pageId = $this->upsertPermission([
            'name' => '实名认证审核',
            'slug' => 'admin_authentications',
            'parent_id' => 0,
            'type' => 1,
            'icon' => 'layui-icon-auz',
            'sort' => 175,
            'route' => '/admin/authentications',
            'api_route' => '',
        ]);

        foreach ([
            ['name' => '查看待审核认证', 'slug' => 'admin_auth_pending_list', 'sort' => 10, 'api_route' => 'admin_api_authPendingList'],
            ['name' => '查看已审核认证', 'slug' => 'admin_auth_certified_list', 'sort' => 20, 'api_route' => 'admin_api_authCertifiedList'],
            ['name' => '执行认证审核', 'slug' => 'admin_user_review_auth', 'sort' => 30, 'api_route' => 'admin_api_reviewAuth'],
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
     * 回滚迁移：删除本迁移维护的认证审核权限。
     *
     * @return void
     */
    public function down()
    {
        DB::table('permissions')->whereIn('slug', [
            'admin_authentications',
            'admin_auth_pending_list',
            'admin_auth_certified_list',
            'admin_user_review_auth',
        ])->delete();
    }

    /**
     * 写入或更新单条权限配置。
     *
     * 参数逻辑说明：
     * - slug：权限唯一标识，Blade `data-permission`、角色授权和测试都依赖该值。
     * - parent_id：父权限 ID，页面节点为 0，列表与审核动作挂在页面节点下。
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
