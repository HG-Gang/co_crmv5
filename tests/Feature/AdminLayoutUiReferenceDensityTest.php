<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/06/09
 * Time: 01:15
 */

/**
 * AdminLayoutUiReferenceDensityTest
 *
 * 文件功能：
 * - 验证后台总布局保留 UI 参考来源标记（Vben/Naive/Ant/Arco 等），公共 CSS 保留密度变量、吸顶页头与统一工具条规则。
 * - 输入：控制器/模型/JS/Blade/CSS 等源码与语言包文本；输出：PHPUnit 断言结果。
 * - 明确不负责：不验证运行时业务行为与数据库交互。
 */

namespace Tests\Feature;

use Tests\TestCase;

/**
 * 后台 Blade 总布局 UI 参考与信息密度测试。
 *
 * 测试目标：
 * - plan.md 第 7 节要求后台 UI 参考 Vben Admin、Vue Naive Admin、Naive UI Admin、Ant Design Pro、Arco Design Pro。
 * - 当前项目不能改成前后端分离，因此这些参考必须落实到 Laravel Blade 外壳和公共 CSS 的可维护结构中。
 * - 本测试约束布局文件保留参考来源标记，CSS 保留密度变量、吸顶页头和统一工具条规则，避免后续页面退回松散卡片布局。
 */
class AdminLayoutUiReferenceDensityTest extends TestCase
{
    public function test_admin_blade_layout_declares_ui_reference_sources(): void
    {
        $layout = file_get_contents(resource_path('admin/layui/layouts/app.blade.php')) ?: '';

        $this->assertStringContainsString('data-ui-reference="Vben Admin, Vue Naive Admin, Naive UI Admin, Ant Design Pro, Arco Design Pro"', $layout);
        $this->assertStringContainsString('data-render-mode="blade"', $layout);
        $this->assertStringContainsString('crm-admin-page-head-main', $layout);
        $this->assertStringContainsString('crm-admin-page-head-tools', $layout);
    }

    public function test_admin_css_defines_maintainable_density_and_sticky_page_head(): void
    {
        $style = file_get_contents(public_path('css/admin/style.css')) ?: '';

        foreach ([
            '后台 UI 参考层',
            'Vben Admin',
            'Vue Naive Admin',
            'Naive UI Admin',
            'Ant Design Pro',
            'Arco Design Pro',
            '--admin-content-gap',
            '--admin-panel-padding',
            '--admin-toolbar-height',
            '.crm-admin-page-head-main',
            '.crm-admin-page-head-tools',
            'position: sticky',
            'top: 0',
            '.crm-admin-toolbar',
            '.crm-admin-density-compact',
            '.crm-admin-density-comfortable',
        ] as $fragment) {
            $this->assertStringContainsString($fragment, $style, '后台公共 CSS 缺少现代布局密度片段：' . $fragment);
        }

        foreach (['鍚庡彴', '鐜颁唬', '闈㈡澘', '寮圭獥', '涔辩爜'] as $fragment) {
            $this->assertStringNotContainsString($fragment, $style, '后台公共 CSS 仍包含乱码中文注释片段：' . $fragment);
        }
    }
}
