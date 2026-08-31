<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/19
 * Time: 13:59
 */

/**
 * AdminLegacyOneKeySearchClosureModuleTest
 *
 * 文件功能：
 * - 验证旧后台异常余额一键清零预扫描入口闭环：返回负余额客户扫描列表、支持 user_id 与旧 userId 别名过滤。
 * - 输入：HTTP 请求与事务回滚的数据库夹具；输出：PHPUnit 断言结果。
 * - 明确不负责：不覆盖真实 MT4 网关与线上支付/出金通道（由网关契约测试锁定）。
 */

namespace Tests\Feature;

use App\Constants\ResponseCode;
use App\Models\Admin;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * 后台遗留"异常余额一键清零-预扫描"入口 order/oneKeySearch 闭环测试。
 *
 * 文件目的：
 * - 锁定旧后台 AdminWhsExpZeroController@oneKeySearch 的迁移行为：旧入口在点击时
 *   扫描负余额客户，并为符合条件的客户预登记 status=1 清零记录。
 * - 名单契约：仅普通客户 + 余额为负 + 无持仓 + 无待处理清零记录。
 */
class AdminLegacyOneKeySearchClosureModuleTest extends TestCase
{
    use DatabaseTransactions;

    public function test_legacy_one_key_search_returns_scan_results_as_list(): void
    {
        $admin = Admin::query()->findOrFail(1);
        $negativeUser = 987601;
        $openTradeUser = 987602;
        $pendingZeroUser = 987603;
        $positiveUser = 987604;
        $agentUser = 987605;

        $this->seedUserInfo($negativeUser, 'Scan Negative Customer', 2, -100.00);
        $this->seedUserInfo($openTradeUser, 'Scan Open Trade Customer', 2, -50.00);
        $this->seedUserInfo($pendingZeroUser, 'Scan Pending Zero Customer', 2, -30.00);
        $this->seedUserInfo($positiveUser, 'Scan Positive Customer', 2, 50.00);
        $this->seedUserInfo($agentUser, 'Scan Negative Agent', 1, -10.00);

        $this->seedOpenTrade($openTradeUser);
        $this->seedPendingZeroRecord($pendingZeroUser);

        $response = $this->actingAs($admin, 'admin')
            ->postJson('/index/admin/order/oneKeySearch')
            ->assertOk()
            ->assertJsonPath('code', ResponseCode::SUCCESS)
            ->assertJsonPath('msg', 'SUC')
            ->assertJsonPath('err', 'noerr');

        $rows = collect($response->json('data.records'));
        $userIds = $rows->pluck('user_id')->map(fn ($v) => (int) $v)->all();

        $this->assertSame($rows->count(), (int) $response->json('col'));
        $this->assertGreaterThanOrEqual(1, $rows->count());

        $this->assertContains($negativeUser, $userIds);
        $this->assertNotContains($openTradeUser, $userIds);
        $this->assertNotContains($pendingZeroUser, $userIds);
        $this->assertNotContains($positiveUser, $userIds);
        $this->assertNotContains($agentUser, $userIds);

        $row = $rows->firstWhere('user_id', $negativeUser);
        $this->assertSame('Scan Negative Customer', $row['user_name']);
        $this->assertSame('-100.00', $row['balance_before']);
        $this->assertSame('100.00', $row['zero_amount']);
        $this->assertDatabaseHas('whs_exp_zeros', ['user_id' => $negativeUser, 'status' => 1]);
        $this->assertDatabaseMissing('whs_exp_zeros', ['user_id' => $openTradeUser]);
        $this->assertDatabaseMissing('whs_exp_zeros', ['user_id' => $positiveUser]);
        $this->assertDatabaseMissing('whs_exp_zeros', ['user_id' => $agentUser]);
    }

