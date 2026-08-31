<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/07/04
 * Time: 17:09
 */

/**
 * AdminChannelsJsCommentReadabilityTest
 *
 * 文件功能：
 * - 验证支付通道模块 Layui JS 的中文逻辑注释保持可读，并禁止乱码片段回流。
 * - 输入：控制器/模型/JS/Blade/CSS 等源码与语言包文本；输出：PHPUnit 断言结果。
 * - 明确不负责：不验证运行时业务行为与数据库交互。
 */

namespace Tests\Feature;

use Tests\TestCase;
use Tests\Feature\Concerns\ReadsAggregatedLayuiScripts;

/**
 * 后台支付通道 JS 中文注释可读性测试。
 *
 * 功能逻辑说明：
 * - `public/js/apps/admin/layui/channels/index.js` 是后台支付通道配置页面的业务脚本。
 * - 支付通道配置会影响前台入金、出金和支付渠道展示，因此字段含义、状态筛选和扩展配置必须有可读中文注释。
 * - 本测试只检查静态 JS 注释和乱码黑名单，不连接数据库，也不调用真实支付通道接口。
 */
class AdminChannelsJsCommentReadabilityTest extends TestCase
{
    use ReadsAggregatedLayuiScripts;

    /**
     * 支付通道脚本必须说明状态筛选、通道字段、扩展配置和按钮权限刷新。
     *
     * @return void
     */
    public function test_channels_js_keeps_readable_chinese_logic_comments(): void
    {
        $script = $this->adminLayuiScript('channels/index.js');

        foreach ($this->requiredCommentFragments() as $fragment) {
            $this->assertStringContainsString($fragment, $script, '支付通道 channels/index.js 缺少中文逻辑注释：' . $fragment);
        }
    }

    /**
     * 支付通道脚本不能继续保留历史乱码注释。
     *
     * @return void
     */
    public function test_channels_js_does_not_contain_garbled_comment_fragments(): void
    {
        $script = $this->adminLayuiScript('channels/index.js');

        foreach ($this->garbledFragments() as $fragment) {
            $this->assertStringNotContainsString($fragment, $script, '支付通道 channels/index.js 仍包含乱码片段：' . $fragment);
        }
    }

    /**
     * 必须保留的中文注释片段。
     *
     * @return array<int, string> 注释片段列表，用于覆盖支付通道配置字段和权限边界。
     */
    private function requiredCommentFragments(): array
    {
        return [
            'status 表示支付通道启用状态筛选',
            'channel_code 表示支付通道编码',
            'exchange_rate 表示该通道使用的汇率',
            'is_enabled 表示通道是否启用',
            'config 表示通道扩展配置',
            'id 为空表示新增支付通道',
            'normalizeConfig 将通道扩展配置转换为 textarea 文本',
            '重新应用按钮权限',
            'permissions.slug',
        ];
    }

    /**
     * 常见乱码片段黑名单。
     *
     * @return array<int, string> 乱码片段列表，用于发现历史编码错乱的中文注释。
     */
    private function garbledFragments(): array
    {
        return [
            'æ”',
            'é€',
            'æ‰',
            'é‡',
            '鍚',
            '绠',
            '閫',
            '�',
        ];
    }
}
