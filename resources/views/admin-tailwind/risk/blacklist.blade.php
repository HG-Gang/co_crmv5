@extends('admin-tailwind.layouts.app')

@section('title', '黑名单管理 - 管理后台')

@section('content')
<!-- Page Header -->
<div class="flex items-center justify-between mb-8">
    <div>
        <h1 class="text-3xl font-bold text-slate-800">黑名单管理</h1>
        <p class="text-slate-600 mt-2">管理被限制访问的用户、IP和设备</p>
    </div>
    <div class="flex gap-3">
        <button onclick="refreshData()" class="px-6 py-3 bg-gradient-to-r from-blue-500 to-indigo-600 text-white font-semibold rounded-lg hover:shadow-lg transition">
            <i class="fas fa-sync-alt mr-2"></i>刷新
        </button>
        <button onclick="openAddModal()" class="px-6 py-3 bg-gradient-to-r from-red-500 to-pink-600 text-white font-semibold rounded-lg hover:shadow-lg transition">
            <i class="fas fa-plus mr-2"></i>添加黑名单
        </button>
    </div>
</div>

<!-- Stats Cards -->
<div class="grid grid-cols-1 md:grid-cols-5 gap-6 mb-6">
    <div class="bg-white rounded-xl shadow-lg p-6 border-l-4 border-red-500">
        <p class="text-sm text-slate-600 mb-2">用户黑名单</p>
        <p class="text-3xl font-bold text-red-600" id="userCount">0</p>
    </div>
    <div class="bg-white rounded-xl shadow-lg p-6 border-l-4 border-orange-500">
        <p class="text-sm text-slate-600 mb-2">IP黑名单</p>
        <p class="text-3xl font-bold text-orange-600" id="ipCount">0</p>
    </div>
    <div class="bg-white rounded-xl shadow-lg p-6 border-l-4 border-purple-500">
        <p class="text-sm text-slate-600 mb-2">设备黑名单</p>
        <p class="text-3xl font-bold text-purple-600" id="deviceCount">0</p>
    </div>
    <div class="bg-white rounded-xl shadow-lg p-6 border-l-4 border-yellow-500">
        <p class="text-sm text-slate-600 mb-2">今日新增</p>
        <p class="text-3xl font-bold text-yellow-600" id="todayAdded">0</p>
    </div>
    <div class="bg-white rounded-xl shadow-lg p-6 border-l-4 border-blue-500">
        <p class="text-sm text-slate-600 mb-2">今日拦截</p>
        <p class="text-3xl font-bold text-blue-600" id="todayBlocked">0</p>
    </div>
</div>

<!-- Filter Bar -->
<div class="bg-white rounded-xl shadow-lg p-6 mb-6">
    <div class="grid grid-cols-1 md:grid-cols-5 gap-4">
        <div>
            <label class="block text-sm font-semibold text-slate-700 mb-2">搜索</label>
            <input type="text" id="searchKeyword" placeholder="用户名/IP/设备ID" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
        </div>
        <div>
            <label class="block text-sm font-semibold text-slate-700 mb-2">黑名单类型</label>
            <select id="filterType" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                <option value="">全部</option>
                <option value="user">用户</option>
                <option value="ip">IP地址</option>
                <option value="device">设备</option>
            </select>
        </div>
        <div>
            <label class="block text-sm font-semibold text-slate-700 mb-2">原因分类</label>
            <select id="filterReason" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                <option value="">全部</option>
                <option value="fraud">欺诈行为</option>
                <option value="abuse">滥用系统</option>
                <option value="risk">高风险用户</option>
                <option value="cheat">作弊行为</option>
                <option value="other">其他</option>
            </select>
        </div>
        <div>
            <label class="block text-sm font-semibold text-slate-700 mb-2">状态</label>
            <select id="filterStatus" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                <option value="">全部</option>
                <option value="active">启用中</option>
                <option value="expired">已过期</option>
            </select>
        </div>
        <div class="flex items-end">
            <button onclick="searchRecords()" class="w-full px-6 py-2 bg-gradient-to-r from-blue-500 to-indigo-600 text-white font-semibold rounded-lg hover:shadow-lg transition">
                <i class="fas fa-search mr-2"></i>搜索
            </button>
        </div>
    </div>
