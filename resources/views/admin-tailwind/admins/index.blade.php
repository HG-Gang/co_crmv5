@extends('admin-tailwind.layouts.app')

@section('title', '管理员列表 - 管理后台')

@section('content')
<!-- Page Header -->
<div class="flex items-center justify-between mb-8">
    <div>
        <h1 class="text-3xl font-bold text-slate-800">管理员列表</h1>
        <p class="text-slate-600 mt-2">管理系统管理员账户和权限</p>
    </div>
    <button onclick="openAddModal()" class="px-6 py-3 bg-gradient-to-r from-blue-500 to-indigo-600 text-white font-semibold rounded-lg hover:shadow-lg transition">
        <i class="fas fa-plus mr-2"></i>添加管理员
    </button>
</div>

<!-- Stats Cards -->
<div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-6">
    <div class="bg-white rounded-xl shadow-lg p-6">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-slate-600 text-sm font-semibold">总管理员</p>
                <h3 class="text-2xl font-bold text-slate-800 mt-2" id="totalAdmins">0</h3>
            </div>
            <div class="w-12 h-12 bg-gradient-to-br from-blue-500 to-indigo-600 rounded-full flex items-center justify-center text-white">
                <i class="fas fa-user-shield"></i>
            </div>
        </div>
    </div>

    <div class="bg-white rounded-xl shadow-lg p-6">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-slate-600 text-sm font-semibold">今日新增</p>
                <h3 class="text-2xl font-bold text-slate-800 mt-2" id="todayAdmins">0</h3>
            </div>
            <div class="w-12 h-12 bg-gradient-to-br from-green-500 to-emerald-600 rounded-full flex items-center justify-center text-white">
                <i class="fas fa-user-plus"></i>
            </div>
        </div>
    </div>

    <div class="bg-white rounded-xl shadow-lg p-6">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-slate-600 text-sm font-semibold">在线管理员</p>
                <h3 class="text-2xl font-bold text-slate-800 mt-2" id="onlineAdmins">0</h3>
            </div>
            <div class="w-12 h-12 bg-gradient-to-br from-purple-500 to-pink-600 rounded-full flex items-center justify-center text-white">
                <i class="fas fa-signal"></i>
            </div>
        </div>
    </div>

    <div class="bg-white rounded-xl shadow-lg p-6">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-slate-600 text-sm font-semibold">禁用账户</p>
                <h3 class="text-2xl font-bold text-slate-800 mt-2" id="disabledAdmins">0</h3>
            </div>
            <div class="w-12 h-12 bg-gradient-to-br from-orange-500 to-red-600 rounded-full flex items-center justify-center text-white">
                <i class="fas fa-ban"></i>
            </div>
        </div>
    </div>
</div>

<!-- Filters -->
<div class="bg-white rounded-xl shadow-lg p-6 mb-6">
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
        <div>
            <label class="block text-sm font-semibold text-slate-700 mb-2">关键词</label>
            <input type="text" id="filterKeyword" placeholder="用户名、邮箱、手机号" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
        </div>
        <div>
            <label class="block text-sm font-semibold text-slate-700 mb-2">角色</label>
            <select id="filterRole" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                <option value="">全部角色</option>
                <option value="1">超级管理员</option>
                <option value="2">系统管理员</option>
                <option value="3">运营管理员</option>
                <option value="4">客服管理员</option>
            </select>
        </div>
        <div>
            <label class="block text-sm font-semibold text-slate-700 mb-2">状态</label>
            <select id="filterStatus" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                <option value="">全部状态</option>
                <option value="active">正常</option>
                <option value="disabled">禁用</option>
            </select>
        </div>
        <div class="flex items-end gap-2">
            <button onclick="searchAdmins()" class="flex-1 px-4 py-2 bg-gradient-to-r from-blue-500 to-indigo-600 text-white font-semibold rounded-lg hover:shadow-lg transition">
                <i class="fas fa-search mr-2"></i>搜索
            </button>
            <button onclick="resetFilters()" class="px-4 py-2 bg-slate-200 text-slate-700 font-semibold rounded-lg hover:bg-slate-300 transition">
                <i class="fas fa-redo"></i>
            </button>
        </div>
    </div>
