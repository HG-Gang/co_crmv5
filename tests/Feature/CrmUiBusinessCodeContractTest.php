<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/22
 * Time: 02:23
 */

declare(strict_types=1);

/**
 * CrmUI 前端业务码（businessCodeSucceeded）契约测试。
 *
 * 文件功能：
 * - 通过 Node 执行抽取出的 JS 函数，验证 CrmUI 只接受文档化的成功业务码。
 * - 验证畸形响应（缺失、null、文本、数组、布尔等 code）一律被拒绝。
 * - 验证旧版成功响应只在显式开启 allowLegacy 时被接受。
 * - 验证 request 不把畸形 2xx 响应交给 done，且可选错误处理器不改变默认提示。
 *
 * 适用场景：
 * - CrmUI 前端 JS 业务码判断与请求包装的回归测试。
 *
 * 入参例子：
 * - businessCodeSucceeded({code: 1000}) 应为 true。
 * - businessCodeSucceeded({code: '1e3'}) 应为 false。
 *
 * 返回值：
 * - 断言通过表示成功码白名单与畸形拒绝行为符合契约。
 *
 * 异常或失败场景：
 * - 成功码被误拒、畸形码被误收、legacy 请求绕过守卫时断言失败。
 */

namespace Tests\Feature;

use Tests\Feature\Concerns\ExecutesJavascriptScenarios;
use Tests\TestCase;

final class CrmUiBusinessCodeContractTest extends TestCase
{
    use ExecutesJavascriptScenarios;

    /**
     * 验证 CrmUI 只接受文档化的成功业务码（含数字字符串形式）。
     *
     * @dataProvider crmUiSourceProvider
     */
    public function test_crmui_accepts_only_documented_success_codes(string $path): void
    {
        $source = (string) file_get_contents(public_path($path));
        $success = $this->extractFunction($source, 'businessCodeSucceeded');

        $actual = $this->executeJavascriptJson(<<<JS
'use strict';
{$success}
var codes = [0, 1000, 1001, 1002, 1003, 1004, 1015, 2000,
    2001, 2002, 2003, 2004, 2005, 2006, 2007, 2008, 2009, 2010,
    2011, 2012, 2013, 2014, 2015, 2016, 2017, 2018, 2019, 2020,
    2021, 2022, 2023, 2024, 2025,
    3000, 3001, 3002, 3003, 3004, 3005, 3006,
    4000, 4001, 4002, 4003, 4004, 4005, 4006, 4007, 4008, 4009,
    5000, 5001, 5002, 5003, 5004];
var accepted = {};
codes.forEach(function (code) {
    accepted[code] = businessCodeSucceeded({code: code});
});
['0', '1000', '1001', '1002', '1003', '1004', '2000', '3000', '3002', '3004', '3005']
    .forEach(function (code) {
        accepted['string:' + code] = businessCodeSucceeded({code: code});
    });
console.log(JSON.stringify(accepted));
JS
        );

        foreach ([0, 1000, 1001, 1002, 1003, 1004, 2000, 3000, 3002, 3004, 3005] as $code) {
            $this->assertTrue($actual[(string) $code], $path . ' must accept ' . $code . '.');
            $this->assertTrue($actual['string:' . $code], $path . ' must accept numeric string ' . $code . '.');
        }
        foreach (array_merge(
            [1015],
            range(2001, 2025),
            [3001, 3003, 3006],
            range(4000, 4009),
            range(5000, 5004)
        ) as $code) {
            $this->assertFalse($actual[(string) $code], $path . ' must reject ' . $code . '.');
        }
    }

    /**
     * 验证 CrmUI 拒绝所有畸形业务码响应。
     *
     * @dataProvider crmUiSourceProvider
     */
    public function test_crmui_rejects_malformed_business_code_responses(string $path): void
    {
        $source = (string) file_get_contents(public_path($path));
        $success = $this->extractFunction($source, 'businessCodeSucceeded');

        $actual = $this->executeJavascriptJson(<<<JS
'use strict';
{$success}
var cases = [
    {name: 'missing response', response: undefined},
    {name: 'null response', response: null},
    {name: 'empty object', response: {}},
    {name: 'missing code', response: {code: undefined}},
    {name: 'null code', response: {code: null}},
    {name: 'empty code', response: {code: ''}},
    {name: 'whitespace code', response: {code: ' '}},
    {name: 'padded code', response: {code: ' 1000 '}},
    {name: 'tabbed code', response: {code: '\t1000'}},
    {name: 'nan code', response: {code: 'NaN'}},
    {name: 'text code', response: {code: 'abc'}},
    {name: 'object code', response: {code: {value: 1000}}},
    {name: 'array code', response: {code: [1000]}},
    {name: 'empty array code', response: {code: []}},
    {name: 'false code', response: {code: false}},
    {name: 'true code', response: {code: true}},
    {name: 'numeric nan code', response: {code: NaN}},
    {name: 'infinity code', response: {code: Infinity}},
    {name: 'infinity string code', response: {code: 'Infinity'}},
    {name: 'scientific string code', response: {code: '1e3'}},
    {name: 'hex string code', response: {code: '0x3e8'}},
    {name: 'signed string code', response: {code: '+1000'}},
    {name: 'padded numeric string code', response: {code: '01000'}},
    {name: 'array response', response: []}
];
var accepted = {};
cases.forEach(function (item) {
    accepted[item.name] = businessCodeSucceeded(item.response);
});
console.log(JSON.stringify(accepted));
JS
        );

        foreach ([
            'missing response',
            'null response',
            'empty object',
            'missing code',
            'null code',
            'empty code',
            'whitespace code',
            'padded code',
            'tabbed code',
            'nan code',
            'text code',
            'object code',
            'array code',
            'empty array code',
            'false code',
            'true code',
            'numeric nan code',
            'infinity code',
            'infinity string code',
            'scientific string code',
            'hex string code',
            'signed string code',
            'padded numeric string code',
            'array response',
        ] as $case) {
            $this->assertFalse($actual[$case], $path . ' must reject malformed response: ' . $case . '.');
        }
    }

