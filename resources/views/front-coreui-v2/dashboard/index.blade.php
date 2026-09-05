@extends('front-coreui-v2.layouts.app')

@section('title', '仪表盘')

@section('content')
<div class="container-fluid">
    <!-- Welcome Section -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card border-0 shadow-sm">
                <div class="card-body p-4">
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <h4 class="mb-2">欢迎回来，张三</h4>
                            <p class="text-muted mb-0">今天是 {{ date('Y年m月d日') }}，祝您交易愉快</p>
                        </div>
                        <div class="d-none d-md-block">
                            <button class="btn btn-primary">
                                <i class="fas fa-plus me-2"></i>快速入金
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Stats Cards -->
    <div class="row g-3 mb-4">
        <div class="col-md-3 col-sm-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="d-flex align-items-center justify-content-between mb-3">
                        <div class="rounded p-3" style="background: linear-gradient(135deg, #321fdb, #5856d6);">
                            <i class="fas fa-wallet text-white fs-4"></i>
                        </div>
                        <span class="badge bg-success">+8.5%</span>
                    </div>
                    <h6 class="text-muted mb-2">账户余额</h6>
                    <h3 class="mb-0 fw-bold">¥125,480.00</h3>
                </div>
            </div>
        </div>

        <div class="col-md-3 col-sm-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="d-flex align-items-center justify-content-between mb-3">
                        <div class="rounded p-3" style="background: linear-gradient(135deg, #2eb85c, #27a844);">
                            <i class="fas fa-chart-line text-white fs-4"></i>
                        </div>
                        <span class="badge bg-success">+15.2%</span>
                    </div>
                    <h6 class="text-muted mb-2">净值</h6>
                    <h3 class="mb-0 fw-bold">¥138,650.00</h3>
                </div>
            </div>
        </div>

        <div class="col-md-3 col-sm-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="d-flex align-items-center justify-content-between mb-3">
                        <div class="rounded p-3" style="background: linear-gradient(135deg, #f9b115, #e8a105);">
                            <i class="fas fa-hand-holding-usd text-white fs-4"></i>
                        </div>
                        <span class="badge bg-warning text-dark">持仓中</span>
                    </div>
                    <h6 class="text-muted mb-2">保证金</h6>
                    <h3 class="mb-0 fw-bold">¥45,200.00</h3>
                </div>
            </div>
        </div>

        <div class="col-md-3 col-sm-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="d-flex align-items-center justify-content-between mb-3">
                        <div class="rounded p-3" style="background: linear-gradient(135deg, #3399ff, #2688eb);">
                            <i class="fas fa-chart-pie text-white fs-4"></i>
                        </div>
                        <span class="badge bg-info">实时</span>
                    </div>
                    <h6 class="text-muted mb-2">浮动盈亏</h6>
                    <h3 class="mb-0 fw-bold text-success">+¥13,170.00</h3>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4">
        <!-- Left Column -->
        <div class="col-lg-8">
            <!-- Equity Chart -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white border-bottom">
                    <div class="d-flex align-items-center justify-content-between">
                        <h5 class="mb-0">账户权益趋势</h5>
                        <div class="btn-group btn-group-sm">
                            <button class="btn btn-primary active">7天</button>
                            <button class="btn btn-outline-primary">30天</button>
                            <button class="btn btn-outline-primary">90天</button>
                        </div>
                    </div>
                </div>
                <div class="card-body">
                    <canvas id="equityChart" height="80"></canvas>
                </div>
            </div>

            <!-- Open Positions -->
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white border-bottom">
                    <div class="d-flex align-items-center justify-content-between">
                        <h5 class="mb-0">持仓订单</h5>
                        <a href="{{ route('front_coreui_v2_page_order_open') }}" class="btn btn-sm btn-outline-primary">查看全部</a>
                    </div>
                </div>
                <div class="card-body p-0">
                    <!-- Desktop Table -->
                    <div class="table-responsive d-none d-md-block">
                        <table class="table table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>订单号</th>
                                    <th>品种</th>
                                    <th>类型</th>
                                    <th>手数</th>
                                    <th>开仓价</th>
                                    <th>当前价</th>
                                    <th>盈亏</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td class="fw-semibold">#1234567</td>
                                    <td>
                                        <span class="badge bg-primary">EURUSD</span>
                                    </td>
                                    <td><span class="text-success">买入</span></td>
                                    <td>1.5</td>
                                    <td>1.0850</td>
                                    <td>1.0920</td>
                                    <td class="text-success fw-bold">+¥1,050.00</td>
                                </tr>
                                <tr>
                                    <td class="fw-semibold">#1234568</td>
                                    <td>
                                        <span class="badge bg-warning text-dark">GOLD</span>
                                    </td>
                                    <td><span class="text-success">买入</span></td>
                                    <td>2.0</td>
                                    <td>1925.50</td>
                                    <td>1932.80</td>
                                    <td class="text-success fw-bold">+¥1,460.00</td>
                                </tr>
                                <tr>
                                    <td class="fw-semibold">#1234569</td>
                                    <td>
                                        <span class="badge bg-dark">GBPUSD</span>
                                    </td>
                                    <td><span class="text-danger">卖出</span></td>
                                    <td>1.0</td>
                                    <td>1.2650</td>
                                    <td>1.2680</td>
                                    <td class="text-danger fw-bold">-¥300.00</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- Mobile Card Stack -->
                    <div class="d-md-none">
                        <div class="list-group list-group-flush">
                            <div class="list-group-item">
                                <div class="d-flex justify-content-between align-items-start mb-2">
                                    <span class="fw-semibold">#1234567</span>
                                    <span class="badge bg-primary">EURUSD</span>
                                </div>
                                <div class="row g-2 small">
                                    <div class="col-6">
                                        <span class="text-muted">类型:</span> <span class="text-success">买入</span>
                                    </div>
                                    <div class="col-6">
                                        <span class="text-muted">手数:</span> 1.5
                                    </div>
                                    <div class="col-6">
                                        <span class="text-muted">开仓:</span> 1.0850
                                    </div>
                                    <div class="col-6">
                                        <span class="text-muted">当前:</span> 1.0920
                                    </div>
                                    <div class="col-12 mt-2">
                                        <span class="text-muted">盈亏:</span> <span class="text-success fw-bold">+¥1,050.00</span>
                                    </div>
                                </div>
                            </div>
                            <div class="list-group-item">
                                <div class="d-flex justify-content-between align-items-start mb-2">
                                    <span class="fw-semibold">#1234568</span>
                                    <span class="badge bg-warning text-dark">GOLD</span>
                                </div>
                                <div class="row g-2 small">
                                    <div class="col-6">
                                        <span class="text-muted">类型:</span> <span class="text-success">买入</span>
                                    </div>
                                    <div class="col-6">
                                        <span class="text-muted">手数:</span> 2.0
                                    </div>
                                    <div class="col-6">
                                        <span class="text-muted">开仓:</span> 1925.50
                                    </div>
                                    <div class="col-6">
                                        <span class="text-muted">当前:</span> 1932.80
                                    </div>
                                    <div class="col-12 mt-2">
                                        <span class="text-muted">盈亏:</span> <span class="text-success fw-bold">+¥1,460.00</span>
                                    </div>
                                </div>
                            </div>
                            <div class="list-group-item">
                                <div class="d-flex justify-content-between align-items-start mb-2">
                                    <span class="fw-semibold">#1234569</span>
                                    <span class="badge bg-dark">GBPUSD</span>
                                </div>
                                <div class="row g-2 small">
                                    <div class="col-6">
                                        <span class="text-muted">类型:</span> <span class="text-danger">卖出</span>
                                    </div>
                                    <div class="col-6">
                                        <span class="text-muted">手数:</span> 1.0
                                    </div>
                                    <div class="col-6">
                                        <span class="text-muted">开仓:</span> 1.2650
                                    </div>
                                    <div class="col-6">
                                        <span class="text-muted">当前:</span> 1.2680
                                    </div>
                                    <div class="col-12 mt-2">
                                        <span class="text-muted">盈亏:</span> <span class="text-danger fw-bold">-¥300.00</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Right Column -->
        <div class="col-lg-4">
            <!-- Quick Actions -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white border-bottom">
                    <h5 class="mb-0">快捷操作</h5>
                </div>
                <div class="card-body">
                    <div class="d-grid gap-2">
                        <a href="{{ route('front_coreui_v2_page_deposit') }}" class="btn btn-success">
                            <i class="fas fa-arrow-down me-2"></i>在线入金
                        </a>
                        <a href="{{ route('front_coreui_v2_page_withdraw') }}" class="btn btn-warning">
                            <i class="fas fa-arrow-up me-2"></i>在线出金
                        </a>
                        <a href="{{ route('front_coreui_v2_page_position_summary') }}" class="btn btn-info text-white">
                            <i class="fas fa-chart-pie me-2"></i>持仓汇总
                        </a>
                    </div>
                </div>
            </div>

            <!-- Account Info -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white border-bottom">
                    <h5 class="mb-0">账户信息</h5>
                </div>
                <div class="card-body">
                    <div class="mb-3 pb-3 border-bottom">
                        <div class="d-flex justify-content-between mb-2">
                            <span class="text-muted">MT4账号</span>
                            <span class="fw-semibold">12345678</span>
                        </div>
                        <div class="d-flex justify-content-between mb-2">
                            <span class="text-muted">账户类型</span>
                            <span class="badge bg-primary">标准账户</span>
                        </div>
                        <div class="d-flex justify-content-between">
                            <span class="text-muted">杠杆比例</span>
                            <span class="fw-semibold">1:100</span>
                        </div>
                    </div>
                    <div>
                        <div class="d-flex justify-content-between mb-2">
                            <span class="text-muted">保证金比例</span>
                            <span class="fw-semibold text-success">326.4%</span>
                        </div>
                        <div class="progress" style="height: 8px;">
                            <div class="progress-bar bg-success" style="width: 100%"></div>
                        </div>
                        <small class="text-muted">保证金充足</small>
                    </div>
                </div>
            </div>

            <!-- Recent Activities -->
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white border-bottom">
                    <h5 class="mb-0">最近动态</h5>
                </div>
                <div class="card-body p-0">
                    <div class="list-group list-group-flush">
                        <div class="list-group-item">
                            <div class="d-flex gap-3">
                                <div class="flex-shrink-0">
                                    <div class="rounded-circle bg-success bg-opacity-10 p-2" style="width: 40px; height: 40px;">
                                        <i class="fas fa-check text-success"></i>
                                    </div>
                                </div>
                                <div class="flex-grow-1">
                                    <p class="mb-1 fw-semibold small">入金成功</p>
                                    <p class="mb-1 text-muted small">金额: ¥50,000.00</p>
                                    <p class="mb-0 text-muted" style="font-size: 0.75rem;">2小时前</p>
                                </div>
                            </div>
                        </div>
                        <div class="list-group-item">
                            <div class="d-flex gap-3">
                                <div class="flex-shrink-0">
                                    <div class="rounded-circle bg-primary bg-opacity-10 p-2" style="width: 40px; height: 40px;">
                                        <i class="fas fa-check-circle text-primary"></i>
                                    </div>
                                </div>
                                <div class="flex-grow-1">
                                    <p class="mb-1 fw-semibold small">平仓成功</p>
                                    <p class="mb-1 text-muted small">EURUSD #1234550</p>
                                    <p class="mb-0 text-muted" style="font-size: 0.75rem;">5小时前</p>
                                </div>
                            </div>
                        </div>
                        <div class="list-group-item">
                            <div class="d-flex gap-3">
                                <div class="flex-shrink-0">
                                    <div class="rounded-circle bg-info bg-opacity-10 p-2" style="width: 40px; height: 40px;">
                                        <i class="fas fa-plus text-info"></i>
                                    </div>
                                </div>
                                <div class="flex-grow-1">
                                    <p class="mb-1 fw-semibold small">开仓成功</p>
                                    <p class="mb-1 text-muted small">GOLD #1234568</p>
                                    <p class="mb-0 text-muted" style="font-size: 0.75rem;">昨天</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    // 权益趋势图表
    const ctx = document.getElementById('equityChart').getContext('2d');
    new Chart(ctx, {
        type: 'line',
        data: {
            labels: ['周一', '周二', '周三', '周四', '周五', '周六', '周日'],
            datasets: [{
                label: '账户权益',
                data: [120000, 122500, 121800, 125600, 128400, 131200, 138650],
                borderColor: '#321fdb',
                backgroundColor: 'rgba(50, 31, 219, 0.1)',
                tension: 0.4,
                fill: true,
                pointRadius: 4,
                pointHoverRadius: 6
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: true,
            plugins: {
                legend: {
                    display: false
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            return '权益: ¥' + context.parsed.y.toLocaleString();
                        }
                    }
                }
            },
            scales: {
                y: {
                    beginAtZero: false,
                    ticks: {
                        callback: function(value) {
                            return '¥' + (value / 1000) + 'K';
                        }
                    }
                }
            }
        }
    });
});
</script>
@endpush
@endsection
