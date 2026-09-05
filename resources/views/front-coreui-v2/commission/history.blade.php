@extends('front-coreui-v2.layouts.app')

@section('title', '历史返佣')

@section('content')
<div class="container-lg px-4">
    <!-- Page Header -->
    <div class="row mb-4">
        <div class="col-12">
            <h2 class="mb-2">历史返佣</h2>
            <p class="text-body-secondary mb-0">查看历史返佣记录和统计分析</p>
        </div>
    </div>

    <!-- Stats Cards -->
    <div class="row g-4 mb-4">
        <div class="col-lg-3 col-md-6">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <p class="text-body-secondary mb-2">上月返佣</p>
                            <h4 class="mb-0 fw-bold text-success" id="lastMonthCommission">$0.00</h4>
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
                            <p class="text-body-secondary mb-2">本季度返佣</p>
                            <h4 class="mb-0 fw-bold text-info" id="quarterCommission">$0.00</h4>
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
                            <p class="text-body-secondary mb-2">本年度返佣</p>
                            <h4 class="mb-0 fw-bold text-primary" id="yearCommission">$0.00</h4>
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
                            <p class="text-body-secondary mb-2">历史总计</p>
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

    <!-- Monthly Summary -->
    <div class="card shadow-sm border-0 mb-4">
        <div class="card-header bg-transparent border-bottom">
            <div class="d-flex justify-content-between align-items-center">
                <h5 class="mb-0">
                    <i class="cil-chart-pie me-2"></i>月度汇总
                </h5>
                <select id="filterYear" class="form-select form-select-sm" style="width: auto;" onchange="loadMonthlySummary()">
                    <option value="2024">2024年</option>
                    <option value="2023">2023年</option>
                    <option value="2022">2022年</option>
                </select>
            </div>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>月份</th>
                            <th>返佣金额</th>
                            <th>订单数量</th>
                            <th>客户数量</th>
                            <th>环比增长</th>
                            <th>操作</th>
                        </tr>
                    </thead>
                    <tbody id="monthlySummaryTable">
                        <tr>
                            <td colspan="6" class="text-center py-5 text-body-secondary">
                                <div class="spinner-border spinner-border-sm me-2"></div>
                                加载中...
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- History Records -->
    <div class="card shadow-sm border-0">
        <div class="card-header bg-transparent border-bottom">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
                <h5 class="mb-0">
                    <i class="cil-list me-2"></i>历史记录
                </h5>
                <div class="d-flex align-items-center gap-2 flex-wrap">
                    <input type="date" id="filterStartDate" class="form-control form-control-sm" style="width: auto;" onchange="loadHistory()">
                    <span class="text-body-secondary">至</span>
                    <input type="date" id="filterEndDate" class="form-control form-control-sm" style="width: auto;" onchange="loadHistory()">
                    <select id="filterType" class="form-select form-select-sm" style="width: auto;" onchange="loadHistory()">
                        <option value="">全部类型</option>
                        <option value="direct">直接返佣</option>
                        <option value="indirect">间接返佣</option>
                    </select>
                    <button class="btn btn-sm btn-success" onclick="exportHistory()">
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
                            <th>结算日期</th>
                            <th>客户信息</th>
                            <th>订单数量</th>
                            <th>返佣金额</th>
                            <th>返佣类型</th>
                            <th>状态</th>
                            <th>操作</th>
                        </tr>
                    </thead>
                    <tbody id="historyTable">
                        <tr>
                            <td colspan="7" class="text-center py-5 text-body-secondary">
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
                    共 <span id="totalRecords">0</span> 条历史记录
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
    const currentYear = new Date().getFullYear();
    document.getElementById('filterYear').value = currentYear;

    loadStats();
    loadMonthlySummary();
    loadHistory();
});

