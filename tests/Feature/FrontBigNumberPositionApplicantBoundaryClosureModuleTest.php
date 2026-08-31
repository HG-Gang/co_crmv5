<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/02
 * Time: 01:50
 */

/**
 * 前端大数代理商持仓汇总-申请人边界封闭模块测试。
 *
 * 文件功能：
 * - 验证普通客户账号（account_type=2）无法通过 big_agent_id / bigAgentId 伪装参数读取大数代理商持仓汇总。
 * - 验证普通客户账号无法通过伪装参数读取大数代理商子代理持仓汇总。
 * - 验证已登录的大数代理商会话（session bigAgents）可以正常读取自身持仓汇总。
 * - 验证最终权限检查清单文档记录了该边界封闭模块。
 *
 * 适用场景：
 * - 前端大数代理商持仓汇总接口的权限边界回归测试。
 *
 * 入参例子：
 * - POST /user/agents/position/positionSummarySearch（body: { "big_agent_id": 4120701 }）
 * - POST /user/agents/position/subAgentsListSearch（body: { "bigAgentId": 4120702 }）
 * - 大数代理商会话：withSession(['bigAgents' => ['id' => 4120703]])
 *
 * 返回值：
 * - 客户账号请求返回 rows 空数组、total 0；大数代理商会话请求返回 total 1 及子代理信息。
 *
 * 异常或失败场景：
 * - 若客户账号能读到持仓数据，或大数代理商会话读不到自身数据，测试失败。
 */

namespace Tests\Feature;

use App\Http\Middleware\JwtAuthMiddleware;
use App\Http\Middleware\SingleSignOn;
use App\Models\UserLogin;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class FrontBigNumberPositionApplicantBoundaryClosureModuleTest extends TestCase
{
    use DatabaseTransactions;

    /**
     * 验证客户账号无法伪装大数代理商查询持仓汇总。
     *
     * 请求 positionSummarySearch，断言 rows/total 为空且响应不含可见代理商。
     */
    public function test_customer_account_cannot_spoof_big_agent_position_summary_search(): void
    {
        $customerId = 412070100;
        $visibleAgentId = 412070101;
        $bigAgentId = 4120701;

        $this->deleteFixtureRows([$customerId, $visibleAgentId], $bigAgentId);
        $this->insertUserInfo($customerId, 'big-position-boundary-customer', 2, 0);
        $this->insertUserInfo($visibleAgentId, 'big-position-boundary-agent', 1, 0);
        $this->insertBigAgent($bigAgentId, $visibleAgentId);

        $login = UserLogin::where('user_id', $customerId)->firstOrFail();
        $response = $this->withoutMiddleware([JwtAuthMiddleware::class, SingleSignOn::class])
            ->actingAs($login, 'user')
            ->postJson('/user/agents/position/positionSummarySearch', [
                'big_agent_id' => $bigAgentId,
            ]);

        $response->assertOk()
            ->assertJsonPath('rows', [])
            ->assertJsonPath('total', 0);
        $this->assertStringNotContainsString((string) $visibleAgentId, $response->getContent());
        $this->assertStringNotContainsString('big-position-boundary-agent', $response->getContent());
    }

    /**
     * 验证客户账号无法伪装大数代理商查询子代理持仓汇总。
     *
     * 请求 subAgentsListSearch，断言 rows/total 为空且响应不含可见代理商。
     */
    public function test_customer_account_cannot_spoof_big_agent_sub_position_summary_search(): void
    {
        $customerId = 412070200;
        $visibleAgentId = 412070201;
        $bigAgentId = 4120702;

        $this->deleteFixtureRows([$customerId, $visibleAgentId], $bigAgentId);
        $this->insertUserInfo($customerId, 'big-sub-position-boundary-customer', 2, 0);
        $this->insertUserInfo($visibleAgentId, 'big-sub-position-boundary-agent', 1, 0);
        $this->insertBigAgent($bigAgentId, $visibleAgentId);

        $login = UserLogin::where('user_id', $customerId)->firstOrFail();
        $response = $this->withoutMiddleware([JwtAuthMiddleware::class, SingleSignOn::class])
            ->actingAs($login, 'user')
            ->postJson('/user/agents/position/subAgentsListSearch', [
                'bigAgentId' => $bigAgentId,
            ]);

        $response->assertOk()
            ->assertJsonPath('rows', [])
            ->assertJsonPath('total', 0);
        $this->assertStringNotContainsString((string) $visibleAgentId, $response->getContent());
        $this->assertStringNotContainsString('big-sub-position-boundary-agent', $response->getContent());
    }

    /**
     * 验证已登录大数代理商会话可以读取自身持仓汇总。
     *
     * 携带 bigAgents 会话请求 positionSummarySearch，断言返回其子代理信息。
     */
    public function test_logged_in_big_agent_session_can_read_position_summary_search(): void
    {
        $visibleAgentId = 412070301;
        $bigAgentId = 4120703;

        $this->deleteFixtureRows([$visibleAgentId], $bigAgentId);
        $this->insertUserInfo($visibleAgentId, 'big-position-session-agent', 1, 0);
        $this->insertBigAgent($bigAgentId, $visibleAgentId);

        $response = $this->withoutMiddleware([JwtAuthMiddleware::class, SingleSignOn::class])
            ->withSession(['bigAgents' => ['id' => $bigAgentId]])
            ->postJson('/user/agents/position/positionSummarySearch');

        $response->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonPath('rows.0.sub_ag_id', $visibleAgentId)
            ->assertJsonPath('rows.0.sub_ag_name', 'big-position-session-agent');
    }

    /**
     * 验证最终权限检查清单记录了本次边界封闭模块。
     *
     * 断言清单包含第 207 项、currentBigAgent 及相关接口路径和本测试类名。
     */
    public function test_final_checklist_records_big_number_position_applicant_boundary(): void
    {
        $checklist = file_get_contents(base_path('docs/admin-backend-blade-permission-final-checklist.md')) ?: '';

        $this->assertStringContainsString('## 207.', $checklist);
        $this->assertStringContainsString('currentBigAgent', $checklist);
        $this->assertStringContainsString('big_agent_id', $checklist);
        $this->assertStringContainsString('user/agents/position/positionSummarySearch', $checklist);
        $this->assertStringContainsString('user/agents/position/subAgentsListSearch', $checklist);
        $this->assertStringContainsString('FrontBigNumberPositionApplicantBoundaryClosureModuleTest', $checklist);
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
            'email' => 'front-big-position-boundary-' . $userId . '@example.test',
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
            'phone' => '1782070' . substr((string) $userId, -4),
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
            'email' => 'front-big-position-boundary-' . $bigAgentId . '@example.test',
            'username' => 'front-big-position-boundary-' . $bigAgentId,
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
     * 清理指定用户的佣金、交易、大数代理商、用户信息及层级关系测试数据。
     *
     * @param array<int, int> $userIds 待清理的用户 ID 列表。
     * @param int $bigAgentId 待清理的大数代理商 ID。
     * @return void 无返回值。
     */
    private function deleteFixtureRows(array $userIds, int $bigAgentId): void
    {
        DB::table('commission_records')->whereIn('agent_id', $userIds)->orWhereIn('parent_id', $userIds)->delete();
        DB::table('user_trades')->whereIn('user_id', $userIds)->delete();
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
