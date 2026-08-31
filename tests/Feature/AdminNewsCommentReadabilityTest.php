<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/07/04
 * Time: 17:09
 */

/**
 * AdminNewsCommentReadabilityTest
 *
 * 文件功能：
 * - 验证新闻公告模块 JS 与 Blade 文件的中文逻辑注释保持可读。
 * - 输入：控制器/模型/JS/Blade/CSS 等源码与语言包文本；输出：PHPUnit 断言结果。
 * - 明确不负责：不验证运行时业务行为与数据库交互。
 */

namespace Tests\Feature;

use Tests\TestCase;
use Tests\Feature\Concerns\ReadsAggregatedLayuiScripts;

/**
 * 后台新闻公告 Blade 与 JS 中文注释可读性测试。
 *
 * 功能逻辑说明：
 * - `resources/admin/layui/news/index.blade.php` 负责后台新闻公告页面结构、标题搜索、CRUD 弹窗和按钮权限标记。
 * - `public/js/apps/admin/layui/news/index.js` 负责新闻公告列表加载、刷新、搜索、新增、编辑、删除和按钮权限刷新。
 * - 本测试只检查静态页面、脚本注释、字段对齐和乱码黑名单，不连接真实数据库，也不调用真实新闻接口。
 */
class AdminNewsCommentReadabilityTest extends TestCase
{
    use ReadsAggregatedLayuiScripts;

    /**
     * 新闻公告 JS 必须说明列表来源、字段含义、CRUD 接口、发布状态和权限边界。
     *
     * @return void
     */
    public function test_news_js_keeps_readable_chinese_logic_comments(): void
    {
        $script = $this->adminLayuiScript('news/index.js');

        foreach ($this->requiredJsCommentFragments() as $fragment) {
            $this->assertStringContainsString($fragment, $script, '新闻公告 news/index.js 缺少中文逻辑注释：' . $fragment);
        }

        $this->assertStringContainsString("url: '/api/admin/news'", $script, '新闻公告列表必须读取资源化新闻公告接口。');
        $this->assertStringContainsString("'/api/admin/updateNews/' + encodeURIComponent(id)", $script, '新闻公告编辑必须通过路由参数 id 调用更新接口。');
        $this->assertDoesNotContainGarbledFragments($script, '新闻公告 news/index.js');
    }

    /**
     * 新闻公告 Blade 必须说明页面职责、接口来源、搜索参数、表单字段和按钮权限来源。
     *
     * @return void
     */
    public function test_news_blade_keeps_readable_chinese_logic_comments(): void
    {
        $blade = file_get_contents(resource_path('admin/layui/news/index.blade.php')) ?: '';

        foreach ($this->requiredBladeCommentFragments() as $fragment) {
            $this->assertStringContainsString($fragment, $blade, '新闻公告 news/index.blade.php 缺少中文逻辑注释：' . $fragment);
        }

        $this->assertStringContainsString('name="title"', $blade, '新闻公告搜索框必须提交 title，和 NewsController@index 的入参保持一致。');
        $this->assertStringNotContainsString('name="keyword"', $blade, '新闻公告搜索框不能继续提交后端不读取的 keyword。');
        $this->assertStringContainsString('data-permission="admin_news_create"', $blade, '新增新闻公告按钮必须绑定 permissions.slug。');
        $this->assertDoesNotContainGarbledFragments($blade, '新闻公告 news/index.blade.php');
    }

    /**
     * 必须保留的 JS 中文注释片段。
     *
     * @return array<int, string> 注释片段列表，用于覆盖 news 字段、接口和权限边界。
     */
    private function requiredJsCommentFragments(): array
    {
        return [
            '新闻公告列表',
            'news',
            'title 表示新闻标题',
            'content 表示新闻正文',
            'is_published 表示发布状态',
            '1=已发布',
            '0=未发布',
            '/api/admin/news',
            '/api/admin/createNews',
            '/api/admin/updateNews/{id}',
            '/api/admin/deleteNews/{id}',
            'id 表示新闻公告主键',
            '重新应用按钮权限',
            'permissions.slug',
        ];
    }

    /**
     * 必须保留的 Blade 中文注释片段。
     *
     * @return array<int, string> 注释片段列表，用于覆盖页面职责、接口来源、字段含义和安全边界。
     */
    private function requiredBladeCommentFragments(): array
    {
        return [
            '新闻公告管理页面',
            'admin_api_newsList',
            'admin_api_createNews',
            'admin_api_updateNews',
            'admin_api_deleteNews',
            'title 搜索参数与 NewsController@index 保持一致',
            'title/content 为发布内容主体',
            'is_published 控制前台是否可见',
            'data-permission 来自 permissions.slug',
            '后端 check.permission:admin',
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
            '�',
            'å…',
            'é‡',
            'æ‰',
            'é”',
            '鍚',
            '绠',
            '閫',
            '鐢',
            '鏉',
            '鑿',
            '娉',
            '杩',
        ];
    }
}
