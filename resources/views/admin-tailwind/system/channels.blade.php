@extends('admin-tailwind.layouts.app')

@section('title', '渠道管理 - 管理后台')

@section('content')
<!-- Page Header -->
<div class="flex items-center justify-between mb-8">
    <div>
        <h1 class="text-3xl font-bold text-slate-800">渠道管理</h1>
        <p class="text-slate-600 mt-2">管理支付渠道和入金出金通道</p>
    </div>
    <div class="flex gap-3">
        <button onclick="refreshChannels()" class="px-6 py-3 bg-gradient-to-r from-blue-500 to-indigo-600 text-white font-semibold rounded-lg hover:shadow-lg transition">
            <i class="fas fa-sync-alt mr-2"></i>刷新
        </button>
        <button onclick="addChannel()" class="px-6 py-3 bg-gradient-to-r from-green-500 to-emerald-600 text-white font-semibold rounded-lg hover:shadow-lg transition">
            <i class="fas fa-plus mr-2"></i>新增渠道
        </button>
    </div>
</div>

<!-- Stats Cards -->
<div class="grid grid-cols-1 md:grid-cols-5 gap-6 mb-6">
    <div class="bg-white rounded-xl shadow-lg p-6 border-l-4 border-blue-500">
        <p class="text-sm text-slate-600 mb-2">总渠道数</p>
        <p class="text-3xl font-bold text-slate-800" id="totalChannels">0</p>
    </div>
    <div class="bg-white rounded-xl shadow-lg p-6 border-l-4 border-green-500">
        <p class="text-sm text-slate-600 mb-2">启用中</p>
        <p class="text-3xl font-bold text-green-600" id="activeChannels">0</p>
    </div>
    <div class="bg-white rounded-xl shadow-lg p-6 border-l-4 border-red-500">
        <p class="text-sm text-slate-600 mb-2">已禁用</p>
        <p class="text-3xl font-bold text-red-600" id="inactiveChannels">0</p>
    </div>
    <div class="bg-white rounded-xl shadow-lg p-6 border-l-4 border-purple-500">
        <p class="text-sm text-slate-600 mb-2">入金渠道</p>
        <p class="text-3xl font-bold text-purple-600" id="depositChannels">0</p>
    </div>
    <div class="bg-white rounded-xl shadow-lg p-6 border-l-4 border-orange-500">
        <p class="text-sm text-slate-600 mb-2">出金渠道</p>
        <p class="text-3xl font-bold text-orange-600" id="withdrawChannels">0</p>
    </div>
</div>

<!-- Filter Bar -->
<div class="bg-white rounded-xl shadow-lg p-6 mb-6">
    <div class="grid grid-cols-1 md:grid-cols-5 gap-4">
        <div>
            <label class="block text-sm font-semibold text-slate-700 mb-2">搜索</label>
            <input type="text" id="searchKeyword" placeholder="渠道名称或代码" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
        </div>
        <div>
            <label class="block text-sm font-semibold text-slate-700 mb-2">类型</label>
            <select id="filterType" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                <option value="">全部</option>
                <option value="deposit">入金</option>
                <option value="withdraw">出金</option>
                <option value="both">双向</option>
            </select>
        </div>
        <div>
            <label class="block text-sm font-semibold text-slate-700 mb-2">状态</label>
            <select id="filterStatus" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                <option value="">全部</option>
                <option value="active">启用</option>
                <option value="inactive">禁用</option>
            </select>
        </div>
        <div>
            <label class="block text-sm font-semibold text-slate-700 mb-2">支付方式</label>
            <select id="filterPaymentType" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                <option value="">全部</option>
                <option value="bank">银行转账</option>
                <option value="alipay">支付宝</option>
                <option value="wechat">微信支付</option>
                <option value="crypto">加密货币</option>
                <option value="card">信用卡</option>
            </select>
        </div>
        <div class="flex items-end">
            <button onclick="searchChannels()" class="w-full px-6 py-2 bg-gradient-to-r from-blue-500 to-indigo-600 text-white font-semibold rounded-lg hover:shadow-lg transition">
                <i class="fas fa-search mr-2"></i>搜索
            </button>
        </div>
    </div>
