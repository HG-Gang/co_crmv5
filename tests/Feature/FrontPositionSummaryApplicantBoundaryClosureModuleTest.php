<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/02
 * Time: 01:47
 */

/**
 * 前台持仓汇总下钻申请人边界闭环测试。
 *
 * 文件功能：
 * - 验证普通客户（account_type=2）不能下钻查看子代理的持仓汇总，包括现代接口
 *   /api/front/positions/summary 与遗留接口 /user/position/positionSummarySearch。
 * - 验证权限清单文档记录了该边界闭环。
 *
 * 适用场景：
 * - 前台持仓模块“子代理持仓汇总下钻”功能的回归测试，防止普通客户越权查看代理持仓。
 *
 * 入参例子：
 * - 登录账号：普通客户（account_type=2），其下挂一个子代理（account_type=1）。
 * - 现代接口参数：target_id={childAgentId}&per_page=20；遗留接口参数：userPId={childAgentId}。
 *
 * 返回值：
 * - 接口返回 HTTP 200，code 为 PERMISSION_DENIED，响应体不含代理 ID 与代理用户名。
 *
 * 异常或失败场景：
 * - 普通客户下钻子代理持仓汇总时被拒绝（PERMISSION_DENIED）。
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

class FrontPositionSummaryApplicantBoundaryClosureModuleTest extends TestCase
{
    use DatabaseTransactions;

    // 验证普通客户不能通过现代接口下钻子代理持仓汇总。
    public function test_customer_account_cannot_drill_into_modern_child_agent_position_summary(): void
    {
        $customerId = 412150100;
        $childAgentId = 412150101;

        $this->deleteFixtureRows([$customerId, $childAgentId]);
        $this->insertUserInfo($customerId, 'position-summary-boundary-customer', 2, 0);
        $this->insertUserInfo($childAgentId, 'position-summary-boundary-child-agent', 1, $customerId);

        $login = UserLogin::where('user_id', $customerId)->firstOrFail();
        $response = $this->withoutMiddleware([JwtAuthMiddleware::class, SingleSignOn::class])
            ->actingAs($login, 'user')
            ->getJson('/api/front/positions/summary?target_id=' . $childAgentId . '&per_page=20');

        $response->assertOk()
            ->assertJsonPath('code', ResponseCode::PERMISSION_DENIED);
        $this->assertStringNotContainsString((string) $childAgentId, $response->getContent());
        $this->assertStringNotContainsString('position-summary-boundary-child-agent', $response->getContent());
    }

    // 验证普通客户不能通过遗留接口下钻子代理持仓汇总。
    public function test_customer_account_cannot_drill_into_legacy_child_agent_position_summary(): void
    {
        $customerId = 412150200;
        $childAgentId = 412150201;

        $this->deleteFixtureRows([$customerId, $childAgentId]);
        $this->insertUserInfo($customerId, 'position-summary-legacy-boundary-customer', 2, 0);
        $this->insertUserInfo($childAgentId, 'position-summary-legacy-boundary-child-agent', 1, $customerId);

        $login = UserLogin::where('user_id', $customerId)->firstOrFail();
        $response = $this->withoutMiddleware([JwtAuthMiddleware::class, SingleSignOn::class])
            ->actingAs($login, 'user')
            ->postJson('/user/position/positionSummarySearch', [
                'userPId' => $childAgentId,
                'per_page' => 20,
            ]);

        $response->assertOk()
            ->assertJsonPath('code', ResponseCode::PERMISSION_DENIED);
        $this->assertStringNotContainsString((string) $childAgentId, $response->getContent());
        $this->assertStringNotContainsString('position-summary-legacy-boundary-child-agent', $response->getContent());
    }

    // 校验权限清单文档记录了持仓汇总下钻申请人边界闭环。
    public function test_final_checklist_records_position_summary_applicant_boundary(): void
    {
        $checklist = file_get_contents(base_path('docs/admin-backend-blade-permission-final-checklist.md')) ?: '';

        $this->assertStringContainsString('## 215.', $checklist);
        $this->assertStringContainsString('PositionController::positionSummary', $checklist);
        $this->assertStringContainsString('/api/front/positions/summary', $checklist);
        $this->assertStringContainsString('user/position/positionSummarySearch', $checklist);
        $this->assertStringContainsString('account_type=1', $checklist);
        $this->assertStringContainsString('FrontPositionSummaryApplicantBoundaryClosureModuleTest', $checklist);
    }

    private function insertUserInfo(int $userId, string $userName, int $accountType, int $parentId): void
    {
        $now = time();

        DB::table('user_infos')->where('user_id', $userId)->delete();
        DB::table('user_logins')->where('user_id', $userId)->delete();

        $loginId = DB::table('user_logins')->insertGetId([
            'user_id' => $userId,
            'email' => 'front-position-summary-boundary-' . $userId . '@example.test',
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
            'phone' => '1782150' . substr((string) $userId, -4),
            'gender' => 1,
            'account_type' => $accountType,
            'parent_id' => $parentId,
            'family_tree' => $parentId > 0 ? $parentId . ',' . $userId : '',
            'group_id' => 0,
            'level_id' => $accountType === 1 ? 1 : 0,
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
        DB::table('commission_records')->whereIn('agent_id', $userIds)->orWhereIn('parent_id', $userIds)->delete();
        DB::table('user_trades')->whereIn('user_id', $userIds)->delete();
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
