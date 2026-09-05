@extends('admin-tailwind.layouts.app')

@section('title', '在线用户 - 管理后台')

@section('content')
<!-- Page Header -->
<div class="flex items-center justify-between mb-8">
    <div>
        <h1 class="text-3xl font-bold text-slate-800">在线用户</h1>
        <p class="text-slate-600 mt-2">实时查看当前在线用户和会话信息</p>
    </div>
    <div class="flex gap-3">
        <button onclick="refreshUsers()" class="px-6 py-3 bg-gradient-to-r from-blue-500 to-indigo-600 text-white font-semibold rounded-lg hover:shadow-lg transition">
            <i class="fas fa-sync-alt mr-2"></i>刷新
        </button>
        <button onclick="kickAllIdle()" class="px-6 py-3 bg-gradient-to-r from-red-500 to-pink-600 text-white font-semibold rounded-lg hover:shadow-lg transition">
            <i class="fas fa-user-times mr-2"></i>踢出空闲用户
        </button>
    </div>
</div>

<!-- Stats Cards -->
<div class="grid grid-cols-1 md:grid-cols-5 gap-6 mb-6">
    <div class="bg-white rounded-xl shadow-lg p-6 border-l-4 border-blue-500">
        <p class="text-sm text-slate-600 mb-2">在线用户</p>
        <p class="text-3xl font-bold text-slate-800" id="totalOnline">0</p>
    </div>
    <div class="bg-white rounded-xl shadow-lg p-6 border-l-4 border-green-500">
        <p class="text-sm text-slate-600 mb-2">活跃用户</p>
        <p class="text-3xl font-bold text-green-600" id="activeUsers">0</p>
    </div>
    <div class="bg-white rounded-xl shadow-lg p-6 border-l-4 border-yellow-500">
        <p class="text-sm text-slate-600 mb-2">空闲用户</p>
        <p class="text-3xl font-bold text-yellow-600" id="idleUsers">0</p>
    </div>
    <div class="bg-white rounded-xl shadow-lg p-6 border-l-4 border-purple-500">
        <p class="text-sm text-slate-600 mb-2">管理员</p>
        <p class="text-3xl font-bold text-purple-600" id="adminUsers">0</p>
    </div>
    <div class="bg-white rounded-xl shadow-lg p-6 border-l-4 border-orange-500">
        <p class="text-sm text-slate-600 mb-2">今日峰值</p>
        <p class="text-3xl font-bold text-orange-600" id="todayPeak">0</p>
    </div>
</div>

<!-- Filter Bar -->
<div class="bg-white rounded-xl shadow-lg p-6 mb-6">
    <div class="grid grid-cols-1 md:grid-cols-5 gap-4">
        <div>
            <label class="block text-sm font-semibold text-slate-700 mb-2">搜索</label>
            <input type="text" id="searchKeyword" placeholder="用户名或IP地址" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
        </div>
        <div>
            <label class="block text-sm font-semibold text-slate-700 mb-2">用户类型</label>
            <select id="filterUserType" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                <option value="">全部</option>
                <option value="admin">管理员</option>
                <option value="agent">代理</option>
                <option value="user">普通用户</option>
            </select>
        </div>
        <div>
            <label class="block text-sm font-semibold text-slate-700 mb-2">状态</label>
            <select id="filterStatus" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                <option value="">全部</option>
                <option value="active">活跃</option>
                <option value="idle">空闲</option>
            </select>
        </div>
        <div>
            <label class="block text-sm font-semibold text-slate-700 mb-2">排序</label>
            <select id="filterSort" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                <option value="login_time_desc">登录时间从新到旧</option>
                <option value="login_time_asc">登录时间从旧到新</option>
                <option value="last_active_desc">最后活跃从新到旧</option>
            </select>
        </div>
        <div class="flex items-end">
            <button onclick="searchUsers()" class="w-full px-6 py-2 bg-gradient-to-r from-blue-500 to-indigo-600 text-white font-semibold rounded-lg hover:shadow-lg transition">
                <i class="fas fa-search mr-2"></i>搜索
            </button>
        </div>
    </div>
</div>

