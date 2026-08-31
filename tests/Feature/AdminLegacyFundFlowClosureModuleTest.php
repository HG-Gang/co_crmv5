<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/07/29
 * Time: 01:50
 */

namespace Tests\Feature;

use App\Http\Middleware\AdminAuthenticate;
use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\JwtAuthMiddleware;
use App\Http\Middleware\LegacyAdminAuthenticate;
use App\Http\Middleware\SingleSignOn;
use App\Models\Admin;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * 旧后台资金流水搜索入口闭环测试。
 *
 * 文件功能：
 * - 验证项目1后台入金流水、出金流水和未入金流水搜索入口在项目2保持可执行。
 * - 验证旧字段 `userId`、`deposit_id`、`withdraw_id`、`undeposit_id` 与日期字段会被转换为当前查询条件。
 * - 验证 V1 入口继续返回 `rows/total/footer`，V2 入口继续返回 `code/msg/count/data/totalRow`。
 */
class AdminLegacyFundFlowClosureModuleTest extends TestCase
{
    use DatabaseTransactions;

    /**
     * 资金流水对账用例的业务用户 ID。夹具据此构造出入金/出金/礼赠流水样本。
     * @var int
     */
    private const USER_ID = 986701;
    /**
     * 本夹具流水单号的统一前缀（legacy-fund-flow-）。清理时按前缀删除样本行。
     * @var string
     */
    private const PREFIX = 'legacy-fund-flow-';

    /**
     * 每个用例开始前清理本测试专用资金流水夹具。
     *
     * 方法说明：
     * - 旧后台 `deposit_id/withdraw_id` 使用 LIKE 模糊匹配，历史测试残留的同前缀票据会影响排序断言。
     * - 清理动作运行在 DatabaseTransactions 事务内，只隔离当前测试视图，不改写真实库最终状态。
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->clearFundFlowFixtures();
    }

    /**
     * 旧入金流水 V1/V2 应读取 MT4 正向余额流水并返回旧表格契约。
     *
     * @return void
     */
    public function test_legacy_deposit_flow_searches_use_old_fields_and_legacy_table_contracts(): void
    {
        $actor = $this->ensureSuperAdmin();
        $ticket = 98670101;
        $this->ensureUser(self::USER_ID, '旧入金流水用户');
        $this->createMt4BalanceTrade($ticket, self::USER_ID, 150.25, 'DBUN-' . self::PREFIX . 'deposit', strtotime('2026-05-10 10:00:00'));
        $this->createDepositRecord($ticket, self::PREFIX . 'deposit-order', 160.50, '02');

        $payload = [
            'userId' => self::USER_ID,
            'deposit_id' => '986701',
            'direct_deposit_source' => 'DBUN',
            'deposit_startdate' => '2026-05-01',
            'deposit_enddate' => '2026-05-31',
            'limit' => 5,
        ];

        $v1 = $this->legacyRequest($actor)->postJson('/index/admin/amount/depositFlowSearch', $payload);
        $v1->assertOk()
            ->assertJsonPath('rows.0.order_no', $ticket)
            ->assertJsonPath('rows.0.userId', self::USER_ID)
            ->assertJsonPath('rows.0.username', '旧入金流水用户')
            ->assertJsonPath('rows.0.directProfit', 150.25)
            ->assertJsonPath('rows.0.depamount', 160.5)
            ->assertJsonPath('total', 1)
            ->assertJsonPath('footer.0.order_no', '总计')
            ->assertJsonPath('footer.0.directProfit', 150.25);

        $v2 = $this->legacyRequest($actor)->postJson('/index/admin/amount/depositFlowSearchV2', $payload);
        $v2->assertOk()
            ->assertJsonPath('code', 200)
            ->assertJsonPath('msg', 'Request data successful.')
            ->assertJsonPath('count', 1)
            ->assertJsonPath('data.0.order_no', $ticket)
            ->assertJsonPath('totalRow.order_no', '总计')
            ->assertJsonPath('totalRow.directProfit', 150.25);
    }

