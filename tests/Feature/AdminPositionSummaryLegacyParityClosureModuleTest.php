<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/21
 * Time: 22:34
 */

declare(strict_types=1);

namespace Tests\Feature;

use App\Constants\ResponseCode;
use App\Models\Admin;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * 后台持仓汇总五条旧路由等价闭环测试。
 *
 * 文件功能：
 * - positionSummarySearch / v2/positionSummarySearchV2 / v2/subAgentsListSearchV2 统一转发
 *   admin_api_positionSummaryList，必须保留旧默认日期 2024-01-01 至当天、旧驼峰 userName 模糊筛选
 *   与 subAgentsSearch 的“父级自身 + 直属下级代理”行集合语义。
 * - v2/parentPath 转发 admin_api_agentParentPath，必须返回旧 path/tree 结构与分组配色 span。
 * - GET position_summary_list 渲染 admin_layui::position-summary.index。
 * - 全部数据只来自 user_infos、agent_descendants、mt4_trades、symbol_prices 真实表，禁止模拟汇总。
 */
class AdminPositionSummaryLegacyParityClosureModuleTest extends TestCase
{
    use DatabaseTransactions;

    /**
     * 代理树根节点用户 ID（固定夹具值）。旧版持仓汇总的层级路径以它为顶。
     * @var int
     */
    private $rootAgentId = 983721;

    /**
     * 根节点的直接下级代理 ID。验证新旧链路对直接下级的统计口径一致。
     * @var int
     */
    private $directAgentId = 983722;

    /**
     * 挂在 directAgentId 名下的客户 ID。其订单构成新旧链路对账的样本数据。
     * @var int
     */
    private $customerId = 983723;

    /**
     * 代理树之外的代理 ID。验证汇总不把树外数据计入。
     * @var int
     */
    private $outsideAgentId = 983724;

    public function test_legacy_position_summary_search_applies_default_dates_and_old_envelope(): void
    {
        $this->seedHierarchy();
        // 区间内已平仓一单；2030 年的平仓单必须被旧默认 enddate=当天 排除。
        $this->seedTrade(990821, $this->customerId, '2026-08-20 10:00:00', 6.0, 120.0);
        $this->seedTrade(990822, $this->customerId, '2030-05-05 10:00:00', 3.0, 300.0);

        $response = $this->postLegacySearch('index/admin/order/positionSummarySearch', [
            'searchtype' => 'autoSearch',
            'userId' => $this->rootAgentId,
        ]);

        $response->assertOk()
            ->assertJsonPath('code', ResponseCode::SUCCESS);

        $rows = collect($response->json('data.records.data'));
        $rootRow = $rows->firstWhere('user_id', $this->rootAgentId);

        $this->assertNotNull($rootRow, 'userId 精确筛选必须返回顶级代理行。');
        $this->assertSame(1, (int) $rootRow['total_orders'], '默认日期区间外的平仓单不得计入汇总。');
        $this->assertSame(120.0, (float) $rootRow['total_profit']);
        $this->assertSame(6.0, (float) $rootRow['total_volume']);
        $this->assertArrayHasKey('summary', $response->json('data'));
    }

    public function test_legacy_search_maps_camel_case_username_fuzzy_filter(): void
    {
        $this->seedHierarchy();

        $response = $this->postLegacySearch('index/admin/order/positionSummarySearch', [
            'searchtype' => 'autoSearch',
            'userName' => 'PS Legacy Root',
        ]);

        $response->assertOk()->assertJsonPath('code', ResponseCode::SUCCESS);

        $rows = collect($response->json('data.records.data'));
        $this->assertSame(
            [$this->rootAgentId],
            $rows->pluck('user_id')->map(static fn ($id): int => (int) $id)->values()->all(),
            '旧驼峰 userName 必须映射为 user_name 模糊筛选。'
        );
    }

