@extends('admin-tailwind.layouts.app')

@section('title', '权益汇总 - 管理后台')

@section('content')
<!-- Page Header -->
<div class="flex items-center justify-between mb-8">
    <div>
        <h1 class="text-3xl font-bold text-slate-800">权益汇总</h1>
        <p class="text-slate-600 mt-2">查看所有用户的账户权益汇总数据</p>
    </div>
    <div class="flex gap-3">
        <button onclick="refreshData()" class="px-6 py-3 bg-gradient-to-r from-blue-500 to-indigo-600 text-white font-semibold rounded-lg hover:shadow-lg transition">
            <i class="fas fa-sync-alt mr-2"></i>刷新数据
        </button>
        <button onclick="exportRights()" class="px-6 py-3 bg-gradient-to-r from-green-500 to-green-600 text-white font-semibold rounded-lg hover:shadow-lg transition">
            <i class="fas fa-download mr-2"></i>导出报表
        </button>
    </div>
</div>

<!-- Stats Cards -->
<div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-6">
    <div class="bg-white rounded-xl shadow-lg p-6 border-l-4 border-blue-500">
        <p class="text-sm text-slate-600 mb-2">总用户数</p>
        <p class="text-3xl font-bold text-slate-800" id="totalUsers">0</p>
    </div>
    <div class="bg-white rounded-xl shadow-lg p-6 border-l-4 border-green-500">
        <p class="text-sm text-slate-600 mb-2">总权益</p>
        <p class="text-3xl font-bold text-slate-800">$<span id="totalEquity">0</span></p>
    </div>
    <div class="bg-white rounded-xl shadow-lg p-6 border-l-4 border-purple-500">
        <p class="text-sm text-slate-600 mb-2">总余额</p>
        <p class="text-3xl font-bold text-slate-800">$<span id="totalBalance">0</span></p>
    </div>
    <div class="bg-white rounded-xl shadow-lg p-6 border-l-4 border-orange-500">
        <p class="text-sm text-slate-600 mb-2">总净值</p>
        <p class="text-3xl font-bold text-slate-800">$<span id="totalNetValue">0</span></p>
    </div>
</div>

<!-- Filter Bar -->
<div class="bg-white rounded-xl shadow-lg p-6 mb-6">
    <div class="grid grid-cols-1 md:grid-cols-5 gap-4">
        <div>
            <label class="block text-sm font-semibold text-slate-700 mb-2">搜索</label>
            <input type="text" id="searchKeyword" placeholder="用户名/MT4账号" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
        </div>
        <div>
            <label class="block text-sm font-semibold text-slate-700 mb-2">代理级别</label>
            <select id="filterAgentLevel" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                <option value="">全部级别</option>
                <option value="ib">IB</option>
                <option value="sub_ib">Sub IB</option>
                <option value="agent">代理</option>
                <option value="customer">客户</option>
            </select>
        </div>
        <div>
            <label class="block text-sm font-semibold text-slate-700 mb-2">权益范围</label>
            <select id="filterEquityRange" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                <option value="">全部范围</option>
                <option value="0-1000">$0 - $1,000</option>
                <option value="1000-10000">$1,000 - $10,000</option>
                <option value="10000-50000">$10,000 - $50,000</option>
                <option value="50000+">$50,000+</option>
            </select>
        </div>
        <div>
            <label class="block text-sm font-semibold text-slate-700 mb-2">排序方式</label>
            <select id="filterSort" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                <option value="equity_desc">权益从高到低</option>
                <option value="equity_asc">权益从低到高</option>
                <option value="balance_desc">余额从高到低</option>
                <option value="balance_asc">余额从低到高</option>
            </select>
        </div>
        <div class="flex items-end">
            <button onclick="searchRights()" class="w-full px-6 py-2 bg-gradient-to-r from-blue-500 to-indigo-600 text-white font-semibold rounded-lg hover:shadow-lg transition">
                <i class="fas fa-search mr-2"></i>搜索
            </button>
        </div>
    </div>
</div>

