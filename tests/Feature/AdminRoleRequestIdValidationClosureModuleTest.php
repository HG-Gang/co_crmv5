<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/02
 * Time: 01:55
 */

/**
 * 后台角色更新、删除接口请求 id 严格校验的功能测试。
 *
 * 文件功能：
 * - 验证请求体 id 传入非严格数字时更新、删除角色接口均返回校验失败。
 * - 验证校验失败后角色记录不被更新或删除。
 *
 * 适用场景：
 * - 后台角色管理页面的更新、删除操作，防止非法 id 误改角色数据。
 *
 * 入参例子：
 * - POST /api/admin/updateRole，body：{"id": "1abc", ...}。
 * - POST /api/admin/deleteRole，body：{"id": "1abc"}。
 *
 * 返回值：
 * - 校验失败返回 code=ResponseCode::VALIDATION_FAILED。
 *
 * 异常或失败场景：
 * - 请求 id 非严格整数时接口拒绝执行并保持原角色记录不变。
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

class AdminRoleRequestIdValidationClosureModuleTest extends TestCase
{
    use DatabaseTransactions;

    // 验证更新角色时非严格 id 被拒绝且角色记录原字段保持不变。
    public function test_update_role_rejects_non_strict_id_without_updating_role(): void
    {
        $actor = $this->ensureSuperAdmin();
        $roleId = $this->createRole('role-request-update-target', 'Original role description', 1);

        $response = $this->withoutMiddleware([AdminAuthenticate::class, JwtAuthMiddleware::class, SingleSignOn::class, CheckPermission::class])
            ->actingAs($actor, 'admin')
            ->post('/api/admin/updateRole', [
                'id' => $roleId . 'abc',
                'name' => 'role-request-update-changed',
                'guard_type' => 'admin',
                'description' => 'Changed role description',
                'status' => 0,
            ]);

        $response->assertOk()
            ->assertJsonPath('code', ResponseCode::VALIDATION_FAILED);

        $this->assertDatabaseHas('roles', [
            'id' => $roleId,
            'name' => 'role-request-update-target',
            'description' => 'Original role description',
            'status' => 1,
            'deleted_at' => null,
        ]);
    }

    // 验证删除角色时非严格 id 被拒绝且角色记录未被删除。
    public function test_delete_role_rejects_non_strict_id_without_deleting_role(): void
    {
        $actor = $this->ensureSuperAdmin();
        $roleId = $this->createRole('role-request-delete-target', 'Role kept after invalid delete', 1);

        $response = $this->withoutMiddleware([AdminAuthenticate::class, JwtAuthMiddleware::class, SingleSignOn::class, CheckPermission::class])
            ->actingAs($actor, 'admin')
            ->post('/api/admin/deleteRole', [
                'id' => $roleId . 'abc',
            ]);

        $response->assertOk()
            ->assertJsonPath('code', ResponseCode::VALIDATION_FAILED);

        $this->assertDatabaseHas('roles', [
            'id' => $roleId,
            'name' => 'role-request-delete-target',
            'deleted_at' => null,
        ]);
    }

    // 校验最终检查清单文档记录了角色请求 id 校验边界。
    public function test_final_checklist_records_role_request_id_validation_boundary(): void
    {
        $checklist = file_get_contents(base_path('docs/admin-backend-blade-permission-final-checklist.md')) ?: '';

        $this->assertStringContainsString('## 301.', $checklist);
        $this->assertStringContainsString('RoleController::updateRole', $checklist);
        $this->assertStringContainsString('RoleController::deleteRole', $checklist);
        $this->assertStringContainsString('/api/admin/updateRole', $checklist);
        $this->assertStringContainsString('/api/admin/deleteRole', $checklist);
        $this->assertStringContainsString('roles.id', $checklist);
        $this->assertStringContainsString('AdminRoleRequestIdValidationClosureModuleTest', $checklist);
    }

    private function ensureSuperAdmin(): Admin
    {
        $now = time();

        DB::table('admins')->updateOrInsert(
            ['id' => 1],
            [
                'username' => 'admin-role-request-id-super',
                'email' => 'admin-role-request-id-super@example.test',
                'password' => Hash::make('password'),
                'status' => 1,
                'created_at' => $now,
                'updated_at' => $now,
                'deleted_at' => null,
            ]
        );

        return Admin::query()->findOrFail(1);
    }

    private function createRole(string $name, string $description, int $status): int
    {
        $now = time();

        return (int) DB::table('roles')->insertGetId([
            'name' => $name,
            'guard_type' => 'admin',
            'description' => $description,
            'permissions' => json_encode([]),
            'status' => $status,
            'created_at' => $now,
            'updated_at' => $now,
            'deleted_at' => null,
        ]);
    }
}
