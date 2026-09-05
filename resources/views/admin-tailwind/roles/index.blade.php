@extends('admin-tailwind.layouts.app')

@section('title', '角色管理 - 管理后台')

@section('content')
<!-- Page Header -->
<div class="flex items-center justify-between mb-8">
    <div>
        <h1 class="text-3xl font-bold text-slate-800">角色管理</h1>
        <p class="text-slate-600 mt-2">管理系统角色和权限分配</p>
    </div>
    <button onclick="showAddModal()" class="px-6 py-2 bg-gradient-to-r from-blue-500 to-indigo-600 text-white font-semibold rounded-lg hover:shadow-lg transition">
        <i class="fas fa-plus mr-2"></i>添加角色
    </button>
</div>

<!-- Stats Cards -->
<div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-6">
    <div class="bg-white rounded-xl shadow-lg p-6">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-slate-600 text-sm font-semibold">总角色数</p>
                <h3 class="text-2xl font-bold text-slate-800 mt-2" id="totalRoles">0</h3>
            </div>
            <div class="w-12 h-12 bg-gradient-to-br from-blue-500 to-indigo-600 rounded-full flex items-center justify-center text-white">
                <i class="fas fa-user-shield"></i>
            </div>
        </div>
    </div>

    <div class="bg-white rounded-xl shadow-lg p-6">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-slate-600 text-sm font-semibold">启用角色</p>
                <h3 class="text-2xl font-bold text-slate-800 mt-2" id="activeRoles">0</h3>
            </div>
            <div class="w-12 h-12 bg-gradient-to-br from-green-500 to-emerald-600 rounded-full flex items-center justify-center text-white">
                <i class="fas fa-check-circle"></i>
            </div>
        </div>
    </div>

    <div class="bg-white rounded-xl shadow-lg p-6">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-slate-600 text-sm font-semibold">禁用角色</p>
                <h3 class="text-2xl font-bold text-slate-800 mt-2" id="disabledRoles">0</h3>
            </div>
            <div class="w-12 h-12 bg-gradient-to-br from-orange-500 to-red-600 rounded-full flex items-center justify-center text-white">
                <i class="fas fa-ban"></i>
            </div>
        </div>
    </div>

    <div class="bg-white rounded-xl shadow-lg p-6">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-slate-600 text-sm font-semibold">关联用户</p>
                <h3 class="text-2xl font-bold text-slate-800 mt-2" id="totalUsers">0</h3>
            </div>
            <div class="w-12 h-12 bg-gradient-to-br from-purple-500 to-pink-600 rounded-full flex items-center justify-center text-white">
                <i class="fas fa-users"></i>
            </div>
        </div>
    </div>
</div>

<!-- Roles Grid -->
<div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-6" id="rolesGrid">
    <div class="col-span-full flex items-center justify-center py-12 text-slate-400">
        <i class="fas fa-spinner fa-spin mr-2"></i>加载中...
    </div>
</div>

<!-- Add/Edit Modal -->
<div id="addModal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-xl shadow-2xl max-w-2xl w-full max-h-[90vh] overflow-y-auto">
        <div class="sticky top-0 bg-white border-b border-slate-200 px-6 py-4 flex items-center justify-between">
            <h3 class="text-xl font-bold text-slate-800" id="modalTitle">添加角色</h3>
            <button onclick="closeAddModal()" class="text-slate-400 hover:text-slate-600">
                <i class="fas fa-times text-xl"></i>
            </button>
        </div>
        <div class="p-6">
            <form id="roleForm" class="space-y-4">
                <input type="hidden" id="roleId">
                <div>
                    <label class="block text-sm font-semibold text-slate-700 mb-2">角色名称 <span class="text-red-500">*</span></label>
                    <input type="text" id="roleName" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" placeholder="例如: 管理员">
                </div>
                <div>
                    <label class="block text-sm font-semibold text-slate-700 mb-2">角色标识 <span class="text-red-500">*</span></label>
                    <input type="text" id="roleSlug" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" placeholder="例如: admin">
                    <p class="text-xs text-slate-500 mt-1">用于系统内部识别，只能包含字母、数字和下划线</p>
                </div>
                <div>
                    <label class="block text-sm font-semibold text-slate-700 mb-2">角色描述</label>
                    <textarea id="roleDescription" rows="3" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" placeholder="请输入角色描述..."></textarea>
                </div>
                <div>
                    <label class="block text-sm font-semibold text-slate-700 mb-2">排序</label>
                    <input type="number" id="roleSort" value="0" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                    <p class="text-xs text-slate-500 mt-1">数字越小越靠前</p>
                </div>
                <div>
                    <label class="flex items-center gap-2 cursor-pointer">
                        <input type="checkbox" id="roleStatus" checked class="w-4 h-4 text-blue-600 border-slate-300 rounded focus:ring-2 focus:ring-blue-500">
                        <span class="text-sm font-semibold text-slate-700">启用该角色</span>
                    </label>
                </div>
            </form>
        </div>
        <div class="sticky bottom-0 bg-slate-50 px-6 py-4 flex justify-end gap-3 border-t border-slate-200">
            <button onclick="closeAddModal()" class="px-6 py-2 bg-slate-200 text-slate-700 font-semibold rounded-lg hover:bg-slate-300 transition">
                取消
            </button>
            <button onclick="saveRole()" class="px-6 py-2 bg-gradient-to-r from-blue-500 to-indigo-600 text-white font-semibold rounded-lg hover:shadow-lg transition">
                <i class="fas fa-save mr-2"></i>保存
            </button>
        </div>
    </div>
