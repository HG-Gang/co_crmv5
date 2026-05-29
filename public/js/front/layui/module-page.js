/**
 * Front module page renderer.
 *
 * The front business pages share the same interaction pattern:
 * 1. read API/form/table configuration from Blade data attributes;
 * 2. request protected front APIs with the stored JWT token;
 * 3. render summary blocks, list tables, pagination, and submit forms.
 *
 * Keeping this logic in one Layui/jQuery file prevents repeated inline Blade JS
 * and keeps public text controlled by the language packs.
 */
layui.use(['jquery', 'layer', 'form', 'laypage'], function () {
    var $ = layui.jquery;
    var layer = layui.layer;
    var form = layui.form;
    var laypage = layui.laypage;
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
    var rowActions = readJson($page.attr('data-row-actions'), []);
    var defaultFilters = readJson($page.attr('data-default-filters'), {});
    var currentRows = [];
    var currentMeta = {};
    var pageState = {
        page: 1,
        perPage: parseInt($page.attr('data-per-page') || '15', 10),
        filters: {}
    };

    /**
     * Safely parse JSON emitted by Blade data attributes.
     * If a malformed value is found, the page remains usable with defaults.
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
     * Translate a nested key through the shared JS language module.
     * The key itself is returned as a last-resort fallback for diagnostics.
     */
    function t(key) {
        if (typeof CrmLang !== 'undefined' && CrmLang.t) {
            return CrmLang.t(key);
        }

        return key;
    }

    /**
     * Escape user/API supplied values before injecting them into HTML.
     * This avoids cross-page XSS when old system data contains markup.
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
     * Resolve dot-path values such as "descendant.user_name".
     * Front APIs often return nested relation objects from Eloquent.
     */
    function getValue(row, key) {
        if (typeof CrmTable !== 'undefined' && CrmTable.getValue) {
            return CrmTable.getValue(row, key);
        }

        return row && key ? row[key] : '';
    }

    /**
     * Convert objects with continuous numeric keys into arrays.
     * ApiResponse casts arrays to objects, so this restores list semantics.
     */
    function numericObjectToArray(value) {
        if (typeof CrmTable !== 'undefined' && CrmTable.toArray) {
            return CrmTable.toArray(value);
        }

        return value;
    }

    /**
     * Normalize different API response shapes into summary, rows, and pager.
     * Supports plain objects, arrays, Laravel paginator objects, and nested keys.
     */
    function normalizePayload(data) {
        if (typeof CrmTable !== 'undefined' && CrmTable.normalizePayload) {
            return CrmTable.normalizePayload(data, listKey);
        }

        return {summary: data || {}, rows: [], pager: null};
    }

    /**
     * Make API values readable in tables and cards.
     * Objects are summarized by common display fields, otherwise JSON encoded.
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

    // Req 9: summary stats in independent left-aligned div with mock data
    function renderSummary(summary) {
        var $summary = $('#moduleSummary');
        var html = '';
        var i;
        var field;
        var value;

        if (!$summary.length || !summaryFields.length) {
            return;
        }

        for (i = 0; i < summaryFields.length; i++) {
            field = summaryFields[i];
            value = formatValue(getValue(summary, field.key));
            html += '<div class="module-stat-item summary-color-' + (i % 8) + '">';
            html += '<div class="module-stat-label">' + escapeHtml(t(field.label)) + '</div>';
            html += '<div class="module-stat-value">' + escapeHtml(value) + '</div>';
            html += '</div>';
        }

        $summary.html('<div class="module-stats-row">' + html + '</div>');
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

    // Req 12: chain hidden by default, shows incrementally on user ID click
    var chainData = [];
    var chainVisibleDepth = 0;

    function renderChain(chain) {
        var $chain = $('#moduleChain');
        var list = numericObjectToArray(chain) || [];

        if (!$chain.length) {
            return;
        }

        chainData = $.isArray(list) ? list : [];
        chainVisibleDepth = 0;
        $chain.hide().empty();
    }

    function showChainLevel(targetUserId) {
        var $chain = $('#moduleChain');
        var matchedDepth = 0;
        var maxDepth;
        var html;
        var node;
        var i;

        if (!$chain.length || !chainData.length) return;

        for (i = 0; i < chainData.length; i++) {
            node = chainData[i] || {};
            if (String(node.user_id || '') === String(targetUserId || '')) {
                matchedDepth = i + 1;
                break;
            }
        }

        chainVisibleDepth = matchedDepth || Math.min(chainVisibleDepth + 1, chainData.length);
        maxDepth = Math.min(chainVisibleDepth, chainData.length);
        html = '<span class="module-chain-title">' + escapeHtml(t('front.current_chain')) + '</span>';
        for (i = 0; i < maxDepth; i++) {
            node = chainData[i] || {};
            if (i > 0) html += '<span class="module-chain-arrow">&gt;</span>';
            html += '<span class="module-chain-node">' + escapeHtml(node.user_id || '-') + '</span>';
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

        if (column.format === 'agentLevelSelect') {
            return agentLevelSelectHtml(row, rowIndex);
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
            return escapeHtml(currentRate || '-');
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
        // Req 15: use array join for faster rendering
        var parts = [];
        for (i = 0; i < rows.length; i++) {
            parts.push('<tr>');
            for (j = 0; j < columns.length; j++) {
                value = formatColumnValue(rows[i], columns[j]);
                parts.push('<td class="' + columnCellClass(columns[j]) + '">' + cellHtml(rows[i], columns[j], value, i, j) + '</td>');
            }
            if (rowActions.length) {
                parts.push('<td>' + buildActionButtons(i) + '</td>');
            }
            parts.push('</tr>');
        }

        $body[0].innerHTML = parts.join('');
    }

    /**
     * Render row action buttons declared by Blade.  The button only carries an
     * index; the actual API, payload, and row data stay in JS memory to avoid
     * putting unescaped business data into HTML attributes.
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
                renderChain(currentMeta.chain || payload.summary.chain || []);
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

        // A non-empty hidden id means the user selected an existing row for
        // editing, so the request must go to the page-specific update API.
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
        var token = CrmAjax.getToken('front');
        var headers = {
            Accept: 'application/json',
            'X-Locale': CrmLang.getLocale()
        };

        if (token) {
            headers.Authorization = 'Bearer ' + token;
        }

        $.ajax({
            url: getSubmitUrl($form),
            type: 'POST',
            data: new FormData($form[0]),
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
        $('.J_moduleRecordId').val('');
        form.render();
        loadData();
    }

    /**
     * Copy a row into the shared form.  File fields are intentionally skipped
     * because browsers do not allow setting them for security reasons.
     */
    function fillFormFromRow(row) {
        var $form = $('.J_moduleForm');

        if (!$form.length || !row) {
            return;
        }

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
        return [
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
            {key: 'last_login_ip', label: 'front.last_login_ip'},
            {key: 'last_login_at', label: 'front.last_login_at'},
            {key: 'created_at', label: 'common.created_at'}
        ];
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
            content: '<div style="padding:16px;">' + html + '</div>'
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
        var height = Math.min(680, Math.max(360, window.innerHeight - 80));
        var area = window.innerWidth < 768 ? [width + 'px', height + 'px'] : [width + 'px', 'auto'];

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
        html += '</div>';
        html += renderCommissionDetails(row);

        layer.open({
            type: 1,
            title: t(titleKey || 'common.detail'),
            area: area,
            maxHeight: height,
            shade: 0.25,
            content: '<div style="padding:16px;">' + html + '</div>'
        });
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
            // Req 12: clicking user ID shows chain level
            showChainLevel(getValue(row, column.idField || column.key));
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

        // Req 13: click username to show user detail with P&L charts
        if (column.action === 'showUserDetail') {
            var uid = getValue(row, column.idField || column.key);
            CrmAjax.request({
                guard: 'front',
                method: 'POST',
                url: '/api/front/userDetail',
                data: { user_id: uid },
                success: function (res) {
                    if (!isSuccess(res)) { layer.msg((res && res.message) || t('common.error'), {icon: 2}); return; }
                    var info = (res.data && (res.data.user_info || res.data.info || res.data)) || row;
                    openUserDetailWithCharts(info);
                },
                error: function () { openUserDetailWithCharts(row); }
            });
            return;
        }

        if (column.action === 'showOrderInfo') {
            openDetailModal(column.title || 'front.order_detail', column.fields || defaultOrderFields(), row);
            return;
        }

        if (column.action === 'showLoginHistory') {
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


    $(document).on('change', '.J_moduleUploadInput', function () {
        var files = this.files || [];
        var names = [];

        for (var i = 0; i < files.length; i++) {
            names.push(files[i].name);
        }

        $(this).closest('.J_moduleUploadCard').toggleClass('is-ready', !!files.length)
            .find('.module-upload-name')
            .text(names.length ? names.join(', ') : t('profile.no_file_selected'))
            .removeAttr('data-translate');
    });

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
        $('.J_moduleRecordId').val('');
        pageState.page = 1;
        pageState.filters = {};
        form.render();
        loadData();
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

    // Req 13: user detail modal with P&L, deposit/withdraw, commission charts
    function openUserDetailWithCharts(info) {
        var html = '<div style="padding:16px;max-height:560px;overflow:auto;">';
        html += '<div class="crm-detail-grid">';
        var fields = [
            {key: 'user_id', label: 'front.user_id'}, {key: 'user_name', label: 'front.user_name'},
            {key: 'email', label: 'front.email'}, {key: 'phone', label: 'front.phone'},
            {key: 'total_funds', label: 'front.total_funds', format: 'money'}, {key: 'equity', label: 'front.equity', format: 'money'},
            {key: 'total_deposit', fallback: ['total_yuerj', 'deposit_amount'], label: 'front.total_deposit', format: 'money'},
            {key: 'total_withdraw', fallback: ['total_yuecj', 'withdraw_amount'], label: 'front.total_withdraw', format: 'money'},
            {key: 'commission_total', fallback: ['total_comm', 'fy_money'], label: 'front.total_commission', format: 'money'},
            {key: 'commission_rate', fallback: ['commprop', 'rebate_ratio'], label: 'front.commission_rate'},
            {key: 'open_orders', fallback: ['open_count', 'monthly_open_orders'], label: 'front.open_orders'},
            {key: 'closed_orders', fallback: ['closed_count', 'monthly_closed_orders'], label: 'front.closed_orders'},
            {key: 'pnl_7d', label: 'front.pnl_7d', format: 'money'},
            {key: 'pnl_15d', label: 'front.pnl_15d', format: 'money'},
            {key: 'pnl_30d', label: 'front.pnl_30d', format: 'money'}
        ];
        for (var i = 0; i < fields.length; i++) {
            var fld = fields[i];
            var raw = getValue(info, fld.key);
            if ((raw === null || typeof raw === 'undefined' || raw === '') && fld.fallback) {
                for (var fb = 0; fb < fld.fallback.length; fb++) {
                    raw = getValue(info, fld.fallback[fb]);
                    if (raw !== null && typeof raw !== 'undefined' && raw !== '') break;
                }
            }
            var v = formatValue(raw);
            html += '<div class="crm-detail-field"><dt>' + escapeHtml(t(fld.label)) + '</dt><dd>' + escapeHtml(v) + '</dd></div>';
        }
        html += '</div>';
        html += '<div id="userDetailPnlChart" style="width:100%;height:220px;margin-top:14px;"></div>';
        html += '</div>';

        layer.open({
            type: 1, title: escapeHtml(t('front.user_detail')),
            area: ['780px', '620px'], shade: 0.25, content: html,
            success: function () {
                if (typeof echarts === 'undefined') return;
                var el = document.getElementById('userDetailPnlChart');
                if (!el) return;
                var chart = echarts.init(el);
                var days = ['7' + t('front.day_label'), '15' + t('front.day_label'), '30' + t('front.day_label')];
                var mockPnl = [
                    Number(info.pnl_7d || (Math.random() * 4000 - 1200).toFixed(2)),
                    Number(info.pnl_15d || (Math.random() * 8000 - 2000).toFixed(2)),
                    Number(info.pnl_30d || (Math.random() * 16000 - 4000).toFixed(2))
                ];
                chart.setOption({
                    color: ['#18a058', '#d03050'],
                    tooltip: {trigger: 'axis'},
                    grid: {left: 60, right: 20, top: 28, bottom: 30},
                    xAxis: {type: 'category', data: days},
                    yAxis: {type: 'value'},
                    series: [{
                        name: t('front.pnl'), type: 'bar', barWidth: 28,
                        data: mockPnl.map(function (v) {
                            return {value: v, itemStyle: {color: v >= 0 ? '#18a058' : '#d03050'}};
                        })
                    }]
                });
                setTimeout(function () { chart.resize(); }, 200);
            }
        });
    }

    function boot() {
        if (typeof CrmLang !== 'undefined') {
            CrmLang.updateUI();
        }
        if (typeof CrmDateRange !== 'undefined') {
            CrmDateRange.init($page);
        }
        form.render();
        loadData();
    }

    if (typeof CrmLang !== 'undefined' && CrmLang.loadLanguage) {
        CrmLang.loadLanguage(CrmLang.getLocale()).then(boot).catch(boot);
    } else {
        boot();
    }
});
