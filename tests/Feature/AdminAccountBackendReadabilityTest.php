<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/07/04
 * Time: 15:35
 */

/**
 * AdminAccountBackendReadabilityTest
 *
 * 文件功能：
 * - 验证后台管理员账号后端（控制器/模型）源码保持可读中文注释，且密码字段遵守隐藏与脱敏边界。
 * - 输入：控制器/模型/JS/Blade/CSS 等源码与语言包文本；输出：PHPUnit 断言结果。
 * - 明确不负责：不验证运行时业务行为与数据库交互。
 */

namespace Tests\Feature;

use Tests\TestCase;
use Tests\Feature\Concerns\ReadsAggregatedLayuiScripts;

/**
 * 后台管理员账号模块字段边界与中文注释可读性测试。
 *
 * 功能逻辑说明：
 * - `AdminController` 负责后台管理员账号列表、新增、编辑、删除和重置密码。
 * - `/admin/admins` Blade 与 JS 负责管理员账号页面、弹窗字段和 CRUD 调用。
 * - 管理员账号属于高敏资源，必须明确参数含义、密码留空边界、权限接口和乱码黑名单。
 */
class AdminAccountBackendReadabilityTest extends TestCase
{
    use ReadsAggregatedLayuiScripts;

    /**
     * 后台管理员账号链路必须保留可读中文注释，并正确处理编辑时密码留空。
     *
     * @return void
     */
    public function test_admin_account_backend_keeps_readable_comments_and_password_boundary(): void
    {
        $controller = file_get_contents(app_path('Http/Controllers/Admin/AdminController.php')) ?: '';
        $blade = file_get_contents(resource_path('admin/layui/admins/index.blade.php')) ?: '';
        $script = $this->adminLayuiScript('admins/index.js');

        foreach ($this->requiredControllerFragments() as $fragment) {
            $this->assertStringContainsString($fragment, $controller, 'AdminController 缺少中文逻辑注释或字段处理：' . $fragment);
        }

        foreach ($this->requiredBladeFragments() as $fragment) {
            $this->assertStringContainsString($fragment, $blade, 'admins/index.blade.php 缺少中文逻辑注释：' . $fragment);
        }

        foreach ($this->requiredJsFragments() as $fragment) {
            $this->assertStringContainsString($fragment, $script, 'admins/index.js 缺少中文逻辑注释：' . $fragment);
        }

        $this->assertStringContainsString("'password' => 'nullable|string|min:6'", $controller, '编辑管理员时 password 留空必须通过校验并保留原密码。');
        $this->assertStringContainsString('delete data.field.password;', $script, '编辑管理员且密码留空时 JS 必须删除 password 字段。');
        $this->assertDoesNotContainGarbledFragments($controller . $blade . $script, '后台管理员账号链路文件');
    }

    /**
     * AdminController 必须保留的中文注释和字段处理片段。
     *
     * @return array<int, string> 控制器注释片段列表。
     */
    private function requiredControllerFragments(): array
    {
        return [
            '管理员账号管理控制器',
            'admins',
            'admin_api_adminList',
            'admin_api_createAdmin',
            'admin_api_updateAdmin',
            'admin_api_deleteAdmin',
            'username 表示管理员登录名',
            'email 表示管理员邮箱',
            'password 表示管理员登录密码',
            '编辑时 password 留空表示保留原密码',
            'id 表示管理员主键',
            'Hash::make',
            'check.permission:admin',
            'permissions.api_route',
        ];
    }

    /**
     * Blade 必须保留的中文注释片段。
     *
     * @return array<int, string> Blade 注释片段列表。
     */
    private function requiredBladeFragments(): array
    {
        return [
            '管理员账号管理页面',
            'admin_api_adminList',
            'admin_api_createAdmin',
            'admin_api_updateAdmin',
            'admin_api_deleteAdmin',
            'password 留空表示编辑时保留原密码',
            'data-permission 对应 permissions.slug',
            '后端 check.permission:admin',
        ];
    }

    /**
     * JS 必须保留的中文注释片段。
     *
     * @return array<int, string> JS 注释片段列表。
     */
    private function requiredJsFragments(): array
    {
        return [
            '管理员账号列表',
            'admins',
            '/api/admin/admins',
            '/api/admin/createAdmin',
            '/api/admin/updateAdmin/{id}',
            '/api/admin/deleteAdmin/{id}',
            'username 表示管理员登录名',
            'email 表示管理员邮箱',
            'password 留空表示编辑时保留原密码',
            '重新应用按钮权限',
            'permissions.slug',
        ];
    }

    /**
     * 断言目标文本不包含常见乱码片段。
     *
     * @param string $content 被检查的文件内容。
     * @param string $label 错误消息中的文件标签。
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
     * @return array<int, string> 乱码片段列表。
     */
    private function garbledFragments(): array
    {
        return [
            '�',
            '绠',
            '鍛',
            '鏂',
            '鍒',
            '琛',
            '瀛',
            '鍚',
            '閫',
            '銆',
            '锛',
            '€',
        ];
    }
}
