@extends('admin-tailwind.layouts.app')

@section('title', '交易记录 - 管理后台')

@section('content')
<!-- Page Header -->
<div class="flex items-center justify-between mb-8">
    <div>
        <h1 class="text-3xl font-bold text-slate-800">交易记录</h1>
        <p class="text-slate-600 mt-2">查看所有用户的历史交易记录和统计</p>
    </div>
    <div class="flex gap-3">
        <button onclick="refreshData()" class="px-6 py-3 bg-gradient-to-r from-blue-500 to-indigo-600 text-white font-semibold rounded-lg hover:shadow-lg transition">
            <i class="fas fa-sync-alt mr-2"></i>刷新数据
        </button>
        <button onclick="exportTrades()" class="px-6 py-3 bg-gradient-to-r from-green-500 to-green-600 text-white font-semibold rounded-lg hover:shadow-lg transition">
            <i class="fas fa-download mr-2"></i>导出报表
        </button>
    </div>
</div>

<!-- Stats Cards -->
<div class="grid grid-cols-1 md:grid-cols-6 gap-6 mb-6">
    <div class="bg-white rounded-xl shadow-lg p-6 border-l-4 border-blue-500">
        <p class="text-sm text-slate-600 mb-2">今日交易笔数</p>
        <p class="text-3xl font-bold text-slate-800" id="todayCount">0</p>
    </div>
    <div class="bg-white rounded-xl shadow-lg p-6 border-l-4 border-purple-500">
        <p class="text-sm text-slate-600 mb-2">今日交易量</p>
        <p class="text-3xl font-bold text-purple-600" id="todayVolume">0</p>
    </div>
    <div class="bg-white rounded-xl shadow-lg p-6 border-l-4 border-green-500">
        <p class="text-sm text-slate-600 mb-2">今日盈利</p>
        <p class="text-3xl font-bold text-green-600">$<span id="todayProfit">0</span></p>
    </div>
    <div class="bg-white rounded-xl shadow-lg p-6 border-l-4 border-red-500">
        <p class="text-sm text-slate-600 mb-2">今日亏损</p>
        <p class="text-3xl font-bold text-red-600">$<span id="todayLoss">0</span></p>
    </div>
    <div class="bg-white rounded-xl shadow-lg p-6 border-l-4 border-orange-500">
        <p class="text-sm text-slate-600 mb-2">活跃用户</p>
        <p class="text-3xl font-bold text-orange-600" id="activeUsers">0</p>
    </div>
    <div class="bg-white rounded-xl shadow-lg p-6 border-l-4 border-cyan-500">
        <p class="text-sm text-slate-600 mb-2">累计交易量</p>
        <p class="text-3xl font-bold text-cyan-600" id="totalVolume">0</p>
    </div>
</div>

<!-- Filter Bar -->
<div class="bg-white rounded-xl shadow-lg p-6 mb-6">
    <div class="grid grid-cols-1 md:grid-cols-7 gap-4">
        <div>
            <label class="block text-sm font-semibold text-slate-700 mb-2">搜索</label>
            <input type="text" id="searchKeyword" placeholder="订单号/用户名" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
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
            <label class="block text-sm font-semibold text-slate-700 mb-2">订单类型</label>
            <select id="filterOrderType" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                <option value="">全部类型</option>
                <option value="buy">买入</option>
                <option value="sell">卖出</option>
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
            <label class="block text-sm font-semibold text-slate-700 mb-2">开始日期</label>
            <input type="date" id="filterStartDate" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
        </div>
        <div>
            <label class="block text-sm font-semibold text-slate-700 mb-2">结束日期</label>
            <input type="date" id="filterEndDate" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
        </div>
        <div class="flex items-end">
            <button onclick="searchTrades()" class="w-full px-6 py-2 bg-gradient-to-r from-blue-500 to-indigo-600 text-white font-semibold rounded-lg hover:shadow-lg transition">
                <i class="fas fa-search mr-2"></i>搜索
            </button>
        </div>
    </div>
