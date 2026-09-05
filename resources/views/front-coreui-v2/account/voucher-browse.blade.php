@extends('front-coreui-v2.layouts.app')

@section('title', '凭证浏览')

@section('content')
<div class="container-fluid px-4">
    <!-- Page Header -->
    <div class="row mb-4">
        <div class="col-12">
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="{{ route('front_coreui_v2_page_account_voucher') }}">账户凭证</a></li>
                    <li class="breadcrumb-item active">凭证浏览</li>
                </ol>
            </nav>
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h2 class="mb-2">凭证浏览</h2>
                    <p class="text-body-secondary mb-0">全屏浏览和管理凭证文件</p>
                </div>
                <a href="{{ route('front_coreui_v2_page_account_voucher') }}" class="btn btn-outline-secondary">
                    <i class="cil-arrow-left me-2"></i>返回列表
                </a>
            </div>
        </div>
    </div>

    <div class="row">
        <!-- Left Sidebar - Thumbnail List -->
        <div class="col-lg-3">
            <div class="card shadow-sm border-0 sticky-top" style="top: 1rem;">
                <div class="card-header bg-transparent border-bottom">
                    <h6 class="mb-0">
                        <i class="cil-list me-2"></i>凭证列表
                        <span class="badge bg-primary-gradient rounded-pill float-end" id="totalCount">0</span>
                    </h6>
                </div>
                <div class="card-body p-2" style="max-height: calc(100vh - 200px); overflow-y: auto;">
                    <div id="thumbnailList">
                        <div class="text-center py-4 text-body-secondary">
                            <div class="spinner-border spinner-border-sm mb-2"></div>
                            <p class="small mb-0">加载中...</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Main Content - Image Viewer -->
        <div class="col-lg-9">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-transparent border-bottom">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="mb-1" id="currentTitle">凭证详情</h6>
                            <small class="text-body-secondary" id="currentInfo">请从左侧选择凭证查看</small>
                        </div>
                        <div class="btn-group">
                            <button onclick="rotateImage(-90)" class="btn btn-outline-secondary btn-sm" title="逆时针旋转">
                                <i class="cil-action-undo"></i>
                            </button>
                            <button onclick="rotateImage(90)" class="btn btn-outline-secondary btn-sm" title="顺时针旋转">
                                <i class="cil-action-redo"></i>
                            </button>
                            <button onclick="zoomIn()" class="btn btn-outline-secondary btn-sm" title="放大">
                                <i class="cil-zoom-in"></i>
                            </button>
                            <button onclick="zoomOut()" class="btn btn-outline-secondary btn-sm" title="缩小">
                                <i class="cil-zoom-out"></i>
                            </button>
                            <button onclick="resetView()" class="btn btn-outline-secondary btn-sm" title="重置">
                                <i class="cil-reload"></i>
                            </button>
                            <button onclick="downloadCurrent()" class="btn btn-outline-primary btn-sm" title="下载">
                                <i class="cil-cloud-download"></i>
                            </button>
                        </div>
                    </div>
                </div>
                <div class="card-body p-0 bg-light" style="min-height: 600px; position: relative; overflow: hidden;">
                    <div id="imageContainer" class="d-flex align-items-center justify-content-center" style="min-height: 600px;">
                        <div class="text-center text-body-secondary">
                            <i class="cil-image" style="font-size: 5rem; opacity: 0.3;"></i>
                            <p class="mt-3">请从左侧选择凭证查看</p>
                        </div>
                    </div>
                </div>
                <div class="card-footer bg-transparent border-top">
                    <div class="row g-3">
                        <div class="col-md-3">
                            <small class="text-body-secondary">凭证类型</small>
                            <p class="mb-0 fw-semibold" id="detailType">-</p>
                        </div>
                        <div class="col-md-3">
                            <small class="text-body-secondary">审核状态</small>
                            <p class="mb-0" id="detailStatus">-</p>
                        </div>
                        <div class="col-md-3">
                            <small class="text-body-secondary">上传时间</small>
                            <p class="mb-0 fw-semibold" id="detailCreated">-</p>
                        </div>
                        <div class="col-md-3">
                            <small class="text-body-secondary">文件大小</small>
                            <p class="mb-0 fw-semibold" id="detailSize">-</p>
                        </div>
                        <div class="col-12" id="detailNoteSection" style="display: none;">
                            <small class="text-body-secondary">备注说明</small>
                            <p class="mb-0" id="detailNote">-</p>
                        </div>
                        <div class="col-12" id="detailRejectSection" style="display: none;">
                            <small class="text-danger">拒绝原因</small>
                            <p class="mb-0 text-danger" id="detailReject">-</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
let vouchers = [];
let currentIndex = -1;
let currentRotation = 0;
let currentScale = 1;

document.addEventListener('DOMContentLoaded', function() {
    loadVouchers();
});

