@extends('front-coreui-v2.layouts.app')

@section('title', '账户凭证')

@section('content')
<div class="container-lg px-4">
    <!-- Page Header -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h2 class="mb-2">账户凭证</h2>
                    <p class="text-body-secondary mb-0">上传和管理您的开户凭证</p>
                </div>
                <button onclick="showUploadModal()" class="btn btn-primary-gradient">
                    <i class="cil-cloud-upload me-2"></i>上传凭证
                </button>
            </div>
        </div>
    </div>

    <!-- Filter Bar -->
    <div class="card shadow-sm border-0 mb-4">
        <div class="card-body">
            <div class="row g-3 align-items-end">
                <div class="col-md-3">
                    <label class="form-label small">凭证类型</label>
                    <select id="filterType" class="form-select">
                        <option value="">全部类型</option>
                        <option value="id_front">身份证正面</option>
                        <option value="id_back">身份证反面</option>
                        <option value="bank_card">银行卡</option>
                        <option value="address">地址证明</option>
                        <option value="other">其他</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label small">审核状态</label>
                    <select id="filterStatus" class="form-select">
                        <option value="">全部状态</option>
                        <option value="pending">待审核</option>
                        <option value="approved">已通过</option>
                        <option value="rejected">已拒绝</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label small">上传时间</label>
                    <select id="filterTime" class="form-select">
                        <option value="">全部时间</option>
                        <option value="today">今天</option>
                        <option value="week">本周</option>
                        <option value="month">本月</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <button onclick="loadVouchers()" class="btn btn-primary w-100">
                        <i class="cil-search me-2"></i>查询
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Vouchers Grid -->
    <div class="row g-4" id="vouchersGrid">
        <div class="col-12 text-center py-5">
            <div class="spinner-border text-primary" role="status">
                <span class="visually-hidden">加载中...</span>
            </div>
            <p class="text-body-secondary mt-3">加载凭证信息...</p>
        </div>
    </div>
</div>

<!-- Upload Modal -->
<div class="modal fade" id="uploadModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="cil-cloud-upload me-2"></i>上传凭证
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="uploadForm">
                    <div class="mb-4">
                        <label class="form-label">凭证类型 <span class="text-danger">*</span></label>
                        <select id="voucherType" class="form-select" required>
                            <option value="">请选择凭证类型</option>
                            <option value="id_front">身份证正面</option>
                            <option value="id_back">身份证反面</option>
                            <option value="bank_card">银行卡</option>
                            <option value="address">地址证明</option>
                            <option value="other">其他</option>
                        </select>
                    </div>

                    <div class="mb-4">
                        <label class="form-label">上传文件 <span class="text-danger">*</span></label>
                        <div class="border rounded p-4 text-center" id="uploadArea" onclick="document.getElementById('fileInput').click()" style="cursor: pointer; border-style: dashed !important;">
                            <i class="cil-cloud-upload" style="font-size: 3rem; color: #667eea;"></i>
                            <p class="mt-3 mb-2">点击上传或拖拽文件到此处</p>
                            <p class="text-body-secondary small mb-0">支持 JPG、PNG、PDF 格式，最大 5MB</p>
                        </div>
                        <input type="file" id="fileInput" accept="image/jpeg,image/png,application/pdf" class="d-none" onchange="handleFileSelect(event)">
                        <div id="filePreview" class="mt-3 d-none">
                            <div class="alert alert-info d-flex align-items-center">
                                <i class="cil-file me-3" style="font-size: 2rem;"></i>
                                <div class="flex-grow-1">
                                    <strong id="fileName"></strong>
                                    <p class="mb-0 small text-body-secondary" id="fileSize"></p>
                                </div>
                                <button type="button" class="btn-close" onclick="clearFile()"></button>
                            </div>
                        </div>
                    </div>

                    <div class="mb-4">
                        <label class="form-label">备注说明</label>
                        <textarea id="voucherNote" class="form-control" rows="3" placeholder="请输入备注信息（选填）"></textarea>
                    </div>

                    <div class="alert alert-warning border-0">
                        <i class="cil-warning me-2"></i>
                        <strong>重要提示：</strong>
                        <ul class="mb-0 mt-2 small">
                            <li>请确保照片清晰完整，四角可见</li>
                            <li>身份证需在有效期内</li>
                            <li>银行卡照片需显示卡号和持卡人姓名</li>
                            <li>审核时间为1-3个工作日</li>
                        </ul>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <i class="cil-x me-2"></i>取消
                </button>
                <button type="button" onclick="submitUpload()" class="btn btn-primary-gradient">
                    <i class="cil-save me-2"></i>确认上传
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Preview Modal -->
<div class="modal fade" id="previewModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="cil-image me-2"></i>凭证预览
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body text-center">
                <img id="previewImage" src="" class="img-fluid rounded" alt="凭证预览">
            </div>
        </div>
    </div>
