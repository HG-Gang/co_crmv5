<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/30
 * Time: 15:20
 */

/**
 * AdminProductionAvgPriceParityClosureModuleTest
 *
 * 文件功能：
 * - 锁定后台产量报表的「买入均价 / 卖出均价」两列与旧项目逐位等价。
 * - 旧行为参照：项目1 app/Http/Controllers/Admin/AdminProductionController.php
 *   get_mt4_trades_production_summary() 第 229 行与第 237 行：
 *     avg_buy_price  = round(SUM(OPEN_PRICE  WHERE cmd=0) / COUNT(cmd=0), 2)
 *     avg_sell_price = round(SUM(CLOSE_PRICE WHERE cmd=1) / COUNT(cmd=1), 2)
 *   注意两侧取价字段**故意不对称**（买单取开仓价、卖单取平仓价），这是旧项目既有行为，
 *   本测试显式锁定该不对称，防止后续「顺手改成同源字段」导致与旧报表数值不一致。
 * - 输入：symbol_prices 与 mt4_trades 真实表夹具 + 后台产量列表 API；输出：PHPUnit 断言结果。
 * - 明确不负责：不覆盖产量页的行情字段（bid/ask/spread）与品种维护写接口（由 AdminProductionModuleTest 锁定）。
 */

namespace Tests\Feature;

use App\Http\Middleware\AdminAuthenticate;
use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\JwtAuthMiddleware;
use App\Http\Middleware\SingleSignOn;
use App\Models\Admin;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * 产量报表买入/卖出均价旧新等价闭环测试。
 */
class AdminProductionAvgPriceParityClosureModuleTest extends TestCase
{
    /**
     * 本夹具使用的品种名前缀。断言与清理都按该前缀圈定，
     * 避免与其它用例或既有 symbol_prices 数据互相污染。
     *
     * @var string
     */
    private const SYMBOL_PREFIX = 'AVGPX';

    /**
     * 夹具 MT4 订单号起始值。取一个远离既有数据的高位区间，
     * 防止与其它用例的 ticket 唯一索引冲突。
     *
     * @var int
     */
    private const TICKET_BASE = 970310000;

    protected function setUp(): void
    {
        parent::setUp();
        $this->cleanupFixture();
    }

    protected function tearDown(): void
    {
        $this->cleanupFixture();
        parent::tearDown();
    }

    /**
     * 买入均价必须等于买单开仓价均值，卖出均价必须等于卖单平仓价均值。
     *
     * 夹具设计（数值刻意选成整除，便于人工核对）：
     * - 买单两笔：open_price 100 与 200，close_price 故意设为 999（若实现误用 close_price 会立刻暴露）
     *   期望 avg_buy_price = (100+200)/2 = 150.00
     * - 卖单两笔：close_price 300 与 500，open_price 故意设为 111（若实现误用 open_price 会立刻暴露）
     *   期望 avg_sell_price = (300+500)/2 = 400.00
     *
     * @return void
     */
    public function test_avg_prices_follow_legacy_asymmetric_price_fields(): void
    {
        $symbol = self::SYMBOL_PREFIX . 'ONE';
        $this->seedSymbol($symbol);

        // 买单：只有 open_price 参与均价；close_price 设成异常大值作为反向探针。
        $this->seedTrade($symbol, 0, '100.00000', '999.00000', 1);
        $this->seedTrade($symbol, 0, '200.00000', '999.00000', 2);
        // 卖单：只有 close_price 参与均价；open_price 设成异常值作为反向探针。
        $this->seedTrade($symbol, 1, '111.00000', '300.00000', 3);
        $this->seedTrade($symbol, 1, '111.00000', '500.00000', 4);

        $row = $this->fetchProductionRow($symbol);

        $this->assertSame('150.00', $this->money($row['avg_buy_price']));
        $this->assertSame('400.00', $this->money($row['avg_sell_price']));
    }

