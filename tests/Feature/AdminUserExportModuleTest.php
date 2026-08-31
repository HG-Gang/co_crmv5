<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/02
 * Time: 01:56
 */

/**
 * 后台用户导出与用户列表接口的功能测试。
 *
 * 文件功能：
 * - 验证导出路由注册了 check.permission:admin 权限中间件。
 * - 验证导出接口按筛选条件返回对应 CSV 内容及文件名 users_export.csv。
 * - 验证用户列表与导出接口支持 created_at 日期范围筛选。
 * - 验证前端导出配置与权限迁移文件均已声明。
 *
 * 适用场景：
 * - 后台用户管理页面的条件筛选、导出 CSV 及前端按钮权限配置。
 *
 * 入参例子：
 * - POST /api/admin/exportUsers，body：{"account_type": 2, "email": "export-alice"}。
 * - POST /api/admin/users，body：{"user_name": "...", "start_date": "2026-03-01", "end_date": "2026-03-31", "limit": 10}。
 *
 * 返回值：
 * - 导出成功返回 text/csv 流式响应；列表成功返回 code=ResponseCode::SUCCESS 及 data.total。
 *
 * 异常或失败场景：
 * - 无 admin_user_export 权限时接口被中间件拦截；日期范围外数据不出现在结果中。
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
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class AdminUserExportModuleTest extends TestCase
{
    use DatabaseTransactions;

    // 校验用户导出 API 路由已注册且挂载 check.permission:admin 中间件。
    public function test_user_export_api_route_has_permission_middleware(): void
    {
        $this->assertTrue(Route::has('admin_api_exportUsers'), 'admin_api_exportUsers API route is not registered.');
        $this->assertContains(
            'check.permission:admin',
            Route::getRoutes()->getByName('admin_api_exportUsers')->gatherMiddleware()
        );
    }

    // 验证导出接口按 account_type 与 email 筛选返回对应 CSV 且不含筛除用户。
    public function test_user_export_endpoint_returns_current_filter_csv(): void
    {
        $admin = $this->ensureSuperAdmin();
        $now = time();

        $this->upsertUserExportFixture(984301, 'Export Alice', 'export-alice@example.test', 2, $now);
        $this->upsertUserExportFixture(984302, 'Export Bob', 'export-bob@example.test', 1, $now);

        $response = $this->withoutMiddleware([AdminAuthenticate::class, JwtAuthMiddleware::class, SingleSignOn::class, CheckPermission::class])
            ->actingAs($admin, 'admin')
            ->post('/api/admin/exportUsers', [
                'account_type' => 2,
                'email' => 'export-alice',
            ]);

        $response->assertOk();
        $this->assertStringContainsString('text/csv', (string) $response->headers->get('content-type'));
        $this->assertStringContainsString('users_export.csv', (string) $response->headers->get('content-disposition'));

        $content = $response->streamedContent();
        $this->assertStringContainsString('user_id,user_name,email,phone,account_type,auth_status,is_enabled,is_cancelled,total_funds,created_at', $content);
        $this->assertStringContainsString('984301', $content);
        $this->assertStringContainsString('"Export Alice"', $content);
        $this->assertStringContainsString('export-alice@example.test', $content);
        $this->assertStringNotContainsString('984302', $content);
        $this->assertStringNotContainsString('export-bob@example.test', $content);
    }

    // 验证用户列表接口按创建日期范围筛选只返回范围内的用户。
    public function test_user_list_endpoint_filters_by_created_date_range(): void
    {
        $admin = $this->ensureSuperAdmin();
        $inRangeUserId = 984311;
        $outsideUserId = 984312;

        $this->upsertUserExportFixture(
            $inRangeUserId,
            'User Date Filter In Range',
            'user-date-in@example.test',
            2,
            strtotime('2026-03-15 10:00:00')
        );
        $this->upsertUserExportFixture(
            $outsideUserId,
            'User Date Filter Outside Range',
            'user-date-out@example.test',
            2,
            strtotime('2026-04-15 10:00:00')
        );

        $response = $this->withoutMiddleware([AdminAuthenticate::class, JwtAuthMiddleware::class, SingleSignOn::class, CheckPermission::class])
            ->actingAs($admin, 'admin')
            ->post('/api/admin/users', [
                'account_type' => 2,
                'user_name' => 'User Date Filter',
                'start_date' => '2026-03-01',
                'end_date' => '2026-03-31',
                'limit' => 10,
            ]);

        $response->assertOk();
        $response->assertJsonPath('code', ResponseCode::SUCCESS);
        $response->assertJsonPath('data.total', 1);
        $this->assertSame($inRangeUserId, (int) $response->json('data.list.0.user_id'));
    }

    // 验证用户导出接口按创建日期范围筛选只导出范围内的用户。
    public function test_user_export_endpoint_filters_by_created_date_range(): void
    {
        $admin = $this->ensureSuperAdmin();

        $this->upsertUserExportFixture(
            984321,
            'Export User Date Filter In Range',
            'export-user-date-in@example.test',
            2,
            strtotime('2026-05-10 10:00:00')
        );
        $this->upsertUserExportFixture(
            984322,
            'Export User Date Filter Outside Range',
            'export-user-date-out@example.test',
            2,
            strtotime('2026-06-10 10:00:00')
        );

        $response = $this->withoutMiddleware([AdminAuthenticate::class, JwtAuthMiddleware::class, SingleSignOn::class, CheckPermission::class])
            ->actingAs($admin, 'admin')
            ->post('/api/admin/exportUsers', [
                'account_type' => 2,
                'user_name' => 'Export User Date Filter',
                'start_date' => '2026-05-01',
                'end_date' => '2026-05-31',
            ]);

        $response->assertOk();
        $content = $response->streamedContent();

        $this->assertStringContainsString('984321', $content);
        $this->assertStringContainsString('export-user-date-in@example.test', $content);
        $this->assertStringNotContainsString('984322', $content);
        $this->assertStringNotContainsString('export-user-date-out@example.test', $content);
    }

    // 校验前端导出按钮、日期控件及后端导出动作配置均已暴露。
    public function test_user_export_frontend_configs_are_exposed(): void
    {
        $blade = file_get_contents(resource_path('admin/layui/users/index.blade.php')) ?: '';
        $layui = file_get_contents(public_path('js/apps/admin/layui/pages.js')) ?: '';
        $crmui = file_get_contents(app_path('Http/Controllers/CrmUi/Admin/PageController.php')) ?: '';

        $this->assertStringContainsString('id="exportUsers"', $blade);
        $this->assertStringContainsString('data-permission="admin_user_export"', $blade);
        $this->assertStringContainsString('name="user_name"', $blade);
        $this->assertStringContainsString('id="userStartDate"', $blade);
        $this->assertStringContainsString('name="start_date"', $blade);
        $this->assertStringContainsString('id="userEndDate"', $blade);
        $this->assertStringContainsString('name="end_date"', $blade);
        $this->assertStringContainsString('/api/admin/exportUsers', $layui);
        $this->assertStringContainsString("laydate.render({elem: '#userStartDate'", $layui);
        $this->assertStringContainsString("laydate.render({elem: '#userEndDate'", $layui);
        $this->assertStringContainsString("exportActions('admin_api_exportUsers', 'users_export.csv')", $crmui);
        $this->assertStringContainsString("'filters' => ['user_id', 'email', 'user_name', 'start_date', 'end_date'", $crmui);
    }

    // 校验核心与跟进迁移文件均声明了用户导出所需权限。
    public function test_user_export_permission_migrations_declare_required_permission(): void
    {
        $coreMigration = file_get_contents(database_path('migrations/2026_06_06_000006_add_admin_core_button_permissions.php')) ?: '';
        $followUpPath = database_path('migrations/2026_07_07_000002_add_admin_user_export_permission.php');

        $this->assertStringContainsString('admin_user_export', $coreMigration);
        $this->assertStringContainsString('admin_api_exportUsers', $coreMigration);
        $this->assertFileExists($followUpPath, 'Existing databases need a follow-up migration for the new user export permission.');

        $followUpMigration = file_get_contents($followUpPath) ?: '';
        $this->assertStringContainsString('admin_user_export', $followUpMigration);
        $this->assertStringContainsString('admin_api_exportUsers', $followUpMigration);
    }

    private function ensureSuperAdmin(): Admin
    {
        $now = time();

        DB::table('admins')->updateOrInsert(
            ['id' => 1],
            [
                'username' => 'user-export-admin',
                'email' => 'user-export-admin@example.test',
                'password' => bcrypt('password'),
                'status' => 1,
                'created_at' => $now,
                'updated_at' => $now,
                'deleted_at' => null,
            ]
        );

        return Admin::query()->findOrFail(1);
    }

    private function upsertUserExportFixture(int $userId, string $userName, string $email, int $accountType, int $now): void
    {
        DB::table('user_infos')->where('user_id', $userId)->delete();
        DB::table('user_logins')->where('email', $email)->orWhere('user_id', $userId)->delete();

        $loginId = DB::table('user_logins')->insertGetId([
            'user_id' => $userId,
            'email' => $email,
            'password' => bcrypt('password'),
            'account_type' => $accountType,
            'is_enabled' => 1,
            'is_cancelled' => 0,
            'source_type' => 0,
            'created_at' => $now,
            'updated_at' => $now,
            'deleted_at' => null,
        ]);

        DB::table('user_infos')->updateOrInsert(
            ['user_id' => $userId],
            [
                'login_id' => $loginId,
                'user_name' => $userName,
                'phone' => '1880000' . substr((string) $userId, -4),
                'account_type' => $accountType,
                'auth_status' => 0,
                'total_funds' => 100,
                'created_at' => $now,
                'updated_at' => $now,
                'deleted_at' => null,
            ]
        );
    }
}
