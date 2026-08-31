<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/02
 * Time: 01:50
 */

/**
 * 前端大数代理商持仓汇总-归属者边界封闭模块测试。
 *
 * 文件功能：
 * - 验证大数代理商持仓汇总接口的 user_id 旧别名只作用于配置范围内可见的子代理商。
 * - 验证通过 userId / user_id 伪装参数查询配置范围外的代理商时返回空结果。
 * - 验证子代理持仓汇总接口同样拒绝配置范围外的伪装查询。
 * - 验证最终权限检查清单文档记录了该边界封闭模块。
 *
 * 适用场景：
 * - 大数代理商（big_agents.sub_agent_ids 配置）持仓汇总接口的归属权边界回归测试。
 *
 * 入参例子：
 * - POST /user/agents/position/positionSummarySearch
 *   请求体：{ "user_id": 412430701, "limit": 20 }
 * - POST /user/agents/position/subAgentsListSearch
 *   请求体：{ "userId": 412430202, "user_id": 412430202, "limit": 20 }
 * - 会话：withSession(['bigAgents' => ['id' => 4124307]])
 *
 * 返回值：
 * - 范围内查询返回 total 1 及对应子代理；范围外伪装查询返回 rows 空数组、total 0。
 *
 * 异常或失败场景：
 * - 若范围外的代理商数据被返回，或范围内查询结果不符，测试失败。
 */

namespace Tests\Feature;

