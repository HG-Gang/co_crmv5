<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/17
 * Time: 01:02
 */

/**
 * AdminLegacyAgentStatisticsAdapterTest
 *
 * 文件功能：
 * - 验证旧代理/大代理商搜索与统计适配器：旧筛选与表格契约、历史日期窗口默认值、待确认代理审核、交易备注编码归类、大代理商范围归属与持仓统计口径。
 * - 输入：HTTP 请求与事务回滚的数据库夹具；输出：PHPUnit 断言结果。
 * - 明确不负责：不覆盖真实 MT4 网关与线上支付/出金通道（由网关契约测试锁定）。
 */

declare(strict_types=1);

namespace Tests\Feature;

use App\Http\Middleware\LegacyAdminAuthenticate;
use App\Models\Admin;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Tests\Support\MySqlAutoIncrementSnapshot;
use Tests\Support\MySqlFixtureMutex;
use Tests\Support\MySqlTableFingerprint;
use Tests\TestCase;

final class AdminLegacyAgentStatisticsAdapterTest extends TestCase
{
    /**
     * 本类夹具写入/断言的全部数据表。setUp 据此捕获表指纹与 AUTO_INCREMENT 快照，tearDown 据此校验清理是否彻底。
     * @var array<int, string>
     */
    private const FIXTURE_TABLES = [
        'roles',
        'role_data_scopes',
        'admins',
        'admin_agent_bindings',
        'big_agents',
        'user_logins',
        'user_infos',
        'agent_descendants',
        'symbol_prices',
        'user_trades',
    ];

    /**
     * tearDown 删除夹具行的顺序，按外键依赖自叶子表到父表排列，保证删除不留孤儿行。
     * @var array<int, string>
     */
    private const CLEANUP_ORDER = [
        'user_trades',
        'symbol_prices',
        'agent_descendants',
        'user_infos',
        'user_logins',
        'admin_agent_bindings',
        'big_agents',
        'role_data_scopes',
        'admins',
        'roles',
    ];

    /**
     * 表名 => 本用例插入的主键列表。tearDown 按 CLEANUP_ORDER 逐表删除，防止夹具数据污染共享库。
     * @var array<string, array<int, int>>
     */
    private $createdRowIds = [];

    /**
     * 已被本用例占用的 user_id 集合。分配新夹具用户时跳过这些值，避免用例内撞号。
     * @var array<int, int>
     */
    private $reservedUserIds = [];

    /**
     * 已被本用例占用的 MT4 ticket 集合。分配新订单时跳过，避免同一用例内重复订单号。
     * @var array<int, int>
     */
    private $reservedTickets = [];

    /**
     * setUp 生成的随机十六进制令牌，用于拼接唯一用户名/订单前缀，避免重复运行或并行时唯一键冲突。
     * @var string
     */
    private $fixtureToken = '';

    /**
     * setUp 捕获的 MySqlAutoIncrementSnapshot 实例；tearDown 调用 restore() 还原各表自增值。null 表示尚未捕获。
     * @var MySqlAutoIncrementSnapshot|null
     */
    private $autoIncrementSnapshot;

    /**
     * MySqlFixtureMutex 实例。串行化共享测试库上的夹具准备与清理，避免并行进程互相踩踏。
     * @var MySqlFixtureMutex|null
     */
    private $fixtureMutex;

    /**
     * setUp 捕获的各表行指纹基线。tearDown 重新捕获比对，不一致即夹具泄漏，测试失败上报。
     * @var array<string, array<string, int|string>>
     */
    private $tableFingerprints = [];

    protected function setUp(): void
    {
        parent::setUp();

        try {
            $this->fixtureToken = bin2hex(random_bytes(8));
            $this->fixtureMutex = new MySqlFixtureMutex();
            $this->fixtureMutex->acquire();
            $this->tableFingerprints = MySqlTableFingerprint::capture(self::FIXTURE_TABLES);
            $this->autoIncrementSnapshot = MySqlAutoIncrementSnapshot::capture(self::FIXTURE_TABLES);
        } catch (\Throwable $exception) {
            $this->abortFixtureSetup($exception);
        }
    }

    protected function tearDown(): void
    {
        $cleanupFailures = [];

        try {
            $this->cleanupFixture($cleanupFailures);
        } finally {
            try {
                if ($this->autoIncrementSnapshot !== null) {
                    $this->autoIncrementSnapshot->restore();
                }
            } catch (\Throwable $exception) {
                $cleanupFailures[] = 'auto_increment_restore: ' . $exception->getMessage();
            }

            try {
                $after = MySqlTableFingerprint::capture(self::FIXTURE_TABLES);
                if ($after !== $this->tableFingerprints) {
                    $cleanupFailures[] = 'table_fingerprint_mismatch';
                }
            } catch (\Throwable $exception) {
                $cleanupFailures[] = 'table_fingerprint_capture: ' . $exception->getMessage();
            }

            try {
                if ($this->fixtureMutex !== null) {
                    $this->fixtureMutex->releaseWithDisconnectFallback();
                }
            } catch (\Throwable $exception) {
                $cleanupFailures[] = 'mutex_release: ' . $exception->getMessage();
            }

            try {
                parent::tearDown();
            } catch (\Throwable $exception) {
                $cleanupFailures[] = 'parent_teardown: ' . $exception->getMessage();
            }
        }

        $this->resetFixtureState();

        if ($cleanupFailures !== []) {
            throw new \RuntimeException(
                'Legacy agent adapter fixture teardown failures: ' . implode(' | ', $cleanupFailures)
            );
        }
    }

