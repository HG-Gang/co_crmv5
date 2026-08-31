<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/28
 * Time: 01:35
 */

/**
 * AdminCustomerStatisticsModuleTest
 *
 * 文件功能：
 * - 验证客户统计模块：真实数据库口径的金额/订单/利润序列、decimal 字符串金额、新旧返佣比例兼容、userId 别名与缺失/未知用户拒绝、Blade 图表与双语 i18n。
 * - 输入：HTTP 请求与事务回滚的数据库夹具；输出：PHPUnit 断言结果。
 * - 明确不负责：不覆盖真实 MT4 网关与线上支付/出金通道（由网关契约测试锁定）。
 */

namespace Tests\Feature;

use App\Constants\ResponseCode;
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
 * 后台客户资料统计闭环测试（需求 13：点击用户名查看用户信息）。
 *
 * 文件目的：
 * - 锁定 admin_api_customerStatistics 的真实 DB 口径：出入金金额、返佣金额、返佣比例、
 *   开/关订单数与近 7/15/30 天盈亏，全部来自 deposit_records / withdraw_records /
 *   commission_records / user_infos.comm_rate / user_trades，不允许出现前端造数。
 * - 锁定金额一律以 BCMath 两位小数字符串返回，禁止退回 float。
 * - 锁定详情页 Blade 与 JS 使用已 vendored 的 ECharts、图表类型切换按钮和 data-translate 多语言属性。
 * - 参数校验与数据范围越权为防回归边界。
 */
class AdminCustomerStatisticsModuleTest extends TestCase
{
    use DatabaseTransactions;

    /** @var int 演示客户业务用户 ID。 */
    private const CUSTOMER_ID = 987201;

    public function test_customer_statistics_api_route_is_registered_with_permission_middleware(): void
    {
        $this->assertTrue(Route::has('admin_api_customerStatistics'), 'admin_api_customerStatistics 未注册。');
        $this->assertContains(
            'check.permission:admin',
            Route::getRoutes()->getByName('admin_api_customerStatistics')->gatherMiddleware()
        );
    }

    public function test_customer_statistics_returns_real_db_backed_amounts_orders_and_profit_series(): void
    {
        $admin = $this->ensureAdmin();
        $this->fixtureCustomer();

        $response = $this->actingAsAdmin($admin)
            ->post('/api/admin/customerStatistics', ['user_id' => self::CUSTOMER_ID]);

        $response->assertOk()->assertJsonPath('code', ResponseCode::SUCCESS);

        // 入金只统计 status='02'（审核通过）：1200.50 + 300.25 = 1500.75，被拒绝的 999.99 不计入。
        $response->assertJsonPath('data.total_deposit', '1500.75');
        // 出金只统计 status=2（已完成）：400.25，待处理的 100.00 不计入。
        $response->assertJsonPath('data.total_withdraw', '400.25');
        $response->assertJsonPath('data.net_flow', '1100.50');
        $response->assertJsonPath('data.total_rebate', '77.77');
        // user_infos.comm_rate 真实列是 int(11)，夹具写 25 表示 25%；
        // rebate_ratio 原样回传库里的值，rebate_ratio_percent 给页面展示用。
        $response->assertJsonPath('data.rebate_ratio', '25.0000');
        $response->assertJsonPath('data.rebate_ratio_percent', '25.00');
        $response->assertJsonPath('data.open_order_count', 1);
        $response->assertJsonPath('data.closed_order_count', 2);

        // 近 7 天只包含今天的 30.50；近 15/30 天再加上 20 天前的 10.25。
        $response->assertJsonPath('data.profit_7d', '30.50');
        $response->assertJsonPath('data.profit_15d', '30.50');
        $response->assertJsonPath('data.profit_30d', '40.75');

        $series = $response->json('data.profit_series');
        $this->assertSame(30, count($series['labels']), '按天序列必须覆盖 30 天。');
        $this->assertSame(count($series['labels']), count($series['values']), 'labels 与 values 长度必须一致。');
        $this->assertSame(date('Y-m-d'), $series['labels'][29], '序列必须按时间升序，最后一天是今天。');
        $this->assertSame('30.50', $series['values'][29]);
    }