</div>

<!-- Admins Table -->
<div class="bg-white rounded-xl shadow-lg overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full">
            <thead class="bg-slate-50 border-b border-slate-200">
                <tr>
                    <th class="px-6 py-4 text-left text-xs font-semibold text-slate-600 uppercase">管理员</th>
                    <th class="px-6 py-4 text-left text-xs font-semibold text-slate-600 uppercase">角色</th>
                    <th class="px-6 py-4 text-left text-xs font-semibold text-slate-600 uppercase">联系方式</th>
                    <th class="px-6 py-4 text-left text-xs font-semibold text-slate-600 uppercase">状态</th>
                    <th class="px-6 py-4 text-left text-xs font-semibold text-slate-600 uppercase">最后登录</th>
                    <th class="px-6 py-4 text-left text-xs font-semibold text-slate-600 uppercase">创建时间</th>
                    <th class="px-6 py-4 text-right text-xs font-semibold text-slate-600 uppercase">操作</th>
                </tr>
            </thead>
            <tbody id="adminsTableBody" class="divide-y divide-slate-100">
                <tr>
                    <td colspan="7" class="px-6 py-12 text-center text-slate-400">
                        <i class="fas fa-spinner fa-spin mr-2"></i>加载中...
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</div>

<!-- Pagination -->
<div class="mt-6 flex items-center justify-between">
    <div class="text-sm text-slate-600">
        显示第 <span id="pageStart">0</span> 至 <span id="pageEnd">0</span> 条，共 <span id="pageTotal">0</span> 条
    </div>
    <div id="pagination" class="flex gap-2"></div>
</div>

