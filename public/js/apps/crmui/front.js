// Created by PhpStorm.
// Project name co_crmv5.
// User: Huang Gang
// Date: 2026/08/28
// Time: 01:18
layui.define(['jquery', 'layer'], function(exports) {
    'use strict';

    var $ = layui.jquery;
    var layer = layui.layer;
    var tokenKeys = ['front_token', 'front_jwt_token'];
    var loginPath = '/front-crmui/login';
    var preferredListKeys = ['shipped_gifts'];
    var sidebarBindingState = null;
    var dynamicOptionUrls = {
        direct_agents: '/api/front/commissions/transfer-agent-options',
        symbols: '/api/front/trade-symbols',
        bigAgentSymbols: '/user/agents/trade-symbols'
    };
    var dynamicOptionMethods = {
        direct_agents: 'GET',
        symbols: 'GET',
        bigAgentSymbols: 'GET'
    };

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

    function currentLocale() {
        var stored = localStorage.getItem('crm_locale');
        var htmlLang = document.documentElement.getAttribute('lang') || '';

        if (stored === 'zh-CN' || stored === 'en') {
            return stored;
        }

        return htmlLang.toLowerCase().indexOf('zh') === 0 ? 'zh-CN' : 'en';
    }

    function headers(auth, extra) {
        var result = $.extend({'X-CSRF-TOKEN': csrfToken(), 'X-Locale': currentLocale()}, extra || {});
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

        return code === 0 || [
            1000, 1001, 1002, 1003, 1004,
            2000,
            3000, 3002, 3004, 3005
        ].indexOf(code) !== -1;
    }

    function successOrLegacy(response) {
        var hasCode = response && Object.prototype.hasOwnProperty.call(response, 'code');
        var legacyMessage;

        if (!response || typeof response !== 'object' || Array.isArray(response)) {
            return false;
        }

        legacyMessage = response.msg;
        if (legacyMessage !== undefined && legacyMessage !== '' &&
            legacyMessage !== 'SUC' && legacyMessage !== 'SUCCESS') {
            return false;
        }
        if (response.status !== undefined && response.status !== true) {
            return false;
        }
        if (hasCode) {
            return businessCodeSucceeded(response) &&
                (!legacyMessage || legacyMessage === 'SUC' || legacyMessage === 'SUCCESS');
        }

        return response.status === true || legacyMessage === 'SUC' || legacyMessage === 'SUCCESS' ||
            (legacyMessage === 'OK' && Number(response.loginStatus) === 200) ||
            ($.isArray(response.rows) && typeof response.total === 'number');
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
            headers: headers(options.auth, options.headers)
        }).then(function(response) {
            if (!(options.allowLegacy === true ? successOrLegacy(response) : businessCodeSucceeded(response))) {
                if (response && response.redirect && response.redirectUrl) {
                    window.location.href = response.redirectUrl;
                    return $.Deferred().reject({responseJSON: response, businessError: true}).promise();
                }
                var businessError = {responseJSON: response, businessError: true};
                reportError(businessError);
                return $.Deferred().reject(businessError).promise();
            }

            return response;
        }, function(xhr) {
            if (xhr.status === 401) {
                if (options.clearTokenOnUnauthorized !== false) {
                    clearToken();
                }
                window.location.href = options.unauthorizedRedirect || loginPath;
                return $.Deferred().reject(xhr).promise();
            }
            reportError(xhr);
            return $.Deferred().reject(xhr).promise();
        });
    }

    function messageFromResponse(response) {
        return response && (response.message || response.msg || response.error);
    }

    function normalizeUiFamily(family) {
        return family === 'crmui' || family === 'naive' ? family : 'layui';
    }

    function currentPagePath() {
        var pagePath = $('body').attr('data-crmui-page-path') || $('.crmui-page').attr('data-page-path') || 'dashboard';

        pagePath = String(pagePath || 'dashboard').replace(/^\/+|\/+$/g, '');
        return pagePath || 'dashboard';
    }

    function uiFamilyUrl(targetFamily, pagePath) {
        targetFamily = normalizeUiFamily(targetFamily);
        pagePath = String(pagePath || 'dashboard').replace(/^\/+|\/+$/g, '') || 'dashboard';

        if (targetFamily === 'layui') {
            return '/front/' + pagePath;
        }
        if (targetFamily === 'naive') {
            return '/front-naive/' + pagePath;
        }

        return '/front-crmui/' + pagePath;
    }

    function switchUiFamily(targetFamily) {
        targetFamily = normalizeUiFamily(targetFamily);

        var $target = $('[data-crmui-ui-target="' + targetFamily + '"]');
        var targetUrl = $target.attr('data-ui-url') || uiFamilyUrl(targetFamily, currentPagePath());

        localStorage.setItem('crm_ui_style', targetFamily);
        localStorage.setItem('front_ui_style', targetFamily);
        $('[data-crmui-ui-target]').removeClass('is-active').attr('aria-pressed', 'false');
        $target.addClass('is-active').attr('aria-pressed', 'true');

        if (targetUrl && targetUrl !== window.location.href) {
            window.location.href = targetUrl;
        }
    }

    function dataFromResponse(response) {
        if (!response) {
            return {};
        }
        return response.data !== undefined ? response.data : response;
    }

    function dataForList(response, listKey) {
        var data = dataFromResponse(response);

        if (listKey && data && typeof data === 'object' && data[listKey] !== undefined) {
            return data[listKey];
        }
        if (!listKey && data && typeof data === 'object') {
            var matchedKey = preferredListKeys.filter(function(key) {
                return data[key] !== undefined;
            })[0];

            if (matchedKey) {
                return data[matchedKey];
            }
        }

        return data;
    }

    function rowsFromData(data) {
        if ($.isArray(data)) {
            return data;
        }
        if ($.isArray(data.data)) {
            return data.data;
        }
        if (data.data && $.isArray(data.data.data)) {
            return data.data.data;
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

    function rowsFromResponse(response, listKey) {
        return rowsFromData(dataForList(response, listKey));
    }

    function footerRowsFromResponse(response, listKey) {
        var data = dataForList(response, listKey);
        var root = dataFromResponse(response);

        if (data && $.isArray(data.footer)) {
            return data.footer;
        }
        if (root && $.isArray(root.footer)) {
            return root.footer;
        }

        return [];
    }

    function totalFromResponse(response, rows, listKey) {
        var data = dataForList(response, listKey);
        return data.total || data.count || data.totalCount || rows.length;
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

    function normalizeFrontFundPayload($form, payload) {
        var pageKey = $form.closest('.crmui-page').attr('data-crmui-page') || '';
        var amount;
        var channel;
        var password;

        if (window.FormData && payload instanceof FormData) {
            return payload;
        }

        if (pageKey === 'front.deposit') {
            amount = payload.amount || payload.deposit_amt_usd || payload.deposit_amt || '';
            channel = payload.channel || payload.pay_channel || payload.passageway || '';

            if (amount !== '') {
                payload.deposit_amt_usd = amount;
                payload.deposit_amt = amount;
                payload.deposit_pay_amt_rmb = payload.deposit_pay_amt_rmb || amount;
            }
            if (channel !== '') {
                payload.pay_channel = channel;
                payload.passageway = channel;
            }
        }

        if (pageKey === 'front.withdraw') {
            amount = payload.amount || payload.withdraw_amt || '';
            password = payload.password || payload.withdraw_password || payload.withdraw_psw || '';

            if (amount !== '') {
                payload.withdraw_amt = amount;
            }
            if (password !== '') {
                payload.withdraw_password = password;
                payload.withdraw_psw = password;
            }
        }

        return payload;
    }

    function formPayload($form) {
        var payload = hasFileInput($form) ? new FormData($form[0]) : readForm($form);

        return normalizeFrontFundPayload($form, payload);
    }

    function secondaryPayload(action, $form) {
        var data = readForm($form);

        if (action === 'send-email-code') {
            delete data.password;
            delete data.password_confirmation;
            delete data.email_code;
            delete data.captcha_key;
            delete data.captcha_code;
            delete data.agree_terms;
            return data;
        }

        return {email: data.email || ''};
    }

    function captchaKey() {
        return 'crmui_' + Date.now().toString(36) + '_' + Math.random().toString(36).slice(2, 10);
    }

    function refreshCaptcha($image) {
        var src = $image.data('captcha-src');
        var key = captchaKey();
        var glue = src && src.indexOf('?') === -1 ? '?' : '&';

        if (!src) {
            return;
        }

        $image.closest('form').find('[name="captcha_key"]').val(key);
        $image.attr('src', src + glue + 'key=' + encodeURIComponent(key) + '&_=' + Date.now());
    }

    function escapeHtml(value) {
        return String(value === undefined || value === null ? '' : value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function translate(key) {
        if (typeof window.CrmLang !== 'undefined' && window.CrmLang && typeof window.CrmLang.t === 'function') {
            return window.CrmLang.t(key);
        }

        return key;
    }

    function stripHtml(value) {
        return String(value === undefined || value === null ? '' : value)
            .replace(/<script[\s\S]*?<\/script>/gi, '')
            .replace(/<style[\s\S]*?<\/style>/gi, '')
            .replace(/<[^>]+>/g, ' ')
            .replace(/&nbsp;/g, ' ')
            .replace(/\s+/g, ' ')
            .trim();
    }

    function jsonFromAttr($element, attrName, fallback) {
        var raw = $element.attr(attrName);

        if (!raw) {
            return fallback;
        }

        try {
            return JSON.parse(raw);
        } catch (error) {
            return fallback;
        }
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

    function listFromValue(value) {
        if ($.isArray(value)) {
            return value;
        }
        if (value && typeof value === 'object') {
            return Object.keys(value).sort(function(left, right) {
                return Number(left) - Number(right);
            }).map(function(key) {
                return value[key];
            });
        }

        return [];
    }

    function agentLevelSelectHtml(row, rowIndex) {
        var levels = listFromValue(rowValue(row, 'range_list'));
        var currentLevel = String(firstPresentValue([rowValue(row, 'userGroupId'), rowValue(row, 'level_id')]));
        var currentRate = String(firstPresentValue([rowValue(row, 'commprop'), rowValue(row, 'comm_rate')]));
        var hasSelected = levels.some(function(item) {
            return truthyFlag(item && item.selected);
        });

        if (!levels.length) {
            return escapeHtml(firstPresentValue([currentRate, currentLevel, cellValue(row, 'userGroupId')]));
        }

        return '<select class="crmui-input crmui-agent-level-select" data-crmui-agent-level-select data-row-index="' + rowIndex + '">' +
            levels.map(function(item) {
                var levelId;
                var prop;
                var selected;
                var label;

                item = item || {};
                levelId = String(firstPresentValue([item.level_id, item.choice_gid, item.value, item.id]));
                prop = String(firstPresentValue([item.prop, item.comm_prop, item.user_commission, item.user_min_prop]));
                selected = truthyFlag(item.selected) || (!hasSelected && (currentLevel ? levelId === currentLevel : prop === currentRate));
                label = firstPresentValue([item.level_name, item.name, item.label, levelId]) + ' / ' + (prop || '--');

                return '<option value="' + escapeHtml(levelId) + '"' +
                    ' data-comm-prop="' + escapeHtml(prop) + '"' +
                    ' data-def-gid="' + escapeHtml(firstPresentValue([item.def_gid, levelId])) + '"' +
                    ' data-choice-gid="' + escapeHtml(firstPresentValue([item.choice_gid, levelId])) + '"' +
                    ' data-extra-val="' + escapeHtml(firstPresentValue([item.extra_val, 0])) + '"' +
                    (selected ? ' selected' : '') + '>' + escapeHtml(label) + '</option>';
            }).join('') +
        '</select>';
    }

    function crmUiActionCellHtml($page, row, column) {
        var value = cellValue(row, column.key);
        var id = recordIdentifier(row, column.recordKey || column.key);

        return '<button class="crmui-cell-link" type="button" data-crmui-cell-action="' + escapeHtml(column.action) + '" data-record-id="' + escapeHtml(id) + '">' + escapeHtml(value) + '</button>';
    }

    function cellHtml($page, row, column, rowIndex) {
        if (($page.attr('data-crmui-page') || '') === 'front.agent_confirm_level' && column.format === 'agentLevelSelect') {
            return agentLevelSelectHtml(row, rowIndex);
        }
        if (column.action) {
            return crmUiActionCellHtml($page, row, column);
        }

        return escapeHtml(cellValue(row, column.key));
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

    function rowActionsHtml($page, row) {
        var template = $page.find('template[data-crmui-row-actions]').html();
        var encodedRow;
        var $actions;

        if (!template) {
            return '';
        }

        encodedRow = encodeURIComponent(JSON.stringify(row || {}));
        $actions = $(template);
        $actions.find('[data-crmui-row-action]').attr('data-row', encodedRow);
        $actions.find('[data-crmui-row-link]').each(function() {
            var $link = $(this);
            var id = recordIdentifier(row, $link.data('record-key'));
            $link.attr('href', String($link.data('crmui-href') || '').replace(/__ID__/g, encodeURIComponent(id)));
        });

        return $actions.prop('outerHTML') || '';
    }

    function renderRows($page, rows) {
        var $body = $page.find('[data-crmui-table-body]');
        var columns = [];
        var emptyText = $page.data('empty-text') || 'No records';
        var hasRowActions = $page.find('template[data-crmui-row-actions]').length > 0;

        $page.find('thead th[data-key]').each(function() {
            var $th = $(this);
            var key = $th.data('key');
            if (key) {
                columns.push({
                    key: key,
                    label: $th.text(),
                    format: $th.data('format') || '',
                    action: $th.data('action') || '',
                    recordKey: $th.data('record-key') || key
                });
            }
        });

        if (!rows.length) {
            $body.html('<tr><td colspan="' + Math.max(columns.length + (hasRowActions ? 1 : 0), 1) + '">' + escapeHtml(emptyText) + '</td></tr>');
            return;
        }

        $body.html(rows.map(function(row, rowIndex) {
            var operationsLabel = $page.find('[data-crmui-action-column]').text() || 'Operations';
            var cells = columns.map(function(column) {
                return '<td data-label="' + escapeHtml(column.label) + '">' + cellHtml($page, row, column, rowIndex) + '</td>';
            });

            if (hasRowActions) {
                cells.push('<td data-label="' + escapeHtml(operationsLabel) + '">' + rowActionsHtml($page, row) + '</td>');
            }

            return '<tr>' + cells.join('') + '</tr>';
        }).join(''));
        $body.find('tr').each(function(rowIndex) {
            $(this).data('crmuiRow', rows[rowIndex] || {});
        });
    }

    function renderTableFooter($page, rows) {
        var $footer = $page.find('[data-crmui-table-footer]');
        var columns = [];
        var hasRowActions = $page.find('template[data-crmui-row-actions]').length > 0;

        if (!$footer.length) {
            return;
        }

        $page.find('thead th[data-key]').each(function() {
            columns.push({
                key: $(this).data('key'),
                label: $(this).text()
            });
        });

        if (!rows.length) {
            $footer.empty().attr('hidden', true);
            return;
        }

        $footer.html(rows.map(function(row) {
            var cells = columns.map(function(column) {
                return '<td data-label="' + escapeHtml(column.label) + '">' + escapeHtml(cellValue(row, column.key)) + '</td>';
            });

            if (hasRowActions) {
                cells.push('<td></td>');
            }

            return '<tr>' + cells.join('') + '</tr>';
        }).join('')).removeAttr('hidden');
    }

    function newsTitle(row) {
        return firstPresentValue([
            rowValue(row, 'news_title'),
            rowValue(row, 'title'),
            rowValue(row, 'name'),
            rowValue(row, 'subject')
        ]);
    }

    function newsDate(row) {
        return firstPresentValue([
            rowValue(row, 'rec_crt_date'),
            rowValue(row, 'created_at'),
            rowValue(row, 'publish_time'),
            rowValue(row, 'rec_upd_date'),
            rowValue(row, 'updated_at')
        ]);
    }

    function newsContent(row) {
        return firstPresentValue([
            rowValue(row, 'news_content'),
            rowValue(row, 'content'),
            rowValue(row, 'description'),
            rowValue(row, 'summary')
        ]);
    }

    function newsExcerpt(row) {
        var text = stripHtml(newsContent(row));

        return text.length > 120 ? text.slice(0, 120) + '...' : text;
    }

    function renderNewsTimeline($page, rows) {
        var $timeline = $page.find('[data-crmui-news-timeline]');
        var emptyText = $page.data('empty-text') || 'No records';

        if (!$timeline.length) {
            return;
        }

        if (!rows.length) {
            $timeline.html('<div class="crmui-news-empty">' + escapeHtml(emptyText) + '</div>');
            return;
        }

        $timeline.html(rows.map(function(row, index) {
            var title = newsTitle(row) || '--';
            var date = newsDate(row) || '--';
            var author = firstPresentValue([rowValue(row, 'author_name'), rowValue(row, 'author')]);
            var excerpt = newsExcerpt(row) || '--';

            return '<article class="crmui-news-item">' +
                '<div class="crmui-news-card">' +
                    '<span class="crmui-news-date">' + escapeHtml(date) + '</span>' +
                    '<strong class="crmui-news-title">' + escapeHtml(title) + '</strong>' +
                    '<span class="crmui-news-meta">' + escapeHtml(author || translate('crmui.common.records')) + '</span>' +
                    '<span class="crmui-news-excerpt">' + escapeHtml(excerpt) + '</span>' +
                '</div>' +
            '</article>';
        }).join(''));
    }

    function chartGroups($page) {
        var groups = jsonFromAttr($page, 'data-chart-groups', []);

        return $.isArray(groups) ? groups : [];
    }

    function summaryFromResponse(response, listKey) {
        var data = dataFromResponse(response);
        var listData = dataForList(response, listKey);

        if (data && typeof data === 'object') {
            return $.extend({}, data, data.stats || {}, data.totalRow || {}, data.summary || {});
        }
        if (listData && typeof listData === 'object') {
            return $.extend({}, listData, listData.stats || {}, listData.totalRow || {}, listData.summary || {});
        }

        return {};
    }

    function numeric(value) {
        var number;

        if (value === undefined || value === null || value === '') {
            return 0;
        }
        number = parseFloat(String(value).replace(/,/g, '').replace(/[^\d.-]/g, ''));

        return isFinite(number) ? number : 0;
    }

    function chartValues(summary, chart) {
        return ($.isArray(chart.fields) ? chart.fields : []).map(function(field) {
            return {
                label: translate(field.label || field.key || ''),
                value: numeric(rowValue(summary, field.key))
            };
        });
    }

    function chartOption(title, values, type) {
        var labels = values.map(function(item) {
            return item.label;
        });
        var numbers = values.map(function(item) {
            return item.value;
        });
        var seriesType = type === 'area' ? 'line' : type;
        var isPie = type === 'pie';

        return {
            color: ['#2563eb', '#16a34a', '#f59e0b', '#dc2626', '#7c3aed', '#0891b2'],
            title: {
                text: title,
                left: 12,
                top: 10,
                textStyle: {fontSize: 13, fontWeight: 700}
            },
            tooltip: {trigger: isPie ? 'item' : 'axis'},
            legend: {
                show: isPie,
                bottom: 8,
                type: 'scroll'
            },
            grid: isPie ? undefined : {
                top: 52,
                right: 18,
                bottom: 34,
                left: 46,
                containLabel: true
            },
            xAxis: isPie ? undefined : {
                type: 'category',
                data: labels,
                axisTick: {alignWithLabel: true}
            },
            yAxis: isPie ? undefined : {
                type: 'value',
                splitLine: {lineStyle: {type: 'dashed'}}
            },
            series: [{
                name: title,
                type: seriesType,
                smooth: type === 'line' || type === 'area',
                radius: isPie ? ['42%', '68%'] : undefined,
                center: isPie ? ['50%', '48%'] : undefined,
                areaStyle: type === 'area' ? {opacity: 0.18} : undefined,
                barMaxWidth: type === 'bar' ? 42 : undefined,
                data: isPie ? values.map(function(item) {
                    return {name: item.label, value: item.value};
                }) : numbers
            }]
        };
    }

    function renderChartSelectors($page) {
        chartGroups($page).forEach(function(chart) {
            var target = chart.target || '';
            var type = chart.defaultType || 'bar';

            if (!target) {
                return;
            }

            $page.find('[data-crmui-chart-type][data-crmui-chart-target="' + target + '"]').val(type);
        });
    }

    function renderCharts($page, summary) {
        var groups = chartGroups($page);

        if (!groups.length) {
            return;
        }

        if (!window.echarts || typeof window.echarts.init !== 'function') {
            $page.find('[data-crmui-chart-target]').each(function() {
                $('#' + $(this).attr('data-crmui-chart-target')).text('ECharts unavailable');
            });
            return;
        }

        groups.forEach(function(chart) {
            var target = chart.target || '';
            var element = target ? document.getElementById(target) : null;
            var $selector = $page.find('[data-crmui-chart-type][data-crmui-chart-target="' + target + '"]');
            var type = $selector.val() || chart.defaultType || 'bar';
            var values;
            var instance;

            if (!element) {
                return;
            }

            values = chartValues(summary, chart);
            instance = window.echarts.getInstanceByDom(element) || window.echarts.init(element);
            instance.setOption(chartOption(translate(chart.title || ''), values, type), true);
            $page.data('chartSummary', summary || {});
        });
    }

    function renderMetrics($page, response, rows, listKey) {
        var data = dataFromResponse(response);
        var summary = summaryFromResponse(response, listKey);

        $page.find('[data-crmui-metric]').each(function() {
            var $metric = $(this);
            var key = $metric.data('crmui-metric');
            var value = data[key];
            var summaryValue;

            if (value === undefined && data.stats) {
                value = data.stats[key];
            }
            if (value === undefined && summary) {
                summaryValue = rowValue(summary, key);
                if (summaryValue !== '') {
                    value = summaryValue;
                }
            }
            if ((value === undefined || value === '') && key === 'total') {
                value = totalFromResponse(response, rows, listKey);
            }

            $metric.find('strong').text(value === undefined || value === null || value === '' ? '--' : value);
        });

        renderCharts($page, summary);
    }

    function defaultFilters($page) {
        var filters = jsonFromAttr($page, 'data-default-filters', {});

        return filters && typeof filters === 'object' && !$.isArray(filters) ? filters : {};
    }

    function currentPageFilter($page) {
        return $.extend({}, defaultFilters($page), readForm($page.find('[data-crmui-filter]')));
    }

    function paginationState($page) {
        var state = $page.data('crmuiPageState');
        var initialSize;

        if (state) {
            return state;
        }

        initialSize = parseInt($page.attr('data-page-size'), 10);
        state = {
            page: 1,
            perPage: initialSize > 0 ? initialSize : 15,
            total: 0
        };
        $page.data('crmuiPageState', state);

        return state;
    }

    function resetPagination($page) {
        paginationState($page).page = 1;
    }

    function renderPagination($page, total) {
        var $pagination = $page.find('[data-crmui-pagination]');
        var state;
        var pageCount;

        if (!$pagination.length) {
            return false;
        }

        state = paginationState($page);
        var requestedPage = state.page;
        state.total = Math.max(0, Number(total) || 0);
        pageCount = Math.max(1, Math.ceil(state.total / state.perPage));
        state.page = Math.min(Math.max(1, state.page), pageCount);

        $pagination.find('[data-crmui-page-current]').text(state.page);
        $pagination.find('[data-crmui-page-count]').text(pageCount);
        $pagination.find('[data-crmui-page-size]').val(String(state.perPage));
        $pagination.find('[data-crmui-page-previous]').prop('disabled', state.page <= 1);
        $pagination.find('[data-crmui-page-next]').prop('disabled', state.page >= pageCount);

        return state.page !== requestedPage;
    }

    function loadPage($page) {
        var url = $page.attr('data-api-url');
        var method = $page.attr('data-api-method') || 'GET';
        var listKey = $page.attr('data-list-key') || '';
        var legacyResponse = $page.attr('data-crmui-legacy-response') === '1';
        var bigAgentSession = $page.attr('data-crmui-session') === 'big-agent';
        var filter = currentPageFilter($page);
        var pageState = paginationState($page);
        var loadGeneration = Number($page.data('loadGeneration') || 0) + 1;

        if (!url) {
            return;
        }

        if ($page.find('[data-crmui-pagination]').length) {
            filter.page = pageState.page;
            filter.limit = pageState.perPage;
            filter.per_page = pageState.perPage;
        }

        $page.data('loadGeneration', loadGeneration);

        request({
            url: url,
            method: method,
            data: filter,
            auth: bigAgentSession ? false : undefined,
            clearTokenOnUnauthorized: bigAgentSession ? false : undefined,
            unauthorizedRedirect: bigAgentSession ? '/front-crmui/big-agent/login' : undefined,
            allowLegacy: legacyResponse,
            onError: function(error) {
                if ($page.data('loadGeneration') !== loadGeneration) {
                    return;
                }

                layer.msg(messageFromResponse(error && error.responseJSON) || messageFromResponse(error) || 'Request failed', {icon: 2});
            }
        }).done(function(response) {
            if ($page.data('loadGeneration') !== loadGeneration) {
                return;
            }

            $page.data('crmuiLastResponse', response);

            var rows = rowsFromResponse(response, listKey);
            var total = totalFromResponse(response, rows, listKey);
            if (renderPagination($page, total)) {
                loadPage($page);
                return;
            }
            if ($page.attr('data-timeline') === 'news') {
                renderNewsTimeline($page, rows);
            } else {
                renderRows($page, rows);
            }
            renderTableFooter($page, footerRowsFromResponse(response, listKey));
            renderMetrics($page, response, rows, listKey);
            fillPageForms($page, response);
            $page.find('[data-crmui-total]').text(total);
            dispatchPageEvent($page, 'crm:module-page-loaded', {
                summary: dataFromResponse(response),
                meta: response
            });
        });
    }

    function dispatchPageEvent($page, eventName, detail) {
        var pageElement = $page.get(0);
        var event;

        if (!pageElement) {
            return;
        }
        if (typeof window.CustomEvent === 'function') {
            pageElement.dispatchEvent(new window.CustomEvent(eventName, {detail: detail || {}}));
            return;
        }

        event = document.createEvent('CustomEvent');
        event.initCustomEvent(eventName, false, false, detail || {});
        pageElement.dispatchEvent(event);
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

    function exportPage($page) {
        var url = $page.attr('data-api-url');
        var method = String($page.attr('data-api-method') || 'GET').toUpperCase();
        var data = $.extend({}, currentPageFilter($page), {export: 1});
        var query;

        if (!url) {
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

    function paymentUrlFromPayload(payload) {
        if (!payload || typeof payload !== 'object') {
            return '';
        }

        if (payload.payment_url) {
            return payload.payment_url;
        }
        if (payload.paymentUrl) {
            return payload.paymentUrl;
        }
        if (payload.deposit) {
            return payload.deposit.payment_url || payload.deposit.paymentUrl || '';
        }

        return '';
    }

    function shouldOpenBlank(payload) {
        return payload.open_blank !== false && payload.open_blank !== 'false' && payload.open_blank !== 0 && payload.open_blank !== '0';
    }

    function openPaymentUrl(payload) {
        var paymentUrl = paymentUrlFromPayload(payload);

        if (!paymentUrl) {
            return false;
        }

        if (shouldOpenBlank(payload)) {
            window.open(paymentUrl, '_blank', 'noopener');
            return true;
        }

        window.location.href = paymentUrl;
        return true;
    }

    function handleFormSuccess($form, response) {
        var payload = dataFromResponse(response);
        var openedPayment = false;
        var successUrl = $form.data('success-url');

        layer.msg(messageFromResponse(response) || 'OK', {icon: 1});

        if ($form.closest('.crmui-page').attr('data-crmui-page') === 'front.deposit') {
            openedPayment = openPaymentUrl(payload);
        }

        if (openedPayment && !shouldOpenBlank(payload)) {
            return;
        }

        if (successUrl) {
            window.location.href = successUrl;
            return;
        }

        if (/\/commissions\/transfers(?:\?|$)/i.test(String($form.data('action-url') || ''))) {
            $form[0].reset();
        }

        loadPage($form.closest('.crmui-page'));
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
        var key;

        if (!$input.length) {
            $input = $('<input type="hidden" name="idempotency_key">').appendTo($form);
        }
        key = String($input.val() || '').trim() || newCommissionTransferKey();
        if (!/^[A-Za-z0-9._:-]{1,100}$/.test(key)) {
            return '';
        }
        $input.val(key);

        return key;
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

            $option.data('item', item);
            $select.append($option);
        });
    }

    function optionListFromResponse(response, key) {
        var data = dataFromResponse(response);

        if ($.isArray(data)) {
            return data;
        }
        if ($.isArray(data.list)) {
            return data.list;
        }
        if (key && $.isArray(data[key])) {
            return data[key];
        }
        if ($.isArray(data.options)) {
            return data.options;
        }

        return [];
    }

    function loadDynamicFilterOptions($page) {
        var requested = {};
        var bigAgentSession = $page.attr('data-crmui-session') === 'big-agent';

        $page.find('[data-dynamic-options]').each(function() {
            var $select = $(this);
            var key = String($select.attr('data-dynamic-options') || '').trim();
            var url = dynamicOptionUrls[key];

            if (!key || !url || requested[key]) {
                return;
            }
            requested[key] = true;

            request({
                url: url,
                method: dynamicOptionMethods[key] || 'POST',
                auth: bigAgentSession ? false : undefined,
                clearTokenOnUnauthorized: bigAgentSession ? false : undefined,
                unauthorizedRedirect: bigAgentSession ? '/front-crmui/big-agent/login' : undefined
            }).done(function(response) {
                fillSelect($page.find('[data-dynamic-options="' + key + '"]'), optionListFromResponse(response, key));
            });
        });
    }

    function renderChannelRemarks($page) {
        var $select = $page.find('select[name="channel"]');
        var $target = $page.find('[data-crmui-channel-remarks]');
        var channel = $select.find('option:selected').data('item') || {};
        var items = $.isArray(channel.remark_items) ? channel.remark_items : [];
        var html = '';

        if (!$target.length) {
            return;
        }

        if (items.length) {
            html = '<ul>' + items.map(function(item) {
                return '<li>' + escapeHtml(item) + '</li>';
            }).join('') + '</ul>';
        } else if (channel.description) {
            html = '<p>' + escapeHtml(channel.description) + '</p>';
        }

        $target.html(html).toggleClass('is-empty', html === '');
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
            var groupItems = data.available_groups || data.groups || data.group_options || [];
            var agentItems = $.isArray(data) ? data : (data.direct_agents || data.agents || data.options || []);

            fillSelect($page.find('select[name="channel"]'), channelItems);
            renderChannelRemarks($page);
            fillSelect($page.find('select[name="bank_card"]'), bankItems);
            fillSelect($page.find('select[name="new_group_id"]'), groupItems);
            fillSelect($page.find('select[name="sub_agent_id"]'), agentItems);
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

    function parseFieldConfig($button) {
        var value = $button.attr('data-field-config');
        var parsed;

        if (!value) {
            return parseFields($button.attr('data-fields'));
        }

        try {
            parsed = JSON.parse(value);
        } catch (error) {
            return parseFields($button.attr('data-fields'));
        }

        return $.isArray(parsed) ? parsed.filter(function(field) {
            return field && field.name;
        }) : [];
    }

    function pageDynamicOptions($page, key) {
        var response = $page.data('crmuiLastResponse') || {};
        var data = dataFromResponse(response);

        if (!key) {
            return [];
        }
        if ($.isArray(response[key])) {
            return response[key];
        }
        if (data && $.isArray(data[key])) {
            return data[key];
        }
        if (response.meta && $.isArray(response.meta[key])) {
            return response.meta[key];
        }

        return [];
    }

    function normalizedFieldOptions(field, $page) {
        if (field.dynamicOptions) {
            return pageDynamicOptions($page, field.dynamicOptions);
        }

        return $.isArray(field.options) ? field.options : [];
    }

    function fieldHtml(field, row, $page) {
        var value = rowValue(row, field.source || field.name);
        var options;

        if (field.type === 'readonly') {
            return '<div class="crmui-readonly-field"><span>' + escapeHtml(field.label) + '</span><strong>' + escapeHtml(value || '--') + '</strong></div>';
        }
        if (field.type === 'hidden') {
            return '<input name="' + escapeHtml(field.name) + '" type="hidden" value="' + escapeHtml(value) + '">';
        }

        if (field.type === 'textarea') {
            return '<textarea class="crmui-input crmui-textarea" name="' + escapeHtml(field.name) + '" placeholder="' + escapeHtml(field.label) + '">' + escapeHtml(value) + '</textarea>';
        }
        if (field.type === 'select') {
            options = normalizedFieldOptions(field, $page);

            return '<select class="crmui-input" name="' + escapeHtml(field.name) + '"><option value="">' + escapeHtml(field.label) + '</option>' +
                options.map(function(option) {
                    var selected = String(option.value) === String(value) ? ' selected' : '';
                    return '<option value="' + escapeHtml(option.value) + '"' + selected + '>' + escapeHtml(option.label) + '</option>';
                }).join('') + '</select>';
        }
        if (field.type === 'checkbox') {
            return '<label class="crmui-check"><input name="' + escapeHtml(field.name) + '" type="checkbox" value="1"' +
                (truthyFlag(value) ? ' checked' : '') + '><span>' + escapeHtml(field.label) + '</span></label>';
        }

        return '<input class="crmui-input" name="' + escapeHtml(field.name) + '" type="' + escapeHtml(field.type || 'text') + '" placeholder="' + escapeHtml(field.label) + '" value="' + escapeHtml(value) + '">';
    }

    function openActionModal($button, row, fields) {
        var $page = $button.closest('.crmui-page');
        var $modal = $page.find('[data-crmui-action-modal]');
        var isLocal = $button.data('crmui-local-modal') === 1 || $button.attr('data-crmui-local-modal') === '1';
        var actionInstance = Number($modal.data('actionInstance') || 0) + 1;
        var $submitButton = $modal.find('[data-crmui-action-form] button[type="submit"]');

        $modal.data('actionInstance', actionInstance);
        $modal.removeData('requestPending');
        $modal.data('actionButton', $button);
        $modal.data('row', row);
        $modal.find('[data-crmui-modal-title]').text($button.text());
        $modal.find('[data-crmui-record-preview]').text(JSON.stringify(row, null, 2)).toggle(isLocal || !fields.length);
        $modal.find('[data-crmui-modal-fields]').html(fields.map(function(field) {
            return fieldHtml(field, row, $page);
        }).join(''));
        $submitButton.prop('disabled', false).removeAttr('aria-busy').toggle(!isLocal);
        $modal.removeAttr('hidden');
    }

    function closeActionModal($modal, actionInstance) {
        if (actionInstance !== undefined && $modal.data('actionInstance') !== actionInstance) {
            return;
        }

        $modal.attr('hidden', true);
        $modal.removeData('actionButton').removeData('row');
    }

    function staticPayload($button) {
        var raw = $button.attr('data-static-payload') || '{}';

        try {
            return JSON.parse(raw) || {};
        } catch (error) {
            return {};
        }
    }

    function selectedAgentLevelPayload($button, row) {
        var $option = $button.closest('tr').find('[data-crmui-agent-level-select] option:selected');
        var levels;
        var selected;

        if ($option.length) {
            return {
                agent_gId: $option.data('choice-gid') || $option.val(),
                comm_prop: $option.data('comm-prop') || rowValue(row, 'comm_rate') || 0,
                def_gid: $option.data('def-gid') || $option.val(),
                choice_gid: $option.data('choice-gid') || $option.val(),
                extra_val: $option.data('extra-val') || 0
            };
        }

        levels = listFromValue(rowValue(row, 'range_list'));
        selected = levels.filter(function(item) {
            return truthyFlag(item && item.selected);
        })[0] || levels[0] || {};

        return {
            agent_gId: selected.choice_gid || selected.level_id || rowValue(row, 'level_id'),
            comm_prop: selected.prop || rowValue(row, 'comm_rate') || 0,
            def_gid: selected.def_gid || selected.level_id || rowValue(row, 'level_id'),
            choice_gid: selected.choice_gid || selected.level_id || rowValue(row, 'level_id'),
            extra_val: selected.extra_val || 0
        };
    }

    function submitRowAction($button, row, extraData, $actionModal) {
        var recordKey = $button.data('record-key') || $button.data('payload-key') || 'id';
        var payloadName = $button.data('payload-name') || $button.data('payload-key') || recordKey;
        var id = recordIdentifier(row, recordKey);
        var url = String($button.data('action-url') || '').replace('__ID__', encodeURIComponent(id));
        var payload = $.extend({}, staticPayload($button), extraData || {});
        var $submitButton = null;
        var pendingRequest;
        var actionInstance;
        var currentModal;
        var actionHeaders;
        var key;

        if ($button.data('crmui-row-action') === 'confirm_level') {
            payload = $.extend(payload, selectedAgentLevelPayload($button, row));
            if (!payload.agent_gId) {
                layer.msg('Invalid agent level', {icon: 2});
                return;
            }
        }

        if (payloadName && id && (payload[payloadName] === undefined || payload[payloadName] === '')) {
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

        if (/\/commissions\/transfers(?:\?|$)/i.test(url)) {
            key = $actionModal && $actionModal.length ? ensureCommissionTransferKey($actionModal.find('[data-crmui-action-form]')) : '';
            key = key || payload.idempotency_key || newCommissionTransferKey();
            if (!/^[A-Za-z0-9._:-]{1,100}$/.test(key)) {
                layer.msg('Validation failed', {icon: 2});
                if ($actionModal && $actionModal.length) {
                    $actionModal.removeData('requestPending');
                    $submitButton.prop('disabled', false).removeAttr('aria-busy');
                }
                return;
            }
            payload.idempotency_key = key;
            actionHeaders = {'Idempotency-Key': key};
        }

        pendingRequest = request({
            url: url,
            method: $button.data('action-method') || 'POST',
            data: payload,
            headers: actionHeaders,
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

    function bindViewTabs() {
        $(document).on('click', '[data-crmui-view]', function() {
            var $tab = $(this);
            var $page = $tab.closest('.crmui-page');

            $tab.addClass('is-active').siblings('[data-crmui-view]').removeClass('is-active');
            $page.attr('data-api-url', $tab.data('api-url'));
            $page.attr('data-api-method', $tab.data('api-method') || 'GET');
            resetPagination($page);
            loadPage($page);
        });
    }

    function openCrmUiImagePreview(src) {
        if (!src) {
            return;
        }

        layer.open({
            type: 1,
            title: false,
            shadeClose: true,
            area: ['min(860px, 92vw)', 'min(720px, 88vh)'],
            content: '<div class="crmui-image-preview"><img src="' + escapeHtml(src) + '" alt=""></div>'
        });
    }

    // 上传交互统一走共享组件的文案与体积格式化，避免各家族各自硬编码英文提示。
    function bindUploads() {
        $(document).on('change', '[data-crmui-upload] input[type="file"]', function() {
            var file = this.files && this.files[0];
            var $upload = $(this).closest('[data-crmui-upload]');
            var $preview = $upload.find('[data-crmui-upload-preview]');
            var emptyText = translateUploadText('front.no_file_selected', 'No file selected');
            var sizeText = file && window.CrmUpload ? window.CrmUpload.formatSize(file.size || 0) : '';

            $upload.find('[data-crmui-upload-name]').text(file ? file.name : emptyText);
            $upload.find('[data-crmui-upload-size]').text(file ? sizeText : '');
            $upload.toggleClass('has-file', !!file);
            if (window.CrmFieldErrors) {
                window.CrmFieldErrors.clearUpload(document, String($upload.attr('data-crmui-upload') || ''));
            }

            if (!$preview.length) {
                return;
            }

            $preview.attr('hidden', true).removeAttr('src');

            if (!file || !/^image\//.test(file.type || '') || !window.FileReader) {
                return;
            }

            var reader = new FileReader();
            reader.onload = function(event) {
                var result = event.target && event.target.result ? String(event.target.result) : '';
                if (result) {
                    $preview.attr('src', result).removeAttr('hidden');
                }
            };
            reader.readAsDataURL(file);
        });

        $(document).on('click', '[data-crmui-upload-preview]', function(event) {
            event.preventDefault();
            event.stopPropagation();
            openCrmUiImagePreview($(this).attr('src') || '');
        });

        $(document).on('keydown', '[data-crmui-upload-preview]', function(event) {
            if (event.key !== 'Enter' && event.key !== ' ') {
                return;
            }

            event.preventDefault();
            event.stopPropagation();
            openCrmUiImagePreview($(this).attr('src') || '');
        });
    }

    /**
     * 读取语言包文案，缺键时回退到调用方给出的兜底串。
     *
     * @param {string} key 语言键。
     * @param {string} fallback 兜底文案。
     * @return {string} 展示文案。
     */
    function translateUploadText(key, fallback) {
        var value = window.CrmLang && window.CrmLang.t ? window.CrmLang.t(key) : '';

        return value && value !== key ? value : fallback;
    }

    function bindCaptcha() {
        $('[data-crmui-captcha]').each(function() {
            refreshCaptcha($(this));
        });

        $(document).on('click', '[data-crmui-refresh-captcha]', function() {
            refreshCaptcha($(this).closest('form').find('[data-crmui-captcha]'));
        });
    }

    function bindRegisterContext() {
        function updateInviterRequirement($form) {
            var accountType = $form.find('[name="account_type"]:checked').val() || '2';
            $form.find('[data-crmui-inviter]').prop('required', accountType === '1');
        }

        $('[data-crmui-register-form]').each(function() {
            updateInviterRequirement($(this));
        });

        $(document).on('change', '[data-crmui-register-form] [name="account_type"]', function() {
            updateInviterRequirement($(this).closest('form'));
        });
    }

    function bindPageActions() {
        $(document).on('click', '[data-crmui-action]', function() {
            var action = $(this).data('crmui-action');
            var $page = $(this).closest('.crmui-page');

            if (action === 'refresh') {
                loadPage($page);
                return;
            }
            if (action === 'create') {
                focusCreateForm($page);
                return;
            }
            if (action === 'export') {
                exportPage($page);
            }
        });
    }

    function bindRowActions() {
        $(document).on('click', '[data-crmui-row-action]', function() {
            var $button = $(this);
            var row = {};
            var fields = parseFieldConfig($button);
            var confirmText = $button.data('confirm');

            try {
                row = JSON.parse(decodeURIComponent($button.attr('data-row') || '%7B%7D'));
            } catch (error) {
                row = {};
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

        $(document).on('click', '[data-crmui-cell-action="showOrderInfo"]', function() {
            var $button = $(this);
            var row = $button.closest('tr').data('crmuiRow') || {};

            openActionModal($button, row, []);
        });

        $(document).on('change', '[data-crmui-chart-type]', function() {
            var $page = $(this).closest('.crmui-page');

            renderCharts($page, $page.data('chartSummary') || {});
        });

        $(document).on('click', '[data-crmui-modal-close]', function() {
            closeActionModal($(this).closest('[data-crmui-action-modal]'));
        });

        $(document).on('submit', '[data-crmui-action-form]', function(event) {
            var $form = $(this);
            var $modal = $form.closest('[data-crmui-action-modal]');
            var $button = $modal.data('actionButton');
            var row = $modal.data('row') || {};

            event.preventDefault();
            submitRowAction($button, row, readForm($form), $modal);
        });
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

        $(document).on('click.crmuiSidebar', '[data-crmui-ui-target]', function() {
            switchUiFamily($(this).data('crmui-ui-target') || $(this).data('ui-target') || 'crmui');
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
            var legacySessionAuth = $form.attr('data-crmui-auth-legacy-session') === '1';
            event.preventDefault();

            if ($form.is('[data-crmui-register-form]')
                && $form.find('[name="password"]').val() !== $form.find('[name="password_confirmation"]').val()) {
                layer.msg($form.attr('data-password-mismatch'), {icon: 2});
                return;
            }

            request({
                url: $form.data('action-url'),
                method: 'POST',
                data: readForm($form),
                auth: false,
                allowLegacy: legacySessionAuth
            }).done(function(response) {
                var data = dataFromResponse(response);
                var token = data.token || data.access_token || data.jwt_token || data.jwtToken;

                if (!legacySessionAuth) {
                    setToken(token);
                }
                layer.msg(messageFromResponse(response) || 'OK', {icon: 1});
                window.location.href = $form.data('success-url') || '/front-crmui/dashboard';
            });
        });

        $(document).on('click', '[data-crmui-secondary-action="send-email-code"]', function() {
            var $button = $(this);
            var $form = $button.closest('form');
            var action = $button.data('crmui-secondary-action');
            request({
                url: $button.data('action-url'),
                method: 'POST',
                data: secondaryPayload(action, $form),
                auth: false
            }).done(function(response) {
                layer.msg(messageFromResponse(response) || 'OK', {icon: 1});
            });
        });

        $(document).on('click', '[data-crmui-cancel-code]', function() {
            var $button = $(this);
            var $form = $button.closest('form');
            var verifyUrl = $form.data('verification-url');
            var codeUrl = $form.data('verification-code-url');
            var payload = readForm($form);

            if (!verifyUrl || !codeUrl) {
                return;
            }

            $button.prop('disabled', true);
            request({
                url: verifyUrl,
                method: 'POST',
                data: payload,
                allowLegacy: true
            }).done(function(response) {
                if (!successOrLegacy(response)) {
                    layer.msg(messageFromResponse(response) || 'Validation failed', {icon: 2});
                    $button.prop('disabled', false);
                    return;
                }

                request({
                    url: codeUrl,
                    method: 'POST',
                    data: payload,
                    allowLegacy: true
                }).done(function(codeResponse) {
                    if (!successOrLegacy(codeResponse)) {
                        layer.msg(messageFromResponse(codeResponse) || 'Request failed', {icon: 2});
                        $button.prop('disabled', false);
                        return;
                    }

                    layer.msg(messageFromResponse(codeResponse) || 'OK', {icon: 1});
                    $button.prop('disabled', false);
                }).fail(function() {
                    $button.prop('disabled', false);
                });
            }).fail(function() {
                $button.prop('disabled', false);
            });
        });

        $(document).on('submit', '[data-crmui-form]', function(event) {
            var $form = $(this);
            var $page = $form.closest('.crmui-page');
            var options = {};
            var key;
            var bigAgentSession = $page.attr('data-crmui-session') === 'big-agent';
            event.preventDefault();

            if (/\/commissions\/transfers(?:\?|$)/i.test(String($form.data('action-url') || ''))) {
                key = ensureCommissionTransferKey($form);
                if (!key) {
                    layer.msg('Validation failed', {icon: 2});
                    return;
                }
                options.headers = {'Idempotency-Key': key};
            }

            request({
                url: $form.data('action-url'),
                method: $form.data('action-method') || 'POST',
                data: formPayload($form),
                headers: options.headers,
                auth: bigAgentSession ? false : undefined,
                clearTokenOnUnauthorized: bigAgentSession ? false : undefined,
                unauthorizedRedirect: bigAgentSession ? '/front-crmui/big-agent/login' : undefined,
                allowLegacy: $page.attr('data-crmui-legacy-response') === '1'
            }).done(function(response) {
                handleFormSuccess($form, response);
            });
        });

        $(document).on('input', '[name="amount"]', function() {
            var $form = $(this).closest('form');
            var value = parseFloat($(this).val() || '0');
            $form.find('[data-crmui-money-preview]').text(value > 0 ? value.toFixed(2) : '--');
        });

        $(document).on('change', 'select[name="channel"]', function() {
            renderChannelRemarks($(this).closest('.crmui-page'));
        });

        $(document).on('submit', '[data-crmui-filter]', function(event) {
            var $page = $(this).closest('.crmui-page');
            event.preventDefault();
            resetPagination($page);
            loadPage($page);
        });

        $(document).on('click', '[data-crmui-reset]', function() {
            var $page = $(this).closest('.crmui-page');
            $page.find('[data-crmui-filter]')[0].reset();
            resetPagination($page);
            loadPage($page);
        });

        $(document).on('click', '[data-crmui-page-previous], [data-crmui-page-next]', function() {
            var $button = $(this);
            var $page = $button.closest('.crmui-page');
            var state = paginationState($page);
            var pageCount = Math.max(1, Math.ceil(state.total / state.perPage));
            var step = $button.is('[data-crmui-page-previous]') ? -1 : 1;
            var target = Math.min(pageCount, Math.max(1, state.page + step));

            if (target === state.page) {
                return;
            }

            state.page = target;
            loadPage($page);
        });

        $(document).on('change', '[data-crmui-page-size]', function() {
            var $page = $(this).closest('.crmui-page');
            var state = paginationState($page);
            var perPage = parseInt($(this).val(), 10);

            if (perPage <= 0 || perPage === state.perPage) {
                return;
            }

            state.perPage = perPage;
            state.page = 1;
            loadPage($page);
        });

    }

    function init() {
        bindShell();
        bindForms();
        bindViewTabs();
        bindUploads();
        bindCaptcha();
        bindRegisterContext();
        bindPageActions();
        bindRowActions();

        $('.crmui-page').each(function() {
            var $page = $(this);

            $page.on('crm:module-page-reload', function() {
                loadPage($page);
            });
            renderChartSelectors($page);
            loadOptions($page);
            loadDynamicFilterOptions($page);
            loadPage($page);
        });
    }

    exports('crmuiFront', {init: init, request: request, loadPage: loadPage});
});

layui.use(['crmuiFront'], function() {
    layui.crmuiFront.init();
});