<!-- Rights Summary Table -->
<div class="bg-white rounded-xl shadow-lg overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full">
            <thead class="bg-slate-50 border-b border-slate-200">
                <tr>
                    <th class="px-6 py-4 text-left text-xs font-semibold text-slate-600 uppercase tracking-wider">用户信息</th>
                    <th class="px-6 py-4 text-left text-xs font-semibold text-slate-600 uppercase tracking-wider">MT4账号</th>
                    <th class="px-6 py-4 text-left text-xs font-semibold text-slate-600 uppercase tracking-wider">代理级别</th>
                    <th class="px-6 py-4 text-left text-xs font-semibold text-slate-600 uppercase tracking-wider">账户余额</th>
                    <th class="px-6 py-4 text-left text-xs font-semibold text-slate-600 uppercase tracking-wider">浮动盈亏</th>
                    <th class="px-6 py-4 text-left text-xs font-semibold text-slate-600 uppercase tracking-wider">账户权益</th>
                    <th class="px-6 py-4 text-left text-xs font-semibold text-slate-600 uppercase tracking-wider">净值</th>
                    <th class="px-6 py-4 text-left text-xs font-semibold text-slate-600 uppercase tracking-wider">更新时间</th>
                    <th class="px-6 py-4 text-left text-xs font-semibold text-slate-600 uppercase tracking-wider">操作</th>
                </tr>
            </thead>
            <tbody id="rightsTableBody" class="divide-y divide-slate-200">
                <tr>
                    <td colspan="9" class="px-6 py-12 text-center text-slate-500">
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
            <h3 class="text-xl font-bold text-white">权益详情</h3>
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
    loadRightsSummary();
});

