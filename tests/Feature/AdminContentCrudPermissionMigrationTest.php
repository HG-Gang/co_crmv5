<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/07/09
 * Time: 07:33
 */

/**
 * AdminContentCrudPermissionMigrationTest
 *
 * 文件功能：
 * - 验证内容与账号类按钮/API 权限由迁移类写入 permissions 表，且对应命名路由接受 id 参数。
 * - 输入：权限/结构迁移类与测试数据库；输出：PHPUnit 断言结果。
 * - 明确不负责：不验证 check.permission 中间件的运行时鉴权与按钮渲染（由模块契约测试锁定）。
 */

namespace Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * 后台内容与账号类 CRUD 权限、路由覆盖测试。
 *
 * 测试目标：
 * - 支付通道、管理员、新闻公告的新增/更新/删除权限必须写入 permissions 表。
 * - 需要记录 ID 的更新/删除接口必须在命名路由中声明 {id} 参数，方便页面 JS 通过 routeParams.id 生成正确 URL。
 */
class AdminContentCrudPermissionMigrationTest extends TestCase
{
    use DatabaseTransactions;

    /**
     * 内容与账号类 CRUD 权限迁移必须写入按钮/API 权限。
     *
     * @return void
     */
    public function test_content_crud_permissions_are_seeded_by_migration(): void
    {
        $migrationPath = database_path('migrations/2026_06_07_000001_add_admin_content_crud_permissions.php');

        $this->assertFileExists($migrationPath, '内容与账号类 CRUD 权限迁移文件不存在。');

        require_once $migrationPath;

        $slugs = collect($this->expectedPermissions())->pluck('slug')->all();
        DB::table('permissions')->whereIn('slug', $slugs)->delete();

        (new \AddAdminContentCrudPermissions())->up();

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
     * 更新和删除接口必须具备路由 ID 参数。
     *
     * @return void
     */
    public function test_content_crud_named_routes_accept_id_parameter(): void
    {
        $expectedUris = [
            'admin_api_createChannel' => 'api/admin/createChannel',
            'admin_api_updateChannel' => 'api/admin/updateChannel/{id}',
            'admin_api_deleteChannel' => 'api/admin/deleteChannel/{id}',
            'admin_api_toggleChannel' => 'api/admin/toggleChannel/{id}',
            'admin_api_createAdmin' => 'api/admin/createAdmin',
            'admin_api_updateAdmin' => 'api/admin/updateAdmin/{id}',
            'admin_api_resetAdminPassword' => 'api/admin/resetAdminPassword/{id}',
            'admin_api_deleteAdmin' => 'api/admin/deleteAdmin/{id}',
            'admin_api_createNews' => 'api/admin/createNews',
            'admin_api_updateNews' => 'api/admin/updateNews/{id}',
            'admin_api_deleteNews' => 'api/admin/deleteNews/{id}',
            'admin_api_toggleNews' => 'api/admin/toggleNews/{id}',
        ];

        foreach ($expectedUris as $routeName => $uri) {
            $route = Route::getRoutes()->getByName($routeName);

            $this->assertNotNull($route, $routeName . ' 命名路由不存在。');
            $this->assertSame($uri, $route->uri(), $routeName . ' 未声明正确的 {id} 路由参数。');
        }
    }

    /**
     * 本迁移必须写入的内容与账号类按钮/API 权限。
     *
     * @return array<int, array{slug:string, api_route:string}>
     */
    private function expectedPermissions(): array
    {
        return [
            ['slug' => 'admin_channel_create', 'api_route' => 'admin_api_createChannel'],
            ['slug' => 'admin_channel_update', 'api_route' => 'admin_api_updateChannel'],
            ['slug' => 'admin_channel_delete', 'api_route' => 'admin_api_deleteChannel'],
            ['slug' => 'admin_channel_toggle', 'api_route' => 'admin_api_toggleChannel'],
            ['slug' => 'admin_admin_create', 'api_route' => 'admin_api_createAdmin'],
            ['slug' => 'admin_admin_update', 'api_route' => 'admin_api_updateAdmin'],
            ['slug' => 'admin_admin_reset_password', 'api_route' => 'admin_api_resetAdminPassword'],
            ['slug' => 'admin_admin_delete', 'api_route' => 'admin_api_deleteAdmin'],
            ['slug' => 'admin_news_create', 'api_route' => 'admin_api_createNews'],
            ['slug' => 'admin_news_update', 'api_route' => 'admin_api_updateNews'],
            ['slug' => 'admin_news_delete', 'api_route' => 'admin_api_deleteNews'],
            ['slug' => 'admin_news_toggle', 'api_route' => 'admin_api_toggleNews'],
        ];
    }
}
