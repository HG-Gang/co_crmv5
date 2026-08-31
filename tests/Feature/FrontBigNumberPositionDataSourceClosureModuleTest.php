<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/17
 * Time: 21:32
 */

/**
 * FrontBigNumberPositionDataSourceClosureModuleTest
 *
 * 文件功能：
 * - 验证大代理持仓汇总真实数据库口径：按可见代理网络的 user_trades 计算余额变动/返佣/已平仓、旧 MT4 规则与默认日期范围、分批 user_trade 查询、旧财务对比列与下级钻取载荷。
 * - 输入：HTTP 请求与事务回滚的数据库夹具；输出：PHPUnit 断言结果。
 * - 明确不负责：不覆盖真实 MT4 网关与线上支付/出金通道（由网关契约测试锁定）。
 */

declare(strict_types=1);

namespace Tests\Feature;

use App\Http\Middleware\JwtAuthMiddleware;
use App\Http\Middleware\SingleSignOn;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * 大代理持仓汇总真实数据库口径闭环测试。
 *
 * 旧大代理持仓页必须按可见代理网络中的 user_trades 计算余额变动、返佣和已平仓交易，
 * 不能复用代理资料列表或从浏览器端生成统计行。
 */
class FrontBigNumberPositionDataSourceClosureModuleTest extends TestCase
{
    use DatabaseTransactions;

    public function test_big_agent_position_and_order_pages_render_database_backed_symbol_select(): void
    {
        $bigAgentId = 5134102;
        $this->insertBigAgent($bigAgentId, 0);

        foreach ([
            '/user/agents/position/summary',
            '/user/agents/open/order',
            '/user/agents/close/order',
        ] as $uri) {
            $this->withSession(['bigAgents' => ['id' => $bigAgentId]])
                ->get($uri)
                ->assertOk()
                ->assertSee('name="symbol"', false)
                ->assertSee('<select', false)
                ->assertSee('data-options-endpoint="/user/agents/trade-symbols"', false);
        }
    }

    public function test_big_agent_trade_symbol_endpoint_returns_database_options(): void
    {
        $bigAgentId = 5134103;
        $this->insertBigAgent($bigAgentId, 0);
        $this->insertSymbol('BIGDBSELECT', 2);

        $this->withSession(['bigAgents' => ['id' => $bigAgentId]])
            ->getJson('/user/agents/trade-symbols')
            ->assertOk()
            ->assertJsonFragment([
                'value' => 'BIGDBSELECT',
                'label' => 'BIGDBSELECT',
            ]);
    }

    public function test_crmui_big_agent_position_and_order_pages_use_legacy_symbol_options(): void
    {
        $bigAgentId = 5134106;
        $this->insertBigAgent($bigAgentId, 0);

        foreach ([
            '/front-crmui/big-agent/position/summary',
            '/front-crmui/big-agent/orders/open',
            '/front-crmui/big-agent/orders/closed',
        ] as $uri) {
            $this->withSession(['bigAgents' => ['id' => $bigAgentId]])
                ->get($uri)
                ->assertOk()
                ->assertSee('name="symbol"', false)
                ->assertSee('data-dynamic-options="bigAgentSymbols"', false)
                ->assertDontSee('/api/front/trade-symbols', false);
        }

        $script = file_get_contents(public_path('js/apps/crmui/front.js')) ?: '';
        $this->assertStringContainsString("bigAgentSymbols: '/user/agents/trade-symbols'", $script);
        $this->assertStringContainsString('auth: bigAgentSession ? false : undefined', $script);
    }

    public function test_crmui_big_agent_tables_submit_pagination_and_render_legacy_footer(): void
    {
        $bigAgentId = 5134114;
        $this->insertBigAgent($bigAgentId, 0);

        foreach ([
            '/front-crmui/big-agent/proxy/list',
            '/front-crmui/big-agent/position/summary',
            '/front-crmui/big-agent/orders/open',
            '/front-crmui/big-agent/orders/closed',
        ] as $uri) {
            $this->withSession(['bigAgents' => ['id' => $bigAgentId]])
                ->get($uri)
                ->assertOk()
                ->assertSee('data-crmui-table-footer', false)
                ->assertSee('data-crmui-pagination', false)
                ->assertSee('data-crmui-page-previous', false)
                ->assertSee('data-crmui-page-next', false)
                ->assertSee('data-crmui-page-size', false);
        }

        $script = file_get_contents(public_path('js/apps/crmui/front.js')) ?: '';
        $this->assertStringContainsString('function footerRowsFromResponse', $script);
        $this->assertStringContainsString('function renderTableFooter', $script);
        $this->assertStringContainsString('filter.page = pageState.page', $script);
        $this->assertStringContainsString('filter.limit = pageState.perPage', $script);
        $this->assertStringContainsString('filter.per_page = pageState.perPage', $script);
        $this->assertStringContainsString("'[data-crmui-page-previous], [data-crmui-page-next]'", $script);
        $this->assertStringContainsString('var requestedPage = state.page', $script);
        $this->assertStringContainsString('return state.page !== requestedPage', $script);
        $this->assertStringContainsString('if (renderPagination($page, total)) {', $script);
        $this->assertStringContainsString('loadPage($page);', $script);
    }

