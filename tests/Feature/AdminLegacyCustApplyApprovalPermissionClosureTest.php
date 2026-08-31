<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/15
 * Time: 22:10
 */

/**
 * AdminLegacyCustApplyApprovalPermissionClosureTest
 *
 * 文件功能：
 * - 验证客户转组审批使用专属权限：路由权限精确匹配、迁移幂等且 down 仅移除自身权限、无关权限拒绝写操作。
 * - 输入：HTTP 请求与事务回滚的数据库夹具；输出：PHPUnit 断言结果。
 * - 明确不负责：不覆盖真实 MT4 网关与线上支付/出金通道（由网关契约测试锁定）。
 */

namespace Tests\Feature;

use App\Constants\ResponseCode;
use App\Http\Controllers\Admin\LegacyAdminController;
use App\Models\Admin;
use App\Services\AdminDataScopeService;
use App\Services\Mt4ManagerService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Mockery;
use Tests\TestCase;

/**
 * Customer group-change approval must have a dedicated admin permission.
 */
class AdminLegacyCustApplyApprovalPermissionClosureTest extends TestCase
{
    use DatabaseTransactions;

    public function test_legacy_customer_approval_routes_share_only_the_dedicated_permission(): void
    {
        $routes = [
            LegacyAdminController::permissionRouteForLegacyUri('index/admin/cust/cust_apply_pass'),
            LegacyAdminController::permissionRouteForLegacyUri('index/admin/cust/cust_apply_nopass'),
        ];

        $this->assertSame(['admin_api_customerGroupApproval', 'admin_api_customerGroupApproval'], $routes);
        $this->assertNotContains('admin_api_confirmAgent', $routes);
        $this->assertNotContains('admin_api_rejectAgentConfirmation', $routes);
        $this->assertNotContains('admin_api_updateUser', $routes);
    }

    public function test_customer_approval_permission_migration_is_idempotent_and_down_only_removes_its_permission(): void
    {
        $path = database_path('migrations/2026_08_15_000001_add_admin_customer_group_approval_permission.php');
        $this->assertFileExists($path);

        require_once $path;
        $migration = new \AddAdminCustomerGroupApprovalPermission();
        DB::table('permissions')->updateOrInsert(
            ['slug' => 'admin_customer_group_approval'],
            [
                'name' => 'stale customer approval permission',
                'guard_type' => 'admin',
                'parent_id' => 0,
                'type' => 3,
                'icon' => '',
                'sort' => 1,
                'route' => '',
                'api_route' => 'admin_api_customerGroupApproval',
                'status' => 0,
                'created_at' => now(),
                'updated_at' => now(),
                'deleted_at' => now(),
            ]
        );

        $migration->up();
        $migration->up();

        $this->assertSame(1, DB::table('permissions')->where('slug', 'admin_customer_group_approval')->count());
        $permission = DB::table('permissions')->where('slug', 'admin_customer_group_approval')->first();
        $this->assertSame('admin', $permission->guard_type);
        $this->assertSame(1, (int) $permission->status);
        $this->assertSame('admin_api_customerGroupApproval', $permission->api_route);
        $this->assertNull($permission->deleted_at, 'Re-running the migration must reactivate a soft-deleted permission.');

        $migration->down();
        $this->assertDatabaseMissing('permissions', ['slug' => 'admin_customer_group_approval']);
    }

    /**
     * @dataProvider unrelatedPermissionProvider
     */
    public function test_unrelated_admin_permissions_are_denied_before_customer_approval_writes(string $apiRoute): void
    {
        $admin = $this->seedAdminWithPermission($apiRoute);
        $this->seedPermission('admin_customer_group_approval', 'admin_api_customerGroupApproval');

        $mt4Calls = 0;
        $this->bindMt4(function () use (&$mt4Calls): array {
            $mt4Calls++;

            return ['status' => 'ok', 'err' => 0];
        });

        $offset = array_search($apiRoute, array_keys(static::unrelatedPermissionProvider()), true) * 10;
        foreach (['pass', 'nopass'] as $index => $action) {
            $userId = 988301 + $offset + $index;
            $this->seedCustomerWithPendingApplication($userId);
            $payload = ['uid' => $userId];
            if ($action === 'nopass') {
                $payload['trans_apply_reason'] = 'permission boundary';
            }

            $response = $this->actingAs($admin, 'admin')->postJson(
                '/index/admin/cust/cust_apply_' . $action,
                $payload
            );

            $response->assertStatus(200)->assertJsonPath('code', ResponseCode::PERMISSION_DENIED);
            $this->assertDatabaseHas('user_infos', [
                'user_id' => $userId,
                'group_id' => 701,
                'mt4_group' => 'origin-group',
            ]);
            $this->assertDatabaseHas('trans_apply_logs', ['user_id' => $userId, 'status' => 0]);
            $this->assertDatabaseMissing('operation_logs', ['target_user_id' => $userId]);
        }

        $this->assertSame(0, $mt4Calls);
    }

    public static function unrelatedPermissionProvider(): array
    {
        return [
            'ordinary customer update' => ['admin_api_updateUser'],
            'agent confirmation' => ['admin_api_confirmAgent'],
            'agent rejection' => ['admin_api_rejectAgentConfirmation'],
        ];
    }

