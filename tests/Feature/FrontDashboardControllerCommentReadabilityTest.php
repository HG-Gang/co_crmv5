<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/06/08
 * Time: 23:52
 */

/**
 * FrontDashboardControllerCommentReadabilityTest
 *
 * 文件功能：
 * - 验证前台 DashboardController 对仪表盘数据、代理/客户统计口径、下载配置、新闻多语言、旧前台热点新闻和邀请注册链接均有中文逻辑说明。
 * - 输入：控制器/模型/JS/Blade/CSS 等源码与语言包文本；输出：PHPUnit 断言结果。
 * - 明确不负责：不验证运行时业务行为与数据库交互。
 */

namespace Tests\Feature;

use Tests\TestCase;

/**
 * 前台仪表盘控制器中文注释可读性测试。
 *
 * 测试目标：
 * - 本测试只读取 Front\DashboardController 源码，不连接真实数据库。
 * - 约束仪表盘数据、代理/客户统计口径、下载配置、新闻多语言、旧前台热点新闻和邀请注册链接必须有中文逻辑说明。
 */
class FrontDashboardControllerCommentReadabilityTest extends TestCase
{
    public function test_front_dashboard_controller_contains_required_chinese_logic_comments(): void
    {
        $source = file_get_contents(app_path('Http/Controllers/Front/DashboardController.php')) ?: '';

        $expectedComments = [
            '前台仪表盘控制器',
            '处理前台首页 Blade 视图、账户摘要、代理层级统计、入金出金交易月度统计、新闻公告、旧前台热点新闻和礼品提示状态',
            'familyTreeService 表示代理层级统计服务',
            'index 用于渲染前台 Layui 仪表盘 Blade 页面',
            'dashboardData 用于返回当前前台用户首页账户摘要数据',
            'userLogin 表示 user_logins 登录账号',
            'userInfo 表示 user_infos 业务资料',
            'scopeUserIds 表示当前首页统计允许聚合的业务用户 ID 列表',
            'descendantIds 表示当前代理名下所有后代用户 ID',
            'monthStart 表示最近 30 天统计窗口的 Unix 时间戳',
            'monthlyDeposits 表示最近 30 天入金金额汇总',
            'monthlyWithdraws 表示最近 30 天出金申请金额汇总',
            'monthlyOpenOrders 表示最近 30 天新开仓订单数量',
            'monthlyClosedOrders 表示最近 30 天平仓订单数量',
            'news 表示首页展示的最新公告列表',
            'share_urls 表示代理专属注册链接集合',
            'downloads 表示 PC 和移动端下载地址配置',
            'frontMsg 用于兼容旧前台消息面板入口',
            'hotNews 用于兼容旧前台首页热点新闻 HTML 列表接口',
            'hotNewsV2 用于兼容旧前台注册页热点新闻表格接口',
            'hasShowGiftTips 用于记录当前用户已查看礼品提示',
            'localizedNewsTitle 用于按当前语言读取新闻标题',
            'configValue 用于从新旧系统配置键中读取第一个有效值',
            'isObsoleteVersionProbe 用于过滤旧版本探测地址',
            'registerShareUrls 用于生成代理邀请注册链接集合',
        ];

        foreach ($expectedComments as $expectedComment) {
            $this->assertStringContainsString($expectedComment, $source, 'Front DashboardController 缺少中文逻辑注释：' . $expectedComment);
        }
    }

    public function test_front_dashboard_controller_does_not_keep_legacy_english_comment_titles(): void
    {
        $source = file_get_contents(app_path('Http/Controllers/Front/DashboardController.php')) ?: '';

        $legacyEnglishComments = [
            'Front Dashboard Controller',
            'Provides dashboard views and account summary data.',
            'Dashboard view',
            'Account summary data',
            'Trading records preserve MT4 open_time/close_time.',
            'Get first configured value from possible old/new keys.',
        ];

        foreach ($legacyEnglishComments as $legacyEnglishComment) {
            $this->assertStringNotContainsString($legacyEnglishComment, $source, 'Front DashboardController 仍残留旧英文注释标题：' . $legacyEnglishComment);
        }
    }
}
