{{--
Created by PhpStorm.
Project name co_crmv5.
User: Huang Gang
Date: 2026/08/07
Time: 22:35
--}}
<!DOCTYPE html>
@php
    // 旧入口仍提交 loginUid/loginPassword/cptcode；现代入口保持 username/password API 契约。
    $isLegacyAdminLogin = request()->is('index/admin/login');
@endphp
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1">
    <title>{{ __('common.admin_system_name') }} - {{ __('auth.login') }}</title>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="stylesheet" href="{{ asset('/js/vendor/layui-v2.13.5/layui/css/layui.css') }}">
    <link rel="stylesheet" href="{{ asset('/css/admin/style.css') }}?v=2026052908">
    <link rel="stylesheet" href="{{ asset('/css/common/crm-design-system.css') }}?v=2026070403">
    @include('partials.lucide-assets')
    @include('partials.theme-assets')
</head>
<body class="layui-layout-body admin-login-body crm-ui-auth-body" data-legacy-admin-login="{{ $isLegacyAdminLogin ? '1' : '0' }}">
<div class="crm-theme-picker-host">
    @include('partials.theme-picker', ['themePickerCompact' => true, 'themePickerLabel' => false])
</div>
<div class="login-container">
    <div class="login-logo" data-translate="system_name">{{ __('common.admin_system_name') }}</div>
    
    <div class="layui-text login-language">
        <a href="javascript:;" class="lang-switch" data-lang="zh-CN" data-translate="lang_zh">{{ __('common.lang_zh') }}</a> | 
        <a href="javascript:;" class="lang-switch" data-lang="en" data-translate="lang_en">{{ __('common.lang_en') }}</a>
    </div>
    
    {{-- 后台登录表单字段必须与 AdminAuthController::doLogin 保持一致。 --}}
    <form class="layui-form" @if ($isLegacyAdminLogin) action="{{ url('/index/admin/logon') }}" @else action="{{ route('admin.login.post') }}" @endif method="POST" data-legacy-admin-login="{{ $isLegacyAdminLogin ? '1' : '0' }}">
        @csrf
        <div class="layui-form-item">
            {{-- email 表示管理员登录邮箱，对应 admins.email，也是登录失败后回填的账号字段。 --}}
            @if ($isLegacyAdminLogin)
                <input type="text" name="loginUid" value="{{ old('loginUid') }}" required lay-verify="required" data-translate-placeholder="auth.email" placeholder="{{ __('auth.email') }}" autocomplete="username" class="layui-input">
            @else
                <input type="text" name="email" value="{{ old('email') }}" required lay-verify="required" data-translate-placeholder="auth.email" placeholder="{{ __('auth.email') }}" autocomplete="username" class="layui-input">
            @endif
        </div>
        <div class="layui-form-item">
            {{-- password 表示管理员登录密码，仅用于后端 Auth 校验，不写入审计日志。 --}}
            @if ($isLegacyAdminLogin)
                <input type="password" name="loginPassword" required lay-verify="required" data-translate-placeholder="auth.password_label" placeholder="{{ __('auth.password_label') }}" autocomplete="current-password" class="layui-input">
            @else
                <input type="password" name="password" required lay-verify="required" data-translate-placeholder="auth.password_label" placeholder="{{ __('auth.password_label') }}" autocomplete="current-password" class="layui-input">
            @endif
        </div>
        @if ($isLegacyAdminLogin)
            <div class="layui-form-item admin-legacy-captcha-field">
                {{-- cptcode 是旧后台强制图形验证码；旧入口写入 Session，登录成功后一次性消费。 --}}
                <input class="layui-input" type="text" name="cptcode" required lay-verify="required" data-translate-placeholder="auth.reset_code" placeholder="{{ __('auth.reset_code') }}" autocomplete="off">
                <div class="code"><img id="legacyAdminCaptcha" src="{{ url('/index/admin/captcha') }}" width="132" height="44" alt="{{ __('auth.reset_code') }}"></div>
            </div>
        @endif
        <div class="layui-form-item">
            {{-- remember 表示是否延长后台登录会话，提交后由 Laravel admin guard 处理记住登录状态。 --}}
            <input type="checkbox" name="remember" lay-skin="primary" title="{{ __('auth.remember_me') }}" data-translate-title="auth.remember_me">
        </div>
        <div class="layui-form-item">
            <button class="layui-btn layui-btn-fluid layui-bg-green" lay-submit lay-filter="adminLogin" data-translate="login">{{ __('auth.login') }}</button>
        </div>
    </form>
</div>

<script src="{{ asset('/js/vendor/jquery/jquery-3.6.0.min.js') }}"></script>
<script src="{{ asset('/js/vendor/layui-v2.13.5/layui/layui.js') }}"></script>
@include('partials.frontend-routes')
<script src="{{ asset('/js/shared/ajax.js') }}?v=2026060702"></script>
<script src="{{ asset('/js/apps/admin/layui/common.js') }}"></script>
<script src="{{ asset('/js/apps/admin/layui/pages.js') }}?v=2026070401"></script>
<div hidden data-layui-page="auth/login"></div>
</body>
</html>

