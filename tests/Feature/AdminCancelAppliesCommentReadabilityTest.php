<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/07/04
 * Time: 17:09
 */

/**
 * AdminCancelAppliesCommentReadabilityTest
 *
 * 文件功能：
 * - 验证销户申请模块 JS 与 Blade 文件的中文逻辑注释保持可读。
 * - 输入：控制器/模型/JS/Blade/CSS 等源码与语言包文本；输出：PHPUnit 断言结果。
 * - 明确不负责：不验证运行时业务行为与数据库交互。
 */

namespace Tests\Feature;

use Tests\TestCase;
use Tests\Feature\Concerns\ReadsAggregatedLayuiScripts;

/**
 * 后台注销申请 Blade 与 JS 中文注释可读性测试。
 *
 * 功能逻辑说明：
 * - `resources/admin/layui/cancel-applies/index.blade.php` 负责后台注销申请页面结构、状态筛选和审核按钮权限标记。
 * - `public/js/apps/admin/layui/cancel-applies/index.js` 负责注销申请列表加载、状态筛选、审核通过、审核拒绝和按钮权限刷新。
 * - 本测试只检查静态页面/脚本注释和乱码黑名单，不连接数据库，也不调用真实注销申请接口。
 */
class AdminCancelAppliesCommentReadabilityTest extends TestCase
{
    use ReadsAggregatedLayuiScripts;

    /**
     * 注销申请 JS 必须说明列表来源、状态枚举、审核动作和按钮权限来源。
     *
     * @return void
     */
    public function test_cancel_applies_js_keeps_readable_chinese_logic_comments(): void
    {
        $script = $this->adminLayuiScript('cancel-applies/index.js');

        foreach ($this->requiredJsCommentFragments() as $fragment) {
            $this->assertStringContainsString($fragment, $script, '注销申请 cancel-applies/index.js 缺少中文逻辑注释：' . $fragment);
        }

        $this->assertDoesNotContainGarbledFragments($script, '注销申请 cancel-applies/index.js');
    }

    /**
     * 注销申请 Blade 必须说明页面职责、接口来源、状态筛选和权限边界。
     *
     * @return void
     */
    public function test_cancel_applies_blade_keeps_readable_chinese_logic_comments(): void
    {
        $blade = file_get_contents(resource_path('admin/layui/cancel-applies/index.blade.php')) ?: '';

        foreach ($this->requiredBladeCommentFragments() as $fragment) {
            $this->assertStringContainsString($fragment, $blade, '注销申请 cancel-applies/index.blade.php 缺少中文逻辑注释：' . $fragment);
        }

        $this->assertDoesNotContainGarbledFragments($blade, '注销申请 cancel-applies/index.blade.php');
    }

    /**
     * 必须保留的 JS 中文注释片段。
     *
     * @return array<int, string> 注释片段列表，用于覆盖注销申请列表、状态和审核权限边界。
     */
    private function requiredJsCommentFragments(): array
    {
        return [
            '注销申请列表',
            'cancel_applies',
            'status 表示注销申请状态',
            '0=待处理',
            '1=通过',
            '-1=拒绝',
            '/api/admin/cancelApplyList',
            '/api/admin/cancelApplyApprove/{id}',
            '/api/admin/cancelApplyReject/{id}',
            'id 表示注销申请主键',
            'approve 表示通过注销申请',
            'reject 表示拒绝注销申请',
            '重新应用按钮权限',
            'permissions.slug',
        ];
    }

    /**
     * 必须保留的 Blade 中文注释片段。
     *
     * @return array<int, string> 注释片段列表，用于覆盖页面结构、接口来源和安全边界。
     */
    private function requiredBladeCommentFragments(): array
    {
        return [
            '注销申请管理页面',
            'admin_api_cancelApplyList',
            'admin_api_cancelApplyApprove',
            'admin_api_cancelApplyReject',
            'status 为空表示全部申请',
            'data-permission 来自 permissions.slug',
            '后端 check.permission:admin',
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
            'å…',
            'é‡',
            'æ‰',
            'é”',
            '鍚',
            '绠',
            '閫',
            '�',
        ];
    }
}
