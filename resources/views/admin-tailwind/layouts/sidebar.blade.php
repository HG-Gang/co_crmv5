<aside
    x-cloak
    :class="sidebarOpen ? 'w-64' : 'w-20'"
    class="fixed top-0 left-0 h-screen bg-slate-800 text-white transition-all duration-300 z-40 overflow-y-auto scrollbar-thin"
>
    <!-- Logo -->
    <div class="h-16 flex items-center justify-center border-b border-slate-700">
        <div x-show="sidebarOpen" class="flex items-center gap-3">
            <div class="w-10 h-10 bg-gradient-to-br from-blue-500 to-indigo-600 rounded-lg flex items-center justify-center">
                <i class="fas fa-chart-line text-white text-lg"></i>
            </div>
            <span class="text-xl font-bold">CRM Admin</span>
        </div>
        <div x-show="!sidebarOpen" class="w-10 h-10 bg-gradient-to-br from-blue-500 to-indigo-600 rounded-lg flex items-center justify-center">
            <i class="fas fa-chart-line text-white"></i>
        </div>
    </div>

    <!-- Navigation -->
    <nav class="mt-6 px-3">
        <!-- Dashboard -->
        <a href="{{ route('admin_tailwind_page_dashboard') }}"
           class="flex items-center gap-3 px-3 py-3 mb-1 rounded-lg transition-colors {{ request()->routeIs('admin_tailwind_page_dashboard') ? 'bg-gradient-to-r from-blue-500 to-indigo-600 text-white' : 'text-slate-300 hover:bg-slate-700' }}">
            <i class="fas fa-home w-5 text-center"></i>
            <span x-show="sidebarOpen" class="font-medium">仪表盘</span>
        </a>

        <!-- 用户管理 -->
        <div x-data="{ open: {{ request()->routeIs('admin_tailwind_page_users*') ? 'true' : 'false' }} }">
            <button @click="sidebarOpen && (open = !open)"
                    class="w-full flex items-center justify-between gap-3 px-3 py-3 mb-1 rounded-lg transition-colors text-slate-300 hover:bg-slate-700">
                <div class="flex items-center gap-3">
                    <i class="fas fa-users w-5 text-center"></i>
                    <span x-show="sidebarOpen" class="font-medium">用户管理</span>
                </div>
                <i x-show="sidebarOpen" :class="open ? 'fa-chevron-down' : 'fa-chevron-right'" class="fas text-xs"></i>
            </button>
            <div x-show="open" x-collapse class="ml-8 space-y-1" style="display: none;">
                <a href="{{ route('admin_tailwind_page_users') }}" class="block px-3 py-2 text-sm text-slate-400 hover:text-white transition-colors">用户列表</a>
                <a href="#" class="block px-3 py-2 text-sm text-slate-400 hover:text-white transition-colors">在线用户</a>
                <a href="#" class="block px-3 py-2 text-sm text-slate-400 hover:text-white transition-colors">实名认证</a>
            </div>
        </div>

        <!-- 代理管理 -->
        <a href="{{ route('admin_tailwind_page_agents') }}"
           class="flex items-center gap-3 px-3 py-3 mb-1 rounded-lg transition-colors {{ request()->routeIs('admin_tailwind_page_agents') ? 'bg-gradient-to-r from-blue-500 to-indigo-600 text-white' : 'text-slate-300 hover:bg-slate-700' }}">
            <i class="fas fa-user-tie w-5 text-center"></i>
            <span x-show="sidebarOpen" class="font-medium">代理管理</span>
        </a>

        <!-- 资金管理 -->
        <div x-data="{ open: {{ request()->routeIs('admin_tailwind_page_deposits') || request()->routeIs('admin_tailwind_page_withdrawals') ? 'true' : 'false' }} }">
            <button @click="sidebarOpen && (open = !open)"
                    class="w-full flex items-center justify-between gap-3 px-3 py-3 mb-1 rounded-lg transition-colors text-slate-300 hover:bg-slate-700">
                <div class="flex items-center gap-3">
                    <i class="fas fa-money-bill-wave w-5 text-center"></i>
                    <span x-show="sidebarOpen" class="font-medium">资金管理</span>
                </div>
                <i x-show="sidebarOpen" :class="open ? 'fa-chevron-down' : 'fa-chevron-right'" class="fas text-xs"></i>
            </button>
            <div x-show="open" x-collapse class="ml-8 space-y-1" style="display: none;">
                <a href="{{ route('admin_tailwind_page_deposits') }}" class="block px-3 py-2 text-sm text-slate-400 hover:text-white transition-colors">入金管理</a>
                <a href="{{ route('admin_tailwind_page_withdrawals') }}" class="block px-3 py-2 text-sm text-slate-400 hover:text-white transition-colors">出金管理</a>
                <a href="#" class="block px-3 py-2 text-sm text-slate-400 hover:text-white transition-colors">流水记录</a>
            </div>
        </div>

        <!-- 交易报表 -->
        <div x-data="{ open: {{ request()->routeIs('admin_tailwind_page_position_summary') || request()->routeIs('admin_tailwind_page_realtime_commissions') ? 'true' : 'false' }} }">
            <button @click="sidebarOpen && (open = !open)"
                    class="w-full flex items-center justify-between gap-3 px-3 py-3 mb-1 rounded-lg transition-colors text-slate-300 hover:bg-slate-700">
                <div class="flex items-center gap-3">
                    <i class="fas fa-chart-bar w-5 text-center"></i>
                    <span x-show="sidebarOpen" class="font-medium">交易报表</span>
                </div>
                <i x-show="sidebarOpen" :class="open ? 'fa-chevron-down' : 'fa-chevron-right'" class="fas text-xs"></i>
            </button>
            <div x-show="open" x-collapse class="ml-8 space-y-1" style="display: none;">
                <a href="{{ route('admin_tailwind_page_position_summary') }}" class="block px-3 py-2 text-sm text-slate-400 hover:text-white transition-colors">持仓汇总</a>
                <a href="{{ route('admin_tailwind_page_realtime_commissions') }}" class="block px-3 py-2 text-sm text-slate-400 hover:text-white transition-colors">实时返佣</a>
                <a href="#" class="block px-3 py-2 text-sm text-slate-400 hover:text-white transition-colors">权益汇总</a>
            </div>
        </div>

        <!-- 系统设置 -->
        <div class="mt-6 px-3 py-2 text-xs font-semibold text-slate-500 uppercase tracking-wider" x-show="sidebarOpen">
            系统管理
        </div>

        <a href="#" class="flex items-center gap-3 px-3 py-3 mb-1 rounded-lg transition-colors text-slate-300 hover:bg-slate-700">
            <i class="fas fa-cog w-5 text-center"></i>
            <span x-show="sidebarOpen" class="font-medium">系统配置</span>
        </a>

        <a href="#" class="flex items-center gap-3 px-3 py-3 mb-1 rounded-lg transition-colors text-slate-300 hover:bg-slate-700">
            <i class="fas fa-user-shield w-5 text-center"></i>
            <span x-show="sidebarOpen" class="font-medium">权限管理</span>
        </a>

        <a href="#" class="flex items-center gap-3 px-3 py-3 mb-1 rounded-lg transition-colors text-slate-300 hover:bg-slate-700">
            <i class="fas fa-newspaper w-5 text-center"></i>
            <span x-show="sidebarOpen" class="font-medium">新闻公告</span>
        </a>
    </nav>

    <!-- Sidebar Toggle (Bottom) -->
    <div class="absolute bottom-4 left-0 right-0 px-3">
        <button @click="sidebarOpen = !sidebarOpen"
                class="w-full flex items-center justify-center gap-3 px-3 py-3 rounded-lg transition-colors text-slate-300 hover:bg-slate-700">
            <i :class="sidebarOpen ? 'fa-angles-left' : 'fa-angles-right'" class="fas w-5"></i>
            <span x-show="sidebarOpen" class="font-medium">收起菜单</span>
        </button>
    </div>
</aside>
