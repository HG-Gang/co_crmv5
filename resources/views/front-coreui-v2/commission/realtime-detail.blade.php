@extends('front-coreui-v2.layouts.app')

@section('title', '返佣详情')

@section('content')
<div class="container-lg px-4">
    <!-- Page Header -->
    <div class="row mb-4">
        <div class="col-12">
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="{{ route('front_coreui_v2_page_commission_realtime') }}">实时返佣</a></li>
                    <li class="breadcrumb-item active">返佣详情</li>
                </ol>
            </nav>
            <h2 class="mb-2">返佣详情</h2>
            <p class="text-body-secondary mb-0">查看返佣记录的详细信息和计算明细</p>
        </div>
    </div>

    <div class="row">
        <!-- Left Column -->
        <div class="col-lg-8">
            <!-- Commission Info -->
            <div class="card shadow-sm border-0 mb-4">
                <div class="card-header bg-gradient-success text-white">
                    <h5 class="mb-0">
                        <i class="cil-dollar me-2"></i>返佣信息
                    </h5>
                </div>
                <div class="card-body">
                    <div class="row g-4">
                        <div class="col-md-6">
                            <div class="border-start border-success border-4 ps-3">
                                <label class="text-body-secondary small mb-1">返佣金额</label>
                                <h3 class="mb-0 text-success" id="commissionAmount">$0.00</h3>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="border-start border-info border-4 ps-3">
                                <label class="text-body-secondary small mb-1">返佣类型</label>
                                <h5 class="mb-0" id="commissionType">-</h5>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="border-start border-primary border-4 ps-3">
                                <label class="text-body-secondary small mb-1">返佣比例</label>
                                <h5 class="mb-0" id="commissionRate">-</h5>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="border-start border-warning border-4 ps-3">
                                <label class="text-body-secondary small mb-1">结算时间</label>
                                <h5 class="mb-0" id="settlementTime">-</h5>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Trade Info -->
            <div class="card shadow-sm border-0 mb-4">
                <div class="card-header bg-transparent border-bottom">
                    <h5 class="mb-0">
                        <i class="cil-chart me-2"></i>交易信息
                    </h5>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <div class="d-flex justify-content-between align-items-center p-3 bg-light rounded">
                                <span class="text-body-secondary">订单编号</span>
                                <strong id="orderId">-</strong>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="d-flex justify-content-between align-items-center p-3 bg-light rounded">
                                <span class="text-body-secondary">交易品种</span>
                                <strong id="symbol">-</strong>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="d-flex justify-content-between align-items-center p-3 bg-light rounded">
                                <span class="text-body-secondary">交易手数</span>
                                <strong id="lots">-</strong>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="d-flex justify-content-between align-items-center p-3 bg-light rounded">
                                <span class="text-body-secondary">交易方向</span>
                                <strong id="direction">-</strong>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="d-flex justify-content-between align-items-center p-3 bg-light rounded">
                                <span class="text-body-secondary">开仓价格</span>
                                <strong id="openPrice">-</strong>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="d-flex justify-content-between align-items-center p-3 bg-light rounded">
                                <span class="text-body-secondary">平仓价格</span>
                                <strong id="closePrice">-</strong>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="d-flex justify-content-between align-items-center p-3 bg-light rounded">
                                <span class="text-body-secondary">开仓时间</span>
                                <strong id="openTime">-</strong>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="d-flex justify-content-between align-items-center p-3 bg-light rounded">
                                <span class="text-body-secondary">平仓时间</span>
                                <strong id="closeTime">-</strong>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Customer Info -->
            <div class="card shadow-sm border-0 mb-4">
                <div class="card-header bg-transparent border-bottom">
                    <h5 class="mb-0">
                        <i class="cil-user me-2"></i>客户信息
                    </h5>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <div class="d-flex justify-content-between align-items-center p-3 bg-light rounded">
                                <span class="text-body-secondary">客户姓名</span>
                                <strong id="customerName">-</strong>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="d-flex justify-content-between align-items-center p-3 bg-light rounded">
                                <span class="text-body-secondary">联系邮箱</span>
                                <strong id="customerEmail">-</strong>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="d-flex justify-content-between align-items-center p-3 bg-light rounded">
                                <span class="text-body-secondary">MT4账号</span>
                                <strong id="mt4Account">-</strong>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="d-flex justify-content-between align-items-center p-3 bg-light rounded">
                                <span class="text-body-secondary">代理层级</span>
                                <strong id="agentLevel">-</strong>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Calculation Details -->
            <div class="card shadow-sm border-0">
                <div class="card-header bg-transparent border-bottom">
                    <h5 class="mb-0">
                        <i class="cil-calculator me-2"></i>计算明细
                    </h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-sm mb-0">
                            <tbody>
                                <tr>
                                    <td class="text-body-secondary">基础返佣</td>
                                    <td class="text-end fw-semibold" id="baseCommission">$0.00</td>
                                </tr>
                                <tr>
                                    <td class="text-body-secondary">返佣比例</td>
                                    <td class="text-end fw-semibold" id="ratePercent">0%</td>
                                </tr>
                                <tr>
                                    <td class="text-body-secondary">手数系数</td>
                                    <td class="text-end fw-semibold" id="lotMultiplier">1.0</td>
                                </tr>
                                <tr class="border-top">
                                    <td class="text-body-secondary fw-bold">实际返佣</td>
                                    <td class="text-end fw-bold text-success" id="actualCommission">$0.00</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <div class="mt-3 p-3 bg-light rounded">
                        <p class="text-body-secondary small mb-2">计算公式</p>
                        <code class="small" id="formula">实际返佣 = 基础返佣 × 返佣比例 × 手数系数</code>
                    </div>
                </div>
            </div>
        </div>

        <!-- Right Column -->
        <div class="col-lg-4">
            <!-- Status Card -->
            <div class="card shadow-sm border-0 mb-4 text-white bg-gradient-success">
                <div class="card-body text-center py-4">
                    <div class="mb-3">
                        <i class="cil-check-circle" style="font-size: 3rem;"></i>
                    </div>
                    <h3 class="mb-2 fw-bold" id="statusText">已结算</h3>
                    <p class="mb-0 opacity-75 small">返佣已成功结算到账户</p>
                </div>
            </div>

            <!-- Commission Path -->
            <div class="card shadow-sm border-0 mb-4">
                <div class="card-header bg-transparent border-bottom">
                    <h6 class="mb-0">
                        <i class="cil-sitemap me-2"></i>返佣路径
                    </h6>
                </div>
                <div class="card-body">
                    <div id="commissionPath">
                        <div class="spinner-border spinner-border-sm"></div>
                    </div>
                </div>
            </div>

            <!-- Related Info -->
            <div class="card shadow-sm border-0 mb-4">
                <div class="card-header bg-transparent border-bottom">
                    <h6 class="mb-0">
                        <i class="cil-info me-2"></i>相关信息
                    </h6>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <label class="text-body-secondary small">记录编号</label>
                        <p class="mb-0 fw-semibold" id="recordId">-</p>
                    </div>
                    <div class="mb-3">
                        <label class="text-body-secondary small">创建时间</label>
                        <p class="mb-0 fw-semibold" id="createdAt">-</p>
                    </div>
                    <div>
                        <label class="text-body-secondary small">备注说明</label>
                        <p class="mb-0 fw-semibold" id="remark">-</p>
                    </div>
                </div>
            </div>

            <!-- Actions -->
            <div class="card shadow-sm border-0">
                <div class="card-header bg-transparent border-bottom">
                    <h6 class="mb-0">
                        <i class="cil-settings me-2"></i>操作
                    </h6>
                </div>
                <div class="card-body">
                    <div class="d-grid gap-2">
                        <button class="btn btn-outline-primary" onclick="viewCustomer()">
                            <i class="cil-user me-2"></i>查看客户
                        </button>
                        <button class="btn btn-outline-secondary" onclick="viewTrade()">
                            <i class="cil-chart me-2"></i>查看交易
                        </button>
                        <button class="btn btn-outline-secondary" onclick="goBack()">
                            <i class="cil-arrow-left me-2"></i>返回列表
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
let commissionId = '';
let customerId = '';
let orderId = '';

