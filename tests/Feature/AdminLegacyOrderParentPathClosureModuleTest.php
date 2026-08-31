<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/16
 * Time: 02:22
 */

/**
 * AdminLegacyOrderParentPathClosureModuleTest
 *
 * 文件功能：
 * - 验证旧后台持仓汇总代理链路路由闭环：沿 parent_id 上溯后自上而下输出着色链路、user_id/userId 别名、缺失用户失败关闭、现代接口忽略过期家谱并转义 HTML。
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
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * 后台遗留"持仓汇总-代理链路"路由 order/v2/parentPath 闭环测试。
 *
 * 文件目的：
 * - 锁定旧后台 PositionSummaryController@parentPathV2 的迁移行为：沿 parent_id 向上
 *   追溯到根节点后按"根->…->目标"自上而下输出 HTML 链路（分等级着色 + lay-event 回调）。
 * - 旧请求字段 user_id（兼容 userId 别名）与 event_name 在新端 admin_api_agentParentPath
 *   上原样保留，保证旧 Layui 页面零改造可用。
 * - family_tree 快照优先、HTML 转义与参数校验为防回归边界。
 */
class AdminLegacyOrderParentPathClosureModuleTest extends TestCase
{
    use DatabaseTransactions;

    public function test_legacy_order_v2_parent_path_resolves_upward_chain_top_down(): void
    {
        $admin = Admin::query()->findOrFail(1);
        $this->fixtureChain();

        $response = $this->actingAs($admin, 'admin')
            ->postJson('/index/admin/order/v2/parentPath', [
                'user_id' => 985903,
                'event_name' => 'returnPreLevel',
            ])
            ->assertOk()
            ->assertJsonPath('code', 200)
            ->assertJsonPath('msg', 'SUCCESS');

        $tree = $response->json('data.tree');
        $this->assertSame(3, count($tree));
        $this->assertStringContainsString('Legacy Root Agent[985901]', $tree[0]);
        $this->assertStringContainsString('Legacy Child Agent[985902]', $tree[1]);
        $this->assertStringContainsString('Legacy Target Customer[985903]', $tree[2]);

        $this->assertStringContainsString('#FFD700', $tree[0]);
        $this->assertStringContainsString('#E8B923', $tree[1]);
        $this->assertStringContainsString('#6B7280', $tree[2]);

        foreach ($tree as $span) {
            $this->assertStringContainsString('lay-event="returnPreLevel"', $span);
            $this->assertStringContainsString('data-user_id', $span);
        }

        $this->assertSame(implode('->', $tree), $response->json('data.path'));
    }

    public function test_legacy_order_v2_parent_path_accepts_userId_field_alias(): void
    {
        $admin = Admin::query()->findOrFail(1);
        $this->fixtureChain();

        $response = $this->actingAs($admin, 'admin')
            ->postJson('/index/admin/order/v2/parentPath', ['userId' => 985903]);
        $response->assertOk()->assertJsonPath('code', 200);

        $tree = $response->json('data.tree');
        $this->assertStringContainsString('Legacy Root Agent[985901]', $tree[0]);
        $this->assertStringContainsString('lay-event="returnPreLevel"', $tree[0]);
        $this->assertStringContainsString('Legacy Target Customer[985903]', $tree[2]);
    }

    public function test_legacy_order_v2_parent_path_missing_user_id_fails_closed(): void
    {
        $admin = Admin::query()->findOrFail(1);

        $this->actingAs($admin, 'admin')
            ->postJson('/index/admin/order/v2/parentPath', [])
            ->assertOk()
            ->assertJsonPath('code', ResponseCode::VALIDATION_FAILED);
    }

    public function test_modern_api_ignores_stale_family_tree_and_uses_current_parent_chain(): void
    {
        $admin = $this->ensureSuperAdmin();
        $this->fixtureSnapshot(985906, '985999,985906');

        $response = $this->withoutMiddleware([AdminAuthenticate::class, JwtAuthMiddleware::class, SingleSignOn::class, CheckPermission::class])
            ->actingAs($admin, 'admin')
            ->post('/api/admin/agentParentPath', ['user_id' => 985906]);

        $response->assertOk()
            ->assertJsonPath('code', 200)
            ->assertJsonPath('msg', 'SUCCESS');

        $tree = $response->json('data.tree');
        $this->assertSame(3, count($tree));
        $this->assertStringContainsString('Snapshot Root Agent[985904]', $tree[0]);
        $this->assertStringContainsString('Snapshot Mid Agent[985905]', $tree[1]);
        $this->assertStringContainsString('Snapshot Target Agent[985906]', $tree[2]);
        foreach ($tree as $span) {
            $this->assertStringContainsString('#9CA3AF', $span);
            $this->assertStringContainsString('lay-event="returnPreLevel"', $span);
        }
    }