    public function test_big_agent_position_summary_uses_legacy_mt4_trade_rules(): void
    {
        $bigAgentId = 5134101;
        $agentId = 513410101;
        $customerId = 513410102;

        $this->insertUser($agentId, '大代理持仓直属代理', 1, 0, (string) $agentId);
        $this->insertUser($customerId, '大代理持仓直属客户', 2, $agentId, $agentId . ',' . $customerId);
        $this->insertBigAgent($bigAgentId, $agentId);
        $this->insertSymbol('BIGDBXAU', 1);
        DB::table('user_infos')->where('user_id', $agentId)->update(['total_funds' => 300, 'equity' => 350]);
        DB::table('user_infos')->where('user_id', $customerId)->update(['total_funds' => 700, 'equity' => 800]);

        $this->insertTrade($customerId, 513410201, 6, 0, 1000, 'DBAA-20260817');
        $this->insertTrade($customerId, 513410202, 6, 0, -250, 'WBAA-20260817');
        $this->insertTrade($agentId, 513410203, 6, 0, 50, 'DBCN-20260817');
        $this->insertTrade($customerId, 513410204, 0, 200, 75, '', -5, -2, 1);

        $response = $this->withoutMiddleware([JwtAuthMiddleware::class, SingleSignOn::class])
            ->withSession(['bigAgents' => ['id' => $bigAgentId]])
            ->postJson('/user/agents/position/positionSummarySearch', [
                'startdate' => '2026-08-17',
                'enddate' => '2026-08-17',
                'limit' => 20,
            ]);

        $response
            ->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonPath('rows.0.user_id', (string) $agentId)
            ->assertJsonPath('rows.0.sub_ag_id', $agentId)
            ->assertJsonPath('rows.0.total_yuerj', '1000.00')
            ->assertJsonPath('rows.0.total_yuecj', '-250.00')
            ->assertJsonPath('rows.0.total_rebate', '50.00')
            ->assertJsonPath('rows.0.total_fy', '50.00')
            ->assertJsonPath('rows.0.total_rj', '1000.00')
            ->assertJsonPath('rows.0.total_qk', '-250.00')
            ->assertJsonPath('rows.0.user_money', '1000.00')
            ->assertJsonPath('rows.0.cust_eqy', '1150.00')
            ->assertJsonPath('rows.0.total_net_worth', '750.00')
            ->assertJsonPath('rows.0.total_profit', '75.00')
            ->assertJsonPath('rows.0.total_comm', '5.00')
            ->assertJsonPath('rows.0.total_noble_metal', '2.00')
            ->assertJsonPath('rows.0.total_volume', '2.00')
            ->assertJsonPath('rows.0.total_swaps', '-2.00');

        $response
            ->assertJsonPath('footer.0.total_yuerj', '1000.00')
            ->assertJsonPath('footer.0.user_money', '1000.00')
            ->assertJsonPath('footer.0.cust_eqy', '1150.00')
            ->assertJsonPath('footer.0.total_profit', '75.00');
    }

