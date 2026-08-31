<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/07/04
 * Time: 17:09
 */

/**
 * AdminBigAgentsCommentReadabilityTest
 *
 * 文件功能：
 * - 验证大代理商模块 JS 与 Blade 文件的中文逻辑注释保持可读。
 * - 输入：控制器/模型/JS/Blade/CSS 等源码与语言包文本；输出：PHPUnit 断言结果。
 * - 明确不负责：不验证运行时业务行为与数据库交互。
 */

namespace Tests\Feature;

use Tests\TestCase;
use Tests\Feature\Concerns\ReadsAggregatedLayuiScripts;

/**
 * 后台大代理管理 Blade 与 JS 中文注释可读性测试。
 *
 * 功能逻辑说明：
 * - `resources/admin/layui/big-agents/index.blade.php` 负责后台大代理管理页面结构、表格和 CRUD 操作按钮权限标记。
 * - `public/js/apps/admin/layui/big-agents/index.js` 负责大代理列表加载、新增、编辑、删除和表格重载后的按钮权限刷新。
 * - 本测试只检查静态页面、脚本注释、字段对齐和乱码黑名单，不连接数据库，也不调用真实大代理接口。
 */
class AdminBigAgentsCommentReadabilityTest extends TestCase
{
    use ReadsAggregatedLayuiScripts;

    /**
     * 大代理 JS 必须说明列表来源、CRUD 接口、字段含义和按钮权限来源。
     *
     * @return void
     */
    public function test_big_agents_js_keeps_readable_chinese_logic_comments(): void
    {
        $script = $this->adminLayuiScript('big-agents/index.js');

        foreach ($this->requiredJsCommentFragments() as $fragment) {
            $this->assertStringContainsString($fragment, $script, '大代理 big-agents/index.js 缺少中文逻辑注释：' . $fragment);
        }

        $this->assertStringContainsString("field: 'is_enabled'", $script, '大代理 JS 必须读取 big_agents.is_enabled。');
        $this->assertStringNotContainsString("field: 'status'", $script, '大代理 JS 不能继续读取无效 status 字段。');
        $this->assertDoesNotContainGarbledFragments($script, '大代理 big-agents/index.js');
    }

    /**
     * 大代理 Blade 必须说明页面职责、接口来源、表单字段和权限边界。
     *
     * @return void
     */
    public function test_big_agents_blade_keeps_readable_chinese_logic_comments(): void
    {
        $blade = file_get_contents(resource_path('admin/layui/big-agents/index.blade.php')) ?: '';

        foreach ($this->requiredBladeCommentFragments() as $fragment) {
            $this->assertStringContainsString($fragment, $blade, '大代理 big-agents/index.blade.php 缺少中文逻辑注释：' . $fragment);
        }

        $this->assertStringContainsString('name="is_enabled"', $blade, '大代理 Blade 必须提交 is_enabled。');
        $this->assertStringNotContainsString('name="status"', $blade, '大代理 Blade 不能继续提交 status。');
        $this->assertDoesNotContainGarbledFragments($blade, '大代理 big-agents/index.blade.php');
    }

    /**
     * 必须保留的 JS 中文注释片段。
     *
     * @return array<int, string> 注释片段列表，用于覆盖大代理 CRUD 字段和权限边界。
     */
    private function requiredJsCommentFragments(): array
    {
        return [
            '大代理列表',
            'big_agents',
            '/api/admin/bigAgentList',
            '/api/admin/createBigAgent',
            '/api/admin/updateBigAgent/{id}',
            '/api/admin/deleteBigAgent/{id}',
            'id 为空表示新增大代理',
            'username 表示大代理登录名',
            'password 表示大代理登录密码',
            'is_enabled 表示大代理账号是否启用',
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
            '大代理管理页面',
            'admin_api_bigAgentList',
            'admin_api_createBigAgent',
            'admin_api_updateBigAgent',
            'admin_api_deleteBigAgent',
            'data-permission 对应 permissions.slug',
            '后端 check.permission:admin',
            'id 为空表示新增',
            'password 留空表示编辑时保留原密码',
            'is_enabled 对应 big_agents.is_enabled',
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
            '澶',
            '鐞',
            '鏂',
            '鍒',
            '琛',
            '瀛',
            '鍚',
            '绠',
            '閫',
            '銆',
            '锛',
            '€',
        ];
    }
}
