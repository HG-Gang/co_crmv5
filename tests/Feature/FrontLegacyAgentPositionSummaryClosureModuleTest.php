<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/07/25
 * Time: 19:19
 */

declare(strict_types=1);

namespace Tests\Feature;

use App\Constants\ResponseCode;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * 旧前台代理持仓汇总闭环测试。
 *
 * 文件功能：
 * - 固定 autoSearch 的数据范围：当前代理、直属代理及其客户均应参与 MT4 汇总。
 * - 固定旧 MT4 余额备注、返点备注、已平仓条件和净入金公式，防止错误改用入出金申请表或账户 equity。
 *
 * 执行结果：
 * - 断言通过表示旧主查询入口按代理网络返回一条汇总根行。
 * - 断言失败表示用户范围、MT4 统计口径或金额公式与旧项目不一致。
 */
class FrontLegacyAgentPositionSummaryClosureModuleTest extends TestCase
{
    use DatabaseTransactions;

    /**
     * 验证初始代理汇总按全量下级 MT4 交易计算资金、返点和已平仓字段。
     *
     * @return void 成功时根代理行聚合本人、下级代理和下级客户，且净入金为入金减出金绝对值再加返点。
     */
    public function test_auto_search_aggregates_the_agent_network_with_legacy_mt4_rules(): void
    {
        $rootAgentId = 513001001;
        $childAgentId = 513001002;
        $customerId = 513001003;

        $this->insertUser($rootAgentId, '代理汇总根节点', 1, 0, (string) $rootAgentId);
        $this->insertUser($childAgentId, '代理汇总下级代理', 1, $rootAgentId, $rootAgentId . ',' . $childAgentId);
        $this->insertUser($customerId, '代理汇总下级客户', 2, $childAgentId, $rootAgentId . ',' . $childAgentId . ',' . $customerId);
        $this->insertSymbol('LPAUTOXAU', 1);

        $this->insertTrade($rootAgentId, 513001101, 6, 0, 1000, 'DBAA-20260725');
        $this->insertTrade($childAgentId, 513001102, 6, 0, 2000, 'DBAA-20260725');
        $this->insertTrade($customerId, 513001103, 6, 0, 3000, 'DBAA-20260725');
        $this->insertTrade($childAgentId, 513001104, 6, 0, -100, 'WBAA-20260725');
        $this->insertTrade($rootAgentId, 513001105, 6, 0, 50, 'DBCN-20260725');
        $this->insertTrade($rootAgentId, 513001106, 0, 100, 100, '', -2, -1, 1);
        $this->insertTrade($childAgentId, 513001107, 0, 100, 200, '', -3, -2, 1);
        $this->insertTrade($customerId, 513001108, 0, 100, 300, '', -4, -3, 1);

        $response = $this->withSession(['suser' => ['user_id' => $rootAgentId]])
            ->postJson('/user/position/positionSummarySearch', [
                'searchtype' => 'autoSearch',
                'startdate' => '2026-07-25',
                'enddate' => '2026-07-25',
                'per_page' => 20,
            ]);

        $response
            ->assertOk()
            ->assertJsonPath('code', ResponseCode::SUCCESS)
            ->assertJsonPath('data.list.data.0.user_id', $rootAgentId)
            ->assertJsonPath('data.list.data.0.total_yuerj', '6000.00')
            ->assertJsonPath('data.list.data.0.total_yuecj', '-100.00')
            ->assertJsonPath('data.list.data.0.total_rebate', '50.00')
            ->assertJsonPath('data.list.data.0.total_net_worth', '5950.00')
            ->assertJsonPath('data.list.data.0.total_profit', '600.00')
            ->assertJsonPath('data.list.data.0.total_comm', '9.00')
            ->assertJsonPath('data.list.data.0.total_noble_metal', '3.00')
            ->assertJsonPath('data.list.data.0.total_volume', '3.00')
            ->assertJsonPath('data.list.data.0.total_swaps', '-6.00');
    }

    /**
     * 验证直属代理下钻按 userPId 返回直属代理，并为每个代理聚合完整下级网络。
     *
     * 执行结果：
     * - 断言通过表示旧页面点击代理编号后，会把该代理的直属代理作为表格行。
     * - 每一行的统计范围包含该代理本人和所有后代客户，避免只统计直属客户而漏算间接客户。
     *
     * @return void 成功时返回下级代理行及其完整 MT4 汇总。
     */
    public function test_sub_agents_search_uses_requested_parent_and_aggregates_each_agent_network(): void
    {
        [$rootAgentId, $childAgentId] = $this->seedAgentNetwork();

        $response = $this->withSession(['suser' => ['user_id' => $rootAgentId]])
            ->postJson('/user/position/v2/subAgentsListSearchV2', [
                'searchtype' => 'subAgentsSearch',
                'userPId' => $rootAgentId,
                'startdate' => '2026-07-25',
                'enddate' => '2026-07-25',
                'per_page' => 20,
            ]);

        $response
            ->assertOk()
            ->assertJsonPath('code', ResponseCode::SUCCESS)
            ->assertJsonPath('data.list.data.0.user_id', $childAgentId)
            ->assertJsonPath('data.list.data.0.total_yuerj', '5000.00')
            ->assertJsonPath('data.list.data.0.total_yuecj', '-100.00')
            ->assertJsonPath('data.list.data.0.total_rebate', '0.00')
            ->assertJsonPath('data.list.data.0.total_net_worth', '4900.00')
            ->assertJsonPath('data.list.data.0.total_profit', '500.00')
            ->assertJsonPath('data.list.data.0.total_comm', '7.00')
            ->assertJsonPath('data.list.data.0.total_noble_metal', '2.00')
            ->assertJsonPath('data.list.data.0.total_volume', '2.00')
            ->assertJsonPath('data.list.data.0.total_swaps', '-5.00');
    }

