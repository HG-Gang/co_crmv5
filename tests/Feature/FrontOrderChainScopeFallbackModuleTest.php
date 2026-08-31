<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/16
 * Time: 02:20
 */

/**
 * 前台已平仓订单列表订单链作用域降级闭合测试。
 *
 * 文件功能：
 * - 验证 GET /api/front/orders/closed 在 agent_descendants 表无数据
 *   （family_tree 缺失）时，降级使用 user_infos.parent_id 树构建订单链 order_chain。
 * - 验证该降级修复已登记在 docs/admin-backend-blade-permission-final-checklist.md（第 178 项）。
 *
 * 适用场景：
 * - 回归 OrderController::orderChain 与 FrontLegacyData::userScopeIds 的
 *   parent_id 降级路径，防止订单链重新依赖可能缺行的 agent_descendants 数据。
 *
 * 入参例子：
 * - 构造 root(411780100) -> sub(411780101) -> customer(411780102) 三级 parent_id 链，
 *   由 root 代理登录后请求 /api/front/orders/closed?orderId={ticket}&per_page=5，
 *   其中 ticket 为 customer 名下的已平仓订单号。
 *
 * 返回值：
 * - 测试无返回值；断言 order_chain 顺序为 [root, sub, customer] 且
 *   user_name 与 fixtures 一致即表示闭环：降级链路按 parent_id 正确还原。
 *
 * 异常或失败场景：
 * - 断言失败意味着作用域降级逻辑失效（order_chain 缺失、顺序错误或为空），
 *   或 checklist 文档未登记该修复，需要立即排查。
 */
namespace Tests\Feature;

use App\Constants\ResponseCode;
use App\Http\Middleware\JwtAuthMiddleware;
use App\Http\Middleware\SingleSignOn;
use App\Models\UserLogin;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class FrontOrderChainScopeFallbackModuleTest extends TestCase
{
    use DatabaseTransactions;

    public function test_order_list_uses_parent_id_tree_for_order_chain_when_family_tree_rows_are_missing(): void
    {
        $rootAgentId = 411780100;
        $subAgentId = $rootAgentId + 1;
        $customerId = $rootAgentId + 2;
        $ticket = $rootAgentId + 900;

        $this->deleteAgentDescendantRows([$rootAgentId, $subAgentId, $customerId]);
        $this->deleteTradeRows([$rootAgentId, $subAgentId, $customerId], [$ticket]);

        $this->insertUserInfo($rootAgentId, 'order-chain-root-agent', 1, 0);
        $this->insertUserInfo($subAgentId, 'order-chain-sub-agent', 1, $rootAgentId);
        $this->insertUserInfo($customerId, 'order-chain-customer', 2, $subAgentId);
        $this->insertClosedTrade($customerId, $ticket);

        $this->assertSame(0, DB::table('agent_descendants')->where('agent_id', $rootAgentId)->count());

        $login = UserLogin::where('user_id', $rootAgentId)->firstOrFail();
        $response = $this->withoutMiddleware([JwtAuthMiddleware::class, SingleSignOn::class])
            ->actingAs($login, 'user')
            ->getJson('/api/front/orders/closed?orderId=' . $ticket . '&per_page=5');

        $response->assertOk()
            ->assertJsonPath('code', ResponseCode::SUCCESS)
            // 旧兼容 JSON 契约中 ticket（MT4 订单号）为字符串（BIGINT + EMULATE_PREPARES）。
            ->assertJsonPath('data.list.data.0.ticket', (string) $ticket)
            // 旧兼容 JSON 契约中 user_id 为字符串（BIGINT + EMULATE_PREPARES）。
            ->assertJsonPath('data.list.data.0.user_id', (string) $customerId);

        $chain = $response->json('data.list.data.0.order_chain');
        $this->assertSame(
            [$rootAgentId, $subAgentId, $customerId],
            array_map('intval', array_column($chain, 'user_id'))
        );
        $this->assertSame('order-chain-root-agent', $chain[0]['user_name']);
        $this->assertSame('order-chain-sub-agent', $chain[1]['user_name']);
        $this->assertSame('order-chain-customer', $chain[2]['user_name']);
    }

    public function test_final_checklist_records_order_chain_scope_fallback(): void
    {
        $checklist = file_get_contents(base_path('docs/admin-backend-blade-permission-final-checklist.md'));

        $this->assertStringContainsString('## 178.', $checklist);
        $this->assertStringContainsString('OrderController::orderChain', $checklist);
        $this->assertStringContainsString('FrontOrderChainScopeFallbackModuleTest', $checklist);
        $this->assertStringContainsString('FrontLegacyData::userScopeIds', $checklist);
        $this->assertStringContainsString('user_infos.parent_id', $checklist);
    }

    private function insertUserInfo(int $userId, string $userName, int $accountType, int $parentId): void
    {
        $now = time();

        DB::table('user_infos')->where('user_id', $userId)->delete();
        DB::table('user_logins')->where('user_id', $userId)->delete();

        $loginId = DB::table('user_logins')->insertGetId([
            'user_id' => $userId,
            'email' => 'front-order-chain-' . $userId . '@example.test',
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
            'phone' => '1787800' . substr((string) $userId, -4),
            'gender' => 1,
            'account_type' => $accountType,
            'parent_id' => $parentId,
            'family_tree' => '999999,' . $userId,
            'group_id' => 0,
            'level_id' => $accountType === 1 ? 2 : 0,
            'comm_rate' => $accountType === 1 ? 0.2 : 0,
            'auth_status' => 1,
            'total_funds' => 0,
            'used_margin' => 0,
            'avail_margin' => 0,
            'equity' => 0,
            'effective_credit' => 0,
            'risk_ratio' => 0,
            'leverage' => 100,
            'created_at' => $now,
            'updated_at' => $now,
            'deleted_at' => null,
        ]);
    }

    private function insertClosedTrade(int $userId, int $ticket): void
    {
        $now = time();

        DB::table('user_trades')->insert([
            'user_id' => $userId,
            'ticket' => $ticket,
            'symbol' => 'XAUUSD',
            'digits' => 2,
            'cmd' => 0,
            'volume' => 10,
            'open_time' => '2026-06-01 09:00:00',
            'open_price' => 2300.12,
            'stop_loss' => 0,
            'take_profit' => 0,
            'close_time' => '2026-06-01 10:00:00',
            'expiration' => null,
            'reason' => 0,
            'conv_rate1' => 1,
            'conv_rate2' => 1,
            'commission' => -3.5,
            'commission_agent' => 12.25,
            'swaps' => 0,
            'close_price' => 2310.12,
            'profit' => 100,
            'taxes' => 0,
            'comment' => 'front order chain scope fallback test',
            'internal_id' => 0,
            'margin_rate' => 1,
            'timestamp_val' => $now,
            'magic' => 0,
            'gw_volume' => 0,
            'gw_open_price' => 0,
            'gw_close_price' => 0,
            'modify_time' => '2026-06-01 10:00:00',
            'settlement_status' => 1,
            'settled_at' => '2026-06-01 10:05:00',
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

    /**
     * @param array<int, int> $userIds
     * @param array<int, int> $tickets
     */
    private function deleteTradeRows(array $userIds, array $tickets): void
    {
        DB::table('user_trades')
            ->whereIn('user_id', $userIds)
            ->orWhereIn('ticket', $tickets)
            ->delete();
    }
}
