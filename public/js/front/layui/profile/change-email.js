layui.use(['form', 'layer', 'jquery'], function() {
    var form = layui.form, layer = layui.layer, $ = layui.jquery;
    
    // Initial UI i18n
    CrmLang.switchUI();

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
