@extends('front-coreui-v2.layouts.app')

@section('title', '在线出金')

@section('content')
<div class="container-lg px-4">
    <!-- Page Header -->
    <div class="row mb-4">
        <div class="col-12">
            <h2 class="mb-2">在线出金</h2>
            <p class="text-body-secondary mb-0">提取您的交易盈利</p>
        </div>
    </div>

    <div class="row">
        <!-- Left Column - Withdraw Form -->
        <div class="col-lg-8">
            <div class="card shadow-sm border-0 mb-4">
                <div class="card-header bg-gradient-primary text-white border-0">
                    <h5 class="mb-0">
                        <i class="cil-arrow-thick-from-bottom me-2"></i>出金申请
                    </h5>
                </div>
                <div class="card-body p-4">
                    <form id="withdrawForm">
                        <div class="mb-4">
                            <label class="form-label fw-semibold">选择MT4账户 <span class="text-danger">*</span></label>
                            <select id="mt4Account" class="form-select form-select-lg" required onchange="loadAccountInfo()">
                                <option value="">请选择出金账户</option>
                            </select>
                            <div class="row g-2 mt-2">
                                <div class="col-6">
                                    <small class="text-body-secondary">可用余额: <span id="currentBalance" class="fw-bold text-success">$0.00</span></small>
                                </div>
                                <div class="col-6 text-end">
                                    <small class="text-body-secondary">净值: <span id="currentEquity" class="fw-bold">$0.00</span></small>
                                </div>
                            </div>
                        </div>

                        <div class="mb-4">
                            <label class="form-label fw-semibold">出金金额 <span class="text-danger">*</span></label>
                            <div class="input-group input-group-lg">
                                <span class="input-group-text">$</span>
                                <input type="number" id="withdrawAmount" class="form-control" placeholder="请输入出金金额" min="50" step="0.01" required>
                                <button class="btn btn-outline-secondary" type="button" onclick="setMaxAmount()">全部</button>
                            </div>
                            <div class="form-text">最低出金金额: $50.00 | 手续费: <span id="feeInfo">根据金额计算</span></div>
                        </div>

                        <div class="mb-4">
                            <label class="form-label fw-semibold">收款方式 <span class="text-danger">*</span></label>
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <div class="form-check payment-option">
                                        <input class="form-check-input" type="radio" name="withdrawMethod" id="bankCard" value="bank_card" checked>
                                        <label class="form-check-label w-100" for="bankCard">
                                            <div class="d-flex align-items-center">
                                                <i class="cil-credit-card me-3 text-primary" style="font-size: 2rem;"></i>
                                                <div>
                                                    <strong>银行卡</strong>
                                                    <p class="mb-0 small text-body-secondary">1-3个工作日到账</p>
                                                </div>
                                            </div>
                                        </label>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-check payment-option">
                                        <input class="form-check-input" type="radio" name="withdrawMethod" id="alipay" value="alipay">
                                        <label class="form-check-label w-100" for="alipay">
                                            <div class="d-flex align-items-center">
                                                <i class="cil-wallet me-3 text-info" style="font-size: 2rem;"></i>
                                                <div>
                                                    <strong>支付宝</strong>
                                                    <p class="mb-0 small text-body-secondary">24小时内到账</p>
                                                </div>
                                            </div>
                                        </label>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-check payment-option">
                                        <input class="form-check-input" type="radio" name="withdrawMethod" id="wechat" value="wechat">
                                        <label class="form-check-label w-100" for="wechat">
                                            <div class="d-flex align-items-center">
                                                <i class="cil-chat-bubble me-3 text-success" style="font-size: 2rem;"></i>
                                                <div>
                                                    <strong>微信</strong>
                                                    <p class="mb-0 small text-body-secondary">24小时内到账</p>
                                                </div>
                                            </div>
                                        </label>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-check payment-option">
                                        <input class="form-check-input" type="radio" name="withdrawMethod" id="usdt" value="usdt">
                                        <label class="form-check-label w-100" for="usdt">
                                            <div class="d-flex align-items-center">
                                                <i class="cil-dollar me-3 text-warning" style="font-size: 2rem;"></i>
                                                <div>
                                                    <strong>USDT</strong>
                                                    <p class="mb-0 small text-body-secondary">1-2小时到账</p>
                                                </div>
                                            </div>
                                        </label>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="mb-4" id="bankCardInfo">
                            <label class="form-label fw-semibold">银行卡信息</label>
                            <select id="savedBankCards" class="form-select mb-2">
                                <option value="">选择已保存的银行卡</option>
                            </select>
                            <div class="row g-2">
                                <div class="col-md-6">
                                    <input type="text" id="bankName" class="form-control" placeholder="开户银行">
                                </div>
                                <div class="col-md-6">
                                    <input type="text" id="cardNumber" class="form-control" placeholder="银行卡号">
                                </div>
                                <div class="col-md-6">
                                    <input type="text" id="cardHolder" class="form-control" placeholder="持卡人姓名">
                                </div>
                                <div class="col-md-6">
                                    <input type="text" id="bankBranch" class="form-control" placeholder="开户支行（选填）">
                                </div>
                            </div>
                        </div>

                        <div class="mb-4">
                            <label class="form-label fw-semibold">备注说明</label>
                            <textarea id="withdrawNote" class="form-control" rows="2" placeholder="请输入备注信息（选填）"></textarea>
                        </div>

                        <div class="mb-4">
                            <div class="alert alert-warning border-0">
                                <i class="cil-warning me-2"></i>
                                <strong>重要提示：</strong>
                                <ul class="mb-0 mt-2 small">
                                    <li>出金账户需与入金账户一致，否则可能延迟到账</li>
                                    <li>请确保收款信息准确无误，错误信息将导致出金失败</li>
                                    <li>单日出金限额为$50,000，如需大额出金请联系客服</li>
                                    <li>出金审核时间为1-3个工作日</li>
                                </ul>
                            </div>
                        </div>

                        <div class="d-grid gap-2">
                            <button type="submit" class="btn btn-primary-gradient btn-lg">
                                <i class="cil-check me-2"></i>提交出金申请
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Recent Withdrawals -->
            <div class="card shadow-sm border-0">
                <div class="card-header bg-transparent border-bottom">
                    <h5 class="mb-0">
                        <i class="cil-list me-2"></i>最近出金记录
                    </h5>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>申请时间</th>
                                    <th>MT4账号</th>
                                    <th>金额</th>
                                    <th>收款方式</th>
                                    <th>状态</th>
                                    <th>操作</th>
                                </tr>
                            </thead>
                            <tbody id="recentWithdrawals">
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

        <!-- Right Column - Info & Stats -->
        <div class="col-lg-4">
            <div class="card shadow-sm border-0 mb-4 bg-gradient-primary text-white">
                <div class="card-body">
                    <h6 class="mb-3">
                        <i class="cil-chart-line me-2"></i>出金统计
                    </h6>
                    <div class="mb-3 pb-3 border-bottom border-white border-opacity-25">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <span class="opacity-75">本月出金</span>
                            <h4 class="mb-0" id="monthWithdraw">$0.00</h4>
                        </div>
                    </div>
                    <div class="mb-3 pb-3 border-bottom border-white border-opacity-25">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <span class="opacity-75">累计出金</span>
                            <h4 class="mb-0" id="totalWithdraw">$0.00</h4>
                        </div>
                    </div>
                    <div>
                        <div class="d-flex justify-content-between align-items-center">
                            <span class="opacity-75">出金次数</span>
                            <h4 class="mb-0" id="withdrawCount">0</h4>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card shadow-sm border-0 mb-4">
                <div class="card-header bg-transparent border-bottom">
                    <h6 class="mb-0">
                        <i class="cil-info me-2"></i>出金规则
                    </h6>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <strong>最低金额</strong>
                        <p class="mb-0 small text-body-secondary">单笔最低$50，未到最低金额无法提交</p>
                    </div>
                    <div class="mb-3">
                        <strong>到账时间</strong>
                        <p class="mb-0 small text-body-secondary">审核通过后1-3个工作日到账</p>
                    </div>
                    <div class="mb-3">
                        <strong>手续费用</strong>
                        <p class="mb-0 small text-body-secondary">$500以下收取2%，$500以上免手续费</p>
                    </div>
                    <div>
                        <strong>出金限制</strong>
                        <p class="mb-0 small text-body-secondary">单日限额$50,000，月限额$500,000</p>
                    </div>
                </div>
            </div>

            <div class="card shadow-sm border-0">
                <div class="card-body">
                    <h6 class="mb-3">
                        <i class="cil-headphones me-2"></i>需要帮助？
                    </h6>
                    <p class="small text-body-secondary mb-3">出金遇到问题？联系我们的客服团队</p>
                    <div class="d-grid gap-2">
                        <a href="#" class="btn btn-outline-primary btn-sm">
                            <i class="cil-chat-bubble me-2"></i>在线客服
                        </a>
                        <a href="#" class="btn btn-outline-secondary btn-sm">
                            <i class="cil-book me-2"></i>出金教程
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
    loadRecentWithdrawals();

    document.getElementById('withdrawForm').addEventListener('submit', function(e) {
        e.preventDefault();
        submitWithdraw();
    });

    document.querySelectorAll('input[name="withdrawMethod"]').forEach(radio => {
        radio.addEventListener('change', updatePaymentFields);
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
                option.dataset.equity = acc.equity;
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
        document.getElementById('currentEquity').textContent = formatCurrency(selectedOption.dataset.equity);
    } else {
        document.getElementById('currentBalance').textContent = '$0.00';
        document.getElementById('currentEquity').textContent = '$0.00';
    }
}

