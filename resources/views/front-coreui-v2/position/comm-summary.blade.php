@extends('front-coreui-v2.layouts.app')

@section('title', '佣金持仓汇总')

@section('content')
<div class="container-lg px-4">
    <!-- Page Header -->
    <div class="row mb-4">
        <div class="col-12">
            <h2 class="mb-2">佣金持仓汇总</h2>
            <p class="text-body-secondary mb-0">查看下级客户的持仓情况和佣金收益</p>
        </div>
    </div>

    <!-- Summary Cards -->
    <div class="row g-4 mb-4">
        <div class="col-lg-3 col-md-6">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <p class="text-body-secondary mb-2">客户持仓手数</p>
                            <h4 class="mb-0 fw-bold text-primary" id="totalLots">0.00</h4>
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
                            <p class="text-body-secondary mb-2">预计佣金</p>
                            <h4 class="mb-0 fw-bold text-success" id="estimatedComm">$0.00</h4>
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
                            <p class="text-body-secondary mb-2">客户数量</p>
                            <h4 class="mb-0 fw-bold text-info" id="totalCustomers">0</h4>
                        </div>
                        <div class="avatar avatar-sm bg-info bg-opacity-10 text-info">
                            <i class="cil-people"></i>
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
                            <p class="text-body-secondary mb-2">持仓订单数</p>
                            <h4 class="mb-0 fw-bold text-warning" id="totalOrders">0</h4>
                        </div>
                        <div class="avatar avatar-sm bg-warning bg-opacity-10 text-warning">
                            <i class="cil-list"></i>
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
                    <label class="form-label small">客户筛选</label>
                    <select id="filterCustomer" class="form-select">
                        <option value="">全部客户</option>
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
                    <label class="form-label small">手数范围</label>
                    <select id="filterLotsRange" class="form-select">
                        <option value="">全部</option>
                        <option value="0-1">0-1手</option>
                        <option value="1-5">1-5手</option>
                        <option value="5-10">5-10手</option>
                        <option value="10+">10手以上</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label small">佣金筛选</label>
                    <select id="filterCommRange" class="form-select">
                        <option value="">全部</option>
                        <option value="0-10">$0-$10</option>
                        <option value="10-50">$10-$50</option>
                        <option value="50-100">$50-$100</option>
                        <option value="100+">$100以上</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <div class="d-flex gap-2">
                        <button onclick="loadPositions()" class="btn btn-primary flex-grow-1">
                            <i class="cil-search me-2"></i>查询
                        </button>
                        <button onclick="refreshData()" class="btn btn-outline-secondary">
                            <i class="cil-reload"></i>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Commission Position Table -->
    <div class="card shadow-sm border-0">
        <div class="card-header bg-transparent border-bottom">
            <div class="d-flex justify-content-between align-items-center">
                <h5 class="mb-0">
                    <i class="cil-layers me-2"></i>客户持仓明细
                </h5>
                <div class="d-flex align-items-center gap-3">
                    <span class="badge bg-primary" id="lastUpdate">实时更新</span>
                    <button onclick="exportData()" class="btn btn-sm btn-outline-secondary">
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
                            <th>客户</th>
                            <th>MT4账号</th>
                            <th>订单号</th>
                            <th>品种</th>
                            <th>类型</th>
                            <th>手数</th>
                            <th>开仓价</th>
                            <th>当前价</th>
                            <th>盈亏</th>
                            <th>预计佣金</th>
                            <th>操作</th>
                        </tr>
                    </thead>
                    <tbody id="positionsTable">
                        <tr>
                            <td colspan="11" class="text-center py-5 text-body-secondary">
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
                    共 <span id="totalRecords">0</span> 条持仓
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
    loadCustomers();
    loadSummary();
    loadPositions();

    // Auto refresh every 10 seconds
    autoRefreshTimer = setInterval(function() {
        refreshData();
    }, 10000);
});

