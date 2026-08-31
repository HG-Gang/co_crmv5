{{--
Created by PhpStorm.
Project name co_crmv5.
User: Huang Gang
Date: 2026/08/18
Time: 16:48
--}}
{{--
 * 大代理 CrmUI 应用布局。
 *
 * 文件功能：
 * - 大代理（Big Agent）crmui 新 UI 的页面骨架：品牌区、侧边栏导航、顶栏、内容区。
 * - 侧边栏菜单项按 navGroups 数据渲染，图标统一使用 Lucide（data-lucide 属性）。
 *
 * 适用场景：
 * - 大代理商登录后访问 /front-crmui/big-agent/* 的页面。
 *
 * 入参：
 * - $page：当前页面元数据（title/path/frame 等）。
 * - $navGroups：侧边栏分组结构，每项含 label/path/icon。
 *
 * 返回值：
 * - 输出完整 HTML 文档。
 *
 * 说明：
 * - 全程禁止表情符号，图标统一来自本地 lucide vendor 与 lucide-bridge。
--}}
@php
    $page = $page ?? [];
    $navGroups = $navGroups ?? [];
    $pageTitle = $page['title'] ?? __('crmui.common.big_agent_console');
    $renderFamily = ($page['renderFamily'] ?? 'crmui') === 'naive' ? 'naive' : 'crmui';
    $routeNames = $page['routeNames'] ?? [
        'login' => 'front_crmui_big_agent_login',
        'logout' => 'front_crmui_big_agent_logout',
        'dashboard' => 'front_crmui_big_agent_dashboard',
        'app' => 'front_crmui_big_agent_app',
    ];
    $currentPath = trim((string) ($page['path'] ?? 'dashboard'), '/') ?: 'dashboard';
    $uiFamilies = [
        'crmui' => [
            'label' => __('front.layout_crmui'),
            'icon' => 'gauge',
            'url' => $currentPath === 'dashboard'
                ? route('front_crmui_big_agent_dashboard')
                : route('front_crmui_big_agent_app', ['path' => $currentPath]),
        ],
        'naive' => [
            'label' => __('front.layout_naive'),
            'icon' => 'panels-top-left',
            'url' => $currentPath === 'dashboard'
                ? route('front_naive_big_agent_dashboard')
                : route('front_naive_big_agent_app', ['path' => $currentPath]),
        ],
    ];
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $pageTitle }} - {{ __('crmui.common.big_agent_console') }}</title>
    <link rel="stylesheet" href="{{ asset('/js/vendor/layui-v2.13.5/layui/css/layui.css') }}">
    <link rel="stylesheet" href="{{ asset('/css/crmui/tokens.css') }}?v=2026072501">
    <link rel="stylesheet" href="{{ asset('/css/crmui/front.css') }}?v=2026072501">
    @if($renderFamily === 'naive')
        <link rel="stylesheet" href="{{ asset('/css/crmui/naive.css') }}?v=2026081201">
    @endif
    @include('partials.lucide-assets')
    @include('partials.theme-assets')
</head>
<body class="crmui-body crmui-front-body" data-crmui-surface="big-agent" data-crmui-session="big-agent" data-ui-family="{{ $renderFamily }}" data-ui-surface="big-agent" data-visual-direction="c" data-crmui-page-path="{{ $currentPath }}">
<div class="crmui-shell">
    <aside class="crmui-sidebar" id="crmuiSidebar">
        <a class="crmui-brand" href="{{ route($routeNames['dashboard']) }}">
            <span class="crmui-brand-mark">CO</span>
            <span><strong>{{ __('crmui.common.big_agent_console') }}</strong><small>{{ __('crmui.common.blade_ui') }}</small></span>
        </a>
        <nav class="crmui-nav" aria-label="{{ __('crmui.nav.primary') }}">
            @foreach($navGroups as $group)
                <section class="crmui-nav-group">
                    <div class="crmui-nav-title">{{ $group['title'] }}</div>
                    @foreach($group['items'] as $item)
                        @php($url = $item['path'] === 'dashboard' ? route($routeNames['dashboard']) : route($routeNames['app'], ['path' => $item['path']]))
                        <a class="crmui-nav-link {{ ($page['path'] ?? '') === $item['path'] ? 'is-active' : '' }}" href="{{ $url }}">@if(!empty($item['icon']))<i data-lucide="{{ $item['icon'] }}"></i>@endif<span>{{ $item['label'] }}</span></a>
                    @endforeach
                </section>
            @endforeach
        </nav>
    </aside>
    <div class="crmui-workspace">
        <header class="crmui-topbar">
            <button class="crmui-icon-button" type="button" data-crmui-toggle-sidebar aria-label="{{ __('crmui.actions.open_menu') }}"><i data-lucide="menu"></i></button>
            <div class="crmui-topbar-title"><strong>{{ $pageTitle }}</strong><span>{{ __('crmui.common.big_agent_console') }}</span></div>
            <div class="crmui-topbar-actions">
                <div class="crmui-ui-switch" role="group" aria-label="{{ __('key.switch_style') }}" data-crmui-ui-switch data-ui-current-family="{{ $renderFamily }}">
                    @foreach($uiFamilies as $family => $option)
                        <button class="crmui-ui-switch-button {{ $renderFamily === $family ? 'is-active' : '' }}"
                                type="button"
                                data-ui-target="{{ $family }}"
                                data-crmui-ui-target="{{ $family }}"
                                data-ui-url="{{ $option['url'] }}"
                                aria-pressed="{{ $renderFamily === $family ? 'true' : 'false' }}"
                                title="{{ $option['label'] }}">
                            <i data-lucide="{{ $option['icon'] }}"></i><span>{{ $option['label'] }}</span>
                        </button>
                    @endforeach
                </div>
                @include('partials.theme-picker', ['themePickerCompact' => true])
                <a class="crmui-tool-button" href="{{ route($routeNames['logout']) }}"><i data-lucide="log-out"></i><span>{{ __('auth.logout') }}</span></a>
            </div>
        </header>
        <main class="crmui-main">@yield('content')</main>
    </div>
</div>
<script src="{{ asset('/js/vendor/jquery/jquery-3.6.0.min.js') }}"></script>
<script src="{{ asset('/js/vendor/layui-v2.13.5/layui/layui.js') }}"></script>
<script src="{{ asset('/js/apps/crmui/front.js') }}?v=2026072501"></script>
</body>
</html>
