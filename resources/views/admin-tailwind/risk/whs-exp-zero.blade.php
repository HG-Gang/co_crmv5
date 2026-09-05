@extends('admin-tailwind.layouts.app')

@section('title', 'WHS到期清零 - 管理后台')

@section('content')
<!-- Page Header -->
<div class="flex items-center justify-between mb-8">
    <div>
        <h1 class="text-3xl font-bold text-slate-800">WHS到期清零</h1>
        <p class="text-slate-600 mt-2">管理和执行仓储费到期账户的自动清零操作</p>
    </div>
    <div class="flex gap-3">
        <button onclick="refreshData()" class="px-6 py-3 bg-gradient-to-r from-blue-500 to-indigo-600 text-white font-semibold rounded-lg hover:shadow-lg transition">
            <i class="fas fa-sync-alt mr-2"></i>刷新
        </button>
        <button onclick="openExecuteModal()" class="px-6 py-3 bg-gradient-to-r from-orange-500 to-red-600 text-white font-semibold rounded-lg hover:shadow-lg transition">
            <i class="fas fa-play mr-2"></i>执行清零
        </button>
    </div>
</div>

<!-- Stats Cards -->
<div class="grid grid-cols-1 md:grid-cols-5 gap-6 mb-6">
    <div class="bg-white rounded-xl shadow-lg p-6 border-l-4 border-orange-500">
        <p class="text-sm text-slate-600 mb-2">待清零账户</p>
        <p class="text-3xl font-bold text-orange-600" id="pendingCount">0</p>
    </div>
    <div class="bg-white rounded-xl shadow-lg p-6 border-l-4 border-red-500">
        <p class="text-sm text-slate-600 mb-2">今日已清零</p>
        <p class="text-3xl font-bold text-red-600" id="todayCleared">0</p>
    </div>
    <div class="bg-white rounded-xl shadow-lg p-6 border-l-4 border-purple-500">
        <p class="text-sm text-slate-600 mb-2">本月已清零</p>
        <p class="text-3xl font-bold text-purple-600" id="monthCleared">0</p>
    </div>
    <div class="bg-white rounded-xl shadow-lg p-6 border-l-4 border-blue-500">
        <p class="text-sm text-slate-600 mb-2">今日清零金额</p>
        <p class="text-3xl font-bold text-blue-600" id="todayAmount">$0</p>
    </div>
    <div class="bg-white rounded-xl shadow-lg p-6 border-l-4 border-green-500">
        <p class="text-sm text-slate-600 mb-2">累计清零金额</p>
        <p class="text-3xl font-bold text-green-600" id="totalAmount">$0</p>
    </div>
</div>

<!-- Filter Bar -->
<div class="bg-white rounded-xl shadow-lg p-6 mb-6">
    <div class="grid grid-cols-1 md:grid-cols-5 gap-4">
        <div>
            <label class="block text-sm font-semibold text-slate-700 mb-2">搜索</label>
            <input type="text" id="searchKeyword" placeholder="用户名/MT4账号" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
        </div>
        <div>
            <label class="block text-sm font-semibold text-slate-700 mb-2">状态</label>
            <select id="filterStatus" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                <option value="">全部</option>
                <option value="pending">待清零</option>
                <option value="cleared">已清零</option>
                <option value="exempted">已豁免</option>
            </select>
        </div>
        <div>
            <label class="block text-sm font-semibold text-slate-700 mb-2">到期时间</label>
            <select id="filterExpire" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                <option value="all">全部</option>
                <option value="expired">已到期</option>
                <option value="today">今天到期</option>
                <option value="week">7天内到期</option>
            </select>
        </div>
        <div>
            <label class="block text-sm font-semibold text-slate-700 mb-2">余额范围</label>
            <select id="filterBalance" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                <option value="all">全部</option>
                <option value="small">小于$100</option>
                <option value="medium">$100-$1000</option>
                <option value="large">大于$1000</option>
            </select>
        </div>
        <div class="flex items-end">
            <button onclick="searchRecords()" class="w-full px-6 py-2 bg-gradient-to-r from-blue-500 to-indigo-600 text-white font-semibold rounded-lg hover:shadow-lg transition">
                <i class="fas fa-search mr-2"></i>搜索
            </button>
        </div>
    </div>
