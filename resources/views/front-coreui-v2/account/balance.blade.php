@extends('front-coreui-v2.layouts.app')

@section('title', '账户余额')

@section('content')
<div class="container-lg px-4">
    <!-- Page Header -->
    <div class="row mb-4">
        <div class="col-12">
            <h2 class="mb-2">账户余额</h2>
            <p class="text-body-secondary mb-0">查看您的账户资金详情和余额变动</p>
        </div>
    </div>

    <!-- Summary Cards -->
    <div class="row g-4 mb-4">
        <div class="col-lg-3 col-md-6">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <p class="text-body-secondary mb-2">账户总余额</p>
                            <h3 class="mb-0 fw-bold text-primary" id="totalBalance">$0.00</h3>
                        </div>
                        <div class="avatar avatar-sm bg-primary bg-opacity-10 text-primary">
                            <i class="cil-wallet"></i>
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
                            <p class="text-body-secondary mb-2">账户净值</p>
                            <h3 class="mb-0 fw-bold text-success" id="totalEquity">$0.00</h3>
                        </div>
                        <div class="avatar avatar-sm bg-success bg-opacity-10 text-success">
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
                            <h3 class="mb-0 fw-bold text-info" id="totalProfit">$0.00</h3>
                        </div>
                        <div class="avatar avatar-sm bg-info bg-opacity-10 text-info">
                            <i class="cil-arrow-top"></i>
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
                            <p class="text-body-secondary mb-2">可用保证金</p>
                            <h3 class="mb-0 fw-bold text-warning" id="totalFreeMargin">$0.00</h3>
                        </div>
                        <div class="avatar avatar-sm bg-warning bg-opacity-10 text-warning">
                            <i class="cil-dollar"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Account Selector -->
    <div class="card shadow-sm border-0 mb-4">
        <div class="card-body">
            <div class="row g-3 align-items-end">
                <div class="col-md-4">
                    <label class="form-label small">选择账户</label>
                    <select id="selectAccount" class="form-select" onchange="loadAccountBalance()">
                        <option value="">全部账户</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label small">时间范围</label>
                    <select id="filterTimeRange" class="form-select">
                        <option value="today">今日</option>
                        <option value="week">本周</option>
                        <option value="month" selected>本月</option>
                        <option value="year">本年</option>
                        <option value="all">全部</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <button onclick="loadAccountBalance()" class="btn btn-primary w-100">
                        <i class="cil-reload me-2"></i>刷新
                    </button>
                </div>
                <div class="col-md-3 text-end">
                    <button onclick="exportData()" class="btn btn-outline-secondary">
                        <i class="cil-cloud-download me-2"></i>导出报表
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Balance Details -->
    <div class="row g-4">
        <!-- Left Column - Balance Chart -->
        <div class="col-lg-8">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-transparent border-bottom">
                    <h5 class="mb-0">
                        <i class="cil-chart-line me-2"></i>余额变动趋势
                    </h5>
                </div>
                <div class="card-body">
                    <canvas id="balanceChart" height="300"></canvas>
                </div>
            </div>

            <!-- Recent Transactions -->
            <div class="card shadow-sm border-0 mt-4">
                <div class="card-header bg-transparent border-bottom">
                    <h5 class="mb-0">
                        <i class="cil-list me-2"></i>最近交易记录
                    </h5>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>时间</th>
                                    <th>类型</th>
                                    <th>MT4账号</th>
                                    <th>金额</th>
                                    <th>余额</th>
                                    <th>备注</th>
                                </tr>
                            </thead>
                            <tbody id="transactionsTable">
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

        <!-- Right Column - Account Stats -->
        <div class="col-lg-4">
            <div class="card shadow-sm border-0 mb-4">
                <div class="card-header bg-transparent border-bottom">
                    <h5 class="mb-0">
                        <i class="cil-info me-2"></i>账户统计
                    </h5>
                </div>
                <div class="card-body">
                    <div class="mb-4">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <span class="text-body-secondary">累计入金</span>
                            <span class="fw-bold text-success" id="totalDeposit">$0.00</span>
                        </div>
                        <div class="progress" style="height: 6px;">
                            <div class="progress-bar bg-success" id="depositProgress" style="width: 0%"></div>
                        </div>
                    </div>

                    <div class="mb-4">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <span class="text-body-secondary">累计出金</span>
                            <span class="fw-bold text-danger" id="totalWithdraw">$0.00</span>
                        </div>
                        <div class="progress" style="height: 6px;">
                            <div class="progress-bar bg-danger" id="withdrawProgress" style="width: 0%"></div>
                        </div>
                    </div>

                    <div class="mb-4">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <span class="text-body-secondary">累计盈亏</span>
                            <span class="fw-bold" id="totalProfitLoss">$0.00</span>
                        </div>
                    </div>

                    <div class="border-top pt-3">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <span class="text-body-secondary small">入金次数</span>
                            <span class="fw-semibold" id="depositCount">0</span>
                        </div>
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <span class="text-body-secondary small">出金次数</span>
                            <span class="fw-semibold" id="withdrawCount">0</span>
                        </div>
                        <div class="d-flex justify-content-between align-items-center">
                            <span class="text-body-secondary small">交易笔数</span>
                            <span class="fw-semibold" id="tradeCount">0</span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card shadow-sm border-0 bg-gradient-primary text-white">
                <div class="card-body">
                    <h6 class="mb-3">
                        <i class="cil-lightbulb me-2"></i>温馨提示
                    </h6>
                    <ul class="mb-0 small opacity-90" style="list-style: none; padding-left: 0;">
                        <li class="mb-2"><i class="cil-check-circle me-2"></i>余额数据每5分钟更新一次</li>
                        <li class="mb-2"><i class="cil-check-circle me-2"></i>净值包含浮动盈亏</li>
                        <li class="mb-2"><i class="cil-check-circle me-2"></i>可用保证金可用于开仓</li>
                        <li class="mb-0"><i class="cil-check-circle me-2"></i>如有疑问请联系客服</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    loadAccounts();
    loadAccountBalance();
});

