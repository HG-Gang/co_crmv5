<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/07/05
 * Time: 08:38
 */

/**
 * PaymentChannelControllerCommentReadabilityTest
 *
 * 文件功能：
 * - 验证后台 PaymentChannelController 对通道字段、兼容字段映射、启用状态和权限边界保持可读中文注释。
 * - 输入：控制器/模型/JS/Blade/CSS 等源码与语言包文本；输出：PHPUnit 断言结果。
 * - 明确不负责：不验证运行时业务行为与数据库交互。
 */

namespace Tests\Feature;

use Tests\TestCase;

/**
 * @runClassInSeparateProcess
 * @preserveGlobalState disabled
 *
 * 后台支付通道控制器中文注释可读性测试。
 *
 * 功能说明：
 * - 该测试只读取 PaymentChannelController 源码，不直接访问数据库。
 * - 目标是确保支付通道字段、兼容字段映射、启用状态和权限边界都有中文逻辑说明。
 */
class PaymentChannelControllerCommentReadabilityTest extends TestCase
{
    protected function tearDown(): void
    {
        parent::tearDown();

        gc_collect_cycles();
    }

    public function test_payment_channel_controller_keeps_chinese_logic_comments_for_channel_fields(): void
    {
        $source = file_get_contents(app_path('Http/Controllers/Admin/PaymentChannelController.php')) ?: '';

        foreach ([
            '后台支付通道管理控制器',
            '数据来源为 payment_channels 表',
            'page 表示当前页码',
            'per_page 表示每页数量',
            'limit 表示 Layui 表格每页数量',
            'id 表示 payment_channels.id',
            'name 表示支付通道名称',
            'channel_name 表示旧页面提交的通道名称',
            'channel_name 映射到 payment_channels.name',
            'channel_code 表示支付通道编码',
            'exchange_rate 表示支付通道汇率',
            'is_enabled 表示通道是否启用',
            'config 表示支付通道扩展配置',
            'toggleEnable 用于切换支付通道启用状态',
        ] as $expectedComment) {
            $this->assertStringContainsString($expectedComment, $source, 'PaymentChannelController 缺少中文注释：' . $expectedComment);
        }

        $this->assertStringNotContainsString(
            'Payment Channel Management Controller',
            $source,
            'PaymentChannelController 不应保留旧英文标题注释。'
        );
    }
}
