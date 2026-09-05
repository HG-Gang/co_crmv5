@extends('front-coreui-v2.layouts.app')

@section('title', '佣金转账')

@section('content')
<div class="container-lg px-4">
    <!-- Page Header -->
    <div class="row mb-4">
        <div class="col-12">
            <h2 class="mb-2">佣金转账</h2>
            <p class="text-body-secondary mb-0">将佣金余额转账到MT4账户或其他代理</p>
        </div>
    </div>

    <div class="row">
        <!-- Left Column -->
        <div class="col-lg-8">
            <!-- Transfer Form -->
            <div class="card shadow-sm border-0 mb-4">
                <div class="card-header bg-gradient-primary text-white">
                    <h5 class="mb-0">
                        <i class="cil-transfer me-2"></i>转账申请
                    </h5>
                </div>
                <div class="card-body">
                    <form id="transferForm">
                        <div class="row g-3">
                            <div class="col-12">
                                <label class="form-label">转账类型 <span class="text-danger">*</span></label>
                                <select class="form-select" id="transferType" onchange="handleTypeChange()">
                                    <option value="">请选择转账类型</option>
                                    <option value="to_mt4">转入MT4账户</option>
                                    <option value="to_agent">转给其他代理</option>
                                </select>
                            </div>
                            <div class="col-md-6" id="mt4AccountField" style="display: none;">
                                <label class="form-label">MT4账号 <span class="text-danger">*</span></label>
                                <select class="form-select" id="mt4Account">
                                    <option value="">请选择MT4账号</option>
                                </select>
                            </div>
                            <div class="col-md-6" id="targetAgentField" style="display: none;">
                                <label class="form-label">目标代理 <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <input type="text" class="form-control" id="targetAgent" readonly placeholder="点击选择代理">
                                    <button class="btn btn-outline-secondary" type="button" onclick="selectAgent()">
                                        <i class="cil-magnifying-glass"></i>
                                    </button>
                                </div>
                                <input type="hidden" id="targetAgentId">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">转账金额 <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <span class="input-group-text">$</span>
                                    <input type="number" class="form-control" id="amount" step="0.01" min="0" placeholder="0.00" onchange="calculateFee()">
                                </div>
                                <div class="form-text">可用余额: <strong class="text-success" id="availableBalance">$0.00</strong></div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">手续费</label>
                                <div class="input-group">
                                    <span class="input-group-text">$</span>
                                    <input type="text" class="form-control" id="fee" readonly value="0.00">
                                </div>
                                <div class="form-text">手续费率: <strong id="feeRate">0%</strong></div>
                            </div>
                            <div class="col-12">
                                <div class="alert alert-info mb-0">
                                    <div class="d-flex align-items-start">
                                        <i class="cil-info me-2 mt-1"></i>
                                        <div>
                                            <strong>实际到账:</strong>
                                            <h4 class="mb-0 text-info" id="actualAmount">$0.00</h4>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-12">
                                <label class="form-label">备注说明</label>
                                <textarea class="form-control" id="remark" rows="3" placeholder="选填"></textarea>
                            </div>
                            <div class="col-12">
                                <button type="button" class="btn btn-primary" onclick="submitTransfer()">
                                    <i class="cil-check me-2"></i>提交申请
                                </button>
                                <button type="button" class="btn btn-secondary" onclick="resetForm()">
                                    <i class="cil-reload me-2"></i>重置
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Transfer History -->
            <div class="card shadow-sm border-0">
                <div class="card-header bg-transparent border-bottom">
                    <div class="d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">
                            <i class="cil-list me-2"></i>转账记录
                        </h5>
                        <select id="filterStatus" class="form-select form-select-sm" style="width: auto;" onchange="loadTransfers()">
                            <option value="">全部状态</option>
                            <option value="pending">待处理</option>
                            <option value="approved">已批准</option>
                            <option value="rejected">已拒绝</option>
                        </select>
                    </div>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>申请编号</th>
                                    <th>转账类型</th>
                                    <th>目标账户</th>
                                    <th>转账金额</th>
                                    <th>手续费</th>
                                    <th>状态</th>
                                    <th>申请时间</th>
                                </tr>
                            </thead>
                            <tbody id="transfersTable">
                                <tr>
                                    <td colspan="7" class="text-center py-5 text-body-secondary">
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
                            共 <span id="totalRecords">0</span> 条转账记录
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

        <!-- Right Column -->
        <div class="col-lg-4">
            <!-- Balance Card -->
            <div class="card shadow-sm border-0 mb-4 text-white bg-gradient-success">
                <div class="card-body text-center py-4">
                    <p class="mb-2 opacity-75">佣金余额</p>
                    <h2 class="mb-0 fw-bold" id="commissionBalance">$0.00</h2>
                </div>
            </div>

            <!-- Quick Stats -->
            <div class="card shadow-sm border-0 mb-4">
                <div class="card-header bg-transparent border-bottom">
                    <h6 class="mb-0">
                        <i class="cil-chart-pie me-2"></i>转账统计
                    </h6>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <div class="d-flex justify-content-between mb-2">
                            <span class="text-body-secondary small">今日转账</span>
                            <strong id="todayTransfer">$0.00</strong>
                        </div>
                    </div>
                    <div class="mb-3">
                        <div class="d-flex justify-content-between mb-2">
                            <span class="text-body-secondary small">本月转账</span>
                            <strong id="monthTransfer">$0.00</strong>
                        </div>
                    </div>
                    <div>
                        <div class="d-flex justify-content-between mb-2">
                            <span class="text-body-secondary small">累计转账</span>
                            <strong id="totalTransfer">$0.00</strong>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Transfer Rules -->
            <div class="card shadow-sm border-0">
                <div class="card-header bg-transparent border-bottom">
                    <h6 class="mb-0">
                        <i class="cil-info me-2"></i>转账规则
                    </h6>
                </div>
                <div class="card-body">
                    <ul class="list-unstyled mb-0">
                        <li class="mb-2">
                            <i class="cil-check-circle text-success me-2"></i>
                            <small>最小转账金额: $10.00</small>
                        </li>
                        <li class="mb-2">
                            <i class="cil-check-circle text-success me-2"></i>
                            <small>转入MT4手续费: 0%</small>
                        </li>
                        <li class="mb-2">
                            <i class="cil-check-circle text-success me-2"></i>
                            <small>转给代理手续费: 2%</small>
                        </li>
                        <li class="mb-2">
                            <i class="cil-check-circle text-success me-2"></i>
                            <small>每日最多转账5次</small>
                        </li>
                        <li>
                            <i class="cil-check-circle text-success me-2"></i>
                            <small>处理时间: 1-3个工作日</small>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Select Agent Modal -->
