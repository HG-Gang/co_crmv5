@extends('admin-tailwind.layouts.app')

@section('title', '汇率管理 - 管理后台')

@section('content')
<!-- Page Header -->
<div class="flex items-center justify-between mb-8">
    <div>
        <h1 class="text-3xl font-bold text-slate-800">汇率管理</h1>
        <p class="text-slate-600 mt-2">管理多币种汇率和自动更新设置</p>
    </div>
    <div class="flex gap-3">
        <button onclick="syncRates()" class="px-6 py-3 bg-gradient-to-r from-purple-500 to-pink-600 text-white font-semibold rounded-lg hover:shadow-lg transition">
            <i class="fas fa-sync-alt mr-2"></i>同步汇率
        </button>
        <button onclick="addRate()" class="px-6 py-3 bg-gradient-to-r from-green-500 to-emerald-600 text-white font-semibold rounded-lg hover:shadow-lg transition">
            <i class="fas fa-plus mr-2"></i>新增汇率
        </button>
    </div>
</div>

<!-- Stats Cards -->
<div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-6">
    <div class="bg-white rounded-xl shadow-lg p-6 border-l-4 border-blue-500">
        <p class="text-sm text-slate-600 mb-2">汇率对数</p>
        <p class="text-3xl font-bold text-slate-800" id="totalPairs">0</p>
    </div>
    <div class="bg-white rounded-xl shadow-lg p-6 border-l-4 border-green-500">
        <p class="text-sm text-slate-600 mb-2">最后更新</p>
        <p class="text-lg font-bold text-green-600" id="lastUpdate">-</p>
    </div>
    <div class="bg-white rounded-xl shadow-lg p-6 border-l-4 border-purple-500">
        <p class="text-sm text-slate-600 mb-2">基准货币</p>
        <p class="text-3xl font-bold text-purple-600" id="baseCurrency">USD</p>
    </div>
    <div class="bg-white rounded-xl shadow-lg p-6 border-l-4 border-orange-500">
        <p class="text-sm text-slate-600 mb-2">自动更新</p>
        <p class="text-lg font-bold" id="autoUpdateStatus">
            <span class="px-3 py-1 bg-green-100 text-green-700 text-sm rounded-full">已启用</span>
        </p>
    </div>
</div>

<!-- Filter Bar -->
<div class="bg-white rounded-xl shadow-lg p-6 mb-6">
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
        <div>
            <label class="block text-sm font-semibold text-slate-700 mb-2">搜索</label>
            <input type="text" id="searchKeyword" placeholder="货币对" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
        </div>
        <div>
            <label class="block text-sm font-semibold text-slate-700 mb-2">基准货币</label>
            <select id="filterBase" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                <option value="">全部</option>
                <option value="USD">USD</option>
                <option value="EUR">EUR</option>
                <option value="GBP">GBP</option>
                <option value="CNY">CNY</option>
            </select>
        </div>
        <div>
            <label class="block text-sm font-semibold text-slate-700 mb-2">排序方式</label>
            <select id="filterSort" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                <option value="pair_asc">货币对 A-Z</option>
                <option value="pair_desc">货币对 Z-A</option>
                <option value="rate_desc">汇率从高到低</option>
                <option value="rate_asc">汇率从低到高</option>
            </select>
        </div>
        <div class="flex items-end">
            <button onclick="searchRates()" class="w-full px-6 py-2 bg-gradient-to-r from-blue-500 to-indigo-600 text-white font-semibold rounded-lg hover:shadow-lg transition">
                <i class="fas fa-search mr-2"></i>搜索
            </button>
        </div>
    </div>
</div>

<!-- Exchange Rates Grid -->
<div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-6" id="ratesGrid">
    <div class="col-span-full text-center py-12">
        <i class="fas fa-spinner fa-spin text-3xl text-slate-500 mb-3"></i>
        <p class="text-slate-600">加载中...</p>
    </div>
</div>

