@extends('admin-tailwind.layouts.app')

@section('title', '组配置 - 管理后台')

@section('content')
<!-- Page Header -->
<div class="flex items-center justify-between mb-8">
    <div>
        <h1 class="text-3xl font-bold text-slate-800">组配置</h1>
        <p class="text-slate-600 mt-2">管理MT4交易组的配置参数和权限</p>
    </div>
    <div class="flex gap-3">
        <button onclick="refreshData()" class="px-6 py-3 bg-gradient-to-r from-blue-500 to-indigo-600 text-white font-semibold rounded-lg hover:shadow-lg transition">
            <i class="fas fa-sync-alt mr-2"></i>刷新数据
        </button>
        <button onclick="addGroup()" class="px-6 py-3 bg-gradient-to-r from-green-500 to-emerald-600 text-white font-semibold rounded-lg hover:shadow-lg transition">
            <i class="fas fa-plus mr-2"></i>新增组配置
        </button>
    </div>
</div>

<!-- Stats Cards -->
<div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-6">
    <div class="bg-white rounded-xl shadow-lg p-6 border-l-4 border-blue-500">
        <p class="text-sm text-slate-600 mb-2">总组数</p>
        <p class="text-3xl font-bold text-slate-800" id="totalGroups">0</p>
    </div>
    <div class="bg-white rounded-xl shadow-lg p-6 border-l-4 border-green-500">
        <p class="text-sm text-slate-600 mb-2">启用组数</p>
        <p class="text-3xl font-bold text-green-600" id="activeGroups">0</p>
    </div>
    <div class="bg-white rounded-xl shadow-lg p-6 border-l-4 border-purple-500">
        <p class="text-sm text-slate-600 mb-2">总用户数</p>
        <p class="text-3xl font-bold text-purple-600" id="totalUsers">0</p>
    </div>
    <div class="bg-white rounded-xl shadow-lg p-6 border-l-4 border-orange-500">
        <p class="text-sm text-slate-600 mb-2">默认组</p>
        <p class="text-xl font-bold text-orange-600" id="defaultGroup">-</p>
    </div>
</div>

<!-- Filter Bar -->
<div class="bg-white rounded-xl shadow-lg p-6 mb-6">
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
        <div>
            <label class="block text-sm font-semibold text-slate-700 mb-2">搜索</label>
            <input type="text" id="searchKeyword" placeholder="组名称" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
        </div>
        <div>
            <label class="block text-sm font-semibold text-slate-700 mb-2">状态</label>
            <select id="filterStatus" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                <option value="">全部状态</option>
                <option value="active">启用</option>
                <option value="inactive">禁用</option>
            </select>
        </div>
        <div>
            <label class="block text-sm font-semibold text-slate-700 mb-2">杠杆范围</label>
            <select id="filterLeverage" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                <option value="">全部杠杆</option>
                <option value="1-100">1:1 - 1:100</option>
                <option value="100-500">1:100 - 1:500</option>
                <option value="500+">1:500+</option>
            </select>
        </div>
        <div class="flex items-end">
            <button onclick="searchGroups()" class="w-full px-6 py-2 bg-gradient-to-r from-blue-500 to-indigo-600 text-white font-semibold rounded-lg hover:shadow-lg transition">
                <i class="fas fa-search mr-2"></i>搜索
            </button>
        </div>
    </div>
</div>

<!-- Groups Grid -->
<div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-6" id="groupsGrid">
    <div class="col-span-full text-center py-12">
        <i class="fas fa-spinner fa-spin text-3xl text-slate-500 mb-3"></i>
        <p class="text-slate-600">加载中...</p>
    </div>
</div>

