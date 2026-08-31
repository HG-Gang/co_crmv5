<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/17
 * Time: 05:38
 */

/**
 * 统一 Blade 设计系统(UnifiedBladeDesignSystemTest)的静态资源契约测试。
 *
 * 文件功能:
 * - 验证前台/后台/旧版前台 layout 壳均加载共享 crm-design-system.css,
 *   并挂载 crm-ui-front-shell / crm-ui-admin-shell 外壳类。
 * - 验证所有登录/注册/找回密码页使用 crm-ui-auth-body 且不再残留随机装饰背景
 *   (auth-bg-orbits / auth-bg-aurora / auth-bg-particles)。
 * - 验证 admin layui 壳的 body 框架标记与 page kicker 结构,以及 crm-design-system.css
 *   定义了全部 UI tokens 与 layui 覆盖规则。
 *
 * 适用场景:任何 Blade 视图、CSS 变量或 layout 结构调整后回归,防止页面样式漂移
 * 与设计系统碎片化。
 *
 * 入参例子:无外部入参;测试直接读取 resources/** 与 public/css/common/crm-design-system.css
 * 文件内容做字符串断言。
 *
 * 返回值:无返回值;断言通过表示视图与 CSS 内容符合设计系统契约,闭环成立。
 *
 * 失败场景:断言失败说明某页面脱离共享设计系统、残留随机装饰背景或 CSS 缺少必需规则,
 * 需回退对应视图/CSS 改动。
 */

namespace Tests\Feature;

use PHPUnit\Framework\TestCase;

class UnifiedBladeDesignSystemTest extends TestCase
{
    public function test_layui_shells_load_shared_design_system_layer(): void
    {
        $frontLayout = $this->readResource('front/layui/layouts/app.blade.php');
        $adminLayout = $this->readResource('admin/layui/layouts/app.blade.php');
        $legacyFrontLayout = $this->readResource('views/front/layouts/app.blade.php');

        foreach ([$frontLayout, $adminLayout, $legacyFrontLayout] as $layout) {
            $this->assertStringContainsString('crm-design-system.css', $layout);
        }

        $this->assertStringContainsString('crm-ui-front-shell', $frontLayout);
        $this->assertStringContainsString('crm-ui-admin-shell', $adminLayout);
        $this->assertStringContainsString('crm-ui-front-shell', $legacyFrontLayout);
    }

    public function test_auth_pages_use_same_design_system_without_random_decorative_backgrounds(): void
    {
        $frontLogin = $this->readResource('front/layui/auth/login.blade.php');
        $frontRegister = $this->readResource('front/layui/auth/register.blade.php');
        $frontForgot = $this->readResource('front/layui/auth/forgot-password.blade.php');
        $frontBigNumber = $this->readResource('front/layui/auth/big-number-login.blade.php');
        $frontLoginV2 = $this->readResource('front/layui/auth/login_v2.blade.php');
        $frontRegisterV2 = $this->readResource('front/layui/auth/register_v2.blade.php');
        $adminLogin = $this->readResource('admin/layui/auth/login.blade.php');

        foreach ([
            $frontLogin,
            $frontRegister,
            $frontForgot,
            $frontBigNumber,
            $frontLoginV2,
            $frontRegisterV2,
            $adminLogin,
        ] as $authPage) {
            $this->assertStringContainsString('crm-design-system.css', $authPage);
            $this->assertStringContainsString('crm-ui-auth-body', $authPage);
            $this->assertStringNotContainsString('auth-bg-orbits', $authPage);
            $this->assertStringNotContainsString('auth-bg-aurora', $authPage);
            $this->assertStringNotContainsString('auth-bg-particles', $authPage);
        }
    }

    public function test_admin_layui_shell_has_valid_design_system_frame_markup(): void
    {
        $layout = $this->readResource('admin/layui/layouts/app.blade.php');

        $this->assertStringContainsString('<body class="layui-layout-body crm-admin-workbench crm-ui-admin-shell"', $layout);
        $this->assertMatchesRegularExpression('/<div class="crm-admin-page-kicker">[^<]+<\/div>/u', $layout);
    }

    public function test_shared_design_system_defines_product_ui_tokens_and_layui_overrides(): void
    {
        $css = $this->readPublic('css/common/crm-design-system.css');

        foreach ([
            '--crm-bg',
            '--crm-surface',
            '--crm-ink',
            '--crm-primary',
            '--crm-sidebar',
            '--crm-radius',
            '--crm-shadow',
            '.crm-ui-front-shell',
            '.crm-ui-admin-shell',
            '.crm-ui-auth-body',
            '.layui-card',
            '.layui-table-view',
            '.layui-form-pane',
            '.front-v2-auth',
            '.front-v2-page-shell',
            '.crm-admin-panel',
            '.crm-table-summary',
            '.layui-tab-title',
            '.front-v2-empty-state',
            '@media screen and (max-width: 768px)',
        ] as $fragment) {
            $this->assertStringContainsString($fragment, $css);
        }
    }

    public function test_shared_auth_card_resets_legacy_mobile_margin_to_prevent_viewport_overflow(): void
    {
        $css = $this->readPublic('css/common/crm-design-system.css');

        $this->assertMatchesRegularExpression(
            '/\.crm-ui-auth-body \.auth-shell \.auth-card\s*\{[^}]*margin:\s*0;/s',
            $css,
            'The shared auth card must neutralize the legacy 20px mobile margin before using width: 100%.'
        );
    }

    public function test_register_captcha_refresh_button_has_touch_target(): void
    {
        $css = $this->readPublic('css/common/crm-design-system.css');

        $this->assertMatchesRegularExpression(
            '/\.crm-ui-auth-body\s+#refreshCaptcha\s*\{[^}]*min-width:\s*44px;[^}]*min-height:\s*44px;/s',
            $css,
            'The register captcha refresh control must provide a 44px touch target.'
        );
    }

    private function readResource(string $relativePath): string
    {
        return file_get_contents($this->basePath('resources/' . $relativePath)) ?: '';
    }

    private function readPublic(string $relativePath): string
    {
        return file_get_contents($this->basePath('public/' . $relativePath)) ?: '';
    }

    private function basePath(string $relativePath): string
    {
        return dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativePath);
    }
}
