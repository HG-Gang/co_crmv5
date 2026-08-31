<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/06/08
 * Time: 23:36
 */

/**
 * FrontCancelControllerCommentReadabilityTest
 *
 * 文件功能：
 * - 验证前台 CancelController 对销户申请、重复待审校验、持仓校验、资金校验、旧前台兼容入口和最新状态查询均有中文逻辑说明。
 * - 输入：控制器/模型/JS/Blade/CSS 等源码与语言包文本；输出：PHPUnit 断言结果。
 * - 明确不负责：不验证运行时业务行为与数据库交互。
 */

namespace Tests\Feature;

use Tests\TestCase;

/**
 * 前台销户申请控制器中文注释可读性测试。
 *
 * 测试目标：
 * - 本测试只读取 Front\CancelController 源码，不连接真实数据库。
 * - 约束销户申请、重复待审校验、持仓校验、资金校验、旧前台兼容入口和最新状态查询必须有中文逻辑说明。
 */
class FrontCancelControllerCommentReadabilityTest extends TestCase
{
    public function test_front_cancel_controller_contains_required_chinese_logic_comments(): void
    {
        $source = file_get_contents(app_path('Http/Controllers/Front/CancelController.php')) ?: '';

        $expectedComments = [
            '前台销户申请控制器',
            '处理当前前台用户提交销户申请、旧前台销户兼容入口和最近一次销户申请状态查询',
            'apply 用于提交当前前台用户的销户申请',
            'reason 表示新版前台提交的销户原因',
            'cancel_applies 表示销户申请数据表',
            'status=0 表示待后台审核',
            'cancel_remark 表示用户提交的销户原因',
            'reject_reason 表示后台拒绝原因或旧表兼容原因字段',
            '重复待审销户申请会被拒绝',
            'UserTrade::open 用于判断当前用户是否仍有未平仓订单',
            'total_funds 表示当前账户总资金',
            'equity 表示当前账户净值',
            'ajaxCancelAccount 用于兼容旧前台销户提交入口',
            'cancelRemark 表示旧前台提交的销户原因字段',
            'remark 表示旧模板可能提交的原因字段别名',
            'status 用于返回当前前台用户最近一次销户申请',
            '最新一条 cancel_applies 记录',
        ];

        foreach ($expectedComments as $expectedComment) {
            $this->assertStringContainsString($expectedComment, $source, 'Front CancelController 缺少中文逻辑注释：' . $expectedComment);
        }
    }

    public function test_front_cancel_controller_does_not_keep_legacy_english_comment_titles(): void
    {
        $source = file_get_contents(app_path('Http/Controllers/Front/CancelController.php')) ?: '';

        $legacyEnglishComments = [
            'Front account cancellation controller.',
            'This controller rebuilds the old front-office cancellation workflow:',
            'Submit an account cancellation request for the current front user.',
            'The request is stored in cancel_applies with status 0 (pending).',
            'Prevent duplicate pending requests.',
            'Open orders must be closed before cancellation',
            'The rebuilt schema stores old user_money/equity style balances',
            'Compatibility fallback for databases that have not run the',
            'Return the latest cancellation application for the current front user.',
        ];

        foreach ($legacyEnglishComments as $legacyEnglishComment) {
            $this->assertStringNotContainsString($legacyEnglishComment, $source, 'Front CancelController 仍残留旧英文注释标题：' . $legacyEnglishComment);
        }
    }
}