</div>

<!-- Permissions Modal -->
<div id="permissionsModal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-xl shadow-2xl max-w-4xl w-full max-h-[90vh] overflow-y-auto">
        <div class="sticky top-0 bg-white border-b border-slate-200 px-6 py-4 flex items-center justify-between">
            <h3 class="text-xl font-bold text-slate-800">
                <i class="fas fa-key text-blue-600 mr-2"></i>权限配置 - <span id="permRoleName"></span>
            </h3>
            <button onclick="closePermissionsModal()" class="text-slate-400 hover:text-slate-600">
                <i class="fas fa-times text-xl"></i>
            </button>
        </div>
        <div class="p-6">
            <div class="mb-4">
                <button onclick="selectAllPermissions()" class="px-4 py-2 bg-blue-100 text-blue-700 font-semibold rounded-lg hover:bg-blue-200 transition mr-2">
                    <i class="fas fa-check-square mr-2"></i>全选
                </button>
                <button onclick="deselectAllPermissions()" class="px-4 py-2 bg-slate-100 text-slate-700 font-semibold rounded-lg hover:bg-slate-200 transition">
                    <i class="fas fa-square mr-2"></i>取消全选
                </button>
            </div>
            <div id="permissionsList" class="space-y-4">
                <div class="flex items-center justify-center py-8 text-slate-400">
                    <i class="fas fa-spinner fa-spin mr-2"></i>加载中...
                </div>
            </div>
        </div>
        <div class="sticky bottom-0 bg-slate-50 px-6 py-4 flex justify-end gap-3 border-t border-slate-200">
            <button onclick="closePermissionsModal()" class="px-6 py-2 bg-slate-200 text-slate-700 font-semibold rounded-lg hover:bg-slate-300 transition">
                取消
            </button>
            <button onclick="savePermissions()" class="px-6 py-2 bg-gradient-to-r from-blue-500 to-indigo-600 text-white font-semibold rounded-lg hover:shadow-lg transition">
                <i class="fas fa-save mr-2"></i>保存权限
            </button>
        </div>
    </div>
</div>

<script>
let currentRoleId = null;

document.addEventListener('DOMContentLoaded', function() {
    loadStats();
    loadRoles();
});

