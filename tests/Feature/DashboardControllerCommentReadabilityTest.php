<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/06/08
 * Time: 08:52
 */

/**
 * DashboardControllerCommentReadabilityTest
 *
 * 文件功能：
 * - 验证 DashboardController 对统计字段、日期参数和状态值含义均有中文逻辑注释。
 * - 输入：控制器/模型/JS/Blade/CSS 等源码与语言包文本；输出：PHPUnit 断言结果。
 * - 明确不负责：不验证运行时业务行为与数据库交互。
 */

namespace Tests\Feature;

use Tests\TestCase;

/**
 * 后台仪表盘统计控制器中文注释可读性测试。
 *
 * 测试目的：
 * - 用户要求所有模块文件和参数必须有详细中文逻辑注释。
 * - `DashboardController` 直接服务后台 Blade 仪表盘统计卡片和趋势图，必须明确统计字段、日期参数和状态值含义。
 */
class DashboardControllerCommentReadabilityTest extends TestCase
{
    /**
     * 验证仪表盘统计控制器包含中文类职责、统计字段和参数说明。
     *
     * @return void
     */
    public function test_dashboard_controller_has_chinese_logic_and_parameter_comments(): void
    {
        $source = file_get_contents(app_path('Http/Controllers/Admin/DashboardController.php')) ?: '';

        $requiredComments = [
            '后台仪表盘统计控制器',
            'index() 统计字段说明',
            'total_users 表示用户总数',
            'total_agents 表示代理账号总数',
            'total_customers 表示普通客户总数',
            'pending_deposits 表示待处理入金数量',
            'pending_withdrawals 表示待处理出金数量',
            'stats() 参数说明',
            'start_date 表示统计开始日期',
            'end_date 表示统计结束日期',
            'user_stats 表示用户注册趋势',
            'deposit_stats 表示入金金额趋势',
            'withdraw_stats 表示出金金额趋势',
            'status=02 表示入金已支付',
            'status=2 表示出金已完成',
        ];

        foreach ($requiredComments as $comment) {
            $this->assertStringContainsString($comment, $source, "DashboardController 缺少中文注释：{$comment}");
        }
    }
}