    public function test_customer_statistics_money_fields_are_decimal_strings_not_floats(): void
    {
        $admin = $this->ensureAdmin();
        $this->fixtureCustomer();

        $payload = $this->actingAsAdmin($admin)
            ->post('/api/admin/customerStatistics', ['user_id' => self::CUSTOMER_ID])
            ->assertOk()
            ->json('data');

        foreach (['total_deposit', 'total_withdraw', 'net_flow', 'total_rebate', 'rebate_ratio', 'rebate_ratio_percent', 'profit_7d', 'profit_15d', 'profit_30d'] as $field) {
            $this->assertIsString($payload[$field], $field . ' 必须是十进制字符串，禁止 float。');
            $this->assertMatchesRegularExpression('/^-?\d+\.\d{2,4}$/', $payload[$field], $field);
        }

        foreach ($payload['profit_series']['values'] as $value) {
            $this->assertIsString($value, '按天盈亏序列必须是十进制字符串。');
        }
    }

    /**
     * 返佣比例必须同时兼容真实库里的两套历史口径。
     *
     * 口径说明：
     * - `user_infos.comm_rate` 是 int(11)，旧库 commprop 迁移进来的是百分数（85 -> 85%）。
     * - 但 AgentController::updateCommission 的写入校验是 0~1 小数；写进 int 列会被截断成 0 或 1。
     * - 因此 rebate_ratio_percent 对 >1 的值按百分数处理，对 <=1 的值按小数乘 100。
     *
     * @return void
     */
    public function test_rebate_ratio_percent_handles_both_legacy_percentage_and_fraction_conventions(): void
    {
        $admin = $this->ensureAdmin();
        $this->fixtureCustomer();

        // 旧库百分数口径：85 -> 85.00%
        DB::table('user_infos')->where('user_id', self::CUSTOMER_ID)->update(['comm_rate' => 85]);
        $this->actingAsAdmin($admin)
            ->post('/api/admin/customerStatistics', ['user_id' => self::CUSTOMER_ID])
            ->assertOk()
            ->assertJsonPath('data.rebate_ratio', '85.0000')
            ->assertJsonPath('data.rebate_ratio_percent', '85.00');

        // 0~1 小数口径：1 -> 100.00%
        DB::table('user_infos')->where('user_id', self::CUSTOMER_ID)->update(['comm_rate' => 1]);
        $this->actingAsAdmin($admin)
            ->post('/api/admin/customerStatistics', ['user_id' => self::CUSTOMER_ID])
            ->assertOk()
            ->assertJsonPath('data.rebate_ratio', '1.0000')
            ->assertJsonPath('data.rebate_ratio_percent', '100.00');

        // 未设置返佣：0 -> 0.00%
        DB::table('user_infos')->where('user_id', self::CUSTOMER_ID)->update(['comm_rate' => 0]);
        $this->actingAsAdmin($admin)
            ->post('/api/admin/customerStatistics', ['user_id' => self::CUSTOMER_ID])
            ->assertOk()
            ->assertJsonPath('data.rebate_ratio_percent', '0.00');
    }

    public function test_customer_statistics_rejects_missing_and_unknown_users(): void
    {
        $admin = $this->ensureAdmin();

        $this->actingAsAdmin($admin)
            ->post('/api/admin/customerStatistics', [])
            ->assertOk()
            ->assertJsonPath('code', ResponseCode::VALIDATION_FAILED);

        $this->actingAsAdmin($admin)
            ->post('/api/admin/customerStatistics', ['user_id' => 987299])
            ->assertOk()
            ->assertJsonPath('code', ResponseCode::USER_NOT_FOUND);
    }

    public function test_customer_statistics_accepts_legacy_userId_alias(): void
    {
        $admin = $this->ensureAdmin();
        $this->fixtureCustomer();

        $this->actingAsAdmin($admin)
            ->post('/api/admin/customerStatistics', ['userId' => self::CUSTOMER_ID])
            ->assertOk()
            ->assertJsonPath('code', ResponseCode::SUCCESS)
            ->assertJsonPath('data.user_id', self::CUSTOMER_ID);
    }

    public function test_customer_statistics_controller_uses_bcmath_and_decimal_aggregates(): void
    {
        $source = file_get_contents(app_path('Http/Controllers/Admin/CustomerStatisticsController.php')) ?: '';

        foreach ([
            'bcadd(',
            'bcsub(',
            'bcmul(',
            'CAST(SUM(CAST(profit AS DECIMAL(18,2))) AS DECIMAL(18,2))',
            'AdminDataScopeService',
            'canAccessUser',
        ] as $expected) {
            $this->assertStringContainsString($expected, $source, '客户统计控制器缺少：' . $expected);
        }

        $this->assertStringNotContainsString('number_format(', $source, '金额禁止走 number_format 的 float 路径。');
        $this->assertStringNotContainsString('(float)', $source, '金额禁止强转 float。');
    }

