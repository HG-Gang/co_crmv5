/**
 * 找回密码重置页脚本。
 * 负责提交重置表单，并在密码重置成功后引导用户返回登录页。
 */
layui.use(['form', 'layer', 'jquery'], function () {
    var form = layui.form;
    var layer = layui.layer;
    var $ = layui.jquery;

    // 先加载语言包，避免异步提交回调里出现未翻译的提示。
    if (typeof CrmLang !== 'undefined') {
        CrmLang.loadLanguage(CrmLang.getLocale());
    }

    // 提交忘记密码表单，成功后跳回登录页，失败保留接口返回提示。
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