<!-- Add/Edit Modal -->
<div id="adminModal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-xl shadow-2xl max-w-2xl w-full max-h-[90vh] overflow-y-auto">
        <div class="sticky top-0 bg-white border-b border-slate-200 px-6 py-4 flex items-center justify-between">
            <h3 class="text-xl font-bold text-slate-800">
                <i class="fas fa-user-shield text-blue-600 mr-2"></i><span id="modalTitle">添加管理员</span>
            </h3>
            <button onclick="closeAdminModal()" class="text-slate-400 hover:text-slate-600">
                <i class="fas fa-times text-xl"></i>
            </button>
        </div>
        <div class="p-6">
            <input type="hidden" id="adminId">

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                <div>
                    <label class="block text-sm font-semibold text-slate-700 mb-2">用户名 <span class="text-red-500">*</span></label>
                    <input type="text" id="adminUsername" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" placeholder="请输入用户名">
                </div>
                <div>
                    <label class="block text-sm font-semibold text-slate-700 mb-2">邮箱 <span class="text-red-500">*</span></label>
                    <input type="email" id="adminEmail" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" placeholder="请输入邮箱">
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                <div>
                    <label class="block text-sm font-semibold text-slate-700 mb-2">手机号</label>
                    <input type="text" id="adminPhone" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" placeholder="请输入手机号">
                </div>
                <div>
                    <label class="block text-sm font-semibold text-slate-700 mb-2">真实姓名</label>
                    <input type="text" id="adminRealName" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" placeholder="请输入真实姓名">
                </div>
            </div>

            <div class="mb-4">
                <label class="block text-sm font-semibold text-slate-700 mb-2">角色 <span class="text-red-500">*</span></label>
                <select id="adminRoles" multiple class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" size="5">
                    <option value="1">超级管理员</option>
                    <option value="2">系统管理员</option>
                    <option value="3">运营管理员</option>
                    <option value="4">客服管理员</option>
                </select>
                <p class="text-xs text-slate-500 mt-1">按住 Ctrl/Cmd 可多选</p>
            </div>

            <div id="passwordFields" class="mb-4">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-semibold text-slate-700 mb-2">密码 <span class="text-red-500" id="passwordRequired">*</span></label>
                        <input type="password" id="adminPassword" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" placeholder="请输入密码">
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-slate-700 mb-2">确认密码 <span class="text-red-500" id="confirmRequired">*</span></label>
                        <input type="password" id="adminPasswordConfirm" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" placeholder="请再次输入密码">
                    </div>
                </div>
                <p class="text-xs text-slate-500 mt-1" id="passwordHint">密码至少8位，包含大小写字母、数字</p>
            </div>

            <div class="mb-4">
                <label class="block text-sm font-semibold text-slate-700 mb-2">备注</label>
                <textarea id="adminRemark" rows="3" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" placeholder="备注信息"></textarea>
            </div>

            <div class="flex items-center gap-4 mb-4">
                <label class="flex items-center gap-2 cursor-pointer">
                    <input type="checkbox" id="adminStatus" checked class="w-4 h-4 text-blue-600 rounded">
                    <span class="text-sm font-semibold text-slate-700">启用账户</span>
                </label>
                <label class="flex items-center gap-2 cursor-pointer">
                    <input type="checkbox" id="adminNotify" class="w-4 h-4 text-blue-600 rounded">
                    <span class="text-sm font-semibold text-slate-700">发送通知邮件</span>
                </label>
            </div>

            <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
                <div class="flex items-start gap-2">
                    <i class="fas fa-info-circle text-blue-600 mt-1"></i>
                    <div class="text-sm text-blue-800">
                        <p class="font-semibold mb-1">提示</p>
                        <ul class="list-disc list-inside space-y-1 text-xs">
                            <li>管理员账户拥有系统管理权限，请谨慎操作</li>
                            <li>建议为不同职能配置不同角色，遵循最小权限原则</li>
                            <li>定期检查管理员登录日志，及时发现异常</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
        <div class="sticky bottom-0 bg-slate-50 px-6 py-4 flex justify-end gap-3 border-t border-slate-200">
            <button onclick="closeAdminModal()" class="px-6 py-2 bg-slate-200 text-slate-700 font-semibold rounded-lg hover:bg-slate-300 transition">
                取消
            </button>
            <button onclick="saveAdmin()" class="px-6 py-2 bg-gradient-to-r from-blue-500 to-indigo-600 text-white font-semibold rounded-lg hover:shadow-lg transition">
                <i class="fas fa-save mr-2"></i>保存
            </button>
        </div>
    </div>
</div>

<script>
let currentPage = 1;
let totalPages = 1;

document.addEventListener('DOMContentLoaded', function() {
    loadStats();
    loadAdmins();
});

