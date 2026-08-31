<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/07/04
 * Time: 17:09
 */

/**
 * AdminProfileEditCommentReadabilityTest
 *
 * 文件功能：
 * - 验证后台个人资料编辑 JS 与 Blade 文件的中文逻辑注释保持可读。
 * - 输入：控制器/模型/JS/Blade/CSS 等源码与语言包文本；输出：PHPUnit 断言结果。
 * - 明确不负责：不验证运行时业务行为与数据库交互。
 */

namespace Tests\Feature;

use Tests\TestCase;
use Tests\Feature\Concerns\ReadsAggregatedLayuiScripts;

/**
 * 后台个人资料编辑 Blade 与 JS 中文注释可读性测试。
 *
 * 功能逻辑说明：
 * - `resources/admin/layui/profile/edit.blade.php` 负责后台当前管理员个人资料编辑表单。
 * - `public/js/apps/admin/layui/profile/edit.js` 负责读取当前管理员资料、回填表单并提交邮箱和手机号更新。
 * - 本测试只检查静态页面/脚本注释和乱码黑名单，不连接数据库，也不调用真实个人资料接口。
 */
class AdminProfileEditCommentReadabilityTest extends TestCase
{
    use ReadsAggregatedLayuiScripts;

    /**
     * 个人资料编辑 JS 必须说明读取接口、更新接口和表单字段含义。
     *
     * @return void
     */
    public function test_profile_edit_js_keeps_readable_chinese_logic_comments(): void
    {
        $script = $this->adminLayuiScript('profile/edit.js');

        foreach ($this->requiredJsCommentFragments() as $fragment) {
            $this->assertStringContainsString($fragment, $script, '个人资料 edit.js 缺少中文逻辑注释：' . $fragment);
        }

        $this->assertDoesNotContainGarbledFragments($script, '个人资料 edit.js');
    }

    /**
     * 个人资料编辑 Blade 必须说明页面职责、字段边界和后端接口来源。
     *
     * @return void
     */
    public function test_profile_edit_blade_keeps_readable_chinese_logic_comments(): void
    {
        $blade = file_get_contents(resource_path('admin/layui/profile/edit.blade.php')) ?: '';

        foreach ($this->requiredBladeCommentFragments() as $fragment) {
            $this->assertStringContainsString($fragment, $blade, '个人资料 edit.blade.php 缺少中文逻辑注释：' . $fragment);
        }

        $this->assertDoesNotContainGarbledFragments($blade, '个人资料 edit.blade.php');
    }

    /**
     * 必须保留的 JS 中文注释片段。
     *
     * @return array<int, string> 注释片段列表，用于覆盖资料读取、字段含义和保存边界。
     */
    private function requiredJsCommentFragments(): array
    {
        return [
            '当前管理员资料',
            '/api/admin/profileInfo',
            '/api/admin/updateProfile',
            'username 表示管理员登录名',
            'email 表示管理员邮箱',
            'mobile 表示管理员手机号',
            '只允许更新 email 和 mobile',
            '当前登录管理员',
        ];
    }

    /**
     * 必须保留的 Blade 中文注释片段。
     *
     * @return array<int, string> 注释片段列表，用于覆盖页面结构、字段来源和权限边界。
     */
    private function requiredBladeCommentFragments(): array
    {
        return [
            '后台个人资料编辑页面',
            'admin_api_profileInfo',
            'admin_api_updateProfile',
            'username 只读',
            'email 可更新',
            'mobile 可更新',
            '当前登录管理员',
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
