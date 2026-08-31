<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/28
 * Time: 01:01
 */

/**
 * GlobalCrmThemeCoverageTest
 *
 * 文件功能：
 * - 验证全局 CRM 主题目录覆盖：主题清单与 WCAG AA 对比度、主题签名与近似色、语义与兼容 token、主题同步脚本为唯一状态源、四大外壳暴露共享主题选择器且 Blade 不硬编码颜色字面量。
 * - 输入：路由、控制器、Blade/JS、迁移等项目源码文本；输出：PHPUnit 断言结果。
 * - 明确不负责：不覆盖 HTTP 运行时业务流与 MT4 真实网关同步（由集成与功能测试锁定）。
 */

namespace Tests\Feature;

use Tests\TestCase;

class GlobalCrmThemeCoverageTest extends TestCase
{
    /**
     * CRM 后台允许上线的全部主题键（含历史 5 个与新增 10 个）。用例断言主题注册表与其完全一致，
     * 多一个少一个都视为注册表漂移。
     * @var array<int, string>
     */
    private const THEMES = [
        'light',
        'dark',
        'sea',
        'warm',
        'contrast',
        'clear-blue',
        'mint-ledger',
        'cloud-minimal',
        'sea-salt',
        'indigo-order',
        'coral-note',
        'celadon-ops',
        'sunlit-mark',
        'steel-table',
        'ink-sidebar',
    ];

    /**
     * 本期新增的 10 个主题键。单独成表用于：验证新主题的可访问性资源齐全，
     * 并两两组合验证选择器特异性互不覆盖。
     * @var array<int, string>
     */
    private const NEW_THEMES = [
        'clear-blue',
        'mint-ledger',
        'cloud-minimal',
        'sea-salt',
        'indigo-order',
        'coral-note',
        'celadon-ops',
        'sunlit-mark',
        'steel-table',
        'ink-sidebar',
    ];

    /**
     * 承载主题切换器的 HTML 入口模板清单。用例逐一检查这些 blade 都引用了主题样式与切换组件。
     * @var array<int, string>
     */
    private const HTML_ENTRYPOINTS = [
        'resources/admin/crmui/layouts/app.blade.php',
        'resources/admin/crmui/layouts/auth.blade.php',
        'resources/admin/layui/layouts/app.blade.php',
        'resources/admin/layui/auth/login.blade.php',
        'resources/front/crmui/layouts/app.blade.php',
        'resources/front/crmui/layouts/auth.blade.php',
        'resources/front/crmui/big-agent/layout.blade.php',
        'resources/front/layui/layouts/app.blade.php',
        'resources/front/layui/legacy-big-agent/layout.blade.php',
        'resources/front/layui/auth/big-number-login.blade.php',
        'resources/front/layui/auth/forgot-password.blade.php',
        'resources/front/layui/auth/login.blade.php',
        'resources/front/layui/auth/login_v2.blade.php',
        'resources/front/layui/auth/register.blade.php',
        'resources/front/layui/auth/register_v2.blade.php',
        'resources/views/front/layouts/app.blade.php',
    ];

    /**
     * 新旧两套后台主布局文件。用例验证主题机制在两套布局中都正确接线，防止只改了 crmui 漏掉 layui。
     * @var array<int, string>
     */
    private const MAIN_LAYOUTS = [
        'resources/admin/crmui/layouts/app.blade.php',
        'resources/admin/layui/layouts/app.blade.php',
        'resources/front/crmui/layouts/app.blade.php',
        'resources/front/layui/layouts/app.blade.php',
    ];

    public function test_theme_catalog_contains_existing_and_ten_approved_themes(): void
    {
        $themes = config('crm_themes.themes', []);

        $this->assertSame(self::THEMES, array_keys($themes));
        $this->assertCount(15, $themes);

        foreach ($themes as $key => $theme) {
            $this->assertSame('front.theme_' . str_replace('-', '_', $key), $theme['label'] ?? null, $key . ' label key is invalid.');
            $this->assertContains($theme['mode'] ?? null, ['light', 'dark'], $key . ' must declare its browser color mode.');
            $this->assertIsArray($theme['colors'] ?? null, $key . ' must declare semantic colors.');
            $this->assertIsArray($theme['ui'] ?? null, $key . ' must declare visual parameters.');
        }
    }

