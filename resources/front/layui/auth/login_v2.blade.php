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
    {{-- 旧登录提交到 web 路由，必须把当前 Session 的 CSRF 令牌交给公共登录脚本。 --}}
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ __('auth.login') }} - {{ __('common.system_name') }}</title>
    <link rel="stylesheet" href="{{ asset('/js/vendor/layui-v2.13.5/layui/css/layui.css') }}">
    <link rel="stylesheet" href="{{ asset('/css/front/style.css') }}?v=2026060306">
    <link rel="stylesheet" href="{{ asset('/css/front/v2.css') }}?v=2026061401">
    <link rel="stylesheet" href="{{ asset('/css/common/crm-design-system.css') }}?v=2026070403">
    @include('partials.lucide-assets')
    @include('partials.theme-assets')
</head>
<body class="front-v2-page front-v2-auth-body crm-ui-auth-body" data-ui-family="layui" data-ui-surface="front" data-visual-direction="c">
    <div class="crm-theme-picker-host">
        @include('partials.theme-picker', ['themePickerCompact' => true, 'themePickerLabel' => false])
    </div>
    <main class="front-v2-auth">
        <section class="front-v2-auth-panel">
            <div class="front-v2-auth-mark">
                <i data-lucide="boxes"></i>
                <span data-translate="system_name">{{ __('common.system_name') }}</span>
            </div>
            <div class="front-v2-auth-copy">
                <h1 data-translate="login_title">{{ __('auth.login') }}</h1>
                <p>{{ app()->getLocale() === 'en' ? 'A focused workspace for account, funds, and trading operations.' : '面向账户、资金和交易业务的清爽工作台。' }}</p>
            </div>
            <p class="front-v2-auth-foot">{{ app()->getLocale() === 'en' ? 'Secure access. Clear actions. Less noise.' : '安全进入，清晰操作，减少干扰。' }}</p>
        </section>

        <section class="front-v2-auth-card">
            <div class="front-v2-auth-heading">
                <h2 data-translate="login_title">{{ __('auth.login') }}</h2>
                <p>{{ app()->getLocale() === 'en' ? 'Use email or user ID to continue.' : '使用邮箱或用户编号继续访问。' }}</p>
            </div>

            <form class="layui-form" lay-filter="loginForm">
                @csrf
                <div class="layui-form-item">
                    <input type="text" name="loginUid" autocomplete="username"
                           data-translate-placeholder="account_or_email" placeholder="{{ __('auth.email') }} / {{ __('auth.user_id') }}"
                           class="layui-input">
                </div>
                <div class="layui-form-item">
                    <input type="password" name="loginPassword" autocomplete="current-password"
                           data-translate-placeholder="password" placeholder="{{ __('auth.password') }}"
                           class="layui-input">
                </div>
                <div class="layui-form-item front-v2-code-row">
                    <input type="text" name="cptcode" autocomplete="off"
                           data-translate-placeholder="captcha_code" placeholder="{{ __('crmui.fields.captcha_code') }}"
                           class="layui-input">
                    <img id="legacyLoginCaptchaImg"
                         src="{{ route('legacy_user_captcha') }}"
                         data-captcha-src="{{ route('legacy_user_captcha') }}"
                         class="front-v2-captcha"
                         alt="{{ __('crmui.fields.captcha_code') }}">
                </div>
                <div class="layui-form-item">
                    <button type="button" class="layui-btn layui-btn-fluid front-v2-primary-btn" lay-submit lay-filter="doLogin"
                            data-translate="login_btn">{{ __('auth.login') }}</button>
                </div>
            </form>

            <div class="front-v2-auth-links">
                <span data-translate="no_account">{{ __('auth.no_account') }}</span>
                <a href="{{ route('front_page_register') }}" data-translate="go_register">{{ __('auth.go_register') }}</a>
                <span>|</span>
                <a href="{{ route('front_page_forgot_password') }}" data-translate="forgot_password">{{ __('auth.forgot_password') }}</a>
            </div>

            <div class="front-v2-lang-row">
                <a href="javascript:;" data-lang="zh-CN" class="J_langSwitch" data-translate="lang_zh">{{ __('common.lang_zh') }}</a>
                <span>|</span>
                <a href="javascript:;" data-lang="en" class="J_langSwitch" data-translate="lang_en">{{ __('common.lang_en') }}</a>
            </div>
        </section>
    </main>

    <script src="{{ asset('/js/vendor/jquery/jquery-3.6.0.min.js') }}"></script>
    <script src="{{ asset('/js/vendor/layui-v2.13.5/layui/layui.js') }}"></script>
    @include('partials.frontend-routes')
    <script src="{{ asset('/js/shared/ajax.js') }}?v=2026060702"></script>
    <script src="{{ asset('/js/apps/front/layui/common.js') }}"></script>
    <script src="{{ asset('/js/apps/front/layui/pages.js') }}?v=2026072801"></script>
    {{--
        旧入口必须走 legacySignIn 建立 suser Session；成功后进入旧 Blade 外壳，
        避免只保存 JWT 后访问 /user/* 时再次被 LegacyFrontAuthenticate 拦回登录页。
    --}}
    <div hidden
         data-layui-page="auth/login"
         data-login-mode="legacy"
         data-login-endpoint="{{ route('legacy_user_sign_in') }}"
         data-success-url="{{ route('legacy_user_index_page') }}"></div>
</body>
</html>

