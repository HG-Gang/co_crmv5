@extends('front-coreui-v2.layouts.app')

@section('title', '礼品列表')

@section('content')
<div class="container-lg px-4">
    <!-- Page Header -->
    <div class="row mb-4">
        <div class="col-12">
            <h2 class="mb-2">礼品兑换</h2>
            <p class="text-body-secondary mb-0">使用积分兑换精美礼品</p>
        </div>
    </div>

    <!-- User Points Card -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card shadow-sm border-0 bg-gradient-primary text-white">
                <div class="card-body">
                    <div class="row align-items-center">
                        <div class="col-md-8">
                            <div class="d-flex align-items-center">
                                <div class="avatar avatar-lg bg-white bg-opacity-25 text-white me-3">
                                    <i class="cil-gift"></i>
                                </div>
                                <div>
                                    <p class="mb-1 opacity-75">我的可用积分</p>
                                    <h2 class="mb-0" id="userPoints">0</h2>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4 text-md-end mt-3 mt-md-0">
                            <a href="{{ route('front_coreui_v2_page_gift_address') }}" class="btn btn-light">
                                <i class="cil-location-pin me-2"></i>管理收货地址
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Filters -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card shadow-sm border-0">
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-3">
                            <select class="form-select" id="categoryFilter" onchange="applyFilters()">
                                <option value="">全部分类</option>
                                <option value="electronics">电子产品</option>
                                <option value="household">家居用品</option>
                                <option value="food">食品饮料</option>
                                <option value="sports">运动户外</option>
                                <option value="other">其他</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <select class="form-select" id="pointsFilter" onchange="applyFilters()">
                                <option value="">全部积分</option>
                                <option value="0-1000">0-1000积分</option>
                                <option value="1000-5000">1000-5000积分</option>
                                <option value="5000-10000">5000-10000积分</option>
                                <option value="10000+">10000+积分</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <select class="form-select" id="statusFilter" onchange="applyFilters()">
                                <option value="">全部状态</option>
                                <option value="available">可兑换</option>
                                <option value="out_of_stock">已售罄</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <select class="form-select" id="sortBy" onchange="applyFilters()">
                                <option value="newest">最新上架</option>
                                <option value="points_asc">积分从低到高</option>
                                <option value="points_desc">积分从高到低</option>
                                <option value="popular">最受欢迎</option>
                            </select>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Gift Grid -->
    <div class="row g-4" id="giftGrid">
        <div class="col-12 text-center py-5">
            <div class="spinner-border spinner-border-sm me-2"></div>
            加载中...
        </div>
    </div>

    <!-- Pagination -->
    <div class="row mt-4">
        <div class="col-12">
            <nav id="paginationNav" style="display: none;">
                <ul class="pagination justify-content-center" id="pagination"></ul>
            </nav>
        </div>
    </div>
</div>

<!-- Exchange Modal -->
<div class="modal fade" id="exchangeModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-gradient-primary text-white">
                <h5 class="modal-title">
                    <i class="cil-gift me-2"></i>确认兑换
                </h5>
                <button type="button" class="btn-close btn-close-white" data-coreui-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="row g-4">
                    <div class="col-md-4">
                        <img id="modalGiftImage" src="" class="img-fluid rounded" alt="Gift">
                    </div>
                    <div class="col-md-8">
                        <h5 id="modalGiftName" class="mb-3">-</h5>
                        <div class="mb-3">
                            <label class="text-body-secondary small mb-1">所需积分</label>
                            <h4 class="text-primary mb-0" id="modalGiftPoints">0</h4>
                        </div>
                        <div class="mb-3">
                            <label class="text-body-secondary small mb-1">我的可用积分</label>
                            <h5 class="mb-0" id="modalUserPoints">0</h5>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">兑换数量</label>
                            <div class="input-group" style="max-width: 200px;">
                                <button class="btn btn-outline-secondary" onclick="decreaseQuantity()">-</button>
                                <input type="number" class="form-control text-center" id="exchangeQuantity" value="1" min="1" onchange="updateTotalPoints()">
                                <button class="btn btn-outline-secondary" onclick="increaseQuantity()">+</button>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">收货地址 <span class="text-danger">*</span></label>
                            <select class="form-select" id="addressSelect">
                                <option value="">请选择收货地址</option>
                            </select>
                            <div class="form-text">
                                <a href="{{ route('front_coreui_v2_page_gift_address') }}" target="_blank">管理地址</a>
                            </div>
                        </div>
                        <div class="alert alert-info mb-0">
                            <div class="d-flex justify-content-between align-items-center">
                                <span>所需总积分:</span>
                                <h4 class="mb-0 text-info" id="totalPoints">0</h4>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-coreui-dismiss="modal">
                    <i class="cil-x me-2"></i>取消
                </button>
                <button type="button" class="btn btn-primary" onclick="confirmExchange()">
                    <i class="cil-check me-2"></i>确认兑换
                </button>
            </div>
        </div>
    </div>
