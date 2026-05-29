<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title data-translate="auth.admin_login">Admin Login - CRM v5</title>
    <script src="{{ asset('js/common/theme-sync.js') }}?v=2026052908"></script>
    <link rel="stylesheet" href="{{ asset('js/common/layui-v2.13.5/layui/css/layui.css') }}">
    <link rel="stylesheet" href="{{ asset('css/common/theme-sync.css') }}?v=2026052908">
    <style>
        body { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); height: 100vh; display: flex; align-items: center; justify-content: center; margin: 0; }
        .login-box { background: #fff; border-radius: 8px; padding: 40px; width: 400px; box-shadow: 0 8px 32px rgba(0,0,0,0.2); }
        .login-box h2 { text-align: center; margin-bottom: 30px; color: #333; font-size: 24px; }
        .login-box .layui-form-item { margin-bottom: 20px; }
        .login-box .layui-btn { width: 100%; height: 42px; font-size: 16px; }
        .login-box .lang-switch { text-align: center; margin-top: 15px; }
        .login-box .lang-switch a { color: #666; text-decoration: none; margin: 0 8px; font-size: 13px; }
        .login-box .lang-switch a:hover { color: #1e9fff; }
        .error-msg { color: #ff5722; font-size: 13px; text-align: center; margin-bottom: 10px; }
    </style>
</head>
<body>
<div class="login-box">
    <h2 data-translate="auth.admin_login">Admin Login</h2>

    @if($errors->any())
    <div class="error-msg">
        @foreach($errors->all() as $error)
            <p>{{ $error }}</p>
        @endforeach
    </div>
    @endif

    <form class="layui-form" action="{{ route('admin.login.post') }}" method="POST">
        @csrf
        <div class="layui-form-item">
            <input type="text" name="username" value="{{ old('username') }}" required lay-verify="required"
                   placeholder="" data-translate-placeholder="auth.user_name" autocomplete="username" class="layui-input">
        </div>
        <div class="layui-form-item">
            <input type="password" name="password" required lay-verify="required"
                   placeholder="" data-translate-placeholder="auth.password_label" autocomplete="current-password" class="layui-input">
        </div>
        <div class="layui-form-item">
            <input type="checkbox" name="remember" lay-skin="primary" title="" data-translate-title="auth.remember_me">
        </div>
        <div class="layui-form-item">
            <button class="layui-btn layui-btn-normal" lay-submit data-translate="auth.login">Login</button>
        </div>
    </form>

    <div class="lang-switch">
        <a href="javascript:void(0);" class="J_legacyLang" data-lang="zh-CN" data-translate="common.lang_zh">{{ __('common.lang_zh') }}</a> |
        <a href="javascript:void(0);" class="J_legacyLang" data-lang="en" data-translate="common.lang_en">{{ __('common.lang_en') }}</a>
    </div>
</div>
<script src="{{ asset('js/common/jquery/jquery-3.6.0.min.js') }}"></script>
<script src="{{ asset('js/common/layui-v2.13.5/layui/layui.js') }}"></script>
<script src="{{ asset('js/common/i18n.js') }}"></script>
<script src="{{ asset('js/common/lang/zh-CN.js') }}"></script>
<script src="{{ asset('js/common/lang/en.js') }}"></script>
<script>
    layui.use('form', function(){});
    
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
