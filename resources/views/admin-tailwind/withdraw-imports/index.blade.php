@extends('admin-tailwind.layouts.app')

@section('title', '出金导入 - 管理后台')

@section('content')
<!-- Page Header -->
<div class="flex items-center justify-between mb-8">
    <div>
        <div class="flex items-center gap-3 mb-2">
            <a href="{{ route('admin_tailwind_page_withdrawals') }}" class="text-slate-600 hover:text-slate-800">
                <i class="fas fa-arrow-left"></i> 返回出金管理
            </a>
        </div>
        <h1 class="text-3xl font-bold text-slate-800">出金批量导入</h1>
        <p class="text-slate-600 mt-2">通过Excel文件批量导入出金记录</p>
    </div>
    <div class="flex gap-3">
        <a href="{{ route('admin_api_withdraw_imports_template') }}" class="px-6 py-3 bg-gradient-to-r from-orange-500 to-red-600 text-white font-semibold rounded-lg hover:shadow-lg transition">
            <i class="fas fa-download mr-2"></i>下载模板
        </a>
    </div>
</div>

<!-- Upload Card -->
<div class="bg-white rounded-xl shadow-lg p-8 mb-6">
    <div class="max-w-3xl mx-auto">
        <div class="text-center mb-6">
            <div class="inline-flex items-center justify-center w-16 h-16 bg-gradient-to-br from-orange-500 to-red-600 rounded-full mb-4">
                <i class="fas fa-cloud-upload-alt text-3xl text-white"></i>
            </div>
            <h2 class="text-2xl font-bold text-slate-800 mb-2">上传出金数据文件</h2>
            <p class="text-slate-600">支持 .xlsx 和 .csv 格式，单次最多导入 1000 条记录</p>
        </div>

        <div id="uploadArea" class="border-2 border-dashed border-slate-300 rounded-xl p-12 text-center hover:border-orange-500 transition cursor-pointer" onclick="document.getElementById('fileInput').click()">
            <input type="file" id="fileInput" accept=".xlsx,.xls,.csv" class="hidden" onchange="handleFileSelect(event)">
            <i class="fas fa-file-excel text-5xl text-slate-300 mb-4"></i>
            <p class="text-lg text-slate-600 mb-2">点击上传或拖拽文件到此区域</p>
            <p class="text-sm text-slate-500">支持 Excel (.xlsx, .xls) 和 CSV (.csv) 格式</p>
        </div>

        <div id="fileInfo" class="hidden mt-4 p-4 bg-orange-50 border border-orange-200 rounded-lg">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <i class="fas fa-file-excel text-2xl text-green-600"></i>
                    <div>
                        <p class="text-sm font-semibold text-slate-800" id="fileName"></p>
                        <p class="text-xs text-slate-600" id="fileSize"></p>
                    </div>
                </div>
                <button onclick="clearFile()" class="text-red-600 hover:text-red-800">
                    <i class="fas fa-times"></i>
                </button>
            </div>
        </div>

        <div class="mt-6 flex justify-center">
            <button id="uploadBtn" onclick="uploadFile()" disabled class="px-8 py-3 bg-gradient-to-r from-green-500 to-green-600 text-white font-semibold rounded-lg hover:shadow-lg transition disabled:opacity-50 disabled:cursor-not-allowed">
                <i class="fas fa-upload mr-2"></i>开始导入
            </button>
        </div>

        <!-- Progress Bar -->
        <div id="progressContainer" class="hidden mt-6">
            <div class="flex items-center justify-between mb-2">
                <span class="text-sm font-semibold text-slate-700">导入进度</span>
                <span class="text-sm font-semibold text-orange-600" id="progressText">0%</span>
            </div>
            <div class="w-full bg-slate-200 rounded-full h-3 overflow-hidden">
                <div id="progressBar" class="h-full bg-gradient-to-r from-orange-500 to-red-600 transition-all duration-300" style="width: 0%"></div>
            </div>
        </div>

        <!-- Result Summary -->
        <div id="resultContainer" class="hidden mt-6 p-6 bg-slate-50 rounded-lg">
            <h3 class="text-lg font-bold text-slate-800 mb-4">导入结果</h3>
            <div class="grid grid-cols-3 gap-4 mb-4">
                <div class="text-center p-4 bg-white rounded-lg">
                    <p class="text-sm text-slate-600 mb-1">总计</p>
                    <p class="text-2xl font-bold text-slate-800" id="totalCount">0</p>
                </div>
                <div class="text-center p-4 bg-green-50 rounded-lg">
                    <p class="text-sm text-green-600 mb-1">成功</p>
                    <p class="text-2xl font-bold text-green-600" id="successCount">0</p>
                </div>
                <div class="text-center p-4 bg-red-50 rounded-lg">
                    <p class="text-sm text-red-600 mb-1">失败</p>
                    <p class="text-2xl font-bold text-red-600" id="failedCount">0</p>
                </div>
            </div>
            <div id="errorList" class="hidden">
                <h4 class="text-sm font-semibold text-red-600 mb-2">错误详情</h4>
                <div class="max-h-60 overflow-y-auto bg-white border border-red-200 rounded-lg p-4">
                    <ul id="errorItems" class="space-y-2 text-sm text-slate-700"></ul>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Import History -->
