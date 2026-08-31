<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/17
 * Time: 19:57
 */

/**
 * AdminWithdrawalsCommentReadabilityTest
 *
 * 文件功能：
 * - 验证出金模块 JS 与 Blade 文件的中文逻辑注释保持可读。
 * - 输入：控制器/模型/JS/Blade/CSS 等源码与语言包文本；输出：PHPUnit 断言结果。
 * - 明确不负责：不验证运行时业务行为与数据库交互。
 */

namespace Tests\Feature;

use Tests\TestCase;
use Tests\Feature\Concerns\ReadsAggregatedLayuiScripts;

/**
 * 后台出金审核 Blade 与 JS 中文注释可读性测试。
 *
 * 功能逻辑说明：
 * - `resources/admin/layui/withdrawals/index.blade.php` 负责出金审核页面结构、筛选表单和操作按钮权限标记。
 * - `public/js/apps/admin/layui/withdrawals/index.js` 负责出金审核列表加载、筛选、状态流转和按钮权限刷新。
 * - 本测试只检查静态页面/脚本注释和乱码黑名单，不连接数据库，也不调用真实出金审核接口。
 */
class AdminWithdrawalsCommentReadabilityTest extends TestCase
{
    use ReadsAggregatedLayuiScripts;

    /**
     * 出金审核 JS 必须说明列表职责、搜索参数、金额字段、状态流转动作和按钮权限来源。
     *
     * @return void
     */
    public function test_withdrawals_js_keeps_readable_chinese_logic_comments(): void
    {
        $script = $this->adminLayuiScript('withdrawals/index.js');

        foreach ($this->requiredJsCommentFragments() as $fragment) {
            $this->assertStringContainsString($fragment, $script, '出金审核 withdrawals/index.js 缺少中文逻辑注释：' . $fragment);
        }

        $this->assertDoesNotContainGarbledFragments($script, '出金审核 withdrawals/index.js');
    }

    /**
     * 出金审核 Blade 必须说明页面边界和后端权限校验入口。
     *
     * @return void
     */
    public function test_withdrawals_blade_keeps_readable_chinese_logic_comments(): void
    {
        $blade = file_get_contents(resource_path('admin/layui/withdrawals/index.blade.php')) ?: '';

        foreach ($this->requiredBladeCommentFragments() as $fragment) {
            $this->assertStringContainsString($fragment, $blade, '出金审核 withdrawals/index.blade.php 缺少中文逻辑注释：' . $fragment);
        }

        $this->assertDoesNotContainGarbledFragments($blade, '出金审核 withdrawals/index.blade.php');
    }

    /**
     * 必须保留的 JS 中文注释片段。
     *
     * @return array<int, string> 注释片段列表，用于覆盖出金审核参数、状态动作和权限边界。
     */
    private function requiredJsCommentFragments(): array
    {
        return [
            '出金审核列表',
            'user_id 表示出金申请人',
            'status 表示出金处理状态',
            'apply_amount 表示出金申请金额',
            'process 表示标记出金处理中',
            'complete 表示完成出金记录',
            'reject 表示拒绝出金记录',
            'id 表示出金申请主键',
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
            '出金管理页面',
            'admin_api_withdrawList',
            'admin_api_withdrawProcess',
            'admin_api_withdrawComplete',
            'admin_api_withdrawReject',
            '后端权限与数据范围校验',
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
