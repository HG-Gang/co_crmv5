<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/06/09
 * Time: 02:26
 */

/**
 * AdminRolePermissionModelReadabilityTest
 *
 * 文件功能：
 * - 验证角色与权限模型保持可读中文逻辑注释。
 * - 输入：控制器/模型/JS/Blade/CSS 等源码与语言包文本；输出：PHPUnit 断言结果。
 * - 明确不负责：不验证运行时业务行为与数据库交互。
 */

namespace Tests\Feature;

use Tests\TestCase;

/**
 * 后台角色与权限模型中文注释可读性测试。
 *
 * 功能逻辑说明：
 * - `Role` 模型负责角色基础信息、角色权限授权关系和角色数据范围关联。
 * - `Permission` 模型负责后台菜单、页面、按钮和接口权限字典。
 * - 本测试只读取源码，不连接数据库，用于防止核心权限模型继续保留乱码注释或缺少参数边界说明。
 */
class AdminRolePermissionModelReadabilityTest extends TestCase
{
    /**
     * 角色与权限模型必须说明字段含义、关联关系和授权来源。
     *
     * 参数与变量含义：
     * - $roleModel：Role 模型源码内容，用于检查角色字段、授权来源和数据范围关联说明。
     * - $permissionModel：Permission 模型源码内容，用于检查权限字段、权限类型和角色关联说明。
     * - $fragment：单个必须存在的中文说明片段。
     *
     * @return void
     */
    public function test_role_and_permission_models_keep_readable_chinese_logic_comments(): void
    {
        $roleModel = file_get_contents(app_path('Models/Role.php')) ?: '';
        $permissionModel = file_get_contents(app_path('Models/Permission.php')) ?: '';

        foreach ($this->requiredRoleFragments() as $fragment) {
            $this->assertStringContainsString($fragment, $roleModel, 'Role 模型缺少中文逻辑注释：' . $fragment);
        }

        foreach ($this->requiredPermissionFragments() as $fragment) {
            $this->assertStringContainsString($fragment, $permissionModel, 'Permission 模型缺少中文逻辑注释：' . $fragment);
        }

        $this->assertDoesNotContainGarbledFragments($roleModel . $permissionModel, '角色与权限模型');
    }

    /**
     * Role 模型必须保留的中文说明片段。
     *
     * @return array<int, string> 注释片段列表，用于覆盖角色字段、权限来源、数据范围和超级权限边界。
     */
    private function requiredRoleFragments(): array
    {
        return [
            '角色模型',
            'roles 表保存后台管理员、前台代理商和普通客户可绑定的角色',
            'guard_type 用于区分 admin 和 front',
            'role_permissions 是唯一生效的权限授权来源',
            'roles.permissions JSON 只保留兼容字段',
            'role_data_scopes.role_id 通过 dataScope() 关联',
            'name 表示角色稳定名称',
            'description 表示角色用途说明',
            'status 表示角色启停状态',
            'permissionsRelation',
            '$slug 表示 permissions.slug',
            '传入 * 只用于判断超级权限',
            '关联后台管理员',
        ];
    }

    /**
     * Permission 模型必须保留的中文说明片段。
     *
     * @return array<int, string> 注释片段列表，用于覆盖权限字段、作用域和角色授权关系。
     */
    private function requiredPermissionFragments(): array
    {
        return [
            '权限模型',
            'permissions 表保存前后台菜单、页面、按钮和接口权限字典',
            'slug 表示稳定权限字符串',
            'api_route 表示 Laravel 命名路由',
            'guard_type 用于区分 admin 和 front',
            'type 表示权限类型',
            'role_permissions.permission_id 对应 permissions.id',
            '关联拥有该权限的角色集合',
            '限定后台权限',
            '限定按钮或接口动作权限',
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
     * 常见 UTF-8/GBK 错误解码后的乱码片段黑名单。
     *
     * @return array<int, string> 乱码片段列表。
     */
    private function garbledFragments(): array
    {
        return [
            '鏉冮檺',
            '瑙掕壊',
            '鍔熻兘',
            '閫昏緫',
            '鍙傛暟',
            '绠＄悊',
            '鑿滃崟',
            '瀹堝崼',
            '缁戝畾',
            '鍏宠仈',
        ];
    }
}
