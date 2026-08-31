{{--
Created by PhpStorm.
Project name co_crmv5.
User: Huang Gang
Date: 2026/08/28
Time: 00:47
--}}
{{--
 * 前台 CrmUI 应用布局。
 *
 * 文件功能：
 * - 前台 crmui 新 UI 的页面骨架：品牌区、侧边栏导航、顶栏（语言/主题/退出）、内容区。
 * - 侧边栏菜单项按 navGroups 数据渲染，图标统一使用 Lucide（data-lucide 属性）。
 *
 * 适用场景：
 * - 普通用户登录后访问 /front-crmui/* 的所有模块页面。
 *
 * 入参：
 * - $page：当前页面元数据（title/path/frame 等）。
 * - $navGroups：侧边栏分组结构，每项含 label/path/icon。
 *
 * 返回值：
 * - 输出完整 HTML 文档；frame 模式仅输出内容区（供弹层内嵌使用）。
 *
 * 说明：
 * - 全程禁止表情符号，图标统一来自本地 lucide vendor 与 lucide-bridge。
--}}
@php
    $page = $page ?? [];
    $navGroups = $navGroups ?? [];
    $pageTitle = $page['title'] ?? __('crmui.common.front_console');
    $isFrame = !empty($page['frame']);
    $renderFamily = ($page['renderFamily'] ?? 'crmui') === 'naive' ? 'naive' : 'crmui';
    $currentPagePath = trim((string) ($page['path'] ?? 'dashboard'), '/') ?: 'dashboard';
    $currentFamilyRoute = $renderFamily === 'naive' ? 'front_naive_app' : 'front_crmui_app';
    $uiFamilies = [
        'layui' => [
            'label' => __('front.layout_classic'),
            'icon' => 'wallet-cards',
            'url' => url('/front/' . $currentPagePath),
        ],
        'crmui' => [
            'label' => __('front.layout_crmui'),
            'icon' => 'gauge',
            'url' => route('front_crmui_app', ['path' => $currentPagePath]),
        ],
        'naive' => [
            'label' => __('front.layout_naive'),
            'icon' => 'sparkles',
            'url' => route('front_naive_app', ['path' => $currentPagePath]),
        ],
    ];
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $pageTitle }} - {{ __('crmui.common.front_console') }}</title>
    <link rel="stylesheet" href="{{ asset('/js/vendor/layui-v2.13.5/layui/css/layui.css') }}">
    <link rel="stylesheet" href="{{ asset('/css/crmui/tokens.css') }}?v=2026070401">
    <link rel="stylesheet" href="{{ asset('/css/crmui/front.css') }}?v=2026081202">
{{-- 统一上传组件样式：CrmUI/Naive 家族与 Layui 家族共用同一份上传视觉。 --}}
<link rel="stylesheet" href="{{ asset('/css/common/crm-upload.css') }}?v=2026082801">
    @if($renderFamily === 'naive')
        <link rel="stylesheet" href="{{ asset('/css/crmui/naive.css') }}?v=2026081201">
    @endif
    @include('partials.lucide-assets')
    @yield('styles')
    <link rel="stylesheet" href="{{ asset('/css/crmui/visual-c.css') }}?v=2026080703">
    @include('partials.theme-assets')
</head>
<body class="crmui-body crmui-front-body {{ $isFrame ? 'crmui-frame-body' : '' }}" data-crmui-surface="front" data-ui-family="{{ $renderFamily }}" data-crmui-render-family="{{ $renderFamily }}" data-ui-surface="front" data-visual-direction="c" data-crmui-page-path="{{ $currentPagePath }}">
@if($isFrame)
    <div class="crm-theme-picker-host">
        @include('partials.theme-picker', ['themePickerCompact' => true, 'themePickerLabel' => false])
    </div>
    <main class="crmui-frame-main">
        @yield('content')
    </main>
@else
    <div class="crmui-shell">
        <aside class="crmui-sidebar" id="crmuiSidebar" tabindex="-1">
            <a class="crmui-brand" href="{{ route($currentFamilyRoute, ['path' => 'dashboard']) }}">
                <span class="crmui-brand-mark">CO</span>
                <span>
                    <strong>{{ __('crmui.common.front_console') }}</strong>
                    <small>{{ __('crmui.common.blade_ui') }}</small>
                </span>
            </a>

            <nav class="crmui-nav" aria-label="{{ __('crmui.nav.primary') }}">
                @foreach($navGroups as $group)
                    <section class="crmui-nav-group">
                        <div class="crmui-nav-title">{{ $group['title'] }}</div>
                        @foreach($group['items'] as $item)
                            <a class="crmui-nav-link {{ ($page['path'] ?? '') === $item['path'] ? 'is-active' : '' }}"
                               href="{{ route($currentFamilyRoute, ['path' => $item['path']]) }}">
                                @if(!empty($item['icon']))<i data-lucide="{{ $item['icon'] }}"></i>@endif
                                <span>{{ $item['label'] }}</span>
                            </a>
                        @endforeach
                    </section>
                @endforeach
            </nav>
        </aside>

        <button class="crmui-sidebar-scrim" type="button" data-crmui-sidebar-dismiss aria-label="{{ __('common.close') }}"></button>

        <div class="crmui-workspace">
            <header class="crmui-topbar">
                <button class="crmui-icon-button" type="button" data-crmui-toggle-sidebar aria-controls="crmuiSidebar" aria-expanded="false" aria-label="{{ __('common.menu') }}"><i data-lucide="menu"></i></button>
                <div class="crmui-topbar-title">
                    <strong>{{ $pageTitle }}</strong>
                    <span>{{ __('crmui.common.front_console') }}</span>
                </div>
                <div class="crmui-topbar-actions">
                    <div class="crmui-ui-switch"
                         role="group"
                         aria-label="{{ __('key.switch_style') }}"
                         data-crmui-ui-switch
                         data-ui-current-family="{{ $renderFamily }}">
                        @foreach($uiFamilies as $family => $option)
                            <button class="crmui-ui-switch-button {{ $renderFamily === $family ? 'is-active' : '' }}"
                                    type="button"
                                    data-ui-target="{{ $family }}"
                                    data-crmui-ui-target="{{ $family }}"
                                    data-ui-url="{{ $option['url'] }}"
                                    aria-pressed="{{ $renderFamily === $family ? 'true' : 'false' }}"
                                    title="{{ $option['label'] }}">
                                <i data-lucide="{{ $option['icon'] }}"></i>
                                <span>{{ $option['label'] }}</span>
                            </button>
                        @endforeach
                    </div>
                    @include('partials.language-picker', ['languagePickerCompact' => true])
                    @include('partials.theme-picker', ['themePickerCompact' => true])
                    <button class="crmui-tool-button is-primary" type="button" data-crmui-logout data-api-url="{{ route('front_api_auth_logout') }}"><i data-lucide="log-out"></i> {{ __('auth.logout') }}</button>
                </div>
            </header>

            <main class="crmui-main">
                @yield('content')
            </main>
        </div>
    </div>
@endif

<script src="{{ asset('/js/vendor/jquery/jquery-3.6.0.min.js') }}"></script>
<script src="{{ asset('/js/vendor/layui-v2.13.5/layui/layui.js') }}"></script>
@include('partials.frontend-routes')
<script src="{{ asset('/js/shared/i18n.js') }}?v=2026060402"></script>
<script src="{{ asset('/js/shared/ajax.js') }}?v=2026060702"></script>
{{-- 字段级校验提示与统一上传组件为跨家族共享能力，必须在业务页面脚本之前注册。 --}}
<script src="{{ asset('/js/shared/form-field-errors.js') }}?v=2026082801"></script>
<script src="{{ asset('/js/shared/layui-upload.js') }}?v=2026082801"></script>
@include('partials.visual-audit-fixture')
<script src="{{ asset('/js/vendor/echarts/echarts.common.min.js') }}"></script>
<script src="{{ asset('/js/apps/crmui/front.js') }}?v=2026081202"></script>
@yield('scripts')
</body>
</html>
