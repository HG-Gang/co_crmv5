<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/02
 * Time: 01:54
 */

/**
 * 后台权限更新、删除接口请求 id 严格校验的功能测试。
 *
 * 文件功能：
 * - 验证请求体 id 传入非严格数字时更新、删除权限接口均返回校验失败。
 * - 验证校验失败后权限记录不被更新或删除。
 *
 * 适用场景：
 * - 后台权限管理页面的更新、删除操作，防止非法 id 误改权限数据。
 *
 * 入参例子：
 * - POST /api/admin/updatePermission，body：{"id": "1abc", ...}。
 * - POST /api/admin/deletePermission，body：{"id": "1abc"}。
 *
 * 返回值：
 * - 校验失败返回 code=ResponseCode::VALIDATION_FAILED。
 *
 * 异常或失败场景：
 * - 请求 id 非严格整数时接口拒绝执行并保持原权限记录不变。
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

class AdminPermissionRequestIdValidationClosureModuleTest extends TestCase
{
    use DatabaseTransactions;

    protected function tearDown(): void
    {
        DB::table('permissions')
            ->where('slug', 'like', 'permission-request-%')
            ->delete();

        parent::tearDown();
    }

    // 验证更新权限时非严格 id 被拒绝且权限记录原字段保持不变。
    public function test_update_permission_rejects_non_strict_id_without_updating_permission(): void
    {
        $actor = $this->ensureSuperAdmin();
        $permissionId = $this->createPermission('permission-request-update-target', '权限请求更新目标', 1);

        $response = $this->withoutMiddleware([AdminAuthenticate::class, JwtAuthMiddleware::class, SingleSignOn::class, CheckPermission::class])
            ->actingAs($actor, 'admin')
            ->post('/api/admin/updatePermission', [
                'id' => $permissionId . 'abc',
                'parent_id' => 0,
                'name' => '权限请求更新已改变',
                'slug' => 'permission-request-update-changed-' . uniqid(),
                'guard_type' => 'admin',
                'type' => 3,
                'api_route' => 'admin_api_updatePermission',
                'route' => '',
                'icon' => '',
                'sort' => 9,
                'status' => 0,
            ]);

        $response->assertOk()
            ->assertJsonPath('code', ResponseCode::VALIDATION_FAILED);

        $this->assertDatabaseHas('permissions', [
            'id' => $permissionId,
            'name' => '权限请求更新目标',
            'slug' => 'permission-request-update-target',
            'status' => 1,
            'deleted_at' => null,
        ]);
    }

    // 验证删除权限时非严格 id 被拒绝且权限记录未被删除。
    public function test_delete_permission_rejects_non_strict_id_without_deleting_permission(): void
    {
        $actor = $this->ensureSuperAdmin();
        $permissionId = $this->createPermission('permission-request-delete-target', '权限请求删除目标', 1);

        $response = $this->withoutMiddleware([AdminAuthenticate::class, JwtAuthMiddleware::class, SingleSignOn::class, CheckPermission::class])
            ->actingAs($actor, 'admin')
            ->post('/api/admin/deletePermission', [
                'id' => $permissionId . 'abc',
            ]);

        $response->assertOk()
            ->assertJsonPath('code', ResponseCode::VALIDATION_FAILED);

        $this->assertDatabaseHas('permissions', [
            'id' => $permissionId,
            'slug' => 'permission-request-delete-target',
            'deleted_at' => null,
        ]);
    }

    // 校验最终检查清单文档记录了权限请求 id 校验边界。
    public function test_final_checklist_records_permission_request_id_validation_boundary(): void
    {
        $checklist = file_get_contents(base_path('docs/admin-backend-blade-permission-final-checklist.md')) ?: '';

        $this->assertStringContainsString('## 303.', $checklist);
        $this->assertStringContainsString('PermissionController::updatePermission', $checklist);
        $this->assertStringContainsString('PermissionController::deletePermission', $checklist);
        $this->assertStringContainsString('/api/admin/updatePermission', $checklist);
        $this->assertStringContainsString('/api/admin/deletePermission', $checklist);
        $this->assertStringContainsString('permissions.id', $checklist);
        $this->assertStringContainsString('AdminPermissionRequestIdValidationClosureModuleTest', $checklist);
    }

    private function ensureSuperAdmin(): Admin
    {
        $now = time();

        DB::table('admins')->updateOrInsert(
            ['id' => 1],
            [
                'username' => 'admin-permission-request-id-super',
                'email' => 'admin-permission-request-id-super@example.test',
                'password' => Hash::make('password'),
                'status' => 1,
                'created_at' => $now,
                'updated_at' => $now,
                'deleted_at' => null,
            ]
        );

        return Admin::query()->findOrFail(1);
    }

    private function createPermission(string $slug, string $name, int $status): int
    {
        $now = now();

        return (int) DB::table('permissions')->insertGetId([
            'name' => $name,
            'slug' => $slug,
            'guard_type' => 'admin',
            'parent_id' => 0,
            'type' => 3,
            'icon' => '',
            'sort' => 0,
            'route' => '',
            'api_route' => 'admin_api_updatePermission',
            'status' => $status,
            'created_at' => $now,
            'updated_at' => $now,
            'deleted_at' => null,
        ]);
    }
}
