<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/17
 * Time: 09:56
 */

/**
 * VisualCFoundationContractTest
 *
 * 文件功能：
 * - 验证视觉 C 基础层契约：每个 UI 家族隔离资产、布局只加载本家族资产、前台框架仅一个主题选择器、响应式侧栏与遮罩、紧凑页头与平板布局、Ajax 遮罩隐藏与参考页路由边界。
 * - 输入：路由、控制器、Blade/JS、迁移等项目源码文本；输出：PHPUnit 断言结果。
 * - 明确不负责：不覆盖 HTTP 运行时业务流与 MT4 真实网关同步（由集成与功能测试锁定）。
 */

declare(strict_types=1);

namespace Tests\Feature;

use Tests\TestCase;

final class VisualCFoundationContractTest extends TestCase
{
    public function test_each_ui_family_has_an_isolated_visual_c_asset(): void
    {
        $layui = $this->source('public/css/layui/visual-c.css');
        $crmui = $this->source('public/css/crmui/visual-c.css');

        foreach (['#171717', '#242424', '#3A3A3A', '#F5F5F5', '#A3A3A3', '#F2C94C', '#34A853', '#EF5350', '#4DA3FF'] as $color) {
            $this->assertStringContainsString($color, $layui);
            $this->assertStringContainsString($color, $crmui);
        }

        $this->assertStringContainsString('[data-ui-family="layui"]', $layui);
        $this->assertStringNotContainsString('[data-ui-family="crmui"]', $layui);
        $this->assertStringContainsString('[data-ui-family="crmui"]', $crmui);
        $this->assertStringNotContainsString('[data-ui-family="layui"]', $crmui);
        $this->assertStringNotContainsString('linear-gradient', $layui);
        $this->assertStringNotContainsString('radial-gradient', $layui);
        $this->assertStringNotContainsString('linear-gradient', $crmui);
        $this->assertStringNotContainsString('radial-gradient', $crmui);
    }

    public function test_layouts_load_only_their_family_visual_c_asset(): void
    {
        $layouts = [
            'resources/front/layui/layouts/app.blade.php' => ['/css/layui/visual-c.css', '/css/crmui/visual-c.css', 'layui', 'front'],
            'resources/admin/layui/layouts/app.blade.php' => ['/css/layui/visual-c.css', '/css/crmui/visual-c.css', 'layui', 'admin'],
            'resources/front/crmui/layouts/app.blade.php' => ['/css/crmui/visual-c.css', '/css/layui/visual-c.css', 'crmui', 'front'],
            'resources/admin/crmui/layouts/app.blade.php' => ['/css/crmui/visual-c.css', '/css/layui/visual-c.css', 'crmui', 'admin'],
        ];

        foreach ($layouts as $path => [$ownAsset, $foreignAsset, $family, $surface]) {
            $source = $this->source($path);
            $this->assertStringContainsString($ownAsset, $source);
            $this->assertStringNotContainsString($foreignAsset, $source);
            if ($family === 'crmui' && strpos($source, 'data-ui-family="{{ $renderFamily }}"') !== false) {
                $this->assertStringContainsString(
                    'data-crmui-render-family="{{ $renderFamily }}"',
                    $source,
                    $path . ' must expose the validated CrmUI/Naive render family.'
                );
            } else {
                $this->assertStringContainsString('data-ui-family="' . $family . '"', $source);
            }
            $this->assertStringContainsString('data-ui-surface="' . $surface . '"', $source);
            $this->assertStringContainsString('data-visual-direction="c"', $source);
        }
    }