    /**
     * 验证主汇总入口携带 userId 时会查询已授权的指定代理，而不会静默回退为根代理。
     *
     * @return void 成功时返回指定代理及其完整下级网络的 MT4 汇总。
     */
    public function test_main_summary_user_id_filter_returns_the_authorized_agent_summary(): void
    {
        [$rootAgentId, $childAgentId] = $this->seedAgentNetwork();

        $response = $this->withSession(['suser' => ['user_id' => $rootAgentId]])
            ->postJson('/user/position/positionSummarySearch', [
                'userId' => $childAgentId,
                'startdate' => '2026-07-25',
                'enddate' => '2026-07-25',
                'per_page' => 20,
            ]);

        $response
            ->assertOk()
            ->assertJsonPath('code', ResponseCode::SUCCESS)
            ->assertJsonPath('data.list.data.0.user_id', $childAgentId)
            ->assertJsonPath('data.list.data.0.total_yuerj', '5000.00')
            ->assertJsonPath('data.list.data.0.total_profit', '500.00');
    }

    /**
     * 验证现代页面的代理姓名筛选会在当前代理可见网络内查找匹配代理。
     *
     * @return void 成功时返回命名匹配代理的完整下级 MT4 汇总，未授权代理不会进入筛选范围。
     */
    public function test_main_summary_user_name_filter_returns_matching_authorized_agent_summary(): void
    {
        [$rootAgentId, $childAgentId] = $this->seedAgentNetwork();

        $response = $this->withSession(['suser' => ['user_id' => $rootAgentId]])
            ->postJson('/user/position/positionSummarySearch', [
                'userName' => '下级代理',
                'startdate' => '2026-07-25',
                'enddate' => '2026-07-25',
                'per_page' => 20,
            ]);

        $response
            ->assertOk()
            ->assertJsonPath('code', ResponseCode::SUCCESS)
            ->assertJsonPath('data.list.data.0.user_id', $childAgentId)
            ->assertJsonPath('data.list.data.0.total_yuerj', '5000.00');
    }

    /**
     * 验证未携带 userPId 的下级汇总请求默认展示当前代理的直属代理。
     *
     * @return void 成功时返回当前登录代理的直属代理汇总，而不是校验失败或跨代理全表查询。
     */
    public function test_sub_agents_search_defaults_to_the_current_agent_when_parent_id_is_omitted(): void
    {
        [$rootAgentId, $childAgentId] = $this->seedAgentNetwork();

        $response = $this->withSession(['suser' => ['user_id' => $rootAgentId]])
            ->postJson('/user/position/v2/subAgentsListSearchV2', [
                'startdate' => '2026-07-25',
                'enddate' => '2026-07-25',
                'per_page' => 20,
            ]);

        $response
            ->assertOk()
            ->assertJsonPath('code', ResponseCode::SUCCESS)
            ->assertJsonPath('data.list.data.0.user_id', $childAgentId)
            ->assertJsonPath('data.list.data.0.total_yuerj', '5000.00');
    }

    /**
     * 验证旧页面按交易账号搜索时返回代理汇总，而不是交易订单明细。
     *
     * @return void 成功时响应包含代理汇总 list，且只统计指定代理可见的下级网络。
     */
    public function test_click_search_returns_authorized_agent_summary_instead_of_trade_details(): void
    {
        [$rootAgentId, $childAgentId] = $this->seedAgentNetwork();

        $response = $this->withSession(['suser' => ['user_id' => $rootAgentId]])
            ->postJson('/user/position/v2/positionSummaryClickSearch', [
                'searchtype' => 'clickSearch',
                'userId' => $childAgentId,
                'startdate' => '2026-07-25',
                'enddate' => '2026-07-25',
                'per_page' => 20,
            ]);

        $response
            ->assertOk()
            ->assertJsonPath('code', ResponseCode::SUCCESS)
            ->assertJsonPath('data.list.data.0.user_id', $childAgentId)
            ->assertJsonPath('data.list.data.0.total_yuerj', '5000.00')
            ->assertJsonPath('data.list.data.0.total_profit', '500.00')
            ->assertJsonStructure(['data' => ['list' => ['data']]]);

        // 旧 Laravel 测试响应没有 assertJsonMissingPath，直接检查汇总行不含交易订单字段。
        self::assertArrayNotHasKey('ticket', $response->json('data.list.data.0'));
    }

