<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/06/09
 * Time: 02:30
 */

/**
 * AdminAuthModelCommentReadabilityTest
 *
 * 文件功能：
 * - 验证后台认证相关模型文件保持可读中文注释，禁止历史乱码或英文占位片段回流。
 * - 输入：控制器/模型/JS/Blade/CSS 等源码与语言包文本；输出：PHPUnit 断言结果。
 * - 明确不负责：不验证运行时业务行为与数据库交互。
 */

namespace Tests\Feature;

use Tests\TestCase;

/**
 * 后台管理员认证相关模型中文注释可读性测试。
 *
 * 功能逻辑说明：
 * - `Admin` 模型负责后台管理员认证主体、角色绑定、权限 slug 获取和登录日志关联。
 * - `AdminRole` 是旧代码兼容的管理员角色模型别名，底层仍读取 `roles` 表。
 * - `AdminLoginLog` 记录管理员登录 IP、地区和浏览器信息，属于后台审计链路。
 * - 本测试只读取源码，不连接数据库，用于约束后台认证模型不再保留乱码或英文占位注释。
 */
class AdminAuthModelCommentReadabilityTest extends TestCase
{
    /**
     * 后台管理员认证相关模型必须包含可读中文职责、字段和参数说明。
     *
     * 参数与变量含义：
     * - $adminModel：`Admin` 模型源码内容。
     * - $adminRoleModel：`AdminRole` 模型源码内容。
     * - $adminLoginLogModel：`AdminLoginLog` 模型源码内容。
     * - $combined：三个模型源码拼接结果，用于统一检查乱码片段。
     * - $fragment：必须存在的中文说明片段。
     *
     * @return void
     */
    public function test_admin_auth_models_contain_readable_chinese_logic_comments(): void
    {
        $adminModel = file_get_contents(app_path('Models/Admin.php')) ?: '';
        $adminRoleModel = file_get_contents(app_path('Models/AdminRole.php')) ?: '';
        $adminLoginLogModel = file_get_contents(app_path('Models/AdminLoginLog.php')) ?: '';
        $combined = $adminModel . $adminRoleModel . $adminLoginLogModel;

        foreach ($this->requiredAdminFragments() as $fragment) {
            $this->assertStringContainsString($fragment, $adminModel, 'Admin 模型缺少中文说明：' . $fragment);
        }

        foreach ($this->requiredAdminRoleFragments() as $fragment) {
            $this->assertStringContainsString($fragment, $adminRoleModel, 'AdminRole 模型缺少中文说明：' . $fragment);
        }

        foreach ($this->requiredAdminLoginLogFragments() as $fragment) {
            $this->assertStringContainsString($fragment, $adminLoginLogModel, 'AdminLoginLog 模型缺少中文说明：' . $fragment);
        }

        foreach ($this->forbiddenFragments() as $fragment) {
            $this->assertStringNotContainsString($fragment, $combined, '后台认证模型仍存在乱码或英文占位片段：' . $fragment);
        }
    }

    /**
     * Admin 模型必须保留的中文说明片段。
     *
     * @return array<int, string> 注释片段列表。
     */
    private function requiredAdminFragments(): array
    {
        return [
            '管理员模型',
            'admins 表保存后台管理员登录账号、角色绑定和登录状态',
            'role_id 表示绑定的 roles.id',
            'jwt_token_id 表示当前有效 JWT 的唯一编号',
            '$slug 表示 permissions.slug',
            'getAllPermissions',
            '权限唯一来源是 role_permissions 中间表',
            '关联登录日志',
            'isActive',
        ];
    }

    /**
     * AdminRole 模型必须保留的中文说明片段。
     *
     * @return array<int, string> 注释片段列表。
     */
    private function requiredAdminRoleFragments(): array
    {
        return [
            '管理员角色兼容模型',
            '底层数据表仍为 roles',
            'guard_type 表示角色守卫类型',
            'permissions 表示历史 JSON 兼容字段',
            'status 表示角色启停状态',
        ];
    }

    /**
     * AdminLoginLog 模型必须保留的中文说明片段。
     *
     * @return array<int, string> 注释片段列表。
     */
    private function requiredAdminLoginLogFragments(): array
    {
        return [
            '管理员登录日志模型',
            'admin_login_logs 表记录后台管理员登录审计信息',
            'admin_id 表示登录管理员 admins.id',
            'login_ip 表示登录 IP',
            'user_agent 表示登录浏览器或客户端标识',
            '关联管理员',
        ];
    }

    /**
     * 不允许继续出现的乱码或英文占位片段。
     *
     * @return array<int, string> 禁止片段列表。
     */
    private function forbiddenFragments(): array
    {
        return [
            '绠＄悊',
            '鍚庡彴',
            '瑙掕壊',
            '鍔熻兘',
            '閫昏緫',
            '鍙傛暟',
            '鍏宠仈',
            'Table Name',
            'Relation:',
            'Responsible for',
            'Records admin login activities',
            'The attributes that are mass assignable',
        ];
    }
}
