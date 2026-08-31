<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/02
 * Time: 01:55
 */

/**
 * 后台权益汇总列表与导出接口数值筛选参数严格校验的功能测试。
 *
 * 文件功能：
 * - 验证 user_id、login、min_equity、max_equity 等数值筛选传入非严格数字时列表与导出接口均返回校验失败。
 * - 验证校验失败时不返回测试账户、不流式导出 CSV。
 *
 * 适用场景：
 * - 权益汇总页面的数值条件筛选与导出，防止非法数值注入查询。
 *
 * 入参例子：
 * - POST /api/admin/rightsSummaryList，body：{"user_id": "983771abc", "limit": 5}。
 * - POST /api/admin/exportRightsSummary，body：{"min_equity": "1000abc"}。
 *
 * 返回值：
 * - 校验失败返回 code=ResponseCode::VALIDATION_FAILED，导出响应非 text/csv。
 *
 * 异常或失败场景：
 * - 任一数值筛选为非严格整数时接口拒绝查询并返回校验失败响应。
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

class AdminRightsSummaryNumericFilterValidationClosureModuleTest extends TestCase
{
    use DatabaseTransactions;

    /**
     * 权益汇总用例的夹具业务用户 ID。验证按 user_id 过滤时拒绝非数字输入。
     * @var int
     */
    private const TEST_USER_ID = 983771;
    /**
     * 夹具用户的 MT4 登录号（user_infos.mt4_code）。验证汇总输出真实登录号。
     * @var int
     */
    private const TEST_LOGIN = 98377101;
    /**
     * 夹具用户的 user_name 标记。断言返回记录归属正确用户。
     * @var string
     */
    private const TEST_USER_NAME = 'Rights Summary Numeric Validation User';
    /**
     * 夹具用户的 MT4 账户名标记。断言 MT4 侧字段映射正确。
     * @var string
     */
    private const TEST_MT4_NAME = 'Rights Summary Numeric MT4 Name';

    protected function tearDown(): void
    {
        DB::table('rights_settlements')->where('user_id', self::TEST_USER_ID)->delete();
        DB::table('mt4_users')->where('login', self::TEST_LOGIN)->delete();
        DB::table('user_infos')->where('user_id', self::TEST_USER_ID)->delete();

        parent::tearDown();
    }

    // 验证权益汇总列表对多种非严格数值筛选均返回校验失败且不返回测试账户。
    public function test_rights_summary_list_rejects_non_strict_numeric_filters_without_returning_account(): void
    {
        $actor = $this->ensureSuperAdmin();
        $this->createRightsSummaryAccount();

        foreach ($this->invalidNumericFilters() as $payload) {
            $response = $this->withoutMiddleware([AdminAuthenticate::class, JwtAuthMiddleware::class, SingleSignOn::class, CheckPermission::class])
                ->actingAs($actor, 'admin')
                ->post('/api/admin/rightsSummaryList', $payload + ['limit' => 5]);

            $response->assertOk()
                ->assertJsonPath('code', ResponseCode::VALIDATION_FAILED);

            $this->assertStringNotContainsString(self::TEST_USER_NAME, $response->getContent());
            $this->assertStringNotContainsString(self::TEST_MT4_NAME, $response->getContent());
        }
    }

    // 验证权益汇总导出对多种非严格数值筛选均返回校验失败且不流式输出 CSV。
    public function test_rights_summary_export_rejects_non_strict_numeric_filters_without_streaming_csv(): void
    {
        $actor = $this->ensureSuperAdmin();
        $this->createRightsSummaryAccount();

        foreach ($this->invalidNumericFilters() as $payload) {
            $response = $this->withoutMiddleware([AdminAuthenticate::class, JwtAuthMiddleware::class, SingleSignOn::class, CheckPermission::class])
                ->actingAs($actor, 'admin')
                ->post('/api/admin/exportRightsSummary', $payload);

            $this->assertStringNotContainsString('text/csv', (string) $response->headers->get('content-type'));

            $response->assertOk()
                ->assertJsonPath('code', ResponseCode::VALIDATION_FAILED);
        }
    }

    // 校验最终检查清单文档记录了权益汇总数值筛选校验边界。
    public function test_final_checklist_records_rights_summary_numeric_filter_validation_boundary(): void
    {
        $checklist = file_get_contents(base_path('docs/admin-backend-blade-permission-final-checklist.md')) ?: '';

        $this->assertStringContainsString('## 322.', $checklist);
        $this->assertStringContainsString('RightsSummaryController::rightsSummaryList', $checklist);
        $this->assertStringContainsString('RightsSummaryController::exportRightsSummary', $checklist);
        $this->assertStringContainsString('/api/admin/rightsSummaryList', $checklist);
        $this->assertStringContainsString('/api/admin/exportRightsSummary', $checklist);
        $this->assertStringContainsString('user_infos.user_id', $checklist);
        $this->assertStringContainsString('mt4_users.login', $checklist);
        $this->assertStringContainsString('mt4_users.equity', $checklist);
        $this->assertStringContainsString('AdminRightsSummaryNumericFilterValidationClosureModuleTest', $checklist);
    }

    /**
     * @return array<int, array<string, string>>
     */
    private function invalidNumericFilters(): array
    {
        return [
            ['user_id' => self::TEST_USER_ID . 'abc'],
            ['login' => self::TEST_LOGIN . 'abc'],
            ['min_equity' => '1000abc'],
            ['max_equity' => '1300abc'],
        ];
    }

    private function ensureSuperAdmin(): Admin
    {
        $now = time();

        DB::table('admins')->updateOrInsert(
            ['id' => 1],
            [
                'username' => 'admin-rights-summary-numeric-super',
                'email' => 'admin-rights-summary-numeric-super@example.test',
                'password' => Hash::make('password'),
                'status' => 1,
                'created_at' => $now,
                'updated_at' => $now,
                'deleted_at' => null,
            ]
        );

        return Admin::query()->findOrFail(1);
    }

    private function createRightsSummaryAccount(): void
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
                'mt4_group' => 'rights-summary-validation',
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
                'name' => self::TEST_MT4_NAME,
                'group' => 'rights-summary-validation',
                'balance' => 1234.56,
                'equity' => 1200.25,
                'margin' => 88.80,
                'margin_free' => 1111.45,
                'leverage' => 200,
                'created_at' => $now,
                'updated_at' => $now,
                'deleted_at' => null,
            ]
        );

        DB::table('rights_settlements')->updateOrInsert(
            ['user_id' => self::TEST_USER_ID],
            [
                'amount' => 77.88,
                'status' => 0,
                'remark' => 'rights numeric validation',
                'created_at' => $now,
                'updated_at' => $now,
                'deleted_at' => null,
            ]
        );
    }
}
