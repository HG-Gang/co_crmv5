<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/02
 * Time: 01:44
 */

/**
 * 后台管理员删除接口路由目标边界闭包测试。
 *
 * 文件功能：
 * - 验证 POST /api/admin/deleteAdmin/{id} 删除管理员时以路由 id 为准，忽略请求体伪造的 id 字段。
 * - 验证 docs/admin-backend-blade-permission-final-checklist.md 最终清单记录了本边界（第 255 项）。
 *
 * 适用场景：
 * - 后台管理员账号管理模块的删除入口回归测试，防止表单伪造 id 误删其它管理员。
 *
 * 入参例子：
 * - POST /api/admin/deleteAdmin/{targetId}
 *   {
 *     "id": {otherId}
 *   }
 *
 * 方法功能：
 * - test_admin_delete_uses_route_id_instead_of_spoofed_form_id：伪造表单 id 删除，断言路由目标被软删除、其它管理员不受影响。
 * - test_final_checklist_records_admin_delete_route_target_boundary：校验最终清单文档包含第 255 项边界记录。
 *
 * 返回值：
 * - 成功时接口返回 code=DELETED，测试通过；断言失败时抛出 PHPUnit 断言异常。
 *
 * 异常或失败场景：
 * - 若控制器使用表单 id 删除，会误删其它管理员导致断言失败。
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

class AdminAccountDeleteRouteTargetBoundaryClosureModuleTest extends TestCase
{
    use DatabaseTransactions;

    /**
     * 删除管理员时伪造表单 id：断言路由目标被软删除，伪造 id 指向的其它管理员不受影响。
     *
     * @return void
     */
    public function test_admin_delete_uses_route_id_instead_of_spoofed_form_id(): void
    {
        $actor = $this->ensureSuperAdmin();
        $targetId = $this->createManagedAdmin(
            'admin-delete-route-target',
            'admin-delete-route-target@example.test'
        );
        $otherId = $this->createManagedAdmin(
            'admin-delete-route-other',
            'admin-delete-route-other@example.test'
        );

        $response = $this->withoutMiddleware([AdminAuthenticate::class, JwtAuthMiddleware::class, SingleSignOn::class, CheckPermission::class])
            ->actingAs($actor, 'admin')
            ->post('/api/admin/deleteAdmin/' . $targetId, [
                'id' => $otherId,
            ]);

        $response->assertOk()
            ->assertJsonPath('code', ResponseCode::DELETED);

        $target = DB::table('admins')->where('id', $targetId)->first();
        $other = DB::table('admins')->where('id', $otherId)->first();

        $this->assertNotNull($target);
        $this->assertNotNull($target->deleted_at, '路由目标管理员必须被软删除。');
        $this->assertNotNull($other);
        $this->assertNull($other->deleted_at, '伪造表单 id 指向的其它管理员不能被删除。');
        $this->assertSame('admin-delete-route-other', (string) $other->username);
        $this->assertSame('admin-delete-route-other@example.test', (string) $other->email);
    }

    /**
     * 校验最终清单文档第 255 项记录了删除接口路由目标边界。
     *
     * @return void
     */
    public function test_final_checklist_records_admin_delete_route_target_boundary(): void
    {
        $checklist = file_get_contents(base_path('docs/admin-backend-blade-permission-final-checklist.md')) ?: '';

        $this->assertStringContainsString('## 255.', $checklist);
        $this->assertStringContainsString('AdminController::destroy', $checklist);
        $this->assertStringContainsString('/api/admin/deleteAdmin/{id}', $checklist);
        $this->assertStringContainsString('admin_admin_delete', $checklist);
        $this->assertStringContainsString('AdminAccountDeleteRouteTargetBoundaryClosureModuleTest', $checklist);
    }

    private function ensureSuperAdmin(): Admin
    {
        $now = time();

        DB::table('admins')->updateOrInsert(
            ['id' => 1],
            [
                'username' => 'admin-delete-route-super',
                'email' => 'admin-delete-route-super@example.test',
                'password' => Hash::make('password'),
                'status' => 1,
                'created_at' => $now,
                'updated_at' => $now,
                'deleted_at' => null,
            ]
        );

        return Admin::query()->findOrFail(1);
    }

    private function createManagedAdmin(string $username, string $email): int
    {
        $now = time();

        DB::table('admins')
            ->where('username', $username)
            ->orWhere('email', $email)
            ->delete();

        return (int) DB::table('admins')->insertGetId([
            'username' => $username,
            'email' => $email,
            'password' => Hash::make('delete-route-secret'),
            'mobile' => '13925500000',
            'status' => 1,
            'created_at' => $now,
            'updated_at' => $now,
            'deleted_at' => null,
        ]);
    }
}
