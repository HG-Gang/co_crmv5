var CrmAjax = (function() {
    'use strict';

    function getToken(guard) {
        if (guard === 'admin') {
            return localStorage.getItem('admin_token') || localStorage.getItem('admin_jwt_token');
        }

        return localStorage.getItem('front_token') || localStorage.getItem('front_jwt_token');
    }

    function setToken(guard, token) {
        if (guard === 'admin') {
            localStorage.setItem('admin_token', token);
            localStorage.setItem('admin_jwt_token', token);
            return;
        }

        localStorage.setItem('front_token', token);
        localStorage.setItem('front_jwt_token', token);
    }

    function removeToken(guard) {
        if (guard === 'admin') {
            localStorage.removeItem('admin_token');
            localStorage.removeItem('admin_jwt_token');
            return;
        }

        localStorage.removeItem('front_token');
        localStorage.removeItem('front_jwt_token');
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

    function rejectExternalRequest(opts) {
        var res = {code: 5000, message: 'External API URL is blocked', data: {}};

        if (typeof opts.error === 'function') {
            opts.error(res);
        }
    }

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
