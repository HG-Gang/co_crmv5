@extends('front-coreui-v2.layouts.app')

@section('title', '平仓订单')

@section('content')
<div class="container-lg px-4">
    <!-- Page Header -->
    <div class="row mb-4">
        <div class="col-12">
            <h2 class="mb-2">平仓订单</h2>
            <p class="text-body-secondary mb-0">查看所有已平仓的历史交易订单</p>
        </div>
    </div>

    <!-- Summary Cards -->
    <div class="row g-4 mb-4">
        <div class="col-lg-3 col-md-6">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <p class="text-body-secondary mb-2">平仓订单数</p>
                            <h4 class="mb-0 fw-bold text-primary" id="totalOrders">0</h4>
                        </div>
                        <div class="avatar avatar-sm bg-primary bg-opacity-10 text-primary">
                            <i class="cil-list"></i>
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
                            <p class="text-body-secondary mb-2">总盈亏</p>
                            <h4 class="mb-0 fw-bold text-success" id="totalProfit">$0.00</h4>
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
                            <p class="text-body-secondary mb-2">盈利订单</p>
                            <h4 class="mb-0 fw-bold text-info" id="profitOrders">0</h4>
                        </div>
                        <div class="avatar avatar-sm bg-info bg-opacity-10 text-info">
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
                            <p class="text-body-secondary mb-2">亏损订单</p>
                            <h4 class="mb-0 fw-bold text-danger" id="lossOrders">0</h4>
                        </div>
                        <div class="avatar avatar-sm bg-danger bg-opacity-10 text-danger">
                            <i class="cil-x-circle"></i>
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
                    <label class="form-label small">交易品种</label>
                    <select id="filterSymbol" class="form-select">
                        <option value="">全部品种</option>
                        <option value="EURUSD">EURUSD</option>
                        <option value="GBPUSD">GBPUSD</option>
                        <option value="USDJPY">USDJPY</option>
                        <option value="XAUUSD">XAUUSD</option>
                        <option value="XAGUSD">XAGUSD</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label small">盈亏筛选</label>
                    <select id="filterProfitStatus" class="form-select">
                        <option value="">全部</option>
                        <option value="profit">盈利</option>
                        <option value="loss">亏损</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label small">时间范围</label>
                    <select id="filterTime" class="form-select">
                        <option value="today">今天</option>
                        <option value="week">本周</option>
                        <option value="month" selected>本月</option>
                        <option value="all">全部</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <div class="d-flex gap-2">
                        <button onclick="loadOrders()" class="btn btn-primary flex-grow-1">
                            <i class="cil-search me-2"></i>查询
                        </button>
                        <button onclick="exportData()" class="btn btn-outline-secondary">
                            <i class="cil-cloud-download"></i>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Orders Table -->
    <div class="card shadow-sm border-0">
        <div class="card-header bg-transparent border-bottom">
            <div class="d-flex justify-content-between align-items-center">
                <h5 class="mb-0">
                    <i class="cil-history me-2"></i>平仓记录
                </h5>
                <div class="btn-group btn-group-sm">
                    <button class="btn btn-outline-secondary active" onclick="setViewMode('table')">
                        <i class="cil-list"></i>
                    </button>
                    <button class="btn btn-outline-secondary" onclick="setViewMode('chart')">
                        <i class="cil-chart"></i>
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
                            <th>订单号</th>
                            <th>MT4账号</th>
                            <th>品种</th>
                            <th>类型</th>
                            <th>手数</th>
                            <th>开仓价</th>
                            <th>平仓价</th>
                            <th>盈亏</th>
                            <th>开仓时间</th>
                            <th>平仓时间</th>
                            <th>操作</th>
                        </tr>
                    </thead>
                    <tbody id="ordersTable">
                        <tr>
                            <td colspan="11" class="text-center py-5 text-body-secondary">
                                <div class="spinner-border spinner-border-sm me-2"></div>
                                加载中...
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Chart View -->
            <div id="chartView" class="p-4" style="display: none;">
                <div class="text-center py-5 text-body-secondary">
                    <i class="cil-chart" style="font-size: 3rem; opacity: 0.3;"></i>
                    <p class="mt-3">盈亏统计图表功能开发中</p>
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
    loadOrders();
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

    fetch('{{ route("front_api_order_closed_summary") }}?' + params.toString(), {
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(res => res.json())
    .then(data => {
        if (data.success && data.summary) {
            document.getElementById('totalOrders').textContent = data.summary.total_orders || 0;
            document.getElementById('totalProfit').textContent = formatCurrency(data.summary.total_profit);
            document.getElementById('profitOrders').textContent = data.summary.profit_orders || 0;
            document.getElementById('lossOrders').textContent = data.summary.loss_orders || 0;

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

function loadOrders(page = 1) {
    currentPage = page;
    const params = getFilterParams();
    params.append('page', page);

    fetch('{{ route("front_api_order_closed_list") }}?' + params.toString(), {
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(res => res.json())
    .then(data => {
        if (data.success && data.orders) {
            renderOrders(data.orders);
            renderPagination(data.pagination);
            document.getElementById('totalRecords').textContent = data.pagination?.total || 0;
        }
    })
    .catch(err => {
        console.error('Load orders error:', err);
        showError('加载失败，请稍后重试');
    });
}

function renderOrders(orders) {
    const tbody = document.getElementById('ordersTable');

    if (orders.length === 0) {
        tbody.innerHTML = `
            <tr>
                <td colspan="11" class="text-center py-5 text-body-secondary">
                    <i class="cil-inbox" style="font-size: 3rem; opacity: 0.3;"></i>
                    <p class="mt-3">暂无平仓记录</p>
                </td>
            </tr>
        `;
        return;
    }

    const html = orders.map(o => `
        <tr>
            <td class="fw-semibold">${o.order_number || '-'}</td>
            <td class="text-body-secondary">${o.mt4_account || '-'}</td>
            <td><span class="badge bg-secondary">${o.symbol || '-'}</span></td>
            <td>${getTypeBadge(o.type)}</td>
            <td class="fw-semibold">${formatNumber(o.lots)}</td>
            <td>${formatPrice(o.open_price)}</td>
            <td class="fw-semibold">${formatPrice(o.close_price)}</td>
            <td class="${o.profit >= 0 ? 'text-success' : 'text-danger'} fw-bold">
                ${o.profit >= 0 ? '+' : ''}${formatCurrency(o.profit)}
            </td>
            <td class="text-body-secondary small text-nowrap">${o.open_time || '-'}</td>
            <td class="text-body-secondary small text-nowrap">${o.close_time || '-'}</td>
            <td>
                <a href="{{ route('front_coreui_v2_page_order_closed_detail') }}?order=${o.order_number}" class="btn btn-sm btn-outline-primary">
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
        <a class="page-link" href="#" onclick="loadOrders(${pagination.current_page - 1}); return false;">上一页</a>
    </li>`;

    for (let i = 1; i <= pagination.last_page; i++) {
        if (i === 1 || i === pagination.last_page || (i >= pagination.current_page - 2 && i <= pagination.current_page + 2)) {
            html += `<li class="page-item ${i === pagination.current_page ? 'active' : ''}">
                <a class="page-link" href="#" onclick="loadOrders(${i}); return false;">${i}</a>
            </li>`;
        } else if (i === pagination.current_page - 3 || i === pagination.current_page + 3) {
            html += `<li class="page-item disabled"><span class="page-link">...</span></li>`;
        }
    }

    html += `<li class="page-item ${pagination.current_page === pagination.last_page ? 'disabled' : ''}">
        <a class="page-link" href="#" onclick="loadOrders(${pagination.current_page + 1}); return false;">下一页</a>
    </li>`;

    paginationEl.innerHTML = html;
}

function getFilterParams() {
    const params = new URLSearchParams();
    const accountId = document.getElementById('filterAccount').value;
    const symbol = document.getElementById('filterSymbol').value;
    const profitStatus = document.getElementById('filterProfitStatus').value;
    const time = document.getElementById('filterTime').value;

    if (accountId) params.append('account_id', accountId);
    if (symbol) params.append('symbol', symbol);
    if (profitStatus) params.append('profit_status', profitStatus);
    if (time) params.append('time', time);

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
        document.getElementById('chartView').style.display = 'none';
    } else {
        document.getElementById('tableView').style.display = 'none';
        document.getElementById('chartView').style.display = 'block';
    }
}

function exportData() {
    const params = getFilterParams();
    window.location.href = '{{ route("front_api_order_closed_export") }}?' + params.toString();
}

function getTypeBadge(type) {
    if (type === 'buy' || type === 0) {
        return '<span class="badge bg-success">买入</span>';
    } else if (type === 'sell' || type === 1) {
        return '<span class="badge bg-danger">卖出</span>';
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

function formatPrice(value) {
    if (!value && value !== 0) return '-';
    return parseFloat(value).toFixed(5);
}

function showError(message) {
    document.getElementById('ordersTable').innerHTML = `
        <tr>
            <td colspan="11" class="text-center py-5">
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
</style>
@endsection
