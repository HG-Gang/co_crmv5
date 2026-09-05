@extends('front-coreui-v2.layouts.app')

@section('title', '组变更申请')

@section('content')
<div class="container-lg px-4">
    <!-- Page Header -->
    <div class="row mb-4">
        <div class="col-12">
            <h2 class="mb-2">组变更申请</h2>
            <p class="text-body-secondary mb-0">为客户申请MT4交易组变更</p>
        </div>
    </div>

    <!-- Stats Cards -->
    <div class="row g-4 mb-4">
        <div class="col-lg-3 col-md-6">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <p class="text-body-secondary mb-2">待审核</p>
                            <h4 class="mb-0 fw-bold text-warning" id="pendingCount">0</h4>
                        </div>
                        <div class="avatar avatar-sm bg-warning bg-opacity-10 text-warning">
                            <i class="cil-clock"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-3 col-md-6">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <p class="text-body-secondary mb-2">已批准</p>
                            <h4 class="mb-0 fw-bold text-success" id="approvedCount">0</h4>
                        </div>
                        <div class="avatar avatar-sm bg-success bg-opacity-10 text-success">
                            <i class="cil-check-circle"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-3 col-md-6">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <p class="text-body-secondary mb-2">已拒绝</p>
                            <h4 class="mb-0 fw-bold text-danger" id="rejectedCount">0</h4>
                        </div>
                        <div class="avatar avatar-sm bg-danger bg-opacity-10 text-danger">
                            <i class="cil-x-circle"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-3 col-md-6">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <p class="text-body-secondary mb-2">本月申请</p>
                            <h4 class="mb-0 fw-bold text-info" id="monthlyCount">0</h4>
                        </div>
                        <div class="avatar avatar-sm bg-info bg-opacity-10 text-info">
                            <i class="cil-list"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- New Application -->
    <div class="card shadow-sm border-0 mb-4">
        <div class="card-header bg-gradient-primary text-white">
            <h5 class="mb-0">
                <i class="cil-plus me-2"></i>新增变更申请
            </h5>
        </div>
        <div class="card-body">
            <form id="applicationForm">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">选择客户 <span class="text-danger">*</span></label>
                        <select class="form-select" id="customerId" onchange="loadCustomerInfo()">
                            <option value="">请选择客户</option>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">MT4账号</label>
                        <input type="text" class="form-control" id="mt4Account" readonly>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">当前组别</label>
                        <input type="text" class="form-control" id="currentGroup" readonly>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">目标组别 <span class="text-danger">*</span></label>
                        <select class="form-select" id="targetGroup">
                            <option value="">请选择目标组别</option>
                        </select>
                    </div>
                    <div class="col-12">
                        <label class="form-label">变更原因 <span class="text-danger">*</span></label>
                        <textarea class="form-control" id="reason" rows="3" placeholder="请详细说明变更原因"></textarea>
                    </div>
                    <div class="col-12">
                        <button type="button" class="btn btn-primary" onclick="submitApplication()">
                            <i class="cil-check me-2"></i>提交申请
                        </button>
                        <button type="button" class="btn btn-secondary" onclick="resetForm()">
                            <i class="cil-reload me-2"></i>重置
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Applications List -->
    <div class="card shadow-sm border-0">
        <div class="card-header bg-transparent border-bottom">
            <div class="d-flex justify-content-between align-items-center">
                <h5 class="mb-0">
                    <i class="cil-layers me-2"></i>申请记录
                </h5>
                <div class="d-flex align-items-center gap-2">
                    <select id="filterStatus" class="form-select form-select-sm" onchange="loadApplications()">
                        <option value="">全部状态</option>
                        <option value="pending">待审核</option>
                        <option value="approved">已批准</option>
                        <option value="rejected">已拒绝</option>
                    </select>
                </div>
            </div>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>申请编号</th>
                            <th>客户信息</th>
                            <th>MT4账号</th>
                            <th>当前组别</th>
                            <th>目标组别</th>
                            <th>申请时间</th>
                            <th>状态</th>
                            <th>操作</th>
                        </tr>
                    </thead>
                    <tbody id="applicationsTable">
                        <tr>
                            <td colspan="8" class="text-center py-5 text-body-secondary">
                                <div class="spinner-border spinner-border-sm me-2"></div>
                                加载中...
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
        <div class="card-footer bg-transparent border-top">
            <div class="d-flex justify-content-between align-items-center">
                <div class="text-body-secondary small">
                    共 <span id="totalRecords">0</span> 条申请记录
                </div>
                <nav>
                    <ul class="pagination pagination-sm mb-0" id="pagination">
                        <li class="page-item disabled">
                            <a class="page-link" href="#">上一页</a>
                        </li>
                        <li class="page-item active">
                            <a class="page-link" href="#">1</a>
                        </li>
                        <li class="page-item disabled">
                            <a class="page-link" href="#">下一页</a>
                        </li>
                    </ul>
                </nav>
            </div>
        </div>
    </div>
