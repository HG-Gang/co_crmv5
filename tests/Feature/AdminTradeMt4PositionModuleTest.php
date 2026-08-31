<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/07/29
 * Time: 01:27
 */

/**
 * AdminTradeMt4PositionModuleTest
 *
 * 文件功能：
 * - 验证后台交易模块 MT4 持仓口径：开/平仓走 mt4_trades、Layui/Blade 汇总卡片、旧平仓筛选与备注列、导出路由权限接线、按用户 MT4 分组后缀过滤 order_type。
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
use Tests\Feature\Concerns\ReadsAggregatedLayuiScripts;

/**
 * 后台 MT4 持仓/平仓第一阶段迁移契约测试。
 *
 * 测试目标：
 * - 使用源码契约确认控制器、Blade 和聚合 JS 均接入后台交易闭环。
 * - 使用真实接口夹具确认旧项目平仓筛选参数、强平 COMMENT 口径和返回字段已恢复。
 * - 契约来自旧项目 `AdminOpenOrderController`、`AdminCloseOrderController` 与当前真实表 `mt4_trades`。
 * - 第一阶段必须保证后台交易接口读取 `Mt4Trade`，并向 Layui 页面返回 `records + summary`，方便表格和汇总卡片共同展示。
 */
class AdminTradeMt4PositionModuleTest extends TestCase
{
    use ReadsAggregatedLayuiScripts;
    use DatabaseTransactions;

    /**
     * TradeController 必须使用 mt4_trades 模型和真实字段口径。
     *
     * @return void
     */
    public function test_trade_controller_uses_mt4_trade_for_open_and_closed_positions(): void
    {
        $source = file_get_contents(app_path('Http/Controllers/Admin/TradeController.php')) ?: '';

        $this->assertStringContainsString('use App\Models\Mt4Trade;', $source);
        $this->assertStringContainsString('use App\Services\AdminDataScopeService;', $source);
        $this->assertStringContainsString('private $adminDataScopeService;', $source);
        $this->assertStringContainsString('Mt4Trade::query()', $source);
        $this->assertStringContainsString('whereIn(\'mt4_trades.cmd\', [0, 1, 2, 3, 4, 5])', $source);
        $this->assertStringContainsString("join->on('user_infos.mt4_code', '=', 'mt4_trades.login')", $source);
        $this->assertStringContainsString('whereNull(\'close_time\')', $source);
        $this->assertStringContainsString('where(\'close_time\', \'>\', 0)', $source);
        $this->assertStringContainsString('$this->applyDataScope($query, $request);', $source);
        $this->assertStringContainsString('$this->adminDataScopeService->apply($query, $admin, \'user\', \'user_infos.user_id\');', $source);
        $this->assertStringContainsString('summaryFor(clone $query)', $source);
    }

    /**
     * 后台交易页面 JS 必须解析 records + summary 包装结构并更新汇总卡片。
     *
     * @return void
     */
    public function test_trade_layui_script_renders_records_and_summary_cards(): void
    {
        $source = $this->adminLayuiScript('trades/index.js');

        $this->assertStringContainsString('updateSummaryCards', $source);
        $this->assertStringContainsString('response.data.summary', $source);
        $this->assertStringContainsString('response.data.records', $source);
        $this->assertStringContainsString('total_orders', $source);
        $this->assertStringContainsString('total_volume', $source);
        $this->assertStringContainsString('total_profit', $source);
    }

    /**
     * 后台交易 Blade 页面必须提供汇总卡片 DOM，避免 JS 汇总结果无处渲染。
     *
     * @return void
     */
    public function test_trade_blade_contains_summary_cards(): void
    {
        $source = file_get_contents(resource_path('admin/layui/trades/index.blade.php')) ?: '';

        $this->assertStringContainsString('data-summary-field="total_orders"', $source);
        $this->assertStringContainsString('data-summary-field="total_volume"', $source);
        $this->assertStringContainsString('data-summary-field="total_profit"', $source);
        $this->assertStringContainsString('data-summary-field="total_swaps"', $source);
        $this->assertStringContainsString('data-summary-field="total_commission"', $source);
    }

