<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/08
 * Time: 00:30
 */

/**
 * VisualAuditFixtureTest
 *
 * 文件功能：
 * - 验证视觉审计夹具契约：资产双重闸门且先于业务脚本加载、仅对显式测试请求渲染、测试环境外永不渲染、只服务只读审计 API 且不持久化认证。
 * - 输入：路由、控制器、Blade/JS、迁移等项目源码文本；输出：PHPUnit 断言结果。
 * - 明确不负责：不覆盖 HTTP 运行时业务流与 MT4 真实网关同步（由集成与功能测试锁定）。
 */

declare(strict_types=1);

namespace Tests\Feature;

use Tests\Feature\Concerns\ExecutesJavascriptScenarios;
use Tests\TestCase;

final class VisualAuditFixtureTest extends TestCase
{
    use ExecutesJavascriptScenarios;

    /**
     * 视觉审计夹具脚本的资源路径。断言其双重门控（仅测试环境可访问）且先于业务脚本加载。
     * @var string
     */
    private const ASSET_PATH = '/js/testing/visual-audit-fixture.js';

    public function test_visual_audit_asset_is_double_gated_and_loaded_before_business_scripts(): void
    {
        $partial = $this->source('resources/views/partials/visual-audit-fixture.blade.php');

        $this->assertStringContainsString("app()->environment('testing')", $partial);
        $this->assertStringContainsString("request()->query('visual_audit') === '1'", $partial);
        $this->assertStringNotContainsString("request()->boolean('visual_audit')", $partial);
        $this->assertStringContainsString('&&', $partial);
        $this->assertStringContainsString(self::ASSET_PATH, $partial);

        $layouts = [
            'resources/front/layui/layouts/app.blade.php' => 2,
            'resources/admin/layui/layouts/app.blade.php' => 1,
            'resources/front/crmui/layouts/app.blade.php' => 1,
            'resources/admin/crmui/layouts/app.blade.php' => 1,
        ];

        foreach ($layouts as $path => $expectedIncludes) {
            $layout = $this->source($path);
            $this->assertSame(
                $expectedIncludes,
                substr_count($layout, "@include('partials.visual-audit-fixture')"),
                $path . ' must include the fixture after every shared AJAX bootstrap.'
            );
            $this->assertSame(
                $expectedIncludes,
                preg_match_all(
                    "~shared/ajax\\.js.*?@include\\('partials\\.visual-audit-fixture'\\).*?<script src=~s",
                    $layout
                ),
                $path . ' must load the fixture between shared AJAX and the next script.'
            );
        }

        $this->assertFileExists(public_path('js/testing/visual-audit-fixture.js'));
    }

    public function test_visual_audit_asset_renders_only_for_explicit_testing_requests(): void
    {
        foreach (['/front/dashboard', '/admin/users', '/front-crmui/dashboard', '/admin-crmui/users'] as $uri) {
            $this->get($uri)
                ->assertOk()
                ->assertDontSee(self::ASSET_PATH, false);

            $this->get($uri . '?visual_audit=1')
                ->assertOk()
                ->assertSee(self::ASSET_PATH, false);
        }

        foreach (['true', 'on', 'yes', '0'] as $invalidValue) {
            $this->get('/front/dashboard?visual_audit=' . $invalidValue)
                ->assertOk()
                ->assertDontSee(self::ASSET_PATH, false);
        }
        foreach (['/front/dashboard?visual_audit', '/front/dashboard?visual_audit='] as $emptyValueUri) {
            $this->get($emptyValueUri)
                ->assertOk()
                ->assertDontSee(self::ASSET_PATH, false);
        }

        $outerFrame = $this->get('/front/dashboard?visual_audit=1')->assertOk();
        $outerFrame->assertSee('visual_audit=1', false);
        $outerFrame->assertSee('frame=1', false);

        $this->get('/front/dashboard?visual_audit=1&frame=1')
            ->assertOk()
            ->assertSee(self::ASSET_PATH, false);
    }

    public function test_visual_audit_asset_never_renders_outside_testing_environment(): void
    {
        $originalEnvironment = $this->app->environment();

        try {
            $this->app->detectEnvironment(static fn (): string => 'production');

            $this->get('/front/dashboard?visual_audit=1')
                ->assertOk()
                ->assertDontSee(self::ASSET_PATH, false);
        } finally {
            $this->app->detectEnvironment(static fn (): string => $originalEnvironment);
        }

        $this->assertSame($originalEnvironment, $this->app->environment());
    }