function loadCustomers() {
    fetch('{{ route("front_api_agent_customers") }}', {
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(res => res.json())
    .then(data => {
        if (data.success && data.customers) {
            const select = document.getElementById('filterCustomer');
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

function loadSummary() {
    const params = getFilterParams();

    fetch('{{ route("front_api_commission_position_summary") }}?' + params.toString(), {
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(res => res.json())
    .then(data => {
        if (data.success && data.summary) {
            document.getElementById('totalLots').textContent = formatNumber(data.summary.total_lots);
            document.getElementById('estimatedComm').textContent = formatCurrency(data.summary.estimated_commission);
            document.getElementById('totalCustomers').textContent = data.summary.total_customers || 0;
            document.getElementById('totalOrders').textContent = data.summary.total_orders || 0;
        }
    })
    .catch(err => {
        console.error('Load summary error:', err);
    });
}

function loadPositions(page = 1) {
    currentPage = page;
    const params = getFilterParams();
    params.append('page', page);

    fetch('{{ route("front_api_commission_position_list") }}?' + params.toString(), {
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(res => res.json())
    .then(data => {
        if (data.success && data.positions) {
            renderPositions(data.positions);
            renderPagination(data.pagination);
            document.getElementById('totalRecords').textContent = data.pagination?.total || 0;
            updateLastUpdateTime();
        }
    })
    .catch(err => {
        console.error('Load positions error:', err);
        showError('加载失败，请稍后重试');
    });
}

function renderPositions(positions) {
    const tbody = document.getElementById('positionsTable');

    if (positions.length === 0) {
        tbody.innerHTML = `
            <tr>
                <td colspan="11" class="text-center py-5 text-body-secondary">
                    <i class="cil-inbox" style="font-size: 3rem; opacity: 0.3;"></i>
                    <p class="mt-3">暂无客户持仓</p>
                </td>
            </tr>
        `;
        return;
    }

    const html = positions.map(p => `
        <tr>
            <td>
                <div class="d-flex align-items-center">
                    <div class="avatar avatar-xs bg-primary bg-opacity-10 text-primary me-2">
                        <i class="cil-user"></i>
                    </div>
                    <div>
                        <div class="fw-semibold">${p.customer_name || '-'}</div>
                        <small class="text-body-secondary">${p.customer_email || ''}</small>
                    </div>
                </div>
            </td>
            <td class="fw-semibold">${p.mt4_account || '-'}</td>
            <td class="text-body-secondary small">${p.order_number || '-'}</td>
            <td><span class="badge bg-secondary">${p.symbol || '-'}</span></td>
            <td>${getTypeBadge(p.type)}</td>
            <td class="fw-semibold">${formatNumber(p.lots)}</td>
            <td>${formatPrice(p.open_price)}</td>
            <td class="fw-semibold">${formatPrice(p.current_price)}</td>
            <td class="${p.profit >= 0 ? 'text-success' : 'text-danger'} fw-semibold">
                ${p.profit >= 0 ? '+' : ''}${formatCurrency(p.profit)}
            </td>
            <td class="text-success fw-bold">${formatCurrency(p.estimated_commission)}</td>
            <td>
                <a href="#" onclick="viewDetail('${p.order_number}'); return false;" class="btn btn-sm btn-outline-primary">
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
        <a class="page-link" href="#" onclick="loadPositions(${pagination.current_page - 1}); return false;">上一页</a>
    </li>`;

    for (let i = 1; i <= pagination.last_page; i++) {
        if (i === 1 || i === pagination.last_page || (i >= pagination.current_page - 2 && i <= pagination.current_page + 2)) {
            html += `<li class="page-item ${i === pagination.current_page ? 'active' : ''}">
                <a class="page-link" href="#" onclick="loadPositions(${i}); return false;">${i}</a>
            </li>`;
        } else if (i === pagination.current_page - 3 || i === pagination.current_page + 3) {
            html += `<li class="page-item disabled"><span class="page-link">...</span></li>`;
        }
    }

    html += `<li class="page-item ${pagination.current_page === pagination.last_page ? 'disabled' : ''}">
        <a class="page-link" href="#" onclick="loadPositions(${pagination.current_page + 1}); return false;">下一页</a>
    </li>`;

    paginationEl.innerHTML = html;
}

function getFilterParams() {
    const params = new URLSearchParams();
    const customerId = document.getElementById('filterCustomer').value;
    const symbol = document.getElementById('filterSymbol').value;
    const lotsRange = document.getElementById('filterLotsRange').value;
    const commRange = document.getElementById('filterCommRange').value;

    if (customerId) params.append('customer_id', customerId);
    if (symbol) params.append('symbol', symbol);
    if (lotsRange) params.append('lots_range', lotsRange);
    if (commRange) params.append('comm_range', commRange);

    return params;
}

function refreshData() {
    loadSummary();
    loadPositions(currentPage);
}

function updateLastUpdateTime() {
    const now = new Date();
    const timeStr = now.toLocaleTimeString('zh-CN', { hour: '2-digit', minute: '2-digit', second: '2-digit' });
    document.getElementById('lastUpdate').textContent = `更新于 ${timeStr}`;
}

function viewDetail(orderNumber) {
    window.location.href = `{{ route('front_coreui_v2_page_position_summary_detail') }}?order=${orderNumber}`;
}

function exportData() {
    const params = getFilterParams();
    window.location.href = '{{ route("front_api_commission_position_export") }}?' + params.toString();
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
    document.getElementById('positionsTable').innerHTML = `
        <tr>
            <td colspan="11" class="text-center py-5">
                <i class="cil-warning text-danger" style="font-size: 3rem;"></i>
                <p class="text-danger mt-3">${message}</p>
            </td>
        </tr>
    `;
}

// Clear timer on page unload
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

.avatar-xs {
    width: 32px;
    height: 32px;
    font-size: 1rem;
}
</style>
@endsection
