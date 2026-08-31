<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/17
 * Time: 19:49
 */

/**
 * FrontLegacyMethodPolicyClosureModuleTest
 *
 * 文件功能：
 * - 验证前台旧路由 HTTP 方法收敛：策略与实际注册路由一致，资金创建/异步通知仅 POST、同步返回页仅 GET、OTC 出金回调拒绝一切非 POST 方法。
 * - 输入：路由、控制器、Blade/JS、迁移等项目源码文本；输出：PHPUnit 断言结果。
 * - 明确不负责：不覆盖 HTTP 运行时业务流与 MT4 真实网关同步（由集成与功能测试锁定）。
 */

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * 前台旧路由 HTTP 方法收敛闭环测试。
 *
 * 旧项目大量使用 Route::any；新项目必须让资金创建、异步通知只接受 POST，
 * 同步返回页只接受 GET，并保证审计策略与 Laravel 实际注册方法完全一致。
 */
class FrontLegacyMethodPolicyClosureModuleTest extends TestCase
{
    public function test_legacy_method_policy_matches_registered_front_routes(): void
    {
        $policyPath = base_path('docs/audits/legacy-route-method-policy.json');
        $policy = json_decode((string) file_get_contents($policyPath), true);

        $this->assertIsArray($policy);
        $this->assertCount(20, $policy);

        $routesByUri = [];
        foreach (Route::getRoutes() as $route) {
            $routesByUri[$route->uri()] = array_values(array_diff($route->methods(), ['HEAD']));
        }

        foreach ($policy as $uri => $decision) {
            $this->assertArrayHasKey($uri, $routesByUri, $uri . ' 兼容路由未注册。');

            $actual = $routesByUri[$uri];
            $expected = $decision['accepted_current_methods'] ?? [];
            sort($actual);
            sort($expected);

            $this->assertSame($expected, $actual, $uri . ' 的审计策略与实际 HTTP 方法不一致。');
            $this->assertNotSame('', trim((string) ($decision['reason'] ?? '')), $uri . ' 缺少安全收敛原因。');
        }
    }

    public function test_fund_creation_notify_and_return_routes_use_safe_methods(): void
    {
        $postOnly = [
            'user/deposit_request',
            'user/deposit_request_otc',
            'user/deposit_notfiy',
            'user/deposit_notfiy2',
            'user/deposit_notfiy_otc',
            'user/deposit_tigerpay_notify',
            'user/deposit_wppay_notify',
            'user/deposit_exlink_bbnotify',
            'user/deposit_exlink_fbnotify',
            'user/deposit_btb_notify',
            'user/deposit_passto_notify',
            'user/deposit_switch_notify',
            'user/withdraw_notfiy_otc',
            'user/withdraw_verify_otc',
        ];
        $getOnly = [
            'user/deposit_return',
            'user/deposit_return2',
            'user/deposit_wppay_return',
            'user/deposit_exlink_bbreturn',
            'user/deposit_exlink_fbreturn',
            'user/deposit_btb_return',
        ];

        foreach (Route::getRoutes() as $route) {
            if (in_array($route->uri(), $postOnly, true)) {
                $this->assertSame(['POST'], array_values(array_diff($route->methods(), ['HEAD'])));
            }
            if (in_array($route->uri(), $getOnly, true)) {
                $this->assertSame(['GET'], array_values(array_diff($route->methods(), ['HEAD'])));
            }
        }
    }

    public function test_legacy_otc_withdraw_callbacks_reject_every_non_post_method(): void
    {
        foreach (['/user/withdraw_notfiy_otc', '/user/withdraw_verify_otc'] as $uri) {
            foreach (['GET', 'PUT', 'PATCH', 'DELETE'] as $method) {
                $this->call($method, $uri)
                    ->assertStatus(405)
                    ->assertHeader('allow', 'POST');
            }
        }
    }
}
