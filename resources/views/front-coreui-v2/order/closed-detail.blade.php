@extends('front-coreui-v2.layouts.app')

@section('title', '平仓详情')

@section('content')
<div class="container-lg px-4">
    <!-- Page Header -->
    <div class="row mb-4">
        <div class="col-12">
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="{{ route('front_coreui_v2_page_order_closed') }}">平仓订单</a></li>
                    <li class="breadcrumb-item active">平仓详情</li>
                </ol>
            </nav>
            <h2 class="mb-2">平仓订单详情</h2>
            <p class="text-body-secondary mb-0">查看已平仓订单的完整交易信息</p>
        </div>
    </div>

    <div class="row">
        <!-- Left Column - Order Details -->
        <div class="col-lg-8">
            <!-- Order Info -->
            <div class="card shadow-sm border-0 mb-4">
                <div class="card-header bg-gradient-primary text-white">
                    <h5 class="mb-0">
                        <i class="cil-layers me-2"></i>订单基本信息
                    </h5>
                </div>
                <div class="card-body">
                    <div class="row g-4">
                        <div class="col-md-6">
                            <div class="border-start border-primary border-4 ps-3">
                                <label class="text-body-secondary small mb-1">订单号</label>
                                <h5 class="mb-0" id="orderNumber">-</h5>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="border-start border-secondary border-4 ps-3">
                                <label class="text-body-secondary small mb-1">MT4账号</label>
                                <h5 class="mb-0" id="mt4Account">-</h5>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="border-start border-info border-4 ps-3">
                                <label class="text-body-secondary small mb-1">交易品种</label>
                                <h5 class="mb-0" id="symbol">-</h5>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="border-start border-warning border-4 ps-3">
                                <label class="text-body-secondary small mb-1">订单类型</label>
                                <h5 class="mb-0" id="orderType">-</h5>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Trade Flow -->
            <div class="card shadow-sm border-0 mb-4">
                <div class="card-header bg-transparent border-bottom">
                    <h5 class="mb-0">
                        <i class="cil-swap-horizontal me-2"></i>交易流程
                    </h5>
                </div>
                <div class="card-body">
                    <div class="row g-4">
                        <div class="col-md-6">
                            <div class="text-center p-4 bg-light rounded">
                                <div class="mb-3">
                                    <i class="cil-arrow-thick-to-bottom text-success" style="font-size: 2.5rem;"></i>
                                </div>
                                <p class="text-body-secondary mb-2 small">开仓价格</p>
                                <h3 class="mb-0 text-success fw-bold" id="openPrice">-</h3>
                                <p class="mb-0 text-body-secondary small mt-2" id="openTime">-</p>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="text-center p-4 bg-light rounded">
                                <div class="mb-3">
                                    <i class="cil-arrow-thick-from-bottom text-danger" style="font-size: 2.5rem;"></i>
                                </div>
                                <p class="text-body-secondary mb-2 small">平仓价格</p>
                                <h3 class="mb-0 text-danger fw-bold" id="closePrice">-</h3>
                                <p class="mb-0 text-body-secondary small mt-2" id="closeTime">-</p>
                            </div>
                        </div>
                    </div>

                    <div class="text-center my-4">
                        <div class="d-inline-flex align-items-center gap-3 p-3 border rounded">
                            <div>
                                <p class="text-body-secondary mb-1 small">价格变动</p>
                                <h4 class="mb-0 fw-bold" id="priceChange">-</h4>
                            </div>
                            <div class="vr"></div>
                            <div>
                                <p class="text-body-secondary mb-1 small">变动点数</p>
                                <h4 class="mb-0 fw-bold" id="pricePips">-</h4>
                            </div>
                        </div>
                    </div>

                    <div class="row g-3">
                        <div class="col-md-6">
                            <div class="d-flex justify-content-between align-items-center p-3 border rounded">
                                <span class="text-body-secondary">止损价 (SL)</span>
                                <strong id="stopLoss">-</strong>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="d-flex justify-content-between align-items-center p-3 border rounded">
                                <span class="text-body-secondary">止盈价 (TP)</span>
                                <strong id="takeProfit">-</strong>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Trade Details -->
            <div class="card shadow-sm border-0 mb-4">
                <div class="card-header bg-transparent border-bottom">
                    <h5 class="mb-0">
                        <i class="cil-dollar me-2"></i>交易明细
                    </h5>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <div class="d-flex justify-content-between align-items-center p-3 bg-light rounded">
                                <span class="text-body-secondary">交易手数</span>
                                <strong id="lots">-</strong>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="d-flex justify-content-between align-items-center p-3 bg-light rounded">
                                <span class="text-body-secondary">占用保证金</span>
                                <strong id="margin">-</strong>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="d-flex justify-content-between align-items-center p-3 bg-light rounded">
                                <span class="text-body-secondary">持仓时长</span>
                                <strong id="duration">-</strong>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="d-flex justify-content-between align-items-center p-3 border rounded">
                                <span class="text-body-secondary">手续费</span>
                                <strong id="commission">-</strong>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="d-flex justify-content-between align-items-center p-3 border rounded">
                                <span class="text-body-secondary">库存费</span>
                                <strong id="swap">-</strong>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="d-flex justify-content-between align-items-center p-3 border rounded">
                                <span class="text-body-secondary">点差</span>
                                <strong id="spread">-</strong>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Close Reason -->
            <div class="card shadow-sm border-0">
                <div class="card-header bg-transparent border-bottom">
                    <h5 class="mb-0">
                        <i class="cil-info me-2"></i>平仓原因
                    </h5>
                </div>
                <div class="card-body">
                    <div class="d-flex align-items-start gap-3">
                        <div class="flex-shrink-0">
                            <div class="avatar bg-primary bg-opacity-10 text-primary">
                                <i class="cil-comment-square"></i>
                            </div>
                        </div>
                        <div class="flex-grow-1">
                            <h6 class="mb-2" id="closeReasonType">手动平仓</h6>
                            <p class="mb-0 text-body-secondary" id="closeReason">用户主动平仓</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Right Column - Profit & Stats -->
        <div class="col-lg-4">
            <!-- Final Profit -->
            <div class="card shadow-sm border-0 mb-4 text-white" id="profitCard">
                <div class="card-body text-center py-4">
                    <h6 class="mb-3 opacity-75">
                        <i class="cil-check-circle me-2"></i>最终盈亏
                    </h6>
                    <h1 class="mb-3 fw-bold" id="profitAmount">$0.00</h1>
                    <div class="d-flex justify-content-center gap-3">
                        <div>
                            <p class="mb-0 small opacity-75">盈亏点数</p>
                            <h5 class="mb-0" id="profitPips">0</h5>
                        </div>
                        <div class="vr"></div>
                        <div>
                            <p class="mb-0 small opacity-75">盈亏比例</p>
                            <h5 class="mb-0" id="profitPercent">0%</h5>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Net Profit Breakdown -->
            <div class="card shadow-sm border-0 mb-4">
                <div class="card-header bg-transparent border-bottom">
                    <h6 class="mb-0">
                        <i class="cil-calculator me-2"></i>盈亏明细
                    </h6>
                </div>
                <div class="card-body">
                    <div class="mb-3 pb-3 border-bottom">
                        <div class="d-flex justify-content-between mb-1">
                            <span class="text-body-secondary small">交易盈亏</span>
                            <strong id="tradeProfit">$0.00</strong>
                        </div>
                    </div>
                    <div class="mb-3 pb-3 border-bottom">
                        <div class="d-flex justify-content-between mb-1">
                            <span class="text-body-secondary small">手续费</span>
                            <strong class="text-danger" id="commissionFee">$0.00</strong>
                        </div>
                    </div>
                    <div class="mb-3 pb-3 border-bottom">
                        <div class="d-flex justify-content-between mb-1">
                            <span class="text-body-secondary small">库存费</span>
                            <strong class="text-warning" id="swapFee">$0.00</strong>
                        </div>
                    </div>
                    <div>
                        <div class="d-flex justify-content-between mb-1">
                            <span class="fw-semibold">净盈亏</span>
                            <strong class="h5 mb-0" id="netProfit">$0.00</strong>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Performance Metrics -->
            <div class="card shadow-sm border-0 mb-4">
                <div class="card-header bg-transparent border-bottom">
                    <h6 class="mb-0">
                        <i class="cil-chart-line me-2"></i>交易指标
                    </h6>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <div class="d-flex justify-content-between mb-2">
                            <span class="text-body-secondary small">风险回报比</span>
                            <strong id="riskReward">-</strong>
                        </div>
                    </div>
                    <div class="mb-3">
                        <div class="d-flex justify-content-between mb-2">
                            <span class="text-body-secondary small">盈亏比率</span>
                            <strong id="profitRatio">-</strong>
                        </div>
                    </div>
                    <div>
                        <div class="d-flex justify-content-between mb-2">
                            <span class="text-body-secondary small">保证金收益率</span>
                            <strong id="marginReturn">-</strong>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Actions -->
            <div class="card shadow-sm border-0">
                <div class="card-body">
                    <h6 class="mb-3">
                        <i class="cil-options me-2"></i>相关操作
                    </h6>
                    <div class="d-grid gap-2">
                        <button class="btn btn-outline-primary" onclick="viewSimilarOrders()">
                            <i class="cil-list me-2"></i>查看相似订单
                        </button>
                        <button class="btn btn-outline-secondary" onclick="exportOrder()">
                            <i class="cil-cloud-download me-2"></i>导出订单
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
let orderNumber = '';

