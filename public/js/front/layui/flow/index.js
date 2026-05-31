layui.use(['jquery', 'form', 'table', 'element', 'layer'], function () {
    var $ = layui.jquery;
    var form = layui.form;
    var table = layui.table;
    var element = layui.element;
    var activeType = 'deposit';
    var mockRows = {
        deposit: [
            {order_no: 'MOCK-D-10001', userId: '1001', depositType: 'USDT', depositComment: 'TRC20', depositActProfit: 1200.50, modify_time: '2026-05-27 10:30:00'},
            {order_no: 'MOCK-D-10002', userId: '1008', depositType: 'Bank', depositComment: 'Online bank', depositActProfit: 860.00, modify_time: '2026-05-28 14:20:00'}
        ],
        withdraw: [
            {order_no: 'MOCK-W-10001', userId: '1001', withdrawalType: 'Bank', withdrawalType2: 'Balance', withdrawalActProfit: 420.00, withdrawalDate: '2026-05-27 16:30:00'},
            {order_no: 'MOCK-W-10002', userId: '1012', withdrawalType: 'USDT', withdrawalType2: 'Commission', withdrawalActProfit: 310.50, withdrawalDate: '2026-05-28 09:18:00'}
        ],
        withdraw_apply: [
            {order_no: 'MOCK-A-10001', userId: '1001', userName: 'Demo Agent', applyamount: 300.00, actdraw: 295.00, drawpoundage: 5.00, drawrate: '7.12', drawbankno: '6222021234567890', drawbankclass: 'Demo Bank', applystatus: 'Pending', applyremark: '-', rec_crt_date: '2026-05-28 11:20:00'},
            {order_no: 'MOCK-A-10002', userId: '1008', userName: 'Demo Customer', applyamount: 520.00, actdraw: 515.00, drawpoundage: 5.00, drawrate: '7.12', drawbankno: '6225889876543210', drawbankclass: 'Demo Bank', applystatus: 'Approved', applyremark: '-', rec_crt_date: '2026-05-28 15:08:00'}
        ],
        direct_deposit: [
            {order_no: 'MOCK-DD-10001', userId: '1008', directType: 'USDT', directProfit: 780.00, directComment: 'Direct customer', directModifyTime: '2026-05-27 13:12:00'},
            {order_no: 'MOCK-DD-10002', userId: '1010', directType: 'Bank', directProfit: 1150.25, directComment: 'Direct customer', directModifyTime: '2026-05-28 17:45:00'}
        ],
        direct_withdraw: [
            {order_no: 'MOCK-DW-10001', userId: '1008', directdrawalComment: 'Bank', directdrawalActProfit: 260.00, directdrawalModifyTime: '2026-05-28 12:18:00'},
            {order_no: 'MOCK-DW-10002', userId: '1010', directdrawalComment: 'USDT', directdrawalActProfit: 180.60, directdrawalModifyTime: '2026-05-28 18:05:00'}
        ],
        direct_agents_deposit: [
            {order_no: 'MOCK-AD-10001', userId: '1002', directType: 'Bank', directProfit: 2200.00, directComment: 'Direct agent', directModifyTime: '2026-05-27 09:50:00'},
            {order_no: 'MOCK-AD-10002', userId: '1003', directType: 'USDT', directProfit: 1690.40, directComment: 'Direct agent', directModifyTime: '2026-05-28 20:10:00'}
        ],
        direct_agents_withdraw: [
            {order_no: 'MOCK-AW-10001', userId: '1002', directdrawalComment: 'Bank', directdrawalActProfit: 900.00, directdrawalModifyTime: '2026-05-27 15:44:00'},
            {order_no: 'MOCK-AW-10002', userId: '1003', directdrawalComment: 'USDT', directdrawalActProfit: 660.30, directdrawalModifyTime: '2026-05-28 19:06:00'}
        ]
    };

    function t(key) {
        return typeof CrmLang !== 'undefined' && CrmLang.t ? CrmLang.t(key) : key;
    }

    function money(value) {
        var numberValue = Number(value || 0);
        return isNaN(numberValue) ? '-' : numberValue.toFixed(2);
    }

    function bankNo(value) {
        value = String(value || '');
        return value.length > 4 ? value.replace(/.(?=.{4})/g, '*') : value;
    }

    // 中文说明：把时间字符串统一转成时间戳，便于按日期范围过滤 mock 数据。
    function toTimeValue(value) {
        var parsed = Date.parse(value || '');
        return isNaN(parsed) ? 0 : parsed;
    }

    // 中文说明：把任意值转换成可模糊匹配的小写字符串。
    function toSearchText(value) {
        return String(value == null ? '' : value).toLowerCase();
    }

    // 中文说明：不同 tab 对应的时间字段不同，这里集中映射，避免筛选逻辑散落各处。
    function timeFieldByType(type) {
        var map = {
            deposit: 'modify_time',
            withdraw: 'withdrawalDate',
            withdraw_apply: 'rec_crt_date',
            direct_deposit: 'directModifyTime',
            direct_withdraw: 'directdrawalModifyTime',
            direct_agents_deposit: 'directModifyTime',
            direct_agents_withdraw: 'directdrawalModifyTime'
        };

        return map[type] || 'modify_time';
    }

    // 中文说明：账户流水页面只展示 mock 测试数据，所以搜索条件直接在前端内存里过滤。
    function filterMockRows(type, params) {
        var rows = (mockRows[type] || []).slice();
        var startTime = toTimeValue(params.startdate);
        var endTime = toTimeValue(params.enddate);
        var timeField = timeFieldByType(type);

        return rows.filter(function (row) {
            var keep = true;

            $.each(params, function (key, value) {
                var searchValue = toSearchText(value).trim();

                if (!searchValue || key === 'flow_type' || key === 'startdate' || key === 'enddate') {
                    return;
                }

                if (!Object.keys(row).some(function (field) {
                    return toSearchText(row[field]).indexOf(searchValue) !== -1;
                })) {
                    keep = false;
                    return false;
                }
            });

            if (!keep) {
                return false;
            }

            if (startTime || endTime) {
                var rowTime = toTimeValue(row[timeField]);

                if (startTime && rowTime < startTime) {
                    return false;
                }
                if (endTime && rowTime > endTime) {
                    return false;
                }
            }

            return true;
        });
    }

    function column(field, titleKey, width, templet, format) {
        var config = {
            field: field,
            title: t(titleKey),
            minWidth: width || 120,
            align: 'center',
            format: format || ''
        };

        if (templet) {
            config.templet = templet;
        }

        return config;
    }

    var columns = {
        deposit: [
            {type: 'numbers', title: '#', width: 70},
            column('order_no', 'front.order_no', 180),
            column('userId', 'front.user_id', 120),
            column('depositType', 'front.deposit_type', 140),
            column('depositComment', 'front.deposit_comment', 180),
            column('depositActProfit', 'front.deposit_amount', 140, function (d) { return money(d.depositActProfit); }, 'money'),
            column('modify_time', 'front.flow_time', 170)
        ],
        withdraw: [
            {type: 'numbers', title: '#', width: 70},
            column('order_no', 'front.order_no', 180),
            column('userId', 'front.user_id', 120),
            column('withdrawalType', 'front.withdraw_type', 140),
            column('withdrawalType2', 'front.withdraw_source', 160),
            column('withdrawalActProfit', 'front.withdraw_amount', 140, function (d) { return money(d.withdrawalActProfit); }, 'money'),
            column('withdrawalDate', 'front.flow_time', 170)
        ],
        withdraw_apply: [
            {type: 'numbers', title: '#', width: 70},
            column('order_no', 'front.order_no', 180),
            column('userId', 'front.user_id', 120),
            column('userName', 'front.user_name', 140),
            column('applyamount', 'front.apply_amount', 130, function (d) { return money(d.applyamount); }, 'money'),
            column('actdraw', 'front.actual_amount', 130, function (d) { return money(d.actdraw); }, 'money'),
            column('drawpoundage', 'front.fee', 120, function (d) { return money(d.drawpoundage); }, 'money'),
            column('drawrate', 'front.exchange_rate', 120),
            column('drawbankno', 'front.bank_no', 160, function (d) { return bankNo(d.drawbankno); }),
            column('drawbankclass', 'front.bank_name', 160),
            column('applystatus', 'front.apply_status', 120),
            column('applyremark', 'front.reject_reason', 180),
            column('rec_crt_date', 'front.flow_time', 170)
        ],
        direct_deposit: [
            {type: 'numbers', title: '#', width: 70},
            column('order_no', 'front.order_no', 180),
            column('userId', 'front.user_id', 120),
            column('directType', 'front.deposit_type', 140),
            column('directProfit', 'front.deposit_amount', 140, function (d) { return money(d.directProfit); }, 'money'),
            column('directComment', 'front.deposit_source', 180),
            column('directModifyTime', 'front.flow_time', 170)
        ],
        direct_withdraw: [
            {type: 'numbers', title: '#', width: 70},
            column('order_no', 'front.order_no', 180),
            column('userId', 'front.user_id', 120),
            column('directdrawalComment', 'front.withdraw_type', 160),
            column('directdrawalActProfit', 'front.withdraw_amount', 140, function (d) { return money(d.directdrawalActProfit); }, 'money'),
            column('directdrawalModifyTime', 'front.flow_time', 170)
        ],
        direct_agents_deposit: [
            {type: 'numbers', title: '#', width: 70},
            column('order_no', 'front.order_no', 180),
            column('userId', 'front.user_id', 120),
            column('directType', 'front.deposit_type', 140),
            column('directProfit', 'front.deposit_amount', 140, function (d) { return money(d.directProfit); }, 'money'),
            column('directComment', 'front.deposit_source', 180),
            column('directModifyTime', 'front.flow_time', 170)
        ],
        direct_agents_withdraw: [
            {type: 'numbers', title: '#', width: 70},
            column('order_no', 'front.order_no', 180),
            column('userId', 'front.user_id', 120),
            column('directdrawalComment', 'front.withdraw_type', 160),
            column('directdrawalActProfit', 'front.withdraw_amount', 140, function (d) { return money(d.directdrawalActProfit); }, 'money'),
            column('directdrawalModifyTime', 'front.flow_time', 170)
        ]
    };

    function formFor(type) {
        return $('.J_flowForm[data-flow-type="' + type + '"]');
    }

    function collect(type) {
        var params = {flow_type: type};

        $.each(formFor(type).serializeArray(), function (_, item) {
            if (item.value !== null && item.value !== '') {
                params[item.name] = item.value;
            }
        });

        return params;
    }

    function syncWithdrawSource(type) {
        var show = ['withdraw', 'withdraw_apply', 'direct_withdraw', 'direct_agents_withdraw'].indexOf(type) !== -1;
        formFor(type).find('.J_withdrawSource').toggle(show);
    }

    function renderTable(type) {
        var params = collect(type);
        var rows = filterMockRows(type, params);

        syncWithdrawSource(type);
        table.render(CrmTable.layuiConfig('front', {
            elem: '#flowTable_' + type,
            id: 'flowTable_' + type,
            data: rows,
            page: false,
            cols: [columns[type] || columns.deposit],
            summaryElem: '#flowSummary_' + type,
            done: function (res) {
                if (typeof CrmTable !== 'undefined' && CrmTable.renderSummary) {
                    CrmTable.renderSummary('#flowSummary_' + type, rows, columns[type] || columns.deposit, null);
                }
            }
        }));
    }

    form.on('submit(flowSearch)', function (data) {
        var type = $(data.form).attr('data-flow-type') || activeType;
        renderTable(type);
        return false;
    });

    $('.J_flowReset').on('click', function () {
        var $form = $(this).closest('.J_flowForm');
        var type = $form.attr('data-flow-type') || activeType;

        $form[0].reset();
        form.render();
        renderTable(type);
    });

    element.on('tab(frontFlowTabs)', function (data) {
        activeType = $(this).attr('lay-id') || activeType;
        renderTable(activeType);
    });

    function boot() {
        if (typeof CrmLang !== 'undefined') {
            CrmLang.switchUI();
        }
        if (typeof CrmDateRange !== 'undefined') {
            CrmDateRange.init($('.flow-page'));
        }
        form.render();
        $('.J_flowForm').each(function () {
            syncWithdrawSource($(this).attr('data-flow-type'));
        });
        renderTable(activeType);
    }

    if (typeof CrmLang !== 'undefined' && CrmLang.loadLanguage) {
        CrmLang.loadLanguage(CrmLang.getLocale()).then(boot).catch(boot);
    } else {
        boot();
    }
});
