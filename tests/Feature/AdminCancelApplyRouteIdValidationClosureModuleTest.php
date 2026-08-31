<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/02
 * Time: 01:45
 */

/**
 * 文件功能：验证销户申请审核通过、拒绝接口对非严格路由 ID 的校验边界，
 *           保证校验失败时不产生任何副作用，并核对最终检查清单文档。
 *
 * 适用场景：后台 /api/admin/cancelApplyApprove/{id}、/api/admin/cancelApplyReject/{id}
 *           接口的输入校验回归测试。
 *
 * 入参例子：
 * - POST /api/admin/cancelApplyApprove/{applyId}abc：无请求体
 * - POST /api/admin/cancelApplyReject/{applyId}abc：{reason}
 *
 * 返回值：
 * - 路由 ID 带非数字后缀时返回 code=VALIDATION_FAILED；
 * - 申请状态、登录账号、user_infos 软删除状态与操作日志均保持原样。
 *
 * 异常或失败场景：
 * - 路由 ID 非严格数字（如 {id}abc）时校验失败，不产生任何数据变更。
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

class AdminCancelApplyRouteIdValidationClosureModuleTest extends TestCase
{
    use DatabaseTransactions;

    // 审核通过时路由 ID 带非数字后缀应校验失败且不产生任何副作用。
    public function test_approve_cancel_apply_rejects_non_strict_route_id_without_side_effects(): void
    {
        $actor = $this->ensureSuperAdmin();
        $userId = 984301;
        $applyId = $this->createCancellationUserAndApply($userId, 'Route Id Approve User');

        DB::table('operation_logs')->where('order_no', 'cancel_apply:' . $applyId)->delete();

        $response = $this->withoutMiddleware([AdminAuthenticate::class, JwtAuthMiddleware::class, SingleSignOn::class, CheckPermission::class])
            ->actingAs($actor, 'admin')
            ->post('/api/admin/cancelApplyApprove/' . $applyId . 'abc');

        $response->assertOk()
            ->assertJsonPath('code', ResponseCode::VALIDATION_FAILED);

        $this->assertDatabaseHas('cancel_applies', [
            'id' => $applyId,
            'status' => 0,
            'reject_reason' => '',
            'updated_by' => '',
        ]);
        $this->assertDatabaseHas('user_logins', [
            'user_id' => $userId,
            'is_cancelled' => 0,
        ]);
        $this->assertNull(DB::table('user_infos')->where('user_id', $userId)->value('deleted_at'));
        $this->assertFalse(DB::table('operation_logs')->where('order_no', 'cancel_apply:' . $applyId)->exists());
    }

    // 审核拒绝时路由 ID 带非数字后缀应校验失败且不产生任何副作用。
    public function test_reject_cancel_apply_rejects_non_strict_route_id_without_side_effects(): void
    {
        $actor = $this->ensureSuperAdmin();
        $userId = 984302;
        $applyId = $this->createCancellationUserAndApply($userId, 'Route Id Reject User');

        DB::table('operation_logs')->where('order_no', 'cancel_apply:' . $applyId)->delete();

        $response = $this->withoutMiddleware([AdminAuthenticate::class, JwtAuthMiddleware::class, SingleSignOn::class, CheckPermission::class])
            ->actingAs($actor, 'admin')
            ->post('/api/admin/cancelApplyReject/' . $applyId . 'abc', [
                'reason' => 'Route id must be strict',
            ]);

        $response->assertOk()
            ->assertJsonPath('code', ResponseCode::VALIDATION_FAILED);

        $this->assertDatabaseHas('cancel_applies', [
            'id' => $applyId,
            'status' => 0,
            'reject_reason' => '',
            'updated_by' => '',
        ]);
        $this->assertDatabaseHas('user_logins', [
            'user_id' => $userId,
            'is_cancelled' => 0,
        ]);
        $this->assertNull(DB::table('user_infos')->where('user_id', $userId)->value('deleted_at'));
        $this->assertFalse(DB::table('operation_logs')->where('order_no', 'cancel_apply:' . $applyId)->exists());
    }

    // 核对最终检查清单文档记录了销户申请路由 ID 校验边界。
    public function test_final_checklist_records_cancel_apply_route_id_validation_boundary(): void
    {
        $checklist = file_get_contents(base_path('docs/admin-backend-blade-permission-final-checklist.md')) ?: '';

        $this->assertStringContainsString('## 294.', $checklist);
        $this->assertStringContainsString('CancelApplyController::approve', $checklist);
        $this->assertStringContainsString('CancelApplyController::reject', $checklist);
        $this->assertStringContainsString('/api/admin/cancelApplyApprove/{id}', $checklist);
        $this->assertStringContainsString('/api/admin/cancelApplyReject/{id}', $checklist);
        $this->assertStringContainsString('cancel_applies.id', $checklist);
        $this->assertStringContainsString('user_logins.is_cancelled', $checklist);
        $this->assertStringContainsString('operation_logs', $checklist);
        $this->assertStringContainsString('AdminCancelApplyRouteIdValidationClosureModuleTest', $checklist);
    }

    private function ensureSuperAdmin(): Admin
    {
        $now = time();

        DB::table('admins')->updateOrInsert(
            ['id' => 1],
            [
                'username' => 'admin-cancel-apply-route-id-super',
                'email' => 'admin-cancel-apply-route-id-super@example.test',
                'password' => Hash::make('password'),
                'status' => 1,
                'created_at' => $now,
                'updated_at' => $now,
                'deleted_at' => null,
            ]
        );

        return Admin::query()->findOrFail(1);
    }

    private function createCancellationUserAndApply(int $userId, string $userName): int
    {
        $now = time();

        DB::table('user_logins')->updateOrInsert(
            ['email' => 'cancel-apply-route-id-' . $userId . '@example.test'],
            [
                'user_id' => $userId,
                'password' => Hash::make('password'),
                'account_type' => 2,
                'is_enabled' => 1,
                'is_cancelled' => 0,
                'source_type' => 0,
                'created_at' => $now,
                'updated_at' => $now,
                'deleted_at' => null,
            ]
        );

        DB::table('user_infos')->updateOrInsert(
            ['user_id' => $userId],
            [
                'login_id' => $userId,
                'user_name' => $userName,
                'account_type' => 2,
                'created_at' => $now,
                'updated_at' => $now,
                'deleted_at' => null,
            ]
        );

        return (int) DB::table('cancel_applies')->insertGetId([
            'user_id' => $userId,
            'user_name' => $userName,
            'status' => 0,
            'cancel_remark' => 'User submitted cancellation request',
            'reject_reason' => '',
            'created_by' => (string) $userId,
            'updated_by' => '',
            'created_at' => $now,
            'updated_at' => $now,
            'deleted_at' => null,
        ]);
    }
}
