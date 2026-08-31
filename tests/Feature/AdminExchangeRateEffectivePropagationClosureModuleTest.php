<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/30
 * Time: 17:05
 */

/**
 * AdminExchangeRateEffectivePropagationClosureModuleTest
 *
 * 文件功能：
 * - 锁定后台汇率页保存后，汇率会同步到三条真实生效链路，而不是只写旧字段名键。
 * - 缺陷背景：sys_deposit_rate / sys_draw_rate 只是项目1 的旧字段名，
 *   新项目没有任何结算代码读取它们；出金结算读 withdraw_exchange_rate_cny，
 *   入金结算读 payment_channels.exchange_rate，入金页展示读 deposit_exchange_rate_cny。
 *   只写旧键会造成「后台改了汇率但资金仍按旧汇率结算」，且页面看不出异常。
 * - 联动范围复刻项目1 ExchangeRateController::whpj_rate_save()：
 *   法币通道（1,2,3,6,7,8,9,10,11）全部跟随同一入金汇率；
 *   加密货币与数字货币通道（4,5）不参与联动，保持原值。
 * - 输入：后台汇率保存接口 + system_configs / payment_channels 真实表；输出：PHPUnit 断言结果。
 * - 明确不负责：不覆盖汇率页渲染与权限链路（由 AdminExchangeRateModuleTest 锁定），
 *   也不覆盖出金结算本身的定点算术（由出金结算族测试锁定）。
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
 * 后台汇率保存 → 生效链路同步闭环测试。
 */
class AdminExchangeRateEffectivePropagationClosureModuleTest extends TestCase
{
    /**
     * 出金结算实际读取的配置键。WithdrawalOrderService 用它计算 rmb_fee 与本币出金额。
     *
     * @var string
     */
    private const EFFECTIVE_DRAW_KEY = 'withdraw_exchange_rate_cny';

    /**
     * 入金页展示读取的配置键。Front\DepositController 用它渲染 exchange_rates.CNY。
     *
     * @var string
     */
    private const EFFECTIVE_DEPOSIT_KEY = 'deposit_exchange_rate_cny';

    /**
     * 跟随入金汇率联动的法币通道 ID。取自项目1 whpj_rate_save() 的联动字段清单。
     *
     * @var array<int, int>
     */
    private const LINKED_CHANNEL_IDS = [1, 2, 3, 6, 7, 8, 9, 10, 11];

    /**
     * 不参与联动的加密货币/数字货币通道 ID。项目1 将其固定为 1.0，不做本币换算。
     *
     * @var array<int, int>
     */
    private const UNLINKED_CHANNEL_IDS = [4, 5];

    /**
     * 保存汇率必须同时写入旧契约键与两个生效配置键。
     *
     * @return void
     */
    public function test_saving_rates_writes_both_legacy_and_effective_config_keys(): void
    {
        $this->submitRates('7.31', '6.42');

        // 旧字段名键：供旧表单与旧 API 回填，必须保留。
        $this->assertSame('7.31', $this->configValue('sys_deposit_rate'));
        $this->assertSame('6.42', $this->configValue('sys_draw_rate'));

        // 生效键：出金结算与入金页展示各自依赖，缺一即造成口径脱节。
        $this->assertSame('7.31', $this->configValue(self::EFFECTIVE_DEPOSIT_KEY));
        $this->assertSame('6.42', $this->configValue(self::EFFECTIVE_DRAW_KEY));
    }

    /**
     * 保存入金汇率必须批量刷新法币通道的 exchange_rate（入金结算的权威来源）。
     *
     * @return void
     */
    public function test_saving_deposit_rate_propagates_to_fiat_payment_channels(): void
    {
        // 自建夹具而非依赖库内既有通道：测试库的 payment_channels 可能为空或 ID 不同，
        // 若靠「行不存在就跳过」的写法，断言会全部被绕过而空转通过——这类假绿比缺陷本身更危险。
        $this->ensureChannelFixture();

        $this->submitRates('7.77', '6.55');

        $verified = 0;
        foreach (self::LINKED_CHANNEL_IDS as $channelId) {
            $rate = DB::table('payment_channels')->where('id', $channelId)->value('exchange_rate');
            $this->assertNotNull($rate, "联动通道 {$channelId} 夹具缺失，断言无法生效");
            $this->assertSame(
                '7.77',
                $this->trimRate((string) $rate),
                "法币通道 {$channelId} 未跟随入金汇率联动"
            );
            $verified++;
        }

        // 守卫：确保本用例真的校验过全部联动通道，杜绝空转。
        $this->assertSame(count(self::LINKED_CHANNEL_IDS), $verified);
    }

