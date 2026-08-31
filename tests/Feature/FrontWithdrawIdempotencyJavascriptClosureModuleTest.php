<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/07/17
 * Time: 20:56
 */

/**
 * FrontWithdrawIdempotencyJavascriptClosureModuleTest
 *
 * 文件功能：
 * - 验证前台出金幂等 JS 契约：确定性响应前复用幂等键、本地 1xxx/2xxx 判定规则、规范化金额绑定与不确定键重放、按用户隔离的持久化存储、存储失败失败关闭与多语言提示。
 * - 输入：路由、控制器、Blade/JS、迁移等项目源码文本；输出：PHPUnit 断言结果。
 * - 明确不负责：不覆盖 HTTP 运行时业务流与 MT4 真实网关同步（由集成与功能测试锁定）。
 */

declare(strict_types=1);

namespace Tests\Feature;

use PHPUnit\Framework\AssertionFailedError;
use Tests\Feature\Concerns\ExecutesJavascriptScenarios;
use Tests\Feature\Concerns\ReadsAggregatedLayuiScripts;
use Tests\TestCase;

class FrontWithdrawIdempotencyJavascriptClosureModuleTest extends TestCase
{
    use ExecutesJavascriptScenarios;
    use ReadsAggregatedLayuiScripts;

    public function test_layui_withdraw_submission_reuses_the_key_until_a_definitive_response(): void
    {
        $source = $this->frontLayuiScript('withdraw/index.js');

        foreach ([
            'var withdrawIdempotencyKey = null;',
            'var withdrawIdempotencyAmount = null;',
            'var withdrawSubmitting = false;',
            'function currentWithdrawIdempotencyKey(amount)',
            'function clearWithdrawIdempotencyKey()',
            'function setWithdrawSubmitting(submitting)',
            "headers: {'Idempotency-Key': requestKey}",
            'var amount = normalizeWithdrawAmount(field.amount);',
            'if (withdrawSubmitting)',
        ] as $expected) {
            $this->assertStringContainsString($expected, $source);
        }
        $submission = $this->withdrawSubmitSource($source);
        $this->assertStringNotContainsString(
            'var amount = Number(field.amount || 0);',
            $submission
        );

        $submitOffset = strpos($submission, "url: '/api/front/withdrawals/submissions'");
        $this->assertNotFalse($submitOffset);
        $success = $this->javascriptCallbackBody(
            $submission,
            'success: function (res) {',
            'error: function (res) {',
            $submitOffset
        );
        $this->assertStringContainsString('if (!clearWithdrawIdempotencyKey())', $success);
        $this->assertLessThan(
            strpos($success, 'if (!clearWithdrawIdempotencyKey())'),
            strpos($success, 'if (!isSuccess(res))'),
            'The response must be classified before the action key is cleared.'
        );

        $networkError = $this->javascriptCallbackBody(
            $submission,
            'error: function (res) {',
            '});',
            $submitOffset
        );
        $this->assertStringContainsString('setWithdrawSubmitting(false);', $networkError);
        $this->assertStringContainsString('if (!clearWithdrawIdempotencyKey())', $networkError);
        $this->assertLessThan(
            strpos($networkError, 'if (!clearWithdrawIdempotencyKey())'),
            strpos($networkError, 'res.code < 5000'),
            'Transport errors may clear the key only after checking an explicit sub-5000 code.'
        );
    }

    /** @dataProvider layuiWithdrawKeyLifecycleProvider */
    public function test_layui_withdraw_key_lifecycle_matches_response_certainty(
        array $response,
        string $transport,
        bool $expectedKeyRetained,
        int $expectedResetCount,
        int $expectedMessageIcon
    ): void {
        $result = $this->executeWithdrawJavascriptScenario($response, $transport);

        $this->assertSame(1, $result['requestCount']);
        $this->assertSame(1, $result['uuidCount']);
        $this->assertSame(['wdr-scenario-uuid-1'], $result['requestHeaders']);
        $this->assertSame('100.00', $result['requestPayloads'][0]['amount']);
        $this->assertSame('100.00', $result['requestPayloads'][0]['withdraw_amt']);
        $this->assertFalse($result['submitting']);
        $this->assertSame($expectedKeyRetained, $result['keyRetained']);
        $this->assertSame($expectedResetCount, $result['resetCount']);
        $this->assertSame($expectedResetCount, $result['loadCount']);
        $this->assertSame($expectedResetCount, $result['historyCount']);
        $this->assertSame([$expectedMessageIcon], $result['messageIcons']);
    }

    public function layuiWithdrawKeyLifecycleProvider(): array
    {
        return [
            '1xxx success clears and resets' => [
                ['code' => 1001, 'message' => 'created'], 'success', false, 1, 1,
            ],
            '2xxx business failure clears without reset' => [
                ['code' => 2000, 'message' => 'business failure'], 'success', false, 0, 2,
            ],
            '4xxx business failure clears without reset' => [
                ['code' => 4005, 'message' => 'validation failure'], 'success', false, 0, 2,
            ],
            '5xxx success callback retains for retry' => [
                ['code' => 5000, 'message' => 'server failure'], 'success', true, 0, 2,
            ],
            '4xxx error callback clears without reset' => [
                ['code' => 4001, 'message' => 'authentication failure'], 'error', false, 0, 2,
            ],
            '5xxx network response retains for retry' => [
                ['code' => 5000, 'message' => 'network failure'], 'error', true, 0, 2,
            ],
            'no-code network failure retains for retry' => [
                ['message' => 'connection closed'], 'error', true, 0, 2,
            ],
        ];
    }

    public function test_layui_withdraw_validation_does_not_use_the_local_available_mirror(): void
    {
        $source = $this->frontLayuiScript('withdraw/index.js');
        $validation = $this->javascriptFunctionSource($source, 'validateSubmit');

        $this->assertStringNotContainsString(
            'amount > pageData.availableAmount',
            $validation
        );
    }

    public function test_layui_withdraw_executes_the_request_when_only_local_available_is_low(): void
    {
        $source = $this->frontLayuiScript('withdraw/index.js');
        $createKey = $this->javascriptFunctionSource($source, 'createWithdrawIdempotencyKey');
        $normalizer = $this->javascriptFunctionSource($source, 'normalizeWithdrawAmount');
        $storageKey = $this->javascriptFunctionSource($source, 'withdrawIdempotencyStorageKey');
        $restoreState = $this->javascriptFunctionSource($source, 'restoreWithdrawIdempotencyState');
        $persistState = $this->javascriptFunctionSource($source, 'persistWithdrawIdempotencyState');
        $prepareKey = $this->javascriptFunctionSource($source, 'prepareWithdrawIdempotencyKey');
        $currentKey = $this->javascriptFunctionSource($source, 'currentWithdrawIdempotencyKey');
        $clearKey = $this->javascriptFunctionSource($source, 'clearWithdrawIdempotencyKey');
        $validation = $this->javascriptFunctionSource($source, 'validateSubmit');
        $submission = $this->javascriptFunctionSource($source, 'submitWithdraw');
        $result = $this->executeJavascriptJson(<<<JS
'use strict';
var pageData = {
    isAllowed: true,
    min: 10,
    max: 500000,
    availableAmount: 1,
    feeRate: 0,
    fixedFee: 0
};
var withdrawIdempotencyKey = null;
var withdrawIdempotencyAmount = null;
var withdrawIdempotencyUserId = null;
var withdrawIdempotencyStorageReady = true;
var withdrawIdempotencyFailureReason = null;
var withdrawSubmitting = false;
var requestCalled = false;
var requestHeaders = [];
var requestPayloads = [];
var uuidCounter = 0;
var idempotencyStorage = {};
var window = {
    localStorage: {
        getItem: function (key) { return Object.prototype.hasOwnProperty.call(idempotencyStorage, key) ? idempotencyStorage[key] : null; },
        setItem: function (key, value) { idempotencyStorage[key] = String(value); },
        removeItem: function (key) { delete idempotencyStorage[key]; }
    },
    crypto: {randomUUID: function () { uuidCounter++; return 'local-uuid-' + uuidCounter; }}
};
var layer = {msg: function () {}};
var form = {render: function () {}};
var CrmAjax = {request: function (options) {
    requestCalled = true;
    requestHeaders.push(options.headers['Idempotency-Key']);
    requestPayloads.push(options.data);
}};
function t(key) { return key; }
function setWithdrawSubmitting(value) { withdrawSubmitting = !!value; }
function isSuccess(res) { return !!res; }
function fillPageFields() {}
function loadPageConfig() {}
function renderHistoryTable() {}
function $(selector) {
    return {
        is: function () { return selector === '#withdrawAgree'; },
        prop: function () { return this; },
        toggleClass: function () { return this; },
        val: function () { return selector === '#withdrawUserId' ? '412372001' : ''; }
    };
}
{$createKey}
{$normalizer}
{$storageKey}
{$restoreState}
{$persistState}
{$prepareKey}
{$currentKey}
{$clearKey}
{$validation}
{$submission}
submitWithdraw({amount: '100.0', password: 'password'});
console.log(JSON.stringify({
    requestCalled: requestCalled,
    requestHeaders: requestHeaders,
    requestPayloads: requestPayloads,
    uuidCount: uuidCounter,
    boundAmount: withdrawIdempotencyAmount
}));
JS
        );

        $this->assertTrue($result['requestCalled']);
        $this->assertSame(['wdr-local-uuid-1'], $result['requestHeaders']);
        $this->assertSame(1, $result['uuidCount']);
        $this->assertSame('100.00', $result['boundAmount']);
        $this->assertSame('100.00', $result['requestPayloads'][0]['amount']);
        $this->assertSame('100.00', $result['requestPayloads'][0]['withdraw_amt']);
    }