</div>

<!-- WHS Records Table -->
<div class="bg-white rounded-xl shadow-lg overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full">
            <thead class="bg-gradient-to-r from-orange-500 to-red-600 text-white">
                <tr>
                    <th class="px-6 py-4 text-left text-sm font-semibold">用户信息</th>
                    <th class="px-6 py-4 text-left text-sm font-semibold">MT4账号</th>
                    <th class="px-6 py-4 text-left text-sm font-semibold">账户余额</th>
                    <th class="px-6 py-4 text-left text-sm font-semibold">未平仓手数</th>
                    <th class="px-6 py-4 text-left text-sm font-semibold">到期时间</th>
                    <th class="px-6 py-4 text-left text-sm font-semibold">清零时间</th>
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
            <h3 class="text-xl font-bold text-white">WHS详情</h3>
            <button onclick="closeDetailModal()" class="text-white hover:text-slate-200">
                <i class="fas fa-times text-xl"></i>
            </button>
        </div>
        <div id="detailContent" class="p-6"></div>
    </div>
</div>

<!-- Execute Modal -->
<div id="executeModal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl max-w-2xl w-full">
        <div class="bg-gradient-to-r from-orange-500 to-red-600 px-6 py-4 flex items-center justify-between rounded-t-2xl">
            <h3 class="text-xl font-bold text-white">执行清零操作</h3>
            <button onclick="closeExecuteModal()" class="text-white hover:text-slate-200">
                <i class="fas fa-times text-xl"></i>
            </button>
        </div>
        <div class="p-6">
            <div class="bg-red-50 border border-red-200 rounded-lg p-4 mb-4">
                <div class="flex items-start gap-3">
                    <i class="fas fa-exclamation-triangle text-red-600 text-xl"></i>
                    <div>
                        <p class="text-sm font-semibold text-red-800">危险操作警告</p>
                        <ul class="text-xs text-red-700 mt-2 space-y-1">
                            <li>• 此操作将清零所有已到期账户的余额和未平仓订单</li>
                            <li>• 清零操作不可逆转，请务必确认</li>
                            <li>• 建议在非交易高峰期执行此操作</li>
                            <li>• 执行前请确认已通知相关用户</li>
                        </ul>
                    </div>
                </div>
            </div>
            <div class="mb-4">
                <label class="block text-sm font-semibold text-slate-700 mb-2">清零范围 <span class="text-red-500">*</span></label>
                <select id="executeScope" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                    <option value="expired">仅已到期账户</option>
                    <option value="today">今天到期账户</option>
                    <option value="all_pending">所有待清零账户</option>
                </select>
            </div>
            <div class="mb-4">
                <label class="block text-sm font-semibold text-slate-700 mb-2">执行方式</label>
                <div class="flex gap-4">
                    <label class="flex items-center cursor-pointer">
                        <input type="radio" name="executeMode" value="auto" checked class="mr-2">
                        <span class="text-sm text-slate-700">自动执行</span>
                    </label>
                    <label class="flex items-center cursor-pointer">
                        <input type="radio" name="executeMode" value="preview" class="mr-2">
                        <span class="text-sm text-slate-700">预览模式（不实际执行）</span>
                    </label>
                </div>
            </div>
            <div>
                <label class="block text-sm font-semibold text-slate-700 mb-2">操作备注</label>
                <textarea id="executeRemark" rows="3" placeholder="请输入操作备注..." class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"></textarea>
            </div>
            <div class="flex justify-end gap-3 pt-4">
                <button type="button" onclick="closeExecuteModal()" class="px-6 py-2 bg-slate-200 text-slate-700 font-semibold rounded-lg hover:bg-slate-300 transition">
                    取消
                </button>
                <button type="button" onclick="submitExecute()" class="px-6 py-2 bg-gradient-to-r from-orange-500 to-red-600 text-white font-semibold rounded-lg hover:shadow-lg transition">
                    确认执行
                </button>
            </div>
        </div>
    </div>
