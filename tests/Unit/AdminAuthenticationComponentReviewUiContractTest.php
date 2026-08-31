<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/22
 * Time: 02:01
 */

/**
 * AdminAuthenticationComponentReviewUiContractTest
 *
 * 文件功能：
 * - 验证实名认证组件化审核 UI 契约：Layui 弹窗仅提交可审核组件载荷、状态门禁拒绝非规范状态串、共享 parse data 适配器、传输失败报告与在途提交防重、CrmUI 严格行结构与请求生命周期。
 * - 输入：内存构造的服务对象、测试替身与必要的数据库夹具；输出：PHPUnit 断言结果。
 * - 明确不负责：不覆盖 HTTP 路由与控制器接线（由 Feature 契约测试锁定）。
 */

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use Tests\Feature\Concerns\ExecutesJavascriptScenarios;

class AdminAuthenticationComponentReviewUiContractTest extends TestCase
{
    use ExecutesJavascriptScenarios;

    private string $root;

    protected function setUp(): void
    {
        parent::setUp();

        $this->root = dirname(__DIR__, 2);
    }

    public function test_layui_review_modal_uses_component_decisions_only(): void
    {
        $blade = $this->source('resources/admin/layui/authentications/index.blade.php');

        foreach (['id_card_decision', 'id_card_reason', 'bank_decision', 'bank_reason'] as $field) {
            $this->assertStringContainsString('name="' . $field . '"', $blade);
        }
        $this->assertStringContainsString('data-auth-review-component="id_card"', $blade);
        $this->assertStringContainsString('data-auth-review-component="bank"', $blade);
        $this->assertStringNotContainsString('name="status"', $blade);
        $this->assertStringNotContainsString('name="reason"', $blade);
        $this->assertSame(2, substr_count($blade, 'maxlength="500"'));
    }

    public function test_layui_script_submits_only_reviewable_component_payload(): void
    {
        $script = $this->source('public/js/apps/admin/layui/pages.js');

        $this->assertStringContainsString('setReviewComponentState', $script);
        $this->assertStringContainsString('buildAuthReviewPayload', $script);
        $this->assertStringContainsString('id_card_decision', $script);
        $this->assertStringContainsString('bank_decision', $script);
        $this->assertStringNotContainsString('status: data.field.status', $script);
        $this->assertStringNotContainsString('reason: data.field.reason', $script);
    }

    public function test_layui_review_status_gate_rejects_non_canonical_status_strings(): void
    {
        $blade = $this->source('resources/admin/layui/authentications/index.blade.php');
        $script = $this->source('public/js/apps/admin/layui/pages.js');

        $this->assertStringNotContainsString('parseInt(d.id_card_status, 10)', $blade);
        $this->assertStringNotContainsString('parseInt(d.bank_status, 10)', $blade);
        $this->assertStringContainsString("String(d.id_card_status) === '1'", $blade);
        $this->assertStringContainsString("String(d.bank_status) === '3'", $blade);

        $this->assertStringNotContainsString('parseInt(row && row[statusField], 10)', $script);
        $this->assertStringContainsString("String(row && row[statusField])", $script);
    }

    public function test_layui_authentication_tables_use_the_shared_parse_data_adapter(): void
    {
        $script = $this->source('public/js/apps/admin/layui/pages.js');
        $start = strpos($script, "registry['authentications/index']");
        $end = strpos($script, "registry['big-agents/index']", $start ?: 0);

        $this->assertNotFalse($start);
        $this->assertNotFalse($end);

        $authenticationModule = substr($script, (int) $start, (int) $end - (int) $start);

        $this->assertSame(2, substr_count($authenticationModule, 'parseData: CrmTable.layuiParseData(),'));
        $this->assertStringNotContainsString('return CrmTable.layuiParseData(response);', $authenticationModule);
    }

