<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/16
 * Time: 02:33
 */

/**
 * 后台实名认证审核通过状态的功能测试。
 *
 * 文件功能：
 * - 验证 reviewAuth 审核通过（status=1）时身份证、银行卡审核状态全部置为通过（2）。
 * - 验证旧驳回原因被清空、用户 auth_status 置为已认证并写入操作日志。
 *
 * 适用场景：
 * - 后台用户实名认证审核页面点击“通过”，需同步更新认证状态与操作日志。
 *
 * 入参例子：
 * - POST /api/admin/reviewAuth，body：{"user_id": 98726901, "status": 1, "reason": "..."}。
 *
 * 返回值：
 * - 审核成功返回 code=ResponseCode::SUCCESS。
 *
 * 异常或失败场景：
 * - 审核通过前用户需处于待审核状态；MT4 更新注释失败不影响本地状态落库。
 */

namespace Tests\Feature;

use App\Constants\ResponseCode;
use App\Http\Middleware\AdminAuthenticate;
use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\JwtAuthMiddleware;
use App\Http\Middleware\SingleSignOn;
use App\Models\Admin;
use App\Services\Mt4ManagerService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AdminReviewAuthApproveStateClosureModuleTest extends TestCase
{
    use DatabaseTransactions;

    // 验证审核通过后认证字段全部置为已通过、旧驳回原因清空并写入操作日志。
    public function test_review_auth_approval_marks_all_auth_fields_passed_and_clears_old_reject_reason(): void
    {
        $admin = $this->ensureSuperAdmin();
        $userId = 98726901;
        $this->createPendingAuthUser($userId);

        DB::table('operation_logs')
            ->where('order_no', 'like', 'auth_review:' . $userId . '%')
            ->delete();

        $this->app->instance(Mt4ManagerService::class, new class extends Mt4ManagerService {
            public function __construct()
            {
                parent::__construct('127.0.0.1', 0, 'k', '1', 1);
            }

            public function updateComment($userId, $comment)
            {
                return ['status' => 'ok', 'message' => 'updated', 'data' => []];
            }
        });

        $response = $this->withoutMiddleware([AdminAuthenticate::class, JwtAuthMiddleware::class, SingleSignOn::class, CheckPermission::class])
            ->actingAs($admin, 'admin')
            ->post('/api/admin/reviewAuth', [
                'user_id' => $userId,
                'status' => 1,
                'reason' => 'old reason must not remain after approval',
            ]);

        $response->assertOk()
            ->assertJsonPath('code', ResponseCode::SUCCESS);

        $auth = DB::table('user_auths')->where('user_id', $userId)->first();
        $user = DB::table('user_infos')->where('user_id', $userId)->first();
        $log = DB::table('operation_logs')
            ->where('order_no', 'like', 'auth_review:' . $userId . '%')
            ->orderByDesc('id')
            ->first();

        $this->assertSame(2, (int) $auth->id_card_status);
        $this->assertSame(2, (int) $auth->bank_status);
        $this->assertSame('', (string) $auth->id_card_remarks);
        $this->assertSame('', (string) $auth->bank_remarks);
        $this->assertSame(1, (int) $user->auth_status);

        $this->assertNotNull($log);
        $this->assertSame((int) $admin->id, (int) $log->admin_id);
        $this->assertSame($admin->username, (string) $log->admin_name);
        $this->assertStringContainsString('Review auth user_id:' . $userId, (string) $log->content);
        $this->assertStringContainsString('status:1', (string) $log->content);
        $this->assertStringContainsString('id_card_status:1->2', (string) $log->content);
        $this->assertStringContainsString('bank_status:3->2', (string) $log->content);
    }

    // 校验最终检查清单文档记录了实名认证审核通过状态边界。
    public function test_final_checklist_records_admin_review_auth_approve_state_boundary(): void
    {
        $checklist = file_get_contents(base_path('docs/admin-backend-blade-permission-final-checklist.md')) ?: '';

        $this->assertStringContainsString('## 269.', $checklist);
        $this->assertStringContainsString('AdminUserController::reviewAuth', $checklist);
        $this->assertStringContainsString('/api/admin/reviewAuth', $checklist);
        $this->assertStringContainsString('user_auths.id_card_status', $checklist);
        $this->assertStringContainsString('user_infos.auth_status', $checklist);
        $this->assertStringContainsString('AdminReviewAuthApproveStateClosureModuleTest', $checklist);
    }

    private function ensureSuperAdmin(): Admin
    {
        $now = time();

        DB::table('admins')->updateOrInsert(
            ['id' => 1],
            [
                'username' => 'admin-review-auth-approve-super',
                'email' => 'admin-review-auth-approve-super@example.test',
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
            'email' => 'admin-review-auth-approve-' . $userId . '@example.test',
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
            'user_name' => 'Admin Review Auth Approve User',
            'phone' => '188269' . substr((string) $userId, -5),
            'account_type' => 2,
            'parent_id' => 0,
            'auth_status' => 2,
            'created_at' => $now,
            'updated_at' => $now,
            'deleted_at' => null,
        ]);

        DB::table('user_auths')->insert([
            'user_id' => $userId,
            'bank_no' => '6222020269000001',
            'bank_name' => 'Review Auth Bank',
            'bank_addr' => 'Review Auth Branch',
            'bank_status' => 3,
            'bank_remarks' => 'old bank reject reason',
            'id_card_no' => '110101199003076901',
            'id_card_status' => 1,
            'id_card_front' => 'id-front-approve.jpg',
            'id_card_back' => 'id-back-approve.jpg',
            'id_card_remarks' => 'old id reject reason',
            'created_at' => $now,
            'updated_at' => $now,
            'deleted_at' => null,
        ]);
    }
}
