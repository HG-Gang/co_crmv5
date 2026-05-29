layui.use(['layer', 'jquery'], function() {
    var layer = layui.layer;
    var $ = layui.jquery;
    var currentNews = [];
    var charts = {};
    var lastChartStats = null;
    var lastChartProfile = null;
    var chartTypes = {
        fundsChart: 'bar',
        networkChart: 'pie',
        orderChart: 'bar',
        commissionChart: 'line'
    };

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
        $('#identityGuideBtn').toggleClass('layui-hide', Number(profile.auth_status || user.auth_status || 0) === 1);

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
        scheduleChartResize();

        bindDownload('#pcDownloadLink', downloads.pc);
        bindDownload('#mobileDownloadLink', downloads.mobile);
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
        renderChartSelectors();

        $('.dashboard-chart-type').off('change.dashboardChart').on('change.dashboardChart', function () {
            chartTypes[$(this).attr('data-chart-target')] = $(this).val() || 'bar';
            if (lastChartStats && lastChartProfile) {
                renderCharts(lastChartStats, lastChartProfile);
            }
        });

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
            light: '◌ ' + CrmLang.t('front.theme_light_fresh'),
            dark: '◑ ' + CrmLang.t('front.theme_dark_fresh'),
            sea: '≋ ' + CrmLang.t('front.theme_sea_fresh'),
            warm: '◒ ' + CrmLang.t('front.theme_warm_fresh'),
            contrast: '◇ ' + CrmLang.t('front.theme_contrast_fresh')
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
        renderChartSelectors();
    }

    function applyDashboardTheme(theme, persist) {
        $('#dashboardThemeSelect').val(theme);
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
            light: CrmLang.t('front.theme_light_fresh'),
            dark: CrmLang.t('front.theme_dark_fresh'),
            sea: CrmLang.t('front.theme_sea_fresh'),
            warm: CrmLang.t('front.theme_warm_fresh'),
            contrast: CrmLang.t('front.theme_contrast_fresh')
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

    function renderChartSelectors() {
        var options = [
            ['bar', CrmLang.t('front.chart_bar')],
            ['line', CrmLang.t('front.chart_line')],
            ['area', CrmLang.t('front.chart_area')],
            ['pie', CrmLang.t('front.chart_pie')]
        ];
        $('.dashboard-chart-type').each(function () {
            var $select = $(this);
            var target = $select.attr('data-chart-target');
            var current = chartTypes[target] || $select.val() || 'bar';
            var html = '';
            $.each(options, function (_, item) {
                html += '<option value="' + item[0] + '"' + (item[0] === current ? ' selected' : '') + '>' + escapeHtml(item[1]) + '</option>';
            });
            $select.html(html);
        });
    }

    function chartSeries(name, values, type) {
        if (type === 'pie') {
            return [{
                name: name,
                type: 'pie',
                radius: ['30%', '64%'],
                center: ['50%', '52%'],
                data: values.map(function (item) {
                    return {name: item.name, value: item.value};
                })
            }];
        }
        return [{
            name: name,
            type: type === 'area' ? 'line' : type,
            smooth: type !== 'bar',
            areaStyle: type === 'area' ? {normal: {opacity: 0.18}} : null,
            barWidth: type === 'bar' ? 18 : null,
            data: values.map(function (item) {
                return item.value;
            })
        }];
    }

    function chartOption(name, values, type, colors) {
        var option = {color: colors, tooltip: {trigger: 'item'}, legend: {bottom: 0}};
        if (type !== 'pie') {
            option.tooltip = {trigger: 'axis', axisPointer: {type: type === 'bar' ? 'shadow' : 'line'}};
            option.grid = {left: 56, right: 22, top: 38, bottom: 36};
            option.xAxis = {type: 'category', data: values.map(function (item) { return item.name; })};
            option.yAxis = {type: 'value'};
            option.legend = null;
        }
        option.series = chartSeries(name, values, type);
        return option;
    }

    function renderCharts(stats, profile) {
        if (typeof echarts === 'undefined') {
            return;
        }

        var funds = [
            {name: CrmLang.t('front.total_funds'), value: numeric(stats.account_balance || profile.total_funds)},
            {name: CrmLang.t('front.equity'), value: numeric(profile.equity)},
            {name: CrmLang.t('front.effective_credit'), value: numeric(profile.effective_credit)},
            {name: CrmLang.t('front.monthly_deposit'), value: numeric(stats.monthly_deposit)},
            {name: CrmLang.t('front.monthly_withdraw'), value: numeric(stats.monthly_withdraw)}
        ];
        setChart('fundsChart', chartOption(CrmLang.t('front.funds_chart'), funds, chartTypes.fundsChart, ['#2080f0', '#18a058', '#d97706', '#7c3aed', '#ef4444']));

        var network = [
            {name: CrmLang.t('front.direct_agents'), value: numeric(stats.direct_agents)},
            {name: CrmLang.t('front.indirect_agents'), value: numeric(stats.indirect_agents)},
            {name: CrmLang.t('front.direct_customers'), value: numeric(stats.direct_customers)},
            {name: CrmLang.t('front.indirect_customers'), value: numeric(stats.indirect_customers)}
        ];
        setChart('networkChart', chartOption(CrmLang.t('front.network_chart'), network, chartTypes.networkChart, ['#2080f0', '#18a058', '#0e7a83', '#d97706']));

        var orders = [
            {name: CrmLang.t('front.open_orders'), value: numeric(stats.open_orders_count)},
            {name: CrmLang.t('front.monthly_open_orders'), value: numeric(stats.monthly_open_orders)},
            {name: CrmLang.t('front.monthly_closed_orders'), value: numeric(stats.monthly_closed_orders)}
        ];
        setChart('orderChart', chartOption(CrmLang.t('front.order_chart'), orders, chartTypes.orderChart, ['#0e7a83', '#7c3aed', '#2080f0']));

        var commission = [
            {name: CrmLang.t('front.monthly_commission'), value: numeric(stats.monthly_commission)},
            {name: CrmLang.t('front.total_commission'), value: numeric(stats.total_commission)}
        ];
        setChart('commissionChart', chartOption(CrmLang.t('front.commission_chart'), commission, chartTypes.commissionChart, ['#18a058', '#2080f0']));
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
