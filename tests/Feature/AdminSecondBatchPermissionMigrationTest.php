<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/18
 * Time: 23:04
 */

/**
 * AdminSecondBatchPermissionMigrationTest
 *
 * 文件功能：
 * - 验证第二批模块页面与 API 权限期望值由迁移写入，盈亏风控权限迁移可回滚且不自动授予角色。
 * - 输入：权限/结构迁移类与测试数据库；输出：PHPUnit 断言结果。
 * - 明确不负责：不验证 check.permission 中间件的运行时鉴权与按钮渲染（由模块契约测试锁定）。
 */

namespace Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * 后台第二批模块权限迁移测试。
 *
 * 测试目标：
 * - 第二批 Blade 页面和新增 API 路由必须写入 permissions 表。
 * - 普通管理员的授权关系继续通过 role_permissions 配置，本迁移只补权限字典。
 */
class AdminSecondBatchPermissionMigrationTest extends TestCase
{
    use DatabaseTransactions;

    /**
     * 第二批权限迁移必须写入页面权限和 API 权限。
     *
     * @return void
     */
    public function test_second_batch_permissions_are_seeded_by_migration(): void
    {
        $migrationPath = database_path('migrations/2026_06_06_000005_add_admin_second_batch_module_permissions.php');

        $this->assertFileExists($migrationPath, '后台第二批业务模块权限迁移文件不存在。');

        require_once $migrationPath;

        $slugs = collect($this->expectedPermissions())->pluck('slug')->all();
        DB::table('permissions')->whereIn('slug', $slugs)->delete();

        (new \AddAdminSecondBatchModulePermissions())->up();

        foreach ($this->expectedPermissions() as $permission) {
            $record = DB::table('permissions')->where('slug', $permission['slug'])->first();

            $this->assertNotNull($record, $permission['slug'] . ' 权限未写入 permissions 表。');
            $this->assertSame('admin', $record->guard_type);
            $this->assertSame($permission['type'], (int) $record->type);
            $this->assertSame($permission['route'], (string) $record->route);
            $this->assertSame($permission['api_route'], (string) $record->api_route);
            $this->assertSame(1, (int) $record->status);
        }
    }

    public function test_profit_risk_permission_migration_is_scoped_reversible_and_does_not_grant_roles(): void
    {
        $migrationPath = database_path('migrations/2026_08_18_000001_add_admin_risk_profit_permission.php');
        $this->assertFileExists($migrationPath, '盈利风险只读权限迁移文件不存在。');
        if (!is_file($migrationPath)) {
            return;
        }

        require_once $migrationPath;

        $parent = DB::table('permissions')->where('slug', 'admin_risk')->first();
        $this->assertNotNull($parent, 'admin_risk 父权限不存在。');
        DB::table('permissions')->where('slug', 'admin_risk_profit_users')->delete();

        $migration = new \AddAdminRiskProfitPermission();
        $migration->up();

        $record = DB::table('permissions')->where('slug', 'admin_risk_profit_users')->first();
        $this->assertNotNull($record);
        $this->assertSame((int) $parent->id, (int) $record->parent_id);
        $this->assertSame('admin', (string) $record->guard_type);
        $this->assertSame(3, (int) $record->type);
        $this->assertSame('', (string) $record->route);
        $this->assertSame('admin_api_riskProfitUsers', (string) $record->api_route);
        $this->assertSame(1, (int) $record->status);
        $this->assertNull($record->deleted_at);
        $this->assertSame(0, DB::table('role_permissions')->where('permission_id', $record->id)->count());

        $migration->down();
        $disabled = DB::table('permissions')->where('id', $record->id)->first();
        $this->assertNotNull($disabled);
        $this->assertSame(0, (int) $disabled->status);
        $this->assertNotNull($disabled->deleted_at);

        $migration->up();
        $restored = DB::table('permissions')->where('id', $record->id)->first();
        $this->assertNotNull($restored);
        $this->assertSame(1, (int) $restored->status);
        $this->assertNull($restored->deleted_at);
    }

