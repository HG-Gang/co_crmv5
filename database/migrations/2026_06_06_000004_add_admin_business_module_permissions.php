<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/01
 * Time: 03:47
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * 新增后台业务模块菜单与接口权限。
 *
 * 文件功能：
 * - permissions 表是后台菜单、页面、按钮和接口鉴权的统一配置来源。
 * - 本迁移把第一批 Blade 后台业务页面及其列表/处理接口写入 permissions 表。
 * - 普通管理员访问接口时仍必须通过 role_permissions 授权；本迁移只负责补齐“可授权的权限字典”。
 */
class AddAdminBusinessModulePermissions extends Migration
{
    /**
     * 执行迁移：写入页面菜单节点和对应 API/按钮权限节点。
     *
     * @return void
     */
    public function up()
    {
        foreach ($this->modules() as $moduleIndex => $module) {
            $pageId = $this->upsertPermission([
                'name' => $module['name'],
                'slug' => $module['slug'],
                'parent_id' => 0,
                'type' => 1,
                'icon' => $module['icon'],
                'sort' => ($moduleIndex + 1) * 10,
                'route' => $module['route'],
                'api_route' => '',
            ]);

            foreach ($module['actions'] as $actionIndex => $action) {
                $this->upsertPermission([
                    'name' => $action['name'],
                    'slug' => $action['slug'],
                    'parent_id' => $pageId,
                    'type' => 3,
                    'icon' => '',
                    'sort' => ($actionIndex + 1) * 10,
                    'route' => '',
                    'api_route' => $action['api_route'],
                ]);
            }
        }
    }

    /**
     * 回滚迁移：删除本迁移写入的页面和 API 权限节点。
     *
     * @return void
     */
    public function down()
    {
        DB::table('permissions')->whereIn('slug', $this->allSlugs())->delete();
    }

