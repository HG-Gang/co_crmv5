<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/07/04
 * Time: 17:09
 */

/**
 * AdminUsersDetailCommentReadabilityTest
 *
 * 文件功能：
 * - 验证用户详情页 JS 与 Blade 文件的中文逻辑注释保持可读。
 * - 输入：控制器/模型/JS/Blade/CSS 等源码与语言包文本；输出：PHPUnit 断言结果。
 * - 明确不负责：不验证运行时业务行为与数据库交互。
 */

namespace Tests\Feature;

use Tests\TestCase;
use Tests\Feature\Concerns\ReadsAggregatedLayuiScripts;

/**
 * 后台用户详情 Blade 与 JS 中文注释可读性测试。
 *
 * 功能逻辑说明：
 * - `resources/admin/layui/users/detail.blade.php` 负责后台用户详情编辑页面结构和表单字段。
 * - `public/js/apps/admin/layui/users/detail.js` 负责读取用户详情、回填表单、保存基础资料和同步登录启停状态。
 * - 本测试只检查静态页面/脚本注释和乱码黑名单，不连接数据库，也不调用真实用户详情或保存接口。
 */
class AdminUsersDetailCommentReadabilityTest extends TestCase
{
    use ReadsAggregatedLayuiScripts;

    /**
     * 用户详情 JS 必须说明读取、回填、保存和状态同步的参数边界。
     *
     * @return void
     */
    public function test_users_detail_js_keeps_readable_chinese_logic_comments(): void
    {
        $script = $this->adminLayuiScript('users/detail.js');

        foreach ($this->requiredJsCommentFragments() as $fragment) {
            $this->assertStringContainsString($fragment, $script, '用户详情 users/detail.js 缺少中文逻辑注释：' . $fragment);
        }

        $this->assertDoesNotContainGarbledFragments($script, '用户详情 users/detail.js');
    }

    /**
     * 用户详情 Blade 必须说明页面职责、隐藏主键、表单字段和后端校验边界。
     *
     * @return void
     */
    public function test_users_detail_blade_keeps_readable_chinese_logic_comments(): void
    {
        $blade = file_get_contents(resource_path('admin/layui/users/detail.blade.php')) ?: '';

        foreach ($this->requiredBladeCommentFragments() as $fragment) {
            $this->assertStringContainsString($fragment, $blade, '用户详情 users/detail.blade.php 缺少中文逻辑注释：' . $fragment);
        }

        $this->assertDoesNotContainGarbledFragments($blade, '用户详情 users/detail.blade.php');
    }

    /**
     * 必须保留的 JS 中文注释片段。
     *
     * @return array<int, string> 注释片段列表，用于覆盖详情读取、表单保存和状态同步边界。
     */
    private function requiredJsCommentFragments(): array
    {
        return [
            '用户详情',
            'user_id 表示业务用户 ID',
            'user_infos',
            'user_logins',
            'user_name 表示用户姓名',
            'phone 表示用户手机号',
            'status 表示页面选择的启停状态',
            'is_enabled 表示登录账号是否启用',
            '/api/admin/users/{user_id}',
            'PATCH /api/admin/users/{user_id}',
            'PATCH /api/admin/users/{user_id}/status',
        ];
    }

    /**
     * 必须保留的 Blade 中文注释片段。
     *
     * @return array<int, string> 注释片段列表，用于覆盖页面结构、字段来源和安全边界。
     */
    private function requiredBladeCommentFragments(): array
    {
        return [
            '用户详情页面',
            'user_id 隐藏字段',
            'admin_api_userDetail',
            'admin_api_updateUser',
            'admin_api_changeUserStatus',
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
