<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1">
    <title>{{ __('auth.forgot_password') }} - {{ __('common.system_name') }}</title>
    <script src="{{ asset('/js/common/theme-sync.js') }}?v=2026052908"></script>
    <link rel="stylesheet" href="{{ asset('/js/common/layui-v2.13.5/layui/css/layui.css') }}">
    <link rel="stylesheet" href="{{ asset('/css/front/style.css') }}?v=2026052908">
</head>
<body class="auth-wrapper">
<div class="auth-card">
    <h2 class="auth-title" data-translate="auth.forgot_password">{{ __('auth.forgot_password') }}</h2>
    <form class="layui-form" lay-filter="forgotForm">
        <div class="layui-form-item">
            <input type="email" name="email" required lay-verify="required|email" class="layui-input"
                   data-translate-placeholder="auth.email" placeholder="{{ __('auth.email') }}">
        </div>
        <div class="layui-form-item">
            <input type="text" name="code" required lay-verify="required" class="layui-input"
                   data-translate-placeholder="auth.reset_code" placeholder="{{ __('auth.reset_code') }}">
        </div>
        <div class="layui-form-item">
            <input type="password" name="password" required lay-verify="required" class="layui-input"
                   data-translate-placeholder="auth.newPassword" placeholder="{{ __('auth.new_password') }}">
        </div>
        <div class="layui-form-item">
            <input type="password" name="password_confirmation" required lay-verify="required" class="layui-input"
                   data-translate-placeholder="auth.confirmPassword" placeholder="{{ __('auth.confirm_password') }}">
        </div>
        <button class="layui-btn layui-btn-fluid layui-bg-blue" lay-submit lay-filter="forgotSubmit" data-translate="auth.send_reset_link">{{ __('auth.send_reset_link') }}</button>
    </form>
    <div class="auth-footer">
        <a href="/front/login" data-translate="auth.back_to_login">{{ __('auth.back_to_login') }}</a>
    </div>
</div>
<script src="{{ asset('/js/common/layui-v2.13.5/layui/layui.js') }}"></script>
<script src="{{ asset('/js/common/i18n.js') }}"></script>
<script src="{{ asset('/js/common/ajax.js') }}"></script>
<script src="{{ asset('/js/front/layui/auth/forgot-password.js') }}"></script>
</body>
</html>