    /**
     * 旧出金流水 V1/V2 应按旧出金字段筛选 MT4 负向余额流水。
     *
     * @return void
     */
    public function test_legacy_withdraw_flow_searches_use_old_fields_and_legacy_table_contracts(): void
    {
        $actor = $this->ensureSuperAdmin();
        $ticket = 98670102;
        $this->ensureUser(self::USER_ID, '旧出金流水用户');
        $this->createMt4BalanceTrade($ticket, self::USER_ID, -88.75, 'WBIN-' . self::PREFIX . 'withdraw', strtotime('2026-05-11 10:00:00'));
        $this->createMt4BalanceTrade(98670103, self::USER_ID, -33.33, 'WBAD-' . self::PREFIX . 'hidden', strtotime('2026-05-11 11:00:00'));

        $payload = [
            'userId' => self::USER_ID,
            'withdraw_id' => '98670102',
            'withdraw_source' => 'WBIN',
            'deposit_startdate' => '2026-05-01',
            'deposit_enddate' => '2026-05-31',
            'limit' => 5,
        ];

        $v1 = $this->legacyRequest($actor)->postJson('/index/admin/amount/withdrawFlowSearch', $payload);
        $v1->assertOk()
            ->assertJsonPath('rows.0.order_no', $ticket)
            ->assertJsonPath('rows.0.userId', self::USER_ID)
            ->assertJsonPath('rows.0.username', '旧出金流水用户')
            ->assertJsonPath('rows.0.directProfit', -88.75)
            ->assertJsonPath('total', 1)
            ->assertJsonPath('footer.0.order_no', '总计')
            ->assertJsonPath('footer.0.directProfit', -88.75);

        $v2 = $this->legacyRequest($actor)->postJson('/index/admin/amount/withdrawFlowSearchV2', $payload);
        $v2->assertOk()
            ->assertJsonPath('code', 200)
            ->assertJsonPath('msg', 'Request data successful.')
            ->assertJsonPath('count', 1)
            ->assertJsonPath('data.0.order_no', $ticket)
            ->assertJsonPath('totalRow.order_no', '总计')
            ->assertJsonPath('totalRow.directProfit', -88.75);

        $this->assertStringNotContainsString('98670103', $v1->getContent());
    }

    /**
     * 旧未入金流水 V1/V2 应按旧订单字段筛选待支付入金申请。
     *
     * @return void
     */
    public function test_legacy_undeposit_flow_searches_use_old_fields_and_legacy_table_contracts(): void
    {
        $actor = $this->ensureSuperAdmin();
        $this->ensureUser(self::USER_ID, '旧未入金用户');
        $this->createDepositRecord(0, self::PREFIX . 'undeposit-order', 77.25, '01');
        $this->createDepositRecord(0, self::PREFIX . 'approved-hidden', 999.99, '02');

        $payload = [
            'userId' => self::USER_ID,
            'undeposit_id' => self::PREFIX . 'undeposit',
            'deposit_startdate' => '2026-05-01',
            'deposit_enddate' => '2026-05-31',
            'limit' => 5,
        ];

        $v1 = $this->legacyRequest($actor)->postJson('/index/admin/amount/undepositFlowSearch', $payload);
        $v1->assertOk()
            ->assertJsonPath('rows.0.local_order_no', self::PREFIX . 'undeposit-order')
            ->assertJsonPath('rows.0.userId', self::USER_ID)
            ->assertJsonPath('rows.0.username', '旧未入金用户')
            ->assertJsonPath('rows.0.amount', 77.25)
            ->assertJsonPath('total', 1)
            ->assertJsonPath('footer.0.order_no', '总计')
            ->assertJsonPath('footer.0.amount', 77.25);

        $v2 = $this->legacyRequest($actor)->postJson('/index/admin/amount/undepositFlowSearchV2', $payload);
        $v2->assertOk()
            ->assertJsonPath('code', 200)
            ->assertJsonPath('msg', 'Request data successful.')
            ->assertJsonPath('count', 1)
            ->assertJsonPath('data.0.local_order_no', self::PREFIX . 'undeposit-order')
            ->assertJsonPath('totalRow.order_no', '总计')
            ->assertJsonPath('totalRow.amount', 77.25);

        $this->assertStringNotContainsString(self::PREFIX . 'approved-hidden', $v1->getContent());
    }

