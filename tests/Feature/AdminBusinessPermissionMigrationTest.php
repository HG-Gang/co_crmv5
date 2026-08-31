<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/07/29
 * Time: 01:34
 */

/**
 * AdminBusinessPermissionMigrationTest
 *
 * 文件功能：
 * - 验证第一批后台业务模块的菜单权限与 API 权限由迁移类写入 permissions 表，role_permissions 仅负责角色授权关系。
 * - 输入：权限/结构迁移类与测试数据库；输出：PHPUnit 断言结果。
 * - 明确不负责：不验证 check.permission 中间件的运行时鉴权与按钮渲染（由模块契约测试锁定）。
 */

namespace Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * 后台业务模块权限迁移测试。
 *
 * 测试目标：
 * - 后台菜单、按钮、API 鉴权必须从 permissions 表配置得到。
 * - 第一批 Blade 页面对应的菜单权限和 API 权限必须由迁移写入，避免手工漏配导致普通管理员无法访问。
 * - role_permissions 仍只负责角色授权关系，本测试只验证权限字典是否完整。
 */
class AdminBusinessPermissionMigrationTest extends TestCase
{
    use DatabaseTransactions;

    /**
     * 业务模块权限迁移文件必须能把页面与接口权限写入 permissions 表。
     *
     * @return void
     */
    public function test_admin_business_module_permissions_are_seeded_by_migration(): void
    {
        $migrationPath = database_path('migrations/2026_06_06_000004_add_admin_business_module_permissions.php');

        $this->assertFileExists($migrationPath, '后台业务模块权限迁移文件不存在。');

        require_once $migrationPath;

        $slugs = collect($this->expectedPermissions())->pluck('slug')->all();
        DB::table('permissions')->whereIn('slug', $slugs)->delete();

        (new \AddAdminBusinessModulePermissions())->up();

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

    /**
     * 第一批后台业务模块权限字典。
     *
     * 字段说明：
     * - slug：permissions.slug，前端菜单/按钮和后端接口共用的稳定权限标识。
     * - type：1=菜单或页面入口，3=按钮/API 动作。
     * - route：Blade 页面路径，只有菜单节点需要配置。
     * - api_route：Laravel API 路由名称，只有按钮/API 节点需要配置。
     *
     * @return array<int, array{slug:string, type:int, route:string, api_route:string}>
     */
    private function expectedPermissions(): array
    {
        return [
            ['slug' => 'admin_agents', 'type' => 1, 'route' => '/admin/agents', 'api_route' => ''],
            ['slug' => 'admin_agent_list', 'type' => 3, 'route' => '', 'api_route' => 'admin_api_agentList'],
            ['slug' => 'admin_agent_descendants', 'type' => 3, 'route' => '', 'api_route' => 'admin_api_agentDescendants'],
            ['slug' => 'admin_deposits', 'type' => 1, 'route' => '/admin/deposits', 'api_route' => ''],
            ['slug' => 'admin_deposit_list', 'type' => 3, 'route' => '', 'api_route' => 'admin_api_depositList'],
            ['slug' => 'admin_deposit_flow_list', 'type' => 3, 'route' => '', 'api_route' => 'admin_api_depositFlowList'],
            ['slug' => 'admin_deposit_approve', 'type' => 3, 'route' => '', 'api_route' => 'admin_api_depositApprove'],
            ['slug' => 'admin_deposit_reject', 'type' => 3, 'route' => '', 'api_route' => 'admin_api_depositReject'],
            ['slug' => 'admin_withdrawals', 'type' => 1, 'route' => '/admin/withdrawals', 'api_route' => ''],
            ['slug' => 'admin_withdraw_list', 'type' => 3, 'route' => '', 'api_route' => 'admin_api_withdrawList'],
            ['slug' => 'admin_withdraw_process', 'type' => 3, 'route' => '', 'api_route' => 'admin_api_withdrawProcess'],
            ['slug' => 'admin_withdraw_complete', 'type' => 3, 'route' => '', 'api_route' => 'admin_api_withdrawComplete'],
            ['slug' => 'admin_withdraw_reject', 'type' => 3, 'route' => '', 'api_route' => 'admin_api_withdrawReject'],
            ['slug' => 'admin_commissions', 'type' => 1, 'route' => '/admin/commissions', 'api_route' => ''],
            ['slug' => 'admin_commission_list', 'type' => 3, 'route' => '', 'api_route' => 'admin_api_commissionList'],
            ['slug' => 'admin_commission_settle', 'type' => 3, 'route' => '', 'api_route' => 'admin_api_commissionSettle'],
            ['slug' => 'admin_agent_levels', 'type' => 1, 'route' => '/admin/agent-levels', 'api_route' => ''],
            ['slug' => 'admin_agent_level_list', 'type' => 3, 'route' => '', 'api_route' => 'admin_api_agentLevelList'],
            ['slug' => 'admin_group_configs', 'type' => 1, 'route' => '/admin/group-configs', 'api_route' => ''],
            ['slug' => 'admin_group_config_list', 'type' => 3, 'route' => '', 'api_route' => 'admin_api_groupConfigList'],
            ['slug' => 'admin_system_configs', 'type' => 1, 'route' => '/admin/system-configs', 'api_route' => ''],
            ['slug' => 'admin_system_config_list', 'type' => 3, 'route' => '', 'api_route' => 'admin_api_systemConfigList'],
            ['slug' => 'admin_channels', 'type' => 1, 'route' => '/admin/channels', 'api_route' => ''],
            ['slug' => 'admin_channel_list', 'type' => 3, 'route' => '', 'api_route' => 'admin_api_channelList'],
            ['slug' => 'admin_admins', 'type' => 1, 'route' => '/admin/admins', 'api_route' => ''],
            ['slug' => 'admin_admin_list', 'type' => 3, 'route' => '', 'api_route' => 'admin_api_adminList'],
            ['slug' => 'admin_news', 'type' => 1, 'route' => '/admin/news', 'api_route' => ''],
            ['slug' => 'admin_news_list', 'type' => 3, 'route' => '', 'api_route' => 'admin_api_newsList'],
        ];
    }
}