    /**
     * 后台交易服务端 Blade 页面和 CrmUI 兼容入口必须暴露旧平仓筛选和 COMMENT/MODIFY_TIME 字段。
     *
     * @return void
     */
    public function test_trade_frontends_expose_legacy_closed_position_filters_and_comment_columns(): void
    {
        $blade = file_get_contents(resource_path('admin/layui/trades/index.blade.php')) ?: '';
        $layuiScript = $this->adminLayuiScript('trades/index.js');
        $crmUiPage = file_get_contents(app_path('Http/Controllers/CrmUi/Admin/PageController.php')) ?: '';

        foreach (['name="user_id"', 'name="ticket"', 'name="symbol"', 'name="start_date"', 'name="end_date"', 'name="is_coercion"', 'name="orderType"', 'value="Yes"', 'value="No"', 'value="real_disk"', 'value="test_disk"', 'data-mode="all"'] as $needle) {
            $this->assertStringContainsString($needle, $blade);
        }

        foreach (['laydate.render', '#tradeStartDate', '#tradeEndDate', "field: 'comment'", "field: 'modify_time'", "name: 'is_coercion'", "name: 'orderType'"] as $needle) {
            $this->assertStringContainsString($needle, $layuiScript);
        }

        foreach (["'ticket'", "'comment'", "'ordercomment'", "'modify_time'", "'is_coercion'", "'orderType'"] as $needle) {
            $this->assertStringContainsString($needle, $crmUiPage);
        }
    }

    /**
     * 历史平仓导出路由和服务端 Blade 页面入口必须与当前筛选闭环。
     *
     * 业务链路：
     * - Layui 后台使用当前搜索表单参数调用 CSV 导出接口。
     * - CrmUI 兼容入口通过统一 export action 暴露同一个导出端点。
     * - permissions.api_route 必须声明导出路由，避免新增接口绕过后台权限体系。
     *
     * @return void
     */
    public function test_closed_positions_export_route_permission_and_frontends_are_wired(): void
    {
        $blade = file_get_contents(resource_path('admin/layui/trades/index.blade.php')) ?: '';
        $layuiScript = $this->adminLayuiScript('trades/index.js');
        $crmUiPage = file_get_contents(app_path('Http/Controllers/CrmUi/Admin/PageController.php')) ?: '';
        $permissionMigration = file_get_contents(database_path('migrations/2026_06_06_000005_add_admin_second_batch_module_permissions.php')) ?: '';

        $this->assertTrue(Route::has('admin_api_exportClosedPositions'), 'admin_api_exportClosedPositions API route is not registered.');
        $this->assertContains(
            'check.permission:admin',
            Route::getRoutes()->getByName('admin_api_exportClosedPositions')->gatherMiddleware()
        );

        $this->assertStringContainsString('id="exportClosedPositions"', $blade);
        $this->assertStringContainsString('data-permission="admin_closed_positions_export"', $blade);
        $this->assertStringContainsString('/api/admin/exportClosedPositions', $layuiScript);
        $this->assertStringContainsString('closed_positions_export.csv', $layuiScript);
        $this->assertStringContainsString("exportActions('admin_api_exportClosedPositions', 'closed_positions_export.csv')", $crmUiPage);
        $this->assertStringContainsString('admin_closed_positions_export', $permissionMigration);
        $this->assertStringContainsString('admin_api_exportClosedPositions', $permissionMigration);
    }

