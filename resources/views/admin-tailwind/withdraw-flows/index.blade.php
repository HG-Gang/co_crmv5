@extends('admin-tailwind.layouts.app')

@section('title', '出金流水 - 管理后台')

@section('content')
<!-- Page Header -->
<div class="flex items-center justify-between mb-8">
    <div>
        <h1 class="text-3xl font-bold text-slate-800">出金流水</h1>
        <p class="text-slate-600 mt-2">查看所有出金交易的详细流水记录</p>
    </div>
    <div class="flex gap-3">
        <button onclick="exportFlows()" class="px-6 py-3 bg-gradient-to-r from-green-500 to-green-600 text-white font-semibold rounded-lg hover:shadow-lg transition">
            <i class="fas fa-download mr-2"></i>导出流水
        </button>
    </div>
</div>

<!-- Stats Cards -->
<div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-6">
    <div class="bg-white rounded-xl shadow-lg p-6 border-l-4 border-blue-500">
        <p class="text-sm text-slate-600 mb-2">今日流水笔数</p>
        <p class="text-3xl font-bold text-slate-800" id="todayCount">0</p>
    </div>
    <div class="bg-white rounded-xl shadow-lg p-6 border-l-4 border-purple-500">
        <p class="text-sm text-slate-600 mb-2">今日流水金额</p>
        <p class="text-3xl font-bold text-slate-800">$<span id="todayAmount">0</span></p>
    </div>
    <div class="bg-white rounded-xl shadow-lg p-6 border-l-4 border-indigo-500">
        <p class="text-sm text-slate-600 mb-2">本月流水笔数</p>
        <p class="text-3xl font-bold text-slate-800" id="monthCount">0</p>
    </div>
    <div class="bg-white rounded-xl shadow-lg p-6 border-l-4 border-cyan-500">
        <p class="text-sm text-slate-600 mb-2">本月流水金额</p>
        <p class="text-3xl font-bold text-slate-800">$<span id="monthAmount">0</span></p>
    </div>
</div>

<!-- Filter Bar -->
<div class="bg-white rounded-xl shadow-lg p-6 mb-6">
    <div class="grid grid-cols-1 md:grid-cols-6 gap-4">
        <div>
            <label class="block text-sm font-semibold text-slate-700 mb-2">搜索</label>
            <input type="text" id="searchKeyword" placeholder="订单号/用户名" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
        </div>
        <div>
            <label class="block text-sm font-semibold text-slate-700 mb-2">流水类型</label>
            <select id="filterType" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                <option value="">全部类型</option>
                <option value="withdraw">出金申请</option>
                <option value="fee">手续费</option>
                <option value="refund">退款</option>
                <option value="adjustment">调整</option>
            </select>
        </div>
        <div>
            <label class="block text-sm font-semibold text-slate-700 mb-2">币种</label>
            <select id="filterCurrency" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                <option value="">全部币种</option>
                <option value="USD">USD</option>
                <option value="CNY">CNY</option>
                <option value="EUR">EUR</option>
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
            <button onclick="searchFlows()" class="w-full px-6 py-2 bg-gradient-to-r from-blue-500 to-indigo-600 text-white font-semibold rounded-lg hover:shadow-lg transition">
                <i class="fas fa-search mr-2"></i>搜索
            </button>
        </div>
    </div>
</div>

<!-- Flows Table -->
<div class="bg-white rounded-xl shadow-lg overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full">
            <thead class="bg-slate-50 border-b border-slate-200">
                <tr>
                    <th class="px-6 py-4 text-left text-xs font-semibold text-slate-600 uppercase tracking-wider">流水号</th>
                    <th class="px-6 py-4 text-left text-xs font-semibold text-slate-600 uppercase tracking-wider">用户信息</th>
                    <th class="px-6 py-4 text-left text-xs font-semibold text-slate-600 uppercase tracking-wider">流水类型</th>
                    <th class="px-6 py-4 text-left text-xs font-semibold text-slate-600 uppercase tracking-wider">关联订单</th>
                    <th class="px-6 py-4 text-left text-xs font-semibold text-slate-600 uppercase tracking-wider">金额</th>
                    <th class="px-6 py-4 text-left text-xs font-semibold text-slate-600 uppercase tracking-wider">余额变动</th>
                    <th class="px-6 py-4 text-left text-xs font-semibold text-slate-600 uppercase tracking-wider">账户余额</th>
                    <th class="px-6 py-4 text-left text-xs font-semibold text-slate-600 uppercase tracking-wider">交易时间</th>
                    <th class="px-6 py-4 text-left text-xs font-semibold text-slate-600 uppercase tracking-wider">操作</th>
                </tr>
            </thead>
            <tbody id="flowsTableBody" class="divide-y divide-slate-200">
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
    <div class="bg-white rounded-2xl max-w-3xl w-full max-h-[90vh] overflow-y-auto">
        <div class="sticky top-0 bg-gradient-to-r from-blue-500 to-indigo-600 px-6 py-4 flex items-center justify-between">
            <h3 class="text-xl font-bold text-white">流水详情</h3>
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
    loadFlows();
});

