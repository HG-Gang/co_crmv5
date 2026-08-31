<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/02
 * Time: 15:09
 */

/**
 * 前端用户登录历史“申请人（applicant）边界”回归测试：客户账户不得读取登录历史。
 *
 * 文件功能：
 * - 验证 account_type=2 的客户账户（customer）访问现代接口
 *   GET /api/front/users/login-history?user_id={self} 时返回 code=PERMISSION_DENIED。
 * - 验证同一客户调用旧接口 POST /user/cust/loginHistorySearch/{uid} 时返回
 *   total=0 且 rows=[]，空结果不泄露任何登录日志。
 * - 校验 docs/admin-backend-blade-permission-final-checklist.md 已记录该边界验收项（## 193.，
 *   覆盖 userLoginHistory / legacyLoginHistorySearch / account_type=1）。
 *
 * 适用场景：任何改动登录历史查询接口、权限判断或旧接口数据源映射的提交都应回归本文件，
 * 防止客户账户越权查看他人登录记录。
 *
 * 入参：无外部参数；用例内固定构造 customerId（account_type=2）并预置其登录日志
 * （user_login_logs），再以该登录身份发起请求。
 *
 * 返回值：无返回值；现代接口断言 PERMISSION_DENIED、旧接口断言空列表通过即表示边界闭环。
 *
 * 失败场景：断言失败意味着客户账户可读登录历史（现代接口放行或旧接口泄露行数据），
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

class FrontUserLoginHistoryApplicantBoundaryClosureModuleTest extends TestCase
{
    use DatabaseTransactions;

    public function test_customer_account_cannot_read_modern_user_login_history(): void
    {
        $customerId = 411930100;

        $this->insertUserWithLoginLog($customerId, 'login-history-modern-boundary-customer', 2);

        $login = UserLogin::where('user_id', $customerId)->firstOrFail();
        $response = $this->withoutMiddleware([JwtAuthMiddleware::class, SingleSignOn::class])
            ->actingAs($login, 'user')
            ->getJson('/api/front/users/login-history?user_id=' . $customerId);

        $response->assertOk()
            ->assertJsonPath('code', ResponseCode::PERMISSION_DENIED);
    }

    public function test_customer_account_cannot_read_legacy_user_login_history_table(): void
    {
        $customerId = 411930200;

        $this->insertUserWithLoginLog($customerId, 'login-history-legacy-boundary-customer', 2);

        $login = UserLogin::where('user_id', $customerId)->firstOrFail();
        $response = $this->withoutMiddleware([JwtAuthMiddleware::class, SingleSignOn::class])
            ->actingAs($login, 'user')
            ->postJson('/user/cust/loginHistorySearch/' . $customerId);

        $response->assertOk()
            ->assertJsonPath('total', 0)
            ->assertJsonPath('rows', []);
    }

    public function test_final_checklist_records_user_login_history_applicant_boundary(): void
    {
        $checklist = file_get_contents(base_path('docs/admin-backend-blade-permission-final-checklist.md')) ?: '';

        $this->assertStringContainsString('## 193.', $checklist);
        $this->assertStringContainsString('userLoginHistory', $checklist);
        $this->assertStringContainsString('legacyLoginHistorySearch', $checklist);
        $this->assertStringContainsString('account_type=1', $checklist);
        $this->assertStringContainsString('FrontUserLoginHistoryApplicantBoundaryClosureModuleTest', $checklist);
    }

    private function insertUserWithLoginLog(int $userId, string $userName, int $accountType): void
    {
        $now = time();

        DB::table('user_login_logs')->where('user_id', $userId)->delete();
        DB::table('user_infos')->where('user_id', $userId)->delete();
        DB::table('user_logins')->where('user_id', $userId)->delete();

        $loginId = DB::table('user_logins')->insertGetId([
            'user_id' => $userId,
            'email' => 'front-user-login-history-boundary-' . $userId . '@example.test',
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
            'phone' => '1789300' . substr((string) $userId, -4),
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

        DB::table('user_login_logs')->insert([
            'login_id' => $loginId,
            'user_id' => $userId,
            'login_ip' => '203.0.113.' . substr((string) $userId, -2),
            'ip_location' => 'boundary-test',
            'user_agent' => 'front-login-history-boundary-test',
            'created_at' => $now,
            'updated_at' => $now,
            'deleted_at' => null,
        ]);
    }
}
