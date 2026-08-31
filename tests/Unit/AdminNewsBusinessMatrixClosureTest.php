<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/21
 * Time: 21:53
 */

/**
 * AdminNewsBusinessMatrixClosureTest
 *
 * 文件功能：
 * - 验证新闻模块七条路由的证据矩阵：每条路由有独立完整证据，全局矩阵汇总与新闻闭环一致。
 * - 输入：内存构造的服务对象、测试替身与必要的数据库夹具；输出：PHPUnit 断言结果。
 * - 明确不负责：不覆盖 HTTP 路由与控制器接线（由 Feature 契约测试锁定）。
 */

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class AdminNewsBusinessMatrixClosureTest extends TestCase
{
    /**
     * 新闻管理证据组的键名。按它从核验矩阵 JSON（storage/app/audits/旧项目模块逻辑迁移核验矩阵.json）
     * 筛选本模块行并断言每行 verification_group 与之一致；值不匹配即门禁失败，防止证据混组。
     */
    private const VERIFICATION_GROUP = 'admin_news_business_2026_08_19';

    /**
     * 七维核验维度全集（每条路由七维必须独立 passed，缺一即门禁失败）。维度差异：
     * legacy_behavior=旧项目行为还原；route_mapping=旧 URI 到当前路由的映射；backend_logic=后端业务逻辑；
     * frontend_contract=前端页面契约；auth_and_scope=鉴权与数据范围；validation_and_errors=校验与错误码；
     * automated_tests=自动化测试覆盖。
     */
    private const REQUIRED_DIMENSIONS = [
        'legacy_behavior',
        'route_mapping',
        'backend_logic',
        'frontend_contract',
        'auth_and_scope',
        'validation_and_errors',
        'automated_tests',
    ];

    /**
     * 新闻管理模块的旧路由认领清单（七条）：键为「HTTP 方法 旧URI」，值为矩阵认定的当前路由名。
     * 门禁逐条断言 verified、绑定与七维独立出证；路由增删必须同步维护，否则漏出证或误报。
     */
    private const EXPECTED_ROUTES = [
        'GET index/admin/news/news_list_browse' => 'legacy_admin_42198f261c2b7f86',
        'GET index/admin/news/news_add_browse' => 'legacy_admin_20a89ea83d396a4b',
        'GET index/admin/news/news_edit/{newsid}' => 'legacy_admin_5c19e6b264838c0d',
        'POST index/admin/news/newsListSearch' => 'legacy_admin_04376725fa561e32',
        'POST index/admin/news/news_save' => 'legacy_admin_f9c20955a37885a5',
        'POST index/admin/news/news_update' => 'legacy_admin_ddccb86035647d8a',
        'POST index/admin/news/del' => 'legacy_admin_5122f3da73c82b44',
    ];

    public function test_all_seven_news_routes_have_independent_complete_evidence(): void
    {
        $matrix = $this->readMatrix();
        $rows = [];

        foreach ($matrix['rows'] as $row) {
            $key = strtoupper((string) $row['legacy_method']) . ' ' . ltrim((string) $row['legacy_uri'], '/');
            if (array_key_exists($key, self::EXPECTED_ROUTES)) {
                $rows[$key] = $row;
            }
        }

        $expectedKeys = array_keys(self::EXPECTED_ROUTES);
        $actualKeys = array_keys($rows);
        sort($expectedKeys);
        sort($actualKeys);
        $this->assertSame($expectedKeys, $actualKeys);

        foreach (self::EXPECTED_ROUTES as $key => $currentName) {
            $row = $rows[$key];
            $this->assertSame('verified', $row['evidence_state'], $key);
            $this->assertSame(self::VERIFICATION_GROUP, $row['verification_group'], $key);
            $this->assertSame($currentName, $row['current_name'], $key);

            $verification = $row['verification'];
            $this->assertSame('verified', $verification['state'], $key);
            $this->assertSame($currentName, $verification['current_route']['name'], $key);
            $this->assertSame(
                'App\\Http\\Controllers\\Admin\\LegacyAdminController@handle',
                $verification['current_route']['action'],
                $key
            );

            foreach (self::REQUIRED_DIMENSIONS as $dimension) {
                $this->assertSame('passed', $verification['dimensions'][$dimension]['result'], $key . ' ' . $dimension);
                $this->assertNotEmpty($verification['dimensions'][$dimension]['evidence'], $key . ' ' . $dimension);
                $this->assertTrue(
                    $this->dimensionEvidenceContainsRouteKey(
                        $verification['dimensions'][$dimension]['evidence'],
                        $key
                    ),
                    $key . ' ' . $dimension . ' 缺少独立方法级说明。'
                );
            }

            $this->assertTrue(
                $this->hasMethodLevelTestEvidence($verification['test_evidence'], $key),
                $key . ' 缺少方法级自动化测试证据。'
            );
        }
    }

    public function test_global_matrix_summary_reflects_the_news_closure(): void
    {
        $matrix = $this->readMatrix();

        $this->assertCount(476, $matrix['rows']);
        $counts = [
            'verified' => 0,
            'needs_manual_business_review' => 0,
            'unresolved_legacy_source' => 0,
            'unmatched_current_route' => 0,
        ];
        foreach ($matrix['rows'] as $row) {
            $state = (string) $row['evidence_state'];
            if (array_key_exists($state, $counts)) {
                ++$counts[$state];
            }
        }

        $this->assertCount(476, $matrix['rows']);
        $this->assertSame(array_merge([
            'legacy_route_methods' => 476,
        ], $counts), $matrix['summary']);
        $this->assertGreaterThanOrEqual(422, $counts['verified']);
    }

    /** @param array<int, array<string, mixed>> $testEvidence */
    private function hasMethodLevelTestEvidence(array $testEvidence, string $routeKey): bool
    {
        foreach ($testEvidence as $evidence) {
            if (in_array($routeKey, $evidence['route_keys'] ?? [], true)
                && ! empty($evidence['methods'] ?? [])) {
                return true;
            }
        }

        return false;
    }

    /** @param array<int, mixed> $evidence */
    private function dimensionEvidenceContainsRouteKey(array $evidence, string $routeKey): bool
    {
        foreach ($evidence as $statement) {
            if (is_string($statement) && strpos($statement, $routeKey) !== false) {
                return true;
            }
        }

        return false;
    }

    /** @return array<string, mixed> */
    private function readMatrix(): array
    {
        $path = dirname(__DIR__, 2) . '/storage/app/audits/旧项目模块逻辑迁移核验矩阵.json';
        $contents = file_get_contents($path);
        $this->assertNotFalse($contents, '核验矩阵不可读：' . $path);

        $matrix = json_decode((string) $contents, true);
        $this->assertIsArray($matrix, '核验矩阵 JSON 无法解析。');

        return $matrix;
    }
}
