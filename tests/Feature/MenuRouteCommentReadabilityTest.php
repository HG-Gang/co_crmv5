<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/07/05
 * Time: 08:42
 */

/**
 * MenuRouteCommentReadabilityTest
 *
 * 文件功能：
 * - 验证前后台 API 路由文件中的菜单路由注释保持可读中文，并禁止典型乱码片段回流。
 * - 输入：控制器/模型/JS/Blade/CSS 等源码与语言包文本；输出：PHPUnit 断言结果。
 * - 明确不负责：不验证运行时业务行为与数据库交互。
 */

namespace Tests\Feature;

use Tests\TestCase;

/**
 * @runClassInSeparateProcess
 * @preserveGlobalState disabled
 *
 * 前后台菜单 API 路由中文注释可读性测试。
 *
 * 功能逻辑说明：
 * - `routes/front.php` 注册前台 agent/customer 菜单接口，必须说明 JWT、SSO 和菜单用途。
 * - `routes/admin.php` 注册后台菜单、菜单管理和按钮权限接口，必须说明 JWT、SSO、check.permission:admin 边界。
 * - 本测试只约束路由文件注释与关键路由存在性，不改变路由行为。
 */
class MenuRouteCommentReadabilityTest extends TestCase
{
    protected function tearDown(): void
    {
        parent::tearDown();

        gc_collect_cycles();
    }

    /**
     * 前台 API 路由必须说明菜单接口的中间件和用途。
     *
     * 参数含义：
     * - $content：前台 API 路由源码内容，用于确认菜单接口注释是否可读。
     * - $requiredTexts：必须存在的中文说明片段，覆盖路由前缀、JWT/SSO 保护和菜单接口用途。
     *
     * @return void
     */
    public function test_front_api_routes_contain_readable_menu_route_comments(): void
    {
        $content = file_get_contents(base_path('routes/front.php'));

        $requiredTexts = [
            '前台 API 路由',
            '路由前缀：api/front',
            'JWT 与 SSO 保护接口',
            '前台菜单接口：返回当前登录用户可见的 Layui/Blade 菜单树',
            "Route::get('/navigation/menus', 'MenuController@userMenus')->name('front_api_navigation_menus')",
        ];

        foreach ($requiredTexts as $text) {
            $this->assertStringContainsString($text, $content);
        }
    }

    /**
     * 后台 API 路由必须说明菜单接口的中间件和权限边界。
     *
     * 参数含义：
     * - $content：后台 API 路由源码内容，用于确认菜单权限接口注释是否可读。
     * - $requiredTexts：必须存在的中文说明片段，覆盖路由前缀、JWT/SSO/权限中间件和菜单管理用途。
     *
     * @return void
     */
    public function test_admin_api_routes_contain_readable_menu_route_comments(): void
    {
        $content = file_get_contents(base_path('routes/admin.php'));

        $requiredTexts = [
            '后台 API 路由',
            '路由前缀：api/admin',
            'JWT、SSO 与后台权限保护接口',
            '后台当前管理员菜单接口：返回 data.menus 和 data.permissions',
            '后台菜单管理接口：维护 permissions 表中的菜单权限字典',
            "Route::post('/menus', 'MenuController@adminMenus')->name('admin_api_menus')",
            "Route::post('/menuTree', 'MenuController@menuTree')->name('admin_api_menuTree')",
        ];

        foreach ($requiredTexts as $text) {
            $this->assertStringContainsString($text, $content);
        }
    }

    /**
     * 前后台 API 路由文件不能出现典型中文乱码片段。
     *
     * 参数含义：
     * - $files：需要扫描的路由文件路径列表。
     * - $fragment：单个乱码特征片段，命中时说明路由注释不可读。
     *
     * @return void
     */
    public function test_menu_route_files_do_not_contain_mojibake_comment_fragments(): void
    {
        $files = [
            base_path('routes/front.php'),
            base_path('routes/admin.php'),
        ];

        foreach ($files as $file) {
            $content = file_get_contents($file);

            foreach (['鐨', '鏉', '閺', '娴', '绠', '缁', '閸', '闁', '婢', '锛', '鍚', '鍙', '�'] as $fragment) {
                $this->assertStringNotContainsString($fragment, $content, $file . ' 存在疑似乱码片段：' . $fragment);
            }
        }
    }
}
