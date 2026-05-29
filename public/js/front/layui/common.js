/**
 * 前台Layui公共模块 | Front Layui Common Module
 * 
 * 全局命名空间 CRM (不使用 window.i18n)
 * Global namespace CRM (not using window.i18n)
 */
layui.define(['jquery', 'layer'], function(exports) {
    var $ = layui.jquery;
    var layer = layui.layer;
    var langPackCache = {};
    var fallbackTranslations = {
        'zh-CN': {
            login_success: '登录成功',
            login_sucess: '登录成功',
            login_failed: '登录失败',
            login_expired: '登录已过期，请重新登录',
            account_required: '请输入邮箱或用户ID',
            password_required: '请输入密码',
            network_error: '网络错误，请稍后重试',
            server_error: '服务器错误'
        },
        en: {
            login_success: 'Login successful',
            login_sucess: 'Login successful',
            login_failed: 'Login failed',
            login_expired: 'Session expired, please login again',
            account_required: 'Please enter email or user ID',
            password_required: 'Please enter password',
            network_error: 'Network error, please try again',
            server_error: 'Server error'
        }
    };

    function lookupTranslation(source, key) {
        var parts;
        var result;
        var i;

        if (!source || !key) {
            return null;
        }
        if (typeof source[key] === 'string') {
            return source[key];
        }

        parts = String(key).split('.');
        result = source;
        for (i = 0; i < parts.length; i++) {
            if (result && typeof result === 'object' && Object.prototype.hasOwnProperty.call(result, parts[i])) {
                result = result[parts[i]];
            } else {
                return null;
            }
        }

        return typeof result === 'string' ? result : null;
    }

    var CRM = {
        /**
         * 翻译函数 | Translation function
         * @param {string} key - 翻译键 | Translation key
         * @param {object} params - 替换参数 | Replacement params
         * @returns {string}
         */
        t: function(key, params) {
            var data = (typeof LANG_DATA !== 'undefined') ? LANG_DATA : {};
            var lang = CRM.getLang();
            var fallback = fallbackTranslations[lang] || fallbackTranslations.en || {};
            var text = lookupTranslation(data, key) || lookupTranslation(fallback, key) || key;
            if (params) {
                for (var k in params) {
                    text = text.replace(':' + k, params[k]);
                }
            }
            return text;
        },

        message: function(value, fallbackKey) {
            var translated;

            if (value) {
                translated = CRM.t(value);
                if (translated !== value) {
                    return translated;
                }
                return value;
            }

            return fallbackKey ? CRM.t(fallbackKey) : '';
        },

        /** JWT Token 管理 | JWT Token Management */
        getToken: function() {
            // front_token 是布局页 CrmAjax 使用的统一键名；front_jwt_token 保留兼容旧页面。
            return localStorage.getItem('front_token') || localStorage.getItem('front_jwt_token');
        },
        setToken: function(token) {
            // 同时写入新旧键名，避免登录页与布局页读取不一致导致登录后被重定向回登录页。
            localStorage.setItem('front_token', token);
            localStorage.setItem('front_jwt_token', token);
        },
        removeToken: function() {
            localStorage.removeItem('front_token');
            localStorage.removeItem('front_jwt_token');
        },

        /**
         * AJAX封装(自动带JWT头) | AJAX wrapper (auto JWT header)
         */
        ajax: function(opts) {
            var defaults = { type: 'POST', dataType: 'json', headers: {} };
            var token = CRM.getToken();
            var attachToken = opts.auth !== false;
            var handleAuthRedirect = opts.authRedirect !== false;

            if (token && attachToken) {
                defaults.headers['Authorization'] = 'Bearer ' + token;
            }
            var settings = $.extend(true, defaults, opts);
            var origSuccess = settings.success;
            var origError = settings.error;

            settings.success = function(res) {
                // SSO被踢出 | SSO conflict
                if (handleAuthRedirect && res && (res.code === 4001 || res.code === 4002 || res.code === 4003 || res.code === 4004)) {
                    CRM.removeToken();
                    layer.msg(CRM.t('login_expired') || 'Session expired', {icon: 2});
                    setTimeout(function() { window.location.href = '/front/login'; }, 1500);
                    return;
                }
                if (origSuccess) origSuccess(res);
            };
            settings.error = function(xhr) {
                if (handleAuthRedirect && xhr.status === 401) {
                    CRM.removeToken();
                    window.location.href = '/front/login';
                    return;
                }
                if (origError) origError(xhr);
                else layer.msg(CRM.t('network_error'), {icon: 2});
            };
            return $.ajax(settings);
        },

        /**
         * 应用data-translate翻译 | Apply data-translate translations
         * 支持: data-translate(文本), data-translate-placeholder, data-translate-title
         */
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

        /**
         * JS动态切换语言 | JS dynamic language switch
         * 使用XHR按需获取语言文件并缓存，不创建script标签，不刷新页面，也不重复加载同一个语言文件。
         * Uses XHR with cache, no script tag, no page reload, no repeated language JS fetch.
         */
        switchLang: function(lang) {
            localStorage.setItem('front_lang', lang);
            localStorage.setItem('crm_locale', lang);

            function activate(data) {
                window.LANG_DATA = data || {};
                if (typeof LANG_DATA !== 'undefined') {
                    LANG_DATA = window.LANG_DATA;
                }
                CRM.applyTranslations();
            }

            if (langPackCache[lang]) {
                activate(langPackCache[lang]);
                return;
            }

            $.ajax({
                url: '/js/front/i18n/' + lang + '.js?v=1',
                type: 'GET',
                dataType: 'text',
                cache: true,
                success: function(text) {
                    var previous = window.LANG_DATA;
                    var loaded;
                    try {
                        window.LANG_DATA = {};
                        loaded = (new Function(text + '; return (typeof LANG_DATA !== "undefined" ? LANG_DATA : window.LANG_DATA);')).call(window);
                        langPackCache[lang] = loaded || window.LANG_DATA || {};
                        activate(langPackCache[lang]);
                    } catch(e) {
                        window.LANG_DATA = previous || {};
                        console.error('Lang eval error:', e);
                    }
                },
                error: function() {
                    layer.msg(CRM.t('network_error'), {icon: 2});
                }
            });
        },

        /**
         * 获取当前语言 | Get current language
         */
        getLang: function() {
            return localStorage.getItem('front_lang') || localStorage.getItem('crm_locale') || 'zh-CN';
        },

        /**
         * UI风格切换 | UI style switch
         */
        switchStyle: function(style) {
            localStorage.setItem('front_ui_style', style);
            $('html').attr('data-ui-style', style);
        },

        /**
         * 初始化语言 | Initialize language
         * 检查localStorage中的语言偏好,如果和当前加载的不同则重新加载
         */
        /**
         * 初始化语言 | Initialize language
         * 页面加载时检查LANG_DATA是否已通过同步XHR加载,直接应用翻译即可
         * On page load, LANG_DATA is already loaded via sync XHR, just apply translations
         */
        initLang: function() {
            var savedLang = CRM.getLang();
            if (typeof LANG_DATA === 'undefined' || $.isEmptyObject(LANG_DATA)) {
                CRM.switchLang(savedLang);
                return;
            }
            CRM.applyTranslations();
        }
    };

    // 页面加载时初始化语言 | Initialize language on page load
    $(function() {
        CRM.initLang();
    });

    // 导出模块 | Export module
    exports('common', CRM);
});
