<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/28
 * Time: 00:56
 */

/**
 * DashboardShellUxRoundClosureModuleTest
 *
 * 文件功能：
 * - 验证全局偏好菜单与 Dashboard 紧凑运营台共享交互契约：图标菜单语言切换、四套主外壳共享图标语言、扁平紧凑布局、图标化图表控件与不固定 30 天窗口的周期文案。
 * - 输入：路由、控制器、Blade/JS、迁移等项目源码文本；输出：PHPUnit 断言结果。
 * - 明确不负责：不覆盖 HTTP 运行时业务流与 MT4 真实网关同步（由集成与功能测试锁定）。
 */

namespace Tests\Feature;

use Tests\TestCase;

/**
 * 锁定全局偏好菜单与 Dashboard 紧凑运营台的共享交互契约。
 */
class DashboardShellUxRoundClosureModuleTest extends TestCase
{
    public function test_shared_theme_picker_is_an_icon_menu_with_current_item_semantics(): void
    {
        $html = view('partials.theme-picker', ['themePickerCompact' => true])->render();
        $sync = file_get_contents(public_path('js/shared/theme-sync.js')) ?: '';
        $css = file_get_contents(public_path('css/common/crm-themes.css')) ?: '';

        $this->assertStringContainsString('data-crm-preference-trigger="theme"', $html);
        $this->assertStringContainsString('data-lucide="palette"', $html);
        $this->assertSame(15, substr_count($html, 'data-theme='));
        $this->assertSame(15, substr_count($html, 'data-lucide="check"'));
        $this->assertStringContainsString('aria-current="', $html);
        $this->assertStringContainsString("setAttribute('aria-current'", $sync);
        $this->assertStringContainsString('.crm-preference-item.is-current', $css);
    }

    public function test_four_main_shells_use_shared_icon_language_picker(): void
    {
        foreach ([
            'resources/admin/layui/layouts/app.blade.php',
            'resources/admin/crmui/layouts/app.blade.php',
            'resources/front/layui/layouts/app.blade.php',
            'resources/front/crmui/layouts/app.blade.php',
        ] as $path) {
            $blade = file_get_contents(base_path($path)) ?: '';

            $this->assertStringContainsString('partials.language-picker', $blade, $path);
            $this->assertStringNotContainsString('data-crmui-lang="zh-CN"><i', $blade, $path);
            $this->assertStringNotContainsString('class="lang-switch"', $blade, $path);
        }

        $script = file_get_contents(public_path('js/shared/preference-menu.js')) ?: '';
        $this->assertStringContainsString("event.key === 'Escape'", $script);
        $this->assertStringContainsString("url.searchParams.set('locale', next)", $script);
        $this->assertStringContainsString("localStorage.setItem('crm_locale', next)", $script);
        $this->assertStringContainsString("trigger.focus()", $script);
    }

    public function test_layui_dashboards_use_flat_compact_operations_layout(): void
    {
        $blade = file_get_contents(resource_path('front/layui/dashboard/index.blade.php')) ?: '';
        $v2Blade = file_get_contents(resource_path('front/layui/dashboard/index_v2.blade.php')) ?: '';
        $v2Css = file_get_contents(public_path('css/front/v2.css')) ?: '';
        $dashboardV2Start = strrpos($v2Css, '.front-v2-dashboard-hero {');

        $this->assertIsInt($dashboardV2Start);
        $dashboardV2Css = substr($v2Css, $dashboardV2Start);

        $this->assertStringNotContainsString('background: var(--front-hero-gradient)', $blade);
        $this->assertStringNotContainsString('.dashboard-hero-main:after', $blade);
        $this->assertStringNotContainsString('border-radius: 999px', $blade);
        // 2026-08-28 紧凑化：卡片正文内边距由 12px 收紧为 9px 10px。
        $this->assertStringContainsString('.dashboard-page .layui-card-body { padding: 9px 10px; }', $blade);
        $this->assertStringContainsString('id="identityGuideBtn"', $blade);
        $this->assertStringContainsString('id="identityGuideBtn"', $v2Blade);

        $this->assertStringNotContainsString('linear-gradient', $dashboardV2Css);
        $this->assertStringNotContainsString('radial-gradient', $dashboardV2Css);
        $this->assertStringNotContainsString('.front-v2-dashboard-hero .front-v2-hero::after', $dashboardV2Css);
        $this->assertStringNotContainsString('letter-spacing: -', $dashboardV2Css);
    }

