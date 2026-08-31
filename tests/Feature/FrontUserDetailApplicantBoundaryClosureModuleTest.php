<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/02
 * Time: 15:09
 */

/**
 * 前端用户详情“申请人（applicant）边界”回归测试：客户账户不得读取代理用户详情。
 *
 * 文件功能：
 * - 验证 account_type=2 的客户账户（customer）访问自己的现代接口
 *   GET /api/front/users/{id} 时返回 code=PERMISSION_DENIED，不能读取代理（agent）专属详情。
 * - 验证同一客户访问旧页面 GET /show/user_detail/{id}/2 时返回 HTTP 403。
 * - 校验 docs/admin-backend-blade-permission-final-checklist.md 已记录该边界验收项（## 194.，
 *   覆盖 userDetail / legacyUserDetailPage / account_type=1）。
 *
 * 适用场景：任何改动用户详情接口、Controller 权限判断或 Blade 页面渲染的提交都应回归本文件，
 * 防止客户账户通过现代接口或旧页面越权看到代理用户详情。
 *
 * 入参：无外部参数；用例内固定构造 customerId（account_type=2）并直接以该登录身份发起请求。
 *
 * 返回值：无返回值；现代接口断言 code=PERMISSION_DENIED、旧页面断言 403 通过即表示边界闭环。
 *
 * 失败场景：断言失败意味着客户账户可读代理用户详情（现代接口或旧页面任一被放开），
 * 属于横向越权回归，必须阻断上线。
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

class FrontUserDetailApplicantBoundaryClosureModuleTest extends TestCase
{
    use DatabaseTransactions;

    public function test_customer_account_cannot_read_modern_agent_user_detail_for_self(): void
    {
        $customerId = 411940100;

        $this->insertUserInfo($customerId, 'user-detail-modern-boundary-customer', 2);

        $login = UserLogin::where('user_id', $customerId)->firstOrFail();
        $response = $this->withoutMiddleware([JwtAuthMiddleware::class, SingleSignOn::class])
            ->actingAs($login, 'user')
            ->getJson('/api/front/users/' . $customerId);

        $response->assertOk()
            ->assertJsonPath('code', ResponseCode::PERMISSION_DENIED);
    }

    public function test_customer_account_cannot_render_legacy_agent_user_detail_for_self(): void
    {
        $customerId = 411940200;

        $this->insertUserInfo($customerId, 'user-detail-legacy-boundary-customer', 2);

        $login = UserLogin::where('user_id', $customerId)->firstOrFail();
        $response = $this->withoutMiddleware([JwtAuthMiddleware::class, SingleSignOn::class])
            ->actingAs($login, 'user')
            ->get('/show/user_detail/' . $customerId . '/2');

        $response->assertStatus(403);
    }

    public function test_final_checklist_records_user_detail_applicant_boundary(): void
    {
        $checklist = file_get_contents(base_path('docs/admin-backend-blade-permission-final-checklist.md')) ?: '';

        $this->assertStringContainsString('## 194.', $checklist);
        $this->assertStringContainsString('userDetail', $checklist);
        $this->assertStringContainsString('legacyUserDetailPage', $checklist);
        $this->assertStringContainsString('account_type=1', $checklist);
        $this->assertStringContainsString('FrontUserDetailApplicantBoundaryClosureModuleTest', $checklist);
    }

    private function insertUserInfo(int $userId, string $userName, int $accountType): void
    {
        $now = time();

        DB::table('user_infos')->where('user_id', $userId)->delete();
        DB::table('user_logins')->where('user_id', $userId)->delete();

        $loginId = DB::table('user_logins')->insertGetId([
            'user_id' => $userId,
            'email' => 'front-user-detail-boundary-' . $userId . '@example.test',
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
            'phone' => '1789400' . substr((string) $userId, -4),
            'gender' => 1,
            'account_type' => $accountType,
            'parent_id' => 0,
            'family_tree' => '',
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
}
