<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/02
 * Time: 01:55
 */

/**
 * 后台管理员个人资料更新接口的归属边界测试。
 *
 * 文件功能：
 * - 验证 updateProfile 接口只更新当前登录管理员，忽略伪造的 id、username、role_id、status、password 等敏感字段。
 * - 验证邮箱被其他管理员占用时返回校验失败且不落任何改动。
 *
 * 适用场景：
 * - 后台个人中心“编辑资料”，防止越权修改他人资料或提权。
 *
 * 入参例子：
 * - POST /api/admin/updateProfile，body：{"id": 其它管理员id, "email": "...", "mobile": "..."}。
 *
 * 返回值：
 * - 更新成功返回 code=ResponseCode::SUCCESS。
 * - 邮箱重复返回 code=ResponseCode::VALIDATION_FAILED。
 *
 * 异常或失败场景：
 * - 伪造敏感字段（role_id、status、password）时被忽略；邮箱被他人占用时拒绝更新。
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

class AdminProfileUpdateOwnerBoundaryClosureModuleTest extends TestCase
{
    use DatabaseTransactions;

    // 验证更新资料只作用于当前管理员并忽略伪造目标与敏感字段。
    public function test_update_profile_uses_current_admin_and_ignores_spoofed_target_and_sensitive_fields(): void
    {
        $current = $this->createManagedAdmin(
            'admin-profile-current',
            'admin-profile-current@example.test',
            'current-secret',
            '13925900001'
        );
        $other = $this->createManagedAdmin(
            'admin-profile-other',
            'admin-profile-other@example.test',
            'other-secret',
            '13925900002'
        );

        $response = $this->withoutMiddleware([AdminAuthenticate::class, JwtAuthMiddleware::class, SingleSignOn::class, CheckPermission::class])
            ->actingAs($current, 'admin')
            ->post('/api/admin/updateProfile', [
                'id' => $other->id,
                'username' => 'admin-profile-spoofed',
                'email' => 'admin-profile-current-new@example.test',
                'mobile' => '13925900999',
                'role_id' => 99,
                'status' => 0,
                'password' => 'spoofed-new-secret',
            ]);

        $response->assertOk()
            ->assertJsonPath('code', ResponseCode::SUCCESS);

        $currentRecord = DB::table('admins')->where('id', $current->id)->first();
        $otherRecord = DB::table('admins')->where('id', $other->id)->first();

        $this->assertSame('admin-profile-current', (string) $currentRecord->username);
        $this->assertSame('admin-profile-current-new@example.test', (string) $currentRecord->email);
        $this->assertSame('13925900999', (string) $currentRecord->mobile);
        $this->assertSame(1, (int) $currentRecord->status);
        $this->assertTrue(Hash::check('current-secret', (string) $currentRecord->password));
        $this->assertFalse(Hash::check('spoofed-new-secret', (string) $currentRecord->password));

        $this->assertSame('admin-profile-other', (string) $otherRecord->username);
        $this->assertSame('admin-profile-other@example.test', (string) $otherRecord->email);
        $this->assertSame('13925900002', (string) $otherRecord->mobile);
        $this->assertTrue(Hash::check('other-secret', (string) $otherRecord->password));
    }

    // 验证邮箱被其他管理员占用时更新被拒绝且双方记录均不变。
    public function test_update_profile_rejects_email_used_by_another_admin(): void
    {
        $current = $this->createManagedAdmin(
            'admin-profile-unique-current',
            'admin-profile-unique-current@example.test',
            'current-secret',
            '13925901001'
        );
        $other = $this->createManagedAdmin(
            'admin-profile-unique-other',
            'admin-profile-unique-other@example.test',
            'other-secret',
            '13925901002'
        );

        $response = $this->withoutMiddleware([AdminAuthenticate::class, JwtAuthMiddleware::class, SingleSignOn::class, CheckPermission::class])
            ->actingAs($current, 'admin')
            ->post('/api/admin/updateProfile', [
                'email' => 'admin-profile-unique-other@example.test',
                'mobile' => '13925901999',
            ]);

        $response->assertOk()
            ->assertJsonPath('code', ResponseCode::VALIDATION_FAILED);

        $currentRecord = DB::table('admins')->where('id', $current->id)->first();
        $otherRecord = DB::table('admins')->where('id', $other->id)->first();

        $this->assertSame('admin-profile-unique-current@example.test', (string) $currentRecord->email);
        $this->assertSame('13925901001', (string) $currentRecord->mobile);
        $this->assertSame('admin-profile-unique-other@example.test', (string) $otherRecord->email);
        $this->assertSame('13925901002', (string) $otherRecord->mobile);
    }

    // 校验最终检查清单文档记录了个人资料更新归属边界。
    public function test_final_checklist_records_admin_profile_update_owner_boundary(): void
    {
        $checklist = file_get_contents(base_path('docs/admin-backend-blade-permission-final-checklist.md')) ?: '';

        $this->assertStringContainsString('## 259.', $checklist);
        $this->assertStringContainsString('AuthController::updateProfile', $checklist);
        $this->assertStringContainsString('/api/admin/updateProfile', $checklist);
        $this->assertStringContainsString('admins.email', $checklist);
        $this->assertStringContainsString('AdminProfileUpdateOwnerBoundaryClosureModuleTest', $checklist);
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