</div>

<script>
let currentPage = 1;

document.addEventListener('DOMContentLoaded', function() {
    loadStats();
    loadCustomers();
    loadGroups();
    loadApplications();
});

function loadStats() {
    fetch('{{ route("front_api_agent_group_change_stats") }}', {
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(res => res.json())
    .then(data => {
        if (data.success && data.stats) {
            document.getElementById('pendingCount').textContent = data.stats.pending || 0;
            document.getElementById('approvedCount').textContent = data.stats.approved || 0;
            document.getElementById('rejectedCount').textContent = data.stats.rejected || 0;
            document.getElementById('monthlyCount').textContent = data.stats.monthly || 0;
        }
    })
    .catch(err => {
        console.error('Load stats error:', err);
    });
}

function loadCustomers() {
    fetch('{{ route("front_api_agent_customers") }}', {
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(res => res.json())
    .then(data => {
        if (data.success && data.customers) {
            const select = document.getElementById('customerId');
            data.customers.forEach(customer => {
                const option = document.createElement('option');
                option.value = customer.id;
                option.textContent = `${customer.name} (${customer.mt4_account})`;
                select.appendChild(option);
            });
        }
    })
    .catch(err => {
        console.error('Load customers error:', err);
    });
}

function loadGroups() {
    fetch('{{ route("front_api_mt4_groups") }}', {
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(res => res.json())
    .then(data => {
        if (data.success && data.groups) {
            const select = document.getElementById('targetGroup');
            data.groups.forEach(group => {
                const option = document.createElement('option');
                option.value = group.name;
                option.textContent = group.name;
                select.appendChild(option);
            });
        }
    })
    .catch(err => {
        console.error('Load groups error:', err);
    });
}

function loadCustomerInfo() {
    const customerId = document.getElementById('customerId').value;
    if (!customerId) {
        document.getElementById('mt4Account').value = '';
        document.getElementById('currentGroup').value = '';
        return;
    }

    fetch(`{{ route("front_api_agent_customer_detail") }}?id=${customerId}`, {
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(res => res.json())
    .then(data => {
        if (data.success && data.customer) {
            document.getElementById('mt4Account').value = data.customer.mt4_account || '';
            document.getElementById('currentGroup').value = data.customer.mt4_group || '';
        }
    })
    .catch(err => {
        console.error('Load customer info error:', err);
    });
}

function submitApplication() {
    const customerId = document.getElementById('customerId').value;
    const targetGroup = document.getElementById('targetGroup').value;
    const reason = document.getElementById('reason').value;

    if (!customerId) {
        alert('请选择客户');
        return;
    }

    if (!targetGroup) {
        alert('请选择目标组别');
        return;
    }

    if (!reason.trim()) {
        alert('请填写变更原因');
        return;
    }

    fetch('{{ route("front_api_agent_group_change_submit") }}', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: JSON.stringify({
            customer_id: customerId,
            target_group: targetGroup,
            reason: reason
        })
    })
    .then(res => res.json())
    .then(data => {
        if (data.success || data.code === 200) {
            alert('申请提交成功');
            resetForm();
            loadStats();
            loadApplications();
        } else {
            alert(data.message || '提交失败');
        }
    })
    .catch(err => {
        console.error('Submit error:', err);
        alert('网络错误，请稍后重试');
    });
}

function resetForm() {
    document.getElementById('customerId').value = '';
    document.getElementById('mt4Account').value = '';
    document.getElementById('currentGroup').value = '';
    document.getElementById('targetGroup').value = '';
    document.getElementById('reason').value = '';
}

function loadApplications(page = 1) {
    currentPage = page;
    const status = document.getElementById('filterStatus').value;
    const params = new URLSearchParams();
    if (status) params.append('status', status);
    params.append('page', page);

    fetch('{{ route("front_api_agent_group_change_list") }}?' + params.toString(), {
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(res => res.json())
    .then(data => {
        if (data.success && data.applications) {
            renderApplications(data.applications);
            renderPagination(data.pagination);
            document.getElementById('totalRecords').textContent = data.pagination?.total || 0;
        }
    })
    .catch(err => {
        console.error('Load applications error:', err);
        showError('加载失败，请稍后重试');
    });
}

function renderApplications(applications) {
    const tbody = document.getElementById('applicationsTable');

    if (applications.length === 0) {
        tbody.innerHTML = `
            <tr>
                <td colspan="8" class="text-center py-5 text-body-secondary">
                    <i class="cil-inbox" style="font-size: 3rem; opacity: 0.3;"></i>
                    <p class="mt-3">暂无申请记录</p>
                </td>
            </tr>
        `;
        return;
    }

    const html = applications.map(a => `
        <tr>
            <td class="fw-semibold">#${a.id || '-'}</td>
            <td>
                <div class="d-flex align-items-center">
                    <div class="avatar avatar-sm bg-primary bg-opacity-10 text-primary me-3">
                        <i class="cil-user"></i>
                    </div>
                    <div>
                        <div class="fw-semibold">${a.customer_name || '-'}</div>
                        <small class="text-body-secondary">${a.customer_email || ''}</small>
                    </div>
                </div>
            </td>
            <td class="fw-semibold">${a.mt4_account || '-'}</td>
            <td><span class="badge bg-secondary">${a.current_group || '-'}</span></td>
            <td><span class="badge bg-primary">${a.target_group || '-'}</span></td>
            <td class="text-body-secondary small">${a.created_at || '-'}</td>
            <td>${getStatusBadge(a.status)}</td>
            <td>
                <a href="{{ route('front_coreui_v2_page_agent_group_change_detail') }}?id=${a.id}" class="btn btn-sm btn-outline-primary">
                    详情
                </a>
            </td>
        </tr>
    `).join('');

    tbody.innerHTML = html;
}

function renderPagination(pagination) {
    if (!pagination) return;

    const paginationEl = document.getElementById('pagination');
    let html = '';

    html += `<li class="page-item ${pagination.current_page === 1 ? 'disabled' : ''}">
        <a class="page-link" href="#" onclick="loadApplications(${pagination.current_page - 1}); return false;">上一页</a>
    </li>`;

    for (let i = 1; i <= pagination.last_page; i++) {
        if (i === 1 || i === pagination.last_page || (i >= pagination.current_page - 2 && i <= pagination.current_page + 2)) {
            html += `<li class="page-item ${i === pagination.current_page ? 'active' : ''}">
                <a class="page-link" href="#" onclick="loadApplications(${i}); return false;">${i}</a>
            </li>`;
        } else if (i === pagination.current_page - 3 || i === pagination.current_page + 3) {
            html += `<li class="page-item disabled"><span class="page-link">...</span></li>`;
        }
    }

    html += `<li class="page-item ${pagination.current_page === pagination.last_page ? 'disabled' : ''}">
        <a class="page-link" href="#" onclick="loadApplications(${pagination.current_page + 1}); return false;">下一页</a>
    </li>`;

    paginationEl.innerHTML = html;
}

function getStatusBadge(status) {
    if (status === 'pending') {
        return '<span class="badge bg-warning">待审核</span>';
    } else if (status === 'approved') {
        return '<span class="badge bg-success">已批准</span>';
    } else if (status === 'rejected') {
        return '<span class="badge bg-danger">已拒绝</span>';
    }
    return '<span class="badge bg-secondary">-</span>';
}

function showError(message) {
    document.getElementById('applicationsTable').innerHTML = `
        <tr>
            <td colspan="8" class="text-center py-5">
                <i class="cil-warning text-danger" style="font-size: 3rem;"></i>
                <p class="text-danger mt-3">${message}</p>
            </td>
        </tr>
    `;
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

.avatar-sm {
    width: 36px;
    height: 36px;
    font-size: 1.1rem;
}
</style>
@endsection
