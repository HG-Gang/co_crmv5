<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/07/29
 * Time: 01:47
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * 新增后台资金流水权限配置。
 *
 * 文件功能：
 * - 出金流水与未入金流水属于后台财务核对页面，页面入口和 API 都必须来自 permissions 表配置。
 * - 页面节点使用 permissions.route 控制菜单可见性，动作节点使用 permissions.api_route 交给 check.permission:admin 鉴权。
 * - 本迁移只维护权限字典，不直接给任何角色授权；具体角色是否拥有权限仍由 role_permissions 表配置决定。
 */
class AddAdminFundFlowPermissions extends Migration
{
    /**
     * 执行迁移：写入资金流水页面和列表 API 权限。
     *
     * @return void
     */
    public function up()
    {
        foreach ($this->pages() as $pageIndex => $page) {
            // $pageId：页面权限写入后的 permissions.id，用于作为列表动作权限的 parent_id。
            $pageId = $this->upsertPermission([
                'name' => $page['name'],
                'slug' => $page['slug'],
                'parent_id' => 0,
                'type' => 1,
                'icon' => $page['icon'],
                'sort' => 410 + ($pageIndex * 10),
                'route' => $page['route'],
                'api_route' => '',
            ]);

            $this->upsertPermission([
                'name' => $page['action_name'],
                'slug' => $page['action_slug'],
                'parent_id' => $pageId,
                'type' => 3,
                'icon' => '',
                'sort' => 10,
                'route' => '',
                'api_route' => $page['api_route'],
            ]);

            foreach ($page['extra_actions'] ?? [] as $extraAction) {
                $this->upsertPermission([
                    'name' => $extraAction['name'],
                    'slug' => $extraAction['slug'],
                    'parent_id' => $pageId,
                    'type' => 3,
                    'icon' => '',
                    'sort' => $extraAction['sort'],
                    'route' => '',
                    'api_route' => $extraAction['api_route'],
                ]);
            }
        }
    }

    /**
     * 回滚迁移：删除本迁移维护的权限节点。
     *
     * @return void
     */
    public function down()
    {
        DB::table('permissions')->whereIn('slug', $this->allSlugs())->delete();
    }

    /**
     * 写入或更新单条权限配置。
     *
     * 参数说明：
     * - slug：权限唯一标识，供菜单、按钮、角色授权共同使用。
     * - parent_id：父级权限 ID，页面节点为 0，动作节点绑定到对应页面节点。
     * - type：权限类型，1=页面/菜单，3=按钮/API 动作。
     * - route：Blade 页面访问路径，仅页面节点填写。
     * - api_route：Laravel 后台 API 命名路由，仅动作节点填写。
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

    /**
     * 返回资金流水页面与 API 权限配置。
     *
     * @return array<int, array<string, string>>
     */
    private function pages()
    {
        return [
            [
                'name' => '入金流水',
                'slug' => 'admin_deposit_flows',
                'route' => '/admin/deposit-flows',
                'icon' => 'layui-icon-list',
                'action_name' => '查看入金流水',
                'action_slug' => 'admin_deposit_flow_list',
                'api_route' => 'admin_api_depositFlowList',
                'extra_actions' => [
                    [
                        'name' => '导出入金流水',
                        'slug' => 'admin_deposit_flow_export',
                        'sort' => 20,
                        'api_route' => 'admin_api_exportDepositFlows',
                    ],
                ],
            ],
            [
                'name' => '出金流水',
                'slug' => 'admin_withdraw_flows',
                'route' => '/admin/withdraw-flows',
                'icon' => 'layui-icon-list',
                'action_name' => '查看出金流水',
                'action_slug' => 'admin_withdraw_flow_list',
                'api_route' => 'admin_api_withdrawFlowList',
                'extra_actions' => [
                    [
                        'name' => '导出出金流水',
                        'slug' => 'admin_withdraw_flow_export',
                        'sort' => 20,
                        'api_route' => 'admin_api_exportWithdrawFlows',
                    ],
                ],
            ],
            [
                'name' => '未入金流水',
                'slug' => 'admin_undeposit_flows',
                'route' => '/admin/undeposit-flows',
                'icon' => 'layui-icon-list',
                'action_name' => '查看未入金流水',
                'action_slug' => 'admin_undeposit_flow_list',
                'api_route' => 'admin_api_undepositFlowList',
                'extra_actions' => [
                    [
                        'name' => '查看从未入金用户',
                        'slug' => 'admin_never_deposit_user_list',
                        'sort' => 30,
                        'api_route' => 'admin_api_neverDepositUserList',
                    ],
                    [
                        'name' => '导出未入金流水',
                        'slug' => 'admin_undeposit_flow_export',
                        'sort' => 20,
                        'api_route' => 'admin_api_exportUndepositFlows',
                    ],
                ],
            ],
        ];
    }

    /**
     * 返回本迁移维护的全部权限 slug。
     *
     * @return array<int, string>
     */
    private function allSlugs()
    {
        $slugs = [];

        foreach ($this->pages() as $page) {
            $slugs[] = $page['slug'];
            $slugs[] = $page['action_slug'];

            foreach ($page['extra_actions'] ?? [] as $extraAction) {
                $slugs[] = $extraAction['slug'];
            }
        }

        return $slugs;
    }
}
