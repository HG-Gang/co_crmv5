@extends('admin-tailwind.layouts.app')

@section('title', '出金管理 - 管理后台')

@section('content')
<!-- Page Header -->
<div class="flex items-center justify-between mb-8">
    <div>
        <h1 class="text-3xl font-bold text-slate-800">出金管理</h1>
        <p class="text-slate-600 mt-2">管理所有用户出金记录</p>
    </div>
    <div class="flex gap-3">
        <button onclick="exportWithdrawals()" class="px-6 py-3 bg-gradient-to-r from-green-500 to-green-600 text-white font-semibold rounded-lg hover:shadow-lg transition">
            <i class="fas fa-download mr-2"></i>导出数据
        </button>
        <button onclick="showBatchApproveModal()" class="px-6 py-3 bg-gradient-to-r from-blue-500 to-indigo-600 text-white font-semibold rounded-lg hover:shadow-lg transition">
            <i class="fas fa-check-double mr-2"></i>批量审核
        </button>
    </div>
</div>

<!-- Stats Cards -->
<div class="grid grid-cols-1 md:grid-cols-5 gap-6 mb-6">
    <div class="bg-white rounded-xl shadow-lg p-6 border-l-4 border-yellow-500">
        <p class="text-sm text-slate-600 mb-2">待处理</p>
        <p class="text-3xl font-bold text-slate-800" id="pendingCount">0</p>
    </div>
    <div class="bg-white rounded-xl shadow-lg p-6 border-l-4 border-blue-500">
        <p class="text-sm text-slate-600 mb-2">处理中</p>
        <p class="text-3xl font-bold text-slate-800" id="processingCount">0</p>
    </div>
    <div class="bg-white rounded-xl shadow-lg p-6 border-l-4 border-green-500">
        <p class="text-sm text-slate-600 mb-2">已完成</p>
        <p class="text-3xl font-bold text-slate-800" id="completedCount">0</p>
    </div>
    <div class="bg-white rounded-xl shadow-lg p-6 border-l-4 border-red-500">
        <p class="text-sm text-slate-600 mb-2">失败</p>
        <p class="text-3xl font-bold text-slate-800" id="failedCount">0</p>
    </div>
    <div class="bg-white rounded-xl shadow-lg p-6 border-l-4 border-orange-500">
        <p class="text-sm text-slate-600 mb-2">今日出金</p>
        <p class="text-3xl font-bold text-slate-800">$<span id="todayWithdrawals">0</span></p>
    </div>
</div>

<!-- Tab Navigation -->
<div class="bg-white rounded-t-xl shadow-lg border-b border-slate-200">
    <div class="flex">
        <button onclick="switchTab('all')" id="tab-all" class="px-6 py-4 text-sm font-semibold text-blue-600 border-b-2 border-blue-600 transition">
            全部
        </button>
        <button onclick="switchTab('pending')" id="tab-pending" class="px-6 py-4 text-sm font-semibold text-slate-600 hover:text-slate-800 transition">
            待处理 <span id="badge-pending" class="ml-2 px-2 py-1 bg-yellow-100 text-yellow-700 text-xs rounded-full">0</span>
        </button>
        <button onclick="switchTab('processing')" id="tab-processing" class="px-6 py-4 text-sm font-semibold text-slate-600 hover:text-slate-800 transition">
            处理中 <span id="badge-processing" class="ml-2 px-2 py-1 bg-blue-100 text-blue-700 text-xs rounded-full">0</span>
        </button>
        <button onclick="switchTab('completed')" id="tab-completed" class="px-6 py-4 text-sm font-semibold text-slate-600 hover:text-slate-800 transition">
            已完成
        </button>
        <button onclick="switchTab('failed')" id="tab-failed" class="px-6 py-4 text-sm font-semibold text-slate-600 hover:text-slate-800 transition">
            失败
        </button>
    </div>
</div>

<!-- Search & Filter -->
<div class="bg-white shadow-lg p-6 border-b border-slate-200">
    <div class="grid grid-cols-1 md:grid-cols-5 gap-4">
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
        <div class="flex items-end">
            <button onclick="searchWithdrawals()" class="w-full px-6 py-2 bg-gradient-to-r from-blue-500 to-indigo-600 text-white font-semibold rounded-lg hover:shadow-lg transition">
                <i class="fas fa-search mr-2"></i>搜索
            </button>
        </div>
    </div>
</div>