    public function test_legacy_agent_searches_honor_old_filters_and_return_old_table_contract(): void
    {
        $rootId = $this->unusedUserId();
        $childId = $this->unusedUserId();
        $customerId = $this->unusedUserId();
        $hiddenRootId = $this->unusedUserId();
        $admin = $this->createRestrictedAdmin([$rootId]);

        $this->createUser($rootId, 'Legacy filtered root ' . $this->fixtureToken, 1, 0, 100.10, 110.20, '2026-01-15 12:00:00', 1, 0, 1);
        $this->createUser($childId, 'Legacy direct child ' . $this->fixtureToken, 1, $rootId, 30, 40, '2026-01-16 12:00:00', 1, 1, 0);
        $this->createUser($customerId, 'Legacy direct customer ' . $this->fixtureToken, 2, $rootId, 20, 25, '2026-01-17 12:00:00');
        $this->createUser($hiddenRootId, 'Legacy hidden root ' . $this->fixtureToken, 1, 0, 9000, 9100, '2026-01-15 12:00:00', 1, 0, 1);
        $this->createDescendant($rootId, $childId, 1, 1, 1);
        $this->createDescendant($rootId, $customerId, 2, 1, 1);
        $this->createTrade($rootId, 6, 7.25, 'agent rebate -FY', '2026-01-20 10:00:00');
        $this->createTrade($rootId, 6, 12.50, 'Deposit approved', '2026-01-20 11:00:00');
        $this->createTrade($rootId, 6, -3.75, 'Withdrawal approved', '2026-01-20 12:00:00');

        $response = $this->postLegacy($admin, '/index/admin/agents/agentsListSearch', [
            'page' => 1,
            'rows' => 20,
            'searchtype' => 'clickSearch',
            'userId' => $rootId,
            'userstatus' => 1,
            'transmode' => 0,
            'is_confirm_agents' => 1,
            'user_cancel' => 1,
            'startdate' => '2026-01-01',
            'enddate' => '2026-01-31',
        ]);

        $response->assertOk();
        $this->assertSame(1, (int) $response->json('total'));
        $this->assertSame($rootId, (int) $response->json('rows.0.user_id'));
        $this->assertSame('Legacy filtered root ' . $this->fixtureToken, $response->json('rows.0.username'));
        $this->assertSame(1, (int) $response->json('rows.0.agentsTotal'));
        $this->assertSame(1, (int) $response->json('rows.0.accountTotal'));
        $this->assertSame('7.25', $response->json('rows.0.fy_money'));
        $this->assertSame('12.50', $response->json('rows.0.rj_money'));
        $this->assertSame('-3.75', $response->json('rows.0.qk_money'));
        $this->assertSame('100.10', $response->json('footer.0.usermoney'));
        $this->assertSame('110.20', $response->json('footer.0.custeqy'));
        $this->assertStringNotContainsString((string) $hiddenRootId, $response->getContent());

        $v2Response = $this->postLegacy($admin, '/index/admin/agent/v2/agentsListSearchV2', [
            'page' => 1,
            'limit' => 20,
            'searchtype' => 'showSubAgents',
            'userPid' => $rootId,
            'user_cancel' => 1,
            'startdate' => '2026-01-01',
            'enddate' => '2026-01-31',
        ]);

        $v2Response->assertOk();
        $this->assertSame(1, (int) $v2Response->json('total'));
        $this->assertSame($childId, (int) $v2Response->json('rows.0.user_id'));
        $this->assertIsArray($v2Response->json('footer'));
    }

    public function test_legacy_agent_search_defaults_to_the_historical_date_window(): void
    {
        $currentRootId = $this->unusedUserId();
        $oldRootId = $this->unusedUserId();
        $admin = $this->createRestrictedAdmin([$currentRootId, $oldRootId]);

        $this->createUser(
            $currentRootId,
            'Default date current root ' . $this->fixtureToken,
            1,
            0,
            10,
            11,
            date('Y-m-d H:i:s', strtotime('-1 day'))
        );
        $this->createUser(
            $oldRootId,
            'Default date old root ' . $this->fixtureToken,
            1,
            0,
            90,
            99,
            '2023-12-31 12:00:00'
        );

        $response = $this->postLegacy($admin, '/index/admin/agents/agentsListSearch', [
            'page' => 1,
            'rows' => 20,
            'searchtype' => 'autoSearch',
            'user_cancel' => 1,
        ]);

        $response->assertOk();
        $this->assertSame(1, (int) $response->json('total'));
        $this->assertSame($currentRootId, (int) $response->json('rows.0.user_id'));
        $this->assertStringNotContainsString((string) $oldRootId, $response->getContent());
    }

