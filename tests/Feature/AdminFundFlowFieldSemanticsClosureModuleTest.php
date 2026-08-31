<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/30
 * Time: 19:40
 */

/**
 * AdminFundFlowFieldSemanticsClosureModuleTest
 *
 * 文件功能：
 * - 锁定后台入金流水两个旧字段的取值语义，防止 USD/RMB 与本地单号/通道单号再次错配。
 * - 旧语义取证（项目1）：
 *   · UserDepositController.php:182  dep_act_amount = $deposit_amt_usd            → USD 原额
 *   · UserDepositController.php:198  $act_pay_rmb = round(USD × sys_deposit_rate) → RMB 实付
 *   · UserDepositController.php:201  dep_amount = $act_pay_rmb                    → 列头「实际支付 / RMB」
 *   · UserDepositController.php:192  $orderId = generate_order_idV5('tg'.$userId) → 本站生成
 *   · UserDepositController.php:200  dep_outTrande = $orderId                     → 列头「充值流水号」
 *   · PayCallBackController.php:494  dep_channel_no = $data['trader_no']          → 通道平台单号
 * - 新库字段映射：amount=USD、actual_amount=USD×汇率=RMB、local_order_no=本地单号、
 *   channel_order_no=通道单号。因此 depamount 必须取 actual_amount，depoutTrande 必须取 local_order_no。
 * - 输入：mt4_trades 与 deposit_records 真实表夹具 + 后台入金流水 API；输出：PHPUnit 断言结果。
 * - 明确不负责：不覆盖入金流水的分页/筛选/导出（由 AdminFundFlowModuleTest 锁定）。
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
 * 后台入金流水字段语义闭环测试。
 */
class AdminFundFlowFieldSemanticsClosureModuleTest extends TestCase
{
    /**
     * 夹具 MT4 订单号。取高位区间避免与既有数据的 ticket 唯一索引冲突。
     *
     * @var int
     */
    private const TICKET = 963210777;

    /**
     * 夹具本地单号。前缀沿用旧项目 generate_order_idV5('tg'.$userId) 的 tg 约定，
     * 与通道单号刻意取不同前缀，便于断言两列不同源。
     *
     * @var string
     */
    private const LOCAL_ORDER_NO = 'tg963210777LOCAL';

    /**
     * 夹具通道平台单号。与本地单号完全不同，若实现把两列同源，断言会立刻失败。
     *
     * @var string
     */
    private const CHANNEL_ORDER_NO = 'CHANNEL-963210777';

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
     * 入金流水「实际支付」必须是 RMB 实付额，「充值流水号」必须是本地单号。
     *
     * 夹具刻意让 USD 与 RMB 取不同值（100.00 / 697.00），本地单号与通道单号取不同字符串，
     * 因此任何一处取错字段都会被断言精确抓住，而不是碰巧相等而漏过。
     *
     * @return void
     */
    public function test_deposit_flow_uses_rmb_actual_amount_and_local_order_no(): void
    {
        $this->seedFixture();

        $row = $this->fetchDepositFlowRow();

        // depamount：列头「实际支付 / RMB」，必须是 697.00（=100 USD × 6.97），不是 100.00。
        $this->assertSame('697.00', $this->money($row['depamount']));

        // depoutTrande：列头「充值流水号」，必须是本站生成的本地单号。
        $this->assertSame(self::LOCAL_ORDER_NO, (string) $row['depoutTrande']);

        // dep_channel_no：通道平台单号，与上面本地单号不同源。
        $this->assertSame(self::CHANNEL_ORDER_NO, (string) $row['dep_channel_no']);

        // 两列不得相等，否则「充值流水号」与「通道单号」在页面上内容重复，本地追溯键丢失。
        $this->assertNotSame((string) $row['depoutTrande'], (string) $row['dep_channel_no']);
    }

