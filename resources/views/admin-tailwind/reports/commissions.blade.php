@extends('admin-tailwind.layouts.app')

@section('title', '佣金管理 - 管理后台')

@section('content')
<!-- Page Header -->
<div class="flex items-center justify-between mb-8">
    <div>
        <h1 class="text-3xl font-bold text-slate-800">佣金管理</h1>
        <p class="text-slate-600 mt-2">查看和管理所有代理的佣金数据</p>
    </div>
    <div class="flex gap-3">
        <button onclick="refreshData()" class="px-6 py-3 bg-gradient-to-r from-blue-500 to-indigo-600 text-white font-semibold rounded-lg hover:shadow-lg transition">
            <i class="fas fa-sync-alt mr-2"></i>刷新数据
        </button>
        <button onclick="exportCommissions()" class="px-6 py-3 bg-gradient-to-r from-green-500 to-green-600 text-white font-semibold rounded-lg hover:shadow-lg transition">
            <i class="fas fa-download mr-2"></i>导出报表
        </button>
        <button onclick="openSettlementModal()" class="px-6 py-3 bg-gradient-to-r from-purple-500 to-pink-600 text-white font-semibold rounded-lg hover:shadow-lg transition">
            <i class="fas fa-calculator mr-2"></i>批量结算
        </button>
    </div>
</div>

<!-- Stats Cards -->
<div class="grid grid-cols-1 md:grid-cols-5 gap-6 mb-6">
    <div class="bg-white rounded-xl shadow-lg p-6 border-l-4 border-blue-500">
        <p class="text-sm text-slate-600 mb-2">代理总数</p>
        <p class="text-3xl font-bold text-slate-800" id="totalAgents">0</p>
    </div>
    <div class="bg-white rounded-xl shadow-lg p-6 border-l-4 border-green-500">
        <p class="text-sm text-slate-600 mb-2">本月佣金</p>
        <p class="text-3xl font-bold text-green-600">$<span id="monthCommission">0</span></p>
    </div>
    <div class="bg-white rounded-xl shadow-lg p-6 border-l-4 border-purple-500">
        <p class="text-sm text-slate-600 mb-2">待结算</p>
        <p class="text-3xl font-bold text-purple-600">$<span id="pendingCommission">0</span></p>
    </div>
    <div class="bg-white rounded-xl shadow-lg p-6 border-l-4 border-orange-500">
        <p class="text-sm text-slate-600 mb-2">已结算</p>
        <p class="text-3xl font-bold text-orange-600">$<span id="settledCommission">0</span></p>
    </div>
    <div class="bg-white rounded-xl shadow-lg p-6 border-l-4 border-cyan-500">
        <p class="text-sm text-slate-600 mb-2">累计佣金</p>
        <p class="text-3xl font-bold text-cyan-600">$<span id="totalCommission">0</span></p>
    </div>
</div>

<!-- Filter Bar -->
<div class="bg-white rounded-xl shadow-lg p-6 mb-6">
    <div class="grid grid-cols-1 md:grid-cols-6 gap-4">
        <div>
            <label class="block text-sm font-semibold text-slate-700 mb-2">搜索</label>
            <input type="text" id="searchKeyword" placeholder="代理名称/用户名" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
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
            <label class="block text-sm font-semibold text-slate-700 mb-2">结算状态</label>
            <select id="filterStatus" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                <option value="">全部状态</option>
                <option value="pending">待结算</option>
                <option value="settled">已结算</option>
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
            <button onclick="searchCommissions()" class="w-full px-6 py-2 bg-gradient-to-r from-blue-500 to-indigo-600 text-white font-semibold rounded-lg hover:shadow-lg transition">
                <i class="fas fa-search mr-2"></i>搜索
            </button>
        </div>
    </div>
</div>