    /**
     * 历史平仓接口必须兼容旧项目筛选参数并返回 COMMENT/MODIFY_TIME。
     *
     * 业务链路：
     * - 旧后台平仓列表用 userId、orderId、sym_symbol、startdate/enddate 和 is_coercion 作为查询字段。
     * - is_coercion=Yes 表示 MT4 COMMENT 以 so 开头的强平单，No 表示排除强平单。
     * - 返回 ordercomment 是旧 Blade 表格字段名，comment 是新项目统一字段名，二者必须指向同一条 MT4 原始备注。
     *
     * @return void
     */
    public function test_closed_positions_honor_legacy_force_close_filters_and_return_comment_fields(): void
    {
        $admin = Admin::query()->find(1) ?: (Admin::query()->first() ?: Admin::factory()->create());
        $now = time();
        $userId = 983311;
        $closeTime = strtotime('2026-07-25 10:30:00');

        $this->upsertUserInfo($userId, 'GMTK-LIVE-FORCE');
        $this->upsertUserInfo($userId + 1, 'GMTK-LIVE-OUTSIDE');
        $this->upsertMt4Trade(991311, $userId, 'XAUUSD.force', 'so: stop out #991311', $closeTime, $closeTime + 90, -18.25);
        $this->upsertMt4Trade(991312, $userId, 'XAUUSD.force', 'manual close #991312', $closeTime, $closeTime + 180, 9.50);
        $this->upsertMt4Trade(991313, $userId + 1, 'XAUUSD.force', 'so: other user #991313', $closeTime, $closeTime + 270, -88.00);

        $forcedResponse = $this->withoutMiddleware([AdminAuthenticate::class, JwtAuthMiddleware::class, SingleSignOn::class, CheckPermission::class])
            ->actingAs($admin, 'admin')
            ->post('/api/admin/closedPositions', [
                'userId' => $userId,
                'orderId' => '99131',
                'sym_symbol' => 'XAUUSD.force',
                'startdate' => '2026-07-25',
                'enddate' => '2026-07-25',
                'is_coercion' => 'Yes',
            ]);

        $forcedResponse->assertOk();
        $forcedResponse->assertJsonPath('data.summary.total_orders', 1);
        $forcedResponse->assertJsonPath('data.summary.total_profit', -18.25);
        $forcedResponse->assertJsonPath('data.records.data.0.ticket', 991311);
        $forcedResponse->assertJsonPath('data.records.data.0.comment', 'so: stop out #991311');
        $forcedResponse->assertJsonPath('data.records.data.0.ordercomment', 'so: stop out #991311');
        $forcedResponse->assertJsonPath('data.records.data.0.modify_time', $closeTime + 90);

        $manualResponse = $this->withoutMiddleware([AdminAuthenticate::class, JwtAuthMiddleware::class, SingleSignOn::class, CheckPermission::class])
            ->actingAs($admin, 'admin')
            ->post('/api/admin/closedPositions', [
                'userId' => $userId,
                'orderId' => '99131',
                'sym_symbol' => 'XAUUSD.force',
                'startdate' => '2026-07-25',
                'enddate' => '2026-07-25',
                'is_coercion' => 'No',
            ]);

        $manualResponse->assertOk();
        $manualResponse->assertJsonPath('data.summary.total_orders', 1);
        $manualResponse->assertJsonPath('data.summary.total_profit', 9.5);
        $manualResponse->assertJsonPath('data.records.data.0.ticket', 991312);
        $manualResponse->assertJsonPath('data.records.data.0.comment', 'manual close #991312');
        $manualResponse->assertJsonPath('data.records.data.0.ordercomment', 'manual close #991312');

        $allTradeResponse = $this->withoutMiddleware([AdminAuthenticate::class, JwtAuthMiddleware::class, SingleSignOn::class, CheckPermission::class])
            ->actingAs($admin, 'admin')
            ->post('/api/admin/tradeList', [
                'userId' => $userId,
                'orderId' => '991311',
                'sym_symbol' => 'XAUUSD.force',
                'startdate' => '2026-07-25',
                'enddate' => '2026-07-25',
            ]);

        $allTradeResponse->assertOk();
        $allTradeResponse->assertJsonPath('data.summary.total_orders', 1);
        $allTradeResponse->assertJsonPath('data.records.data.0.ticket', 991311);
        $allTradeResponse->assertJsonPath('data.records.data.0.comment', 'so: stop out #991311');
        $allTradeResponse->assertJsonPath('data.records.data.0.ordercomment', 'so: stop out #991311');
    }

