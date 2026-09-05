@extends('admin-tailwind.layouts.app')

@section('title', '管理后台 - 仪表盘')

@section('content')
<!-- Page Header -->
<div class="mb-8">
    <h1 class="text-3xl font-bold text-slate-800">仪表盘</h1>
    <p class="text-slate-600 mt-2">实时监控系统运营数据</p>
</div>

<!-- Stats Grid -->
<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6 mb-6">
    <!-- Total Users -->
    <div class="bg-gradient-to-br from-blue-500 to-indigo-600 rounded-xl shadow-lg p-6 text-white">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-blue-100 text-sm font-semibold">总用户数</p>
                <h3 class="text-3xl font-bold mt-2" id="totalUsers">-</h3>
                <p class="text-blue-100 text-xs mt-2">
                    <span id="todayNewUsers">-</span> 今日新增
                </p>
            </div>
            <div class="w-16 h-16 bg-white bg-opacity-20 rounded-full flex items-center justify-center">
                <i class="fas fa-users text-3xl"></i>
            </div>
        </div>
    </div>

    <!-- Total Deposits -->
    <div class="bg-gradient-to-br from-green-500 to-emerald-600 rounded-xl shadow-lg p-6 text-white">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-green-100 text-sm font-semibold">总入金</p>
                <h3 class="text-3xl font-bold mt-2" id="totalDeposits">-</h3>
                <p class="text-green-100 text-xs mt-2">
                    <span id="todayDeposits">-</span> 今日入金
                </p>
            </div>
            <div class="w-16 h-16 bg-white bg-opacity-20 rounded-full flex items-center justify-center">
                <i class="fas fa-arrow-down text-3xl"></i>
            </div>
        </div>
    </div>

    <!-- Total Withdrawals -->
    <div class="bg-gradient-to-br from-orange-500 to-red-600 rounded-xl shadow-lg p-6 text-white">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-orange-100 text-sm font-semibold">总出金</p>
                <h3 class="text-3xl font-bold mt-2" id="totalWithdrawals">-</h3>
                <p class="text-orange-100 text-xs mt-2">
                    <span id="todayWithdrawals">-</span> 今日出金
                </p>
            </div>
            <div class="w-16 h-16 bg-white bg-opacity-20 rounded-full flex items-center justify-center">
                <i class="fas fa-arrow-up text-3xl"></i>
            </div>
        </div>
    </div>

    <!-- Total Trades -->
    <div class="bg-gradient-to-br from-purple-500 to-pink-600 rounded-xl shadow-lg p-6 text-white">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-purple-100 text-sm font-semibold">总交易量</p>
                <h3 class="text-3xl font-bold mt-2" id="totalTrades">-</h3>
                <p class="text-purple-100 text-xs mt-2">
                    <span id="todayTrades">-</span> 今日交易
                </p>
            </div>
            <div class="w-16 h-16 bg-white bg-opacity-20 rounded-full flex items-center justify-center">
                <i class="fas fa-chart-line text-3xl"></i>
            </div>
        </div>
    </div>
</div>

<!-- Secondary Stats -->
<div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
    <!-- Pending Deposits -->
    <div class="bg-white rounded-xl shadow-lg p-6">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-slate-600 text-sm font-semibold">待处理入金</p>
                <h3 class="text-2xl font-bold text-slate-800 mt-2" id="pendingDeposits">-</h3>
            </div>
            <div class="w-12 h-12 bg-yellow-100 rounded-full flex items-center justify-center">
                <i class="fas fa-clock text-yellow-600 text-xl"></i>
            </div>
        </div>
    </div>

    <!-- Pending Withdrawals -->
    <div class="bg-white rounded-xl shadow-lg p-6">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-slate-600 text-sm font-semibold">待处理出金</p>
                <h3 class="text-2xl font-bold text-slate-800 mt-2" id="pendingWithdrawals">-</h3>
            </div>
            <div class="w-12 h-12 bg-orange-100 rounded-full flex items-center justify-center">
                <i class="fas fa-hourglass-half text-orange-600 text-xl"></i>
            </div>
        </div>
    </div>

    <!-- Online Users -->
    <div class="bg-white rounded-xl shadow-lg p-6">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-slate-600 text-sm font-semibold">在线用户</p>
                <h3 class="text-2xl font-bold text-slate-800 mt-2" id="onlineUsers">-</h3>
            </div>
            <div class="w-12 h-12 bg-green-100 rounded-full flex items-center justify-center">
                <i class="fas fa-user-check text-green-600 text-xl"></i>
            </div>
        </div>
    </div>
</div>

