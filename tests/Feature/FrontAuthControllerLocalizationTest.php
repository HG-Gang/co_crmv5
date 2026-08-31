<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/16
 * Time: 03:26
 */

/**
 * FrontAuthControllerLocalizationTest
 *
 * 文件功能：
 * - 验证前台认证控制器多语言契约：注册图形验证码、兼容邮箱验证码发送、发送频率与邮件失败提示均来自语言包且 key 中英文存在。
 * - 输入：语言包数组与渲染后的响应/脚本文本；输出：PHPUnit 断言结果。
 * - 明确不负责：不覆盖业务逻辑与路由契约（由各模块功能测试锁定）。
 */

namespace Tests\Feature;

use Tests\TestCase;

/**
 * 前台认证控制器多语言测试。
 *
 * 功能说明：
 * - 该测试只读取 Front\AuthController、注册验证码 Mailable、邮件模板源码和语言包，不访问真实数据库。
 * - 目标是确保注册图形验证码、兼容邮箱验证码发送、发送频率和邮件发送失败提示都来自 Laravel 语言包。
 * - 注册主链路不校验邮箱验证码，因此不要求控制器调用 auth.invalid_email_code。
 */
class FrontAuthControllerLocalizationTest extends TestCase
{
    public function test_front_auth_controller_uses_language_keys_for_register_code_messages(): void
    {
        $controllerSource = file_get_contents(app_path('Http/Controllers/Front/AuthController.php')) ?: '';
        $mailSource = file_get_contents(app_path('Mail/FrontRegistrationVerificationCode.php')) ?: '';
        $mailViewSource = file_get_contents(resource_path('views/emails/front-registration-verification-code.blade.php')) ?: '';
        $source = $controllerSource . "\n" . $mailSource . "\n" . $mailViewSource;

        foreach ([
            'Invalid captcha',
            'Invalid email verification code',
            'Validation failed',
            'Please request the email code later',
            'Email send failed',
            'Your registration verification code is:',
            'Registration verification code',
        ] as $hardCodedMessage) {
            $this->assertStringNotContainsString($hardCodedMessage, $source, '前台认证与注册验证码邮件链仍存在硬编码英文文案：' . $hardCodedMessage);
        }

        foreach ([
            'auth.invalid_captcha',
            'response.validation_failed',
            'response.rate_limited',
            'response.email_send_failed',
            'auth.registration_verification_mail_body',
            'auth.registration_verification_mail_subject',
        ] as $languageKey) {
            $this->assertStringContainsString($languageKey, $source, '前台认证与注册验证码邮件链缺少语言包 key 调用：' . $languageKey);
        }
    }

    public function test_front_auth_register_code_language_keys_exist_in_zh_cn_and_en(): void
    {
        $authKeys = [
            'invalid_captcha',
            'invalid_email_code',
            'registration_verification_mail_body',
            'registration_verification_mail_subject',
        ];
        $responseKeys = [
            'validation_failed',
            'rate_limited',
            'email_send_failed',
        ];

        $zhAuth = require resource_path('lang/zh-CN/auth.php');
        $enAuth = require resource_path('lang/en/auth.php');
        $zhResponse = require resource_path('lang/zh-CN/response.php');
        $enResponse = require resource_path('lang/en/response.php');

        foreach ($authKeys as $key) {
            $this->assertArrayHasKey($key, $zhAuth, 'zh-CN/auth.php 缺少语言 key：' . $key);
            $this->assertArrayHasKey($key, $enAuth, 'en/auth.php 缺少语言 key：' . $key);
            $this->assertNotSame('', trim((string) $zhAuth[$key]), 'zh-CN/auth.php 的 ' . $key . ' 不能为空');
            $this->assertNotSame('', trim((string) $enAuth[$key]), 'en/auth.php 的 ' . $key . ' 不能为空');
        }

        foreach ($responseKeys as $key) {
            $this->assertArrayHasKey($key, $zhResponse, 'zh-CN/response.php 缺少语言 key：' . $key);
            $this->assertArrayHasKey($key, $enResponse, 'en/response.php 缺少语言 key：' . $key);
        }
    }
}
