<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/02
 * Time: 01:51
 */

/**
 * 前台组变更申请人边界（Applicant Boundary）闭环测试。
 *
 * 文件功能：
 * - 验证普通客户账号不能为自己提交组变更申请。
 * - 验证最终清单文档已记录申请人边界（account_type=1 才能申请）。
 *
 * 适用场景：
 * - 前台代理组变更申请的越权防护回归测试。
 *
 * 入参例子：
 * - POST /api/front/agents/group-change-applications
 *   target_user_id: {customerId}, new_group_id: {targetGroupId}, reason: {reason}
 *
 * 返回值：
 * - 客户自申请返回 code 为 PERMISSION_DENIED，trans_apply_logs 无记录。
 *
 * 异常或失败场景：
 * - 若客户自申请被放行并写入日志，断言失败。
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

class FrontAgentGroupChangeApplicantBoundaryClosureModuleTest extends TestCase
{
    use DatabaseTransactions;

    /**
     * 验证客户账号不能为自己提交组变更申请。
     */
    public function test_customer_account_cannot_submit_group_change_application_for_itself(): void
    {
        $customerId = 411880100;
        $originGroupId = $this->insertCustomerGroup('applicant-boundary-origin');
        $targetGroupId = $this->insertCustomerGroup('applicant-boundary-target');

        $this->deleteFixtureRows([$customerId]);
        $this->insertUserInfo($customerId, 'applicant-boundary-customer', 2, 0, $originGroupId);

        $login = UserLogin::where('user_id', $customerId)->firstOrFail();
        $response = $this->withoutMiddleware([JwtAuthMiddleware::class, SingleSignOn::class])
            ->actingAs($login, 'user')
            ->postJson('/api/front/agents/group-change-applications', [
                'target_user_id' => $customerId,
                'new_group_id' => $targetGroupId,
                'reason' => 'customer self group change should be rejected',
            ]);

        $response->assertOk()
            ->assertJsonPath('code', ResponseCode::PERMISSION_DENIED);

        $this->assertSame(
            0,
            DB::table('trans_apply_logs')
                ->where('user_id', $customerId)
                ->where('applicant_id', $customerId)
                ->count()
        );
    }

    /**
     * 验证最终清单文档已记录组变更申请人边界（## 188）。
     */
    public function test_final_checklist_records_group_change_applicant_boundary(): void
    {
        $checklist = file_get_contents(base_path('docs/admin-backend-blade-permission-final-checklist.md')) ?: '';

        $this->assertStringContainsString('## 188. 2026-07-09', $checklist);
        $this->assertStringContainsString('groupChange', $checklist);
        $this->assertStringContainsString('account_type=1', $checklist);
        $this->assertStringContainsString('FrontAgentGroupChangeApplicantBoundaryClosureModuleTest', $checklist);
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
            'email' => 'front-group-applicant-boundary-' . $userId . '@example.test',
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
            'phone' => '1788800' . substr((string) $userId, -4),
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
