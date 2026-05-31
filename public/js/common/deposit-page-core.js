/**
 * 入金页面公共核心逻辑。
 * 页面入口只传入选择器、表格列和接口地址，本文件统一处理通道配置加载、提交校验、搜索重置和历史表格刷新。
 */
var DepositPageCore = (function () {
    'use strict';

    // 统一读取前台语言包，缺少语言对象时返回原 key，便于快速发现未补齐的多语言项。
    function t(key) {
        return typeof CrmLang !== 'undefined' && CrmLang.t ? CrmLang.t(key) : key;
    }

    // 新旧接口成功码略有差异，优先复用公共表格工具的成功判断。
    function isSuccess(res) {
        return typeof CrmTable !== 'undefined' && CrmTable.isSuccess ? CrmTable.isSuccess(res) : (res && res.code === 1000);
    }

    // 初始化入金页的全部运行状态；不同页面通过 options 复用同一套流程。
    function init(options) {
        var opts = options || {};
        var form = layui.form;
        var table = layui.table;
        var layer = layui.layer;
        var manager = opts.manager;
        var pageState = {
            isAllowed: true,
            lastPaymentUrl: ''
        };

        // 只收集用户填写过的筛选条件，避免空字符串覆盖后端默认查询。
        function collectFilters() {
            var params = {};

            $(opts.filterForm).find('input[name], select[name]').each(function () {
                var $field = $(this);
                var value = $field.val();

                if (value !== null && value !== '') {
                    params[$field.attr('name')] = value;
                }
            });

            return params;
        }

        // 获取当前用户可用状态、通道列表和默认用户信息，然后刷新通道 Tab。
        function loadPageConfig() {
            CrmAjax.request({
                guard: 'front',
                url: opts.pageApi,
                success: function (res) {
                    var data;

                    if (!isSuccess(res)) {
                        layer.msg(res.message || t('common.error'), {icon: 2});
                        return;
                    }

                    data = res.data || {};
                    pageState.isAllowed = !(data.is_allowed === false || data.is_allowed === 0 || data.is_allowed === '0');
                    if (opts.userIdInput && data.user) {
                        $(opts.userIdInput).val(data.user.user_id || '');
                    }
                    renderAllowedState(data.disabled_message || '');
                    manager.render(data.channels || []);
                    if (form) {
                        form.render();
                    }
                },
                error: function () {
                    layer.msg(t('common.error'), {icon: 2});
                }
            });
        }

        // 根据接口开关控制提交按钮，并把禁用原因展示在表单上方。
        function renderAllowedState(message) {
            var $notice = $(opts.disabledNotice);
            var $submit = $(opts.submitButton);

            $('.deposit-page').toggleClass('is-disabled', !pageState.isAllowed);
            $submit.prop('disabled', !pageState.isAllowed).toggleClass('layui-btn-disabled', !pageState.isAllowed);
            if (!$notice.length) {
                return;
            }

            if (pageState.isAllowed) {
                $notice.addClass('layui-hide').text('');
                return;
            }

            $notice.removeClass('layui-hide').text(message || t('front.deposit_disabled'));
        }

        // 渲染入金历史记录，summaryElem 会让公共表格工具把统计放在表格上方。
        function renderHistoryTable() {
            table.render(CrmTable.layuiConfig('front', {
                elem: opts.tableElem,
                url: opts.historyApi,
                where: collectFilters(),
                cols: [opts.columns],
                parseData: CrmTable.layuiParseData(),
                summaryElem: opts.summaryElem
            }));
        }

        // 提交前先校验通道、金额和限额，通过后提交旧项目兼容字段。
        function submitDeposit(field) {
            var channel = manager.getSelected();
            var amount = Number(field.deposit_amt_usd || field.amount || 0);
            var actualAmount = $(opts.formSelector).find('[name="deposit_pay_amt_rmb"]').val() || '';

            if (!pageState.isAllowed) {
                layer.msg(t('front.deposit_disabled'), {icon: 2});
                return;
            }

            if (!channel) {
                layer.msg(t('front.no_payment_channel'), {icon: 2});
                return;
            }
            if (!amount || amount <= 0) {
                layer.msg(t('validation.numeric'), {icon: 2});
                return;
            }
            if (channel.min_amount && amount < Number(channel.min_amount)) {
                layer.msg(t('front.amount_below_channel_min'), {icon: 2});
                return;
            }
            if (channel.max_amount && amount > Number(channel.max_amount)) {
                layer.msg(t('front.amount_above_channel_max'), {icon: 2});
                return;
            }

            CrmAjax.request({
                guard: 'front',
                url: opts.submitApi,
                data: {
                    amount: amount,
                    deposit_amt_usd: amount,
                    deposit_pay_amt_rmb: actualAmount,
                    channel: channel.code,
                    pay_channel: channel.code,
                    passageway: channel.passageway || channel.code
                },
                success: function (res) {
                    var responseData;

                    if (!isSuccess(res)) {
                        layer.msg(res.message || t('common.error'), {icon: 2});
                        return;
                    }

                    layer.msg(res.message || t('common.success'), {icon: 1});
                    responseData = res.data || {};
                    pageState.lastPaymentUrl = responseData.payment_url
                        || (responseData.deposit && responseData.deposit.payment_url)
                        || responseData.pay_url
                        || responseData.url
                        || '';
                    toggleRetryButton();
                    var currentUserId = opts.userIdInput ? $(opts.userIdInput).val() : '';
                    $(opts.formSelector)[0].reset();
                    if (opts.userIdInput) {
                        $(opts.userIdInput).val(currentUserId);
                    }
                    manager.select(channel.code);
                    renderHistoryTable();
                },
                error: function () {
                    layer.msg(t('common.error'), {icon: 2});
                }
            });
        }

        // 支付链接只在提交成功后出现，方便用户重新打开支付页面。
        function toggleRetryButton() {
            var $button = $(opts.retryButton);

            if (!$button.length) {
                return;
            }

            if (pageState.lastPaymentUrl) {
                $button.attr('href', pageState.lastPaymentUrl).show();
                return;
            }

            $button.attr('href', 'javascript:void(0);').hide();
        }

        $(opts.amountInput).on('input propertychange', function () {
            manager.syncAmount();
        });

        $(opts.retryButton).on('click', function () {
            if (!pageState.lastPaymentUrl) {
                return false;
            }
        });

        form.on('submit(depositSubmit)', function (data) {
            submitDeposit(data.field || {});
            return false;
        });

        form.on('submit(depositSearch)', function () {
            renderHistoryTable();
            return false;
        });

        $(opts.resetButton).on('click', function () {
            $(opts.filterForm)[0].reset();
            form.render();
            renderHistoryTable();
        });

        if (typeof CrmLang !== 'undefined') {
            CrmLang.switchUI();
        }
        loadPageConfig();
        renderHistoryTable();
    }

    return {
        init: init
    };
})();