<!-- Withdrawals Table -->
<div class="bg-white rounded-b-xl shadow-lg overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full">
            <thead class="bg-slate-50 border-b border-slate-200">
                <tr>
                    <th class="px-6 py-4 text-left">
                        <input type="checkbox" id="selectAll" onchange="toggleSelectAll()" class="w-4 h-4 text-blue-600 border-slate-300 rounded focus:ring-2 focus:ring-blue-500">
                    </th>
                    <th class="px-6 py-4 text-left text-xs font-semibold text-slate-600 uppercase tracking-wider">订单号</th>
                    <th class="px-6 py-4 text-left text-xs font-semibold text-slate-600 uppercase tracking-wider">用户信息</th>
                    <th class="px-6 py-4 text-left text-xs font-semibold text-slate-600 uppercase tracking-wider">金额</th>
                    <th class="px-6 py-4 text-left text-xs font-semibold text-slate-600 uppercase tracking-wider">手续费</th>
                    <th class="px-6 py-4 text-left text-xs font-semibold text-slate-600 uppercase tracking-wider">实际到账</th>
                    <th class="px-6 py-4 text-left text-xs font-semibold text-slate-600 uppercase tracking-wider">状态</th>
                    <th class="px-6 py-4 text-left text-xs font-semibold text-slate-600 uppercase tracking-wider">申请时间</th>
                    <th class="px-6 py-4 text-left text-xs font-semibold text-slate-600 uppercase tracking-wider">操作</th>
                </tr>
            </thead>
            <tbody id="withdrawalsTableBody" class="divide-y divide-slate-200">
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
    <div class="bg-white rounded-2xl max-w-2xl w-full max-h-[90vh] overflow-y-auto">
        <div class="sticky top-0 bg-gradient-to-r from-orange-500 to-orange-600 px-6 py-4 flex items-center justify-between">
            <h3 class="text-xl font-bold text-white">出金详情</h3>
            <button onclick="closeDetailModal()" class="text-white hover:text-slate-200">
                <i class="fas fa-times text-xl"></i>
            </button>
        </div>
        <div id="modalContent" class="p-6"></div>
    </div>
</div>

<!-- Approve Modal -->
<div id="approveModal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl max-w-md w-full">
        <div class="bg-gradient-to-r from-green-500 to-green-600 px-6 py-4">
            <h3 class="text-xl font-bold text-white">审核出金</h3>
        </div>
        <div class="p-6">
            <div class="mb-4">
                <label class="block text-sm font-semibold text-slate-700 mb-2">审核结果</label>
                <select id="approveResult" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                    <option value="approved">通过并处理</option>
                    <option value="rejected">拒绝</option>
                </select>
            </div>
            <div class="mb-4">
                <label class="block text-sm font-semibold text-slate-700 mb-2">备注</label>
                <textarea id="approveRemark" rows="3" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" placeholder="请输入备注信息"></textarea>
            </div>
            <div class="flex gap-3">
                <button onclick="closeApproveModal()" class="flex-1 px-4 py-2 border border-slate-300 text-slate-700 font-semibold rounded-lg hover:bg-slate-50 transition">
                    取消
                </button>
                <button onclick="submitApprove()" class="flex-1 px-4 py-2 bg-gradient-to-r from-green-500 to-green-600 text-white font-semibold rounded-lg hover:shadow-lg transition">
                    提交
                </button>
            </div>
        </div>
    </div>
</div>

<script>
let currentPage = 1;
let totalPages = 1;
let currentTab = 'all';
let currentWithdrawalId = null;
let selectedWithdrawals = [];

document.addEventListener('DOMContentLoaded', function() {
    loadWithdrawalsStats();
    loadWithdrawals();
});

