/**
 * Payment channel UI manager.
 *
 * This is the new Layui/jQuery version of the old payChannelManager idea:
 * channel data comes from the API, this manager sorts/renders it, tracks the
 * selected channel, and recalculates the payable amount whenever the amount or
 * channel changes.
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
                    description: item.description || ''
                };
            }).sort(function (a, b) {
                return b.sort - a.sort;
            });
        }

        function render(rawChannels) {
            var $container = $(opts.container);
            var html = '';
            var i;
            var defaultChannel = null;

            channels = normalize(rawChannels);
            if (!$container.length) {
                return;
            }

            if (!channels.length) {
                $container.html('<div class="layui-col-md12"><div class="layui-elem-quote">' + escapeHtml(t('front.no_payment_channel')) + '</div></div>');
                select('');
                return;
            }

            for (i = 0; i < channels.length; i++) {
                if (!defaultChannel || channels[i].is_default) {
                    defaultChannel = channels[i];
                }

                html += '<div class="layui-col-md2 layui-col-sm3 layui-col-xs6">';
                html += '<div class="deposit-channel-card J_payChannelCard" data-channel-code="' + escapeHtml(channels[i].code) + '">';
                html += '<div class="layui-form-item">';
                html += '<input type="radio" name="deposit_channel_radio" value="' + escapeHtml(channels[i].code) + '" lay-filter="depositChannel" title="' + escapeHtml(channels[i].name) + '">';
                html += '</div>';
                html += '<div class="channel-meta"><span class="channel-rate">' + escapeHtml(t('front.exchange_rate')) + ': ' + escapeHtml(channels[i].exchange_rate) + '</span></div>';
                if (channels[i].min_amount || channels[i].max_amount) {
                    html += '<div class="channel-meta">' + escapeHtml(t('front.channel_min_max')) + ': ' + escapeHtml(channels[i].min_amount || 0) + ' - ' + escapeHtml(channels[i].max_amount || '-') + '</div>';
                }
                if (channels[i].type || channels[i].type_label || channels[i].type_label_key) {
                    html += '<div class="channel-meta">' + escapeHtml(t('front.channel_type')) + ': ' + escapeHtml(channelTypeLabel(channels[i])) + '</div>';
                }
                if (channels[i].description) {
                    html += '<div class="channel-meta">' + escapeHtml(channels[i].description) + '</div>';
                }
                html += '</div></div>';
            }

            $container.html(html);
            if (layui.form) {
                layui.form.render('radio');
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
            $(opts.container).find('.J_payChannelCard').removeClass('is-active');
            if (selectedCode) {
                $(opts.container).find('[data-channel-code="' + selectedCode + '"]').addClass('is-active');
                $(opts.container).find('input[name="deposit_channel_radio"][value="' + selectedCode + '"]').prop('checked', true);
            }
            if (layui.form) {
                layui.form.render('radio');
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

        if (layui.form) {
            layui.form.on('radio(depositChannel)', function (data) {
                select(data.value);
            });
        }

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
