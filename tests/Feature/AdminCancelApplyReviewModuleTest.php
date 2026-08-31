<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/02
 * Time: 01:45
 */

/**
 * 文件功能：验证销户申请审核通过（cancelApplyApprove）与拒绝（cancelApplyReject）
 *           的完整流程：状态流转、账号停用/软删除、MT4 锁定与操作日志审计。
 *
 * 适用场景：后台 /api/admin/cancelApplyApprove/{id}、/api/admin/cancelApplyReject/{id}
 *           接口的业务回归测试。
 *
 * 入参例子：
 * - POST /api/admin/cancelApplyApprove/{applyId}：无请求体
 * - POST /api/admin/cancelApplyReject/{applyId}：{reason}
 *
 * 返回值：
 * - 通过：code=SUCCESS，cancel_applies.status=1，登录账号置为已注销，
 *   user_infos 软删除并置为 MT4 只读，operation_logs 写入审核记录；
 * - 拒绝：code=SUCCESS，cancel_applies.status=-1 并记录 reject_reason。
 *
 * 异常或失败场景：
 * - 未生成 operation_logs 审计记录时断言失败。
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

class AdminCancelApplyReviewModuleTest extends TestCase
{
    use DatabaseTransactions;

    // 审核通过应停用登录账号、软删除用户并写入操作日志审计记录。
    public function test_cancel_apply_approve_marks_login_cancelled_soft_deletes_user_and_writes_operation_log(): void
    {
        $admin = $this->ensureSuperAdmin();
        $now = time();
        $userId = 984201;
        $applyId = $this->createCancellationUserAndApply($userId, 'Approve Cancel User', $now);

        DB::table('operation_logs')
            ->where('order_no', 'cancel_apply:' . $applyId)
            ->delete();

        $this->app->instance(Mt4ManagerService::class, new class extends Mt4ManagerService {
            public function __construct()
            {
                parent::__construct('127.0.0.1', 0, 'k', '1', 1);
            }

            public function lockUser($userId)
            {
                return ['status' => 'ok', 'message' => 'locked', 'data' => []];
            }
        });

        $response = $this->withoutMiddleware([AdminAuthenticate::class, JwtAuthMiddleware::class, SingleSignOn::class, CheckPermission::class])
            ->actingAs($admin, 'admin')
            ->post('/api/admin/cancelApplyApprove/' . $applyId);

        $response->assertOk();
        $response->assertJsonPath('code', ResponseCode::SUCCESS);

        $this->assertDatabaseHas('cancel_applies', [
            'id' => $applyId,
            'status' => 1,
        ]);
        $this->assertDatabaseHas('user_logins', [
            'user_id' => $userId,
            'is_cancelled' => 1,
            'is_enabled' => 0,
        ]);
        $this->assertDatabaseHas('user_infos', [
            'user_id' => $userId,
            'is_mt4_enabled' => 0,
            'is_mt4_readonly' => 1,
        ]);
        $this->assertSoftDeleted('user_infos', [
            'user_id' => $userId,
        ]);

        $log = DB::table('operation_logs')
            ->where('admin_id', $admin->id)
            ->where('order_no', 'cancel_apply:' . $applyId)
            ->orderByDesc('id')
            ->first();

        $this->assertNotNull($log, 'cancelApplyApprove must create an operation_logs audit record.');
        $this->assertSame($admin->username, $log->admin_name);
        $this->assertSame($userId, (int) $log->target_user_id);
        $this->assertSame(0, (int) $log->action_type);
        $this->assertStringContainsString('Review cancel apply id:' . $applyId, $log->content);
        $this->assertStringContainsString('action:approve', $log->content);
        $this->assertStringContainsString('status:0->1', $log->content);
    }

    // 审核拒绝应更新 reject_reason 并写入操作日志审计记录。
    public function test_cancel_apply_reject_updates_reject_reason_and_writes_operation_log(): void
    {
        $admin = $this->ensureSuperAdmin();
        $now = time();
        $userId = 984202;
        $reason = 'Open positions still exist';
        $applyId = $this->createCancellationUserAndApply($userId, 'Reject Cancel User', $now);

        DB::table('operation_logs')
            ->where('order_no', 'cancel_apply:' . $applyId)
            ->delete();

        $response = $this->withoutMiddleware([AdminAuthenticate::class, JwtAuthMiddleware::class, SingleSignOn::class, CheckPermission::class])
            ->actingAs($admin, 'admin')
            ->post('/api/admin/cancelApplyReject/' . $applyId, [
                'reason' => $reason,
            ]);

        $response->assertOk();
        $response->assertJsonPath('code', ResponseCode::SUCCESS);

        $this->assertDatabaseHas('cancel_applies', [
            'id' => $applyId,
            'status' => -1,
            'reject_reason' => $reason,
        ]);
        $this->assertDatabaseHas('user_logins', [
            'user_id' => $userId,
            'is_cancelled' => 0,
        ]);

        $log = DB::table('operation_logs')
            ->where('admin_id', $admin->id)
            ->where('order_no', 'cancel_apply:' . $applyId)
            ->orderByDesc('id')
            ->first();

        $this->assertNotNull($log, 'cancelApplyReject must create an operation_logs audit record.');
        $this->assertSame($admin->username, $log->admin_name);
        $this->assertSame($userId, (int) $log->target_user_id);
        $this->assertSame(0, (int) $log->action_type);
        $this->assertStringContainsString('Review cancel apply id:' . $applyId, $log->content);
        $this->assertStringContainsString('action:reject', $log->content);
        $this->assertStringContainsString('status:0->-1', $log->content);
        $this->assertStringContainsString('reason:' . $reason, $log->content);
    }

    private function ensureSuperAdmin(): Admin
    {
        $now = time();

        DB::table('admins')->updateOrInsert(
            ['id' => 1],
            [
                'username' => 'cancel-apply-audit-admin',
                'email' => 'cancel-apply-audit-admin@example.test',
                'password' => bcrypt('password'),
                'status' => 1,
                'created_at' => $now,
                'updated_at' => $now,
                'deleted_at' => null,
            ]
        );

        return Admin::query()->findOrFail(1);
    }

    private function createCancellationUserAndApply(int $userId, string $userName, int $now): int
    {
        DB::table('user_logins')->updateOrInsert(
            ['email' => 'cancel-apply-' . $userId . '@example.test'],
            [
                'user_id' => $userId,
                'password' => bcrypt('password'),
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
