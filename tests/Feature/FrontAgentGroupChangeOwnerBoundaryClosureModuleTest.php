<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/02
 * Time: 01:52
 */

/**
 * 前台组变更归属边界（Owner Boundary）闭环测试。
 *
 * 文件功能：
 * - 验证现代与旧版组变更申请只为当前代理的客户写入申请日志。
 * - 验证其他代理树下的客户被拒绝且无写入。
 * - 验证组变更列表按当前申请人过滤，伪造 userId 不返回其他分支记录。
 * - 验证最终清单文档已记录归属边界。
 *
 * 适用场景：
 * - 前台代理组变更申请与列表的越权防护回归测试。
 *
 * 入参例子：
 * - POST /api/front/agents/group-change-applications
 *   target_user_id: {customerId}, new_group_id: {targetGroupId}, reason: {reason}
 * - POST /user/cust/change/group_edit
 *   userId: {customerId}, grpName: {targetGroupName}, trans_apply_reason: {reason}
 * - GET /api/front/agents/group-changes?userId={otherCustomerId}（伪造）
 *
 * 返回值：
 * - 本分支客户写入成功（SUCCESS/msg=SUCCESS）；其他分支拒绝（PERMISSION_DENIED/msg=FAIL）。
 * - 伪造 userId 的列表返回空结果。
 *
 * 异常或失败场景：
 * - 若跨分支申请被写入或伪造 userId 返回他人记录，断言失败。
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

class FrontAgentGroupChangeOwnerBoundaryClosureModuleTest extends TestCase
{
    use DatabaseTransactions;

    /**
     * 验证现代组变更申请为本分支客户写入申请日志。
     */
    public function test_modern_group_change_application_writes_for_current_agent_customer(): void
    {
        $agentId = 411900100;
        $customerId = $agentId + 1;
        $originGroupId = $this->insertCustomerGroup('owner-modern-origin-' . $agentId);
        $targetGroupId = $this->insertCustomerGroup('owner-modern-target-' . $agentId);
        $reason = 'modern owner boundary should write';

        $this->deleteFixtureRows([$agentId, $customerId]);
        $this->insertUserInfo($agentId, 'owner-modern-root-agent', 1, 0, 0);
        $this->insertUserInfo($customerId, 'owner-modern-customer', 2, $agentId, $originGroupId);

        $login = UserLogin::where('user_id', $agentId)->firstOrFail();
        $response = $this->withoutMiddleware([JwtAuthMiddleware::class, SingleSignOn::class])
            ->actingAs($login, 'user')
            ->postJson('/api/front/agents/group-change-applications', [
                'target_user_id' => $customerId,
                'new_group_id' => $targetGroupId,
                'reason' => $reason,
            ]);

        $response->assertOk()
            ->assertJsonPath('code', ResponseCode::SUCCESS);

        $this->assertDatabaseHas('trans_apply_logs', [
            'user_id' => $customerId,
            'origin_group_id' => $originGroupId,
            'group_id' => $targetGroupId,
            'applicant_id' => $agentId,
            'apply_reason' => $reason,
        ]);
    }

    /**
     * 验证现代组变更申请拒绝其他代理树客户且无写入。
     */
    public function test_modern_group_change_application_rejects_other_agent_tree_customer_without_write(): void
    {
        $agentId = 411900200;
        $otherAgentId = $agentId + 100;
        $otherCustomerId = $otherAgentId + 1;
        $originGroupId = $this->insertCustomerGroup('owner-modern-denied-origin-' . $agentId);
        $targetGroupId = $this->insertCustomerGroup('owner-modern-denied-target-' . $agentId);

        $this->deleteFixtureRows([$agentId, $otherAgentId, $otherCustomerId]);
        $this->insertUserInfo($agentId, 'owner-modern-denied-root', 1, 0, 0);
        $this->insertUserInfo($otherAgentId, 'owner-modern-denied-other-agent', 1, 0, 0);
        $this->insertUserInfo($otherCustomerId, 'owner-modern-denied-other-customer', 2, $otherAgentId, $originGroupId);

        $login = UserLogin::where('user_id', $agentId)->firstOrFail();
        $response = $this->withoutMiddleware([JwtAuthMiddleware::class, SingleSignOn::class])
            ->actingAs($login, 'user')
            ->postJson('/api/front/agents/group-change-applications', [
                'target_user_id' => $otherCustomerId,
                'new_group_id' => $targetGroupId,
                'reason' => 'modern owner boundary should reject',
            ]);

        $response->assertOk()
            ->assertJsonPath('code', ResponseCode::PERMISSION_DENIED);

        $this->assertNoGroupChangeApplication($agentId, $otherCustomerId);
    }

    /**
     * 验证旧版组编辑为本分支客户写入申请日志。
     */
    public function test_legacy_group_edit_writes_for_current_agent_customer(): void
    {
        $agentId = 411900300;
        $customerId = $agentId + 1;
        $originGroupId = $this->insertCustomerGroup('owner-legacy-origin-' . $agentId);
        $targetGroupName = 'owner-legacy-target-' . $agentId;
        $reason = 'legacy owner boundary should write';

        $this->insertCustomerGroup($targetGroupName);
        $this->deleteFixtureRows([$agentId, $customerId]);
        $this->insertUserInfo($agentId, 'owner-legacy-root-agent', 1, 0, 0);
        $this->insertUserInfo($customerId, 'owner-legacy-customer', 2, $agentId, $originGroupId);

        $login = UserLogin::where('user_id', $agentId)->firstOrFail();
        $response = $this->withoutMiddleware([JwtAuthMiddleware::class, SingleSignOn::class])
            ->actingAs($login, 'user')
            ->postJson('/user/cust/change/group_edit', [
                'userId' => $customerId,
                'grpName' => $targetGroupName,
                'trans_apply_reason' => $reason,
            ]);

        $response->assertOk()
            ->assertJsonPath('msg', 'SUCCESS');

        $this->assertDatabaseHas('trans_apply_logs', [
            'user_id' => $customerId,
            'origin_group_id' => $originGroupId,
            'group_name' => $targetGroupName,
            'applicant_id' => $agentId,
            'apply_reason' => $reason,
        ]);
    }

    /**
     * 验证旧版组编辑拒绝其他代理树客户且无写入。
     */
    public function test_legacy_group_edit_rejects_other_agent_tree_customer_without_write(): void
    {
        $agentId = 411900400;
        $otherAgentId = $agentId + 100;
        $otherCustomerId = $otherAgentId + 1;
        $originGroupId = $this->insertCustomerGroup('owner-legacy-denied-origin-' . $agentId);
        $targetGroupName = 'owner-legacy-denied-target-' . $agentId;

        $this->insertCustomerGroup($targetGroupName);
        $this->deleteFixtureRows([$agentId, $otherAgentId, $otherCustomerId]);
        $this->insertUserInfo($agentId, 'owner-legacy-denied-root', 1, 0, 0);
        $this->insertUserInfo($otherAgentId, 'owner-legacy-denied-other-agent', 1, 0, 0);
        $this->insertUserInfo($otherCustomerId, 'owner-legacy-denied-other-customer', 2, $otherAgentId, $originGroupId);

        $login = UserLogin::where('user_id', $agentId)->firstOrFail();
        $response = $this->withoutMiddleware([JwtAuthMiddleware::class, SingleSignOn::class])
            ->actingAs($login, 'user')
            ->postJson('/user/cust/change/group_edit', [
                'userId' => $otherCustomerId,
                'grpName' => $targetGroupName,
                'trans_apply_reason' => 'legacy owner boundary should reject',
            ]);

        $response->assertOk()
            ->assertJsonPath('msg', 'FAIL');

        $this->assertNoGroupChangeApplication($agentId, $otherCustomerId);
    }

    /**
     * 验证组变更列表按当前申请人过滤，伪造 userId 不返回他人记录。
     */
    public function test_group_change_lists_are_scoped_to_current_applicant_when_filtering_by_user_id(): void
    {
        $agentId = 411900500;
        $customerId = $agentId + 1;
        $otherAgentId = $agentId + 100;
        $otherCustomerId = $otherAgentId + 1;
        $groupId = $this->insertCustomerGroup('owner-list-target-' . $agentId);

        $this->deleteFixtureRows([$agentId, $customerId, $otherAgentId, $otherCustomerId]);
        $this->insertUserInfo($agentId, 'owner-list-root-agent', 1, 0, 0);
        $this->insertUserInfo($customerId, 'owner-list-customer', 2, $agentId, $groupId);
        $this->insertUserInfo($otherAgentId, 'owner-list-other-agent', 1, 0, 0);
        $this->insertUserInfo($otherCustomerId, 'owner-list-other-customer', 2, $otherAgentId, $groupId);
        $this->insertApplyLog($customerId, $agentId, 'owner-list-root-agent', $groupId, 'visible-owned-log');
        $this->insertApplyLog($otherCustomerId, $otherAgentId, 'owner-list-other-agent', $groupId, 'hidden-other-log');

        $login = UserLogin::where('user_id', $agentId)->firstOrFail();
        $modernList = $this->withoutMiddleware([JwtAuthMiddleware::class, SingleSignOn::class])
            ->actingAs($login, 'user')
            ->getJson('/api/front/agents/group-changes');

        $modernList->assertOk()
            ->assertJsonPath('code', ResponseCode::SUCCESS);

        $modernRows = $modernList->json('data.list.data');
        $this->assertCount(1, $modernRows);
        $this->assertSame($customerId, (int) $modernRows[0]['trans_uid']);

        $modernSpoofed = $this->withoutMiddleware([JwtAuthMiddleware::class, SingleSignOn::class])
            ->actingAs($login, 'user')
            ->getJson('/api/front/agents/group-changes?userId=' . $otherCustomerId);

        $modernSpoofed->assertOk()
            ->assertJsonPath('code', ResponseCode::SUCCESS);
        $this->assertSame([], $modernSpoofed->json('data.list.data'));

        $legacySpoofed = $this->withoutMiddleware([JwtAuthMiddleware::class, SingleSignOn::class])
            ->actingAs($login, 'user')
            ->postJson('/user/cust/directCustChangeListSearch', [
                'userId' => $otherCustomerId,
            ]);

        $legacySpoofed->assertOk()
            ->assertJsonPath('code', ResponseCode::SUCCESS);
        $this->assertSame([], $legacySpoofed->json('data.list.data'));
    }

    /**
     * 验证最终清单文档已记录组变更归属边界（## 250）。
     */
    public function test_final_checklist_records_group_change_owner_boundary(): void
    {
        $checklist = file_get_contents(base_path('docs/admin-backend-blade-permission-final-checklist.md')) ?: '';

        $this->assertStringContainsString('## 250.', $checklist);
        $this->assertStringContainsString('groupChange', $checklist);
        $this->assertStringContainsString('changeDirectCustGroupEdit', $checklist);
        $this->assertStringContainsString('/api/front/agents/group-change-applications', $checklist);
        $this->assertStringContainsString('user/cust/change/group_edit', $checklist);
        $this->assertStringContainsString('trans_apply_logs', $checklist);
        $this->assertStringContainsString('FrontAgentGroupChangeOwnerBoundaryClosureModuleTest', $checklist);
    }

    private function insertCustomerGroup(string $name): int
    {
        $now = time();

        return (int) DB::table('group_configs')->insertGetId([
            'pair_id' => null,
            'name' => $name,
            'radix' => 50,
            'category' => 2,
            'has_commission' => 0,
            'is_enabled' => 1,
            'is_ecn' => 0,
            'is_default' => 0,
            'created_by' => 0,
            'updated_by' => 0,
            'created_at' => $now,
            'updated_at' => $now,
            'deleted_at' => null,
        ]);
    }

    private function insertUserInfo(int $userId, string $userName, int $accountType, int $parentId, int $groupId): void
    {
        $now = time();

        DB::table('user_infos')->where('user_id', $userId)->delete();
        DB::table('user_logins')->where('user_id', $userId)->delete();

        $loginId = DB::table('user_logins')->insertGetId([
            'user_id' => $userId,
            'email' => 'front-group-owner-boundary-' . $userId . '@example.test',
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
            'phone' => '1789000' . substr((string) $userId, -4),
            'gender' => 1,
            'account_type' => $accountType,
            'parent_id' => $parentId,
            'family_tree' => '',
            'group_id' => $groupId,
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

    private function insertApplyLog(int $customerId, int $agentId, string $agentName, int $groupId, string $reason): void
    {
        $now = time();

        DB::table('trans_apply_logs')->insert([
            'user_id' => $customerId,
            'origin_group_id' => 0,
            'group_id' => $groupId,
            'group_name' => 'owner-list-target',
            'applicant_id' => $agentId,
            'applicant_name' => $agentName,
            'status' => 0,
            'apply_reason' => $reason,
            'reject_reason' => '',
            'created_by' => $agentName,
            'updated_by' => '',
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

        DB::table('trans_apply_logs')
            ->where(function ($query) use ($userIds) {
                $query->whereIn('user_id', $userIds)
                    ->orWhereIn('applicant_id', $userIds);
            })
            ->delete();
    }

    private function assertNoGroupChangeApplication(int $agentId, int $customerId): void
    {
        $this->assertSame(
            0,
            DB::table('trans_apply_logs')
                ->where('user_id', $customerId)
                ->where('applicant_id', $agentId)
                ->count()
        );
    }
}