function loadStats() {
    fetch('{{ route("front_api_commission_history_stats") }}', {
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(res => res.json())
    .then(data => {
        if (data.success && data.stats) {
            document.getElementById('lastMonthCommission').textContent = formatCurrency(data.stats.last_month);
            document.getElementById('quarterCommission').textContent = formatCurrency(data.stats.quarter);
            document.getElementById('yearCommission').textContent = formatCurrency(data.stats.year);
            document.getElementById('totalCommission').textContent = formatCurrency(data.stats.total);
        }
    })
    .catch(err => {
        console.error('Load stats error:', err);
    });
}

function loadMonthlySummary() {
    const year = document.getElementById('filterYear').value;

    fetch(`{{ route("front_api_commission_monthly_summary") }}?year=${year}`, {
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(res => res.json())
    .then(data => {
        if (data.success && data.summary) {
            renderMonthlySummary(data.summary);
        }
    })
    .catch(err => {
        console.error('Load monthly summary error:', err);
    });
}

function renderMonthlySummary(summary) {
    const tbody = document.getElementById('monthlySummaryTable');

    if (summary.length === 0) {
        tbody.innerHTML = `
            <tr>
                <td colspan="6" class="text-center py-5 text-body-secondary">
                    <i class="cil-inbox" style="font-size: 3rem; opacity: 0.3;"></i>
                    <p class="mt-3">暂无月度数据</p>
                </td>
            </tr>
        `;
        return;
    }

    const html = summary.map(s => `
        <tr>
            <td class="fw-semibold">${s.month || '-'}</td>
            <td class="fw-bold text-success">${formatCurrency(s.amount)}</td>
            <td>${s.order_count || 0}</td>
            <td>${s.customer_count || 0}</td>
            <td>${getGrowthBadge(s.growth)}</td>
            <td>
                <button class="btn btn-sm btn-outline-primary" onclick="viewMonthDetail('${s.month}')">
                    查看详情
                </button>
            </td>
        </tr>
    `).join('');

    tbody.innerHTML = html;
}

function loadHistory(page = 1) {
    currentPage = page;
    const startDate = document.getElementById('filterStartDate').value;
    const endDate = document.getElementById('filterEndDate').value;
    const type = document.getElementById('filterType').value;
    const params = new URLSearchParams();
    if (startDate) params.append('start_date', startDate);
    if (endDate) params.append('end_date', endDate);
    if (type) params.append('type', type);
    params.append('page', page);

    fetch('{{ route("front_api_commission_history") }}?' + params.toString(), {
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(res => res.json())
    .then(data => {
        if (data.success && data.history) {
            renderHistory(data.history);
            renderPagination(data.pagination);
            document.getElementById('totalRecords').textContent = data.pagination?.total || 0;
        }
    })
    .catch(err => {
        console.error('Load history error:', err);
        showError('加载失败，请稍后重试');
    });
}

function renderHistory(history) {
    const tbody = document.getElementById('historyTable');

    if (history.length === 0) {
        tbody.innerHTML = `
            <tr>
                <td colspan="7" class="text-center py-5 text-body-secondary">
                    <i class="cil-inbox" style="font-size: 3rem; opacity: 0.3;"></i>
                    <p class="mt-3">暂无历史记录</p>
                </td>
            </tr>
        `;
        return;
    }

    const html = history.map(h => `
        <tr>
            <td class="fw-semibold">${h.settlement_date || '-'}</td>
            <td>
                <div class="d-flex align-items-center">
                    <div class="avatar avatar-sm bg-primary bg-opacity-10 text-primary me-3">
                        <i class="cil-user"></i>
                    </div>
                    <div>
                        <div class="fw-semibold">${h.customer_name || '-'}</div>
                        <small class="text-body-secondary">${h.mt4_account || ''}</small>
                    </div>
                </div>
            </td>
            <td>${h.order_count || 0}</td>
            <td class="fw-bold text-success">${formatCurrency(h.amount)}</td>
            <td>${getTypeBadge(h.type)}</td>
            <td>${getStatusBadge(h.status)}</td>
            <td>
                <button class="btn btn-sm btn-outline-primary" onclick="viewDetail(${h.id})">
                    详情
                </button>
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
        <a class="page-link" href="#" onclick="loadHistory(${pagination.current_page - 1}); return false;">上一页</a>
    </li>`;

    for (let i = 1; i <= pagination.last_page; i++) {
        if (i === 1 || i === pagination.last_page || (i >= pagination.current_page - 2 && i <= pagination.current_page + 2)) {
            html += `<li class="page-item ${i === pagination.current_page ? 'active' : ''}">
                <a class="page-link" href="#" onclick="loadHistory(${i}); return false;">${i}</a>
            </li>`;
        } else if (i === pagination.current_page - 3 || i === pagination.current_page + 3) {
            html += `<li class="page-item disabled"><span class="page-link">...</span></li>`;
        }
    }

    html += `<li class="page-item ${pagination.current_page === pagination.last_page ? 'disabled' : ''}">
        <a class="page-link" href="#" onclick="loadHistory(${pagination.current_page + 1}); return false;">下一页</a>
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

function getStatusBadge(status) {
    if (status === 'settled') {
        return '<span class="badge bg-success">已结算</span>';
    } else if (status === 'pending') {
        return '<span class="badge bg-warning">待结算</span>';
    } else if (status === 'failed') {
        return '<span class="badge bg-danger">结算失败</span>';
    }
    return '<span class="badge bg-secondary">-</span>';
}

function getGrowthBadge(growth) {
    if (!growth && growth !== 0) return '-';
    const value = parseFloat(growth);
    if (value > 0) {
        return `<span class="badge bg-success">+${value}%</span>`;
    } else if (value < 0) {
        return `<span class="badge bg-danger">${value}%</span>`;
    } else {
        return `<span class="badge bg-secondary">0%</span>`;
    }
}

function viewMonthDetail(month) {
    const startDate = month + '-01';
    const lastDay = new Date(month.split('-')[0], month.split('-')[1], 0).getDate();
    const endDate = month + '-' + lastDay;

    document.getElementById('filterStartDate').value = startDate;
    document.getElementById('filterEndDate').value = endDate;
    loadHistory(1);
}

function viewDetail(id) {
    window.location.href = `{{ route('front_coreui_v2_page_commission_realtime_detail') }}?id=${id}`;
}

function exportHistory() {
    const startDate = document.getElementById('filterStartDate').value;
    const endDate = document.getElementById('filterEndDate').value;
    const type = document.getElementById('filterType').value;
    const params = new URLSearchParams();
    if (startDate) params.append('start_date', startDate);
    if (endDate) params.append('end_date', endDate);
    if (type) params.append('type', type);

    window.location.href = '{{ route("front_api_commission_history_export") }}?' + params.toString();
}

function showError(message) {
    document.getElementById('historyTable').innerHTML = `
        <tr>
            <td colspan="7" class="text-center py-5">
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