</div>

<!-- Blacklist Table -->
<div class="bg-white rounded-xl shadow-lg overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full">
            <thead class="bg-gradient-to-r from-red-500 to-pink-600 text-white">
                <tr>
                    <th class="px-6 py-4 text-left text-sm font-semibold">黑名单类型</th>
                    <th class="px-6 py-4 text-left text-sm font-semibold">目标信息</th>
                    <th class="px-6 py-4 text-left text-sm font-semibold">原因</th>
                    <th class="px-6 py-4 text-left text-sm font-semibold">拦截次数</th>
                    <th class="px-6 py-4 text-left text-sm font-semibold">添加时间</th>
                    <th class="px-6 py-4 text-left text-sm font-semibold">过期时间</th>
                    <th class="px-6 py-4 text-center text-sm font-semibold">状态</th>
                    <th class="px-6 py-4 text-center text-sm font-semibold">操作</th>
                </tr>
            </thead>
            <tbody id="recordsTable">
                <tr>
                    <td colspan="8" class="px-6 py-12 text-center">
                        <i class="fas fa-spinner fa-spin text-3xl text-slate-500 mb-3"></i>
                        <p class="text-slate-600">加载中...</p>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</div>

<!-- Add/Edit Modal -->
<div id="addModal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl max-w-2xl w-full">
        <div class="bg-gradient-to-r from-red-500 to-pink-600 px-6 py-4 flex items-center justify-between rounded-t-2xl">
            <h3 class="text-xl font-bold text-white">添加黑名单</h3>
            <button onclick="closeAddModal()" class="text-white hover:text-slate-200">
                <i class="fas fa-times text-xl"></i>
            </button>
        </div>
        <div class="p-6">
            <form id="addForm" class="space-y-4">
                <div>
                    <label class="block text-sm font-semibold text-slate-700 mb-2">黑名单类型 <span class="text-red-500">*</span></label>
                    <select id="addType" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                        <option value="user">用户</option>
                        <option value="ip">IP地址</option>
                        <option value="device">设备</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-semibold text-slate-700 mb-2">目标值 <span class="text-red-500">*</span></label>
                    <input type="text" id="addTarget" placeholder="用户ID/IP地址/设备ID" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                    <p class="text-xs text-slate-500 mt-1">IP地址支持通配符，如：192.168.1.*</p>
                </div>
                <div>
                    <label class="block text-sm font-semibold text-slate-700 mb-2">原因分类 <span class="text-red-500">*</span></label>
                    <select id="addReason" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                        <option value="fraud">欺诈行为</option>
                        <option value="abuse">滥用系统</option>
                        <option value="risk">高风险用户</option>
                        <option value="cheat">作弊行为</option>
                        <option value="other">其他</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-semibold text-slate-700 mb-2">详细说明</label>
                    <textarea id="addRemark" rows="3" placeholder="请输入详细原因..." class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"></textarea>
                </div>
                <div>
                    <label class="block text-sm font-semibold text-slate-700 mb-2">过期时间</label>
                    <input type="datetime-local" id="addExpireTime" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                    <p class="text-xs text-slate-500 mt-1">留空表示永久</p>
                </div>
                <div class="flex justify-end gap-3 pt-4">
                    <button type="button" onclick="closeAddModal()" class="px-6 py-2 bg-slate-200 text-slate-700 font-semibold rounded-lg hover:bg-slate-300 transition">
                        取消
                    </button>
                    <button type="button" onclick="submitAdd()" class="px-6 py-2 bg-gradient-to-r from-red-500 to-pink-600 text-white font-semibold rounded-lg hover:shadow-lg transition">
                        添加
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Detail Modal -->
<div id="detailModal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl max-w-3xl w-full max-h-[90vh] overflow-y-auto">
        <div class="sticky top-0 bg-gradient-to-r from-red-500 to-pink-600 px-6 py-4 flex items-center justify-between">
            <h3 class="text-xl font-bold text-white">黑名单详情</h3>
            <button onclick="closeDetailModal()" class="text-white hover:text-slate-200">
                <i class="fas fa-times text-xl"></i>
            </button>
        </div>
        <div id="detailContent" class="p-6"></div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    loadStats();
    loadRecords();
});

