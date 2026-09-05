@extends('admin-tailwind.layouts.app')

@section('title', '入金管理 - 管理后台')

@section('content')
<!-- Page Header -->
<div class="flex items-center justify-between mb-8">
    <div>
        <h1 class="text-3xl font-bold text-slate-800">入金管理</h1>
        <p class="text-slate-600 mt-2">管理所有用户入金记录</p>
    </div>
    <div class="flex gap-3">
        <button onclick="exportDeposits()" class="px-6 py-3 bg-gradient-to-r from-green-500 to-green-600 text-white font-semibold rounded-lg hover:shadow-lg transition">
            <i class="fas fa-download mr-2"></i>导出数据
        </button>
        <button onclick="showImportModal()" class="px-6 py-3 bg-gradient-to-r from-blue-500 to-indigo-600 text-white font-semibold rounded-lg hover:shadow-lg transition">
            <i class="fas fa-upload mr-2"></i>批量导入
        </button>
    </div>
</div>

<!-- Stats Cards -->
<div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-6">
    <div class="bg-white rounded-xl shadow-lg p-6 border-l-4 border-green-500">
        <p class="text-sm text-slate-600 mb-2">今日入金</p>
        <p class="text-3xl font-bold text-slate-800">$<span id="todayDeposits">0</span></p>
    </div>
    <div class="bg-white rounded-xl shadow-lg p-6 border-l-4 border-blue-500">
        <p class="text-sm text-slate-600 mb-2">本月入金</p>
        <p class="text-3xl font-bold text-slate-800">$<span id="monthDeposits">0</span></p>
    </div>
    <div class="bg-white rounded-xl shadow-lg p-6 border-l-4 border-purple-500">
        <p class="text-sm text-slate-600 mb-2">待审核</p>
        <p class="text-3xl font-bold text-slate-800" id="pendingCount">0</p>
    </div>
    <div class="bg-white rounded-xl shadow-lg p-6 border-l-4 border-orange-500">
        <p class="text-sm text-slate-600 mb-2">总入金额</p>
        <p class="text-3xl font-bold text-slate-800">$<span id="totalDeposits">0</span></p>
    </div>
</div>

<!-- Search & Filter -->
<div class="bg-white rounded-xl shadow-lg p-6 mb-6">
    <div class="grid grid-cols-1 md:grid-cols-6 gap-4">
        <div>
            <label class="block text-sm font-semibold text-slate-700 mb-2">搜索</label>
            <input type="text" id="searchKeyword" placeholder="订单号/用户名" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
        </div>
        <div>
            <label class="block text-sm font-semibold text-slate-700 mb-2">状态</label>
            <select id="filterStatus" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                <option value="">全部状态</option>
                <option value="pending">待审核</option>
                <option value="processing">处理中</option>
                <option value="completed">已完成</option>
                <option value="failed">失败</option>
            </select>
        </div>
        <div>
            <label class="block text-sm font-semibold text-slate-700 mb-2">渠道</label>
            <select id="filterChannel" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                <option value="">全部渠道</option>
                <option value="bank">银行转账</option>
                <option value="alipay">支付宝</option>
                <option value="wechat">微信支付</option>
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
            <button onclick="searchDeposits()" class="w-full px-6 py-2 bg-gradient-to-r from-blue-500 to-indigo-600 text-white font-semibold rounded-lg hover:shadow-lg transition">
                <i class="fas fa-search mr-2"></i>搜索
            </button>
        </div>
    </div>
</div>