    /**
     * 历史平仓导出接口必须返回当前筛选结果 CSV。
     *
     * 业务链路：
     * - 导出沿用 closedPositions 的 close_time、COMMENT 强平筛选和 orderType 实盘/测试盘口径。
     * - CSV 行必须包含 ticket、login、symbol、comment、ordercomment、modify_time 等核对字段。
     * - 导出只返回当前筛选命中的平仓单，不把其它用户、其它盘型或非强平单混入文件。
     *
     * @return void
     */
    public function test_closed_positions_export_endpoint_returns_current_filter_csv(): void
    {
        $admin = Admin::query()->find(1) ?: (Admin::query()->first() ?: Admin::factory()->create());
        $closeTime = strtotime('2026-07-25 15:30:00');

        $this->upsertUserInfo(983331, 'GMTK-LIVE-EXPORT');
        $this->upsertUserInfo(983332, 'GMTK-EXPORT-TEST');

        $this->upsertMt4Trade(991331, 983332, 'EXPORT-CLOSED', 'so: export forced #991331', $closeTime, $closeTime + 30, -12.34);
        $this->upsertMt4Trade(991332, 983332, 'EXPORT-CLOSED', 'manual export #991332', $closeTime, $closeTime + 60, 56.78);
        $this->upsertMt4Trade(991333, 983331, 'EXPORT-CLOSED', 'so: real export #991333', $closeTime, $closeTime + 90, -90.12);

        $response = $this->withoutMiddleware([AdminAuthenticate::class, JwtAuthMiddleware::class, SingleSignOn::class, CheckPermission::class])
            ->actingAs($admin, 'admin')
            ->post('/api/admin/exportClosedPositions', [
                'symbol' => 'EXPORT-CLOSED',
                'start_date' => '2026-07-25',
                'end_date' => '2026-07-25',
                'is_coercion' => 'Yes',
                'orderType' => 'test_disk',
            ]);

        $response->assertOk();
        $this->assertStringContainsString('text/csv', (string) $response->headers->get('content-type'));
        $this->assertStringContainsString('closed_positions_export.csv', (string) $response->headers->get('content-disposition'));

        $content = $response->streamedContent();
        $this->assertStringContainsString('ticket,login,symbol,cmd,volume,commission,swaps,profit,comment,ordercomment,open_time,close_time,modify_time', $content);
        $this->assertStringContainsString('991331', $content);
        $this->assertStringContainsString('983332', $content);
        $this->assertStringContainsString('EXPORT-CLOSED', $content);
        $this->assertStringContainsString('so: export forced #991331', $content);
        $this->assertStringContainsString('-12.34', $content);
        $this->assertStringNotContainsString('991332', $content);
        $this->assertStringNotContainsString('991333', $content);
    }

