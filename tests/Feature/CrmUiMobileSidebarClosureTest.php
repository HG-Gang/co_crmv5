<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/16
 * Time: 03:07
 */

/**
 * CrmUiMobileSidebarClosureTest
 *
 * 文件功能：
 * - 验证 CrmUI 移动端侧边栏闭环：外壳才渲染可访问侧栏控件、遮罩与可见性样式仅限移动端作用域、外壳脚本管理焦点与幂等绑定。
 * - 输入：路由、控制器、Blade/JS、迁移等项目源码文本；输出：PHPUnit 断言结果。
 * - 明确不负责：不覆盖 HTTP 运行时业务流与 MT4 真实网关同步（由集成与功能测试锁定）。
 */

declare(strict_types=1);

namespace Tests\Feature;

use Tests\Feature\Concerns\ExecutesJavascriptScenarios;
use Tests\TestCase;

final class CrmUiMobileSidebarClosureTest extends TestCase
{
    use ExecutesJavascriptScenarios;

    public function test_app_layouts_render_accessible_sidebar_controls_only_in_the_shell(): void
    {
        $layouts = [
            'resources/front/crmui/layouts/app.blade.php' => '/front-crmui/dashboard',
            'resources/admin/crmui/layouts/app.blade.php' => '/admin-crmui/users',
        ];

        foreach ($layouts as $path => $route) {
            $layout = $this->source($path);

            $this->assertMatchesRegularExpression(
                '/<aside\b(?=[^>]*\bid="crmuiSidebar")(?=[^>]*\btabindex="-1")[^>]*>/',
                $layout,
                $path . ' must provide a focus fallback when navigation is empty.'
            );
            $this->assertMatchesRegularExpression(
                '/<button\b(?=[^>]*\bdata-crmui-toggle-sidebar\b)(?=[^>]*\baria-controls="crmuiSidebar")(?=[^>]*\baria-expanded="false")[^>]*>/',
                $layout,
                $path . ' must expose the controlled sidebar and its initial collapsed state.'
            );
            $this->assertStringContainsString("aria-label=\"{{ __('common.menu') }}\"", $layout);
            $this->assertStringNotContainsString(
                "aria-label=\"{{ __('crmui.actions.open_menu') }}\"",
                $layout
            );
            $this->assertMatchesRegularExpression(
                '/<button\b(?=[^>]*\btype="button")(?=[^>]*\bclass="[^"]*\bcrmui-sidebar-scrim\b[^"]*")(?=[^>]*\bdata-crmui-sidebar-dismiss\b)(?=[^>]*\baria-label=)[^>]*><\/button>/',
                $layout,
                $path . ' must render an accessible button scrim.'
            );
            $this->assertSame(1, substr_count($layout, 'data-crmui-sidebar-dismiss'));

            $this->get($route)
                ->assertOk()
                ->assertSee('data-crmui-sidebar-dismiss', false);
            $this->get($route . '?frame=1')
                ->assertOk()
                ->assertDontSee('data-crmui-sidebar-dismiss', false);
        }
    }

