<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/06/08
 * Time: 08:38
 */

/**
 * AdminDashboardControllerCommentReadabilityTest
 *
 * 文件功能：
 * - 验证 AdminDashboardController 的类、方法、请求参数和统计字段含义均有中文逻辑注释。
 * - 输入：控制器/模型/JS/Blade/CSS 等源码与语言包文本；输出：PHPUnit 断言结果。
 * - 明确不负责：不验证运行时业务行为与数据库交互。
 */

namespace Tests\Feature;

use Tests\TestCase;

/**
 * 后台统计控制器中文注释可读性测试。
 *
 * 测试目的：
 * - 用户要求所有模块文件和参数必须有详细中文逻辑注释。
 * - `AdminDashboardController` 仍承担旧后台系统统计接口，本测试确保类、方法、请求参数和统计字段含义都有中文说明。
 */
class AdminDashboardControllerCommentReadabilityTest extends TestCase
{
    /**
     * 验证后台统计控制器包含中文逻辑注释和参数含义说明。
     *
     * @return void
     */
    public function test_admin_dashboard_controller_has_chinese_logic_and_parameter_comments(): void
    {
        $source = file_get_contents(app_path('Http/Controllers/Admin/AdminDashboardController.php')) ?: '';

        $requiredComments = [
            '后台系统统计控制器',
            'dashboardData() 参数说明',
            'Request $request 当前 HTTP 请求对象',
            'total_users 表示用户总数',
            'total_agents 表示代理账号总数',
            'total_customers 表示普通客户总数',
            'pending_deposits 表示待审核入金数量',
            'pending_withdrawals 表示待处理出金数量',
            'today_new_users 表示今日新增用户数量',
            'account_type=1 表示代理账号',
            'created_at 为 10 位时间戳',
        ];

        foreach ($requiredComments as $comment) {
            $this->assertStringContainsString($comment, $source, "后台统计控制器缺少中文注释：{$comment}");
        }
    }
}
