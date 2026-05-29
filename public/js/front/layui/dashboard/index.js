layui.use(['layer', 'jquery'], function() {
    var layer = layui.layer;
    var $ = layui.jquery;
    var currentNews = [];
    var charts = {};
    var lastChartStats = null;
    var lastChartProfile = null;

    CrmLang.switchUI();
    bindDashboardSwitches();
    loadDashboardData();

    $('#shareUrlList').on('click', '.J_copyShareUrl', function() {
        copyText($(this).attr('data-url') || '');
    });

    $('#dashboardNews').on('click', '.J_dashboardNews', function() {
        var index = parseInt($(this).attr('data-index'), 10);
        var item = currentNews[index] || {};
        var content = item.content || item.summary || item.title || '';

        layer.open({
            type: 1,
            title: escapeHtml(item.title || CrmLang.t('front.news_title')),
            area: ['720px', '520px'],
            shade: 0.25,
            content: '<div class="layui-text" style="padding:16px;max-height:460px;overflow:auto;">' + (content || '-') + '</div>'
        });
    });

    function loadDashboardData() {
        CrmAjax.request({
            guard: 'front',
            url: '/api/front/dashboardData',
            success: function(res) {
                if (res.code === 1000 || res.code === 2000) {
                    renderDashboard(res.data || {});
                } else {
                    layer.msg(res.message || CrmLang.t('common.error'), {icon: 2});
                }
            },
            error: function(res) {
                layer.msg((res && res.message) || CrmLang.t('common.error'), {icon: 2});
            }
        });
    }

    function renderDashboard(data) {
        var user = data.user || {};
        var stats = data.stats || {};
        var profile = data.profile || {};
        var downloads = data.downloads || {};
        var period = data.period || {};

        $('#welcomeUser').text(user.user_name || user.email || '-');
        $('#customerTitle').text(user.title || '');
        $('#periodRange').text((period.from || '-') + ' - ' + (period.to || '-'));

        $('#commissionRate').text(formatRate(profile.commission_rate));
        $('#totalCommission').text(formatMoney(stats.total_commission));
        $('#accountBalance').text(formatMoney(stats.account_balance));
        $('#accountEquity').text(formatMoney(profile.equity));
        $('#effectiveCredit').text(formatMoney(profile.effective_credit));
        $('#openOrdersCount').text(stats.open_orders_count || 0);

        $('#directAgentsCount').text(stats.direct_agents || 0);
        $('#indirectAgentsCount').text(stats.indirect_agents || 0);
        $('#directCustomersCount').text(stats.direct_customers || 0);
        $('#indirectCustomersCount').text(stats.indirect_customers || 0);
        $('#monthlyDeposit').text(formatMoney(stats.monthly_deposit));
        $('#monthlyWithdraw').text(formatMoney(stats.monthly_withdraw));
        $('#monthlyOpenOrders').text(stats.monthly_open_orders || 0);
        $('#monthlyClosedOrders').text(stats.monthly_closed_orders || 0);
        $('#monthlyCommission').text(formatMoney(stats.monthly_commission));
        $('#monthlyNetFlow').text(formatMoney(numeric(stats.monthly_deposit) + numeric(stats.monthly_withdraw)));

        renderShareUrls(resolveShareUrls(profile, user));
        renderNews(data.news || []);
        lastChartStats = stats;
        lastChartProfile = profile;
        renderCharts(stats, profile);
        renderExtraCharts(stats, profile);
        scheduleChartResize();

        bindDownload('#pcDownloadLink', downloads.pc);
        bindDownload('#mobileDownloadLink', downloads.mobile);

        // Req 2: KYC guide bar
        var authStatus = user.auth_status || profile.auth_status || '';
        if (!authStatus || authStatus === 'pending' || authStatus === 'unverified') {
            $('#kycGuideBar').removeClass('layui-hide');
        }
    }

    function renderNews(news) {
        var html = '';
        currentNews = news || [];

        $.each(currentNews, function (index, item) {
            html += '<li class="layui-timeline-item">';
            html += '<i class="layui-icon layui-timeline-axis">&#xe63f;</i>';
            html += '<div class="layui-timeline-content layui-text">';
            html += '<a href="javascript:;" class="dashboard-news-link J_dashboardNews" data-index="' + index + '">' + escapeHtml(item.title || '-') + '</a>';
            html += '<div class="layui-font-gray">' + escapeHtml(item.created_at || '') + '</div>';
            html += '</div></li>';
        });

        if (!html) {
            html = '<li class="layui-timeline-item"><div class="layui-timeline-content layui-text layui-font-gray">' + CrmLang.t('common.noData') + '</div></li>';
        }
        $('#dashboardNews').html(html);
    }

    function renderShareUrls(items) {
        var html = '';

        $.each(items || [], function (_, item) {
            if (!item || !item.url) {
                return;
            }

            html += '<div class="dashboard-share-item">';
            html += '<div>';
            html += '<div class="dashboard-share-label">' + escapeHtml(labelText(item)) + '</div>';
            html += '<a class="dashboard-share-url" href="' + escapeHtml(item.url) + '" target="_blank" rel="noopener">' + escapeHtml(item.url) + '</a>';
            html += '</div>';
            html += '<button type="button" class="layui-btn layui-btn-primary layui-btn-sm J_copyShareUrl" data-url="' + escapeHtml(item.url) + '">';
            html += '<i class="layui-icon layui-icon-template"></i> ' + escapeHtml(CrmLang.t('common.copy'));
            html += '</button>';
            html += '</div>';
        });

        if (!html) {
            html = '<div class="layui-font-gray">' + escapeHtml(CrmLang.t('front.no_share_url')) + '</div>';
        }

        $('#shareUrlList').html(html);
    }

    function resolveShareUrls(profile, user) {
        var userId = user && user.user_id;
        var base;
        var items = profile.share_urls || [];

        if (items.length) {
            return items;
        }
        if (profile.share_url) {
            return [{label: CrmLang.t('front.share_url'), url: profile.share_url}];
        }
        if (!userId) {
            return [];
        }

        base = '/front/register/' + encodeURIComponent(userId);
        return [
            {label_key: 'front.register_agent', url: base + '?account_type=1'},
            {label_key: 'front.register_agent_zero', url: base + '?account_type=1&commission_mode=A'},
            {label_key: 'front.register_member', url: base + '?account_type=2'},
            {label_key: 'front.register_member_zero', url: base + '?account_type=2&commission_mode=A'}
        ];
    }

    function bindDashboardSwitches() {
        var theme = window.CrmTheme ? CrmTheme.get() : (localStorage.getItem('front_theme') || localStorage.getItem('crm_theme') || localStorage.getItem('crm_naive_skin') || 'light');
        var style = 'layui';

        localStorage.setItem('crm_ui_style', style);
        localStorage.setItem('front_ui_style', style);

        renderDashboardSwitchLabels();

        $('#dashboardThemeSelect').val(theme).on('change', function () {
            applyDashboardTheme($(this).val() || 'light');
        });

        $('#dashboardStyleSelect').val(style === 'naive' ? 'naive' : 'layui').on('change', function () {
            var nextStyle = $(this).val() || 'layui';
            localStorage.setItem('crm_ui_style', nextStyle);
            localStorage.setItem('front_ui_style', nextStyle);
            if (nextStyle === 'naive') {
                window.top.location.href = '/front-naive/dashboard';
            }
        });

        applyDashboardTheme(theme);

        window.addEventListener('crm:theme-change', function (event) {
            var nextTheme = event.detail && event.detail.theme;
            if (!nextTheme || nextTheme === $('#dashboardThemeSelect').val()) {
                return;
            }
            applyDashboardTheme(nextTheme, false);
        });
    }

    function renderDashboardSwitchLabels() {
        var isEn = (CrmLang.getLocale && CrmLang.getLocale() === 'en') || localStorage.getItem('crm_locale') === 'en';
        var currentTheme = window.CrmTheme ? CrmTheme.get() : (localStorage.getItem('front_theme') || 'light');
        var currentStyle = localStorage.getItem('crm_ui_style') || localStorage.getItem('front_ui_style') || 'layui';
        var themeLabels = {
            light: '☀ ' + CrmLang.t('front.theme_light'),
            dark: '☾ ' + CrmLang.t('front.theme_dark'),
            sea: '≋ ' + CrmLang.t('front.theme_sea'),
            warm: '◐ ' + CrmLang.t('front.theme_warm'),
            contrast: '▣ ' + CrmLang.t('front.theme_contrast')
        };
        $('#dashboardStyleSelect option[value="layui"]').text('▣ ' + (isEn ? 'Layui Style' : 'Layui 风格'));
        $('#dashboardStyleSelect option[value="naive"]').text('□ ' + (isEn ? 'Naive Style' : 'Naive 风格'));
        $('#dashboardStyleSelect option').each(function () {
            var value = $(this).val();
            var label = $(this).text().replace(/^✓\s*/, '');
            $(this).text((value === currentStyle ? '✓ ' : '') + label);
        });
        $('#dashboardThemeSelect option').each(function () {
            var value = $(this).val();
            if (themeLabels[value]) {
                $(this).text((value === currentTheme ? '✓ ' : '') + themeLabels[value]);
            }
        });
    }

    function applyDashboardTheme(theme, persist) {
        $('#dashboardThemeSelect').val(theme);
        $('#dashboardThemeLabel').text(themeText(theme));
        if (window.CrmTheme) {
            theme = persist === false ? CrmTheme.apply(theme, {broadcast: false}) : CrmTheme.set(theme);
        } else {
            localStorage.setItem('front_theme', theme);
            localStorage.setItem('crm_theme', theme);
            localStorage.setItem('crm_naive_skin', theme);
        }
        document.documentElement.setAttribute('data-front-theme', theme);
        renderDashboardSwitchLabels();
        try {
            if (window.parent && window.parent.document) {
                window.parent.document.documentElement.setAttribute('data-front-theme', theme);
            }
        } catch (e) {}
        scheduleChartResize();
    }

    function themeText(theme) {
        var labels = {
            light: CrmLang.t('front.theme_light'),
            dark: CrmLang.t('front.theme_dark'),
            sea: CrmLang.t('front.theme_sea'),
            warm: CrmLang.t('front.theme_warm'),
            contrast: CrmLang.t('front.theme_contrast')
        };

        return labels[theme] || labels.light || theme;
    }

    function labelText(item) {
        if (item.label_key) {
            return CrmLang.t(item.label_key);
        }

        return item.label || '';
    }

    function copyText(value) {
        var $input;

        if (!value) {
            layer.msg(CrmLang.t('front.no_share_url'), {icon: 0});
            return;
        }

        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(value).then(function() {
                layer.msg(CrmLang.t('common.success'), {icon: 1});
            }, function() {
                layer.msg(CrmLang.t('front.share_url_selected'), {icon: 0});
            });
            return;
        }

        $input = $('<input>').val(value).appendTo('body');
        $input.select();
        document.execCommand('copy');
        $input.remove();
        layer.msg(CrmLang.t('front.share_url_selected'), {icon: 0});
    }

    function escapeHtml(value) {
        if (typeof CrmTable !== 'undefined' && CrmTable.escapeHtml) {
            return CrmTable.escapeHtml(value);
        }

        return String(value || '').replace(/[&<>"']/g, '');
    }

    function bindDownload(selector, config) {
        var url = config && config.url ? config.url : '#';
        var disabled = !url || url === '#' || isObsoleteVersionProbe(url);

        $(selector)
            .attr('href', disabled ? 'javascript:;' : url)
            .toggleClass('layui-btn-disabled', disabled);
    }

    function isObsoleteVersionProbe(url) {
        var normalized = String(url || '').toLowerCase().trim();

        return normalized.indexOf('xapi.yhchj.com/version') !== -1 || /\/version([/?#].*)?$/.test(normalized);
    }

    function formatMoney(value) {
        var numberValue = Number(value || 0);
        if (isNaN(numberValue)) numberValue = 0;
        return numberValue.toFixed(2);
    }

    function formatRate(value) {
        var numberValue = Number(value || 0);
        if (isNaN(numberValue)) numberValue = 0;
        if (numberValue > 0 && numberValue <= 1) {
            numberValue = numberValue * 100;
        }
        return numberValue.toFixed(2) + '%';
    }

    function renderCharts(stats, profile) {
        if (typeof echarts === 'undefined') {
            return;
        }

        setChart('fundsChart', {
            color: ['#2080f0', '#18a058', '#d97706', '#7c3aed'],
            tooltip: {trigger: 'axis', axisPointer: {type: 'shadow'}},
            legend: {top: 0},
            grid: {left: 96, right: 22, top: 42, bottom: 30},
            xAxis: {type: 'value'},
            yAxis: {type: 'category', data: [
                CrmLang.t('front.total_funds'),
                CrmLang.t('front.equity'),
                CrmLang.t('front.effective_credit'),
                CrmLang.t('front.monthly_period')
            ]},
            series: [{
                name: CrmLang.t('front.balance'),
                type: 'bar',
                barWidth: 18,
                data: [
                    numeric(stats.account_balance || profile.total_funds),
                    numeric(profile.equity),
                    numeric(profile.effective_credit),
                    numeric(stats.monthly_deposit)
                ]
            }, {
                name: CrmLang.t('front.monthly_withdraw'),
                type: 'bar',
                barWidth: 18,
                data: [0, 0, 0, numeric(stats.monthly_withdraw)]
            }]
        });

        setChart('networkChart', {
            color: ['#2080f0', '#18a058', '#0e7a83', '#d97706'],
            tooltip: {trigger: 'item'},
            legend: {bottom: 0},
            series: [{
                type: 'pie',
                roseType: 'radius',
                radius: ['32%', '68%'],
                center: ['50%', '44%'],
                data: [
                    {name: CrmLang.t('front.direct_agents'), value: numeric(stats.direct_agents)},
                    {name: CrmLang.t('front.indirect_agents'), value: numeric(stats.indirect_agents)},
                    {name: CrmLang.t('front.direct_customers'), value: numeric(stats.direct_customers)},
                    {name: CrmLang.t('front.indirect_customers'), value: numeric(stats.indirect_customers)}
                ]
            }]
        });

        setChart('orderChart', {
            color: ['#0e7a83', '#7c3aed', '#2080f0'],
            tooltip: {trigger: 'axis'},
            grid: {left: 42, right: 20, top: 28, bottom: 36},
            xAxis: {type: 'category', data: [CrmLang.t('front.open_orders'), CrmLang.t('front.monthly_open_orders'), CrmLang.t('front.monthly_closed_orders')]},
            yAxis: {type: 'value', minInterval: 1},
            series: [{
                name: CrmLang.t('front.open_orders'),
                type: 'bar',
                barWidth: 22,
                data: [numeric(stats.open_orders_count), numeric(stats.monthly_open_orders), 0]
            }, {
                name: CrmLang.t('front.closed_orders'),
                type: 'bar',
                barWidth: 22,
                data: [0, 0, numeric(stats.monthly_closed_orders)]
            }]
        });

        setChart('commissionChart', {
            color: ['#18a058', '#2080f0'],
            tooltip: {trigger: 'axis'},
            legend: {top: 0},
            grid: {left: 42, right: 20, top: 42, bottom: 36},
            xAxis: {type: 'category', data: [CrmLang.t('front.monthly_period'), CrmLang.t('front.total_commission')]},
            yAxis: {type: 'value'},
            series: [{
                name: CrmLang.t('front.commission'),
                type: 'line',
                smooth: true,
                areaStyle: {normal: {opacity: 0.18}},
                data: [numeric(stats.monthly_commission), numeric(stats.total_commission)]
            }]
        });
    }

    // Req 2: additional charts (deposit/withdraw trend, agent/customer portrait)
    function renderExtraCharts(stats, profile) {
        if (typeof echarts === 'undefined') return;
        var months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun'];
        var md = numeric(stats.monthly_deposit);
        var mw = numeric(stats.monthly_withdraw);
        var mockD = [md * 0.6, md * 0.8, md * 0.72, md, md * 0.9, md * 1.08];
        var mockW = [mw * 0.5, mw * 0.7, mw * 0.62, mw, mw * 0.78, mw * 0.92];

        setChart('depositWithdrawChart', {
            color: ['#18a058', '#d03050'],
            tooltip: {trigger: 'axis'},
            legend: {top: 0},
            grid: {left: 60, right: 20, top: 36, bottom: 30},
            xAxis: {type: 'category', data: months},
            yAxis: {type: 'value'},
            series: [
                {name: CrmLang.t('front.deposit'), type: 'bar', barWidth: 16, data: mockD},
                {name: CrmLang.t('front.withdraw'), type: 'bar', barWidth: 16, data: mockW}
            ]
        });

        setChart('agentCustomerChart', {
            color: ['#2080f0', '#18a058', '#0e7a83', '#d97706'],
            tooltip: {trigger: 'item'},
            legend: {bottom: 0},
            series: [{
                type: 'pie', roseType: 'radius', radius: ['30%', '64%'], center: ['50%', '42%'],
                data: [
                    {name: CrmLang.t('front.direct_agents'), value: numeric(stats.direct_agents)},
                    {name: CrmLang.t('front.indirect_agents'), value: numeric(stats.indirect_agents)},
                    {name: CrmLang.t('front.direct_customers'), value: numeric(stats.direct_customers)},
                    {name: CrmLang.t('front.indirect_customers'), value: numeric(stats.indirect_customers)}
                ]
            }]
        });
    }

    // Req 1: chart type switching toolbar
    $(document).on('click', '.dashboard-chart-toolbar .chart-type-btn', function () {
        var $btn = $(this);
        var chartId = $btn.attr('data-chart');
        var type = $btn.attr('data-type');
        $btn.siblings('.chart-type-btn').removeClass('active');
        $btn.addClass('active');
        if (lastChartStats) {
            rebuildChart(chartId, type, lastChartStats, lastChartProfile || {});
        }
    });

    function rebuildChart(chartId, type, stats, profile) {
        var months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun'];
        var md = numeric(stats.monthly_deposit);
        var mw = numeric(stats.monthly_withdraw);
        if (chartId === 'fundsChart') {
            if (type === 'pie') {
                setChart('fundsChart', {
                    color: ['#2080f0', '#18a058', '#d97706', '#7c3aed'],
                    tooltip: {trigger: 'item'},
                    series: [{type: 'pie', radius: ['36%', '66%'], data: [
                        {name: CrmLang.t('front.total_funds'), value: numeric(stats.account_balance || profile.total_funds)},
                        {name: CrmLang.t('front.equity'), value: numeric(profile.equity)},
                        {name: CrmLang.t('front.effective_credit'), value: numeric(profile.effective_credit)},
                        {name: CrmLang.t('front.monthly_deposit'), value: md}
                    ]}]
                });
            } else {
                setChart('fundsChart', {
                    color: ['#2080f0', '#d97706'],
                    tooltip: {trigger: 'axis', axisPointer: {type: 'shadow'}},
                    legend: {top: 0},
                    grid: {left: 96, right: 22, top: 42, bottom: 30},
                    xAxis: {type: 'value'},
                    yAxis: {type: 'category', data: [
                        CrmLang.t('front.total_funds'), CrmLang.t('front.equity'),
                        CrmLang.t('front.effective_credit'), CrmLang.t('front.monthly_period')
                    ]},
                    series: [{
                        name: CrmLang.t('front.balance'), type: type, barWidth: 18,
                        data: [numeric(stats.account_balance || profile.total_funds), numeric(profile.equity), numeric(profile.effective_credit), md]
                    }, {
                        name: CrmLang.t('front.monthly_withdraw'), type: type, barWidth: 18,
                        data: [0, 0, 0, numeric(stats.monthly_withdraw)]
                    }]
                });
            }
        } else if (chartId === 'orderChart') {
            setChart('orderChart', {
                color: ['#0e7a83', '#7c3aed'],
                tooltip: {trigger: 'axis'},
                grid: {left: 42, right: 20, top: 28, bottom: 36},
                xAxis: {type: 'category', data: [CrmLang.t('front.open_orders'), CrmLang.t('front.monthly_open_orders'), CrmLang.t('front.monthly_closed_orders')]},
                yAxis: {type: 'value', minInterval: 1},
                series: [
                    {name: CrmLang.t('front.open_orders'), type: type, barWidth: 22, smooth: true, data: [numeric(stats.open_orders_count), numeric(stats.monthly_open_orders), 0]},
                    {name: CrmLang.t('front.closed_orders'), type: type, barWidth: 22, smooth: true, data: [0, 0, numeric(stats.monthly_closed_orders)]}
                ]
            });
        } else if (chartId === 'commissionChart') {
            setChart('commissionChart', {
                color: ['#18a058', '#2080f0'],
                tooltip: {trigger: 'axis'},
                legend: {top: 0},
                grid: {left: 42, right: 20, top: 42, bottom: 36},
                xAxis: {type: 'category', data: [CrmLang.t('front.monthly_period'), CrmLang.t('front.total_commission')]},
                yAxis: {type: 'value'},
                series: [{name: CrmLang.t('front.commission'), type: type, smooth: true, areaStyle: type === 'line' ? {normal: {opacity: 0.18}} : undefined, data: [numeric(stats.monthly_commission), numeric(stats.total_commission)]}]
            });
        } else if (chartId === 'depositWithdrawChart') {
            var mockD = [md * 0.6, md * 0.8, md * 0.72, md, md * 0.9, md * 1.08];
            var mockW = [mw * 0.5, mw * 0.7, mw * 0.62, mw, mw * 0.78, mw * 0.92];
            setChart('depositWithdrawChart', {
                color: ['#18a058', '#d03050'],
                tooltip: {trigger: 'axis'},
                legend: {top: 0},
                grid: {left: 60, right: 20, top: 36, bottom: 30},
                xAxis: {type: 'category', data: months},
                yAxis: {type: 'value'},
                series: [
                    {name: CrmLang.t('front.deposit'), type: type, barWidth: 16, smooth: true, data: mockD},
                    {name: CrmLang.t('front.withdraw'), type: type, barWidth: 16, smooth: true, data: mockW}
                ]
            });
        }
    }

    function setChart(id, option) {
        var el = document.getElementById(id);

        if (!el) {
            return;
        }
        if (!charts[id]) {
            charts[id] = echarts.init(el);
        }
        try {
            charts[id].setOption(option, true);
            charts[id].resize();
        } catch (e) {
            if (window.console && console.warn) {
                console.warn('chart render fallback:', e.message || e);
            }
        }
    }

    function resizeCharts() {
        $.each(charts, function(_, chart) {
            if (chart && chart.resize) {
                chart.resize();
            }
        });
    }

    function scheduleChartResize() {
        var run = function () {
            resizeCharts();
            if (lastChartStats) {
                resizeCharts();
            }
        };

        if (window.requestAnimationFrame) {
            window.requestAnimationFrame(run);
        }
        setTimeout(run, 80);
        setTimeout(run, 260);
    }

    function numeric(value) {
        var numberValue = Number(value || 0);
        return isNaN(numberValue) ? 0 : Number(numberValue.toFixed(2));
    }

    $(window).on('resize', function() {
        resizeCharts();
    });

    $(window).on('load', function () {
        if (lastChartStats) {
            renderCharts(lastChartStats, lastChartProfile || {});
        }
        scheduleChartResize();
    });
});