<div class="bg-white rounded-xl shadow-lg overflow-hidden">
    <div class="px-6 py-4 bg-slate-50 border-b border-slate-200">
        <h2 class="text-xl font-bold text-slate-800">导入历史</h2>
    </div>

    <div class="overflow-x-auto">
        <table class="w-full">
            <thead class="bg-slate-50 border-b border-slate-200">
                <tr>
                    <th class="px-6 py-4 text-left text-xs font-semibold text-slate-600 uppercase tracking-wider">导入批次</th>
                    <th class="px-6 py-4 text-left text-xs font-semibold text-slate-600 uppercase tracking-wider">文件名</th>
                    <th class="px-6 py-4 text-left text-xs font-semibold text-slate-600 uppercase tracking-wider">导入时间</th>
                    <th class="px-6 py-4 text-left text-xs font-semibold text-slate-600 uppercase tracking-wider">操作人</th>
                    <th class="px-6 py-4 text-left text-xs font-semibold text-slate-600 uppercase tracking-wider">总计</th>
                    <th class="px-6 py-4 text-left text-xs font-semibold text-slate-600 uppercase tracking-wider">成功</th>
                    <th class="px-6 py-4 text-left text-xs font-semibold text-slate-600 uppercase tracking-wider">失败</th>
                    <th class="px-6 py-4 text-left text-xs font-semibold text-slate-600 uppercase tracking-wider">状态</th>
                    <th class="px-6 py-4 text-left text-xs font-semibold text-slate-600 uppercase tracking-wider">操作</th>
                </tr>
            </thead>
            <tbody id="historyTableBody" class="divide-y divide-slate-200">
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
    <div class="bg-white rounded-2xl max-w-4xl w-full max-h-[90vh] overflow-y-auto">
        <div class="sticky top-0 bg-gradient-to-r from-orange-500 to-red-600 px-6 py-4 flex items-center justify-between">
            <h3 class="text-xl font-bold text-white">导入详情</h3>
            <button onclick="closeDetailModal()" class="text-white hover:text-slate-200">
                <i class="fas fa-times text-xl"></i>
            </button>
        </div>
        <div id="modalContent" class="p-6"></div>
    </div>
</div>

<script>
let selectedFile = null;
let currentPage = 1;
let totalPages = 1;

