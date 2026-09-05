@extends('admin-tailwind.layouts.app')

@section('title', '实时返佣 - 管理后台')

@section('content')
<!-- Page Header -->
<div class="flex items-center justify-between mb-8">
    <div>
        <h1 class="text-3xl font-bold text-slate-800">实时返佣</h1>
        <p class="text-slate-600 mt-2">实时查看代理佣金产生和返还情况</p>
    </div>
    <div class="flex gap-3">
        <button onclick="refreshData()" class="px-6 py-3 bg-gradient-to-r from-blue-500 to-indigo-600 text-white font-semibold rounded-lg hover:shadow-lg transition">
            <i class="fas fa-sync-alt mr-2"></i>刷新数据
        </button>
        <button onclick="exportRealtime()" class="px-6 py-3 bg-gradient-to-r from-green-500 to-green-600 text-white font-semibold rounded-lg hover:shadow-lg transition">
            <i class="fas fa-download mr-2"></i>导出报表
        </button>
    </div>
</div>

<!-- Stats Cards -->
<div class="grid grid-cols-1 md:grid-cols-5 gap-6 mb-6">
    <div class="bg-white rounded-xl shadow-lg p-6 border-l-4 border-blue-500">
        <p class="text-sm text-slate-600 mb-2">今日返佣笔数</p>
        <p class="text-3xl font-bold text-slate-800" id="todayCount">0</p>
    </div>
    <div class="bg-white rounded-xl shadow-lg p-6 border-l-4 border-green-500">
        <p class="text-sm text-slate-600 mb-2">今日返佣金额</p>
        <p class="text-3xl font-bold text-green-600">$<span id="todayAmount">0</span></p>
    </div>
    <div class="bg-white rounded-xl shadow-lg p-6 border-l-4 border-purple-500">
        <p class="text-sm text-slate-600 mb-2">本周返佣金额</p>
        <p class="text-3xl font-bold text-purple-600">$<span id="weekAmount">0</span></p>
    </div>
    <div class="bg-white rounded-xl shadow-lg p-6 border-l-4 border-orange-500">
        <p class="text-sm text-slate-600 mb-2">本月返佣金额</p>
        <p class="text-3xl font-bold text-orange-600">$<span id="monthAmount">0</span></p>
    </div>
    <div class="bg-white rounded-xl shadow-lg p-6 border-l-4 border-cyan-500">
        <p class="text-sm text-slate-600 mb-2">活跃代理数</p>
        <p class="text-3xl font-bold text-cyan-600" id="activeAgents">0</p>
    </div>
</div>

<!-- Filter Bar -->
<div class="bg-white rounded-xl shadow-lg p-6 mb-6">
    <div class="grid grid-cols-1 md:grid-cols-6 gap-4">
        <div>
            <label class="block text-sm font-semibold text-slate-700 mb-2">搜索</label>
            <input type="text" id="searchKeyword" placeholder="代理名称/订单号" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
        </div>
        <div>
            <label class="block text-sm font-semibold text-slate-700 mb-2">代理级别</label>
            <select id="filterAgentLevel" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                <option value="">全部级别</option>
                <option value="ib">IB</option>
                <option value="sub_ib">Sub IB</option>
                <option value="agent">代理</option>
            </select>
        </div>
        <div>
            <label class="block text-sm font-semibold text-slate-700 mb-2">交易品种</label>
            <select id="filterSymbol" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                <option value="">全部品种</option>
                <option value="EURUSD">EURUSD</option>
                <option value="GBPUSD">GBPUSD</option>
                <option value="USDJPY">USDJPY</option>
                <option value="XAUUSD">XAUUSD</option>
            </select>
        </div>
        <div>
            <label class="block text-sm font-semibold text-slate-700 mb-2">时间范围</label>
            <select id="filterTimeRange" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                <option value="today">今日</option>
                <option value="yesterday">昨日</option>
                <option value="week">本周</option>
                <option value="month">本月</option>
                <option value="custom">自定义</option>
            </select>
        </div>
        <div>
            <label class="block text-sm font-semibold text-slate-700 mb-2">排序方式</label>
            <select id="filterSort" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                <option value="time_desc">时间从新到旧</option>
                <option value="time_asc">时间从旧到新</option>
                <option value="amount_desc">金额从高到低</option>
                <option value="amount_asc">金额从低到高</option>
            </select>
        </div>
        <div class="flex items-end">
            <button onclick="searchRealtime()" class="w-full px-6 py-2 bg-gradient-to-r from-blue-500 to-indigo-600 text-white font-semibold rounded-lg hover:shadow-lg transition">
                <i class="fas fa-search mr-2"></i>搜索
            </button>
        </div>
    </div>
