<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/02
 * Time: 01:50
 */

/**
 * 前端大数代理商列表与订单-申请人边界封闭模块测试。
 *
 * 文件功能：
 * - 验证普通客户账号（account_type=2）无法通过 big_agent_id / bigAgentId 伪装参数读取大数代理商的代理列表。
 * - 验证普通客户账号无法通过伪装参数读取大数代理商的平仓/持仓订单列表。
 * - 验证最终权限检查清单文档记录了该边界封闭模块。
 *
 * 适用场景：
 * - 前端大数代理商列表与订单接口的权限边界回归测试，防止客户账号越权读取大数代理数据。
 *
 * 入参例子：
 * - POST /user/agents/proxy/proxySearch（body: { "big_agent_id": 4121401 }）
 * - POST /user/agents/proxy/proxySearchBySub（body: { "bigAgentId": 4121401 }）
 * - POST /user/agents/close/closeOrderSearch（body: { "big_agent_id": 4121402 }）
 * - POST /user/agents/open/openOrderSearch（body: { "bigAgentId": 4121402 }）
 *
 * 返回值：
 * - 各接口均返回 HTTP 200，rows 为空数组、total 为 0，且响应中不含可见代理商数据。
 *
 * 异常或失败场景：
 * - 若客户账号能读到代理或订单数据（rows/total 非空），测试失败。
 */

namespace Tests\Feature;

