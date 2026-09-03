<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/31
 * Time: 21:40
 */

/**
 * AdminWithdrawalFeeSwitchClosureModuleTest
 *
 * 文件功能：
 * - 锁定出金手续费总开关 withdrawal_fee_enabled 的完整闭环：服务层定点算术、
 *   后台保存与回显、缺键降级、四套 UI 的提交契约。
 * - 缺陷背景：此前「是否扣手续费」只能靠把 withdrawal_fixed_fee_usd 与
 *   withdrawal_fee_rate 两个金额都改成 0 来实现，停收后原费率标准丢失、
 *   恢复时只能靠人工记忆，且无任何审计痕迹。开关把「是否扣」与「扣多少」
 *   拆成两个独立维度后，关闭时原值仍保留在 system_configs 中，重新开启即恢复。
 * - 为什么必须有本测试：该功能上一轮「代码已写完」但从未真正跑通过 ——
 *   ExchangeRateController 的 DB::transaction() 闭包漏了 use ($feeUpdates)，
 *   保存汇率直接 500。静态审计与「代码已落地」都无法暴露这类缺陷，
 *   只有端到端提交 + 定点算术断言才能锁定。
 * - 输入：system_configs 真实表 + 后台汇率保存/回显接口 + 服务层私有算术方法；
 *   输出：PHPUnit 断言结果。
 * - 明确不负责：不覆盖出金资格校验、余额快照与出金状态机（由出金结算族测试锁定），
 *   也不覆盖汇率向支付通道的联动（由 AdminExchangeRateEffectivePropagation 测试锁定）。
 */

namespace Tests\Feature;

use App\Constants\ResponseCode;
use App\Contracts\WithdrawalAccountSnapshotGateway;
use App\Http\Controllers\Admin\ExchangeRateController;
use App\Http\Controllers\CrmUi\Admin\PageController;
use App\Http\Middleware\AdminAuthenticate;
use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\JwtAuthMiddleware;
use App\Http\Middleware\SingleSignOn;
use App\Models\Admin;
use App\Services\Withdrawal\WithdrawalAccountSnapshot;
use App\Services\Withdrawal\WithdrawalOrderService;
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use ReflectionMethod;
use Tests\TestCase;

/**
 * 出金手续费总开关闭环测试。
 */
class AdminWithdrawalFeeSwitchClosureModuleTest extends TestCase
{
    /**
     * 出金手续费总开关配置键。'1' 扣费、'0' 不扣，由 2026_08_30_000001 迁移写入。
     *
     * @var string
     */
    private const FEE_ENABLED_KEY = 'withdrawal_fee_enabled';

    /**
     * 固定手续费配置键，单位 USD。参与 fee = 固定费 + 申请金额 × 费率 / 100。
     *
     * @var string
     */
    private const FIXED_FEE_KEY = 'withdrawal_fixed_fee_usd';

    /**
     * 比例手续费配置键，语义是百分数（填 5 表示 5%），服务层计算时除以 100。
     *
     * @var string
     */
    private const FEE_RATE_KEY = 'withdrawal_fee_rate';

    /**
     * 本测试会改写的全部 system_configs 键。
     *
     * setUp 按此清单整行快照、tearDown 整行恢复（含被删除的行），
     * 保证共享测试库不被本类污染 —— 全量串行中后续测试仍读到原配置。
     *
     * @var array<int, string>
     */
    private const MANAGED_CONFIG_KEYS = [
        self::FEE_ENABLED_KEY,
        self::FIXED_FEE_KEY,
        self::FEE_RATE_KEY,
        'sys_deposit_rate',
        'sys_draw_rate',
        'deposit_exchange_rate_cny',
        'withdraw_exchange_rate_cny',
    ];

    /**
     * 被本类改写的配置行原始快照（key => 行数组，null 表示该键原本不存在）。
     * tearDown 依据它整行恢复；null 表示恢复时应删除该行。
     *
     * @var array<string, array<string, mixed>|null>|null
     */
    private $configSnapshot;

    /**
     * 保存汇率会联动刷新 payment_channels.exchange_rate，这里快照被联动通道的原值
     * （通道 ID => 原汇率），tearDown 逐行恢复，避免联动副作用留在共享库。
     *
     * @var array<int, string>|null
     */
    private $channelSnapshot;