    /**
     * 验证下钻请求不可通过伪造 userPId 越权查看其他代理网络。
     *
     * @return void 成功时返回权限不足业务码，且不返回外部代理的数据。
     */
    public function test_sub_agents_search_rejects_an_outside_parent_id(): void
    {
        [$rootAgentId] = $this->seedAgentNetwork();
        $outsideAgentId = 513001099;
        $this->insertUser($outsideAgentId, '代理汇总外部节点', 1, 0, (string) $outsideAgentId);

        $response = $this->withSession(['suser' => ['user_id' => $rootAgentId]])
            ->postJson('/user/position/v2/subAgentsListSearchV2', [
                'searchtype' => 'subAgentsSearch',
                'userPId' => $outsideAgentId,
                'startdate' => '2026-07-25',
                'enddate' => '2026-07-25',
            ]);

        $response
            ->assertOk()
            ->assertJsonPath('code', ResponseCode::PERMISSION_DENIED);
    }

    /**
     * 写入根代理、直属代理、直属客户和同日 MT4 交易夹具。
     *
     * @return array{0: int, 1: int, 2: int} 依次返回根代理、下级代理和下级客户业务用户 ID。
     */
    private function seedAgentNetwork(): array
    {
        $rootAgentId = 513001001;
        $childAgentId = 513001002;
        $customerId = 513001003;

        $this->insertUser($rootAgentId, '代理汇总根节点', 1, 0, (string) $rootAgentId);
        $this->insertUser($childAgentId, '代理汇总下级代理', 1, $rootAgentId, $rootAgentId . ',' . $childAgentId);
        $this->insertUser($customerId, '代理汇总下级客户', 2, $childAgentId, $rootAgentId . ',' . $childAgentId . ',' . $customerId);
        $this->insertSymbol('LPAUTOXAU', 1);

        $this->insertTrade($rootAgentId, 513001101, 6, 0, 1000, 'DBAA-20260725');
        $this->insertTrade($childAgentId, 513001102, 6, 0, 2000, 'DBAA-20260725');
        $this->insertTrade($customerId, 513001103, 6, 0, 3000, 'DBAA-20260725');
        $this->insertTrade($childAgentId, 513001104, 6, 0, -100, 'WBAA-20260725');
        $this->insertTrade($rootAgentId, 513001105, 6, 0, 50, 'DBCN-20260725');
        $this->insertTrade($rootAgentId, 513001106, 0, 100, 100, '', -2, -1, 1);
        $this->insertTrade($childAgentId, 513001107, 0, 100, 200, '', -3, -2, 1);
        $this->insertTrade($customerId, 513001108, 0, 100, 300, '', -4, -3, 1);

        return [$rootAgentId, $childAgentId, $customerId];
    }

    /**
     * 写入可被旧 session 中间件和代理树查询识别的用户、登录与层级资料。
     *
     * @param int $userId MT4 登录号和业务用户 ID。
     * @param string $userName 汇总表展示名称。
     * @param int $accountType 1 表示代理，2 表示普通客户。
     * @param int $parentId 直属上级业务用户 ID。
     * @param string $familyTree 从根节点到当前用户的旧层级路径。
     * @return void 测试事务结束时自动回滚夹具数据。
     */
    private function insertUser(int $userId, string $userName, int $accountType, int $parentId, string $familyTree): void
    {
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
            'email' => 'legacy-agent-position-' . $userId . '@example.test',
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

    /**
     * 写入启用的旧品种分组，供贵金属手数分类使用。
     *
     * @param string $symbol MT4 品种编码。
     * @param int $groupId 旧品种组，1 表示贵金属。
     * @return void 后续交易汇总会按此配置归类成交量。
     */
    private function insertSymbol(string $symbol, int $groupId): void
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
            'spread' => 10,
            'group_id' => $groupId,
            'status' => 1,
            'modify_time' => '2026-07-25 10:00:00',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    /**
     * 写入旧 MT4 余额变动或已平仓交易夹具。
     *
     * @param int $userId 交易所属用户。
     * @param int $ticket MT4 唯一单号。
     * @param int $cmd 6 表示余额变动，0 表示买入已平仓交易。
     * @param int $volume 原始手数，100 等于 1 手。
     * @param float $profit 余额变动金额或已平仓盈亏。
     * @param string $comment MT4 备注代码。
     * @param float $commission 已平仓手续费。
     * @param float $swaps 已平仓库存费。
     * @param float $marginRate 非零时表示有效已平仓订单。
     * @return void 所有交易时间固定在同一个查询日期，保证断言可重复。
     */
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
            'symbol' => 'LPAUTOXAU',
            'digits' => 2,
            'cmd' => $cmd,
            'volume' => $volume,
            'open_time' => '2026-07-25 09:00:00',
            'open_price' => 100,
            'close_time' => '2026-07-25 10:00:00',
            'close_price' => 101,
            'commission' => $commission,
            'swaps' => $swaps,
            'profit' => $profit,
            'margin_rate' => $marginRate,
            'comment' => $comment,
            'modify_time' => '2026-07-25 10:00:00',
            'settlement_status' => 0,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }
}