function loadWithdrawalsStats() {
    fetch('{{ route("admin_api_withdrawals_stats") }}', {
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(res => res.json())
    .then(data => {
        if (data.success || data.code === 200) {
            document.getElementById('pendingCount').textContent = formatNumber(data.pendingCount || 0);
            document.getElementById('processingCount').textContent = formatNumber(data.processingCount || 0);
            document.getElementById('completedCount').textContent = formatNumber(data.completedCount || 0);
            document.getElementById('failedCount').textContent = formatNumber(data.failedCount || 0);
            document.getElementById('todayWithdrawals').textContent = formatNumber(data.todayWithdrawals || 0);

            document.getElementById('badge-pending').textContent = data.pendingCount || 0;
            document.getElementById('badge-processing').textContent = data.processingCount || 0;
        }
    })
    .catch(err => console.error('Load stats error:', err));
}

function loadWithdrawals(page = 1) {
    currentPage = page;
    const keyword = document.getElementById('searchKeyword').value;
    const currency = document.getElementById('filterCurrency').value;
    const startDate = document.getElementById('filterStartDate').value;
    const endDate = document.getElementById('filterEndDate').value;

    const params = new URLSearchParams({
        page: page,
        keyword: keyword,
        currency: currency,
        start_date: startDate,
        end_date: endDate,
        status: currentTab === 'all' ? '' : currentTab
    });

    fetch(`{{ route('admin_api_withdrawals_list') }}?${params}`, {
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(res => res.json())
    .then(data => {
        if (data.success || data.code === 200) {
            renderWithdrawalsTable(data.withdrawals || []);
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

function renderWithdrawalsTable(withdrawals) {
    const tbody = document.getElementById('withdrawalsTableBody');

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

    tbody.innerHTML = withdrawals.map(withdrawal => `
        <tr class="hover:bg-slate-50 transition">
            <td class="px-6 py-4">
                <input type="checkbox" class="withdraw-checkbox w-4 h-4 text-blue-600 border-slate-300 rounded" value="${withdrawal.id}" onchange="updateSelection()">
            </td>
            <td class="px-6 py-4">
                <p class="text-sm font-mono text-slate-800">${withdrawal.order_no || '-'}</p>
            </td>
            <td class="px-6 py-4">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 bg-gradient-to-br from-orange-500 to-orange-600 rounded-full flex items-center justify-center text-white font-bold">
                        ${(withdrawal.username || 'U').charAt(0).toUpperCase()}
                    </div>
                    <div>
                        <p class="text-sm font-semibold text-slate-800">${withdrawal.username || 'N/A'}</p>
                        <p class="text-xs text-slate-500">ID: ${withdrawal.user_id || '-'}</p>
                    </div>
                </div>
            </td>
            <td class="px-6 py-4">
                <p class="text-lg font-bold text-orange-600">$${formatNumber(withdrawal.amount || 0)}</p>
            </td>
            <td class="px-6 py-4 text-sm text-slate-600">$${formatNumber(withdrawal.fee || 0)}</td>
            <td class="px-6 py-4">
                <p class="text-sm font-semibold text-green-600">$${formatNumber((withdrawal.amount || 0) - (withdrawal.fee || 0))}</p>
            </td>
            <td class="px-6 py-4">
                ${getStatusBadge(withdrawal.status)}
            </td>
            <td class="px-6 py-4 text-sm text-slate-600">${withdrawal.created_at || '-'}</td>
            <td class="px-6 py-4">
                <div class="flex gap-2">
                    <button onclick="viewWithdrawal(${withdrawal.id})" class="px-3 py-1 bg-blue-100 text-blue-700 text-xs font-semibold rounded hover:bg-blue-200 transition">
                        <i class="fas fa-eye mr-1"></i>详情
                    </button>
                    ${withdrawal.status === 'pending'
                        ? `<button onclick="approveWithdrawal(${withdrawal.id})" class="px-3 py-1 bg-green-100 text-green-700 text-xs font-semibold rounded hover:bg-green-200 transition">
                            <i class="fas fa-check mr-1"></i>审核
                        </button>`
                        : ''
                    }
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
        html += `<button onclick="loadWithdrawals(${currentPage - 1})" class="px-3 py-1 border border-slate-300 rounded hover:bg-slate-50">上一页</button>`;
    }
    for (let i = Math.max(1, currentPage - 2); i <= Math.min(totalPages, currentPage + 2); i++) {
        html += `<button onclick="loadWithdrawals(${i})" class="px-3 py-1 border ${i === currentPage ? 'bg-blue-500 text-white' : 'border-slate-300 hover:bg-slate-50'} rounded">${i}</button>`;
    }
    if (currentPage < totalPages) {
        html += `<button onclick="loadWithdrawals(${currentPage + 1})" class="px-3 py-1 border border-slate-300 rounded hover:bg-slate-50">下一页</button>`;
    }
    paginationDiv.innerHTML = html;
}

function getStatusBadge(status) {
    const badges = {
        'pending': '<span class="px-3 py-1 bg-yellow-100 text-yellow-700 text-xs font-semibold rounded-full">待处理</span>',
        'processing': '<span class="px-3 py-1 bg-blue-100 text-blue-700 text-xs font-semibold rounded-full">处理中</span>',
        'completed': '<span class="px-3 py-1 bg-green-100 text-green-700 text-xs font-semibold rounded-full">已完成</span>',
        'failed': '<span class="px-3 py-1 bg-red-100 text-red-700 text-xs font-semibold rounded-full">失败</span>'
    };
    return badges[status] || '<span class="px-3 py-1 bg-slate-100 text-slate-700 text-xs font-semibold rounded-full">未知</span>';
}

function switchTab(tab) {
    currentTab = tab;
    document.querySelectorAll('[id^="tab-"]').forEach(btn => {
        btn.className = 'px-6 py-4 text-sm font-semibold text-slate-600 hover:text-slate-800 transition';
    });
    document.getElementById('tab-' + tab).className = 'px-6 py-4 text-sm font-semibold text-blue-600 border-b-2 border-blue-600 transition';
    loadWithdrawals(1);
}

function searchWithdrawals() {
    loadWithdrawals(1);
}

function viewWithdrawal(withdrawalId) {
    fetch(`{{ route('admin_api_withdrawals_detail', ['id' => '__ID__']) }}`.replace('__ID__', withdrawalId), {
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(res => res.json())
    .then(data => {
        if (data.success && data.withdrawal) {
            const w = data.withdrawal;
            document.getElementById('modalContent').innerHTML = `
                <div class="space-y-4">
                    <div class="grid grid-cols-2 gap-4">
                        <div><p class="text-sm text-slate-600 mb-1">订单号</p><p class="text-base font-semibold text-slate-800">${w.order_no || '-'}</p></div>
                        <div><p class="text-sm text-slate-600 mb-1">用户</p><p class="text-base font-semibold text-slate-800">${w.username || 'N/A'}</p></div>
                        <div><p class="text-sm text-slate-600 mb-1">申请金额</p><p class="text-base font-bold text-orange-600">$${formatNumber(w.amount || 0)}</p></div>
                        <div><p class="text-sm text-slate-600 mb-1">手续费</p><p class="text-base font-semibold text-slate-800">$${formatNumber(w.fee || 0)}</p></div>
                        <div><p class="text-sm text-slate-600 mb-1">实际到账</p><p class="text-base font-bold text-green-600">$${formatNumber((w.amount || 0) - (w.fee || 0))}</p></div>
                        <div><p class="text-sm text-slate-600 mb-1">状态</p>${getStatusBadge(w.status)}</div>
                        <div><p class="text-sm text-slate-600 mb-1">银行卡号</p><p class="text-base font-mono text-slate-800">${w.bank_card || '-'}</p></div>
                        <div><p class="text-sm text-slate-600 mb-1">开户行</p><p class="text-base text-slate-800">${w.bank_name || '-'}</p></div>
                        <div><p class="text-sm text-slate-600 mb-1">申请时间</p><p class="text-base font-semibold text-slate-800">${w.created_at || '-'}</p></div>
                        <div><p class="text-sm text-slate-600 mb-1">处理时间</p><p class="text-base font-semibold text-slate-800">${w.processed_at || '-'}</p></div>
                        <div><p class="text-sm text-slate-600 mb-1">处理人</p><p class="text-base font-semibold text-slate-800">${w.processor || '-'}</p></div>
                    </div>
                    ${w.remark ? `<div><p class="text-sm text-slate-600 mb-1">备注</p><p class="text-base text-slate-800">${w.remark}</p></div>` : ''}
                </div>
            `;
            document.getElementById('detailModal').classList.remove('hidden');
        }
    })
    .catch(err => console.error('Load detail error:', err));
}

function approveWithdrawal(withdrawalId) {
    currentWithdrawalId = withdrawalId;
    document.getElementById('approveModal').classList.remove('hidden');
}

function submitApprove() {
    const result = document.getElementById('approveResult').value;
    const remark = document.getElementById('approveRemark').value;

    fetch(`{{ route('admin_api_withdrawals_approve') }}`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: JSON.stringify({ withdrawal_id: currentWithdrawalId, result: result, remark: remark })
    })
    .then(res => res.json())
    .then(data => {
        if (data.success || data.code === 200) {
            alert('审核成功');
            closeApproveModal();
            loadWithdrawals(currentPage);
            loadWithdrawalsStats();
        } else {
            alert(data.message || '审核失败');
        }
    })
    .catch(err => {
        console.error('Approve error:', err);
        alert('网络错误，请稍后重试');
    });
}

function toggleSelectAll() {
    const checked = document.getElementById('selectAll').checked;
    document.querySelectorAll('.withdraw-checkbox').forEach(cb => {
        cb.checked = checked;
    });
    updateSelection();
}

function updateSelection() {
    selectedWithdrawals = Array.from(document.querySelectorAll('.withdraw-checkbox:checked')).map(cb => cb.value);
}

function showBatchApproveModal() {
    if (selectedWithdrawals.length === 0) {
        alert('请先选择要批量审核的记录');
        return;
    }
    alert(`批量审核功能开发中，已选择 ${selectedWithdrawals.length} 条记录`);
}

function closeDetailModal() {
    document.getElementById('detailModal').classList.add('hidden');
}

function closeApproveModal() {
    document.getElementById('approveModal').classList.add('hidden');
    currentWithdrawalId = null;
}

function exportWithdrawals() {
    window.location.href = '{{ route("admin_api_withdrawals_export") }}';
}

function formatNumber(num) {
    return new Intl.NumberFormat('en-US').format(num);
}

function showError(message) {
    document.getElementById('withdrawalsTableBody').innerHTML = `
        <tr><td colspan="9" class="px-6 py-12 text-center text-red-500">
            <i class="fas fa-exclamation-circle text-3xl mb-3"></i><p>${message}</p>
        </td></tr>
    `;
}
</script>
@endsection
