@extends('admin-tailwind.layouts.app')

@section('title', '礼品管理 - 管理后台')

@section('content')
<!-- Page Header -->
<div class="flex items-center justify-between mb-8">
    <div>
        <h1 class="text-3xl font-bold text-slate-800">礼品管理</h1>
        <p class="text-slate-600 mt-2">管理积分兑换礼品和活动奖励</p>
    </div>
    <div class="flex gap-3">
        <button onclick="refreshGifts()" class="px-6 py-3 bg-gradient-to-r from-blue-500 to-indigo-600 text-white font-semibold rounded-lg hover:shadow-lg transition">
            <i class="fas fa-sync-alt mr-2"></i>刷新
        </button>
        <button onclick="addGift()" class="px-6 py-3 bg-gradient-to-r from-green-500 to-emerald-600 text-white font-semibold rounded-lg hover:shadow-lg transition">
            <i class="fas fa-plus mr-2"></i>新增礼品
        </button>
    </div>
</div>

<!-- Stats Cards -->
<div class="grid grid-cols-1 md:grid-cols-5 gap-6 mb-6">
    <div class="bg-white rounded-xl shadow-lg p-6 border-l-4 border-blue-500">
        <p class="text-sm text-slate-600 mb-2">总礼品数</p>
        <p class="text-3xl font-bold text-slate-800" id="totalGifts">0</p>
    </div>
    <div class="bg-white rounded-xl shadow-lg p-6 border-l-4 border-green-500">
        <p class="text-sm text-slate-600 mb-2">上架中</p>
        <p class="text-3xl font-bold text-green-600" id="activeGifts">0</p>
    </div>
    <div class="bg-white rounded-xl shadow-lg p-6 border-l-4 border-red-500">
        <p class="text-sm text-slate-600 mb-2">已下架</p>
        <p class="text-3xl font-bold text-red-600" id="inactiveGifts">0</p>
    </div>
    <div class="bg-white rounded-xl shadow-lg p-6 border-l-4 border-purple-500">
        <p class="text-sm text-slate-600 mb-2">总兑换次数</p>
        <p class="text-3xl font-bold text-purple-600" id="totalExchanges">0</p>
    </div>
    <div class="bg-white rounded-xl shadow-lg p-6 border-l-4 border-orange-500">
        <p class="text-sm text-slate-600 mb-2">总积分消耗</p>
        <p class="text-3xl font-bold text-orange-600" id="totalPoints">0</p>
    </div>
</div>

<!-- Filter Bar -->
<div class="bg-white rounded-xl shadow-lg p-6 mb-6">
    <div class="grid grid-cols-1 md:grid-cols-5 gap-4">
        <div>
            <label class="block text-sm font-semibold text-slate-700 mb-2">搜索</label>
            <input type="text" id="searchKeyword" placeholder="礼品名称" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
        </div>
        <div>
            <label class="block text-sm font-semibold text-slate-700 mb-2">分类</label>
            <select id="filterCategory" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                <option value="">全部</option>
                <option value="electronics">电子产品</option>
                <option value="household">家居用品</option>
                <option value="voucher">代金券</option>
                <option value="other">其他</option>
            </select>
        </div>
        <div>
            <label class="block text-sm font-semibold text-slate-700 mb-2">状态</label>
            <select id="filterStatus" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                <option value="">全部</option>
                <option value="active">上架</option>
                <option value="inactive">下架</option>
            </select>
        </div>
        <div>
            <label class="block text-sm font-semibold text-slate-700 mb-2">排序</label>
            <select id="filterSort" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                <option value="points_asc">积分从低到高</option>
                <option value="points_desc">积分从高到低</option>
                <option value="exchange_desc">兑换次数从高到低</option>
            </select>
        </div>
        <div class="flex items-end">
            <button onclick="searchGifts()" class="w-full px-6 py-2 bg-gradient-to-r from-blue-500 to-indigo-600 text-white font-semibold rounded-lg hover:shadow-lg transition">
                <i class="fas fa-search mr-2"></i>搜索
            </button>
        </div>
    </div>
