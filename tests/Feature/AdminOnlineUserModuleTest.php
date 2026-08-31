<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/07/26
 * Time: 04:19
 */

/**
 * AdminOnlineUserModuleTest
 *
 * 文件功能：
 * - 验证后台在线用户模块：页面注册、Blade 控件、列表与强下线 API 权限中间件、真实表映射、权限迁移及前端强下线动作暴露。
 * - 输入：HTTP 请求与事务回滚的数据库夹具；输出：PHPUnit 断言结果。
 * - 明确不负责：不覆盖真实 MT4 网关与线上支付/出金通道（由网关契约测试锁定）。
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

/**
 * 后台在线用户模块覆盖测试。
 *
 * 功能逻辑说明：
 * - 旧项目 `UserLoginOnlineController` 提供在线用户查看能力，新项目第一阶段基于真实表 `user_onlines` 做只读列表。
 * - 当前 MySQL 3307 可能不可用，本测试不读取真实数据库，只约束 Blade 页面、API 路由、权限中间件、模型和权限迁移契约。
 * - 后续数据库恢复后，再补充真实在线用户样本和数据范围验证。
 */
class AdminOnlineUserModuleTest extends TestCase
{
    use DatabaseTransactions;

    /**
     * 在线用户页面必须注册为独立 Blade 路由。
     *
     * @return void
     */
    public function test_online_user_page_is_registered(): void
    {
        $this->assertTrue(Route::has('admin_page_online_users'), 'admin_page_online_users 页面路由未注册。');
    }

    /**
     * 在线用户页面必须包含筛选表单、表格容器和页面脚本。
     *
     * @return void
     */
    public function test_online_user_page_renders_blade_controls(): void
    {
        $response = $this->get('/admin/online-users');

        $response->assertOk();
        $response->assertSee('crm-admin-workbench', false);
        $response->assertSee('id="onlineUserSearchForm"', false);
        $response->assertSee('id="onlineUserTable"', false);
        $response->assertSee('name="user_id"', false);
        $response->assertSee('name="ip_address"', false);
        $response->assertSee('lay-event="forceOffline"', false);
        $response->assertSee('data-permission="admin_online_user_force_offline"', false);
        $response->assertSee('/js/apps/admin/layui/pages.js', false);
        $response->assertSee("data-layui-page=\"online-users/index\"", false);
    }

    public function test_online_user_force_offline_api_route_has_permission_middleware(): void
    {
        $this->assertTrue(Route::has('admin_api_forceOfflineUser'), 'admin_api_forceOfflineUser API route is not registered.');
        $this->assertContains(
            'check.permission:admin',
            Route::getRoutes()->getByName('admin_api_forceOfflineUser')->gatherMiddleware()
        );
    }

    /**
     * 在线用户列表 API 必须注册并挂载后台权限中间件。
     *
     * @return void
     */
    public function test_online_user_api_route_has_permission_middleware(): void
    {
        $this->assertTrue(Route::has('admin_api_onlineUserList'), 'admin_api_onlineUserList API 路由未注册。');
        $this->assertContains(
            'check.permission:admin',
            Route::getRoutes()->getByName('admin_api_onlineUserList')->gatherMiddleware()
        );
    }

    /**
     * 控制器必须读取真实 user_onlines 表并关联 user_infos 业务用户信息。
     *
     * @return void
     */
    public function test_online_user_controller_uses_real_table_and_user_mapping(): void
    {
        $controllerPath = app_path('Http/Controllers/Admin/OnlineUserController.php');

        $this->assertFileExists($controllerPath, 'OnlineUserController 控制器不存在。');
        $source = file_get_contents($controllerPath);

        $this->assertStringContainsString('UserOnline::query()', $source);
        $this->assertStringContainsString('leftJoin', $source);
        $this->assertStringContainsString('user_infos', $source);
        $this->assertStringContainsString('last_activity', $source);
    }

