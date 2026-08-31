<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/01
 * Time: 03:47
 */

namespace Tests\Feature;

use App\Http\Middleware\CheckPermission;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use ReflectionMethod;
use Tests\TestCase;

/**
 * 受保护后台 API 权限闭环测试。
 *
 * 文件功能：
 * - 枚举所有挂载 `check.permission:admin` 的后台 API 命名路由。
 * - 验证登录后白名单路由被显式分类，其他业务 API 必须存在启用的 `permissions.api_route`。
 * - 验证补权限迁移可幂等恢复软停用权限，避免新增 API 只注册路由却无法给普通角色授权。
 */
class AdminProtectedRoutePermissionClosureModuleTest extends TestCase
{
    use DatabaseTransactions;

    /**
     * 被测迁移文件路径：为受保护的后台路由补齐权限 slug。用例执行迁移后断言权限行与路由保护生效。
     * @var string
     */
    private const MIGRATION = 'migrations/2026_07_19_000001_ensure_protected_admin_route_permissions.php';

    /**
     * 已登录但不需要专项权限的路由白名单（登出、刷新令牌、个人资料等）。
     * 迁移必须为它们兜底授权，否则管理员登录后连基本操作都不可用。
     * @var array<int, string>
     */
    private const AUTHENTICATED_WHITELIST = [
        'admin_api_logout',
        'admin_api_refreshToken',
        'admin_api_menus',
        'admin_api_profileInfo',
        'admin_api_updateProfile',
        'admin_api_changePassword',
        'admin_api_uploadAvatar',
    ];

    public function test_public_and_authenticated_utility_routes_are_explicitly_classified(): void
    {
        $login = Route::getRoutes()->getByName('admin_api_login');
        $this->assertNotNull($login);
        $this->assertNotContains('check.permission:admin', $login->gatherMiddleware());

        $whiteRouteMethod = new ReflectionMethod(CheckPermission::class, 'isPermissionWhiteRoute');
        $whiteRouteMethod->setAccessible(true);
        $middleware = app(CheckPermission::class);

        foreach (self::AUTHENTICATED_WHITELIST as $routeName) {
            $route = Route::getRoutes()->getByName($routeName);
            $this->assertNotNull($route, $routeName . ' is not registered.');
            $this->assertContains('check.permission:admin', $route->gatherMiddleware(), $routeName);
            $this->assertTrue($whiteRouteMethod->invoke($middleware, $routeName), $routeName);
        }
    }

    public function test_permission_tree_compatibility_uri_reuses_the_canonical_permission_route(): void
    {
        $route = collect(Route::getRoutes()->getRoutes())->first(function ($route): bool {
            return $route->uri() === 'api/admin/permissionTree';
        });

        $this->assertNotNull($route);
        $this->assertSame('admin_api_permissionTree', $route->getName());
    }

    public function test_follow_up_migration_closes_every_protected_admin_api_route_permission(): void
    {
        $migration = $this->migration();
        $migration->up();

        $missing = $this->protectedRouteNames()->diff($this->activePermissionRouteNames())->values()->all();

        $this->assertSame([], $missing, 'Protected admin routes missing permissions.api_route: ' . implode(', ', $missing));

        foreach ($this->expectedPermissions() as $expected) {
            $permission = DB::table('permissions')->where('slug', $expected['slug'])->first();
            $this->assertNotNull($permission, $expected['slug']);
            $this->assertSame('admin', (string) $permission->guard_type, $expected['slug']);
            $this->assertSame($expected['api_route'], (string) $permission->api_route, $expected['slug']);
            $this->assertSame($expected['type'], (int) $permission->type, $expected['slug']);
            $this->assertSame($this->parentId($expected['parent']), (int) $permission->parent_id, $expected['slug']);
            $this->assertSame(1, (int) $permission->status, $expected['slug']);
            $this->assertNull($permission->deleted_at, $expected['slug']);
        }
    }

    public function test_follow_up_migration_is_idempotent_and_reactivates_soft_rollback_rows(): void
    {
        $migration = $this->migration();
        $migration->up();
        $firstIds = DB::table('permissions')
            ->whereIn('slug', array_column($this->expectedPermissions(), 'slug'))
            ->pluck('id', 'slug')
            ->map(static function ($id): int {
                return (int) $id;
            })
            ->all();

        $migration->up();
        foreach ($this->expectedPermissions() as $expected) {
            $this->assertSame(1, DB::table('permissions')->where('slug', $expected['slug'])->count());
        }

        $migration->down();
        foreach ($this->expectedPermissions() as $expected) {
            $permission = DB::table('permissions')->where('slug', $expected['slug'])->first();
            $this->assertNotNull($permission, $expected['slug']);
            $this->assertSame(0, (int) $permission->status, $expected['slug']);
            $this->assertNotNull($permission->deleted_at, $expected['slug']);
        }

        $migration->up();
        foreach ($this->expectedPermissions() as $expected) {
            $permission = DB::table('permissions')->where('slug', $expected['slug'])->first();
            $this->assertSame($firstIds[$expected['slug']], (int) $permission->id, $expected['slug']);
            $this->assertSame(1, (int) $permission->status, $expected['slug']);
            $this->assertNull($permission->deleted_at, $expected['slug']);
        }
    }

    private function migration()
    {
        $path = database_path(self::MIGRATION);
        $this->assertFileExists($path);
        require_once $path;

        return new \EnsureProtectedAdminRoutePermissions();
    }