    /**
     * 没有对应方向持仓时均价必须为 0.00，不能因除零而报错或返回 null。
     *
     * 旧逻辑把 $_avg_buy_price 初始化为 0.00 且仅在有该方向记录时覆盖，
     * 因此「无买单」与「无卖单」都应落到 0.00。
     *
     * @return void
     */
    public function test_avg_prices_fall_back_to_zero_without_matching_direction(): void
    {
        $symbol = self::SYMBOL_PREFIX . 'TWO';
        $this->seedSymbol($symbol);

        // 只有卖单：买入均价应为 0.00，卖出均价应为该卖单的 close_price。
        $this->seedTrade($symbol, 1, '111.00000', '250.00000', 11);

        $row = $this->fetchProductionRow($symbol);

        $this->assertSame('0.00', $this->money($row['avg_buy_price']));
        $this->assertSame('250.00', $this->money($row['avg_sell_price']));

        $onlyBuySymbol = self::SYMBOL_PREFIX . 'THREE';
        $this->seedSymbol($onlyBuySymbol);
        $this->seedTrade($onlyBuySymbol, 0, '80.00000', '999.00000', 12);

        $buyRow = $this->fetchProductionRow($onlyBuySymbol);

        $this->assertSame('80.00', $this->money($buyRow['avg_buy_price']));
        $this->assertSame('0.00', $this->money($buyRow['avg_sell_price']));
    }

    /**
     * 完全没有持仓的品种，两个均价都必须是 0.00 且接口不报错。
     *
     * 这条覆盖 leftJoin 后聚合列全为 NULL 的路径：
     * 若实现漏了 COALESCE，均价会返回 null 并让前端显示空白而不是 0.00。
     *
     * @return void
     */
    public function test_avg_prices_are_zero_for_symbol_without_any_trade(): void
    {
        $symbol = self::SYMBOL_PREFIX . 'FOUR';
        $this->seedSymbol($symbol);

        $row = $this->fetchProductionRow($symbol);

        $this->assertSame('0.00', $this->money($row['avg_buy_price']));
        $this->assertSame('0.00', $this->money($row['avg_sell_price']));
    }

    /**
     * 已平仓单不得参与均价计算，口径与同行的手数/浮动盈亏列保持一致。
     *
     * 产量报表统计的是当前未平仓持仓；若把已平仓单计入，
     * 均价会随历史成交漂移，与旧后台的「当前持仓均价」语义不符。
     *
     * @return void
     */
    public function test_closed_trades_are_excluded_from_avg_prices(): void
    {
        $symbol = self::SYMBOL_PREFIX . 'FIVE';
        $this->seedSymbol($symbol);

        // 未平仓买单：参与计算。
        $this->seedTrade($symbol, 0, '100.00000', '999.00000', 21);
        // 已平仓买单：close_time 非空且非 0，必须被排除；若被计入均价会变成 (100+900)/2=500.00。
        $this->seedTrade($symbol, 0, '900.00000', '999.00000', 22, 1735689600);

        $row = $this->fetchProductionRow($symbol);

        $this->assertSame('100.00', $this->money($row['avg_buy_price']));
    }

    /**
     * 两套后台 UI 都必须展示这两列，避免出现「后端已算、某一家族看不到」的家族间不一致。
     *
     * @return void
     */
    public function test_both_admin_families_expose_avg_price_columns(): void
    {
        // Layui 家族的列定义在共享 pages.js 的 productions 注册块内。
        $script = file_get_contents(public_path('js/apps/admin/layui/pages.js')) ?: '';
        $this->assertStringContainsString("{field: 'avg_buy_price'", $script);
        $this->assertStringContainsString("{field: 'avg_sell_price'", $script);

        // CrmUI 家族的列由 PageController 的 productions definition 下发并渲染成表头。
        $html = $this->get('/admin-crmui/productions')->assertOk()->getContent();
        $this->assertStringContainsString('data-key="avg_buy_price"', $html);
        $this->assertStringContainsString('data-key="avg_sell_price"', $html);
        $this->assertStringContainsString('data-key="total_buy_volume"', $html);
        $this->assertStringContainsString('data-key="net_volume"', $html);
        $this->assertStringContainsString('data-key="float_profit_loss"', $html);
    }

    /**
     * 两个均价的中英文列名必须齐备，缺键会让页面直接显示原始 key。
     *
     * @return void
     */
    public function test_avg_price_language_keys_exist_in_both_locales(): void
    {
        foreach (['zh-CN', 'en'] as $locale) {
            foreach (['admin.avg_buy_price', 'admin.avg_sell_price', 'crmui.fields.avg_buy_price', 'crmui.fields.avg_sell_price'] as $key) {
                $value = __($key, [], $locale);
                $this->assertNotSame($key, $value, "缺少语言键 {$key}（{$locale}）");
                $this->assertNotSame('', trim((string) $value), "语言键为空 {$key}（{$locale}）");
            }
        }
    }

