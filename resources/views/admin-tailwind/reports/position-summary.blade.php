@extends('admin-tailwind.layouts.app')

@section('title', '持仓汇总 - 管理后台')

@section('content')
<!-- Page Header -->
<div class="flex items-center justify-between mb-8">
    <div>
        <h1 class="text-3xl font-bold text-slate-800">持仓汇总</h1>
        <p class="text-slate-600 mt-2">查看所有用户的持仓情况和盈亏统计</p>
    </div>
    <div class="flex gap-3">
        <button onclick="refreshData()" class="px-6 py-3 bg-gradient-to-r from-blue-500 to-indigo-600 text-white font-semibold rounded-lg hover:shadow-lg transition">
            <i class="fas fa-sync-alt mr-2"></i>刷新数据
        </button>
        <button onclick="exportPositions()" class="px-6 py-3 bg-gradient-to-r from-green-500 to-green-600 text-white font-semibold rounded-lg hover:shadow-lg transition">
            <i class="fas fa-download mr-2"></i>导出报表
        </button>
    </div>
</div>

<!-- Stats Cards -->
<div class="grid grid-cols-1 md:grid-cols-5 gap-6 mb-6">
    <div class="bg-white rounded-xl shadow-lg p-6 border-l-4 border-blue-500">
        <p class="text-sm text-slate-600 mb-2">持仓用户</p>
        <p class="text-3xl font-bold text-slate-800" id="totalUsers">0</p>
    </div>
    <div class="bg-white rounded-xl shadow-lg p-6 border-l-4 border-purple-500">
        <p class="text-sm text-slate-600 mb-2">持仓订单</p>
        <p class="text-3xl font-bold text-slate-800" id="totalOrders">0</p>
    </div>
    <div class="bg-white rounded-xl shadow-lg p-6 border-l-4 border-indigo-500">
        <p class="text-sm text-slate-600 mb-2">总手数</p>
        <p class="text-3xl font-bold text-slate-800" id="totalLots">0</p>
    </div>
    <div class="bg-white rounded-xl shadow-lg p-6 border-l-4 border-green-500">
        <p class="text-sm text-slate-600 mb-2">总盈利</p>
        <p class="text-3xl font-bold text-green-600">$<span id="totalProfit">0</span></p>
    </div>
    <div class="bg-white rounded-xl shadow-lg p-6 border-l-4 border-red-500">
        <p class="text-sm text-slate-600 mb-2">总亏损</p>
        <p class="text-3xl font-bold text-red-600">$<span id="totalLoss">0</span></p>
    </div>
</div>

<!-- Filter Bar -->
<div class="bg-white rounded-xl shadow-lg p-6 mb-6">
    <div class="grid grid-cols-1 md:grid-cols-6 gap-4">
        <div>
            <label class="block text-sm font-semibold text-slate-700 mb-2">搜索</label>
            <input type="text" id="searchKeyword" placeholder="用户名/MT4账号" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
        </div>
        <div>
            <label class="block text-sm font-semibold text-slate-700 mb-2">交易品种</label>
            <select id="filterSymbol" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                <option value="">全部品种</option>
                <option value="EURUSD">EURUSD</option>
                <option value="GBPUSD">GBPUSD</option>
                <option value="USDJPY">USDJPY</option>
                <option value="XAUUSD">XAUUSD</option>
                <option value="CRUDE">CRUDE</option>
            </select>
        </div>
        <div>
            <label class="block text-sm font-semibold text-slate-700 mb-2">盈亏状态</label>
            <select id="filterProfitStatus" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                <option value="">全部状态</option>
                <option value="profit">盈利</option>
                <option value="loss">亏损</option>
                <option value="breakeven">持平</option>
            </select>
        </div>
        <div>
            <label class="block text-sm font-semibold text-slate-700 mb-2">持仓时长</label>
            <select id="filterDuration" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                <option value="">全部时长</option>
                <option value="0-1">1小时内</option>
                <option value="1-24">1-24小时</option>
                <option value="24-168">1-7天</option>
                <option value="168+">7天以上</option>
            </select>
        </div>
        <div>
            <label class="block text-sm font-semibold text-slate-700 mb-2">排序方式</label>
            <select id="filterSort" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                <option value="profit_desc">盈亏从高到低</option>
                <option value="profit_asc">盈亏从低到高</option>
                <option value="lots_desc">手数从大到小</option>
                <option value="time_desc">时间从新到旧</option>
            </select>
        </div>
        <div class="flex items-end">
            <button onclick="searchPositions()" class="w-full px-6 py-2 bg-gradient-to-r from-blue-500 to-indigo-600 text-white font-semibold rounded-lg hover:shadow-lg transition">
                <i class="fas fa-search mr-2"></i>搜索
            </button>
        </div>
    </div>
