/**
 * 后台Layui公共模块 | Admin Layui Common Module
 */
layui.define(['jquery', 'layer'], function(exports) {
    var $ = layui.jquery;
    var layer = layui.layer;
    var langPackCache = {};

    var CRM = {
        /** 翻译函数 */
        t: function(key, params) {
            var data = (typeof LANG_DATA !== 'undefined') ? LANG_DATA : {};
            var text = data[key] || key;
            if (params) {
                for (var k in params) {
                    text = text.replace(':' + k, params[k]);
                }
            }
            return text;
        },

        /** Token 管理 */
        getToken: function() {
            // admin_token 是布局页 CrmAjax 使用的统一键名；admin_jwt_token 保留兼容旧页面。
            return localStorage.getItem('admin_token') || localStorage.getItem('admin_jwt_token');
        },
        setToken: function(token) {
            // 同时写入新旧键名，避免后台登录成功后布局页读不到令牌。
            localStorage.setItem('admin_token', token);
            localStorage.setItem('admin_jwt_token', token);
        },
        removeToken: function() {
            localStorage.removeItem('admin_token');
            localStorage.removeItem('admin_jwt_token');
        },

        /** AJAX封装 */
        ajax: function(opts) {
            var defaults = { type: 'POST', dataType: 'json', headers: {} };
            var token = CRM.getToken();
            if (token) {
                defaults.headers['Authorization'] = 'Bearer ' + token;
            }
            var settings = $.extend(true, defaults, opts);
            var origSuccess = settings.success;
            var origError = settings.error;

            settings.success = function(res) {
                if (res && (res.code === 4001 || res.code === 4002 || res.code === 4003 || res.code === 4004)) {
                    CRM.removeToken();
                    layer.msg(CRM.t('login_expired') || 'Session expired', {icon: 2});
                    setTimeout(function() { window.location.href = '/admin/login'; }, 1500);
                    return;
                }
                if (origSuccess) origSuccess(res);
            };
            settings.error = function(xhr) {
                if (xhr.status === 401) {
                    CRM.removeToken();
                    window.location.href = '/admin/login';
                    return;
                }
                if (origError) origError(xhr);
                else layer.msg(CRM.t('network_error'), {icon: 2});
            };
            return $.ajax(settings);
        },

        /** 应用翻译 */
        applyTranslations: function() {
            $('[data-translate]').each(function() {
                var key = $(this).data('translate');
                var text = CRM.t(key);
                if (text !== key) {
                    var tag = this.tagName.toLowerCase();
                    if (tag === 'input' || tag === 'textarea') {
                        $(this).attr('placeholder', text);
                    } else if (tag === 'option') {
                        $(this).text(text);
                    } else {
                        $(this).text(text);
                    }
                }
            });
            $('[data-translate-placeholder]').each(function() {
                var key = $(this).data('translate-placeholder');
                var text = CRM.t(key);
                if (text !== key) $(this).attr('placeholder', text);
            });
            $('[data-translate-title]').each(function() {
                var key = $(this).data('translate-title');
                var text = CRM.t(key);
                if (text !== key) $(this).attr('title', text);
            });

            // Re-render layui form components if form module is loaded
            if (layui.form) {
                layui.form.render();
            }
        },

        /** 切换语言 */
        switchLang: function(lang) {
            localStorage.setItem('admin_lang', lang);
            if (langPackCache[lang]) {
                LANG_DATA = langPackCache[lang];
                CRM.applyTranslations();
                return;
            }

            $.ajax({
                url: '/js/admin/i18n/' + lang + '.js?v=1',
                type: 'GET',
                dataType: 'text',
                cache: true,
                success: function(text) {
                    try {
                        eval(text);
                        langPackCache[lang] = (typeof LANG_DATA !== 'undefined') ? LANG_DATA : {};
                        CRM.applyTranslations();
                    } catch(e) {
                        console.error('Lang eval error:', e);
                    }
                },
                error: function() {
                    layer.msg(CRM.t('network_error'), {icon: 2});
                }
            });
        },

        /** 获取当前语言 */
        getLang: function() {
            return localStorage.getItem('admin_lang') || 'en';
        },

        /** 初始化语言 */
        initLang: function() {
            var savedLang = CRM.getLang();
            if (typeof LANG_DATA === 'undefined' || $.isEmptyObject(LANG_DATA)) {
                CRM.switchLang(savedLang);
                return;
            }
            CRM.applyTranslations();
        }
    };

    $(function() {
        CRM.initLang();
    });

    exports('common', CRM);
});
