<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/07/04
 * Time: 17:09
 */

/**
 * AdminBlacklistCommentReadabilityTest
 *
 * 文件功能：
 * - 验证黑名单模块 JS 与 Blade 文件的中文逻辑注释保持可读。
 * - 输入：控制器/模型/JS/Blade/CSS 等源码与语言包文本；输出：PHPUnit 断言结果。
 * - 明确不负责：不验证运行时业务行为与数据库交互。
 */

namespace Tests\Feature;

use Tests\TestCase;
use Tests\Feature\Concerns\ReadsAggregatedLayuiScripts;

/**
 * 后台黑名单 Blade 与 JS 中文注释可读性测试。
 *
 * 功能逻辑说明：
 * - `resources/admin/layui/blacklist/index.blade.php` 负责后台黑名单页面结构、搜索表单和 CRUD 操作按钮权限标记。
 * - `public/js/apps/admin/layui/blacklist/index.js` 负责黑名单列表加载、关键词搜索、新增、编辑、删除和按钮权限刷新。
 * - 本测试只检查静态页面/脚本注释和乱码黑名单，不连接数据库，也不调用真实黑名单接口。
 */
class AdminBlacklistCommentReadabilityTest extends TestCase
{
    use ReadsAggregatedLayuiScripts;

    /**
     * 黑名单 JS 必须说明列表来源、关键词搜索范围、字段含义、CRUD 接口和按钮权限来源。
     *
     * @return void
     */
    public function test_blacklist_js_keeps_readable_chinese_logic_comments(): void
    {
        $script = $this->adminLayuiScript('blacklist/index.js');

        foreach ($this->requiredJsCommentFragments() as $fragment) {
            $this->assertStringContainsString($fragment, $script, '黑名单 blacklist/index.js 缺少中文逻辑注释：' . $fragment);
        }

        $this->assertDoesNotContainGarbledFragments($script, '黑名单 blacklist/index.js');
    }

    /**
     * 黑名单 Blade 必须说明页面职责、接口来源、表单字段和权限边界。
     *
     * @return void
     */
    public function test_blacklist_blade_keeps_readable_chinese_logic_comments(): void
    {
        $blade = file_get_contents(resource_path('admin/layui/blacklist/index.blade.php')) ?: '';

        foreach ($this->requiredBladeCommentFragments() as $fragment) {
            $this->assertStringContainsString($fragment, $blade, '黑名单 blacklist/index.blade.php 缺少中文逻辑注释：' . $fragment);
        }

        $this->assertDoesNotContainGarbledFragments($blade, '黑名单 blacklist/index.blade.php');
    }

    /**
     * 必须保留的 JS 中文注释片段。
     *
     * @return array<int, string> 注释片段列表，用于覆盖黑名单 CRUD 字段和权限边界。
     */
    private function requiredJsCommentFragments(): array
    {
        return [
            '黑名单列表',
            'blacklists',
            'keyword 表示统一搜索关键字',
            'name 表示黑名单姓名',
            'id_card 表示证件号码',
            'email 表示邮箱',
            'phone 表示手机号',
            'remark 表示备注',
            '/api/admin/blacklistList',
            '/api/admin/createBlacklist',
            '/api/admin/updateBlacklist/{id}',
            '/api/admin/deleteBlacklist/{id}',
            'id 为空表示新增黑名单',
            '重新应用按钮权限',
            'permissions.slug',
        ];
    }

    /**
     * 必须保留的 Blade 中文注释片段。
     *
     * @return array<int, string> 注释片段列表，用于覆盖页面结构、接口来源和安全边界。
     */
    private function requiredBladeCommentFragments(): array
    {
        return [
            '黑名单管理页面',
            'admin_api_blacklistList',
            'admin_api_createBlacklist',
            'admin_api_updateBlacklist',
            'admin_api_deleteBlacklist',
            'keyword 匹配姓名',
            'data-permission 来自 permissions.slug',
            '后端 check.permission:admin',
            'id 为空时创建记录',
            '字段名与 BlacklistController 入参保持一致',
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