    public function test_dashboard_chart_controls_are_icon_based_localized_and_range_aware(): void
    {
        foreach ([
            'resources/front/layui/dashboard/index.blade.php',
            'resources/front/layui/dashboard/index_v2.blade.php',
        ] as $path) {
            $blade = file_get_contents(base_path($path)) ?: '';

            foreach ([7, 15, 30] as $days) {
                $this->assertSame(1, substr_count($blade, 'data-dashboard-range="' . $days . '"'), $path);
                $this->assertStringContainsString("front.range_days_{$days}", $blade, $path);
            }
            foreach ([
                'bar' => 'chart-column',
                'line' => 'chart-line',
                'area' => 'chart-area',
                'pie' => 'chart-pie',
            ] as $type => $icon) {
                // 2026-08-28：新增 4 张日粒度趋势图后，每种查看方式在 8 张图上各出现一次。
                $this->assertSame(8, substr_count($blade, 'data-chart-type="' . $type . '"'), $path);
                $this->assertSame(8, substr_count($blade, 'data-lucide="' . $icon . '"'), $path);
                $this->assertStringContainsString("front.chart_{$type}", $blade, $path);
            }
            $this->assertStringContainsString('aria-pressed="true"', $blade, $path);
            $this->assertStringContainsString('width: 44px; height: 44px;', $blade, $path);
            $this->assertStringNotContainsString('<select class="dashboard-chart-type"', $blade, $path);
        }

        $script = file_get_contents(public_path('js/apps/front/layui/pages.js')) ?: '';
        $this->assertStringContainsString('var dashboardRequestSequence = 0;', $script);
        $this->assertStringContainsString('data: {days: activeRange}', $script);
        $this->assertStringContainsString('requestId !== dashboardRequestSequence', $script);
        $this->assertStringContainsString('getComputedStyle(document.documentElement)', $script);
        foreach (['--front-blue', '--front-accent', '--front-warn', '--front-danger', '--front-cyan'] as $token) {
            $this->assertStringContainsString($token, $script);
        }
        $this->assertStringNotContainsString("['#2080f0'", $script);
        $this->assertStringNotContainsString("['#0e7a83'", $script);
        $this->assertStringNotContainsString("['#18a058'", $script);

        foreach ([
            resource_path('lang/zh-CN/front.php'),
            resource_path('lang/en/front.php'),
            public_path('js/shared/lang/common/zh-CN.js'),
            public_path('js/shared/lang/common/en.js'),
        ] as $path) {
            $translations = file_get_contents($path) ?: '';
            foreach ([7, 15, 30] as $days) {
                $this->assertStringContainsString('range_days_' . $days, $translations, $path);
            }
        }
    }

    public function test_dashboard_period_labels_do_not_claim_a_fixed_thirty_day_window(): void
    {
        $zh = require resource_path('lang/zh-CN/front.php');
        $en = require resource_path('lang/en/front.php');

        $this->assertSame('当前周期', $zh['monthly_period']);
        $this->assertSame('当前周期入金', $zh['monthly_deposit']);
        $this->assertSame('Current Period', $en['monthly_period']);
        $this->assertSame('Current Period Deposit', $en['monthly_deposit']);

        $zhScript = file_get_contents(public_path('js/shared/lang/common/zh-CN.js')) ?: '';
        $enScript = file_get_contents(public_path('js/shared/lang/common/en.js')) ?: '';
        $this->assertStringContainsString("monthly_period: '当前周期'", $zhScript);
        $this->assertStringContainsString("monthly_period: 'Current Period'", $enScript);
    }
}