    public function test_layui_authentication_review_reports_transport_failures(): void
    {
        $script = $this->source('public/js/apps/admin/layui/pages.js');
        $start = strpos($script, "registry['authentications/index']");
        $end = strpos($script, "registry['big-agents/index']", $start ?: 0);

        $this->assertNotFalse($start);
        $this->assertNotFalse($end);

        $authenticationModule = substr($script, (int) $start, (int) $end - (int) $start);

        $this->assertStringContainsString('error: function (res)', $authenticationModule);
        $this->assertStringContainsString("(res && res.message) || CrmLang.t('common.error')", $authenticationModule);
    }

    public function test_layui_authentication_review_blocks_duplicate_in_flight_submissions(): void
    {
        $authenticationModule = $this->layuiAuthenticationModule();

        $this->assertStringContainsString('var authReviewSubmitting = false;', $authenticationModule);
        $this->assertStringContainsString('if (authReviewSubmitting)', $authenticationModule);
        $this->assertStringContainsString('setAuthReviewSubmitting(true);', $authenticationModule);
        $this->assertGreaterThanOrEqual(2, substr_count($authenticationModule, 'setAuthReviewSubmitting(false);'));
        $this->assertStringContainsString(".prop('disabled', submitting)", $authenticationModule);
    }

    public function test_layui_authentication_review_uses_the_current_row_user_id_only(): void
    {
        $authenticationModule = $this->layuiAuthenticationModule();

        $this->assertStringContainsString('String(row && row.user_id)', $authenticationModule);
        $this->assertStringContainsString('/^[1-9]\\d*$/.test(userId)', $authenticationModule);
        $this->assertStringNotContainsString('fields.user_id ||', $authenticationModule);
    }

    public function test_crmui_review_actions_use_component_fields_and_filter_by_row_status(): void
    {
        $controller = $this->source('app/Http/Controllers/CrmUi/Admin/PageController.php');
        $script = $this->source('public/js/apps/crmui/admin.js');

        $this->assertSame(2, substr_count($controller, "'fields' => \$this->authReviewFields()"));
        foreach (['id_card_decision', 'id_card_reason', 'bank_decision', 'bank_reason'] as $field) {
            $this->assertStringContainsString("'name' => '" . $field . "'", $controller);
            $this->assertStringContainsString("'label' => '" . $field . "'", $controller);
        }
        $fieldsStart = strpos($controller, 'private function authReviewFields(): array');
        $this->assertNotFalse($fieldsStart);
        $fieldsEnd = strpos($controller, 'private function', $fieldsStart + 1);
        $this->assertNotFalse($fieldsEnd);
        // 契约只约束实名认证组件字段：身份证/银行卡两条拒绝原因必须各自限制 500 字。
        // 其他业务（如注销申请审批备注）允许自带独立 maxlength，不再参与全局计数。
        $authReviewFields = substr($controller, (int) $fieldsStart, (int) $fieldsEnd - (int) $fieldsStart);
        $this->assertSame(2, substr_count($authReviewFields, "'maxlength' => 500"));
        $this->assertStringContainsString('field.maxlength', $script);
        $this->assertStringContainsString("' maxlength=\"'", $script);
        $this->assertStringContainsString('isAuthReviewableRow', $script);
        $this->assertStringContainsString('authReviewFieldsForRow', $script);
        $this->assertStringContainsString("actionKey === 'review' || actionKey === 'review_auth'", $script);
    }

