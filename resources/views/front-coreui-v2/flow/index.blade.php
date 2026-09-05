@extends('front-coreui-v2.layouts.app')

@section('title', '资金流水')

@section('content')
<div class="container-lg px-4">
    <!-- Page Header -->
    <div class="row mb-4">
        <div class="col-12">
            <h2 class="mb-2">资金流水</h2>
            <p class="text-body-secondary mb-0">查看账户资金明细和交易记录</p>
        </div>
    </div>

    <!-- Summary Cards -->
    <div class="row g-4 mb-4">
        <div class="col-lg-3 col-md-6">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <p class="text-body-secondary mb-2">总入金</p>
                            <h4 class="mb-0 fw-bold text-success" id="totalDeposit">$0.00</h4>
                        </div>
                        <div class="avatar avatar-sm bg-success bg-opacity-10 text-success">
                            <i class="cil-arrow-thick-to-bottom"></i>
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
                            <p class="text-body-secondary mb-2">总出金</p>
                            <h4 class="mb-0 fw-bold text-danger" id="totalWithdraw">$0.00</h4>
                        </div>
                        <div class="avatar avatar-sm bg-danger bg-opacity-10 text-danger">
                            <i class="cil-arrow-thick-from-bottom"></i>
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
                            <p class="text-body-secondary mb-2">交易盈亏</p>
                            <h4 class="mb-0 fw-bold text-info" id="totalProfit">$0.00</h4>
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
                            <p class="text-body-secondary mb-2">总流水</p>
                            <h4 class="mb-0 fw-bold text-primary" id="totalFlow">$0.00</h4>
                        </div>
                        <div class="avatar avatar-sm bg-primary bg-opacity-10 text-primary">
                            <i class="cil-wallet"></i>
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
                <div class="col-md-3">
                    <label class="form-label small">MT4账户</label>
                    <select id="filterAccount" class="form-select">
                        <option value="">全部账户</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label small">流水类型</label>
                    <select id="filterType" class="form-select">
                        <option value="">全部类型</option>
                        <option value="deposit">入金</option>
                        <option value="withdraw">出金</option>
                        <option value="trade">交易</option>
                        <option value="commission">佣金</option>
                        <option value="bonus">赠金</option>
                        <option value="transfer">转账</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label small">开始日期</label>
                    <input type="date" id="filterStartDate" class="form-control">
                </div>
                <div class="col-md-2">
                    <label class="form-label small">结束日期</label>
                    <input type="date" id="filterEndDate" class="form-control">
                </div>
                <div class="col-md-3">
                    <div class="d-flex gap-2">
                        <button onclick="loadFlows()" class="btn btn-primary flex-grow-1">
                            <i class="cil-search me-2"></i>查询
                        </button>
                        <button onclick="exportFlows()" class="btn btn-outline-secondary">
                            <i class="cil-cloud-download"></i>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Flow Table -->
    <div class="card shadow-sm border-0">
        <div class="card-header bg-transparent border-bottom">
            <div class="d-flex justify-content-between align-items-center">
                <h5 class="mb-0">
                    <i class="cil-list me-2"></i>流水明细
                </h5>
                <div class="btn-group btn-group-sm">
                    <button class="btn btn-outline-secondary active" onclick="setViewMode('table')">
                        <i class="cil-list"></i>
                    </button>
                    <button class="btn btn-outline-secondary" onclick="setViewMode('timeline')">
                        <i class="cil-timeline"></i>
                    </button>
                </div>
            </div>
        </div>
        <div class="card-body p-0">
            <!-- Table View -->
            <div id="tableView" class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>时间</th>
                            <th>MT4账号</th>
                            <th>类型</th>
                            <th>订单号</th>
                            <th>金额</th>
                            <th>余额</th>
                            <th>说明</th>
                        </tr>
                    </thead>
                    <tbody id="flowsTable">
                        <tr>
                            <td colspan="7" class="text-center py-5 text-body-secondary">
                                <div class="spinner-border spinner-border-sm me-2"></div>
                                加载中...
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Timeline View -->
            <div id="timelineView" class="p-4" style="display: none;">
                <div id="flowsTimeline">
                    <div class="text-center py-5 text-body-secondary">
                        <div class="spinner-border spinner-border-sm me-2"></div>
                        加载中...
                    </div>
                </div>
            </div>
        </div>
        <div class="card-footer bg-transparent border-top">
            <div class="d-flex justify-content-between align-items-center">
                <div class="text-body-secondary small">
                    共 <span id="totalRecords">0</span> 条记录
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
let currentViewMode = 'table';

