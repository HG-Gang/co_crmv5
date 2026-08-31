<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/16
 * Time: 02:16
 */

/**
 * AdminPositionSummaryModuleTest
 *
 * 文件功能：
 * - 验证后台持仓汇总模块：页面/路由权限、按当前筛选导出 CSV、后代客户交易汇总到代理行、缺后代行时家谱回退、旧 sub_agents 搜索返回父级与直属汇总。
 * - 输入：HTTP 请求与事务回滚的数据库夹具；输出：PHPUnit 断言结果。
 * - 明确不负责：不覆盖真实 MT4 网关与线上支付/出金通道（由网关契约测试锁定）。
 */

namespace Tests\Feature;

use App\Http\Middleware\AdminAuthenticate;
use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\JwtAuthMiddleware;
use App\Http\Middleware\SingleSignOn;
use App\Models\Admin;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * 后台持仓汇总模块覆盖测试。
 *
 * 重点验证页面入口、权限路由、真实表聚合和当前筛选 CSV 导出闭环。
 */
class AdminPositionSummaryModuleTest extends TestCase
{
    use DatabaseTransactions;

    public function test_position_summary_page_is_registered(): void
    {
        $this->assertTrue(Route::has('admin_page_position_summary'), 'admin_page_position_summary page route is missing.');
    }

    public function test_position_summary_page_renders_blade_controls(): void
    {
        $response = $this->get('/admin/position-summary');

        $response->assertOk();
        $response->assertSee('crm-admin-workbench', false);
        $response->assertSee('id="positionSummaryCards"', false);
        $response->assertSee('id="positionSummarySearchForm"', false);
        $response->assertSee('id="positionSummaryTable"', false);
        $response->assertSee('name="user_id"', false);
        $response->assertSee('name="user_name"', false);
        $response->assertSee('name="account_type"', false);
        $response->assertSee('id="exportPositionSummary"', false);
        $response->assertSee('data-permission="admin_position_summary_export"', false);
        $response->assertSee('/js/apps/admin/layui/pages.js', false);
        $response->assertSee("data-layui-page=\"position-summary/index\"", false);
    }

    public function test_position_summary_api_routes_have_permission_middleware(): void
    {
        foreach (['admin_api_positionSummaryList', 'admin_api_exportPositionSummary'] as $routeName) {
            $this->assertTrue(Route::has($routeName), $routeName . ' API route is missing.');
            $this->assertContains('check.permission:admin', Route::getRoutes()->getByName($routeName)->gatherMiddleware());
        }
    }

    public function test_position_summary_export_endpoint_returns_current_filter_csv(): void
    {
        $admin = Admin::query()->find(1) ?: (Admin::query()->first() ?: Admin::factory()->create());
        $now = time();
        $userId = 982611;

        DB::table('user_infos')->updateOrInsert(
            ['user_id' => $userId],
            [
                'login_id' => 0,
                'user_name' => 'Position Export User',
                'phone' => '',
                'gender' => 1,
                'account_type' => 2,
                'parent_id' => 0,
                'family_tree' => (string) $userId,
                'mt4_code' => $userId,
                'mt4_group' => 'demo-position',
                'total_funds' => 0,
                'equity' => 0,
                'effective_credit' => 0,
                'created_at' => $now,
                'updated_at' => $now,
                'deleted_at' => null,
            ]
        );

        DB::table('symbol_prices')->updateOrInsert(
            ['symbol' => 'XAUCSV'],
            [
                'time' => date('Y-m-d H:i:s', $now),
                'bid' => 100,
                'ask' => 101,
                'low' => 99,
                'high' => 102,
                'direction' => 0,
                'digits' => 2,
                'spread' => 1,
                'group_id' => 1,
                'status' => 1,
                'modify_time' => date('Y-m-d H:i:s', $now),
                'created_at' => $now,
                'updated_at' => $now,
                'deleted_at' => null,
            ]
        );

        DB::table('mt4_trades')->updateOrInsert(
            ['ticket' => 990611],
            [
                'login' => $userId,
                'symbol' => 'XAUCSV',
                'cmd' => 0,
                'volume' => 3.5,
                'open_price' => 100,
                'close_price' => 101,
                'commission' => -1.23,
                'swaps' => -0.45,
                'profit' => 66.78,
                'open_time' => $now - 7200,
                'close_time' => $now - 3600,
                'created_at' => $now,
                'updated_at' => $now,
            ]
        );

        $response = $this->withoutMiddleware([AdminAuthenticate::class, JwtAuthMiddleware::class, SingleSignOn::class, CheckPermission::class])
            ->actingAs($admin, 'admin')
            ->post('/api/admin/exportPositionSummary', ['user_id' => $userId]);

        $response->assertOk();
        $this->assertStringContainsString('text/csv', (string) $response->headers->get('content-type'));
        $this->assertStringContainsString('position_summary_export.csv', (string) $response->headers->get('content-disposition'));

        $content = $response->streamedContent();
        $this->assertStringContainsString('Position Export User', $content);
        $this->assertStringContainsString('66.78', $content);
        $this->assertStringContainsString('3.5', $content);
    }

