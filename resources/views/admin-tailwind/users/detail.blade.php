@extends('admin-tailwind.layouts.app')

@section('title', '用户详情 - 管理后台')

@section('content')
<!-- Page Header -->
<div class="flex items-center justify-between mb-8">
    <div>
        <h1 class="text-3xl font-bold text-slate-800">用户详情</h1>
        <p class="text-slate-600 mt-2">查看用户完整信息和操作历史</p>
    </div>
    <button onclick="history.back()" class="px-6 py-2 bg-slate-200 text-slate-700 font-semibold rounded-lg hover:bg-slate-300 transition">
        <i class="fas fa-arrow-left mr-2"></i>返回
    </button>
</div>

<div class="grid grid-cols-1 xl:grid-cols-3 gap-6">
    <!-- User Profile Card -->
    <div class="xl:col-span-1">
        <div class="bg-white rounded-xl shadow-lg p-6">
            <div class="flex flex-col items-center">
                <div class="w-24 h-24 rounded-full bg-gradient-to-br from-blue-500 to-indigo-600 flex items-center justify-center text-white text-4xl font-bold mb-4">
                    <span id="avatarPreview">U</span>
                </div>
                <h3 class="text-xl font-bold text-slate-800" id="userName">-</h3>
                <p class="text-sm text-slate-500 mt-1" id="userEmail">-</p>
                <div class="mt-3" id="userStatusBadge"></div>
            </div>

            <div class="mt-6 space-y-3">
                <div class="flex items-center justify-between py-2 border-b border-slate-100">
                    <span class="text-sm text-slate-600">用户ID</span>
                    <span class="text-sm font-semibold text-slate-800" id="userId">-</span>
                </div>
                <div class="flex items-center justify-between py-2 border-b border-slate-100">
                    <span class="text-sm text-slate-600">用户类型</span>
                    <span id="userTypeBadge">-</span>
                </div>
                <div class="flex items-center justify-between py-2 border-b border-slate-100">
                    <span class="text-sm text-slate-600">手机号</span>
                    <span class="text-sm text-slate-600" id="userPhone">-</span>
                </div>
                <div class="flex items-center justify-between py-2 border-b border-slate-100">
                    <span class="text-sm text-slate-600">真实姓名</span>
                    <span class="text-sm text-slate-600" id="realName">-</span>
                </div>
                <div class="flex items-center justify-between py-2 border-b border-slate-100">
                    <span class="text-sm text-slate-600">注册时间</span>
                    <span class="text-sm text-slate-600" id="registerTime">-</span>
                </div>
                <div class="flex items-center justify-between py-2">
                    <span class="text-sm text-slate-600">最后登录</span>
                    <span class="text-sm text-slate-600" id="lastLogin">-</span>
                </div>
            </div>

            <div class="mt-6 space-y-2">
                <button onclick="editUser()" class="w-full px-4 py-2 bg-gradient-to-r from-blue-500 to-indigo-600 text-white font-semibold rounded-lg hover:shadow-lg transition">
                    <i class="fas fa-edit mr-2"></i>编辑用户
                </button>
                <button onclick="resetPassword()" class="w-full px-4 py-2 bg-gradient-to-r from-orange-500 to-red-600 text-white font-semibold rounded-lg hover:shadow-lg transition">
                    <i class="fas fa-key mr-2"></i>重置密码
                </button>
            </div>
        </div>

        <div class="bg-white rounded-xl shadow-lg p-6 mt-6">
            <h3 class="text-lg font-bold text-slate-800 mb-4">备注信息</h3>
            <textarea id="userRemark" rows="4" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" placeholder="暂无备注"></textarea>
            <button onclick="saveRemark()" class="mt-3 w-full px-4 py-2 bg-gradient-to-r from-green-500 to-emerald-600 text-white font-semibold rounded-lg hover:shadow-lg transition">
                <i class="fas fa-save mr-2"></i>保存备注
            </button>
        </div>
    </div>

    <!-- User Details -->
    <div class="xl:col-span-2">
        <!-- Stats Cards -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
            <div class="bg-white rounded-xl shadow-lg p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-slate-600 text-sm font-semibold">账户余额</p>
                        <h3 class="text-2xl font-bold text-slate-800 mt-2" id="accountBalance">$0.00</h3>
                    </div>
                    <div class="w-12 h-12 bg-gradient-to-br from-green-500 to-emerald-600 rounded-full flex items-center justify-center text-white">
                        <i class="fas fa-wallet"></i>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-xl shadow-lg p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-slate-600 text-sm font-semibold">总入金</p>
                        <h3 class="text-2xl font-bold text-slate-800 mt-2" id="totalDeposits">$0.00</h3>
                    </div>
                    <div class="w-12 h-12 bg-gradient-to-br from-blue-500 to-indigo-600 rounded-full flex items-center justify-center text-white">
                        <i class="fas fa-arrow-down"></i>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-xl shadow-lg p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-slate-600 text-sm font-semibold">总出金</p>
                        <h3 class="text-2xl font-bold text-slate-800 mt-2" id="totalWithdrawals">$0.00</h3>
                    </div>
                    <div class="w-12 h-12 bg-gradient-to-br from-orange-500 to-red-600 rounded-full flex items-center justify-center text-white">
                        <i class="fas fa-arrow-up"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- Tabs -->
        <div class="bg-white rounded-xl shadow-lg overflow-hidden">
            <div class="border-b border-slate-200">
                <div class="flex">
                    <button onclick="switchTab('mt4')" id="tabMt4" class="flex-1 px-6 py-4 text-sm font-semibold text-blue-600 border-b-2 border-blue-600 bg-blue-50">
                        MT4账户
                    </button>
                    <button onclick="switchTab('deposits')" id="tabDeposits" class="flex-1 px-6 py-4 text-sm font-semibold text-slate-600 hover:bg-slate-50">
                        入金记录
                    </button>
                    <button onclick="switchTab('withdrawals')" id="tabWithdrawals" class="flex-1 px-6 py-4 text-sm font-semibold text-slate-600 hover:bg-slate-50">
                        出金记录
                    </button>
                    <button onclick="switchTab('trades')" id="tabTrades" class="flex-1 px-6 py-4 text-sm font-semibold text-slate-600 hover:bg-slate-50">
                        交易记录
                    </button>
                    <button onclick="switchTab('logs')" id="tabLogs" class="flex-1 px-6 py-4 text-sm font-semibold text-slate-600 hover:bg-slate-50">
                        操作日志
                    </button>
                </div>
            </div>

            <div class="p-6">
                <!-- MT4 Accounts Tab -->
                <div id="contentMt4">
                    <div class="space-y-4" id="mt4List">
                        <div class="flex items-center justify-center py-8 text-slate-400">
                            <i class="fas fa-spinner fa-spin mr-2"></i>加载中...
                        </div>
                    </div>
                </div>

                <!-- Deposits Tab -->
                <div id="contentDeposits" class="hidden">
                    <div class="space-y-4" id="depositsList">
                        <div class="flex items-center justify-center py-8 text-slate-400">
                            <i class="fas fa-spinner fa-spin mr-2"></i>加载中...
                        </div>
                    </div>
                </div>

                <!-- Withdrawals Tab -->
                <div id="contentWithdrawals" class="hidden">
                    <div class="space-y-4" id="withdrawalsList">
                        <div class="flex items-center justify-center py-8 text-slate-400">
                            <i class="fas fa-spinner fa-spin mr-2"></i>加载中...
                        </div>
                    </div>
                </div>

                <!-- Trades Tab -->
                <div id="contentTrades" class="hidden">
                    <div class="space-y-4" id="tradesList">
                        <div class="flex items-center justify-center py-8 text-slate-400">
                            <i class="fas fa-spinner fa-spin mr-2"></i>加载中...
                        </div>
                    </div>
                </div>

                <!-- Logs Tab -->
                <div id="contentLogs" class="hidden">
                    <div class="space-y-4" id="logsList">
                        <div class="flex items-center justify-center py-8 text-slate-400">
                            <i class="fas fa-spinner fa-spin mr-2"></i>加载中...
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
let currentUserId = null;

