<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/07/26
 * Time: 11:08
 */

/**
 * AdminLayoutShellReadabilityTest
 *
 * 文件功能：
 * - 验证后台总布局外壳的中文标签保持 UTF-8 可读，并禁止常见 UTF-8/GBK 错解乱码片段。
 * - 输入：控制器/模型/JS/Blade/CSS 等源码与语言包文本；输出：PHPUnit 断言结果。
 * - 明确不负责：不验证运行时业务行为与数据库交互。
 */

namespace Tests\Feature;

use Tests\TestCase;

/**
 * 后台 Blade 总布局可读性回归测试。
 *
 * 功能逻辑说明：
 * - 后台所有 Blade 页面都会继承 `resources/admin/layui/layouts/app.blade.php`。
 * - 如果总布局出现中文乱码，后台顶部栏、侧边栏容器、页面标题区会在所有模块扩散不可读文案。
 * - 本测试只约束总布局外壳文案和常见乱码片段，避免后续 UI 优化时再次写入编码错误内容。
 */
class AdminLayoutShellReadabilityTest extends TestCase
{
    /**
     * 后台总布局中的外壳中文文案必须保持可读。
     *
     * @return void
     */
    public function test_admin_layout_shell_labels_are_readable_chinese(): void
    {
        // $layoutContent：后台总布局源码，用于检查 Blade 外壳静态中文是否可读。
        $layoutContent = file_get_contents(resource_path('admin/layui/layouts/app.blade.php')) ?: '';

        foreach ([
            'data-shell-label="后台工作台"',
            'title="{{ __(\'common.toggle_sidebar\') ?? \'折叠菜单\' }}"',
            '<a href="javascript:;">界面</a>',
            'Layui 风格',
            'CrmUI 风格',
            'title="主题"',
            '<div class="crm-admin-page-kicker">后台工作台</div>',
            '菜单由 /api/admin/menus 接口加载，Blade 只负责后台外壳渲染。',
        ] as $expectedText) {
            // $expectedText：后台总布局必须出现的可读中文片段。
            $this->assertStringContainsString($expectedText, $layoutContent);
        }

        // Naive 客户端运行时已经退役，布局不能再给用户展示一个无法落到服务端 Blade 页面的切换入口。
        $this->assertStringNotContainsString('Naive 风格', $layoutContent);
    }

    /**
     * 后台总布局不能包含常见 UTF-8/GBK 错解后的乱码片段。
     *
     * @return void
     */
    public function test_admin_layout_shell_has_no_common_mojibake_fragments(): void
    {
        // $layoutContent：后台总布局源码，用于扫描常见中文乱码片段。
        $layoutContent = file_get_contents(resource_path('admin/layui/layouts/app.blade.php')) ?: '';

        foreach (['鐨', '鏉', '閺', '闁', '缁', '缂', '鎺', '鍚', '锟', '�'] as $fragment) {
            // $fragment：常见乱码片段，命中时说明总布局存在不可读中文。
            $this->assertStringNotContainsString($fragment, $layoutContent, '后台总布局存在疑似乱码片段：' . $fragment);
        }
    }
}
