@php
    $isFrame = request()->boolean('frame') || request()->boolean('iframe');
    $pageTitle = trim($__env->yieldContent('title', __('common.dashboard')));
    $pageBreadcrumb = trim($__env->yieldContent('breadcrumb', $pageTitle));
    $frameSrc = request()->fullUrlWithQuery(['frame' => 1]);
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1">
    <title>{{ __('common.system_name') }} - {{ $pageTitle }}</title>
    <script src="{{ asset('/js/common/theme-sync.js') }}?v=2026052908"></script>
    <link rel="stylesheet" href="{{ asset('/js/common/layui-v2.13.5/layui/css/layui.css') }}">
    <link rel="stylesheet" href="{{ asset('/css/front/style.css') }}?v=2026052918">
    @yield('styles')
</head>
<body class="{{ $isFrame ? 'front-frame-body' : 'layui-layout-body front-shell-body' }}">
@if($isFrame)
<div class="front-frame-page">
    <div class="front-page-content">
        @yield('content')
    </div>
</div>

<script src="{{ asset('/js/common/jquery/jquery-3.6.0.min.js') }}"></script>
<script src="{{ asset('/js/common/layui-v2.13.5/layui/layui.js') }}"></script>
<script src="{{ asset('/js/common/i18n.js') }}?v=2026052907"></script>
<script src="{{ asset('/js/common/ajax.js') }}"></script>
<script src="{{ asset('/js/common/table-common.js') }}"></script>
<script src="{{ asset('/js/common/date-range-shortcuts.js') }}"></script>
<script>
    window.__CRM_FRAME_PAGE = {
        title: @json($pageTitle),
        breadcrumb: @json($pageBreadcrumb),
        path: window.location.pathname
    };
    if (window.CrmLang && CrmLang.loadLanguage) {
        CrmLang.loadLanguage(CrmLang.getLocale());
    }
    if (window.parent && window.parent !== window) {
        window.parent.postMessage({
            type: 'crm:frame-page',
            title: window.__CRM_FRAME_PAGE.title,
            breadcrumb: window.__CRM_FRAME_PAGE.breadcrumb,
            path: window.__CRM_FRAME_PAGE.path
        }, window.location.origin);
    }
    $(document).on('click', 'a[href^="/front/"]', function (event) {
        var href = $(this).attr('href');
        if (!href || href.indexOf('/front/login') === 0 || href.indexOf('/front/register') === 0) {
            return;
        }
        if (window.parent && window.parent !== window) {
            event.preventDefault();
            window.parent.postMessage({
                type: 'crm:frame-navigate',
                url: href,
                title: $.trim($(this).text())
            }, window.location.origin);
        }
    });
</script>
@yield('scripts')
</body>
</html>
@else
<div class="layui-layout layui-layout-admin">
    <div class="layui-header">
        <div class="layui-logo" data-translate="common.systemName">{{ __('common.system_name') }}</div>
        <!-- Req 11: Right Header - language/skin use ICONS only with hover dropdowns -->
        <ul class="layui-nav layui-layout-right">
            <li class="layui-nav-item">
                <a href="javascript:;" title="{{ __('common.language') }}"><i class="layui-icon layui-icon-website"></i></a>
                <dl class="layui-nav-child">
                    <dd><a href="javascript:;" class="lang-switch" data-lang="zh-CN" data-translate="common.langZh">{{ __('common.lang_zh') }}</a></dd>
                    <dd><a href="javascript:;" class="lang-switch" data-lang="en" data-translate="common.langEn">{{ __('common.lang_en') }}</a></dd>
                </dl>
            </li>
            <li class="layui-nav-item">
                <a href="javascript:;" title="{{ __('front.ui_style') }}"><i class="layui-icon layui-icon-template-1"></i></a>
                <dl class="layui-nav-child">
                    <dd><a href="javascript:;" class="crm-style-switch" data-style="layui">▣ Layui 风格</a></dd>
                    <dd><a href="javascript:;" class="crm-style-switch" data-style="naive">□ Naive 风格</a></dd>
                </dl>
            </li>
            <li class="layui-nav-item">
                <a href="javascript:;" title="{{ __('front.skin_mode') }}"><i class="layui-icon layui-icon-theme"></i><span id="frontThemeBadge" class="front-theme-badge"></span></a>
                <dl class="layui-nav-child">
                    <dd><a href="javascript:;" class="theme-switch crm-naive-skin-switch" data-theme="light" data-skin="light">☀ 浅色</a></dd>
                    <dd><a href="javascript:;" class="theme-switch crm-naive-skin-switch" data-theme="dark" data-skin="dark">☾ 深色</a></dd>
                    <dd><a href="javascript:;" class="theme-switch crm-naive-skin-switch" data-theme="sea" data-skin="sea">≋ 海蓝</a></dd>
                    <dd><a href="javascript:;" class="theme-switch crm-naive-skin-switch" data-theme="warm" data-skin="warm">◐ 暖色</a></dd>
                    <dd><a href="javascript:;" class="theme-switch crm-naive-skin-switch" data-theme="contrast" data-skin="contrast">▣ 高对比</a></dd>
                </dl>
            </li>
            <li class="layui-nav-item">
                <a href="javascript:;" id="userNameHeader">
                    <img id="userAvatarHeader" src="{{ asset('/images/default-avatar.svg') }}" class="layui-nav-img">
                    <span id="userNameLabel" data-translate="common.user">{{ __('common.user') }}</span>
                </a>
                <dl class="layui-nav-child">
                    <dd><a href="{{ url('/front/profile') }}" class="J_frameLink" data-title="{{ __('front.profile') }}" data-breadcrumb="{{ __('breadcrumb.front_profile') }}" data-translate="menu.myProfile">{{ __('front.profile') }}</a></dd>
                    <hr>
                    <dd><a href="javascript:;" id="logoutBtn" data-translate="common.logout">{{ __('auth.logout') }}</a></dd>
                </dl>
            </li>
        </ul>
    </div>

    <div class="layui-side layui-bg-black">
        <div class="layui-side-scroll">
            <ul class="layui-nav layui-nav-tree" lay-filter="sideMenu" id="sideMenu">
                <!-- Menus loaded via AJAX -->
            </ul>
        </div>
    </div>

    <div class="layui-body">
        <div class="front-frame-shell">
            <iframe id="contentFrame" name="contentFrame" src="{{ $frameSrc }}" title="{{ $pageTitle }}"></iframe>
        </div>
    </div>

    <div class="layui-footer">
        <span data-translate="common.copyrightFront">{{ __('common.copyright_front') }}</span>
    </div>
</div>

<script src="{{ asset('/js/common/jquery/jquery-3.6.0.min.js') }}"></script>
<script src="{{ asset('/js/common/layui-v2.13.5/layui/layui.js') }}"></script>
<script src="{{ asset('/js/common/i18n.js') }}?v=2026052907"></script>
<script src="{{ asset('/js/common/ajax.js') }}"></script>
<script src="{{ asset('/js/common/table-common.js') }}"></script>
<script src="{{ asset('/js/common/date-range-shortcuts.js') }}"></script>
<script src="{{ asset('/js/front/layui/layout.js') }}?v=2026052918"></script>
</body>
</html>
@endif
