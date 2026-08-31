<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/02
 * Time: 01:44
 */

/**
 * 后台管理员更新留空密码闭包测试。
 *
 * 文件功能：
 * - 验证 POST /api/admin/updateAdmin/{id} 提交空字符串 password 时保留原密码哈希，不覆盖为空白密码。
 * - 验证响应 data 不包含 password 字段。
 * - 验证 docs/admin-backend-blade-permission-final-checklist.md 最终清单记录了本边界（第 262 项）。
 *
 * 适用场景：
 * - 后台管理员账号管理模块更新入口的密码留空处理回归测试。
 *
 * 入参例子：
 * - POST /api/admin/updateAdmin/{targetId}
 *   {
 *     "username": "admin-blank-password-target-updated-{id}",
 *     "email": "admin-blank-password-target-updated-{id}@example.test",
 *     "mobile": "13926200999",
 *     "password": ""
 *   }
 *
 * 方法功能：
 * - test_update_admin_keeps_existing_password_when_blank_password_is_submitted：提交空密码更新，断言其余字段更新而密码哈希不变。
 * - test_final_checklist_records_admin_account_blank_password_update_boundary：校验最终清单文档包含第 262 项边界记录。
 *
 * 返回值：
 * - 更新成功返回 code=UPDATED；断言失败时抛出 PHPUnit 断言异常。
 *
 * 异常或失败场景：
 * - 若空密码被当作新密码写入，原密码哈希被覆盖导致断言失败。
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

class AdminAccountUpdateBlankPasswordClosureModuleTest extends TestCase
{
    use DatabaseTransactions;

    /**
     * 提交空密码更新管理员：断言其余字段更新而原密码哈希保持不变，响应不含 password。
     *
     * @return void
     */
    public function test_update_admin_keeps_existing_password_when_blank_password_is_submitted(): void
    {
        $this->cleanupManagedAdmins();

        $actor = $this->createManagedAdmin(
            'admin-blank-password-actor',
            'admin-blank-password-actor@example.test',
            'actor-secret',
            '13926200001'
        );
        $target = $this->createManagedAdmin(
            'admin-blank-password-target',
            'admin-blank-password-target@example.test',
            'old-target-secret',
            '13926200002'
        );
        $oldHash = (string) DB::table('admins')->where('id', $target->id)->value('password');
        $updatedUsername = 'admin-blank-password-target-updated-' . $target->id;
        $updatedEmail = 'admin-blank-password-target-updated-' . $target->id . '@example.test';

        $response = $this->withoutMiddleware([AdminAuthenticate::class, JwtAuthMiddleware::class, SingleSignOn::class, CheckPermission::class])
            ->actingAs($actor, 'admin')
            ->post('/api/admin/updateAdmin/' . $target->id, [
                'username' => $updatedUsername,
                'email' => $updatedEmail,
                'mobile' => '13926200999',
                'password' => '',
            ]);

        $response->assertOk()
            ->assertJsonPath('code', ResponseCode::UPDATED)
            ->assertJsonPath('data.username', $updatedUsername)
            ->assertJsonPath('data.email', $updatedEmail);

        $payload = $response->json();
        $this->assertArrayNotHasKey('password', $payload['data'] ?? []);

        $record = DB::table('admins')->where('id', $target->id)->first();

        $this->assertSame($updatedUsername, (string) $record->username);
        $this->assertSame($updatedEmail, (string) $record->email);
        $this->assertSame('13926200999', (string) $record->mobile);
        $this->assertSame($oldHash, (string) $record->password);
        $this->assertTrue(Hash::check('old-target-secret', (string) $record->password));
        $this->assertFalse(Hash::check('', (string) $record->password));
    }

    /**
     * 校验最终清单文档第 262 项记录了更新留空密码边界。
     *
     * @return void
     */
    public function test_final_checklist_records_admin_account_blank_password_update_boundary(): void
    {
        $checklist = file_get_contents(base_path('docs/admin-backend-blade-permission-final-checklist.md')) ?: '';

        $this->assertStringContainsString('## 262.', $checklist);
        $this->assertStringContainsString('AdminController::update', $checklist);
        $this->assertStringContainsString('/api/admin/updateAdmin/{id}', $checklist);
        $this->assertStringContainsString('password 留空', $checklist);
        $this->assertStringContainsString('AdminAccountUpdateBlankPasswordClosureModuleTest', $checklist);
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

    private function cleanupManagedAdmins(): void
    {
        DB::table('admins')
            ->where('username', 'like', 'admin-blank-password-%')
            ->orWhere('email', 'like', 'admin-blank-password-%@example.test')
            ->delete();
    }
}
