{{--
Created by PhpStorm.
Project name co_crmv5.
User: Huang Gang
Date: 2026/08/17
Time: 09:55
--}}
<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1">
    <title>{{ __('auth.forgot_password') }} - {{ __('common.system_name') }}</title>
    {{-- 找回密码独立页加载共享 Lucide，确保状态图标和动态反馈可统一刷新。 --}}
    @include('partials.lucide-assets')
    <link rel="stylesheet" href="{{ asset('/js/vendor/layui-v2.13.5/layui/css/layui.css') }}">
    <link rel="stylesheet" href="{{ asset('/css/front/style.css') }}?v=2026060307">
    <link rel="stylesheet" href="{{ asset('/css/common/crm-design-system.css') }}?v=2026070403">
    @include('partials.theme-assets')
</head>
<body class="auth-wrapper crm-ui-auth-body" data-ui-family="layui" data-ui-surface="front" data-visual-direction="c">
<div class="crm-theme-picker-host">
    @include('partials.theme-picker', ['themePickerCompact' => true, 'themePickerLabel' => false])
</div>
<div class="auth-card">
    <h2 class="auth-title" data-translate="auth.forgot_password">{{ __('auth.forgot_password') }}</h2>
    <form class="layui-form" lay-filter="forgotForm">
        <div class="layui-form-item">
            <input type="email" name="email" required lay-verify="required|email" class="layui-input"
                   data-translate-placeholder="auth.email" placeholder="{{ __('auth.email') }}">
        </div>
        <div class="layui-form-item">
            <div class="register-code-row">
                <input type="text" name="code" required lay-verify="required" class="layui-input"
                       data-translate-placeholder="auth.reset_code" placeholder="{{ __('auth.reset_code') }}">
                <button type="button"
                        id="sendResetCode"
                        class="layui-btn layui-btn-primary"
                        data-translate="auth.send_reset_code">{{ __('auth.send_reset_code') }}</button>
            </div>
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
        <a href="{{ route('front_page_login') }}" data-translate="auth.back_to_login">{{ __('auth.back_to_login') }}</a>
    </div>
</div>
<script src="{{ asset('/js/vendor/layui-v2.13.5/layui/layui.js') }}"></script>
@include('partials.frontend-routes')
<script src="{{ asset('/js/shared/i18n.js') }}?v=2026060402"></script>
<script src="{{ asset('/js/shared/ajax.js') }}?v=2026060702"></script>
<script src="{{ asset('/js/apps/front/layui/pages.js') }}?v=2026070401"></script>
<div hidden data-layui-page="auth/forgot-password"></div>
</body>
</html>

