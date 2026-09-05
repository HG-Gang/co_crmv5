<header class="bg-white shadow-sm h-16 flex items-center justify-between px-6 sticky top-0 z-30">
    <!-- Left: Page Title -->
    <div>
        <h1 class="text-xl font-semibold text-slate-800">@yield('page-title', '仪表盘')</h1>
    </div>

    <!-- Right: User Menu -->
    <div class="flex items-center gap-4">
        <!-- Notifications -->
        <div x-data="{ open: false }" class="relative">
            <button @click="open = !open" class="relative p-2 text-slate-600 hover:text-slate-800 hover:bg-slate-100 rounded-lg transition-colors">
                <i class="fas fa-bell text-lg"></i>
                <span class="absolute top-1 right-1 w-2 h-2 bg-red-500 rounded-full"></span>
            </button>
            <div x-show="open" @click.away="open = false" x-cloak
                 class="absolute right-0 mt-2 w-80 bg-white rounded-lg shadow-lg border border-slate-200 py-2">
                <div class="px-4 py-2 border-b border-slate-200">
                    <h3 class="font-semibold text-slate-800">通知</h3>
                </div>
                <div class="max-h-96 overflow-y-auto">
                    <a href="#" class="block px-4 py-3 hover:bg-slate-50 transition-colors">
                        <div class="flex items-start gap-3">
                            <div class="w-8 h-8 bg-blue-100 rounded-full flex items-center justify-center flex-shrink-0">
                                <i class="fas fa-user text-blue-600 text-sm"></i>
                            </div>
                            <div class="flex-1 min-w-0">
                                <p class="text-sm text-slate-800">新用户注册</p>
                                <p class="text-xs text-slate-500 mt-1">张三刚刚完成注册</p>
                                <p class="text-xs text-slate-400 mt-1">5分钟前</p>
                            </div>
                        </div>
                    </a>
                    <a href="#" class="block px-4 py-3 hover:bg-slate-50 transition-colors">
                        <div class="flex items-start gap-3">
                            <div class="w-8 h-8 bg-green-100 rounded-full flex items-center justify-center flex-shrink-0">
                                <i class="fas fa-money-bill text-green-600 text-sm"></i>
                            </div>
                            <div class="flex-1 min-w-0">
                                <p class="text-sm text-slate-800">入金申请</p>
                                <p class="text-xs text-slate-500 mt-1">李四申请入金 ¥10,000</p>
                                <p class="text-xs text-slate-400 mt-1">15分钟前</p>
                            </div>
                        </div>
                    </a>
                    <a href="#" class="block px-4 py-3 hover:bg-slate-50 transition-colors">
                        <div class="flex items-start gap-3">
                            <div class="w-8 h-8 bg-yellow-100 rounded-full flex items-center justify-center flex-shrink-0">
                                <i class="fas fa-exclamation-triangle text-yellow-600 text-sm"></i>
                            </div>
                            <div class="flex-1 min-w-0">
                                <p class="text-sm text-slate-800">风控警告</p>
                                <p class="text-xs text-slate-500 mt-1">账户 MT4-12345 接近强平</p>
                                <p class="text-xs text-slate-400 mt-1">1小时前</p>
                            </div>
                        </div>
                    </a>
                </div>
                <div class="px-4 py-2 border-t border-slate-200">
                    <a href="#" class="text-sm text-blue-600 hover:text-blue-700">查看全部通知</a>
                </div>
            </div>
        </div>

        <!-- User Dropdown -->
        <div x-data="{ open: false }" class="relative">
            <button @click="open = !open" class="flex items-center gap-3 p-2 hover:bg-slate-100 rounded-lg transition-colors">
                <div class="w-8 h-8 bg-gradient-to-br from-blue-500 to-indigo-600 rounded-full flex items-center justify-center">
                    <span class="text-white text-sm font-medium">管</span>
                </div>
                <div class="text-left hidden md:block">
                    <p class="text-sm font-medium text-slate-800">管理员</p>
                    <p class="text-xs text-slate-500">超级管理员</p>
                </div>
                <i class="fas fa-chevron-down text-slate-400 text-xs"></i>
            </button>
            <div x-show="open" @click.away="open = false" x-cloak
                 class="absolute right-0 mt-2 w-48 bg-white rounded-lg shadow-lg border border-slate-200 py-2">
                <a href="#" class="block px-4 py-2 text-sm text-slate-700 hover:bg-slate-50 transition-colors">
                    <i class="fas fa-user w-5 text-slate-400"></i> 个人资料
                </a>
                <a href="#" class="block px-4 py-2 text-sm text-slate-700 hover:bg-slate-50 transition-colors">
                    <i class="fas fa-key w-5 text-slate-400"></i> 修改密码
                </a>
                <a href="#" class="block px-4 py-2 text-sm text-slate-700 hover:bg-slate-50 transition-colors">
                    <i class="fas fa-cog w-5 text-slate-400"></i> 系统设置
                </a>
                <div class="border-t border-slate-200 my-2"></div>
                <a href="#" class="block px-4 py-2 text-sm text-red-600 hover:bg-red-50 transition-colors">
                    <i class="fas fa-sign-out-alt w-5"></i> 退出登录
                </a>
            </div>
        </div>
    </div>
</header>
