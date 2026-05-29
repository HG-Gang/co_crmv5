<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', __('auth.admin_panel')) - {{ __('common.system_name') }}</title>
    <script src="{{ asset('js/common/theme-sync.js') }}?v=2026052908"></script>
    <link rel="stylesheet" href="{{ asset('js/common/layui-v2.13.5/layui/css/layui.css') }}">
    <link rel="stylesheet" href="{{ asset('css/common/theme-sync.css') }}?v=2026052908">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        html, body { height: 100%; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif; }
        .admin-header { background: #2d3a4b; color: #fff; height: 60px; display: flex; align-items: center; padding: 0 20px; justify-content: space-between; position: fixed; top: 0; left: 0; right: 0; z-index: 1000; }
        .admin-header .logo { font-size: 20px; font-weight: bold; color: #409eff; }
        .admin-header .header-right { display: flex; align-items: center; gap: 15px; }
        .admin-header .header-right a { color: #bfcbd9; text-decoration: none; font-size: 14px; }
        .admin-header .header-right a:hover { color: #fff; }
        .admin-sidebar { width: 220px; background: #304156; position: fixed; top: 60px; left: 0; bottom: 0; overflow-y: auto; z-index: 999; }
        .admin-sidebar .layui-nav { background: transparent; padding-top: 10px; }
        .admin-sidebar .layui-nav .layui-nav-item a { color: #bfcbd9; font-size: 14px; padding: 0 20px; height: 46px; line-height: 46px; }
        .admin-sidebar .layui-nav .layui-nav-item a:hover { color: #409eff; background: rgba(0,0,0,0.1); }
        .admin-sidebar .layui-nav .layui-this a { color: #fff; background: #409eff !important; }
        .admin-sidebar .layui-nav .layui-nav-item .layui-icon { margin-right: 8px; font-size: 16px; }
        .admin-main { margin-left: 220px; margin-top: 60px; padding: 20px; min-height: calc(100vh - 60px); background: #f0f2f5; }
        .admin-main .page-header { margin-bottom: 20px; }
        .admin-main .page-header h2 { font-size: 20px; color: #333; font-weight: 500; }
        .content-card { background: #fff; border-radius: 4px; padding: 20px; margin-bottom: 20px; box-shadow: 0 1px 4px rgba(0,0,0,0.08); }
        .layui-table th { background: #fafafa; font-weight: 600; }
        .stat-card { display: flex; gap: 20px; flex-wrap: wrap; margin-bottom: 20px; }
        .stat-item { flex: 1; min-width: 200px; background: #fff; padding: 20px; border-radius: 4px; box-shadow: 0 1px 4px rgba(0,0,0,0.08); }
        .stat-item .stat-number { font-size: 28px; font-weight: bold; color: #409eff; }
        .stat-item .stat-label { font-size: 14px; color: #999; margin-top: 5px; }
        @yield('style')
    </style>
</head>
<body>
    <!-- Header -->
    <div class="admin-header">
        <div class="logo">{{ __('common.system_name') }} {{ __('auth.admin_panel') }}</div>
        <div class="header-right">
            <a href="javascript:;" class="legacy-admin-lang-switch" data-lang="zh-CN">{{ __('common.lang_zh') }}</a>
            <a href="javascript:;" class="legacy-admin-lang-switch" data-lang="en">{{ __('common.lang_en') }}</a>
            <span style="color:#bfcbd9;">|</span>
            <span style="color:#bfcbd9;">{{ Auth::guard('admin')->user()->username }}</span>
            <a href="{{ route('admin.password.form') }}">{{ __('auth.change_password') }}</a>
            <a href="javascript:;" id="adminLogoutBtn">{{ __('auth.logout') }}</a>
            <form id="logout-form" action="{{ route('admin.logout') }}" method="POST" style="display:none;">@csrf</form>
        </div>
    </div>

    <!-- Sidebar -->
    <div class="admin-sidebar">
        <ul class="layui-nav layui-nav-tree" lay-filter="admin-nav">
            <li class="layui-nav-item {{ request()->routeIs('admin.dashboard*') ? 'layui-this' : '' }}">
                <a href="{{ route('admin.dashboard') }}"><i class="layui-icon layui-icon-home"></i>{{ __('auth.dashboard') }}</a>
            </li>
            <li class="layui-nav-item {{ request()->routeIs('admin.users*') ? 'layui-this' : '' }}">
                <a href="{{ route('admin.users.index') }}"><i class="layui-icon layui-icon-user"></i>{{ __('auth.user_management') }}</a>
            </li>
            <li class="layui-nav-item">
                <a href="{{ route('admin.password.form') }}"><i class="layui-icon layui-icon-password"></i>{{ __('auth.change_password') }}</a>
            </li>
        </ul>
    </div>

    <!-- Main Content -->
    <div class="admin-main">
        @if(session('success'))
        <div class="layui-bg-green" style="padding:10px 15px;margin-bottom:15px;border-radius:4px;">
            {{ session('success') }}
        </div>
        @endif

        @if($errors->any())
        <div class="layui-bg-red" style="padding:10px 15px;margin-bottom:15px;border-radius:4px;">
            @foreach($errors->all() as $error)
                <p>{{ $error }}</p>
            @endforeach
        </div>
        @endif

        @yield('content')
    </div>

    <script src="{{ asset('js/common/jquery/jquery-3.6.0.min.js') }}"></script>
    <script src="{{ asset('js/common/layui-v2.13.5/layui/layui.js') }}"></script>
    <script>
        layui.use(['element', 'layer', 'jquery'], function(){
            var element = layui.element;
            var $ = layui.jquery;

            $('.legacy-admin-lang-switch').on('click', function() {
                localStorage.setItem('crm_locale', $(this).data('lang'));
            });

            $('#adminLogoutBtn').on('click', function() {
                $('#logout-form').trigger('submit');
            });
        });
    </script>
    @yield('scripts')
</body>
</html>