function loadStats() {
    fetch('{{ route("admin_api_rights_summary_stats") }}', {
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(res => res.json())
    .then(data => {
        if (data.success || data.code === 200) {
            document.getElementById('totalUsers').textContent = formatNumber(data.totalUsers || 0);
            document.getElementById('totalEquity').textContent = formatNumber(data.totalEquity || 0);
            document.getElementById('totalBalance').textContent = formatNumber(data.totalBalance || 0);
            document.getElementById('totalNetValue').textContent = formatNumber(data.totalNetValue || 0);
        }
    })
    .catch(err => console.error('Load stats error:', err));
}

function loadRightsSummary(page = 1) {
    currentPage = page;
    const keyword = document.getElementById('searchKeyword').value;
    const agentLevel = document.getElementById('filterAgentLevel').value;
    const equityRange = document.getElementById('filterEquityRange').value;
    const sort = document.getElementById('filterSort').value;

    const params = new URLSearchParams({
        page: page,
        keyword: keyword,
        agent_level: agentLevel,
        equity_range: equityRange,
        sort: sort
    });

    fetch(`{{ route('admin_api_rights_summary_list') }}?${params}`, {
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(res => res.json())
    .then(data => {
        if (data.success || data.code === 200) {
            renderTable(data.rights || []);
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

function renderTable(rights) {
    const tbody = document.getElementById('rightsTableBody');

    if (rights.length === 0) {
        tbody.innerHTML = `
            <tr>
                <td colspan="9" class="px-6 py-12 text-center text-slate-500">
                    <i class="fas fa-inbox text-3xl mb-3"></i>
                    <p>暂无数据</p>
                </td>
            </tr>
        `;
        return;
    }

    tbody.innerHTML = rights.map(r => `
        <tr class="hover:bg-slate-50 transition">
            <td class="px-6 py-4">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 bg-gradient-to-br from-blue-500 to-indigo-600 rounded-full flex items-center justify-center text-white font-bold">
                        ${(r.username || 'U').charAt(0).toUpperCase()}
                    </div>
                    <div>
                        <p class="text-sm font-semibold text-slate-800">${r.username || 'N/A'}</p>
                        <p class="text-xs text-slate-500">ID: ${r.user_id || '-'}</p>
                    </div>
                </div>
            </td>
            <td class="px-6 py-4">
                <p class="text-sm font-mono text-slate-800">${r.mt4_account || '-'}</p>
            </td>
            <td class="px-6 py-4">${getAgentLevelBadge(r.agent_level)}</td>
            <td class="px-6 py-4">
                <p class="text-base font-semibold text-slate-800">$${formatNumber(r.balance || 0)}</p>
            </td>
            <td class="px-6 py-4">
                <p class="text-base font-semibold ${r.floating_pl >= 0 ? 'text-green-600' : 'text-red-600'}">
                    ${r.floating_pl >= 0 ? '+' : ''}$${formatNumber(r.floating_pl || 0)}
                </p>
            </td>
            <td class="px-6 py-4">
                <p class="text-base font-bold text-blue-600">$${formatNumber(r.equity || 0)}</p>
            </td>
            <td class="px-6 py-4">
                <p class="text-base font-semibold text-purple-600">$${formatNumber(r.net_value || 0)}</p>
            </td>
            <td class="px-6 py-4 text-sm text-slate-600">${r.updated_at || '-'}</td>
            <td class="px-6 py-4">
                <button onclick="viewDetail(${r.user_id})" class="px-3 py-1 bg-blue-100 text-blue-700 text-xs font-semibold rounded hover:bg-blue-200 transition">
                    <i class="fas fa-eye mr-1"></i>详情
                </button>
            </td>
        </tr>
    `).join('');
}

function renderPagination(pagination) {
    document.getElementById('pageStart').textContent = pagination.from || 0;
    document.getElementById('pageEnd').textContent = pagination.to || 0;
    document.getElementById('totalRecords').textContent = pagination.total || 0;

    totalPages = pagination.last_page || 1;
    const paginationDiv = document.getElementById('pagination');

    let html = '';
    if (currentPage > 1) {
        html += `<button onclick="loadRightsSummary(${currentPage - 1})" class="px-3 py-1 border border-slate-300 rounded hover:bg-slate-50">上一页</button>`;
    }
    for (let i = Math.max(1, currentPage - 2); i <= Math.min(totalPages, currentPage + 2); i++) {
        html += `<button onclick="loadRightsSummary(${i})" class="px-3 py-1 border ${i === currentPage ? 'bg-blue-500 text-white' : 'border-slate-300 hover:bg-slate-50'} rounded">${i}</button>`;
    }
    if (currentPage < totalPages) {
        html += `<button onclick="loadRightsSummary(${currentPage + 1})" class="px-3 py-1 border border-slate-300 rounded hover:bg-slate-50">下一页</button>`;
    }
    paginationDiv.innerHTML = html;
}

function getAgentLevelBadge(level) {
    const badges = {
        'ib': '<span class="px-2 py-1 bg-purple-100 text-purple-700 text-xs font-semibold rounded-full">IB</span>',
        'sub_ib': '<span class="px-2 py-1 bg-indigo-100 text-indigo-700 text-xs font-semibold rounded-full">Sub IB</span>',
        'agent': '<span class="px-2 py-1 bg-blue-100 text-blue-700 text-xs font-semibold rounded-full">代理</span>',
        'customer': '<span class="px-2 py-1 bg-slate-100 text-slate-700 text-xs font-semibold rounded-full">客户</span>'
    };
    return badges[level] || '<span class="px-2 py-1 bg-slate-100 text-slate-700 text-xs font-semibold rounded-full">-</span>';
}

function searchRights() {
    loadRightsSummary(1);
}

function refreshData() {
    loadStats();
    loadRightsSummary(currentPage);
}

function viewDetail(userId) {
    fetch(`{{ route('admin_api_rights_summary_detail', ['user_id' => '__USER_ID__']) }}`.replace('__USER_ID__', userId), {
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(res => res.json())
    .then(data => {
        if (data.success && data.right) {
            const r = data.right;
            document.getElementById('modalContent').innerHTML = `
                <div class="space-y-6">
                    <div class="flex items-center justify-center mb-4">
                        <div class="w-16 h-16 bg-gradient-to-br from-blue-500 to-indigo-600 rounded-full flex items-center justify-center text-white text-2xl font-bold">
                            ${(r.username || 'U').charAt(0).toUpperCase()}
                        </div>
                    </div>

                    <div class="text-center mb-6">
                        <p class="text-2xl font-bold text-slate-800 mb-2">${r.username || 'N/A'}</p>
                        <p class="text-sm text-slate-500">用户ID: ${r.user_id || '-'}</p>
                    </div>

                    <div class="grid grid-cols-2 gap-6">
                        <div class="col-span-2 bg-blue-50 rounded-lg p-6 text-center">
                            <p class="text-sm text-blue-600 mb-2">账户权益</p>
                            <p class="text-4xl font-bold text-blue-600">$${formatNumber(r.equity || 0)}</p>
                        </div>

                        <div class="bg-green-50 rounded-lg p-4 text-center">
                            <p class="text-xs text-green-600 mb-2">账户余额</p>
                            <p class="text-2xl font-bold text-green-600">$${formatNumber(r.balance || 0)}</p>
                        </div>

                        <div class="bg-${r.floating_pl >= 0 ? 'green' : 'red'}-50 rounded-lg p-4 text-center">
                            <p class="text-xs text-${r.floating_pl >= 0 ? 'green' : 'red'}-600 mb-2">浮动盈亏</p>
                            <p class="text-2xl font-bold text-${r.floating_pl >= 0 ? 'green' : 'red'}-600">${r.floating_pl >= 0 ? '+' : ''}$${formatNumber(r.floating_pl || 0)}</p>
                        </div>

                        <div class="bg-purple-50 rounded-lg p-4 text-center">
                            <p class="text-xs text-purple-600 mb-2">净值</p>
                            <p class="text-2xl font-bold text-purple-600">$${formatNumber(r.net_value || 0)}</p>
                        </div>

                        <div class="bg-orange-50 rounded-lg p-4 text-center">
                            <p class="text-xs text-orange-600 mb-2">已用保证金</p>
                            <p class="text-2xl font-bold text-orange-600">$${formatNumber(r.margin || 0)}</p>
                        </div>

                        <div class="bg-cyan-50 rounded-lg p-4 text-center">
                            <p class="text-xs text-cyan-600 mb-2">可用保证金</p>
                            <p class="text-2xl font-bold text-cyan-600">$${formatNumber(r.free_margin || 0)}</p>
                        </div>

                        <div class="bg-indigo-50 rounded-lg p-4 text-center">
                            <p class="text-xs text-indigo-600 mb-2">保证金比例</p>
                            <p class="text-2xl font-bold text-indigo-600">${formatNumber(r.margin_level || 0)}%</p>
                        </div>
                    </div>

                    <div class="border-t border-slate-200 pt-4">
                        <h4 class="text-sm font-semibold text-slate-700 mb-3">账户信息</h4>
                        <div class="grid grid-cols-2 gap-4">
                            <div><p class="text-sm text-slate-600 mb-1">MT4账号</p><p class="text-base font-mono text-slate-800">${r.mt4_account || '-'}</p></div>
                            <div><p class="text-sm text-slate-600 mb-1">代理级别</p><div class="mt-1">${getAgentLevelBadge(r.agent_level)}</div></div>
                            <div><p class="text-sm text-slate-600 mb-1">账户组</p><p class="text-base text-slate-800">${r.account_group || '-'}</p></div>
                            <div><p class="text-sm text-slate-600 mb-1">币种</p><p class="text-base text-slate-800">${r.currency || 'USD'}</p></div>
                            <div><p class="text-sm text-slate-600 mb-1">杠杆</p><p class="text-base text-slate-800">1:${r.leverage || 100}</p></div>
                            <div><p class="text-sm text-slate-600 mb-1">持仓手数</p><p class="text-base text-slate-800">${formatNumber(r.total_lots || 0)}</p></div>
                            <div class="col-span-2"><p class="text-sm text-slate-600 mb-1">更新时间</p><p class="text-base text-slate-800">${r.updated_at || '-'}</p></div>
                        </div>
                    </div>

                    ${r.remark ? `
                        <div class="border-t border-slate-200 pt-4">
                            <p class="text-sm text-slate-600 mb-2">备注说明</p>
                            <p class="text-base text-slate-800 bg-slate-50 rounded-lg p-3">${r.remark}</p>
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

function exportRights() {
    const agentLevel = document.getElementById('filterAgentLevel').value;
    const equityRange = document.getElementById('filterEquityRange').value;

    let url = '{{ route("admin_api_rights_summary_export") }}';
    const params = new URLSearchParams();
    if (agentLevel) params.append('agent_level', agentLevel);
    if (equityRange) params.append('equity_range', equityRange);

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
    document.getElementById('rightsTableBody').innerHTML = `
        <tr><td colspan="9" class="px-6 py-12 text-center text-red-500">
            <i class="fas fa-exclamation-circle text-3xl mb-3"></i><p>${message}</p>
        </td></tr>
    `;
}
</script>
@endsection