    public function test_customer_detail_blade_renders_statistics_block_with_echarts_and_i18n(): void
    {
        $blade = file_get_contents(resource_path('admin/layui/users/customer-detail.blade.php')) ?: '';

        $this->assertStringContainsString('data-customer-statistics', $blade);
        $this->assertStringContainsString('/js/vendor/echarts/echarts.common.min.js', $blade);
        $this->assertStringContainsString('id="customerProfitChart"', $blade);
        $this->assertStringContainsString('id="customerFundsChart"', $blade);

        // 需求 13 列出的每一项统计都必须有对应展示位。
        foreach ([
            'total_deposit',
            'total_withdraw',
            'total_rebate',
            'rebate_ratio_percent',
            'open_order_count',
            'closed_order_count',
            'profit_7d',
            'profit_15d',
            'profit_30d',
        ] as $field) {
            $this->assertStringContainsString('data-customer-stat="' . $field . '"', $blade, '缺少统计字段展示位：' . $field);
        }

        // 图表类型切换 + 多语言 + 无障碍：沿用前台控制台约定。
        foreach (['bar', 'line', 'area', 'pie'] as $type) {
            $this->assertStringContainsString('data-chart-type="' . $type . '"', $blade, '缺少图表类型按钮：' . $type);
        }
        foreach ([7, 15, 30] as $window) {
            $this->assertStringContainsString('data-customer-profit-window="' . $window . '"', $blade);
        }
        $this->assertStringContainsString('data-translate="admin.profit_trend"', $blade);
        $this->assertStringContainsString('crm-sr-only', $blade);
        $this->assertStringContainsString('aria-pressed', $blade);

        // 新增文案一律走语言包，不允许硬编码中英文字面量。
        $this->assertStringContainsString("__('admin.rebate_amount')", $blade);
        $this->assertStringContainsString("__('admin.rebate_ratio')", $blade);
    }

    public function test_customer_detail_script_reads_statistics_endpoint_and_switches_chart_types(): void
    {
        $script = file_get_contents(public_path('js/apps/admin/layui/users/customer-detail.js')) ?: '';

        $this->assertStringContainsString('customer-statistics-endpoint', $script);
        $this->assertStringContainsString('data-customer-stat', $script);
        $this->assertStringContainsString('echarts.init', $script);
        $this->assertStringContainsString('data-customer-profit-window', $script);
        $this->assertStringContainsString("aria-pressed", $script);
        $this->assertStringContainsString('requestAnimationFrame', $script, '图表重绘必须合并到下一帧，避免布局抖动。');
    }

    public function test_statistics_keys_exist_in_both_locales(): void
    {
        $zh = require resource_path('lang/zh-CN/admin.php');
        $en = require resource_path('lang/en/admin.php');

        foreach ([
            'customer_statistics',
            'deposit_withdraw_amount',
            'rebate_amount',
            'rebate_ratio',
            'net_flow',
            'profit_7d',
            'profit_15d',
            'profit_30d',
            'profit_trend',
            'profit_window',
            'chart_bar',
            'chart_line',
            'chart_area',
            'chart_pie',
            'chart_view_mode',
        ] as $key) {
            $this->assertArrayHasKey($key, $zh, 'zh-CN 缺少 admin.' . $key);
            $this->assertArrayHasKey($key, $en, 'en 缺少 admin.' . $key);
            $this->assertNotSame('', trim((string) $zh[$key]));
            $this->assertNotSame('', trim((string) $en[$key]));
        }
    }

    private function actingAsAdmin(Admin $admin)
    {
        return $this->withoutMiddleware([
            AdminAuthenticate::class,
            JwtAuthMiddleware::class,
            SingleSignOn::class,
            CheckPermission::class,
        ])->actingAs($admin, 'admin');
    }

    private function ensureAdmin(): Admin
    {
        $now = time();

        DB::table('admins')->updateOrInsert(
            ['id' => 1],
            [
                'username' => 'customer-statistics-admin',
                'email' => 'customer-statistics-admin@example.test',
                'password' => bcrypt('password'),
                'status' => 1,
                'created_at' => $now,
                'updated_at' => $now,
                'deleted_at' => null,
            ]
        );

        return Admin::query()->findOrFail(1);
    }