    private function protectedRouteNames()
    {
        return collect(Route::getRoutes()->getRoutes())
            ->filter(static function ($route): bool {
                return in_array('check.permission:admin', $route->gatherMiddleware(), true);
            })
            ->map(static function ($route): ?string {
                return $route->getName();
            })
            ->filter(static function ($name): bool {
                return is_string($name) && strpos($name, 'admin_api_') === 0;
            })
            ->reject(function (string $name): bool {
                return in_array($name, self::AUTHENTICATED_WHITELIST, true);
            })
            ->unique()
            ->sort()
            ->values();
    }

    private function activePermissionRouteNames()
    {
        return DB::table('permissions')
            ->where('guard_type', 'admin')
            ->where('status', 1)
            ->whereNull('deleted_at')
            ->whereNotNull('api_route')
            ->where('api_route', '<>', '')
            ->pluck('api_route')
            ->unique()
            ->sort()
            ->values();
    }

    private function parentId(string $parent): int
    {
        if ($parent === '') {
            return 0;
        }

        if ($parent === '/admin/users') {
            return (int) DB::table('permissions')
                ->where('guard_type', 'admin')
                ->where('status', 1)
                ->whereNull('deleted_at')
                ->where('route', $parent)
                ->orderBy('id')
                ->value('id');
        }

        return (int) DB::table('permissions')
            ->where('guard_type', 'admin')
            ->where('slug', $parent)
            ->value('id');
    }

    /**
     * @return array<int, array{slug:string, api_route:string, parent:string, type:int}>
     */
    private function expectedPermissions(): array
    {
        return [
            ['slug' => 'admin_agent_detail', 'api_route' => 'admin_api_agentDetail', 'parent' => 'admin_agents', 'type' => 3],
            ['slug' => 'admin_agent_parent_path', 'api_route' => 'admin_api_agentParentPath', 'parent' => 'admin_agents', 'type' => 3],
            ['slug' => 'admin_admin_status', 'api_route' => 'admin_api_changeAdminStatus', 'parent' => 'admin_admins', 'type' => 3],
            ['slug' => 'admin_big_agent_status', 'api_route' => 'admin_api_changeBigAgentStatus', 'parent' => 'admin_big_agents', 'type' => 3],
            ['slug' => 'admin_agent_create', 'api_route' => 'admin_api_createAgent', 'parent' => 'admin_agents', 'type' => 3],
            ['slug' => 'admin_permission_create', 'api_route' => 'admin_api_createPermission', 'parent' => 'admin_permissions', 'type' => 3],
            ['slug' => 'admin_production_create', 'api_route' => 'admin_api_createProduction', 'parent' => 'admin_productions', 'type' => 3],
            ['slug' => 'admin_user_create', 'api_route' => 'admin_api_createUser', 'parent' => '/admin/users', 'type' => 3],
            ['slug' => 'admin_menu_delete', 'api_route' => 'admin_api_deleteMenu', 'parent' => 'admin_menus', 'type' => 3],
            ['slug' => 'admin_permission_delete', 'api_route' => 'admin_api_deletePermission', 'parent' => 'admin_permissions', 'type' => 3],
            ['slug' => 'admin_production_delete', 'api_route' => 'admin_api_deleteProduction', 'parent' => 'admin_productions', 'type' => 3],
            ['slug' => 'admin_deposit_flow_list', 'api_route' => 'admin_api_depositFlowList', 'parent' => 'admin_deposits', 'type' => 3],
            ['slug' => 'admin_deposit_flow_export', 'api_route' => 'admin_api_exportDepositFlows', 'parent' => 'admin_deposits', 'type' => 3],
            ['slug' => 'admin_deposit_detail', 'api_route' => 'admin_api_depositDetail', 'parent' => 'admin_deposits', 'type' => 3],
            ['slug' => 'admin_deposit_export', 'api_route' => 'admin_api_exportDeposits', 'parent' => 'admin_deposits', 'type' => 3],
            ['slug' => 'admin_gift_export', 'api_route' => 'admin_api_exportGiftShipments', 'parent' => 'admin_gifts', 'type' => 3],
            ['slug' => 'admin_position_summary_export', 'api_route' => 'admin_api_exportPositionSummary', 'parent' => 'admin_position_summary', 'type' => 3],
            ['slug' => 'admin_production_export', 'api_route' => 'admin_api_exportProductions', 'parent' => 'admin_productions', 'type' => 3],
            ['slug' => 'admin_realtime_commission_export', 'api_route' => 'admin_api_exportRealtimeCommissions', 'parent' => 'admin_realtime_commissions', 'type' => 3],
            ['slug' => 'admin_withdraw_export', 'api_route' => 'admin_api_exportWithdrawals', 'parent' => 'admin_withdrawals', 'type' => 3],
            ['slug' => 'admin_operation_logs', 'api_route' => 'admin_api_operationLogs', 'parent' => '', 'type' => 1],
            ['slug' => 'admin_user_reset_password', 'api_route' => 'admin_api_resetUserPassword', 'parent' => '/admin/users', 'type' => 3],
            ['slug' => 'admin_gift_update_shipment', 'api_route' => 'admin_api_updateGiftShipment', 'parent' => 'admin_gifts', 'type' => 3],
            ['slug' => 'admin_production_update', 'api_route' => 'admin_api_updateProduction', 'parent' => 'admin_productions', 'type' => 3],
            ['slug' => 'admin_user_update', 'api_route' => 'admin_api_updateUser', 'parent' => '/admin/users', 'type' => 3],
            ['slug' => 'admin_upload_file', 'api_route' => 'admin_api_uploadFile', 'parent' => '', 'type' => 3],
            ['slug' => 'admin_user_detail', 'api_route' => 'admin_api_userDetail', 'parent' => '/admin/users', 'type' => 3],
            ['slug' => 'admin_whs_exp_zero_records', 'api_route' => 'admin_api_whsExpZeroRecords', 'parent' => 'admin_page_whs_exp_zero', 'type' => 3],
        ];
    }
}
