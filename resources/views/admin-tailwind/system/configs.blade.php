@extends('admin-tailwind.layouts.app')

@section('title', '系统配置 - 管理后台')

@section('content')
<!-- Page Header -->
<div class="flex items-center justify-between mb-8">
    <div>
        <h1 class="text-3xl font-bold text-slate-800">系统配置</h1>
        <p class="text-slate-600 mt-2">管理系统的全局配置参数</p>
    </div>
    <div class="flex gap-3">
        <button onclick="refreshConfigs()" class="px-6 py-3 bg-gradient-to-r from-blue-500 to-indigo-600 text-white font-semibold rounded-lg hover:shadow-lg transition">
            <i class="fas fa-sync-alt mr-2"></i>刷新配置
        </button>
        <button onclick="addConfig()" class="px-6 py-3 bg-gradient-to-r from-green-500 to-emerald-600 text-white font-semibold rounded-lg hover:shadow-lg transition">
            <i class="fas fa-plus mr-2"></i>新增配置
        </button>
    </div>
</div>

<!-- Config Tabs -->
<div class="bg-white rounded-xl shadow-lg mb-6">
    <div class="flex border-b border-slate-200 overflow-x-auto">
        <button onclick="switchTab('general')" id="tab-general" class="tab-btn px-6 py-4 text-sm font-semibold whitespace-nowrap border-b-2 border-blue-500 text-blue-600">
            <i class="fas fa-cog mr-2"></i>基础配置
        </button>
        <button onclick="switchTab('trading')" id="tab-trading" class="tab-btn px-6 py-4 text-sm font-semibold text-slate-600 hover:text-slate-800 whitespace-nowrap border-b-2 border-transparent">
            <i class="fas fa-chart-line mr-2"></i>交易配置
        </button>
        <button onclick="switchTab('payment')" id="tab-payment" class="tab-btn px-6 py-4 text-sm font-semibold text-slate-600 hover:text-slate-800 whitespace-nowrap border-b-2 border-transparent">
            <i class="fas fa-credit-card mr-2"></i>支付配置
        </button>
        <button onclick="switchTab('commission')" id="tab-commission" class="tab-btn px-6 py-4 text-sm font-semibold text-slate-600 hover:text-slate-800 whitespace-nowrap border-b-2 border-transparent">
            <i class="fas fa-percent mr-2"></i>佣金配置
        </button>
        <button onclick="switchTab('notification')" id="tab-notification" class="tab-btn px-6 py-4 text-sm font-semibold text-slate-600 hover:text-slate-800 whitespace-nowrap border-b-2 border-transparent">
            <i class="fas fa-bell mr-2"></i>通知配置
        </button>
        <button onclick="switchTab('security')" id="tab-security" class="tab-btn px-6 py-4 text-sm font-semibold text-slate-600 hover:text-slate-800 whitespace-nowrap border-b-2 border-transparent">
            <i class="fas fa-shield-alt mr-2"></i>安全配置
        </button>
    </div>
</div>

<!-- Config Content -->
<div id="configContent" class="space-y-6">
    <div class="text-center py-12">
        <i class="fas fa-spinner fa-spin text-3xl text-slate-500 mb-3"></i>
        <p class="text-slate-600">加载中...</p>
    </div>
</div>