    /**
     * 验证旧版前端校验器显式处理 legacy 响应且拒绝畸形 code。
     */
    public function test_front_legacy_validator_is_explicit_and_rejects_malformed_code(): void
    {
        $source = (string) file_get_contents(public_path('js/apps/crmui/front.js'));
        $success = $this->extractFunction($source, 'businessCodeSucceeded');
        $legacy = $this->extractFunction($source, 'successOrLegacy');
        $request = $this->extractFunction($source, 'request');

        $result = $this->executeJavascriptJson(<<<JS
'use strict';
var doneCount = 0;
var errorCount = 0;
var layer = {msg: function () {}};
var window = {};
var FormData = function () {};
var rejected = {done: function () { return this; }, fail: function () { return this; }};
var deferred = {reject: function () { errorCount++; return this; }, promise: function () { return rejected; }};
var responses = [{msg: 'SUC'}, {status: true}, {code: 'abc', status: true}];
var index = 0;
var $ = {
    ajax: function () {
        return {then: function (onFulfilled) {
            var value = onFulfilled(responses[index++]);
            if (value === rejected) return rejected;
            return {done: function (callback) { doneCount++; callback(value); return this; }, fail: function () { return this; }};
        }};
    },
    Deferred: function () { return deferred; }
};
function headers() { return {}; }
function messageFromResponse() { return ''; }
{$success}
{$legacy}
{$request}
var legacyOne = successOrLegacy({msg: 'SUC'});
var legacyTwo = successOrLegacy({status: true});
var malformedLegacy = successOrLegacy({code: 'abc', status: true});
var malformedNull = successOrLegacy({code: null, status: true});
var malformedEmpty = successOrLegacy({code: '', msg: 'SUC'});
var malformedWhitespace = successOrLegacy({code: ' ', msg: 'SUC'});
var malformedArray = successOrLegacy({code: [], status: true});
var malformedObject = successOrLegacy({code: {}, status: true});
var malformedBoolean = successOrLegacy({code: false, status: true});
request({url: '/strict'}).done(function () {});
request({url: '/legacy', allowLegacy: true}).done(function () {});
console.log(JSON.stringify({legacyOne: legacyOne, legacyTwo: legacyTwo, malformedLegacy: malformedLegacy, malformedNull: malformedNull, malformedEmpty: malformedEmpty, malformedWhitespace: malformedWhitespace, malformedArray: malformedArray, malformedObject: malformedObject, malformedBoolean: malformedBoolean, doneCount: doneCount, errorCount: errorCount}));
JS
        );

        $this->assertTrue($result['legacyOne']);
        $this->assertTrue($result['legacyTwo']);
        $this->assertFalse($result['malformedLegacy']);
        foreach (['malformedNull', 'malformedEmpty', 'malformedWhitespace', 'malformedArray', 'malformedObject', 'malformedBoolean'] as $case) {
            $this->assertFalse($result[$case], $case . ' must not bypass the legacy code guard.');
        }
        $this->assertSame(1, $result['doneCount'], 'Only the explicitly enabled legacy request may resolve.');
        $this->assertSame(1, $result['errorCount'], 'The default request must reject a legacy-shaped response.');
    }

    /**
     * 验证 request 不把畸形 2xx 响应交给 done，而是拒绝并提示错误。
     *
     * @dataProvider crmUiSourceProvider
     */
    public function test_crmui_request_does_not_send_malformed_2xx_to_done(string $path): void
    {
        $source = (string) file_get_contents(public_path($path));
        $success = $this->extractFunction($source, 'businessCodeSucceeded');
        $request = $this->extractFunction($source, 'request');

        $result = $this->executeJavascriptJson(<<<JS
'use strict';
var doneCount = 0;
var errorCount = 0;
var messageCount = 0;
var rejectedPromise = {
    done: function (callback) { return this; },
    fail: function (callback) { errorCount++; return this; }
};
var deferred = {
    reject: function () { errorCount++; return this; },
    promise: function () { return rejectedPromise; }
};
var $ = {
    ajax: function () {
        return {
            then: function (onFulfilled) {
                var value = onFulfilled({code: ' '});
                if (value === undefined || value === rejectedPromise) {
                    return rejectedPromise;
                }
                return {
                    done: function (callback) { doneCount++; callback(value); return this; },
                    fail: function (callback) { return this; }
                };
            }
        };
    },
    Deferred: function () { return deferred; }
};
var window = {};
var FormData = function () {};
var layer = {msg: function () { messageCount++; }};
function headers() { return {}; }
function messageFromResponse() { return ''; }
{$success}
{$request}
var returned = request({url: '/malformed'});
if (returned && returned.done) {
    returned.done(function () { doneCount++; });
}
console.log(JSON.stringify({doneCount: doneCount, errorCount: errorCount, messageCount: messageCount}));
JS
        );

        $this->assertSame(0, $result['doneCount'], $path . ' must not invoke done for malformed 2xx.');
        $this->assertSame(1, $result['errorCount'], $path . ' must reject malformed 2xx.');
        $this->assertSame(1, $result['messageCount'], $path . ' must surface malformed 2xx as an error.');
    }

    /**
     * 验证可选错误处理器不改变默认 toast 行为。
     *
     * @dataProvider crmUiSourceProvider
     */
    public function test_crmui_request_scopes_optional_error_handler_without_changing_default_toast(string $path): void
    {
        $source = (string) file_get_contents(public_path($path));
        $success = $this->extractFunction($source, 'businessCodeSucceeded');
        $request = $this->extractFunction($source, 'request');

        $result = $this->executeJavascriptJson(<<<JS
'use strict';
var toastCount = 0;
var scopedErrorCount = 0;
var rejectionCount = 0;
var requestIndex = 0;
var layer = {msg: function () { toastCount++; }};
var window = {location: {href: '/current'}};
var FormData = function () {};
var rejected = {
    done: function () { return this; },
    fail: function () { rejectionCount++; return this; }
};
var deferred = {
    reject: function () { return this; },
    promise: function () { return rejected; }
};
var $ = {
    ajax: function () {
        requestIndex++;
        return {
            then: function (onFulfilled, onRejected) {
                if (requestIndex > 2) {
                    return onFulfilled({code: 4005, message: 'business failed'});
                }
                return onRejected({status: 500, responseJSON: {message: 'network failed'}});
            }
        };
    },
    Deferred: function () { return deferred; }
};
function headers() { return {}; }
function clearToken() {}
function messageFromResponse(response) { return response && response.message; }
{$success}
{$request}
var defaultRequest = request({url: '/default'});
var scopedRequest = request({url: '/scoped', onError: function () { scopedErrorCount++; }});
var defaultBusinessRequest = request({url: '/default-business'});
var scopedBusinessRequest = request({url: '/scoped-business', onError: function () { scopedErrorCount++; }});
defaultRequest.fail(function () { rejectionCount++; });
scopedRequest.fail(function () { rejectionCount++; });
defaultBusinessRequest.fail(function () { rejectionCount++; });
scopedBusinessRequest.fail(function () { rejectionCount++; });
        console.log(JSON.stringify({toastCount: toastCount, scopedErrorCount: scopedErrorCount, rejectionCount: rejectionCount}));
JS
        );

        $this->assertSame(2, $result['toastCount'], $path . ' default HTTP and business errors must retain the global toast.');
        $this->assertSame(2, $result['scopedErrorCount'], $path . ' scoped HTTP and business errors must invoke their handler.');
        $this->assertSame(4, $result['rejectionCount'], $path . ' HTTP and business errors must remain rejected without invoking done.');
    }

