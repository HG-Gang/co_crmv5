<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/02
 * Time: 05:39
 */

declare(strict_types=1);

/**
 * 前端佣金转账幂等键 JavaScript-封闭模块测试。
 *
 * 文件功能：
 * - 验证前端各页面外壳（layui 与 crmui）的佣金转账幂等键生成器在失败后复用同一 key，仅在重置后才轮换新 key。
 * - 验证业务失败时保留转账意图数据，仅在确定性成功后清理。
 * - 验证各外壳成功码白名单一致（isSuccess / isSuccessOrLegacy / businessCodeSucceeded / successOrLegacy）。
 * - 验证所有相关前端源码均携带 Idempotency-Key 头与幂等键工具函数。
 *
 * 适用场景：
 * - 佣金转账前端幂等键（Idempotency-Key）生成与清理逻辑的回归测试，防止重复转账或幂等键泄漏。
 *
 * 入参例子：
 * - 通过 executeJavascriptJson 注入模拟 window.crypto、jQuery 选择器与表单对象，
 *   以 JSON 输出断言脚本内部行为；数据源为 public/js/apps/front/layui/module-page.js 与 public/js/apps/crmui/front.js。
 *
 * 返回值：
 * - 断言通过即测试通过，无业务返回值。
 *
 * 异常或失败场景：
 * - 若失败后 key 被轮换、成功后 key 未清理，或各外壳成功码判定不一致，测试失败。
 */

namespace Tests\Feature;

use Tests\Feature\Concerns\ExecutesJavascriptScenarios;
use Tests\TestCase;

final class FrontCommissionTransferJavascriptClosureModuleTest extends TestCase
{
    use ExecutesJavascriptScenarios;

    /**
     * @dataProvider frontShellProvider
     * 验证页面外壳失败后复用幂等键、仅重置后轮换新键。
     *
     * 通过数据提供器分别对 layui 与 crmui 外壳执行 key 场景脚本，
     * 断言 key 序列为 [ct-shell-1, ct-shell-1, ct-shell-2] 且无 crypto 时生成失败返回空串。
     */
    public function test_front_shell_reuses_key_after_failure_and_rotates_only_after_reset(string $name, string $path): void
    {
        $source = (string) file_get_contents(public_path($path));
        $generator = $this->extractFunction($source, 'newCommissionTransferKey');
        $ensurer = $this->extractFunction($source, 'ensureCommissionTransferKey');

        $result = $this->runJqueryKeyScenario($name, $generator, $ensurer);

        $this->assertSame(['ct-shell-1', 'ct-shell-1', 'ct-shell-2'], $result['keys']);
        $this->assertSame(2, $result['uuidCount']);
        $this->assertSame('', $result['withoutCrypto']);
    }

    /**
     * 验证各外壳源码保留失败 key，仅在确定性成功后清理。
     *
     * 断言 layui 与 crmui 源码均包含 Idempotency-Key 及幂等键函数，
     * 且提交、失败判定、清理语句的出现顺序符合预期。
     */
    public function test_front_shells_keep_failure_keys_and_clear_only_on_definitive_success(): void
    {
        $sources = [
            'layui' => (string) file_get_contents(public_path('js/apps/front/layui/module-page.js')),
            'crmui' => (string) file_get_contents(public_path('js/apps/crmui/front.js')),
        ];

        foreach ($sources as $name => $source) {
            $this->assertStringContainsString('Idempotency-Key', $source, $name);
            $this->assertStringContainsString('ensureCommissionTransferKey', $source, $name);
            $this->assertStringContainsString('newCommissionTransferKey', $source, $name);
        }

        $layuiSubmit = strpos($sources['layui'], "headers['Idempotency-Key'] = key;");
        $layuiFailure = strpos($sources['layui'], 'if (!isSuccessOrLegacy(res))');
        $layuiClear = strpos($sources['layui'], "$('[data-commission-transfer-intent]').val('');");
        $this->assertNotFalse($layuiSubmit);
        $this->assertNotFalse($layuiFailure);
        $this->assertNotFalse($layuiClear);
        $this->assertLessThan($layuiClear, $layuiFailure);

        $crmuiFailure = strpos($sources['crmui'], 'businessError: true');
        $crmuiReset = strpos($sources['crmui'], '$form[0].reset();');
        $this->assertNotFalse($crmuiFailure);
        $this->assertNotFalse($crmuiReset);
    }

