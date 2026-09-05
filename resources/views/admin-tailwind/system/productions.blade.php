@extends('admin-tailwind.layouts.app')

@section('title', '产品管理 - 管理后台')

@section('content')
<!-- Page Header -->
<div class="flex items-center justify-between mb-8">
    <div>
        <h1 class="text-3xl font-bold text-slate-800">产品管理</h1>
        <p class="text-slate-600 mt-2">管理MT4交易产品、品种和合约配置</p>
    </div>
    <div class="flex gap-3">
        <button onclick="syncProducts()" class="px-6 py-3 bg-gradient-to-r from-purple-500 to-pink-600 text-white font-semibold rounded-lg hover:shadow-lg transition">
            <i class="fas fa-sync-alt mr-2"></i>从MT4同步
        </button>
        <button onclick="addProduct()" class="px-6 py-3 bg-gradient-to-r from-green-500 to-emerald-600 text-white font-semibold rounded-lg hover:shadow-lg transition">
            <i class="fas fa-plus mr-2"></i>新增产品
        </button>
    </div>
</div>

<!-- Stats Cards -->
<div class="grid grid-cols-1 md:grid-cols-5 gap-6 mb-6">
    <div class="bg-white rounded-xl shadow-lg p-6 border-l-4 border-blue-500">
        <p class="text-sm text-slate-600 mb-2">总产品数</p>
        <p class="text-3xl font-bold text-slate-800" id="totalProducts">0</p>
    </div>
    <div class="bg-white rounded-xl shadow-lg p-6 border-l-4 border-green-500">
        <p class="text-sm text-slate-600 mb-2">启用中</p>
        <p class="text-3xl font-bold text-green-600" id="activeProducts">0</p>
    </div>
    <div class="bg-white rounded-xl shadow-lg p-6 border-l-4 border-purple-500">
        <p class="text-sm text-slate-600 mb-2">外汇产品</p>
        <p class="text-3xl font-bold text-purple-600" id="forexProducts">0</p>
    </div>
    <div class="bg-white rounded-xl shadow-lg p-6 border-l-4 border-orange-500">
        <p class="text-sm text-slate-600 mb-2">贵金属</p>
        <p class="text-3xl font-bold text-orange-600" id="metalProducts">0</p>
    </div>
    <div class="bg-white rounded-xl shadow-lg p-6 border-l-4 border-red-500">
        <p class="text-sm text-slate-600 mb-2">能源产品</p>
        <p class="text-3xl font-bold text-red-600" id="energyProducts">0</p>
    </div>
</div>

<!-- Filter Bar -->
<div class="bg-white rounded-xl shadow-lg p-6 mb-6">
    <div class="grid grid-cols-1 md:grid-cols-5 gap-4">
        <div>
            <label class="block text-sm font-semibold text-slate-700 mb-2">搜索</label>
            <input type="text" id="searchKeyword" placeholder="产品代码或名称" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
        </div>
        <div>
            <label class="block text-sm font-semibold text-slate-700 mb-2">产品类型</label>
            <select id="filterCategory" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                <option value="">全部</option>
                <option value="forex">外汇</option>
                <option value="metal">贵金属</option>
                <option value="energy">能源</option>
                <option value="index">指数</option>
                <option value="crypto">加密货币</option>
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
            <label class="block text-sm font-semibold text-slate-700 mb-2">排序</label>
            <select id="filterSort" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                <option value="symbol_asc">代码 A-Z</option>
                <option value="symbol_desc">代码 Z-A</option>
                <option value="volume_desc">成交量从高到低</option>
            </select>
        </div>
        <div class="flex items-end">
            <button onclick="searchProducts()" class="w-full px-6 py-2 bg-gradient-to-r from-blue-500 to-indigo-600 text-white font-semibold rounded-lg hover:shadow-lg transition">
                <i class="fas fa-search mr-2"></i>搜索
            </button>
        </div>
    </div>
</div>

