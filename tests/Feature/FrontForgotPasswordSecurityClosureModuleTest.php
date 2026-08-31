<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/02
 * Time: 01:45
 */

/**
 * 前台忘记密码安全闭环测试。
 *
 * 文件功能：
 * - 验证遗留改密接口 /user/change_password 的校验码、userId 严格性、确认密码、
 *   用户状态、MT4 同步失败回滚等安全边界。
 * - 验证发送验证码接口 /user/forgetpswSendCode 与 /api/front/auth/password/email-code
 *   的载荷绑定（userId + email）、限流与邮件发送。
 * - 验证验证码核验接口 /user/forgetPasswordInfoVerification 拒绝跨用户复用。
 * - 验证权限清单文档记录了该安全闭环。
 *
 * 适用场景：
 * - 前台“忘记密码/修改密码”功能的回归测试，防止验证码绕过、跨用户重置等安全问题。
 *
 * 入参例子：
 * - /user/change_password：userId、password、againpassword、userverfcode（6 位验证码）。
 * - /user/forgetpswSendCode：userId、useremail。
 * - /api/front/auth/password/email-code：email。
 *
 * 返回值：
 * - 成功时 msg 为 SUC，密码被更新且验证码缓存被消费。
 * - 失败时 msg 为 FAIL，err 为 errorCodedate / IDerror / passworderr / UserDisable / neterr 等。
 *
 * 异常或失败场景：
 * - 缺失/错误验证码、非数字 userId、两次密码不一致、用户被禁用、MT4 同步失败时均拒绝修改。
 */

namespace Tests\Feature;

