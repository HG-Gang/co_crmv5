<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/07/31
 * Time: 23:44
 */

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Blade 单体前端架构约束测试。
 *
 * 文件功能：
 * - 防止 PHP 单体 CRM 再次引入 Node、Webpack 或客户端拼接页面的运行依赖。
 * - 保留历史 Naive URL 的可访问性，但统一把访问者带到同业务的服务端 Blade 页面。
 * - 未知页面路径必须回到 Blade 仪表盘，不能由 JavaScript 根据字符串临时生成界面。
 */
class BladeOnlyFrontendArchitectureTest extends TestCase
{
    /**
     * 验证项目根目录不再保留未使用的 Node/Mix 前端构建工具链。
     *
     * @return void 删除这些残留后，页面 CSS、JS 和组件只由 Blade 与 public 本地静态资源承担。
     */
    public function test_monolith_does_not_keep_a_node_or_mix_frontend_build_toolchain(): void
    {
        foreach ([
            base_path('node_modules'),
            base_path('package.json'),
            base_path('package-lock.json'),
            base_path('webpack.mix.js'),
            resource_path('js'),
            resource_path('css'),
            resource_path('views/naive'),
            public_path('js/apps/naive-admin'),
            public_path('css/naive-admin'),
        ] as $path) {
            $this->assertFalse(file_exists($path), 'Blade 单体项目不应保留未使用的 Node/Mix 构建残留：' . $path);
        }
    }

    /**
     * 验证前后台未知页面不会再落入 Naive 客户端渲染兜底。
     *
     * @return void 未识别路径重定向到各自 Blade 仪表盘，避免渲染逻辑存在第二个前端运行时。
     */
    public function test_unknown_page_paths_redirect_to_blade_dashboards(): void
    {
        $this->get('/front/not-a-page')->assertRedirect(route('front_page_dashboard'));
        $this->get('/admin/not-a-page')->assertRedirect(route('admin_page_dashboard'));
    }
}
