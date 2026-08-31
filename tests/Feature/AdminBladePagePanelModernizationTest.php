<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/06/07
 * Time: 21:35
 */

/**
 * AdminBladePagePanelModernizationTest
 *
 * 文件功能：
 * - 验证后台表格页统一使用 crm-admin-panel 面板样式类完成现代化布局。
 * - 输入：路由、控制器、Blade/JS、迁移等项目源码文本；输出：PHPUnit 断言结果。
 * - 明确不负责：不覆盖 HTTP 运行时业务流与 MT4 真实网关同步（由集成与功能测试锁定）。
 */

namespace Tests\Feature;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use Tests\TestCase;

/**
 * 后台 Blade 业务面板现代化布局测试。
 *
 * 功能逻辑说明：
 * - plan.md 第 7 节要求后台 UI 参考 Vben Admin、Naive UI Admin、Ant Design Pro、Arco Design Pro 的中后台布局密度。
 * - 当前项目仍使用 Laravel Blade + Layui 渲染，因此需要通过统一的业务面板类承接公共 CSS，而不是每个页面散落裸 `layui-card`。
 * - 本测试只检查含 Layui 表格的业务列表页，避免把登录页、个人资料页和简单详情页纳入同一布局规则。
 */
class AdminBladePagePanelModernizationTest extends TestCase
{
    /**
     * 含后台表格的 Blade 页面必须使用统一业务面板类。
     *
     * 参数含义：
     * - $files：后台 Blade 文件列表，来源为 `resources/admin/layui`。
     * - $content：单个 Blade 文件源码，用于判断是否是列表页、是否使用统一面板类。
     * - $missingPanelFiles：缺少 `crm-admin-panel` 的列表页路径集合。
     *
     * @return void
     */
    public function test_admin_table_pages_use_crm_admin_panel_class(): void
    {
        $missingPanelFiles = [];

        /** @var SplFileInfo $file */
        foreach ($this->adminBladeFiles() as $file) {
            $content = file_get_contents($file->getPathname()) ?: '';

            if (strpos($content, '<table class="layui-hide"') === false) {
                continue;
            }

            if (strpos($content, 'crm-admin-panel') === false) {
                $missingPanelFiles[] = $file->getPathname();
            }
        }

        $this->assertEmpty(
            $missingPanelFiles,
            "以下后台表格页缺少 crm-admin-panel 统一业务面板类：\n" . implode("\n", $missingPanelFiles)
        );
    }

    /**
     * 读取后台 Blade 文件迭代器。
     *
     * @return array<int, SplFileInfo> 后台 Blade 文件对象列表。
     */
    private function adminBladeFiles(): array
    {
        $files = [];
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(resource_path('admin/layui')));

        /** @var SplFileInfo $file */
        foreach ($iterator as $file) {
            if (! $file->isFile() || substr($file->getFilename(), -10) !== '.blade.php') {
                continue;
            }

            $files[] = $file;
        }

        return $files;
    }
}
