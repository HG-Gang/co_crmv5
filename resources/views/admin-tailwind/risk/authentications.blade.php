@extends('admin-tailwind.layouts.app')

@section('title', '身份认证管理 - 管理后台')

@section('content')
<!-- Page Header -->
<div class="flex items-center justify-between mb-8">
    <div>
        <h1 class="text-3xl font-bold text-slate-800">身份认证管理</h1>
        <p class="text-slate-600 mt-2">审核用户身份认证申请和资料</p>
    </div>
    <div class="flex gap-3">
        <button onclick="refreshData()" class="px-6 py-3 bg-gradient-to-r from-blue-500 to-indigo-600 text-white font-semibold rounded-lg hover:shadow-lg transition">
            <i class="fas fa-sync-alt mr-2"></i>刷新
        </button>
        <button onclick="exportRecords()" class="px-6 py-3 bg-gradient-to-r from-green-500 to-emerald-600 text-white font-semibold rounded-lg hover:shadow-lg transition">
            <i class="fas fa-file-export mr-2"></i>导出
        </button>
    </div>
</div>

<!-- Stats Cards -->
<div class="grid grid-cols-1 md:grid-cols-5 gap-6 mb-6">
    <div class="bg-white rounded-xl shadow-lg p-6 border-l-4 border-yellow-500">
        <p class="text-sm text-slate-600 mb-2">待审核</p>
        <p class="text-3xl font-bold text-yellow-600" id="pendingCount">0</p>
    </div>
    <div class="bg-white rounded-xl shadow-lg p-6 border-l-4 border-green-500">
        <p class="text-sm text-slate-600 mb-2">已通过</p>
        <p class="text-3xl font-bold text-green-600" id="approvedCount">0</p>
    </div>
    <div class="bg-white rounded-xl shadow-lg p-6 border-l-4 border-red-500">
        <p class="text-sm text-slate-600 mb-2">已拒绝</p>
        <p class="text-3xl font-bold text-red-600" id="rejectedCount">0</p>
    </div>
    <div class="bg-white rounded-xl shadow-lg p-6 border-l-4 border-blue-500">
        <p class="text-sm text-slate-600 mb-2">今日提交</p>
        <p class="text-3xl font-bold text-blue-600" id="todaySubmit">0</p>
    </div>
    <div class="bg-white rounded-xl shadow-lg p-6 border-l-4 border-purple-500">
        <p class="text-sm text-slate-600 mb-2">今日审核</p>
        <p class="text-3xl font-bold text-purple-600" id="todayReview">0</p>
    </div>
</div>

<!-- Filter Bar -->
<div class="bg-white rounded-xl shadow-lg p-6 mb-6">
    <div class="grid grid-cols-1 md:grid-cols-5 gap-4">
        <div>
            <label class="block text-sm font-semibold text-slate-700 mb-2">搜索</label>
            <input type="text" id="searchKeyword" placeholder="用户名/姓名/证件号" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
        </div>
        <div>
            <label class="block text-sm font-semibold text-slate-700 mb-2">认证类型</label>
            <select id="filterType" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                <option value="">全部</option>
                <option value="id_card">身份证</option>
                <option value="passport">护照</option>
                <option value="driver_license">驾驶证</option>
            </select>
        </div>
        <div>
            <label class="block text-sm font-semibold text-slate-700 mb-2">审核状态</label>
            <select id="filterStatus" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                <option value="">全部</option>
                <option value="pending">待审核</option>
                <option value="approved">已通过</option>
                <option value="rejected">已拒绝</option>
            </select>
        </div>
        <div>
            <label class="block text-sm font-semibold text-slate-700 mb-2">提交时间</label>
            <select id="filterTime" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                <option value="all">全部</option>
                <option value="today">今天</option>
                <option value="week">最近7天</option>
                <option value="month">最近30天</option>
            </select>
        </div>
        <div class="flex items-end">
            <button onclick="searchRecords()" class="w-full px-6 py-2 bg-gradient-to-r from-blue-500 to-indigo-600 text-white font-semibold rounded-lg hover:shadow-lg transition">
                <i class="fas fa-search mr-2"></i>搜索
            </button>
        </div>
    </div>
