<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/02
 * Time: 02:52
 */

/**
 * 提现处理/完成/驳回接口请求 id 参数严格校验的闭环测试。
 *
 * 文件功能：
 * - 验证 withdrawProcess、withdrawComplete、withdrawReject 三个接口均拒绝非严格 id。
 * - 验证校验失败时提现记录的状态与驳回理由均不被修改。
 * - 验证最终清单文档已记录该请求 id 校验边界。
 *
 * 适用场景：
 * - 管理员提现操作接口入参安全的回归测试。
 *
 * 入参例子：
 * - POST /api/admin/withdrawProcess  id: {withdrawId}abc
 * - POST /api/admin/withdrawComplete id: {withdrawId}abc
 * - POST /api/admin/withdrawReject   id: {withdrawId}abc, reason: changed reject reason
 *
 * 返回值：
 * - 各接口返回 HTTP 200，code 为 VALIDATION_FAILED。
 * - withdraw_records 记录保持原状态与原驳回理由。
 *
 * 异常或失败场景：
 * - 若非严格 id 被放行并改变提现状态，断言失败。
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

class AdminWithdrawRequestIdValidationClosureModuleTest extends TestCase
{
    use DatabaseTransactions;

    protected function tearDown(): void
    {
        DB::table('withdraw_records')
            ->where('local_order_no', 'like', 'withdraw-id-validation-%')
            ->delete();

        parent::tearDown();
    }

    /**
     * 验证提现处理接口拒绝非严格 id 且不标记处理中。
     */
    public function test_withdraw_process_rejects_non_strict_id_without_marking_processing(): void
    {
        $actor = $this->ensureSuperAdmin();
        $withdrawId = $this->createWithdrawRecord('withdraw-id-validation-process', 0, null);

        $response = $this->withoutMiddleware([AdminAuthenticate::class, JwtAuthMiddleware::class, SingleSignOn::class, CheckPermission::class])
            ->actingAs($actor, 'admin')
            ->post('/api/admin/withdrawProcess', [
                'id' => $withdrawId . 'abc',
            ]);

        $response->assertOk()
            ->assertJsonPath('code', ResponseCode::VALIDATION_FAILED);

        $this->assertDatabaseHas('withdraw_records', [
            'id' => $withdrawId,
            'status' => 0,
            'reject_reason' => null,
        ]);
    }

    /**
     * 验证提现完成接口拒绝非严格 id 且不标记已完成。
     */
    public function test_withdraw_complete_rejects_non_strict_id_without_marking_completed(): void
    {
        $actor = $this->ensureSuperAdmin();
        $withdrawId = $this->createWithdrawRecord('withdraw-id-validation-complete', 0, null);

        $response = $this->withoutMiddleware([AdminAuthenticate::class, JwtAuthMiddleware::class, SingleSignOn::class, CheckPermission::class])
            ->actingAs($actor, 'admin')
            ->post('/api/admin/withdrawComplete', [
                'id' => $withdrawId . 'abc',
            ]);

        $response->assertOk()
            ->assertJsonPath('code', ResponseCode::VALIDATION_FAILED);

        $this->assertDatabaseHas('withdraw_records', [
            'id' => $withdrawId,
            'status' => 0,
            'reject_reason' => null,
        ]);
    }

    /**
     * 验证提现驳回接口拒绝非严格 id 且不修改记录与驳回理由。
     */
    public function test_withdraw_reject_rejects_non_strict_id_without_modifying_record(): void
    {
        $actor = $this->ensureSuperAdmin();
        $withdrawId = $this->createWithdrawRecord('withdraw-id-validation-reject', 0, 'original reject reason');

        $response = $this->withoutMiddleware([AdminAuthenticate::class, JwtAuthMiddleware::class, SingleSignOn::class, CheckPermission::class])
            ->actingAs($actor, 'admin')
            ->post('/api/admin/withdrawReject', [
                'id' => $withdrawId . 'abc',
                'reason' => 'changed reject reason',
            ]);

        $response->assertOk()
            ->assertJsonPath('code', ResponseCode::VALIDATION_FAILED);

        $this->assertDatabaseHas('withdraw_records', [
            'id' => $withdrawId,
            'status' => 0,
            'reject_reason' => 'original reject reason',
        ]);
    }

    /**
     * 验证最终清单文档已记录提现请求 id 校验边界（## 306）。
     */
    public function test_final_checklist_records_withdraw_request_id_validation_boundary(): void
    {
        $checklist = file_get_contents(base_path('docs/admin-backend-blade-permission-final-checklist.md')) ?: '';

        $this->assertStringContainsString('## 306.', $checklist);
        $this->assertStringContainsString('WithdrawController::process', $checklist);
        $this->assertStringContainsString('WithdrawController::complete', $checklist);
        $this->assertStringContainsString('WithdrawController::reject', $checklist);
        $this->assertStringContainsString('/api/admin/withdrawProcess', $checklist);
        $this->assertStringContainsString('/api/admin/withdrawComplete', $checklist);
        $this->assertStringContainsString('/api/admin/withdrawReject', $checklist);
        $this->assertStringContainsString('withdraw_records.id', $checklist);
        $this->assertStringContainsString('AdminWithdrawRequestIdValidationClosureModuleTest', $checklist);
    }

    private function ensureSuperAdmin(): Admin
    {
        $now = time();

        DB::table('admins')->updateOrInsert(
            ['id' => 1],
            [
                'username' => 'admin-withdraw-request-id-super',
                'email' => 'admin-withdraw-request-id-super@example.test',
                'password' => Hash::make('password'),
                'status' => 1,
                'created_at' => $now,
                'updated_at' => $now,
                'deleted_at' => null,
            ]
        );

        return Admin::query()->findOrFail(1);
    }

    private function createWithdrawRecord(string $localOrderNo, int $status, string $rejectReason = null): int
    {
        $now = time();

        return (int) DB::table('withdraw_records')->insertGetId([
            'user_id' => 983101,
            'user_name' => 'Withdraw ID Validation User',
            'mt4_ticket' => '',
            'apply_amount' => 88.50,
            'actual_amount' => 0,
            'fee' => 0,
            'exchange_rate' => 1,
            'rmb_fee' => 0,
            'bank_no' => '',
            'bank_name' => '',
            'bank_addr' => '',
            'status' => $status,
            'local_order_no' => $localOrderNo,
            'third_order_no' => '',
            'reject_reason' => $rejectReason,
            'mt4_return_status' => '',
            'created_by' => 'test',
            'updated_by' => 'test',
            'created_at' => $now,
            'updated_at' => $now,
            'deleted_at' => null,
        ]);
    }
}