function setMaxAmount() {
    const select = document.getElementById('mt4Account');
    const selectedOption = select.options[select.selectedIndex];
    if (selectedOption && selectedOption.dataset.balance) {
        document.getElementById('withdrawAmount').value = selectedOption.dataset.balance;
    }
}

function updatePaymentFields() {
    const method = document.querySelector('input[name="withdrawMethod"]:checked').value;
    const bankCardInfo = document.getElementById('bankCardInfo');

    if (method === 'bank_card') {
        bankCardInfo.style.display = 'block';
    } else {
        bankCardInfo.style.display = 'none';
    }
}

function loadStats() {
    fetch('{{ route("front_api_withdraw_stats") }}', {
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(res => res.json())
    .then(data => {
        if (data.success && data.stats) {
            document.getElementById('monthWithdraw').textContent = formatCurrency(data.stats.month_withdraw);
            document.getElementById('totalWithdraw').textContent = formatCurrency(data.stats.total_withdraw);
            document.getElementById('withdrawCount').textContent = data.stats.withdraw_count || 0;
        }
    })
    .catch(err => {
        console.error('Load stats error:', err);
    });
}

function loadRecentWithdrawals() {
    fetch('{{ route("front_api_withdraw_recent") }}', {
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(res => res.json())
    .then(data => {
        if (data.success && data.withdrawals) {
            renderWithdrawals(data.withdrawals);
        }
    })
    .catch(err => {
        console.error('Load withdrawals error:', err);
    });
}

function renderWithdrawals(withdrawals) {
    const tbody = document.getElementById('recentWithdrawals');

    if (withdrawals.length === 0) {
        tbody.innerHTML = `
            <tr>
                <td colspan="6" class="text-center py-4 text-body-secondary">暂无出金记录</td>
            </tr>
        `;
        return;
    }

    const html = withdrawals.map(w => `
        <tr>
            <td>${w.created_at || '-'}</td>
            <td>${w.mt4_account || '-'}</td>
            <td class="fw-semibold text-danger">${formatCurrency(w.amount)}</td>
            <td>${getWithdrawMethodText(w.withdraw_method)}</td>
            <td>${getStatusBadge(w.status)}</td>
            <td>
                ${w.status === 'pending' ? `<button onclick="cancelWithdraw('${w.id}')" class="btn btn-outline-danger btn-sm"><i class="cil-x"></i></button>` : '-'}
            </td>
        </tr>
    `).join('');

    tbody.innerHTML = html;
}

function submitWithdraw() {
    const accountId = document.getElementById('mt4Account').value;
    const amount = document.getElementById('withdrawAmount').value;
    const withdrawMethod = document.querySelector('input[name="withdrawMethod"]:checked').value;
    const note = document.getElementById('withdrawNote').value.trim();

    if (!accountId) {
        alert('请选择MT4账户');
        return;
    }

    if (!amount || amount < 50) {
        alert('出金金额不能少于$50');
        return;
    }

    const data = {
        account_id: accountId,
        amount: amount,
        withdraw_method: withdrawMethod,
        note: note
    };

    if (withdrawMethod === 'bank_card') {
        data.bank_name = document.getElementById('bankName').value.trim();
        data.card_number = document.getElementById('cardNumber').value.trim();
        data.card_holder = document.getElementById('cardHolder').value.trim();
        data.bank_branch = document.getElementById('bankBranch').value.trim();

        if (!data.bank_name || !data.card_number || !data.card_holder) {
            alert('请填写完整的银行卡信息');
            return;
        }
    }

    fetch('{{ route("front_api_withdraw_submit") }}', {
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
            alert('出金申请提交成功，请等待审核');
            document.getElementById('withdrawForm').reset();
            loadRecentWithdrawals();
            loadStats();
        } else {
            alert(data.message || '提交失败');
        }
    })
    .catch(err => {
        console.error('Submit withdraw error:', err);
        alert('网络错误，请稍后重试');
    });
}

function cancelWithdraw(id) {
    if (!confirm('确定要取消该出金申请吗？')) return;

    fetch('{{ route("front_api_withdraw_cancel") }}', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: JSON.stringify({ id: id })
    })
    .then(res => res.json())
    .then(data => {
        if (data.success || data.code === 200) {
            alert('取消成功');
            loadRecentWithdrawals();
        } else {
            alert(data.message || '取消失败');
        }
    })
    .catch(err => {
        console.error('Cancel withdraw error:', err);
        alert('网络错误，请稍后重试');
    });
}

function getWithdrawMethodText(method) {
    const methods = {
        'bank_card': '银行卡',
        'alipay': '支付宝',
        'wechat': '微信',
        'usdt': 'USDT'
    };
    return methods[method] || method;
}

function getStatusBadge(status) {
    const badges = {
        'pending': '<span class="badge bg-warning text-dark">待审核</span>',
        'approved': '<span class="badge bg-info">已批准</span>',
        'processing': '<span class="badge bg-primary">处理中</span>',
        'completed': '<span class="badge bg-success">已完成</span>',
        'rejected': '<span class="badge bg-danger">已拒绝</span>',
        'cancelled': '<span class="badge bg-secondary">已取消</span>'
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
</style>
@endsection