</div>

<!-- Authentication Records Table -->
<div class="bg-white rounded-xl shadow-lg overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full">
            <thead class="bg-gradient-to-r from-purple-500 to-pink-600 text-white">
                <tr>
                    <th class="px-6 py-4 text-left text-sm font-semibold">用户信息</th>
                    <th class="px-6 py-4 text-left text-sm font-semibold">真实姓名</th>
                    <th class="px-6 py-4 text-left text-sm font-semibold">认证类型</th>
                    <th class="px-6 py-4 text-left text-sm font-semibold">证件号码</th>
                    <th class="px-6 py-4 text-left text-sm font-semibold">提交时间</th>
                    <th class="px-6 py-4 text-left text-sm font-semibold">审核时间</th>
                    <th class="px-6 py-4 text-center text-sm font-semibold">状态</th>
                    <th class="px-6 py-4 text-center text-sm font-semibold">操作</th>
                </tr>
            </thead>
            <tbody id="recordsTable">
                <tr>
                    <td colspan="8" class="px-6 py-12 text-center">
                        <i class="fas fa-spinner fa-spin text-3xl text-slate-500 mb-3"></i>
                        <p class="text-slate-600">加载中...</p>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</div>

<!-- Detail Modal -->
<div id="detailModal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl max-w-4xl w-full max-h-[90vh] overflow-y-auto">
        <div class="sticky top-0 bg-gradient-to-r from-purple-500 to-pink-600 px-6 py-4 flex items-center justify-between">
            <h3 class="text-xl font-bold text-white">认证详情</h3>
            <button onclick="closeDetailModal()" class="text-white hover:text-slate-200">
                <i class="fas fa-times text-xl"></i>
            </button>
        </div>
        <div id="detailContent" class="p-6"></div>
    </div>
</div>

<!-- Review Modal -->
<div id="reviewModal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl max-w-2xl w-full">
        <div class="bg-gradient-to-r from-blue-500 to-indigo-600 px-6 py-4 flex items-center justify-between rounded-t-2xl">
            <h3 class="text-xl font-bold text-white">审核认证</h3>
            <button onclick="closeReviewModal()" class="text-white hover:text-slate-200">
                <i class="fas fa-times text-xl"></i>
            </button>
        </div>
        <div class="p-6">
            <input type="hidden" id="reviewId">
            <div class="mb-4">
                <label class="block text-sm font-semibold text-slate-700 mb-2">审核结果 <span class="text-red-500">*</span></label>
                <div class="flex gap-4">
                    <label class="flex items-center cursor-pointer">
                        <input type="radio" name="reviewResult" value="approved" class="mr-2">
                        <span class="text-sm text-slate-700">通过</span>
                    </label>
                    <label class="flex items-center cursor-pointer">
                        <input type="radio" name="reviewResult" value="rejected" class="mr-2">
                        <span class="text-sm text-slate-700">拒绝</span>
                    </label>
                </div>
            </div>
            <div>
                <label class="block text-sm font-semibold text-slate-700 mb-2">审核备注</label>
                <textarea id="reviewRemark" rows="4" placeholder="请输入审核意见..." class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"></textarea>
            </div>
            <div class="flex justify-end gap-3 pt-4">
                <button type="button" onclick="closeReviewModal()" class="px-6 py-2 bg-slate-200 text-slate-700 font-semibold rounded-lg hover:bg-slate-300 transition">
                    取消
                </button>
                <button type="button" onclick="submitReview()" class="px-6 py-2 bg-gradient-to-r from-blue-500 to-indigo-600 text-white font-semibold rounded-lg hover:shadow-lg transition">
                    提交审核
                </button>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    loadStats();
    loadRecords();
});

