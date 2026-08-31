<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/18
 * Time: 11:00
 */

/**
 * AdminLegacyWithdrawStatusPermissionClosureModuleTest
 *
 * 文件功能：
 * - 验证旧后台出金状态路由权限闭环：路由契约精确、匿名请求不进入控制器、普通角色遵守列表/导出边界。
 * - 输入：HTTP 请求与事务回滚的数据库夹具；输出：PHPUnit 断言结果。
 * - 明确不负责：不覆盖真实 MT4 网关与线上支付/出金通道（由网关契约测试锁定）。
 */

namespace Tests\Feature;

use App\Constants\ResponseCode;
use App\Http\Controllers\Admin\LegacyAdminController;
use App\Models\Admin;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Illuminate\Routing\Route as LaravelRoute;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

/**
 * Locks the legacy WithdrawStatusController route and permission boundary.
 */
class AdminLegacyWithdrawStatusPermissionClosureModuleTest extends TestCase
{
    use DatabaseTransactions;

    /**
     * @dataProvider withdrawStatusRouteProvider
     */
    public function test_withdraw_status_route_contract_is_exact(
        string $method,
        string $uri,
        string $name,
        string $permissionRoute
    ): void {
        $routes = $this->routesForUri($uri);

        $this->assertCount(1, $routes, $uri);
        $route = $routes[0];
        $this->assertSame($uri, $route->uri());
        $expectedMethods = $method === 'GET' ? ['GET', 'HEAD'] : ['POST'];
        $this->assertSame($expectedMethods, $route->methods());
        $this->assertSame($name, $route->getName());
        $this->assertSame(LegacyAdminController::class . '@handle', $route->getActionName());
        $this->assertContains('legacy.admin.auth', $route->gatherMiddleware(), $uri);
        $this->assertSame(
            $permissionRoute,
            $route->defaults['legacy_permission_route'] ?? null,
            $uri
        );
    }

    /**
     * @dataProvider withdrawStatusRouteProvider
     */
    public function test_anonymous_withdraw_status_request_never_enters_the_controller(
        string $method,
        string $uri,
        string $name,
        string $permissionRoute
    ): void {
        $probe = $this->installControllerProbe();

        $this->requestJson($method, $uri)
            ->assertUnauthorized()
            ->assertJsonPath('code', ResponseCode::AUTH_FAILED);

        $this->assertSame(0, $probe->calls, $uri);
    }

    /**
     * @dataProvider withdrawStatusRouteProvider
     */
    public function test_ordinary_roles_obey_the_withdraw_status_list_export_boundary(
        string $method,
        string $uri,
        string $name,
        string $permissionRoute
    ): void {
        $listOnly = $this->seedOrdinaryAdmin(994201, 'admin_api_withdrawList');
        $exportOnly = $this->seedOrdinaryAdmin(994202, 'admin_api_exportWithdrawals');
        $this->assertNotSame(1, (int) $listOnly->id);
        $this->assertNotSame('super_admin', (string) $listOnly->role->name);
        $this->assertNotSame(1, (int) $exportOnly->id);
        $this->assertNotSame('super_admin', (string) $exportOnly->role->name);

        $allowedAdmin = $permissionRoute === 'admin_api_exportWithdrawals' ? $exportOnly : $listOnly;
        $deniedAdmin = $permissionRoute === 'admin_api_exportWithdrawals' ? $listOnly : $exportOnly;
        $probe = $this->installControllerProbe();

        $this->actingAs($deniedAdmin, 'admin');
        $this->requestJson($method, $uri)
            ->assertForbidden()
            ->assertJsonPath('code', ResponseCode::PERMISSION_DENIED);
        $this->assertSame(0, $probe->calls, $uri);

        $this->actingAs($allowedAdmin, 'admin');
        $this->requestJson($method, $uri)
            ->assertOk()
            ->assertJsonPath('controller_entered', true);
        $this->assertSame(1, $probe->calls, $uri);
    }