    public function test_crmui_auth_review_helpers_handle_row_shapes_strict_statuses_and_payload_validation(): void
    {
        $script = $this->source('public/js/apps/crmui/admin.js');
        $export = 'exports(\'crmuiAdmin\', {init: init, request: request, loadPage: loadPage});';
        $testExport = <<<'JS'
exports('crmuiAdmin', {
    isAuthReviewableRow: isAuthReviewableRow,
    authReviewFieldsForRow: authReviewFieldsForRow,
    buildAuthReviewPayload: buildAuthReviewPayload
});
JS;
        $this->assertStringContainsString($export, $script);
        $script = str_replace($export, $testExport, $script);

        $scenario = <<<'JS'
const fs = require('fs');
const vm = require('vm');
let review;
const sandbox = {
    window: {},
    document: {},
    localStorage: {},
    layui: {
        jquery: {isArray: Array.isArray},
        layer: {msg() {}},
        define(_dependencies, factory) {
            factory((_name, module) => { review = module; });
        },
        use() {}
    }
};
vm.runInNewContext(__ADMIN_SCRIPT__, sandbox, {filename: 'admin.js'});

const fields = [
    {name: 'id_card_decision', label: '证件审核结论'},
    {name: 'id_card_reason', label: '证件拒绝原因'},
    {name: 'bank_decision', label: '银行卡审核结论'},
    {name: 'bank_reason', label: '银行卡拒绝原因'}
];
const names = row => review.authReviewFieldsForRow(row, fields).map(field => field.name);
const invalidIdStatuses = ['1abc', ' 1', '1 ', '01', true, false, '', null];
const invalidBankStatuses = ['1abc', '3abc', ' 1', '3 ', '01', '03', true, false, '', null];
const approved = review.buildAuthReviewPayload(
    {id_card_status: 1, bank_status: 2},
    {id_card_decision: '1', id_card_reason: '', bank_decision: '2', bank_reason: 'must not leak'}
);
const rejectedWithoutReason = review.buildAuthReviewPayload(
    {auth: {id_card_status: 2, bank_status: '3'}},
    {bank_decision: '2', bank_reason: '   '},
    fields
);
const invalidDecision = review.buildAuthReviewPayload(
    {id_card_status: '1', bank_status: 2},
    {id_card_decision: '01', id_card_reason: 'invalid'},
    fields
);
const noReviewable = review.buildAuthReviewPayload(
    {id_card_status: 2, bank_status: 2},
    {},
    [],
    '暂无可审核组件'
);

console.log(JSON.stringify({
    flatIdFields: names({id_card_status: 1, bank_status: 2}),
    flatBankFields: names({id_card_status: 2, bank_status: '3'}),
    nestedFields: names({auth: {id_card_status: '1', bank_status: 1}}),
    nestedStatusWins: !review.isAuthReviewableRow({
        id_card_status: 1,
        bank_status: 3,
        auth: {id_card_status: 2, bank_status: 2}
    }),
    certifiedReviewable: review.isAuthReviewableRow({id_card_status: 2, bank_status: 2}),
    invalidStatusesBlocked:
        invalidIdStatuses.every(status => !review.isAuthReviewableRow({id_card_status: status, bank_status: 2})) &&
        invalidBankStatuses.every(status => !review.isAuthReviewableRow({id_card_status: 2, bank_status: status})),
    approvedPayload: approved.payload,
    approvedError: approved.error,
    missingReasonError: rejectedWithoutReason.error,
    invalidDecisionError: invalidDecision.error,
    noReviewableError: noReviewable.error
}));
JS;
        $scenario = str_replace('__ADMIN_SCRIPT__', json_encode($script, JSON_THROW_ON_ERROR), $scenario);
        $result = $this->executeJavascriptJson($scenario);

        $this->assertSame(['id_card_decision', 'id_card_reason'], $result['flatIdFields']);
        $this->assertSame(['bank_decision', 'bank_reason'], $result['flatBankFields']);
        $this->assertSame(['id_card_decision', 'id_card_reason', 'bank_decision', 'bank_reason'], $result['nestedFields']);
        $this->assertTrue($result['nestedStatusWins']);
        $this->assertFalse($result['certifiedReviewable']);
        $this->assertTrue($result['invalidStatusesBlocked']);
        $this->assertSame(['id_card_decision' => '1'], $result['approvedPayload']);
        $this->assertSame('', $result['approvedError']);
        $this->assertSame('银行卡拒绝原因', $result['missingReasonError']);
        $this->assertSame('证件审核结论', $result['invalidDecisionError']);
        $this->assertSame('暂无可审核组件', $result['noReviewableError']);

        $this->assertStringNotContainsString('Select an approval decision', $script);
        $this->assertStringNotContainsString('Enter a rejection reason', $script);
        $this->assertStringNotContainsString('No reviewable authentication component.', $script);
    }

