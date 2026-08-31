<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/02
 * Time: 01:53
 */

declare(strict_types=1);

/**
 * 前端入金退款状态文案-封闭模块测试。
 *
 * 文件功能：
 * - 验证 FrontLegacyData::depositStatusText('05') 在 zh-CN 与 en 语言下分别返回 "已退款" 与 "Refunded"。
 *
 * 适用场景：
 * - 入金记录退款状态（05）文案多语言输出的回归测试。
 *
 * 入参例子：
 * - app()->setLocale('zh-CN'); FrontLegacyData::depositStatusText('05')
 * - app()->setLocale('en'); FrontLegacyData::depositStatusText('05')
 *
 * 返回值：
 * - zh-CN 返回字符串 "已退款"；en 返回字符串 "Refunded"。
 *
 * 异常或失败场景：
 * - 若任一语言下返回值与预期文案不符，测试失败。
 */

namespace Tests\Feature;

use App\Support\FrontLegacyData;
use Tests\TestCase;

class FrontDepositRefundStatusTextClosureModuleTest extends TestCase
{
    /**
     * 验证退款状态码 05 在中文与英文下均有明确文案。
     *
     * 切换 locale 后分别断言 depositStatusText('05') 的返回值。
     */
    public function test_refunded_deposit_status_has_explicit_chinese_and_english_labels(): void
    {
        app()->setLocale('zh-CN');
        $this->assertSame('已退款', FrontLegacyData::depositStatusText('05'));

        app()->setLocale('en');
        $this->assertSame('Refunded', FrontLegacyData::depositStatusText('05'));
    }
}
