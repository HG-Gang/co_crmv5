@extends('front-coreui-v2.layouts.app')

@section('title', '礼品地址')

@section('content')
<div class="container-lg px-4">
    <!-- Page Header -->
    <div class="row mb-4">
        <div class="col-12">
            <h2 class="mb-2">礼品地址</h2>
            <p class="text-body-secondary mb-0">管理您的礼品收货地址</p>
        </div>
    </div>

    <!-- Address List -->
    <div class="row g-4" id="addressList">
        <div class="col-12 text-center py-5">
            <div class="spinner-border spinner-border-sm me-2"></div>
            加载中...
        </div>
    </div>

    <!-- Add Address Button -->
    <div class="row mt-4">
        <div class="col-12">
            <a href="{{ route('front_coreui_v2_page_gift_address_add') }}" class="btn btn-primary">
                <i class="cil-plus me-2"></i>添加新地址
            </a>
        </div>
    </div>
</div>

<!-- Delete Confirm Modal -->
<div class="modal fade" id="deleteModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="cil-warning me-2"></i>确认删除
                </h5>
                <button type="button" class="btn-close" data-coreui-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p>确定要删除这个地址吗？</p>
                <p class="text-danger mb-0">此操作不可撤销</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-coreui-dismiss="modal">取消</button>
                <button type="button" class="btn btn-danger" onclick="confirmDelete()">
                    <i class="cil-trash me-2"></i>确认删除
                </button>
            </div>
        </div>
    </div>
</div>

<script>
let deleteAddressId = null;

document.addEventListener('DOMContentLoaded', function() {
    loadAddresses();
});

function loadAddresses() {
    fetch('{{ route("front_api_gift_addresses") }}', {
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(res => res.json())
    .then(data => {
        if (data.success && data.addresses) {
            renderAddresses(data.addresses);
        }
    })
    .catch(err => {
        console.error('Load addresses error:', err);
        showError('加载失败，请稍后重试');
    });
}

function renderAddresses(addresses) {
    const container = document.getElementById('addressList');

    if (addresses.length === 0) {
        container.innerHTML = `
            <div class="col-12">
                <div class="card shadow-sm border-0">
                    <div class="card-body text-center py-5">
                        <i class="cil-location-pin text-body-secondary" style="font-size: 3rem; opacity: 0.3;"></i>
                        <p class="text-body-secondary mt-3 mb-0">暂无收货地址，请添加新地址</p>
                    </div>
                </div>
            </div>
        `;
        return;
    }

    const html = addresses.map(addr => `
        <div class="col-lg-6">
            <div class="card shadow-sm border-0 h-100 ${addr.is_default ? 'border-primary' : ''}">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start mb-3">
                        <div>
                            <h5 class="mb-1">
                                ${addr.receiver_name || '-'}
                                ${addr.is_default ? '<span class="badge bg-primary ms-2">默认</span>' : ''}
                            </h5>
                            <p class="text-body-secondary mb-0">
                                <i class="cil-phone me-1"></i>${addr.phone || '-'}
                            </p>
                        </div>
                        <div class="avatar avatar-sm bg-primary bg-opacity-10 text-primary">
                            <i class="cil-location-pin"></i>
                        </div>
                    </div>

                    <div class="mb-3">
                        <p class="text-body-secondary small mb-1">收货地址</p>
                        <p class="mb-0">${formatAddress(addr)}</p>
                    </div>

                    ${addr.postal_code ? `
                    <div class="mb-3">
                        <p class="text-body-secondary small mb-1">邮政编码</p>
                        <p class="mb-0">${addr.postal_code}</p>
                    </div>
                    ` : ''}

                    <div class="d-flex gap-2">
                        <a href="{{ route('front_coreui_v2_page_gift_address_edit') }}?id=${addr.id}" class="btn btn-sm btn-outline-primary">
                            <i class="cil-pencil me-1"></i>编辑
                        </a>
                        ${!addr.is_default ? `
                        <button class="btn btn-sm btn-outline-success" onclick="setDefault(${addr.id})">
                            <i class="cil-star me-1"></i>设为默认
                        </button>
                        ` : ''}
                        <button class="btn btn-sm btn-outline-danger" onclick="deleteAddress(${addr.id})">
                            <i class="cil-trash me-1"></i>删除
                        </button>
                    </div>
                </div>
            </div>
        </div>
    `).join('');

    container.innerHTML = html;
}

function formatAddress(addr) {
    const parts = [];
    if (addr.country) parts.push(addr.country);
    if (addr.province) parts.push(addr.province);
    if (addr.city) parts.push(addr.city);
    if (addr.district) parts.push(addr.district);
    if (addr.address) parts.push(addr.address);
    return parts.join(' ') || '-';
}

function setDefault(id) {
    fetch('{{ route("front_api_gift_address_set_default") }}', {
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
            loadAddresses();
        } else {
            alert(data.message || '设置失败');
        }
    })
    .catch(err => {
        console.error('Set default error:', err);
        alert('网络错误，请稍后重试');
    });
}

function deleteAddress(id) {
    deleteAddressId = id;
    const modal = new coreui.Modal(document.getElementById('deleteModal'));
    modal.show();
}

function confirmDelete() {
    if (!deleteAddressId) return;

    fetch('{{ route("front_api_gift_address_delete") }}', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: JSON.stringify({ id: deleteAddressId })
    })
    .then(res => res.json())
    .then(data => {
        if (data.success || data.code === 200) {
            coreui.Modal.getInstance(document.getElementById('deleteModal')).hide();
            loadAddresses();
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
    document.getElementById('addressList').innerHTML = `
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
</script>

<style>
.avatar {
    width: 40px;
    height: 40px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 8px;
    font-size: 1.25rem;
}

.avatar-sm {
    width: 36px;
    height: 36px;
    font-size: 1.1rem;
}

.card.border-primary {
    border-width: 2px !important;
}
</style>
@endsection
