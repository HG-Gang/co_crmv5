// Created by PhpStorm.
// Project name co_crmv5.
// User: Huang Gang
// Date: 2026/07/04
// Time: 17:09
/**
 * 后台 Layui 公共模块 | Admin Layui Common Module
 *
 * 功能逻辑说明：
 * - 兼容旧版后台 Layui 页面，集中提供路由生成、Token 读写、AJAX 请求封装和旧版 admin i18n 文案加载。
 * - 新后台布局主要使用 CrmAjax 与 CrmLang，本模块仍服务登录页和部分旧后台页面，因此接口名称保持不变。
 */
layui.define(['jquery', 'layer'], function(exports) {
    var $ = layui.jquery;
    var layer = layui.layer;
    var langPackCache = {};

    /**
     * 通过 PHP 导出的 Laravel 路由名称生成 URL。
     *
     * 参数含义：
     * - name：Laravel 命名路由，例如 admin_page_login；为空时可按 fallback 反查旧页面 URL。
     * - params：路由参数对象，传给 window.crmRoute 生成带参数的 URL。
     * - fallback：兼容未注入路由清单的旧页面路径，避免旧页面无法跳转。
     *
     * @param {string} name Laravel 命名路由。
     * @param {Object=} params 路由参数对象。
     * @param {string=} fallback 旧页面兜底 URL。
     * @returns {string} 可直接用于跳转或请求的 URL。
     */
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
         * 读取旧版后台语言包文案。
         *
         * 参数含义：
         * - key：LANG_DATA 中的文案 key，例如 login_expired、network_error。
         * - params：占位符替换对象，键名会替换文案里的 :key。
         *
         * @param {string} key 语言包 key。
         * @param {Object=} params 文案占位符参数。
         * @returns {string} 翻译后的文案；缺少 key 时返回 key 本身，便于暴露配置缺口。
         */
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

        /**
         * 读取后台 JWT Token。
         *
         * admin_token 是布局页 CrmAjax 使用的统一键名；admin_jwt_token 保留兼容旧页面。
         *
         * @returns {string|null} 当前后台登录 Token。
         */
        getToken: function() {
            return localStorage.getItem('admin_token') || localStorage.getItem('admin_jwt_token');
        },

        /**
         * 写入后台 JWT Token。
         *
         * @param {string} token 后台登录接口返回的 JWT 字符串。
         * @returns {void}
         */
        setToken: function(token) {
            // 同时写入新旧键名，避免后台登录成功后布局页读不到令牌。
            localStorage.setItem('admin_token', token);
            localStorage.setItem('admin_jwt_token', token);
        },

        /**
         * 清理后台 JWT Token。
         *
         * @returns {void}
         */
        removeToken: function() {
            localStorage.removeItem('admin_token');
            localStorage.removeItem('admin_jwt_token');
        },

        route: routeUrl,

        /**
         * 旧版后台 AJAX 请求封装。
         *
         * 参数含义：
         * - opts：jQuery.ajax 配置对象，业务页面可传 url、data、success、error 等字段。
         * - opts.success：业务成功回调；登录过期响应码会在进入该回调前被公共层拦截。
         * - opts.error：业务错误回调；未传时统一显示 network_error。
         *
         * 登录过期响应码会清理 Token 并跳回后台登录页，避免失效 Token 继续访问后台接口。
         *
         * @param {Object} opts jQuery.ajax 请求配置。
         * @returns {jqXHR} jQuery AJAX 请求对象。
         */
        ajax: function(opts) {
            opts = opts || {};
            var defaults = { type: 'POST', dataType: 'json', headers: {} };
            var token = CRM.getToken();
            var loginUrl = routeUrl('admin_page_login');
            if (token) {
                defaults.headers['Authorization'] = 'Bearer ' + token;
            }
            var settings = $.extend(true, defaults, opts);
            var origSuccess = settings.success;
            var origError = settings.error;

            settings.success = function(res) {
                if (res && (res.code === 4001 || res.code === 4002 || res.code === 4003 || res.code === 4004)) {
                    CRM.removeToken();
                    layer.msg(CRM.t('login_expired'), {icon: 2});
                    setTimeout(function() { window.location.href = loginUrl; }, 1500);
                    return;
                }
                if (origSuccess) origSuccess(res);
            };
            settings.error = function(xhr) {
                if (xhr.status === 401) {
                    CRM.removeToken();
                    window.location.href = loginUrl;
                    return;
                }
                if (origError) origError(xhr);
                else layer.msg(CRM.t('network_error'), {icon: 2});
            };
            return $.ajax(settings);
        },

        /**
         * 按 data-translate 属性应用旧版后台语言包。
         *
         * 逻辑说明：
         * - data-translate：替换元素文本；input/textarea 替换 placeholder。
         * - data-translate-placeholder：只替换 placeholder。
         * - data-translate-title：只替换 title。
         * - 翻译完成后重新渲染 Layui form，保证 select、checkbox 等组件显示最新文案。
         *
         * @returns {void}
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

            if (layui.form) {
                layui.form.render();
            }
        },

        /**
         * 从 public/js/apps/admin/i18n 加载旧版后台语言包。
         *
         * 参数含义：
         * - lang：语言标识，例如 zh-CN、en；会保存到 localStorage.admin_lang。
         *
         * @param {string} lang 目标语言标识。
         * @returns {void}
         */
        switchLang: function(lang) {
            localStorage.setItem('admin_lang', lang);
            if (langPackCache[lang]) {
                LANG_DATA = langPackCache[lang];
                CRM.applyTranslations();
                return;
            }

            $.ajax({
                url: '/js/apps/admin/i18n/' + lang + '.js?v=1',
                type: 'GET',
                dataType: 'text',
                cache: true,
                success: function(text) {
                    try {
                        eval(text);
                        langPackCache[lang] = (typeof LANG_DATA !== 'undefined') ? LANG_DATA : {};
                        CRM.applyTranslations();
                    } catch(e) {
                        layer.msg(CRM.t('network_error'), {icon: 2});
                    }
                },
                error: function() {
                    layer.msg(CRM.t('network_error'), {icon: 2});
                }
            });
        },

        /**
         * 获取当前后台语言标识。
         *
         * @returns {string} 当前语言标识，默认 en 以兼容旧后台初始行为。
         */
        getLang: function() {
            return localStorage.getItem('admin_lang') || 'en';
        },

        /**
         * 初始化旧版后台语言包。
         *
         * @returns {void}
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

    $(function() {
        CRM.initLang();
    });

    exports('common', CRM);
});