use App\Http\Middleware\JwtAuthMiddleware;
use App\Http\Middleware\SingleSignOn;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class FrontBigNumberPositionOwnerBoundaryClosureModuleTest extends TestCase
{
    use DatabaseTransactions;

    /**
     * 验证持仓汇总的 user_id 旧别名只返回配置范围内的代理商。
     *
     * 大数代理商配置两个子代理，以 user_id 查询其中一个，
     * 断言只返回该子代理且不含另一个。
     */
    public function test_big_agent_position_summary_uses_legacy_user_id_alias_without_returning_other_agents(): void
    {
        $bigAgentId = 4124307;
        $visibleAgentId = 412430701;
        $otherAgentId = 412430702;

        $this->deleteFixtureRows([$visibleAgentId, $otherAgentId], $bigAgentId);
        $this->insertUserInfo($visibleAgentId, 'big-position-legacy-user-id-visible', 1, 0);
        $this->insertUserInfo($otherAgentId, 'big-position-legacy-user-id-other', 1, 0);
        $this->insertBigAgent($bigAgentId, $visibleAgentId);
        DB::table('big_agents')->where('id', $bigAgentId)->update([
            'sub_agent_ids' => $visibleAgentId . ',' . $otherAgentId,
        ]);

        $response = $this->withoutMiddleware([JwtAuthMiddleware::class, SingleSignOn::class])
            ->withSession(['bigAgents' => ['id' => $bigAgentId]])
            ->postJson('/user/agents/position/positionSummarySearch', [
                'user_id' => $visibleAgentId,
                'limit' => 20,
            ]);

        $response->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonPath('rows.0.sub_ag_id', $visibleAgentId);
        $this->assertStringNotContainsString((string) $otherAgentId, $response->getContent());
    }

    /**
     * 验证持仓汇总拒绝配置范围外的伪装代理商查询。
     *
     * 未带参数时只返回配置内子代理；带 userId / user_id 伪装范围外代理商时返回空结果。
     */
    public function test_big_agent_position_summary_rejects_spoofed_agent_outside_configured_scope(): void
    {
        $bigAgentId = 4124301;
        $visibleAgentId = 412430101;
        $otherAgentId = 412430102;

        $this->deleteFixtureRows([$visibleAgentId, $otherAgentId], $bigAgentId);
        $this->insertUserInfo($visibleAgentId, 'big-position-owner-visible-agent', 1, 0);
        $this->insertUserInfo($otherAgentId, 'big-position-owner-other-agent', 1, 0);
        $this->insertBigAgent($bigAgentId, $visibleAgentId);

        $visibleResponse = $this->withoutMiddleware([JwtAuthMiddleware::class, SingleSignOn::class])
            ->withSession(['bigAgents' => ['id' => $bigAgentId]])
            ->postJson('/user/agents/position/positionSummarySearch', ['limit' => 20]);

        $visibleResponse->assertOk()
            ->assertJsonPath('total', 1);
        $this->assertStringContainsString((string) $visibleAgentId, $visibleResponse->getContent());
        $this->assertStringNotContainsString((string) $otherAgentId, $visibleResponse->getContent());
        $this->assertStringNotContainsString('big-position-owner-other-agent', $visibleResponse->getContent());

        $spoofedResponse = $this->withoutMiddleware([JwtAuthMiddleware::class, SingleSignOn::class])
            ->withSession(['bigAgents' => ['id' => $bigAgentId]])
            ->postJson('/user/agents/position/positionSummarySearch', [
                'userId' => $otherAgentId,
                'user_id' => $otherAgentId,
                'limit' => 20,
            ]);

        $spoofedResponse->assertOk()
            ->assertJsonPath('rows', [])
            ->assertJsonPath('total', 0);
        $this->assertStringNotContainsString((string) $otherAgentId, $spoofedResponse->getContent());
        $this->assertStringNotContainsString('big-position-owner-other-agent', $spoofedResponse->getContent());
    }

    /**
     * 验证子代理持仓汇总拒绝配置范围外的伪装代理商查询。
     *
     * 未带参数时只返回配置内子代理；带 userId / user_id 伪装范围外代理商时返回空结果。
     */
    public function test_big_agent_sub_position_summary_rejects_spoofed_agent_outside_configured_scope(): void
    {
        $bigAgentId = 4124302;
        $visibleAgentId = 412430201;
        $otherAgentId = 412430202;

        $this->deleteFixtureRows([$visibleAgentId, $otherAgentId], $bigAgentId);
        $this->insertUserInfo($visibleAgentId, 'big-sub-position-owner-visible-agent', 1, 0);
        $this->insertUserInfo($otherAgentId, 'big-sub-position-owner-other-agent', 1, 0);
        $this->insertBigAgent($bigAgentId, $visibleAgentId);

        $visibleResponse = $this->withoutMiddleware([JwtAuthMiddleware::class, SingleSignOn::class])
            ->withSession(['bigAgents' => ['id' => $bigAgentId]])
            ->postJson('/user/agents/position/subAgentsListSearch', ['limit' => 20]);

        $visibleResponse->assertOk()
            ->assertJsonPath('total', 1);
        $this->assertStringContainsString((string) $visibleAgentId, $visibleResponse->getContent());
        $this->assertStringNotContainsString((string) $otherAgentId, $visibleResponse->getContent());
        $this->assertStringNotContainsString('big-sub-position-owner-other-agent', $visibleResponse->getContent());

        $spoofedResponse = $this->withoutMiddleware([JwtAuthMiddleware::class, SingleSignOn::class])
            ->withSession(['bigAgents' => ['id' => $bigAgentId]])
            ->postJson('/user/agents/position/subAgentsListSearch', [
                'userId' => $otherAgentId,
                'user_id' => $otherAgentId,
                'limit' => 20,
            ]);

        $spoofedResponse->assertOk()
            ->assertJsonPath('rows', [])
            ->assertJsonPath('total', 0);
        $this->assertStringNotContainsString((string) $otherAgentId, $spoofedResponse->getContent());
        $this->assertStringNotContainsString('big-sub-position-owner-other-agent', $spoofedResponse->getContent());
    }

    /**
     * 验证最终权限检查清单记录了本次归属者边界封闭模块。
     *
     * 断言清单包含第 243 项、BigNumberController 相关方法及本测试类名。
     */
    public function test_final_checklist_records_big_number_position_owner_boundary(): void
    {
        $checklist = file_get_contents(base_path('docs/admin-backend-blade-permission-final-checklist.md')) ?: '';

        $this->assertStringContainsString('## 243.', $checklist);
        $this->assertStringContainsString('BigNumberController::bigPositionSummarySearch', $checklist);
        $this->assertStringContainsString('BigNumberController::bigSubPositionSummaryStats', $checklist);
        $this->assertStringContainsString('user/agents/position/positionSummarySearch', $checklist);
        $this->assertStringContainsString('user/agents/position/subAgentsListSearch', $checklist);
        $this->assertStringContainsString('FrontBigNumberPositionOwnerBoundaryClosureModuleTest', $checklist);
    }

    /**
     * 插入带父子关系的测试用户数据，代理商默认级别 1、佣金比例 0.1。
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
            'email' => 'front-big-position-owner-' . $userId . '@example.test',
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
            'phone' => '1782430' . substr((string) $userId, -4),
            'gender' => 1,
            'account_type' => $accountType,
            'parent_id' => $parentId,
            'family_tree' => $parentId > 0 ? $parentId . ',' . $userId : '',
            'group_id' => 0,
            'level_id' => $accountType === 1 ? 1 : 0,
            'comm_rate' => $accountType === 1 ? 0.1 : 0,
            'auth_status' => 1,
            'total_funds' => 0,
            'used_margin' => 0,
            'avail_margin' => 0,
            'equity' => 0,
            'effective_credit' => 0,
            'risk_ratio' => 0,
            'leverage' => 100,
            'is_ecn' => 0,
            'is_withdrawal_allowed' => 0,
            'is_deposit_allowed' => 0,
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
            'email' => 'front-big-position-owner-' . $bigAgentId . '@example.test',
            'username' => 'front-big-position-owner-' . $bigAgentId,
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
     * 清理指定用户的佣金、交易、大数代理商、层级关系及用户信息测试数据。
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
        DB::table('agent_descendants')
            ->where(function ($query) use ($userIds) {
                $query->whereIn('agent_id', $userIds)
                    ->orWhereIn('descendant_id', $userIds);
            })
            ->delete();
        DB::table('user_infos')->whereIn('user_id', $userIds)->delete();
        DB::table('user_logins')->whereIn('user_id', $userIds)->delete();
    }
}
