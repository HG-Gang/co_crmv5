<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/10
 * Time: 21:42
 */

/**
 * AdminLegacyIdentityRoleClosureModuleTest
 *
 * 文件功能：
 * - 验证旧后台身份与角色闭环：密码修改仅作用于当前管理员、旧字段优先级、角色增删改写旧字段、被引用/软删引用角色删除被拒、权限指派锁行替换与回滚。
 * - 输入：HTTP 请求与事务回滚的数据库夹具；输出：PHPUnit 断言结果。
 * - 明确不负责：不覆盖真实 MT4 网关与线上支付/出金通道（由网关契约测试锁定）。
 */

namespace Tests\Feature;

use App\Constants\ResponseCode;
use App\Http\Middleware\AdminAuthenticate;
use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\JwtAuthMiddleware;
use App\Http\Middleware\LegacyAdminAuthenticate;
use App\Http\Middleware\SingleSignOn;
use App\Models\Admin;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use RuntimeException;
use Tests\TestCase;

class AdminLegacyIdentityRoleClosureModuleTest extends TestCase
{
    use DatabaseTransactions;

    public function test_legacy_password_fields_change_only_the_current_admin_password(): void
    {
        $current = $this->createAdmin('legacy-profile-current', 'current-password');
        $other = $this->createAdmin('legacy-profile-other', 'other-password');

        $response = $this->legacyRequest($current)->postJson('/index/admin/userpwd/save', [
            'id' => $other->id,
            'admin_id' => $other->id,
            'pwd' => 'current-password',
            'npwd' => 'current-new-password',
        ]);

        $response->assertOk()
            ->assertJsonPath('code', ResponseCode::SUCCESS)
            ->assertJsonPath('state', 1)
            ->assertJsonPath('msg', '成功');
        $this->assertTrue(Hash::check(
            'current-new-password',
            (string) DB::table('admins')->where('id', $current->id)->value('password')
        ));
        $this->assertTrue(Hash::check(
            'other-password',
            (string) DB::table('admins')->where('id', $other->id)->value('password')
        ));
    }

    public function test_legacy_password_success_preserves_the_modern_data_object(): void
    {
        $admin = $this->createAdmin('legacy-password-object-success', 'current-password');

        $response = $this->legacyRequest($admin)->postJson('/index/admin/userpwd/save', [
            'pwd' => 'current-password',
            'npwd' => 'current-new-password',
        ]);

        $response->assertOk()->assertJsonPath('code', ResponseCode::SUCCESS);
        $body = json_decode($response->getContent());

        $this->assertIsObject($body);
        $this->assertIsObject($body->data);
    }

    public function test_legacy_password_failure_preserves_the_modern_data_object(): void
    {
        $admin = $this->createAdmin('legacy-password-object-failure', 'current-password');

        $response = $this->legacyRequest($admin)->postJson('/index/admin/userpwd/save', [
            'pwd' => 'wrong-password',
            'npwd' => 'current-new-password',
        ]);

        $response->assertOk()->assertJsonPath('code', ResponseCode::OLD_PASSWORD_WRONG);
        $body = json_decode($response->getContent());

        $this->assertIsObject($body);
        $this->assertIsObject($body->data);
    }

    public function test_modern_password_fields_take_priority_over_legacy_aliases(): void
    {
        $admin = $this->createAdmin('legacy-password-modern-priority', 'current-password');

        $this->legacyRequest($admin)->postJson('/index/admin/userpwd/save', [
            'pwd' => 'legacy-wrong-password',
            'npwd' => 'legacy-new-password',
            'old_password' => 'current-password',
            'password' => 'modern-new-password',
            'password_confirmation' => 'explicit-mismatch',
        ])->assertOk()
            ->assertJsonPath('code', ResponseCode::VALIDATION_FAILED)
            ->assertJsonPath('state', 0);

        $this->assertTrue(Hash::check(
            'current-password',
            (string) DB::table('admins')->where('id', $admin->id)->value('password')
        ));

        $this->legacyRequest($admin->fresh())->postJson('/index/admin/userpwd/save', [
            'pwd' => 'legacy-wrong-password',
            'npwd' => 'legacy-new-password',
            'old_password' => 'current-password',
            'password' => 'modern-new-password',
            'password_confirmation' => 'modern-new-password',
        ])->assertOk()->assertJsonPath('code', ResponseCode::SUCCESS);

        $passwordHash = (string) DB::table('admins')->where('id', $admin->id)->value('password');
        $this->assertTrue(Hash::check('modern-new-password', $passwordHash));
        $this->assertFalse(Hash::check('legacy-new-password', $passwordHash));
    }

