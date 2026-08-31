// Created by PhpStorm.
// Project name co_crmv5.
// User: Huang Gang
// Date: 2026/08/29
// Time: 14:33
layui.define(['jquery', 'layer'], function(exports) {
    'use strict';

    var $ = layui.jquery;
    var layer = layui.layer;
    var tokenKeys = ['admin_token', 'admin_jwt_token'];
    var loginPath = '/admin-crmui/login';
    var sidebarBindingState = null;
    var permissionSlugs = null;

    function csrfToken() {
        return $('meta[name="csrf-token"]').attr('content') || '';
    }

    function getToken() {
        return localStorage.getItem(tokenKeys[0]) || localStorage.getItem(tokenKeys[1]) || '';
    }

    function setToken(token) {
        if (!token) {
            return;
        }
        tokenKeys.forEach(function(key) {
            localStorage.setItem(key, token);
        });
    }

    function clearToken() {
        tokenKeys.forEach(function(key) {
            localStorage.removeItem(key);
        });
    }

    function normalizePermissionSlugs(value) {
        var keys;

        if (value && typeof value === 'object' && !Array.isArray(value)) {
            keys = Object.keys(value);
            if (keys.every(function(key) {
                return String(parseInt(key, 10)) === key;
            })) {
                value = keys.map(function(key) {
                    return value[key];
                });
            }
        }

        if (!Array.isArray(value)) {
            return [];
        }

        return value.map(function(slug) {
            return String(slug || '').trim();
        }).filter(function(slug) {
            return slug !== '';
        });
    }

    function applyPermissionVisibility(root) {
        var $root;

        if (permissionSlugs === null) {
            return;
        }

        $root = $(root || document);
        $root.find('[data-permission]').each(function() {
            var $element = $(this);
            var slug = String($element.attr('data-permission') || '').trim();
            var allowed;

            if (!slug) {
                return;
            }

            allowed = permissionSlugs.indexOf(slug) !== -1;
            $element.toggle(allowed);
            $element.attr('aria-hidden', allowed ? 'false' : 'true');
            if ($element.is('button, input, select, textarea')) {
                $element.prop('disabled', !allowed).attr('aria-disabled', allowed ? 'false' : 'true');
            }
        });
    }

    function setPermissionSlugs(slugs) {
        permissionSlugs = normalizePermissionSlugs(slugs);
        localStorage.setItem('crm_admin_permissions', JSON.stringify(permissionSlugs));
        applyPermissionVisibility(document);
    }

    function loadPermissionSlugs() {
        var cached = null;

        try {
            cached = JSON.parse(localStorage.getItem('crm_admin_permissions') || 'null');
        } catch (error) {
            cached = null;
        }

        if (Array.isArray(cached)) {
            permissionSlugs = normalizePermissionSlugs(cached);
            applyPermissionVisibility(document);
        }

        request({url: '/api/admin/menus', method: 'POST'}).done(function(response) {
            var data = dataFromResponse(response);

            setPermissionSlugs(data.permissions || []);
        });
    }

    function currentLocale() {
        var stored = localStorage.getItem('crm_locale');
        var htmlLang = document.documentElement.getAttribute('lang') || '';

        if (stored === 'zh-CN' || stored === 'en') {
            return stored;
        }

        return htmlLang.toLowerCase().indexOf('zh') === 0 ? 'zh-CN' : 'en';
    }

    function headers(auth) {
        var result = {'X-CSRF-TOKEN': csrfToken(), 'X-Locale': currentLocale()};
        var token = getToken();

        if (auth !== false && token) {
            result.Authorization = 'Bearer ' + token;
        }

        return result;
    }

    function businessCodeSucceeded(response) {
        var rawCode;
        var code;

        if (!response || typeof response !== 'object' || Array.isArray(response)) {
            return false;
        }

        rawCode = response.code;
        if (typeof rawCode !== 'number' && typeof rawCode !== 'string') {
            return false;
        }
        if (typeof rawCode === 'string' && !/^(?:0|[1-9]\d*)$/.test(rawCode)) {
            return false;
        }

        code = Number(rawCode);
        if (!isFinite(code)) {
            return false;
        }

        // 1xxx/3xxx are not uniformly successful: 1015, 3001, 3003 and 3006
        // are explicit failure results and must not reach success handlers.
        return code === 0 || [
            1000, 1001, 1002, 1003, 1004,
            2000,
            3000, 3002, 3004, 3005
        ].indexOf(code) !== -1;
    }

    function request(options) {
        options = options || {};

        var data = options.data || {};
        var isFormData = window.FormData && data instanceof FormData;
        var reportError = function(error) {
            if (typeof options.onError === 'function') {
                options.onError(error);
                return;
            }

            layer.msg(messageFromResponse(error && error.responseJSON) || 'Request failed', {icon: 2});
        };

        return $.ajax({
            url: options.url,
            type: options.method || 'GET',
            data: data,
            dataType: 'json',
            processData: !isFormData,
            contentType: isFormData ? false : undefined,
            headers: headers(options.auth)
        }).then(function(response) {
            if (!businessCodeSucceeded(response)) {
                var businessError = {responseJSON: response, businessError: true};
                reportError(businessError);
                return $.Deferred().reject(businessError).promise();
            }

            return response;
        }, function(xhr) {
            if (xhr.status === 401) {
                clearToken();
                window.location.href = loginPath;
                return $.Deferred().reject(xhr).promise();
            }
            reportError(xhr);
            return $.Deferred().reject(xhr).promise();
        });
    }

    function messageFromResponse(response) {
        return response && (response.message || response.msg || response.error);
    }

    function dataFromResponse(response) {
        if (!response) {
            return {};
        }
        return response.data !== undefined ? response.data : response;
    }

    function rowsFromResponse(response) {
        var data = dataFromResponse(response);

        if ($.isArray(data)) {
            return data;
        }
        if ($.isArray(data.data)) {
            return data.data;
        }
        if (data.data && $.isArray(data.data.data)) {
            return data.data.data;
        }
        if (data.records && $.isArray(data.records.data)) {
            return data.records.data;
        }
        if (data.info || data.login || data.auth) {
            return [flattenFormPayload(data)];
        }
        if ($.isArray(data.list)) {
            return data.list;
        }
        if ($.isArray(data.records)) {
            return data.records;
        }
        if ($.isArray(data.items)) {
            return data.items;
        }
        if ($.isArray(data.rows)) {
            return data.rows;
        }

        return data && typeof data === 'object' ? [data] : [];
    }

    function csvFileName(response, fallback) {
        var disposition = response.headers.get('content-disposition') || '';
        var match = disposition.match(/filename="?([^";]+)"?/i);

        return match ? match[1] : fallback;
    }

    function downloadCsv(url, data, fallbackName) {
        var requestHeaders = headers(true);

        requestHeaders.Accept = 'text/csv';
        requestHeaders['Content-Type'] = 'application/json';

        return fetch(url, {
            method: 'POST',
            headers: requestHeaders,
            body: JSON.stringify(data || {}),
            credentials: 'same-origin'
        }).then(function(response) {
            var contentType = response.headers.get('content-type') || '';

            if (!response.ok || contentType.indexOf('text/csv') === -1) {
                return response.text().then(function() {
                    throw new Error('csv_download_failed');
                });
            }

            return response.blob().then(function(blob) {
                return {blob: blob, fileName: csvFileName(response, fallbackName)};
            });
        }).then(function(download) {
            var link = document.createElement('a');
            var objectUrl = URL.createObjectURL(download.blob);

            link.href = objectUrl;
            link.download = download.fileName || fallbackName || 'export.csv';
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
            URL.revokeObjectURL(objectUrl);
            layer.msg('OK', {icon: 1});
        }).catch(function() {
            layer.msg('Request failed', {icon: 2});
        });
    }

    function totalFromResponse(response, rows) {
        var data = dataFromResponse(response);

        if (data.records && data.records.total !== undefined) {
            return data.records.total;
        }
        if (data.total !== undefined) {
            return data.total;
        }
        if (data.count !== undefined) {
            return data.count;
        }
        if (data.totalCount !== undefined) {
            return data.totalCount;
        }

        return rows.length;
    }

    function pageState($page) {
        var state = $page.data('paginationState');

        if (!state) {
            state = {page: 1, perPage: 15, lastPage: 1};
            $page.data('paginationState', state);
        }

        return state;
    }

    function resetPageState($page) {
        var state = pageState($page);

        state.page = 1;
        state.lastPage = 1;
        renderPagination($page, state);
    }

    function paginatorFromResponse(response) {
        var data = dataFromResponse(response);
        var candidates = [data && data.records, data && data.data, data];
        var i;

        for (i = 0; i < candidates.length; i++) {
            if (candidates[i] && typeof candidates[i] === 'object' && !$.isArray(candidates[i]) && (
                candidates[i].current_page !== undefined || candidates[i].last_page !== undefined
            )) {
                return candidates[i];
            }
        }

        return {};
    }

    function updatePageState($page, response, rows) {
        var state = pageState($page);
        var paginator = paginatorFromResponse(response);
        var total = Number(paginator.total !== undefined ? paginator.total : totalFromResponse(response, rows));
        var perPage = Number(paginator.per_page || state.perPage || 15);
        var lastPage = Number(paginator.last_page || (total > 0 ? Math.ceil(total / perPage) : 1));

        state.perPage = Math.max(perPage || 15, 1);
        state.lastPage = Math.max(lastPage || 1, 1);
        state.page = Math.min(Math.max(Number(paginator.current_page || state.page || 1), 1), state.lastPage);
        renderPagination($page, state);
    }

    function renderPagination($page, state) {
        var $pagination = $page.find('[data-crmui-pagination]');

        if (!$pagination.length) {
            return;
        }

        $pagination.find('[data-crmui-page-current]').text(state.page + ' / ' + state.lastPage);
        $pagination.find('[data-crmui-page-prev]').prop('disabled', state.page <= 1);
        $pagination.find('[data-crmui-page-next]').prop('disabled', state.page >= state.lastPage);
    }

    function readForm($form) {
        var data = {};

        $.each($form.serializeArray(), function(_, item) {
            if (item.value !== '') {
                data[item.name] = item.value;
            }
        });

        return data;
    }

    function hasFileInput($form) {
        return $form.find('input[type="file"]').length > 0;
    }

    function formPayload($form) {
        return hasFileInput($form) ? new FormData($form[0]) : readForm($form);
    }

    function escapeHtml(value) {
        return String(value === undefined || value === null ? '' : value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function rowValue(row, key) {
        var value;

        if (!row || !key) {
            return '';
        }

        value = row[key];
        if (value === undefined && key.indexOf('.') !== -1) {
            value = key.split('.').reduce(function(carry, part) {
                return carry && carry[part] !== undefined ? carry[part] : undefined;
            }, row);
        }

        return value === undefined || value === null ? '' : value;
    }

    function cellValue(row, key) {
        var value = rowValue(row, key);

        return value === '' ? '--' : value;
    }

    function cellHtml($page, row, column) {
        var value = cellValue(row, column.key);
        var pageKey = String($page.attr('data-crmui-page') || '');
        var status;
        var label;

        if (pageKey === 'admin.cancel_applies' && column.key === 'status') {
            status = Number(rowValue(row, 'status'));
            if (status === 1) {
                label = $page.attr('data-status-approved-text');
                return '<span class="crmui-status-badge is-approved">' + escapeHtml(label) + '</span>';
            }
            if (status === -1) {
                label = $page.attr('data-status-rejected-text');
                return '<span class="crmui-status-badge is-rejected">' + escapeHtml(label) + '</span>';
            }

            label = $page.attr('data-status-pending-text');
            return '<span class="crmui-status-badge is-pending">' + escapeHtml(label) + '</span>';
        }

        if (pageKey === 'admin.cancel_applies' && column.key === 'balance') {
            return '<span class="crmui-money' + (Number(value) < 0 ? ' is-negative' : '') + '">' + escapeHtml(value) + '</span>';
        }

        return escapeHtml(value);
    }

    function copyScalarValues(target, source) {
        if (!source || typeof source !== 'object' || $.isArray(source)) {
            return;
        }

        Object.keys(source).forEach(function(key) {
            var value = source[key];

            if (value === undefined || value === null || typeof value === 'object') {
                return;
            }

            target[key] = value;
        });
    }

    function flattenFormPayload(payload) {
        var values = {};

        copyScalarValues(values, payload);
        copyScalarValues(values, payload && payload.login);
        copyScalarValues(values, payload && payload.info);
        copyScalarValues(values, payload && payload.auth);

        if (values.email !== undefined && values.current_email === undefined) {
            values.current_email = values.email;
        }
        if (values.phone !== undefined && values.verify_phone === undefined) {
            values.verify_phone = values.phone;
        }

        return values;
    }

    function setFormFieldValue($field, value) {
        if (value === undefined || value === null || $field.attr('type') === 'file') {
            return;
        }

        if ($field.attr('type') === 'checkbox') {
            $field.prop('checked', truthyFlag(value));
            return;
        }

        $field.val(value);
    }

    function fillFormFields($form, values) {
        $form.find('[name]').each(function() {
            var $field = $(this);
            var value = rowValue(values, $field.attr('name'));

            if (value === '') {
                return;
            }

            setFormFieldValue($field, value);
        });
    }

    function fillPageForms($page, response) {
        var mode = $page.attr('data-crmui-mode') || 'table';
        var values;

        if (mode !== 'form' && mode !== 'profile') {
            return;
        }

        values = flattenFormPayload(dataFromResponse(response));
        $page.find('[data-crmui-form]').each(function() {
            fillFormFields($(this), values);
        });
    }

    function recordIdentifier(row, preferredKey) {
        return row[preferredKey] || row.id || row.record_id || row.user_id || row.order_id || row.ticket || row.order_no || row.login_ip || '';
    }

    /**
     * 读取行操作按钮声明的固定扩展参数。
     *
     * @param {jQuery} $button 当前行操作按钮。
     * @returns {Object} 返回可合并进请求的键值对象；解析失败时返回空对象，让错误保持在点击动作自身。
     */
    function readRowActionExtraPayload($button) {
        var raw = String($button.attr('data-extra-payload') || '{}');
        var parsed;

        try {
            parsed = JSON.parse(raw);
        } catch (error) {
            return {};
        }

        return $.isPlainObject(parsed) ? parsed : {};
    }

    /**
     * 读取页面当前附加筛选参数。
     *
     * @param {jQuery} $page 当前 CrmUI 页面根节点。
     * @returns {Object} 返回本地行操作写入的筛选参数，例如持仓汇总钻取中的 searchtype/userPId。
     */
    function pageExtraFilter($page) {
        var filter = $page.data('crmuiExtraFilter');

        return $.isPlainObject(filter) ? filter : {};
    }

    /**
     * 汇总页面筛选表单和本地行操作附加筛选。
     *
     * @param {jQuery} $page 当前 CrmUI 页面根节点。
     * @returns {Object} 返回真实请求参数，供列表刷新和导出复用。
     */
    function currentPageFilter($page) {
        return $.extend({}, readForm($page.find('[data-crmui-filter]')), pageExtraFilter($page));
    }

    function rowActionsHtml($page, row) {
        var template = $page.find('template[data-crmui-row-actions]').html();
        var activeView = String($page.attr('data-crmui-active-view') || '');
        var encodedRow;
        var $actions;

        if (!template) {
            return '';
        }

        encodedRow = encodeURIComponent(JSON.stringify(row || {}));
        $actions = $(template);
        $actions.find('[data-crmui-row-action]').each(function() {
            var $button = $(this);
            var actionView = String($button.attr('data-action-view') || '');
            var actionKey = String($button.attr('data-crmui-row-action') || '');

            if (actionView && activeView && actionView !== activeView) {
                $button.remove();
                return;
            }
            if (!actionMatchesRow($button, row)) {
                $button.remove();
                return;
            }
            if ((actionKey === 'review' || actionKey === 'review_auth') && !isAuthReviewableRow(row)) {
                $button.remove();
                return;
            }
            if (actionKey === 'position_summary_drilldown' && parseInt(row.account_type, 10) !== 1) {
                $button.remove();
                return;
            }
            if (actionKey === 'force_close' && !row.force_close_id) {
                $button.remove();
                return;
            }
            $button.attr('data-row', encodedRow);
        });

        return $actions.prop('outerHTML') || '';
    }

    function renderRows($page, rows) {
        var $body = $page.find('[data-crmui-table-body]');
        var columns = [];
        var emptyText = $page.data('empty-text') || 'No records';
        var hasRowActions = $page.find('template[data-crmui-row-actions]').length > 0;
        var batchConfig = batchConfigFor($page);

        $page.find('thead th[data-key]').each(function() {
            var $th = $(this);
            var key = $th.data('key');
            if (key) {
                columns.push({key: key, label: $th.text()});
            }
        });

        if (!rows.length) {
            renderTableState($page, 'empty', emptyText);
            return;
        }

        $body.html(rows.map(function(row, rowIndex) {
            var operationsLabel = $page.find('[data-crmui-action-column]').text() || 'Operations';
            var cells = columns.map(function(column) {
                return '<td data-label="' + escapeHtml(column.label) + '">' + cellHtml($page, row, column) + '</td>';
            });

            // 勾选列必须排在业务列之前，与表头 data-crmui-select-column 的位置一一对应。
            if (batchConfig) {
                cells.unshift(batchSelectCellHtml(batchConfig, row, rowIndex));
            }

            if (hasRowActions) {
                cells.push('<td data-label="' + escapeHtml(operationsLabel) + '">' + rowActionsHtml($page, row) + '</td>');
            }

            return '<tr>' + cells.join('') + '</tr>';
        }).join(''));
        applyPermissionVisibility($body);

        // 每次重渲染都会替换整个 tbody，因此全选框与统计必须回到初始态，避免显示上一页的选中数。
        if (batchConfig) {
            resetBatchSelection($page);
        }
    }

    /**
     * 读取当前页面的批量声明；未声明批量的页面返回 null，调用方据此完全跳过勾选逻辑。
     * 声明来自 module-page.blade.php 渲染的 data-crmui-batch-open 按钮，
     * transitions 决定哪些来源状态可批量，sourceField 指明判定来源状态的行字段。
     */
    function batchConfigFor($page) {
        var $button = $page.find('[data-crmui-batch-open]').first();
        var transitions;
        var targetStatuses;

        if (!$button.length) {
            return null;
        }

        transitions = $button.data('batch-transitions') || {};
        targetStatuses = $button.data('batch-target-statuses') || [];

        return {
            url: String($button.attr('data-batch-url') || ''),
            method: String($button.attr('data-batch-method') || 'POST'),
            recordKey: String($button.attr('data-batch-record-key') || 'id'),
            sourceField: String($button.attr('data-batch-source-field') || 'status'),
            transitions: transitions,
            targetStatuses: targetStatuses
        };
    }

    /**
     * 生成单行勾选单元格。
     * 来源状态不在 transitions 白名单内的行（出金场景即已完成/已拒绝的终态行）禁用勾选，
     * 因为后端状态机必然拒绝，先在 UI 侧挡住可避免管理员收到一批注定失败的结果。
     */
    function batchSelectCellHtml(batchConfig, row, rowIndex) {
        var sourceStatus = String(row[batchConfig.sourceField]);
        var eligible = Object.prototype.hasOwnProperty.call(batchConfig.transitions, sourceStatus);
        var recordId = row[batchConfig.recordKey];

        return '<td class="crmui-table-select" data-label="">' +
            '<label class="crmui-check">' +
            '<input type="checkbox" data-crmui-select-row' +
            ' value="' + escapeHtml(String(recordId === undefined || recordId === null ? '' : recordId)) + '"' +
            ' data-row-index="' + escapeHtml(String(rowIndex)) + '"' +
            ' data-source-status="' + escapeHtml(sourceStatus) + '"' +
            (eligible ? '' : ' disabled') + '>' +
            '</label></td>';
    }

    /**
     * 清空当前页的勾选状态并同步全选框。
     * 表格重载（翻页、筛选、批量提交后刷新）都必须调用，否则会保留已不存在行的选中态。
     */
    function resetBatchSelection($page) {
        $page.find('[data-crmui-select-all]').prop('checked', false).prop('indeterminate', false);
        $page.find('[data-crmui-batch-count]').text('');
    }

    function renderTableState($page, state, text) {
        var $body = $page.find('[data-crmui-table-body]');
        var columnCount = $page.find('thead th').length;
        var fallback = state === 'loading'
            ? $page.data('loading-text')
            : (state === 'error' ? $page.data('error-text') : $page.data('empty-text'));

        $body.html(
            '<tr data-ui-state="' + escapeHtml(state) + '"><td colspan="' + Math.max(columnCount, 1) + '">' +
            escapeHtml(text || fallback || '') + '</td></tr>'
        );
        $page.find('[data-crmui-total]').text(state === 'loading' ? fallback : '0');
        $page.find('[data-crmui-metric] strong').text('--');
    }

    function renderMetrics($page, response, rows) {
        var data = dataFromResponse(response);

        $page.find('[data-crmui-metric]').each(function() {
            var $metric = $(this);
            var key = $metric.data('crmui-metric');
            var value = data[key];

            // 持仓汇总等接口把卡片合计放在 data.summary，必须优先读取当前筛选后的派生汇总。
            if (value === undefined && data.summary) {
                value = data.summary[key];
            }
            if (value === undefined && data.stats) {
                value = data.stats[key];
            }
            if (value === undefined && key === 'total') {
                value = totalFromResponse(response, rows);
            }

            $metric.find('strong').text(value === undefined || value === null ? '--' : value);
        });
    }

    function loadPage($page) {
        var url = $page.attr('data-api-url');
        var method = $page.attr('data-api-method') || 'GET';
        var filter = currentPageFilter($page);
        var state = pageState($page);
        var loadGeneration = Number($page.data('loadGeneration') || 0) + 1;

        if (!url) {
            return;
        }

        $page.data('loadGeneration', loadGeneration);
        renderTableState($page, 'loading', $page.data('loading-text'));

        if ($page.find('[data-crmui-pagination]').length) {
            filter.page = state.page;
            filter.per_page = state.perPage;
        }

        request({
            url: url,
            method: method,
            data: filter,
            onError: function(error) {
                if ($page.data('loadGeneration') !== loadGeneration) {
                    return;
                }

                var message = messageFromResponse(error && error.responseJSON)
                    || messageFromResponse(error)
                    || $page.data('error-text')
                    || 'Request failed';
                renderTableState($page, 'error', message);
                layer.msg(message, {icon: 2});
            }
        }).done(function(response) {
            if ($page.data('loadGeneration') !== loadGeneration) {
                return;
            }

            var rows = rowsFromResponse(response);
            updatePageState($page, response, rows);
            renderRows($page, rows);
            renderMetrics($page, response, rows);
            fillPageForms($page, response);
            $page.find('[data-crmui-total]').text(totalFromResponse(response, rows));
        });
    }

    function focusCreateForm($page) {
        var $form = $page.find('[data-crmui-form]').first();
        var $firstField = $form.find('input, select, textarea').filter(':visible:not([type="hidden"])').first();

        if (!$form.length) {
            layer.msg('No form available', {icon: 0});
            return;
        }

        $('html, body').animate({scrollTop: Math.max($form.offset().top - 84, 0)}, 180);
        $firstField.trigger('focus');
    }

    function exportPage($page, $button) {
        var actionUrl = $button ? String($button.attr('data-action-url') || '') : '';
        var url = actionUrl || $page.attr('data-api-url');
        var method = String($page.attr('data-api-method') || 'GET').toUpperCase();
        var data = $.extend({}, currentPageFilter($page), {export: 1});
        var query;

        if (!url) {
            return;
        }

        if (actionUrl) {
            downloadCsv(actionUrl, data, $button.attr('data-file-name') || 'export.csv');
            return;
        }

        if (method === 'GET') {
            query = $.param(data);
            window.open(url + (url.indexOf('?') === -1 ? '?' : '&') + query, '_blank');
            return;
        }

        request({url: url, method: method, data: data}).done(function(response) {
            var payload = dataFromResponse(response);
            var downloadUrl = payload.url || payload.download_url || payload.file_url || payload.path;

            if (downloadUrl) {
                window.open(downloadUrl, '_blank');
                return;
            }

            layer.msg(messageFromResponse(response) || 'OK', {icon: 1});
        });
    }

    function firstPresentValue(values) {
        var i;

        for (i = 0; i < values.length; i++) {
            if (values[i] !== undefined && values[i] !== null && values[i] !== '') {
                return values[i];
            }
        }

        return '';
    }

    function truthyFlag(value) {
        return value === true || value === 1 || value === '1' || value === 'true' || value === 'yes';
    }

    function itemIsSelected(item) {
        return truthyFlag(item.is_default) || truthyFlag(item.selected) || truthyFlag(item['default']);
    }

    function fillSelect($select, items) {
        if (!$.isArray(items) || !items.length) {
            return;
        }

        $select.find('option:not(:first)').remove();
        items.forEach(function(item) {
            var value;
            var label;
            var $option;

            item = item || {};
            value = firstPresentValue([item.value, item.id, item.code, item.channel_id, item.bank_card, item.name]);
            label = firstPresentValue([item.label, item.name, item.title, item.channel_name, item.bank_name, value]);
            $option = $('<option>').attr('value', value).text(label);

            if (itemIsSelected(item)) {
                $option.prop('selected', true);
            }

            $select.append($option);
        });
    }

    function loadOptions($page) {
        var url = $page.attr('data-options-url');

        if (!url) {
            return;
        }

        request({url: url, method: 'GET'}).done(function(response) {
            var data = dataFromResponse(response);
            var channelItems = data.channels || data.payment_channels || data.channelList || [];
            var bankItems = data.bank_cards || data.bankCards || data.cards || [];

            fillSelect($page.find('select[name="channel"]'), channelItems);
            fillSelect($page.find('select[name="bank_card"]'), bankItems);
        });
    }

    function parseFields(value) {
        if (!value) {
            return [];
        }

        return String(value).split('|').map(function(chunk) {
            var parts = chunk.trim().split(':');
            if (parts[0] === 'name') {
                parts.shift();
            }
            return {
                name: parts[0] || '',
                type: parts[1] || 'text',
                label: parts[2] || parts[0] || '',
                options: parts[3] ? parts[3].split(',').filter(Boolean).map(function(option) {
                    var pair = option.split('=');
                    return {value: pair[0], label: pair[1] || pair[0]};
                }) : []
            };
        }).filter(function(field) {
            return field.name;
        });
    }

    function parseFieldRules(value) {
        var rules;

        try {
            rules = JSON.parse(String(value || '[]'));
        } catch (error) {
            return [];
        }

        return $.isArray(rules) ? rules : [];
    }

    function actionFields($button) {
        var fields = parseFields($button.attr('data-fields'));
        var rules = parseFieldRules($button.attr('data-field-rules'));

        return fields.map(function(field) {
            var rule = rules.filter(function(candidate) {
                return candidate && candidate.name === field.name;
            })[0] || {};

            field.required = rule.required === true || String(rule.required) === '1';
            field.maxlength = Number(rule.maxlength) > 0 ? Number(rule.maxlength) : null;

            return field;
        });
    }

    function actionMatchesRow($button, row) {
        var conditions;

        try {
            conditions = JSON.parse(String($button.attr('data-visible-when') || '{}'));
        } catch (error) {
            return false;
        }

        if ($.isArray(conditions) && conditions.length === 0) {
            return true;
        }
        if (!$.isPlainObject(conditions)) {
            return false;
        }

        return Object.keys(conditions).every(function(key) {
            var expected = conditions[key];
            var actual = rowValue(row, key);

            if ($.isArray(expected)) {
                return expected.map(String).indexOf(String(actual)) !== -1;
            }

            return String(actual) === String(expected);
        });
    }

    function authReviewableComponents(row) {
        var nested = row && row.auth && typeof row.auth === 'object' && !$.isArray(row.auth) ? row.auth : {};
        var idCardStatus = nested.id_card_status !== undefined ? nested.id_card_status : row && row.id_card_status;
        var bankStatus = nested.bank_status !== undefined ? nested.bank_status : row && row.bank_status;

        return {
            idCard: String(idCardStatus) === '1',
            bank: String(bankStatus) === '1' || String(bankStatus) === '3'
        };
    }

    function isAuthReviewableRow(row) {
        var components = authReviewableComponents(row);

        return components.idCard || components.bank;
    }

    function authReviewFieldsForRow(row, fields) {
        var components = authReviewableComponents(row);

        return (fields || []).filter(function(field) {
            if (field.name === 'id_card_decision' || field.name === 'id_card_reason') {
                return components.idCard;
            }
            if (field.name === 'bank_decision' || field.name === 'bank_reason') {
                return components.bank;
            }
            return false;
        });
    }

    function authReviewFieldLabel(fields, name) {
        var match = (fields || []).filter(function(field) {
            return field.name === name;
        })[0];

        return match && match.label ? match.label : name;
    }

    function buildAuthReviewPayload(row, data, fields, noReviewableMessage) {
        var components = authReviewableComponents(row);
        var payload = {};
        var error = '';

        [
            {reviewable: components.idCard, key: 'id_card'},
            {reviewable: components.bank, key: 'bank'}
        ].some(function(component) {
            var decisionName;
            var reasonName;
            var decision;
            var reason;

            if (!component.reviewable) {
                return false;
            }

            decisionName = component.key + '_decision';
            reasonName = component.key + '_reason';
            decision = String(data && data[decisionName] !== undefined ? data[decisionName] : '');
            reason = String(data && data[reasonName] !== undefined ? data[reasonName] : '').trim();

            if (decision !== '1' && decision !== '2') {
                error = authReviewFieldLabel(fields, decisionName);
                return true;
            }
            if (decision === '2' && !reason) {
                error = authReviewFieldLabel(fields, reasonName);
                return true;
            }

            payload[decisionName] = decision;
            if (reason) {
                payload[reasonName] = reason;
            }
            return false;
        });

        if (!components.idCard && !components.bank) {
            error = noReviewableMessage || authReviewFieldLabel(fields, 'id_card_decision');
        }

        return {payload: payload, error: error};
    }

    function authDetailRecord(response, expectedUserId) {
        var record = dataFromResponse(response);

        if (!record || typeof record !== 'object' || $.isArray(record) || record.user_id === undefined) {
            return null;
        }
        if (String(record.user_id) !== String(expectedUserId)) {
            return null;
        }

        return record;
    }

    function safeCrmUiAuthDetailImageUrl(value) {
        var url = String(value || '').trim();

        return /^(?:https?:\/\/|\/(?!\/))/i.test(url) ? url : '';
    }

    function buildCrmUiAuthDetailReviewPayload(record, data, expectedUserId, fields, noReviewableMessage) {
        var review;
        var payload;

        if (!record || String(record.user_id) !== String(expectedUserId)) {
            return {payload: {}, error: noReviewableMessage || 'Invalid authentication record'};
        }

        review = buildAuthReviewPayload(record, data, fields, noReviewableMessage);
        if (!review.error) {
            payload = {user_id: String(expectedUserId)};
            Object.keys(review.payload).forEach(function(key) {
                payload[key] = review.payload[key];
            });
            review.payload = payload;
        }

        return review;
    }

    function showCrmUiAuthDetailState($page, state) {
        $page.find('[data-crmui-auth-state]').each(function() {
            $(this).prop('hidden', $(this).attr('data-crmui-auth-state') !== state);
        });
    }

    function renderCrmUiAuthDetail($page, record) {
        var components = authReviewableComponents(record);
        var $reviewForm = $page.find('[data-crmui-auth-review-form]');
        var reviewable = components.idCard || components.bank;

        $page.data('authDetailRecord', record);
        $page.find('[data-crmui-auth-field]').each(function() {
            var $field = $(this);
            $field.text(cellValue(record, $field.attr('data-crmui-auth-field')));
        });
        $page.find('[data-crmui-auth-image]').each(function() {
            var $image = $(this);
            var field = $image.attr('data-crmui-auth-image');
            var url = safeCrmUiAuthDetailImageUrl(rowValue(record, field));
            var $empty = $page.find('[data-crmui-auth-image-empty="' + field + '"]');

            if (url) {
                $image.attr('src', url).prop('hidden', false);
                $empty.prop('hidden', true);
                return;
            }

            $image.removeAttr('src').prop('hidden', true);
            $empty.prop('hidden', false);
        });

        if ($reviewForm.length) {
            $reviewForm.find('[data-crmui-auth-review-component="id_card"]').prop('hidden', !components.idCard);
            $reviewForm.find('[data-crmui-auth-review-component="bank"]').prop('hidden', !components.bank);
            $reviewForm.prop('hidden', !reviewable);
            $page.find('[data-crmui-auth-review-empty]').prop('hidden', reviewable);
        }

        showCrmUiAuthDetailState($page, 'content');
    }

    function loadCrmUiAuthDetail($page) {
        var expectedUserId = $page.attr('data-crmui-auth-user-id');
        var loadGeneration = Number($page.data('authDetailLoadGeneration') || 0) + 1;

        $page.data('authDetailLoadGeneration', loadGeneration);
        $page.removeData('authDetailRecord');
        showCrmUiAuthDetailState($page, 'loading');
        request({
            url: $page.attr('data-api-url'),
            method: 'POST',
            data: {user_id: expectedUserId},
            onError: function(error) {
                var message = messageFromResponse(error && error.responseJSON) || messageFromResponse(error) || 'Request failed';

                if ($page.data('authDetailLoadGeneration') !== loadGeneration) {
                    return;
                }

                $page.find('[data-crmui-auth-error]').text(message);
                showCrmUiAuthDetailState($page, 'error');
            }
        }).done(function(response) {
            var record = authDetailRecord(response, expectedUserId);

            if ($page.data('authDetailLoadGeneration') !== loadGeneration) {
                return;
            }

            if (!record) {
                showCrmUiAuthDetailState($page, 'empty');
                return;
            }

            renderCrmUiAuthDetail($page, record);
        });
    }

    function crmUiAuthDetailReviewFields($form) {
        return $form.find('[name]').map(function() {
            return {
                name: this.name,
                label: $(this).attr('data-label') || this.name
            };
        }).get();
    }

    function bindCrmUiAuthDetail() {
        $(document).on('click', '[data-crmui-auth-retry]', function() {
            loadCrmUiAuthDetail($(this).closest('[data-crmui-auth-detail]'));
        });

        $(document).on('submit', '[data-crmui-auth-review-form]', function(event) {
            var $form = $(this);
            var $page = $form.closest('[data-crmui-auth-detail]');
            var $submit = $form.find('button[type="submit"]');
            var review;
            var pendingRequest;

            event.preventDefault();
            if ($form.data('requestPending')) {
                return;
            }

            review = buildCrmUiAuthDetailReviewPayload(
                $page.data('authDetailRecord'),
                readForm($form),
                $page.attr('data-crmui-auth-user-id'),
                crmUiAuthDetailReviewFields($form),
                $page.attr('data-no-reviewable-text')
            );
            if (review.error) {
                layer.msg(review.error, {icon: 0});
                return;
            }

            $form.data('requestPending', true);
            $submit.prop('disabled', true).attr('aria-busy', 'true');
            pendingRequest = request({
                url: $page.attr('data-review-url'),
                method: 'POST',
                data: review.payload,
                onError: function(error) {
                    layer.msg(messageFromResponse(error && error.responseJSON) || messageFromResponse(error) || 'Request failed', {icon: 2});
                }
            });
            pendingRequest.done(function(response) {
                layer.msg(messageFromResponse(response) || 'OK', {icon: 1});
                $form[0].reset();
                loadCrmUiAuthDetail($page);
            });
            pendingRequest.always(function() {
                $form.removeData('requestPending');
                $submit.prop('disabled', false).removeAttr('aria-busy');
            });
        });
    }

    function fieldHtml(field, row) {
        var value = rowValue(row, field.name);
        var required = field.required ? ' required aria-required="true"' : '';
        var maxlength = field.maxlength ? ' maxlength="' + escapeHtml(field.maxlength) + '"' : '';
        var label = ' aria-label="' + escapeHtml(field.label) + '"';

        if (value === '' && field.name === 'group_name') {
            value = rowValue(row, 'name');
        }
        if (value === '' && row && row.data_scope && typeof row.data_scope === 'object') {
            value = rowValue(row.data_scope, field.name);
        }

        if (field.type === 'permission_tree') {
            return '<div class="crmui-permission-tree" data-permission-tree data-field-name="' + escapeHtml(field.name) + '">' + escapeHtml(field.label) + '</div>';
        }
        if (field.type === 'textarea') {
            return '<textarea class="crmui-input crmui-textarea" name="' + escapeHtml(field.name) + '" placeholder="' + escapeHtml(field.label) + '"' + label + required + maxlength + '>' + escapeHtml(value) + '</textarea>';
        }
        if (field.type === 'select') {
            return '<select class="crmui-input" name="' + escapeHtml(field.name) + '"' + label + required + '><option value="">' + escapeHtml(field.label) + '</option>' +
                field.options.map(function(option) {
                    var selected = String(option.value) === String(value) ? ' selected' : '';
                    return '<option value="' + escapeHtml(option.value) + '"' + selected + '>' + escapeHtml(option.label) + '</option>';
                }).join('') + '</select>';
        }
        if (field.type === 'checkbox') {
            return '<label class="crmui-check"><input name="' + escapeHtml(field.name) + '" type="checkbox" value="1"' +
                (truthyFlag(value) ? ' checked' : '') + '><span>' + escapeHtml(field.label) + '</span></label>';
        }

        return '<input class="crmui-input" name="' + escapeHtml(field.name) + '" type="' + escapeHtml(field.type || 'text') + '" placeholder="' + escapeHtml(field.label) + '" value="' + escapeHtml(value) + '"' + label + required + maxlength + '>';
    }

    function validateRequiredActionFields($form, fields) {
        var valid = true;

        fields.some(function(field) {
            var $input;
            var input;

            if (!field.required) {
                return false;
            }

            $input = $form.find('[name="' + field.name.replace(/"/g, '\\"') + '"]').first();
            if (!$input.length || String($input.val() || '').trim() !== '') {
                return false;
            }

            input = $input[0];
            input.setCustomValidity(field.label);
            input.reportValidity();
            $input.one('input change', function() {
                this.setCustomValidity('');
            });
            $input.trigger('focus');
            valid = false;

            return true;
        });

        return valid;
    }

    function permissionTreeNodes(response) {
        var data = dataFromResponse(response);

        if ($.isArray(data)) {
            return data;
        }
        if (data && $.isArray(data.list)) {
            return data.list;
        }
        if (data && $.isArray(data.data)) {
            return data.data;
        }
        return [];
    }

    function permissionTreeHtml(nodes, selectedMap) {
        if (!nodes || !nodes.length) {
            return '<p class="crmui-muted">No permissions</p>';
        }

        return '<ul class="crmui-permission-tree-list">' + nodes.map(function(node) {
            var id = node.id;
            var label = node.name || node.title || node.slug || id;
            var checked = selectedMap[String(id)] ? ' checked' : '';
            var children = permissionTreeHtml(node.children || [], selectedMap);

            return '<li><label class="crmui-check"><input type="checkbox" data-permission-id="' + escapeHtml(id) + '" value="' + escapeHtml(id) + '"' + checked + '><span>' + escapeHtml(label) + '</span></label>' + children + '</li>';
        }).join('') + '</ul>';
    }

    function loadCrmUiPermissionTree($button, row, $modal) {
        var url = $button.attr('data-permission-tree-url') || '';
        var selectedMap = {};
        var selected = row.permission_ids || [];
        var $tree = $modal.find('[data-permission-tree]');
        var actionInstance = Number($modal.data('actionInstance') || 0);
        var $submitButton = $modal.find('[data-crmui-action-form] button[type="submit"]');

        if (!url) {
            return;
        }

        if (!$tree.length) {
            $modal.data('permissionTreeLoading', false)
                .data('permissionTreeReady', false)
                .data('permissionTreeFailed', true);
            $submitButton.prop('disabled', true).removeAttr('aria-busy');
            return;
        }

        $.each(selected, function(_, id) {
            selectedMap[String(id)] = true;
        });
        $modal.data('permissionTreeLoading', true)
            .data('permissionTreeReady', false)
            .data('permissionTreeFailed', false);
        $submitButton.prop('disabled', true).attr('aria-busy', 'true');
        $tree.html('<p class="crmui-muted">Loading...</p>');
        request({
            url: url,
            method: 'POST',
            data: {guard_type: row.guard_type || 'admin'},
            onError: function(error) {
                if ($modal.data('actionInstance') !== actionInstance) {
                    return;
                }

                $modal.data('permissionTreeLoading', false)
                    .data('permissionTreeReady', false)
                    .data('permissionTreeFailed', true);
                $submitButton.prop('disabled', true).removeAttr('aria-busy');
                layer.msg(messageFromResponse(error && error.responseJSON) || messageFromResponse(error) || 'Request failed', {icon: 2});
            }
        }).done(function(response) {
            if ($modal.data('actionInstance') !== actionInstance) {
                return;
            }

            $tree.html(permissionTreeHtml(permissionTreeNodes(response), selectedMap));
            $modal.data('permissionTreeLoading', false)
                .data('permissionTreeReady', true)
                .data('permissionTreeFailed', false);
            $submitButton.prop('disabled', false).removeAttr('aria-busy');
        });
    }

    function collectCrmUiPermissionIds($modal) {
        var ids = [];

        $modal.find('[data-permission-tree] input[data-permission-id]:checked').each(function() {
            ids.push(Number($(this).val()));
        });

        return ids;
    }

    function openActionModal($button, row, fields) {
        var $modal = $button.closest('.crmui-page').find('[data-crmui-action-modal]');
        var isLocal = $button.data('crmui-local-modal') === 1 || $button.attr('data-crmui-local-modal') === '1';
        var actionInstance = Number($modal.data('actionInstance') || 0) + 1;
        var $submitButton = $modal.find('[data-crmui-action-form] button[type="submit"]');

        $modal.data('actionInstance', actionInstance);
        $modal.removeData('requestPending');
        $modal.data('actionButton', $button);
        $modal.data('returnFocus', $button[0]);
        $modal.data('row', row);
        $modal.find('[data-crmui-modal-title]').text($button.text());
        $modal.find('[data-crmui-record-preview]').text(JSON.stringify(row, null, 2)).toggle(isLocal || !fields.length);
        $modal.find('[data-crmui-modal-fields]').html(fields.map(function(field) {
            return fieldHtml(field, row);
        }).join(''));
        $submitButton.prop('disabled', false).removeAttr('aria-busy').toggle(!isLocal);
        $modal.removeAttr('hidden');
        window.setTimeout(function() {
            $modal.find('[name], button').filter(':visible').first().trigger('focus');
        }, 0);
        if ($button.attr('data-permission-tree-url')) {
            loadCrmUiPermissionTree($button, row, $modal);
        }
    }

    function closeActionModal($modal, actionInstance) {
        var returnFocus = $modal.data('returnFocus');

        if (actionInstance !== undefined && $modal.data('actionInstance') !== actionInstance) {
            return;
        }

        $modal.attr('hidden', true);
        $modal.removeData('actionButton').removeData('row').removeData('returnFocus');
        if (returnFocus && document.documentElement.contains(returnFocus)) {
            returnFocus.focus();
        }
    }

    function submitRowAction($button, row, extraData, $actionModal) {
        var recordKey = $button.data('record-key') || $button.data('payload-key') || 'id';
        var payloadName = $button.data('payload-name') || $button.data('payload-key') || recordKey;
        var id = recordIdentifier(row, recordKey);
        var url = String($button.data('action-url') || '').replace('__ID__', encodeURIComponent(id));
        var payload = $.extend({}, extraData || {});
        var $modal = $button.closest('.crmui-page').find('[data-crmui-action-modal]');
        var $submitButton = null;
        var pendingRequest;
        var actionInstance;
        var currentModal;

        if ($button.attr('data-permission-tree-url')) {
            if (!$modal.length || !$modal.data('permissionTreeReady')) {
                return;
            }
            payload.permissions = collectCrmUiPermissionIds($modal);
        }

        if (payloadName && id && payload[payloadName] === undefined) {
            payload[payloadName] = id;
        }

        if (!url) {
            openActionModal($button, row, []);
            return;
        }

        if ($actionModal && $actionModal.length) {
            if ($actionModal.data('requestPending')) {
                return;
            }
            actionInstance = $actionModal.data('actionInstance');
            $actionModal.data('requestPending', true);
            $submitButton = $actionModal.find('[data-crmui-action-form] button[type="submit"]');
            $submitButton.prop('disabled', true).attr('aria-busy', 'true');
        }

        pendingRequest = request({
            url: url,
            method: $button.data('action-method') || 'POST',
            data: payload,
            onError: function(error) {
                var current = !$actionModal || !$actionModal.length ||
                    $actionModal.data('actionInstance') === actionInstance;

                if (!current) {
                    return;
                }

                layer.msg(messageFromResponse(error && error.responseJSON) || messageFromResponse(error) || 'Request failed', {icon: 2});
            }
        });
        pendingRequest.done(function(response) {
            currentModal = !$actionModal || !$actionModal.length ||
                $actionModal.data('actionInstance') === actionInstance;

            if (!currentModal) {
                return;
            }

            var rows = rowsFromResponse(response);
            layer.msg(messageFromResponse(response) || 'OK', {icon: 1});
            if ($actionModal && $actionModal.length && currentModal) {
                closeActionModal($actionModal, actionInstance);
            }
            if (currentModal && $button.data('crmui-row-action') === 'detail' && rows.length) {
                openActionModal($button, rows[0], []);
                return;
            }
            loadPage($button.closest('.crmui-page'));
        });
        pendingRequest.always(function() {
            currentModal = !$actionModal || !$actionModal.length ||
                $actionModal.data('actionInstance') === actionInstance;
            if ($actionModal && $actionModal.length && currentModal) {
                $actionModal.removeData('requestPending');
                $submitButton.prop('disabled', false).removeAttr('aria-busy');
            }
        });
    }

    function crmUiGiftRecipientFromRow(row) {
        return {
            user_id: rowValue(row, 'user_id'),
            address_id: rowValue(row, 'id'),
            recipient_name: rowValue(row, 'recipient_name'),
            recipient_phone: rowValue(row, 'recipient_phone'),
            recipient_address: rowValue(row, 'recipient_address')
        };
    }

    function crmUiGiftRecipientKey(recipient) {
        return String(recipient.address_id || '') + ':' + String(recipient.user_id || '');
    }

    function crmUiGiftRecipients($page) {
        return $page.data('giftRecipients') || [];
    }

    function updateCrmUiGiftRecipientPreview($page) {
        var recipients = crmUiGiftRecipients($page);
        var text = recipients.length ? recipients.length + ' recipient(s) selected' : '0';

        $page.find('[name="recipients_payload"]').val(JSON.stringify(recipients));
        $page.find('[data-crmui-gift-recipient-preview]').text(text);
    }

    function addCrmUiGiftRecipient($page, row) {
        var recipient = crmUiGiftRecipientFromRow(row || {});
        var recipients = crmUiGiftRecipients($page).slice();
        var key = crmUiGiftRecipientKey(recipient);
        var exists;

        if (!recipient.user_id || !recipient.address_id || !recipient.recipient_name || !recipient.recipient_phone || !recipient.recipient_address) {
            layer.msg('Select a complete recipient address first', {icon: 0});
            return;
        }

        exists = recipients.some(function(item) {
            return crmUiGiftRecipientKey(item) === key;
        });

        if (!exists) {
            recipients.push(recipient);
            $page.data('giftRecipients', recipients);
        }

        updateCrmUiGiftRecipientPreview($page);
        focusCreateForm($page);
    }

    /**
     * 执行后台持仓汇总旧代理钻取。
     *
     * @param {jQuery} $button 当前行按钮，提供 recordKey、payloadName 和 extraPayload 声明。
     * @param {Object} row 当前代理行；row.user_id 会写入 userPId。
     * @returns {void} 成功时只重载当前 CrmUI 页面，不打开通用弹窗。
     */
    function positionSummaryDrilldown($button, row) {
        var $page = $button.closest('.crmui-page');
        var recordKey = $button.data('record-key') || 'user_id';
        var payloadName = $button.data('payload-name') || 'userPId';
        var userId = row[recordKey] || row.user_id || '';
        var payload = $.extend({}, readRowActionExtraPayload($button), {
            userPId: row.user_id
        });

        if (!userId) {
            layer.msg('Missing user_id', {icon: 0});
            return;
        }

        payload.searchtype = payload.searchtype || 'subAgentsSearch';
        payload[payloadName] = userId;
        $page.data('crmuiExtraFilter', payload);
        loadPage($page);
    }

    /**
     * 执行后台持仓汇总交易明细下钻。
     *
     * @param {jQuery} $button 当前行按钮，data-extra-payload 中声明交易页默认 mode。
     * @param {Object} row 当前持仓汇总行，row.user_id 会写入交易订单页查询参数。
     * @returns {void} 成功时跳转到 CrmUI 交易页；缺少用户 ID 时提示并终止。
     */
    function positionSummaryTradeDetail($button, row) {
        var $page = $button.closest('.crmui-page');
        var filters = currentPageFilter($page);
        var payload = readRowActionExtraPayload($button);
        var userId = row.user_id || '';
        var url;

        if (!userId) {
            layer.msg('Missing user_id', {icon: 0});
            return;
        }

        url = new URL('/admin-crmui/trades', window.location.origin);
        url.searchParams.set('user_id', userId);
        if (filters.start_date) {
            url.searchParams.set('start_date', filters.start_date);
        }
        if (filters.end_date) {
            url.searchParams.set('end_date', filters.end_date);
        }
        url.searchParams.set('mode', payload.mode || 'all');

        window.location.href = url.toString();
    }

    /**
     * 执行后台持仓汇总风险联动下钻。
     *
     * @param {jQuery} $button 当前行按钮，data-extra-payload 声明目标风险视图。
     * @param {Object} row 当前持仓汇总行，只接受 row.user_id 作为 CRM 业务用户编号。
     * @returns {void} 成功时进入 CrmUI 风控页；缺少业务用户 ID 时提示并终止，避免把 MT4 login 当作 user_id。
     */
    function positionSummaryRiskDetail($button, row) {
        var $page = $button.closest('.crmui-page');
        var filters = currentPageFilter($page);
        var payload = readRowActionExtraPayload($button);
        var userId = row.user_id || '';
        var url;

        if (!userId) {
            layer.msg('Missing user_id', {icon: 0});
            return;
        }

        url = new URL('/admin-crmui/risk', window.location.origin);
        url.searchParams.set('user_id', userId);
        if (filters.start_date) {
            url.searchParams.set('start_date', filters.start_date);
        }
        if (filters.end_date) {
            url.searchParams.set('end_date', filters.end_date);
        }
        url.searchParams.set('mode', payload.mode || 'positions');

        window.location.href = url.toString();
    }

    function clearCrmUiGiftRecipients($page) {
        $page.data('giftRecipients', []);
        updateCrmUiGiftRecipientPreview($page);
    }

    function crmUiGiftSendPayload($form, payload) {
        var $page = $form.closest('.crmui-page');
        var recipients;

        if ($page.attr('data-crmui-gift-recipient-picker') !== '1') {
            return payload;
        }

        try {
            recipients = JSON.parse(payload.recipients_payload || '[]');
        } catch (error) {
            recipients = [];
        }

        payload.recipients = recipients;
        delete payload.recipients_payload;

        return payload;
    }

    function bindViewTabs() {
        $(document).on('click', '[data-crmui-view]', function() {
            var $tab = $(this);
            var $page = $tab.closest('.crmui-page');

            $tab.addClass('is-active').attr('aria-selected', 'true')
                .siblings('[data-crmui-view]').removeClass('is-active').attr('aria-selected', 'false');
            $page.attr('data-api-url', $tab.data('api-url'));
            $page.attr('data-api-method', $tab.data('api-method') || 'GET');
            $page.attr('data-crmui-active-view', $tab.data('crmui-view') || '');
            applyViewColumns($page, viewColumns($tab));
            resetPageState($page);
            loadPage($page);
        });
    }

    function viewColumns($tab) {
        var raw = String($tab.attr('data-columns') || '[]');

        try {
            var columns = JSON.parse(raw);
            return Array.isArray(columns) ? columns : [];
        } catch (error) {
            return [];
        }
    }

    function applyViewColumns($page, columns) {
        var $header = $page.find('.crmui-table thead tr');
        var $actionColumn;

        if (!columns.length) {
            return;
        }

        $actionColumn = $header.find('[data-crmui-action-column]').detach();
        $header.empty();
        columns.forEach(function(column) {
            $('<th>')
                .attr('data-key', String(column.key || ''))
                .text(String(column.label || column.key || ''))
                .appendTo($header);
        });
        if ($actionColumn.length) {
            $header.append($actionColumn);
        }
    }

    // 上传交互统一走共享组件的文案与体积格式化，避免各家族各自硬编码英文提示。
    function bindUploads() {
        $(document).on('change', '[data-crmui-upload] input[type="file"]', function() {
            var file = this.files && this.files[0];
            var $upload = $(this).closest('[data-crmui-upload]');
            var emptyText = translate('front.no_file_selected', 'No file selected');
            var sizeText = file && window.CrmUpload ? window.CrmUpload.formatSize(file.size || 0) : '';

            $upload.find('[data-crmui-upload-name]').text(file ? file.name : emptyText);
            $upload.find('[data-crmui-upload-size]').text(file ? sizeText : '');
            $upload.toggleClass('has-file', !!file);
            if (window.CrmFieldErrors) {
                window.CrmFieldErrors.clearUpload(document, String($upload.attr('data-crmui-upload') || ''));
            }
        });
    }

    /**
     * 读取语言包文案，缺键时回退到调用方给出的兜底串。
     *
     * @param {string} key 语言键。
     * @param {string} fallback 兜底文案。
     * @return {string} 展示文案。
     */
    function translate(key, fallback) {
        var value = window.CrmLang && window.CrmLang.t ? window.CrmLang.t(key) : '';

        return value && value !== key ? value : fallback;
    }

    function bindPageActions() {
        $(document).on('click', '[data-crmui-action]', function() {
            var $button = $(this);
            var action = $button.data('crmui-action');
            var $page = $button.closest('.crmui-page');

            if (action === 'refresh') {
                loadPage($page);
                return;
            }
            if (action === 'create') {
                focusCreateForm($page);
                return;
            }
            if (action === 'import') {
                openImportDialog($button, $page);
                return;
            }
            if (action === 'template') {
                downloadCsv($button.attr('data-action-url'), {}, $button.attr('data-file-name') || 'template.csv');
                return;
            }
            if (action === 'export') {
                exportPage($page, $button);
            }
        });
    }

    /**
     * 打开 CSV 批量导入弹窗：共享上传组件（deferred 模式缓存文件），提交走 CrmAjax.upload 携带管理员令牌。
     *
     * @param {jQuery} $button 触发导入动作的按钮；data-action-url 为批量导入端点。
     * @param {jQuery} $page 按钮所属 CrmUI 页面容器。
     * @returns {void}
     */
    function openImportDialog($button, $page) {
        var $modal = $page.find('[data-crmui-import-modal]');
        var $submitButton = $modal.find('[data-crmui-import-submit]');

        if (!$modal.length) {
            return;
        }

        $submitButton.attr('data-import-url', $button.attr('data-action-url') || $submitButton.attr('data-import-url') || '');
        $modal.removeData('importButton');
        $modal.data('importButton', $button);
        $modal.removeAttr('hidden');
        if (window.CrmUpload) {
            CrmUpload.init($modal[0]);
        }
        window.setTimeout(function() {
            $modal.find('button:visible').first().trigger('focus');
        }, 0);
    }

    function closeImportDialog($modal) {
        var returnFocus = $modal.data('importButton');

        $modal.attr('hidden', true);
        if (window.CrmUpload) {
            CrmUpload.reset('csv_import', false);
        }
        if (returnFocus && document.documentElement.contains(returnFocus)) {
            $(returnFocus).trigger('focus');
        }
    }

    function submitImportDialog($modal) {
        var $submitButton = $modal.find('[data-crmui-import-submit]');
        var url = String($submitButton.attr('data-import-url') || '');
        var file = window.CrmUpload ? CrmUpload.file('csv_import') : null;
        var formData;

        if (!url) {
            layer.msg(translate('crmui.common.request_failed', 'Request failed'), {icon: 2});
            return;
        }
        if (!file) {
            layer.msg(translate('front.no_file_selected', 'No file selected'), {icon: 0});
            return;
        }

        formData = new FormData();
        formData.append('file', file);
        $submitButton.prop('disabled', true).attr('aria-busy', 'true');
        CrmAjax.upload({
            guard: 'admin',
            url: url,
            formData: formData,
            success: function(res) {
                var successCodes = {1000: true, 1001: true, 1002: true};
                var count = res && Array.isArray(res.data) ? res.data.length : 0;

                $submitButton.prop('disabled', false).removeAttr('aria-busy');
                if (res && successCodes[res.code]) {
                    closeImportDialog($modal);
                    loadPage($modal.closest('.crmui-page'));
                    layer.msg(translate('admin.import_csv_result', 'Batch import finished: :count records created.').replace(':count', count), {icon: 1});
                    return;
                }
                layer.msg((res && res.message) || translate('crmui.common.request_failed', 'Request failed'), {icon: 2});
            },
            error: function(res) {
                $submitButton.prop('disabled', false).removeAttr('aria-busy');
                layer.msg((res && res.message) || translate('crmui.common.request_failed', 'Request failed'), {icon: 2});
            }
        });
    }

    function bindRowActions() {
        $(document).on('click', '[data-crmui-row-action]', function() {
            var $button = $(this);
            var row = {};
            var fields = actionFields($button);
            var confirmText = $button.data('confirm');
            var actionKey = String($button.data('crmui-row-action') || '');

            try {
                row = JSON.parse(decodeURIComponent($button.attr('data-row') || '%7B%7D'));
            } catch (error) {
                row = {};
            }

            if (actionKey === 'review' || actionKey === 'review_auth') {
                fields = authReviewFieldsForRow(row, fields);
                if (!fields.length) {
                    return;
                }
            }

            if (actionKey === 'select_gift_recipient') {
                addCrmUiGiftRecipient($button.closest('.crmui-page'), row);
                return;
            }

            if (actionKey === 'ip_detail') {
                openIpRiskDetail($button, row);
                return;
            }

            if ($button.data('crmui-row-action') === 'position_summary_drilldown') {
                positionSummaryDrilldown($button, row);
                return;
            }

            if ($button.data('crmui-row-action') === 'position_summary_trades') {
                positionSummaryTradeDetail($button, row);
                return;
            }

            if ($button.data('crmui-row-action') === 'position_summary_risk') {
                positionSummaryRiskDetail($button, row);
                return;
            }

            if ($button.attr('data-crmui-local-modal') === '1' || fields.length) {
                openActionModal($button, row, fields);
                return;
            }

            layer.confirm(confirmText || $button.text(), function(index) {
                layer.close(index);
                submitRowAction($button, row, {});
            });
        });

        $(document).on('click', '[data-crmui-import-close], [data-crmui-import-modal] .crmui-modal-backdrop', function() {
            closeImportDialog($(this).closest('[data-crmui-import-modal]'));
        });

        $(document).on('click', '[data-crmui-import-submit]', function() {
            submitImportDialog($(this).closest('[data-crmui-import-modal]'));
        });

        $(document).on('click', '[data-crmui-modal-close]', function() {
            closeActionModal($(this).closest('[data-crmui-action-modal]'));
        });

        $(document).on('click', '[data-crmui-ip-detail-close]', function() {
            closeIpRiskDetail($(this).closest('[data-crmui-ip-detail-modal]'));
        });

        $(document).on('keydown', function(event) {
            var $modal;

            if (event.key !== 'Escape') {
                return;
            }

            $modal = $('[data-crmui-ip-detail-modal]').filter(function() {
                return !this.hasAttribute('hidden');
            }).first();
            if ($modal.length) {
                closeIpRiskDetail($modal);
                return;
            }

            $modal = $('[data-crmui-action-modal]').filter(function() {
                return !this.hasAttribute('hidden');
            }).first();
            if ($modal.length) {
                closeActionModal($modal);
            }
        });

        $(document).on('submit', '[data-crmui-action-form]', function(event) {
            var $form = $(this);
            var $modal = $form.closest('[data-crmui-action-modal]');
            var $button = $modal.data('actionButton');
            var row = $modal.data('row') || {};
            var actionKey = String($button.data('crmui-row-action') || '');
            var review;
            var reviewFields;
            var fields;

            event.preventDefault();
            fields = actionFields($button);
            if (!validateRequiredActionFields($form, fields)) {
                return;
            }

            if (actionKey === 'review' || actionKey === 'review_auth') {
                reviewFields = authReviewFieldsForRow(row, fields);
                review = buildAuthReviewPayload(
                    row,
                    readForm($form),
                    reviewFields,
                    String($button.text() || '').trim()
                );
                if (review.error) {
                    layer.msg(review.error, {icon: 0});
                    return;
                }
                submitRowAction($button, row, review.payload, $modal);
                return;
            }
            submitRowAction($button, row, readForm($form), $modal);
        });
    }

    function openIpRiskDetail($button, row) {
        var $page = $button.closest('.crmui-page');
        var $modal = $page.find('[data-crmui-ip-detail-modal]');
        var recordKey = $button.data('record-key') || 'login_ip';
        var loginIp = String(row[recordKey] || row.login_ip || '');
        var loadGeneration = Number($modal.data('loadGeneration') || 0) + 1;
        var $content = $modal.find('.crmui-ip-detail-content');

        if (!loginIp || !$modal.length) {
            layer.msg('Missing login_ip', {icon: 0});
            return;
        }

        $modal.data('loadGeneration', loadGeneration);
        $modal.data('returnFocus', $button.get(0));
        $modal.find('[data-crmui-ip-detail-title]').text($button.text() + ' - ' + loginIp);
        setIpRiskDetailState($modal, 'loading', $content.data('loading-text'));
        $modal.removeAttr('hidden');
        $modal.find('[data-crmui-ip-detail-close]').last().trigger('focus');

        request({
            url: $button.data('action-url'),
            method: $button.data('action-method') || 'POST',
            data: {login_ip: loginIp},
            onError: function(error) {
                if ($modal.data('loadGeneration') !== loadGeneration) {
                    return;
                }

                setIpRiskDetailState(
                    $modal,
                    'error',
                    messageFromResponse(error && error.responseJSON) || $content.data('error-text')
                );
            }
        }).done(function(response) {
            var rows;

            if ($modal.data('loadGeneration') !== loadGeneration) {
                return;
            }

            rows = rowsFromResponse(response);
            if (!rows.length) {
                setIpRiskDetailState($modal, 'empty', $content.data('empty-text'));
                return;
            }

            renderIpRiskDetailRows($modal, rows);
        });
    }

    function renderIpRiskDetailRows($modal, rows) {
        var columns = [];

        $modal.find('thead th[data-key]').each(function() {
            columns.push({key: String($(this).data('key') || ''), label: $(this).text()});
        });
        $modal.find('[data-crmui-ip-detail-body]').html(rows.map(function(row) {
            return '<tr>' + columns.map(function(column) {
                var value = row[column.key];

                return '<td data-label="' + escapeHtml(column.label) + '">' +
                    escapeHtml(value === undefined || value === null ? '' : value) + '</td>';
            }).join('') + '</tr>';
        }).join(''));
        setIpRiskDetailState($modal, 'ready', '');
    }

    function setIpRiskDetailState($modal, state, text) {
        var ready = state === 'ready';

        $modal.find('[data-crmui-ip-detail-status]')
            .attr('data-ui-state', state)
            .text(text || '')
            .toggle(!ready);
        $modal.find('[data-crmui-ip-detail-table]').prop('hidden', !ready);
    }

    function closeIpRiskDetail($modal) {
        var returnFocus = $modal.data('returnFocus');

        $modal.data('loadGeneration', Number($modal.data('loadGeneration') || 0) + 1);
        $modal.attr('hidden', true).removeData('returnFocus');
        if (returnFocus && typeof returnFocus.focus === 'function') {
            returnFocus.focus();
        }
    }

    function bindShell() {
        var $sidebar = $('#crmuiSidebar');
        var $sidebarToggles = $('[data-crmui-toggle-sidebar]');
        var sidebarMedia;
        var lastSidebarToggle = null;

        if (sidebarBindingState) {
            if (typeof sidebarBindingState.media.removeEventListener === 'function') {
                sidebarBindingState.media.removeEventListener('change', sidebarBindingState.listener);
            } else {
                sidebarBindingState.media.removeListener(sidebarBindingState.listener);
            }
            sidebarBindingState = null;
        }
        $(document).off('.crmuiSidebar');

        if (!$sidebar.length || !$sidebarToggles.length) {
            return;
        }

        sidebarMedia = window.matchMedia('(max-width: 768px)');

        function setSidebarOpen(open) {
            var shouldOpen = sidebarMedia.matches && open === true;
            var shouldHide = sidebarMedia.matches && !shouldOpen;

            $sidebar.toggleClass('is-open', shouldOpen);
            $sidebar.attr('aria-hidden', shouldHide ? 'true' : 'false');
            $sidebar.prop('inert', shouldHide);
            $sidebarToggles.attr('aria-expanded', shouldOpen ? 'true' : 'false');

            return shouldOpen;
        }

        function focusSidebar() {
            var target = $sidebar.find('.crmui-nav-link').get(0) || $sidebar.get(0);

            if (target && typeof target.focus === 'function') {
                target.focus();
            }
        }

        function closeSidebar(restoreToggleFocus) {
            var wasOpen = $sidebar.hasClass('is-open');

            setSidebarOpen(false);
            if (restoreToggleFocus === true && wasOpen && lastSidebarToggle &&
                typeof lastSidebarToggle.focus === 'function') {
                lastSidebarToggle.focus();
            }
        }

        $(document).on('click.crmuiSidebar', '[data-crmui-toggle-sidebar]', function() {
            lastSidebarToggle = this;
            if (setSidebarOpen(!$sidebar.hasClass('is-open'))) {
                focusSidebar();
            }
        });

        $(document).on('click.crmuiSidebar', '[data-crmui-sidebar-dismiss]', function() {
            closeSidebar(true);
        });
        $(document).on('click.crmuiSidebar', '.crmui-nav-link', function() {
            closeSidebar(false);
        });
        $(document).on('keydown.crmuiSidebar', function(event) {
            if (event.key === 'Escape') {
                closeSidebar(true);
            }
        });

        if (typeof sidebarMedia.addEventListener === 'function') {
            sidebarMedia.addEventListener('change', setSidebarClosedAtBreakpoint);
        } else {
            sidebarMedia.addListener(setSidebarClosedAtBreakpoint);
        }
        sidebarBindingState = {
            media: sidebarMedia,
            listener: setSidebarClosedAtBreakpoint
        };

        function setSidebarClosedAtBreakpoint() {
            closeSidebar(false);
        }
        closeSidebar(false);

        $(document).on('click.crmuiSidebar', '[data-crmui-lang]', function() {
            var lang = $(this).data('crmui-lang');
            var url = new URL(window.location.href);
            url.searchParams.set('locale', lang);
            localStorage.setItem('crm_locale', lang);
            window.location.href = url.toString();
        });

        $(document).on('click.crmuiSidebar', '[data-crmui-logout]', function() {
            var url = $(this).data('api-url');
            request({url: url, method: 'POST'}).always(function() {
                clearToken();
                window.location.href = loginPath;
            });
        });
    }

    function bindForms() {
        $(document).on('submit', '[data-crmui-auth-form]', function(event) {
            var $form = $(this);
            event.preventDefault();

            request({
                url: $form.data('action-url'),
                method: 'POST',
                data: readForm($form),
                auth: false
            }).done(function(response) {
                var data = dataFromResponse(response);
                var token = data.token || data.access_token || data.jwt_token || data.jwtToken;

                setToken(token);
                layer.msg(messageFromResponse(response) || 'OK', {icon: 1});
                window.location.href = $form.data('success-url') || '/admin-crmui/dashboard';
            });
        });

        $(document).on('click', '[data-crmui-secondary-action="send-email-code"]', function() {
            var $button = $(this);
            var $form = $button.closest('form');
            request({
                url: $button.data('action-url'),
                method: 'POST',
                data: {email: $form.find('[name="email"]').val()},
                auth: false
            }).done(function(response) {
                layer.msg(messageFromResponse(response) || 'OK', {icon: 1});
            });
        });

        $(document).on('submit', '[data-crmui-form]', function(event) {
            var $form = $(this);
            var $page = $form.closest('.crmui-page');
            var $submit = $form.find('button[type="submit"]');
            var payload;
            var pendingRequest;

            event.preventDefault();
            if ($form.data('requestPending')) {
                return;
            }

            payload = crmUiGiftSendPayload($form, formPayload($form));
            $form.data('requestPending', true);
            $submit.prop('disabled', true).attr('aria-busy', 'true');

            pendingRequest = request({
                url: $form.data('action-url'),
                method: $form.data('action-method') || 'POST',
                data: payload
            }).done(function(response) {
                layer.msg(messageFromResponse(response) || 'OK', {icon: 1});
                if ($page.attr('data-crmui-gift-recipient-picker') === '1') {
                    clearCrmUiGiftRecipients($page);
                    $form[0].reset();
                }
                loadPage($page);
            });
            pendingRequest.always(function() {
                $form.removeData('requestPending');
                $submit.prop('disabled', false).removeAttr('aria-busy');
            });
        });

        $(document).on('input', '[name="amount"]', function() {
            var $form = $(this).closest('form');
            var value = parseFloat($(this).val() || '0');
            $form.find('[data-crmui-money-preview]').text(value > 0 ? value.toFixed(2) : '--');
        });

        $(document).on('submit', '[data-crmui-filter]', function(event) {
            var $page = $(this).closest('.crmui-page');

            event.preventDefault();
            resetPageState($page);
            loadPage($page);
        });

        $(document).on('click', '[data-crmui-reset]', function() {
            var $page = $(this).closest('.crmui-page');
            $page.find('[data-crmui-filter]')[0].reset();
            $page.removeData('crmuiExtraFilter');
            resetPageState($page);
            loadPage($page);
        });

        $(document).on('click', '[data-crmui-page-prev], [data-crmui-page-next]', function() {
            var $button = $(this);
            var $page = $button.closest('.crmui-page');
            var state = pageState($page);
            var delta = $button.is('[data-crmui-page-next]') ? 1 : -1;
            var nextPage = Math.min(Math.max(state.page + delta, 1), state.lastPage);

            if (nextPage === state.page) {
                return;
            }

            state.page = nextPage;
            renderPagination($page, state);
            loadPage($page);
        });

    }

    /**
     * 绑定批量操作交互：全选、单行勾选联动、打开弹窗、提交与关闭。
     *
     * 全部使用 $(document) 委托，因此表格重渲染后无需重新绑定；
     * 未声明 batch 的页面不会渲染任何 data-crmui-select-row / data-crmui-batch-open 节点，
     * 因此这些处理器对其余页面完全无副作用。
     *
     * @returns {void}
     */
    function bindBatchActions() {
        // 全选：只作用于当前页未禁用的勾选框，不跨页保持选择（与服务端分页语义一致）。
        $(document).on('change', '[data-crmui-select-all]', function() {
            var $page = $(this).closest('.crmui-page');
            var checked = $(this).prop('checked');

            $page.find('[data-crmui-select-row]').not(':disabled').prop('checked', checked);
            syncBatchSelectionState($page);
        });

        // 单行勾选：回写全选框的三态（全选/未选/部分选），并刷新已选数提示。
        $(document).on('change', '[data-crmui-select-row]', function() {
            syncBatchSelectionState($(this).closest('.crmui-page'));
        });

        $(document).on('click', '[data-crmui-batch-open]', function() {
            openBatchModal($(this));
        });

        $(document).on('click', '[data-crmui-batch-close]', function() {
            closeBatchModal($(this).closest('[data-crmui-batch-modal]'));
        });

        // 目标状态切换时同步备注必填标记，让「拒绝必须填备注」在提交前就可见。
        $(document).on('change', '[data-crmui-batch-form] input[name="target_status"]', function() {
            var $form = $(this).closest('[data-crmui-batch-form]');
            var required = String($(this).attr('data-remark-required') || '0') === '1';

            $form.find('[data-crmui-batch-remark]').prop('required', required);
        });

        $(document).on('submit', '[data-crmui-batch-form]', function(event) {
            event.preventDefault();
            submitBatchForm($(this));
        });
    }

    /**
     * 同步全选框三态与已选数提示。
     *
     * @param {jQuery} $page 当前 CrmUI 页面容器。
     * @returns {void}
     */
    function syncBatchSelectionState($page) {
        var $eligible = $page.find('[data-crmui-select-row]').not(':disabled');
        var $checked = $eligible.filter(':checked');
        var $all = $page.find('[data-crmui-select-all]');

        $all.prop('checked', $eligible.length > 0 && $checked.length === $eligible.length);
        $all.prop('indeterminate', $checked.length > 0 && $checked.length < $eligible.length);
    }

    /**
     * 打开批量弹窗前完成勾选校验，并按来源状态禁用非法目标项。
     *
     * 校验顺序与旧后台一致：先要求至少勾选一行，再要求来源状态一致；
     * 来源状态混合时同一目标状态对部分行必然非法，因此必须在打开弹窗前拦住。
     *
     * @param {jQuery} $button 批量入口按钮，承载 transitions 等声明。
     * @returns {void}
     */
    function openBatchModal($button) {
        var $page = $button.closest('.crmui-page');
        var $modal = $page.find('[data-crmui-batch-modal]');
        var batchConfig = batchConfigFor($page);
        var $checked = $page.find('[data-crmui-select-row]:checked').not(':disabled');
        var sourceStatus;
        var uniform = true;
        var allowed;

        if (!$modal.length || !batchConfig) {
            return;
        }

        if (!$checked.length) {
            layer.msg(translate('admin.batch_select_records_first', 'Select at least one record first'), {icon: 0});
            return;
        }

        sourceStatus = String($checked.first().attr('data-source-status'));
        $checked.each(function() {
            if (String($(this).attr('data-source-status')) !== sourceStatus) {
                uniform = false;
            }
        });
        if (!uniform) {
            layer.msg(translate('admin.batch_select_same_status', 'Select records that share the same current status'), {icon: 2});
            return;
        }

        allowed = batchConfig.transitions[sourceStatus] || [];
        if (!allowed.length) {
            layer.msg(translate('admin.batch_select_processable_only', 'Only records in Pending or Processing status can be selected'), {icon: 2});
            return;
        }

        // 重置上一次的选择与备注，并按白名单禁用非法目标状态。
        $modal.find('input[name="target_status"]').each(function() {
            var value = String($(this).val());

            $(this).prop('checked', false).prop('disabled', allowed.indexOf(value) === -1);
        });
        $modal.find('[data-crmui-batch-remark]').val('').prop('required', false);
        $modal.find('[data-crmui-batch-count]').text(
            translate('admin.batch_selected_count', ':count record(s) selected')
                .replace(':count', String($checked.length))
        );
        $modal.data('batchSourceStatus', sourceStatus);
        $modal.removeAttr('hidden');
        $modal.data('batchReturnFocus', $button);
        window.setTimeout(function() {
            $modal.find('input[name="target_status"]').not(':disabled').first().trigger('focus');
        }, 0);
    }

    /**
     * 关闭批量弹窗并把焦点还给触发按钮，保证键盘操作不丢焦点。
     *
     * @param {jQuery} $modal 批量弹窗容器。
     * @returns {void}
     */
    function closeBatchModal($modal) {
        var returnFocus = $modal.data('batchReturnFocus');

        $modal.attr('hidden', true);
        if (returnFocus && document.documentElement.contains(returnFocus[0] || returnFocus)) {
            $(returnFocus).trigger('focus');
        }
    }

    /**
     * 提交批量操作。
     *
     * 载荷结构与旧后台 batchWithdrawApply 完全一致：payload.status 为目标状态，
     * payload.remark 为备注，payload.orderList 逐条给出 recordId；
     * 后端按 status 动态改判权限，再逐条复用现代状态机，因此这里不做任何状态写入判断。
     *
     * 部分失败（3006）与全部失败（4005）都不在 businessCodeSucceeded 白名单内，
     * 会走 onError；但对批量而言这是正常业务结果，因此 onError 里先判断响应是否带
     * data.total，带则按批量结果渲染成功/失败计数，不带才当作真正的请求错误。
     *
     * @param {jQuery} $form 批量弹窗内的表单。
     * @returns {void}
     */
    function submitBatchForm($form) {
        var $page = $form.closest('.crmui-page');
        var $modal = $form.closest('[data-crmui-batch-modal]');
        var batchConfig = batchConfigFor($page);
        var sourceStatus = String($modal.data('batchSourceStatus') || '');
        var $target = $form.find('input[name="target_status"]:checked');
        var targetStatus = String($target.val() || '');
        var remark = String($form.find('[data-crmui-batch-remark]').val() || '').trim();
        var allowed;
        var orderList;

        if (!batchConfig) {
            return;
        }

        if (targetStatus === '') {
            layer.msg(translate('admin.batch_target_status_required', 'Select a target status'), {icon: 0});
            return;
        }

        allowed = batchConfig.transitions[sourceStatus] || [];
        if (allowed.indexOf(targetStatus) === -1) {
            layer.msg(translate('admin.batch_target_status_invalid', 'The current status cannot transition to the selected target status'), {icon: 2});
            return;
        }

        // 目标状态标记了 remarkRequired（出金拒绝）时备注必填：后端 reject() 要求非空 reason。
        if (String($target.attr('data-remark-required') || '0') === '1' && remark === '') {
            layer.msg(translate('admin.reject_reason_required', 'A reason is required when rejecting'), {icon: 0});
            return;
        }

        orderList = $page.find('[data-crmui-select-row]:checked').not(':disabled').map(function() {
            return {recordId: $(this).val()};
        }).get();

        if (!orderList.length) {
            layer.msg(translate('admin.batch_select_records_first', 'Select at least one record first'), {icon: 0});
            return;
        }

        request({
            url: batchConfig.url,
            method: batchConfig.method,
            data: {
                payload: {
                    status: targetStatus,
                    remark: remark,
                    orderList: orderList
                }
            },
            onError: function(error) {
                var response = (error && error.responseJSON) || null;

                // 带 data.total 说明后端已逐条处理完（部分或全部失败），按批量结果展示而非报错。
                if (response && response.data && typeof response.data.total !== 'undefined') {
                    finishBatchSubmit($page, $modal, response);
                    return;
                }

                layer.msg(messageFromResponse(response) || 'Request failed', {icon: 2});
            }
        }).then(function(response) {
            finishBatchSubmit($page, $modal, response);
        });
    }

    /**
     * 渲染批量结果并刷新列表。
     *
     * @param {jQuery} $page 当前 CrmUI 页面容器。
     * @param {jQuery} $modal 批量弹窗容器。
     * @param {Object} response 后端响应，data 内含 total/success/failed。
     * @returns {void}
     */
    function finishBatchSubmit($page, $modal, response) {
        var data = (response && response.data) || {};
        var succeeded = Number(data.success || 0);
        var failed = Number(data.failed || 0);
        var summary = translate('admin.batch_withdraw_result', 'Batch completed: :success succeeded, :failed failed.')
            .replace(':success', String(succeeded))
            .replace(':failed', String(failed));

        closeBatchModal($modal);
        layer.msg(summary, {icon: failed === 0 ? 1 : 2, time: 3000});
        loadPage($page);
    }

    function init() {
        bindShell();
        bindForms();
        bindViewTabs();
        bindUploads();
        bindPageActions();
        bindRowActions();
        bindBatchActions();
        bindCrmUiAuthDetail();
        loadPermissionSlugs();

        $('.crmui-page').each(function() {
            var $page = $(this);

            if ($page.attr('data-crmui-auth-detail') === '1') {
                loadCrmUiAuthDetail($page);
                return;
            }

            loadOptions($page);
            loadPage($page);
        });
    }

    exports('crmuiAdmin', {init: init, request: request, loadPage: loadPage});
});

layui.use(['crmuiAdmin'], function() {
    layui.crmuiAdmin.init();
});
