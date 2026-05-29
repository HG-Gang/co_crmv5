<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title data-translate="auth.login">Login - CRM v5</title>
    <script src="{{ asset('js/common/theme-sync.js') }}?v=2026052908"></script>
    <link rel="stylesheet" href="{{ asset('js/common/layui-v2.13.5/layui/css/layui.css') }}">
    <link rel="stylesheet" href="{{ asset('css/common/theme-sync.css') }}?v=2026052908">
    <style>
        body { background: #f5f7fa; min-height: 100vh; display: flex; align-items: center; justify-content: center; margin: 0; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; }
        .login-container { width: 420px; }
        .login-card { background: #fff; border-radius: 12px; padding: 40px; box-shadow: 0 2px 12px rgba(0,0,0,0.08); }
        .login-card .logo { text-align: center; margin-bottom: 30px; }
        .login-card .logo h1 { font-size: 28px; color: #18a058; margin: 0; }
        .login-card .logo p { color: #999; font-size: 14px; margin-top: 6px; }
        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; margin-bottom: 6px; font-size: 14px; color: #333; }
        .form-group input { width: 100%; height: 42px; padding: 0 12px; border: 1px solid #d9d9d9; border-radius: 6px; font-size: 14px; outline: none; transition: border-color 0.2s; }
        .form-group input:focus { border-color: #18a058; }
        .btn-login { width: 100%; height: 44px; background: #18a058; color: #fff; border: none; border-radius: 6px; font-size: 16px; cursor: pointer; transition: background 0.2s; }
        .btn-login:hover { background: #0e7a42; }
        .login-footer { display: flex; justify-content: space-between; margin-top: 16px; font-size: 13px; }
        .login-footer a { color: #18a058; text-decoration: none; }
        .login-footer a:hover { text-decoration: underline; }
        .error-msg { padding: 8px 12px; background: #fff2f0; border: 1px solid #ffccc7; border-radius: 4px; color: #ff4d4f; margin-bottom: 16px; font-size: 13px; }
        .lang-switch { text-align: center; margin-top: 20px; }
        .lang-switch a { color: #999; text-decoration: none; font-size: 13px; margin: 0 6px; }
        .lang-switch a:hover { color: #18a058; }
    </style>
</head>
<body>
<div class="login-container">
    <div class="login-card">
        <div class="logo">
            <h1>CRM v5</h1>
            <p data-translate="auth.login">Login</p>
        </div>

        @if($errors->any())
        <div class="error-msg">
            @foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach
        </div>
        @endif

        <form action="{{ route('front.login.post') }}" method="POST">
            @csrf
            <div class="form-group">
                <label data-translate="auth.email">Email</label>
                <input type="email" name="email" value="{{ old('email') }}" required placeholder="" data-translate-placeholder="auth.email">
            </div>
            <div class="form-group">
                <label data-translate="auth.password_label">Password</label>
                <input type="password" name="password" required placeholder="" data-translate-placeholder="auth.password_label">
            </div>
            <div style="margin-bottom:20px;">
                <label style="font-size:13px;color:#666;cursor:pointer;">
                    <input type="checkbox" name="remember"> <span data-translate="auth.remember_me">Remember me</span>
                </label>
            </div>
            <button type="submit" class="btn-login" data-translate="auth.login">Login</button>
        </form>

        <div class="login-footer">
            <a href="{{ route('front.register') }}" data-translate="auth.register">Register</a>
            <a href="{{ route('admin.login') }}" data-translate="auth.admin_login">Admin Login</a>
        </div>
        <div class="lang-switch">
            <a href="javascript:void(0);" class="J_legacyLang" data-lang="zh-CN" data-translate="common.lang_zh">{{ __('common.lang_zh') }}</a> |
            <a href="javascript:void(0);" class="J_legacyLang" data-lang="en" data-translate="common.lang_en">{{ __('common.lang_en') }}</a>
        </div>
    </div>
</div>
<script src="{{ asset('js/common/jquery/jquery-3.6.0.min.js') }}"></script>
<script src="{{ asset('js/common/i18n.js') }}"></script>
<script src="{{ asset('js/common/lang/zh-CN.js') }}"></script>
<script src="{{ asset('js/common/lang/en.js') }}"></script>
<script>
    // 确保在DOM加载完成后初始化
    $(function() {
        console.log('DOM准备就绪，开始初始化语言');
        // 获取保存的语言偏好或使用默认语言
        var savedLocale = localStorage.getItem('crm_locale') || 'zh-CN';
        console.log('保存/默认语言:', savedLocale);
        
        // 直接设置语言（因为语言文件已经加载）
        CrmI18n.setLocale(savedLocale);
        console.log('语言设置完成:', savedLocale);
    });

    // 语言切换功能
    function switchLanguage(locale) {
        console.log('切换到语言:', locale);
        // 直接设置语言（不需要动态加载）
        CrmI18n.setLocale(locale);
        console.log('语言切换完成:', locale);
    }
</script>
<script>
    $(function() {
        $('.J_legacyLang').on('click', function() {
            CrmI18n.setLocale($(this).data('lang'));
        });
    });
</script>
</body>
</html>
