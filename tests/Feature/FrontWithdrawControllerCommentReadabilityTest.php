<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/06/08
 * Time: 23:06
 */

/**
 * FrontWithdrawControllerCommentReadabilityTest
 *
 * 文件功能：
 * - 验证前台 WithdrawController 对出金页面、出金申请、旧接口兼容、出金历史、手续费、银行卡和状态字段均有中文逻辑说明。
 * - 输入：控制器/模型/JS/Blade/CSS 等源码与语言包文本；输出：PHPUnit 断言结果。
 * - 明确不负责：不验证运行时业务行为与数据库交互。
 */

namespace Tests\Feature;

use Tests\TestCase;

/**
 * 前台出金控制器中文注释可读性测试。
 *
 * 测试目标：
 * - 本测试只读取 Front\WithdrawController 源码，不连接真实数据库。
 * - 约束前台出金页面、出金申请、旧接口兼容、出金历史、手续费、银行卡和状态字段必须有中文逻辑说明。
 */
class FrontWithdrawControllerCommentReadabilityTest extends TestCase
{
    public function test_front_withdraw_controller_contains_required_chinese_logic_comments(): void
    {
        $source = file_get_contents(app_path('Http/Controllers/Front/WithdrawController.php')) ?: '';

        $expectedComments = [
            '前台出金管理控制器',
            '处理出金页面配置、出金申请、旧前台出金接口兼容和出金历史记录',
            'withdrawPage 用于返回前台出金页初始化数据',
            'bank_no 表示用户实名资料中的银行卡号',
            'withdraw_limits 表示出金金额上下限配置',
            'submitWithdraw 用于提交新版前台出金申请',
            'amount 表示新版接口提交的出金金额',
            'withdraw_amt 表示旧前台接口提交的出金金额',
            'password 表示当前登录账号密码',
            'agree 表示用户是否确认出金条款',
            'status=0 表示出金申请待后台审核',
            'local_order_no 表示本次出金申请的本地订单号',
            'fee 表示按固定手续费和比例手续费计算后的出金手续费',
            'withdraw_request 用于兼容旧前台出金申请接口',
            'withdraw_request_OTC 用于兼容旧 OTC 出金申请入口',
            'withdrawHistory 用于返回当前用户出金历史记录',
            'applystatus 表示旧前台表格使用的出金审核状态',
            'withdrawableAmount 用于计算当前用户可申请出金的余额',
            'withdrawAvailability 用于判断当前账号是否允许出金',
            'withdrawDisplayOrderNo 用于兼容旧前台订单号展示字段',
            'withdrawSourceText 用于返回旧前台出金来源文案',
        ];

        foreach ($expectedComments as $expectedComment) {
            $this->assertStringContainsString($expectedComment, $source, 'Front WithdrawController 缺少中文逻辑注释：' . $expectedComment);
        }
    }

    public function test_front_withdraw_controller_does_not_keep_legacy_english_comment_titles(): void
    {
        $source = file_get_contents(app_path('Http/Controllers/Front/WithdrawController.php')) ?: '';

        $legacyEnglishComments = [
            'Front Withdraw Management Controller',
            'Handles withdrawal page data, withdrawal requests, and history.',
            'Get withdraw page data (bank info, rates, limits)',
            'Submit withdrawal request',
            'List withdrawal records',
            'Legacy method for store',
            'Legacy method for records',
            'Check Risk Ratio',
            'Calculate fee',
            'Pending',
        ];

        foreach ($legacyEnglishComments as $legacyEnglishComment) {
            $this->assertStringNotContainsString($legacyEnglishComment, $source, 'Front WithdrawController 仍残留旧英文注释标题：' . $legacyEnglishComment);
        }
    }
}