    public function test_customer_specific_permission_allows_customer_approval_service(): void
    {
        $admin = $this->seedAdminWithPermission(
            'admin_api_customerGroupApproval',
            'admin_customer_group_approval'
        );
        $userId = 988302;
        $this->seedCustomerWithPendingApplication($userId);
        $this->app->instance(AdminDataScopeService::class, tap(Mockery::mock(AdminDataScopeService::class), function ($scope): void {
            $scope->shouldReceive('canAccessUser')->twice()->andReturn(true);
        }));

        $mt4Calls = [];
        $this->bindMt4(function (int $id, string $group) use (&$mt4Calls): array {
            $mt4Calls[] = [$id, $group];

            return ['status' => 'ok', 'err' => 0];
        });

        $response = $this->actingAs($admin, 'admin')->postJson(
            '/index/admin/cust/cust_apply_pass',
            ['uid' => $userId]
        );

        $response->assertStatus(200)->assertJsonPath('code', ResponseCode::UPDATED);
        $this->assertSame([[$userId, 'target-group']], $mt4Calls);
        $this->assertDatabaseHas('user_infos', [
            'user_id' => $userId,
            'group_id' => 702,
            'mt4_group' => 'target-group',
        ]);
        $this->assertDatabaseHas('trans_apply_logs', ['user_id' => $userId, 'status' => 1]);
        $this->assertDatabaseHas('operation_logs', [
            'target_user_id' => $userId,
            'order_no' => 'legacy_customer_group_approval:' . $userId,
        ]);
    }

    private function seedAdminWithPermission(string $apiRoute, ?string $slug = null): Admin
    {
        $permission = $this->seedPermission($slug ?? ('test_' . md5($apiRoute)), $apiRoute);
        $roleId = DB::table('roles')->insertGetId([
            'name' => 'legacy-customer-approval-' . md5($apiRoute),
            'guard_type' => 'admin',
            'description' => 'test',
            'permissions' => json_encode([]),
            'status' => 1,
            'created_at' => time(),
            'updated_at' => time(),
            'deleted_at' => null,
        ]);
        DB::table('role_permissions')->insert([
            'role_id' => $roleId,
            'permission_id' => $permission->id,
            'created_at' => time(),
            'updated_at' => time(),
            'deleted_at' => null,
        ]);

        $adminId = 990001;
        DB::table('admins')->updateOrInsert(
            ['id' => $adminId],
            [
                'username' => 'legacy-customer-approval-admin',
                'email' => 'legacy-customer-approval-admin@example.test',
                'password' => Hash::make('password'),
                'role_id' => (string) $roleId,
                'status' => 1,
                'created_at' => time(),
                'updated_at' => time(),
                'deleted_at' => null,
            ]
        );

        return Admin::query()->findOrFail($adminId);
    }

    private function seedPermission(string $slug, string $apiRoute): object
    {
        DB::table('permissions')->updateOrInsert(
            ['slug' => $slug],
            [
                'name' => $slug,
                'guard_type' => 'admin',
                'parent_id' => 0,
                'type' => 3,
                'icon' => '',
                'sort' => 1,
                'route' => '',
                'api_route' => $apiRoute,
                'status' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );

        return DB::table('permissions')->where('slug', $slug)->first();
    }

    private function bindMt4(callable $callback): void
    {
        $mock = Mockery::mock(Mt4ManagerService::class);
        $mock->shouldReceive('changeGroup')->zeroOrMoreTimes()->andReturnUsing($callback);
        $this->app->instance(Mt4ManagerService::class, $mock);
    }

    private function seedCustomerWithPendingApplication(int $userId): void
    {
        $now = time();
        DB::table('operation_logs')->where('target_user_id', $userId)->delete();
        DB::table('trans_apply_logs')->where('user_id', $userId)->delete();
        DB::table('user_infos')->where('user_id', $userId)->delete();
        DB::table('user_logins')->where('user_id', $userId)->delete();

        $loginId = DB::table('user_logins')->insertGetId([
            'user_id' => $userId,
            'email' => 'legacy-cust-perm-' . $userId . '@example.test',
            'password' => Hash::make('password'),
            'account_type' => 2,
            'role_id' => 0,
            'is_enabled' => 1,
            'is_cancelled' => 0,
            'source_type' => 0,
            'created_at' => $now,
            'updated_at' => $now,
            'deleted_at' => null,
        ]);

        DB::table('user_infos')->insert([
            'user_id' => $userId,
            'login_id' => $loginId,
            'user_name' => 'Permission boundary customer',
            'phone' => '178000' . substr((string) $userId, -4),
            'account_type' => 2,
            'group_id' => 701,
            'mt4_group' => 'origin-group',
            'parent_id' => 0,
            'family_tree' => (string) $userId,
            'level_id' => 0,
            'auth_status' => 1,
            'is_agent_confirmed' => 7,
            'remark' => 'unchanged',
            'total_funds' => 0,
            'equity' => 0,
            'created_at' => $now,
            'updated_at' => $now,
            'deleted_at' => null,
        ]);

        DB::table('trans_apply_logs')->insert([
            'user_id' => $userId,
            'origin_group_id' => 701,
            'group_id' => 702,
            'group_name' => 'target-group',
            'applicant_id' => $userId,
            'applicant_name' => 'Permission boundary customer',
            'status' => 0,
            'apply_reason' => 'permission boundary',
            'reject_reason' => '',
            'created_by' => 'test',
            'updated_by' => '',
            'created_at' => $now,
            'updated_at' => $now,
            'deleted_at' => null,
        ]);
    }
}
