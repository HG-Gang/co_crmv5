<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/02
 * Time: 01:44
 */

/**
 * 后台管理员角色与状态校验闭包测试。
 *
 * 文件功能：
 * - 验证创建/更新管理员时 role_id 必须存在、status 必须合法，非法值返回校验失败且不写入/不修改账号。
 * - 验证 docs/admin-backend-blade-permission-final-checklist.md 最终清单记录了本边界（第 264 项）。
 *
 * 适用场景：
 * - 后台管理员账号管理模块创建与更新入口的参数校验回归测试。
 *
 * 入参例子：
 * - POST /api/admin/createAdmin
 *   {
 *     "username": "admin-role-status-invalid-role",
 *     "email": "admin-role-status-invalid-role@example.test",
 *     "password": "new-secret",
 *     "role_id": {missingRoleId},
 *     "status": 1
 *   }
 * - status 传 9 时同样应校验失败。
 *
 * 方法功能：
 * - test_create_admin_rejects_invalid_role_or_status_without_writing_account：创建时传不存在的 role_id 或非法 status，断言校验失败且无新记录。
 * - test_update_admin_rejects_invalid_role_or_status_without_changing_account：更新时传非法 role_id 或 status，断言校验失败且目标记录不变。
 * - test_final_checklist_records_admin_account_role_status_validation_boundary：校验最终清单文档包含第 264 项边界记录。
 *
 * 返回值：
 * - 校验失败时接口返回 code=VALIDATION_FAILED；断言失败时抛出 PHPUnit 断言异常。
 *
 * 异常或失败场景：
 * - 若非法 role_id/status 被接受并写入账号，测试断言失败。
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

class AdminAccountRoleStatusValidationClosureModuleTest extends TestCase
{
    use DatabaseTransactions;

    /**
     * 创建管理员时传不存在的 role_id 或非法 status：断言校验失败且未写入新账号。
     *
     * @return void
     */
    public function test_create_admin_rejects_invalid_role_or_status_without_writing_account(): void
    {
        $this->cleanupManagedAdmins();

        $actor = $this->createManagedAdmin(
            'admin-role-status-actor',
            'admin-role-status-actor@example.test',
            'actor-secret'
        );
        $missingRoleId = $this->missingRoleId();

        $invalidRoleResponse = $this->withoutMiddleware([AdminAuthenticate::class, JwtAuthMiddleware::class, SingleSignOn::class, CheckPermission::class])
            ->actingAs($actor, 'admin')
            ->post('/api/admin/createAdmin', [
                'username' => 'admin-role-status-invalid-role',
                'email' => 'admin-role-status-invalid-role@example.test',
                'password' => 'new-secret',
                'role_id' => $missingRoleId,
                'status' => 1,
            ]);

        $invalidRoleResponse->assertOk()
            ->assertJsonPath('code', ResponseCode::VALIDATION_FAILED);
        $this->assertFalse(DB::table('admins')->where('email', 'admin-role-status-invalid-role@example.test')->exists());

        $invalidStatusResponse = $this->withoutMiddleware([AdminAuthenticate::class, JwtAuthMiddleware::class, SingleSignOn::class, CheckPermission::class])
            ->actingAs($actor, 'admin')
            ->post('/api/admin/createAdmin', [
                'username' => 'admin-role-status-invalid-status',
                'email' => 'admin-role-status-invalid-status@example.test',
                'password' => 'new-secret',
                'status' => 9,
            ]);

        $invalidStatusResponse->assertOk()
            ->assertJsonPath('code', ResponseCode::VALIDATION_FAILED);
        $this->assertFalse(DB::table('admins')->where('email', 'admin-role-status-invalid-status@example.test')->exists());
    }

    /**
     * 更新管理员时传非法 role_id 或 status：断言校验失败且目标账号保持不变。
     *
     * @return void
     */
    public function test_update_admin_rejects_invalid_role_or_status_without_changing_account(): void
    {
        $this->cleanupManagedAdmins();

        $actor = $this->createManagedAdmin(
            'admin-role-status-update-actor',
            'admin-role-status-update-actor@example.test',
            'actor-secret'
        );
        $target = $this->createManagedAdmin(
            'admin-role-status-update-target',
            'admin-role-status-update-target@example.test',
            'target-secret'
        );
        $missingRoleId = $this->missingRoleId();

        $invalidRoleResponse = $this->withoutMiddleware([AdminAuthenticate::class, JwtAuthMiddleware::class, SingleSignOn::class, CheckPermission::class])
            ->actingAs($actor, 'admin')
            ->post('/api/admin/updateAdmin/' . $target->id, [
                'username' => 'admin-role-status-update-target-new',
                'email' => 'admin-role-status-update-target-new@example.test',
                'role_id' => $missingRoleId,
                'status' => 1,
            ]);

        $invalidRoleResponse->assertOk()
            ->assertJsonPath('code', ResponseCode::VALIDATION_FAILED);

        $invalidStatusResponse = $this->withoutMiddleware([AdminAuthenticate::class, JwtAuthMiddleware::class, SingleSignOn::class, CheckPermission::class])
            ->actingAs($actor, 'admin')
            ->post('/api/admin/updateAdmin/' . $target->id, [
                'username' => 'admin-role-status-update-target-new',
                'email' => 'admin-role-status-update-target-new@example.test',
                'status' => 9,
            ]);

        $invalidStatusResponse->assertOk()
            ->assertJsonPath('code', ResponseCode::VALIDATION_FAILED);

        $targetRecord = DB::table('admins')->where('id', $target->id)->first();

        $this->assertSame('admin-role-status-update-target', (string) $targetRecord->username);
        $this->assertSame('admin-role-status-update-target@example.test', (string) $targetRecord->email);
        $this->assertSame(1, (int) $targetRecord->status);
        $this->assertNull($targetRecord->role_id);
        $this->assertTrue(Hash::check('target-secret', (string) $targetRecord->password));
    }

    /**
     * 校验最终清单文档第 264 项记录了角色与状态校验边界。
     *
     * @return void
     */
    public function test_final_checklist_records_admin_account_role_status_validation_boundary(): void
    {
        $checklist = file_get_contents(base_path('docs/admin-backend-blade-permission-final-checklist.md')) ?: '';

        $this->assertStringContainsString('## 264.', $checklist);
        $this->assertStringContainsString('AdminController::store', $checklist);
        $this->assertStringContainsString('AdminController::update', $checklist);
        $this->assertStringContainsString('role_id', $checklist);
        $this->assertStringContainsString('status', $checklist);
        $this->assertStringContainsString('AdminAccountRoleStatusValidationClosureModuleTest', $checklist);
    }

    private function createManagedAdmin(string $username, string $email, string $password): Admin
    {
        $now = time();

        $id = DB::table('admins')->insertGetId([
            'username' => $username,
            'email' => $email,
            'password' => Hash::make($password),
            'mobile' => '',
            'role_id' => null,
            'status' => 1,
            'created_at' => $now,
            'updated_at' => $now,
            'deleted_at' => null,
        ]);

        return Admin::query()->findOrFail($id);
    }

    private function missingRoleId(): int
    {
        return ((int) DB::table('roles')->max('id')) + 10000;
    }

    private function cleanupManagedAdmins(): void
    {
        DB::table('admins')
            ->where('username', 'like', 'admin-role-status-%')
            ->orWhere('email', 'like', 'admin-role-status-%@example.test')
            ->delete();
    }
}
