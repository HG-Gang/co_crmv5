<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/02
 * Time: 01:47
 */

/**
 * 文件功能：验证销户申请审核通过（cancelApplyApprove）时与 MT4 锁定服务的联动：
 *           成功时锁定 MT4 并停用本地账号，MT4 锁定失败时整体失败回滚。
 *
 * 适用场景：后台 /api/admin/cancelApplyApprove/{id} 接口的 MT4 同步回归测试。
 *
 * 入参例子：
 * - POST /api/admin/cancelApplyApprove/{applyId}：无请求体（需管理员登录态）
 *
 * 返回值：
 * - MT4 锁定成功：code=SUCCESS，cancel_applies.status=1，
 *   user_logins.is_cancelled=1、is_enabled=0，user_infos 软删除并置为 MT4 只读；
 * - MT4 锁定失败：code=MT4_SYNC_FAILED，申请与账号状态全部保持原样。
 *
 * 异常或失败场景：
 * - MT4 lockUser 返回 error 时接口失败关闭（fail-closed），不产生部分变更。
 */

declare(strict_types=1);

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
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminCancelApplyApproveMt4ClosureModuleTest extends TestCase
{
    use DatabaseTransactions;

    // 审核通过且 MT4 锁定成功时应锁定 MT4、停用本地账号并软删除用户。
    public function test_cancel_approve_locks_mt4_and_disables_local_account_on_success(): void
    {
        $admin = $this->ensureSuperAdmin();
        $userId = 985101;
        $applyId = $this->createCancellationUserAndApply($userId, 'Cancel MT4 Success User');

        $calls = [];
        $this->app->instance(Mt4ManagerService::class, new class($calls) extends Mt4ManagerService {
            /**
             * MT4 lockUser 替身的调用捕获表。记录被锁定的 userId，断言审批通过触发的锁定指令与目标。
             * @var array<int, int>
             */
            private $calls;

            public function __construct(array &$calls)
            {
                $this->calls = &$calls;
                parent::__construct('127.0.0.1', 0, 'k', '1', 1);
            }

            public function lockUser($userId)
            {
                $this->calls[] = (int) $userId;

                return ['status' => 'ok', 'message' => 'locked', 'data' => []];
            }
        });

        $response = $this->withoutMiddleware([
            AdminAuthenticate::class,
            JwtAuthMiddleware::class,
            SingleSignOn::class,
            CheckPermission::class,
        ])
            ->actingAs($admin, 'admin')
            ->postJson('/api/admin/cancelApplyApprove/' . $applyId);

        $response->assertOk()->assertJsonPath('code', ResponseCode::SUCCESS);
        $this->assertSame([$userId], $calls);
        $this->assertDatabaseHas('cancel_applies', ['id' => $applyId, 'status' => 1]);
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
        $this->assertSoftDeleted('user_infos', ['user_id' => $userId]);
    }

    // MT4 锁定失败时审核应失败关闭（fail-closed），申请与账号状态全部回滚。
    public function test_cancel_approve_fails_closed_when_mt4_lock_fails(): void
    {
        $admin = $this->ensureSuperAdmin();
        $userId = 985102;
        $applyId = $this->createCancellationUserAndApply($userId, 'Cancel MT4 Fail User');

        $this->app->instance(Mt4ManagerService::class, new class extends Mt4ManagerService {
            public function __construct()
            {
                parent::__construct('127.0.0.1', 0, 'k', '1', 1);
            }

            public function lockUser($userId)
            {
                return [
                    'status' => 'error',
                    'message' => 'connection failed',
                    'error_code' => 'connection_failed',
                    'data' => [],
                ];
            }
        });

        $response = $this->withoutMiddleware([
            AdminAuthenticate::class,
            JwtAuthMiddleware::class,
            SingleSignOn::class,
            CheckPermission::class,
        ])
            ->actingAs($admin, 'admin')
            ->postJson('/api/admin/cancelApplyApprove/' . $applyId);

        $response->assertOk()->assertJsonPath('code', ResponseCode::MT4_SYNC_FAILED);
        $this->assertDatabaseHas('cancel_applies', ['id' => $applyId, 'status' => 0]);
        $this->assertDatabaseHas('user_logins', [
            'user_id' => $userId,
            'is_cancelled' => 0,
            'is_enabled' => 1,
        ]);
        $this->assertDatabaseHas('user_infos', [
            'user_id' => $userId,
            'is_mt4_enabled' => 1,
            'is_mt4_readonly' => 0,
        ]);
        $this->assertNull(
            DB::table('user_infos')->where('user_id', $userId)->value('deleted_at')
        );
    }

    private function ensureSuperAdmin(): Admin
    {
        $now = time();
        DB::table('admins')->updateOrInsert(
            ['id' => 1],
            [
                'username' => 'cancel-apply-mt4-admin',
                'email' => 'cancel-apply-mt4-admin@example.test',
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
            ['email' => 'cancel-mt4-' . $userId . '@example.test'],
            [
                'user_id' => $userId,
                'password' => Hash::make('password'),
                'account_type' => 2,
                'role_id' => 0,
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
                'phone' => '',
                'gender' => 1,
                'account_type' => 2,
                'parent_id' => 0,
                'family_tree' => (string) $userId,
                'total_funds' => 0,
                'equity' => 0,
                'is_mt4_enabled' => 1,
                'is_mt4_readonly' => 0,
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
