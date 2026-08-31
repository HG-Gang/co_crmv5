<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/17
 * Time: 01:45
 */

/**
 * AdminUserGroupRouteClosureModuleTest
 *
 * 文件功能：
 * - 验证后台用户组兼容接口闭环：8 条旧路由走受保护适配器、页面只读、非法/部分数字路由 id 拒绝、重复与自配对拒绝、默认组不可删除、配对关系双向与删除后原子释放对端。
 * - 输入：HTTP 请求与事务回滚的数据库夹具；输出：PHPUnit 断言结果。
 * - 明确不负责：不覆盖真实 MT4 网关与线上支付/出金通道（由网关契约测试锁定）。
 */

namespace Tests\Feature;

use App\Constants\ResponseCode;
use App\Http\Controllers\Admin\LegacyAdminController;
use App\Http\Controllers\Admin\UserGroupController;
use App\Http\Middleware\LegacyAdminAuthenticate;
use App\Models\Admin;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Events\Dispatcher;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Illuminate\Routing\Route as LaravelRoute;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/** 后台用户组兼容接口路由、参数和删除状态闭环测试。 */
class AdminUserGroupRouteClosureModuleTest extends TestCase
{
    use DatabaseTransactions;

    public function test_user_group_compatibility_routes_are_permission_protected(): void
    {
        foreach ([
            'admin_api_userGroupList',
            'admin_api_createUserGroup',
            'admin_api_updateUserGroup',
            'admin_api_deleteUserGroup',
        ] as $name) {
            $route = Route::getRoutes()->getByName($name);
            $this->assertNotNull($route, $name . ' 未注册。');
            $middleware = $route->gatherMiddleware();
            $this->assertContains('jwt.auth:admin', $middleware);
            $this->assertContains('sso:admin', $middleware);
            $this->assertContains('check.permission:admin', $middleware);
        }
    }

    public function test_all_eight_legacy_user_group_routes_use_the_protected_adapter(): void
    {
        foreach ([
            ['GET', 'index/admin/group/user_group_add'],
            ['GET', 'index/admin/group/user_group_browse'],
            ['POST', 'index/admin/group/user_group_delete'],
            ['GET', 'index/admin/group/user_group_edit/{recId}'],
            ['POST', 'index/admin/group/user_group_search'],
            ['POST', 'index/admin/group/user_group_searchV2'],
            ['POST', 'index/admin/group/user_group_store'],
            ['POST', 'index/admin/group/user_group_update'],
        ] as [$method, $uri]) {
            $routes = array_values(array_filter(
                Route::getRoutes()->getRoutes(),
                static function (LaravelRoute $route) use ($method, $uri): bool {
                    return trim($route->uri(), '/') === $uri
                        && in_array($method, $route->methods(), true);
                }
            ));

            $this->assertNotSame([], $routes, $method . ' ' . $uri);
            foreach ($routes as $route) {
                $this->assertSame(LegacyAdminController::class . '@handle', $route->getActionName());
                $this->assertContains('legacy.admin.auth', $route->gatherMiddleware());
            }
        }
    }

    public function test_legacy_user_group_pages_are_read_only_and_edit_requires_an_existing_group(): void
    {
        $admin = Admin::query()->findOrFail(1);
        $groupId = $this->insertGroup('legacy-user-group-page-' . uniqid(), 0);

        foreach ([
            '/index/admin/group/user_group_add',
            '/index/admin/group/user_group_browse',
            '/index/admin/group/user_group_edit/' . $groupId,
        ] as $url) {
            DB::flushQueryLog();
            DB::enableQueryLog();
            try {
                $response = $this->withoutMiddleware(LegacyAdminAuthenticate::class)
                    ->actingAs($admin, 'admin')
                    ->get($url);
                $queries = DB::getQueryLog();
            } finally {
                DB::disableQueryLog();
            }

            $response->assertOk()->assertViewIs('admin_layui::group-configs.index');
            $this->assertSame([], array_values(array_filter($queries, static function (array $query): bool {
                return preg_match('/^\s*(insert|update|delete|replace)\b/i', (string) ($query['query'] ?? '')) === 1;
            })));
        }

        $this->actingAs($admin, 'admin')
            ->get('/index/admin/group/user_group_edit/' . $groupId . 'abc')
            ->assertNotFound();
        $this->actingAs($admin, 'admin')
            ->get('/index/admin/group/user_group_edit/1999999999')
            ->assertNotFound();
    }

