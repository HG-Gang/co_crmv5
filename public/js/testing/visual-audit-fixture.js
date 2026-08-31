// Created by PhpStorm.
// Project name co_crmv5.
// User: Huang Gang
// Date: 2026/08/08
// Time: 00:31
(function (window, document) {
    'use strict';

    var body = document && document.body;
    var $ = window.jQuery;
    var family = body ? body.getAttribute('data-ui-family') : '';
    var direction = body ? body.getAttribute('data-visual-direction') : '';
    var originalAjax;
    var originalFetch;
    var OriginalXMLHttpRequest;
    var originalSendBeacon;

    if (!body || ['layui', 'crmui'].indexOf(family) === -1 || direction !== 'c') {
        return;
    }
    if (!$ || typeof $.ajax !== 'function' || typeof $.Deferred !== 'function' || !window.CrmAjax) {
        return;
    }
    if (window.__visualAuditFixtureInstalled) {
        return;
    }

    originalAjax = $.ajax;
    originalFetch = typeof window.fetch === 'function' ? window.fetch : null;
    OriginalXMLHttpRequest = typeof window.XMLHttpRequest === 'function' ? window.XMLHttpRequest : null;
    originalSendBeacon = window.navigator && typeof window.navigator.sendBeacon === 'function'
        ? window.navigator.sendBeacon
        : null;

    window.__visualAuditFixtureInstalled = true;
    window.__visualAuditApiHits = [];
    window.__visualAuditUnknownRequests = [];

    function syncAuditState() {
        body.setAttribute(
            'data-visual-audit-unknown-count',
            String(window.__visualAuditUnknownRequests.length)
        );
        body.setAttribute(
            'data-visual-audit-unknown-requests',
            JSON.stringify(window.__visualAuditUnknownRequests)
        );
    }

    syncAuditState();

    window.CrmAjax.getToken = function () {
        return 'visual-audit-testing-only-not-a-real-token';
    };

    var responses = {
        'GET /api/front/profile': {
            code: 1000,
            message: 'OK',
            data: {
                info: {
                    user_id: 0,
                    user_name: 'Visual Audit User',
                    avatar_url: '/images/default-avatar.svg',
                    auth_status: 1
                },
                login: {email: 'visual-audit@example.test'}
            }
        },
        'GET /api/front/navigation/menus': {
            code: 1000,
            message: 'OK',
            data: {menus: []}
        },
        'GET /api/front/dashboard': {
            code: 1000,
            message: 'OK',
            data: {
                user: {
                    user_id: 0,
                    user_name: 'Visual Audit User',
                    email: 'visual-audit@example.test',
                    title: '',
                    auth_status: 1
                },
                stats: {
                    total_commission: 0,
                    account_balance: 0,
                    total_deposit: 0,
                    total_withdrawal: 0,
                    net_fund: 0,
                    open_orders: 0,
                    closed_orders: 0,
                    profit: 0,
                    loss: 0
                },
                profile: {
                    auth_status: 1,
                    commission_rate: 0,
                    equity: 0,
                    effective_credit: 0,
                    share_urls: []
                },
                downloads: {pc: null, mobile: null},
                period: {from: '-', to: '-'},
                news: []
            }
        },
        'POST /api/admin/menus': {
            code: 1000,
            message: 'OK',
            data: {
                admin_name: 'Visual Audit Admin',
                menus: [],
                permissions: []
            }
        },
        'POST /api/admin/users': {
            code: 1000,
            message: 'OK',
            data: {data: [], total: 0}
        },
        'POST /api/admin/userList': {
            code: 1000,
            message: 'OK',
            data: {data: [], total: 0}
        }
    };

    function clone(value) {
        return JSON.parse(JSON.stringify(value));
    }

    function decodePercentLayers(path) {
        var previous;

        do {
            previous = path;
            path = path.replace(/%([0-9a-f]{2})/gi, function (_match, hexadecimal) {
                return String.fromCharCode(parseInt(hexadecimal, 16));
            });
        } while (path !== previous);

        return path;
    }

    function normalizePath(path) {
        var normalizedSegments = [];

        String(path || '').replace(/\\/g, '/').split('/').forEach(function (segment) {
            if (!segment || segment === '.') {
                return;
            }
            if (segment === '..') {
                normalizedSegments.pop();
                return;
            }
            normalizedSegments.push(segment);
        });

        return '/' + normalizedSegments.join('/');
    }

    function canonicalPath(rawUrl) {
        var value = String(rawUrl || '');
        var path;

        try {
            path = new window.URL(value, window.location.href).pathname;
        } catch (error) {
            path = value.split('?')[0].split('#')[0];
        }

        return normalizePath(decodePercentLayers(path));
    }

    function classifyRequest(rawUrl, rawMethod) {
        var method = String(rawMethod || 'GET').toUpperCase();
        var path = canonicalPath(rawUrl);
        var key = method + ' ' + path;

        if (path !== '/api' && path.indexOf('/api/') !== 0) {
            return {kind: 'pass', method: method, path: path};
        }
        if (Object.prototype.hasOwnProperty.call(responses, key)) {
            return {kind: 'allowed', method: method, path: path, response: clone(responses[key])};
        }

        return {kind: 'blocked', method: method, path: path};
    }

    function recordAllowed(classification, rawUrl, transport) {
        window.__visualAuditApiHits.push({
            method: classification.method,
            path: classification.path,
            url: String(rawUrl || ''),
            transport: transport
        });
        syncAuditState();
    }

    function recordBlocked(classification, rawUrl, transport) {
        window.__visualAuditUnknownRequests.push({
            method: classification.method,
            path: classification.path,
            url: String(rawUrl || ''),
            transport: transport,
            reason: 'not_whitelisted'
        });
        syncAuditState();
    }

    function blockedResponse() {
        return {
            code: 5000,
            message: 'Visual audit blocked an API request outside the read-only fixture.',
            data: {}
        };
    }

    function requestOptions(urlOrOptions, extraOptions) {
        if (typeof urlOrOptions === 'string') {
            return $.extend({}, extraOptions || {}, {url: urlOrOptions});
        }

        return $.extend({}, urlOrOptions || {});
    }

    function extractRequestUrl(input) {
        if (typeof input === 'string') {
            return input;
        }
        if (input) {
            try {
                if (typeof input.url === 'string') {
                    return input.url;
                }
            } catch (error) {
                // Continue to the next safe extraction strategy.
            }
            try {
                if (typeof input.href === 'string') {
                    return input.href;
                }
            } catch (error) {
                // Continue to the String fallback.
            }
        }

        try {
            return input === null || input === undefined ? '' : String(input);
        } catch (error) {
            return '';
        }
    }

    function makeJqueryPromise(options, response, rejectionMessage) {
        var deferred = $.Deferred();
        var promise = deferred.promise();
        var context = options.context || options;
        var state = 'pending';
        var settleTimer;
        var completeScheduled = false;

        promise.readyState = 1;
        promise.status = 0;
        promise.statusText = '';
        promise.responseJSON = null;

        function scheduleComplete(statusText) {
            if (completeScheduled || typeof options.complete !== 'function') {
                return;
            }
            completeScheduled = true;
            window.setTimeout(function () {
                options.complete.call(context, promise, statusText);
            }, 0);
        }

        function resolve() {
            if (state !== 'pending') {
                return;
            }
            state = 'resolved';
            promise.readyState = 4;
            promise.status = 200;
            promise.statusText = 'success';
            promise.responseJSON = response;
            deferred.resolveWith(context, [response, 'success', promise]);
            scheduleComplete('success');
        }

        function reject(statusText, message, rejectionResponse) {
            if (state !== 'pending') {
                return;
            }
            state = 'rejected';
            promise.readyState = 4;
            promise.status = 0;
            promise.statusText = statusText;
            promise.responseJSON = rejectionResponse;
            deferred.rejectWith(context, [promise, statusText, message]);
            scheduleComplete(statusText);
        }

        if (typeof options.success === 'function') {
            deferred.done(function (data, status, xhr) {
                options.success.call(context, data, status, xhr);
            });
        }
        if (typeof options.error === 'function') {
            deferred.fail(function (xhr, status, error) {
                options.error.call(context, xhr, status, error);
            });
        }

        settleTimer = window.setTimeout(function () {
            if (typeof rejectionMessage === 'string') {
                reject('error', rejectionMessage, response);
                return;
            }
            resolve();
        }, 0);

        promise.abort = function () {
            var abortResponse;

            if (state !== 'pending') {
                return promise;
            }
            window.clearTimeout(settleTimer);
            abortResponse = {code: 5000, message: 'Visual audit request aborted.', data: {}};
            reject('abort', 'abort', abortResponse);
            return promise;
        };

        return promise;
    }

    $.ajax = function (urlOrOptions, extraOptions) {
        var options = requestOptions(urlOrOptions, extraOptions);
        var method = options.method || options.type || 'GET';
        var classification = classifyRequest(options.url, method);
        var failure;

        if (classification.kind === 'pass') {
            return originalAjax.apply(this, arguments);
        }
        if (classification.kind === 'allowed') {
            recordAllowed(classification, options.url, 'jquery');
            return makeJqueryPromise(options, classification.response);
        }

        failure = blockedResponse();
        recordBlocked(classification, options.url, 'jquery');
        return makeJqueryPromise(options, failure, failure.message);
    };

    function createFetchResponse(response) {
        var bodyText = JSON.stringify(response);

        if (typeof window.Response === 'function') {
            return new window.Response(bodyText, {
                status: 200,
                statusText: 'OK',
                headers: {'Content-Type': 'application/json'}
            });
        }

        return {
            ok: true,
            status: 200,
            statusText: 'OK',
            headers: {
                get: function (name) {
                    return String(name).toLowerCase() === 'content-type' ? 'application/json' : null;
                }
            },
            json: function () {
                return window.Promise.resolve(clone(response));
            },
            text: function () {
                return window.Promise.resolve(bodyText);
            }
        };
    }

    if (originalFetch) {
        window.fetch = function (input, init) {
            var rawUrl = extractRequestUrl(input);
            var method = (init && init.method) || (input && input.method) || 'GET';
            var classification = classifyRequest(rawUrl, method);
            var FetchError = window.TypeError || Error;

            if (classification.kind === 'pass') {
                return originalFetch.apply(this, arguments);
            }
            if (classification.kind === 'allowed') {
                recordAllowed(classification, rawUrl, 'fetch');
                return window.Promise.resolve(createFetchResponse(classification.response));
            }

            recordBlocked(classification, rawUrl, 'fetch');
            return window.Promise.reject(new FetchError(blockedResponse().message));
        };
    }

    function installXMLHttpRequestFixture() {
        var eventTypes = [
            'readystatechange',
            'loadstart',
            'progress',
            'abort',
            'error',
            'load',
            'timeout',
            'loadend'
        ];

        if (!OriginalXMLHttpRequest) {
            return;
        }

        function VisualAuditXMLHttpRequest() {
            var self = this;

            this._native = new OriginalXMLHttpRequest();
            this._classification = null;
            this._mode = null;
            this._method = 'GET';
            this._url = '';
            this._timer = null;
            this._sent = false;
            this._listeners = {};
            this._headers = {};
            this._readyState = 0;
            this._status = 0;
            this._statusText = '';
            this._response = null;
            this._responseText = '';
            this._responseURL = '';
            this._responseType = '';
            this._timeout = 0;
            this._withCredentials = false;
            this._openingNative = false;
            this._pendingNativeReadyStateEvent = null;

            eventTypes.forEach(function (type) {
                self._native.addEventListener(type, function (event) {
                    if (type === 'readystatechange' && self._openingNative) {
                        self._pendingNativeReadyStateEvent = event;
                        return;
                    }
                    self._dispatch(type, event);
                });
            });
        }

        VisualAuditXMLHttpRequest.prototype._dispatch = function (type, nativeEvent) {
            var event = {type: type, target: this, currentTarget: this};
            var handler = this['on' + type];

            if (nativeEvent) {
                ['loaded', 'total', 'lengthComputable'].forEach(function (name) {
                    if (name in nativeEvent) {
                        event[name] = nativeEvent[name];
                    }
                });
            }
            if (typeof handler === 'function') {
                handler.call(this, event);
            }
            (this._listeners[type] || []).slice().forEach(function (callback) {
                callback.call(this, event);
            }, this);
        };

        VisualAuditXMLHttpRequest.prototype.addEventListener = function (type, callback) {
            if (typeof callback !== 'function') {
                return;
            }
            (this._listeners[type] = this._listeners[type] || []).push(callback);
        };

        VisualAuditXMLHttpRequest.prototype.removeEventListener = function (type, callback) {
            this._listeners[type] = (this._listeners[type] || []).filter(function (item) {
                return item !== callback;
            });
        };

        VisualAuditXMLHttpRequest.prototype.open = function (method, url) {
            var nativeArguments = arguments;
            var openedEvent;
            var result;

            this._method = String(method || 'GET').toUpperCase();
            this._url = String(url || '');
            this._classification = classifyRequest(this._url, this._method);
            this._mode = this._classification.kind === 'pass' ? 'native' : 'fixture';
            if (this._mode === 'native') {
                this._pendingNativeReadyStateEvent = null;
                this._openingNative = true;
                try {
                    result = this._native.open.apply(this._native, nativeArguments);
                } finally {
                    this._openingNative = false;
                }
                this._native.responseType = this._responseType;
                this._native.timeout = this._timeout;
                this._native.withCredentials = this._withCredentials;
                openedEvent = this._pendingNativeReadyStateEvent;
                this._pendingNativeReadyStateEvent = null;
                if (openedEvent) {
                    this._dispatch('readystatechange', openedEvent);
                }
                return result;
            }

            this._readyState = 1;
            this._dispatch('readystatechange');
        };

        VisualAuditXMLHttpRequest.prototype.send = function (bodyValue) {
            var self = this;
            var response;

            if (this._mode === 'native') {
                return this._native.send(bodyValue);
            }
            if (!this._classification || this._sent) {
                throw new Error('Visual audit XMLHttpRequest is not in a sendable state.');
            }
            this._sent = true;

            if (this._classification.kind === 'allowed') {
                recordAllowed(this._classification, this._url, 'xhr');
                response = this._classification.response;
                this._timer = window.setTimeout(function () {
                    var responseText = JSON.stringify(response);

                    self._readyState = 4;
                    self._status = 200;
                    self._statusText = 'OK';
                    self._responseText = responseText;
                    self._response = self._responseType === 'json' ? clone(response) : responseText;
                    try {
                        self._responseURL = new window.URL(self._url, window.location.href).href;
                    } catch (error) {
                        self._responseURL = self._url;
                    }
                    self._dispatch('readystatechange');
                    self._dispatch('load');
                    self._dispatch('loadend');
                }, 0);
                return;
            }

            recordBlocked(this._classification, this._url, 'xhr');
            this._timer = window.setTimeout(function () {
                self._readyState = 4;
                self._status = 0;
                self._statusText = '';
                self._response = null;
                self._responseText = '';
                self._dispatch('readystatechange');
                self._dispatch('error');
                self._dispatch('loadend');
            }, 0);
        };

        VisualAuditXMLHttpRequest.prototype.abort = function () {
            var self = this;

            if (this._mode === 'native') {
                return this._native.abort();
            }
            if (!this._sent || this._readyState === 4) {
                return;
            }
            window.clearTimeout(this._timer);
            this._sent = false;
            this._readyState = 0;
            this._status = 0;
            this._statusText = '';
            window.setTimeout(function () {
                self._dispatch('abort');
                self._dispatch('loadend');
            }, 0);
        };

        VisualAuditXMLHttpRequest.prototype.setRequestHeader = function (name, value) {
            if (this._mode === 'native') {
                return this._native.setRequestHeader(name, value);
            }
            this._headers[String(name).toLowerCase()] = String(value);
        };

        VisualAuditXMLHttpRequest.prototype.getResponseHeader = function (name) {
            if (this._mode === 'native') {
                return this._native.getResponseHeader(name);
            }
            if (this._status !== 200) {
                return null;
            }
            return String(name).toLowerCase() === 'content-type' ? 'application/json' : null;
        };

        VisualAuditXMLHttpRequest.prototype.getAllResponseHeaders = function () {
            if (this._mode === 'native') {
                return this._native.getAllResponseHeaders();
            }
            return this._status === 200 ? 'content-type: application/json\r\n' : '';
        };

        VisualAuditXMLHttpRequest.prototype.overrideMimeType = function (mimeType) {
            if (this._mode === 'native' && typeof this._native.overrideMimeType === 'function') {
                this._native.overrideMimeType(mimeType);
            }
        };

        ['readyState', 'status', 'statusText', 'response', 'responseText', 'responseURL'].forEach(function (name) {
            Object.defineProperty(VisualAuditXMLHttpRequest.prototype, name, {
                configurable: true,
                enumerable: true,
                get: function () {
                    return this._mode === 'native' ? this._native[name] : this['_' + name];
                }
            });
        });

        ['responseType', 'timeout', 'withCredentials'].forEach(function (name) {
            Object.defineProperty(VisualAuditXMLHttpRequest.prototype, name, {
                configurable: true,
                enumerable: true,
                get: function () {
                    return this._mode === 'native' ? this._native[name] : this['_' + name];
                },
                set: function (value) {
                    this['_' + name] = value;
                    if (this._mode === 'native') {
                        this._native[name] = value;
                    }
                }
            });
        });

        Object.defineProperty(VisualAuditXMLHttpRequest.prototype, 'upload', {
            configurable: true,
            enumerable: true,
            get: function () {
                return this._native.upload;
            }
        });

        VisualAuditXMLHttpRequest.UNSENT = 0;
        VisualAuditXMLHttpRequest.OPENED = 1;
        VisualAuditXMLHttpRequest.HEADERS_RECEIVED = 2;
        VisualAuditXMLHttpRequest.LOADING = 3;
        VisualAuditXMLHttpRequest.DONE = 4;
        VisualAuditXMLHttpRequest.prototype.UNSENT = 0;
        VisualAuditXMLHttpRequest.prototype.OPENED = 1;
        VisualAuditXMLHttpRequest.prototype.HEADERS_RECEIVED = 2;
        VisualAuditXMLHttpRequest.prototype.LOADING = 3;
        VisualAuditXMLHttpRequest.prototype.DONE = 4;

        window.XMLHttpRequest = VisualAuditXMLHttpRequest;
    }

    installXMLHttpRequestFixture();

    if (originalSendBeacon) {
        window.navigator.sendBeacon = function (rawUrl, data) {
            var classification = classifyRequest(rawUrl, 'POST');

            if (classification.kind === 'pass') {
                return originalSendBeacon.apply(this, arguments);
            }
            if (classification.kind === 'allowed') {
                recordAllowed(classification, rawUrl, 'beacon');
                return true;
            }

            recordBlocked(classification, rawUrl, 'beacon');
            return false;
        };
    }
})(window, document);