    /**
     * 旧入金流水和出金流水导出入口应返回当前筛选条件下的 CSV。
     *
     * @return void
     */
    public function test_legacy_fund_flow_exports_return_current_filter_csv_downloads(): void
    {
        $actor = $this->ensureSuperAdmin();
        $this->ensureUser(self::USER_ID, '旧资金流水导出用户');
        $this->createMt4BalanceTrade(98670104, self::USER_ID, 210.75, 'DBUN-' . self::PREFIX . 'export-deposit', strtotime('2026-05-12 10:00:00'));
        $this->createMt4BalanceTrade(98670105, self::USER_ID, -66.50, 'WBIN-' . self::PREFIX . 'export-withdraw', strtotime('2026-05-12 11:00:00'));
        $this->createDepositRecord(98670104, self::PREFIX . 'export-deposit-order', 220.00, '02');

        $depositExport = $this->legacyRequest($actor)->post('/index/admin/amount/depositExport', [
            'role' => 'admin',
            'type' => 'depositFlow',
            'data' => [
                'userId' => self::USER_ID,
                'deposit_id' => '98670104',
                'direct_deposit_source' => 'DBUN',
                'deposit_startdate' => '2026-05-01',
                'deposit_enddate' => '2026-05-31',
            ],
        ]);

        $depositExport->assertOk();
        $this->assertStringContainsString('text/csv', (string) $depositExport->headers->get('content-type'));
        $this->assertStringContainsString('deposit_flows_export.csv', (string) $depositExport->headers->get('content-disposition'));
        $depositCsv = $depositExport->streamedContent();
        $this->assertStringContainsString('98670104', $depositCsv);
        $this->assertStringContainsString('DBUN-' . self::PREFIX . 'export-deposit', $depositCsv);
        $this->assertStringContainsString('210.75', $depositCsv);
        $this->assertStringNotContainsString('98670105', $depositCsv);

        $withdrawExport = $this->legacyRequest($actor)->post('/index/admin/amount/withdrawFlowExport', [
            'role' => 'admin',
            'userId' => self::USER_ID,
            'withdraw_id' => '98670105',
            'withdraw_source' => 'WBIN',
            'deposit_startdate' => '2026-05-01',
            'deposit_enddate' => '2026-05-31',
        ]);

        $withdrawExport->assertOk();
        $this->assertStringContainsString('text/csv', (string) $withdrawExport->headers->get('content-type'));
        $this->assertStringContainsString('withdraw_flows_export.csv', (string) $withdrawExport->headers->get('content-disposition'));
        $withdrawCsv = $withdrawExport->streamedContent();
        $this->assertStringContainsString('98670105', $withdrawCsv);
        $this->assertStringContainsString('WBIN-' . self::PREFIX . 'export-withdraw', $withdrawCsv);
        $this->assertStringContainsString('-66.5', $withdrawCsv);
        $this->assertStringNotContainsString('98670104', $withdrawCsv);
    }

    /**
     * 创建绕过旧后台中间件后的测试请求对象。
     *
     * @param Admin $actor 当前登录后台管理员。
     * @return self 当前测试实例，已绑定 admin guard 登录态。
     */
    private function legacyRequest(Admin $actor): self
    {
        return $this->withoutMiddleware([
            AdminAuthenticate::class,
            LegacyAdminAuthenticate::class,
            JwtAuthMiddleware::class,
            SingleSignOn::class,
            CheckPermission::class,
        ])->actingAs($actor, 'admin');
    }

    /**
     * 清理本测试固定票据和订单号。
     *
     * @return void
     */
    private function clearFundFlowFixtures(): void
    {
        DB::table('mt4_trades')
            ->whereIn('ticket', [98670101, 98670102, 98670103, 98670104, 98670105])
            ->orWhere('comment', 'LIKE', '%' . self::PREFIX . '%')
            ->delete();

        DB::table('deposit_records')
            ->where('user_id', self::USER_ID)
            ->where(function ($query): void {
                $query->where('local_order_no', 'LIKE', self::PREFIX . '%')
                    ->orWhereIn('mt4_ticket', [98670101, 98670104]);
            })
            ->delete();
    }

