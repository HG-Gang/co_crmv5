<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/06/08
 * Time: 01:44
 */

/**
 * AdminCorePermissionMigrationTest
 *
 * 文件功能：
 * - 验证核心按钮权限期望值由迁移类完整写入 permissions 表。
 * - 输入：权限/结构迁移类与测试数据库；输出：PHPUnit 断言结果。
 * - 明确不负责：不验证 check.permission 中间件的运行时鉴权与按钮渲染（由模块契约测试锁定）。
 */

namespace Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * 后台核心模块权限迁移测试。
 *
 * 测试目标：
 * - 用户、角色、权限、菜单这些后台基础模块的按钮/API 权限必须写入 permissions 表。
 * - Blade 的 data-permission 必须能对应真实 permissions.slug，否则前端按钮控制无法从数据表配置得到。
 */
class AdminCorePermissionMigrationTest extends TestCase
{
    use DatabaseTransactions;

    /**
     * 核心权限迁移必须写入基础后台模块按钮/API 权限。
     *
     * @return void
     */
    public function test_core_button_permissions_are_seeded_by_migration(): void
    {
        $migrationPath = database_path('migrations/2026_06_06_000006_add_admin_core_button_permissions.php');

        $this->assertFileExists($migrationPath, '后台核心按钮权限迁移文件不存在。');

        require_once $migrationPath;

        $slugs = collect($this->expectedPermissions())->pluck('slug')->all();
        DB::table('permissions')->whereIn('slug', $slugs)->delete();

        (new \AddAdminCoreButtonPermissions())->up();

        foreach ($this->expectedPermissions() as $permission) {
            $record = DB::table('permissions')->where('slug', $permission['slug'])->first();

            $this->assertNotNull($record, $permission['slug'] . ' 权限未写入 permissions 表。');
            $this->assertSame('admin', $record->guard_type);
            $this->assertSame(3, (int) $record->type);
            $this->assertSame($permission['api_route'], (string) $record->api_route);
            $this->assertSame(1, (int) $record->status);
        }
    }

    /**
     * 核心按钮权限期望值。
     *
     * @return array<int, array{slug:string, api_route:string}>
     */
    private function expectedPermissions(): array
    {
        return [
            ['slug' => 'admin_user_status', 'api_route' => 'admin_api_changeUserStatus'],
            ['slug' => 'admin_role_create', 'api_route' => 'admin_api_createRole'],
            ['slug' => 'admin_role_update', 'api_route' => 'admin_api_updateRole'],
            ['slug' => 'admin_role_delete', 'api_route' => 'admin_api_deleteRole'],
            ['slug' => 'admin_role_assign_permissions', 'api_route' => 'admin_api_assignPermissions'],
            ['slug' => 'admin_permission_update', 'api_route' => 'admin_api_updatePermission'],
            ['slug' => 'admin_menu_create', 'api_route' => 'admin_api_createMenu'],
            ['slug' => 'admin_menu_update', 'api_route' => 'admin_api_updateMenu'],
        ];
    }
}
