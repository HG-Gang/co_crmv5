<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/02
 * Time: 01:55
 */

/**
 * 后台风险追保（Margin Call）列表接口数值筛选参数严格校验的功能测试。
 *
 * 文件功能：
 * - 验证 user_id、login、max_margin_level 等数值筛选传入非严格数字时接口返回校验失败。
 * - 验证校验失败时不返回测试账户数据。
 *
 * 适用场景：
 * - 风控后台追保账户列表的数值条件筛选，防止非法数值注入查询。
 *
 * 入参例子：
 * - POST /api/admin/riskMarginCalls，body：{"user_id": "983741abc", "limit": 5}。
 *
 * 返回值：
 * - 校验失败返回 code=ResponseCode::VALIDATION_FAILED。
 *
 * 异常或失败场景：
 * - user_id、login、max_margin_level 为非严格整数时接口拒绝查询并返回校验失败响应。
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

class AdminRiskMarginCallsNumericFilterValidationClosureModuleTest extends TestCase
{
    use DatabaseTransactions;

    /**
     * 追保（margin call）列表校验用例的夹具业务用户 ID。验证按 user_id 过滤拒绝非数字输入。
     * @var int
     */
    private const TEST_USER_ID = 983741;
    /**
     * 夹具用户的 MT4 登录号。断言追保列表输出的登录号正确。
     * @var int
     */
    private const TEST_LOGIN = 98374101;
    /**
     * 夹具用户的 user_name 标记。断言返回记录归属正确用户。
     * @var string
     */
    private const TEST_USER_NAME = 'Risk Margin Call Numeric Validation User';

    protected function tearDown(): void
    {
        DB::table('mt4_users')->where('login', self::TEST_LOGIN)->delete();
        DB::table('user_infos')->where('user_id', self::TEST_USER_ID)->delete();

        parent::tearDown();
    }

    // 验证风险追保列表对多种非严格数值筛选均返回校验失败且不返回测试账户。
    public function test_risk_margin_calls_rejects_non_strict_numeric_filters_without_returning_account(): void
    {
        $actor = $this->ensureSuperAdmin();
        $this->createMarginCallAccount();

        foreach ($this->invalidNumericFilters() as $payload) {
            $response = $this->withoutMiddleware([AdminAuthenticate::class, JwtAuthMiddleware::class, SingleSignOn::class, CheckPermission::class])
                ->actingAs($actor, 'admin')
                ->post('/api/admin/riskMarginCalls', $payload + ['limit' => 5]);

            $response->assertOk()
                ->assertJsonPath('code', ResponseCode::VALIDATION_FAILED);

            $this->assertStringNotContainsString((string) self::TEST_LOGIN, $response->getContent());
            $this->assertStringNotContainsString(self::TEST_USER_NAME, $response->getContent());
        }
    }

    // 校验最终检查清单文档记录了风险追保数值筛选校验边界。
    public function test_final_checklist_records_risk_margin_calls_numeric_filter_validation_boundary(): void
    {
        $checklist = file_get_contents(base_path('docs/admin-backend-blade-permission-final-checklist.md')) ?: '';

        $this->assertStringContainsString('## 318.', $checklist);
        $this->assertStringContainsString('RiskController::marginCalls', $checklist);
        $this->assertStringContainsString('/api/admin/riskMarginCalls', $checklist);
        $this->assertStringContainsString('user_infos.user_id', $checklist);
        $this->assertStringContainsString('mt4_users.login', $checklist);
        $this->assertStringContainsString('max_margin_level', $checklist);
        $this->assertStringContainsString('AdminRiskMarginCallsNumericFilterValidationClosureModuleTest', $checklist);
    }

    /**
     * @return array<int, array<string, string>>
     */
    private function invalidNumericFilters(): array
    {
        return [
            ['user_id' => self::TEST_USER_ID . 'abc'],
            ['login' => self::TEST_LOGIN . 'abc'],
            ['max_margin_level' => '100abc'],
        ];
    }

    private function ensureSuperAdmin(): Admin
    {
        $now = time();

        DB::table('admins')->updateOrInsert(
            ['id' => 1],
            [
                'username' => 'admin-risk-margin-calls-numeric-super',
                'email' => 'admin-risk-margin-calls-numeric-super@example.test',
                'password' => Hash::make('password'),
                'status' => 1,
                'created_at' => $now,
                'updated_at' => $now,
                'deleted_at' => null,
            ]
        );

        return Admin::query()->findOrFail(1);
    }

    private function createMarginCallAccount(): void
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
                'mt4_group' => 'risk-margin-call-validation',
                'mt4_code' => (string) self::TEST_LOGIN,
                'total_funds' => 0,
                'equity' => 0,
                'effective_credit' => 0,
                'created_at' => $now - 3600,
                'updated_at' => $now,
                'deleted_at' => null,
            ]
        );

        DB::table('mt4_users')->updateOrInsert(
            ['login' => self::TEST_LOGIN],
            [
                'name' => self::TEST_USER_NAME,
                'group' => 'risk-margin-call-validation',
                'balance' => 1000.00,
                'equity' => 80.00,
                'margin' => 100.00,
                'margin_free' => -20.00,
                'leverage' => 100,
                'created_at' => $now,
                'updated_at' => $now,
                'deleted_at' => null,
            ]
        );
    }
}