    public function test_visual_c_scrim_and_sidebar_visibility_are_scoped_and_mobile_only(): void
    {
        $css = $this->source('public/css/crmui/visual-c.css');
        $scope = 'body[data-ui-family="crmui"][data-visual-direction="c"] ';
        $scrimSelector = $scope . '.crmui-sidebar-scrim';
        $baseRule = $this->cssRule($css, $scrimSelector);
        $mobileCss = $this->between(
            $css,
            '@media (max-width: 768px) {',
            '@media (max-width: 480px) {'
        );
        $mobileScrimRule = $this->cssRule($mobileCss, $scrimSelector);
        $closedSidebarRule = $this->cssRule($mobileCss, $scope . '.crmui-sidebar');
        $openSidebarRule = $this->cssRule($mobileCss, $scope . '.crmui-sidebar.is-open');
        $openScrimRule = $this->cssRule(
            $mobileCss,
            $scope . '.crmui-sidebar.is-open + .crmui-sidebar-scrim'
        );

        foreach ([
            'display: none;',
            'position: fixed;',
            'inset: 0;',
            'z-index: 90;',
            'opacity: 0;',
            'visibility: hidden;',
            'pointer-events: none;',
        ] as $declaration) {
            $this->assertStringContainsString($declaration, $baseRule);
        }
        $this->assertMatchesRegularExpression('/background:\s*rgba\(/', $baseRule);
        $this->assertStringNotContainsString('gradient', strtolower($baseRule));

        $this->assertStringContainsString('display: block;', $mobileScrimRule);
        $this->assertStringContainsString('visibility: hidden;', $closedSidebarRule);
        $this->assertStringContainsString('pointer-events: none;', $closedSidebarRule);
        $this->assertStringContainsString('visibility: visible;', $openSidebarRule);
        $this->assertStringContainsString('pointer-events: auto;', $openSidebarRule);
        $this->assertStringContainsString('transition-delay: 0s;', $openSidebarRule);
        $this->assertStringContainsString('opacity: 1;', $openScrimRule);
        $this->assertStringContainsString('visibility: visible;', $openScrimRule);
        $this->assertStringContainsString('pointer-events: auto;', $openScrimRule);
    }

