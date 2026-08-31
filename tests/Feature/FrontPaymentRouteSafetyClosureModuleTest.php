<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/02
 * Time: 01:47
 */

/**
 * 前台支付路由安全闭环测试。
 *
 * 文件功能：
 * - 验证遗留订单创建路由（/user/deposit_request、/user/deposit_request_otc）仅允许 POST。
 * - 验证遗留通知路由仅允许 POST、返回路由仅允许 GET。
 * - 验证未签名或未配置渠道的回调不能改变订单状态。
 * - 验证未知渠道回调被拒绝，CSRF 白名单精确声明（无通配符），
 *   且回调控制器不信任原始 payload 的 status 字段。
 *
 * 适用场景：
 * - 前台支付路由与回调的安全回归测试，防止方法滥用、CSRF 通配与状态伪造。
 *
 * 入参例子：
 * - POST /api/front/payment/notify/gateway-test：local_order_no、status。
 * - POST /user/deposit_tigerpay_notify：local_order_no、status。
 *
 * 返回值：
 * - 方法不符返回 405；未签名/未知渠道回调返回 404/422 且订单状态保持 01。
 *
 * 异常或失败场景：
 * - 未签名或未配置渠道回调、未知渠道、CSRF 白名单通配、状态字段信任均被拒绝。
 */

namespace Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class FrontPaymentRouteSafetyClosureModuleTest extends TestCase
{
    use DatabaseTransactions;

    /**
     * 夹具订单的本地订单号。验证支付路由对他人订单号的拒绝与自身的正常访问。
     * @var string
     */
    private $orderNo;

    protected function setUp(): void
    {
        parent::setUp();
        $this->orderNo = 'PAY-SAFETY-' . random_int(100000, 999999);
        DB::table('deposit_records')->insert([
            'user_id' => 991001,
            'user_name' => 'Payment Safety User',
            'mt4_ticket' => 0,
            'amount' => 100.00,
            'actual_amount' => 100.00,
            'exchange_rate' => 1,
            'channel_name' => 'Safety Gateway',
            'channel_order_no' => '',
            'local_order_no' => $this->orderNo,
            'status' => '01',
            'payment_time' => null,
            'remarks' => 'payment route safety fixture',
            'created_by' => 'test',
            'updated_by' => '',
            'created_at' => time(),
            'updated_at' => time(),
            'deleted_at' => null,
        ]);
    }

    protected function tearDown(): void
    {
        DB::table('deposit_records')->where('local_order_no', $this->orderNo)->delete();
        parent::tearDown();
    }

    // 验证遗留订单创建路由仅允许 POST。
    public function test_legacy_order_creation_is_post_only(): void
    {
        $this->get('/user/deposit_request')->assertStatus(405);
        $this->get('/user/deposit_request_otc')->assertStatus(405);

        foreach (['legacy_user_deposit_request', 'legacy_user_deposit_request_otc'] as $name) {
            $route = Route::getRoutes()->getByName($name);
            $this->assertSame(['POST'], array_values(array_diff($route->methods(), ['HEAD'])));
        }
    }

    // 验证遗留通知仅允许 POST 而返回仅允许 GET。
    public function test_legacy_notify_is_post_only_and_return_is_get_only(): void
    {
        foreach (['/user/deposit_notfiy', '/user/deposit_tigerpay_notify', '/user/deposit_passto_notify'] as $path) {
            $this->get($path)->assertStatus(405);
        }
        foreach ([
            '/user/deposit_return',
            '/user/deposit_wppay_return',
            '/user/deposit_exlink_bbreturn',
            '/user/deposit_exlink_fbreturn',
            '/user/deposit_btb_return',
        ] as $path) {
            $this->post($path)->assertStatus(405);
            $this->get($path)->assertRedirect();
        }
    }

    // 验证未签名或未配置渠道的回调不能改变订单状态。
    public function test_unsigned_or_unconfigured_callback_cannot_change_order_status(): void
    {
        $response = $this->postJson('/api/front/payment/notify/gateway-test', [
            'local_order_no' => $this->orderNo,
            'status' => 'success',
        ]);

        $this->assertContains($response->status(), [404, 422]);
        $this->assertSame('01', (string) DB::table('deposit_records')->where('local_order_no', $this->orderNo)->value('status'));
        $this->assertNull(DB::table('deposit_records')->where('local_order_no', $this->orderNo)->value('payment_time'));

        $legacy = $this->post('/user/deposit_tigerpay_notify', [
            'local_order_no' => $this->orderNo,
            'status' => 'success',
        ]);
        $legacy->assertStatus(422);
        $this->assertSame('01', (string) DB::table('deposit_records')->where('local_order_no', $this->orderNo)->value('status'));
    }

    // 验证未知渠道被拒绝且 CSRF 白名单为精确声明、控制器不信任原始 status。
    public function test_unknown_gateway_is_rejected_and_exact_notify_csrf_exclusions_are_declared(): void
    {
        $this->postJson('/api/front/payment/notify/not-a-real-gateway', [])->assertNotFound();

        $source = file_get_contents(app_path('Http/Middleware/VerifyCsrfToken.php')) ?: '';
        foreach ([
            'user/deposit_notfiy',
            'user/deposit_tigerpay_notify',
            'user/deposit_wppay_notify',
            'user/deposit_exlink_bbnotify',
            'user/deposit_exlink_fbnotify',
            'user/deposit_btb_notify',
            'user/deposit_passto_notify',
            'user/deposit_switch_notify',
            'user/deposit_notfiy_otc',
            'user/withdraw_notfiy_otc',
            'user/withdraw_verify_otc',
        ] as $uri) {
            $this->assertStringContainsString("'{$uri}'", $source);
        }
        $this->assertStringNotContainsString("'user/*'", $source);
        $this->assertStringNotContainsString("'user/deposit_*'", $source);

        $controller = file_get_contents(app_path('Http/Controllers/Front/PaymentNotifyController.php')) ?: '';
        $this->assertStringNotContainsString("'payload' => \$request->all()", $controller);
        $this->assertStringNotContainsString("'status' => (string) \$request->input", $controller);
        $this->assertStringContainsString("'status' => 'pending'", $controller);
    }
}