    /**
     * 验证 layui 业务错误保留转账意图直至确定性成功。
     *
     * 通过 JS 脚本模拟 afterSubmit 对未知结果（2025）与成功（1000）的不同处理，
     * 断言未知结果不清除意图、成功结果清除意图并重载页面。
     */
    public function test_layui_business_errors_keep_transfer_intent_until_definitive_success(): void
    {
        $tableSource = (string) file_get_contents(public_path('js/shared/table-common.js'));
        $source = (string) file_get_contents(public_path('js/apps/front/layui/module-page.js'));
        $tableSuccess = $this->extractFunction($tableSource, 'isSuccess');
        $success = $this->extractFunction($source, 'isSuccess');
        $successOrLegacy = $this->extractFunction($source, 'isSuccessOrLegacy');
        $commissionSubmit = $this->extractFunction($source, 'isCommissionTransferSubmit');
        $afterSubmit = $this->extractFunction($source, 'afterSubmit');
        $generator = $this->extractFunction($source, 'newCommissionTransferKey');
        $ensurer = $this->extractFunction($source, 'ensureCommissionTransferKey');

        $result = $this->executeJavascriptJson(<<<JS
'use strict';
var uuidCount = 0;
var events = [];
var window = {
    crypto: {randomUUID: function () { uuidCount++; return 'shell-' + uuidCount; }},
    setTimeout: function (callback) { callback(); },
    location: {reload: function () { events.push('reload'); }}
};
var CrmTable = {isSuccess: (function () {
{$tableSuccess}
    return isSuccess;
}())};
{$success}
{$successOrLegacy}
{$commissionSubmit}
{$afterSubmit}
{$generator}
{$ensurer}
var external = {
    length: 1,
    value: 'ct-existing',
    val: function (value) {
        if (arguments.length) { this.value = value; events.push('clear'); }
        return this.value;
    },
    first: function () { return this; }
};
var input = {
    length: 1,
    value: 'ct-existing',
    val: function (value) {
        if (arguments.length) { this.value = value; }
        return this.value;
    }
};
var moduleForm = {
    find: function () { return {first: function () { return input; }}; }
};
var form = {render: function () { events.push('render'); }};
var formNode = {reset: function () { events.push('reset'); input.value = ''; }};
function $(selector) {
    if (selector === '[data-commission-transfer-intent]') return external;
    if (selector === '.J_moduleForm') return {0: formNode};
    if (selector === '.J_moduleRecordId') return {val: function () { events.push('record-clear'); }};
    return {length: 0};
}
$.trim = function (value) { return String(value || '').trim(); };
var layer = {msg: function (message) { events.push('msg:' + message); }};
function resetAllEnhancedUploads() { events.push('uploads-reset'); }
function initEnhancedUpload() { events.push('upload-init'); }
function initDatePickers() { events.push('date-init'); }
function loadData() { events.push('load'); }
var submitApiUrl = '/user/proxy/directUserCommTrans';
var codes = [1000, 1001, 1015, 2000, 2015, 2021, 2025, 3000, 3001, 3002, 3003, 3004, 3005, 3006];
var accepted = {};
codes.forEach(function (code) {
    accepted[code] = isSuccessOrLegacy({code: code, msg: code === 2025 ? 'SUCCESS' : ''});
});
var unknownStart = events.length;
afterSubmit({code: 2025, message: 'MT4 result is unknown'});
var unknownEvents = events.slice(unknownStart);
var unknownKey = ensureCommissionTransferKey(moduleForm);
var unknownUuidCount = uuidCount;
var successStart = events.length;
afterSubmit({code: 1000, message: 'Transfer completed'});
var successEvents = events.slice(successStart);
var successKey = ensureCommissionTransferKey(moduleForm);
console.log(JSON.stringify({
    accepted: accepted,
    unknownEvents: unknownEvents,
    unknownKey: unknownKey,
    unknownUuidCount: unknownUuidCount,
    successEvents: successEvents,
    successKey: successKey,
    uuidCount: uuidCount,
    external: external.value
}));
JS
        );

        $this->assertTrue($result['accepted']['1000']);
        $this->assertTrue($result['accepted']['1001']);
        $this->assertTrue($result['accepted']['2000']);
        $this->assertTrue($result['accepted']['3000']);
        $this->assertTrue($result['accepted']['3002']);
        $this->assertTrue($result['accepted']['3004']);
        $this->assertTrue($result['accepted']['3005']);
        $this->assertFalse($result['accepted']['1015']);
        $this->assertFalse($result['accepted']['2015']);
        $this->assertFalse($result['accepted']['2021']);
        $this->assertFalse($result['accepted']['2025']);
        $this->assertFalse($result['accepted']['3001']);
        $this->assertFalse($result['accepted']['3003']);
        $this->assertFalse($result['accepted']['3006']);
        $this->assertSame('ct-existing', $result['unknownKey']);
        $this->assertSame(0, $result['unknownUuidCount']);
        $this->assertNotContains('clear', $result['unknownEvents']);
        $this->assertNotContains('reset', $result['unknownEvents']);
        $this->assertNotContains('reload', $result['unknownEvents']);
        $this->assertContains('clear', $result['successEvents']);
        $this->assertContains('reset', $result['successEvents']);
        $this->assertContains('reload', $result['successEvents']);
        $this->assertSame('ct-shell-1', $result['successKey']);
        $this->assertSame(1, $result['uuidCount']);
        $this->assertSame('ct-shell-1', $result['external']);
    }