    public function test_front_layui_frames_render_exactly_one_theme_picker(): void
    {
        foreach ([
            '/front/dashboard?frame=1',
            '/front/account/balance?frame=1',
        ] as $uri) {
            $html = $this->get($uri)->assertOk()->getContent();

            $this->assertSame(
                1,
                substr_count($html, 'data-crm-skin-select'),
                $uri . ' must render exactly one theme picker control.'
            );
            $this->assertSame(
                1,
                substr_count($html, 'id="crm-theme-picker"'),
                $uri . ' must render the default theme picker ID exactly once.'
            );
        }

        foreach ([
            'resources/front/layui/dashboard/index.blade.php',
            'resources/front/layui/dashboard/index_v2.blade.php',
        ] as $path) {
            $this->assertStringContainsString(
                "@section('frame-theme-picker-provided'",
                $this->source($path),
                $path . ' must declare its page-owned frame theme picker.'
            );
        }
    }

    public function test_visual_c_integrates_with_the_final_global_theme_layer(): void
    {
        $violations = [];
        $layouts = [
            'resources/front/layui/layouts/app.blade.php' => ['/css/layui/visual-c.css', '/css/crmui/visual-c.css'],
            'resources/admin/layui/layouts/app.blade.php' => ['/css/layui/visual-c.css', '/css/crmui/visual-c.css'],
            'resources/front/crmui/layouts/app.blade.php' => ['/css/crmui/visual-c.css', '/css/layui/visual-c.css'],
            'resources/admin/crmui/layouts/app.blade.php' => ['/css/crmui/visual-c.css', '/css/layui/visual-c.css'],
        ];

        foreach ($layouts as $path => [$ownAsset, $foreignAsset]) {
            $source = $this->source($path);
            $themeAssetsPosition = strpos($source, "@include('partials.theme-assets')");
            $pageStylesPosition = strpos($source, "@yield('styles')");
            $visualCPosition = strpos($source, $ownAsset);
            $headEndPosition = $themeAssetsPosition === false ? false : strpos($source, '</head>', $themeAssetsPosition);

            if ($themeAssetsPosition === false || $pageStylesPosition === false || $visualCPosition === false
                || ! ($pageStylesPosition < $visualCPosition && $visualCPosition < $themeAssetsPosition)) {
                $violations[] = $path . ' must load page styles, then Visual C, then theme-assets';
            }
            if (substr_count($source, $ownAsset) !== 1 || str_contains($source, $foreignAsset)) {
                $violations[] = $path . ' must load exactly one Visual C asset from its own family';
            }
            if ($themeAssetsPosition === false || $headEndPosition === false) {
                $violations[] = $path . ' must close head after theme-assets';
            } else {
                $afterThemeAssetsPosition = $themeAssetsPosition + strlen("@include('partials.theme-assets')");
                $afterThemeAssets = substr(
                    $source,
                    $afterThemeAssetsPosition,
                    $headEndPosition - $afterThemeAssetsPosition
                );
                if (preg_match('/rel\s*=\s*["\']stylesheet["\']|<style\b|@include\s*\(|@(?:yield|stack)\s*\(\s*["\']styles["\']\s*\)/i', $afterThemeAssets) === 1) {
                    $violations[] = $path . ' must not emit CSS after theme-assets';
                }
            }
        }

        $crmui = $this->source('public/css/crmui/visual-c.css');
        $rootPosition = strpos($crmui, ':root');
        $rootBlockStart = $rootPosition === false ? false : strpos($crmui, '{', $rootPosition);
        $rootBlockEnd = $rootBlockStart === false ? false : strpos($crmui, '}', $rootBlockStart);
        $rootBlock = $rootBlockStart === false || $rootBlockEnd === false
            ? ''
            : substr($crmui, $rootBlockStart + 1, $rootBlockEnd - $rootBlockStart - 1);
        $selector = 'body[data-ui-family="crmui"][data-visual-direction="c"]';
        $selectorPosition = strpos($crmui, $selector);
        $blockStart = $selectorPosition === false ? false : strpos($crmui, '{', $selectorPosition);
        $blockEnd = $blockStart === false ? false : strpos($crmui, '}', $blockStart);
        $firstScopedBlock = $blockStart === false || $blockEnd === false
            ? ''
            : substr($crmui, $blockStart + 1, $blockEnd - $blockStart - 1);

        $fallbacks = [
            '--crmui-vc-bg: #171717;',
            '--crmui-vc-surface: #242424;',
            '--crmui-vc-surface-raised: #2C2C2C;',
            '--crmui-vc-border: #3A3A3A;',
            '--crmui-vc-text: #F5F5F5;',
            '--crmui-vc-muted: #A3A3A3;',
            '--crmui-vc-accent: #F2C94C;',
            '--crmui-vc-success: #34A853;',
            '--crmui-vc-danger: #EF5350;',
            '--crmui-vc-info: #4DA3FF;',
            '--crmui-vc-on-accent: #171717;',
            '--crmui-vc-sidebar-bg: #111111;',
            '--crmui-vc-scrim: rgba(0, 0, 0, .7);',
            '--crmui-vc-shadow-color: rgba(0, 0, 0, .46);',
            '--crmui-vc-radius: 6px;',
        ];
        foreach ($fallbacks as $declaration) {
            if (! str_contains($rootBlock, $declaration)) {
                $violations[] = 'CrmUI :root must retain fallback ' . $declaration;
            }
        }
        if (preg_match('/--crmui-vc-[a-z0-9-]+\s*:/i', $firstScopedBlock) === 1) {
            $violations[] = 'CrmUI body scope must inherit --crmui-vc-* tokens from the global theme layer';
        }
        foreach ([
            '--crmui-bg: var(--crmui-vc-bg);',
            '--crmui-surface: var(--crmui-vc-surface);',
            '--crmui-surface-2: var(--crmui-vc-surface-raised);',
            '--crmui-ink: var(--crmui-vc-text);',
            '--crmui-muted: var(--crmui-vc-muted);',
            '--crmui-subtle: var(--crmui-vc-muted);',
            '--crmui-border: var(--crmui-vc-border);',
            '--crmui-primary: var(--crmui-vc-accent);',
            '--crmui-primary-ink: var(--crmui-vc-on-accent);',
            '--crmui-accent: var(--crmui-vc-info);',
            '--crmui-warning: var(--crmui-vc-accent);',
            '--crmui-danger: var(--crmui-vc-danger);',
            '--crmui-success: var(--crmui-vc-success);',
            '--crmui-info: var(--crmui-vc-info);',
            '--crmui-radius: var(--crmui-vc-radius);',
        ] as $mapping) {
            if (! str_contains($firstScopedBlock, $mapping)) {
                $violations[] = 'CrmUI body scope must retain mapping ' . $mapping;
            }
        }
        $onAccentColor = 'color: var(--crmui-vc-on-accent) !important;';
        if (substr_count($crmui, $onAccentColor) !== 2) {
            $violations[] = 'CrmUI Visual C must use ' . $onAccentColor . ' exactly twice';
        }
        if (preg_match('/color\s*:\s*var\s*\(\s*--crmui-vc-bg\s*\)\s*(?:!\s*important\s*)?;/i', $crmui) === 1) {
            $violations[] = 'CrmUI Visual C must not use --crmui-vc-bg as an emphasized foreground color';
        }

        $normalizeSelector = static fn (string $selector): string => trim((string) preg_replace('/\s+/', ' ', $selector));
        $ruleContaining = static function (string $css, string $selector) use ($normalizeSelector): array {
            preg_match_all(
                '/(?<header>[^{}]+)\{(?<declarations>[^{}]*)\}/s',
                $css,
                $rules,
                PREG_SET_ORDER
            );
            $target = $normalizeSelector($selector);

            foreach ($rules as $rule) {
                $members = array_map($normalizeSelector, explode(',', $rule['header']));
                if (in_array($target, $members, true)) {
                    return [
                        'members' => $members,
                        'declarations' => $rule['declarations'],
                    ];
                }
            }

            return ['members' => [], 'declarations' => ''];
        };
        $onAccentDeclaration = '/color\s*:\s*var\s*\(\s*--crmui-vc-on-accent\s*\)\s*!\s*important\s*;/i';
        $brandSelector = 'body[data-ui-family="crmui"][data-visual-direction="c"] .crmui-brand-mark';
        $brandRule = $ruleContaining($crmui, $brandSelector);
        if ($brandRule['declarations'] === '' || preg_match($onAccentDeclaration, $brandRule['declarations']) !== 1) {
            $violations[] = 'CrmUI brand mark rule must consume --crmui-vc-on-accent';
        }

        $primaryButtonSelector = 'body[data-ui-family="crmui"][data-visual-direction="c"] .crmui-button.is-primary';
        $primaryToolButtonSelector = 'body[data-ui-family="crmui"][data-visual-direction="c"] .crmui-tool-button.is-primary';
        $primaryButtonRule = $ruleContaining($crmui, $primaryButtonSelector);
        if ($primaryButtonRule['declarations'] === ''
            || ! in_array($normalizeSelector($primaryToolButtonSelector), $primaryButtonRule['members'], true)
            || preg_match($onAccentDeclaration, $primaryButtonRule['declarations']) !== 1) {
            $violations[] = 'CrmUI primary button and tool button shared rule must consume --crmui-vc-on-accent';
        }

        $this->assertSame([], $violations, implode(PHP_EOL, $violations));
    }

