<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/07/26
 * Time: 13:27
 */

/**
 * AdminLayuiLayoutReadableChineseTest
 *
 * 文件功能：
 * - 验证后台总布局外壳文案保持可读中文，禁止常见 mojibake 乱码片段。
 * - 输入：控制器/模型/JS/Blade/CSS 等源码与语言包文本；输出：PHPUnit 断言结果。
 * - 明确不负责：不验证运行时业务行为与数据库交互。
 */

namespace Tests\Feature;

use Tests\TestCase;

/**
 * 后台 Layui Blade 总布局中文可读性测试。
 *
 * 功能逻辑说明：
 * - 后台 UI 虽然保留 Layui 和 Blade 渲染方式，但壳层文案必须参考 Vben/Naive/Ant/Arco 的现代后台体验。
 * - 如果总布局存在 mojibake 乱码，所有后台页面都会继承错误文案，影响导航、主题、风格切换和页头识别。
 * - 本测试只约束 `resources/admin/layui/layouts/app.blade.php` 的可见中文和关键注释，不修改业务页面结构。
 */
class AdminLayuiLayoutReadableChineseTest extends TestCase
{
    /**
     * 后台 Layui 总布局必须保留可读中文壳层文案和 UI 参考标识。
     *
     * 参数与变量含义：
     * - $layoutPath：后台 Layui 总布局文件路径，所有后台 Blade 页面都会继承该壳层。
     * - $layout：布局源码文本，用于检查可见中文、data 属性、注释和乱码黑名单。
     * - $fragment：必须出现或禁止出现的片段，用于明确布局文案是否可读。
     *
     * @return void
     */
    public function test_admin_layui_layout_keeps_readable_chinese_shell_text(): void
    {
        $layoutPath = resource_path('admin/layui/layouts/app.blade.php');
        $layout = file_get_contents($layoutPath) ?: '';

        foreach ($this->requiredReadableFragments() as $fragment) {
            $this->assertStringContainsString($fragment, $layout, '后台 Layui 总布局缺少可读中文片段：' . $fragment);
        }

        foreach ($this->forbiddenGarbledFragments() as $fragment) {
            $this->assertStringNotContainsString($fragment, $layout, '后台 Layui 总布局仍包含乱码片段：' . $fragment);
        }
    }

    /**
     * 必须保留的可读中文和现代后台 UI 标识。
     *
     * @return array<int, string> 可读片段列表，用于证明布局文案和 UI 参考来源清晰。
     */
    private function requiredReadableFragments(): array
    {
        return [
            'data-render-mode="blade"',
            'data-ui-reference="Vben Admin, Vue Naive Admin, Naive UI Admin, Ant Design Pro, Arco Design Pro"',
            'data-shell-label="后台工作台"',
            'title="{{ __(\'common.toggle_sidebar\') ?? \'折叠菜单\' }}"',
            '>界面<',
            'Layui 风格',
            'CrmUI 风格',
            'title="主题"',
            '菜单由 /api/admin/menus 接口加载',
            '后台页头：参考 Vben/Ant/Arco 的工作台页头结构',
            '<div class="crm-admin-page-kicker">后台工作台</div>',
        ];
    }

    /**
     * 后台总布局中禁止继续出现的常见 mojibake 乱码片段。
     *
     * @return array<int, string> 乱码片段黑名单。
     */
    private function forbiddenGarbledFragments(): array
    {
        return [
            '鍚庡彴宸ヤ綔鍙',
            '鎶樺彔鑿滃崟',
            '鐣岄潰',
            '椋庢牸',
            '涓婚',
            '鑿滃崟',
            '鎺ュ彛',
            '鍙礋璐',
            '鍙充晶',
        ];
    }
}
