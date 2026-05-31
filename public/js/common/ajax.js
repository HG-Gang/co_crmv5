/**
 * CRM 前后台统一 Ajax 工具。
 * 负责 token 读写、语言头、外部地址拦截、登录失效处理和普通上传请求。
 */
var CrmAjax = (function() {
    'use strict';

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

    // 普通 JSON 请求入口：自动注入 token、语言头，并统一处理登录失效和单点冲突。
    function request(opts) {
        var guard = opts.guard || 'front';
        var token = getToken(guard);
        var loginUrl = guard === 'admin' ? '/admin/login' : '/front/login';
        var headers = {
            Accept: 'application/json',
            'X-Locale': CrmLang.getLocale()
        };
        var contentType = opts.contentType || 'application/json';
        var requestData = opts.data || null;

        if (isExternalUrl(opts.url)) {
            rejectExternalRequest(opts);
            return;
        }

        if (token) {
            headers.Authorization = 'Bearer ' + token;
        }
        if (requestData && typeof requestData === 'object' && !(requestData instanceof FormData) && contentType === 'application/json') {
            requestData = JSON.stringify(requestData);
        }

        $.ajax({
            url: opts.url,
            type: opts.method || 'POST',
            data: requestData,
            dataType: 'json',
            contentType: contentType,
            processData: !(requestData instanceof FormData),
            headers: headers
        }).done(function(res) {
            res = res || {code: 5000, message: 'Server error', data: {}};
            if (res.code === 4001 || res.code === 4002 || res.code === 4004) {
                removeToken(guard);
                if (typeof opts.error === 'function') opts.error(res);
                else window.location.href = loginUrl;
                return;
            }
            if (res.code === 4003) {
                removeToken(guard);
                alert(CrmLang.t('auth.ssoConflict'));
                window.location.href = loginUrl;
                return;
            }
            if (typeof opts.success === 'function') opts.success(res);
        }).fail(function(xhr) {
            var res = {code: 5000, message: 'Network error', data: {}};
            if (xhr.responseJSON) {
                res = xhr.responseJSON;
            }
            if (typeof opts.error === 'function') opts.error(res);
        });
    }

    // 文件上传入口：保持 FormData 原样提交，并复用 token、语言和外部地址拦截。
    function upload(opts) {
        var guard = opts.guard || 'front';
        var token = getToken(guard);
        var headers = {
            Accept: 'application/json',
            'X-Locale': CrmLang.getLocale()
        };

        if (isExternalUrl(opts.url)) {
            rejectExternalRequest(opts);
            return;
        }

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
            contentType: false
        }).done(function(res) {
            if (typeof opts.success === 'function') {
                opts.success(res || {code: 5000, message: 'Server error', data: {}});
            }
        }).fail(function(xhr) {
            if (xhr.responseJSON && typeof opts.error === 'function') {
                opts.error(xhr.responseJSON);
                return;
            }
            if (typeof opts.error === 'function') opts.error({code: 5000, message: 'Network error', data: {}});
        });
    }

    return {
        getToken: getToken,
        setToken: setToken,
        removeToken: removeToken,
        request: request,
        upload: upload
    };
})();