    /**
     * 旧项目后台持仓汇总按代理树统计下级客户交易。
     *
     * 业务意图：
     * - 根代理本身没有 MT4 交易时，仍必须把 agent_descendants 范围内的下级客户交易汇总到代理行。
     * - 返回值中的 records 表示当前页代理/用户行，summary 表示当前筛选范围总计；两者都不能只统计代理自己的 mt4_trades.login。
     */
    public function test_position_summary_rolls_up_descendant_customer_trades_to_agent_row(): void
    {
        $admin = Admin::query()->find(1) ?: (Admin::query()->first() ?: Admin::factory()->create());
        $now = time();
        $agentId = 982701;
        $customerId = 982702;
        $ticket = 990701;

        $this->upsertPositionSummaryUser($agentId, 'Position Tree Agent', 1, 0, $now);
        $this->upsertPositionSummaryUser($customerId, 'Position Tree Customer', 2, $agentId, $now);

        DB::table('agent_descendants')->updateOrInsert(
            ['agent_id' => $agentId, 'descendant_id' => $customerId],
            [
                'descendant_type' => 2,
                'is_direct' => 1,
                'depth' => 1,
                'created_at' => $now,
                'updated_at' => $now,
                'deleted_at' => null,
            ]
        );

        DB::table('symbol_prices')->updateOrInsert(
            ['symbol' => 'XAUPOSROLL'],
            [
                'time' => date('Y-m-d H:i:s', $now),
                'bid' => 100,
                'ask' => 101,
                'low' => 99,
                'high' => 102,
                'direction' => 0,
                'digits' => 2,
                'spread' => 1,
                'group_id' => 1,
                'status' => 1,
                'modify_time' => date('Y-m-d H:i:s', $now),
                'created_at' => $now,
                'updated_at' => $now,
                'deleted_at' => null,
            ]
        );

        DB::table('mt4_trades')->updateOrInsert(
            ['ticket' => $ticket],
            [
                'login' => $customerId,
                'symbol' => 'XAUPOSROLL',
                'cmd' => 0,
                'volume' => 7.25,
                'open_price' => 100,
                'close_price' => 103,
                'commission' => -2.5,
                'swaps' => -0.75,
                'profit' => 123.45,
                'open_time' => $now - 7200,
                'close_time' => $now - 3600,
                'comment' => 'descendant trade',
                'modify_time' => $now - 1800,
                'created_at' => $now,
                'updated_at' => $now,
            ]
        );

        $response = $this->withoutMiddleware([AdminAuthenticate::class, JwtAuthMiddleware::class, SingleSignOn::class, CheckPermission::class])
            ->actingAs($admin, 'admin')
            ->post('/api/admin/positionSummaryList', ['user_id' => $agentId, 'per_page' => 5]);

        $response->assertOk();
        $payload = $response->json('data');

        $this->assertSame($agentId, (int) $payload['records']['data'][0]['user_id']);

        $row = $payload['records']['data'][0];
        $summary = $payload['summary'];

        $this->assertSame(1, (int) $row['total_orders']);
        $this->assertSame(7.25, (float) $row['total_volume']);
        $this->assertSame(123.45, (float) $row['total_profit']);
        $this->assertSame(7.25, (float) $row['total_noble_metal']);
        $this->assertSame(1, (int) $summary['total_orders']);
        $this->assertSame(7.25, (float) $summary['total_volume']);
        $this->assertSame(123.45, (float) $summary['total_profit']);
    }