    /**
     * 验证行操作模态框只在请求成功后关闭。
     *
     * @dataProvider crmUiSourceProvider
     */
    public function test_crmui_row_action_modal_closes_only_after_request_success(string $path): void
    {
        $source = (string) file_get_contents(public_path($path));
        $closeModal = $this->extractFunction($source, 'closeActionModal');
        $submitAction = $this->extractFunction($source, 'submitRowAction');
$fieldHelpers = $this->crmUiActionFieldHelpers($source);
        $bindActions = $this->extractFunction($source, 'bindRowActions');

        $result = $this->executeJavascriptJson(<<<JS
'use strict';
// 源码 request() 会探测 window.FormData；沙箱提供最小 window 桩。
var window = {FormData: null, location: {href: '', origin: ''}, open: function () {}, setTimeout: function () {}};
var document = {};
var handlers = {};
var activeRequest = null;
var requestCount = 0;
var loadCount = 0;
var layer = {msg: function () {}, confirm: function () {}, close: function () {}};

function jquery(target) {
    if (target === document) {
        return {
            on: function (eventName, selector, handler) {
                handlers[eventName + '|' + selector] = handler;
                return this;
            }
        };
    }

    return target;
}
jquery.extend = function () {
    var target = arguments[0] || {};
    for (var index = 1; index < arguments.length; index++) {
        var source = arguments[index] || {};
        Object.keys(source).forEach(function (key) {
            target[key] = source[key];
        });
    }
    return target;
};
jquery.isArray = function (value) {
    return Object.prototype.toString.call(value) === '[object Array]';
};
var $ = jquery;

function makeModal(inputValue) {
    var state = {
        hidden: false,
        actionButton: null,
        row: null,
        inputValue: inputValue,
        closeCount: 0,
        requestPending: false,
        submitDisabled: false,
        submitBusy: false
    };

    var submitButton = {
        prop: function (name, value) {
            if (name === 'disabled' && arguments.length > 1) state.submitDisabled = value;
            return this;
        },
        attr: function (name, value) {
            if (name === 'aria-busy' && arguments.length > 1) state.submitBusy = value === 'true';
            return this;
        },
        removeAttr: function (name) {
            if (name === 'aria-busy') state.submitBusy = false;
            return this;
        }
    };

    return {
        length: 1,
        state: state,
        data: function (name, value) {
            if (arguments.length > 1) {
                state[name] = value;
                return this;
            }
            return state[name];
        },
        find: function () { return submitButton; },
        attr: function (name, value) {
            if (arguments.length > 1) {
                state[name] = value;
                if (name === 'hidden') state.closeCount++;
                return this;
            }
            return state[name];
        },
        removeData: function (name) {
            state[name] = undefined;
            return this;
        }
    };
}

function makeButton(modal) {
    var page = {find: function () { return modal; }};
    var values = {
        'action-url': '/row-action/__ID__',
        'action-method': 'POST',
        'crmui-row-action': 'update'
    };

    return {
        data: function (name) { return values[name]; },
        attr: function () { return undefined; },
        closest: function () { return page; },
        text: function () { return 'Update'; }
    };
}

function request(options) {
    var doneCallbacks = [];
    var alwaysCallbacks = [];
    var settled = false;
    requestCount++;
    activeRequest = {
        done: function (callback) {
            if (!settled) doneCallbacks.push(callback);
            return this;
        },
        always: function (callback) {
            if (settled) callback();
            else alwaysCallbacks.push(callback);
            return this;
        },
        reject: function () {
            if (settled) return;
            settled = true;
            doneCallbacks = [];
            alwaysCallbacks.slice().forEach(function (callback) { callback(); });
        },
        resolve: function (response) {
            if (settled) return;
            settled = true;
            doneCallbacks.slice().forEach(function (callback) { callback(response); });
            alwaysCallbacks.slice().forEach(function (callback) { callback(); });
        }
    };
    return activeRequest;
}

function submitModal(modal) {
    var form = {
        value: modal.state.inputValue,
        closest: function () { return modal; }
    };
    handlers['submit|[data-crmui-action-form]'].call(form, {preventDefault: function () {}});
    return form;
}

function recordIdentifier(row, key) { return row[key]; }
function staticPayload() { return {}; }
function selectedAgentLevelPayload() { return {}; }
function collectCrmUiPermissionIds() { return []; }
function readForm(form) { return {note: form.value}; }
function rowsFromResponse() { return []; }
function messageFromResponse() { return ''; }
function openActionModal() {}
function loadPage() { loadCount++; }

{$closeModal}
{$fieldHelpers}
{$submitAction}
{$bindActions}

bindRowActions();

var failedModal = makeModal('keep failed input');
failedModal.state.actionButton = makeButton(failedModal);
failedModal.state.row = {id: 41};
var failedForm = submitModal(failedModal);
submitModal(failedModal);
var failedPending = {
    requestCount: requestCount,
    disabled: failedModal.state.submitDisabled,
    busy: failedModal.state.submitBusy
};
activeRequest.reject();

var successModal = makeModal('keep success input');
successModal.state.actionButton = makeButton(successModal);
successModal.state.row = {id: 42};
submitModal(successModal);
var successBeforeResolve = {
    hidden: successModal.state.hidden,
    closeCount: successModal.state.closeCount
};
activeRequest.resolve({code: 1000, message: 'OK'});

console.log(JSON.stringify({
    failed: {
        hidden: failedModal.state.hidden,
        closeCount: failedModal.state.closeCount,
        hasButton: !!failedModal.state.actionButton,
        rowId: failedModal.state.row && failedModal.state.row.id,
        inputValue: failedForm.value,
        disabledAfterReject: failedModal.state.submitDisabled,
        busyAfterReject: failedModal.state.submitBusy
    },
    failedPending: failedPending,
    successBeforeResolve: successBeforeResolve,
    successAfterResolve: {
        hidden: successModal.state.hidden,
        closeCount: successModal.state.closeCount,
        hasButton: !!successModal.state.actionButton,
        rowCleared: successModal.state.row === undefined
    },
    loadCount: loadCount,
    requestCount: requestCount
}));
JS
        );

        $this->assertFalse($result['failed']['hidden'], $path . ' must keep the modal visible after rejection.');
        $this->assertSame(0, $result['failed']['closeCount'], $path . ' must not close the modal on rejection.');
        $this->assertTrue($result['failed']['hasButton'], $path . ' must retain the action button on rejection.');
        $this->assertSame(41, $result['failed']['rowId'], $path . ' must retain the row on rejection.');
        $this->assertSame('keep failed input', $result['failed']['inputValue'], $path . ' must retain user input on rejection.');
        $this->assertSame(1, $result['failedPending']['requestCount'], $path . ' must ignore a duplicate modal submit while pending.');
        $this->assertTrue($result['failedPending']['disabled'], $path . ' must disable modal submit while pending.');
        $this->assertTrue($result['failedPending']['busy'], $path . ' must expose a busy state while pending.');
        $this->assertFalse($result['failed']['disabledAfterReject'], $path . ' must re-enable modal submit after rejection.');
        $this->assertFalse($result['failed']['busyAfterReject'], $path . ' must clear the busy state after rejection.');
        $this->assertFalse($result['successBeforeResolve']['hidden'], $path . ' must wait for request success before closing.');
        $this->assertSame(0, $result['successBeforeResolve']['closeCount'], $path . ' must not close while the request is pending.');
        $this->assertTrue($result['successAfterResolve']['hidden'], $path . ' must close after request success.');
        $this->assertSame(1, $result['successAfterResolve']['closeCount'], $path . ' must close exactly once after success.');
        $this->assertFalse($result['successAfterResolve']['hasButton'], $path . ' must clear the action button after success.');
        $this->assertTrue($result['successAfterResolve']['rowCleared'], $path . ' must clear the row after success.');
        $this->assertSame(1, $result['loadCount'], $path . ' must keep the existing success refresh behavior.');
        $this->assertSame(2, $result['requestCount'], $path . ' must send one failed and one successful request only.');
    }

