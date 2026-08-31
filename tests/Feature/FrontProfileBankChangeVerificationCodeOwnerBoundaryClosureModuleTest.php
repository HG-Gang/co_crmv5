<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/02
 * Time: 01:47
 */

/**
 * 前台改银行卡验证码属主边界闭环测试。
 *
 * 文件功能：
 * - 验证现代改卡验证码发送（/api/front/profile/bank-card-change/verification-codes）
 *   将验证码写入当前用户（front_profile_change_code:{userId}）而非伪造的 user_id。
 * - 验证遗留改卡验证码发送（/user/center/changeBankCardSendCode）拒绝其他用户邮箱。
 * - 验证权限清单文档记录了该边界闭环。
 *
 * 适用场景：
 * - 前台“修改银行卡”验证码发送的越权回归测试，防止验证码落到他人缓存键。
 *
 * 入参例子：
 * - 现代参数：useremail={viewerEmail}、userphoneNo=13922600100、type=bank-change、
 *   伪造 user_id={otherId}&userId={otherId}。
 *
 * 返回值：
 * - 现代接口返回 status 为 true，缓存写入 viewerId 键（含 email/phone/type/code），
 *   其他用户缓存键为空。
 * - 遗留接口对他人邮箱返回 status 为 false，且不产生任何缓存。
 *
 * 异常或失败场景：
 * - 遗留接口提交他人邮箱时被拒绝（status=false）且无缓存写入。
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

class FrontProfileBankChangeVerificationCodeOwnerBoundaryClosureModuleTest extends TestCase
{
    use DatabaseTransactions;

    // 验证现代改卡验证码写入当前用户缓存而非伪造的 user_id。
    public function test_modern_bank_change_code_send_writes_cache_for_current_user_not_spoofed_user_id(): void
    {
        $viewerId = 412260100;
        $otherId = 412260101;
        $viewerEmail = 'front-bank-change-code-boundary-' . $viewerId . '@example.test';
        $otherEmail = 'front-bank-change-code-boundary-' . $otherId . '@example.test';

        $this->deleteFixtureRows([$viewerId, $otherId], [$viewerEmail, $otherEmail]);
        $this->insertUserInfo($viewerId, 'bank-change-code-boundary-viewer', $viewerEmail);
        $this->insertUserInfo($otherId, 'bank-change-code-boundary-other', $otherEmail);
        Cache::forget('front_profile_change_code:' . $viewerId);
        Cache::forget('front_profile_change_code:' . $otherId);
        Mail::fake();

        $login = UserLogin::where('user_id', $viewerId)->firstOrFail();
        $response = $this->withoutMiddleware([JwtAuthMiddleware::class, SingleSignOn::class])
            ->actingAs($login, 'user')
            ->postJson('/api/front/profile/bank-card-change/verification-codes', [
                'useremail' => $viewerEmail,
                'userphoneNo' => '13922600100',
                'type' => 'bank-change',
                'user_id' => $otherId,
                'userId' => $otherId,
            ]);

        $response->assertOk()
            ->assertJsonPath('status', true);

        $cached = Cache::get('front_profile_change_code:' . $viewerId);
        $this->assertIsArray($cached);
        $this->assertSame($viewerEmail, $cached['email'] ?? '');
        $this->assertSame('13922600100', $cached['phone'] ?? '');
        $this->assertSame('bank-change', $cached['type'] ?? '');
        $this->assertArrayHasKey('code', $cached);
        $this->assertNull(Cache::get('front_profile_change_code:' . $otherId));
    }

    // 验证遗留改卡验证码发送拒绝伪造的其他用户邮箱。
    public function test_legacy_bank_change_code_send_rejects_spoofed_other_user_email(): void
    {
        $viewerId = 412260200;
        $otherId = 412260201;
        $viewerEmail = 'front-bank-change-code-boundary-' . $viewerId . '@example.test';
        $otherEmail = 'front-bank-change-code-boundary-' . $otherId . '@example.test';

        $this->deleteFixtureRows([$viewerId, $otherId], [$viewerEmail, $otherEmail]);
        $this->insertUserInfo($viewerId, 'bank-change-code-legacy-boundary-viewer', $viewerEmail);
        $this->insertUserInfo($otherId, 'bank-change-code-legacy-boundary-other', $otherEmail);
        Cache::forget('front_profile_change_code:' . $viewerId);
        Cache::forget('front_profile_change_code:' . $otherId);
        Mail::fake();

        $login = UserLogin::where('user_id', $viewerId)->firstOrFail();
        $response = $this->withoutMiddleware([JwtAuthMiddleware::class, SingleSignOn::class])
            ->actingAs($login, 'user')
            ->postJson('/user/center/changeBankCardSendCode', [
                'useremail' => $otherEmail,
                'userphoneNo' => '13922600201',
                'type' => 'bank-change',
                'user_id' => $otherId,
                'userId' => $otherId,
            ]);

        $response->assertOk()
            ->assertJsonPath('status', false);

        $this->assertNull(Cache::get('front_profile_change_code:' . $viewerId));
        $this->assertNull(Cache::get('front_profile_change_code:' . $otherId));
    }

    // 校验权限清单文档记录了改卡验证码属主边界闭环。
    public function test_final_checklist_records_bank_change_verification_code_owner_boundary(): void
    {
        $checklist = file_get_contents(base_path('docs/admin-backend-blade-permission-final-checklist.md')) ?: '';

        $this->assertStringContainsString('## 226.', $checklist);
        $this->assertStringContainsString('ProfileController::changeBankCardSendCode', $checklist);
        $this->assertStringContainsString('/api/front/profile/bank-card-change/verification-codes', $checklist);
        $this->assertStringContainsString('user/center/changeBankCardSendCode', $checklist);
        $this->assertStringContainsString('front_profile_change_code', $checklist);
        $this->assertStringContainsString('FrontProfileBankChangeVerificationCodeOwnerBoundaryClosureModuleTest', $checklist);
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
            'phone' => '1392260' . substr((string) $userId, -4),
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
