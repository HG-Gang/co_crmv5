<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/07/11
 * Time: 08:01
 */

/**
 * FrontPaymentNotifyControllerCommentReadabilityTest
 *
 * 文件功能：
 * - 验证前台支付回调控制器对旧入金/出金回调、新支付回调、同步返回页和网关映射均有中文逻辑说明且无旧英文标题。
 * - 输入：控制器/模型/JS/Blade/CSS 等源码与语言包文本；输出：PHPUnit 断言结果。
 * - 明确不负责：不验证运行时业务行为与数据库交互。
 */

namespace Tests\Feature;

use Tests\TestCase;

/**
 * 前台支付回调控制器中文注释与兼容边界可读性测试。
 *
 * 测试目标：
 * - 只读取 PaymentNotifyController 源码，不连接真实数据库、不触发真实支付回调。
 * - 约束旧前台入金/出金回调、新前台支付回调、同步返回页和网关映射都具备中文逻辑说明。
 * - 约束生产代码不能继续保留旧英文标题，避免后续排查支付回调时看不清安全边界。
 */
class FrontPaymentNotifyControllerCommentReadabilityTest extends TestCase
{
    public function test_payment_notify_controller_contains_required_chinese_logic_comments(): void
    {
        $source = file_get_contents(app_path('Http/Controllers/Front/PaymentNotifyController.php')) ?: '';

        $expectedComments = [
            '前台支付回调控制器',
            'legacy /user/deposit_*',
            'POST /api/front/payment/notify/{gateway}',
            'GET /api/front/payment/return/{gateway}',
            'gateway 表示支付网关标识',
            'payload 表示第三方支付平台回传的完整参数',
            '所有通知都失败关闭',
            '已知旧网关只表示路由可识别',
            '未知网关返回 404',
            '返回 422',
            '请求体哈希和拒绝原因',
            'legacyGatewayName 用于把旧路由路径映射为统一网关标识',
        ];

        foreach ($expectedComments as $expectedComment) {
            $this->assertStringContainsString($expectedComment, $source, 'PaymentNotifyController 缺少中文逻辑注释：' . $expectedComment);
        }
    }

    public function test_payment_notify_controller_no_longer_uses_legacy_english_title(): void
    {
        $source = file_get_contents(app_path('Http/Controllers/Front/PaymentNotifyController.php')) ?: '';

        $this->assertStringNotContainsString('Payment gateway notify/return endpoints', $source);
        $this->assertStringContainsString("Log::warning('front.payment.callback_rejected'", $source);
        $this->assertStringContainsString("return response('callback_not_configured', 422)", $source);
        $this->assertStringContainsString("return redirect()->route('front_page_deposit'", $source);
    }
}
