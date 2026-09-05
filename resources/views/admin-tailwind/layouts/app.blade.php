<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', '后台管理系统') - CRM Admin</title>

    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>

    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.3.0/dist/chart.umd.min.js"></script>

    <!-- Alpine.js -->
    <script defer src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js"></script>

    <style>
        [x-cloak] { display: none !important; }

        /* Tailwind 自定义配置 */
        @layer utilities {
            .scrollbar-thin::-webkit-scrollbar {
                width: 6px;
                height: 6px;
            }
            .scrollbar-thin::-webkit-scrollbar-track {
                background: #1e293b;
            }
            .scrollbar-thin::-webkit-scrollbar-thumb {
                background: #475569;
                border-radius: 3px;
            }
            .scrollbar-thin::-webkit-scrollbar-thumb:hover {
                background: #64748b;
            }
        }
    </style>

    @stack('styles')
</head>
<body class="bg-slate-100 font-sans antialiased">
    <div x-data="{ sidebarOpen: true }" class="min-h-screen">
        <!-- Sidebar -->
        @include('admin-tailwind.layouts.sidebar')

        <!-- Main Content -->
        <div :class="sidebarOpen ? 'lg:ml-64' : 'lg:ml-20'" class="transition-all duration-300">
            <!-- Header -->
            @include('admin-tailwind.layouts.header')

            <!-- Page Content -->
            <main class="p-6">
                @yield('content')
            </main>
        </div>
    </div>

    @stack('scripts')
</body>
</html>