    public function test_all_theme_text_pairs_meet_wcag_aa_contrast(): void
    {
        $themes = config('crm_themes.themes', []);
        $pairs = [
            ['text', 'background'],
            ['text', 'surface'],
            ['text', 'surface_alt'],
            ['muted', 'background'],
            ['muted', 'surface'],
            ['muted', 'surface_alt'],
            ['heading', 'background'],
            ['heading', 'surface'],
            ['heading', 'surface_alt'],
            ['on_accent', 'accent'],
            ['on_accent', 'accent_hover'],
            ['focus', 'background'],
            ['focus', 'surface'],
            ['sidebar_text', 'sidebar_hover'],
            ['sidebar_text', 'sidebar'],
            ['sidebar_muted', 'sidebar_hover'],
            ['sidebar_muted', 'sidebar'],
            ['danger', 'surface'],
            ['warning', 'surface'],
        ];

        foreach (self::THEMES as $key) {
            $this->assertArrayHasKey($key, $themes);
            $colors = $themes[$key]['colors'];

            foreach ($pairs as [$foreground, $background]) {
                $this->assertArrayHasKey($foreground, $colors, $key . ' is missing ' . $foreground . '.');
                $this->assertArrayHasKey($background, $colors, $key . ' is missing ' . $background . '.');
                $ratio = $this->contrastRatio($colors[$foreground], $colors[$background]);
                $this->assertGreaterThanOrEqual(
                    4.5,
                    $ratio,
                    sprintf('%s %s on %s has only %.2f:1 contrast.', $key, $foreground, $background, $ratio)
                );
            }
        }
    }

    public function test_new_themes_have_distinct_visual_signatures(): void
    {
        $themes = config('crm_themes.themes', []);
        $signatures = [];

        foreach (self::NEW_THEMES as $key) {
            $this->assertArrayHasKey($key, $themes);
            $theme = $themes[$key];
            $signatures[$key] = implode('|', [
                $theme['colors']['background'] ?? '',
                $theme['colors']['accent'] ?? '',
                $theme['ui']['radius'] ?? '',
                $theme['ui']['sidebar_width'] ?? '',
                $theme['ui']['nav_style'] ?? '',
                $theme['ui']['panel_style'] ?? '',
                $theme['ui']['table_row_height'] ?? '',
            ]);
        }

        $this->assertCount(10, array_unique($signatures), 'Each approved theme must have an independent visual signature.');
    }

    public function test_new_theme_accent_colors_are_not_near_duplicates(): void
    {
        $themes = config('crm_themes.themes', []);

        foreach (self::NEW_THEMES as $leftIndex => $leftKey) {
            for ($rightIndex = $leftIndex + 1; $rightIndex < count(self::NEW_THEMES); $rightIndex++) {
                $rightKey = self::NEW_THEMES[$rightIndex];
                $left = $this->rgbChannels($themes[$leftKey]['colors']['accent']);
                $right = $this->rgbChannels($themes[$rightKey]['colors']['accent']);
                $distance = sqrt(
                    (($left[0] - $right[0]) ** 2)
                    + (($left[1] - $right[1]) ** 2)
                    + (($left[2] - $right[2]) ** 2)
                );

                $this->assertGreaterThanOrEqual(
                    25,
                    $distance,
                    sprintf('%s and %s accent colors are too similar (%.1f RGB distance).', $leftKey, $rightKey, $distance)
                );
            }
        }
    }

    public function test_all_theme_control_boundaries_meet_non_text_contrast(): void
    {
        $themes = config('crm_themes.themes', []);

        foreach (self::THEMES as $key) {
            $colors = $themes[$key]['colors'];

            foreach (['surface', 'background', 'surface_alt'] as $background) {
                $ratio = $this->contrastRatio($colors['border_strong'], $colors[$background]);
                $this->assertGreaterThanOrEqual(
                    3,
                    $ratio,
                    sprintf('%s strong border on %s has only %.2f:1 contrast.', $key, $background, $ratio)
                );
            }
        }
    }

