/**
 * Shared table and list utilities for front/admin Layui pages.
 *
 * The old CRM kept repeated list behavior in common.js/tableCommon.js.  This
 * file is the Laravel/Layui replacement: every page can reuse one response
 * normalizer, one auth-header builder, one value formatter, and one Layui table
 * parser instead of re-implementing the same rules in each Blade page script.
 */
var CrmTable = (function () {
    'use strict';

    /**
     * Return the active i18n text and keep the key as a diagnostic fallback.
     */
    function t(key) {
        if (typeof CrmLang !== 'undefined' && CrmLang.t) {
            return CrmLang.t(key);
        }

        return key;
    }

    /**
     * Convert an API value into safe HTML text before injecting it into a table.
     */
    function escapeHtml(value) {
        return String(value === null || typeof value === 'undefined' ? '' : value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    /**
     * Resolve nested object values such as "login.email" from API rows.
     */
    function getValue(row, path) {
        var parts;
        var current;
        var i;

        if (!row || !path) {
            return '';
        }

        parts = String(path).split('.');
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
     * ApiResponse casts root arrays to objects.  Numeric object keys are turned
     * back into arrays so Layui and the generic front renderer receive lists.
     */
    function toArray(value) {
        var keys;
        var result = [];
        var i;

        if (!value || $.isArray(value) || typeof value !== 'object') {
            return value;
        }

        keys = Object.keys(value);
        if (!keys.length) {
            return [];
        }

        for (i = 0; i < keys.length; i++) {
            if (String(parseInt(keys[i], 10)) !== keys[i]) {
                return value;
            }
            result.push(value[keys[i]]);
        }

        return result;
    }

    /**
     * Format objects consistently.  Relation objects are represented by the
     * first common display field, while unknown objects are JSON encoded.
     */
    function formatValue(value) {
        if (value === null || typeof value === 'undefined' || value === '') {
            return '-';
        }

        if (typeof value === 'object') {
            if (value.name) {
                return value.name;
            }
            if (value.user_name) {
                return value.user_name;
            }
            if (value.title) {
                return value.title;
            }
            return JSON.stringify(value);
        }

        return value;
    }

    /**
     * Match the project's business success codes rather than HTTP status only.
     */
    function isSuccess(res) {
        return res && res.code >= 1000 && res.code < 4000;
    }

    /**
     * Build the authenticated request headers used by Layui table.render.
     */
    function authHeaders(guard) {
        var token = typeof CrmAjax !== 'undefined' ? CrmAjax.getToken(guard || 'front') : '';
        var headers = {
            Accept: 'application/json'
        };

        if (typeof CrmLang !== 'undefined' && CrmLang.getLocale) {
            headers['X-Locale'] = CrmLang.getLocale();
        }
        if (token) {
            headers.Authorization = 'Bearer ' + token;
        }

        return headers;
    }

    /**
     * Normalize Laravel paginator, custom {list,total}, plain array, and nested
     * list responses into one shape for both static HTML tables and Layui tables.
     */
    function normalizePayload(data, listPath) {
        var source = data || {};
        var listSource = listPath ? getValue(source, listPath) : source;
        var normalized = {
            summary: source,
            rows: [],
            pager: null,
            total: 0
        };

        listSource = toArray(listSource);
        if ($.isArray(listSource)) {
            normalized.rows = listSource;
            normalized.total = listSource.length;
            return normalized;
        }

        if (listSource && typeof listSource === 'object') {
            if (listSource.list && typeof listSource.list === 'object' && $.isArray(toArray(listSource.list.data))) {
                normalized.rows = toArray(listSource.list.data);
                normalized.total = parseInt(listSource.list.total || normalized.rows.length, 10);
                normalized.pager = listSource.list;
                if (listSource.totalRow) {
                    normalized.serverTotalRow = listSource.totalRow;
                } else if (source.totalRow) {
                    normalized.serverTotalRow = source.totalRow;
                }
                return normalized;
            }

            if ($.isArray(toArray(listSource.list))) {
                normalized.rows = toArray(listSource.list);
                normalized.total = parseInt(listSource.total || normalized.rows.length, 10);
                normalized.pager = listSource;
                if (source.totalRow) {
                    normalized.serverTotalRow = source.totalRow;
                }
                return normalized;
            }

            if ($.isArray(toArray(listSource.data))) {
                normalized.rows = toArray(listSource.data);
                normalized.total = parseInt(listSource.total || normalized.rows.length, 10);
                normalized.pager = listSource;
                if (source.totalRow) {
                    normalized.serverTotalRow = source.totalRow;
                }
                return normalized;
            }
        }

        if (source && typeof source === 'object' && $.isArray(toArray(source.data))) {
            normalized.rows = toArray(source.data);
            normalized.total = parseInt(source.total || normalized.rows.length, 10);
            normalized.pager = source;
        }

        if (source && source.totalRow) {
            normalized.serverTotalRow = source.totalRow;
        } else if (source && source.summary && source.summary.totalRow) {
            normalized.serverTotalRow = source.summary.totalRow;
        }

        return normalized;
    }

    /**
     * Layui table.parseData adapter.  The backend remains unchanged while every
     * table receives the code/msg/count/data fields Layui expects.
     */
    function layuiParseData(listPath) {
        return function (res) {
            var payload = normalizePayload(res && res.data ? res.data : {}, listPath || '');

            return {
                code: isSuccess(res) ? 0 : ((res && res.code) || 5000),
                msg: (res && res.message) || t('common.error'),
                count: payload.total,
                data: payload.rows,
                totalRow: payload.serverTotalRow || null,
                summary: payload.summary || {}
            };
        };
    }

    function normalizeColumn(column) {
        column = column || {};
        return {
            key: column.key || column.field || '',
            label: column.label || column.title || column.key || column.field || '',
            format: column.format || '',
            total: column.total,
            type: column.type || ''
        };
    }

    function parseNumber(value) {
        var number;

        if (value === null || typeof value === 'undefined' || value === '') {
            return null;
        }

        number = Number(String(value).replace(/,/g, '').replace(/[^\d.-]/g, ''));
        return isNaN(number) ? null : number;
    }

    function isSummableColumn(column) {
        var normalized = normalizeColumn(column);
        var key = normalized.key.toLowerCase();

        if (!normalized.key || normalized.type === 'checkbox' || normalized.type === 'radio') {
            return false;
        }
        if (normalized.total === false) {
            return false;
        }
        if (normalized.total === true || normalized.format === 'money' || normalized.format === 'lots') {
            return true;
        }

        if (/(phone|mobile|tel|recipient_phone|receiver_phone|userphone|contact_phone|bank_no|card_no|account_no)/.test(key)) {
            return false;
        }

        return /(amount|balance|equity|credit|margin|profit|commission|rebate|deposit|withdraw|fee|volume|lots|count|total)/.test(key);
    }

    function summarizeRows(rows, columns) {
        var normalizedRows = toArray(rows) || [];
        var normalizedColumns = columns || [];
        var summary = {
            count: $.isArray(normalizedRows) ? normalizedRows.length : 0,
            totals: []
        };
        var i;
        var j;
        var column;
        var value;
        var total;
        var numericCount;

        if (!$.isArray(normalizedRows)) {
            normalizedRows = [];
        }

        for (i = 0; i < normalizedColumns.length; i++) {
            column = normalizeColumn(normalizedColumns[i]);
            if (!isSummableColumn(column)) {
                continue;
            }

            total = 0;
            numericCount = 0;
            for (j = 0; j < normalizedRows.length; j++) {
                value = parseNumber(getValue(normalizedRows[j], column.key));
                if (value === null) {
                    continue;
                }
                if (column.format === 'lots' && value > 50) {
                    value = value / 100;
                }
                total += value;
                numericCount++;
            }

            if (numericCount) {
                summary.totals.push({
                    key: column.key,
                    label: column.label,
                    format: column.format,
                    value: total
                });
            }
        }

        return summary;
    }

    function formatSummaryValue(item) {
        var value = Number(item.value || 0);

        if (item.format === 'lots' || item.format === 'money') {
            return value.toFixed(2);
        }
        if (Math.abs(value % 1) > 0) {
            return value.toFixed(2);
        }

        return String(value);
    }

    function renderSummary(target, rows, columns, serverTotalRow) {
        var $target = $(target);
        var summary;
        var html = '';
        var i;
        var item;
        var column;
        var serverValue;

        if (!$target.length) {
            return;
        }

        $target.each(function () {
            var $summary = $(this);
            var $next = $summary.next();
            if ($next.hasClass('layui-table-view') || $next.is('table') || $next.hasClass('module-table-wrap') || $next.hasClass('flow-table-wrap') || $next.hasClass('withdraw-table-wrap')) {
                return;
            }
            $summary.insertBefore($summary.siblings('.layui-table-view, table, .module-table-wrap, .flow-table-wrap, .withdraw-table-wrap').first());
        });
        summary = summarizeRows(rows, columns);
        html += '<div class="crm-table-summary-item summary-color-0">';
        html += '<span>' + escapeHtml(t('common.total')) + '</span>';
        html += '<strong>' + escapeHtml(summary.count) + '</strong>';
        html += '</div>';

        if (serverTotalRow && typeof serverTotalRow === 'object') {
            for (i = 0; i < (columns || []).length; i++) {
                column = normalizeColumn(columns[i]);
                if (!isSummableColumn(column)) {
                    continue;
                }
                serverValue = getValue(serverTotalRow, column.key);
                if (serverValue === null || typeof serverValue === 'undefined' || serverValue === '') {
                    continue;
                }
                html += '<div class="crm-table-summary-item summary-color-' + ((i + 1) % 8) + '">';
                html += '<span>' + escapeHtml(t(column.label)) + '</span>';
                html += '<strong>' + escapeHtml(formatSummaryValue({
                    value: serverValue,
                    format: column.format
                })) + '</strong>';
                html += '</div>';
            }
        } else {
            for (i = 0; i < summary.totals.length; i++) {
                item = summary.totals[i];
                html += '<div class="crm-table-summary-item summary-color-' + ((i + 1) % 8) + '">';
                html += '<span>' + escapeHtml(t(item.label)) + '</span>';
                html += '<strong>' + escapeHtml(formatSummaryValue(item)) + '</strong>';
                html += '</div>';
            }
        }

        $target.html(html);
    }

    /**
     * Merge a page table config with the CRM default auth/response behavior.
     */
    function layuiConfig(guard, customConfig) {
        var summaryElem = customConfig && customConfig.summaryElem;
        var customDone = customConfig && customConfig.done;
        var tableColumns = customConfig && customConfig.cols && customConfig.cols[0] ? customConfig.cols[0] : [];
        var merged;

        merged = $.extend(true, {
            method: 'POST',
            headers: authHeaders(guard),
            page: true,
            request: {
                pageName: 'page',
                limitName: 'per_page'
            },
            parseData: layuiParseData(),
            done: function () {
                if (typeof CrmLang !== 'undefined') {
                    CrmLang.switchUI();
                }
            },
            error: function () {
                if (typeof layui !== 'undefined' && layui.layer) {
                    layui.layer.msg(t('common.error'), {icon: 2});
                }
            }
        }, customConfig || {});

        merged.done = function (res, curr, count) {
            if (summaryElem) {
                renderSummary(summaryElem, (res && res.data) || [], tableColumns, res && res.totalRow ? res.totalRow : null);
            }
            if (typeof CrmLang !== 'undefined') {
                CrmLang.switchUI();
            }
            if (typeof customDone === 'function') {
                customDone.call(this, res, curr, count);
            }
        };

        return merged;
    }

    /**
     * Old common.js exposed "checkField" for batch table operations.  The new
     * helper keeps that behavior but avoids page-level duplication.
     */
    function selectedField(tableId, field) {
        var table = typeof layui !== 'undefined' ? layui.table : null;
        var checked;
        var values = [];

        if (!table || !tableId || !field) {
            return '';
        }

        checked = table.checkStatus(tableId).data || [];
        $.each(checked, function (_, row) {
            if (typeof row[field] !== 'undefined') {
                values.push(row[field]);
            }
        });

        return values.join(',');
    }

    /**
     * Standard iframe modal used by admin/front detail pages.  It mirrors the
     * old modalBoxByPage behavior while keeping Layui-specific code centralized.
     */
    function openIframe(title, url, area) {
        var layer = typeof layui !== 'undefined' ? layui.layer : null;
        var index;

        if (!layer) {
            return null;
        }

        index = layer.open({
            type: 2,
            title: title || '',
            shade: 0.3,
            move: false,
            area: area || ['900px', '650px'],
            content: url
        });

        return index;
    }

    return {
        t: t,
        escapeHtml: escapeHtml,
        getValue: getValue,
        toArray: toArray,
        formatValue: formatValue,
        isSuccess: isSuccess,
        authHeaders: authHeaders,
        normalizePayload: normalizePayload,
        layuiParseData: layuiParseData,
        summarizeRows: summarizeRows,
        renderSummary: renderSummary,
        layuiConfig: layuiConfig,
        selectedField: selectedField,
        openIframe: openIframe
    };
})();
