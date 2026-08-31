<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/02
 * Time: 01:49
 */

/**
 * 提现列表 user_id 筛选参数严格校验的闭环测试。
 *
 * 文件功能：
 * - 验证 /api/admin/withdrawList 拒绝非严格 user_id 且不返回提现记录。
 * - 验证最终清单文档已记录该 user_id 校验边界。
 *
 * 适用场景：
 * - 管理员提现列表接口入参安全的回归测试。
 *
 * 入参例子：
 * - POST /api/admin/withdrawList
 *   user_id: 983301abc
 *   per_page: 5
 *
 * 返回值：
 * - 接口返回 HTTP 200，code 为 VALIDATION_FAILED，响应不含目标记录。
 *
 * 异常或失败场景：
 * - 若非严格 user_id 被放行并返回提现记录，断言失败。
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

class AdminWithdrawListUserIdValidationClosureModuleTest extends TestCase
{
    use DatabaseTransactions;

    protected function tearDown(): void
    {
        DB::table('withdraw_records')
            ->where('local_order_no', 'like', 'withdraw-list-user-id-validation-%')
            ->delete();

        parent::tearDown();
    }

    /**
     * 验证提现列表拒绝非严格 user_id 且不返回记录。
     */
    public function test_withdraw_list_rejects_non_strict_user_id_filter_without_returning_records(): void
    {
        $actor = $this->ensureSuperAdmin();
        $userId = 983301;
        $this->createWithdrawRecord($userId, 'withdraw-list-user-id-validation-row');

        $response = $this->withoutMiddleware([AdminAuthenticate::class, JwtAuthMiddleware::class, SingleSignOn::class, CheckPermission::class])
            ->actingAs($actor, 'admin')
            ->post('/api/admin/withdrawList', [
                'user_id' => $userId . 'abc',
                'per_page' => 5,
            ]);

        $response->assertOk()
            ->assertJsonPath('code', ResponseCode::VALIDATION_FAILED);

        $this->assertStringNotContainsString('withdraw-list-user-id-validation-row', $response->getContent());
    }

    /**
     * 验证最终清单文档已记录提现列表 user_id 校验边界（## 310）。
     */
    public function test_final_checklist_records_withdraw_list_user_id_validation_boundary(): void
    {
        $checklist = file_get_contents(base_path('docs/admin-backend-blade-permission-final-checklist.md')) ?: '';

        $this->assertStringContainsString('## 310.', $checklist);
        $this->assertStringContainsString('WithdrawController::index', $checklist);
        $this->assertStringContainsString('/api/admin/withdrawList', $checklist);
        $this->assertStringContainsString('withdraw_records.user_id', $checklist);
        $this->assertStringContainsString('AdminWithdrawListUserIdValidationClosureModuleTest', $checklist);
    }

    private function ensureSuperAdmin(): Admin
    {
        $now = time();

        DB::table('admins')->updateOrInsert(
            ['id' => 1],
            [
                'username' => 'admin-withdraw-list-user-id-super',
                'email' => 'admin-withdraw-list-user-id-super@example.test',
                'password' => Hash::make('password'),
                'status' => 1,
                'created_at' => $now,
                'updated_at' => $now,
                'deleted_at' => null,
            ]
        );

        return Admin::query()->findOrFail(1);
    }

    private function createWithdrawRecord(int $userId, string $localOrderNo): int
    {
        $now = time();

        return (int) DB::table('withdraw_records')->insertGetId([
            'user_id' => $userId,
            'user_name' => 'Withdraw List User ID Validation User',
            'mt4_ticket' => '',
            'apply_amount' => 98.50,
            'actual_amount' => 0,
            'fee' => 0,
            'exchange_rate' => 1,
            'rmb_fee' => 0,
            'bank_no' => '',
            'bank_name' => '',
            'bank_addr' => '',
            'status' => 0,
            'local_order_no' => $localOrderNo,
            'third_order_no' => '',
            'reject_reason' => null,
            'mt4_return_status' => '',
            'created_by' => 'test',
            'updated_by' => 'test',
            'created_at' => $now,
            'updated_at' => $now,
            'deleted_at' => null,
        ]);
    }
}
