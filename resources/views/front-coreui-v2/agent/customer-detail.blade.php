@extends('front-coreui-v2.layouts.app')

@section('title', '客户详情')

@section('content')
<div class="container-lg px-4">
    <!-- Page Header -->
    <div class="row mb-4">
        <div class="col-12">
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="{{ route('front_coreui_v2_page_agent_customers') }}">客户管理</a></li>
                    <li class="breadcrumb-item active">客户详情</li>
                </ol>
            </nav>
            <h2 class="mb-2">客户详情</h2>
            <p class="text-body-secondary mb-0">查看客户的详细信息和交易表现</p>
        </div>
    </div>

    <div class="row">
        <!-- Left Column -->
        <div class="col-lg-8">
            <!-- Customer Profile -->
            <div class="card shadow-sm border-0 mb-4">
                <div class="card-header bg-gradient-primary text-white">
                    <h5 class="mb-0">
                        <i class="cil-user me-2"></i>客户档案
                    </h5>
                </div>
                <div class="card-body">
                    <div class="row g-4">
                        <div class="col-md-6">
                            <div class="border-start border-primary border-4 ps-3">
                                <label class="text-body-secondary small mb-1">客户姓名</label>
                                <h5 class="mb-0" id="customerName">-</h5>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="border-start border-secondary border-4 ps-3">
                                <label class="text-body-secondary small mb-1">联系邮箱</label>
                                <h5 class="mb-0" id="customerEmail">-</h5>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="border-start border-info border-4 ps-3">
                                <label class="text-body-secondary small mb-1">MT4账户</label>
                                <h5 class="mb-0" id="mt4Account">-</h5>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="border-start border-warning border-4 ps-3">
                                <label class="text-body-secondary small mb-1">客户编号</label>
                                <h5 class="mb-0" id="customerCode">-</h5>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Performance Stats -->
            <div class="card shadow-sm border-0 mb-4">
                <div class="card-header bg-transparent border-bottom">
                    <h5 class="mb-0">
                        <i class="cil-chart-line me-2"></i>业绩统计
                    </h5>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <div class="text-center p-4 border rounded">
                                <i class="cil-list text-primary mb-2" style="font-size: 2rem;"></i>
                                <p class="text-body-secondary mb-2 small">总交易次数</p>
                                <h3 class="mb-0 fw-bold text-primary" id="totalTrades">0</h3>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="text-center p-4 border rounded">
                                <i class="cil-chart-line text-info mb-2" style="font-size: 2rem;"></i>
                                <p class="text-body-secondary mb-2 small">总交易手数</p>
                                <h3 class="mb-0 fw-bold text-info" id="totalVolume">0.00</h3>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="text-center p-4 border rounded">
                                <i class="cil-check-circle text-success mb-2" style="font-size: 2rem;"></i>
                                <p class="text-body-secondary mb-2 small">盈利交易</p>
                                <h3 class="mb-0 fw-bold text-success" id="profitTrades">0</h3>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="text-center p-4 border rounded">
                                <i class="cil-x-circle text-danger mb-2" style="font-size: 2rem;"></i>
                                <p class="text-body-secondary mb-2 small">亏损交易</p>
                                <h3 class="mb-0 fw-bold text-danger" id="lossTrades">0</h3>
                            </div>
                        </div>
                    </div>

                    <div class="mt-4">
                        <div class="d-flex justify-content-between mb-2">
                            <span class="text-body-secondary">交易胜率</span>
                            <strong id="winRate">0%</strong>
                        </div>
                        <div class="progress" style="height: 12px;">
                            <div class="progress-bar bg-success" id="winRateBar" style="width: 0%"></div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Account Balance -->
            <div class="card shadow-sm border-0 mb-4">
                <div class="card-header bg-transparent border-bottom">
                    <h5 class="mb-0">
                        <i class="cil-wallet me-2"></i>账户余额
                    </h5>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <div class="d-flex justify-content-between align-items-center p-3 bg-light rounded">
                                <span class="text-body-secondary">账户余额</span>
                                <strong class="text-success" id="balance">$0.00</strong>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="d-flex justify-content-between align-items-center p-3 bg-light rounded">
                                <span class="text-body-secondary">净值</span>
                                <strong id="equity">$0.00</strong>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="d-flex justify-content-between align-items-center p-3 bg-light rounded">
                                <span class="text-body-secondary">可用保证金</span>
                                <strong id="freeMargin">$0.00</strong>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Recent Activity -->
            <div class="card shadow-sm border-0">
                <div class="card-header bg-transparent border-bottom">
                    <div class="d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">
                            <i class="cil-history me-2"></i>最近活动
                        </h5>
                        <button onclick="viewAllActivities()" class="btn btn-sm btn-outline-primary">
                            查看全部
                        </button>
                    </div>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>时间</th>
                                    <th>活动类型</th>
                                    <th>描述</th>
                                    <th>金额</th>
                                </tr>
                            </thead>
                            <tbody id="activityTable">
                                <tr>
                                    <td colspan="4" class="text-center py-4 text-body-secondary">
                                        <div class="spinner-border spinner-border-sm me-2"></div>
                                        加载中...
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Right Column -->
        <div class="col-lg-4">
            <!-- Total Performance -->
            <div class="card shadow-sm border-0 mb-4 text-white" id="profitCard">
                <div class="card-body text-center py-4">
                    <h6 class="mb-3 opacity-75">
                        <i class="cil-dollar me-2"></i>总盈亏
                    </h6>
                    <h1 class="mb-3 fw-bold" id="totalProfit">$0.00</h1>
                    <div class="d-flex justify-content-center gap-3">
                        <div>
                            <p class="mb-0 small opacity-75">总入金</p>
                            <h5 class="mb-0" id="totalDeposit">$0</h5>
                        </div>
                        <div class="vr"></div>
                        <div>
                            <p class="mb-0 small opacity-75">总出金</p>
                            <h5 class="mb-0" id="totalWithdraw">$0</h5>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Commission Summary -->
            <div class="card shadow-sm border-0 mb-4">
                <div class="card-header bg-transparent border-bottom">
                    <h6 class="mb-0">
                        <i class="cil-money me-2"></i>佣金汇总
                    </h6>
                </div>
                <div class="card-body">
                    <div class="mb-3 pb-3 border-bottom">
                        <div class="d-flex justify-content-between mb-1">
                            <span class="text-body-secondary small">累计获得</span>
                            <strong class="text-success" id="totalCommission">$0.00</strong>
                        </div>
                    </div>
                    <div class="mb-3 pb-3 border-bottom">
                        <div class="d-flex justify-content-between mb-1">
                            <span class="text-body-secondary small">本月佣金</span>
                            <strong id="monthlyCommission">$0.00</strong>
                        </div>
                    </div>
                    <div>
                        <div class="d-flex justify-content-between mb-1">
                            <span class="text-body-secondary small">今日佣金</span>
                            <strong id="todayCommission">$0.00</strong>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Customer Status -->
            <div class="card shadow-sm border-0 mb-4">
                <div class="card-header bg-transparent border-bottom">
                    <h6 class="mb-0">
                        <i class="cil-info me-2"></i>客户状态
                    </h6>
                </div>
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <span>账户状态</span>
                        <span class="badge bg-success" id="accountStatus">正常</span>
                    </div>
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <span>实名认证</span>
                        <span class="badge bg-secondary" id="verifiedStatus">未认证</span>
                    </div>
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <span>注册时间</span>
                        <span class="text-body-secondary small" id="registerTime">-</span>
                    </div>
                    <div class="d-flex justify-content-between align-items-center">
                        <span>最后登录</span>
                        <span class="text-body-secondary small" id="lastLogin">-</span>
                    </div>
                </div>
            </div>

            <!-- Quick Actions -->
            <div class="card shadow-sm border-0">
                <div class="card-header bg-transparent border-bottom">
                    <h6 class="mb-0">
                        <i class="cil-settings me-2"></i>快捷操作
                    </h6>
                </div>
                <div class="card-body">
                    <div class="d-grid gap-2">
                        <button class="btn btn-outline-primary" onclick="viewTrades()">
                            <i class="cil-list me-2"></i>交易记录
                        </button>
                        <button class="btn btn-outline-secondary" onclick="viewPositions()">
                            <i class="cil-layers me-2"></i>持仓情况
                        </button>
                        <button class="btn btn-outline-info" onclick="viewFlows()">
                            <i class="cil-swap-horizontal me-2"></i>资金流水
                        </button>
                        <button class="btn btn-outline-success" onclick="contactCustomer()">
                            <i class="cil-envelope-closed me-2"></i>联系客户
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
let customerId = '';