<!-- Online Users Table -->
<div class="bg-white rounded-xl shadow-lg overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full">
            <thead class="bg-gradient-to-r from-blue-500 to-indigo-600 text-white">
                <tr>
                    <th class="px-6 py-4 text-left text-sm font-semibold">用户信息</th>
                    <th class="px-6 py-4 text-left text-sm font-semibold">用户类型</th>
                    <th class="px-6 py-4 text-left text-sm font-semibold">IP地址</th>
                    <th class="px-6 py-4 text-left text-sm font-semibold">设备信息</th>
                    <th class="px-6 py-4 text-left text-sm font-semibold">登录时间</th>
                    <th class="px-6 py-4 text-left text-sm font-semibold">最后活跃</th>
                    <th class="px-6 py-4 text-left text-sm font-semibold">在线时长</th>
                    <th class="px-6 py-4 text-center text-sm font-semibold">状态</th>
                    <th class="px-6 py-4 text-center text-sm font-semibold">操作</th>
                </tr>
            </thead>
            <tbody id="usersTable">
                <tr>
                    <td colspan="9" class="px-6 py-12 text-center">
                        <i class="fas fa-spinner fa-spin text-3xl text-slate-500 mb-3"></i>
                        <p class="text-slate-600">加载中...</p>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</div>

<!-- Detail Modal -->
<div id="detailModal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl max-w-4xl w-full max-h-[90vh] overflow-y-auto">
        <div class="sticky top-0 bg-gradient-to-r from-purple-500 to-pink-600 px-6 py-4 flex items-center justify-between">
            <h3 class="text-xl font-bold text-white">会话详情</h3>
            <button onclick="closeDetailModal()" class="text-white hover:text-slate-200">
                <i class="fas fa-times text-xl"></i>
            </button>
        </div>
        <div id="detailContent" class="p-6"></div>
    </div>
</div>

<script>
let autoRefreshInterval = null;

document.addEventListener('DOMContentLoaded', function() {
    loadStats();
    loadUsers();

    // Auto refresh every 10 seconds
    autoRefreshInterval = setInterval(() => {
        loadStats();
        loadUsers();
    }, 10000);
});

