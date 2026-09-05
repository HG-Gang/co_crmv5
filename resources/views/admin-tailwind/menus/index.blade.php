@extends('admin-tailwind.layouts.app')

@section('title', '菜单管理 - 管理后台')

@section('content')
<!-- Page Header -->
<div class="flex items-center justify-between mb-8">
    <div>
        <h1 class="text-3xl font-bold text-slate-800">菜单管理</h1>
        <p class="text-slate-600 mt-2">管理系统导航菜单结构</p>
    </div>
    <button onclick="showAddModal()" class="px-6 py-2 bg-gradient-to-r from-blue-500 to-indigo-600 text-white font-semibold rounded-lg hover:shadow-lg transition">
        <i class="fas fa-plus mr-2"></i>添加菜单
    </button>
</div>

<!-- Stats Cards -->
<div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-6">
    <div class="bg-white rounded-xl shadow-lg p-6">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-slate-600 text-sm font-semibold">总菜单数</p>
                <h3 class="text-2xl font-bold text-slate-800 mt-2" id="totalMenus">0</h3>
            </div>
            <div class="w-12 h-12 bg-gradient-to-br from-blue-500 to-indigo-600 rounded-full flex items-center justify-center text-white">
                <i class="fas fa-bars"></i>
            </div>
        </div>
    </div>

    <div class="bg-white rounded-xl shadow-lg p-6">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-slate-600 text-sm font-semibold">一级菜单</p>
                <h3 class="text-2xl font-bold text-slate-800 mt-2" id="topMenus">0</h3>
            </div>
            <div class="w-12 h-12 bg-gradient-to-br from-green-500 to-emerald-600 rounded-full flex items-center justify-center text-white">
                <i class="fas fa-th-large"></i>
            </div>
        </div>
    </div>

    <div class="bg-white rounded-xl shadow-lg p-6">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-slate-600 text-sm font-semibold">子菜单</p>
                <h3 class="text-2xl font-bold text-slate-800 mt-2" id="subMenus">0</h3>
            </div>
            <div class="w-12 h-12 bg-gradient-to-br from-purple-500 to-pink-600 rounded-full flex items-center justify-center text-white">
                <i class="fas fa-sitemap"></i>
            </div>
        </div>
    </div>

    <div class="bg-white rounded-xl shadow-lg p-6">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-slate-600 text-sm font-semibold">隐藏菜单</p>
                <h3 class="text-2xl font-bold text-slate-800 mt-2" id="hiddenMenus">0</h3>
            </div>
            <div class="w-12 h-12 bg-gradient-to-br from-orange-500 to-red-600 rounded-full flex items-center justify-center text-white">
                <i class="fas fa-eye-slash"></i>
            </div>
        </div>
    </div>
</div>

<!-- Menus Tree -->
<div class="bg-white rounded-xl shadow-lg p-6">
    <div class="flex items-center justify-between mb-6">
        <h3 class="text-lg font-bold text-slate-800">
            <i class="fas fa-stream text-blue-600 mr-2"></i>菜单树
        </h3>
        <div class="flex gap-2">
            <button onclick="expandAll()" class="px-4 py-2 bg-blue-100 text-blue-700 font-semibold rounded-lg hover:bg-blue-200 transition text-sm">
                <i class="fas fa-expand-alt mr-1"></i>展开全部
            </button>
            <button onclick="collapseAll()" class="px-4 py-2 bg-slate-100 text-slate-700 font-semibold rounded-lg hover:bg-slate-200 transition text-sm">
                <i class="fas fa-compress-alt mr-1"></i>折叠全部
            </button>
        </div>
    </div>

    <div id="menusTree">
        <div class="flex items-center justify-center py-12 text-slate-400">
            <i class="fas fa-spinner fa-spin mr-2"></i>加载中...
        </div>
    </div>
</div>