    public function test_foundations_cover_required_components_states_and_breakpoints(): void
    {
        $layui = $this->source('public/css/layui/visual-c.css');
        $crmui = $this->source('public/css/crmui/visual-c.css');

        foreach (['.layui-layout-admin', '.layui-table', '.layui-form', '.layui-layer', '[data-ui-state="loading"]', '[data-ui-state="empty"]', '[data-ui-state="error"]', '[data-ui-state="success"]', '[aria-disabled="true"]', '@media (max-width: 768px)', '@media (max-width: 480px)'] as $needle) {
            $this->assertStringContainsString($needle, $layui);
        }
        foreach (['.crmui-shell', '.crmui-table', '.crmui-form', '.crmui-modal', '[data-ui-state="loading"]', '[data-ui-state="empty"]', '[data-ui-state="error"]', '[data-ui-state="success"]', '[aria-disabled="true"]', '@media (max-width: 768px)', '@media (max-width: 480px)'] as $needle) {
            $this->assertStringContainsString($needle, $crmui);
        }
    }

    public function test_crm_polish_descendant_selectors_remain_scoped_to_front_shells(): void
    {
        $source = $this->source('public/css/common/crm-design-system.css');
        $markerPosition = strpos($source, 'crm-polish');
        $polish = $markerPosition === false ? '' : substr($source, $markerPosition);
        $violations = [];

        if ($markerPosition === false) {
            $violations[] = 'crm-polish enhancement layer marker must exist';
        }
        if (preg_match('/^\s*\.crm-ui-front-shell\s*,\s*\.legacy-big-agent-shell\s+[^,{\r\n]+/m', $polish, $match) === 1) {
            $violations[] = 'crm-polish contains an unscoped front selector: ' . trim($match[0]);
        }
        if (preg_match('/^\s*html\[data-front-theme="dark"\]\s+\.crm-ui-front-shell\s*,\s*\.legacy-big-agent-shell\s+[^,{\r\n]+/m', $polish, $match) === 1) {
            $violations[] = 'crm-polish contains an unscoped dark selector: ' . trim($match[0]);
        }
        $this->assertSame([], $violations, implode(PHP_EOL, $violations));

        $normalSuffixes = [
            '.crm-ui-front-shell' => [],
            '.legacy-big-agent-shell' => [],
        ];
        preg_match_all(
            '/^\s*(?<scope>\.crm-ui-front-shell|\.legacy-big-agent-shell)\s+(?<suffix>[^,{\r\n]+?)\s*(?:,|\{)\s*$/m',
            $polish,
            $normalMatches,
            PREG_SET_ORDER
        );
        foreach ($normalMatches as $match) {
            $normalSuffixes[$match['scope']][] = trim($match['suffix']);
        }
        $normalFrontSuffixes = $normalSuffixes['.crm-ui-front-shell'];
        $normalLegacySuffixes = $normalSuffixes['.legacy-big-agent-shell'];
        sort($normalFrontSuffixes);
        sort($normalLegacySuffixes);

        $darkSuffixes = [
            '.crm-ui-front-shell' => [],
            '.legacy-big-agent-shell' => [],
        ];
        preg_match_all(
            '/^\s*html\[data-front-theme="dark"\]\s+(?<scope>\.crm-ui-front-shell|\.legacy-big-agent-shell)\s+(?<suffix>[^,{\r\n]+?)\s*(?:,|\{)\s*$/m',
            $polish,
            $darkMatches,
            PREG_SET_ORDER
        );
        foreach ($darkMatches as $match) {
            $darkSuffixes[$match['scope']][] = trim($match['suffix']);
        }
        $darkFrontSuffixes = $darkSuffixes['.crm-ui-front-shell'];
        $darkLegacySuffixes = $darkSuffixes['.legacy-big-agent-shell'];
        sort($darkFrontSuffixes);
        sort($darkLegacySuffixes);

        $this->assertCount(51, $normalFrontSuffixes, 'crm-polish front scope must retain exactly 51 normal descendant selectors');
        $this->assertCount(51, $normalLegacySuffixes, 'crm-polish legacy scope must retain exactly 51 normal descendant selectors');
        $this->assertSame($normalFrontSuffixes, $normalLegacySuffixes, 'crm-polish normal front and legacy descendant suffix sets must match exactly');
        $this->assertCount(8, $darkFrontSuffixes, 'crm-polish front scope must retain exactly 8 dark descendant selectors');
        $this->assertCount(8, $darkLegacySuffixes, 'crm-polish legacy scope must retain exactly 8 dark descendant selectors');
        $this->assertSame($darkFrontSuffixes, $darkLegacySuffixes, 'crm-polish dark front and legacy descendant suffix sets must match exactly');
    }

