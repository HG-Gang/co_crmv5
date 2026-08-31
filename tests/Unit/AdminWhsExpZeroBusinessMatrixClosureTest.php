<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/21
 * Time: 21:53
 */

/**
 * AdminWhsExpZeroBusinessMatrixClosureTest
 *
 * 文件功能：
 * - 验证仓位清零业务五条路由的证据矩阵：每条路由有独立完整证据、损坏的 whstest 路由确认为失败关闭维护态、全局矩阵汇总一致。
 * - 输入：内存构造的服务对象、测试替身与必要的数据库夹具；输出：PHPUnit 断言结果。
 * - 明确不负责：不覆盖 HTTP 路由与控制器接线（由 Feature 契约测试锁定）。
 */

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class AdminWhsExpZeroBusinessMatrixClosureTest extends TestCase
{
    /**
     * 兑换率清零（whs/exp zero）业务路由的证据组键名。按它从核验矩阵 JSON
     * （storage/app/audits/旧项目模块逻辑迁移核验矩阵.json）筛选业务行并断言
     * verification_group 与之一致；与 MAINTENANCE_GROUP 的差异：本组承载真实业务入口出证。
     */
    private const BUSINESS_GROUP = 'admin_whs_exp_zero_business_2026_08_19';

    /**
     * 前台停用维护页测试条目的证据组键名。与 BUSINESS_GROUP 同族但语义不同：
     * 该组只收录“功能停用/维护占位”类条目，不允许混入业务路由出证，防止把维护页当成已迁移业务。
     */
    private const MAINTENANCE_GROUP = 'front_disabled_maintenance_test_entries_2026_08_16';

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
     * 兑换率清零模块的旧路由认领清单（五条）：键为「HTTP 方法 旧URI」，值为矩阵认定的当前路由名。
     * 门禁逐条断言 verified、绑定与七维独立出证；路由增删必须同步维护，否则漏出证或误报。
     */
    private const EXPECTED_BUSINESS_ROUTES = [
        'GET index/admin/order/whs_exp_zero_list' => 'legacy_admin_eae3d77c30d94f23',
        'POST index/admin/order/oneKeySearch' => 'legacy_admin_2931bbc65230ff5c',
        'POST index/admin/order/oneKeyZero' => 'legacy_admin_b38f4008344527aa',
        'POST index/admin/order/whsExpZeroListSearch' => 'legacy_admin_e4f42464596e155f',
        'POST index/admin/order/whsExpZeroListSearchV2' => 'legacy_admin_75049d7eb4a95a0d',
    ];

    public function test_all_five_whs_business_routes_have_independent_complete_evidence(): void
    {
        $rows = $this->rowsByRouteKey($this->readMatrix()['rows']);

        foreach (self::EXPECTED_BUSINESS_ROUTES as $routeKey => $currentName) {
            $this->assertArrayHasKey($routeKey, $rows);
            $row = $rows[$routeKey];

            $this->assertSame('verified', $row['evidence_state'], $routeKey);
            $this->assertSame(self::BUSINESS_GROUP, $row['verification_group'], $routeKey);
            $this->assertSame($currentName, $row['current_name'], $routeKey);

            $verification = $row['verification'];
            $this->assertSame('verified', $verification['state'], $routeKey);
            $this->assertSame($currentName, $verification['current_route']['name'], $routeKey);
            $this->assertSame(
                'App\\Http\\Controllers\\Admin\\LegacyAdminController@handle',
                $verification['current_route']['action'],
                $routeKey
            );

            foreach (self::REQUIRED_DIMENSIONS as $dimension) {
                $this->assertSame('passed', $verification['dimensions'][$dimension]['result'], $routeKey . ' ' . $dimension);
                $this->assertNotEmpty($verification['dimensions'][$dimension]['evidence'], $routeKey . ' ' . $dimension);
                $this->assertTrue(
                    $this->dimensionEvidenceContainsRouteKey(
                        $verification['dimensions'][$dimension]['evidence'],
                        $routeKey
                    ),
                    $routeKey . ' ' . $dimension . ' 缺少独立方法级说明。'
                );
            }

            $this->assertTrue(
                $this->hasMethodLevelTestEvidence($verification['test_evidence'], $routeKey),
                $routeKey . ' 缺少方法级自动化测试证据。'
            );
        }
    }

    public function test_broken_whstest_route_is_verified_as_fail_closed_maintenance(): void
    {
        $routeKey = 'GET whstest';
        $rows = $this->rowsByRouteKey($this->readMatrix()['rows']);

        $this->assertArrayHasKey($routeKey, $rows);
        $row = $rows[$routeKey];
        $this->assertSame('verified', $row['evidence_state']);
        $this->assertSame(self::MAINTENANCE_GROUP, $row['verification_group']);
        $this->assertSame('legacy_whs_test', $row['current_name']);
        $this->assertSame('verified', $row['verification']['state']);
        $this->assertSame('legacy_whs_test', $row['verification']['current_route']['name']);
        $this->assertSame(
            'App\\Http\\Controllers\\Front\\LegacyMaintenanceController@whsTest',
            $row['verification']['current_route']['action']
        );
        $this->assertTrue($this->hasMethodLevelTestEvidence($row['verification']['test_evidence'], $routeKey));
    }

    public function test_global_matrix_summary_reflects_the_whs_closure(): void
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
        $this->assertGreaterThanOrEqual(428, $counts['verified']);
    }

    /** @param array<int, array<string, mixed>> $rows
     *  @return array<string, array<string, mixed>>
     */
    private function rowsByRouteKey(array $rows): array
    {
        $indexed = [];

        foreach ($rows as $row) {
            $key = strtoupper((string) $row['legacy_method']) . ' ' . ltrim((string) $row['legacy_uri'], '/');
            $indexed[$key] = $row;
        }

        return $indexed;
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