document.addEventListener('DOMContentLoaded', function() {
    loadAccounts();
    loadSummary();
    loadFlows();

    // Set default date range (last 30 days)
    const endDate = new Date();
    const startDate = new Date();
    startDate.setDate(startDate.getDate() - 30);

    document.getElementById('filterStartDate').valueAsDate = startDate;
    document.getElementById('filterEndDate').valueAsDate = endDate;
});

function loadAccounts() {
    fetch('{{ route("front_api_account_list") }}', {
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(res => res.json())
    .then(data => {
        if (data.success && data.accounts) {
            const select = document.getElementById('filterAccount');
            data.accounts.forEach(acc => {
                const option = document.createElement('option');
                option.value = acc.id;
                option.textContent = `${acc.mt4_account} - ${acc.account_name}`;
                select.appendChild(option);
            });
        }
    })
    .catch(err => {
        console.error('Load accounts error:', err);
    });
}

function loadSummary() {
    const params = getFilterParams();

    fetch('{{ route("front_api_flow_summary") }}?' + params.toString(), {
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(res => res.json())
    .then(data => {
        if (data.success && data.summary) {
            document.getElementById('totalDeposit').textContent = formatCurrency(data.summary.total_deposit);
            document.getElementById('totalWithdraw').textContent = formatCurrency(data.summary.total_withdraw);
            document.getElementById('totalProfit').textContent = formatCurrency(data.summary.total_profit);
            document.getElementById('totalFlow').textContent = formatCurrency(data.summary.total_flow);

            const profitEl = document.getElementById('totalProfit');
            if (data.summary.total_profit >= 0) {
                profitEl.className = 'mb-0 fw-bold text-success';
            } else {
                profitEl.className = 'mb-0 fw-bold text-danger';
            }
        }
    })
    .catch(err => {
        console.error('Load summary error:', err);
    });
}

function loadFlows(page = 1) {
    currentPage = page;
    const params = getFilterParams();
    params.append('page', page);

    fetch('{{ route("front_api_flow_list") }}?' + params.toString(), {
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(res => res.json())
    .then(data => {
        if (data.success && data.flows) {
            if (currentViewMode === 'table') {
                renderFlowsTable(data.flows);
            } else {
                renderFlowsTimeline(data.flows);
            }
            renderPagination(data.pagination);
            document.getElementById('totalRecords').textContent = data.pagination?.total || 0;
        }
    })
    .catch(err => {
        console.error('Load flows error:', err);
        showError('加载失败，请稍后重试');
    });
}

function renderFlowsTable(flows) {
    const tbody = document.getElementById('flowsTable');

    if (flows.length === 0) {
        tbody.innerHTML = `
            <tr>
                <td colspan="7" class="text-center py-5 text-body-secondary">
                    <i class="cil-inbox" style="font-size: 3rem; opacity: 0.3;"></i>
                    <p class="mt-3">暂无流水记录</p>
                </td>
            </tr>
        `;
        return;
    }

    const html = flows.map(f => `
        <tr>
            <td class="text-nowrap">${f.created_at || '-'}</td>
            <td>${f.mt4_account || '-'}</td>
            <td>${getFlowTypeBadge(f.type)}</td>
            <td class="text-body-secondary">${f.order_number || '-'}</td>
            <td class="${f.amount >= 0 ? 'text-success' : 'text-danger'} fw-semibold">
                ${f.amount >= 0 ? '+' : ''}${formatCurrency(f.amount)}
            </td>
            <td class="fw-semibold">${formatCurrency(f.balance_after)}</td>
            <td class="text-body-secondary small">${f.comment || '-'}</td>
        </tr>
    `).join('');

    tbody.innerHTML = html;
}

function renderFlowsTimeline(flows) {
    const timeline = document.getElementById('flowsTimeline');

    if (flows.length === 0) {
        timeline.innerHTML = `
            <div class="text-center py-5 text-body-secondary">
                <i class="cil-inbox" style="font-size: 3rem; opacity: 0.3;"></i>
                <p class="mt-3">暂无流水记录</p>
            </div>
        `;
        return;
    }

    const html = flows.map(f => `
        <div class="timeline-item mb-4">
            <div class="d-flex">
                <div class="flex-shrink-0 me-3">
                    <div class="timeline-icon ${getFlowIconClass(f.type)}">
                        <i class="${getFlowIcon(f.type)}"></i>
                    </div>
                </div>
                <div class="flex-grow-1">
                    <div class="card border-0 shadow-sm">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-start mb-2">
                                <div>
                                    <h6 class="mb-1">${getFlowTypeBadge(f.type)}</h6>
                                    <p class="mb-0 small text-body-secondary">
                                        <i class="cil-calendar me-1"></i>${f.created_at} |
                                        <i class="cil-credit-card me-1"></i>${f.mt4_account}
                                    </p>
                                </div>
                                <h5 class="mb-0 ${f.amount >= 0 ? 'text-success' : 'text-danger'}">
                                    ${f.amount >= 0 ? '+' : ''}${formatCurrency(f.amount)}
                                </h5>
                            </div>
                            <p class="mb-1 small text-body-secondary">${f.comment || '-'}</p>
                            <div class="text-body-secondary small">
                                余额: <strong>${formatCurrency(f.balance_after)}</strong>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    `).join('');

    timeline.innerHTML = html;
}

function renderPagination(pagination) {
    if (!pagination) return;

    const paginationEl = document.getElementById('pagination');
    let html = '';

    html += `<li class="page-item ${pagination.current_page === 1 ? 'disabled' : ''}">
        <a class="page-link" href="#" onclick="loadFlows(${pagination.current_page - 1}); return false;">上一页</a>
    </li>`;

    for (let i = 1; i <= pagination.last_page; i++) {
        if (i === 1 || i === pagination.last_page || (i >= pagination.current_page - 2 && i <= pagination.current_page + 2)) {
            html += `<li class="page-item ${i === pagination.current_page ? 'active' : ''}">
                <a class="page-link" href="#" onclick="loadFlows(${i}); return false;">${i}</a>
            </li>`;
        } else if (i === pagination.current_page - 3 || i === pagination.current_page + 3) {
            html += `<li class="page-item disabled"><span class="page-link">...</span></li>`;
        }
    }

    html += `<li class="page-item ${pagination.current_page === pagination.last_page ? 'disabled' : ''}">
        <a class="page-link" href="#" onclick="loadFlows(${pagination.current_page + 1}); return false;">下一页</a>
    </li>`;

    paginationEl.innerHTML = html;
}

function getFilterParams() {
    const params = new URLSearchParams();
    const accountId = document.getElementById('filterAccount').value;
    const type = document.getElementById('filterType').value;
    const startDate = document.getElementById('filterStartDate').value;
    const endDate = document.getElementById('filterEndDate').value;

    if (accountId) params.append('account_id', accountId);
    if (type) params.append('type', type);
    if (startDate) params.append('start_date', startDate);
    if (endDate) params.append('end_date', endDate);

    return params;
}

function setViewMode(mode) {
    currentViewMode = mode;

    document.querySelectorAll('.btn-group button').forEach(btn => {
        btn.classList.remove('active');
    });
    event.target.closest('button').classList.add('active');

    if (mode === 'table') {
        document.getElementById('tableView').style.display = 'block';
        document.getElementById('timelineView').style.display = 'none';
    } else {
        document.getElementById('tableView').style.display = 'none';
        document.getElementById('timelineView').style.display = 'block';
    }

    loadFlows(currentPage);
}

function exportFlows() {
    const params = getFilterParams();
    window.location.href = '{{ route("front_api_flow_export") }}?' + params.toString();
}

function getFlowTypeBadge(type) {
    const badges = {
        'deposit': '<span class="badge bg-success">入金</span>',
        'withdraw': '<span class="badge bg-danger">出金</span>',
        'trade': '<span class="badge bg-primary">交易</span>',
        'commission': '<span class="badge bg-info">佣金</span>',
        'bonus': '<span class="badge bg-warning text-dark">赠金</span>',
        'transfer': '<span class="badge bg-secondary">转账</span>'
    };
    return badges[type] || '<span class="badge bg-secondary">其他</span>';
}

function getFlowIcon(type) {
    const icons = {
        'deposit': 'cil-arrow-thick-to-bottom',
        'withdraw': 'cil-arrow-thick-from-bottom',
        'trade': 'cil-chart-line',
        'commission': 'cil-dollar',
        'bonus': 'cil-gift',
        'transfer': 'cil-swap-horizontal'
    };
    return icons[type] || 'cil-info';
}

function getFlowIconClass(type) {
    const classes = {
        'deposit': 'bg-success',
        'withdraw': 'bg-danger',
        'trade': 'bg-primary',
        'commission': 'bg-info',
        'bonus': 'bg-warning',
        'transfer': 'bg-secondary'
    };
    return classes[type] || 'bg-secondary';
}

function formatCurrency(value) {
    if (!value) return '$0.00';
    return '$' + parseFloat(value).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

function showError(message) {
    document.getElementById('flowsTable').innerHTML = `
        <tr>
            <td colspan="7" class="text-center py-5">
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

.timeline-icon {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 1.25rem;
}
</style>
@endsection
