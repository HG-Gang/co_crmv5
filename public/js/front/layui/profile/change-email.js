layui.use(['form', 'layer', 'jquery'], function() {
    var form = layui.form, layer = layui.layer, $ = layui.jquery;
    
    // Initial UI i18n
    CrmLang.switchUI();

    form.verify({
        profileRequired: function(value, elem) {
            if (!$.trim(value || '')) {
                return requiredMessage(elem);
            }
        }
    });

    function translateOr(key, fallback) {
        var value = CrmLang.t(key);
        return value && value !== key ? value : fallback;
    }

    // 生成带表单名和字段名的必填提示，让邮箱页提交时能准确指出缺少的新邮箱。
    function requiredTemplateMessage(formTitle, fieldTitle) {
        var template = translateOr('front.profile_required_message', '请填写【{form}】的【{field}】');
        return template
            .replace('{form}', $.trim(formTitle || translateOr('profile.changeEmail', '修改邮箱')))
            .replace('{field}', $.trim(fieldTitle || ''));
    }

    // 从当前输入框上溯卡片和标签，确保提示语对应用户点击的邮箱表单。
    function requiredMessage(elem) {
        var $elem = $(elem);
        var formTitle = $.trim($elem.closest('.layui-card').find('.layui-card-header').first().text()) || translateOr('profile.changeEmail', '修改邮箱');
        var fieldTitle = $.trim($elem.closest('.layui-form-item').find('.layui-form-label').first().text()) || $elem.attr('name') || '';

        return requiredTemplateMessage(formTitle, fieldTitle);
    }

    form.on('submit(emailSubmit)', function(data) {
        var loadIdx = layer.load(1);
        CrmAjax.request({
            guard: 'front',
            url: '/api/front/changeEmail',
            method: 'POST',
            data: data.field,
            success: function(res) {
                layer.close(loadIdx);
                if (res.code === 1000 || res.code === 2000) {
                    layer.msg(CrmLang.t('profile.emailChanged'), {icon: 1});
                    setTimeout(function() {
                        window.location.href = '/front/profile';
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
