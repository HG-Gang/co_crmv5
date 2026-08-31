<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/02
 * Time: 01:45
 */

/**
 * 文件功能：验证佣金列表（commissionList）对 agent_id 筛选值的严格校验，
 *           非法筛选值不得返回任何佣金记录，并核对最终检查清单文档。
 *
 * 适用场景：后台 /api/admin/commissionList 接口的筛选参数校验回归测试。
 *
 * 入参例子：
 * - POST /api/admin/commissionList：{agent_id, per_page}
 *
 * 返回值：
 * - agent_id 带非数字后缀时返回 code=VALIDATION_FAILED，且响应不含任何佣金记录。
 *
 * 异常或失败场景：
 * - 非严格数字 agent_id（如 '984201abc'）时校验失败，不执行查询返回数据。
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

class AdminCommissionListAgentIdValidationClosureModuleTest extends TestCase
{
    use DatabaseTransactions;

    protected function tearDown(): void
    {
        DB::table('commission_records')
            ->where('unique_id', 'like', 'commission-list-agent-id-validation-%')
            ->delete();

        parent::tearDown();
    }

    // 佣金列表应拒绝非严格 agent_id 筛选值且不返回任何记录。
    public function test_commission_list_rejects_non_strict_agent_id_filter_without_returning_records(): void
    {
        $actor = $this->ensureSuperAdmin();
        $agentId = 984201;
        $this->createCommissionRecord('commission-list-agent-id-validation-row', $agentId);

        $response = $this->withoutMiddleware([AdminAuthenticate::class, JwtAuthMiddleware::class, SingleSignOn::class, CheckPermission::class])
            ->actingAs($actor, 'admin')
            ->post('/api/admin/commissionList', [
                'agent_id' => $agentId . 'abc',
                'per_page' => 5,
            ]);

        $response->assertOk()
            ->assertJsonPath('code', ResponseCode::VALIDATION_FAILED);

        $this->assertStringNotContainsString('commission-list-agent-id-validation-row', $response->getContent());
    }

    // 核对最终检查清单文档记录了佣金列表 agent_id 校验边界。
    public function test_final_checklist_records_commission_list_agent_id_validation_boundary(): void
    {
        $checklist = file_get_contents(base_path('docs/admin-backend-blade-permission-final-checklist.md')) ?: '';

        $this->assertStringContainsString('## 308.', $checklist);
        $this->assertStringContainsString('CommissionController::index', $checklist);
        $this->assertStringContainsString('/api/admin/commissionList', $checklist);
        $this->assertStringContainsString('commission_records.agent_id', $checklist);
        $this->assertStringContainsString('AdminCommissionListAgentIdValidationClosureModuleTest', $checklist);
    }

    private function ensureSuperAdmin(): Admin
    {
        $now = time();

        DB::table('admins')->updateOrInsert(
            ['id' => 1],
            [
                'username' => 'admin-commission-list-agent-id-super',
                'email' => 'admin-commission-list-agent-id-super@example.test',
                'password' => Hash::make('password'),
                'status' => 1,
                'created_at' => $now,
                'updated_at' => $now,
                'deleted_at' => null,
            ]
        );

        return Admin::query()->findOrFail(1);
    }

    private function createCommissionRecord(string $uniqueId, int $agentId): int
    {
        $now = time();

        return (int) DB::table('commission_records')->insertGetId([
            'unique_id' => $uniqueId,
            'agent_id' => $agentId,
            'parent_id' => $agentId - 1,
            'agent_profit' => 18.50,
            'agent_volume' => 1.80,
            'equity_value' => 1000,
            'equity_diff' => 10,
            'settle_cycle' => 1,
            'mt4_order_id' => 0,
            'date_range' => '2026-07-09',
            'settle_status' => 1,
            'fee' => 0,
            'swap' => 0,
            'commission_amount' => 18.50,
            'returned_amount' => 0,
            'deposit' => 0,
            'real_amount' => 18.50,
            'data_type' => 'manual',
            'manual_reason' => 'agent id validation test',
            'remarks' => 'commission list validation row',
            'created_by' => 'test',
            'updated_by' => 'test',
            'created_at' => $now,
            'updated_at' => $now,
            'deleted_at' => null,
        ]);
    }
}
