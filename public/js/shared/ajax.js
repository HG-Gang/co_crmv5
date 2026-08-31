// Created by PhpStorm.
// Project name co_crmv5.
// User: Huang Gang
// Date: 2026/08/28
// Time: 01:06
/**
 * CRM 前后台统一 Ajax 工具。
 * 负责 token 读写、语言头、外部地址拦截、登录失效处理和普通上传请求。
 */
var CrmAjax = (function() {
    'use strict';

    var activeMaskCount = 0;
    var maskNode = null;
    var defaultMaskConfig = {
        style: 'spinner',
        text: '',
        image: '',
        gif: ''
    };
    var allowedMaskStyles = ['spinner', 'minimal', 'dots', 'bar', 'card', 'gif'];

    function normalizeMaskConfig(config) {
        var normalized = Object.assign({}, defaultMaskConfig, config || {});
        normalized.style = String(normalized.style || 'spinner').toLowerCase();

        if ((normalized.gif || normalized.image) && (!config || !config.style)) {
            normalized.style = 'gif';
        }
        if (allowedMaskStyles.indexOf(normalized.style) === -1) {
            normalized.style = 'spinner';
        }

        return normalized;
    }

    function maskConfig() {
        return normalizeMaskConfig(window.CrmAjaxMaskConfig || {});
    }

    function configureMask(config) {
        window.CrmAjaxMaskConfig = normalizeMaskConfig(Object.assign({}, maskConfig(), config || {}));
        if (maskNode) {
            ensureGlobalMask();
        }

        return window.CrmAjaxMaskConfig;
    }

    function ensureGlobalMask() {
        var config = maskConfig();
        var text = config.text || (window.CrmLang && CrmLang.t ? CrmLang.t('common.loading') : 'Loading...');
        var style = config.style || 'spinner';
        var image = config.gif || config.image || '';
        var visible = maskNode && maskNode.classList.contains('is-visible');

        if (!maskNode) {
            maskNode = document.createElement('div');
            maskNode.className = 'crm-ajax-mask';
            maskNode.innerHTML = '<div class="crm-ajax-mask-box"><span class="crm-ajax-mask-spinner"></span><img class="crm-ajax-mask-gif" alt=""><em class="crm-ajax-mask-text"></em></div>';
            document.body.appendChild(maskNode);
        }

        maskNode.className = 'crm-ajax-mask crm-ajax-mask-style-' + style + (image ? ' has-gif' : '');
        if (visible) { maskNode.classList.add('is-visible'); }
        maskNode.querySelector('.crm-ajax-mask-text').textContent = text;
        maskNode.querySelector('.crm-ajax-mask-gif').src = image || '';
        maskNode.querySelector('.crm-ajax-mask-box').setAttribute('data-style', style);

        return maskNode;
    }

    function showGlobalMask(opts) {
        if (opts && opts.mask === false) {
            return false;
        }

        activeMaskCount += 1;
        ensureGlobalMask().classList.add('is-visible');

        return true;
    }

    function hideGlobalMask(enabled) {
        if (!enabled) {
            return;
        }

        activeMaskCount = Math.max(0, activeMaskCount - 1);
        if (activeMaskCount === 0 && maskNode) {
            maskNode.classList.remove('is-visible');
        }
    }

    function installJqueryGlobalMask() {
        if (!window.jQuery || window.__crmAjaxGlobalMaskInstalled) {
            return;
        }

        window.__crmAjaxGlobalMaskInstalled = true;
        $(document).ajaxSend(function (_event, _xhr, settings) {
            settings = settings || {};
            if (settings.__crmMaskManaged) {
                return;
            }
            settings.__crmMaskEnabled = showGlobalMask(settings);
        });
        $(document).ajaxComplete(function (_event, _xhr, settings) {
            settings = settings || {};
            if (settings.__crmMaskManaged) {
                return;
            }
            hideGlobalMask(settings.__crmMaskEnabled);
            settings.__crmMaskEnabled = false;
        });
    }

    // 后端 API 地址必须由业务 JS 直接传入清晰的 /api/... URL。
    function resolveUrl(opts, fallback) {
        return (opts && opts.url) || fallback || '';
    }

    // 根据 guard 读取前台或后台 token，同时兼容旧 key 和新 key。
    function getToken(guard) {
        if (guard === 'admin') {
            return localStorage.getItem('admin_token') || localStorage.getItem('admin_jwt_token');
        }

        return localStorage.getItem('front_token') || localStorage.getItem('front_jwt_token');
    }

    // 登录成功后同时写入新旧 token key，避免不同页面脚本读取不到登录态。
    function setToken(guard, token) {
        if (guard === 'admin') {
            localStorage.setItem('admin_token', token);
            localStorage.setItem('admin_jwt_token', token);
            return;
        }

        localStorage.setItem('front_token', token);
        localStorage.setItem('front_jwt_token', token);
    }

    // token 失效或退出登录时清理对应 guard 的所有兼容 key。
    function removeToken(guard) {
        if (guard === 'admin') {
            localStorage.removeItem('admin_token');
            localStorage.removeItem('admin_jwt_token');
            return;
        }

        localStorage.removeItem('front_token');
        localStorage.removeItem('front_jwt_token');
    }

    // 只允许请求当前站点接口，阻止配置错误导致的外部地址请求。
    function appendQuery(url, data) {
        var query = [];

        if (!data || typeof data !== 'object' || data instanceof FormData) {
            return url;
        }
        Object.keys(data).forEach(function (key) {
            var value = data[key];

            if (value === undefined || value === null || value === '') {
                return;
            }
            query.push(encodeURIComponent(key) + '=' + encodeURIComponent(String(value)));
        });

        return query.length ? url + (url.indexOf('?') === -1 ? '?' : '&') + query.join('&') : url;
    }

    function isExternalUrl(url) {
        var link;

        if (!url || !/^https?:\/\//i.test(String(url))) {
            return false;
        }

        link = document.createElement('a');
        link.href = String(url);

        return link.origin !== window.location.origin;
    }

    // 外部地址被拦截时走 error 回调，让页面按失败路径提示用户。
    function rejectExternalRequest(opts) {
        var res = {code: 5000, message: 'External API URL is blocked', data: {}};

        if (typeof opts.error === 'function') {
            opts.error(res);
        }
    }

    function currentLocale() {
        return window.CrmLang && CrmLang.getLocale ? CrmLang.getLocale() : 'zh-CN';
    }

    function translate(key, fallback) {
        return window.CrmLang && CrmLang.t ? CrmLang.t(key) : fallback;
    }

    function reportCallbackError(error) {
        try {
            if (window.console && typeof window.console.error === 'function') {
                window.console.error('CrmAjax callback failed.', error);
            }
        } catch (consoleError) {
            // The asynchronous rethrow below remains the authoritative failure signal.
        }

        try {
            window.setTimeout(function () {
                throw error;
            }, 0);
        } catch (scheduleError) {
            if (window.console && typeof window.console.error === 'function') {
                window.console.error('CrmAjax could not schedule the callback error.', scheduleError);
            }
        }
    }

    function invokeSafely(callback, args, context) {
        if (typeof callback !== 'function') {
            return;
        }

        try {
            callback.apply(context || null, args || []);
        } catch (error) {
            reportCallbackError(error);
        }
    }

    function completeRequest(opts, maskEnabled) {
        hideGlobalMask(maskEnabled);
        invokeSafely(opts.complete, [], opts);
    }

    function handleAuthenticationFailure(res, opts, guard, loginUrl) {
        if (!res || [4001, 4002, 4003, 4004].indexOf(Number(res.code)) === -1) {
            return false;
        }

        removeToken(guard);
        try {
            invokeSafely(opts.error, [res], opts);
            if (opts.authRedirect !== false && Number(res.code) === 4003) {
                invokeSafely(function () {
                    alert(translate('auth.ssoConflict', 'Login conflict, please sign in again.'));
                });
            }
        } finally {
            if (opts.authRedirect !== false) {
                window.location.href = loginUrl;
            }
        }

        return true;
    }

    // 普通 JSON 请求入口：自动注入 token、语言头，并统一处理登录失效和单点冲突。
    function request(opts) {
        opts = opts || {};
        var guard = opts.guard || 'front';
        var token = getToken(guard);
        var maskEnabled = false;
        var loginUrl = guard === 'admin' ? '/admin/login' : '/front/login';
        var headers = $.extend({}, opts.headers || {}, {
            Accept: 'application/json',
            'X-Locale': currentLocale()
        });
        var contentType = opts.contentType || 'application/json';
        var requestData = opts.data || null;

        opts.url = resolveUrl(opts, opts.url);
        if ((opts.method || 'POST').toUpperCase() === 'GET') {
            opts.url = appendQuery(opts.url, requestData);
            requestData = null;
        }

        if (isExternalUrl(opts.url)) {
            rejectExternalRequest(opts);
            return;
        }

        maskEnabled = showGlobalMask(opts);

        if (token) {
            headers.Authorization = 'Bearer ' + token;
        }
        if (requestData && typeof requestData === 'object' && !(requestData instanceof FormData) && contentType === 'application/json') {
            requestData = JSON.stringify(requestData);
        }

        // 返回 jqXHR，让调用方可以 abort() 掉过期请求（例如输入防抖后的旧一次查询）。
        // 历史调用方忽略返回值即可，行为完全不变。
        return $.ajax({
            url: opts.url,
            type: opts.method || 'POST',
            data: requestData,
            dataType: 'json',
            contentType: contentType,
            processData: !(requestData instanceof FormData),
            headers: headers,
            __crmMaskManaged: true
        }).done(function(res) {
            try {
                res = res || {code: 5000, message: 'Server error', data: {}};
                if (handleAuthenticationFailure(res, opts, guard, loginUrl)) {
                    return;
                }
                invokeSafely(opts.success, [res], opts);
            } catch (error) {
                reportCallbackError(error);
            }
        }).fail(function(xhr, textStatus) {
            try {
                // 主动 abort 的请求不是业务错误，不能弹网络错误提示，也不该覆盖新请求的结果。
                if (textStatus === 'abort' || xhr.statusText === 'abort') {
                    return;
                }

                var res = {code: 5000, message: 'Network error', data: {}};
                if (xhr.responseJSON) {
                    res = xhr.responseJSON;
                }
                if (handleAuthenticationFailure(res, opts, guard, loginUrl)) {
                    return;
                }
                invokeSafely(opts.error, [res], opts);
            } catch (error) {
                reportCallbackError(error);
            }
        }).always(function () {
            completeRequest(opts, maskEnabled);
        });
    }

    // 文件上传入口：保持 FormData 原样提交，并复用 token、语言和外部地址拦截。
    function upload(opts) {
        opts = opts || {};
        var guard = opts.guard || 'front';
        var token = getToken(guard);
        var maskEnabled = false;
        var loginUrl = guard === 'admin' ? '/admin/login' : '/front/login';
        var headers = $.extend({}, opts.headers || {}, {
            Accept: 'application/json',
            'X-Locale': currentLocale()
        });

        opts.url = resolveUrl(opts, opts.url);

        if (isExternalUrl(opts.url)) {
            rejectExternalRequest(opts);
            return;
        }

        maskEnabled = showGlobalMask(opts);

        if (token) {
            headers.Authorization = 'Bearer ' + token;
        }

        $.ajax({
            url: opts.url,
            type: 'POST',
            data: opts.formData,
            dataType: 'json',
            headers: headers,
            processData: false,
            contentType: false,
            __crmMaskManaged: true
        }).done(function(res) {
            try {
                res = res || {code: 5000, message: 'Server error', data: {}};
                if (handleAuthenticationFailure(res, opts, guard, loginUrl)) {
                    return;
                }
                invokeSafely(opts.success, [res], opts);
            } catch (error) {
                reportCallbackError(error);
            }
        }).fail(function(xhr) {
            try {
                var res = xhr.responseJSON || {code: 5000, message: 'Network error', data: {}};
                if (handleAuthenticationFailure(res, opts, guard, loginUrl)) {
                    return;
                }
                invokeSafely(opts.error, [res], opts);
            } catch (error) {
                reportCallbackError(error);
            }
        }).always(function () {
            completeRequest(opts, maskEnabled);
        });
    }

    return {
        getToken: getToken,
        setToken: setToken,
        removeToken: removeToken,
        showGlobalMask: showGlobalMask,
        hideGlobalMask: hideGlobalMask,
        configureMask: configureMask,
        installJqueryGlobalMask: installJqueryGlobalMask,
        request: request,
        upload: upload
    };
})();

window.CrmAjax = CrmAjax;
CrmAjax.installJqueryGlobalMask();
