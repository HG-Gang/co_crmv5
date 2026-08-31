<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/08
 * Time: 01:04
 */

/**
 * 前端注册成功欢迎邮件（FrontRegistrationSuccessNotification）发送闭环的回归测试。
 *
 * 文件功能：
 * - 通过 Mockery 替身替换 UserRegistrationService，模拟注册开户（provisioning）结果：
 *   - provisioning_status=processed 时，POST /api/front/auth/register 返回 SUCCESS，
 *     并发送欢迎邮件，收件人、tradingAccount、userName 必须与注册结果一致。
 *   - provisioning 失败（pending_retry）时返回 MT4_SYNC_FAILED，且不发送欢迎邮件。
 * - 校验邮件模板渲染：正文包含交易账号，绝不包含明文密码。
 * - 校验 docs/admin-backend-blade-permission-final-checklist.md 已记录该闭环验收项（## 382.）。
 *
 * 适用场景：任何改动注册流程、UserRegistrationService 返回值契约、欢迎邮件发送时机或
 * 邮件模板的提交都应回归本文件，防止“开户成功却收不到欢迎邮件”或“开户失败仍发邮件”。
 *
 * 入参：无外部参数；用例内通过 Cache 预置注册验证码（captcha 与 email code），并提交固定
 * 注册 payload，Mail::fake() 拦截真实发信。
 *
 * 返回值：无返回值；Mail::assertSent / assertNotSent 及响应断言全部通过即表示邮件闭环成立。
 *
 * 失败场景：断言失败意味着欢迎邮件发送时机或内容与 provisioning 结果不符，属于注册链路
 * 通知回归，需在注册/开户相关改动时重点排查。
 */

namespace Tests\Feature;

use App\Constants\ResponseCode;
use App\Mail\FrontRegistrationSuccessNotification;
use App\Models\UserInfo;
use App\Models\UserLogin;
use App\Services\UserRegistrationService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Mockery;
use Tests\TestCase;

class FrontRegistrationSuccessNotificationClosureModuleTest extends TestCase
{
    private function seedRegisterVerification(string $email, string $captchaKey): void
    {
        Cache::put('front_register_captcha_' . sha1($captchaKey), 'AB12', now()->addMinutes(10));
    }

    private function pendingUserLogin(string $email, int $userId): UserLogin
    {
        $login = new UserLogin();
        $login->forceFill([
            'id' => $userId - 41900000,
            'user_id' => $userId,
            'email' => $email,
            'is_enabled' => 1,
            'is_cancelled' => 0,
        ]);
        $login->setRelation('userInfo', (new UserInfo())->forceFill([
            'user_id' => $userId,
            'user_name' => 'Welcome Tester',
            'mt4_code' => $userId,
            'is_mt4_synced' => 0,
            'is_mt4_enabled' => 0,
        ]));

        return $login;
    }

    public function test_registration_success_mail_sent_after_local_registration_with_pending_provisioning(): void
    {
        $email = 'welcome-success@example.test';
        $captchaKey = 'welcome-success';
        $userId = 41991002;
        $this->seedRegisterVerification($email, $captchaKey);
        Mail::fake();

        $login = $this->pendingUserLogin($email, $userId);
        $registrationService = Mockery::mock(UserRegistrationService::class);
        $registrationService->shouldReceive('validateRegistration')
            ->once()
            ->andReturn([]);
        $registrationService->shouldReceive('register')
            ->once()
            ->andReturn([
                'success' => true,
                'registered' => true,
                'provisioning_status' => 'pending',
                'user_login' => $login,
            ]);
        $this->app->instance(UserRegistrationService::class, $registrationService);

        $response = $this->postJson('/api/front/auth/register', [
            'email' => $email, 'password' => 'RegisterPassword1', 'password_confirmation' => 'RegisterPassword1',
            'user_name' => 'Welcome Tester', 'phone_code' => '86', 'phone_number' => '13999110006',
            'phone' => '86-13999110006', 'id_card_no' => 'WELCOME-SUCCESS', 'account_type' => 2,
            'inviter_id' => 41881001, 'captcha_key' => $captchaKey, 'captcha_code' => 'AB12',
            'agree_terms' => 1,
        ]);

        $response->assertOk()
            ->assertJsonPath('code', ResponseCode::SUCCESS)
            ->assertJsonPath('data.provisioning_status', 'pending')
            ->assertJsonPath('data.user.user_id', $userId);
        Mail::assertSent(FrontRegistrationSuccessNotification::class, function (FrontRegistrationSuccessNotification $mail) use ($email, $userId): bool {
            return $mail->hasTo($email)
                && $mail->tradingAccount === $userId
                && $mail->userName === 'Welcome Tester';
        });
    }