    /**
     * 第二批模块页面和 API 权限期望值。
     *
     * @return array<int, array{slug:string, type:int, route:string, api_route:string}>
     */
    private function expectedPermissions(): array
    {
        return [
            ['slug' => 'admin_vouchers', 'type' => 1, 'route' => '/admin/vouchers', 'api_route' => ''],
            ['slug' => 'admin_voucher_list', 'type' => 3, 'route' => '', 'api_route' => 'admin_api_voucherList'],
            ['slug' => 'admin_voucher_approve', 'type' => 3, 'route' => '', 'api_route' => 'admin_api_voucherApprove'],
            ['slug' => 'admin_voucher_reject', 'type' => 3, 'route' => '', 'api_route' => 'admin_api_voucherReject'],
            ['slug' => 'admin_risk', 'type' => 1, 'route' => '/admin/risk', 'api_route' => ''],
            ['slug' => 'admin_risk_positions', 'type' => 3, 'route' => '', 'api_route' => 'admin_api_riskPositions'],
            ['slug' => 'admin_risk_margin_calls', 'type' => 3, 'route' => '', 'api_route' => 'admin_api_riskMarginCalls'],
            ['slug' => 'admin_risk_force_close', 'type' => 3, 'route' => '', 'api_route' => 'admin_api_riskForceClose'],
            ['slug' => 'admin_blacklist', 'type' => 1, 'route' => '/admin/blacklist', 'api_route' => ''],
            ['slug' => 'admin_blacklist_list', 'type' => 3, 'route' => '', 'api_route' => 'admin_api_blacklistList'],
            ['slug' => 'admin_blacklist_create', 'type' => 3, 'route' => '', 'api_route' => 'admin_api_createBlacklist'],
            ['slug' => 'admin_blacklist_update', 'type' => 3, 'route' => '', 'api_route' => 'admin_api_updateBlacklist'],
            ['slug' => 'admin_blacklist_delete', 'type' => 3, 'route' => '', 'api_route' => 'admin_api_deleteBlacklist'],
            ['slug' => 'admin_cancel_applies', 'type' => 1, 'route' => '/admin/cancel-applies', 'api_route' => ''],
            ['slug' => 'admin_cancel_apply_list', 'type' => 3, 'route' => '', 'api_route' => 'admin_api_cancelApplyList'],
            ['slug' => 'admin_cancel_apply_approve', 'type' => 3, 'route' => '', 'api_route' => 'admin_api_cancelApplyApprove'],
            ['slug' => 'admin_cancel_apply_reject', 'type' => 3, 'route' => '', 'api_route' => 'admin_api_cancelApplyReject'],
            ['slug' => 'admin_trades', 'type' => 1, 'route' => '/admin/trades', 'api_route' => ''],
            ['slug' => 'admin_trade_list', 'type' => 3, 'route' => '', 'api_route' => 'admin_api_tradeList'],
            ['slug' => 'admin_open_positions', 'type' => 3, 'route' => '', 'api_route' => 'admin_api_openPositions'],
            ['slug' => 'admin_closed_positions', 'type' => 3, 'route' => '', 'api_route' => 'admin_api_closedPositions'],
            ['slug' => 'admin_closed_positions_export', 'type' => 3, 'route' => '', 'api_route' => 'admin_api_exportClosedPositions'],
            ['slug' => 'admin_trade_summary', 'type' => 3, 'route' => '', 'api_route' => 'admin_api_tradeSummary'],
            ['slug' => 'admin_big_agents', 'type' => 1, 'route' => '/admin/big-agents', 'api_route' => ''],
            ['slug' => 'admin_big_agent_list', 'type' => 3, 'route' => '', 'api_route' => 'admin_api_bigAgentList'],
            ['slug' => 'admin_big_agent_create', 'type' => 3, 'route' => '', 'api_route' => 'admin_api_createBigAgent'],
            ['slug' => 'admin_big_agent_update', 'type' => 3, 'route' => '', 'api_route' => 'admin_api_updateBigAgent'],
            ['slug' => 'admin_big_agent_delete', 'type' => 3, 'route' => '', 'api_route' => 'admin_api_deleteBigAgent'],
        ];
    }
}
