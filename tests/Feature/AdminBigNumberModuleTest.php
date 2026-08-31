<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/07/05
 * Time: 12:32
 */

/**
 * AdminBigNumberModuleTest
 *
 * 文件功能：
 * - 验证大数据统计模块：API 路由权限中间件与权限迁移契约，控制器统计必须按当前 user_trades 的 dateTime 字段而非旧 Unix 时间戳 SQL。
 * - 输入：路由、控制器、Blade/JS、迁移等项目源码文本；输出：PHPUnit 断言结果。
 * - 明确不负责：不覆盖 HTTP 运行时业务流与 MT4 真实网关同步（由集成与功能测试锁定）。
 */

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * 后台大数据统计旧模块接入测试。
 *
 * 业务说明：
 * - 旧项目 BigNumberController 提供 dashboard 和 trend 两个后台统计能力。
 * - 新项目必须把这两个能力接入受保护的后台 API 路由，并由 permissions.api_route 驱动鉴权。
 */
class AdminBigNumberModuleTest extends TestCase
{
    /**
     * 后台大数据统计 API 必须注册并挂载后台权限中间件。
     *
     * @return void
     */
    public function test_big_number_api_routes_are_registered_with_permission_middleware(): void
    {
        foreach ([
            'admin_api_bigNumberDashboard',
            'admin_api_bigNumberTrend',
        ] as $routeName) {
            $this->assertTrue(Route::has($routeName), $routeName . ' API 路由未注册');
            $this->assertContains('check.permission:admin', Route::getRoutes()->getByName($routeName)->gatherMiddleware());
        }
    }

    /**
     * 后台大数据统计权限必须由迁移写入 permissions 表。
     *
     * @return void
     */
    public function test_big_number_permissions_are_seeded_by_migration(): void
    {
        $migrationPath = database_path('migrations/2026_07_05_000001_add_admin_big_number_permissions.php');

        $this->assertFileExists($migrationPath);

        require_once $migrationPath;
        (new \AddAdminBigNumberPermissions())->up();

        $expectedPermissions = [
            'admin_big_number_dashboard' => 'admin_api_bigNumberDashboard',
            'admin_big_number_trend' => 'admin_api_bigNumberTrend',
        ];

        foreach ($expectedPermissions as $slug => $apiRoute) {
            $permission = DB::table('permissions')->where('slug', $slug)->first();

            $this->assertNotNull($permission, $slug . ' 权限未写入 permissions 表');
            $this->assertSame('admin', $permission->guard_type);
            $this->assertSame($apiRoute, $permission->api_route);
            $this->assertSame(3, (int) $permission->type);
        }
    }

    /**
     * 大数据统计必须按当前 user_trades 的 dateTime 字段统计，不能沿用旧 Unix 时间戳 SQL。
     *
     * @return void
     */
    public function test_big_number_controller_uses_current_user_trade_datetime_fields(): void
    {
        $source = file_get_contents(app_path('Http/Controllers/Admin/BigNumberController.php')) ?: '';

        $this->assertStringNotContainsString('FROM_UNIXTIME', $source);
        $this->assertStringNotContainsString("strtotime('1970-01-02 00:00:00')", $source);
        $this->assertStringContainsString("'1970-01-01 00:00:00'", $source);
    }
}
