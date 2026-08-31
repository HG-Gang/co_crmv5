<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/02
 * Time: 01:55
 */

/**
 * 后台风险 IP 详情接口 user_id 筛选参数严格校验的功能测试。
 *
 * 文件功能：
 * - 验证 user_id 传入非严格数字时风险 IP 详情接口返回校验失败。
 * - 验证校验失败时不返回测试用户的登录详情。
 *
 * 适用场景：
 * - 风控后台按 IP 查看登录明细时的 user_id 精确筛选，防止非法数值注入查询。
 *
 * 入参例子：
 * - POST /api/admin/riskIpDetail，body：{"login_ip": "203.0.113.252", "user_id": "983761abc", "limit": 5}。
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

class AdminRiskIpDetailUserIdValidationClosureModuleTest extends TestCase
{
    use DatabaseTransactions;

    /**
     * 异常 IP 详情校验用例的夹具业务用户 ID。验证按 user_id 过滤拒绝非数字输入。
     * @var int
     */
    private const TEST_USER_ID = 983761;
    /**
     * 夹具登录行写入的 IP（TEST-NET-3 保留段）。详情统计以此聚合。
     * @var string
     */
    private const TEST_LOGIN_IP = '203.0.113.252';
    /**
     * 夹具用户的 user_name 标记。断言返回记录归属正确用户。
     * @var string
     */
    private const TEST_USER_NAME = 'Risk IP Detail User ID Validation User';

    protected function tearDown(): void
    {
        DB::table('user_login_logs')->where('user_id', self::TEST_USER_ID)->delete();
        DB::table('user_infos')->where('user_id', self::TEST_USER_ID)->delete();

        parent::tearDown();
    }

    // 验证风险 IP 详情对非严格 user_id 筛选返回校验失败且不返回登录明细。
    public function test_risk_ip_detail_rejects_non_strict_user_id_filter_without_returning_login_detail(): void
    {
        $actor = $this->ensureSuperAdmin();
        $this->createRiskIpDetail();

        $response = $this->withoutMiddleware([AdminAuthenticate::class, JwtAuthMiddleware::class, SingleSignOn::class, CheckPermission::class])
            ->actingAs($actor, 'admin')
            ->post('/api/admin/riskIpDetail', [
                'login_ip' => self::TEST_LOGIN_IP,
                'user_id' => self::TEST_USER_ID . 'abc',
                'limit' => 5,
            ]);

        $response->assertOk()
            ->assertJsonPath('code', ResponseCode::VALIDATION_FAILED);

        $this->assertStringNotContainsString(self::TEST_LOGIN_IP, $response->getContent());
        $this->assertStringNotContainsString(self::TEST_USER_NAME, $response->getContent());
    }

    // 校验最终检查清单文档记录了风险 IP 详情 user_id 校验边界。
    public function test_final_checklist_records_risk_ip_detail_user_id_validation_boundary(): void
    {
        $checklist = file_get_contents(base_path('docs/admin-backend-blade-permission-final-checklist.md')) ?: '';

        $this->assertStringContainsString('## 320.', $checklist);
        $this->assertStringContainsString('RiskController::riskIpDetail', $checklist);
        $this->assertStringContainsString('/api/admin/riskIpDetail', $checklist);
        $this->assertStringContainsString('user_login_logs.user_id', $checklist);
        $this->assertStringContainsString('AdminRiskIpDetailUserIdValidationClosureModuleTest', $checklist);
    }

    private function ensureSuperAdmin(): Admin
    {
        $now = time();

        DB::table('admins')->updateOrInsert(
            ['id' => 1],
            [
                'username' => 'admin-risk-ip-detail-user-id-super',
                'email' => 'admin-risk-ip-detail-user-id-super@example.test',
                'password' => Hash::make('password'),
                'status' => 1,
                'created_at' => $now,
                'updated_at' => $now,
                'deleted_at' => null,
            ]
        );

        return Admin::query()->findOrFail(1);
    }

    private function createRiskIpDetail(): void
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
            'user_id' => self::TEST_USER_ID,
            'login_ip' => self::TEST_LOGIN_IP,
            'ip_location' => 'Risk IP Detail User ID Test',
            'user_agent' => 'Risk IP Detail User ID Browser',
            'created_at' => $now - 60,
            'updated_at' => $now,
            'deleted_at' => null,
        ]);
    }
}
