<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/19
 * Time: 16:24
 */

/**
 * AdminLegacyOrderRiskSearchClosureModuleTest
 *
 * 文件功能：
 * - 验证旧后台订单/持仓/品种/实时返佣/清零/风控搜索闭环：转发到现代真实表并保留 V1/V2 旧信封，一键清零对非负余额、缺失用户失败关闭。
 * - 输入：HTTP 请求与事务回滚的数据库夹具；输出：PHPUnit 断言结果。
 * - 明确不负责：不覆盖真实 MT4 网关与线上支付/出金通道（由网关契约测试锁定）。
 */

namespace Tests\Feature;

use App\Constants\ResponseCode;
use App\Contracts\DepositSettlementGateway;
use App\Models\Admin;
use App\Services\Payment\DepositSettlementResult;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * 后台遗留"订单列表/持仓汇总/品种/实时返佣/仓位清零/风控持仓与盈亏"搜索闭环测试。
 *
 * 文件目的：
 * - 旧后台 order/closeListSearch、openlistSearch 转发到现代平仓/持仓列表，
 *   以 mt4_trades 真实表 + user_infos.mt4_code 映射返回现代分页信封；
 * - positionSummarySearch、productionListSearch、realCommissionListSearch、
 *   whsExpZeroListSearch 转发到真实清零记录列表，并保留 V1/V2 旧信封；
 * - order/oneKeyZero 一键清零：负余额无持仓用户 + MT4 成功后落 whs_exp_zeros 并回写余额；
 * - fengXian/positionSearch 使用 user_trades 恢复旧 V1/V2 风险契约；profitSearch 在盈利读模型完成前明确失败关闭；
 * - 逐一断言旧入口能看到按旧条件种子的记录，并保持现代错误码 fail-closed 语义。
 */
class AdminLegacyOrderRiskSearchClosureModuleTest extends TestCase
{
    use DatabaseTransactions;

    public function test_legacy_closed_position_searches_see_seeded_closed_trades(): void
    {
        $admin = Admin::query()->findOrFail(1);
        $userId = 984301;
        $login = 20090101;
        $this->seedUserWithMt4Code($userId, 'Legacy Closed Search User', $login);
        $this->seedMt4Trade($login, 98430101, 'XAUUSD', 0, 1.0, 0, time() - 60, $now = time(), 'legacy-closed-' . $userId);

        foreach (['closeListSearch', 'closeListSearchV2'] as $action) {
            $response = $this->actingAs($admin, 'admin')
                ->postJson('/index/admin/order/' . $action, ['user_id' => $userId])
                ->assertOk()
                ->assertJsonPath('code', ResponseCode::SUCCESS);

            $this->assertStringContainsString((string) 98430101, $response->getContent(), $action);
        }
    }

    public function test_legacy_open_position_searches_see_seeded_open_trades(): void
    {
        $admin = Admin::query()->findOrFail(1);
        $userId = 984302;
        $login = 20090102;
        $this->seedUserWithMt4Code($userId, 'Legacy Open Search User', $login);
        $this->seedMt4Trade($login, 98430201, 'EURUSD', 0, 2.0, 0, time(), 0, 'legacy-open-' . $userId);

        foreach (['openlistSearch', 'openlistSearchV2'] as $action) {
            $response = $this->actingAs($admin, 'admin')
                ->postJson('/index/admin/order/' . $action, ['user_id' => $userId])
                ->assertOk()
                ->assertJsonPath('code', ResponseCode::SUCCESS);

            $this->assertStringContainsString((string) 98430201, $response->getContent(), $action);
        }
    }

    public function test_legacy_position_summary_searches_see_seeded_user_row(): void
    {
        $admin = Admin::query()->findOrFail(1);
        $userId = 984303;
        $login = 20090103;
        $this->seedUserWithMt4Code($userId, 'Legacy Position Summary User', $login);
        $this->seedMt4Trade($login, 98430301, 'XAUUSD', 0, 1.5, 0, time() - 120, time(), 'legacy-possum-' . $userId);

        foreach ([
            'index/admin/order/positionSummarySearch',
            'index/admin/order/v2/positionSummarySearchV2',
        ] as $uri) {
            $response = $this->actingAs($admin, 'admin')
                ->postJson('/' . $uri, ['user_id' => $userId])
                ->assertOk()
                ->assertJsonPath('code', ResponseCode::SUCCESS);

            $this->assertStringContainsString((string) $userId, $response->getContent(), $uri);
        }
    }

