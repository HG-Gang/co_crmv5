@extends('admin-tailwind.layouts.app')

@section('title', '用户列表 - 管理后台')

@section('content')
<!-- Page Header -->
<div class="flex items-center justify-between mb-8">
    <div>
        <h1 class="text-3xl font-bold text-slate-800">用户列表</h1>
        <p class="text-slate-600 mt-2">管理和查看所有用户信息</p>
    </div>
    <button onclick="showAddModal()" class="px-6 py-2 bg-gradient-to-r from-blue-500 to-indigo-600 text-white font-semibold rounded-lg hover:shadow-lg transition">
        <i class="fas fa-plus mr-2"></i>添加用户
    </button>
</div>

<!-- Stats Cards -->
<div class="grid grid-cols-1 md:grid-cols-5 gap-6 mb-6">
    <div class="bg-white rounded-xl shadow-lg p-6">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-slate-600 text-sm font-semibold">总用户数</p>
                <h3 class="text-2xl font-bold text-slate-800 mt-2" id="totalUsers">0</h3>
            </div>
            <div class="w-12 h-12 bg-gradient-to-br from-blue-500 to-indigo-600 rounded-full flex items-center justify-center text-white">
                <i class="fas fa-users"></i>
            </div>
        </div>
    </div>

    <div class="bg-white rounded-xl shadow-lg p-6">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-slate-600 text-sm font-semibold">今日新增</p>
                <h3 class="text-2xl font-bold text-slate-800 mt-2" id="todayUsers">0</h3>
            </div>
            <div class="w-12 h-12 bg-gradient-to-br from-green-500 to-emerald-600 rounded-full flex items-center justify-center text-white">
                <i class="fas fa-user-plus"></i>
            </div>
        </div>
    </div>

    <div class="bg-white rounded-xl shadow-lg p-6">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-slate-600 text-sm font-semibold">活跃用户</p>
                <h3 class="text-2xl font-bold text-slate-800 mt-2" id="activeUsers">0</h3>
            </div>
            <div class="w-12 h-12 bg-gradient-to-br from-purple-500 to-pink-600 rounded-full flex items-center justify-center text-white">
                <i class="fas fa-user-check"></i>
            </div>
        </div>
    </div>

    <div class="bg-white rounded-xl shadow-lg p-6">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-slate-600 text-sm font-semibold">冻结用户</p>
                <h3 class="text-2xl font-bold text-slate-800 mt-2" id="frozenUsers">0</h3>
            </div>
            <div class="w-12 h-12 bg-gradient-to-br from-orange-500 to-red-600 rounded-full flex items-center justify-center text-white">
                <i class="fas fa-user-lock"></i>
            </div>
        </div>
    </div>

    <div class="bg-white rounded-xl shadow-lg p-6">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-slate-600 text-sm font-semibold">代理用户</p>
                <h3 class="text-2xl font-bold text-slate-800 mt-2" id="agentUsers">0</h3>
            </div>
            <div class="w-12 h-12 bg-gradient-to-br from-yellow-500 to-orange-600 rounded-full flex items-center justify-center text-white">
                <i class="fas fa-user-tie"></i>
            </div>
        </div>
    </div>
</div>

<!-- Filter Bar -->
<div class="bg-white rounded-xl shadow-lg p-6 mb-6">
    <div class="grid grid-cols-1 md:grid-cols-6 gap-4">
        <div>
            <input type="text" id="searchKeyword" placeholder="用户名/邮箱/手机" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
        </div>
        <div>
            <select id="filterStatus" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                <option value="">全部状态</option>
                <option value="active">正常</option>
                <option value="frozen">冻结</option>
                <option value="deleted">已删除</option>
            </select>
        </div>
        <div>
            <select id="filterUserType" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                <option value="">全部类型</option>
                <option value="user">普通用户</option>
                <option value="agent">代理</option>
                <option value="big_agent">大代理</option>
            </select>
        </div>
        <div>
            <input type="date" id="filterStartDate" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
        </div>
        <div>
            <input type="date" id="filterEndDate" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
        </div>
        <div class="flex gap-2">
            <button onclick="searchUsers()" class="flex-1 px-4 py-2 bg-gradient-to-r from-blue-500 to-indigo-600 text-white font-semibold rounded-lg hover:shadow-lg transition">
                <i class="fas fa-search mr-2"></i>搜索
            </button>
            <button onclick="resetFilters()" class="px-4 py-2 bg-slate-200 text-slate-700 font-semibold rounded-lg hover:bg-slate-300 transition">
                <i class="fas fa-redo"></i>
            </button>
        </div>
    </div>