document.addEventListener('DOMContentLoaded', function() {
    const urlParams = new URLSearchParams(window.location.search);
    currentUserId = urlParams.get('id');

    if (currentUserId) {
        loadUserDetail();
        loadMt4Accounts();
    }
});

function loadUserDetail() {
    fetch(`{{ route("admin_api_users_detail") }}?id=${currentUserId}`, {
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(res => res.json())
    .then(data => {
        if (data.success && data.user) {
            const u = data.user;

            // Profile
            document.getElementById('avatarPreview').textContent = u.username.charAt(0).toUpperCase();
            document.getElementById('userName').textContent = u.username;
            document.getElementById('userEmail').textContent = u.email || '-';
            document.getElementById('userId').textContent = u.id;
            document.getElementById('userPhone').textContent = u.phone || '-';
            document.getElementById('realName').textContent = u.real_name || '-';
            document.getElementById('registerTime').textContent = u.created_at || '-';
            document.getElementById('lastLogin').textContent = u.last_login || '-';
            document.getElementById('userRemark').value = u.remark || '';

            // Badges
            document.getElementById('userStatusBadge').innerHTML = getStatusBadge(u.status);
            document.getElementById('userTypeBadge').innerHTML = getUserTypeBadge(u.user_type);

            // Stats
            document.getElementById('accountBalance').textContent = formatMoney(u.balance || 0);
            document.getElementById('totalDeposits').textContent = formatMoney(u.total_deposits || 0);
            document.getElementById('totalWithdrawals').textContent = formatMoney(u.total_withdrawals || 0);
        }
    })
    .catch(err => console.error('Load user detail error:', err));
}

function loadMt4Accounts() {
    fetch(`{{ route("admin_api_users_mt4_accounts") }}?user_id=${currentUserId}`, {
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(res => res.json())
    .then(data => {
        if (data.success && data.accounts && data.accounts.length > 0) {
            const html = data.accounts.map(a => `
                <div class="border border-slate-200 rounded-lg p-4">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-3">
                            <div class="w-12 h-12 bg-gradient-to-br from-blue-500 to-indigo-600 rounded-full flex items-center justify-center text-white">
                                <i class="fas fa-server"></i>
                            </div>
                            <div>
                                <p class="text-sm font-semibold text-slate-800">MT4: ${a.login}</p>
                                <p class="text-xs text-slate-500">组别: ${a.group || '-'}</p>
                            </div>
                        </div>
                        <div class="text-right">
                            <p class="text-lg font-bold text-slate-800">${formatMoney(a.balance || 0)}</p>
                            <p class="text-xs text-slate-500">余额</p>
                        </div>
                    </div>
                    <div class="mt-3 grid grid-cols-3 gap-3 text-center">
                        <div>
                            <p class="text-xs text-slate-500">权益</p>
                            <p class="text-sm font-semibold text-slate-800">${formatMoney(a.equity || 0)}</p>
                        </div>
                        <div>
                            <p class="text-xs text-slate-500">保证金</p>
                            <p class="text-sm font-semibold text-slate-800">${formatMoney(a.margin || 0)}</p>
                        </div>
                        <div>
                            <p class="text-xs text-slate-500">可用</p>
                            <p class="text-sm font-semibold text-slate-800">${formatMoney(a.free_margin || 0)}</p>
                        </div>
                    </div>
                </div>
            `).join('');
            document.getElementById('mt4List').innerHTML = html;
        } else {
            document.getElementById('mt4List').innerHTML = `
                <div class="flex items-center justify-center py-8 text-slate-400">
                    <i class="fas fa-inbox mr-2"></i>暂无MT4账户
                </div>
            `;
        }
    })
    .catch(err => {
        console.error('Load MT4 accounts error:', err);
        document.getElementById('mt4List').innerHTML = `
            <div class="flex items-center justify-center py-8 text-slate-400">
                <i class="fas fa-inbox mr-2"></i>暂无MT4账户
            </div>
        `;
    });
}

function switchTab(tab) {
    // Reset all tabs
    ['mt4', 'deposits', 'withdrawals', 'trades', 'logs'].forEach(t => {
        document.getElementById(`tab${t.charAt(0).toUpperCase() + t.slice(1)}`).className = 'flex-1 px-6 py-4 text-sm font-semibold text-slate-600 hover:bg-slate-50';
        document.getElementById(`content${t.charAt(0).toUpperCase() + t.slice(1)}`).classList.add('hidden');
    });

    // Activate selected tab
    const tabName = tab.charAt(0).toUpperCase() + tab.slice(1);
    document.getElementById(`tab${tabName}`).className = 'flex-1 px-6 py-4 text-sm font-semibold text-blue-600 border-b-2 border-blue-600 bg-blue-50';
    document.getElementById(`content${tabName}`).classList.remove('hidden');

    // Load content
    if (tab === 'deposits' && document.getElementById('depositsList').innerHTML.includes('加载中')) {
        loadDeposits();
    } else if (tab === 'withdrawals' && document.getElementById('withdrawalsList').innerHTML.includes('加载中')) {
        loadWithdrawals();
    } else if (tab === 'trades' && document.getElementById('tradesList').innerHTML.includes('加载中')) {
        loadTrades();
    } else if (tab === 'logs' && document.getElementById('logsList').innerHTML.includes('加载中')) {
        loadLogs();
    }
}

function loadDeposits() {
    fetch(`{{ route("admin_api_users_deposits") }}?user_id=${currentUserId}`, {
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(res => res.json())
    .then(data => {
        if (data.success && data.deposits && data.deposits.length > 0) {
            const html = data.deposits.slice(0, 10).map(d => `
                <div class="border border-slate-200 rounded-lg p-4">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-semibold text-slate-800">入金金额: ${formatMoney(d.amount)}</p>
                            <p class="text-xs text-slate-500 mt-1">时间: ${d.created_at}</p>
                        </div>
                        ${getDepositStatusBadge(d.status)}
                    </div>
                </div>
            `).join('');
            document.getElementById('depositsList').innerHTML = html;
        } else {
            document.getElementById('depositsList').innerHTML = `
                <div class="flex items-center justify-center py-8 text-slate-400">
                    <i class="fas fa-inbox mr-2"></i>暂无入金记录
                </div>
            `;
        }
    })
    .catch(err => {
        console.error('Load deposits error:', err);
        document.getElementById('depositsList').innerHTML = `
            <div class="flex items-center justify-center py-8 text-slate-400">
                <i class="fas fa-inbox mr-2"></i>暂无入金记录
            </div>
        `;
    });
}

function loadWithdrawals() {
    fetch(`{{ route("admin_api_users_withdrawals") }}?user_id=${currentUserId}`, {
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(res => res.json())
    .then(data => {
        if (data.success && data.withdrawals && data.withdrawals.length > 0) {
            const html = data.withdrawals.slice(0, 10).map(w => `
                <div class="border border-slate-200 rounded-lg p-4">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-semibold text-slate-800">出金金额: ${formatMoney(w.amount)}</p>
                            <p class="text-xs text-slate-500 mt-1">时间: ${w.created_at}</p>
                        </div>
                        ${getWithdrawalStatusBadge(w.status)}
                    </div>
                </div>
            `).join('');
            document.getElementById('withdrawalsList').innerHTML = html;
        } else {
            document.getElementById('withdrawalsList').innerHTML = `
                <div class="flex items-center justify-center py-8 text-slate-400">
                    <i class="fas fa-inbox mr-2"></i>暂无出金记录
                </div>
            `;
        }
    })
    .catch(err => {
        console.error('Load withdrawals error:', err);
        document.getElementById('withdrawalsList').innerHTML = `
            <div class="flex items-center justify-center py-8 text-slate-400">
                <i class="fas fa-inbox mr-2"></i>暂无出金记录
            </div>
        `;
    });
}

function loadTrades() {
    fetch(`{{ route("admin_api_users_trades") }}?user_id=${currentUserId}`, {
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(res => res.json())
    .then(data => {
        if (data.success && data.trades && data.trades.length > 0) {
            const html = data.trades.slice(0, 10).map(t => `
                <div class="border border-slate-200 rounded-lg p-4">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-semibold text-slate-800">${t.symbol} - ${t.cmd === 0 ? 'BUY' : 'SELL'} ${t.volume} lots</p>
                            <p class="text-xs text-slate-500 mt-1">时间: ${t.open_time}</p>
                        </div>
                        <div class="text-right">
                            <p class="text-sm font-bold ${t.profit >= 0 ? 'text-green-600' : 'text-red-600'}">
                                ${t.profit >= 0 ? '+' : ''}${formatMoney(t.profit)}
                            </p>
                        </div>
                    </div>
                </div>
            `).join('');
            document.getElementById('tradesList').innerHTML = html;
        } else {
            document.getElementById('tradesList').innerHTML = `
                <div class="flex items-center justify-center py-8 text-slate-400">
                    <i class="fas fa-inbox mr-2"></i>暂无交易记录
                </div>
            `;
        }
    })
    .catch(err => {
        console.error('Load trades error:', err);
        document.getElementById('tradesList').innerHTML = `
            <div class="flex items-center justify-center py-8 text-slate-400">
                <i class="fas fa-inbox mr-2"></i>暂无交易记录
            </div>
        `;
    });
}

function loadLogs() {
    fetch(`{{ route("admin_api_users_logs") }}?user_id=${currentUserId}`, {
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(res => res.json())
    .then(data => {
        if (data.success && data.logs && data.logs.length > 0) {
            const html = data.logs.slice(0, 10).map(l => `
                <div class="border border-slate-200 rounded-lg p-4">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 bg-gradient-to-br from-blue-500 to-indigo-600 rounded-full flex items-center justify-center text-white">
                            <i class="fas fa-info-circle"></i>
                        </div>
                        <div class="flex-1">
                            <p class="text-sm font-semibold text-slate-800">${l.action}</p>
                            <p class="text-xs text-slate-500 mt-1">${l.description || '-'}</p>
                            <p class="text-xs text-slate-400 mt-1">
                                <i class="fas fa-clock mr-1"></i>${l.created_at}
                            </p>
                        </div>
                    </div>
                </div>
            `).join('');
            document.getElementById('logsList').innerHTML = html;
        } else {
            document.getElementById('logsList').innerHTML = `
                <div class="flex items-center justify-center py-8 text-slate-400">
                    <i class="fas fa-inbox mr-2"></i>暂无操作日志
                </div>
            `;
        }
    })
    .catch(err => {
        console.error('Load logs error:', err);
        document.getElementById('logsList').innerHTML = `
            <div class="flex items-center justify-center py-8 text-slate-400">
                <i class="fas fa-inbox mr-2"></i>暂无操作日志
            </div>
        `;
    });
}

function getUserTypeBadge(type) {
    const badges = {
        'user': '<span class="px-2 py-1 bg-blue-100 text-blue-700 text-xs font-semibold rounded-full"><i class="fas fa-user mr-1"></i>普通用户</span>',
        'agent': '<span class="px-2 py-1 bg-purple-100 text-purple-700 text-xs font-semibold rounded-full"><i class="fas fa-user-tie mr-1"></i>代理</span>',
        'big_agent': '<span class="px-2 py-1 bg-yellow-100 text-yellow-700 text-xs font-semibold rounded-full"><i class="fas fa-crown mr-1"></i>大代理</span>'
    };
    return badges[type] || '<span class="px-2 py-1 bg-slate-100 text-slate-700 text-xs font-semibold rounded-full">未知</span>';
}

function getStatusBadge(status) {
    const badges = {
        'active': '<span class="px-3 py-1 bg-green-100 text-green-700 text-sm font-semibold rounded-full"><i class="fas fa-check-circle mr-1"></i>正常</span>',
        'frozen': '<span class="px-3 py-1 bg-orange-100 text-orange-700 text-sm font-semibold rounded-full"><i class="fas fa-lock mr-1"></i>冻结</span>',
        'deleted': '<span class="px-3 py-1 bg-red-100 text-red-700 text-sm font-semibold rounded-full"><i class="fas fa-times-circle mr-1"></i>已删除</span>'
    };
    return badges[status] || '<span class="px-3 py-1 bg-slate-100 text-slate-700 text-sm font-semibold rounded-full">未知</span>';
}

function getDepositStatusBadge(status) {
    const badges = {
        'pending': '<span class="px-2 py-1 bg-yellow-100 text-yellow-700 text-xs font-semibold rounded-full">待处理</span>',
        'approved': '<span class="px-2 py-1 bg-green-100 text-green-700 text-xs font-semibold rounded-full">已通过</span>',
        'rejected': '<span class="px-2 py-1 bg-red-100 text-red-700 text-xs font-semibold rounded-full">已拒绝</span>'
    };
    return badges[status] || '<span class="px-2 py-1 bg-slate-100 text-slate-700 text-xs font-semibold rounded-full">未知</span>';
}

function getWithdrawalStatusBadge(status) {
    const badges = {
        'pending': '<span class="px-2 py-1 bg-yellow-100 text-yellow-700 text-xs font-semibold rounded-full">待处理</span>',
        'processing': '<span class="px-2 py-1 bg-blue-100 text-blue-700 text-xs font-semibold rounded-full">处理中</span>',
        'completed': '<span class="px-2 py-1 bg-green-100 text-green-700 text-xs font-semibold rounded-full">已完成</span>',
        'failed': '<span class="px-2 py-1 bg-red-100 text-red-700 text-xs font-semibold rounded-full">失败</span>'
    };
    return badges[status] || '<span class="px-2 py-1 bg-slate-100 text-slate-700 text-xs font-semibold rounded-full">未知</span>';
}

function formatMoney(amount) {
    return '$' + new Intl.NumberFormat('en-US', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
    }).format(amount);
}

function editUser() {
    window.location.href = '{{ route("admin_tailwind_page_users") }}?edit=' + currentUserId;
}

function resetPassword() {
    if (!confirm('确定要重置该用户的密码吗？')) return;

    fetch('{{ route("admin_api_users_reset_password") }}', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: JSON.stringify({ user_id: currentUserId })
    })
    .then(res => res.json())
    .then(data => {
        if (data.success || data.code === 200) {
            alert(`密码已重置为: ${data.new_password || '123456'}`);
        } else {
            alert(data.message || '重置失败');
        }
    })
    .catch(err => {
        console.error('Reset password error:', err);
        alert('网络错误，请稍后重试');
    });
}

function saveRemark() {
    const remark = document.getElementById('userRemark').value.trim();

    fetch('{{ route("admin_api_users_update_remark") }}', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: JSON.stringify({
            user_id: currentUserId,
            remark: remark
        })
    })
    .then(res => res.json())
    .then(data => {
        if (data.success || data.code === 200) {
            alert('备注已保存');
        } else {
            alert(data.message || '保存失败');
        }
    })
    .catch(err => {
        console.error('Save remark error:', err);
        alert('网络错误，请稍后重试');
    });
}
</script>
@endsection
