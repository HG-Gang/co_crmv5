<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1">
    <title>{{ __('common.admin_system_name') }} - {{ __('auth.login') }}</title>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <script src="{{ asset('/js/common/theme-sync.js') }}?v=2026052908"></script>
    <link rel="stylesheet" href="{{ asset('/js/common/layui-v2.13.5/layui/css/layui.css') }}">
    <link rel="stylesheet" href="{{ asset('/css/admin/style.css') }}?v=2026052908">
</head>
<body class="layui-layout-body admin-login-body">
<div class="login-container">
    <div class="login-logo" data-translate="system_name">{{ __('common.admin_system_name') }}</div>
    
    <div class="layui-text login-language">
        <a href="javascript:;" class="lang-switch" data-lang="zh-CN" data-translate="lang_zh">{{ __('common.lang_zh') }}</a> | 
        <a href="javascript:;" class="lang-switch" data-lang="en" data-translate="lang_en">{{ __('common.lang_en') }}</a>
    </div>
    
    <form class="layui-form" action="">
        <div class="layui-form-item">
            <input type="text" name="username" required lay-verify="required" data-translate-placeholder="username" placeholder="{{ __('auth.username') }}" autocomplete="off" class="layui-input">
        </div>
        <div class="layui-form-item">
            <input type="password" name="password" required lay-verify="required" data-translate-placeholder="password" placeholder="{{ __('auth.password') }}" autocomplete="off" class="layui-input">
        </div>
        <div class="layui-form-item">
            <button class="layui-btn layui-btn-fluid layui-bg-green" lay-submit lay-filter="adminLogin" data-translate="login">{{ __('auth.login') }}</button>
        </div>
    </form>
</div>

<script src="{{ asset('/js/common/jquery/jquery-3.6.0.min.js') }}"></script>
<script src="{{ asset('/js/common/layui-v2.13.5/layui/layui.js') }}"></script>
<script src="{{ asset('/js/admin/layui/common.js') }}"></script>
<script src="{{ asset('/js/admin/layui/auth/login.js') }}"></script>
</body>
</html>
