<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/02
 * Time: 01:55
 */

/**
 * 后台交易列表与持仓接口 user_id 筛选参数严格校验的功能测试。
 *
 * 文件功能：
 * - 验证 user_id 传入非严格数字时交易列表、持仓、历史持仓接口均返回校验失败。
 * - 验证校验失败时不返回测试的开仓与平仓交易记录。
 *
 * 适用场景：
 * - 后台交易记录页面的 user_id 精确筛选，防止非法数值注入查询。
 *
 * 入参例子：
 * - POST /api/admin/tradeList、/api/admin/openPositions、/api/admin/closedPositions，body：{"user_id": "983781abc", "limit": 5}。
 *
 * 返回值：
 * - 校验失败返回 code=ResponseCode::VALIDATION_FAILED。
 *
 * 异常或失败场景：
 * - user_id 非严格整数时接口拒绝查询并返回校验失败响应。
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
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminTradeUserIdValidationClosureModuleTest extends TestCase
{
    use DatabaseTransactions;

    /**
     * 交易校验用例的夹具业务用户 ID。验证交易列表按 user_id 过滤时拒绝非数字输入且正确匹配。
     * @var int
     */
    private const TEST_USER_ID = 983781;
    /**
     * 夹具开仓订单 ticket。断言 user_id 过滤命中的是本用户订单。
     * @var int
     */
    private const OPEN_TICKET = 98378101;
    /**
     * 夹具平仓订单 ticket，与 OPEN_TICKET 一起覆盖持仓状态维度。
     * @var int
     */
    private const CLOSED_TICKET = 98378102;
    /**
     * 夹具用户的 user_name 标记。断言返回记录归属正确用户。
     * @var string
     */
    private const TEST_USER_NAME = 'Trade User ID Validation User';

    protected function tearDown(): void
    {
        DB::table('mt4_trades')->where('login', self::TEST_USER_ID)->delete();
        DB::table('user_infos')->where('user_id', self::TEST_USER_ID)->delete();

        parent::tearDown();
    }

    // 验证三个交易相关接口对非严格 user_id 筛选均返回校验失败且不返回交易记录。
    public function test_trade_endpoints_reject_non_strict_user_id_filter_without_returning_trade(): void
    {
        $actor = $this->ensureSuperAdmin();
        $this->createTrades();

        foreach ($this->tradeEndpoints() as $endpoint) {
            $response = $this->withoutMiddleware([AdminAuthenticate::class, JwtAuthMiddleware::class, SingleSignOn::class, CheckPermission::class])
                ->actingAs($actor, 'admin')
                ->post($endpoint, [
                    'user_id' => self::TEST_USER_ID . 'abc',
                    'limit' => 5,
                ]);

            $response->assertOk()
                ->assertJsonPath('code', ResponseCode::VALIDATION_FAILED);

            $this->assertStringNotContainsString((string) self::OPEN_TICKET, $response->getContent());
            $this->assertStringNotContainsString((string) self::CLOSED_TICKET, $response->getContent());
            $this->assertStringNotContainsString(self::TEST_USER_NAME, $response->getContent());
        }
    }

    // 校验最终检查清单文档记录了交易 user_id 校验边界。
    public function test_final_checklist_records_trade_user_id_validation_boundary(): void
    {
        $checklist = file_get_contents(base_path('docs/admin-backend-blade-permission-final-checklist.md')) ?: '';

        $this->assertStringContainsString('## 324.', $checklist);
        $this->assertStringContainsString('TradeController::index', $checklist);
        $this->assertStringContainsString('TradeController::openPositions', $checklist);
        $this->assertStringContainsString('TradeController::closedPositions', $checklist);
        $this->assertStringContainsString('/api/admin/tradeList', $checklist);
        $this->assertStringContainsString('/api/admin/openPositions', $checklist);
        $this->assertStringContainsString('/api/admin/closedPositions', $checklist);
        $this->assertStringContainsString('mt4_trades.login', $checklist);
        $this->assertStringContainsString('AdminTradeUserIdValidationClosureModuleTest', $checklist);
    }

    /**
     * @return array<int, string>
     */
    private function tradeEndpoints(): array
    {
        return [
            '/api/admin/tradeList',
            '/api/admin/openPositions',
            '/api/admin/closedPositions',
        ];
    }

    private function ensureSuperAdmin(): Admin
    {
        $now = time();

        DB::table('admins')->updateOrInsert(
            ['id' => 1],
            [
                'username' => 'admin-trade-user-id-super',
                'email' => 'admin-trade-user-id-super@example.test',
                'password' => Hash::make('password'),
                'status' => 1,
                'created_at' => $now,
                'updated_at' => $now,
                'deleted_at' => null,
            ]
        );

        return Admin::query()->findOrFail(1);
    }

    private function createTrades(): void
    {
        $now = time();

        DB::table('user_infos')->updateOrInsert(
            ['user_id' => self::TEST_USER_ID],
            [
                'login_id' => 0,
                'user_name' => self::TEST_USER_NAME,
                'phone' => '',
                'gender' => 1,
                'account_type' => 2,
                'parent_id' => 0,
                'family_tree' => (string) self::TEST_USER_ID,
                'mt4_group' => 'trade-user-id-validation',
                'total_funds' => 0,
                'equity' => 0,
                'effective_credit' => 0,
                'created_at' => $now - 3600,
                'updated_at' => $now,
                'deleted_at' => null,
            ]
        );

        DB::table('mt4_trades')->updateOrInsert(
            ['ticket' => self::OPEN_TICKET],
            [
                'login' => self::TEST_USER_ID,
                'symbol' => 'XAUUSD',
                'cmd' => 0,
                'volume' => 1.00,
                'open_price' => 2300.00,
                'close_price' => 0,
                'commission' => -1.00,
                'swaps' => 0,
                'profit' => 12.34,
                'open_time' => $now - 3600,
                'close_time' => 0,
                'created_at' => $now,
                'updated_at' => $now,
            ]
        );

        DB::table('mt4_trades')->updateOrInsert(
            ['ticket' => self::CLOSED_TICKET],
            [
                'login' => self::TEST_USER_ID,
                'symbol' => 'EURUSD',
                'cmd' => 1,
                'volume' => 2.00,
                'open_price' => 1.0800,
                'close_price' => 1.0850,
                'commission' => -2.00,
                'swaps' => 0,
                'profit' => 20.50,
                'open_time' => $now - 7200,
                'close_time' => $now - 600,
                'created_at' => $now,
                'updated_at' => $now,
            ]
        );
    }
}
