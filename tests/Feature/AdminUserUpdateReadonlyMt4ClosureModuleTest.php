<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/07/29
 * Time: 00:14
 */

/**
 * AdminUserUpdateReadonlyMt4ClosureModuleTest
 *
 * 文件功能：
 * - 验证后台用户更新 MT4 只读边界：本地只读写入前先锁 MT4、解锁失败失败关闭、非法只读值拒绝部分写入。
 * - 输入：HTTP 请求与事务回滚的数据库夹具；输出：PHPUnit 断言结果。
 * - 明确不负责：不覆盖真实 MT4 网关与线上支付/出金通道（由网关契约测试锁定）。
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
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * 后台普通用户资料编辑 MT4 只读状态闭环测试。
 *
 * 功能逻辑说明：
 * - 旧项目 CustomerController::cust_save_info 使用 enablereadonly 控制 MT4 账号是否只读。
 * - enablereadonly=1 表示能登录但不能交易，需要先调用 MT4 lock_user。
 * - enablereadonly=0 表示解除只读，需要先调用 MT4 unlock_user。
 * - MT4 未明确成功时本地 user_infos.is_mt4_readonly 不能先改，避免后台状态与真实交易账号分叉。
 */
class AdminUserUpdateReadonlyMt4ClosureModuleTest extends TestCase
{
    use DatabaseTransactions;

    public function test_admin_user_update_locks_mt4_before_local_readonly_write(): void
    {
        $admin = $this->ensureSuperAdmin();
        $userId = 98727601;
        $this->seedUser($userId, 'Before Readonly Lock', 0);

        $calls = [];
        $this->bindReadonlyMt4($calls, true);

        $response = $this->withoutMiddleware([AdminAuthenticate::class, JwtAuthMiddleware::class, SingleSignOn::class, CheckPermission::class])
            ->actingAs($admin, 'admin')
            ->postJson('/api/admin/updateUser', [
                'data' => [
                    'userId' => $userId,
                    'username' => 'After Readonly Lock',
                ],
                'enablereadonly' => '1',
            ]);

        $response->assertOk()
            ->assertJsonPath('code', ResponseCode::UPDATED);

        $this->assertSame([
            [
                'action' => 'lock',
                'user_id' => $userId,
                'before_readonly' => 0,
                'before_user_name' => 'Before Readonly Lock',
            ],
        ], $calls);

        $this->assertDatabaseHas('user_infos', [
            'user_id' => $userId,
            'user_name' => 'After Readonly Lock',
            'is_mt4_readonly' => 1,
        ]);

        $log = DB::table('operation_logs')
            ->where('order_no', 'user_update:' . $userId)
            ->first();

        $this->assertNotNull($log, 'updateUser 修改 MT4 只读状态后必须写 operation_logs 审计记录。');
        $this->assertStringContainsString('is_mt4_readonly:0->1', (string) $log->content);
    }

    public function test_admin_user_update_readonly_fails_closed_when_mt4_unlock_fails(): void
    {
        $admin = $this->ensureSuperAdmin();
        $userId = 98727602;
        $this->seedUser($userId, 'Before Readonly Unlock Fail', 1);

        $calls = [];
        $this->bindReadonlyMt4($calls, false);

        $response = $this->withoutMiddleware([AdminAuthenticate::class, JwtAuthMiddleware::class, SingleSignOn::class, CheckPermission::class])
            ->actingAs($admin, 'admin')
            ->patch('/api/admin/users/' . $userId, [
                'user_name' => 'Should Not Persist Readonly',
                'enablereadonly' => '0',
            ]);

        $response->assertOk()
            ->assertJsonPath('code', ResponseCode::MT4_SYNC_FAILED);

        $this->assertSame('unlock', $calls[0]['action'] ?? null);
        $this->assertDatabaseHas('user_infos', [
            'user_id' => $userId,
            'user_name' => 'Before Readonly Unlock Fail',
            'is_mt4_readonly' => 1,
        ]);
        $this->assertSame(0, DB::table('operation_logs')->where('order_no', 'user_update:' . $userId)->count());
    }

    public function test_admin_user_update_rejects_invalid_readonly_value_without_partial_profile_write(): void
    {
        $admin = $this->ensureSuperAdmin();
        $userId = 98727603;
        $this->seedUser($userId, 'Before Invalid Readonly', 0);

        $response = $this->withoutMiddleware([AdminAuthenticate::class, JwtAuthMiddleware::class, SingleSignOn::class, CheckPermission::class])
            ->actingAs($admin, 'admin')
            ->patch('/api/admin/users/' . $userId, [
                'user_name' => 'Should Not Persist Invalid Readonly',
                'enablereadonly' => '2',
            ]);

        $response->assertOk()
            ->assertJsonPath('code', ResponseCode::VALIDATION_FAILED);

        $this->assertDatabaseHas('user_infos', [
            'user_id' => $userId,
            'user_name' => 'Before Invalid Readonly',
            'is_mt4_readonly' => 0,
        ]);
        $this->assertSame(0, DB::table('operation_logs')->where('order_no', 'user_update:' . $userId)->count());
    }