    /**
     * 验证过期的模态请求不能关闭或解锁新实例。
     *
     * @dataProvider crmUiSourceProvider
     */
    public function test_crmui_stale_modal_request_cannot_close_or_unlock_new_instance(string $path): void
    {
        $source = (string) file_get_contents(public_path($path));
        $openModal = $this->extractFunction($source, 'openActionModal');
        $closeModal = $this->extractFunction($source, 'closeActionModal');
        $submitAction = $this->extractFunction($source, 'submitRowAction');
$fieldHelpers = $this->crmUiActionFieldHelpers($source);

        $result = $this->executeJavascriptJson(<<<JS
'use strict';
// 源码 request() 会探测 window.FormData；沙箱提供最小 window 桩。
var window = {FormData: null, location: {href: '', origin: ''}, open: function () {}, setTimeout: function () {}};
var requests = [];
var toastCount = 0;
var layer = {msg: function () { toastCount++; }};
var loadCount = 0;

function jquery(target) { return target; }
jquery.extend = function () {
    var target = arguments[0] || {};
    for (var index = 1; index < arguments.length; index++) {
        var source = arguments[index] || {};
        Object.keys(source).forEach(function (key) { target[key] = source[key]; });
    }
    return target;
};
var $ = jquery;

function makeButton(name, modal) {
    var values = {
        'action-url': '/row-action/' + name,
        'action-method': 'POST',
        'crmui-row-action': 'update'
    };
    var page = {find: function () { return modal; }};
    return {
        name: name,
        data: function (key) { return values[key]; },
        attr: function () { return undefined; },
        closest: function () { return page; },
        text: function () { return name; }
    };
}

function makeModal() {
    var state = {
        hidden: true,
        actionButton: null,
        row: null,
        requestPending: false,
        closeCount: 0,
        submitDisabled: false,
        submitBusy: false
    };
    var submitButton = {
        prop: function (name, value) {
            if (name === 'disabled' && arguments.length > 1) state.submitDisabled = value;
            return this;
        },
        attr: function (name, value) {
            if (name === 'aria-busy' && arguments.length > 1) state.submitBusy = value === 'true';
            return this;
        },
        removeAttr: function (name) {
            if (name === 'aria-busy') state.submitBusy = false;
            return this;
        },
        toggle: function () { return this; }
    };
    var fieldContainer = {html: function () { return this; }};
    var preview = {text: function () { return this; }, toggle: function () { return this; }};
    var title = {text: function () { return this; }};
    return {
        length: 1,
        state: state,
        data: function (name, value) {
            if (arguments.length > 1) { state[name] = value; return this; }
            return state[name];
        },
        find: function (selector) {
            if (selector.indexOf('button') !== -1) return submitButton;
            if (selector.indexOf('modal-fields') !== -1) return fieldContainer;
            if (selector.indexOf('record-preview') !== -1) return preview;
            if (selector.indexOf('modal-title') !== -1) return title;
            return submitButton;
        },
        attr: function (name, value) {
            if (arguments.length > 1) {
                state[name] = value;
                if (name === 'hidden' && value === true) state.closeCount++;
                return this;
            }
            return state[name];
        },
        removeAttr: function (name) {
            if (name === 'hidden') state.hidden = false;
            return this;
        },
        removeData: function (name) { state[name] = undefined; return this; }
    };
}

function request(options) {
    var doneCallbacks = [];
    var alwaysCallbacks = [];
    var settled = false;
    var current = {
        done: function (callback) { if (!settled) doneCallbacks.push(callback); return this; },
        always: function (callback) { if (!settled) alwaysCallbacks.push(callback); return this; },
        resolve: function (response) {
            if (settled) return;
            settled = true;
            doneCallbacks.slice().forEach(function (callback) { callback(response); });
            alwaysCallbacks.slice().forEach(function (callback) { callback(); });
        },
        reject: function (error) {
            if (settled) return;
            settled = true;
            if (options && typeof options.onError === 'function') options.onError(error || {responseJSON: {message: 'failed'}});
            alwaysCallbacks.slice().forEach(function (callback) { callback(); });
        }
    };
    current.options = options || {};
    requests.push(current);
    return current;
}

function fieldHtml() { return '<input>'; }
function loadCrmUiPermissionTree() {}
function staticPayload() { return {}; }
function selectedAgentLevelPayload() { return {}; }
function collectCrmUiPermissionIds() { return []; }
function recordIdentifier(row, key) { return row[key]; }
function rowsFromResponse() { return []; }
function messageFromResponse() { return ''; }
function loadPage() { loadCount++; }

{$openModal}
{$closeModal}
{$fieldHelpers}
{$submitAction}

var modal = makeModal();
var buttonA = makeButton('A', modal);
var buttonB = makeButton('B', modal);
var rowA = {id: 'A'};
var rowB = {id: 'B'};

openActionModal(buttonA, rowA, [{name: 'note', label: 'Note', type: 'text'}]);
submitRowAction(buttonA, rowA, {note: 'first'}, modal);
closeActionModal(modal);
openActionModal(buttonB, rowB, [{name: 'note', label: 'Note', type: 'text'}]);
submitRowAction(buttonB, rowB, {note: 'second'}, modal);

var beforeStaleResolve = {
    hidden: modal.state.hidden,
    button: modal.state.actionButton && modal.state.actionButton.name,
    row: modal.state.row && modal.state.row.id,
    pending: !!modal.state.requestPending,
    disabled: modal.state.submitDisabled,
    busy: modal.state.submitBusy,
    closeCount: modal.state.closeCount,
    requestCount: requests.length
};
requests[0].resolve({code: 1000, message: 'A completed'});

var afterStaleResolve = {
    hidden: modal.state.hidden,
    button: modal.state.actionButton && modal.state.actionButton.name,
    row: modal.state.row && modal.state.row.id,
    pending: !!modal.state.requestPending,
    disabled: modal.state.submitDisabled,
    busy: modal.state.submitBusy,
    closeCount: modal.state.closeCount
};
if (requests[1]) requests[1].reject();

var afterCurrentReject = {
    hidden: modal.state.hidden,
    button: modal.state.actionButton && modal.state.actionButton.name,
    row: modal.state.row && modal.state.row.id,
    pending: !!modal.state.requestPending,
    disabled: modal.state.submitDisabled,
    busy: modal.state.submitBusy,
    closeCount: modal.state.closeCount,
    toastCount: toastCount,
    loadCount: loadCount
};

closeActionModal(modal);
var buttonC = makeButton('C', modal);
var buttonD = makeButton('D', modal);
openActionModal(buttonC, {id: 'C'}, [{name: 'note', label: 'Note', type: 'text'}]);
submitRowAction(buttonC, {id: 'C'}, {note: 'third'}, modal);
closeActionModal(modal);
openActionModal(buttonD, {id: 'D'}, [{name: 'note', label: 'Note', type: 'text'}]);
submitRowAction(buttonD, {id: 'D'}, {note: 'fourth'}, modal);
requests[2].reject({responseJSON: {message: 'C failed'}});
var afterStaleReject = {
    pending: !!modal.state.requestPending,
    disabled: modal.state.submitDisabled,
    busy: modal.state.submitBusy,
    toastCount: toastCount,
    loadCount: loadCount
};
requests[3].resolve({code: 1000, message: 'D completed'});

console.log(JSON.stringify({
    beforeStaleResolve: beforeStaleResolve,
    afterStaleResolve: afterStaleResolve,
    afterCurrentReject: afterCurrentReject,
    afterStaleReject: afterStaleReject,
    loadCount: loadCount,
    toastCount: toastCount
}));
JS
        );

        $this->assertFalse($result['beforeStaleResolve']['hidden'], $path . ' must show the replacement modal instance.');
        $this->assertSame('B', $result['beforeStaleResolve']['button'], $path . ' must bind the replacement action button.');
        $this->assertSame('B', $result['beforeStaleResolve']['row'], $path . ' must bind the replacement row.');
        $this->assertTrue($result['beforeStaleResolve']['pending'], $path . ' replacement request must own its pending state.');
        $this->assertTrue($result['beforeStaleResolve']['disabled'], $path . ' replacement request must disable its submit button.');
        $this->assertTrue($result['beforeStaleResolve']['busy'], $path . ' replacement request must expose its busy state.');
        $this->assertSame(2, $result['beforeStaleResolve']['requestCount'], $path . ' must allow the replacement instance to submit independently.');
        $this->assertFalse($result['afterStaleResolve']['hidden'], $path . ' stale completion must not close the replacement modal.');
        $this->assertSame('B', $result['afterStaleResolve']['button'], $path . ' stale completion must not clear replacement action button.');
        $this->assertSame('B', $result['afterStaleResolve']['row'], $path . ' stale completion must not clear replacement row.');
        $this->assertTrue($result['afterStaleResolve']['pending'], $path . ' stale completion must not release replacement pending state.');
        $this->assertTrue($result['afterStaleResolve']['disabled'], $path . ' stale completion must not enable replacement submit.');
        $this->assertTrue($result['afterStaleResolve']['busy'], $path . ' stale completion must not clear replacement busy state.');
        $this->assertSame(1, $result['afterStaleResolve']['closeCount'], $path . ' stale completion must not close the replacement modal.');
        $this->assertFalse($result['afterCurrentReject']['hidden'], $path . ' replacement rejection must retain the replacement modal.');
        $this->assertSame('B', $result['afterCurrentReject']['button'], $path . ' replacement rejection must retain its action button.');
        $this->assertSame('B', $result['afterCurrentReject']['row'], $path . ' replacement rejection must retain its row.');
        $this->assertFalse($result['afterCurrentReject']['pending'], $path . ' replacement rejection must release only its own pending state.');
        $this->assertFalse($result['afterCurrentReject']['disabled'], $path . ' replacement rejection must re-enable its submit button.');
        $this->assertFalse($result['afterCurrentReject']['busy'], $path . ' replacement rejection must clear its busy state.');
        $this->assertSame(1, $result['afterCurrentReject']['toastCount'], $path . ' current rejection must keep the existing error toast.');
        $this->assertSame(0, $result['afterCurrentReject']['loadCount'], $path . ' stale success must not refresh the page.');
        $this->assertTrue($result['afterStaleReject']['pending'], $path . ' stale rejection must not release the current request.');
        $this->assertTrue($result['afterStaleReject']['disabled'], $path . ' stale rejection must not enable the current submit button.');
        $this->assertTrue($result['afterStaleReject']['busy'], $path . ' stale rejection must not clear the current busy state.');
        $this->assertSame(1, $result['afterStaleReject']['toastCount'], $path . ' stale rejection must not show a toast.');
        $this->assertSame(0, $result['afterStaleReject']['loadCount'], $path . ' stale rejection must not refresh the page.');
        $this->assertSame(1, $result['loadCount'], $path . ' current success must refresh the page exactly once.');
        $this->assertSame(2, $result['toastCount'], $path . ' only current failure and current success may show toasts.');
    }

