<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/02
 * Time: 01:49
 */

/**
 * 前端代理商主列表-归属者边界封闭模块测试。
 *
 * 文件功能：
 * - 验证旧接口 /user/proxy/proxyListSearch 的 userPId / user_pid 参数作为父级范围过滤，仅返回该父级直系数据。
 * - 验证现代接口 /api/front/agents/direct 与 /api/front/agents/direct-customers 不跟随其他分支的 parent_id / userId 过滤参数。
 * - 验证旧接口 proxyListSearch / directCustListSearch 不跟随其他分支的 userId 伪装参数。
 * - 验证最终权限检查清单文档记录了该边界封闭模块。
 *
 * 适用场景：
 * - 前端代理商主列表（代理商/客户）接口的归属权边界回归测试。
 *
 * 入参例子：
 * - POST /user/proxy/proxyListSearch
 *   请求体：{ "userPId": 411920701, "direct_only": 1, "limit": 20 }
 * - GET /api/front/agents/direct?parent_id={其他分支代理商ID}&direct_only=1&per_page=20
 * - GET /api/front/agents/direct-customers?userId={其他分支客户ID}&per_page=20
 * - POST /user/cust/directCustListSearch（body: { "userId": ..., "limit": 20 }）
 *
 * 返回值：
 * - 合法查询返回 SUCCESS 且仅含自身分支数据；伪装查询返回 SUCCESS 但列表为空。
 *
 * 异常或失败场景：
 * - 若自身分支外的数据被返回，或合法查询结果数量/内容不符，测试失败。
 */

namespace Tests\Feature;

