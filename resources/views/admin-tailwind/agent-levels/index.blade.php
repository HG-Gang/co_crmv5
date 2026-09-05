@extends('admin-tailwind.layouts.app')

@section('title', '代理等级 - 管理后台')

@section('content')
<!-- Page Header -->
<div class="flex items-center justify-between mb-8">
    <div>
        <h1 class="text-3xl font-bold text-slate-800">代理等级</h1>
        <p class="text-slate-600 mt-2">管理代理等级体系和佣金规则</p>
    </div>
    <button onclick="openAddModal()" class="px-6 py-3 bg-gradient-to-r from-indigo-500 to-purple-600 text-white font-semibold rounded-lg hover:shadow-lg transition">
        <i class="fas fa-plus mr-2"></i>添加等级
    </button>
</div>

<!-- Stats Cards -->
<div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-6">
    <div class="bg-white rounded-xl shadow-lg p-6">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-slate-600 text-sm font-semibold">总等级数</p>
                <h3 class="text-2xl font-bold text-slate-800 mt-2" id="totalLevels">0</h3>
            </div>
            <div class="w-12 h-12 bg-gradient-to-br from-indigo-500 to-purple-600 rounded-full flex items-center justify-center text-white">
                <i class="fas fa-layer-group"></i>
            </div>
        </div>
    </div>

    <div class="bg-white rounded-xl shadow-lg p-6">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-slate-600 text-sm font-semibold">启用等级</p>
                <h3 class="text-2xl font-bold text-slate-800 mt-2" id="activeLevels">0</h3>
            </div>
            <div class="w-12 h-12 bg-gradient-to-br from-green-500 to-emerald-600 rounded-full flex items-center justify-center text-white">
                <i class="fas fa-check-circle"></i>
            </div>
        </div>
    </div>

    <div class="bg-white rounded-xl shadow-lg p-6">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-slate-600 text-sm font-semibold">总代理数</p>
                <h3 class="text-2xl font-bold text-slate-800 mt-2" id="totalAgents">0</h3>
            </div>
            <div class="w-12 h-12 bg-gradient-to-br from-blue-500 to-cyan-600 rounded-full flex items-center justify-center text-white">
                <i class="fas fa-users"></i>
            </div>
        </div>
    </div>

    <div class="bg-white rounded-xl shadow-lg p-6">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-slate-600 text-sm font-semibold">平均佣金率</p>
                <h3 class="text-2xl font-bold text-slate-800 mt-2" id="avgCommission">0%</h3>
            </div>
            <div class="w-12 h-12 bg-gradient-to-br from-orange-500 to-red-600 rounded-full flex items-center justify-center text-white">
                <i class="fas fa-percentage"></i>
            </div>
        </div>
    </div>
</div>

<!-- Agent Levels Grid -->
<div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-6" id="levelsGrid">
    <div class="col-span-full flex items-center justify-center py-12 text-slate-400">
        <i class="fas fa-spinner fa-spin mr-2"></i>加载中...
    </div>
</div>

