@extends('admin-tailwind.layouts.app')

@section('title', '权限管理 - 管理后台')

@section('content')
<!-- Page Header -->
<div class="flex items-center justify-between mb-8">
    <div>
        <h1 class="text-3xl font-bold text-slate-800">权限管理</h1>
        <p class="text-slate-600 mt-2">管理系统所有权限项</p>
    </div>
    <button onclick="showAddModal()" class="px-6 py-2 bg-gradient-to-r from-blue-500 to-indigo-600 text-white font-semibold rounded-lg hover:shadow-lg transition">
        <i class="fas fa-plus mr-2"></i>添加权限
    </button>
</div>

<!-- Stats Cards -->
<div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-6">
    <div class="bg-white rounded-xl shadow-lg p-6">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-slate-600 text-sm font-semibold">总权限数</p>
                <h3 class="text-2xl font-bold text-slate-800 mt-2" id="totalPermissions">0</h3>
            </div>
            <div class="w-12 h-12 bg-gradient-to-br from-blue-500 to-indigo-600 rounded-full flex items-center justify-center text-white">
                <i class="fas fa-key"></i>
            </div>
        </div>
    </div>

    <div class="bg-white rounded-xl shadow-lg p-6">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-slate-600 text-sm font-semibold">启用权限</p>
                <h3 class="text-2xl font-bold text-slate-800 mt-2" id="activePermissions">0</h3>
            </div>
            <div class="w-12 h-12 bg-gradient-to-br from-green-500 to-emerald-600 rounded-full flex items-center justify-center text-white">
                <i class="fas fa-check-circle"></i>
            </div>
        </div>
    </div>

    <div class="bg-white rounded-xl shadow-lg p-6">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-slate-600 text-sm font-semibold">权限模块</p>
                <h3 class="text-2xl font-bold text-slate-800 mt-2" id="totalModules">0</h3>
            </div>
            <div class="w-12 h-12 bg-gradient-to-br from-purple-500 to-pink-600 rounded-full flex items-center justify-center text-white">
                <i class="fas fa-folder-open"></i>
            </div>
        </div>
    </div>

    <div class="bg-white rounded-xl shadow-lg p-6">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-slate-600 text-sm font-semibold">今日新增</p>
                <h3 class="text-2xl font-bold text-slate-800 mt-2" id="todayPermissions">0</h3>
            </div>
            <div class="w-12 h-12 bg-gradient-to-br from-orange-500 to-red-600 rounded-full flex items-center justify-center text-white">
                <i class="fas fa-plus-circle"></i>
            </div>
        </div>
    </div>
</div>

<!-- Filter Bar -->
<div class="bg-white rounded-xl shadow-lg p-6 mb-6">
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
        <div>
            <input type="text" id="searchKeyword" placeholder="权限名称/标识" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
        </div>
        <div>
            <select id="filterModule" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                <option value="">全部模块</option>
            </select>
        </div>
        <div>
            <select id="filterStatus" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                <option value="">全部状态</option>
                <option value="1">启用</option>
                <option value="0">禁用</option>
            </select>
        </div>
        <div class="flex gap-2">
            <button onclick="searchPermissions()" class="flex-1 px-4 py-2 bg-gradient-to-r from-blue-500 to-indigo-600 text-white font-semibold rounded-lg hover:shadow-lg transition">
                <i class="fas fa-search mr-2"></i>搜索
            </button>
            <button onclick="resetFilters()" class="px-4 py-2 bg-slate-200 text-slate-700 font-semibold rounded-lg hover:bg-slate-300 transition">
                <i class="fas fa-redo"></i>
            </button>
        </div>
    </div>
</div>

<!-- Permissions List -->
<div class="bg-white rounded-xl shadow-lg overflow-hidden">
    <div id="permissionsList">
        <div class="flex items-center justify-center py-12 text-slate-400">
            <i class="fas fa-spinner fa-spin mr-2"></i>加载中...
        </div>
    </div>
