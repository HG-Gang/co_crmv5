<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/06/09
 * Time: 00:28
 */

/**
 * FrontCustomerControllerCommentReadabilityTest
 *
 * 文件功能：
 * - 验证前台 CustomerController 对代理客户范围、直属筛选、客户名称筛选、交易统计和活跃客户统计均有中文逻辑说明。
 * - 输入：控制器/模型/JS/Blade/CSS 等源码与语言包文本；输出：PHPUnit 断言结果。
 * - 明确不负责：不验证运行时业务行为与数据库交互。
 */

namespace Tests\Feature;

use Tests\TestCase;

/**
 * 前台客户控制器中文注释可读性测试。
 *
 * 测试目标：
 * - 只读取 Front\CustomerController 源码，不连接真实数据库。
 * - 约束代理客户范围、直属筛选、客户名称筛选、交易统计和活跃客户统计必须具备中文逻辑说明。
 */
class FrontCustomerControllerCommentReadabilityTest extends TestCase
{
    public function test_front_customer_controller_contains_required_chinese_logic_comments(): void
    {
        $source = file_get_contents(app_path('Http/Controllers/Front/CustomerController.php')) ?: '';

        $expectedComments = [
            '前台客户控制器',
            '处理当前代理可见客户列表、直属客户筛选、客户名称筛选、客户交易统计和客户汇总统计',
            'myCustomers 用于返回当前代理可见的客户列表',
            'userLogin 表示当前 user guard 登录记录',
            'agentId 表示当前代理业务用户 ID',
            'descendant_type=2 表示只查询客户节点',
            'direct_only 表示是否只查询直属客户',
            'user_name 表示客户姓名模糊筛选关键字',
            'per_page 表示每页客户数量',
            'trade_stats 表示追加到每个客户节点上的交易统计',
            'total_volume 表示客户交易总手数',
            'total_profit 表示客户交易总盈亏',
            'trade_count 表示客户交易订单数量',
            'stats 用于返回当前代理客户统计摘要',
            'descendantIds 表示当前代理名下全部客户 ID 集合',
            'totalCustomers 表示客户总数',
            'activeCount 表示最近一个月有交易的活跃客户数',
            'inactive_customers 表示未活跃客户数',
        ];

        foreach ($expectedComments as $expectedComment) {
            $this->assertStringContainsString($expectedComment, $source, 'Front CustomerController 缺少中文逻辑注释：' . $expectedComment);
        }
    }

    public function test_front_customer_controller_does_not_keep_legacy_english_comment_titles(): void
    {
        $source = file_get_contents(app_path('Http/Controllers/Front/CustomerController.php')) ?: '';

        $legacyEnglishComments = [
            'List current agent\'s direct and indirect customers',
            'Add trade stats for each customer',
            'Customer statistics summary',
            'Active customers (traded in last month)',
            'Total volume',
        ];

        foreach ($legacyEnglishComments as $legacyEnglishComment) {
            $this->assertStringNotContainsString($legacyEnglishComment, $source, 'Front CustomerController 仍残留旧英文注释标题：' . $legacyEnglishComment);
        }
    }
}
