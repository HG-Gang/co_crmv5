<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/02
 * Time: 01:44
 */

/**
 * 后台管理员接口路由 id 严格校验闭包测试。
 *
 * 文件功能：
 * - 验证更新、重置密码、删除管理员接口对非严格整数路由 id（如 {id}abc）返回校验失败，且不修改目标记录。
 * - 验证 docs/admin-backend-blade-permission-final-checklist.md 最终清单记录了本边界（第 284 项）。
 *
 * 适用场景：
 * - 后台管理员账号管理模块各写接口的路由参数严格校验回归测试。
 *
 * 入参例子：
 * - POST /api/admin/updateAdmin/{targetId}abc
 *   {
 *     "username": "admin-route-id-update-new",
 *     "email": "admin-route-id-update-new@example.test",
 *     "password": "target-new-secret",
 *     "mobile": "13928400001",
 *     "status": 0
 *   }
 *
 * 方法功能：
 * - test_update_admin_rejects_non_strict_route_id_without_changing_account：非严格路由 id 更新被拒，目标账号保持原样。
 * - test_reset_admin_password_rejects_non_strict_route_id_without_changing_password：非严格路由 id 重置密码被拒，密码不变。
 * - test_delete_admin_rejects_non_strict_route_id_without_deleting_account：非严格路由 id 删除被拒，记录未被软删除。
 * - test_final_checklist_records_admin_account_route_id_validation_boundary：校验最终清单文档包含第 284 项边界记录。
 *
 * 返回值：
 * - 校验失败时接口返回 code=VALIDATION_FAILED；断言失败时抛出 PHPUnit 断言异常。
 *
 * 异常或失败场景：
 * - 若非严格路由 id 被接受并执行写操作，测试断言失败。
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

class AdminAccountRouteIdValidationClosureModuleTest extends TestCase
{
    use DatabaseTransactions;

    /**
     * 非严格路由 id 更新管理员：断言校验失败且目标账号保持原样。
     *
     * @return void
     */
    public function test_update_admin_rejects_non_strict_route_id_without_changing_account(): void
    {
        $actor = $this->ensureSuperAdmin();
        $targetId = $this->createManagedAdmin(
            'admin-route-id-update-target',
            'admin-route-id-update-target@example.test',
            'target-old-secret'
        );

        $response = $this->withoutMiddleware([AdminAuthenticate::class, JwtAuthMiddleware::class, SingleSignOn::class, CheckPermission::class])
            ->actingAs($actor, 'admin')
            ->post('/api/admin/updateAdmin/' . $targetId . 'abc', [
                'username' => 'admin-route-id-update-new',
                'email' => 'admin-route-id-update-new@example.test',
                'password' => 'target-new-secret',
                'mobile' => '13928400001',
                'status' => 0,
            ]);

        $response->assertOk()
            ->assertJsonPath('code', ResponseCode::VALIDATION_FAILED);

        $target = DB::table('admins')->where('id', $targetId)->first();

        $this->assertSame('admin-route-id-update-target', (string) $target->username);
        $this->assertSame('admin-route-id-update-target@example.test', (string) $target->email);
        $this->assertSame('13928400000', (string) $target->mobile);
        $this->assertSame(1, (int) $target->status);
        $this->assertTrue(Hash::check('target-old-secret', (string) $target->password));
    }

    /**
     * 非严格路由 id 重置密码：断言校验失败且密码不变。
     *
     * @return void
     */
    public function test_reset_admin_password_rejects_non_strict_route_id_without_changing_password(): void
    {
        $actor = $this->ensureSuperAdmin();
        $targetId = $this->createManagedAdmin(
            'admin-route-id-reset-target',
            'admin-route-id-reset-target@example.test',
            'reset-old-secret'
        );

        $response = $this->withoutMiddleware([AdminAuthenticate::class, JwtAuthMiddleware::class, SingleSignOn::class, CheckPermission::class])
            ->actingAs($actor, 'admin')
            ->post('/api/admin/resetAdminPassword/' . $targetId . 'abc', [
                'password' => 'reset-new-secret',
            ]);

        $response->assertOk()
            ->assertJsonPath('code', ResponseCode::VALIDATION_FAILED);

        $password = (string) DB::table('admins')->where('id', $targetId)->value('password');

        $this->assertTrue(Hash::check('reset-old-secret', $password));
        $this->assertFalse(Hash::check('reset-new-secret', $password));
    }

    /**
     * 非严格路由 id 删除管理员：断言校验失败且记录未被软删除。
     *
     * @return void
     */
    public function test_delete_admin_rejects_non_strict_route_id_without_deleting_account(): void
    {
        $actor = $this->ensureSuperAdmin();
        $targetId = $this->createManagedAdmin(
            'admin-route-id-delete-target',
            'admin-route-id-delete-target@example.test',
            'delete-old-secret'
        );

        $response = $this->withoutMiddleware([AdminAuthenticate::class, JwtAuthMiddleware::class, SingleSignOn::class, CheckPermission::class])
            ->actingAs($actor, 'admin')
            ->post('/api/admin/deleteAdmin/' . $targetId . 'abc');

        $response->assertOk()
            ->assertJsonPath('code', ResponseCode::VALIDATION_FAILED);

        $target = DB::table('admins')->where('id', $targetId)->first();

        $this->assertNotNull($target);
        $this->assertNull($target->deleted_at);
        $this->assertSame('admin-route-id-delete-target', (string) $target->username);
    }

    /**
     * 校验最终清单文档第 284 项记录了路由 id 校验边界。
     *
     * @return void
     */
    public function test_final_checklist_records_admin_account_route_id_validation_boundary(): void
    {
        $checklist = file_get_contents(base_path('docs/admin-backend-blade-permission-final-checklist.md')) ?: '';

        $this->assertStringContainsString('## 284.', $checklist);
        $this->assertStringContainsString('AdminController::update', $checklist);
        $this->assertStringContainsString('AdminController::resetPassword', $checklist);
        $this->assertStringContainsString('AdminController::destroy', $checklist);
        $this->assertStringContainsString('/api/admin/updateAdmin/{id}', $checklist);
        $this->assertStringContainsString('/api/admin/resetAdminPassword/{id}', $checklist);
        $this->assertStringContainsString('/api/admin/deleteAdmin/{id}', $checklist);
        $this->assertStringContainsString('admins.id', $checklist);
        $this->assertStringContainsString('AdminAccountRouteIdValidationClosureModuleTest', $checklist);
    }

    private function ensureSuperAdmin(): Admin
    {
        $now = time();

        DB::table('admins')->updateOrInsert(
            ['id' => 1],
            [
                'username' => 'admin-route-id-super',
                'email' => 'admin-route-id-super@example.test',
                'password' => Hash::make('password'),
                'status' => 1,
                'created_at' => $now,
                'updated_at' => $now,
                'deleted_at' => null,
            ]
        );

        return Admin::query()->findOrFail(1);
    }

    private function createManagedAdmin(string $username, string $email, string $password): int
    {
        $now = time();

        DB::table('admins')
            ->where('username', $username)
            ->orWhere('email', $email)
            ->delete();

        return (int) DB::table('admins')->insertGetId([
            'username' => $username,
            'email' => $email,
            'password' => Hash::make($password),
            'mobile' => '13928400000',
            'status' => 1,
            'created_at' => $now,
            'updated_at' => $now,
            'deleted_at' => null,
        ]);
    }
}
