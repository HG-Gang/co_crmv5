<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/06/07
 * Time: 20:14
 */

/**
 * AdminLayoutUiModernizationTest
 *
 * 文件功能：
 * - 验证后台 Blade 全局布局现代化：统一外壳入口与 CSS 设计变量、Layui 组件覆盖与常见乱码风险约束。
 * - 输入：控制器/模型/JS/Blade/CSS 等源码与语言包文本；输出：PHPUnit 断言结果。
 * - 明确不负责：不验证运行时业务行为与数据库交互。
 */

namespace Tests\Feature;

use Tests\TestCase;

/**
 * 后台 Blade 全局 UI 现代化回归测试。
 *
 * 功能逻辑说明：
 * - 后台所有页面都通过 `resources/admin/layui/layouts/app.blade.php` 加载统一外壳和公共 CSS。
 * - 本测试约束全局布局入口、CSS 设计变量、Layui 组件覆盖和常见乱码风险。
 * - `$layoutPath` 表示后台 Blade 总布局文件路径，`$stylePath` 表示后台公共样式文件路径。
 */
class AdminLayoutUiModernizationTest extends TestCase
{
    /**
     * 后台总布局必须继续提供统一的 Blade 外壳和公共样式入口。
     *
     * @return void
     */
    public function test_admin_layout_keeps_modern_workbench_shell(): void
    {
        // $layoutPath：后台总布局文件路径，所有后台 Blade 页面都依赖该入口渲染。
        $layoutPath = resource_path('admin/layui/layouts/app.blade.php');
        // $layoutContent：后台总布局源码内容，用于检查关键结构是否仍然存在。
        $layoutContent = file_get_contents($layoutPath) ?: '';

        $this->assertStringContainsString('/css/admin/style.css', $layoutContent);
        $this->assertStringContainsString('crm-admin-workbench', $layoutContent);
        $this->assertStringContainsString('crm-admin-shell', $layoutContent);
        $this->assertStringContainsString('crm-admin-topbar', $layoutContent);
        $this->assertStringContainsString('crm-admin-sidebar', $layoutContent);
        $this->assertStringContainsString('crm-admin-page-head', $layoutContent);
        $this->assertStringContainsString('data-shell-label="后台工作台"', $layoutContent);

        foreach (['閸', '閳', '鍚庡彴', '宸ヤ綔'] as $fragment) {
            // $fragment：常见乱码片段，命中时说明后台总布局静态中文不可读。
            $this->assertStringNotContainsString($fragment, $layoutContent);
        }
    }

    /**
     * 后台公共 CSS 必须覆盖现代后台需要的主要布局和 Layui 组件。
     *
     * @return void
     */
    public function test_admin_style_defines_modern_layout_tokens_and_components(): void
    {
        // $stylePath：后台公共 CSS 文件路径，负责统一改造所有 Blade 后台页面视觉。
        $stylePath = public_path('css/admin/style.css');
        // $styleContent：后台公共 CSS 源码内容，用于检查变量、组件选择器和响应式规则。
        $styleContent = file_get_contents($stylePath) ?: '';

        $requiredFragments = [
            '--admin-radius',
            '--admin-radius-sm',
            '--admin-sidebar-width',
            '--admin-header-height',
            '--admin-shadow',
            '--admin-shadow-soft',
            '.crm-admin-shell',
            '.crm-admin-topbar',
            '.crm-admin-sidebar',
            '.crm-admin-page-head',
            '.layui-card',
            '.layui-form-pane',
            '.layui-input',
            '.layui-table-view',
            '.layui-layer',
            '.layui-laypage',
            '.admin-dialog-body',
            '@media screen and (max-width: 768px)',
        ];

        foreach ($requiredFragments as $fragment) {
            // $fragment：必须存在的 CSS 变量或选择器片段，用于证明全局 UI 规则覆盖完整。
            $this->assertStringContainsString($fragment, $styleContent);
        }

        foreach (['閴', '閳', '閸', '鍚庡彴'] as $fragment) {
            // $fragment：常见乱码片段，命中时说明后台公共样式文件存在不可读中文。
            $this->assertStringNotContainsString($fragment, $styleContent);
        }
    }
}