    public function test_layui_mobile_sidebar_uses_explicit_accessible_state(): void
    {
        $frontLayout = $this->source('resources/front/layui/layouts/app.blade.php');
        $adminLayout = $this->source('resources/admin/layui/layouts/app.blade.php');
        $frontScript = $this->source('public/js/apps/front/layui/layout.js');
        $adminScript = $this->source('public/js/apps/admin/layui/layout.js');

        foreach ([$frontLayout, $adminLayout] as $layout) {
            $this->assertStringContainsString('data-layui-sidebar-toggle', $layout);
            $this->assertStringContainsString('aria-controls=', $layout);
            $this->assertStringContainsString('aria-expanded="false"', $layout);
            $this->assertStringContainsString('data-layui-sidebar-dismiss', $layout);
        }
        foreach ([$frontScript, $adminScript] as $script) {
            $this->assertStringContainsString('is-sidebar-open', $script);
            $this->assertStringContainsString('is-sidebar-collapsed', $script);
            $this->assertStringContainsString('aria-expanded', $script);
            $this->assertStringContainsString("matchMedia('(max-width: 768px)')", $script);
        }
    }

    public function test_layui_sidebar_runtime_is_published_with_the_visual_c_cache_version(): void
    {
        $frontLayout = $this->source('resources/front/layui/layouts/app.blade.php');
        $adminLayout = $this->source('resources/admin/layui/layouts/app.blade.php');

        $this->assertStringContainsString(
            "asset('/js/apps/front/layui/layout.js') }}?v=2026080801",
            $frontLayout
        );
        $this->assertStringContainsString(
            "asset('/js/apps/admin/layui/layout.js') }}?v=2026080801",
            $adminLayout
        );
    }