<!-- Edit Modal -->
<div id="editModal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl max-w-2xl w-full">
        <div class="bg-gradient-to-r from-blue-500 to-indigo-600 px-6 py-4 flex items-center justify-between rounded-t-2xl">
            <h3 class="text-xl font-bold text-white" id="modalTitle">编辑汇率</h3>
            <button onclick="closeEditModal()" class="text-white hover:text-slate-200">
                <i class="fas fa-times text-xl"></i>
            </button>
        </div>
        <div class="p-6">
            <form id="rateForm">
                <input type="hidden" id="rateId">

                <div class="grid grid-cols-2 gap-4 mb-4">
                    <div>
                        <label class="block text-sm font-semibold text-slate-700 mb-2">基准货币 *</label>
                        <select id="rateBaseCurrency" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" required>
                            <option value="USD">USD - 美元</option>
                            <option value="EUR">EUR - 欧元</option>
                            <option value="GBP">GBP - 英镑</option>
                            <option value="CNY">CNY - 人民币</option>
                            <option value="JPY">JPY - 日元</option>
                            <option value="AUD">AUD - 澳元</option>
                            <option value="CAD">CAD - 加元</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-slate-700 mb-2">目标货币 *</label>
                        <select id="rateTargetCurrency" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" required>
                            <option value="USD">USD - 美元</option>
                            <option value="EUR">EUR - 欧元</option>
                            <option value="GBP">GBP - 英镑</option>
                            <option value="CNY">CNY - 人民币</option>
                            <option value="JPY">JPY - 日元</option>
                            <option value="AUD">AUD - 澳元</option>
                            <option value="CAD">CAD - 加元</option>
                        </select>
                    </div>
                </div>

                <div class="mb-4">
                    <label class="block text-sm font-semibold text-slate-700 mb-2">汇率 *</label>
                    <input type="number" id="rateValue" step="0.000001" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" required>
                    <p class="text-xs text-slate-500 mt-1">例如：1 USD = 6.87 CNY，则填入 6.87</p>
                </div>

                <div class="grid grid-cols-2 gap-4 mb-4">
                    <div>
                        <label class="block text-sm font-semibold text-slate-700 mb-2">买入价</label>
                        <input type="number" id="rateBuy" step="0.000001" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-slate-700 mb-2">卖出价</label>
                        <input type="number" id="rateSell" step="0.000001" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>
                </div>

                <div class="mb-4">
                    <label class="flex items-center gap-2">
                        <input type="checkbox" id="rateAutoUpdate" class="w-4 h-4 text-blue-600 rounded focus:ring-2 focus:ring-blue-500">
                        <span class="text-sm font-semibold text-slate-700">启用自动更新</span>
                    </label>
                    <p class="text-xs text-slate-500 mt-1">从第三方API定期同步汇率</p>
                </div>

                <div class="mb-6">
                    <label class="block text-sm font-semibold text-slate-700 mb-2">备注</label>
                    <textarea id="rateRemark" rows="2" placeholder="选填：汇率说明或注意事项" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"></textarea>
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

<!-- History Modal -->
<div id="historyModal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl max-w-4xl w-full max-h-[90vh] overflow-y-auto">
        <div class="sticky top-0 bg-gradient-to-r from-purple-500 to-pink-600 px-6 py-4 flex items-center justify-between">
            <h3 class="text-xl font-bold text-white">汇率历史</h3>
            <button onclick="closeHistoryModal()" class="text-white hover:text-slate-200">
                <i class="fas fa-times text-xl"></i>
            </button>
        </div>
        <div id="historyContent" class="p-6"></div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    loadStats();
    loadRates();

    document.getElementById('rateForm').addEventListener('submit', function(e) {
        e.preventDefault();
        saveRate();
    });

    // Auto refresh every 60 seconds
    setInterval(() => {
        loadStats();
        loadRates();
    }, 60000);
});

