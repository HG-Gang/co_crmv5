<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/02
 * Time: 01:52
 */

/**
 * 前台客户组变更申请写入闭环测试。
 *
 * 文件功能：
 * - 验证代理可为直接客户提交组变更申请并完整写入 trans_apply_logs。
 * - 验证不能为直接代理商提交客户组变更申请（account_type=2 才可作目标）。
 * - 验证最终清单文档已记录客户组变更写入闭环。
 *
 * 适用场景：
 * - 前台代理组变更申请写入与目标类型校验的回归测试。
 *
 * 入参例子：
 * - POST /api/front/agents/group-change-applications
 *   target_user_id: {customerId}, new_group_id: {targetGroupId}, reason: {reason}
 *
 * 返回值：
 * - 对直接客户申请返回 code 为 SUCCESS 并写入日志；对代理商返回 PERMISSION_DENIED。
 *
 * 异常或失败场景：
 * - 若目标类型校验失效或日志字段不完整，断言失败。
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

class FrontAgentGroupChangeWriteClosureModuleTest extends TestCase
{
    use DatabaseTransactions;

    /**
     * 验证代理可为直接客户提交组变更申请并写入完整日志。
     */
    public function test_agent_can_submit_group_change_application_for_direct_customer(): void
    {
        $agentId = 411850100;
        $customerId = $agentId + 1;
        $originGroupId = $this->insertCustomerGroup('group-change-origin');
        $targetGroupId = $this->insertCustomerGroup('group-change-target');
        $reason = 'legacy customer group write closure';

        $this->deleteFixtureRows([$agentId, $customerId]);
        $this->insertUserInfo($agentId, 'group-change-root-agent', 1, 0, 0);
        $this->insertUserInfo($customerId, 'group-change-direct-customer', 2, $agentId, $originGroupId);

        $this->assertSame(0, DB::table('agent_descendants')->where('agent_id', $agentId)->count());

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
            'group_name' => 'group-change-target',
            'applicant_id' => $agentId,
            'applicant_name' => 'group-change-root-agent',
            'status' => 0,
            'apply_reason' => $reason,
        ]);
    }

    /**
     * 验证不能为直接代理商提交客户组变更申请。
     */
    public function test_agent_cannot_submit_customer_group_change_application_for_direct_agent(): void
    {
        $agentId = 411850200;
        $subAgentId = $agentId + 1;
        $targetGroupId = $this->insertCustomerGroup('group-change-denied-target');

        $this->deleteFixtureRows([$agentId, $subAgentId]);
        $this->insertUserInfo($agentId, 'group-change-deny-root', 1, 0, 0);
        $this->insertUserInfo($subAgentId, 'group-change-deny-sub-agent', 1, $agentId, 0);

        $this->assertSame(0, DB::table('agent_descendants')->where('agent_id', $agentId)->count());

        $login = UserLogin::where('user_id', $agentId)->firstOrFail();
        $response = $this->withoutMiddleware([JwtAuthMiddleware::class, SingleSignOn::class])
            ->actingAs($login, 'user')
            ->postJson('/api/front/agents/group-change-applications', [
                'target_user_id' => $subAgentId,
                'new_group_id' => $targetGroupId,
                'reason' => 'should not write',
            ]);

        $response->assertOk()
            ->assertJsonPath('code', ResponseCode::PERMISSION_DENIED);

        $this->assertSame(
            0,
            DB::table('trans_apply_logs')
                ->where('user_id', $subAgentId)
                ->where('applicant_id', $agentId)
                ->count()
        );
    }

    /**
     * 验证最终清单文档已记录客户组变更写入闭环（## 185）。
     */
    public function test_final_checklist_records_customer_group_change_write_closure(): void
    {
        $checklist = file_get_contents(base_path('docs/admin-backend-blade-permission-final-checklist.md')) ?: '';

        $this->assertStringContainsString('## 185.', $checklist);
        $this->assertStringContainsString('groupChange', $checklist);
        $this->assertStringContainsString('account_type=2', $checklist);
        $this->assertStringContainsString('trans_apply_logs', $checklist);
        $this->assertStringContainsString('FrontAgentGroupChangeWriteClosureModuleTest', $checklist);
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
            'email' => 'front-group-change-write-' . $userId . '@example.test',
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
            'phone' => '1788500' . substr((string) $userId, -4),
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
