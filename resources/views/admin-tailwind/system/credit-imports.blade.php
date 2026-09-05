@extends('admin-tailwind.layouts.app')

@section('title', '信用导入 - 管理后台')

@section('content')
<!-- Page Header -->
<div class="flex items-center justify-between mb-8">
    <div>
        <h1 class="text-3xl font-bold text-slate-800">信用导入</h1>
        <p class="text-slate-600 mt-2">批量导入MT4账户信用调整记录</p>
    </div>
    <div class="flex gap-3">
        <button onclick="downloadTemplate()" class="px-6 py-3 bg-gradient-to-r from-purple-500 to-pink-600 text-white font-semibold rounded-lg hover:shadow-lg transition">
            <i class="fas fa-download mr-2"></i>下载模板
        </button>
        <button onclick="openImportModal()" class="px-6 py-3 bg-gradient-to-r from-green-500 to-emerald-600 text-white font-semibold rounded-lg hover:shadow-lg transition">
            <i class="fas fa-file-upload mr-2"></i>导入文件
        </button>
    </div>
</div>

<!-- Stats Cards -->
<div class="grid grid-cols-1 md:grid-cols-5 gap-6 mb-6">
    <div class="bg-white rounded-xl shadow-lg p-6 border-l-4 border-blue-500">
        <p class="text-sm text-slate-600 mb-2">总导入次数</p>
        <p class="text-3xl font-bold text-slate-800" id="totalImports">0</p>
    </div>
    <div class="bg-white rounded-xl shadow-lg p-6 border-l-4 border-green-500">
        <p class="text-sm text-slate-600 mb-2">成功记录</p>
        <p class="text-3xl font-bold text-green-600" id="successRecords">0</p>
    </div>
    <div class="bg-white rounded-xl shadow-lg p-6 border-l-4 border-red-500">
        <p class="text-sm text-slate-600 mb-2">失败记录</p>
        <p class="text-3xl font-bold text-red-600" id="failedRecords">0</p>
    </div>
    <div class="bg-white rounded-xl shadow-lg p-6 border-l-4 border-purple-500">
        <p class="text-sm text-slate-600 mb-2">累计信用金额</p>
        <p class="text-3xl font-bold text-purple-600" id="totalCreditAmount">$0</p>
    </div>
    <div class="bg-white rounded-xl shadow-lg p-6 border-l-4 border-orange-500">
        <p class="text-sm text-slate-600 mb-2">今日导入</p>
        <p class="text-3xl font-bold text-orange-600" id="todayImports">0</p>
    </div>
</div>

<!-- Filter Bar -->
<div class="bg-white rounded-xl shadow-lg p-6 mb-6">
    <div class="grid grid-cols-1 md:grid-cols-5 gap-4">
        <div>
            <label class="block text-sm font-semibold text-slate-700 mb-2">搜索</label>
            <input type="text" id="searchKeyword" placeholder="导入批次号或操作员" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
        </div>
        <div>
            <label class="block text-sm font-semibold text-slate-700 mb-2">状态</label>
            <select id="filterStatus" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                <option value="">全部</option>
                <option value="success">成功</option>
                <option value="partial">部分成功</option>
                <option value="failed">失败</option>
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
            <button onclick="searchImports()" class="w-full px-6 py-2 bg-gradient-to-r from-blue-500 to-indigo-600 text-white font-semibold rounded-lg hover:shadow-lg transition">
                <i class="fas fa-search mr-2"></i>搜索
            </button>
        </div>
    </div>
</div>

<!-- Import History -->
<div class="bg-white rounded-xl shadow-lg overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full">
            <thead class="bg-gradient-to-r from-blue-500 to-indigo-600 text-white">
                <tr>
                    <th class="px-6 py-4 text-left text-sm font-semibold">批次号</th>
                    <th class="px-6 py-4 text-left text-sm font-semibold">文件名</th>
                    <th class="px-6 py-4 text-right text-sm font-semibold">总记录数</th>
                    <th class="px-6 py-4 text-right text-sm font-semibold">成功</th>
                    <th class="px-6 py-4 text-right text-sm font-semibold">失败</th>
                    <th class="px-6 py-4 text-right text-sm font-semibold">信用金额</th>
                    <th class="px-6 py-4 text-left text-sm font-semibold">操作员</th>
                    <th class="px-6 py-4 text-center text-sm font-semibold">状态</th>
                    <th class="px-6 py-4 text-left text-sm font-semibold">导入时间</th>
                    <th class="px-6 py-4 text-center text-sm font-semibold">操作</th>
                </tr>
            </thead>
            <tbody id="importsTable">
                <tr>
                    <td colspan="10" class="px-6 py-12 text-center">
                        <i class="fas fa-spinner fa-spin text-3xl text-slate-500 mb-3"></i>
                        <p class="text-slate-600">加载中...</p>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</div>

