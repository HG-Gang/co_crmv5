{{--
Created by PhpStorm.
Project name co_crmv5.
User: Huang Gang
Date: 2026/08/17
Time: 09:55
--}}
@php
    $isLegacyBigAgentLogin = !empty($legacyBigAgentLogin) || (($legacyWho ?? '') === 'bigAgents');
    $loginEndpoint = $isLegacyBigAgentLogin
        ? url('/user/agents/signIn')
        : url('/api/front/auth/big-number/login');
    $successUrl = $isLegacyBigAgentLogin
        ? route('legacy_user_agents_index')
        : route('front_page_dashboard');
@endphp
<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ __('front.big_number_login') }} - {{ __('common.system_name') }}</title>
    <link rel="stylesheet" href="{{ asset('/js/vendor/layui-v2.13.5/layui/css/layui.css') }}">
    <link rel="stylesheet" href="{{ asset('/css/front/style.css') }}?v=2026060307">
    <link rel="stylesheet" href="{{ asset('/css/common/crm-design-system.css') }}?v=2026070403">
    @include('partials.lucide-assets')
    @include('partials.theme-assets')
</head>
<body class="auth-wrapper crm-ui-auth-body" data-ui-family="layui" data-ui-surface="front" data-visual-direction="c">
<div class="crm-theme-picker-host">
    @include('partials.theme-picker', ['themePickerCompact' => true, 'themePickerLabel' => false])
</div>
<div class="auth-card">
    <h2 class="auth-title" data-translate="front.big_number_login">{{ __('front.big_number_login') }}</h2>
    <form class="layui-form" lay-filter="bigNumberLoginForm">
        @csrf
        <div class="layui-form-item">
            <input type="text" name="{{ $isLegacyBigAgentLogin ? 'loginUid' : 'user_id' }}" class="layui-input"
                   data-translate-placeholder="front.user_id" placeholder="{{ __('front.user_id') }}">
        </div>
        <div class="layui-form-item">
            <input type="password" name="{{ $isLegacyBigAgentLogin ? 'loginPassword' : 'password' }}" required lay-verify="required" class="layui-input"
                   data-translate-placeholder="auth.password" placeholder="{{ __('auth.password') }}">
        </div>
        <button class="layui-btn layui-btn-fluid layui-bg-blue" lay-submit lay-filter="bigNumberLoginSubmit" data-translate="auth.login">{{ __('auth.login') }}</button>
    </form>
</div>
<script src="{{ asset('/js/vendor/layui-v2.13.5/layui/layui.js') }}"></script>
@unless($isLegacyBigAgentLogin)
    @include('partials.frontend-routes')
@endunless
<script src="{{ asset('/js/shared/i18n.js') }}?v=2026060402"></script>
<script src="{{ asset('/js/shared/ajax.js') }}?v=2026060702"></script>
<script src="{{ asset('/js/apps/front/layui/pages.js') }}?v=2026070401"></script>
<div hidden
     data-layui-page="auth/big-number-login"
     data-legacy-big-agent="{{ $isLegacyBigAgentLogin ? '1' : '0' }}"
     data-login-endpoint="{{ $loginEndpoint }}"
     data-success-url="{{ $successUrl }}"></div>
</body>
</html>

