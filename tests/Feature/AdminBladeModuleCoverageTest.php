<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/07/04
 * Time: 18:10
 */

/**
 * AdminBladeModuleCoverageTest
 *
 * 文件功能：
 * - 验证后台业务模块路由已注册，且页面由 Blade 外壳渲染并加载模块资源。
 * - 输入：路由、控制器、Blade/JS、迁移等项目源码文本；输出：PHPUnit 断言结果。
 * - 明确不负责：不覆盖 HTTP 运行时业务流与 MT4 真实网关同步（由集成与功能测试锁定）。
 */

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * 后台业务模块 Blade 页面覆盖测试。
 *
 * 测试目的：
 * - 防止后台业务页面继续落入 /admin/{path?} 的 Naive UI 兜底页面。
 * - 约束本项目后台必须使用 Laravel Blade 模板直接渲染页面外壳。
 * - 约束每个业务模块都拥有独立的表格容器与 JS 入口，方便后续做权限按钮、多语言和接口联调。
 */
class AdminBladeModuleCoverageTest extends TestCase
{
    /**
     * 第一批必须 Blade 化的后台模块配置。
     *
     * 数组字段说明：
     * - path：后台页面访问路径。
     * - route：页面路由名称，供 Blade/JS 统一生成 URL。
     * - table：页面主表格 DOM ID，用于 Layui table.render 绑定。
     * - script：页面专属 JS 资源路径，用于承载接口、筛选和按钮权限逻辑。
     *
     * @return array<string, array{path:string, route:string, table:string, script:string}>
     */
    public static function bladeModuleProvider(): array
    {
        return [
            'agents' => [
                'path' => '/admin/agents',
                'route' => 'admin_page_agents',
                'table' => 'agentTable',
                'script' => '/js/apps/admin/layui/agents/index.js',
            ],
            'deposits' => [
                'path' => '/admin/deposits',
                'route' => 'admin_page_deposits',
                'table' => 'depositTable',
                'script' => '/js/apps/admin/layui/deposits/index.js',
            ],
            'withdrawals' => [
                'path' => '/admin/withdrawals',
                'route' => 'admin_page_withdrawals',
                'table' => 'withdrawTable',
                'script' => '/js/apps/admin/layui/withdrawals/index.js',
            ],
            'commissions' => [
                'path' => '/admin/commissions',
                'route' => 'admin_page_commissions',
                'table' => 'commissionTable',
                'script' => '/js/apps/admin/layui/commissions/index.js',
            ],
            'agent-levels' => [
                'path' => '/admin/agent-levels',
                'route' => 'admin_page_agent_levels',
                'table' => 'agentLevelTable',
                'script' => '/js/apps/admin/layui/agent-levels/index.js',
            ],
            'group-configs' => [
                'path' => '/admin/group-configs',
                'route' => 'admin_page_group_configs',
                'table' => 'groupConfigTable',
                'script' => '/js/apps/admin/layui/group-configs/index.js',
            ],
            'system-configs' => [
                'path' => '/admin/system-configs',
                'route' => 'admin_page_system_configs',
                'table' => 'systemConfigTable',
                'script' => '/js/apps/admin/layui/system-configs/index.js',
            ],
            'channels' => [
                'path' => '/admin/channels',
                'route' => 'admin_page_channels',
                'table' => 'channelTable',
                'script' => '/js/apps/admin/layui/channels/index.js',
            ],
            'admins' => [
                'path' => '/admin/admins',
                'route' => 'admin_page_admins',
                'table' => 'adminTable',
                'script' => '/js/apps/admin/layui/admins/index.js',
            ],
            'news' => [
                'path' => '/admin/news',
                'route' => 'admin_page_news',
                'table' => 'newsTable',
                'script' => '/js/apps/admin/layui/news/index.js',
            ],
        ];
    }

    /**
     * 后台业务模块必须注册独立页面路由。
     *
     * @dataProvider bladeModuleProvider
     *
     * @param string $path 页面访问路径。
     * @param string $route 页面命名路由。
     * @param string $table 页面主表格 DOM ID。
     * @param string $script 页面专属 JS 资源路径。
     * @return void
     */
    public function test_admin_business_module_routes_are_registered(string $path, string $route, string $table, string $script): void
    {
        $this->assertTrue(Route::has($route), $route . ' 页面路由未注册。');
    }

    /**
     * 后台业务模块必须由 Blade 外壳渲染并加载模块资源。
     *
     * @dataProvider bladeModuleProvider
     *
     * @param string $path 页面访问路径。
     * @param string $route 页面命名路由。
     * @param string $table 页面主表格 DOM ID。
     * @param string $script 页面专属 JS 资源路径。
     * @return void
     */
    public function test_admin_business_module_pages_render_blade_shell(string $path, string $route, string $table, string $script): void
    {
        $response = $this->get($path);

        $response->assertOk();
        $response->assertSee('crm-admin-workbench', false);
        $response->assertSee('data-render-mode="blade"', false);
        $response->assertSee('id="' . $table . '"', false);
        $module = preg_replace('#^/js/apps/admin/layui/(.+)\.js$#', '$1', $script);
        $response->assertSee('/js/apps/admin/layui/pages.js', false);
        $response->assertSee("data-layui-page=\"" . $module . "\"", false);
    }
}
