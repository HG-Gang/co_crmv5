layui.use(['jquery', 'form', 'table', 'layer'], function () {
    var $ = layui.jquery;

    var manager = PayChannelManager.create({
        container: '#depositChannelList',
        input: '#depositChannel',
        payChannelInput: '#pay_channel',
        passagewayInput: '#passageway',
        amountInput: '#deposit_amt_usd',
        actualInput: '#deposit_pay_amt_rmb',
        rateInput: '#depositExchangeRate'
    });

    function boot() {
        if (typeof CrmLang !== 'undefined') {
            CrmLang.updateUI();
        }
        if (typeof CrmDateRange !== 'undefined') {
            CrmDateRange.init($('.deposit-page'));
        }

        DepositPageCore.init({
            manager: manager,
            pageApi: '/api/front/depositPage',
            submitApi: '/api/front/submitDeposit',
            historyApi: '/api/front/depositHistory',
            formSelector: '#depositForm',
            filterForm: '#depositSearchForm',
            tableElem: '#depositHistoryTable',
            summaryElem: '#depositHistorySummary',
            amountInput: '#deposit_amt_usd',
            userIdInput: '#depositUserId',
            disabledNotice: '#depositDisabledNotice',
            submitButton: '#depositBtn',
            retryButton: '#openBlankBtn',
            resetButton: '#depositSearchReset',
            columns: [
                {field: 'order_no', title: CrmLang.t('front.order_no'), minWidth: 180},
                {field: 'userId', title: CrmLang.t('front.user_id'), width: 120},
                {field: 'depositType', title: CrmLang.t('front.deposit_type'), width: 150},
                {field: 'exchange_rate', title: CrmLang.t('front.exchange_rate'), width: 120},
                {field: 'depositActProfit', title: CrmLang.t('front.deposit_amount'), width: 140, format: 'money'},
                {field: 'status_text', title: CrmLang.t('common.status'), width: 120},
                {field: 'depositComment', title: CrmLang.t('front.deposit_comment'), minWidth: 180},
                {field: 'modify_time', title: CrmLang.t('front.flow_time'), minWidth: 170}
            ]
        });
    }

    if (typeof CrmLang !== 'undefined' && CrmLang.loadLanguage) {
        CrmLang.loadLanguage(CrmLang.getLocale()).then(boot).catch(boot);
    } else {
        boot();
    }
});
