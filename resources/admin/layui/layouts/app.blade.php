{{--
Created by PhpStorm.
Project name co_crmv5.
User: Huang Gang
Date: 2026/08/28
Time: 00:47
--}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1">
    <title>{{ __('common.admin_system_name') }} - @yield('title', __('common.dashboard'))</title>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="stylesheet" href="{{ asset('/js/vendor/layui-v2.13.5/layui/css/layui.css') }}">
    <link rel="stylesheet" href="{{ asset('/css/admin/style.css') }}?v=2026080702">
    <link rel="stylesheet" href="{{ asset('/css/common/crm-design-system.css') }}?v=2026082801">
    {{-- 统一上传组件样式：后台上传入口与前台复用同一份 Layui 上传视觉。 --}}
    <link rel="stylesheet" href="{{ asset('/css/common/crm-upload.css') }}?v=2026082801">
    @include('partials.lucide-assets')
    @yield('styles')
    <link rel="stylesheet" href="{{ asset('/css/layui/visual-c.css') }}?v=2026080701">
    @include('partials.theme-assets')
</head>
<body class="layui-layout-body crm-admin-workbench crm-ui-admin-shell" data-render-mode="blade" data-ui-reference="Vben Admin, Vue Naive Admin, Naive UI Admin, Ant Design Pro, Arco Design Pro" data-shell-label="后台工作台" data-ui-family="layui" data-ui-surface="admin" data-visual-direction="c">
<div class="layui-layout layui-layout-admin crm-admin-shell" id="adminLayuiShell">
    <div class="layui-header crm-admin-topbar">
        <div class="layui-logo crm-admin-brand" data-translate="common.adminSystemName">
            <span class="crm-admin-brand-mark">CO</span>
            <span class="crm-admin-brand-text">{{ __('common.admin_system_name') }}</span>
        </div>

        <ul class="layui-nav layui-layout-left">
            <li class="layui-nav-item" lay-unselect>
                <a href="javascript:;" id="toggleSidebar" data-layui-sidebar-toggle aria-controls="adminLayuiSidebar" aria-expanded="false" title="{{ __('common.toggle_sidebar') ?? '折叠菜单' }}">
                    <i data-lucide="panel-left-close"></i>
                </a>
            </li>
        </ul>

        <ul class="layui-nav layui-layout-right">
            <li class="layui-nav-item crm-preference-nav-item" lay-unselect>
                @include('partials.language-picker', ['languagePickerCompact' => true])
            </li>
            <li class="layui-nav-item">
                <a href="javascript:;">界面</a>
                <dl class="layui-nav-child">
                    <dd><a href="javascript:;" class="crm-style-switch" data-style="layui">Layui 风格</a></dd>
                    <dd><a href="javascript:;" class="crm-style-switch" data-style="crmui">CrmUI 风格</a></dd>
                </dl>
            </li>
            <!-- 主题切换入口：固定声明可读中文 title，避免仅依赖语言包时丢失可访问名称。 -->
            <li class="layui-nav-item crm-theme-picker-nav-item" lay-unselect title="主题">
                @include('partials.theme-picker', ['themePickerCompact' => true])
            </li>
            <li class="layui-nav-item">
                <a href="javascript:;" id="adminUsername" data-translate="common.admin">{{ __('common.admin') }}</a>
                <dl class="layui-nav-child">
                    <dd><a href="{{ route('admin_page_profile_edit') }}" data-translate="profile.title">{{ __('front.profile') }}</a></dd>
                    <dd><a href="{{ route('admin_page_profile_change_password') }}" data-translate="profile.changePassword">{{ __('front.change_password') }}</a></dd>
                    <hr>
                    <dd><a href="javascript:;" id="logoutBtn" data-translate="common.logout">{{ __('auth.logout') }}</a></dd>
                </dl>
            </li>
        </ul>
    </div>

    <div class="layui-side layui-bg-black crm-admin-sidebar" id="adminLayuiSidebar">
        <div class="layui-side-scroll">
            <ul class="layui-nav layui-nav-tree" lay-filter="adminMenu" id="adminMenu">
                <!-- 菜单由 /api/admin/menus 接口加载，Blade 只负责后台外壳渲染。 -->
            </ul>
        </div>
    </div>
    <button type="button" class="visual-c-sidebar-scrim" data-layui-sidebar-dismiss aria-label="{{ __('common.close') }}"></button>

    <div class="layui-body crm-admin-body">
        <div class="admin-page-shell crm-admin-main">
            {{-- 后台页头：参考 Vben/Ant/Arco 的工作台页头结构，左侧承载页面标题，右侧承载面包屑和后续工具按钮。 --}}
            <div class="crm-admin-page-head">
                <div class="crm-admin-page-head-main">
                    <div class="crm-admin-page-kicker">后台工作台</div>
                    <h1 class="crm-admin-page-title">@yield('title', __('common.dashboard'))</h1>
                </div>
                <div class="crm-admin-page-head-tools">
                    <span class="layui-breadcrumb crm-admin-breadcrumb" id="breadcrumb">
                        <a href="{{ route('admin_page_dashboard') }}" data-translate="menu.dashboard">{{ __('common.dashboard') }}</a>
                    </span>
                </div>
            </div>
            @yield('content')
        </div>
    </div>

    <div class="layui-footer">
        <span data-translate="common.copyrightAdmin">{{ __('common.copyright_admin') }}</span>
    </div>
</div>

{{-- 关键资源：同步加载保证框架初始化 --}}
<script src="{{ asset('/js/vendor/jquery/jquery-3.6.0.min.js') }}"></script>
<script src="{{ asset('/js/vendor/layui-v2.13.5/layui/layui.js') }}"></script>
@include('partials.frontend-routes')
<script src="{{ asset('/js/shared/i18n.js') }}?v=2026060306"></script>
<script src="{{ asset('/js/shared/ajax.js') }}?v=2026060702"></script>
@include('partials.visual-audit-fixture')
<script src="{{ asset('/js/shared/table-common.js') }}"></script>
{{-- 字段级校验提示与统一上传组件为跨页面共享能力，必须在业务页面脚本之前注册。 --}}
<script src="{{ asset('/js/shared/form-field-errors.js') }}?v=2026082801"></script>
<script src="{{ asset('/js/shared/layui-upload.js') }}?v=2026082801"></script>
<script src="{{ asset('/js/apps/admin/layui/layout.js') }}?v=2026080801"></script>
<script src="{{ asset('/js/apps/admin/layui/pages.js') }}?v=2026082801"></script>
{{-- 非关键资源：延迟加载提升首屏速度 --}}
<script src="{{ asset('/js/apps/front/layui/stat-animate.js') }}?v=2026080101" defer></script>
@yield('scripts')
</body>
</html>