<!-- Deposits Table -->
<div class="bg-white rounded-xl shadow-lg overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full">
            <thead class="bg-slate-50 border-b border-slate-200">
                <tr>
                    <th class="px-6 py-4 text-left text-xs font-semibold text-slate-600 uppercase tracking-wider">订单号</th>
                    <th class="px-6 py-4 text-left text-xs font-semibold text-slate-600 uppercase tracking-wider">用户信息</th>
                    <th class="px-6 py-4 text-left text-xs font-semibold text-slate-600 uppercase tracking-wider">金额</th>
                    <th class="px-6 py-4 text-left text-xs font-semibold text-slate-600 uppercase tracking-wider">渠道</th>
                    <th class="px-6 py-4 text-left text-xs font-semibold text-slate-600 uppercase tracking-wider">状态</th>
                    <th class="px-6 py-4 text-left text-xs font-semibold text-slate-600 uppercase tracking-wider">申请时间</th>
                    <th class="px-6 py-4 text-left text-xs font-semibold text-slate-600 uppercase tracking-wider">处理时间</th>
                    <th class="px-6 py-4 text-left text-xs font-semibold text-slate-600 uppercase tracking-wider">操作</th>
                </tr>
            </thead>
            <tbody id="depositsTableBody" class="divide-y divide-slate-200">
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
    <div class="bg-white rounded-2xl max-w-2xl w-full max-h-[90vh] overflow-y-auto">
        <div class="sticky top-0 bg-gradient-to-r from-blue-500 to-indigo-600 px-6 py-4 flex items-center justify-between">
            <h3 class="text-xl font-bold text-white">入金详情</h3>
            <button onclick="closeModal()" class="text-white hover:text-slate-200">
                <i class="fas fa-times text-xl"></i>
            </button>
        </div>
        <div id="modalContent" class="p-6">
            <!-- Content will be loaded dynamically -->
        </div>
    </div>
</div>

<!-- Approve Modal -->
<div id="approveModal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl max-w-md w-full">
        <div class="bg-gradient-to-r from-green-500 to-green-600 px-6 py-4">
            <h3 class="text-xl font-bold text-white">审核入金</h3>
        </div>
        <div class="p-6">
            <div class="mb-4">
                <label class="block text-sm font-semibold text-slate-700 mb-2">审核结果</label>
                <select id="approveResult" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                    <option value="approved">通过</option>
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
let currentDepositId = null;

document.addEventListener('DOMContentLoaded', function() {
    loadDepositsStats();
    loadDeposits();
});