    public function test_layui_compact_header_reserves_a_non_overlapping_toggle_region(): void
    {
        $layui = $this->source('public/css/layui/visual-c.css');

        $this->assertMatchesRegularExpression(
            '~@media\s*\(max-width:\s*480px\)\s*\{'
            . '(?:(?!@media).)*?\.layui-logo\s*\{[^}]*display:\s*none;[^}]*\}'
            . '(?:(?!@media).)*?\.layui-layout-left\s*\{[^}]*left:\s*0;[^}]*\}~s',
            $layui,
            'The compact header must free the left edge for the sidebar toggle instead of overlapping the theme picker.'
        );
    }

    public function test_layui_tablet_brand_stays_on_a_single_line(): void
    {
        $layui = $this->source('public/css/layui/visual-c.css');

        $this->assertMatchesRegularExpression(
            '~@media\s*\(max-width:\s*768px\)\s*\{'
            . '(?:(?!@media).)*?\.layui-logo\s*\{[^}]*width:\s*168px\s*!important;[^}]*white-space:\s*nowrap;[^}]*\}'
            . '(?:(?!@media).)*?\.layui-layout-left\s*\{[^}]*left:\s*168px;[^}]*\}~s',
            $layui,
            'The tablet header must reserve enough width for the longer admin brand without clipping it.'
        );
    }