    public function test_final_checklist_records_admin_user_update_readonly_boundary(): void
    {
        $checklist = file_get_contents(base_path('docs/admin-backend-blade-permission-final-checklist.md')) ?: '';

        $this->assertStringContainsString('AdminUserController::updateUser', $checklist);
        $this->assertStringContainsString('enablereadonly', $checklist);
        $this->assertStringContainsString('user_infos.is_mt4_readonly', $checklist);
        $this->assertStringContainsString('Mt4ManagerService::lockUser', $checklist);
        $this->assertStringContainsString('Mt4ManagerService::unlockUser', $checklist);
        $this->assertStringContainsString('AdminUserUpdateReadonlyMt4ClosureModuleTest', $checklist);
    }

    private function ensureSuperAdmin(): Admin
    {
        $now = time();

        DB::table('admins')->updateOrInsert(
            ['id' => 1],
            [
                'username' => 'admin-user-update-readonly-super',
                'email' => 'admin-user-update-readonly-super@example.test',
                'password' => Hash::make('password'),
                'status' => 1,
                'created_at' => $now,
                'updated_at' => $now,
                'deleted_at' => null,
            ]
        );

        return Admin::query()->findOrFail(1);
    }

    /**
     * 创建 MT4 只读状态测试用户。
     *
     * @param int $userId 业务用户 ID。
     * @param string $userName 更新前用户姓名。
     * @param int $readonly 当前本地 MT4 只读标记，0=可交易，1=只读。
     * @return void
     */
    private function seedUser(int $userId, string $userName, int $readonly): void
    {
        $now = time();

        DB::table('operation_logs')->where('order_no', 'user_update:' . $userId)->delete();
        DB::table('user_infos')->where('user_id', $userId)->delete();
        DB::table('user_logins')->where('user_id', $userId)->delete();

        $loginId = DB::table('user_logins')->insertGetId([
            'user_id' => $userId,
            'email' => 'admin-user-update-readonly-' . $userId . '@example.test',
            'password' => Hash::make('password'),
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
            'user_name' => $userName,
            'phone' => '18827601001',
            'gender' => 1,
            'account_type' => 2,
            'parent_id' => 0,
            'family_tree' => (string) $userId,
            'auth_status' => 0,
            'mt4_group' => 'READONLY-GROUP',
            'leverage' => 100,
            'is_mt4_enabled' => 1,
            'is_mt4_readonly' => $readonly,
            'created_at' => $now,
            'updated_at' => $now,
            'deleted_at' => null,
        ]);
    }

    private function bindReadonlyMt4(array &$calls, bool $ok): void
    {
        $this->app->instance(Mt4ManagerService::class, new class($calls, $ok) extends Mt4ManagerService {
            /**
             * MT4 替身的调用捕获表。记录 updateUserTradingProfile 的入参，断言只读字段更新不触发 MT4 指令。
             * @var array<int, array<string, mixed>>
             */
            private $calls;
            /**
             * MT4 替身的成功开关。false 返回连接失败，驱动只读更新路径的失败关闭断言。
             * @var bool
             */
            private $ok;

            public function __construct(array &$calls, bool $ok)
            {
                $this->calls = &$calls;
                $this->ok = $ok;
                parent::__construct('127.0.0.1', 0, 'k', '1', 1);
            }

            public function lockUser($userId)
            {
                $this->recordCall('lock', (int) $userId);

                return $this->mt4Result();
            }

            public function unlockUser($userId)
            {
                $this->recordCall('unlock', (int) $userId);

                return $this->mt4Result();
            }

            private function recordCall(string $action, int $userId): void
            {
                $user = DB::table('user_infos')->where('user_id', $userId)->first();
                $this->calls[] = [
                    'action' => $action,
                    'user_id' => $userId,
                    'before_readonly' => (int) $user->is_mt4_readonly,
                    'before_user_name' => (string) $user->user_name,
                ];
            }

            private function mt4Result(): array
            {
                return $this->ok
                    ? ['status' => 'ok', 'err' => '0', 'message' => 'updated', 'data' => []]
                    : ['status' => 'error', 'error_code' => 'connection_failed', 'message' => 'fail', 'data' => []];
            }
        });
    }
}