    public function test_big_agent_proxy_summary_uses_each_agents_legacy_trade_and_balance_fields(): void
    {
        $bigAgentId = 5134109;
        $agentId = 513410901;
        $customerId = 513410902;

        $this->insertUser($agentId, '大代理列表资金直属代理', 1, 0, (string) $agentId);
        $this->insertUser($customerId, '大代理列表资金直属客户', 2, $agentId, $agentId . ',' . $customerId);
        $this->insertBigAgent($bigAgentId, $agentId);
        DB::table('user_infos')->where('user_id', $agentId)->update(['total_funds' => 300, 'equity' => 350]);
        DB::table('user_infos')->where('user_id', $customerId)->update(['total_funds' => 700, 'equity' => 800]);

        $this->insertTrade($agentId, 513410911, 6, 0, 100, 'DBAA-20260817');
        $this->insertTrade($agentId, 513410912, 6, 0, -20, 'WBAA-20260817');
        $this->insertTrade($agentId, 513410913, 6, 0, 10, 'DBCN-20260817');
        $this->insertTrade($customerId, 513410914, 6, 0, 1000, 'DBAA-20260817');

        $response = $this->withoutMiddleware([JwtAuthMiddleware::class, SingleSignOn::class])
            ->withSession(['bigAgents' => ['id' => $bigAgentId]])
            ->postJson('/user/agents/proxy/proxySearch', ['limit' => 20]);

        $response
            ->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonPath('rows.0.sub_ag_id', $agentId)
            ->assertJsonPath('rows.0.user_money', '300.00')
            ->assertJsonPath('rows.0.cust_eqy', '350.00')
            ->assertJsonPath('rows.0.fy_money', '10.00')
            ->assertJsonPath('rows.0.rj_money', '100.00')
            ->assertJsonPath('rows.0.qk_money', '-20.00')
            ->assertJsonPath('footer.0.user_money', '300.00')
            ->assertJsonPath('footer.0.cust_eqy', '350.00')
            ->assertJsonPath('footer.0.rj_money', '100.00');
    }

    public function test_big_agent_proxy_list_uses_legacy_default_date_range(): void
    {
        $bigAgentId = 5134111;
        $oldAgentId = 513411101;
        $currentAgentId = 513411102;

        $this->insertUser($oldAgentId, '大代理列表旧日期代理', 1, 0, (string) $oldAgentId);
        $this->insertUser($currentAgentId, '大代理列表当前日期代理', 1, 0, (string) $currentAgentId);
        $this->insertBigAgent($bigAgentId, $oldAgentId);
        DB::table('big_agents')->where('id', $bigAgentId)->update([
            'sub_agent_ids' => $oldAgentId . ',' . $currentAgentId,
        ]);
        DB::table('user_infos')->where('user_id', $oldAgentId)->update([
            'created_at' => strtotime('2023-12-31 12:00:00'),
        ]);
        DB::table('user_infos')->where('user_id', $currentAgentId)->update([
            'created_at' => strtotime('2024-01-01 12:00:00'),
        ]);

        $response = $this->withoutMiddleware([JwtAuthMiddleware::class, SingleSignOn::class])
            ->withSession(['bigAgents' => ['id' => $bigAgentId]])
            ->postJson('/user/agents/proxy/proxySearch', ['limit' => 20]);

        $response
            ->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonPath('rows.0.sub_ag_id', $currentAgentId);
        $this->assertStringNotContainsString((string) $oldAgentId, $response->getContent());
    }

    public function test_big_agent_order_lists_use_legacy_default_date_range(): void
    {
        $bigAgentId = 5134112;
        $agentId = 513411201;
        $customerId = 513411202;

        $this->insertUser($agentId, '大代理订单默认日期代理', 1, 0, (string) $agentId);
        $this->insertUser($customerId, '大代理订单默认日期客户', 2, $agentId, $agentId . ',' . $customerId);
        $this->insertBigAgent($bigAgentId, $agentId);

        $this->insertOrderTrade($customerId, 513411211, 'BIGDBDATE', 0, 1, true);
        $this->insertOrderTrade($customerId, 513411212, 'BIGDBDATE', 0, 1, true);
        DB::table('user_trades')->where('ticket', 513411211)->update([
            'open_time' => '2023-12-31 09:00:00',
        ]);

        $this->insertOrderTrade($customerId, 513411221, 'BIGDBDATE', 1, 1, false);
        $this->insertOrderTrade($customerId, 513411222, 'BIGDBDATE', 1, 1, false);
        DB::table('user_trades')->where('ticket', 513411221)->update([
            'open_time' => '2023-12-31 09:00:00',
            'close_time' => '2023-12-31 10:00:00',
        ]);

        foreach ([
            ['/user/agents/open/openOrderSearch', 513411212],
            ['/user/agents/close/closeOrderSearch', 513411222],
        ] as [$uri, $ticket]) {
            $response = $this->withoutMiddleware([JwtAuthMiddleware::class, SingleSignOn::class])
                ->withSession(['bigAgents' => ['id' => $bigAgentId]])
                ->postJson($uri, ['limit' => 20]);

            $response
                ->assertOk()
                ->assertJsonPath('total', 1)
                ->assertJsonPath('rows.0.ticket', (string) $ticket);
        }
    }