    protected function setUp(): void
    {
        parent::setUp();

        $this->configSnapshot = [];
        foreach (self::MANAGED_CONFIG_KEYS as $key) {
            $row = DB::table('system_configs')->where('key', $key)->first();
            $this->configSnapshot[$key] = $row === null ? null : (array) $row;
        }

        $this->channelSnapshot = [];
        foreach (DB::table('payment_channels')->get(['id', 'exchange_rate']) as $channel) {
            $this->channelSnapshot[(int) $channel->id] = (string) $channel->exchange_rate;
        }
    }

    protected function tearDown(): void
    {
        $this->restoreManagedConfigRows();
        $this->restoreChannelRates();

        parent::tearDown();
    }

    /**
     * 开关为 '0' 时：fee 与 rmb_fee 必须均为 0.00，实际到账等于申请金额。
     *
     * 这是开关的核心语义。断言 actual_amount 严格等于申请金额，
     * 而不是「小于申请金额」—— 后者在少扣 0.01 时依然会通过，锁不住口径。
     *
     * @return void
     */
    public function test_switch_off_charges_nothing_and_pays_full_amount(): void
    {
        // 固定费与费率都刻意配成非零：若实现是「跳过计算」而非「按 0 计算」，
        // 或开关判定失效，这两个非零值必然让断言变红。
        $snapshot = $this->settlementSnapshotFor('1000.00', '0', '5.00', '2.5');

        $this->assertSame('0.00', $snapshot['fee'], '开关关闭时仍扣取了手续费');
        $this->assertSame('0.00', $snapshot['rmb_fee'], '开关关闭时仍产生了本币手续费');
        $this->assertSame('1000.00', $snapshot['actual_amount'], '开关关闭时实际到账不等于申请金额');
    }

    /**
     * 开关为 '1' 时：按 固定费 + 申请金额 × 费率 / 100 扣取。
     *
     * 同时锁定 rmb_fee = fee × 汇率，确认关闭开关影响的只是费用本身，
     * 而非把整段本币换算一并跳过。
     *
     * @return void
     */
    public function test_switch_on_charges_fixed_plus_percentage(): void
    {
        // 1000.00 × 2.5 / 100 = 25.00，加固定费 5.00 得 30.00；本币 30.00 × 7.2 = 216.00。
        $snapshot = $this->settlementSnapshotFor('1000.00', '1', '5.00', '2.5');

        $this->assertSame('30.00', $snapshot['fee'], '固定费与比例费之和不符');
        $this->assertSame('970.00', $snapshot['actual_amount'], '实际到账未扣除手续费');
        $this->assertSame('216.00', $snapshot['rmb_fee'], '本币手续费未按汇率换算');
    }

    /**
     * 比例费语义必须是百分数而非分数，且尾差按四舍五入到分。
     *
     * 若有人把 /100 去掉或改成分数语义，2.5 会被当成 250% 扣光余额；
     * 本用例用带尾差的金额把「百分数 + 四舍五入」两件事一起钉死。
     *
     * @return void
     */
    public function test_percentage_fee_is_percent_semantics_with_half_up_rounding(): void
    {
        // 123.45 × 3.3 / 100 = 4.07385 → 截到 3 位 4.073；加 1.11 得 5.183 → 四舍五入 5.18。
        // 实际到账 123.45 - 5.18 = 118.27；本币 5.18 × 7.2 = 37.296 → 37.30。
        $snapshot = $this->settlementSnapshotFor('123.45', '1', '1.11', '3.3');

        $this->assertSame('5.18', $snapshot['fee'], '比例费语义或舍入口径不符');
        $this->assertSame('118.27', $snapshot['actual_amount'], '实际到账与手续费不自洽');
        $this->assertSame('37.30', $snapshot['rmb_fee'], '本币手续费舍入口径不符');
    }

