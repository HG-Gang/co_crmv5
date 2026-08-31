<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/02
 * Time: 01:44
 */

/**
 * 后台管理员唯一标识闭包测试。
 *
 * 文件功能：
 * - 验证创建/更新管理员时 username、email 与其它管理员重复会被校验拦截，且不写入/不修改账号。
 * - 验证 docs/admin-backend-blade-permission-final-checklist.md 最终清单记录了本边界（第 263 项）。
 *
 * 适用场景：
 * - 后台管理员账号管理模块创建与更新入口的唯一性校验回归测试。
 *
 * 入参例子：
 * - POST /api/admin/createAdmin
 *   {
 *     "username": "{existing->username}",
 *     "email": "admin-account-unique-new@example.test",
 *     "password": "new-secret"
 *   }
 *
 * 方法功能：
 * - test_create_admin_rejects_existing_username_or_email：创建时用户名或邮箱已存在，断言校验失败且未写入新账号。
 * - test_update_admin_rejects_username_or_email_used_by_another_admin：更新时用户名或邮箱被其它管理员占用，断言校验失败且目标账号不变。
 * - test_final_checklist_records_admin_account_unique_identity_boundary：校验最终清单文档包含第 263 项边界记录。
 *
 * 返回值：
 * - 校验失败时接口返回 code=VALIDATION_FAILED；断言失败时抛出 PHPUnit 断言异常。
 *
 * 异常或失败场景：
 * - 若重复 username/email 被接受并写入账号，测试断言失败。
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

class AdminAccountUniqueIdentityClosureModuleTest extends TestCase
{
    use DatabaseTransactions;

    /**
     * 创建管理员时用户名或邮箱已存在：断言校验失败且未写入新账号。
     *
     * @return void
     */
    public function test_create_admin_rejects_existing_username_or_email(): void
    {
        $this->cleanupManagedAdmins();

        $actor = $this->createManagedAdmin(
            'admin-account-unique-actor',
            'admin-account-unique-actor@example.test',
            'actor-secret'
        );
        $existing = $this->createManagedAdmin(
            'admin-account-unique-existing',
            'admin-account-unique-existing@example.test',
            'existing-secret'
        );

        $duplicateUsernameResponse = $this->withoutMiddleware([AdminAuthenticate::class, JwtAuthMiddleware::class, SingleSignOn::class, CheckPermission::class])
            ->actingAs($actor, 'admin')
            ->post('/api/admin/createAdmin', [
                'username' => $existing->username,
                'email' => 'admin-account-unique-new@example.test',
                'password' => 'new-secret',
            ]);

        $duplicateUsernameResponse->assertOk()
            ->assertJsonPath('code', ResponseCode::VALIDATION_FAILED);
        $this->assertFalse(DB::table('admins')->where('email', 'admin-account-unique-new@example.test')->exists());

        $duplicateEmailResponse = $this->withoutMiddleware([AdminAuthenticate::class, JwtAuthMiddleware::class, SingleSignOn::class, CheckPermission::class])
            ->actingAs($actor, 'admin')
            ->post('/api/admin/createAdmin', [
                'username' => 'admin-account-unique-new',
                'email' => $existing->email,
                'password' => 'new-secret',
            ]);

        $duplicateEmailResponse->assertOk()
            ->assertJsonPath('code', ResponseCode::VALIDATION_FAILED);
        $this->assertFalse(DB::table('admins')->where('username', 'admin-account-unique-new')->exists());
    }

    /**
     * 更新管理员时用户名或邮箱被其它管理员占用：断言校验失败且目标账号不变。
     *
     * @return void
     */
    public function test_update_admin_rejects_username_or_email_used_by_another_admin(): void
    {
        $this->cleanupManagedAdmins();

        $actor = $this->createManagedAdmin(
            'admin-account-unique-update-actor',
            'admin-account-unique-update-actor@example.test',
            'actor-secret'
        );
        $existing = $this->createManagedAdmin(
            'admin-account-unique-update-existing',
            'admin-account-unique-update-existing@example.test',
            'existing-secret'
        );
        $target = $this->createManagedAdmin(
            'admin-account-unique-update-target',
            'admin-account-unique-update-target@example.test',
            'target-secret'
        );

        $duplicateUsernameResponse = $this->withoutMiddleware([AdminAuthenticate::class, JwtAuthMiddleware::class, SingleSignOn::class, CheckPermission::class])
            ->actingAs($actor, 'admin')
            ->post('/api/admin/updateAdmin/' . $target->id, [
                'username' => $existing->username,
                'email' => 'admin-account-unique-update-target-new@example.test',
                'mobile' => '13926300999',
            ]);

        $duplicateUsernameResponse->assertOk()
            ->assertJsonPath('code', ResponseCode::VALIDATION_FAILED);

        $duplicateEmailResponse = $this->withoutMiddleware([AdminAuthenticate::class, JwtAuthMiddleware::class, SingleSignOn::class, CheckPermission::class])
            ->actingAs($actor, 'admin')
            ->post('/api/admin/updateAdmin/' . $target->id, [
                'username' => 'admin-account-unique-update-target-new',
                'email' => $existing->email,
                'mobile' => '13926300999',
            ]);

        $duplicateEmailResponse->assertOk()
            ->assertJsonPath('code', ResponseCode::VALIDATION_FAILED);

        $targetRecord = DB::table('admins')->where('id', $target->id)->first();

        $this->assertSame('admin-account-unique-update-target', (string) $targetRecord->username);
        $this->assertSame('admin-account-unique-update-target@example.test', (string) $targetRecord->email);
        $this->assertNotSame('13926300999', (string) $targetRecord->mobile);
        $this->assertTrue(Hash::check('target-secret', (string) $targetRecord->password));
    }

    /**
     * 校验最终清单文档第 263 项记录了唯一标识边界。
     *
     * @return void
     */
    public function test_final_checklist_records_admin_account_unique_identity_boundary(): void
    {
        $checklist = file_get_contents(base_path('docs/admin-backend-blade-permission-final-checklist.md')) ?: '';

        $this->assertStringContainsString('## 263.', $checklist);
        $this->assertStringContainsString('AdminController::store', $checklist);
        $this->assertStringContainsString('AdminController::update', $checklist);
        $this->assertStringContainsString('admins.username', $checklist);
        $this->assertStringContainsString('admins.email', $checklist);
        $this->assertStringContainsString('AdminAccountUniqueIdentityClosureModuleTest', $checklist);
    }

    private function createManagedAdmin(string $username, string $email, string $password): Admin
    {
        $now = time();

        $id = DB::table('admins')->insertGetId([
            'username' => $username,
            'email' => $email,
            'password' => Hash::make($password),
            'mobile' => '',
            'status' => 1,
            'created_at' => $now,
            'updated_at' => $now,
            'deleted_at' => null,
        ]);

        return Admin::query()->findOrFail($id);
    }

    private function cleanupManagedAdmins(): void
    {
        DB::table('admins')
            ->where('username', 'like', 'admin-account-unique-%')
            ->orWhere('email', 'like', 'admin-account-unique-%@example.test')
            ->delete();
    }
}
