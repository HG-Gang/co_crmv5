layui.use(['jquery', 'form', 'table', 'layer'], function () {
    var $ = layui.jquery;
    var form = layui.form;
    var table = layui.table;
    var layer = layui.layer;
    var pageData = {
        isAllowed: true,
        min: 0,
        max: 0,
        feeRate: 0,
        fixedFee: 0,
        availableAmount: 0
    };
    var historyRendered = false;

    function t(key) {
        return typeof CrmLang !== 'undefined' && CrmLang.t ? CrmLang.t(key) : key;
    }

    function isSuccess(res) {
        return typeof CrmTable !== 'undefined' && CrmTable.isSuccess ? CrmTable.isSuccess(res) : (res && res.code >= 1000 && res.code < 4000);
    }

    function money(value) {
        var numberValue = Number(value || 0);
        return isNaN(numberValue) ? '0.00' : numberValue.toFixed(2);
    }

    function renderMockSummary() {
        var html = '';
        var items = [
            {label: t('front.today_withdraw'), value: 6200.00},
            {label: t('front.pending_count'), value: 4, text: true},
            {label: t('front.week_withdraw'), value: 41580.25},
            {label: t('front.average_amount'), value: 2450.50}
        ];

        items.forEach(function (item, index) {
            html += '<div class="crm-table-summary-item summary-color-' + (index % 8) + '">';
            html += '<span>' + item.label + '</span>';
            html += '<strong>' + (item.text ? item.value : money(item.value)) + '</strong>';
            html += '</div>';
        });
        $('#withdrawMockSummary').html(html);
    }

    function bankNo(value) {
        value = String(value || '');
        return value.length > 4 ? value.replace(/.(?=.{4})/g, '*') : value;
    }

    function collectFilters() {
        var params = {};

        $('#withdrawSearchForm').find('input[name], select[name]').each(function () {
            var $field = $(this);
            var value = $field.val();

            if (value !== null && value !== '') {
                params[$field.attr('name')] = value;
            }
        });

        return params;
    }

    function renderAllowedState(message) {
        var $notice = $('#withdrawDisabledNotice');
        var disabled = !pageData.isAllowed;

        $('.withdraw-page').toggleClass('is-disabled', disabled);
        $('#withdrawBtn').prop('disabled', disabled).toggleClass('layui-btn-disabled', disabled);
        if (!$notice.length) {
            return;
        }

        if (!disabled) {
            $notice.addClass('layui-hide').text('');
            return;
        }

        $notice.removeClass('layui-hide').text(message || t('front.withdraw_disabled'));
    }

    function fillPageFields(data) {
        var user = data.user || {};
        var bank = data.bank || {};
        var limits = data.withdraw_limits || {};
        var rates = data.exchange_rates || {};

        pageData.isAllowed = !(data.is_allowed === false || data.is_allowed === 0 || data.is_allowed === '0');
        pageData.min = Number(limits.min || 0);
        pageData.max = Number(limits.max || 0);
        pageData.feeRate = Number(data.fee_rate || 0);
        pageData.fixedFee = Number(data.fixed_fee || 0);
        pageData.availableAmount = Number(user.available_amount || 0);

        $('#withdrawUserId').val(user.user_id || '');
        $('#withdrawBalance').val(money(user.balance));
        $('#withdrawAvailable').val(money(user.available_amount));
        $('#withdrawExchangeRate').val(rates.CNY || rates.cny || '');
        $('#withdrawBankName').val(bank.bank_name ? bank.bank_name + ' / ' + bankNo(bank.bank_no) : bankNo(bank.bank_no));
        calculateAmount();
        renderAllowedState(data.disabled_message || '');
    }

    function loadPageConfig() {
        CrmAjax.request({
            guard: 'front',
            url: '/api/front/withdrawPage',
            success: function (res) {
                if (!isSuccess(res)) {
                    layer.msg(res.message || t('common.error'), {icon: 2});
                    return;
                }

                fillPageFields(res.data || {});
                form.render();
            },
            error: function () {
                layer.msg(t('common.error'), {icon: 2});
            }
        });
    }

    function calculateAmount() {
        var amount = Number($('#withdrawAmount').val() || 0);
        var fee = 0;
        var actual = 0;

        if (amount > 0) {
            fee = pageData.fixedFee + amount * (pageData.feeRate / 100);
            actual = Math.max(0, amount - fee);
        }

        $('#withdrawFee').val(money(fee));
        $('#withdrawActualAmount').val(money(actual));
    }

    function renderHistoryTable() {
        var columns = [
            {field: 'order_no', title: t('front.order_no'), minWidth: 180},
            {field: 'userId', title: t('front.user_id'), width: 120},
            {field: 'userName', title: t('front.user_name'), width: 140},
            {field: 'applyamount', title: t('front.apply_amount'), width: 130, format: 'money', templet: function (d) { return money(d.applyamount); }},
            {field: 'actdraw', title: t('front.actual_amount'), width: 130, format: 'money', templet: function (d) { return money(d.actdraw); }},
            {field: 'drawpoundage', title: t('front.fee'), width: 120, format: 'money', templet: function (d) { return money(d.drawpoundage); }},
            {field: 'drawrate', title: t('front.exchange_rate'), width: 120},
            {field: 'drawbankno', title: t('front.bank_no'), minWidth: 160, templet: function (d) { return bankNo(d.drawbankno); }},
            {field: 'drawbankclass', title: t('front.bank_name'), minWidth: 160},
            {field: 'status_text', title: t('common.status'), width: 120},
            {field: 'applyremark', title: t('front.reject_reason'), minWidth: 180},
            {field: 'withdrawalDate', title: t('front.flow_time'), minWidth: 170}
        ];

        if (historyRendered) {
            table.reloadData('withdrawHistoryTable', {
                where: collectFilters(),
                page: {curr: 1}
            });
            return;
        }

        table.render(CrmTable.layuiConfig('front', {
            elem: '#withdrawHistoryTable',
            id: 'withdrawHistoryTable',
            url: '/api/front/withdrawHistory',
            where: collectFilters(),
            cols: [columns],
            summaryElem: '#withdrawHistorySummary'
        }));
        historyRendered = true;
    }

    function validateSubmit(field) {
        var amount = Number(field.amount || 0);

        if (!pageData.isAllowed) {
            layer.msg(t('front.withdraw_disabled'), {icon: 2});
            return false;
        }
        if (!amount || amount <= 0) {
            layer.msg(t('validation.numeric'), {icon: 2});
            return false;
        }
        if (pageData.min && amount < pageData.min) {
            layer.msg(t('front.withdraw_amount_below_min'), {icon: 2});
            return false;
        }
        if (pageData.max && amount > pageData.max) {
            layer.msg(t('front.withdraw_amount_above_max'), {icon: 2});
            return false;
        }
        if (pageData.availableAmount && amount > pageData.availableAmount) {
            layer.msg(t('front.withdraw_amount_exceeds_available'), {icon: 2});
            return false;
        }
        if (!field.password) {
            layer.msg(t('front.withdraw_password_placeholder'), {icon: 2});
            return false;
        }
        if (!$('#withdrawAgree').is(':checked')) {
            layer.msg(t('front.withdrawal_terms_required'), {icon: 2});
            return false;
        }

        return true;
    }

    function submitWithdraw(field) {
        var amount = Number(field.amount || 0);

        if (!validateSubmit(field)) {
            return;
        }

        CrmAjax.request({
            guard: 'front',
            url: '/api/front/submitWithdraw',
            data: {
                amount: amount,
                withdraw_amt: amount,
                password: field.password,
                withdraw_password: field.password,
                withdraw_psw: field.password,
                agree: $('#withdrawAgree').is(':checked') ? 1 : 0
            },
            success: function (res) {
                var userId = $('#withdrawUserId').val();

                if (!isSuccess(res)) {
                    layer.msg(res.message || t('common.error'), {icon: 2});
                    return;
                }

                layer.msg(res.message || t('common.success'), {icon: 1});
                $('#withdrawForm')[0].reset();
                $('#withdrawUserId').val(userId);
                fillPageFields({
                    user: {
                        user_id: userId,
                        balance: $('#withdrawBalance').val(),
                        available_amount: pageData.availableAmount
                    },
                    bank: {},
                    withdraw_limits: {min: pageData.min, max: pageData.max},
                    exchange_rates: {CNY: $('#withdrawExchangeRate').val()},
                    fee_rate: pageData.feeRate,
                    fixed_fee: pageData.fixedFee,
                    is_allowed: pageData.isAllowed
                });
                form.render();
                loadPageConfig();
                renderHistoryTable();
            },
            error: function () {
                layer.msg(t('common.error'), {icon: 2});
            }
        });
    }

    $('#withdrawAmount').on('input propertychange', calculateAmount);

    form.on('submit(withdrawSubmit)', function (data) {
        submitWithdraw(data.field || {});
        return false;
    });

    form.on('submit(withdrawSearch)', function () {
        renderHistoryTable();
        return false;
    });

    $('#withdrawSearchReset').on('click', function () {
        $('#withdrawSearchForm')[0].reset();
        form.render();
        renderMockSummary();
        renderHistoryTable();
    });

    function boot() {
        if (typeof CrmLang !== 'undefined') {
            CrmLang.updateUI();
        }
        if (typeof CrmDateRange !== 'undefined') {
            CrmDateRange.init($('.withdraw-page'));
        }
        form.render();
        renderMockSummary();
        loadPageConfig();
        renderHistoryTable();
    }

    if (typeof CrmLang !== 'undefined' && CrmLang.loadLanguage) {
        CrmLang.loadLanguage(CrmLang.getLocale()).then(boot).catch(boot);
    } else {
        boot();
    }
});
