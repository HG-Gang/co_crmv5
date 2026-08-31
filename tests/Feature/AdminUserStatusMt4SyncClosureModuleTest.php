<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/02
 * Time: 01:56
 */

declare(strict_types=1);

/**
 * 后台用户启停状态变更与 MT4 同步联动（fail-closed）的功能测试。
 *
 * 文件功能：
 * - 验证禁用用户时调用 MT4 lockUser 并同步本地 user_logins.is_enabled、user_infos.is_mt4_enabled/is_mt4_readonly。
 * - 验证启用用户时调用 MT4 unlockUser 并同步本地状态。
 * - 验证 MT4 锁定失败时接口返回 MT4_SYNC_FAILED 且本地状态回滚保持不变。
 *
 * 适用场景：
 * - 后台用户管理页面的启用/禁用操作，需与 MT4 账户锁定状态保持一致。
 *
 * 入参例子：
 * - POST /api/admin/changeUserStatus，body：{"user_id": 986201, "is_enabled": 0|1}。
 *
 * 返回值：
 * - 同步成功返回 code=ResponseCode::SUCCESS。
 * - MT4 同步失败返回 code=ResponseCode::MT4_SYNC_FAILED。
 *
 * 异常或失败场景：
 * - MT4 网关不可达时本地状态保持原值，接口失败关闭。
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

class AdminUserStatusMt4SyncClosureModuleTest extends TestCase
{
    use DatabaseTransactions;

    // 验证禁用用户触发 MT4 锁定并同步本地禁用与只读标记。
    public function test_disable_user_locks_mt4_and_updates_local_flags(): void
    {
        $admin = $this->ensureSuperAdmin();
        $userId = 986201;
        $this->seedUser($userId, 'Status MT4 Disable User', true);

        $calls = [];
        $this->bindMt4($calls, true);

        $response = $this->withoutMiddleware([
            AdminAuthenticate::class,
            JwtAuthMiddleware::class,
            SingleSignOn::class,
            CheckPermission::class,
        ])
            ->actingAs($admin, 'admin')
            ->postJson('/api/admin/changeUserStatus', [
                'user_id' => $userId,
                'is_enabled' => 0,
            ]);

        $response->assertOk()->assertJsonPath('code', ResponseCode::SUCCESS);
        $this->assertSame(['lock:' . $userId], $calls);
        $this->assertDatabaseHas('user_logins', [
            'user_id' => $userId,
            'is_enabled' => 0,
        ]);
        $this->assertDatabaseHas('user_infos', [
            'user_id' => $userId,
            'is_mt4_enabled' => 0,
            'is_mt4_readonly' => 1,
        ]);
    }

    // 验证启用用户触发 MT4 解锁并同步本地启用与可交易标记。
    public function test_enable_user_unlocks_mt4_and_updates_local_flags(): void
    {
        $admin = $this->ensureSuperAdmin();
        $userId = 986202;
        $this->seedUser($userId, 'Status MT4 Enable User', false);

        $calls = [];
        $this->bindMt4($calls, true);

        $response = $this->withoutMiddleware([
            AdminAuthenticate::class,
            JwtAuthMiddleware::class,
            SingleSignOn::class,
            CheckPermission::class,
        ])
            ->actingAs($admin, 'admin')
            ->postJson('/api/admin/changeUserStatus', [
                'user_id' => $userId,
                'is_enabled' => 1,
            ]);

        $response->assertOk()->assertJsonPath('code', ResponseCode::SUCCESS);
        $this->assertSame(['unlock:' . $userId], $calls);
        $this->assertDatabaseHas('user_logins', [
            'user_id' => $userId,
            'is_enabled' => 1,
        ]);
        $this->assertDatabaseHas('user_infos', [
            'user_id' => $userId,
            'is_mt4_enabled' => 1,
            'is_mt4_readonly' => 0,
        ]);
    }

    // 验证 MT4 锁定失败时接口失败关闭且本地用户状态保持不变。
    public function test_disable_user_fails_closed_when_mt4_lock_fails(): void
    {
        $admin = $this->ensureSuperAdmin();
        $userId = 986203;
        $this->seedUser($userId, 'Status MT4 Fail User', true);
        $calls = [];
        $this->bindMt4($calls, false);

        $response = $this->withoutMiddleware([
            AdminAuthenticate::class,
            JwtAuthMiddleware::class,
            SingleSignOn::class,
            CheckPermission::class,
        ])
            ->actingAs($admin, 'admin')
            ->postJson('/api/admin/changeUserStatus', [
                'user_id' => $userId,
                'is_enabled' => 0,
            ]);

        $response->assertOk()->assertJsonPath('code', ResponseCode::MT4_SYNC_FAILED);
        $this->assertDatabaseHas('user_logins', [
            'user_id' => $userId,
            'is_enabled' => 1,
        ]);
        $this->assertDatabaseHas('user_infos', [
            'user_id' => $userId,
            'is_mt4_enabled' => 1,
            'is_mt4_readonly' => 0,
        ]);
    }

    private function ensureSuperAdmin(): Admin
    {
        $now = time();
        DB::table('admins')->updateOrInsert(
            ['id' => 1],
            [
                'username' => 'admin-user-status-mt4',
                'email' => 'admin-user-status-mt4@example.test',
                'password' => Hash::make('password'),
                'status' => 1,
                'created_at' => $now,
                'updated_at' => $now,
                'deleted_at' => null,
            ]
        );

        return Admin::query()->findOrFail(1);
    }

    private function seedUser(int $userId, string $userName, bool $enabled): void
    {
        $now = time();
        DB::table('user_logins')->updateOrInsert(
            ['email' => 'status-mt4-' . $userId . '@example.test'],
            [
                'user_id' => $userId,
                'password' => Hash::make('password'),
                'account_type' => 2,
                'role_id' => 0,
                'is_enabled' => $enabled ? 1 : 0,
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
                'is_mt4_enabled' => $enabled ? 1 : 0,
                'is_mt4_readonly' => $enabled ? 0 : 1,
                'created_at' => $now,
                'updated_at' => $now,
                'deleted_at' => null,
            ]
        );
    }

    private function bindMt4(array &$calls, bool $ok): void
    {
        $this->app->instance(Mt4ManagerService::class, new class($calls, $ok) extends Mt4ManagerService {
            /**
             * MT4 替身的调用捕获表。记录用户状态同步指令的入参，断言启用/禁用触发的 MT4 操作。
             * @var array<int, array<string, mixed>>
             */
            private $calls;
            /**
             * MT4 替身的成功开关。false 返回连接失败，验证状态同步失败时的失败关闭行为。
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
                $this->calls[] = 'lock:' . (int) $userId;

                return $this->ok
                    ? ['status' => 'ok', 'message' => 'locked', 'data' => []]
                    : ['status' => 'error', 'error_code' => 'connection_failed', 'message' => 'fail', 'data' => []];
            }

            public function unlockUser($userId)
            {
                $this->calls[] = 'unlock:' . (int) $userId;

                return $this->ok
                    ? ['status' => 'ok', 'message' => 'unlocked', 'data' => []]
                    : ['status' => 'error', 'error_code' => 'connection_failed', 'message' => 'fail', 'data' => []];
            }
        });
    }
}
