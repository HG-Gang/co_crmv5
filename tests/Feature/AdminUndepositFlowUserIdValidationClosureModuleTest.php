<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/02
 * Time: 01:55
 */

/**
 * 后台未入金流水列表与导出接口 user_id 筛选参数严格校验的功能测试。
 *
 * 文件功能：
 * - 验证 user_id 传入非严格数字时未入金流水列表与导出接口均返回校验失败。
 * - 验证校验失败时不返回测试流水记录、导出响应保持 JSON 而非 CSV。
 *
 * 适用场景：
 * - 资金流水页面的未入金查询与导出，防止非法数值注入查询。
 *
 * 入参例子：
 * - POST /api/admin/undepositFlowList，body：{"user_id": "983501abc", "per_page": 5}。
 * - POST /api/admin/exportUndepositFlows，body：{"user_id": "983501abc"}。
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

class AdminUndepositFlowUserIdValidationClosureModuleTest extends TestCase
{
    use DatabaseTransactions;

    protected function tearDown(): void
    {
        DB::table('deposit_records')
            ->where('local_order_no', 'like', 'undeposit-flow-user-id-validation-%')
            ->delete();

        parent::tearDown();
    }

    // 验证未入金流水列表对非严格 user_id 筛选返回校验失败且不返回测试记录。
    public function test_undeposit_flow_list_rejects_non_strict_user_id_filter_without_returning_records(): void
    {
        $actor = $this->ensureSuperAdmin();
        $userId = 983501;
        $this->createUndepositRecord($userId, 'undeposit-flow-user-id-validation-row');

        $response = $this->withoutMiddleware([AdminAuthenticate::class, JwtAuthMiddleware::class, SingleSignOn::class, CheckPermission::class])
            ->actingAs($actor, 'admin')
            ->post('/api/admin/undepositFlowList', [
                'user_id' => $userId . 'abc',
                'per_page' => 5,
            ]);

        $response->assertOk()
            ->assertJsonPath('code', ResponseCode::VALIDATION_FAILED);

        $this->assertStringNotContainsString('undeposit-flow-user-id-validation-row', $response->getContent());
    }

    // 验证未入金流水导出对非严格 user_id 筛选返回校验失败且不流式输出 CSV。
    public function test_undeposit_flow_export_rejects_non_strict_user_id_filter(): void
    {
        $actor = $this->ensureSuperAdmin();
        $userId = 983501;
        $this->createUndepositRecord($userId, 'undeposit-flow-user-id-validation-export');

        $response = $this->withoutMiddleware([AdminAuthenticate::class, JwtAuthMiddleware::class, SingleSignOn::class, CheckPermission::class])
            ->actingAs($actor, 'admin')
            ->post('/api/admin/exportUndepositFlows', [
                'user_id' => $userId . 'abc',
            ]);

        $response->assertOk();
        $this->assertStringContainsString('application/json', (string) $response->headers->get('Content-Type'));
        $response->assertJsonPath('code', ResponseCode::VALIDATION_FAILED);
    }

    // 校验最终检查清单文档记录了未入金流水 user_id 校验边界。
    public function test_final_checklist_records_undeposit_flow_user_id_validation_boundary(): void
    {
        $checklist = file_get_contents(base_path('docs/admin-backend-blade-permission-final-checklist.md')) ?: '';

        $this->assertStringContainsString('## 312.', $checklist);
        $this->assertStringContainsString('FundFlowController::undepositFlowList', $checklist);
        $this->assertStringContainsString('FundFlowController::exportUndepositFlows', $checklist);
        $this->assertStringContainsString('/api/admin/undepositFlowList', $checklist);
        $this->assertStringContainsString('/api/admin/exportUndepositFlows', $checklist);
        $this->assertStringContainsString('deposit_records.user_id', $checklist);
        $this->assertStringContainsString('AdminUndepositFlowUserIdValidationClosureModuleTest', $checklist);
    }

    private function ensureSuperAdmin(): Admin
    {
        $now = time();

        DB::table('admins')->updateOrInsert(
            ['id' => 1],
            [
                'username' => 'admin-undeposit-flow-user-id-super',
                'email' => 'admin-undeposit-flow-user-id-super@example.test',
                'password' => Hash::make('password'),
                'status' => 1,
                'created_at' => $now,
                'updated_at' => $now,
                'deleted_at' => null,
            ]
        );

        return Admin::query()->findOrFail(1);
    }

    private function createUndepositRecord(int $userId, string $localOrderNo): int
    {
        $now = time();

        return (int) DB::table('deposit_records')->insertGetId([
            'user_id' => $userId,
            'user_name' => 'Undeposit Flow User ID Validation User',
            'mt4_ticket' => 0,
            'amount' => 148.50,
            'actual_amount' => 0,
            'exchange_rate' => 1,
            'channel_name' => 'test channel',
            'channel_order_no' => '',
            'local_order_no' => $localOrderNo,
            'status' => '01',
            'payment_time' => null,
            'remarks' => 'undeposit flow validation row',
            'created_by' => 'test',
            'updated_by' => 'test',
            'created_at' => $now,
            'updated_at' => $now,
            'deleted_at' => null,
        ]);
    }
}
