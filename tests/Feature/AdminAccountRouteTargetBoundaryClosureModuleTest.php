<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/02
 * Time: 01:44
 */

/**
 * 后台管理员更新接口路由目标边界闭包测试。
 *
 * 文件功能：
 * - 验证 POST /api/admin/updateAdmin/{id} 更新管理员时以路由 id 为准，忽略请求体伪造的 id 字段。
 * - 验证 docs/admin-backend-blade-permission-final-checklist.md 最终清单记录了本边界（第 253 项）。
 *
 * 适用场景：
 * - 后台管理员账号管理模块的更新入口回归测试，防止表单伪造 id 误改其它管理员。
 *
 * 入参例子：
 * - POST /api/admin/updateAdmin/{targetId}
 *   {
 *     "id": {otherId},
 *     "username": "admin-route-target-edited",
 *     "email": "admin-route-target-edited@example.test",
 *     "password": "target-new-secret",
 *     "mobile": "13925300001",
 *     "status": 0
 *   }
 *
 * 方法功能：
 * - test_admin_update_uses_route_id_instead_of_spoofed_form_id：伪造表单 id 更新，断言仅路由目标被修改、其它管理员不变。
 * - test_final_checklist_records_admin_account_route_target_boundary：校验最终清单文档包含第 253 项边界记录。
 *
 * 返回值：
 * - 更新成功返回 code=UPDATED；断言失败时抛出 PHPUnit 断言异常。
 *
 * 异常或失败场景：
 * - 若控制器使用表单 id 更新，会误改其它管理员导致断言失败。
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

class AdminAccountRouteTargetBoundaryClosureModuleTest extends TestCase
{
    use DatabaseTransactions;

    /**
     * 伪造表单 id 更新管理员：断言仅路由目标被修改，其它管理员不受影响。
     *
     * @return void
     */
    public function test_admin_update_uses_route_id_instead_of_spoofed_form_id(): void
    {
        $actor = $this->ensureSuperAdmin();
        $this->deleteManagedAdmin('admin-route-target-edited', 'admin-route-target-edited@example.test');
        $targetId = $this->createManagedAdmin(
            'admin-route-target-edit-target',
            'admin-route-target-edit-target@example.test',
            'target-old-secret'
        );
        $otherId = $this->createManagedAdmin(
            'admin-route-target-edit-other',
            'admin-route-target-edit-other@example.test',
            'other-old-secret'
        );

        $response = $this->withoutMiddleware([AdminAuthenticate::class, JwtAuthMiddleware::class, SingleSignOn::class, CheckPermission::class])
            ->actingAs($actor, 'admin')
            ->post('/api/admin/updateAdmin/' . $targetId, [
                'id' => $otherId,
                'username' => 'admin-route-target-edited',
                'email' => 'admin-route-target-edited@example.test',
                'password' => 'target-new-secret',
                'mobile' => '13925300001',
                'status' => 0,
            ]);

        $response->assertOk()
            ->assertJsonPath('code', ResponseCode::UPDATED);

        $target = DB::table('admins')->where('id', $targetId)->first();
        $other = DB::table('admins')->where('id', $otherId)->first();

        $this->assertSame('admin-route-target-edited', (string) $target->username);
        $this->assertSame('admin-route-target-edited@example.test', (string) $target->email);
        $this->assertSame('13925300001', (string) $target->mobile);
        $this->assertSame(0, (int) $target->status);
        $this->assertTrue(Hash::check('target-new-secret', (string) $target->password));

        $this->assertSame('admin-route-target-edit-other', (string) $other->username);
        $this->assertSame('admin-route-target-edit-other@example.test', (string) $other->email);
        $this->assertSame('13925300000', (string) $other->mobile);
        $this->assertSame(1, (int) $other->status);
        $this->assertTrue(Hash::check('other-old-secret', (string) $other->password));
        $this->assertFalse(Hash::check('target-new-secret', (string) $other->password));
    }

    /**
     * 校验最终清单文档第 253 项记录了更新接口路由目标边界。
     *
     * @return void
     */
    public function test_final_checklist_records_admin_account_route_target_boundary(): void
    {
        $checklist = file_get_contents(base_path('docs/admin-backend-blade-permission-final-checklist.md')) ?: '';

        $this->assertStringContainsString('## 253.', $checklist);
        $this->assertStringContainsString('AdminController::update', $checklist);
        $this->assertStringContainsString('/api/admin/updateAdmin/{id}', $checklist);
        $this->assertStringContainsString('admins.id', $checklist);
        $this->assertStringContainsString('AdminAccountRouteTargetBoundaryClosureModuleTest', $checklist);
    }

    private function ensureSuperAdmin(): Admin
    {
        $now = time();

        DB::table('admins')->updateOrInsert(
            ['id' => 1],
            [
                'username' => 'admin-route-target-super',
                'email' => 'admin-route-target-super@example.test',
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

        $this->deleteManagedAdmin($username, $email);

        return (int) DB::table('admins')->insertGetId([
            'username' => $username,
            'email' => $email,
            'password' => Hash::make($password),
            'mobile' => '13925300000',
            'status' => 1,
            'created_at' => $now,
            'updated_at' => $now,
            'deleted_at' => null,
        ]);
    }

    private function deleteManagedAdmin(string $username, string $email): void
    {
        DB::table('admins')
            ->where('username', $username)
            ->orWhere('email', $email)
            ->delete();
    }
}
