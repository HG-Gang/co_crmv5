@extends('admin-tailwind.layouts.app')

@section('title', '注销申请管理 - 管理后台')

@section('content')
<!-- Page Header -->
<div class="flex items-center justify-between mb-8">
    <div>
        <h1 class="text-3xl font-bold text-slate-800">注销申请管理</h1>
        <p class="text-slate-600 mt-2">审核和处理用户账户注销申请</p>
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
        <p class="text-sm text-slate-600 mb-2">已同意</p>
        <p class="text-3xl font-bold text-green-600" id="approvedCount">0</p>
    </div>
    <div class="bg-white rounded-xl shadow-lg p-6 border-l-4 border-red-500">
        <p class="text-sm text-slate-600 mb-2">已拒绝</p>
        <p class="text-3xl font-bold text-red-600" id="rejectedCount">0</p>
    </div>
    <div class="bg-white rounded-xl shadow-lg p-6 border-l-4 border-blue-500">
        <p class="text-sm text-slate-600 mb-2">今日申请</p>
        <p class="text-3xl font-bold text-blue-600" id="todayApply">0</p>
    </div>
    <div class="bg-white rounded-xl shadow-lg p-6 border-l-4 border-purple-500">
        <p class="text-sm text-slate-600 mb-2">今日处理</p>
        <p class="text-3xl font-bold text-purple-600" id="todayProcess">0</p>
    </div>
</div>

<!-- Filter Bar -->
<div class="bg-white rounded-xl shadow-lg p-6 mb-6">
    <div class="grid grid-cols-1 md:grid-cols-5 gap-4">
        <div>
            <label class="block text-sm font-semibold text-slate-700 mb-2">搜索</label>
            <input type="text" id="searchKeyword" placeholder="用户名/手机/邮箱" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
        </div>
        <div>
            <label class="block text-sm font-semibold text-slate-700 mb-2">申请状态</label>
            <select id="filterStatus" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                <option value="">全部</option>
                <option value="pending">待审核</option>
                <option value="approved">已同意</option>
                <option value="rejected">已拒绝</option>
                <option value="cancelled">已取消</option>
            </select>
        </div>
        <div>
            <label class="block text-sm font-semibold text-slate-700 mb-2">注销原因</label>
            <select id="filterReason" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                <option value="">全部</option>
                <option value="no_use">不再使用</option>
                <option value="privacy">隐私考虑</option>
                <option value="switch">切换平台</option>
                <option value="other">其他原因</option>
            </select>
        </div>
        <div>
            <label class="block text-sm font-semibold text-slate-700 mb-2">申请时间</label>
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

<!-- Cancel Applications Table -->
<div class="bg-white rounded-xl shadow-lg overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full">
            <thead class="bg-gradient-to-r from-orange-500 to-red-600 text-white">
                <tr>
                    <th class="px-6 py-4 text-left text-sm font-semibold">用户信息</th>
                    <th class="px-6 py-4 text-left text-sm font-semibold">MT4账号</th>
                    <th class="px-6 py-4 text-left text-sm font-semibold">账户余额</th>
                    <th class="px-6 py-4 text-left text-sm font-semibold">注销原因</th>
                    <th class="px-6 py-4 text-left text-sm font-semibold">申请时间</th>
                    <th class="px-6 py-4 text-left text-sm font-semibold">处理时间</th>
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
        <div class="sticky top-0 bg-gradient-to-r from-orange-500 to-red-600 px-6 py-4 flex items-center justify-between">
            <h3 class="text-xl font-bold text-white">注销申请详情</h3>
            <button onclick="closeDetailModal()" class="text-white hover:text-slate-200">
                <i class="fas fa-times text-xl"></i>
            </button>
        </div>
        <div id="detailContent" class="p-6"></div>
    </div>
</div>

