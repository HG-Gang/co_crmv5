<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/07/28
 * Time: 23:33
 */

/**
 * AdminUserUpdatePasswordClosureModuleTest
 *
 * 文件功能：
 * - 验证后台用户更新密码边界：成功改密更新登录密码并对审计日志脱敏、失败保留登录与资料、密码占位符不调用密码服务。
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
use App\Models\UserLogin;
use App\Services\UserPasswordService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * 后台普通用户资料编辑密码闭环测试。
 *
 * 功能逻辑说明：
 * - 旧项目 cust_save_info 在 password 不等于 ******** 时会同步修改 MT4 密码并更新本地登录密码。
 * - 新项目 updateUser 需要兼容该旧字段，但不能把明文密码写入审计日志。
 * - 密码服务失败代表远端或密码同步没有明确成功，资料编辑必须整体失败并保持本地资料不变。
 */
class AdminUserUpdatePasswordClosureModuleTest extends TestCase
{
    use DatabaseTransactions;

    public function test_update_user_password_success_changes_login_password_and_masks_audit_log(): void
    {
        $admin = $this->ensureSuperAdmin();
        $userId = 98726801;
        $this->seedUser($userId, 'Before Password Success', 'old-password');

        $calls = [];
        $this->bindPasswordService($calls, true);

        $response = $this->withoutMiddleware([AdminAuthenticate::class, JwtAuthMiddleware::class, SingleSignOn::class, CheckPermission::class])
            ->actingAs($admin, 'admin')
            ->postJson('/api/admin/updateUser', [
                'user_id' => $userId,
                'user_name' => 'After Password Success',
                'password' => 'new-secret-98726801',
            ]);

        $response->assertOk()
            ->assertJsonPath('code', ResponseCode::UPDATED);

        $this->assertSame([
            [
                'user_id' => $userId,
                'password' => 'new-secret-98726801',
                'before_name' => 'Before Password Success',
            ],
        ], $calls);

        $login = DB::table('user_logins')->where('user_id', $userId)->first();
        $this->assertTrue(Hash::check('new-secret-98726801', (string) $login->password));
        $this->assertDatabaseHas('user_infos', [
            'user_id' => $userId,
            'user_name' => 'After Password Success',
        ]);

        $log = DB::table('operation_logs')->where('order_no', 'user_update:' . $userId)->first();
        $this->assertNotNull($log, 'updateUser password branch must create an audit record.');
        $this->assertStringContainsString('password:changed', (string) $log->content);
        $this->assertStringNotContainsString('new-secret-98726801', (string) $log->content);
    }

    public function test_update_user_password_failure_preserves_login_and_profile(): void
    {
        $admin = $this->ensureSuperAdmin();
        $userId = 98726802;
        $this->seedUser($userId, 'Before Password Fail', 'old-password');

        $calls = [];
        $this->bindPasswordService($calls, false);

        $response = $this->withoutMiddleware([AdminAuthenticate::class, JwtAuthMiddleware::class, SingleSignOn::class, CheckPermission::class])
            ->actingAs($admin, 'admin')
            ->postJson('/api/admin/updateUser', [
                'user_id' => $userId,
                'user_name' => 'After Password Fail',
                'password1' => 'rejected-secret-98726802',
            ]);

        $response->assertOk()
            ->assertJsonPath('code', ResponseCode::MT4_SYNC_FAILED);

        $this->assertSame([
            [
                'user_id' => $userId,
                'password' => 'rejected-secret-98726802',
                'before_name' => 'Before Password Fail',
            ],
        ], $calls);

        $login = DB::table('user_logins')->where('user_id', $userId)->first();
        $this->assertTrue(Hash::check('old-password', (string) $login->password));
        $this->assertFalse(Hash::check('rejected-secret-98726802', (string) $login->password));
        $this->assertDatabaseHas('user_infos', [
            'user_id' => $userId,
            'user_name' => 'Before Password Fail',
        ]);
        $this->assertSame(0, DB::table('operation_logs')->where('order_no', 'user_update:' . $userId)->count());
    }

