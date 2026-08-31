<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/17
 * Time: 22:24
 */

declare(strict_types=1);

namespace Tests\Feature;

use App\Constants\ResponseCode;
use App\Http\Middleware\JwtAuthMiddleware;
use App\Http\Middleware\SingleSignOn;
use App\Models\UserLogin;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * 旧前台本人持仓汇总入口闭环测试。
 *
 * 文件功能：
 * - 固定旧 MT4 资金备注代码，验证入金、出金、已平仓统计和品种手数分类。
 * - 验证非旧协议的自然语言备注不会混入资金汇总，避免扩大统计口径。
 * - 通过真实旧 session 和 HTTP 路由验证认证、中间件、控制器和数据聚合链路。
 */
class FrontLegacyPositionSummary2ClosureModuleTest extends TestCase
{
    use DatabaseTransactions;

    /**
     * 验证旧 MT4 固定备注代码的资金与交易汇总口径。
     *
     * @return void 成功时返回一条本人汇总行；不属于旧协议的 deposit 文本不得计入余额入金。
     */
    public function test_legacy_position_summary2_uses_exact_mt4_balance_comment_codes(): void
    {
        $userId = 512541001;
        $this->insertActiveUser($userId);
        $this->insertSymbol('LPS2XAUA', 1);
        $this->insertSymbol('LPS2EURB', 5);

        $this->insertTrade($userId, 512541101, 'LPS2XAUA', 6, 0, 1000, 'DBAA-20260724');
        $this->insertTrade($userId, 512541102, 'LPS2XAUA', 6, 0, 100, 'WBIR-20260724');
        $this->insertTrade($userId, 512541103, 'LPS2XAUA', 6, 0, 75, 'deposit-manual');
        $this->insertTrade($userId, 512541104, 'LPS2XAUA', 6, 0, -200, 'WBAA-20260724');
        $this->insertTrade($userId, 512541105, 'LPS2XAUA', 6, 0, -50, 'DBZR-20260724');
        $this->insertTrade($userId, 512541106, 'LPS2XAUA', 0, 200, 50, '', -3, -1, 1);
        $this->insertTrade($userId, 512541107, 'LPS2EURB', 1, 100, -10, '', -1, 0, 1);
        $this->insertTrade($userId, 512541108, 'LPS2XAUA', 0, 500, 99, '', -99, -9, 0);

        $response = $this->withSession(['suser' => ['user_id' => $userId]])
            ->postJson('/user/position/positionSummary2Search', [
                'startdate' => '2026-07-24',
                'enddate' => '2026-07-24',
            ]);

        $response
            ->assertOk()
            ->assertJsonPath('code', ResponseCode::SUCCESS)
            ->assertJsonPath('data.count', 1)
            ->assertJsonPath('data.data.0.user_id', $userId)
            ->assertJsonPath('data.data.0.total_yuerj', '1100.00')
            ->assertJsonPath('data.data.0.total_yuecj', '-250.00')
            ->assertJsonPath('data.data.0.total_net_worth', '850.00')
            ->assertJsonPath('data.data.0.total_profit', '40.00')
            ->assertJsonPath('data.data.0.total_comm', '4.00')
            ->assertJsonPath('data.data.0.total_noble_metal', '2.00')
            ->assertJsonPath('data.data.0.total_currency', '1.00')
            ->assertJsonPath('data.data.0.total_volume', '3.00')
            ->assertJsonPath('data.data.0.total_swaps', '-1.00');

        $login = UserLogin::where('user_id', $userId)->firstOrFail();
        $modernResponse = $this->withoutMiddleware([JwtAuthMiddleware::class, SingleSignOn::class])
            ->actingAs($login, 'user')
            ->getJson('/api/front/positions/self-summary?date_from=2026-07-24&date_to=2026-07-24');

        $modernResponse
            ->assertOk()
            ->assertJsonPath('code', ResponseCode::SUCCESS)
            ->assertJsonPath('data.count', 1)
            ->assertJsonPath('data.data.0.user_id', $userId);

        $this->assertSame(
            $response->json('data.data.0'),
            $modernResponse->json('data.data.0'),
            'Modern self-summary must reuse the legacy DB aggregation without a second data source.'
        );
    }

