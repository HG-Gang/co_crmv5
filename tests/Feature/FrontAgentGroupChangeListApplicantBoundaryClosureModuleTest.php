<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/02
 * Time: 01:52
 */

/**
 * 前台组变更列表申请人边界（List Applicant Boundary）闭环测试。
 *
 * 文件功能：
 * - 验证普通客户账号不能读取代理组变更列表。
 * - 验证最终清单文档已记录列表申请人边界（account_type=1 才能读取）。
 *
 * 适用场景：
 * - 前台代理组变更列表接口的越权防护回归测试。
 *
 * 入参例子：
 * - GET /api/front/agents/group-changes（以客户账号登录）。
 *
 * 返回值：
 * - 客户访问返回 code 为 PERMISSION_DENIED。
 *
 * 异常或失败场景：
 * - 若客户能读取组变更列表，断言失败。
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

class FrontAgentGroupChangeListApplicantBoundaryClosureModuleTest extends TestCase
{
    use DatabaseTransactions;

    /**
     * 验证客户账号不能读取代理组变更列表。
     */
    public function test_customer_account_cannot_read_agent_group_change_list(): void
    {
        $customerId = 411890100;

        $this->deleteFixtureRows([$customerId]);
        $this->insertUserInfo($customerId, 'group-change-list-customer', 2, 0, 0);
        $this->insertApplyLog($customerId);

        $login = UserLogin::where('user_id', $customerId)->firstOrFail();
        $response = $this->withoutMiddleware([JwtAuthMiddleware::class, SingleSignOn::class])
            ->actingAs($login, 'user')
            ->getJson('/api/front/agents/group-changes');

        $response->assertOk()
            ->assertJsonPath('code', ResponseCode::PERMISSION_DENIED);
    }

    /**
     * 验证最终清单文档已记录组变更列表申请人边界（## 189）。
     */
    public function test_final_checklist_records_group_change_list_applicant_boundary(): void
    {
        $checklist = file_get_contents(base_path('docs/admin-backend-blade-permission-final-checklist.md')) ?: '';

        $this->assertStringContainsString('## 189.', $checklist);
        $this->assertStringContainsString('groupChangeList', $checklist);
        $this->assertStringContainsString('account_type=1', $checklist);
        $this->assertStringContainsString('available_groups', $checklist);
        $this->assertStringContainsString('FrontAgentGroupChangeListApplicantBoundaryClosureModuleTest', $checklist);
    }

    private function insertUserInfo(int $userId, string $userName, int $accountType, int $parentId, int $groupId): void
    {
        $now = time();

        DB::table('user_infos')->where('user_id', $userId)->delete();
        DB::table('user_logins')->where('user_id', $userId)->delete();

        $loginId = DB::table('user_logins')->insertGetId([
            'user_id' => $userId,
            'email' => 'front-group-list-boundary-' . $userId . '@example.test',
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
            'phone' => '1788900' . substr((string) $userId, -4),
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

    private function insertApplyLog(int $customerId): void
    {
        $now = time();

        DB::table('trans_apply_logs')->insert([
            'user_id' => $customerId,
            'origin_group_id' => 0,
            'group_id' => 1,
            'group_name' => 'customer-owned-log',
            'applicant_id' => $customerId,
            'applicant_name' => 'group-change-list-customer',
            'status' => 0,
            'apply_reason' => 'ordinary customer should not read agent list',
            'reject_reason' => '',
            'created_by' => 'group-change-list-customer',
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
}
