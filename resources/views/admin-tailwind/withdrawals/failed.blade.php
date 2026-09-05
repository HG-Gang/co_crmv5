@extends('admin-tailwind.layouts.app')

@section('title', '失败出金 - 管理后台')

@section('content')
<!-- Page Header -->
<div class="flex items-center justify-between mb-8">
    <div>
        <div class="flex items-center gap-3 mb-2">
            <a href="{{ route('admin_tailwind_page_withdrawals') }}" class="text-slate-600 hover:text-slate-800">
                <i class="fas fa-arrow-left"></i> 返回列表
            </a>
        </div>
        <h1 class="text-3xl font-bold text-slate-800">失败出金</h1>
        <p class="text-slate-600 mt-2">查看和处理失败的出金记录</p>
    </div>
    <div class="flex gap-3">
        <button onclick="exportFailed()" class="px-6 py-3 bg-gradient-to-r from-green-500 to-green-600 text-white font-semibold rounded-lg hover:shadow-lg transition">
            <i class="fas fa-download mr-2"></i>导出数据
        </button>
    </div>
</div>

<!-- Stats Cards -->
<div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-6">
    <div class="bg-white rounded-xl shadow-lg p-6 border-l-4 border-red-500">
        <p class="text-sm text-slate-600 mb-2">今日失败</p>
        <p class="text-3xl font-bold text-slate-800 text-red-600" id="todayFailed">0</p>
    </div>
    <div class="bg-white rounded-xl shadow-lg p-6 border-l-4 border-rose-500">
        <p class="text-sm text-slate-600 mb-2">本月失败</p>
        <p class="text-3xl font-bold text-slate-800 text-red-600" id="monthFailed">0</p>
    </div>
    <div class="bg-white rounded-xl shadow-lg p-6 border-l-4 border-orange-500">
        <p class="text-sm text-slate-600 mb-2">失败总金额</p>
        <p class="text-3xl font-bold text-slate-800">$<span id="failedAmount">0</span></p>
    </div>
    <div class="bg-white rounded-xl shadow-lg p-6 border-l-4 border-amber-500">
        <p class="text-sm text-slate-600 mb-2">待重新处理</p>
        <p class="text-3xl font-bold text-slate-800 text-amber-600" id="pendingRetry">0</p>
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
            <label class="block text-sm font-semibold text-slate-700 mb-2">失败原因</label>
            <select id="filterReason" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                <option value="">全部原因</option>
                <option value="insufficient_balance">余额不足</option>
                <option value="bank_error">银行错误</option>
                <option value="info_mismatch">信息不符</option>
                <option value="risk_control">风控拒绝</option>
                <option value="other">其他</option>
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
            <button onclick="searchFailed()" class="w-full px-6 py-2 bg-gradient-to-r from-blue-500 to-indigo-600 text-white font-semibold rounded-lg hover:shadow-lg transition">
                <i class="fas fa-search mr-2"></i>搜索
            </button>
        </div>
    </div>
</div>

<!-- Failed Withdrawals Table -->
<div class="bg-white rounded-xl shadow-lg overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full">
            <thead class="bg-slate-50 border-b border-slate-200">
                <tr>
                    <th class="px-6 py-4 text-left text-xs font-semibold text-slate-600 uppercase tracking-wider">订单号</th>
                    <th class="px-6 py-4 text-left text-xs font-semibold text-slate-600 uppercase tracking-wider">用户信息</th>
                    <th class="px-6 py-4 text-left text-xs font-semibold text-slate-600 uppercase tracking-wider">申请金额</th>
                    <th class="px-6 py-4 text-left text-xs font-semibold text-slate-600 uppercase tracking-wider">失败原因</th>
                    <th class="px-6 py-4 text-left text-xs font-semibold text-slate-600 uppercase tracking-wider">处理人</th>
                    <th class="px-6 py-4 text-left text-xs font-semibold text-slate-600 uppercase tracking-wider">申请时间</th>
                    <th class="px-6 py-4 text-left text-xs font-semibold text-slate-600 uppercase tracking-wider">失败时间</th>
                    <th class="px-6 py-4 text-left text-xs font-semibold text-slate-600 uppercase tracking-wider">状态</th>
                    <th class="px-6 py-4 text-left text-xs font-semibold text-slate-600 uppercase tracking-wider">操作</th>
                </tr>
            </thead>
            <tbody id="failedTableBody" class="divide-y divide-slate-200">
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
        <div class="sticky top-0 bg-gradient-to-r from-red-500 to-rose-600 px-6 py-4 flex items-center justify-between">
            <h3 class="text-xl font-bold text-white">出金详情</h3>
            <button onclick="closeDetailModal()" class="text-white hover:text-slate-200">
                <i class="fas fa-times text-xl"></i>
            </button>
        </div>
        <div id="modalContent" class="p-6"></div>
    </div>
