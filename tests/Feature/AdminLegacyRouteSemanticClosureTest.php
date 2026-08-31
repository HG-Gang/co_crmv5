<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/17
 * Time: 18:46
 */

/**
 * AdminLegacyRouteSemanticClosureTest
 *
 * 文件功能：
 * - 验证旧后台路由语义闭环：仅旧认证入口公开、匿名变更/下载不渲染页面、会话鉴权旧变更在写目标前被拦、按载荷重算权限语义、未映射 POST 返回 Gone 且无业务写入。
 * - 输入：HTTP 请求与事务回滚的数据库夹具；输出：PHPUnit 断言结果。
 * - 明确不负责：不覆盖真实 MT4 网关与线上支付/出金通道（由网关契约测试锁定）。
 */

namespace Tests\Feature;

use App\Constants\ResponseCode;
use App\Http\Controllers\Admin\LegacyAdminController;
use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\LegacyAdminAuthenticate;
use App\Models\Admin;
use Illuminate\Http\Request;
use Illuminate\Routing\Route as LaravelRoute;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Covers the high-risk legacy admin route semantics without touching database schema.
 */
class AdminLegacyRouteSemanticClosureTest extends TestCase
{
    public function test_only_legacy_auth_entrypoints_are_public(): void
    {
        foreach ([
            'index/admin/login',
            'index/admin/captcha',
            'index/admin/logon',
            'index/admin/logout',
        ] as $uri) {
            foreach ($this->routesForUri($uri) as $route) {
                $this->assertNotContains('legacy.admin.auth', $route->gatherMiddleware(), $uri);
            }
        }

        foreach ([
            'index/admin/role/del',
            'index/admin/Administrators/start',
            'index/admin/bigAgents/stop',
            'index/admin/amount/depositDownloadfile/{file}/{role}',
            'index/admin/withdraw/pendingExport',
        ] as $uri) {
            $routes = $this->routesForUri($uri);
            $this->assertNotSame([], $routes, 'Missing legacy route: ' . $uri);
            foreach ($routes as $route) {
                $this->assertContains('legacy.admin.auth', $route->gatherMiddleware(), $uri);
            }
        }
    }

    public function test_get_mutations_and_downloads_do_not_render_pages_for_anonymous_requests(): void
    {
        $this->getJson('/index/admin/role/del?id=1')
            ->assertStatus(401)
            ->assertJsonPath('code', ResponseCode::AUTH_FAILED);

        $this->getJson('/index/admin/amount/depositDownloadfile/test.csv/admin')
            ->assertStatus(401)
            ->assertJsonPath('code', ResponseCode::AUTH_FAILED);
    }

    public function test_session_authenticated_legacy_mutation_is_blocked_before_modern_write_target(): void
    {
        $admin = new Admin();
        $admin->id = 1;
        $admin->status = 1;

        $response = $this->actingAs($admin, 'admin')
            ->getJson('/index/admin/Administrators/start');

        $response->assertStatus(405);
        $response->assertJsonPath('code', ResponseCode::OPERATION_NOT_ALLOWED);
        $this->assertNotSame(ResponseCode::TOKEN_MISSING, $response->json('code'));
    }

    public function test_legacy_session_permission_uses_internal_modern_target_instead_of_legacy_route_name(): void
    {
        $admin = new Admin();
        $admin->id = 2;
        $admin->status = 1;
        $admin->setRelation('role', null);
        Auth::guard('admin')->setUser($admin);

        $route = new LaravelRoute(
            ['GET'],
            'index/admin/permission-target-probe',
            static function (): void {
            }
        );
        $route->name('legacy_admin_permission_target_probe');
        $route->defaults('legacy_permission_route', 'admin_api_menus');

        $request = Request::create('/index/admin/permission-target-probe', 'GET');
        $request->setRouteResolver(static function () use ($route): LaravelRoute {
            return $route;
        });

        $nextCalled = false;
        $response = app(LegacyAdminAuthenticate::class)->handle(
            $request,
            static function (Request $authorizedRequest) use (&$nextCalled) {
                $nextCalled = true;

                return response()->json([
                    'permission_route_name' => $authorizedRequest->attributes->get('permission_route_name'),
                ]);
            }
        );

        $this->assertTrue($nextCalled);
        $this->assertSame('admin_api_menus', $request->attributes->get('permission_route_name'));
        $this->assertSame('admin_api_menus', $response->getData(true)['permission_route_name'] ?? null);
    }

    public function test_modern_permission_route_name_remains_the_default_without_legacy_override(): void
    {
        $admin = new Admin();
        $admin->id = 2;
        $admin->status = 1;
        $admin->setRelation('role', null);
        Auth::guard('admin')->setUser($admin);

        $route = new LaravelRoute(
            ['GET'],
            'api/admin/permission-target-probe',
            static function (): void {
            }
        );
        $route->name('admin_api_menus');

        $request = Request::create('/api/admin/permission-target-probe', 'GET');
        $request->setRouteResolver(static function () use ($route): LaravelRoute {
            return $route;
        });

        $nextCalled = false;
        app(CheckPermission::class)->handle(
            $request,
            static function () use (&$nextCalled) {
                $nextCalled = true;

                return response()->json();
            },
            'admin'
        );

        $this->assertTrue($nextCalled);
        $this->assertNull($request->attributes->get('permission_route_name'));
    }

