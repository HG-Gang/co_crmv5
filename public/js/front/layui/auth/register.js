layui.config({
    base: '/js/front/layui/'
}).use(['form', 'layer', 'jquery', 'common'], function() {
    var form = layui.form, 
        layer = layui.layer, 
        $ = layui.jquery,
        CRM = layui.common;
    
    var urlInviterId = $('#inviterIdFromUrl').val();
    var accountTypeFromUrl = queryValue('account_type');
    var emailCodeTimer = null;

    if (urlInviterId) {
        $('#inviterId').val(urlInviterId);
        validateInviter(urlInviterId);
    }
    if (accountTypeFromUrl === '1' || accountTypeFromUrl === '2') {
        $('input[name="account_type"][value="' + accountTypeFromUrl + '"]').prop('checked', true);
        form.render('radio');
    }
    refreshCaptcha();
    updateTermsLinks();
    
    form.on('radio(accountType)', function(data) {
        if (data.value === '2') {
            $('#inviterId').attr('lay-verify', 'required');
        } else {
            $('#inviterId').removeAttr('lay-verify');
        }
        form.render();
    });
    
    $('#inviterId').on('blur', function() {
        var inviterId = $(this).val().trim();
        if (inviterId) validateInviter(inviterId);
    });
    
    function validateInviter(inviterId) {
        $('#inviterInfo').text(CRM.t('loading')).css('color', '#666').show();
        
        CRM.ajax({
            url: '/api/front/validateInviter',
            auth: false,
            authRedirect: false,
            data: { inviter_id: inviterId },
            success: function(res) {
                if (res.code === 1000 || res.code === 2000) {
                    $('#inviterInfo').text(res.data.inviter_name || CRM.t('invitation_invalid')).css('color', 'green').show();
                } else {
                    $('#inviterInfo').text(res.message || CRM.t('invitation_invalid')).css('color', 'red').show();
                }
            },
            error: function() {
                $('#inviterInfo').text(CRM.t('network_error')).css('color', 'red').show();
            }
        });
    }

    function queryValue(name) {
        var params = new URLSearchParams(window.location.search || '');

        return params.get(name) || '';
    }
    
    form.verify({
        email: function(value) {
            if (!/^[\w.-]+@[\w.-]+\.\w+$/.test(value)) return CRM.t('email_invalid');
        },
        password: function(value) {
            if (value.length < 6) return CRM.t('password_min');
        },
        confirmPass: function(value) {
            var pwd = $('input[name=password]').val();
            if (value !== pwd) return CRM.t('password_confirm');
        },
        phoneNumber: function(value) {
            if (!/^[0-9]{12,20}$/.test(value)) return CRM.t('phone_invalid');
        },
        idCardNo: function(value) {
            if ($.trim(value).length < 4) return CRM.t('id_card_required');
        },
        emailCode: function(value) {
            if (!/^[0-9]{4,8}$/.test(value)) return CRM.t('email_code_required');
        },
        captchaCode: function(value) {
            if ($.trim(value).length < 4) return CRM.t('captcha_required');
        }
    });

    $('#sendEmailCode').on('click', function() {
        if ($(this).hasClass('layui-btn-disabled')) {
            return;
        }
        sendEmailCode();
    });

    $('#refreshCaptcha, #registerCaptchaImg').on('click', function() {
        refreshCaptcha();
    });
    
    form.on('submit(registerSubmit)', function(data) {
        var payload = $.extend({}, data.field, {
            commission_mode: queryValue('commission_mode') || queryValue('comm_type') || ''
        });
        var loadIdx = layer.load(1);
        CRM.ajax({
            url: '/api/front/register',
            auth: false,
            authRedirect: false,
            data: payload,
            success: function(res) {
                layer.close(loadIdx);
                if (res.code === 1000 || res.code === 2000) {
                    layer.msg(CRM.message(res.message, 'register_success'), {icon: 1});
                    if (res.data && res.data.access_token) {
                        CRM.setToken(res.data.access_token);
                    }
                    setTimeout(function() {
                        window.location.href = '/front/dashboard';
                    }, 1000);
                } else {
                    layer.msg(CRM.message(res.message, 'network_error'), {icon: 2});
                    refreshCaptcha();
                }
            },
            error: function() {
                layer.close(loadIdx);
                layer.msg(CRM.t('network_error'), {icon: 2});
                refreshCaptcha();
            }
        });
        return false;
    });
    
    $('.lang-switch').on('click', function() {
        var lang = $(this).data('lang');
        CRM.switchLang(lang);
        updateTermsLinks(lang);
    });

    function sendEmailCode() {
        var data = form.val('registerForm');
        var payload = $.extend({}, data, {
            commission_mode: queryValue('commission_mode') || queryValue('comm_type') || ''
        });

        if (!payload.email || !/^[\w.-]+@[\w.-]+\.\w+$/.test(payload.email)) {
            layer.msg(CRM.t('email_invalid'), {icon: 2});
            return;
        }
        if (!payload.phone_code || !payload.phone_number) {
            layer.msg(CRM.t('phone_required'), {icon: 2});
            return;
        }
        if (!payload.id_card_no) {
            layer.msg(CRM.t('id_card_required'), {icon: 2});
            return;
        }

        CRM.ajax({
            url: '/api/front/registerSendCode',
            auth: false,
            authRedirect: false,
            data: payload,
            success: function(res) {
                if (res.code === 1000 || res.code === 2000) {
                    layer.msg(CRM.t('email_code_sent'), {icon: 1});
                    startEmailCodeCountdown();
                    return;
                }
                layer.msg(CRM.message(res.message, 'network_error'), {icon: 2});
            },
            error: function() {
                layer.msg(CRM.t('network_error'), {icon: 2});
            }
        });
    }

    function startEmailCodeCountdown() {
        var seconds = 60;
        var $button = $('#sendEmailCode');

        clearInterval(emailCodeTimer);
        $button.addClass('layui-btn-disabled').prop('disabled', true);
        emailCodeTimer = setInterval(function() {
            seconds--;
            if (seconds <= 0) {
                clearInterval(emailCodeTimer);
                $button.removeClass('layui-btn-disabled').prop('disabled', false).text(CRM.t('send_email_code'));
                return;
            }
            $button.text(seconds + 's');
        }, 1000);
        $button.text(seconds + 's');
    }

    function refreshCaptcha() {
        var key = Date.now().toString(36) + Math.random().toString(36).slice(2);
        $('#captchaKey').val(key);
        $('#registerCaptchaImg').attr('src', '/api/front/registerCaptcha?key=' + encodeURIComponent(key) + '&_=' + Date.now());
    }

    function updateTermsLinks(lang) {
        var current = lang || CRM.getLang();
        $('.register-terms-links a').each(function() {
            var $link = $(this);
            $link.attr('href', current === 'en' ? $link.attr('data-en-href') : $link.attr('data-zh-href'));
        });
    }
});
