<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/07/04
 * Time: 17:09
 */

/**
 * AdminCommissionsCommentReadabilityTest
 *
 * 文件功能：
 * - 验证佣金模块 JS 与 Blade 文件的中文逻辑注释保持可读。
 * - 输入：控制器/模型/JS/Blade/CSS 等源码与语言包文本；输出：PHPUnit 断言结果。
 * - 明确不负责：不验证运行时业务行为与数据库交互。
 */

namespace Tests\Feature;

use Tests\TestCase;
use Tests\Feature\Concerns\ReadsAggregatedLayuiScripts;

/**
 * 后台返佣结算 Blade 与 JS 中文注释可读性测试。
 *
 * 功能逻辑说明：
 * - `resources/admin/layui/commissions/index.blade.php` 负责后台返佣结算页面结构、筛选表单和结算按钮权限标记。
 * - `public/js/apps/admin/layui/commissions/index.js` 负责返佣列表加载、代理/结算状态筛选、单条返佣结算和按钮权限刷新。
 * - 本测试只检查静态页面/脚本注释、字段对齐和乱码黑名单，不连接数据库，也不调用真实返佣接口。
 */
class AdminCommissionsCommentReadabilityTest extends TestCase
{
    use ReadsAggregatedLayuiScripts;

    /**
     * 返佣结算 JS 必须说明列表来源、筛选字段、结算金额、数据范围和按钮权限来源。
     *
     * @return void
     */
    public function test_commissions_js_keeps_readable_chinese_logic_comments(): void
    {
        $script = $this->adminLayuiScript('commissions/index.js');

        foreach ($this->requiredJsCommentFragments() as $fragment) {
            $this->assertStringContainsString($fragment, $script, '返佣结算 commissions/index.js 缺少中文逻辑注释：' . $fragment);
        }

        $this->assertStringContainsString("field: 'settle_status'", $script, '返佣表格必须读取 commission_records.settle_status。');
        $this->assertDoesNotContainGarbledFragments($script, '返佣结算 commissions/index.js');
    }

    /**
     * 返佣结算 Blade 必须说明页面职责、接口来源、筛选字段和权限边界。
     *
     * @return void
     */
    public function test_commissions_blade_keeps_readable_chinese_logic_comments(): void
    {
        $blade = file_get_contents(resource_path('admin/layui/commissions/index.blade.php')) ?: '';

        foreach ($this->requiredBladeCommentFragments() as $fragment) {
            $this->assertStringContainsString($fragment, $blade, '返佣结算 commissions/index.blade.php 缺少中文逻辑注释：' . $fragment);
        }

        $this->assertStringContainsString('name="settle_status"', $blade, '返佣筛选表单必须提交 settle_status，与 CommissionController@index 保持一致。');
        $this->assertDoesNotContainGarbledFragments($blade, '返佣结算 commissions/index.blade.php');
    }

    /**
     * 必须保留的 JS 中文注释片段。
     *
     * @return array<int, string> 注释片段列表，用于覆盖返佣结算字段、数据范围和权限边界。
     */
    private function requiredJsCommentFragments(): array
    {
        return [
            '返佣结算列表',
            'commission_records',
            'agent_id 表示返佣归属代理',
            'user_id 表示产生返佣的客户',
            'amount 表示返佣金额',
            'settle_status 表示结算状态',
            '1=待结算',
            '2=已结算',
            'AdminDataScopeService',
            '/api/admin/commissions',
            '/api/admin/commissions/{id}/settle',
            'id 表示返佣记录主键',
            'settle 表示结算返佣记录',
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
            '返佣结算管理页面',
            'admin_api_commissionList',
            'admin_api_commissionSettle',
            'agent_id 筛选返佣归属代理',
            'settle_status 筛选结算状态',
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
