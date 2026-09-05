@extends('front-coreui-v2.layouts.app')

@section('title', '实时返佣')

@section('content')
<div class="container-lg px-4">
    <!-- Page Header -->
    <div class="row mb-4">
        <div class="col-12">
            <h2 class="mb-2">实时返佣</h2>
            <p class="text-body-secondary mb-0">查看下级客户交易产生的实时返佣记录</p>
        </div>
    </div>

    <!-- Stats Cards -->
    <div class="row g-4 mb-4">
        <div class="col-lg-3 col-md-6">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <p class="text-body-secondary mb-2">今日返佣</p>
                            <h4 class="mb-0 fw-bold text-success" id="todayCommission">$0.00</h4>
                        </div>
                        <div class="avatar avatar-sm bg-success bg-opacity-10 text-success">
                            <i class="cil-dollar"></i>
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
                            <p class="text-body-secondary mb-2">本周返佣</p>
                            <h4 class="mb-0 fw-bold text-info" id="weekCommission">$0.00</h4>
                        </div>
                        <div class="avatar avatar-sm bg-info bg-opacity-10 text-info">
                            <i class="cil-calendar"></i>
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
                            <p class="text-body-secondary mb-2">本月返佣</p>
                            <h4 class="mb-0 fw-bold text-primary" id="monthCommission">$0.00</h4>
                        </div>
                        <div class="avatar avatar-sm bg-primary bg-opacity-10 text-primary">
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
                            <p class="text-body-secondary mb-2">累计返佣</p>
                            <h4 class="mb-0 fw-bold text-warning" id="totalCommission">$0.00</h4>
                        </div>
                        <div class="avatar avatar-sm bg-warning bg-opacity-10 text-warning">
                            <i class="cil-layers"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Commission List -->
    <div class="card shadow-sm border-0">
        <div class="card-header bg-transparent border-bottom">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
                <h5 class="mb-0">
                    <i class="cil-list me-2"></i>返佣记录
                </h5>
                <div class="d-flex align-items-center gap-2 flex-wrap">
                    <select id="filterType" class="form-select form-select-sm" style="width: auto;" onchange="loadCommissions()">
                        <option value="">全部类型</option>
                        <option value="direct">直接返佣</option>
                        <option value="indirect">间接返佣</option>
                    </select>
                    <select id="filterPeriod" class="form-select form-select-sm" style="width: auto;" onchange="loadCommissions()">
                        <option value="today">今日</option>
                        <option value="week">本周</option>
                        <option value="month">本月</option>
                        <option value="all">全部</option>
                    </select>
                    <button class="btn btn-sm btn-outline-primary" onclick="refreshData()">
                        <i class="cil-reload me-1"></i>刷新
                    </button>
                    <button class="btn btn-sm btn-success" onclick="exportCommissions()">
                        <i class="cil-cloud-download me-1"></i>导出
                    </button>
                </div>
            </div>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>订单编号</th>
                            <th>客户信息</th>
                            <th>交易品种</th>
                            <th>交易手数</th>
                            <th>返佣金额</th>
                            <th>返佣类型</th>
                            <th>交易时间</th>
                            <th>操作</th>
                        </tr>
                    </thead>
                    <tbody id="commissionsTable">
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
                    共 <span id="totalRecords">0</span> 条返佣记录
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
let autoRefreshTimer = null;

document.addEventListener('DOMContentLoaded', function() {
    loadStats();
    loadCommissions();
    startAutoRefresh();
});