function loadStats() {
    fetch('{{ route("admin_api_authentications_stats") }}', {
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(res => res.json())
    .then(data => {
        if (data.success || data.code === 200) {
            document.getElementById('pendingCount').textContent = data.pending || 0;
            document.getElementById('approvedCount').textContent = data.approved || 0;
            document.getElementById('rejectedCount').textContent = data.rejected || 0;
            document.getElementById('todaySubmit').textContent = data.todaySubmit || 0;
            document.getElementById('todayReview').textContent = data.todayReview || 0;
        }
    })
    .catch(err => console.error('Load stats error:', err));
}

function loadRecords() {
    const keyword = document.getElementById('searchKeyword').value;
    const type = document.getElementById('filterType').value;
    const status = document.getElementById('filterStatus').value;
    const time = document.getElementById('filterTime').value;

    const params = new URLSearchParams({
        keyword: keyword,
        type: type,
        status: status,
        time: time
    });

    fetch(`{{ route('admin_api_authentications_list') }}?${params}`, {
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(res => res.json())
    .then(data => {
        if (data.success || data.code === 200) {
            renderRecords(data.records || []);
        } else {
            showError(data.message || '加载失败');
        }
    })
    .catch(err => {
        console.error('Load error:', err);
        showError('网络错误，请稍后重试');
    });
}

function renderRecords(records) {
    const table = document.getElementById('recordsTable');

    if (records.length === 0) {
        table.innerHTML = `
            <tr>
                <td colspan="8" class="px-6 py-12 text-center">
                    <i class="fas fa-inbox text-5xl text-slate-300 mb-4"></i>
                    <p class="text-slate-600">暂无认证记录</p>
                </td>
            </tr>
        `;
        return;
    }

    table.innerHTML = records.map((r, index) => `
        <tr class="border-b border-slate-200 hover:bg-slate-50 transition ${index % 2 === 0 ? 'bg-white' : 'bg-slate-50'}">
            <td class="px-6 py-4">
                <div>
                    <p class="text-sm font-semibold text-slate-800">${r.username || 'N/A'}</p>
                    <p class="text-xs text-slate-500">${r.email || '-'}</p>
                </div>
            </td>
            <td class="px-6 py-4">
                <span class="text-sm font-semibold text-slate-800">${r.real_name || '-'}</span>
            </td>
            <td class="px-6 py-4">
                ${getTypeBadge(r.type)}
            </td>
            <td class="px-6 py-4">
                <span class="text-sm font-mono text-slate-800">${maskIdNumber(r.id_number)}</span>
            </td>
            <td class="px-6 py-4">
                <span class="text-sm text-slate-600">${r.submit_time || '-'}</span>
            </td>
            <td class="px-6 py-4">
                <span class="text-sm text-slate-600">${r.review_time || '-'}</span>
            </td>
            <td class="px-6 py-4 text-center">
                ${getStatusBadge(r.status)}
            </td>
            <td class="px-6 py-4">
                <div class="flex items-center justify-center gap-2">
                    <button onclick="viewDetail(${r.id})" class="px-3 py-1 bg-blue-100 text-blue-700 text-xs font-semibold rounded hover:bg-blue-200 transition">
                        <i class="fas fa-eye"></i>
                    </button>
                    ${r.status === 'pending' ? `
                    <button onclick="openReviewModal(${r.id})" class="px-3 py-1 bg-green-100 text-green-700 text-xs font-semibold rounded hover:bg-green-200 transition">
                        <i class="fas fa-check"></i>
                    </button>
                    ` : ''}
                </div>
            </td>
        </tr>
    `).join('');
}

function getTypeBadge(type) {
    const badges = {
        'id_card': '<span class="px-2 py-1 bg-blue-100 text-blue-700 text-xs font-semibold rounded-full"><i class="fas fa-id-card mr-1"></i>身份证</span>',
        'passport': '<span class="px-2 py-1 bg-purple-100 text-purple-700 text-xs font-semibold rounded-full"><i class="fas fa-passport mr-1"></i>护照</span>',
        'driver_license': '<span class="px-2 py-1 bg-green-100 text-green-700 text-xs font-semibold rounded-full"><i class="fas fa-id-badge mr-1"></i>驾驶证</span>'
    };
    return badges[type] || '<span class="px-2 py-1 bg-slate-100 text-slate-700 text-xs font-semibold rounded-full">其他</span>';
}

function getStatusBadge(status) {
    const badges = {
        'pending': '<span class="px-3 py-1 bg-yellow-100 text-yellow-700 text-xs font-semibold rounded-full"><i class="fas fa-clock mr-1"></i>待审核</span>',
        'approved': '<span class="px-3 py-1 bg-green-100 text-green-700 text-xs font-semibold rounded-full"><i class="fas fa-check-circle mr-1"></i>已通过</span>',
        'rejected': '<span class="px-3 py-1 bg-red-100 text-red-700 text-xs font-semibold rounded-full"><i class="fas fa-times-circle mr-1"></i>已拒绝</span>'
    };
    return badges[status] || '<span class="px-3 py-1 bg-slate-100 text-slate-700 text-xs font-semibold rounded-full">未知</span>';
}

function maskIdNumber(idNumber) {
    if (!idNumber) return '-';
    const len = idNumber.length;
    if (len <= 8) return idNumber;
    return idNumber.substring(0, 4) + '****' + idNumber.substring(len - 4);
}

function viewDetail(id) {
    fetch(`{{ route('admin_api_authentications_detail', ['id' => '__ID__']) }}`.replace('__ID__', id), {
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(res => res.json())
    .then(data => {
        if (data.success && data.record) {
            const r = data.record;

            document.getElementById('detailContent').innerHTML = `
                <div class="mb-6">
                    <h4 class="text-lg font-bold text-slate-800 mb-4">基本信息</h4>
                    <div class="grid grid-cols-2 gap-4">
                        <div class="bg-slate-50 rounded-lg p-4">
                            <p class="text-xs text-slate-600 mb-1">用户名</p>
                            <p class="text-sm font-semibold text-slate-800">${r.username}</p>
                        </div>
                        <div class="bg-slate-50 rounded-lg p-4">
                            <p class="text-xs text-slate-600 mb-1">真实姓名</p>
                            <p class="text-sm font-semibold text-slate-800">${r.real_name}</p>
                        </div>
                        <div class="bg-slate-50 rounded-lg p-4">
                            <p class="text-xs text-slate-600 mb-1">认证类型</p>
                            ${getTypeBadge(r.type)}
                        </div>
                        <div class="bg-slate-50 rounded-lg p-4">
                            <p class="text-xs text-slate-600 mb-1">证件号码</p>
                            <p class="text-sm font-mono font-semibold text-slate-800">${r.id_number}</p>
                        </div>
                        <div class="bg-slate-50 rounded-lg p-4">
                            <p class="text-xs text-slate-600 mb-1">手机号</p>
                            <p class="text-sm font-semibold text-slate-800">${r.phone || '-'}</p>
                        </div>
                        <div class="bg-slate-50 rounded-lg p-4">
                            <p class="text-xs text-slate-600 mb-1">邮箱</p>
                            <p class="text-sm font-semibold text-slate-800">${r.email || '-'}</p>
                        </div>
                        <div class="bg-slate-50 rounded-lg p-4">
                            <p class="text-xs text-slate-600 mb-1">提交时间</p>
                            <p class="text-sm font-semibold text-slate-800">${r.submit_time}</p>
                        </div>
                        <div class="bg-slate-50 rounded-lg p-4">
                            <p class="text-xs text-slate-600 mb-1">状态</p>
                            ${getStatusBadge(r.status)}
                        </div>
                    </div>
                </div>

                <div class="mb-6">
                    <h4 class="text-lg font-bold text-slate-800 mb-4">证件照片</h4>
                    <div class="grid grid-cols-2 gap-4">
                        ${r.front_image ? `
                        <div class="bg-slate-50 rounded-lg p-4">
                            <p class="text-xs text-slate-600 mb-2">正面照</p>
                            <img src="${r.front_image}" alt="正面照" class="w-full rounded-lg cursor-pointer" onclick="viewImage('${r.front_image}')">
                        </div>
                        ` : ''}
                        ${r.back_image ? `
                        <div class="bg-slate-50 rounded-lg p-4">
                            <p class="text-xs text-slate-600 mb-2">背面照</p>
                            <img src="${r.back_image}" alt="背面照" class="w-full rounded-lg cursor-pointer" onclick="viewImage('${r.back_image}')">
                        </div>
                        ` : ''}
                        ${r.hold_image ? `
                        <div class="bg-slate-50 rounded-lg p-4">
                            <p class="text-xs text-slate-600 mb-2">手持照</p>
                            <img src="${r.hold_image}" alt="手持照" class="w-full rounded-lg cursor-pointer" onclick="viewImage('${r.hold_image}')">
                        </div>
                        ` : ''}
                    </div>
                </div>

                ${r.status !== 'pending' ? `
                <div>
                    <h4 class="text-lg font-bold text-slate-800 mb-4">审核信息</h4>
                    <div class="grid grid-cols-2 gap-4">
                        <div class="bg-slate-50 rounded-lg p-4">
                            <p class="text-xs text-slate-600 mb-1">审核人</p>
                            <p class="text-sm font-semibold text-slate-800">${r.reviewer || '-'}</p>
                        </div>
                        <div class="bg-slate-50 rounded-lg p-4">
                            <p class="text-xs text-slate-600 mb-1">审核时间</p>
                            <p class="text-sm font-semibold text-slate-800">${r.review_time || '-'}</p>
                        </div>
                        ${r.review_remark ? `
                        <div class="bg-slate-50 rounded-lg p-4 col-span-2">
                            <p class="text-xs text-slate-600 mb-1">审核备注</p>
                            <p class="text-sm text-slate-800">${r.review_remark}</p>
                        </div>
                        ` : ''}
                    </div>
                </div>
                ` : ''}
            `;
            document.getElementById('detailModal').classList.remove('hidden');
        }
    })
    .catch(err => console.error('Load detail error:', err));
}

function viewImage(src) {
    window.open(src, '_blank');
}

function closeDetailModal() {
    document.getElementById('detailModal').classList.add('hidden');
}

function openReviewModal(id) {
    document.getElementById('reviewId').value = id;
    document.getElementById('reviewRemark').value = '';
    document.querySelectorAll('input[name="reviewResult"]').forEach(r => r.checked = false);
    document.getElementById('reviewModal').classList.remove('hidden');
}

function closeReviewModal() {
    document.getElementById('reviewModal').classList.add('hidden');
}

function submitReview() {
    const id = document.getElementById('reviewId').value;
    const result = document.querySelector('input[name="reviewResult"]:checked');
    const remark = document.getElementById('reviewRemark').value.trim();

    if (!result) {
        alert('请选择审核结果');
        return;
    }

    fetch(`{{ route('admin_api_authentications_review', ['id' => '__ID__']) }}`.replace('__ID__', id), {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: JSON.stringify({
            result: result.value,
            remark: remark
        })
    })
    .then(res => res.json())
    .then(data => {
        if (data.success || data.code === 200) {
            alert('审核成功');
            closeReviewModal();
            loadStats();
            loadRecords();
        } else {
            alert(data.message || '审核失败');
        }
    })
    .catch(err => {
        console.error('Review error:', err);
        alert('网络错误，请稍后重试');
    });
}

function exportRecords() {
    const keyword = document.getElementById('searchKeyword').value;
    const type = document.getElementById('filterType').value;
    const status = document.getElementById('filterStatus').value;
    const time = document.getElementById('filterTime').value;

    const params = new URLSearchParams({
        keyword: keyword,
        type: type,
        status: status,
        time: time,
        export: 1
    });

    window.location.href = `{{ route('admin_api_authentications_export') }}?${params}`;
}

function refreshData() {
    loadStats();
    loadRecords();
}

function searchRecords() {
    loadRecords();
}

function showError(message) {
    document.getElementById('recordsTable').innerHTML = `
        <tr>
            <td colspan="8" class="px-6 py-12 text-center">
                <i class="fas fa-exclamation-circle text-5xl text-red-500 mb-4"></i>
                <p class="text-red-600">${message}</p>
            </td>
        </tr>
    `;
}
</script>
@endsection
