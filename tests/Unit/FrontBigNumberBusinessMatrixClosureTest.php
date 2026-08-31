<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/17
 * Time: 20:45
 */

/**
 * FrontBigNumberBusinessMatrixClosureTest
 *
 * 文件功能：
 * - 验证大代理业务十条路由的证据矩阵：每条路由有完整证据，矩阵汇总由全部行派生。
 * - 输入：内存构造的服务对象、测试替身与必要的数据库夹具；输出：PHPUnit 断言结果。
 * - 明确不负责：不覆盖 HTTP 路由与控制器接线（由 Feature 契约测试锁定）。
 */

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class FrontBigNumberBusinessMatrixClosureTest extends TestCase
{
    /**
     * 前台大代理业务数据证据组的键名。按它从核验矩阵 JSON（storage/app/audits/旧项目模块逻辑迁移核验矩阵.json）
     * 筛选本模块行并断言每行 verification_group 与之一致；值不匹配即门禁失败，防止证据混组。
     */
    private const VERIFICATION_GROUP = 'front_big_agent_business_data_2026_08_17';

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
     * 前台大代理业务模块的旧路由认领清单（十条：代理列表/持仓/开平仓各入口）：键为「HTTP 方法 旧URI」，
     * 值为矩阵认定的当前路由名。门禁逐条断言 verified、绑定与七维独立出证；路由增删必须同步维护。
     */
    private const EXPECTED_ROUTES = [
        'GET user/agents/proxy/list' => 'legacy_user_agents_proxy_list',
        'POST user/agents/proxy/proxySearch' => 'legacy_user_agents_proxy_search',
        'POST user/agents/proxy/proxySearchBySub' => 'legacy_user_agents_proxy_search_by_sub',
        'GET user/agents/position/summary' => 'legacy_user_agents_position_summary',
        'POST user/agents/position/positionSummarySearch' => 'legacy_user_agents_position_search',
        'POST user/agents/position/subAgentsListSearch' => 'legacy_user_agents_position_sub_search',
        'GET user/agents/open/order' => 'legacy_user_agents_open_order',
        'POST user/agents/open/openOrderSearch' => 'legacy_user_agents_open_order_search',
        'GET user/agents/close/order' => 'legacy_user_agents_close_order',
        'POST user/agents/close/closeOrderSearch' => 'legacy_user_agents_close_order_search',
    ];

    public function test_all_ten_big_agent_business_routes_have_complete_evidence(): void
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
            $this->assertSame($row['current_action'], $verification['current_route']['action'], $key);
            $this->assertNotEmpty($verification['test_evidence'], $key);

            foreach (self::REQUIRED_DIMENSIONS as $dimension) {
                $this->assertSame('passed', $verification['dimensions'][$dimension]['result'], $key . ' ' . $dimension);
                $this->assertNotEmpty($verification['dimensions'][$dimension]['evidence'], $key . ' ' . $dimension);
            }
        }
    }

    public function test_matrix_summary_is_derived_from_all_rows(): void
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
        $this->assertSame(476, $matrix['summary']['legacy_route_methods']);
        foreach ($counts as $state => $count) {
            $this->assertSame($count, $matrix['summary'][$state], $state);
        }
        $this->assertGreaterThanOrEqual(375, $counts['verified']);
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