function loadStats() {
    fetch('{{ route("front_api_commission_stats") }}', {
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(res => res.json())
    .then(data => {
        if (data.success && data.stats) {
            document.getElementById('todayCommission').textContent = formatCurrency(data.stats.today);
            document.getElementById('weekCommission').textContent = formatCurrency(data.stats.week);
            document.getElementById('monthCommission').textContent = formatCurrency(data.stats.month);
            document.getElementById('totalCommission').textContent = formatCurrency(data.stats.total);
        }
    })
    .catch(err => {
        console.error('Load stats error:', err);
    });
}

function loadCommissions(page = 1) {
    currentPage = page;
    const type = document.getElementById('filterType').value;
    const period = document.getElementById('filterPeriod').value;
    const params = new URLSearchParams();
    if (type) params.append('type', type);
    if (period) params.append('period', period);
    params.append('page', page);

    fetch('{{ route("front_api_commission_realtime") }}?' + params.toString(), {
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(res => res.json())
    .then(data => {
        if (data.success && data.commissions) {
            renderCommissions(data.commissions);
            renderPagination(data.pagination);
            document.getElementById('totalRecords').textContent = data.pagination?.total || 0;
        }
    })
    .catch(err => {
        console.error('Load commissions error:', err);
        showError('加载失败，请稍后重试');
    });
}

function renderCommissions(commissions) {
    const tbody = document.getElementById('commissionsTable');

    if (commissions.length === 0) {
        tbody.innerHTML = `
            <tr>
                <td colspan="8" class="text-center py-5 text-body-secondary">
                    <i class="cil-inbox" style="font-size: 3rem; opacity: 0.3;"></i>
                    <p class="mt-3">暂无返佣记录</p>
                </td>
            </tr>
        `;
        return;
    }

    const html = commissions.map(c => `
        <tr>
            <td class="fw-semibold">#${c.order_id || '-'}</td>
            <td>
                <div class="d-flex align-items-center">
                    <div class="avatar avatar-sm bg-primary bg-opacity-10 text-primary me-3">
                        <i class="cil-user"></i>
                    </div>
                    <div>
                        <div class="fw-semibold">${c.customer_name || '-'}</div>
                        <small class="text-body-secondary">${c.mt4_account || ''}</small>
                    </div>
                </div>
            </td>
            <td class="fw-semibold">${c.symbol || '-'}</td>
            <td>${c.lots || '-'}</td>
            <td class="fw-bold text-success">${formatCurrency(c.commission)}</td>
            <td>${getTypeBadge(c.type)}</td>
            <td class="text-body-secondary small">${c.trade_time || '-'}</td>
            <td>
                <a href="{{ route('front_coreui_v2_page_commission_realtime_detail') }}?id=${c.id}" class="btn btn-sm btn-outline-primary">
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
        <a class="page-link" href="#" onclick="loadCommissions(${pagination.current_page - 1}); return false;">上一页</a>
    </li>`;

    for (let i = 1; i <= pagination.last_page; i++) {
        if (i === 1 || i === pagination.last_page || (i >= pagination.current_page - 2 && i <= pagination.current_page + 2)) {
            html += `<li class="page-item ${i === pagination.current_page ? 'active' : ''}">
                <a class="page-link" href="#" onclick="loadCommissions(${i}); return false;">${i}</a>
            </li>`;
        } else if (i === pagination.current_page - 3 || i === pagination.current_page + 3) {
            html += `<li class="page-item disabled"><span class="page-link">...</span></li>`;
        }
    }

    html += `<li class="page-item ${pagination.current_page === pagination.last_page ? 'disabled' : ''}">
        <a class="page-link" href="#" onclick="loadCommissions(${pagination.current_page + 1}); return false;">下一页</a>
    </li>`;

    paginationEl.innerHTML = html;
}

function getTypeBadge(type) {
    if (type === 'direct') {
        return '<span class="badge bg-success">直接返佣</span>';
    } else if (type === 'indirect') {
        return '<span class="badge bg-info">间接返佣</span>';
    }
    return '<span class="badge bg-secondary">-</span>';
}

function refreshData() {
    loadStats();
    loadCommissions(currentPage);
}

function startAutoRefresh() {
    autoRefreshTimer = setInterval(() => {
        loadStats();
        loadCommissions(currentPage);
    }, 30000);
}

function exportCommissions() {
    const type = document.getElementById('filterType').value;
    const period = document.getElementById('filterPeriod').value;
    const params = new URLSearchParams();
    if (type) params.append('type', type);
    if (period) params.append('period', period);

    window.location.href = '{{ route("front_api_commission_export") }}?' + params.toString();
}

function showError(message) {
    document.getElementById('commissionsTable').innerHTML = `
        <tr>
            <td colspan="8" class="text-center py-5">
                <i class="cil-warning text-danger" style="font-size: 3rem;"></i>
                <p class="text-danger mt-3">${message}</p>
            </td>
        </tr>
    `;
}

function formatCurrency(value) {
    if (!value && value !== 0) return '-';
    return '$' + parseFloat(value).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

window.addEventListener('beforeunload', function() {
    if (autoRefreshTimer) {
        clearInterval(autoRefreshTimer);
    }
});
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