    /**
     * @dataProvider scriptProvider
     */
    public function test_shell_script_manages_accessibility_focus_and_idempotent_bindings(
        string $path,
        string $module,
        string $mediaApi,
        bool $hasShell,
        bool $hasNavigation
    ): void {
        $source = $this->source($path);
        $productionExport = sprintf(
            "exports('%s', {init: init, request: request, loadPage: loadPage});",
            $module
        );
        $testExport = sprintf("exports('%s', {bindShell: bindShell});", $module);
        $this->assertStringContainsString($productionExport, $source);
        $source = str_replace($productionExport, $testExport, $source);

        $scenario = <<<'JS'
'use strict';
const vm = require('vm');
const source = __CRMUI_SOURCE__;
const mediaApi = __MEDIA_API__;
const hasShell = __HAS_SHELL__;
const hasNavigation = __HAS_NAVIGATION__;
const handlers = [];
const mediaLists = [];
const documentObject = {
    activeElement: null,
    documentElement: {getAttribute() { return null; }, setAttribute() {}}
};

function element(name, attrs) {
    return {
        name,
        attrs: Object.assign({}, attrs || {}),
        props: {},
        classes: new Set(),
        focusCount: 0,
        focus() {
            this.focusCount++;
            documentObject.activeElement = this;
        }
    };
}

const sidebar = hasShell ? element('sidebar') : null;
const toggles = hasShell ? [
    element('toggle-primary', {'aria-expanded': 'false'}),
    element('toggle-secondary', {'aria-expanded': 'false'})
] : [];
const scrim = hasShell ? element('scrim') : null;
const navLink = hasShell && hasNavigation ? element('nav-link') : null;

function eventParts(eventName) {
    const parts = String(eventName || '').split('.');
    return {
        event: parts.shift() || '',
        namespace: parts.join('.')
    };
}

function collection(items) {
    const api = {
        length: items.length,
        on(eventName, selector, handler) {
            if (items[0] !== documentObject) {
                return this;
            }
            if (typeof selector === 'function') {
                handler = selector;
                selector = null;
            }
            const parts = eventParts(eventName);
            handlers.push({
                event: parts.event,
                namespace: parts.namespace,
                selector: selector || null,
                handler
            });
            return this;
        },
        off(eventName) {
            if (items[0] !== documentObject) {
                return this;
            }
            const parts = eventParts(eventName);
            for (let index = handlers.length - 1; index >= 0; index--) {
                const eventMatches = !parts.event || handlers[index].event === parts.event;
                const namespaceMatches = !parts.namespace
                    || handlers[index].namespace === parts.namespace;
                if (eventMatches && namespaceMatches) {
                    handlers.splice(index, 1);
                }
            }
            return this;
        },
        attr(name, value) {
            if (value === undefined) {
                return items.length ? items[0].attrs[name] : undefined;
            }
            items.forEach(item => { item.attrs[name] = String(value); });
            return this;
        },
        removeAttr(name) {
            items.forEach(item => { delete item.attrs[name]; });
            return this;
        },
        prop(name, value) {
            if (value === undefined) {
                return items.length ? items[0].props[name] : undefined;
            }
            items.forEach(item => { item.props[name] = value; });
            return this;
        },
        hasClass(name) {
            return items.length ? items[0].classes.has(name) : false;
        },
        addClass(name) {
            items.forEach(item => item.classes.add(name));
            return this;
        },
        removeClass(name) {
            items.forEach(item => item.classes.delete(name));
            return this;
        },
        toggleClass(name, state) {
            items.forEach(item => {
                const enabled = state === undefined ? !item.classes.has(name) : Boolean(state);
                if (enabled) {
                    item.classes.add(name);
                } else {
                    item.classes.delete(name);
                }
            });
            return this;
        },
        find(selector) {
            if (items[0] === sidebar && selector === '.crmui-nav-link' && navLink) {
                return collection([navLink]);
            }
            return collection([]);
        },
        get(index) {
            return items[index];
        }
    };
    items.forEach((item, index) => { api[index] = item; });
    return api;
}

function jquery(target) {
    if (target === documentObject) {
        return collection([documentObject]);
    }
    if (target === sidebar || target === scrim || target === navLink || toggles.includes(target)) {
        return collection(target ? [target] : []);
    }
    if (target === '#crmuiSidebar') {
        return collection(sidebar ? [sidebar] : []);
    }
    if (target === '[data-crmui-toggle-sidebar]') {
        return collection(toggles);
    }
    return collection([]);
}
jquery.isArray = Array.isArray;
jquery.extend = Object.assign;

function createMedia(query) {
    const media = {
        query,
        matches: true,
        listeners: [],
        added: 0,
        removed: 0,
        dispatch(matches) {
            this.matches = matches;
            this.listeners.slice().forEach(listener => listener({matches}));
        }
    };
    if (mediaApi === 'modern') {
        media.addEventListener = function(type, listener) {
            if (type === 'change') {
                this.added++;
                this.listeners.push(listener);
            }
        };
        media.removeEventListener = function(type, listener) {
            const index = type === 'change' ? this.listeners.indexOf(listener) : -1;
            if (index !== -1) {
                this.removed++;
                this.listeners.splice(index, 1);
            }
        };
    } else {
        media.addListener = function(listener) {
            this.added++;
            this.listeners.push(listener);
        };
        media.removeListener = function(listener) {
            const index = this.listeners.indexOf(listener);
            if (index !== -1) {
                this.removed++;
                this.listeners.splice(index, 1);
            }
        };
    }
    mediaLists.push(media);
    return media;
}

let shellModule = null;
const context = {
    console,
    URL,
    document: documentObject,
    localStorage: {getItem() { return null; }, setItem() {}, removeItem() {}},
    FormData: function FormData() {},
    window: null,
    layui: {
        jquery,
        layer: {msg() {}, confirm() {}, close() {}, open() {}},
        define(_dependencies, factory) {
            factory((_name, value) => { shellModule = value; });
        },
        use() {}
    }
};
context.window = context;
context.matchMedia = createMedia;

vm.runInNewContext(source, context, {filename: __CRMUI_FILENAME__});
shellModule.bindShell();
shellModule.bindShell();

function targetFor(selector) {
    if (selector === '[data-crmui-toggle-sidebar]') {
        return toggles[0] || null;
    }
    if (selector === '[data-crmui-sidebar-dismiss]') {
        return scrim;
    }
    if (selector === '.crmui-nav-link') {
        return navLink;
    }
    return null;
}

function trigger(event, selector, payload) {
    const target = targetFor(selector);
    if (event === 'click' && target && typeof target.focus === 'function') {
        target.focus();
    }
    handlers
        .filter(item => item.event === event && item.selector === selector)
        .slice()
        .forEach(item => item.handler.call(target || documentObject, Object.assign({
            key: null
        }, payload || {})));
}

function snapshot() {
    return {
        open: sidebar ? sidebar.classes.has('is-open') : false,
        toggleAria: toggles.map(toggle => toggle.attrs['aria-expanded']),
        sidebarAria: sidebar && sidebar.attrs['aria-hidden'] !== undefined
            ? sidebar.attrs['aria-hidden']
            : null,
        sidebarInert: sidebar && sidebar.props.inert !== undefined
            ? Boolean(sidebar.props.inert)
            : null,
        active: documentObject.activeElement ? documentObject.activeElement.name : null
    };
}

function handlerSummary() {
    const namespaced = {};
    handlers
        .filter(item => item.namespace === 'crmuiSidebar')
        .forEach(item => {
            const key = item.event + '|' + (item.selector || '*');
            namespaced[key] = (namespaced[key] || 0) + 1;
        });
    return {total: handlers.length, namespaced};
}

function mediaSummary() {
    return {
        created: mediaLists.length,
        active: mediaLists.reduce((count, media) => count + media.listeners.length, 0),
        added: mediaLists.reduce((count, media) => count + media.added, 0),
        removed: mediaLists.reduce((count, media) => count + media.removed, 0),
        query: mediaLists.length ? mediaLists[mediaLists.length - 1].query : null
    };
}

const actual = {
    initial: snapshot(),
    handlers: handlerSummary(),
    media: mediaSummary()
};

if (hasShell) {
    trigger('click', '[data-crmui-toggle-sidebar]');
    actual.mobileOpened = snapshot();
    trigger('click', '[data-crmui-toggle-sidebar]');
    actual.mobileToggleClosed = snapshot();

    trigger('click', '[data-crmui-toggle-sidebar]');
    trigger('keydown', null, {key: 'Escape'});
    actual.escapeClosed = snapshot();

    trigger('click', '[data-crmui-toggle-sidebar]');
    trigger('click', '[data-crmui-sidebar-dismiss]');
    actual.scrimClosed = snapshot();

    trigger('click', '[data-crmui-toggle-sidebar]');
    trigger('click', '.crmui-nav-link');
    actual.navClosed = snapshot();

    trigger('click', '[data-crmui-toggle-sidebar]');
    mediaLists[mediaLists.length - 1].dispatch(false);
    actual.desktopBreakpointClosed = snapshot();
    trigger('click', '[data-crmui-toggle-sidebar]');
    actual.desktopClickClosed = snapshot();
    mediaLists[mediaLists.length - 1].dispatch(true);
    actual.mobileBreakpointReset = snapshot();
}

console.log(JSON.stringify(actual));
JS;

        $scenario = str_replace(
            [
                '__CRMUI_SOURCE__',
                '__CRMUI_FILENAME__',
                '__MEDIA_API__',
                '__HAS_SHELL__',
                '__HAS_NAVIGATION__',
            ],
            [
                json_encode($source, JSON_THROW_ON_ERROR),
                json_encode(basename($path), JSON_THROW_ON_ERROR),
                json_encode($mediaApi, JSON_THROW_ON_ERROR),
                json_encode($hasShell, JSON_THROW_ON_ERROR),
                json_encode($hasNavigation, JSON_THROW_ON_ERROR),
            ],
            $scenario
        );
        $actual = $this->executeJavascriptJson($scenario);

        if (!$hasShell) {
            $this->assertSame(0, $actual['handlers']['total']);
            $this->assertSame([], $actual['handlers']['namespaced']);
            $this->assertSame(0, $actual['media']['active']);

            return;
        }

        $expectedHandlers = [
            'click|.crmui-nav-link' => 1,
            'click|[data-crmui-lang]' => 1,
            'click|[data-crmui-logout]' => 1,
            'click|[data-crmui-sidebar-dismiss]' => 1,
            'click|[data-crmui-toggle-sidebar]' => 1,
            'keydown|*' => 1,
        ];
        if (basename($path) === 'front.js') {
            $expectedHandlers['click|[data-crmui-ui-target]'] = 1;
        }
        $actualHandlers = $actual['handlers']['namespaced'];
        ksort($actualHandlers);
        ksort($expectedHandlers);
        $this->assertSame(6 + (basename($path) === 'front.js' ? 1 : 0), $actual['handlers']['total']);
        $this->assertSame($expectedHandlers, $actualHandlers);
        $this->assertSame(2, $actual['media']['created']);
        $this->assertSame(1, $actual['media']['active']);
        $this->assertSame(2, $actual['media']['added']);
        $this->assertSame(1, $actual['media']['removed']);
        $this->assertSame('(max-width: 768px)', $actual['media']['query']);

        $this->assertMobileClosed($actual['initial'], null);
        $this->assertSame(
            [
                'open' => true,
                'toggleAria' => ['true', 'true'],
                'sidebarAria' => 'false',
                'sidebarInert' => false,
                'active' => $hasNavigation ? 'nav-link' : 'sidebar',
            ],
            $actual['mobileOpened']
        );
        $this->assertMobileClosed($actual['mobileToggleClosed'], 'toggle-primary');
        $this->assertMobileClosed($actual['escapeClosed'], 'toggle-primary');
        $this->assertMobileClosed($actual['scrimClosed'], 'toggle-primary');
        $this->assertMobileClosed(
            $actual['navClosed'],
            $hasNavigation ? 'nav-link' : 'sidebar'
        );
        $this->assertDesktopClosed(
            $actual['desktopBreakpointClosed'],
            $hasNavigation ? 'nav-link' : 'sidebar'
        );
        $this->assertDesktopClosed($actual['desktopClickClosed'], 'toggle-primary');
        $this->assertMobileClosed($actual['mobileBreakpointReset'], 'toggle-primary');
    }