    public function test_crmui_tablet_sidebar_keeps_a_visible_toggle(): void
    {
        $crmui = $this->source('public/css/crmui/visual-c.css');

        $this->assertMatchesRegularExpression(
            '~@media\s*\(max-width:\s*768px\)\s*\{'
            . '(?:(?!@media).)*?\[data-crmui-toggle-sidebar\]\s*\{[^}]*display:\s*inline-grid;[^}]*\}~s',
            $crmui,
            'The tablet drawer breakpoint must expose the matching sidebar toggle.'
        );
    }

    public function test_crmui_compact_primary_tool_button_keeps_its_icon_visible(): void
    {
        $crmui = $this->source('public/css/crmui/visual-c.css');

        $this->assertMatchesRegularExpression(
            '~@media\s*\(max-width:\s*480px\)\s*\{'
            . '(?:(?!@media).)*?\.crmui-topbar-actions\s+\.is-primary\s+\.crm-lucide-icon\s*,'
            . '(?:(?!\{).)*?svg\.lucide\s*\{[^}]*width:\s*18px;[^}]*height:\s*18px;[^}]*\}~s',
            $crmui,
            'The compact primary command must retain a visible Lucide icon when its text size is zero.'
        );
    }

    public function test_crmui_ajax_mask_is_hidden_after_request_completion(): void
    {
        $crmui = $this->source('public/css/crmui/visual-c.css');

        $this->assertMatchesRegularExpression(
            '~\.crm-ajax-mask\s*\{[^}]*position:\s*fixed;[^}]*display:\s*none;[^}]*\}~s',
            $crmui,
            'The inactive CrmUI Ajax mask must not remain as ordinary page content.'
        );
        $this->assertMatchesRegularExpression(
            '~\.crm-ajax-mask\.is-visible\s*\{[^}]*display:\s*flex;[^}]*\}~s',
            $crmui,
            'The CrmUI Ajax mask must remain available while requests are active.'
        );
    }

    public function test_visual_c_overlay_masks_keep_explicit_scrim_fallbacks(): void
    {
        $crmui = $this->source('public/css/crmui/visual-c.css');

        foreach (['.crmui-modal-backdrop', '.crm-ajax-mask'] as $selector) {
            $this->assertMatchesRegularExpression(
                '~' . preg_quote($selector, '~') . '\s*\{[^}]*background:\s*rgba\(0,\s*0,\s*0,\s*\.7\)(?:\s*!important)?;[^}]*background:\s*var\(--crmui-vc-scrim\)(?:\s*!important)?~s',
                $crmui,
                $selector . ' must retain an explicit scrim fallback before the Visual C token.'
            );
        }
    }

