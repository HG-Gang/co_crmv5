<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/07/09
 * Time: 04:31
 */

/**
 * FrontFlowControllerCommentReadabilityTest
 *
 * 文件功能：
 * - 验证前台 FlowController 对入金/出金/返佣/直属客户/直属代理流水、旧前台搜索入口和导出下载均有中文逻辑说明。
 * - 输入：控制器/模型/JS/Blade/CSS 等源码与语言包文本；输出：PHPUnit 断言结果。
 * - 明确不负责：不验证运行时业务行为与数据库交互。
 */

namespace Tests\Feature;

use Tests\TestCase;

/**
 * 前台资金流水控制器中文注释可读性测试。
 *
 * 测试目标：
 * - 本测试只读取 Front\FlowController 源码，不连接真实数据库。
 * - 约束入金流水、出金流水、返佣流水、直属客户流水、直属代理流水、旧前台搜索入口和导出下载必须有中文逻辑说明。
 */
class FrontFlowControllerCommentReadabilityTest extends TestCase
{
    public function test_front_flow_controller_contains_required_chinese_logic_comments(): void
    {
        $source = file_get_contents(app_path('Http/Controllers/Front/FlowController.php')) ?: '';

        $expectedComments = [
            '前台账户流水控制器',
            '处理入金流水、出金流水、返佣流水、直属客户流水、直属代理流水、旧前台流水搜索和导出下载',
            'accountFlow 用于返回当前用户账户流水汇总列表',
            'flow_type 表示流水类型',
            'flowType 表示旧前台提交的驼峰流水类型别名',
            'date_from 表示流水开始日期',
            'date_to 表示流水结束日期',
            'deposit_records 表示入金流水来源表',
            'withdraw_records 表示出金流水来源表',
            'commission_records 表示返佣流水来源表',
            'local_order_no 表示本地订单号',
            'third_order_no 表示第三方订单号',
            'flow_type_text 表示前端展示的流水类型文案',
            'totalRow 表示当前筛选条件下的汇总行',
            'typedFlow 用于按指定流水类型查询单类流水',
            'agentId 表示当前登录代理或用户的业务用户 ID',
            'withdraw_source 表示出金来源筛选字段',
            'applyWithdrawSourceFilter 用于按银行转账或数字货币过滤出金流水',
            'depositExport 用于导出直属客户入金流水 CSV',
            'direct_deposit_userId 表示旧前台提交的直属客户用户 ID',
            'direct_deposit_id 表示旧前台提交的入金订单号筛选',
            'direct_deposit_startdate 表示直属客户入金导出开始日期',
            'direct_deposit_enddate 表示直属客户入金导出结束日期',
            'downloadFile 用于下载前台流水导出文件',
            'file 表示待下载的导出文件名',
            'role 表示旧前台下载路由携带的角色标识',
            'depositFlowSearch 用于兼容旧前台入金流水搜索入口',
            'withdrawalFlowSearch 用于兼容旧前台出金流水搜索入口',
            'withdrawApplyFlowSearch 用于兼容旧前台出金申请流水搜索入口',
            'directDepositFlowSearch 用于兼容旧前台直属客户入金流水搜索入口',
            'directWithdrawalFlowSearch 用于兼容旧前台直属客户出金流水搜索入口',
            'legacyTypedFlow 用于兼容旧前台独立流水搜索入口',
            'withdrawDisplayOrderNo 用于标准化出金展示订单号',
            'withdrawSourceText 用于返回出金来源文案',
            'flowScopeUserIds 用于按流水类型计算可见用户范围',
        ];

        foreach ($expectedComments as $expectedComment) {
            $this->assertStringContainsString($expectedComment, $source, 'Front FlowController 缺少中文逻辑注释：' . $expectedComment);
        }
    }

    public function test_front_flow_controller_does_not_keep_legacy_english_comment_titles(): void
    {
        $source = file_get_contents(app_path('Http/Controllers/Front/FlowController.php')) ?: '';

        $legacyEnglishComments = [
            'Front Account Flow Controller',
            'Lists all account transactions including deposits, withdrawals, and commissions.',
            'List all account transactions (deposits, withdrawals, commissions)',
            'Query deposits',
            'Query withdrawals',
            'Query commissions.',
            'Combine and paginate',
            'commission_records uses the rebuilt schema field commission_amount',
            'All three source tables use integer timestamps',
            'Assume 02 is completed',
        ];

        foreach ($legacyEnglishComments as $legacyEnglishComment) {
            $this->assertStringNotContainsString($legacyEnglishComment, $source, 'Front FlowController 仍残留旧英文注释标题：' . $legacyEnglishComment);
        }
    }
}
