<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1">
    <title>{{ __('auth.login') }} - {{ __('common.system_name') }}</title>
    <script src="{{ asset('/js/common/theme-sync.js') }}?v=2026052908"></script>
    {{-- Layui local CSS --}}
    <link rel="stylesheet" href="{{ asset('/js/common/layui-v2.13.5/layui/css/layui.css') }}">
    <link rel="stylesheet" href="{{ asset('/css/front/style.css') }}?v=2026052911">
    <style>
        body {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0;
            padding: 20px;
            box-sizing: border-box;
            background:
                linear-gradient(90deg, rgba(255,255,255,.7) 1px, transparent 1px) 0 0 / 26px 26px,
                linear-gradient(rgba(255,255,255,.7) 1px, transparent 1px) 0 0 / 26px 26px,
                var(--front-bg);
        }
        .login-card {
            width: 100%;
            max-width: 430px;
            background: var(--front-panel);
            border: 1px solid var(--front-line);
            border-radius: 8px;
            padding: 38px 34px;
            box-shadow: 0 24px 70px rgba(17,24,39,.16);
        }
        .login-logo { text-align: center; margin-bottom: 25px; }
        .login-logo h1 { font-size: 26px; color: var(--front-strong); margin: 0; font-weight: 800; }
        .login-logo p { color: var(--front-muted); font-size: 14px; margin-top: 6px; }
        .layui-form-item { margin-bottom: 18px; }
        .login-footer { text-align: center; margin-top: 20px; font-size: 13px; color: var(--front-muted); }
        .login-footer a { color: var(--front-blue); text-decoration: none; font-weight: 600; }
        .lang-bar { text-align: center; margin-top: 18px; padding-top: 15px; border-top: 1px solid var(--front-line); }
        .lang-bar a { color: var(--front-muted); margin: 0 10px; cursor: pointer; text-decoration: none; font-size: 13px; }
        .lang-bar a:hover, .lang-bar a.active { color: var(--front-accent); }
        @media (max-width: 480px) { .login-card { padding: 30px 20px; } .login-logo h1 { font-size: 22px; } }
    </style>
</head>
<body>
    <div class="login-card">
        {{-- Logo area --}}
        <div class="login-logo">
            <h1 data-translate="system_name">{{ __('common.system_name') }}</h1>
            <p data-translate="login_title">{{ __('auth.login') }}</p>
        </div>

        <form class="layui-form" lay-filter="loginForm">
            {{-- Account input: backend automatically detects email or user ID --}}
            <div class="layui-form-item">
                <input type="text" name="account" autocomplete="username"
                       data-translate-placeholder="account_or_email" placeholder="{{ __('auth.email') }} / {{ __('auth.user_id') }}"
                       class="layui-input">
            </div>

            {{-- Password --}}
            <div class="layui-form-item">
                <input type="password" name="password" autocomplete="current-password"
                       data-translate-placeholder="password" placeholder="{{ __('auth.password') }}"
                       class="layui-input">
            </div>

            {{-- Login button --}}
            <div class="layui-form-item">
                <button type="button" class="layui-btn layui-btn-fluid" lay-submit lay-filter="doLogin"
                        data-translate="login_btn">{{ __('auth.login') }}</button>
            </div>
        </form>

        {{-- Footer links --}}
        <div class="login-footer">
            <span data-translate="no_account">{{ __('auth.no_account') }}</span>
            <a href="{{ url('/front/register') }}" data-translate="go_register">{{ __('auth.go_register') }}</a>
            <span class="login-separator">|</span>
            <a href="{{ url('/front/forgot-password') }}" data-translate="forgot_password">{{ __('auth.forgot_password') }}</a>
        </div>

        {{-- Pure JavaScript language switch, no route navigation --}}
        <div class="lang-bar">
            <a href="javascript:;" data-lang="en" class="J_langSwitch" data-translate="lang_en">{{ __('common.lang_en') }}</a>
            <a href="javascript:;" data-lang="zh-CN" class="J_langSwitch" data-translate="lang_zh">{{ __('common.lang_zh') }}</a>
        </div>
    </div>

    {{-- JavaScript --}}
    <script src="{{ asset('/js/common/jquery/jquery-3.6.0.min.js') }}"></script>
    <script src="{{ asset('/js/common/layui-v2.13.5/layui/layui.js') }}"></script>
    <script src="{{ asset('/js/front/layui/common.js') }}"></script>
    <script src="{{ asset('/js/front/layui/auth/login.js') }}"></script>
</body>
</html>