<!-- Import Modal -->
<div id="importModal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl max-w-2xl w-full">
        <div class="bg-gradient-to-r from-green-500 to-emerald-600 px-6 py-4 flex items-center justify-between rounded-t-2xl">
            <h3 class="text-xl font-bold text-white">导入信用记录</h3>
            <button onclick="closeImportModal()" class="text-white hover:text-slate-200">
                <i class="fas fa-times text-xl"></i>
            </button>
        </div>
        <div class="p-6">
            <form id="importForm">
                <div class="mb-4">
                    <label class="block text-sm font-semibold text-slate-700 mb-2">选择文件 *</label>
                    <input type="file" id="importFile" accept=".csv,.xlsx,.xls" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" required>
                    <p class="text-xs text-slate-500 mt-1">支持格式：CSV, Excel (.xlsx, .xls)</p>
                </div>

                <div class="mb-4">
                    <label class="block text-sm font-semibold text-slate-700 mb-2">备注说明</label>
                    <textarea id="importRemark" rows="3" placeholder="选填：导入原因、批次说明等" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"></textarea>
                </div>

                <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 mb-4">
                    <h4 class="text-sm font-semibold text-blue-800 mb-2">
                        <i class="fas fa-info-circle mr-1"></i>文件格式说明
                    </h4>
                    <ul class="text-xs text-blue-700 space-y-1">
                        <li>• 第一行为标题行：MT4账号, 信用金额, 备注</li>
                        <li>• MT4账号：必填，整数格式</li>
                        <li>• 信用金额：必填，正数为增加，负数为减少</li>
                        <li>• 备注：选填，说明信用调整原因</li>
                    </ul>
                </div>

                <div class="flex gap-3">
                    <button type="button" onclick="closeImportModal()" class="flex-1 px-6 py-3 border border-slate-300 text-slate-700 font-semibold rounded-lg hover:bg-slate-50 transition">
                        取消
                    </button>
                    <button type="submit" class="flex-1 px-6 py-3 bg-gradient-to-r from-green-500 to-emerald-600 text-white font-semibold rounded-lg hover:shadow-lg transition">
                        <i class="fas fa-upload mr-2"></i>开始导入
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Detail Modal -->
<div id="detailModal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl max-w-6xl w-full max-h-[90vh] overflow-y-auto">
        <div class="sticky top-0 bg-gradient-to-r from-purple-500 to-pink-600 px-6 py-4 flex items-center justify-between">
            <h3 class="text-xl font-bold text-white">导入详情</h3>
            <button onclick="closeDetailModal()" class="text-white hover:text-slate-200">
                <i class="fas fa-times text-xl"></i>
            </button>
        </div>
        <div id="detailContent" class="p-6"></div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    loadStats();
    loadImports();

    document.getElementById('importForm').addEventListener('submit', function(e) {
        e.preventDefault();
        submitImport();
    });
});