    public function test_crmui_form_controls_consume_the_strong_boundary_token(): void
    {
        $assets = file_get_contents(resource_path('views/partials/theme-assets.blade.php'));
        $themeCss = file_get_contents(public_path('css/common/crm-themes.css'));
        $visualCss = file_get_contents(public_path('css/crmui/visual-c.css'));

        $this->assertStringContainsString("--crmui-border-strong: {{ \$colors['border_strong'] }};", $assets);
        $this->assertStringContainsString("--crmui-vc-border-strong: {{ \$colors['border_strong'] }};", $assets);
        $this->assertStringContainsString('border-color: var(--crmui-border-strong) !important;', $themeCss);

        $formRuleStart = strpos($visualCss, 'body[data-ui-family="crmui"][data-visual-direction="c"] .crmui-input,');
        $formRuleEnd = strpos($visualCss, 'body[data-ui-family="crmui"][data-visual-direction="c"] .crmui-input::placeholder,');
        $surfaceRuleStart = strpos($visualCss, 'body[data-ui-family="crmui"][data-visual-direction="c"] .crmui-metric,');
        $surfaceRuleEnd = strpos($visualCss, 'body[data-ui-family="crmui"][data-visual-direction="c"] .crmui-metric {', $surfaceRuleStart + 1);

        $this->assertIsInt($formRuleStart);
        $this->assertIsInt($formRuleEnd);
        $this->assertIsInt($surfaceRuleStart);
        $this->assertIsInt($surfaceRuleEnd);

        $formRule = substr($visualCss, $formRuleStart, $formRuleEnd - $formRuleStart);
        $surfaceRule = substr($visualCss, $surfaceRuleStart, $surfaceRuleEnd - $surfaceRuleStart);

        $this->assertStringContainsString('border: 1px solid var(--crmui-vc-border-strong) !important;', $formRule);
        $this->assertStringContainsString('border: 1px solid var(--crmui-vc-border) !important;', $surfaceRule);
    }

    public function test_theme_catalog_exposes_complete_semantic_and_compatibility_tokens(): void
    {
        $themes = config('crm_themes.themes', []);
        $assets = file_get_contents(resource_path('views/partials/theme-assets.blade.php'));

        foreach (self::THEMES as $key) {
            $colors = $themes[$key]['colors'];

            foreach (['danger', 'danger_bg', 'danger_on', 'warning', 'warning_bg', 'warning_on'] as $token) {
                $this->assertArrayHasKey($token, $colors, $key . ' is missing the fixed ' . $token . ' semantic color.');
            }
        }

        foreach ([
            '--crm-on-accent',
            '--crm-danger-soft',
            '--crm-on-danger',
            '--crm-warning-soft',
            '--crm-on-warning',
            '--crm-success',
            '--crm-success-soft',
            '--crm-info',
            '--crm-online',
            '--front-success',
            '--front-info',
            '--front-online',
            '--admin-success',
            '--admin-info',
            '--admin-online',
            '--crmui-success',
            '--crmui-info',
            '--crmui-online',
        ] as $variable) {
            $this->assertStringContainsString($variable, $assets, $variable . ' is not mapped by the global catalog.');
        }

        $this->assertStringContainsString("--crm-success: {{ \$colors['accent'] }};", $assets);
        $this->assertStringContainsString("--crm-info: {{ \$colors['accent'] }};", $assets);
        $this->assertStringContainsString("--crm-online: {{ \$colors['accent'] }};", $assets);
    }

    public function test_global_theme_css_covers_components_states_and_responsive_behavior(): void
    {
        $css = file_get_contents(public_path('css/common/crm-themes.css'));

        foreach ([
            'html[data-crm-theme] body',
            'html[data-crm-theme] .crmui-sidebar',
            'html[data-crm-theme] .crmui-topbar',
            'html[data-crm-theme] .crmui-panel',
            'html[data-crm-theme] .crmui-metric',
            'html[data-crm-theme] .crmui-table-wrap',
            'html[data-crm-theme] .module-table-wrap',
            'html[data-crm-theme] .crmui-input',
            'html[data-crm-theme] .layui-form-select dl',
            'html[data-crm-theme] .crmui-button',
            'html[data-crm-theme] .layui-dropdown',
            'html[data-crm-theme] .layui-laypage a',
            'html[data-crm-theme] .crmui-badge',
            'html[data-crm-theme] .crmui-modal-panel',
            'html[data-crm-theme] .layui-layer',
            'html[data-crm-theme] [data-ui-state="loading"]',
            'html[data-crm-theme] [data-ui-state="empty"]',
            'html[data-crm-theme] [data-ui-state="error"]',
            'html[data-crm-theme] [data-ui-state="success"]',
            'html[data-crm-theme] [data-ui-state="unauthorized"]',
            'html[data-crm-theme] [data-ui-state="online"]',
            'html[data-crm-theme] :disabled',
            'html[data-crm-theme] [aria-disabled="true"]',
            ':focus-visible',
            '::-webkit-scrollbar-thumb',
            '@media (pointer: coarse)',
            '@media (prefers-reduced-motion: reduce)',
        ] as $coverage) {
            $this->assertStringContainsString($coverage, $css, $coverage . ' is missing from the global theme CSS.');
        }

        $this->assertMatchesRegularExpression('/\.crm-theme-picker select\s*\{[^}]*min-height:\s*44px/s', substr($css, strpos($css, '@media (pointer: coarse)')));
        $this->assertMatchesRegularExpression('/\.crmui-table-wrap,[^{]*\.module-table-wrap[^{]*\{[^}]*overflow-x:\s*auto/s', $css);
        $this->assertStringContainsString('border-inline-start-style: double;', $css, 'Online/success state needs a non-color semantic cue.');
        $this->assertStringContainsString('cursor: progress;', $css, 'Loading state needs a non-color semantic cue.');

        preg_match_all('/\b(?:transition|transition-duration)\s*:[^;}]*?(?<!\d)(\d+)ms/i', $css, $durations);
        $this->assertNotEmpty($durations[1], 'Theme transitions must declare an explicit duration.');
        foreach ($durations[1] as $duration) {
            $this->assertGreaterThanOrEqual(150, (int) $duration);
            $this->assertLessThanOrEqual(200, (int) $duration);
        }

        $this->assertDoesNotMatchRegularExpression('/#[0-9a-f]{3,8}\b|rgba?\(|hsla?\(/i', $css, 'Component CSS must not contain theme color literals.');
        $this->assertDoesNotMatchRegularExpression('/gradient|\borb\b|letter-spacing\s*:\s*-|font-size\s*:[^;]*(?:vw|vh|vmin|vmax)/i', $css);
    }

