@extends('admin-tailwind.layouts.app')

@section('title', '待处理出金 - 管理后台')

@section('content')
<!-- Page Header -->
<div class="flex items-center justify-between mb-8">
    <div>
        <div class="flex items-center gap-3 mb-2">
            <a href="{{ route('admin_tailwind_page_withdrawals') }}" class="text-slate-600 hover:text-slate-800">
                <i class="fas fa-arrow-left"></i> 返回列表
            </a>
        </div>
        <h1 class="text-3xl font-bold text-slate-800">待处理出金</h1>
        <p class="text-slate-600 mt-2">审核待处理的出金申请</p>
    </div>
    <div class="flex gap-3">
        <button onclick="batchApprove()" class="px-6 py-3 bg-gradient-to-r from-green-500 to-green-600 text-white font-semibold rounded-lg hover:shadow-lg transition">
            <i class="fas fa-check-double mr-2"></i>批量通过
        </button>
        <button onclick="batchReject()" class="px-6 py-3 bg-gradient-to-r from-red-500 to-red-600 text-white font-semibold rounded-lg hover:shadow-lg transition">
            <i class="fas fa-times-circle mr-2"></i>批量拒绝
        </button>
    </div>
</div>

<!-- Stats Cards -->
<div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-6">
    <div class="bg-white rounded-xl shadow-lg p-6 border-l-4 border-yellow-500">
        <p class="text-sm text-slate-600 mb-2">待处理数量</p>
        <p class="text-3xl font-bold text-slate-800" id="pendingCount">0</p>
    </div>
    <div class="bg-white rounded-xl shadow-lg p-6 border-l-4 border-orange-500">
        <p class="text-sm text-slate-600 mb-2">待处理金额</p>
        <p class="text-3xl font-bold text-slate-800">$<span id="pendingAmount">0</span></p>
    </div>
    <div class="bg-white rounded-xl shadow-lg p-6 border-l-4 border-blue-500">
        <p class="text-sm text-slate-600 mb-2">今日新增</p>
        <p class="text-3xl font-bold text-slate-800" id="todayNew">0</p>
    </div>
    <div class="bg-white rounded-xl shadow-lg p-6 border-l-4 border-purple-500">
        <p class="text-sm text-slate-600 mb-2">超时单数</p>
        <p class="text-3xl font-bold text-slate-800 text-red-600" id="overdueCount">0</p>
    </div>
</div>

<!-- Filter Bar -->
<div class="bg-white rounded-xl shadow-lg p-6 mb-6">
    <div class="grid grid-cols-1 md:grid-cols-5 gap-4">
        <div>
            <label class="block text-sm font-semibold text-slate-700 mb-2">搜索</label>
            <input type="text" id="searchKeyword" placeholder="订单号/用户名" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
        </div>
        <div>
            <label class="block text-sm font-semibold text-slate-700 mb-2">金额范围</label>
            <select id="filterAmountRange" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                <option value="">全部金额</option>
                <option value="0-1000">$0 - $1,000</option>
                <option value="1000-5000">$1,000 - $5,000</option>
                <option value="5000-10000">$5,000 - $10,000</option>
                <option value="10000+">$10,000+</option>
            </select>
        </div>
        <div>
            <label class="block text-sm font-semibold text-slate-700 mb-2">排序方式</label>
            <select id="sortBy" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                <option value="created_at_desc">时间倒序</option>
                <option value="created_at_asc">时间正序</option>
                <option value="amount_desc">金额从高到低</option>
                <option value="amount_asc">金额从低到高</option>
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
        <div class="flex items-end">
            <button onclick="searchPending()" class="w-full px-6 py-2 bg-gradient-to-r from-blue-500 to-indigo-600 text-white font-semibold rounded-lg hover:shadow-lg transition">
                <i class="fas fa-search mr-2"></i>搜索
            </button>
        </div>
    </div>
</div>

