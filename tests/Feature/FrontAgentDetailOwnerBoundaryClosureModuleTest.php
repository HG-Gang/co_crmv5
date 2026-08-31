<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/02
 * Time: 01:51
 */

/**
 * 前台代理详情归属边界（Owner Boundary）闭环测试。
 *
 * 文件功能：
 * - 验证现代与旧版用户详情接口拒绝查看其他分支的目标用户且不泄漏内容。
 * - 验证旧版父路径（parentPath）接口对其他分支目标返回空路径。
 * - 验证旧版直属客户列表按 puid 过滤时只返回本分支客户。
 * - 验证最终清单文档已记录代理详情归属边界。
 *
 * 适用场景：
 * - 前台代理查看客户/下级详情时的越权防护回归测试。
 *
 * 入参例子：
 * - GET /api/front/users/{otherCustomerId}
 * - POST /user/proxy/parentPath  user_id: {otherCustomerId}
 * - POST /user/proxy/direct_cust_detail_list  puid: {otherSubAgentId}
 *
 * 返回值：
 * - 本分支目标返回 SUCCESS 与数据；其他分支目标返回 PERMISSION_DENIED/403 或空结果。
 *
 * 异常或失败场景：
 * - 若其他分支数据被泄漏或越权请求被放行，断言失败。
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

class FrontAgentDetailOwnerBoundaryClosureModuleTest extends TestCase
{
    use DatabaseTransactions;

    /**
     * 验证现代与旧版用户详情接口拒绝其他分支目标且不泄漏内容。
     */
    public function test_modern_and_legacy_user_detail_reject_other_branch_target_without_leaking_content(): void
    {
        $viewerAgentId = 412470100;
        $ownCustomerId = 412470101;
        $otherAgentId = 412470102;
        $otherCustomerId = 412470103;

        $this->deleteFixtureRows([$viewerAgentId, $ownCustomerId, $otherAgentId, $otherCustomerId]);
        $this->insertUserInfo($viewerAgentId, 'agent-detail-owner-viewer-agent', 1, 0);
        $this->insertUserInfo($ownCustomerId, 'agent-detail-owner-own-customer', 2, $viewerAgentId);
        $this->insertUserInfo($otherAgentId, 'agent-detail-owner-other-agent', 1, 0);
        $this->insertUserInfo($otherCustomerId, 'agent-detail-owner-other-customer', 2, $otherAgentId);

        $login = UserLogin::where('user_id', $viewerAgentId)->firstOrFail();
        $visibleModern = $this->withoutMiddleware([JwtAuthMiddleware::class, SingleSignOn::class])
            ->actingAs($login, 'user')
            ->getJson('/api/front/users/' . $ownCustomerId);

        $visibleModern->assertOk()
            ->assertJsonPath('code', ResponseCode::SUCCESS);
        $this->assertStringContainsString('agent-detail-owner-own-customer', $visibleModern->getContent());
        $this->assertStringNotContainsString('agent-detail-owner-other-customer', $visibleModern->getContent());

        $outsideModern = $this->withoutMiddleware([JwtAuthMiddleware::class, SingleSignOn::class])
            ->actingAs($login, 'user')
            ->getJson('/api/front/users/' . $otherCustomerId);

        $outsideModern->assertOk()
            ->assertJsonPath('code', ResponseCode::PERMISSION_DENIED);
        $this->assertStringNotContainsString('agent-detail-owner-other-customer', $outsideModern->getContent());
        $this->assertStringNotContainsString((string) $otherCustomerId, $outsideModern->getContent());

        $visibleLegacy = $this->withoutMiddleware([JwtAuthMiddleware::class, SingleSignOn::class])
            ->actingAs($login, 'user')
            ->get('/show/user_detail/' . $ownCustomerId . '/2');

        $visibleLegacy->assertOk()
            ->assertSee('agent-detail-owner-own-customer', false)
            ->assertDontSee('agent-detail-owner-other-customer', false);

        $outsideLegacy = $this->withoutMiddleware([JwtAuthMiddleware::class, SingleSignOn::class])
            ->actingAs($login, 'user')
            ->get('/show/user_detail/' . $otherCustomerId . '/2');

        $outsideLegacy->assertStatus(403);
        $this->assertStringNotContainsString('agent-detail-owner-other-customer', $outsideLegacy->getContent());
        $this->assertStringNotContainsString((string) $otherCustomerId, $outsideLegacy->getContent());
    }

    /**
     * 验证旧版父路径接口对其他分支目标返回空路径。
     */
    public function test_legacy_parent_path_rejects_other_branch_target_path(): void
    {
        $viewerAgentId = 412470200;
        $ownSubAgentId = 412470201;
        $ownCustomerId = 412470202;
        $otherRootAgentId = 412470203;
        $otherSubAgentId = 412470204;
        $otherCustomerId = 412470205;

        $this->deleteFixtureRows([$viewerAgentId, $ownSubAgentId, $ownCustomerId, $otherRootAgentId, $otherSubAgentId, $otherCustomerId]);
        $this->insertUserInfo($viewerAgentId, 'agent-parent-path-viewer', 1, 0);
        $this->insertUserInfo($ownSubAgentId, 'agent-parent-path-own-sub', 1, $viewerAgentId);
        $this->insertUserInfo($ownCustomerId, 'agent-parent-path-own-customer', 2, $ownSubAgentId);
        $this->insertUserInfo($otherRootAgentId, 'agent-parent-path-other-root', 1, 0);
        $this->insertUserInfo($otherSubAgentId, 'agent-parent-path-other-sub', 1, $otherRootAgentId);
        $this->insertUserInfo($otherCustomerId, 'agent-parent-path-other-customer', 2, $otherSubAgentId);

        $login = UserLogin::where('user_id', $viewerAgentId)->firstOrFail();
        $visibleResponse = $this->withoutMiddleware([JwtAuthMiddleware::class, SingleSignOn::class])
            ->actingAs($login, 'user')
            ->postJson('/user/proxy/parentPath', [
                'user_id' => $ownCustomerId,
                'event_name' => 'returnPreLevel',
            ]);

        $visibleResponse->assertOk()
            ->assertJsonPath('code', 200);
        $this->assertStringContainsString('agent-parent-path-viewer[' . $viewerAgentId . ']', (string) $visibleResponse->json('data.path'));
        $this->assertStringContainsString('agent-parent-path-own-sub[' . $ownSubAgentId . ']', (string) $visibleResponse->json('data.path'));
        $this->assertStringContainsString('agent-parent-path-own-customer[' . $ownCustomerId . ']', (string) $visibleResponse->json('data.path'));
        $this->assertStringNotContainsString('agent-parent-path-other-customer', $visibleResponse->getContent());

        $outsideResponse = $this->withoutMiddleware([JwtAuthMiddleware::class, SingleSignOn::class])
            ->actingAs($login, 'user')
            ->postJson('/user/proxy/parentPath', [
                'user_id' => $otherCustomerId,
                'event_name' => 'returnPreLevel',
            ]);

        $outsideResponse->assertOk()
            ->assertJsonPath('code', 200)
            ->assertJsonPath('data.path', '')
            ->assertJsonPath('data.tree', []);
        $this->assertStringNotContainsString('agent-parent-path-other-customer', $outsideResponse->getContent());
        $this->assertStringNotContainsString((string) $otherCustomerId, $outsideResponse->getContent());
    }

    /**
     * 验证旧版直属客户列表拒绝其他分支的父级过滤。
     */
    public function test_legacy_direct_customer_detail_rejects_other_branch_parent_filter(): void
    {
        $viewerAgentId = 412470300;
        $ownSubAgentId = 412470301;
        $ownCustomerId = 412470302;
        $otherRootAgentId = 412470303;
        $otherSubAgentId = 412470304;
        $otherCustomerId = 412470305;

        $this->deleteFixtureRows([$viewerAgentId, $ownSubAgentId, $ownCustomerId, $otherRootAgentId, $otherSubAgentId, $otherCustomerId]);
        $this->insertUserInfo($viewerAgentId, 'agent-direct-detail-viewer', 1, 0);
        $this->insertUserInfo($ownSubAgentId, 'agent-direct-detail-own-sub', 1, $viewerAgentId);
        $this->insertUserInfo($ownCustomerId, 'agent-direct-detail-own-customer', 2, $ownSubAgentId);
        $this->insertUserInfo($otherRootAgentId, 'agent-direct-detail-other-root', 1, 0);
        $this->insertUserInfo($otherSubAgentId, 'agent-direct-detail-other-sub', 1, $otherRootAgentId);
        $this->insertUserInfo($otherCustomerId, 'agent-direct-detail-other-customer', 2, $otherSubAgentId);

        $login = UserLogin::where('user_id', $viewerAgentId)->firstOrFail();
        $visibleResponse = $this->withoutMiddleware([JwtAuthMiddleware::class, SingleSignOn::class])
            ->actingAs($login, 'user')
            ->postJson('/user/proxy/direct_cust_detail_list', [
                'puid' => $ownSubAgentId,
                'per_page' => 20,
            ]);

        $visibleResponse->assertOk()
            ->assertJsonPath('code', ResponseCode::SUCCESS)
            ->assertJsonPath('count', 1);
        $this->assertStringContainsString('agent-direct-detail-own-customer', $visibleResponse->getContent());
        $this->assertStringNotContainsString('agent-direct-detail-other-customer', $visibleResponse->getContent());

        $outsideResponse = $this->withoutMiddleware([JwtAuthMiddleware::class, SingleSignOn::class])
            ->actingAs($login, 'user')
            ->postJson('/user/proxy/direct_cust_detail_list', [
                'puid' => $otherSubAgentId,
                'per_page' => 20,
            ]);

        $outsideResponse->assertOk()
            ->assertJsonPath('code', ResponseCode::SUCCESS)
            ->assertJsonPath('count', 0)
            ->assertJsonPath('data', []);
        $this->assertStringNotContainsString('agent-direct-detail-other-customer', $outsideResponse->getContent());
        $this->assertStringNotContainsString((string) $otherCustomerId, $outsideResponse->getContent());
    }

    /**
     * 验证最终清单文档已记录代理详情归属边界（## 247）。
     */
    public function test_final_checklist_records_agent_detail_owner_boundary(): void
    {
        $checklist = file_get_contents(base_path('docs/admin-backend-blade-permission-final-checklist.md')) ?: '';

        $this->assertStringContainsString('## 247.', $checklist);
        $this->assertStringContainsString('AgentController::userDetail', $checklist);
        $this->assertStringContainsString('AgentController::legacyUserDetailPage', $checklist);
        $this->assertStringContainsString('AgentController::getParentPath', $checklist);
        $this->assertStringContainsString('AgentController::directCustDetailList', $checklist);
        $this->assertStringContainsString('/api/front/users/{user}', $checklist);
        $this->assertStringContainsString('user/proxy/parentPath', $checklist);
        $this->assertStringContainsString('user/proxy/direct_cust_detail_list', $checklist);
        $this->assertStringContainsString('FrontAgentDetailOwnerBoundaryClosureModuleTest', $checklist);
    }

    private function insertUserInfo(int $userId, string $userName, int $accountType, int $parentId): void
    {
        $now = time();

        DB::table('user_infos')->where('user_id', $userId)->delete();
        DB::table('user_logins')->where('user_id', $userId)->delete();

        $loginId = DB::table('user_logins')->insertGetId([
            'user_id' => $userId,
            'email' => 'front-agent-detail-owner-' . $userId . '@example.test',
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
            'phone' => '1782470' . substr((string) $userId, -4),
            'gender' => 1,
            'account_type' => $accountType,
            'parent_id' => $parentId,
            'family_tree' => $parentId > 0 ? $this->familyTreeFor($userId, $parentId) : '',
            'group_id' => 0,
            'level_id' => $accountType === 1 ? 1 : 0,
            'comm_rate' => $accountType === 1 ? 0.2 : 0,
            'auth_status' => 1,
            'is_agent_confirmed' => $accountType === 1 ? 1 : 0,
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

    private function familyTreeFor(int $userId, int $parentId): string
    {
        $parentTree = (string) DB::table('user_infos')->where('user_id', $parentId)->value('family_tree');
        $ids = array_values(array_filter(array_map('intval', explode(',', $parentTree))));
        $ids[] = $parentId;
        $ids[] = $userId;

        return implode(',', array_values(array_unique($ids)));
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
        DB::table('deposit_records')->whereIn('user_id', $userIds)->delete();
        DB::table('withdraw_records')->whereIn('user_id', $userIds)->delete();
        DB::table('commission_records')
            ->where(function ($query) use ($userIds) {
                $query->whereIn('agent_id', $userIds)
                    ->orWhereIn('parent_id', $userIds);
            })
            ->delete();
        DB::table('user_trades')->whereIn('user_id', $userIds)->delete();
        DB::table('user_infos')->whereIn('user_id', $userIds)->delete();
        DB::table('user_logins')->whereIn('user_id', $userIds)->delete();
    }
}
