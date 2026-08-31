{{--
Created by PhpStorm.
Project name co_crmv5.
User: Huang Gang
Date: 2026/08/17
Time: 09:55
--}}
@php
    $pageTitle = trim($__env->yieldContent('title', __('front.dashboard')));
    $agent = $legacyBigAgent ?? [];
    $csrfToken = csrf_token();
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1">
    <meta name="csrf-token" content="{{ $csrfToken }}">
    <title>{{ $pageTitle }} - {{ __('common.system_name') }}</title>
    <link rel="stylesheet" href="{{ asset('/js/vendor/layui-v2.13.5/layui/css/layui.css') }}">
    <link rel="stylesheet" href="{{ asset('/css/front/style.css') }}?v=2026070406">
    <link rel="stylesheet" href="{{ asset('/css/common/crm-design-system.css') }}?v=2026070403">
    @include('partials.lucide-assets')
    <style>
        .legacy-big-agent-shell { min-height: 100vh; background: var(--front-bg); }
        .legacy-big-agent-header { display: flex; align-items: center; justify-content: space-between; gap: 16px; min-height: 58px; padding: 0 22px; color: var(--front-side-text); background: var(--front-side); }
        .legacy-big-agent-brand { font-size: 17px; font-weight: 700; }
        .legacy-big-agent-user { display: flex; align-items: center; gap: 12px; font-size: 13px; }
        .legacy-big-agent-user span { opacity: .82; }
        .legacy-big-agent-nav { display: flex; flex-wrap: wrap; gap: 8px; padding: 12px 22px; border-bottom: 1px solid var(--front-line); background: var(--front-panel); }
        .legacy-big-agent-nav a { display: inline-flex; align-items: center; min-height: 32px; padding: 0 11px; border-radius: 5px; color: var(--front-text); text-decoration: none; }
        .legacy-big-agent-nav a:hover, .legacy-big-agent-nav a.is-active { color: var(--front-on-accent); background: var(--front-blue); }
        .legacy-big-agent-content { max-width: 1440px; margin: 0 auto; padding: 18px 22px 32px; }
        .legacy-big-agent-shell .layui-card { border-radius: 6px; }
        .legacy-big-agent-muted { color: var(--front-muted); }
        @media (max-width: 640px) { .legacy-big-agent-header { align-items: flex-start; flex-direction: column; padding: 14px 16px; } .legacy-big-agent-nav { padding: 10px 16px; } .legacy-big-agent-content { padding: 14px 16px 24px; } }
    </style>
    @include('partials.theme-assets')
</head>
<body class="legacy-big-agent-shell"
      data-legacy-shell="big-agent"
      data-ui-family="layui"
      data-ui-surface="big-agent"
      data-visual-direction="c"
      data-csrf-token="{{ $csrfToken }}"
      data-csrf-header="X-CSRF-TOKEN"
      data-login-url="{{ route('agentsLogin') }}">
<header class="legacy-big-agent-header">
    <div class="legacy-big-agent-brand">{{ __('front.big_number_login') }}</div>
    <div class="legacy-big-agent-user">
        @include('partials.theme-picker', ['themePickerCompact' => true])
        <strong>{{ $agent['username'] ?? $agent['email'] ?? '-' }}</strong>
        <span>{{ $agent['email'] ?? '' }}</span>
    </div>
</header>
<nav class="legacy-big-agent-nav" aria-label="{{ __('common.menu') }}">
    <a class="{{ request()->is('user/agents/index') || request()->is('user/agents/main/home') ? 'is-active' : '' }}" href="{{ route('legacy_user_agents_index') }}">{{ __('front.dashboard') }}</a>
    <a class="{{ request()->is('user/agents/proxy/list') ? 'is-active' : '' }}" href="{{ route('legacy_user_agents_proxy_list') }}">{{ __('front.sub_agents') }}</a>
    <a class="{{ request()->is('user/agents/position/summary') ? 'is-active' : '' }}" href="{{ route('legacy_user_agents_position_summary') }}">{{ __('front.position_summary') }}</a>
    <a class="{{ request()->is('user/agents/open/order') ? 'is-active' : '' }}" href="{{ route('legacy_user_agents_open_order') }}">{{ __('front.open_orders') }}</a>
    <a class="{{ request()->is('user/agents/close/order') ? 'is-active' : '' }}" href="{{ route('legacy_user_agents_close_order') }}">{{ __('front.closed_orders') }}</a>
    <a class="{{ request()->is('user/agents/editpsw') ? 'is-active' : '' }}" href="{{ route('legacy_user_agents_edit_password_page') }}">{{ __('front.change_password') }}</a>
    <a href="{{ route('legacy_user_agents_logout') }}">{{ __('auth.logout') }}</a>
</nav>
<main class="legacy-big-agent-content">
    @yield('content')
</main>
<script src="{{ asset('/js/vendor/jquery/jquery-3.6.0.min.js') }}"></script>
<script src="{{ asset('/js/vendor/layui-v2.13.5/layui/layui.js') }}"></script>
<script src="{{ asset('/js/shared/i18n.js') }}?v=2026060402"></script>
<script src="{{ asset('/js/apps/front/layui/legacy-big-agent.js') }}?v=2026072301"></script>
<script src="{{ asset('/js/apps/front/layui/pages.js') }}?v=2026070401"></script>
@yield('scripts')
</body>
</html>
