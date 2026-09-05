@extends('admin-tailwind.layouts.app')

@section('title', '已完成出金 - 管理后台')

@section('content')
<!-- Page Header -->
<div class="flex items-center justify-between mb-8">
    <div>
        <div class="flex items-center gap-3 mb-2">
            <a href="{{ route('admin_tailwind_page_withdrawals') }}" class="text-slate-600 hover:text-slate-800">
                <i class="fas fa-arrow-left"></i> 返回列表
            </a>
        </div>
        <h1 class="text-3xl font-bold text-slate-800">已完成出金</h1>
        <p class="text-slate-600 mt-2">查看所有成功完成的出金记录</p>
    </div>
    <div class="flex gap-3">
        <button onclick="exportCompleted()" class="px-6 py-3 bg-gradient-to-r from-green-500 to-green-600 text-white font-semibold rounded-lg hover:shadow-lg transition">
            <i class="fas fa-download mr-2"></i>导出数据
        </button>
    </div>
</div>

<!-- Stats Cards -->
<div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-6">
    <div class="bg-white rounded-xl shadow-lg p-6 border-l-4 border-green-500">
        <p class="text-sm text-slate-600 mb-2">今日完成</p>
        <p class="text-3xl font-bold text-slate-800" id="todayCompleted">0</p>
    </div>
    <div class="bg-white rounded-xl shadow-lg p-6 border-l-4 border-emerald-500">
        <p class="text-sm text-slate-600 mb-2">今日金额</p>
        <p class="text-3xl font-bold text-slate-800">$<span id="todayAmount">0</span></p>
    </div>
    <div class="bg-white rounded-xl shadow-lg p-6 border-l-4 border-teal-500">
        <p class="text-sm text-slate-600 mb-2">本月完成</p>
        <p class="text-3xl font-bold text-slate-800" id="monthCompleted">0</p>
    </div>
    <div class="bg-white rounded-xl shadow-lg p-6 border-l-4 border-cyan-500">
        <p class="text-sm text-slate-600 mb-2">本月金额</p>
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
        <div>
            <label class="block text-sm font-semibold text-slate-700 mb-2">处理人</label>
            <select id="filterProcessor" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                <option value="">全部处理人</option>
                <option value="admin1">管理员1</option>
                <option value="admin2">管理员2</option>
                <option value="admin3">管理员3</option>
            </select>
        </div>
        <div class="flex items-end">
            <button onclick="searchCompleted()" class="w-full px-6 py-2 bg-gradient-to-r from-blue-500 to-indigo-600 text-white font-semibold rounded-lg hover:shadow-lg transition">
                <i class="fas fa-search mr-2"></i>搜索
            </button>
        </div>
    </div>
</div>

<!-- Completed Withdrawals Table -->
<div class="bg-white rounded-xl shadow-lg overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full">
            <thead class="bg-slate-50 border-b border-slate-200">
                <tr>
                    <th class="px-6 py-4 text-left text-xs font-semibold text-slate-600 uppercase tracking-wider">订单号</th>
                    <th class="px-6 py-4 text-left text-xs font-semibold text-slate-600 uppercase tracking-wider">用户信息</th>
                    <th class="px-6 py-4 text-left text-xs font-semibold text-slate-600 uppercase tracking-wider">申请金额</th>
                    <th class="px-6 py-4 text-left text-xs font-semibold text-slate-600 uppercase tracking-wider">手续费</th>
                    <th class="px-6 py-4 text-left text-xs font-semibold text-slate-600 uppercase tracking-wider">实际到账</th>
                    <th class="px-6 py-4 text-left text-xs font-semibold text-slate-600 uppercase tracking-wider">交易凭证</th>
                    <th class="px-6 py-4 text-left text-xs font-semibold text-slate-600 uppercase tracking-wider">处理人</th>
                    <th class="px-6 py-4 text-left text-xs font-semibold text-slate-600 uppercase tracking-wider">完成时间</th>
                    <th class="px-6 py-4 text-left text-xs font-semibold text-slate-600 uppercase tracking-wider">操作</th>
                </tr>
            </thead>
            <tbody id="completedTableBody" class="divide-y divide-slate-200">
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
        <div class="sticky top-0 bg-gradient-to-r from-green-500 to-emerald-600 px-6 py-4 flex items-center justify-between">
            <h3 class="text-xl font-bold text-white">出金详情</h3>
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
    loadCompletedWithdrawals();
});