<!-- Add/Edit Modal -->
<div id="addModal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-xl shadow-2xl max-w-2xl w-full max-h-[90vh] overflow-y-auto">
        <div class="sticky top-0 bg-white border-b border-slate-200 px-6 py-4 flex items-center justify-between">
            <h3 class="text-xl font-bold text-slate-800" id="modalTitle">添加菜单</h3>
            <button onclick="closeAddModal()" class="text-slate-400 hover:text-slate-600">
                <i class="fas fa-times text-xl"></i>
            </button>
        </div>
        <div class="p-6">
            <form id="menuForm" class="space-y-4">
                <input type="hidden" id="menuId">
                <div>
                    <label class="block text-sm font-semibold text-slate-700 mb-2">上级菜单</label>
                    <select id="parentId" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                        <option value="0">顶级菜单</option>
                    </select>
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-semibold text-slate-700 mb-2">菜单名称 <span class="text-red-500">*</span></label>
                        <input type="text" id="menuName" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" placeholder="例如: 用户管理">
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-slate-700 mb-2">图标</label>
                        <input type="text" id="menuIcon" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" placeholder="例如: fa-users">
                        <p class="text-xs text-slate-500 mt-1">FontAwesome 图标类名</p>
                    </div>
                </div>
                <div>
                    <label class="block text-sm font-semibold text-slate-700 mb-2">菜单路由</label>
                    <input type="text" id="menuRoute" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" placeholder="例如: /admin/users">
                </div>
                <div>
                    <label class="block text-sm font-semibold text-slate-700 mb-2">权限标识</label>
                    <input type="text" id="menuPermission" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" placeholder="例如: users.view">
                    <p class="text-xs text-slate-500 mt-1">关联权限标识，留空表示不限制</p>
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-semibold text-slate-700 mb-2">排序</label>
                        <input type="number" id="menuSort" value="0" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                        <p class="text-xs text-slate-500 mt-1">数字越小越靠前</p>
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-slate-700 mb-2">打开方式</label>
                        <select id="menuTarget" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                            <option value="_self">当前窗口</option>
                            <option value="_blank">新窗口</option>
                        </select>
                    </div>
                </div>
                <div class="flex items-center gap-4">
                    <label class="flex items-center gap-2 cursor-pointer">
                        <input type="checkbox" id="menuVisible" checked class="w-4 h-4 text-blue-600 border-slate-300 rounded focus:ring-2 focus:ring-blue-500">
                        <span class="text-sm font-semibold text-slate-700">显示菜单</span>
                    </label>
                    <label class="flex items-center gap-2 cursor-pointer">
                        <input type="checkbox" id="menuStatus" checked class="w-4 h-4 text-blue-600 border-slate-300 rounded focus:ring-2 focus:ring-blue-500">
                        <span class="text-sm font-semibold text-slate-700">启用菜单</span>
                    </label>
                </div>
            </form>
        </div>
        <div class="sticky bottom-0 bg-slate-50 px-6 py-4 flex justify-end gap-3 border-t border-slate-200">
            <button onclick="closeAddModal()" class="px-6 py-2 bg-slate-200 text-slate-700 font-semibold rounded-lg hover:bg-slate-300 transition">
                取消
            </button>
            <button onclick="saveMenu()" class="px-6 py-2 bg-gradient-to-r from-blue-500 to-indigo-600 text-white font-semibold rounded-lg hover:shadow-lg transition">
                <i class="fas fa-save mr-2"></i>保存
            </button>
        </div>
    </div>
</div>

<script>
let menusData = [];

document.addEventListener('DOMContentLoaded', function() {
    loadStats();
    loadMenus();
});