    /**
     * 验证各外壳成功码白名单一致。
     *
     * 将 table-common.js、layui 与 crmui 的成功判定函数注入 JS 脚本，
     * 断言三个外壳对同一组业务码的接受/拒绝结果完全一致。
     */
    public function test_front_shell_success_code_whitelists_are_consistent(): void
    {
        $tableSource = (string) file_get_contents(public_path('js/shared/table-common.js'));
        $layuiSource = (string) file_get_contents(public_path('js/apps/front/layui/module-page.js'));
        $crmuiSource = (string) file_get_contents(public_path('js/apps/crmui/front.js'));
        $tableSuccess = $this->extractFunction($tableSource, 'isSuccess');
        $layuiSuccess = $this->extractFunction($layuiSource, 'isSuccess');
        $layuiLegacy = $this->extractFunction($layuiSource, 'isSuccessOrLegacy');
        $crmuiSuccess = $this->extractFunction($crmuiSource, 'businessCodeSucceeded');
        $crmuiLegacy = $this->extractFunction($crmuiSource, 'successOrLegacy');

        $result = $this->executeJavascriptJson(<<<JS
'use strict';
var codes = [1000, 1001, 1015, 2000, 2015, 2021, 2025, 3000, 3001, 3002, 3003, 3004, 3005, 3006];
function evaluate(rule) {
    var values = {};
    codes.forEach(function (code) { values[code] = !!rule({code: code}); });
    return values;
}
var table = (function () {
{$tableSuccess}
    return evaluate(isSuccess);
}());
var layui = (function () {
{$layuiSuccess}
{$layuiLegacy}
    return evaluate(isSuccessOrLegacy);
}());
var crmui = (function () {
{$crmuiSuccess}
{$crmuiLegacy}
    return evaluate(successOrLegacy);
}());
console.log(JSON.stringify({table: table, layui: layui, crmui: crmui}));
JS
        );

        foreach (['table', 'layui', 'crmui'] as $shell) {
            foreach ([1000, 1001, 2000, 3000, 3002, 3004, 3005] as $code) {
                $this->assertTrue($result[$shell][(string) $code], $shell . ' should accept ' . $code);
            }
            foreach ([1015, 2015, 2021, 2025, 3001, 3003, 3006] as $code) {
                $this->assertFalse($result[$shell][(string) $code], $shell . ' should reject ' . $code);
            }
        }
    }

    /**
     * 数据提供器：返回各外壳的名称与 JS 文件路径。
     *
     * @return array<string, array{0: string, 1: string}> 外壳名到文件路径的映射。
     */
    public function frontShellProvider(): array
    {
        return [
            'layui' => ['layui', 'js/apps/front/layui/module-page.js'],
            'crmui' => ['crmui', 'js/apps/crmui/front.js'],
        ];
    }

    /**
     * 执行 jQuery key 场景脚本并返回结果。
     *
     * @param string $name 外壳名（layui / crmui），决定注入的 DOM 模拟代码。
     * @param string $generator 幂等键生成函数源码。
     * @param string $ensurer 幂等键保障函数源码。
     * @return array<string, mixed> 脚本输出解析后的 JSON 结果。
     */
    private function runJqueryKeyScenario(string $name, string $generator, string $ensurer): array
    {
        $extra = $name === 'layui'
            ? <<<'JS'
var external = {length: 0, value: '', val: function (value) { if (arguments.length) { this.value = value; return this; } return this.value; }};
var input = {length: 1, value: '', val: function (value) { if (arguments.length) { this.value = value; return this; } return this.value; }};
var form = {find: function () { return {first: function () { return input; }}; }};
function $(selector) {
    if (selector === '[data-commission-transfer-intent]') return {first: function () { return external; }};
    return {appendTo: function () { return input; }};
}
$.trim = function (value) { return String(value || '').trim(); };
JS
            : <<<'JS'
var input = {length: 1, value: '', val: function (value) { if (arguments.length) { this.value = value; return this; } return this.value; }};
var form = {find: function () { return {first: function () { return input; }}; }};
function $(selector) { return {appendTo: function () { return input; }}; }
$.trim = function (value) { return String(value || '').trim(); };
JS;

        return $this->executeJavascriptJson(<<<JS
'use strict';
var uuidCount = 0;
var window = {crypto: {randomUUID: function () { uuidCount++; return 'shell-' + uuidCount; }}};
{$extra}
{$generator}
{$ensurer}
var keys = [];
keys.push(ensureCommissionTransferKey(form));
keys.push(ensureCommissionTransferKey(form));
input.value = '';
keys.push(ensureCommissionTransferKey(form));
window.crypto = {};
input.value = '';
var withoutCrypto = ensureCommissionTransferKey(form);
console.log(JSON.stringify({keys: keys, uuidCount: uuidCount, withoutCrypto: withoutCrypto}));
JS
        );
    }

    /**
     * 从 JS 源码中截取指定函数定义。
     *
     * @param string $source JS 源码全文。
     * @param string $name 函数名。
     * @return string 从 "function 名称(" 开始到下一个函数定义前的源码片段。
     */
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
}
