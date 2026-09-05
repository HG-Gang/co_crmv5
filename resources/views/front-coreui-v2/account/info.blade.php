@extends('front-coreui-v2.layouts.app')

@section('title', '账户信息')

@section('content')
<div class="container-lg px-4">
    <!-- Page Header -->
    <div class="row mb-4">
        <div class="col-12">
            <h2 class="mb-2">账户信息</h2>
            <p class="text-body-secondary mb-0">查看您的MT4账户详细信息</p>
        </div>
    </div>

    <!-- Filter Bar -->
    <div class="card shadow-sm border-0 mb-4">
        <div class="card-body">
            <div class="row g-3 align-items-end">
                <div class="col-md-3">
                    <label class="form-label small">账户类型</label>
                    <select id="filterAccountType" class="form-select">
                        <option value="">全部类型</option>
                        <option value="real">真实账户</option>
                        <option value="demo">模拟账户</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label small">账户状态</label>
                    <select id="filterStatus" class="form-select">
                        <option value="">全部状态</option>
                        <option value="active">正常</option>
                        <option value="readonly">只读</option>
                        <option value="disabled">禁用</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label small">搜索</label>
                    <input type="text" id="filterKeyword" class="form-control" placeholder="MT4账号/名称">
                </div>
                <div class="col-md-3">
                    <button onclick="loadAccounts()" class="btn btn-primary w-100">
                        <i class="cil-search me-2"></i>查询
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Accounts Grid -->
    <div class="row g-4" id="accountsGrid">
        <div class="col-12 text-center py-5">
            <div class="spinner-border text-primary" role="status">
                <span class="visually-hidden">加载中...</span>
            </div>
            <p class="text-body-secondary mt-3">加载账户信息...</p>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    loadAccounts();
});

function loadAccounts() {
    const accountType = document.getElementById('filterAccountType').value;
    const status = document.getElementById('filterStatus').value;
    const keyword = document.getElementById('filterKeyword').value.trim();

    const params = new URLSearchParams();
    if (accountType) params.append('account_type', accountType);
    if (status) params.append('status', status);
    if (keyword) params.append('keyword', keyword);

    fetch('{{ route("front_api_account_list") }}?' + params.toString(), {
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(res => res.json())
    .then(data => {
        if (data.success && data.accounts) {
            renderAccounts(data.accounts);
        } else {
            showError('加载失败');
        }
    })
    .catch(err => {
        console.error('Load accounts error:', err);
        showError('网络错误，请稍后重试');
    });
}

function renderAccounts(accounts) {
    const grid = document.getElementById('accountsGrid');

    if (accounts.length === 0) {
        grid.innerHTML = `
            <div class="col-12 text-center py-5">
                <i class="cil-inbox" style="font-size: 4rem; color: #ccc;"></i>
                <p class="text-body-secondary mt-3">暂无账户信息</p>
                <a href="{{ route('front_coreui_v2_page_account_voucher') }}" class="btn btn-primary-gradient">
                    <i class="cil-plus me-2"></i>申请开户
                </a>
            </div>
        `;
        return;
    }

    const html = accounts.map(acc => `
        <div class="col-xl-3 col-lg-4 col-md-6">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-header bg-gradient-primary text-white border-0">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h5 class="mb-1 fw-bold">${acc.mt4_account || '-'}</h5>
                            <small class="opacity-75">${acc.account_name || '-'}</small>
                        </div>
                        ${getAccountTypeBadge(acc.account_type)}
                    </div>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <span class="text-body-secondary small">账户状态</span>
                            ${getStatusBadge(acc.status)}
                        </div>
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <span class="text-body-secondary small">账户组</span>
                            <span class="fw-semibold">${acc.group_name || '-'}</span>
                        </div>
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <span class="text-body-secondary small">杠杆</span>
                            <span class="fw-semibold">1:${acc.leverage || '100'}</span>
                        </div>
                    </div>

                    <div class="border-top pt-3 mb-3">
                        <div class="row text-center g-2">
                            <div class="col-6">
                                <div class="p-2 bg-light rounded">
                                    <div class="h5 mb-0 text-primary">${formatCurrency(acc.balance)}</div>
                                    <small class="text-body-secondary">余额</small>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="p-2 bg-light rounded">
                                    <div class="h5 mb-0 text-success">${formatCurrency(acc.equity)}</div>
                                    <small class="text-body-secondary">净值</small>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="p-2 bg-light rounded">
                                    <div class="h5 mb-0 text-info">${formatCurrency(acc.margin)}</div>
                                    <small class="text-body-secondary">已用保证金</small>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="p-2 bg-light rounded">
                                    <div class="h5 mb-0 text-warning">${formatCurrency(acc.free_margin)}</div>
                                    <small class="text-body-secondary">可用保证金</small>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="border-top pt-3">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <span class="text-body-secondary small"><i class="cil-chart-line me-1"></i>持仓</span>
                            <span class="fw-semibold">${acc.open_positions || 0} 笔</span>
                        </div>
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <span class="text-body-secondary small"><i class="cil-calendar me-1"></i>开户时间</span>
                            <span class="small">${acc.created_at || '-'}</span>
                        </div>
                        <div class="d-flex justify-content-between align-items-center">
                            <span class="text-body-secondary small"><i class="cil-reload me-1"></i>最后更新</span>
                            <span class="small">${acc.updated_at || '-'}</span>
                        </div>
                    </div>
                </div>
                <div class="card-footer bg-transparent border-0 pt-0">
                    <div class="d-grid gap-2">
                        <button onclick="viewDetails('${acc.id}')" class="btn btn-outline-primary btn-sm">
                            <i class="cil-info me-2"></i>查看详情
                        </button>
                    </div>
                </div>
            </div>
        </div>
    `).join('');

    grid.innerHTML = html;
}

function getAccountTypeBadge(type) {
    const badges = {
        'real': '<span class="badge bg-warning text-dark">真实</span>',
        'demo': '<span class="badge bg-info">模拟</span>'
    };
    return badges[type] || '<span class="badge bg-secondary">未知</span>';
}

function getStatusBadge(status) {
    const badges = {
        'active': '<span class="badge bg-success">正常</span>',
        'readonly': '<span class="badge bg-warning text-dark">只读</span>',
        'disabled': '<span class="badge bg-danger">禁用</span>'
    };
    return badges[status] || '<span class="badge bg-secondary">未知</span>';
}

function formatCurrency(value) {
    if (!value) return '$0.00';
    return '$' + parseFloat(value).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

function showError(message) {
    document.getElementById('accountsGrid').innerHTML = `
        <div class="col-12 text-center py-5">
            <i class="cil-warning" style="font-size: 4rem; color: #dc3545;"></i>
            <p class="text-danger mt-3">${message}</p>
            <button onclick="loadAccounts()" class="btn btn-outline-primary">
                <i class="cil-reload me-2"></i>重新加载
            </button>
        </div>
    `;
}

function viewDetails(accountId) {
    window.location.href = '{{ route("front_coreui_v2_page_account_info") }}?id=' + accountId;
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
</style>
@endsection