<!-- Edit Modal -->
<div id="editModal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl max-w-2xl w-full">
        <div class="bg-gradient-to-r from-blue-500 to-indigo-600 px-6 py-4 flex items-center justify-between rounded-t-2xl">
            <h3 class="text-xl font-bold text-white" id="modalTitle">编辑配置</h3>
            <button onclick="closeEditModal()" class="text-white hover:text-slate-200">
                <i class="fas fa-times text-xl"></i>
            </button>
        </div>
        <div class="p-6">
            <form id="configForm">
                <input type="hidden" id="configId">

                <div class="mb-4">
                    <label class="block text-sm font-semibold text-slate-700 mb-2">配置键名</label>
                    <input type="text" id="configKey" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" required>
                </div>

                <div class="mb-4">
                    <label class="block text-sm font-semibold text-slate-700 mb-2">配置名称</label>
                    <input type="text" id="configName" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" required>
                </div>

                <div class="mb-4">
                    <label class="block text-sm font-semibold text-slate-700 mb-2">配置值</label>
                    <textarea id="configValue" rows="3" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" required></textarea>
                </div>

                <div class="mb-4">
                    <label class="block text-sm font-semibold text-slate-700 mb-2">配置分组</label>
                    <select id="configGroup" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                        <option value="general">基础配置</option>
                        <option value="trading">交易配置</option>
                        <option value="payment">支付配置</option>
                        <option value="commission">佣金配置</option>
                        <option value="notification">通知配置</option>
                        <option value="security">安全配置</option>
                    </select>
                </div>

                <div class="mb-4">
                    <label class="block text-sm font-semibold text-slate-700 mb-2">数据类型</label>
                    <select id="configType" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                        <option value="string">字符串</option>
                        <option value="number">数字</option>
                        <option value="boolean">布尔值</option>
                        <option value="json">JSON</option>
                    </select>
                </div>

                <div class="mb-6">
                    <label class="block text-sm font-semibold text-slate-700 mb-2">配置说明</label>
                    <textarea id="configDescription" rows="2" placeholder="选填：描述此配置的用途和注意事项" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"></textarea>
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
let currentTab = 'general';

document.addEventListener('DOMContentLoaded', function() {
    loadConfigs('general');

    document.getElementById('configForm').addEventListener('submit', function(e) {
        e.preventDefault();
        saveConfig();
    });
});

function switchTab(tab) {
    currentTab = tab;

    // Update tab styles
    document.querySelectorAll('.tab-btn').forEach(btn => {
        btn.classList.remove('border-blue-500', 'text-blue-600');
        btn.classList.add('border-transparent', 'text-slate-600');
    });

    const activeTab = document.getElementById(`tab-${tab}`);
    activeTab.classList.remove('border-transparent', 'text-slate-600');
    activeTab.classList.add('border-blue-500', 'text-blue-600');

    loadConfigs(tab);
}

