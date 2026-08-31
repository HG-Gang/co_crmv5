<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/19
 * Time: 00:53
 */

/**
 * AdminLegacyNewsPermissionClosureModuleTest
 *
 * 文件功能：
 * - 验证旧新闻路由权限闭环：路由契约精确、匿名请求与缺少新闻权限的普通管理员在进入控制器前被拒绝。
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

class AdminLegacyNewsPermissionClosureModuleTest extends TestCase
{
    use DatabaseTransactions;

    /**
     * @dataProvider legacyNewsRouteProvider
     */
    public function test_legacy_news_route_contract_is_exact(
        string $method,
        string $uri,
        string $permissionRoute
    ): void {
        $routes = $this->routesForUri($uri);

        $this->assertCount(1, $routes, $uri);
        $route = $routes[0];
        $this->assertSame($method === 'GET' ? ['GET', 'HEAD'] : ['POST'], $route->methods());
        $this->assertSame(
            'legacy_admin_' . substr(md5($uri . '|' . $method), 0, 16),
            $route->getName()
        );
        $this->assertSame(LegacyAdminController::class . '@handle', $route->getActionName());
        $this->assertContains('legacy.admin.auth', $route->gatherMiddleware());
        $this->assertSame($permissionRoute, $route->defaults['legacy_permission_route'] ?? null);
        $this->assertSame($permissionRoute, LegacyAdminController::permissionRouteForLegacyUri($uri));
    }

    /**
     * @dataProvider legacyNewsRouteProvider
     */
    public function test_anonymous_legacy_news_requests_fail_before_the_controller(
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

    /**
     * @dataProvider legacyNewsRouteProvider
     */
    public function test_ordinary_admin_without_required_news_permission_fails_before_the_controller(
        string $method,
        string $uri,
        string $permissionRoute
    ): void {
        $admin = $this->seedOrdinaryAdminWithoutPermissions(998101);
        $probe = $this->installControllerProbe();

        $this->actingAs($admin, 'admin');
        $this->requestJson($method, $uri)
            ->assertOk()
            ->assertJsonPath('code', ResponseCode::PERMISSION_DENIED);

        $this->assertSame(0, $probe->calls, $permissionRoute);
    }

    public static function legacyNewsRouteProvider(): array
    {
        return [
            'list page' => ['GET', 'index/admin/news/news_list_browse', 'admin_api_newsList'],
            'create page' => ['GET', 'index/admin/news/news_add_browse', 'admin_api_createNews'],
            'edit page' => ['GET', 'index/admin/news/news_edit/{newsid}', 'admin_api_updateNews'],
            'search' => ['POST', 'index/admin/news/newsListSearch', 'admin_api_newsList'],
            'save' => ['POST', 'index/admin/news/news_save', 'admin_api_createNews'],
            'update' => ['POST', 'index/admin/news/news_update', 'admin_api_updateNews'],
            'delete' => ['POST', 'index/admin/news/del', 'admin_api_deleteNews'],
        ];
    }

    private function requestJson(string $method, string $uri)
    {
        $uri = str_replace('{newsid}', '1', $uri);

        return $method === 'GET'
            ? $this->getJson('/' . $uri)
            : $this->postJson('/' . $uri);
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
        DB::table('roles')->updateOrInsert(
            ['id' => $adminId],
            [
                'name' => 'legacy-news-denied-' . $adminId,
                'guard_type' => 'admin',
                'description' => 'Legacy news permission boundary test role',
                'permissions' => json_encode([]),
                'status' => 1,
                'created_at' => $now,
                'updated_at' => $now,
                'deleted_at' => null,
            ]
        );
        DB::table('role_permissions')->where('role_id', $adminId)->delete();
        DB::table('admins')->updateOrInsert(
            ['id' => $adminId],
            [
                'username' => 'legacy_news_denied_' . $adminId,
                'email' => 'legacy-news-denied-' . $adminId . '@example.test',
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