    public function test_each_new_theme_implements_navigation_and_panel_treatments(): void
    {
        $css = file_get_contents(public_path('css/common/crm-themes.css'));

        foreach (self::NEW_THEMES as $key) {
            $prefix = preg_quote('html[data-crm-theme="' . $key . '"]', '/');

            $this->assertMatchesRegularExpression(
                '/' . $prefix . '\s+\.crmui-nav-link\.is-active/s',
                $css,
                $key . ' does not implement its CrmUI active-navigation treatment.'
            );
            $this->assertMatchesRegularExpression(
                '/' . $prefix . '[^{]*(?:\.crmui-panel|\.crmui-panel-head)/s',
                $css,
                $key . ' does not implement its CrmUI panel treatment.'
            );
        }
    }

    public function test_crmui_auth_brand_consumes_each_theme_sidebar_tokens(): void
    {
        $themeCss = file_get_contents(public_path('css/common/crm-themes.css'));

        $this->assertStringContainsString('html[data-crm-theme] .crmui-auth-brand {', $themeCss);
        $this->assertStringContainsString('background: var(--crm-sidebar) !important;', $themeCss);
        $this->assertStringContainsString('color: var(--crm-sidebar-ink) !important;', $themeCss);
        $this->assertStringContainsString('color: var(--crm-sidebar-muted) !important;', $themeCss);
        $this->assertStringContainsString('background: var(--crm-sidebar-soft) !important;', $themeCss);
    }

    public function test_shared_theme_assets_and_picker_expose_the_full_catalog(): void
    {
        $assetsPath = resource_path('views/partials/theme-assets.blade.php');
        $pickerPath = resource_path('views/partials/theme-picker.blade.php');
        $cssPath = public_path('css/common/crm-themes.css');

        $this->assertFileExists($assetsPath);
        $this->assertFileExists($pickerPath);
        $this->assertFileExists($cssPath);

        $assets = file_get_contents($assetsPath);
        $picker = file_get_contents($pickerPath);
        $css = file_get_contents($cssPath);

        $this->assertStringContainsString('id="crm-theme-values"', $assets);
        $this->assertStringContainsString('type="application/json"', $assets);
        $this->assertStringNotContainsString('window.CRM_THEME_VALUES', $assets);
        $this->assertStringContainsString('js/shared/theme-sync.js', $assets);
        $this->assertStringContainsString('css/common/crm-themes.css', $assets);
        $this->assertStringContainsString('<select', $picker);
        $this->assertStringContainsString('data-crm-skin-select', $picker);
        $this->assertStringContainsString('.crm-ui-auth-body .auth-brand-copy h1', $css);
        $this->assertStringContainsString('color: var(--crm-sidebar-ink) !important;', $css);

        foreach (self::THEMES as $key) {
            $this->assertStringContainsString('html[data-crm-theme="' . $key . '"]', $css, $key . ' has no global CSS token block.');
        }
    }

