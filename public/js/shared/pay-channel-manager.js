// Created by PhpStorm.
// Project name co_crmv5.
// User: Huang Gang
// Date: 2026/07/28
// Time: 23:44
/**
 * 支付通道界面管理器。
 *
 * 这是旧 payChannelManager 的 Layui/jQuery 版本：通道数据来自接口，
 * 这里负责排序、渲染、记录选中通道，并在金额或通道变化时重新计算
 * 实际应付金额。
 */
var PayChannelManager = (function () {
    'use strict';

    function t(key) {
        return typeof CrmLang !== 'undefined' && CrmLang.t ? CrmLang.t(key) : key;
    }

    function escapeHtml(value) {
        return typeof CrmTable !== 'undefined' && CrmTable.escapeHtml
            ? CrmTable.escapeHtml(value)
            : String(value || '');
    }

    function toArray(value) {
        return typeof CrmTable !== 'undefined' && CrmTable.toArray
            ? CrmTable.toArray(value)
            : value;
    }

    function create(options) {
        var opts = options || {};
        var channels = [];
        var selectedCode = '';

        function normalize(rawChannels) {
            var list = toArray(rawChannels) || [];

            if (!$.isArray(list)) {
                list = [];
            }

            return $.map(list, function (item) {
                if (!item) {
                    return null;
                }

                return {
                    id: item.id,
                    code: String(item.code || item.channel_code || item.id || ''),
                    label_key: item.label_key || '',
                    name: item.label_key ? t(item.label_key) : (item.name || item.channel_name || item.code || item.channel_code),
                    exchange_rate: Number(item.exchange_rate || item.rate || 1),
                    sort: Number(item.sort || 0),
                    is_default: item.is_default == 1 || item.is_default === true,
                    min_amount: Number(item.min_amount || item.min || 0),
                    max_amount: Number(item.max_amount || item.max || 0),
                    precision: Number(item.precision || 2),
                    type: item.type || '',
                    type_label_key: item.type_label_key || '',
                    type_label: item.type_label || '',
                    passageway: String(item.passageway || item.code || item.channel_code || item.id || ''),
                    description: item.description || '',
                    remark_items: $.isArray(item.remark_items) ? item.remark_items : []
                };
            }).sort(function (a, b) {
                return b.sort - a.sort;
            });
        }

        function renderRemarkHtml(remarkItems, description) {
            var html = '';
            var items = remarkItems || [];
            var i;

            if (items.length) {
                html += '<ul class="channel-remark-list">';
                for (i = 0; i < items.length; i++) {
                    html += '<li>' + escapeHtml(items[i]) + '</li>';
                }
                html += '</ul>';
                return html;
            }

            if (description) {
                return '<div class="channel-meta">' + escapeHtml(description) + '</div>';
            }

            return '';
        }

        function render(rawChannels) {
            var $container = $(opts.container);
            var html = '';
            var i;
            var defaultChannel = null;
            var useHostTab;

            channels = normalize(rawChannels);
            if (!$container.length) {
                return;
            }

            if (!channels.length) {
                $container.html('<div class="layui-col-md12"><div class="layui-elem-quote">' + escapeHtml(t('front.no_payment_channel')) + '</div></div>');
                select('');
                return;
            }

            // 如果模板已经声明了 layui-tab 容器，这里只渲染 tab 内部结构，避免异步刷新后出现嵌套 tab。
            // 旧页面未声明容器时仍补全外层结构，保证支付通道组件可以被其他页面复用。
            useHostTab = $container.hasClass('layui-tab');
            html += useHostTab ? '<ul class="layui-tab-title">' : '<div class="layui-tab payment-channel-layui-tabs" lay-filter="paymentChannelTabs"><ul class="layui-tab-title">';
            for (i = 0; i < channels.length; i++) {
                if (!defaultChannel || channels[i].is_default) {
                    defaultChannel = channels[i];
                }
                html += '<li class="J_payChannelCard" lay-id="' + escapeHtml(channels[i].code) + '" data-channel-code="' + escapeHtml(channels[i].code) + '">' + escapeHtml(channels[i].name) + '</li>';
            }
            html += '</ul><div class="layui-tab-content">';
            for (i = 0; i < channels.length; i++) {
                html += '<div class="layui-tab-item" data-channel-panel="' + escapeHtml(channels[i].code) + '"><div class="payment-channel-panel">';
                html += '<div class="payment-channel-head">';
                html += '<span data-lucide="badge-dollar-sign"></span>';
                html += '<div><strong>' + escapeHtml(channels[i].name) + '</strong><small>' + escapeHtml(channels[i].code) + '</small></div>';
                html += '</div>';
                html += '<div class="channel-meta-grid">';
                html += '<div class="channel-meta-item"><span>' + escapeHtml(t('front.exchange_rate')) + '</span><strong>' + escapeHtml(channels[i].exchange_rate) + '</strong></div>';
                if (channels[i].min_amount || channels[i].max_amount) {
                    html += '<div class="channel-meta-item"><span>' + escapeHtml(t('front.channel_min_max')) + '</span><strong>' + escapeHtml(channels[i].min_amount || 0) + ' - ' + escapeHtml(channels[i].max_amount || '-') + '</strong></div>';
                }
                if (channels[i].type || channels[i].type_label || channels[i].type_label_key) {
                    html += '<div class="channel-meta-item"><span>' + escapeHtml(t('front.channel_type')) + '</span><strong>' + escapeHtml(channelTypeLabel(channels[i])) + '</strong></div>';
                }
                html += '</div>';
                html += renderRemarkHtml(channels[i].remark_items, channels[i].description);
                html += '</div></div>';
            }
            html += useHostTab ? '</div>' : '</div></div>';

            $container.html(html);
            // 输出标准 Layui tab 结构后主动渲染一次，保证后续皮肤切换或
            // 异步通道刷新时，tab 标题和内容区域仍按 Layui 组件行为工作。
            if (window.layui && layui.element && layui.element.render) {
                layui.element.render('tab');
            }
            select(defaultChannel ? defaultChannel.code : channels[0].code);
        }

        function findChannel(code) {
            var i;

            for (i = 0; i < channels.length; i++) {
                if (String(channels[i].code) === String(code)) {
                    return channels[i];
                }
            }

            return null;
        }

        function channelTypeLabel(channel) {
            if (channel.type_label_key) {
                return t(channel.type_label_key);
            }
            if (channel.type_label) {
                return channel.type_label;
            }
            if (channel.type === 'crypto' || channel.type === 'fiat') {
                return t('front.channel_type_' + channel.type);
            }

            return channel.type;
        }

        function select(code) {
            var channel = findChannel(code);

            selectedCode = channel ? channel.code : '';
            $(opts.input).val(selectedCode);
            if (opts.payChannelInput) {
                $(opts.payChannelInput).val(selectedCode);
            }
            if (opts.passagewayInput) {
                $(opts.passagewayInput).val(channel ? channel.passageway : '');
            }
            $(opts.container).find('.J_payChannelCard').removeClass('is-active layui-this');
            $(opts.container).find('.layui-tab-item').removeClass('layui-show');
            if (selectedCode) {
                $(opts.container).find('[data-channel-code="' + selectedCode + '"]').addClass('is-active layui-this');
                $(opts.container).find('[data-channel-panel="' + selectedCode + '"]').addClass('layui-show');
            }
            syncAmount();
        }

        function syncAmount() {
            var channel = getSelected();
            var amount = Number($(opts.amountInput).val() || 0);
            var rate = channel ? Number(channel.exchange_rate || 1) : 1;
            var precision = channel ? Number(channel.precision || 2) : 2;

            $(opts.rateInput).val(channel ? rate : '');
            if (!amount || amount <= 0 || !channel) {
                $(opts.actualInput).val('');
                return;
            }

            $(opts.actualInput).val((amount * rate).toFixed(precision));
        }

        function getSelected() {
            return selectedCode ? findChannel(selectedCode) : null;
        }

        $(opts.container).on('click', '.J_payChannelCard', function () {
            select($(this).attr('data-channel-code'));
        });

        return {
            render: render,
            select: select,
            syncAmount: syncAmount,
            getSelected: getSelected
        };
    }

    return {
        create: create
    };
})();
