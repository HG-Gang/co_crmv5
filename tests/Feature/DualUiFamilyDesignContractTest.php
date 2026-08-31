<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/07
 * Time: 19:58
 */

/**
 * 双 UI 家族（Layui 与 CrmUI）设计契约测试。
 *
 * 文件功能：
 * - 验证双 UI 设计文档存在并明确四套 UI 家族边界（不改成 SPA、不新增第三套 UI）。
 * - 验证四个 Blade 视图命名空间（front_layui、admin_layui、front_crmui、admin_crmui）均已注册。
 * - 验证公开入口页渲染各自家族的资产，且布局不跨家族引用页面级资产。
 *
 * 适用场景：
 * - 双 UI 家族实现与设计文档一致性的回归测试。
 *
 * 入参例子：
 * - GET /front/login、/admin/login、/front-crmui/login、/admin-crmui/login。
 *
 * 返回值：
 * - 各页面渲染对应家族 CSS 且不混入另一家族资产时断言通过。
 *
 * 异常或失败场景：
 * - 缺文档、缺命名空间、资产串家族时断言失败。
 */

namespace Tests\Feature;

use Tests\TestCase;

final class DualUiFamilyDesignContractTest extends TestCase
{
    /**
     * 验证双 UI 设计源文档存在并明确四套 UI 家族边界。
     */
    public function test_design_source_documents_exist_for_dual_ui_implementation(): void
    {
        $spec = base_path('docs/superpowers/specs/2026-07-31-laravel-dual-ui-families-design.md');
        $plan = base_path('docs/superpowers/plans/2026-08-01-laravel-dual-ui-families-phase1-implementation.md');
        $handbook = base_path('docs/superpowers/guides/dual-ui-implementation-handbook.md');

        $this->assertFileExists($spec);
        $this->assertFileExists($plan);
        $this->assertFileExists($handbook);

        $specText = file_get_contents($spec) ?: '';
        foreach ([
            '前台 A：Layui + Blade',
            '后台 A：Layui + Blade',
            '前台 B：CRMUI + Blade',
            '后台 B：CRMUI + Blade',
            '不把系统改造成 SPA',
            '不新增第三套真实 UI',
        ] as $needle) {
            $this->assertStringContainsString($needle, $specText);
        }
    }

    /**
     * 验证四个 UI 家族视图命名空间均已注册。
     */
    public function test_four_ui_family_view_namespaces_are_registered(): void
    {
        foreach ([
            'front_layui::layouts.app',
            'admin_layui::layouts.app',
            'front_crmui::layouts.app',
            'admin_crmui::layouts.app',
        ] as $view) {
            $this->assertTrue(view()->exists($view), 'Missing Blade view namespace: ' . $view);
        }
    }

    /**
     * 验证公开入口页渲染各自家族的资产。
     */
    public function test_public_entry_pages_render_the_expected_family_assets(): void
    {
        $this->get('/front/login')
            ->assertOk()
            ->assertSee('/css/front/', false)
            ->assertDontSee('/css/crmui/front.css', false);

        $this->get('/admin/login')
            ->assertOk()
            ->assertSee('/css/admin/style.css', false)
            ->assertDontSee('/css/crmui/admin.css', false);

        $this->get('/front-crmui/login')
            ->assertOk()
            ->assertSee('/css/crmui/front.css', false)
            ->assertDontSee('/css/front/style.css', false);

        $this->get('/admin-crmui/login')
            ->assertOk()
            ->assertSee('/css/crmui/admin.css', false)
            ->assertDontSee('/css/admin/style.css', false);
    }

    /**
     * 验证布局不跨家族引用页面级资产。
     */
    public function test_layouts_do_not_cross_link_page_level_family_assets(): void
    {
        $frontLayui = file_get_contents(resource_path('front/layui/layouts/app.blade.php')) ?: '';
        $adminLayui = file_get_contents(resource_path('admin/layui/layouts/app.blade.php')) ?: '';
        $frontCrmui = file_get_contents(resource_path('front/crmui/layouts/app.blade.php')) ?: '';
        $adminCrmui = file_get_contents(resource_path('admin/crmui/layouts/app.blade.php')) ?: '';

        $this->assertStringContainsString('/css/front/style.css', $frontLayui);
        $this->assertStringNotContainsString('/css/crmui/front.css', $frontLayui);

        $this->assertStringContainsString('/css/admin/style.css', $adminLayui);
        $this->assertStringNotContainsString('/css/crmui/admin.css', $adminLayui);

        $this->assertStringContainsString('/css/crmui/front.css', $frontCrmui);
        $this->assertStringNotContainsString('/css/front/style.css', $frontCrmui);

        $this->assertStringContainsString('/css/crmui/admin.css', $adminCrmui);
        $this->assertStringNotContainsString('/css/admin/style.css', $adminCrmui);
    }

    /**
     * 验证 Layui 认证详情的加载图标样式由实际加载的 Layui 家族样式表持有。
     */
    public function test_authentication_detail_loading_icon_style_stays_in_layui_family(): void
    {
        $crmuiCss = file_get_contents(public_path('css/crmui/admin.css')) ?: '';
        $layuiCss = file_get_contents(public_path('css/admin/style.css')) ?: '';

        $this->assertStringNotContainsString('.auth-detail-loading-icon', $crmuiCss);
        $this->assertStringContainsString('.auth-detail-loading-icon', $layuiCss);
    }
}
