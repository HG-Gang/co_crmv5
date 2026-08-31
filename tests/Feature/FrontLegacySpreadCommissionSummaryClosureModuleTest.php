<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/02
 * Time: 05:10
 */

namespace Tests\Feature;

use App\Contracts\DepositSettlementGateway;
use App\Services\Legacy\LegacySpreadCommissionSummaryService;
use App\Services\Payment\DepositSettlementResult;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * 旧前台 comm_summaryv2 点差返佣闭环测试。
 *
 * 文件功能：
 * - 固定旧 V2 路由必须执行点差返佣任务，不能渲染持仓汇总页面。
 * - 验证普通、特殊倍率和无手续费组的旧金额公式，以及配置缺失时的资金保护状态。
 *
 * 测试边界：
 * - 所有夹具均使用固定测试账号和数据库事务，不能扫描共享库中的真实待结算交易。
 * - MT4 网关由受控实现替代，只记录调用参数，不访问外部交易服务器。
 */
class FrontLegacySpreadCommissionSummaryClosureModuleTest extends TestCase
{
    use DatabaseTransactions;

    /**
     * 验证旧 V2 入口不再错误渲染持仓 Blade。
     *
     * @return void 路由必须保持原 URL 和 GET 方法，并指向专用点差返佣控制器。
     */
    public function test_legacy_comm_summary_v2_route_points_to_the_dedicated_spread_settlement_controller(): void
    {
        $route = Route::getRoutes()->getByName('legacy_user_position_comm_summary_v2_page');

        $this->assertNotNull($route);
        $this->assertSame(
            'App\\Http\\Controllers\\Front\\LegacySpreadCommissionSummaryController@commSummaryV2',
            $route->getActionName()
        );
    }

    /**
     * 验证旧点差 10 使用直属点差比例与上级差额比例结算。
     *
     * 计算示例：
     * - 200 成交量表示 2 手；直属代理比例 12，金额为 12 * 2 = 24。
     * - 上级代理比例 16、直属代理比例 12，金额为 (16 - 12) * 2 = 8。
     *
     * @return void 断言入金金额、幂等行为、计算类型快照和源交易完成状态。
     */
    public function test_legacy_comm_summary_v2_settles_standard_spread_ratio_chain_idempotently(): void
    {
        $rootAgentId = 412541100;
        $directAgentId = 412541101;
        $customerId = 412541102;
        [$rootAgentGroupId, $directAgentGroupId, $customerGroupId] = $this->createGroups(true);
        $this->insertUser($rootAgentId, 'spread-root-agent', 1, 0, $rootAgentGroupId, 80);
        $this->insertUser($directAgentId, 'spread-direct-agent', 1, $rootAgentId, $directAgentGroupId, 60);
        $this->insertUser($customerId, 'spread-customer', 2, $directAgentId, $customerGroupId, 0);
        $this->insertSpreadConfig(10, $directAgentGroupId, 12);
        $this->insertSpreadConfig(10, $rootAgentGroupId, 16);
        $this->insertSymbol('LEGSPREAD10A', 10);
        $tradeId = $this->insertClosedTrade($customerId, 125411001, 'LEGSPREAD10A', 200);
        $calls = [];

        $this->bindGateway([
            DepositSettlementResult::settled('91254101'),
            DepositSettlementResult::settled('91254102'),
        ], $calls);

        $summary = $this->settleFixtureTrade($tradeId);

        $this->assertSame(2, $summary['settled_count']);
        $this->assertSame(0, $summary['failed_count']);
        $this->assertSame([
            [$directAgentId, '24.00', 'DBCN-' . $customerId . '-#125411001'],
            [$rootAgentId, '8.00', 'DBCN-' . $customerId . '-#125411001'],
        ], $calls);
        $this->assertSame(1, (int) DB::table('user_trades')->where('id', $tradeId)->value('settlement_status'));
        $this->assertSame(2, DB::table('commission_rebate_payouts')
            ->where('source_trade_id', $tradeId)
            ->where('calculation_type', 'legacy_spread_comm_summary')
            ->where('status', 'settled')
            ->count());

        $this->assertSame(0, $this->settleFixtureTrade($tradeId)['settled_count']);
        $this->assertCount(2, $calls);
    }

