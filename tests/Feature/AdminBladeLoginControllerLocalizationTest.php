<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/06/09
 * Time: 22:35
 */

/**
 * AdminBladeLoginControllerLocalizationTest
 *
 * 文件功能：
 * - 验证后台 Blade 登录控制器使用语言感知校验并保留指定中文注释片段。
 * - 输入：控制器/模型/JS/Blade/CSS 等源码与语言包文本；输出：PHPUnit 断言结果。
 * - 明确不负责：不验证运行时业务行为与数据库交互。
 */

namespace Tests\Feature;

use Tests\TestCase;

/**
 * 后台 Blade 登录控制器多语言与中文注释契约测试。
 *
 * 功能逻辑说明：
 * - `AdminAuthController` 负责传统 Blade 后台登录页的展示、表单登录和退出登录。
 * - 用户要求后端必须支持多语言，因此登录表单验证错误不能固定使用 `zh_CN` 或硬编码中文提示。
 * - 用户要求所有模块文件和参数必须有中文逻辑注释，因此本测试同时约束 `$request`、`email`、`password`、`remember` 等登录参数说明。
 */
class AdminBladeLoginControllerLocalizationTest extends TestCase
{
    /**
     * 后台 Blade 登录验证必须使用当前语言环境，并保留登录参数中文说明。
     *
     * 参数与变量含义：
     * - $source：后台 Blade 登录控制器源码文本，用于静态检查多语言调用和中文逻辑注释。
     * - $requiredFragments：必须出现的中文注释片段，覆盖登录入口、登录参数、登录日志和退出登录边界。
     *
     * @return void
     */
    public function test_blade_admin_login_controller_uses_locale_aware_validation_and_comments(): void
    {
        $source = file_get_contents(app_path('Http/Controllers/Admin/AdminAuthController.php')) ?: '';

        foreach ($this->requiredFragments() as $fragment) {
            $this->assertStringContainsString($fragment, $source, 'AdminAuthController 缺少中文逻辑注释：' . $fragment);
        }

        $this->assertStringContainsString("__('validation.required', ['attribute' => __('auth.email')])", $source);
        $this->assertStringContainsString("__('validation.required', ['attribute' => __('auth.password_label')])", $source);
        $this->assertStringContainsString("'password_label'", file_get_contents(resource_path('lang/zh-CN/auth.php')) ?: '');
        $this->assertStringContainsString("'password_label'", file_get_contents(resource_path('lang/en/auth.php')) ?: '');

        $this->assertStringNotContainsString("__('common.required', [], 'zh_CN')", $source);
        $this->assertStringNotContainsString("不能为空", $source);
        $this->assertStringNotContainsString("不能為空", $source);
        $this->assertStringNotContainsString("涓嶈兘涓虹┖", $source);
        $this->assertStringNotContainsString("zh_CN", $source);
    }

    /**
     * 必须保留的后台 Blade 登录控制器中文注释片段。
     *
     * @return array<int, string> 中文注释片段列表，用于覆盖登录页、登录动作、退出动作和关键参数含义。
     */
    private function requiredFragments(): array
    {
        return [
            '后台 Blade 登录控制器',
            'showLogin 展示后台登录页',
            'doLogin 处理后台登录表单',
            '$request 表示当前登录表单请求',
            'email 表示管理员登录邮箱',
            'password 表示管理员登录密码',
            'remember 表示是否延长后台登录会话',
            'AdminLoginLog 记录后台登录审计日志',
            'logout 退出后台 Blade 会话',
            '重新生成 CSRF Token',
        ];
    }
}
