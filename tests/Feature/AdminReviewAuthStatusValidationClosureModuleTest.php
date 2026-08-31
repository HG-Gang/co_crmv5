<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/02
 * Time: 01:55
 */

/**
 * 后台实名认证审核状态参数严格校验的功能测试。
 *
 * 文件功能：
 * - 验证 reviewAuth 接口 status 传入非严格数字（如 "1abc"）时返回校验失败。
 * - 验证校验失败时认证记录与用户状态均不被修改、不写操作日志。
 *
 * 适用场景：
 * - 后台实名认证审核操作，防止非法状态值误通过或误驳回用户。
 *
 * 入参例子：
 * - POST /api/admin/reviewAuth，body：{"user_id": 98727001, "status": "1abc", "reason": "..."}。
 *
 * 返回值：
 * - 校验失败返回 code=ResponseCode::VALIDATION_FAILED。
 *
 * 异常或失败场景：
 * - status 非严格整数（合法值仅 1/2）时接口拒绝审核并保持原认证数据不变。
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
use Tests\TestCase;

class AdminReviewAuthStatusValidationClosureModuleTest extends TestCase
{
    use DatabaseTransactions;

    // 验证非严格 status 被拒绝且认证记录、用户状态与操作日志均无改动。
    public function test_review_auth_rejects_non_strict_status_without_writing_auth_or_log(): void
    {
        $admin = $this->ensureSuperAdmin();
        $userId = 98727001;
        $this->createPendingAuthUser($userId);

        DB::table('operation_logs')
            ->where('order_no', 'auth_review:' . $userId)
            ->delete();

        $response = $this->withoutMiddleware([AdminAuthenticate::class, JwtAuthMiddleware::class, SingleSignOn::class, CheckPermission::class])
            ->actingAs($admin, 'admin')
            ->post('/api/admin/reviewAuth', [
                'user_id' => $userId,
                'status' => '1abc',
                'reason' => 'invalid status must not approve user',
            ]);

        $response->assertOk()
            ->assertJsonPath('code', ResponseCode::VALIDATION_FAILED);

        $auth = DB::table('user_auths')->where('user_id', $userId)->first();
        $user = DB::table('user_infos')->where('user_id', $userId)->first();

        $this->assertSame(1, (int) $auth->id_card_status);
        $this->assertSame(1, (int) $auth->bank_status);
        $this->assertSame('pending id remark', (string) $auth->id_card_remarks);
        $this->assertSame('pending bank remark', (string) $auth->bank_remarks);
        $this->assertSame(0, (int) $user->auth_status);
        $this->assertFalse(DB::table('operation_logs')->where('order_no', 'auth_review:' . $userId)->exists());
    }

    // 校验最终检查清单文档记录了实名认证审核状态校验边界。
    public function test_final_checklist_records_admin_review_auth_status_validation_boundary(): void
    {
        $checklist = file_get_contents(base_path('docs/admin-backend-blade-permission-final-checklist.md')) ?: '';

        $this->assertStringContainsString('## 270.', $checklist);
        $this->assertStringContainsString('AdminUserController::reviewAuth', $checklist);
        $this->assertStringContainsString('/api/admin/reviewAuth', $checklist);
        $this->assertStringContainsString('status=1/2', $checklist);
        $this->assertStringContainsString('ResponseCode::VALIDATION_FAILED', $checklist);
        $this->assertStringContainsString('AdminReviewAuthStatusValidationClosureModuleTest', $checklist);
    }

    private function ensureSuperAdmin(): Admin
    {
        $now = time();

        DB::table('admins')->updateOrInsert(
            ['id' => 1],
            [
                'username' => 'admin-review-auth-status-super',
                'email' => 'admin-review-auth-status-super@example.test',
                'password' => bcrypt('password'),
                'status' => 1,
                'created_at' => $now,
                'updated_at' => $now,
                'deleted_at' => null,
            ]
        );

        return Admin::query()->findOrFail(1);
    }

    private function createPendingAuthUser(int $userId): void
    {
        $now = time();

        DB::table('user_auths')->where('user_id', $userId)->delete();
        DB::table('user_infos')->where('user_id', $userId)->delete();
        DB::table('user_logins')->where('user_id', $userId)->delete();

        $loginId = DB::table('user_logins')->insertGetId([
            'user_id' => $userId,
            'email' => 'admin-review-auth-status-' . $userId . '@example.test',
            'password' => bcrypt('password'),
            'account_type' => 2,
            'role_id' => 0,
            'is_enabled' => 1,
            'is_cancelled' => 0,
            'source_type' => 0,
            'created_at' => $now,
            'updated_at' => $now,
            'deleted_at' => null,
        ]);

        DB::table('user_infos')->insert([
            'user_id' => $userId,
            'login_id' => $loginId,
            'user_name' => 'Admin Review Auth Status User',
            'phone' => '188270' . substr((string) $userId, -5),
            'account_type' => 2,
            'parent_id' => 0,
            'auth_status' => 0,
            'created_at' => $now,
            'updated_at' => $now,
            'deleted_at' => null,
        ]);

        DB::table('user_auths')->insert([
            'user_id' => $userId,
            'bank_no' => '6222020270000001',
            'bank_name' => 'Review Status Bank',
            'bank_addr' => 'Review Status Branch',
            'bank_status' => 1,
            'bank_remarks' => 'pending bank remark',
            'id_card_no' => '110101199003077001',
            'id_card_status' => 1,
            'id_card_front' => 'id-front-status.jpg',
            'id_card_back' => 'id-back-status.jpg',
            'id_card_remarks' => 'pending id remark',
            'created_at' => $now,
            'updated_at' => $now,
            'deleted_at' => null,
        ]);
    }
}
