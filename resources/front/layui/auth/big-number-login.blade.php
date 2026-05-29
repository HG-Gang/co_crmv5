<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1">
    <title>{{ __('front.big_number_login') }} - {{ __('common.system_name') }}</title>
    <script src="{{ asset('/js/common/theme-sync.js') }}?v=2026052908"></script>
    <link rel="stylesheet" href="{{ asset('/js/common/layui-v2.13.5/layui/css/layui.css') }}">
    <link rel="stylesheet" href="{{ asset('/css/front/style.css') }}?v=2026052911">
</head>
<body class="auth-wrapper">
<div class="auth-card">
    <h2 class="auth-title" data-translate="front.big_number_login">{{ __('front.big_number_login') }}</h2>
    <form class="layui-form" lay-filter="bigNumberLoginForm">
        <div class="layui-form-item">
            <input type="text" name="user_id" class="layui-input"
                   data-translate-placeholder="front.user_id" placeholder="{{ __('front.user_id') }}">
        </div>
        <div class="layui-form-item">
            <input type="password" name="password" required lay-verify="required" class="layui-input"
                   data-translate-placeholder="auth.password" placeholder="{{ __('auth.password') }}">
        </div>
        <button class="layui-btn layui-btn-fluid layui-bg-blue" lay-submit lay-filter="bigNumberLoginSubmit" data-translate="auth.login">{{ __('auth.login') }}</button>
    </form>
</div>
<script src="{{ asset('/js/common/layui-v2.13.5/layui/layui.js') }}"></script>
<script src="{{ asset('/js/common/i18n.js') }}"></script>
<script src="{{ asset('/js/common/ajax.js') }}"></script>
<script src="{{ asset('/js/front/layui/auth/big-number-login.js') }}"></script>
</body>
</html>
