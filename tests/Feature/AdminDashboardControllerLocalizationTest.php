<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/06/08
 * Time: 08:37
 */

/**
 * AdminDashboardControllerLocalizationTest
 *
 * 文件功能：
 * - 验证后台统计控制器响应使用语言 key，且该 key 在 zh_CN 与 en 语言包中均已配置。
 * - 输入：语言包数组与渲染后的响应/脚本文本；输出：PHPUnit 断言结果。
 * - 明确不负责：不覆盖业务逻辑与路由契约（由各模块功能测试锁定）。
 */

namespace Tests\Feature;

use Tests\TestCase;

/**
 * 后台统计控制器多语言测试。
 *
 * 测试目的：
 * - 后台统计接口的 message 必须从 Laravel 语言包读取，避免 Blade + Layui 页面在切换语言时仍显示硬编码英文。
 * - `AdminDashboardController` 是旧后台统计入口之一，本测试先锁定响应文案来源，不改变统计 SQL 逻辑。
 */
class AdminDashboardControllerLocalizationTest extends TestCase
{
    /**
     * 验证后台统计控制器不再直接返回硬编码英文响应。
     *
     * @return void
     */
    public function test_admin_dashboard_controller_uses_language_key_for_response_message(): void
    {
        $source = file_get_contents(app_path('Http/Controllers/Admin/AdminDashboardController.php')) ?: '';

        $this->assertStringNotContainsString(
            'System statistics fetched',
            $source,
            '后台统计控制器仍存在硬编码英文响应：System statistics fetched'
        );

        $this->assertStringContainsString(
            "__('admin.system_statistics_fetched')",
            $source,
            '后台统计控制器缺少 system_statistics_fetched 语言包调用'
        );
    }

    /**
     * 验证后台统计控制器依赖的中英文语言包 key 均已配置。
     *
     * @return void
     */
    public function test_admin_dashboard_response_language_key_exists_in_zh_cn_and_en(): void
    {
        $zhCn = require resource_path('lang/zh-CN/admin.php');
        $en = require resource_path('lang/en/admin.php');

        $this->assertArrayHasKey('system_statistics_fetched', $zhCn, 'zh-CN/admin.php 缺少 system_statistics_fetched');
        $this->assertArrayHasKey('system_statistics_fetched', $en, 'en/admin.php 缺少 system_statistics_fetched');
        $this->assertNotSame('', trim((string) $zhCn['system_statistics_fetched']), 'zh-CN system_statistics_fetched 不能为空');
        $this->assertNotSame('', trim((string) $en['system_statistics_fetched']), 'en system_statistics_fetched 不能为空');
    }
}