function loadStats() {
    fetch('{{ route("admin_api_menus_stats") }}', {
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(res => res.json())
    .then(data => {
        if (data.success && data.stats) {
            document.getElementById('totalMenus').textContent = data.stats.total || 0;
            document.getElementById('topMenus').textContent = data.stats.top || 0;
            document.getElementById('subMenus').textContent = data.stats.sub || 0;
            document.getElementById('hiddenMenus').textContent = data.stats.hidden || 0;
        }
    })
    .catch(err => console.error('Load stats error:', err));
}

function loadMenus() {
    fetch('{{ route("admin_api_menus_list") }}', {
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(res => res.json())
    .then(data => {
        if (data.success && data.menus) {
            menusData = data.menus;
            renderMenusTree(data.menus);
            updateParentSelect(data.menus);
        }
    })
    .catch(err => {
        console.error('Load menus error:', err);
        document.getElementById('menusTree').innerHTML = `
            <div class="flex items-center justify-center py-12 text-slate-400">
                <i class="fas fa-exclamation-circle mr-2"></i>加载失败
            </div>
        `;
    });
}

function renderMenusTree(menus) {
    if (menus.length === 0) {
        document.getElementById('menusTree').innerHTML = `
            <div class="flex items-center justify-center py-12 text-slate-400">
                <i class="fas fa-inbox mr-2"></i>暂无数据
            </div>
        `;
        return;
    }

    const html = buildTreeHTML(menus, 0, 0);
    document.getElementById('menusTree').innerHTML = html;
}

function buildTreeHTML(menus, parentId, level) {
    const children = menus.filter(m => (m.parent_id || 0) === parentId);
    if (children.length === 0) return '';

    return children.map(menu => {
        const hasChildren = menus.some(m => (m.parent_id || 0) === menu.id);
        const indent = level * 30;

        return `
            <div class="menu-item border-b border-slate-100 last:border-0" data-menu-id="${menu.id}">
                <div class="flex items-center justify-between py-3 hover:bg-slate-50" style="padding-left: ${indent + 16}px;">
                    <div class="flex items-center gap-3 flex-1">
                        ${hasChildren ? `
                            <button onclick="toggleChildren(${menu.id})" class="toggle-btn text-slate-400 hover:text-slate-600 w-5">
                                <i class="fas fa-chevron-down"></i>
                            </button>
                        ` : '<span class="w-5"></span>'}
                        <div class="w-10 h-10 bg-gradient-to-br from-blue-500 to-indigo-600 rounded-lg flex items-center justify-center text-white">
                            <i class="fas ${menu.icon || 'fa-bars'}"></i>
                        </div>
                        <div class="flex-1">
                            <div class="flex items-center gap-2">
                                <h4 class="text-sm font-semibold text-slate-800">${menu.name}</h4>
                                ${getVisibleBadge(menu.visible)}
                                ${getStatusBadge(menu.status)}
                            </div>
                            <p class="text-xs text-slate-500 mt-1">
                                ${menu.route ? `<i class="fas fa-link mr-1"></i>${menu.route}` : '<i class="fas fa-folder mr-1"></i>菜单组'}
                                ${menu.permission ? `<span class="ml-3"><i class="fas fa-key mr-1"></i>${menu.permission}</span>` : ''}
                            </p>
                        </div>
                    </div>
                    <div class="flex items-center gap-2">
                        <span class="text-xs text-slate-500 mr-2">排序: ${menu.sort || 0}</span>
                        <button onclick="addSubMenu(${menu.id}, '${menu.name}')" class="text-green-600 hover:text-green-700" title="添加子菜单">
                            <i class="fas fa-plus-circle"></i>
                        </button>
                        <button onclick="editMenu(${menu.id})" class="text-blue-600 hover:text-blue-700" title="编辑">
                            <i class="fas fa-edit"></i>
                        </button>
                        <button onclick="deleteMenu(${menu.id})" class="text-red-600 hover:text-red-700" title="删除">
                            <i class="fas fa-trash"></i>
                        </button>
                    </div>
                </div>
                ${hasChildren ? `<div class="children">${buildTreeHTML(menus, menu.id, level + 1)}</div>` : ''}
            </div>
        `;
    }).join('');
}

function getVisibleBadge(visible) {
    return visible === 1 || visible === true
        ? '<span class="px-2 py-0.5 bg-blue-100 text-blue-700 text-xs font-semibold rounded-full"><i class="fas fa-eye mr-1"></i>显示</span>'
        : '<span class="px-2 py-0.5 bg-slate-100 text-slate-700 text-xs font-semibold rounded-full"><i class="fas fa-eye-slash mr-1"></i>隐藏</span>';
}

function getStatusBadge(status) {
    return status === 1 || status === 'active'
        ? '<span class="px-2 py-0.5 bg-green-100 text-green-700 text-xs font-semibold rounded-full"><i class="fas fa-check-circle mr-1"></i>启用</span>'
        : '<span class="px-2 py-0.5 bg-red-100 text-red-700 text-xs font-semibold rounded-full"><i class="fas fa-ban mr-1"></i>禁用</span>';
}

function updateParentSelect(menus) {
    const select = document.getElementById('parentId');
    select.innerHTML = '<option value="0">顶级菜单</option>';

    function addOptions(parentId, level) {
        const children = menus.filter(m => (m.parent_id || 0) === parentId);
        children.forEach(menu => {
            const option = document.createElement('option');
            option.value = menu.id;
            option.textContent = '　'.repeat(level) + menu.name;
            select.appendChild(option);
            addOptions(menu.id, level + 1);
        });
    }

    addOptions(0, 0);
}

function toggleChildren(menuId) {
    const item = document.querySelector(`[data-menu-id="${menuId}"]`);
    const children = item.querySelector('.children');
    const btn = item.querySelector('.toggle-btn i');

    if (children.style.display === 'none') {
        children.style.display = 'block';
        btn.classList.replace('fa-chevron-right', 'fa-chevron-down');
    } else {
        children.style.display = 'none';
        btn.classList.replace('fa-chevron-down', 'fa-chevron-right');
    }
}

function expandAll() {
    document.querySelectorAll('.children').forEach(el => el.style.display = 'block');
    document.querySelectorAll('.toggle-btn i').forEach(i => {
        i.classList.remove('fa-chevron-right');
        i.classList.add('fa-chevron-down');
    });
}

function collapseAll() {
    document.querySelectorAll('.children').forEach(el => el.style.display = 'none');
    document.querySelectorAll('.toggle-btn i').forEach(i => {
        i.classList.remove('fa-chevron-down');
        i.classList.add('fa-chevron-right');
    });
}

function showAddModal() {
    document.getElementById('modalTitle').textContent = '添加菜单';
    document.getElementById('menuForm').reset();
    document.getElementById('menuId').value = '';
    document.getElementById('parentId').value = '0';
    document.getElementById('menuVisible').checked = true;
    document.getElementById('menuStatus').checked = true;
    document.getElementById('addModal').classList.remove('hidden');
}

function addSubMenu(parentId, parentName) {
    document.getElementById('modalTitle').textContent = `添加子菜单 - ${parentName}`;
    document.getElementById('menuForm').reset();
    document.getElementById('menuId').value = '';
    document.getElementById('parentId').value = parentId;
    document.getElementById('menuVisible').checked = true;
    document.getElementById('menuStatus').checked = true;
    document.getElementById('addModal').classList.remove('hidden');
}

function closeAddModal() {
    document.getElementById('addModal').classList.add('hidden');
}

function saveMenu() {
    const menuId = document.getElementById('menuId').value;
    const parentId = document.getElementById('parentId').value;
    const name = document.getElementById('menuName').value.trim();
    const icon = document.getElementById('menuIcon').value.trim();
    const route = document.getElementById('menuRoute').value.trim();
    const permission = document.getElementById('menuPermission').value.trim();
    const sort = document.getElementById('menuSort').value;
    const target = document.getElementById('menuTarget').value;
    const visible = document.getElementById('menuVisible').checked ? 1 : 0;
    const status = document.getElementById('menuStatus').checked ? 1 : 0;

    if (!name) {
        alert('请输入菜单名称');
        return;
    }

    const url = menuId ? '{{ route("admin_api_menus_update") }}' : '{{ route("admin_api_menus_create") }}';

    fetch(url, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: JSON.stringify({
            id: menuId,
            parent_id: parentId,
            name: name,
            icon: icon,
            route: route,
            permission: permission,
            sort: sort,
            target: target,
            visible: visible,
            status: status
        })
    })
    .then(res => res.json())
    .then(data => {
        if (data.success || data.code === 200) {
            alert(menuId ? '保存成功' : '添加成功');
            closeAddModal();
            loadStats();
            loadMenus();
        } else {
            alert(data.message || '操作失败');
        }
    })
    .catch(err => {
        console.error('Save menu error:', err);
        alert('网络错误，请稍后重试');
    });
}

function editMenu(id) {
    fetch(`{{ route("admin_api_menus_detail") }}?id=${id}`, {
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(res => res.json())
    .then(data => {
        if (data.success && data.menu) {
            const m = data.menu;
            document.getElementById('modalTitle').textContent = '编辑菜单';
            document.getElementById('menuId').value = m.id;
            document.getElementById('parentId').value = m.parent_id || 0;
            document.getElementById('menuName').value = m.name;
            document.getElementById('menuIcon').value = m.icon || '';
            document.getElementById('menuRoute').value = m.route || '';
            document.getElementById('menuPermission').value = m.permission || '';
            document.getElementById('menuSort').value = m.sort || 0;
            document.getElementById('menuTarget').value = m.target || '_self';
            document.getElementById('menuVisible').checked = m.visible === 1 || m.visible === true;
            document.getElementById('menuStatus').checked = m.status === 1 || m.status === 'active';
            document.getElementById('addModal').classList.remove('hidden');
        }
    })
    .catch(err => console.error('Load menu error:', err));
}

function deleteMenu(id) {
    if (!confirm('确定要删除该菜单吗？如果有子菜单也会一并删除。')) return;

    fetch('{{ route("admin_api_menus_delete") }}', {
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
            loadMenus();
        } else {
            alert(data.message || '删除失败');
        }
    })
    .catch(err => {
        console.error('Delete menu error:', err);
        alert('网络错误，请稍后重试');
    });
}
</script>
@endsection