    /**
     * 验证旧特殊点差使用 0.1 倍率，并保留无手续费客户组的直属减 50 规则。
     *
     * 计算示例：
     * - 点差 13、无手续费组、直属比例 60： (60 - 50) * 2 * 0.1 = 2。
     * - 上级比例 80、直属比例 60： (80 - 60) * 2 * 0.1 = 4。
     *
     * @return void 断言特殊品种不会错误套用普通点差或组基数公式。
     */
    public function test_legacy_comm_summary_v2_applies_special_spread_multiplier_and_no_commission_group_rule(): void
    {
        $rootAgentId = 412541200;
        $directAgentId = 412541201;
        $customerId = 412541202;
        [$rootAgentGroupId, $directAgentGroupId, $customerGroupId] = $this->createGroups(false);
        $this->insertUser($rootAgentId, 'special-root-agent', 1, 0, $rootAgentGroupId, 80);
        $this->insertUser($directAgentId, 'special-direct-agent', 1, $rootAgentId, $directAgentGroupId, 60);
        $this->insertUser($customerId, 'special-customer', 2, $directAgentId, $customerGroupId, 0);
        $this->insertSpreadConfig(13, $directAgentGroupId, 60);
        $this->insertSpreadConfig(13, $rootAgentGroupId, 80);
        $this->insertSymbol('LEGSPREAD13A', 13);
        $tradeId = $this->insertClosedTrade($customerId, 125412001, 'LEGSPREAD13A', 200);
        $calls = [];

        $this->bindGateway([
            DepositSettlementResult::settled('91254201'),
            DepositSettlementResult::settled('91254202'),
        ], $calls);

        $summary = $this->settleFixtureTrade($tradeId);

        $this->assertSame(2, $summary['settled_count']);
        $this->assertSame([
            [$directAgentId, '2.00', 'DBCN-' . $customerId . '-#125412001'],
            [$rootAgentId, '4.00', 'DBCN-' . $customerId . '-#125412001'],
        ], $calls);
        $this->assertSame(1, (int) DB::table('user_trades')->where('id', $tradeId)->value('settlement_status'));
    }

    /**
     * 验证缺少启用品种配置时不允许按零金额完成源交易。
     *
     * @return void 断言不调用 MT4、源交易仍待结算，并把交易单号加入 24 小时配置异常隔离缓存。
     */
    public function test_legacy_comm_summary_v2_keeps_trade_pending_when_symbol_configuration_is_missing(): void
    {
        $agentId = 412541300;
        $customerId = 412541301;
        [, $agentGroupId, $customerGroupId] = $this->createGroups(true);
        $this->insertUser($agentId, 'missing-symbol-agent', 1, 0, $agentGroupId, 60);
        $this->insertUser($customerId, 'missing-symbol-customer', 2, $agentId, $customerGroupId, 0);
        $tradeId = $this->insertClosedTrade($customerId, 125413001, 'LEGMISSPREADA', 200);
        $calls = [];

        $this->bindGateway([], $calls);
        $summary = $this->settleFixtureTrade($tradeId);

        $this->assertSame(1, $summary['failed_count']);
        $this->assertSame(0, (int) DB::table('user_trades')->where('id', $tradeId)->value('settlement_status'));
        $this->assertSame([], $calls);
        $this->assertTrue(Cache::has('legacy_spread_commission_missing_symbol:125413001'));
    }