</div>

<!-- Users Table -->
<div class="bg-white rounded-xl shadow-lg overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full">
            <thead class="bg-gradient-to-r from-slate-50 to-slate-100">
                <tr>
                    <th class="px-6 py-4 text-left text-xs font-semibold text-slate-600 uppercase">ID</th>
                    <th class="px-6 py-4 text-left text-xs font-semibold text-slate-600 uppercase">用户名</th>
                    <th class="px-6 py-4 text-left text-xs font-semibold text-slate-600 uppercase">邮箱</th>
                    <th class="px-6 py-4 text-left text-xs font-semibold text-slate-600 uppercase">手机号</th>
                    <th class="px-6 py-4 text-left text-xs font-semibold text-slate-600 uppercase">类型</th>
                    <th class="px-6 py-4 text-left text-xs font-semibold text-slate-600 uppercase">账户余额</th>
                    <th class="px-6 py-4 text-left text-xs font-semibold text-slate-600 uppercase">状态</th>
                    <th class="px-6 py-4 text-left text-xs font-semibold text-slate-600 uppercase">注册时间</th>
                    <th class="px-6 py-4 text-left text-xs font-semibold text-slate-600 uppercase">操作</th>
                </tr>
            </thead>
            <tbody id="usersTable">
                <tr>
                    <td colspan="9" class="px-6 py-12 text-center text-slate-400">
                        <i class="fas fa-spinner fa-spin mr-2"></i>加载中...
                    </td>
                </tr>
            </tbody>
        </table>
    </div>

    <!-- Pagination -->
    <div class="bg-slate-50 px-6 py-4 flex items-center justify-between border-t border-slate-200">
        <div class="text-sm text-slate-600">
            显示第 <span id="pageStart">0</span> 到 <span id="pageEnd">0</span> 条，共 <span id="pageTotal">0</span> 条
        </div>
        <div id="pagination" class="flex gap-2"></div>
    </div>
</div>

<!-- Add/Edit Modal -->
<div id="addModal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-xl shadow-2xl max-w-2xl w-full max-h-[90vh] overflow-y-auto">
        <div class="sticky top-0 bg-white border-b border-slate-200 px-6 py-4 flex items-center justify-between">
            <h3 class="text-xl font-bold text-slate-800" id="modalTitle">添加用户</h3>
            <button onclick="closeAddModal()" class="text-slate-400 hover:text-slate-600">
                <i class="fas fa-times text-xl"></i>
            </button>
        </div>
        <div class="p-6">
            <form id="userForm" class="space-y-4">
                <input type="hidden" id="userId">
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-semibold text-slate-700 mb-2">用户名 <span class="text-red-500">*</span></label>
                        <input type="text" id="username" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-slate-700 mb-2">邮箱 <span class="text-red-500">*</span></label>
                        <input type="email" id="email" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-semibold text-slate-700 mb-2">手机号</label>
                        <input type="tel" id="phone" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-slate-700 mb-2">用户类型</label>
                        <select id="userType" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                            <option value="user">普通用户</option>
                            <option value="agent">代理</option>
                            <option value="big_agent">大代理</option>
                        </select>
                    </div>
                </div>
                <div class="grid grid-cols-2 gap-4" id="passwordFields">
                    <div>
                        <label class="block text-sm font-semibold text-slate-700 mb-2">密码 <span class="text-red-500">*</span></label>
                        <input type="password" id="password" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-slate-700 mb-2">确认密码 <span class="text-red-500">*</span></label>
                        <input type="password" id="confirmPassword" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>
                </div>
                <div>
                    <label class="block text-sm font-semibold text-slate-700 mb-2">真实姓名</label>
                    <input type="text" id="realName" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>
                <div>
                    <label class="block text-sm font-semibold text-slate-700 mb-2">备注</label>
                    <textarea id="remark" rows="3" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"></textarea>
                </div>
            </form>
        </div>
        <div class="sticky bottom-0 bg-slate-50 px-6 py-4 flex justify-end gap-3 border-t border-slate-200">
            <button onclick="closeAddModal()" class="px-6 py-2 bg-slate-200 text-slate-700 font-semibold rounded-lg hover:bg-slate-300 transition">
                取消
            </button>
            <button onclick="saveUser()" class="px-6 py-2 bg-gradient-to-r from-blue-500 to-indigo-600 text-white font-semibold rounded-lg hover:shadow-lg transition">
                <i class="fas fa-save mr-2"></i>保存
            </button>
        </div>
    </div>