    public static function scriptProvider(): array
    {
        return [
            'front modern' => [
                'public/js/apps/crmui/front.js',
                'crmuiFront',
                'modern',
                true,
                true,
            ],
            'admin modern' => [
                'public/js/apps/crmui/admin.js',
                'crmuiAdmin',
                'modern',
                true,
                true,
            ],
            'front legacy' => [
                'public/js/apps/crmui/front.js',
                'crmuiFront',
                'legacy',
                true,
                true,
            ],
            'admin legacy empty navigation' => [
                'public/js/apps/crmui/admin.js',
                'crmuiAdmin',
                'legacy',
                true,
                false,
            ],
            'front empty frame' => [
                'public/js/apps/crmui/front.js',
                'crmuiFront',
                'modern',
                false,
                false,
            ],
            'admin empty frame' => [
                'public/js/apps/crmui/admin.js',
                'crmuiAdmin',
                'legacy',
                false,
                false,
            ],
        ];
    }

    /** @param array<string, mixed> $actual */
    private function assertMobileClosed(array $actual, ?string $active): void
    {
        $this->assertSame([
            'open' => false,
            'toggleAria' => ['false', 'false'],
            'sidebarAria' => 'true',
            'sidebarInert' => true,
            'active' => $active,
        ], $actual);
    }