<!-- Process Modal -->
<div id="processModal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl max-w-2xl w-full">
        <div class="bg-gradient-to-r from-blue-500 to-indigo-600 px-6 py-4 flex items-center justify-between rounded-t-2xl">
            <h3 class="text-xl font-bold text-white">处理注销申请</h3>
            <button onclick="closeProcessModal()" class="text-white hover:text-slate-200">
                <i class="fas fa-times text-xl"></i>
            </button>
        </div>
        <div class="p-6">
            <input type="hidden" id="processId">
            <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4 mb-4">
                <div class="flex items-start gap-3">
                    <i class="fas fa-exclamation-triangle text-yellow-600 text-xl"></i>
                    <div>
                        <p class="text-sm font-semibold text-yellow-800">注意事项</p>
                        <ul class="text-xs text-yellow-700 mt-2 space-y-1">
                            <li>• 同意注销前请确认用户账户余额已处理完毕</li>
                            <li>• 注销后用户数据将被删除且无法恢复</li>
                            <li>• 如有未结订单或未处理资金，请先拒绝申请</li>
                        </ul>
                    </div>
                </div>
            </div>
            <div class="mb-4">
                <label class="block text-sm font-semibold text-slate-700 mb-2">处理结果 <span class="text-red-500">*</span></label>
                <div class="flex gap-4">
                    <label class="flex items-center cursor-pointer">
                        <input type="radio" name="processResult" value="approved" class="mr-2">
                        <span class="text-sm text-slate-700">同意注销</span>
                    </label>
                    <label class="flex items-center cursor-pointer">
                        <input type="radio" name="processResult" value="rejected" class="mr-2">
                        <span class="text-sm text-slate-700">拒绝注销</span>
                    </label>
                </div>
            </div>
            <div>
                <label class="block text-sm font-semibold text-slate-700 mb-2">处理备注</label>
                <textarea id="processRemark" rows="4" placeholder="请输入处理说明..." class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"></textarea>
            </div>
            <div class="flex justify-end gap-3 pt-4">
                <button type="button" onclick="closeProcessModal()" class="px-6 py-2 bg-slate-200 text-slate-700 font-semibold rounded-lg hover:bg-slate-300 transition">
                    取消
                </button>
                <button type="button" onclick="submitProcess()" class="px-6 py-2 bg-gradient-to-r from-blue-500 to-indigo-600 text-white font-semibold rounded-lg hover:shadow-lg transition">
                    提交处理
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
    fetch('{{ route("admin_api_cancel_applies_stats") }}', {
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(res => res.json())
    .then(data => {
        if (data.success || data.code === 200) {
            document.getElementById('pendingCount').textContent = data.pending || 0;
            document.getElementById('approvedCount').textContent = data.approved || 0;
            document.getElementById('rejectedCount').textContent = data.rejected || 0;
            document.getElementById('todayApply').textContent = data.todayApply || 0;
            document.getElementById('todayProcess').textContent = data.todayProcess || 0;
        }
    })
    .catch(err => console.error('Load stats error:', err));
}

function loadRecords() {
    const keyword = document.getElementById('searchKeyword').value;
    const status = document.getElementById('filterStatus').value;
    const reason = document.getElementById('filterReason').value;
    const time = document.getElementById('filterTime').value;

    const params = new URLSearchParams({
        keyword: keyword,
        status: status,
        reason: reason,
        time: time
    });

    fetch(`{{ route('admin_api_cancel_applies_list') }}?${params}`, {
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
                    <p class="text-slate-600">暂无注销申请</p>
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
                    <p class="text-xs text-slate-500">${r.phone || r.email || '-'}</p>
                </div>
            </td>
            <td class="px-6 py-4">
                <span class="text-sm font-mono font-semibold text-slate-800">${r.mt4_account || '-'}</span>
            </td>
            <td class="px-6 py-4">
                <span class="text-sm font-bold ${parseFloat(r.balance) > 0 ? 'text-orange-600' : 'text-slate-600'}">${formatMoney(r.balance)}</span>
            </td>
            <td class="px-6 py-4">
                <div>
                    ${getReasonBadge(r.reason)}
                    ${r.reason_detail ? `<p class="text-xs text-slate-500 mt-1">${r.reason_detail}</p>` : ''}
                </div>
            </td>
            <td class="px-6 py-4">
                <span class="text-sm text-slate-600">${r.apply_time || '-'}</span>
            </td>
            <td class="px-6 py-4">
                <span class="text-sm text-slate-600">${r.process_time || '-'}</span>
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
                    <button onclick="openProcessModal(${r.id})" class="px-3 py-1 bg-green-100 text-green-700 text-xs font-semibold rounded hover:bg-green-200 transition">
                        <i class="fas fa-check"></i>
                    </button>
                    ` : ''}
                </div>
            </td>
        </tr>
    `).join('');
}

function getReasonBadge(reason) {
    const badges = {
        'no_use': '<span class="px-2 py-1 bg-slate-100 text-slate-700 text-xs font-semibold rounded-full">不再使用</span>',
        'privacy': '<span class="px-2 py-1 bg-purple-100 text-purple-700 text-xs font-semibold rounded-full">隐私考虑</span>',
        'switch': '<span class="px-2 py-1 bg-blue-100 text-blue-700 text-xs font-semibold rounded-full">切换平台</span>',
        'other': '<span class="px-2 py-1 bg-orange-100 text-orange-700 text-xs font-semibold rounded-full">其他原因</span>'
    };
    return badges[reason] || '<span class="px-2 py-1 bg-slate-100 text-slate-700 text-xs font-semibold rounded-full">未分类</span>';
}

function getStatusBadge(status) {
    const badges = {
        'pending': '<span class="px-3 py-1 bg-yellow-100 text-yellow-700 text-xs font-semibold rounded-full"><i class="fas fa-clock mr-1"></i>待审核</span>',
        'approved': '<span class="px-3 py-1 bg-green-100 text-green-700 text-xs font-semibold rounded-full"><i class="fas fa-check-circle mr-1"></i>已同意</span>',
        'rejected': '<span class="px-3 py-1 bg-red-100 text-red-700 text-xs font-semibold rounded-full"><i class="fas fa-times-circle mr-1"></i>已拒绝</span>',
        'cancelled': '<span class="px-3 py-1 bg-slate-100 text-slate-700 text-xs font-semibold rounded-full"><i class="fas fa-ban mr-1"></i>已取消</span>'
    };
    return badges[status] || '<span class="px-3 py-1 bg-slate-100 text-slate-700 text-xs font-semibold rounded-full">未知</span>';
}

function formatMoney(amount) {
    if (!amount) return '$0.00';
    return new Intl.NumberFormat('en-US', {
        style: 'currency',
        currency: 'USD',
        minimumFractionDigits: 2
    }).format(amount);
}

function viewDetail(id) {
    fetch(`{{ route('admin_api_cancel_applies_detail', ['id' => '__ID__']) }}`.replace('__ID__', id), {
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(res => res.json())
    .then(data => {
        if (data.success && data.record) {
            const r = data.record;

            document.getElementById('detailContent').innerHTML = `
                <div class="mb-6">
                    <h4 class="text-lg font-bold text-slate-800 mb-4">申请信息</h4>
                    <div class="grid grid-cols-2 gap-4">
                        <div class="bg-slate-50 rounded-lg p-4">
                            <p class="text-xs text-slate-600 mb-1">用户名</p>
                            <p class="text-sm font-semibold text-slate-800">${r.username}</p>
                        </div>
                        <div class="bg-slate-50 rounded-lg p-4">
                            <p class="text-xs text-slate-600 mb-1">MT4账号</p>
                            <p class="text-sm font-mono font-semibold text-slate-800">${r.mt4_account || '-'}</p>
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
                            <p class="text-xs text-slate-600 mb-1">账户余额</p>
                            <p class="text-sm font-bold ${parseFloat(r.balance) > 0 ? 'text-orange-600' : 'text-slate-800'}">${formatMoney(r.balance)}</p>
                        </div>
                        <div class="bg-slate-50 rounded-lg p-4">
                            <p class="text-xs text-slate-600 mb-1">未结订单</p>
                            <p class="text-sm font-bold ${parseInt(r.open_orders) > 0 ? 'text-red-600' : 'text-slate-800'}">${r.open_orders || 0}</p>
                        </div>
                        <div class="bg-slate-50 rounded-lg p-4">
                            <p class="text-xs text-slate-600 mb-1">注销原因</p>
                            ${getReasonBadge(r.reason)}
                        </div>
                        <div class="bg-slate-50 rounded-lg p-4">
                            <p class="text-xs text-slate-600 mb-1">申请时间</p>
                            <p class="text-sm font-semibold text-slate-800">${r.apply_time}</p>
                        </div>
                        ${r.reason_detail ? `
                        <div class="bg-slate-50 rounded-lg p-4 col-span-2">
                            <p class="text-xs text-slate-600 mb-1">详细原因</p>
                            <p class="text-sm text-slate-800">${r.reason_detail}</p>
                        </div>
                        ` : ''}
                    </div>
                </div>

                ${r.status !== 'pending' ? `
                <div class="mb-6">
                    <h4 class="text-lg font-bold text-slate-800 mb-4">处理信息</h4>
                    <div class="grid grid-cols-2 gap-4">
                        <div class="bg-slate-50 rounded-lg p-4">
                            <p class="text-xs text-slate-600 mb-1">处理状态</p>
                            ${getStatusBadge(r.status)}
                        </div>
                        <div class="bg-slate-50 rounded-lg p-4">
                            <p class="text-xs text-slate-600 mb-1">处理人</p>
                            <p class="text-sm font-semibold text-slate-800">${r.processor || '-'}</p>
                        </div>
                        <div class="bg-slate-50 rounded-lg p-4">
                            <p class="text-xs text-slate-600 mb-1">处理时间</p>
                            <p class="text-sm font-semibold text-slate-800">${r.process_time || '-'}</p>
                        </div>
                        <div class="bg-slate-50 rounded-lg p-4">
                            <p class="text-xs text-slate-600 mb-1">处理耗时</p>
                            <p class="text-sm font-semibold text-slate-800">${r.process_duration || '-'}</p>
                        </div>
                        ${r.process_remark ? `
                        <div class="bg-slate-50 rounded-lg p-4 col-span-2">
                            <p class="text-xs text-slate-600 mb-1">处理备注</p>
                            <p class="text-sm text-slate-800">${r.process_remark}</p>
                        </div>
                        ` : ''}
                    </div>
                </div>
                ` : ''}

                <div>
                    <h4 class="text-lg font-bold text-slate-800 mb-4">账户统计</h4>
                    <div class="grid grid-cols-3 gap-4">
                        <div class="bg-slate-50 rounded-lg p-4">
                            <p class="text-xs text-slate-600 mb-1">注册时长</p>
                            <p class="text-sm font-semibold text-slate-800">${r.registered_days || 0}天</p>
                        </div>
                        <div class="bg-slate-50 rounded-lg p-4">
                            <p class="text-xs text-slate-600 mb-1">总入金</p>
                            <p class="text-sm font-semibold text-slate-800">${formatMoney(r.total_deposit)}</p>
                        </div>
                        <div class="bg-slate-50 rounded-lg p-4">
                            <p class="text-xs text-slate-600 mb-1">总出金</p>
                            <p class="text-sm font-semibold text-slate-800">${formatMoney(r.total_withdraw)}</p>
                        </div>
                        <div class="bg-slate-50 rounded-lg p-4">
                            <p class="text-xs text-slate-600 mb-1">总交易笔数</p>
                            <p class="text-sm font-semibold text-slate-800">${r.total_trades || 0}</p>
                        </div>
                        <div class="bg-slate-50 rounded-lg p-4">
                            <p class="text-xs text-slate-600 mb-1">总盈亏</p>
                            <p class="text-sm font-bold ${parseFloat(r.total_profit) >= 0 ? 'text-green-600' : 'text-red-600'}">${formatMoney(r.total_profit)}</p>
                        </div>
                        <div class="bg-slate-50 rounded-lg p-4">
                            <p class="text-xs text-slate-600 mb-1">最后登录</p>
                            <p class="text-sm font-semibold text-slate-800">${r.last_login || '-'}</p>
                        </div>
                    </div>
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

function openProcessModal(id) {
    document.getElementById('processId').value = id;
    document.getElementById('processRemark').value = '';
    document.querySelectorAll('input[name="processResult"]').forEach(r => r.checked = false);
    document.getElementById('processModal').classList.remove('hidden');
}

function closeProcessModal() {
    document.getElementById('processModal').classList.add('hidden');
}

function submitProcess() {
    const id = document.getElementById('processId').value;
    const result = document.querySelector('input[name="processResult"]:checked');
    const remark = document.getElementById('processRemark').value.trim();

    if (!result) {
        alert('请选择处理结果');
        return;
    }

    if (result.value === 'approved' && !confirm('确定要同意此注销申请吗？注销后用户数据将被永久删除！')) {
        return;
    }

    fetch(`{{ route('admin_api_cancel_applies_process', ['id' => '__ID__']) }}`.replace('__ID__', id), {
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
            alert('处理成功');
            closeProcessModal();
            loadStats();
            loadRecords();
        } else {
            alert(data.message || '处理失败');
        }
    })
    .catch(err => {
        console.error('Process error:', err);
        alert('网络错误，请稍后重试');
    });
}

function exportRecords() {
    const keyword = document.getElementById('searchKeyword').value;
    const status = document.getElementById('filterStatus').value;
    const reason = document.getElementById('filterReason').value;
    const time = document.getElementById('filterTime').value;

    const params = new URLSearchParams({
        keyword: keyword,
        status: status,
        reason: reason,
        time: time,
        export: 1
    });

    window.location.href = `{{ route('admin_api_cancel_applies_export') }}?${params}`;
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
