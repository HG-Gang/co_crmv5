<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/02
 * Time: 01:56
 */

/**
 * 后台用户状态变更接口 is_enabled 参数取值校验的功能测试。
 *
 * 文件功能：
 * - 验证 is_enabled 取合法值 0/1 时接口成功并写入 user_logins.is_enabled。
 * - 验证 is_enabled 取其他值（如 2）时返回校验失败且登录状态不变。
 *
 * 适用场景：
 * - 后台用户启用/禁用操作，保证状态字段只接受 0 或 1。
 *
 * 入参例子：
 * - POST /api/admin/changeUserStatus，body：{"user_id": 98726501, "is_enabled": 0|1|2}。
 *
 * 返回值：
 * - 合法值返回 code=ResponseCode::SUCCESS。
 * - 非法值返回 code=ResponseCode::VALIDATION_FAILED。
 *
 * 异常或失败场景：
 * - is_enabled 非 0/1 时接口拒绝执行并保持原登录状态不变。
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

class AdminUserStatusValidationClosureModuleTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->app->instance(Mt4ManagerService::class, new class extends Mt4ManagerService {
            public function __construct()
            {
                parent::__construct('127.0.0.1', 0, 'k', '1', 1);
            }

            public function lockUser($userId)
            {
                return ['status' => 'ok', 'message' => 'locked', 'data' => []];
            }

            public function unlockUser($userId)
            {
                return ['status' => 'ok', 'message' => 'unlocked', 'data' => []];
            }
        });
    }

    // 验证 is_enabled 取 0/1 时状态变更成功并正确写入登录状态。
    public function test_change_user_status_accepts_enabled_and_disabled_values(): void
    {
        $admin = $this->ensureSuperAdmin();
        $userId = 98726501;
        $this->createManagedUser($userId, 1);

        $disableResponse = $this->withoutMiddleware([AdminAuthenticate::class, JwtAuthMiddleware::class, SingleSignOn::class, CheckPermission::class])
            ->actingAs($admin, 'admin')
            ->post('/api/admin/changeUserStatus', [
                'user_id' => $userId,
                'is_enabled' => 0,
            ]);

        $disableResponse->assertOk()
            ->assertJsonPath('code', ResponseCode::SUCCESS);
        $this->assertSame(0, (int) DB::table('user_logins')->where('user_id', $userId)->value('is_enabled'));

        $enableResponse = $this->withoutMiddleware([AdminAuthenticate::class, JwtAuthMiddleware::class, SingleSignOn::class, CheckPermission::class])
            ->actingAs($admin, 'admin')
            ->post('/api/admin/changeUserStatus', [
                'user_id' => $userId,
                'is_enabled' => 1,
            ]);

        $enableResponse->assertOk()
            ->assertJsonPath('code', ResponseCode::SUCCESS);
        $this->assertSame(1, (int) DB::table('user_logins')->where('user_id', $userId)->value('is_enabled'));
    }

    // 验证 is_enabled 取非法值 2 时返回校验失败且登录状态不变。
    public function test_change_user_status_rejects_invalid_enabled_value_without_writing_login(): void
    {
        $admin = $this->ensureSuperAdmin();
        $userId = 98726502;
        $this->createManagedUser($userId, 1);

        $response = $this->withoutMiddleware([AdminAuthenticate::class, JwtAuthMiddleware::class, SingleSignOn::class, CheckPermission::class])
            ->actingAs($admin, 'admin')
            ->post('/api/admin/changeUserStatus', [
                'user_id' => $userId,
                'is_enabled' => 2,
            ]);

        $response->assertOk()
            ->assertJsonPath('code', ResponseCode::VALIDATION_FAILED);

        $this->assertSame(1, (int) DB::table('user_logins')->where('user_id', $userId)->value('is_enabled'));
    }

    // 校验最终检查清单文档记录了用户状态取值校验边界。
    public function test_final_checklist_records_admin_user_status_validation_boundary(): void
    {
        $checklist = file_get_contents(base_path('docs/admin-backend-blade-permission-final-checklist.md')) ?: '';

        $this->assertStringContainsString('## 265.', $checklist);
        $this->assertStringContainsString('AdminUserController::changeUserStatus', $checklist);
        $this->assertStringContainsString('/api/admin/changeUserStatus', $checklist);
        $this->assertStringContainsString('user_logins.is_enabled', $checklist);
        $this->assertStringContainsString('0/1', $checklist);
        $this->assertStringContainsString('AdminUserStatusValidationClosureModuleTest', $checklist);
    }

    private function ensureSuperAdmin(): Admin
    {
        $now = time();

        DB::table('admins')->updateOrInsert(
            ['id' => 1],
            [
                'username' => 'admin-user-status-super',
                'email' => 'admin-user-status-super@example.test',
                'password' => bcrypt('password'),
                'status' => 1,
                'created_at' => $now,
                'updated_at' => $now,
                'deleted_at' => null,
            ]
        );

        return Admin::query()->findOrFail(1);
    }

    private function createManagedUser(int $userId, int $isEnabled): void
    {
        $now = time();

        DB::table('user_infos')->where('user_id', $userId)->delete();
        DB::table('user_logins')->where('user_id', $userId)->delete();

        $loginId = DB::table('user_logins')->insertGetId([
            'user_id' => $userId,
            'email' => 'admin-user-status-' . $userId . '@example.test',
            'password' => bcrypt('password'),
            'account_type' => 2,
            'role_id' => 0,
            'is_enabled' => $isEnabled,
            'is_cancelled' => 0,
            'source_type' => 0,
            'created_at' => $now,
            'updated_at' => $now,
            'deleted_at' => null,
        ]);

        DB::table('user_infos')->insert([
            'user_id' => $userId,
            'login_id' => $loginId,
            'user_name' => 'Admin User Status ' . $userId,
            'phone' => '188265' . substr((string) $userId, -5),
            'account_type' => 2,
            'parent_id' => 0,
            'auth_status' => 0,
            'created_at' => $now,
            'updated_at' => $now,
            'deleted_at' => null,
        ]);
    }
}