</div>

<!-- Realtime Commission Table -->
<div class="bg-white rounded-xl shadow-lg overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full">
            <thead class="bg-slate-50 border-b border-slate-200">
                <tr>
                    <th class="px-6 py-4 text-left text-xs font-semibold text-slate-600 uppercase tracking-wider">返佣记录号</th>
                    <th class="px-6 py-4 text-left text-xs font-semibold text-slate-600 uppercase tracking-wider">代理信息</th>
                    <th class="px-6 py-4 text-left text-xs font-semibold text-slate-600 uppercase tracking-wider">客户信息</th>
                    <th class="px-6 py-4 text-left text-xs font-semibold text-slate-600 uppercase tracking-wider">交易订单</th>
                    <th class="px-6 py-4 text-left text-xs font-semibold text-slate-600 uppercase tracking-wider">交易品种</th>
                    <th class="px-6 py-4 text-left text-xs font-semibold text-slate-600 uppercase tracking-wider">交易手数</th>
                    <th class="px-6 py-4 text-left text-xs font-semibold text-slate-600 uppercase tracking-wider">佣金比例</th>
                    <th class="px-6 py-4 text-left text-xs font-semibold text-slate-600 uppercase tracking-wider">返佣金额</th>
                    <th class="px-6 py-4 text-left text-xs font-semibold text-slate-600 uppercase tracking-wider">返佣时间</th>
                    <th class="px-6 py-4 text-left text-xs font-semibold text-slate-600 uppercase tracking-wider">操作</th>
                </tr>
            </thead>
            <tbody id="realtimeTableBody" class="divide-y divide-slate-200">
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
        <div class="sticky top-0 bg-gradient-to-r from-green-500 to-emerald-600 px-6 py-4 flex items-center justify-between">
            <h3 class="text-xl font-bold text-white">返佣详情</h3>
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
let autoRefreshInterval = null;

document.addEventListener('DOMContentLoaded', function() {
    loadStats();
    loadRealtime();

    // Auto refresh every 10 seconds
    autoRefreshInterval = setInterval(() => {
        loadStats();
        loadRealtime(currentPage);
    }, 10000);
});

