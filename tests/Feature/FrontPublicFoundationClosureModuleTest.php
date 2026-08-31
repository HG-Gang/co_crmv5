<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/07/26
 * Time: 12:42
 */

namespace Tests\Feature;

use Tests\TestCase;

/**
 * 前台公共根入口与第三方验证码运行闭环测试。
 *
 * 文件功能：
 * - 验证项目2根路径保留项目1的 langId 透传和默认值语义。
 * - 验证 mews/captcha 注册的图片与 API 路由都可真实执行，而非只存在于路由表。
 *
 * 返回结果：根入口成功时重定向到 Blade 登录页；验证码图片返回 PNG，API 返回 key、
 * sensitive 与 data URL，未知配置由组件抛出的真实错误处理，不在路由层伪造成功。
 */
class FrontPublicFoundationClosureModuleTest extends TestCase
{
    /**
     * 根入口必须把旧 langId 参数原样带到新的 Blade 登录页，缺省时使用项目1的值 1。
     *
     * @return void
     */
    public function test_root_redirect_preserves_legacy_language_parameter_and_default(): void
    {
        $this->get('/?langId=2')
            ->assertRedirect('/front/login?langId=2');

        $this->get('/')
            ->assertRedirect('/front/login?langId=1');
    }

    /**
     * vendor 验证码图片和 API 必须按 default 配置生成同一类可消费凭证。
     *
     * @return void
     */
    public function test_vendor_captcha_image_and_api_routes_execute_successfully(): void
    {
        $this->get('/captcha/default')
            ->assertOk()
            ->assertHeader('Content-Type', 'image/png');

        $response = $this->getJson('/captcha/api/default')
            ->assertOk()
            ->assertJsonStructure(['sensitive', 'key', 'img']);

        $this->assertIsBool($response->json('sensitive'));
        $this->assertNotSame('', trim((string) $response->json('key')));
        $this->assertStringStartsWith('data:image/png;base64,', (string) $response->json('img'));
    }
}
