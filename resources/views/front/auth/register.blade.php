<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title data-translate="auth.register">Register - CRM v5</title>
    <script src="{{ asset('js/common/theme-sync.js') }}?v=2026052908"></script>
    <link rel="stylesheet" href="{{ asset('js/common/layui-v2.13.5/layui/css/layui.css') }}">
    <link rel="stylesheet" href="{{ asset('css/common/theme-sync.css') }}?v=2026052908">
    <style>
        body { background: #f5f7fa; min-height: 100vh; display: flex; align-items: center; justify-content: center; margin: 0; padding: 20px; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; }
        .register-container { width: 520px; }
        .register-card { background: #fff; border-radius: 12px; padding: 36px; box-shadow: 0 2px 12px rgba(0,0,0,0.08); }
        .register-card .logo { text-align: center; margin-bottom: 24px; }
        .register-card .logo h1 { font-size: 24px; color: #18a058; margin: 0; }
        .register-card .logo p { color: #999; font-size: 14px; margin-top: 4px; }
        .form-row { display: flex; gap: 16px; }
        .form-row .form-group { flex: 1; }
        .form-group { margin-bottom: 16px; }
        .form-group label { display: block; margin-bottom: 4px; font-size: 13px; color: #333; font-weight: 500; }
        .form-group label .required { color: #ff4d4f; }
        .form-group input, .form-group select { width: 100%; height: 38px; padding: 0 12px; border: 1px solid #d9d9d9; border-radius: 6px; font-size: 14px; outline: none; transition: border-color 0.2s; }
        .form-group input:focus, .form-group select:focus { border-color: #18a058; }
        .btn-register { width: 100%; height: 42px; background: #18a058; color: #fff; border: none; border-radius: 6px; font-size: 15px; cursor: pointer; transition: background 0.2s; }
        .btn-register:hover { background: #0e7a42; }
        .inviter-info { padding: 10px 16px; background: #f0faf4; border: 1px solid #b7eb8f; border-radius: 6px; margin-bottom: 16px; font-size: 13px; color: #333; }
        .inviter-info strong { color: #18a058; }
        .error-msg { padding: 8px 12px; background: #fff2f0; border: 1px solid #ffccc7; border-radius: 4px; color: #ff4d4f; margin-bottom: 16px; font-size: 13px; }
        .register-footer { text-align: center; margin-top: 16px; font-size: 13px; }
        .register-footer a { color: #18a058; text-decoration: none; }
        .lang-switch { text-align: center; margin-top: 16px; }
        .lang-switch a { color: #999; text-decoration: none; font-size: 13px; margin: 0 6px; }
    </style>
</head>
<body>
<div class="register-container">
    <div class="register-card">
        <div class="logo">
            <h1>CRM v5</h1>
            <p data-translate="auth.register">Register</p>
        </div>

        @if($errors->any())
        <div class="error-msg">
            @foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach
        </div>
        @endif

        @if($inviterInfo)
        <div class="inviter-info">
            <span data-translate="messages.parent_agent">Parent Agent</span>: <strong>{{ $inviterInfo->user_name }} (ID: {{ $inviterInfo->user_id }})</strong>
        </div>
        @endif

        <form action="{{ route('front.register.post') }}" method="POST">
            @csrf
            <input type="hidden" name="inviter_id" value="{{ $inviterId }}">

            <div class="form-group">
                <label><span data-translate="auth.register_type">Register Type</span> <span class="required">*</span></label>
                <select name="account_type">
                    @if(!$inviterId)
                    <option value="1" {{ $registerType == 1 ? 'selected' : '' }} data-translate="auth.agent">Agent</option>
                    @endif
                    <option value="2" {{ $registerType == 2 ? 'selected' : '' }} data-translate="auth.customer">Customer</option>
                </select>
            </div>

            <div class="form-group">
                <label><span data-translate="auth.email">Email</span> <span class="required">*</span></label>
                <input type="email" name="email" value="{{ old('email') }}" required placeholder="" data-translate-placeholder="auth.email">
            </div>

            <div class="form-group">
                <label><span data-translate="auth.user_name">Username</span> <span class="required">*</span></label>
                <input type="text" name="user_name" value="{{ old('user_name') }}" required placeholder="" data-translate-placeholder="auth.user_name">
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label><span data-translate="auth.password_label">Password</span> <span class="required">*</span></label>
                    <input type="password" name="password" required placeholder="" data-translate-placeholder="auth.password_label" minlength="6">
                </div>
                <div class="form-group">
                    <label><span data-translate="auth.confirm_password">Confirm Password</span> <span class="required">*</span></label>
                    <input type="password" name="password_confirmation" required placeholder="" data-translate-placeholder="auth.confirm_password">
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label data-translate="auth.phone">Phone</label>
                    <input type="text" name="phone" value="{{ old('phone') }}" placeholder="" data-translate-placeholder="auth.phone">
                </div>
                <div class="form-group">
                    <label data-translate="auth.gender">Gender</label>
                    <select name="gender">
                        <option value="1" data-translate="auth.male">Male</option>
                        <option value="2" data-translate="auth.female">Female</option>
                    </select>
                </div>
            </div>

            <div class="form-group">
                <label data-translate="auth.country">Country</label>
                <input type="text" name="country" value="{{ old('country') }}" placeholder="" data-translate-placeholder="auth.country">
            </div>

            @if(!$inviterId)
            <div class="form-group">
                <label data-translate="auth.invite_code_optional">Invite Code (Optional)</label>
                <input type="text" name="inviter_id" value="{{ old('inviter_id') }}" placeholder="" data-translate-placeholder="auth.invite_code">
            </div>
            @endif

            <button type="submit" class="btn-register" data-translate="auth.register">Register</button>
        </form>

        <div class="register-footer">
            <span data-translate="auth.login">Login</span>? <a href="{{ route('front.login') }}" data-translate="auth.login">Login</a>
        </div>
    </div>
    <div class="lang-switch">
        <a href="javascript:void(0);" class="J_legacyLang" data-lang="zh-CN" data-translate="common.lang_zh">{{ __('common.lang_zh') }}</a> |
        <a href="javascript:void(0);" class="J_legacyLang" data-lang="en" data-translate="common.lang_en">{{ __('common.lang_en') }}</a>
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
