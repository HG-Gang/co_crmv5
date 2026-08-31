// Created by PhpStorm.
// Project name co_crmv5.
// User: Huang Gang
// Date: 2026/08/28
// Time: 01:42
layui.use(['form', 'layer', 'jquery'], function () {
    var form = layui.form;
    var layer = layui.layer;
    var $ = layui.jquery;
    var $form = $('#legacyCustomerDetailForm');

    function addIfChanged(payload, fieldName, selector) {
        var $field = $(selector);
        var value = String($field.val() == null ? '' : $field.val()).trim();
        var initial = String($field.data('initial') == null ? '' : $field.data('initial')).trim();
        if (value !== initial) payload[fieldName] = value;
    }

    function checkboxValue(name) {
        return $form.find('[name="' + name + '"]').prop('checked') ? '1' : '0';
    }

    form.on('submit(legacyCustomerDetailSubmit)', function () {
        var payload = {userId: String($form.find('[name="userId"]').val())};
        addIfChanged(payload, 'username', '#legacyCustomerName');
        addIfChanged(payload, 'useremail', '#legacyCustomerEmail');
        addIfChanged(payload, 'userphoneNo', '#legacyCustomerPhone');
        addIfChanged(payload, 'userIdcardNo', '#legacyCustomerIdCard');
        addIfChanged(payload, 'userparentId', '#legacyCustomerParent');
        addIfChanged(payload, 'userremark', '#legacyCustomerRemark');

        var password = String($('#legacyCustomerPassword').val() || '').trim();
        if (password && password !== '********') payload.password = password;

        var gender = String($form.find('[name="sex"]:checked').val() || '');
        var initialGender = String($form.find('[data-initial-gender]').data('initial-gender'));
        if (gender !== initialGender) payload.sex = gender;

        var giftAllowed = String($form.find('[name="gift_allowed"]:checked').val() || '');
        var initialGift = String($form.find('[data-initial-gift]').data('initial-gift'));
        if (giftAllowed !== initialGift) payload.gift_allowed = giftAllowed;

        ['enablereadonly', 'isoutmoney', 'isallowmoney'].forEach(function (fieldName) {
            var $field = $form.find('[name="' + fieldName + '"]');
            var value = checkboxValue(fieldName);
            if (value !== String($field.data('initial'))) payload[fieldName] = value;
        });

        var $selectedGroup = $('#legacyCustomerGroup option:selected');
        var selectedGroupName = String($selectedGroup.data('group-name') || '').trim();
        var initialGroupName = String($('#legacyCustomerGroup').data('initial-name') || '').trim();
        if (selectedGroupName !== initialGroupName) {
            payload.usergrpId = String($selectedGroup.val() || '');
            payload.usergrpName = selectedGroupName;
            payload.is_enc = String($selectedGroup.data('is-ecn') || 0);
        }

        if (Number($form.data('bank-status')) === 2) {
            addIfChanged(payload, 'bank_no', '#legacyCustomerBankNo');
            addIfChanged(payload, 'bank_class', '#legacyCustomerBankName');
            addIfChanged(payload, 'bank_info', '#legacyCustomerBankAddr');
        }

        if (Object.keys(payload).length === 1) {
            layer.msg('资料没有变化');
            return false;
        }

        CrmAjax.request({
            guard: 'admin',
            url: $form.data('save-endpoint'),
            method: 'POST',
            data: {data: payload, usergrpName: payload.usergrpName, is_enc: payload.is_enc},
            success: function (response) {
                if ([1000, 1001, 1002].indexOf(Number(response && response.code)) !== -1) {
                    layer.msg(response.message || '客户资料已保存', {icon: 1}, function () {
                        window.location.reload();
                    });
                    return;
                }
                layer.msg((response && response.message) || '客户资料保存失败', {icon: 2});
            }
        });

        return false;
    });

    $('#legacyCustomerDetailBack').on('click', function () {
        window.history.back();
    });

    $('#legacyCustomerPassword').on('keydown', function (event) {
        if ((event.key === 'Backspace' || event.key === 'Delete') && this.value === '********') {
            this.value = '';
        }
    });

    form.render();

    // ================================================================
    // 需求 13：客户数据统计（出入金、返佣金额/比例、开关订单数、近 7/15/30 天盈亏 + 图表）
    // 所有数字来自 /api/admin/customerStatistics 的真实 DB 查询，前端只负责展示与图表切换。
    // 请求地址由 Blade 的 data-customer-statistics-endpoint 注入，脚本不拼接路由名。
    // ================================================================
    (function initCustomerStatistics() {
        var $block = $('#customerStatisticsBlock');
        var $root = $('[data-legacy-customer-detail]');
        var chartInstances = {};
        var chartTypes = {};
        var profitWindow = 30;
        var statistics = null;
        var resizePending = false;

        if (!$block.length || !$root.length) {
            return;
        }

        /**
         * 取当前语言文案，CrmLang 缺失时回退到 key，避免详情页因语言包未就绪而报错。
         *
         * @param {string} key 语言 key。
         * @returns {string} 已翻译文案。
         */
        function t(key) {
            return window.CrmLang && CrmLang.t ? CrmLang.t(key) : key;
        }

        /**
         * 读取当前激活的图表类型初值。
         *
         * @returns {void}
         */
        function readInitialChartTypes() {
            $block.find('.crm-chart-type[data-chart-target].is-active').each(function () {
                chartTypes[$(this).data('chart-target')] = String($(this).data('chart-type') || 'line');
            });
        }

        /**
         * 把统计数值写进统计条目。
         *
         * @param {Object} payload 后端返回的统计对象。
         * @returns {void}
         */
        function renderStatValues(payload) {
            $block.find('[data-customer-stat]').each(function () {
                var key = $(this).data('customer-stat');
                var value = payload[key];

                if (value === undefined || value === null || value === '') {
                    return;
                }

                $(this).text(String(value));
            });
        }

        /**
         * 按当前天数窗口裁剪盈亏序列。
         *
         * 逻辑说明：
         * - 后端一次返回 30 天的完整按天序列，7/15 天窗口在前端直接取末尾切片，
         *   切换窗口不需要再打一次接口。
         *
         * @returns {{labels: Array<string>, values: Array<number>}} 当前窗口的图表数据。
         */
        function currentProfitSeries() {
            var series = (statistics && statistics.profit_series) || {labels: [], values: []};
            var labels = series.labels || [];
            var values = series.values || [];
            var start = Math.max(0, labels.length - profitWindow);

            return {
                labels: labels.slice(start),
                values: values.slice(start).map(function (value) {
                    return Number(value || 0);
                })
            };
        }

        /**
         * 生成 ECharts 配置。
         *
         * @param {string} target 图表容器 DOM id。
         * @param {string} type 图表类型，支持 bar/line/area/pie。
         * @returns {Object} ECharts option。
         */
        function chartOption(target, type) {
            var series;

            if (target === 'customerFundsChart') {
                // 资金构成图：入金、出金、返佣三项金额对比。
                series = {
                    labels: [t('admin.total_deposit'), t('admin.total_withdraw'), t('admin.rebate_amount')],
                    values: [
                        Number((statistics && statistics.total_deposit) || 0),
                        Number((statistics && statistics.total_withdraw) || 0),
                        Number((statistics && statistics.total_rebate) || 0)
                    ]
                };
            } else {
                series = currentProfitSeries();
            }

            if (type === 'pie') {
                return {
                    tooltip: {trigger: 'item'},
                    legend: {bottom: 0, type: 'scroll'},
                    series: [{
                        name: t('admin.customer_statistics'),
                        type: 'pie',
                        radius: ['38%', '66%'],
                        data: series.labels.map(function (label, index) {
                            return {name: label, value: Number(series.values[index] || 0)};
                        })
                    }]
                };
            }

            return {
                tooltip: {trigger: 'axis'},
                grid: {left: 52, right: 16, top: 24, bottom: 32},
                xAxis: {type: 'category', data: series.labels, boundaryGap: type === 'bar'},
                yAxis: {type: 'value'},
                series: [{
                    name: target === 'customerFundsChart' ? t('admin.deposit_withdraw_amount') : t('admin.profit_trend'),
                    type: type === 'bar' ? 'bar' : 'line',
                    smooth: type !== 'bar',
                    areaStyle: type === 'area' ? {} : null,
                    data: series.values
                }]
            };
        }

        /**
         * 渲染指定图表。
         *
         * @param {string} target 图表容器 DOM id。
         * @returns {void}
         */
        function renderChart(target) {
            var container = document.getElementById(target);
            var type = chartTypes[target] || 'line';

            if (!container || typeof echarts === 'undefined' || !container.offsetWidth) {
                return;
            }

            chartInstances[target] = chartInstances[target] || echarts.init(container);
            chartInstances[target].setOption(chartOption(target, type), true);
        }

        /**
         * 重绘全部图表。
         *
         * @returns {void}
         */
        function renderAllCharts() {
            renderChart('customerProfitChart');
            renderChart('customerFundsChart');
        }

        /**
         * 合并 resize 与皮肤切换触发的重绘，避免连续布局抖动。
         *
         * @returns {void}
         */
        function scheduleChartResize() {
            if (resizePending) {
                return;
            }

            resizePending = true;
            window.requestAnimationFrame(function () {
                resizePending = false;
                Object.keys(chartInstances).forEach(function (key) {
                    if (chartInstances[key]) {
                        chartInstances[key].resize();
                    }
                });
                renderAllCharts();
            });
        }

        // 图表类型切换：与前台控制台一致，按钮组内只保留一个 is-active + aria-pressed=true。
        $block.on('click', '.crm-chart-type[data-chart-target]', function () {
            var $button = $(this);
            var target = $button.data('chart-target');

            $button.closest('.crm-chart-controls').find('.crm-chart-type')
                .removeClass('is-active').attr('aria-pressed', 'false');
            $button.addClass('is-active').attr('aria-pressed', 'true');
            chartTypes[target] = String($button.data('chart-type') || 'line');
            renderChart(target);
        });

        // 盈亏天数窗口切换：7/15/30 天，直接复用已加载的 30 天序列做切片。
        $block.on('click', '[data-customer-profit-window]', function () {
            var $button = $(this);

            $button.closest('.crm-chart-controls').find('[data-customer-profit-window]')
                .removeClass('is-active').attr('aria-pressed', 'false');
            $button.addClass('is-active').attr('aria-pressed', 'true');
            profitWindow = parseInt($button.data('customer-profit-window'), 10) || 30;
            renderChart('customerProfitChart');
        });

        window.addEventListener('resize', scheduleChartResize);
        window.addEventListener('crm:theme-change', scheduleChartResize);

        readInitialChartTypes();

        CrmAjax.request({
            guard: 'admin',
            url: $block.data('customer-statistics-endpoint'),
            method: 'POST',
            data: {user_id: String($root.data('customer-id') || '')},
            success: function (response) {
                if ([1000, 1001, 1002].indexOf(Number(response && response.code)) === -1) {
                    return;
                }

                statistics = (response && response.data) || {};
                renderStatValues(statistics);
                renderAllCharts();
            }
        });
    })();
});