<!-- Add/Edit Modal -->
<div id="levelModal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-xl shadow-2xl max-w-2xl w-full max-h-[90vh] overflow-y-auto">
        <div class="sticky top-0 bg-white border-b border-slate-200 px-6 py-4 flex items-center justify-between">
            <h3 class="text-xl font-bold text-slate-800">
                <i class="fas fa-layer-group text-indigo-600 mr-2"></i><span id="modalTitle">添加等级</span>
            </h3>
            <button onclick="closeLevelModal()" class="text-slate-400 hover:text-slate-600">
                <i class="fas fa-times text-xl"></i>
            </button>
        </div>
        <div class="p-6">
            <input type="hidden" id="levelId">

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                <div>
                    <label class="block text-sm font-semibold text-slate-700 mb-2">等级名称 <span class="text-red-500">*</span></label>
                    <input type="text" id="levelName" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500" placeholder="例如：一级代理">
                </div>
                <div>
                    <label class="block text-sm font-semibold text-slate-700 mb-2">等级编号 <span class="text-red-500">*</span></label>
                    <input type="number" id="levelCode" min="1" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500" placeholder="例如：1">
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                <div>
                    <label class="block text-sm font-semibold text-slate-700 mb-2">佣金比例 (%) <span class="text-red-500">*</span></label>
                    <input type="number" id="levelCommissionRate" min="0" max="100" step="0.01" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500" placeholder="例如：5.5">
                </div>
                <div>
                    <label class="block text-sm font-semibold text-slate-700 mb-2">返佣周期</label>
                    <select id="levelRebateCycle" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500">
                        <option value="daily">每日</option>
                        <option value="weekly">每周</option>
                        <option value="monthly" selected>每月</option>
                    </select>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                <div>
                    <label class="block text-sm font-semibold text-slate-700 mb-2">最低入金要求 ($)</label>
                    <input type="number" id="levelMinDeposit" min="0" step="100" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500" placeholder="例如：1000">
                </div>
                <div>
                    <label class="block text-sm font-semibold text-slate-700 mb-2">最低客户数</label>
                    <input type="number" id="levelMinCustomers" min="0" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500" placeholder="例如：10">
                </div>
            </div>

            <div class="mb-4">
                <label class="block text-sm font-semibold text-slate-700 mb-2">权限范围</label>
                <div class="grid grid-cols-2 gap-3">
                    <label class="flex items-center gap-2 p-3 border border-slate-300 rounded-lg cursor-pointer hover:bg-indigo-50">
                        <input type="checkbox" id="permCanDevelop" class="w-4 h-4 text-indigo-600 rounded">
                        <span class="text-sm text-slate-700">可发展下级</span>
                    </label>
                    <label class="flex items-center gap-2 p-3 border border-slate-300 rounded-lg cursor-pointer hover:bg-indigo-50">
                        <input type="checkbox" id="permCanWithdraw" class="w-4 h-4 text-indigo-600 rounded">
                        <span class="text-sm text-slate-700">可申请出金</span>
                    </label>
                    <label class="flex items-center gap-2 p-3 border border-slate-300 rounded-lg cursor-pointer hover:bg-indigo-50">
                        <input type="checkbox" id="permCanViewReport" class="w-4 h-4 text-indigo-600 rounded">
                        <span class="text-sm text-slate-700">查看报表</span>
                    </label>
                    <label class="flex items-center gap-2 p-3 border border-slate-300 rounded-lg cursor-pointer hover:bg-indigo-50">
                        <input type="checkbox" id="permCanManageCustomer" class="w-4 h-4 text-indigo-600 rounded">
                        <span class="text-sm text-slate-700">管理客户</span>
                    </label>
                </div>
            </div>

            <div class="mb-4">
                <label class="block text-sm font-semibold text-slate-700 mb-2">等级描述</label>
                <textarea id="levelDescription" rows="3" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500" placeholder="描述该等级的特点和权益"></textarea>
            </div>

            <div class="flex items-center gap-4 mb-4">
                <label class="flex items-center gap-2 cursor-pointer">
                    <input type="checkbox" id="levelStatus" checked class="w-4 h-4 text-indigo-600 rounded">
                    <span class="text-sm font-semibold text-slate-700">启用该等级</span>
                </label>
            </div>

            <div class="bg-indigo-50 border border-indigo-200 rounded-lg p-4">
                <div class="flex items-start gap-2">
                    <i class="fas fa-info-circle text-indigo-600 mt-1"></i>
                    <div class="text-sm text-indigo-800">
                        <p class="font-semibold mb-1">提示</p>
                        <ul class="list-disc list-inside space-y-1 text-xs">
                            <li>等级编号越小，等级越高，权限越大</li>
                            <li>佣金比例影响代理的收益结算</li>
                            <li>权限范围决定代理可以执行的操作</li>
                            <li>修改等级配置会影响所有该等级的代理</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
        <div class="sticky bottom-0 bg-slate-50 px-6 py-4 flex justify-end gap-3 border-t border-slate-200">
            <button onclick="closeLevelModal()" class="px-6 py-2 bg-slate-200 text-slate-700 font-semibold rounded-lg hover:bg-slate-300 transition">
                取消
            </button>
            <button onclick="saveLevel()" class="px-6 py-2 bg-gradient-to-r from-indigo-500 to-purple-600 text-white font-semibold rounded-lg hover:shadow-lg transition">
                <i class="fas fa-save mr-2"></i>保存
            </button>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    loadStats();
    loadLevels();
});

