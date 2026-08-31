<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/02
 * Time: 15:09
 */

/**
 * 前端个人资料修改验证码的“拥有者边界”回归测试。
 *
 * 文件功能：
 * - 验证现代接口 POST /api/front/profile/verification-password/verification-codes：
 *   即使请求体伪造 user_id/userId 指向他人，验证码 Cache 键 front_profile_updverify_code:{id}
 *   也只能写入当前登录用户（viewerId）名下，伪造目标不产生任何 Cache。
 * - 验证旧接口 POST /user/center/updVerifyPassSendCode：请求体使用他人邮箱时返回
 *   status=false，且 viewerId/otherId 两个验证码 Cache 均为空，拒绝伪造邮箱发码。
 * - 校验 docs/admin-backend-blade-permission-final-checklist.md 已记录该边界验收项（## 225.）。
 *
 * 适用场景：任何改动验证码发送、Cache 键、用户身份解析或 ProfileController::updVerifyPassSendCode
 * 的提交都应回归本文件，防止验证码落到非登录用户名下造成越权发码。
 *
 * 入参：无外部参数；用例内固定构造 viewerId/otherId 两个用户（account_type=2）及对应邮箱，
 * 并 Mail::fake() 拦截真实邮件发送。
 *
 * 返回值：无返回值；所有断言通过即表示“验证码按登录身份写入、伪造身份被拒绝”闭环成立。
 *
 * 失败场景：断言失败意味着验证码未绑定当前登录用户（越权写入他人 Cache）或旧接口接受了
 * 伪造邮箱，属于安全边界回归，必须阻断上线。
 */

namespace Tests\Feature;

use App\Http\Middleware\JwtAuthMiddleware;
use App\Http\Middleware\SingleSignOn;
use App\Models\UserLogin;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class FrontProfileUpdateVerificationCodeOwnerBoundaryClosureModuleTest extends TestCase
{
    use DatabaseTransactions;

    public function test_modern_profile_update_code_send_writes_cache_for_current_user_not_spoofed_user_id(): void
    {
        $viewerId = 412250100;
        $otherId = 412250101;
        $viewerEmail = 'front-profile-code-boundary-' . $viewerId . '@example.test';
        $otherEmail = 'front-profile-code-boundary-' . $otherId . '@example.test';

        $this->deleteFixtureRows([$viewerId, $otherId], [$viewerEmail, $otherEmail]);
        $this->insertUserInfo($viewerId, 'profile-code-boundary-viewer', $viewerEmail);
        $this->insertUserInfo($otherId, 'profile-code-boundary-other', $otherEmail);
        Cache::forget('front_profile_updverify_code:' . $viewerId);
        Cache::forget('front_profile_updverify_code:' . $otherId);
        Mail::fake();

        $login = UserLogin::where('user_id', $viewerId)->firstOrFail();
        $response = $this->withoutMiddleware([JwtAuthMiddleware::class, SingleSignOn::class])
            ->actingAs($login, 'user')
            ->postJson('/api/front/profile/verification-password/verification-codes', [
                'useremail' => $viewerEmail,
                'userphoneNo' => '13922500100',
                'type' => 'email',
                'user_id' => $otherId,
                'userId' => $otherId,
            ]);

        $response->assertOk()
            ->assertJsonPath('status', true);

        $cached = Cache::get('front_profile_updverify_code:' . $viewerId);
        $this->assertIsArray($cached);
        $this->assertSame($viewerEmail, $cached['email'] ?? '');
        $this->assertSame('13922500100', $cached['phone'] ?? '');
        $this->assertSame('email', $cached['type'] ?? '');
        $this->assertArrayHasKey('code', $cached);
        $this->assertNull(Cache::get('front_profile_updverify_code:' . $otherId));
    }

    public function test_legacy_profile_update_code_send_rejects_spoofed_other_user_email(): void
    {
        $viewerId = 412250200;
        $otherId = 412250201;
        $viewerEmail = 'front-profile-code-boundary-' . $viewerId . '@example.test';
        $otherEmail = 'front-profile-code-boundary-' . $otherId . '@example.test';

        $this->deleteFixtureRows([$viewerId, $otherId], [$viewerEmail, $otherEmail]);
        $this->insertUserInfo($viewerId, 'profile-code-legacy-boundary-viewer', $viewerEmail);
        $this->insertUserInfo($otherId, 'profile-code-legacy-boundary-other', $otherEmail);
        Cache::forget('front_profile_updverify_code:' . $viewerId);
        Cache::forget('front_profile_updverify_code:' . $otherId);
        Mail::fake();

        $login = UserLogin::where('user_id', $viewerId)->firstOrFail();
        $response = $this->withoutMiddleware([JwtAuthMiddleware::class, SingleSignOn::class])
            ->actingAs($login, 'user')
            ->postJson('/user/center/updVerifyPassSendCode', [
                'useremail' => $otherEmail,
                'userphoneNo' => '13922500201',
                'type' => 'phone',
                'user_id' => $otherId,
                'userId' => $otherId,
            ]);

        $response->assertOk()
            ->assertJsonPath('status', false);

        $this->assertNull(Cache::get('front_profile_updverify_code:' . $viewerId));
        $this->assertNull(Cache::get('front_profile_updverify_code:' . $otherId));
    }

    public function test_final_checklist_records_profile_update_verification_code_owner_boundary(): void
    {
        $checklist = file_get_contents(base_path('docs/admin-backend-blade-permission-final-checklist.md')) ?: '';

        $this->assertStringContainsString('## 225.', $checklist);
        $this->assertStringContainsString('ProfileController::updVerifyPassSendCode', $checklist);
        $this->assertStringContainsString('/api/front/profile/verification-password/verification-codes', $checklist);
        $this->assertStringContainsString('user/center/updVerifyPassSendCode', $checklist);
        $this->assertStringContainsString('front_profile_updverify_code', $checklist);
        $this->assertStringContainsString('FrontProfileUpdateVerificationCodeOwnerBoundaryClosureModuleTest', $checklist);
    }

    private function insertUserInfo(int $userId, string $userName, string $email): void
    {
        $now = time();

        $loginId = DB::table('user_logins')->insertGetId([
            'user_id' => $userId,
            'email' => $email,
            'password' => Hash::make('password'),
            'account_type' => 2,
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
            'phone' => '1392250' . substr((string) $userId, -4),
            'gender' => 1,
            'account_type' => 2,
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

    /**
     * @param array<int, int> $userIds
     * @param array<int, string> $emails
     */
    private function deleteFixtureRows(array $userIds, array $emails): void
    {
        DB::table('user_infos')->whereIn('user_id', $userIds)->delete();
        DB::table('user_logins')->whereIn('user_id', $userIds)->delete();
        DB::table('user_logins')->whereIn('email', $emails)->delete();
    }
}