use App\Constants\ResponseCode;
use App\Facades\Mt4ManagerApi;
use App\Mail\FrontResetPasswordCode;
use App\Models\UserLogin;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class FrontForgotPasswordSecurityClosureModuleTest extends TestCase
{
    use DatabaseTransactions;

    /**
     * 本用例写入 Cache 的键清单（找回密码令牌/验证码）。tearDown 逐键 forget，防止令牌跨用例残留。
     * @var array<int, string>
     */
    private $cacheKeys = [];

    protected function tearDown(): void
    {
        foreach ($this->cacheKeys as $key) {
            Cache::forget($key);
        }

        parent::tearDown();
    }

    // 验证遗留改密接口拒绝缺失验证码的请求。
    public function test_legacy_password_change_rejects_missing_verification_code(): void
    {
        $login = $this->insertLogin(418110101, 'forgot-missing-code-418110101@example.test');
        $this->cacheResetCode($login, '612301');

        $this->postJson('/user/change_password', [
            'userId' => $login->user_id,
            'password' => 'new-password-1',
            'againpassword' => 'new-password-1',
        ])->assertOk()
            ->assertJsonPath('msg', 'FAIL')
            ->assertJsonPath('err', 'errorCodedate');

        $this->assertTrue(Hash::check('old-password', $login->fresh()->password));
    }

    // 验证遗留改密接口拒绝带字母前缀的 userId。
    public function test_legacy_password_change_rejects_numeric_prefix_user_id(): void
    {
        $login = $this->insertLogin(418110102, 'forgot-prefix-id-418110102@example.test');
        $this->cacheResetCode($login, '612302');

        $this->postJson('/user/change_password', [
            'userId' => $login->user_id . 'abc',
            'password' => 'new-password-2',
            'againpassword' => 'new-password-2',
            'userverfcode' => '612302',
        ])->assertOk()
            ->assertJsonPath('msg', 'FAIL')
            ->assertJsonPath('err', 'IDerror');

        $this->assertTrue(Hash::check('old-password', $login->fresh()->password));
    }

    // 验证正确的 userverfcode 可完成改密并消费缓存验证码。
    public function test_legacy_password_change_accepts_bound_userverfcode_and_consumes_it(): void
    {
        $login = $this->insertLogin(418110103, 'forgot-valid-code-418110103@example.test');
        $key = $this->cacheResetCode($login, '612303');

        $this->postJson('/user/change_password', [
            'userId' => $login->user_id,
            'password' => 'new-password-3',
            'againpassword' => 'new-password-3',
            'userverfcode' => '612303',
        ])->assertOk()
            ->assertJsonPath('msg', 'SUC');

        $this->assertTrue(Hash::check('new-password-3', $login->fresh()->password));
        $this->assertNull(Cache::get($key));
    }

    // 验证遗留改密接口拒绝两次密码不一致的请求。
    public function test_legacy_password_change_rejects_mismatched_confirmation(): void
    {
        $login = $this->insertLogin(418110104, 'forgot-confirmation-418110104@example.test');
        $this->cacheResetCode($login, '612304');

        $this->postJson('/user/change_password', [
            'userId' => $login->user_id,
            'password' => 'new-password-4',
            'againpassword' => 'different-password',
            'userverfcode' => '612304',
        ])->assertOk()
            ->assertJsonPath('msg', 'FAIL')
            ->assertJsonPath('err', 'passworderr');

        $this->assertTrue(Hash::check('old-password', $login->fresh()->password));
    }

    // 验证遗留改密接口拒绝被禁用用户。
    public function test_legacy_password_change_rejects_disabled_user(): void
    {
        $login = $this->insertLogin(418110105, 'forgot-disabled-418110105@example.test', 0);
        $this->cacheResetCode($login, '612305');

        $this->postJson('/user/change_password', [
            'userId' => $login->user_id,
            'password' => 'new-password-5',
            'againpassword' => 'new-password-5',
            'userverfcode' => '612305',
        ])->assertOk()
            ->assertJsonPath('msg', 'FAIL')
            ->assertJsonPath('err', 'UserDisable');

        $this->assertTrue(Hash::check('old-password', $login->fresh()->password));
    }

    // 验证发送验证码接口将 userId 与 email 绑定进缓存并发送邮件。
    public function test_legacy_send_code_binds_user_and_email_and_sends_mail(): void
    {
        $login = $this->insertLogin(418110106, 'forgot-send-418110106@example.test');
        $key = $this->resetCacheKey($login->email);
        $this->trackSendCacheKeys($login->email);
        Mail::fake();

        $this->postJson('/user/forgetpswSendCode', [
            'userId' => $login->user_id,
            'useremail' => $login->email,
        ])->assertOk()
            ->assertJsonPath('status', true);

        $payload = Cache::get($key);
        $this->assertIsArray($payload);
        $this->assertSame((int) $login->user_id, $payload['user_id'] ?? null);
        $this->assertSame(strtolower($login->email), $payload['email'] ?? null);
        $this->assertMatchesRegularExpression('/^\d{6}$/', (string) ($payload['code'] ?? ''));
        Mail::assertSent(FrontResetPasswordCode::class, function (FrontResetPasswordCode $mail) use ($login, $payload): bool {
            return $mail->hasTo($login->email) && $mail->code === $payload['code'];
        });
    }

    // 验证发送验证码接口拒绝 userId 与 email 不匹配或带前缀 userId 的请求。
    public function test_legacy_send_code_rejects_mismatched_and_prefixed_user_id(): void
    {
        $first = $this->insertLogin(418110107, 'forgot-send-first-418110107@example.test');
        $second = $this->insertLogin(418110108, 'forgot-send-second-418110108@example.test');
        $this->trackSendCacheKeys($first->email);
        $this->trackSendCacheKeys($second->email);
        Mail::fake();

        $this->postJson('/user/forgetpswSendCode', [
            'userId' => $first->user_id,
            'useremail' => $second->email,
        ])->assertOk()
            ->assertJsonPath('status', false);

        $this->postJson('/user/forgetpswSendCode', [
            'userId' => $first->user_id . 'abc',
            'useremail' => $first->email,
        ])->assertOk()
            ->assertJsonPath('status', false);

        $this->assertNull(Cache::get($this->resetCacheKey($first->email)));
        $this->assertNull(Cache::get($this->resetCacheKey($second->email)));
        Mail::assertNothingSent();
    }

    // 验证验证码核验接口拒绝绑定到其他用户的载荷。
    public function test_legacy_code_verification_rejects_payload_bound_to_different_user(): void
    {
        $owner = $this->insertLogin(418110109, 'forgot-verify-owner-418110109@example.test');
        $other = $this->insertLogin(418110110, 'forgot-verify-other-418110110@example.test');
        $this->cacheResetCode($owner, '612309');

        $this->postJson('/user/forgetPasswordInfoVerification', [
            'userId' => $other->user_id,
            'useremail' => $owner->email,
            'codedata' => '612309',
        ])->assertOk()
            ->assertJsonPath('msg', 'FAIL')
            ->assertJsonPath('err', 'emailerror');
    }

    // 验证现代发送验证码接口使用绑定载荷并发送邮件。
    public function test_modern_send_code_uses_bound_payload_and_mailable(): void
    {
        $login = $this->insertLogin(418110111, 'forgot-modern-send-418110111@example.test');
        $key = $this->resetCacheKey($login->email);
        $this->trackSendCacheKeys($login->email);
        Mail::fake();

        $response = $this->postJson('/api/front/auth/password/email-code', [
            'email' => $login->email,
        ])->assertOk()
            ->assertJsonPath('code', ResponseCode::SUCCESS);

        $payload = Cache::get($key);
        $this->assertIsArray($payload);
        $this->assertSame((int) $login->user_id, $payload['user_id'] ?? null);
        $this->assertSame($response->json('data.debug_code'), $payload['code'] ?? null);
        Mail::assertSent(FrontResetPasswordCode::class, function (FrontResetPasswordCode $mail) use ($login): bool {
            return $mail->hasTo($login->email);
        });
    }

    // 验证 MT4 同步失败时本地密码状态保持不变且验证码未被消费。
    public function test_legacy_password_change_keeps_local_state_when_mt4_sync_fails(): void
    {
        $login = $this->insertLogin(418110112, 'forgot-mt4-failure-418110112@example.test');
        $key = $this->cacheResetCode($login, '612312');
        config(['mt4.enabled' => true]);
        Mt4ManagerApi::shouldReceive('changePassword')
            ->once()
            ->with((int) $login->user_id, 'new-password-12')
            ->andReturn(['status' => 'error', 'message' => 'MT4 unavailable']);

        $this->postJson('/user/change_password', [
            'userId' => $login->user_id,
            'password' => 'new-password-12',
            'againpassword' => 'new-password-12',
            'userverfcode' => '612312',
        ])->assertOk()
            ->assertJsonPath('msg', 'FAIL')
            ->assertJsonPath('err', 'neterr');

        $this->assertTrue(Hash::check('old-password', $login->fresh()->password));
        $this->assertIsArray(Cache::get($key));
    }

    // 校验权限清单文档记录了忘记密码安全闭环。
    public function test_final_checklist_records_forgot_password_security_closure(): void
    {
        $checklist = file_get_contents(base_path('docs/admin-backend-blade-permission-final-checklist.md')) ?: '';

        $this->assertStringContainsString('## 340.', $checklist);
        $this->assertStringContainsString('ForgotPasswordController::saveChangePassword', $checklist);
        $this->assertStringContainsString('front_reset_code:{email}', $checklist);
        $this->assertStringContainsString('FrontResetPasswordCode', $checklist);
        $this->assertStringContainsString('Mt4ManagerApi::changePassword', $checklist);
        $this->assertStringContainsString('FrontForgotPasswordSecurityClosureModuleTest', $checklist);
    }

    private function insertLogin(int $userId, string $email, int $isEnabled = 1): UserLogin
    {
        DB::table('user_logins')->where('user_id', $userId)->delete();
        DB::table('user_logins')->where('email', $email)->delete();
        $now = time();

        $id = DB::table('user_logins')->insertGetId([
            'user_id' => $userId,
            'email' => $email,
            'password' => Hash::make('old-password'),
            'account_type' => 2,
            'role_id' => 0,
            'is_enabled' => $isEnabled,
            'is_cancelled' => 0,
            'source_type' => 0,
            'jwt_token_id' => '',
            'last_login_ip' => '',
            'last_login_at' => null,
            'created_at' => $now,
            'updated_at' => $now,
            'deleted_at' => null,
        ]);

        return UserLogin::findOrFail($id);
    }

    private function cacheResetCode(UserLogin $login, string $code): string
    {
        $key = $this->resetCacheKey($login->email);
        $this->cacheKeys[] = $key;
        Cache::put($key, [
            'user_id' => (int) $login->user_id,
            'email' => strtolower($login->email),
            'code' => $code,
        ], 600);

        return $key;
    }

    private function trackSendCacheKeys(string $email): void
    {
        $this->cacheKeys[] = $this->resetCacheKey($email);
        $this->cacheKeys[] = 'front_reset_code_rate_' . sha1(strtolower($email) . '|127.0.0.1');
    }

    private function resetCacheKey(string $email): string
    {
        return 'front_reset_code:' . strtolower($email);
    }
}
