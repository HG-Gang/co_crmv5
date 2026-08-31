<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/07/07
 * Time: 05:56
 */

/**
 * AdminAgentOperationPermissionMigrationTest
 *
 * 文件功能：
 * - 验证代理操作权限（下级查看、等级调整、佣金调整等）由迁移类完整写入 permissions 表。
 * - 输入：权限/结构迁移类与测试数据库；输出：PHPUnit 断言结果。
 * - 明确不负责：不验证 check.permission 中间件的运行时鉴权与按钮渲染（由模块契约测试锁定）。
 */

namespace Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * 后台代理操作权限迁移测试。
 *
 * 测试目标：
 * - 代理等级调整、代理佣金调整必须写入 permissions 表。
 * - 权限必须绑定真实 API 命名路由，保证 check.permission:admin 能在接口层鉴权。
 */
class AdminAgentOperationPermissionMigrationTest extends TestCase
{
    use DatabaseTransactions;

    /**
     * 代理操作权限迁移必须写入按钮/API 权限。
     *
     * @return void
     */
    public function test_agent_operation_permissions_are_seeded_by_migration(): void
    {
        $migrationPath = database_path('migrations/2026_06_07_000003_add_admin_agent_operation_permissions.php');

        $this->assertFileExists($migrationPath, '代理操作权限迁移文件不存在。');

        require_once $migrationPath;

        $slugs = collect($this->expectedPermissions())->pluck('slug')->all();
        DB::table('permissions')->whereIn('slug', $slugs)->delete();

        (new \AddAdminAgentOperationPermissions())->up();

        foreach ($this->expectedPermissions() as $permission) {
            $record = DB::table('permissions')->where('slug', $permission['slug'])->first();

            $this->assertNotNull($record, $permission['slug'] . ' 权限未写入 permissions 表。');
            $this->assertSame('admin', $record->guard_type);
            $this->assertSame(3, (int) $record->type);
            $this->assertSame($permission['api_route'], (string) $record->api_route);
            $this->assertSame(1, (int) $record->status);
        }
    }

    /**
     * 本迁移必须写入的代理操作权限。
     *
     * @return array<int, array{slug:string, api_route:string}>
     */
    private function expectedPermissions(): array
    {
        return [
            ['slug' => 'admin_agent_update_level', 'api_route' => 'admin_api_updateAgentLevel'],
            ['slug' => 'admin_agent_update_commission', 'api_route' => 'admin_api_updateAgentCommission'],
            ['slug' => 'admin_agent_export', 'api_route' => 'admin_api_exportAgents'],
            ['slug' => 'admin_agent_stats', 'api_route' => 'admin_api_agentStatsList'],
            ['slug' => 'admin_agent_confirm', 'api_route' => 'admin_api_confirmAgent'],
            ['slug' => 'admin_agent_reject_confirmation', 'api_route' => 'admin_api_rejectAgentConfirmation'],
        ];
    }
}