    /**
     * 加密货币与数字货币通道不得被入金汇率覆盖。
     *
     * 项目1 的联动清单不含 sys_deposit_rate4/5，seeder 将其固定为 1.0；
     * 若这两个通道被拉平成法币汇率，加密货币入金金额会被错误放大约 7 倍。
     *
     * @return void
     */
    public function test_crypto_channels_are_excluded_from_deposit_rate_linkage(): void
    {
        $this->ensureChannelFixture();

        $before = [];
        foreach (self::UNLINKED_CHANNEL_IDS as $channelId) {
            $before[$channelId] = DB::table('payment_channels')->where('id', $channelId)->value('exchange_rate');
            $this->assertNotNull($before[$channelId], "非联动通道 {$channelId} 夹具缺失，断言无法生效");
        }

        $this->submitRates('8.88', '6.11');

        $verified = 0;
        foreach (self::UNLINKED_CHANNEL_IDS as $channelId) {
            $after = DB::table('payment_channels')->where('id', $channelId)->value('exchange_rate');
            $this->assertSame(
                $this->trimRate((string) $before[$channelId]),
                $this->trimRate((string) $after),
                "非联动通道 {$channelId} 被入金汇率错误覆盖"
            );
            $this->assertNotSame('8.88', $this->trimRate((string) $after));
            $verified++;
        }

        $this->assertSame(count(self::UNLINKED_CHANNEL_IDS), $verified);
    }

    /**
     * 确保联动与非联动通道在测试库中都存在，且初值与目标汇率不同。
     *
     * 初值刻意设为与用例提交值不同的 1.2345（非联动通道设 1.0），
     * 这样「实现没有联动」时断言必然失败，避免夹具初值恰好等于目标值造成假绿。
     *
     * @return void
     */
    private function ensureChannelFixture(): void
    {
        $now = time();

        foreach (self::LINKED_CHANNEL_IDS as $channelId) {
            $this->upsertChannel($channelId, '1.2345', $now);
        }
        foreach (self::UNLINKED_CHANNEL_IDS as $channelId) {
            $this->upsertChannel($channelId, '1.0000', $now);
        }
    }