<div class="grid grid-cols-1 xl:grid-cols-3 gap-6 mb-6">
    <!-- Recent Activities -->
    <div class="xl:col-span-2 bg-white rounded-xl shadow-lg p-6">
        <div class="flex items-center justify-between mb-6">
            <h3 class="text-lg font-bold text-slate-800">
                <i class="fas fa-bell text-blue-600 mr-2"></i>最新动态
            </h3>
            <button onclick="loadActivities()" class="text-blue-600 hover:text-blue-700 text-sm font-semibold">
                <i class="fas fa-sync-alt mr-1"></i>刷新
            </button>
        </div>
        <div id="activitiesList" class="space-y-3">
            <div class="flex items-center justify-center py-8 text-slate-400">
                <i class="fas fa-spinner fa-spin mr-2"></i>加载中...
            </div>
        </div>
    </div>

    <!-- Quick Actions -->
    <div class="bg-white rounded-xl shadow-lg p-6">
        <h3 class="text-lg font-bold text-slate-800 mb-6">
            <i class="fas fa-bolt text-yellow-600 mr-2"></i>快捷操作
        </h3>
        <div class="space-y-3">
            <a href="{{ route('admin_tailwind_page_deposits') }}" class="flex items-center gap-3 p-3 bg-gradient-to-r from-blue-50 to-indigo-50 rounded-lg hover:shadow-md transition">
                <div class="w-10 h-10 bg-gradient-to-br from-blue-500 to-indigo-600 rounded-full flex items-center justify-center text-white">
                    <i class="fas fa-arrow-down"></i>
                </div>
                <div>
                    <p class="text-sm font-semibold text-slate-800">入金管理</p>
                    <p class="text-xs text-slate-500">处理用户入金申请</p>
                </div>
            </a>

            <a href="{{ route('admin_tailwind_page_withdrawals') }}" class="flex items-center gap-3 p-3 bg-gradient-to-r from-orange-50 to-red-50 rounded-lg hover:shadow-md transition">
                <div class="w-10 h-10 bg-gradient-to-br from-orange-500 to-red-600 rounded-full flex items-center justify-center text-white">
                    <i class="fas fa-arrow-up"></i>
                </div>
                <div>
                    <p class="text-sm font-semibold text-slate-800">出金管理</p>
                    <p class="text-xs text-slate-500">处理用户出金申请</p>
                </div>
            </a>

            <a href="{{ route('admin_tailwind_page_users') }}" class="flex items-center gap-3 p-3 bg-gradient-to-r from-green-50 to-emerald-50 rounded-lg hover:shadow-md transition">
                <div class="w-10 h-10 bg-gradient-to-br from-green-500 to-emerald-600 rounded-full flex items-center justify-center text-white">
                    <i class="fas fa-users"></i>
                </div>
                <div>
                    <p class="text-sm font-semibold text-slate-800">用户管理</p>
                    <p class="text-xs text-slate-500">查看和管理用户</p>
                </div>
            </a>

            <a href="{{ route('admin_tailwind_page_risk') }}" class="flex items-center gap-3 p-3 bg-gradient-to-r from-purple-50 to-pink-50 rounded-lg hover:shadow-md transition">
                <div class="w-10 h-10 bg-gradient-to-br from-purple-500 to-pink-600 rounded-full flex items-center justify-center text-white">
                    <i class="fas fa-shield-alt"></i>
                </div>
                <div>
                    <p class="text-sm font-semibold text-slate-800">风控管理</p>
                    <p class="text-xs text-slate-500">风险监控和处理</p>
                </div>
            </a>

            <a href="{{ route('admin_tailwind_page_system_configs') }}" class="flex items-center gap-3 p-3 bg-gradient-to-r from-slate-50 to-slate-100 rounded-lg hover:shadow-md transition">
                <div class="w-10 h-10 bg-gradient-to-br from-slate-500 to-slate-600 rounded-full flex items-center justify-center text-white">
                    <i class="fas fa-cog"></i>
                </div>
                <div>
                    <p class="text-sm font-semibold text-slate-800">系统配置</p>
                    <p class="text-xs text-slate-500">系统参数设置</p>
                </div>
            </a>
        </div>
    </div>
</div>

<!-- Charts Row -->
<div class="grid grid-cols-1 xl:grid-cols-2 gap-6">
    <!-- Deposits Trend -->
    <div class="bg-white rounded-xl shadow-lg p-6">
        <h3 class="text-lg font-bold text-slate-800 mb-6">
            <i class="fas fa-chart-area text-green-600 mr-2"></i>入金趋势
        </h3>
        <div id="depositChart" class="h-64 flex items-center justify-center text-slate-400">
            <i class="fas fa-chart-line mr-2"></i>图表加载中...
        </div>
    </div>

    <!-- Withdrawals Trend -->
    <div class="bg-white rounded-xl shadow-lg p-6">
        <h3 class="text-lg font-bold text-slate-800 mb-6">
            <i class="fas fa-chart-area text-orange-600 mr-2"></i>出金趋势
        </h3>
        <div id="withdrawalChart" class="h-64 flex items-center justify-center text-slate-400">
            <i class="fas fa-chart-line mr-2"></i>图表加载中...
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    loadDashboardData();
    loadActivities();

    // Auto refresh every 30 seconds
    setInterval(function() {
        loadDashboardData();
        loadActivities();
    }, 30000);
});