    /**
     * 旧代理审核列表只返回默认日期窗口内待确认的代理，并保持旧 Layui 表格字段。
     */
    public function test_legacy_agent_examine_search_returns_pending_confirmation_agents_with_old_contract(): void
    {
        $pendingAgentId = $this->unusedUserId();
        $confirmedAgentId = $this->unusedUserId();
        $oldPendingAgentId = $this->unusedUserId();
        $admin = $this->createRestrictedAdmin([$pendingAgentId, $confirmedAgentId, $oldPendingAgentId]);

        $this->createUser(
            $pendingAgentId,
            'Pending examine agent ' . $this->fixtureToken,
            1,
            0,
            10,
            11,
            date('Y-m-d H:i:s', strtotime('-1 day')),
            1,
            0,
            0
        );
        $this->createUser(
            $confirmedAgentId,
            'Confirmed examine agent ' . $this->fixtureToken,
            1,
            0,
            20,
            22,
            date('Y-m-d H:i:s', strtotime('-1 day')),
            1,
            0,
            1
        );
        $this->createUser(
            $oldPendingAgentId,
            'Old pending examine agent ' . $this->fixtureToken,
            1,
            0,
            30,
            33,
            '2023-12-31 12:00:00',
            1,
            0,
            0
        );

        $response = $this->postLegacy($admin, '/index/admin/agents/agentsExamineListSearch', [
            'page' => 1,
            'rows' => 20,
        ]);

        $response->assertOk();
        $this->assertSame(1, (int) $response->json('total'));
        $this->assertSame($pendingAgentId, (int) $response->json('rows.0.userId'));
        $this->assertSame('Pending examine agent ' . $this->fixtureToken, $response->json('rows.0.userName'));
        $this->assertSame('legacy-agent-adapter-' . $this->fixtureToken . '-' . $pendingAgentId . '@example.test', $response->json('rows.0.userEmail'));
        $this->assertSame('188' . substr((string) $pendingAgentId, -8), $response->json('rows.0.userPhone'));
        $this->assertSame(3, (int) $response->json('rows.0.userGroupId'));
        $this->assertSame(1, (int) $response->json('rows.0.userRights'));
        $this->assertSame([[]], $response->json('footer'));
        $this->assertStringNotContainsString((string) $confirmedAgentId, $response->getContent());
        $this->assertStringNotContainsString((string) $oldPendingAgentId, $response->getContent());
    }

    public function test_legacy_agent_statistics_classify_historical_trade_comment_codes(): void
    {
        $rootId = $this->unusedUserId();
        $admin = $this->createRestrictedAdmin([$rootId]);
        $this->createUser(
            $rootId,
            'Historical comment code root ' . $this->fixtureToken,
            1,
            0,
            10,
            11,
            '2026-01-15 12:00:00'
        );
        $this->createTrade($rootId, 6, 7.25, 'DBCN-' . $rootId, '2026-01-20 10:00:00');
        $this->createTrade($rootId, 6, 12.50, 'DBUN-' . $rootId, '2026-01-20 11:00:00');
        $this->createTrade($rootId, 6, -3.75, 'WBIN-' . $rootId, '2026-01-20 12:00:00');

        $response = $this->postLegacy($admin, '/index/admin/agents/agentsListSearch', [
            'page' => 1,
            'rows' => 20,
            'searchtype' => 'clickSearch',
            'userId' => $rootId,
            'user_cancel' => 1,
            'startdate' => '2026-01-01',
            'enddate' => '2026-01-31',
        ]);

        $response->assertOk();
        $this->assertSame('7.25', $response->json('rows.0.fy_money'));
        $this->assertSame('12.50', $response->json('rows.0.rj_money'));
        $this->assertSame('-3.75', $response->json('rows.0.qk_money'));
    }

    public function test_legacy_agent_sub_search_keeps_old_agents_outside_explicit_date_window(): void
    {
        $rootId = $this->unusedUserId();
        $oldChildId = $this->unusedUserId();
        $admin = $this->createRestrictedAdmin([$rootId]);
        $this->createUser($rootId, 'Date compatibility root ' . $this->fixtureToken, 1, 0, 10, 11, '2026-01-01 12:00:00');
        $this->createUser($oldChildId, 'Date compatibility old child ' . $this->fixtureToken, 1, $rootId, 20, 22, '2023-12-31 12:00:00');
        $this->createDescendant($rootId, $oldChildId, 1, 1, 1);

        $response = $this->postLegacy($admin, '/index/admin/agent/v2/agentsListSearchV2', [
            'page' => 1,
            'limit' => 20,
            'searchtype' => 'showSubAgents',
            'userPid' => $rootId,
            'user_cancel' => 1,
            'startdate' => '2026-01-01',
            'enddate' => '2026-01-31',
        ]);

        $response->assertOk();
        $this->assertSame(1, (int) $response->json('total'));
        $this->assertSame($oldChildId, (int) $response->json('rows.0.user_id'));
    }