function loadDepositsStats() {
    fetch('{{ route("admin_api_deposits_stats") }}', {
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(res => res.json())
    .then(data => {
        if (data.success || data.code === 200) {
            document.getElementById('todayDeposits').textContent = formatNumber(data.todayDeposits || 0);
            document.getElementById('monthDeposits').textContent = formatNumber(data.monthDeposits || 0);
            document.getElementById('pendingCount').textContent = formatNumber(data.pendingCount || 0);
            document.getElementById('totalDeposits').textContent = formatNumber(data.totalDeposits || 0);
        }
    })
    .catch(err => console.error('Load stats error:', err));
}

function loadDeposits(page = 1) {
    currentPage = page;
    const keyword = document.getElementById('searchKeyword').value;
    const status = document.getElementById('filterStatus').value;
    const channel = document.getElementById('filterChannel').value;
    const startDate = document.getElementById('filterStartDate').value;
    const endDate = document.getElementById('filterEndDate').value;

    const params = new URLSearchParams({
        page: page,
        keyword: keyword,
        status: status,
        channel: channel,
        start_date: startDate,
        end_date: endDate
    });

    fetch(`{{ route('admin_api_deposits_list') }}?${params}`, {
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(res => res.json())
    .then(data => {
        if (data.success || data.code === 200) {
            renderDepositsTable(data.deposits || []);
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

function renderDepositsTable(deposits) {
    const tbody = document.getElementById('depositsTableBody');

    if (deposits.length === 0) {
        tbody.innerHTML = `
            <tr>
                <td colspan="8" class="px-6 py-12 text-center text-slate-500">
                    <i class="fas fa-inbox text-3xl mb-3"></i>
                    <p>暂无数据</p>
                </td>
            </tr>
        `;
        return;
    }

    tbody.innerHTML = deposits.map(deposit => `
        <tr class="hover:bg-slate-50 transition">
            <td class="px-6 py-4">
                <p class="text-sm font-mono text-slate-800">${deposit.order_no || '-'}</p>
            </td>
            <td class="px-6 py-4">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 bg-gradient-to-br from-blue-500 to-indigo-600 rounded-full flex items-center justify-center text-white font-bold">
                        ${(deposit.username || 'U').charAt(0).toUpperCase()}
                    </div>
                    <div>
                        <p class="text-sm font-semibold text-slate-800">${deposit.username || 'N/A'}</p>
                        <p class="text-xs text-slate-500">ID: ${deposit.user_id || '-'}</p>
                    </div>
                </div>
            </td>
            <td class="px-6 py-4">
                <p class="text-lg font-bold text-green-600">$${formatNumber(deposit.amount || 0)}</p>
            </td>
            <td class="px-6 py-4">
                ${getChannelBadge(deposit.channel)}
            </td>
            <td class="px-6 py-4">
                ${getStatusBadge(deposit.status)}
            </td>
            <td class="px-6 py-4 text-sm text-slate-600">${deposit.created_at || '-'}</td>
            <td class="px-6 py-4 text-sm text-slate-600">${deposit.processed_at || '-'}</td>
            <td class="px-6 py-4">
                <div class="flex gap-2">
                    <button onclick="viewDeposit(${deposit.id})" class="px-3 py-1 bg-blue-100 text-blue-700 text-xs font-semibold rounded hover:bg-blue-200 transition">
                        <i class="fas fa-eye mr-1"></i>详情
                    </button>
                    ${deposit.status === 'pending'
                        ? `<button onclick="approveDeposit(${deposit.id})" class="px-3 py-1 bg-green-100 text-green-700 text-xs font-semibold rounded hover:bg-green-200 transition">
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
        html += `<button onclick="loadDeposits(${currentPage - 1})" class="px-3 py-1 border border-slate-300 rounded hover:bg-slate-50">上一页</button>`;
    }
    for (let i = Math.max(1, currentPage - 2); i <= Math.min(totalPages, currentPage + 2); i++) {
        html += `<button onclick="loadDeposits(${i})" class="px-3 py-1 border ${i === currentPage ? 'bg-blue-500 text-white' : 'border-slate-300 hover:bg-slate-50'} rounded">${i}</button>`;
    }
    if (currentPage < totalPages) {
        html += `<button onclick="loadDeposits(${currentPage + 1})" class="px-3 py-1 border border-slate-300 rounded hover:bg-slate-50">下一页</button>`;
    }
    paginationDiv.innerHTML = html;
}

function getChannelBadge(channel) {
    const badges = {
        'bank': '<span class="px-2 py-1 bg-blue-100 text-blue-700 text-xs font-semibold rounded-full">银行转账</span>',
        'alipay': '<span class="px-2 py-1 bg-cyan-100 text-cyan-700 text-xs font-semibold rounded-full">支付宝</span>',
        'wechat': '<span class="px-2 py-1 bg-green-100 text-green-700 text-xs font-semibold rounded-full">微信支付</span>',
        'usdt': '<span class="px-2 py-1 bg-orange-100 text-orange-700 text-xs font-semibold rounded-full">USDT</span>'
    };
    return badges[channel] || '<span class="px-2 py-1 bg-slate-100 text-slate-700 text-xs font-semibold rounded-full">其他</span>';
}

function getStatusBadge(status) {
    const badges = {
        'pending': '<span class="px-3 py-1 bg-yellow-100 text-yellow-700 text-xs font-semibold rounded-full">待审核</span>',
        'processing': '<span class="px-3 py-1 bg-blue-100 text-blue-700 text-xs font-semibold rounded-full">处理中</span>',
        'completed': '<span class="px-3 py-1 bg-green-100 text-green-700 text-xs font-semibold rounded-full">已完成</span>',
        'failed': '<span class="px-3 py-1 bg-red-100 text-red-700 text-xs font-semibold rounded-full">失败</span>'
    };
    return badges[status] || '<span class="px-3 py-1 bg-slate-100 text-slate-700 text-xs font-semibold rounded-full">未知</span>';
}

function searchDeposits() {
    loadDeposits(1);
}

function viewDeposit(depositId) {
    fetch(`{{ route('admin_api_deposits_detail', ['id' => '__ID__']) }}`.replace('__ID__', depositId), {
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(res => res.json())
    .then(data => {
        if (data.success && data.deposit) {
            const d = data.deposit;
            document.getElementById('modalContent').innerHTML = `
                <div class="space-y-4">
                    <div class="grid grid-cols-2 gap-4">
                        <div><p class="text-sm text-slate-600 mb-1">订单号</p><p class="text-base font-semibold text-slate-800">${d.order_no || '-'}</p></div>
                        <div><p class="text-sm text-slate-600 mb-1">用户</p><p class="text-base font-semibold text-slate-800">${d.username || 'N/A'}</p></div>
                        <div><p class="text-sm text-slate-600 mb-1">金额</p><p class="text-base font-bold text-green-600">$${formatNumber(d.amount || 0)}</p></div>
                        <div><p class="text-sm text-slate-600 mb-1">渠道</p><p class="text-base font-semibold text-slate-800">${d.channel || '-'}</p></div>
                        <div><p class="text-sm text-slate-600 mb-1">状态</p>${getStatusBadge(d.status)}</div>
                        <div><p class="text-sm text-slate-600 mb-1">申请时间</p><p class="text-base font-semibold text-slate-800">${d.created_at || '-'}</p></div>
                        <div><p class="text-sm text-slate-600 mb-1">处理时间</p><p class="text-base font-semibold text-slate-800">${d.processed_at || '-'}</p></div>
                        <div><p class="text-sm text-slate-600 mb-1">处理人</p><p class="text-base font-semibold text-slate-800">${d.processor || '-'}</p></div>
                    </div>
                    ${d.remark ? `<div><p class="text-sm text-slate-600 mb-1">备注</p><p class="text-base text-slate-800">${d.remark}</p></div>` : ''}
                </div>
            `;
            document.getElementById('detailModal').classList.remove('hidden');
        }
    })
    .catch(err => console.error('Load detail error:', err));
}

function approveDeposit(depositId) {
    currentDepositId = depositId;
    document.getElementById('approveModal').classList.remove('hidden');
}

function submitApprove() {
    const result = document.getElementById('approveResult').value;
    const remark = document.getElementById('approveRemark').value;

    fetch(`{{ route('admin_api_deposits_approve') }}`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: JSON.stringify({ deposit_id: currentDepositId, result: result, remark: remark })
    })
    .then(res => res.json())
    .then(data => {
        if (data.success || data.code === 200) {
            alert('审核成功');
            closeApproveModal();
            loadDeposits(currentPage);
            loadDepositsStats();
        } else {
            alert(data.message || '审核失败');
        }
    })
    .catch(err => {
        console.error('Approve error:', err);
        alert('网络错误，请稍后重试');
    });
}

function closeModal() {
    document.getElementById('detailModal').classList.add('hidden');
}

function closeApproveModal() {
    document.getElementById('approveModal').classList.add('hidden');
    currentDepositId = null;
}

function showImportModal() {
    alert('批量导入功能开发中');
}

function exportDeposits() {
    window.location.href = '{{ route("admin_api_deposits_export") }}';
}

function formatNumber(num) {
    return new Intl.NumberFormat('en-US').format(num);
}

function showError(message) {
    document.getElementById('depositsTableBody').innerHTML = `
        <tr><td colspan="8" class="px-6 py-12 text-center text-red-500">
            <i class="fas fa-exclamation-circle text-3xl mb-3"></i><p>${message}</p>
        </td></tr>
    `;
}
</script>
@endsection
