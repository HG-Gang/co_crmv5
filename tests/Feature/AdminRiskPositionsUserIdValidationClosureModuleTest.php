<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/02
 * Time: 01:55
 */

/**
 * 后台风险持仓列表接口 user_id 筛选参数严格校验的功能测试。
 *
 * 文件功能：
 * - 验证 user_id 传入非严格数字时风险持仓接口返回校验失败。
 * - 验证校验失败时不返回测试持仓交易数据。
 *
 * 适用场景：
 * - 风控后台持仓列表的 user_id 精确筛选，防止非法数值注入查询。
 *
 * 入参例子：
 * - POST /api/admin/riskPositions，body：{"user_id": "983731abc", "limit": 5}。
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

class AdminRiskPositionsUserIdValidationClosureModuleTest extends TestCase
{
    use DatabaseTransactions;

    /**
     * 风险持仓校验用例的夹具业务用户 ID。验证按 user_id 过滤拒绝非数字输入。
     * @var int
     */
    private const TEST_USER_ID = 983731;
    /**
     * 夹具订单 ticket。断言过滤命中的是本用户订单。
     * @var int
     */
    private const TEST_TICKET = 98373101;
    /**
     * 夹具用户的 user_name 标记。断言返回记录归属正确用户。
     * @var string
     */
    private const TEST_USER_NAME = 'Risk Position User ID Validation User';

    protected function tearDown(): void
    {
        DB::table('mt4_trades')->where('login', self::TEST_USER_ID)->delete();
        DB::table('user_infos')->where('user_id', self::TEST_USER_ID)->delete();

        parent::tearDown();
    }

    // 验证风险持仓对非严格 user_id 筛选返回校验失败且不返回测试持仓。
    public function test_risk_positions_rejects_non_strict_user_id_filter_without_returning_trade(): void
    {
        $actor = $this->ensureSuperAdmin();
        $this->createRiskPosition();

        $response = $this->withoutMiddleware([AdminAuthenticate::class, JwtAuthMiddleware::class, SingleSignOn::class, CheckPermission::class])
            ->actingAs($actor, 'admin')
            ->post('/api/admin/riskPositions', [
                'user_id' => self::TEST_USER_ID . 'abc',
                'limit' => 5,
            ]);

        $response->assertOk()
            ->assertJsonPath('code', ResponseCode::VALIDATION_FAILED);

        $this->assertStringNotContainsString((string) self::TEST_TICKET, $response->getContent());
        $this->assertStringNotContainsString(self::TEST_USER_NAME, $response->getContent());
    }

    // 校验最终检查清单文档记录了风险持仓 user_id 校验边界。
    public function test_final_checklist_records_risk_positions_user_id_validation_boundary(): void
    {
        $checklist = file_get_contents(base_path('docs/admin-backend-blade-permission-final-checklist.md')) ?: '';

        $this->assertStringContainsString('## 317.', $checklist);
        $this->assertStringContainsString('RiskController::positions', $checklist);
        $this->assertStringContainsString('/api/admin/riskPositions', $checklist);
        $this->assertStringContainsString('mt4_trades.login', $checklist);
        $this->assertStringContainsString('AdminRiskPositionsUserIdValidationClosureModuleTest', $checklist);
    }

    private function ensureSuperAdmin(): Admin
    {
        $now = time();

        DB::table('admins')->updateOrInsert(
            ['id' => 1],
            [
                'username' => 'admin-risk-positions-user-id-super',
                'email' => 'admin-risk-positions-user-id-super@example.test',
                'password' => Hash::make('password'),
                'status' => 1,
                'created_at' => $now,
                'updated_at' => $now,
                'deleted_at' => null,
            ]
        );

        return Admin::query()->findOrFail(1);
    }

    private function createRiskPosition(): void
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
                'mt4_group' => 'risk-position-validation',
                'total_funds' => 0,
                'equity' => 0,
                'effective_credit' => 0,
                'created_at' => $now - 3600,
                'updated_at' => $now,
                'deleted_at' => null,
            ]
        );

        DB::table('mt4_trades')->updateOrInsert(
            ['ticket' => self::TEST_TICKET],
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
    }
}
