<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'CRM v5')</title>
    <script src="{{ asset('js/common/theme-sync.js') }}?v=2026052908"></script>
    <link rel="stylesheet" href="{{ asset('js/common/layui-v2.13.5/layui/css/layui.css') }}">
    <link rel="stylesheet" href="{{ asset('css/common/theme-sync.css') }}?v=2026052908">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        html, body { height: 100%; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif; background: #f5f7fa; }
        .front-header { background: #fff; height: 56px; display: flex; align-items: center; padding: 0 30px; justify-content: space-between; box-shadow: 0 1px 4px rgba(0,0,0,0.08); position: fixed; top: 0; left: 0; right: 0; z-index: 1000; }
        .front-header .logo { font-size: 18px; font-weight: 600; color: #18a058; }
        .front-header .nav-links { display: flex; gap: 20px; align-items: center; }
        .front-header .nav-links a { color: #666; text-decoration: none; font-size: 14px; padding: 6px 12px; border-radius: 4px; transition: all 0.2s; }
        .front-header .nav-links a:hover, .front-header .nav-links a.active { color: #18a058; background: #f0faf4; }
        .front-header .user-area { display: flex; align-items: center; gap: 12px; }
        .front-header .user-area img { width: 32px; height: 32px; border-radius: 50%; object-fit: cover; }
        .front-header .user-area .user-name { font-size: 14px; color: #333; }
        .front-header .user-area .lang-btn { font-size: 12px; color: #999; cursor: pointer; }
        .front-main { margin-top: 56px; padding: 24px 30px; max-width: 1200px; margin-left: auto; margin-right: auto; }
        .card { background: #fff; border-radius: 8px; padding: 24px; margin-bottom: 20px; box-shadow: 0 1px 4px rgba(0,0,0,0.06); }
        .card h3 { font-size: 16px; color: #333; margin-bottom: 16px; padding-bottom: 12px; border-bottom: 1px solid #f0f0f0; }
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 16px; margin-bottom: 24px; }
        .stat-box { background: #fff; border-radius: 8px; padding: 20px; box-shadow: 0 1px 4px rgba(0,0,0,0.06); text-align: center; }
        .stat-box .number { font-size: 32px; font-weight: 700; color: #18a058; }
        .stat-box .label { font-size: 13px; color: #999; margin-top: 4px; }
        .success-msg { padding: 10px 16px; background: #f0faf4; border: 1px solid #b7eb8f; border-radius: 4px; color: #52c41a; margin-bottom: 16px; }
        .error-msg { padding: 10px 16px; background: #fff2f0; border: 1px solid #ffccc7; border-radius: 4px; color: #ff4d4f; margin-bottom: 16px; }
        @yield('style')
    </style>
</head>
<body>
    <div class="front-header">
        <div style="display:flex;align-items:center;gap:30px;">
            <div class="logo">{{ __('common.system_name') }}</div>
            <div class="nav-links">
                <a href="{{ route('front.dashboard') }}" class="{{ request()->routeIs('front.dashboard*') ? 'active' : '' }}">{{ __('auth.dashboard') }}</a>
                <a href="{{ route('front.profile') }}" class="{{ request()->routeIs('front.profile*') ? 'active' : '' }}">{{ __('auth.profile') }}</a>
                <a href="{{ route('front.password.form') }}" class="{{ request()->routeIs('front.password*') ? 'active' : '' }}">{{ __('auth.change_password') }}</a>
            </div>
        </div>
        <div class="user-area">
            @php $userLogin = Auth::guard('user')->user(); @endphp
            <span class="user-name">{{ $userLogin->email ?? '' }}</span>
            <a href="javascript:;" class="lang-btn legacy-lang-switch" data-lang="en">{{ __('common.lang_en') }}</a>
            <a href="javascript:;" class="lang-btn legacy-lang-switch" data-lang="zh-CN">{{ __('common.lang_zh') }}</a>
            <a href="javascript:;" id="frontLogoutBtn" style="color:#ff4d4f;font-size:13px;text-decoration:none;">{{ __('auth.logout') }}</a>
            <form id="front-logout" action="{{ route('front.logout') }}" method="POST" style="display:none;">@csrf</form>
        </div>
    </div>

    <div class="front-main">
        @if(session('success'))
            <div class="success-msg">{{ session('success') }}</div>
        @endif
        @if($errors->any())
            <div class="error-msg">
                @foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach
            </div>
        @endif
        @yield('content')
    </div>

    <script src="{{ asset('js/common/jquery/jquery-3.6.0.min.js') }}"></script>
    <script src="{{ asset('js/common/layui-v2.13.5/layui/layui.js') }}"></script>
    <script src="{{ asset('js/common/i18n.js') }}"></script>
    <script src="{{ asset('js/common/lang/' . app()->getLocale() . '.js') }}"></script>
    <script>
        layui.use(['element', 'layer', 'form', 'jquery'], function(){
            var $ = layui.jquery;
            CrmI18n.setLocale('{{ app()->getLocale() }}');

            $('.legacy-lang-switch').on('click', function() {
                CrmI18n.setLocale($(this).data('lang'));
            });

            $('#frontLogoutBtn').on('click', function() {
                $('#front-logout').trigger('submit');
            });
        });

        function updatePageLanguage(locale) {
            CrmI18n.setLocale(locale);
        }
    </script>
    @yield('scripts')
</body>
</html>