    /**
     * 未入金流水接口不得引用 deposit_records 上不存在的列。
     *
     * 该表真实列只有 channel_name / channel_order_no / gateway_code，
     * 引用 channel 或 third_party_order_no 会抛 SQLSTATE[42S22] 1054，
     * 并被控制器末尾的 catch 降级成通用 500，掩盖真实原因。
     *
     * @return void
     */
    public function test_undeposit_controller_does_not_reference_missing_columns(): void
    {
        $source = file_get_contents(app_path('Http/Controllers/Admin/UnDepositAmountController.php')) ?: '';

        $this->assertStringNotContainsString("'deposit_records.channel'", $source);
        $this->assertStringNotContainsString('deposit_records.third_party_order_no', $source);
        $this->assertStringNotContainsString('$record->third_party_order_no', $source);

        // 正向确认改用真实列。
        $this->assertStringContainsString("'deposit_records.channel_name'", $source);
    }

    /**
     * 调用后台入金流水接口并取出夹具行。
     *
     * @return array<string, mixed> 夹具对应的流水行。
     */
    private function fetchDepositFlowRow(): array
    {
        $admin = Admin::query()->findOrFail(1);
        $response = $this
            ->withoutMiddleware([
                AdminAuthenticate::class,
                JwtAuthMiddleware::class,
                SingleSignOn::class,
                CheckPermission::class,
            ])
            ->actingAs($admin, 'admin')
            ->postJson('/api/admin/depositFlowList', ['ticket' => self::TICKET, 'per_page' => 50])
            ->assertOk();

        // depositFlowList 把分页器挂在 data.list 下（同级还有 totalRow 与 summary）。
        $rows = data_get($response->json(), 'data.list.data', []);
        $this->assertIsArray($rows);

        foreach ($rows as $row) {
            if ((int) ($row['ticket'] ?? $row['order_no'] ?? 0) === self::TICKET) {
                return $row;
            }
        }

        $this->fail('入金流水未返回夹具记录 ticket=' . self::TICKET);
    }

    /**
     * 归一为两位小数字符串，屏蔽 float 与 DECIMAL 的表现差异。
     *
     * @param mixed $value 接口返回的金额原始值。
     * @return string 两位小数定点字符串。
     */
    private function money($value): string
    {
        return number_format((float) $value, 2, '.', '');
    }

    /**
     * 写入一条 MT4 入金流水与其对应的已支付入金记录。
     *
     * mt4_trades 侧必须满足入金流水查询条件：cmd=6、open_price=0、profit>0、
     * comment 命中旧入金关键字（这里用 DBUN，即旧项目 dep_body 前缀）。
     * deposit_records 侧必须 mt4_ticket 对应且 status='02'（已支付），否则关联不上。
     *
     * @return void
     */
    private function seedFixture(): void
    {
        $now = time();

        DB::table('mt4_trades')->insert([
            'ticket' => self::TICKET,
            'login' => 1,
            'symbol' => '',
            'cmd' => 6,
            'volume' => 0,
            'open_price' => 0,
            'close_price' => 0,
            'commission' => 0,
            'swaps' => 0,
            'profit' => 100.00,
            'open_time' => $now,
            'close_time' => $now,
            'created_at' => $now,
            'updated_at' => $now,
            'comment' => 'DBUN1',
        ]);

        DB::table('deposit_records')->insert([
            'user_id' => 1,
            'user_name' => 'fixture',
            'mt4_ticket' => self::TICKET,
            // amount 是 USD 原额，actual_amount 是 USD × 汇率后的 RMB 实付额。
            // 两者刻意不同，用于验证 depamount 取的是后者。
            'amount' => '100.00',
            'actual_amount' => '697.00',
            'exchange_rate' => '6.97000000',
            'channel_name' => 'Fixture Channel',
            'channel_order_no' => self::CHANNEL_ORDER_NO,
            'local_order_no' => self::LOCAL_ORDER_NO,
            'currency' => 'CNY',
            'status' => '02',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    /**
     * 按 ticket 与单号圈定清理夹具，不影响其它数据。
     *
     * @return void
     */
    private function cleanupFixture(): void
    {
        DB::table('deposit_records')->where('mt4_ticket', self::TICKET)->delete();
        DB::table('mt4_trades')->where('ticket', self::TICKET)->delete();
    }
}