    public function test_legacy_agent_search_without_searchtype_does_not_force_root_only_results(): void
    {
        $rootId = $this->unusedUserId();
        $childId = $this->unusedUserId();
        $admin = $this->createRestrictedAdmin([$rootId]);
        $this->createUser($rootId, 'Search type root ' . $this->fixtureToken, 1, 0, 10, 11, '2026-01-01 12:00:00');
        $this->createUser($childId, 'Search type child ' . $this->fixtureToken, 1, $rootId, 20, 22, '2026-01-02 12:00:00');
        $this->createDescendant($rootId, $childId, 1, 1, 1);

        $response = $this->postLegacy($admin, '/index/admin/agents/agentsListSearch', [
            'page' => 1,
            'rows' => 20,
        ]);

        $response->assertOk();
        $this->assertSame(2, (int) $response->json('total'));
        $this->assertEqualsCanonicalizing(
            [$rootId, $childId],
            collect($response->json('rows'))->pluck('user_id')->map(static function ($id): int {
                return (int) $id;
            })->all()
        );
    }

    public function test_big_agent_search_returns_only_scoped_assignments_with_fund_and_position_stats(): void
    {
        $rootId = $this->unusedUserId();
        $childId = $this->unusedUserId();
        $customerId = $this->unusedUserId();
        $hiddenRootId = $this->unusedUserId();
        $admin = $this->createRestrictedAdmin([$rootId]);

        $this->createUser($rootId, 'Big assigned root ' . $this->fixtureToken, 1, 0, 100, 110, '2026-01-10 12:00:00');
        $this->createUser($childId, 'Big assigned child ' . $this->fixtureToken, 1, $rootId, 200, 220, '2026-01-11 12:00:00');
        $this->createUser($customerId, 'Big assigned customer ' . $this->fixtureToken, 2, $childId, 50, 55, '2026-01-12 12:00:00');
        $this->createUser($hiddenRootId, 'Big hidden root ' . $this->fixtureToken, 1, 0, 9000, 9100, '2026-01-10 12:00:00');
        $this->createDescendant($rootId, $childId, 1, 1, 1);
        $this->createDescendant($rootId, $customerId, 2, 0, 2);

        $this->createTrade($rootId, 6, 7, 'agent rebate -FY', '2026-01-20 10:00:00');
        $this->createTrade($rootId, 6, 30, 'Deposit approved', '2026-01-20 11:00:00');
        $this->createTrade($childId, 6, -5, 'Withdrawal approved', '2026-01-20 12:00:00');
        $this->createTrade($childId, 0, 12, 'closed position', '2026-01-20 13:00:00', 250, -2, -1, 1);
        $this->createTrade($hiddenRootId, 6, 999, 'Deposit approved', '2026-01-20 14:00:00');

        $bigAgentId = $this->createBigAgentAt(
            [$rootId, $hiddenRootId],
            '2026-01-10 12:00:00'
        );
        $response = $this->postLegacy($admin, '/index/admin/bigAgents/agentsListSearch', [
            'big_id' => $bigAgentId,
            'startdate' => '2026-01-01',
            'enddate' => '2026-01-31',
        ]);

        $response->assertOk();
        $this->assertSame(1, (int) $response->json('total'));
        $this->assertSame($rootId, (int) $response->json('rows.0.sub_ag_id'));
        $this->assertSame('350.00', $response->json('rows.0.user_money'));
        $this->assertSame('385.00', $response->json('rows.0.cust_eqy'));
        $this->assertSame('7.00', $response->json('rows.0.total_fy'));
        $this->assertSame('30.00', $response->json('rows.0.total_rj'));
        $this->assertSame('-5.00', $response->json('rows.0.total_qk'));
        $this->assertSame('25.00', $response->json('rows.0.total_net_worth'));
        $this->assertSame('12.00', $response->json('rows.0.total_profit'));
        $this->assertSame('2.50', $response->json('rows.0.total_volume'));
        $this->assertSame('350.00', $response->json('footer.0.user_money'));
        $this->assertStringNotContainsString((string) $hiddenRootId, $response->getContent());
        $this->assertStringNotContainsString('999.00', $response->getContent());
    }

    public function test_big_agent_search_without_id_returns_all_big_agents_in_the_default_date_window(): void
    {
        $currentRootId = $this->unusedUserId();
        $oldRootId = $this->unusedUserId();
        $admin = $this->createRestrictedAdmin([$currentRootId, $oldRootId]);

        $this->createUser(
            $currentRootId,
            'Default big date current root ' . $this->fixtureToken,
            1,
            0,
            10,
            11,
            date('Y-m-d H:i:s', strtotime('-1 day'))
        );
        $this->createUser(
            $oldRootId,
            'Default big date old root ' . $this->fixtureToken,
            1,
            0,
            90,
            99,
            '2023-12-31 12:00:00'
        );

        $currentBigAgentId = $this->createBigAgentAt([$currentRootId], date('Y-m-d H:i:s', strtotime('-1 day')));
        $oldBigAgentId = $this->createBigAgentAt([$oldRootId], '2023-12-31 12:00:00');

        $response = $this->postLegacy($admin, '/index/admin/bigAgents/agentsListSearch', [
            'startdate' => '',
            'enddate' => '',
        ]);

        $response->assertOk();
        $this->assertSame(1, (int) $response->json('total'));
        $this->assertSame($currentRootId, (int) $response->json('rows.0.sub_ag_id'));
        $this->assertSame($currentBigAgentId, (int) $response->json('rows.0.id'));
        $returnedRows = collect($response->json('rows'));
        $this->assertNotContains(
            $oldRootId,
            $returnedRows->pluck('sub_ag_id')->map(static function ($id): int {
                return (int) $id;
            })->all()
        );
        $this->assertNotContains(
            $oldBigAgentId,
            $returnedRows->pluck('id')->map(static function ($id): int {
                return (int) $id;
            })->all()
        );
    }

