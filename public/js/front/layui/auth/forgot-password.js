layui.use(['form', 'layer', 'jquery'], function () {
    var form = layui.form;
    var layer = layui.layer;
    var $ = layui.jquery;

    if (typeof CrmLang !== 'undefined') {
        CrmLang.loadLanguage(CrmLang.getLocale());
    }

    form.on('submit(forgotSubmit)', function (data) {
        var loadIdx = layer.load(1);
        CrmAjax.request({
            url: '/api/front/forgotPasswordReset',
            data: data.field,
            success: function (res) {
                layer.close(loadIdx);
                if (res.code >= 1000 && res.code < 4000) {
                    layer.msg(res.message || CrmLang.t('auth.password_reset_success'), {icon: 1}, function () {
                        window.location.href = '/front/login';
                    });
                    return;
                }
                layer.msg(res.message || CrmLang.t('common.error'), {icon: 2});
            },
            error: function (res) {
                layer.close(loadIdx);
                layer.msg((res && res.message) || CrmLang.t('common.error'), {icon: 2});
            }
        });
        return false;
    });
});
