@extends('front-coreui-v2.layouts.app')

@section('title', '开仓详情')

@section('content')
<div class="container-lg px-4">
    <!-- Page Header -->
    <div class="row mb-4">
        <div class="col-12">
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="{{ route('front_coreui_v2_page_order_open') }}">开仓订单</a></li>
                    <li class="breadcrumb-item active">订单详情</li>
                </ol>
            </nav>
            <h2 class="mb-2">开仓订单详情</h2>
            <p class="text-body-secondary mb-0">查看订单完整信息和实时行情</p>
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

            <!-- Price Details -->
            <div class="card shadow-sm border-0 mb-4">
                <div class="card-header bg-transparent border-bottom">
                    <h5 class="mb-0">
                        <i class="cil-chart-line me-2"></i>价格明细
                    </h5>
                </div>
                <div class="card-body">
                    <div class="row g-4">
                        <div class="col-md-4">
                            <div class="text-center p-3 bg-light rounded">
                                <p class="text-body-secondary mb-2 small">开仓价格</p>
                                <h4 class="mb-0 text-primary" id="openPrice">-</h4>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="text-center p-3 bg-light rounded">
                                <p class="text-body-secondary mb-2 small">当前价格</p>
                                <h4 class="mb-0 text-info" id="currentPrice">-</h4>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="text-center p-3 bg-light rounded">
                                <p class="text-body-secondary mb-2 small">价格变动</p>
                                <h4 class="mb-0 text-secondary" id="priceDiff">-</h4>
                            </div>
                        </div>
                    </div>

                    <div class="row g-3 mt-3">
                        <div class="col-md-6">
                            <div class="d-flex justify-content-between align-items-center p-3 border rounded">
                                <div>
                                    <i class="cil-arrow-bottom text-danger me-2"></i>
                                    <span class="text-body-secondary">止损价 (Stop Loss)</span>
                                </div>
                                <strong id="stopLoss">-</strong>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="d-flex justify-content-between align-items-center p-3 border rounded">
                                <div>
                                    <i class="cil-arrow-top text-success me-2"></i>
                                    <span class="text-body-secondary">止盈价 (Take Profit)</span>
                                </div>
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
                    <div class="row g-4">
                        <div class="col-md-6">
                            <div class="d-flex justify-content-between align-items-center p-3 bg-light rounded">
                                <div>
                                    <p class="text-body-secondary mb-1 small">交易手数</p>
                                    <h4 class="mb-0 fw-bold" id="lots">-</h4>
                                </div>
                                <i class="cil-chart-line text-primary" style="font-size: 2rem;"></i>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="d-flex justify-content-between align-items-center p-3 bg-light rounded">
                                <div>
                                    <p class="text-body-secondary mb-1 small">占用保证金</p>
                                    <h4 class="mb-0 fw-bold" id="margin">-</h4>
                                </div>
                                <i class="cil-wallet text-warning" style="font-size: 2rem;"></i>
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

            <!-- Time Info -->
            <div class="card shadow-sm border-0 mb-4">
                <div class="card-header bg-transparent border-bottom">
                    <h5 class="mb-0">
                        <i class="cil-calendar me-2"></i>时间信息
                    </h5>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <div class="d-flex justify-content-between align-items-center">
                                <span class="text-body-secondary">
                                    <i class="cil-clock me-2"></i>开仓时间
                                </span>
                                <strong id="openTime">-</strong>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="d-flex justify-content-between align-items-center">
                                <span class="text-body-secondary">
                                    <i class="cil-history me-2"></i>持仓时长
                                </span>
                                <strong id="duration">-</strong>
                            </div>
                        </div>
                        <div class="col-12">
                            <div class="d-flex justify-content-between align-items-center">
                                <span class="text-body-secondary">
                                    <i class="cil-reload me-2"></i>到期时间
                                </span>
                                <strong id="expiration">无限期</strong>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Comment -->
            <div class="card shadow-sm border-0">
                <div class="card-header bg-transparent border-bottom">
                    <h5 class="mb-0">
                        <i class="cil-comment-square me-2"></i>订单备注
                    </h5>
                </div>
                <div class="card-body">
                    <p class="mb-0 text-body-secondary" id="comment">无备注信息</p>
                </div>
            </div>
        </div>

        <!-- Right Column - Profit & Actions -->
        <div class="col-lg-4">
            <!-- Real-time Profit -->
            <div class="card shadow-sm border-0 mb-4 text-white" id="profitCard">
                <div class="card-body text-center py-4">
                    <h6 class="mb-3 opacity-75">
                        <i class="cil-dollar me-2"></i>实时浮动盈亏
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

            <!-- Quick Actions -->
            <div class="card shadow-sm border-0 mb-4">
                <div class="card-header bg-transparent border-bottom">
                    <h6 class="mb-0">
                        <i class="cil-settings me-2"></i>快捷操作
                    </h6>
                </div>
                <div class="card-body">
                    <div class="d-grid gap-2">
                        <button class="btn btn-outline-primary" onclick="modifyOrder()">
                            <i class="cil-pencil me-2"></i>修改止损/止盈
                        </button>
                        <button class="btn btn-outline-warning" onclick="partialClose()">
                            <i class="cil-layers me-2"></i>部分平仓
                        </button>
                        <button class="btn btn-danger" onclick="closeOrder()">
                            <i class="cil-x-circle me-2"></i>全部平仓
                        </button>
                    </div>
                </div>
            </div>

            <!-- Account Info -->
            <div class="card shadow-sm border-0 mb-4">
                <div class="card-header bg-transparent border-bottom">
                    <h6 class="mb-0">
                        <i class="cil-wallet me-2"></i>账户状态
                    </h6>
                </div>
                <div class="card-body">
                    <div class="mb-3 pb-3 border-bottom">
                        <div class="d-flex justify-content-between mb-1">
                            <span class="text-body-secondary small">账户余额</span>
                            <strong id="accountBalance">$0.00</strong>
                        </div>
                    </div>
                    <div class="mb-3 pb-3 border-bottom">
                        <div class="d-flex justify-content-between mb-1">
                            <span class="text-body-secondary small">账户净值</span>
                            <strong id="accountEquity">$0.00</strong>
                        </div>
                    </div>
                    <div class="mb-3 pb-3 border-bottom">
                        <div class="d-flex justify-content-between mb-1">
                            <span class="text-body-secondary small">可用保证金</span>
                            <strong id="freeMargin">$0.00</strong>
                        </div>
                    </div>
                    <div>
                        <div class="d-flex justify-content-between mb-1">
                            <span class="text-body-secondary small">保证金比例</span>
                            <strong id="marginLevel">0%</strong>
                        </div>
                        <div class="progress mt-2" style="height: 6px;">
                            <div class="progress-bar" id="marginLevelBar" role="progressbar" style="width: 0%"></div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Tips -->
            <div class="card shadow-sm border-0">
                <div class="card-body">
                    <h6 class="mb-3">
                        <i class="cil-lightbulb me-2"></i>交易提示
                    </h6>
                    <ul class="mb-0 small text-body-secondary">
                        <li class="mb-2">数据每2秒自动刷新</li>
                        <li class="mb-2">合理设置止损止盈保护资金</li>
                        <li class="mb-2">关注保证金比例避免强平</li>
                        <li>重大行情时注意风险控制</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
