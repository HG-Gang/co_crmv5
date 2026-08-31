<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/02
 * Time: 01:46
 */

/**
 * 文件功能：验证菜单（permissions 表菜单行）更新、删除接口对请求体 id 的
 *           严格校验，非法 id 不得变更或删除菜单，并核对最终检查清单文档。
 *
 * 适用场景：后台 /api/admin/updateMenu、/api/admin/deleteMenu 接口的
 *           输入校验回归测试。
 *
 * 入参例子：
 * - POST /api/admin/updateMenu：{id, title, slug, icon, url, path, api_route, ...}
 * - POST /api/admin/deleteMenu：{id}
 *
 * 返回值：
 * - id 带非数字后缀时返回 code=VALIDATION_FAILED，菜单行保持原样。
 *
 * 异常或失败场景：
 * - 非严格数字 id（如 '{id}abc'）时校验失败，不做更新或删除。
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

class AdminMenuRequestIdValidationClosureModuleTest extends TestCase
{
    use DatabaseTransactions;

    protected function tearDown(): void
    {
        DB::table('permissions')
            ->where(function ($query) {
                $query->where('slug', 'like', 'menu-request-%')
                    ->orWhere('slug', 'like', 'admin_menu_request_%');
            })
            ->delete();

        parent::tearDown();
    }

    // 更新菜单时应拒绝非严格 id 且菜单保持原样。
    public function test_update_menu_rejects_non_strict_id_without_updating_menu(): void
    {
        $actor = $this->ensureSuperAdmin();
        $menuId = $this->createMenuPermission('menu-request-update-target', 'Menu request update target', 1);

        $response = $this->withoutMiddleware([AdminAuthenticate::class, JwtAuthMiddleware::class, SingleSignOn::class, CheckPermission::class])
            ->actingAs($actor, 'admin')
            ->post('/api/admin/updateMenu', [
                'id' => $menuId . 'abc',
                'title' => 'Menu request update changed',
                'slug' => 'menu-request-update-changed-' . uniqid(),
                'icon' => 'layui-icon-set',
                'url' => '/admin/menu-request-changed',
                'path' => '/admin/menu-request-changed',
                'api_route' => 'admin_api_updateMenu',
                'parent_id' => 0,
                'guard_type' => 'admin',
                'type' => 1,
                'sort' => 8,
                'status' => 0,
            ]);

        $response->assertOk()
            ->assertJsonPath('code', ResponseCode::VALIDATION_FAILED);

        $this->assertDatabaseHas('permissions', [
            'id' => $menuId,
            'name' => 'Menu request update target',
            'slug' => 'menu-request-update-target',
            'route' => '/admin/menu-request-update-target',
            'status' => 1,
            'deleted_at' => null,
        ]);
    }

    // 删除菜单时应拒绝非严格 id 且不删除菜单。
    public function test_delete_menu_rejects_non_strict_id_without_deleting_menu(): void
    {
        $actor = $this->ensureSuperAdmin();
        $menuId = $this->createMenuPermission('menu-request-delete-target', 'Menu request delete target', 1);

        $response = $this->withoutMiddleware([AdminAuthenticate::class, JwtAuthMiddleware::class, SingleSignOn::class, CheckPermission::class])
            ->actingAs($actor, 'admin')
            ->post('/api/admin/deleteMenu', [
                'id' => $menuId . 'abc',
            ]);

        $response->assertOk()
            ->assertJsonPath('code', ResponseCode::VALIDATION_FAILED);

        $this->assertDatabaseHas('permissions', [
            'id' => $menuId,
            'slug' => 'menu-request-delete-target',
            'deleted_at' => null,
        ]);
    }

    // 核对最终检查清单文档记录了菜单请求 id 校验边界。
    public function test_final_checklist_records_menu_request_id_validation_boundary(): void
    {
        $checklist = file_get_contents(base_path('docs/admin-backend-blade-permission-final-checklist.md')) ?: '';

        $this->assertStringContainsString('## 304.', $checklist);
        $this->assertStringContainsString('MenuController::updateMenu', $checklist);
        $this->assertStringContainsString('MenuController::deleteMenu', $checklist);
        $this->assertStringContainsString('/api/admin/updateMenu', $checklist);
        $this->assertStringContainsString('/api/admin/deleteMenu', $checklist);
        $this->assertStringContainsString('permissions.id', $checklist);
        $this->assertStringContainsString('AdminMenuRequestIdValidationClosureModuleTest', $checklist);
    }

    private function ensureSuperAdmin(): Admin
    {
        $now = time();

        DB::table('admins')->updateOrInsert(
            ['id' => 1],
            [
                'username' => 'admin-menu-request-id-super',
                'email' => 'admin-menu-request-id-super@example.test',
                'password' => Hash::make('password'),
                'status' => 1,
                'created_at' => $now,
                'updated_at' => $now,
                'deleted_at' => null,
            ]
        );

        return Admin::query()->findOrFail(1);
    }

    private function createMenuPermission(string $slug, string $name, int $status): int
    {
        $now = now();

        return (int) DB::table('permissions')->insertGetId([
            'name' => $name,
            'slug' => $slug,
            'guard_type' => 'admin',
            'parent_id' => 0,
            'type' => 1,
            'icon' => 'layui-icon-set',
            'sort' => 0,
            'route' => '/admin/' . $slug,
            'api_route' => 'admin_api_menuTree',
            'status' => $status,
            'created_at' => $now,
            'updated_at' => $now,
            'deleted_at' => null,
        ]);
    }
}