<!-- Pending Withdrawals Table -->
<div class="bg-white rounded-xl shadow-lg overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full">
            <thead class="bg-slate-50 border-b border-slate-200">
                <tr>
                    <th class="px-6 py-4 text-left">
                        <input type="checkbox" id="selectAll" onchange="toggleSelectAll()" class="w-4 h-4 text-blue-600 border-slate-300 rounded focus:ring-2 focus:ring-blue-500">
                    </th>
                    <th class="px-6 py-4 text-left text-xs font-semibold text-slate-600 uppercase tracking-wider">订单号</th>
                    <th class="px-6 py-4 text-left text-xs font-semibold text-slate-600 uppercase tracking-wider">用户信息</th>
                    <th class="px-6 py-4 text-left text-xs font-semibold text-slate-600 uppercase tracking-wider">申请金额</th>
                    <th class="px-6 py-4 text-left text-xs font-semibold text-slate-600 uppercase tracking-wider">手续费</th>
                    <th class="px-6 py-4 text-left text-xs font-semibold text-slate-600 uppercase tracking-wider">实际到账</th>
                    <th class="px-6 py-4 text-left text-xs font-semibold text-slate-600 uppercase tracking-wider">账户余额</th>
                    <th class="px-6 py-4 text-left text-xs font-semibold text-slate-600 uppercase tracking-wider">申请时间</th>
                    <th class="px-6 py-4 text-left text-xs font-semibold text-slate-600 uppercase tracking-wider">等待时长</th>
                    <th class="px-6 py-4 text-left text-xs font-semibold text-slate-600 uppercase tracking-wider">操作</th>
                </tr>
            </thead>
            <tbody id="pendingTableBody" class="divide-y divide-slate-200">
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
        <div class="sticky top-0 bg-gradient-to-r from-yellow-500 to-orange-500 px-6 py-4 flex items-center justify-between">
            <h3 class="text-xl font-bold text-white">出金详情与审核</h3>
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
let selectedWithdrawals = [];

document.addEventListener('DOMContentLoaded', function() {
    loadStats();
    loadPendingWithdrawals();
});

