/**
 * Account info charts (Req 6).
 * Renders deposit/withdraw, commission, order and agent/customer charts.
 */
layui.use(['jquery'], function () {
    var $ = layui.jquery;
    if (typeof echarts === 'undefined') return;

    var charts = {};
    var months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun'];
    var chartConfigs = {};

    function t(key) { return window.CrmLang && CrmLang.t ? CrmLang.t(key) : key; }
    function n(v) { var x = Number(v || 0); return isNaN(x) ? 0 : Math.round(x * 100) / 100; }

    var mockDeposit = [12000, 18000, 15600, 22000, 19800, 23800];
    var mockWithdraw = [8000, 12600, 10200, 14200, 9800, 12680];
    var mockComm = [2800, 3100, 3600, 4200, 3800, 5120];
    var mockCommRate = [10, 11, 11.5, 12, 11.8, 12.2];
    var mockOpenOrders = [18, 22, 30, 26, 34, 28];
    var mockClosedOrders = [12, 16, 20, 22, 18, 24];
    var mockAgents = [{ name: t('front.direct_agents'), value: 38 }, { name: t('front.indirect_agents'), value: 126 }, { name: t('front.direct_customers'), value: 612 }, { name: t('front.indirect_customers'), value: 2840 }];

    function initChart(id) {
        var el = document.getElementById(id);
        if (!el) return null;
        if (!charts[id]) charts[id] = echarts.init(el);
        return charts[id];
    }

    function renderDepositChart(type) {
        type = type || 'bar';
        var chart = initChart('acctDepositChart');
        if (!chart) return;
        chartConfigs.acctDepositChart = type;
        if (type === 'pie') {
            chart.setOption({
                color: ['#18a058', '#d03050'],
                tooltip: { trigger: 'item' },
                legend: { bottom: 0 },
                series: [{ type: 'pie', radius: ['34%', '64%'], center: ['50%', '42%'], data: [
                    { name: t('front.deposit'), value: mockDeposit.reduce(function (a, b) { return a + b; }, 0) },
                    { name: t('front.withdraw'), value: mockWithdraw.reduce(function (a, b) { return a + b; }, 0) }
                ] }]
            }, true);
            return;
        }
        chart.setOption({
            color: ['#18a058', '#d03050'],
            tooltip: { trigger: 'axis' },
            legend: { top: 0 },
            grid: { left: 60, right: 20, top: 36, bottom: 30 },
            xAxis: { type: 'category', data: months },
            yAxis: { type: 'value' },
            series: [
                { name: t('front.deposit'), type: type, barWidth: 16, smooth: true, data: mockDeposit },
                { name: t('front.withdraw'), type: type, barWidth: 16, smooth: true, data: mockWithdraw }
            ]
        }, true);
    }

    function renderCommChart(type) {
        type = type || 'line';
        var chart = initChart('acctCommChart');
        if (!chart) return;
        chartConfigs.acctCommChart = type;
        if (type === 'pie') {
            chart.setOption({
                color: ['#2080f0', '#f0a020'],
                tooltip: { trigger: 'item' },
                legend: { bottom: 0 },
                series: [{ type: 'pie', radius: ['34%', '64%'], center: ['50%', '42%'], data: [
                    { name: t('front.commission'), value: mockComm.reduce(function (a, b) { return a + b; }, 0) },
                    { name: t('front.commission_rate'), value: mockCommRate.reduce(function (a, b) { return a + b; }, 0) }
                ] }]
            }, true);
            return;
        }
        chart.setOption({
            color: ['#2080f0', '#f0a020'],
            tooltip: { trigger: 'axis' },
            legend: { top: 0 },
            grid: { left: 60, right: 30, top: 36, bottom: 30 },
            xAxis: { type: 'category', data: months },
            yAxis: [{ type: 'value', name: t('front.commission') }, { type: 'value', name: '%', max: 20 }],
            series: [
                { name: t('front.commission'), type: type, smooth: true, areaStyle: type === 'line' ? { opacity: 0.15 } : undefined, data: mockComm },
                { name: t('front.commission_rate'), type: 'line', smooth: true, yAxisIndex: 1, data: mockCommRate }
            ]
        }, true);
    }

    function renderOrderChart(type) {
        type = type || 'bar';
        var chart = initChart('acctOrderChart');
        if (!chart) return;
        chartConfigs.acctOrderChart = type;
        if (type === 'pie') {
            var total = mockOpenOrders.reduce(function (a, b) { return a + b; }, 0);
            var closedTotal = mockClosedOrders.reduce(function (a, b) { return a + b; }, 0);
            chart.setOption({
                color: ['#0e7a83', '#7c3aed'],
                tooltip: { trigger: 'item' },
                series: [{ type: 'pie', radius: ['36%', '66%'], data: [{ name: t('front.open_orders'), value: total }, { name: t('front.closed_orders'), value: closedTotal }] }]
            }, true);
        } else {
            chart.setOption({
                color: ['#0e7a83', '#7c3aed'],
                tooltip: { trigger: 'axis' },
                legend: { top: 0 },
                grid: { left: 40, right: 20, top: 36, bottom: 30 },
                xAxis: { type: 'category', data: months },
                yAxis: { type: 'value', minInterval: 1 },
                series: [
                    { name: t('front.open_orders'), type: type, barWidth: 16, smooth: true, data: mockOpenOrders },
                    { name: t('front.closed_orders'), type: type, barWidth: 16, smooth: true, data: mockClosedOrders }
                ]
            }, true);
        }
    }

    function renderAgentChart() {
        var chart = initChart('acctAgentChart');
        if (!chart) return;
        chart.setOption({
            color: ['#2080f0', '#18a058', '#0e7a83', '#d97706'],
            tooltip: { trigger: 'item' },
            legend: { bottom: 0 },
            series: [{ type: 'pie', roseType: 'radius', radius: ['28%', '64%'], center: ['50%', '42%'], data: mockAgents }]
        }, true);
    }

    renderDepositChart();
    renderCommChart();
    renderOrderChart();
    renderAgentChart();

    $(document).on('click', '.account-chart-toolbar .chart-type-btn', function () {
        var $btn = $(this);
        var chartId = $btn.attr('data-chart');
        var type = $btn.attr('data-type');
        $btn.siblings('.chart-type-btn').removeClass('active');
        $btn.addClass('active');
        if (chartId === 'acctDepositChart') renderDepositChart(type);
        else if (chartId === 'acctCommChart') renderCommChart(type);
        else if (chartId === 'acctOrderChart') renderOrderChart(type);
    });

    $(window).on('resize', function () {
        $.each(charts, function (_, c) { if (c && c.resize) c.resize(); });
    });

    setTimeout(function () {
        $.each(charts, function (_, c) { if (c && c.resize) c.resize(); });
    }, 200);
});
