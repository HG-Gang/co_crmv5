<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/02
 * Time: 01:48
 */

/**
 * 凭证审批/驳回路由 id 参数严格校验的闭环测试。
 *
 * 文件功能：
 * - 验证 /api/admin/voucherApprove/{id} 与 /api/admin/voucherReject/{id} 对非严格 id（带字母后缀）返回校验失败。
 * - 验证校验失败时凭证的审核状态、备注与驳回信息均不被修改。
 * - 验证最终清单文档已记录该路由参数校验边界。
 *
 * 适用场景：
 * - 管理员凭证审批与驳回接口路由参数安全的回归测试。
 *
 * 入参例子：
 * - POST /api/admin/voucherApprove/{targetId}abc
 * - POST /api/admin/voucherReject/{targetId}abc，body 含 reason。
 *
 * 返回值：
 * - 接口返回 HTTP 200，code 为 VALIDATION_FAILED。
 * - voucher_infos 记录保持 review_status = 0、remarks 不变、review_message 为空。
 *
 * 异常或失败场景：
 * - 若非严格 id 被放行并修改凭证状态，断言失败。
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

class AdminVoucherRouteIdValidationClosureModuleTest extends TestCase
{
    use DatabaseTransactions;

    /**
     * 验证审批凭证接口拒绝非严格路由 id，且不改变凭证审核状态。
     */
    public function test_approve_voucher_rejects_non_strict_route_id_without_approving_voucher(): void
    {
        $actor = $this->ensureSuperAdmin();
        $targetId = $this->createManagedVoucher(920101, 'Route Id Voucher Approve');

        $response = $this->withoutMiddleware([AdminAuthenticate::class, JwtAuthMiddleware::class, SingleSignOn::class, CheckPermission::class])
            ->actingAs($actor, 'admin')
            ->post('/api/admin/voucherApprove/' . $targetId . 'abc');

        $response->assertOk()
            ->assertJsonPath('code', ResponseCode::VALIDATION_FAILED);

        $voucher = DB::table('voucher_infos')->where('id', $targetId)->first();

        $this->assertSame(0, (int) $voucher->review_status);
        $this->assertSame('Route Id Voucher Approve', (string) $voucher->remarks);
        $this->assertSame('', (string) $voucher->review_message);
        $this->assertNull($voucher->deleted_at);
    }

    /**
     * 验证驳回凭证接口拒绝非严格路由 id，且不改变凭证审核状态。
     */
    public function test_reject_voucher_rejects_non_strict_route_id_without_rejecting_voucher(): void
    {
        $actor = $this->ensureSuperAdmin();
        $targetId = $this->createManagedVoucher(920102, 'Route Id Voucher Reject');

        $response = $this->withoutMiddleware([AdminAuthenticate::class, JwtAuthMiddleware::class, SingleSignOn::class, CheckPermission::class])
            ->actingAs($actor, 'admin')
            ->post('/api/admin/voucherReject/' . $targetId . 'abc', [
                'reason' => 'Invalid voucher image',
            ]);

        $response->assertOk()
            ->assertJsonPath('code', ResponseCode::VALIDATION_FAILED);

        $voucher = DB::table('voucher_infos')->where('id', $targetId)->first();

        $this->assertSame(0, (int) $voucher->review_status);
        $this->assertSame('', (string) $voucher->review_message);
        $this->assertSame('Route Id Voucher Reject', (string) $voucher->remarks);
    }

    /**
     * 验证最终清单文档已记录凭证路由 id 校验边界（## 292）。
     */
    public function test_final_checklist_records_voucher_route_id_validation_boundary(): void
    {
        $checklist = file_get_contents(base_path('docs/admin-backend-blade-permission-final-checklist.md')) ?: '';

        $this->assertStringContainsString('## 292.', $checklist);
        $this->assertStringContainsString('VoucherController::approve', $checklist);
        $this->assertStringContainsString('VoucherController::reject', $checklist);
        $this->assertStringContainsString('/api/admin/voucherApprove/{id}', $checklist);
        $this->assertStringContainsString('/api/admin/voucherReject/{id}', $checklist);
        $this->assertStringContainsString('voucher_infos.id', $checklist);
        $this->assertStringContainsString('voucher_infos.review_status', $checklist);
        $this->assertStringContainsString('AdminVoucherRouteIdValidationClosureModuleTest', $checklist);
    }

    private function ensureSuperAdmin(): Admin
    {
        $now = time();

        DB::table('admins')->updateOrInsert(
            ['id' => 1],
            [
                'username' => 'admin-voucher-route-id-super',
                'email' => 'admin-voucher-route-id-super@example.test',
                'password' => Hash::make('password'),
                'status' => 1,
                'created_at' => $now,
                'updated_at' => $now,
                'deleted_at' => null,
            ]
        );

        return Admin::query()->findOrFail(1);
    }

    private function createManagedVoucher(int $userId, string $remarks): int
    {
        $now = time();

        DB::table('voucher_infos')->where('user_id', $userId)->delete();

        return (int) DB::table('voucher_infos')->insertGetId([
            'user_id' => $userId,
            'images' => '/uploads/vouchers/route-id.png',
            'remarks' => $remarks,
            'review_status' => 0,
            'review_message' => '',
            'created_by' => 'front-user',
            'updated_by' => 'front-user',
            'created_at' => $now,
            'updated_at' => $now,
            'deleted_at' => null,
        ]);
    }
}
