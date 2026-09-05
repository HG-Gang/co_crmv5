@extends('front-coreui-v2.layouts.app')

@section('title', '客户管理')

@section('content')
<div class="container-lg px-4">
    <!-- Page Header -->
    <div class="row mb-4">
        <div class="col-12">
            <h2 class="mb-2">客户管理</h2>
            <p class="text-body-secondary mb-0">查看和管理所有下级客户信息</p>
        </div>
    </div>

    <!-- Summary Cards -->
    <div class="row g-4 mb-4">
        <div class="col-lg-3 col-md-6">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <p class="text-body-secondary mb-2">客户总数</p>
                            <h4 class="mb-0 fw-bold text-primary" id="totalCustomers">0</h4>
                        </div>
                        <div class="avatar avatar-sm bg-primary bg-opacity-10 text-primary">
                            <i class="cil-user"></i>
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
                            <p class="text-body-secondary mb-2">活跃客户</p>
                            <h4 class="mb-0 fw-bold text-success" id="activeCustomers">0</h4>
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
                            <p class="text-body-secondary mb-2">总交易手数</p>
                            <h4 class="mb-0 fw-bold text-info" id="totalLots">0.00</h4>
                        </div>
                        <div class="avatar avatar-sm bg-info bg-opacity-10 text-info">
                            <i class="cil-chart-line"></i>
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
                            <p class="text-body-secondary mb-2">本月新增</p>
                            <h4 class="mb-0 fw-bold text-warning" id="monthlyNew">0</h4>
                        </div>
                        <div class="avatar avatar-sm bg-warning bg-opacity-10 text-warning">
                            <i class="cil-plus"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Filter Bar -->
    <div class="card shadow-sm border-0 mb-4">
        <div class="card-body">
            <div class="row g-3 align-items-end">
                <div class="col-md-2">
                    <label class="form-label small">客户状态</label>
                    <select id="filterStatus" class="form-select">
                        <option value="">全部状态</option>
                        <option value="active">活跃</option>
                        <option value="inactive">未激活</option>
                        <option value="frozen">冻结</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label small">认证状态</label>
                    <select id="filterVerified" class="form-select">
                        <option value="">全部</option>
                        <option value="1">已认证</option>
                        <option value="0">未认证</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label small">交易状态</label>
                    <select id="filterTrading" class="form-select">
                        <option value="">全部</option>
                        <option value="active">有交易</option>
                        <option value="inactive">无交易</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label small">注册时间</label>
                    <select id="filterTime" class="form-select">
                        <option value="">全部时间</option>
                        <option value="today">今天</option>
                        <option value="week">本周</option>
                        <option value="month">本月</option>
                        <option value="year">本年</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label small">搜索客户</label>
                    <input type="text" id="filterKeyword" class="form-control" placeholder="姓名/邮箱/MT4">
                </div>
                <div class="col-md-2">
                    <div class="d-flex gap-2">
                        <button onclick="loadCustomers()" class="btn btn-primary flex-grow-1">
                            <i class="cil-search me-2"></i>查询
                        </button>
                        <button onclick="resetFilters()" class="btn btn-outline-secondary">
                            <i class="cil-reload"></i>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Customers Table -->
    <div class="card shadow-sm border-0">
        <div class="card-header bg-transparent border-bottom">
            <div class="d-flex justify-content-between align-items-center">
                <h5 class="mb-0">
                    <i class="cil-layers me-2"></i>客户列表
                </h5>
                <button onclick="exportData()" class="btn btn-sm btn-outline-secondary">
                    <i class="cil-cloud-download me-1"></i>导出
                </button>
            </div>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>客户信息</th>
                            <th>MT4账号</th>
                            <th>账户余额</th>
                            <th>总交易手数</th>
                            <th>盈亏状况</th>
                            <th>认证状态</th>
                            <th>注册时间</th>
                            <th>状态</th>
                            <th>操作</th>
                        </tr>
                    </thead>
                    <tbody id="customersTable">
                        <tr>
                            <td colspan="9" class="text-center py-5 text-body-secondary">
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
                    共 <span id="totalRecords">0</span> 条客户记录
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
    loadSummary();
    loadCustomers();
});