    /**
     * 交易接口必须按旧项目 orderType 区分实盘和测试盘。
     *
     * 业务链路：
     * - 旧后台使用 orderType=real_disk/test_disk 区分真实盘和测试盘。
     * - 测试盘由用户 MT4 分组是否以 -TEST 或 -TEST-P 结尾识别。
     * - 新项目没有旧 data_list.mt4_grp，因此使用真实迁移后的 user_infos.mt4_group 承接该口径。
     *
     * @return void
     */
    public function test_trade_order_type_filter_uses_user_mt4_group_suffixes(): void
    {
        $admin = Admin::query()->find(1) ?: (Admin::query()->first() ?: Admin::factory()->create());
        $closeTime = strtotime('2026-07-25 12:30:00');

        $this->upsertUserInfo(983321, 'GMTK-LIVE-A');
        $this->upsertUserInfo(983322, 'GMTK0-TEST');
        $this->upsertUserInfo(983323, 'GMTK1-TEST-P');

        $this->upsertMt4Trade(991321, 983321, 'ORDER-TYPE', 'manual live #991321', $closeTime, $closeTime + 10, 11.00);
        $this->upsertMt4Trade(991322, 983322, 'ORDER-TYPE', 'manual test #991322', $closeTime, $closeTime + 20, 22.00);
        $this->upsertMt4Trade(991323, 983323, 'ORDER-TYPE', 'manual test p #991323', $closeTime, $closeTime + 30, 33.00);

        $testDiskResponse = $this->withoutMiddleware([AdminAuthenticate::class, JwtAuthMiddleware::class, SingleSignOn::class, CheckPermission::class])
            ->actingAs($admin, 'admin')
            ->post('/api/admin/closedPositions', [
                'symbol' => 'ORDER-TYPE',
                'start_date' => '2026-07-25',
                'end_date' => '2026-07-25',
                'orderType' => 'test_disk',
            ]);

        $testDiskResponse->assertOk();
        $this->assertSame([991323, 991322], collect($testDiskResponse->json('data.records.data'))->pluck('ticket')->all());
        $testDiskResponse->assertJsonPath('data.summary.total_orders', 2);
        $testDiskResponse->assertJsonPath('data.summary.total_profit', 55);

        $realDiskResponse = $this->withoutMiddleware([AdminAuthenticate::class, JwtAuthMiddleware::class, SingleSignOn::class, CheckPermission::class])
            ->actingAs($admin, 'admin')
            ->post('/api/admin/closedPositions', [
                'symbol' => 'ORDER-TYPE',
                'start_date' => '2026-07-25',
                'end_date' => '2026-07-25',
                'orderType' => 'real_disk',
            ]);

        $realDiskResponse->assertOk();
        $realDiskResponse->assertJsonPath('data.summary.total_orders', 1);
        $realDiskResponse->assertJsonPath('data.summary.total_profit', 11);
        $realDiskResponse->assertJsonPath('data.records.data.0.ticket', 991321);
    }

    /**
     * 写入受控 MT4 平仓夹具。
     *
     * @param int $ticket MT4 订单号，作为 updateOrInsert 的稳定唯一键。
     * @param int $login MT4 登录账号，对应后台 userId/user_id 查询。
     * @param string $symbol 交易品种，对应旧 sym_symbol 和新 symbol 查询。
     * @param string $comment MT4 COMMENT，强平单必须以 so 开头。
     * @param int $closeTime 平仓时间戳，用于旧 startdate/enddate 范围筛选。
     * @param int $modifyTime MT4 修改时间戳，用于历史平仓排序和展示。
     * @param float $profit 当前订单盈亏，用于验证 summary 汇总只统计命中的记录。
     * @return void
     */
    private function upsertMt4Trade(int $ticket, int $login, string $symbol, string $comment, int $closeTime, int $modifyTime, float $profit): void
    {
        $now = time();

        DB::table('mt4_trades')->updateOrInsert(
            ['ticket' => $ticket],
            [
                'login' => $login,
                'symbol' => $symbol,
                'cmd' => 0,
                'volume' => 100,
                'open_price' => 1900.12345,
                'close_price' => 1901.12345,
                'commission' => -3.2,
                'swaps' => -0.4,
                'profit' => $profit,
                'open_time' => $closeTime - 3600,
                'close_time' => $closeTime,
                'comment' => $comment,
                'modify_time' => $modifyTime,
                'created_at' => $now,
                'updated_at' => $now,
            ]
        );
    }

    /**
     * 写入受控用户 MT4 分组夹具。
     *
     * @param int $userId 业务用户 ID，对应 mt4_trades.login。
     * @param string $mt4Group MT4 分组，后缀 -TEST/-TEST-P 表示测试盘。
     * @return void
     */
    private function upsertUserInfo(int $userId, string $mt4Group): void
    {
        $now = time();

        DB::table('user_infos')->updateOrInsert(
            ['user_id' => $userId],
            [
                'login_id' => 0,
                'user_name' => '交易分组夹具' . $userId,
                'phone' => '',
                'gender' => 1,
                'account_type' => 2,
                'parent_id' => 0,
                'family_tree' => (string) $userId,
                'mt4_code' => $userId,
                'mt4_group' => $mt4Group,
                'total_funds' => 0,
                'equity' => 0,
                'effective_credit' => 0,
                'created_at' => $now - 3600,
                'updated_at' => $now,
                'deleted_at' => null,
            ]
        );
    }
}