</div>

<!-- Gifts Grid -->
<div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-6" id="giftsGrid">
    <div class="col-span-full text-center py-12">
        <i class="fas fa-spinner fa-spin text-3xl text-slate-500 mb-3"></i>
        <p class="text-slate-600">加载中...</p>
    </div>
</div>

<!-- Edit Modal -->
<div id="editModal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl max-w-3xl w-full max-h-[90vh] overflow-y-auto">
        <div class="sticky top-0 bg-gradient-to-r from-blue-500 to-indigo-600 px-6 py-4 flex items-center justify-between">
            <h3 class="text-xl font-bold text-white" id="modalTitle">编辑礼品</h3>
            <button onclick="closeEditModal()" class="text-white hover:text-slate-200">
                <i class="fas fa-times text-xl"></i>
            </button>
        </div>
        <div class="p-6">
            <form id="giftForm">
                <input type="hidden" id="giftId">

                <div class="grid grid-cols-2 gap-4 mb-4">
                    <div>
                        <label class="block text-sm font-semibold text-slate-700 mb-2">礼品名称 *</label>
                        <input type="text" id="giftName" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" required>
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-slate-700 mb-2">分类 *</label>
                        <select id="giftCategory" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" required>
                            <option value="electronics">电子产品</option>
                            <option value="household">家居用品</option>
                            <option value="voucher">代金券</option>
                            <option value="other">其他</option>
                        </select>
                    </div>
                </div>

                <div class="grid grid-cols-3 gap-4 mb-4">
                    <div>
                        <label class="block text-sm font-semibold text-slate-700 mb-2">所需积分 *</label>
                        <input type="number" id="giftPoints" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" required>
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-slate-700 mb-2">库存数量 *</label>
                        <input type="number" id="giftStock" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" required>
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-slate-700 mb-2">市场价值</label>
                        <input type="number" id="giftMarketValue" step="0.01" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>
                </div>

                <div class="mb-4">
                    <label class="block text-sm font-semibold text-slate-700 mb-2">礼品图片URL</label>
                    <input type="text" id="giftImage" placeholder="https://example.com/image.jpg" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>

                <div class="mb-4">
                    <label class="block text-sm font-semibold text-slate-700 mb-2">礼品描述</label>
                    <textarea id="giftDescription" rows="3" placeholder="详细描述礼品的特点、规格、使用说明等" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"></textarea>
                </div>

                <div class="grid grid-cols-2 gap-4 mb-4">
                    <div>
                        <label class="block text-sm font-semibold text-slate-700 mb-2">每人限兑次数</label>
                        <input type="number" id="giftLimitPerUser" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                        <p class="text-xs text-slate-500 mt-1">0表示不限制</p>
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-slate-700 mb-2">排序权重</label>
                        <input type="number" id="giftSort" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                        <p class="text-xs text-slate-500 mt-1">数值越大越靠前</p>
                    </div>
                </div>

                <div class="mb-4 space-y-2">
                    <label class="flex items-center gap-2">
                        <input type="checkbox" id="giftIsActive" class="w-4 h-4 text-blue-600 rounded focus:ring-2 focus:ring-blue-500">
                        <span class="text-sm font-semibold text-slate-700">上架显示</span>
                    </label>
                    <label class="flex items-center gap-2">
                        <input type="checkbox" id="giftIsHot" class="w-4 h-4 text-blue-600 rounded focus:ring-2 focus:ring-blue-500">
                        <span class="text-sm font-semibold text-slate-700">热门推荐</span>
                    </label>
                    <label class="flex items-center gap-2">
                        <input type="checkbox" id="giftNeedAddress" class="w-4 h-4 text-blue-600 rounded focus:ring-2 focus:ring-blue-500">
                        <span class="text-sm font-semibold text-slate-700">需要收货地址</span>
                    </label>
                </div>

                <div class="mb-6">
                    <label class="block text-sm font-semibold text-slate-700 mb-2">备注说明</label>
                    <textarea id="giftRemark" rows="2" placeholder="选填：礼品来源、发货说明、注意事项等" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"></textarea>
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