</div>

<!-- Channels Grid -->
<div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-6" id="channelsGrid">
    <div class="col-span-full text-center py-12">
        <i class="fas fa-spinner fa-spin text-3xl text-slate-500 mb-3"></i>
        <p class="text-slate-600">加载中...</p>
    </div>
</div>

<!-- Edit Modal -->
<div id="editModal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl max-w-3xl w-full max-h-[90vh] overflow-y-auto">
        <div class="sticky top-0 bg-gradient-to-r from-blue-500 to-indigo-600 px-6 py-4 flex items-center justify-between">
            <h3 class="text-xl font-bold text-white" id="modalTitle">编辑渠道</h3>
            <button onclick="closeEditModal()" class="text-white hover:text-slate-200">
                <i class="fas fa-times text-xl"></i>
            </button>
        </div>
        <div class="p-6">
            <form id="channelForm">
                <input type="hidden" id="channelId">

                <div class="grid grid-cols-2 gap-4 mb-4">
                    <div>
                        <label class="block text-sm font-semibold text-slate-700 mb-2">渠道代码 *</label>
                        <input type="text" id="channelCode" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" required>
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-slate-700 mb-2">渠道名称 *</label>
                        <input type="text" id="channelName" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" required>
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-4 mb-4">
                    <div>
                        <label class="block text-sm font-semibold text-slate-700 mb-2">渠道类型 *</label>
                        <select id="channelType" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" required>
                            <option value="deposit">入金</option>
                            <option value="withdraw">出金</option>
                            <option value="both">双向</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-slate-700 mb-2">支付方式 *</label>
                        <select id="channelPaymentType" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" required>
                            <option value="bank">银行转账</option>
                            <option value="alipay">支付宝</option>
                            <option value="wechat">微信支付</option>
                            <option value="crypto">加密货币</option>
                            <option value="card">信用卡</option>
                        </select>
                    </div>
                </div>

                <div class="grid grid-cols-3 gap-4 mb-4">
                    <div>
                        <label class="block text-sm font-semibold text-slate-700 mb-2">最小金额 *</label>
                        <input type="number" id="channelMinAmount" step="0.01" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" required>
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-slate-700 mb-2">最大金额 *</label>
                        <input type="number" id="channelMaxAmount" step="0.01" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" required>
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-slate-700 mb-2">手续费率 (%)</label>
                        <input type="number" id="channelFeeRate" step="0.01" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-4 mb-4">
                    <div>
                        <label class="block text-sm font-semibold text-slate-700 mb-2">货币</label>
                        <select id="channelCurrency" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                            <option value="USD">USD - 美元</option>
                            <option value="CNY">CNY - 人民币</option>
                            <option value="EUR">EUR - 欧元</option>
                            <option value="GBP">GBP - 英镑</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-slate-700 mb-2">排序权重</label>
                        <input type="number" id="channelSort" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>
                </div>

                <div class="mb-4">
                    <label class="block text-sm font-semibold text-slate-700 mb-2">API配置</label>
                    <textarea id="channelApiConfig" rows="4" placeholder='{"merchant_id": "xxx", "api_key": "xxx"}' class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 font-mono text-sm"></textarea>
                    <p class="text-xs text-slate-500 mt-1">JSON格式，存储第三方支付接口的配置信息</p>
                </div>

                <div class="mb-4 space-y-2">
                    <label class="flex items-center gap-2">
                        <input type="checkbox" id="channelIsActive" class="w-4 h-4 text-blue-600 rounded focus:ring-2 focus:ring-blue-500">
                        <span class="text-sm font-semibold text-slate-700">启用渠道</span>
                    </label>
                    <label class="flex items-center gap-2">
                        <input type="checkbox" id="channelAutoApprove" class="w-4 h-4 text-blue-600 rounded focus:ring-2 focus:ring-blue-500">
                        <span class="text-sm font-semibold text-slate-700">自动审批（到账后自动入金/出金）</span>
                    </label>
                </div>

                <div class="mb-6">
                    <label class="block text-sm font-semibold text-slate-700 mb-2">备注说明</label>
                    <textarea id="channelRemark" rows="2" placeholder="选填：渠道说明、注意事项、适用场景等" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"></textarea>
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
    loadChannels();

    document.getElementById('channelForm').addEventListener('submit', function(e) {
        e.preventDefault();
        saveChannel();
    });
});

