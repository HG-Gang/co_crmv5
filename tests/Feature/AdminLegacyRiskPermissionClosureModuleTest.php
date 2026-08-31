<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/18
 * Time: 23:16
 */

/**
 * AdminLegacyRiskPermissionClosureModuleTest
 *
 * 文件功能：
 * - 验证旧风控路由权限闭环：现代接口与旧入口使用 admin api 安全边界、路由契约精确、匿名请求与无权限角色被拒。
 * - 输入：HTTP 请求与事务回滚的数据库夹具；输出：PHPUnit 断言结果。
 * - 明确不负责：不覆盖真实 MT4 网关与线上支付/出金通道（由网关契约测试锁定）。
 */

namespace Tests\Feature;

use App\Constants\ResponseCode;
use App\Http\Controllers\Admin\LegacyAdminController;
use App\Http\Controllers\Admin\RiskController;
use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\JwtAuthMiddleware;
use App\Http\Middleware\SingleSignOn;
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
 * Locks the legacy FengXian route, authentication and permission boundary.
 */
class AdminLegacyRiskPermissionClosureModuleTest extends TestCase
{
    use DatabaseTransactions;

    public function test_modern_profit_risk_route_is_real_and_uses_the_admin_api_security_boundary(): void
    {
        $route = Route::getRoutes()->getByName('admin_api_riskProfitUsers');

        $this->assertNotNull($route);
        $this->assertSame('api/admin/riskProfitUsers', $route->uri());
        $this->assertSame(['POST'], $route->methods());
        $this->assertSame(RiskController::class . '@profitableUsers', $route->getActionName());
        $this->assertContains('jwt.auth:admin', $route->gatherMiddleware());
        $this->assertContains('sso:admin', $route->gatherMiddleware());
        $this->assertContains('check.permission:admin', $route->gatherMiddleware());
    }

    public function test_modern_profit_risk_endpoint_returns_the_real_empty_read_model_contract(): void
    {
        $admin = Admin::query()->findOrFail(1);

        $response = $this->withoutMiddleware([
            JwtAuthMiddleware::class,
            SingleSignOn::class,
            CheckPermission::class,
        ])->actingAs($admin, 'admin')->postJson('/api/admin/riskProfitUsers');

        $response->assertOk()
            ->assertJsonPath('code', ResponseCode::SUCCESS)
            ->assertJsonPath('data.records.total', 0)
            ->assertJsonPath('data.records.data', []);
    }

    /**
     * @dataProvider legacyProfitSearchProvider
     */
    public function test_authorized_legacy_profit_search_returns_the_real_empty_contract(string $uri): void
    {
        $admin = Admin::query()->findOrFail(1);

        $response = $this->actingAs($admin, 'admin')->postJson('/' . $uri);

        $response->assertOk();
        if (substr($uri, -2) === 'V2') {
            $response->assertJsonPath('code', 200)
                ->assertJsonPath('count', 0)
                ->assertJsonPath('data', [])
                ->assertJsonPath('totalRow', []);
        } else {
            $response->assertJsonPath('rows', '')
                ->assertJsonPath('total', 0);
        }
    }

    public static function legacyProfitSearchProvider(): array
    {
        return [
            'v1' => ['index/admin/fengXian/profitSearch'],
            'v2' => ['index/admin/fengXian/profitSearchV2'],
        ];
    }

