@extends('admin-tailwind.layouts.app')

@section('title', '未入金流水 - 管理后台')

@section('content')
<!-- Page Header -->
<div class="flex items-center justify-between mb-8">
    <div>
        <h1 class="text-3xl font-bold text-slate-800">未入金流水</h1>
        <p class="text-slate-600 mt-2">查看用户提交但尚未到账的入金记录</p>
    </div>
    <div class="flex gap-3">
        <button onclick="exportUndeposits()" class="px-6 py-3 bg-gradient-to-r from-green-500 to-green-600 text-white font-semibold rounded-lg hover:shadow-lg transition">
            <i class="fas fa-download mr-2"></i>导出数据
        </button>
    </div>
</div>

<!-- Stats Cards -->
<div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-6">
    <div class="bg-white rounded-xl shadow-lg p-6 border-l-4 border-yellow-500">
        <p class="text-sm text-slate-600 mb-2">待确认笔数</p>
        <p class="text-3xl font-bold text-slate-800" id="pendingCount">0</p>
    </div>
    <div class="bg-white rounded-xl shadow-lg p-6 border-l-4 border-orange-500">
        <p class="text-sm text-slate-600 mb-2">待确认金额</p>
        <p class="text-3xl font-bold text-slate-800">$<span id="pendingAmount">0</span></p>
    </div>
    <div class="bg-white rounded-xl shadow-lg p-6 border-l-4 border-red-500">
        <p class="text-sm text-slate-600 mb-2">超时未入金</p>
        <p class="text-3xl font-bold text-slate-800" id="overdueCount">0</p>
    </div>
    <div class="bg-white rounded-xl shadow-lg p-6 border-l-4 border-blue-500">
        <p class="text-sm text-slate-600 mb-2">今日新增</p>
        <p class="text-3xl font-bold text-slate-800" id="todayNew">0</p>
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
            <label class="block text-sm font-semibold text-slate-700 mb-2">支付方式</label>
            <select id="filterMethod" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                <option value="">全部方式</option>
                <option value="bank_transfer">银行转账</option>
                <option value="alipay">支付宝</option>
                <option value="wechat">微信</option>
                <option value="usdt">USDT</option>
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
            <button onclick="searchUndeposits()" class="w-full px-6 py-2 bg-gradient-to-r from-blue-500 to-indigo-600 text-white font-semibold rounded-lg hover:shadow-lg transition">
                <i class="fas fa-search mr-2"></i>搜索
            </button>
        </div>
    </div>
</div>

<!-- Undeposit Table -->
<div class="bg-white rounded-xl shadow-lg overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full">
            <thead class="bg-slate-50 border-b border-slate-200">
                <tr>
                    <th class="px-6 py-4 text-left text-xs font-semibold text-slate-600 uppercase tracking-wider">订单号</th>
                    <th class="px-6 py-4 text-left text-xs font-semibold text-slate-600 uppercase tracking-wider">用户信息</th>
                    <th class="px-6 py-4 text-left text-xs font-semibold text-slate-600 uppercase tracking-wider">申请金额</th>
                    <th class="px-6 py-4 text-left text-xs font-semibold text-slate-600 uppercase tracking-wider">支付方式</th>
                    <th class="px-6 py-4 text-left text-xs font-semibold text-slate-600 uppercase tracking-wider">凭证</th>
                    <th class="px-6 py-4 text-left text-xs font-semibold text-slate-600 uppercase tracking-wider">等待时长</th>
                    <th class="px-6 py-4 text-left text-xs font-semibold text-slate-600 uppercase tracking-wider">提交时间</th>
                    <th class="px-6 py-4 text-left text-xs font-semibold text-slate-600 uppercase tracking-wider">操作</th>
                </tr>
            </thead>
            <tbody id="undepositTableBody" class="divide-y divide-slate-200">
                <tr>
                    <td colspan="8" class="px-6 py-12 text-center text-slate-500">
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
        <div class="sticky top-0 bg-gradient-to-r from-yellow-500 to-orange-600 px-6 py-4 flex items-center justify-between">
            <h3 class="text-xl font-bold text-white">未入金详情</h3>
            <button onclick="closeDetailModal()" class="text-white hover:text-slate-200">
                <i class="fas fa-times text-xl"></i>
            </button>
        </div>
        <div id="modalContent" class="p-6"></div>
    </div>
</div>

