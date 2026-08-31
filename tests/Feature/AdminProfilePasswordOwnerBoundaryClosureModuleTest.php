<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/02
 * Time: 01:54
 */

/**
 * 后台管理员修改密码接口的归属边界测试。
 *
 * 文件功能：
 * - 验证 changePassword 接口只修改当前登录管理员的密码，忽略请求中伪造的 id/admin_id。
 * - 验证旧密码错误时返回 OLD_PASSWORD_WRONG 且不修改任何管理员密码。
 *
 * 适用场景：
 * - 后台个人中心“修改密码”，防止通过伪造目标 id 修改他人密码。
 *
 * 入参例子：
 * - POST /api/admin/changePassword，body：{"id": 其它管理员id, "admin_id": 其它管理员id, "old_password": "...", "password": "...", "password_confirmation": "..."}。
 *
 * 返回值：
 * - 修改成功返回 code=ResponseCode::SUCCESS。
 * - 旧密码错误返回 code=ResponseCode::OLD_PASSWORD_WRONG。
 *
 * 异常或失败场景：
 * - 伪造目标 id 时仍修改当前管理员；旧密码错误时不落任何改动。
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

class AdminProfilePasswordOwnerBoundaryClosureModuleTest extends TestCase
{
    use DatabaseTransactions;

    // 验证修改密码只作用于当前管理员并忽略伪造目标 id。
    public function test_change_password_uses_current_admin_and_ignores_spoofed_target(): void
    {
        $current = $this->createManagedAdmin(
            'admin-password-current',
            'admin-password-current@example.test',
            'current-secret',
            '13926000001'
        );
        $other = $this->createManagedAdmin(
            'admin-password-other',
            'admin-password-other@example.test',
            'other-secret',
            '13926000002'
        );

        $response = $this->withoutMiddleware([AdminAuthenticate::class, JwtAuthMiddleware::class, SingleSignOn::class, CheckPermission::class])
            ->actingAs($current, 'admin')
            ->post('/api/admin/changePassword', [
                'id' => $other->id,
                'admin_id' => $other->id,
                'old_password' => 'current-secret',
                'password' => 'current-new-secret',
                'password_confirmation' => 'current-new-secret',
            ]);

        $response->assertOk()
            ->assertJsonPath('code', ResponseCode::SUCCESS);

        $currentRecord = DB::table('admins')->where('id', $current->id)->first();
        $otherRecord = DB::table('admins')->where('id', $other->id)->first();

        $this->assertTrue(Hash::check('current-new-secret', (string) $currentRecord->password));
        $this->assertFalse(Hash::check('current-secret', (string) $currentRecord->password));
        $this->assertTrue(Hash::check('other-secret', (string) $otherRecord->password));
        $this->assertFalse(Hash::check('current-new-secret', (string) $otherRecord->password));
    }

    // 验证旧密码错误时返回 OLD_PASSWORD_WRONG 且所有管理员密码均不变。
    public function test_change_password_rejects_wrong_current_password_without_touching_any_admin(): void
    {
        $current = $this->createManagedAdmin(
            'admin-password-wrong-current',
            'admin-password-wrong-current@example.test',
            'current-secret',
            '13926001001'
        );
        $other = $this->createManagedAdmin(
            'admin-password-wrong-other',
            'admin-password-wrong-other@example.test',
            'other-secret',
            '13926001002'
        );

        $response = $this->withoutMiddleware([AdminAuthenticate::class, JwtAuthMiddleware::class, SingleSignOn::class, CheckPermission::class])
            ->actingAs($current, 'admin')
            ->post('/api/admin/changePassword', [
                'id' => $other->id,
                'admin_id' => $other->id,
                'old_password' => 'wrong-current-secret',
                'password' => 'current-new-secret',
                'password_confirmation' => 'current-new-secret',
            ]);

        $response->assertOk()
            ->assertJsonPath('code', ResponseCode::OLD_PASSWORD_WRONG);

        $currentRecord = DB::table('admins')->where('id', $current->id)->first();
        $otherRecord = DB::table('admins')->where('id', $other->id)->first();

        $this->assertTrue(Hash::check('current-secret', (string) $currentRecord->password));
        $this->assertFalse(Hash::check('current-new-secret', (string) $currentRecord->password));
        $this->assertTrue(Hash::check('other-secret', (string) $otherRecord->password));
        $this->assertFalse(Hash::check('current-new-secret', (string) $otherRecord->password));
    }

    // 校验最终检查清单文档记录了个人资料密码归属边界。
    public function test_final_checklist_records_admin_profile_password_owner_boundary(): void
    {
        $checklist = file_get_contents(base_path('docs/admin-backend-blade-permission-final-checklist.md')) ?: '';

        $this->assertStringContainsString('## 260.', $checklist);
        $this->assertStringContainsString('AuthController::changePassword', $checklist);
        $this->assertStringContainsString('/api/admin/changePassword', $checklist);
        $this->assertStringContainsString('admins.password', $checklist);
        $this->assertStringContainsString('AdminProfilePasswordOwnerBoundaryClosureModuleTest', $checklist);
    }

    private function createManagedAdmin(string $username, string $email, string $password, string $mobile): Admin
    {
        $now = time();

        DB::table('admins')
            ->where('username', $username)
            ->orWhere('email', $email)
            ->delete();

        $id = DB::table('admins')->insertGetId([
            'username' => $username,
            'email' => $email,
            'password' => Hash::make($password),
            'mobile' => $mobile,
            'status' => 1,
            'created_at' => $now,
            'updated_at' => $now,
            'deleted_at' => null,
        ]);

        return Admin::query()->findOrFail($id);
    }
}
