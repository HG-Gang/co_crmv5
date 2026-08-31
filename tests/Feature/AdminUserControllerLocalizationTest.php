<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/06/08
 * Time: 08:34
 */

/**
 * AdminUserControllerLocalizationTest
 *
 * 文件功能：
 * - 验证后台用户控制器响应使用语言 key，且这些 key 在 zh_CN 与 en 语言包中均已配置。
 * - 输入：语言包数组与渲染后的响应/脚本文本；输出：PHPUnit 断言结果。
 * - 明确不负责：不覆盖业务逻辑与路由契约（由各模块功能测试锁定）。
 */

namespace Tests\Feature;

use Tests\TestCase;

/**
 * 后台用户控制器多语言测试。
 *
 * 测试目的：
 * - 后台接口响应文案必须从 Laravel 语言包读取，避免控制器写死英文导致后端不支持多语言。
 * - `AdminUserController` 是后台用户列表、详情、更新、删除和状态切换的核心入口，因此先用静态测试锁定响应文案来源。
 */
class AdminUserControllerLocalizationTest extends TestCase
{
    /**
     * 验证后台用户控制器不再直接返回硬编码英文文案。
     *
     * @return void
     */
    public function test_admin_user_controller_uses_language_keys_for_response_messages(): void
    {
        $source = file_get_contents(app_path('Http/Controllers/Admin/AdminUserController.php')) ?: '';

        $hardCodedMessages = [
            'User list fetched',
            'User not found',
            'User deleted',
            'User detail fetched',
            'User updated',
            'User status updated',
        ];

        foreach ($hardCodedMessages as $message) {
            $this->assertStringNotContainsString($message, $source, "控制器仍存在硬编码英文响应：{$message}");
        }

        $languageCalls = [
            "__('admin.user_list_fetched')",
            "__('admin.user_not_found')",
            "__('admin.user_deleted')",
            "__('admin.user_detail_fetched')",
            "__('admin.user_updated')",
            "__('admin.user_status_updated')",
        ];

        foreach ($languageCalls as $call) {
            $this->assertStringContainsString($call, $source, "控制器缺少语言包调用：{$call}");
        }
    }

    /**
     * 验证后台用户控制器依赖的中英文语言包 key 均已配置。
     *
     * @return void
     */
    public function test_admin_user_response_language_keys_exist_in_zh_cn_and_en(): void
    {
        $requiredKeys = [
            'user_list_fetched',
            'user_not_found',
            'user_deleted',
            'user_detail_fetched',
            'user_updated',
            'user_status_updated',
        ];

        $zhCn = require resource_path('lang/zh-CN/admin.php');
        $en = require resource_path('lang/en/admin.php');

        foreach ($requiredKeys as $key) {
            $this->assertArrayHasKey($key, $zhCn, "zh-CN/admin.php 缺少语言 key：{$key}");
            $this->assertArrayHasKey($key, $en, "en/admin.php 缺少语言 key：{$key}");
            $this->assertNotSame('', trim((string) $zhCn[$key]), "zh-CN/admin.php 的 {$key} 不能为空");
            $this->assertNotSame('', trim((string) $en[$key]), "en/admin.php 的 {$key} 不能为空");
        }
    }
}