<!-- Exchange Records Modal -->
<div id="exchangeModal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl max-w-5xl w-full max-h-[90vh] overflow-y-auto">
        <div class="sticky top-0 bg-gradient-to-r from-purple-500 to-pink-600 px-6 py-4 flex items-center justify-between">
            <h3 class="text-xl font-bold text-white">兑换记录</h3>
            <button onclick="closeExchangeModal()" class="text-white hover:text-slate-200">
                <i class="fas fa-times text-xl"></i>
            </button>
        </div>
        <div id="exchangeContent" class="p-6"></div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    loadStats();
    loadGifts();

    document.getElementById('giftForm').addEventListener('submit', function(e) {
        e.preventDefault();
        saveGift();
    });
});

function loadStats() {
    fetch('{{ route("admin_api_gifts_stats") }}', {
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(res => res.json())
    .then(data => {
        if (data.success || data.code === 200) {
            document.getElementById('totalGifts').textContent = data.total || 0;
            document.getElementById('activeGifts').textContent = data.active || 0;
            document.getElementById('inactiveGifts').textContent = data.inactive || 0;
            document.getElementById('totalExchanges').textContent = data.totalExchanges || 0;
            document.getElementById('totalPoints').textContent = formatNumber(data.totalPoints || 0, 0);
        }
    })
    .catch(err => console.error('Load stats error:', err));
}

function loadGifts() {
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

    fetch(`{{ route('admin_api_gifts_list') }}?${params}`, {
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(res => res.json())
    .then(data => {
        if (data.success || data.code === 200) {
            renderGifts(data.gifts || []);
        } else {
            showError(data.message || '加载失败');
        }
    })
    .catch(err => {
        console.error('Load error:', err);
        showError('网络错误，请稍后重试');
    });
}

function renderGifts(gifts) {
    const grid = document.getElementById('giftsGrid');

    if (gifts.length === 0) {
        grid.innerHTML = `
            <div class="col-span-full bg-white rounded-xl shadow-lg p-12 text-center">
                <i class="fas fa-inbox text-5xl text-slate-300 mb-4"></i>
                <p class="text-slate-600">暂无礼品数据</p>
            </div>
        `;
        return;
    }

    grid.innerHTML = gifts.map(g => `
        <div class="bg-white rounded-xl shadow-lg overflow-hidden hover:shadow-xl transition">
            ${g.image ? `
                <div class="h-48 bg-gradient-to-br from-slate-100 to-slate-200 flex items-center justify-center overflow-hidden">
                    <img src="${g.image}" alt="${g.name}" class="w-full h-full object-cover">
                </div>
            ` : `
                <div class="h-48 bg-gradient-to-br from-slate-100 to-slate-200 flex items-center justify-center">
                    <i class="fas fa-gift text-6xl text-slate-300"></i>
                </div>
            `}

            <div class="p-6">
                <div class="flex items-start justify-between mb-3">
                    <h3 class="text-lg font-bold text-slate-800 flex-1">${g.name || 'N/A'}</h3>
                    ${g.is_hot ? '<span class="ml-2 px-2 py-1 bg-red-100 text-red-700 text-xs font-semibold rounded-full">HOT</span>' : ''}
                </div>

                <div class="flex items-center justify-between mb-4">
                    <div>
                        <p class="text-xs text-slate-500">所需积分</p>
                        <p class="text-2xl font-bold text-blue-600">${formatNumber(g.points || 0, 0)}</p>
                    </div>
                    <div class="text-right">
                        <p class="text-xs text-slate-500">库存</p>
                        <p class="text-lg font-bold ${g.stock > 0 ? 'text-green-600' : 'text-red-600'}">${g.stock || 0}</p>
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-2 mb-4">
                    <div class="bg-purple-50 rounded-lg p-2 text-center">
                        <p class="text-xs text-purple-600">已兑换</p>
                        <p class="text-sm font-bold text-purple-700">${g.exchange_count || 0}</p>
                    </div>
                    <div class="bg-orange-50 rounded-lg p-2 text-center">
                        <p class="text-xs text-orange-600">市场价</p>
                        <p class="text-sm font-bold text-orange-700">${g.market_value ? '$' + formatNumber(g.market_value) : '-'}</p>
                    </div>
                </div>

                <div class="mb-4">
                    ${getCategoryBadge(g.category)}
                    ${getStatusBadge(g.is_active)}
                </div>

                <div class="flex gap-2">
                    <button onclick="viewExchanges(${g.id}, '${g.name}')" class="flex-1 px-3 py-2 bg-purple-100 text-purple-700 text-xs font-semibold rounded hover:bg-purple-200 transition">
                        <i class="fas fa-list mr-1"></i>记录
                    </button>
                    <button onclick="editGift(${g.id})" class="flex-1 px-3 py-2 bg-blue-100 text-blue-700 text-xs font-semibold rounded hover:bg-blue-200 transition">
                        <i class="fas fa-edit mr-1"></i>编辑
                    </button>
                    <button onclick="toggleGift(${g.id}, ${g.is_active})" class="flex-1 px-3 py-2 bg-${g.is_active ? 'red' : 'green'}-100 text-${g.is_active ? 'red' : 'green'}-700 text-xs font-semibold rounded hover:bg-${g.is_active ? 'red' : 'green'}-200 transition">
                        <i class="fas fa-${g.is_active ? 'eye-slash' : 'eye'}"></i>
                    </button>
                    <button onclick="deleteGift(${g.id})" class="flex-1 px-3 py-2 bg-red-100 text-red-700 text-xs font-semibold rounded hover:bg-red-200 transition">
                        <i class="fas fa-trash"></i>
                    </button>
                </div>
            </div>
        </div>
    `).join('');
}

function getCategoryBadge(category) {
    const badges = {
        'electronics': '<span class="px-2 py-1 bg-blue-100 text-blue-700 text-xs font-semibold rounded-full">电子产品</span>',
        'household': '<span class="px-2 py-1 bg-green-100 text-green-700 text-xs font-semibold rounded-full">家居用品</span>',
        'voucher': '<span class="px-2 py-1 bg-orange-100 text-orange-700 text-xs font-semibold rounded-full">代金券</span>',
        'other': '<span class="px-2 py-1 bg-slate-100 text-slate-700 text-xs font-semibold rounded-full">其他</span>'
    };
    return badges[category] || badges['other'];
}

function getStatusBadge(isActive) {
    return isActive
        ? '<span class="px-2 py-1 bg-green-100 text-green-700 text-xs font-semibold rounded-full">上架</span>'
        : '<span class="px-2 py-1 bg-red-100 text-red-700 text-xs font-semibold rounded-full">下架</span>';
}

function addGift() {
    document.getElementById('modalTitle').textContent = '新增礼品';
    document.getElementById('giftId').value = '';
    document.getElementById('giftName').value = '';
    document.getElementById('giftCategory').value = 'electronics';
    document.getElementById('giftPoints').value = '1000';
    document.getElementById('giftStock').value = '100';
    document.getElementById('giftMarketValue').value = '';
    document.getElementById('giftImage').value = '';
    document.getElementById('giftDescription').value = '';
    document.getElementById('giftLimitPerUser').value = '0';
    document.getElementById('giftSort').value = '0';
    document.getElementById('giftIsActive').checked = true;
    document.getElementById('giftIsHot').checked = false;
    document.getElementById('giftNeedAddress').checked = true;
    document.getElementById('giftRemark').value = '';
    document.getElementById('editModal').classList.remove('hidden');
}

function editGift(id) {
    fetch(`{{ route('admin_api_gifts_detail', ['id' => '__ID__']) }}`.replace('__ID__', id), {
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(res => res.json())
    .then(data => {
        if (data.success && data.gift) {
            const g = data.gift;
            document.getElementById('modalTitle').textContent = '编辑礼品';
            document.getElementById('giftId').value = g.id;
            document.getElementById('giftName').value = g.name;
            document.getElementById('giftCategory').value = g.category;
            document.getElementById('giftPoints').value = g.points;
            document.getElementById('giftStock').value = g.stock;
            document.getElementById('giftMarketValue').value = g.market_value || '';
            document.getElementById('giftImage').value = g.image || '';
            document.getElementById('giftDescription').value = g.description || '';
            document.getElementById('giftLimitPerUser').value = g.limit_per_user || 0;
            document.getElementById('giftSort').value = g.sort || 0;
            document.getElementById('giftIsActive').checked = g.is_active;
            document.getElementById('giftIsHot').checked = g.is_hot;
            document.getElementById('giftNeedAddress').checked = g.need_address;
            document.getElementById('giftRemark').value = g.remark || '';
            document.getElementById('editModal').classList.remove('hidden');
        }
    })
    .catch(err => console.error('Load gift error:', err));
}

function saveGift() {
    const id = document.getElementById('giftId').value;
    const data = {
        name: document.getElementById('giftName').value,
        category: document.getElementById('giftCategory').value,
        points: document.getElementById('giftPoints').value,
        stock: document.getElementById('giftStock').value,
        market_value: document.getElementById('giftMarketValue').value,
        image: document.getElementById('giftImage').value,
        description: document.getElementById('giftDescription').value,
        limit_per_user: document.getElementById('giftLimitPerUser').value,
        sort: document.getElementById('giftSort').value,
        is_active: document.getElementById('giftIsActive').checked,
        is_hot: document.getElementById('giftIsHot').checked,
        need_address: document.getElementById('giftNeedAddress').checked,
        remark: document.getElementById('giftRemark').value
    };

    const url = id
        ? `{{ route('admin_api_gifts_update', ['id' => '__ID__']) }}`.replace('__ID__', id)
        : '{{ route("admin_api_gifts_create") }}';

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
            alert(id ? '礼品更新成功' : '礼品创建成功');
            closeEditModal();
            loadStats();
            loadGifts();
        } else {
            alert(data.message || '保存失败');
        }
    })
    .catch(err => {
        console.error('Save error:', err);
        alert('网络错误，请稍后重试');
    });
}

function toggleGift(id, currentStatus) {
    const action = currentStatus ? '下架' : '上架';
    if (!confirm(`确定要${action}此礼品吗？`)) {
        return;
    }

    fetch(`{{ route('admin_api_gifts_toggle', ['id' => '__ID__']) }}`.replace('__ID__', id), {
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
            alert(`礼品${action}成功`);
            loadStats();
            loadGifts();
        } else {
            alert(data.message || `${action}失败`);
        }
    })
    .catch(err => {
        console.error('Toggle error:', err);
        alert('网络错误，请稍后重试');
    });
}

function deleteGift(id) {
    if (!confirm('确定要删除此礼品吗？此操作不可恢复。')) {
        return;
    }

    fetch(`{{ route('admin_api_gifts_delete', ['id' => '__ID__']) }}`.replace('__ID__', id), {
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
            alert('礼品删除成功');
            loadStats();
            loadGifts();
        } else {
            alert(data.message || '删除失败');
        }
    })
    .catch(err => {
        console.error('Delete error:', err);
        alert('网络错误，请稍后重试');
    });
}

function viewExchanges(giftId, giftName) {
    fetch(`{{ route('admin_api_gifts_exchanges', ['id' => '__ID__']) }}`.replace('__ID__', giftId), {
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(res => res.json())
    .then(data => {
        if (data.success && data.exchanges) {
            const exchanges = data.exchanges;
            document.getElementById('exchangeContent').innerHTML = `
                <h4 class="text-lg font-bold text-slate-800 mb-4">${giftName} - 兑换记录 (${exchanges.length})</h4>
                ${exchanges.length === 0 ? `
                    <div class="text-center py-12">
                        <i class="fas fa-inbox text-5xl text-slate-300 mb-4"></i>
                        <p class="text-slate-600">暂无兑换记录</p>
                    </div>
                ` : `
                    <div class="overflow-x-auto">
                        <table class="w-full">
                            <thead class="bg-slate-100">
                                <tr>
                                    <th class="px-4 py-3 text-left text-xs font-semibold text-slate-700">用户</th>
                                    <th class="px-4 py-3 text-right text-xs font-semibold text-slate-700">消耗积分</th>
                                    <th class="px-4 py-3 text-left text-xs font-semibold text-slate-700">收货地址</th>
                                    <th class="px-4 py-3 text-center text-xs font-semibold text-slate-700">状态</th>
                                    <th class="px-4 py-3 text-left text-xs font-semibold text-slate-700">兑换时间</th>
                                </tr>
                            </thead>
                            <tbody>
                                ${exchanges.map(e => `
                                    <tr class="border-b border-slate-200 hover:bg-slate-50">
                                        <td class="px-4 py-3 text-sm text-slate-800">${e.user_name || '-'}</td>
                                        <td class="px-4 py-3 text-sm text-right font-semibold text-blue-600">${formatNumber(e.points || 0, 0)}</td>
                                        <td class="px-4 py-3 text-sm text-slate-600">${e.address || '无需地址'}</td>
                                        <td class="px-4 py-3 text-center">${getExchangeStatusBadge(e.status)}</td>
                                        <td class="px-4 py-3 text-sm text-slate-600">${e.created_at || '-'}</td>
                                    </tr>
                                `).join('')}
                            </tbody>
                        </table>
                    </div>
                `}
            `;
            document.getElementById('exchangeModal').classList.remove('hidden');
        }
    })
    .catch(err => console.error('Load exchanges error:', err));
}

function getExchangeStatusBadge(status) {
    const badges = {
        'pending': '<span class="px-2 py-1 bg-yellow-100 text-yellow-700 text-xs rounded-full">待发货</span>',
        'shipped': '<span class="px-2 py-1 bg-blue-100 text-blue-700 text-xs rounded-full">已发货</span>',
        'completed': '<span class="px-2 py-1 bg-green-100 text-green-700 text-xs rounded-full">已完成</span>',
        'cancelled': '<span class="px-2 py-1 bg-red-100 text-red-700 text-xs rounded-full">已取消</span>'
    };
    return badges[status] || '<span class="px-2 py-1 bg-slate-100 text-slate-700 text-xs rounded-full">未知</span>';
}

function closeEditModal() {
    document.getElementById('editModal').classList.add('hidden');
}

function closeExchangeModal() {
    document.getElementById('exchangeModal').classList.add('hidden');
}

function refreshGifts() {
    loadStats();
    loadGifts();
}

function searchGifts() {
    loadGifts();
}

function formatNumber(num, decimals = 2) {
    return new Intl.NumberFormat('en-US', {
        minimumFractionDigits: decimals,
        maximumFractionDigits: decimals
    }).format(num);
}

function showError(message) {
    document.getElementById('giftsGrid').innerHTML = `
        <div class="col-span-full bg-white rounded-xl shadow-lg p-12 text-center">
            <i class="fas fa-exclamation-circle text-5xl text-red-500 mb-4"></i>
            <p class="text-red-600">${message}</p>
        </div>
    `;
}
</script>
@endsection