    public function test_layui_withdraw_uses_a_local_1xxx_success_rule(): void
    {
        $source = $this->frontLayuiScript('withdraw/index.js');
        $successRule = $this->javascriptFunctionSource($source, 'isSuccess');

        $this->assertStringContainsString(
            'res.code >= 1000 && res.code < 2000',
            $successRule
        );
        $this->assertStringNotContainsString('CrmTable.isSuccess', $successRule);
    }

    public function test_layui_withdraw_rejects_2xxx_without_success_side_effects(): void
    {
        $accepted = $this->executeWithdrawJavascriptScenario([
            'code' => 1001,
            'message' => 'accepted',
        ]);
        $businessFailure = $this->executeWithdrawJavascriptScenario([
            'code' => 2000,
            'message' => 'business failure',
        ]);

        $this->assertSame(1, $accepted['resetCount']);
        $this->assertSame(1, $accepted['loadCount']);
        $this->assertSame(1, $accepted['historyCount']);
        $this->assertSame([1], $accepted['messageIcons']);
        $this->assertSame(0, $businessFailure['resetCount']);
        $this->assertSame(0, $businessFailure['loadCount']);
        $this->assertSame(0, $businessFailure['historyCount']);
        $this->assertSame([2], $businessFailure['messageIcons']);
    }

    public function test_layui_withdraw_binds_the_key_to_a_plain_string_normalized_amount(): void
    {
        $source = $this->frontLayuiScript('withdraw/index.js');

        $this->assertStringContainsString('var withdrawIdempotencyAmount = null;', $source);
        $this->assertStringContainsString('function normalizeWithdrawAmount(value)', $source);
        $normalizer = $this->javascriptFunctionSource($source, 'normalizeWithdrawAmount');
        $prepareKey = $this->javascriptFunctionSource($source, 'prepareWithdrawIdempotencyKey');
        $clearKey = $this->javascriptFunctionSource($source, 'clearWithdrawIdempotencyKey');
        $this->assertStringNotContainsString('Number(', $normalizer);
        $this->assertStringContainsString('withdrawIdempotencyAmount', $prepareKey);
        $this->assertStringContainsString('persistWithdrawIdempotencyState', $prepareKey);
        $this->assertStringContainsString('withdrawIdempotencyAmount = null;', $clearKey);
    }

    public function test_layui_withdraw_reuses_an_uncertain_key_only_for_the_same_normalized_amount(): void
    {
        $result = $this->executeWithdrawJavascriptSequence([
            ['amount' => '100', 'response' => ['code' => 5000, 'message' => 'uncertain']],
            ['amount' => '100.0', 'response' => ['code' => 5000, 'message' => 'uncertain']],
            ['amount' => '101.00', 'response' => ['code' => 5000, 'message' => 'must not send']],
        ]);

        $this->assertCount(2, $result['requestHeaders']);
        $this->assertSame(['wdr-uuid-1', 'wdr-uuid-1'], $result['requestHeaders']);
        $this->assertSame(1, $result['uuidCount']);
        $this->assertSame(['100.00', '100.00'], array_column($result['requestPayloads'], 'amount'));
        $this->assertSame(['100.00', '100.00'], array_column($result['requestPayloads'], 'withdraw_amt'));
        $this->assertTrue($result['keyRetained']);
        $this->assertSame('100.00', $result['boundAmount']);
        $this->assertContains('front.withdraw_retry_original_amount', $result['messages']);
    }

    public function test_layui_withdraw_uses_a_new_key_after_a_definitive_2xxx_response(): void
    {
        $result = $this->executeWithdrawJavascriptSequence([
            ['amount' => '100.00', 'response' => ['code' => 2000, 'message' => 'definitive']],
            ['amount' => '101.00', 'response' => ['code' => 5000, 'message' => 'uncertain']],
        ]);

        $this->assertCount(2, $result['requestHeaders']);
        $this->assertSame(['wdr-uuid-1', 'wdr-uuid-2'], $result['requestHeaders']);
        $this->assertSame(2, $result['uuidCount']);
        $this->assertSame(['100.00', '101.00'], array_column($result['requestPayloads'], 'amount'));
        $this->assertSame(['100.00', '101.00'], array_column($result['requestPayloads'], 'withdraw_amt'));
        $this->assertTrue($result['keyRetained']);
        $this->assertSame('101.00', $result['boundAmount']);
    }

    public function test_layui_withdraw_clears_the_uncertain_key_only_after_a_1xxx_replay(): void
    {
        $result = $this->executeWithdrawJavascriptSequence([
            ['amount' => '100', 'response' => ['code' => 5000, 'message' => 'uncertain']],
            ['amount' => '100.0', 'response' => ['code' => 1001, 'message' => 'replayed']],
        ]);

        $this->assertSame(['wdr-uuid-1', 'wdr-uuid-1'], $result['requestHeaders']);
        $this->assertSame(1, $result['uuidCount']);
        $this->assertSame(['100.00', '100.00'], array_column($result['requestPayloads'], 'amount'));
        $this->assertFalse($result['keyRetained']);
        $this->assertNull($result['boundAmount']);
        $this->assertSame(1, $result['resetCount']);
    }

    public function test_layui_withdraw_reuses_an_uncertain_key_after_reload_and_blocks_a_changed_amount(): void
    {
        $result = $this->executeWithdrawPersistenceJavascriptScenario([
            [
                'userId' => '412372001',
                'amount' => '100',
                'transport' => 'error',
                'response' => ['code' => 5000, 'message' => 'network uncertain'],
            ],
            [
                'userId' => '412372001',
                'amount' => '100.0',
                'transport' => 'success',
                'response' => ['code' => 5000, 'message' => 'server uncertain'],
            ],
            [
                'userId' => '412372001',
                'amount' => '101.00',
                'transport' => 'success',
                'response' => ['code' => 5000, 'message' => 'must not send'],
            ],
        ]);

        $this->assertSame(2, $result['requestCount']);
        $this->assertSame(['wdr-persisted-uuid-1', 'wdr-persisted-uuid-1'], $result['requestHeaders']);
        $this->assertSame(['100.00', '100.00'], array_column($result['requestPayloads'], 'amount'));
        $this->assertSame(1, $result['uuidCount']);
        $this->assertContains('front.withdraw_retry_original_amount', $result['messages']);
        $this->assertSame('100.00', $result['storedStates']['412372001']['normalizedAmount']);
    }

    public function test_layui_withdraw_terminal_responses_clear_storage_before_a_new_key_is_created(): void
    {
        $result = $this->executeWithdrawPersistenceJavascriptScenario([
            ['userId' => '412372001', 'amount' => '100', 'response' => ['code' => 5000, 'message' => 'uncertain']],
            ['userId' => '412372001', 'amount' => '100.0', 'response' => ['code' => 2000, 'message' => 'definitive failure']],
            ['userId' => '412372001', 'amount' => '100.00', 'response' => ['code' => 5000, 'message' => 'second uncertain']],
            ['userId' => '412372001', 'amount' => '100', 'response' => ['code' => 1001, 'message' => 'replayed']],
            ['userId' => '412372001', 'amount' => '100.00', 'response' => ['code' => 5000, 'message' => 'third uncertain']],
        ]);

        $this->assertSame([
            'wdr-persisted-uuid-1',
            'wdr-persisted-uuid-1',
            'wdr-persisted-uuid-2',
            'wdr-persisted-uuid-2',
            'wdr-persisted-uuid-3',
        ], $result['requestHeaders']);
        $this->assertSame(3, $result['uuidCount']);
        $this->assertSame('wdr-persisted-uuid-3', $result['storedStates']['412372001']['key']);
    }

