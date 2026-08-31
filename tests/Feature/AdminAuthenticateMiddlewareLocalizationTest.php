<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/06/09
 * Time: 01:52
 */

/**
 * AdminAuthenticateMiddlewareLocalizationTest
 *
 * 文件功能：
 * - 验证 AdminAuthenticate 中间件的 JSON 响应走本地化语言包，且请求、闭包、JSON 响应与登录跳转的参数含义均有中文说明。
 * - 输入：控制器/模型/JS/Blade/CSS 等源码与语言包文本；输出：PHPUnit 断言结果。
 * - 明确不负责：不验证运行时业务行为与数据库交互。
 */

namespace Tests\Feature;

use Tests\TestCase;

/**
 * 后台 Session 鉴权中间件多语言与中文注释测试。
 *
 * 功能逻辑说明：
 * - AdminAuthenticate 是后台 Blade 页面可能复用的 Session guard 鉴权边界。
 * - JSON 请求未登录时必须返回后端语言包文案，不能继续硬编码 `Unauthenticated.`。
 * - 页面请求未登录时仍跳转后台登录页，避免破坏当前 `/admin/login` Blade 登录入口。
 */
class AdminAuthenticateMiddlewareLocalizationTest extends TestCase
{
    /**
     * 未认证 JSON 响应必须使用 response.auth_failed 多语言文案。
     *
     * @return void
     */
    public function test_admin_authenticate_uses_localized_json_message(): void
    {
        $source = file_get_contents(app_path('Http/Middleware/AdminAuthenticate.php')) ?: '';

        $this->assertStringContainsString("__('response.auth_failed')", $source);
        $this->assertStringNotContainsString("'Unauthenticated.'", $source);
        $this->assertSame('认证失败', __('response.auth_failed', [], 'zh-CN'));
        $this->assertSame('Authentication failed', __('response.auth_failed', [], 'en'));
    }

    /**
     * 中间件必须说明请求、闭包、JSON 响应和登录跳转的参数含义。
     *
     * @return void
     */
    public function test_admin_authenticate_documents_request_next_and_redirect_boundaries(): void
    {
        $source = file_get_contents(app_path('Http/Middleware/AdminAuthenticate.php')) ?: '';

        foreach ([
            '$request 表示当前后台页面请求对象',
            '$next 表示通过鉴权后的下一个请求处理闭包',
            'expectsJson 表示前端希望接收 JSON 响应',
            'admin_page_login 表示后台 Blade 登录页命名路由',
        ] as $expectedComment) {
            $this->assertStringContainsString($expectedComment, $source);
        }
    }
}
