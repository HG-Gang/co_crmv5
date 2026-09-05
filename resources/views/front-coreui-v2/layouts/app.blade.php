<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'CRM 前台系统') - CRM User</title>

    <!-- Bootstrap 5 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

    <!-- CoreUI CSS -->
    <link href="https://cdn.jsdelivr.net/npm/@coreui/coreui@5.0.0/dist/css/coreui.min.css" rel="stylesheet">

    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.3.0/dist/chart.umd.min.js"></script>

    <style>
        :root {
            --cui-primary: #321fdb;
            --cui-secondary: #9da5b1;
            --cui-success: #2eb85c;
            --cui-info: #39f;
            --cui-warning: #f9b115;
            --cui-danger: #e55353;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            background-color: #f0f3f5;
        }

        .sidebar {
            position: fixed;
            top: 0;
            left: 0;
            height: 100vh;
            width: 256px;
            background: #fff;
            border-right: 1px solid #d8dbe0;
            z-index: 1030;
            overflow-y: auto;
        }

        .sidebar::-webkit-scrollbar {
            width: 6px;
        }

        .sidebar::-webkit-scrollbar-track {
            background: #f8f9fa;
        }

        .sidebar::-webkit-scrollbar-thumb {
            background: #d8dbe0;
            border-radius: 3px;
        }

        .main-content {
            margin-left: 256px;
            min-height: 100vh;
        }

        .header {
            height: 56px;
            background: #fff;
            border-bottom: 1px solid #d8dbe0;
        }

        @media (max-width: 991px) {
            .sidebar {
                transform: translateX(-100%);
                transition: transform 0.3s ease-in-out;
            }
            .sidebar.show {
                transform: translateX(0);
            }
            .main-content {
                margin-left: 0;
            }
        }

        .sidebar-overlay {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(0, 0, 0, 0.5);
            z-index: 1025;
            opacity: 0;
            transition: opacity 0.3s ease-in-out;
        }

        .sidebar-overlay.show {
            display: block;
            opacity: 1;
        }

        body.sidebar-open {
            overflow: hidden;
        }
    </style>

    @stack('styles')
</head>
<body>
    <!-- Mobile Sidebar Overlay -->
    <div class="sidebar-overlay" id="sidebarOverlay"></div>

    <!-- Sidebar -->
    @include('front-coreui-v2.layouts.sidebar')

    <!-- Main Content -->
    <div class="main-content">
        <!-- Header -->
        @include('front-coreui-v2.layouts.header')

        <!-- Page Content -->
        <main class="p-4">
            @yield('content')
        </main>
    </div>

    <!-- Bootstrap Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <!-- CoreUI JS -->
    <script src="https://cdn.jsdelivr.net/npm/@coreui/coreui@5.0.0/dist/js/coreui.bundle.min.js"></script>

    <script>
        // Mobile sidebar toggle logic
        function toggleSidebar() {
            const sidebar = document.querySelector('.sidebar');
            const overlay = document.getElementById('sidebarOverlay');
            const body = document.body;

            sidebar.classList.toggle('show');
            overlay.classList.toggle('show');
            body.classList.toggle('sidebar-open');
        }

        // Close sidebar when clicking overlay
        document.getElementById('sidebarOverlay').addEventListener('click', toggleSidebar);

        // Expose toggle function globally for header button
        window.toggleSidebar = toggleSidebar;
    </script>

    @stack('scripts')
</body>
</html>
