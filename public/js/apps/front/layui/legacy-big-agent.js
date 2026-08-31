// Created by PhpStorm.
// Project name co_crmv5.
// User: Huang Gang
// Date: 2026/08/17
// Time: 22:25
(function (window, document, $) {
    'use strict';

    if (!$) {
        return;
    }

    var config = window.LegacyBigAgent || {};
    var $tableConfig = $('#legacyBigAgentTableConfig');
    var currentPage = 1;
    var pageSize = 20;
    var loading = false;
    var activeRequest = null;
    var requestGeneration = 0;

    // 所有 legacy 写请求都依赖同源 session，并显式携带 CSRF；这里绝不读取或发送普通用户 Bearer token。
    $.ajaxSetup({
        headers: {
            'X-CSRF-TOKEN': config.csrfToken || $('meta[name="csrf-token"]').attr('content') || '',
            'X-Requested-With': 'XMLHttpRequest'
        }
    });

    if (!$tableConfig.length) {
        return;
    }

    function escapeHtml(value) {
        return $('<div>').text(value === null || typeof value === 'undefined' ? '' : String(value)).html();
    }

    function readColumns() {
        try {
            return JSON.parse($tableConfig.attr('data-columns') || '[]');
        } catch (error) {
            return [];
        }
    }

    function formPayload() {
        var payload = {_token: config.csrfToken || '', page: currentPage, limit: pageSize, searchtype: 'clickSearch'};
        $('#legacyBigAgentSearchForm').serializeArray().forEach(function (item) {
            payload[item.name] = item.value;
        });
        return payload;
    }

    function loadDynamicOptions() {
        var requests = [];

        $('#legacyBigAgentSearchForm select[data-options-endpoint]').each(function () {
            var $select = $(this);
            var endpoint = $select.attr('data-options-endpoint');
            if (!endpoint) {
                return;
            }

            requests.push($.getJSON(endpoint).done(function (response) {
                var options = response && response.data && Array.isArray(response.data.list)
                    ? response.data.list
                    : [];
                options.forEach(function (option) {
                    var value = option && option.value;
                    var label = option && option.label;
                    if (value === null || typeof value === 'undefined' || String(value).trim() === '') {
                        return;
                    }
                    $select.append($('<option>').val(String(value)).text(label || value));
                });
            }));
        });

        return $.when.apply($, requests).always(function () {
            if (window.layui && layui.form) {
                layui.form.render('select');
            }
        });
    }

    function renderRows(rows, footer) {
        var columns = readColumns();
        var $body = $('#legacyBigAgentResultTable tbody').empty();
        var $foot = $('#legacyBigAgentResultTable tfoot').empty();
        var emptyText = $tableConfig.attr('data-empty-text') || '-';

        if (!rows.length) {
            $body.append('<tr><td colspan="' + columns.length + '" class="legacy-big-agent-muted">' + escapeHtml(emptyText) + '</td></tr>');
            return;
        }

        rows.forEach(function (row) {
            var html = '<tr>';
            columns.forEach(function (column, index) {
                var value = row[column.key];
                if (index === 0 && $tableConfig.attr('data-child-endpoint')) {
                    html += '<td><button type="button" class="layui-btn layui-btn-xs layui-btn-primary J_legacyBigAgentDrill" data-user-id="' + escapeHtml(row.user_id || row.userId || '') + '">' + escapeHtml(value) + '</button></td>';
                    return;
                }
                html += '<td>' + escapeHtml(value) + '</td>';
            });
            $body.append(html + '</tr>');
        });

        (footer || []).forEach(function (row) {
            var html = '<tr>';
            columns.forEach(function (column) {
                html += '<td><strong>' + escapeHtml(row[column.key]) + '</strong></td>';
            });
            $foot.append(html + '</tr>');
        });
    }

    function renderPagination(total, endpoint, extraPayload) {
        if (!window.layui || !layui.laypage) {
            return;
        }
        layui.laypage.render({
            elem: 'legacyBigAgentPagination',
            count: Number(total || 0),
            curr: currentPage,
            limit: pageSize,
            jump: function (obj, first) {
                if (!first && currentPage !== obj.curr) {
                    currentPage = obj.curr;
                    loadRows(endpoint, extraPayload);
                }
            }
        });
    }

    function handleFailure(response) {
        if (response && response.redirect && response.redirectUrl) {
            window.location.href = response.redirectUrl;
            return;
        }
        var message = response && (response.message || response.msg);
        if (window.layui && layui.layer) {
            layui.layer.msg(message || $tableConfig.attr('data-error-text') || 'Request failed', {icon: 2});
        }
    }

    function loadRows(endpoint, extraPayload) {
        var generation = ++requestGeneration;
        if (activeRequest && typeof activeRequest.abort === 'function') {
            activeRequest.abort();
        }
        loading = true;
        var request = $.ajax({
            url: endpoint || $tableConfig.attr('data-endpoint'),
            type: 'POST',
            dataType: 'json',
            data: $.extend(formPayload(), extraPayload || {})
        });
        activeRequest = request;

        request.done(function (response) {
            if (generation !== requestGeneration) {
                return;
            }
            // 旧表格协议把 rows/total/footer 放在根级，不能按现代 API 的 response.data 解包。
            var rows = response.rows || [];
            if (response.redirect || !Array.isArray(rows)) {
                handleFailure(response);
                return;
            }
            renderRows(rows, response.footer || []);
            renderPagination(response.total || 0, endpoint, extraPayload);
        }).fail(function (xhr) {
            if (generation !== requestGeneration) {
                return;
            }
            handleFailure(xhr.responseJSON || {});
        }).always(function () {
            if (generation !== requestGeneration) {
                return;
            }
            loading = false;
            activeRequest = null;
        });
    }

    function loadRootRows() {
        loadRows();
    }

    layui.use(['form', 'layer', 'laypage'], function () {
        layui.form.on('submit(legacyBigAgentSearch)', function () {
            currentPage = 1;
            loadRootRows();
            return false;
        });

        $('#legacyBigAgentReset').on('click', function () {
            document.getElementById('legacyBigAgentSearchForm').reset();
            currentPage = 1;
            loadRootRows();
        });

        $(document).on('click', '.J_legacyBigAgentDrill', function () {
            var endpoint = $tableConfig.attr('data-child-endpoint');
            var userId = Number($(this).attr('data-user-id') || 0);
            if (!endpoint || userId <= 0) {
                return;
            }
            currentPage = 1;
            loadRows(endpoint, {userPId: userId, searchtype: 'subSearch'});
        });

        loadDynamicOptions();
        loadRows();
    });
})(window, document, window.jQuery);
