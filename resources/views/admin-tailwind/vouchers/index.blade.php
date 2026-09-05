@extends('admin-tailwind.layouts.app')

@section('title', '凭证管理 - 管理后台')

@section('content')
<!-- Page Header -->
<div class="flex items-center justify-between mb-8">
    <div>
        <h1 class="text-3xl font-bold text-slate-800">凭证管理</h1>
        <p class="text-slate-600 mt-2">管理所有用户上传的支付凭证</p>
    </div>
    <div class="flex gap-3">
        <button onclick="exportVouchers()" class="px-6 py-3 bg-gradient-to-r from-green-500 to-green-600 text-white font-semibold rounded-lg hover:shadow-lg transition">
            <i class="fas fa-download mr-2"></i>导出数据
        </button>
    </div>
</div>

<!-- Stats Cards -->
<div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-6">
    <div class="bg-white rounded-xl shadow-lg p-6 border-l-4 border-yellow-500">
        <p class="text-sm text-slate-600 mb-2">待审核凭证</p>
        <p class="text-3xl font-bold text-slate-800" id="pendingCount">0</p>
    </div>
    <div class="bg-white rounded-xl shadow-lg p-6 border-l-4 border-green-500">
        <p class="text-sm text-slate-600 mb-2">已通过</p>
        <p class="text-3xl font-bold text-slate-800" id="approvedCount">0</p>
    </div>
    <div class="bg-white rounded-xl shadow-lg p-6 border-l-4 border-red-500">
        <p class="text-sm text-slate-600 mb-2">已拒绝</p>
        <p class="text-3xl font-bold text-slate-800" id="rejectedCount">0</p>
    </div>
    <div class="bg-white rounded-xl shadow-lg p-6 border-l-4 border-blue-500">
        <p class="text-sm text-slate-600 mb-2">今日新增</p>
        <p class="text-3xl font-bold text-slate-800" id="todayNew">0</p>
    </div>
</div>

<!-- Tab Navigation -->
<div class="bg-white rounded-t-xl shadow-lg border-b border-slate-200">
    <div class="flex">
        <button onclick="switchTab('all')" id="tab-all" class="px-6 py-4 text-sm font-semibold text-blue-600 border-b-2 border-blue-600 transition">
            全部凭证
        </button>
        <button onclick="switchTab('pending')" id="tab-pending" class="px-6 py-4 text-sm font-semibold text-slate-600 hover:text-slate-800 transition">
            待审核 <span id="badge-pending" class="ml-2 px-2 py-1 bg-yellow-100 text-yellow-700 text-xs rounded-full">0</span>
        </button>
        <button onclick="switchTab('approved')" id="tab-approved" class="px-6 py-4 text-sm font-semibold text-slate-600 hover:text-slate-800 transition">
            已通过
        </button>
        <button onclick="switchTab('rejected')" id="tab-rejected" class="px-6 py-4 text-sm font-semibold text-slate-600 hover:text-slate-800 transition">
            已拒绝
        </button>
    </div>
</div>

<!-- Search & Filter -->
<div class="bg-white shadow-lg p-6 border-b border-slate-200">
    <div class="grid grid-cols-1 md:grid-cols-5 gap-4">
        <div>
            <label class="block text-sm font-semibold text-slate-700 mb-2">搜索</label>
            <input type="text" id="searchKeyword" placeholder="凭证号/用户名" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
        </div>
        <div>
            <label class="block text-sm font-semibold text-slate-700 mb-2">凭证类型</label>
            <select id="filterType" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                <option value="">全部类型</option>
                <option value="deposit">入金凭证</option>
                <option value="withdraw">出金凭证</option>
                <option value="identity">身份凭证</option>
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
            <button onclick="searchVouchers()" class="w-full px-6 py-2 bg-gradient-to-r from-blue-500 to-indigo-600 text-white font-semibold rounded-lg hover:shadow-lg transition">
                <i class="fas fa-search mr-2"></i>搜索
            </button>
        </div>
    </div>
</div>

<!-- Vouchers Grid -->
<div class="bg-white rounded-b-xl shadow-lg p-6">
    <div id="vouchersGrid" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">
        <div class="col-span-full flex items-center justify-center py-12 text-slate-500">
            <i class="fas fa-spinner fa-spin text-3xl mb-3"></i>
            <p class="ml-3">加载中...</p>
        </div>
    </div>

    <!-- Pagination -->
    <div class="mt-6 pt-6 border-t border-slate-200 flex items-center justify-between">
        <div class="text-sm text-slate-600">
            显示第 <span id="pageStart">0</span> - <span id="pageEnd">0</span> 条，共 <span id="totalRecords">0</span> 条
        </div>
        <div class="flex gap-2" id="pagination"></div>
    </div>
</div>

