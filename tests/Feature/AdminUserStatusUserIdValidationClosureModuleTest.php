<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/02
 * Time: 01:56
 */

/**
 * 后台用户状态变更接口 user_id 参数严格校验的功能测试。
 *
 * 文件功能：
 * - 验证 user_id 传入非严格数字时状态变更接口返回校验失败。
 * - 验证校验失败后用户登录状态不被写入。
 *
 * 适用场景：
 * - 后台用户启停操作，防止非法 user_id 误改用户状态。
 *
 * 入参例子：
 * - POST /api/admin/changeUserStatus，body：{"user_id": "98728201abc", "is_enabled": 0}。
 *
 * 返回值：
 * - 校验失败返回 code=ResponseCode::VALIDATION_FAILED。
 *
 * 异常或失败场景：
 * - user_id 非严格整数时接口拒绝执行并保持原登录状态不变。
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

class AdminUserStatusUserIdValidationClosureModuleTest extends TestCase
{
    use DatabaseTransactions;

    // 验证状态变更对非严格 user_id 返回校验失败且登录状态不变。
    public function test_change_user_status_rejects_non_strict_user_id_without_writing_login_status(): void
    {
        $admin = $this->ensureSuperAdmin();
        $userId = 98728201;

        $this->createManagedUser($userId, 1);

        $response = $this->withoutMiddleware([AdminAuthenticate::class, JwtAuthMiddleware::class, SingleSignOn::class, CheckPermission::class])
            ->actingAs($admin, 'admin')
            ->post('/api/admin/changeUserStatus', [
                'user_id' => $userId . 'abc',
                'is_enabled' => 0,
            ]);

        $response->assertOk()
            ->assertJsonPath('code', ResponseCode::VALIDATION_FAILED);

        $this->assertSame(1, (int) DB::table('user_logins')->where('user_id', $userId)->value('is_enabled'));
    }

    // 校验最终检查清单文档记录了用户状态 user_id 校验边界。
    public function test_final_checklist_records_admin_user_status_user_id_validation_boundary(): void
    {
        $checklist = file_get_contents(base_path('docs/admin-backend-blade-permission-final-checklist.md')) ?: '';

        $this->assertStringContainsString('## 282.', $checklist);
        $this->assertStringContainsString('AdminUserController::changeUserStatus', $checklist);
        $this->assertStringContainsString('/api/admin/changeUserStatus', $checklist);
        $this->assertStringContainsString('user_id', $checklist);
        $this->assertStringContainsString('user_logins.user_id', $checklist);
        $this->assertStringContainsString('AdminUserStatusUserIdValidationClosureModuleTest', $checklist);
    }

    private function ensureSuperAdmin(): Admin
    {
        $now = time();

        DB::table('admins')->updateOrInsert(
            ['id' => 1],
            [
                'username' => 'admin-user-status-user-id-super',
                'email' => 'admin-user-status-user-id-super@example.test',
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
            'email' => 'admin-user-status-user-id-' . $userId . '@example.test',
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
            'user_name' => 'Admin User Status Strict User Id',
            'phone' => '188282' . substr((string) $userId, -5),
            'account_type' => 2,
            'parent_id' => 0,
            'auth_status' => 0,
            'created_at' => $now,
            'updated_at' => $now,
            'deleted_at' => null,
        ]);
    }
}