    /**
     * 验证列表加载只渲染最新一代请求的结果。
     *
     * @dataProvider crmUiSourceProvider
     */
    public function test_crmui_list_load_renders_only_the_latest_request_generation(string $path): void
    {
        $source = (string) file_get_contents(public_path($path));
        $loadPage = $this->extractFunction($source, 'loadPage');
        // front.js 的 loadPage 不依赖分页状态助手；admin.js 依赖时按需注入，避免对不存在函数断言失败。
        $listLoadHelpers = '';
        foreach (['pageState', 'paginatorFromResponse', 'dataFromResponse', 'updatePageState'] as $helper) {
            if (strpos($source, 'function ' . $helper . '(') !== false) {
                $listLoadHelpers .= $this->extractFunction($source, $helper) . "\n";
            }
        }

        $result = $this->executeJavascriptJson(<<<JS
'use strict';
// paginatorFromResponse 依赖 $.isArray；沙箱提供最小 jQuery 静态桩。
var $ = {isArray: function (value) { return Object.prototype.toString.call(value) === '[object Array]'; }};
function currentPageFilter(page) { return {}; }
var requests = [];
var renderedRows = [];
var renderedMetrics = [];
var filledForms = [];
var totalWrites = [];
var toasts = [];
var layer = {msg: function (message) { toasts.push(message); }};

function request(options) {
    var doneCallbacks = [];
    var requestState = {
        options: options,
        done: function (callback) { doneCallbacks.push(callback); return this; },
        resolve: function (response) {
            doneCallbacks.slice().forEach(function (callback) { callback(response); });
        },
        reject: function (error) {
            if (typeof options.onError === 'function') options.onError(error);
        }
    };
    requests.push(requestState);
    return requestState;
}

var state = {};
var totalTarget = {text: function (value) { totalWrites.push(value); return this; }};
var filterTarget = {};
var page = {
    attr: function (name) {
        if (name === 'data-api-url') return '/api/list';
        if (name === 'data-api-method') return 'GET';
        if (name === 'data-list-key') return '';
        return '';
    },
    data: function (name, value) {
        if (arguments.length > 1) { state[name] = value; return this; }
        return state[name];
    },
    find: function (selector) {
        return selector === '[data-crmui-total]' ? totalTarget : filterTarget;
    }
};

function readForm() { return {}; }
function rowsFromResponse(response) { return [response.data.item]; }
function dataFromResponse(response) { return response.data; }
function renderRows(_, rows) { renderedRows.push(rows[0]); }
function renderMetrics(_, response) { renderedMetrics.push(response.data.item); }
function fillPageForms(_, response) { filledForms.push(response.data.item); }
function totalFromResponse(response) { return response.data.total; }
function paginationState() { return {page: 1, perPage: 15, total: 0}; }
function renderPagination() { return false; }
function renderTableState() {}
function footerRowsFromResponse() { return []; }
function renderTableFooter() {}
function dispatchPageEvent() {}
function messageFromResponse(response) {
    var payload = response && response.responseJSON ? response.responseJSON : response;
    return payload && payload.message;
}

{$listLoadHelpers}

{$loadPage}

loadPage(page);
loadPage(page);
loadPage(page);
requests[0].resolve({code: 1000, data: {item: 'old-success', total: 11}});
requests[1].reject({responseJSON: {message: 'old-error'}});
requests[2].resolve({code: 1000, data: {item: 'latest-success', total: 33}});
loadPage(page);
requests[3].reject({responseJSON: {message: 'current-error'}});

console.log(JSON.stringify({
    requestCount: requests.length,
    renderedRows: renderedRows,
    renderedMetrics: renderedMetrics,
    filledForms: filledForms,
    totalWrites: totalWrites,
    toasts: toasts,
    generation: state.loadGeneration
}));
JS
        );

        $this->assertSame(4, $result['requestCount'], $path . ' must keep sending each explicit list load.');
        $this->assertSame(['latest-success'], $result['renderedRows'], $path . ' stale rows must not replace the latest list.');
        $this->assertSame(['latest-success'], $result['renderedMetrics'], $path . ' stale metrics must not replace the latest list.');
        $this->assertSame(['latest-success'], $result['filledForms'], $path . ' stale responses must not refill page forms.');
        $this->assertSame([33], $result['totalWrites'], $path . ' stale totals must not replace the latest total.');
        $this->assertSame(['current-error'], $result['toasts'], $path . ' only the current list error may show a toast.');
        $this->assertSame(4, $result['generation'], $path . ' each list load must advance its generation.');
    }

