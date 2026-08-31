// Created by PhpStorm.
// Project name co_crmv5.
// User: Huang Gang
// Date: 2026/08/28
// Time: 01:18
/**
 * 前台模块页渲染器。
 *
 * 前台业务页的交互模式基本一致：
 * 1. 从 Blade data 属性读取接口、表单和表格配置；
 * 2. 使用保存好的 JWT token 请求受保护的前台接口；
 * 3. 渲染汇总区、列表表格、分页和提交表单。
 *
 * 把这些逻辑集中在一个 Layui/jQuery 文件里，避免每个 Blade 页面重复写内联 JS，
 * 也让公开文案统一交给语言包管理。
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
    var requestMethod = $page.attr('data-method') || 'POST';
    var submitApiUrl = $page.attr('data-submit-api') || '';
    var verificationApiUrl = $page.attr('data-verification-api') || '';
    var verificationCodeApiUrl = $page.attr('data-verification-code-api') || '';
    var editApiUrl = $page.attr('data-edit-api') || '';
    var editMethod = $page.attr('data-edit-method') || 'POST';
    var listKey = $page.attr('data-list-key') || '';
    var timelineType = $page.attr('data-timeline') || '';
    var initialNewsId = parseInt($page.attr('data-initial-news-id') || '0', 10);
    var initialNewsOpened = false;
    var columns = readJson($page.attr('data-columns'), []);
    var summaryFields = readJson($page.attr('data-summary-fields'), []);
    var chartGroups = readJson($page.attr('data-chart-groups'), []);
    var comparisonTableKey = $page.attr('data-comparison-table') || 'funds_comparison';
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
    var latestLoadSerial = 0;
    var latestDetailSerial = 0;
    var pendingOrderDetailRequests = {};
    var pageState = {
        page: 1,
        perPage: parseInt($page.attr('data-per-page') || '15', 10),
        filters: {}
    };
    var API = {
        tradeSymbols: '/api/front/trade-symbols',
        commissionTransferAgents: '/api/front/commissions/transfer-agent-options',
        agentSubList: '/api/front/agents/direct',
        agentCustomerList: '/api/front/agents/direct-customers',
        usersShow: '/api/front/users/{user}',
        directUserCommTrans: '/api/front/customers/commission-transfers',
        agentGroupChange: '/api/front/agents/group-change-applications'
    };
    var dynamicOptionRoutes = {
        symbols: '/api/front/trade-symbols',
        direct_agents: '/api/front/commissions/transfer-agent-options'
    };
    var dynamicOptionMethods = {
        symbols: 'GET',
        direct_agents: 'GET'
    };
    var dynamicOptionCache = {};

    function apiTemplate(url, params) {
        var resolved = String(url || '');

        $.each(params || {}, function (key, value) {
            resolved = resolved.replace(new RegExp('\\{' + key + '\\}', 'g'), encodeURIComponent(value));
        });

        return resolved;
    }

    function normalizeOptionList(payload) {
        var data = payload && payload.data ? payload.data : payload;
        var list = [];

        if ($.isArray(data)) {
            list = data;
        } else if (data && $.isArray(data.list)) {
            list = data.list;
        } else if (data && data.list && $.isArray(data.list.data)) {
            list = data.list.data;
        } else if (data && $.isArray(data.items)) {
            list = data.items;
        }

        return $.map(list, function (item) {
            var value = item && typeof item === 'object' ? (item.value || item.id || item.symbol || item.name) : item;
            var label = item && typeof item === 'object' ? (item.label || item.text || item.symbol || item.name || value) : item;

            if (value === null || typeof value === 'undefined' || value === '') {
                return null;
            }

            return {
                value: String(value),
                label: String(label || value)
            };
        });
    }

    function renderDynamicOptions($select, items) {
        var current = $select.val() || '';
        var legacyTarget = $.trim($page.attr('data-legacy-target-user-id') || '');
        var selectName = $select.attr('name') || '';
        var placeholderKey = $select.closest('.J_moduleForm').length ? 'common.select' : 'common.all';
        var html = '<option value="" data-translate="' + placeholderKey + '">' + escapeHtml(t(placeholderKey)) + '</option>';

        if (!current && legacyTarget && (selectName === 'sub_agent_id' || selectName === 'depositId')) {
            current = legacyTarget;
        }

        $.each(items || [], function (_, item) {
            html += '<option value="' + escapeHtml(item.value) + '">' + escapeHtml(item.label) + '</option>';
        });
        if (current && html.indexOf('value="' + escapeHtml(current) + '"') === -1) {
            html += '<option value="' + escapeHtml(current) + '">' + escapeHtml(current) + '</option>';
        }

        $select.html(html);
        $select.val(current);
        form.render('select');
    }

    /**
     * 使用列表接口响应里的元数据填充没有独立选项接口的下拉框。
     *
     * 适用场景：
     * - group-change 页面把 available_groups 随列表响应返回，避免额外请求一次组配置。
     * - 只处理当前响应确实提供的字段，避免覆盖其它动态选项接口已经加载的内容。
     */
    function renderResponseDynamicOptions() {
        $('[data-dynamic-options]').each(function () {
            var $select = $(this);
            var key = $select.attr('data-dynamic-options');
            var items;

            if (!key || !Object.prototype.hasOwnProperty.call(currentMeta, key)) {
                return;
            }

            items = currentMeta[key];
            if (!$.isArray(items)) {
                return;
            }

            renderDynamicOptions($select, normalizeOptionList(items));
        });
    }

    function loadDynamicFilterOptions() {
        $('[data-dynamic-options]').each(function () {
            var $select = $(this);
            var key = $select.attr('data-dynamic-options');
            var route = dynamicOptionRoutes[key];

            if (!key || !route) {
                return;
            }
            if (dynamicOptionCache[key]) {
                renderDynamicOptions($select, dynamicOptionCache[key]);
                return;
            }

            CrmAjax.request({
                guard: 'front',
                method: dynamicOptionMethods[key] || 'POST',
                url: route,
                data: {},
                success: function (res) {
                    if (!isSuccess(res)) {
                        return;
                    }
                    dynamicOptionCache[key] = normalizeOptionList(res);
                    renderDynamicOptions($select, dynamicOptionCache[key]);
                }
            });
        });
    }

    // 前台模块页之间的跳转统一由 PHP 注入的命名路由生成，避免页面地址调整后 JS 内部残留旧路径。
    function frontPageRouteUrl(routeName, params) {
        if (typeof crmRoute === 'function') {
            return crmRoute(routeName, params || {}, '');
        }

        return '';
    }

    /**
     * 安全解析 Blade data 属性里输出的 JSON。
     * 如果值损坏，就回退到默认值，页面仍然可以继续使用。
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

    // 从列表快捷入口跳转到表单页时，用 localStorage 暂存一次性预填值。
    // 读取后立即删除，避免用户下次手动打开页面还带着上一次的客户 ID。
    function savePendingFormValues(key, values) {
        try {
            localStorage.setItem('crm_layui_pending_' + key, JSON.stringify(values || {}));
        } catch (e) {}
    }

    function consumePendingFormValues(key) {
        var storageKey = 'crm_layui_pending_' + key;
        var raw = '';

        try {
            raw = localStorage.getItem(storageKey) || '';
            localStorage.removeItem(storageKey);
        } catch (e) {
            raw = '';
        }
        if (!raw) {
            return {};
        }
        try {
            return JSON.parse(raw) || {};
        } catch (e) {
            return {};
        }
    }

    function applyPendingFormValues() {
        var values = {};

        if (/\/commissions\/transfers/i.test(submitApiUrl) || $page.hasClass('commission-transfer-module')) {
            values = consumePendingFormValues('commission-transfer');
        } else if (/\/agents\/group-change-applications/i.test(submitApiUrl) || $page.hasClass('agent-group-change-module')) {
            values = consumePendingFormValues('group-change');
        }

        Object.keys(values).forEach(function (name) {
            $('.J_moduleForm [name="' + name + '"]').val(values[name]);
        });
        if (Object.keys(values).length) {
            form.render();
        }
    }

    /**
     * 把旧详情路由的目标客户 ID 回填到现代转组表单。
     *
     * 逻辑说明：
     * - 详情路由通过 data-legacy-target-user-id 传入旧页面的 uid。
     * - 只有目标表单为空时才回填，优先保留列表快捷入口暂存的 pending 值。
     */
    function applyLegacyTargetUserId() {
        var legacyTarget = $.trim($page.attr('data-legacy-target-user-id') || '');
        var $targetInput = $('.J_moduleForm [name="target_user_id"]');

        if (!$targetInput.length || $.trim($targetInput.val())) {
            return;
        }
        if (!$page.hasClass('agent-group-change-module') || !/^[1-9][0-9]*$/.test(legacyTarget)) {
            return;
        }

        $targetInput.val(legacyTarget);
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
     * 在写入 HTML 前先转义用户或接口返回的值，
     * 避免旧系统数据里带标签时触发跨页 XSS。
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
     * 解析类似 descendant.user_name 这样的点路径值。
     * 前台接口经常直接返回 Eloquent 的嵌套关联对象。
     */
    function getValue(row, key) {
        var parts;
        var current;
        var i;

        if (typeof CrmTable !== 'undefined' && CrmTable.getValue) {
            return CrmTable.getValue(row, key);
        }

        if (!row || !key) {
            return '';
        }

        parts = String(key).split('.');
        current = row;
        for (i = 0; i < parts.length; i++) {
            if (current === null || typeof current === 'undefined') {
                return '';
            }
            current = current[parts[i]];
        }

        return current;
    }

    /**
     * 把连续数字 key 的对象转回数组。
     * ApiResponse 可能把数组转成对象，这里负责恢复列表语义。
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
     * 让接口值在表格和卡片里更容易阅读。对象优先取常见展示字段，
     * 不认识的对象则转成 JSON。
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

        var code = Number(res && res.code);

        return !!res && !isNaN(code) && [
            1000, 1001, 1002, 1003, 1004,
            2000,
            3000, 3002, 3004, 3005
        ].indexOf(code) !== -1;
    }

    function isSuccessOrLegacy(res) {
        var code = Number(res && res.code);

        // A numeric 2xxx code is a definitive business failure. Do not let a
        // stale legacy SUCCESS marker turn it into a successful submission.
        if (res && res.code !== undefined && res.code !== null && res.code !== '' &&
            !isNaN(code) && code >= 2000 && code < 3000 && code !== 2000) {
            return false;
        }

        return isSuccess(res) || !!(res && (res.status === true || res.msg === 'SUC' || res.msg === 'SUCCESS'));
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

    function accountComparisonTableHtml(rows) {
        rows = numericObjectToArray(rows) || [];
        if (!$.isArray(rows) || !rows.length) {
            return '';
        }

        return '<div class="module-comparison-table">' +
            '<div class="module-comparison-title">' + escapeHtml(t('front.account_comparison_table')) + '</div>' +
            '<table class="layui-table" lay-size="sm">' +
                '<thead><tr><th>' + escapeHtml(t('front.funds_profile')) + '</th><th>' + escapeHtml(t('front.amount')) + '</th></tr></thead>' +
                '<tbody>' + rows.map(function (row) {
                    return '<tr><td>' + escapeHtml(t(row.label || row.key)) + '</td><td>' + escapeHtml(numeric(row.value).toFixed(2)) + '</td></tr>';
                }).join('') + '</tbody>' +
            '</table>' +
        '</div>';
    }

    function renderComparisonTable(summary) {
        var $target = $('#moduleComparisonTable');
        var rows;

        if (!$target.length || !comparisonTableKey) {
            return;
        }

        rows = getValue(summary || {}, comparisonTableKey);
        $target.html(accountComparisonTableHtml(rows));
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
            : '<span data-lucide="file-text" class="crm-upload-file-icon"></span>';

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
        $('#' + id + '_status').text(uploadEmptyText()).removeClass('has-file is-error');
        $('[data-upload-target="' + id + '"]').removeClass('is-visible');
        // 复位共享上传块的进度条与行内错误，保证下一次选择从干净状态开始。
        $input.closest('[data-crm-upload]').removeClass('has-file')
            .find('[data-upload-progress]').removeClass('is-visible').attr('aria-valuenow', '0')
            .find('[data-upload-progress-bar]').css('width', '0%');
        if (window.CrmFieldErrors && fieldName) {
            window.CrmFieldErrors.clearUpload(document, fieldName);
        }
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
                drag: true,
                size: parseInt($input.attr('data-max-size') || '4096', 10),
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
                        .addClass('has-file')
                        .removeClass('is-error');
                    $('[data-upload-target="' + id + '"]').addClass('is-visible');
                    $input.closest('[data-crm-upload]').addClass('has-file');
                    // 重新选择文件后立即清掉上一次的行内错误，避免过期提示留在界面上。
                    if (window.CrmFieldErrors) {
                        window.CrmFieldErrors.clearUpload(document, fieldName);
                    }
                }
            });
        });
    }

    function uploadFieldTitle($input) {
        var text = $input.closest('.layui-form-item').find('.layui-form-label').first().text();

        text = $.trim(String(text || '').replace(/\*/g, ''));
        return text || $input.attr('name') || t('front.choose_file');
    }

    function validateEnhancedUploads($form) {
        var passed = true;

        // Layui upload.render 选择文件后会把 File 对象放到 moduleUploadFiles。
        // 提交前先校验必填上传项，避免隐藏 file input 被浏览器原生校验跳过。
        $form.find('.J_crmUploadInput[required], .J_crmUploadInput[lay-verify*="required"]').each(function () {
            var $input = $(this);
            var fieldName = $input.attr('name');
            var files = moduleUploadFiles[fieldName];
            var nativeFiles = this.files || [];
            var hasFile = $.isArray(files) ? files.length > 0 : !!files;
            var offset;

            hasFile = hasFile || nativeFiles.length > 0;

            if (hasFile) {
                return;
            }

            passed = false;
            // 必传上传项缺失时把提示锚定到对应上传块，而不是弹出与字段无关的全局提示。
            if (window.CrmFieldErrors) {
                window.CrmFieldErrors.showUpload(
                    $form[0],
                    fieldName,
                    t('front.upload_required_message').replace('{field}', uploadFieldTitle($input))
                );

                return false;
            }
            layer.msg(t('front.upload_required_message').replace('{field}', uploadFieldTitle($input)), {icon: 2});
            offset = $input.closest('.layui-form-item').offset();
            if (offset) {
                $('html, body').animate({
                    scrollTop: Math.max(0, offset.top - 80)
                }, 160);
            }
            return false;
        });

        return passed;
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
        if (url.indexOf('storage/') === 0) {
            return '/' + url.replace(/^\/+/, '');
        }
        if (/^(uploads|images|demo)\//i.test(url)) {
            return '/' + url.replace(/^\/+/, '');
        }

        return '/storage/' + url.replace(/^\/+/, '');
    }

    function imageIconsHtml(key, value) {
        var images = /(^|_)(image|images|avatar|photo|voucher|url)(_|$)/i.test(key || '') ? parseImages(value) : [];

        if (!images.length) {
            return '';
        }

        return '<span class="crm-image-icons">' + images.map(function (src, index) {
            var url = imageUrl(src);
            return '<a href="' + escapeHtml(url) + '" data-image-preview="' + escapeHtml(url) + '" title="' + escapeHtml(t('front.images') + ' ' + (index + 1)) + '">查看</a>';
        }).join('') + '</span>';
    }

    function openImagePreview(url) {
        if (!url || url === '#') {
            return;
        }

        layer.open({
            type: 1,
            title: t('front.images'),
            area: [Math.min(860, Math.max(320, window.innerWidth - 32)) + 'px', 'auto'],
            skin: 'crm-responsive-layer',
            shade: 0.25,
            content: '<div class="crm-responsive-layer-body crm-image-preview-layer"><img src="' + escapeHtml(url) + '" alt=""></div>'
        });
    }

    // 图表查看方式按钮：按当前生效类型同步选中态、aria-pressed 和多语言标签，
    // 与控制台的 dashboard-chart-type 保持同一交互约定。
    function renderChartSelectors() {
        var labels = {
            bar: t('front.chart_bar'),
            line: t('front.chart_line'),
            area: t('front.chart_area'),
            pie: t('front.chart_pie')
        };

        $('.J_moduleChartType').each(function () {
            var $button = $(this);
            var target = $button.attr('data-chart-target');
            var type = $button.attr('data-chart-type') || 'bar';
            var current = moduleChartTypes[target] || defaultChartType(target);
            var label = labels[type] || labels.bar;
            var isActive = type === current;

            $button.toggleClass('is-active', isActive)
                .attr('aria-pressed', isActive ? 'true' : 'false')
                .attr('aria-label', label)
                .attr('title', label);
            $button.find('.crm-sr-only').text(label);
        });
    }

    /**
     * 读取某个图表容器的默认查看方式。
     *
     * @param {string} target 图表容器 DOM id。
     * @return {string} 默认类型，缺省为 bar。
     */
    function defaultChartType(target) {
        var i;

        for (i = 0; i < chartGroups.length; i++) {
            if (chartGroups[i] && chartGroups[i].target === target) {
                return chartGroups[i].defaultType || 'bar';
            }
        }

        return 'bar';
    }

    function chartOption(title, values, type) {
        var option = {
            color: ['#2080f0', '#18a058', '#d97706', '#7c3aed', '#ef4444', '#0e7a83'],
            tooltip: {trigger: 'item', confine: true},
            animationDuration: 450,
            animationEasing: 'cubicOut'
        };
        var maxValue = Math.max.apply(null, values.map(function (item) { return numeric(item.value); }).concat([10]));

        if (type === 'pie') {
            option.legend = {bottom: 0, textStyle: {color: '#475467'}};
            option.series = [{
                name: title,
                type: 'pie',
                radius: ['30%', '64%'],
                center: ['50%', '48%'],
                avoidLabelOverlap: true,
                label: {show: true, formatter: '{b}: {d}%'},
                labelLine: {smooth: true, length: 12, length2: 8},
                itemStyle: {borderRadius: 6, borderColor: '#fff', borderWidth: 2},
                data: values
            }];
            return option;
        }

        option.tooltip = {trigger: 'axis', confine: true, axisPointer: {type: type === 'bar' ? 'shadow' : 'line'}};
        option.grid = {left: 54, right: 18, top: 28, bottom: 42};
        option.xAxis = {
            type: 'category',
            data: values.map(function (item) { return item.name; }),
            axisTick: {show: false},
            axisLabel: {color: '#667085'},
            axisLine: {lineStyle: {color: '#d9e2ec'}}
        };
        option.yAxis = {
            type: 'value',
            axisLabel: {color: '#667085'},
            splitLine: {lineStyle: {color: '#eef2f7', type: 'dashed'}}
        };
        option.series = [{
            name: title,
            type: type === 'area' ? 'line' : type,
            smooth: type !== 'bar',
            areaStyle: type === 'area' ? {opacity: 0.18} : null,
            barWidth: type === 'bar' ? 18 : null,
            itemStyle: {borderRadius: [8, 8, 2, 2]},
            lineStyle: {width: 2},
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

    // 根据后端返回的祖先链路或当前点击顺序，生成只包含用户 ID 的可见链路。
    function chainIdsFromRow(row, clickedId) {
        var source = Array.isArray(row && row.chain) ? row.chain : [];
        var ids = source.map(function (item) {
            return String(item && typeof item === 'object' ? (item.user_id || item.userId || item.id || '') : item || '').trim();
        }).filter(Boolean);
        var normalizedId = String(clickedId || '').trim();
        var sourceIndex;
        var currentIndex;

        if (!normalizedId) {
            return ids;
        }

        sourceIndex = ids.indexOf(normalizedId);
        if (sourceIndex >= 0) {
            return ids.slice(0, sourceIndex + 1);
        }

        if (!ids.length && clickedChain.length) {
            ids = clickedChain.slice();
        }

        currentIndex = ids.indexOf(normalizedId);
        if (currentIndex >= 0) {
            return ids.slice(0, currentIndex + 1);
        }

        ids.push(normalizedId);
        return ids;
    }

    // 用户 ID 列点击时才更新链路；展示内容只保留 ID，不带用户名或代理等级。
    function updateClickedChain(row, clickedId) {
        clickedChain = chainIdsFromRow(row, clickedId);
        renderChain([]);
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
        var html = column.actionLabel ? escapeHtml(t(column.actionLabel)) : escapeHtml(value);
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
            return escapeHtml(formatValue(currentRate || currentLevel || ''));
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

    function stripHtml(value) {
        return String(value || '').replace(/<[^>]*>/g, ' ').replace(/\s+/g, ' ').trim();
    }

    function newsExcerpt(row) {
        var content = stripHtml(getValue(row, 'content') || getValue(row, 'news_content'));

        if (content.length > 160) {
            return content.slice(0, 160) + '...';
        }

        return content;
    }

    function renderNewsTimeline(rows) {
        var $timeline = $('#moduleNewsTimeline');
        var html = '';
        var i;
        var row;

        if (!$timeline.length) {
            return;
        }

        currentRows = rows || [];

        if (!currentRows.length) {
            $timeline.html('<li class="layui-timeline-item"><div class="layui-timeline-content layui-text module-empty">' + escapeHtml(t('common.noData')) + '</div></li>');
            return;
        }

        for (i = 0; i < currentRows.length; i++) {
            row = currentRows[i] || {};
            html += '<li class="layui-timeline-item module-news-item">';
            // 时间轴节点使用 Lucide 圆点，不依赖 Layui 私有字体编码，字体加载失败时也不会显示乱码。
            html += '<i data-lucide="circle" class="layui-timeline-axis"></i>';
            html += '<div class="layui-timeline-content layui-text">';
            html += '<h3 class="module-news-title">' + escapeHtml(getValue(row, 'title') || getValue(row, 'news_title') || '-') + '</h3>';
            html += '<p class="module-news-meta">' + escapeHtml(getValue(row, 'author_name') || '-') + ' · ' + escapeHtml(getValue(row, 'created_at') || getValue(row, 'rec_upd_date') || '-') + '</p>';
            html += '<p class="module-news-excerpt">' + escapeHtml(newsExcerpt(row)) + '</p>';
            html += '</div></li>';
        }

        $timeline.html(html);
    }

    function openNewsDetailModal(row) {
        var newsId = parseInt(getValue(row, 'news_id') || getValue(row, 'id') || '0', 10);

        if (!newsId) {
            return;
        }

        layer.open({
            type: 2,
            title: t('front.news_detail'),
            area: [Math.min(920, Math.max(320, window.innerWidth - 32)) + 'px', Math.min(720, Math.max(360, window.innerHeight - 48)) + 'px'],
            skin: 'crm-responsive-layer',
            shade: 0.25,
            content: '/user/news/news_detail/' + encodeURIComponent(newsId)
        });
    }

    /**
     * 渲染 Blade 中声明的行操作按钮。按钮本身只带索引，真正的接口、参数和行数据都留在 JS 内存里，
     * 避免把未转义的业务数据写入 DOM 属性。
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

    function realtimeVisibleRows(rows) {
        // 实时返佣接口在旧系统里偶尔会忽略分页参数返回大量数据。
        // 这里只限制当前页渲染数量，避免一次性拼接过多 DOM 导致页面卡顿。
    if (!$page.hasClass('commission-realtime-module') || !$.isArray(rows)) {
            return rows;
        }

        return rows.slice(0, Math.max(1, pageState.perPage));
    }

    function loadData() {
        var requestSerial;

        if (!apiUrl) {
            return;
        }

        requestSerial = ++latestLoadSerial;
        CrmAjax.request({
            guard: 'front',
            method: requestMethod,
            url: apiUrl,
            data: buildRequestData(),
            success: function (res) {
                var payload;
                var summary;

                if (requestSerial !== latestLoadSerial) {
                    return;
                }
                if (!isSuccess(res)) {
                    layer.msg(res.message || t('common.error'), {icon: 2});
                    return;
                }

                payload = normalizePayload(res.data);
                currentMeta = res.data || {};
                renderResponseDynamicOptions();
                if (payload.serverTotalRow) {
                    currentMeta.totalRow = payload.serverTotalRow;
                }
                summary = payload.summary && !payload.serverTotalRow
                    ? payload.summary
                    : (payload.serverTotalRow || payload.summary);
                renderSummary(summary);
                renderComparisonTable(summary);
                renderCharts(summary);
                renderChain([]);
                if (timelineType === 'news') {
                    renderNewsTimeline(payload.rows);
                    if (initialNewsId > 0 && !initialNewsOpened) {
                        var initialNewsRow = null;
                        $.each(payload.rows || [], function (_, row) {
                            var rowId = parseInt(getValue(row, 'news_id') || getValue(row, 'id') || '0', 10);
                            if (rowId === initialNewsId) {
                                initialNewsRow = row;
                                return false;
                            }
                        });
                        if (initialNewsRow) {
                            initialNewsOpened = true;
                            openNewsDetailModal(initialNewsRow);
                            dispatchModulePageEvent('crm:news-initial-detail-opened', {newsId: initialNewsId});
                        }
                    }
                } else {
                    renderTable(realtimeVisibleRows(payload.rows));
                }
                renderPager(payload.pager);
                dispatchModulePageEvent('crm:module-page-loaded', {
                    summary: summary || {},
                    meta: currentMeta
                });
            },
            error: function () {
                if (requestSerial !== latestLoadSerial) {
                    return;
                }
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

    function isCommissionTransferSubmit(url) {
        return /\/commissions\/transfers(?:\?|$)|\/customers\/commission-transfers(?:\?|$)|\/user\/proxy\/directUserCommTrans(?:\?|$)/i.test(String(url || ''));
    }

    function newCommissionTransferKey() {
        var cryptoApi = window.crypto || window.msCrypto;
        var bytes;
        var index;
        var hex = '';

        if (cryptoApi && typeof cryptoApi.randomUUID === 'function') {
            return 'ct-' + cryptoApi.randomUUID();
        }
        if (!cryptoApi || typeof cryptoApi.getRandomValues !== 'function') {
            return '';
        }
        bytes = new Uint8Array(32);
        cryptoApi.getRandomValues(bytes);
        for (index = 0; index < bytes.length; index++) {
            hex += ('0' + bytes[index].toString(16)).slice(-2);
        }

        return 'ct-' + hex;
    }

    function ensureCommissionTransferKey($form) {
        var $input = $form.find('input[name="idempotency_key"]').first();
        var $external = $('[data-commission-transfer-intent]').first();
        var key = $.trim($input.val() || ($external.length ? $external.val() : ''));

        if (!key) {
            key = newCommissionTransferKey();
        }
        if (!/^[A-Za-z0-9._:-]{1,100}$/.test(key)) {
            return '';
        }
        if (!$input.length) {
            $input = $('<input type="hidden" name="idempotency_key">').appendTo($form);
        }
        $input.val(key);
        if ($external.length) {
            $external.val(key);
        }

        return key;
    }

    function getSubmitRecordId($form) {
        return $.trim($form.find('.J_moduleRecordId').val() || '');
    }

    function getSubmitUrl($form) {
        var recordId = getSubmitRecordId($form);

        if (recordId && editApiUrl) {
            return apiTemplate(editApiUrl, {id: recordId});
        }

        return submitApiUrl;
    }

    function getSubmitMethod($form) {
        var recordId = $.trim($form.find('.J_moduleRecordId').val() || '');

        if (recordId && editApiUrl) {
            return editMethod;
        }

        return 'POST';
    }

    function submitJsonForm($form) {
        var url = getSubmitUrl($form);
        var data = formArrayToObject($form.serializeArray());
        var headers = {};
        var key;

        if (isCommissionTransferSubmit(url)) {
            key = ensureCommissionTransferKey($form);
            if (!key) {
                layer.msg(t('response.validation_failed'), {icon: 2});
                return;
            }
            data.idempotency_key = key;
            headers['Idempotency-Key'] = key;
        }
        CrmAjax.request({
            guard: 'front',
            method: getSubmitMethod($form),
            url: url,
            data: data,
            headers: headers,
            success: afterSubmit,
            error: function () {
                layer.msg(t('common.error'), {icon: 2});
            }
        });
    }

    /**
     * 向当前 Blade 模块广播数据生命周期事件。
     *
     * 账户类型等页面专用脚本只消费已经加载的数据，不再重复请求同一资料接口；
     * detail 返回 summary 和原始 meta，便于专用组件按自己的展示契约读取。
     *
     * @param {string} eventName 事件名称，例如 crm:module-page-loaded。
     * @param {Object} detail 事件数据，summary 为规范化汇总数据。
     * @return {void}
     */
    function dispatchModulePageEvent(eventName, detail) {
        var pageElement = $page.get(0);
        var event;

        if (!pageElement) {
            return;
        }

        if (typeof window.CustomEvent === 'function') {
            pageElement.dispatchEvent(new window.CustomEvent(eventName, {detail: detail || {}}));
            return;
        }

        // 兼容不支持 CustomEvent 构造器的旧浏览器，但仍保持同一 detail 契约。
        event = document.createEvent('CustomEvent');
        event.initCustomEvent(eventName, false, false, detail || {});
        pageElement.dispatchEvent(event);
    }

    function requestCancelVerificationCode($form, $button) {
        if (!verificationApiUrl || !verificationCodeApiUrl) {
            return;
        }

        $button.addClass('layui-btn-disabled').prop('disabled', true);
        CrmAjax.request({
            guard: 'front',
            method: 'POST',
            url: verificationApiUrl,
            data: formArrayToObject($form.serializeArray()),
            success: function (res) {
                if (!isSuccessOrLegacy(res)) {
                    layer.msg((res && (res.message || res.msg)) || t('response.validation_failed'), {icon: 2});
                    $button.removeClass('layui-btn-disabled').prop('disabled', false);
                    return;
                }

                CrmAjax.request({
                    guard: 'front',
                    method: 'POST',
                    url: verificationCodeApiUrl,
                    data: formArrayToObject($form.serializeArray()),
                    success: function (codeRes) {
                        if (!isSuccessOrLegacy(codeRes)) {
                            layer.msg((codeRes && (codeRes.message || codeRes.msg)) || t('common.error'), {icon: 2});
                            $button.removeClass('layui-btn-disabled').prop('disabled', false);
                            return;
                        }

                        layer.msg((codeRes && (codeRes.message || codeRes.msg)) || t('front.email_code_sent'), {icon: 1});
                        $button.removeClass('layui-btn-disabled').prop('disabled', false);
                    },
                    error: function () {
                        layer.msg(t('common.error'), {icon: 2});
                        $button.removeClass('layui-btn-disabled').prop('disabled', false);
                    }
                });
            },
            error: function () {
                layer.msg(t('common.error'), {icon: 2});
                $button.removeClass('layui-btn-disabled').prop('disabled', false);
            }
        });
    }

    function submitMultipartForm($form) {
        // 把可见表单值和缓存的文件对象合并成一个请求体。这样上传提交行为更确定，
        // 后端校验必填文件时也更容易排查。
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

        CrmAjax.upload({
            guard: 'front',
            url: getSubmitUrl($form),
            formData: formData,
            success: afterSubmit,
            error: function () {
                layer.msg(t('common.error'), {icon: 2});
            }
        });
    }

    function afterSubmit(res) {
        if (!isSuccessOrLegacy(res)) {
            layer.msg((res && res.message) || t('common.error'), {icon: 2});
            return;
        }

        layer.msg(res.message || t('common.success'), {icon: 1});
        if (isCommissionTransferSubmit(submitApiUrl)) {
            $('[data-commission-transfer-intent]').val('');
        }
        if ($('.J_moduleForm')[0]) {
            $('.J_moduleForm')[0].reset();
        }
        resetAllEnhancedUploads();
        $('.J_moduleRecordId').val('');
        form.render();
        initEnhancedUpload();
        initDatePickers();
        loadData();
        if (/\/user\/proxy\/directUserCommTrans(?:\?|$)/i.test(submitApiUrl)) {
            window.setTimeout(function () {
                window.location.reload();
            }, 300);
        }
    }

    /**
     * 把一行数据回填到共享表单里。文件字段会刻意跳过，因为出于安全原因，
     * 浏览器不允许直接给 file 输入框赋值。
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

        if (action.type === 'detail') {
            openDetailModal(action.title || 'common.detail', action.fields || columns, row);
            return;
        }

        if (action.type === 'showOrderInfo') {
            openOrderDetail(action.title || 'front.order_detail', action.fields || defaultOrderFields(), row);
            return;
        }

        if (action.action === 'showOrderInfo') {
            openOrderDetail(action.title || 'front.order_detail', action.fields || defaultOrderFields(), row);
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
            payload.agent_gId = $option.data('choice-gid') || $option.val();
            payload.comm_prop = $option.data('comm-prop');
            payload.def_gid = $option.data('def-gid') || $option.val();
            payload.choice_gid = $option.data('choice-gid') || $option.val();
            payload.extra_val = $option.data('extra-val') || 0;
        }

        payload[idField] = idValue;
        CrmAjax.request({
            guard: 'front',
            method: action.method || 'POST',
            url: apiTemplate(action.api, {id: idValue}),
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

    function openListModal(titleKey, columns, rows) {
        var html = '<div class="crm-detail-subtable-wrap"><table class="crm-detail-subtable"><thead><tr>';
        var i;
        var j;
        var value;
        var width = Math.min(920, Math.max(320, window.innerWidth - 32));
        var area = [width + 'px', 'auto'];

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
            skin: 'crm-responsive-layer',
            shade: 0.25,
            content: '<div class="crm-responsive-layer-body">' + html + '</div>'
        });
    }

    function directRelationColumns(type) {
        var base = [
            {key: 'user_id', label: 'front.user_id'},
            {key: 'user_name', label: 'front.user_name'},
            {key: 'email', label: 'front.email'},
            {key: 'account_type_text', label: 'front.account_type'},
            {key: 'rec_crt_date', label: 'common.created_at'}
        ];

        if (type === 'agents') {
            return base.concat([
                {key: 'agentsTotal', label: 'front.agent_count'},
                {key: 'accountTotal', label: 'front.customer_count'},
                {key: 'commprop', label: 'front.commission_rate'}
            ]);
        }

        return base.concat([
            {key: 'mt4_balance', label: 'front.mt4_balance', format: 'money'},
            {key: 'cust_eqy', label: 'front.customer_equity', format: 'money'},
            {key: 'total_yuerj', label: 'front.total_deposit', format: 'money'},
            {key: 'total_yuecj', label: 'front.total_withdraw', format: 'money'}
        ]);
    }

    function openDirectRelationList(type, row) {
        var targetUserId = getValue(row, 'user_id') || getValue(row, 'userId');
        var endpoint = type === 'agents' ? API.agentSubList : API.agentCustomerList;
        var titleKey = type === 'agents' ? 'front.direct_agents' : 'front.direct_customers';

        if (!targetUserId) {
            layer.msg(t('response.validation_failed'), {icon: 2});
            return;
        }

        CrmAjax.request({
            guard: 'front',
            method: 'GET',
            url: endpoint,
            data: {
                parent_id: targetUserId,
                direct_only: 1,
                page: 1,
                per_page: 50,
                limit: 50
            },
            success: function (res) {
                var payload;

                if (!isSuccess(res)) {
                    layer.msg((res && res.message) || t('common.error'), {icon: 2});
                    return;
                }
                payload = normalizePayload(res.data);
                openListModal(titleKey, directRelationColumns(type), payload.rows || []);
            },
            error: function () {
                layer.msg(t('common.error'), {icon: 2});
            }
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

    function renderOrderChainDetails(row) {
        var chain = numericObjectToArray(getValue(row, 'order_chain')) || [];
        var html = '';
        var i;
        var node;
        var label;

        if (!$.isArray(chain) || !chain.length) {
            return '';
        }

        html += '<section class="crm-detail-section">';
        html += '<h3>' + escapeHtml(t('front.current_chain')) + '</h3>';
        html += '<div class="module-chain order-chain-detail">';
        for (i = 0; i < chain.length; i++) {
            node = chain[i] || {};
            label = [getValue(node, 'user_id'), getValue(node, 'user_name'), getValue(node, 'agent_level_name') || getValue(node, 'account_type_text')]
                .filter(function (value) { return value !== null && typeof value !== 'undefined' && value !== ''; })
                .join(' / ');
            if (i > 0) {
                html += '<span class="module-chain-arrow">&gt;</span>';
            }
            html += '<span class="module-chain-node">' + escapeHtml(label) + '</span>';
        }
        html += '</div></section>';

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
        var area = [width + 'px', 'auto'];

        latestDetailSerial += 1;
        fields = normalizeDetailFields(fields, row);
        for (i = 0; i < fields.length; i++) {
            field = fields[i];
            if (field.key === 'order_chain') {
                continue;
            }
            if (/^agent_level/i.test(field.key || '') && String(getValue(row, 'account_type')) !== '1') {
                continue;
            }
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
        html += renderOrderChainDetails(row);
        html += renderCommissionDetails(row);
        html += '</div>';

        return layer.open({
            type: 1,
            title: t(titleKey || 'common.detail'),
            area: area,
            skin: 'crm-responsive-layer',
            shade: 0.25,
            content: '<div class="crm-responsive-layer-body">' + html + '</div>'
          });
      }

    function customerGroupOptionsHtml() {
        var groups = currentMeta.available_groups || [];
        var html = '<option value="">' + escapeHtml(t('common.select')) + '</option>';

        $.each(groups, function (_, group) {
            var value = group && typeof group === 'object' ? (group.value || group.id || group.group_id) : group;
            var label = group && typeof group === 'object' ? (group.label || group.name || group.group_name || value) : group;

            if (value === null || typeof value === 'undefined' || value === '') {
                return;
            }
            html += '<option value="' + escapeHtml(value) + '">' + escapeHtml(label || value) + '</option>';
        });

        return html;
    }

    function customerActionModalHtml(action, userId) {
        var html = '<form class="layui-form layui-form-pane customer-action-form" data-customer-action-form="' + escapeHtml(action) + '" lay-filter="customerActionSubmit">';

        if (action === 'commission-transfer') {
            html += '<input type="hidden" name="sub_agent_id" value="' + escapeHtml(userId) + '">';
            html += '<div class="layui-form-item"><label class="layui-form-label">' + escapeHtml(t('front.target_user_id')) + '</label><div class="layui-input-block"><input type="text" class="layui-input" value="' + escapeHtml(userId) + '" disabled></div></div>';
            html += '<div class="layui-form-item"><label class="layui-form-label">' + escapeHtml(t('front.amount')) + '</label><div class="layui-input-block"><input type="number" name="amount" class="layui-input" min="0.01" step="0.01" lay-verify="required|number" autocomplete="off"></div></div>';
            html += '<div class="layui-form-item"><label class="layui-form-label">' + escapeHtml(t('auth.password')) + '</label><div class="layui-input-block"><input type="password" name="password" class="layui-input" lay-verify="required" autocomplete="new-password"></div></div>';
        } else {
            html += '<input type="hidden" name="target_user_id" value="' + escapeHtml(userId) + '">';
            html += '<div class="layui-form-item"><label class="layui-form-label">' + escapeHtml(t('front.target_user_id')) + '</label><div class="layui-input-block"><input type="text" class="layui-input" value="' + escapeHtml(userId) + '" disabled></div></div>';
            html += '<div class="layui-form-item"><label class="layui-form-label">' + escapeHtml(t('front.new_group_id')) + '</label><div class="layui-input-block"><select name="new_group_id" lay-verify="required">' + customerGroupOptionsHtml() + '</select></div></div>';
            html += '<div class="layui-form-item layui-form-text"><label class="layui-form-label">' + escapeHtml(t('front.apply_reason')) + '</label><div class="layui-input-block"><textarea name="reason" class="layui-textarea"></textarea></div></div>';
        }

        html += '<div class="layui-form-item"><div class="layui-input-block"><button class="layui-btn" lay-submit lay-filter="customerActionSubmit">' + escapeHtml(t('common.submit')) + '</button></div></div>';
        html += '</form>';

        return html;
    }

    function openCustomerActionModal(action, userId) {
        var titleKey = action === 'commission-transfer' ? 'front.commission_transfer' : 'front.group_change';

        layer.open({
            type: 1,
            title: t(titleKey),
            area: [Math.min(560, Math.max(320, window.innerWidth - 32)) + 'px', 'auto'],
            skin: 'crm-responsive-layer',
            shade: 0.25,
            content: '<div class="crm-responsive-layer-body">' + customerActionModalHtml(action, userId) + '</div>',
            success: function () {
                form.render();
            }
        });
    }

    function isRealtimeCommissionPage() {
        return $page.hasClass('commission-realtime-module') || apiUrl.indexOf('/commissions/realtime') !== -1;
    }

    function realtimeCommissionOrderId(row) {
        return getValue(row, 'ticket') || getValue(row, 'orderId') || getValue(row, 'order_no') || getValue(row, 'mt4_order_id');
    }

    function rowHasCommissionDetails(row) {
        var details = numericObjectToArray(getValue(row, 'commission_details')) || [];

        return $.isArray(details) && details.length > 0;
    }

    function openOrderDetail(titleKey, fields, row) {
        var orderId = realtimeCommissionOrderId(row);
        var shouldFetchRealtimeDetail = isRealtimeCommissionPage() && orderId && !rowHasCommissionDetails(row);
        var detailRequestKey = String(orderId || '');
        var layerIndex;
        var detailSerial;

        // 实时返佣列表首次加载不计算每一笔订单的返佣明细，避免进入页面时大量递归计算导致卡顿。
        // 用户点击订单详情后，再按订单号单独请求 detail_commission=1，把订单返佣详情按需补回弹框。
        if (shouldFetchRealtimeDetail && pendingOrderDetailRequests[detailRequestKey]) {
            return;
        }

        if (shouldFetchRealtimeDetail) {
            pendingOrderDetailRequests[detailRequestKey] = true;
        }

        layerIndex = openDetailModal(titleKey || 'front.order_detail', fields || defaultOrderFields(), row);
        detailSerial = latestDetailSerial;

        if (!shouldFetchRealtimeDetail) {
            return;
        }
        CrmAjax.request({
            guard: 'front',
            method: 'GET',
            url: apiUrl,
            data: $.extend({}, defaultFilters, pageState.filters, {
                orderId: orderId,
                detail_commission: 1,
                page: 1,
                per_page: 1
            }),
            success: function (res) {
                var payload;
                var detailRow;

                delete pendingOrderDetailRequests[detailRequestKey];
                if (detailSerial !== latestDetailSerial || !isSuccess(res)) {
                    return;
                }

                payload = normalizePayload(res.data);
                detailRow = $.extend({}, row, (payload.rows && payload.rows[0]) || {});
                if (layerIndex) {
                    layer.close(layerIndex);
                }
                openDetailModal(titleKey || 'front.order_detail', fields || defaultOrderFields(), detailRow);
            },
            error: function () {
                delete pendingOrderDetailRequests[detailRequestKey];
            }
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
                updateClickedChain(row, idValue);
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

        if (column.action === 'updateUserChain') {
            updateClickedChain(row, idValue || getValue(row, column.key));
            return;
        }

        if (column.action === 'showDirectAgents' || column.action === 'showDirectCustomers') {
            openDirectRelationList(column.action === 'showDirectAgents' ? 'agents' : 'customers', row);
            return;
        }

        if (column.action === 'showUserInfo') {
            var detailRow = getValue(row, 'user_info') || row;
            var userDetailApi = column.api || API.usersShow;
            var userDetailMethod = column.method || 'GET';
            var userDetailRouteParams = column.routeParams || {user: '{' + (column.idField || column.key) + '}'};

            if (column.chainAction) {
                updateClickedChain(row, idValue || getValue(row, column.key));
            }

            CrmAjax.request({
                guard: 'front',
                method: userDetailMethod,
                url: apiTemplate(userDetailApi, buildActionPayload(userDetailRouteParams, row)),
                data: userDetailMethod === 'GET' ? null : buildActionPayload(column.payload || {user_id: '{' + (column.idField || column.key) + '}'}, row),
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

        if (column.action === 'showOrderInfo') {
            openOrderDetail(column.title || 'front.order_detail', column.fields || defaultOrderFields(), row);
            return;
        }

        if (column.action === 'openCommissionTransfer' || column.action === 'openGroupChange') {
            if (!idValue) {
                layer.msg(t('response.validation_failed'), {icon: 2});
                return;
            }
            if (column.action === 'openCommissionTransfer') {
                openCustomerActionModal('commission-transfer', idValue);
                return;
            }
            openCustomerActionModal('group-change', idValue);
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

    function navigateFrontPage(url, title) {
        if (!url) {
            layer.msg(t('common.error'), {icon: 2});
            return;
        }
        if (window.parent && window.parent !== window) {
            window.parent.postMessage({
                type: 'crm:frame-navigate',
                url: url,
                title: title || ''
            }, window.location.origin);
            return;
        }
        window.location.href = url.replace('?frame=1', '');
    }

    form.on('submit(moduleSearchSubmit)', function () {
        var filters = collectFilters();
        var keepCurrentChain = false;

        if (pageState.filters.searchtype && pageState.filters.userPId && !filters.userId && !filters.user_id) {
            filters.searchtype = pageState.filters.searchtype;
            filters.userPId = pageState.filters.userPId;
            keepCurrentChain = true;
        }

        // 手动按用户 ID 或新条件搜索时清空旧链路；只有沿着当前用户 ID 下钻继续筛选时才保留。
    if (!keepCurrentChain) {
            clickedChain = [];
            renderChain([]);
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

    $(document).on('click', '[data-image-preview]', function (event) {
        event.preventDefault();
        openImagePreview($(this).attr('data-image-preview'));
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

    $('#moduleTableBody').on('click', '.J_moduleCellAction', function (event) {
        var $link = $(this);
        var rowIndex = parseInt($link.attr('data-row-index'), 10);
        var columnIndex = parseInt($link.attr('data-column-index'), 10);
        var row = currentRows[rowIndex] || {};
        var column = columns[columnIndex] || {};

        event.preventDefault();
        event.stopPropagation();
        runColumnAction(column, row);
    });

    $('#moduleSummary').on('click', '#moduleSummaryToggle', function () {
        summaryCollapsed = !summaryCollapsed;
        renderSummary(currentMeta.totalRow || currentMeta);
    });

    // 切换查看方式只用当前已加载的统计快照重绘，不重新请求接口。
    $('.J_moduleChartType').on('click', function () {
        var target = $(this).attr('data-chart-target');
        var type = $(this).attr('data-chart-type');

        if (!target || ['bar', 'line', 'area', 'pie'].indexOf(type) === -1) {
            return;
        }
        moduleChartTypes[target] = type;
        renderChartSelectors();
        renderCharts(currentMeta.totalRow || currentMeta);
    });

    $('.J_cancelCodeButton').on('click', function () {
        requestCancelVerificationCode($(this).closest('form'), $(this));
    });

    // 页面专用组件提交成功后通过事件要求统一加载器刷新，避免各组件复制鉴权和渲染请求。
    $page.get(0).addEventListener('crm:module-page-reload', function () {
        loadData();
    });

    form.on('submit(moduleFormSubmit)', function (data) {
        var $form = $(data.form);

        if (!submitApiUrl) {
            return false;
        }

        if (!validateEnhancedUploads($form)) {
            return false;
        }

        if ($form.find('input[type="file"]').length) {
            submitMultipartForm($form);
        } else {
            submitJsonForm($form);
        }

        return false;
    });

    form.on('submit(customerActionSubmit)', function (data) {
        var $form = $(data.form);
        var action = $form.attr('data-customer-action-form');
        var endpoint = action === 'commission-transfer' ? API.directUserCommTrans : API.agentGroupChange;
        var payload = formArrayToObject($form.serializeArray());
        var headers = {};
        var key;

        if (action === 'commission-transfer') {
            key = ensureCommissionTransferKey($form);
            if (!key) {
                layer.msg(t('response.validation_failed'), {icon: 2});
                return false;
            }
            payload.idempotency_key = key;
            headers['Idempotency-Key'] = key;
        }

        CrmAjax.request({
            guard: 'front',
            method: 'POST',
            url: endpoint,
            data: payload,
            headers: headers,
            success: function (res) {
                var actionSucceeded = action === 'commission-transfer'
                    ? isSuccessOrLegacy(res)
                    : (isSuccess(res) || String(res && res.msg) === 'SUCCESS' || Number(res && res.code) === 0);

                if (!actionSucceeded) {
                    layer.msg((res && (res.message || res.msg || res.errorType)) || t('common.error'), {icon: 2});
                    return;
                }
                layer.msg((res && (res.message || res.msg)) || t('common.success'), {icon: 1});
                layer.closeAll('page');
                loadData();
            },
            error: function () {
                layer.msg(t('common.error'), {icon: 2});
            }
        });

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
        loadDynamicFilterOptions();
        initEnhancedUpload();
        initDatePickers();
        applyPendingFormValues();
        applyLegacyTargetUserId();
        loadData();
    }

    if (typeof CrmLang !== 'undefined' && CrmLang.loadLanguage) {
        CrmLang.loadLanguage(CrmLang.getLocale()).then(boot).catch(boot);
    } else {
        boot();
    }
});
