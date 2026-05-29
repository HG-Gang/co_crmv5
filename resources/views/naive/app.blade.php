<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $guard === 'admin' ? 'CoCRM Admin' : 'CoCRM Front' }}</title>
    <script src="/js/common/theme-sync.js?v=2026052908"></script>
    <link rel="stylesheet" href="/css/naive-admin/app.css?v=2026052908">
</head>
<body>
    <div id="naive-crm-app">
        <div class="naive-boot-screen" aria-hidden="true">
            <div class="naive-boot-mark"></div>
        </div>
    </div>

    <script>
        window.CrmNaiveBoot = {
            guard: @json($guard),
            page: @json($page ?? 'dashboard'),
            locale: @json(str_replace('_', '-', app()->getLocale())),
            apiBase: @json($guard === 'admin' ? '/api/admin' : '/api/front'),
            loginPath: @json($guard === 'admin' ? '/admin-naive/login' : '/front-naive/login'),
            homePath: @json($guard === 'admin' ? '/admin-naive/dashboard' : '/front-naive/dashboard'),
            legacyPath: @json($guard === 'admin' ? '/admin/dashboard' : '/front/dashboard')
        };
    </script>
    <script src="/js/common/jquery/jquery-3.6.0.min.js"></script>
    <script src="/js/common/i18n.js?v=2026052907"></script>
    <script src="/js/common/echarts.common.min.js"></script>
    <script src="/js/naive-admin/front-plain.js?v=2026052908"></script>
</body>
</html>