    public function test_legacy_role_writes_accept_the_old_name_description_and_id_fields(): void
    {
        $actor = $this->createAdmin('legacy-role-actor', 'password');
        $name = 'legacy-role-' . uniqid();

        $create = $this->legacyRequest($actor)->postJson('/index/admin/role/addsave', [
            'username' => $name,
            'desc' => 'Legacy role description',
        ]);

        $create->assertOk()
            ->assertJsonPath('code', ResponseCode::CREATED)
            ->assertJsonPath('state', '1')
            ->assertJsonPath('msg', '添加成功');
        $roleId = (int) DB::table('roles')->where('name', $name)->value('id');
        $this->assertGreaterThan(0, $roleId);
        $this->assertDatabaseHas('roles', [
            'id' => $roleId,
            'guard_type' => 'admin',
            'description' => 'Legacy role description',
            'status' => 1,
        ]);

        $updatedName = $name . '-updated';
        $update = $this->legacyRequest($actor)->postJson('/index/admin/role/editsave', [
            'role_id' => $roleId,
            'username' => $updatedName,
            'desc' => 'Updated legacy role description',
        ]);

        $update->assertOk()
            ->assertJsonPath('code', ResponseCode::UPDATED)
            ->assertJsonPath('state', '1')
            ->assertJsonPath('msg', '修改成功');
        $this->assertDatabaseHas('roles', [
            'id' => $roleId,
            'name' => $updatedName,
            'guard_type' => 'admin',
            'description' => 'Updated legacy role description',
            'status' => 1,
        ]);
    }

    public function test_legacy_profile_save_updates_only_the_current_admin_with_legacy_response(): void
    {
        $current = $this->createAdmin('legacy-profile-save-current', 'current-password');
        $other = $this->createAdmin('legacy-profile-save-other', 'other-password');
        $otherEmail = (string) $other->email;

        $response = $this->legacyRequest($current)->postJson('/index/admin/userinfo/save', [
            'id' => $other->id,
            'admin_id' => $other->id,
            'email' => 'legacy-profile-save-current-new@example.test',
            'mobile' => '13900006666',
            'role_id' => 999,
            'status' => 0,
        ]);

        $response->assertOk()
            ->assertJsonPath('code', ResponseCode::SUCCESS)
            ->assertJsonPath('state', 1)
            ->assertJsonPath('msg', '成功');
        $this->assertDatabaseHas('admins', [
            'id' => $current->id,
            'email' => 'legacy-profile-save-current-new@example.test',
            'mobile' => '13900006666',
            'status' => 1,
        ]);
        $this->assertDatabaseHas('admins', [
            'id' => $other->id,
            'email' => $otherEmail,
            'status' => 1,
        ]);
    }

    public function test_role_delete_rejects_a_role_referenced_by_an_admin(): void
    {
        $actor = $this->createAdmin('role-delete-actor', 'password');
        $roleId = $this->createRole('role-delete-referenced-' . uniqid());
        $assigned = $this->createAdmin('role-delete-assigned', 'password', $roleId);

        $response = $this->apiRequest($actor)->postJson('/api/admin/deleteRole', [
            'id' => $roleId,
        ]);

        $response->assertOk()->assertJsonPath('code', ResponseCode::OPERATION_NOT_ALLOWED);
        $this->assertDatabaseHas('roles', ['id' => $roleId, 'deleted_at' => null]);
        $this->assertSame($roleId, (int) DB::table('admins')->where('id', $assigned->id)->value('role_id'));
    }

    public function test_role_delete_locks_the_role_row_before_checking_admin_references(): void
    {
        $actor = $this->createAdmin('role-delete-lock-actor', 'password');
        $roleId = $this->createRole('role-delete-lock-' . uniqid());
        $queries = [];

        DB::listen(function (QueryExecuted $query) use (&$queries): void {
            $queries[] = $query->sql;
        });

        $this->apiRequest($actor)->postJson('/api/admin/deleteRole', [
            'id' => $roleId,
        ])->assertOk()->assertJsonPath('code', ResponseCode::DELETED);

        $lockedRoleQuery = array_filter($queries, function (string $sql): bool {
            return stripos($sql, 'roles') !== false
                && stripos($sql, 'for update') !== false;
        });

        $this->assertNotEmpty($lockedRoleQuery);
    }

    public function test_admin_create_locks_the_role_row_before_writing_role_id(): void
    {
        $actor = $this->createAdmin('admin-create-role-lock-actor', 'password');
        $roleId = $this->createRole('admin-create-role-lock-' . uniqid());
        $queries = [];

        DB::listen(function (QueryExecuted $query) use (&$queries): void {
            $queries[] = $query->sql;
        });

        $username = 'admin-create-role-lock-' . uniqid();
        $response = $this->apiRequest($actor)->postJson('/api/admin/createAdmin', [
            'username' => $username,
            'email' => $username . '@example.test',
            'password' => 'new-secret',
            'role_id' => $roleId,
            'status' => 1,
        ]);

        $response->assertOk()->assertJsonPath('code', ResponseCode::CREATED);
        $this->assertSame($roleId, (int) DB::table('admins')->where('username', $username)->value('role_id'));

        $lockedRoleQuery = array_filter($queries, function (string $sql): bool {
            return stripos($sql, 'roles') !== false
                && stripos($sql, 'for update') !== false;
        });

        $this->assertNotEmpty($lockedRoleQuery);
    }