    /**
     * 验证管理员权限树按模态实例隔离且就绪前禁止提交。
     */
    public function test_admin_permission_tree_is_instance_scoped_and_must_be_ready_before_submit(): void
    {
        $source = (string) file_get_contents(public_path('js/apps/crmui/admin.js'));
        $loadPermissionTree = $this->extractFunction($source, 'loadCrmUiPermissionTree');
        $openModal = $this->extractFunction($source, 'openActionModal');
        $submitAction = $this->extractFunction($source, 'submitRowAction');
$fieldHelpers = $this->crmUiActionFieldHelpers($source);

        $result = $this->executeJavascriptJson(<<<JS
'use strict';
// request() 内部探测 window.FormData；沙箱提供最小 window 桩即可。
var window = {FormData: null, location: {href: '', origin: ''}, open: function () {}, setTimeout: function () {}};
var treeRequests = [];
var actionRequests = [];
var toasts = [];
var layer = {msg: function (message) { toasts.push(message); }};

function jquery(target) { return target; }
jquery.extend = function () {
    var target = arguments[0] || {};
    for (var index = 1; index < arguments.length; index++) {
        var source = arguments[index] || {};
        Object.keys(source).forEach(function (key) { target[key] = source[key]; });
    }
    return target;
};
jquery.each = function (items, callback) {
    (items || []).forEach(function (item, index) { callback(index, item); });
};
var $ = jquery;

function makeRequest(options) {
    var doneCallbacks = [];
    var alwaysCallbacks = [];
    return {
        options: options,
        done: function (callback) { doneCallbacks.push(callback); return this; },
        always: function (callback) { alwaysCallbacks.push(callback); return this; },
        resolve: function (response) {
            doneCallbacks.slice().forEach(function (callback) { callback(response); });
            alwaysCallbacks.slice().forEach(function (callback) { callback(); });
        },
        reject: function (error) {
            if (typeof options.onError === 'function') options.onError(error);
            alwaysCallbacks.slice().forEach(function (callback) { callback(); });
        }
    };
}

function request(options) {
    var current = makeRequest(options);
    if (options.url.indexOf('/permission-tree/') === 0) treeRequests.push(current);
    else actionRequests.push(current);
    return current;
}

function makeModal() {
    var state = {
        hidden: true,
        submitDisabled: false,
        submitBusy: false,
        treeHtml: ''
    };
    var submitButton = {
        length: 1,
        prop: function (name, value) {
            if (name === 'disabled' && arguments.length > 1) state.submitDisabled = value;
            return this;
        },
        attr: function (name, value) {
            if (name === 'aria-busy' && arguments.length > 1) state.submitBusy = value === 'true';
            return this;
        },
        removeAttr: function (name) {
            if (name === 'aria-busy') state.submitBusy = false;
            return this;
        },
        toggle: function () { return this; }
    };
    var tree = {
        length: 1,
        html: function (value) {
            if (arguments.length) { state.treeHtml = value; return this; }
            return state.treeHtml;
        }
    };
    var fieldContainer = {html: function () { return this; }};
    var preview = {text: function () { return this; }, toggle: function () { return this; }};
    var title = {text: function () { return this; }};
    return {
        length: 1,
        state: state,
        data: function (name, value) {
            if (arguments.length > 1) { state[name] = value; return this; }
            return state[name];
        },
        removeData: function (name) { state[name] = undefined; return this; },
        find: function (selector) {
            if (selector === '[data-permission-tree]') return tree;
            if (selector.indexOf('button') !== -1) return submitButton;
            if (selector.indexOf('modal-fields') !== -1) return fieldContainer;
            if (selector.indexOf('record-preview') !== -1) return preview;
            if (selector.indexOf('modal-title') !== -1) return title;
            return tree;
        },
        attr: function (name, value) {
            if (arguments.length > 1) { state[name] = value; return this; }
            return state[name];
        },
        removeAttr: function (name) {
            if (name === 'hidden') state.hidden = false;
            return this;
        }
    };
}

function makeButton(name, modal) {
    var page = {find: function () { return modal; }};
    var values = {
        'action-url': '/assign/' + name,
        'action-method': 'POST',
        'crmui-row-action': 'assign_permissions',
        'record-key': 'id',
        'payload-name': 'role_id'
    };
    return {
        name: name,
        data: function (key) { return values[key]; },
        attr: function (key) {
            return key === 'data-permission-tree-url' ? '/permission-tree/' + name : undefined;
        },
        closest: function () { return page; },
        text: function () { return name; }
    };
}

function fieldHtml() { return '<div data-permission-tree></div>'; }
function permissionTreeNodes(response) { return response.data.nodes; }
function permissionTreeHtml(nodes) { return 'TREE:' + nodes[0].label; }
function collectCrmUiPermissionIds() { return [7, 8]; }
function recordIdentifier(row, key) { return row[key]; }
function rowsFromResponse() { return []; }
function messageFromResponse(response) {
    var payload = response && response.responseJSON ? response.responseJSON : response;
    return payload && payload.message;
}
function closeActionModal() {}
function loadPage() {}

{$loadPermissionTree}
{$openModal}
{$fieldHelpers}
{$submitAction}

var modal = makeModal();
var buttonA = makeButton('A', modal);
var buttonB = makeButton('B', modal);
var buttonC = makeButton('C', modal);

openActionModal(buttonA, {id: 'A', permission_ids: [1]}, [{name: 'permissions'}]);
var openingA = {
    disabled: modal.state.submitDisabled,
    busy: modal.state.submitBusy,
    loading: modal.state.permissionTreeLoading,
    ready: modal.state.permissionTreeReady
};
submitRowAction(buttonA, {id: 'A'}, {}, modal);
var actionsWhileLoading = actionRequests.length;

openActionModal(buttonB, {id: 'B', permission_ids: [2]}, [{name: 'permissions'}]);
treeRequests[0].resolve({code: 1000, data: {nodes: [{label: 'A'}]}});
var afterStaleTree = {
    disabled: modal.state.submitDisabled,
    loading: modal.state.permissionTreeLoading,
    ready: modal.state.permissionTreeReady,
    treeHtml: modal.state.treeHtml
};
treeRequests[1].resolve({code: 1000, data: {nodes: [{label: 'B'}]}});
var afterCurrentTree = {
    disabled: modal.state.submitDisabled,
    busy: modal.state.submitBusy,
    loading: modal.state.permissionTreeLoading,
    ready: modal.state.permissionTreeReady,
    treeHtml: modal.state.treeHtml
};
submitRowAction(buttonB, {id: 'B'}, {}, modal);
var actionsAfterReady = actionRequests.length;

openActionModal(buttonC, {id: 'C', permission_ids: []}, [{name: 'permissions'}]);
treeRequests[2].reject({responseJSON: {message: 'tree-C failed'}});
var afterFailure = {
    disabled: modal.state.submitDisabled,
    busy: modal.state.submitBusy,
    loading: modal.state.permissionTreeLoading,
    ready: modal.state.permissionTreeReady
};
submitRowAction(buttonC, {id: 'C'}, {}, modal);

console.log(JSON.stringify({
    openingA: openingA,
    actionsWhileLoading: actionsWhileLoading,
    afterStaleTree: afterStaleTree,
    afterCurrentTree: afterCurrentTree,
    actionsAfterReady: actionsAfterReady,
    afterFailure: afterFailure,
    finalActionCount: actionRequests.length,
    toasts: toasts
}));
JS
        );

        $this->assertTrue($result['openingA']['disabled'], 'Permission submit must be disabled while the tree loads.');
        $this->assertTrue($result['openingA']['busy'], 'Permission submit must expose the loading state.');
        $this->assertTrue($result['openingA']['loading'], 'Permission tree must be marked loading on open.');
        $this->assertFalse($result['openingA']['ready'], 'Permission tree must not be ready before its response.');
        $this->assertSame(0, $result['actionsWhileLoading'], 'Loading permissions must block assignment requests.');
        $this->assertTrue($result['afterStaleTree']['disabled'], 'A stale tree response must not enable the replacement modal.');
        $this->assertTrue($result['afterStaleTree']['loading'], 'A stale tree response must not clear replacement loading state.');
        $this->assertFalse($result['afterStaleTree']['ready'], 'A stale tree response must not mark the replacement modal ready.');
        $this->assertStringNotContainsString('TREE:A', $result['afterStaleTree']['treeHtml']);
        $this->assertFalse($result['afterCurrentTree']['disabled'], 'Only the current tree response may enable submit.');
        $this->assertFalse($result['afterCurrentTree']['busy'], 'Current tree success must clear the loading indicator.');
        $this->assertFalse($result['afterCurrentTree']['loading']);
        $this->assertTrue($result['afterCurrentTree']['ready']);
        $this->assertSame('TREE:B', $result['afterCurrentTree']['treeHtml']);
        $this->assertSame(1, $result['actionsAfterReady'], 'A ready tree must allow exactly one assignment request.');
        $this->assertTrue($result['afterFailure']['disabled'], 'Tree failure must keep assignment disabled.');
        $this->assertFalse($result['afterFailure']['busy'], 'Tree failure must end the loading indicator.');
        $this->assertFalse($result['afterFailure']['loading']);
        $this->assertFalse($result['afterFailure']['ready']);
        $this->assertSame(1, $result['finalActionCount'], 'Tree failure must not sync an empty permission list.');
        $this->assertSame(['tree-C failed'], $result['toasts'], 'Only the current tree failure may show an error.');
    }

