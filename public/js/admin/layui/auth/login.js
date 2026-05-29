layui.config({
    base: '/js/admin/layui/'
}).use(['form', 'layer', 'jquery', 'common'], function() {
    var form = layui.form, 
        layer = layui.layer, 
        $ = layui.jquery,
        CRM = layui.common;

    form.on('submit(adminLogin)', function(data) {
        var loadIdx = layer.load(1);
        CRM.ajax({
            url: '/api/admin/login',
            data: { username: data.field.username, password: data.field.password },
            success: function(res) {
                layer.close(loadIdx);
                if (res.code === 1000) {
                    CRM.setToken(res.data.access_token);
                    layer.msg(CRM.t('login_success'), {icon: 1});
                    setTimeout(function() { window.location.href = '/admin/dashboard'; }, 500);
                } else {
                    layer.msg(res.message || CRM.t('login_failed'), {icon: 2});
                }
            },
            error: function() { 
                layer.close(loadIdx);
                layer.msg(CRM.t('network_error'), {icon: 2}); 
            }
        });
        return false;
    });
    
    // Language switch
    $('.lang-switch').on('click', function() {
        var lang = $(this).data('lang');
        CRM.switchLang(lang);
    });
});