<!-- Edit Modal -->
<div id="editModal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl max-w-3xl w-full max-h-[90vh] overflow-y-auto">
        <div class="sticky top-0 bg-gradient-to-r from-blue-500 to-indigo-600 px-6 py-4 flex items-center justify-between rounded-t-2xl">
            <h3 class="text-xl font-bold text-white" id="modalTitle">编辑组配置</h3>
            <button onclick="closeEditModal()" class="text-white hover:text-slate-200">
                <i class="fas fa-times text-xl"></i>
            </button>
        </div>
        <div class="p-6">
            <form id="groupForm">
                <input type="hidden" id="groupId">

                <div class="grid grid-cols-2 gap-4 mb-4">
                    <div>
                        <label class="block text-sm font-semibold text-slate-700 mb-2">组名称 *</label>
                        <input type="text" id="groupName" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" required>
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-slate-700 mb-2">组代码 *</label>
                        <input type="text" id="groupCode" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" required>
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-4 mb-4">
                    <div>
                        <label class="block text-sm font-semibold text-slate-700 mb-2">杠杆 *</label>
                        <select id="groupLeverage" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                            <option value="50">1:50</option>
                            <option value="100">1:100</option>
                            <option value="200">1:200</option>
                            <option value="400">1:400</option>
                            <option value="500">1:500</option>
                            <option value="1000">1:1000</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-slate-700 mb-2">基础货币</label>
                        <select id="groupCurrency" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                            <option value="USD">USD</option>
                            <option value="EUR">EUR</option>
                            <option value="GBP">GBP</option>
                            <option value="CNY">CNY</option>
                        </select>
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-4 mb-4">
                    <div>
                        <label class="block text-sm font-semibold text-slate-700 mb-2">最小入金</label>
                        <input type="number" id="groupMinDeposit" step="0.01" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-slate-700 mb-2">最小手数</label>
                        <input type="number" id="groupMinLot" step="0.01" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-4 mb-4">
                    <div>
                        <label class="block text-sm font-semibold text-slate-700 mb-2">最大手数</label>
                        <input type="number" id="groupMaxLot" step="0.01" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-slate-700 mb-2">爆仓比例 (%)</label>
                        <input type="number" id="groupMarginLevel" step="1" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>
                </div>

                <div class="mb-4">
                    <label class="flex items-center gap-2">
                        <input type="checkbox" id="groupIsDefault" class="w-4 h-4 text-blue-600 rounded focus:ring-2 focus:ring-blue-500">
                        <span class="text-sm font-semibold text-slate-700">设为默认组</span>
                    </label>
                </div>

                <div class="mb-4">
                    <label class="flex items-center gap-2">
                        <input type="checkbox" id="groupIsActive" class="w-4 h-4 text-blue-600 rounded focus:ring-2 focus:ring-blue-500" checked>
                        <span class="text-sm font-semibold text-slate-700">启用此组</span>
                    </label>
                </div>

                <div class="mb-6">
                    <label class="block text-sm font-semibold text-slate-700 mb-2">组说明</label>
                    <textarea id="groupDescription" rows="3" placeholder="选填：描述此组的特点和适用人群" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"></textarea>
                </div>

                <div class="flex gap-3">
                    <button type="button" onclick="closeEditModal()" class="flex-1 px-6 py-3 border border-slate-300 text-slate-700 font-semibold rounded-lg hover:bg-slate-50 transition">
                        取消
                    </button>
                    <button type="submit" class="flex-1 px-6 py-3 bg-gradient-to-r from-blue-500 to-indigo-600 text-white font-semibold rounded-lg hover:shadow-lg transition">
                        保存
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    loadStats();
    loadGroups();

    document.getElementById('groupForm').addEventListener('submit', function(e) {
        e.preventDefault();
        saveGroup();
    });
});

