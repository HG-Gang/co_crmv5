<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/30
 * Time: 20:05
 */

/**
 * FrontBankNoMaskingClosureModuleTest
 *
 * 文件功能：
 * - 锁定前台银行卡号脱敏规则与两个调用点，防止完整卡号再次下发到前端。
 * - 旧行为取证：项目1 CustomerFlowController.php:308
 *     $formatData[$index]['drawbankno'] = substr($it['drawbankno'], 0, 4) . '****' . substr($it['drawbankno'], -4);
 *   即前台流水与出金列表**从不下发完整卡号**。
 * - 新项目此前在 Front\FlowController 与 Front\WithdrawController 直接返回 bank_no 原文，
 *   其中 WithdrawController 还先 toArray() 把模型全部属性摊平，连原始 bank_no 键一起下发。
 * - 输入：脱敏函数直接调用 + 两个控制器源码契约；输出：PHPUnit 断言结果。
 * - 明确不负责：不覆盖出金业务流与金额计算（由出金族测试锁定）。
 */

namespace Tests\Feature;

use App\Support\FrontLegacyData;
use Tests\TestCase;

/**
 * 前台银行卡号脱敏闭环测试。
 */
class FrontBankNoMaskingClosureModuleTest extends TestCase
{
    /**
     * 脱敏规则必须与旧项目逐位一致：保留前 4 与后 4，中间固定 `****`。
     *
     * @return void
     */
    public function test_mask_keeps_first_and_last_four_digits(): void
    {
        // 19 位卡号：旧项目 substr(0,4) . '****' . substr(-4) 的结果。
        $this->assertSame('6222****0123', FrontLegacyData::maskBankNo('6222021234567890123'));
        // 恰好 8 位：前 4 与后 4 不重叠，仍按规则脱敏。
        $this->assertSame('6222****7890', FrontLegacyData::maskBankNo('62227890'));
    }

    /**
     * 不足 8 位时整体打码，避免前后 4 位重叠反而暴露更多相邻位。
     *
     * @return void
     */
    public function test_short_card_numbers_are_fully_masked(): void
    {
        foreach (['1', '12', '1234', '1234567'] as $short) {
            $this->assertSame('****', FrontLegacyData::maskBankNo($short), "短卡号未整体打码：{$short}");
        }
    }

    /**
     * 无卡号时返回空串而非 `****`，与旧前台「未绑卡则该列为空」的表现一致。
     *
     * 返回 `****` 会让用户误以为已绑卡，属于比泄露更隐蔽的展示错误。
     *
     * @return void
     */
    public function test_missing_card_number_returns_empty_string(): void
    {
        $this->assertSame('', FrontLegacyData::maskBankNo(null));
        $this->assertSame('', FrontLegacyData::maskBankNo(''));
        $this->assertSame('', FrontLegacyData::maskBankNo('   '));
    }

    /**
     * 两个前台调用点都必须经脱敏函数输出，且 WithdrawController 必须覆盖 toArray() 摊平的原始键。
     *
     * 这里按源码契约断言而非发起 HTTP 请求：出金列表需要完整的登录态与出金夹具，
     * 而本用例要锁定的是「卡号不得以原文出现在响应构造中」这一静态约束，
     * 源码断言能在任何数据条件下都生效，不会因夹具缺失而空转通过。
     *
     * @return void
     */
    public function test_both_front_call_sites_mask_before_output(): void
    {
        $flow = file_get_contents(app_path('Http/Controllers/Front/FlowController.php')) ?: '';
        $withdraw = file_get_contents(app_path('Http/Controllers/Front/WithdrawController.php')) ?: '';

        // 流水页：drawbankno 必须走脱敏函数，不得直接取 bank_no 原文。
        $this->assertStringContainsString("'drawbankno' => FrontLegacyData::maskBankNo(", $flow);
        $this->assertStringNotContainsString("'drawbankno' => \$row->bank_no", $flow);

        // 出金列表：drawbankno 走脱敏，且必须显式覆盖 toArray() 带出的原始 bank_no 键。
        $this->assertStringContainsString("\$row['drawbankno'] = FrontLegacyData::maskBankNo(", $withdraw);
        $this->assertStringContainsString("\$row['bank_no'] = \$row['drawbankno'];", $withdraw);
        $this->assertStringNotContainsString("\$row['drawbankno'] = \$record->bank_no;", $withdraw);
    }
}