<!-- Confirm Modal -->
<div id="confirmModal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl max-w-md w-full">
        <div class="bg-gradient-to-r from-green-500 to-green-600 px-6 py-4">
            <h3 class="text-xl font-bold text-white">确认入金</h3>
        </div>
        <div class="p-6">
            <div class="mb-4">
                <label class="block text-sm font-semibold text-slate-700 mb-2">实际到账金额</label>
                <input type="number" id="actualAmount" step="0.01" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" placeholder="请输入实际到账金额">
            </div>
            <div class="mb-4">
                <label class="block text-sm font-semibold text-slate-700 mb-2">备注说明</label>
                <textarea id="confirmRemark" rows="3" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" placeholder="请输入备注信息"></textarea>
            </div>
            <div class="flex gap-3">
                <button onclick="closeConfirmModal()" class="flex-1 px-4 py-2 border border-slate-300 text-slate-700 font-semibold rounded-lg hover:bg-slate-50 transition">
                    取消
                </button>
                <button onclick="submitConfirm()" class="flex-1 px-4 py-2 bg-gradient-to-r from-green-500 to-green-600 text-white font-semibold rounded-lg hover:shadow-lg transition">
                    确认入金
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Reject Modal -->
<div id="rejectModal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl max-w-md w-full">
        <div class="bg-gradient-to-r from-red-500 to-red-600 px-6 py-4">
            <h3 class="text-xl font-bold text-white">拒绝入金</h3>
        </div>
        <div class="p-6">
            <div class="mb-4">
                <label class="block text-sm font-semibold text-slate-700 mb-2">拒绝原因</label>
                <select id="rejectReason" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 mb-2">
                    <option value="">请选择拒绝原因</option>
                    <option value="voucher_invalid">凭证无效</option>
                    <option value="amount_mismatch">金额不符</option>
                    <option value="duplicate">重复提交</option>
                    <option value="timeout">超时未到账</option>
                    <option value="other">其他原因</option>
                </select>
            </div>
            <div class="mb-4">
                <label class="block text-sm font-semibold text-slate-700 mb-2">备注说明</label>
                <textarea id="rejectRemark" rows="3" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" placeholder="请输入详细说明"></textarea>
            </div>
            <div class="flex gap-3">
                <button onclick="closeRejectModal()" class="flex-1 px-4 py-2 border border-slate-300 text-slate-700 font-semibold rounded-lg hover:bg-slate-50 transition">
                    取消
                </button>
                <button onclick="submitReject()" class="flex-1 px-4 py-2 bg-gradient-to-r from-red-500 to-red-600 text-white font-semibold rounded-lg hover:shadow-lg transition">
                    确认拒绝
                </button>
            </div>
        </div>
    </div>
</div>

<script>
let currentPage = 1;
let totalPages = 1;
let currentUndepositId = null;

document.addEventListener('DOMContentLoaded', function() {
    loadStats();
    loadUndeposits();
});