    /**
     * 权限迁移必须声明页面权限和列表 API 权限，保证权限仍来自 permissions 表。
     *
     * @return void
     */
    public function test_online_user_permission_migration_declares_required_permissions(): void
    {
        $migrationPath = database_path('migrations/2026_06_07_000009_add_admin_online_user_permissions.php');

        $this->assertFileExists($migrationPath, '在线用户权限迁移文件不存在。');
        $source = file_get_contents($migrationPath);

        foreach ([
            'admin_online_users',
            'admin_online_user_list',
            'admin_online_user_force_offline',
            'admin_api_onlineUserList',
            'admin_api_forceOfflineUser',
            '/admin/online-users',
        ] as $expected) {
            $this->assertStringContainsString($expected, $source);
        }
    }

    public function test_online_user_force_offline_permission_has_follow_up_migration_for_existing_databases(): void
    {
        $migrationPath = database_path('migrations/2026_07_07_000001_add_admin_online_user_force_offline_permission.php');

        $this->assertFileExists($migrationPath, 'Existing databases need a follow-up migration for the new force-offline permission.');

        $source = file_get_contents($migrationPath);
        $this->assertStringContainsString('admin_online_user_force_offline', $source);
        $this->assertStringContainsString('admin_api_forceOfflineUser', $source);
        $this->assertStringContainsString('admin_online_users', $source);
    }

    public function test_online_user_frontend_configs_expose_force_offline_action(): void
    {
        $blade = file_get_contents(resource_path('admin/layui/online-users/index.blade.php'));
        $layui = file_get_contents(public_path('js/apps/admin/layui/pages.js'));
        $pageController = file_get_contents(app_path('Http/Controllers/CrmUi/Admin/PageController.php'));

        $this->assertStringContainsString('lay-event="forceOffline"', $blade);
        $this->assertStringContainsString('admin_online_user_force_offline', $blade);
        $this->assertStringContainsString('/api/admin/forceOfflineUser/', $layui);
        $this->assertStringContainsString("tool(onlineUserTable)", $layui);
        $this->assertStringContainsString("'route' => 'admin_api_forceOfflineUser'", $pageController);
    }

    public function test_force_offline_user_removes_online_record_and_writes_operation_log(): void
    {
        $admin = Admin::query()->first() ?: Admin::query()->create([
            'username' => 'online-user-audit-admin',
            'email' => 'online-user-audit-admin@example.test',
            'password' => bcrypt('password'),
            'status' => 1,
        ]);
        $now = time();
        $targetUserId = 983901;
        $ipAddress = '203.0.113.91';

        $onlineId = DB::table('user_onlines')->insertGetId([
            'user_id' => $targetUserId,
            'last_activity' => $now,
            'ip_address' => $ipAddress,
            'user_agent' => 'Feature Test Browser',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        DB::table('operation_logs')
            ->where('order_no', 'online_user:' . $onlineId)
            ->delete();

        $response = $this->withoutMiddleware([AdminAuthenticate::class, JwtAuthMiddleware::class, SingleSignOn::class, CheckPermission::class])
            ->actingAs($admin, 'admin')
            ->post('/api/admin/forceOfflineUser/' . $onlineId);

        $response->assertOk();
        $response->assertJsonPath('code', ResponseCode::DELETED);

        $this->assertDatabaseMissing('user_onlines', ['id' => $onlineId]);

        $log = DB::table('operation_logs')
            ->where('admin_id', $admin->id)
            ->where('order_no', 'online_user:' . $onlineId)
            ->orderByDesc('id')
            ->first();

        $this->assertNotNull($log, 'forceOfflineUser must create an operation_logs audit record.');
        $this->assertSame($admin->username, $log->admin_name);
        $this->assertSame($targetUserId, (int) $log->target_user_id);
        $this->assertNotSame('', (string) $log->ip);
        $this->assertStringContainsString('Force offline user_id:' . $targetUserId, $log->content);
        $this->assertStringContainsString('online_record_id:' . $onlineId, $log->content);
        $this->assertStringContainsString('ip_address:' . $ipAddress, $log->content);
    }
}