    public function test_legacy_sub_agents_uris_return_parent_and_direct_agent_rows_only(): void
    {
        $this->seedHierarchy();
        $this->seedTrade(990823, $this->customerId, '2026-08-20 10:00:00', 6.0, 120.0);
        $this->seedTrade(990824, $this->outsideAgentId, '2026-08-20 10:00:00', 3.0, 300.0);

        foreach ([
            'index/admin/order/v2/subAgentsListSearchV2',
            'index/admin/order/v2/positionSummarySearchV2',
        ] as $legacyUri) {
            $response = $this->postLegacySearch($legacyUri, [
                'searchtype' => 'subAgentsSearch',
                'userPId' => $this->rootAgentId,
                'page' => 1,
                'rows' => 10,
            ]);

            $response->assertOk()
                ->assertJsonPath('code', ResponseCode::SUCCESS);

            $rows = collect($response->json('data.records.data'));
            $this->assertSame(
                [$this->rootAgentId, $this->directAgentId],
                $rows->pluck('user_id')->map(static fn ($id): int => (int) $id)->sort()->values()->all(),
                $legacyUri . ' 必须返回父级自身与直属下级代理两行。'
            );

            foreach ($rows as $row) {
                $this->assertSame(1, (int) $row['total_orders']);
                $this->assertSame(120.0, (float) $row['total_profit']);
                $this->assertSame(6.0, (float) $row['total_volume']);
            }

            $this->assertFalse(
                $rows->contains('user_id', $this->outsideAgentId),
                $legacyUri . ' 不得包含范围外代理行。'
            );
        }
    }

    public function test_legacy_parent_path_returns_old_path_and_tree_contract(): void
    {
        $this->seedHierarchy();

        $response = $this->actingAs(Admin::query()->findOrFail(1), 'admin')
            ->postJson('/index/admin/order/v2/parentPath', [
                'event_name' => 'clickPositionSummaryPath',
                'user_id' => $this->directAgentId,
            ]);

        $response->assertOk()
            ->assertJsonPath('code', 200)
            ->assertJsonPath('msg', 'SUCCESS');

        $payload = (array) $response->json();
        $path = (string) ($payload['data']['path'] ?? '');
        $tree = (array) ($payload['data']['tree'] ?? []);

        $this->assertStringContainsString('PS Legacy Direct Agent[' . $this->directAgentId . ']', $path);
        $this->assertStringContainsString('PS Legacy Root Agent[' . $this->rootAgentId . ']', $path);
        $this->assertStringContainsString('->', $path, 'path 必须保留旧 -> 链路分隔符。');
        $this->assertNotEmpty($tree);
        // 旧 parentPathV2 的 tree 按上级→下级排序：首元素是根代理，末元素是目标用户。
        $this->assertStringContainsString('lay-event="clickPositionSummaryPath"', (string) $tree[0]);
        $this->assertStringContainsString('data-user_id="' . $this->rootAgentId . '"', (string) $tree[0]);
        $lastSpan = (string) end($tree);
        $this->assertStringContainsString('data-user_id="' . $this->directAgentId . '"', $lastSpan);
    }

    public function test_legacy_page_renders_position_summary_module(): void
    {
        $this->actingAs(Admin::query()->findOrFail(1), 'admin')
            ->get('/index/admin/order/position_summary_list')
            ->assertOk()
            ->assertSee('positionSummaryTable', false);
    }

    public function test_legacy_search_fails_closed_on_non_numeric_user_pid(): void
    {
        $this->seedHierarchy();

        $this->postLegacySearch('index/admin/order/positionSummarySearch', [
            'searchtype' => 'subAgentsSearch',
            'userPId' => 'not-a-number',
        ])->assertOk()->assertJsonPath('code', ResponseCode::VALIDATION_FAILED);
    }

