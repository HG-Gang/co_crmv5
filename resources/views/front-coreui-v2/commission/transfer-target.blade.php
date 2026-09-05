@extends('front-coreui-v2.layouts.app')

@section('title', '选择转账目标')

@section('content')
<div class="container-lg px-4">
    <!-- Page Header -->
    <div class="row mb-4">
        <div class="col-12">
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="{{ route('front_coreui_v2_page_commission_transfer') }}">佣金转账</a></li>
                    <li class="breadcrumb-item active">选择转账目标</li>
                </ol>
            </nav>
            <h2 class="mb-2">选择转账目标</h2>
            <p class="text-body-secondary mb-0">选择要转账的代理或MT4账户</p>
        </div>
    </div>

    <div class="row">
        <!-- Left Column - Agents -->
        <div class="col-lg-6">
            <div class="card shadow-sm border-0 mb-4">
                <div class="card-header bg-transparent border-bottom">
                    <div class="d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">
                            <i class="cil-people me-2"></i>可转账代理
                        </h5>
                        <div class="input-group" style="width: auto;">
                            <input type="text" class="form-control form-control-sm" id="searchAgent" placeholder="搜索代理">
                            <button class="btn btn-sm btn-outline-secondary" onclick="searchAgents()">
                                <i class="cil-magnifying-glass"></i>
                            </button>
                        </div>
                    </div>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>代理信息</th>
                                    <th>等级</th>
                                    <th>关系</th>
                                    <th>操作</th>
                                </tr>
                            </thead>
                            <tbody id="agentsTable">
                                <tr>
                                    <td colspan="4" class="text-center py-5 text-body-secondary">
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

        <!-- Right Column - MT4 Accounts -->
        <div class="col-lg-6">
            <div class="card shadow-sm border-0 mb-4">
                <div class="card-header bg-transparent border-bottom">
                    <h5 class="mb-0">
                        <i class="cil-bank me-2"></i>我的MT4账户
                    </h5>
                </div>
                <div class="card-body p-0">
                    <div class="list-group list-group-flush" id="mt4AccountsList">
                        <div class="list-group-item text-center py-5 text-body-secondary">
                            <div class="spinner-border spinner-border-sm me-2"></div>
                            加载中...
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Transfer Form Card -->
    <div class="card shadow-sm border-0" id="transferFormCard" style="display: none;">
        <div class="card-header bg-gradient-primary text-white">
            <h5 class="mb-0">
                <i class="cil-transfer me-2"></i>确认转账信息
            </h5>
        </div>
        <div class="card-body">
            <div class="row g-4">
                <div class="col-md-6">
                    <div class="border-start border-primary border-4 ps-3">
                        <label class="text-body-secondary small mb-1">转账目标</label>
                        <h5 class="mb-0" id="targetName">-</h5>
                        <p class="text-body-secondary small mb-0" id="targetType">-</p>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="border-start border-success border-4 ps-3">
                        <label class="text-body-secondary small mb-1">可用余额</label>
                        <h5 class="mb-0 text-success" id="availableBalance">$0.00</h5>
                    </div>
                </div>
                <div class="col-md-6">
                    <label class="form-label">转账金额 <span class="text-danger">*</span></label>
                    <div class="input-group">
                        <span class="input-group-text">$</span>
                        <input type="number" class="form-control" id="transferAmount" step="0.01" min="10" placeholder="最少10.00" onchange="calculateFee()">
                    </div>
                </div>
                <div class="col-md-6">
                    <label class="form-label">手续费</label>
                    <div class="input-group">
                        <span class="input-group-text">$</span>
                        <input type="text" class="form-control" id="transferFee" readonly value="0.00">
                    </div>
                    <div class="form-text">费率: <strong id="feeRate">0%</strong></div>
                </div>
                <div class="col-12">
                    <div class="alert alert-info mb-0">
                        <div class="d-flex justify-content-between align-items-center">
                            <span>实际到账金额:</span>
                            <h4 class="mb-0 text-info" id="actualAmount">$0.00</h4>
                        </div>
                    </div>
                </div>
                <div class="col-12">
                    <label class="form-label">备注说明</label>
                    <textarea class="form-control" id="transferRemark" rows="2" placeholder="选填"></textarea>
                </div>
                <div class="col-12">
                    <button class="btn btn-primary" onclick="submitTransfer()">
                        <i class="cil-check me-2"></i>确认转账
                    </button>
                    <button class="btn btn-secondary" onclick="cancelTransfer()">
                        <i class="cil-x me-2"></i>取消
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
let selectedTarget = null;
let selectedType = null;
let commissionBalance = 0;

