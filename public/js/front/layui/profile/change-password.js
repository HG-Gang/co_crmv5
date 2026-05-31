layui.use(['form', 'layer', 'jquery'], function() {
    var form = layui.form, layer = layui.layer, $ = layui.jquery;
    
    // Initial UI i18n
    CrmLang.switchUI();

    form.verify({
        profileRequired: function(value, elem) {
            if (!$.trim(value || '')) {
                return requiredMessage(elem);
            }
        },
        password: function(value) {
            if (value.length < 6) return CrmLang.t('register.passwordRule');
        },
        confirmPass: function(value) {
            var pwd = $('#new_password').val();
            if (value !== pwd) return CrmLang.t('register.passwordMismatch');
        }
    });

    function translateOr(key, fallback) {
        var value = CrmLang.t(key);
        return value && value !== key ? value : fallback;
    }

    // 生成“当前表单 + 当前字段”的必填提示，避免修改密码页多个密码框提示不清楚。
    function requiredTemplateMessage(formTitle, fieldTitle) {
        var template = translateOr('front.profile_required_message', '请填写【{form}】的【{field}】');
        return template
            .replace('{form}', $.trim(formTitle || translateOr('profile.changePassword', '修改密码')))
            .replace('{field}', $.trim(fieldTitle || ''));
    }

    // 根据触发校验的输入框反查卡片标题和字段标签，让提示对应到用户刚点击提交的表单。
    function requiredMessage(elem) {
        var $elem = $(elem);
        var formTitle = $.trim($elem.closest('.layui-card').find('.layui-card-header').first().text()) || translateOr('profile.changePassword', '修改密码');
        var fieldTitle = $.trim($elem.closest('.layui-form-item').find('.layui-form-label').first().text()) || $elem.attr('name') || '';

        return requiredTemplateMessage(formTitle, fieldTitle);
    }

    form.on('submit(passwordSubmit)', function(data) {
        var loadIdx = layer.load(1);
        CrmAjax.request({
            guard: 'front',
            url: '/api/front/changePassword',
            method: 'POST',
            data: data.field,
            success: function(res) {
                layer.close(loadIdx);
                if (res.code === 1000 || res.code === 2000) {
                    layer.msg(CrmLang.t('profile.passwordChanged'), {icon: 1});
                    CrmAjax.removeToken('front');
                    setTimeout(function() {
                        window.location.href = '/front/login';
                    }, 1500);
                } else {
                    layer.msg(res.message || CrmLang.t('common.error'), {icon: 2});
                }
            },
            error: function() {
                layer.close(loadIdx);
                layer.msg(CrmLang.t('common.error'), {icon: 2});
            }
        });
        return false;
    });
});