function loadStats() {
    fetch('{{ route("admin_api_admins_stats") }}', {
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(res => res.json())
    .then(data => {
        if (data.success && data.stats) {
            document.getElementById('totalAdmins').textContent = data.stats.total || 0;
            document.getElementById('todayAdmins').textContent = data.stats.today || 0;
            document.getElementById('onlineAdmins').textContent = data.stats.online || 0;
            document.getElementById('disabledAdmins').textContent = data.stats.disabled || 0;
        }
    })
    .catch(err => console.error('Load stats error:', err));
}

function loadAdmins(page = 1) {
    const keyword = document.getElementById('filterKeyword').value;
    const role = document.getElementById('filterRole').value;
    const status = document.getElementById('filterStatus').value;

    const params = new URLSearchParams({
        page: page,
        keyword: keyword,
        role: role,
        status: status
    });

    fetch(`{{ route("admin_api_admins_list") }}?${params}`, {
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(res => res.json())
    .then(data => {
        if (data.success && data.admins) {
            renderAdmins(data.admins);
            renderPagination(data.pagination);
        }
    })
    .catch(err => {
        console.error('Load admins error:', err);
        document.getElementById('adminsTableBody').innerHTML = `
            <tr><td colspan="7" class="px-6 py-12 text-center text-slate-400">
                <i class="fas fa-exclamation-circle mr-2"></i>加载失败
            </td></tr>
        `;
    });
}

function renderAdmins(admins) {
    if (admins.length === 0) {
        document.getElementById('adminsTableBody').innerHTML = `
            <tr><td colspan="7" class="px-6 py-12 text-center text-slate-400">
                <i class="fas fa-inbox mr-2"></i>暂无数据
            </td></tr>
        `;
        return;
    }

    const html = admins.map(a => `
        <tr class="hover:bg-slate-50">
            <td class="px-6 py-4">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 bg-gradient-to-br from-blue-500 to-indigo-600 rounded-full flex items-center justify-center text-white font-semibold">
                        ${a.username ? a.username.charAt(0).toUpperCase() : 'A'}
                    </div>
                    <div>
                        <p class="font-semibold text-slate-800">${a.username}</p>
                        <p class="text-xs text-slate-500">${a.real_name || '-'}</p>
                    </div>
                </div>
            </td>
            <td class="px-6 py-4">
                ${a.roles && a.roles.length > 0 ? a.roles.map(r => `
                    <span class="inline-block px-2 py-1 bg-purple-100 text-purple-700 text-xs font-semibold rounded-full mr-1 mb-1">
                        ${r.name}
                    </span>
                `).join('') : '<span class="text-slate-400 text-sm">-</span>'}
            </td>
            <td class="px-6 py-4">
                <p class="text-sm text-slate-800"><i class="fas fa-envelope text-slate-400 mr-1"></i>${a.email || '-'}</p>
                <p class="text-sm text-slate-500"><i class="fas fa-phone text-slate-400 mr-1"></i>${a.phone || '-'}</p>
            </td>
            <td class="px-6 py-4">
                ${getStatusBadge(a.status)}
            </td>
            <td class="px-6 py-4">
                <p class="text-sm text-slate-800">${a.last_login_at || '从未登录'}</p>
                ${a.last_login_ip ? `<p class="text-xs text-slate-500">${a.last_login_ip}</p>` : ''}
            </td>
            <td class="px-6 py-4">
                <p class="text-sm text-slate-600">${a.created_at || '-'}</p>
            </td>
            <td class="px-6 py-4">
                <div class="flex items-center justify-end gap-2">
                    <button onclick="editAdmin(${a.id})" class="px-3 py-1 text-blue-600 hover:bg-blue-50 rounded-lg transition" title="编辑">
                        <i class="fas fa-edit"></i>
                    </button>
                    <button onclick="resetPassword(${a.id})" class="px-3 py-1 text-orange-600 hover:bg-orange-50 rounded-lg transition" title="重置密码">
                        <i class="fas fa-key"></i>
                    </button>
                    ${a.status === 'active' ? `
                        <button onclick="disableAdmin(${a.id})" class="px-3 py-1 text-yellow-600 hover:bg-yellow-50 rounded-lg transition" title="禁用">
                            <i class="fas fa-ban"></i>
                        </button>
                    ` : `
                        <button onclick="enableAdmin(${a.id})" class="px-3 py-1 text-green-600 hover:bg-green-50 rounded-lg transition" title="启用">
                            <i class="fas fa-check-circle"></i>
                        </button>
                    `}
                    <button onclick="deleteAdmin(${a.id})" class="px-3 py-1 text-red-600 hover:bg-red-50 rounded-lg transition" title="删除">
                        <i class="fas fa-trash"></i>
                    </button>
                </div>
            </td>
        </tr>
    `).join('');

    document.getElementById('adminsTableBody').innerHTML = html;
}

function getStatusBadge(status) {
    const badges = {
        'active': '<span class="px-2 py-1 bg-green-100 text-green-700 text-xs font-semibold rounded-full"><i class="fas fa-check-circle mr-1"></i>正常</span>',
        'disabled': '<span class="px-2 py-1 bg-red-100 text-red-700 text-xs font-semibold rounded-full"><i class="fas fa-ban mr-1"></i>禁用</span>'
    };
    return badges[status] || '<span class="px-2 py-1 bg-slate-100 text-slate-700 text-xs font-semibold rounded-full">未知</span>';
}

function renderPagination(pagination) {
    if (!pagination) return;

    currentPage = pagination.current_page;
    totalPages = pagination.last_page;

    document.getElementById('pageStart').textContent = pagination.from || 0;
    document.getElementById('pageEnd').textContent = pagination.to || 0;
    document.getElementById('pageTotal').textContent = pagination.total || 0;

    let html = '';
    if (currentPage > 1) {
        html += `<button onclick="loadAdmins(${currentPage - 1})" class="px-3 py-1 border border-slate-300 rounded-lg hover:bg-slate-50">上一页</button>`;
    }

    for (let i = Math.max(1, currentPage - 2); i <= Math.min(totalPages, currentPage + 2); i++) {
        if (i === currentPage) {
            html += `<button class="px-3 py-1 bg-blue-600 text-white rounded-lg">${i}</button>`;
        } else {
            html += `<button onclick="loadAdmins(${i})" class="px-3 py-1 border border-slate-300 rounded-lg hover:bg-slate-50">${i}</button>`;
        }
    }

    if (currentPage < totalPages) {
        html += `<button onclick="loadAdmins(${currentPage + 1})" class="px-3 py-1 border border-slate-300 rounded-lg hover:bg-slate-50">下一页</button>`;
    }

    document.getElementById('pagination').innerHTML = html;
}

function searchAdmins() {
    loadAdmins(1);
}

function resetFilters() {
    document.getElementById('filterKeyword').value = '';
    document.getElementById('filterRole').value = '';
    document.getElementById('filterStatus').value = '';
    loadAdmins(1);
}

function openAddModal() {
    document.getElementById('modalTitle').textContent = '添加管理员';
    document.getElementById('adminId').value = '';
    document.getElementById('adminUsername').value = '';
    document.getElementById('adminEmail').value = '';
    document.getElementById('adminPhone').value = '';
    document.getElementById('adminRealName').value = '';
    document.getElementById('adminPassword').value = '';
    document.getElementById('adminPasswordConfirm').value = '';
    document.getElementById('adminRemark').value = '';
    document.getElementById('adminStatus').checked = true;
    document.getElementById('adminNotify').checked = false;

    document.querySelectorAll('#adminRoles option').forEach(opt => opt.selected = false);

    document.getElementById('passwordFields').classList.remove('hidden');
    document.getElementById('passwordRequired').classList.remove('hidden');
    document.getElementById('confirmRequired').classList.remove('hidden');
    document.getElementById('passwordHint').textContent = '密码至少8位，包含大小写字母、数字';

    document.getElementById('adminModal').classList.remove('hidden');
}

function editAdmin(id) {
    fetch(`{{ route("admin_api_admins_detail") }}?id=${id}`, {
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(res => res.json())
    .then(data => {
        if (data.success && data.admin) {
            const a = data.admin;
            document.getElementById('modalTitle').textContent = '编辑管理员';
            document.getElementById('adminId').value = a.id;
            document.getElementById('adminUsername').value = a.username || '';
            document.getElementById('adminEmail').value = a.email || '';
            document.getElementById('adminPhone').value = a.phone || '';
            document.getElementById('adminRealName').value = a.real_name || '';
            document.getElementById('adminRemark').value = a.remark || '';
            document.getElementById('adminStatus').checked = a.status === 'active';

            document.querySelectorAll('#adminRoles option').forEach(opt => {
                opt.selected = a.role_ids && a.role_ids.includes(parseInt(opt.value));
            });

            document.getElementById('passwordFields').classList.remove('hidden');
            document.getElementById('passwordRequired').classList.add('hidden');
            document.getElementById('confirmRequired').classList.add('hidden');
            document.getElementById('passwordHint').textContent = '留空表示不修改密码';

            document.getElementById('adminModal').classList.remove('hidden');
        }
    })
    .catch(err => console.error('Load admin detail error:', err));
}

function closeAdminModal() {
    document.getElementById('adminModal').classList.add('hidden');
}

function saveAdmin() {
    const id = document.getElementById('adminId').value;
    const username = document.getElementById('adminUsername').value.trim();
    const email = document.getElementById('adminEmail').value.trim();
    const phone = document.getElementById('adminPhone').value.trim();
    const realName = document.getElementById('adminRealName').value.trim();
    const password = document.getElementById('adminPassword').value;
    const passwordConfirm = document.getElementById('adminPasswordConfirm').value;
    const remark = document.getElementById('adminRemark').value.trim();
    const status = document.getElementById('adminStatus').checked ? 'active' : 'disabled';
    const notify = document.getElementById('adminNotify').checked;
    const roleIds = Array.from(document.getElementById('adminRoles').selectedOptions).map(o => parseInt(o.value));

    if (!username) {
        alert('请输入用户名');
        return;
    }

    if (!email) {
        alert('请输入邮箱');
        return;
    }

    if (roleIds.length === 0) {
        alert('请选择至少一个角色');
        return;
    }

    if (!id) {
        if (!password) {
            alert('请输入密码');
            return;
        }
        if (password !== passwordConfirm) {
            alert('两次密码输入不一致');
            return;
        }
    } else {
        if (password && password !== passwordConfirm) {
            alert('两次密码输入不一致');
            return;
        }
    }

    const data = {
        id: id || undefined,
        username: username,
        email: email,
        phone: phone,
        real_name: realName,
        password: password || undefined,
        remark: remark,
        status: status,
        notify: notify,
        role_ids: roleIds
    };

    fetch('{{ route("admin_api_admins_save") }}', {
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
            alert(id ? '更新成功' : '添加成功');
            closeAdminModal();
            loadStats();
            loadAdmins(currentPage);
        } else {
            alert(data.message || '保存失败');
        }
    })
    .catch(err => {
        console.error('Save admin error:', err);
        alert('网络错误，请稍后重试');
    });
}

function resetPassword(id) {
    if (!confirm('确定要重置该管理员的密码吗？新密码将发送到其邮箱。')) return;

    fetch('{{ route("admin_api_admins_reset_password") }}', {
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
            alert('密码重置成功');
        } else {
            alert(data.message || '重置失败');
        }
    })
    .catch(err => {
        console.error('Reset password error:', err);
        alert('网络错误，请稍后重试');
    });
}

function disableAdmin(id) {
    if (!confirm('确定要禁用该管理员吗？')) return;

    fetch('{{ route("admin_api_admins_disable") }}', {
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
            alert('禁用成功');
            loadStats();
            loadAdmins(currentPage);
        } else {
            alert(data.message || '禁用失败');
        }
    })
    .catch(err => {
        console.error('Disable admin error:', err);
        alert('网络错误，请稍后重试');
    });
}

function enableAdmin(id) {
    if (!confirm('确定要启用该管理员吗？')) return;

    fetch('{{ route("admin_api_admins_enable") }}', {
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
            alert('启用成功');
            loadStats();
            loadAdmins(currentPage);
        } else {
            alert(data.message || '启用失败');
        }
    })
    .catch(err => {
        console.error('Enable admin error:', err);
        alert('网络错误，请稍后重试');
    });
}

function deleteAdmin(id) {
    if (!confirm('确定要删除该管理员吗？此操作不可恢复！')) return;

    fetch('{{ route("admin_api_admins_delete") }}', {
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
            loadAdmins(currentPage);
        } else {
            alert(data.message || '删除失败');
        }
    })
    .catch(err => {
        console.error('Delete admin error:', err);
        alert('网络错误，请稍后重试');
    });
}
</script>
@endsection