    public function test_big_agent_total_counts_visible_big_agents_while_rows_expand_assigned_roots(): void
    {
        $firstRootId = $this->unusedUserId();
        $secondRootId = $this->unusedUserId();
        $admin = $this->createRestrictedAdmin([$firstRootId, $secondRootId]);
        $this->createUser($firstRootId, 'Multi-root first ' . $this->fixtureToken, 1, 0, 10, 11, '2026-01-01 12:00:00');
        $this->createUser($secondRootId, 'Multi-root second ' . $this->fixtureToken, 1, 0, 20, 22, '2026-01-02 12:00:00');
        $bigAgentId = $this->createBigAgentAt([$firstRootId, $secondRootId], '2026-01-01 12:00:00');

        $response = $this->postLegacy($admin, '/index/admin/bigAgents/agentsListSearch', [
            'big_id' => $bigAgentId,
            'startdate' => '2026-01-01',
            'enddate' => '2026-01-31',
        ]);

        $response->assertOk();
        $this->assertSame(1, (int) $response->json('total'));
        $this->assertCount(2, $response->json('rows'));
        $this->assertEqualsCanonicalizing(
            [$firstRootId, $secondRootId],
            collect($response->json('rows'))->pluck('sub_ag_id')->map(static function ($id): int {
                return (int) $id;
            })->all()
        );
    }

    public function test_big_agent_sub_search_accepts_both_parent_aliases_and_returns_direct_stat_rows(): void
    {
        $rootId = $this->unusedUserId();
        $childOneId = $this->unusedUserId();
        $childTwoId = $this->unusedUserId();
        $directCustomerId = $this->unusedUserId();
        $grandchildId = $this->unusedUserId();
        $admin = $this->createRestrictedAdmin([$rootId]);

        $this->createUser($rootId, 'Sub stats root ' . $this->fixtureToken, 1, 0, 10, 11, '2026-02-01 12:00:00');
        $this->createUser($childOneId, 'Sub stats child one ' . $this->fixtureToken, 1, $rootId, 20, 22, '2026-02-02 12:00:00');
        $this->createUser($childTwoId, 'Sub stats child two ' . $this->fixtureToken, 1, $rootId, 30, 33, '2026-02-03 12:00:00');
        $this->createUser($directCustomerId, 'Sub stats direct customer ' . $this->fixtureToken, 2, $rootId, 40, 44, '2026-02-04 12:00:00');
        $this->createUser($grandchildId, 'Sub stats grandchild ' . $this->fixtureToken, 1, $childOneId, 50, 55, '2026-02-05 12:00:00');
        $this->createDescendant($rootId, $childOneId, 1, 1, 1);
        $this->createDescendant($rootId, $childTwoId, 1, 1, 1);
        $this->createDescendant($rootId, $directCustomerId, 2, 1, 1);
        $this->createDescendant($rootId, $grandchildId, 1, 0, 2);
        $this->createDescendant($childOneId, $grandchildId, 1, 1, 1);
        $this->createTrade($grandchildId, 0, 8, 'grandchild position', '2026-02-10 12:00:00', 100, -1, 0, 1);

        $bigAgentId = $this->createBigAgent([$rootId]);
        $expectedChildIds = [$childOneId, $childTwoId];
        sort($expectedChildIds);
        foreach (['user_pid', 'userPId'] as $parentKey) {
            $response = $this->postLegacy($admin, '/index/admin/bigAgents/subAgentsListSearch', [
                'big_id' => $bigAgentId,
                $parentKey => $rootId,
                'startdate' => '2026-02-01',
                'enddate' => '2026-02-28',
            ]);

            $response->assertOk();
            $this->assertSame(2, (int) $response->json('total'), $parentKey);
            $rows = collect($response->json('rows'));
            $this->assertSame(
                $expectedChildIds,
                $rows->pluck('sub_ag_id')->map(static function ($id): int {
                    return (int) $id;
                })->sort()->values()->all(),
                $parentKey
            );
            $childOne = $rows->firstWhere('sub_ag_id', $childOneId);
            $this->assertSame('8.00', $childOne['total_profit'] ?? null, $parentKey);
            $this->assertSame('1.00', $childOne['total_volume'] ?? null, $parentKey);
            $this->assertArrayNotHasKey('descendant_id', $childOne, $parentKey);
        }
    }