    public function test_crmui_review_submit_reports_validation_error_before_request(): void
    {
        $script = $this->source('public/js/apps/crmui/admin.js');
        $submitStart = strpos($script, "$(document).on('submit', '[data-crmui-action-form]'");
        $submitEnd = strpos($script, 'function bindShell()', $submitStart ?: 0);

        $this->assertNotFalse($submitStart);
        $this->assertNotFalse($submitEnd);
        $submitHandler = substr($script, (int) $submitStart, (int) $submitEnd - (int) $submitStart);

        $this->assertStringContainsString('buildAuthReviewPayload', $submitHandler);
        $this->assertStringContainsString('layer.msg(review.error', $submitHandler);
        $this->assertStringContainsString('submitRowAction($button, row, review.payload, $modal);', $submitHandler);
    }

    public function test_crmui_auth_detail_helpers_validate_read_data_and_component_review_payload(): void
    {
        $script = $this->source('public/js/apps/crmui/admin.js');
        foreach ([
            'function authDetailRecord',
            'function safeCrmUiAuthDetailImageUrl',
            'function buildCrmUiAuthDetailReviewPayload',
            'function loadCrmUiAuthDetail',
            'function renderCrmUiAuthDetail',
            'function bindCrmUiAuthDetail',
        ] as $function) {
            $this->assertStringContainsString($function, $script, $function);
        }

        $export = 'exports(\'crmuiAdmin\', {init: init, request: request, loadPage: loadPage});';
        $testExport = <<<'JS'
exports('crmuiAdmin', {
    authDetailRecord: authDetailRecord,
    safeCrmUiAuthDetailImageUrl: safeCrmUiAuthDetailImageUrl,
    buildCrmUiAuthDetailReviewPayload: buildCrmUiAuthDetailReviewPayload
});
JS;
        $this->assertStringContainsString($export, $script);
        $script = str_replace($export, $testExport, $script);

        $scenario = <<<'JS'
const vm = require('vm');
let detailHelpers;
const sandbox = {
    window: {},
    document: {},
    localStorage: {},
    layui: {
        jquery: {isArray: Array.isArray},
        layer: {msg() {}},
        define(_dependencies, factory) {
            factory((_name, module) => { detailHelpers = module; });
        },
        use() {}
    }
};
vm.runInNewContext(__ADMIN_SCRIPT__, sandbox, {filename: 'admin.js'});

const fields = [
    {name: 'id_card_decision', label: '证件审核结论'},
    {name: 'id_card_reason', label: '证件拒绝原因'},
    {name: 'bank_decision', label: '银行卡审核结论'},
    {name: 'bank_reason', label: '银行卡拒绝原因'}
];
const detail = {
    user_id: 984205,
    id_card_status: 1,
    bank_status: 2,
    id_card_front_url: '/storage/auth/id-front.jpg'
};
const review = detailHelpers.buildCrmUiAuthDetailReviewPayload(
    detail,
    {
        id_card_decision: '2',
        id_card_reason: '资料不清晰',
        bank_decision: '1',
        bank_reason: 'must not leak'
    },
    '984205',
    fields,
    '暂无可审核组件'
);
const invalidUser = detailHelpers.buildCrmUiAuthDetailReviewPayload(
    detail,
    {id_card_decision: '1'},
    '984206',
    fields,
    '暂无可审核组件'
);

console.log(JSON.stringify({
    acceptedRecord: detailHelpers.authDetailRecord({data: detail}, '984205').user_id,
    mismatchedRecord: detailHelpers.authDetailRecord({data: detail}, '984206'),
    emptyRecord: detailHelpers.authDetailRecord({data: {}}, '984205'),
    rootImage: detailHelpers.safeCrmUiAuthDetailImageUrl('/storage/auth/id-front.jpg'),
    httpsImage: detailHelpers.safeCrmUiAuthDetailImageUrl('https://assets.example.test/id.jpg'),
    protocolRelativeImage: detailHelpers.safeCrmUiAuthDetailImageUrl('//evil.example.test/id.jpg'),
    javascriptImage: detailHelpers.safeCrmUiAuthDetailImageUrl('javascript:alert(1)'),
    reviewPayload: review.payload,
    reviewError: review.error,
    invalidUserError: invalidUser.error
}));
JS;
        $scenario = str_replace('__ADMIN_SCRIPT__', json_encode($script, JSON_THROW_ON_ERROR), $scenario);
        $result = $this->executeJavascriptJson($scenario);

        $this->assertSame(984205, $result['acceptedRecord']);
        $this->assertNull($result['mismatchedRecord']);
        $this->assertNull($result['emptyRecord']);
        $this->assertSame('/storage/auth/id-front.jpg', $result['rootImage']);
        $this->assertSame('https://assets.example.test/id.jpg', $result['httpsImage']);
        $this->assertSame('', $result['protocolRelativeImage']);
        $this->assertSame('', $result['javascriptImage']);
        $this->assertSame([
            'user_id' => '984205',
            'id_card_decision' => '2',
            'id_card_reason' => '资料不清晰',
        ], $result['reviewPayload']);
        $this->assertSame('', $result['reviewError']);
        $this->assertNotSame('', $result['invalidUserError']);
    }