    /**
     * 按主键写入或重置一个支付通道夹具行。
     *
     * @param int $channelId 通道主键，与项目1 的 PAYMENT_CHANNEL_N 编号一致。
     * @param string $rate 该通道的初始汇率。
     * @param int $now 10 位时间戳，用于 created_at/updated_at。
     * @return void
     */
    private function upsertChannel(int $channelId, string $rate, int $now): void
    {
        $exists = DB::table('payment_channels')->where('id', $channelId)->exists();

        if ($exists) {
            DB::table('payment_channels')->where('id', $channelId)->update([
                'exchange_rate' => $rate,
                'updated_at' => $now,
            ]);

            return;
        }

        DB::table('payment_channels')->insert([
            'id' => $channelId,
            'name' => 'Rate Fixture Channel ' . $channelId,
            'channel_code' => 'rate_fixture_' . $channelId,
            'exchange_rate' => $rate,
            'is_enabled' => 1,
            'sort' => $channelId,
            'config' => json_encode(['legacy_para_name' => 'PAYMENT_CHANNEL_' . $channelId]),
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    /**
     * 校验失败时不得产生任何部分写入。
     *
     * 四个键加通道批量更新必须在同一事务内，否则「写了一半」会让展示汇率与结算汇率长期脱节。
     *
     * @return void
     */
    public function test_validation_failure_leaves_all_rate_sources_untouched(): void
    {
        $this->submitRates('7.10', '6.20');

        $depositBefore = $this->configValue(self::EFFECTIVE_DEPOSIT_KEY);
        $drawBefore = $this->configValue(self::EFFECTIVE_DRAW_KEY);
        $channelBefore = (string) DB::table('payment_channels')->where('id', 1)->value('exchange_rate');

        // 入金汇率为 0 违反 min:0.000001，必须整体拒绝。
        $this->rateRequest()
            ->postJson('/api/admin/updateExchangeRate', [
                'sys_deposit_rate' => '0',
                'sys_draw_rate' => '6.99',
            ])
            ->assertOk()
            ->assertJsonPath('code', \App\Constants\ResponseCode::VALIDATION_FAILED);

        $this->assertSame($depositBefore, $this->configValue(self::EFFECTIVE_DEPOSIT_KEY));
        $this->assertSame($drawBefore, $this->configValue(self::EFFECTIVE_DRAW_KEY));
        $this->assertSame(
            $this->trimRate($channelBefore),
            $this->trimRate((string) DB::table('payment_channels')->where('id', 1)->value('exchange_rate'))
        );
    }

    /**
     * 控制器必须显式声明生效键与联动通道白名单，防止后续重构悄悄退回只写旧键。
     *
     * @return void
     */
    public function test_controller_declares_effective_keys_and_linked_channels(): void
    {
        $source = file_get_contents(app_path('Http/Controllers/Admin/ExchangeRateController.php')) ?: '';

        $this->assertStringContainsString("EFFECTIVE_DEPOSIT_RATE_KEY = 'deposit_exchange_rate_cny'", $source);
        $this->assertStringContainsString("EFFECTIVE_DRAW_RATE_KEY = 'withdraw_exchange_rate_cny'", $source);
        $this->assertStringContainsString('DEPOSIT_LINKED_CHANNEL_IDS = [1, 2, 3, 6, 7, 8, 9, 10, 11]', $source);
        // 必须在事务内写，避免部分写入造成展示与结算脱节。
        $this->assertStringContainsString('DB::transaction(', $source);
    }

    /**
     * 提交汇率保存请求并断言成功。
     *
     * @param string $depositRate 入金汇率。
     * @param string $drawRate 出金汇率。
     * @return void
     */
    private function submitRates(string $depositRate, string $drawRate): void
    {
        $this->rateRequest()
            ->postJson('/api/admin/updateExchangeRate', [
                'sys_deposit_rate' => $depositRate,
                'sys_draw_rate' => $drawRate,
            ])
            ->assertOk()
            ->assertJsonPath('code', \App\Constants\ResponseCode::UPDATED);
    }

    /**
     * 构造旁路鉴权中间件的后台请求。
     *
     * 本用例只验证汇率传播口径，不重复验证鉴权链路（由 AdminExchangeRateModuleTest 锁定）。
     *
     * @return $this
     */
    private function rateRequest()
    {
        $admin = Admin::query()->findOrFail(1);

        return $this
            ->withoutMiddleware([
                AdminAuthenticate::class,
                JwtAuthMiddleware::class,
                SingleSignOn::class,
                CheckPermission::class,
            ])
            ->actingAs($admin, 'admin');
    }

    /**
     * 读取 system_configs 中指定键的值。
     *
     * @param string $key 配置键名。
     * @return string 配置值；不存在时返回空字符串。
     */
    private function configValue(string $key): string
    {
        return (string) DB::table('system_configs')->where('key', $key)->value('value');
    }

    /**
     * 去除 DECIMAL 列取回后的无意义尾零，便于与页面提交的字符串直接比较。
     *
     * payment_channels.exchange_rate 是 DECIMAL 列，7.77 取回可能是 '7.7700'；
     * 直接字符串比较会误报，因此统一归一到最短等值表示。
     *
     * @param string $rate 原始汇率字符串。
     * @return string 去尾零后的汇率字符串。
     */
    private function trimRate(string $rate): string
    {
        if (strpos($rate, '.') === false) {
            return $rate;
        }

        return rtrim(rtrim($rate, '0'), '.');
    }
}
