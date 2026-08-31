<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/02
 * Time: 02:10
 */

/**
 * 旧项目路由清单比对服务单元测试。
 *
 * 文件功能：
 * - 校验 LegacyRouteInventory::compare 对旧路由与当前路由输出 matched / missing_methods / missing_uri 等比对结果。
 * - 校验仅显式、精确的 HTTP 方法限制策略才会被采纳为 intentional_method_restriction。
 *
 * 适用场景：
 * - 改动旧路由比对逻辑或方法限制策略解析后回归。
 *
 * 入参例子：
 * - 旧路由：['methods' => ['POST'], 'uri' => 'user/signIn', ...]。
 * - 当前路由缺少 POST => 状态 missing_methods，缺失方法 ['POST']。
 * - 当前路由缺失该 URI => 状态 missing_uri。
 *
 * 返回值：断言通过表示路由比对状态、缺失方法与决策理由完全一致。
 *
 * 异常或失败场景：
 * - 方法限制策略不精确（如接受未显式列出的方法）或比对状态误判时失败。
 */
namespace Tests\Unit;

use App\Support\LegacyRouteInventory;
use PHPUnit\Framework\TestCase;

class LegacyRouteInventoryTest extends TestCase
{
    /**
     * 校验 compare 能报告 matched、missing_uri 与 missing_methods 三类状态。
     *
     * @return void 断言通过不返回值。
     */
    public function test_compare_reports_matched_missing_uri_and_missing_methods(): void
    {
        $this->assertTrue(class_exists(LegacyRouteInventory::class), 'Legacy route inventory service is missing.');

        $legacy = [
            ['methods' => ['GET', 'HEAD'], 'uri' => 'user/login', 'name' => 'login', 'action' => 'OldLogin@login'],
            ['methods' => ['POST'], 'uri' => 'user/signIn', 'name' => 'signIn', 'action' => 'OldLogin@signIn'],
            ['methods' => ['GET', 'HEAD', 'POST', 'PUT', 'PATCH', 'DELETE'], 'uri' => 'user/deposit_request', 'name' => null, 'action' => 'OldDeposit@submit'],
            ['methods' => ['POST'], 'uri' => 'user/missing', 'name' => null, 'action' => 'OldMissing@store'],
        ];

        $current = [
            ['methods' => ['GET', 'HEAD'], 'uri' => 'user/login', 'name' => 'legacy_login', 'action' => 'NewLogin@show'],
            ['methods' => ['GET', 'HEAD'], 'uri' => 'user/signIn', 'name' => 'legacy_sign_in', 'action' => 'NewLogin@signIn'],
            ['methods' => ['GET', 'HEAD', 'POST', 'PUT', 'PATCH', 'DELETE'], 'uri' => 'user/deposit_request', 'name' => 'legacy_deposit', 'action' => 'NewDeposit@submit'],
        ];

        $rows = (new LegacyRouteInventory())->compare($legacy, $current);

        $this->assertSame('matched', $rows[0]['status']);
        $this->assertSame('NewLogin@show', $rows[0]['current_action']);
        $this->assertSame('missing_methods', $rows[1]['status']);
        $this->assertSame(['POST'], $rows[1]['missing_methods']);
        $this->assertSame('matched', $rows[2]['status']);
        $this->assertSame('missing_uri', $rows[3]['status']);
    }

    /**
     * 校验 compare 只接受显式且精确的方法限制策略，未覆盖的策略按 missing_methods 处理。
     *
     * @return void 断言通过不返回值。
     */
    public function test_compare_accepts_only_explicit_and_exact_method_restriction_policies(): void
    {
        $method = new \ReflectionMethod(LegacyRouteInventory::class, 'compare');
        $this->assertGreaterThanOrEqual(3, $method->getNumberOfParameters(), 'Method policy support is missing.');

        $legacy = [
            ['methods' => ['GET', 'HEAD', 'POST', 'PUT', 'PATCH', 'DELETE'], 'uri' => 'user/deposit_request', 'name' => null, 'action' => 'OldDeposit@submit'],
            ['methods' => ['GET', 'HEAD', 'POST', 'PUT', 'PATCH', 'DELETE'], 'uri' => 'user/deposit_unapproved', 'name' => null, 'action' => 'OldDeposit@unapproved'],
        ];
        $current = [
            ['methods' => ['GET', 'HEAD', 'POST'], 'uri' => 'user/deposit_request', 'name' => 'legacy_deposit', 'action' => 'NewDeposit@submit'],
            ['methods' => ['GET', 'HEAD', 'POST'], 'uri' => 'user/deposit_unapproved', 'name' => 'legacy_unapproved', 'action' => 'NewDeposit@unapproved'],
        ];
        $policies = [
            'user/deposit_request' => [
                'accepted_current_methods' => ['GET', 'POST'],
                'reason' => 'The legacy any route exposed unused mutating verbs.',
            ],
        ];

        $rows = (new LegacyRouteInventory())->compare($legacy, $current, $policies);

        $this->assertSame('intentional_method_restriction', $rows[0]['status']);
        $this->assertSame(['DELETE', 'PATCH', 'PUT'], $rows[0]['missing_methods']);
        $this->assertSame($policies['user/deposit_request']['reason'], $rows[0]['decision_reason']);
        $this->assertSame('missing_methods', $rows[1]['status']);
        $this->assertNull($rows[1]['decision_reason']);
    }
}