    public function test_update_and_delete_reject_partially_numeric_route_ids_without_writes(): void
    {
        $id = $this->insertGroup('user-group-route-id-' . uniqid(), 0);
        $controller = app(UserGroupController::class);

        $update = $controller->update(Request::create('/api/admin/updateUserGroup/1abc', 'POST', [
            'group_name' => 'must-not-write',
        ]), $id . 'abc')->getData(true);
        $delete = $controller->destroy($id . 'abc')->getData(true);

        $this->assertSame(ResponseCode::VALIDATION_FAILED, $update['code']);
        $this->assertSame(ResponseCode::VALIDATION_FAILED, $delete['code']);
        $this->assertDatabaseHas('group_configs', ['id' => $id]);
        $this->assertDatabaseMissing('group_configs', ['name' => 'must-not-write']);
    }

    public function test_update_rejects_duplicate_or_self_pair_and_default_group_cannot_be_deleted(): void
    {
        $firstId = $this->insertGroup('user-group-first-' . uniqid(), 0);
        $secondName = 'user-group-second-' . uniqid();
        $secondId = $this->insertGroup($secondName, 0);
        $defaultId = $this->insertGroup('user-group-default-' . uniqid(), 1);
        $controller = app(UserGroupController::class);

        $duplicate = $controller->update(Request::create('/api/admin/updateUserGroup/' . $firstId, 'POST', [
            'group_name' => $secondName,
        ]), $firstId)->getData(true);
        $selfPair = $controller->update(Request::create('/api/admin/updateUserGroup/' . $secondId, 'POST', [
            'relation_group_id' => $secondId,
        ]), $secondId)->getData(true);
        $deleteDefault = $controller->destroy($defaultId)->getData(true);

        $this->assertSame(ResponseCode::DATA_ALREADY_EXISTS, $duplicate['code']);
        $this->assertSame(ResponseCode::VALIDATION_FAILED, $selfPair['code']);
        $this->assertSame(ResponseCode::OPERATION_NOT_ALLOWED, $deleteDefault['code']);
        $this->assertDatabaseHas('group_configs', ['id' => $defaultId, 'is_default' => 1]);
    }

    public function test_store_and_update_keep_pair_relationship_reciprocal(): void
    {
        $peerId = $this->insertGroup('user-group-pair-peer-' . uniqid(), 0, 0);
        $controller = app(UserGroupController::class);
        $name = 'user-group-paired-' . uniqid();

        $created = $controller->store(Request::create('/api/admin/createUserGroup', 'POST', [
            'group_name' => $name,
            'group_type' => 2,
            'group_id' => 0,
            'group_enable' => 1,
            'is_default' => 0,
            'is_enc' => 1,
            'relation_group_id' => $peerId,
        ]))->getData(true);

        $this->assertSame(ResponseCode::CREATED, $created['code']);
        $groupId = (int) DB::table('group_configs')->where('name', $name)->value('id');
        $this->assertDatabaseHas('group_configs', ['id' => $groupId, 'pair_id' => $peerId]);
        $this->assertDatabaseHas('group_configs', ['id' => $peerId, 'pair_id' => $groupId]);

        $newPeerId = $this->insertGroup('user-group-new-peer-' . uniqid(), 0, 0);
        $updated = $controller->update(Request::create('/api/admin/updateUserGroup/' . $groupId, 'POST', [
            'relation_group_id' => $newPeerId,
        ]), $groupId)->getData(true);

        $this->assertSame(ResponseCode::UPDATED, $updated['code']);
        $this->assertDatabaseHas('group_configs', ['id' => $peerId, 'pair_id' => null]);
        $this->assertDatabaseHas('group_configs', ['id' => $groupId, 'pair_id' => $newPeerId]);
        $this->assertDatabaseHas('group_configs', ['id' => $newPeerId, 'pair_id' => $groupId]);
    }

