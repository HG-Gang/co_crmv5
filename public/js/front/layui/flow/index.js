layui.use(['jquery', 'form', 'table', 'element', 'layer'], function () {
    var $ = layui.jquery;
    var form = layui.form;
    var table = layui.table;
    var element = layui.element;
    var rendered = {};
    var activeType = 'deposit';

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

    // Req 10: mock test data for each flow tab
    function mockFlowRows(type, count) {
        count = count || 12;
        var rows = [];
        var now = new Date();
        for (var i = 0; i < count; i++) {
            var d = new Date(now - (i + 1) * 86400000);
            var dateStr = d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0') + ' 10:' + String(30 + (i % 30)).padStart(2, '0') + ':00';
            var base = {
                order_no: 'FL' + (type === 'deposit' ? 'D' : 'W') + (200000 + i),
                userId: 1000 + (i % 20),
                userName: 'user_' + (1000 + (i % 20)),
                modify_time: dateStr,
                rec_crt_date: dateStr,
                withdrawalDate: dateStr,
                directModifyTime: dateStr,
                directdrawalModifyTime: dateStr
            };
            if (type === 'deposit' || type === 'direct_deposit' || type === 'direct_agents_deposit') {
                base.depositType = ['Bank', 'USDT', 'Wire'][i % 3];
                base.depositComment = 'Demo deposit #' + (i + 1);
                base.depositActProfit = Math.round((800 + i * 123.5) * 100) / 100;
                base.directType = base.depositType;
                base.directProfit = base.depositActProfit;
                base.directComment = 'Agent deposit #' + (i + 1);
            } else {
                base.withdrawalType = ['Bank', 'USDT', 'Wire'][i % 3];
                base.withdrawalType2 = ['Manual', 'Auto'][i % 2];
                base.withdrawalActProfit = Math.round((500 + i * 87.2) * 100) / 100;
                base.directdrawalComment = 'Withdrawal #' + (i + 1);
                base.directdrawalActProfit = base.withdrawalActProfit;
                base.applyamount = Math.round((600 + i * 92) * 100) / 100;
                base.actdraw = Math.round(base.applyamount * 0.98 * 100) / 100;
                base.drawpoundage = Math.round(base.applyamount * 0.02 * 100) / 100;
                base.drawrate = '1.0000';
                base.drawbankno = '622588****' + String(1000 + i);
                base.drawbankclass = ['ICBC', 'BOC', 'CCB'][i % 3];
                base.applystatus = ['Approved', 'Pending', 'Rejected'][i % 3];
                base.applyremark = i % 3 === 2 ? 'Insufficient balance' : '';
            }
            rows.push(base);
        }
        return rows;
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
            data: mockFlowRows(type)
        }));
        rendered[type] = true;
    }

    form.on('submit(flowSearch)', function (data) {
        var type = $(data.form).attr('data-flow-type') || activeType;
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