    /**
     * 创建超级管理员。
     *
     * @return Admin admin guard 可识别的后台管理员。
     */
    private function ensureSuperAdmin(): Admin
    {
        $now = time();

        DB::table('admins')->updateOrInsert(
            ['id' => 1],
            [
                'username' => self::PREFIX . 'super',
                'email' => self::PREFIX . 'super@example.test',
                'password' => Hash::make('password'),
                'status' => 1,
                'created_at' => $now,
                'updated_at' => $now,
                'deleted_at' => null,
            ]
        );

        return Admin::query()->findOrFail(1);
    }

    /**
     * 创建资金流水所属用户。
     *
     * @param int $userId 业务用户 ID，同时作为 MT4 登录号写入 mt4_code。
     * @param string $userName 用户展示名。
     * @return void
     */
    private function ensureUser(int $userId, string $userName): void
    {
        $now = time();

        DB::table('user_infos')->updateOrInsert(
            ['user_id' => $userId],
            [
                'login_id' => 0,
                'user_name' => $userName,
                'phone' => '',
                'gender' => 1,
                'account_type' => 2,
                'parent_id' => 0,
                'family_tree' => (string) $userId,
                'total_funds' => 0,
                'equity' => 0,
                'effective_credit' => 0,
                'mt4_code' => $userId,
                'created_at' => $now,
                'updated_at' => $now,
                'deleted_at' => null,
            ]
        );
    }

    /**
     * 创建 MT4 余额类资金流水。
     *
     * @param int $ticket MT4 订单号。
     * @param int $login MT4 登录号。
     * @param float $profit 正数表示入金，负数表示出金。
     * @param string $comment MT4 备注，用于识别出入金来源。
     * @param int $closeTime 平仓/资金发生时间戳。
     * @return void
     */
    private function createMt4BalanceTrade(int $ticket, int $login, float $profit, string $comment, int $closeTime): void
    {
        DB::table('mt4_trades')->updateOrInsert(
            ['ticket' => $ticket],
            [
                'login' => $login,
                'symbol' => 'BALANCE',
                'cmd' => 6,
                'volume' => 0,
                'open_price' => 0,
                'close_price' => 0,
                'commission' => 0,
                'swaps' => 0,
                'profit' => $profit,
                'open_time' => $closeTime - 60,
                'close_time' => $closeTime,
                'comment' => $comment,
                'modify_time' => $closeTime,
                'created_at' => $closeTime,
                'updated_at' => $closeTime,
            ]
        );
    }

    /**
     * 创建入金记录，用于入金流水补充实际支付金额或未入金列表。
     *
     * @param int $mt4Ticket 关联的 MT4 订单号；未入金记录传 0。
     * @param string $localOrderNo 本地订单号。
     * @param float $amount 申请或实际支付金额。
     * @param string $status 入金状态：01=待支付，02=已支付。
     * @return void
     */
    private function createDepositRecord(int $mt4Ticket, string $localOrderNo, float $amount, string $status): void
    {
        $createdAt = strtotime('2026-05-10 12:00:00');

        DB::table('deposit_records')->updateOrInsert(
            ['local_order_no' => $localOrderNo],
            [
                'user_id' => self::USER_ID,
                'user_name' => '旧资金流水用户',
                'mt4_ticket' => $mt4Ticket,
                'amount' => $amount,
                'actual_amount' => $amount,
                'exchange_rate' => 1,
                'channel_name' => 'Legacy Channel',
                'channel_order_no' => 'channel-' . $localOrderNo,
                'status' => $status,
                'payment_time' => null,
                'remarks' => 'legacy fund flow closure',
                'created_by' => 'test',
                'updated_by' => 'test',
                'created_at' => $createdAt,
                'updated_at' => $createdAt,
                'deleted_at' => null,
            ]
        );
    }
}