    /**
     * 写入或更新单条权限配置，并返回该权限的主键 ID。
     *
     * 参数说明：
     * - name：权限显示名称，用于权限管理页展示。
     * - slug：权限稳定标识，是前端按钮控制和后端角色授权的共同 key。
     * - parent_id：父权限 ID，页面节点为 0，按钮/API 节点挂到所属页面下面。
     * - type：权限类型，1=菜单/页面，3=按钮/API。
     * - route：Blade 页面路径，仅页面节点配置。
     * - api_route：Laravel API 路由名称，仅接口节点配置。
     *
     * @param array<string, mixed> $permission 权限配置数组。
     * @return int permissions.id，用于后续子节点 parent_id 绑定。
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
                'updated_at' => $now,
                'created_at' => $now,
            ]
        );

        return (int) DB::table('permissions')->where('slug', $permission['slug'])->value('id');
    }

    /**
     * 第一批后台业务模块权限配置。
     *
     * 配置说明：
     * - slug 为页面级权限，route 指向对应 Blade 页面。
     * - actions 中每一项为按钮/API 权限，api_route 必须与 routes/admin.php 的命名路由一致。
     *
     * @return array<int, array<string, mixed>>
     */
    private function modules()
    {
        return [
            [
                'name' => '代理管理',
                'slug' => 'admin_agents',
                'route' => '/admin/agents',
                'icon' => 'layui-icon-user',
                'actions' => [
                    ['name' => '查看代理列表', 'slug' => 'admin_agent_list', 'api_route' => 'admin_api_agentList'],
                    ['name' => '查看代理下级', 'slug' => 'admin_agent_descendants', 'api_route' => 'admin_api_agentDescendants'],
                    ['name' => '查看代理上级链路', 'slug' => 'admin_agent_parent_path', 'api_route' => 'admin_api_agentParentPath'],
                ],
            ],
            [
                'name' => '入金管理',
                'slug' => 'admin_deposits',
                'route' => '/admin/deposits',
                'icon' => 'layui-icon-rmb',
                'actions' => [
                    ['name' => '查看入金列表', 'slug' => 'admin_deposit_list', 'api_route' => 'admin_api_depositList'],
                    ['name' => '查看入金流水', 'slug' => 'admin_deposit_flow_list', 'api_route' => 'admin_api_depositFlowList'],
                    ['name' => '导出入金流水', 'slug' => 'admin_deposit_flow_export', 'api_route' => 'admin_api_exportDepositFlows'],
                    ['name' => '审核通过入金', 'slug' => 'admin_deposit_approve', 'api_route' => 'admin_api_depositApprove'],
                    ['name' => '拒绝入金', 'slug' => 'admin_deposit_reject', 'api_route' => 'admin_api_depositReject'],
                ],
            ],
            [
                'name' => '出金管理',
                'slug' => 'admin_withdrawals',
                'route' => '/admin/withdrawals',
                'icon' => 'layui-icon-transfer',
                'actions' => [
                    ['name' => '查看出金列表', 'slug' => 'admin_withdraw_list', 'api_route' => 'admin_api_withdrawList'],
                    ['name' => '标记出金处理中', 'slug' => 'admin_withdraw_process', 'api_route' => 'admin_api_withdrawProcess'],
                    ['name' => '完成出金', 'slug' => 'admin_withdraw_complete', 'api_route' => 'admin_api_withdrawComplete'],
                    ['name' => '拒绝出金', 'slug' => 'admin_withdraw_reject', 'api_route' => 'admin_api_withdrawReject'],
                ],
            ],
            [
                'name' => '返佣管理',
                'slug' => 'admin_commissions',
                'route' => '/admin/commissions',
                'icon' => 'layui-icon-chart-screen',
                'actions' => [
                    ['name' => '查看返佣列表', 'slug' => 'admin_commission_list', 'api_route' => 'admin_api_commissionList'],
                    ['name' => '结算返佣', 'slug' => 'admin_commission_settle', 'api_route' => 'admin_api_commissionSettle'],
                ],
            ],
            [
                'name' => '代理等级',
                'slug' => 'admin_agent_levels',
                'route' => '/admin/agent-levels',
                'icon' => 'layui-icon-template-1',
                'actions' => [
                    ['name' => '查看代理等级', 'slug' => 'admin_agent_level_list', 'api_route' => 'admin_api_agentLevelList'],
                ],
            ],
            [
                'name' => '组别配置',
                'slug' => 'admin_group_configs',
                'route' => '/admin/group-configs',
                'icon' => 'layui-icon-tabs',
                'actions' => [
                    ['name' => '查看组别配置', 'slug' => 'admin_group_config_list', 'api_route' => 'admin_api_groupConfigList'],
                ],
            ],
            [
                'name' => '系统配置',
                'slug' => 'admin_system_configs',
                'route' => '/admin/system-configs',
                'icon' => 'layui-icon-set',
                'actions' => [
                    ['name' => '查看系统配置', 'slug' => 'admin_system_config_list', 'api_route' => 'admin_api_systemConfigList'],
                ],
            ],
            [
                'name' => '支付通道',
                'slug' => 'admin_channels',
                'route' => '/admin/channels',
                'icon' => 'layui-icon-dollar',
                'actions' => [
                    ['name' => '查看支付通道', 'slug' => 'admin_channel_list', 'api_route' => 'admin_api_channelList'],
                ],
            ],
            [
                'name' => '管理员账号',
                'slug' => 'admin_admins',
                'route' => '/admin/admins',
                'icon' => 'layui-icon-username',
                'actions' => [
                    ['name' => '查看管理员列表', 'slug' => 'admin_admin_list', 'api_route' => 'admin_api_adminList'],
                ],
            ],
            [
                'name' => '新闻公告',
                'slug' => 'admin_news',
                'route' => '/admin/news',
                'icon' => 'layui-icon-notice',
                'actions' => [
                    ['name' => '查看新闻列表', 'slug' => 'admin_news_list', 'api_route' => 'admin_api_newsList'],
                ],
            ],
        ];
    }

    /**
     * 返回本迁移管理的所有权限 slug。
     *
     * @return array<int, string> 页面和 API 权限 slug 列表。
     */
    private function allSlugs()
    {
        $slugs = [];
        foreach ($this->modules() as $module) {
            $slugs[] = $module['slug'];
            foreach ($module['actions'] as $action) {
                $slugs[] = $action['slug'];
            }
        }

        return $slugs;
    }
}