function loadAccounts() {
    fetch('{{ route("front_api_account_list") }}', {
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(res => res.json())
    .then(data => {
        if (data.success && data.accounts) {
            const select = document.getElementById('selectAccount');
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

function loadAccountBalance() {
    const accountId = document.getElementById('selectAccount').value;
    const timeRange = document.getElementById('filterTimeRange').value;

    const params = new URLSearchParams();
    if (accountId) params.append('account_id', accountId);
    if (timeRange) params.append('time_range', timeRange);

    fetch('{{ route("front_api_account_balance") }}?' + params.toString(), {
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            updateSummary(data.summary);
            updateStats(data.stats);
            renderTransactions(data.transactions);
        }
    })
    .catch(err => {
        console.error('Load balance error:', err);
    });
}

function updateSummary(summary) {
    document.getElementById('totalBalance').textContent = formatCurrency(summary?.total_balance);
    document.getElementById('totalEquity').textContent = formatCurrency(summary?.total_equity);
    document.getElementById('totalProfit').textContent = formatCurrency(summary?.total_profit);
    document.getElementById('totalFreeMargin').textContent = formatCurrency(summary?.total_free_margin);
}

function updateStats(stats) {
    const totalDeposit = parseFloat(stats?.total_deposit || 0);
    const totalWithdraw = parseFloat(stats?.total_withdraw || 0);
    const totalAmount = totalDeposit + totalWithdraw;

    document.getElementById('totalDeposit').textContent = formatCurrency(totalDeposit);
    document.getElementById('totalWithdraw').textContent = formatCurrency(totalWithdraw);
    document.getElementById('totalProfitLoss').textContent = formatCurrency(stats?.total_profit_loss);

    if (totalAmount > 0) {
        document.getElementById('depositProgress').style.width = ((totalDeposit / totalAmount) * 100) + '%';
        document.getElementById('withdrawProgress').style.width = ((totalWithdraw / totalAmount) * 100) + '%';
    }

    document.getElementById('depositCount').textContent = stats?.deposit_count || 0;
    document.getElementById('withdrawCount').textContent = stats?.withdraw_count || 0;
    document.getElementById('tradeCount').textContent = stats?.trade_count || 0;

    const profitLossEl = document.getElementById('totalProfitLoss');
    if (stats?.total_profit_loss >= 0) {
        profitLossEl.className = 'fw-bold text-success';
    } else {
        profitLossEl.className = 'fw-bold text-danger';
    }
}

function renderTransactions(transactions) {
    const tbody = document.getElementById('transactionsTable');

    if (!transactions || transactions.length === 0) {
        tbody.innerHTML = `
            <tr>
                <td colspan="6" class="text-center py-4 text-body-secondary">
                    暂无交易记录
                </td>
            </tr>
        `;
        return;
    }

    const html = transactions.map(t => `
        <tr>
            <td>${t.created_at || '-'}</td>
            <td>${getTransactionTypeBadge(t.type)}</td>
            <td>${t.mt4_account || '-'}</td>
            <td class="${t.amount >= 0 ? 'text-success' : 'text-danger'} fw-semibold">
                ${t.amount >= 0 ? '+' : ''}${formatCurrency(t.amount)}
            </td>
            <td>${formatCurrency(t.balance_after)}</td>
            <td class="text-body-secondary small">${t.comment || '-'}</td>
        </tr>
    `).join('');

    tbody.innerHTML = html;
}

function getTransactionTypeBadge(type) {
    const badges = {
        'deposit': '<span class="badge bg-success">入金</span>',
        'withdraw': '<span class="badge bg-danger">出金</span>',
        'trade': '<span class="badge bg-primary">交易</span>',
        'commission': '<span class="badge bg-info">佣金</span>',
        'bonus': '<span class="badge bg-warning text-dark">赠金</span>'
    };
    return badges[type] || '<span class="badge bg-secondary">其他</span>';
}

function formatCurrency(value) {
    if (!value) return '$0.00';
    return '$' + parseFloat(value).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

function exportData() {
    const accountId = document.getElementById('selectAccount').value;
    const timeRange = document.getElementById('filterTimeRange').value;
    window.location.href = '{{ route("front_api_account_balance_export") }}?account_id=' + accountId + '&time_range=' + timeRange;
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