function loadStats() {
    fetch('{{ route("admin_api_roles_stats") }}', {
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(res => res.json())
    .then(data => {
        if (data.success && data.stats) {
            document.getElementById('totalRoles').textContent = data.stats.total || 0;
            document.getElementById('activeRoles').textContent = data.stats.active || 0;
            document.getElementById('disabledRoles').textContent = data.stats.disabled || 0;
            document.getElementById('totalUsers').textContent = data.stats.total_users || 0;
        }
    })
    .catch(err => console.error('Load stats error:', err));
}

function loadRoles() {
    fetch('{{ route("admin_api_roles_list") }}', {
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(res => res.json())
    .then(data => {
        if (data.success && data.roles) {
            renderRoles(data.roles);
        }
    })
    .catch(err => {
        console.error('Load roles error:', err);
        document.getElementById('rolesGrid').innerHTML = `
            <div class="col-span-full flex items-center justify-center py-12 text-slate-400">
                <i class="fas fa-exclamation-circle mr-2"></i>加载失败
            </div>
        `;
    });
}

function renderRoles(roles) {
    if (roles.length === 0) {
        document.getElementById('rolesGrid').innerHTML = `
            <div class="col-span-full flex items-center justify-center py-12 text-slate-400">
                <i class="fas fa-inbox mr-2"></i>暂无数据
            </div>
        `;
        return;
    }

    const html = roles.map(r => `
        <div class="bg-white rounded-xl shadow-lg p-6 hover:shadow-xl transition">
            <div class="flex items-center justify-between mb-4">
                <div class="flex items-center gap-3">
                    <div class="w-12 h-12 bg-gradient-to-br from-blue-500 to-indigo-600 rounded-full flex items-center justify-center text-white">
                        <i class="fas fa-user-shield"></i>
                    </div>
                    <div>
                        <h3 class="text-lg font-bold text-slate-800">${r.name}</h3>
                        <p class="text-xs text-slate-500">${r.slug}</p>
                    </div>
                </div>
                ${getStatusBadge(r.status)}
            </div>

            <p class="text-sm text-slate-600 mb-4 min-h-[40px]">${r.description || '暂无描述'}</p>

            <div class="grid grid-cols-2 gap-3 mb-4 py-3 border-t border-b border-slate-100">
                <div class="text-center">
                    <p class="text-xs text-slate-500">权限数</p>
                    <p class="text-lg font-bold text-slate-800">${r.permissions_count || 0}</p>
                </div>
                <div class="text-center">
                    <p class="text-xs text-slate-500">用户数</p>
                    <p class="text-lg font-bold text-slate-800">${r.users_count || 0}</p>
                </div>
            </div>

            <div class="flex items-center gap-2">
                <button onclick="editRole(${r.id})" class="flex-1 px-4 py-2 bg-gradient-to-r from-blue-500 to-indigo-600 text-white font-semibold rounded-lg hover:shadow-lg transition text-sm">
                    <i class="fas fa-edit mr-1"></i>编辑
                </button>
                <button onclick="configPermissions(${r.id}, '${r.name}')" class="flex-1 px-4 py-2 bg-gradient-to-r from-green-500 to-emerald-600 text-white font-semibold rounded-lg hover:shadow-lg transition text-sm">
                    <i class="fas fa-key mr-1"></i>权限
                </button>
                <button onclick="deleteRole(${r.id})" class="px-4 py-2 bg-gradient-to-r from-red-500 to-pink-600 text-white font-semibold rounded-lg hover:shadow-lg transition text-sm">
                    <i class="fas fa-trash"></i>
                </button>
            </div>
        </div>
    `).join('');

    document.getElementById('rolesGrid').innerHTML = html;
}

function getStatusBadge(status) {
    return status === 1 || status === 'active'
        ? '<span class="px-2 py-1 bg-green-100 text-green-700 text-xs font-semibold rounded-full"><i class="fas fa-check-circle mr-1"></i>启用</span>'
        : '<span class="px-2 py-1 bg-slate-100 text-slate-700 text-xs font-semibold rounded-full"><i class="fas fa-ban mr-1"></i>禁用</span>';
}

function showAddModal() {
    document.getElementById('modalTitle').textContent = '添加角色';
    document.getElementById('roleForm').reset();
    document.getElementById('roleId').value = '';
    document.getElementById('roleStatus').checked = true;
    document.getElementById('addModal').classList.remove('hidden');
}

function closeAddModal() {
    document.getElementById('addModal').classList.add('hidden');
}

function saveRole() {
    const roleId = document.getElementById('roleId').value;
    const name = document.getElementById('roleName').value.trim();
    const slug = document.getElementById('roleSlug').value.trim();
    const description = document.getElementById('roleDescription').value.trim();
    const sort = document.getElementById('roleSort').value;
    const status = document.getElementById('roleStatus').checked ? 1 : 0;

    if (!name) {
        alert('请输入角色名称');
        return;
    }

    if (!slug) {
        alert('请输入角色标识');
        return;
    }

    if (!/^[a-zA-Z0-9_]+$/.test(slug)) {
        alert('角色标识只能包含字母、数字和下划线');
        return;
    }

    const url = roleId ? '{{ route("admin_api_roles_update") }}' : '{{ route("admin_api_roles_create") }}';

    fetch(url, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: JSON.stringify({
            id: roleId,
            name: name,
            slug: slug,
            description: description,
            sort: sort,
            status: status
        })
    })
    .then(res => res.json())
    .then(data => {
        if (data.success || data.code === 200) {
            alert(roleId ? '保存成功' : '添加成功');
            closeAddModal();
            loadStats();
            loadRoles();
        } else {
            alert(data.message || '操作失败');
        }
    })
    .catch(err => {
        console.error('Save role error:', err);
        alert('网络错误，请稍后重试');
    });
}

function editRole(id) {
    fetch(`{{ route("admin_api_roles_detail") }}?id=${id}`, {
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(res => res.json())
    .then(data => {
        if (data.success && data.role) {
            const r = data.role;
            document.getElementById('modalTitle').textContent = '编辑角色';
            document.getElementById('roleId').value = r.id;
            document.getElementById('roleName').value = r.name;
            document.getElementById('roleSlug').value = r.slug;
            document.getElementById('roleDescription').value = r.description || '';
            document.getElementById('roleSort').value = r.sort || 0;
            document.getElementById('roleStatus').checked = r.status === 1 || r.status === 'active';
            document.getElementById('addModal').classList.remove('hidden');
        }
    })
    .catch(err => console.error('Load role error:', err));
}

function deleteRole(id) {
    if (!confirm('确定要删除该角色吗？此操作不可恢复。')) return;

    fetch('{{ route("admin_api_roles_delete") }}', {
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
            loadRoles();
        } else {
            alert(data.message || '删除失败');
        }
    })
    .catch(err => {
        console.error('Delete role error:', err);
        alert('网络错误，请稍后重试');
    });
}

function configPermissions(id, name) {
    currentRoleId = id;
    document.getElementById('permRoleName').textContent = name;
    document.getElementById('permissionsModal').classList.remove('hidden');
    loadPermissions(id);
}

function closePermissionsModal() {
    document.getElementById('permissionsModal').classList.add('hidden');
    currentRoleId = null;
}

function loadPermissions(roleId) {
    fetch(`{{ route("admin_api_roles_permissions") }}?role_id=${roleId}`, {
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(res => res.json())
    .then(data => {
        if (data.success && data.permissions) {
            renderPermissions(data.permissions, data.selected || []);
        }
    })
    .catch(err => {
        console.error('Load permissions error:', err);
        document.getElementById('permissionsList').innerHTML = `
            <div class="flex items-center justify-center py-8 text-slate-400">
                <i class="fas fa-exclamation-circle mr-2"></i>加载失败
            </div>
        `;
    });
}

function renderPermissions(permissions, selected) {
    if (permissions.length === 0) {
        document.getElementById('permissionsList').innerHTML = `
            <div class="flex items-center justify-center py-8 text-slate-400">
                <i class="fas fa-inbox mr-2"></i>暂无权限
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
        <div class="border border-slate-200 rounded-lg p-4">
            <h4 class="text-sm font-bold text-slate-800 mb-3">
                <i class="fas fa-folder-open text-blue-600 mr-2"></i>${module}
            </h4>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-2">
                ${grouped[module].map(p => `
                    <label class="flex items-center gap-2 cursor-pointer p-2 hover:bg-slate-50 rounded">
                        <input type="checkbox" value="${p.id}" class="permission-checkbox w-4 h-4 text-blue-600 border-slate-300 rounded focus:ring-2 focus:ring-blue-500" ${selected.includes(p.id) ? 'checked' : ''}>
                        <span class="text-sm text-slate-700">${p.name}</span>
                    </label>
                `).join('')}
            </div>
        </div>
    `).join('');

    document.getElementById('permissionsList').innerHTML = html;
}

function selectAllPermissions() {
    document.querySelectorAll('.permission-checkbox').forEach(cb => cb.checked = true);
}

function deselectAllPermissions() {
    document.querySelectorAll('.permission-checkbox').forEach(cb => cb.checked = false);
}

function savePermissions() {
    const selected = Array.from(document.querySelectorAll('.permission-checkbox:checked')).map(cb => parseInt(cb.value));

    fetch('{{ route("admin_api_roles_update_permissions") }}', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: JSON.stringify({
            role_id: currentRoleId,
            permissions: selected
        })
    })
    .then(res => res.json())
    .then(data => {
        if (data.success || data.code === 200) {
            alert('权限配置已保存');
            closePermissionsModal();
            loadRoles();
        } else {
            alert(data.message || '保存失败');
        }
    })
    .catch(err => {
        console.error('Save permissions error:', err);
        alert('网络错误，请稍后重试');
    });
}
</script>
@endsection