    public function test_legacy_store_and_update_map_execution_specific_pair_fields(): void
    {
        $admin = Admin::query()->findOrFail(1);
        $stpPeerId = $this->insertGroup('legacy-user-group-stp-' . uniqid(), 0, 0);
        $name = 'legacy-user-group-ecn-' . uniqid();

        $this->actingAs($admin, 'admin')
            ->postJson('/index/admin/group/user_group_store', [
                'group_name' => $name,
                'group_type' => 2,
                'group_id' => 0,
                'group_enable' => 1,
                'is_default' => 0,
                'is_enc' => 1,
                'add_ecn_grp_id' => 0,
                'add_stp_grp_id' => $stpPeerId,
            ])
            ->assertOk()
            ->assertJsonPath('code', 0)
            ->assertJsonPath('modern_code', ResponseCode::CREATED);

        $groupId = (int) DB::table('group_configs')->where('name', $name)->value('id');
        $this->assertDatabaseHas('group_configs', ['id' => $groupId, 'pair_id' => $stpPeerId]);
        $this->assertDatabaseHas('group_configs', ['id' => $stpPeerId, 'pair_id' => $groupId]);

        $newStpPeerId = $this->insertGroup('legacy-user-group-new-stp-' . uniqid(), 0, 0);
        $this->actingAs($admin, 'admin')
            ->postJson('/index/admin/group/user_group_update', [
                'grp_recId' => $groupId,
                'group_name' => $name,
                'group_type' => 2,
                'group_id' => 0,
                'group_enable' => 1,
                'is_default' => 0,
                'is_enc' => 1,
                'edit_ecn_grp_id' => 0,
                'edit_stp_grp_id' => $newStpPeerId,
            ])
            ->assertOk()
            ->assertJsonPath('code', 0)
            ->assertJsonPath('modern_code', ResponseCode::UPDATED);

        $this->assertDatabaseHas('group_configs', ['id' => $stpPeerId, 'pair_id' => null]);
        $this->assertDatabaseHas('group_configs', ['id' => $groupId, 'pair_id' => $newStpPeerId]);
        $this->assertDatabaseHas('group_configs', ['id' => $newStpPeerId, 'pair_id' => $groupId]);
    }

    public function test_legacy_search_routes_keep_v1_and_v2_table_contracts(): void
    {
        $admin = Admin::query()->findOrFail(1);
        $name = 'legacy-user-group-list-' . uniqid();
        $this->insertGroup($name, 0, 0);

        $v1 = $this->actingAs($admin, 'admin')
            ->postJson('/index/admin/group/user_group_search', ['page' => 1, 'rows' => 100])
            ->assertOk();
        $this->assertIsArray($v1->json('rows'));
        $this->assertGreaterThan(0, (int) $v1->json('total'));
        $this->assertContains($name, array_column($v1->json('rows'), 'user_group_name'));

        $v2 = $this->actingAs($admin, 'admin')
            ->postJson('/index/admin/group/user_group_searchV2', ['page' => 1, 'limit' => 100])
            ->assertOk()
            ->assertJsonPath('code', 200)
            ->assertJsonStructure(['code', 'msg', 'count', 'data', 'totalRow']);
        $this->assertContains($name, array_column($v2->json('data'), 'user_group_name'));
    }