function loadStats() {
    fetch('{{ route("admin_api_withdrawals_pending_stats") }}', {
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(res => res.json())
    .then(data => {
        if (data.success || data.code === 200) {
            document.getElementById('pendingCount').textContent = formatNumber(data.pendingCount || 0);
            document.getElementById('pendingAmount').textContent = formatNumber(data.pendingAmount || 0);
            document.getElementById('todayNew').textContent = formatNumber(data.todayNew || 0);
            document.getElementById('overdueCount').textContent = formatNumber(data.overdueCount || 0);
        }
    })
    .catch(err => console.error('Load stats error:', err));
}

function loadPendingWithdrawals(page = 1) {
    currentPage = page;
    const keyword = document.getElementById('searchKeyword').value;
    const amountRange = document.getElementById('filterAmountRange').value;
    const sortBy = document.getElementById('sortBy').value;
    const currency = document.getElementById('filterCurrency').value;

    const params = new URLSearchParams({
        page: page,
        keyword: keyword,
        amount_range: amountRange,
        sort_by: sortBy,
        currency: currency
    });

    fetch(`{{ route('admin_api_withdrawals_pending_list') }}?${params}`, {
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
    const tbody = document.getElementById('pendingTableBody');

    if (withdrawals.length === 0) {
        tbody.innerHTML = `
            <tr>
                <td colspan="10" class="px-6 py-12 text-center text-slate-500">
                    <i class="fas fa-inbox text-3xl mb-3"></i>
                    <p>暂无待处理出金</p>
                </td>
            </tr>
        `;
        return;
    }

    tbody.innerHTML = withdrawals.map(w => {
        const waitTime = calculateWaitTime(w.created_at);
        const isOverdue = waitTime.hours >= 2;

        return `
            <tr class="hover:bg-slate-50 transition ${isOverdue ? 'bg-red-50' : ''}">
                <td class="px-6 py-4">
                    <input type="checkbox" class="withdraw-checkbox w-4 h-4 text-blue-600 border-slate-300 rounded" value="${w.id}" onchange="updateSelection()">
                </td>
                <td class="px-6 py-4">
                    <p class="text-sm font-mono text-slate-800">${w.order_no || '-'}</p>
                    ${isOverdue ? '<span class="text-xs text-red-600 font-semibold">超时</span>' : ''}
                </td>
                <td class="px-6 py-4">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 bg-gradient-to-br from-yellow-500 to-orange-600 rounded-full flex items-center justify-center text-white font-bold">
                            ${(w.username || 'U').charAt(0).toUpperCase()}
                        </div>
                        <div>
                            <p class="text-sm font-semibold text-slate-800">${w.username || 'N/A'}</p>
                            <p class="text-xs text-slate-500">ID: ${w.user_id || '-'}</p>
                        </div>
                    </div>
                </td>
                <td class="px-6 py-4">
                    <p class="text-lg font-bold text-orange-600">$${formatNumber(w.amount || 0)}</p>
                </td>
                <td class="px-6 py-4 text-sm text-slate-600">$${formatNumber(w.fee || 0)}</td>
                <td class="px-6 py-4">
                    <p class="text-sm font-semibold text-green-600">$${formatNumber((w.amount || 0) - (w.fee || 0))}</p>
                </td>
                <td class="px-6 py-4">
                    <p class="text-sm font-semibold ${(w.balance || 0) >= (w.amount || 0) ? 'text-green-600' : 'text-red-600'}">
                        $${formatNumber(w.balance || 0)}
                    </p>
                </td>
                <td class="px-6 py-4 text-sm text-slate-600">${w.created_at || '-'}</td>
                <td class="px-6 py-4">
                    <span class="text-sm ${isOverdue ? 'text-red-600 font-semibold' : 'text-slate-600'}">
                        ${waitTime.text}
                    </span>
                </td>
                <td class="px-6 py-4">
                    <div class="flex gap-2">
                        <button onclick="viewDetail(${w.id})" class="px-3 py-1 bg-blue-100 text-blue-700 text-xs font-semibold rounded hover:bg-blue-200 transition">
                            <i class="fas fa-eye mr-1"></i>详情
                        </button>
                        <button onclick="quickApprove(${w.id})" class="px-3 py-1 bg-green-100 text-green-700 text-xs font-semibold rounded hover:bg-green-200 transition">
                            <i class="fas fa-check mr-1"></i>通过
                        </button>
                        <button onclick="quickReject(${w.id})" class="px-3 py-1 bg-red-100 text-red-700 text-xs font-semibold rounded hover:bg-red-200 transition">
                            <i class="fas fa-times mr-1"></i>拒绝
                        </button>
                    </div>
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
        html += `<button onclick="loadPendingWithdrawals(${currentPage - 1})" class="px-3 py-1 border border-slate-300 rounded hover:bg-slate-50">上一页</button>`;
    }
    for (let i = Math.max(1, currentPage - 2); i <= Math.min(totalPages, currentPage + 2); i++) {
        html += `<button onclick="loadPendingWithdrawals(${i})" class="px-3 py-1 border ${i === currentPage ? 'bg-blue-500 text-white' : 'border-slate-300 hover:bg-slate-50'} rounded">${i}</button>`;
    }
    if (currentPage < totalPages) {
        html += `<button onclick="loadPendingWithdrawals(${currentPage + 1})" class="px-3 py-1 border border-slate-300 rounded hover:bg-slate-50">下一页</button>`;
    }
    paginationDiv.innerHTML = html;
}

function calculateWaitTime(createdAt) {
    const created = new Date(createdAt);
    const now = new Date();
    const diff = now - created;
    const hours = Math.floor(diff / (1000 * 60 * 60));
    const minutes = Math.floor((diff % (1000 * 60 * 60)) / (1000 * 60));

    if (hours > 0) {
        return { hours, minutes, text: `${hours}小时${minutes}分钟` };
    } else {
        return { hours: 0, minutes, text: `${minutes}分钟` };
    }
}

function searchPending() {
    loadPendingWithdrawals(1);
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
                    <div class="grid grid-cols-2 gap-4">
                        <div><p class="text-sm text-slate-600 mb-1">订单号</p><p class="text-base font-mono text-slate-800">${w.order_no || '-'}</p></div>
                        <div><p class="text-sm text-slate-600 mb-1">用户</p><p class="text-base font-semibold text-slate-800">${w.username || 'N/A'}</p></div>
                        <div><p class="text-sm text-slate-600 mb-1">申请金额</p><p class="text-lg font-bold text-orange-600">$${formatNumber(w.amount || 0)}</p></div>
                        <div><p class="text-sm text-slate-600 mb-1">手续费</p><p class="text-base text-slate-800">$${formatNumber(w.fee || 0)}</p></div>
                        <div><p class="text-sm text-slate-600 mb-1">实际到账</p><p class="text-lg font-bold text-green-600">$${formatNumber((w.amount || 0) - (w.fee || 0))}</p></div>
                        <div><p class="text-sm text-slate-600 mb-1">账户余额</p><p class="text-base font-semibold text-slate-800">$${formatNumber(w.balance || 0)}</p></div>
                        <div><p class="text-sm text-slate-600 mb-1">银行卡号</p><p class="text-base font-mono text-slate-800">${w.bank_card || '-'}</p></div>
                        <div><p class="text-sm text-slate-600 mb-1">开户行</p><p class="text-base text-slate-800">${w.bank_name || '-'}</p></div>
                        <div><p class="text-sm text-slate-600 mb-1">开户人</p><p class="text-base text-slate-800">${w.bank_account_name || '-'}</p></div>
                        <div><p class="text-sm text-slate-600 mb-1">申请时间</p><p class="text-base text-slate-800">${w.created_at || '-'}</p></div>
                    </div>

                    <div class="border-t border-slate-200 pt-4">
                        <p class="text-sm font-semibold text-slate-700 mb-3">审核操作</p>
                        <div class="space-y-3">
                            <div>
                                <label class="block text-sm font-semibold text-slate-700 mb-2">审核结果</label>
                                <select id="approveAction" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                                    <option value="approved">通过并处理</option>
                                    <option value="rejected">拒绝</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-sm font-semibold text-slate-700 mb-2">备注说明</label>
                                <textarea id="approveRemark" rows="3" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" placeholder="请输入审核备注"></textarea>
                            </div>
                            <div class="flex gap-3">
                                <button onclick="submitApproval(${w.id})" class="flex-1 px-4 py-3 bg-gradient-to-r from-green-500 to-green-600 text-white font-semibold rounded-lg hover:shadow-lg transition">
                                    <i class="fas fa-check mr-2"></i>提交审核
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            `;
            document.getElementById('detailModal').classList.remove('hidden');
        }
    })
    .catch(err => console.error('Load detail error:', err));
}

function submitApproval(id) {
    const action = document.getElementById('approveAction').value;
    const remark = document.getElementById('approveRemark').value;

    fetch(`{{ route('admin_api_withdrawals_approve') }}`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: JSON.stringify({ withdrawal_id: id, result: action, remark: remark })
    })
    .then(res => res.json())
    .then(data => {
        if (data.success || data.code === 200) {
            alert('审核成功');
            closeDetailModal();
            loadPendingWithdrawals(currentPage);
            loadStats();
        } else {
            alert(data.message || '审核失败');
        }
    })
    .catch(err => {
        console.error('Approve error:', err);
        alert('网络错误，请稍后重试');
    });
}

function quickApprove(id) {
    if (!confirm('确定要通过此出金申请吗？')) return;

    fetch(`{{ route('admin_api_withdrawals_approve') }}`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: JSON.stringify({ withdrawal_id: id, result: 'approved', remark: '快速审核通过' })
    })
    .then(res => res.json())
    .then(data => {
        if (data.success || data.code === 200) {
            alert('审核通过');
            loadPendingWithdrawals(currentPage);
            loadStats();
        } else {
            alert(data.message || '操作失败');
        }
    })
    .catch(err => {
        console.error('Approve error:', err);
        alert('网络错误，请稍后重试');
    });
}

function quickReject(id) {
    const reason = prompt('请输入拒绝原因：');
    if (!reason) return;

    fetch(`{{ route('admin_api_withdrawals_approve') }}`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: JSON.stringify({ withdrawal_id: id, result: 'rejected', remark: reason })
    })
    .then(res => res.json())
    .then(data => {
        if (data.success || data.code === 200) {
            alert('已拒绝');
            loadPendingWithdrawals(currentPage);
            loadStats();
        } else {
            alert(data.message || '操作失败');
        }
    })
    .catch(err => {
        console.error('Reject error:', err);
        alert('网络错误，请稍后重试');
    });
}