function loadConfigs(group) {
    const content = document.getElementById('configContent');
    content.innerHTML = '<div class="text-center py-12"><i class="fas fa-spinner fa-spin text-3xl text-slate-500 mb-3"></i><p class="text-slate-600">加载中...</p></div>';

    fetch(`{{ route('admin_api_system_configs_list') }}?group=${group}`, {
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(res => res.json())
    .then(data => {
        if (data.success || data.code === 200) {
            renderConfigs(data.configs || []);
        } else {
            showError(data.message || '加载失败');
        }
    })
    .catch(err => {
        console.error('Load error:', err);
        showError('网络错误，请稍后重试');
    });
}

function renderConfigs(configs) {
    const content = document.getElementById('configContent');

    if (configs.length === 0) {
        content.innerHTML = `
            <div class="bg-white rounded-xl shadow-lg p-12 text-center">
                <i class="fas fa-inbox text-5xl text-slate-300 mb-4"></i>
                <p class="text-slate-600">暂无配置项</p>
            </div>
        `;
        return;
    }

    content.innerHTML = configs.map(c => `
        <div class="bg-white rounded-xl shadow-lg p-6 hover:shadow-xl transition">
            <div class="flex items-start justify-between mb-4">
                <div class="flex-1">
                    <div class="flex items-center gap-3 mb-2">
                        <h3 class="text-lg font-bold text-slate-800">${c.name || 'N/A'}</h3>
                        ${getTypeBadge(c.type)}
                    </div>
                    <p class="text-sm text-slate-500 font-mono mb-2">${c.key || '-'}</p>
                    ${c.description ? `<p class="text-sm text-slate-600">${c.description}</p>` : ''}
                </div>
                <div class="flex gap-2">
                    <button onclick="editConfig(${c.id})" class="px-4 py-2 bg-blue-100 text-blue-700 text-sm font-semibold rounded-lg hover:bg-blue-200 transition">
                        <i class="fas fa-edit mr-1"></i>编辑
                    </button>
                    <button onclick="deleteConfig(${c.id})" class="px-4 py-2 bg-red-100 text-red-700 text-sm font-semibold rounded-lg hover:bg-red-200 transition">
                        <i class="fas fa-trash mr-1"></i>删除
                    </button>
                </div>
            </div>

            <div class="bg-slate-50 rounded-lg p-4">
                <p class="text-xs text-slate-500 mb-1">当前值</p>
                <div class="text-base font-mono text-slate-800 break-all">${formatValue(c.value, c.type)}</div>
            </div>

            <div class="mt-4 flex items-center justify-between text-xs text-slate-500">
                <span>更新时间: ${c.updated_at || '-'}</span>
                <span>更新人: ${c.updated_by || 'System'}</span>
            </div>
        </div>
    `).join('');
}

function getTypeBadge(type) {
    const badges = {
        'string': '<span class="px-2 py-1 bg-blue-100 text-blue-700 text-xs font-semibold rounded-full">字符串</span>',
        'number': '<span class="px-2 py-1 bg-green-100 text-green-700 text-xs font-semibold rounded-full">数字</span>',
        'boolean': '<span class="px-2 py-1 bg-purple-100 text-purple-700 text-xs font-semibold rounded-full">布尔值</span>',
        'json': '<span class="px-2 py-1 bg-orange-100 text-orange-700 text-xs font-semibold rounded-full">JSON</span>'
    };
    return badges[type] || '<span class="px-2 py-1 bg-slate-100 text-slate-700 text-xs font-semibold rounded-full">其他</span>';
}

function formatValue(value, type) {
    if (type === 'json') {
        try {
            return JSON.stringify(JSON.parse(value), null, 2);
        } catch {
            return value;
        }
    }
    return value;
}

function addConfig() {
    document.getElementById('modalTitle').textContent = '新增配置';
    document.getElementById('configId').value = '';
    document.getElementById('configKey').value = '';
    document.getElementById('configName').value = '';
    document.getElementById('configValue').value = '';
    document.getElementById('configGroup').value = currentTab;
    document.getElementById('configType').value = 'string';
    document.getElementById('configDescription').value = '';
    document.getElementById('configKey').disabled = false;
    document.getElementById('editModal').classList.remove('hidden');
}

function editConfig(id) {
    fetch(`{{ route('admin_api_system_configs_detail', ['id' => '__ID__']) }}`.replace('__ID__', id), {
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(res => res.json())
    .then(data => {
        if (data.success && data.config) {
            const c = data.config;
            document.getElementById('modalTitle').textContent = '编辑配置';
            document.getElementById('configId').value = c.id;
            document.getElementById('configKey').value = c.key;
            document.getElementById('configName').value = c.name;
            document.getElementById('configValue').value = c.value;
            document.getElementById('configGroup').value = c.group;
            document.getElementById('configType').value = c.type;
            document.getElementById('configDescription').value = c.description || '';
            document.getElementById('configKey').disabled = true;
            document.getElementById('editModal').classList.remove('hidden');
        }
    })
    .catch(err => console.error('Load config error:', err));
}

function saveConfig() {
    const id = document.getElementById('configId').value;
    const data = {
        key: document.getElementById('configKey').value,
        name: document.getElementById('configName').value,
        value: document.getElementById('configValue').value,
        group: document.getElementById('configGroup').value,
        type: document.getElementById('configType').value,
        description: document.getElementById('configDescription').value
    };

    const url = id
        ? `{{ route('admin_api_system_configs_update', ['id' => '__ID__']) }}`.replace('__ID__', id)
        : '{{ route("admin_api_system_configs_create") }}';

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
            alert(id ? '配置更新成功' : '配置创建成功');
            closeEditModal();
            loadConfigs(currentTab);
        } else {
            alert(data.message || '保存失败');
        }
    })
    .catch(err => {
        console.error('Save error:', err);
        alert('网络错误，请稍后重试');
    });
}

function deleteConfig(id) {
    if (!confirm('确定要删除此配置吗？此操作不可恢复。')) {
        return;
    }

    fetch(`{{ route('admin_api_system_configs_delete', ['id' => '__ID__']) }}`.replace('__ID__', id), {
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
            alert('配置删除成功');
            loadConfigs(currentTab);
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

function refreshConfigs() {
    loadConfigs(currentTab);
}

function showError(message) {
    document.getElementById('configContent').innerHTML = `
        <div class="bg-white rounded-xl shadow-lg p-12 text-center">
            <i class="fas fa-exclamation-circle text-5xl text-red-500 mb-4"></i>
            <p class="text-red-600">${message}</p>
        </div>
    `;
}
</script>
@endsection