    public function test_group_with_members_cannot_be_renamed_or_deleted(): void
    {
        $groupName = 'member-user-group-' . uniqid();
        $groupId = $this->insertGroup($groupName, 0);
        $userId = random_int(1200000000, 1900000000);
        DB::table('user_infos')->insert([
            'user_id' => $userId,
            'login_id' => 0,
            'user_name' => 'User group member',
            'account_type' => 2,
            'group_id' => $groupId,
            'parent_id' => 0,
            'family_tree' => (string) $userId,
            'mt4_group' => $groupName,
            'created_at' => time(),
            'updated_at' => time(),
        ]);
        $controller = app(UserGroupController::class);

        $rename = $controller->update(Request::create('/api/admin/updateUserGroup/' . $groupId, 'POST', [
            'group_name' => $groupName . '-renamed',
        ]), $groupId)->getData(true);
        $delete = $controller->destroy($groupId)->getData(true);

        $this->assertSame(ResponseCode::OPERATION_NOT_ALLOWED, $rename['code']);
        $this->assertSame(ResponseCode::OPERATION_NOT_ALLOWED, $delete['code']);
        $this->assertDatabaseHas('group_configs', ['id' => $groupId, 'name' => $groupName]);
    }

    public function test_destroy_rechecks_members_after_locking_the_group(): void
    {
        $groupName = 'locked-member-user-group-' . uniqid();
        $groupId = $this->insertGroup($groupName, 0);
        $injected = false;
        $connection = DB::connection();
        $originalDispatcher = $connection->getEventDispatcher();
        $testDispatcher = new Dispatcher($this->app);
        $testDispatcher->listen(QueryExecuted::class, function (QueryExecuted $query) use ($groupId, $groupName, &$injected): void {
            if (!$injected && $this->isTargetGroupLockQuery($query, $groupId)) {
                $injected = true;
                $userId = random_int(1200000000, 1900000000);
                DB::table('user_infos')->insert([
                    'user_id' => $userId,
                    'login_id' => 0,
                    'user_name' => 'Locked user group member',
                    'account_type' => 2,
                    'group_id' => $groupId,
                    'parent_id' => 0,
                    'family_tree' => (string) $userId,
                    'mt4_group' => $groupName,
                    'created_at' => time(),
                    'updated_at' => time(),
                ]);
            }
        });
        $connection->setEventDispatcher($testDispatcher);

        try {
            $payload = app(UserGroupController::class)->destroy($groupId)->getData(true);
        } finally {
            if ($originalDispatcher) {
                $connection->setEventDispatcher($originalDispatcher);
            } else {
                $connection->unsetEventDispatcher();
            }
        }

        $this->assertTrue($injected);
        $this->assertSame($originalDispatcher, $connection->getEventDispatcher());
        $this->assertSame(ResponseCode::OPERATION_NOT_ALLOWED, $payload['code']);
        $this->assertDatabaseHas('group_configs', ['id' => $groupId, 'deleted_at' => null]);
    }

    public function test_deleting_unreferenced_non_default_group_releases_peer_atomically(): void
    {
        $groupId = $this->insertGroup('delete-paired-user-group-' . uniqid(), 0, 1);
        $peerId = $this->insertGroup('delete-paired-peer-' . uniqid(), 0, 0);
        DB::table('group_configs')->where('id', $groupId)->update(['pair_id' => $peerId]);
        DB::table('group_configs')->where('id', $peerId)->update(['pair_id' => $groupId]);

        $payload = app(UserGroupController::class)->destroy($groupId)->getData(true);

        $this->assertSame(ResponseCode::DELETED, $payload['code']);
        $this->assertSoftDeleted('group_configs', ['id' => $groupId]);
        $this->assertDatabaseHas('group_configs', ['id' => $peerId, 'pair_id' => null]);
    }

    private function insertGroup(string $name, int $isDefault, int $isEcn = 0): int
    {
        $now = time();
        return (int) DB::table('group_configs')->insertGetId([
            'name' => $name,
            'radix' => 50,
            'category' => 2,
            'has_commission' => 0,
            'is_enabled' => 1,
            'is_ecn' => $isEcn,
            'is_default' => $isDefault,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    private function isTargetGroupLockQuery(QueryExecuted $query, int $groupId): bool
    {
        return strpos(strtolower($query->sql), 'group_configs') !== false
            && strpos(strtolower($query->sql), 'for update') !== false
            && in_array($groupId, array_map('intval', $query->bindings), true);
    }
}