document.addEventListener('DOMContentLoaded', function() {
    loadImportHistory();

    const uploadArea = document.getElementById('uploadArea');
    uploadArea.addEventListener('dragover', (e) => {
        e.preventDefault();
        uploadArea.classList.add('border-orange-500', 'bg-orange-50');
    });
    uploadArea.addEventListener('dragleave', (e) => {
        e.preventDefault();
        uploadArea.classList.remove('border-orange-500', 'bg-orange-50');
    });
    uploadArea.addEventListener('drop', (e) => {
        e.preventDefault();
        uploadArea.classList.remove('border-orange-500', 'bg-orange-50');
        const files = e.dataTransfer.files;
        if (files.length > 0) {
            handleFile(files[0]);
        }
    });
});

function handleFileSelect(event) {
    const file = event.target.files[0];
    if (file) {
        handleFile(file);
    }
}

function handleFile(file) {
    const validExtensions = ['.xlsx', '.xls', '.csv'];
    const fileName = file.name.toLowerCase();
    const isValid = validExtensions.some(ext => fileName.endsWith(ext));

    if (!isValid) {
        alert('请上传 Excel (.xlsx, .xls) 或 CSV (.csv) 格式的文件');
        return;
    }

    if (file.size > 10 * 1024 * 1024) {
        alert('文件大小不能超过 10MB');
        return;
    }

    selectedFile = file;
    document.getElementById('fileName').textContent = file.name;
    document.getElementById('fileSize').textContent = formatFileSize(file.size);
    document.getElementById('fileInfo').classList.remove('hidden');
    document.getElementById('uploadBtn').disabled = false;
}

function clearFile() {
    selectedFile = null;
    document.getElementById('fileInput').value = '';
    document.getElementById('fileInfo').classList.add('hidden');
    document.getElementById('uploadBtn').disabled = true;
    document.getElementById('progressContainer').classList.add('hidden');
    document.getElementById('resultContainer').classList.add('hidden');
}

function uploadFile() {
    if (!selectedFile) {
        alert('请先选择文件');
        return;
    }

    const formData = new FormData();
    formData.append('file', selectedFile);

    document.getElementById('uploadBtn').disabled = true;
    document.getElementById('progressContainer').classList.remove('hidden');
    document.getElementById('resultContainer').classList.add('hidden');

    let progress = 0;
    const progressInterval = setInterval(() => {
        if (progress < 90) {
            progress += Math.random() * 10;
            updateProgress(Math.min(progress, 90));
        }
    }, 200);

    fetch('{{ route("admin_api_withdraw_imports_upload") }}', {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: formData
    })
    .then(res => res.json())
    .then(data => {
        clearInterval(progressInterval);
        updateProgress(100);

        setTimeout(() => {
            if (data.success || data.code === 200) {
                showResult(data.result || {});
                loadImportHistory();
                setTimeout(() => {
                    clearFile();
                }, 3000);
            } else {
                alert(data.message || '导入失败');
                document.getElementById('uploadBtn').disabled = false;
            }
        }, 500);
    })
    .catch(err => {
        clearInterval(progressInterval);
        console.error('Upload error:', err);
        alert('网络错误，请稍后重试');
        document.getElementById('uploadBtn').disabled = false;
        document.getElementById('progressContainer').classList.add('hidden');
    });
}

function updateProgress(percent) {
    document.getElementById('progressBar').style.width = percent + '%';
    document.getElementById('progressText').textContent = Math.round(percent) + '%';
}

function showResult(result) {
    document.getElementById('totalCount').textContent = result.total || 0;
    document.getElementById('successCount').textContent = result.success || 0;
    document.getElementById('failedCount').textContent = result.failed || 0;

    const errorList = document.getElementById('errorList');
    const errorItems = document.getElementById('errorItems');

    if (result.errors && result.errors.length > 0) {
        errorList.classList.remove('hidden');
        errorItems.innerHTML = result.errors.map(err => `
            <li class="flex items-start gap-2">
                <i class="fas fa-exclamation-circle text-red-500 mt-1"></i>
                <span>${err}</span>
            </li>
        `).join('');
    } else {
        errorList.classList.add('hidden');
    }

    document.getElementById('resultContainer').classList.remove('hidden');
}

