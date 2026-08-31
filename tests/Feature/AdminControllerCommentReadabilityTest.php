<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/06/08
 * Time: 22:13
 */

/**
 * AdminControllerCommentReadabilityTest
 *
 * 文件功能：
 * - 验证 AdminController 源码对管理员账号字段、角色绑定和密码边界保持可读中文说明。
 * - 输入：控制器/模型/JS/Blade/CSS 等源码与语言包文本；输出：PHPUnit 断言结果。
 * - 明确不负责：不验证运行时业务行为与数据库交互。
 */

namespace Tests\Feature;

use Tests\TestCase;

/**
 * 后台管理员账号控制器中文注释可读性测试。
 *
 * 功能说明：
 * - 该测试读取 AdminController 源码，约束管理员账号字段、角色绑定和密码边界的中文说明。
 * - 重点保证后续维护人员能直接看懂 admins 表、roles 表和页面参数之间的对应关系。
 */
class AdminControllerCommentReadabilityTest extends TestCase
{
    public function test_admin_controller_keeps_chinese_logic_comments_for_account_and_role_fields(): void
    {
        $source = file_get_contents(app_path('Http/Controllers/Admin/AdminController.php')) ?: '';

        foreach ([
            '后台管理员账号管理控制器',
            '数据来源为 admins 表',
            'id 表示 admins.id',
            'username 表示管理员登录名',
            'email 表示管理员邮箱',
            'password 表示管理员登录密码',
            'password 留空表示编辑时保留原密码',
            'role_id 表示绑定的后台角色',
            'roles 表示可选角色 ID 数组',
            'roles.id',
            'resetPassword 用于重置管理员登录密码',
            'admin_api_deleteAdmin',
        ] as $expectedComment) {
            $this->assertStringContainsString($expectedComment, $source, 'AdminController 缺少中文注释：' . $expectedComment);
        }

        $this->assertStringNotContainsString(
            'Admin User Management Controller',
            $source,
            'AdminController 不应保留旧英文标题注释。'
        );
    }
}