function loadStats() {
    fetch('{{ route("admin_api_realtime_commissions_stats") }}', {
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(res => res.json())
    .then(data => {
        if (data.success || data.code === 200) {
            document.getElementById('todayCount').textContent = formatNumber(data.todayCount || 0);
            document.getElementById('todayAmount').textContent = formatNumber(data.todayAmount || 0);
            document.getElementById('weekAmount').textContent = formatNumber(data.weekAmount || 0);
            document.getElementById('monthAmount').textContent = formatNumber(data.monthAmount || 0);
            document.getElementById('activeAgents').textContent = formatNumber(data.activeAgents || 0);
        }
    })
    .catch(err => console.error('Load stats error:', err));
}

function loadRealtime(page = 1) {
    currentPage = page;
    const keyword = document.getElementById('searchKeyword').value;
    const agentLevel = document.getElementById('filterAgentLevel').value;
    const symbol = document.getElementById('filterSymbol').value;
    const timeRange = document.getElementById('filterTimeRange').value;
    const sort = document.getElementById('filterSort').value;

    const params = new URLSearchParams({
        page: page,
        keyword: keyword,
        agent_level: agentLevel,
        symbol: symbol,
        time_range: timeRange,
        sort: sort
    });

    fetch(`{{ route('admin_api_realtime_commissions_list') }}?${params}`, {
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(res => res.json())
    .then(data => {
        if (data.success || data.code === 200) {
            renderTable(data.commissions || []);
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

function renderTable(commissions) {
    const tbody = document.getElementById('realtimeTableBody');

    if (commissions.length === 0) {
        tbody.innerHTML = `
            <tr>
                <td colspan="10" class="px-6 py-12 text-center text-slate-500">
                    <i class="fas fa-inbox text-3xl mb-3"></i>
                    <p>暂无返佣数据</p>
                </td>
            </tr>
        `;
        return;
    }

    tbody.innerHTML = commissions.map(c => {
        const isNew = isRecent(c.created_at);

        return `
        <tr class="hover:bg-slate-50 transition ${isNew ? 'bg-green-50' : ''}">
            <td class="px-6 py-4">
                <div class="flex items-center gap-2">
                    <p class="text-sm font-mono text-slate-800">${c.record_no || '-'}</p>
                    ${isNew ? '<span class="px-1 py-0.5 bg-red-500 text-white text-xs rounded">NEW</span>' : ''}
                </div>
            </td>
            <td class="px-6 py-4">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 bg-gradient-to-br from-green-500 to-emerald-600 rounded-full flex items-center justify-center text-white font-bold">
                        ${(c.agent_name || 'A').charAt(0).toUpperCase()}
                    </div>
                    <div>
                        <p class="text-sm font-semibold text-slate-800">${c.agent_name || 'N/A'}</p>
                        <div class="mt-1">${getAgentLevelBadge(c.agent_level)}</div>
                    </div>
                </div>
            </td>
            <td class="px-6 py-4">
                <div>
                    <p class="text-sm font-semibold text-slate-800">${c.customer_name || 'N/A'}</p>
                    <p class="text-xs text-slate-500">MT4: ${c.customer_mt4 || '-'}</p>
                </div>
            </td>
            <td class="px-6 py-4">
                <p class="text-xs font-mono text-slate-600">${c.order_id || '-'}</p>
            </td>
            <td class="px-6 py-4">
                <p class="text-sm font-semibold text-slate-800">${c.symbol || '-'}</p>
            </td>
            <td class="px-6 py-4">
                <p class="text-sm font-semibold text-slate-800">${formatNumber(c.lots || 0)}</p>
            </td>
            <td class="px-6 py-4">
                <p class="text-sm text-slate-600">${c.commission_rate || 0}%</p>
            </td>
            <td class="px-6 py-4">
                <p class="text-base font-bold text-green-600">$${formatNumber(c.commission_amount || 0)}</p>
            </td>
            <td class="px-6 py-4">
                <p class="text-sm text-slate-600">${c.created_at || '-'}</p>
            </td>
            <td class="px-6 py-4">
                <button onclick="viewDetail(${c.id})" class="px-3 py-1 bg-blue-100 text-blue-700 text-xs font-semibold rounded hover:bg-blue-200 transition">
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
        html += `<button onclick="loadRealtime(${currentPage - 1})" class="px-3 py-1 border border-slate-300 rounded hover:bg-slate-50">上一页</button>`;
    }
    for (let i = Math.max(1, currentPage - 2); i <= Math.min(totalPages, currentPage + 2); i++) {
        html += `<button onclick="loadRealtime(${i})" class="px-3 py-1 border ${i === currentPage ? 'bg-blue-500 text-white' : 'border-slate-300 hover:bg-slate-50'} rounded">${i}</button>`;
    }
    if (currentPage < totalPages) {
        html += `<button onclick="loadRealtime(${currentPage + 1})" class="px-3 py-1 border border-slate-300 rounded hover:bg-slate-50">下一页</button>`;
    }
    paginationDiv.innerHTML = html;
}

function getAgentLevelBadge(level) {
    const badges = {
        'ib': '<span class="px-2 py-1 bg-purple-100 text-purple-700 text-xs font-semibold rounded-full">IB</span>',
        'sub_ib': '<span class="px-2 py-1 bg-indigo-100 text-indigo-700 text-xs font-semibold rounded-full">Sub IB</span>',
        'agent': '<span class="px-2 py-1 bg-blue-100 text-blue-700 text-xs font-semibold rounded-full">代理</span>'
    };
    return badges[level] || '<span class="px-2 py-1 bg-slate-100 text-slate-700 text-xs font-semibold rounded-full">-</span>';
}

function isRecent(timestamp) {
    const created = new Date(timestamp);
    const now = new Date();
    const diff = now - created;
    return diff < 60000; // Less than 1 minute
}

function searchRealtime() {
    loadRealtime(1);
}

function refreshData() {
    loadStats();
    loadRealtime(currentPage);
}

function viewDetail(id) {
    fetch(`{{ route('admin_api_realtime_commissions_detail', ['id' => '__ID__']) }}`.replace('__ID__', id), {
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(res => res.json())
    .then(data => {
        if (data.success && data.commission) {
            const c = data.commission;
            document.getElementById('modalContent').innerHTML = `
                <div class="space-y-6">
                    <div class="flex items-center justify-center mb-4">
                        <div class="w-16 h-16 bg-gradient-to-br from-green-500 to-emerald-600 rounded-full flex items-center justify-center">
                            <i class="fas fa-coins text-3xl text-white"></i>
                        </div>
                    </div>

                    <div class="text-center mb-6">
                        <p class="text-3xl font-bold text-green-600 mb-2">$${formatNumber(c.commission_amount || 0)}</p>
                        <p class="text-sm text-slate-600">返佣金额</p>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div class="col-span-2 bg-slate-50 rounded-lg p-4">
                            <p class="text-sm text-slate-600 mb-1">返佣记录号</p>
                            <p class="text-base font-mono text-slate-800">${c.record_no || '-'}</p>
                        </div>

                        <div class="col-span-2 border-b border-slate-200 pb-4">
                            <h4 class="text-sm font-semibold text-slate-700 mb-3">代理信息</h4>
                            <div class="grid grid-cols-2 gap-4">
                                <div><p class="text-sm text-slate-600 mb-1">代理名称</p><p class="text-base font-semibold text-slate-800">${c.agent_name || 'N/A'}</p></div>
                                <div><p class="text-sm text-slate-600 mb-1">代理ID</p><p class="text-base text-slate-800">${c.agent_id || '-'}</p></div>
                                <div><p class="text-sm text-slate-600 mb-1">代理级别</p><div class="mt-1">${getAgentLevelBadge(c.agent_level)}</div></div>
                                <div><p class="text-sm text-slate-600 mb-1">佣金比例</p><p class="text-base text-slate-800">${c.commission_rate || 0}%</p></div>
                            </div>
                        </div>

                        <div class="col-span-2 border-b border-slate-200 pb-4">
                            <h4 class="text-sm font-semibold text-slate-700 mb-3">客户信息</h4>
                            <div class="grid grid-cols-2 gap-4">
                                <div><p class="text-sm text-slate-600 mb-1">客户名称</p><p class="text-base font-semibold text-slate-800">${c.customer_name || 'N/A'}</p></div>
                                <div><p class="text-sm text-slate-600 mb-1">客户ID</p><p class="text-base text-slate-800">${c.customer_id || '-'}</p></div>
                                <div><p class="text-sm text-slate-600 mb-1">MT4账号</p><p class="text-base font-mono text-slate-800">${c.customer_mt4 || '-'}</p></div>
                                <div><p class="text-sm text-slate-600 mb-1">账户组</p><p class="text-base text-slate-800">${c.account_group || '-'}</p></div>
                            </div>
                        </div>

                        <div class="col-span-2">
                            <h4 class="text-sm font-semibold text-slate-700 mb-3">交易信息</h4>
                            <div class="grid grid-cols-2 gap-4">
                                <div><p class="text-sm text-slate-600 mb-1">订单号</p><p class="text-xs font-mono text-slate-800">${c.order_id || '-'}</p></div>
                                <div><p class="text-sm text-slate-600 mb-1">交易品种</p><p class="text-base font-semibold text-slate-800">${c.symbol || '-'}</p></div>
                                <div><p class="text-sm text-slate-600 mb-1">交易手数</p><p class="text-base font-semibold text-slate-800">${formatNumber(c.lots || 0)}</p></div>
                                <div><p class="text-sm text-slate-600 mb-1">交易金额</p><p class="text-base text-slate-800">$${formatNumber(c.trade_amount || 0)}</p></div>
                                <div><p class="text-sm text-slate-600 mb-1">开仓时间</p><p class="text-base text-slate-800">${c.open_time || '-'}</p></div>
                                <div><p class="text-sm text-slate-600 mb-1">平仓时间</p><p class="text-base text-slate-800">${c.close_time || '-'}</p></div>
                            </div>
                        </div>

                        <div class="col-span-2 bg-green-50 rounded-lg p-4">
                            <div class="grid grid-cols-2 gap-4">
                                <div><p class="text-sm text-green-600 mb-1">返佣金额</p><p class="text-2xl font-bold text-green-600">$${formatNumber(c.commission_amount || 0)}</p></div>
                                <div><p class="text-sm text-green-600 mb-1">返佣时间</p><p class="text-base text-green-600">${c.created_at || '-'}</p></div>
                            </div>
                        </div>
                    </div>

                    ${c.remark ? `
                        <div class="border-t border-slate-200 pt-4">
                            <p class="text-sm text-slate-600 mb-2">备注说明</p>
                            <p class="text-base text-slate-800 bg-slate-50 rounded-lg p-3">${c.remark}</p>
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

function exportRealtime() {
    const agentLevel = document.getElementById('filterAgentLevel').value;
    const symbol = document.getElementById('filterSymbol').value;
    const timeRange = document.getElementById('filterTimeRange').value;

    let url = '{{ route("admin_api_realtime_commissions_export") }}';
    const params = new URLSearchParams();
    if (agentLevel) params.append('agent_level', agentLevel);
    if (symbol) params.append('symbol', symbol);
    if (timeRange) params.append('time_range', timeRange);

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

function showError(message) {
    document.getElementById('realtimeTableBody').innerHTML = `
        <tr><td colspan="10" class="px-6 py-12 text-center text-red-500">
            <i class="fas fa-exclamation-circle text-3xl mb-3"></i><p>${message}</p>
        </td></tr>
    `;
}

// Cleanup interval on page unload
window.addEventListener('beforeunload', () => {
    if (autoRefreshInterval) {
        clearInterval(autoRefreshInterval);
    }
});
</script>
@endsection