    public function test_fixture_fulfils_only_read_only_audit_apis_and_never_persists_auth(): void
    {
        $fixture = $this->source('public/js/testing/visual-audit-fixture.js');
        $script = str_replace('__FIXTURE_BASE64__', base64_encode($fixture), <<<'JS'
'use strict';
const vm = require('vm');
const fixtureSource = Buffer.from('__FIXTURE_BASE64__', 'base64').toString('utf8');
const scheduled = [];
const jqueryOutbound = [];
const fetchOutbound = [];
const beaconOutbound = [];
const xhrTransport = {
    constructed: 0,
    opened: 0,
    sent: 0,
    aborted: 0,
    propertiesAtOpen: []
};
const storageWrites = [];
const bodyAttributeWrites = [];
const bodyAttributes = {
    'data-ui-family': 'layui',
    'data-visual-direction': 'c'
};
let nextTimerId = 1;

function schedule(callback) {
    const timer = {id: nextTimerId++, callback, cancelled: false};
    scheduled.push(timer);
    return timer.id;
}

function cancelTimer(timerId) {
    scheduled.forEach(function(timer) {
        if (timer.id === timerId) {
            timer.cancelled = true;
        }
    });
}

function createDeferred() {
    let state = 'pending';
    let settledContext = null;
    let settledArgs = [];
    const doneCallbacks = [];
    const failCallbacks = [];
    const alwaysCallbacks = [];

    function invoke(callback, context, args) {
        callback.apply(context, args);
    }

    function add(callbacks, callback, expectedState) {
        if (typeof callback !== 'function') {
            return promise;
        }
        callbacks.push(callback);
        if (state === expectedState) {
            invoke(callback, settledContext, settledArgs);
        }
        return promise;
    }

    function adopt(next, value) {
        if (value && typeof value.done === 'function' && typeof value.fail === 'function') {
            value.done(function() {
                next.resolveWith(this, Array.from(arguments));
            }).fail(function() {
                next.rejectWith(this, Array.from(arguments));
            });
            return;
        }
        next.resolve(value);
    }

    const promise = {
        done(callback) {
            return add(doneCallbacks, callback, 'resolved');
        },
        fail(callback) {
            return add(failCallbacks, callback, 'rejected');
        },
        always(callback) {
            if (typeof callback === 'function') {
                alwaysCallbacks.push(callback);
                if (state !== 'pending') {
                    invoke(callback, settledContext, settledArgs);
                }
            }
            return promise;
        },
        then(onResolved, onRejected) {
            const next = createDeferred();
            promise.done(function() {
                try {
                    adopt(next, typeof onResolved === 'function'
                        ? onResolved.apply(this, arguments)
                        : arguments[0]);
                } catch (error) {
                    next.reject(error);
                }
            });
            promise.fail(function() {
                if (typeof onRejected !== 'function') {
                    next.rejectWith(this, Array.from(arguments));
                    return;
                }
                try {
                    adopt(next, onRejected.apply(this, arguments));
                } catch (error) {
                    next.reject(error);
                }
            });
            return next.promise();
        },
        promise() {
            return promise;
        }
    };

    const deferred = Object.assign({}, promise, {
        resolveWith(context, args) {
            if (state !== 'pending') {
                return deferred;
            }
            state = 'resolved';
            settledContext = context;
            settledArgs = args || [];
            doneCallbacks.concat(alwaysCallbacks).forEach(function(callback) {
                invoke(callback, settledContext, settledArgs);
            });
            return deferred;
        },
        rejectWith(context, args) {
            if (state !== 'pending') {
                return deferred;
            }
            state = 'rejected';
            settledContext = context;
            settledArgs = args || [];
            failCallbacks.concat(alwaysCallbacks).forEach(function(callback) {
                invoke(callback, settledContext, settledArgs);
            });
            return deferred;
        },
        resolve() {
            return deferred.resolveWith(deferred, Array.from(arguments));
        },
        reject() {
            return deferred.rejectWith(deferred, Array.from(arguments));
        },
        promise() {
            return promise;
        }
    });

    return deferred;
}

function jquery() {}
jquery.Deferred = createDeferred;
jquery.extend = function() {
    return Object.assign.apply(Object, arguments);
};
jquery.ajax = function() {
    const deferred = createDeferred();
    const promise = deferred.promise();

    promise.native = true;
    jqueryOutbound.push({arguments: Array.from(arguments)});
    schedule(function() {
        deferred.resolve({native: true}, 'success', promise);
    });
    return promise;
};

class FakeHeaders {
    constructor(values) {
        this.values = {};
        Object.keys(values || {}).forEach((key) => {
            this.values[String(key).toLowerCase()] = String(values[key]);
        });
    }

    get(name) {
        return this.values[String(name).toLowerCase()] || null;
    }
}

class FakeResponse {
    constructor(body, init) {
        init = init || {};
        this.bodyText = String(body || '');
        this.status = init.status === undefined ? 200 : init.status;
        this.statusText = init.statusText || '';
        this.ok = this.status >= 200 && this.status < 300;
        this.headers = new FakeHeaders(init.headers || {});
    }

    json() {
        return Promise.resolve(JSON.parse(this.bodyText));
    }

    text() {
        return Promise.resolve(this.bodyText);
    }

    clone() {
        return new FakeResponse(this.bodyText, {
            status: this.status,
            statusText: this.statusText,
            headers: this.headers.values
        });
    }
}

function nativeFetch(input, init) {
    const url = typeof input === 'string' ? input : (input && input.url) || '';
    fetchOutbound.push({url, method: (init && init.method) || (input && input.method) || 'GET'});
    return Promise.resolve({native: true, url});
}

function NativeXMLHttpRequest() {
    xhrTransport.constructed++;
    this.readyState = 0;
    this.status = 0;
    this.statusText = '';
    this.response = null;
    this.responseText = '';
    this.responseType = '';
    this.timeout = 0;
    this.withCredentials = false;
    this._listeners = {};
}

NativeXMLHttpRequest.prototype.addEventListener = function(type, callback) {
    (this._listeners[type] = this._listeners[type] || []).push(callback);
};
NativeXMLHttpRequest.prototype.removeEventListener = function(type, callback) {
    this._listeners[type] = (this._listeners[type] || []).filter(function(item) {
        return item !== callback;
    });
};
NativeXMLHttpRequest.prototype._dispatch = function(type, values) {
    const event = Object.assign({type, target: this, currentTarget: this}, values || {});
    if (typeof this['on' + type] === 'function') {
        this['on' + type].call(this, event);
    }
    (this._listeners[type] || []).slice().forEach((callback) => callback.call(this, event));
};
NativeXMLHttpRequest.prototype.open = function(method, url) {
    xhrTransport.opened++;
    xhrTransport.propertiesAtOpen.push({
        responseType: this.responseType,
        timeout: this.timeout,
        withCredentials: this.withCredentials
    });
    this.method = method;
    this.url = url;
    this.readyState = 1;
    this._dispatch('readystatechange');
};
NativeXMLHttpRequest.prototype.send = function() {
    xhrTransport.sent++;
    this._dispatch('loadstart');
    this._dispatch('progress', {loaded: 3, total: 10, lengthComputable: true});
    this.readyState = 4;
    this.status = 599;
    this.statusText = 'native';
    this.responseText = JSON.stringify({native: true});
    this.response = this.responseText;
    this._dispatch('readystatechange');
    this._dispatch('load');
    this._dispatch('loadend');
};
NativeXMLHttpRequest.prototype.abort = function() {
    xhrTransport.aborted++;
    this._dispatch('abort');
    this._dispatch('loadend');
};
NativeXMLHttpRequest.prototype.setRequestHeader = function() {};
NativeXMLHttpRequest.prototype.getResponseHeader = function() { return null; };
NativeXMLHttpRequest.prototype.getAllResponseHeaders = function() { return ''; };

function nativeSendBeacon(url, data) {
    beaconOutbound.push({url, data});
    return true;
}

const originalAjax = jquery.ajax;
const context = {
    console,
    URL,
    JSON,
    FormData: function FormData() {},
    document: {
        body: {
            getAttribute(name) {
                return Object.prototype.hasOwnProperty.call(bodyAttributes, name)
                    ? bodyAttributes[name]
                    : null;
            },
            setAttribute(name, value) {
                bodyAttributes[name] = String(value);
                bodyAttributeWrites.push([name, String(value)]);
            }
        }
    },
    location: {
        href: 'http://visual-audit.test/admin/users?visual_audit=1',
        origin: 'http://visual-audit.test',
        pathname: '/admin/users'
    },
    localStorage: {
        getItem() { return null; },
        setItem(key, value) { storageWrites.push(['set', key, value]); },
        removeItem(key) { storageWrites.push(['remove', key]); }
    },
    setTimeout: schedule,
    clearTimeout: cancelTimer,
    Promise,
    Response: FakeResponse,
    Headers: FakeHeaders,
    fetch: nativeFetch,
    XMLHttpRequest: NativeXMLHttpRequest,
    navigator: {sendBeacon: nativeSendBeacon},
    jQuery: jquery,
    $: jquery,
    CrmAjax: {
        getToken() { return ''; }
    }
};
context.window = context;
vm.runInNewContext(fixtureSource, context, {filename: 'visual-audit-fixture.js'});

function auditDomState() {
    return {
        count: context.document.body.getAttribute('data-visual-audit-unknown-count'),
        requests: context.document.body.getAttribute('data-visual-audit-unknown-requests'),
        writeCount: bodyAttributeWrites.length
    };
}

const initialAuditDomState = auditDomState();

function flush() {
    while (scheduled.length) {
        const timer = scheduled.shift();
        if (!timer.cancelled) {
            timer.callback();
        }
    }
}

(async function() {
const successOrder = [];
const profileRequest = jquery.ajax({
    url: '/api/front/profile?from=fixture-test',
    method: 'GET',
    success(response, status) {
        successOrder.push('option-success:' + response.code + ':' + status);
    },
    error() {
        successOrder.push('unexpected-error');
    },
    complete(_xhr, status) {
        successOrder.push('complete:' + status);
    }
});
const allowedAuditDomState = auditDomState();
profileRequest.done(function(response, status) {
    successOrder.push('done:' + response.data.info.user_name + ':' + status);
}).fail(function() {
    successOrder.push('unexpected-fail');
}).always(function() {
    successOrder.push('always');
});
flush();

const allowed = [
    ['frontMenus', '/api/front/navigation/menus', 'GET'],
    ['frontDashboard', 'http://visual-audit.test/api/front/dashboard?period=week', 'GET'],
    ['adminMenus', '/api/admin/menus', 'POST'],
    ['adminUsersLayui', '/api/admin/users', 'POST'],
    ['adminUsersCrmUi', '/api/admin/userList', 'POST']
];
const responses = {};
allowed.forEach(function(item) {
    jquery.ajax({url: item[1], type: item[2]}).done(function(response) {
        responses[item[0]] = response;
    });
    flush();
});

const errorOrder = [];
jquery.ajax({
    url: '/api/front/orders',
    method: 'GET',
    error(xhr, status) {
        errorOrder.push('option-error:' + xhr.status + ':' + status);
    },
    complete(_xhr, status) {
        errorOrder.push('complete:' + status);
    }
}).done(function() {
    errorOrder.push('unexpected-done');
}).fail(function(xhr, status) {
    errorOrder.push('fail:' + xhr.status + ':' + status);
}).always(function() {
    errorOrder.push('always');
});
const blockedAuditDomState = auditDomState();
flush();

let transformedValue = null;
jquery.ajax({url: '/api/front/profile', method: 'GET'}).then(function(response) {
    return response.data.info.user_name + ' transformed';
}).done(function(value) {
    transformedValue = value;
});
flush();

let propagatedRejection = null;
jquery.ajax({url: '/api/front/unknown-propagation', method: 'GET'}).then().fail(function(xhr, status) {
    propagatedRejection = [xhr.status, status];
});
flush();

let mappedRejectionMessage = null;
jquery.ajax({url: '/api/front/unknown-mapped', method: 'GET'}).then(null, function() {
    throw new Error('mapped rejection');
}).fail(function(error) {
    mappedRejectionMessage = error && error.message;
});
flush();

const abortOrder = [];
const abortRequest = jquery.ajax({
    url: '/api/front/dashboard',
    method: 'GET',
    success() {
        abortOrder.push('unexpected-success');
    },
    error(_xhr, status) {
        abortOrder.push('option-error:' + status);
    },
    complete(_xhr, status) {
        abortOrder.push('complete:' + status);
    }
}).done(function() {
    abortOrder.push('unexpected-done');
}).fail(function(_xhr, status) {
    abortOrder.push('fail:' + status);
}).always(function() {
    abortOrder.push('always');
});
abortRequest.abort();
abortRequest.abort();
flush();

let encodedJqueryAllowedCode = null;
jquery.ajax({url: '/%61pi/front/profile', method: 'GET'}).done(function(response) {
    encodedJqueryAllowedCode = response && response.code;
});
flush();

let encodedJqueryBlocked = false;
jquery.ajax({url: '/api%2Ffront%2Forders', method: 'GET'}).fail(function() {
    encodedJqueryBlocked = true;
});
flush();

const canonicalJquery = {};
[
    ['encodedLeadingSlashAllowed', '/%2Fapi/front/profile'],
    ['doubleEncodedBlocked', '/%252Fapi%252Ffront%252Forders'],
    ['encodedBackslashAllowed', '/%5Capi/front/profile'],
    ['encodedDotSegmentBlocked', '/safe/%2e%2e/api/front/orders']
].forEach(function(item) {
    const result = {resolved: false, rejected: false, code: null};

    jquery.ajax({url: item[1], method: 'GET'}).done(function(response) {
        result.resolved = true;
        result.code = response && response.code;
    }).fail(function() {
        result.rejected = true;
    });
    flush();
    canonicalJquery[item[0]] = result;
});

const fetchAllowedResponse = await context.fetch(
    '/%2561pi%252Ffront%252Fdashboard',
    {method: 'GET'}
);
const fetchAllowedBody = fetchAllowedResponse && typeof fetchAllowedResponse.json === 'function'
    ? await fetchAllowedResponse.json()
    : null;
const fetchAllowedContentType = fetchAllowedResponse && fetchAllowedResponse.headers
    && typeof fetchAllowedResponse.headers.get === 'function'
    ? fetchAllowedResponse.headers.get('content-type')
    : null;
let fetchBlockedRejected = false;
try {
    await context.fetch('/%2561pi%252Ffront%252Forders%ZZ', {method: 'GET'});
} catch (error) {
    fetchBlockedRejected = true;
}

async function runFetchCase(input, init) {
    const result = {resolved: false, rejected: false, status: null, code: null};

    try {
        const response = await context.fetch(input, init);
        const body = response && typeof response.json === 'function'
            ? await response.json()
            : null;

        result.resolved = true;
        result.status = response && response.status === undefined ? null : response.status;
        result.code = body && body.code;
    } catch (error) {
        result.rejected = true;
    }

    return result;
}

const fetchVariants = {
    urlAllowed: await runFetchCase(
        new URL('/api/front/profile', context.location.href),
        {method: 'GET'}
    ),
    urlBlocked: await runFetchCase(
        new URL('/api/front/orders', context.location.href),
        {method: 'GET'}
    ),
    requestLikeAllowed: await runFetchCase({url: '/api/admin/users', method: 'POST'}),
    stringFallbackBlocked: await runFetchCase({
        toString() {
            return '/api/front/orders';
        }
    })
};
const fetchApiOutbound = fetchOutbound.slice();

function runXhr(method, url) {
    const events = [];
    const xhr = new context.XMLHttpRequest();
    ['readystatechange', 'load', 'error', 'loadend'].forEach(function(type) {
        xhr.addEventListener(type, function() {
            events.push(type);
        });
    });
    xhr.open(method, url, true);
    const readyStateBeforeFlush = xhr.readyState;
    const eventsBeforeFlush = events.slice();
    xhr.send();
    flush();

    let parsedResponse = null;
    try {
        parsedResponse = JSON.parse(xhr.responseText || 'null');
    } catch (error) {
        parsedResponse = null;
    }

    return {
        events,
        eventsBeforeFlush,
        readyStateBeforeFlush,
        readyState: xhr.readyState,
        status: xhr.status,
        response: parsedResponse
    };
}

const xhrAllowed = runXhr('GET', '/api%2Ffront%2Fnavigation%2Fmenus');
const xhrBlocked = runXhr('POST', '/%2561pi%252Fadmin%252Fusers%252F42%ZZ');
const beaconAllowed = context.navigator.sendBeacon('/%61pi/admin/menus', 'audit');
const beaconBlocked = context.navigator.sendBeacon('/api%2Ffront%2Fprofile', 'audit');

const apiOutbound = {
    jquery: jqueryOutbound.length,
    fetch: fetchOutbound.length,
    xhrOpen: xhrTransport.opened,
    xhrSend: xhrTransport.sent,
    beacon: beaconOutbound.length
};

const nativeJqueryResult = jquery.ajax('/images/default-avatar.svg', {type: 'GET'});
flush();
const nativeFetchResult = await context.fetch('/assets/visual-audit.json', {method: 'GET'});
const xhrConstructedBeforeNative = xhrTransport.constructed;
const xhrOpenedBeforeNative = xhrTransport.opened;
const xhrSentBeforeNative = xhrTransport.sent;
const nativeXhr = new context.XMLHttpRequest();
const nativeXhrEvents = [];
let nativeXhrOpenedProperties = null;
[
    'readystatechange',
    'loadstart',
    'progress',
    'abort',
    'error',
    'load',
    'timeout',
    'loadend'
].forEach(function(type) {
    nativeXhr.addEventListener(type, function(event) {
        if (type === 'readystatechange' && nativeXhr.readyState === 1
            && nativeXhrOpenedProperties === null) {
            nativeXhrOpenedProperties = {
                responseType: nativeXhr.responseType,
                timeout: nativeXhr.timeout,
                withCredentials: nativeXhr.withCredentials
            };
        }
        nativeXhrEvents.push({
            type: event.type,
            targetIsWrapper: event.target === nativeXhr,
            currentTargetIsWrapper: event.currentTarget === nativeXhr,
            loaded: event.loaded === undefined ? null : event.loaded,
            total: event.total === undefined ? null : event.total,
            lengthComputable: event.lengthComputable === undefined
                ? null
                : event.lengthComputable
        });
    });
});
nativeXhr.responseType = 'json';
nativeXhr.timeout = 1200;
nativeXhr.withCredentials = true;
nativeXhr.open('GET', '/assets/visual-audit.json', true);
const nativeXhrPropertiesAfterOpen = {
    responseType: nativeXhr.responseType,
    timeout: nativeXhr.timeout,
    withCredentials: nativeXhr.withCredentials
};
nativeXhr.timeout = 2400;
const nativeXhrPropertiesAfterSetter = {
    responseType: nativeXhr.responseType,
    timeout: nativeXhr.timeout,
    withCredentials: nativeXhr.withCredentials
};
nativeXhr.send();
const nativeBeaconResult = context.navigator.sendBeacon('/telemetry/visual-audit', 'ok');

console.log(JSON.stringify({
    installed: context.__visualAuditFixtureInstalled === true,
    token: context.CrmAjax.getToken('front'),
    successOrder,
    errorOrder,
    abortOrder,
    transformedValue,
    propagatedRejection,
    mappedRejectionMessage,
    responses,
    encodedJqueryAllowedCode,
    encodedJqueryBlocked,
    canonicalJquery,
    fetchAllowedCode: fetchAllowedBody && fetchAllowedBody.code,
    fetchAllowedContentType,
    fetchBlockedRejected,
    fetchVariants,
    fetchApiOutbound,
    xhrAllowed,
    xhrBlocked,
    beaconAllowed,
    beaconBlocked,
    hits: context.__visualAuditApiHits,
    unknown: context.__visualAuditUnknownRequests,
    auditDom: {
        initial: initialAuditDomState,
        afterAllowed: allowedAuditDomState,
        afterBlocked: blockedAuditDomState,
        final: auditDomState()
    },
    apiOutbound,
    nativePassedThrough: {
        jquery: nativeJqueryResult.native === true && jquery.ajax !== originalAjax,
        fetch: nativeFetchResult.native === true && fetchOutbound.length === apiOutbound.fetch + 1,
        xhrConstructed: xhrTransport.constructed - xhrConstructedBeforeNative,
        xhrOpen: xhrTransport.opened - xhrOpenedBeforeNative,
        xhrSend: xhrTransport.sent - xhrSentBeforeNative,
        beacon: nativeBeaconResult === true && beaconOutbound.length === apiOutbound.beacon + 1
    },
    nativeXhrBehavior: {
        propertiesAtOpen: xhrTransport.propertiesAtOpen,
        propertiesDuringOpenedEvent: nativeXhrOpenedProperties,
        propertiesAfterOpen: nativeXhrPropertiesAfterOpen,
        propertiesAfterSetter: nativeXhrPropertiesAfterSetter,
        events: nativeXhrEvents
    },
    storageWrites,
    promiseMethods: ['done', 'fail', 'always', 'then'].every(function(method) {
        return typeof profileRequest[method] === 'function';
    })
}));
})().catch(function(error) {
    console.error(error && error.stack ? error.stack : error);
    process.exitCode = 1;
});
JS
        );

        $actual = $this->executeJavascriptJson($script);

        $this->assertTrue($actual['installed']);
        $this->assertSame('visual-audit-testing-only-not-a-real-token', $actual['token']);
        $this->assertSame([
            'option-success:1000:success',
            'done:Visual Audit User:success',
            'always',
            'complete:success',
        ], $actual['successOrder']);
        $this->assertSame([
            'option-error:0:error',
            'fail:0:error',
            'always',
            'complete:error',
        ], $actual['errorOrder']);
        $this->assertSame([
            'option-error:abort',
            'fail:abort',
            'always',
            'complete:abort',
        ], $actual['abortOrder']);
        $this->assertSame('Visual Audit User transformed', $actual['transformedValue']);
        $this->assertSame([0, 'error'], $actual['propagatedRejection']);
        $this->assertSame('mapped rejection', $actual['mappedRejectionMessage']);
        $this->assertSame([], $actual['responses']['frontMenus']['data']['menus']);
        $this->assertSame([], $actual['responses']['frontDashboard']['data']['news']);
        $this->assertArrayHasKey('downloads', $actual['responses']['frontDashboard']['data']);
        $this->assertSame([], $actual['responses']['adminMenus']['data']['menus']);
        $this->assertSame([], $actual['responses']['adminMenus']['data']['permissions']);
        $this->assertSame(
            ['data' => [], 'total' => 0],
            $actual['responses']['adminUsersLayui']['data']
        );
        $this->assertSame(
            $actual['responses']['adminUsersLayui'],
            $actual['responses']['adminUsersCrmUi']
        );
        $this->assertSame(1000, $actual['encodedJqueryAllowedCode']);
        $this->assertTrue($actual['encodedJqueryBlocked']);
        $this->assertSame([
            'encodedLeadingSlashAllowed' => ['resolved' => true, 'rejected' => false, 'code' => 1000],
            'doubleEncodedBlocked' => ['resolved' => false, 'rejected' => true, 'code' => null],
            'encodedBackslashAllowed' => ['resolved' => true, 'rejected' => false, 'code' => 1000],
            'encodedDotSegmentBlocked' => ['resolved' => false, 'rejected' => true, 'code' => null],
        ], $actual['canonicalJquery']);
        $this->assertSame(1000, $actual['fetchAllowedCode']);
        $this->assertSame('application/json', $actual['fetchAllowedContentType']);
        $this->assertTrue($actual['fetchBlockedRejected']);
        $this->assertSame([
            'urlAllowed' => ['resolved' => true, 'rejected' => false, 'status' => 200, 'code' => 1000],
            'urlBlocked' => ['resolved' => false, 'rejected' => true, 'status' => null, 'code' => null],
            'requestLikeAllowed' => ['resolved' => true, 'rejected' => false, 'status' => 200, 'code' => 1000],
            'stringFallbackBlocked' => ['resolved' => false, 'rejected' => true, 'status' => null, 'code' => null],
        ], $actual['fetchVariants']);
        $this->assertSame([], $actual['fetchApiOutbound']);
        $this->assertSame(4, $actual['xhrAllowed']['readyState']);
        $this->assertSame(1, $actual['xhrAllowed']['readyStateBeforeFlush']);
        $this->assertSame(['readystatechange'], $actual['xhrAllowed']['eventsBeforeFlush']);
        $this->assertSame(200, $actual['xhrAllowed']['status']);
        $this->assertSame(1000, $actual['xhrAllowed']['response']['code']);
        $this->assertSame(
            ['readystatechange', 'load', 'loadend'],
            array_slice($actual['xhrAllowed']['events'], -3)
        );
        $this->assertSame(0, $actual['xhrBlocked']['status']);
        $this->assertSame(1, $actual['xhrBlocked']['readyStateBeforeFlush']);
        $this->assertSame(['readystatechange'], $actual['xhrBlocked']['eventsBeforeFlush']);
        $this->assertContains('error', $actual['xhrBlocked']['events']);
        $this->assertContains('loadend', $actual['xhrBlocked']['events']);
        $this->assertNotContains('load', $actual['xhrBlocked']['events']);
        $this->assertTrue($actual['beaconAllowed']);
        $this->assertFalse($actual['beaconBlocked']);
        $this->assertSame([
            'jquery' => 0,
            'fetch' => 0,
            'xhrOpen' => 0,
            'xhrSend' => 0,
            'beacon' => 0,
        ], $actual['apiOutbound']);
        $this->assertSame([
            'jquery' => true,
            'fetch' => true,
            'xhrConstructed' => 1,
            'xhrOpen' => 1,
            'xhrSend' => 1,
            'beacon' => true,
        ], $actual['nativePassedThrough']);
        $this->assertSame([[
            'responseType' => '',
            'timeout' => 0,
            'withCredentials' => false,
        ]], $actual['nativeXhrBehavior']['propertiesAtOpen']);
        $this->assertSame([
            'responseType' => 'json',
            'timeout' => 1200,
            'withCredentials' => true,
        ], $actual['nativeXhrBehavior']['propertiesDuringOpenedEvent']);
        $this->assertSame([
            'responseType' => 'json',
            'timeout' => 1200,
            'withCredentials' => true,
        ], $actual['nativeXhrBehavior']['propertiesAfterOpen']);
        $this->assertSame([
            'responseType' => 'json',
            'timeout' => 2400,
            'withCredentials' => true,
        ], $actual['nativeXhrBehavior']['propertiesAfterSetter']);
        $this->assertSame(
            ['readystatechange', 'loadstart', 'progress', 'readystatechange', 'load', 'loadend'],
            array_column($actual['nativeXhrBehavior']['events'], 'type')
        );
        foreach ($actual['nativeXhrBehavior']['events'] as $event) {
            $this->assertTrue($event['targetIsWrapper']);
            $this->assertTrue($event['currentTargetIsWrapper']);
        }
        $progressEvent = $actual['nativeXhrBehavior']['events'][2];
        $this->assertSame(3, $progressEvent['loaded']);
        $this->assertSame(10, $progressEvent['total']);
        $this->assertTrue($progressEvent['lengthComputable']);
        $this->assertCount(16, $actual['hits']);
        $this->assertCount(11, $actual['unknown']);
        foreach ($actual['unknown'] as $unknownRequest) {
            $this->assertStringStartsWith('/api', $unknownRequest['path']);
        }
        $this->assertSame([
            'count' => '0',
            'requests' => '[]',
            'writeCount' => 2,
        ], $actual['auditDom']['initial']);
        $this->assertSame([
            'count' => '0',
            'requests' => '[]',
            'writeCount' => 4,
        ], $actual['auditDom']['afterAllowed']);
        $this->assertSame('1', $actual['auditDom']['afterBlocked']['count']);
        $this->assertSame(16, $actual['auditDom']['afterBlocked']['writeCount']);
        $this->assertSame(
            [$actual['unknown'][0]],
            json_decode($actual['auditDom']['afterBlocked']['requests'], true, 512, JSON_THROW_ON_ERROR)
        );
        $this->assertSame((string) count($actual['unknown']), $actual['auditDom']['final']['count']);
        $this->assertSame(
            $actual['unknown'],
            json_decode($actual['auditDom']['final']['requests'], true, 512, JSON_THROW_ON_ERROR)
        );
        $this->assertSame([], $actual['storageWrites']);
        $this->assertTrue($actual['promiseMethods']);
    }

    private function source(string $relativePath): string
    {
        $path = base_path(str_replace('/', DIRECTORY_SEPARATOR, $relativePath));
        $this->assertFileExists($path);

        return (string) file_get_contents($path);
    }
}
