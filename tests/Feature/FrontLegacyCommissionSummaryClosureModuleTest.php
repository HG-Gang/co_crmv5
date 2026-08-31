<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/02
 * Time: 05:10
 */

/**
 * FrontLegacyCommissionSummaryClosureModuleTest
 *
 * 文件功能：
 * - 验证旧前台返佣汇总闭环：路由指向专用结算控制器、按费率差逐级结算并关闭源交易、同交易重复触发幂等、未发送支付可重试、网关异常标记未知且不自动重发。
 * - 输入：HTTP 请求与事务回滚的数据库夹具；输出：PHPUnit 断言结果。
 * - 明确不负责：不覆盖真实 MT4 网关与线上支付/出金通道（由网关契约测试锁定）。
 */

namespace Tests\Feature;

use App\Contracts\DepositSettlementGateway;
use App\Services\Legacy\LegacyCommissionSummaryService;
use App\Services\Payment\DepositSettlementResult;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * 旧前台 comm_summary 返佣汇总闭环测试。
 *
 * 适用场景：
 * - 旧定时入口 GET /user/position/comm_summary 扫描已平仓交易，为每一层上级代理计算并发放返佣。
 * - 同一交易可能有多个上级代理，任一层失败都不能把原交易误标记为已结算。
 *
 * 验证边界：
 * - 返佣金额使用“上级比例减直属下级比例”的差额，并使用收款代理组的 radix。
 * - 同一交易和同一收款代理只能向 MT4 发起一次入金。
 * - MT4 明确表示未发送时保留待重试状态，不能生成已结算账本或标记源交易完成。
 */
class FrontLegacyCommissionSummaryClosureModuleTest extends TestCase
{
    use DatabaseTransactions;

    /**
     * 验证旧路由已从错误的持仓 Blade 映射为返佣批处理控制器。
     *
     * @return void 断言路由名称对应的控制器方法，避免以后被页面路由回退覆盖。
     */
    public function test_legacy_comm_summary_route_points_to_the_dedicated_settlement_controller(): void
    {
        $route = Route::getRoutes()->getByName('legacy_user_position_comm_summary_page');

        $this->assertNotNull($route);
        $this->assertSame(
            'App\\Http\\Controllers\\Front\\LegacyCommissionSummaryController@commSummary',
            $route->getActionName()
        );
    }

    /**
     * 验证旧入口会为直属和间接上级分别按比例差额结算，并在全部成功后标记源交易。
     *
     * 计算示例：
     * - 成交量 200 表示 2 手；直属代理比例 60、客户比例 0、基数 50，金额为 2 * 60% * 50 = 60。
     * - 上级代理比例 80、直属代理比例 60、基数 50，金额为 2 * 20% * 50 = 20。
     *
     * @return void 断言新路由返回汇总、MT4 入金顺序、返佣账本和源交易完成状态。
     */
    public function test_legacy_comm_summary_settles_each_ancestor_by_rate_difference_and_closes_the_source_trade(): void
    {
        $rootAgentId = 412540100;
        $directAgentId = 412540101;
        $customerId = 412540102;
        [$agentGroupId, $customerGroupId] = $this->createCommissionGroups();
        $this->insertUser($rootAgentId, 'comm-root-agent', 1, 0, (string) $rootAgentId, $agentGroupId, 80);
        $this->insertUser($directAgentId, 'comm-direct-agent', 1, $rootAgentId, $rootAgentId . ',' . $directAgentId, $agentGroupId, 60);
        $this->insertUser($customerId, 'comm-customer', 2, $directAgentId, $rootAgentId . ',' . $directAgentId . ',' . $customerId, $customerGroupId, 0);
        $tradeId = $this->insertClosedTrade($customerId, 125401001, 200);
        $calls = [];

        $this->bindGateway([
            DepositSettlementResult::settled('91254001'),
            DepositSettlementResult::settled('91254002'),
        ], $calls);

        $summary = $this->settleFixtureTrade($tradeId);

        $this->assertSame(2, $summary['settled_count']);
        $this->assertSame(0, $summary['failed_count']);
        $this->assertSame([
            [$directAgentId, '60.00', 'DBCN-' . $customerId . '-#125401001'],
            [$rootAgentId, '20.00', 'DBCN-' . $customerId . '-#125401001'],
        ], $calls);
        $this->assertSame(1, (int) DB::table('user_trades')->where('id', $tradeId)->value('settlement_status'));
        $this->assertSame(2, DB::table('commission_rebate_payouts')->where('source_trade_id', $tradeId)->where('status', 'settled')->count());
        $this->assertSame(2, DB::table('commission_records')
            ->where('data_type', 'legacy_comm_summary')
            ->where('remarks', 'DBCN-' . $customerId . '-#125401001')
            ->count());
    }

