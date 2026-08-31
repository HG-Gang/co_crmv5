<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/06/08
 * Time: 02:12
 */

/**
 * AdminUserControllerCommentReadabilityTest
 *
 * 文件功能：
 * - 验证后台用户控制器保持可读中文参数注释。
 * - 输入：控制器/模型/JS/Blade/CSS 等源码与语言包文本；输出：PHPUnit 断言结果。
 * - 明确不负责：不验证运行时业务行为与数据库交互。
 */

namespace Tests\Feature;

use Tests\TestCase;

/**
 * 后台用户控制器中文逻辑注释可读性测试。
 *
 * 功能逻辑说明：
 * - AdminUserController 是后台查看、审核、更新和启停业务用户的核心入口。
 * - 用户要求所有模块文件和参数必须有详细中文注释，所以控制器不能只保留英文标题或笼统的 Request 注释。
 * - 本测试只读取源码，不连接数据库，用于约束接口参数含义、权限边界和数据范围边界必须写在控制器注释中。
 */
class AdminUserControllerCommentReadabilityTest extends TestCase
{
    /**
     * 后台用户控制器必须说明列表、审核、详情、更新和启停接口的参数含义。
     *
     * @return void
     */
    public function test_admin_user_controller_keeps_readable_chinese_parameter_comments(): void
    {
        $source = file_get_contents(app_path('Http/Controllers/Admin/AdminUserController.php')) ?: '';

        foreach ($this->requiredFragments() as $fragment) {
            $this->assertStringContainsString($fragment, $source, 'AdminUserController 缺少中文参数或逻辑注释：' . $fragment);
        }

        $this->assertDoesNotContainGarbledFragments($source, 'AdminUserController');
    }

    /**
     * 控制器必须保留的中文说明片段。
     *
     * @return array<int, string> 注释片段列表，用于覆盖参数含义、表来源、数据范围和权限边界。
     */
    private function requiredFragments(): array
    {
        return [
            '后台用户管理控制器',
            'userList() 参数说明',
            'page 表示当前页码',
            'limit 表示每页数量',
            'user_id 表示业务用户 ID',
            'email 表示 user_logins.email 登录邮箱',
            'account_type 表示账号类型',
            'reviewAuth() 参数说明',
            'status 表示审核结果',
            'reason 表示审核拒绝原因',
            'userDetail() 参数说明',
            'updateUser() 参数说明',
            'changeUserStatus() 参数说明',
            'is_enabled 表示 user_logins.is_enabled',
            'AdminDataScopeService',
            'role_data_scopes',
            'admin_agent_bindings',
            'permissions.api_route',
        ];
    }

    /**
     * 断言目标源码不包含常见乱码片段。
     *
     * @param string $content 被检查的源码内容。
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
            '锟',
            '绠',
            '鍛',
            '鏂',
            '閫',
            '銆',
        ];
    }
}
