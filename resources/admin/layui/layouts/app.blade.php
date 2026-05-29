<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1">
    <title>{{ __('common.admin_system_name') }} - @yield('title', __('common.dashboard'))</title>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <script src="{{ asset('/js/common/theme-sync.js') }}?v=2026052908"></script>
    <link rel="stylesheet" href="{{ asset('/js/common/layui-v2.13.5/layui/css/layui.css') }}">
    <link rel="stylesheet" href="{{ asset('/css/admin/style.css') }}?v=2026052908">
    @yield('styles')
</head>
<body class="layui-layout-body">
<div class="layui-layout layui-layout-admin">
    <div class="layui-header">
        <div class="layui-logo" data-translate="common.adminSystemName">{{ __('common.admin_system_name') }}</div>
        <!-- Header Left -->
        <ul class="layui-nav layui-layout-left">
            <li class="layui-nav-item" lay-unselect>
                <a href="javascript:;" id="toggleSidebar" data-translate-title="common.toggleSidebar">
                    <i class="layui-icon layui-icon-shrink-right"></i>
                </a>
            </li>
        </ul>
        <!-- Header Right -->
        <ul class="layui-nav layui-layout-right">
            <li class="layui-nav-item">
                <a href="javascript:;" data-translate="common.language">{{ __('common.language') }}</a>
                <dl class="layui-nav-child">
                    <dd><a href="javascript:;" class="lang-switch" data-lang="zh-CN" data-translate="common.langZh">{{ __('common.lang_zh') }}</a></dd>
                    <dd><a href="javascript:;" class="lang-switch" data-lang="en" data-translate="common.langEn">{{ __('common.lang_en') }}</a></dd>
                </dl>
            </li>
            <li class="layui-nav-item">
                <a href="javascript:;">界面</a>
                <dl class="layui-nav-child">
                    <dd><a href="javascript:;" class="crm-style-switch" data-style="layui">Layui 风格</a></dd>
                    <dd><a href="javascript:;" class="crm-style-switch" data-style="naive">Naive 风格</a></dd>
                </dl>
            </li>
            <li class="layui-nav-item">
                <a href="javascript:;"><i class="layui-icon layui-icon-theme"></i> 皮肤<span id="adminThemeBadge" class="admin-theme-badge"></span></a>
                <dl class="layui-nav-child">
                    <dd><a href="javascript:;" class="crm-theme-switch crm-naive-skin-switch" data-skin="light">☀ 浅色</a></dd>
                    <dd><a href="javascript:;" class="crm-theme-switch crm-naive-skin-switch" data-skin="dark">☾ 深色</a></dd>
                    <dd><a href="javascript:;" class="crm-theme-switch crm-naive-skin-switch" data-skin="sea">≋ 海蓝</a></dd>
                    <dd><a href="javascript:;" class="crm-theme-switch crm-naive-skin-switch" data-skin="warm">◐ 暖色</a></dd>
                    <dd><a href="javascript:;" class="crm-theme-switch crm-naive-skin-switch" data-skin="contrast">▣ 高对比</a></dd>
                </dl>
            </li>
            <li class="layui-nav-item">
                <a href="javascript:;" id="adminUsername" data-translate="common.admin">{{ __('common.admin') }}</a>
                <dl class="layui-nav-child">
                    <dd><a href="/admin/profile/edit" data-translate="profile.title">{{ __('front.profile') }}</a></dd>
                    <dd><a href="/admin/profile/change-password" data-translate="profile.changePassword">{{ __('front.change_password') }}</a></dd>
                    <hr>
                    <dd><a href="javascript:;" id="logoutBtn" data-translate="common.logout">{{ __('auth.logout') }}</a></dd>
                </dl>
            </li>
        </ul>
    </div>

    <div class="layui-side layui-bg-black">
        <div class="layui-side-scroll">
            <ul class="layui-nav layui-nav-tree" lay-filter="adminMenu" id="adminMenu">
                <!-- Menus will be loaded via AJAX -->
            </ul>
        </div>
    </div>

    <div class="layui-body">
        <div class="admin-page-shell">
            <span class="layui-breadcrumb" id="breadcrumb">
                <a href="/admin/dashboard" data-translate="menu.dashboard">{{ __('common.dashboard') }}</a>
            </span>
            <hr>
            @yield('content')
        </div>
    </div>

    <div class="layui-footer">
        <span data-translate="common.copyrightAdmin">{{ __('common.copyright_admin') }}</span>
    </div>
</div>

<script src="{{ asset('/js/common/jquery/jquery-3.6.0.min.js') }}"></script>
<script src="{{ asset('/js/common/layui-v2.13.5/layui/layui.js') }}"></script>
<script src="{{ asset('/js/common/i18n.js') }}?v=2026052907"></script>
<script src="{{ asset('/js/common/ajax.js') }}"></script>
<script src="{{ asset('/js/common/table-common.js') }}"></script>
<script src="{{ asset('/js/admin/layui/layout.js') }}?v=2026052908"></script>
@yield('scripts')
</body>
</html>