    /**
     * 开关键缺失时必须降级为「扣费」，且不得让出金整体失败。
     *
     * 这是降级安全的核心：withdrawal_fee_enabled 是后加键，尚未执行
     * 2026_08_30_000001 迁移的库里它不存在。若把它当必填键，
     * loadConfiguration() 会抛 withdrawal_configuration_invalid，
     * 结果是**全部出金申请失败**——拿可用性换配置完整性，代价不对等。
     * 缺键兜底为 '1' 也保证与迁移前的既有行为完全一致，升级不产生资金口径突变。
     *
     * @return void
     */
    public function test_missing_switch_key_defaults_to_charging_without_failing_withdrawals(): void
    {
        // 模拟尚未执行迁移的库：整行删除，而不是把值置空。
        DB::table('system_configs')->where('key', self::FEE_ENABLED_KEY)->delete();
        $this->assertSame(
            0,
            DB::table('system_configs')->where('key', self::FEE_ENABLED_KEY)->count(),
            '缺键前置条件未生效，后续断言无意义'
        );

        $service = new WithdrawalOrderService($this->stubSnapshotGateway());
        $method = new ReflectionMethod($service, 'loadConfiguration');
        $method->setAccessible(true);

        // 缺键不抛异常本身就是被测行为之一：抛了就说明降级路径没了。
        $config = $method->invoke($service);

        $this->assertSame(
            '1',
            $config[self::FEE_ENABLED_KEY],
            '开关缺键时未按「扣费」兜底，升级会产生资金口径突变'
        );
    }

    /**
     * 关闭开关后，两个金额键的原值必须仍保留在 system_configs 中。
     *
     * 这是「重新开启即恢复」的前提，也是本功能相对「把金额改成 0」的唯一优势。
     * 若保存时把未提交的金额字段当成 0 覆盖，停收一次就永久丢失原费率标准。
     *
     * @return void
     */
    public function test_disabling_switch_preserves_original_fee_amounts(): void
    {
        $this->setConfigValue(self::FIXED_FEE_KEY, '5.00');
        $this->setConfigValue(self::FEE_RATE_KEY, '2.5');

        // 只提交开关与必填汇率，不提交两个金额字段 —— 复刻前端在开关关闭时
        // 把金额输入置为 disabled、浏览器因此不提交它们的真实行为。
        $this->submitExchangeRateForm([
            'sys_deposit_rate' => '7.20',
            'sys_draw_rate' => '7.20',
            self::FEE_ENABLED_KEY => '0',
        ]);

        $this->assertSame('0', $this->configValue(self::FEE_ENABLED_KEY), '开关未被关闭');
        $this->assertSame('5.00', $this->configValue(self::FIXED_FEE_KEY), '固定费原值被清零，无法恢复');
        $this->assertSame('2.5', $this->configValue(self::FEE_RATE_KEY), '比例费原值被清零，无法恢复');
    }

    /**
     * 重新开启开关后，必须按保留的原费率恢复扣费。
     *
     * 与上一条合起来构成完整往返：关闭不丢值、开启即恢复。
     * 只测「关闭后值还在」不够 —— 值还在但服务层读不到同样是缺陷。
     *
     * @return void
     */
    public function test_reenabling_switch_restores_charging_with_preserved_amounts(): void
    {
        $this->setConfigValue(self::FIXED_FEE_KEY, '5.00');
        $this->setConfigValue(self::FEE_RATE_KEY, '2.5');

        $this->submitExchangeRateForm([
            'sys_deposit_rate' => '7.20',
            'sys_draw_rate' => '7.20',
            self::FEE_ENABLED_KEY => '0',
        ]);
        $this->submitExchangeRateForm([
            'sys_deposit_rate' => '7.20',
            'sys_draw_rate' => '7.20',
            self::FEE_ENABLED_KEY => '1',
        ]);

        $this->assertSame('1', $this->configValue(self::FEE_ENABLED_KEY), '开关未被重新开启');

        // 用恢复后的库内真实配置走一遍算术，确认扣费口径与关闭前一致。
        $snapshot = $this->settlementSnapshotFor(
            '1000.00',
            $this->configValue(self::FEE_ENABLED_KEY),
            $this->configValue(self::FIXED_FEE_KEY),
            $this->configValue(self::FEE_RATE_KEY)
        );
        $this->assertSame('30.00', $snapshot['fee'], '重新开启后未按原费率标准恢复扣费');
    }

    /**
     * 回显接口必须把开关归一为 '1'/'0' 字符串。
     *
     * 前端用 `String(value) === '1'` 判定开关状态；若后端回显 null 或缺字段，
     * 页面会把「未知」渲染成关闭，管理员据此以为已停收，实际仍在扣费。
     *
     * @return void
     */
    public function test_info_endpoint_returns_normalized_switch_state(): void
    {
        $this->setConfigValue(self::FEE_ENABLED_KEY, '0');
        $this->adminRequest()
            ->postJson('/api/admin/exchangeRateInfo')
            ->assertOk()
            ->assertJsonPath('data.' . self::FEE_ENABLED_KEY, '0');

        $this->setConfigValue(self::FEE_ENABLED_KEY, '1');
        $this->adminRequest()
            ->postJson('/api/admin/exchangeRateInfo')
            ->assertOk()
            ->assertJsonPath('data.' . self::FEE_ENABLED_KEY, '1');
    }