    public function test_legacy_production_searches_see_seeded_symbol(): void
    {
        $admin = Admin::query()->findOrFail(1);
        $symbol = 'CLOSUREPROD';
        $now = time();

        DB::table('symbol_prices')->updateOrInsert(
            ['symbol' => $symbol],
            [
                'time' => date('Y-m-d H:i:s', $now),
                'bid' => 2001.12,
                'ask' => 2001.62,
                'low' => 1998.1,
                'high' => 2005.2,
                'direction' => 0,
                'digits' => 2,
                'spread' => 0.5,
                'group_id' => 1,
                'status' => 1,
                'modify_time' => date('Y-m-d H:i:s', $now),
                'created_at' => $now,
                'updated_at' => $now,
                'deleted_at' => null,
            ]
        );

        foreach (['productionListSearch', 'productionListSearchV2'] as $action) {
            $response = $this->actingAs($admin, 'admin')
                ->postJson('/index/admin/order/' . $action, ['symbol' => $symbol])
                ->assertOk()
                ->assertJsonPath('code', ResponseCode::SUCCESS);

            $this->assertStringContainsString($symbol, $response->getContent(), $action);
        }
    }

    public function test_legacy_realtime_commission_searches_see_seeded_rebate_trade(): void
    {
        $admin = Admin::query()->findOrFail(1);
        $login = 20090105;
        $this->seedUserWithMt4Code(984305, 'Legacy Realtime Commission User', $login);
        $this->seedMt4Trade($login, 98430501, 'XAUUSD', 6, 0, 12.34, time() - 60, time(), 'DBCN legacy-rebate-' . $login);

        foreach (['realCommissionListSearch', 'realCommissionListSearchV2'] as $action) {
            $response = $this->actingAs($admin, 'admin')
                ->postJson('/index/admin/order/' . $action, ['user_id' => $login])
                ->assertOk()
                ->assertJsonPath('code', ResponseCode::SUCCESS);

            $this->assertStringContainsString((string) 98430501, $response->getContent(), $action);
        }
    }

    public function test_legacy_whs_exp_zero_searches_read_zero_records_with_v1_and_v2_envelopes(): void
    {
        $admin = Admin::query()->findOrFail(1);
        $userId = 984306;
        $now = time();

        DB::table('user_infos')->where('user_id', $userId)->delete();
        DB::table('user_logins')->where('user_id', $userId)->delete();

        $loginId = DB::table('user_logins')->insertGetId([
            'user_id' => $userId,
            'email' => 'legacy-whs-' . $userId . '@example.test',
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
            'user_name' => 'Legacy Whs Exp Zero User',
            'phone' => '178984306',
            'account_type' => 2,
            'parent_id' => 0,
            'level_id' => 0,
            'comm_rate' => 0,
            'auth_status' => 1,
            'total_funds' => -55.50,
            'effective_credit' => 5.50,
            'equity' => 0,
            'created_at' => $now,
            'updated_at' => $now,
            'deleted_at' => null,
        ]);

        DB::table('whs_exp_zeros')->insert([
            'user_id' => $userId,
            'user_name' => 'Legacy Whs Exp Zero User',
            'balance' => -55.50,
            'credit' => 5.50,
            'status' => 2,
            'md5_key' => 'legacy-whs-record-' . $userId,
            'created_by' => '1',
            'updated_by' => '1',
            'created_at' => $now,
            'updated_at' => $now,
            'deleted_at' => null,
        ]);

        $filters = [
            'wez_userid' => $userId,
            'startdate' => date('Y-m-d', $now),
            'enddate' => date('Y-m-d', $now),
        ];

        $this->actingAs($admin, 'admin')
            ->postJson('/index/admin/order/whsExpZeroListSearch', $filters)
            ->assertOk()
            ->assertJsonPath('rows.0.wezuserid', $userId)
            ->assertJsonPath('rows.0.wezstatus', 2)
            ->assertJsonPath('total', '');

        $this->actingAs($admin, 'admin')
            ->postJson('/index/admin/order/whsExpZeroListSearchV2', $filters)
            ->assertOk()
            ->assertJsonPath('code', 200)
            ->assertJsonPath('count', 1)
            ->assertJsonPath('data.0.wezuserid', $userId)
            ->assertJsonPath('data.0.wezstatus', 2)
            ->assertJsonPath('totalRow', []);
    }