<!-- Commissions Table -->
<div class="bg-white rounded-xl shadow-lg overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full">
            <thead class="bg-slate-50 border-b border-slate-200">
                <tr>
                    <th class="px-6 py-4 text-left">
                        <input type="checkbox" id="selectAll" onchange="toggleSelectAll()" class="w-4 h-4 text-blue-600 rounded focus:ring-2 focus:ring-blue-500">
                    </th>
                    <th class="px-6 py-4 text-left text-xs font-semibold text-slate-600 uppercase tracking-wider">代理信息</th>
                    <th class="px-6 py-4 text-left text-xs font-semibold text-slate-600 uppercase tracking-wider">代理级别</th>
                    <th class="px-6 py-4 text-left text-xs font-semibold text-slate-600 uppercase tracking-wider">本月交易量</th>
                    <th class="px-6 py-4 text-left text-xs font-semibold text-slate-600 uppercase tracking-wider">本月佣金</th>
                    <th class="px-6 py-4 text-left text-xs font-semibold text-slate-600 uppercase tracking-wider">待结算</th>
                    <th class="px-6 py-4 text-left text-xs font-semibold text-slate-600 uppercase tracking-wider">已结算</th>
                    <th class="px-6 py-4 text-left text-xs font-semibold text-slate-600 uppercase tracking-wider">状态</th>
                    <th class="px-6 py-4 text-left text-xs font-semibold text-slate-600 uppercase tracking-wider">更新时间</th>
                    <th class="px-6 py-4 text-left text-xs font-semibold text-slate-600 uppercase tracking-wider">操作</th>
                </tr>
            </thead>
            <tbody id="commissionsTableBody" class="divide-y divide-slate-200">
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
        <div class="sticky top-0 bg-gradient-to-r from-purple-500 to-pink-600 px-6 py-4 flex items-center justify-between">
            <h3 class="text-xl font-bold text-white">佣金详情</h3>
            <button onclick="closeDetailModal()" class="text-white hover:text-slate-200">
                <i class="fas fa-times text-xl"></i>
            </button>
        </div>
        <div id="modalContent" class="p-6"></div>
    </div>
</div>

<!-- Settlement Modal -->
<div id="settlementModal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl max-w-2xl w-full">
        <div class="bg-gradient-to-r from-purple-500 to-pink-600 px-6 py-4 flex items-center justify-between rounded-t-2xl">
            <h3 class="text-xl font-bold text-white">批量结算</h3>
            <button onclick="closeSettlementModal()" class="text-white hover:text-slate-200">
                <i class="fas fa-times text-xl"></i>
            </button>
        </div>
        <div class="p-6">
            <div class="mb-6">
                <p class="text-slate-600 mb-4">已选择 <span id="selectedCount" class="font-bold text-purple-600">0</span> 个代理进行结算</p>
                <div class="bg-purple-50 rounded-lg p-4">
                    <div class="flex items-center justify-between mb-2">
                        <p class="text-sm text-slate-600">待结算总额</p>
                        <p class="text-2xl font-bold text-purple-600">$<span id="settlementAmount">0</span></p>
                    </div>
                </div>
            </div>

            <div class="mb-6">
                <label class="block text-sm font-semibold text-slate-700 mb-2">结算周期</label>
                <select id="settlementPeriod" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500">
                    <option value="current_month">本月</option>
                    <option value="last_month">上月</option>
                    <option value="custom">自定义</option>
                </select>
            </div>

            <div class="mb-6">
                <label class="block text-sm font-semibold text-slate-700 mb-2">结算备注</label>
                <textarea id="settlementRemark" rows="3" placeholder="请输入结算备注信息..." class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500"></textarea>
            </div>

            <div class="flex gap-3">
                <button onclick="closeSettlementModal()" class="flex-1 px-6 py-3 border border-slate-300 text-slate-700 font-semibold rounded-lg hover:bg-slate-50 transition">
                    取消
                </button>
                <button onclick="confirmSettlement()" class="flex-1 px-6 py-3 bg-gradient-to-r from-purple-500 to-pink-600 text-white font-semibold rounded-lg hover:shadow-lg transition">
                    确认结算
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Settle Single Modal -->
<div id="settleSingleModal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl max-w-md w-full">
        <div class="bg-gradient-to-r from-green-500 to-emerald-600 px-6 py-4 flex items-center justify-between rounded-t-2xl">
            <h3 class="text-xl font-bold text-white">确认结算</h3>
            <button onclick="closeSettleSingleModal()" class="text-white hover:text-slate-200">
                <i class="fas fa-times text-xl"></i>
            </button>
        </div>
        <div class="p-6">
            <div class="text-center mb-6">
                <div class="w-16 h-16 bg-gradient-to-br from-green-500 to-emerald-600 rounded-full flex items-center justify-center mx-auto mb-4">
                    <i class="fas fa-check text-3xl text-white"></i>
                </div>
                <p class="text-lg font-semibold text-slate-800 mb-2">结算金额</p>
                <p class="text-3xl font-bold text-green-600">$<span id="singleSettlementAmount">0</span></p>
            </div>

            <div class="mb-6">
                <label class="block text-sm font-semibold text-slate-700 mb-2">结算备注</label>
                <textarea id="singleSettlementRemark" rows="3" placeholder="请输入结算备注信息..." class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500"></textarea>
            </div>

            <div class="flex gap-3">
                <button onclick="closeSettleSingleModal()" class="flex-1 px-6 py-3 border border-slate-300 text-slate-700 font-semibold rounded-lg hover:bg-slate-50 transition">
                    取消
                </button>
                <button onclick="confirmSingleSettlement()" class="flex-1 px-6 py-3 bg-gradient-to-r from-green-500 to-emerald-600 text-white font-semibold rounded-lg hover:shadow-lg transition">
                    确认结算
                </button>
            </div>
        </div>
    </div>