function loadStats() {
    fetch('{{ route("admin_api_undeposit_flows_stats") }}', {
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(res => res.json())
    .then(data => {
        if (data.success || data.code === 200) {
            document.getElementById('pendingCount').textContent = formatNumber(data.pendingCount || 0);
            document.getElementById('pendingAmount').textContent = formatNumber(data.pendingAmount || 0);
            document.getElementById('overdueCount').textContent = formatNumber(data.overdueCount || 0);
            document.getElementById('todayNew').textContent = formatNumber(data.todayNew || 0);
        }
    })
    .catch(err => console.error('Load stats error:', err));
}

function loadUndeposits(page = 1) {
    currentPage = page;
    const keyword = document.getElementById('searchKeyword').value;
    const currency = document.getElementById('filterCurrency').value;
    const method = document.getElementById('filterMethod').value;
    const startDate = document.getElementById('filterStartDate').value;
    const endDate = document.getElementById('filterEndDate').value;

    const params = new URLSearchParams({
        page: page,
        keyword: keyword,
        currency: currency,
        method: method,
        start_date: startDate,
        end_date: endDate
    });

    fetch(`{{ route('admin_api_undeposit_flows_list') }}?${params}`, {
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(res => res.json())
    .then(data => {
        if (data.success || data.code === 200) {
            renderTable(data.undeposits || []);
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

function renderTable(undeposits) {
    const tbody = document.getElementById('undepositTableBody');

    if (undeposits.length === 0) {
        tbody.innerHTML = `
            <tr>
                <td colspan="8" class="px-6 py-12 text-center text-slate-500">
                    <i class="fas fa-inbox text-3xl mb-3"></i>
                    <p>暂无未入金数据</p>
                </td>
            </tr>
        `;
        return;
    }

    tbody.innerHTML = undeposits.map(u => {
        const waitTime = calculateWaitTime(u.created_at);
        const isOverdue = waitTime.hours >= 24;

        return `
        <tr class="hover:bg-slate-50 transition ${isOverdue ? 'bg-red-50' : ''}">
            <td class="px-6 py-4">
                <p class="text-sm font-mono text-slate-800">${u.order_no || '-'}</p>
            </td>
            <td class="px-6 py-4">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 bg-gradient-to-br from-yellow-500 to-orange-600 rounded-full flex items-center justify-center text-white font-bold">
                        ${(u.username || 'U').charAt(0).toUpperCase()}
                    </div>
                    <div>
                        <p class="text-sm font-semibold text-slate-800">${u.username || 'N/A'}</p>
                        <p class="text-xs text-slate-500">ID: ${u.user_id || '-'}</p>
                    </div>
                </div>
            </td>
            <td class="px-6 py-4">
                <p class="text-base font-bold text-blue-600">$${formatNumber(u.amount || 0)}</p>
            </td>
            <td class="px-6 py-4">${getMethodBadge(u.payment_method)}</td>
            <td class="px-6 py-4">
                ${u.voucher_url
                    ? `<button onclick="viewVoucher('${u.voucher_url}')" class="text-blue-600 hover:text-blue-800"><i class="fas fa-image"></i> 查看</button>`
                    : '<span class="text-slate-400">无凭证</span>'
                }
            </td>
            <td class="px-6 py-4">
                <p class="text-sm font-semibold ${isOverdue ? 'text-red-600' : 'text-orange-600'}">
                    ${waitTime.text}
                    ${isOverdue ? '<br><span class="text-xs">(已超时)</span>' : ''}
                </p>
            </td>
            <td class="px-6 py-4 text-sm text-slate-600">${u.created_at || '-'}</td>
            <td class="px-6 py-4">
                <div class="flex gap-2">
                    <button onclick="confirmDeposit(${u.id}, ${u.amount})" class="px-3 py-1 bg-green-100 text-green-700 text-xs font-semibold rounded hover:bg-green-200 transition">
                        <i class="fas fa-check mr-1"></i>确认
                    </button>
                    <button onclick="rejectDeposit(${u.id})" class="px-3 py-1 bg-red-100 text-red-700 text-xs font-semibold rounded hover:bg-red-200 transition">
                        <i class="fas fa-times mr-1"></i>拒绝
                    </button>
                    <button onclick="viewDetail(${u.id})" class="px-3 py-1 bg-blue-100 text-blue-700 text-xs font-semibold rounded hover:bg-blue-200 transition">
                        <i class="fas fa-eye mr-1"></i>详情
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
        html += `<button onclick="loadUndeposits(${currentPage - 1})" class="px-3 py-1 border border-slate-300 rounded hover:bg-slate-50">上一页</button>`;
    }
    for (let i = Math.max(1, currentPage - 2); i <= Math.min(totalPages, currentPage + 2); i++) {
        html += `<button onclick="loadUndeposits(${i})" class="px-3 py-1 border ${i === currentPage ? 'bg-blue-500 text-white' : 'border-slate-300 hover:bg-slate-50'} rounded">${i}</button>`;
    }
    if (currentPage < totalPages) {
        html += `<button onclick="loadUndeposits(${currentPage + 1})" class="px-3 py-1 border border-slate-300 rounded hover:bg-slate-50">下一页</button>`;
    }
    paginationDiv.innerHTML = html;
}

function getMethodBadge(method) {
    const badges = {
        'bank_transfer': '<span class="px-2 py-1 bg-blue-100 text-blue-700 text-xs font-semibold rounded-full">银行转账</span>',
        'alipay': '<span class="px-2 py-1 bg-cyan-100 text-cyan-700 text-xs font-semibold rounded-full">支付宝</span>',
        'wechat': '<span class="px-2 py-1 bg-green-100 text-green-700 text-xs font-semibold rounded-full">微信支付</span>',
        'usdt': '<span class="px-2 py-1 bg-purple-100 text-purple-700 text-xs font-semibold rounded-full">USDT</span>'
    };
    return badges[method] || '<span class="px-2 py-1 bg-slate-100 text-slate-700 text-xs font-semibold rounded-full">其他</span>';
}

function calculateWaitTime(createdAt) {
    const created = new Date(createdAt);
    const now = new Date();
    const diff = now - created;
    const hours = Math.floor(diff / (1000 * 60 * 60));
    const minutes = Math.floor((diff % (1000 * 60 * 60)) / (1000 * 60));

    if (hours >= 24) {
        const days = Math.floor(hours / 24);
        const remainingHours = hours % 24;
        return {
            hours,
            minutes,
            text: `${days}天${remainingHours}小时`
        };
    } else if (hours > 0) {
        return {
            hours,
            minutes,
            text: `${hours}小时${minutes}分钟`
        };
    } else {
        return {
            hours: 0,
            minutes,
            text: `${minutes}分钟`
        };
    }
}

function searchUndeposits() {
    loadUndeposits(1);
}

function confirmDeposit(id, amount) {
    currentUndepositId = id;
    document.getElementById('actualAmount').value = amount;
    document.getElementById('confirmModal').classList.remove('hidden');
}

function rejectDeposit(id) {
    currentUndepositId = id;
    document.getElementById('rejectModal').classList.remove('hidden');
}

function submitConfirm() {
    const actualAmount = document.getElementById('actualAmount').value;
    const remark = document.getElementById('confirmRemark').value;

    if (!actualAmount || actualAmount <= 0) {
        alert('请输入有效的到账金额');
        return;
    }

    fetch('{{ route("admin_api_undeposit_flows_confirm") }}', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: JSON.stringify({
            undeposit_id: currentUndepositId,
            actual_amount: actualAmount,
            remark: remark
        })
    })
    .then(res => res.json())
    .then(data => {
        if (data.success || data.code === 200) {
            alert('确认成功');
            closeConfirmModal();
            loadUndeposits(currentPage);
            loadStats();
        } else {
            alert(data.message || '确认失败');
        }
    })
    .catch(err => {
        console.error('Confirm error:', err);
        alert('网络错误，请稍后重试');
    });
}

function submitReject() {
    const reason = document.getElementById('rejectReason').value;
    const remark = document.getElementById('rejectRemark').value;

    if (!reason) {
        alert('请选择拒绝原因');
        return;
    }

    fetch('{{ route("admin_api_undeposit_flows_reject") }}', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: JSON.stringify({
            undeposit_id: currentUndepositId,
            reason: reason,
            remark: remark
        })
    })
    .then(res => res.json())
    .then(data => {
        if (data.success || data.code === 200) {
            alert('拒绝成功');
            closeRejectModal();
            loadUndeposits(currentPage);
            loadStats();
        } else {
            alert(data.message || '拒绝失败');
        }
    })
    .catch(err => {
        console.error('Reject error:', err);
        alert('网络错误，请稍后重试');
    });
}

function viewDetail(id) {
    fetch(`{{ route('admin_api_undeposit_flows_detail', ['id' => '__ID__']) }}`.replace('__ID__', id), {
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(res => res.json())
    .then(data => {
        if (data.success && data.undeposit) {
            const u = data.undeposit;
            const waitTime = calculateWaitTime(u.created_at);
            const isOverdue = waitTime.hours >= 24;

            document.getElementById('modalContent').innerHTML = `
                <div class="space-y-6">
                    <div class="flex items-center justify-center mb-4">
                        <div class="w-16 h-16 bg-gradient-to-br from-yellow-500 to-orange-600 rounded-full flex items-center justify-center">
                            <i class="fas fa-clock text-3xl text-white"></i>
                        </div>
                    </div>

                    <div class="text-center mb-6">
                        <p class="text-2xl font-bold text-blue-600 mb-2">$${formatNumber(u.amount || 0)}</p>
                        <p class="text-sm ${isOverdue ? 'text-red-600' : 'text-orange-600'} font-semibold">
                            已等待 ${waitTime.text} ${isOverdue ? '(已超时)' : ''}
                        </p>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div class="col-span-2 bg-slate-50 rounded-lg p-4">
                            <p class="text-sm text-slate-600 mb-1">订单号</p>
                            <p class="text-base font-mono text-slate-800">${u.order_no || '-'}</p>
                        </div>
                        <div><p class="text-sm text-slate-600 mb-1">用户</p><p class="text-base font-semibold text-slate-800">${u.username || 'N/A'}</p></div>
                        <div><p class="text-sm text-slate-600 mb-1">用户ID</p><p class="text-base text-slate-800">${u.user_id || '-'}</p></div>
                        <div><p class="text-sm text-slate-600 mb-1">申请金额</p><p class="text-base font-bold text-blue-600">$${formatNumber(u.amount || 0)}</p></div>
                        <div><p class="text-sm text-slate-600 mb-1">币种</p><p class="text-base text-slate-800">${u.currency || 'USD'}</p></div>
                        <div class="col-span-2"><p class="text-sm text-slate-600 mb-1">支付方式</p><div class="mt-1">${getMethodBadge(u.payment_method)}</div></div>
                        <div><p class="text-sm text-slate-600 mb-1">提交时间</p><p class="text-base text-slate-800">${u.created_at || '-'}</p></div>
                        <div><p class="text-sm text-slate-600 mb-1">等待时长</p><p class="text-base ${isOverdue ? 'text-red-600' : 'text-orange-600'} font-semibold">${waitTime.text}</p></div>
                    </div>

                    ${u.voucher_url ? `
                        <div class="border-t border-slate-200 pt-4">
                            <p class="text-sm text-slate-600 mb-2">支付凭证</p>
                            <div class="rounded-lg overflow-hidden border border-slate-200 cursor-pointer" onclick="window.open('${u.voucher_url}', '_blank')">
                                <img src="${u.voucher_url}" alt="支付凭证" class="w-full h-auto" onerror="this.parentElement.innerHTML='<div class=\\'p-8 text-center text-slate-400\\'>凭证加载失败</div>'">
                            </div>
                            <p class="text-xs text-slate-500 mt-2 text-center"><i class="fas fa-info-circle mr-1"></i>点击图片查看大图</p>
                        </div>
                    ` : ''}

                    ${u.remark ? `
                        <div class="border-t border-slate-200 pt-4">
                            <p class="text-sm text-slate-600 mb-2">备注说明</p>
                            <p class="text-base text-slate-800 bg-slate-50 rounded-lg p-3">${u.remark}</p>
                        </div>
                    ` : ''}

                    <div class="border-t border-slate-200 pt-4 flex gap-3">
                        <button onclick="confirmDeposit(${u.id}, ${u.amount}); closeDetailModal();" class="flex-1 px-4 py-3 bg-gradient-to-r from-green-500 to-green-600 text-white font-semibold rounded-lg hover:shadow-lg transition">
                            <i class="fas fa-check mr-2"></i>确认入金
                        </button>
                        <button onclick="rejectDeposit(${u.id}); closeDetailModal();" class="flex-1 px-4 py-3 bg-gradient-to-r from-red-500 to-red-600 text-white font-semibold rounded-lg hover:shadow-lg transition">
                            <i class="fas fa-times mr-2"></i>拒绝入金
                        </button>
                    </div>
                </div>
            `;
            document.getElementById('detailModal').classList.remove('hidden');
        }
    })
    .catch(err => console.error('Load detail error:', err));
}

function viewVoucher(url) {
    window.open(url, '_blank');
}

function closeDetailModal() {
    document.getElementById('detailModal').classList.add('hidden');
}

function closeConfirmModal() {
    document.getElementById('confirmModal').classList.add('hidden');
    currentUndepositId = null;
    document.getElementById('actualAmount').value = '';
    document.getElementById('confirmRemark').value = '';
}

function closeRejectModal() {
    document.getElementById('rejectModal').classList.add('hidden');
    currentUndepositId = null;
    document.getElementById('rejectReason').value = '';
    document.getElementById('rejectRemark').value = '';
}

function exportUndeposits() {
    const startDate = document.getElementById('filterStartDate').value;
    const endDate = document.getElementById('filterEndDate').value;

    let url = '{{ route("admin_api_undeposit_flows_export") }}';
    const params = new URLSearchParams();
    if (startDate) params.append('start_date', startDate);
    if (endDate) params.append('end_date', endDate);

    if (params.toString()) {
        url += '?' + params.toString();
    }

    window.location.href = url;
}

function formatNumber(num) {
    return new Intl.NumberFormat('en-US').format(num);
}

function showError(message) {
    document.getElementById('undepositTableBody').innerHTML = `
        <tr><td colspan="8" class="px-6 py-12 text-center text-red-500">
            <i class="fas fa-exclamation-circle text-3xl mb-3"></i><p>${message}</p>
        </td></tr>
    `;
}
</script>
@endsection