    /** @param array<string, mixed> $actual */
    private function assertDesktopClosed(array $actual, ?string $active): void
    {
        $this->assertSame([
            'open' => false,
            'toggleAria' => ['false', 'false'],
            'sidebarAria' => 'false',
            'sidebarInert' => false,
            'active' => $active,
        ], $actual);
    }

    private function cssRule(string $css, string $selector): string
    {
        $matched = preg_match(
            '/' . preg_quote($selector, '/') . '\s*\{([^}]*)\}/',
            $css,
            $matches
        );
        $this->assertSame(1, $matched, 'Missing CSS rule: ' . $selector);

        return $matches[1];
    }

    private function between(string $source, string $start, string $end): string
    {
        $startPosition = strpos($source, $start);
        $this->assertNotFalse($startPosition, 'Missing CSS section start: ' . $start);
        $endPosition = strpos($source, $end, $startPosition + strlen($start));
        $this->assertNotFalse($endPosition, 'Missing CSS section end: ' . $end);

        return substr($source, $startPosition, $endPosition - $startPosition);
    }

    private function source(string $relativePath): string
    {
        $path = base_path(str_replace('/', DIRECTORY_SEPARATOR, $relativePath));
        $this->assertFileExists($path);

        return (string) file_get_contents($path);
    }
}
