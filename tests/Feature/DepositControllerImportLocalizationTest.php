<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/07/05
 * Time: 22:57
 */

/**
 * DepositControllerImportLocalizationTest
 *
 * 文件功能：
 * - 验证旧入金导入口兼容：不再返回假成功，委托给真实批量入金导入控制器，保留中文参数与边界注释并清除占位文案。
 * - 输入：路由、控制器、Blade/JS、迁移等项目源码文本；输出：PHPUnit 断言结果。
 * - 明确不负责：不覆盖 HTTP 运行时业务流与 MT4 真实网关同步（由集成与功能测试锁定）。
 */

namespace Tests\Feature;

use Tests\TestCase;

/**
 * 后台旧入金导入口兼容测试。
 *
 * 测试目的：
 * - DepositController#import 不能继续返回“即将开放”之类的假成功。
 * - 旧入口必须复用 BatchAmountImportController 的真实入金导入逻辑。
 */
class DepositControllerImportLocalizationTest extends TestCase
{
    public function test_deposit_import_legacy_entry_delegates_to_real_batch_import_controller(): void
    {
        $source = file_get_contents(app_path('Http/Controllers/Admin/DepositController.php')) ?: '';

        $this->assertStringContainsString('BatchAmountImportController', $source);
        $this->assertStringContainsString('createDepositImport($request)', $source);
        $this->assertStringNotContainsString('deposit_import_feature_coming_soon', $source);
        $this->assertStringNotContainsString('Import feature coming soon', $source);
        $this->assertStringNotContainsString('功能即将开放', $source);
    }

    public function test_deposit_import_legacy_entry_has_chinese_parameter_and_boundary_comments(): void
    {
        $source = file_get_contents(app_path('Http/Controllers/Admin/DepositController.php')) ?: '';

        foreach ([
            '旧入金导入兼容入口',
            'import() 参数说明',
            'Request $request 当前 HTTP 请求对象',
            '复用 BatchAmountImportController::createDepositImport',
            '旧入口不再返回占位成功',
        ] as $comment) {
            $this->assertStringContainsString($comment, $source, "DepositController#import 缺少中文说明：{$comment}");
        }
    }

    public function test_deposit_import_legacy_placeholder_copy_is_removed_from_active_sources(): void
    {
        foreach ([
            resource_path('lang/en/admin.php'),
            resource_path('lang/zh-CN/admin.php'),
            base_path('routes/admin.php'),
        ] as $path) {
            $source = file_get_contents($path) ?: '';

            $this->assertStringNotContainsString('deposit_import_feature_coming_soon', $source, $path);
            $this->assertStringNotContainsString('Deposit import feature coming soon', $source, $path);
            $this->assertStringNotContainsString('功能即将开放', $source, $path);
            $this->assertStringNotContainsString('Excel 解析和失败重试后续独立补齐', $source, $path);
        }
    }
}