</div>

<script>
let autoRefreshInterval = null;

document.addEventListener('DOMContentLoaded', function() {
    loadStats();
    loadRecords();

    // Auto refresh every 60 seconds
    autoRefreshInterval = setInterval(() => {
        loadStats();
        loadRecords();
    }, 60000);
});

function loadStats() {
    fetch('{{ route("admin_api_whs_exp_zero_stats") }}', {
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(res => res.json())
    .then(data => {
        if (data.success || data.code === 200) {
            document.getElementById('pendingCount').textContent = data.pending || 0;
            document.getElementById('todayCleared').textContent = data.todayCleared || 0;
            document.getElementById('monthCleared').textContent = data.monthCleared || 0;
            document.getElementById('todayAmount').textContent = formatMoney(data.todayAmount || 0);
            document.getElementById('totalAmount').textContent = formatMoney(data.totalAmount || 0);
        }
    })
    .catch(err => console.error('Load stats error:', err));
}

function loadRecords() {
    const keyword = document.getElementById('searchKeyword').value;
    const status = document.getElementById('filterStatus').value;
    const expire = document.getElementById('filterExpire').value;
    const balance = document.getElementById('filterBalance').value;

    const params = new URLSearchParams({
        keyword: keyword,
        status: status,
        expire: expire,
        balance: balance
    });

    fetch(`{{ route('admin_api_whs_exp_zero_list') }}?${params}`, {
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
                    <p class="text-slate-600">暂无WHS记录</p>
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
                <span class="text-sm font-mono font-semibold text-slate-800">${r.mt4_account || '-'}</span>
            </td>
            <td class="px-6 py-4">
                <span class="text-sm font-bold text-slate-800">${formatMoney(r.balance)}</span>
            </td>
            <td class="px-6 py-4">
                <span class="text-sm font-semibold ${parseFloat(r.open_lots) > 0 ? 'text-orange-600' : 'text-slate-600'}">${r.open_lots || '0.00'}</span>
            </td>
            <td class="px-6 py-4">
                <span class="text-sm ${isExpired(r.expire_time) ? 'text-red-600 font-semibold' : 'text-slate-600'}">${r.expire_time || '-'}</span>
            </td>
            <td class="px-6 py-4">
                <span class="text-sm text-slate-600">${r.cleared_time || '-'}</span>
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
                    <button onclick="clearSingle(${r.id}, '${r.mt4_account}')" class="px-3 py-1 bg-orange-100 text-orange-700 text-xs font-semibold rounded hover:bg-orange-200 transition">
                        <i class="fas fa-eraser"></i>
                    </button>
                    <button onclick="exemptAccount(${r.id}, '${r.mt4_account}')" class="px-3 py-1 bg-green-100 text-green-700 text-xs font-semibold rounded hover:bg-green-200 transition">
                        <i class="fas fa-shield-alt"></i>
                    </button>
                    ` : ''}
                </div>
            </td>
        </tr>
    `).join('');
}

function getStatusBadge(status) {
    const badges = {
        'pending': '<span class="px-3 py-1 bg-orange-100 text-orange-700 text-xs font-semibold rounded-full"><i class="fas fa-clock mr-1"></i>待清零</span>',
        'cleared': '<span class="px-3 py-1 bg-red-100 text-red-700 text-xs font-semibold rounded-full"><i class="fas fa-times-circle mr-1"></i>已清零</span>',
        'exempted': '<span class="px-3 py-1 bg-green-100 text-green-700 text-xs font-semibold rounded-full"><i class="fas fa-shield-alt mr-1"></i>已豁免</span>'
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

function isExpired(expireTime) {
    if (!expireTime) return false;
    return new Date(expireTime) < new Date();
}

function viewDetail(id) {
    fetch(`{{ route('admin_api_whs_exp_zero_detail', ['id' => '__ID__']) }}`.replace('__ID__', id), {
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(res => res.json())
    .then(data => {
        if (data.success && data.record) {
            const r = data.record;
            const history = data.history || [];

            document.getElementById('detailContent').innerHTML = `
                <div class="mb-6">
                    <h4 class="text-lg font-bold text-slate-800 mb-4">账户信息</h4>
                    <div class="grid grid-cols-2 gap-4">
                        <div class="bg-slate-50 rounded-lg p-4">
                            <p class="text-xs text-slate-600 mb-1">用户名</p>
                            <p class="text-sm font-semibold text-slate-800">${r.username}</p>
                        </div>
                        <div class="bg-slate-50 rounded-lg p-4">
                            <p class="text-xs text-slate-600 mb-1">MT4账号</p>
                            <p class="text-sm font-mono font-semibold text-slate-800">${r.mt4_account}</p>
                        </div>
                        <div class="bg-slate-50 rounded-lg p-4">
                            <p class="text-xs text-slate-600 mb-1">账户余额</p>
                            <p class="text-sm font-bold text-slate-800">${formatMoney(r.balance)}</p>
                        </div>
                        <div class="bg-slate-50 rounded-lg p-4">
                            <p class="text-xs text-slate-600 mb-1">未平仓手数</p>
                            <p class="text-sm font-semibold text-slate-800">${r.open_lots || '0.00'}</p>
                        </div>
                        <div class="bg-slate-50 rounded-lg p-4">
                            <p class="text-xs text-slate-600 mb-1">未平仓订单数</p>
                            <p class="text-sm font-semibold text-slate-800">${r.open_orders || 0}</p>
                        </div>
                        <div class="bg-slate-50 rounded-lg p-4">
                            <p class="text-xs text-slate-600 mb-1">最后交易时间</p>
                            <p class="text-sm font-semibold text-slate-800">${r.last_trade_time || '-'}</p>
                        </div>
                        <div class="bg-slate-50 rounded-lg p-4">
                            <p class="text-xs text-slate-600 mb-1">到期时间</p>
                            <p class="text-sm font-semibold ${isExpired(r.expire_time) ? 'text-red-600' : 'text-slate-800'}">${r.expire_time || '-'}</p>
                        </div>
                        <div class="bg-slate-50 rounded-lg p-4">
                            <p class="text-xs text-slate-600 mb-1">状态</p>
                            ${getStatusBadge(r.status)}
                        </div>
                    </div>
                </div>

                ${r.status !== 'pending' ? `
                <div class="mb-6">
                    <h4 class="text-lg font-bold text-slate-800 mb-4">清零信息</h4>
                    <div class="grid grid-cols-2 gap-4">
                        <div class="bg-slate-50 rounded-lg p-4">
                            <p class="text-xs text-slate-600 mb-1">清零时间</p>
                            <p class="text-sm font-semibold text-slate-800">${r.cleared_time || '-'}</p>
                        </div>
                        <div class="bg-slate-50 rounded-lg p-4">
                            <p class="text-xs text-slate-600 mb-1">操作人</p>
                            <p class="text-sm font-semibold text-slate-800">${r.operator || '-'}</p>
                        </div>
                        <div class="bg-slate-50 rounded-lg p-4">
                            <p class="text-xs text-slate-600 mb-1">清零金额</p>
                            <p class="text-sm font-bold text-red-600">${formatMoney(r.cleared_amount)}</p>
                        </div>
                        <div class="bg-slate-50 rounded-lg p-4">
                            <p class="text-xs text-slate-600 mb-1">平仓订单数</p>
                            <p class="text-sm font-semibold text-slate-800">${r.closed_orders || 0}</p>
                        </div>
                        ${r.clear_remark ? `
                        <div class="bg-slate-50 rounded-lg p-4 col-span-2">
                            <p class="text-xs text-slate-600 mb-1">操作备注</p>
                            <p class="text-sm text-slate-800">${r.clear_remark}</p>
                        </div>
                        ` : ''}
                    </div>
                </div>
                ` : ''}

                <div>
                    <h4 class="text-lg font-bold text-slate-800 mb-4">操作历史 (${history.length})</h4>
                    ${history.length === 0 ? `
                        <div class="text-center py-8 bg-slate-50 rounded-lg">
                            <i class="fas fa-inbox text-3xl text-slate-300 mb-2"></i>
                            <p class="text-slate-600 text-sm">暂无操作记录</p>
                        </div>
                    ` : `
                        <div class="space-y-2 max-h-96 overflow-y-auto">
                            ${history.map(h => `
                                <div class="flex items-start gap-3 p-3 bg-slate-50 rounded-lg">
                                    <div class="w-8 h-8 rounded-full bg-orange-100 flex items-center justify-center flex-shrink-0">
                                        <i class="fas fa-${h.action_type === 'clear' ? 'eraser' : 'shield-alt'} text-orange-600 text-xs"></i>
                                    </div>
                                    <div class="flex-1">
                                        <p class="text-sm font-semibold text-slate-800">${h.action_name}</p>
                                        <p class="text-xs text-slate-500">${h.remark || ''}</p>
                                        <p class="text-xs text-slate-400 mt-1">操作人：${h.operator} | ${h.created_at}</p>
                                    </div>
                                </div>
                            `).join('')}
                        </div>
                    `}
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

function openExecuteModal() {
    document.getElementById('executeRemark').value = '';
    document.getElementById('executeModal').classList.remove('hidden');
}

function closeExecuteModal() {
    document.getElementById('executeModal').classList.add('hidden');
}

function submitExecute() {
    const scope = document.getElementById('executeScope').value;
    const mode = document.querySelector('input[name="executeMode"]:checked').value;
    const remark = document.getElementById('executeRemark').value.trim();

    if (mode === 'auto' && !confirm('确定要执行批量清零操作吗？此操作不可逆！')) {
        return;
    }

    fetch('{{ route("admin_api_whs_exp_zero_execute") }}', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: JSON.stringify({
            scope: scope,
            mode: mode,
            remark: remark
        })
    })
    .then(res => res.json())
    .then(data => {
        if (data.success || data.code === 200) {
            alert(`操作成功！${mode === 'preview' ? '预览' : '实际'}处理了 ${data.count || 0} 个账户`);
            closeExecuteModal();
            loadStats();
            loadRecords();
        } else {
            alert(data.message || '操作失败');
        }
    })
    .catch(err => {
        console.error('Execute error:', err);
        alert('网络错误，请稍后重试');
    });
}

function clearSingle(id, mt4Account) {
    if (!confirm(`确定要清零账户 ${mt4Account} 吗？`)) {
        return;
    }

    fetch(`{{ route('admin_api_whs_exp_zero_clear_single', ['id' => '__ID__']) }}`.replace('__ID__', id), {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
            'X-Requested-With': 'XMLHttpRequest'
        }
    })
    .then(res => res.json())
    .then(data => {
        if (data.success || data.code === 200) {
            alert('清零成功');
            loadStats();
            loadRecords();
        } else {
            alert(data.message || '操作失败');
        }
    })
    .catch(err => {
        console.error('Clear error:', err);
        alert('网络错误，请稍后重试');
    });
}

function exemptAccount(id, mt4Account) {
    if (!confirm(`确定要豁免账户 ${mt4Account} 吗？豁免后将不再执行自动清零。`)) {
        return;
    }

    fetch(`{{ route('admin_api_whs_exp_zero_exempt', ['id' => '__ID__']) }}`.replace('__ID__', id), {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
            'X-Requested-With': 'XMLHttpRequest'
        }
    })
    .then(res => res.json())
    .then(data => {
        if (data.success || data.code === 200) {
            alert('豁免成功');
            loadStats();
            loadRecords();
        } else {
            alert(data.message || '操作失败');
        }
    })
    .catch(err => {
        console.error('Exempt error:', err);
        alert('网络错误，请稍后重试');
    });
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
