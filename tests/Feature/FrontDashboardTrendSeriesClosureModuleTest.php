<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/28
 * Time: 00:57
 */

/**
 * FrontDashboardTrendSeriesClosureModuleTest
 *
 * 文件功能：
 * - 验证前台首页日粒度趋势序列：日期轴零填充连续且末位为今天、入出金按真实 DB 行分桶不串用户、序列合计与汇总口径一致、两份 Layui 首页四张趋势图与双语标题。
 * - 输入：HTTP 请求与事务回滚的数据库夹具；输出：PHPUnit 断言结果。
 * - 明确不负责：不覆盖真实 MT4 网关与线上支付/出金通道（由网关契约测试锁定）。
 */

namespace Tests\Feature;

use App\Constants\ResponseCode;
use App\Services\JwtService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Support\CreatesLegacyFrontUserFixture;
use Tests\TestCase;

/**
 * 锁定前台首页日粒度趋势序列（/api/front/dashboard 的 series 字段）与四张趋势图前端契约。
 *
 * 覆盖点：
 * - 日期轴长度等于统计天数、零填充连续、末位为今天；
 * - 入金/出金按真实 DB 行分桶到正确日期，且不串用户；
 * - 序列合计与首页汇总指标口径一致；
 * - 两份 Layui 首页 Blade 均输出四张趋势图与四种查看方式；
 * - 切换查看方式复用快照，不重复请求接口。
 */
class FrontDashboardTrendSeriesClosureModuleTest extends TestCase
{
    use DatabaseTransactions;
    use CreatesLegacyFrontUserFixture;

    /**
     * 夹具登录用户 ID。趋势序列用例以它构造资金流水样本。
     * @var int
     */
    private $userId;

    /**
     * 登录成功后缓存的 JWT。后续带鉴权的仪表盘请求都携带它。
     * @var string
     */
    private $token;

    protected function setUp(): void
    {
        parent::setUp();
        $this->userId = random_int(370000000, 379999999);
        $login = $this->createLegacyFrontUserFixture($this->userId, 2, 'Dashboard Trend Fixture');
        $this->token = app(JwtService::class)->generateToken([
            'sub' => $login->getAuthIdentifier(),
            'guard' => 'user',
        ]);
    }

    public function test_series_date_axis_is_zero_filled_and_ends_today(): void
    {
        $response = $this->withToken($this->token)
            ->getJson('/api/front/dashboard?days=7')
            ->assertOk()
            ->assertJsonPath('code', ResponseCode::SUCCESS);

        $series = $response->json('data.series');

        $this->assertIsArray($series);
        $this->assertCount(7, $series['dates']);
        $this->assertSame(date('Y-m-d'), $series['dates'][6], '日期轴末位必须是今天。');
        $this->assertSame(date('Y-m-d', strtotime('today') - 6 * 86400), $series['dates'][0]);

        foreach (['deposit', 'withdraw', 'commission', 'open_orders', 'closed_orders', 'profit'] as $metric) {
            $this->assertArrayHasKey($metric, $series, $metric . ' 序列缺失。');
            $this->assertCount(7, $series[$metric], $metric . ' 序列长度必须与日期轴一致。');
        }
    }

    public function test_series_respects_requested_window_length(): void
    {
        foreach ([7, 15, 30] as $days) {
            $series = $this->withToken($this->token)
                ->getJson('/api/front/dashboard?days=' . $days)
                ->assertOk()
                ->json('data.series');

            $this->assertCount($days, $series['dates'], $days . ' 天窗口日期轴长度错误。');
            $this->assertCount($days, $series['deposit'], $days . ' 天窗口入金序列长度错误。');
        }
    }

    public function test_deposit_and_withdraw_land_on_their_own_day_buckets(): void
    {
        $this->insertDeposit('TREND-IN', '120.00', strtotime('today') - 2 * 86400 + 3600);
        $this->insertWithdraw('45.00', strtotime('today') - 4 * 86400 + 3600);

        $series = $this->withToken($this->token)
            ->getJson('/api/front/dashboard?days=7')
            ->assertOk()
            ->json('data.series');

        $depositIndex = array_search(date('Y-m-d', strtotime('today') - 2 * 86400), $series['dates'], true);
        $withdrawIndex = array_search(date('Y-m-d', strtotime('today') - 4 * 86400), $series['dates'], true);

        $this->assertIsInt($depositIndex);
        $this->assertIsInt($withdrawIndex);
        $this->assertSame(120.0, (float) $series['deposit'][$depositIndex]);
        $this->assertSame(45.0, (float) $series['withdraw'][$withdrawIndex]);

        // 其他日必须保持零填充，不允许把金额摊到相邻日期。
        $this->assertSame(0.0, (float) $series['deposit'][$withdrawIndex]);
        $this->assertSame(0.0, (float) $series['withdraw'][$depositIndex]);
    }

    public function test_series_deposit_total_matches_period_summary(): void
    {
        $this->insertDeposit('TREND-SUM-A', '30.00', strtotime('today') - 1 * 86400 + 3600);
        $this->insertDeposit('TREND-SUM-B', '70.00', strtotime('today') - 3 * 86400 + 3600);

        $payload = $this->withToken($this->token)
            ->getJson('/api/front/dashboard?days=7')
            ->assertOk()
            ->json('data');

        $seriesTotal = array_sum(array_map('floatval', $payload['series']['deposit']));

        $this->assertSame(100.0, $seriesTotal);
        $this->assertSame(
            (float) $payload['stats']['monthly_deposit'],
            $seriesTotal,
            '趋势序列合计必须与首页周期汇总同口径。'
        );
    }

