<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/06/08
 * Time: 01:58
 */

/**
 * AdminCheckPermissionMiddlewareReadabilityTest
 *
 * 文件功能：
 * - 验证 check.permission 权限中间件源码保持可读中文逻辑注释。
 * - 输入：控制器/模型/JS/Blade/CSS 等源码与语言包文本；输出：PHPUnit 断言结果。
 * - 明确不负责：不验证运行时业务行为与数据库交互。
 */

namespace Tests\Feature;

use Tests\TestCase;

/**
 * 后台接口权限中间件中文注释可读性测试。
 *
 * 功能逻辑说明：
 * - CheckPermission 是后台接口权限强制校验入口，负责把当前路由名映射到 permissions.api_route。
 * - JWT 与 SSO 只确认“是谁”和“token 是否有效”，真正“能不能访问接口”必须由该中间件完成。
 * - 本测试只读取源码，不连接真实数据库，用于约束中间件参数含义、白名单边界和多语言响应说明。
 */
class AdminCheckPermissionMiddlewareReadabilityTest extends TestCase
{
    /**
     * 权限中间件必须说明鉴权顺序、参数含义和白名单边界。
     *
     * @return void
     */
    public function test_check_permission_middleware_keeps_readable_chinese_logic_comments(): void
    {
        $source = file_get_contents(app_path('Http/Middleware/CheckPermission.php')) ?: '';

        foreach ($this->requiredFragments() as $fragment) {
            $this->assertStringContainsString($fragment, $source, 'CheckPermission 缺少中文逻辑注释：' . $fragment);
        }

        $this->assertStringContainsString("__('response.permission_denied')", $source, '权限不足响应必须使用后端多语言语言包。');
        $this->assertStringContainsString("__('response.auth_failed')", $source, '认证失败响应必须使用后端多语言语言包。');
        $this->assertDoesNotContainGarbledFragments($source, 'CheckPermission 中间件');
    }

    /**
     * 必须保留的中文说明片段。
     *
     * @return array<int, string> 注释片段列表，用于覆盖后台接口权限强制鉴权边界。
     */
    private function requiredFragments(): array
    {
        return [
            '后台接口权限检查中间件',
            '$guardType：权限守卫类型',
            '$routeName：当前 Laravel 命名路由',
            'permissions.api_route',
            'permissions.guard_type',
            'role_permissions',
            '超级管理员只跳过权限表校验',
            '白名单接口只要求登录和 SSO 有效',
            '前端隐藏按钮不是安全边界',
        ];
    }

    /**
     * 断言目标内容不包含常见乱码片段。
     *
     * @param string $content 待检查源码内容。
     * @param string $label 错误提示标签。
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
     * @return array<int, string> 乱码片段列表，用于发现 UTF-8/GBK 错解后的不可读注释。
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