function loadStats() {
    fetch('{{ route("admin_api_channels_stats") }}', {
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(res => res.json())
    .then(data => {
        if (data.success || data.code === 200) {
            document.getElementById('totalChannels').textContent = data.total || 0;
            document.getElementById('activeChannels').textContent = data.active || 0;
            document.getElementById('inactiveChannels').textContent = data.inactive || 0;
            document.getElementById('depositChannels').textContent = data.deposit || 0;
            document.getElementById('withdrawChannels').textContent = data.withdraw || 0;
        }
    })
    .catch(err => console.error('Load stats error:', err));
}

function loadChannels() {
    const keyword = document.getElementById('searchKeyword').value;
    const type = document.getElementById('filterType').value;
    const status = document.getElementById('filterStatus').value;
    const paymentType = document.getElementById('filterPaymentType').value;

    const params = new URLSearchParams({
        keyword: keyword,
        type: type,
        status: status,
        payment_type: paymentType
    });

    fetch(`{{ route('admin_api_channels_list') }}?${params}`, {
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(res => res.json())
    .then(data => {
        if (data.success || data.code === 200) {
            renderChannels(data.channels || []);
        } else {
            showError(data.message || '加载失败');
        }
    })
    .catch(err => {
        console.error('Load error:', err);
        showError('网络错误，请稍后重试');
    });
}

function renderChannels(channels) {
    const grid = document.getElementById('channelsGrid');

    if (channels.length === 0) {
        grid.innerHTML = `
            <div class="col-span-full bg-white rounded-xl shadow-lg p-12 text-center">
                <i class="fas fa-inbox text-5xl text-slate-300 mb-4"></i>
                <p class="text-slate-600">暂无渠道数据</p>
            </div>
        `;
        return;
    }

    grid.innerHTML = channels.map(c => `
        <div class="bg-white rounded-xl shadow-lg overflow-hidden hover:shadow-xl transition">
            <div class="bg-gradient-to-r ${c.is_active ? 'from-green-500 to-emerald-600' : 'from-slate-500 to-slate-600'} px-6 py-4">
                <div class="flex items-center justify-between mb-2">
                    <h3 class="text-xl font-bold text-white">${c.name || 'N/A'}</h3>
                    ${getStatusBadge(c.is_active)}
                </div>
                <p class="text-sm text-white opacity-90">${c.code || '-'}</p>
            </div>

            <div class="p-6">
                <div class="grid grid-cols-2 gap-3 mb-4">
                    <div class="bg-blue-50 rounded-lg p-3">
                        <p class="text-xs text-blue-600 mb-1">类型</p>
                        <p class="text-sm font-bold text-blue-700">${getTypeLabel(c.type)}</p>
                    </div>
                    <div class="bg-purple-50 rounded-lg p-3">
                        <p class="text-xs text-purple-600 mb-1">支付方式</p>
                        <p class="text-sm font-bold text-purple-700">${getPaymentTypeLabel(c.payment_type)}</p>
                    </div>
                </div>

                <div class="space-y-2 mb-4">
                    <div class="flex items-center justify-between text-sm">
                        <span class="text-slate-600">限额范围</span>
                        <span class="font-semibold text-slate-800">${formatNumber(c.min_amount)} - ${formatNumber(c.max_amount)}</span>
                    </div>
                    <div class="flex items-center justify-between text-sm">
                        <span class="text-slate-600">手续费率</span>
                        <span class="font-semibold text-slate-800">${c.fee_rate || 0}%</span>
                    </div>
                    <div class="flex items-center justify-between text-sm">
                        <span class="text-slate-600">货币</span>
                        <span class="font-semibold text-slate-800">${c.currency || 'USD'}</span>
                    </div>
                </div>

                ${c.auto_approve ? '<div class="mb-4"><span class="px-3 py-1 bg-green-100 text-green-700 text-xs rounded-full"><i class="fas fa-check mr-1"></i>自动审批</span></div>' : ''}

                <div class="flex gap-2">
                    <button onclick="toggleChannel(${c.id}, ${c.is_active})" class="flex-1 px-3 py-2 bg-${c.is_active ? 'red' : 'green'}-100 text-${c.is_active ? 'red' : 'green'}-700 text-xs font-semibold rounded hover:bg-${c.is_active ? 'red' : 'green'}-200 transition">
                        <i class="fas fa-${c.is_active ? 'ban' : 'check'} mr-1"></i>${c.is_active ? '禁用' : '启用'}
                    </button>
                    <button onclick="editChannel(${c.id})" class="flex-1 px-3 py-2 bg-blue-100 text-blue-700 text-xs font-semibold rounded hover:bg-blue-200 transition">
                        <i class="fas fa-edit mr-1"></i>编辑
                    </button>
                    <button onclick="deleteChannel(${c.id})" class="flex-1 px-3 py-2 bg-red-100 text-red-700 text-xs font-semibold rounded hover:bg-red-200 transition">
                        <i class="fas fa-trash mr-1"></i>删除
                    </button>
                </div>

                <div class="mt-4 pt-4 border-t border-slate-200 text-xs text-slate-500">
                    更新时间: ${c.updated_at || '-'}
                </div>
            </div>
        </div>
    `).join('');
}

function getStatusBadge(isActive) {
    return isActive
        ? '<span class="px-2 py-1 bg-green-100 text-green-800 text-xs font-semibold rounded-full">启用</span>'
        : '<span class="px-2 py-1 bg-red-100 text-red-800 text-xs font-semibold rounded-full">禁用</span>';
}

function getTypeLabel(type) {
    const labels = {
        'deposit': '入金',
        'withdraw': '出金',
        'both': '双向'
    };
    return labels[type] || type;
}

function getPaymentTypeLabel(type) {
    const labels = {
        'bank': '银行转账',
        'alipay': '支付宝',
        'wechat': '微信支付',
        'crypto': '加密货币',
        'card': '信用卡'
    };
    return labels[type] || type;
}

function addChannel() {
    document.getElementById('modalTitle').textContent = '新增渠道';
    document.getElementById('channelId').value = '';
    document.getElementById('channelCode').value = '';
    document.getElementById('channelName').value = '';
    document.getElementById('channelType').value = 'deposit';
    document.getElementById('channelPaymentType').value = 'bank';
    document.getElementById('channelMinAmount').value = '100';
    document.getElementById('channelMaxAmount').value = '50000';
    document.getElementById('channelFeeRate').value = '0';
    document.getElementById('channelCurrency').value = 'USD';
    document.getElementById('channelSort').value = '0';
    document.getElementById('channelApiConfig').value = '';
    document.getElementById('channelIsActive').checked = true;
    document.getElementById('channelAutoApprove').checked = false;
    document.getElementById('channelRemark').value = '';
    document.getElementById('channelCode').disabled = false;
    document.getElementById('editModal').classList.remove('hidden');
}

function editChannel(id) {
    fetch(`{{ route('admin_api_channels_detail', ['id' => '__ID__']) }}`.replace('__ID__', id), {
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(res => res.json())
    .then(data => {
        if (data.success && data.channel) {
            const c = data.channel;
            document.getElementById('modalTitle').textContent = '编辑渠道';
            document.getElementById('channelId').value = c.id;
            document.getElementById('channelCode').value = c.code;
            document.getElementById('channelName').value = c.name;
            document.getElementById('channelType').value = c.type;
            document.getElementById('channelPaymentType').value = c.payment_type;
            document.getElementById('channelMinAmount').value = c.min_amount;
            document.getElementById('channelMaxAmount').value = c.max_amount;
            document.getElementById('channelFeeRate').value = c.fee_rate || 0;
            document.getElementById('channelCurrency').value = c.currency || 'USD';
            document.getElementById('channelSort').value = c.sort || 0;
            document.getElementById('channelApiConfig').value = c.api_config || '';
            document.getElementById('channelIsActive').checked = c.is_active;
            document.getElementById('channelAutoApprove').checked = c.auto_approve;
            document.getElementById('channelRemark').value = c.remark || '';
            document.getElementById('channelCode').disabled = true;
            document.getElementById('editModal').classList.remove('hidden');
        }
    })
    .catch(err => console.error('Load channel error:', err));
}

function saveChannel() {
    const id = document.getElementById('channelId').value;
    const data = {
        code: document.getElementById('channelCode').value,
        name: document.getElementById('channelName').value,
        type: document.getElementById('channelType').value,
        payment_type: document.getElementById('channelPaymentType').value,
        min_amount: document.getElementById('channelMinAmount').value,
        max_amount: document.getElementById('channelMaxAmount').value,
        fee_rate: document.getElementById('channelFeeRate').value,
        currency: document.getElementById('channelCurrency').value,
        sort: document.getElementById('channelSort').value,
        api_config: document.getElementById('channelApiConfig').value,
        is_active: document.getElementById('channelIsActive').checked,
        auto_approve: document.getElementById('channelAutoApprove').checked,
        remark: document.getElementById('channelRemark').value
    };

    const url = id
        ? `{{ route('admin_api_channels_update', ['id' => '__ID__']) }}`.replace('__ID__', id)
        : '{{ route("admin_api_channels_create") }}';

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
            alert(id ? '渠道更新成功' : '渠道创建成功');
            closeEditModal();
            loadStats();
            loadChannels();
        } else {
            alert(data.message || '保存失败');
        }
    })
    .catch(err => {
        console.error('Save error:', err);
        alert('网络错误，请稍后重试');
    });
}

function toggleChannel(id, currentStatus) {
    const action = currentStatus ? '禁用' : '启用';
    if (!confirm(`确定要${action}此渠道吗？`)) {
        return;
    }

    fetch(`{{ route('admin_api_channels_toggle', ['id' => '__ID__']) }}`.replace('__ID__', id), {
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
            alert(`渠道${action}成功`);
            loadStats();
            loadChannels();
        } else {
            alert(data.message || `${action}失败`);
        }
    })
    .catch(err => {
        console.error('Toggle error:', err);
        alert('网络错误，请稍后重试');
    });
}

function deleteChannel(id) {
    if (!confirm('确定要删除此渠道吗？此操作不可恢复。')) {
        return;
    }

    fetch(`{{ route('admin_api_channels_delete', ['id' => '__ID__']) }}`.replace('__ID__', id), {
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
            alert('渠道删除成功');
            loadStats();
            loadChannels();
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

function refreshChannels() {
    loadStats();
    loadChannels();
}

function searchChannels() {
    loadChannels();
}

function formatNumber(num) {
    return new Intl.NumberFormat('en-US', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
    }).format(num);
}

function showError(message) {
    document.getElementById('channelsGrid').innerHTML = `
        <div class="col-span-full bg-white rounded-xl shadow-lg p-12 text-center">
            <i class="fas fa-exclamation-circle text-5xl text-red-500 mb-4"></i>
            <p class="text-red-600">${message}</p>
        </div>
    `;
}
</script>
@endsection
