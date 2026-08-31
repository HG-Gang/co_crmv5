<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/06/08
 * Time: 22:27
 */

/**
 * DepositControllerCommentReadabilityTest
 *
 * 文件功能：
 * - 验证 DepositController 无旧英文标题，入金列表、详情、审核通过/驳回、状态码和数据范围鉴权均有中文逻辑说明。
 * - 输入：控制器/模型/JS/Blade/CSS 等源码与语言包文本；输出：PHPUnit 断言结果。
 * - 明确不负责：不验证运行时业务行为与数据库交互。
 */

namespace Tests\Feature;

use Tests\TestCase;

/**
 * 后台入金控制器中文注释可读性测试。
 *
 * 功能说明：
 * - 该测试只读取 DepositController 源码，不访问真实数据库。
 * - 目标是清理旧英文标题，并约束入金列表、详情、审核通过、审核驳回、状态码和数据范围鉴权均有中文逻辑说明。
 */
class DepositControllerCommentReadabilityTest extends TestCase
{
    public function test_deposit_controller_keeps_chinese_logic_comments_without_legacy_english_titles(): void
    {
        $source = file_get_contents(app_path('Http/Controllers/Admin/DepositController.php')) ?: '';

        foreach ([
            '后台入金管理控制器',
            '数据来源为 deposit_records 表',
            'status 表示入金审核状态',
            'user_id 表示入金所属业务用户ID',
            'local_order_no 表示本地入金订单号',
            'id 表示 deposit_records.id',
            'approve 用于审核通过入金记录',
            'status=02 表示入金已审核通过',
            'payment_time 表示审核通过时间',
            'reject 用于驳回入金记录',
            'status=09 表示入金审核驳回或失败',
            'reason 表示驳回原因',
            'denyDepositAccessIfNeeded 用于按当前管理员数据范围判断是否允许访问指定入金记录',
        ] as $expectedComment) {
            $this->assertStringContainsString($expectedComment, $source, 'DepositController 缺少中文注释：' . $expectedComment);
        }

        foreach ([
            'Deposit Management Controller',
            'List all deposit records',
            'Get deposit detail',
            'Approve deposit',
            'Further logic to update user balance can be added here',
            'Reject deposit',
            'Failed/Rejected',
        ] as $legacyEnglishTitle) {
            $this->assertStringNotContainsString($legacyEnglishTitle, $source, 'DepositController 不应保留旧英文标题注释：' . $legacyEnglishTitle);
        }
    }
}
