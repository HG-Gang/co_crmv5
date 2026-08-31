<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/06/09
 * Time: 01:41
 */

/**
 * JwtServiceLocalizationTest
 *
 * 文件功能：
 * - 验证 JwtService 异常消息本地化，并对安全字段与运行变量等核心参数具备中文业务含义说明。
 * - 输入：控制器/模型/JS/Blade/CSS 等源码与语言包文本；输出：PHPUnit 断言结果。
 * - 明确不负责：不验证运行时业务行为与数据库交互。
 */

namespace Tests\Feature;

use Tests\TestCase;

/**
 * JWT 服务多语言与中文逻辑注释覆盖测试。
 *
 * 功能逻辑说明：
 * - JwtService 是前台 user 与后台 admin 共用的令牌签发、解析、刷新和失效服务。
 * - 服务层抛出的异常会被中间件或控制器转换为认证响应，因此不能继续硬编码英文业务文案。
 * - JWT 载荷字段涉及 sub、guard、jti、iat、exp 等安全参数，必须有中文逻辑说明，避免后续误改认证边界。
 */
class JwtServiceLocalizationTest extends TestCase
{
    /**
     * JWT 服务异常文案必须改为 response 语言包 key。
     *
     * 参数和断言含义：
     * - $serviceContent：JwtService 源码文本，用于确认旧英文异常已经移除。
     * - $zhContent/$enContent：中英文响应语言包源码，用于确认新增 key 在两套语言中同时存在。
     *
     * @return void
     */
    public function test_jwt_service_uses_localized_exception_messages(): void
    {
        $serviceContent = file_get_contents(app_path('Services/JwtService.php'));
        $zhContent = file_get_contents(resource_path('lang/zh-CN/response.php'));
        $enContent = file_get_contents(resource_path('lang/en/response.php'));

        $this->assertStringNotContainsString("'Token has been invalidated'", $serviceContent);
        $this->assertStringNotContainsString("'Token cannot be refreshed, refresh window expired'", $serviceContent);
        $this->assertStringNotContainsString("'Token refresh failed: '", $serviceContent);
        $this->assertStringContainsString("__('response.jwt_token_invalidated')", $serviceContent);
        $this->assertStringContainsString("__('response.jwt_refresh_window_expired')", $serviceContent);
        $this->assertStringContainsString("__('response.jwt_refresh_failed')", $serviceContent);

        foreach (['jwt_token_invalidated', 'jwt_refresh_window_expired', 'jwt_refresh_failed'] as $key) {
            $this->assertStringContainsString("'" . $key . "'", $zhContent, $key . ' 缺少中文语言包配置。');
            $this->assertStringContainsString("'" . $key . "'", $enContent, $key . ' 缺少英文语言包配置。');
        }
    }

    /**
     * JWT 服务核心参数必须具备中文业务含义说明。
     *
     * 参数和断言含义：
     * - $requiredPhrases：JWT 服务必须说明的安全字段和运行变量。
     * - $serviceContent：JwtService 源码文本，用于静态确认中文注释覆盖。
     *
     * @return void
     */
    public function test_jwt_service_documents_core_security_parameters_in_chinese(): void
    {
        $serviceContent = file_get_contents(app_path('Services/JwtService.php'));

        $requiredPhrases = [
            '$secret 表示 JWT 签名密钥',
            '$ttl 表示访问令牌有效期',
            '$refreshTtl 表示刷新窗口有效期',
            '$algo 表示 JWT 签名算法',
            '$payload 表示业务载荷',
            '$jti 表示令牌唯一编号',
            '$mergedPayload 表示最终写入 JWT 的完整载荷',
            '$decoded 表示解码后的 JWT 载荷对象',
            '$cacheKey 表示单点登录缓存键',
            '$token 表示待解析或待刷新的 JWT 字符串',
            '$newPayload 表示刷新后新令牌的业务载荷',
        ];

        foreach ($requiredPhrases as $phrase) {
            $this->assertStringContainsString($phrase, $serviceContent, $phrase . ' 缺少中文逻辑注释。');
        }
    }
}