function loadVouchers() {
    const urlParams = new URLSearchParams(window.location.search);
    const voucherId = urlParams.get('id');

    fetch('{{ route("front_api_account_voucher_list") }}?status=', {
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(res => res.json())
    .then(data => {
        if (data.success && data.vouchers) {
            vouchers = data.vouchers;
            renderThumbnails();

            if (voucherId) {
                const index = vouchers.findIndex(v => v.id == voucherId);
                if (index !== -1) {
                    selectVoucher(index);
                } else if (vouchers.length > 0) {
                    selectVoucher(0);
                }
            } else if (vouchers.length > 0) {
                selectVoucher(0);
            }
        }
    })
    .catch(err => {
        console.error('Load vouchers error:', err);
    });
}

function renderThumbnails() {
    const list = document.getElementById('thumbnailList');
    document.getElementById('totalCount').textContent = vouchers.length;

    if (vouchers.length === 0) {
        list.innerHTML = `
            <div class="text-center py-4 text-body-secondary">
                <i class="cil-inbox mb-2" style="font-size: 2rem;"></i>
                <p class="small mb-0">暂无凭证</p>
            </div>
        `;
        return;
    }

    const html = vouchers.map((v, index) => `
        <div class="thumbnail-item p-2 rounded mb-2 ${index === currentIndex ? 'active' : ''}" onclick="selectVoucher(${index})" style="cursor: pointer;">
            <div class="row g-2 align-items-center">
                <div class="col-4">
                    <img src="${v.thumbnail_url || v.file_url}" class="img-fluid rounded" alt="${v.type_name}" style="width: 100%; height: 60px; object-fit: cover;">
                </div>
                <div class="col-8">
                    <div class="small fw-semibold text-truncate">${v.type_name}</div>
                    <div class="small text-body-secondary text-truncate">${v.created_at}</div>
                    ${getStatusBadge(v.status)}
                </div>
            </div>
        </div>
    `).join('');

    list.innerHTML = html;
}

function selectVoucher(index) {
    if (index < 0 || index >= vouchers.length) return;

    currentIndex = index;
    const voucher = vouchers[index];

    // Update thumbnails active state
    document.querySelectorAll('.thumbnail-item').forEach((item, i) => {
        if (i === index) {
            item.classList.add('active');
        } else {
            item.classList.remove('active');
        }
    });

    // Display image
    displayImage(voucher);

    // Update details
    updateDetails(voucher);

    // Reset view
    resetView();
}

function displayImage(voucher) {
    const container = document.getElementById('imageContainer');
    container.innerHTML = `
        <img id="mainImage" src="${voucher.file_url}" class="img-fluid" alt="${voucher.type_name}" style="max-width: 100%; max-height: 600px; transition: transform 0.3s ease;">
    `;

    document.getElementById('currentTitle').textContent = voucher.type_name || '凭证详情';
    document.getElementById('currentInfo').textContent = `${currentIndex + 1} / ${vouchers.length}`;
}

function updateDetails(voucher) {
    document.getElementById('detailType').textContent = voucher.type_name || '-';
    document.getElementById('detailStatus').innerHTML = getStatusBadge(voucher.status);
    document.getElementById('detailCreated').textContent = voucher.created_at || '-';
    document.getElementById('detailSize').textContent = voucher.file_size || '-';

    if (voucher.note) {
        document.getElementById('detailNote').textContent = voucher.note;
        document.getElementById('detailNoteSection').style.display = 'block';
    } else {
        document.getElementById('detailNoteSection').style.display = 'none';
    }

    if (voucher.status === 'rejected' && voucher.reject_reason) {
        document.getElementById('detailReject').textContent = voucher.reject_reason;
        document.getElementById('detailRejectSection').style.display = 'block';
    } else {
        document.getElementById('detailRejectSection').style.display = 'none';
    }
}

function rotateImage(degrees) {
    currentRotation = (currentRotation + degrees) % 360;
    applyTransform();
}

function zoomIn() {
    currentScale = Math.min(currentScale + 0.2, 3);
    applyTransform();
}

function zoomOut() {
    currentScale = Math.max(currentScale - 0.2, 0.5);
    applyTransform();
}

function resetView() {
    currentRotation = 0;
    currentScale = 1;
    applyTransform();
}

function applyTransform() {
    const img = document.getElementById('mainImage');
    if (img) {
        img.style.transform = `rotate(${currentRotation}deg) scale(${currentScale})`;
    }
}

function downloadCurrent() {
    if (currentIndex >= 0 && currentIndex < vouchers.length) {
        const voucher = vouchers[currentIndex];
        window.location.href = '{{ route("front_api_account_voucher_download") }}?id=' + voucher.id;
    }
}

function getStatusBadge(status) {
    const badges = {
        'pending': '<span class="badge bg-warning text-dark">待审核</span>',
        'approved': '<span class="badge bg-success">已通过</span>',
        'rejected': '<span class="badge bg-danger">已拒绝</span>'
    };
    return badges[status] || '<span class="badge bg-secondary">未知</span>';
}

// Keyboard navigation
document.addEventListener('keydown', function(e) {
    if (e.key === 'ArrowLeft' && currentIndex > 0) {
        selectVoucher(currentIndex - 1);
    } else if (e.key === 'ArrowRight' && currentIndex < vouchers.length - 1) {
        selectVoucher(currentIndex + 1);
    }
});
</script>

<style>
.thumbnail-item {
    transition: all 0.2s ease;
    border: 2px solid transparent;
}

.thumbnail-item:hover {
    background-color: rgba(102, 126, 234, 0.1);
    border-color: rgba(102, 126, 234, 0.3);
}

.thumbnail-item.active {
    background-color: rgba(102, 126, 234, 0.15);
    border-color: #667eea;
}

.bg-primary-gradient {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
}

#imageContainer {
    user-select: none;
}

#mainImage {
    cursor: move;
}
</style>
@endsection
