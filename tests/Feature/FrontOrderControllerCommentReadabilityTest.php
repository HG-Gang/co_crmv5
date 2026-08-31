<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/06/08
 * Time: 23:12
 */

/**
 * FrontOrderControllerCommentReadabilityTest
 *
 * 文件功能：
 * - 验证前台 OrderController 对持仓订单、历史订单、旧搜索入口、订单详情、代理链路和返佣明细均有中文逻辑说明。
 * - 输入：控制器/模型/JS/Blade/CSS 等源码与语言包文本；输出：PHPUnit 断言结果。
 * - 明确不负责：不验证运行时业务行为与数据库交互。
 */

namespace Tests\Feature;

use Tests\TestCase;

/**
 * 前台订单控制器中文注释可读性测试。
 *
 * 测试目标：
 * - 本测试只读取 Front\OrderController 源码，不连接真实数据库。
 * - 约束前台持仓订单、历史订单、旧搜索入口、订单详情、代理链路和返佣明细必须有中文逻辑说明。
 */
class FrontOrderControllerCommentReadabilityTest extends TestCase
{
    public function test_front_order_controller_contains_required_chinese_logic_comments(): void
    {
        $source = file_get_contents(app_path('Http/Controllers/Front/OrderController.php')) ?: '';

        $expectedComments = [
            '前台订单管理控制器',
            '处理当前持仓订单、历史平仓订单、旧前台订单搜索入口和订单详情弹层',
            'openOrders 用于返回当前用户可见的持仓订单列表',
            'orderId 表示旧前台和新版页面提交的订单号筛选字段',
            'symbol 表示交易品种筛选字段',
            'commission_details 表示代理账号查看订单时附带的返佣拆分明细',
            'closedOrders 用于返回当前用户可见的历史平仓订单列表',
            'is_coercion 表示旧前台强平筛选字段',
            'closeOrderSearch 用于兼容旧前台历史订单搜索入口',
            'openOrderDetail 用于兼容旧前台持仓订单详情弹层',
            'closeOrderDetail 用于兼容旧前台历史订单详情弹层',
            'userDetail 用于组装订单所属用户的展示资料',
            'orderChain 用于按 family_tree 返回当前查看代理可见的用户链路',
            'legacyOrderDetailHtml 用于生成旧前台订单详情 HTML',
            'legacyOrderChainHtml 用于生成旧前台订单链路 HTML',
            'legacyCommissionDetailsHtml 用于生成旧前台订单返佣明细 HTML',
            'legacyDetailItem 用于生成旧前台详情字段块',
        ];

        foreach ($expectedComments as $expectedComment) {
            $this->assertStringContainsString($expectedComment, $source, 'Front OrderController 缺少中文逻辑注释：' . $expectedComment);
        }
    }

    public function test_front_order_controller_does_not_keep_legacy_english_comment_titles(): void
    {
        $source = file_get_contents(app_path('Http/Controllers/Front/OrderController.php')) ?: '';

        $legacyEnglishComments = [
            'Front Order Management Controller',
            'Handles open and closed trading orders for users.',
            'List current open orders',
            'List historical closed orders',
        ];

        foreach ($legacyEnglishComments as $legacyEnglishComment) {
            $this->assertStringNotContainsString($legacyEnglishComment, $source, 'Front OrderController 仍残留旧英文注释标题：' . $legacyEnglishComment);
        }
    }
}