    public function test_theme_values_are_safely_rendered_before_the_sync_script(): void
    {
        $originalThemes = config('crm_themes.themes', []);
        $maliciousThemeKey = '</script><script data-test="theme-injection">alert(\'theme\')</script>&';
        $themes = $originalThemes;
        $themes[$maliciousThemeKey] = $themes['light'];

        config(['crm_themes.themes' => $themes]);

        try {
            $html = view('partials.theme-assets')->render();
        } finally {
            config(['crm_themes.themes' => $originalThemes]);
        }

        $valuesPrefix = '<script type="application/json" id="crm-theme-values">';
        $valuesPosition = strpos($html, $valuesPrefix);
        $syncScriptPosition = strpos($html, 'js/shared/theme-sync.js');
        $encodingFlags = JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT;
        $expectedJson = json_encode(array_keys($themes), $encodingFlags);
        $encodedMaliciousThemeKey = json_encode($maliciousThemeKey, $encodingFlags);

        $this->assertIsInt($valuesPosition, 'The theme catalog JSON block is not rendered.');
        $this->assertIsInt($syncScriptPosition, 'The shared theme sync script is not loaded.');
        $this->assertLessThan($syncScriptPosition, $valuesPosition, 'Theme values must be rendered before the sync script runs.');
        $this->assertStringContainsString($valuesPrefix . $expectedJson . '</script>', $html);
        $this->assertStringContainsString($valuesPrefix . '["light"', $html, 'The JSON array must retain JSON quotes.');
        $this->assertStringNotContainsString($valuesPrefix . '[&quot;', $html, 'The JSON array must not be HTML-escaped.');
        $this->assertStringContainsString($encodedMaliciousThemeKey, $html);
        $this->assertStringNotContainsString($maliciousThemeKey, $html, 'Rendered output contains a script-closing injection payload.');
    }

    public function test_every_complete_blade_entrypoint_loads_assets_and_a_picker(): void
    {
        foreach (self::HTML_ENTRYPOINTS as $relativePath) {
            $blade = file_get_contents(base_path($relativePath));
            $themePosition = strpos($blade, 'partials.theme-assets');
            $stylePositions = array_filter([
                strrpos($blade, '<link rel="stylesheet"'),
                strrpos($blade, "@yield('styles')"),
                strrpos($blade, '</style>'),
            ], static fn ($position) => $position !== false);

            $this->assertStringContainsString('partials.theme-assets', $blade, $relativePath . ' does not load shared theme assets.');
            $this->assertStringContainsString('partials.theme-picker', $blade, $relativePath . ' does not expose the shared theme picker.');
            $this->assertGreaterThan(max($stylePositions), $themePosition, $relativePath . ' must load shared theme CSS last.');
        }
    }

    public function test_four_main_layouts_expose_the_shared_theme_picker(): void
    {
        foreach (self::MAIN_LAYOUTS as $relativePath) {
            $blade = file_get_contents(base_path($relativePath));

            $this->assertStringContainsString(
                'partials.theme-picker',
                $blade,
                $relativePath . ' does not expose the shared theme picker.'
            );
        }
    }

    public function test_every_admin_and_front_blade_resolves_to_a_themed_entrypoint(): void
    {
        $roots = [
            'admin_crmui' => 'resources/admin/crmui',
            'admin_layui' => 'resources/admin/layui',
            'front_crmui' => 'resources/front/crmui',
            'front_layui' => 'resources/front/layui',
        ];
        $partialFiles = [
            'resources/admin/crmui/partials/module-page.blade.php',
            'resources/admin/layui/layouts/header.blade.php',
            'resources/admin/layui/layouts/sidebar.blade.php',
            'resources/front/crmui/partials/module-page.blade.php',
            'resources/front/layui/partials/module-page.blade.php',
        ];
        $bladeFiles = [];

        foreach ($roots as $root) {
            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator(base_path($root), \FilesystemIterator::SKIP_DOTS)
            );

            foreach ($iterator as $file) {
                if (! $file->isFile() || substr($file->getFilename(), -10) !== '.blade.php') {
                    continue;
                }

                $bladeFiles[] = str_replace('\\', '/', substr($file->getPathname(), strlen(base_path()) + 1));
            }
        }

        sort($bladeFiles);
        $this->assertCount(182, $bladeFiles, 'The theme coverage audit must account for every current admin/front Blade file.');