    /**
     * @dataProvider riskRouteProvider
     */
    public function test_risk_route_contract_is_exact(
        string $method,
        string $uri,
        string $name,
        string $permissionRoute
    ): void {
        $routes = $this->routesForUri($uri);

        $this->assertCount(1, $routes, $uri);
        $route = $routes[0];
        $this->assertSame($uri, $route->uri());
        $this->assertSame($method === 'GET' ? ['GET', 'HEAD'] : ['POST'], $route->methods());
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
     * @dataProvider riskRouteProvider
     */
    public function test_anonymous_risk_request_never_enters_the_controller(
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
     * @dataProvider riskRouteProvider
     */
    public function test_ordinary_role_without_the_risk_permission_is_forbidden(
        string $method,
        string $uri,
        string $name,
        string $permissionRoute
    ): void {
        $admin = $this->seedOrdinaryAdminWithoutPermission(995101, $permissionRoute);
        $probe = $this->installControllerProbe();

        $this->actingAs($admin, 'admin');
        $this->requestJson($method, $uri)
            ->assertForbidden()
            ->assertJsonPath('code', ResponseCode::PERMISSION_DENIED);

        $this->assertSame(0, $probe->calls, $uri);
    }

    public static function riskRouteProvider(): array
    {
        return [
            'profit page' => ['GET', 'index/admin/fengXian/profit_list', 'legacy_admin_1820f13a8bd1f4b4', 'admin_api_riskProfitUsers'],
            'profit search v1' => ['POST', 'index/admin/fengXian/profitSearch', 'legacy_admin_37967de6002975f4', 'admin_api_riskProfitUsers'],
            'profit search v2' => ['POST', 'index/admin/fengXian/profitSearchV2', 'legacy_admin_067c5336cc8d7f1f', 'admin_api_riskProfitUsers'],
            'position page' => ['GET', 'index/admin/fengXian/position_list', 'legacy_admin_301b9ab00f34a0af', 'admin_api_riskPositions'],
            'position search v1' => ['POST', 'index/admin/fengXian/positionSearch', 'legacy_admin_2ebb7d8224ca15d3', 'admin_api_riskPositions'],
            'position search v2' => ['POST', 'index/admin/fengXian/positionSearchv2', 'legacy_admin_ef87bc3816cc4e56', 'admin_api_riskPositions'],
            'ip page' => ['GET', 'index/admin/fengXian/Ipaddress_list', 'legacy_admin_c82de95a228251b7', 'admin_api_riskIpList'],
            'ip search' => ['POST', 'index/admin/fengXian/IpaddressSearch', 'legacy_admin_a7247b9891d8643b', 'admin_api_riskIpList'],
            'ip detail' => ['GET', 'index/admin/fengXian/IpaddressDeatail/{idaddr}', 'legacy_admin_40570f7ac589f230', 'admin_api_riskIpDetail'],
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
        $uri = str_replace('{idaddr}', '192_168_1_1', $uri);

        return $method === 'GET'
            ? $this->getJson('/' . $uri)
            : $this->postJson('/' . $uri);
    }

    private function seedOrdinaryAdminWithoutPermission(int $adminId, string $permissionRoute): Admin
    {
        $now = time();
        DB::table('roles')->updateOrInsert(
            ['id' => $adminId],
            [
                'name' => 'legacy-risk-denied-' . $adminId,
                'guard_type' => 'admin',
                'description' => 'Legacy risk permission boundary test role',
                'permissions' => json_encode([]),
                'status' => 1,
                'created_at' => $now,
                'updated_at' => $now,
                'deleted_at' => null,
            ]
        );
        DB::table('role_permissions')->where('role_id', $adminId)->delete();
        $this->ensurePermissionExists($permissionRoute);
        DB::table('admins')->updateOrInsert(
            ['id' => $adminId],
            [
                'username' => 'legacy_risk_denied_' . $adminId,
                'email' => 'legacy-risk-denied-' . $adminId . '@example.test',
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

    private function ensurePermissionExists(string $apiRoute): void
    {
        DB::table('permissions')->updateOrInsert(
            ['guard_type' => 'admin', 'api_route' => $apiRoute],
            [
                'parent_id' => 0,
                'name' => $apiRoute,
                'slug' => 'test_' . md5($apiRoute),
                'route' => '',
                'icon' => '',
                'type' => 3,
                'sort' => 1,
                'status' => 1,
                'created_at' => now(),
                'updated_at' => now(),
                'deleted_at' => null,
            ]
        );
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
