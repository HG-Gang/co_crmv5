<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/02
 * Time: 01:52
 */

/**
 * 旧版组编辑申请理由（trans_apply_reason）持久化闭环测试。
 *
 * 文件功能：
 * - 验证旧版 /user/cust/change/group_edit 把 trans_apply_reason 写入 trans_apply_logs.apply_reason。
 * - 验证最终清单文档已记录理由映射边界。
 *
 * 适用场景：
 * - 前台代理旧版组变更接口理由字段的回归测试。
 *
 * 入参例子：
 * - POST /user/cust/change/group_edit
 *   userId: {customerId}, grpName: {targetGroupName}, trans_apply_reason: {reason}
 *
 * 返回值：
 * - 接口返回 msg 为 SUCCESS；trans_apply_logs 记录含 apply_reason 与目标组名。
 *
 * 异常或失败场景：
 * - 若理由未持久化或映射错误，断言失败。
 */

namespace Tests\Feature;

use App\Http\Middleware\JwtAuthMiddleware;
use App\Http\Middleware\SingleSignOn;
use App\Models\UserLogin;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class FrontAgentGroupChangeLegacyReasonClosureModuleTest extends TestCase
{
    use DatabaseTransactions;

    /**
     * 验证旧版组编辑把申请理由写入 apply_log。
     */
    public function test_legacy_group_edit_writes_trans_apply_reason_to_apply_log(): void
    {
        $agentId = 411870100;
        $customerId = $agentId + 1;
        $originGroupId = $this->insertCustomerGroup('legacy-reason-origin');
        $targetGroupName = 'legacy-reason-target';
        $reason = 'legacy trans apply reason should persist';

        $this->insertCustomerGroup($targetGroupName);
        $this->deleteFixtureRows([$agentId, $customerId]);
        $this->insertUserInfo($agentId, 'legacy-reason-root-agent', 1, 0, 0);
        $this->insertUserInfo($customerId, 'legacy-reason-customer', 2, $agentId, $originGroupId);

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
            'applicant_id' => $agentId,
            'group_name' => $targetGroupName,
            'origin_group_id' => $originGroupId,
            'apply_reason' => $reason,
        ]);
    }

    /**
     * 验证最终清单文档已记录旧版组编辑理由映射（## 187）。
     */
    public function test_final_checklist_records_legacy_group_edit_reason_mapping(): void
    {
        $checklist = file_get_contents(base_path('docs/admin-backend-blade-permission-final-checklist.md')) ?: '';

        $this->assertStringContainsString('## 187.', $checklist);
        $this->assertStringContainsString('changeDirectCustGroupEdit', $checklist);
        $this->assertStringContainsString('trans_apply_reason', $checklist);
        $this->assertStringContainsString('apply_reason', $checklist);
        $this->assertStringContainsString('FrontAgentGroupChangeLegacyReasonClosureModuleTest', $checklist);
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
            'email' => 'front-group-legacy-reason-' . $userId . '@example.test',
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
            'phone' => '1788700' . substr((string) $userId, -4),
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
}
