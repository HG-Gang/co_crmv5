<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/02
 * Time: 01:55
 */

/**
 * 后台风险 IP 列表接口数值筛选参数严格校验的功能测试。
 *
 * 文件功能：
 * - 验证 user_id、min_user_count 等数值筛选传入非严格数字时接口返回校验失败。
 * - 验证校验失败时不返回测试 IP 分组数据。
 *
 * 适用场景：
 * - 风控后台按 IP 聚合统计列表的数值条件筛选，防止非法数值注入查询。
 *
 * 入参例子：
 * - POST /api/admin/riskIpList，body：{"user_id": "983751abc", "login_ip": "203.0.113.251", "limit": 5}。
 *
 * 返回值：
 * - 校验失败返回 code=ResponseCode::VALIDATION_FAILED。
 *
 * 异常或失败场景：
 * - user_id、min_user_count 为非严格整数时接口拒绝查询并返回校验失败响应。
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

class AdminRiskIpListNumericFilterValidationClosureModuleTest extends TestCase
{
    use DatabaseTransactions;

    /**
     * 异常 IP 列表用例的夹具业务用户 ID。验证列表按 user_id 过滤时拒绝非数字输入。
     * @var int
     */
    private const TEST_USER_ID = 983751;
    /**
     * 另一业务用户 ID，用于验证过滤后不串数据。
     * @var int
     */
    private const OTHER_USER_ID = 983752;
    /**
     * 夹具登录行写入的 IP（TEST-NET-3 保留段）。异常 IP 统计以此聚合。
     * @var string
     */
    private const TEST_LOGIN_IP = '203.0.113.251';
    /**
     * 夹具用户的 user_name 标记。断言返回记录归属正确用户。
     * @var string
     */
    private const TEST_USER_NAME = 'Risk IP List Numeric Validation User';

    protected function tearDown(): void
    {
        DB::table('user_login_logs')->whereIn('user_id', [self::TEST_USER_ID, self::OTHER_USER_ID])->delete();
        DB::table('user_infos')->whereIn('user_id', [self::TEST_USER_ID, self::OTHER_USER_ID])->delete();

        parent::tearDown();
    }

    // 验证风险 IP 列表对多种非严格数值筛选均返回校验失败且不返回 IP 分组。
    public function test_risk_ip_list_rejects_non_strict_numeric_filters_without_returning_ip_group(): void
    {
        $actor = $this->ensureSuperAdmin();
        $this->createRiskIpLogs();

        foreach ($this->invalidNumericFilters() as $payload) {
            $response = $this->withoutMiddleware([AdminAuthenticate::class, JwtAuthMiddleware::class, SingleSignOn::class, CheckPermission::class])
                ->actingAs($actor, 'admin')
                ->post('/api/admin/riskIpList', $payload + [
                    'login_ip' => self::TEST_LOGIN_IP,
                    'limit' => 5,
                ]);

            $response->assertOk()
                ->assertJsonPath('code', ResponseCode::VALIDATION_FAILED);

            $this->assertStringNotContainsString(self::TEST_LOGIN_IP, $response->getContent());
            $this->assertStringNotContainsString(self::TEST_USER_NAME, $response->getContent());
        }
    }

    // 校验最终检查清单文档记录了风险 IP 列表数值筛选校验边界。
    public function test_final_checklist_records_risk_ip_list_numeric_filter_validation_boundary(): void
    {
        $checklist = file_get_contents(base_path('docs/admin-backend-blade-permission-final-checklist.md')) ?: '';

        $this->assertStringContainsString('## 319.', $checklist);
        $this->assertStringContainsString('RiskController::riskIpList', $checklist);
        $this->assertStringContainsString('/api/admin/riskIpList', $checklist);
        $this->assertStringContainsString('user_login_logs.user_id', $checklist);
        $this->assertStringContainsString('min_user_count', $checklist);
        $this->assertStringContainsString('AdminRiskIpListNumericFilterValidationClosureModuleTest', $checklist);
    }

    /**
     * @return array<int, array<string, string>>
     */
    private function invalidNumericFilters(): array
    {
        return [
            ['user_id' => self::TEST_USER_ID . 'abc'],
            ['min_user_count' => '2abc'],
        ];
    }

    private function ensureSuperAdmin(): Admin
    {
        $now = time();

        DB::table('admins')->updateOrInsert(
            ['id' => 1],
            [
                'username' => 'admin-risk-ip-list-numeric-super',
                'email' => 'admin-risk-ip-list-numeric-super@example.test',
                'password' => Hash::make('password'),
                'status' => 1,
                'created_at' => $now,
                'updated_at' => $now,
                'deleted_at' => null,
            ]
        );

        return Admin::query()->findOrFail(1);
    }

    private function createRiskIpLogs(): void
    {
        $now = time();

        foreach ([self::TEST_USER_ID => self::TEST_USER_NAME, self::OTHER_USER_ID => 'Risk IP List Numeric Other User'] as $userId => $userName) {
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
                    'created_at' => $now - 3600,
                    'updated_at' => $now,
                    'deleted_at' => null,
                ]
            );

            DB::table('user_login_logs')->insert([
                'login_id' => 0,
                'user_id' => $userId,
                'login_ip' => self::TEST_LOGIN_IP,
                'ip_location' => 'Risk IP List Numeric Test',
                'user_agent' => 'Risk IP List Numeric Browser',
                'created_at' => $now - ($userId === self::TEST_USER_ID ? 60 : 30),
                'updated_at' => $now,
                'deleted_at' => null,
            ]);
        }
    }
}
