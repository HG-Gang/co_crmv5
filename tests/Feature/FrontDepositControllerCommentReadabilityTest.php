<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/07/11
 * Time: 09:52
 */

/**
 * FrontDepositControllerCommentReadabilityTest
 *
 * 文件功能：
 * - 验证前台 DepositController 记录当前入金与注册链路的中文说明，且不恢复不安全的回退实现。
 * - 输入：控制器/模型/JS/Blade/CSS 等源码与语言包文本；输出：PHPUnit 断言结果。
 * - 明确不负责：不验证运行时业务行为与数据库交互。
 */

namespace Tests\Feature;

use Tests\TestCase;

/**
 * 前台入金控制器中文注释与安全边界可读性测试。
 */
class FrontDepositControllerCommentReadabilityTest extends TestCase
{
    public function test_front_deposit_controller_documents_current_deposit_and_registry_chain(): void
    {
        $source = file_get_contents(app_path('Http/Controllers/Front/DepositController.php')) ?: '';

        foreach ([
            '前台入金管理控制器',
            '处理入金页面配置、入金申请、旧前台入金接口兼容和入金历史记录',
            'depositPage 用于返回前台入金页初始化数据',
            'channels 表示可用支付通道列表',
            'exchange_rates 表示前台展示的币种汇率',
            'deposit_limits 表示全局入金限额',
            'submitDeposit 用于提交新版前台入金申请',
            'amount 表示新版入金金额',
            'deposit_amt_usd 表示旧页面提交的美元入金金额',
            'channel 表示新版支付通道编码',
            'pay_channel 表示旧页面支付通道字段',
            'local_order_no 表示本地入金订单号',
            'status=01 表示入金未支付',
            'deposit_request 用于兼容旧前台入金申请接口',
            'deposit_request_otc 用于兼容旧前台 OTC 入金申请接口',
            'depositHistory 用于返回当前用户入金历史记录',
            'status 表示入金记录状态筛选',
            'frontChannels 用于构建前台可展示支付通道',
            '只展示具备可调用白名单 adapter 的通道',
            'resolvePaymentChannel 用于校验并标准化前端提交的通道',
            '不再重开内置 fallback 通道',
            'amountLimits 用于读取全局入金金额上下限',
            'depositAvailability 用于判断当前用户和系统是否允许入金',
            'legacyChannelMeta 用于读取旧通道限额和类型',
        ] as $expectedComment) {
            $this->assertStringContainsString(
                $expectedComment,
                $source,
                'Front DepositController 缺少中文逻辑注释：' . $expectedComment
            );
        }
    }

    public function test_front_deposit_controller_does_not_restore_unsafe_fallback_implementation(): void
    {
        $source = file_get_contents(app_path('Http/Controllers/Front/DepositController.php')) ?: '';

        $this->assertStringNotContainsString('function fallbackChannels', $source);
        $this->assertStringNotContainsString('function configValue', $source);
        $this->assertStringNotContainsString('hasCompleteAdapterConfig', $source);
        $this->assertStringContainsString('PaymentGatewayRegistry::class', $source);
    }
}
