<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/07/23
 * Time: 08:17
 */

/**
 * AdminLegacyLoginCaptchaImageContractTest
 *
 * 文件功能：
 * - 验证旧后台图形验证码遵循旧项目 mews/captcha 的 Session/Cache 契约，且生成的验证码图片可被旧登录流程消费。
 * - 输入：路由、控制器、Blade/JS、迁移等项目源码文本；输出：PHPUnit 断言结果。
 * - 明确不负责：不覆盖 HTTP 运行时业务流与 MT4 真实网关同步（由集成与功能测试锁定）。
 */

namespace Tests\Feature;

use App\Constants\ResponseCode;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * 验证旧后台图形验证码仍遵循旧项目 mews/captcha 的 Session/Cache 契约。
 */
class AdminLegacyLoginCaptchaImageContractTest extends TestCase
{
    public function test_legacy_custom_captcha_profile_matches_old_project_contract(): void
    {
        $this->assertSame(4, config('captcha.custom_captcha.length'));
        $this->assertSame(150, config('captcha.custom_captcha.width'));
        $this->assertSame(35, config('captcha.custom_captcha.height'));
        $this->assertFalse(config('captcha.custom_captcha.sensitive'));
    }

    public function test_captcha_image_writes_package_session_and_cache_and_is_consumed_by_legacy_login(): void
    {
        // 固定字符只用于测试，确保可以从真实生成链路取得可验证的输入，不依赖解析图片像素。
        config([
            'captcha.characters' => ['A'],
            'captcha.custom_captcha' => [
                'length' => 4,
                'width' => 150,
                'height' => 35,
                'quality' => 90,
                'expire' => 60,
                'sensitive' => false,
            ],
        ]);

        $captcha = $this->get('/index/admin/captcha');

        $captcha->assertOk()
            ->assertHeader('Content-Type', 'image/png');

        // 图片响应本身不是 View/Redirect，不会附带 TestResponse::getSession()；
        // StartSession 使用的 store 是应用容器中的同一实例。
        $session = $this->app['session.store'];
        $captchaKey = (string) $session->get('captcha.key');

        $this->assertNotSame('', $captchaKey);
        $this->assertTrue($session->has('captcha'));
        $this->assertTrue(Cache::has('captcha_' . md5($captchaKey)));
        $this->assertStringNotContainsString('AAAA', $captcha->getContent());

        // 有效验证码应放行到账号校验；不存在账号时返回 AUTH_FAILED，且验证码一次性消费。
        $response = $this->withSession($session->all())->postJson('/index/admin/logon', [
            'loginUid' => 'captcha-contract-missing-admin',
            'loginPassword' => 'not-used',
            'cptcode' => 'aaaa',
        ]);

        $response->assertOk()->assertJsonPath('code', ResponseCode::AUTH_FAILED);
        $response->assertSessionMissing('captcha');
        $this->assertFalse(Cache::has('captcha_' . md5($captchaKey)));
    }
}
