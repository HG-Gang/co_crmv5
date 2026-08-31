<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/06/08
 * Time: 01:34
 */

/**
 * AdminRolePermissionControllerReadabilityTest
 *
 * 文件功能：
 * - 验证角色与权限控制器保持可读中文逻辑注释。
 * - 输入：控制器/模型/JS/Blade/CSS 等源码与语言包文本；输出：PHPUnit 断言结果。
 * - 明确不负责：不验证运行时业务行为与数据库交互。
 */

namespace Tests\Feature;

use Tests\TestCase;

/**
 * 后台角色与权限控制器中文注释可读性测试。
 *
 * 功能逻辑说明：
 * - RoleController 负责维护 roles 表和 role_permissions 授权关系，是后台角色菜单、按钮、接口权限的配置入口。
 * - PermissionController 负责维护 permissions 表中的菜单、页面、按钮和接口权限字典，是前后台菜单可控的底层来源。
 * - 本测试只做源码静态约束，不连接真实数据库，用于防止权限核心文件继续保留乱码注释或缺少参数边界说明。
 */
class AdminRolePermissionControllerReadabilityTest extends TestCase
{
    /**
     * 角色与权限控制器必须说明数据表来源、请求参数含义和授权边界。
     *
     * @return void
     */
    public function test_role_and_permission_controllers_keep_readable_chinese_logic_comments(): void
    {
        $roleController = file_get_contents(app_path('Http/Controllers/Admin/RoleController.php')) ?: '';
        $permissionController = file_get_contents(app_path('Http/Controllers/Admin/PermissionController.php')) ?: '';

        foreach ($this->requiredRoleControllerFragments() as $fragment) {
            $this->assertStringContainsString($fragment, $roleController, 'RoleController 缺少中文逻辑注释：' . $fragment);
        }

        foreach ($this->requiredPermissionControllerFragments() as $fragment) {
            $this->assertStringContainsString($fragment, $permissionController, 'PermissionController 缺少中文逻辑注释：' . $fragment);
        }

        $this->assertDoesNotContainGarbledFragments($roleController . $permissionController, '角色与权限控制器');
    }

    /**
     * RoleController 必须保留的中文说明片段。
     *
     * @return array<int, string> 注释片段列表，用于覆盖角色表、授权表和请求参数边界。
     */
    private function requiredRoleControllerFragments(): array
    {
        return [
            '后台角色管理控制器',
            'roles 表保存角色基础信息',
            'role_permissions 表保存角色与 permissions.id 的授权关系',
            '$page：Layui 表格当前页码',
            '$perPage：每页条数',
            '$roleId：待授权的 roles.id',
            '$permissions：前端提交的 permissions.id 数组',
            '只允许同步同 guard_type 下的权限',
        ];
    }

    /**
     * PermissionController 必须保留的中文说明片段。
     *
     * @return array<int, string> 注释片段列表，用于覆盖权限树来源、字段含义和删除边界。
     */
    private function requiredPermissionControllerFragments(): array
    {
        return [
            '后台权限字典管理控制器',
            'permissions 表是前后台菜单、页面、按钮和接口权限的唯一配置来源',
            '$guardType：权限守卫类型',
            '$parentId：当前递归层级的父级 permissions.id',
            '$type：权限类型',
            '$apiRoute：接口命名路由',
            '存在子权限时禁止删除',
        ];
    }

    /**
     * 断言目标内容不包含常见乱码片段。
     *
     * @param string $content 待检查的源码内容。
     * @param string $label 错误提示中的文件范围说明。
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
