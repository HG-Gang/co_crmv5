<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/06/09
 * Time: 23:38
 */

/**
 * AdminExceptionMessageLocalizationTest
 *
 * 文件功能：
 * - 验证后台控制器不向客户端暴露原始异常消息，且后台基类提供语言感知的服务端异常响应方法。
 * - 输入：控制器/模型/JS/Blade/CSS 等源码与语言包文本；输出：PHPUnit 断言结果。
 * - 明确不负责：不验证运行时业务行为与数据库交互。
 */

namespace Tests\Feature;

use Tests\TestCase;

/**
 * 后台控制器异常消息多语言契约测试。
 *
 * 功能逻辑说明：
 * - 后台 API 发生未预期异常时，接口响应应该返回统一的 `response.server_error` 多语言文案。
 * - 控制器不能把 `$e->getMessage()` 直接返回给前端，否则会绕过语言包并可能泄露 SQL、文件路径或第三方异常详情。
 * - 本测试静态扫描 `app/Http/Controllers/Admin`，确保后台控制器统一走基类封装的服务端错误响应。
 */
class AdminExceptionMessageLocalizationTest extends TestCase
{
    /**
     * 后台控制器不能直接把异常原始消息作为接口 message 返回。
     *
     * 参数与变量含义：
     * - $controllerDirectory：后台控制器目录，包含所有后台 API 与 Blade 控制器。
     * - $files：被扫描的 PHP 控制器文件列表。
     * - $source：当前控制器源码文本，用于查找 `$e->getMessage()` 外泄模式。
     * - $relativePath：失败提示中展示的相对路径，便于快速定位问题文件。
     *
     * @return void
     */
    public function test_admin_controllers_do_not_expose_raw_exception_messages(): void
    {
        $controllerDirectory = app_path('Http/Controllers/Admin');
        $files = glob($controllerDirectory . '/*.php') ?: [];

        $this->assertNotEmpty($files, '后台控制器目录不能为空。');

        foreach ($files as $file) {
            $source = file_get_contents($file) ?: '';
            $relativePath = str_replace(base_path() . DIRECTORY_SEPARATOR, '', $file);

            $this->assertStringNotContainsString(
                '$this->error($e->getMessage(), ResponseCode::SERVER_ERROR)',
                $source,
                $relativePath . ' 不能直接把异常原始消息返回给前端，必须使用多语言 serverError 响应。'
            );
        }
    }

    /**
     * 后台基类必须提供统一的服务端异常响应方法。
     *
     * @return void
     */
    public function test_admin_base_controller_provides_locale_aware_server_error_response(): void
    {
        $source = file_get_contents(app_path('Http/Controllers/Admin/AdminBaseController.php')) ?: '';

        $this->assertStringContainsString('后台服务端异常响应', $source);
        $this->assertStringContainsString('serverErrorResponse', $source);
        $this->assertStringContainsString("__('response.server_error')", $source);
        $this->assertStringContainsString('ResponseCode::SERVER_ERROR', $source);
    }
}
