<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/16
 * Time: 02:32
 */

/**
 * 后台代理后代模块测试。
 *
 * 文件功能：
 * - 验证 agentDescendants 接口在 agent_descendants 闭包表无数据时，回退使用 user_infos.parent_id 递归拼装后代树。
 * - 验证存在闭包表数据时按闭包表返回并规范化为前端表格字段（agent_id、descendant_id、is_direct、depth 等）。
 *
 * 适用场景：
 * - 后台代理管理模块后代列表接口的闭包表回退与字段规范化回归测试。
 *
 * 入参例子：
 * - POST /api/admin/agentDescendants
 *   {
 *     "agent_id": 985801
 *   }
 *
 * 方法功能：
 * - test_agent_descendants_endpoint_falls_back_to_user_parent_tree_when_closure_rows_are_missing：无闭包表数据时断言按 parent_id 树返回全部后代。
 * - test_agent_descendants_endpoint_normalizes_existing_closure_rows_for_frontend_tables：有闭包表数据时断言字段被规范化为前端格式。
 *
 * 返回值：
 * - 接口成功返回 code=SUCCESS，data 为后代列表；断言失败时抛出 PHPUnit 断言异常。
 *
 * 异常或失败场景：
 * - 若回退逻辑漏掉某层后代或字段未规范化，测试断言失败。
 */

namespace Tests\Feature;