    public function test_big_agent_sub_search_fails_closed_when_parent_is_not_assigned_to_big_agent(): void
    {
        $assignedRootId = $this->unusedUserId();
        $otherRootId = $this->unusedUserId();
        $otherChildId = $this->unusedUserId();
        $admin = $this->createRestrictedAdmin([$assignedRootId, $otherRootId]);

        $this->createUser($assignedRootId, 'Authorized big root ' . $this->fixtureToken, 1, 0, 10, 11, '2026-03-01 12:00:00');
        $this->createUser($otherRootId, 'Unassigned big root ' . $this->fixtureToken, 1, 0, 90, 99, '2026-03-01 12:00:00');
        $this->createUser($otherChildId, 'Unassigned big child ' . $this->fixtureToken, 1, $otherRootId, 900, 990, '2026-03-02 12:00:00');
        $this->createDescendant($otherRootId, $otherChildId, 1, 1, 1);
        $this->createTrade($otherChildId, 6, 777, 'Deposit approved', '2026-03-10 12:00:00');
        $bigAgentId = $this->createBigAgent([$assignedRootId]);

        $response = $this->postLegacy($admin, '/index/admin/bigAgents/subAgentsListSearch', [
            'big_id' => $bigAgentId,
            'user_pid' => $otherRootId,
        ]);

        $response->assertOk();
        $this->assertSame(0, (int) $response->json('total'));
        $this->assertSame([], $response->json('rows'));
        $this->assertIsArray($response->json('footer'));
        $this->assertStringNotContainsString((string) $otherChildId, $response->getContent());
        $this->assertStringNotContainsString('777.00', $response->getContent());
    }

    public function test_big_agent_sub_search_uses_the_default_date_window_when_dates_are_omitted(): void
    {
        $rootId = $this->unusedUserId();
        $childId = $this->unusedUserId();
        $admin = $this->createRestrictedAdmin([$rootId]);

        $this->createUser($rootId, 'Sub default date root ' . $this->fixtureToken, 1, 0, 10, 11, '2026-01-01 12:00:00');
        $this->createUser($childId, 'Sub default date child ' . $this->fixtureToken, 1, $rootId, 20, 22, '2026-01-02 12:00:00');
        $this->createDescendant($rootId, $childId, 1, 1, 1);
        $this->createTrade($childId, 0, 100, 'pre-default-window', '2023-12-31 12:00:00', 100, 0, 0, 1);
        $this->createTrade($childId, 0, 8, 'inside-default-window', '2026-01-10 12:00:00', 100, 0, 0, 1);
        $bigAgentId = $this->createBigAgentAt([$rootId], '2026-01-01 12:00:00');

        $response = $this->postLegacy($admin, '/index/admin/bigAgents/subAgentsListSearch', [
            'big_id' => $bigAgentId,
            'user_pid' => $rootId,
        ]);

        $response->assertOk();
        $this->assertSame(1, (int) $response->json('total'));
        $this->assertSame('8.00', $response->json('rows.0.total_profit'));
    }

    public function test_big_agent_position_stats_ignore_disabled_symbol_groups(): void
    {
        $rootId = $this->unusedUserId();
        $admin = $this->createRestrictedAdmin([$rootId]);
        $this->createUser($rootId, 'Symbol status root ' . $this->fixtureToken, 1, 0, 10, 11, '2026-01-01 12:00:00');
        $symbol = 'LG' . substr($this->fixtureToken, 0, 10);
        $this->createSymbolPrice($symbol, 2, 1);
        $this->createSymbolPrice($symbol, 6, 0);
        $this->createTrade($rootId, 0, 1, 'symbol-status-test', '2026-01-10 12:00:00', 100, 0, 0, 1, $symbol);
        $bigAgentId = $this->createBigAgentAt([$rootId], '2026-01-01 12:00:00');

        $response = $this->postLegacy($admin, '/index/admin/bigAgents/agentsListSearch', [
            'big_id' => $bigAgentId,
            'startdate' => '2026-01-01',
            'enddate' => '2026-01-31',
        ]);

        $response->assertOk();
        $this->assertSame('1.00', $response->json('rows.0.total_for_exca'));
        $this->assertSame('0.00', $response->json('rows.0.total_stock'));
    }

    /** @param array<int, int> $boundAgentIds */
    private function createRestrictedAdmin(array $boundAgentIds): Admin
    {
        $now = time();
        $roleId = (int) DB::table('roles')->insertGetId([
            'name' => 'legacy-agent-adapter-' . $this->fixtureToken . '-' . count($this->createdRowIds),
            'guard_type' => 'admin',
            'description' => 'Legacy agent adapter test role',
            'permissions' => json_encode([]),
            'status' => 1,
            'created_at' => $now,
            'updated_at' => $now,
            'deleted_at' => null,
        ]);
        $this->recordRowId('roles', $roleId);

        $scopeId = (int) DB::table('role_data_scopes')->insertGetId([
            'role_id' => $roleId,
            'scope_type' => 'agent_tree',
            'agent_ids' => null,
            'user_ids' => null,
            'status' => 1,
            'created_at' => $now,
            'updated_at' => $now,
            'deleted_at' => null,
        ]);
        $this->recordRowId('role_data_scopes', $scopeId);

        $adminId = $this->insertAdmin($roleId, $now);
        if ($adminId === 1) {
            $adminId = $this->insertAdmin($roleId, $now);
        }

        foreach ($boundAgentIds as $agentId) {
            $bindingId = (int) DB::table('admin_agent_bindings')->insertGetId([
                'admin_id' => $adminId,
                'agent_id' => $agentId,
                'binding_type' => 'primary',
                'status' => 1,
                'created_at' => $now,
                'updated_at' => $now,
                'deleted_at' => null,
            ]);
            $this->recordRowId('admin_agent_bindings', $bindingId);
        }

        return Admin::query()->findOrFail($adminId);
    }