    public function test_legacy_mutation_permission_changes_with_review_decision_payload(): void
    {
        $rejectVoucher = Request::create('/index/admin/auth/voucherReviewSave', 'POST', [
            'reviewstatus' => 2,
        ]);
        $this->assertSame(
            'admin_api_voucherReject',
            LegacyAdminController::permissionRouteForLegacyRequest($rejectVoucher)
        );

        $approveCancel = Request::create('/index/admin/cancel/update_cancel', 'POST', [
            'accept_rejection' => 1,
        ]);
        $this->assertSame(
            'admin_api_cancelApplyApprove',
            LegacyAdminController::permissionRouteForLegacyRequest($approveCancel)
        );
    }

    public function test_legacy_order_status_permission_changes_with_status_payload(): void
    {
        foreach ([
            1 => 'admin_api_withdrawProcess',
            2 => 'admin_api_withdrawComplete',
            3 => 'admin_api_withdrawReject',
        ] as $status => $permissionRoute) {
            $request = Request::create('/index/admin/amount/order_status', 'POST', [
                'orderStatus' => $status,
            ]);

            $this->assertSame(
                $permissionRoute,
                LegacyAdminController::permissionRouteForLegacyRequest($request),
                'Unexpected permission route for orderStatus=' . $status
            );
        }
    }

    public function test_legacy_auth_recalculates_order_status_permission_before_checking_it(): void
    {
        $admin = new Admin();
        $admin->id = 2;
        $admin->status = 1;
        Auth::guard('admin')->setUser($admin);

        $permissionProbe = new class extends CheckPermission {
            /**
             * CheckPermission 探针捕获的权限路由名（来自 legacy_permission_route 覆写）。
             * 断言旧路由解析出正确的权限语义而不是凭空猜测。
             * @var string|null
             */
            public $routeName;

            public function handle(Request $request, \Closure $next, $guardType = 'admin', $routeNameOverride = null)
            {
                $this->routeName = $routeNameOverride;

                return response()->json(['permission_route_name' => $routeNameOverride]);
            }
        };
        app()->instance(CheckPermission::class, $permissionProbe);

        $route = new LaravelRoute(
            ['POST'],
            'index/admin/amount/order_status',
            static function (): void {
            }
        );
        $route->defaults('legacy_permission_route', 'admin_api_withdrawComplete');

        $request = Request::create('/index/admin/amount/order_status', 'POST', [
            'orderStatus' => 1,
        ]);
        $request->setRouteResolver(static function () use ($route): LaravelRoute {
            return $route;
        });

        $response = app(LegacyAdminAuthenticate::class)->handle(
            $request,
            static function (): void {
            }
        );

        $this->assertSame('admin_api_withdrawProcess', $permissionProbe->routeName);
        $this->assertSame('admin_api_withdrawProcess', $response->getData(true)['permission_route_name'] ?? null);
    }

    public function test_protected_legacy_pages_carry_their_modern_permission_target(): void
    {
        $expectations = [
            'index/admin/Administrators' => 'admin_api_adminList',
            'index/admin/agents_add' => 'admin_api_agentList',
            'index/admin/agents_examine' => 'admin_api_agentList',
            'index/admin/agents_list' => 'admin_api_agentList',
            'index/admin/cust/list' => 'admin_api_userList',
            'index/admin/amount/withdraw_apply' => 'admin_api_withdrawList',
        ];

        foreach ($expectations as $uri => $permissionRoute) {
            $route = $this->routesForUri($uri)[0] ?? null;
            $this->assertNotNull($route, $uri);
            $this->assertSame(
                $permissionRoute,
                $route->defaults['legacy_permission_route'] ?? null,
                $uri
            );
        }
    }

    public function test_legacy_captcha_and_logout_have_real_responses(): void
    {
        $captcha = $this->get('/index/admin/captcha');
        $captcha->assertOk();
        // 旧项目 Captcha::create('custom_captcha') 返回 PNG，并把一次性校验值写入 Session/Cache；
        // 这里只验证响应类型和非空二进制正文，避免把验证码明文泄露到响应正文。
        $this->assertStringStartsWith('image/png', (string) $captcha->headers->get('content-type'));
        $this->assertNotSame('', $captcha->getContent());
        $this->assertStringNotContainsString('<svg', $captcha->getContent());
        $this->assertStringNotContainsString('data-layui-page="auth/login"', $captcha->getContent());

        $logout = $this->withSession(['legacy_admin_marker' => 'present'])
            ->get('/index/admin/logout');
        $logout->assertRedirect('/admin/login');
        $logout->assertSessionMissing('legacy_admin_marker');
    }