    /**
     * 通过旧 URI 发起转发请求。
     *
     * @param string $legacyUri 旧后台路由 URI。
     * @param array<string, mixed> $payload 旧协议参数。
     */
    private function postLegacySearch(string $legacyUri, array $payload)
    {
        return $this->withoutMiddleware([
            \App\Http\Middleware\AdminAuthenticate::class,
            \App\Http\Middleware\JwtAuthMiddleware::class,
            \App\Http\Middleware\SingleSignOn::class,
            \App\Http\Middleware\CheckPermission::class,
        ])->actingAs(Admin::query()->findOrFail(1), 'admin')
            ->postJson('/' . $legacyUri, $payload);
    }

    private function seedHierarchy(): void
    {
        $now = time();
        $this->upsertUser($this->rootAgentId, 'PS Legacy Root Agent', 1, 0, $now);
        $this->upsertUser($this->directAgentId, 'PS Legacy Direct Agent', 1, $this->rootAgentId, $now);
        $this->upsertUser($this->customerId, 'PS Legacy Customer', 2, $this->directAgentId, $now);
        $this->upsertUser($this->outsideAgentId, 'PS Legacy Outside Agent', 1, 0, $now);

        foreach ([
            [$this->rootAgentId, $this->directAgentId, 1, 1],
            [$this->rootAgentId, $this->customerId, 2, 2],
            [$this->directAgentId, $this->customerId, 2, 1],
        ] as [$agentId, $descendantId, $descendantType, $depth]) {
            DB::table('agent_descendants')->updateOrInsert(
                ['agent_id' => $agentId, 'descendant_id' => $descendantId],
                [
                    'descendant_type' => $descendantType,
                    'is_direct' => $depth === 1 ? 1 : 0,
                    'depth' => $depth,
                    'created_at' => $now,
                    'updated_at' => $now,
                    'deleted_at' => null,
                ]
            );
        }

        DB::table('symbol_prices')->updateOrInsert(
            ['symbol' => 'LEGACYPOSSUM'],
            [
                'time' => date('Y-m-d H:i:s', $now),
                'bid' => 10,
                'ask' => 11,
                'low' => 9,
                'high' => 12,
                'direction' => 0,
                'digits' => 2,
                'spread' => 1,
                'group_id' => 4,
                'status' => 1,
                'modify_time' => date('Y-m-d H:i:s', $now),
                'created_at' => $now,
                'updated_at' => $now,
                'deleted_at' => null,
            ]
        );
    }

    private function upsertUser(int $userId, string $userName, int $accountType, int $parentId, int $now): void
    {
        DB::table('user_infos')->updateOrInsert(
            ['user_id' => $userId],
            [
                'login_id' => 0,
                'user_name' => $userName,
                'phone' => '',
                'gender' => 1,
                'account_type' => $accountType,
                'parent_id' => $parentId,
                'family_tree' => $parentId > 0 ? $parentId . ',' . $userId : (string) $userId,
                'mt4_code' => $userId,
                'mt4_group' => 'demo-position-tree',
                'total_funds' => 0,
                'equity' => 0,
                'effective_credit' => 0,
                'created_at' => $now,
                'updated_at' => $now,
                'deleted_at' => null,
            ]
        );
    }

    private function seedTrade(int $ticket, int $login, string $closeTime, float $volume, float $profit): void
    {
        DB::table('mt4_trades')->updateOrInsert(
            ['ticket' => $ticket],
            [
                'login' => $login,
                'symbol' => 'LEGACYPOSSUM',
                'cmd' => 0,
                'volume' => $volume,
                'open_price' => 10,
                'close_price' => 12,
                'commission' => -1,
                'swaps' => -0.5,
                'profit' => $profit,
                'open_time' => strtotime($closeTime) - 7200,
                'close_time' => strtotime($closeTime),
                'comment' => 'legacy position summary parity',
                'modify_time' => strtotime($closeTime),
                'created_at' => strtotime($closeTime),
                'updated_at' => strtotime($closeTime),
            ]
        );
    }
}
