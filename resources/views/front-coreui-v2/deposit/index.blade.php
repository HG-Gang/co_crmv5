@extends('front-coreui-v2.layouts.app')

@section('title', '在线入金')

@section('content')
<div class="container-lg px-4">
    <!-- Page Header -->
    <div class="row mb-4">
        <div class="col-12">
            <h2 class="mb-2">在线入金</h2>
            <p class="text-body-secondary mb-0">为您的MT4账户充值</p>
        </div>
    </div>

    <div class="row">
        <!-- Left Column - Deposit Form -->
        <div class="col-lg-8">
            <div class="card shadow-sm border-0 mb-4">
                <div class="card-header bg-gradient-primary text-white border-0">
                    <h5 class="mb-0">
                        <i class="cil-arrow-thick-to-bottom me-2"></i>入金申请
                    </h5>
                </div>
                <div class="card-body p-4">
                    <form id="depositForm">
                        <div class="mb-4">
                            <label class="form-label fw-semibold">选择MT4账户 <span class="text-danger">*</span></label>
                            <select id="mt4Account" class="form-select form-select-lg" required onchange="loadAccountInfo()">
                                <option value="">请选择入金账户</option>
                            </select>
                            <div class="form-text">当前余额: <span id="currentBalance" class="fw-bold">$0.00</span></div>
                        </div>

                        <div class="mb-4">
                            <label class="form-label fw-semibold">入金金额 <span class="text-danger">*</span></label>
                            <div class="input-group input-group-lg">
                                <span class="input-group-text">$</span>
                                <input type="number" id="depositAmount" class="form-control" placeholder="请输入入金金额" min="100" step="0.01" required>
                            </div>
                            <div class="form-text">最低入金金额: $100.00</div>
                        </div>

                        <div class="mb-4">
                            <label class="form-label fw-semibold">支付方式 <span class="text-danger">*</span></label>
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <div class="form-check payment-option">
                                        <input class="form-check-input" type="radio" name="paymentMethod" id="bankTransfer" value="bank_transfer" checked>
                                        <label class="form-check-label w-100" for="bankTransfer">
                                            <div class="d-flex align-items-center">
                                                <i class="cil-bank me-3 text-primary" style="font-size: 2rem;"></i>
                                                <div>
                                                    <strong>银行转账</strong>
                                                    <p class="mb-0 small text-body-secondary">到账时间: 1-2小时</p>
                                                </div>
                                            </div>
                                        </label>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-check payment-option">
                                        <input class="form-check-input" type="radio" name="paymentMethod" id="alipay" value="alipay">
                                        <label class="form-check-label w-100" for="alipay">
                                            <div class="d-flex align-items-center">
                                                <i class="cil-wallet me-3 text-info" style="font-size: 2rem;"></i>
                                                <div>
                                                    <strong>支付宝</strong>
                                                    <p class="mb-0 small text-body-secondary">到账时间: 即时</p>
                                                </div>
                                            </div>
                                        </label>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-check payment-option">
                                        <input class="form-check-input" type="radio" name="paymentMethod" id="wechat" value="wechat">
                                        <label class="form-check-label w-100" for="wechat">
                                            <div class="d-flex align-items-center">
                                                <i class="cil-chat-bubble me-3 text-success" style="font-size: 2rem;"></i>
                                                <div>
                                                    <strong>微信支付</strong>
                                                    <p class="mb-0 small text-body-secondary">到账时间: 即时</p>
                                                </div>
                                            </div>
                                        </label>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-check payment-option">
                                        <input class="form-check-input" type="radio" name="paymentMethod" id="usdt" value="usdt">
                                        <label class="form-check-label w-100" for="usdt">
                                            <div class="d-flex align-items-center">
                                                <i class="cil-dollar me-3 text-warning" style="font-size: 2rem;"></i>
                                                <div>
                                                    <strong>USDT</strong>
                                                    <p class="mb-0 small text-body-secondary">到账时间: 10-30分钟</p>
                                                </div>
                                            </div>
                                        </label>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="mb-4">
                            <label class="form-label fw-semibold">备注说明</label>
                            <textarea id="depositNote" class="form-control" rows="3" placeholder="请输入备注信息（选填）"></textarea>
                        </div>

                        <div class="mb-4">
                            <div class="alert alert-info border-0">
                                <i class="cil-info me-2"></i>
                                <strong>温馨提示：</strong>
                                <ul class="mb-0 mt-2 small">
                                    <li>请确认MT4账户信息无误后再提交</li>
                                    <li>入金金额需与实际转账金额一致</li>
                                    <li>银行转账请保存好转账凭证</li>
                                    <li>如有疑问请联系在线客服</li>
                                </ul>
                            </div>
                        </div>

                        <div class="d-grid gap-2">
                            <button type="submit" class="btn btn-primary-gradient btn-lg">
                                <i class="cil-check me-2"></i>提交入金申请
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Recent Deposits -->
            <div class="card shadow-sm border-0">
                <div class="card-header bg-transparent border-bottom">
                    <h5 class="mb-0">
                        <i class="cil-list me-2"></i>最近入金记录
                    </h5>
                </div>
                <div class="card-body p-0">
                    <!-- Desktop Table -->
                    <div class="table-responsive d-none d-md-block">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>时间</th>
                                    <th>MT4账号</th>
                                    <th>金额</th>
                                    <th>支付方式</th>
                                    <th>状态</th>
                                </tr>
                            </thead>
                            <tbody id="recentDeposits">
                                <tr>
                                    <td colspan="5" class="text-center py-4 text-body-secondary">
                                        <div class="spinner-border spinner-border-sm me-2"></div>
                                        加载中...
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- Mobile Card Stack -->
                    <div class="d-md-none">
                        <div class="list-group list-group-flush" id="recentDepositsMobile">
                            <div class="list-group-item text-center py-4 text-body-secondary">
                                <div class="spinner-border spinner-border-sm me-2"></div>
                                加载中...
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Right Column - Info & Stats -->
        <div class="col-lg-4">
            <div class="card shadow-sm border-0 mb-4 bg-gradient-primary text-white">
                <div class="card-body">
                    <h6 class="mb-3">
                        <i class="cil-chart-line me-2"></i>入金统计
                    </h6>
                    <div class="mb-3 pb-3 border-bottom border-white border-opacity-25">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <span class="opacity-75">本月入金</span>
                            <h4 class="mb-0" id="monthDeposit">$0.00</h4>
                        </div>
                    </div>
                    <div class="mb-3 pb-3 border-bottom border-white border-opacity-25">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <span class="opacity-75">累计入金</span>
                            <h4 class="mb-0" id="totalDeposit">$0.00</h4>
                        </div>
                    </div>
                    <div>
                        <div class="d-flex justify-content-between align-items-center">
                            <span class="opacity-75">入金次数</span>
                            <h4 class="mb-0" id="depositCount">0</h4>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card shadow-sm border-0 mb-4">
                <div class="card-header bg-transparent border-bottom">
                    <h6 class="mb-0">
                        <i class="cil-money me-2"></i>支付方式说明
                    </h6>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <strong class="text-primary">银行转账</strong>
                        <p class="mb-0 small text-body-secondary">支持国内各大银行，到账时间1-2小时，需上传转账凭证</p>
                    </div>
                    <div class="mb-3">
                        <strong class="text-info">支付宝</strong>
                        <p class="mb-0 small text-body-secondary">扫码支付，即时到账，方便快捷</p>
                    </div>
                    <div class="mb-3">
                        <strong class="text-success">微信支付</strong>
                        <p class="mb-0 small text-body-secondary">扫码支付，即时到账，安全可靠</p>
                    </div>
                    <div>
                        <strong class="text-warning">USDT</strong>
                        <p class="mb-0 small text-body-secondary">加密货币支付，到账时间10-30分钟</p>
                    </div>
                </div>
            </div>

            <div class="card shadow-sm border-0">
                <div class="card-body">
                    <h6 class="mb-3">
                        <i class="cil-headphones me-2"></i>需要帮助？
                    </h6>
                    <p class="small text-body-secondary mb-3">如遇到入金问题，请联系我们的客服团队</p>
                    <div class="d-grid gap-2">
                        <a href="#" class="btn btn-outline-primary btn-sm">
                            <i class="cil-chat-bubble me-2"></i>在线客服
                        </a>
                        <a href="#" class="btn btn-outline-secondary btn-sm">
                            <i class="cil-book me-2"></i>入金教程
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    loadAccounts();
    loadStats();
    loadRecentDeposits();

    document.getElementById('depositForm').addEventListener('submit', function(e) {
        e.preventDefault();
        submitDeposit();
    });
});