document.addEventListener('DOMContentLoaded', function() {
    const urlParams = new URLSearchParams(window.location.search);
    commissionId = urlParams.get('id');

    if (commissionId) {
        loadCommissionDetail();
    } else {
        alert('返佣ID不能为空');
        window.location.href = '{{ route("front_coreui_v2_page_commission_realtime") }}';
    }
});

function loadCommissionDetail() {
    fetch(`{{ route("front_api_commission_detail") }}?id=${commissionId}`, {
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(res => res.json())
    .then(data => {
        if (data.success && data.commission) {
            renderCommissionDetail(data.commission);
        } else {
            alert(data.message || '返佣记录不存在');
            window.location.href = '{{ route("front_coreui_v2_page_commission_realtime") }}';
        }
    })
    .catch(err => {
        console.error('Load commission detail error:', err);
    });
}

function renderCommissionDetail(comm) {
    customerId = comm.customer_id;
    orderId = comm.order_id;

    // Commission Info
    document.getElementById('commissionAmount').textContent = formatCurrency(comm.amount);
    document.getElementById('commissionType').innerHTML = getTypeBadge(comm.type);
    document.getElementById('commissionRate').textContent = (comm.rate || 0) + '%';
    document.getElementById('settlementTime').textContent = comm.settlement_time || '-';

    // Trade Info
    document.getElementById('orderId').textContent = '#' + (comm.order_id || '-');
    document.getElementById('symbol').textContent = comm.symbol || '-';
    document.getElementById('lots').textContent = comm.lots || '-';
    document.getElementById('direction').innerHTML = getDirectionBadge(comm.direction);
    document.getElementById('openPrice').textContent = comm.open_price || '-';
    document.getElementById('closePrice').textContent = comm.close_price || '-';
    document.getElementById('openTime').textContent = comm.open_time || '-';
    document.getElementById('closeTime').textContent = comm.close_time || '-';

    // Customer Info
    document.getElementById('customerName').textContent = comm.customer_name || '-';
    document.getElementById('customerEmail').textContent = comm.customer_email || '-';
    document.getElementById('mt4Account').textContent = comm.mt4_account || '-';
    document.getElementById('agentLevel').textContent = comm.agent_level || '-';

    // Calculation Details
    document.getElementById('baseCommission').textContent = formatCurrency(comm.base_commission);
    document.getElementById('ratePercent').textContent = (comm.rate || 0) + '%';
    document.getElementById('lotMultiplier').textContent = comm.lot_multiplier || '1.0';
    document.getElementById('actualCommission').textContent = formatCurrency(comm.amount);

    // Related Info
    document.getElementById('recordId').textContent = '#' + (comm.id || '-');
    document.getElementById('createdAt').textContent = comm.created_at || '-';
    document.getElementById('remark').textContent = comm.remark || '无';

    // Commission Path
    renderCommissionPath(comm.path || []);
}

function renderCommissionPath(path) {
    const pathEl = document.getElementById('commissionPath');

    if (path.length === 0) {
        pathEl.innerHTML = '<p class="text-body-secondary small mb-0">暂无路径信息</p>';
        return;
    }

    const html = path.map((p, index) => `
        <div class="d-flex align-items-start mb-3">
            <div class="flex-shrink-0">
                <div class="avatar avatar-xs bg-success bg-opacity-10 text-success">
                    <i class="cil-user"></i>
                </div>
            </div>
            <div class="flex-grow-1 ms-3">
                <p class="mb-1 small fw-semibold">${p.name || '-'}</p>
                <p class="mb-0 text-body-secondary small">等级: ${p.level || '-'} | 返佣: ${formatCurrency(p.amount)}</p>
            </div>
        </div>
        ${index < path.length - 1 ? '<div class="ms-2 mb-2"><i class="cil-arrow-bottom text-body-secondary"></i></div>' : ''}
    `).join('');

    pathEl.innerHTML = html;
}

function getTypeBadge(type) {
    if (type === 'direct') {
        return '<span class="badge bg-success">直接返佣</span>';
    } else if (type === 'indirect') {
        return '<span class="badge bg-info">间接返佣</span>';
    }
    return '<span class="badge bg-secondary">-</span>';
}

function getDirectionBadge(direction) {
    if (direction === 'buy' || direction === 'Buy') {
        return '<span class="badge bg-success">买入</span>';
    } else if (direction === 'sell' || direction === 'Sell') {
        return '<span class="badge bg-danger">卖出</span>';
    }
    return '<span class="badge bg-secondary">-</span>';
}

function viewCustomer() {
    window.location.href = `{{ route('front_coreui_v2_page_agent_customers_detail') }}?id=${customerId}`;
}

function viewTrade() {
    window.location.href = `{{ route('front_coreui_v2_page_order_closed_detail') }}?id=${orderId}`;
}

function goBack() {
    window.location.href = '{{ route("front_coreui_v2_page_commission_realtime") }}';
}

function formatCurrency(value) {
    if (!value && value !== 0) return '-';
    return '$' + parseFloat(value).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}
</script>

<style>
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

.avatar-xs {
    width: 24px;
    height: 24px;
    font-size: 0.875rem;
}
</style>
@endsection