<!-- Products Table -->
<div class="bg-white rounded-xl shadow-lg overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full">
            <thead class="bg-gradient-to-r from-blue-500 to-indigo-600 text-white">
                <tr>
                    <th class="px-6 py-4 text-left text-sm font-semibold">产品代码</th>
                    <th class="px-6 py-4 text-left text-sm font-semibold">产品名称</th>
                    <th class="px-6 py-4 text-left text-sm font-semibold">类型</th>
                    <th class="px-6 py-4 text-right text-sm font-semibold">合约大小</th>
                    <th class="px-6 py-4 text-right text-sm font-semibold">点差</th>
                    <th class="px-6 py-4 text-right text-sm font-semibold">最小手数</th>
                    <th class="px-6 py-4 text-right text-sm font-semibold">最大手数</th>
                    <th class="px-6 py-4 text-center text-sm font-semibold">状态</th>
                    <th class="px-6 py-4 text-center text-sm font-semibold">操作</th>
                </tr>
            </thead>
            <tbody id="productsTable">
                <tr>
                    <td colspan="9" class="px-6 py-12 text-center">
                        <i class="fas fa-spinner fa-spin text-3xl text-slate-500 mb-3"></i>
                        <p class="text-slate-600">加载中...</p>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</div>

<!-- Edit Modal -->
<div id="editModal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl max-w-4xl w-full max-h-[90vh] overflow-y-auto">
        <div class="sticky top-0 bg-gradient-to-r from-blue-500 to-indigo-600 px-6 py-4 flex items-center justify-between">
            <h3 class="text-xl font-bold text-white" id="modalTitle">编辑产品</h3>
            <button onclick="closeEditModal()" class="text-white hover:text-slate-200">
                <i class="fas fa-times text-xl"></i>
            </button>
        </div>
        <div class="p-6">
            <form id="productForm">
                <input type="hidden" id="productId">

                <div class="grid grid-cols-3 gap-4 mb-4">
                    <div>
                        <label class="block text-sm font-semibold text-slate-700 mb-2">产品代码 *</label>
                        <input type="text" id="productSymbol" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" required>
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-slate-700 mb-2">产品名称 *</label>
                        <input type="text" id="productName" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" required>
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-slate-700 mb-2">产品类型 *</label>
                        <select id="productCategory" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" required>
                            <option value="forex">外汇</option>
                            <option value="metal">贵金属</option>
                            <option value="energy">能源</option>
                            <option value="index">指数</option>
                            <option value="crypto">加密货币</option>
                        </select>
                    </div>
                </div>

                <div class="grid grid-cols-4 gap-4 mb-4">
                    <div>
                        <label class="block text-sm font-semibold text-slate-700 mb-2">合约大小 *</label>
                        <input type="number" id="productContractSize" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" required>
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-slate-700 mb-2">点差（点）</label>
                        <input type="number" id="productSpread" step="0.1" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-slate-700 mb-2">点值</label>
                        <input type="number" id="productPointValue" step="0.00001" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-slate-700 mb-2">小数位数</label>
                        <input type="number" id="productDigits" min="0" max="5" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>
                </div>

                <div class="grid grid-cols-4 gap-4 mb-4">
                    <div>
                        <label class="block text-sm font-semibold text-slate-700 mb-2">最小手数 *</label>
                        <input type="number" id="productMinLot" step="0.01" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" required>
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-slate-700 mb-2">最大手数 *</label>
                        <input type="number" id="productMaxLot" step="0.01" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" required>
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-slate-700 mb-2">手数步进</label>
                        <input type="number" id="productLotStep" step="0.01" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-slate-700 mb-2">保证金比例%</label>
                        <input type="number" id="productMarginRate" step="0.01" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>
                </div>

                <div class="grid grid-cols-3 gap-4 mb-4">
                    <div>
                        <label class="block text-sm font-semibold text-slate-700 mb-2">基础货币</label>
                        <input type="text" id="productBaseCurrency" maxlength="3" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-slate-700 mb-2">报价货币</label>
                        <input type="text" id="productQuoteCurrency" maxlength="3" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-slate-700 mb-2">利润货币</label>
                        <input type="text" id="productProfitCurrency" maxlength="3" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-4 mb-4">
                    <div>
                        <label class="block text-sm font-semibold text-slate-700 mb-2">佣金（单边）</label>
                        <input type="number" id="productCommission" step="0.01" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-slate-700 mb-2">隔夜利息（多/空）</label>
                        <div class="grid grid-cols-2 gap-2">
                            <input type="number" id="productSwapLong" step="0.01" placeholder="多头" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                            <input type="number" id="productSwapShort" step="0.01" placeholder="空头" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                        </div>
                    </div>
                </div>

                <div class="mb-4 space-y-2">
                    <label class="flex items-center gap-2">
                        <input type="checkbox" id="productIsActive" class="w-4 h-4 text-blue-600 rounded focus:ring-2 focus:ring-blue-500">
                        <span class="text-sm font-semibold text-slate-700">启用产品</span>
                    </label>
                    <label class="flex items-center gap-2">
                        <input type="checkbox" id="productIsTradeAllowed" class="w-4 h-4 text-blue-600 rounded focus:ring-2 focus:ring-blue-500">
                        <span class="text-sm font-semibold text-slate-700">允许交易</span>
                    </label>
                </div>

                <div class="mb-6">
                    <label class="block text-sm font-semibold text-slate-700 mb-2">备注说明</label>
                    <textarea id="productRemark" rows="2" placeholder="选填：产品说明、交易时间、特殊规则等" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"></textarea>
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
    loadProducts();

    document.getElementById('productForm').addEventListener('submit', function(e) {
        e.preventDefault();
        saveProduct();
    });
});

