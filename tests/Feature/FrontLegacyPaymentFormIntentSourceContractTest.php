<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/02
 * Time: 01:45
 */

/**
 * 遗留支付表单意图源码契约测试。
 *
 * 文件功能：
 * - 以源码断言方式锁定充值/出金页面的表单意图契约：
 *   - 充值页引入依赖脚本并提交渲染出的意图 nonce（idempotency_key 与 Idempotency-Key 头）。
 *   - 出金提交优先使用页面渲染的意图 key。
 *   - 遗留出金响应同时保留旧字段（msg/err/col）与新合约。
 * - 锁定 LegacyFormIntentService 的默认 TTL 与最大意图数常量。
 *
 * 适用场景：
 * - 防止前端脚本或响应合约在改动中漂移，保证遗留表单意图桥可回滚兼容。
 *
 * 入参例子：
 * - 读取资源文件：resource_path('front/layui/deposit/index_v2.blade.php')、
 *   public_path('js/shared/deposit-page-core.js')、
 *   public_path('js/apps/front/layui/pages.js')、
 *   app_path('Http/Controllers/Front/WithdrawController.php')。
 *
 * 返回值：
 * - 无返回值；全部通过字符串包含/常量相等断言。
 *
 * 异常或失败场景：
 * - 脚本或控制器源码缺失关键契约片段、TTL/容量常量变化时断言失败。
 */

declare(strict_types=1);

namespace Tests\Feature;

use Tests\TestCase;

final class FrontLegacyPaymentFormIntentSourceContractTest extends TestCase
{
    // 验证充值 iframe 加载依赖并提交渲染出的意图 nonce。
    public function test_deposit_iframe_loads_dependencies_and_submits_the_rendered_intent(): void
    {
        $blade = (string) file_get_contents(resource_path('front/layui/deposit/index_v2.blade.php'));
        $core = (string) file_get_contents(public_path('js/shared/deposit-page-core.js'));

        $this->assertStringContainsString("/js/shared/pay-channel-manager.js", $blade);
        $this->assertStringContainsString("/js/shared/deposit-page-core.js", $blade);
        $this->assertStringContainsString("[name=\"idempotency_key\"]", $core);
        $this->assertStringContainsString("headers: {'Idempotency-Key': requestKey}", $core);
        $this->assertStringContainsString('idempotency_key: requestKey', $core);
    }

    // 验证出金提交优先使用渲染出的遗留意图 key。
    public function test_withdraw_submission_prefers_the_rendered_legacy_intent(): void
    {
        $source = (string) file_get_contents(public_path('js/apps/front/layui/pages.js'));

        $this->assertStringContainsString('function renderedWithdrawIntentKey()', $source);
        $this->assertStringContainsString('requestKey = renderedKey || currentWithdrawIdempotencyKey(amount)', $source);
        $this->assertStringContainsString('idempotency_key: requestKey', $source);
    }

    // 验证遗留出金响应同时保留旧字段与新合约字段。
    public function test_legacy_withdraw_response_preserves_old_and_modern_contracts(): void
    {
        $source = (string) file_get_contents(app_path('Http/Controllers/Front/WithdrawController.php'));

        $this->assertStringContainsString('legacyWithdrawResponse', $source);
        $this->assertStringContainsString("\$payload['msg'] = \$isSuccess ? 'SUC' : 'FAIL'", $source);
        $this->assertStringContainsString("\$payload['err']", $source);
        $this->assertStringContainsString("\$payload['col']", $source);
        $this->assertStringContainsString('return $this->legacyWithdrawResponse($this->submitWithdraw($request));', $source);
    }

    // 验证表单意图默认 TTL 与最大意图数是公开契约的一部分。
    public function test_default_intent_limits_are_part_of_the_public_contract(): void
    {
        $this->assertSame(900, \App\Services\Legacy\LegacyFormIntentService::TTL_SECONDS);
        $this->assertSame(12, \App\Services\Legacy\LegacyFormIntentService::MAX_INTENTS);
    }
}
