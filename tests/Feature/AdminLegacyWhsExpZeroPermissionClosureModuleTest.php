<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/19
 * Time: 13:33
 */

/**
 * AdminLegacyWhsExpZeroPermissionClosureModuleTest
 *
 * 文件功能：
 * - 验证旧仓位清零路由权限闭环：路由契约精确、匿名请求与缺少权限的管理员在控制器前被拒、损坏的 whstest 动作保持显式禁用。
 * - 输入：HTTP 请求与事务回滚的数据库夹具；输出：PHPUnit 断言结果。
 * - 明确不负责：不覆盖真实 MT4 网关与线上支付/出金通道（由网关契约测试锁定）。
 */

namespace Tests\Feature;

use App\Constants\ResponseCode;
use App\Http\Controllers\Admin\LegacyAdminController;
use App\Http\Controllers\Front\LegacyMaintenanceController;
use App\Models\Admin;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Illuminate\Routing\Route as LaravelRoute;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

class AdminLegacyWhsExpZeroPermissionClosureModuleTest extends TestCase
{
    use DatabaseTransactions;

    /** @dataProvider legacyAdminRouteProvider */
    public function test_legacy_whs_admin_route_contract_is_exact(
        string $method,
        string $uri,
        string $permissionRoute
    ): void {
        $routes = $this->routesForUri($uri);

        $this->assertCount(1, $routes, $uri);
        $route = $routes[0];
        $this->assertSame($method === 'GET' ? ['GET', 'HEAD'] : ['POST'], $route->methods());
        $this->assertSame('legacy_admin_' . substr(md5($uri . '|' . $method), 0, 16), $route->getName());
        $this->assertSame(LegacyAdminController::class . '@handle', $route->getActionName());
        $this->assertContains('legacy.admin.auth', $route->gatherMiddleware());
        $this->assertSame($permissionRoute, $route->defaults['legacy_permission_route'] ?? null);
        $this->assertSame($permissionRoute, LegacyAdminController::permissionRouteForLegacyUri($uri));
    }

    /** @dataProvider legacyAdminRouteProvider */
    public function test_anonymous_whs_admin_request_fails_before_controller(
        string $method,
        string $uri,
        string $permissionRoute
    ): void {
        $probe = $this->installControllerProbe();

        $this->requestJson($method, $uri)
            ->assertUnauthorized()
            ->assertJsonPath('code', ResponseCode::AUTH_FAILED);

        $this->assertSame(0, $probe->calls, $permissionRoute);
    }

    /** @dataProvider legacyAdminRouteProvider */
    public function test_admin_without_required_whs_permission_fails_before_controller(
        string $method,
        string $uri,
        string $permissionRoute
    ): void {
        $admin = $this->seedOrdinaryAdminWithoutPermissions(998301);
        $probe = $this->installControllerProbe();

        $this->actingAs($admin, 'admin');
        $this->requestJson($method, $uri)
            ->assertOk()
            ->assertJsonPath('code', ResponseCode::PERMISSION_DENIED);

        $this->assertSame(0, $probe->calls, $permissionRoute);
    }

    public function test_broken_whstest_action_remains_explicitly_disabled(): void
    {
        $route = Route::getRoutes()->getByName('legacy_whs_test');

        $this->assertNotNull($route);
        $this->assertSame(['GET', 'HEAD'], $route->methods());
        $this->assertSame('whstest', $route->uri());
        $this->assertSame(LegacyMaintenanceController::class . '@whsTest', $route->getActionName());

        $this->getJson('/whstest')
            ->assertStatus(423)
            ->assertJsonPath('code', ResponseCode::OPERATION_NOT_ALLOWED)
            ->assertJsonPath('data.legacy_action', 'whsTest');
    }

    public static function legacyAdminRouteProvider(): array
    {
        return [
            'page' => ['GET', 'index/admin/order/whs_exp_zero_list', 'admin_api_whsExpZeroList'],
            'scan mutation' => ['POST', 'index/admin/order/oneKeySearch', 'admin_api_whsExpZero'],
            'clear mutation' => ['POST', 'index/admin/order/oneKeyZero', 'admin_api_whsExpZero'],
            'record search v1' => ['POST', 'index/admin/order/whsExpZeroListSearch', 'admin_api_whsExpZeroRecords'],
            'record search v2' => ['POST', 'index/admin/order/whsExpZeroListSearchV2', 'admin_api_whsExpZeroRecords'],
        ];
    }

    private function requestJson(string $method, string $uri)
    {
        return $method === 'GET' ? $this->getJson('/' . $uri) : $this->postJson('/' . $uri);
    }

    private function installControllerProbe(): LegacyAdminController
    {
        $probe = new class extends LegacyAdminController {
            public int $calls = 0;

            public function handle(Request $request): Response
            {
                $this->calls++;

                return response()->json(['controller_entered' => true]);
            }
        };
        $this->app->instance(LegacyAdminController::class, $probe);

        return $probe;
    }

    private function seedOrdinaryAdminWithoutPermissions(int $adminId): Admin
    {
        $now = time();
        DB::table('roles')->updateOrInsert(['id' => $adminId], [
            'name' => 'legacy-whs-denied-' . $adminId,
            'guard_type' => 'admin',
            'description' => 'Legacy WHS permission boundary test role',
            'permissions' => json_encode([]),
            'status' => 1,
            'created_at' => $now,
            'updated_at' => $now,
            'deleted_at' => null,
        ]);
        DB::table('role_permissions')->where('role_id', $adminId)->delete();
        DB::table('admins')->updateOrInsert(['id' => $adminId], [
            'username' => 'legacy_whs_denied_' . $adminId,
            'email' => 'legacy-whs-denied-' . $adminId . '@example.test',
            'password' => Hash::make('password'),
            'role_id' => (string) $adminId,
            'status' => 1,
            'created_at' => $now,
            'updated_at' => $now,
            'deleted_at' => null,
        ]);

        return Admin::query()->findOrFail($adminId);
    }

    /** @return array<int, LaravelRoute> */
    private function routesForUri(string $uri): array
    {
        return array_values(array_filter(
            Route::getRoutes()->getRoutes(),
            static fn (LaravelRoute $route): bool => trim($route->uri(), '/') === trim($uri, '/')
        ));
    }
}
