<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/02
 * Time: 01:55
 */

/**
 * 后台实时佣金列表与导出接口 user_id 筛选参数严格校验的功能测试。
 *
 * 文件功能：
 * - 验证 user_id 传入非严格数字时实时佣金列表与导出接口均返回校验失败。
 * - 验证校验失败时不返回测试佣金记录、不流式导出 CSV。
 *
 * 适用场景：
 * - 实时佣金管理页面的 user_id 精确筛选与导出，防止非法数值注入查询。
 *
 * 入参例子：
 * - POST /api/admin/realtimeCommissionList，body：{"user_id": "983791abc", "limit": 5}。
 * - POST /api/admin/exportRealtimeCommissions，body：{"user_id": "983791abc"}。
 *
 * 返回值：
 * - 校验失败返回 code=ResponseCode::VALIDATION_FAILED，导出响应非 text/csv。
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

class AdminRealtimeCommissionUserIdValidationClosureModuleTest extends TestCase
{
    use DatabaseTransactions;

    /**
     * 实时佣金列表校验用例的夹具业务用户 ID。验证按 user_id 过滤拒绝非数字输入。
     * @var int
     */
    private const TEST_USER_ID = 983791;
    /**
     * 夹具订单 ticket。佣金记录按它构造样本。
     * @var int
     */
    private const TEST_TICKET = 98379101;
    /**
     * 夹具用户的 user_name 标记。断言返回记录归属正确用户。
     * @var string
     */
    private const TEST_USER_NAME = 'Realtime Commission User ID Validation User';

    protected function tearDown(): void
    {
        DB::table('mt4_trades')->where('login', self::TEST_USER_ID)->delete();
        DB::table('user_infos')->where('user_id', self::TEST_USER_ID)->delete();

        parent::tearDown();
    }

    // 验证实时佣金列表对非严格 user_id 筛选返回校验失败且不返回测试记录。
    public function test_realtime_commission_list_rejects_non_strict_user_id_filter_without_returning_record(): void
    {
        $actor = $this->ensureSuperAdmin();
        $this->createRealtimeCommission();

        $response = $this->withoutMiddleware([AdminAuthenticate::class, JwtAuthMiddleware::class, SingleSignOn::class, CheckPermission::class])
            ->actingAs($actor, 'admin')
            ->post('/api/admin/realtimeCommissionList', [
                'user_id' => self::TEST_USER_ID . 'abc',
                'limit' => 5,
            ]);

        $response->assertOk()
            ->assertJsonPath('code', ResponseCode::VALIDATION_FAILED);

        $this->assertStringNotContainsString((string) self::TEST_TICKET, $response->getContent());
        $this->assertStringNotContainsString(self::TEST_USER_NAME, $response->getContent());
    }

    // 验证实时佣金导出对非严格 user_id 筛选返回校验失败且不流式输出 CSV。
    public function test_realtime_commission_export_rejects_non_strict_user_id_filter_without_streaming_csv(): void
    {
        $actor = $this->ensureSuperAdmin();
        $this->createRealtimeCommission();

        $response = $this->withoutMiddleware([AdminAuthenticate::class, JwtAuthMiddleware::class, SingleSignOn::class, CheckPermission::class])
            ->actingAs($actor, 'admin')
            ->post('/api/admin/exportRealtimeCommissions', [
                'user_id' => self::TEST_USER_ID . 'abc',
            ]);

        $this->assertStringNotContainsString('text/csv', (string) $response->headers->get('content-type'));

        $response->assertOk()
            ->assertJsonPath('code', ResponseCode::VALIDATION_FAILED);
    }

    // 校验最终检查清单文档记录了实时佣金 user_id 校验边界。
    public function test_final_checklist_records_realtime_commission_user_id_validation_boundary(): void
    {
        $checklist = file_get_contents(base_path('docs/admin-backend-blade-permission-final-checklist.md')) ?: '';

        $this->assertStringContainsString('## 328.', $checklist);
        $this->assertStringContainsString('RealtimeCommissionController::realtimeCommissionList', $checklist);
        $this->assertStringContainsString('RealtimeCommissionController::exportRealtimeCommissions', $checklist);
        $this->assertStringContainsString('/api/admin/realtimeCommissionList', $checklist);
        $this->assertStringContainsString('/api/admin/exportRealtimeCommissions', $checklist);
        $this->assertStringContainsString('mt4_trades.login', $checklist);
        $this->assertStringContainsString('AdminRealtimeCommissionUserIdValidationClosureModuleTest', $checklist);
    }

    private function ensureSuperAdmin(): Admin
    {
        $now = time();

        DB::table('admins')->updateOrInsert(
            ['id' => 1],
            [
                'username' => 'admin-realtime-commission-user-id-super',
                'email' => 'admin-realtime-commission-user-id-super@example.test',
                'password' => Hash::make('password'),
                'status' => 1,
                'created_at' => $now,
                'updated_at' => $now,
                'deleted_at' => null,
            ]
        );

        return Admin::query()->findOrFail(1);
    }

    private function createRealtimeCommission(): void
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
                'mt4_group' => 'realtime-commission-validation',
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
                'symbol' => 'REBATE',
                'cmd' => 6,
                'volume' => 0,
                'open_price' => 0,
                'close_price' => 0,
                'commission' => 0,
                'swaps' => 0,
                'profit' => 18.88,
                'open_time' => $now - 3600,
                'close_time' => $now - 60,
                'created_at' => $now,
                'updated_at' => $now,
            ]
        );
    }
}
