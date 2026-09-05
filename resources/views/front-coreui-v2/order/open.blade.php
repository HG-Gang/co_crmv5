@extends('front-coreui-v2.layouts.app')

@section('title', '开仓订单')

@section('content')
<div class="container-lg px-4">
    <!-- Page Header -->
    <div class="row mb-4">
        <div class="col-12">
            <h2 class="mb-2">开仓订单</h2>
            <p class="text-body-secondary mb-0">查看所有未平仓的交易订单</p>
        </div>
    </div>

    <!-- Summary Cards -->
    <div class="row g-4 mb-4">
        <div class="col-lg-3 col-md-6">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <p class="text-body-secondary mb-2">开仓订单</p>
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
                            <p class="text-body-secondary mb-2">总手数</p>
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
                            <p class="text-body-secondary mb-2">浮动盈亏</p>
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
                            <p class="text-body-secondary mb-2">占用保证金</p>
                            <h4 class="mb-0 fw-bold text-warning" id="totalMargin">$0.00</h4>
                        </div>
                        <div class="avatar avatar-sm bg-warning bg-opacity-10 text-warning">
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
                    <label class="form-label small">订单类型</label>
                    <select id="filterType" class="form-select">
                        <option value="">全部类型</option>
                        <option value="buy">买入</option>
                        <option value="sell">卖出</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label small">开仓时间</label>
                    <select id="filterTime" class="form-select">
                        <option value="">全部时间</option>
                        <option value="today">今天</option>
                        <option value="week">本周</option>
                        <option value="month">本月</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <div class="d-flex gap-2">
                        <button onclick="loadOrders()" class="btn btn-primary flex-grow-1">
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

    <!-- Orders Table -->
    <div class="card shadow-sm border-0">
        <div class="card-header bg-transparent border-bottom">
            <div class="d-flex justify-content-between align-items-center">
                <h5 class="mb-0">
                    <i class="cil-layers me-2"></i>订单列表
                </h5>
                <span class="badge bg-primary" id="lastUpdate">实时更新</span>
            </div>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>订单号</th>
                            <th>MT4账号</th>
                            <th>品种</th>
                            <th>类型</th>
                            <th>手数</th>
                            <th>开仓价</th>
                            <th>当前价</th>
                            <th>止损/止盈</th>
                            <th>浮动盈亏</th>
                            <th>开仓时间</th>
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
        </div>
        <div class="card-footer bg-transparent border-top">
            <div class="d-flex justify-content-between align-items-center">
                <div class="text-body-secondary small">
                    共 <span id="totalRecords">0</span> 条订单
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
    loadAccounts();
    loadSummary();
    loadOrders();

    // Auto refresh every 5 seconds
    autoRefreshTimer = setInterval(function() {
        refreshData();
    }, 5000);
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

    fetch('{{ route("front_api_order_open_summary") }}?' + params.toString(), {
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(res => res.json())
    .then(data => {
        if (data.success && data.summary) {
            document.getElementById('totalOrders').textContent = data.summary.total_orders || 0;
            document.getElementById('totalLots').textContent = formatNumber(data.summary.total_lots);
            document.getElementById('totalProfit').textContent = formatCurrency(data.summary.total_profit);
            document.getElementById('totalMargin').textContent = formatCurrency(data.summary.total_margin);

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

    fetch('{{ route("front_api_order_open_list") }}?' + params.toString(), {
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(res => res.json())
    .then(data => {
        if (data.success && data.orders) {
            renderOrders(data.orders);
            renderPagination(data.pagination);
            document.getElementById('totalRecords').textContent = data.pagination?.total || 0;
            updateLastUpdateTime();
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
                    <p class="mt-3">暂无开仓订单</p>
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
            <td class="fw-semibold">${formatPrice(o.current_price)}</td>
            <td class="text-body-secondary small">
                ${formatPrice(o.stop_loss)} / ${formatPrice(o.take_profit)}
            </td>
            <td class="${o.profit >= 0 ? 'text-success' : 'text-danger'} fw-bold">
                ${o.profit >= 0 ? '+' : ''}${formatCurrency(o.profit)}
            </td>
            <td class="text-body-secondary small text-nowrap">${o.open_time || '-'}</td>
            <td>
                <div class="btn-group btn-group-sm">
                    <a href="{{ route('front_coreui_v2_page_order_open_detail') }}?order=${o.order_number}" class="btn btn-outline-primary">
                        详情
                    </a>
                    <button onclick="closeOrder('${o.order_number}')" class="btn btn-outline-danger">
                        平仓
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
    const type = document.getElementById('filterType').value;
    const time = document.getElementById('filterTime').value;

    if (accountId) params.append('account_id', accountId);
    if (symbol) params.append('symbol', symbol);
    if (type) params.append('type', type);
    if (time) params.append('time', time);

    return params;
}

function refreshData() {
    loadSummary();
    loadOrders(currentPage);
}

function updateLastUpdateTime() {
    const now = new Date();
    const timeStr = now.toLocaleTimeString('zh-CN', { hour: '2-digit', minute: '2-digit', second: '2-digit' });
    document.getElementById('lastUpdate').textContent = `更新于 ${timeStr}`;
}

function closeOrder(orderNumber) {
    if (!confirm('确定要平仓吗？')) {
        return;
    }

    fetch('{{ route("front_api_order_close") }}', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: JSON.stringify({ order_number: orderNumber })
    })
    .then(res => res.json())
    .then(data => {
        if (data.success || data.code === 200) {
            alert('平仓成功');
            refreshData();
        } else {
            alert(data.message || '平仓失败');
        }
    })
    .catch(err => {
        console.error('Close order error:', err);
        alert('网络错误，请稍后重试');
    });
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
</style>
@endsection