    public function test_admin_update_locks_the_role_row_before_writing_role_id(): void
    {
        $actor = $this->createAdmin('admin-update-role-lock-actor', 'password');
        $target = $this->createAdmin('admin-update-role-lock-target', 'password');
        $roleId = $this->createRole('admin-update-role-lock-' . uniqid());
        $queries = [];

        DB::listen(function (QueryExecuted $query) use (&$queries): void {
            $queries[] = $query->sql;
        });

        $response = $this->apiRequest($actor)->postJson('/api/admin/updateAdmin/' . $target->id, [
            'username' => 'admin-role-lock-updated-' . uniqid(),
            'email' => 'admin-update-role-lock-updated-' . uniqid() . '@example.test',
            'role_id' => $roleId,
            'status' => 1,
        ]);

        $response->assertOk();
        $this->assertSame(ResponseCode::UPDATED, $response->json('code'), $response->getContent());
        $this->assertSame($roleId, (int) DB::table('admins')->where('id', $target->id)->value('role_id'));

        $lockedRoleQuery = array_filter($queries, function (string $sql): bool {
            return stripos($sql, 'roles') !== false
                && stripos($sql, 'for update') !== false;
        });

        $this->assertNotEmpty($lockedRoleQuery);
    }

    public function test_admin_create_accepts_an_explicit_null_role_id(): void
    {
        $actor = $this->createAdmin('admin-create-null-role-actor', 'password');
        $username = 'admin-create-null-role-' . uniqid();

        $response = $this->apiRequest($actor)->postJson('/api/admin/createAdmin', [
            'username' => $username,
            'email' => $username . '@example.test',
            'password' => 'new-secret',
            'role_id' => null,
            'status' => 1,
        ]);

        $response->assertOk()->assertJsonPath('code', ResponseCode::CREATED);
        $this->assertNull(DB::table('admins')->where('username', $username)->value('role_id'));
    }

    public function test_admin_update_can_clear_an_existing_role_with_null(): void
    {
        $actor = $this->createAdmin('admin-clear-role-actor', 'password');
        $roleId = $this->createRole('admin-clear-role-' . uniqid());
        $target = $this->createAdmin('admin-clear-role-target', 'password', $roleId);

        $response = $this->apiRequest($actor)->postJson('/api/admin/updateAdmin/' . $target->id, [
            'username' => (string) $target->username,
            'email' => (string) $target->email,
            'role_id' => null,
            'status' => 1,
        ]);

        $response->assertOk()->assertJsonPath('code', ResponseCode::UPDATED);
        $this->assertNull(DB::table('admins')->where('id', $target->id)->value('role_id'));
    }

    public function test_role_delete_rejects_a_role_referenced_only_by_a_soft_deleted_admin(): void
    {
        $actor = $this->createAdmin('role-delete-soft-deleted-actor', 'password');
        $roleId = $this->createRole('role-delete-soft-deleted-reference-' . uniqid());
        $assigned = $this->createAdmin('role-delete-soft-deleted-assigned', 'password', $roleId);
        $assigned->delete();

        $response = $this->apiRequest($actor)->postJson('/api/admin/deleteRole', [
            'id' => $roleId,
        ]);

        $response->assertOk()->assertJsonPath('code', ResponseCode::OPERATION_NOT_ALLOWED);
        $this->assertDatabaseHas('roles', ['id' => $roleId, 'deleted_at' => null]);
        $this->assertDatabaseHas('admins', [
            'id' => $assigned->id,
            'role_id' => $roleId,
        ]);
    }

    public function test_assign_permissions_replaces_only_the_pivot_source(): void
    {
        $actor = $this->createAdmin('role-permission-actor', 'password');
        $roleId = $this->createRole('role-permission-replace-' . uniqid(), ['legacy_snapshot']);
        [$oldPermissionId, $newPermissionId] = $this->adminPermissionIds();
        DB::table('role_permissions')->insert([
            'role_id' => $roleId,
            'permission_id' => $oldPermissionId,
            'created_at' => time(),
            'updated_at' => time(),
        ]);

        $response = $this->apiRequest($actor)->postJson('/api/admin/assignPermissions', [
            'role_id' => $roleId,
            'permissions' => [$newPermissionId, $newPermissionId],
        ]);

        $response->assertOk()->assertJsonPath('code', ResponseCode::SUCCESS);
        $this->assertSame(
            [$newPermissionId],
            DB::table('role_permissions')->where('role_id', $roleId)->pluck('permission_id')->map(function ($id) {
                return (int) $id;
            })->all()
        );
        $this->assertSame(
            ['legacy_snapshot'],
            json_decode((string) DB::table('roles')->where('id', $roleId)->value('permissions'), true)
        );
    }

