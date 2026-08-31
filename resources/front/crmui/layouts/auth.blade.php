{{--
Created by PhpStorm.
Project name co_crmv5.
User: Huang Gang
Date: 2026/08/18
Time: 16:48
--}}
@php
    $page = $page ?? [];
    $pageTitle = $page['title'] ?? __('auth.login');
    $renderFamily = ($page['renderFamily'] ?? 'crmui') === 'naive' ? 'naive' : 'crmui';
    $loginUrl = $page['loginUrl'] ?? route('front_crmui_login');
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $pageTitle }} - {{ __('crmui.common.front_console') }}</title>
    <link rel="stylesheet" href="{{ asset('/js/vendor/layui-v2.13.5/layui/css/layui.css') }}">
    <link rel="stylesheet" href="{{ asset('/css/crmui/tokens.css') }}?v=2026070401">
    <link rel="stylesheet" href="{{ asset('/css/crmui/front.css') }}?v=2026070501">
    @if($renderFamily === 'naive')
        <link rel="stylesheet" href="{{ asset('/css/crmui/naive.css') }}?v=2026081201">
    @endif
    @include('partials.lucide-assets')
    @include('partials.theme-assets')
</head>
<body class="crmui-body crmui-auth-body" data-crmui-surface="front" data-ui-family="{{ $renderFamily }}" data-ui-surface="front" data-visual-direction="c">
<div class="crm-theme-picker-host">
    @include('partials.theme-picker', ['themePickerCompact' => true, 'themePickerLabel' => false])
</div>
<main class="crmui-auth-shell">
    <section class="crmui-auth-brand">
        <a class="crmui-brand" href="{{ $loginUrl }}">
            <span class="crmui-brand-mark">CO</span>
            <span>
                <strong>{{ __('crmui.common.front_console') }}</strong>
                <small>{{ __('crmui.common.blade_ui') }}</small>
            </span>
        </a>
        <div class="crmui-auth-copy">
            <h1>{{ __('crmui.common.front_auth_headline') }}</h1>
            <p>{{ __('crmui.common.front_auth_intro') }}</p>
        </div>
        <div class="crmui-auth-points">
            <span>{{ __('crmui.common.point_secure') }}</span>
            <span>{{ __('crmui.common.point_responsive') }}</span>
            <span>{{ __('crmui.common.point_blade') }}</span>
        </div>
    </section>

    <section class="crmui-auth-panel">
        @yield('content')
    </section>
</main>

<script src="{{ asset('/js/vendor/jquery/jquery-3.6.0.min.js') }}"></script>
<script src="{{ asset('/js/vendor/layui-v2.13.5/layui/layui.js') }}"></script>
@include('partials.frontend-routes')
<script src="{{ asset('/js/shared/ajax.js') }}?v=2026060702"></script>
<script src="{{ asset('/js/apps/crmui/front.js') }}?v=2026070501"></script>
</body>
</html>