    public static function withdrawStatusRouteProvider(): array
    {
        return [
            'pending page' => ['GET', 'index/admin/withdraw/pending', 'legacy_admin_358028a2f9cb4e45', 'admin_api_withdrawList'],
            'pending search' => ['POST', 'index/admin/withdraw/pendingSearch', 'legacy_admin_5269b1fad284b3ad', 'admin_api_withdrawList'],
            'pending export' => ['POST', 'index/admin/withdraw/pendingExport', 'legacy_admin_fefb71576f8e611e', 'admin_api_exportWithdrawals'],
            'processing page' => ['GET', 'index/admin/withdraw/processing', 'legacy_admin_246fa87280b166f1', 'admin_api_withdrawList'],
            'processing search' => ['POST', 'index/admin/withdraw/processingSearch', 'legacy_admin_e0b482fde40b40bc', 'admin_api_withdrawList'],
            'processing export' => ['POST', 'index/admin/withdraw/processingExport', 'legacy_admin_678e9e7fd0ac873a', 'admin_api_exportWithdrawals'],
            'completed page' => ['GET', 'index/admin/withdraw/completed', 'legacy_admin_e56d812229eddf32', 'admin_api_withdrawList'],
            'completed search' => ['POST', 'index/admin/withdraw/completedSearch', 'legacy_admin_1e10361fc02ab375', 'admin_api_withdrawList'],
            'completed export' => ['POST', 'index/admin/withdraw/completedExport', 'legacy_admin_dd7af3546af3a53e', 'admin_api_exportWithdrawals'],
            'failed page' => ['GET', 'index/admin/withdraw/failed', 'legacy_admin_f97b3c545c41c2dd', 'admin_api_withdrawList'],
            'failed search' => ['POST', 'index/admin/withdraw/failedSearch', 'legacy_admin_26024800fff01d67', 'admin_api_withdrawList'],
            'failed export' => ['POST', 'index/admin/withdraw/failedExport', 'legacy_admin_9fcc2e829e29a151', 'admin_api_exportWithdrawals'],
        ];
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

    private function requestJson(string $method, string $uri)
    {
        if ($method === 'GET') {
            return $this->getJson('/' . $uri);
        }

        return $this->postJson('/' . $uri);
    }

    private function seedOrdinaryAdmin(int $adminId, string $apiRoute): Admin
    {
        $now = time();
        DB::table('roles')->updateOrInsert(
            ['id' => $adminId],
            [
                'name' => 'legacy-withdraw-status-' . $adminId,
                'guard_type' => 'admin',
                'description' => 'Legacy withdraw status permission boundary test role',
                'permissions' => json_encode([]),
                'status' => 1,
                'created_at' => $now,
                'updated_at' => $now,
                'deleted_at' => null,
            ]
        );
        DB::table('role_permissions')->where('role_id', $adminId)->delete();
        DB::table('role_permissions')->insert([
            'role_id' => $adminId,
            'permission_id' => $this->permissionIdForRoute($apiRoute),
            'created_at' => $now,
            'updated_at' => $now,
            'deleted_at' => null,
        ]);
        DB::table('admins')->updateOrInsert(
            ['id' => $adminId],
            [
                'username' => 'legacy_withdraw_status_' . $adminId,
                'email' => 'legacy-withdraw-status-' . $adminId . '@example.test',
                'password' => Hash::make('password'),
                'role_id' => (string) $adminId,
                'status' => 1,
                'created_at' => $now,
                'updated_at' => $now,
                'deleted_at' => null,
            ]
        );

        return Admin::query()->findOrFail($adminId);
    }

    private function permissionIdForRoute(string $apiRoute): int
    {
        $permission = DB::table('permissions')
            ->where('guard_type', 'admin')
            ->where('api_route', $apiRoute)
            ->orderBy('id')
            ->first();

        if ($permission) {
            DB::table('permissions')->where('id', $permission->id)->update([
                'status' => 1,
                'deleted_at' => null,
                'updated_at' => now(),
            ]);

            return (int) $permission->id;
        }

        return (int) DB::table('permissions')->insertGetId([
            'parent_id' => 0,
            'name' => $apiRoute,
            'slug' => 'test_' . md5($apiRoute),
            'api_route' => $apiRoute,
            'route' => '',
            'icon' => '',
            'type' => 3,
            'guard_type' => 'admin',
            'sort' => 1,
            'status' => 1,
            'created_at' => now(),
            'updated_at' => now(),
            'deleted_at' => null,
        ]);
    }

    /**
     * @return array<int, LaravelRoute>
     */
    private function routesForUri(string $uri): array
    {
        return array_values(array_filter(
            Route::getRoutes()->getRoutes(),
            static fn (LaravelRoute $route): bool => trim($route->uri(), '/') === trim($uri, '/')
        ));
    }
}