    public function test_layui_withdraw_persisted_keys_are_isolated_by_user(): void
    {
        $result = $this->executeWithdrawPersistenceJavascriptScenario([
            ['userId' => '412372001', 'amount' => '100', 'response' => ['code' => 5000, 'message' => 'user one uncertain']],
            ['userId' => '412372002', 'amount' => '100', 'response' => ['code' => 5000, 'message' => 'user two uncertain']],
            ['userId' => '412372001', 'amount' => '100.0', 'response' => ['code' => 5000, 'message' => 'user one replay']],
        ]);

        $this->assertSame([
            'wdr-persisted-uuid-1',
            'wdr-persisted-uuid-2',
            'wdr-persisted-uuid-1',
        ], $result['requestHeaders']);
        $this->assertSame('412372001', $result['storedStates']['412372001']['userId']);
        $this->assertSame('412372002', $result['storedStates']['412372002']['userId']);
    }

    public function test_layui_withdraw_clears_malformed_persisted_state_before_creating_a_request(): void
    {
        $result = $this->executeWithdrawPersistenceJavascriptScenario([
            ['userId' => '412372001', 'amount' => '100', 'response' => ['code' => 5000, 'message' => 'uncertain']],
        ], [
            '412372001' => '{broken-json',
        ]);

        $this->assertSame(1, $result['requestCount']);
        $this->assertSame(['wdr-persisted-uuid-1'], $result['requestHeaders']);
        $this->assertSame(1, $result['removeCount']);
        $this->assertSame('100.00', $result['storedStates']['412372001']['normalizedAmount']);
    }

    public function test_layui_withdraw_fails_closed_when_the_idempotency_state_cannot_be_stored(): void
    {
        $result = $this->executeWithdrawPersistenceJavascriptScenario([
            ['userId' => '412372001', 'amount' => '100', 'response' => ['code' => 5000, 'message' => 'must not send']],
        ], [], true);

        $this->assertSame(0, $result['requestCount']);
        $this->assertSame([], $result['requestHeaders']);
        $this->assertContains('front.withdraw_idempotency_storage_unavailable', $result['messages']);
        $this->assertFalse($result['storageReady']);
    }

    public function test_layui_withdraw_storage_failure_message_is_translated_in_supported_locales(): void
    {
        $zh = require resource_path('lang/zh-CN/front.php');
        $en = require resource_path('lang/en/front.php');

        $this->assertSame(
            '浏览器无法安全保存本次出金请求，请启用本地存储后重试。',
            $zh['withdraw_idempotency_storage_unavailable'] ?? null
        );
        $this->assertSame(
            'The withdrawal request cannot be stored safely in this browser. Enable local storage and try again.',
            $en['withdraw_idempotency_storage_unavailable'] ?? null
        );
    }

    public function test_layui_withdraw_success_does_not_reset_read_only_account_fields(): void
    {
        $source = $this->frontLayuiScript('withdraw/index.js');
        $submission = $this->javascriptFunctionSource($source, 'submitWithdraw');
        $configLoader = $this->javascriptFunctionSource($source, 'loadPageConfig');

        $this->assertStringContainsString('var withdrawConfigReady = false;', $source);
        $this->assertStringContainsString('function clearWithdrawEditableFields()', $source);
        $this->assertStringContainsString('clearWithdrawEditableFields();', $submission);
        $this->assertStringContainsString('loadPageConfig(function', $submission);
        $this->assertStringNotContainsString("$('#withdrawForm')[0].reset();", $submission);
        $this->assertStringNotContainsString('fillPageFields({', $submission);
        $this->assertStringContainsString('function loadPageConfig(completion)', $configLoader);
    }

    public function test_withdraw_scenario_helpers_extract_submit_and_clear_from_the_same_source(): void
    {
        foreach ([
            'executeWithdrawJavascriptScenario',
            'executeWithdrawJavascriptSequence',
            'executeWithdrawPersistenceJavascriptScenario',
        ] as $method) {
            $methodSource = $this->phpMethodSource($method);
            $this->assertStringContainsString("'clearWithdrawEditableFields'", $methodSource, $method);
            $this->assertStringNotContainsString('function clearWithdrawEditableFields', $methodSource, $method);
        }
    }

    public function test_withdraw_scenarios_execute_the_real_editable_field_clear_behavior(): void
    {
        $single = $this->executeWithdrawJavascriptScenario(['code' => 1001, 'message' => 'accepted']);
        $sequence = $this->executeWithdrawJavascriptSequence([
            ['amount' => '100.00', 'response' => ['code' => 1001, 'message' => 'accepted']],
        ]);
        $persistence = $this->executeWithdrawPersistenceJavascriptScenario([
            [
                'userId' => '412372001',
                'amount' => '100.00',
                'response' => ['code' => 1001, 'message' => 'accepted'],
            ],
        ]);

        $this->assertSame(1, $single['editableClearCount']);
        $this->assertSame(1, $sequence['editableClearCount']);
        $this->assertSame(1, $persistence['editableClearCount']);
    }

    public function test_layui_withdraw_waits_for_real_config_refresh_before_enabling_submit(): void
    {
        $success = $this->executeWithdrawConfigCompletionScenario('success');
        $failure = $this->executeWithdrawConfigCompletionScenario('error');

        foreach ([$success, $failure] as $result) {
            $this->assertTrue($result['before']['buttonDisabled']);
            $this->assertSame('', $result['before']['amount']);
            $this->assertSame('', $result['before']['password']);
            $this->assertFalse($result['before']['agree']);
            $this->assertSame('500.00', $result['before']['balance']);
            $this->assertSame('450.00', $result['before']['available']);
            $this->assertSame('7.20', $result['before']['exchangeRate']);
            $this->assertSame('Task Bank / ****0001', $result['before']['bank']);
            $this->assertSame(0, $result['resetCount']);
        }

        $this->assertFalse($success['after']['buttonDisabled']);
        $this->assertSame('600.00', $success['after']['balance']);
        $this->assertSame('550.00', $success['after']['available']);
        $this->assertSame('7.30', $success['after']['exchangeRate']);
        $this->assertSame('Fresh Bank / ****9999', $success['after']['bank']);
        $this->assertTrue($failure['after']['buttonDisabled']);
        $this->assertSame('500.00', $failure['after']['balance']);
        $this->assertSame('450.00', $failure['after']['available']);
        $this->assertSame('7.20', $failure['after']['exchangeRate']);
        $this->assertSame('Task Bank / ****0001', $failure['after']['bank']);
        $this->assertContains(2, $failure['messageIcons']);
    }

