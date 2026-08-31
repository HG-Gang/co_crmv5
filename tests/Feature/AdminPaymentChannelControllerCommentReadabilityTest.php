<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/06/08
 * Time: 01:11
 */

/**
 * AdminPaymentChannelControllerCommentReadabilityTest
 *
 * 文件功能：
 * - 验证支付通道控制器源码保持可读中文逻辑注释。
 * - 输入：控制器/模型/JS/Blade/CSS 等源码与语言包文本；输出：PHPUnit 断言结果。
 * - 明确不负责：不验证运行时业务行为与数据库交互。
 */

namespace Tests\Feature;

use Tests\TestCase;

/**
 * 后台支付通道控制器中文注释可读性测试。
 *
 * 功能逻辑说明：
 * - `App\Http\Controllers\Admin\PaymentChannelController` 负责后台支付通道列表、新增、编辑、删除和启用状态切换。
 * - 支付通道会影响前台入金流程，因此控制器必须清楚说明字段含义、兼容字段映射、路由参数和权限边界。
 * - 本测试只检查源代码注释和接口名可读性，不连接真实数据库，也不执行真实支付通道接口。
 */
class AdminPaymentChannelControllerCommentReadabilityTest extends TestCase
{
    /**
     * PaymentChannelController 必须保留覆盖支付通道 CRUD 的中文逻辑注释和参数说明。
     *
     * @return void
     */
    public function test_payment_channel_controller_keeps_readable_chinese_logic_comments(): void
    {
        $source = file_get_contents(app_path('Http/Controllers/Admin/PaymentChannelController.php')) ?: '';

        foreach ($this->requiredCommentFragments() as $fragment) {
            $this->assertStringContainsString($fragment, $source, 'PaymentChannelController 缺少中文逻辑注释：' . $fragment);
        }

        $this->assertStringContainsString("'channel_code' => 'required|string|max:50|unique:payment_channels'", $source, '新增支付通道必须校验 channel_code 唯一。');
        $this->assertStringContainsString("'channel_code' => 'required|string|max:50|unique:payment_channels,channel_code,' . " . '$id', $source, '编辑支付通道必须排除当前 id 后校验 channel_code 唯一。');
        $this->assertDoesNotContainGarbledFragments($source, 'PaymentChannelController.php');
    }

    /**
     * 必须保留的中文注释片段。
     *
     * @return array<int, string> 注释片段列表，用于覆盖 payment_channels 字段、接口和权限边界。
     */
    private function requiredCommentFragments(): array
    {
        return [
            '支付通道管理控制器',
            'payment_channels',
            'admin_api_channelList',
            'admin_api_createChannel',
            'admin_api_updateChannel',
            'admin_api_deleteChannel',
            'page 表示当前页码',
            'per_page 表示每页数量',
            'limit 表示 Layui 表格每页数量',
            'name 表示支付通道名称',
            'channel_name 表示旧页面提交的通道名称',
            'channel_code 表示支付通道编码',
            'exchange_rate 表示支付通道汇率',
            'is_enabled 表示通道是否启用',
            'sort 表示后台排序值',
            'config 表示支付通道扩展配置',
            'id 表示支付通道主键',
            'check.permission:admin',
            'permissions.api_route',
        ];
    }

    /**
     * 断言目标文本不包含常见乱码片段。
     *
     * @param string $content 被检查的文件内容。
     * @param string $label 错误消息中的文件标签，用于快速定位失败文件。
     * @return void
     */
    private function assertDoesNotContainGarbledFragments(string $content, string $label): void
    {
        foreach ($this->garbledFragments() as $fragment) {
            $this->assertStringNotContainsString($fragment, $content, $label . ' 仍包含乱码片段：' . $fragment);
        }
    }

    /**
     * 常见乱码片段黑名单。
     *
     * @return array<int, string> 乱码片段列表，用于发现历史编码错乱的中文注释。
     */
    private function garbledFragments(): array
    {
        return [
            '�',
            'å…',
            'é‡',
            'æ‰',
            'é”',
            '鍚',
            '绠',
            '閫',
            '鐢',
            '鏉',
            '鑿',
            '娉',
            '杩',
        ];
    }
}