function loadStats() {
    fetch('{{ route("admin_api_blacklist_stats") }}', {
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(res => res.json())
    .then(data => {
        if (data.success || data.code === 200) {
            document.getElementById('userCount').textContent = data.userCount || 0;
            document.getElementById('ipCount').textContent = data.ipCount || 0;
            document.getElementById('deviceCount').textContent = data.deviceCount || 0;
            document.getElementById('todayAdded').textContent = data.todayAdded || 0;
            document.getElementById('todayBlocked').textContent = data.todayBlocked || 0;
        }
    })
    .catch(err => console.error('Load stats error:', err));
}

function loadRecords() {
    const keyword = document.getElementById('searchKeyword').value;
    const type = document.getElementById('filterType').value;
    const reason = document.getElementById('filterReason').value;
    const status = document.getElementById('filterStatus').value;

    const params = new URLSearchParams({
        keyword: keyword,
        type: type,
        reason: reason,
        status: status
    });

    fetch(`{{ route('admin_api_blacklist_list') }}?${params}`, {
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(res => res.json())
    .then(data => {
        if (data.success || data.code === 200) {
            renderRecords(data.records || []);
        } else {
            showError(data.message || '加载失败');
        }
    })
    .catch(err => {
        console.error('Load error:', err);
        showError('网络错误，请稍后重试');
    });
}

function renderRecords(records) {
    const table = document.getElementById('recordsTable');

    if (records.length === 0) {
        table.innerHTML = `
            <tr>
                <td colspan="8" class="px-6 py-12 text-center">
                    <i class="fas fa-inbox text-5xl text-slate-300 mb-4"></i>
                    <p class="text-slate-600">暂无黑名单记录</p>
                </td>
            </tr>
        `;
        return;
    }

    table.innerHTML = records.map((r, index) => `
        <tr class="border-b border-slate-200 hover:bg-slate-50 transition ${index % 2 === 0 ? 'bg-white' : 'bg-slate-50'}">
            <td class="px-6 py-4">
                ${getTypeBadge(r.type)}
            </td>
            <td class="px-6 py-4">
                <div>
                    <p class="text-sm font-mono font-semibold text-slate-800">${r.target || '-'}</p>
                    ${r.type === 'user' && r.username ? `<p class="text-xs text-slate-500">${r.username}</p>` : ''}
                </div>
            </td>
            <td class="px-6 py-4">
                <div>
                    ${getReasonBadge(r.reason)}
                    ${r.remark ? `<p class="text-xs text-slate-500 mt-1">${r.remark}</p>` : ''}
                </div>
            </td>
            <td class="px-6 py-4">
                <span class="text-sm font-bold text-red-600">${r.blocked_count || 0}</span>
            </td>
            <td class="px-6 py-4">
                <span class="text-sm text-slate-600">${r.created_at || '-'}</span>
            </td>
            <td class="px-6 py-4">
                <span class="text-sm text-slate-600">${r.expire_time || '永久'}</span>
            </td>
            <td class="px-6 py-4 text-center">
                ${getStatusBadge(r.status)}
            </td>
            <td class="px-6 py-4">
                <div class="flex items-center justify-center gap-2">
                    <button onclick="viewDetail(${r.id})" class="px-3 py-1 bg-blue-100 text-blue-700 text-xs font-semibold rounded hover:bg-blue-200 transition">
                        <i class="fas fa-eye"></i>
                    </button>
                    <button onclick="removeBlacklist(${r.id}, '${r.target}')" class="px-3 py-1 bg-red-100 text-red-700 text-xs font-semibold rounded hover:bg-red-200 transition">
                        <i class="fas fa-trash"></i>
                    </button>
                </div>
            </td>
        </tr>
    `).join('');
}

function getTypeBadge(type) {
    const badges = {
        'user': '<span class="px-2 py-1 bg-purple-100 text-purple-700 text-xs font-semibold rounded-full"><i class="fas fa-user mr-1"></i>用户</span>',
        'ip': '<span class="px-2 py-1 bg-orange-100 text-orange-700 text-xs font-semibold rounded-full"><i class="fas fa-network-wired mr-1"></i>IP</span>',
        'device': '<span class="px-2 py-1 bg-blue-100 text-blue-700 text-xs font-semibold rounded-full"><i class="fas fa-mobile-alt mr-1"></i>设备</span>'
    };
    return badges[type] || '<span class="px-2 py-1 bg-slate-100 text-slate-700 text-xs font-semibold rounded-full">未知</span>';
}

function getReasonBadge(reason) {
    const badges = {
        'fraud': '<span class="px-2 py-1 bg-red-100 text-red-700 text-xs font-semibold rounded-full">欺诈行为</span>',
        'abuse': '<span class="px-2 py-1 bg-orange-100 text-orange-700 text-xs font-semibold rounded-full">滥用系统</span>',
        'risk': '<span class="px-2 py-1 bg-yellow-100 text-yellow-700 text-xs font-semibold rounded-full">高风险</span>',
        'cheat': '<span class="px-2 py-1 bg-pink-100 text-pink-700 text-xs font-semibold rounded-full">作弊行为</span>',
        'other': '<span class="px-2 py-1 bg-slate-100 text-slate-700 text-xs font-semibold rounded-full">其他</span>'
    };
    return badges[reason] || '<span class="px-2 py-1 bg-slate-100 text-slate-700 text-xs font-semibold rounded-full">未分类</span>';
}

function getStatusBadge(status) {
    return status === 'active'
        ? '<span class="px-3 py-1 bg-red-100 text-red-700 text-xs font-semibold rounded-full"><i class="fas fa-ban mr-1"></i>启用中</span>'
        : '<span class="px-3 py-1 bg-slate-100 text-slate-700 text-xs font-semibold rounded-full"><i class="fas fa-clock mr-1"></i>已过期</span>';
}

function openAddModal() {
    document.getElementById('addForm').reset();
    document.getElementById('addModal').classList.remove('hidden');
}

function closeAddModal() {
    document.getElementById('addModal').classList.add('hidden');
}

function submitAdd() {
    const type = document.getElementById('addType').value;
    const target = document.getElementById('addTarget').value.trim();
    const reason = document.getElementById('addReason').value;
    const remark = document.getElementById('addRemark').value.trim();
    const expireTime = document.getElementById('addExpireTime').value;

    if (!target) {
        alert('请输入目标值');
        return;
    }

    fetch('{{ route("admin_api_blacklist_add") }}', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: JSON.stringify({
            type: type,
            target: target,
            reason: reason,
            remark: remark,
            expire_time: expireTime
        })
    })
    .then(res => res.json())
    .then(data => {
        if (data.success || data.code === 200) {
            alert('添加成功');
            closeAddModal();
            loadStats();
            loadRecords();
        } else {
            alert(data.message || '添加失败');
        }
    })
    .catch(err => {
        console.error('Add error:', err);
        alert('网络错误，请稍后重试');
    });
}

function viewDetail(id) {
    fetch(`{{ route('admin_api_blacklist_detail', ['id' => '__ID__']) }}`.replace('__ID__', id), {
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(res => res.json())
    .then(data => {
        if (data.success && data.record) {
            const r = data.record;
            const logs = data.logs || [];

            document.getElementById('detailContent').innerHTML = `
                <div class="mb-6">
                    <h4 class="text-lg font-bold text-slate-800 mb-4">基本信息</h4>
                    <div class="grid grid-cols-2 gap-4">
                        <div class="bg-slate-50 rounded-lg p-4">
                            <p class="text-xs text-slate-600 mb-1">类型</p>
                            ${getTypeBadge(r.type)}
                        </div>
                        <div class="bg-slate-50 rounded-lg p-4">
                            <p class="text-xs text-slate-600 mb-1">目标</p>
                            <p class="text-sm font-mono font-semibold text-slate-800">${r.target}</p>
                        </div>
                        <div class="bg-slate-50 rounded-lg p-4">
                            <p class="text-xs text-slate-600 mb-1">原因</p>
                            ${getReasonBadge(r.reason)}
                        </div>
                        <div class="bg-slate-50 rounded-lg p-4">
                            <p class="text-xs text-slate-600 mb-1">拦截次数</p>
                            <p class="text-sm font-bold text-red-600">${r.blocked_count || 0}</p>
                        </div>
                        <div class="bg-slate-50 rounded-lg p-4">
                            <p class="text-xs text-slate-600 mb-1">添加时间</p>
                            <p class="text-sm font-semibold text-slate-800">${r.created_at}</p>
                        </div>
                        <div class="bg-slate-50 rounded-lg p-4">
                            <p class="text-xs text-slate-600 mb-1">过期时间</p>
                            <p class="text-sm font-semibold text-slate-800">${r.expire_time || '永久'}</p>
                        </div>
                        <div class="bg-slate-50 rounded-lg p-4">
                            <p class="text-xs text-slate-600 mb-1">添加人</p>
                            <p class="text-sm font-semibold text-slate-800">${r.operator || '-'}</p>
                        </div>
                        <div class="bg-slate-50 rounded-lg p-4">
                            <p class="text-xs text-slate-600 mb-1">状态</p>
                            ${getStatusBadge(r.status)}
                        </div>
                        ${r.remark ? `
                        <div class="bg-slate-50 rounded-lg p-4 col-span-2">
                            <p class="text-xs text-slate-600 mb-1">详细说明</p>
                            <p class="text-sm text-slate-800">${r.remark}</p>
                        </div>
                        ` : ''}
                    </div>
                </div>

                <div>
                    <h4 class="text-lg font-bold text-slate-800 mb-4">拦截日志 (${logs.length})</h4>
                    ${logs.length === 0 ? `
                        <div class="text-center py-8 bg-slate-50 rounded-lg">
                            <i class="fas fa-inbox text-3xl text-slate-300 mb-2"></i>
                            <p class="text-slate-600 text-sm">暂无拦截记录</p>
                        </div>
                    ` : `
                        <div class="space-y-2 max-h-96 overflow-y-auto">
                            ${logs.map(log => `
                                <div class="flex items-start gap-3 p-3 bg-slate-50 rounded-lg">
                                    <div class="w-8 h-8 rounded-full bg-red-100 flex items-center justify-center flex-shrink-0">
                                        <i class="fas fa-ban text-red-600 text-xs"></i>
                                    </div>
                                    <div class="flex-1">
                                        <p class="text-sm font-semibold text-slate-800">${log.action}</p>
                                        <p class="text-xs text-slate-500">IP: ${log.ip_address} | ${log.user_agent || ''}</p>
                                        <p class="text-xs text-slate-400 mt-1">${log.created_at}</p>
                                    </div>
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

function closeDetailModal() {
    document.getElementById('detailModal').classList.add('hidden');
}

function removeBlacklist(id, target) {
    if (!confirm(`确定要从黑名单中移除 "${target}" 吗？`)) {
        return;
    }

    fetch(`{{ route('admin_api_blacklist_remove', ['id' => '__ID__']) }}`.replace('__ID__', id), {
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
            alert('移除成功');
            loadStats();
            loadRecords();
        } else {
            alert(data.message || '操作失败');
        }
    })
    .catch(err => {
        console.error('Remove error:', err);
        alert('网络错误，请稍后重试');
    });
}

function refreshData() {
    loadStats();
    loadRecords();
}

function searchRecords() {
    loadRecords();
}

function showError(message) {
    document.getElementById('recordsTable').innerHTML = `
        <tr>
            <td colspan="8" class="px-6 py-12 text-center">
                <i class="fas fa-exclamation-circle text-5xl text-red-500 mb-4"></i>
                <p class="text-red-600">${message}</p>
            </td>
        </tr>
    `;
}
</script>
@endsection