    public function test_javascript_function_extraction_uses_javascript_syntax_boundaries(): void
    {
        $expected = <<<'JS'
function syntaxBoundaryProbe() {
    var stringValue = 'string closes }';
    // line comment opens {
    /* block comment closes } */
    var regexValue = /regex\{/;
    var templateValue = `template closes }`;
    return {stringValue: stringValue, regexValue: regexValue, templateValue: templateValue};
}
JS;
        $source = $expected . <<<'JS'

function followingFunction() {
    return 'following';
}
JS;

        $extracted = $this->javascriptFunctionSource($source, 'syntaxBoundaryProbe');

        $this->assertSame($expected, $extracted);
        $this->assertStringNotContainsString('followingFunction', $extracted);
        $this->assertStringContainsString(
            "return 'following';",
            $this->javascriptFunctionSource($source, 'followingFunction')
        );
    }

    public function test_javascript_function_extraction_rejects_a_truncated_candidate_that_only_repairs_the_scope(): void
    {
        $expected = <<<'JS'
function truncationProbe(){var value='}//';return value;}
JS;
        $source = $expected . <<<'JS'

function truncationTail(){return 'tail';}
JS;

        $extracted = $this->javascriptFunctionSource($source, 'truncationProbe');
        $this->assertSame($expected, $extracted);
        $result = $this->executeJavascriptJson(<<<JS
'use strict';
{$extracted}
console.log(JSON.stringify({value: truncationProbe()}));
JS
        );

        $this->assertSame('}//', $result['value']);
        $this->assertStringNotContainsString('truncationTail', $extracted);
    }

    public function test_javascript_function_extraction_accepts_whitespace_before_the_parameter_list(): void
    {
        $source = <<<'JS'
function whitespaceProbe () {
    return 'whitespace';
}
JS;

        $extracted = $this->javascriptFunctionSource($source, 'whitespaceProbe');
        $this->assertSame($source, $extracted);
        $result = $this->executeJavascriptJson(<<<JS
'use strict';
{$extracted}
console.log(JSON.stringify({value: whitespaceProbe()}));
JS
        );

        $this->assertSame('whitespace', $result['value']);
    }

    public function test_javascript_function_extraction_preserves_an_async_declaration_prefix(): void
    {
        $source = <<<'JS'
async function asyncDeclarationProbe() {
    return 'async';
}
JS;

        $extracted = $this->javascriptFunctionSource($source, 'asyncDeclarationProbe');
        $this->assertSame($source, $extracted);
        $result = $this->executeJavascriptJson(<<<JS
'use strict';
{$extracted}
asyncDeclarationProbe().then(function (value) {
    console.log(JSON.stringify({value: value}));
});
JS
        );

        $this->assertSame('async', $result['value']);
    }

    public function test_javascript_function_extraction_accepts_generator_declarations_without_name_whitespace(): void
    {
        $generatorSource = <<<'JS'
function*generatorProbe() {
    yield 'generator';
    return 'generator-done';
}
JS;
        $asyncGeneratorSource = <<<'JS'
async function*asyncGeneratorProbe() {
    yield 'async-generator';
    return 'async-generator-done';
}
JS;
        $source = $generatorSource . "\n" . $asyncGeneratorSource;

        $generator = $this->javascriptFunctionSource($source, 'generatorProbe');
        $asyncGenerator = $this->javascriptFunctionSource($source, 'asyncGeneratorProbe');

        $this->assertSame($generatorSource, $generator);
        $this->assertSame($asyncGeneratorSource, $asyncGenerator);
        $result = $this->executeJavascriptJson(<<<JS
'use strict';
{$generator}
{$asyncGenerator}
var generatorIterator = generatorProbe();
var generatorFirst = generatorIterator.next();
var generatorSecond = generatorIterator.next();
var asyncGeneratorIterator = asyncGeneratorProbe();
asyncGeneratorIterator.next().then(function (asyncGeneratorFirst) {
    asyncGeneratorIterator.next().then(function (asyncGeneratorSecond) {
        console.log(JSON.stringify({
            generator: [generatorFirst, generatorSecond],
            asyncGenerator: [asyncGeneratorFirst, asyncGeneratorSecond]
        }));
    });
});
JS
        );

        $this->assertSame([
            ['value' => 'generator', 'done' => false],
            ['value' => 'generator-done', 'done' => true],
        ], $result['generator']);
        $this->assertSame([
            ['value' => 'async-generator', 'done' => false],
            ['value' => 'async-generator-done', 'done' => true],
        ], $result['asyncGenerator']);
    }

    public function test_javascript_function_extraction_does_not_treat_async_across_a_line_break_as_a_prefix(): void
    {
        $expected = <<<'JS'
function asyncLineBreakProbe() {
    return 'plain';
}
JS;
        $source = "var async = true;\nasync\n" . $expected;

        $this->assertSame(
            $expected,
            $this->javascriptFunctionSource($source, 'asyncLineBreakProbe')
        );
    }

    public function test_javascript_function_extraction_ignores_non_code_declarations(): void
    {
        $source = <<<'JS'
// function codeContextProbe() { return 'line-comment'; }
/* function codeContextProbe() { return 'block-comment'; } */
var singleQuoted = 'function codeContextProbe() { return "single-quoted"; }';
var doubleQuoted = "function codeContextProbe() { return 'double-quoted'; }";
var templateRaw = `function codeContextProbe() { return 'template-raw'; }`;
var nestedTemplate = `outer ${`inner ${/function codeContextProbe()/.test(
    'function codeContextProbe()'
) ? 'matched' : 'missed'}`}`;
var regexValue = /function codeContextProbe() \{ return 'regex'; \}/;
var escapedRegexValue = /\function codeContextProbe()/u;
function codeContextProbe() {
    return 'real-declaration';
}
JS;

        $extracted = $this->javascriptFunctionSource($source, 'codeContextProbe');
        $result = $this->executeJavascriptJson(<<<JS
'use strict';
{$extracted}
console.log(JSON.stringify({value: codeContextProbe()}));
JS
        );

        $this->assertSame('real-declaration', $result['value']);
        $this->assertStringNotContainsString('line-comment', $extracted);
    }

    public function test_withdraw_submit_source_ignores_a_non_code_declaration(): void
    {
        $source = <<<'JS'
var decoy = 'function submitWithdraw(field) { return "string-decoy"; }';
function submitWithdraw(field) {
    return field.value;
}
$('#withdrawAmount').on('input', function () {});
JS;

        $submission = $this->withdrawSubmitSource($source);
        $result = $this->executeJavascriptJson(<<<JS
'use strict';
{$submission}
console.log(JSON.stringify({value: submitWithdraw({value: 'real-submission'})}));
JS
        );

        $this->assertSame('real-submission', $result['value']);
        $this->assertStringNotContainsString('string-decoy', $submission);
    }

    public function test_javascript_function_extraction_ignores_a_named_function_expression(): void
    {
        $expected = <<<'JS'
function expressionProbe() {
    return 'real-declaration';
}
JS;
        $source = <<<'JS'
var decoy = function expressionProbe() {
    return 'named-expression';
};
JS;
        $source .= "\n" . $expected;

        $this->assertSame($expected, $this->javascriptFunctionSource($source, 'expressionProbe'));
    }

    public function test_javascript_function_extraction_ignores_a_dead_block_declaration(): void
    {
        $expected = <<<'JS'
function deadCodeProbe() {
    return 'real-declaration';
}
JS;
        $source = <<<'JS'
if (false) {
    function deadCodeProbe() {
        return 'dead-declaration';
    }
}
JS;
        $source .= "\n" . $expected;

        $this->assertSame($expected, $this->javascriptFunctionSource($source, 'deadCodeProbe'));
    }

    public function test_javascript_function_extraction_ignores_a_nested_declaration(): void
    {
        $expected = <<<'JS'
function nestedProbe() {
    return 'real-declaration';
}
JS;
        $source = <<<'JS'
function wrapper() {
    function nestedProbe() {
        return 'nested-declaration';
    }
}
JS;
        $source .= "\n" . $expected;

        $this->assertSame($expected, $this->javascriptFunctionSource($source, 'nestedProbe'));
    }

    public function test_javascript_function_extraction_rejects_source_without_a_root_declaration(): void
    {
        $source = <<<'JS'
var expression = function rootScopeProbe() { return 'expression'; };
if (false) {
    function rootScopeProbe() { return 'dead-block'; }
}
function wrapper() {
    function rootScopeProbe() { return 'nested'; }
}
JS;

        $this->assertJavascriptFunctionExtractionFails(
            $source,
            'rootScopeProbe',
            'JavaScript function was not found: rootScopeProbe'
        );
    }

    public function test_javascript_function_extraction_preserves_non_strict_duplicate_parameters(): void
    {
        $source = <<<'JS'
function duplicateParameterProbe(value, value) {
    return value;
}
JS;

        $this->assertSame(
            $source,
            $this->javascriptFunctionSource($source, 'duplicateParameterProbe')
        );
    }

    public function test_javascript_function_extraction_parses_aggregate_scope_as_strict(): void
    {
        $source = <<<'JS'
registry['probe/page'] = once(function () {
    layui.use(['jquery'], function () {
        function aggregateStrictDuplicateProbe(value, value) {
            return value;
        }
    });
});
JS;

        $this->assertJavascriptFunctionExtractionFails(
            $source,
            'aggregateStrictDuplicateProbe',
            'JavaScript source is not parseable while locating function: aggregateStrictDuplicateProbe'
        );
    }

    public function test_javascript_function_extraction_rejects_duplicate_parameters_in_plain_strict_source(): void
    {
        $source = <<<'JS'
'use strict';
function plainStrictDuplicateProbe(value, value) {
    return value;
}
JS;

        $this->assertJavascriptFunctionExtractionFails(
            $source,
            'plainStrictDuplicateProbe',
            'JavaScript source is not parseable while locating function: plainStrictDuplicateProbe'
        );
    }

    public function test_javascript_function_extraction_fails_loud_for_invalid_source(): void
    {
        $this->assertJavascriptFunctionExtractionFails(
            'function invalidSourceProbe() {',
            'invalidSourceProbe',
            'JavaScript source is not parseable while locating function: invalidSourceProbe'
        );
    }

    public function test_javascript_function_extraction_fails_loud_when_the_function_is_not_found(): void
    {
        $this->assertJavascriptFunctionExtractionFails(
            'var available = true;',
            'missingProbe',
            'JavaScript function was not found: missingProbe'
        );
    }

    public function test_javascript_function_extraction_rejects_an_invalid_identifier(): void
    {
        $this->assertJavascriptFunctionExtractionFails(
            'function validProbe() {}',
            'invalid-name',
            'JavaScript function name must be a valid identifier.'
        );
    }

    public function test_javascript_function_extraction_rejects_an_unsupported_aggregate_callback(): void
    {
        $source = <<<'JS'
registry['probe/page'] = once(function () {
    layui.use(['jquery'], () => {
        function aggregateNormalizationProbe() { return 'nested-arrow'; }
    });
});
JS;

        $this->assertJavascriptFunctionExtractionFails(
            $source,
            'aggregateNormalizationProbe',
            'JavaScript aggregate page scope could not be normalized: aggregateNormalizationProbe'
        );
    }

    public function test_javascript_function_extraction_rejects_multiple_aggregate_callbacks(): void
    {
        $source = <<<'JS'
registry['probe/page'] = once(function () {
    layui.use(['jquery'], function () {
        function aggregateCallbackProbe() { return 'first'; }
    });
    layui.use(['form'], function () {
        function aggregateCallbackProbe() { return 'second'; }
    });
});
JS;

        $this->assertJavascriptFunctionExtractionFails(
            $source,
            'aggregateCallbackProbe',
            'JavaScript aggregate page scope could not be normalized: aggregateCallbackProbe'
        );
    }

    public function test_javascript_function_extraction_rejects_a_dead_second_aggregate_callback(): void
    {
        $source = <<<'JS'
registry['probe/page'] = once(function () {
    var decoy = "layui.use(['decoy'], () => {});";
    layui.use(['jquery'], function () {
        function deadAggregateCallbackProbe() { return 'real'; }
    });
    if (false) {
        layui.use(['form'], () => {});
    }
});
JS;

        $this->assertJavascriptFunctionExtractionFails(
            $source,
            'deadAggregateCallbackProbe',
            'JavaScript aggregate page scope could not be normalized: deadAggregateCallbackProbe'
        );
    }

    public function test_javascript_function_extraction_rejects_multiple_root_declarations(): void
    {
        $source = <<<'JS'
function ambiguousProbe() { return 'first'; }
function ambiguousProbe() { return 'second'; }
JS;

        $this->assertJavascriptFunctionExtractionFails(
            $source,
            'ambiguousProbe',
            'JavaScript function declaration is ambiguous: ambiguousProbe'
        );
    }

    public function test_javascript_function_extraction_rejects_an_unrelated_root_binding(): void
    {
        $source = <<<'JS'
var unrelatedBindingProbe;
function unrelatedBindingProbe() { return 'declaration'; }
JS;

        $this->assertJavascriptFunctionExtractionFails(
            $source,
            'unrelatedBindingProbe',
            'JavaScript function declaration is ambiguous: unrelatedBindingProbe'
        );
    }

    private function assertJavascriptFunctionExtractionFails(
        string $source,
        string $name,
        string $expectedMessage
    ): void {
        try {
            $this->javascriptFunctionSource($source, $name);
        } catch (AssertionFailedError $error) {
            $this->assertStringContainsString($expectedMessage, $error->getMessage());
            return;
        }

        $this->fail('Expected JavaScript function extraction to fail for: ' . $name);
    }

    private function phpMethodSource(string $method): string
    {
        $reflection = new \ReflectionMethod($this, $method);
        $lines = file(__FILE__);
        $this->assertIsArray($lines, 'The current test source must be readable.');

        return implode('', array_slice(
            $lines,
            $reflection->getStartLine() - 1,
            $reflection->getEndLine() - $reflection->getStartLine() + 1
        ));
    }

    private function withdrawSubmitSource(string $source): string
    {
        return $this->javascriptFunctionSource($source, 'submitWithdraw');
    }

    private function javascriptCallbackBody(
        string $source,
        string $startMarker,
        string $endMarker,
        int $offset = 0
    ): string {
        $start = strpos($source, $startMarker, $offset);
        $end = $start === false ? false : strpos($source, $endMarker, $start + strlen($startMarker));
        $this->assertNotFalse($start);
        $this->assertNotFalse($end);

        return substr($source, $start + strlen($startMarker), $end - $start - strlen($startMarker));
    }

    private function javascriptFunctionSource(string $source, string $name): string
    {
        $this->assertSame(
            1,
            preg_match('/\A[A-Za-z_$][A-Za-z0-9_$]*\z/D', $name),
            'JavaScript function name must be a valid identifier.'
        );

        static $cache = [];
        $cacheKey = hash('sha256', $source) . ':' . $name;
        if (isset($cache[$cacheKey])) {
            return $cache[$cacheKey];
        }

        $sourceJson = json_encode(base64_encode($source), JSON_THROW_ON_ERROR);
        $nameJson = json_encode($name, JSON_THROW_ON_ERROR);
        $result = $this->executeJavascriptJson(<<<JS
'use strict';
var vm = require('vm');
var source = Buffer.from({$sourceJson}, 'base64').toString('utf8');
var name = {$nameJson};

function parsesAsJavascript(candidate, strictMode) {
    try {
        var strictPrefix = strictMode ? "'use strict';\\n" : '';
        new Function(strictPrefix + candidate);
        return true;
    } catch (error) {
        if (error instanceof SyntaxError) {
            return false;
        }
        throw error;
    }
}

function parsesAsNamedFunctionDeclaration(candidate, strictMode) {
    try {
        var strictPrefix = strictMode ? "'use strict';\\n" : '';
        var extractedFunction = new Function(
            strictPrefix + candidate + '\\nreturn ' + name + ';'
        )();
        return typeof extractedFunction === 'function' && extractedFunction.name === name;
    } catch (error) {
        if (error instanceof SyntaxError) {
            return false;
        }
        throw error;
    }
}

var aggregateSource = /^\s*registry\s*\[/.test(source);
if (!parsesAsJavascript(source, aggregateSource)) {
    throw new Error('JavaScript source is not parseable while locating function: ' + name);
}

function sourceUsesStrictMode(candidateSource) {
    var strictProbe = '__crm_strict_probe_';
    while (candidateSource.indexOf(strictProbe) !== -1) {
        strictProbe += '_';
    }

    return !parsesAsJavascript(
        candidateSource + '\\nfunction ' + strictProbe + '(value, value) {}',
        false
    );
}

var sourceIsStrict = aggregateSource || sourceUsesStrictMode(source);
var scopeIsStrict = sourceIsStrict;

function aggregateNormalizationFailure() {
    throw new Error('JavaScript aggregate page scope could not be normalized: ' + name);
}

function countCodeContextLayuiUse(candidateSource) {
    var marker = 'layui.use';
    var count = 0;

    for (
        var offset = candidateSource.indexOf(marker);
        offset !== -1;
        offset = candidateSource.indexOf(marker, offset + marker.length)
    ) {
        var propertyOffset = offset + 'layui.'.length;
        var probe = candidateSource.slice(0, propertyOffset)
            + '0'
            + candidateSource.slice(propertyOffset + 'use'.length);
        if (!parsesAsJavascript(probe, true)) {
            count++;
        }
    }

    return count;
}

function normalizedFunctionScope(candidateSource) {
    if (!aggregateSource) {
        return candidateSource;
    }

    // Aggregate page functions live directly in the one captured layui.use callback body.
    var header = /^\s*registry\[\s*(['"])([^'"\\r\\n]+)\\1\s*\]\s*=\s*once\s*\(\s*function\s*\(\s*\)\s*\{/;
    if (!header.test(candidateSource) || !/\}\s*\)\s*;\s*$/.test(candidateSource)) {
        aggregateNormalizationFailure();
    }
    if (countCodeContextLayuiUse(candidateSource) !== 1) {
        aggregateNormalizationFailure();
    }

    var headerMatch = candidateSource.match(header);
    var registry = Object.create(null);
    var onceCount = 0;
    var onceFactory = null;
    var useCount = 0;
    var capturedCallback = null;
    var dependenciesAreValid = true;
    var once = function (factory) {
        onceCount++;
        onceFactory = factory;
        return factory;
    };
    var layui = Object.freeze({
        use: function (dependencies, callback) {
            useCount++;
            dependenciesAreValid = dependenciesAreValid && Array.isArray(dependencies);
            capturedCallback = callback;
        }
    });
    var sandbox = {registry: registry, once: once, layui: layui};

    try {
        var context = vm.createContext(sandbox, {
            codeGeneration: {strings: false, wasm: false}
        });
        new vm.Script("'use strict';\\n" + candidateSource).runInContext(context, {timeout: 1000});

        var registryKeys = Object.keys(registry);
        if (
            sandbox.registry !== registry
            || sandbox.once !== once
            || sandbox.layui !== layui
            || onceCount !== 1
            || typeof onceFactory !== 'function'
            || registryKeys.length !== 1
            || registryKeys[0] !== headerMatch[2]
            || registry[registryKeys[0]] !== onceFactory
            || useCount !== 0
        ) {
            aggregateNormalizationFailure();
        }

        sandbox.__crmPageKey = registryKeys[0];
        vm.runInContext('registry[__crmPageKey]();', context, {timeout: 1000});
    } catch (error) {
        aggregateNormalizationFailure();
    }

    if (
        onceCount !== 1
        || useCount !== 1
        || !dependenciesAreValid
        || typeof capturedCallback !== 'function'
    ) {
        aggregateNormalizationFailure();
    }

    var callbackSource = Function.prototype.toString.call(capturedCallback);
    if (!/^function\s*\(\s*\)\s*\{/.test(callbackSource) || !/\}\s*$/.test(callbackSource)) {
        aggregateNormalizationFailure();
    }

    var bodyStart = callbackSource.indexOf('{');
    var bodyEnd = callbackSource.lastIndexOf('}');
    var callbackBody = callbackSource.slice(bodyStart + 1, bodyEnd);
    if (!parsesAsJavascript(callbackBody, true)) {
        aggregateNormalizationFailure();
    }

    scopeIsStrict = true;
    return callbackBody;
}

var scope = normalizedFunctionScope(source);
var declarationPattern = /(?:async(?: |\t)+)?function(?:\s*\*\s*|\s+)([A-Za-z_$][A-Za-z0-9_$]*)\s*\(/g;
var declarationMarkers = [];
var declarationMatch;
while ((declarationMatch = declarationPattern.exec(scope)) !== null) {
    if (declarationMatch[1] !== name) {
        continue;
    }
    declarationMarkers.push({
        declarationStart: declarationMatch.index,
        nameOffset: declarationMatch.index + declarationMatch[0].lastIndexOf(name)
    });
}

var probePrefix = '__crm_function_probe_';
while (scope.indexOf(probePrefix) !== -1) {
    probePrefix += '_';
}

function sourceWithOnlyMarkerName(restoredIndex) {
    var result = '';
    var cursor = 0;

    declarationMarkers.forEach(function (marker, index) {
        result += scope.slice(cursor, marker.nameOffset);
        result += index === restoredIndex ? name : probePrefix + index;
        cursor = marker.nameOffset + name.length;
    });

    return result + scope.slice(cursor);
}

// Only a root FunctionDeclaration conflicts with an appended lexical binding.
var bindingProbe = '\\nlet ' + name + ';';
if (!parsesAsJavascript(sourceWithOnlyMarkerName(-1) + bindingProbe, scopeIsStrict)) {
    throw new Error('JavaScript function declaration is ambiguous: ' + name);
}

var declarations = [];
declarationMarkers.forEach(function (marker, index) {
    if (!parsesAsJavascript(sourceWithOnlyMarkerName(index) + bindingProbe, scopeIsStrict)) {
        declarations.push(marker);
    }
});

if (declarations.length === 0) {
    throw new Error('JavaScript function was not found: ' + name);
}
if (declarations.length !== 1) {
    throw new Error('JavaScript function declaration is ambiguous: ' + name);
}

var declaration = declarations[0];
var start = declaration.declarationStart;
var replacement = scope.slice(start, declaration.nameOffset) + name + '() {}';
var extracted = null;
// Parse each replacement in the original scope so strictness and syntax context stay unchanged.
for (var end = scope.indexOf('}', start); end !== -1; end = scope.indexOf('}', end + 1)) {
    var scopeWithoutCandidate = scope.slice(0, start) + replacement + scope.slice(end + 1);
    var candidate = scope.slice(start, end + 1);
    if (
        parsesAsJavascript(scopeWithoutCandidate, scopeIsStrict)
        && parsesAsNamedFunctionDeclaration(candidate, scopeIsStrict)
    ) {
        extracted = candidate;
        break;
    }
}

if (extracted === null) {
    throw new Error('JavaScript function boundary could not be isolated: ' + name);
}

console.log(JSON.stringify({source: extracted}));
JS
        );
        $this->assertIsString($result['source'] ?? null, 'JavaScript function source must be a string.');
        $cache[$cacheKey] = $result['source'];

        return $cache[$cacheKey];
    }

    /**
     * @param array<string, mixed> $response
     * @return array<string, mixed>
     */
    private function executeWithdrawJavascriptScenario(
        array $response,
        string $transport = 'success'
    ): array {
        $source = $this->frontLayuiScript('withdraw/index.js');
        $createKey = $this->javascriptFunctionSource($source, 'createWithdrawIdempotencyKey');
        $normalizer = $this->javascriptFunctionSource($source, 'normalizeWithdrawAmount');
        $storageKey = $this->javascriptFunctionSource($source, 'withdrawIdempotencyStorageKey');
        $restoreState = $this->javascriptFunctionSource($source, 'restoreWithdrawIdempotencyState');
        $persistState = $this->javascriptFunctionSource($source, 'persistWithdrawIdempotencyState');
        $prepareKey = $this->javascriptFunctionSource($source, 'prepareWithdrawIdempotencyKey');
        $currentKey = $this->javascriptFunctionSource($source, 'currentWithdrawIdempotencyKey');
        $clearKey = $this->javascriptFunctionSource($source, 'clearWithdrawIdempotencyKey');
        $validation = $this->javascriptFunctionSource($source, 'validateSubmit');
        $successRule = $this->javascriptFunctionSource($source, 'isSuccess');
        $money = $this->javascriptFunctionSource($source, 'money');
        $calculateAmount = $this->javascriptFunctionSource($source, 'calculateAmount');
        $clearEditableFields = $this->javascriptFunctionSource($source, 'clearWithdrawEditableFields');
        $submission = $this->javascriptFunctionSource($source, 'submitWithdraw');
        $responseJson = json_encode($response, JSON_UNESCAPED_SLASHES);
        $transportJson = json_encode($transport, JSON_UNESCAPED_SLASHES);

        return $this->executeJavascriptJson(<<<JS
'use strict';
var pageData = {
    isAllowed: true,
    min: 10,
    max: 500000,
    availableAmount: 1000,
    feeRate: 0,
    fixedFee: 0
};
var withdrawSubmitting = false;
var withdrawIdempotencyKey = null;
var withdrawIdempotencyAmount = null;
var withdrawIdempotencyUserId = null;
var withdrawIdempotencyStorageReady = true;
var withdrawIdempotencyFailureReason = null;
var resetCount = 0;
var editableClearCount = 0;
var loadCount = 0;
var historyCount = 0;
var requestCount = 0;
var requestHeaders = [];
var requestPayloads = [];
var uuidCounter = 0;
var messageIcons = [];
var response = {$responseJson};
var transport = {$transportJson};
var values = {
    '#withdrawAmount': '100.00',
    '#withdrawPassword': 'password',
    '#withdrawUserId': '412372001',
    '#withdrawBalance': '500.00',
    '#withdrawExchangeRate': '7.20'
};
var checked = {'#withdrawAgree': true};
var idempotencyStorage = {};
var window = {
    localStorage: {
        getItem: function (key) { return Object.prototype.hasOwnProperty.call(idempotencyStorage, key) ? idempotencyStorage[key] : null; },
        setItem: function (key, value) { idempotencyStorage[key] = String(value); },
        removeItem: function (key) { delete idempotencyStorage[key]; }
    },
    crypto: {randomUUID: function () { uuidCounter++; return 'scenario-uuid-' + uuidCounter; }}
};
var layer = {msg: function (message, options) { messageIcons.push(options.icon); }};
var form = {render: function () {}};
var CrmTable = {isSuccess: function () { return true; }};
var CrmAjax = {
    request: function (options) {
        requestCount++;
        requestHeaders.push(options.headers['Idempotency-Key']);
        requestPayloads.push(options.data);
        if (transport === 'success') {
            options.success(response);
        } else {
            options.error(response);
        }
    }
};
function t(key) { return key; }
function setWithdrawSubmitting(value) { withdrawSubmitting = !!value; }
function fillPageFields() {}
function loadPageConfig(completion) {
    loadCount++;
    if (typeof completion === 'function') { completion(true); }
}
function renderHistoryTable() { historyCount++; }
function $(selector) {
    return {
        0: {reset: function () { resetCount++; }},
        is: function () { return !!checked[selector]; },
        prop: function (name, value) {
            if (name === 'checked') { checked[selector] = !!value; }
            return this;
        },
        toggleClass: function () { return this; },
        val: function (value) {
            if (arguments.length) {
                if (selector === '#withdrawAmount' && value === '') {
                    resetCount++;
                    editableClearCount++;
                }
                values[selector] = value;
                return this;
            }
            return values[selector] || '';
        }
    };
}
{$createKey}
{$normalizer}
{$storageKey}
{$restoreState}
{$persistState}
{$prepareKey}
{$currentKey}
{$clearKey}
{$validation}
{$successRule}
{$money}
{$calculateAmount}
{$clearEditableFields}
{$submission}
submitWithdraw({amount: '100.00', password: 'password'});
console.log(JSON.stringify({
    resetCount: resetCount,
    editableClearCount: editableClearCount,
    loadCount: loadCount,
    historyCount: historyCount,
    requestCount: requestCount,
    requestHeaders: requestHeaders,
    requestPayloads: requestPayloads,
    uuidCount: uuidCounter,
    messageIcons: messageIcons,
    keyRetained: withdrawIdempotencyKey !== null,
    boundAmount: withdrawIdempotencyAmount,
    submitting: withdrawSubmitting
}));
JS
        );
    }

    /**
     * @param array<int, array<string, mixed>> $attempts
     * @return array<string, mixed>
     */
    private function executeWithdrawJavascriptSequence(array $attempts): array
    {
        $source = $this->frontLayuiScript('withdraw/index.js');
        $createKey = $this->javascriptFunctionSource($source, 'createWithdrawIdempotencyKey');
        $storageKey = $this->javascriptFunctionSource($source, 'withdrawIdempotencyStorageKey');
        $restoreState = $this->javascriptFunctionSource($source, 'restoreWithdrawIdempotencyState');
        $persistState = $this->javascriptFunctionSource($source, 'persistWithdrawIdempotencyState');
        $prepareKey = $this->javascriptFunctionSource($source, 'prepareWithdrawIdempotencyKey');
        $currentKey = $this->javascriptFunctionSource($source, 'currentWithdrawIdempotencyKey');
        $clearKey = $this->javascriptFunctionSource($source, 'clearWithdrawIdempotencyKey');
        $setSubmitting = $this->javascriptFunctionSource($source, 'setWithdrawSubmitting');
        $allowedState = $this->javascriptFunctionSource($source, 'renderAllowedState');
        $validation = $this->javascriptFunctionSource($source, 'validateSubmit');
        $successRule = $this->javascriptFunctionSource($source, 'isSuccess');
        $submission = $this->javascriptFunctionSource($source, 'submitWithdraw');
        $normalizer = $this->javascriptFunctionSource($source, 'normalizeWithdrawAmount');
        $money = $this->javascriptFunctionSource($source, 'money');
        $calculateAmount = $this->javascriptFunctionSource($source, 'calculateAmount');
        $clearEditableFields = $this->javascriptFunctionSource($source, 'clearWithdrawEditableFields');
        $attemptsJson = json_encode($attempts, JSON_UNESCAPED_SLASHES);

        return $this->executeJavascriptJson(<<<JS
'use strict';
var pageData = {isAllowed: true, min: 10, max: 500000, availableAmount: 1000, feeRate: 0, fixedFee: 0};
var historyRendered = false;
var withdrawIdempotencyKey = null;
var withdrawIdempotencyAmount = null;
var withdrawIdempotencyUserId = null;
var withdrawIdempotencyStorageReady = true;
var withdrawIdempotencyFailureReason = null;
var withdrawSubmitting = false;
var withdrawConfigReady = true;
var requestHeaders = [];
var requestPayloads = [];
var messages = [];
var resetCount = 0;
var editableClearCount = 0;
var uuidCounter = 0;
var attempts = {$attemptsJson};
var activeAttempt = null;
var values = {'#withdrawUserId': '412372001', '#withdrawBalance': '500.00', '#withdrawExchangeRate': '7.20'};
var checked = {'#withdrawAgree': true};
var idempotencyStorage = {};
var window = {
    localStorage: {
        getItem: function (key) { return Object.prototype.hasOwnProperty.call(idempotencyStorage, key) ? idempotencyStorage[key] : null; },
        setItem: function (key, value) { idempotencyStorage[key] = String(value); },
        removeItem: function (key) { delete idempotencyStorage[key]; }
    },
    crypto: {randomUUID: function () { uuidCounter++; return 'uuid-' + uuidCounter; }}
};
var layer = {msg: function (message) { messages.push(message); }};
var form = {render: function () {}};
var CrmAjax = {
    request: function (options) {
        requestHeaders.push(options.headers['Idempotency-Key']);
        requestPayloads.push(options.data);
        if ((activeAttempt.transport || 'success') === 'success') {
            options.success(activeAttempt.response || {});
        } else {
            options.error(activeAttempt.response || {});
        }
    }
};
function t(key) { return key; }
function fillPageFields() {}
function loadPageConfig(completion) {
    if (typeof completion === 'function') { completion(true); }
}
function renderHistoryTable() {}
function $(selector) {
    return {
        0: {reset: function () { resetCount++; }},
        is: function () { return !!checked[selector]; },
        length: selector === '#withdrawDisabledNotice' ? 0 : 1,
        prop: function (name, value) {
            if (name === 'checked') { checked[selector] = !!value; }
            return this;
        },
        toggleClass: function () { return this; },
        addClass: function () { return this; },
        removeClass: function () { return this; },
        text: function () { return this; },
        val: function (value) {
            if (arguments.length) {
                if (selector === '#withdrawAmount' && value === '') {
                    resetCount++;
                    editableClearCount++;
                }
                values[selector] = value;
                return this;
            }
            return values[selector] || '';
        }
    };
}
{$createKey}
{$normalizer}
{$storageKey}
{$restoreState}
{$persistState}
{$prepareKey}
{$currentKey}
{$clearKey}
{$allowedState}
{$setSubmitting}
{$validation}
{$successRule}
{$money}
{$calculateAmount}
{$clearEditableFields}
{$submission}
attempts.forEach(function (attempt) {
    activeAttempt = attempt;
    values['#withdrawAmount'] = attempt.amount;
    values['#withdrawPassword'] = 'password';
    checked['#withdrawAgree'] = true;
    submitWithdraw({amount: attempt.amount, password: 'password'});
});
console.log(JSON.stringify({
    requestHeaders: requestHeaders,
    requestPayloads: requestPayloads,
    uuidCount: uuidCounter,
    messages: messages,
    keyRetained: withdrawIdempotencyKey !== null,
    boundAmount: withdrawIdempotencyAmount,
    resetCount: resetCount,
    editableClearCount: editableClearCount
}));
JS
        );
    }

    /**
     * @param array<int, array<string, mixed>> $attempts
     * @param array<string, string> $initialStates
     * @return array<string, mixed>
     */
    private function executeWithdrawPersistenceJavascriptScenario(
        array $attempts,
        array $initialStates = [],
        bool $failWrites = false
    ): array {
        $source = $this->frontLayuiScript('withdraw/index.js');
        $functionNames = [
            'createWithdrawIdempotencyKey',
            'normalizeWithdrawAmount',
            'withdrawIdempotencyStorageKey',
            'restoreWithdrawIdempotencyState',
            'persistWithdrawIdempotencyState',
            'prepareWithdrawIdempotencyKey',
            'currentWithdrawIdempotencyKey',
            'clearWithdrawIdempotencyKey',
            'setWithdrawSubmitting',
            'isSuccess',
            'money',
            'bankNo',
            'renderAllowedState',
            'fillPageFields',
            'calculateAmount',
            'clearWithdrawEditableFields',
            'validateSubmit',
            'submitWithdraw',
        ];
        $javascriptFunctions = implode("\n", array_map(function (string $name) use ($source): string {
            return $this->javascriptFunctionSource($source, $name);
        }, $functionNames));
        $attemptsJson = json_encode($attempts, JSON_UNESCAPED_SLASHES);
        $initialStatesJson = json_encode($initialStates, JSON_UNESCAPED_SLASHES);
        $failWritesJson = json_encode($failWrites);

        return $this->executeJavascriptJson(<<<JS
'use strict';
var attempts = {$attemptsJson};
var initialStates = {$initialStatesJson};
var failWrites = {$failWritesJson};
var storageValues = {};
var removeCount = 0;
var uuidCounter = 0;
var requestHeaders = [];
var requestPayloads = [];
var messages = [];
var editableClearCount = 0;
var latestStorageReady = true;
var activeAttempt = null;
var sharedStorage = {
    getItem: function (key) {
        return Object.prototype.hasOwnProperty.call(storageValues, key) ? storageValues[key] : null;
    },
    setItem: function (key, value) {
        if (failWrites) { throw new Error('storage quota denied'); }
        storageValues[key] = String(value);
    },
    removeItem: function (key) {
        removeCount++;
        delete storageValues[key];
    }
};
Object.keys(initialStates).forEach(function (userId) {
    storageValues['crm:front:withdraw:idempotency:v1:' + encodeURIComponent(userId)] = initialStates[userId];
});

function createRuntime(userId) {
    var pageData = {isAllowed: true, min: 10, max: 500000, availableAmount: 1000, feeRate: 0, fixedFee: 0};
    var historyRendered = false;
    var withdrawIdempotencyKey = null;
    var withdrawIdempotencyAmount = null;
    var withdrawIdempotencyUserId = null;
    var withdrawIdempotencyStorageReady = true;
    var withdrawIdempotencyFailureReason = null;
    var withdrawSubmitting = false;
    var withdrawConfigReady = true;
    var values = {};
    var checked = {'#withdrawAgree': true};
    var window = {
        localStorage: sharedStorage,
        crypto: {randomUUID: function () { uuidCounter++; return 'persisted-uuid-' + uuidCounter; }}
    };
    var layer = {msg: function (message) { messages.push(message); }};
    var form = {render: function () {}};
    var CrmAjax = {request: function (options) {
        requestHeaders.push(options.headers['Idempotency-Key']);
        requestPayloads.push(options.data);
        if ((activeAttempt.transport || 'success') === 'error') {
            options.error(activeAttempt.response || {});
            return;
        }
        options.success(activeAttempt.response || {});
    }};
    function t(key) { return key; }
    function loadPageConfig(completion) {
        if (typeof completion === 'function') { completion(true); }
    }
    function renderHistoryTable() {}
    function $(selector) {
        return {
            length: selector === '#withdrawDisabledNotice' ? 1 : 1,
            is: function () { return !!checked[selector]; },
            prop: function (name, value) {
                if (name === 'checked') { checked[selector] = !!value; }
                return this;
            },
            toggleClass: function () { return this; },
            addClass: function () { return this; },
            removeClass: function () { return this; },
            text: function (value) { if (arguments.length) { values[selector + ':text'] = value; } return this; },
            val: function (value) {
                if (arguments.length) {
                    if (selector === '#withdrawAmount' && value === '') {
                        editableClearCount++;
                    }
                    values[selector] = String(value);
                    return this;
                }
                return values[selector] || '';
            }
        };
    }
    {$javascriptFunctions}
    fillPageFields({
        user: {user_id: userId, balance: '500.00', available_amount: '450.00'},
        bank: {bank_name: 'Task Bank', bank_no: '00000001'},
        withdraw_limits: {min: 10, max: 500000},
        exchange_rates: {CNY: '7.20'},
        fee_rate: 0,
        fixed_fee: 0,
        is_allowed: true
    });
    return {
        submit: function (amount) { submitWithdraw({amount: amount, password: 'password'}); },
        storageReady: function () { return withdrawIdempotencyStorageReady; }
    };
}

attempts.forEach(function (attempt) {
    activeAttempt = attempt;
    var runtime = createRuntime(String(attempt.userId));
    runtime.submit(attempt.amount);
    latestStorageReady = runtime.storageReady();
});
var storedStates = {};
Object.keys(storageValues).forEach(function (key) {
    var prefix = 'crm:front:withdraw:idempotency:v1:';
    if (key.indexOf(prefix) !== 0) { return; }
    var userId = decodeURIComponent(key.slice(prefix.length));
    try { storedStates[userId] = JSON.parse(storageValues[key]); } catch (error) { storedStates[userId] = storageValues[key]; }
});
console.log(JSON.stringify({
    requestCount: requestHeaders.length,
    requestHeaders: requestHeaders,
    requestPayloads: requestPayloads,
    messages: messages,
    uuidCount: uuidCounter,
    removeCount: removeCount,
    storedStates: storedStates,
    storageReady: latestStorageReady,
    editableClearCount: editableClearCount
}));
JS
        );
    }

    /** @return array<string, mixed> */
    private function executeWithdrawConfigCompletionScenario(string $outcome): array
    {
        $source = $this->frontLayuiScript('withdraw/index.js');
        $functions = [
            'createWithdrawIdempotencyKey',
            'normalizeWithdrawAmount',
            'withdrawIdempotencyStorageKey',
            'restoreWithdrawIdempotencyState',
            'persistWithdrawIdempotencyState',
            'prepareWithdrawIdempotencyKey',
            'currentWithdrawIdempotencyKey',
            'clearWithdrawIdempotencyKey',
            'setWithdrawSubmitting',
            'isSuccess',
            'money',
            'bankNo',
            'renderAllowedState',
            'fillPageFields',
            'loadPageConfig',
            'calculateAmount',
            'validateSubmit',
            'submitWithdraw',
        ];
        $functionSource = [];
        foreach ($functions as $function) {
            $functionSource[] = $this->javascriptFunctionSource($source, $function);
        }
        $functionSource[] = $this->javascriptFunctionSource($source, 'clearWithdrawEditableFields');
        $javascriptFunctions = implode("\n", $functionSource);
        $outcomeJson = json_encode($outcome);

        return $this->executeJavascriptJson(<<<JS
'use strict';
var pageData = {isAllowed: true, min: 10, max: 500000, availableAmount: 450, feeRate: 0, fixedFee: 0};
var withdrawIdempotencyKey = null;
var withdrawIdempotencyAmount = null;
var withdrawIdempotencyUserId = null;
var withdrawIdempotencyStorageReady = true;
var withdrawIdempotencyFailureReason = null;
var withdrawSubmitting = false;
var withdrawConfigReady = true;
var buttonDisabled = false;
var configRequest = null;
var resetCount = 0;
var historyCount = 0;
var messageIcons = [];
var checked = {'#withdrawAgree': true};
var values = {
    '#withdrawAmount': '100.00',
    '#withdrawPassword': 'password',
    '#withdrawUserId': '412372001',
    '#withdrawBalance': '500.00',
    '#withdrawAvailable': '450.00',
    '#withdrawExchangeRate': '7.20',
    '#withdrawBankName': 'Task Bank / ****0001',
    '#withdrawFee': '0.00',
    '#withdrawActualAmount': '100.00'
};
var idempotencyStorage = {};
var window = {
    localStorage: {
        getItem: function (key) { return Object.prototype.hasOwnProperty.call(idempotencyStorage, key) ? idempotencyStorage[key] : null; },
        setItem: function (key, value) { idempotencyStorage[key] = String(value); },
        removeItem: function (key) { delete idempotencyStorage[key]; }
    },
    crypto: {randomUUID: function () { return 'completion-key'; }}
};
var layer = {msg: function (message, options) { messageIcons.push(options.icon); }};
var form = {render: function () {}};
var CrmAjax = {
    request: function (options) {
        if (options.url === '/api/front/withdrawals/submissions') {
            options.success({code: 1001, message: 'created'});
            return;
        }
        configRequest = options;
    }
};
function t(key) { return key; }
function renderHistoryTable() { historyCount++; }
function $(selector) {
    return {
        0: {reset: function () {
            resetCount++;
            Object.keys(values).forEach(function (key) { values[key] = ''; });
            checked['#withdrawAgree'] = false;
        }},
        length: selector === '#withdrawDisabledNotice' ? 0 : 1,
        is: function () { return !!checked[selector]; },
        prop: function (name, value) {
            if (name === 'disabled') { buttonDisabled = !!value; }
            if (name === 'checked') { checked[selector] = !!value; }
            return this;
        },
        toggleClass: function () { return this; },
        addClass: function () { return this; },
        removeClass: function () { return this; },
        text: function () { return this; },
        val: function (value) {
            if (arguments.length) { values[selector] = value; return this; }
            return values[selector] || '';
        }
    };
}
{$javascriptFunctions}
submitWithdraw({amount: '100.00', password: 'password'});
var before = {
    buttonDisabled: buttonDisabled,
    amount: values['#withdrawAmount'],
    password: values['#withdrawPassword'],
    agree: !!checked['#withdrawAgree'],
    balance: values['#withdrawBalance'],
    available: values['#withdrawAvailable'],
    exchangeRate: values['#withdrawExchangeRate'],
    bank: values['#withdrawBankName']
};
if ({$outcomeJson} === 'success') {
    configRequest.success({
        code: 1000,
        data: {
            user: {user_id: 412372001, balance: '600.00', available_amount: '550.00'},
            bank: {bank_name: 'Fresh Bank', bank_no: '99999999'},
            withdraw_limits: {min: 10, max: 500000},
            exchange_rates: {CNY: '7.30'},
            fee_rate: 0,
            fixed_fee: 0,
            is_allowed: true
        }
    });
} else {
    configRequest.error({code: 5000, message: 'refresh failed'});
}
console.log(JSON.stringify({
    before: before,
    after: {
        buttonDisabled: buttonDisabled,
        balance: values['#withdrawBalance'],
        available: values['#withdrawAvailable'],
        exchangeRate: values['#withdrawExchangeRate'],
        bank: values['#withdrawBankName']
    },
    resetCount: resetCount,
    historyCount: historyCount,
    messageIcons: messageIcons
}));
JS
        );
    }

}
