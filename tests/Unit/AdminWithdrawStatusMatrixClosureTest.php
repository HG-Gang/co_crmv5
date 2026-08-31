<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/21
 * Time: 21:53
 */

/**
 * AdminWithdrawStatusMatrixClosureTest
 *
 * 文件功能：
 * - 验证出金状态相关十二条路由的证据矩阵：每条路由有独立完整证据，全局矩阵汇总与出金状态闭环一致。
 * - 输入：内存构造的服务对象、测试替身与必要的数据库夹具；输出：PHPUnit 断言结果。
 * - 明确不负责：不覆盖 HTTP 路由与控制器接线（由 Feature 契约测试锁定）。
 */

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class AdminWithdrawStatusMatrixClosureTest extends TestCase
{
    /**
     * 出金状态矩阵证据组的键名。按它从核验矩阵 JSON（storage/app/audits/旧项目模块逻辑迁移核验矩阵.json）
     * 筛选本模块行并断言每行 verification_group 与之一致；值不匹配即门禁失败，防止证据混组。
     */
    private const VERIFICATION_GROUP = 'admin_withdraw_status_business_2026_08_17';

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
     * 出金状态矩阵（待处理/处理中/已完成/失败四态各三条）的旧路由认领清单（十二条）：
     * 键为「HTTP 方法 旧URI」，值为矩阵认定的当前路由名。门禁逐条断言 verified、绑定与七维独立出证；
     * 路由增删必须同步维护，否则漏出证或误报。
     */
    private const EXPECTED_ROUTES = [
        'GET index/admin/withdraw/pending' => 'legacy_admin_358028a2f9cb4e45',
        'POST index/admin/withdraw/pendingSearch' => 'legacy_admin_5269b1fad284b3ad',
        'POST index/admin/withdraw/pendingExport' => 'legacy_admin_fefb71576f8e611e',
        'GET index/admin/withdraw/processing' => 'legacy_admin_246fa87280b166f1',
        'POST index/admin/withdraw/processingSearch' => 'legacy_admin_e0b482fde40b40bc',
        'POST index/admin/withdraw/processingExport' => 'legacy_admin_678e9e7fd0ac873a',
        'GET index/admin/withdraw/completed' => 'legacy_admin_e56d812229eddf32',
        'POST index/admin/withdraw/completedSearch' => 'legacy_admin_1e10361fc02ab375',
        'POST index/admin/withdraw/completedExport' => 'legacy_admin_dd7af3546af3a53e',
        'GET index/admin/withdraw/failed' => 'legacy_admin_f97b3c545c41c2dd',
        'POST index/admin/withdraw/failedSearch' => 'legacy_admin_26024800fff01d67',
        'POST index/admin/withdraw/failedExport' => 'legacy_admin_9fcc2e829e29a151',
    ];

    public function test_all_twelve_withdraw_status_routes_have_independent_complete_evidence(): void
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
                    $key . ' ' . $dimension . ' 缺少该方法级路由的独立说明。'
                );
            }

            $this->assertTrue(
                $this->hasMethodLevelTestEvidence($verification['test_evidence'], $key),
                $key . ' 缺少方法级自动化测试证据。'
            );
        }
    }

    public function test_global_matrix_summary_reflects_the_withdraw_status_closure(): void
    {
        $matrix = $this->readMatrix();
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
        $this->assertGreaterThanOrEqual(406, $counts['verified']);
    }

    /**
     * @param array<int, array<string, mixed>> $testEvidence
     */
    private function hasMethodLevelTestEvidence(array $testEvidence, string $routeKey): bool
    {
        foreach ($testEvidence as $evidence) {
            $routeKeys = $evidence['route_keys'] ?? [];
            $methods = $evidence['methods'] ?? [];
            if (in_array($routeKey, $routeKeys, true) && ! empty($methods)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<int, mixed> $evidence
     */
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