</div>

<script>
let currentPage = 1;
let pageSize = 20;

document.addEventListener('DOMContentLoaded', function() {
    loadStats();
    loadUsers();
});

function loadStats() {
    fetch('{{ route("admin_api_users_stats") }}', {
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(res => res.json())
    .then(data => {
        if (data.success && data.stats) {
            document.getElementById('totalUsers').textContent = data.stats.total || 0;
            document.getElementById('todayUsers').textContent = data.stats.today || 0;
            document.getElementById('activeUsers').textContent = data.stats.active || 0;
            document.getElementById('frozenUsers').textContent = data.stats.frozen || 0;
            document.getElementById('agentUsers').textContent = data.stats.agents || 0;
        }
    })
    .catch(err => console.error('Load stats error:', err));
}

function loadUsers(page = 1) {
    currentPage = page;
    const keyword = document.getElementById('searchKeyword').value.trim();
    const status = document.getElementById('filterStatus').value;
    const userType = document.getElementById('filterUserType').value;
    const startDate = document.getElementById('filterStartDate').value;
    const endDate = document.getElementById('filterEndDate').value;

    const params = new URLSearchParams({
        page: currentPage,
        page_size: pageSize,
        keyword: keyword,
        status: status,
        user_type: userType,
        start_date: startDate,
        end_date: endDate
    });

    fetch(`{{ route("admin_api_users_list") }}?${params}`, {
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(res => res.json())
    .then(data => {
        if (data.success && data.users) {
            renderUsers(data.users);
            renderPagination(data.pagination);
        }
    })
    .catch(err => {
        console.error('Load users error:', err);
        document.getElementById('usersTable').innerHTML = `
            <tr><td colspan="9" class="px-6 py-12 text-center text-slate-400">
                <i class="fas fa-exclamation-circle mr-2"></i>加载失败
            </td></tr>
        `;
    });
}

function renderUsers(users) {
    if (users.length === 0) {
        document.getElementById('usersTable').innerHTML = `
            <tr><td colspan="9" class="px-6 py-12 text-center text-slate-400">
                <i class="fas fa-inbox mr-2"></i>暂无数据
            </td></tr>
        `;
        return;
    }

    const html = users.map(u => `
        <tr class="border-t border-slate-100 hover:bg-slate-50">
            <td class="px-6 py-4 text-sm text-slate-600">${u.id}</td>
            <td class="px-6 py-4">
                <div class="flex items-center gap-2">
                    <div class="w-8 h-8 rounded-full bg-gradient-to-br from-blue-500 to-indigo-600 flex items-center justify-center text-white text-xs font-bold">
                        ${u.username.charAt(0).toUpperCase()}
                    </div>
                    <span class="text-sm font-semibold text-slate-800">${u.username}</span>
                </div>
            </td>
            <td class="px-6 py-4 text-sm text-slate-600">${u.email || '-'}</td>
            <td class="px-6 py-4 text-sm text-slate-600">${u.phone || '-'}</td>
            <td class="px-6 py-4">${getUserTypeBadge(u.user_type)}</td>
            <td class="px-6 py-4 text-sm font-semibold text-slate-800">${formatMoney(u.balance || 0)}</td>
            <td class="px-6 py-4">${getStatusBadge(u.status)}</td>
            <td class="px-6 py-4 text-sm text-slate-600">${u.created_at || '-'}</td>
            <td class="px-6 py-4">
                <div class="flex items-center gap-2">
                    <button onclick="viewUser(${u.id})" class="p-2 text-blue-600 hover:bg-blue-50 rounded-lg transition" title="查看">
                        <i class="fas fa-eye"></i>
                    </button>
                    <button onclick="editUser(${u.id})" class="p-2 text-green-600 hover:bg-green-50 rounded-lg transition" title="编辑">
                        <i class="fas fa-edit"></i>
                    </button>
                    ${u.status === 'active' ? `
                        <button onclick="freezeUser(${u.id})" class="p-2 text-orange-600 hover:bg-orange-50 rounded-lg transition" title="冻结">
                            <i class="fas fa-lock"></i>
                        </button>
                    ` : ''}
                    ${u.status === 'frozen' ? `
                        <button onclick="unfreezeUser(${u.id})" class="p-2 text-green-600 hover:bg-green-50 rounded-lg transition" title="解冻">
                            <i class="fas fa-unlock"></i>
                        </button>
                    ` : ''}
                    <button onclick="deleteUser(${u.id})" class="p-2 text-red-600 hover:bg-red-50 rounded-lg transition" title="删除">
                        <i class="fas fa-trash"></i>
                    </button>
                </div>
            </td>
        </tr>
    `).join('');

    document.getElementById('usersTable').innerHTML = html;
}

function renderPagination(pagination) {
    document.getElementById('pageStart').textContent = pagination.from || 0;
    document.getElementById('pageEnd').textContent = pagination.to || 0;
    document.getElementById('pageTotal').textContent = pagination.total || 0;

    const totalPages = pagination.last_page || 1;
    let html = '';

    if (currentPage > 1) {
        html += `<button onclick="loadUsers(${currentPage - 1})" class="px-3 py-1 border border-slate-300 rounded hover:bg-slate-50"><i class="fas fa-chevron-left"></i></button>`;
    }

    for (let i = 1; i <= totalPages; i++) {
        if (i === 1 || i === totalPages || (i >= currentPage - 2 && i <= currentPage + 2)) {
            html += `<button onclick="loadUsers(${i})" class="px-3 py-1 border ${i === currentPage ? 'bg-blue-500 text-white border-blue-500' : 'border-slate-300 hover:bg-slate-50'} rounded">${i}</button>`;
        } else if (i === currentPage - 3 || i === currentPage + 3) {
            html += `<span class="px-2">...</span>`;
        }
    }

    if (currentPage < totalPages) {
        html += `<button onclick="loadUsers(${currentPage + 1})" class="px-3 py-1 border border-slate-300 rounded hover:bg-slate-50"><i class="fas fa-chevron-right"></i></button>`;
    }

    document.getElementById('pagination').innerHTML = html;
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
        'active': '<span class="px-2 py-1 bg-green-100 text-green-700 text-xs font-semibold rounded-full"><i class="fas fa-check-circle mr-1"></i>正常</span>',
        'frozen': '<span class="px-2 py-1 bg-orange-100 text-orange-700 text-xs font-semibold rounded-full"><i class="fas fa-lock mr-1"></i>冻结</span>',
        'deleted': '<span class="px-2 py-1 bg-red-100 text-red-700 text-xs font-semibold rounded-full"><i class="fas fa-times-circle mr-1"></i>已删除</span>'
    };
    return badges[status] || '<span class="px-2 py-1 bg-slate-100 text-slate-700 text-xs font-semibold rounded-full">未知</span>';
}

function formatMoney(amount) {
    return '$' + new Intl.NumberFormat('en-US', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
    }).format(amount);
}

function searchUsers() {
    loadUsers(1);
}

function resetFilters() {
    document.getElementById('searchKeyword').value = '';
    document.getElementById('filterStatus').value = '';
    document.getElementById('filterUserType').value = '';
    document.getElementById('filterStartDate').value = '';
    document.getElementById('filterEndDate').value = '';
    loadUsers(1);
}

function showAddModal() {
    document.getElementById('modalTitle').textContent = '添加用户';
    document.getElementById('userForm').reset();
    document.getElementById('userId').value = '';
    document.getElementById('passwordFields').classList.remove('hidden');
    document.getElementById('addModal').classList.remove('hidden');
}

function closeAddModal() {
    document.getElementById('addModal').classList.add('hidden');
}

function saveUser() {
    const userId = document.getElementById('userId').value;
    const username = document.getElementById('username').value.trim();
    const email = document.getElementById('email').value.trim();
    const phone = document.getElementById('phone').value.trim();
    const userType = document.getElementById('userType').value;
    const password = document.getElementById('password').value;
    const confirmPassword = document.getElementById('confirmPassword').value;
    const realName = document.getElementById('realName').value.trim();
    const remark = document.getElementById('remark').value.trim();

    if (!username) {
        alert('请输入用户名');
        return;
    }

    if (!email) {
        alert('请输入邮箱');
        return;
    }

    if (!userId) {
        if (!password) {
            alert('请输入密码');
            return;
        }
        if (password !== confirmPassword) {
            alert('两次输入的密码不一致');
            return;
        }
    }

    const url = userId ? '{{ route("admin_api_users_update") }}' : '{{ route("admin_api_users_create") }}';
    const data = {
        id: userId,
        username: username,
        email: email,
        phone: phone,
        user_type: userType,
        real_name: realName,
        remark: remark
    };

    if (!userId) {
        data.password = password;
    }

    fetch(url, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: JSON.stringify(data)
    })
    .then(res => res.json())
    .then(data => {
        if (data.success || data.code === 200) {
            alert(userId ? '保存成功' : '添加成功');
            closeAddModal();
            loadStats();
            loadUsers(currentPage);
        } else {
            alert(data.message || '操作失败');
        }
    })
    .catch(err => {
        console.error('Save user error:', err);
        alert('网络错误，请稍后重试');
    });
}

function viewUser(id) {
    window.location.href = '{{ route("admin_tailwind_page_users_detail", ["id" => "__ID__"]) }}'.replace('__ID__', id);
}

function editUser(id) {
    fetch(`{{ route("admin_api_users_detail") }}?id=${id}`, {
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(res => res.json())
    .then(data => {
        if (data.success && data.user) {
            const u = data.user;
            document.getElementById('modalTitle').textContent = '编辑用户';
            document.getElementById('userId').value = u.id;
            document.getElementById('username').value = u.username;
            document.getElementById('email').value = u.email || '';
            document.getElementById('phone').value = u.phone || '';
            document.getElementById('userType').value = u.user_type || 'user';
            document.getElementById('realName').value = u.real_name || '';
            document.getElementById('remark').value = u.remark || '';
            document.getElementById('passwordFields').classList.add('hidden');
            document.getElementById('addModal').classList.remove('hidden');
        }
    })
    .catch(err => console.error('Load user error:', err));
}

function freezeUser(id) {
    if (!confirm('确定要冻结该用户吗？')) return;

    fetch('{{ route("admin_api_users_freeze") }}', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: JSON.stringify({ id: id })
    })
    .then(res => res.json())
    .then(data => {
        if (data.success || data.code === 200) {
            alert('冻结成功');
            loadUsers(currentPage);
        } else {
            alert(data.message || '操作失败');
        }
    })
    .catch(err => {
        console.error('Freeze user error:', err);
        alert('网络错误，请稍后重试');
    });
}

function unfreezeUser(id) {
    if (!confirm('确定要解冻该用户吗？')) return;

    fetch('{{ route("admin_api_users_unfreeze") }}', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: JSON.stringify({ id: id })
    })
    .then(res => res.json())
    .then(data => {
        if (data.success || data.code === 200) {
            alert('解冻成功');
            loadUsers(currentPage);
        } else {
            alert(data.message || '操作失败');
        }
    })
    .catch(err => {
        console.error('Unfreeze user error:', err);
        alert('网络错误，请稍后重试');
    });
}

function deleteUser(id) {
    if (!confirm('确定要删除该用户吗？此操作不可恢复。')) return;

    fetch('{{ route("admin_api_users_delete") }}', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: JSON.stringify({ id: id })
    })
    .then(res => res.json())
    .then(data => {
        if (data.success || data.code === 200) {
            alert('删除成功');
            loadStats();
            loadUsers(currentPage);
        } else {
            alert(data.message || '操作失败');
        }
    })
    .catch(err => {
        console.error('Delete user error:', err);
        alert('网络错误，请稍后重试');
    });
}
</script>
@endsection
