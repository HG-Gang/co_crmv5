<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/17
 * Time: 01:36
 */

/**
 * FrontAuthControllerCommentReadabilityTest
 *
 * 文件功能：
 * - 验证前台 AuthController 对登录、注册、验证码、旧接口兼容和 Token 相关参数保留中文逻辑说明。
 * - 输入：控制器/模型/JS/Blade/CSS 等源码与语言包文本；输出：PHPUnit 断言结果。
 * - 明确不负责：不验证运行时业务行为与数据库交互。
 */

namespace Tests\Feature;

use Tests\TestCase;

/**
 * 前台认证控制器中文注释可读性测试。
 *
 * 功能说明：
 * - 本测试只静态读取 Front\AuthController 源码，不连接真实数据库。
 * - 目标是约束登录、注册、验证码、旧接口兼容和 Token 相关参数必须保留中文逻辑说明。
 */
class FrontAuthControllerCommentReadabilityTest extends TestCase
{
    public function test_front_auth_controller_has_chinese_logic_comments_for_core_parameters(): void
    {
        $source = file_get_contents(app_path('Http/Controllers/Front/AuthController.php')) ?: '';

        foreach ([
            '前台用户认证控制器',
            '处理前台用户登录、注册、注销、令牌刷新',
            'registrationService 表示注册服务',
            'jwtService 表示 JWT 服务',
            'showLogin 用于渲染前台 Layui 登录页',
            'showRegister 用于渲染前台 Layui 注册页',
            'legacyRegisterPage 用于兼容旧前台注册链接',
            'register 用于处理前台注册',
            'account_type=1 表示代理',
            'account_type=2 表示普通客户',
            'captcha_key 表示图形验证码缓存键',
            'captcha_code 表示用户输入的图形验证码',
            'email_code 表示邮箱验证码',
            'login 用于处理新版前台登录',
            'legacySignIn 用于兼容旧前台登录接口',
            'registerSendCode 用于发送注册邮箱验证码',
            'normalizedRegisterInput 用于兼容旧页面字段名称',
            'verifyRegisterCaptcha 用于校验图形验证码',
            '注册主链路只校验图形验证码，不依赖邮箱验证码',
        ] as $expectedComment) {
            $this->assertStringContainsString($expectedComment, $source, 'Front AuthController 缺少中文逻辑注释：' . $expectedComment);
        }
    }

    public function test_front_auth_controller_no_longer_uses_legacy_english_comment_titles(): void
    {
        $source = file_get_contents(app_path('Http/Controllers/Front/AuthController.php')) ?: '';

        foreach ([
            'Front User Authentication Controller',
            'Handles login, registration, logout, and token refresh for front-end users.',
            'Show login page',
            'Show registration page',
            'Process registration',
            'User Login',
            'User Logout',
            'Refresh Token',
            'Change Password',
            'Validate inviter',
            'Check if email is registered',
            'Generate JWT token',
            'Update login info',
            'Record login log',
            'Password required',
        ] as $legacyEnglishComment) {
            $this->assertStringNotContainsString($legacyEnglishComment, $source, 'Front AuthController 仍残留旧英文注释标题：' . $legacyEnglishComment);
        }
    }
}