document.addEventListener('DOMContentLoaded', function() {
    const urlParams = new URLSearchParams(window.location.search);
    orderNumber = urlParams.get('order');

    if (orderNumber) {
        loadOrderDetail();
    } else {
        alert('订单号不能为空');
        window.location.href = '{{ route("front_coreui_v2_page_order_closed") }}';
    }
});

function loadOrderDetail() {
    fetch(`{{ route("front_api_order_closed_detail") }}?order=${orderNumber}`, {
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(res => res.json())
    .then(data => {
        if (data.success && data.order) {
            renderOrderDetail(data.order);
        } else {
            alert(data.message || '订单不存在');
            window.location.href = '{{ route("front_coreui_v2_page_order_closed") }}';
        }
    })
    .catch(err => {
        console.error('Load order detail error:', err);
    });
}

function renderOrderDetail(order) {
    // Basic Info
    document.getElementById('orderNumber').textContent = order.order_number || '-';
    document.getElementById('mt4Account').textContent = order.mt4_account || '-';
    document.getElementById('symbol').textContent = order.symbol || '-';
    document.getElementById('orderType').innerHTML = getTypeBadge(order.type);

    // Trade Flow
    document.getElementById('openPrice').textContent = formatPrice(order.open_price);
    document.getElementById('openTime').textContent = order.open_time || '-';
    document.getElementById('closePrice').textContent = formatPrice(order.close_price);
    document.getElementById('closeTime').textContent = order.close_time || '-';

    const priceChange = parseFloat(order.close_price || 0) - parseFloat(order.open_price || 0);
    document.getElementById('priceChange').textContent = formatPrice(Math.abs(priceChange));
    document.getElementById('pricePips').textContent = formatPips(order.price_pips);

    document.getElementById('stopLoss').textContent = formatPrice(order.stop_loss);
    document.getElementById('takeProfit').textContent = formatPrice(order.take_profit);

    // Trade Details
    document.getElementById('lots').textContent = formatNumber(order.lots);
    document.getElementById('margin').textContent = formatCurrency(order.margin);
    document.getElementById('duration').textContent = calculateDuration(order.open_time, order.close_time);
    document.getElementById('commission').textContent = formatCurrency(order.commission);
    document.getElementById('swap').textContent = formatCurrency(order.swap);
    document.getElementById('spread').textContent = formatPips(order.spread);

    // Close Reason
    document.getElementById('closeReasonType').textContent = order.close_reason_type || '手动平仓';
    document.getElementById('closeReason').textContent = order.close_reason || '用户主动平仓';

    // Profit Card
    const profit = parseFloat(order.profit || 0);
    const profitEl = document.getElementById('profitAmount');
    const profitCard = document.getElementById('profitCard');

    profitEl.textContent = formatCurrency(profit);
    if (profit >= 0) {
        profitCard.style.background = 'linear-gradient(135deg, #10b981 0%, #059669 100%)';
    } else {
        profitCard.style.background = 'linear-gradient(135deg, #ef4444 0%, #dc2626 100%)';
    }

    document.getElementById('profitPips').textContent = formatPips(order.profit_pips);
    document.getElementById('profitPercent').textContent = formatPercent(order.profit_percent);

    // Profit Breakdown
    document.getElementById('tradeProfit').textContent = formatCurrency(order.trade_profit);
    document.getElementById('commissionFee').textContent = formatCurrency(Math.abs(order.commission || 0));
    document.getElementById('swapFee').textContent = formatCurrency(Math.abs(order.swap || 0));
    document.getElementById('netProfit').textContent = formatCurrency(profit);

    // Performance Metrics
    document.getElementById('riskReward').textContent = order.risk_reward || '-';
    document.getElementById('profitRatio').textContent = order.profit_ratio || '-';
    document.getElementById('marginReturn').textContent = formatPercent(order.margin_return);
}

function calculateDuration(openTime, closeTime) {
    if (!openTime || !closeTime) return '-';

    const start = new Date(openTime);
    const end = new Date(closeTime);
    const diff = end - start;

    const days = Math.floor(diff / (1000 * 60 * 60 * 24));
    const hours = Math.floor((diff % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
    const minutes = Math.floor((diff % (1000 * 60 * 60)) / (1000 * 60));

    if (days > 0) {
        return `${days}天 ${hours}小时`;
    } else if (hours > 0) {
        return `${hours}小时 ${minutes}分钟`;
    } else {
        return `${minutes}分钟`;
    }
}

function viewSimilarOrders() {
    alert('查看相似订单功能开发中');
}

function exportOrder() {
    window.location.href = `{{ route("front_api_order_export") }}?order=${orderNumber}`;
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

function formatPips(value) {
    if (!value && value !== 0) return '0';
    const num = parseFloat(value);
    return (num >= 0 ? '+' : '') + num.toFixed(1);
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
