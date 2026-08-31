<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/22
 * Time: 01:48
 */

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Http\Request;
use Tests\TestCase;

/**
 * 品牌门户页与 crmui 导航图标渲染闭环测试。
 *
 * 文件功能：
 * - 验证重做后的 welcome 品牌门户页（替换 Laravel 默认脚手架页）可渲染，
 *   包含本地 Lucide 资源、三端入口（前台/代理商/后台）且无脚手架残留。
 * - 验证 crmui 后台、前台、大代理三套侧边栏导航均输出 data-lucide 图标属性，
 *   与全站“统一 Lucide、禁止表情符号”策略保持一致。
 *
 * 适用场景：
 * - 任何改动 resources/views/welcome.blade.php、
 *   app/Http/Controllers/CrmUi 下各 PageController 的 navGroups() 方法、
 *   resources 下 crmui 布局或 big-agent 布局后回归。
 *
 * 入参例子：无（全部走渲染路径）。
 *
 * 返回值：断言通过即表示门户页与导航图标渲染链路闭环。
 *
 * 异常或失败场景：
 * - 门户页缺失 Lucide 资源片段或仍含 Laravel 脚手架（Laracasts 等）时失败。
 * - 任一导航组缺少 icon 字段导致 data-lucide 属性缺失时失败。
 */
final class PortalBrandPageClosureModuleTest extends TestCase
{
    /**
     * welcome 品牌门户页必须渲染本地 Lucide 资源、三端入口，且无默认脚手架残留。
     *
     * @return void 断言通过不返回值。
     */
    public function test_welcome_portal_page_renders_lucide_entries_without_scaffold(): void
    {
        $html = view('welcome')->render();

        // 品牌标题与本地 Lucide 桥接资源必须存在。
        $this->assertStringContainsString('CO CRM', $html);
        $this->assertStringContainsString('js/shared/lucide-bridge.js', $html);

        // 三端入口卡片必须存在：前台(user-round)、后台(shield-check)、代理商(network)。
        $this->assertStringContainsString('data-lucide="user-round"', $html);
        $this->assertStringContainsString('data-lucide="shield-check"', $html);
        $this->assertStringContainsString('data-lucide="network"', $html);

        // Laravel 默认脚手架内容必须被替换干净。
        $this->assertStringNotContainsString('Laracasts', $html);
        $this->assertStringNotContainsString('laravel.com/docs', $html);
        $this->assertStringNotContainsString('Nunito', $html);
    }

    /**
     * 前台注册 v2 视图必须可正常渲染（历史上曾存在 GBK 转码损坏导致语法错误）。
     *
     * @return void 断言通过不返回值。
     */
    public function test_front_register_v2_view_renders_without_syntax_error(): void
    {
        $html = view('front_layui::auth.register_v2')->render();
        $this->assertStringContainsString('data-lucide=', $html);
        $this->assertStringContainsString('register', $html);
    }

    /**
     * crmui 后台侧边栏导航必须为每个菜单项输出 data-lucide 图标。
     *
     * @return void 断言通过不返回值。
     */
    public function test_admin_crmui_sidebar_renders_lucide_icons(): void
    {
        $request = Request::create('/admin-crmui/dashboard', 'GET');
        $controller = app(\App\Http\Controllers\CrmUi\Admin\PageController::class);
        $response = $controller->show($request, 'dashboard');
        $html = $response instanceof \Illuminate\Contracts\View\View
            ? $response->render()
            : (string) $response;

        // 概览组、资金组、系统组各抽查一个图标。
        $this->assertStringContainsString('data-lucide="gauge"', $html);
        $this->assertStringContainsString('data-lucide="users"', $html);
        $this->assertStringContainsString('data-lucide="settings"', $html);
    }

    /**
     * crmui 前台侧边栏导航必须为每个菜单项输出 data-lucide 图标。
     *
     * @return void 断言通过不返回值。
     */
    public function test_front_crmui_sidebar_renders_lucide_icons(): void
    {
        $request = Request::create('/front-crmui/dashboard', 'GET');
        $controller = app(\App\Http\Controllers\CrmUi\Front\PageController::class);
        $response = $controller->show($request, 'dashboard');
        $html = $response instanceof \Illuminate\Contracts\View\View
            ? $response->render()
            : (string) $response;

        // 概览组、代理组、系统组各抽查一个图标。
        $this->assertStringContainsString('data-lucide="gauge"', $html);
        $this->assertStringContainsString('data-lucide="bell"', $html);
        $this->assertStringContainsString('data-lucide="send"', $html);
    }

    /**
     * crmui 大代理侧边栏导航必须为每个菜单项输出 data-lucide 图标。
     *
     * @return void 断言通过不返回值。
     */
    public function test_big_agent_crmui_sidebar_renders_lucide_icons(): void
    {
        $controller = app(\App\Http\Controllers\CrmUi\Front\BigAgentPageController::class);
        // dashboard() 需要 Request 以解析视觉家族；空请求按默认 crmui 家族渲染。
        $response = $controller->dashboard(new \Illuminate\Http\Request());
        $html = $response instanceof \Illuminate\Contracts\View\View
            ? $response->render()
            : (string) $response;

        // 工作台、代理列表、修改密码各抽查一个图标。
        $this->assertStringContainsString('data-lucide="gauge"', $html);
        $this->assertStringContainsString('data-lucide="network"', $html);
        $this->assertStringContainsString('data-lucide="key-round"', $html);
    }
}