document.addEventListener('DOMContentLoaded', function() {
    const urlParams = new URLSearchParams(window.location.search);
    customerId = urlParams.get('id');

    if (customerId) {
        loadCustomerDetail();
        loadRecentActivity();
    } else {
        alert('客户ID不能为空');
        window.location.href = '{{ route("front_coreui_v2_page_agent_customers") }}';
    }
});

function loadCustomerDetail() {
    fetch(`{{ route("front_api_agent_customer_detail") }}?id=${customerId}`, {
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(res => res.json())
    .then(data => {
        if (data.success && data.customer) {
            renderCustomerDetail(data.customer);
        } else {
            alert(data.message || '客户不存在');
            window.location.href = '{{ route("front_coreui_v2_page_agent_customers") }}';
        }
    })
    .catch(err => {
        console.error('Load customer detail error:', err);
    });
}

function renderCustomerDetail(customer) {
    // Profile
    document.getElementById('customerName').textContent = customer.name || '-';
    document.getElementById('customerEmail').textContent = customer.email || '-';
    document.getElementById('mt4Account').textContent = customer.mt4_account || '-';
    document.getElementById('customerCode').textContent = customer.customer_code || '-';

    // Performance Stats
    document.getElementById('totalTrades').textContent = customer.total_trades || 0;
    document.getElementById('totalVolume').textContent = formatNumber(customer.total_volume);
    document.getElementById('profitTrades').textContent = customer.profit_trades || 0;
    document.getElementById('lossTrades').textContent = customer.loss_trades || 0;

    const winRate = customer.win_rate || 0;
    document.getElementById('winRate').textContent = formatPercent(winRate);
    document.getElementById('winRateBar').style.width = winRate + '%';

    // Account Balance
    document.getElementById('balance').textContent = formatCurrency(customer.balance);
    document.getElementById('equity').textContent = formatCurrency(customer.equity);
    document.getElementById('freeMargin').textContent = formatCurrency(customer.free_margin);

    // Total Performance
    const profit = parseFloat(customer.total_profit || 0);
    const profitEl = document.getElementById('totalProfit');
    const profitCard = document.getElementById('profitCard');

    profitEl.textContent = formatCurrency(profit);
    if (profit >= 0) {
        profitCard.style.background = 'linear-gradient(135deg, #10b981 0%, #059669 100%)';
    } else {
        profitCard.style.background = 'linear-gradient(135deg, #ef4444 0%, #dc2626 100%)';
    }

    document.getElementById('totalDeposit').textContent = formatCurrency(customer.total_deposit);
    document.getElementById('totalWithdraw').textContent = formatCurrency(customer.total_withdraw);

    // Commission Summary
    document.getElementById('totalCommission').textContent = formatCurrency(customer.total_commission);
    document.getElementById('monthlyCommission').textContent = formatCurrency(customer.monthly_commission);
    document.getElementById('todayCommission').textContent = formatCurrency(customer.today_commission);

    // Status
    document.getElementById('accountStatus').textContent = getStatusText(customer.status);
    document.getElementById('accountStatus').className = getStatusBadgeClass(customer.status);
    document.getElementById('verifiedStatus').textContent = customer.is_verified ? '已认证' : '未认证';
    document.getElementById('verifiedStatus').className = customer.is_verified ? 'badge bg-success' : 'badge bg-secondary';
    document.getElementById('registerTime').textContent = customer.created_at || '-';
    document.getElementById('lastLogin').textContent = customer.last_login || '-';
}

function loadRecentActivity() {
    fetch(`{{ route("front_api_agent_customer_activity") }}?id=${customerId}&limit=10`, {
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(res => res.json())
    .then(data => {
        if (data.success && data.activities) {
            renderActivities(data.activities);
        }
    })
    .catch(err => {
        console.error('Load activity error:', err);
    });
}

function renderActivities(activities) {
    const tbody = document.getElementById('activityTable');

    if (activities.length === 0) {
        tbody.innerHTML = `
            <tr>
                <td colspan="4" class="text-center py-4 text-body-secondary">
                    暂无活动记录
                </td>
            </tr>
        `;
        return;
    }

    const html = activities.map(a => `
        <tr>
            <td class="text-body-secondary small">${a.time || '-'}</td>
            <td><span class="badge ${getActivityBadge(a.type)}">${a.type_text || '-'}</span></td>
            <td class="small">${a.description || '-'}</td>
            <td class="fw-semibold small">${a.amount ? formatCurrency(a.amount) : '-'}</td>
        </tr>
    `).join('');

    tbody.innerHTML = html;
}

function viewTrades() {
    window.location.href = `{{ route('front_coreui_v2_page_order_closed') }}?customer=${customerId}`;
}

function viewPositions() {
    window.location.href = `{{ route('front_coreui_v2_page_position_summary') }}?customer=${customerId}`;
}

function viewFlows() {
    window.location.href = `{{ route('front_coreui_v2_page_flow') }}?customer=${customerId}`;
}

function viewAllActivities() {
    alert('查看全部活动功能开发中');
}

function contactCustomer() {
    alert('联系客户功能开发中');
}

function getStatusText(status) {
    const texts = {
        'active': '正常',
        'inactive': '未激活',
        'frozen': '冻结'
    };
    return texts[status] || '-';
}

function getStatusBadgeClass(status) {
    const classes = {
        'active': 'badge bg-success',
        'inactive': 'badge bg-secondary',
        'frozen': 'badge bg-danger'
    };
    return classes[status] || 'badge bg-secondary';
}

function getActivityBadge(type) {
    const badges = {
        'deposit': 'bg-success',
        'withdraw': 'bg-warning',
        'trade': 'bg-primary',
        'commission': 'bg-info'
    };
    return badges[type] || 'bg-secondary';
}

function formatCurrency(value) {
    if (!value && value !== 0) return '-';
    return '$' + parseFloat(value).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

function formatNumber(value) {
    if (!value && value !== 0) return '-';
    return parseFloat(value).toFixed(2);
}

function formatPercent(value) {
    if (!value && value !== 0) return '0%';
    return parseFloat(value).toFixed(2) + '%';
}
</script>

<style>
.bg-gradient-primary {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
}
</style>
@endsection
