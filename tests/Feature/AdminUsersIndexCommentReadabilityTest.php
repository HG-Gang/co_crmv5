<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/07/04
 * Time: 17:09
 */

/**
 * AdminUsersIndexCommentReadabilityTest
 *
 * 文件功能：
 * - 验证用户列表页 JS 与 Blade 文件的中文逻辑注释保持可读。
 * - 输入：控制器/模型/JS/Blade/CSS 等源码与语言包文本；输出：PHPUnit 断言结果。
 * - 明确不负责：不验证运行时业务行为与数据库交互。
 */

namespace Tests\Feature;

use Tests\TestCase;
use Tests\Feature\Concerns\ReadsAggregatedLayuiScripts;

/**
 * 后台用户列表 Blade 与 JS 中文注释可读性测试。
 *
 * 功能逻辑说明：
 * - `resources/admin/layui/users/index.blade.php` 负责后台用户列表页面结构、筛选表单和操作按钮权限标记。
 * - `public/js/apps/admin/layui/users/index.js` 负责用户列表加载、搜索参数传递、账号类型展示、认证状态展示、详情弹窗和启停状态操作。
 * - 本测试只检查静态页面/脚本注释和乱码黑名单，不连接数据库，也不调用真实用户列表或状态修改接口。
 */
class AdminUsersIndexCommentReadabilityTest extends TestCase
{
    use ReadsAggregatedLayuiScripts;

    /**
     * 用户列表 JS 必须说明数据来源、筛选参数、展示字段、详情入口和状态按钮权限来源。
     *
     * @return void
     */
    public function test_users_index_js_keeps_readable_chinese_logic_comments(): void
    {
        $script = $this->adminLayuiScript('users/index.js');

        foreach ($this->requiredJsCommentFragments() as $fragment) {
            $this->assertStringContainsString($fragment, $script, '用户列表 users/index.js 缺少中文逻辑注释：' . $fragment);
        }

        $this->assertDoesNotContainGarbledFragments($script, '用户列表 users/index.js');
    }

    /**
     * 用户列表 Blade 必须说明页面职责、接口来源、筛选参数和按钮权限边界。
     *
     * @return void
     */
    public function test_users_index_blade_keeps_readable_chinese_logic_comments(): void
    {
        $blade = file_get_contents(resource_path('admin/layui/users/index.blade.php')) ?: '';

        foreach ($this->requiredBladeCommentFragments() as $fragment) {
            $this->assertStringContainsString($fragment, $blade, '用户列表 users/index.blade.php 缺少中文逻辑注释：' . $fragment);
        }

        $this->assertDoesNotContainGarbledFragments($blade, '用户列表 users/index.blade.php');
    }

    /**
     * 必须保留的 JS 中文注释片段。
     *
     * @return array<int, string> 注释片段列表，用于覆盖用户列表参数、展示字段和权限边界。
     */
    private function requiredJsCommentFragments(): array
    {
        return [
            '用户列表',
            'user_id 表示业务用户 ID',
            'email 表示登录邮箱',
            'account_type 表示账号类型',
            'auth_status 表示认证状态',
            'detail 表示打开用户详情',
            'status 表示切换用户启停状态',
            'is_enabled 表示登录账号是否启用',
            '重新应用按钮权限',
            'permissions.slug',
        ];
    }

    /**
     * 必须保留的 Blade 中文注释片段。
     *
     * @return array<int, string> 注释片段列表，用于覆盖页面职责、接口来源和安全边界。
     */
    private function requiredBladeCommentFragments(): array
    {
        return [
            '用户管理页面',
            'admin_api_userList',
            'admin_api_changeUserStatus',
            'user_id 筛选业务用户 ID',
            'email 筛选登录邮箱',
            'account_type 区分代理和客户',
            '后端权限与数据范围校验',
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
            'å…',
            'é‡',
            'æ‰',
            'é”',
            '鍚',
            '绠',
            '閫',
            '�',
        ];
    }
}