    public function test_series_excludes_rows_outside_window_and_other_users(): void
    {
        $this->insertDeposit('TREND-OLD', '500.00', strtotime('today') - 9 * 86400);
        $this->insertForeignDeposit('900.00', strtotime('today') - 1 * 86400 + 3600);

        $series = $this->withToken($this->token)
            ->getJson('/api/front/dashboard?days=7')
            ->assertOk()
            ->json('data.series');

        $this->assertSame(0.0, array_sum(array_map('floatval', $series['deposit'])));
    }

    public function test_both_layui_dashboards_expose_four_trend_charts_with_all_view_modes(): void
    {
        foreach ([
            'resources/front/layui/dashboard/index.blade.php',
            'resources/front/layui/dashboard/index_v2.blade.php',
        ] as $path) {
            $blade = file_get_contents(base_path($path)) ?: '';

            foreach (['flowTrendChart', 'orderTrendChart', 'profitTrendChart', 'commissionTrendChart'] as $chart) {
                $this->assertStringContainsString('id="' . $chart . '"', $blade, $path . ' 缺少趋势图容器 ' . $chart);
                foreach (['bar', 'line', 'area', 'pie'] as $type) {
                    $this->assertStringContainsString(
                        'data-chart-target="' . $chart . '" data-chart-type="' . $type . '"',
                        $blade,
                        $path . ' 的 ' . $chart . ' 缺少 ' . $type . ' 查看方式。'
                    );
                }
            }

            foreach ([
                'front.flow_trend_chart',
                'front.order_trend_chart',
                'front.profit_trend_chart',
                'front.commission_trend_chart',
            ] as $key) {
                $this->assertStringContainsString($key, $blade, $path . ' 趋势图标题必须使用多语言 key ' . $key);
            }
        }
    }

    public function test_trend_chart_titles_exist_in_both_locales(): void
    {
        foreach (['zh-CN', 'en'] as $locale) {
            $strings = require base_path('resources/lang/' . $locale . '/front.php');

            foreach ([
                'flow_trend_chart',
                'order_trend_chart',
                'profit_trend_chart',
                'commission_trend_chart',
            ] as $key) {
                $this->assertArrayHasKey($key, $strings, $locale . ' 缺少 ' . $key);
                $this->assertNotSame('', trim((string) $strings[$key]));
            }
        }
    }

    public function test_view_mode_switch_reuses_the_series_snapshot_without_refetching(): void
    {
        $script = file_get_contents(public_path('js/apps/front/layui/pages.js')) ?: '';

        $this->assertStringContainsString('function renderTrendCharts(series)', $script);
        $this->assertStringContainsString('function multiSeriesOption(dates, seriesList, type)', $script);
        $this->assertStringContainsString('lastChartSeries = data.series || null;', $script);
        $this->assertStringContainsString('renderTrendCharts(lastChartSeries);', $script);

        foreach (['flowTrendChart', 'orderTrendChart', 'profitTrendChart', 'commissionTrendChart'] as $chart) {
            $this->assertStringContainsString($chart, $script, 'pages.js 未注册趋势图 ' . $chart);
        }
    }

    private function insertDeposit(string $orderNo, string $amount, int $createdAt): void
    {
        $this->insertDepositRow($this->userId, $orderNo, $amount, $createdAt);
    }

    private function insertForeignDeposit(string $amount, int $createdAt): void
    {
        $foreignId = $this->userId + 1;
        $this->createLegacyFrontUserFixture($foreignId, 2, 'Dashboard Trend Foreign');
        $this->insertDepositRow($foreignId, 'TREND-FOREIGN', $amount, $createdAt);
    }

    private function insertDepositRow(int $userId, string $orderNo, string $amount, int $createdAt): void
    {
        DB::table('deposit_records')->insert([
            'user_id' => $userId,
            'user_name' => 'dashboard-trend-' . $userId,
            'mt4_ticket' => $userId,
            'amount' => $amount,
            'actual_amount' => $amount,
            'exchange_rate' => 1,
            'channel_name' => 'phpunit',
            'channel_order_no' => 'CH-' . $orderNo . '-' . $userId,
            'local_order_no' => $orderNo . '-' . $userId,
            'status' => '02',
            'payment_time' => date('Y-m-d H:i:s', $createdAt),
            'remarks' => 'dashboard trend closure test',
            'created_by' => 'phpunit',
            'updated_by' => 'phpunit',
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ]);
    }

    private function insertWithdraw(string $amount, int $createdAt): void
    {
        DB::table('withdraw_records')->insert([
            'user_id' => $this->userId,
            'user_name' => 'dashboard-trend-' . $this->userId,
            'mt4_ticket' => (string) $this->userId,
            'apply_amount' => $amount,
            'fee' => '0.00',
            'actual_amount' => $amount,
            'status' => 2,
            'local_order_no' => 'TREND-OUT-' . $this->userId,
            'created_by' => 'phpunit',
            'updated_by' => 'phpunit',
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ]);
    }
}
