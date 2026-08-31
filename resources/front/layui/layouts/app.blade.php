{{--
Created by PhpStorm.
Project name co_crmv5.
User: Huang Gang
Date: 2026/08/28
Time: 00:46
--}}
@php
    $isFrame = request()->boolean('frame') || request()->boolean('iframe');
    $pageTitle = trim($__env->yieldContent('title', __('common.dashboard')));
    $pageBreadcrumb = trim($__env->yieldContent('breadcrumb', $pageTitle));
    // POST 兼容入口由控制器传入可 GET 的 iframe 地址；普通 GET 页面仍使用当前页面的 frame 版本。
    $frameSrc = $frameSrc ?? request()->fullUrlWithQuery(['frame' => 1]);
    $frontDashboardPath = parse_url(route('front_page_dashboard'), PHP_URL_PATH);
    $frontLoginPath = parse_url(route('front_page_login'), PHP_URL_PATH);
    $frontRegisterPath = parse_url(route('front_page_register'), PHP_URL_PATH);
    $frontPathSegments = explode('/', trim($frontDashboardPath, '/'));
    $frontPagePrefix = isset($frontPathSegments[0]) && $frontPathSegments[0] !== '' ? '/' . $frontPathSegments[0] : '';
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1">
    <title>{{ __('common.system_name') }} - {{ $pageTitle }}</title>
    <link rel="stylesheet" href="{{ asset('/js/vendor/layui-v2.13.5/layui/css/layui.css') }}">
    <link rel="stylesheet" href="{{ asset('/css/front/style.css') }}?v=2026082801">
    <link rel="stylesheet" href="{{ asset('/css/common/crm-design-system.css') }}?v=2026082801">
    {{-- 统一上传组件样式：前后台共用同一份 Layui 上传视觉，颜色跟随全局皮肤令牌。 --}}
    <link rel="stylesheet" href="{{ asset('/css/common/crm-upload.css') }}?v=2026082801">
    @include('partials.lucide-assets')
    @yield('styles')
    <link rel="stylesheet" href="{{ asset('/css/layui/visual-c.css') }}?v=2026080701">
    @include('partials.theme-assets')
</head>
<body class="{{ $isFrame ? 'front-frame-body crm-ui-front-shell' : 'layui-layout-body front-shell-body crm-ui-front-shell' }}" data-ui-family="layui" data-ui-surface="front" data-visual-direction="c">
@if($isFrame)
<div class="front-frame-page">
    @if(! $__env->hasSection('frame-theme-picker-provided'))
    <div class="crm-theme-picker-host">
        @include('partials.theme-picker', ['themePickerCompact' => true, 'themePickerLabel' => false])
    </div>
    @endif
    <div class="front-page-content">
        @yield('content')
    </div>
</div>