function loadStats() {
    fetch('{{ route("admin_api_online_users_stats") }}', {
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(res => res.json())
    .then(data => {
        if (data.success || data.code === 200) {
            document.getElementById('totalOnline').textContent = data.total || 0;
            document.getElementById('activeUsers').textContent = data.active || 0;
            document.getElementById('idleUsers').textContent = data.idle || 0;
            document.getElementById('adminUsers').textContent = data.admin || 0;
            document.getElementById('todayPeak').textContent = data.todayPeak || 0;
        }
    })
    .catch(err => console.error('Load stats error:', err));
}

function loadUsers() {
    const keyword = document.getElementById('searchKeyword').value;
    const userType = document.getElementById('filterUserType').value;
    const status = document.getElementById('filterStatus').value;
    const sort = document.getElementById('filterSort').value;

    const params = new URLSearchParams({
        keyword: keyword,
        user_type: userType,
        status: status,
        sort: sort
    });

    fetch(`{{ route('admin_api_online_users_list') }}?${params}`, {
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(res => res.json())
    .then(data => {
        if (data.success || data.code === 200) {
            renderUsers(data.users || []);
        } else {
            showError(data.message || '加载失败');
        }
    })
    .catch(err => {
        console.error('Load error:', err);
        showError('网络错误，请稍后重试');
    });
}

function renderUsers(users) {
    const table = document.getElementById('usersTable');

    if (users.length === 0) {
        table.innerHTML = `
            <tr>
                <td colspan="9" class="px-6 py-12 text-center">
                    <i class="fas fa-inbox text-5xl text-slate-300 mb-4"></i>
                    <p class="text-slate-600">当前无在线用户</p>
                </td>
            </tr>
        `;
        return;
    }

    table.innerHTML = users.map((u, index) => `
        <tr class="border-b border-slate-200 hover:bg-slate-50 transition ${index % 2 === 0 ? 'bg-white' : 'bg-slate-50'}">
            <td class="px-6 py-4">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-full bg-gradient-to-br from-blue-500 to-indigo-600 flex items-center justify-center text-white font-bold">
                        ${(u.username || 'U').charAt(0).toUpperCase()}
                    </div>
                    <div>
                        <p class="text-sm font-semibold text-slate-800">${u.username || 'N/A'}</p>
                        <p class="text-xs text-slate-500">${u.email || '-'}</p>
                    </div>
                </div>
            </td>
            <td class="px-6 py-4">
                ${getUserTypeBadge(u.user_type)}
            </td>
            <td class="px-6 py-4">
                <div>
                    <p class="text-sm font-mono text-slate-800">${u.ip_address || '-'}</p>
                    <p class="text-xs text-slate-500">${u.location || '未知位置'}</p>
                </div>
            </td>
            <td class="px-6 py-4">
                <div>
                    <p class="text-sm text-slate-800">${u.device || '-'}</p>
                    <p class="text-xs text-slate-500">${u.browser || '-'}</p>
                </div>
            </td>
            <td class="px-6 py-4">
                <span class="text-sm text-slate-600">${u.login_time || '-'}</span>
            </td>
            <td class="px-6 py-4">
                <span class="text-sm text-slate-600">${u.last_active || '-'}</span>
            </td>
            <td class="px-6 py-4">
                <span class="text-sm font-semibold text-slate-800">${formatDuration(u.online_duration || 0)}</span>
            </td>
            <td class="px-6 py-4 text-center">
                ${getStatusBadge(u.status)}
            </td>
            <td class="px-6 py-4">
                <div class="flex items-center justify-center gap-2">
                    <button onclick="viewDetail('${u.session_id}')" class="px-3 py-1 bg-blue-100 text-blue-700 text-xs font-semibold rounded hover:bg-blue-200 transition">
                        <i class="fas fa-eye"></i>
                    </button>
                    <button onclick="kickUser('${u.session_id}', '${u.username}')" class="px-3 py-1 bg-red-100 text-red-700 text-xs font-semibold rounded hover:bg-red-200 transition">
                        <i class="fas fa-sign-out-alt"></i>
                    </button>
                </div>
            </td>
        </tr>
    `).join('');
}

function getUserTypeBadge(type) {
    const badges = {
        'admin': '<span class="px-2 py-1 bg-purple-100 text-purple-700 text-xs font-semibold rounded-full"><i class="fas fa-shield-alt mr-1"></i>管理员</span>',
        'agent': '<span class="px-2 py-1 bg-blue-100 text-blue-700 text-xs font-semibold rounded-full"><i class="fas fa-user-tie mr-1"></i>代理</span>',
        'user': '<span class="px-2 py-1 bg-green-100 text-green-700 text-xs font-semibold rounded-full"><i class="fas fa-user mr-1"></i>用户</span>'
    };
    return badges[type] || '<span class="px-2 py-1 bg-slate-100 text-slate-700 text-xs font-semibold rounded-full">未知</span>';
}

function getStatusBadge(status) {
    return status === 'active'
        ? '<span class="px-3 py-1 bg-green-100 text-green-700 text-xs font-semibold rounded-full"><i class="fas fa-circle text-green-500 mr-1"></i>活跃</span>'
        : '<span class="px-3 py-1 bg-yellow-100 text-yellow-700 text-xs font-semibold rounded-full"><i class="fas fa-circle text-yellow-500 mr-1"></i>空闲</span>';
}

function formatDuration(seconds) {
    const hours = Math.floor(seconds / 3600);
    const minutes = Math.floor((seconds % 3600) / 60);

    if (hours > 0) {
        return `${hours}小时${minutes}分钟`;
    } else if (minutes > 0) {
        return `${minutes}分钟`;
    } else {
        return `${seconds}秒`;
    }
}

function viewDetail(sessionId) {
    fetch(`{{ route('admin_api_online_users_detail', ['id' => '__ID__']) }}`.replace('__ID__', sessionId), {
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(res => res.json())
    .then(data => {
        if (data.success && data.session) {
            const s = data.session;
            const activities = data.activities || [];

            document.getElementById('detailContent').innerHTML = `
                <div class="mb-6">
                    <h4 class="text-lg font-bold text-slate-800 mb-4">会话信息</h4>
                    <div class="grid grid-cols-2 gap-4">
                        <div class="bg-slate-50 rounded-lg p-4">
                            <p class="text-xs text-slate-600 mb-1">会话ID</p>
                            <p class="text-sm font-mono font-semibold text-slate-800">${s.session_id}</p>
                        </div>
                        <div class="bg-slate-50 rounded-lg p-4">
                            <p class="text-xs text-slate-600 mb-1">用户名</p>
                            <p class="text-sm font-semibold text-slate-800">${s.username}</p>
                        </div>
                        <div class="bg-slate-50 rounded-lg p-4">
                            <p class="text-xs text-slate-600 mb-1">IP地址</p>
                            <p class="text-sm font-mono font-semibold text-slate-800">${s.ip_address}</p>
                        </div>
                        <div class="bg-slate-50 rounded-lg p-4">
                            <p class="text-xs text-slate-600 mb-1">位置</p>
                            <p class="text-sm font-semibold text-slate-800">${s.location || '未知'}</p>
                        </div>
                        <div class="bg-slate-50 rounded-lg p-4">
                            <p class="text-xs text-slate-600 mb-1">设备</p>
                            <p class="text-sm font-semibold text-slate-800">${s.device || '-'}</p>
                        </div>
                        <div class="bg-slate-50 rounded-lg p-4">
                            <p class="text-xs text-slate-600 mb-1">浏览器</p>
                            <p class="text-sm font-semibold text-slate-800">${s.browser || '-'}</p>
                        </div>
                        <div class="bg-slate-50 rounded-lg p-4">
                            <p class="text-xs text-slate-600 mb-1">登录时间</p>
                            <p class="text-sm font-semibold text-slate-800">${s.login_time}</p>
                        </div>
                        <div class="bg-slate-50 rounded-lg p-4">
                            <p class="text-xs text-slate-600 mb-1">在线时长</p>
                            <p class="text-sm font-semibold text-slate-800">${formatDuration(s.online_duration || 0)}</p>
                        </div>
                    </div>
                </div>

                <div>
                    <h4 class="text-lg font-bold text-slate-800 mb-4">活动记录 (${activities.length})</h4>
                    ${activities.length === 0 ? `
                        <div class="text-center py-8 bg-slate-50 rounded-lg">
                            <i class="fas fa-inbox text-3xl text-slate-300 mb-2"></i>
                            <p class="text-slate-600 text-sm">暂无活动记录</p>
                        </div>
                    ` : `
                        <div class="space-y-2 max-h-96 overflow-y-auto">
                            ${activities.map(a => `
                                <div class="flex items-start gap-3 p-3 bg-slate-50 rounded-lg hover:bg-slate-100 transition">
                                    <div class="w-8 h-8 rounded-full bg-blue-100 flex items-center justify-center flex-shrink-0">
                                        <i class="fas fa-${getActivityIcon(a.type)} text-blue-600 text-xs"></i>
                                    </div>
                                    <div class="flex-1">
                                        <p class="text-sm font-semibold text-slate-800">${a.description}</p>
                                        <p class="text-xs text-slate-500">${a.url || ''}</p>
                                    </div>
                                    <span class="text-xs text-slate-500 whitespace-nowrap">${a.time}</span>
                                </div>
                            `).join('')}
                        </div>
                    `}
                </div>
            `;
            document.getElementById('detailModal').classList.remove('hidden');
        }
    })
    .catch(err => console.error('Load detail error:', err));
}

function getActivityIcon(type) {
    const icons = {
        'login': 'sign-in-alt',
        'page': 'file',
        'action': 'mouse-pointer',
        'api': 'exchange-alt'
    };
    return icons[type] || 'circle';
}

function closeDetailModal() {
    document.getElementById('detailModal').classList.add('hidden');
}

function kickUser(sessionId, username) {
    if (!confirm(`确定要踢出用户 "${username}" 吗？`)) {
        return;
    }

    fetch(`{{ route('admin_api_online_users_kick', ['id' => '__ID__']) }}`.replace('__ID__', sessionId), {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
            'X-Requested-With': 'XMLHttpRequest'
        }
    })
    .then(res => res.json())
    .then(data => {
        if (data.success || data.code === 200) {
            alert('用户已被踢出');
            loadStats();
            loadUsers();
        } else {
            alert(data.message || '操作失败');
        }
    })
    .catch(err => {
        console.error('Kick error:', err);
        alert('网络错误，请稍后重试');
    });
}

function kickAllIdle() {
    if (!confirm('确定要踢出所有空闲用户吗？')) {
        return;
    }

    fetch('{{ route("admin_api_online_users_kick_idle") }}', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
            'X-Requested-With': 'XMLHttpRequest'
        }
    })
    .then(res => res.json())
    .then(data => {
        if (data.success || data.code === 200) {
            alert(`已踢出 ${data.count || 0} 个空闲用户`);
            loadStats();
            loadUsers();
        } else {
            alert(data.message || '操作失败');
        }
    })
    .catch(err => {
        console.error('Kick idle error:', err);
        alert('网络错误，请稍后重试');
    });
}

function refreshUsers() {
    loadStats();
    loadUsers();
}

function searchUsers() {
    loadUsers();
}

function showError(message) {
    document.getElementById('usersTable').innerHTML = `
        <tr>
            <td colspan="9" class="px-6 py-12 text-center">
                <i class="fas fa-exclamation-circle text-5xl text-red-500 mb-4"></i>
                <p class="text-red-600">${message}</p>
            </td>
        </tr>
    `;
}
</script>
@endsection
