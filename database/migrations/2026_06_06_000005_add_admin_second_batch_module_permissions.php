<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/07/25
 * Time: 19:08
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * 新增后台第二批业务模块菜单与接口权限。
 *
 * 文件功能：
 * - permissions 表继续作为后台菜单、页面、按钮和接口鉴权的唯一权限字典来源。
 * - 本迁移覆盖凭证审核、风控、黑名单、注销申请、交易订单和大代理模块。
 * - 角色是否真正拥有这些权限，仍由 role_permissions 中间表配置决定。
 */
class AddAdminSecondBatchModulePermissions extends Migration
{
    /**
     * 执行迁移：写入页面权限和 API/按钮权限。
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
                'sort' => 200 + (($moduleIndex + 1) * 10),
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
     * 回滚迁移：删除本迁移维护的权限节点。
     *
     * @return void
     */
    public function down()
    {
        DB::table('permissions')->whereIn('slug', $this->allSlugs())->delete();
    }

    /**
     * 写入或更新单条权限配置并返回主键 ID。
     *
     * 参数说明：
     * - slug：稳定权限标识，前端按钮控制和后端角色授权共同使用。
     * - type：1=页面/菜单，3=按钮/API。
     * - route：页面访问路径，仅页面节点填写。
     * - api_route：后台 API 命名路由，仅动作节点填写。
     *
     * @param array<string, mixed> $permission 权限配置。
     * @return int permissions.id。
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
     * 第二批后台模块权限配置。
     *
     * @return array<int, array<string, mixed>>
     */
    private function modules()
    {
        return [
            [
                'name' => '凭证审核',
                'slug' => 'admin_vouchers',
                'route' => '/admin/vouchers',
                'icon' => 'layui-icon-form',
                'actions' => [
                    ['name' => '查看凭证列表', 'slug' => 'admin_voucher_list', 'api_route' => 'admin_api_voucherList'],
                    ['name' => '通过凭证审核', 'slug' => 'admin_voucher_approve', 'api_route' => 'admin_api_voucherApprove'],
                    ['name' => '拒绝凭证审核', 'slug' => 'admin_voucher_reject', 'api_route' => 'admin_api_voucherReject'],
                ],
            ],
            [
                'name' => '风控管理',
                'slug' => 'admin_risk',
                'route' => '/admin/risk',
                'icon' => 'layui-icon-auz',
                'actions' => [
                    ['name' => '查看风险持仓', 'slug' => 'admin_risk_positions', 'api_route' => 'admin_api_riskPositions'],
                    ['name' => '查看追保预警', 'slug' => 'admin_risk_margin_calls', 'api_route' => 'admin_api_riskMarginCalls'],
                    ['name' => '查看异常IP', 'slug' => 'admin_risk_ip_list', 'api_route' => 'admin_api_riskIpList'],
                    ['name' => '查看异常IP详情', 'slug' => 'admin_risk_ip_detail', 'api_route' => 'admin_api_riskIpDetail'],
                    ['name' => '发送强平信号', 'slug' => 'admin_risk_force_close', 'api_route' => 'admin_api_riskForceClose'],
                ],
            ],
            [
                'name' => '黑名单',
                'slug' => 'admin_blacklist',
                'route' => '/admin/blacklist',
                'icon' => 'layui-icon-close-fill',
                'actions' => [
                    ['name' => '查看黑名单', 'slug' => 'admin_blacklist_list', 'api_route' => 'admin_api_blacklistList'],
                    ['name' => '新增黑名单', 'slug' => 'admin_blacklist_create', 'api_route' => 'admin_api_createBlacklist'],
                    ['name' => '更新黑名单', 'slug' => 'admin_blacklist_update', 'api_route' => 'admin_api_updateBlacklist'],
                    ['name' => '删除黑名单', 'slug' => 'admin_blacklist_delete', 'api_route' => 'admin_api_deleteBlacklist'],
                ],
            ],
            [
                'name' => '注销申请',
                'slug' => 'admin_cancel_applies',
                'route' => '/admin/cancel-applies',
                'icon' => 'layui-icon-delete',
                'actions' => [
                    ['name' => '查看注销申请', 'slug' => 'admin_cancel_apply_list', 'api_route' => 'admin_api_cancelApplyList'],
                    ['name' => '通过注销申请', 'slug' => 'admin_cancel_apply_approve', 'api_route' => 'admin_api_cancelApplyApprove'],
                    ['name' => '拒绝注销申请', 'slug' => 'admin_cancel_apply_reject', 'api_route' => 'admin_api_cancelApplyReject'],
                ],
            ],
            [
                'name' => '交易订单',
                'slug' => 'admin_trades',
                'route' => '/admin/trades',
                'icon' => 'layui-icon-chart',
                'actions' => [
                    ['name' => '查看交易列表', 'slug' => 'admin_trade_list', 'api_route' => 'admin_api_tradeList'],
                    ['name' => '查看当前持仓', 'slug' => 'admin_open_positions', 'api_route' => 'admin_api_openPositions'],
                    ['name' => '查看历史平仓', 'slug' => 'admin_closed_positions', 'api_route' => 'admin_api_closedPositions'],
                    ['name' => '导出历史平仓', 'slug' => 'admin_closed_positions_export', 'api_route' => 'admin_api_exportClosedPositions'],
                    ['name' => '查看交易汇总', 'slug' => 'admin_trade_summary', 'api_route' => 'admin_api_tradeSummary'],
                ],
            ],
            [
                'name' => '大代理',
                'slug' => 'admin_big_agents',
                'route' => '/admin/big-agents',
                'icon' => 'layui-icon-group',
                'actions' => [
                    ['name' => '查看大代理', 'slug' => 'admin_big_agent_list', 'api_route' => 'admin_api_bigAgentList'],
                    ['name' => '新增大代理', 'slug' => 'admin_big_agent_create', 'api_route' => 'admin_api_createBigAgent'],
                    ['name' => '更新大代理', 'slug' => 'admin_big_agent_update', 'api_route' => 'admin_api_updateBigAgent'],
                    ['name' => '删除大代理', 'slug' => 'admin_big_agent_delete', 'api_route' => 'admin_api_deleteBigAgent'],
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
        foreach ($this->modules() as $module) {
            $slugs[] = $module['slug'];
            foreach ($module['actions'] as $action) {
                $slugs[] = $action['slug'];
            }
        }

        return $slugs;
    }
}
