<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/08
 * Time: 01:01
 */

/**
 * 前台遗留注册载荷兼容性闭环测试。
 *
 * 文件功能：
 * - 验证现代注册接口 /api/front/auth/register 不启用遗留字段默认值、拒绝遗留字段别名
 *   （useremail、username、modules、userphoneNo、userIdcardNo、reguserverfcode、userverfcode），
 *   且不转发遗留 comm_type。
 * - 验证遗留注册接口 /user/register/registerinto 的载荷正确映射到现代注册契约
 *   （邀请人、性别、区号、电话、验证码、同意条款、佣金模式）。
 * - 验证遗留发送验证码接口 /user/register/registerSendCode 映射账号类型与联系方式字段。
 * - 验证权限清单文档记录了该兼容性闭环。
 *
 * 适用场景：
 * - 前台注册模块的兼容性回归测试，防止遗留与现代载荷互相污染。
 *
 * 入参例子：
 * - POST /api/front/auth/register：email、password、password_confirmation、user_name、
 *   phone_code、phone_number、id_card_no、captcha_key、captcha_code、agree_terms。
 * - POST /user/register/registerinto：register_type、userInviterId、comm_type、username、
 *   sex、userIdcardNo、modules、userphoneNo、useremail、reguserverfcode、userverfcode 等。
 *
 * 返回值：
 * - 现代接口对遗留别名返回 VALIDATION_ERROR；遗留接口正确映射后 code 为 SUCCESS。
 *
 * 异常或失败场景：
 * - 现代接口收到遗留别名/comm_type 时校验失败；遗留接口映射缺失字段时注册失败。
 */

namespace Tests\Feature;

use App\Constants\ResponseCode;
use App\Mail\FrontRegistrationVerificationCode;
use App\Models\UserInfo;
use App\Models\UserLogin;
use App\Services\UserRegistrationService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Mockery;
use Tests\TestCase;

class FrontLegacyRegisterPayloadCompatibilityClosureModuleTest extends TestCase
{
    // 验证现代注册接口不启用遗留字段默认值。
    public function test_modern_register_route_does_not_enable_legacy_field_defaults(): void
    {
        $registrationService = Mockery::mock(UserRegistrationService::class);
        $registrationService->shouldNotReceive('validateRegistration');
        $registrationService->shouldNotReceive('register');
        $this->app->instance(UserRegistrationService::class, $registrationService);

        $response = $this->postJson('/api/front/auth/register', [
            'email' => 'modern-mixed@example.test', 'password' => 'RegisterPassword1',
            'user_name' => 'Modern', 'phone_code' => '86', 'phone_number' => '13999110003',
            'phone' => '86-13999110003', 'id_card_no' => 'MODERN-MIXED', 'account_type' => 2,
            'inviter_id' => '123A', 'captcha_key' => 'unused', 'captcha_code' => 'AB12',
            'agreeRule' => 1, 'sex' => 2,
        ]);

        $response->assertOk()->assertJsonPath('code', ResponseCode::VALIDATION_ERROR);
        $this->assertStringContainsString('password confirmation', strtolower((string) $response->json('message')));
    }

    /** @dataProvider modernLegacyAliasProvider */
    // 验证现代注册接口拒绝遗留字段别名（数据提供器驱动）。
    public function test_modern_register_route_rejects_legacy_alias(string $modernField, string $legacyField, string $legacyValue): void
    {
        $payload = [
            'email' => 'modern-alias@example.test', 'password' => 'RegisterPassword1',
            'password_confirmation' => 'RegisterPassword1', 'user_name' => 'Modern Alias',
            'phone_code' => '86', 'phone_number' => '13999110004', 'phone' => '86-13999110004',
            'id_card_no' => 'MODERN-ALIAS', 'account_type' => 2, 'captcha_key' => 'unused',
            'captcha_code' => 'AB12', 'agree_terms' => 1,
        ];
        unset($payload[$modernField]);
        $payload[$legacyField] = $legacyValue;

        $registrationService = Mockery::mock(UserRegistrationService::class);
        $registrationService->shouldNotReceive('validateRegistration');
        $registrationService->shouldNotReceive('register');
        $this->app->instance(UserRegistrationService::class, $registrationService);

        $response = $this->postJson('/api/front/auth/register', $payload);
        $response->assertOk()->assertJsonPath('code', ResponseCode::VALIDATION_ERROR);
        $this->assertStringContainsString(str_replace('_', ' ', $modernField), strtolower((string) $response->json('message')));
    }

    // 数据提供器：现代字段与遗留别名字段的映射用例。
    public function modernLegacyAliasProvider(): array
    {
        return [
            ['email', 'useremail', 'legacy@example.test'], ['user_name', 'username', 'Legacy'],
            ['phone_code', 'modules', '86'], ['phone_number', 'userphoneNo', '13999110004'],
            ['id_card_no', 'userIdcardNo', 'LEGACY-ID'], ['captcha_code', 'reguserverfcode', 'AB12'],
        ];
    }