    public function test_assign_permissions_locks_the_role_row_before_replacing_permissions(): void
    {
        $actor = $this->createAdmin('role-permission-lock-actor', 'password');
        $roleId = $this->createRole('role-permission-lock-' . uniqid());
        [, $permissionId] = $this->adminPermissionIds();
        $queries = [];

        DB::listen(function (QueryExecuted $query) use (&$queries): void {
            $queries[] = $query->sql;
        });

        $this->apiRequest($actor)->postJson('/api/admin/assignPermissions', [
            'role_id' => $roleId,
            'permissions' => [$permissionId],
        ])->assertOk()->assertJsonPath('code', ResponseCode::SUCCESS);

        $lockedRoleQuery = array_filter($queries, function (string $sql): bool {
            return stripos($sql, 'roles') !== false
                && stripos($sql, 'for update') !== false;
        });

        $this->assertNotEmpty(
            $lockedRoleQuery,
            'assignPermissions must lock the target roles row with SELECT ... FOR UPDATE.'
        );
    }

    public function test_assign_permissions_rolls_back_when_the_pivot_insert_fails(): void
    {
        $actor = $this->createAdmin('role-permission-rollback-actor', 'password');
        $roleId = $this->createRole('role-permission-rollback-' . uniqid());
        [$oldPermissionId, $newPermissionId] = $this->adminPermissionIds();
        DB::table('role_permissions')->insert([
            'role_id' => $roleId,
            'permission_id' => $oldPermissionId,
            'created_at' => time(),
            'updated_at' => time(),
        ]);

        $armed = true;
        DB::listen(function (QueryExecuted $query) use (&$armed): void {
            if ($armed
                && stripos(ltrim($query->sql), 'insert') === 0
                && stripos($query->sql, 'role_permissions') !== false) {
                $armed = false;
                throw new RuntimeException('Forced role permission pivot failure.');
            }
        });

        $this->apiRequest($actor)->postJson('/api/admin/assignPermissions', [
            'role_id' => $roleId,
            'permissions' => [$newPermissionId],
        ])->assertStatus(500);

        $this->assertSame(
            [$oldPermissionId],
            DB::table('role_permissions')->where('role_id', $roleId)->pluck('permission_id')->map(function ($id) {
                return (int) $id;
            })->all()
        );
    }

    private function legacyRequest(Admin $actor): self
    {
        return $this->withoutMiddleware([
            AdminAuthenticate::class,
            LegacyAdminAuthenticate::class,
            JwtAuthMiddleware::class,
            SingleSignOn::class,
            CheckPermission::class,
        ])->actingAs($actor, 'admin');
    }

    private function apiRequest(Admin $actor): self
    {
        return $this->withoutMiddleware([
            AdminAuthenticate::class,
            JwtAuthMiddleware::class,
            SingleSignOn::class,
            CheckPermission::class,
        ])->actingAs($actor, 'admin');
    }

    private function createAdmin(string $prefix, string $password, ?int $roleId = null): Admin
    {
        $username = $prefix . '-' . uniqid();
        $now = time();
        $id = DB::table('admins')->insertGetId([
            'username' => $username,
            'email' => $username . '@example.test',
            'password' => Hash::make($password),
            'mobile' => null,
            'role_id' => $roleId,
            'status' => 1,
            'created_at' => $now,
            'updated_at' => $now,
            'deleted_at' => null,
        ]);

        return Admin::query()->findOrFail($id);
    }

    /**
     * @param array<int, string>|null $legacySnapshot
     */
    private function createRole(string $name, ?array $legacySnapshot = null): int
    {
        $now = time();

        return (int) DB::table('roles')->insertGetId([
            'name' => $name,
            'guard_type' => 'admin',
            'description' => 'Phase 2 role closure fixture',
            'permissions' => $legacySnapshot === null ? null : json_encode($legacySnapshot),
            'status' => 1,
            'created_at' => $now,
            'updated_at' => $now,
            'deleted_at' => null,
        ]);
    }

    /**
     * @return array{int, int}
     */
    private function adminPermissionIds(): array
    {
        $ids = DB::table('permissions')
            ->where('guard_type', 'admin')
            ->whereNull('deleted_at')
            ->orderBy('id')
            ->limit(2)
            ->pluck('id')
            ->map(function ($id) {
                return (int) $id;
            })
            ->all();

        $this->assertCount(2, $ids, '测试数据库必须至少包含两条后台权限。');

        return [$ids[0], $ids[1]];
    }
}
