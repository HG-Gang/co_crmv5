<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/17
 * Time: 02:05
 */

namespace Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * 旧前台普通用户登录页会话闭环测试。
 *
 * 文件功能：
 * - 约束 /user/login 必须提交旧字段到 /user/signIn，由服务端建立 suser Session。
 * - 约束登录成功后进入 /user/index，避免仅取得 JWT 后再次被旧鉴权中间件拦截。
 * - 保证 /front/login 继续使用现代 account、password 与 JWT 接口，两套协议互不污染。
 *
 * 返回结果：
 * - 页面契约完整时测试通过。
 * - 任一提交地址、字段、CSRF、成功判断或跳转地址缺失时测试失败并指出缺口。
 */
class FrontLegacyLoginPageSessionClosureModuleTest extends TestCase
{
    use DatabaseTransactions;

    /**
     * 验证旧登录页把会话登录所需配置完整交给公共页面脚本。
     *
     * @return void 页面返回 200，并包含旧协议标识、旧字段、CSRF、提交地址与成功地址。
     */
    public function test_legacy_login_page_exposes_session_sign_in_contract(): void
    {
        $this->get('/user/login')
            ->assertOk()
            ->assertSee('name="csrf-token"', false)
            ->assertSee('data-login-mode="legacy"', false)
            ->assertSee('data-login-endpoint="' . url('/user/signIn') . '"', false)
            ->assertSee('data-success-url="' . url('/user/index') . '"', false)
            ->assertSee('name="loginUid"', false)
            ->assertSee('name="loginPassword"', false)
            ->assertDontSee('name="account"', false)
            ->assertDontSee('name="password"', false);
    }

    /**
     * 验证现代登录页仍保留 JWT 登录字段，不能误带旧 Session 协议标识。
     *
     * @return void 页面返回 200，保留 account、password，且不输出旧登录配置。
     */
    public function test_modern_login_page_keeps_jwt_contract(): void
    {
        $this->get('/front/login')
            ->assertOk()
            ->assertSee('name="account"', false)
            ->assertSee('name="password"', false)
            ->assertDontSee('data-login-mode="legacy"', false)
            ->assertDontSee('name="loginUid"', false)
            ->assertDontSee('name="loginPassword"', false);
    }

    /**
     * 验证公共登录脚本根据 Blade 配置分流两套后端协议。
     *
     * @return void 脚本必须映射旧字段、携带 CSRF、识别旧成功结构并保留现代 JWT 接口。
     */
    public function test_login_script_separates_legacy_session_and_modern_jwt_protocols(): void
    {
        $script = file_get_contents(public_path('js/apps/front/layui/pages.js')) ?: '';

        foreach ([
            'data-login-mode',
            'data-login-endpoint',
            'data-success-url',
            'loginUid',
            'loginPassword',
            'cptcode',
            '/user/captcha',
            'X-CSRF-TOKEN',
            'Number(res.loginStatus) === 200',
            "res.msg === 'OK'",
            "'/api/front/auth/login'",
        ] as $fragment) {
            $this->assertStringContainsString($fragment, $script);
        }
    }

    /**
     * 验证旧登录控制器建立的 Session 能立即通过账户页鉴权。
     *
     * 入参示例：agent@test.com / abc123，为固定演示代理账号。
     *
     * @return void 登录返回旧成功结构，Session 包含 user_id=1001，随后账户页直接返回 200。
     */
    public function test_legacy_sign_in_session_can_open_account_page_immediately(): void
    {
        config(['captcha.characters' => ['A']]);
        $this->get('/user/captcha')->assertOk();
        $session = $this->app['session.store']->all();

        $login = $this->withSession($session)->postJson('/user/signIn', [
            'loginUid' => 'agent@test.com',
            'loginPassword' => 'abc123',
            'cptcode' => 'aaaa',
        ]);

        $login->assertOk()
            ->assertJsonPath('loginStatus', 200)
            ->assertJsonPath('msg', 'OK')
            ->assertSessionHas('suser.user_id', 1001);

        $this->get('/user/account?frame=1')
            ->assertOk()
            ->assertSee('data-api="/api/front/account/profile"', false)
            ->assertSee('id="accountTypeSwitch"', false);
    }
}
