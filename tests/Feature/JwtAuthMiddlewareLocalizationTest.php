<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/06/09
 * Time: 01:30
 */

/**
 * JwtAuthMiddlewareLocalizationTest
 *
 * 文件功能：
 * - 验证 JWT 认证中间件响应消息本地化，且 guard、token 与 payload 核心参数含义有中文说明。
 * - 输入：控制器/模型/JS/Blade/CSS 等源码与语言包文本；输出：PHPUnit 断言结果。
 * - 明确不负责：不验证运行时业务行为与数据库交互。
 */

namespace Tests\Feature;

use Tests\TestCase;

/**
 * JWT 鉴权中间件多语言与中文逻辑注释测试。
 *
 * 功能逻辑说明：
 * - JWT 中间件位于前后台 API 的认证入口，错误响应不能硬编码英文。
 * - token 缺失、用户不存在、解析失败等消息必须通过 resources/lang 下的 response.php 输出。
 * - guard、token、payload 等参数含义必须有中文注释，方便后续维护前台 user 与后台 admin 双守卫。
 */
class JwtAuthMiddlewareLocalizationTest extends TestCase
{
    /**
     * JWT 中间件认证失败响应必须使用后端多语言语言包。
     *
     * @return void
     */
    public function test_jwt_auth_middleware_uses_localized_response_messages(): void
    {
        $source = file_get_contents(app_path('Http/Middleware/JwtAuthMiddleware.php')) ?: '';

        $this->assertStringContainsString("__('response.token_missing')", $source, '缺少 Token 必须使用 response.token_missing 多语言消息。');
        $this->assertStringContainsString("__('response.user_not_found')", $source, '用户不存在必须使用 response.user_not_found 多语言消息。');
        $this->assertStringNotContainsString("'Authorization token not found'", $source, 'JWT 中间件不能硬编码英文 Token 缺失消息。');
        $this->assertStringNotContainsString("'User not found'", $source, 'JWT 中间件不能硬编码英文用户不存在消息。');
    }

    /**
     * JWT 中间件必须说明核心参数的中文逻辑含义。
     *
     * @return void
     */
    public function test_jwt_auth_middleware_documents_guard_token_and_payload_meaning(): void
    {
        $source = file_get_contents(app_path('Http/Middleware/JwtAuthMiddleware.php')) ?: '';

        foreach ([
            '$guard 表示当前认证守卫',
            '$header 表示 Authorization 请求头',
            '$token 表示 Bearer 后面的 JWT 字符串',
            '$payload 表示 JWT 解析后的载荷',
            '$decodedGuard 表示令牌载荷中的守卫类型',
        ] as $expectedComment) {
            $this->assertStringContainsString($expectedComment, $source);
        }
    }
}