</div>

<!-- Add/Edit Modal -->
<div id="addModal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-xl shadow-2xl max-w-2xl w-full max-h-[90vh] overflow-y-auto">
        <div class="sticky top-0 bg-white border-b border-slate-200 px-6 py-4 flex items-center justify-between">
            <h3 class="text-xl font-bold text-slate-800" id="modalTitle">添加权限</h3>
            <button onclick="closeAddModal()" class="text-slate-400 hover:text-slate-600">
                <i class="fas fa-times text-xl"></i>
            </button>
        </div>
        <div class="p-6">
            <form id="permissionForm" class="space-y-4">
                <input type="hidden" id="permissionId">
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-semibold text-slate-700 mb-2">权限名称 <span class="text-red-500">*</span></label>
                        <input type="text" id="permissionName" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" placeholder="例如: 查看用户">
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-slate-700 mb-2">权限标识 <span class="text-red-500">*</span></label>
                        <input type="text" id="permissionSlug" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" placeholder="例如: users.view">
                    </div>
                </div>
                <div>
                    <label class="block text-sm font-semibold text-slate-700 mb-2">所属模块 <span class="text-red-500">*</span></label>
                    <input type="text" id="permissionModule" list="modulesList" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" placeholder="例如: 用户管理">
                    <datalist id="modulesList"></datalist>
                </div>
                <div>
                    <label class="block text-sm font-semibold text-slate-700 mb-2">权限描述</label>
                    <textarea id="permissionDescription" rows="3" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" placeholder="请输入权限描述..."></textarea>
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-semibold text-slate-700 mb-2">HTTP方法</label>
                        <select id="permissionMethod" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                            <option value="">不限</option>
                            <option value="GET">GET</option>
                            <option value="POST">POST</option>
                            <option value="PUT">PUT</option>
                            <option value="DELETE">DELETE</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-slate-700 mb-2">排序</label>
                        <input type="number" id="permissionSort" value="0" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>
                </div>
                <div>
                    <label class="block text-sm font-semibold text-slate-700 mb-2">路由规则</label>
                    <input type="text" id="permissionRoute" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" placeholder="例如: /admin/users/*">
                    <p class="text-xs text-slate-500 mt-1">支持通配符 *，留空表示不限制路由</p>
                </div>
                <div>
                    <label class="flex items-center gap-2 cursor-pointer">
                        <input type="checkbox" id="permissionStatus" checked class="w-4 h-4 text-blue-600 border-slate-300 rounded focus:ring-2 focus:ring-blue-500">
                        <span class="text-sm font-semibold text-slate-700">启用该权限</span>
                    </label>
                </div>
            </form>
        </div>
        <div class="sticky bottom-0 bg-slate-50 px-6 py-4 flex justify-end gap-3 border-t border-slate-200">
            <button onclick="closeAddModal()" class="px-6 py-2 bg-slate-200 text-slate-700 font-semibold rounded-lg hover:bg-slate-300 transition">
                取消
            </button>
            <button onclick="savePermission()" class="px-6 py-2 bg-gradient-to-r from-blue-500 to-indigo-600 text-white font-semibold rounded-lg hover:shadow-lg transition">
                <i class="fas fa-save mr-2"></i>保存
            </button>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    loadStats();
    loadModules();
    loadPermissions();
});

function loadStats() {
    fetch('{{ route("admin_api_permissions_stats") }}', {
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(res => res.json())
    .then(data => {
        if (data.success && data.stats) {
            document.getElementById('totalPermissions').textContent = data.stats.total || 0;
            document.getElementById('activePermissions').textContent = data.stats.active || 0;
            document.getElementById('totalModules').textContent = data.stats.modules || 0;
            document.getElementById('todayPermissions').textContent = data.stats.today || 0;
        }
    })
    .catch(err => console.error('Load stats error:', err));
}

function loadModules() {
    fetch('{{ route("admin_api_permissions_modules") }}', {
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(res => res.json())
    .then(data => {
        if (data.success && data.modules) {
            const filterSelect = document.getElementById('filterModule');
            const datalist = document.getElementById('modulesList');

            data.modules.forEach(m => {
                const option1 = document.createElement('option');
                option1.value = m;
                option1.textContent = m;
                filterSelect.appendChild(option1);

                const option2 = document.createElement('option');
                option2.value = m;
                datalist.appendChild(option2);
            });
        }
    })
    .catch(err => console.error('Load modules error:', err));
}

function loadPermissions() {
    const keyword = document.getElementById('searchKeyword').value.trim();
    const module = document.getElementById('filterModule').value;
    const status = document.getElementById('filterStatus').value;

    const params = new URLSearchParams({
        keyword: keyword,
        module: module,
        status: status
    });

    fetch(`{{ route("admin_api_permissions_list") }}?${params}`, {
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(res => res.json())
    .then(data => {
        if (data.success && data.permissions) {
            renderPermissions(data.permissions);
        }
    })
    .catch(err => {
        console.error('Load permissions error:', err);
        document.getElementById('permissionsList').innerHTML = `
            <div class="flex items-center justify-center py-12 text-slate-400">
                <i class="fas fa-exclamation-circle mr-2"></i>加载失败
            </div>
        `;
    });
}

function renderPermissions(permissions) {
    if (permissions.length === 0) {
        document.getElementById('permissionsList').innerHTML = `
            <div class="flex items-center justify-center py-12 text-slate-400">
                <i class="fas fa-inbox mr-2"></i>暂无数据
            </div>
        `;
        return;
    }

    // Group by module
    const grouped = {};
    permissions.forEach(p => {
        const module = p.module || '其他';
        if (!grouped[module]) grouped[module] = [];
        grouped[module].push(p);
    });

    const html = Object.keys(grouped).map(module => `
        <div class="border-b border-slate-200 last:border-0">
            <div class="bg-gradient-to-r from-slate-50 to-slate-100 px-6 py-3">
                <h3 class="text-sm font-bold text-slate-800">
                    <i class="fas fa-folder-open text-blue-600 mr-2"></i>${module}
                    <span class="ml-2 px-2 py-0.5 bg-blue-100 text-blue-700 text-xs rounded-full">${grouped[module].length}</span>
                </h3>
            </div>
            <div class="divide-y divide-slate-100">
                ${grouped[module].map(p => `
                    <div class="px-6 py-4 hover:bg-slate-50">
                        <div class="flex items-center justify-between">
                            <div class="flex-1">
                                <div class="flex items-center gap-3">
                                    <h4 class="text-sm font-semibold text-slate-800">${p.name}</h4>
                                    ${getStatusBadge(p.status)}
                                    ${p.method ? `<span class="px-2 py-0.5 bg-purple-100 text-purple-700 text-xs font-semibold rounded">${p.method}</span>` : ''}
                                </div>
                                <p class="text-xs text-slate-500 mt-1">
                                    <i class="fas fa-code mr-1"></i>${p.slug}
                                    ${p.route ? `<span class="ml-3"><i class="fas fa-route mr-1"></i>${p.route}</span>` : ''}
                                </p>
                                ${p.description ? `<p class="text-xs text-slate-600 mt-1">${p.description}</p>` : ''}
                            </div>
                            <div class="flex items-center gap-2 ml-4">
                                <button onclick="editPermission(${p.id})" class="text-blue-600 hover:text-blue-700" title="编辑">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <button onclick="deletePermission(${p.id})" class="text-red-600 hover:text-red-700" title="删除">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                `).join('')}
            </div>
        </div>
    `).join('');

    document.getElementById('permissionsList').innerHTML = html;
}

function getStatusBadge(status) {
    return status === 1 || status === 'active'
        ? '<span class="px-2 py-0.5 bg-green-100 text-green-700 text-xs font-semibold rounded-full"><i class="fas fa-check-circle mr-1"></i>启用</span>'
        : '<span class="px-2 py-0.5 bg-slate-100 text-slate-700 text-xs font-semibold rounded-full"><i class="fas fa-ban mr-1"></i>禁用</span>';
}

function searchPermissions() {
    loadPermissions();
}

function resetFilters() {
    document.getElementById('searchKeyword').value = '';
    document.getElementById('filterModule').value = '';
    document.getElementById('filterStatus').value = '';
    loadPermissions();
}

function showAddModal() {
    document.getElementById('modalTitle').textContent = '添加权限';
    document.getElementById('permissionForm').reset();
    document.getElementById('permissionId').value = '';
    document.getElementById('permissionStatus').checked = true;
    document.getElementById('addModal').classList.remove('hidden');
}

function closeAddModal() {
    document.getElementById('addModal').classList.add('hidden');
}

function savePermission() {
    const permissionId = document.getElementById('permissionId').value;
    const name = document.getElementById('permissionName').value.trim();
    const slug = document.getElementById('permissionSlug').value.trim();
    const module = document.getElementById('permissionModule').value.trim();
    const description = document.getElementById('permissionDescription').value.trim();
    const method = document.getElementById('permissionMethod').value;
    const sort = document.getElementById('permissionSort').value;
    const route = document.getElementById('permissionRoute').value.trim();
    const status = document.getElementById('permissionStatus').checked ? 1 : 0;

    if (!name) {
        alert('请输入权限名称');
        return;
    }

    if (!slug) {
        alert('请输入权限标识');
        return;
    }

    if (!module) {
        alert('请输入所属模块');
        return;
    }

    const url = permissionId ? '{{ route("admin_api_permissions_update") }}' : '{{ route("admin_api_permissions_create") }}';

    fetch(url, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: JSON.stringify({
            id: permissionId,
            name: name,
            slug: slug,
            module: module,
            description: description,
            method: method,
            sort: sort,
            route: route,
            status: status
        })
    })
    .then(res => res.json())
    .then(data => {
        if (data.success || data.code === 200) {
            alert(permissionId ? '保存成功' : '添加成功');
            closeAddModal();
            loadStats();
            loadModules();
            loadPermissions();
        } else {
            alert(data.message || '操作失败');
        }
    })
    .catch(err => {
        console.error('Save permission error:', err);
        alert('网络错误，请稍后重试');
    });
}

function editPermission(id) {
    fetch(`{{ route("admin_api_permissions_detail") }}?id=${id}`, {
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(res => res.json())
    .then(data => {
        if (data.success && data.permission) {
            const p = data.permission;
            document.getElementById('modalTitle').textContent = '编辑权限';
            document.getElementById('permissionId').value = p.id;
            document.getElementById('permissionName').value = p.name;
            document.getElementById('permissionSlug').value = p.slug;
            document.getElementById('permissionModule').value = p.module || '';
            document.getElementById('permissionDescription').value = p.description || '';
            document.getElementById('permissionMethod').value = p.method || '';
            document.getElementById('permissionSort').value = p.sort || 0;
            document.getElementById('permissionRoute').value = p.route || '';
            document.getElementById('permissionStatus').checked = p.status === 1 || p.status === 'active';
            document.getElementById('addModal').classList.remove('hidden');
        }
    })
    .catch(err => console.error('Load permission error:', err));
}

function deletePermission(id) {
    if (!confirm('确定要删除该权限吗？此操作不可恢复。')) return;

    fetch('{{ route("admin_api_permissions_delete") }}', {
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
            loadPermissions();
        } else {
            alert(data.message || '删除失败');
        }
    })
    .catch(err => {
        console.error('Delete permission error:', err);
        alert('网络错误，请稍后重试');
    });
}
</script>
@endsection
