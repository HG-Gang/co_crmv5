<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/02
 * Time: 01:48
 */

/**
 * 前台关系路径申请人边界闭环测试。
 *
 * 文件功能：
 * - 验证普通客户（account_type=2）不能读取与自己无代理关系的用户的关系路径，
 *   包括现代接口 /api/front/profile/relationship-path 与遗留接口 /user/relationShipHtml。
 * - 验证权限清单文档记录了该边界闭环。
 *
 * 适用场景：
 * - 前台个人资料“关系路径”功能的回归测试，防止普通客户越权查看他人代理关系。
 *
 * 入参例子：
 * - 登录账号：普通客户（account_type=2，无任何下级）。
 * - 请求参数：userId={targetCustomerId}（属于其他根代理的客户）。
 *
 * 返回值：
 * - 接口返回 HTTP 200，real 为空字符串，响应体不含根代理与目标客户 ID。
 *
 * 异常或失败场景：
 * - 普通客户查询无关用户的关系路径时返回空结果，不泄露任何关系信息。
 */

namespace Tests\Feature;

use App\Http\Middleware\JwtAuthMiddleware;
use App\Http\Middleware\SingleSignOn;
use App\Models\UserLogin;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class FrontProfileRelationshipApplicantBoundaryClosureModuleTest extends TestCase
{
    use DatabaseTransactions;

    // 验证普通客户不能通过现代接口读取无关用户的关系路径。
    public function test_customer_account_cannot_read_modern_unrelated_profile_relationship_path(): void
    {
        $viewerId = 412160100;
        $rootAgentId = 412160101;
        $targetCustomerId = 412160102;

        $this->deleteFixtureRows([$viewerId, $rootAgentId, $targetCustomerId]);
        $this->insertUserInfo($viewerId, 'relationship-boundary-viewer', 2, 0);
        $this->insertUserInfo($rootAgentId, 'relationship-boundary-root-agent', 1, 0);
        $this->insertUserInfo($targetCustomerId, 'relationship-boundary-target', 2, $rootAgentId);

        $login = UserLogin::where('user_id', $viewerId)->firstOrFail();
        $response = $this->withoutMiddleware([JwtAuthMiddleware::class, SingleSignOn::class])
            ->actingAs($login, 'user')
            ->getJson('/api/front/profile/relationship-path?userId=' . $targetCustomerId);

        $response->assertOk()
            ->assertJsonPath('real', '');
        $this->assertStringNotContainsString((string) $rootAgentId, $response->getContent());
        $this->assertStringNotContainsString((string) $targetCustomerId, $response->getContent());
    }

    // 验证普通客户不能通过遗留接口读取无关用户的关系路径 HTML。
    public function test_customer_account_cannot_read_legacy_unrelated_profile_relationship_html(): void
    {
        $viewerId = 412160200;
        $rootAgentId = 412160201;
        $targetCustomerId = 412160202;

        $this->deleteFixtureRows([$viewerId, $rootAgentId, $targetCustomerId]);
        $this->insertUserInfo($viewerId, 'relationship-legacy-boundary-viewer', 2, 0);
        $this->insertUserInfo($rootAgentId, 'relationship-legacy-boundary-root-agent', 1, 0);
        $this->insertUserInfo($targetCustomerId, 'relationship-legacy-boundary-target', 2, $rootAgentId);

        $login = UserLogin::where('user_id', $viewerId)->firstOrFail();
        $response = $this->withoutMiddleware([JwtAuthMiddleware::class, SingleSignOn::class])
            ->actingAs($login, 'user')
            ->postJson('/user/relationShipHtml', [
                'userId' => $targetCustomerId,
            ]);

        $response->assertOk()
            ->assertJsonPath('real', '');
        $this->assertStringNotContainsString((string) $rootAgentId, $response->getContent());
        $this->assertStringNotContainsString((string) $targetCustomerId, $response->getContent());
    }

    // 校验权限清单文档记录了关系路径申请人边界闭环。
    public function test_final_checklist_records_profile_relationship_applicant_boundary(): void
    {
        $checklist = file_get_contents(base_path('docs/admin-backend-blade-permission-final-checklist.md')) ?: '';

        $this->assertStringContainsString('## 216.', $checklist);
        $this->assertStringContainsString('ProfileController::relationshipText', $checklist);
        $this->assertStringContainsString('/api/front/profile/relationship-path', $checklist);
        $this->assertStringContainsString('user/relationShipHtml', $checklist);
        $this->assertStringContainsString('account_type=1', $checklist);
        $this->assertStringContainsString('FrontProfileRelationshipApplicantBoundaryClosureModuleTest', $checklist);
    }

    private function insertUserInfo(int $userId, string $userName, int $accountType, int $parentId): void
    {
        $now = time();

        DB::table('user_infos')->where('user_id', $userId)->delete();
        DB::table('user_logins')->where('user_id', $userId)->delete();

        $loginId = DB::table('user_logins')->insertGetId([
            'user_id' => $userId,
            'email' => 'front-relationship-boundary-' . $userId . '@example.test',
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
            'phone' => '1782160' . substr((string) $userId, -4),
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