        foreach ($bladeFiles as $relativePath) {
            $blade = file_get_contents(base_path($relativePath));

            if (strpos($blade, '<!DOCTYPE html>') !== false) {
                $this->assertStringContainsString('partials.theme-assets', $blade, $relativePath . ' has no theme assets.');
                $this->assertStringContainsString('partials.theme-picker', $blade, $relativePath . ' has no theme picker.');
                continue;
            }

            preg_match('/@extends\(([^\r\n]+)\)/', $blade, $extendsMatch);
            preg_match_all(
                "/['\"]((?:admin|front)_(?:crmui|layui)::[^'\"]+)['\"]/",
                $extendsMatch[1] ?? '',
                $matches
            );
            $layouts = array_values(array_unique($matches[1] ?? []));

            if ($layouts !== []) {
                foreach ($layouts as $layout) {
                    [$namespace, $view] = explode('::', $layout, 2);
                    $this->assertArrayHasKey($namespace, $roots, $relativePath . ' uses an unknown theme namespace.');
                    $layoutPath = $roots[$namespace] . '/' . str_replace('.', '/', $view) . '.blade.php';
                    $this->assertFileExists(base_path($layoutPath), $relativePath . ' references a missing layout.');
                    $layoutBlade = file_get_contents(base_path($layoutPath));
                    $this->assertStringContainsString('partials.theme-assets', $layoutBlade, $layoutPath . ' has no theme assets.');
                    $this->assertStringContainsString('partials.theme-picker', $layoutBlade, $layoutPath . ' has no theme picker.');
                }
                continue;
            }

            $this->assertContains($relativePath, $partialFiles, $relativePath . ' is neither a themed page nor an approved partial.');
        }
    }

    public function test_admin_and_front_blades_do_not_hardcode_color_literals(): void
    {
        foreach (['resources/admin', 'resources/front'] as $root) {
            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator(base_path($root), \FilesystemIterator::SKIP_DOTS)
            );

            foreach ($iterator as $file) {
                if (! $file->isFile() || substr($file->getFilename(), -10) !== '.blade.php') {
                    continue;
                }

                $blade = file_get_contents($file->getPathname());
                preg_match_all('/#[0-9a-f]{3,8}\b|rgba?\(|hsla?\(/i', $blade, $matches);
                $relativePath = str_replace('\\', '/', substr($file->getPathname(), strlen(base_path()) + 1));

                $this->assertSame([], $matches[0], $relativePath . ' must use semantic theme tokens instead of color literals.');
            }
        }
    }

    public function test_theme_sync_is_the_only_crmui_theme_state_source(): void
    {
        $sync = file_get_contents(public_path('js/shared/theme-sync.js'));

        $changeListenerStart = strpos($sync, "document.addEventListener('change'");
        $nextListenerStart = strpos($sync, "document.addEventListener('click'", $changeListenerStart ?: 0);

        $this->assertIsInt($changeListenerStart, 'The shared theme script does not listen for picker changes.');
        $this->assertIsInt($nextListenerStart, 'The shared theme change listener boundary could not be found.');

        $changeListener = substr($sync, $changeListenerStart, $nextListenerStart - $changeListenerStart);

        $this->assertStringContainsString("document.getElementById('crm-theme-values')", $sync);
        $this->assertStringContainsString("JSON.parse(catalog.textContent || '[]')", $sync);
        $this->assertStringContainsString("hasAttribute('data-crm-skin-select')", $changeListener);
        $this->assertStringContainsString('window.CrmTheme.set(target.value)', $changeListener);
        $this->assertStringContainsString('data-crmui-theme-mode', $sync);
        $this->assertStringNotContainsString('[data-theme-select]', $sync, 'Theme controls must use the approved shared picker attribute only.');

        foreach (['admin.js', 'front.js'] as $script) {
            $content = file_get_contents(public_path('js/apps/crmui/' . $script));
            $this->assertStringNotContainsString('crmui_theme', $content, $script . ' still owns an independent theme state.');
            $this->assertStringNotContainsString('data-crmui-theme]', $content, $script . ' still binds the obsolete binary theme button.');
        }
    }

    public function test_shared_theme_sync_uses_the_injected_catalog_and_canonical_write_keys(): void
    {
        $sync = file_get_contents(public_path('js/shared/theme-sync.js'));

        preg_match('/var fallbackValues = \[(.*?)\];/s', $sync, $fallbackMatch);
        preg_match_all("/'([^']+)'/", $fallbackMatch[1] ?? '', $fallbackValues);
        preg_match('/var writeKeys = \[(.*?)\];/s', $sync, $writeKeysMatch);
        preg_match_all("/'([^']+)'/", $writeKeysMatch[1] ?? '', $writeKeys);

        $this->assertSame(self::THEMES, $fallbackValues[1] ?? [], 'The static fallback must expose all 15 approved themes.');
        $this->assertSame(
            ['front_theme', 'crm_theme', 'crm_color_mode'],
            $writeKeys[1] ?? [],
            'Theme persistence must only write the three canonical keys.'
        );
        $this->assertStringContainsString("document.getElementById('crm-theme-values')", $sync);
        $this->assertStringContainsString("JSON.parse(catalog.textContent || '[]')", $sync);
        $this->assertStringContainsString('fallbackValues.slice()', $sync, 'The sync script must retain the static fallback catalog.');
    }

    public function test_shared_theme_sync_falls_back_to_light_for_explicit_invalid_values(): void
    {
        $sync = file_get_contents(public_path('js/shared/theme-sync.js'));

        $this->assertStringContainsString("return normalize(value) || 'light';", $sync, 'Explicit invalid themes must resolve to light.');
    }

    public function test_shared_theme_sync_updates_both_dom_roots_and_clears_old_theme_classes(): void
    {
        $sync = file_get_contents(public_path('js/shared/theme-sync.js'));

        $this->assertSame(2, substr_count($sync, "setAttribute('data-front-theme', theme)"));
        $this->assertSame(2, substr_count($sync, "setAttribute('data-crm-theme', theme)"));
        $this->assertSame(2, substr_count($sync, "setAttribute('data-crmui-theme-mode', mode)"));
        $this->assertStringContainsString('(?:crm-skin|skin|theme)-[a-z0-9-]+', $sync, 'Old skin/theme classes must be removed before applying the current class.');
    }

    public function test_shared_theme_sync_broadcasts_and_delegates_without_parent_dom_access(): void
    {
        $sync = file_get_contents(public_path('js/shared/theme-sync.js'));

        $this->assertStringContainsString("new CustomEvent('crm:theme-change'", $sync);
        $this->assertStringContainsString("window.addEventListener('storage'", $sync);
        $this->assertStringContainsString('window.parent !== window', $sync);
        $this->assertStringContainsString('window.parent.CrmTheme.get() !== theme', $sync);
        $this->assertStringNotContainsString('window.parent.document', $sync, 'Theme sync must delegate through the parent API instead of mutating the shell DOM.');
    }

    public function test_visual_c_surfaces_delegate_colors_to_the_global_theme_catalog(): void
    {
        $assets = file_get_contents(resource_path('views/partials/theme-assets.blade.php'));

        $this->assertStringContainsString('body[data-ui-family="layui"][data-visual-direction="c"]', $assets);
        $this->assertStringContainsString('body[data-ui-family="crmui"][data-visual-direction="c"]', $assets);

        foreach ([
            '--layui-vc-bg',
            '--layui-vc-surface',
            '--layui-vc-text',
            '--layui-vc-muted',
            '--layui-vc-accent',
            '--layui-vc-on-accent',
            '--layui-vc-sidebar-bg',
            '--crmui-vc-bg',
            '--crmui-vc-surface',
            '--crmui-vc-text',
            '--crmui-vc-muted',
            '--crmui-vc-accent',
            '--crmui-vc-on-accent',
            '--crmui-vc-sidebar-bg',
            '--crm-color-scheme',
        ] as $variable) {
            $this->assertStringContainsString($variable, $assets, $variable . ' is not mapped by the global catalog.');
        }

        foreach (['layui/visual-c.css', 'crmui/visual-c.css'] as $relativePath) {
            $css = file_get_contents(public_path('css/' . $relativePath));
            preg_match_all(
                '/^\s*(?:color|background(?:-color)?|border(?:-color)?|box-shadow)\s*:\s*(?:#[0-9a-f]|rgba?\()/im',
                $css,
                $matches
            );

            $this->assertSame([], $matches[0], $relativePath . ' must consume theme variables for rendered colors.');
            $this->assertStringNotContainsString('color-scheme: dark;', $css, $relativePath . ' forces dark browser controls in light themes.');
        }
    }

    public function test_dashboards_use_the_shared_compact_picker_without_legacy_theme_controls(): void
    {
        foreach (['index.blade.php', 'index_v2.blade.php'] as $file) {
            $blade = file_get_contents(resource_path('front/layui/dashboard/' . $file));

            $this->assertStringContainsString("@section('frame-theme-picker-provided', '1')", $blade);
            $this->assertStringContainsString("@include('partials.theme-picker', ['themePickerCompact' => true])", $blade);
            $this->assertStringNotContainsString("'themePickerId' =>", $blade);

            foreach ([
                'data-dashboard-theme-option',
                'data-dashboard-theme-current',
                'data-dashboard-switch="theme"',
                'dashboard-theme-swatch',
                'data-lucide="palette"',
            ] as $legacyThemeControl) {
                $this->assertStringNotContainsString($legacyThemeControl, $blade, $file . ' still contains ' . $legacyThemeControl);
            }
        }
    }

    public function test_all_theme_labels_and_ui_theme_heading_exist_in_all_frontend_locales(): void
    {
        $zh = require base_path('resources/lang/zh-CN/front.php');
        $en = require base_path('resources/lang/en/front.php');

        $this->assertSame('界面主题', $zh['skin_mode'] ?? null);
        $this->assertSame('UI Theme', $en['skin_mode'] ?? null);

        foreach (self::THEMES as $key) {
            $labelKey = 'theme_' . str_replace('-', '_', $key);

            $this->assertArrayHasKey($labelKey, $zh, 'Missing Simplified Chinese label: ' . $labelKey);
            $this->assertArrayHasKey($labelKey, $en, 'Missing English label: ' . $labelKey);
            $this->assertNotSame($labelKey, $zh[$labelKey]);
            $this->assertNotSame($labelKey, $en[$labelKey]);
        }

        foreach ([
            'public/js/shared/lang/common/zh-CN.js' => "skin_mode: '界面主题'",
            'public/js/shared/lang/common/en.js' => "skin_mode: 'UI Theme'",
        ] as $relativePath => $heading) {
            $locale = file_get_contents(base_path($relativePath));

            $this->assertStringContainsString($heading, $locale, $relativePath . ' has the wrong UI theme heading.');
            foreach (self::THEMES as $key) {
                $labelKey = 'theme_' . str_replace('-', '_', $key);
                $this->assertMatchesRegularExpression(
                    '/\b' . preg_quote($labelKey, '/') . '\s*:\s*[\'\"][^\'\"]+[\'\"]/',
                    $locale,
                    $relativePath . ' is missing ' . $labelKey
                );
            }
        }
    }

    public function test_layui_dashboard_theme_state_is_owned_by_shared_sync(): void
    {
        $dashboardScript = file_get_contents(public_path('js/apps/front/layui/pages.js'));
        $themeSyncScript = file_get_contents(public_path('js/shared/theme-sync.js'));

        $dashboardStart = strpos($dashboardScript, "registry['dashboard/index']");
        $dashboardEnd = strpos($dashboardScript, "\n    registry['", $dashboardStart + 1);
        $dashboard = substr($dashboardScript, $dashboardStart, $dashboardEnd - $dashboardStart);

        foreach ([
            'data-dashboard-theme-option',
            'data-dashboard-theme-current',
            'applyDashboardTheme',
            'currentDashboardTheme',
            'themeText',
            "localStorage.setItem('front_theme'",
            "localStorage.setItem('crm_theme'",
            'window.parent.CrmTheme',
        ] as $privateThemeState) {
            $this->assertStringNotContainsString($privateThemeState, $dashboard, 'Dashboard still owns theme state via ' . $privateThemeState);
        }

        $this->assertStringContainsString("window.addEventListener('crm:theme-change'", $dashboard);
        $this->assertStringContainsString('scheduleChartResize();', $dashboard);
        $this->assertStringContainsString("hasAttribute('data-crm-skin-select')", $themeSyncScript);
        $this->assertStringContainsString('window.parent.CrmTheme.set(theme)', $themeSyncScript);
    }

    private function contrastRatio(string $foreground, string $background): float
    {
        $light = $this->relativeLuminance($foreground);
        $dark = $this->relativeLuminance($background);

        if ($dark > $light) {
            [$light, $dark] = [$dark, $light];
        }

        return ($light + 0.05) / ($dark + 0.05);
    }

    private function relativeLuminance(string $hex): float
    {
        $channels = array_map(static fn (int $channel): float => $channel / 255, $this->rgbChannels($hex));

        $channels = array_map(static function (float $channel): float {
            return $channel <= 0.04045
                ? $channel / 12.92
                : (($channel + 0.055) / 1.055) ** 2.4;
        }, $channels);

        return (0.2126 * $channels[0]) + (0.7152 * $channels[1]) + (0.0722 * $channels[2]);
    }

    /** @return array{0:int,1:int,2:int} */
    private function rgbChannels(string $hex): array
    {
        $hex = ltrim($hex, '#');
        $this->assertMatchesRegularExpression('/^[0-9a-fA-F]{6}$/', $hex, 'Theme colors must use six-digit hex values.');

        return [
            hexdec(substr($hex, 0, 2)),
            hexdec(substr($hex, 2, 2)),
            hexdec(substr($hex, 4, 2)),
        ];
    }
}