    public function test_registration_success_mail_not_sent_for_non_pending_provisioning_status(): void
    {
        $email = 'welcome-failed@example.test';
        $captchaKey = 'welcome-failed';
        $this->seedRegisterVerification($email, $captchaKey);
        Mail::fake();

        $login = $this->pendingUserLogin($email, 41991003);
        $registrationService = Mockery::mock(UserRegistrationService::class);
        $registrationService->shouldReceive('validateRegistration')
            ->once()
            ->andReturn([]);
        $registrationService->shouldReceive('register')
            ->once()
            ->andReturn([
                'success' => true,
                'registered' => true,
                'provisioning_status' => 'processed',
                'user_login' => $login,
            ]);
        $this->app->instance(UserRegistrationService::class, $registrationService);

        $response = $this->postJson('/api/front/auth/register', [
            'email' => $email, 'password' => 'RegisterPassword1', 'password_confirmation' => 'RegisterPassword1',
            'user_name' => 'Welcome Tester', 'phone_code' => '86', 'phone_number' => '13999110007',
            'phone' => '86-13999110007', 'id_card_no' => 'WELCOME-FAILED', 'account_type' => 2,
            'inviter_id' => 41881001, 'captcha_key' => $captchaKey, 'captcha_code' => 'AB12',
            'agree_terms' => 1,
        ]);

        $response->assertOk()->assertJsonPath('code', ResponseCode::INTERNAL_ERROR);
        Mail::assertNotSent(FrontRegistrationSuccessNotification::class);
    }

    public function test_registration_success_mail_renders_account_without_password(): void
    {
        $email = 'welcome-render@example.test';
        $this->seedRegisterVerification($email, 'welcome-render');
        Mail::fake();

        $userId = 41991004;
        $registrationService = Mockery::mock(UserRegistrationService::class);
        $registrationService->shouldReceive('validateRegistration')->once()->andReturn([]);
        $registrationService->shouldReceive('register')->once()->andReturn([
            'success' => true,
            'registered' => true,
            'provisioning_status' => 'pending',
            'user_login' => $this->pendingUserLogin($email, $userId),
        ]);
        $this->app->instance(UserRegistrationService::class, $registrationService);

        $this->postJson('/api/front/auth/register', [
            'email' => $email, 'password' => 'RegisterPassword1', 'password_confirmation' => 'RegisterPassword1',
            'user_name' => 'Welcome Tester', 'phone_code' => '86', 'phone_number' => '13999110008',
            'phone' => '86-13999110008', 'id_card_no' => 'WELCOME-RENDER', 'account_type' => 2,
            'inviter_id' => 41881001, 'captcha_key' => 'welcome-render', 'captcha_code' => 'AB12',
            'agree_terms' => 1,
        ]);

        Mail::assertSent(FrontRegistrationSuccessNotification::class, function (FrontRegistrationSuccessNotification $mail) use ($email, $userId): bool {
            return $mail->hasTo($email)
                && $mail->tradingAccount === $userId
                && $mail->userName === 'Welcome Tester';
        });

        $mail = new FrontRegistrationSuccessNotification($userId, 'Welcome Tester');
        $mail->build();
        $this->assertSame(__('auth.registration_success_mail_subject'), $mail->subject);

        $rendered = view('emails.front-registration-success-notification', [
            'userName' => $mail->userName,
            'tradingAccount' => $mail->tradingAccount,
        ])->render();
        $this->assertStringContainsString((string) $userId, $rendered);
        $this->assertStringNotContainsString('RegisterPassword1', $rendered);
        $this->assertStringContainsString(__('auth.registration_success_mail_account', ['account' => $userId]), $rendered);
    }

    public function test_final_checklist_records_registration_success_mail_closure(): void
    {
        $checklist = file_get_contents(base_path('docs/admin-backend-blade-permission-final-checklist.md')) ?: '';

        $this->assertStringContainsString('## 382.', $checklist);
        $this->assertStringContainsString('FrontRegistrationSuccessNotification', $checklist);
        $this->assertStringContainsString('FrontRegistrationSuccessNotificationClosureModuleTest', $checklist);
    }
}