    /**
     * 验证畸形佣金响应时表单动作状态与幂等键保持不变。
     */
    public function test_crmui_front_malformed_commission_response_keeps_action_state_and_key(): void
    {
        $source = (string) file_get_contents(public_path('js/apps/crmui/front.js'));
        $success = $this->extractFunction($source, 'businessCodeSucceeded');
        $request = $this->extractFunction($source, 'request');
        $handler = $this->extractFunction($source, 'handleFormSuccess');
        $generator = $this->extractFunction($source, 'newCommissionTransferKey');
        $ensurer = $this->extractFunction($source, 'ensureCommissionTransferKey');

        $result = $this->executeJavascriptJson(<<<JS
'use strict';
var doneCount = 0;
var failCount = 0;
var resetCount = 0;
var loadCount = 0;
var ajaxOptions = null;
var rejectedPromise = {
    done: function (callback) { return this; },
    fail: function (callback) { failCount++; callback({businessError: true}); return this; }
};
var deferred = {
    reject: function () { return this; },
    promise: function () { return rejectedPromise; }
};
var $ = {
    ajax: function (options) {
        ajaxOptions = options;
        return {
            then: function (onFulfilled) {
                var value = onFulfilled({code: ' '});
                if (value === rejectedPromise) {
                    return rejectedPromise;
                }
                return {
                    done: function (callback) { doneCount++; callback(value); return this; },
                    fail: function (callback) { return this; }
                };
            }
        };
    },
    Deferred: function () { return deferred; }
};
var uuidCount = 0;
var window = {crypto: {randomUUID: function () { uuidCount++; return 'crmui-' + uuidCount; }}, location: {href: '/current'}};
var FormData = function () {};
var layer = {msg: function () {}};
function headers(auth, extra) { return extra || {}; }
function messageFromResponse() { return ''; }
function dataFromResponse(response) { return response && response.data !== undefined ? response.data : {}; }
function openPaymentUrl() { return false; }
function shouldOpenBlank() { return true; }
function loadPage() { loadCount++; }
var input = {length: 1, value: 'ct-original-key', val: function (value) { if (arguments.length) this.value = value; return this.value; }};
var page = {
    attr: function (name) { return name === 'data-crmui-page' ? 'front.commission' : ''; }
};
var form = {
    0: {reset: function () { resetCount++; input.value = ''; }},
    closest: function () { return page; },
    find: function () { return {first: function () { return input; }}; },
    data: function (name) {
        return name === 'action-url' ? '/api/front/commissions/transfers' : '';
    }
};
{$success}
{$handler}
{$generator}
{$ensurer}
{$request}
var key = ensureCommissionTransferKey(form);
var returned = request({
    url: '/api/front/commissions/transfers',
    method: 'POST',
    headers: {'Idempotency-Key': key}
});
if (returned && returned.done) {
    returned.done(function (response) { handleFormSuccess(form, response); });
}
if (returned && returned.fail) {
    returned.fail(function () {});
}
var malformedKey = ensureCommissionTransferKey(form);
handleFormSuccess(form, {code: 1000, message: 'Transfer completed'});
var rotatedKey = ensureCommissionTransferKey(form);
console.log(JSON.stringify({
    doneCount: doneCount,
    failCount: failCount,
    resetCount: resetCount,
    loadCount: loadCount,
    location: window.location.href,
    key: key,
    requestKey: ajaxOptions && ajaxOptions.headers && ajaxOptions.headers['Idempotency-Key'],
    malformedKey: malformedKey,
    rotatedKey: rotatedKey,
    uuidCount: uuidCount
}));
JS
        );

        $this->assertSame(0, $result['doneCount']);
        $this->assertSame(1, $result['failCount']);
        $this->assertSame(1, $result['resetCount']);
        $this->assertSame(1, $result['loadCount']);
        $this->assertSame('/current', $result['location']);
        $this->assertSame('ct-original-key', $result['key']);
        $this->assertSame('ct-original-key', $result['requestKey']);
        $this->assertSame('ct-original-key', $result['malformedKey']);
        $this->assertSame('ct-crmui-1', $result['rotatedKey']);
        $this->assertSame(1, $result['uuidCount']);
    }