function loadDashboardData() {
    fetch('{{ route("admin_api_dashboard_stats") }}', {
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(res => res.json())
    .then(data => {
        if (data.success && data.stats) {
            const s = data.stats;

            // Main stats
            document.getElementById('totalUsers').textContent = formatNumber(s.total_users || 0);
            document.getElementById('todayNewUsers').textContent = formatNumber(s.today_new_users || 0);
            document.getElementById('totalDeposits').textContent = formatMoney(s.total_deposits || 0);
            document.getElementById('todayDeposits').textContent = formatMoney(s.today_deposits || 0);
            document.getElementById('totalWithdrawals').textContent = formatMoney(s.total_withdrawals || 0);
            document.getElementById('todayWithdrawals').textContent = formatMoney(s.today_withdrawals || 0);
            document.getElementById('totalTrades').textContent = formatNumber(s.total_trades || 0);
            document.getElementById('todayTrades').textContent = formatNumber(s.today_trades || 0);

            // Secondary stats
            document.getElementById('pendingDeposits').textContent = formatNumber(s.pending_deposits || 0);
            document.getElementById('pendingWithdrawals').textContent = formatNumber(s.pending_withdrawals || 0);
            document.getElementById('onlineUsers').textContent = formatNumber(s.online_users || 0);
        }
    })
    .catch(err => console.error('Load dashboard data error:', err));
}

function loadActivities() {
    fetch('{{ route("admin_api_dashboard_activities") }}', {
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(res => res.json())
    .then(data => {
        if (data.success && data.activities && data.activities.length > 0) {
            const html = data.activities.slice(0, 10).map(a => `
                <div class="flex items-start gap-3 py-3 border-b border-slate-100 last:border-0">
                    <div class="w-10 h-10 ${getActivityColor(a.type)} rounded-full flex items-center justify-center flex-shrink-0">
                        <i class="fas ${getActivityIcon(a.type)} text-white"></i>
                    </div>
                    <div class="flex-1 min-w-0">
                        <p class="text-sm font-semibold text-slate-800 truncate">${a.title}</p>
                        <p class="text-xs text-slate-500 mt-1">${a.description}</p>
                        <p class="text-xs text-slate-400 mt-1">
                            <i class="fas fa-clock mr-1"></i>${a.created_at}
                        </p>
                    </div>
                </div>
            `).join('');
            document.getElementById('activitiesList').innerHTML = html;
        } else {
            document.getElementById('activitiesList').innerHTML = `
                <div class="flex items-center justify-center py-8 text-slate-400">
                    <i class="fas fa-inbox mr-2"></i>暂无动态
                </div>
            `;
        }
    })
    .catch(err => {
        console.error('Load activities error:', err);
        document.getElementById('activitiesList').innerHTML = `
            <div class="flex items-center justify-center py-8 text-slate-400">
                <i class="fas fa-inbox mr-2"></i>暂无动态
            </div>
        `;
    });
}

function getActivityIcon(type) {
    const icons = {
        'deposit': 'fa-arrow-down',
        'withdrawal': 'fa-arrow-up',
        'trade': 'fa-chart-line',
        'user': 'fa-user-plus',
        'risk': 'fa-exclamation-triangle',
        'system': 'fa-cog'
    };
    return icons[type] || 'fa-info-circle';
}

function getActivityColor(type) {
    const colors = {
        'deposit': 'bg-gradient-to-br from-green-500 to-emerald-600',
        'withdrawal': 'bg-gradient-to-br from-orange-500 to-red-600',
        'trade': 'bg-gradient-to-br from-blue-500 to-indigo-600',
        'user': 'bg-gradient-to-br from-purple-500 to-pink-600',
        'risk': 'bg-gradient-to-br from-red-500 to-pink-600',
        'system': 'bg-gradient-to-br from-slate-500 to-slate-600'
    };
    return colors[type] || 'bg-gradient-to-br from-slate-500 to-slate-600';
}

function formatNumber(num) {
    return new Intl.NumberFormat('en-US').format(num);
}

function formatMoney(amount) {
    return '$' + new Intl.NumberFormat('en-US', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
    }).format(amount);
}
</script>
@endsection
