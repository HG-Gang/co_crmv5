<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/07/29
 * Time: 01:48
 */

/**
 * AdminFundFlowPermissionMigrationTest
 *
 * 文件功能：
 * - 验证出金流水与未入金流水的权限 slug、guard_type 和 api_route 由迁移类完整写入 permissions 表。
 * - 输入：权限/结构迁移类与测试数据库；输出：PHPUnit 断言结果。
 * - 明确不负责：不验证 check.permission 中间件的运行时鉴权与按钮渲染（由模块契约测试锁定）。
 */

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * 后台资金流水权限迁移测试。
 *
 * 功能逻辑说明：
 * - 出金流水和未入金流水属于财务核对数据，必须由 `permissions` 数据表配置驱动。
 * - 页面入口使用 `permissions.route` 控制菜单或页面可见性，接口入口使用 `permissions.api_route` 做后端鉴权。
 * - 本测试直接执行迁移类，验证权限 slug、guard_type 和 api_route 是否完整写入。
 */
class AdminFundFlowPermissionMigrationTest extends TestCase
{
    /**
     * 出金流水和未入金流水页面/API 权限必须写入权限表。
     *
     * @return void
     */
    public function test_fund_flow_permissions_are_seeded_by_migration(): void
    {
        // $migrationPath：本轮资金流水权限迁移文件路径，确保权限来源可重复执行。
        $migrationPath = database_path('migrations/2026_06_07_000006_add_admin_fund_flow_permissions.php');

        $this->assertFileExists($migrationPath);

        require_once $migrationPath;
        (new \AddAdminFundFlowPermissions())->up();

        // $expectedPermissions：必须落到 permissions 表的权限 slug 与 API 路由映射。
        $expectedPermissions = [
            'admin_deposit_flows' => '',
            'admin_deposit_flow_list' => 'admin_api_depositFlowList',
            'admin_deposit_flow_export' => 'admin_api_exportDepositFlows',
            'admin_withdraw_flows' => '',
            'admin_withdraw_flow_list' => 'admin_api_withdrawFlowList',
            'admin_withdraw_flow_export' => 'admin_api_exportWithdrawFlows',
            'admin_undeposit_flows' => '',
            'admin_undeposit_flow_list' => 'admin_api_undepositFlowList',
            'admin_undeposit_flow_export' => 'admin_api_exportUndepositFlows',
            'admin_never_deposit_user_list' => 'admin_api_neverDepositUserList',
        ];

        foreach ($expectedPermissions as $slug => $apiRoute) {
            $permission = DB::table('permissions')->where('slug', $slug)->first();

            $this->assertNotNull($permission, $slug . ' 权限未写入 permissions 表');
            $this->assertSame('admin', $permission->guard_type);
            $this->assertSame($apiRoute, $permission->api_route);
        }
    }
}