</div>

<!-- Trades Table -->
<div class="bg-white rounded-xl shadow-lg overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full">
            <thead class="bg-slate-50 border-b border-slate-200">
                <tr>
                    <th class="px-6 py-4 text-left text-xs font-semibold text-slate-600 uppercase tracking-wider">订单号</th>
                    <th class="px-6 py-4 text-left text-xs font-semibold text-slate-600 uppercase tracking-wider">用户信息</th>
                    <th class="px-6 py-4 text-left text-xs font-semibold text-slate-600 uppercase tracking-wider">交易品种</th>
                    <th class="px-6 py-4 text-left text-xs font-semibold text-slate-600 uppercase tracking-wider">类型</th>
                    <th class="px-6 py-4 text-left text-xs font-semibold text-slate-600 uppercase tracking-wider">手数</th>
                    <th class="px-6 py-4 text-left text-xs font-semibold text-slate-600 uppercase tracking-wider">开仓价</th>
                    <th class="px-6 py-4 text-left text-xs font-semibold text-slate-600 uppercase tracking-wider">平仓价</th>
                    <th class="px-6 py-4 text-left text-xs font-semibold text-slate-600 uppercase tracking-wider">盈亏</th>
                    <th class="px-6 py-4 text-left text-xs font-semibold text-slate-600 uppercase tracking-wider">交易时间</th>
                    <th class="px-6 py-4 text-left text-xs font-semibold text-slate-600 uppercase tracking-wider">操作</th>
                </tr>
            </thead>
            <tbody id="tradesTableBody" class="divide-y divide-slate-200">
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
    <div class="bg-white rounded-2xl max-w-4xl w-full max-h-[90vh] overflow-y-auto">
        <div class="sticky top-0 bg-gradient-to-r from-blue-500 to-indigo-600 px-6 py-4 flex items-center justify-between">
            <h3 class="text-xl font-bold text-white">交易详情</h3>
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
    loadTrades();
});