    private function insertAdmin(int $roleId, int $now): int
    {
        $sequence = count($this->createdRowIds['admins'] ?? []);
        $adminId = (int) DB::table('admins')->insertGetId([
            'username' => 'legacy-agent-adapter-' . $this->fixtureToken . '-' . $sequence,
            'email' => 'legacy-agent-adapter-' . $this->fixtureToken . '-' . $sequence . '@example.test',
            'password' => bcrypt('password'),
            'role_id' => $roleId,
            'status' => 1,
            'created_at' => $now,
            'updated_at' => $now,
            'deleted_at' => null,
        ]);
        $this->recordRowId('admins', $adminId);

        return $adminId;
    }

    private function createUser(
        int $userId,
        string $userName,
        int $accountType,
        int $parentId,
        float $balance,
        float $equity,
        string $createdAt,
        int $authStatus = 1,
        int $tradingMode = 0,
        int $confirmed = 1
    ): void {
        $timestamp = strtotime($createdAt);
        $loginId = (int) DB::table('user_logins')->insertGetId([
            'user_id' => $userId,
            'email' => 'legacy-agent-adapter-' . $this->fixtureToken . '-' . $userId . '@example.test',
            'password' => bcrypt('password'),
            'account_type' => $accountType,
            'is_enabled' => 1,
            'is_cancelled' => 0,
            'source_type' => 0,
            'created_at' => $timestamp,
            'updated_at' => $timestamp,
            'deleted_at' => null,
        ]);
        $this->recordRowId('user_logins', $loginId);

        $userInfoId = (int) DB::table('user_infos')->insertGetId([
            'user_id' => $userId,
            'login_id' => $loginId,
            'user_name' => $userName,
            'phone' => '188' . substr((string) $userId, -8),
            'account_type' => $accountType,
            'parent_id' => $parentId,
            'family_tree' => $parentId > 0 ? ',' . $parentId . ',' . $userId . ',' : ',' . $userId . ',',
            'level_id' => $accountType === 1 ? 2 : 0,
            'group_id' => 3,
            'comm_rate' => 1,
            'auth_status' => $authStatus,
            'trading_mode' => $tradingMode,
            'is_agent_confirmed' => $confirmed,
            'total_funds' => $balance,
            'equity' => $equity,
            'risk_ratio' => 88.8,
            'mt4_group' => 'fixture-group',
            'created_at' => $timestamp,
            'updated_at' => $timestamp,
            'deleted_at' => null,
        ]);
        $this->recordRowId('user_infos', $userInfoId);
    }

    private function createDescendant(
        int $agentId,
        int $descendantId,
        int $descendantType,
        int $isDirect,
        int $depth
    ): void {
        $now = time();
        $id = (int) DB::table('agent_descendants')->insertGetId([
            'agent_id' => $agentId,
            'descendant_id' => $descendantId,
            'descendant_type' => $descendantType,
            'is_direct' => $isDirect,
            'depth' => $depth,
            'created_at' => $now,
            'updated_at' => $now,
            'deleted_at' => null,
        ]);
        $this->recordRowId('agent_descendants', $id);
    }

    private function createTrade(
        int $userId,
        int $cmd,
        float $profit,
        string $comment,
        string $closeTime,
        int $volume = 0,
        float $commission = 0,
        float $swaps = 0,
        float $marginRate = 0,
        string $symbol = 'EURUSD'
    ): void {
        $timestamp = strtotime($closeTime);
        $id = (int) DB::table('user_trades')->insertGetId([
            'user_id' => $userId,
            'ticket' => $this->unusedTicket(),
            'symbol' => $symbol,
            'digits' => 2,
            'cmd' => $cmd,
            'volume' => $volume,
            'open_time' => $closeTime,
            'open_price' => 0,
            'stop_loss' => 0,
            'take_profit' => 0,
            'close_time' => $closeTime,
            'expiration' => null,
            'reason' => 0,
            'conv_rate1' => 0,
            'conv_rate2' => 0,
            'commission' => $commission,
            'commission_agent' => 0,
            'swaps' => $swaps,
            'close_price' => 0,
            'profit' => $profit,
            'taxes' => 0,
            'comment' => $comment,
            'internal_id' => 0,
            'margin_rate' => $marginRate,
            'timestamp_val' => $timestamp,
            'magic' => 0,
            'gw_volume' => 0,
            'gw_open_price' => 0,
            'gw_close_price' => 0,
            'modify_time' => $closeTime,
            'settlement_status' => 0,
            'settled_at' => null,
            'created_at' => $timestamp,
            'updated_at' => $timestamp,
            'deleted_at' => null,
        ]);
        $this->recordRowId('user_trades', $id);
    }

    /** @param array<int, int> $subAgentIds */
    private function createBigAgent(array $subAgentIds): int
    {
        return $this->createBigAgentAt($subAgentIds, date('Y-m-d H:i:s'));
    }