    /**
     * 写入中间件可识别的活动用户与业务资料。
     *
     * @param int $userId 前台 session、user_logins 和 user_infos 共用的业务用户 ID。
     * @return void 测试事务结束后自动回滚用户和登录资料。
     */
    private function insertActiveUser(int $userId): void
    {
        $now = time();
        // 共享测试库可能残留上次中断的同一夹具；仅清理固定测试用户，保证重复执行仍然可重现。
        DB::table('user_trades')->where('user_id', $userId)->delete();
        DB::table('user_infos')->where('user_id', $userId)->delete();
        DB::table('user_logins')->where('user_id', $userId)->delete();
        $loginId = DB::table('user_logins')->insertGetId([
            'user_id' => $userId,
            'email' => 'legacy-position-summary-' . $userId . '@example.test',
            'password' => Hash::make('password'),
            'account_type' => 2,
            'role_id' => 0,
            'is_enabled' => 1,
            'is_cancelled' => 0,
            'source_type' => 0,
            'jwt_token_id' => '',
            'last_login_ip' => '',
            'last_login_at' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        DB::table('user_infos')->insert([
            'user_id' => $userId,
            'login_id' => $loginId,
            'user_name' => '旧持仓汇总测试用户',
            'phone' => '',
            'gender' => 0,
            'account_type' => 2,
            'parent_id' => 0,
            'family_tree' => (string) $userId,
            'group_id' => 0,
            'level_id' => 0,
            'comm_rate' => 0,
            'auth_status' => 1,
            'total_funds' => 0,
            'used_margin' => 0,
            'avail_margin' => 0,
            'equity' => 0,
            'effective_credit' => 0,
            'risk_ratio' => 0,
            'leverage' => 100,
            'is_mt4_synced' => 1,
            'is_mt4_enabled' => 1,
            'is_mt4_readonly' => 0,
            'is_withdrawal_allowed' => 0,
            'is_deposit_allowed' => 0,
            'is_agent_confirmed' => 0,
            'mt4_group' => '',
            'settle_method' => 1,
            'settle_cycle' => 0,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    /**
     * 写入启用的品种分类配置。
     *
     * @param string $symbol 交易品种编码。
     * @param int $groupId 旧品种分类，1=贵金属、5=货币。
     * @return void 汇总服务通过该配置把成交量归入旧前台的分类列。
     */
    private function insertSymbol(string $symbol, int $groupId): void
    {
        $now = time();
        DB::table('symbol_prices')->where('symbol', $symbol)->delete();
        DB::table('symbol_prices')->insert([
            'symbol' => $symbol,
            'time' => '2026-07-24 10:00:00',
            'bid' => 100,
            'ask' => 101,
            'low' => 99,
            'high' => 102,
            'direction' => 0,
            'digits' => 2,
            'spread' => 10,
            'group_id' => $groupId,
            'status' => 1,
            'modify_time' => '2026-07-24 10:00:00',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    /**
     * 写入符合旧 MT4 数据字段的测试交易。
     *
     * @param int $userId 交易所属业务用户。
     * @param int $ticket MT4 唯一交易单号。
     * @param string $symbol 交易品种。
     * @param int $cmd MT4 命令，6=余额变动，0/1=已平仓交易。
     * @param int $volume 原始成交量，100 表示 1 手。
     * @param float $profit MT4 盈亏或余额变动金额。
     * @param string $comment MT4 备注，用于严格判断旧资金协议。
     * @param float $commission 已平仓交易手续费。
     * @param float $swaps 已平仓交易库存费。
     * @param float $marginRate 非零代表有效的已平仓交易。
     * @return void 交易时间固定在请求筛选区间内。
     */
    private function insertTrade(
        int $userId,
        int $ticket,
        string $symbol,
        int $cmd,
        int $volume,
        float $profit,
        string $comment,
        float $commission = 0,
        float $swaps = 0,
        float $marginRate = 0
    ): void {
        $now = time();
        DB::table('user_trades')->insert([
            'user_id' => $userId,
            'ticket' => $ticket,
            'symbol' => $symbol,
            'digits' => 2,
            'cmd' => $cmd,
            'volume' => $volume,
            'open_time' => '2026-07-24 09:00:00',
            'open_price' => 100,
            'close_time' => '2026-07-24 10:00:00',
            'close_price' => 101,
            'commission' => $commission,
            'swaps' => $swaps,
            'profit' => $profit,
            'margin_rate' => $marginRate,
            'comment' => $comment,
            'modify_time' => '2026-07-24 10:00:00',
            'settlement_status' => 0,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }
}
