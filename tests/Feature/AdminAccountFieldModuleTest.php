<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/02
 * Time: 01:44
 */

/**
 * 后台管理员账号字段模块测试。
 *
 * 文件功能：
 * - 验证创建/更新管理员时 mobile、role_id、status 字段正确持久化。
 * - 验证更新管理员时不传密码（或留空）不会改动原密码。
 * - 验证前端表单（layui 页面、pages.js、CrmUi PageController）暴露了 mobile、role_id、status 字段。
 *
 * 适用场景：
 * - 后台管理员账号管理模块创建与编辑入口的字段完整性回归测试。
 *
 * 入参例子：
 * - POST /api/admin/createAdmin
 *   {
 *     "username": "admin-create-fields",
 *     "email": "admin-create-fields@example.test",
 *     "password": "secret123",
 *     "mobile": "13900001111",
 *     "role_id": {roleId},
 *     "status": 0
 *   }
 * - POST /api/admin/updateAdmin/{adminId}（不带 password 字段）
 *
 * 方法功能：
 * - test_admin_create_endpoint_persists_mobile_role_and_status：创建管理员并断言 mobile、role_id、status 落库。
 * - test_admin_update_endpoint_persists_mobile_role_and_status_without_password_change：更新管理员且不传密码，断言字段更新而密码不变。
 * - test_admin_frontend_forms_expose_mobile_role_and_status_fields：检查 blade、layui、CrmUi 配置包含上述表单字段。
 *
 * 返回值：
 * - 创建成功返回 code=CREATED，更新成功返回 code=UPDATED；断言失败时抛出 PHPUnit 断言异常。
 *
 * 异常或失败场景：
 * - 若后端漏存字段或更新时误改密码，测试断言失败。
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

class AdminAccountFieldModuleTest extends TestCase
{
    use DatabaseTransactions;

    /**
     * 创建管理员：断言 mobile、role_id、status 字段正确落库。
     *
     * @return void
     */
    public function test_admin_create_endpoint_persists_mobile_role_and_status(): void
    {
        $actor = $this->ensureSuperAdmin();
        $roleId = $this->createAdminRole('admin-create-field-role');
        $email = 'admin-create-fields@example.test';

        DB::table('admins')->where('email', $email)->delete();

        $response = $this->withoutMiddleware([AdminAuthenticate::class, JwtAuthMiddleware::class, SingleSignOn::class, CheckPermission::class])
            ->actingAs($actor, 'admin')
            ->post('/api/admin/createAdmin', [
                'username' => 'admin-create-fields',
                'email' => $email,
                'password' => 'secret123',
                'mobile' => '13900001111',
                'role_id' => $roleId,
                'status' => 0,
            ]);

        $response->assertJsonPath('code', ResponseCode::CREATED);

        $record = DB::table('admins')->where('email', $email)->first();
        $this->assertNotNull($record);
        $this->assertSame('13900001111', (string) $record->mobile);
        $this->assertSame((string) $roleId, (string) $record->role_id);
        $this->assertSame(0, (int) $record->status);
    }

    /**
     * 更新管理员（不传密码）：断言 mobile、role_id、status 更新而 password 保持不变。
     *
     * @return void
     */
    public function test_admin_update_endpoint_persists_mobile_role_and_status_without_password_change(): void
    {
        $actor = $this->ensureSuperAdmin();
        $oldRoleId = $this->createAdminRole('admin-update-old-role');
        $newRoleId = $this->createAdminRole('admin-update-new-role');
        $adminId = $this->createManagedAdmin($oldRoleId);
        $updatedUsername = 'admin-updated-fields-' . $adminId;
        $updatedEmail = 'admin-updated-fields-' . $adminId . '@example.test';
        $oldPassword = DB::table('admins')->where('id', $adminId)->value('password');

        DB::table('admins')->where('username', $updatedUsername)->where('id', '<>', $adminId)->delete();
        DB::table('admins')->where('email', $updatedEmail)->where('id', '<>', $adminId)->delete();

        $response = $this->withoutMiddleware([AdminAuthenticate::class, JwtAuthMiddleware::class, SingleSignOn::class, CheckPermission::class])
            ->actingAs($actor, 'admin')
            ->post('/api/admin/updateAdmin/' . $adminId, [
                'username' => $updatedUsername,
                'email' => $updatedEmail,
                'mobile' => '13900002222',
                'role_id' => $newRoleId,
                'status' => 0,
            ]);

        $response->assertJsonPath('code', ResponseCode::UPDATED);

        $record = DB::table('admins')->where('id', $adminId)->first();
        $this->assertSame($updatedUsername, (string) $record->username);
        $this->assertSame($updatedEmail, (string) $record->email);
        $this->assertSame('13900002222', (string) $record->mobile);
        $this->assertSame((string) $newRoleId, (string) $record->role_id);
        $this->assertSame(0, (int) $record->status);
        $this->assertSame($oldPassword, $record->password);
    }

    /**
     * 检查前端表单（blade、pages.js、CrmUi PageController）暴露 mobile、role_id、status 字段。
     *
     * @return void
     */
    public function test_admin_frontend_forms_expose_mobile_role_and_status_fields(): void
    {
        $blade = file_get_contents(resource_path('admin/layui/admins/index.blade.php')) ?: '';
        $layui = file_get_contents(public_path('js/apps/admin/layui/pages.js')) ?: '';
        $crmui = file_get_contents(app_path('Http/Controllers/CrmUi/Admin/PageController.php')) ?: '';

        foreach (['name="mobile"', 'name="role_id"', 'name="status"'] as $expected) {
            $this->assertStringContainsString($expected, $blade);
        }

        foreach (['mobile: row.mobile ||', 'role_id: row.role_id ||', 'status: row.status'] as $expected) {
            $this->assertStringContainsString($expected, $layui);
        }

        $this->assertStringContainsString("'formFields' => ['username', ['name' => 'email', 'type' => 'email'], 'password', 'mobile', ['name' => 'role_id', 'type' => 'number']", $crmui);
    }

    private function ensureSuperAdmin(): Admin
    {
        $now = time();

        DB::table('admins')->updateOrInsert(
            ['id' => 1],
            [
                'username' => 'admin-field-super',
                'email' => 'admin-field-super@example.test',
                'password' => bcrypt('password'),
                'status' => 1,
                'created_at' => $now,
                'updated_at' => $now,
                'deleted_at' => null,
            ]
        );

        return Admin::query()->findOrFail(1);
    }

    private function createAdminRole(string $name): int
    {
        $now = time();

        DB::table('roles')->where('name', $name)->delete();

        return (int) DB::table('roles')->insertGetId([
            'name' => $name,
            'guard_type' => 'admin',
            'description' => 'Admin account field module test role',
            'permissions' => json_encode([]),
            'status' => 1,
            'created_at' => $now,
            'updated_at' => $now,
            'deleted_at' => null,
        ]);
    }

    private function createManagedAdmin(int $roleId): int
    {
        $now = time();
        $email = 'admin-managed-fields@example.test';

        DB::table('admins')->where('email', $email)->delete();

        return (int) DB::table('admins')->insertGetId([
            'username' => 'admin-managed-fields',
            'email' => $email,
            'password' => Hash::make('old-secret'),
            'mobile' => '13900000000',
            'role_id' => $roleId,
            'status' => 1,
            'created_at' => $now,
            'updated_at' => $now,
            'deleted_at' => null,
        ]);
    }
}