</div>

<!-- Position Summary Table -->
<div class="bg-white rounded-xl shadow-lg overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full">
            <thead class="bg-slate-50 border-b border-slate-200">
                <tr>
                    <th class="px-6 py-4 text-left text-xs font-semibold text-slate-600 uppercase tracking-wider">用户信息</th>
                    <th class="px-6 py-4 text-left text-xs font-semibold text-slate-600 uppercase tracking-wider">MT4订单号</th>
                    <th class="px-6 py-4 text-left text-xs font-semibold text-slate-600 uppercase tracking-wider">交易品种</th>
                    <th class="px-6 py-4 text-left text-xs font-semibold text-slate-600 uppercase tracking-wider">类型</th>
                    <th class="px-6 py-4 text-left text-xs font-semibold text-slate-600 uppercase tracking-wider">手数</th>
                    <th class="px-6 py-4 text-left text-xs font-semibold text-slate-600 uppercase tracking-wider">开仓价</th>
                    <th class="px-6 py-4 text-left text-xs font-semibold text-slate-600 uppercase tracking-wider">当前价</th>
                    <th class="px-6 py-4 text-left text-xs font-semibold text-slate-600 uppercase tracking-wider">浮动盈亏</th>
                    <th class="px-6 py-4 text-left text-xs font-semibold text-slate-600 uppercase tracking-wider">持仓时长</th>
                    <th class="px-6 py-4 text-left text-xs font-semibold text-slate-600 uppercase tracking-wider">操作</th>
                </tr>
            </thead>
            <tbody id="positionsTableBody" class="divide-y divide-slate-200">
                <tr>
                    <td colspan="10" class="px-6 py-12 text-center text-slate-500">
                        <i class="fas fa-spinner fa-spin text-3xl mb-3"></i>
                        <p>加载中...</p>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>

    <!-- Pagination -->
    <div class="px-6 py-4 bg-slate-50 border-t border-slate-200 flex items-center justify-between">
        <div class="text-sm text-slate-600">
            显示第 <span id="pageStart">0</span> - <span id="pageEnd">0</span> 条，共 <span id="totalRecords">0</span> 条
        </div>
        <div class="flex gap-2" id="pagination"></div>
    </div>
</div>

<!-- Detail Modal -->
<div id="detailModal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl max-w-3xl w-full max-h-[90vh] overflow-y-auto">
        <div class="sticky top-0 bg-gradient-to-r from-indigo-500 to-purple-600 px-6 py-4 flex items-center justify-between">
            <h3 class="text-xl font-bold text-white">持仓详情</h3>
            <button onclick="closeDetailModal()" class="text-white hover:text-slate-200">
                <i class="fas fa-times text-xl"></i>
            </button>
        </div>
        <div id="modalContent" class="p-6"></div>
    </div>
</div>

<script>
let currentPage = 1;
let totalPages = 1;

document.addEventListener('DOMContentLoaded', function() {
    loadStats();
    loadPositions();

    // Auto refresh every 30 seconds
    setInterval(() => {
        loadStats();
        loadPositions(currentPage);
    }, 30000);
});

