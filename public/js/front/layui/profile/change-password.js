layui.use(['form', 'layer', 'jquery'], function() {
    var form = layui.form, layer = layui.layer, $ = layui.jquery;
    
    // Initial UI i18n
    CrmLang.switchUI();

    form.verify({
        password: function(value) {
            if (value.length < 6) return CrmLang.t('register.passwordRule');
        },
        confirmPass: function(value) {
            var pwd = $('#new_password').val();
            if (value !== pwd) return CrmLang.t('register.passwordMismatch');
        }
    });

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