    /**
     * 用反射调用服务层私有的 settlementSnapshot()，直接锁定定点算术。
     *
     * 为什么用反射而不走完整下单链路：settlementSnapshot() 是纯函数，
     * 走完整链路需要用户、实名、银行卡、余额快照等大量夹具，
     * 任一夹具变化都会让「手续费算错」这件事以无关原因失败，
     * 反而掩盖被测语义。项目既有测试（如 AdminAuthReviewProcessorTest）已用同一手法。
     *
     * @param string $amount 申请金额。
     * @param string $feeEnabled 开关值，'1' 扣费、'0' 不扣。
     * @param string $fixedFee 固定手续费（USD）。
     * @param string $feeRate 比例手续费（百分数）。
     * @return array{fee: string, actual_amount: string, exchange_rate: string, rmb_fee: string}
     */
    private function settlementSnapshotFor(
        string $amount,
        string $feeEnabled,
        string $fixedFee,
        string $feeRate
    ): array {
        $service = new WithdrawalOrderService($this->stubSnapshotGateway());
        $method = new ReflectionMethod($service, 'settlementSnapshot');
        $method->setAccessible(true);

        return $method->invoke(
            $service,
            Money::fromDecimalString($amount, '10.00', '500000.00'),
            [
                self::FEE_ENABLED_KEY => $feeEnabled,
                self::FIXED_FEE_KEY => $fixedFee,
                self::FEE_RATE_KEY => $feeRate,
                'withdraw_exchange_rate_cny' => '7.20000000',
            ]
        );
    }

    /**
     * normalizeSwitch() 必须把各种「关闭」写法都归一为 '0'。
     *
     * 关键边界：不能用 (bool) 判定。configs 是文本表，
     * (bool) 'false' 与 (bool) 'off' 在 PHP 里都是 true ——
     * 那会让管理员点了关闭却仍在扣费，且页面显示「已关闭」，属最难察觉的一类缺陷。
     *
     * @dataProvider switchOffValues
     * @param mixed $input 提交值。
     * @return void
     */
    public function test_normalize_switch_maps_off_variants_to_zero($input): void
    {
        $this->assertSame('0', $this->normalizeSwitch($input));
    }

    /**
     * 各种应判为「关闭」的提交写法。
     *
     * 覆盖 Layui 未勾选补的 '0'、现代表单的布尔 false、
     * 以及历史调用方可能传的 'false' / 'off' / 'no' 与空串。
     *
     * @return array<string, array<int, mixed>>
     */
    public function switchOffValues(): array
    {
        return [
            'string zero' => ['0'],
            'bool false' => [false],
            'string false' => ['false'],
            'string off' => ['off'],
            'string no' => ['no'],
            'empty string' => [''],
            'uppercase OFF' => ['OFF'],
            'padded false' => ['  false  '],
        ];
    }

    /**
     * normalizeSwitch() 必须把各种「开启」写法都归一为 '1'。
     *
     * @dataProvider switchOnValues
     * @param mixed $input 提交值。
     * @return void
     */
    public function test_normalize_switch_maps_on_variants_to_one($input): void
    {
        $this->assertSame('1', $this->normalizeSwitch($input));
    }

    /**
     * 各种应判为「开启」的提交写法，含 Layui switch 勾选时提交的 'on'。
     *
     * @return array<string, array<int, mixed>>
     */
    public function switchOnValues(): array
    {
        return [
            'string one' => ['1'],
            'bool true' => [true],
            'string true' => ['true'],
            'string on' => ['on'],
        ];
    }

