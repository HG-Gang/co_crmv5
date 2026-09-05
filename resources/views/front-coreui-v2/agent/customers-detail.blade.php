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
            <p class="text-body-secondary mb-0">查看客户的完整信息和交易数据</p>
        </div>
    </div>

    <div class="row">
        <!-- Left Column - Customer Info -->
        <div class="col-lg-8">
            <!-- Basic Info -->
            <div class="card shadow-sm border-0 mb-4">
                <div class="card-header bg-gradient-primary text-white">
                    <h5 class="mb-0">
                        <i class="cil-user me-2"></i>基本信息
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
                                <label class="text-body-secondary small mb-1">邮箱地址</label>
                                <h5 class="mb-0" id="customerEmail">-</h5>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="border-start border-info border-4 ps-3">
                                <label class="text-body-secondary small mb-1">手机号码</label>
                                <h5 class="mb-0" id="customerPhone">-</h5>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="border-start border-warning border-4 ps-3">
                                <label class="text-body-secondary small mb-1">注册时间</label>
                                <h5 class="mb-0" id="registeredAt">-</h5>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Account Info -->
            <div class="card shadow-sm border-0 mb-4">
                <div class="card-header bg-transparent border-bottom">
                    <h5 class="mb-0">
                        <i class="cil-wallet me-2"></i>账户信息
                    </h5>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <div class="d-flex justify-content-between align-items-center p-3 bg-light rounded">
                                <span class="text-body-secondary">MT4账号</span>
                                <strong id="mt4Account">-</strong>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="d-flex justify-content-between align-items-center p-3 bg-light rounded">
                                <span class="text-body-secondary">账户余额</span>
                                <strong class="text-success" id="balance">$0.00</strong>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="d-flex justify-content-between align-items-center p-3 bg-light rounded">
                                <span class="text-body-secondary">可用保证金</span>
                                <strong id="freeMargin">$0.00</strong>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="d-flex justify-content-between align-items-center p-3 border rounded">
                                <span class="text-body-secondary">净值</span>
                                <strong id="equity">$0.00</strong>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="d-flex justify-content-between align-items-center p-3 border rounded">
                                <span class="text-body-secondary">占用保证金</span>
                                <strong id="usedMargin">$0.00</strong>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="d-flex justify-content-between align-items-center p-3 border rounded">
                                <span class="text-body-secondary">保证金比例</span>
                                <strong id="marginLevel">0%</strong>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Trading Stats -->
            <div class="card shadow-sm border-0 mb-4">
                <div class="card-header bg-transparent border-bottom">
                    <h5 class="mb-0">
                        <i class="cil-chart-line me-2"></i>交易统计
                    </h5>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-3">
                            <div class="text-center p-3 border rounded">
                                <p class="text-body-secondary mb-2 small">总交易次数</p>
                                <h4 class="mb-0 fw-bold text-primary" id="totalTrades">0</h4>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="text-center p-3 border rounded">
                                <p class="text-body-secondary mb-2 small">总交易手数</p>
                                <h4 class="mb-0 fw-bold text-info" id="totalLots">0.00</h4>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="text-center p-3 border rounded">
                                <p class="text-body-secondary mb-2 small">盈利次数</p>
                                <h4 class="mb-0 fw-bold text-success" id="profitTrades">0</h4>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="text-center p-3 border rounded">
                                <p class="text-body-secondary mb-2 small">亏损次数</p>
                                <h4 class="mb-0 fw-bold text-danger" id="lossTrades">0</h4>
                            </div>
                        </div>
                    </div>

                    <div class="mt-4">
                        <div class="d-flex justify-content-between mb-2">
                            <span class="text-body-secondary">胜率</span>
                            <strong id="winRate">0%</strong>
                        </div>
                        <div class="progress" style="height: 10px;">
                            <div class="progress-bar bg-success" id="winRateBar" style="width: 0%"></div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Recent Trades -->
            <div class="card shadow-sm border-0">
                <div class="card-header bg-transparent border-bottom">
                    <div class="d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">
                            <i class="cil-history me-2"></i>最近交易
                        </h5>
                        <a href="{{ route('front_coreui_v2_page_order_closed') }}" class="btn btn-sm btn-outline-primary">
                            查看全部
                        </a>
                    </div>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>订单号</th>
                                    <th>品种</th>
                                    <th>类型</th>
                                    <th>手数</th>
                                    <th>盈亏</th>
                                    <th>平仓时间</th>
                                </tr>
                            </thead>
                            <tbody id="recentTradesTable">
                                <tr>
                                    <td colspan="6" class="text-center py-4 text-body-secondary">
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

        <!-- Right Column - Stats & Actions -->
        <div class="col-lg-4">
            <!-- Total Profit/Loss -->
            <div class="card shadow-sm border-0 mb-4 text-white" id="profitCard">
                <div class="card-body text-center py-4">
                    <h6 class="mb-3 opacity-75">
                        <i class="cil-dollar me-2"></i>累计盈亏
                    </h6>
                    <h1 class="mb-3 fw-bold" id="totalProfit">$0.00</h1>
                    <div class="d-flex justify-content-center gap-3">
                        <div>
                            <p class="mb-0 small opacity-75">累计入金</p>
                            <h5 class="mb-0" id="totalDeposit">$0</h5>
                        </div>
                        <div class="vr"></div>
                        <div>
                            <p class="mb-0 small opacity-75">累计出金</p>
                            <h5 class="mb-0" id="totalWithdraw">$0</h5>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Commission Info -->
            <div class="card shadow-sm border-0 mb-4">
                <div class="card-header bg-transparent border-bottom">
                    <h6 class="mb-0">
                        <i class="cil-money me-2"></i>佣金信息
                    </h6>
                </div>
                <div class="card-body">
                    <div class="mb-3 pb-3 border-bottom">
                        <div class="d-flex justify-content-between mb-1">
                            <span class="text-body-secondary small">累计佣金</span>
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

            <!-- Verification Status -->
            <div class="card shadow-sm border-0 mb-4">
                <div class="card-header bg-transparent border-bottom">
                    <h6 class="mb-0">
                        <i class="cil-shield-alt me-2"></i>认证状态
                    </h6>
                </div>
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <span>实名认证</span>
                        <span class="badge bg-success" id="idVerified">未认证</span>
                    </div>
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <span>邮箱验证</span>
                        <span class="badge bg-success" id="emailVerified">未验证</span>
                    </div>
                    <div class="d-flex justify-content-between align-items-center">
                        <span>手机验证</span>
                        <span class="badge bg-secondary" id="phoneVerified">未验证</span>
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
                        <button class="btn btn-outline-primary" onclick="viewPositions()">
                            <i class="cil-layers me-2"></i>查看持仓
                        </button>
                        <button class="btn btn-outline-secondary" onclick="viewOrders()">
                            <i class="cil-list me-2"></i>交易记录
                        </button>
                        <button class="btn btn-outline-info" onclick="viewFlows()">
                            <i class="cil-swap-horizontal me-2"></i>资金流水
                        </button>
                        <button class="btn btn-outline-success" onclick="viewCommissions()">
                            <i class="cil-dollar me-2"></i>佣金明细
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
        loadRecentTrades();
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
    // Basic Info
    document.getElementById('customerName').textContent = customer.name || '-';
    document.getElementById('customerEmail').textContent = customer.email || '-';
    document.getElementById('customerPhone').textContent = customer.phone || '-';
    document.getElementById('registeredAt').textContent = customer.created_at || '-';

    // Account Info
    document.getElementById('mt4Account').textContent = customer.mt4_account || '-';
    document.getElementById('balance').textContent = formatCurrency(customer.balance);
    document.getElementById('freeMargin').textContent = formatCurrency(customer.free_margin);
    document.getElementById('equity').textContent = formatCurrency(customer.equity);
    document.getElementById('usedMargin').textContent = formatCurrency(customer.used_margin);
    document.getElementById('marginLevel').textContent = formatPercent(customer.margin_level);

    // Trading Stats
    document.getElementById('totalTrades').textContent = customer.total_trades || 0;
    document.getElementById('totalLots').textContent = formatNumber(customer.total_lots);
    document.getElementById('profitTrades').textContent = customer.profit_trades || 0;
    document.getElementById('lossTrades').textContent = customer.loss_trades || 0;

    const winRate = customer.win_rate || 0;
    document.getElementById('winRate').textContent = formatPercent(winRate);
    document.getElementById('winRateBar').style.width = winRate + '%';

    // Profit Card
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

    // Commission Info
    document.getElementById('totalCommission').textContent = formatCurrency(customer.total_commission);
    document.getElementById('monthlyCommission').textContent = formatCurrency(customer.monthly_commission);
    document.getElementById('todayCommission').textContent = formatCurrency(customer.today_commission);

    // Verification Status
    document.getElementById('idVerified').textContent = customer.id_verified ? '已认证' : '未认证';
    document.getElementById('idVerified').className = customer.id_verified ? 'badge bg-success' : 'badge bg-secondary';

    document.getElementById('emailVerified').textContent = customer.email_verified ? '已验证' : '未验证';
    document.getElementById('emailVerified').className = customer.email_verified ? 'badge bg-success' : 'badge bg-secondary';

    document.getElementById('phoneVerified').textContent = customer.phone_verified ? '已验证' : '未验证';
    document.getElementById('phoneVerified').className = customer.phone_verified ? 'badge bg-success' : 'badge bg-secondary';
}