document.addEventListener('DOMContentLoaded', function() {
    loadBalance();
    loadAgents();
    loadMt4Accounts();
});

function loadBalance() {
    fetch('{{ route("front_api_commission_balance") }}', {
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(res => res.json())
    .then(data => {
        if (data.success && data.balance !== undefined) {
            commissionBalance = parseFloat(data.balance);
            document.getElementById('availableBalance').textContent = formatCurrency(commissionBalance);
        }
    })
    .catch(err => {
        console.error('Load balance error:', err);
    });
}

function loadAgents() {
    fetch('{{ route("front_api_commission_transfer_targets") }}', {
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(res => res.json())
    .then(data => {
        if (data.success && data.agents) {
            renderAgents(data.agents);
        }
    })
    .catch(err => {
        console.error('Load agents error:', err);
    });
}

function renderAgents(agents) {
    const tbody = document.getElementById('agentsTable');

    if (agents.length === 0) {
        tbody.innerHTML = `
            <tr>
                <td colspan="4" class="text-center py-5 text-body-secondary">
                    <i class="cil-inbox" style="font-size: 3rem; opacity: 0.3;"></i>
                    <p class="mt-3">暂无可转账代理</p>
                </td>
            </tr>
        `;
        return;
    }

    const html = agents.map(a => `
        <tr>
            <td>
                <div class="d-flex align-items-center">
                    <div class="avatar avatar-sm bg-primary bg-opacity-10 text-primary me-3">
                        <i class="cil-user"></i>
                    </div>
                    <div>
                        <div class="fw-semibold">${a.name || '-'}</div>
                        <small class="text-body-secondary">${a.email || ''}</small>
                    </div>
                </div>
            </td>
            <td><span class="badge ${getLevelBadge(a.level)}">${getLevelText(a.level)}</span></td>
            <td><span class="badge bg-info">${a.relationship || '下级'}</span></td>
            <td>
                <button class="btn btn-sm btn-primary" onclick="selectAgent(${a.id}, '${a.name}', '${a.email}')">
                    <i class="cil-cursor me-1"></i>选择
                </button>
            </td>
        </tr>
    `).join('');

    tbody.innerHTML = html;
}

function loadMt4Accounts() {
    fetch('{{ route("front_api_my_mt4_accounts") }}', {
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(res => res.json())
    .then(data => {
        if (data.success && data.accounts) {
            renderMt4Accounts(data.accounts);
        }
    })
    .catch(err => {
        console.error('Load MT4 accounts error:', err);
    });
}

function renderMt4Accounts(accounts) {
    const container = document.getElementById('mt4AccountsList');

    if (accounts.length === 0) {
        container.innerHTML = `
            <div class="list-group-item text-center py-5 text-body-secondary">
                <i class="cil-inbox" style="font-size: 3rem; opacity: 0.3;"></i>
                <p class="mt-3">暂无MT4账户</p>
            </div>
        `;
        return;
    }

    const html = accounts.map(acc => `
        <div class="list-group-item list-group-item-action">
            <div class="d-flex justify-content-between align-items-center">
                <div class="flex-grow-1">
                    <div class="d-flex align-items-center mb-2">
                        <div class="avatar avatar-sm bg-success bg-opacity-10 text-success me-3">
                            <i class="cil-bank"></i>
                        </div>
                        <div>
                            <h6 class="mb-0">${acc.mt4_account || '-'}</h6>
                            <small class="text-body-secondary">${acc.group || '-'}</small>
                        </div>
                    </div>
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="text-body-secondary small">余额</span>
                        <strong class="text-success">${formatCurrency(acc.balance)}</strong>
                    </div>
                </div>
                <div class="ms-3">
                    <button class="btn btn-sm btn-primary" onclick="selectMt4Account(${acc.id}, '${acc.mt4_account}')">
                        <i class="cil-cursor me-1"></i>选择
                    </button>
                </div>
            </div>
        </div>
    `).join('');

    container.innerHTML = html;
}

function searchAgents() {
    const keyword = document.getElementById('searchAgent').value;
    fetch(`{{ route("front_api_commission_transfer_targets") }}?keyword=${encodeURIComponent(keyword)}`, {
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(res => res.json())
    .then(data => {
        if (data.success && data.agents) {
            renderAgents(data.agents);
        }
    })
    .catch(err => {
        console.error('Search agents error:', err);
    });
}

function selectAgent(id, name, email) {
    selectedTarget = id;
    selectedType = 'agent';

    document.getElementById('targetName').textContent = name;
    document.getElementById('targetType').textContent = `代理 (${email})`;
    document.getElementById('feeRate').textContent = '2%';
    document.getElementById('transferFormCard').style.display = 'block';

    document.getElementById('transferFormCard').scrollIntoView({ behavior: 'smooth' });
}

function selectMt4Account(id, account) {
    selectedTarget = id;
    selectedType = 'mt4';

    document.getElementById('targetName').textContent = account;
    document.getElementById('targetType').textContent = 'MT4账户';
    document.getElementById('feeRate').textContent = '0%';
    document.getElementById('transferFormCard').style.display = 'block';

    document.getElementById('transferFormCard').scrollIntoView({ behavior: 'smooth' });
}

function calculateFee() {
    const amount = parseFloat(document.getElementById('transferAmount').value) || 0;
    let feeRate = 0;

    if (selectedType === 'agent') {
        feeRate = 0.02;
    }

    const fee = amount * feeRate;
    const actualAmount = amount - fee;

    document.getElementById('transferFee').value = fee.toFixed(2);
    document.getElementById('actualAmount').textContent = formatCurrency(actualAmount);
}

function submitTransfer() {
    const amount = parseFloat(document.getElementById('transferAmount').value);
    const remark = document.getElementById('transferRemark').value;

    if (!selectedTarget || !selectedType) {
        alert('请先选择转账目标');
        return;
    }

    if (!amount || amount < 10) {
        alert('转账金额不能少于$10.00');
        return;
    }

    if (amount > commissionBalance) {
        alert('转账金额超过可用余额');
        return;
    }

    const type = selectedType === 'agent' ? 'to_agent' : 'to_mt4';

    fetch('{{ route("front_api_commission_transfer_submit") }}', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: JSON.stringify({
            type: type,
            target_id: selectedTarget,
            amount: amount,
            remark: remark
        })
    })
    .then(res => res.json())
    .then(data => {
        if (data.success || data.code === 200) {
            alert('转账申请提交成功');
            window.location.href = '{{ route("front_coreui_v2_page_commission_transfer") }}';
        } else {
            alert(data.message || '提交失败');
        }
    })
    .catch(err => {
        console.error('Submit error:', err);
        alert('网络错误，请稍后重试');
    });
}

function cancelTransfer() {
    document.getElementById('transferFormCard').style.display = 'none';
    selectedTarget = null;
    selectedType = null;
    document.getElementById('transferAmount').value = '';
    document.getElementById('transferFee').value = '0.00';
    document.getElementById('actualAmount').textContent = '$0.00';
    document.getElementById('transferRemark').value = '';
}

function getLevelBadge(level) {
    const badges = {
        1: 'bg-success',
        2: 'bg-info',
        3: 'bg-warning'
    };
    return badges[level] || 'bg-secondary';
}

function getLevelText(level) {
    const texts = {
        1: '一级代理',
        2: '二级代理',
        3: '三级代理'
    };
    return texts[level] || '代理';
}

function formatCurrency(value) {
    if (!value && value !== 0) return '-';
    return '$' + parseFloat(value).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigals: 2 });
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

.avatar-sm {
    width: 36px;
    height: 36px;
    font-size: 1.1rem;
}
</style>
@endsection
