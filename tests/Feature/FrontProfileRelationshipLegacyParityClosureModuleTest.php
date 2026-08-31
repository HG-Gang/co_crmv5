<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/08
 * Time: 01:59
 */

/**
 * FrontProfileRelationshipLegacyParityClosureModuleTest
 *
 * 文件功能：
 * - 验证前台个人资料关系链旧口径等价：文本返回数据库姓名与用户 ID、HTML 返回可点击路径且不带内联颜色、v2 返回基于数据库节点的位置路径。
 * - 输入：HTTP 请求与事务回滚的数据库夹具；输出：PHPUnit 断言结果。
 * - 明确不负责：不覆盖真实 MT4 网关与线上支付/出金通道（由网关契约测试锁定）。
 */

namespace Tests\Feature;

use App\Http\Middleware\JwtAuthMiddleware;
use App\Http\Middleware\SingleSignOn;
use App\Models\UserLogin;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class FrontProfileRelationshipLegacyParityClosureModuleTest extends TestCase
{
    use DatabaseTransactions;

    public function test_legacy_relationship_text_returns_database_names_with_user_ids(): void
    {
        [$rootAgentId, $subAgentId, $customerId] = $this->seedRelationshipChain(412920100);

        $login = UserLogin::where('user_id', $rootAgentId)->firstOrFail();
        $response = $this->withoutMiddleware([JwtAuthMiddleware::class, SingleSignOn::class])
            ->actingAs($login, 'user')
            ->postJson('/user/relationShip', [
                'userId' => $customerId,
                'role' => 'agents',
            ]);

        $response->assertOk()
            ->assertJsonPath(
                'real',
                'Root Agent[' . $rootAgentId . '] -> Sub Agent[' . $subAgentId . '] -> Target Customer[' . $customerId . ']'
            );
    }

    public function test_legacy_relationship_html_returns_clickable_database_path_without_inline_colors(): void
    {
        [$rootAgentId, $subAgentId, $customerId] = $this->seedRelationshipChain(412920200);

        $login = UserLogin::where('user_id', $rootAgentId)->firstOrFail();
        $response = $this->withoutMiddleware([JwtAuthMiddleware::class, SingleSignOn::class])
            ->actingAs($login, 'user')
            ->postJson('/user/relationShipHtml', [
                'userId' => $customerId,
                'fname' => 'openRelationshipNode',
                'role' => 'agents',
            ]);

        $response->assertOk();
        $html = (string) $response->json('real');

        $this->assertStringContainsString('Root Agent[' . $rootAgentId . ']', $html);
        $this->assertStringContainsString('Sub Agent[' . $subAgentId . ']', $html);
        $this->assertStringContainsString('Target Customer[' . $customerId . ']', $html);
        $this->assertStringContainsString('onclick="openRelationshipNode(' . $customerId . ')"', $html);
        $this->assertStringContainsString('data-user_id="' . $subAgentId . '"', $html);
        $this->assertStringNotContainsString('style=', strtolower($html));
    }

    public function test_legacy_agents_relationship_html_v2_returns_position_path_from_database_nodes(): void
    {
        [$rootAgentId, $subAgentId, $customerId] = $this->seedRelationshipChain(412920300);

        $login = UserLogin::where('user_id', $rootAgentId)->firstOrFail();
        $response = $this->withoutMiddleware([JwtAuthMiddleware::class, SingleSignOn::class])
            ->actingAs($login, 'user')
            ->postJson('/user/agents/relationShipHtml', [
                'userId' => $customerId,
                'fname' => 'openAgentNode',
                'role' => 'agents',
            ]);

        $response->assertOk();
        $html = (string) $response->json('real');

        $this->assertStringStartsWith('我的位置: ', $html);
        $this->assertStringContainsString('Root Agent[' . $rootAgentId . ']', $html);
        $this->assertStringContainsString('Sub Agent[' . $subAgentId . ']', $html);
        $this->assertStringContainsString('Target Customer[' . $customerId . ']', $html);
        $this->assertStringContainsString('onclick="openAgentNode(' . $subAgentId . ')"', $html);
        $this->assertStringContainsString('class="crm-relationship-node crm-relationship-node-level-2"', $html);
        $this->assertStringNotContainsString('style=', strtolower($html));
    }

    /**
     * @return array{0:int,1:int,2:int}
     */
    private function seedRelationshipChain(int $baseUserId): array
    {
        $rootAgentId = $baseUserId;
        $subAgentId = $baseUserId + 1;
        $customerId = $baseUserId + 2;

        $this->deleteFixtureRows([$rootAgentId, $subAgentId, $customerId]);
        $this->insertUserInfo($rootAgentId, 'Root Agent', 1, 0, 1, (string) $rootAgentId);
        $this->insertUserInfo($subAgentId, 'Sub Agent', 1, $rootAgentId, 2, $rootAgentId . ',' . $subAgentId);
        $this->insertUserInfo($customerId, 'Target Customer', 2, $subAgentId, 0, $rootAgentId . ',' . $subAgentId . ',' . $customerId);

        $now = time();

        DB::table('agent_descendants')->insert([
            [
                'agent_id' => $rootAgentId,
                'descendant_id' => $subAgentId,
                'descendant_type' => 1,
                'depth' => 1,
                'is_direct' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'agent_id' => $rootAgentId,
                'descendant_id' => $customerId,
                'descendant_type' => 2,
                'depth' => 2,
                'is_direct' => 0,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'agent_id' => $subAgentId,
                'descendant_id' => $customerId,
                'descendant_type' => 2,
                'depth' => 1,
                'is_direct' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);

        return [$rootAgentId, $subAgentId, $customerId];
    }

    private function insertUserInfo(
        int $userId,
        string $userName,
        int $accountType,
        int $parentId,
        int $groupId,
        string $familyTree
    ): void {
        $now = time();

        $loginId = DB::table('user_logins')->insertGetId([
            'user_id' => $userId,
            'email' => 'front-relationship-parity-' . $userId . '@example.test',
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
            'phone' => '1782920' . substr((string) $userId, -4),
            'gender' => 1,
            'account_type' => $accountType,
            'parent_id' => $parentId,
            'family_tree' => $familyTree,
            'group_id' => $groupId,
            'level_id' => $accountType === 1 ? $groupId : 0,
            'comm_rate' => $accountType === 1 ? 0.1 : 0,
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
     * @param array<int, int> $userIds
     */
    private function deleteFixtureRows(array $userIds): void
    {
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
