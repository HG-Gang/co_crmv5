<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title data-translate="reset_password_title">Forgot Password</title>
    <script src="/js/common/theme-sync.js?v=2026052908"></script>
    <link rel="stylesheet" href="/js/common/layui-v2.13.5/layui/css/layui.css">
    <link rel="stylesheet" href="/css/common/theme-sync.css?v=2026052908">
    <style>
        :root { --primary: #18a058; --bg: #f0f2f5; --card: #fff; --text: #1c2127; --border: #e5e7eb; }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { background: var(--bg); display: flex; align-items: center; justify-content: center; min-height: 100vh; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; color: var(--text); }
        .wrap { width: 420px; }
        .logo-area { text-align: center; margin-bottom: 24px; }
        .logo-area h1 { font-size: 24px; font-weight: 700; }
        .card { background: var(--card); border: 1px solid var(--border); border-radius: 12px; padding: 40px; }
        .card-title { font-size: 18px; font-weight: 600; margin-bottom: 8px; }
        .card-desc { color: #888; font-size: 13px; margin-bottom: 24px; }
        .form-group { margin-bottom: 16px; }
        label { display: block; font-size: 14px; font-weight: 500; margin-bottom: 6px; }
        input { width: 100%; height: 42px; border: 1px solid var(--border); border-radius: 8px; padding: 0 14px; font-size: 14px; background: var(--card); color: var(--text); outline: none; }
        input:focus { border-color: var(--primary); }
        .btn { width: 100%; height: 44px; background: var(--primary); color: #fff; border: none; border-radius: 8px; font-size: 15px; cursor: pointer; }
        .suc { background: #f0fdf4; border: 1px solid #86efac; border-radius: 6px; padding: 10px 14px; margin-bottom: 16px; color: #166534; font-size: 13px; }
        .link-row { text-align: center; margin-top: 16px; font-size: 13px; }
        .link-row a { color: var(--primary); text-decoration: none; }
    </style>
</head>
<body>
<div class="wrap">
    <div class="logo-area"><h1 data-translate="system_name">CRM v5</h1></div>
    <div class="card">
        <div class="card-title" data-translate="reset_password_title">Forgot Password</div>
        <div class="card-desc" data-translate="reset_password_desc">Enter your registered email, we will send reset link</div>
        @if(session('success'))
            <div class="suc">✅ {{ session('success') }}</div>
        @endif
        <form method="POST" action="{{ route('user.forget.password.post') }}">
            @csrf
            <div class="form-group">
                <label data-translate="email">Email</label>
                <input type="email" name="email" required placeholder="" data-translate-placeholder="email">
            </div>
            <button type="submit" class="btn" data-translate="send_reset_link">Send Reset Link</button>
        </form>
        <div class="link-row"><a href="{{ route('user.login') }}" data-translate="back_to_login">← Back to Login</a></div>
    </div>
    <div class="lang-switch-container" style="text-align: center; margin-top: 20px;">
        <a href="javascript:void(0);" class="lang-switch" data-lang="zh-CN" style="color: #666; text-decoration: none; margin: 0 8px; font-size: 13px;">中文</a> |
        <a href="javascript:void(0);" class="lang-switch" data-lang="en" style="color: #666; text-decoration: none; margin: 0 8px; font-size: 13px;">English</a>
    </div>
</div>
<script>
var LANG_DATA = {};
(function(){
    var lang = localStorage.getItem('front_lang') || 'en';
    var xhr = new XMLHttpRequest();
    xhr.open('GET', '/js/front/i18n/' + lang + '.js', false);
    xhr.send();
    if (xhr.status === 200) {
        try { eval(xhr.responseText); } catch(e) {}
    }
})();
</script>
<script src="/js/common/jquery/jquery-3.6.0.min.js"></script>
<script src="/js/common/layui-v2.13.5/layui/layui.js"></script>
<script src="/js/front/layui/common.js"></script>
<script>
    layui.config({
        base: '/js/front/layui/'
    }).use(['common', 'jquery'], function() {
        var CRM = layui.common, $ = layui.jquery;
        
        $('.lang-switch').on('click', function() {
            var lang = $(this).data('lang');
            CRM.switchLang(lang);
        });
    });
</script>
</body>
</html>