    /**
     * 创建代理组和客户组。
     *
     * @param bool $customerHasCommission true 对应旧 group_id=1；false 对应旧 group_id=0。
     * @return array{0: int, 1: int, 2: int} 返回上级代理组、直属代理组和客户组主键。
     */
    private function createGroups(bool $customerHasCommission): array
    {
        $now = time();
        $rootAgentGroupId = DB::table('group_configs')->insertGetId([
            'pair_id' => null,
            'name' => 'spread-root-agent-' . uniqid('', true),
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
        $directAgentGroupId = DB::table('group_configs')->insertGetId([
            'pair_id' => null,
            'name' => 'spread-direct-agent-' . uniqid('', true),
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
            'name' => 'spread-customer-' . uniqid('', true),
            'radix' => 50,
            'category' => 2,
            'has_commission' => $customerHasCommission ? 1 : 0,
            'is_enabled' => 1,
            'is_ecn' => 0,
            'is_default' => 0,
            'created_by' => 0,
            'updated_by' => 0,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return [$rootAgentGroupId, $directAgentGroupId, $customerGroupId];
    }

    /**
     * 写入测试代理或普通客户及其可用登录资料。
     *
     * @param int $userId 业务用户 ID，也是测试 MT4 账号。
     * @param string $name 用户名称前缀。
     * @param int $accountType 1=代理，2=普通客户。
     * @param int $parentId 上级代理业务用户 ID。
     * @param int $groupId 当前用户组主键。
     * @param int $commRate 旧 comm_prop 映射后的返佣比例。
     * @return void 夹具数据会随数据库事务自动回滚。
     */
    private function insertUser(int $userId, string $name, int $accountType, int $parentId, int $groupId, int $commRate): void
    {
        $now = time();
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
            'family_tree' => (string) $userId,
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
     * 写入旧点差配置映射。
     *
     * @param int $spread 旧算法会比较的整数点差。
     * @param int $agentGroupId 收款代理组主键。
     * @param int $ratio 该点差下的返佣比例。
     * @return void 当前配置启用后才允许服务计算点差返佣。
     */
    private function insertSpreadConfig(int $spread, int $agentGroupId, int $ratio): void
    {
        DB::table('spread_configs')->where('spread', $spread)->where('agent_group_id', $agentGroupId)->delete();
        DB::table('spread_configs')->insert([
            'spread' => $spread,
            'agent_group_id' => $agentGroupId,
            'spread_ratio' => $ratio,
            'status' => 1,
            'created_at' => time(),
            'updated_at' => time(),
        ]);
    }

    /**
     * 写入启用的测试交易品种。
     *
     * @param string $symbol 交易品种编码。
     * @param int $spread 旧 V2 支持的整数点差。
     * @return void 价格字段仅满足表结构，返佣只读取 spread 和 status。
     */
    private function insertSymbol(string $symbol, int $spread): void
    {
        $now = time();
        DB::table('symbol_prices')->where('symbol', $symbol)->delete();
        DB::table('symbol_prices')->insert([
            'symbol' => $symbol,
            'time' => '2026-07-25 10:00:00',
            'bid' => 100,
            'ask' => 101,
            'low' => 99,
            'high' => 102,
            'direction' => 0,
            'digits' => 2,
            'spread' => $spread,
            'group_id' => 1,
            'status' => 1,
            'modify_time' => '2026-07-25 10:00:00',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    /**
     * 写入符合旧 V2 筛选条件的平仓交易。
     *
     * @param int $userId 交易所属用户。
     * @param int $ticket 旧 MT4 交易单号。
     * @param string $symbol 交易品种编码。
     * @param int $volume 200 表示 2 手。
     * @return int 返回 user_trades 主键。
     */
    private function insertClosedTrade(int $userId, int $ticket, string $symbol, int $volume): int
    {
        $now = time();
        $previousTradeIds = DB::table('user_trades')->where('user_id', $userId)->where('ticket', $ticket)->pluck('id')->all();
        if ($previousTradeIds !== []) {
            DB::table('commission_rebate_payouts')->whereIn('source_trade_id', $previousTradeIds)->delete();
            DB::table('user_trades')->whereIn('id', $previousTradeIds)->delete();
        }

        return DB::table('user_trades')->insertGetId([
            'user_id' => $userId,
            'ticket' => $ticket,
            'symbol' => $symbol,
            'digits' => 2,
            'cmd' => 0,
            'volume' => $volume,
            'open_time' => '2026-07-24 09:00:00',
            'open_price' => 100,
            'close_time' => '2026-07-24 10:00:00',
            'close_price' => 101,
            'commission' => 0,
            'profit' => 1,
            'margin_rate' => 1,
            'comment' => '',
            'modify_time' => '2026-07-24 10:00:00',
            'settlement_status' => 0,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    /**
     * 注入受控 MT4 网关并记录每次入金参数。
     *
     * @param array<int, DepositSettlementResult> $results 按调用顺序返回的外部结果。
     * @param array<int, array{0: int, 1: string, 2: string}> $calls 捕获的账号、金额和备注。
     * @return void 网关只在当前测试应用容器内生效。
     */
    private function bindGateway(array $results, array &$calls): void
    {
        $this->app->instance(DepositSettlementGateway::class, new class($results, $calls) implements DepositSettlementGateway {
            /**
             * 预设的结算结果序列，多次调用逐个弹出。驱动点差佣金发放的成功/失败/重试分支。
             * @var array<int, DepositSettlementResult>
             */
            private $results;

            /**
             * 引用传入的调用捕获表。deposit() 记下 [userId, amount, comment]，断言发放命令的次数与参数。
             * @var array<int, array{0: int, 1: string, 2: string}>
             */
            private $calls;

            /**
             * @param array<int, DepositSettlementResult> $results 预设 MT4 返回结果。
             * @param array<int, array{0: int, 1: string, 2: string}> $calls 调用记录容器。
             */
            public function __construct(array $results, array &$calls)
            {
                $this->results = $results;
                $this->calls = &$calls;
            }

            /**
             * 记录一次模拟 MT4 入金。
             *
             * @param int $userId 收款代理账号。
             * @param string $amount 固定两位小数的返佣金额。
             * @param string $comment 旧 DBCN 幂等备注。
             * @return DepositSettlementResult 返回预设外部处理结果。
             */
            public function deposit(int $userId, string $amount, string $comment): DepositSettlementResult
            {
                $this->calls[] = [$userId, $amount, $comment];

                return array_shift($this->results);
            }
        });
    }

    /**
     * 只运行真实服务中单条交易的点差返佣链，隔离共享数据库的批次选取。
     *
     * @param int $tradeId 当前测试创建的源交易主键。
     * @return array<string, int> 返回真实服务汇总计数。
     */
    private function settleFixtureTrade(int $tradeId): array
    {
        $service = new LegacySpreadCommissionSummaryService($this->app->make(DepositSettlementGateway::class));
        $summary = [
            'scanned_count' => 1,
            'settled_count' => 0,
            'retryable_count' => 0,
            'failed_count' => 0,
            'skipped_count' => 0,
            'completed_trade_count' => 0,
        ];
        $runner = \Closure::bind(function (int $id, array &$result): void {
            $this->settleSpreadTrade($id, $result);
        }, $service, LegacySpreadCommissionSummaryService::class);

        $runner($tradeId, $summary);

        return $summary;
    }
}