use App\Http\Middleware\JwtAuthMiddleware;
use App\Http\Middleware\SingleSignOn;
use App\Models\UserLogin;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class FrontBigNumberListAndOrderApplicantBoundaryClosureModuleTest extends TestCase
{
    use DatabaseTransactions;

    /**
     * 验证客户账号无法伪装大数代理商查询代理列表。
     *
     * 构造客户账号、可见代理商与大数代理商后，分别请求 proxySearch 与 proxySearchBySub，
     * 断言 rows/total 为空且响应不含可见代理商。
     */
    public function test_customer_account_cannot_spoof_big_agent_proxy_list_searches(): void
    {
        $customerId = 412140100;
        $visibleAgentId = 412140101;
        $bigAgentId = 4121401;

        $this->deleteFixtureRows([$customerId, $visibleAgentId], $bigAgentId, []);
        $this->insertUserInfo($customerId, 'big-list-boundary-customer', 2, 0);
        $this->insertUserInfo($visibleAgentId, 'big-list-visible-agent', 1, 0);
        $this->insertBigAgent($bigAgentId, $visibleAgentId);

        $login = UserLogin::where('user_id', $customerId)->firstOrFail();

        $direct = $this->withoutMiddleware([JwtAuthMiddleware::class, SingleSignOn::class])
            ->actingAs($login, 'user')
            ->postJson('/user/agents/proxy/proxySearch', [
                'big_agent_id' => $bigAgentId,
            ]);
        $direct->assertOk()
            ->assertJsonPath('rows', [])
            ->assertJsonPath('total', 0);
        $this->assertStringNotContainsString((string) $visibleAgentId, $direct->getContent());
        $this->assertStringNotContainsString('big-list-visible-agent', $direct->getContent());

        $withSub = $this->withoutMiddleware([JwtAuthMiddleware::class, SingleSignOn::class])
            ->actingAs($login, 'user')
            ->postJson('/user/agents/proxy/proxySearchBySub', [
                'bigAgentId' => $bigAgentId,
            ]);
        $withSub->assertOk()
            ->assertJsonPath('rows', [])
            ->assertJsonPath('total', 0);
        $this->assertStringNotContainsString((string) $visibleAgentId, $withSub->getContent());
        $this->assertStringNotContainsString('big-list-visible-agent', $withSub->getContent());
    }

    /**
     * 验证客户账号无法伪装大数代理商查询平仓/持仓订单列表。
     *
     * 构造可见客户的平仓与持仓订单后，请求 closeOrderSearch 与 openOrderSearch，
     * 断言 rows/total 为空且响应不含订单与客户数据。
     */
    public function test_customer_account_cannot_spoof_big_agent_order_searches(): void
    {
        $customerId = 412140200;
        $visibleAgentId = 412140201;
        $visibleCustomerId = 412140202;
        $bigAgentId = 4121402;
        $closedTicket = 41214021;
        $openTicket = 41214022;

        $this->deleteFixtureRows([$customerId, $visibleAgentId, $visibleCustomerId], $bigAgentId, [$closedTicket, $openTicket]);
        $this->insertUserInfo($customerId, 'big-order-boundary-customer', 2, 0);
        $this->insertUserInfo($visibleAgentId, 'big-order-visible-agent', 1, 0);
        $this->insertUserInfo($visibleCustomerId, 'big-order-visible-customer', 2, $visibleAgentId);
        $this->insertBigAgent($bigAgentId, $visibleAgentId);
        $this->insertTrade($visibleCustomerId, $closedTicket, '2026-07-09 12:00:00');
        $this->insertTrade($visibleCustomerId, $openTicket, '1970-01-01 00:00:00');

        $login = UserLogin::where('user_id', $customerId)->firstOrFail();

        $closed = $this->withoutMiddleware([JwtAuthMiddleware::class, SingleSignOn::class])
            ->actingAs($login, 'user')
            ->postJson('/user/agents/close/closeOrderSearch', [
                'big_agent_id' => $bigAgentId,
            ]);
        $closed->assertOk()
            ->assertJsonPath('rows', [])
            ->assertJsonPath('total', 0);
        $this->assertStringNotContainsString((string) $closedTicket, $closed->getContent());
        $this->assertStringNotContainsString((string) $visibleCustomerId, $closed->getContent());

        $open = $this->withoutMiddleware([JwtAuthMiddleware::class, SingleSignOn::class])
            ->actingAs($login, 'user')
            ->postJson('/user/agents/open/openOrderSearch', [
                'bigAgentId' => $bigAgentId,
            ]);
        $open->assertOk()
            ->assertJsonPath('rows', [])
            ->assertJsonPath('total', 0);
        $this->assertStringNotContainsString((string) $openTicket, $open->getContent());
        $this->assertStringNotContainsString((string) $visibleCustomerId, $open->getContent());
    }

    /**
     * 验证最终权限检查清单记录了本次边界封闭模块。
     *
     * 断言清单包含第 214 项、proxySearch、closeOrderSearch、openOrderSearch 及本测试类名。
     */
    public function test_final_checklist_records_big_number_list_and_order_applicant_boundary(): void
    {
        $checklist = file_get_contents(base_path('docs/admin-backend-blade-permission-final-checklist.md')) ?: '';

        $this->assertStringContainsString('## 214.', $checklist);
        $this->assertStringContainsString('proxySearch', $checklist);
        $this->assertStringContainsString('closeOrderSearch', $checklist);
        $this->assertStringContainsString('openOrderSearch', $checklist);
        $this->assertStringContainsString('currentBigAgent', $checklist);
        $this->assertStringContainsString('FrontBigNumberListAndOrderApplicantBoundaryClosureModuleTest', $checklist);
    }

    /**
     * 插入带父子关系的测试用户数据。
     *
     * @param int $userId 用户 ID。
     * @param string $userName 用户名。
     * @param int $accountType 账号类型（1=代理商，2=客户）。
     * @param int $parentId 上级用户 ID（0 表示无上级）。
     * @return void 无返回值，仅写入数据库。
     */
    private function insertUserInfo(int $userId, string $userName, int $accountType, int $parentId): void
    {
        $now = time();

        DB::table('user_infos')->where('user_id', $userId)->delete();
        DB::table('user_logins')->where('user_id', $userId)->delete();

        $loginId = DB::table('user_logins')->insertGetId([
            'user_id' => $userId,
            'email' => 'front-big-list-order-boundary-' . $userId . '@example.test',
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
            'deleted_at' => null,
        ]);

        DB::table('user_infos')->insert([
            'user_id' => $userId,
            'login_id' => $loginId,
            'user_name' => $userName,
            'phone' => '1782140' . substr((string) $userId, -4),
            'gender' => 1,
            'account_type' => $accountType,
            'parent_id' => $parentId,
            'family_tree' => $parentId > 0 ? $parentId . ',' . $userId : '',
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
            'created_at' => $now,
            'updated_at' => $now,
            'deleted_at' => null,
        ]);
    }

    /**
     * 插入一条大数代理商记录，并挂接可见子代理商。
     *
     * @param int $bigAgentId 大数代理商 ID。
     * @param int $visibleAgentId 挂接的子代理商 ID。
     * @return void 无返回值，仅写入数据库。
     */
    private function insertBigAgent(int $bigAgentId, int $visibleAgentId): void
    {
        $now = time();

        DB::table('big_agents')->where('id', $bigAgentId)->delete();
        DB::table('big_agents')->insert([
            'id' => $bigAgentId,
            'email' => 'front-big-list-order-boundary-' . $bigAgentId . '@example.test',
            'username' => 'front-big-list-order-boundary-' . $bigAgentId,
            'password' => Hash::make('password'),
            'sub_agent_ids' => (string) $visibleAgentId,
            'is_enabled' => 1,
            'jwt_token_id' => '',
            'created_by' => 'phpunit',
            'created_at' => $now,
            'updated_at' => $now,
            'deleted_at' => null,
        ]);
    }

    /**
     * 插入一条用户交易订单记录。
     *
     * @param int $userId 所属用户 ID。
     * @param int $ticket 订单票号。
     * @param string $closeTime 平仓时间（1970-01-01 表示未平仓）。
     * @return void 无返回值，仅写入数据库。
     */
    private function insertTrade(int $userId, int $ticket, string $closeTime): void
    {
        $now = time();

        DB::table('user_trades')->insert([
            'user_id' => $userId,
            'ticket' => $ticket,
            'symbol' => 'XAUUSD',
            'digits' => 2,
            'cmd' => 0,
            'volume' => 100,
            'open_time' => '2026-07-09 10:00:00',
            'open_price' => 2300.10,
            'stop_loss' => 0,
            'take_profit' => 0,
            'close_time' => $closeTime,
            'expiration' => null,
            'reason' => 0,
            'conv_rate1' => 1,
            'conv_rate2' => 1,
            'commission' => 0,
            'commission_agent' => 0,
            'swaps' => 0,
            'close_price' => 2301.20,
            'profit' => 10.50,
            'taxes' => 0,
            'comment' => 'big list order boundary fixture',
            'internal_id' => 0,
            'margin_rate' => 1,
            'timestamp_val' => $now,
            'magic' => 0,
            'gw_volume' => 0,
            'gw_open_price' => 0,
            'gw_close_price' => 0,
            'modify_time' => '2026-07-09 12:00:00',
            'settlement_status' => 0,
            'settled_at' => null,
            'created_at' => $now,
            'updated_at' => $now,
            'deleted_at' => null,
        ]);
    }

    /**
     * 清理指定用户的交易、大数代理商、用户信息及层级关系测试数据。
     *
     * @param array<int, int> $userIds 待清理的用户 ID 列表。
     * @param int $bigAgentId 待清理的大数代理商 ID。
     * @param array<int, int> $tickets 待清理的订单票号列表。
     * @return void 无返回值。
     */
    private function deleteFixtureRows(array $userIds, int $bigAgentId, array $tickets): void
    {
        DB::table('user_trades')
            ->whereIn('user_id', $userIds)
            ->orWhereIn('ticket', $tickets)
            ->delete();
        DB::table('big_agents')->where('id', $bigAgentId)->delete();
        DB::table('user_infos')->whereIn('user_id', $userIds)->delete();
        DB::table('user_logins')->whereIn('user_id', $userIds)->delete();
        DB::table('agent_descendants')
            ->where(function ($query) use ($userIds) {
                $query->whereIn('agent_id', $userIds)
                    ->orWhereIn('descendant_id', $userIds);
            })
            ->delete();
    }
}