    /**
     * 验证重复访问旧入口不会对已经成功的代理返佣再次调用 MT4。
     *
     * @return void 断言唯一返佣标识会复用已有结算记录，外部入金只执行首次两笔。
     */
    public function test_legacy_comm_summary_is_idempotent_when_the_same_trade_is_triggered_twice(): void
    {
        $rootAgentId = 412540200;
        $directAgentId = 412540201;
        $customerId = 412540202;
        [$agentGroupId, $customerGroupId] = $this->createCommissionGroups();
        $this->insertUser($rootAgentId, 'idem-root-agent', 1, 0, (string) $rootAgentId, $agentGroupId, 80);
        $this->insertUser($directAgentId, 'idem-direct-agent', 1, $rootAgentId, $rootAgentId . ',' . $directAgentId, $agentGroupId, 60);
        $this->insertUser($customerId, 'idem-customer', 2, $directAgentId, $rootAgentId . ',' . $directAgentId . ',' . $customerId, $customerGroupId, 0);
        $tradeId = $this->insertClosedTrade($customerId, 125402001, 200);
        $calls = [];

        $this->bindGateway([
            DepositSettlementResult::settled('91254201'),
            DepositSettlementResult::settled('91254202'),
        ], $calls);
        $this->assertSame(2, $this->settleFixtureTrade($tradeId)['settled_count']);
        $this->assertSame(0, $this->settleFixtureTrade($tradeId)['settled_count']);

        $this->assertCount(2, $calls);
        $this->assertSame(2, DB::table('commission_rebate_payouts')->where('source_trade_id', $tradeId)->count());
        $this->assertSame(2, DB::table('commission_records')
            ->where('data_type', 'legacy_comm_summary')
            ->where('remarks', 'DBCN-' . $customerId . '-#125402001')
            ->count());
        $this->assertSame(1, (int) DB::table('user_trades')->where('id', $tradeId)->value('settlement_status'));
    }

    /**
     * 验证 MT4 明确未发送时必须保留重试机会，且不能把交易或账本伪装成成功。
     *
     * @return void 断言第一次失败后源交易未结算；人工将重试时间置为当前后，第二次可安全完成同一笔返佣。
     */
    public function test_legacy_comm_summary_keeps_not_sent_payout_retryable_without_marking_the_trade_settled(): void
    {
        $agentId = 412540300;
        $customerId = 412540301;
        [$agentGroupId, $customerGroupId] = $this->createCommissionGroups();
        $this->insertUser($agentId, 'retry-agent', 1, 0, (string) $agentId, $agentGroupId, 60);
        $this->insertUser($customerId, 'retry-customer', 2, $agentId, $agentId . ',' . $customerId, $customerGroupId, 0);
        $tradeId = $this->insertClosedTrade($customerId, 125403001, 200);
        $calls = [];

        $this->bindGateway([DepositSettlementResult::retryableNotSent('connection_failed')], $calls);
        $firstSummary = $this->settleFixtureTrade($tradeId);
        $this->assertSame(0, $firstSummary['settled_count']);
        $this->assertSame(1, $firstSummary['retryable_count']);

        $this->assertSame(0, (int) DB::table('user_trades')->where('id', $tradeId)->value('settlement_status'));
        $payout = DB::table('commission_rebate_payouts')->where('source_trade_id', $tradeId)->first();
        $this->assertSame('retryable', $payout->status);
        $this->assertSame(0, DB::table('commission_records')
            ->where('data_type', 'legacy_comm_summary')
            ->where('remarks', 'DBCN-' . $customerId . '-#125403001')
            ->count());

        DB::table('commission_rebate_payouts')->where('id', $payout->id)->update(['available_at' => time() - 1]);
        $this->bindGateway([DepositSettlementResult::settled('91254301')], $calls);
        $this->assertSame(1, $this->settleFixtureTrade($tradeId)['settled_count']);

        $this->assertCount(2, $calls);
        $this->assertSame(1, (int) DB::table('user_trades')->where('id', $tradeId)->value('settlement_status'));
        $this->assertSame('settled', DB::table('commission_rebate_payouts')->where('id', $payout->id)->value('status'));
    }

