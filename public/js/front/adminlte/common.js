/**
 * CRM Front Common JS
 * Contains global namespace, JWT handling, AJAX wrapper and translations.
 * 包含全局命名空间、JWT 处理、AJAX 封装和翻译功能。
 */
var CRM = CRM || {};

// Language data injected from server side (if any)
// 从服务器端注入的语言数据（如果有）
CRM.lang = (typeof LANG_DATA !== 'undefined') ? LANG_DATA : {};

/**
 * Translation function
 * @param {string} key
 * @param {object} params
 * @returns {string}
 */
CRM.t = function(key, params) {
    var text = CRM.lang[key] || key;
    if (params) {
        for (var k in params) {
            text = text.replace(':' + k, params[k]);
        }
    }
    return text;
};

// JWT Token operations
// JWT Token 操作
CRM.getToken = function() {
    return localStorage.getItem('front_token') || localStorage.getItem('front_jwt_token');
};

CRM.setToken = function(t) {
    localStorage.setItem('front_token', t);
    localStorage.setItem('front_jwt_token', t);
};

CRM.removeToken = function() {
    localStorage.removeItem('front_token');
    localStorage.removeItem('front_jwt_token');
};

/**
 * AJAX wrapper with automatic JWT header and 401 handling
 * 自动包含 JWT 请求头和 401 处理的 AJAX 封装
 */
CRM.ajax = function(opts) {
    var defaults = {
        type: 'POST',
        dataType: 'json',
        headers: {}
    };

    var token = CRM.getToken();
    if (token) {
        defaults.headers['Authorization'] = 'Bearer ' + token;
    }

    var settings = $.extend(true, defaults, opts);
    var origError = settings.error;

    settings.error = function(xhr) {
        // Handle Unauthorized or specific business logic codes for session expiration
        // 处理未授权或特定的会话过期业务逻辑代码
        if (xhr.status === 401 || (xhr.responseJSON && (xhr.responseJSON.code === 4001 || xhr.responseJSON.code === 4002 || xhr.responseJSON.code === 4003 || xhr.responseJSON.code === 4004))) {
            CRM.removeToken();
            window.location.href = '/front/login';
            return;
        }
        if (origError) {
            origError(xhr);
        }
    };

    return $.ajax(settings);
};

/**
 * Apply translations to elements with data-translate attribute
 * 将翻译应用到带有 data-translate 属性的元素
 */
CRM.applyTranslations = function() {
    $('[data-translate]').each(function() {
        var key = $(this).data('translate');
        var text = CRM.t(key);
        if (text !== key) {
            var tag = this.tagName.toLowerCase();
            if (tag === 'input' || tag === 'textarea') {
                $(this).attr('placeholder', text);
            } else {
                $(this).text(text);
            }
        }
    });
};

/**
 * Apply shared color mode to the legacy AdminLTE shell.
 * 将统一颜色模式应用到旧 AdminLTE 外壳。
 */
CRM.applyTheme = function(theme) {
    theme = (window.CrmTheme && CrmTheme.normalize ? CrmTheme.normalize(theme) : theme) || 'light';
    CURRENT_UI_STYLE = theme === 'dark' ? 'dark' : 'light';

    $('html').attr('data-front-theme', theme).attr('data-ui-style', CURRENT_UI_STYLE);
    $('body').toggleClass('dark-mode', theme === 'dark');
    $('.main-header')
        .toggleClass('navbar-dark', theme === 'dark')
        .toggleClass('navbar-white navbar-light', theme !== 'dark');
    $('#ui-style-switcher i')
        .toggleClass('fa-sun', theme === 'dark')
        .toggleClass('fa-moon', theme !== 'dark');
};

/**
 * Switch color mode and persist it in the shared theme storage.
 * 切换颜色模式并写入统一主题状态。
 */
CRM.switchStyle = function(style) {
    var theme = style === 'dark' ? 'dark' : 'light';

    if (window.CrmTheme) {
        theme = CrmTheme.set(theme);
    } else {
        localStorage.setItem('front_theme', theme);
        localStorage.setItem('crm_theme', theme);
        localStorage.setItem('crm_naive_skin', theme);
    }
    CRM.applyTheme(theme);
};

// Auto apply translations on DOM ready
// DOM 就绪后自动应用翻译
$(function() {
    CRM.applyTheme(window.CrmTheme ? CrmTheme.get() : (localStorage.getItem('front_theme') || CURRENT_UI_STYLE || 'light'));
    window.addEventListener('crm:theme-change', function(event) {
        if (event.detail && event.detail.theme) {
            CRM.applyTheme(event.detail.theme);
        }
    });
    CRM.applyTranslations();
});
