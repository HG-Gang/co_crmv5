<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/02
 * Time: 01:46
 */

/**
 * 文件功能：验证入金详情、审核通过、审核拒绝接口对请求体 id 的严格校验，
 *           非法 id 不得返回或变更入金记录，并核对最终检查清单文档。
 *
 * 适用场景：后台 /api/admin/depositDetail、depositApprove、depositReject
 *           接口的输入校验回归测试。
 *
 * 入参例子：
 * - POST /api/admin/depositDetail：{id}
 * - POST /api/admin/depositApprove：{id}
 * - POST /api/admin/depositReject：{id, reason}
 *
 * 返回值：
 * - id 带非数字后缀时统一返回 code=VALIDATION_FAILED，记录状态与备注保持原样。
 *
 * 异常或失败场景：
 * - 非严格数字 id（如 '{id}abc'）时校验失败，不产生任何业务变更。
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

class AdminDepositRequestIdValidationClosureModuleTest extends TestCase
{
    use DatabaseTransactions;

    protected function tearDown(): void
    {
        DB::table('deposit_records')
            ->where('local_order_no', 'like', 'deposit-id-validation-%')
            ->delete();

        parent::tearDown();
    }

    // 入金详情应拒绝非严格 id 且不返回记录。
    public function test_deposit_detail_rejects_non_strict_id_without_returning_record(): void
    {
        $actor = $this->ensureSuperAdmin();
        $depositId = $this->createDepositRecord('deposit-id-validation-detail', '01', 'detail original remark');

        $response = $this->withoutMiddleware([AdminAuthenticate::class, JwtAuthMiddleware::class, SingleSignOn::class, CheckPermission::class])
            ->actingAs($actor, 'admin')
            ->post('/api/admin/depositDetail', [
                'id' => $depositId . 'abc',
            ]);

        $response->assertOk()
            ->assertJsonPath('code', ResponseCode::VALIDATION_FAILED);

        $this->assertStringNotContainsString('deposit-id-validation-detail', $response->getContent());
    }

    // 入金审核通过应拒绝非严格 id 且不审批记录。
    public function test_deposit_approve_rejects_non_strict_id_without_approving_record(): void
    {
        $actor = $this->ensureSuperAdmin();
        $depositId = $this->createDepositRecord('deposit-id-validation-approve', '01', 'approve original remark');

        $response = $this->withoutMiddleware([AdminAuthenticate::class, JwtAuthMiddleware::class, SingleSignOn::class, CheckPermission::class])
            ->actingAs($actor, 'admin')
            ->post('/api/admin/depositApprove', [
                'id' => $depositId . 'abc',
            ]);

        $response->assertOk()
            ->assertJsonPath('code', ResponseCode::VALIDATION_FAILED);

        $this->assertDatabaseHas('deposit_records', [
            'id' => $depositId,
            'status' => '01',
            'payment_time' => null,
            'remarks' => 'approve original remark',
        ]);
    }

    // 入金审核拒绝应拒绝非严格 id 且不改动记录。
    public function test_deposit_reject_rejects_non_strict_id_without_rejecting_record(): void
    {
        $actor = $this->ensureSuperAdmin();
        $depositId = $this->createDepositRecord('deposit-id-validation-reject', '01', 'reject original remark');

        $response = $this->withoutMiddleware([AdminAuthenticate::class, JwtAuthMiddleware::class, SingleSignOn::class, CheckPermission::class])
            ->actingAs($actor, 'admin')
            ->post('/api/admin/depositReject', [
                'id' => $depositId . 'abc',
                'reason' => 'changed reject reason',
            ]);

        $response->assertOk()
            ->assertJsonPath('code', ResponseCode::VALIDATION_FAILED);

        $this->assertDatabaseHas('deposit_records', [
            'id' => $depositId,
            'status' => '01',
            'remarks' => 'reject original remark',
        ]);
    }

    // 核对最终检查清单文档记录了入金请求 id 校验边界。
    public function test_final_checklist_records_deposit_request_id_validation_boundary(): void
    {
        $checklist = file_get_contents(base_path('docs/admin-backend-blade-permission-final-checklist.md')) ?: '';

        $this->assertStringContainsString('## 305.', $checklist);
        $this->assertStringContainsString('DepositController::show', $checklist);
        $this->assertStringContainsString('DepositController::approve', $checklist);
        $this->assertStringContainsString('DepositController::reject', $checklist);
        $this->assertStringContainsString('/api/admin/depositDetail', $checklist);
        $this->assertStringContainsString('/api/admin/depositApprove', $checklist);
        $this->assertStringContainsString('/api/admin/depositReject', $checklist);
        $this->assertStringContainsString('deposit_records.id', $checklist);
        $this->assertStringContainsString('AdminDepositRequestIdValidationClosureModuleTest', $checklist);
    }

    private function ensureSuperAdmin(): Admin
    {
        $now = time();

        DB::table('admins')->updateOrInsert(
            ['id' => 1],
            [
                'username' => 'admin-deposit-request-id-super',
                'email' => 'admin-deposit-request-id-super@example.test',
                'password' => Hash::make('password'),
                'status' => 1,
                'created_at' => $now,
                'updated_at' => $now,
                'deleted_at' => null,
            ]
        );

        return Admin::query()->findOrFail(1);
    }

    private function createDepositRecord(string $localOrderNo, string $status, string $remarks): int
    {
        $now = time();

        return (int) DB::table('deposit_records')->insertGetId([
            'user_id' => 983001,
            'user_name' => 'Deposit ID Validation User',
            'mt4_ticket' => 0,
            'amount' => 128.50,
            'actual_amount' => 0,
            'exchange_rate' => 1,
            'channel_name' => 'test channel',
            'channel_order_no' => '',
            'local_order_no' => $localOrderNo,
            'status' => $status,
            'payment_time' => null,
            'remarks' => $remarks,
            'created_by' => 'test',
            'updated_by' => 'test',
            'created_at' => $now,
            'updated_at' => $now,
            'deleted_at' => null,
        ]);
    }
}