    public function test_crmui_compact_tables_preserve_the_card_layout(): void
    {
        $crmui = $this->source('public/css/crmui/visual-c.css');

        $this->assertMatchesRegularExpression(
            '~@media\s*\(max-width:\s*640px\)\s*\{'
            . '(?:(?!@media).)*?\.crmui-table-wrap\s*\{[^}]*overflow-x:\s*visible;[^}]*\}'
            . '(?:(?!@media).)*?\.crmui-table\s*\{[^}]*min-width:\s*0;[^}]*\}'
            . '(?:(?!@media).)*?\.crmui-table\s+td\s*\{[^}]*white-space:\s*normal;[^}]*\}~s',
            $crmui,
            'Visual C must not override the compact CrmUI table card layout with desktop sizing.'
        );
    }

    public function test_layui_foundation_resets_legacy_shell_geometry(): void
    {
        $layui = $this->source('public/css/layui/visual-c.css');

        foreach ([
            'html:has(> body[data-ui-family="layui"][data-visual-direction="c"])',
            'display: block !important;',
            'width: 100% !important;',
            'min-width: 320px !important;',
            'animation: none !important;',
            'transform: none !important;',
            '.front-frame-shell',
            '#contentFrame',
        ] as $needle) {
            $this->assertStringContainsString($needle, $layui);
        }
    }

    public function test_reference_pages_keep_business_hooks_and_declare_visual_reference(): void
    {
        $frontDashboard = $this->source('resources/front/layui/dashboard/index.blade.php');
        $adminUsers = $this->source('resources/admin/layui/users/index.blade.php');
        $frontCrmui = $this->source('resources/front/crmui/partials/module-page.blade.php');
        $adminCrmui = $this->source('resources/admin/crmui/partials/module-page.blade.php');

        $this->assertStringContainsString('data-visual-c-reference="front-dashboard"', $frontDashboard);
        $this->assertStringContainsString('data-layui-page="dashboard/index"', $frontDashboard);
        $this->assertStringContainsString('data-visual-c-reference="admin-users"', $adminUsers);
        $this->assertStringContainsString('id="userSearchForm"', $adminUsers);
        $this->assertStringContainsString('id="userTable"', $adminUsers);
        foreach ([$frontCrmui, $adminCrmui] as $partial) {
            $this->assertStringContainsString('data-visual-c-reference=', $partial);
            $this->assertStringContainsString('data-crmui-page=', $partial);
            $this->assertStringContainsString('data-crmui-table-body', $partial);
            $this->assertStringContainsString('data-crmui-action-modal', $partial);
        }
    }

    public function test_reference_routes_render_visual_c_without_cross_family_assets(): void
    {
        foreach ([
            '/front/dashboard' => ['/css/layui/visual-c.css', '/css/crmui/visual-c.css', 'front-dashboard', '/front/dashboard?frame=1'],
            '/admin/users' => ['/css/layui/visual-c.css', '/css/crmui/visual-c.css', 'admin-users', null],
            '/front-crmui/dashboard' => ['/css/crmui/visual-c.css', '/css/layui/visual-c.css', 'front.dashboard', null],
            '/admin-crmui/users' => ['/css/crmui/visual-c.css', '/css/layui/visual-c.css', 'admin.users', null],
        ] as $uri => [$ownAsset, $foreignAsset, $reference, $frameUri]) {
            $response = $this->get($uri)->assertOk();
            $response->assertSee($ownAsset, false);
            $response->assertDontSee($foreignAsset, false);

            $referenceResponse = $response;
            if ($frameUri !== null) {
                $response->assertSee($frameUri, false);
                $referenceResponse = $this->get($frameUri)->assertOk();
            }

            $referenceResponse->assertSee('data-visual-c-reference="' . $reference . '"', false);
        }
    }

    private function source(string $relativePath): string
    {
        $path = base_path(str_replace('/', DIRECTORY_SEPARATOR, $relativePath));
        $this->assertFileExists($path);

        return (string) file_get_contents($path);
    }
}