function batchApprove() {
    if (selectedWithdrawals.length === 0) {
        alert('请先选择要审核的记录');
        return;
    }

    if (!confirm(`确定要批量通过选中的 ${selectedWithdrawals.length} 条记录吗？`)) return;

    fetch(`{{ route('admin_api_withdrawals_batch_approve') }}`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: JSON.stringify({ withdrawal_ids: selectedWithdrawals, result: 'approved' })
    })
    .then(res => res.json())
    .then(data => {
        if (data.success || data.code === 200) {
            alert('批量审核成功');
            loadPendingWithdrawals(currentPage);
            loadStats();
            selectedWithdrawals = [];
            document.getElementById('selectAll').checked = false;
        } else {
            alert(data.message || '批量审核失败');
        }
    })
    .catch(err => {
        console.error('Batch approve error:', err);
        alert('网络错误，请稍后重试');
    });
}

function batchReject() {
    if (selectedWithdrawals.length === 0) {
        alert('请先选择要拒绝的记录');
        return;
    }

    const reason = prompt(`确定要批量拒绝选中的 ${selectedWithdrawals.length} 条记录吗？请输入拒绝原因：`);
    if (!reason) return;

    fetch(`{{ route('admin_api_withdrawals_batch_approve') }}`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: JSON.stringify({ withdrawal_ids: selectedWithdrawals, result: 'rejected', remark: reason })
    })
    .then(res => res.json())
    .then(data => {
        if (data.success || data.code === 200) {
            alert('批量拒绝成功');
            loadPendingWithdrawals(currentPage);
            loadStats();
            selectedWithdrawals = [];
            document.getElementById('selectAll').checked = false;
        } else {
            alert(data.message || '批量拒绝失败');
        }
    })
    .catch(err => {
        console.error('Batch reject error:', err);
        alert('网络错误，请稍后重试');
    });
}

function closeDetailModal() {
    document.getElementById('detailModal').classList.add('hidden');
}

function formatNumber(num) {
    return new Intl.NumberFormat('en-US').format(num);
}

function showError(message) {
    document.getElementById('pendingTableBody').innerHTML = `
        <tr><td colspan="10" class="px-6 py-12 text-center text-red-500">
            <i class="fas fa-exclamation-circle text-3xl mb-3"></i><p>${message}</p>
        </td></tr>
    `;
}
</script>
@endsection
