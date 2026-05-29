layui.use(['form', 'layer', 'jquery'], function() {
    var form = layui.form, layer = layui.layer, $ = layui.jquery;

    // Initial UI i18n
    CrmLang.switchUI();

    var userId = $('#user-id').val();
    if (userId) {
        CrmAjax.request({
            guard: 'admin',
            url: '/api/admin/userDetail',
            method: 'POST',
            data: {user_id: userId},
            success: function(res) {
                if (res.code === 1000 || res.code === 1002) {
                    // 后台用户详情接口返回 user_infos，并带 login/auth 关系；表单字段按实际表结构映射。
                    var user = res.data || {};
                    var login = user.login || {};
                    form.val('user-form', {
                        user_id: user.user_id,
                        user_name: user.user_name,
                        email: login.email || '',
                        phone: user.phone || '',
                        status: login.is_enabled == 1 ? '1' : '0'
                    });
                    form.render();
                    CrmLang.switchUI();
                }
            }
        });
    }

    form.on('submit(user-save)', function(data) {
        var fields = data.field;
        var status = fields.status;
        CrmAjax.request({
            guard: 'admin',
            url: '/api/admin/updateUser',
            method: 'POST',
            data: {
                user_id: fields.user_id,
                user_name: fields.user_name,
                phone: fields.phone
            },
            success: function(res) {
                if (res.code === 1000) {
                    // 登录启用状态存放在 user_logins，资料信息更新后单独同步状态。
                    CrmAjax.request({
                        guard: 'admin',
                        url: '/api/admin/changeUserStatus',
                        data: {user_id: fields.user_id, is_enabled: status},
                        success: function() {
                            layer.msg(CrmLang.t('common.success'), {icon: 1}, function() {
                                window.location.href = '/admin/users';
                            });
                        }
                    });
                } else {
                    layer.msg(res.message || CrmLang.t('common.error'), {icon: 2});
                }
            }
        });
        return false;
    });

    $('#cancel-btn').on('click', function() {
        window.history.back();
    });
});
