/**
 * 前台登录页JS | Front Login Page JS
 *
 * 功能 | Features:
 * - 后端自动识别 Email 或 UserID | Backend auto-detects Email / UserID
 * - 纯JS语言切换(不刷新页面) | Pure JS language switch (no reload)
 * - 通过 layui.use 加载 common 模块 | Load common module via layui.use
 */
layui.config({
    base: '/js/front/layui/',   // common.js 所在目录 | Directory of common.js
    version: '20260527-login-fix'
}).use(['form', 'layer', 'jquery', 'common'], function () {
    var form   = layui.form;       // 表单模块 | Form module
    var layer  = layui.layer;      // 弹层模块 | Layer module
    var $      = layui.jquery;     // jQuery (layui自带) | jQuery (built-in)
    var CRM    = layui.common;     // 公共模块(CRM命名空间) | Common module

    // =========================================================
    // 1. 表单提交 | Form submit
    // =========================================================
    form.on('submit(doLogin)', function (formData) {
        var pwd = formData.field.password;
        if (!pwd) {
            layer.msg(CRM.t('password_required'), { icon: 2 });
            return false;
        }

        // 构造请求数据，后端自动判断账号是 email 还是 user_id | Backend auto-detects account type
        var account = formData.field.account;
        if (!account) {
            layer.msg(CRM.t('account_required'), { icon: 2 });
            return false;
        }
        var postData = { account: account, password: pwd };

        // 显示加载遮罩 | Show loading
        var loadIdx = layer.load(1);

        CRM.ajax({
            url: '/api/front/login',
            auth: false,
            authRedirect: false,
            data: postData,
            success: function (res) {
                layer.close(loadIdx);
                if (res.code === 1000 || res.code === 2000) {
                    CRM.setToken(res.data.access_token);
                    layer.msg(CRM.message(res.message, 'login_success'), { icon: 1 });
                    setTimeout(function () {
                        var dashboardUrl = '/front/dashboard?login_at=' + Date.now();
                        if (window.top && window.top !== window) {
                            window.top.location.replace(dashboardUrl);
                            return;
                        }
                        window.location.replace(dashboardUrl);
                    }, 250);
                } else {
                    // 服务端返回的错误消息 | Server error message
                    layer.msg(CRM.message(res.message, 'login_failed'), { icon: 2 });
                }
            },
            error: function () {
                layer.close(loadIdx);
                layer.msg(CRM.t('network_error'), { icon: 2 });
            }
        });
        return false;   // 阻止表单默认提交 | Prevent default form submit
    });

    // =========================================================
    // 2. 纯JS语言切换(不刷新页面,不重新加载JS) | Pure JS language switch
    // =========================================================
    $('.J_langSwitch').on('click', function () {
        var lang = $(this).data('lang');
        // switchLang 内部使用 XHR 加载新语言文件并 eval,
        // 然后调用 applyTranslations 更新所有 data-translate 元素
        // 不创建新 script 标签, 不刷新页面
        CRM.switchLang(lang);
    });

    CRM.initLang();
});
