<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/17
 * Time: 21:42
 */

/**
 * AdminWithdrawAmountMatrixClosureTest
 *
 * 文件功能：
 * - 验证出金金额相关十九条路由的证据矩阵：每条路由有独立完整证据，全局矩阵汇总与出金金额闭环一致。
 * - 输入：内存构造的服务对象、测试替身与必要的数据库夹具；输出：PHPUnit 断言结果。
 * - 明确不负责：不覆盖 HTTP 路由与控制器接线（由 Feature 契约测试锁定）。
 */

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class AdminWithdrawAmountMatrixClosureTest extends TestCase
{
    /**
     * 七维核验维度全集（每条路由七维必须独立 passed，缺一即门禁失败）。维度差异：
     * legacy_behavior=旧项目行为还原；route_mapping=旧 URI 到当前路由的映射；backend_logic=后端业务逻辑；
     * frontend_contract=前端页面契约；auth_and_scope=鉴权与数据范围；validation_and_errors=校验与错误码；
     * automated_tests=自动化测试覆盖。本测试不绑定单一证据组，按路由键直接检索矩阵行。
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
     * 出金金额/OTC 订单模块的旧路由认领清单（含 5 个 admin 路由方法与两类 OTC 通知路由的
     * 全部 HTTP 动词变体）：键为「HTTP 方法 旧URI」，值为矩阵认定的当前路由名。
     * 门禁逐条断言 verified、绑定与七维独立出证；路由增删必须同步维护，否则漏出证或误报。
     */
    private const EXPECTED_ROUTES = [
        'POST index/admin/amount/generate_OTCorder' => 'legacy_admin_ba28ed95c65914b9',
        'GET index/admin/amount/orderId_detail/{orderId}' => 'legacy_admin_3b9f3962d9ab93c9',
        'POST index/admin/amount/order_status' => 'legacy_admin_ef3cbcfcb81e5772',
        'POST index/admin/amount/order_status_OTC' => 'legacy_admin_3c7ee54f34c97136',
        'POST index/admin/amount/withdrawApplySearch' => 'legacy_admin_c8b234b5c311eb84',
        'POST index/admin/amount/withdrawApplySearchV2' => 'legacy_admin_9d0afc74a9b6d78f',
        'POST index/admin/amount/withdrawExport' => 'legacy_admin_59e9650cf006d3ac',
        'GET index/admin/amount/withdraw_apply' => 'legacy_admin_07244e864467989d',
        'GET index/admin/amount/withdraw_downloadfile/{file}/{role}' => 'legacy_admin_0908abf24c87e603',
        'DELETE user/withdraw_notfiy_otc' => 'legacy_user_withdraw_notify_otc',
        'GET user/withdraw_notfiy_otc' => 'legacy_user_withdraw_notify_otc',
        'PATCH user/withdraw_notfiy_otc' => 'legacy_user_withdraw_notify_otc',
        'POST user/withdraw_notfiy_otc' => 'legacy_user_withdraw_notify_otc',
        'PUT user/withdraw_notfiy_otc' => 'legacy_user_withdraw_notify_otc',
        'DELETE user/withdraw_verify_otc' => 'legacy_user_withdraw_verify_otc',
        'GET user/withdraw_verify_otc' => 'legacy_user_withdraw_verify_otc',
        'PATCH user/withdraw_verify_otc' => 'legacy_user_withdraw_verify_otc',
        'POST user/withdraw_verify_otc' => 'legacy_user_withdraw_verify_otc',
        'PUT user/withdraw_verify_otc' => 'legacy_user_withdraw_verify_otc',
    ];

    public function test_all_nineteen_withdraw_amount_routes_have_independent_complete_evidence(): void
    {
        $matrix = $this->readMatrix();
        $rows = [];

        foreach ($matrix['rows'] as $row) {
            $key = strtoupper((string) $row['legacy_method']) . ' ' . ltrim((string) $row['legacy_uri'], '/');
            if (array_key_exists($key, self::EXPECTED_ROUTES)) {
                $rows[$key] = $row;
            }
        }

        $this->assertSame(array_keys(self::EXPECTED_ROUTES), array_keys($rows));

        $groups = [];
        foreach (self::EXPECTED_ROUTES as $key => $currentName) {
            $row = $rows[$key];
            $this->assertSame('verified', $row['evidence_state'], $key);
            $this->assertSame($currentName, $row['current_name'], $key);
            $this->assertNotSame('', $row['verification_group'], $key);
            $groups[] = $row['verification_group'];

            $verification = $row['verification'];
            $this->assertSame('verified', $verification['state'], $key);
            $this->assertSame($currentName, $verification['current_route']['name'], $key);
            $this->assertNotEmpty($verification['test_evidence'], $key);
            foreach (self::REQUIRED_DIMENSIONS as $dimension) {
                $this->assertSame('passed', $verification['dimensions'][$dimension]['result'], $key . ' ' . $dimension);
                $this->assertNotEmpty($verification['dimensions'][$dimension]['evidence'], $key . ' ' . $dimension);
            }
        }

        $this->assertCount(19, array_unique($groups), '每个 HTTP 方法必须拥有独立核验证据组。');
    }

    public function test_global_matrix_summary_reflects_the_withdraw_amount_closure(): void
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
        $this->assertGreaterThanOrEqual(384, $counts['verified']);
    }

    /**
     * @return array<string, mixed>
     */
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
