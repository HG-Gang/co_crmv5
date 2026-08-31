<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/17
 * Time: 20:05
 */

/**
 * FrontBusinessDataSourceAuditTest
 *
 * 文件功能：
 * - 验证前台业务数据源审计：业务源码不生成模拟行或硬编码品种，视觉审计夹具仅测试可用且需显式开启。
 * - 输入：路由、控制器、Blade/JS、迁移等项目源码文本；输出：PHPUnit 断言结果。
 * - 明确不负责：不覆盖 HTTP 运行时业务流与 MT4 真实网关同步（由集成与功能测试锁定）。
 */

namespace Tests\Feature;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use Tests\TestCase;

/**
 * 前台双 UI 业务数据源静态门禁。
 *
 * 该测试只扫描业务 Blade 和项目自有 JavaScript，防止空数据时重新引入本地 mock、demo 行或写死品种。
 * testing 环境的视觉审计 fixture 允许存在，但必须保持显式环境和 query 双重门禁。
 */
class FrontBusinessDataSourceAuditTest extends TestCase
{
    public function test_front_business_sources_do_not_generate_mock_rows_or_hardcoded_symbols(): void
    {
        $sources = $this->frontBusinessSources();
        $forbiddenMarkers = [
            'mockWhenEmpty',
            'data-mock-when-empty',
            'mockValue',
            'mockRows',
            'mockSummary',
            'renderMockData',
            'MOCK-',
            'demo_user_',
            'uploads/voucher/demo_',
        ];

        foreach ($sources as $path => $source) {
            foreach ($forbiddenMarkers as $marker) {
                $this->assertStringNotContainsString(
                    $marker,
                    $source,
                    'Front business source must not generate local mock data: ' . $path . ' [' . $marker . ']'
                );
            }

            $this->assertDoesNotMatchRegularExpression(
                '/[\[\(]\s*[\'\"]XAUUSD[\'\"]\s*,\s*[\'\"]EURUSD[\'\"]/i',
                $source,
                'Trade symbol options must come from /api/front/trade-symbols: ' . $path
            );
        }
    }

    public function test_visual_audit_fixture_is_testing_only_and_explicitly_enabled(): void
    {
        $fixture = file_get_contents(resource_path('views/partials/visual-audit-fixture.blade.php')) ?: '';

        $this->assertStringContainsString("app()->environment('testing')", $fixture);
        $this->assertStringContainsString("request()->query('visual_audit') === '1'", $fixture);
        $this->assertStringContainsString('/js/testing/visual-audit-fixture.js', $fixture);
    }

    /**
     * @return array<string, string>
     */
    private function frontBusinessSources(): array
    {
        $paths = [
            resource_path('front/layui'),
            resource_path('front/crmui'),
            public_path('js/apps/front/layui'),
        ];
        $files = [
            public_path('js/apps/crmui/front.js'),
        ];
        $sources = [];

        foreach ($paths as $path) {
            $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path));
            /** @var SplFileInfo $file */
            foreach ($iterator as $file) {
                if (!$file->isFile() || !in_array(strtolower($file->getExtension()), ['php', 'js'], true)) {
                    continue;
                }

                $files[] = $file->getPathname();
            }
        }

        foreach (array_unique($files) as $file) {
            $sources[str_replace(base_path() . DIRECTORY_SEPARATOR, '', $file)] = file_get_contents($file) ?: '';
        }

        return $sources;
    }
}