    /**
     * 验证 MT4 调用异常属于发送结果未知，必须立即冻结自动重试。
     *
     * @return void 断言异常不会把返佣留在 processing，也不会在下一次扫描时重复发送。
     */
    public function test_legacy_comm_summary_marks_gateway_exception_unknown_without_automatic_resend(): void
    {
        $agentId = 412540400;
        $customerId = 412540401;
        [$agentGroupId, $customerGroupId] = $this->createCommissionGroups();
        $this->insertUser($agentId, 'unknown-agent', 1, 0, (string) $agentId, $agentGroupId, 60);
        $this->insertUser($customerId, 'unknown-customer', 2, $agentId, $agentId . ',' . $customerId, $customerGroupId, 0);
        $tradeId = $this->insertClosedTrade($customerId, 125404001, 200);
        $calls = [];

        // 外部连接在命令是否送达前中断时，应用不能猜测为“未发送”。
        $this->app->instance(DepositSettlementGateway::class, new class($calls) implements DepositSettlementGateway {
            /**
             * 引用传入的调用捕获表。deposit() 记下 [userId, amount, comment]，
             * 断言佣金汇总链路在连接中断场景下不猜测"未发送"。
             * @var array<int, array{0: int, 1: string, 2: string}>
             */
            private $calls;

            /**
             * @param array<int, array{0: int, 1: string, 2: string}> $calls 捕获实际尝试发送的返佣命令。
             */
            public function __construct(array &$calls)
            {
                $this->calls = &$calls;
            }

            /**
             * 模拟 MT4 在未知发送阶段中断。
             *
             * @param int $userId 收款代理账号。
             * @param string $amount 返佣金额。
             * @param string $comment 返佣幂等备注。
             * @return DepositSettlementResult 此分支始终抛出异常，因此没有可确认的返回结果。
             *
             * @throws \RuntimeException 模拟网络或底层客户端异常。
             */
            public function deposit(int $userId, string $amount, string $comment): DepositSettlementResult
            {
                $this->calls[] = [$userId, $amount, $comment];

                throw new \RuntimeException('gateway_transport_interrupted');
            }
        });

        $summary = $this->settleFixtureTrade($tradeId);

        $this->assertSame(1, $summary['failed_count']);
        $this->assertSame(0, (int) DB::table('user_trades')->where('id', $tradeId)->value('settlement_status'));
        $this->assertSame('unknown', DB::table('commission_rebate_payouts')->where('source_trade_id', $tradeId)->value('status'));
        $this->assertCount(1, $calls);

        $this->bindGateway([DepositSettlementResult::settled('91254401')], $calls);
        $this->assertSame(0, $this->settleFixtureTrade($tradeId)['settled_count']);
        $this->assertCount(1, $calls);
    }

