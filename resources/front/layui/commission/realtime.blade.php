@extends('front_layui::layouts.app')

@section('title', __('front.realtime_commission'))
@section('breadcrumb', __('breadcrumb.front_commission_rt'))

@section('content')
@include('front_layui::partials.module-page', [
    'pageClass' => 'commission-module commission-realtime-module',
    'titleKey' => 'front.realtime_commission',
    'descriptionKey' => 'front.realtime_commission_desc',
    'api' => '/api/front/commissionRealTime',
    'listKey' => 'list',
    'showSummary' => true,
    'showChartCollapse' => true,
    'filters' => [
        ['name' => 'userId', 'label' => 'front.user_id', 'type' => 'text'],
        ['name' => 'orderId', 'label' => 'front.order_no', 'type' => 'text'],
        ['name' => 'startdate', 'label' => 'front.date_from', 'type' => 'date'],
        ['name' => 'enddate', 'label' => 'front.date_to', 'type' => 'date'],
    ],
    'summaryFields' => [
        ['key' => 'total_commission', 'label' => 'front.total_commission'],
        ['key' => 'total_volume', 'label' => 'front.total_volume'],
        ['key' => 'profit_gain', 'label' => 'front.profit_gain'],
        ['key' => 'profit_loss', 'label' => 'front.profit_loss'],
        ['key' => 'profit_net', 'label' => 'front.profit_net'],
    ],
    'columns' => [
        ['key' => 'ticket', 'label' => 'front.ticket', 'action' => 'showOrderInfo', 'linkClass' => 'module-link-order'],
        ['key' => 'login', 'label' => 'front.user_id', 'action' => 'showUserInfo', 'linkClass' => 'module-link-user'],
        ['key' => 'volume_lots', 'label' => 'front.volume', 'format' => 'lots'],
        ['key' => 'profit_gain', 'label' => 'front.profit_gain', 'format' => 'money'],
        ['key' => 'profit_loss', 'label' => 'front.profit_loss', 'format' => 'money'],
        ['key' => 'profit_net', 'label' => 'front.profit_net', 'format' => 'money'],
        ['key' => 'comment', 'label' => 'common.remark'],
        ['key' => 'modify_time', 'label' => 'common.updated_at'],
    ],
])
@endsection

@section('scripts')
{{-- Req 15: module-page with performance optimization --}}
<script src="{{ asset('/js/front/layui/module-page.js') }}?v=2026052918"></script>
<script>
// Req 16: collapsible chart toggle + Req 15: lazy chart init
(function () {
    var chartInstance = null;
    var toggleBtn = document.getElementById('moduleChartToggle');
    var chartBody = document.getElementById('moduleChartBody');
    if (!toggleBtn || !chartBody) return;

    toggleBtn.addEventListener('click', function () {
        var isOpen = chartBody.classList.contains('show');
        if (isOpen) {
            chartBody.classList.remove('show');
            toggleBtn.classList.remove('open');
        } else {
            chartBody.classList.add('show');
            toggleBtn.classList.add('open');
            if (!chartInstance && typeof echarts !== 'undefined') {
                var el = document.getElementById('moduleStatsChart');
                if (el) {
                    chartInstance = echarts.init(el);
                    chartInstance.setOption({
                        color: ['#18a058', '#d03050', '#2080f0'],
                        tooltip: { trigger: 'axis' },
                        legend: { top: 0 },
                        grid: { left: 60, right: 20, top: 36, bottom: 30 },
                        xAxis: { type: 'category', data: ['Mon', 'Tue', 'Wed', 'Thu', 'Fri'] },
                        yAxis: { type: 'value' },
                        series: [
                            { name: 'Gain', type: 'bar', barWidth: 14, data: [320, 280, 410, 390, 520] },
                            { name: 'Loss', type: 'bar', barWidth: 14, data: [-110, -80, -150, -120, -200] },
                            { name: 'Net', type: 'line', smooth: true, data: [210, 200, 260, 270, 320] }
                        ]
                    });
                    setTimeout(function () { chartInstance.resize(); }, 100);
                }
            } else if (chartInstance) {
                chartInstance.resize();
            }
        }
    });

    window.addEventListener('resize', function () {
        if (chartInstance) chartInstance.resize();
    });
})();
</script>
@endsection