    /**
     * Layui 提交时必须显式补 '0'，否则开关只能开不能关。
     *
     * 未勾选的 checkbox 不会出现在 layui 的 data.field 中，而后端用 has()
     * 判断本次是否提交该字段 —— 前端不显式补 '0' 时，「关闭」这个动作
     * 永远传不到后端。这是纯前端行为，只能静态锁定提交语句本身。
     *
     * @return void
     */
    public function test_layui_form_submits_explicit_zero_when_switch_unchecked(): void
    {
        $source = $this->layuiExchangeRateSubmitBlock();

        // 锁定三元表达式而非仅锁字段名出现：只出现字段名不能证明未勾选时提交了 '0'。
        $this->assertStringContainsString(
            'payload.withdrawal_fee_enabled =',
            $source,
            'Layui 提交未显式设置开关字段'
        );
        $this->assertMatchesRegularExpression(
            '~payload\.withdrawal_fee_enabled\s*=\s*\$\([^)]*withdrawal_fee_enabled[^)]*\)\s*\.prop\(\s*[\'"]checked[\'"]\s*\)\s*\?\s*[\'"]1[\'"]\s*:\s*[\'"]0[\'"]~',
            $source,
            'Layui 未在开关未勾选时显式提交 \'0\'，开关将只能开不能关'
        );
    }

    /**
     * 截取 Layui 汇率页的提交处理块源码。
     *
     * 只取被测区块而非整份 pages.js：该文件近万行，断言失败时若把整份源码
     * 打进失败信息，真正的差异会被淹没在噪声里，反而看不出哪里错了。
     * 截取失败即失败关闭，避免退化成「在空串里找不到 → 报告缺失」的误导性结论。
     *
     * @return string 提交处理块源码。
     */
    private function layuiExchangeRateSubmitBlock(): string
    {
        $path = base_path('public/js/apps/admin/layui/pages.js');
        $this->assertFileExists($path, 'Layui 页面脚本缺失，前端契约无法验证');

        $source = (string) file_get_contents($path);
        $start = strpos($source, "form.on('submit(saveExchangeRate)'");
        $this->assertNotFalse($start, 'Layui 汇率页缺少 saveExchangeRate 提交处理器');

        // 从提交处理器起截取足够覆盖整个处理块的长度；块内已包含开关归一语句。
        return substr($source, $start, 2000);
    }

    /**
     * CrmUI 汇率页声明的表单字段名必须与 API 校验键一致。
     *
     * 缺陷背景：该页 formFields 曾写成 ['deposit_rate', 'withdraw_rate']，
     * 而 fields() 原样把 name 用作表单字段名，API 却按 sys_deposit_rate /
     * sys_draw_rate 做 required 校验 —— 提交必然 VALIDATION_FAILED，
     * 即「这一页的保存从来没成功过」。静态审计抓不到，只有端到端提交能暴露。
     *
     * @return void
     */
    public function test_crmui_exchange_rate_fields_are_accepted_by_the_api(): void
    {
        $names = $this->crmUiExchangeRateFieldNames();

        // 先锁定声明本身：必填的两个汇率键与手续费三项都要在。
        foreach (['sys_deposit_rate', 'sys_draw_rate', self::FEE_ENABLED_KEY, self::FIXED_FEE_KEY, self::FEE_RATE_KEY] as $expected) {
            $this->assertContains($expected, $names, 'CrmUI 汇率页缺少字段 ' . $expected);
        }

        // 再按声明的字段名真实提交一次：字段名与 API 校验键不一致时必然 VALIDATION_FAILED。
        $payload = [];
        foreach ($names as $name) {
            $payload[$name] = $name === self::FEE_ENABLED_KEY ? '1' : '7.20';
        }

        $this->submitExchangeRateForm($payload);
        $this->assertSame('7.2', $this->configValue('sys_deposit_rate'), 'CrmUI 声明的字段名未被 API 接受');
    }

    /**
     * 读取 CrmUI 汇率页声明的表单字段名。
     *
     * 直接反射 pages() 定义而非解析 Blade：定义是唯一事实源，
     * 页面渲染只是它的下游，锁定定义才能覆盖四套 UI 共享渲染器的全部页面。
     *
     * @return array<int, string> 表单字段名清单。
     */
    private function crmUiExchangeRateFieldNames(): array
    {
        $controller = app(PageController::class);
        $method = new ReflectionMethod($controller, 'pages');
        $method->setAccessible(true);
        $pages = $method->invoke($controller);

        $this->assertArrayHasKey('exchange-rates', $pages, 'CrmUI 缺少 exchange-rates 页定义');

        $names = [];
        foreach ($pages['exchange-rates']['formFields'] as $field) {
            $names[] = is_array($field) ? (string) $field['name'] : (string) $field;
        }

        return $names;
    }

