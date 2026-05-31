/**
 * 前台/后台 Layui 页面共用的表格与列表工具。
 *
 * 旧 CRM 里很多列表逻辑散落在 common.js/tableCommon.js 中。这里把
 * 它们收拢成 Laravel/Layui 版本，所有页面都可以复用统一的响应整理、
 * 认证头构造、值格式化和 Layui 表格解析逻辑。
 */
var CrmTable = (function () {
    'use strict';

    /**
     * 取当前语言文案，找不到时保留 key 作为排查兜底。
     */
    function t(key) {
        if (typeof CrmLang !== 'undefined' && CrmLang.t) {
            return CrmLang.t(key);
        }

        return key;
    }

    /**
     * 在写入表格前先把接口值转成安全的 HTML 文本。
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
     * 从接口行数据里解析类似 "login.email" 这样的嵌套字段。
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
     * ApiResponse 会把根数组转成对象，这里把连续数字 key 再转回数组，
     * 让 Layui 和通用前台渲染器都能继续按列表处理。
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
     * 统一对象格式化规则。关联对象优先取常见展示字段，不认识的对象
     * 则直接转成 JSON，避免表格里出现不可读的 [object Object]。
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
     * 按项目自己的业务成功码判断，而不是只看 HTTP 状态码。
     */
    function isSuccess(res) {
        return res && res.code >= 1000 && res.code < 4000;
    }

    /**
     * 组装 Layui table.render 会用到的鉴权请求头。
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
     * 把 Laravel 分页、{list,total}、纯数组和嵌套 list 返回统一成一个
     * 结构，方便静态 HTML 表格和 Layui 表格共用。
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
     * Layui table.parseData 适配器。
     * 后端保持不变，但每个表格都能拿到 Layui 需要的 code/msg/count/data 字段。
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
     * 旧 common.js 里暴露过批量表格操作的 "checkField"。
     * 这里保留同样的行为，但避免每个页面重复实现。
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
     * 管理端和前台详情页共用的标准 iframe 弹窗。
     * 这里复刻旧 modalBoxByPage 的行为，同时把 Layui 相关代码集中起来。
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