<div class="modal fade" id="selectAgentModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="cil-magnifying-glass me-2"></i>选择代理
                </h5>
                <button type="button" class="btn-close" data-coreui-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <input type="text" class="form-control" id="searchAgent" placeholder="搜索代理姓名或账号">
                </div>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>代理信息</th>
                                <th>等级</th>
                                <th>状态</th>
                                <th>操作</th>
                            </tr>
                        </thead>
                        <tbody id="agentsTable">
                            <tr>
                                <td colspan="4" class="text-center py-3 text-body-secondary">
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
</div>

<script>
let currentPage = 1;

document.addEventListener('DOMContentLoaded', function() {
    loadBalance();
    loadStats();
    loadMt4Accounts();
    loadTransfers();
});

function loadBalance() {
    fetch('{{ route("front_api_commission_balance") }}', {
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(res => res.json())
    .then(data => {
        if (data.success && data.balance !== undefined) {
            document.getElementById('commissionBalance').textContent = formatCurrency(data.balance);
            document.getElementById('availableBalance').textContent = formatCurrency(data.balance);
        }
    })
    .catch(err => {
        console.error('Load balance error:', err);
    });
}

function loadStats() {
    fetch('{{ route("front_api_commission_transfer_stats") }}', {
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(res => res.json())
    .then(data => {
        if (data.success && data.stats) {
            document.getElementById('todayTransfer').textContent = formatCurrency(data.stats.today);
            document.getElementById('monthTransfer').textContent = formatCurrency(data.stats.month);
            document.getElementById('totalTransfer').textContent = formatCurrency(data.stats.total);
        }
    })
    .catch(err => {
        console.error('Load stats error:', err);
    });
}

function loadMt4Accounts() {
    fetch('{{ route("front_api_my_mt4_accounts") }}', {
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(res => res.json())
    .then(data => {
        if (data.success && data.accounts) {
            const select = document.getElementById('mt4Account');
            select.innerHTML = '<option value="">请选择MT4账号</option>';
            data.accounts.forEach(account => {
                const option = document.createElement('option');
                option.value = account.id;
                option.textContent = `${account.mt4_account} (余额: ${formatCurrency(account.balance)})`;
                select.appendChild(option);
            });
        }
    })
    .catch(err => {
        console.error('Load MT4 accounts error:', err);
    });
}

function handleTypeChange() {
    const type = document.getElementById('transferType').value;
    const mt4Field = document.getElementById('mt4AccountField');
    const agentField = document.getElementById('targetAgentField');

    if (type === 'to_mt4') {
        mt4Field.style.display = 'block';
        agentField.style.display = 'none';
        document.getElementById('feeRate').textContent = '0%';
    } else if (type === 'to_agent') {
        mt4Field.style.display = 'none';
        agentField.style.display = 'block';
        document.getElementById('feeRate').textContent = '2%';
    } else {
        mt4Field.style.display = 'none';
        agentField.style.display = 'none';
    }

    calculateFee();
}

function calculateFee() {
    const amount = parseFloat(document.getElementById('amount').value) || 0;
    const type = document.getElementById('transferType').value;
    let feeRate = 0;

    if (type === 'to_agent') {
        feeRate = 0.02;
    }

    const fee = amount * feeRate;
    const actualAmount = amount - fee;

    document.getElementById('fee').value = fee.toFixed(2);
    document.getElementById('actualAmount').textContent = formatCurrency(actualAmount);
}

function selectAgent() {
    const modal = new coreui.Modal(document.getElementById('selectAgentModal'));
    modal.show();
    loadAgentsList();
}

function loadAgentsList() {
    fetch('{{ route("front_api_commission_transfer_targets") }}', {
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(res => res.json())
    .then(data => {
        if (data.success && data.agents) {
            renderAgentsList(data.agents);
        }
    })
    .catch(err => {
        console.error('Load agents error:', err);
    });
}

function renderAgentsList(agents) {
    const tbody = document.getElementById('agentsTable');

    if (agents.length === 0) {
        tbody.innerHTML = `
            <tr>
                <td colspan="4" class="text-center py-3 text-body-secondary">
                    暂无可转账代理
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
            <td><span class="badge bg-info">${a.level || '-'}</span></td>
            <td><span class="badge bg-success">正常</span></td>
            <td>
                <button class="btn btn-sm btn-primary" onclick="chooseAgent(${a.id}, '${a.name}')">
                    选择
                </button>
            </td>
        </tr>
    `).join('');

    tbody.innerHTML = html;
}

function chooseAgent(id, name) {
    document.getElementById('targetAgentId').value = id;
    document.getElementById('targetAgent').value = name;
    coreui.Modal.getInstance(document.getElementById('selectAgentModal')).hide();
}

function submitTransfer() {
    const type = document.getElementById('transferType').value;
    const amount = document.getElementById('amount').value;
    const remark = document.getElementById('remark').value;

    if (!type) {
        alert('请选择转账类型');
        return;
    }

    if (!amount || parseFloat(amount) < 10) {
        alert('转账金额不能少于$10.00');
        return;
    }

    let targetId = '';
    if (type === 'to_mt4') {
        targetId = document.getElementById('mt4Account').value;
        if (!targetId) {
            alert('请选择MT4账号');
            return;
        }
    } else if (type === 'to_agent') {
        targetId = document.getElementById('targetAgentId').value;
        if (!targetId) {
            alert('请选择目标代理');
            return;
        }
    }

    fetch('{{ route("front_api_commission_transfer_submit") }}', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: JSON.stringify({
            type: type,
            target_id: targetId,
            amount: amount,
            remark: remark
        })
    })
    .then(res => res.json())
    .then(data => {
        if (data.success || data.code === 200) {
            alert('转账申请提交成功');
            resetForm();
            loadBalance();
            loadStats();
            loadTransfers();
        } else {
            alert(data.message || '提交失败');
        }
    })
    .catch(err => {
        console.error('Submit error:', err);
        alert('网络错误，请稍后重试');
    });
}

function resetForm() {
    document.getElementById('transferType').value = '';
    document.getElementById('mt4Account').value = '';
    document.getElementById('targetAgent').value = '';
    document.getElementById('targetAgentId').value = '';
    document.getElementById('amount').value = '';
    document.getElementById('fee').value = '0.00';
    document.getElementById('actualAmount').textContent = '$0.00';
    document.getElementById('remark').value = '';
    document.getElementById('mt4AccountField').style.display = 'none';
    document.getElementById('targetAgentField').style.display = 'none';
}

function loadTransfers(page = 1) {
    currentPage = page;
    const status = document.getElementById('filterStatus').value;
    const params = new URLSearchParams();
    if (status) params.append('status', status);
    params.append('page', page);

    fetch('{{ route("front_api_commission_transfer_list") }}?' + params.toString(), {
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(res => res.json())
    .then(data => {
        if (data.success && data.transfers) {
            renderTransfers(data.transfers);
            renderPagination(data.pagination);
            document.getElementById('totalRecords').textContent = data.pagination?.total || 0;
        }
    })
    .catch(err => {
        console.error('Load transfers error:', err);
    });
}

function renderTransfers(transfers) {
    const tbody = document.getElementById('transfersTable');

    if (transfers.length === 0) {
        tbody.innerHTML = `
            <tr>
                <td colspan="7" class="text-center py-5 text-body-secondary">
                    <i class="cil-inbox" style="font-size: 3rem; opacity: 0.3;"></i>
                    <p class="mt-3">暂无转账记录</p>
                </td>
            </tr>
        `;
        return;
    }

    const html = transfers.map(t => `
        <tr>
            <td class="fw-semibold">#${t.id || '-'}</td>
            <td>${getTypeBadge(t.type)}</td>
            <td class="fw-semibold">${t.target_account || '-'}</td>
            <td class="fw-bold text-success">${formatCurrency(t.amount)}</td>
            <td class="text-body-secondary">${formatCurrency(t.fee)}</td>
            <td>${getStatusBadge(t.status)}</td>
            <td class="text-body-secondary small">${t.created_at || '-'}</td>
        </tr>
    `).join('');

    tbody.innerHTML = html;
}

function renderPagination(pagination) {
    if (!pagination) return;

    const paginationEl = document.getElementById('pagination');
    let html = '';

    html += `<li class="page-item ${pagination.current_page === 1 ? 'disabled' : ''}">
        <a class="page-link" href="#" onclick="loadTransfers(${pagination.current_page - 1}); return false;">上一页</a>
    </li>`;

    for (let i = 1; i <= pagination.last_page; i++) {
        if (i === 1 || i === pagination.last_page || (i >= pagination.current_page - 2 && i <= pagination.current_page + 2)) {
            html += `<li class="page-item ${i === pagination.current_page ? 'active' : ''}">
                <a class="page-link" href="#" onclick="loadTransfers(${i}); return false;">${i}</a>
            </li>`;
        } else if (i === pagination.current_page - 3 || i === pagination.current_page + 3) {
            html += `<li class="page-item disabled"><span class="page-link">...</span></li>`;
        }
    }

    html += `<li class="page-item ${pagination.current_page === pagination.last_page ? 'disabled' : ''}">
        <a class="page-link" href="#" onclick="loadTransfers(${pagination.current_page + 1}); return false;">下一页</a>
    </li>`;

    paginationEl.innerHTML = html;
}

function getTypeBadge(type) {
    if (type === 'to_mt4') {
        return '<span class="badge bg-primary">转入MT4</span>';
    } else if (type === 'to_agent') {
        return '<span class="badge bg-info">转给代理</span>';
    }
    return '<span class="badge bg-secondary">-</span>';
}

function getStatusBadge(status) {
    if (status === 'pending') {
        return '<span class="badge bg-warning">待处理</span>';
    } else if (status === 'approved') {
        return '<span class="badge bg-success">已批准</span>';
    } else if (status === 'rejected') {
        return '<span class="badge bg-danger">已拒绝</span>';
    }
    return '<span class="badge bg-secondary">-</span>';
}

function formatCurrency(value) {
    if (!value && value !== 0) return '-';
    return '$' + parseFloat(value).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}
</script>

<style>
.bg-gradient-primary {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
}

.bg-gradient-success {
    background: linear-gradient(135deg, #10b981 0%, #059669 100%);
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