function loadStats() {
    fetch('{{ route("admin_api_withdraw_flows_stats") }}', {
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(res => res.json())
    .then(data => {
        if (data.success || data.code === 200) {
            document.getElementById('todayCount').textContent = formatNumber(data.todayCount || 0);
            document.getElementById('todayAmount').textContent = formatNumber(data.todayAmount || 0);
            document.getElementById('monthCount').textContent = formatNumber(data.monthCount || 0);
            document.getElementById('monthAmount').textContent = formatNumber(data.monthAmount || 0);
        }
    })
    .catch(err => console.error('Load stats error:', err));
}

function loadFlows(page = 1) {
    currentPage = page;
    const keyword = document.getElementById('searchKeyword').value;
    const type = document.getElementById('filterType').value;
    const currency = document.getElementById('filterCurrency').value;
    const startDate = document.getElementById('filterStartDate').value;
    const endDate = document.getElementById('filterEndDate').value;

    const params = new URLSearchParams({
        page: page,
        keyword: keyword,
        type: type,
        currency: currency,
        start_date: startDate,
        end_date: endDate
    });

    fetch(`{{ route('admin_api_withdraw_flows_list') }}?${params}`, {
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(res => res.json())
    .then(data => {
        if (data.success || data.code === 200) {
            renderTable(data.flows || []);
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

function renderTable(flows) {
    const tbody = document.getElementById('flowsTableBody');

    if (flows.length === 0) {
        tbody.innerHTML = `
            <tr>
                <td colspan="9" class="px-6 py-12 text-center text-slate-500">
                    <i class="fas fa-inbox text-3xl mb-3"></i>
                    <p>暂无流水数据</p>
                </td>
            </tr>
        `;
        return;
    }

    tbody.innerHTML = flows.map(f => `
        <tr class="hover:bg-slate-50 transition">
            <td class="px-6 py-4">
                <p class="text-sm font-mono text-slate-800">${f.flow_no || '-'}</p>
            </td>
            <td class="px-6 py-4">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 bg-gradient-to-br from-blue-500 to-indigo-600 rounded-full flex items-center justify-center text-white font-bold">
                        ${(f.username || 'U').charAt(0).toUpperCase()}
                    </div>
                    <div>
                        <p class="text-sm font-semibold text-slate-800">${f.username || 'N/A'}</p>
                        <p class="text-xs text-slate-500">ID: ${f.user_id || '-'}</p>
                    </div>
                </div>
            </td>
            <td class="px-6 py-4">${getTypeBadge(f.type)}</td>
            <td class="px-6 py-4">
                <p class="text-xs font-mono text-slate-600">${f.order_no || '-'}</p>
            </td>
            <td class="px-6 py-4">
                <p class="text-base font-bold ${f.amount >= 0 ? 'text-green-600' : 'text-red-600'}">
                    ${f.amount >= 0 ? '+' : ''}$${formatNumber(Math.abs(f.amount || 0))}
                </p>
            </td>
            <td class="px-6 py-4">
                <p class="text-sm ${f.balance_change >= 0 ? 'text-green-600' : 'text-red-600'}">
                    ${f.balance_change >= 0 ? '+' : ''}$${formatNumber(Math.abs(f.balance_change || 0))}
                </p>
            </td>
            <td class="px-6 py-4">
                <p class="text-sm font-semibold text-slate-800">$${formatNumber(f.balance_after || 0)}</p>
            </td>
            <td class="px-6 py-4 text-sm text-slate-600">${f.created_at || '-'}</td>
            <td class="px-6 py-4">
                <button onclick="viewDetail(${f.id})" class="px-3 py-1 bg-blue-100 text-blue-700 text-xs font-semibold rounded hover:bg-blue-200 transition">
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
        html += `<button onclick="loadFlows(${currentPage - 1})" class="px-3 py-1 border border-slate-300 rounded hover:bg-slate-50">上一页</button>`;
    }
    for (let i = Math.max(1, currentPage - 2); i <= Math.min(totalPages, currentPage + 2); i++) {
        html += `<button onclick="loadFlows(${i})" class="px-3 py-1 border ${i === currentPage ? 'bg-blue-500 text-white' : 'border-slate-300 hover:bg-slate-50'} rounded">${i}</button>`;
    }
    if (currentPage < totalPages) {
        html += `<button onclick="loadFlows(${currentPage + 1})" class="px-3 py-1 border border-slate-300 rounded hover:bg-slate-50">下一页</button>`;
    }
    paginationDiv.innerHTML = html;
}

function getTypeBadge(type) {
    const badges = {
        'withdraw': '<span class="px-2 py-1 bg-orange-100 text-orange-700 text-xs font-semibold rounded-full">出金申请</span>',
        'fee': '<span class="px-2 py-1 bg-purple-100 text-purple-700 text-xs font-semibold rounded-full">手续费</span>',
        'refund': '<span class="px-2 py-1 bg-green-100 text-green-700 text-xs font-semibold rounded-full">退款</span>',
        'adjustment': '<span class="px-2 py-1 bg-blue-100 text-blue-700 text-xs font-semibold rounded-full">调整</span>'
    };
    return badges[type] || '<span class="px-2 py-1 bg-slate-100 text-slate-700 text-xs font-semibold rounded-full">其他</span>';
}

function searchFlows() {
    loadFlows(1);
}

function viewDetail(id) {
    fetch(`{{ route('admin_api_withdraw_flows_detail', ['id' => '__ID__']) }}`.replace('__ID__', id), {
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(res => res.json())
    .then(data => {
        if (data.success && data.flow) {
            const f = data.flow;
            document.getElementById('modalContent').innerHTML = `
                <div class="space-y-6">
                    <div class="flex items-center justify-center mb-4">
                        <div class="w-16 h-16 bg-gradient-to-br ${f.amount >= 0 ? 'from-green-500 to-emerald-600' : 'from-red-500 to-pink-600'} rounded-full flex items-center justify-center">
                            <i class="fas ${f.amount >= 0 ? 'fa-arrow-up' : 'fa-arrow-down'} text-3xl text-white"></i>
                        </div>
                    </div>

                    <div class="text-center mb-6">
                        <p class="text-2xl font-bold ${f.amount >= 0 ? 'text-green-600' : 'text-red-600'} mb-2">
                            ${f.amount >= 0 ? '+' : ''}$${formatNumber(Math.abs(f.amount || 0))}
                        </p>
                        ${getTypeBadge(f.type)}
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div class="col-span-2 bg-slate-50 rounded-lg p-4">
                            <p class="text-sm text-slate-600 mb-1">流水号</p>
                            <p class="text-base font-mono text-slate-800">${f.flow_no || '-'}</p>
                        </div>
                        <div><p class="text-sm text-slate-600 mb-1">用户</p><p class="text-base font-semibold text-slate-800">${f.username || 'N/A'}</p></div>
                        <div><p class="text-sm text-slate-600 mb-1">用户ID</p><p class="text-base text-slate-800">${f.user_id || '-'}</p></div>
                        <div><p class="text-sm text-slate-600 mb-1">关联订单</p><p class="text-xs font-mono text-slate-800">${f.order_no || '-'}</p></div>
                        <div><p class="text-sm text-slate-600 mb-1">币种</p><p class="text-base text-slate-800">${f.currency || 'USD'}</p></div>
                        <div><p class="text-sm text-slate-600 mb-1">交易金额</p><p class="text-base font-bold ${f.amount >= 0 ? 'text-green-600' : 'text-red-600'}">${f.amount >= 0 ? '+' : ''}$${formatNumber(Math.abs(f.amount || 0))}</p></div>
                        <div><p class="text-sm text-slate-600 mb-1">余额变动</p><p class="text-base ${f.balance_change >= 0 ? 'text-green-600' : 'text-red-600'}">${f.balance_change >= 0 ? '+' : ''}$${formatNumber(Math.abs(f.balance_change || 0))}</p></div>
                        <div><p class="text-sm text-slate-600 mb-1">交易前余额</p><p class="text-base text-slate-800">$${formatNumber(f.balance_before || 0)}</p></div>
                        <div><p class="text-sm text-slate-600 mb-1">交易后余额</p><p class="text-base font-semibold text-slate-800">$${formatNumber(f.balance_after || 0)}</p></div>
                        <div class="col-span-2"><p class="text-sm text-slate-600 mb-1">交易时间</p><p class="text-base text-slate-800">${f.created_at || '-'}</p></div>
                    </div>

                    ${f.remark ? `
                        <div class="border-t border-slate-200 pt-4">
                            <p class="text-sm text-slate-600 mb-2">备注说明</p>
                            <p class="text-base text-slate-800 bg-slate-50 rounded-lg p-3">${f.remark}</p>
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

function exportFlows() {
    const startDate = document.getElementById('filterStartDate').value;
    const endDate = document.getElementById('filterEndDate').value;
    const type = document.getElementById('filterType').value;

    let url = '{{ route("admin_api_withdraw_flows_export") }}';
    const params = new URLSearchParams();
    if (startDate) params.append('start_date', startDate);
    if (endDate) params.append('end_date', endDate);
    if (type) params.append('type', type);

    if (params.toString()) {
        url += '?' + params.toString();
    }

    window.location.href = url;
}

function formatNumber(num) {
    return new Intl.NumberFormat('en-US').format(num);
}

function showError(message) {
    document.getElementById('flowsTableBody').innerHTML = `
        <tr><td colspan="9" class="px-6 py-12 text-center text-red-500">
            <i class="fas fa-exclamation-circle text-3xl mb-3"></i><p>${message}</p>
        </td></tr>
    `;
}
</script>
@endsection
