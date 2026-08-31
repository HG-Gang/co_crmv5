<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/02
 * Time: 01:45
 */

/**
 * 文件功能：验证佣金结算（commissionSettle）对请求体 id 的严格校验，
 *           非法 id 不得触发结算，并核对最终检查清单文档。
 *
 * 适用场景：后台 /api/admin/commissionSettle 接口的输入校验回归测试。
 *
 * 入参例子：
 * - POST /api/admin/commissionSettle：{id}
 *
 * 返回值：
 * - id 带非数字后缀时返回 code=VALIDATION_FAILED，佣金记录 settle_status 保持原样。
 *
 * 异常或失败场景：
 * - 非严格数字 id（如 '{id}abc'）时校验失败，不做任何结算变更。
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

class AdminCommissionRequestIdValidationClosureModuleTest extends TestCase
{
    use DatabaseTransactions;

    protected function tearDown(): void
    {
        DB::table('commission_records')
            ->where('unique_id', 'like', 'commission-id-validation-%')
            ->delete();

        parent::tearDown();
    }

    // 佣金结算应拒绝非严格 id 且不触发结算变更。
    public function test_commission_settle_rejects_non_strict_id_without_settling_record(): void
    {
        $actor = $this->ensureSuperAdmin();
        $commissionId = $this->createCommissionRecord('commission-id-validation-settle', 1);

        $response = $this->withoutMiddleware([AdminAuthenticate::class, JwtAuthMiddleware::class, SingleSignOn::class, CheckPermission::class])
            ->actingAs($actor, 'admin')
            ->post('/api/admin/commissionSettle', [
                'id' => $commissionId . 'abc',
            ]);

        $response->assertOk()
            ->assertJsonPath('code', ResponseCode::VALIDATION_FAILED);

        $this->assertDatabaseHas('commission_records', [
            'id' => $commissionId,
            'settle_status' => 1,
        ]);
    }

    // 核对最终检查清单文档记录了佣金请求 id 校验边界。
    public function test_final_checklist_records_commission_request_id_validation_boundary(): void
    {
        $checklist = file_get_contents(base_path('docs/admin-backend-blade-permission-final-checklist.md')) ?: '';

        $this->assertStringContainsString('## 307.', $checklist);
        $this->assertStringContainsString('CommissionController::show', $checklist);
        $this->assertStringContainsString('CommissionController::settle', $checklist);
        $this->assertStringContainsString('/api/admin/commissionSettle', $checklist);
        $this->assertStringContainsString('commission_records.id', $checklist);
        $this->assertStringContainsString('AdminCommissionRequestIdValidationClosureModuleTest', $checklist);
    }

    private function ensureSuperAdmin(): Admin
    {
        $now = time();

        DB::table('admins')->updateOrInsert(
            ['id' => 1],
            [
                'username' => 'admin-commission-request-id-super',
                'email' => 'admin-commission-request-id-super@example.test',
                'password' => Hash::make('password'),
                'status' => 1,
                'created_at' => $now,
                'updated_at' => $now,
                'deleted_at' => null,
            ]
        );

        return Admin::query()->findOrFail(1);
    }

    private function createCommissionRecord(string $uniqueId, int $settleStatus): int
    {
        $now = time();

        return (int) DB::table('commission_records')->insertGetId([
            'unique_id' => $uniqueId,
            'agent_id' => 984101,
            'parent_id' => 984100,
            'agent_profit' => 12.50,
            'agent_volume' => 1.20,
            'equity_value' => 1000,
            'equity_diff' => 10,
            'settle_cycle' => 1,
            'mt4_order_id' => 0,
            'date_range' => '2026-07-09',
            'settle_status' => $settleStatus,
            'fee' => 0,
            'swap' => 0,
            'commission_amount' => 12.50,
            'returned_amount' => 0,
            'deposit' => 0,
            'real_amount' => 12.50,
            'data_type' => 'manual',
            'manual_reason' => 'id validation test',
            'remarks' => 'original commission remark',
            'created_by' => 'test',
            'updated_by' => 'test',
            'created_at' => $now,
            'updated_at' => $now,
            'deleted_at' => null,
        ]);
    }
}