    public function test_modern_api_rejects_unknown_user(): void
    {
        $admin = $this->ensureSuperAdmin();

        $this->withoutMiddleware([AdminAuthenticate::class, JwtAuthMiddleware::class, SingleSignOn::class, CheckPermission::class])
            ->actingAs($admin, 'admin')
            ->post('/api/admin/agentParentPath', ['user_id' => 985999])
            ->assertOk()
            ->assertJsonPath('code', ResponseCode::USER_NOT_FOUND);
    }

    public function test_modern_api_escapes_html_special_chars_in_user_names(): void
    {
        $admin = $this->ensureSuperAdmin();
        $now = time();
        $userId = 985907;

        DB::table('user_infos')->where('user_id', $userId)->delete();
        DB::table('user_logins')->where('user_id', $userId)->delete();

        $loginId = DB::table('user_logins')->insertGetId([
            'user_id' => $userId,
            'email' => 'legacy-parent-path-' . $userId . '@example.test',
            'password' => bcrypt('password'),
            'account_type' => 2,
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
            'user_name' => 'A<B>C & D',
            'phone' => '178000' . substr((string) $userId, -4),
            'account_type' => 2,
            'parent_id' => 0,
            'group_id' => 7,
            'level_id' => 0,
            'comm_rate' => 0,
            'auth_status' => 1,
            'total_funds' => 0,
            'equity' => 0,
            'created_at' => $now,
            'updated_at' => $now,
            'deleted_at' => null,
        ]);

        $response = $this->withoutMiddleware([AdminAuthenticate::class, JwtAuthMiddleware::class, SingleSignOn::class, CheckPermission::class])
            ->actingAs($admin, 'admin')
            ->post('/api/admin/agentParentPath', ['user_id' => $userId]);

        $response->assertOk()->assertJsonPath('code', 200);

        $this->assertStringContainsString('A&lt;B&gt;C &amp; D[985907]', $response->json('data.path'));
        $this->assertStringNotContainsString('A<B>C', $response->json('data.path'));
    }

    private function ensureSuperAdmin(): Admin
    {
        $now = time();

        DB::table('admins')->updateOrInsert(
            ['id' => 1],
            [
                'username' => 'legacy-parent-path-admin',
                'email' => 'legacy-parent-path-admin@example.test',
                'password' => bcrypt('password'),
                'status' => 1,
                'created_at' => $now,
                'updated_at' => $now,
                'deleted_at' => null,
            ]
        );

        return Admin::query()->findOrFail(1);
    }

    private function fixtureChain(): void
    {
        $rows = [
            [985901, 'Legacy Root Agent', 1, 0, 1],
            [985902, 'Legacy Child Agent', 1, 985901, 2],
            [985903, 'Legacy Target Customer', 2, 985902, 7],
        ];

        foreach ($rows as [$userId, $userName, $accountType, $parentId, $groupId]) {
            $this->upsertUserInfo($userId, $userName, $accountType, $parentId, $groupId);
        }
    }

    private function fixtureSnapshot(int $targetUserId, string $familyTree): void
    {
        $this->upsertUserInfo(985904, 'Snapshot Root Agent', 1, 0, 99);
        $this->upsertUserInfo(985905, 'Snapshot Mid Agent', 1, 985904, 99);
        $this->upsertUserInfo($targetUserId, 'Snapshot Target Agent', 1, 985905, 99);

        DB::table('user_infos')
            ->where('user_id', $targetUserId)
            ->update(['family_tree' => $familyTree]);
    }

    private function upsertUserInfo(int $userId, string $userName, int $accountType, int $parentId, int $groupId): void
    {
        $now = time();

        DB::table('user_infos')->where('user_id', $userId)->delete();
        DB::table('user_logins')->where('user_id', $userId)->delete();

        $loginId = DB::table('user_logins')->insertGetId([
            'user_id' => $userId,
            'email' => 'legacy-parent-path-' . $userId . '@example.test',
            'password' => bcrypt('password'),
            'account_type' => $accountType,
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
            'user_name' => $userName,
            'phone' => '178000' . substr((string) $userId, -4),
            'account_type' => $accountType,
            'parent_id' => $parentId,
            'group_id' => $groupId,
            'level_id' => $accountType === 1 ? 2 : 0,
            'comm_rate' => $accountType === 1 ? 0.2 : 0,
            'auth_status' => 1,
            'total_funds' => 0,
            'equity' => 0,
            'created_at' => $now,
            'updated_at' => $now,
            'deleted_at' => null,
        ]);
    }
}