</div>

<!-- Retry Modal -->
<div id="retryModal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl max-w-md w-full">
        <div class="bg-gradient-to-r from-amber-500 to-orange-600 px-6 py-4">
            <h3 class="text-xl font-bold text-white">重新处理</h3>
        </div>
        <div class="p-6">
            <div class="mb-4">
                <p class="text-sm text-slate-700 mb-3">确认要重新处理此出金申请吗？系统将重新审核并尝试处理。</p>
            </div>
            <div class="mb-4">
                <label class="block text-sm font-semibold text-slate-700 mb-2">备注说明</label>
                <textarea id="retryRemark" rows="3" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" placeholder="请输入重新处理的原因或说明"></textarea>
            </div>
            <div class="flex gap-3">
                <button onclick="closeRetryModal()" class="flex-1 px-4 py-2 border border-slate-300 text-slate-700 font-semibold rounded-lg hover:bg-slate-50 transition">
                    取消
                </button>
                <button onclick="submitRetry()" class="flex-1 px-4 py-2 bg-gradient-to-r from-amber-500 to-orange-600 text-white font-semibold rounded-lg hover:shadow-lg transition">
                    确认重新处理
                </button>
            </div>
        </div>
    </div>
</div>

<script>
let currentPage = 1;
let totalPages = 1;
let currentWithdrawalId = null;

document.addEventListener('DOMContentLoaded', function() {
    loadStats();
    loadFailedWithdrawals();
});