function loadStats() {
    fetch('{{ route("admin_api_position_summary_stats") }}', {
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(res => res.json())
    .then(data => {
        if (data.success || data.code === 200) {
            document.getElementById('totalUsers').textContent = formatNumber(data.totalUsers || 0);
            document.getElementById('totalOrders').textContent = formatNumber(data.totalOrders || 0);
            document.getElementById('totalLots').textContent = formatNumber(data.totalLots || 0);
            document.getElementById('totalProfit').textContent = formatNumber(data.totalProfit || 0);
            document.getElementById('totalLoss').textContent = formatNumber(Math.abs(data.totalLoss || 0));
        }
    })
    .catch(err => console.error('Load stats error:', err));
}

function loadPositions(page = 1) {
    currentPage = page;
    const keyword = document.getElementById('searchKeyword').value;
    const symbol = document.getElementById('filterSymbol').value;
    const profitStatus = document.getElementById('filterProfitStatus').value;
    const duration = document.getElementById('filterDuration').value;
    const sort = document.getElementById('filterSort').value;

    const params = new URLSearchParams({
        page: page,
        keyword: keyword,
        symbol: symbol,
        profit_status: profitStatus,
        duration: duration,
        sort: sort
    });

    fetch(`{{ route('admin_api_position_summary_list') }}?${params}`, {
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(res => res.json())
    .then(data => {
        if (data.success || data.code === 200) {
            renderTable(data.positions || []);
            renderPagination(data.pagination || {});
        } else {
            showError(data.message || '加载失败');
        }
    })
    .catch(err => {
        console.error('Load error:', err);
        showError('网络错误，请稍后重试');
    });
}

function renderTable(positions) {
    const tbody = document.getElementById('positionsTableBody');

    if (positions.length === 0) {
        tbody.innerHTML = `
            <tr>
                <td colspan="10" class="px-6 py-12 text-center text-slate-500">
                    <i class="fas fa-inbox text-3xl mb-3"></i>
                    <p>暂无持仓数据</p>
                </td>
            </tr>
        `;
        return;
    }

    tbody.innerHTML = positions.map(p => {
        const duration = calculateDuration(p.open_time);

        return `
        <tr class="hover:bg-slate-50 transition">
            <td class="px-6 py-4">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 bg-gradient-to-br from-indigo-500 to-purple-600 rounded-full flex items-center justify-center text-white font-bold">
                        ${(p.username || 'U').charAt(0).toUpperCase()}
                    </div>
                    <div>
                        <p class="text-sm font-semibold text-slate-800">${p.username || 'N/A'}</p>
                        <p class="text-xs text-slate-500">MT4: ${p.mt4_account || '-'}</p>
                    </div>
                </div>
            </td>
            <td class="px-6 py-4">
                <p class="text-sm font-mono text-slate-800">${p.order_id || '-'}</p>
            </td>
            <td class="px-6 py-4">
                <p class="text-sm font-semibold text-slate-800">${p.symbol || '-'}</p>
            </td>
            <td class="px-6 py-4">${getOrderTypeBadge(p.order_type)}</td>
            <td class="px-6 py-4">
                <p class="text-sm font-semibold text-slate-800">${formatNumber(p.lots || 0)}</p>
            </td>
            <td class="px-6 py-4">
                <p class="text-sm text-slate-600">${formatPrice(p.open_price || 0)}</p>
            </td>
            <td class="px-6 py-4">
                <p class="text-sm text-slate-600">${formatPrice(p.current_price || 0)}</p>
            </td>
            <td class="px-6 py-4">
                <p class="text-base font-bold ${p.profit >= 0 ? 'text-green-600' : 'text-red-600'}">
                    ${p.profit >= 0 ? '+' : ''}$${formatNumber(p.profit || 0)}
                </p>
            </td>
            <td class="px-6 py-4">
                <p class="text-sm text-slate-600">${duration}</p>
            </td>
            <td class="px-6 py-4">
                <button onclick="viewDetail(${p.order_id})" class="px-3 py-1 bg-blue-100 text-blue-700 text-xs font-semibold rounded hover:bg-blue-200 transition">
                    <i class="fas fa-eye mr-1"></i>详情
                </button>
            </td>
        </tr>
        `;
    }).join('');
}

function renderPagination(pagination) {
    document.getElementById('pageStart').textContent = pagination.from || 0;
    document.getElementById('pageEnd').textContent = pagination.to || 0;
    document.getElementById('totalRecords').textContent = pagination.total || 0;

    totalPages = pagination.last_page || 1;
    const paginationDiv = document.getElementById('pagination');

    let html = '';
    if (currentPage > 1) {
        html += `<button onclick="loadPositions(${currentPage - 1})" class="px-3 py-1 border border-slate-300 rounded hover:bg-slate-50">上一页</button>`;
    }
    for (let i = Math.max(1, currentPage - 2); i <= Math.min(totalPages, currentPage + 2); i++) {
        html += `<button onclick="loadPositions(${i})" class="px-3 py-1 border ${i === currentPage ? 'bg-blue-500 text-white' : 'border-slate-300 hover:bg-slate-50'} rounded">${i}</button>`;
    }
    if (currentPage < totalPages) {
        html += `<button onclick="loadPositions(${currentPage + 1})" class="px-3 py-1 border border-slate-300 rounded hover:bg-slate-50">下一页</button>`;
    }
    paginationDiv.innerHTML = html;
}

function getOrderTypeBadge(type) {
    const badges = {
        'buy': '<span class="px-2 py-1 bg-green-100 text-green-700 text-xs font-semibold rounded-full">买入</span>',
        'sell': '<span class="px-2 py-1 bg-red-100 text-red-700 text-xs font-semibold rounded-full">卖出</span>'
    };
    return badges[type] || '<span class="px-2 py-1 bg-slate-100 text-slate-700 text-xs font-semibold rounded-full">-</span>';
}

function calculateDuration(openTime) {
    const open = new Date(openTime);
    const now = new Date();
    const diff = now - open;

    const days = Math.floor(diff / (1000 * 60 * 60 * 24));
    const hours = Math.floor((diff % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
    const minutes = Math.floor((diff % (1000 * 60 * 60)) / (1000 * 60));

    if (days > 0) {
        return `${days}天${hours}小时`;
    } else if (hours > 0) {
        return `${hours}小时${minutes}分钟`;
    } else {
        return `${minutes}分钟`;
    }
}

function searchPositions() {
    loadPositions(1);
}

function refreshData() {
    loadStats();
    loadPositions(currentPage);
}

function viewDetail(orderId) {
    fetch(`{{ route('admin_api_position_summary_detail', ['order_id' => '__ORDER_ID__']) }}`.replace('__ORDER_ID__', orderId), {
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(res => res.json())
    .then(data => {
        if (data.success && data.position) {
            const p = data.position;
            const duration = calculateDuration(p.open_time);

            document.getElementById('modalContent').innerHTML = `
                <div class="space-y-6">
                    <div class="flex items-center justify-center mb-4">
                        <div class="w-16 h-16 bg-gradient-to-br ${p.profit >= 0 ? 'from-green-500 to-emerald-600' : 'from-red-500 to-pink-600'} rounded-full flex items-center justify-center">
                            <i class="fas ${p.order_type === 'buy' ? 'fa-arrow-up' : 'fa-arrow-down'} text-3xl text-white"></i>
                        </div>
                    </div>

                    <div class="text-center mb-6">
                        <p class="text-2xl font-bold ${p.profit >= 0 ? 'text-green-600' : 'text-red-600'} mb-2">
                            ${p.profit >= 0 ? '+' : ''}$${formatNumber(p.profit || 0)}
                        </p>
                        <p class="text-sm text-slate-600">${p.symbol || '-'} · ${formatNumber(p.lots || 0)} 手</p>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div class="col-span-2 bg-slate-50 rounded-lg p-4">
                            <p class="text-sm text-slate-600 mb-1">MT4订单号</p>
                            <p class="text-base font-mono text-slate-800">${p.order_id || '-'}</p>
                        </div>

                        <div><p class="text-sm text-slate-600 mb-1">用户</p><p class="text-base font-semibold text-slate-800">${p.username || 'N/A'}</p></div>
                        <div><p class="text-sm text-slate-600 mb-1">MT4账号</p><p class="text-base font-mono text-slate-800">${p.mt4_account || '-'}</p></div>

                        <div><p class="text-sm text-slate-600 mb-1">交易品种</p><p class="text-base font-semibold text-slate-800">${p.symbol || '-'}</p></div>
                        <div><p class="text-sm text-slate-600 mb-1">订单类型</p><div class="mt-1">${getOrderTypeBadge(p.order_type)}</div></div>

                        <div><p class="text-sm text-slate-600 mb-1">手数</p><p class="text-base font-semibold text-slate-800">${formatNumber(p.lots || 0)}</p></div>
                        <div><p class="text-sm text-slate-600 mb-1">杠杆</p><p class="text-base text-slate-800">1:${p.leverage || 100}</p></div>

                        <div><p class="text-sm text-slate-600 mb-1">开仓价</p><p class="text-base text-slate-800">${formatPrice(p.open_price || 0)}</p></div>
                        <div><p class="text-sm text-slate-600 mb-1">当前价</p><p class="text-base font-semibold text-slate-800">${formatPrice(p.current_price || 0)}</p></div>

                        <div><p class="text-sm text-slate-600 mb-1">止损价</p><p class="text-base text-slate-800">${p.stop_loss ? formatPrice(p.stop_loss) : '-'}</p></div>
                        <div><p class="text-sm text-slate-600 mb-1">止盈价</p><p class="text-base text-slate-800">${p.take_profit ? formatPrice(p.take_profit) : '-'}</p></div>

                        <div><p class="text-sm text-slate-600 mb-1">手续费</p><p class="text-base text-slate-800">$${formatNumber(p.commission || 0)}</p></div>
                        <div><p class="text-sm text-slate-600 mb-1">库存费</p><p class="text-base text-slate-800">$${formatNumber(p.swap || 0)}</p></div>

                        <div><p class="text-sm text-slate-600 mb-1">开仓时间</p><p class="text-base text-slate-800">${p.open_time || '-'}</p></div>
                        <div><p class="text-sm text-slate-600 mb-1">持仓时长</p><p class="text-base font-semibold text-orange-600">${duration}</p></div>

                        <div class="col-span-2 bg-${p.profit >= 0 ? 'green' : 'red'}-50 rounded-lg p-4">
                            <p class="text-sm text-${p.profit >= 0 ? 'green' : 'red'}-600 mb-1">浮动盈亏</p>
                            <p class="text-2xl font-bold text-${p.profit >= 0 ? 'green' : 'red'}-600">${p.profit >= 0 ? '+' : ''}$${formatNumber(p.profit || 0)}</p>
                        </div>
                    </div>

                    ${p.comment ? `
                        <div class="border-t border-slate-200 pt-4">
                            <p class="text-sm text-slate-600 mb-2">订单备注</p>
                            <p class="text-base text-slate-800 bg-slate-50 rounded-lg p-3">${p.comment}</p>
                        </div>
                    ` : ''}
                </div>
            `;
            document.getElementById('detailModal').classList.remove('hidden');
        }
    })
    .catch(err => console.error('Load detail error:', err));
}

function closeDetailModal() {
    document.getElementById('detailModal').classList.add('hidden');
}

function exportPositions() {
    const symbol = document.getElementById('filterSymbol').value;
    const profitStatus = document.getElementById('filterProfitStatus').value;

    let url = '{{ route("admin_api_position_summary_export") }}';
    const params = new URLSearchParams();
    if (symbol) params.append('symbol', symbol);
    if (profitStatus) params.append('profit_status', profitStatus);

    if (params.toString()) {
        url += '?' + params.toString();
    }

    window.location.href = url;
}

function formatNumber(num) {
    return new Intl.NumberFormat('en-US', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
    }).format(num);
}

function formatPrice(price) {
    return new Intl.NumberFormat('en-US', {
        minimumFractionDigits: 5,
        maximumFractionDigits: 5
    }).format(price);
}

function showError(message) {
    document.getElementById('positionsTableBody').innerHTML = `
        <tr><td colspan="10" class="px-6 py-12 text-center text-red-500">
            <i class="fas fa-exclamation-circle text-3xl mb-3"></i><p>${message}</p>
        </td></tr>
    `;
}
</script>
@endsection