    /**
     * 用反射调用后台控制器私有的 normalizeSwitch()。
     *
     * @param mixed $input 提交值。
     * @return string 归一后的 '1' 或 '0'。
     */
    private function normalizeSwitch($input): string
    {
        $controller = app(ExchangeRateController::class);
        $method = new ReflectionMethod($controller, 'normalizeSwitch');
        $method->setAccessible(true);

        return (string) $method->invoke($controller, $input);
    }

    /**
     * 构造只满足契约、不产生外部调用的余额快照网关替身。
     *
     * settlementSnapshot() 不触碰网关，但构造服务需要它；
     * 用匿名类替身而非 mock 框架，避免为一个不被调用的依赖引入期望设置。
     *
     * @return WithdrawalAccountSnapshotGateway
     */
    private function stubSnapshotGateway(): WithdrawalAccountSnapshotGateway
    {
        return new class implements WithdrawalAccountSnapshotGateway {
            /**
             * 本测试不经过余额快照路径；被调用即说明用例走错了链路，直接失败关闭。
             *
             * @param int $userId 用户主键 ID。
             * @return WithdrawalAccountSnapshot 永不返回，调用即抛出。
             */
            public function snapshot(int $userId): WithdrawalAccountSnapshot
            {
                throw new \LogicException(
                    'settlementSnapshot() 不应触达余额快照网关；用例走错了链路。'
                );
            }
        };
    }

    /**
     * 提交后台汇率保存表单并断言保存成功。
     *
     * 断言 UPDATED 而非仅 assertOk()：本项目的业务失败同样返回 HTTP 200，
     * 只用 assertOk() 会让 VALIDATION_FAILED 与 500 之外的失败静默通过。
     *
     * @param array<string, string> $payload 提交字段。
     * @return void
     */
    private function submitExchangeRateForm(array $payload): void
    {
        $this->adminRequest()
            ->postJson('/api/admin/updateExchangeRate', $payload)
            ->assertOk()
            ->assertJsonPath('code', ResponseCode::UPDATED);
    }

    /**
     * 构造旁路鉴权中间件的后台请求。
     *
     * 本类只验证手续费开关口径，不重复验证鉴权链路（由汇率页权限测试锁定）。
     *
     * @return $this
     */
    private function adminRequest()
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
     * @return string 配置值；行不存在时返回空字符串。
     */
    private function configValue(string $key): string
    {
        return (string) DB::table('system_configs')->where('key', $key)->value('value');
    }

    /**
     * 写入指定配置键的值；行不存在时插入。
     *
     * 用 updateOrInsert 而非 update：测试库可能尚未包含后加的开关键，
     * 若静默跳过写入，用例会在错误的前置条件下空转通过。
     *
     * @param string $key 配置键名。
     * @param string $value 配置值。
     * @return void
     */
    private function setConfigValue(string $key, string $value): void
    {
        $now = time();

        DB::table('system_configs')->updateOrInsert(
            ['key' => $key],
            [
                'value' => $value,
                'group' => 'finance',
                'updated_at' => $now,
                'created_at' => $now,
            ]
        );

        $this->assertSame($value, $this->configValue($key), '配置前置条件写入失败：' . $key);
    }

    /**
     * 把本类改写过的配置行整行恢复为 setUp 时的状态。
     *
     * 用整行 delete + insert 而非 update：用例会删除整行（缺键降级用例），
     * 单纯 update 无法把删掉的行恢复回来，也无法恢复被改写的 group/description。
     *
     * @return void
     */
    private function restoreManagedConfigRows(): void
    {
        if ($this->configSnapshot === null) {
            return;
        }

        foreach ($this->configSnapshot as $key => $row) {
            DB::table('system_configs')->where('key', $key)->delete();
            if ($row !== null) {
                DB::table('system_configs')->insert($row);
            }
        }

        $this->configSnapshot = null;
    }

    /**
     * 恢复被汇率联动改写的支付通道汇率。
     *
     * @return void
     */
    private function restoreChannelRates(): void
    {
        if ($this->channelSnapshot === null) {
            return;
        }

        foreach ($this->channelSnapshot as $channelId => $rate) {
            DB::table('payment_channels')->where('id', $channelId)->update([
                'exchange_rate' => $rate,
            ]);
        }

        $this->channelSnapshot = null;
    }
}
