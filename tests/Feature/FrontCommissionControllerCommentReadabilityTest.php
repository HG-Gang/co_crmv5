<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/06/08
 * Time: 23:22
 */

/**
 * FrontCommissionControllerCommentReadabilityTest
 *
 * 文件功能：
 * - 验证前台 CommissionController 对实时返佣、旧返佣详情、返佣历史、统计分析、转账下级代理选项和佣金转账均有中文逻辑说明。
 * - 输入：控制器/模型/JS/Blade/CSS 等源码与语言包文本；输出：PHPUnit 断言结果。
 * - 明确不负责：不验证运行时业务行为与数据库交互。
 */

namespace Tests\Feature;

use Tests\TestCase;

/**
 * 前台返佣控制器中文注释可读性测试。
 *
 * 测试目标：
 * - 本测试只读取 Front\CommissionController 源码，不连接真实数据库。
 * - 约束实时返佣、旧返佣详情、返佣历史、统计分析、转账下级代理选项和佣金转账必须有中文逻辑说明。
 */
class FrontCommissionControllerCommentReadabilityTest extends TestCase
{
    public function test_front_commission_controller_contains_required_chinese_logic_comments(): void
    {
        $source = file_get_contents(app_path('Http/Controllers/Front/CommissionController.php')) ?: '';

        $expectedComments = [
            '前台返佣管理控制器',
            '处理实时返佣计算、返佣历史、返佣统计分析、旧前台返佣详情和佣金转账',
            'commissionService 表示返佣计算服务',
            'realTime 用于返回当前代理可见的实时返佣订单列表',
            'detail_commission 表示是否返回逐级返佣明细',
            'userId 表示被筛选的下级客户或代理业务用户 ID',
            'orderId 表示 MT4 订单号筛选字段',
            'current_commission_amount 表示当前代理在该订单中的返佣金额',
            'realtimeRebateSearch 用于兼容旧前台实时返佣搜索入口',
            'realtimeRebateDetail 用于兼容旧前台实时返佣详情弹层',
            'orderNo 表示旧前台传入的 MT4 订单号',
            'role 表示旧前台详情弹层展示的查看角色',
            'userDetail 用于返回订单所属用户基础资料',
            'orderChain 用于返回当前代理可见的订单用户代理链路',
            'currentAgentOrderCommission 用于计算当前代理在单笔订单中的返佣状态',
            'history 用于返回当前代理已结算或待结算的返佣历史',
            'date_from 表示返佣历史开始日期',
            'date_to 表示返佣历史结束日期',
            'dataType 表示返佣记录类型筛选字段',
            'commissionHistoryAnalytics 用于返回返佣趋势和性别维度统计',
            'transferAgentOptions 用于返回当前代理可转账的直属下级代理选项',
            'sub_agent_id 表示接收佣金转账的直属下级代理 ID',
            'transfer 用于把当前代理返佣余额转给直属下级代理',
            'amount 表示佣金转账金额',
            'remark 表示佣金转账备注',
            'DBCT 表示下级代理入账流水',
            'WBCT 表示当前代理出账流水',
        ];

        foreach ($expectedComments as $expectedComment) {
            $this->assertStringContainsString($expectedComment, $source, 'Front CommissionController 缺少中文逻辑注释：' . $expectedComment);
        }
    }

    public function test_front_commission_controller_does_not_keep_legacy_english_comment_titles(): void
    {
        $source = file_get_contents(app_path('Http/Controllers/Front/CommissionController.php')) ?: '';

        $legacyEnglishComments = [
            'Front Commission Management Controller',
            'Handles real-time commission calculation, history, and transfers.',
            'Calculate real-time commission for current agent',
            'Get settled commission history',
            'Get settled commission history.',
            'Commission transfer to sub-agent',
            'Verify sub-agent belongs to current agent',
            'Handle transfer (update both account balances and write a commission flow record).',
            'Deduct from agent',
            'Add to sub-agent',
            'Receiver deposit side: DBCT, sender withdrawal side: WBCT.',
        ];

        foreach ($legacyEnglishComments as $legacyEnglishComment) {
            $this->assertStringNotContainsString($legacyEnglishComment, $source, 'Front CommissionController 仍残留旧英文注释标题：' . $legacyEnglishComment);
        }
    }
}