    public function test_password_placeholder_does_not_call_password_service(): void
    {
        $admin = $this->ensureSuperAdmin();
        $userId = 98726803;
        $this->seedUser($userId, 'Before Placeholder', 'old-password');

        $calls = [];
        $this->bindPasswordService($calls, true);

        $response = $this->withoutMiddleware([AdminAuthenticate::class, JwtAuthMiddleware::class, SingleSignOn::class, CheckPermission::class])
            ->actingAs($admin, 'admin')
            ->postJson('/api/admin/updateUser', [
                'user_id' => $userId,
                'user_name' => 'After Placeholder',
                'password' => '********',
            ]);

        $response->assertOk()
            ->assertJsonPath('code', ResponseCode::UPDATED);

        $this->assertSame([], $calls);
        $login = DB::table('user_logins')->where('user_id', $userId)->first();
        $this->assertTrue(Hash::check('old-password', (string) $login->password));
        $this->assertDatabaseHas('user_infos', [
            'user_id' => $userId,
            'user_name' => 'After Placeholder',
        ]);
    }

    public function test_final_checklist_records_admin_user_update_password_boundary(): void
    {
        $checklist = file_get_contents(base_path('docs/admin-backend-blade-permission-final-checklist.md')) ?: '';

        $this->assertStringContainsString('UserPasswordService', $checklist);
        $this->assertStringContainsString('password:changed', $checklist);
        $this->assertStringContainsString('********', $checklist);
        $this->assertStringContainsString('AdminUserUpdatePasswordClosureModuleTest', $checklist);
    }

    private function ensureSuperAdmin(): Admin
    {
        $now = time();

        DB::table('admins')->updateOrInsert(
            ['id' => 1],
            [
                'username' => 'admin-user-update-password-super',
                'email' => 'admin-user-update-password-super@example.test',
                'password' => bcrypt('password'),
                'status' => 1,
                'created_at' => $now,
                'updated_at' => $now,
                'deleted_at' => null,
            ]
        );

        return Admin::query()->findOrFail(1);
    }

    private function seedUser(int $userId, string $userName, string $password): void
    {
        $now = time();

        DB::table('operation_logs')->where('order_no', 'user_update:' . $userId)->delete();
        DB::table('user_infos')->where('user_id', $userId)->delete();
        DB::table('user_logins')->where('user_id', $userId)->delete();

        $loginId = DB::table('user_logins')->insertGetId([
            'user_id' => $userId,
            'email' => 'admin-user-update-password-' . $userId . '@example.test',
            'password' => Hash::make($password),
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
            'phone' => '',
            'account_type' => 2,
            'parent_id' => 0,
            'family_tree' => (string) $userId,
            'auth_status' => 0,
            'created_at' => $now,
            'updated_at' => $now,
            'deleted_at' => null,
        ]);
    }

    private function bindPasswordService(array &$calls, bool $ok): void
    {
        $this->app->instance(UserPasswordService::class, new class($calls, $ok) extends UserPasswordService {
            /**
             * 密码服务替身的调用捕获表。记录 change() 收到的 [user_id, 新密码, 改前 user_name]，
             * 断言改密时序与写入目标。
             * @var array<int, array{user_id: int, password: string, before_name: string}>
             */
            private $calls;
            /**
             * 密码服务替身的成功开关。false 返回 false 且不落库，驱动改密失败回滚分支。
             * @var bool
             */
            private $ok;

            public function __construct(array &$calls, bool $ok)
            {
                $this->calls = &$calls;
                $this->ok = $ok;
            }

            public function change(UserLogin $login, string $newPassword): bool
            {
                $beforeName = DB::table('user_infos')->where('user_id', (int) $login->user_id)->value('user_name');
                $this->calls[] = [
                    'user_id' => (int) $login->user_id,
                    'password' => $newPassword,
                    'before_name' => (string) $beforeName,
                ];

                if (!$this->ok) {
                    return false;
                }

                $login->update(['password' => Hash::make($newPassword)]);

                return true;
            }
        });
    }
}
