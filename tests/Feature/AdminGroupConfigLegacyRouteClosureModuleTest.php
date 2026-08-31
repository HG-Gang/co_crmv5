<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/12
 * Time: 13:09
 */

/**
 * AdminGroupConfigLegacyRouteClosureModuleTest
 *
 * 文件功能：
 * - 验证分组配置旧路由等价闭环：v3 字段映射与对侧执行组配对、双向重绑与释放旧对端、仅列出可用对侧组、被 MT4 组或成员引用的组禁止改名/删除、软删组名可复用与锁后复查。
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
use App\Models\GroupConfig;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Events\Dispatcher;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AdminGroupConfigLegacyRouteClosureModuleTest extends TestCase
{
    use DatabaseTransactions;

    public function test_legacy_group_store_maps_all_v3_fields_and_pairs_opposite_execution_groups(): void
    {
        $admin = Admin::query()->findOrFail(1);
        $peerId = $this->insertGroup('legacy-group-peer-' . uniqid(), 2, 0, 0);
        $name = 'legacy-group-created-' . uniqid();

        $this->actingAs($admin, 'admin')
            ->postJson('/index/admin/group/store', [
                'name' => $name,
                'radix' => '37.5',
                'type' => '1',
                'comm_mode' => '1',
                'is_enabled' => '1',
                'is_ecn' => '1',
                'is_default' => '0',
                'bind_id' => (string) $peerId,
            ])
            ->assertOk()
            ->assertJsonPath('code', ResponseCode::CREATED);

        $createdId = (int) DB::table('group_configs')->where('name', $name)->value('id');
        $this->assertGreaterThan(0, $createdId);
        $this->assertDatabaseHas('group_configs', [
            'id' => $createdId,
            'radix' => 37.5,
            'category' => 1,
            'has_commission' => 1,
            'is_enabled' => 1,
            'is_ecn' => 1,
            'is_default' => 0,
            'pair_id' => $peerId,
        ]);
        $this->assertDatabaseHas('group_configs', [
            'id' => $peerId,
            'pair_id' => $createdId,
        ]);
    }

    public function test_legacy_group_update_rebinds_both_sides_and_releases_old_peer(): void
    {
        $admin = Admin::query()->findOrFail(1);
        $groupId = $this->insertGroup('legacy-group-update-' . uniqid(), 1, 1, 1);
        $oldPeerId = $this->insertGroup('legacy-group-old-peer-' . uniqid(), 1, 0, 0);
        $newPeerId = $this->insertGroup('legacy-group-new-peer-' . uniqid(), 1, 0, 0);
        DB::table('group_configs')->where('id', $groupId)->update(['pair_id' => $oldPeerId]);
        DB::table('group_configs')->where('id', $oldPeerId)->update(['pair_id' => $groupId]);

        $newName = 'legacy-group-updated-' . uniqid();
        $this->actingAs($admin, 'admin')
            ->postJson('/index/admin/group/update', [
                'id' => (string) $groupId,
                'name' => $newName,
                'radix' => '42',
                'type' => '2',
                'comm_mode' => '0',
                'is_enabled' => '1',
                'is_ecn' => '1',
                'is_default' => '0',
                'bind_id' => (string) $newPeerId,
            ])
            ->assertOk()
            ->assertJsonPath('code', ResponseCode::UPDATED);

        $this->assertDatabaseHas('group_configs', [
            'id' => $groupId,
            'name' => $newName,
            'radix' => 42,
            'category' => 2,
            'has_commission' => 0,
            'is_ecn' => 1,
            'pair_id' => $newPeerId,
        ]);
        $this->assertDatabaseHas('group_configs', ['id' => $oldPeerId, 'pair_id' => null]);
        $this->assertDatabaseHas('group_configs', ['id' => $newPeerId, 'pair_id' => $groupId]);
    }

    public function test_legacy_pair_select_only_lists_available_opposite_execution_groups(): void
    {
        $admin = Admin::query()->findOrFail(1);
        $selfId = $this->insertGroup('legacy-pair-self-' . uniqid(), 1, 1, 0);
        $availableName = 'legacy-pair-available-' . uniqid();
        $availableId = $this->insertGroup($availableName, 1, 0, 0);
        $sameTypeName = 'legacy-pair-same-' . uniqid();
        $this->insertGroup($sameTypeName, 1, 1, 0);
        $occupiedName = 'legacy-pair-occupied-' . uniqid();
        $occupiedId = $this->insertGroup($occupiedName, 1, 0, 0);
        DB::table('group_configs')->where('id', $occupiedId)->update(['pair_id' => 999999]);

        $response = $this->actingAs($admin, 'admin')
            ->get('/index/admin/group/pairSelect?is_ecn=1&self_id=' . $selfId)
            ->assertOk();

        $response->assertSee('<option value="' . $availableId . '">' . $availableName . '</option>', false);
        $response->assertDontSee($sameTypeName, false);
        $response->assertDontSee($occupiedName, false);
    }

    public function test_modern_update_without_pair_field_preserves_existing_pair(): void
    {
        $admin = Admin::query()->findOrFail(1);
        $groupId = $this->insertGroup('modern-group-update-' . uniqid(), 1, 1, 0);
        $peerId = $this->insertGroup('modern-group-peer-' . uniqid(), 1, 0, 0);
        DB::table('group_configs')->where('id', $groupId)->update(['pair_id' => $peerId]);
        DB::table('group_configs')->where('id', $peerId)->update(['pair_id' => $groupId]);

        $this->withoutMiddleware([
            AdminAuthenticate::class,
            JwtAuthMiddleware::class,
            SingleSignOn::class,
            CheckPermission::class,
        ])->actingAs($admin, 'admin')
            ->postJson('/api/admin/updateGroupConfig/' . $groupId, [
                'name' => 'modern-group-updated-' . uniqid(),
                'radix' => 55,
                'category' => 1,
                'has_commission' => 0,
                'is_enabled' => 1,
                'is_ecn' => 1,
                'is_default' => 0,
            ])
            ->assertOk()
            ->assertJsonPath('code', ResponseCode::UPDATED);

        $this->assertDatabaseHas('group_configs', ['id' => $groupId, 'pair_id' => $peerId]);
        $this->assertDatabaseHas('group_configs', ['id' => $peerId, 'pair_id' => $groupId]);
    }

    public function test_modern_update_rejects_renaming_a_group_referenced_by_mt4_group(): void
    {
        $admin = Admin::query()->findOrFail(1);
        $originalName = 'modern-group-member-name-' . uniqid();
        $groupId = $this->insertGroup($originalName, 2, 0, 0);
        $this->insertUserInfo(0, $originalName);

        $this->withoutAdminApiMiddleware()
            ->actingAs($admin, 'admin')
            ->postJson('/api/admin/updateGroupConfig/' . $groupId, [
                'name' => 'modern-group-renamed-' . uniqid(),
                'radix' => 50,
                'category' => 2,
                'has_commission' => 0,
                'is_enabled' => 1,
                'is_ecn' => 0,
                'is_default' => 0,
            ])
            ->assertOk()
            ->assertJsonPath('code', ResponseCode::OPERATION_NOT_ALLOWED);

        $this->assertDatabaseHas('group_configs', ['id' => $groupId, 'name' => $originalName]);
    }

    public function test_legacy_update_rejects_renaming_a_group_referenced_by_group_id(): void
    {
        $admin = Admin::query()->findOrFail(1);
        $originalName = 'legacy-group-member-name-' . uniqid();
        $groupId = $this->insertGroup($originalName, 2, 0, 0);
        $this->insertUserInfo($groupId, '');

        $this->actingAs($admin, 'admin')
            ->postJson('/index/admin/group/update', [
                'id' => (string) $groupId,
                'name' => 'legacy-group-renamed-' . uniqid(),
                'radix' => '50',
                'type' => '2',
                'comm_mode' => '0',
                'is_enabled' => '1',
                'is_ecn' => '0',
                'is_default' => '0',
            ])
            ->assertOk()
            ->assertJsonPath('code', ResponseCode::OPERATION_NOT_ALLOWED);

        $this->assertDatabaseHas('group_configs', ['id' => $groupId, 'name' => $originalName]);
    }

    public function test_modern_destroy_rejects_a_group_with_members(): void
    {
        $admin = Admin::query()->findOrFail(1);
        $groupId = $this->insertGroup('modern-group-member-delete-' . uniqid(), 2, 0, 0);
        $this->insertUserInfo($groupId, '');

        $this->withoutAdminApiMiddleware()
            ->actingAs($admin, 'admin')
            ->postJson('/api/admin/deleteGroupConfig/' . $groupId)
            ->assertOk()
            ->assertJsonPath('code', ResponseCode::OPERATION_NOT_ALLOWED);

        $this->assertDatabaseHas('group_configs', ['id' => $groupId, 'deleted_at' => null]);
    }

    public function test_modern_destroy_rejects_a_group_referenced_by_mt4_group(): void
    {
        $admin = Admin::query()->findOrFail(1);
        $groupName = 'modern-mt4-member-delete-' . uniqid();
        $groupId = $this->insertGroup($groupName, 2, 0, 0);
        $this->insertUserInfo(0, $groupName);

        $this->withoutAdminApiMiddleware()
            ->actingAs($admin, 'admin')
            ->postJson('/api/admin/deleteGroupConfig/' . $groupId)
            ->assertOk()
            ->assertJsonPath('code', ResponseCode::OPERATION_NOT_ALLOWED);

        $this->assertDatabaseHas('group_configs', ['id' => $groupId, 'deleted_at' => null]);
    }

    public function test_modern_destroy_rejects_a_default_group(): void
    {
        $admin = Admin::query()->findOrFail(1);
        $groupId = $this->insertGroup('modern-default-group-delete-' . uniqid(), 2, 0, 0);
        DB::table('group_configs')->where('id', $groupId)->update(['is_default' => 1]);

        $this->withoutAdminApiMiddleware()
            ->actingAs($admin, 'admin')
            ->postJson('/api/admin/deleteGroupConfig/' . $groupId)
            ->assertOk()
            ->assertJsonPath('code', ResponseCode::OPERATION_NOT_ALLOWED);

        $this->assertDatabaseHas('group_configs', ['id' => $groupId, 'deleted_at' => null]);
    }

    public function test_modern_destroy_unbinds_the_peer_before_deleting_the_group(): void
    {
        $admin = Admin::query()->findOrFail(1);
        $groupId = $this->insertGroup('modern-group-delete-' . uniqid(), 2, 1, 0);
        $peerId = $this->insertGroup('modern-group-delete-peer-' . uniqid(), 2, 0, 0);
        DB::table('group_configs')->where('id', $groupId)->update(['pair_id' => $peerId]);
        DB::table('group_configs')->where('id', $peerId)->update(['pair_id' => $groupId]);

        $this->withoutAdminApiMiddleware()
            ->actingAs($admin, 'admin')
            ->postJson('/api/admin/deleteGroupConfig/' . $groupId)
            ->assertOk()
            ->assertJsonPath('code', ResponseCode::DELETED);

        $this->assertSoftDeleted('group_configs', ['id' => $groupId]);
        $this->assertDatabaseHas('group_configs', ['id' => $peerId, 'pair_id' => null]);
    }

    public function test_modern_destroy_unbinds_soft_deleted_peer_and_reverse_reference(): void
    {
        $admin = Admin::query()->findOrFail(1);
        $groupId = $this->insertGroup('modern-group-soft-delete-' . uniqid(), 2, 1, 0);
        $peerId = $this->insertGroup('modern-group-soft-peer-' . uniqid(), 2, 0, 0);
        $reverseId = $this->insertGroup('modern-group-soft-reverse-' . uniqid(), 2, 0, 0);
        DB::table('group_configs')->where('id', $groupId)->update(['pair_id' => $peerId]);
        DB::table('group_configs')->whereIn('id', [$peerId, $reverseId])->update(['pair_id' => $groupId]);
        GroupConfig::query()->whereIn('id', [$peerId, $reverseId])->get()->each->delete();

        $this->withoutAdminApiMiddleware()
            ->actingAs($admin, 'admin')
            ->postJson('/api/admin/deleteGroupConfig/' . $groupId)
            ->assertOk()
            ->assertJsonPath('code', ResponseCode::DELETED);

        GroupConfig::withTrashed()->whereIn('id', [$peerId, $reverseId])->restore();
        $this->assertDatabaseHas('group_configs', ['id' => $peerId, 'pair_id' => null, 'deleted_at' => null]);
        $this->assertDatabaseHas('group_configs', ['id' => $reverseId, 'pair_id' => null, 'deleted_at' => null]);
    }

    public function test_modern_store_can_reuse_a_soft_deleted_group_name(): void
    {
        $admin = Admin::query()->findOrFail(1);
        $name = 'modern-soft-deleted-name-' . uniqid();
        $deletedId = $this->insertGroup($name, 2, 0, 0);
        GroupConfig::query()->findOrFail($deletedId)->delete();

        $this->withoutAdminApiMiddleware()
            ->actingAs($admin, 'admin')
            ->postJson('/api/admin/createGroupConfig', [
                'name' => $name,
                'radix' => 50,
                'category' => 2,
                'has_commission' => 0,
                'is_enabled' => 1,
                'is_ecn' => 0,
                'is_default' => 0,
            ])
            ->assertOk()
            ->assertJsonPath('code', ResponseCode::CREATED);

        $this->assertSame(1, GroupConfig::query()->where('name', $name)->count());
    }

    public function test_modern_update_rechecks_members_after_locking_the_group(): void
    {
        $admin = Admin::query()->findOrFail(1);
        $originalName = 'modern-lock-member-name-' . uniqid();
        $groupId = $this->insertGroup($originalName, 2, 0, 0);
        $injected = false;
        $connection = DB::connection();
        $originalDispatcher = $connection->getEventDispatcher();
        $testDispatcher = new Dispatcher($this->app);
        $testDispatcher->listen(QueryExecuted::class, function (QueryExecuted $query) use ($groupId, &$injected): void {
            if (!$injected && $this->isTargetGroupLockQuery($query, $groupId)) {
                $injected = true;
                $this->insertUserInfo($groupId, '');
            }
        });
        $connection->setEventDispatcher($testDispatcher);

        try {
            $this->withoutAdminApiMiddleware()
                ->actingAs($admin, 'admin')
                ->postJson('/api/admin/updateGroupConfig/' . $groupId, [
                    'name' => 'modern-lock-member-renamed-' . uniqid(),
                    'radix' => 50,
                    'category' => 2,
                    'has_commission' => 0,
                    'is_enabled' => 1,
                    'is_ecn' => 0,
                    'is_default' => 0,
                ])
                ->assertOk()
                ->assertJsonPath('code', ResponseCode::OPERATION_NOT_ALLOWED);
        } finally {
            if ($originalDispatcher) {
                $connection->setEventDispatcher($originalDispatcher);
            } else {
                $connection->unsetEventDispatcher();
            }
        }

        $this->assertTrue($injected);
        $this->assertSame($originalDispatcher, $connection->getEventDispatcher());
        $this->assertDatabaseHas('group_configs', ['id' => $groupId, 'name' => $originalName]);
    }

    private function withoutAdminApiMiddleware(): self
    {
        return $this->withoutMiddleware([
            AdminAuthenticate::class,
            JwtAuthMiddleware::class,
            SingleSignOn::class,
            CheckPermission::class,
        ]);
    }

    private function insertUserInfo(int $groupId, string $mt4Group): void
    {
        do {
            $userId = random_int(1200000000, 1900000000);
        } while (DB::table('user_infos')->where('user_id', $userId)->exists());

        $now = time();
        DB::table('user_infos')->insert([
            'user_id' => $userId,
            'login_id' => 0,
            'user_name' => 'Group config member fixture',
            'group_id' => $groupId,
            'account_type' => 2,
            'parent_id' => 0,
            'family_tree' => (string) $userId,
            'mt4_group' => $mt4Group,
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

    private function insertGroup(string $name, int $category, int $isEcn, int $hasCommission): int
    {
        $now = time();

        return (int) DB::table('group_configs')->insertGetId([
            'name' => $name,
            'radix' => 50,
            'category' => $category,
            'has_commission' => $hasCommission,
            'is_enabled' => 1,
            'is_ecn' => $isEcn,
            'is_default' => 0,
            'pair_id' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }
}
