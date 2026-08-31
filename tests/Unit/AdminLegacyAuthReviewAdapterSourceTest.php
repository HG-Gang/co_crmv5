<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/07
 * Time: 14:29
 */

/**
 * AdminLegacyAuthReviewAdapterSourceTest
 *
 * 文件功能：
 * - 验证旧实名审核适配器源码转发原子化组件审核决定。
 * - 输入：内存构造的服务对象、测试替身与必要的数据库夹具；输出：PHPUnit 断言结果。
 * - 明确不负责：不覆盖 HTTP 路由与控制器接线（由 Feature 契约测试锁定）。
 */

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class AdminLegacyAuthReviewAdapterSourceTest extends TestCase
{
    public function test_legacy_auth_adapter_forwards_atomic_component_decisions(): void
    {
        $source = file_get_contents(dirname(__DIR__, 2) . '/app/Http/Controllers/Admin/LegacyAdminController.php');

        $this->assertIsString($source);
        $this->assertStringContainsString('AuthReviewTransition::legacyDecisionPayload($payload)', $source);
        $this->assertStringContainsString("'status' => null", $source);
        $collapsedExpression = <<<'PHP'
($idCardAuth === '0' && $bankAuth === '0') ? 1 : 2
PHP;
        $this->assertStringNotContainsString(
            $collapsedExpression,
            $source
        );
    }
}
