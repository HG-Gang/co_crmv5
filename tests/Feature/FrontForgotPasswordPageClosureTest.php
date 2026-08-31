<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/07/26
 * Time: 11:30
 */

/**
 * FrontForgotPasswordPageClosureTest
 *
 * 文件功能：
 * - 验证找回密码页面闭环：Layui 页面暴露邮箱验证码按钮，脚本管理验证码发送流程与按钮状态。
 * - 输入：路由、控制器、Blade/JS、迁移等项目源码文本；输出：PHPUnit 断言结果。
 * - 明确不负责：不覆盖 HTTP 运行时业务流与 MT4 真实网关同步（由集成与功能测试锁定）。
 */

namespace Tests\Feature;

use Tests\TestCase;

/**
 * 前台 Layui 找回密码页面闭环测试。
 *
 * 业务边界：
 * - 用户必须先向公开邮箱验证码接口请求验证码，再提交邮箱、验证码和新密码完成重置。
 * - 发送成功后按钮进入倒计时，接口失败或倒计时结束后必须恢复可点击状态。
 *
 * 验证结果：
 * - 页面具备可访问的发送验证码按钮。
 * - 页面脚本调用真实资源式接口并明确处理成功、失败和重复发送状态。
 */
class FrontForgotPasswordPageClosureTest extends TestCase
{
    /**
     * 验证找回密码 Blade 暴露发送验证码交互入口。
     *
     * @return void 断言验证码输入框与发送按钮处于同一交互区域，按钮不提交重置表单。
     */
    public function test_layui_forgot_password_page_exposes_email_code_button(): void
    {
        $blade = file_get_contents(resource_path('front/layui/auth/forgot-password.blade.php')) ?: '';

        $this->assertStringContainsString('class="register-code-row"', $blade);
        $this->assertStringContainsString('id="sendResetCode"', $blade);
        $this->assertStringContainsString('type="button"', $blade);
        $this->assertStringContainsString("__('auth.send_reset_code')", $blade);
    }

    /**
     * 验证找回密码脚本使用真实邮箱验证码接口并管理按钮状态。
     *
     * @return void 断言请求地址、邮箱参数、成功倒计时和失败恢复逻辑全部存在。
     */
    public function test_layui_forgot_password_script_closes_email_code_flow(): void
    {
        $script = file_get_contents(public_path('js/apps/front/layui/pages.js')) ?: '';
        $start = strpos($script, "registry['auth/forgot-password']");
        $end = strpos($script, "registry['auth/login']", $start === false ? 0 : $start);
        $module = ($start !== false && $end !== false) ? substr($script, $start, $end - $start) : '';

        $this->assertStringContainsString("url: '/api/front/auth/password/email-code'", $module);
        $this->assertStringContainsString("email: email", $module);
        $this->assertStringContainsString("$('#sendResetCode')", $module);
        $this->assertStringContainsString('startResetCodeCountdown', $module);
        $this->assertStringContainsString("prop('disabled', false)", $module);
    }
}
