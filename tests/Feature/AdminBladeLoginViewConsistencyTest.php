<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/06/10
 * Time: 00:00
 */

/**
 * AdminBladeLoginViewConsistencyTest
 *
 * 文件功能：
 * - 验证现代后台 Layui 登录页的表单提交地址与字段名和控制器校验字段保持一致。
 * - 输入：路由、控制器、Blade/JS、迁移等项目源码文本；输出：PHPUnit 断言结果。
 * - 明确不负责：不覆盖 HTTP 运行时业务流与 MT4 真实网关同步（由集成与功能测试锁定）。
 */

namespace Tests\Feature;

use Tests\TestCase;

/**
 * 后台 Blade 登录视图一致性测试。
 *
 * 功能逻辑说明：
 * - 后台页面必须使用 Laravel Blade 渲染，并且 UI 参考 Vben/Naive/Ant/Arco 后统一落到 `admin_layui::` 现代后台视图命名空间。
 * - `AdminAuthController` 如果继续返回旧 `admin.auth.login`，会绕开 `resources/admin/layui/auth/login.blade.php` 的现代登录页。
 * - 登录页表单字段必须与控制器校验字段一致，否则页面提交 `username` 但后端校验 `email`，会导致真实登录不可用。
 */
class AdminBladeLoginViewConsistencyTest extends TestCase
{
    /**
     * 后台 Blade 登录控制器必须使用现代 admin_layui 登录页。
     *
     * 参数与变量含义：
     * - $controller：后台 Blade 登录控制器源码，用于确认视图命名空间和表单字段契约。
     *
     * @return void
     */
    public function test_admin_auth_controller_uses_modern_layui_login_view(): void
    {
        $controller = file_get_contents(app_path('Http/Controllers/Admin/AdminAuthController.php')) ?: '';

        $this->assertStringContainsString("view('admin_layui::auth.login')", $controller);
        $this->assertStringNotContainsString("view('admin.auth.login')", $controller);
        $this->assertStringContainsString("'email'    => 'required|email'", $controller);
        $this->assertStringContainsString('email 表示管理员登录邮箱', $controller);
    }

    /**
     * 现代后台登录页表单字段必须与控制器校验字段一致。
     *
     * 参数与变量含义：
     * - $loginView：现代后台 Layui 登录页源码，用于检查表单提交地址、字段名和中文注释。
     *
     * @return void
     */
    public function test_modern_layui_login_view_posts_email_and_password_fields(): void
    {
        $loginView = file_get_contents(resource_path('admin/layui/auth/login.blade.php')) ?: '';

        $this->assertStringContainsString("action=\"{{ route('admin.login.post') }}\"", $loginView);
        $this->assertStringContainsString('name="email"', $loginView);
        $this->assertStringContainsString('autocomplete="username"', $loginView);
        $this->assertStringContainsString('data-translate-placeholder="auth.email"', $loginView);
        $this->assertStringContainsString('name="password"', $loginView);
        $this->assertStringContainsString('data-translate-placeholder="auth.password_label"', $loginView);
        $this->assertStringContainsString('name="remember"', $loginView);
        $this->assertStringContainsString('email 表示管理员登录邮箱', $loginView);
        $this->assertStringContainsString('password 表示管理员登录密码', $loginView);
        $this->assertStringContainsString('remember 表示是否延长后台登录会话', $loginView);
    }
}