    /**
     * 调用后台产量列表 API 并取出指定品种所在行。
     *
     * @param string $symbol 品种名，用作接口 symbol 模糊筛选条件。
     * @return array<string, mixed> 该品种对应的列表行。
     */
    private function fetchProductionRow(string $symbol): array
    {
        // 与既有产量 API 测试一致：本用例只验证聚合口径，不重复验证鉴权链路，
        // 因此按项目惯例旁路 JWT/SSO/权限中间件（鉴权由 AdminProductionModuleTest 单独锁定）。
        $admin = Admin::query()->findOrFail(1);
        $response = $this
            ->withoutMiddleware([
                AdminAuthenticate::class,
                JwtAuthMiddleware::class,
                SingleSignOn::class,
                CheckPermission::class,
            ])
            ->actingAs($admin, 'admin')
            ->postJson('/api/admin/productionList', ['symbol' => $symbol, 'per_page' => 50])
            ->assertOk();

        $rows = data_get($response->json(), 'data.records.data', []);
        $this->assertIsArray($rows);

        foreach ($rows as $row) {
            if ((string) ($row['symbol'] ?? '') === $symbol) {
                return $row;
            }
        }

        $this->fail('产量列表未返回夹具品种：' . $symbol);
    }

    /**
     * 把接口返回值归一为两位小数字符串，屏蔽 DECIMAL 与 float 的表现差异。
     *
     * @param mixed $value 接口返回的均价原始值。
     * @return string 两位小数定点字符串。
     */
    private function money($value): string
    {
        return number_format((float) $value, 2, '.', '');
    }

    /**
     * 写入一个夹具品种行。
     *
     * @param string $symbol 品种名。
     * @return void
     */
    private function seedSymbol(string $symbol): void
    {
        // symbol_prices 的 time / modify_time 是 dateTime 列，created_at / updated_at 是 10 位整数时间戳，
        // 两种时间口径不能混用，否则 MySQL 会以 1292 Incorrect datetime value 直接拒绝插入。
        $now = time();
        $nowDateTime = date('Y-m-d H:i:s', $now);

        DB::table('symbol_prices')->insert([
            'symbol' => $symbol,
            'time' => $nowDateTime,
            'bid' => 1.0,
            'ask' => 1.0,
            'low' => 1.0,
            'high' => 1.0,
            'direction' => 0,
            'digits' => 5,
            'spread' => 0,
            'group_id' => 0,
            'status' => 1,
            'modify_time' => $nowDateTime,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    /**
     * 写入一笔夹具 MT4 持仓单。
     *
     * @param string $symbol 品种名，与 symbol_prices.symbol 关联。
     * @param int $cmd 方向：0=买入，1=卖出。
     * @param string $openPrice 开仓价，买入均价的唯一来源。
     * @param string $closePrice 平仓价，卖出均价的唯一来源。
     * @param int $seq 序号，用于生成互不冲突的 ticket 与 login。
     * @param int|null $closeTime 平仓时间；null 表示未平仓，非空且非 0 表示已平仓需被排除。
     * @return void
     */
    private function seedTrade(
        string $symbol,
        int $cmd,
        string $openPrice,
        string $closePrice,
        int $seq,
        ?int $closeTime = null
    ): void {
        DB::table('mt4_trades')->insert([
            'ticket' => self::TICKET_BASE + $seq,
            'login' => self::TICKET_BASE + $seq,
            'symbol' => $symbol,
            'cmd' => $cmd,
            'volume' => 100,
            'open_price' => $openPrice,
            'close_price' => $closePrice,
            'commission' => 0,
            'swaps' => 0,
            'profit' => 0,
            'open_time' => time(),
            'close_time' => $closeTime,
            'created_at' => time(),
            'updated_at' => time(),
        ]);
    }

    /**
     * 清理本夹具写入的品种与持仓单，按前缀与 ticket 区间圈定，不影响其它数据。
     *
     * @return void
     */
    private function cleanupFixture(): void
    {
        DB::table('mt4_trades')->where('symbol', 'LIKE', self::SYMBOL_PREFIX . '%')->delete();
        DB::table('symbol_prices')->where('symbol', 'LIKE', self::SYMBOL_PREFIX . '%')->delete();
    }
}