    /** @param array<int, int> $subAgentIds */
    private function createBigAgentAt(array $subAgentIds, string $createdAt): int
    {
        $timestamp = strtotime($createdAt);
        $id = (int) DB::table('big_agents')->insertGetId([
            'email' => 'legacy-big-' . $this->fixtureToken . '-' . count($this->createdRowIds['big_agents'] ?? []) . '@example.test',
            'username' => 'legacy-big-' . $this->fixtureToken,
            'password' => bcrypt('password'),
            'sub_agent_ids' => implode(',', $subAgentIds),
            'is_enabled' => 1,
            'created_by' => 'fixture-' . $this->fixtureToken,
            'created_at' => $timestamp,
            'updated_at' => $timestamp,
            'deleted_at' => null,
        ]);
        $this->recordRowId('big_agents', $id);

        return $id;
    }

    private function createSymbolPrice(string $symbol, int $groupId, int $status): void
    {
        $now = date('Y-m-d H:i:s');
        $timestamp = time();
        $id = (int) DB::table('symbol_prices')->insertGetId([
            'symbol' => $symbol,
            'time' => $now,
            'bid' => 1,
            'ask' => 1,
            'low' => 1,
            'high' => 1,
            'direction' => 0,
            'digits' => 2,
            'spread' => 0,
            'group_id' => $groupId,
            'status' => $status,
            'modify_time' => $now,
            'created_at' => $timestamp,
            'updated_at' => $timestamp,
            'deleted_at' => null,
        ]);
        $this->recordRowId('symbol_prices', $id);
    }

    private function postLegacy(Admin $admin, string $uri, array $payload): TestResponse
    {
        return $this->withoutMiddleware(LegacyAdminAuthenticate::class)
            ->actingAs($admin, 'admin')
            ->postJson($uri, $payload);
    }

    private function unusedUserId(): int
    {
        for ($attempt = 0; $attempt < 200; $attempt++) {
            $candidate = random_int(1000000000, 1900000000);
            if (in_array($candidate, $this->reservedUserIds, true)) {
                continue;
            }

            $used = DB::table('user_infos')->where('user_id', $candidate)->exists()
                || DB::table('user_logins')->where('user_id', $candidate)->exists()
                || DB::table('user_trades')->where('user_id', $candidate)->exists()
                || DB::table('admin_agent_bindings')->where('agent_id', $candidate)->exists()
                || DB::table('agent_descendants')
                    ->where('agent_id', $candidate)
                    ->orWhere('descendant_id', $candidate)
                    ->exists();

            if (!$used) {
                $this->reservedUserIds[] = $candidate;

                return $candidate;
            }
        }

        throw new \RuntimeException('Unable to allocate an unused legacy adapter user ID.');
    }

    private function unusedTicket(): int
    {
        for ($attempt = 0; $attempt < 200; $attempt++) {
            $candidate = random_int(1000000000, 1900000000);
            if (in_array($candidate, $this->reservedTickets, true)) {
                continue;
            }
            if (DB::table('user_trades')->where('ticket', $candidate)->exists()) {
                continue;
            }

            $this->reservedTickets[] = $candidate;

            return $candidate;
        }

        throw new \RuntimeException('Unable to allocate an unused legacy adapter trade ticket.');
    }

    private function recordRowId(string $table, int $id): void
    {
        $this->createdRowIds[$table][] = $id;
    }

    /** @param array<int, string> $cleanupFailures */
    private function cleanupFixture(array &$cleanupFailures): void
    {
        foreach (self::CLEANUP_ORDER as $table) {
            $ids = $this->createdRowIds[$table] ?? [];
            if ($ids === []) {
                continue;
            }

            try {
                DB::table($table)->whereIn('id', $ids)->delete();
            } catch (\Throwable $exception) {
                $cleanupFailures[] = $table . ': ' . $exception->getMessage();
            }
        }
    }

    private function abortFixtureSetup(\Throwable $cause): void
    {
        $failures = [];
        $this->cleanupFixture($failures);

        try {
            if ($this->autoIncrementSnapshot !== null) {
                $this->autoIncrementSnapshot->restore();
            }
        } catch (\Throwable $exception) {
            $failures[] = 'auto_increment_restore: ' . $exception->getMessage();
        }

        try {
            if ($this->fixtureMutex !== null) {
                $this->fixtureMutex->releaseWithDisconnectFallback();
            }
        } catch (\Throwable $exception) {
            $failures[] = 'mutex_release: ' . $exception->getMessage();
        }

        try {
            parent::tearDown();
        } catch (\Throwable $exception) {
            $failures[] = 'parent_teardown: ' . $exception->getMessage();
        }

        $this->resetFixtureState();

        if ($failures !== []) {
            throw new \RuntimeException(
                'Legacy agent adapter fixture setup failed: ' . implode(' | ', $failures),
                0,
                $cause
            );
        }

        throw $cause;
    }

    private function resetFixtureState(): void
    {
        $this->createdRowIds = [];
        $this->reservedUserIds = [];
        $this->reservedTickets = [];
        $this->fixtureToken = '';
        $this->autoIncrementSnapshot = null;
        $this->fixtureMutex = null;
        $this->tableFingerprints = [];
    }
}