    /**
     * 旧数据迁移时闭包表可能缺行，此时后台持仓汇总必须用 parent_id/family_tree 兜底。
     *
     * 返回值要求：
     * - agent_descendants 没有记录时，代理行仍按 user_infos.family_tree 中的祖先关系汇总下级客户交易。
     * - 该兜底只补真实关系链，不伪造 MT4 交易或代理层级。
     */
    public function test_position_summary_uses_family_tree_fallback_when_descendant_rows_are_missing(): void
    {
        $admin = Admin::query()->find(1) ?: (Admin::query()->first() ?: Admin::factory()->create());
        $now = time();
        $agentId = 982711;
        $customerId = 982712;
        $ticket = 990711;

        $this->upsertPositionSummaryUser($agentId, 'Position Family Agent', 1, 0, $now);
        $this->upsertPositionSummaryUser($customerId, 'Position Family Customer', 2, $agentId, $now);
        DB::table('user_infos')->where('user_id', $customerId)->update([
            'family_tree' => '999999,' . $customerId,
        ]);

        DB::table('agent_descendants')
            ->where('agent_id', $agentId)
            ->orWhere('descendant_id', $customerId)
            ->delete();

        DB::table('symbol_prices')->updateOrInsert(
            ['symbol' => 'EURPOSROLL'],
            [
                'time' => date('Y-m-d H:i:s', $now),
                'bid' => 1.1,
                'ask' => 1.2,
                'low' => 1.0,
                'high' => 1.3,
                'direction' => 0,
                'digits' => 5,
                'spread' => 1,
                'group_id' => 2,
                'status' => 1,
                'modify_time' => date('Y-m-d H:i:s', $now),
                'created_at' => $now,
                'updated_at' => $now,
                'deleted_at' => null,
            ]
        );

        DB::table('mt4_trades')->updateOrInsert(
            ['ticket' => $ticket],
            [
                'login' => $customerId,
                'symbol' => 'EURPOSROLL',
                'cmd' => 1,
                'volume' => 4.5,
                'open_price' => 1.1,
                'close_price' => 1.0,
                'commission' => -1.5,
                'swaps' => -0.25,
                'profit' => 88.8,
                'open_time' => $now - 7200,
                'close_time' => $now - 3600,
                'comment' => 'family fallback trade',
                'modify_time' => $now - 1800,
                'created_at' => $now,
                'updated_at' => $now,
            ]
        );

        $response = $this->withoutMiddleware([AdminAuthenticate::class, JwtAuthMiddleware::class, SingleSignOn::class, CheckPermission::class])
            ->actingAs($admin, 'admin')
            ->post('/api/admin/positionSummaryList', ['user_id' => $agentId, 'per_page' => 5]);

        $response->assertOk();
        $row = $response->json('data.records.data.0');

        $this->assertSame($agentId, (int) $row['user_id']);
        $this->assertSame(1, (int) $row['total_orders']);
        $this->assertSame(4.5, (float) $row['total_volume']);
        $this->assertSame(88.8, (float) $row['total_profit']);
    }

    /**
     * 旧后台下级代理持仓汇总入口必须兼容 searchtype/userPId 参数。
     *
     * 业务意图：
     * - 旧 `subAgentsListSearchV2` 传入 `searchtype=subAgentsSearch` 和 `userPId` 时，返回当前代理自身与直属下级代理的持仓汇总行。
     * - 每个代理行仍按完整代理树汇总下级客户交易，不应退化为纯代理下级列表或全量用户列表。
     */
    public function test_position_summary_legacy_sub_agents_search_returns_parent_and_direct_agent_rollups(): void
    {
        $admin = Admin::query()->find(1) ?: (Admin::query()->first() ?: Admin::factory()->create());
        $now = time();
        $rootAgentId = 982721;
        $directAgentId = 982722;
        $customerId = 982723;
        $outsideAgentId = 982724;

        $this->upsertPositionSummaryUser($rootAgentId, 'Position Legacy Root Agent', 1, 0, $now);
        $this->upsertPositionSummaryUser($directAgentId, 'Position Legacy Direct Agent', 1, $rootAgentId, $now);
        $this->upsertPositionSummaryUser($customerId, 'Position Legacy Customer', 2, $directAgentId, $now);
        $this->upsertPositionSummaryUser($outsideAgentId, 'Position Legacy Outside Agent', 1, 0, $now);

        foreach ([
            [$rootAgentId, $directAgentId, 1, 1],
            [$rootAgentId, $customerId, 2, 2],
            [$directAgentId, $customerId, 2, 1],
        ] as [$agentId, $descendantId, $descendantType, $depth]) {
            DB::table('agent_descendants')->updateOrInsert(
                ['agent_id' => $agentId, 'descendant_id' => $descendantId],
                [
                    'descendant_type' => $descendantType,
                    'is_direct' => $depth === 1 ? 1 : 0,
                    'depth' => $depth,
                    'created_at' => $now,
                    'updated_at' => $now,
                    'deleted_at' => null,
                ]
            );
        }

        DB::table('symbol_prices')->updateOrInsert(
            ['symbol' => 'LEGACYSUBROLL'],
            [
                'time' => date('Y-m-d H:i:s', $now),
                'bid' => 10,
                'ask' => 11,
                'low' => 9,
                'high' => 12,
                'direction' => 0,
                'digits' => 2,
                'spread' => 1,
                'group_id' => 4,
                'status' => 1,
                'modify_time' => date('Y-m-d H:i:s', $now),
                'created_at' => $now,
                'updated_at' => $now,
                'deleted_at' => null,
            ]
        );

        foreach ([
            [990721, $customerId, 6.0, 120.0],
            [990722, $outsideAgentId, 3.0, 300.0],
        ] as [$ticket, $login, $volume, $profit]) {
            DB::table('mt4_trades')->updateOrInsert(
                ['ticket' => $ticket],
                [
                    'login' => $login,
                    'symbol' => 'LEGACYSUBROLL',
                    'cmd' => 0,
                    'volume' => $volume,
                    'open_price' => 10,
                    'close_price' => 12,
                    'commission' => -1,
                    'swaps' => -0.5,
                    'profit' => $profit,
                    'open_time' => $now - 7200,
                    'close_time' => $now - 3600,
                    'comment' => 'legacy sub agents rollup',
                    'modify_time' => $now - 1800,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]
            );
        }

        $response = $this->withoutMiddleware([AdminAuthenticate::class, JwtAuthMiddleware::class, SingleSignOn::class, CheckPermission::class])
            ->actingAs($admin, 'admin')
            ->post('/api/admin/positionSummaryList', [
                'searchtype' => 'subAgentsSearch',
                'userPId' => $rootAgentId,
                'per_page' => 10,
            ]);

        $response->assertOk();
        $rows = collect($response->json('data.records.data'));

        $this->assertSame(
            [$rootAgentId, $directAgentId],
            $rows->pluck('user_id')->map(static fn ($id): int => (int) $id)->sort()->values()->all()
        );

        $rootRow = $rows->firstWhere('user_id', $rootAgentId);
        $directRow = $rows->firstWhere('user_id', $directAgentId);

        $this->assertSame(1, (int) $rootRow['total_orders']);
        $this->assertSame(1, (int) $directRow['total_orders']);
        $this->assertSame(6.0, (float) $rootRow['total_volume']);
        $this->assertSame(6.0, (float) $directRow['total_volume']);
        $this->assertSame(120.0, (float) $rootRow['total_profit']);
        $this->assertSame(120.0, (float) $directRow['total_profit']);
        $this->assertFalse($rows->contains('user_id', $outsideAgentId));
    }

