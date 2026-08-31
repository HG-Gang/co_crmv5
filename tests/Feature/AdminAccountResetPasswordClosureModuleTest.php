<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/02
 * Time: 01:44
 */

/**
 * 后台管理员重置密码闭包测试。
 *
 * 文件功能：
 * - 验证命名路由 admin_api_resetAdminPassword（POST /api/admin/resetAdminPassword/{id}）及其权限、前端按钮、语言包均已接线。
 * - 验证重置密码时以路由 id 为准，忽略请求体伪造的 id 字段。
 * - 验证 docs/admin-backend-blade-permission-final-checklist.md 最终清单记录了本闭包（第 254 项）。
 *
 * 适用场景：
 * - 后台管理员账号管理模块重置密码入口的回归测试，防止表单伪造 id 重置其它管理员密码。
 *
 * 入参例子：
 * - POST /api/admin/resetAdminPassword/{targetId}
 *   {
 *     "id": {otherId},
 *     "password": "target-reset-secret"
 *   }
 *
 * 方法功能：
 * - test_admin_reset_password_route_permission_and_frontend_actions_are_wired：校验路由、权限、blade/layui/CrmUi 与多语言配置。
 * - test_admin_reset_password_uses_route_id_instead_of_spoofed_form_id：伪造表单 id 重置密码，断言仅路由目标密码被修改。
 * - test_final_checklist_records_admin_reset_password_closure：校验最终清单文档包含第 254 项闭包记录。
 *
 * 返回值：
 * - 重置成功返回 code=SUCCESS；断言失败时抛出 PHPUnit 断言异常。
 *
 * 异常或失败场景：
 * - 若控制器使用表单 id 重置密码，会误改其它管理员密码导致断言失败。
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
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class AdminAccountResetPasswordClosureModuleTest extends TestCase
{
    use DatabaseTransactions;

    /**
     * 校验重置密码命名路由、权限种子、前端按钮与多语言包均已接线。
     *
     * @return void
     */
    public function test_admin_reset_password_route_permission_and_frontend_actions_are_wired(): void
    {
        $route = Route::getRoutes()->getByName('admin_api_resetAdminPassword');

        $this->assertNotNull($route, 'admin_api_resetAdminPassword 命名路由必须存在。');
        $this->assertSame('api/admin/resetAdminPassword/{id}', $route->uri());

        $migrationPath = database_path('migrations/2026_06_07_000001_add_admin_content_crud_permissions.php');
        require_once $migrationPath;

        DB::table('permissions')->where('slug', 'admin_admin_reset_password')->delete();
        (new \AddAdminContentCrudPermissions())->up();

        $permission = DB::table('permissions')->where('slug', 'admin_admin_reset_password')->first();

        $this->assertNotNull($permission, 'admin_admin_reset_password 权限必须写入 permissions 表。');
        $this->assertSame('admin_api_resetAdminPassword', (string) $permission->api_route);
        $this->assertSame(3, (int) $permission->type);
        $this->assertSame(1, (int) $permission->status);

        $blade = file_get_contents(resource_path('admin/layui/admins/index.blade.php')) ?: '';
        $layui = file_get_contents(public_path('js/apps/admin/layui/pages.js')) ?: '';
        $crmui = file_get_contents(app_path('Http/Controllers/CrmUi/Admin/PageController.php')) ?: '';
        $zhAdminLang = file_get_contents(resource_path('lang/zh-CN/admin.php')) ?: '';
        $enAdminLang = file_get_contents(resource_path('lang/en/admin.php')) ?: '';
        $zhSharedLang = file_get_contents(public_path('js/shared/lang/common/zh-CN.js')) ?: '';
        $enSharedLang = file_get_contents(public_path('js/shared/lang/common/en.js')) ?: '';

        $this->assertStringContainsString('data-permission="admin_admin_reset_password"', $blade);
        $this->assertStringContainsString('lay-event="resetPassword"', $blade);
        $this->assertStringContainsString('/api/admin/resetAdminPassword/', $layui);
        $this->assertStringContainsString('admin_api_resetAdminPassword', $crmui);
        $this->assertStringContainsString("'reset_password' =>", $zhAdminLang);
        $this->assertStringContainsString("'reset_password' =>", $enAdminLang);
        $this->assertStringContainsString("reset_password: '重置密码'", $zhSharedLang);
        $this->assertStringContainsString("reset_password: 'Reset Password'", $enSharedLang);
    }

    /**
     * 伪造表单 id 重置密码：断言仅路由目标管理员密码被重置，其它管理员不受影响。
     *
     * @return void
     */
    public function test_admin_reset_password_uses_route_id_instead_of_spoofed_form_id(): void
    {
        $actor = $this->ensureSuperAdmin();
        $targetId = $this->createManagedAdmin(
            'admin-reset-password-target',
            'admin-reset-password-target@example.test',
            'target-old-secret'
        );
        $otherId = $this->createManagedAdmin(
            'admin-reset-password-other',
            'admin-reset-password-other@example.test',
            'other-old-secret'
        );

        $response = $this->withoutMiddleware([AdminAuthenticate::class, JwtAuthMiddleware::class, SingleSignOn::class, CheckPermission::class])
            ->actingAs($actor, 'admin')
            ->post('/api/admin/resetAdminPassword/' . $targetId, [
                'id' => $otherId,
                'password' => 'target-reset-secret',
            ]);

        $response->assertOk()
            ->assertJsonPath('code', ResponseCode::SUCCESS);

        $targetPassword = (string) DB::table('admins')->where('id', $targetId)->value('password');
        $otherPassword = (string) DB::table('admins')->where('id', $otherId)->value('password');

        $this->assertTrue(Hash::check('target-reset-secret', $targetPassword));
        $this->assertFalse(Hash::check('target-old-secret', $targetPassword));
        $this->assertTrue(Hash::check('other-old-secret', $otherPassword));
        $this->assertFalse(Hash::check('target-reset-secret', $otherPassword));
    }

    /**
     * 校验最终清单文档第 254 项记录了重置密码闭包。
     *
     * @return void
     */
    public function test_final_checklist_records_admin_reset_password_closure(): void
    {
        $checklist = file_get_contents(base_path('docs/admin-backend-blade-permission-final-checklist.md')) ?: '';

        $this->assertStringContainsString('## 254.', $checklist);
        $this->assertStringContainsString('AdminController::resetPassword', $checklist);
        $this->assertStringContainsString('/api/admin/resetAdminPassword/{id}', $checklist);
        $this->assertStringContainsString('admin_admin_reset_password', $checklist);
        $this->assertStringContainsString('AdminAccountResetPasswordClosureModuleTest', $checklist);
    }

    private function ensureSuperAdmin(): Admin
    {
        $now = time();

        DB::table('admins')->updateOrInsert(
            ['id' => 1],
            [
                'username' => 'admin-reset-password-super',
                'email' => 'admin-reset-password-super@example.test',
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
            'mobile' => '13925400000',
            'status' => 1,
            'created_at' => $now,
            'updated_at' => $now,
            'deleted_at' => null,
        ]);
    }
}
