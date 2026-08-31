<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/07/04
 * Time: 17:09
 */

/**
 * AdminLayoutJsCommentReadabilityTest
 *
 * 文件功能：
 * - 验证后台布局 JS 的中文逻辑注释保持可读，并禁止乱码中文注释回流。
 * - 输入：控制器/模型/JS/Blade/CSS 等源码与语言包文本；输出：PHPUnit 断言结果。
 * - 明确不负责：不验证运行时业务行为与数据库交互。
 */

namespace Tests\Feature;

use Tests\TestCase;
use Tests\Feature\Concerns\ReadsAggregatedLayuiScripts;

/**
 * 后台布局 JS 中文注释可读性测试。
 *
 * 功能逻辑说明：
 * - `public/js/apps/admin/layui/layout.js` 负责后台菜单加载、按钮权限显隐、主题切换和侧边栏交互，是所有后台 Blade 页面共用的运行时外壳。
 * - 该文件中的注释必须说明权限 slug、菜单接口缓存、前端显隐与后端 `check.permission:admin` 的边界，避免后续误把前端隐藏当成安全鉴权。
 * - 本测试只读取静态 JS 文件，不连接数据库。
 */
class AdminLayoutJsCommentReadabilityTest extends TestCase
{
    use ReadsAggregatedLayuiScripts;

    /**
     * 后台布局 JS 必须包含关键中文逻辑注释。
     *
     * @return void
     */
    public function test_admin_layout_js_keeps_readable_chinese_logic_comments(): void
    {
        $script = $this->adminLayuiScript('layout.js');

        foreach ($this->requiredCommentFragments() as $fragment) {
            $this->assertStringContainsString($fragment, $script, '后台布局 layout.js 缺少中文逻辑注释：' . $fragment);
        }
    }

    /**
     * 后台布局 JS 不允许继续出现典型中文乱码。
     *
     * @return void
     */
    public function test_admin_layout_js_does_not_contain_garbled_chinese_comments(): void
    {
        $script = $this->adminLayuiScript('layout.js');

        foreach ($this->garbledFragments() as $fragment) {
            $this->assertStringNotContainsString($fragment, $script, '后台布局 layout.js 仍包含乱码片段：' . $fragment);
        }
    }

    /**
     * 必须保留的中文注释片段。
     *
     * @return array<int, string> 注释片段列表，用于约束权限与布局外壳核心说明。
     */
    private function requiredCommentFragments(): array
    {
        return [
            '后台布局壳层的接口和跳转都从 PHP 注入的 Laravel 路由清单读取',
            '后台按钮权限控制器只负责前端显示体验',
            '真正安全边界仍是 check.permission:admin 中间件',
            'slug 对应 permissions.slug',
            '菜单接口返回后会覆盖该缓存',
            '接口权限必须继续依赖后端中间件校验',
            'Layui table 工具栏由模板异步渲染',
        ];
    }

    /**
     * 常见乱码片段黑名单。
     *
     * @return array<int, string> 乱码片段列表，覆盖当前 layout.js 中已出现过的历史编码错读字符。
     */
    private function garbledFragments(): array
    {
        return [
            '鍚',
            '甯',
            '澹',
            '璺',
            '鎸',
            '閽',
            '鏉',
            '閰',
            '缂',
            '娓',
            '�',
        ];
    }
}