</div>

<script>
let currentPage = 1;
let totalPages = 1;
let selectedIds = [];
let currentSettleId = null;

document.addEventListener('DOMContentLoaded', function() {
    loadStats();
    loadCommissions();
});

function loadStats() {
    fetch('{{ route("admin_api_commissions_stats") }}', {
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(res => res.json())
    .then(data => {
        if (data.success || data.code === 200) {
            document.getElementById('totalAgents').textContent = formatNumber(data.totalAgents || 0);
            document.getElementById('monthCommission').textContent = formatNumber(data.monthCommission || 0);
            document.getElementById('pendingCommission').textContent = formatNumber(data.pendingCommission || 0);
            document.getElementById('settledCommission').textContent = formatNumber(data.settledCommission || 0);
            document.getElementById('totalCommission').textContent = formatNumber(data.totalCommission || 0);
        }
    })
    .catch(err => console.error('Load stats error:', err));
}

function loadCommissions(page = 1) {
    currentPage = page;
    const keyword = document.getElementById('searchKeyword').value;
    const agentLevel = document.getElementById('filterAgentLevel').value;
    const status = document.getElementById('filterStatus').value;
    const startDate = document.getElementById('filterStartDate').value;
    const endDate = document.getElementById('filterEndDate').value;

    const params = new URLSearchParams({
        page: page,
        keyword: keyword,
        agent_level: agentLevel,
        status: status,
        start_date: startDate,
        end_date: endDate
    });

    fetch(`{{ route('admin_api_commissions_list') }}?${params}`, {
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
    const tbody = document.getElementById('commissionsTableBody');

    if (commissions.length === 0) {
        tbody.innerHTML = `
            <tr>
                <td colspan="10" class="px-6 py-12 text-center text-slate-500">
                    <i class="fas fa-inbox text-3xl mb-3"></i>
                    <p>暂无佣金数据</p>
                </td>
            </tr>
        `;
        return;
    }

    tbody.innerHTML = commissions.map(c => `
        <tr class="hover:bg-slate-50 transition">
            <td class="px-6 py-4">
                <input type="checkbox" class="commission-checkbox w-4 h-4 text-blue-600 rounded focus:ring-2 focus:ring-blue-500"
                    data-id="${c.id}"
                    data-amount="${c.pending_amount || 0}"
                    onchange="updateSelection()">
            </td>
            <td class="px-6 py-4">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 bg-gradient-to-br from-purple-500 to-pink-600 rounded-full flex items-center justify-center text-white font-bold">
                        ${(c.agent_name || 'A').charAt(0).toUpperCase()}
                    </div>
                    <div>
                        <p class="text-sm font-semibold text-slate-800">${c.agent_name || 'N/A'}</p>
                        <p class="text-xs text-slate-500">ID: ${c.agent_id || '-'}</p>
                    </div>
                </div>
            </td>
            <td class="px-6 py-4">${getAgentLevelBadge(c.agent_level)}</td>
            <td class="px-6 py-4">
                <p class="text-sm font-semibold text-slate-800">${formatNumber(c.month_volume || 0)} 手</p>
            </td>
            <td class="px-6 py-4">
                <p class="text-base font-bold text-green-600">$${formatNumber(c.month_commission || 0)}</p>
            </td>
            <td class="px-6 py-4">
                <p class="text-base font-semibold text-purple-600">$${formatNumber(c.pending_amount || 0)}</p>
            </td>
            <td class="px-6 py-4">
                <p class="text-base font-semibold text-orange-600">$${formatNumber(c.settled_amount || 0)}</p>
            </td>
            <td class="px-6 py-4">${getStatusBadge(c.status)}</td>
            <td class="px-6 py-4 text-sm text-slate-600">${c.updated_at || '-'}</td>
            <td class="px-6 py-4">
                <div class="flex gap-2">
                    <button onclick="viewDetail(${c.id})" class="px-3 py-1 bg-blue-100 text-blue-700 text-xs font-semibold rounded hover:bg-blue-200 transition">
                        <i class="fas fa-eye mr-1"></i>详情
                    </button>
                    ${c.pending_amount > 0 ? `
                        <button onclick="openSettleSingleModal(${c.id}, ${c.pending_amount})" class="px-3 py-1 bg-green-100 text-green-700 text-xs font-semibold rounded hover:bg-green-200 transition">
                            <i class="fas fa-check mr-1"></i>结算
                        </button>
                    ` : ''}
                </div>
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
        html += `<button onclick="loadCommissions(${currentPage - 1})" class="px-3 py-1 border border-slate-300 rounded hover:bg-slate-50">上一页</button>`;
    }
    for (let i = Math.max(1, currentPage - 2); i <= Math.min(totalPages, currentPage + 2); i++) {
        html += `<button onclick="loadCommissions(${i})" class="px-3 py-1 border ${i === currentPage ? 'bg-blue-500 text-white' : 'border-slate-300 hover:bg-slate-50'} rounded">${i}</button>`;
    }
    if (currentPage < totalPages) {
        html += `<button onclick="loadCommissions(${currentPage + 1})" class="px-3 py-1 border border-slate-300 rounded hover:bg-slate-50">下一页</button>`;
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

function getStatusBadge(status) {
    const badges = {
        'pending': '<span class="px-2 py-1 bg-yellow-100 text-yellow-700 text-xs font-semibold rounded-full">待结算</span>',
        'settled': '<span class="px-2 py-1 bg-green-100 text-green-700 text-xs font-semibold rounded-full">已结算</span>'
    };
    return badges[status] || '<span class="px-2 py-1 bg-slate-100 text-slate-700 text-xs font-semibold rounded-full">-</span>';
}

function toggleSelectAll() {
    const selectAll = document.getElementById('selectAll');
    const checkboxes = document.querySelectorAll('.commission-checkbox');
    checkboxes.forEach(cb => cb.checked = selectAll.checked);
    updateSelection();
}

function updateSelection() {
    const checkboxes = document.querySelectorAll('.commission-checkbox:checked');
    selectedIds = Array.from(checkboxes).map(cb => parseInt(cb.dataset.id));

    const totalAmount = Array.from(checkboxes).reduce((sum, cb) => {
        return sum + parseFloat(cb.dataset.amount || 0);
    }, 0);

    document.getElementById('selectedCount').textContent = selectedIds.length;
    document.getElementById('settlementAmount').textContent = formatNumber(totalAmount);
}

function searchCommissions() {
    loadCommissions(1);
}

function refreshData() {
    loadStats();
    loadCommissions(currentPage);
}

function viewDetail(id) {
    fetch(`{{ route('admin_api_commissions_detail', ['id' => '__ID__']) }}`.replace('__ID__', id), {
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(res => res.json())
    .then(data => {
        if (data.success && data.commission) {
            const c = data.commission;
            document.getElementById('modalContent').innerHTML = `
                <div class="space-y-6">
                    <div class="flex items-center justify-center mb-4">
                        <div class="w-16 h-16 bg-gradient-to-br from-purple-500 to-pink-600 rounded-full flex items-center justify-center text-white text-2xl font-bold">
                            ${(c.agent_name || 'A').charAt(0).toUpperCase()}
                        </div>
                    </div>

                    <div class="text-center mb-6">
                        <p class="text-2xl font-bold text-slate-800 mb-2">${c.agent_name || 'N/A'}</p>
                        <p class="text-sm text-slate-500">代理ID: ${c.agent_id || '-'}</p>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div class="col-span-2 bg-gradient-to-r from-purple-50 to-pink-50 rounded-lg p-6">
                            <div class="grid grid-cols-2 gap-4">
                                <div class="text-center">
                                    <p class="text-sm text-purple-600 mb-2">待结算</p>
                                    <p class="text-2xl font-bold text-purple-600">$${formatNumber(c.pending_amount || 0)}</p>
                                </div>
                                <div class="text-center">
                                    <p class="text-sm text-orange-600 mb-2">已结算</p>
                                    <p class="text-2xl font-bold text-orange-600">$${formatNumber(c.settled_amount || 0)}</p>
                                </div>
                            </div>
                        </div>

                        <div><p class="text-sm text-slate-600 mb-1">代理级别</p><div class="mt-1">${getAgentLevelBadge(c.agent_level)}</div></div>
                        <div><p class="text-sm text-slate-600 mb-1">状态</p><div class="mt-1">${getStatusBadge(c.status)}</div></div>

                        <div><p class="text-sm text-slate-600 mb-1">本月交易量</p><p class="text-base font-semibold text-slate-800">${formatNumber(c.month_volume || 0)} 手</p></div>
                        <div><p class="text-sm text-slate-600 mb-1">本月佣金</p><p class="text-base font-bold text-green-600">$${formatNumber(c.month_commission || 0)}</p></div>

                        <div><p class="text-sm text-slate-600 mb-1">累计交易量</p><p class="text-base text-slate-800">${formatNumber(c.total_volume || 0)} 手</p></div>
                        <div><p class="text-sm text-slate-600 mb-1">累计佣金</p><p class="text-base font-semibold text-cyan-600">$${formatNumber(c.total_commission || 0)}</p></div>

                        <div><p class="text-sm text-slate-600 mb-1">佣金比例</p><p class="text-base text-slate-800">${c.commission_rate || 0}%</p></div>
                        <div><p class="text-sm text-slate-600 mb-1">下级客户数</p><p class="text-base text-slate-800">${c.customer_count || 0}</p></div>

                        <div class="col-span-2"><p class="text-sm text-slate-600 mb-1">更新时间</p><p class="text-base text-slate-800">${c.updated_at || '-'}</p></div>
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

function openSettlementModal() {
    if (selectedIds.length === 0) {
        alert('请先选择需要结算的代理');
        return;
    }
    document.getElementById('settlementModal').classList.remove('hidden');
}

function closeSettlementModal() {
    document.getElementById('settlementModal').classList.add('hidden');
}

function confirmSettlement() {
    const period = document.getElementById('settlementPeriod').value;
    const remark = document.getElementById('settlementRemark').value;

    fetch('{{ route("admin_api_commissions_batch_settle") }}', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: JSON.stringify({
            ids: selectedIds,
            period: period,
            remark: remark
        })
    })
    .then(res => res.json())
    .then(data => {
        if (data.success || data.code === 200) {
            alert('批量结算成功');
            closeSettlementModal();
            selectedIds = [];
            document.getElementById('selectAll').checked = false;
            refreshData();
        } else {
            alert(data.message || '结算失败');
        }
    })
    .catch(err => {
        console.error('Settlement error:', err);
        alert('网络错误，请稍后重试');
    });
}

function openSettleSingleModal(id, amount) {
    currentSettleId = id;
    document.getElementById('singleSettlementAmount').textContent = formatNumber(amount);
    document.getElementById('settleSingleModal').classList.remove('hidden');
}

function closeSettleSingleModal() {
    currentSettleId = null;
    document.getElementById('settleSingleModal').classList.add('hidden');
}

function confirmSingleSettlement() {
    const remark = document.getElementById('singleSettlementRemark').value;

    fetch('{{ route("admin_api_commissions_settle", ["id" => "__ID__"]) }}'.replace('__ID__', currentSettleId), {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: JSON.stringify({ remark: remark })
    })
    .then(res => res.json())
    .then(data => {
        if (data.success || data.code === 200) {
            alert('结算成功');
            closeSettleSingleModal();
            refreshData();
        } else {
            alert(data.message || '结算失败');
        }
    })
    .catch(err => {
        console.error('Settlement error:', err);
        alert('网络错误，请稍后重试');
    });
}

function exportCommissions() {
    const agentLevel = document.getElementById('filterAgentLevel').value;
    const status = document.getElementById('filterStatus').value;
    const startDate = document.getElementById('filterStartDate').value;
    const endDate = document.getElementById('filterEndDate').value;

    let url = '{{ route("admin_api_commissions_export") }}';
    const params = new URLSearchParams();
    if (agentLevel) params.append('agent_level', agentLevel);
    if (status) params.append('status', status);
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

function showError(message) {
    document.getElementById('commissionsTableBody').innerHTML = `
        <tr><td colspan="10" class="px-6 py-12 text-center text-red-500">
            <i class="fas fa-exclamation-circle text-3xl mb-3"></i><p>${message}</p>
        </td></tr>
    `;
}
</script>
@endsection