function loadRecentTrades() {
    fetch(`{{ route("front_api_agent_customer_recent_trades") }}?id=${customerId}&limit=10`, {
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(res => res.json())
    .then(data => {
        if (data.success && data.trades) {
            renderRecentTrades(data.trades);
        }
    })
    .catch(err => {
        console.error('Load recent trades error:', err);
    });
}

function renderRecentTrades(trades) {
    const tbody = document.getElementById('recentTradesTable');

    if (trades.length === 0) {
        tbody.innerHTML = `
            <tr>
                <td colspan="6" class="text-center py-4 text-body-secondary">
                    暂无交易记录
                </td>
            </tr>
        `;
        return;
    }

    const html = trades.map(t => `
        <tr>
            <td class="small">${t.order_number || '-'}</td>
            <td><span class="badge bg-secondary small">${t.symbol || '-'}</span></td>
            <td>${getTypeBadge(t.type)}</td>
            <td class="fw-semibold small">${formatNumber(t.lots)}</td>
            <td class="${t.profit >= 0 ? 'text-success' : 'text-danger'} fw-semibold small">
                ${t.profit >= 0 ? '+' : ''}${formatCurrency(t.profit)}
            </td>
            <td class="text-body-secondary small">${t.close_time || '-'}</td>
        </tr>
    `).join('');

    tbody.innerHTML = html;
}

function viewPositions() {
    window.location.href = `{{ route('front_coreui_v2_page_position_summary') }}?customer=${customerId}`;
}

function viewOrders() {
    window.location.href = `{{ route('front_coreui_v2_page_order_closed') }}?customer=${customerId}`;
}

function viewFlows() {
    window.location.href = `{{ route('front_coreui_v2_page_flow') }}?customer=${customerId}`;
}

function viewCommissions() {
    window.location.href = `{{ route('front_coreui_v2_page_commission_history') }}?customer=${customerId}`;
}

function getTypeBadge(type) {
    if (type === 'buy' || type === 0) {
        return '<span class="badge bg-success small">买入</span>';
    } else if (type === 'sell' || type === 1) {
        return '<span class="badge bg-danger small">卖出</span>';
    }
    return '<span class="badge bg-secondary small">-</span>';
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
