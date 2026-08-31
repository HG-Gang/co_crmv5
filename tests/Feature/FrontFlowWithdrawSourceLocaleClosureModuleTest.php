<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/28
 * Time: 01:51
 */

/**
 * FrontFlowWithdrawSourceLocaleClosureModuleTest
 *
 * 文件功能：
 * - 验证前台流水出金来源筛选与语言无关：option 提交稳定 key 而非当前语言译文、控制器接受两种出金来源稳定 key、Blade 中所有翻译 option 值均为稳定 key。
 * - 输入：路由、控制器、Blade/JS、迁移等项目源码文本；输出：PHPUnit 断言结果。
 * - 明确不负责：不覆盖 HTTP 运行时业务流与 MT4 真实网关同步（由集成与功能测试锁定）。
 */

namespace Tests\Feature;

use Tests\TestCase;

/**
 * 锁定前台账户流水「出金来源」筛选与语言无关。
 *
 * 缺陷背景：
 * - 该 select 的 option value 曾用 __('front.bank_transfer') 渲染，即把当前语言的译文当成提交值。
 * - option 上的 data-translate 只会在页内切换语言时改写「文案」，不会改写「value」，
 *   因此切换语言后显示英文、提交值仍是中文，控制器比对必然失败。
 * - FlowController::applyWithdrawSourceFilter 比对失败时会静默落到 bank_name LIKE 兜底，
 *   结果是筛选返回空列表且不报错，属于难以察觉的静默失效。
 *
 * 本测试锁定 option value 必须是稳定键，且控制器必须接受稳定键。
 */
class FrontFlowWithdrawSourceLocaleClosureModuleTest extends TestCase
{
    public function test_withdraw_source_options_submit_locale_independent_stable_keys(): void
    {
        $blade = file_get_contents(resource_path('front/layui/flow/index.blade.php')) ?: '';

        $this->assertStringContainsString(
            '<option value="bank_transfer"',
            $blade,
            '银行卡出金选项必须提交稳定键 bank_transfer。'
        );
        $this->assertStringContainsString(
            '<option value="crypto_currency"',
            $blade,
            '数字货币出金选项必须提交稳定键 crypto_currency。'
        );

        // 回归防护：value 不得再用 __() 渲染译文。
        $this->assertStringNotContainsString(
            'value="{{ __(\'front.bank_transfer\') }}"',
            $blade,
            'option value 不能使用译文，否则页内切换语言后提交值与文案不一致。'
        );
        $this->assertStringNotContainsString(
            'value="{{ __(\'front.crypto_currency\') }}"',
            $blade,
            'option value 不能使用译文，否则页内切换语言后提交值与文案不一致。'
        );
    }

    public function test_controller_accepts_stable_keys_for_both_withdraw_sources(): void
    {
        $controller = file_get_contents(app_path('Http/Controllers/Front/FlowController.php')) ?: '';

        $this->assertStringContainsString(
            "\$withdrawSource === 'bank_transfer'",
            $controller,
            '控制器必须接受稳定键 bank_transfer。'
        );
        $this->assertStringContainsString(
            "\$withdrawSource === 'crypto_currency'",
            $controller,
            '控制器必须接受稳定键 crypto_currency。'
        );
    }

    public function test_every_translated_option_value_in_flow_blade_is_a_stable_key(): void
    {
        $blade = file_get_contents(resource_path('front/layui/flow/index.blade.php')) ?: '';

        // 任何带 data-translate 的 option，其 value 都不允许是 Blade 译文表达式。
        preg_match_all('/<option value="([^"]*)"[^>]*data-translate=/', $blade, $matches);

        $this->assertNotEmpty($matches[1], '流水页应存在带 data-translate 的 option。');

        foreach ($matches[1] as $value) {
            $this->assertStringNotContainsString(
                '__(',
                $value,
                '带 data-translate 的 option value 必须是稳定键，不能是译文表达式：' . $value
            );
        }
    }
}
