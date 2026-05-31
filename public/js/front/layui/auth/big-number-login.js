/**
 * 大账户号登录页脚本。
 * 负责加载语言包、提交登录表单、保存前台 token，并在成功后进入控制台。
 */
layui.use(['form', 'layer', 'jquery'], function () {
    var form = layui.form;
    var layer = layui.layer;

    // 登录页也要先加载当前语言，确保失败提示和按钮文案一致。
    if (typeof CrmLang !== 'undefined') {
        CrmLang.loadLanguage(CrmLang.getLocale());
    }

    // 统一读取登录提示文案，语言模块不可用时保留 key 方便排查。
    function t(key) {
        return typeof CrmLang !== 'undefined' && CrmLang.t ? CrmLang.t(key) : key;
    }

    // 提交大账户号登录，成功后保存前台 token，失败时显示后端返回的明确原因。
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