    /**
     * 写入一组可参与返佣的客户组和两个代理组基数。
     *
     * @return array{0: int, 1: int} 返回代理组 ID 与客户组 ID，供用户资料的 group_id 引用。
     */
    private function createCommissionGroups(): array
    {
        $now = time();
        $agentGroupId = DB::table('group_configs')->insertGetId([
            'pair_id' => null,
            'name' => 'comm-summary-agent-' . uniqid('', true),
            'radix' => 50,
            'category' => 1,
            'has_commission' => 1,
            'is_enabled' => 1,
            'is_ecn' => 0,
            'is_default' => 0,
            'created_by' => 0,
            'updated_by' => 0,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $customerGroupId = DB::table('group_configs')->insertGetId([
            'pair_id' => null,
            'name' => 'comm-summary-customer-' . uniqid('', true),
            'radix' => 0,
            'category' => 2,
            'has_commission' => 1,
            'is_enabled' => 1,
            'is_ecn' => 0,
            'is_default' => 0,
            'created_by' => 0,
            'updated_by' => 0,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return [$agentGroupId, $customerGroupId];
    }

    /**
     * 写入返佣链条中的代理或普通客户资料，以及配套的可用登录记录。
     *
     * @param int $userId 业务用户 ID，也是旧 MT4 登录号。
     * @param string $name 用户显示名称。
     * @param int $accountType 账号类型，1=代理、2=普通客户。
     * @param int $parentId 直属上级代理业务用户 ID，根代理传 0。
     * @param string $familyTree 从根代理到当前用户的逗号分隔链路。
     * @param int $groupId 返佣组配置 ID。
     * @param int $commRate 旧 comm_prop 映射后的返佣比例，单位为百分比整数。
     * @return void 当前测试事务结束时自动回滚夹具数据。
     */
    private function insertUser(
        int $userId,
        string $name,
        int $accountType,
        int $parentId,
        string $familyTree,
        int $groupId,
        int $commRate
    ): void {
        $now = time();
        // 仅清理本测试固定业务 ID 的历史异常残留，避免共享测试库中的上次中断数据污染本次断言。
        DB::table('user_infos')->where('user_id', $userId)->delete();
        DB::table('user_logins')->where('user_id', $userId)->delete();
        $loginId = DB::table('user_logins')->insertGetId([
            'user_id' => $userId,
            'email' => $name . '-' . uniqid('', true) . '@example.test',
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
            'user_name' => $name,
            'phone' => '',
            'gender' => 0,
            'account_type' => $accountType,
            'parent_id' => $parentId,
            'family_tree' => $familyTree,
            'group_id' => $groupId,
            'level_id' => 0,
            'comm_rate' => $commRate,
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
            'is_agent_confirmed' => $accountType === 1 ? 1 : 0,
            'mt4_group' => '',
            'settle_method' => 1,
            'settle_cycle' => 0,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    /**
     * 写入符合旧 comm_summary 筛选条件的已平仓交易。
     *
     * @param int $userId 交易所属普通客户的业务用户 ID。
     * @param int $ticket 旧 MT4 交易单号。
     * @param int $volume MT4 成交量，200 表示 2 手。
     * @return int 返回 user_trades 主键，供返佣唯一性和结算状态断言使用。
     */
    private function insertClosedTrade(int $userId, int $ticket, int $volume): int
    {
        $now = time();
        $previousTradeIds = DB::table('user_trades')
            ->where('user_id', $userId)
            ->where('ticket', $ticket)
            ->pluck('id')
            ->all();
        if ($previousTradeIds !== []) {
            DB::table('commission_rebate_payouts')->whereIn('source_trade_id', $previousTradeIds)->delete();
            DB::table('user_trades')->whereIn('id', $previousTradeIds)->delete();
        }

        return DB::table('user_trades')->insertGetId([
            'user_id' => $userId,
            'ticket' => $ticket,
            'symbol' => 'XAUUSD',
            'digits' => 2,
            'cmd' => 0,
            'volume' => $volume,
            'open_time' => '2026-07-24 09:00:00',
            'open_price' => 100,
            'close_time' => '2026-07-24 10:00:00',
            'close_price' => 101,
            'commission' => -10,
            'profit' => 1,
            'margin_rate' => 1,
            'modify_time' => '2026-07-24 10:00:00',
            'settlement_status' => 0,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    /**
     * 向服务容器注入可控 MT4 入金网关，避免测试访问真实交易服务器。
     *
     * @param array<int, DepositSettlementResult> $results 按调用顺序返回的 MT4 结果集合。
     * @param array<int, array{0: int, 1: string, 2: string}> $calls 捕获的入金调用参数。
     * @return DepositSettlementGateway 返回已写入服务容器的受控入金网关。
     */
    private function bindGateway(array $results, array &$calls): DepositSettlementGateway
    {
        $gateway = new class($results, $calls) implements DepositSettlementGateway {
            /**
             * 预设的结算结果序列，多次调用逐个弹出。驱动佣金发放先失败后成功等重试场景。
             * @var array<int, DepositSettlementResult>
             */
            private $results;

            /**
             * 引用传入的调用捕获表。deposit() 记下入参，断言调用次数与参数。
             * @var array<int, array{0: int, 1: string, 2: string}>
             */
            private $calls;

            /**
             * @param array<int, DepositSettlementResult> $results 预设 MT4 返回结果。
             * @param array<int, array{0: int, 1: string, 2: string}> $calls 对外部调用的记录容器。
             */
            public function __construct(array $results, array &$calls)
            {
                $this->results = $results;
                $this->calls = &$calls;
            }

            /**
             * 模拟 MT4 入金，并记录调用时的账号、金额和备注。
             *
             * @param int $userId 收款代理的 MT4 账号。
             * @param string $amount 本次返佣金额，固定保留两位小数。
             * @param string $comment 旧 MT4 入金备注，用于外部系统人工核对。
             * @return DepositSettlementResult 返回预设成功、可重试或失败结果。
             */
            public function deposit(int $userId, string $amount, string $comment): DepositSettlementResult
            {
                $this->calls[] = [$userId, $amount, $comment];

                return array_shift($this->results);
            }
        };
        $this->app->instance(DepositSettlementGateway::class, $gateway);

        return $gateway;
    }

    /**
     * 在不扫描共享数据库其他历史交易的前提下，执行真实服务中的单交易闭环。
     *
     * 说明：
     * - 公开旧路由必须保留“扫描全部待处理交易”的真实生产语义，不能为了测试增加筛选参数。
     * - 此处通过受控闭包调用同一个私有处理方法，只隔离批次选取边界，不替换返佣计算、幂等、MT4 状态或账本代码。
     *
     * @param int $tradeId 本测试创建的 user_trades 主键。
     * @return array<string, int> 返回真实服务对该交易产生的汇总计数。
     */
    private function settleFixtureTrade(int $tradeId): array
    {
        $service = new LegacyCommissionSummaryService($this->app->make(DepositSettlementGateway::class));
        $summary = [
            'scanned_count' => 1,
            'settled_count' => 0,
            'retryable_count' => 0,
            'failed_count' => 0,
            'skipped_count' => 0,
            'completed_trade_count' => 0,
        ];
        $runner = \Closure::bind(function (int $id, array &$result): void {
            $this->settleTrade($id, $result);
        }, $service, LegacyCommissionSummaryService::class);

        $runner($tradeId, $summary);

        return $summary;
    }
}
