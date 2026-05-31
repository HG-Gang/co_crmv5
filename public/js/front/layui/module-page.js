/**
 * 前台模块页渲染器。
 *
 * 前台业务页的交互模式基本一致：
 * 1. 从 Blade 的 data 属性读取接口、表单和表格配置；
 * 2. 使用保存好的 JWT token 请求受保护的前台接口；
 * 3. 渲染汇总区、列表表格、分页和提交表单。
 *
 * 把这些逻辑收在一个 Layui/jQuery 文件里，可以避免每个 Blade 页面
 * 重复写内联 JS，也能让公开文案统一交给语言包管理。
 */
layui.use(['jquery', 'layer', 'form', 'laypage', 'laydate', 'upload'], function () {
    var $ = layui.jquery;
    var layer = layui.layer;
    var form = layui.form;
    var laydate = layui.laydate;
    var laypage = layui.laypage;
    var upload = layui.upload;
    var $page = $('#frontModulePage');

    if (!$page.length) {
        return;
    }

    var apiUrl = $page.attr('data-api') || '';
    var submitApiUrl = $page.attr('data-submit-api') || '';
    var editApiUrl = $page.attr('data-edit-api') || '';
    var listKey = $page.attr('data-list-key') || '';
    var columns = readJson($page.attr('data-columns'), []);
    var summaryFields = readJson($page.attr('data-summary-fields'), []);
    var chartGroups = readJson($page.attr('data-chart-groups'), []);
    var rowActions = readJson($page.attr('data-row-actions'), []);
    var defaultFilters = readJson($page.attr('data-default-filters'), {});
    var currentRows = [];
    var currentMeta = {};
    var moduleCharts = {};
    var moduleChartTypes = {};
    // 缓存当前选择的文件对象，提交 multipart 表单时显式追加，
    // 避免依赖浏览器对隐藏 file 输入框的默认行为。
    var moduleUploadFiles = {};
    var clickedChain = [];
    var summaryCollapsed = $page.hasClass('commission-realtime-module');
    var pageState = {
        page: 1,
        perPage: parseInt($page.attr('data-per-page') || '15', 10),
        filters: {}
    };

    /**
     * 安全解析 Blade data 属性里输出的 JSON。
     * 如果值损坏了，就回退到默认值，页面仍然可以继续用。
     */
    function readJson(raw, fallback) {
        if (!raw) {
            return fallback;
        }

        try {
            return JSON.parse(raw);
        } catch (e) {
            return fallback;
        }
    }

    /**
     * 通过共享的 JS 语言模块翻译嵌套 key。
     * 找不到文案时保留 key，方便排查问题。
     */
    function t(key) {
        if (typeof CrmLang !== 'undefined' && CrmLang.t) {
            return CrmLang.t(key);
        }

        return key;
    }

    /**
     * 在写入 HTML 前先转义用户或接口返回的值。
     * 这样可以避免旧系统数据里带标签时触发跨页 XSS。
     */
    function escapeHtml(value) {
        if (typeof CrmTable !== 'undefined' && CrmTable.escapeHtml) {
            return CrmTable.escapeHtml(value);
        }

        return String(value).replace(/[&<>"']/g, function (char) {
            return {
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#39;'
            }[char];
        });
    }

    /**
     * 解析类似 "descendant.user_name" 这样的点路径值。
     * 前台接口经常会直接返回 Eloquent 的嵌套关联对象。
     */
    function getValue(row, key) {
        if (typeof CrmTable !== 'undefined' && CrmTable.getValue) {
            return CrmTable.getValue(row, key);
        }

        return row && key ? row[key] : '';
    }

    /**
     * 把连续数字 key 的对象转回数组。
     * ApiResponse 会把数组转成对象，这里负责恢复列表语义。
     */
    function numericObjectToArray(value) {
        if (typeof CrmTable !== 'undefined' && CrmTable.toArray) {
            return CrmTable.toArray(value);
        }

        return value;
    }

    /**
     * 把不同形状的接口返回统一成 summary、rows 和 pager。
     * 兼容普通对象、数组、Laravel 分页对象和嵌套 key。
     */
    function normalizePayload(data) {
        if (typeof CrmTable !== 'undefined' && CrmTable.normalizePayload) {
            return CrmTable.normalizePayload(data, listKey);
        }

        return {summary: data || {}, rows: [], pager: null};
    }

    /**
     * 让接口值在表格和卡片里更容易阅读。
     * 对象优先取常见展示字段，不认识的对象则转成 JSON。
     */
    function formatValue(value) {
        if (typeof CrmTable !== 'undefined' && CrmTable.formatValue) {
            return CrmTable.formatValue(value);
        }

        return value === null || typeof value === 'undefined' || value === '' ? '-' : value;
    }

    function isSuccess(res) {
        if (typeof CrmTable !== 'undefined' && CrmTable.isSuccess) {
            return CrmTable.isSuccess(res);
        }

        return res && res.code >= 1000 && res.code < 4000;
    }

    function collectFilters() {
        var params = {};

        $('.J_moduleFilter').each(function () {
            var $field = $(this);
            var name = $field.attr('name');
            var value = $field.val();

            if (name && value !== null && value !== '') {
                params[name] = value;
            }
        });

        return params;
    }

    function renderSummary(summary) {
        var $summary = $('#moduleSummary');
        var html = '';
        var i;
        var field;
        var value;
        var toggle = '';

        if (!$summary.length || !summaryFields.length) {
            return;
        }

        for (i = 0; i < summaryFields.length; i++) {
            field = summaryFields[i];
            value = formatValue(getValue(summary, field.key));
            html += '<div class="layui-col-md3 layui-col-sm6">';
            html += '<div class="module-stat summary-color-' + (i % 8) + '">';
            html += '<div class="module-stat-label">' + escapeHtml(t(field.label)) + '</div>';
            html += '<div class="module-stat-value">' + escapeHtml(value) + '</div>';
            html += '</div>';
            html += '</div>';
        }

        if ($page.hasClass('commission-realtime-module')) {
            toggle = '<button type="button" class="module-summary-toggle" id="moduleSummaryToggle" title="' + escapeHtml(t('front.summary')) + '"><span>' + (summaryCollapsed ? '&#12299;' : '&#8744;') + '</span></button>';
        }

        $summary.html(toggle + '<div class="module-summary-items' + (summaryCollapsed ? ' is-collapsed' : '') + '">' + html + '</div>');
    }


    function initDatePickers() {
        if (!laydate || !laydate.render) {
            return;
        }
        $('.J_layDate').each(function () {
            var el = this;
            if ($(el).data('crmLaydateReady')) {
                return;
            }
            $(el).data('crmLaydateReady', true);
            laydate.render({ elem: el, trigger: 'click', type: 'date' });
        });
    }

    function uploadEmptyText() {
        return t('front.no_file_selected');
    }

    function uploadFileSize(file) {
        var size = file && file.size ? file.size : 0;
        if (size >= 1024 * 1024) {
            return (size / 1024 / 1024).toFixed(1) + ' MB';
        }
        if (size >= 1024) {
            return Math.ceil(size / 1024) + ' KB';
        }
        return size + ' B';
    }

    function revokeUploadUrls($input) {
        var urls = $input.data('crmUploadUrls') || [];
        $.each(urls, function (_, url) {
            if (window.URL && URL.revokeObjectURL) {
                URL.revokeObjectURL(url);
            }
        });
        $input.removeData('crmUploadUrls');
    }

    function renderUploadPreview(file, url) {
        var name = escapeHtml(file.name || '');
        var size = escapeHtml(uploadFileSize(file));
        var image = url
            ? '<img src="' + escapeHtml(url) + '" alt="' + name + '">'
            : '<span class="crm-upload-file-icon">▧</span>';

        return '<div class="crm-upload-preview-item">' +
            '<div class="crm-upload-thumb">' + image + '</div>' +
            '<div class="crm-upload-file-meta">' +
                '<b title="' + name + '">' + name + '</b>' +
                '<em>' + size + '</em>' +
            '</div>' +
        '</div>';
    }

    function resetEnhancedUpload(input) {
        var $input = $(input);
        var id = $input.attr('id');
        var fieldName = $input.attr('name');

        if (!id) {
            return;
        }

        revokeUploadUrls($input);
        delete moduleUploadFiles[fieldName];
        $input.val('');
        $('#' + id + '_list').empty();
        $('#' + id + '_status').text(uploadEmptyText()).removeClass('has-file');
        $('[data-upload-target="' + id + '"]').removeClass('is-visible');
    }

    function resetAllEnhancedUploads() {
        moduleUploadFiles = {};
        $('.J_crmUploadInput').each(function () {
            resetEnhancedUpload(this);
        });
    }

    function initEnhancedUpload() {
        // 把每个自定义上传按钮都绑定到 layui.upload，这样既能生成预览，
        // 也能把选中的文件缓存起来，供最终的 FormData 提交使用。
        $('.J_crmUploadInput').each(function () {
            var input = this;
            var $input = $(input);
            var id = $input.attr('id');
            var $list = $('#' + id + '_list');
            var fieldName = $input.attr('name');
            if ($input.data('crmUploadReady')) {
                return;
            }
            $input.data('crmUploadReady', true);

            upload.render({
                elem: '#' + id + '_trigger',
                auto: false,
                accept: ($input.attr('accept') || '').indexOf('image') !== -1 ? 'images' : 'file',
                multiple: input.hasAttribute('multiple'),
                choose: function (obj) {
                    var files = obj.pushFile();
                    var list = [];
                    var html = '';
                    var objectUrls = [];

                    revokeUploadUrls($input);
                    $.each(files, function (_, file) {
                        var previewUrl = '';
                        list.push(file);
                        if (/^image\//.test(file.type || '') && window.URL && URL.createObjectURL) {
                            previewUrl = URL.createObjectURL(file);
                            objectUrls.push(previewUrl);
                        }
                        html += renderUploadPreview(file, previewUrl);
                    });

                    moduleUploadFiles[fieldName] = input.hasAttribute('multiple') ? list : (list[0] || null);
                    $input.data('crmUploadUrls', objectUrls);
                    $list.html(html);
                    $('#' + id + '_status')
                        .text(t('front.selected_files').replace('{count}', list.length))
                        .addClass('has-file');
                    $('[data-upload-target="' + id + '"]').addClass('is-visible');
                }
            });
        });
    }

    function numeric(value) {
        var numberValue = Number(String(value || 0).replace(/,/g, ''));
        return isNaN(numberValue) ? 0 : numberValue;
    }

    function parseImages(value) {
        if (!value) {
            return [];
        }
        if ($.isArray(value)) {
            return value;
        }
        if (typeof value === 'string') {
            try {
                var parsed = JSON.parse(value);
                if ($.isArray(parsed)) {
                    return parsed;
                }
            } catch (e) {}

            return value.split(',').map(function (item) {
                return $.trim(item.replace(/\\\//g, '/'));
            }).filter(Boolean);
        }

        return [];
    }

    function imageUrl(value) {
        var url = String(value || '').replace(/\\\//g, '/').trim();
        if (!url) {
            return '#';
        }
        if (/^(https?:)?\/\//i.test(url) || url.charAt(0) === '/') {
            return url;
        }

        return '/' + url.replace(/^\/+/, '');
    }

    function imageIconsHtml(key, value) {
        var images = /(^|_)(image|images|avatar|photo|voucher|url)(_|$)/i.test(key || '') ? parseImages(value) : [];

        if (!images.length) {
            return '';
        }

        return '<span class="crm-image-icons">' + images.map(function (src, index) {
            return '<a href="' + escapeHtml(imageUrl(src)) + '" target="_blank" rel="noopener" title="' + escapeHtml(t('front.images') + ' ' + (index + 1)) + '">▧</a>';
        }).join('') + '</span>';
    }

    function renderChartSelectors() {
        var options = [
            ['bar', t('front.chart_bar')],
            ['line', t('front.chart_line')],
            ['area', t('front.chart_area')],
            ['pie', t('front.chart_pie')],
            ['radar', t('front.chart_radar')]
        ];

        $('.J_moduleChartType').each(function () {
            var $select = $(this);
            var target = $select.attr('data-chart-target');
            var current = moduleChartTypes[target] || $select.val() || 'bar';
            var html = '';

            $.each(options, function (_, item) {
                html += '<option value="' + item[0] + '"' + (item[0] === current ? ' selected' : '') + '>' + escapeHtml(item[1]) + '</option>';
            });
            $select.html(html);
        });
    }

    function chartOption(title, values, type) {
        var option = {
            color: ['#2080f0', '#18a058', '#d97706', '#7c3aed', '#ef4444', '#0e7a83'],
            tooltip: {trigger: 'item'}
        };
        var maxValue = Math.max.apply(null, values.map(function (item) { return numeric(item.value); }).concat([10]));

        if (type === 'pie') {
            option.legend = {bottom: 0};
            option.series = [{
                name: title,
                type: 'pie',
                radius: ['30%', '64%'],
                center: ['50%', '48%'],
                data: values
            }];
            return option;
        }

        if (type === 'radar') {
            option.radar = {
                indicator: values.map(function (item) {
                    return {name: item.name, max: Math.max(maxValue, numeric(item.value) * 1.2, 10)};
                })
            };
            option.series = [{name: title, type: 'radar', data: [{value: values.map(function (item) { return item.value; }), name: title}]}];
            return option;
        }

        option.tooltip = {trigger: 'axis', axisPointer: {type: type === 'bar' ? 'shadow' : 'line'}};
        option.grid = {left: 54, right: 18, top: 24, bottom: 38};
        option.xAxis = {type: 'category', data: values.map(function (item) { return item.name; })};
        option.yAxis = {type: 'value'};
        option.series = [{
            name: title,
            type: type === 'area' ? 'line' : type,
            smooth: type !== 'bar',
            areaStyle: type === 'area' ? {opacity: 0.18} : null,
            barWidth: type === 'bar' ? 18 : null,
            data: values.map(function (item) { return item.value; })
        }];

        return option;
    }

    function renderCharts(summary) {
        var i;
        var chart;
        var el;
        var values;
        var type;
        var title;

        summary = summary || {};
        if (!chartGroups.length || typeof echarts === 'undefined') {
            return;
        }
        renderChartSelectors();
        for (i = 0; i < chartGroups.length; i++) {
            chart = chartGroups[i] || {};
            el = document.getElementById(chart.target);
            if (!el) {
                continue;
            }
            type = moduleChartTypes[chart.target] || chart.defaultType || 'bar';
            moduleChartTypes[chart.target] = type;
            title = t(chart.title || 'front.account_chart_title');
            values = (chart.fields || []).map(function (field) {
                return {name: t(field.label || field.key), value: numeric(getValue(summary, field.key))};
            });
            if (!moduleCharts[chart.target]) {
                moduleCharts[chart.target] = echarts.init(el);
            }
            moduleCharts[chart.target].setOption(chartOption(title, values, type), true);
            moduleCharts[chart.target].resize();
        }
    }

    function levelClass(value) {
        var rank = parseInt(value || 0, 10);

        if (isNaN(rank) || rank < 1) {
            rank = 5;
        }
        if (rank > 5) {
            rank = 5;
        }

        return 'level-' + rank;
    }

    function renderChain(chain) {
        var $chain = $('#moduleChain');
        var html = '';
        var i;

        if (!$chain.length) {
            return;
        }
        if (!clickedChain.length) {
            $chain.hide().empty();
            return;
        }

        html += '<span class="module-chain-title">' + escapeHtml(t('front.current_chain')) + '</span>';
        for (i = 0; i < clickedChain.length; i++) {
            if (i > 0) {
                html += '<span class="module-chain-arrow">&gt;</span>';
            }
            html += '<span class="module-chain-node">' + escapeHtml(clickedChain[i]) + '</span>';
        }

        $chain.html(html).show();
    }

    function formatColumnValue(row, column) {
        var value = getValue(row, column.key);
        var numberValue;

        if (column.displayKey) {
            value = getValue(row, column.displayKey);
        }

        if (column.format === 'money') {
            numberValue = Number(value || 0);
            return isNaN(numberValue) ? formatValue(value) : numberValue.toFixed(2);
        }
        if (column.format === 'lots') {
            numberValue = Number(value || 0);
            if (isNaN(numberValue)) {
                return formatValue(value);
            }
            return numberValue > 50 ? (numberValue / 100).toFixed(2) : numberValue.toFixed(2);
        }
        if (column.format === 'cmd') {
            return getValue(row, 'cmd_text') || formatValue(value);
        }
        if (column.format === 'yesno') {
            return value == 1 || value === true || value === '1' ? t('front.yes') : t('front.no');
        }
        if (column.format === 'gender') {
            if (value === null || typeof value === 'undefined' || value === '') {
                return '-';
            }
            if (String(value).toLowerCase() === 'female' || Number(value) === 2) {
                return t('register.female');
            }
            if (String(value).toLowerCase() === 'male' || Number(value) === 1) {
                return t('register.male');
            }

            return formatValue(value);
        }
        if (column.format === 'agentLevel') {
            return formatValue(value || getValue(row, 'agent_level_name') || getValue(row, 'level_name'));
        }
        if (column.format === 'agentLevelSelect') {
            return formatValue(value || getValue(row, 'commprop') || getValue(row, 'comm_rate'));
        }

        return formatValue(value);
    }

    function columnCellClass(column) {
        if (column.align) {
            return ' module-cell-' + column.align;
        }
        if (column.format === 'money' || column.format === 'lots') {
            return ' module-cell-right';
        }

        return '';
    }

    function columnAllowsAction(row, column) {
        var flagValue;

        if (!column.action) {
            return false;
        }
        if (!column.actionIf) {
            return true;
        }

        flagValue = getValue(row, column.actionIf);
        return flagValue === true || flagValue === 1 || flagValue === '1' || flagValue === 'true';
    }

    function cellHtml(row, column, value, rowIndex, columnIndex) {
        var rawValue = column.displayKey ? getValue(row, column.displayKey) : getValue(row, column.key);
        var numberValue = Number(rawValue || 0);
        var html = escapeHtml(value);
        var levelValue = column.levelClassKey ? getValue(row, column.levelClassKey) : '';
        var imageHtml = imageIconsHtml(column.key, rawValue);

        if (column.format === 'agentLevelSelect') {
            return agentLevelSelectHtml(row, rowIndex);
        }
        if (imageHtml) {
            return imageHtml;
        }
        if ((column.format === 'money' || column.format === 'lots') && !isNaN(numberValue) && numberValue < 0) {
            html = '<span class="value-negative">' + html + '</span>';
        } else if (column.format === 'money' && !isNaN(numberValue) && numberValue > 0 && column.emphasis === 'positive') {
            html = '<span class="value-positive">' + html + '</span>';
        }
        if (column.format === 'agentLevel' && !levelValue) {
            levelValue = getValue(row, column.rankKey || 'agent_level_rank');
        }
        if (levelValue) {
            html = '<span class="module-agent-level ' + levelClass(levelValue) + '">' + html + '</span>';
        }

        if (columnAllowsAction(row, column)) {
            return '<a href="javascript:;" class="module-cell-link ' + escapeHtml(column.linkClass || '') + ' J_moduleCellAction" data-row-index="' + rowIndex + '" data-column-index="' + columnIndex + '">' + html + '</a>';
        }

        return html;
    }

    // 代理等级确认接口偶尔缺少 range_list，旧项目会直接给用户一个
    // 可确认的等级选择。这里补同样的兜底数据，避免页面只剩空模板。
    function defaultAgentLevelRange(row) {
        var currentRate = Number(getValue(row, 'commprop') || getValue(row, 'comm_rate') || 35);
        var currentLevel = getValue(row, 'userGroupId') || getValue(row, 'level_id') || 2;

        if (isNaN(currentRate)) {
            currentRate = 35;
        }

        return [
            {level_id: 1, level_name: 'Level 1', prop: currentRate + 10, choice_gid: 1, def_gid: 1, extra_val: 0, selected: Number(currentLevel) === 1},
            {level_id: 2, level_name: 'Level 2', prop: currentRate + 5, choice_gid: 2, def_gid: 2, extra_val: 0, selected: Number(currentLevel) === 2},
            {level_id: 3, level_name: 'Level 3', prop: currentRate, choice_gid: 3, def_gid: 3, extra_val: 0, selected: Number(currentLevel) === 3}
        ];
    }

    function agentLevelSelectHtml(row, rowIndex) {
        var list = numericObjectToArray(getValue(row, 'range_list')) || [];
        var currentLevel = String(getValue(row, 'userGroupId') || getValue(row, 'level_id') || '');
        var currentRate = String(getValue(row, 'commprop') || getValue(row, 'comm_rate') || '');
        var html = '';
        var i;
        var item;
        var levelId;
        var prop;
        var selected;
        var label;
        var hasSelected = false;

        if (!list.length) {
            list = defaultAgentLevelRange(row);
        }

        for (i = 0; i < list.length; i++) {
            if (list[i] && list[i].selected) {
                hasSelected = true;
                break;
            }
        }

        html += '<select class="module-level-select J_agentLevelSelect" lay-ignore data-row-index="' + rowIndex + '">';
        for (i = 0; i < list.length; i++) {
            item = list[i] || {};
            levelId = String(item.level_id || '');
            prop = String(item.prop || '');
            selected = item.selected || (!hasSelected && (currentLevel ? levelId === currentLevel : (prop && prop === currentRate)));
            label = (item.level_name ? item.level_name + ' / ' : '') + (prop || '-');
            html += '<option value="' + escapeHtml(levelId) + '"';
            html += ' data-comm-prop="' + escapeHtml(prop) + '"';
            html += ' data-def-gid="' + escapeHtml(item.def_gid || levelId) + '"';
            html += ' data-choice-gid="' + escapeHtml(item.choice_gid || levelId) + '"';
            html += ' data-extra-val="' + escapeHtml(item.extra_val || 0) + '"';
            if (selected) {
                html += ' selected';
            }
            html += '>' + escapeHtml(label) + '</option>';
        }
        html += '</select>';

        return html;
    }

    function renderTable(rows) {
        var $body = $('#moduleTableBody');
        var html = '';
        var i;
        var j;
        var value;
        var colspan = columns.length + (rowActions.length ? 1 : 0);

        if (!$body.length) {
            return;
        }

        if (!rows || !rows.length) {
            currentRows = [];
            if (typeof CrmTable !== 'undefined' && CrmTable.renderSummary) {
                CrmTable.renderSummary('#moduleAutoSummary', [], columns, currentMeta.totalRow || null);
            }
            $body.html('<tr><td colspan="' + colspan + '" class="module-empty">' + escapeHtml(t('common.noData')) + '</td></tr>');
            return;
        }

        currentRows = rows;
        if (typeof CrmTable !== 'undefined' && CrmTable.renderSummary) {
            CrmTable.renderSummary('#moduleAutoSummary', rows, columns, currentMeta.totalRow || null);
        }
        for (i = 0; i < rows.length; i++) {
            html += '<tr>';
            for (j = 0; j < columns.length; j++) {
                value = formatColumnValue(rows[i], columns[j]);
                html += '<td class="' + columnCellClass(columns[j]) + '">' + cellHtml(rows[i], columns[j], value, i, j) + '</td>';
            }
            if (rowActions.length) {
                html += '<td>' + buildActionButtons(i) + '</td>';
            }
            html += '</tr>';
        }

        $body.html(html);
    }

    /**
     * 渲染 Blade 里声明的行操作按钮。按钮本身只带索引，真正的接口、
     * 参数和行数据都留在 JS 内存里，避免把未转义的业务数据塞进属性。
     */
    function buildActionButtons(rowIndex) {
        var html = '';
        var i;
        var action;
        var css;

        for (i = 0; i < rowActions.length; i++) {
            action = rowActions[i] || {};
            css = '';
            if (action.style === 'danger') {
                css = ' layui-btn-danger';
            } else if (action.style !== 'normal') {
                css = ' layui-btn-primary';
            }
            html += '<button type="button" class="layui-btn layui-btn-xs' + css + ' J_moduleRowAction"';
            html += ' data-row-index="' + rowIndex + '" data-action-index="' + i + '">';
            if (action.icon) {
                html += '<i class="layui-icon ' + escapeHtml(action.icon) + '"></i>';
            }
            html += escapeHtml(t(action.label || 'common.operation'));
            html += '</button>';
        }

        return html;
    }

    function renderPager(pager) {
        var $pager = $('#modulePager');

        if (!$pager.length || !pager || !pager.total) {
            $pager.empty();
            return;
        }

        laypage.render({
            elem: 'modulePager',
            count: parseInt(pager.total || 0, 10),
            limit: parseInt(pager.per_page || pageState.perPage, 10),
            curr: parseInt(pager.current_page || pageState.page, 10),
            layout: ['prev', 'page', 'next', 'count', 'limit'],
            limits: [20, 50, 100],
            jump: function (obj, first) {
                if (first) {
                    return;
                }
                pageState.page = obj.curr;
                pageState.perPage = obj.limit;
                loadData();
            }
        });
    }

    function buildRequestData() {
        return $.extend({}, defaultFilters, pageState.filters, {
            page: pageState.page,
            per_page: pageState.perPage
        });
    }

    function loadData() {
        if (!apiUrl) {
            return;
        }

        layer.load(1);
        CrmAjax.request({
            guard: 'front',
            method: 'POST',
            url: apiUrl,
            data: buildRequestData(),
            success: function (res) {
                var payload;

                layer.closeAll('loading');
                if (!isSuccess(res)) {
                    layer.msg(res.message || t('common.error'), {icon: 2});
                    return;
                }

                payload = normalizePayload(res.data);
                currentMeta = res.data || {};
                if (payload.serverTotalRow) {
                    currentMeta.totalRow = payload.serverTotalRow;
                }
                renderSummary(payload.summary && !payload.serverTotalRow ? payload.summary : (payload.serverTotalRow || payload.summary));
                renderCharts(payload.summary && !payload.serverTotalRow ? payload.summary : (payload.serverTotalRow || payload.summary));
                renderChain([]);
                renderTable(payload.rows);
                renderPager(payload.pager);
            },
            error: function () {
                layer.closeAll('loading');
                layer.msg(t('common.error'), {icon: 2});
            }
        });
    }

    function formArrayToObject(items) {
        var data = {};

        $.each(items, function (_, item) {
            if (typeof data[item.name] !== 'undefined') {
                if (!$.isArray(data[item.name])) {
                    data[item.name] = [data[item.name]];
                }
                data[item.name].push(item.value);
                return;
            }
            data[item.name] = item.value;
        });

        return data;
    }

    function getSubmitUrl($form) {
        var recordId = $.trim($form.find('.J_moduleRecordId').val() || '');

        // 隐藏的 id 不为空，说明用户选中了已有记录要编辑，所以请求要
        // 走页面自己的更新接口。
        if (recordId && editApiUrl) {
            return editApiUrl;
        }

        return submitApiUrl;
    }

    function submitJsonForm($form) {
        CrmAjax.request({
            guard: 'front',
            method: 'POST',
            url: getSubmitUrl($form),
            data: formArrayToObject($form.serializeArray()),
            success: afterSubmit,
            error: function () {
                layer.msg(t('common.error'), {icon: 2});
            }
        });
    }

    function submitMultipartForm($form) {
        // 把可见表单值和缓存的文件对象合并成一个请求体。这样上传提交
        // 的行为更确定，后端校验必填文件时也更容易排查。
        var token = CrmAjax.getToken('front');
        var headers = {
            Accept: 'application/json',
            'X-Locale': CrmLang.getLocale()
        };
        var formData = new FormData($form[0]);

        if (token) {
            headers.Authorization = 'Bearer ' + token;
        }

        $.each(moduleUploadFiles, function (fieldName, files) {
            if (!fieldName || !files) {
                return;
            }
            if ($.isArray(files)) {
                $.each(files, function (_, file) {
                    if (file) {
                        formData.append(fieldName, file);
                    }
                });
                return;
            }
            formData.append(fieldName, files);
        });

        $.ajax({
            url: getSubmitUrl($form),
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            headers: headers,
            success: afterSubmit,
            error: function () {
                layer.msg(t('common.error'), {icon: 2});
            }
        });
    }

    function afterSubmit(res) {
        if (!isSuccess(res)) {
            layer.msg((res && res.message) || t('common.error'), {icon: 2});
            return;
        }

        layer.msg(res.message || t('common.success'), {icon: 1});
        if ($('.J_moduleForm')[0]) {
            $('.J_moduleForm')[0].reset();
        }
        resetAllEnhancedUploads();
        $('.J_moduleRecordId').val('');
        form.render();
        initEnhancedUpload();
        initDatePickers();
        loadData();
    }

    /**
     * 把一行数据回填到共享表单里。文件字段会刻意跳过，因为出于安全
     * 原因，浏览器不允许直接给 file 输入框赋值。
     */
    function fillFormFromRow(row) {
        var $form = $('.J_moduleForm');

        if (!$form.length || !row) {
            return;
        }

        resetAllEnhancedUploads();
        $form.find('input, select, textarea').each(function () {
            var $field = $(this);
            var name = $field.attr('name');
            var value;

            if (!name || $field.attr('type') === 'file') {
                return;
            }

            value = getValue(row, name.replace(/\[\]$/, ''));
            if ($field.attr('type') === 'checkbox') {
                $field.prop('checked', value == 1 || value === true || value === '1');
                return;
            }

            $field.val(value);
        });

        form.render();
    }

    function runRowAction(action, row, rowIndex) {
        var idField = action.idField || 'id';
        var payload = buildActionPayload(action.payload || {}, row);
        var idValue = getValue(row, idField);
        var $select;
        var $option;

        if (action.type === 'detail' || action.type === 'showOrderInfo') {
            openDetailModal(action.title || 'common.detail', action.fields || (action.type === 'showOrderInfo' ? defaultOrderFields() : columns), row);
            return;
        }

        if (action.action === 'showOrderInfo') {
            openDetailModal(action.title || 'front.order_detail', action.fields || defaultOrderFields(), row);
            return;
        }

        if (!action.api && !action.action) {
            openDetailModal(action.title || 'common.detail', action.fields || columns, row);
            return;
        }

        if (!action.api || !idValue) {
            return;
        }

        if (action.type === 'confirmAgentLevel') {
            $select = $('.J_agentLevelSelect[data-row-index="' + rowIndex + '"]');
            $option = $select.find('option:selected');
            if (!$select.length || !$option.length) {
                layer.msg(t('common.error'), {icon: 2});
                return;
            }
            payload.agent_gId = $option.val();
            payload.comm_prop = $option.data('comm-prop');
            payload.def_gid = $option.data('def-gid') || $option.val();
            payload.choice_gid = $option.data('choice-gid') || $option.val();
            payload.extra_val = $option.data('extra-val') || 0;
        }

        payload[idField] = idValue;
        CrmAjax.request({
            guard: 'front',
            method: action.method || 'POST',
            url: action.api,
            data: payload,
            success: function (res) {
                if (!isSuccess(res)) {
                    layer.msg((res && res.message) || t('common.error'), {icon: 2});
                    return;
                }

                layer.msg(res.message || t('common.success'), {icon: 1});
                loadData();
            },
            error: function () {
                layer.msg(t('common.error'), {icon: 2});
            }
        });
    }

    function resolvePayloadValue(value, row) {
        if (typeof value !== 'string') {
            return value;
        }

        return value.replace(/\{([^}]+)\}/g, function (_, key) {
            return getValue(row, key) || '';
        });
    }

    function buildActionPayload(template, row) {
        var payload = {};
        var key;

        template = template || {};
        for (key in template) {
            if (Object.prototype.hasOwnProperty.call(template, key)) {
                payload[key] = resolvePayloadValue(template[key], row);
            }
        }

        return payload;
    }

    function defaultUserFields() {
        var fields = [
            {key: 'user_id', label: 'front.user_id'},
            {key: 'user_name', label: 'front.user_name'},
            {key: 'email', label: 'front.email'},
            {key: 'phone', label: 'front.phone'},
            {key: 'id_card_no', label: 'front.id_card_no'},
            {key: 'gender', label: 'front.gender', format: 'gender'},
            {key: 'account_type_text', label: 'front.account_type'},
            {key: 'agent_level_name', label: 'front.agent_level', format: 'agentLevel', rankKey: 'agent_level_rank'},
            {key: 'group_name', label: 'front.group_name'},
            {key: 'group_id', label: 'front.group_id'},
            {key: 'parent_id', label: 'front.parent_agent'},
            {key: 'total_deposit', label: 'front.total_deposit', format: 'money'},
            {key: 'total_withdraw', label: 'front.total_withdraw', format: 'money'},
            {key: 'total_rebate', label: 'front.total_rebate', format: 'money'},
            {key: 'commprop', label: 'front.commission_rate'},
            {key: 'open_order_count', label: 'front.open_order_count'},
            {key: 'closed_order_count', label: 'front.closed_order_count'},
            {key: 'profit_7d', label: 'front.profit_7d', format: 'money'},
            {key: 'profit_15d', label: 'front.profit_15d', format: 'money'},
            {key: 'profit_30d', label: 'front.profit_30d', format: 'money'}
        ];

        // 代理管理页不展示登录历史类信息，避免把用户要求隐藏的登录轨迹带进详情弹框。
        if (!/agent(Sub|Customer)List/i.test(apiUrl)) {
            fields.push(
                {key: 'last_login_ip', label: 'front.last_login_ip'},
                {key: 'last_login_at', label: 'front.last_login_at'}
            );
        }

        fields.push(
            {key: 'created_at', label: 'common.created_at'}
        );

        return fields;
    }

    function defaultOrderFields() {
        return [
            {key: 'ticket', label: 'front.ticket'},
            {key: 'login', label: 'front.user_id'},
            {key: 'symbol', label: 'front.symbol'},
            {key: 'cmd', label: 'front.order_cmd', format: 'cmd'},
            {key: 'volume_lots', label: 'front.volume', format: 'lots'},
            {key: 'open_price', label: 'front.open_price'},
            {key: 'close_price', label: 'front.close_price'},
            {key: 'sl', label: 'front.stop_loss'},
            {key: 'tp', label: 'front.take_profit'},
            {key: 'commission', label: 'front.commission', format: 'money'},
            {key: 'profit', label: 'front.profit', format: 'money'},
            {key: 'swaps', label: 'front.swaps', format: 'money'},
            {key: 'open_time', label: 'front.open_time'},
            {key: 'close_time', label: 'front.close_time'},
            {key: 'comment', label: 'common.remark'}
        ];
    }

    function commissionDetailColumns() {
        return [
            {key: 'agent_id', label: 'front.rebate_user_id'},
            {key: 'agent_name', label: 'front.rebate_user_name'},
            {key: 'agent_level', label: 'front.agent_level', format: 'agentLevel', rankKey: 'agent_level_rank'},
            {key: 'commission_amount', label: 'front.rebate_amount', format: 'money'},
            {key: 'rebate_ratio', label: 'front.rebate_ratio'},
            {key: 'spread', label: 'front.spread', format: 'money'},
            {key: 'spread_ratio', label: 'front.spread_ratio'},
            {key: 'rebate_time', label: 'front.rebate_time'},
            {key: 'settle_status_text', label: 'front.settle_status'}
        ];
    }

    function loginHistoryColumns() {
        return [
            {key: 'login_ip', label: 'front.login_ip'},
            {key: 'ip_location', label: 'front.ip_location'},
            {key: 'user_agent', label: 'front.user_agent'},
            {key: 'created_at', label: 'front.login_time'}
        ];
    }

    function openListModal(titleKey, columns, rows) {
        var html = '<div class="crm-detail-subtable-wrap"><table class="crm-detail-subtable"><thead><tr>';
        var i;
        var j;
        var value;
        var width = Math.min(920, Math.max(320, window.innerWidth - 32));
        var height = Math.min(680, Math.max(360, window.innerHeight - 80));
        var area = [width + 'px', height + 'px'];

        rows = numericObjectToArray(rows) || [];
        for (i = 0; i < columns.length; i++) {
            html += '<th>' + escapeHtml(t(columns[i].label)) + '</th>';
        }
        html += '</tr></thead><tbody>';
        if (!$.isArray(rows) || !rows.length) {
            html += '<tr><td colspan="' + columns.length + '" class="module-empty">' + escapeHtml(t('common.noData')) + '</td></tr>';
        } else {
            for (i = 0; i < rows.length; i++) {
                html += '<tr>';
                for (j = 0; j < columns.length; j++) {
                    value = formatColumnValue(rows[i], columns[j]);
                    html += '<td>' + escapeHtml(value) + '</td>';
                }
                html += '</tr>';
            }
        }
        html += '</tbody></table></div>';

        layer.open({
            type: 1,
            title: t(titleKey || 'common.detail'),
            area: area,
            maxHeight: height,
            shade: 0.25,
            content: '<div style="padding:16px;max-height:' + (height - 54) + 'px;overflow:auto;box-sizing:border-box;">' + html + '</div>'
        });
    }

    function renderCommissionDetails(row) {
        var list = numericObjectToArray(getValue(row, 'commission_details')) || [];
        var cols = commissionDetailColumns();
        var html = '';
        var i;
        var j;
        var value;

        if (!$.isArray(list) || !list.length) {
            return '';
        }

        html += '<div class="crm-detail-section-title">' + escapeHtml(t('front.commission_detail')) + '</div>';
        html += '<div class="crm-detail-subtable-wrap"><table class="crm-detail-subtable"><thead><tr>';
        for (i = 0; i < cols.length; i++) {
            html += '<th>' + escapeHtml(t(cols[i].label)) + '</th>';
        }
        html += '</tr></thead><tbody>';
        for (i = 0; i < list.length; i++) {
            html += '<tr>';
            for (j = 0; j < cols.length; j++) {
                value = formatColumnValue(list[i], cols[j]);
                html += '<td>' + cellHtml(list[i], cols[j], value, i, j).replace(/J_moduleCellAction/g, '') + '</td>';
            }
            html += '</tr>';
        }
        html += '</tbody></table></div>';

        return html;
    }

    function detailGroupTitle(group) {
        var titles = {
            identity: 'front.basic_info',
            trade: 'front.trade_info',
            finance: 'front.finance_info',
            time: 'front.time_info',
            other: 'front.other_info'
        };

        return t(titles[group] || titles.other);
    }

    function detailGroupForKey(key) {
        if (/^(id|user|login|email|phone|account|agent|group|level|auth|parent|real_name|username)/i.test(key)) {
            return 'identity';
        }
        if (/^(ticket|order|symbol|cmd|volume|open_|close_|sl|tp|stop_|take_|reason|comment)/i.test(key)) {
            return 'trade';
        }
        if (/(amount|balance|equity|credit|margin|profit|commission|rebate|fee|swaps|funds|rate|total|net_worth)/i.test(key)) {
            return 'finance';
        }
        if (/(_at|_time|date|created|updated|modify)/i.test(key)) {
            return 'time';
        }

        return 'other';
    }

    function normalizeDetailFields(fields, row) {
        if (fields && fields.length) {
            return fields;
        }

        return Object.keys(row || {}).map(function (key) {
            return {
                key: key,
                label: 'front.' + key
            };
        });
    }

    function openDetailModal(titleKey, fields, row) {
        var groups = {
            identity: [],
            trade: [],
            finance: [],
            time: [],
            other: []
        };
        var groupKeys = ['identity', 'trade', 'finance', 'time', 'other'];
        var html = '<div class="crm-detail-modal">';
        var i;
        var j;
        var field;
        var value;
        var group;
        var width = Math.min(920, Math.max(320, window.innerWidth - 32));
        var height = Math.min(680, Math.max(320, window.innerHeight - 48));
        var area = [width + 'px', height + 'px'];

        fields = normalizeDetailFields(fields, row);
        for (i = 0; i < fields.length; i++) {
            field = fields[i];
            groups[detailGroupForKey(field.key)].push(field);
        }

        for (i = 0; i < groupKeys.length; i++) {
            group = groupKeys[i];
            if (!groups[group].length) {
                continue;
            }

            html += '<section class="crm-detail-section">';
            html += '<h3>' + escapeHtml(detailGroupTitle(group)) + '</h3>';
            html += '<dl class="crm-detail-grid">';
            for (j = 0; j < groups[group].length; j++) {
                field = groups[group][j];
                value = formatColumnValue(row, field);
                html += '<div class="crm-detail-field">';
                html += '<dt>' + escapeHtml(t(field.label || field.key)) + '</dt>';
                html += '<dd>' + cellHtml(row, field, value, 0, 0).replace(/J_moduleCellAction/g, '') + '</dd>';
                html += '</div>';
            }
            html += '</dl></section>';
        }
        html += renderUserDetailCharts(row);
        html += renderCommissionDetails(row);
        html += '</div>';

        layer.open({
            type: 1,
            title: t(titleKey || 'common.detail'),
            area: area,
            maxHeight: height,
            shade: 0.25,
            content: '<div style="padding:16px;max-height:' + (height - 54) + 'px;overflow:auto;box-sizing:border-box;">' + html + '</div>'
        });
    }

    function renderUserDetailCharts(row) {
        var values = [
            {label: 'front.total_deposit', value: getValue(row, 'total_deposit')},
            {label: 'front.total_withdraw', value: getValue(row, 'total_withdraw')},
            {label: 'front.total_rebate', value: getValue(row, 'total_rebate')},
            {label: 'front.commission_rate', value: getValue(row, 'commprop') || getValue(row, 'comm_rate')},
            {label: 'front.open_order_count', value: getValue(row, 'open_order_count') || getValue(row, 'open_count')},
            {label: 'front.closed_order_count', value: getValue(row, 'closed_order_count') || getValue(row, 'closed_count')},
            {label: 'front.profit_7d', value: getValue(row, 'profit_7d')},
            {label: 'front.profit_15d', value: getValue(row, 'profit_15d')},
            {label: 'front.profit_30d', value: getValue(row, 'profit_30d')}
        ];
        var hasValue = values.some(function (item) {
            return numeric(item.value) !== 0;
        });
        var html = '';

        if (!hasValue) {
            return '';
        }

        html += '<section class="crm-detail-section">';
        html += '<h3>' + escapeHtml(t('front.account_chart_title')) + '</h3>';
        html += '<div class="crm-detail-bars">';
        var maxAbs = Math.max.apply(null, values.map(function (entry) { return Math.abs(numeric(entry.value)); }).concat([1]));

        values.forEach(function (item) {
            var value = numeric(item.value);
            var width = Math.max(6, Math.min(100, Math.abs(value) / maxAbs * 100));

            html += '<div class="crm-detail-bar">';
            html += '<span>' + escapeHtml(t(item.label)) + '</span>';
            html += '<strong>' + escapeHtml(value.toFixed(2)) + '</strong>';
            html += '<i style="width:' + width + '%"></i>';
            html += '</div>';
        });
        html += '</div></section>';

        return html;
    }

    function clearNamedFilters(names) {
        var i;

        for (i = 0; i < names.length; i++) {
            delete pageState.filters[names[i]];
            $('.J_moduleFilter[name="' + names[i] + '"]').val('');
        }
    }

    function runColumnAction(column, row) {
        var idField = column.idField || column.key;
        var idValue = getValue(row, idField);
        var payload;

        if (column.action === 'positionSummaryDrill') {
            if (!idValue) {
                return;
            }
            if (column.chainAction) {
                clickedChain.push(String(idValue));
                renderChain([]);
            }
            pageState.page = 1;
            pageState.filters = $.extend({}, pageState.filters, {
                searchtype: 'subAgentsSearch',
                userPId: idValue
            }, buildActionPayload(column.payload, row));
            clearNamedFilters(column.clearFilters || ['userId', 'userName']);
            form.render();
            loadData();
            return;
        }

        if (column.action === 'showUserInfo') {
            var detailRow = getValue(row, 'user_info') || row;
            if (column.api) {
                CrmAjax.request({
                    guard: 'front',
                    method: 'POST',
                    url: column.api,
                    data: buildActionPayload(column.payload || {user_id: '{' + (column.idField || column.key) + '}'}, row),
                    success: function (res) {
                        if (!isSuccess(res)) {
                            layer.msg((res && res.message) || t('common.error'), {icon: 2});
                            return;
                        }
                        openDetailModal(column.title || 'front.user_detail', column.fields || defaultUserFields(), (res.data && (res.data.user_info || res.data.info)) || res.data || detailRow);
                    },
                    error: function () {
                        layer.msg(t('common.error'), {icon: 2});
                    }
                });
                return;
            }
            openDetailModal(column.title || 'front.user_detail', column.fields || defaultUserFields(), detailRow);
            return;
        }

        if (column.action === 'showOrderInfo') {
            openDetailModal(column.title || 'front.order_detail', column.fields || defaultOrderFields(), row);
            return;
        }

        if (column.action === 'showLoginHistory') {
            // 代理管理页不允许打开登录历史，即使后续配置里误加了该动作也直接拦截。
            if (/agent(Sub|Customer)List/i.test(apiUrl)) {
                return;
            }
            CrmAjax.request({
                guard: 'front',
                method: 'POST',
                url: column.api || '/api/front/userLoginHistory',
                data: buildActionPayload(column.payload || {user_id: '{' + (column.idField || column.key) + '}'}, row),
                success: function (res) {
                    var data;

                    if (!isSuccess(res)) {
                        layer.msg((res && res.message) || t('common.error'), {icon: 2});
                        return;
                    }
                    data = res.data || {};
                    openListModal(column.title || 'front.login_history', column.fields || loginHistoryColumns(), data.list || data.data || data);
                },
                error: function () {
                    layer.msg(t('common.error'), {icon: 2});
                }
            });
            return;
        }

        if (column.action === 'reload') {
            if (!idValue) {
                return;
            }
            pageState.page = 1;
            payload = buildActionPayload(column.payload, row);
            pageState.filters = $.extend({}, pageState.filters, payload);
            clearNamedFilters(column.clearFilters || []);
            form.render();
            loadData();
        }
    }

    form.on('submit(moduleSearchSubmit)', function () {
        var filters = collectFilters();

        if (pageState.filters.searchtype && pageState.filters.userPId && !filters.userId && !filters.user_id) {
            filters.searchtype = pageState.filters.searchtype;
            filters.userPId = pageState.filters.userPId;
        }

        pageState.page = 1;
        pageState.filters = filters;
        loadData();
        return false;
    });

    $('.J_moduleReset').on('click', function () {
        $('.J_moduleFilter').val('');
        if ($('.J_moduleForm')[0]) {
            $('.J_moduleForm')[0].reset();
        }
        resetAllEnhancedUploads();
        $('.J_moduleRecordId').val('');
        pageState.page = 1;
        pageState.filters = {};
        clickedChain = [];
        renderChain([]);
        form.render();
        initEnhancedUpload();
        initDatePickers();
        loadData();
    });

    $(document).on('click', '.J_crmUploadClear', function () {
        var input = document.getElementById($(this).attr('data-upload-target'));
        if (input) {
            resetEnhancedUpload(input);
        }
    });

    $('#moduleTableBody').on('click', '.J_moduleRowAction', function () {
        var $btn = $(this);
        var rowIndex = parseInt($btn.attr('data-row-index'), 10);
        var actionIndex = parseInt($btn.attr('data-action-index'), 10);
        var action = rowActions[actionIndex] || {};
        var row = currentRows[rowIndex] || {};

        if (action.type === 'edit') {
            fillFormFromRow(row);
            return;
        }

        if (action.confirm) {
            layer.confirm(t(action.confirm), function (index) {
                runRowAction(action, row, rowIndex);
                layer.close(index);
            });
            return;
        }

        runRowAction(action, row, rowIndex);
    });

    $('#moduleTableBody').on('click', '.J_moduleCellAction', function () {
        var $link = $(this);
        var rowIndex = parseInt($link.attr('data-row-index'), 10);
        var columnIndex = parseInt($link.attr('data-column-index'), 10);
        var row = currentRows[rowIndex] || {};
        var column = columns[columnIndex] || {};

        runColumnAction(column, row);
    });

    $('#moduleSummary').on('click', '#moduleSummaryToggle', function () {
        summaryCollapsed = !summaryCollapsed;
        renderSummary(currentMeta.totalRow || currentMeta);
    });

    $('.J_moduleChartType').on('change', function () {
        var target = $(this).attr('data-chart-target');

        moduleChartTypes[target] = $(this).val() || 'bar';
        renderCharts(currentMeta.totalRow || currentMeta);
    });

    form.on('submit(moduleFormSubmit)', function (data) {
        var $form = $(data.form);

        if (!submitApiUrl) {
            return false;
        }

        if ($form.find('input[type="file"]').length) {
            submitMultipartForm($form);
        } else {
            submitJsonForm($form);
        }

        return false;
    });

    function boot() {
        if (typeof CrmLang !== 'undefined') {
            CrmLang.updateUI();
        }
        if (typeof CrmDateRange !== 'undefined') {
            CrmDateRange.init($page);
        }
        renderChartSelectors();
        form.render();
        initEnhancedUpload();
        initDatePickers();
        loadData();
    }

    if (typeof CrmLang !== 'undefined' && CrmLang.loadLanguage) {
        CrmLang.loadLanguage(CrmLang.getLocale()).then(boot).catch(boot);
    } else {
        boot();
    }
});
