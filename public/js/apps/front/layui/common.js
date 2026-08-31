// Created by PhpStorm.
// Project name co_crmv5.
// User: Huang Gang
// Date: 2026/07/04
// Time: 17:09
/**
 * 前台 Layui 公共模块。
 * 
 * 维护 CRM 全局命名空间，提供翻译、令牌、AJAX、语言切换等公共能力。
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

    // 通过 PHP 导出的 Laravel 路由名称生成 URL，fallback 仅用于兼容未注入路由清单的旧页面。
    function routeUrl(name, params, fallback) {
        if (name && window.crmRoute) {
            return window.crmRoute(name, params || {}, fallback || '');
        }
        if (!name && fallback && window.crmRouteFromUrl) {
            return window.crmRouteFromUrl(fallback, fallback || '');
        }

        return fallback || '';
    }

    var CRM = {
        /**
         * 翻译函数。
         * @param {string} key 翻译键。
         * @param {object} params 替换参数。
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

        /** JWT 令牌管理。 */
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
        route: routeUrl,

        /**
         * AJAX 封装，自动携带 JWT 请求头。
         */
        ajax: function(opts) {
            opts = opts || {};
            var defaults = { type: 'POST', dataType: 'json', headers: {} };
            var token = CRM.getToken();
            var attachToken = opts.auth !== false;
            var handleAuthRedirect = opts.authRedirect !== false;
            var loginUrl = routeUrl('front_page_login');

            if (token && attachToken) {
                defaults.headers['Authorization'] = 'Bearer ' + token;
            }
            var settings = $.extend(true, defaults, opts);
            var origSuccess = settings.success;
            var origError = settings.error;
            var maskEnabled = window.CrmAjax && CrmAjax.showGlobalMask ? CrmAjax.showGlobalMask(settings) : false;
            settings.__crmMaskManaged = true;

            settings.success = function(res) {
                // 单点登录互踢或登录态失效时清理令牌并跳回登录页。
                if (handleAuthRedirect && res && (res.code === 4001 || res.code === 4002 || res.code === 4003 || res.code === 4004)) {
                    CRM.removeToken();
                    layer.msg(CRM.t('login_expired') || 'Session expired', {icon: 2});
                    setTimeout(function() { window.location.href = loginUrl; }, 1500);
                    return;
                }
                if (origSuccess) origSuccess(res);
            };
            settings.error = function(xhr) {
                if (handleAuthRedirect && xhr.status === 401) {
                    CRM.removeToken();
                    window.location.href = loginUrl;
                    return;
                }
                if (origError) origError(xhr);
                else layer.msg(CRM.t('network_error'), {icon: 2});
            };
            return $.ajax(settings).always(function () {
                if (window.CrmAjax && CrmAjax.hideGlobalMask) {
                    CrmAjax.hideGlobalMask(maskEnabled);
                }
            });
        },

        /**
         * 应用 data-translate 翻译。
         * 支持 data-translate 文本、data-translate-placeholder 和 data-translate-title。
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

            // 如果 form 模块已经加载，重新渲染 Layui 表单组件。
            if (layui.form) {
                layui.form.render();
            }
        },

        /**
         * JS 动态切换语言。
         * 使用XHR按需获取语言文件并缓存，不创建script标签，不刷新页面，也不重复加载同一个语言文件。
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
                url: '/js/apps/front/i18n/' + lang + '.js?v=2026060403',
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
                        layer.msg(CRM.t('network_error'), {icon: 2});
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