    // 验证现代注册接口不转发遗留 comm_type 佣金模式。
    public function test_modern_register_route_does_not_forward_legacy_comm_type(): void
    {
        $email = 'modern-comm-type@example.test';
        $captchaKey = 'modern-comm-type';
        Cache::put('front_register_captcha_' . sha1($captchaKey), 'AB12', now()->addMinutes(10));
        $registrationService = Mockery::mock(UserRegistrationService::class);
        $registrationService->shouldReceive('validateRegistration')->once()
            ->withArgs(fn (array $data, $parentId, int $accountType, string $commissionMode): bool => $commissionMode === '')
            ->andReturn(['stop']);
        $this->app->instance(UserRegistrationService::class, $registrationService);

        $this->postJson('/api/front/auth/register', [
            'email' => $email, 'password' => 'RegisterPassword1', 'password_confirmation' => 'RegisterPassword1',
            'user_name' => 'Modern', 'phone_code' => '86', 'phone_number' => '13999110005',
            'phone' => '86-13999110005', 'id_card_no' => 'MODERN-COMM', 'account_type' => 2,
            'captcha_key' => $captchaKey, 'captcha_code' => 'AB12',
            'agree_terms' => 1, 'comm_type' => 'A',
        ])->assertOk()->assertJsonPath('code', ResponseCode::VALIDATION_ERROR);
    }

    // 验证遗留注册载荷正确映射到现代注册契约。
    public function test_legacy_register_payload_maps_to_modern_registration_contract(): void
    {
        $email = 'legacy-register-payload@example.test';
        $captchaResponse = $this->get('/user/register/captcha')->assertOk();
        preg_match('/<text[^>]*>([^<]+)<\/text>/', $captchaResponse->getContent(), $matches);
        $captchaCode = trim((string) ($matches[1] ?? ''));
        $this->assertNotSame('', $captchaCode);

        $login = new UserLogin();
        $login->forceFill([
            'id' => 991001,
            'user_id' => 41991001,
            'email' => $email,
            'is_enabled' => 1,
            'is_cancelled' => 0,
        ]);
        $login->setRelation('userInfo', (new UserInfo())->forceFill([
            'user_id' => 41991001,
            'mt4_code' => 41991001,
            'is_mt4_synced' => 1,
            'is_mt4_enabled' => 1,
        ]));
        $registrationService = Mockery::mock(UserRegistrationService::class);
        $registrationService->shouldReceive('validateRegistration')
            ->once()
            ->withArgs(function (array $data, $parentId, int $accountType, string $commissionMode) use ($email): bool {
                return $data['email'] === $email
                    && $data['account_type'] === 2
                    && $data['inviter_id'] === 41881001
                    && $data['agree_terms'] === '1'
                    && $data['password_confirmation'] === 'LegacyRegisterPassword1'
                    && $data['gender'] === '2'
                    && $parentId === 41881001
                    && $accountType === 2
                    && $commissionMode === 'A';
            })
            ->andReturn([]);
        $registrationService->shouldReceive('register')
            ->once()
            ->withArgs(function (array $data, $parentId, int $accountType) use ($email): bool {
                return $data['email'] === $email
                    && $data['phone'] === '86-13999110001'
                    && $data['gender'] === '2'
                    && $parentId === 41881001
                    && $accountType === 2;
            })
            ->andReturn([
                'success' => true,
                'registered' => true,
                'provisioning_status' => 'pending',
                'user_login' => $login,
            ]);
        $this->app->instance(UserRegistrationService::class, $registrationService);

        $response = $this->postJson('/user/register/registerinto', [
            'register_type' => 'user',
            'userInviterId' => 41881001,
            'comm_type' => 'A',
            'username' => 'Legacy Register User',
            'sex' => 2,
            'userIdcardNo' => 'LEGACY-ID-991001',
            'modules' => '86',
            'userphoneNo' => '13999110001',
            'useremail' => $email,
            'reguserverfcode' => $captchaCode,
            'password' => 'LegacyRegisterPassword1',
            'agreeRule' => 1,
        ]);

        $response->assertOk()
            ->assertJsonPath('code', ResponseCode::SUCCESS)
            ->assertJsonPath('data.user.user_id', 41991001)
            ->assertJsonPath('data.user.email', $email);
    }

    // 验证遗留发送验证码接口映射账号类型与联系方式字段。
    public function test_legacy_register_send_code_payload_maps_account_type_and_contact_fields(): void
    {
        $email = 'legacy-register-send-code@example.test';
        Cache::forget('front_register_email_code_' . sha1($email));
        Cache::forget('front_register_email_code_rate_' . sha1($email . '|127.0.0.1'));
        Mail::fake();

        $response = $this->postJson('/user/register/registerSendCode', [
            'register_type' => 'user',
            'useremail' => $email,
            'modules' => '86',
            'userphoneNo' => '13999110002',
            'userIdcardNo' => 'LEGACY-ID-991002',
            'verifyType' => 'verifyemail',
        ]);

        $response->assertOk()
            ->assertJsonPath('code', ResponseCode::SUCCESS)
            ->assertJsonPath('data.sent', true);
        $payload = Cache::get('front_register_email_code_' . sha1($email));
        $this->assertIsArray($payload);
        $this->assertSame($email, $payload['email']);
        $this->assertMatchesRegularExpression('/^\d{6}$/', (string) $payload['code']);
        Mail::assertSent(FrontRegistrationVerificationCode::class, function (FrontRegistrationVerificationCode $mail) use ($email, $payload): bool {
            return $mail->hasTo($email) && $mail->code === (string) $payload['code'];
        });
    }

    // 校验权限清单文档记录了遗留注册载荷兼容性闭环。
    public function test_final_checklist_records_legacy_register_payload_compatibility(): void
    {
        $checklist = file_get_contents(base_path('docs/admin-backend-blade-permission-final-checklist.md')) ?: '';

        $this->assertStringContainsString('## 348.', $checklist);
        $this->assertStringContainsString('AuthController::normalizedRegisterInput', $checklist);
        $this->assertStringContainsString('user/register/registerinto', $checklist);
        $this->assertStringContainsString('FrontLegacyRegisterPayloadCompatibilityClosureModuleTest', $checklist);
    }
}