function loadStats() {
    fetch('{{ route("admin_api_productions_stats") }}', {
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(res => res.json())
    .then(data => {
        if (data.success || data.code === 200) {
            document.getElementById('totalProducts').textContent = data.total || 0;
            document.getElementById('activeProducts').textContent = data.active || 0;
            document.getElementById('forexProducts').textContent = data.forex || 0;
            document.getElementById('metalProducts').textContent = data.metal || 0;
            document.getElementById('energyProducts').textContent = data.energy || 0;
        }
    })
    .catch(err => console.error('Load stats error:', err));
}

function loadProducts() {
    const keyword = document.getElementById('searchKeyword').value;
    const category = document.getElementById('filterCategory').value;
    const status = document.getElementById('filterStatus').value;
    const sort = document.getElementById('filterSort').value;

    const params = new URLSearchParams({
        keyword: keyword,
        category: category,
        status: status,
        sort: sort
    });

    fetch(`{{ route('admin_api_productions_list') }}?${params}`, {
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(res => res.json())
    .then(data => {
        if (data.success || data.code === 200) {
            renderProducts(data.products || []);
        } else {
            showError(data.message || '加载失败');
        }
    })
    .catch(err => {
        console.error('Load error:', err);
        showError('网络错误，请稍后重试');
    });
}

function renderProducts(products) {
    const table = document.getElementById('productsTable');

    if (products.length === 0) {
        table.innerHTML = `
            <tr>
                <td colspan="9" class="px-6 py-12 text-center">
                    <i class="fas fa-inbox text-5xl text-slate-300 mb-4"></i>
                    <p class="text-slate-600">暂无产品数据</p>
                </td>
            </tr>
        `;
        return;
    }

    table.innerHTML = products.map((p, index) => `
        <tr class="border-b border-slate-200 hover:bg-slate-50 transition ${index % 2 === 0 ? 'bg-white' : 'bg-slate-50'}">
            <td class="px-6 py-4">
                <span class="font-mono font-semibold text-slate-800">${p.symbol || '-'}</span>
            </td>
            <td class="px-6 py-4">
                <span class="text-slate-800">${p.name || '-'}</span>
            </td>
            <td class="px-6 py-4">
                ${getCategoryBadge(p.category)}
            </td>
            <td class="px-6 py-4 text-right">
                <span class="text-slate-800">${formatNumber(p.contract_size || 0, 0)}</span>
            </td>
            <td class="px-6 py-4 text-right">
                <span class="text-slate-800">${p.spread || 0}</span>
            </td>
            <td class="px-6 py-4 text-right">
                <span class="text-slate-800">${p.min_lot || 0}</span>
            </td>
            <td class="px-6 py-4 text-right">
                <span class="text-slate-800">${p.max_lot || 0}</span>
            </td>
            <td class="px-6 py-4 text-center">
                ${getStatusBadge(p.is_active)}
            </td>
            <td class="px-6 py-4">
                <div class="flex items-center justify-center gap-2">
                    <button onclick="editProduct(${p.id})" class="px-3 py-1 bg-blue-100 text-blue-700 text-xs font-semibold rounded hover:bg-blue-200 transition">
                        <i class="fas fa-edit"></i>
                    </button>
                    <button onclick="toggleProduct(${p.id}, ${p.is_active})" class="px-3 py-1 bg-${p.is_active ? 'red' : 'green'}-100 text-${p.is_active ? 'red' : 'green'}-700 text-xs font-semibold rounded hover:bg-${p.is_active ? 'red' : 'green'}-200 transition">
                        <i class="fas fa-${p.is_active ? 'ban' : 'check'}"></i>
                    </button>
                    <button onclick="deleteProduct(${p.id})" class="px-3 py-1 bg-red-100 text-red-700 text-xs font-semibold rounded hover:bg-red-200 transition">
                        <i class="fas fa-trash"></i>
                    </button>
                </div>
            </td>
        </tr>
    `).join('');
}

function getCategoryBadge(category) {
    const badges = {
        'forex': '<span class="px-2 py-1 bg-blue-100 text-blue-700 text-xs font-semibold rounded-full">外汇</span>',
        'metal': '<span class="px-2 py-1 bg-yellow-100 text-yellow-700 text-xs font-semibold rounded-full">贵金属</span>',
        'energy': '<span class="px-2 py-1 bg-red-100 text-red-700 text-xs font-semibold rounded-full">能源</span>',
        'index': '<span class="px-2 py-1 bg-purple-100 text-purple-700 text-xs font-semibold rounded-full">指数</span>',
        'crypto': '<span class="px-2 py-1 bg-orange-100 text-orange-700 text-xs font-semibold rounded-full">加密货币</span>'
    };
    return badges[category] || '<span class="px-2 py-1 bg-slate-100 text-slate-700 text-xs font-semibold rounded-full">其他</span>';
}

function getStatusBadge(isActive) {
    return isActive
        ? '<span class="px-3 py-1 bg-green-100 text-green-700 text-xs font-semibold rounded-full">启用</span>'
        : '<span class="px-3 py-1 bg-red-100 text-red-700 text-xs font-semibold rounded-full">禁用</span>';
}

function addProduct() {
    document.getElementById('modalTitle').textContent = '新增产品';
    document.getElementById('productId').value = '';
    document.getElementById('productSymbol').value = '';
    document.getElementById('productName').value = '';
    document.getElementById('productCategory').value = 'forex';
    document.getElementById('productContractSize').value = '100000';
    document.getElementById('productSpread').value = '2';
    document.getElementById('productPointValue').value = '10';
    document.getElementById('productDigits').value = '5';
    document.getElementById('productMinLot').value = '0.01';
    document.getElementById('productMaxLot').value = '100';
    document.getElementById('productLotStep').value = '0.01';
    document.getElementById('productMarginRate').value = '1';
    document.getElementById('productBaseCurrency').value = '';
    document.getElementById('productQuoteCurrency').value = '';
    document.getElementById('productProfitCurrency').value = '';
    document.getElementById('productCommission').value = '0';
    document.getElementById('productSwapLong').value = '0';
    document.getElementById('productSwapShort').value = '0';
    document.getElementById('productIsActive').checked = true;
    document.getElementById('productIsTradeAllowed').checked = true;
    document.getElementById('productRemark').value = '';
    document.getElementById('productSymbol').disabled = false;
    document.getElementById('editModal').classList.remove('hidden');
}

function editProduct(id) {
    fetch(`{{ route('admin_api_productions_detail', ['id' => '__ID__']) }}`.replace('__ID__', id), {
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(res => res.json())
    .then(data => {
        if (data.success && data.product) {
            const p = data.product;
            document.getElementById('modalTitle').textContent = '编辑产品';
            document.getElementById('productId').value = p.id;
            document.getElementById('productSymbol').value = p.symbol;
            document.getElementById('productName').value = p.name;
            document.getElementById('productCategory').value = p.category;
            document.getElementById('productContractSize').value = p.contract_size;
            document.getElementById('productSpread').value = p.spread || 0;
            document.getElementById('productPointValue').value = p.point_value || 0;
            document.getElementById('productDigits').value = p.digits || 5;
            document.getElementById('productMinLot').value = p.min_lot;
            document.getElementById('productMaxLot').value = p.max_lot;
            document.getElementById('productLotStep').value = p.lot_step || 0.01;
            document.getElementById('productMarginRate').value = p.margin_rate || 1;
            document.getElementById('productBaseCurrency').value = p.base_currency || '';
            document.getElementById('productQuoteCurrency').value = p.quote_currency || '';
            document.getElementById('productProfitCurrency').value = p.profit_currency || '';
            document.getElementById('productCommission').value = p.commission || 0;
            document.getElementById('productSwapLong').value = p.swap_long || 0;
            document.getElementById('productSwapShort').value = p.swap_short || 0;
            document.getElementById('productIsActive').checked = p.is_active;
            document.getElementById('productIsTradeAllowed').checked = p.is_trade_allowed;
            document.getElementById('productRemark').value = p.remark || '';
            document.getElementById('productSymbol').disabled = true;
            document.getElementById('editModal').classList.remove('hidden');
        }
    })
    .catch(err => console.error('Load product error:', err));
}

function saveProduct() {
    const id = document.getElementById('productId').value;
    const data = {
        symbol: document.getElementById('productSymbol').value,
        name: document.getElementById('productName').value,
        category: document.getElementById('productCategory').value,
        contract_size: document.getElementById('productContractSize').value,
        spread: document.getElementById('productSpread').value,
        point_value: document.getElementById('productPointValue').value,
        digits: document.getElementById('productDigits').value,
        min_lot: document.getElementById('productMinLot').value,
        max_lot: document.getElementById('productMaxLot').value,
        lot_step: document.getElementById('productLotStep').value,
        margin_rate: document.getElementById('productMarginRate').value,
        base_currency: document.getElementById('productBaseCurrency').value,
        quote_currency: document.getElementById('productQuoteCurrency').value,
        profit_currency: document.getElementById('productProfitCurrency').value,
        commission: document.getElementById('productCommission').value,
        swap_long: document.getElementById('productSwapLong').value,
        swap_short: document.getElementById('productSwapShort').value,
        is_active: document.getElementById('productIsActive').checked,
        is_trade_allowed: document.getElementById('productIsTradeAllowed').checked,
        remark: document.getElementById('productRemark').value
    };

    const url = id
        ? `{{ route('admin_api_productions_update', ['id' => '__ID__']) }}`.replace('__ID__', id)
        : '{{ route("admin_api_productions_create") }}';

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
            alert(id ? '产品更新成功' : '产品创建成功');
            closeEditModal();
            loadStats();
            loadProducts();
        } else {
            alert(data.message || '保存失败');
        }
    })
    .catch(err => {
        console.error('Save error:', err);
        alert('网络错误，请稍后重试');
    });
}

function toggleProduct(id, currentStatus) {
    const action = currentStatus ? '禁用' : '启用';
    if (!confirm(`确定要${action}此产品吗？`)) {
        return;
    }

    fetch(`{{ route('admin_api_productions_toggle', ['id' => '__ID__']) }}`.replace('__ID__', id), {
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
            alert(`产品${action}成功`);
            loadStats();
            loadProducts();
        } else {
            alert(data.message || `${action}失败`);
        }
    })
    .catch(err => {
        console.error('Toggle error:', err);
        alert('网络错误，请稍后重试');
    });
}

function deleteProduct(id) {
    if (!confirm('确定要删除此产品吗？此操作不可恢复。')) {
        return;
    }

    fetch(`{{ route('admin_api_productions_delete', ['id' => '__ID__']) }}`.replace('__ID__', id), {
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
            alert('产品删除成功');
            loadStats();
            loadProducts();
        } else {
            alert(data.message || '删除失败');
        }
    })
    .catch(err => {
        console.error('Delete error:', err);
        alert('网络错误，请稍后重试');
    });
}

function syncProducts() {
    if (!confirm('确定要从MT4服务器同步产品数据吗？')) {
        return;
    }

    fetch('{{ route("admin_api_productions_sync") }}', {
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
            alert('产品同步成功');
            loadStats();
            loadProducts();
        } else {
            alert(data.message || '同步失败');
        }
    })
    .catch(err => {
        console.error('Sync error:', err);
        alert('网络错误，请稍后重试');
    });
}

function closeEditModal() {
    document.getElementById('editModal').classList.add('hidden');
}

function searchProducts() {
    loadProducts();
}

function formatNumber(num, decimals = 2) {
    return new Intl.NumberFormat('en-US', {
        minimumFractionDigits: decimals,
        maximumFractionDigits: decimals
    }).format(num);
}

function showError(message) {
    document.getElementById('productsTable').innerHTML = `
        <tr>
            <td colspan="9" class="px-6 py-12 text-center">
                <i class="fas fa-exclamation-circle text-5xl text-red-500 mb-4"></i>
                <p class="text-red-600">${message}</p>
            </td>
        </tr>
    `;
}
</script>
@endsection