    /**
     * 写入客户统计夹具：入金、出金、返佣、持仓与平仓订单。
     *
     * 夹具口径说明：
     * - 入金写 3 条：两条 status='02'（计入合计），一条 status='09'（不计入）。
     * - 出金写 2 条：一条 status=2（计入合计），一条 status=0（不计入）。
     * - 订单写 3 条：1 条未平仓（close_time=1970-01-01），2 条已平仓（今天与 20 天前）。
     */
    private function fixtureCustomer(): void
    {
        $now = time();
        $userId = self::CUSTOMER_ID;

        DB::table('deposit_records')->where('user_id', $userId)->delete();
        DB::table('withdraw_records')->where('user_id', $userId)->delete();
        DB::table('commission_records')->where('agent_id', $userId)->delete();
        DB::table('user_trades')->where('user_id', $userId)->delete();
        DB::table('user_infos')->where('user_id', $userId)->delete();
        DB::table('user_logins')->where('user_id', $userId)->delete();

        $loginId = DB::table('user_logins')->insertGetId([
            'user_id' => $userId,
            'email' => 'customer-statistics-' . $userId . '@example.test',
            'password' => bcrypt('password'),
            'account_type' => 2,
            'is_enabled' => 1,
            'is_cancelled' => 0,
            'source_type' => 0,
            'created_at' => $now,
            'updated_at' => $now,
            'deleted_at' => null,
        ]);

        DB::table('user_infos')->insert([
            'user_id' => $userId,
            'login_id' => $loginId,
            'user_name' => 'Customer Statistics Demo',
            'phone' => '17800' . substr((string) $userId, -5),
            'account_type' => 2,
            'parent_id' => 0,
            'group_id' => 7,
            'level_id' => 0,
            // comm_rate 真实列是 int(11)：现网数据是旧库 commprop 搬过来的百分数，这里写 25 表示 25%。
            'comm_rate' => 25,
            'auth_status' => 1,
            'total_funds' => 0,
            'equity' => 0,
            'created_at' => $now,
            'updated_at' => $now,
            'deleted_at' => null,
        ]);

        foreach ([
            ['amount' => 1200.50, 'status' => '02'],
            ['amount' => 300.25, 'status' => '02'],
            ['amount' => 999.99, 'status' => '09'],
        ] as $index => $deposit) {
            DB::table('deposit_records')->insert([
                'user_id' => $userId,
                'user_name' => 'Customer Statistics Demo',
                'amount' => $deposit['amount'],
                'actual_amount' => $deposit['amount'],
                'exchange_rate' => 1,
                'status' => $deposit['status'],
                'local_order_no' => 'DEPSTAT' . $userId . $index,
                'created_at' => $now - 3600,
                'updated_at' => $now - 3600,
            ]);
        }

        foreach ([
            ['apply_amount' => 400.25, 'status' => 2],
            ['apply_amount' => 100.00, 'status' => 0],
        ] as $index => $withdraw) {
            DB::table('withdraw_records')->insert([
                'user_id' => $userId,
                'user_name' => 'Customer Statistics Demo',
                'apply_amount' => $withdraw['apply_amount'],
                'actual_amount' => $withdraw['apply_amount'],
                'fee' => 0,
                'exchange_rate' => 1,
                'status' => $withdraw['status'],
                'local_order_no' => 'WDRSTAT' . $userId . $index,
                'created_at' => $now - 3600,
                'updated_at' => $now - 3600,
            ]);
        }

        DB::table('commission_records')->insert([
            'unique_id' => 'COMMSTAT' . $userId,
            'agent_id' => $userId,
            'parent_id' => 0,
            'commission_amount' => 77.77,
            'settle_status' => 1,
            'created_at' => $now - 3600,
            'updated_at' => $now - 3600,
        ]);

        foreach ([
            ['ticket' => 970001, 'close_time' => '1970-01-01 00:00:00', 'profit' => 0],
            ['ticket' => 970002, 'close_time' => date('Y-m-d H:i:s', $now - 600), 'profit' => 30.50],
            ['ticket' => 970003, 'close_time' => date('Y-m-d H:i:s', $now - (20 * 86400)), 'profit' => 10.25],
        ] as $trade) {
            DB::table('user_trades')->insert([
                'user_id' => $userId,
                'ticket' => $trade['ticket'],
                'symbol' => 'XAUUSD',
                'digits' => 2,
                'cmd' => 0,
                'volume' => 100,
                'open_time' => date('Y-m-d H:i:s', $now - (30 * 86400)),
                'open_price' => 1800,
                'close_time' => $trade['close_time'],
                'close_price' => 1810,
                'profit' => $trade['profit'],
                'commission' => 0,
                'swaps' => 0,
                // user_trades 的 modify_time / expiration 无默认值，夹具必须显式写入。
                'modify_time' => $trade['close_time'],
                'expiration' => '1970-01-01 00:00:00',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }
}
