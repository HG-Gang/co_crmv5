<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/02
 * Time: 01:55
 */

/**
 * 后台角色分配权限接口 role_id 与 permission_id 严格校验的功能测试。
 *
 * 文件功能：
 * - 验证 role_id 或 permissions 数组内的权限 id 传入非严格数字时接口返回校验失败。
 * - 验证校验失败后 role_permissions 关联数据不被同步写入。
 *
 * 适用场景：
 * - 后台角色管理页面分配权限，防止非法 id 造成权限误配。
 *
 * 入参例子：
 * - POST /api/admin/assignPermissions，body：{"role_id": "1abc", "permissions": [1]}。
 *
 * 返回值：
 * - 校验失败返回 code=ResponseCode::VALIDATION_FAILED。
 *
 * 异常或失败场景：
 * - role_id 或任一 permission_id 非严格整数时接口拒绝执行且不写入角色权限关联。
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

class AdminRoleAssignPermissionIdValidationClosureModuleTest extends TestCase
{
    use DatabaseTransactions;

    protected function tearDown(): void
    {
        $roleIds = DB::table('roles')
            ->where('name', 'like', 'role-assign-invalid-%')
            ->pluck('id');

        if ($roleIds->isNotEmpty()) {
            DB::table('role_permissions')->whereIn('role_id', $roleIds)->delete();
            DB::table('roles')->whereIn('id', $roleIds)->delete();
        }

        parent::tearDown();
    }

    // 验证非严格 role_id 被拒绝且角色权限关联不落库。
    public function test_assign_permissions_rejects_non_strict_role_id_without_syncing_permissions(): void
    {
        $actor = $this->ensureSuperAdmin();
        $roleId = $this->createRole('role-assign-invalid-role-id');
        $permissionId = $this->existingAdminPermissionId();

        $response = $this->withoutMiddleware([AdminAuthenticate::class, JwtAuthMiddleware::class, SingleSignOn::class, CheckPermission::class])
            ->actingAs($actor, 'admin')
            ->post('/api/admin/assignPermissions', [
                'role_id' => $roleId . 'abc',
                'permissions' => [$permissionId],
            ]);

        $response->assertOk()
            ->assertJsonPath('code', ResponseCode::VALIDATION_FAILED);

        $this->assertDatabaseMissing('role_permissions', [
            'role_id' => $roleId,
            'permission_id' => $permissionId,
        ]);
    }

    // 验证非严格 permission_id 被拒绝且角色权限关联不落库。
    public function test_assign_permissions_rejects_non_strict_permission_ids_without_syncing_permissions(): void
    {
        $actor = $this->ensureSuperAdmin();
        $roleId = $this->createRole('role-assign-invalid-permission-id');
        $permissionId = $this->existingAdminPermissionId();

        $response = $this->withoutMiddleware([AdminAuthenticate::class, JwtAuthMiddleware::class, SingleSignOn::class, CheckPermission::class])
            ->actingAs($actor, 'admin')
            ->post('/api/admin/assignPermissions', [
                'role_id' => $roleId,
                'permissions' => [$permissionId . 'abc'],
            ]);

        $response->assertOk()
            ->assertJsonPath('code', ResponseCode::VALIDATION_FAILED);

        $this->assertDatabaseMissing('role_permissions', [
            'role_id' => $roleId,
            'permission_id' => $permissionId,
        ]);
    }

    // 校验最终检查清单文档记录了角色权限分配 id 校验边界。
    public function test_final_checklist_records_role_permission_assignment_id_validation_boundary(): void
    {
        $checklist = file_get_contents(base_path('docs/admin-backend-blade-permission-final-checklist.md')) ?: '';

        $this->assertStringContainsString('## 302.', $checklist);
        $this->assertStringContainsString('RoleController::assignPermissions', $checklist);
        $this->assertStringContainsString('/api/admin/assignPermissions', $checklist);
        $this->assertStringContainsString('role_permissions.role_id', $checklist);
        $this->assertStringContainsString('role_permissions.permission_id', $checklist);
        $this->assertStringContainsString('permissions.id', $checklist);
        $this->assertStringContainsString('AdminRoleAssignPermissionIdValidationClosureModuleTest', $checklist);
    }

    private function ensureSuperAdmin(): Admin
    {
        $now = time();

        DB::table('admins')->updateOrInsert(
            ['id' => 1],
            [
                'username' => 'admin-role-assign-id-super',
                'email' => 'admin-role-assign-id-super@example.test',
                'password' => Hash::make('password'),
                'status' => 1,
                'created_at' => $now,
                'updated_at' => $now,
                'deleted_at' => null,
            ]
        );

        return Admin::query()->findOrFail(1);
    }

    private function createRole(string $name): int
    {
        $now = time();

        return (int) DB::table('roles')->insertGetId([
            'name' => $name,
            'guard_type' => 'admin',
            'description' => 'Role assignment id validation role',
            'permissions' => json_encode([]),
            'status' => 1,
            'created_at' => $now,
            'updated_at' => $now,
            'deleted_at' => null,
        ]);
    }

    private function existingAdminPermissionId(): int
    {
        $permissionId = DB::table('permissions')
            ->where('guard_type', 'admin')
            ->whereNull('deleted_at')
            ->orderBy('id')
            ->value('id');

        $this->assertNotNull($permissionId, '测试数据库必须存在至少一条后台权限记录。');

        return (int) $permissionId;
    }
}
