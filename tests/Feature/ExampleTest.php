<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/07/26
 * Time: 13:27
 */

namespace Tests\Feature;

use Tests\TestCase;

/**
 * 站点根路径兼容跳转测试。
 *
 * 文件功能：验证访问根路径时进入普通用户登录页，并保留旧项目默认语言编号。
 */
class ExampleTest extends TestCase
{
    /**
     * 验证根路径默认跳转到中文登录入口。
     *
     * @return void 成功时响应为带 `langId=1` 的 HTTP 302；路由目标或默认语言变化时测试失败。
     */
    public function test_root_route_preserves_default_legacy_language_id(): void
    {
        $response = $this->get('/');

        $response->assertRedirect('/front/login?langId=1');
    }
}