function loadStats() {
    fetch('{{ route("admin_api_withdrawals_failed_stats") }}', {
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(res => res.json())
    .then(data => {
        if (data.success || data.code === 200) {
            document.getElementById('todayFailed').textContent = formatNumber(data.todayFailed || 0);
            document.getElementById('monthFailed').textContent = formatNumber(data.monthFailed || 0);
            document.getElementById('failedAmount').textContent = formatNumber(data.failedAmount || 0);
            document.getElementById('pendingRetry').textContent = formatNumber(data.pendingRetry || 0);
        }
    })
    .catch(err => console.error('Load stats error:', err));
}

function loadFailedWithdrawals(page = 1) {
    currentPage = page;
    const keyword = document.getElementById('searchKeyword').value;
    const reason = document.getElementById('filterReason').value;
    const currency = document.getElementById('filterCurrency').value;
    const startDate = document.getElementById('filterStartDate').value;
    const endDate = document.getElementById('filterEndDate').value;

    const params = new URLSearchParams({
        page: page,
        keyword: keyword,
        reason: reason,
        currency: currency,
        start_date: startDate,
        end_date: endDate
    });

    fetch(`{{ route('admin_api_withdrawals_failed_list') }}?${params}`, {
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
    const tbody = document.getElementById('failedTableBody');

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
                    <div class="w-10 h-10 bg-gradient-to-br from-red-500 to-rose-600 rounded-full flex items-center justify-center text-white font-bold">
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
            <td class="px-6 py-4">
                <div class="max-w-xs">
                    ${getReasonBadge(w.failure_reason)}
                    <p class="text-xs text-slate-500 mt-1">${w.failure_detail || ''}</p>
                </div>
            </td>
            <td class="px-6 py-4">
                <div class="flex items-center gap-2">
                    <div class="w-8 h-8 bg-gradient-to-br from-purple-500 to-indigo-600 rounded-full flex items-center justify-center text-white text-xs font-bold">
                        ${(w.processor_name || 'A').charAt(0).toUpperCase()}
                    </div>
                    <span class="text-sm text-slate-800">${w.processor_name || '-'}</span>
                </div>
            </td>
            <td class="px-6 py-4 text-sm text-slate-600">${w.created_at || '-'}</td>
            <td class="px-6 py-4 text-sm text-slate-600">${w.failed_at || '-'}</td>
            <td class="px-6 py-4">
                ${w.can_retry
                    ? '<span class="px-3 py-1 bg-amber-100 text-amber-700 text-xs font-semibold rounded-full">可重试</span>'
                    : '<span class="px-3 py-1 bg-red-100 text-red-700 text-xs font-semibold rounded-full">已失败</span>'
                }
            </td>
            <td class="px-6 py-4">
                <div class="flex gap-2">
                    <button onclick="viewDetail(${w.id})" class="px-3 py-1 bg-blue-100 text-blue-700 text-xs font-semibold rounded hover:bg-blue-200 transition">
                        <i class="fas fa-eye mr-1"></i>详情
                    </button>
                    ${w.can_retry
                        ? `<button onclick="retryWithdrawal(${w.id})" class="px-3 py-1 bg-amber-100 text-amber-700 text-xs font-semibold rounded hover:bg-amber-200 transition">
                            <i class="fas fa-redo mr-1"></i>重试
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
        html += `<button onclick="loadFailedWithdrawals(${currentPage - 1})" class="px-3 py-1 border border-slate-300 rounded hover:bg-slate-50">上一页</button>`;
    }
    for (let i = Math.max(1, currentPage - 2); i <= Math.min(totalPages, currentPage + 2); i++) {
        html += `<button onclick="loadFailedWithdrawals(${i})" class="px-3 py-1 border ${i === currentPage ? 'bg-blue-500 text-white' : 'border-slate-300 hover:bg-slate-50'} rounded">${i}</button>`;
    }
    if (currentPage < totalPages) {
        html += `<button onclick="loadFailedWithdrawals(${currentPage + 1})" class="px-3 py-1 border border-slate-300 rounded hover:bg-slate-50">下一页</button>`;
    }
    paginationDiv.innerHTML = html;
}

function getReasonBadge(reason) {
    const badges = {
        'insufficient_balance': '<span class="px-2 py-1 bg-orange-100 text-orange-700 text-xs font-semibold rounded-full">余额不足</span>',
        'bank_error': '<span class="px-2 py-1 bg-red-100 text-red-700 text-xs font-semibold rounded-full">银行错误</span>',
        'info_mismatch': '<span class="px-2 py-1 bg-yellow-100 text-yellow-700 text-xs font-semibold rounded-full">信息不符</span>',
        'risk_control': '<span class="px-2 py-1 bg-purple-100 text-purple-700 text-xs font-semibold rounded-full">风控拒绝</span>',
        'other': '<span class="px-2 py-1 bg-slate-100 text-slate-700 text-xs font-semibold rounded-full">其他原因</span>'
    };
    return badges[reason] || badges['other'];
}

function searchFailed() {
    loadFailedWithdrawals(1);
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
                        <div class="w-16 h-16 bg-gradient-to-br from-red-500 to-rose-600 rounded-full flex items-center justify-center">
                            <i class="fas fa-times text-3xl text-white"></i>
                        </div>
                    </div>

                    <div class="text-center mb-6">
                        <p class="text-2xl font-bold text-slate-800 mb-2">出金失败</p>
                        <p class="text-lg text-red-600 font-bold">$${formatNumber(w.amount || 0)}</p>
                    </div>

                    <div class="bg-red-50 border border-red-200 rounded-lg p-4 mb-4">
                        <p class="text-sm font-semibold text-red-800 mb-2">失败原因</p>
                        ${getReasonBadge(w.failure_reason)}
                        ${w.failure_detail ? `<p class="text-sm text-red-700 mt-2">${w.failure_detail}</p>` : ''}
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
                        <div><p class="text-sm text-slate-600 mb-1">银行卡号</p><p class="text-base font-mono text-slate-800">${w.bank_card || '-'}</p></div>
                        <div><p class="text-sm text-slate-600 mb-1">开户行</p><p class="text-base text-slate-800">${w.bank_name || '-'}</p></div>
                        <div><p class="text-sm text-slate-600 mb-1">开户人</p><p class="text-base text-slate-800">${w.bank_account_name || '-'}</p></div>
                        <div><p class="text-sm text-slate-600 mb-1">处理人</p><p class="text-base text-slate-800">${w.processor_name || '-'}</p></div>
                        <div><p class="text-sm text-slate-600 mb-1">申请时间</p><p class="text-base text-slate-800">${w.created_at || '-'}</p></div>
                        <div><p class="text-sm text-slate-600 mb-1">失败时间</p><p class="text-base text-slate-800">${w.failed_at || '-'}</p></div>
                    </div>

                    ${w.remark ? `
                        <div class="border-t border-slate-200 pt-4">
                            <p class="text-sm text-slate-600 mb-2">备注说明</p>
                            <p class="text-base text-slate-800 bg-slate-50 rounded-lg p-3">${w.remark}</p>
                        </div>
                    ` : ''}

                    ${w.can_retry ? `
                        <div class="border-t border-slate-200 pt-4">
                            <button onclick="retryWithdrawal(${w.id}); closeDetailModal();" class="w-full px-4 py-3 bg-gradient-to-r from-amber-500 to-orange-600 text-white font-semibold rounded-lg hover:shadow-lg transition">
                                <i class="fas fa-redo mr-2"></i>重新处理此出金
                            </button>
                        </div>
                    ` : ''}
                </div>
            `;
            document.getElementById('detailModal').classList.remove('hidden');
        }
    })
    .catch(err => console.error('Load detail error:', err));
}

function retryWithdrawal(id) {
    currentWithdrawalId = id;
    document.getElementById('retryModal').classList.remove('hidden');
}

function submitRetry() {
    const remark = document.getElementById('retryRemark').value;

    fetch(`{{ route('admin_api_withdrawals_retry') }}`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: JSON.stringify({ withdrawal_id: currentWithdrawalId, remark: remark })
    })
    .then(res => res.json())
    .then(data => {
        if (data.success || data.code === 200) {
            alert('已提交重新处理请求');
            closeRetryModal();
            loadFailedWithdrawals(currentPage);
            loadStats();
        } else {
            alert(data.message || '操作失败');
        }
    })
    .catch(err => {
        console.error('Retry error:', err);
        alert('网络错误，请稍后重试');
    });
}

function closeDetailModal() {
    document.getElementById('detailModal').classList.add('hidden');
}

function closeRetryModal() {
    document.getElementById('retryModal').classList.add('hidden');
    currentWithdrawalId = null;
    document.getElementById('retryRemark').value = '';
}

function exportFailed() {
    const startDate = document.getElementById('filterStartDate').value;
    const endDate = document.getElementById('filterEndDate').value;

    let url = '{{ route("admin_api_withdrawals_failed_export") }}';
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
    document.getElementById('failedTableBody').innerHTML = `
        <tr><td colspan="9" class="px-6 py-12 text-center text-red-500">
            <i class="fas fa-exclamation-circle text-3xl mb-3"></i><p>${message}</p>
        </td></tr>
    `;
}
</script>
@endsection
