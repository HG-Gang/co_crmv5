layui.use(['jquery', 'form', 'table', 'element', 'layer'], function () {
    var $ = layui.jquery;
    var form = layui.form;
    var table = layui.table;
    var element = layui.element;
    var rendered = {};
    var activeType = 'deposit';
    var usingMock = {};
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
        syncWithdrawSource(type);
        if (rendered[type]) {
            return;
        }

        table.render(CrmTable.layuiConfig('front', {
            elem: '#flowTable_' + type,
            id: 'flowTable_' + type,
            url: '/api/front/accountFlow',
            where: collect(type),
            cols: [columns[type] || columns.deposit],
            summaryElem: '#flowSummary_' + type,
            done: function (res) {
                var rows = res && res.data ? res.data : [];

                if (rows.length || !mockRows[type] || usingMock[type]) {
                    return;
                }
                usingMock[type] = true;
                table.reloadData('flowTable_' + type, {
                    data: mockRows[type],
                    page: false
                });
                if (typeof CrmTable !== 'undefined' && CrmTable.renderSummary) {
                    CrmTable.renderSummary('#flowSummary_' + type, mockRows[type], columns[type] || columns.deposit, null);
                }
            }
        }));
        rendered[type] = true;
    }

    form.on('submit(flowSearch)', function (data) {
        var type = $(data.form).attr('data-flow-type') || activeType;
        usingMock[type] = false;
        renderTable(type);
        table.reloadData('flowTable_' + type, {
            where: collect(type),
            page: {curr: 1}
        });
        return false;
    });

    $('.J_flowReset').on('click', function () {
        var $form = $(this).closest('.J_flowForm');
        var type = $form.attr('data-flow-type') || activeType;

        $form[0].reset();
        usingMock[type] = false;
        form.render();
        renderTable(type);
        table.reloadData('flowTable_' + type, {
            where: collect(type),
            page: {curr: 1}
        });
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
