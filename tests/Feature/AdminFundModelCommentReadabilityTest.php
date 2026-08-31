<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/06/09
 * Time: 02:43
 */

/**
 * AdminFundModelCommentReadabilityTest
 *
 * 文件功能：
 * - 验证资金相关模型保持可读中文注释，禁止历史乱码与英文占位注释回流。
 * - 输入：控制器/模型/JS/Blade/CSS 等源码与语言包文本；输出：PHPUnit 断言结果。
 * - 明确不负责：不验证运行时业务行为与数据库交互。
 */

namespace Tests\Feature;

use Tests\TestCase;

/**
 * 后台资金模型中文注释可读性测试。
 *
 * 功能逻辑说明：
 * - 本测试只约束资金相关模型的源码注释质量，不触发真实入金、出金或批量导入业务。
 * - 资金模型连接后台审核、前台用户资金记录和批量导入记录，字段含义必须用中文写清楚，避免后续权限和数据范围开发误读。
 * - 每个断言都读取真实项目文件，确保维护者修改模型时同步保留可读中文逻辑说明。
 */
class AdminFundModelCommentReadabilityTest extends TestCase
{
    /**
     * 资金相关模型必须说明真实数据表职责、关键字段含义和用户关联逻辑。
     *
     * @return void
     */
    public function test_fund_models_contain_readable_chinese_logic_comments(): void
    {
        // $expectations 表示每个资金模型必须包含的中文说明片段；键名为模型路径，值为该模型应解释清楚的业务字段和功能边界。
        $expectations = [
            app_path('Models/DepositRecord.php') => [
                '入金记录模型',
                'deposit_records 表保存前台用户入金申请和后台审核结果',
                'user_id 表示入金所属业务用户 ID',
                'local_order_no 表示本地入金订单号',
                'status 表示入金审核状态',
            ],
            app_path('Models/WithdrawRecord.php') => [
                '出金记录模型',
                'withdraw_records 表保存前台用户出金申请和后台处理结果',
                'user_id 表示出金所属业务用户 ID',
                'apply_amount 表示出金申请金额',
                'reject_reason 表示拒绝原因',
            ],
            app_path('Models/DepositImport.php') => [
                '批量入金导入模型',
                'deposit_imports 表保存后台批量入金导入记录',
                'user_id 表示导入记录所属业务用户 ID',
                'batch_no 表示批次号',
                'is_synced 表示后续资金系统同步状态',
            ],
            app_path('Models/WithdrawImport.php') => [
                '批量出金导入模型',
                'withdraw_imports 表保存后台批量出金导入记录',
                'user_id 表示导入记录所属业务用户 ID',
                'amount 表示本条导入记录的出金金额',
                'is_synced 表示后续出金处理或资金系统同步状态',
            ],
        ];

        foreach ($expectations as $file => $requiredFragments) {
            // $content 表示当前被检查的模型源码内容，用于确认注释仍然是可读中文而不是历史编码残留。
            $content = file_get_contents($file);

            foreach ($requiredFragments as $fragment) {
                $this->assertStringContainsString($fragment, $content, $file . ' 缺少中文说明：' . $fragment);
            }
        }
    }

    /**
     * 资金相关模型不允许继续保留历史乱码和英文占位注释。
     *
     * @return void
     */
    public function test_fund_models_do_not_contain_mojibake_or_english_placeholder_comments(): void
    {
        // $files 表示本轮直接维护的资金模型文件，范围保持收敛，便于定位失败来源。
        $files = [
            app_path('Models/DepositRecord.php'),
            app_path('Models/WithdrawRecord.php'),
            app_path('Models/DepositImport.php'),
            app_path('Models/WithdrawImport.php'),
        ];

        // $forbiddenFragments 表示历史 UTF-8/GBK 错解后的乱码片段和旧英文占位注释，命中即说明注释尚未清理干净。
        $forbiddenFragments = [
            '鍏呭€',
            '鍑洪噾',
            '鏁版嵁',
            '鍏宠仈',
            'Table Name',
            'Relation:',
            'Maintains user deposit transaction history',
            'Records the withdrawal transaction details',
        ];

        foreach ($files as $file) {
            // $content 表示模型源码内容，用于逐项排查不可读注释片段。
            $content = file_get_contents($file);

            foreach ($forbiddenFragments as $fragment) {
                $this->assertStringNotContainsString($fragment, $content, $file . ' 仍包含不可读或占位注释：' . $fragment);
            }
        }
    }
}
