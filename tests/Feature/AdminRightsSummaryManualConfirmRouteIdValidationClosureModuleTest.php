<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/02
 * Time: 01:55
 */

/**
 * 后台权益汇总手动确认结算接口路由 id 严格校验的功能测试。
 *
 * 文件功能：
 * - 验证手动确认结算路由参数 id 传入非严格数字时接口返回校验失败。
 * - 验证校验失败后结算记录仍为待确认状态、数据不被修改。
 *
 * 适用场景：
 * - 权益汇总页面手动确认结算操作，防止非法路由 id 误确认结算。
 *
 * 入参例子：
 * - POST /api/admin/manualConfirmRightsSettlement/{id}abc，body：{"manual_confirm_reason": "..."}。
 *
 * 返回值：
 * - 校验失败返回 code=ResponseCode::VALIDATION_FAILED。
 *
 * 异常或失败场景：
 * - 路由 id 非严格整数时接口拒绝执行并保持结算记录待确认状态不变。
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

class AdminRightsSummaryManualConfirmRouteIdValidationClosureModuleTest extends TestCase
{
    use DatabaseTransactions;

    // 验证手动确认结算时非严格路由 id 被拒绝且结算记录保持待确认。
    public function test_manual_confirm_route_rejects_non_strict_route_id_without_confirming_settlement(): void
    {
        $actor = $this->ensureSuperAdmin();
        $userId = 982802;
        $this->ensureUser($userId);
        $settlementId = $this->createPendingSettlement($userId);

        $response = $this->withoutMiddleware([AdminAuthenticate::class, JwtAuthMiddleware::class, SingleSignOn::class, CheckPermission::class])
            ->actingAs($actor, 'admin')
            ->post('/api/admin/manualConfirmRightsSettlement/' . $settlementId . 'abc', [
                'manual_confirm_reason' => 'manual route id validation',
            ]);

        $response->assertOk()
            ->assertJsonPath('code', ResponseCode::VALIDATION_FAILED);

        $this->assertDatabaseHas('rights_settlements', [
            'id' => $settlementId,
            'user_id' => $userId,
            'status' => 0,
            'remark' => 'pending manual confirm',
        ]);
    }

    // 校验最终检查清单文档记录了权益汇总手动确认路由 id 校验边界。
    public function test_final_checklist_records_rights_summary_manual_confirm_route_id_validation_boundary(): void
    {
        $checklist = file_get_contents(base_path('docs/admin-backend-blade-permission-final-checklist.md')) ?: '';

        $this->assertStringContainsString('## 297.', $checklist);
        $this->assertStringContainsString('RightsSummaryController::manualConfirmRightsSettlement', $checklist);
        $this->assertStringContainsString('/api/admin/manualConfirmRightsSettlement/{id}', $checklist);
        $this->assertStringContainsString('rights_settlements.id', $checklist);
        $this->assertStringContainsString('AdminRightsSummaryManualConfirmRouteIdValidationClosureModuleTest', $checklist);
    }

    private function ensureSuperAdmin(): Admin
    {
        $now = time();

        DB::table('admins')->updateOrInsert(
            ['id' => 1],
            [
                'username' => 'admin-rights-manual-route-id-super',
                'email' => 'admin-rights-manual-route-id-super@example.test',
                'password' => Hash::make('password'),
                'status' => 1,
                'created_at' => $now,
                'updated_at' => $now,
                'deleted_at' => null,
            ]
        );

        return Admin::query()->findOrFail(1);
    }

    private function ensureUser(int $userId): void
    {
        $now = time();

        DB::table('user_infos')->updateOrInsert(
            ['user_id' => $userId],
            [
                'login_id' => 0,
                'user_name' => 'Rights Manual Route User',
                'phone' => '',
                'gender' => 1,
                'account_type' => 2,
                'parent_id' => 0,
                'family_tree' => (string) $userId,
                'total_funds' => 0,
                'equity' => 0,
                'effective_credit' => 0,
                'created_at' => $now,
                'updated_at' => $now,
                'deleted_at' => null,
            ]
        );
    }

    private function createPendingSettlement(int $userId): int
    {
        $now = time();

        return (int) DB::table('rights_settlements')->insertGetId([
            'user_id' => $userId,
            'amount' => 123.4567,
            'status' => 0,
            'remark' => 'pending manual confirm',
            'created_at' => $now,
            'updated_at' => $now,
            'deleted_at' => null,
        ]);
    }
}
