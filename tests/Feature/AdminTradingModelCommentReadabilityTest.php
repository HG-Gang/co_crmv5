<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/06/09
 * Time: 08:46
 */

/**
 * AdminTradingModelCommentReadabilityTest
 *
 * 文件功能：
 * - 验证交易相关模型保持可读中文注释，禁止旧英文占位或乱码注释回流。
 * - 输入：控制器/模型/JS/Blade/CSS 等源码与语言包文本；输出：PHPUnit 断言结果。
 * - 明确不负责：不验证运行时业务行为与数据库交互。
 */

namespace Tests\Feature;

use Tests\TestCase;

/**
 * 交易订单、清零、转组和行情模型中文注释可读性测试。
 *
 * 功能逻辑说明：
 * - 本测试约束交易订单、余额信用清零、交易组变更申请和品种价格模型的中文注释质量。
 * - 这些模型会影响后台交易风控、持仓/平仓查询、清零操作、转组审核和行情展示，字段含义必须清楚。
 * - 测试只读取源码文件，不创建交易订单、清零记录、转组申请或行情数据。
 */
class AdminTradingModelCommentReadabilityTest extends TestCase
{
    /**
     * 交易相关模型必须包含真实表职责、关键字段和查询作用域说明。
     *
     * @return void
     */
    public function test_trading_models_contain_readable_chinese_logic_comments(): void
    {
        // $expectations 表示每个模型必须包含的中文说明片段；键名为模型路径，值为真实表和关键字段含义。
        $expectations = [
            app_path('Models/UserTrade.php') => [
                '用户交易模型',
                'user_trades 表保存用户 MT4 交易订单数据',
                'ticket 表示 MT4 订单号',
                'close_time 表示订单平仓时间',
                'settlement_status 表示订单返佣或结算状态',
                '$query 表示交易订单查询构造器',
            ],
            app_path('Models/WhsExpZero.php') => [
                '余额信用清零模型',
                'whs_exp_zeros 表保存用户余额或信用额度清零操作记录',
                'balance 表示清零前或清零目标余额',
                'credit 表示清零前或清零目标信用额度',
                'md5_key 表示清零请求校验签名',
            ],
            app_path('Models/TransApplyLog.php') => [
                '转组申请日志模型',
                'trans_apply_logs 表保存用户申请变更交易组的审核记录',
                'origin_group_id 表示原交易组 ID',
                'group_id 表示目标交易组 ID',
                'apply_reason 表示申请原因',
                'reject_reason 表示拒绝原因',
            ],
            app_path('Models/SymbolPrice.php') => [
                '交易品种价格模型',
                'symbol_prices 表保存交易品种实时或历史报价',
                'symbol 表示交易品种代码',
                'bid 表示买价',
                'ask 表示卖价',
                'spread 表示点差',
            ],
        ];

        foreach ($expectations as $file => $requiredFragments) {
            // $content 表示当前模型源码，用于确认注释覆盖真实表职责和关键字段含义。
            $content = file_get_contents($file);

            foreach ($requiredFragments as $fragment) {
                $this->assertStringContainsString($fragment, $content, $file . ' 缺少中文说明：' . $fragment);
            }
        }
    }

    /**
     * 交易相关模型不允许保留旧英文占位或乱码注释。
     *
     * @return void
     */
    public function test_trading_models_do_not_contain_mojibake_or_english_placeholders(): void
    {
        // $files 表示本轮直接维护的模型文件集合，用于将失败范围限制在当前修复边界内。
        $files = [
            app_path('Models/UserTrade.php'),
            app_path('Models/WhsExpZero.php'),
            app_path('Models/TransApplyLog.php'),
            app_path('Models/SymbolPrice.php'),
        ];

        // $forbiddenFragments 表示旧注释中常见的英文占位和 UTF-8/GBK 错解片段。
        $forbiddenFragments = [
            'Table Name',
            'Relation:',
            'Records user',
            'Records the operation',
            'Records the history',
            'Stores real-time',
            '鏁版嵁',
            '鍏宠仈',
            '浜ゆ槗',
            '浣欓',
            '杞粍',
        ];

        foreach ($files as $file) {
            // $content 表示当前模型源码，用于逐项排查不可读注释残留。
            $content = file_get_contents($file);

            foreach ($forbiddenFragments as $fragment) {
                $this->assertStringNotContainsString($fragment, $content, $file . ' 仍包含不可读或占位注释：' . $fragment);
            }
        }
    }
}
