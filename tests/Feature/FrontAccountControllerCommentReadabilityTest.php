<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/06/08
 * Time: 22:51
 */

/**
 * FrontAccountControllerCommentReadabilityTest
 *
 * 文件功能：
 * - 验证前台 AccountController 对账户概览、余额、凭证、旧接口兼容和账户类型切换参数保留中文逻辑说明。
 * - 输入：控制器/模型/JS/Blade/CSS 等源码与语言包文本；输出：PHPUnit 断言结果。
 * - 明确不负责：不验证运行时业务行为与数据库交互。
 */

namespace Tests\Feature;

use Tests\TestCase;

/**
 * 前台账户控制器中文注释可读性测试。
 *
 * 功能说明：
 * - 本测试只读取 Front\AccountController 源码，不连接真实数据库。
 * - 目标是约束账户概览、余额、凭证、旧接口兼容和账户类型切换参数必须保留中文逻辑说明。
 */
class FrontAccountControllerCommentReadabilityTest extends TestCase
{
    public function test_front_account_controller_has_chinese_logic_comments_for_account_and_voucher_parameters(): void
    {
        $source = file_get_contents(app_path('Http/Controllers/Front/AccountController.php')) ?: '';

        foreach ([
            '前台账户管理控制器',
            '处理账户信息、余额明细、凭证提交和旧前台账户接口兼容',
            'accountInfo 用于返回当前登录用户的账户综合数据',
            'accountBalance 用于返回当前登录用户的余额明细数据',
            'currentUserInfo 用于解析当前前台登录用户资料',
            'accountOverviewData 用于组装账户综合指标',
            'user_id 表示当前业务用户编号',
            'total_funds 表示账户总资金',
            'equity 表示账户净值',
            'used_margin 表示已用保证金',
            'avail_margin 表示可用保证金',
            'comm_rate 表示代理返佣比例',
            'customerGenderProfile 用于统计代理名下客户性别分布',
            'submitVoucher 用于提交新版凭证图片',
            'images 表示凭证图片数组',
            'remarks 表示凭证备注',
            'userVoucherSave 用于兼容旧前台凭证上传接口',
            'voucherimg 表示旧页面第一张凭证图片',
            'changeAccountSave 用于兼容旧前台账户类型切换接口',
            'is_enc 表示旧页面 ECN 标识',
            'voucherList 用于返回当前用户凭证列表',
            'review_status 表示凭证审核状态',
            'legacySuccess 用于返回旧前台成功响应结构',
            'legacyFail 用于返回旧前台失败响应结构',
        ] as $expectedComment) {
            $this->assertStringContainsString($expectedComment, $source, 'Front AccountController 缺少中文逻辑注释：' . $expectedComment);
        }
    }

    public function test_front_account_controller_no_longer_uses_legacy_english_comment_titles(): void
    {
        $source = file_get_contents(app_path('Http/Controllers/Front/AccountController.php')) ?: '';

        foreach ([
            'Front Account Management Controller',
            'Handles account information, balance details, and voucher submissions.',
            'Get current user account info',
            'Get detailed balance breakdown',
            'Upload voucher images for review',
            'Store to storage/app/public/vouchers',
            'List submitted vouchers',
            'Pending',
        ] as $legacyEnglishComment) {
            $this->assertStringNotContainsString($legacyEnglishComment, $source, 'Front AccountController 仍残留旧英文注释标题：' . $legacyEnglishComment);
        }
    }
}