use App\Constants\ResponseCode;
use App\Http\Middleware\JwtAuthMiddleware;
use App\Http\Middleware\SingleSignOn;
use App\Models\UserLogin;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class FrontAgentMainListOwnerBoundaryClosureModuleTest extends TestCase
{
    use DatabaseTransactions;

    /**
     * 验证旧代理商列表接口使用 userPId 作为父级范围。
     *
     * 构造三级代理商链后以 userPId 查询，断言只返回指定父级的直系子代理。
     */
    public function test_legacy_proxy_list_uses_user_p_id_as_parent_scope(): void
    {
        $rootAgentId = 411920700;
        $parentAgentId = $rootAgentId + 1;
        $childAgentId = $rootAgentId + 2;

        $this->deleteFixtureRows([$rootAgentId, $parentAgentId, $childAgentId]);
        $this->insertUserInfo($rootAgentId, 'legacy-alias-root-agent', 1, 0);
        $this->insertUserInfo($parentAgentId, 'legacy-alias-parent-agent', 1, $rootAgentId);
        $this->insertUserInfo($childAgentId, 'legacy-alias-child-agent', 1, $parentAgentId);

        $login = UserLogin::where('user_id', $rootAgentId)->firstOrFail();
        $response = $this->withoutMiddleware([JwtAuthMiddleware::class, SingleSignOn::class])
            ->actingAs($login, 'user')
            ->postJson('/user/proxy/proxyListSearch', [
                'userPId' => $parentAgentId,
                'direct_only' => 1,
                'limit' => 20,
            ]);

        $response->assertOk()
            ->assertJsonPath('code', ResponseCode::SUCCESS)
            ->assertJsonCount(1, 'data.list.data')
            ->assertJsonPath('data.list.data.0.user_id', (string) $childAgentId);
    }

    /**
     * 验证旧代理商列表接口的 user_pid 别名在子级搜索时按直系范围过滤。
     *
     * 构造四级代理商链，断言 subSearch 只返回指定父级的直系子代理，不含孙级。
     */
    public function test_legacy_proxy_list_user_pid_alias_is_direct_scope_for_sub_search(): void
    {
        $rootAgentId = 411921700;
        $parentAgentId = $rootAgentId + 1;
        $childAgentId = $rootAgentId + 2;
        $grandChildAgentId = $rootAgentId + 3;

        $this->deleteFixtureRows([$rootAgentId, $parentAgentId, $childAgentId, $grandChildAgentId]);
        $this->insertUserInfo($rootAgentId, 'legacy-user-pid-root-agent', 1, 0);
        $this->insertUserInfo($parentAgentId, 'legacy-user-pid-parent-agent', 1, $rootAgentId);
        $this->insertUserInfo($childAgentId, 'legacy-user-pid-child-agent', 1, $parentAgentId);
        $this->insertUserInfo($grandChildAgentId, 'legacy-user-pid-grand-child-agent', 1, $childAgentId);

        $login = UserLogin::where('user_id', $rootAgentId)->firstOrFail();
        $response = $this->withoutMiddleware([JwtAuthMiddleware::class, SingleSignOn::class])
            ->actingAs($login, 'user')
            ->postJson('/user/proxy/proxyListSearch', [
                'user_pid' => $parentAgentId,
                'searchtype' => 'subSearch',
                'limit' => 20,
            ]);

        $response->assertOk()
            ->assertJsonPath('code', ResponseCode::SUCCESS)
            ->assertJsonCount(1, 'data.list.data')
            ->assertJsonPath('data.list.data.0.user_id', (string) $childAgentId);
            $this->assertStringNotContainsString((string) $grandChildAgentId, $response->getContent());
    }

    /**
     * 验证代理商列表不跟随其他代理商树的 parent_id 或 userId 过滤参数。
     *
     * 对自身分支按 parent_id 查询正常返回；对他人分支按 parent_id / userId
     * （现代与旧接口）查询均返回空列表。
     */
    public function test_agent_lists_do_not_follow_other_agent_tree_parent_or_user_filters(): void
    {
        $agentId = 411920100;
        $ownedAgentId = $agentId + 1;
        $ownedGrandAgentId = $agentId + 2;
        $otherAgentId = $agentId + 100;
        $otherChildAgentId = $otherAgentId + 1;

        $this->deleteFixtureRows([$agentId, $ownedAgentId, $ownedGrandAgentId, $otherAgentId, $otherChildAgentId]);
        $this->insertUserInfo($agentId, 'main-list-owner-root-agent', 1, 0);
        $this->insertUserInfo($ownedAgentId, 'main-list-owner-direct-agent', 1, $agentId);
        $this->insertUserInfo($ownedGrandAgentId, 'main-list-owner-grand-agent', 1, $ownedAgentId);
        $this->insertUserInfo($otherAgentId, 'main-list-owner-other-root', 1, 0);
        $this->insertUserInfo($otherChildAgentId, 'main-list-owner-other-agent', 1, $otherAgentId);

        $login = UserLogin::where('user_id', $agentId)->firstOrFail();
        $acting = $this->withoutMiddleware([JwtAuthMiddleware::class, SingleSignOn::class])
            ->actingAs($login, 'user');

        $ownedParent = $acting->getJson('/api/front/agents/direct?parent_id=' . $ownedAgentId . '&direct_only=1&per_page=20');
        $ownedParent->assertOk()
            ->assertJsonPath('code', ResponseCode::SUCCESS)
            ->assertJsonCount(1, 'data.list.data')
            ->assertJsonPath('data.list.data.0.user_id', (string) $ownedGrandAgentId);

        $otherParent = $acting->getJson('/api/front/agents/direct?parent_id=' . $otherAgentId . '&direct_only=1&per_page=20');
        $otherParent->assertOk()
            ->assertJsonPath('code', ResponseCode::SUCCESS)
            ->assertJsonPath('data.count', 0);
        $this->assertSame([], $otherParent->json('data.data'));

        $modernSpoofedUser = $acting->getJson('/api/front/agents/direct?userId=' . $otherChildAgentId . '&per_page=20');
        $modernSpoofedUser->assertOk()
            ->assertJsonPath('code', ResponseCode::SUCCESS);
        $this->assertSame([], $modernSpoofedUser->json('data.list.data'));

        $legacySpoofedUser = $acting->postJson('/user/proxy/proxyListSearch', [
            'userId' => $otherChildAgentId,
            'limit' => 20,
        ]);
        $legacySpoofedUser->assertOk()
            ->assertJsonPath('code', ResponseCode::SUCCESS);
        $this->assertSame([], $legacySpoofedUser->json('data.list.data'));
    }

    /**
     * 验证客户列表不跟随其他代理商树的 parent_id 或 userId 过滤参数。
     *
     * 对自身分支按 parent_id 查询正常返回；对他人分支按 parent_id / userId
     * （现代与旧接口）查询均返回空列表。
     */
    public function test_customer_lists_do_not_follow_other_agent_tree_parent_or_user_filters(): void
    {
        $agentId = 411920300;
        $ownedAgentId = $agentId + 1;
        $ownedCustomerId = $agentId + 2;
        $otherAgentId = $agentId + 100;
        $otherCustomerId = $otherAgentId + 1;

        $this->deleteFixtureRows([$agentId, $ownedAgentId, $ownedCustomerId, $otherAgentId, $otherCustomerId]);
        $this->insertUserInfo($agentId, 'main-list-customer-owner-root', 1, 0);
        $this->insertUserInfo($ownedAgentId, 'main-list-customer-owner-agent', 1, $agentId);
        $this->insertUserInfo($ownedCustomerId, 'main-list-customer-owned-customer', 2, $ownedAgentId);
        $this->insertUserInfo($otherAgentId, 'main-list-customer-other-root', 1, 0);
        $this->insertUserInfo($otherCustomerId, 'main-list-customer-other-customer', 2, $otherAgentId);

        $login = UserLogin::where('user_id', $agentId)->firstOrFail();
        $acting = $this->withoutMiddleware([JwtAuthMiddleware::class, SingleSignOn::class])
            ->actingAs($login, 'user');

        $ownedParent = $acting->getJson('/api/front/agents/direct-customers?parent_id=' . $ownedAgentId . '&direct_only=1&per_page=20');
        $ownedParent->assertOk()
            ->assertJsonPath('code', ResponseCode::SUCCESS)
            ->assertJsonCount(1, 'data.list.data')
            ->assertJsonPath('data.list.data.0.user_id', (string) $ownedCustomerId);

        $otherParent = $acting->getJson('/api/front/agents/direct-customers?parent_id=' . $otherAgentId . '&direct_only=1&per_page=20');
        $otherParent->assertOk()
            ->assertJsonPath('code', ResponseCode::SUCCESS)
            ->assertJsonPath('data.count', 0);
        $this->assertSame([], $otherParent->json('data.data'));

        $modernSpoofedUser = $acting->getJson('/api/front/agents/direct-customers?userId=' . $otherCustomerId . '&per_page=20');
        $modernSpoofedUser->assertOk()
            ->assertJsonPath('code', ResponseCode::SUCCESS);
        $this->assertSame([], $modernSpoofedUser->json('data.list.data'));

        $legacySpoofedUser = $acting->postJson('/user/cust/directCustListSearch', [
            'userId' => $otherCustomerId,
            'limit' => 20,
        ]);
        $legacySpoofedUser->assertOk()
            ->assertJsonPath('code', ResponseCode::SUCCESS);
        $this->assertSame([], $legacySpoofedUser->json('data.list.data'));
    }

    /**
     * 验证最终权限检查清单记录了本次归属者边界封闭模块。
     *
     * 断言清单包含第 251 项、subList、customerList 及相关接口路径和本测试类名。
     */
    public function test_final_checklist_records_main_list_owner_boundary(): void
    {
        $checklist = file_get_contents(base_path('docs/admin-backend-blade-permission-final-checklist.md')) ?: '';

        $this->assertStringContainsString('## 251.', $checklist);
        $this->assertStringContainsString('subList', $checklist);
        $this->assertStringContainsString('customerList', $checklist);
        $this->assertStringContainsString('/api/front/agents/direct', $checklist);
        $this->assertStringContainsString('/api/front/agents/direct-customers', $checklist);
        $this->assertStringContainsString('user/proxy/proxyListSearch', $checklist);
        $this->assertStringContainsString('user/cust/directCustListSearch', $checklist);
        $this->assertStringContainsString('FrontAgentMainListOwnerBoundaryClosureModuleTest', $checklist);
    }

    /**
     * 插入带父子关系的测试用户数据，代理商默认级别 2、佣金比例 20。
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
            'email' => 'front-main-list-owner-' . $userId . '@example.test',
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
            'phone' => '1789200' . substr((string) $userId, -4),
            'gender' => 1,
            'account_type' => $accountType,
            'parent_id' => $parentId,
            'family_tree' => '',
            'group_id' => 0,
            'level_id' => $accountType === 1 ? 2 : 0,
            'comm_rate' => $accountType === 1 ? 20 : 0,
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
     * 清理指定用户相关的 agent_descendants 测试数据。
     *
     * @param array<int, int> $userIds 待清理的用户 ID 列表。
     * @return void 无返回值。
     */
    private function deleteFixtureRows(array $userIds): void
    {
        DB::table('agent_descendants')
            ->where(function ($query) use ($userIds) {
                $query->whereIn('agent_id', $userIds)
                    ->orWhereIn('descendant_id', $userIds);
            })
            ->delete();
    }
}
