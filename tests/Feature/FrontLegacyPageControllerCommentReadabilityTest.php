<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/06/09
 * Time: 01:01
 */

/**
 * FrontLegacyPageControllerCommentReadabilityTest
 *
 * 文件功能：
 * - 验证旧前台页面控制器中文注释与旧页面参数说明，且反馈提交使用本地化成功消息且无乱码。
 * - 输入：语言包数组与渲染后的响应/脚本文本；输出：PHPUnit 断言结果。
 * - 明确不负责：不覆盖业务逻辑与路由契约（由各模块功能测试锁定）。
 */

namespace Tests\Feature;

use Tests\TestCase;

/**
 * 旧前台页面控制器中文注释与旧页面参数可读性测试。
 *
 * 测试目标：
 * - 只读取 LegacyPageController 源码和语言包，不连接真实数据库。
 * - 约束旧前台页面入口必须说明映射到 Laravel Blade 的具体职责。
 * - 约束旧页面参数 legacyParentUserId、legacyTargetUserId、legacyAddressId 和 offweb_feedbacks 写入边界必须具备中文说明。
 */
class FrontLegacyPageControllerCommentReadabilityTest extends TestCase
{
    public function test_legacy_page_controller_contains_required_chinese_logic_comments(): void
    {
        $source = file_get_contents(app_path('Http/Controllers/Front/LegacyPageController.php')) ?: '';

        $expectedComments = [
            '旧前台页面控制器',
            'legacy user/* 页面入口',
            'Laravel Blade 模板',
            'front_layui::dashboard.index',
            'front_layui::deposit.index',
            'front_layui::withdraw.index',
            'front_layui::agent.customers',
            'legacyParentUserId 表示旧直属客户页面传入的上级代理用户 ID',
            'legacyTargetUserId 表示旧返佣转账或组别变更页面传入的目标用户 ID',
            'legacyAddressId 表示旧地址编辑页面传入的地址记录 ID',
            'offweb_feedbacks 表',
            'email 表示旧意见反馈提交的联系邮箱',
            'remarks 表示旧意见反馈提交的反馈内容',
            'logout 用于清理新 user guard 和旧 session suser 登录态',
        ];

        foreach ($expectedComments as $expectedComment) {
            $this->assertStringContainsString($expectedComment, $source, 'LegacyPageController 缺少中文逻辑注释：' . $expectedComment);
        }
    }

    public function test_legacy_feedback_uses_localized_success_message_and_no_mojibake(): void
    {
        $source = file_get_contents(app_path('Http/Controllers/Front/LegacyPageController.php')) ?: '';

        $this->assertStringContainsString("__('response.success')", $source, '旧意见反馈成功消息必须使用后端多语言 key。');
        foreach (['鍙戦€佹垚鍔', '�'] as $fragment) {
            $this->assertStringNotContainsString($fragment, $source, 'LegacyPageController 存在疑似乱码硬编码：' . $fragment);
        }

        $zhResponse = require resource_path('lang/zh-CN/response.php');
        $enResponse = require resource_path('lang/en/response.php');
        $this->assertArrayHasKey('success', $zhResponse);
        $this->assertArrayHasKey('success', $enResponse);
    }
}
