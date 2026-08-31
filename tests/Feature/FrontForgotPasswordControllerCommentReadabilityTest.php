<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/06/09
 * Time: 00:50
 */

/**
 * FrontForgotPasswordControllerCommentReadabilityTest
 *
 * 文件功能：
 * - 验证前台找回密码控制器中文注释与多语言契约：新旧找回密码接口的中文逻辑说明、用户不存在/验证码无效/重置成功消息使用真实存在的语言 key。
 * - 输入：语言包数组与渲染后的响应/脚本文本；输出：PHPUnit 断言结果。
 * - 明确不负责：不覆盖业务逻辑与路由契约（由各模块功能测试锁定）。
 */

namespace Tests\Feature;

use Tests\TestCase;

/**
 * 前台找回密码控制器中文注释与多语言响应可读性测试。
 *
 * 测试目标：
 * - 只读取 ForgotPasswordController 源码和语言包，不连接真实数据库。
 * - 约束新前台找回密码接口和旧前台找回密码兼容接口都具备中文逻辑说明。
 * - 约束用户不存在、验证码无效、重置成功等接口消息必须使用真实存在的 Laravel 多语言 key。
 */
class FrontForgotPasswordControllerCommentReadabilityTest extends TestCase
{
    public function test_forgot_password_controller_contains_required_chinese_logic_comments(): void
    {
        $source = file_get_contents(app_path('Http/Controllers/Front/ForgotPasswordController.php')) ?: '';

        $expectedComments = [
            '前台找回密码控制器',
            '新前台接口',
            '旧前台兼容接口',
            'front_reset_code',
            'email 表示接收验证码的登录邮箱',
            'useremail 表示旧前台提交的邮箱参数',
            'codedata 表示旧前台提交的验证码参数',
            'password_confirmation 表示 Laravel confirmed 规则使用的确认密码',
            'checkUserInfo 用于旧前台找回密码第一步校验用户 ID 与邮箱',
            'forgetPasswordInfoVerification 用于旧前台校验邮箱验证码',
            'saveChangePassword 用于旧前台保存新密码',
            'legacyFail 用于保留旧前台 msg/err/col 响应结构',
        ];

        foreach ($expectedComments as $expectedComment) {
            $this->assertStringContainsString($expectedComment, $source, 'ForgotPasswordController 缺少中文逻辑注释：' . $expectedComment);
        }
    }

    public function test_forgot_password_controller_uses_existing_language_keys(): void
    {
        $source = file_get_contents(app_path('Http/Controllers/Front/ForgotPasswordController.php')) ?: '';

        $this->assertStringContainsString('response.user_not_found', $source, '用户不存在响应必须使用 response.user_not_found 语言 key。');
        $this->assertStringContainsString('auth.reset_code_invalid', $source, '验证码错误响应必须使用 auth.reset_code_invalid 语言 key。');
        $this->assertStringContainsString('auth.reset_code_sent', $source, '验证码发送成功响应必须使用 auth.reset_code_sent 语言 key。');
        $this->assertStringContainsString('auth.password_reset_success', $source, '密码重置成功响应必须使用 auth.password_reset_success 语言 key。');
        $this->assertStringNotContainsString('auth.user_not_found', $source, 'auth.php 中没有 user_not_found，不能继续使用不存在的语言 key。');
    }

    public function test_forgot_password_language_keys_exist_in_both_languages(): void
    {
        $zhAuth = require resource_path('lang/zh-CN/auth.php');
        $enAuth = require resource_path('lang/en/auth.php');
        $zhResponse = require resource_path('lang/zh-CN/response.php');
        $enResponse = require resource_path('lang/en/response.php');

        foreach (['reset_code_sent', 'reset_code_invalid', 'password_reset_success'] as $key) {
            $this->assertArrayHasKey($key, $zhAuth, 'zh-CN/auth.php 缺少语言 key：' . $key);
            $this->assertArrayHasKey($key, $enAuth, 'en/auth.php 缺少语言 key：' . $key);
            $this->assertNotSame('', trim((string) $zhAuth[$key]), 'zh-CN/auth.php 的 ' . $key . ' 不能为空');
            $this->assertNotSame('', trim((string) $enAuth[$key]), 'en/auth.php 的 ' . $key . ' 不能为空');
        }

        foreach (['user_not_found', 'validation_failed'] as $key) {
            $this->assertArrayHasKey($key, $zhResponse, 'zh-CN/response.php 缺少语言 key：' . $key);
            $this->assertArrayHasKey($key, $enResponse, 'en/response.php 缺少语言 key：' . $key);
            $this->assertNotSame('', trim((string) $zhResponse[$key]), 'zh-CN/response.php 的 ' . $key . ' 不能为空');
            $this->assertNotSame('', trim((string) $enResponse[$key]), 'en/response.php 的 ' . $key . ' 不能为空');
        }
    }
}
