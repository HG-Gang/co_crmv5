<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/17
 * Time: 22:29
 */

/**
 * FrontBigNumberOrderScopeBatchClosureModuleTest
 *
 * 文件功能：
 * - 验证大代理订单批量范围闭环：任一配置根非法即失败关闭、根去重并返回代理客户并集、代理环拒绝、128 层后代接受而 129 层拒绝、深层多根仅一次 user_info 查询。
 * - 输入：HTTP 请求与事务回滚的数据库夹具；输出：PHPUnit 断言结果。
 * - 明确不负责：不覆盖真实 MT4 网关与线上支付/出金通道（由网关契约测试锁定）。
 */

namespace Tests\Feature;

use App\Http\Middleware\JwtAuthMiddleware;
use App\Http\Middleware\SingleSignOn;
use App\Support\FrontLegacyData;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class FrontBigNumberOrderScopeBatchClosureModuleTest extends TestCase
{
    use DatabaseTransactions;

    public function test_strict_batch_scope_fails_closed_when_any_configured_root_is_invalid(): void
    {
        [$validRootId, $validCustomerId, $nonAgentRootId, $softDeletedRootId, $missingRootId]
            = $this->unusedUserIds(5);

        $this->insertUser($validRootId, 1, 0);
        $this->insertUser($validCustomerId, 2, $validRootId);
        $this->insertUser($nonAgentRootId, 2, 0);
        $this->insertUser($softDeletedRootId, 1, 0, time());

        foreach ([
            'missing root' => $missingRootId,
            'soft-deleted root' => $softDeletedRootId,
            'non-agent root' => $nonAgentRootId,
        ] as $label => $invalidRootId) {
            $this->assertNull(
                FrontLegacyData::strictAgentNetworkIdsOrNull([$validRootId, $invalidRootId]),
                $label . ' must invalidate the complete configured root set.'
            );
        }
    }

    public function test_strict_batch_scope_deduplicates_roots_and_returns_agent_customer_unions(): void
    {
        [$rootId, $childAgentId, $customerId, $secondRootId, $secondCustomerId] = $this->unusedUserIds(5);
        $this->insertUser($rootId, 1, 0);
        $this->insertUser($childAgentId, 1, $rootId);
        $this->insertUser($customerId, 2, $childAgentId);
        $this->insertUser($secondRootId, 1, 0);
        $this->insertUser($secondCustomerId, 2, $secondRootId);

        DB::flushQueryLog();
        DB::enableQueryLog();
        try {
            $scope = FrontLegacyData::strictAgentNetworkIdsOrNull([
                $rootId,
                $rootId,
                $secondRootId,
                $secondRootId,
            ]);
            $queries = DB::getQueryLog();
        } finally {
            DB::disableQueryLog();
        }

        $this->assertSame([$rootId, $childAgentId, $secondRootId], $scope['agent_ids'] ?? null);
        $this->assertSame([$customerId, $secondCustomerId], $scope['customer_ids'] ?? null);
        $this->assertCount(1, $this->userInfoQueries($queries));
    }

    public function test_strict_batch_scope_rejects_agent_cycles(): void
    {
        [$rootId, $childAgentId] = $this->unusedUserIds(2);
        $this->insertUser($rootId, 1, $childAgentId);
        $this->insertUser($childAgentId, 1, $rootId);

        $this->assertNull(FrontLegacyData::strictAgentNetworkIdsOrNull([$rootId]));
    }

    public function test_strict_batch_scope_accepts_128_descendant_levels_and_rejects_129(): void
    {
        $ids = $this->unusedUserIds(259);
        $acceptedIds = array_slice($ids, 0, 129);
        $rejectedIds = array_slice($ids, 129, 130);

        $this->insertChain($acceptedIds, true);
        $this->insertChain($rejectedIds, false);

        $accepted = FrontLegacyData::strictAgentNetworkIdsOrNull([$acceptedIds[0]]);
        $this->assertSame(array_slice($acceptedIds, 0, 128), $accepted['agent_ids'] ?? null);
        $this->assertSame([$acceptedIds[128]], $accepted['customer_ids'] ?? null);
        $this->assertNull(FrontLegacyData::strictAgentNetworkIdsOrNull([$rejectedIds[0]]));
    }

    public function test_open_order_scope_uses_one_user_info_query_for_deep_duplicate_multi_roots(): void
    {
        $ids = $this->unusedUserIds(131);
        $deepChainIds = array_slice($ids, 0, 129);
        $secondRootId = $ids[129];
        $secondCustomerId = $ids[130];
        $deepCustomerId = $deepChainIds[128];
        $bigAgentId = $this->unusedBigAgentId();
        $tickets = [random_int(800000000, 899999998), random_int(900000000, 999999998)];

        $this->insertChain($deepChainIds, true);
        $this->insertUser($secondRootId, 1, 0);
        $this->insertUser($secondCustomerId, 2, $secondRootId);
        $this->insertBigAgent($bigAgentId, [
            $deepChainIds[0],
            $deepChainIds[0],
            $secondRootId,
            $secondRootId,
        ]);
        $this->insertOpenTrade($deepCustomerId, $tickets[0]);
        $this->insertOpenTrade($secondCustomerId, $tickets[1]);

        DB::flushQueryLog();
        DB::enableQueryLog();
        try {
            $response = $this->withoutMiddleware([JwtAuthMiddleware::class, SingleSignOn::class])
                ->withSession(['bigAgents' => ['id' => $bigAgentId]])
                ->postJson('/user/agents/open/openOrderSearch', ['limit' => 20]);
            $queries = DB::getQueryLog();
        } finally {
            DB::disableQueryLog();
        }

        $response->assertOk()->assertJsonPath('total', 2);
        $this->assertCount(1, $this->userInfoQueries($queries));
    }

    /** @param array<int, int> $ids */
    private function insertChain(array $ids, bool $customerLeaf): void
    {
        $parentId = 0;
        $lastIndex = count($ids) - 1;
        foreach ($ids as $index => $userId) {
            $accountType = $customerLeaf && $index === $lastIndex ? 2 : 1;
            $this->insertUser($userId, $accountType, $parentId);
            $parentId = $userId;
        }
    }

    private function insertUser(int $userId, int $accountType, int $parentId, ?int $deletedAt = null): void
    {
        $now = time();
        DB::table('user_infos')->insert([
            'user_id' => $userId,
            'login_id' => 0,
            'user_name' => 'Big order batch ' . $userId,
            'account_type' => $accountType,
            'parent_id' => $parentId,
            'family_tree' => '',
            'created_at' => $now,
            'updated_at' => $now,
            'deleted_at' => $deletedAt,
        ]);
    }

    /** @param array<int, int> $rootIds */
    private function insertBigAgent(int $bigAgentId, array $rootIds): void
    {
        $now = time();
        DB::table('big_agents')->insert([
            'id' => $bigAgentId,
            'email' => 'big-order-batch-' . $bigAgentId . '@example.test',
            'username' => 'big-order-batch-' . $bigAgentId,
            'password' => 'not-used',
            'sub_agent_ids' => implode(',', $rootIds),
            'is_enabled' => 1,
            'jwt_token_id' => '',
            'created_by' => 'phpunit',
            'created_at' => $now,
            'updated_at' => $now,
            'deleted_at' => null,
        ]);
    }

    private function insertOpenTrade(int $userId, int $ticket): void
    {
        $now = time();
        DB::table('user_trades')->insert([
            'user_id' => $userId,
            'ticket' => $ticket,
            'symbol' => 'XAUUSD',
            'digits' => 2,
            'cmd' => 0,
            'volume' => 100,
            'open_time' => '2026-08-17 10:00:00',
            'open_price' => 2300,
            'stop_loss' => 0,
            'take_profit' => 0,
            'close_time' => '1970-01-01 00:00:00',
            'expiration' => null,
            'reason' => 0,
            'conv_rate1' => 1,
            'conv_rate2' => 1,
            'commission' => 0,
            'commission_agent' => 0,
            'swaps' => 0,
            'close_price' => 0,
            'profit' => 0,
            'taxes' => 0,
            'comment' => 'big order batch query fixture',
            'internal_id' => 0,
            'margin_rate' => 1,
            'timestamp_val' => $now,
            'magic' => 0,
            'gw_volume' => 0,
            'gw_open_price' => 0,
            'gw_close_price' => 0,
            'modify_time' => '2026-08-17 10:00:00',
            'settlement_status' => 0,
            'settled_at' => null,
            'created_at' => $now,
            'updated_at' => $now,
            'deleted_at' => null,
        ]);
    }

    /** @return array<int, int> */
    private function unusedUserIds(int $count): array
    {
        for ($attempt = 0; $attempt < 50; $attempt++) {
            $start = random_int(1500000000, 1900000000 - $count);
            $ids = range($start, $start + $count - 1);
            if (!DB::table('user_infos')->whereIn('user_id', $ids)->exists()) {
                return $ids;
            }
        }

        throw new \RuntimeException('Unable to allocate BigNumber batch scope fixture user IDs.');
    }

    private function unusedBigAgentId(): int
    {
        for ($attempt = 0; $attempt < 50; $attempt++) {
            $id = random_int(6000000, 6999999);
            if (!DB::table('big_agents')->where('id', $id)->exists()) {
                return $id;
            }
        }

        throw new \RuntimeException('Unable to allocate a BigNumber batch scope fixture ID.');
    }

    /**
     * @param array<int, array{query:string}> $queries
     * @return array<int, array{query:string}>
     */
    private function userInfoQueries(array $queries): array
    {
        return array_values(array_filter($queries, static function (array $query): bool {
            return stripos((string) ($query['query'] ?? ''), 'user_infos') !== false;
        }));
    }
}