    public function test_crmui_auth_detail_init_and_submit_follow_the_real_request_lifecycle(): void
    {
        $script = $this->source('public/js/apps/crmui/admin.js');
        $requestStart = strpos($script, '    function request(options) {');
        $requestEnd = strpos($script, "\n    function messageFromResponse", $requestStart ?: 0);

        $this->assertNotFalse($requestStart);
        $this->assertNotFalse($requestEnd);

        $requestStub = <<<'JS'
    function request(options) {
        return window.__request(options);
    }
JS;
        $script = substr($script, 0, (int) $requestStart)
            . $requestStub
            . substr($script, (int) $requestEnd);

        $scenario = <<<'JS'
const vm = require('vm');
const handlers = {};
const requests = [];
const layerMessages = [];
const states = ['loading', 'error', 'empty', 'content'].map(state => ({
    kind: 'state', attrs: {'data-crmui-auth-state': state}, props: {}
}));
const userNameField = {
    kind: 'display-field',
    attrs: {'data-crmui-auth-field': 'user_name'},
    props: {},
    text: ''
};
const authError = {kind: 'error', attrs: {}, props: {}, text: 'initial error'};
const page = {
    kind: 'page',
    attrs: {
        'data-crmui-auth-detail': '1',
        'data-crmui-auth-user-id': '984205',
        'data-api-url': '/api/admin/authDetail',
        'data-review-url': '/api/admin/reviewAuth',
        'data-no-reviewable-text': '暂无可审核组件'
    },
    dataStore: {},
    props: {}
};
const submitButton = {kind: 'submit', attrs: {}, dataStore: {}, props: {}};
const componentFields = [
    {name: 'id_card_decision', label: '证件审核结论'},
    {name: 'id_card_reason', label: '证件拒绝原因'},
    {name: 'bank_decision', label: '银行卡审核结论'},
    {name: 'bank_reason', label: '银行卡拒绝原因'}
].map(field => ({
    kind: 'field',
    name: field.name,
    attrs: {name: field.name, 'data-label': field.label},
    dataStore: {},
    props: {}
}));
const reviewComponents = {
    id_card: {kind: 'component', attrs: {}, dataStore: {}, props: {}},
    bank: {kind: 'component', attrs: {}, dataStore: {}, props: {}}
};
const form = {
    kind: 'form',
    attrs: {},
    dataStore: {},
    props: {},
    fields: {
        id_card_decision: '2',
        id_card_reason: '资料不清晰',
        bank_decision: '1',
        bank_reason: '不得提交'
    },
    resetCount: 0,
    reset() {
        this.resetCount += 1;
    }
};

function findFor(element, selector) {
    if (element === page) {
        if (selector === '[data-crmui-auth-state]') return states;
        if (selector === '[data-crmui-auth-review-form]') return [form];
        if (selector === '[data-crmui-auth-field]') return [userNameField];
        if (selector === '[data-crmui-auth-error]') return [authError];
        return [];
    }
    if (element === form) {
        if (selector === '[name]') return componentFields;
        if (selector === 'button[type="submit"]') return [submitButton];
        if (selector === '[data-crmui-auth-review-component="id_card"]') return [reviewComponents.id_card];
        if (selector === '[data-crmui-auth-review-component="bank"]') return [reviewComponents.bank];
        return [];
    }
    return [];
}

class Collection {
    constructor(items) {
        this.items = items || [];
        this.length = this.items.length;
        this.items.forEach((item, index) => { this[index] = item; });
    }
    each(callback) {
        this.items.forEach((item, index) => callback.call(item, index, item));
        return this;
    }
    map(callback) {
        const values = this.items.map((item, index) => callback.call(item, index, item));
        return {get() { return values; }};
    }
    on(event, selector, handler) {
        handlers[event + ' ' + selector] = handler;
        return this;
    }
    off(event) {
        const namespace = String(event || '');
        Object.keys(handlers).forEach(key => {
            if (namespace === '' || key.indexOf(namespace) !== -1) {
                delete handlers[key];
            }
        });
        return this;
    }
    attr(name, value) {
        const item = this.items[0];
        if (!item) return value === undefined ? undefined : this;
        item.attrs = item.attrs || {};
        if (value === undefined) return item.attrs[name];
        this.items.forEach(current => { current.attrs[name] = value; });
        return this;
    }
    removeAttr(name) {
        this.items.forEach(item => { if (item.attrs) delete item.attrs[name]; });
        return this;
    }
    data(name, value) {
        const item = this.items[0];
        if (!item) return value === undefined ? undefined : this;
        item.dataStore = item.dataStore || {};
        if (value === undefined) return item.dataStore[name];
        this.items.forEach(current => {
            current.dataStore = current.dataStore || {};
            current.dataStore[name] = value;
        });
        return this;
    }
    removeData(name) {
        this.items.forEach(item => { if (item.dataStore) delete item.dataStore[name]; });
        return this;
    }
    prop(name, value) {
        const item = this.items[0];
        if (!item) return value === undefined ? undefined : this;
        item.props = item.props || {};
        if (value === undefined) return item.props[name];
        this.items.forEach(current => { current.props[name] = value; });
        return this;
    }
    text(value) {
        if (value === undefined) return this.items[0] && this.items[0].text || '';
        this.items.forEach(item => { item.text = value; });
        return this;
    }
    find(selector) {
        return new Collection(this.items.length ? findFor(this.items[0], selector) : []);
    }
    closest() {
        return new Collection([page]);
    }
    serializeArray() {
        const item = this.items[0];
        return Object.keys(item && item.fields || {}).map(name => ({name, value: item.fields[name]}));
    }
}

const documentObject = {kind: 'document', attrs: {}, dataStore: {}, props: {}};
function jquery(value) {
    if (value === documentObject) return new Collection([documentObject]);
    if (value === '.crmui-page') return new Collection([page]);
    if (value && typeof value === 'object') return new Collection([value]);
    return new Collection([]);
}
jquery.isArray = Array.isArray;
jquery.each = (items, callback) => items.forEach((item, index) => callback(index, item));

function requestStub(options) {
    const doneCallbacks = [];
    const alwaysCallbacks = [];
    const deferred = {
        options,
        done(callback) {
            doneCallbacks.push(callback);
            return this;
        },
        always(callback) {
            alwaysCallbacks.push(callback);
            return this;
        },
        resolve(value) {
            doneCallbacks.forEach(callback => callback(value));
            alwaysCallbacks.forEach(callback => callback());
        },
        reject(error) {
            if (typeof options.onError === 'function') options.onError(error);
            alwaysCallbacks.forEach(callback => callback());
        }
    };
    requests.push(deferred);
    return deferred;
}

const sandbox = {
    window: {__request: requestStub},
    document: documentObject,
    localStorage: {getItem() { return null; }, setItem() {}, removeItem() {}},
    URL,
    console,
    layui: {
        jquery,
        layer: {msg(message) { layerMessages.push(message); }},
        define(_dependencies, factory) {
            factory((name, module) => { this[name] = module; });
        },
        use(_dependencies, callback) {
            callback();
        }
    }
};

vm.runInNewContext(__ADMIN_SCRIPT__, sandbox, {filename: 'admin.js'});
const requestsFor = url => requests.filter(item => item.options.url === url);
const requestAt = (url, index) => requestsFor(url)[index];
const initSnapshot = {
    requestCount: requests.length,
    menuRequests: requestsFor('/api/admin/menus').length,
    menuMethod: requestsFor('/api/admin/menus')[0] && requestsFor('/api/admin/menus')[0].options.method,
    url: requestsFor('/api/admin/authDetail')[0] && requestsFor('/api/admin/authDetail')[0].options.url,
    reviewRequests: requestsFor('/api/admin/reviewAuth').length
};

const retryHandler = handlers['click [data-crmui-auth-retry]'];
const retryButton = {kind: 'retry', attrs: {}, dataStore: {}, props: {}};
function detailSnapshot() {
    const activeState = states.filter(item => item.props.hidden === false)[0];
    const record = page.dataStore.authDetailRecord || {};
    return {
        recordName: record.user_name || '',
        displayedName: userNameField.text,
        activeState: activeState && activeState.attrs['data-crmui-auth-state'],
        idCardHidden: Boolean(reviewComponents.id_card.props.hidden),
        bankHidden: Boolean(reviewComponents.bank.props.hidden),
        errorText: authError.text
    };
}

retryHandler.call(retryButton);
requestAt('/api/admin/authDetail', 1).resolve({data: {
    user_id: 984205,
    user_name: 'latest success',
    id_card_status: 1,
    bank_status: 2
}});
const latestSuccessSnapshot = detailSnapshot();
requestAt('/api/admin/authDetail', 0).resolve({data: {
    user_id: 984205,
    user_name: 'stale success',
    id_card_status: 2,
    bank_status: 1
}});
const staleSuccessSnapshot = detailSnapshot();

retryHandler.call(retryButton);
retryHandler.call(retryButton);
requestAt('/api/admin/authDetail', 3).resolve({data: {
    user_id: 984205,
    user_name: 'latest after stale failure',
    id_card_status: 2,
    bank_status: 3
}});
const latestBeforeStaleFailureSnapshot = detailSnapshot();
requestAt('/api/admin/authDetail', 2).reject({responseJSON: {message: 'stale failure'}});
const staleFailureSnapshot = detailSnapshot();

const submitHandler = handlers['submit [data-crmui-auth-review-form]'];
const submitEvent = {preventDefault() {}};
submitHandler.call(form, submitEvent);
submitHandler.call(form, submitEvent);
const duplicateSnapshot = {
    requestCount: requests.length,
    pending: form.dataStore.requestPending,
    disabled: submitButton.props.disabled
};

requestAt('/api/admin/reviewAuth', 0).reject({responseJSON: {message: '审核失败'}});
const failureSnapshot = {
    requestCount: requests.length,
    pending: Boolean(form.dataStore.requestPending),
    disabled: Boolean(submitButton.props.disabled),
    reason: form.fields.id_card_reason,
    resetCount: form.resetCount,
    authDetailRequests: requests.filter(item => item.options.url === '/api/admin/authDetail').length
};

submitHandler.call(form, submitEvent);
requestAt('/api/admin/reviewAuth', 1).resolve({message: '审核成功'});
requestAt('/api/admin/authDetail', 4).resolve({data: {
    user_id: 984205,
    user_name: 'latest after review',
    id_card_status: 2,
    bank_status: 3
}});
const successSnapshot = {
    requestCount: requests.length,
    pending: Boolean(form.dataStore.requestPending),
    disabled: Boolean(submitButton.props.disabled),
    resetCount: form.resetCount,
    authDetailRequests: requests.filter(item => item.options.url === '/api/admin/authDetail').length,
    reviewRequests: requests.filter(item => item.options.url === '/api/admin/reviewAuth').length,
    lastUrl: requests[requests.length - 1].options.url
};

console.log(JSON.stringify({
    initSnapshot,
    latestSuccessSnapshot,
    staleSuccessSnapshot,
    latestBeforeStaleFailureSnapshot,
    staleFailureSnapshot,
    duplicateSnapshot,
    failureSnapshot,
    successSnapshot,
    layerMessages
}));
JS;
        $scenario = str_replace('__ADMIN_SCRIPT__', json_encode($script, JSON_THROW_ON_ERROR), $scenario);
        $result = $this->executeJavascriptJson($scenario);

        $this->assertSame([
            'requestCount' => 2,
            'menuRequests' => 1,
            'menuMethod' => 'POST',
            'url' => '/api/admin/authDetail',
            'reviewRequests' => 0,
        ], $result['initSnapshot']);
        $latestSuccess = [
            'recordName' => 'latest success',
            'displayedName' => 'latest success',
            'activeState' => 'content',
            'idCardHidden' => false,
            'bankHidden' => true,
            'errorText' => 'initial error',
        ];
        $this->assertSame($latestSuccess, $result['latestSuccessSnapshot']);
        $this->assertSame($latestSuccess, $result['staleSuccessSnapshot']);

        $latestAfterFailure = [
            'recordName' => 'latest after stale failure',
            'displayedName' => 'latest after stale failure',
            'activeState' => 'content',
            'idCardHidden' => true,
            'bankHidden' => false,
            'errorText' => 'initial error',
        ];
        $this->assertSame($latestAfterFailure, $result['latestBeforeStaleFailureSnapshot']);
        $this->assertSame($latestAfterFailure, $result['staleFailureSnapshot']);

        $this->assertSame(['requestCount' => 6, 'pending' => true, 'disabled' => true], $result['duplicateSnapshot']);
        $this->assertSame([
            'requestCount' => 6,
            'pending' => false,
            'disabled' => false,
            'reason' => '资料不清晰',
            'resetCount' => 0,
            'authDetailRequests' => 4,
        ], $result['failureSnapshot']);
        $this->assertSame([
            'requestCount' => 8,
            'pending' => false,
            'disabled' => false,
            'resetCount' => 1,
            'authDetailRequests' => 5,
            'reviewRequests' => 2,
            'lastUrl' => '/api/admin/authDetail',
        ], $result['successSnapshot']);
        $this->assertSame(['审核失败', '审核成功'], $result['layerMessages']);
    }

    public function test_processor_enforces_component_reviewability_before_transition(): void
    {
        $source = $this->source('app/Services/AdminAuthReviewProcessor.php');

        $reviewabilityCheck = strpos($source, 'AuthReviewTransition::assertReviewableComponents');
        $transition = strpos($source, 'AuthReviewTransition::resolve');

        $this->assertNotFalse($reviewabilityCheck);
        $this->assertNotFalse($transition);
        $this->assertLessThan($transition, $reviewabilityCheck);
    }

    private function source(string $path): string
    {
        $source = file_get_contents($this->root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $path));
        $this->assertNotFalse($source, $path);

        return $source;
    }

    private function layuiAuthenticationModule(): string
    {
        $script = $this->source('public/js/apps/admin/layui/pages.js');
        $start = strpos($script, "registry['authentications/index']");
        $end = strpos($script, "registry['big-agents/index']", $start ?: 0);

        $this->assertNotFalse($start);
        $this->assertNotFalse($end);

        return substr($script, (int) $start, (int) $end - (int) $start);
    }
}
