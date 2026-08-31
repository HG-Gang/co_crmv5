<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/02
 * Time: 01:47
 */

/**
 * 前台直接代理持仓汇总申请人边界闭环测试。
 *
 * 文件功能：
 * - 验证普通客户（account_type=2）不能读取直接代理的持仓汇总，包括现代接口
 *   /api/front/positions/direct-agent-summaries 与遗留接口 /user/position/v2/subAgentsListSearchV2。
 * - 验证权限清单文档记录了该边界闭环。
 *
 * 适用场景：
 * - 前台持仓模块“直接代理持仓汇总”功能的回归测试，防止普通客户越权查看代理持仓。
 *
 * 入参例子：
 * - 登录账号：普通客户（account_type=2），其下挂一个直接代理（account_type=1）。
 *
 * 返回值：
 * - 接口返回 HTTP 200，code 为 PERMISSION_DENIED，响应体不含代理 ID 与代理用户名。
 *
 * 异常或失败场景：
 * - 普通客户访问直接代理持仓汇总接口时被拒绝（PERMISSION_DENIED）。
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

class FrontPositionDirectAgentSummaryApplicantBoundaryClosureModuleTest extends TestCase
{
    use DatabaseTransactions;

    // 验证普通客户不能通过现代接口读取直接代理的持仓汇总。
    public function test_customer_account_cannot_read_modern_direct_agent_position_summaries(): void
    {
        $customerId = 412050100;
        $directAgentId = 412050101;

        $this->deleteFixtureRows([$customerId, $directAgentId]);
        $this->insertUserInfo($customerId, 'position-direct-summary-boundary-customer', 2, 0);
        $this->insertUserInfo($directAgentId, 'position-direct-summary-boundary-agent', 1, $customerId);

        $login = UserLogin::where('user_id', $customerId)->firstOrFail();
        $response = $this->withoutMiddleware([JwtAuthMiddleware::class, SingleSignOn::class])
            ->actingAs($login, 'user')
            ->getJson('/api/front/positions/direct-agent-summaries');

        $response->assertOk()
            ->assertJsonPath('code', ResponseCode::PERMISSION_DENIED);
        $this->assertStringNotContainsString((string) $directAgentId, $response->getContent());
        $this->assertStringNotContainsString('position-direct-summary-boundary-agent', $response->getContent());
    }

    // 验证普通客户不能通过遗留接口读取直接代理的持仓汇总。
    public function test_customer_account_cannot_read_legacy_direct_agent_position_summaries(): void
    {
        $customerId = 412050200;
        $directAgentId = 412050201;

        $this->deleteFixtureRows([$customerId, $directAgentId]);
        $this->insertUserInfo($customerId, 'position-direct-summary-legacy-boundary-customer', 2, 0);
        $this->insertUserInfo($directAgentId, 'position-direct-summary-legacy-boundary-agent', 1, $customerId);

        $login = UserLogin::where('user_id', $customerId)->firstOrFail();
        $response = $this->withoutMiddleware([JwtAuthMiddleware::class, SingleSignOn::class])
            ->actingAs($login, 'user')
            ->postJson('/user/position/v2/subAgentsListSearchV2');

        $response->assertOk()
            ->assertJsonPath('code', ResponseCode::PERMISSION_DENIED);
        $this->assertStringNotContainsString((string) $directAgentId, $response->getContent());
        $this->assertStringNotContainsString('position-direct-summary-legacy-boundary-agent', $response->getContent());
    }

    // 校验权限清单文档记录了直接代理持仓汇总申请人边界闭环。
    public function test_final_checklist_records_direct_agent_position_summary_applicant_boundary(): void
    {
        $checklist = file_get_contents(base_path('docs/admin-backend-blade-permission-final-checklist.md')) ?: '';

        $this->assertStringContainsString('## 205.', $checklist);
        $this->assertStringContainsString('subPositionSummary', $checklist);
        $this->assertStringContainsString('/api/front/positions/direct-agent-summaries', $checklist);
        $this->assertStringContainsString('user/position/v2/subAgentsListSearchV2', $checklist);
        $this->assertStringContainsString('account_type=1', $checklist);
        $this->assertStringContainsString('FrontPositionDirectAgentSummaryApplicantBoundaryClosureModuleTest', $checklist);
    }

    private function insertUserInfo(int $userId, string $userName, int $accountType, int $parentId): void
    {
        $now = time();

        DB::table('user_infos')->where('user_id', $userId)->delete();
        DB::table('user_logins')->where('user_id', $userId)->delete();

        $loginId = DB::table('user_logins')->insertGetId([
            'user_id' => $userId,
            'email' => 'front-position-direct-summary-boundary-' . $userId . '@example.test',
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
            'phone' => '1782050' . substr((string) $userId, -4),
            'gender' => 1,
            'account_type' => $accountType,
            'parent_id' => $parentId,
            'family_tree' => $parentId > 0 ? $parentId . ',' . $userId : '',
            'group_id' => 0,
            'level_id' => 0,
            'comm_rate' => 0,
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
        DB::table('commission_records')->whereIn('agent_id', $userIds)->orWhereIn('parent_id', $userIds)->delete();
        DB::table('user_trades')->whereIn('user_id', $userIds)->delete();
        DB::table('agent_descendants')
            ->where(function ($query) use ($userIds) {
                $query->whereIn('agent_id', $userIds)
                    ->orWhereIn('descendant_id', $userIds);
            })
            ->delete();
    }
}