<!-- Detail Modal -->
<div id="detailModal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl max-w-4xl w-full max-h-[90vh] overflow-y-auto">
        <div class="sticky top-0 bg-gradient-to-r from-indigo-500 to-purple-600 px-6 py-4 flex items-center justify-between">
            <h3 class="text-xl font-bold text-white">凭证详情</h3>
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
            <h3 class="text-xl font-bold text-white">审核凭证</h3>
        </div>
        <div class="p-6">
            <div class="mb-4">
                <label class="block text-sm font-semibold text-slate-700 mb-2">审核结果</label>
                <select id="approveResult" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                    <option value="approved">通过</option>
                    <option value="rejected">拒绝</option>
                </select>
            </div>
            <div class="mb-4" id="rejectReasonContainer" style="display: none;">
                <label class="block text-sm font-semibold text-slate-700 mb-2">拒绝原因</label>
                <select id="rejectReason" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 mb-2">
                    <option value="">请选择拒绝原因</option>
                    <option value="unclear">图片不清晰</option>
                    <option value="incomplete">信息不完整</option>
                    <option value="mismatch">信息不匹配</option>
                    <option value="fake">疑似伪造</option>
                    <option value="expired">凭证过期</option>
                    <option value="other">其他原因</option>
                </select>
            </div>
            <div class="mb-4">
                <label class="block text-sm font-semibold text-slate-700 mb-2">备注说明</label>
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
let currentVoucherId = null;

document.addEventListener('DOMContentLoaded', function() {
    loadStats();
    loadVouchers();

    document.getElementById('approveResult').addEventListener('change', function() {
        const rejectReasonContainer = document.getElementById('rejectReasonContainer');
        if (this.value === 'rejected') {
            rejectReasonContainer.style.display = 'block';
        } else {
            rejectReasonContainer.style.display = 'none';
        }
    });
});

