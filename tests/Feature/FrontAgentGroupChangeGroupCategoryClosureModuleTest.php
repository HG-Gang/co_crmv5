<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/02
 * Time: 01:51
 */

/**
 * 前台组变更目标组类别（Group Category）边界闭环测试。
 *
 * 文件功能：
 * - 验证现代组变更申请拒绝把客户组目标指向代理商组（category=1）。
 * - 验证旧版 group_edit 按组名拒绝代理商组。
 * - 验证拒绝时不会写入 trans_apply_logs。
 * - 验证最终清单文档已记录客户组类别边界（group_configs.category=2）。
 *
 * 适用场景：
 * - 前台代理为客户申请组变更时的类别校验回归测试。
 *
 * 入参例子：
 * - POST /api/front/agents/group-change-applications
 *   target_user_id: {customerId}, new_group_id: {agentGroupId}
 * - POST /user/cust/change/group_edit
 *   userId: {customerId}, grpName: {agentGroupName}
 *
 * 返回值：
 * - 现代接口返回 VALIDATION_FAILED；旧版接口返回 msg 为 FAIL；均无日志写入。
 *
 * 异常或失败场景：
 * - 若代理商组被当作客户组接受并写入日志，断言失败。
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

class FrontAgentGroupChangeGroupCategoryClosureModuleTest extends TestCase
{
    use DatabaseTransactions;

    /**
     * 验证现代组变更申请拒绝代理商组 id 且无写入。
     */
    public function test_customer_group_change_rejects_agent_group_id_without_write(): void
    {
        $agentId = 411860100;
        $customerId = $agentId + 1;
        $originGroupId = $this->insertGroup('group-category-origin-customer', 2);
        $agentGroupId = $this->insertGroup('group-category-agent-target', 1);

        $this->deleteFixtureRows([$agentId, $customerId]);
        $this->insertUserInfo($agentId, 'group-category-root-agent', 1, 0, 0);
        $this->insertUserInfo($customerId, 'group-category-customer', 2, $agentId, $originGroupId);

        $login = UserLogin::where('user_id', $agentId)->firstOrFail();
        $response = $this->withoutMiddleware([JwtAuthMiddleware::class, SingleSignOn::class])
            ->actingAs($login, 'user')
            ->postJson('/api/front/agents/group-change-applications', [
                'target_user_id' => $customerId,
                'new_group_id' => $agentGroupId,
                'reason' => 'should reject agent group',
            ]);

        $response->assertOk()
            ->assertJsonPath('code', ResponseCode::VALIDATION_FAILED);

        $this->assertNoGroupChangeApplication($agentId, $customerId);
    }

    /**
     * 验证旧版 group_edit 拒绝代理商组名且无写入。
     */
    public function test_legacy_group_edit_rejects_agent_group_name_without_write(): void
    {
        $agentId = 411860200;
        $customerId = $agentId + 1;
        $originGroupId = $this->insertGroup('legacy-group-category-origin', 2);
        $agentGroupName = 'legacy-group-category-agent-target';

        $this->insertGroup($agentGroupName, 1);
        $this->deleteFixtureRows([$agentId, $customerId]);
        $this->insertUserInfo($agentId, 'legacy-group-category-root', 1, 0, 0);
        $this->insertUserInfo($customerId, 'legacy-group-category-customer', 2, $agentId, $originGroupId);

        $login = UserLogin::where('user_id', $agentId)->firstOrFail();
        $response = $this->withoutMiddleware([JwtAuthMiddleware::class, SingleSignOn::class])
            ->actingAs($login, 'user')
            ->postJson('/user/cust/change/group_edit', [
                'userId' => $customerId,
                'grpName' => $agentGroupName,
                'trans_apply_reason' => 'legacy request should not write',
            ]);

        $response->assertOk()
            ->assertJsonPath('msg', 'FAIL');

        $this->assertNoGroupChangeApplication($agentId, $customerId);
    }

    /**
     * 验证最终清单文档已记录客户组类别边界（## 186）。
     */
    public function test_final_checklist_records_customer_group_category_boundary(): void
    {
        $checklist = file_get_contents(base_path('docs/admin-backend-blade-permission-final-checklist.md')) ?: '';

        $this->assertStringContainsString('## 186.', $checklist);
        $this->assertStringContainsString('group_configs.category=2', $checklist);
        $this->assertStringContainsString('changeDirectCustGroupEdit', $checklist);
        $this->assertStringContainsString('FrontAgentGroupChangeGroupCategoryClosureModuleTest', $checklist);
    }

    private function insertGroup(string $name, int $category): int
    {
        $now = time();

        return (int) DB::table('group_configs')->insertGetId([
            'pair_id' => null,
            'name' => $name,
            'radix' => 50,
            'category' => $category,
            'has_commission' => $category === 1 ? 1 : 0,
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
            'email' => 'front-group-category-' . $userId . '@example.test',
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
            'phone' => '1788600' . substr((string) $userId, -4),
            'gender' => 1,
            'account_type' => $accountType,
            'parent_id' => $parentId,
            'family_tree' => '',
            'group_id' => $groupId,
            'level_id' => $accountType === 1 ? 2 : 0,
            'comm_rate' => $accountType === 1 ? 0.2 : 0,
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