</div>

<script>
let uploadModal, previewModal;
let selectedFile = null;

document.addEventListener('DOMContentLoaded', function() {
    uploadModal = new bootstrap.Modal(document.getElementById('uploadModal'));
    previewModal = new bootstrap.Modal(document.getElementById('previewModal'));
    loadVouchers();
});

function loadVouchers() {
    const type = document.getElementById('filterType').value;
    const status = document.getElementById('filterStatus').value;
    const time = document.getElementById('filterTime').value;

    const params = new URLSearchParams();
    if (type) params.append('type', type);
    if (status) params.append('status', status);
    if (time) params.append('time', time);

    fetch('{{ route("front_api_account_voucher_list") }}?' + params.toString(), {
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(res => res.json())
    .then(data => {
        if (data.success && data.vouchers) {
            renderVouchers(data.vouchers);
        } else {
            showError('加载失败');
        }
    })
    .catch(err => {
        console.error('Load vouchers error:', err);
        showError('网络错误，请稍后重试');
    });
}

function renderVouchers(vouchers) {
    const grid = document.getElementById('vouchersGrid');

    if (vouchers.length === 0) {
        grid.innerHTML = `
            <div class="col-12 text-center py-5">
                <i class="cil-inbox" style="font-size: 4rem; color: #ccc;"></i>
                <p class="text-body-secondary mt-3">暂无凭证记录</p>
                <button onclick="showUploadModal()" class="btn btn-primary-gradient">
                    <i class="cil-cloud-upload me-2"></i>立即上传
                </button>
            </div>
        `;
        return;
    }

    const html = vouchers.map(v => `
        <div class="col-xl-3 col-lg-4 col-md-6">
            <div class="card shadow-sm border-0 h-100">
                <div class="position-relative">
                    <img src="${v.thumbnail_url || v.file_url}" class="card-img-top" alt="${v.type_name}" style="height: 200px; object-fit: cover; cursor: pointer;" onclick="previewVoucher('${v.file_url}')">
                    <div class="position-absolute top-0 end-0 m-2">
                        ${getStatusBadge(v.status)}
                    </div>
                </div>
                <div class="card-body">
                    <h6 class="card-title mb-2">
                        <i class="cil-file me-2"></i>${v.type_name || '未知类型'}
                    </h6>
                    <p class="text-body-secondary small mb-2">
                        <i class="cil-calendar me-1"></i>上传时间：${v.created_at || '-'}
                    </p>
                    ${v.status === 'approved' ? `
                        <p class="text-success small mb-2">
                            <i class="cil-check-circle me-1"></i>审核时间：${v.approved_at || '-'}
                        </p>
                    ` : ''}
                    ${v.status === 'rejected' ? `
                        <p class="text-danger small mb-2">
                            <i class="cil-x-circle me-1"></i>拒绝原因：${v.reject_reason || '-'}
                        </p>
                    ` : ''}
                    ${v.note ? `
                        <p class="text-body-secondary small mb-0">
                            <i class="cil-notes me-1"></i>备注：${v.note}
                        </p>
                    ` : ''}
                </div>
                <div class="card-footer bg-transparent border-0 pt-0">
                    <div class="d-flex gap-2">
                        <button onclick="previewVoucher('${v.file_url}')" class="btn btn-outline-primary btn-sm flex-grow-1">
                            <i class="cil-eye me-1"></i>查看
                        </button>
                        <button onclick="downloadVoucher('${v.id}')" class="btn btn-outline-secondary btn-sm">
                            <i class="cil-cloud-download"></i>
                        </button>
                        ${v.status === 'pending' ? `
                            <button onclick="deleteVoucher('${v.id}')" class="btn btn-outline-danger btn-sm">
                                <i class="cil-trash"></i>
                            </button>
                        ` : ''}
                    </div>
                </div>
            </div>
        </div>
    `).join('');

    grid.innerHTML = html;
}

function getStatusBadge(status) {
    const badges = {
        'pending': '<span class="badge bg-warning text-dark">待审核</span>',
        'approved': '<span class="badge bg-success">已通过</span>',
        'rejected': '<span class="badge bg-danger">已拒绝</span>'
    };
    return badges[status] || '<span class="badge bg-secondary">未知</span>';
}

function showUploadModal() {
    uploadModal.show();
}

function handleFileSelect(event) {
    const file = event.target.files[0];
    if (!file) return;

    if (file.size > 5 * 1024 * 1024) {
        alert('文件大小不能超过5MB');
        return;
    }

    selectedFile = file;
    document.getElementById('fileName').textContent = file.name;
    document.getElementById('fileSize').textContent = (file.size / 1024).toFixed(2) + ' KB';
    document.getElementById('filePreview').classList.remove('d-none');
}

function clearFile() {
    selectedFile = null;
    document.getElementById('fileInput').value = '';
    document.getElementById('filePreview').classList.add('d-none');
}

function submitUpload() {
    const type = document.getElementById('voucherType').value;
    const note = document.getElementById('voucherNote').value.trim();

    if (!type) {
        alert('请选择凭证类型');
        return;
    }

    if (!selectedFile) {
        alert('请选择要上传的文件');
        return;
    }

    const formData = new FormData();
    formData.append('type', type);
    formData.append('file', selectedFile);
    if (note) formData.append('note', note);

    fetch('{{ route("front_api_account_voucher_upload") }}', {
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
            alert('上传成功，等待审核');
            uploadModal.hide();
            document.getElementById('uploadForm').reset();
            clearFile();
            loadVouchers();
        } else {
            alert(data.message || '上传失败');
        }
    })
    .catch(err => {
        console.error('Upload error:', err);
        alert('网络错误，请稍后重试');
    });
}

function previewVoucher(url) {
    document.getElementById('previewImage').src = url;
    previewModal.show();
}

function downloadVoucher(id) {
    window.location.href = '{{ route("front_api_account_voucher_download") }}?id=' + id;
}

function deleteVoucher(id) {
    if (!confirm('确定要删除该凭证吗？')) return;

    fetch('{{ route("front_api_account_voucher_delete") }}', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: JSON.stringify({ id: id })
    })
    .then(res => res.json())
    .then(data => {
        if (data.success || data.code === 200) {
            alert('删除成功');
            loadVouchers();
        } else {
            alert(data.message || '删除失败');
        }
    })
    .catch(err => {
        console.error('Delete error:', err);
        alert('网络错误，请稍后重试');
    });
}

function showError(message) {
    document.getElementById('vouchersGrid').innerHTML = `
        <div class="col-12 text-center py-5">
            <i class="cil-warning" style="font-size: 4rem; color: #dc3545;"></i>
            <p class="text-danger mt-3">${message}</p>
            <button onclick="loadVouchers()" class="btn btn-outline-primary">
                <i class="cil-reload me-2"></i>重新加载
            </button>
        </div>
    `;
}
</script>

<style>
.btn-primary-gradient {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    border: none;
    color: white;
    font-weight: 600;
}

.btn-primary-gradient:hover {
    opacity: 0.9;
    color: white;
}
</style>
@endsection