    public function test_legacy_one_key_zero_completes_negative_balance_user(): void
    {
        $admin = Admin::query()->findOrFail(1);
        $userId = 984307;
        $this->seedNegativeBalanceUser($userId, 'Legacy One Key Zero User', -66.60, 11.10);

        $this->app->instance(DepositSettlementGateway::class, new class implements DepositSettlementGateway {
            public function deposit(int $userId, string $amount, string $comment): DepositSettlementResult
            {
                return DepositSettlementResult::settled('legacy-zero-11001');
            }
        });

        $this->actingAs($admin, 'admin')
            ->postJson('/index/admin/order/oneKeyZero', [
                'user_id' => $userId,
                'userName' => 'Forged Legacy Name',
                'balance' => -999999.99,
                'crdt' => 999999.99,
            ])
            ->assertOk()
            ->assertJsonPath('code', ResponseCode::SUCCESS)
            ->assertJsonPath('data.user_id', $userId)
            ->assertJsonPath('data.status', 2)
            ->assertJsonPath('msg', 'SUC')
            ->assertJsonPath('err', 'noerr')
            ->assertJsonPath('col', 'enable');

        $this->assertDatabaseHas('whs_exp_zeros', [
            'user_id' => $userId,
            'user_name' => 'Legacy One Key Zero User',
            'balance' => -66.60,
            'credit' => 11.10,
            'status' => 2,
        ]);
        $this->assertSame(0.0, (float) DB::table('user_infos')->where('user_id', $userId)->value('total_funds'));
    }

    public function test_legacy_one_key_zero_fails_closed_for_non_negative_balance(): void
    {
        $admin = Admin::query()->findOrFail(1);
        $userId = 984308;
        $this->seedNegativeBalanceUser($userId, 'Legacy Zero Not Negative', 100.00, 0);

        $this->actingAs($admin, 'admin')
            ->postJson('/index/admin/order/oneKeyZero', ['user_id' => $userId])
            ->assertOk()
            ->assertJsonPath('code', ResponseCode::OPERATION_NOT_ALLOWED)
            ->assertJsonPath('msg', 'FAIL')
            ->assertJsonPath('err', 'crtfail')
            ->assertJsonPath('col', 'nocol');

        $this->assertSame(0, DB::table('whs_exp_zeros')->where('user_id', $userId)->count());
    }

    public function test_legacy_one_key_zero_fails_closed_for_missing_user(): void
    {
        $admin = Admin::query()->findOrFail(1);

        $this->actingAs($admin, 'admin')
            ->postJson('/index/admin/order/oneKeyZero', ['user_id' => 98430999])
            ->assertOk()
            ->assertJsonPath('code', ResponseCode::DATA_NOT_FOUND);
    }

    public function test_legacy_one_key_zero_fails_closed_without_user_id(): void
    {
        $admin = Admin::query()->findOrFail(1);

        $this->actingAs($admin, 'admin')
            ->postJson('/index/admin/order/oneKeyZero')
            ->assertOk()
            ->assertJsonPath('code', ResponseCode::VALIDATION_FAILED);
    }

    public function test_legacy_risk_position_searches_see_seeded_open_trades(): void
    {
        $admin = Admin::query()->findOrFail(1);
        $userId = 984309;
        $login = 20090109;
        $this->seedUserWithMt4Code($userId, 'Legacy Risk Position User', $login);
        $this->seedLocalRiskTrade($userId, 98430901, 'GBPUSD', 12, -2);

        $this->actingAs($admin, 'admin')
            ->postJson('/index/admin/fengXian/positionSearch', ['user_id' => $userId])
            ->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonPath('rows.0.ticket', 98430901);

        $this->actingAs($admin, 'admin')
            ->postJson('/index/admin/fengXian/positionSearchv2', ['user_id' => $userId])
            ->assertOk()
            ->assertJsonPath('code', 200)
            ->assertJsonPath('count', 1)
            ->assertJsonPath('data.0.ticket', 98430901);
    }