    public function crmUiSourceProvider(): array
    {
        return [
            'front CRMUI' => ['js/apps/crmui/front.js'],
            'admin CRMUI' => ['js/apps/crmui/admin.js'],
        ];
    }

    private function extractFunction(string $source, string $name): string
    {
        $start = strpos($source, 'function ' . $name . '(');
        $this->assertNotFalse($start, $name . ' must exist');
        $next = strpos($source, "\n    function ", $start + 1);
        if ($next === false) {
            $next = strpos($source, "\nfunction ", $start + 1);
        }

        return substr($source, $start, $next === false ? null : $next - $start);
    }

    /**
     * 提取行动作字段相关的纯函数助手。
     *
     * submitRowAction 自注销申请批次起依赖 actionFields/parseFields/parseFieldRules
     * 三个纯函数读取 data-fields/data-field-rules；JS 沙箱必须一并注入才能执行。
     */
    private function crmUiActionFieldHelpers(string $source): string
    {
        // front.js 未实现行动作字段能力，仅在源码确实声明时注入，避免沙箱断言误伤。
        $helpers = '';
        foreach (['parseFields', 'parseFieldRules', 'actionFields', 'validateRequiredActionFields'] as $name) {
            if (strpos($source, 'function ' . $name . '(') !== false) {
                $helpers .= $this->extractFunction($source, $name) . "\n";
            }
        }

        return $helpers;
    }
}