    public function test_unmapped_legacy_post_returns_gone_without_business_write(): void
    {
        DB::flushQueryLog();
        DB::enableQueryLog();

        $request = Request::create('/index/admin/unmapped', 'POST');
        $response = app(LegacyAdminController::class)->handle($request);

        $payload = $response->getData(true);
        $queries = DB::getQueryLog();
        DB::disableQueryLog();
        $this->assertSame(410, $response->getStatusCode());
        $this->assertSame(410, $payload['code'] ?? null);
        $this->assertSame('Legacy admin route has no current target.', $payload['message'] ?? null);
        $this->assertSame('index/admin/unmapped', $payload['data']['legacy_uri'] ?? null);
        $this->assertCount(0, $queries, 'An unmapped legacy write must not touch the database.');
    }

    public function test_unregistered_legacy_admin_uri_remains_a_router_not_found_response(): void
    {
        $this->postJson('/index/admin/unmapped')
            ->assertNotFound();
    }

    public function test_high_risk_legacy_uris_map_to_semantic_targets(): void
    {
        $controller = new LegacyAdminController();
        $method = new \ReflectionMethod($controller, 'targetRouteFor');
        $method->setAccessible(true);

        $expected = [
            'index/admin/role/del' => ['admin_api_deleteRole', []],
            'index/admin/Administrators/del' => ['admin_api_deleteAdmin', []],
            'index/admin/Administrators/start' => ['admin_api_changeAdminStatus', ['status' => 1]],
            'index/admin/Administrators/stop' => ['admin_api_changeAdminStatus', ['status' => 0]],
            'index/admin/bigAgents/del' => ['admin_api_deleteBigAgent', []],
            'index/admin/bigAgents/save' => ['admin_api_createBigAgent', []],
            'index/admin/bigAgents/start' => ['admin_api_changeBigAgentStatus', ['is_enabled' => 1]],
            'index/admin/bigAgents/stop' => ['admin_api_changeBigAgentStatus', ['is_enabled' => 0]],
            'index/admin/bigAgents/updateInfo' => ['admin_api_updateBigAgent', []],
            'index/admin/agents_save' => ['admin_api_createAgent', []],
            'index/admin/send/againSendSms' => ['admin_api_resetUserPassword', []],
            'index/admin/cust/cust_save_add' => ['admin_api_createUser', []],
            'index/admin/cust/cust_save_info' => ['admin_api_updateUser', []],
            'index/admin/amount/depositExport' => ['admin_api_exportDepositFlows', []],
            'index/admin/amount/depositDownloadfile/{file}/{role}' => ['admin_api_exportDepositFlows', []],
            'index/admin/amount/withdrawExport' => ['admin_api_exportWithdrawals', []],
            'index/admin/amount/withdraw_downloadfile/{file}/{role}' => ['admin_api_exportWithdrawals', []],
            'index/admin/amount/withdrawDownloadfile/{file}/{role}' => ['admin_api_exportWithdrawFlows', []],
            'index/admin/amount/rights_downloadfile/{file}/{role}' => ['admin_api_exportRightsSummary', []],
            'index/admin/order/v2/subAgentsListSearchV2' => ['admin_api_positionSummaryList', []],
            'index/admin/withdraw/pendingExport' => ['admin_api_exportWithdrawals', ['status' => 0]],
            'index/admin/withdraw/processingExport' => ['admin_api_exportWithdrawals', ['status' => 1]],
            'index/admin/withdraw/completedExport' => ['admin_api_exportWithdrawals', ['status' => 2]],
            'index/admin/withdraw/failedExport' => ['admin_api_exportWithdrawals', ['status' => 3]],
        ];

        foreach ($expected as $uri => [$route, $defaults]) {
            $actual = $method->invoke($controller, $uri);
            $this->assertIsArray($actual, $uri);
            $this->assertSame($route, $actual['route'] ?? null, $uri);
            $this->assertSame($defaults, $actual['defaults'] ?? [], $uri);
        }
    }

    public function test_new_legacy_action_and_export_targets_are_protected_api_routes(): void
    {
        foreach ([
            'admin_api_changeAdminStatus',
            'admin_api_changeBigAgentStatus',
            'admin_api_createBigAgent',
            'admin_api_createAgent',
            'admin_api_createUser',
            'admin_api_updateBigAgent',
            'admin_api_resetUserPassword',
            'admin_api_exportDeposits',
            'admin_api_exportWithdrawals',
        ] as $routeName) {
            $this->assertTrue(Route::has($routeName), 'Missing target route: ' . $routeName);
            $route = Route::getRoutes()->getByName($routeName);
            $this->assertContains('jwt.auth:admin', $route->gatherMiddleware(), $routeName);
            $this->assertContains('sso:admin', $route->gatherMiddleware(), $routeName);
            $this->assertContains('check.permission:admin', $route->gatherMiddleware(), $routeName);
        }
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