function loadStats() {
    fetch('{{ route("admin_api_credit_imports_stats") }}', {
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(res => res.json())
    .then(data => {
        if (data.success || data.code === 200) {
            document.getElementById('totalImports').textContent = data.total || 0;
            document.getElementById('successRecords').textContent = data.success || 0;
            document.getElementById('failedRecords').textContent = data.failed || 0;
            document.getElementById('totalCreditAmount').textContent = '$' + formatNumber(data.totalAmount || 0);
            document.getElementById('todayImports').textContent = data.today || 0;
        }
    })
    .catch(err => console.error('Load stats error:', err));
}

function loadImports() {
    const keyword = document.getElementById('searchKeyword').value;
    const status = document.getElementById('filterStatus').value;
    const startDate = document.getElementById('filterStartDate').value;
    const endDate = document.getElementById('filterEndDate').value;

    const params = new URLSearchParams({
        keyword: keyword,
        status: status,
        start_date: startDate,
        end_date: endDate
    });

    fetch(`{{ route('admin_api_credit_imports_list') }}?${params}`, {
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(res => res.json())
    .then(data => {
        if (data.success || data.code === 200) {
            renderImports(data.imports || []);
        } else {
            showError(data.message || '加载失败');
        }
    })
    .catch(err => {
        console.error('Load error:', err);
        showError('网络错误，请稍后重试');
    });
}

function renderImports(imports) {
    const table = document.getElementById('importsTable');

    if (imports.length === 0) {
        table.innerHTML = `
            <tr>
                <td colspan="10" class="px-6 py-12 text-center">
                    <i class="fas fa-inbox text-5xl text-slate-300 mb-4"></i>
                    <p class="text-slate-600">暂无导入记录</p>
                </td>
            </tr>
        `;
        return;
    }

    table.innerHTML = imports.map((imp, index) => `
        <tr class="border-b border-slate-200 hover:bg-slate-50 transition ${index % 2 === 0 ? 'bg-white' : 'bg-slate-50'}">
            <td class="px-6 py-4">
                <span class="font-mono text-sm font-semibold text-slate-800">${imp.batch_no || '-'}</span>
            </td>
            <td class="px-6 py-4">
                <span class="text-sm text-slate-800">${imp.filename || '-'}</span>
            </td>
            <td class="px-6 py-4 text-right">
                <span class="text-sm font-semibold text-slate-800">${imp.total_records || 0}</span>
            </td>
            <td class="px-6 py-4 text-right">
                <span class="text-sm font-semibold text-green-600">${imp.success_records || 0}</span>
            </td>
            <td class="px-6 py-4 text-right">
                <span class="text-sm font-semibold text-red-600">${imp.failed_records || 0}</span>
            </td>
            <td class="px-6 py-4 text-right">
                <span class="text-sm font-semibold ${imp.total_amount >= 0 ? 'text-green-600' : 'text-red-600'}">
                    ${imp.total_amount >= 0 ? '+' : ''}${formatNumber(imp.total_amount || 0)}
                </span>
            </td>
            <td class="px-6 py-4">
                <span class="text-sm text-slate-800">${imp.operator || '-'}</span>
            </td>
            <td class="px-6 py-4 text-center">
                ${getStatusBadge(imp.status)}
            </td>
            <td class="px-6 py-4">
                <span class="text-sm text-slate-600">${imp.created_at || '-'}</span>
            </td>
            <td class="px-6 py-4">
                <div class="flex items-center justify-center gap-2">
                    <button onclick="viewDetail(${imp.id})" class="px-3 py-1 bg-blue-100 text-blue-700 text-xs font-semibold rounded hover:bg-blue-200 transition">
                        <i class="fas fa-eye"></i>
                    </button>
                    ${imp.failed_records > 0 ? `
                        <button onclick="downloadErrors(${imp.id})" class="px-3 py-1 bg-red-100 text-red-700 text-xs font-semibold rounded hover:bg-red-200 transition">
                            <i class="fas fa-download"></i>
                        </button>
                    ` : ''}
                </div>
            </td>
        </tr>
    `).join('');
}

function getStatusBadge(status) {
    const badges = {
        'success': '<span class="px-3 py-1 bg-green-100 text-green-700 text-xs font-semibold rounded-full">成功</span>',
        'partial': '<span class="px-3 py-1 bg-yellow-100 text-yellow-700 text-xs font-semibold rounded-full">部分成功</span>',
        'failed': '<span class="px-3 py-1 bg-red-100 text-red-700 text-xs font-semibold rounded-full">失败</span>',
        'processing': '<span class="px-3 py-1 bg-blue-100 text-blue-700 text-xs font-semibold rounded-full">处理中</span>'
    };
    return badges[status] || '<span class="px-3 py-1 bg-slate-100 text-slate-700 text-xs font-semibold rounded-full">未知</span>';
}

function openImportModal() {
    document.getElementById('importFile').value = '';
    document.getElementById('importRemark').value = '';
    document.getElementById('importModal').classList.remove('hidden');
}

function closeImportModal() {
    document.getElementById('importModal').classList.add('hidden');
}

function submitImport() {
    const fileInput = document.getElementById('importFile');
    const file = fileInput.files[0];

    if (!file) {
        alert('请选择要导入的文件');
        return;
    }

    const formData = new FormData();
    formData.append('file', file);
    formData.append('remark', document.getElementById('importRemark').value);

    fetch('{{ route("admin_api_credit_imports_upload") }}', {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: formData
    })
    .then(res => res.json())
    .then(data => {
        if (data.success || data.code === 200) {
            alert(`导入完成！成功：${data.success_count}，失败：${data.failed_count}`);
            closeImportModal();
            loadStats();
            loadImports();
        } else {
            alert(data.message || '导入失败');
        }
    })
    .catch(err => {
        console.error('Import error:', err);
        alert('网络错误，请稍后重试');
    });
}

function viewDetail(id) {
    fetch(`{{ route('admin_api_credit_imports_detail', ['id' => '__ID__']) }}`.replace('__ID__', id), {
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(res => res.json())
    .then(data => {
        if (data.success && data.import && data.records) {
            const imp = data.import;
            const records = data.records;

            document.getElementById('detailContent').innerHTML = `
                <div class="mb-6">
                    <h4 class="text-lg font-bold text-slate-800 mb-4">导入信息</h4>
                    <div class="grid grid-cols-2 gap-4">
                        <div class="bg-slate-50 rounded-lg p-4">
                            <p class="text-xs text-slate-600 mb-1">批次号</p>
                            <p class="text-sm font-mono font-semibold text-slate-800">${imp.batch_no}</p>
                        </div>
                        <div class="bg-slate-50 rounded-lg p-4">
                            <p class="text-xs text-slate-600 mb-1">文件名</p>
                            <p class="text-sm font-semibold text-slate-800">${imp.filename}</p>
                        </div>
                        <div class="bg-slate-50 rounded-lg p-4">
                            <p class="text-xs text-slate-600 mb-1">总记录数</p>
                            <p class="text-sm font-semibold text-slate-800">${imp.total_records}</p>
                        </div>
                        <div class="bg-slate-50 rounded-lg p-4">
                            <p class="text-xs text-slate-600 mb-1">成功/失败</p>
                            <p class="text-sm font-semibold"><span class="text-green-600">${imp.success_records}</span> / <span class="text-red-600">${imp.failed_records}</span></p>
                        </div>
                        <div class="bg-slate-50 rounded-lg p-4">
                            <p class="text-xs text-slate-600 mb-1">信用金额</p>
                            <p class="text-sm font-semibold ${imp.total_amount >= 0 ? 'text-green-600' : 'text-red-600'}">${imp.total_amount >= 0 ? '+' : ''}${formatNumber(imp.total_amount)}</p>
                        </div>
                        <div class="bg-slate-50 rounded-lg p-4">
                            <p class="text-xs text-slate-600 mb-1">导入时间</p>
                            <p class="text-sm font-semibold text-slate-800">${imp.created_at}</p>
                        </div>
                    </div>
                    ${imp.remark ? `<div class="mt-4 bg-blue-50 rounded-lg p-4"><p class="text-xs text-blue-600 mb-1">备注</p><p class="text-sm text-blue-800">${imp.remark}</p></div>` : ''}
                </div>

                <div>
                    <h4 class="text-lg font-bold text-slate-800 mb-4">详细记录 (${records.length})</h4>
                    <div class="overflow-x-auto">
                        <table class="w-full">
                            <thead class="bg-slate-100">
                                <tr>
                                    <th class="px-4 py-3 text-left text-xs font-semibold text-slate-700">MT4账号</th>
                                    <th class="px-4 py-3 text-right text-xs font-semibold text-slate-700">信用金额</th>
                                    <th class="px-4 py-3 text-left text-xs font-semibold text-slate-700">备注</th>
                                    <th class="px-4 py-3 text-center text-xs font-semibold text-slate-700">状态</th>
                                    <th class="px-4 py-3 text-left text-xs font-semibold text-slate-700">失败原因</th>
                                </tr>
                            </thead>
                            <tbody>
                                ${records.map(r => `
                                    <tr class="border-b border-slate-200 hover:bg-slate-50">
                                        <td class="px-4 py-3 text-sm font-mono text-slate-800">${r.mt4_account || '-'}</td>
                                        <td class="px-4 py-3 text-sm text-right font-semibold ${r.amount >= 0 ? 'text-green-600' : 'text-red-600'}">
                                            ${r.amount >= 0 ? '+' : ''}${formatNumber(r.amount || 0)}
                                        </td>
                                        <td class="px-4 py-3 text-sm text-slate-600">${r.remark || '-'}</td>
                                        <td class="px-4 py-3 text-center">
                                            ${r.status === 'success'
                                                ? '<span class="px-2 py-1 bg-green-100 text-green-700 text-xs rounded-full">成功</span>'
                                                : '<span class="px-2 py-1 bg-red-100 text-red-700 text-xs rounded-full">失败</span>'}
                                        </td>
                                        <td class="px-4 py-3 text-sm text-red-600">${r.error_message || '-'}</td>
                                    </tr>
                                `).join('')}
                            </tbody>
                        </table>
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

function downloadTemplate() {
    window.location.href = '{{ route("admin_api_credit_imports_template") }}';
}

function downloadErrors(id) {
    window.location.href = `{{ route('admin_api_credit_imports_errors', ['id' => '__ID__']) }}`.replace('__ID__', id);
}

function searchImports() {
    loadImports();
}

function formatNumber(num, decimals = 2) {
    return new Intl.NumberFormat('en-US', {
        minimumFractionDigits: decimals,
        maximumFractionDigits: decimals
    }).format(num);
}

function showError(message) {
    document.getElementById('importsTable').innerHTML = `
        <tr>
            <td colspan="10" class="px-6 py-12 text-center">
                <i class="fas fa-exclamation-circle text-5xl text-red-500 mb-4"></i>
                <p class="text-red-600">${message}</p>
            </td>
        </tr>
    `;
}
</script>
@endsection