    public function test_big_agent_financial_lists_batch_user_trade_queries(): void
    {
        $bigAgentId = 5134113;
        $agentIds = [513411301, 513411302, 513411303];

        foreach ($agentIds as $agentId) {
            $this->insertUser($agentId, '大代理批量统计' . $agentId, 1, 0, (string) $agentId);
        }
        $this->insertBigAgent($bigAgentId, $agentIds[0]);
        DB::table('big_agents')->where('id', $bigAgentId)->update([
            'sub_agent_ids' => implode(',', $agentIds),
        ]);

        foreach ([
            '/user/agents/proxy/proxySearch',
            '/user/agents/position/positionSummarySearch',
        ] as $uri) {
            DB::flushQueryLog();
            DB::enableQueryLog();

            $this->withoutMiddleware([JwtAuthMiddleware::class, SingleSignOn::class])
                ->withSession(['bigAgents' => ['id' => $bigAgentId]])
                ->postJson($uri, ['limit' => 1])
                ->assertOk();

            $queries = DB::getQueryLog();
            DB::disableQueryLog();
            $userTradeQueries = array_filter($queries, static function (array $query): bool {
                return stripos((string) ($query['query'] ?? ''), 'user_trades') !== false;
            });

            $this->assertCount(1, $userTradeQueries, $uri . ' 必须批量读取 user_trades。');
            if (strpos($uri, '/position/') !== false) {
                $userInfoQueries = array_filter($queries, static function (array $query): bool {
                    return stripos((string) ($query['query'] ?? ''), 'user_infos') !== false;
                });
                $this->assertLessThanOrEqual(5, count($userInfoQueries), $uri . ' 必须批量加载代理层级。');
            }
        }
    }

    public function test_big_agent_position_pages_render_legacy_financial_comparison_columns(): void
    {
        $bigAgentId = 5134110;
        $this->insertBigAgent($bigAgentId, 0);
        $columns = ['user_money', 'cust_eqy', 'total_fy', 'total_rj', 'total_qk', 'total_net_worth'];

        $layui = $this->withSession(['bigAgents' => ['id' => $bigAgentId]])
            ->get('/user/agents/position/summary')
            ->assertOk();
        foreach ($columns as $column) {
            $layui->assertSee('"key":"' . $column . '"', false);
        }

        $crmui = $this->withSession(['bigAgents' => ['id' => $bigAgentId]])
            ->get('/front-crmui/big-agent/position/summary')
            ->assertOk();
        foreach ($columns as $column) {
            $crmui->assertSee('data-key="' . $column . '"', false);
        }
    }

    public function test_big_agent_sub_position_summary_accepts_legacy_parent_drill_payload(): void
    {
        $bigAgentId = 5134104;
        $rootAgentId = 513410401;
        $childAgentId = 513410402;
        $customerId = 513410403;

        $this->insertUser($rootAgentId, '大代理持仓根代理', 1, 0, (string) $rootAgentId);
        $this->insertUser($childAgentId, '大代理持仓下级代理', 1, $rootAgentId, $rootAgentId . ',' . $childAgentId);
        $this->insertUser($customerId, '大代理持仓下级客户', 2, $childAgentId, $rootAgentId . ',' . $childAgentId . ',' . $customerId);
        $this->insertBigAgent($bigAgentId, $rootAgentId);
        $this->insertTrade($customerId, 513410405, 6, 0, 600, 'DBAA-20260817');

        $this->withoutMiddleware([JwtAuthMiddleware::class, SingleSignOn::class])
            ->withSession(['bigAgents' => ['id' => $bigAgentId]])
            ->postJson('/user/agents/position/subAgentsListSearch', [
                'userPId' => $rootAgentId,
                'userId' => $rootAgentId,
                'searchtype' => 'subSearch',
                'startdate' => '2026-08-17',
                'enddate' => '2026-08-17',
                'limit' => 20,
            ])
            ->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonPath('rows.0.sub_ag_id', $childAgentId)
            ->assertJsonPath('rows.0.total_yuerj', '600.00');
    }