    public function test_legacy_one_key_search_forwards_user_id_filter(): void
    {
        $admin = Admin::query()->findOrFail(1);
        $this->seedUserInfo(987606, 'Scan Filter Target', 2, -20.00);
        $this->seedUserInfo(987607, 'Scan Filter Other', 2, -10.00);

        $response = $this->actingAs($admin, 'admin')
            ->postJson('/index/admin/order/oneKeySearch', ['user_id' => 987606])
            ->assertOk()
            ->assertJsonPath('code', ResponseCode::SUCCESS)
            ->assertJsonPath('data.created_count', 1)
            ->assertJsonPath('col', 1);

        $rows = collect($response->json('data.records'));
        $userIds = $rows->pluck('user_id')->map(fn ($v) => (int) $v)->all();

        $this->assertContains(987606, $userIds);
        $this->assertNotContains(987607, $userIds);
        $this->assertDatabaseHas('whs_exp_zeros', ['user_id' => 987606, 'status' => 1]);
        $this->assertDatabaseMissing('whs_exp_zeros', ['user_id' => 987607]);
    }

    public function test_legacy_one_key_search_supports_legacy_userId_field_alias(): void
    {
        $admin = Admin::query()->findOrFail(1);
        $this->seedUserInfo(987608, 'Scan Alias Target', 2, -15.00);

        $this->actingAs($admin, 'admin')
            ->postJson('/index/admin/order/oneKeySearch', ['userId' => 987608])
            ->assertOk()
            ->assertJsonPath('code', ResponseCode::SUCCESS)
            ->assertJsonPath('data.created_count', 1)
            ->assertJsonPath('col', 1);

        $this->assertDatabaseHas('whs_exp_zeros', ['user_id' => 987608, 'status' => 1]);
    }

    private function seedUserInfo(int $userId, string $userName, int $accountType, float $totalFunds): void
    {
        $now = time();

        DB::table('user_infos')->where('user_id', $userId)->delete();
        DB::table('user_logins')->where('user_id', $userId)->delete();

        $loginId = DB::table('user_logins')->insertGetId([
            'user_id' => $userId,
            'email' => 'legacy-one-key-' . $userId . '@example.test',
            'password' => bcrypt('password'),
            'account_type' => $accountType,
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
            'phone' => '178000' . substr((string) $userId, -4),
            'account_type' => $accountType,
            'parent_id' => 0,
            'level_id' => $accountType === 1 ? 2 : 0,
            'comm_rate' => $accountType === 1 ? 0.2 : 0,
            'auth_status' => 1,
            'total_funds' => $totalFunds,
            'equity' => 0,
            'effective_credit' => 0,
            'created_at' => $now,
            'updated_at' => $now,
            'deleted_at' => null,
        ]);
    }

    private function seedOpenTrade(int $userId): void
    {
        $now = time();

        DB::table('user_trades')->insert([
            'user_id' => $userId,
            'ticket' => $userId * 100,
            'symbol' => 'EURUSD',
            'digits' => 5,
            'cmd' => 0,
            'volume' => 1,
            'open_time' => date('Y-m-d H:i:s', $now),
            'open_price' => 1.1,
            'stop_loss' => 0,
            'take_profit' => 0,
            'close_time' => '1970-01-01 00:00:00',
            'expiration' => null,
            'reason' => 0,
            'conv_rate1' => 1,
            'conv_rate2' => 1,
            'commission' => 0,
            'commission_agent' => 0,
            'swaps' => 0,
            'close_price' => 0,
            'profit' => 0,
            'taxes' => 0,
            'comment' => '',
            'internal_id' => 0,
            'margin_rate' => 0,
            'timestamp_val' => 0,
            'magic' => 0,
            'gw_volume' => 0,
            'gw_open_price' => 0,
            'gw_close_price' => 0,
            'modify_time' => date('Y-m-d H:i:s', $now),
            'settlement_status' => 0,
            'settled_at' => null,
            'created_at' => $now,
            'updated_at' => $now,
            'deleted_at' => null,
        ]);
    }

    private function seedPendingZeroRecord(int $userId): void
    {
        $now = time();

        DB::table('whs_exp_zeros')->insert([
            'user_id' => $userId,
            'user_name' => 'Scan Pending Zero Customer',
            'balance' => -30.00,
            'credit' => 0,
            'status' => 1,
            'md5_key' => 'scan-pending-' . $userId,
            'created_by' => 'test',
            'updated_by' => 'test',
            'created_at' => $now,
            'updated_at' => $now,
            'deleted_at' => null,
        ]);
    }
}
