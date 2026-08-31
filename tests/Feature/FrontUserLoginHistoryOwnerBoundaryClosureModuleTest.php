<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/02
 * Time: 15:09
 */

/**
 * 前端用户登录历史“拥有者（owner）边界”回归测试：代理只能查看自己分支下的客户登录历史。
 *
 * 文件功能：
 * - 现代接口 GET /api/front/users/login-history：代理（account_type=1）查自己分支客户时
 *   返回 SUCCESS 且包含该客户日志（IP/设备），响应体不得混入他人分支数据；查他人分支客户时
 *   返回 PERMISSION_DENIED，且响应体不含他人 IP、设备标识与 user_id。
 * - 旧接口 POST /user/cust/loginHistorySearch/{uid}：自己分支客户返回 total=1 且含日志；
 *   他人分支客户返回 total=0、rows=[]，同样不泄露任何数据。
 * - 校验 docs/admin-backend-blade-permission-final-checklist.md 已记录该边界验收项（## 248.，
 *   覆盖 AgentController::userLoginHistory / legacyLoginHistorySearch / user_login_logs）。
 *
 * 适用场景：任何改动登录历史查询、代理分支（family_tree/agent_descendants）归属判断或
 * 旧接口数据源映射的提交都应回归本文件，防止跨分支越权读取登录日志。
 *
 * 入参：无外部参数；用例内固定构造 viewerAgent、ownCustomer、otherAgent、otherCustomer
 * 四个账号（含 parent_id 与 family_tree 归属关系），并为每个账号预置一条 user_login_logs。
 *
 * 返回值：无返回值；可见/不可见两组断言的组合通过即表示“分支内可见、分支外拒绝且不泄露”闭环。
 *
 * 失败场景：断言失败意味着代理可查看他人分支的登录历史（现代接口或旧接口任一越权），
 * 属于横向越权/隐私泄露回归，必须阻断上线。
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

class FrontUserLoginHistoryOwnerBoundaryClosureModuleTest extends TestCase
{
    use DatabaseTransactions;

    public function test_modern_user_login_history_rejects_other_branch_target_without_leaking_logs(): void
    {
        $viewerAgentId = 412480100;
        $ownCustomerId = 412480101;
        $otherAgentId = 412480102;
        $otherCustomerId = 412480103;

        $this->deleteFixtureRows([$viewerAgentId, $ownCustomerId, $otherAgentId, $otherCustomerId]);
        $this->insertUserWithLoginLog($viewerAgentId, 'login-history-owner-viewer-agent', 1, 0, '203.0.113.10', 'viewer-agent-device');
        $this->insertUserWithLoginLog($ownCustomerId, 'login-history-owner-own-customer', 2, $viewerAgentId, '203.0.113.11', 'own-customer-device');
        $this->insertUserWithLoginLog($otherAgentId, 'login-history-owner-other-agent', 1, 0, '203.0.113.12', 'other-agent-device');
        $this->insertUserWithLoginLog($otherCustomerId, 'login-history-owner-other-customer', 2, $otherAgentId, '203.0.113.13', 'other-customer-device');

        $login = UserLogin::where('user_id', $viewerAgentId)->firstOrFail();
        $visibleResponse = $this->withoutMiddleware([JwtAuthMiddleware::class, SingleSignOn::class])
            ->actingAs($login, 'user')
            ->getJson('/api/front/users/login-history?user_id=' . $ownCustomerId);

        $visibleResponse->assertOk()
            ->assertJsonPath('code', ResponseCode::SUCCESS)
            ->assertJsonPath('data.user_id', $ownCustomerId);
        $this->assertStringContainsString('203.0.113.11', $visibleResponse->getContent());
        $this->assertStringContainsString('own-customer-device', $visibleResponse->getContent());
        $this->assertStringNotContainsString('203.0.113.13', $visibleResponse->getContent());
        $this->assertStringNotContainsString('other-customer-device', $visibleResponse->getContent());

        $outsideResponse = $this->withoutMiddleware([JwtAuthMiddleware::class, SingleSignOn::class])
            ->actingAs($login, 'user')
            ->getJson('/api/front/users/login-history?user_id=' . $otherCustomerId);

        $outsideResponse->assertOk()
            ->assertJsonPath('code', ResponseCode::PERMISSION_DENIED);
        $this->assertStringNotContainsString('203.0.113.13', $outsideResponse->getContent());
        $this->assertStringNotContainsString('other-customer-device', $outsideResponse->getContent());
        $this->assertStringNotContainsString((string) $otherCustomerId, $outsideResponse->getContent());
    }

    public function test_legacy_user_login_history_rejects_other_branch_target_without_leaking_rows(): void
    {
        $viewerAgentId = 412480200;
        $ownCustomerId = 412480201;
        $otherAgentId = 412480202;
        $otherCustomerId = 412480203;

        $this->deleteFixtureRows([$viewerAgentId, $ownCustomerId, $otherAgentId, $otherCustomerId]);
        $this->insertUserWithLoginLog($viewerAgentId, 'legacy-login-history-viewer-agent', 1, 0, '203.0.113.20', 'legacy-viewer-device');
        $this->insertUserWithLoginLog($ownCustomerId, 'legacy-login-history-own-customer', 2, $viewerAgentId, '203.0.113.21', 'legacy-own-device');
        $this->insertUserWithLoginLog($otherAgentId, 'legacy-login-history-other-agent', 1, 0, '203.0.113.22', 'legacy-other-agent-device');
        $this->insertUserWithLoginLog($otherCustomerId, 'legacy-login-history-other-customer', 2, $otherAgentId, '203.0.113.23', 'legacy-other-device');

        $login = UserLogin::where('user_id', $viewerAgentId)->firstOrFail();
        $visibleResponse = $this->withoutMiddleware([JwtAuthMiddleware::class, SingleSignOn::class])
            ->actingAs($login, 'user')
            ->postJson('/user/cust/loginHistorySearch/' . $ownCustomerId);

        $visibleResponse->assertOk()
            ->assertJsonPath('total', 1);
        $this->assertStringContainsString('203.0.113.21', $visibleResponse->getContent());
        $this->assertStringContainsString('legacy-own-device', $visibleResponse->getContent());
        $this->assertStringNotContainsString('203.0.113.23', $visibleResponse->getContent());
        $this->assertStringNotContainsString('legacy-other-device', $visibleResponse->getContent());

        $outsideResponse = $this->withoutMiddleware([JwtAuthMiddleware::class, SingleSignOn::class])
            ->actingAs($login, 'user')
            ->postJson('/user/cust/loginHistorySearch/' . $otherCustomerId);

        $outsideResponse->assertOk()
            ->assertJsonPath('total', 0)
            ->assertJsonPath('rows', []);
        $this->assertStringNotContainsString('203.0.113.23', $outsideResponse->getContent());
        $this->assertStringNotContainsString('legacy-other-device', $outsideResponse->getContent());
        $this->assertStringNotContainsString((string) $otherCustomerId, $outsideResponse->getContent());
    }

    public function test_final_checklist_records_user_login_history_owner_boundary(): void
    {
        $checklist = file_get_contents(base_path('docs/admin-backend-blade-permission-final-checklist.md')) ?: '';

        $this->assertStringContainsString('## 248.', $checklist);
        $this->assertStringContainsString('AgentController::userLoginHistory', $checklist);
        $this->assertStringContainsString('AgentController::legacyLoginHistorySearch', $checklist);
        $this->assertStringContainsString('/api/front/users/login-history', $checklist);
        $this->assertStringContainsString('user/cust/loginHistorySearch/{uid}', $checklist);
        $this->assertStringContainsString('user_login_logs', $checklist);
        $this->assertStringContainsString('FrontUserLoginHistoryOwnerBoundaryClosureModuleTest', $checklist);
    }

    private function insertUserWithLoginLog(
        int $userId,
        string $userName,
        int $accountType,
        int $parentId,
        string $loginIp,
        string $userAgent
    ): void {
        $now = time();

        DB::table('user_login_logs')->where('user_id', $userId)->delete();
        DB::table('user_infos')->where('user_id', $userId)->delete();
        DB::table('user_logins')->where('user_id', $userId)->delete();

        $loginId = DB::table('user_logins')->insertGetId([
            'user_id' => $userId,
            'email' => 'front-user-login-history-owner-' . $userId . '@example.test',
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
            'phone' => '1782480' . substr((string) $userId, -4),
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

        DB::table('user_login_logs')->insert([
            'login_id' => $loginId,
            'user_id' => $userId,
            'login_ip' => $loginIp,
            'ip_location' => 'owner-boundary-location',
            'user_agent' => $userAgent,
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
        DB::table('user_login_logs')->whereIn('user_id', $userIds)->delete();
        DB::table('user_infos')->whereIn('user_id', $userIds)->delete();
        DB::table('user_logins')->whereIn('user_id', $userIds)->delete();
    }
}