function loadStats() {
    fetch('{{ route("admin_api_withdrawals_completed_stats") }}', {
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(res => res.json())
    .then(data => {
        if (data.success || data.code === 200) {
            document.getElementById('todayCompleted').textContent = formatNumber(data.todayCompleted || 0);
            document.getElementById('todayAmount').textContent = formatNumber(data.todayAmount || 0);
            document.getElementById('monthCompleted').textContent = formatNumber(data.monthCompleted || 0);
            document.getElementById('monthAmount').textContent = formatNumber(data.monthAmount || 0);
        }
    })
    .catch(err => console.error('Load stats error:', err));
}

function loadCompletedWithdrawals(page = 1) {
    currentPage = page;
    const keyword = document.getElementById('searchKeyword').value;
    const currency = document.getElementById('filterCurrency').value;
    const startDate = document.getElementById('filterStartDate').value;
    const endDate = document.getElementById('filterEndDate').value;
    const processor = document.getElementById('filterProcessor').value;

    const params = new URLSearchParams({
        page: page,
        keyword: keyword,
        currency: currency,
        start_date: startDate,
        end_date: endDate,
        processor: processor
    });

    fetch(`{{ route('admin_api_withdrawals_completed_list') }}?${params}`, {
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(res => res.json())
    .then(data => {
        if (data.success || data.code === 200) {
            renderTable(data.withdrawals || []);
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

function renderTable(withdrawals) {
    const tbody = document.getElementById('completedTableBody');

    if (withdrawals.length === 0) {
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

    tbody.innerHTML = withdrawals.map(w => `
        <tr class="hover:bg-slate-50 transition">
            <td class="px-6 py-4">
                <p class="text-sm font-mono text-slate-800">${w.order_no || '-'}</p>
            </td>
            <td class="px-6 py-4">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 bg-gradient-to-br from-green-500 to-emerald-600 rounded-full flex items-center justify-center text-white font-bold">
                        ${(w.username || 'U').charAt(0).toUpperCase()}
                    </div>
                    <div>
                        <p class="text-sm font-semibold text-slate-800">${w.username || 'N/A'}</p>
                        <p class="text-xs text-slate-500">ID: ${w.user_id || '-'}</p>
                    </div>
                </div>
            </td>
            <td class="px-6 py-4">
                <p class="text-base font-bold text-orange-600">$${formatNumber(w.amount || 0)}</p>
            </td>
            <td class="px-6 py-4 text-sm text-slate-600">$${formatNumber(w.fee || 0)}</td>
            <td class="px-6 py-4">
                <p class="text-base font-semibold text-green-600">$${formatNumber((w.amount || 0) - (w.fee || 0))}</p>
            </td>
            <td class="px-6 py-4">
                <p class="text-xs font-mono text-slate-600">${w.transaction_ref || '-'}</p>
            </td>
            <td class="px-6 py-4">
                <div class="flex items-center gap-2">
                    <div class="w-8 h-8 bg-gradient-to-br from-purple-500 to-indigo-600 rounded-full flex items-center justify-center text-white text-xs font-bold">
                        ${(w.processor_name || 'A').charAt(0).toUpperCase()}
                    </div>
                    <span class="text-sm text-slate-800">${w.processor_name || '-'}</span>
                </div>
            </td>
            <td class="px-6 py-4 text-sm text-slate-600">${w.completed_at || '-'}</td>
            <td class="px-6 py-4">
                <button onclick="viewDetail(${w.id})" class="px-3 py-1 bg-blue-100 text-blue-700 text-xs font-semibold rounded hover:bg-blue-200 transition">
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
        html += `<button onclick="loadCompletedWithdrawals(${currentPage - 1})" class="px-3 py-1 border border-slate-300 rounded hover:bg-slate-50">上一页</button>`;
    }
    for (let i = Math.max(1, currentPage - 2); i <= Math.min(totalPages, currentPage + 2); i++) {
        html += `<button onclick="loadCompletedWithdrawals(${i})" class="px-3 py-1 border ${i === currentPage ? 'bg-blue-500 text-white' : 'border-slate-300 hover:bg-slate-50'} rounded">${i}</button>`;
    }
    if (currentPage < totalPages) {
        html += `<button onclick="loadCompletedWithdrawals(${currentPage + 1})" class="px-3 py-1 border border-slate-300 rounded hover:bg-slate-50">下一页</button>`;
    }
    paginationDiv.innerHTML = html;
}

function searchCompleted() {
    loadCompletedWithdrawals(1);
}

function viewDetail(id) {
    fetch(`{{ route('admin_api_withdrawals_detail', ['id' => '__ID__']) }}`.replace('__ID__', id), {
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(res => res.json())
    .then(data => {
        if (data.success && data.withdrawal) {
            const w = data.withdrawal;
            document.getElementById('modalContent').innerHTML = `
                <div class="space-y-6">
                    <div class="flex items-center justify-center mb-4">
                        <div class="w-16 h-16 bg-gradient-to-br from-green-500 to-emerald-600 rounded-full flex items-center justify-center">
                            <i class="fas fa-check text-3xl text-white"></i>
                        </div>
                    </div>

                    <div class="text-center mb-6">
                        <p class="text-2xl font-bold text-slate-800 mb-2">出金成功</p>
                        <p class="text-lg text-green-600 font-bold">$${formatNumber((w.amount || 0) - (w.fee || 0))}</p>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div class="col-span-2 bg-slate-50 rounded-lg p-4">
                            <p class="text-sm text-slate-600 mb-1">订单号</p>
                            <p class="text-base font-mono text-slate-800">${w.order_no || '-'}</p>
                        </div>
                        <div><p class="text-sm text-slate-600 mb-1">用户</p><p class="text-base font-semibold text-slate-800">${w.username || 'N/A'}</p></div>
                        <div><p class="text-sm text-slate-600 mb-1">用户ID</p><p class="text-base text-slate-800">${w.user_id || '-'}</p></div>
                        <div><p class="text-sm text-slate-600 mb-1">申请金额</p><p class="text-base font-bold text-orange-600">$${formatNumber(w.amount || 0)}</p></div>
                        <div><p class="text-sm text-slate-600 mb-1">手续费</p><p class="text-base text-slate-800">$${formatNumber(w.fee || 0)}</p></div>
                        <div class="col-span-2"><p class="text-sm text-slate-600 mb-1">实际到账</p><p class="text-xl font-bold text-green-600">$${formatNumber((w.amount || 0) - (w.fee || 0))}</p></div>
                        <div><p class="text-sm text-slate-600 mb-1">银行卡号</p><p class="text-base font-mono text-slate-800">${w.bank_card || '-'}</p></div>
                        <div><p class="text-sm text-slate-600 mb-1">开户行</p><p class="text-base text-slate-800">${w.bank_name || '-'}</p></div>
                        <div><p class="text-sm text-slate-600 mb-1">开户人</p><p class="text-base text-slate-800">${w.bank_account_name || '-'}</p></div>
                        <div><p class="text-sm text-slate-600 mb-1">交易凭证号</p><p class="text-base font-mono text-slate-800">${w.transaction_ref || '-'}</p></div>
                        <div><p class="text-sm text-slate-600 mb-1">处理人</p><p class="text-base text-slate-800">${w.processor_name || '-'}</p></div>
                        <div><p class="text-sm text-slate-600 mb-1">币种</p><p class="text-base text-slate-800">${w.currency || 'USD'}</p></div>
                        <div><p class="text-sm text-slate-600 mb-1">申请时间</p><p class="text-base text-slate-800">${w.created_at || '-'}</p></div>
                        <div><p class="text-sm text-slate-600 mb-1">完成时间</p><p class="text-base text-slate-800">${w.completed_at || '-'}</p></div>
                    </div>

                    ${w.remark ? `
                        <div class="border-t border-slate-200 pt-4">
                            <p class="text-sm text-slate-600 mb-2">备注说明</p>
                            <p class="text-base text-slate-800 bg-slate-50 rounded-lg p-3">${w.remark}</p>
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

function exportCompleted() {
    const startDate = document.getElementById('filterStartDate').value;
    const endDate = document.getElementById('filterEndDate').value;

    let url = '{{ route("admin_api_withdrawals_completed_export") }}';
    if (startDate || endDate) {
        const params = new URLSearchParams();
        if (startDate) params.append('start_date', startDate);
        if (endDate) params.append('end_date', endDate);
        url += '?' + params.toString();
    }

    window.location.href = url;
}

function formatNumber(num) {
    return new Intl.NumberFormat('en-US').format(num);
}

function showError(message) {
    document.getElementById('completedTableBody').innerHTML = `
        <tr><td colspan="9" class="px-6 py-12 text-center text-red-500">
            <i class="fas fa-exclamation-circle text-3xl mb-3"></i><p>${message}</p>
        </td></tr>
    `;
}
</script>
@endsection
