<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/07/04
 * Time: 15:35
 */

/**
 * AdminRolePermissionAssignmentUiTest
 *
 * 文件功能：
 * - 验证角色页包含权限指派 UI 与对应 API 调用声明。
 * - 输入：路由、控制器、Blade/JS、迁移等项目源码文本；输出：PHPUnit 断言结果。
 * - 明确不负责：不覆盖 HTTP 运行时业务流与 MT4 真实网关同步（由集成与功能测试锁定）。
 */

namespace Tests\Feature;

use Tests\TestCase;
use Tests\Feature\Concerns\ReadsAggregatedLayuiScripts;

/**
 * 后台角色权限分配 UI 覆盖测试。
 *
 * 功能逻辑说明：
 * - 后台多管理员角色的菜单、按钮和接口权限必须从 permissions、role_permissions 数据表配置得到。
 * - 角色管理页必须提供“分配权限”入口，否则管理员只能创建角色，不能维护不同角色的菜单权限。
 * - 本测试只读取 Blade/JS 源码，不连接真实数据库，用于约束前后端不分离场景下的 Blade + JS 授权入口。
 */
class AdminRolePermissionAssignmentUiTest extends TestCase
{
    use ReadsAggregatedLayuiScripts;

    /**
     * 角色管理页面必须提供权限树授权入口，并调用真实后端授权接口。
     *
     * @return void
     */
    public function test_role_page_contains_permission_assignment_ui_and_api_calls(): void
    {
        $blade = file_get_contents(resource_path('admin/layui/roles/index.blade.php')) ?: '';
        $script = $this->adminLayuiScript('roles/index.js');

        foreach ($this->requiredBladeFragments() as $fragment) {
            $this->assertStringContainsString($fragment, $blade, '角色 Blade 缺少权限分配入口：' . $fragment);
        }

        foreach ($this->requiredScriptFragments() as $fragment) {
            $this->assertStringContainsString($fragment, $script, '角色 JS 缺少权限分配逻辑：' . $fragment);
        }

        $this->assertDoesNotContainGarbledFragments($blade . $script, '角色权限分配页面文件');
    }

    /**
     * Blade 必须存在的授权入口片段。
     *
     * @return array<int, string> 片段列表，用于确认页面拥有授权按钮和权限树容器。
     */
    private function requiredBladeFragments(): array
    {
        return [
            'admin_role_assign_permissions',
            'assignPermissions',
            'permissionTreeBox',
            'saveRolePermissions',
            'rolePermissionForm',
            '权限树来自 permissions 表',
        ];
    }

    /**
     * JS 必须存在的授权逻辑片段。
     *
     * @return array<int, string> 片段列表，用于确认脚本调用权限树和角色授权接口。
     */
    private function requiredScriptFragments(): array
    {
        return [
            '/api/admin/permissions/tree',
            '/api/admin/assignPermissions',
            'tree.render',
            'showCheckbox: true',
            'getChecked',
            'role_id',
            'permissions',
            '只同步当前角色 guard_type 下的权限',
            'role_permissions 表',
        ];
    }

    /**
     * 断言目标内容不包含常见乱码片段。
     *
     * @param string $content 待检查内容。
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
