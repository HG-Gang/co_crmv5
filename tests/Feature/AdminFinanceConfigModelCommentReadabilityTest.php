<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/06/09
 * Time: 02:56
 */

/**
 * AdminFinanceConfigModelCommentReadabilityTest
 *
 * 文件功能：
 * - 验证返佣和资金配置相关模型保持可读中文注释，禁止旧英文占位或乱码注释回流。
 * - 输入：控制器/模型/JS/Blade/CSS 等源码与语言包文本；输出：PHPUnit 断言结果。
 * - 明确不负责：不验证运行时业务行为与数据库交互。
 */

namespace Tests\Feature;

use Tests\TestCase;

/**
 * 后台返佣、交易组配置和支付通道模型中文注释可读性测试。
 *
 * 功能逻辑说明：
 * - 本测试约束返佣记录、交易组配置、支付通道三个资金配置相关模型的中文注释质量。
 * - 这些模型影响后台返佣结算、用户交易组、入金支付通道和资金展示，字段含义必须与真实数据表一致。
 * - 测试只读取源码文件，不写入真实资金或配置数据，也不改变返佣、组配置和支付通道业务行为。
 */
class AdminFinanceConfigModelCommentReadabilityTest extends TestCase
{
    /**
     * 返佣和资金配置模型必须包含真实表职责、关键字段和查询作用域说明。
     *
     * @return void
     */
    public function test_finance_config_models_contain_readable_chinese_logic_comments(): void
    {
        // $expectations 表示每个模型必须包含的中文说明片段；键名为模型路径，值为真实字段和业务含义。
        $expectations = [
            app_path('Models/CommissionRecord.php') => [
                '佣金记录模型',
                'commission_records 表保存代理返佣结算和人工调整记录',
                'agent_id 表示获得返佣的代理业务用户 ID',
                'commission_amount 表示本次应返佣金额',
                'settle_status 表示返佣结算状态',
                'manual_reason 表示人工调整原因',
            ],
            app_path('Models/GroupConfig.php') => [
                '组配置模型',
                'group_configs 表保存代理组和客户交易组配置',
                'pair_id 表示成对关联的组配置 ID',
                'category 表示组类型',
                'has_commission 表示该组是否参与返佣',
                '$query 表示组配置查询构造器',
            ],
            app_path('Models/PaymentChannel.php') => [
                '支付通道模型',
                'payment_channels 表保存后台可用支付通道配置',
                'channel_code 表示支付通道唯一编码',
                'exchange_rate 表示通道入金汇率',
                'config 表示通道扩展配置',
                '$query 表示支付通道查询构造器',
            ],
        ];

        foreach ($expectations as $file => $requiredFragments) {
            // $content 表示当前模型源码，用于检查注释是否覆盖真实表职责和关键字段含义。
            $content = file_get_contents($file);

            foreach ($requiredFragments as $fragment) {
                $this->assertStringContainsString($fragment, $content, $file . ' 缺少中文说明：' . $fragment);
            }
        }
    }

    /**
     * 返佣和资金配置模型不允许保留旧英文占位或乱码注释。
     *
     * @return void
     */
    public function test_finance_config_models_do_not_contain_mojibake_or_english_placeholders(): void
    {
        // $files 表示本轮直接维护的模型文件集合，用于把失败范围限制在当前修复边界内。
        $files = [
            app_path('Models/CommissionRecord.php'),
            app_path('Models/GroupConfig.php'),
            app_path('Models/PaymentChannel.php'),
        ];

        // $forbiddenFragments 表示旧注释中常见的英文占位和 UTF-8/GBK 错解片段。
        $forbiddenFragments = [
            'Table Name',
            'Relation:',
            'Scope:',
            'Attribute Casting',
            'Records details of commissions',
            'Stores configuration parameters',
            'Manages available payment channels',
            '浣ｉ噾',
            '鏁版嵁',
            '鍏宠仈',
            '鏀粯',
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