    public function test_big_agent_order_lists_apply_legacy_trade_and_exact_symbol_filters(): void
    {
        $bigAgentId = 5134105;
        $agentId = 513410501;
        $customerId = 513410502;

        $this->insertUser($agentId, '大代理订单直属代理', 1, 0, (string) $agentId);
        $this->insertUser($customerId, '大代理订单直属客户', 2, $agentId, $agentId . ',' . $customerId);
        $this->insertBigAgent($bigAgentId, $agentId);

        $this->insertOrderTrade($customerId, 513410511, 'BIGDBXAU', 0, 1, true);
        $this->insertOrderTrade($customerId, 513410512, 'BIGDBXAU.m', 0, 1, true);
        $this->insertOrderTrade($customerId, 513410513, 'BIGDBXAU', 6, 1, true);
        $this->insertOrderTrade($customerId, 513410514, 'BIGDBXAU', 0, 0, true);
        $this->insertOrderTrade($customerId, 513410521, 'BIGDBXAU', 1, 1, false);
        $this->insertOrderTrade($customerId, 513410522, 'BIGDBXAU.m', 1, 1, false);
        $this->insertOrderTrade($customerId, 513410523, 'BIGDBXAU', 6, 1, false);
        $this->insertOrderTrade($customerId, 513410524, 'BIGDBXAU', 1, 0, false);

        foreach ([
            ['/user/agents/open/openOrderSearch', 513410511],
            ['/user/agents/close/closeOrderSearch', 513410521],
        ] as [$uri, $ticket]) {
            $response = $this->withoutMiddleware([JwtAuthMiddleware::class, SingleSignOn::class])
                ->withSession(['bigAgents' => ['id' => $bigAgentId]])
                ->postJson($uri, [
                    'symbol' => 'BIGDBXAU',
                    'startdate' => '2026-08-17',
                    'enddate' => '2026-08-17',
                    'limit' => 20,
                ]);

            $response
                ->assertOk()
                ->assertJsonPath('total', 1)
                ->assertJsonPath('rows.0.ticket', (string) $ticket)
                ->assertJsonPath('rows.0.symbol', 'BIGDBXAU');
        }
    }

    public function test_big_agent_proxy_descendants_returns_only_direct_children(): void
    {
        $bigAgentId = 5134107;
        $rootAgentId = 513410701;
        $childAgentId = 513410702;
        $grandchildAgentId = 513410703;

        $this->insertUser($rootAgentId, '大代理列表根代理', 1, 0, (string) $rootAgentId);
        $this->insertUser($childAgentId, '大代理列表直属下级', 1, $rootAgentId, $rootAgentId . ',' . $childAgentId);
        $this->insertUser($grandchildAgentId, '大代理列表间接下级', 1, $childAgentId, $rootAgentId . ',' . $childAgentId . ',' . $grandchildAgentId);
        $this->insertBigAgent($bigAgentId, $rootAgentId);

        $response = $this->withoutMiddleware([JwtAuthMiddleware::class, SingleSignOn::class])
            ->withSession(['bigAgents' => ['id' => $bigAgentId]])
            ->postJson('/user/agents/proxy/proxySearchBySub', [
                'userPId' => $rootAgentId,
                'searchtype' => 'subSearch',
                'limit' => 20,
            ]);

        $response
            ->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonPath('rows.0.sub_ag_id', $childAgentId);
        $this->assertStringNotContainsString((string) $grandchildAgentId, $response->getContent());
    }

    public function test_crmui_big_agent_descendant_pages_bind_parent_filter(): void
    {
        $bigAgentId = 5134108;
        $parentId = 513410801;
        $this->insertBigAgent($bigAgentId, $parentId);

        foreach ([
            '/front-crmui/big-agent/proxy/descendants?userId=' . $parentId,
            '/front-crmui/big-agent/position/descendants?userId=' . $parentId,
        ] as $uri) {
            $this->withSession(['bigAgents' => ['id' => $bigAgentId]])
                ->get($uri)
                ->assertOk()
                ->assertSee('name="userPId"', false)
                ->assertSee('value="' . $parentId . '"', false);
        }
    }

