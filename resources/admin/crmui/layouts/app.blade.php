{{--
Created by PhpStorm.
Project name co_crmv5.
User: Huang Gang
Date: 2026/08/28
Time: 00:48
--}}
{{--
 * 后台 CrmUI 应用布局。
 *
 * 文件功能：
 * - 后台 crmui 新 UI 的页面骨架：品牌区、侧边栏导航、顶栏（语言/主题/退出）、内容区。
 * - 侧边栏菜单项按 navGroups 数据渲染，图标统一使用 Lucide（data-lucide 属性）。
 *
 * 适用场景：
 * - 后台管理员登录后访问 /admin-crmui/* 的所有模块页面。
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
    $pageTitle = $page['title'] ?? __('crmui.common.admin_console');
    $isFrame = !empty($page['frame']);
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $pageTitle }} - {{ __('crmui.common.admin_console') }}</title>
    <link rel="stylesheet" href="{{ asset('/js/vendor/layui-v2.13.5/layui/css/layui.css') }}">
    <link rel="stylesheet" href="{{ asset('/css/crmui/tokens.css') }}?v=2026070401">
    <link rel="stylesheet" href="{{ asset('/css/crmui/admin.css') }}?v=2026080702">
{{-- 统一上传组件样式：后台 CrmUI 家族与 Layui 家族共用同一份上传视觉。 --}}
<link rel="stylesheet" href="{{ asset('/css/common/crm-upload.css') }}?v=2026082801">
    @include('partials.lucide-assets')
    @yield('styles')
    <link rel="stylesheet" href="{{ asset('/css/crmui/visual-c.css') }}?v=2026080703">
    @include('partials.theme-assets')
</head>
<body class="crmui-body crmui-admin-body {{ $isFrame ? 'crmui-frame-body' : '' }}" data-crmui-surface="admin" data-ui-family="crmui" data-ui-surface="admin" data-visual-direction="c">
@if($isFrame)
    <main class="crmui-frame-main">
        @yield('content')
    </main>
@else
    <div class="crmui-shell">
        <aside class="crmui-sidebar" id="crmuiSidebar" tabindex="-1">
            <a class="crmui-brand" href="{{ route('admin_crmui_app', ['path' => 'dashboard']) }}">
                <span class="crmui-brand-mark">CO</span>
                <span>
                    <strong>{{ __('crmui.common.admin_console') }}</strong>
                    <small>{{ __('crmui.common.blade_ui') }}</small>
                </span>
            </a>

            <nav class="crmui-nav" aria-label="{{ __('crmui.nav.primary') }}">
                @foreach($navGroups as $group)
                    <section class="crmui-nav-group">
                        <div class="crmui-nav-title">{{ $group['title'] }}</div>
                        @foreach($group['items'] as $item)
                            <a class="crmui-nav-link {{ ($page['path'] ?? '') === $item['path'] ? 'is-active' : '' }}"
                               href="{{ route('admin_crmui_app', ['path' => $item['path']]) }}">
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
                    <span>{{ __('crmui.common.admin_console') }}</span>
                </div>
                <div class="crmui-topbar-actions">
                    @include('partials.language-picker', ['languagePickerCompact' => true])
                    @include('partials.theme-picker', ['themePickerCompact' => true])
                    <button class="crmui-tool-button is-primary" type="button" data-crmui-logout data-api-url="{{ route('admin_api_logout') }}"><i data-lucide="log-out"></i> {{ __('auth.logout') }}</button>
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
<script src="{{ asset('/js/apps/crmui/admin.js') }}?v=2026080705"></script>
@yield('scripts')
</body>
</html>
