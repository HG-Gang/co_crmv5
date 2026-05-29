layui.use(['form', 'layer'], function() {
    var form = layui.form, layer = layui.layer, $ = layui.jquery;

    // Load profile
    CrmAjax.request({
        guard: 'admin',
        url: '/api/admin/profileInfo',
        success: function(res) {
            if (res.code === 1000) {
                form.val('profileForm', res.data);
            }
        }
    });

    // Update profile
    form.on('submit(updateProfile)', function(data) {
        CrmAjax.request({
            guard: 'admin',
            url: '/api/admin/updateProfile',
            data: data.field,
            success: function(res) {
                if (res.code === 1000) {
                    layer.msg(CrmLang.t('common.success'), {icon: 1});
                } else {
                    layer.msg(res.message || CrmLang.t('common.error'), {icon: 2});
                }
            }
        });
        return false;
    });
});
