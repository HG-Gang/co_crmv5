layui.use(['form', 'layer', 'jquery'], function () {
    var form = layui.form;
    var layer = layui.layer;

    if (typeof CrmLang !== 'undefined') {
        CrmLang.loadLanguage(CrmLang.getLocale());
    }

    function t(key) {
        return typeof CrmLang !== 'undefined' && CrmLang.t ? CrmLang.t(key) : key;
    }

    form.on('submit(bigNumberLoginSubmit)', function (data) {
        var loadIdx = layer.load(1);
        CrmAjax.request({
            url: '/api/front/bigNumber/login',
            data: data.field,
            success: function (res) {
                layer.close(loadIdx);
                if (res.code === 1000 || res.code === 2000) {
                    CrmAjax.setToken('front', res.data.access_token);
                    window.location.href = '/front/dashboard';
                    return;
                }
                layer.msg(res.message || t('auth.loginFailed'), {icon: 2});
            },
            error: function (res) {
                layer.close(loadIdx);
                layer.msg((res && res.message) || t('common.error'), {icon: 2});
            }
        });
        return false;
    });
});