use App\Constants\ResponseCode;
use App\Http\Middleware\AdminAuthenticate;
use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\JwtAuthMiddleware;
use App\Http\Middleware\SingleSignOn;
use App\Models\Admin;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AdminAgentDescendantsModuleTest extends TestCase
{
    use DatabaseTransactions;

    /**
     * 闭包表无数据时：断言接口回退 user_infos.parent_id 树并返回全部后代（含孙级）。
     *
     * @return void
     */
    public function test_agent_descendants_endpoint_falls_back_to_user_parent_tree_when_closure_rows_are_missing(): void
    {
        $admin = $this->ensureSuperAdmin();
        $rootAgentId = 985801;
        $childAgentId = 985802;
        $childCustomerId = 985803;
        $grandchildAgentId = 985804;

        $this->deleteAgentDescendantRows([$rootAgentId, $childAgentId, $childCustomerId, $grandchildAgentId]);
        $this->upsertUserInfo($rootAgentId, 'Desc Root Agent', 1, 0);
        $this->upsertUserInfo($childAgentId, 'Desc Child Agent', 1, $rootAgentId);
        $this->upsertUserInfo($childCustomerId, 'Desc Child Customer', 2, $rootAgentId);
        $this->upsertUserInfo($grandchildAgentId, 'Desc Grandchild Agent', 1, $childAgentId);

        $this->assertSame(
            0,
            DB::table('agent_descendants')->where('agent_id', $rootAgentId)->count(),
            'The fixture must prove fallback from user_infos.parent_id instead of closure-table rows.'
        );

        $response = $this->withoutMiddleware([AdminAuthenticate::class, JwtAuthMiddleware::class, SingleSignOn::class, CheckPermission::class])
            ->actingAs($admin, 'admin')
            ->post('/api/admin/agentDescendants', ['agent_id' => $rootAgentId]);

        $response->assertOk();
        $response->assertJsonPath('code', ResponseCode::SUCCESS);

        $rows = collect($response->json('data'))->keyBy(function (array $row) {
            return (int) $row['user_id'];
        });

        $this->assertSame([$childAgentId, $childCustomerId, $grandchildAgentId], $rows->keys()->sort()->values()->all());
        $this->assertDescendantRow($rows, $rootAgentId, $childAgentId, 'Desc Child Agent', 1, $rootAgentId, 1, 1);
        $this->assertDescendantRow($rows, $rootAgentId, $childCustomerId, 'Desc Child Customer', 2, $rootAgentId, 1, 1);
        $this->assertDescendantRow($rows, $rootAgentId, $grandchildAgentId, 'Desc Grandchild Agent', 1, $childAgentId, 0, 2);
    }

    /**
     * 存在闭包表数据时：断言接口按闭包表返回并规范化为前端表格字段。
     *
     * @return void
     */
    public function test_agent_descendants_endpoint_normalizes_existing_closure_rows_for_frontend_tables(): void
    {
        $admin = $this->ensureSuperAdmin();
        $rootAgentId = 985811;
        $childCustomerId = 985812;
        $outsideCustomerId = 985813;
        $now = time();

        $this->deleteAgentDescendantRows([$rootAgentId, $childCustomerId, $outsideCustomerId]);
        $this->upsertUserInfo($rootAgentId, 'Closure Root Agent', 1, 0);
        $this->upsertUserInfo($childCustomerId, 'Closure Child Customer', 2, $rootAgentId);
        $this->upsertUserInfo($outsideCustomerId, 'Stale Outside Customer', 2, 0);

        DB::table('agent_descendants')->insert([
            [
                'agent_id' => $rootAgentId,
                'descendant_id' => $childCustomerId,
                'descendant_type' => 2,
                'is_direct' => 1,
                'depth' => 1,
                'created_at' => $now,
                'updated_at' => $now,
                'deleted_at' => null,
            ],
            [
                'agent_id' => $rootAgentId,
                'descendant_id' => $outsideCustomerId,
                'descendant_type' => 2,
                'is_direct' => 1,
                'depth' => 1,
                'created_at' => $now,
                'updated_at' => $now,
                'deleted_at' => null,
            ],
        ]);

        $response = $this->withoutMiddleware([AdminAuthenticate::class, JwtAuthMiddleware::class, SingleSignOn::class, CheckPermission::class])
            ->actingAs($admin, 'admin')
            ->post('/api/admin/agentDescendants', ['agent_id' => $rootAgentId]);

        $response->assertOk();
        $response->assertJsonPath('code', ResponseCode::SUCCESS);

        $this->assertCount(1, $response->json('data'));
        $this->assertSame($childCustomerId, (int) $response->json('data.0.user_id'));
        $this->assertSame('Closure Child Customer', $response->json('data.0.user_name'));
        $this->assertSame($rootAgentId, (int) $response->json('data.0.agent_id'));
        $this->assertSame($childCustomerId, (int) $response->json('data.0.descendant_id'));
        $this->assertSame(2, (int) $response->json('data.0.descendant_type'));
        $this->assertSame(1, (int) $response->json('data.0.is_direct'));
        $this->assertSame(1, (int) $response->json('data.0.depth'));
    }

    private function ensureSuperAdmin(): Admin
    {
        $now = time();

        DB::table('admins')->updateOrInsert(
            ['id' => 1],
            [
                'username' => 'agent-descendants-admin',
                'email' => 'agent-descendants-admin@example.test',
                'password' => bcrypt('password'),
                'status' => 1,
                'created_at' => $now,
                'updated_at' => $now,
                'deleted_at' => null,
            ]
        );

        return Admin::query()->findOrFail(1);
    }

    private function upsertUserInfo(int $userId, string $userName, int $accountType, int $parentId): void
    {
        $now = time();

        DB::table('user_infos')->where('user_id', $userId)->delete();
        DB::table('user_logins')->where('user_id', $userId)->delete();

        $loginId = DB::table('user_logins')->insertGetId([
            'user_id' => $userId,
            'email' => 'agent-descendants-' . $userId . '@example.test',
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
            'phone' => '1780000' . substr((string) $userId, -4),
            'account_type' => $accountType,
            'parent_id' => $parentId,
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

    /**
     * @param array<int, int> $userIds
     */
    private function deleteAgentDescendantRows(array $userIds): void
    {
        DB::table('agent_descendants')
            ->whereIn('agent_id', $userIds)
            ->orWhereIn('descendant_id', $userIds)
            ->delete();
    }

    private function assertDescendantRow(
        Collection $rows,
        int $agentId,
        int $userId,
        string $userName,
        int $accountType,
        int $parentId,
        int $isDirect,
        int $depth
    ): void {
        $this->assertTrue($rows->has($userId), 'Missing descendant row for user_id ' . $userId);

        $row = $rows->get($userId);
        $this->assertSame($agentId, (int) $row['agent_id']);
        $this->assertSame($userId, (int) $row['user_id']);
        $this->assertSame($userId, (int) $row['descendant_id']);
        $this->assertSame($userName, $row['user_name']);
        $this->assertSame($accountType, (int) $row['account_type']);
        $this->assertSame($parentId, (int) $row['parent_id']);
        $this->assertSame($accountType, (int) $row['descendant_type']);
        $this->assertSame($isDirect, (int) $row['is_direct']);
        $this->assertSame($depth, (int) $row['depth']);
    }
}