function loadStats() {
    fetch('{{ route("admin_api_group_configs_stats") }}', {
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(res => res.json())
    .then(data => {
        if (data.success || data.code === 200) {
            document.getElementById('totalGroups').textContent = data.totalGroups || 0;
            document.getElementById('activeGroups').textContent = data.activeGroups || 0;
            document.getElementById('totalUsers').textContent = data.totalUsers || 0;
            document.getElementById('defaultGroup').textContent = data.defaultGroup || '-';
        }
    })
    .catch(err => console.error('Load stats error:', err));
}

function loadGroups() {
    const keyword = document.getElementById('searchKeyword').value;
    const status = document.getElementById('filterStatus').value;
    const leverage = document.getElementById('filterLeverage').value;

    const params = new URLSearchParams({
        keyword: keyword,
        status: status,
        leverage: leverage
    });

    fetch(`{{ route('admin_api_group_configs_list') }}?${params}`, {
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(res => res.json())
    .then(data => {
        if (data.success || data.code === 200) {
            renderGroups(data.groups || []);
        } else {
            showError(data.message || '加载失败');
        }
    })
    .catch(err => {
        console.error('Load error:', err);
        showError('网络错误，请稍后重试');
    });
}

function renderGroups(groups) {
    const grid = document.getElementById('groupsGrid');

    if (groups.length === 0) {
        grid.innerHTML = `
            <div class="col-span-full bg-white rounded-xl shadow-lg p-12 text-center">
                <i class="fas fa-inbox text-5xl text-slate-300 mb-4"></i>
                <p class="text-slate-600">暂无组配置</p>
            </div>
        `;
        return;
    }

    grid.innerHTML = groups.map(g => `
        <div class="bg-white rounded-xl shadow-lg overflow-hidden hover:shadow-xl transition">
            <div class="bg-gradient-to-r from-blue-500 to-indigo-600 px-6 py-4">
                <div class="flex items-center justify-between mb-2">
                    <h3 class="text-xl font-bold text-white">${g.name || 'N/A'}</h3>
                    ${g.is_default ? '<span class="px-2 py-1 bg-yellow-400 text-yellow-900 text-xs font-semibold rounded-full">默认</span>' : ''}
                </div>
                <p class="text-sm text-blue-100 font-mono">${g.code || '-'}</p>
            </div>

            <div class="p-6">
                <div class="grid grid-cols-2 gap-4 mb-4">
                    <div>
                        <p class="text-xs text-slate-500 mb-1">杠杆</p>
                        <p class="text-lg font-bold text-slate-800">1:${g.leverage || 100}</p>
                    </div>
                    <div>
                        <p class="text-xs text-slate-500 mb-1">货币</p>
                        <p class="text-lg font-bold text-slate-800">${g.currency || 'USD'}</p>
                    </div>
                    <div>
                        <p class="text-xs text-slate-500 mb-1">最小入金</p>
                        <p class="text-sm font-semibold text-slate-800">$${formatNumber(g.min_deposit || 0)}</p>
                    </div>
                    <div>
                        <p class="text-xs text-slate-500 mb-1">爆仓比例</p>
                        <p class="text-sm font-semibold text-slate-800">${g.margin_level || 0}%</p>
                    </div>
                    <div>
                        <p class="text-xs text-slate-500 mb-1">手数范围</p>
                        <p class="text-sm text-slate-800">${g.min_lot || 0.01} - ${g.max_lot || 100}</p>
                    </div>
                    <div>
                        <p class="text-xs text-slate-500 mb-1">用户数</p>
                        <p class="text-sm font-semibold text-purple-600">${g.user_count || 0}</p>
                    </div>
                </div>

                ${g.description ? `<p class="text-sm text-slate-600 mb-4 line-clamp-2">${g.description}</p>` : ''}

                <div class="flex items-center justify-between pt-4 border-t border-slate-200">
                    <div>
                        ${g.is_active
                            ? '<span class="px-2 py-1 bg-green-100 text-green-700 text-xs font-semibold rounded-full">启用</span>'
                            : '<span class="px-2 py-1 bg-red-100 text-red-700 text-xs font-semibold rounded-full">禁用</span>'
                        }
                    </div>
                    <div class="flex gap-2">
                        <button onclick="editGroup(${g.id})" class="px-3 py-1 bg-blue-100 text-blue-700 text-xs font-semibold rounded hover:bg-blue-200 transition">
                            <i class="fas fa-edit mr-1"></i>编辑
                        </button>
                        <button onclick="deleteGroup(${g.id})" class="px-3 py-1 bg-red-100 text-red-700 text-xs font-semibold rounded hover:bg-red-200 transition">
                            <i class="fas fa-trash mr-1"></i>删除
                        </button>
                    </div>
                </div>
            </div>
        </div>
    `).join('');
}

function addGroup() {
    document.getElementById('modalTitle').textContent = '新增组配置';
    document.getElementById('groupId').value = '';
    document.getElementById('groupName').value = '';
    document.getElementById('groupCode').value = '';
    document.getElementById('groupLeverage').value = '100';
    document.getElementById('groupCurrency').value = 'USD';
    document.getElementById('groupMinDeposit').value = '100';
    document.getElementById('groupMinLot').value = '0.01';
    document.getElementById('groupMaxLot').value = '100';
    document.getElementById('groupMarginLevel').value = '30';
    document.getElementById('groupIsDefault').checked = false;
    document.getElementById('groupIsActive').checked = true;
    document.getElementById('groupDescription').value = '';
    document.getElementById('groupCode').disabled = false;
    document.getElementById('editModal').classList.remove('hidden');
}

function editGroup(id) {
    fetch(`{{ route('admin_api_group_configs_detail', ['id' => '__ID__']) }}`.replace('__ID__', id), {
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(res => res.json())
    .then(data => {
        if (data.success && data.group) {
            const g = data.group;
            document.getElementById('modalTitle').textContent = '编辑组配置';
            document.getElementById('groupId').value = g.id;
            document.getElementById('groupName').value = g.name;
            document.getElementById('groupCode').value = g.code;
            document.getElementById('groupLeverage').value = g.leverage;
            document.getElementById('groupCurrency').value = g.currency;
            document.getElementById('groupMinDeposit').value = g.min_deposit;
            document.getElementById('groupMinLot').value = g.min_lot;
            document.getElementById('groupMaxLot').value = g.max_lot;
            document.getElementById('groupMarginLevel').value = g.margin_level;
            document.getElementById('groupIsDefault').checked = g.is_default;
            document.getElementById('groupIsActive').checked = g.is_active;
            document.getElementById('groupDescription').value = g.description || '';
            document.getElementById('groupCode').disabled = true;
            document.getElementById('editModal').classList.remove('hidden');
        }
    })
    .catch(err => console.error('Load group error:', err));
}

function saveGroup() {
    const id = document.getElementById('groupId').value;
    const data = {
        name: document.getElementById('groupName').value,
        code: document.getElementById('groupCode').value,
        leverage: document.getElementById('groupLeverage').value,
        currency: document.getElementById('groupCurrency').value,
        min_deposit: document.getElementById('groupMinDeposit').value,
        min_lot: document.getElementById('groupMinLot').value,
        max_lot: document.getElementById('groupMaxLot').value,
        margin_level: document.getElementById('groupMarginLevel').value,
        is_default: document.getElementById('groupIsDefault').checked,
        is_active: document.getElementById('groupIsActive').checked,
        description: document.getElementById('groupDescription').value
    };

    const url = id
        ? `{{ route('admin_api_group_configs_update', ['id' => '__ID__']) }}`.replace('__ID__', id)
        : '{{ route("admin_api_group_configs_create") }}';

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
            alert(id ? '组配置更新成功' : '组配置创建成功');
            closeEditModal();
            loadStats();
            loadGroups();
        } else {
            alert(data.message || '保存失败');
        }
    })
    .catch(err => {
        console.error('Save error:', err);
        alert('网络错误，请稍后重试');
    });
}

function deleteGroup(id) {
    if (!confirm('确定要删除此组配置吗？此操作不可恢复。')) {
        return;
    }

    fetch(`{{ route('admin_api_group_configs_delete', ['id' => '__ID__']) }}`.replace('__ID__', id), {
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
            alert('组配置删除成功');
            loadStats();
            loadGroups();
        } else {
            alert(data.message || '删除失败');
        }
    })
    .catch(err => {
        console.error('Delete error:', err);
        alert('网络错误，请稍后重试');
    });
}

function closeEditModal() {
    document.getElementById('editModal').classList.add('hidden');
}

function searchGroups() {
    loadGroups();
}

function refreshData() {
    loadStats();
    loadGroups();
}

function formatNumber(num) {
    return new Intl.NumberFormat('en-US', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
    }).format(num);
}

function showError(message) {
    document.getElementById('groupsGrid').innerHTML = `
        <div class="col-span-full bg-white rounded-xl shadow-lg p-12 text-center">
            <i class="fas fa-exclamation-circle text-5xl text-red-500 mb-4"></i>
            <p class="text-red-600">${message}</p>
        </div>
    `;
}
</script>
@endsection