    public function test_legacy_profit_searches_do_not_fall_back_to_the_mt4_symbol_summary(): void
    {
        $admin = Admin::query()->findOrFail(1);
        $login = 20090110;
        $this->seedUserWithMt4Code(984310, 'Legacy Profit Summary User', $login);
        $this->seedMt4Trade($login, 98431001, 'XAUUSD', 0, 1.0, 0, time(), time(), 'legacy-profit-' . $login);

        $this->actingAs($admin, 'admin')
            ->postJson('/index/admin/fengXian/profitSearch')
            ->assertOk()
            ->assertJsonPath('rows', '')
            ->assertJsonPath('total', 0);

        $this->actingAs($admin, 'admin')
            ->postJson('/index/admin/fengXian/profitSearchV2')
            ->assertOk()
            ->assertJsonPath('code', 200)
            ->assertJsonPath('count', 0)
            ->assertJsonPath('data', [])
            ->assertJsonPath('totalRow', []);
    }

    private function seedUserWithMt4Code(int $userId, string $userName, int $login): void
    {
        $now = time();

        DB::table('user_infos')->where('user_id', $userId)->delete();
        DB::table('user_logins')->where('user_id', $userId)->delete();

        $loginId = DB::table('user_logins')->insertGetId([
            'user_id' => $userId,
            'email' => 'legacy-order-' . $userId . '@example.test',
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
            'user_name' => $userName,
            'phone' => '178' . substr((string) $userId, -5),
            'account_type' => 2,
            'parent_id' => 0,
            'level_id' => 0,
            'comm_rate' => 0,
            'auth_status' => 1,
            'mt4_code' => $login,
            'mt4_group' => 'closure-group',
            'total_funds' => 0,
            'equity' => 0,
            'created_at' => $now,
            'updated_at' => $now,
            'deleted_at' => null,
        ]);
    }

    private function seedMt4Trade(int $login, int $ticket, string $symbol, int $cmd, float $volume, float $profit, int $openTime, int $closeTime, string $comment): void
    {
        $now = time();

        DB::table('mt4_trades')->where('ticket', $ticket)->delete();

        DB::table('mt4_trades')->insert([
            'ticket' => $ticket,
            'login' => $login,
            'symbol' => $symbol,
            'cmd' => $cmd,
            'volume' => $volume,
            'open_price' => 100.00,
            'close_price' => 100.00,
            'commission' => -1.00,
            'swaps' => -0.50,
            'profit' => $profit,
            'open_time' => $openTime,
            'close_time' => $closeTime,
            'comment' => $comment,
            'modify_time' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    private function seedLocalRiskTrade(
        int $userId,
        int $ticket,
        string $symbol,
        float $profit,
        float $commission
    ): void {
        $now = time();

        DB::table('user_trades')->where('ticket', $ticket)->delete();
        DB::table('user_trades')->insert([
            'user_id' => $userId,
            'ticket' => $ticket,
            'symbol' => $symbol,
            'digits' => 5,
            'cmd' => 0,
            'volume' => 100,
            'open_time' => date('Y-m-d H:i:s', $now - 60),
            'open_price' => 100,
            'stop_loss' => 0,
            'take_profit' => 0,
            'close_time' => '1970-01-01 00:00:00',
            'commission' => $commission,
            'swaps' => 0,
            'close_price' => 0,
            'profit' => $profit,
            'margin_rate' => 1,
            'comment' => 'legacy local risk ' . $ticket,
            'modify_time' => date('Y-m-d H:i:s', $now),
            'created_at' => $now,
            'updated_at' => $now,
            'deleted_at' => null,
        ]);
    }

    private function seedNegativeBalanceUser(int $userId, string $userName, float $totalFunds, float $credit): void
    {
        $now = time();

        DB::table('user_infos')->where('user_id', $userId)->delete();
        DB::table('user_logins')->where('user_id', $userId)->delete();
        DB::table('whs_exp_zeros')->where('user_id', $userId)->delete();

        $loginId = DB::table('user_logins')->insertGetId([
            'user_id' => $userId,
            'email' => 'legacy-whs-' . $userId . '@example.test',
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
            'user_name' => $userName,
            'phone' => '178' . substr((string) $userId, -5),
            'account_type' => 2,
            'parent_id' => 0,
            'level_id' => 0,
            'comm_rate' => 0,
            'auth_status' => 1,
            'total_funds' => $totalFunds,
            'effective_credit' => $credit,
            'equity' => 0,
            'created_at' => $now,
            'updated_at' => $now,
            'deleted_at' => null,
        ]);
    }
}