function loadAccounts() {
    fetch('{{ route("front_api_account_list") }}', {
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(res => res.json())
    .then(data => {
        if (data.success && data.accounts) {
            const select = document.getElementById('mt4Account');
            data.accounts.forEach(acc => {
                const option = document.createElement('option');
                option.value = acc.id;
                option.textContent = `${acc.mt4_account} - ${acc.account_name}`;
                option.dataset.balance = acc.balance;
                select.appendChild(option);
            });
        }
    })
    .catch(err => {
        console.error('Load accounts error:', err);
    });
}

function loadAccountInfo() {
    const select = document.getElementById('mt4Account');
    const selectedOption = select.options[select.selectedIndex];
    if (selectedOption && selectedOption.dataset.balance) {
        document.getElementById('currentBalance').textContent = formatCurrency(selectedOption.dataset.balance);
    } else {
        document.getElementById('currentBalance').textContent = '$0.00';
    }
}

function loadStats() {
    fetch('{{ route("front_api_deposit_stats") }}', {
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(res => res.json())
    .then(data => {
        if (data.success && data.stats) {
            document.getElementById('monthDeposit').textContent = formatCurrency(data.stats.month_deposit);
            document.getElementById('totalDeposit').textContent = formatCurrency(data.stats.total_deposit);
            document.getElementById('depositCount').textContent = data.stats.deposit_count || 0;
        }
    })
    .catch(err => {
        console.error('Load stats error:', err);
    });
}

function loadRecentDeposits() {
    fetch('{{ route("front_api_deposit_recent") }}', {
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(res => res.json())
    .then(data => {
        if (data.success && data.deposits) {
            renderDeposits(data.deposits);
        }
    })
    .catch(err => {
        console.error('Load deposits error:', err);
    });
}

function renderDeposits(deposits) {
    const tbody = document.getElementById('recentDeposits');
    const mobileList = document.getElementById('recentDepositsMobile');

    if (deposits.length === 0) {
        tbody.innerHTML = `
            <tr>
                <td colspan="5" class="text-center py-4 text-body-secondary">暂无入金记录</td>
            </tr>
        `;
        mobileList.innerHTML = `
            <div class="list-group-item text-center py-4 text-body-secondary">暂无入金记录</div>
        `;
        return;
    }

    // Desktop table rows
    const htmlDesktop = deposits.map(d => `
        <tr>
            <td>${d.created_at || '-'}</td>
            <td>${d.mt4_account || '-'}</td>
            <td class="fw-semibold text-success">${formatCurrency(d.amount)}</td>
            <td>${getPaymentMethodText(d.payment_method)}</td>
            <td>${getStatusBadge(d.status)}</td>
        </tr>
    `).join('');

    // Mobile cards
    const htmlMobile = deposits.map(d => `
        <div class="list-group-item">
            <div class="d-flex justify-content-between align-items-start mb-2">
                <span class="fw-semibold text-success">${formatCurrency(d.amount)}</span>
                ${getStatusBadge(d.status)}
            </div>
            <div class="row g-2 small text-muted">
                <div class="col-6">
                    <i class="fas fa-clock me-1"></i>${d.created_at || '-'}
                </div>
                <div class="col-6">
                    <i class="fas fa-wallet me-1"></i>${getPaymentMethodText(d.payment_method)}
                </div>
                <div class="col-12">
                    <i class="fas fa-user me-1"></i>MT4: ${d.mt4_account || '-'}
                </div>
            </div>
        </div>
    `).join('');

    tbody.innerHTML = htmlDesktop;
    mobileList.innerHTML = htmlMobile;
}

function submitDeposit() {
    const accountId = document.getElementById('mt4Account').value;
    const amount = document.getElementById('depositAmount').value;
    const paymentMethod = document.querySelector('input[name="paymentMethod"]:checked').value;
    const note = document.getElementById('depositNote').value.trim();

    if (!accountId) {
        alert('请选择MT4账户');
        return;
    }

    if (!amount || amount < 100) {
        alert('入金金额不能少于$100');
        return;
    }

    const data = {
        account_id: accountId,
        amount: amount,
        payment_method: paymentMethod,
        note: note
    };

    fetch('{{ route("front_api_deposit_submit") }}', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: JSON.stringify(data)
    })
    .then(res => res.json())
    .then(data => {
        if (data.success || data.code === 200) {
            alert('入金申请提交成功，请按提示完成支付');
            if (data.payment_url) {
                window.location.href = data.payment_url;
            } else {
                document.getElementById('depositForm').reset();
                loadRecentDeposits();
                loadStats();
            }
        } else {
            alert(data.message || '提交失败');
        }
    })
    .catch(err => {
        console.error('Submit deposit error:', err);
        alert('网络错误，请稍后重试');
    });
}

function getPaymentMethodText(method) {
    const methods = {
        'bank_transfer': '银行转账',
        'alipay': '支付宝',
        'wechat': '微信支付',
        'usdt': 'USDT'
    };
    return methods[method] || method;
}

function getStatusBadge(status) {
    const badges = {
        'pending': '<span class="badge bg-warning text-dark">待支付</span>',
        'processing': '<span class="badge bg-info">处理中</span>',
        'completed': '<span class="badge bg-success">已完成</span>',
        'failed': '<span class="badge bg-danger">失败</span>'
    };
    return badges[status] || '<span class="badge bg-secondary">未知</span>';
}

function formatCurrency(value) {
    if (!value) return '$0.00';
    return '$' + parseFloat(value).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}
</script>

<style>
.bg-gradient-primary {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
}

.btn-primary-gradient {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    border: none;
    color: white;
    font-weight: 600;
}

.btn-primary-gradient:hover {
    opacity: 0.9;
    color: white;
}

.payment-option {
    border: 2px solid #e9ecef;
    border-radius: 8px;
    padding: 1rem;
    transition: all 0.2s ease;
}

.payment-option:hover {
    border-color: #667eea;
    background-color: rgba(102, 126, 234, 0.05);
}

.payment-option .form-check-input:checked ~ .form-check-label {
    color: #667eea;
}

.payment-option .form-check-input:checked ~ .form-check-label .payment-option {
    border-color: #667eea;
    background-color: rgba(102, 126, 234, 0.1);
}
</style>
@endsection
