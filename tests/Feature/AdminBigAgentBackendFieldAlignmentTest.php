<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/07/04
 * Time: 15:35
 */

/**
 * AdminBigAgentBackendFieldAlignmentTest
 *
 * 文件功能：
 * - 验证大代理商后端源码使用 is_enabled 字段口径并保持可读中文注释。
 * - 输入：控制器/模型/JS/Blade/CSS 等源码与语言包文本；输出：PHPUnit 断言结果。
 * - 明确不负责：不验证运行时业务行为与数据库交互。
 */

namespace Tests\Feature;

use Tests\TestCase;
use Tests\Feature\Concerns\ReadsAggregatedLayuiScripts;

/**
 * 后台大代理模块字段对齐与中文注释可读性测试。
 *
 * 功能逻辑说明：
 * - `big_agents` 表使用 `is_enabled` 表示大代理账号是否启用，前台大代理登录也读取该字段。
 * - 后台 Blade、JS、控制器和模型必须统一使用 `is_enabled`，不能继续用不存在或不生效的 `status` 字段。
 * - 本测试只检查静态字段对齐、注释可读性和乱码黑名单，不连接真实数据库。
 */
class AdminBigAgentBackendFieldAlignmentTest extends TestCase
{
    use ReadsAggregatedLayuiScripts;

    /**
     * 大代理后台链路必须统一使用 is_enabled，并保留可读中文逻辑注释。
     *
     * @return void
     */
    public function test_big_agent_backend_uses_is_enabled_and_readable_comments(): void
    {
        $controller = file_get_contents(app_path('Http/Controllers/Admin/BigAgentController.php')) ?: '';
        $model = file_get_contents(app_path('Models/BigAgent.php')) ?: '';
        $blade = file_get_contents(resource_path('admin/layui/big-agents/index.blade.php')) ?: '';
        $script = $this->adminLayuiScript('big-agents/index.js');

        foreach ($this->requiredControllerFragments() as $fragment) {
            $this->assertStringContainsString($fragment, $controller, 'BigAgentController 缺少中文逻辑注释或字段处理：' . $fragment);
        }

        foreach ($this->requiredModelFragments() as $fragment) {
            $this->assertStringContainsString($fragment, $model, 'BigAgent 模型缺少中文逻辑注释：' . $fragment);
        }

        $this->assertStringContainsString('name="is_enabled"', $blade, '大代理表单必须提交 is_enabled，对齐 big_agents.is_enabled。');
        $this->assertStringNotContainsString('name="status"', $blade, '大代理表单不能继续提交 status。');
        $this->assertStringContainsString("field: 'is_enabled'", $script, '大代理表格必须展示 is_enabled 字段。');
        $this->assertStringNotContainsString("field: 'status'", $script, '大代理表格不能继续读取 status 字段。');
        $this->assertStringContainsString('data.field.is_enabled = data.field.is_enabled ? 1 : 0;', $script, '大代理保存前必须把 is_enabled 归一化为 1/0。');
        $this->assertStringContainsString("'password' => 'nullable|string|min:6'", $controller, '编辑大代理时 password 留空必须通过校验并保留原密码。');

        $combined = $controller . $model . $blade . $script;
        $this->assertDoesNotContainGarbledFragments($combined, '大代理后台链路文件');
    }

    /**
     * BigAgentController 必须保留的中文注释与字段处理片段。
     *
     * @return array<int, string> 控制器注释和字段片段列表。
     */
    private function requiredControllerFragments(): array
    {
        return [
            '大代理管理控制器',
            'big_agents',
            'admin_api_bigAgentList',
            'admin_api_createBigAgent',
            'admin_api_updateBigAgent',
            'admin_api_deleteBigAgent',
            'username 表示大代理登录名',
            'password 表示大代理登录密码',
            'is_enabled 表示大代理账号是否启用',
            'id 表示大代理主键',
            '编辑时 password 留空表示保留原密码',
            'Hash::make',
            'check.permission:admin',
            'permissions.api_route',
        ];
    }

    /**
     * BigAgent 模型必须保留的中文注释片段。
     *
     * @return array<int, string> 模型注释片段列表。
     */
    private function requiredModelFragments(): array
    {
        return [
            '大代理模型',
            'big_agents',
            'sub_agent_ids 表示大代理可管理的下级代理 ID 集合',
            'is_enabled 表示大代理账号是否启用',
            'loginLogs 表示大代理登录日志关联',
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
