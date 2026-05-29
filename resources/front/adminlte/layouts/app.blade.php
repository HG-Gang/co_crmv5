<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ config('app.name', 'CRM') }} | @yield('title', __('front.dashboard'))</title>

    <script src="{{ asset('js/common/theme-sync.js') }}?v=2026052908"></script>
    <!-- Google Font: Source Sans Pro -->
    <link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Source+Sans+Pro:300,400,400i,700&display=fallback">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="{{ asset('vendor/adminlte/plugins/fontawesome-free/css/all.min.css') }}">
    <!-- Theme style -->
    <link rel="stylesheet" href="{{ asset('vendor/adminlte/css/adminlte.min.css') }}">
    <link rel="stylesheet" href="{{ asset('css/common/theme-sync.css') }}?v=2026052908">
    <!-- Dark Mode style if cookie is set -->
    @if(request()->cookie('ui_style') === 'dark')
    <link rel="stylesheet" href="{{ asset('vendor/adminlte/plugins/overlayScrollbars/css/OverlayScrollbars.min.css') }}">
    <style>
        body { background-color: #454d55 !important; color: #fff; }
        .content-wrapper { background-color: #454d55 !important; color: #fff; }
        .main-header { background-color: #343a40 !important; border-bottom: 1px solid #4b545c !important; }
        .navbar-light .navbar-nav .nav-link { color: rgba(255,255,255,.75); }
    </style>
    @endif
    
    @yield('styles')
</head>
<body class="hold-transition sidebar-mini layout-fixed {{ request()->cookie('ui_style') === 'dark' ? 'dark-mode' : '' }}">
<div class="wrapper">

    <!-- Navbar -->
    <nav class="main-header navbar navbar-expand {{ request()->cookie('ui_style') === 'dark' ? 'navbar-dark' : 'navbar-white navbar-light' }}">
        <!-- Left navbar links -->
        <ul class="navbar-nav">
            <li class="nav-item">
                <a class="nav-link" data-widget="pushmenu" href="#" role="button"><i class="fas fa-bars"></i></a>
            </li>
        </ul>

        <!-- Right navbar links -->
        <ul class="navbar-nav ml-auto">
            <!-- Language Dropdown Menu -->
            <li class="nav-item dropdown">
                <a class="nav-link" data-toggle="dropdown" href="#">
                    <i class="fas fa-globe"></i>
                </a>
                <div class="dropdown-menu dropdown-menu-right p-0">
                    <a href="javascript:;" class="dropdown-item adminlte-lang-switch {{ app()->getLocale() == 'zh_CN' ? 'active' : '' }}" data-lang="zh-CN">
                        {{ __('common.lang_zh') }}
                    </a>
                    <a href="javascript:;" class="dropdown-item adminlte-lang-switch {{ app()->getLocale() == 'en' ? 'active' : '' }}" data-lang="en">
                        {{ __('common.lang_en') }}
                    </a>
                </div>
            </li>

            <!-- UI Style Switcher -->
            <li class="nav-item">
                <a class="nav-link" id="ui-style-switcher" href="#" role="button">
                    <i class="fas {{ request()->cookie('ui_style') === 'dark' ? 'fa-sun' : 'fa-moon' }}"></i>
                </a>
            </li>

            <!-- User Menu -->
            <li class="nav-item dropdown user-menu">
                <a href="#" class="nav-link dropdown-toggle" data-toggle="dropdown">
                    <img src="{{ asset('vendor/adminlte/img/user2-160x160.jpg') }}" class="user-image img-circle elevation-2 user-avatar-img" alt="User Image">
                    <span class="d-none d-md-inline user-display-name">{{ __('common.user') }}</span>
                </a>
                <ul class="dropdown-menu dropdown-menu-lg dropdown-menu-right">
                    <!-- User image -->
                    <li class="user-header bg-primary">
                        <img src="{{ asset('vendor/adminlte/img/user2-160x160.jpg') }}" class="img-circle elevation-2 user-avatar-img" alt="User Image">
                        <p>
                            <span class="user-display-name">{{ __('common.user') }}</span> - <span class="user-role">{{ __('admin.role') }}</span>
                            <small class="user-join-date">{{ __('admin.member_since') }}</small>
                        </p>
                    </li>
                    <!-- Menu Footer-->
                    <li class="user-footer">
                        <a href="{{ route('front.profile.index') }}" class="btn btn-default btn-flat">{{ __('front.profile') }}</a>
                        <a href="#" id="btn-logout" class="btn btn-default btn-flat float-right">{{ __('front.logout') }}</a>
                    </li>
                </ul>
            </li>
        </ul>
    </nav>
    <!-- /.navbar -->

    <!-- Main Sidebar Container -->
    <aside class="main-sidebar sidebar-dark-primary elevation-4">
        <!-- Brand Logo -->
        <a href="{{ route('front.dashboard') }}" class="brand-link">
            <img src="{{ asset('vendor/adminlte/img/AdminLTELogo.png') }}" alt="AdminLTE Logo" class="brand-image img-circle elevation-3" style="opacity: .8">
            <span class="brand-text font-weight-light">{{ config('app.name') }}</span>
        </a>

        <!-- Sidebar -->
        <div class="sidebar">
            <!-- Sidebar Menu (Dynamic) -->
            <nav class="mt-2">
                <ul class="nav nav-pills nav-sidebar flex-column" data-widget="treeview" role="menu" data-accordion="false" id="sidebar-menu">
                    <!-- Loaded by JS -->
                </ul>
            </nav>
            <!-- /.sidebar-menu -->
        </div>
        <!-- /.sidebar -->
    </aside>

    <!-- Content Wrapper. Contains page content -->
    <div class="content-wrapper">
        <!-- Content Header (Page header) -->
        <div class="content-header">
            <div class="container-fluid">
                <div class="row mb-2">
                    <div class="col-sm-6">
                        <h1 class="m-0">@yield('title', __('front.dashboard'))</h1>
                    </div>
                </div>
            </div>
        </div>
        <!-- /.content-header -->

        <!-- Main content -->
        <section class="content">
            <div class="container-fluid">
                @yield('content')
            </div>
        </section>
        <!-- /.content -->
    </div>
    <!-- /.content-wrapper -->

    <!-- Main Footer -->
    <footer class="main-footer">
        <strong>{{ __('common.copyright_front') }}</strong>
    </footer>
</div>
<!-- ./wrapper -->

<!-- REQUIRED SCRIPTS -->
<!-- jQuery -->
<script src="{{ asset('vendor/adminlte/plugins/jquery/jquery.min.js') }}"></script>
<!-- Bootstrap 4 (AdminLTE 3 uses BS4) -->
<script src="{{ asset('vendor/adminlte/plugins/bootstrap/js/bootstrap.bundle.min.js') }}"></script>
<!-- AdminLTE App -->
<script src="{{ asset('vendor/adminlte/js/adminlte.min.js') }}"></script>

<!-- Global variables for JS -->
<script>
    var LANG_DATA = {!! json_encode(__('front_js')) !!};
    var CURRENT_UI_STYLE = (window.CrmTheme && window.CrmTheme.get() === 'dark') ? 'dark' : '{{ request()->cookie('ui_style', 'light') }}';
</script>

<!-- Custom JS -->
<script src="{{ asset('js/front/adminlte/common.js') }}?v=2026052908"></script>
<script src="{{ asset('js/front/adminlte/layout.js') }}?v=2026052908"></script>

@yield('scripts')
</body>
</html>