function loadStats() {
    fetch('{{ route("admin_api_exchange_rates_stats") }}', {
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(res => res.json())
    .then(data => {
        if (data.success || data.code === 200) {
            document.getElementById('totalPairs').textContent = data.totalPairs || 0;
            document.getElementById('lastUpdate').textContent = data.lastUpdate || '-';
            document.getElementById('baseCurrency').textContent = data.baseCurrency || 'USD';

            const statusEl = document.getElementById('autoUpdateStatus');
            if (data.autoUpdate) {
                statusEl.innerHTML = '<span class="px-3 py-1 bg-green-100 text-green-700 text-sm rounded-full">已启用</span>';
            } else {
                statusEl.innerHTML = '<span class="px-3 py-1 bg-red-100 text-red-700 text-sm rounded-full">已禁用</span>';
            }
        }
    })
    .catch(err => console.error('Load stats error:', err));
}

function loadRates() {
    const keyword = document.getElementById('searchKeyword').value;
    const base = document.getElementById('filterBase').value;
    const sort = document.getElementById('filterSort').value;

    const params = new URLSearchParams({
        keyword: keyword,
        base: base,
        sort: sort
    });

    fetch(`{{ route('admin_api_exchange_rates_list') }}?${params}`, {
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(res => res.json())
    .then(data => {
        if (data.success || data.code === 200) {
            renderRates(data.rates || []);
        } else {
            showError(data.message || '加载失败');
        }
    })
    .catch(err => {
        console.error('Load error:', err);
        showError('网络错误，请稍后重试');
    });
}

function renderRates(rates) {
    const grid = document.getElementById('ratesGrid');

    if (rates.length === 0) {
        grid.innerHTML = `
            <div class="col-span-full bg-white rounded-xl shadow-lg p-12 text-center">
                <i class="fas fa-inbox text-5xl text-slate-300 mb-4"></i>
                <p class="text-slate-600">暂无汇率数据</p>
            </div>
        `;
        return;
    }

    grid.innerHTML = rates.map(r => {
        const changePercent = r.change_percent || 0;
        const isPositive = changePercent >= 0;

        return `
        <div class="bg-white rounded-xl shadow-lg overflow-hidden hover:shadow-xl transition">
            <div class="bg-gradient-to-r ${isPositive ? 'from-green-500 to-emerald-600' : 'from-red-500 to-pink-600'} px-6 py-4">
                <div class="flex items-center justify-between">
                    <h3 class="text-2xl font-bold text-white">${r.base_currency}/${r.target_currency}</h3>
                    ${r.auto_update ? '<i class="fas fa-sync-alt text-white"></i>' : ''}
                </div>
            </div>

            <div class="p-6">
                <div class="text-center mb-4">
                    <p class="text-4xl font-bold text-slate-800 mb-2">${formatRate(r.rate)}</p>
                    <div class="flex items-center justify-center gap-2">
                        <span class="text-sm ${isPositive ? 'text-green-600' : 'text-red-600'} font-semibold">
                            ${isPositive ? '+' : ''}${changePercent.toFixed(2)}%
                        </span>
                        <i class="fas fa-arrow-${isPositive ? 'up' : 'down'} text-xs ${isPositive ? 'text-green-600' : 'text-red-600'}"></i>
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-3 mb-4">
                    <div class="bg-blue-50 rounded-lg p-3 text-center">
                        <p class="text-xs text-blue-600 mb-1">买入</p>
                        <p class="text-sm font-bold text-blue-700">${formatRate(r.buy_rate || r.rate)}</p>
                    </div>
                    <div class="bg-purple-50 rounded-lg p-3 text-center">
                        <p class="text-xs text-purple-600 mb-1">卖出</p>
                        <p class="text-sm font-bold text-purple-700">${formatRate(r.sell_rate || r.rate)}</p>
                    </div>
                </div>

                <div class="text-xs text-slate-500 mb-4">
                    更新时间: ${r.updated_at || '-'}
                </div>

                <div class="flex gap-2">
                    <button onclick="viewHistory(${r.id}, '${r.base_currency}/${r.target_currency}')" class="flex-1 px-3 py-2 bg-purple-100 text-purple-700 text-xs font-semibold rounded hover:bg-purple-200 transition">
                        <i class="fas fa-history mr-1"></i>历史
                    </button>
                    <button onclick="editRate(${r.id})" class="flex-1 px-3 py-2 bg-blue-100 text-blue-700 text-xs font-semibold rounded hover:bg-blue-200 transition">
                        <i class="fas fa-edit mr-1"></i>编辑
                    </button>
                    <button onclick="deleteRate(${r.id})" class="flex-1 px-3 py-2 bg-red-100 text-red-700 text-xs font-semibold rounded hover:bg-red-200 transition">
                        <i class="fas fa-trash mr-1"></i>删除
                    </button>
                </div>
            </div>
        </div>
        `;
    }).join('');
}

function addRate() {
    document.getElementById('modalTitle').textContent = '新增汇率';
    document.getElementById('rateId').value = '';
    document.getElementById('rateBaseCurrency').value = 'USD';
    document.getElementById('rateTargetCurrency').value = 'CNY';
    document.getElementById('rateValue').value = '';
    document.getElementById('rateBuy').value = '';
    document.getElementById('rateSell').value = '';
    document.getElementById('rateAutoUpdate').checked = true;
    document.getElementById('rateRemark').value = '';
    document.getElementById('rateBaseCurrency').disabled = false;
    document.getElementById('rateTargetCurrency').disabled = false;
    document.getElementById('editModal').classList.remove('hidden');
}

function editRate(id) {
    fetch(`{{ route('admin_api_exchange_rates_detail', ['id' => '__ID__']) }}`.replace('__ID__', id), {
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(res => res.json())
    .then(data => {
        if (data.success && data.rate) {
            const r = data.rate;
            document.getElementById('modalTitle').textContent = '编辑汇率';
            document.getElementById('rateId').value = r.id;
            document.getElementById('rateBaseCurrency').value = r.base_currency;
            document.getElementById('rateTargetCurrency').value = r.target_currency;
            document.getElementById('rateValue').value = r.rate;
            document.getElementById('rateBuy').value = r.buy_rate || '';
            document.getElementById('rateSell').value = r.sell_rate || '';
            document.getElementById('rateAutoUpdate').checked = r.auto_update;
            document.getElementById('rateRemark').value = r.remark || '';
            document.getElementById('rateBaseCurrency').disabled = true;
            document.getElementById('rateTargetCurrency').disabled = true;
            document.getElementById('editModal').classList.remove('hidden');
        }
    })
    .catch(err => console.error('Load rate error:', err));
}

function saveRate() {
    const id = document.getElementById('rateId').value;
    const data = {
        base_currency: document.getElementById('rateBaseCurrency').value,
        target_currency: document.getElementById('rateTargetCurrency').value,
        rate: document.getElementById('rateValue').value,
        buy_rate: document.getElementById('rateBuy').value,
        sell_rate: document.getElementById('rateSell').value,
        auto_update: document.getElementById('rateAutoUpdate').checked,
        remark: document.getElementById('rateRemark').value
    };

    const url = id
        ? `{{ route('admin_api_exchange_rates_update', ['id' => '__ID__']) }}`.replace('__ID__', id)
        : '{{ route("admin_api_exchange_rates_create") }}';

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
            alert(id ? '汇率更新成功' : '汇率创建成功');
            closeEditModal();
            loadStats();
            loadRates();
        } else {
            alert(data.message || '保存失败');
        }
    })
    .catch(err => {
        console.error('Save error:', err);
        alert('网络错误，请稍后重试');
    });
}

function deleteRate(id) {
    if (!confirm('确定要删除此汇率吗？')) {
        return;
    }

    fetch(`{{ route('admin_api_exchange_rates_delete', ['id' => '__ID__']) }}`.replace('__ID__', id), {
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
            alert('汇率删除成功');
            loadStats();
            loadRates();
        } else {
            alert(data.message || '删除失败');
        }
    })
    .catch(err => {
        console.error('Delete error:', err);
        alert('网络错误，请稍后重试');
    });
}

function syncRates() {
    if (!confirm('确定要从第三方API同步所有汇率吗？')) {
        return;
    }

    fetch('{{ route("admin_api_exchange_rates_sync") }}', {
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
            alert('汇率同步成功');
            loadStats();
            loadRates();
        } else {
            alert(data.message || '同步失败');
        }
    })
    .catch(err => {
        console.error('Sync error:', err);
        alert('网络错误，请稍后重试');
    });
}

function viewHistory(id, pair) {
    fetch(`{{ route('admin_api_exchange_rates_history', ['id' => '__ID__']) }}`.replace('__ID__', id), {
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(res => res.json())
    .then(data => {
        if (data.success && data.history) {
            const history = data.history;
            document.getElementById('historyContent').innerHTML = `
                <h4 class="text-lg font-bold text-slate-800 mb-4">${pair} 汇率历史</h4>
                <div class="space-y-3">
                    ${history.map(h => `
                        <div class="flex items-center justify-between p-4 bg-slate-50 rounded-lg">
                            <div>
                                <p class="text-2xl font-bold text-slate-800">${formatRate(h.rate)}</p>
                                <p class="text-xs text-slate-500">${h.created_at}</p>
                            </div>
                            <div class="text-right">
                                <p class="text-sm text-slate-600">变化: <span class="font-semibold ${h.change >= 0 ? 'text-green-600' : 'text-red-600'}">${h.change >= 0 ? '+' : ''}${h.change.toFixed(6)}</span></p>
                            </div>
                        </div>
                    `).join('')}
                </div>
            `;
            document.getElementById('historyModal').classList.remove('hidden');
        }
    })
    .catch(err => console.error('Load history error:', err));
}

function closeEditModal() {
    document.getElementById('editModal').classList.add('hidden');
}

function closeHistoryModal() {
    document.getElementById('historyModal').classList.add('hidden');
}

function searchRates() {
    loadRates();
}

function formatRate(rate) {
    return parseFloat(rate).toFixed(6);
}

function showError(message) {
    document.getElementById('ratesGrid').innerHTML = `
        <div class="col-span-full bg-white rounded-xl shadow-lg p-12 text-center">
            <i class="fas fa-exclamation-circle text-5xl text-red-500 mb-4"></i>
            <p class="text-red-600">${message}</p>
        </div>
    `;
}
</script>
@endsection