    public function test_position_summary_controller_uses_real_tables_and_data_scope(): void
    {
        $controllerPath = app_path('Http/Controllers/Admin/PositionSummaryController.php');

        $this->assertFileExists($controllerPath, 'PositionSummaryController does not exist.');
        $source = file_get_contents($controllerPath);

        foreach ([
            'UserInfo::query()',
            'Mt4Trade::query()',
            'symbol_prices',
            'AdminDataScopeService',
            'applyDataScope',
            'positionSummaryList',
        ] as $expected) {
            $this->assertStringContainsString($expected, $source);
        }
    }

    public function test_position_summary_permission_migration_declares_required_permissions(): void
    {
        $migrationPath = database_path('migrations/2026_06_07_000015_add_admin_position_summary_permissions.php');

        $this->assertFileExists($migrationPath, 'Position summary permission migration does not exist.');
        $source = file_get_contents($migrationPath);

        foreach ([
            'admin_position_summary',
            'admin_position_summary_list',
            'admin_position_summary_export',
            'admin_api_positionSummaryList',
            'admin_api_exportPositionSummary',
            '/admin/position-summary',
        ] as $expected) {
            $this->assertStringContainsString($expected, $source);
        }
    }

    /**
     * 写入持仓汇总测试用户。
     *
     * @param int $userId 业务用户 ID，对应 user_infos.user_id。
     * @param string $userName 测试用户名称，用于响应断言定位。
     * @param int $accountType 账号类型，1=代理，2=普通客户。
     * @param int $parentId 上级代理 ID，补充 parent_id 代理树兜底关系。
     * @param int $now 当前测试时间戳，用于固定 created_at/updated_at。
     * @return void
     */
    private function upsertPositionSummaryUser(int $userId, string $userName, int $accountType, int $parentId, int $now): void
    {
        DB::table('user_infos')->updateOrInsert(
            ['user_id' => $userId],
            [
                'login_id' => 0,
                'user_name' => $userName,
                'phone' => '',
                'gender' => 1,
                'account_type' => $accountType,
                'parent_id' => $parentId,
                'family_tree' => $parentId > 0 ? $parentId . ',' . $userId : (string) $userId,
                // 这些历史兼容夹具让 MT4 login 与业务 ID 相同，但仍显式走 mt4_code 映射，禁止控制器自行猜测。
                'mt4_code' => $userId,
                'mt4_group' => 'demo-position-tree',
                'total_funds' => 0,
                'equity' => 0,
                'effective_credit' => 0,
                'created_at' => $now,
                'updated_at' => $now,
                'deleted_at' => null,
            ]
        );
    }
}
