layui.use(['form', 'layer'], function() {
    var form = layui.form, layer = layui.layer, $ = layui.jquery;

    form.on('submit(changePassword)', function(data) {
        if (data.field.new_password !== data.field.confirm_password) {
            layer.msg(CrmLang.t('register.passwordMismatch'), {icon: 2});
            return false;
        }

        CrmAjax.request({
            guard: 'admin',
            url: '/api/admin/changePassword',
            data: data.field,
            success: function(res) {
                if (res.code === 1000) {
                    layer.msg(CrmLang.t('profile.passwordChanged'), {icon: 1});
                    CrmAjax.removeToken('admin');
                    setTimeout(function() {
                        window.location.href = '/admin/login';
                    }, 1000);
                } else {
                    layer.msg(res.message || CrmLang.t('common.error'), {icon: 2});
                }
            }
        });
        return false;
    });
});