function loadSummary() {
    fetch('{{ route("front_api_agent_customers_summary") }}', {
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(res => res.json())
    .then(data => {
        if (data.success && data.summary) {
            document.getElementById('totalCustomers').textContent = data.summary.total_customers || 0;
            document.getElementById('activeCustomers').textContent = data.summary.active_customers || 0;
            document.getElementById('totalLots').textContent = formatNumber(data.summary.total_lots);
            document.getElementById('monthlyNew').textContent = data.summary.monthly_new || 0;
        }
    })
    .catch(err => {
        console.error('Load summary error:', err);
    });
}

function loadCustomers(page = 1) {
    currentPage = page;
    const params = getFilterParams();
    params.append('page', page);

    fetch('{{ route("front_api_agent_customers_list") }}?' + params.toString(), {
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(res => res.json())
    .then(data => {
        if (data.success && data.customers) {
            renderCustomers(data.customers);
            renderPagination(data.pagination);
            document.getElementById('totalRecords').textContent = data.pagination?.total || 0;
        }
    })
    .catch(err => {
        console.error('Load customers error:', err);
        showError('加载失败，请稍后重试');
    });
}

function renderCustomers(customers) {
    const tbody = document.getElementById('customersTable');

    if (customers.length === 0) {
        tbody.innerHTML = `
            <tr>
                <td colspan="9" class="text-center py-5 text-body-secondary">
                    <i class="cil-inbox" style="font-size: 3rem; opacity: 0.3;"></i>
                    <p class="mt-3">暂无客户记录</p>
                </td>
            </tr>
        `;
        return;
    }

    const html = customers.map(c => `
        <tr>
            <td>
                <div class="d-flex align-items-center">
                    <div class="avatar avatar-sm bg-primary bg-opacity-10 text-primary me-3">
                        <i class="cil-user"></i>
                    </div>
                    <div>
                        <div class="fw-semibold">${c.name || '-'}</div>
                        <small class="text-body-secondary">${c.email || ''}</small>
                    </div>
                </div>
            </td>
            <td class="fw-semibold">${c.mt4_account || '-'}</td>
            <td class="text-success fw-semibold">${formatCurrency(c.balance)}</td>
            <td class="fw-semibold">${formatNumber(c.total_lots)}</td>
            <td>
                <div class="${c.profit >= 0 ? 'text-success' : 'text-danger'} fw-semibold">
                    ${c.profit >= 0 ? '+' : ''}${formatCurrency(c.profit)}
                </div>
            </td>
            <td>${getVerifiedBadge(c.is_verified)}</td>
            <td class="text-body-secondary small">${c.created_at || '-'}</td>
            <td>${getStatusBadge(c.status)}</td>
            <td>
                <div class="btn-group btn-group-sm">
                    <a href="{{ route('front_coreui_v2_page_agent_customers_detail') }}?id=${c.id}" class="btn btn-outline-primary">
                        详情
                    </a>
                    <button onclick="viewTrades(${c.id})" class="btn btn-outline-secondary">
                        交易
                    </button>
                </div>
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
        <a class="page-link" href="#" onclick="loadCustomers(${pagination.current_page - 1}); return false;">上一页</a>
    </li>`;

    for (let i = 1; i <= pagination.last_page; i++) {
        if (i === 1 || i === pagination.last_page || (i >= pagination.current_page - 2 && i <= pagination.current_page + 2)) {
            html += `<li class="page-item ${i === pagination.current_page ? 'active' : ''}">
                <a class="page-link" href="#" onclick="loadCustomers(${i}); return false;">${i}</a>
            </li>`;
        } else if (i === pagination.current_page - 3 || i === pagination.current_page + 3) {
            html += `<li class="page-item disabled"><span class="page-link">...</span></li>`;
        }
    }

    html += `<li class="page-item ${pagination.current_page === pagination.last_page ? 'disabled' : ''}">
        <a class="page-link" href="#" onclick="loadCustomers(${pagination.current_page + 1}); return false;">下一页</a>
    </li>`;

    paginationEl.innerHTML = html;
}

function getFilterParams() {
    const params = new URLSearchParams();
    const status = document.getElementById('filterStatus').value;
    const verified = document.getElementById('filterVerified').value;
    const trading = document.getElementById('filterTrading').value;
    const time = document.getElementById('filterTime').value;
    const keyword = document.getElementById('filterKeyword').value;

    if (status) params.append('status', status);
    if (verified) params.append('verified', verified);
    if (trading) params.append('trading', trading);
    if (time) params.append('time', time);
    if (keyword) params.append('keyword', keyword);

    return params;
}

function resetFilters() {
    document.getElementById('filterStatus').value = '';
    document.getElementById('filterVerified').value = '';
    document.getElementById('filterTrading').value = '';
    document.getElementById('filterTime').value = '';
    document.getElementById('filterKeyword').value = '';
    loadCustomers();
}

function viewTrades(customerId) {
    window.location.href = `{{ route('front_coreui_v2_page_order_closed') }}?customer=${customerId}`;
}

function exportData() {
    const params = getFilterParams();
    window.location.href = '{{ route("front_api_agent_customers_export") }}?' + params.toString();
}

function getVerifiedBadge(verified) {
    if (verified === 1 || verified === true) {
        return '<span class="badge bg-success"><i class="cil-check me-1"></i>已认证</span>';
    }
    return '<span class="badge bg-secondary">未认证</span>';
}

function getStatusBadge(status) {
    if (status === 'active') {
        return '<span class="badge bg-success">活跃</span>';
    } else if (status === 'inactive') {
        return '<span class="badge bg-secondary">未激活</span>';
    } else if (status === 'frozen') {
        return '<span class="badge bg-danger">冻结</span>';
    }
    return '<span class="badge bg-secondary">-</span>';
}

function formatCurrency(value) {
    if (!value && value !== 0) return '-';
    return '$' + parseFloat(value).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

function formatNumber(value) {
    if (!value && value !== 0) return '-';
    return parseFloat(value).toFixed(2);
}

function showError(message) {
    document.getElementById('customersTable').innerHTML = `
        <tr>
            <td colspan="9" class="text-center py-5">
                <i class="cil-warning text-danger" style="font-size: 3rem;"></i>
                <p class="text-danger mt-3">${message}</p>
            </td>
        </tr>
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
</style>
@endsection