    private function insertUser(
        int $userId,
        string $userName,
        int $accountType,
        int $parentId,
        string $familyTree
    ): void {
        $now = time();
        DB::table('user_trades')->where('user_id', $userId)->delete();
        DB::table('user_infos')->where('user_id', $userId)->delete();
        DB::table('user_logins')->where('user_id', $userId)->delete();
        DB::table('agent_descendants')
            ->where('agent_id', $userId)
            ->orWhere('descendant_id', $userId)
            ->delete();

        $loginId = DB::table('user_logins')->insertGetId([
            'user_id' => $userId,
            'email' => 'big-position-db-' . $userId . '@example.test',
            'password' => Hash::make('password'),
            'account_type' => $accountType,
            'role_id' => 0,
            'is_enabled' => 1,
            'is_cancelled' => 0,
            'source_type' => 0,
            'jwt_token_id' => '',
            'last_login_ip' => '',
            'last_login_at' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        DB::table('user_infos')->insert([
            'user_id' => $userId,
            'login_id' => $loginId,
            'user_name' => $userName,
            'phone' => '',
            'gender' => 0,
            'account_type' => $accountType,
            'parent_id' => $parentId,
            'family_tree' => $familyTree,
            'group_id' => 0,
            'level_id' => 0,
            'comm_rate' => 0,
            'auth_status' => 1,
            'total_funds' => 0,
            'used_margin' => 0,
            'avail_margin' => 0,
            'equity' => 0,
            'effective_credit' => 0,
            'risk_ratio' => 0,
            'leverage' => 100,
            'is_mt4_synced' => 1,
            'is_mt4_enabled' => 1,
            'is_mt4_readonly' => 0,
            'is_withdrawal_allowed' => 0,
            'is_deposit_allowed' => 0,
            'is_agent_confirmed' => 0,
            'mt4_group' => '',
            'settle_method' => 1,
            'settle_cycle' => 0,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    private function insertBigAgent(int $bigAgentId, int $agentId): void
    {
        $now = time();
        DB::table('big_agents')->where('id', $bigAgentId)->delete();
        DB::table('big_agents')->insert([
            'id' => $bigAgentId,
            'email' => 'big-position-db-' . $bigAgentId . '@example.test',
            'username' => 'big-position-db-' . $bigAgentId,
            'password' => Hash::make('password'),
            'sub_agent_ids' => (string) $agentId,
            'is_enabled' => 1,
            'jwt_token_id' => '',
            'created_by' => 'phpunit',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    private function insertSymbol(string $symbol, int $groupId): void
    {
        $now = time();
        DB::table('symbol_prices')->where('symbol', $symbol)->delete();
        DB::table('symbol_prices')->insert([
            'symbol' => $symbol,
            'time' => '2026-08-17 10:00:00',
            'bid' => 100,
            'ask' => 101,
            'low' => 99,
            'high' => 102,
            'direction' => 0,
            'digits' => 2,
            'spread' => 10,
            'group_id' => $groupId,
            'status' => 1,
            'modify_time' => '2026-08-17 10:00:00',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    private function insertTrade(
        int $userId,
        int $ticket,
        int $cmd,
        int $volume,
        float $profit,
        string $comment,
        float $commission = 0,
        float $swaps = 0,
        float $marginRate = 0
    ): void {
        $now = time();
        DB::table('user_trades')->insert([
            'user_id' => $userId,
            'ticket' => $ticket,
            'symbol' => 'BIGDBXAU',
            'digits' => 2,
            'cmd' => $cmd,
            'volume' => $volume,
            'open_time' => '2026-08-17 09:00:00',
            'open_price' => 100,
            'close_time' => '2026-08-17 10:00:00',
            'close_price' => 101,
            'commission' => $commission,
            'swaps' => $swaps,
            'profit' => $profit,
            'margin_rate' => $marginRate,
            'comment' => $comment,
            'modify_time' => '2026-08-17 10:00:00',
            'settlement_status' => 0,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    private function insertOrderTrade(
        int $userId,
        int $ticket,
        string $symbol,
        int $cmd,
        float $marginRate,
        bool $open
    ): void {
        $now = time();
        DB::table('user_trades')->insert([
            'user_id' => $userId,
            'ticket' => $ticket,
            'symbol' => $symbol,
            'digits' => 2,
            'cmd' => $cmd,
            'volume' => 100,
            'open_time' => '2026-08-17 09:00:00',
            'open_price' => 100,
            'close_time' => $open ? '1970-01-01 00:00:00' : '2026-08-17 10:00:00',
            'close_price' => $open ? 0 : 101,
            'commission' => -1,
            'swaps' => -1,
            'profit' => $open ? 0 : 10,
            'margin_rate' => $marginRate,
            'comment' => 'big number order DB closure',
            'modify_time' => '2026-08-17 10:00:00',
            'settlement_status' => 0,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }
}