</div>

<script>
let currentPage = 1;
let currentGift = null;
let userPoints = 0;

document.addEventListener('DOMContentLoaded', function() {
    loadUserPoints();
    loadGifts(1);
    loadAddresses();
});

function loadUserPoints() {
    fetch('{{ route("front_api_gift_user_points") }}', {
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(res => res.json())
    .then(data => {
        if (data.success && data.points !== undefined) {
            userPoints = parseInt(data.points);
            document.getElementById('userPoints').textContent = userPoints.toLocaleString();
        }
    })
    .catch(err => {
        console.error('Load points error:', err);
    });
}

function loadGifts(page) {
    currentPage = page;
    const params = new URLSearchParams({
        page: page,
        category: document.getElementById('categoryFilter').value,
        points_range: document.getElementById('pointsFilter').value,
        status: document.getElementById('statusFilter').value,
        sort: document.getElementById('sortBy').value
    });

    fetch(`{{ route("front_api_gift_list") }}?${params.toString()}`, {
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(res => res.json())
    .then(data => {
        if (data.success && data.gifts) {
            renderGifts(data.gifts);
            renderPagination(data.pagination || {});
        }
    })
    .catch(err => {
        console.error('Load gifts error:', err);
        showError('加载失败，请稍后重试');
    });
}

function renderGifts(gifts) {
    const container = document.getElementById('giftGrid');

    if (gifts.length === 0) {
        container.innerHTML = `
            <div class="col-12">
                <div class="card shadow-sm border-0">
                    <div class="card-body text-center py-5">
                        <i class="cil-gift text-body-secondary" style="font-size: 3rem; opacity: 0.3;"></i>
                        <p class="text-body-secondary mt-3 mb-0">暂无礼品</p>
                    </div>
                </div>
            </div>
        `;
        return;
    }

    const html = gifts.map(gift => `
        <div class="col-lg-3 col-md-4 col-sm-6">
            <div class="card shadow-sm border-0 h-100 gift-card">
                <div class="position-relative">
                    <img src="${gift.image_url || '/images/gift-placeholder.jpg'}" class="card-img-top" alt="${gift.name || ''}" style="height: 200px; object-fit: cover;">
                    ${gift.stock <= 0 ? '<div class="position-absolute top-0 end-0 m-2"><span class="badge bg-danger">已售罄</span></div>' : ''}
                    ${gift.is_hot ? '<div class="position-absolute top-0 start-0 m-2"><span class="badge bg-warning">热门</span></div>' : ''}
                </div>
                <div class="card-body d-flex flex-column">
                    <h6 class="card-title mb-2">${gift.name || '-'}</h6>
                    <p class="card-text text-body-secondary small flex-grow-1">${gift.description || '暂无描述'}</p>
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span class="text-body-secondary small">所需积分</span>
                        <h5 class="text-primary mb-0">${gift.points || 0}</h5>
                    </div>
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <span class="text-body-secondary small">剩余库存</span>
                        <span class="badge ${gift.stock > 10 ? 'bg-success' : gift.stock > 0 ? 'bg-warning' : 'bg-danger'}">${gift.stock || 0}</span>
                    </div>
                    <button class="btn btn-primary w-100" onclick="openExchangeModal(${gift.id}, '${escapeHtml(gift.name)}', ${gift.points}, '${gift.image_url || ''}', ${gift.stock})" ${gift.stock <= 0 ? 'disabled' : ''}>
                        <i class="cil-gift me-2"></i>${gift.stock > 0 ? '立即兑换' : '已售罄'}
                    </button>
                </div>
            </div>
        </div>
    `).join('');

    container.innerHTML = html;
}

function renderPagination(pagination) {
    const nav = document.getElementById('paginationNav');
    const ul = document.getElementById('pagination');

    if (!pagination.total || pagination.total <= 1) {
        nav.style.display = 'none';
        return;
    }

    nav.style.display = 'block';
    const currentPage = pagination.current_page || 1;
    const lastPage = pagination.last_page || 1;

    let html = '';

    // Previous
    html += `<li class="page-item ${currentPage === 1 ? 'disabled' : ''}">
        <a class="page-link" href="#" onclick="loadGifts(${currentPage - 1}); return false;">上一页</a>
    </li>`;

    // Pages
    for (let i = 1; i <= lastPage; i++) {
        if (i === 1 || i === lastPage || (i >= currentPage - 2 && i <= currentPage + 2)) {
            html += `<li class="page-item ${i === currentPage ? 'active' : ''}">
                <a class="page-link" href="#" onclick="loadGifts(${i}); return false;">${i}</a>
            </li>`;
        } else if (i === currentPage - 3 || i === currentPage + 3) {
            html += `<li class="page-item disabled"><span class="page-link">...</span></li>`;
        }
    }

    // Next
    html += `<li class="page-item ${currentPage === lastPage ? 'disabled' : ''}">
        <a class="page-link" href="#" onclick="loadGifts(${currentPage + 1}); return false;">下一页</a>
    </li>`;

    ul.innerHTML = html;
}

function loadAddresses() {
    fetch('{{ route("front_api_gift_addresses") }}', {
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(res => res.json())
    .then(data => {
        if (data.success && data.addresses) {
            renderAddressOptions(data.addresses);
        }
    })
    .catch(err => {
        console.error('Load addresses error:', err);
    });
}

function renderAddressOptions(addresses) {
    const select = document.getElementById('addressSelect');

    if (addresses.length === 0) {
        select.innerHTML = '<option value="">暂无地址，请先添加</option>';
        return;
    }

    let html = '<option value="">请选择收货地址</option>';
    addresses.forEach(addr => {
        const addressText = [addr.province, addr.city, addr.district, addr.address].filter(Boolean).join(' ');
        html += `<option value="${addr.id}">${addr.receiver_name} - ${addressText}${addr.is_default ? ' (默认)' : ''}</option>`;

        if (addr.is_default) {
            setTimeout(() => {
                select.value = addr.id;
            }, 100);
        }
    });

    select.innerHTML = html;
}

function applyFilters() {
    loadGifts(1);
}

function openExchangeModal(id, name, points, imageUrl, stock) {
    if (stock <= 0) {
        alert('该礼品已售罄');
        return;
    }

    currentGift = { id, name, points, imageUrl, stock };

    document.getElementById('modalGiftName').textContent = name;
    document.getElementById('modalGiftPoints').textContent = points.toLocaleString();
    document.getElementById('modalGiftImage').src = imageUrl || '/images/gift-placeholder.jpg';
    document.getElementById('modalUserPoints').textContent = userPoints.toLocaleString();
    document.getElementById('exchangeQuantity').value = 1;
    document.getElementById('exchangeQuantity').max = stock;
    updateTotalPoints();

    const modal = new coreui.Modal(document.getElementById('exchangeModal'));
    modal.show();
}

function increaseQuantity() {
    const input = document.getElementById('exchangeQuantity');
    const current = parseInt(input.value);
    const max = parseInt(input.max) || 99;
    if (current < max) {
        input.value = current + 1;
        updateTotalPoints();
    }
}

function decreaseQuantity() {
    const input = document.getElementById('exchangeQuantity');
    const current = parseInt(input.value);
    if (current > 1) {
        input.value = current - 1;
        updateTotalPoints();
    }
}

function updateTotalPoints() {
    const quantity = parseInt(document.getElementById('exchangeQuantity').value) || 1;
    const total = currentGift.points * quantity;
    document.getElementById('totalPoints').textContent = total.toLocaleString();
}

function confirmExchange() {
    if (!currentGift) return;

    const quantity = parseInt(document.getElementById('exchangeQuantity').value);
    const addressId = document.getElementById('addressSelect').value;
    const totalPoints = currentGift.points * quantity;

    if (!addressId) {
        alert('请选择收货地址');
        return;
    }

    if (totalPoints > userPoints) {
        alert('您的积分不足');
        return;
    }

    fetch('{{ route("front_api_gift_exchange") }}', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: JSON.stringify({
            gift_id: currentGift.id,
            quantity: quantity,
            address_id: addressId
        })
    })
    .then(res => res.json())
    .then(data => {
        if (data.success || data.code === 200) {
            alert('兑换成功！礼品将尽快配送');
            coreui.Modal.getInstance(document.getElementById('exchangeModal')).hide();
            loadUserPoints();
            loadGifts(currentPage);
        } else {
            alert(data.message || '兑换失败');
        }
    })
    .catch(err => {
        console.error('Exchange error:', err);
        alert('网络错误，请稍后重试');
    });
}

function showError(message) {
    document.getElementById('giftGrid').innerHTML = `
        <div class="col-12">
            <div class="card shadow-sm border-0">
                <div class="card-body text-center py-5">
                    <i class="cil-warning text-danger" style="font-size: 3rem;"></i>
                    <p class="text-danger mt-3 mb-0">${message}</p>
                </div>
            </div>
        </div>
    `;
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}
</script>

<style>
.bg-gradient-primary {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
}

.avatar {
    width: 40px;
    height: 40px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 8px;
    font-size: 1.25rem;
}

.avatar-lg {
    width: 64px;
    height: 64px;
    font-size: 2rem;
}

.gift-card {
    transition: transform 0.2s, box-shadow 0.2s;
}

.gift-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15) !important;
}
</style>
@endsection