let orderNumber = '';
let autoRefreshTimer = null;

document.addEventListener('DOMContentLoaded', function() {
    const urlParams = new URLSearchParams(window.location.search);
    orderNumber = urlParams.get('order');

    if (orderNumber) {
        loadOrderDetail();

        // Auto refresh every 2 seconds
        autoRefreshTimer = setInterval(function() {
            loadOrderDetail();
        }, 2000);
    } else {
        alert('订单号不能为空');
        window.location.href = '{{ route("front_coreui_v2_page_order_open") }}';
    }
});

function loadOrderDetail() {
    fetch(`{{ route("front_api_order_detail") }}?order=${orderNumber}`, {
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(res => res.json())
    .then(data => {
        if (data.success && data.order) {
            renderOrderDetail(data.order);
        } else {
            alert(data.message || '订单不存在');
            window.location.href = '{{ route("front_coreui_v2_page_order_open") }}';
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

    // Price Info
    document.getElementById('openPrice').textContent = formatPrice(order.open_price);
    document.getElementById('currentPrice').textContent = formatPrice(order.current_price);
    document.getElementById('priceDiff').textContent = formatPips(order.price_diff);
    document.getElementById('stopLoss').textContent = formatPrice(order.stop_loss);
    document.getElementById('takeProfit').textContent = formatPrice(order.take_profit);

    // Trade Details
    document.getElementById('lots').textContent = formatNumber(order.lots);
    document.getElementById('margin').textContent = formatCurrency(order.margin);
    document.getElementById('commission').textContent = formatCurrency(order.commission);
    document.getElementById('swap').textContent = formatCurrency(order.swap);
    document.getElementById('spread').textContent = formatPips(order.spread);

    // Time Info
    document.getElementById('openTime').textContent = order.open_time || '-';
    document.getElementById('duration').textContent = calculateDuration(order.open_time);
    document.getElementById('expiration').textContent = order.expiration || '无限期';

    // Comment
    document.getElementById('comment').textContent = order.comment || '无备注信息';

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

    // Account Info
    document.getElementById('accountBalance').textContent = formatCurrency(order.account_balance);
    document.getElementById('accountEquity').textContent = formatCurrency(order.account_equity);
    document.getElementById('freeMargin').textContent = formatCurrency(order.free_margin);

    const marginLevel = parseFloat(order.margin_level || 0);
    document.getElementById('marginLevel').textContent = formatPercent(marginLevel);

    const marginBar = document.getElementById('marginLevelBar');
    marginBar.style.width = Math.min(marginLevel, 100) + '%';
    if (marginLevel < 100) {
        marginBar.className = 'progress-bar bg-danger';
    } else if (marginLevel < 200) {
        marginBar.className = 'progress-bar bg-warning';
    } else {
        marginBar.className = 'progress-bar bg-success';
    }
}

function modifyOrder() {
    alert('修改止损止盈功能开发中');
}

function partialClose() {
    alert('部分平仓功能开发中');
}

function closeOrder() {
    if (!confirm('确定要平仓吗？')) {
        return;
    }

    fetch('{{ route("front_api_order_close") }}', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: JSON.stringify({ order_number: orderNumber })
    })
    .then(res => res.json())
    .then(data => {
        if (data.success || data.code === 200) {
            alert('平仓成功');
            window.location.href = '{{ route("front_coreui_v2_page_order_open") }}';
        } else {
            alert(data.message || '平仓失败');
        }
    })
    .catch(err => {
        console.error('Close order error:', err);
        alert('网络错误，请稍后重试');
    });
}

function calculateDuration(openTime) {
    if (!openTime) return '-';

    const start = new Date(openTime);
    const now = new Date();
    const diff = now - start;

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

// Clear timer on page unload
window.addEventListener('beforeunload', function() {
    if (autoRefreshTimer) {
        clearInterval(autoRefreshTimer);
    }
});
</script>

<style>
.bg-gradient-primary {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
}
</style>
@endsection