<script src="{{ asset('/js/vendor/jquery/jquery-3.6.0.min.js') }}"></script>
<script src="{{ asset('/js/vendor/layui-v2.13.5/layui/layui.js') }}"></script>
@include('partials.frontend-routes')
<script src="{{ asset('/js/shared/i18n.js') }}?v=2026060402"></script>
<script src="{{ asset('/js/shared/ajax.js') }}?v=2026060702"></script>
@include('partials.visual-audit-fixture')
<script src="{{ asset('/js/shared/table-common.js') }}"></script>
<script src="{{ asset('/js/shared/date-range-shortcuts.js') }}"></script>
{{-- 字段级校验提示与统一上传组件都是跨页面共享能力，必须在业务页面脚本之前注册。 --}}
<script src="{{ asset('/js/shared/form-field-errors.js') }}?v=2026082801"></script>
<script src="{{ asset('/js/shared/layui-upload.js') }}?v=2026082801"></script>
<script src="{{ asset('/js/apps/front/layui/pages.js') }}?v=2026082801"></script>
<script src="{{ asset('/js/apps/front/layui/stat-animate.js') }}?v=2026080101"></script>
<script type="application/json" id="crm-frame-page-config">
{"title": @json($pageTitle), "breadcrumb": @json($pageBreadcrumb), "frontPagePrefix": @json($frontPagePrefix), "frontLoginPath": @json($frontLoginPath), "frontRegisterPath": @json($frontRegisterPath)}
</script>
@yield('scripts')
</body>
</html>
@else
<div class="layui-layout layui-layout-admin" id="frontLayuiShell">
    <div class="layui-header">
        <div class="layui-logo" data-translate="common.systemName">{{ __('common.system_name') }}</div>
        <ul class="layui-nav layui-layout-left">
            <li class="layui-nav-item" lay-unselect>
                <button type="button" class="visual-c-sidebar-toggle" id="frontSidebarToggle" data-layui-sidebar-toggle aria-controls="frontLayuiSidebar" aria-expanded="false" title="{{ __('common.toggle_sidebar') }}">
                    <i data-lucide="menu"></i>
                </button>
            </li>
        </ul>
        <!-- Right Header -->
        <ul class="layui-nav layui-layout-right">
            <li class="layui-nav-item crm-preference-nav-item" lay-unselect>
                @include('partials.language-picker', ['languagePickerCompact' => true])
            </li>
            <li class="layui-nav-item">
                <a href="javascript:;" title="{{ __('front.ui_style') }}"><i data-lucide="wallet-cards"></i></a>
                <dl class="layui-nav-child">
                    <dd><a href="javascript:;" class="crm-style-switch" data-style="layui"><i data-lucide="wallet-cards"></i> Layui</a></dd>
                    <dd><a href="javascript:;" class="crm-style-switch" data-style="crmui"><i data-lucide="gauge"></i> CrmUI</a></dd>
                    <dd><a href="javascript:;" class="crm-style-switch" data-style="naive"><i data-lucide="sparkles"></i> Naive</a></dd>
                </dl>
            </li>
            <li class="layui-nav-item crm-theme-picker-nav-item">
                @include('partials.theme-picker', ['themePickerCompact' => true, 'themePickerId' => 'crm-shell-theme-picker'])
            </li>
            <li class="layui-nav-item">
                <a href="javascript:;" id="userNameHeader">
                    <img id="userAvatarHeader" src="{{ asset('/images/default-avatar.svg') }}" class="layui-nav-img">
                    <span id="userNameLabel" data-translate="common.user">{{ __('common.user') }}</span>
                </a>
                <dl class="layui-nav-child">
                    <dd><a href="{{ route('front_page_profile') }}" class="J_frameLink" data-title="{{ __('front.profile') }}" data-breadcrumb="{{ __('breadcrumb.front_profile') }}" data-translate="menu.myProfile">{{ __('front.profile') }}</a></dd>
                    <hr>
                    <dd><a href="javascript:;" id="logoutBtn" data-translate="common.logout">{{ __('auth.logout') }}</a></dd>
                </dl>
            </li>
        </ul>
    </div>

    <div class="layui-side layui-bg-black" id="frontLayuiSidebar">
        <div class="layui-side-scroll">
            <ul class="layui-nav layui-nav-tree" lay-filter="sideMenu" id="sideMenu">
                <!-- Menus loaded via AJAX -->
            </ul>
        </div>
    </div>
    <button type="button" class="visual-c-sidebar-scrim" data-layui-sidebar-dismiss aria-label="{{ __('common.close') }}"></button>

    <div class="layui-body">
        <div class="front-frame-shell">
            @if(!empty($legacyFormIntentNonce))
                <input type="hidden" name="idempotency_key" value="{{ $legacyFormIntentNonce }}" data-legacy-form-intent>
            @endif
            <iframe id="contentFrame" name="contentFrame" src="{{ $frameSrc }}" title="{{ $pageTitle }}"></iframe>
        </div>
    </div>

    <div class="layui-footer">
        <span data-translate="common.copyrightFront">{{ __('common.copyright_front') }}</span>
    </div>
</div>

<script src="{{ asset('/js/vendor/jquery/jquery-3.6.0.min.js') }}"></script>
<script src="{{ asset('/js/vendor/layui-v2.13.5/layui/layui.js') }}"></script>
@include('partials.frontend-routes')
<script src="{{ asset('/js/shared/i18n.js') }}?v=2026060402"></script>
<script src="{{ asset('/js/shared/ajax.js') }}?v=2026060702"></script>
@include('partials.visual-audit-fixture')
<script src="{{ asset('/js/shared/table-common.js') }}"></script>
<script src="{{ asset('/js/shared/date-range-shortcuts.js') }}"></script>
<script src="{{ asset('/js/apps/front/layui/layout.js') }}?v=2026080801"></script>
</body>
</html>
@endif