function loadStats() {
    fetch('{{ route("admin_api_agent_levels_stats") }}', {
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(res => res.json())
    .then(data => {
        if (data.success && data.stats) {
            document.getElementById('totalLevels').textContent = data.stats.total || 0;
            document.getElementById('activeLevels').textContent = data.stats.active || 0;
            document.getElementById('totalAgents').textContent = data.stats.agents || 0;
            document.getElementById('avgCommission').textContent = (data.stats.avg_commission || 0) + '%';
        }
    })
    .catch(err => console.error('Load stats error:', err));
}

function loadLevels() {
    fetch('{{ route("admin_api_agent_levels_list") }}', {
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(res => res.json())
    .then(data => {
        if (data.success && data.levels) {
            renderLevels(data.levels);
        }
    })
    .catch(err => {
        console.error('Load levels error:', err);
        document.getElementById('levelsGrid').innerHTML = `
            <div class="col-span-full flex items-center justify-center py-12 text-slate-400">
                <i class="fas fa-exclamation-circle mr-2"></i>加载失败
            </div>
        `;
    });
}

function renderLevels(levels) {
    if (levels.length === 0) {
        document.getElementById('levelsGrid').innerHTML = `
            <div class="col-span-full flex items-center justify-center py-12 text-slate-400">
                <i class="fas fa-inbox mr-2"></i>暂无数据
            </div>
        `;
        return;
    }

    const html = levels.map(l => `
        <div class="bg-white rounded-xl shadow-lg p-6 hover:shadow-xl transition">
            <div class="flex items-center justify-between mb-4">
                <div class="flex items-center gap-3">
                    <div class="w-12 h-12 ${getLevelGradient(l.code)} rounded-full flex items-center justify-center text-white font-bold text-lg">
                        ${l.code}
                    </div>
                    <div>
                        <h3 class="text-lg font-bold text-slate-800">${l.name}</h3>
                        <p class="text-xs text-slate-500">${l.description || '-'}</p>
                    </div>
                </div>
                ${getStatusBadge(l.status)}
            </div>

            <div class="space-y-3 mb-4">
                <div class="flex items-center justify-between py-2 border-b border-slate-100">
                    <span class="text-sm text-slate-600"><i class="fas fa-percentage text-slate-400 mr-2"></i>佣金比例</span>
                    <span class="text-sm font-semibold text-indigo-600">${l.commission_rate}%</span>
                </div>
                <div class="flex items-center justify-between py-2 border-b border-slate-100">
                    <span class="text-sm text-slate-600"><i class="fas fa-clock text-slate-400 mr-2"></i>返佣周期</span>
                    <span class="text-sm font-semibold text-slate-800">${getRebateCycleText(l.rebate_cycle)}</span>
                </div>
                <div class="flex items-center justify-between py-2 border-b border-slate-100">
                    <span class="text-sm text-slate-600"><i class="fas fa-users text-slate-400 mr-2"></i>代理数量</span>
                    <span class="text-sm font-semibold text-slate-800">${l.agents_count || 0}</span>
                </div>
            </div>

            ${l.min_deposit || l.min_customers ? `
                <div class="bg-slate-50 rounded-lg p-3 mb-4">
                    <p class="text-xs text-slate-600 font-semibold mb-2">晋升条件</p>
                    ${l.min_deposit ? `<p class="text-xs text-slate-600">• 最低入金: $${l.min_deposit}</p>` : ''}
                    ${l.min_customers ? `<p class="text-xs text-slate-600">• 最低客户: ${l.min_customers}人</p>` : ''}
                </div>
            ` : ''}

            <div class="flex items-center gap-2 mb-4 flex-wrap">
                ${l.permissions && l.permissions.can_develop ? '<span class="px-2 py-1 bg-green-100 text-green-700 text-xs rounded-full">可发展下级</span>' : ''}
                ${l.permissions && l.permissions.can_withdraw ? '<span class="px-2 py-1 bg-blue-100 text-blue-700 text-xs rounded-full">可申请出金</span>' : ''}
                ${l.permissions && l.permissions.can_view_report ? '<span class="px-2 py-1 bg-purple-100 text-purple-700 text-xs rounded-full">查看报表</span>' : ''}
                ${l.permissions && l.permissions.can_manage_customer ? '<span class="px-2 py-1 bg-orange-100 text-orange-700 text-xs rounded-full">管理客户</span>' : ''}
            </div>

            <div class="flex gap-2">
                <button onclick="editLevel(${l.id})" class="flex-1 px-4 py-2 bg-gradient-to-r from-indigo-500 to-purple-600 text-white font-semibold rounded-lg hover:shadow-lg transition">
                    <i class="fas fa-edit mr-2"></i>编辑
                </button>
                ${l.status === 'active' ? `
                    <button onclick="disableLevel(${l.id})" class="px-4 py-2 bg-orange-50 text-orange-600 font-semibold rounded-lg hover:bg-orange-100 transition">
                        <i class="fas fa-ban"></i>
                    </button>
                ` : `
                    <button onclick="enableLevel(${l.id})" class="px-4 py-2 bg-green-50 text-green-600 font-semibold rounded-lg hover:bg-green-100 transition">
                        <i class="fas fa-check"></i>
                    </button>
                `}
                <button onclick="deleteLevel(${l.id})" class="px-4 py-2 bg-red-50 text-red-600 font-semibold rounded-lg hover:bg-red-100 transition">
                    <i class="fas fa-trash"></i>
                </button>
            </div>
        </div>
    `).join('');

    document.getElementById('levelsGrid').innerHTML = html;
}

function getLevelGradient(code) {
    const gradients = {
        1: 'bg-gradient-to-br from-yellow-500 to-amber-600',
        2: 'bg-gradient-to-br from-purple-500 to-pink-600',
        3: 'bg-gradient-to-br from-blue-500 to-indigo-600'
    };
    return gradients[code] || 'bg-gradient-to-br from-slate-500 to-slate-600';
}

function getStatusBadge(status) {
    const badges = {
        'active': '<span class="px-2 py-1 bg-green-100 text-green-700 text-xs font-semibold rounded-full"><i class="fas fa-check-circle mr-1"></i>启用</span>',
        'disabled': '<span class="px-2 py-1 bg-red-100 text-red-700 text-xs font-semibold rounded-full"><i class="fas fa-ban mr-1"></i>禁用</span>'
    };
    return badges[status] || '<span class="px-2 py-1 bg-slate-100 text-slate-700 text-xs font-semibold rounded-full">未知</span>';
}

function getRebateCycleText(cycle) {
    const texts = {
        'daily': '每日',
        'weekly': '每周',
        'monthly': '每月'
    };
    return texts[cycle] || '-';
}

function openAddModal() {
    document.getElementById('modalTitle').textContent = '添加等级';
    document.getElementById('levelId').value = '';
    document.getElementById('levelName').value = '';
    document.getElementById('levelCode').value = '';
    document.getElementById('levelCommissionRate').value = '';
    document.getElementById('levelRebateCycle').value = 'monthly';
    document.getElementById('levelMinDeposit').value = '';
    document.getElementById('levelMinCustomers').value = '';
    document.getElementById('levelDescription').value = '';
    document.getElementById('levelStatus').checked = true;

    document.getElementById('permCanDevelop').checked = false;
    document.getElementById('permCanWithdraw').checked = false;
    document.getElementById('permCanViewReport').checked = false;
    document.getElementById('permCanManageCustomer').checked = false;

    document.getElementById('levelModal').classList.remove('hidden');
}

function editLevel(id) {
    fetch(`{{ route("admin_api_agent_levels_detail") }}?id=${id}`, {
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(res => res.json())
    .then(data => {
        if (data.success && data.level) {
            const l = data.level;
            document.getElementById('modalTitle').textContent = '编辑等级';
            document.getElementById('levelId').value = l.id;
            document.getElementById('levelName').value = l.name || '';
            document.getElementById('levelCode').value = l.code || '';
            document.getElementById('levelCommissionRate').value = l.commission_rate || '';
            document.getElementById('levelRebateCycle').value = l.rebate_cycle || 'monthly';
            document.getElementById('levelMinDeposit').value = l.min_deposit || '';
            document.getElementById('levelMinCustomers').value = l.min_customers || '';
            document.getElementById('levelDescription').value = l.description || '';
            document.getElementById('levelStatus').checked = l.status === 'active';

            const perms = l.permissions || {};
            document.getElementById('permCanDevelop').checked = perms.can_develop || false;
            document.getElementById('permCanWithdraw').checked = perms.can_withdraw || false;
            document.getElementById('permCanViewReport').checked = perms.can_view_report || false;
            document.getElementById('permCanManageCustomer').checked = perms.can_manage_customer || false;

            document.getElementById('levelModal').classList.remove('hidden');
        }
    })
    .catch(err => console.error('Load level detail error:', err));
}

function closeLevelModal() {
    document.getElementById('levelModal').classList.add('hidden');
}

function saveLevel() {
    const id = document.getElementById('levelId').value;
    const name = document.getElementById('levelName').value.trim();
    const code = document.getElementById('levelCode').value;
    const commissionRate = document.getElementById('levelCommissionRate').value;
    const rebateCycle = document.getElementById('levelRebateCycle').value;
    const minDeposit = document.getElementById('levelMinDeposit').value;
    const minCustomers = document.getElementById('levelMinCustomers').value;
    const description = document.getElementById('levelDescription').value.trim();
    const status = document.getElementById('levelStatus').checked ? 'active' : 'disabled';

    if (!name) {
        alert('请输入等级名称');
        return;
    }

    if (!code) {
        alert('请输入等级编号');
        return;
    }

    if (!commissionRate) {
        alert('请输入佣金比例');
        return;
    }

    const permissions = {
        can_develop: document.getElementById('permCanDevelop').checked,
        can_withdraw: document.getElementById('permCanWithdraw').checked,
        can_view_report: document.getElementById('permCanViewReport').checked,
        can_manage_customer: document.getElementById('permCanManageCustomer').checked
    };

    const data = {
        id: id || undefined,
        name: name,
        code: code,
        commission_rate: commissionRate,
        rebate_cycle: rebateCycle,
        min_deposit: minDeposit || undefined,
        min_customers: minCustomers || undefined,
        description: description,
        status: status,
        permissions: permissions
    };

    fetch('{{ route("admin_api_agent_levels_save") }}', {
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
            closeLevelModal();
            loadStats();
            loadLevels();
        } else {
            alert(data.message || '保存失败');
        }
    })
    .catch(err => {
        console.error('Save level error:', err);
        alert('网络错误，请稍后重试');
    });
}

function disableLevel(id) {
    if (!confirm('确定要禁用该等级吗？禁用后该等级的代理将无法继续使用相关权限。')) return;

    fetch('{{ route("admin_api_agent_levels_disable") }}', {
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
            loadLevels();
        } else {
            alert(data.message || '禁用失败');
        }
    })
    .catch(err => {
        console.error('Disable level error:', err);
        alert('网络错误，请稍后重试');
    });
}

function enableLevel(id) {
    if (!confirm('确定要启用该等级吗？')) return;

    fetch('{{ route("admin_api_agent_levels_enable") }}', {
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
            loadLevels();
        } else {
            alert(data.message || '启用失败');
        }
    })
    .catch(err => {
        console.error('Enable level error:', err);
        alert('网络错误，请稍后重试');
    });
}

function deleteLevel(id) {
    if (!confirm('确定要删除该等级吗？此操作不可恢复！已分配该等级的代理需要重新分配等级。')) return;

    fetch('{{ route("admin_api_agent_levels_delete") }}', {
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
            loadLevels();
        } else {
            alert(data.message || '删除失败');
        }
    })
    .catch(err => {
        console.error('Delete level error:', err);
        alert('网络错误，请稍后重试');
    });
}
</script>
@endsection