function loadStats() {
    fetch('{{ route("admin_api_trades_stats") }}', {
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(res => res.json())
    .then(data => {
        if (data.success || data.code === 200) {
            document.getElementById('todayCount').textContent = formatNumber(data.todayCount || 0);
            document.getElementById('todayVolume').textContent = formatNumber(data.todayVolume || 0);
            document.getElementById('todayProfit').textContent = formatNumber(data.todayProfit || 0);
            document.getElementById('todayLoss').textContent = formatNumber(Math.abs(data.todayLoss || 0));
            document.getElementById('activeUsers').textContent = formatNumber(data.activeUsers || 0);
            document.getElementById('totalVolume').textContent = formatNumber(data.totalVolume || 0);
        }
    })
    .catch(err => console.error('Load stats error:', err));
}

function loadTrades(page = 1) {
    currentPage = page;
    const keyword = document.getElementById('searchKeyword').value;
    const symbol = document.getElementById('filterSymbol').value;
    const orderType = document.getElementById('filterOrderType').value;
    const profitStatus = document.getElementById('filterProfitStatus').value;
    const startDate = document.getElementById('filterStartDate').value;
    const endDate = document.getElementById('filterEndDate').value;

    const params = new URLSearchParams({
        page: page,
        keyword: keyword,
        symbol: symbol,
        order_type: orderType,
        profit_status: profitStatus,
        start_date: startDate,
        end_date: endDate
    });

    fetch(`{{ route('admin_api_trades_list') }}?${params}`, {
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(res => res.json())
    .then(data => {
        if (data.success || data.code === 200) {
            renderTable(data.trades || []);
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

function renderTable(trades) {
    const tbody = document.getElementById('tradesTableBody');

    if (trades.length === 0) {
        tbody.innerHTML = `
            <tr>
                <td colspan="10" class="px-6 py-12 text-center text-slate-500">
                    <i class="fas fa-inbox text-3xl mb-3"></i>
                    <p>暂无交易数据</p>
                </td>
            </tr>
        `;
        return;
    }

    tbody.innerHTML = trades.map(t => {
        const duration = calculateDuration(t.open_time, t.close_time);

        return `
        <tr class="hover:bg-slate-50 transition">
            <td class="px-6 py-4">
                <p class="text-sm font-mono text-slate-800">${t.order_id || '-'}</p>
            </td>
            <td class="px-6 py-4">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 bg-gradient-to-br from-blue-500 to-indigo-600 rounded-full flex items-center justify-center text-white font-bold">
                        ${(t.username || 'U').charAt(0).toUpperCase()}
                    </div>
                    <div>
                        <p class="text-sm font-semibold text-slate-800">${t.username || 'N/A'}</p>
                        <p class="text-xs text-slate-500">MT4: ${t.mt4_account || '-'}</p>
                    </div>
                </div>
            </td>
            <td class="px-6 py-4">
                <p class="text-sm font-semibold text-slate-800">${t.symbol || '-'}</p>
            </td>
            <td class="px-6 py-4">${getOrderTypeBadge(t.order_type)}</td>
            <td class="px-6 py-4">
                <p class="text-sm font-semibold text-slate-800">${formatNumber(t.lots || 0)}</p>
            </td>
            <td class="px-6 py-4">
                <p class="text-sm text-slate-600">${formatPrice(t.open_price || 0)}</p>
            </td>
            <td class="px-6 py-4">
                <p class="text-sm text-slate-600">${formatPrice(t.close_price || 0)}</p>
            </td>
            <td class="px-6 py-4">
                <p class="text-base font-bold ${t.profit >= 0 ? 'text-green-600' : 'text-red-600'}">
                    ${t.profit >= 0 ? '+' : ''}$${formatNumber(t.profit || 0)}
                </p>
            </td>
            <td class="px-6 py-4">
                <div>
                    <p class="text-sm text-slate-600">${t.open_time || '-'}</p>
                    <p class="text-xs text-slate-500">${duration}</p>
                </div>
            </td>
            <td class="px-6 py-4">
                <button onclick="viewDetail(${t.order_id})" class="px-3 py-1 bg-blue-100 text-blue-700 text-xs font-semibold rounded hover:bg-blue-200 transition">
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
        html += `<button onclick="loadTrades(${currentPage - 1})" class="px-3 py-1 border border-slate-300 rounded hover:bg-slate-50">上一页</button>`;
    }
    for (let i = Math.max(1, currentPage - 2); i <= Math.min(totalPages, currentPage + 2); i++) {
        html += `<button onclick="loadTrades(${i})" class="px-3 py-1 border ${i === currentPage ? 'bg-blue-500 text-white' : 'border-slate-300 hover:bg-slate-50'} rounded">${i}</button>`;
    }
    if (currentPage < totalPages) {
        html += `<button onclick="loadTrades(${currentPage + 1})" class="px-3 py-1 border border-slate-300 rounded hover:bg-slate-50">下一页</button>`;
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

function calculateDuration(openTime, closeTime) {
    const open = new Date(openTime);
    const close = new Date(closeTime);
    const diff = close - open;

    const days = Math.floor(diff / (1000 * 60 * 60 * 24));
    const hours = Math.floor((diff % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
    const minutes = Math.floor((diff % (1000 * 60 * 60)) / (1000 * 60));

    if (days > 0) {
        return `持仓 ${days}天${hours}小时`;
    } else if (hours > 0) {
        return `持仓 ${hours}小时${minutes}分钟`;
    } else {
        return `持仓 ${minutes}分钟`;
    }
}

function searchTrades() {
    loadTrades(1);
}

function refreshData() {
    loadStats();
    loadTrades(currentPage);
}

function viewDetail(orderId) {
    fetch(`{{ route('admin_api_trades_detail', ['order_id' => '__ORDER_ID__']) }}`.replace('__ORDER_ID__', orderId), {
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(res => res.json())
    .then(data => {
        if (data.success && data.trade) {
            const t = data.trade;
            const duration = calculateDuration(t.open_time, t.close_time);
            const netProfit = (t.profit || 0) - (t.commission || 0) - (t.swap || 0);

            document.getElementById('modalContent').innerHTML = `
                <div class="space-y-6">
                    <div class="flex items-center justify-center mb-4">
                        <div class="w-16 h-16 bg-gradient-to-br ${t.profit >= 0 ? 'from-green-500 to-emerald-600' : 'from-red-500 to-pink-600'} rounded-full flex items-center justify-center">
                            <i class="fas ${t.order_type === 'buy' ? 'fa-arrow-up' : 'fa-arrow-down'} text-3xl text-white"></i>
                        </div>
                    </div>

                    <div class="text-center mb-6">
                        <p class="text-3xl font-bold ${t.profit >= 0 ? 'text-green-600' : 'text-red-600'} mb-2">
                            ${t.profit >= 0 ? '+' : ''}$${formatNumber(t.profit || 0)}
                        </p>
                        <p class="text-sm text-slate-600">${t.symbol || '-'} · ${formatNumber(t.lots || 0)} 手</p>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div class="col-span-2 bg-slate-50 rounded-lg p-4">
                            <p class="text-sm text-slate-600 mb-1">订单号</p>
                            <p class="text-base font-mono text-slate-800">${t.order_id || '-'}</p>
                        </div>

                        <div class="col-span-2 border-b border-slate-200 pb-4">
                            <h4 class="text-sm font-semibold text-slate-700 mb-3">用户信息</h4>
                            <div class="grid grid-cols-2 gap-4">
                                <div><p class="text-sm text-slate-600 mb-1">用户名</p><p class="text-base font-semibold text-slate-800">${t.username || 'N/A'}</p></div>
                                <div><p class="text-sm text-slate-600 mb-1">用户ID</p><p class="text-base text-slate-800">${t.user_id || '-'}</p></div>
                                <div><p class="text-sm text-slate-600 mb-1">MT4账号</p><p class="text-base font-mono text-slate-800">${t.mt4_account || '-'}</p></div>
                                <div><p class="text-sm text-slate-600 mb-1">账户组</p><p class="text-base text-slate-800">${t.account_group || '-'}</p></div>
                            </div>
                        </div>

                        <div class="col-span-2 border-b border-slate-200 pb-4">
                            <h4 class="text-sm font-semibold text-slate-700 mb-3">交易信息</h4>
                            <div class="grid grid-cols-2 gap-4">
                                <div><p class="text-sm text-slate-600 mb-1">交易品种</p><p class="text-base font-semibold text-slate-800">${t.symbol || '-'}</p></div>
                                <div><p class="text-sm text-slate-600 mb-1">订单类型</p><div class="mt-1">${getOrderTypeBadge(t.order_type)}</div></div>
                                <div><p class="text-sm text-slate-600 mb-1">交易手数</p><p class="text-base font-semibold text-slate-800">${formatNumber(t.lots || 0)}</p></div>
                                <div><p class="text-sm text-slate-600 mb-1">杠杆</p><p class="text-base text-slate-800">1:${t.leverage || 100}</p></div>
                                <div><p class="text-sm text-slate-600 mb-1">开仓价</p><p class="text-base text-slate-800">${formatPrice(t.open_price || 0)}</p></div>
                                <div><p class="text-sm text-slate-600 mb-1">平仓价</p><p class="text-base font-semibold text-slate-800">${formatPrice(t.close_price || 0)}</p></div>
                                <div><p class="text-sm text-slate-600 mb-1">止损价</p><p class="text-base text-slate-800">${t.stop_loss ? formatPrice(t.stop_loss) : '-'}</p></div>
                                <div><p class="text-sm text-slate-600 mb-1">止盈价</p><p class="text-base text-slate-800">${t.take_profit ? formatPrice(t.take_profit) : '-'}</p></div>
                            </div>
                        </div>

                        <div class="col-span-2 border-b border-slate-200 pb-4">
                            <h4 class="text-sm font-semibold text-slate-700 mb-3">时间信息</h4>
                            <div class="grid grid-cols-2 gap-4">
                                <div><p class="text-sm text-slate-600 mb-1">开仓时间</p><p class="text-base text-slate-800">${t.open_time || '-'}</p></div>
                                <div><p class="text-sm text-slate-600 mb-1">平仓时间</p><p class="text-base text-slate-800">${t.close_time || '-'}</p></div>
                                <div class="col-span-2"><p class="text-sm text-slate-600 mb-1">持仓时长</p><p class="text-base font-semibold text-orange-600">${duration}</p></div>
                            </div>
                        </div>

                        <div class="col-span-2">
                            <h4 class="text-sm font-semibold text-slate-700 mb-3">盈亏明细</h4>
                            <div class="space-y-2">
                                <div class="flex items-center justify-between p-3 bg-slate-50 rounded-lg">
                                    <p class="text-sm text-slate-600">盈亏金额</p>
                                    <p class="text-base font-bold ${t.profit >= 0 ? 'text-green-600' : 'text-red-600'}">${t.profit >= 0 ? '+' : ''}$${formatNumber(t.profit || 0)}</p>
                                </div>
                                <div class="flex items-center justify-between p-3 bg-slate-50 rounded-lg">
                                    <p class="text-sm text-slate-600">手续费</p>
                                    <p class="text-base text-slate-800">-$${formatNumber(Math.abs(t.commission || 0))}</p>
                                </div>
                                <div class="flex items-center justify-between p-3 bg-slate-50 rounded-lg">
                                    <p class="text-sm text-slate-600">库存费</p>
                                    <p class="text-base text-slate-800">${(t.swap || 0) >= 0 ? '+' : '-'}$${formatNumber(Math.abs(t.swap || 0))}</p>
                                </div>
                                <div class="flex items-center justify-between p-3 bg-${netProfit >= 0 ? 'green' : 'red'}-50 rounded-lg border-2 border-${netProfit >= 0 ? 'green' : 'red'}-200">
                                    <p class="text-sm font-semibold text-${netProfit >= 0 ? 'green' : 'red'}-700">净盈亏</p>
                                    <p class="text-lg font-bold text-${netProfit >= 0 ? 'green' : 'red'}-600">${netProfit >= 0 ? '+' : ''}$${formatNumber(netProfit)}</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    ${t.comment ? `
                        <div class="border-t border-slate-200 pt-4">
                            <p class="text-sm text-slate-600 mb-2">交易备注</p>
                            <p class="text-base text-slate-800 bg-slate-50 rounded-lg p-3">${t.comment}</p>
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

function exportTrades() {
    const symbol = document.getElementById('filterSymbol').value;
    const orderType = document.getElementById('filterOrderType').value;
    const profitStatus = document.getElementById('filterProfitStatus').value;
    const startDate = document.getElementById('filterStartDate').value;
    const endDate = document.getElementById('filterEndDate').value;

    let url = '{{ route("admin_api_trades_export") }}';
    const params = new URLSearchParams();
    if (symbol) params.append('symbol', symbol);
    if (orderType) params.append('order_type', orderType);
    if (profitStatus) params.append('profit_status', profitStatus);
    if (startDate) params.append('start_date', startDate);
    if (endDate) params.append('end_date', endDate);

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
    document.getElementById('tradesTableBody').innerHTML = `
        <tr><td colspan="10" class="px-6 py-12 text-center text-red-500">
            <i class="fas fa-exclamation-circle text-3xl mb-3"></i><p>${message}</p>
        </td></tr>
    `;
}
</script>
@endsection