function loadImportHistory(page = 1) {
    currentPage = page;

    fetch(`{{ route('admin_api_withdraw_imports_history') }}?page=${page}`, {
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(res => res.json())
    .then(data => {
        if (data.success || data.code === 200) {
            renderHistoryTable(data.imports || []);
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

function renderHistoryTable(imports) {
    const tbody = document.getElementById('historyTableBody');

    if (imports.length === 0) {
        tbody.innerHTML = `
            <tr>
                <td colspan="9" class="px-6 py-12 text-center text-slate-500">
                    <i class="fas fa-inbox text-3xl mb-3"></i>
                    <p>暂无导入记录</p>
                </td>
            </tr>
        `;
        return;
    }

    tbody.innerHTML = imports.map(imp => `
        <tr class="hover:bg-slate-50 transition">
            <td class="px-6 py-4">
                <p class="text-sm font-mono text-slate-800">${imp.batch_no || '-'}</p>
            </td>
            <td class="px-6 py-4">
                <p class="text-sm text-slate-800">${imp.file_name || '-'}</p>
            </td>
            <td class="px-6 py-4 text-sm text-slate-600">${imp.created_at || '-'}</td>
            <td class="px-6 py-4">
                <div class="flex items-center gap-2">
                    <div class="w-8 h-8 bg-gradient-to-br from-purple-500 to-indigo-600 rounded-full flex items-center justify-center text-white text-xs font-bold">
                        ${(imp.operator_name || 'A').charAt(0).toUpperCase()}
                    </div>
                    <span class="text-sm text-slate-800">${imp.operator_name || '-'}</span>
                </div>
            </td>
            <td class="px-6 py-4 text-sm text-slate-800">${imp.total_count || 0}</td>
            <td class="px-6 py-4 text-sm font-semibold text-green-600">${imp.success_count || 0}</td>
            <td class="px-6 py-4 text-sm font-semibold text-red-600">${imp.failed_count || 0}</td>
            <td class="px-6 py-4">${getStatusBadge(imp.status)}</td>
            <td class="px-6 py-4">
                <button onclick="viewImportDetail(${imp.id})" class="px-3 py-1 bg-orange-100 text-orange-700 text-xs font-semibold rounded hover:bg-orange-200 transition">
                    <i class="fas fa-eye mr-1"></i>详情
                </button>
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
        html += `<button onclick="loadImportHistory(${currentPage - 1})" class="px-3 py-1 border border-slate-300 rounded hover:bg-slate-50">上一页</button>`;
    }
    for (let i = Math.max(1, currentPage - 2); i <= Math.min(totalPages, currentPage + 2); i++) {
        html += `<button onclick="loadImportHistory(${i})" class="px-3 py-1 border ${i === currentPage ? 'bg-orange-500 text-white' : 'border-slate-300 hover:bg-slate-50'} rounded">${i}</button>`;
    }
    if (currentPage < totalPages) {
        html += `<button onclick="loadImportHistory(${currentPage + 1})" class="px-3 py-1 border border-slate-300 rounded hover:bg-slate-50">下一页</button>`;
    }
    paginationDiv.innerHTML = html;
}

function getStatusBadge(status) {
    const badges = {
        'completed': '<span class="px-2 py-1 bg-green-100 text-green-700 text-xs font-semibold rounded-full">已完成</span>',
        'processing': '<span class="px-2 py-1 bg-blue-100 text-blue-700 text-xs font-semibold rounded-full">处理中</span>',
        'failed': '<span class="px-2 py-1 bg-red-100 text-red-700 text-xs font-semibold rounded-full">失败</span>',
        'partial': '<span class="px-2 py-1 bg-yellow-100 text-yellow-700 text-xs font-semibold rounded-full">部分成功</span>'
    };
    return badges[status] || '<span class="px-2 py-1 bg-slate-100 text-slate-700 text-xs font-semibold rounded-full">未知</span>';
}

function viewImportDetail(id) {
    fetch(`{{ route('admin_api_withdraw_imports_detail', ['id' => '__ID__']) }}`.replace('__ID__', id), {
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(res => res.json())
    .then(data => {
        if (data.success && data.import) {
            const imp = data.import;
            document.getElementById('modalContent').innerHTML = `
                <div class="space-y-6">
                    <div class="grid grid-cols-2 gap-4">
                        <div><p class="text-sm text-slate-600 mb-1">批次号</p><p class="text-base font-mono text-slate-800">${imp.batch_no || '-'}</p></div>
                        <div><p class="text-sm text-slate-600 mb-1">文件名</p><p class="text-base text-slate-800">${imp.file_name || '-'}</p></div>
                        <div><p class="text-sm text-slate-600 mb-1">导入时间</p><p class="text-base text-slate-800">${imp.created_at || '-'}</p></div>
                        <div><p class="text-sm text-slate-600 mb-1">操作人</p><p class="text-base text-slate-800">${imp.operator_name || '-'}</p></div>
                    </div>

                    <div class="border-t border-slate-200 pt-4">
                        <h4 class="text-sm font-semibold text-slate-700 mb-3">导入统计</h4>
                        <div class="grid grid-cols-4 gap-4">
                            <div class="text-center p-4 bg-slate-50 rounded-lg">
                                <p class="text-sm text-slate-600 mb-1">总计</p>
                                <p class="text-2xl font-bold text-slate-800">${imp.total_count || 0}</p>
                            </div>
                            <div class="text-center p-4 bg-green-50 rounded-lg">
                                <p class="text-sm text-green-600 mb-1">成功</p>
                                <p class="text-2xl font-bold text-green-600">${imp.success_count || 0}</p>
                            </div>
                            <div class="text-center p-4 bg-red-50 rounded-lg">
                                <p class="text-sm text-red-600 mb-1">失败</p>
                                <p class="text-2xl font-bold text-red-600">${imp.failed_count || 0}</p>
                            </div>
                            <div class="text-center p-4 bg-orange-50 rounded-lg">
                                <p class="text-sm text-orange-600 mb-1">状态</p>
                                <div class="mt-2">${getStatusBadge(imp.status)}</div>
                            </div>
                        </div>
                    </div>

                    ${imp.errors && imp.errors.length > 0 ? `
                        <div class="border-t border-slate-200 pt-4">
                            <h4 class="text-sm font-semibold text-red-600 mb-3">错误详情 (${imp.errors.length}条)</h4>
                            <div class="max-h-60 overflow-y-auto bg-red-50 border border-red-200 rounded-lg p-4">
                                <ul class="space-y-2 text-sm text-slate-700">
                                    ${imp.errors.map(err => `
                                        <li class="flex items-start gap-2">
                                            <i class="fas fa-exclamation-circle text-red-500 mt-1"></i>
                                            <span>${err}</span>
                                        </li>
                                    `).join('')}
                                </ul>
                            </div>
                        </div>
                    ` : ''}

                    ${imp.remark ? `
                        <div class="border-t border-slate-200 pt-4">
                            <p class="text-sm text-slate-600 mb-2">备注</p>
                            <p class="text-base text-slate-800 bg-slate-50 rounded-lg p-3">${imp.remark}</p>
                        </div>
                    ` : ''}
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

function formatFileSize(bytes) {
    if (bytes === 0) return '0 B';
    const k = 1024;
    const sizes = ['B', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return Math.round(bytes / Math.pow(k, i) * 100) / 100 + ' ' + sizes[i];
}

function showError(message) {
    document.getElementById('historyTableBody').innerHTML = `
        <tr><td colspan="9" class="px-6 py-12 text-center text-red-500">
            <i class="fas fa-exclamation-circle text-3xl mb-3"></i><p>${message}</p>
        </td></tr>
    `;
}
</script>
@endsection
