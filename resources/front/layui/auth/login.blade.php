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
    <title>{{ __('auth.login') }} - {{ __('common.system_name') }}</title>
    <link rel="stylesheet" href="{{ asset('/js/vendor/layui-v2.13.5/layui/css/layui.css') }}">
    <link rel="stylesheet" href="{{ asset('/css/front/style.css') }}?v=2026060306">
    <link rel="stylesheet" href="{{ asset('/css/common/crm-design-system.css') }}?v=2026070403">
    @include('partials.lucide-assets')
    @include('partials.theme-assets')
</head>
<body class="crm-ui-auth-body" data-ui-family="layui" data-ui-surface="front" data-visual-direction="c">
    <div class="crm-theme-picker-host">
        @include('partials.theme-picker', ['themePickerCompact' => true, 'themePickerLabel' => false])
    </div>
    <div class="auth-shell">
        <div class="auth-card">
            <aside class="auth-brand">
                <div class="auth-brand-mark">
                    <span class="auth-brand-logo"><i data-lucide="boxes"></i></span>
                    <span data-translate="system_name">{{ __('common.system_name') }}</span>
                </div>

                <div class="auth-brand-copy">
                    <h1>{{ app()->getLocale() === 'en' ? 'One workspace for account, funds and operations.' : '账户、资金与业务操作集中在一个工作台。' }}</h1>
                    <p>{{ app()->getLocale() === 'en' ? 'A calmer CRM entry built for repeated daily work, clear navigation and secure sessions.' : '面向日常高频使用的 CRM 入口，强调清晰导航、低干扰操作与安全会话。' }}</p>
                </div>

                <ul class="auth-brand-points">
                    <li><i data-lucide="badge-check"></i>{{ app()->getLocale() === 'en' ? 'Session protection and clear account access' : '会话保护与清晰的账户入口' }}</li>
                    <li><i data-lucide="presentation"></i>{{ app()->getLocale() === 'en' ? 'Fast access to funds, team and trading views' : '快速进入资金、团队与交易视图' }}</li>
                    <li><i data-lucide="settings"></i>{{ app()->getLocale() === 'en' ? 'Blade pages, no separate frontend stack' : '保留 Blade 页面，不引入前后端分离' }}</li>
                </ul>
            </aside>

            <main class="auth-form-side">
                <div class="auth-head">
                    <h2 data-translate="login_title">{{ __('auth.login') }}</h2>
                    <p>{{ app()->getLocale() === 'en' ? 'Use your email or user ID to continue.' : '使用邮箱或用户编号继续访问。' }}</p>
                </div>

                <form class="layui-form" lay-filter="loginForm">
                    <div class="layui-form-item">
                        <label class="auth-field-label" data-translate="account_or_email">{{ __('auth.email') }} / {{ __('auth.user_id') }}</label>
                        <input type="text" name="account" autocomplete="username"
                               data-translate-placeholder="account_or_email" placeholder="{{ __('auth.email') }} / {{ __('auth.user_id') }}"
                               class="layui-input">
                    </div>

                    <div class="layui-form-item">
                        <label class="auth-field-label" data-translate="password">{{ __('auth.password') }}</label>
                        <input type="password" name="password" autocomplete="current-password"
                               data-translate-placeholder="password" placeholder="{{ __('auth.password') }}"
                               class="layui-input">
                    </div>

                    <div class="layui-form-item">
                        <button type="button" class="layui-btn layui-btn-fluid auth-submit" lay-submit lay-filter="doLogin"
                                data-translate="login_btn">{{ __('auth.login') }}</button>
                    </div>
                </form>

                <div class="auth-foot">
                    <span data-translate="no_account">{{ __('auth.no_account') }}</span>
                    <a href="{{ route('front_page_register') }}" data-translate="go_register">{{ __('auth.go_register') }}</a>
                    <span class="auth-sep">|</span>
                    <a href="{{ route('front_page_forgot_password') }}" data-translate="forgot_password">{{ __('auth.forgot_password') }}</a>
                </div>

                <div class="auth-lang">
                    <a href="javascript:;" data-lang="zh-CN" class="J_langSwitch" data-translate="lang_zh">{{ __('common.lang_zh') }}</a>
                    <a href="javascript:;" data-lang="en" class="J_langSwitch" data-translate="lang_en">{{ __('common.lang_en') }}</a>
                </div>
            </main>
        </div>
    </div>

    <script src="{{ asset('/js/vendor/jquery/jquery-3.6.0.min.js') }}"></script>
    <script src="{{ asset('/js/vendor/layui-v2.13.5/layui/layui.js') }}"></script>
    @include('partials.frontend-routes')
    <script src="{{ asset('/js/shared/ajax.js') }}?v=2026060702"></script>
    <script src="{{ asset('/js/apps/front/layui/common.js') }}"></script>
    <script src="{{ asset('/js/apps/front/layui/pages.js') }}?v=2026070401"></script>
    <div hidden data-layui-page="auth/login"></div>
</body>
</html>