function loadStats() {
    fetch('{{ route("admin_api_vouchers_stats") }}', {
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(res => res.json())
    .then(data => {
        if (data.success || data.code === 200) {
            document.getElementById('pendingCount').textContent = formatNumber(data.pendingCount || 0);
            document.getElementById('approvedCount').textContent = formatNumber(data.approvedCount || 0);
            document.getElementById('rejectedCount').textContent = formatNumber(data.rejectedCount || 0);
            document.getElementById('todayNew').textContent = formatNumber(data.todayNew || 0);

            document.getElementById('badge-pending').textContent = data.pendingCount || 0;
        }
    })
    .catch(err => console.error('Load stats error:', err));
}

function loadVouchers(page = 1) {
    currentPage = page;
    const keyword = document.getElementById('searchKeyword').value;
    const type = document.getElementById('filterType').value;
    const startDate = document.getElementById('filterStartDate').value;
    const endDate = document.getElementById('filterEndDate').value;

    const params = new URLSearchParams({
        page: page,
        keyword: keyword,
        type: type,
        start_date: startDate,
        end_date: endDate,
        status: currentTab === 'all' ? '' : currentTab
    });

    fetch(`{{ route('admin_api_vouchers_list') }}?${params}`, {
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(res => res.json())
    .then(data => {
        if (data.success || data.code === 200) {
            renderVouchersGrid(data.vouchers || []);
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

function renderVouchersGrid(vouchers) {
    const grid = document.getElementById('vouchersGrid');

    if (vouchers.length === 0) {
        grid.innerHTML = `
            <div class="col-span-full flex flex-col items-center justify-center py-12 text-slate-500">
                <i class="fas fa-inbox text-4xl mb-3"></i>
                <p>暂无凭证数据</p>
            </div>
        `;
        return;
    }

    grid.innerHTML = vouchers.map(v => `
        <div class="bg-white border border-slate-200 rounded-xl overflow-hidden hover:shadow-xl transition-all duration-300 cursor-pointer" onclick="viewVoucher(${v.id})">
            <div class="relative aspect-[4/3] bg-slate-100">
                ${v.image_url
                    ? `<img src="${v.image_url}" alt="凭证图片" class="w-full h-full object-cover" onerror="this.src='data:image/svg+xml,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 400 300%22%3E%3Crect fill=%22%23e2e8f0%22 width=%22400%22 height=%22300%22/%3E%3Ctext fill=%22%2394a3b8%22 font-family=%22sans-serif%22 font-size=%2224%22 x=%2250%25%22 y=%2250%25%22 text-anchor=%22middle%22 dominant-baseline=%22middle%22%3E图片加载失败%3C/text%3E%3C/svg%3E'">`
                    : `<div class="w-full h-full flex items-center justify-center"><i class="fas fa-file-image text-4xl text-slate-300"></i></div>`
                }
                <div class="absolute top-2 right-2">
                    ${getStatusBadge(v.status)}
                </div>
                <div class="absolute top-2 left-2">
                    ${getTypeBadge(v.type)}
                </div>
            </div>
            <div class="p-4">
                <div class="flex items-center gap-2 mb-3">
                    <div class="w-8 h-8 bg-gradient-to-br from-indigo-500 to-purple-600 rounded-full flex items-center justify-center text-white text-xs font-bold">
                        ${(v.username || 'U').charAt(0).toUpperCase()}
                    </div>
                    <div class="flex-1 min-w-0">
                        <p class="text-sm font-semibold text-slate-800 truncate">${v.username || 'N/A'}</p>
                        <p class="text-xs text-slate-500">${v.voucher_no || '-'}</p>
                    </div>
                </div>
                <div class="space-y-1 text-xs text-slate-600">
                    <div class="flex justify-between">
                        <span>上传时间：</span>
                        <span class="font-medium">${v.created_at || '-'}</span>
                    </div>
                    ${v.reviewed_at ? `
                        <div class="flex justify-between">
                            <span>审核时间：</span>
                            <span class="font-medium">${v.reviewed_at}</span>
                        </div>
                    ` : ''}
                </div>
                ${v.status === 'pending' ? `
                    <div class="mt-4 flex gap-2">
                        <button onclick="event.stopPropagation(); approveVoucher(${v.id})" class="flex-1 px-3 py-2 bg-green-500 text-white text-xs font-semibold rounded-lg hover:bg-green-600 transition">
                            <i class="fas fa-check mr-1"></i>审核
                        </button>
                    </div>
                ` : ''}
            </div>
        </div>
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
        html += `<button onclick="loadVouchers(${currentPage - 1})" class="px-3 py-1 border border-slate-300 rounded hover:bg-slate-50">上一页</button>`;
    }
    for (let i = Math.max(1, currentPage - 2); i <= Math.min(totalPages, currentPage + 2); i++) {
        html += `<button onclick="loadVouchers(${i})" class="px-3 py-1 border ${i === currentPage ? 'bg-blue-500 text-white' : 'border-slate-300 hover:bg-slate-50'} rounded">${i}</button>`;
    }
    if (currentPage < totalPages) {
        html += `<button onclick="loadVouchers(${currentPage + 1})" class="px-3 py-1 border border-slate-300 rounded hover:bg-slate-50">下一页</button>`;
    }
    paginationDiv.innerHTML = html;
}

function getStatusBadge(status) {
    const badges = {
        'pending': '<span class="px-2 py-1 bg-yellow-500 text-white text-xs font-semibold rounded-full">待审核</span>',
        'approved': '<span class="px-2 py-1 bg-green-500 text-white text-xs font-semibold rounded-full">已通过</span>',
        'rejected': '<span class="px-2 py-1 bg-red-500 text-white text-xs font-semibold rounded-full">已拒绝</span>'
    };
    return badges[status] || '<span class="px-2 py-1 bg-slate-500 text-white text-xs font-semibold rounded-full">未知</span>';
}

function getTypeBadge(type) {
    const badges = {
        'deposit': '<span class="px-2 py-1 bg-blue-500 text-white text-xs font-semibold rounded-full">入金</span>',
        'withdraw': '<span class="px-2 py-1 bg-orange-500 text-white text-xs font-semibold rounded-full">出金</span>',
        'identity': '<span class="px-2 py-1 bg-purple-500 text-white text-xs font-semibold rounded-full">身份</span>'
    };
    return badges[type] || '<span class="px-2 py-1 bg-slate-500 text-white text-xs font-semibold rounded-full">其他</span>';
}

function switchTab(tab) {
    currentTab = tab;
    document.querySelectorAll('[id^="tab-"]').forEach(btn => {
        btn.className = 'px-6 py-4 text-sm font-semibold text-slate-600 hover:text-slate-800 transition';
    });
    document.getElementById('tab-' + tab).className = 'px-6 py-4 text-sm font-semibold text-blue-600 border-b-2 border-blue-600 transition';
    loadVouchers(1);
}

function searchVouchers() {
    loadVouchers(1);
}

function viewVoucher(id) {
    fetch(`{{ route('admin_api_vouchers_detail', ['id' => '__ID__']) }}`.replace('__ID__', id), {
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(res => res.json())
    .then(data => {
        if (data.success && data.voucher) {
            const v = data.voucher;
            document.getElementById('modalContent').innerHTML = `
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    <div>
                        <div class="aspect-[4/3] bg-slate-100 rounded-lg overflow-hidden mb-4">
                            ${v.image_url
                                ? `<img src="${v.image_url}" alt="凭证图片" class="w-full h-full object-contain cursor-pointer" onclick="window.open('${v.image_url}', '_blank')">`
                                : `<div class="w-full h-full flex items-center justify-center"><i class="fas fa-file-image text-6xl text-slate-300"></i></div>`
                            }
                        </div>
                        ${v.image_url ? '<p class="text-xs text-slate-500 text-center"><i class="fas fa-info-circle mr-1"></i>点击图片可查看大图</p>' : ''}
                    </div>
                    <div class="space-y-4">
                        <div class="flex items-center justify-between mb-4">
                            ${getTypeBadge(v.type)}
                            ${getStatusBadge(v.status)}
                        </div>

                        <div class="grid grid-cols-2 gap-4">
                            <div><p class="text-sm text-slate-600 mb-1">凭证号</p><p class="text-base font-mono text-slate-800">${v.voucher_no || '-'}</p></div>
                            <div><p class="text-sm text-slate-600 mb-1">用户</p><p class="text-base font-semibold text-slate-800">${v.username || 'N/A'}</p></div>
                            <div><p class="text-sm text-slate-600 mb-1">用户ID</p><p class="text-base text-slate-800">${v.user_id || '-'}</p></div>
                            <div><p class="text-sm text-slate-600 mb-1">关联订单</p><p class="text-base font-mono text-slate-800">${v.order_no || '-'}</p></div>
                            <div><p class="text-sm text-slate-600 mb-1">上传时间</p><p class="text-base text-slate-800">${v.created_at || '-'}</p></div>
                            ${v.reviewed_at ? `<div><p class="text-sm text-slate-600 mb-1">审核时间</p><p class="text-base text-slate-800">${v.reviewed_at}</p></div>` : ''}
                            ${v.reviewer_name ? `<div><p class="text-sm text-slate-600 mb-1">审核人</p><p class="text-base text-slate-800">${v.reviewer_name}</p></div>` : ''}
                        </div>

                        ${v.remark ? `
                            <div class="border-t border-slate-200 pt-4">
                                <p class="text-sm text-slate-600 mb-2">备注说明</p>
                                <p class="text-base text-slate-800 bg-slate-50 rounded-lg p-3">${v.remark}</p>
                            </div>
                        ` : ''}

                        ${v.reject_reason ? `
                            <div class="border-t border-slate-200 pt-4">
                                <p class="text-sm text-slate-600 mb-2">拒绝原因</p>
                                <p class="text-base text-red-600 bg-red-50 rounded-lg p-3">${v.reject_reason}</p>
                            </div>
                        ` : ''}

                        ${v.status === 'pending' ? `
                            <div class="border-t border-slate-200 pt-4">
                                <button onclick="approveVoucher(${v.id}); closeDetailModal();" class="w-full px-4 py-3 bg-gradient-to-r from-green-500 to-green-600 text-white font-semibold rounded-lg hover:shadow-lg transition">
                                    <i class="fas fa-check mr-2"></i>审核此凭证
                                </button>
                            </div>
                        ` : ''}
                    </div>
                </div>
            `;
            document.getElementById('detailModal').classList.remove('hidden');
        }
    })
    .catch(err => console.error('Load detail error:', err));
}

function approveVoucher(id) {
    currentVoucherId = id;
    document.getElementById('approveModal').classList.remove('hidden');
}

function submitApprove() {
    const result = document.getElementById('approveResult').value;
    const rejectReason = document.getElementById('rejectReason').value;
    const remark = document.getElementById('approveRemark').value;

    if (result === 'rejected' && !rejectReason) {
        alert('请选择拒绝原因');
        return;
    }

    fetch(`{{ route('admin_api_vouchers_approve') }}`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: JSON.stringify({
            voucher_id: currentVoucherId,
            result: result,
            reject_reason: rejectReason,
            remark: remark
        })
    })
    .then(res => res.json())
    .then(data => {
        if (data.success || data.code === 200) {
            alert('审核成功');
            closeApproveModal();
            loadVouchers(currentPage);
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

function closeDetailModal() {
    document.getElementById('detailModal').classList.add('hidden');
}

function closeApproveModal() {
    document.getElementById('approveModal').classList.add('hidden');
    currentVoucherId = null;
    document.getElementById('approveResult').value = 'approved';
    document.getElementById('rejectReason').value = '';
    document.getElementById('approveRemark').value = '';
    document.getElementById('rejectReasonContainer').style.display = 'none';
}

function exportVouchers() {
    window.location.href = '{{ route("admin_api_vouchers_export") }}';
}

function formatNumber(num) {
    return new Intl.NumberFormat('en-US').format(num);
}

function showError(message) {
    document.getElementById('vouchersGrid').innerHTML = `
        <div class="col-span-full flex flex-col items-center justify-center py-12 text-red-500">
            <i class="fas fa-exclamation-circle text-4xl mb-3"></i>
            <p>${message}</p>
        </div>
    `;
}
</script>
@endsection
