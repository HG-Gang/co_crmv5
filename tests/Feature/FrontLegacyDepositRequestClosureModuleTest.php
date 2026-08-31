<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/01
 * Time: 10:43
 */

/**
 * FrontLegacyDepositRequestClosureModuleTest
 *
 * 文件功能：
 * - 验证旧前台入金申请闭环：旧别名字段建单并忽略伪造 user_id、OTC 通道别名、缺幂等键/金额/未知渠道拒绝、金额超渠道限额拒绝、缺网关适配器失败关闭。
 * - 输入：HTTP 请求与事务回滚的数据库夹具；输出：PHPUnit 断言结果。
 * - 明确不负责：不覆盖真实 MT4 网关与线上支付/出金通道（由网关契约测试锁定）。
 */

declare(strict_types=1);

namespace Tests\Feature;

use App\Constants\ResponseCode;
use App\Contracts\PaymentGatewayAdapter;
use App\Http\Middleware\JwtAuthMiddleware;
use App\Http\Middleware\SingleSignOn;
use App\Models\UserLogin;
use App\Services\Payment\PaymentCallback;
use App\Services\Payment\PaymentGatewayRegistry;
use App\Services\Payment\PaymentOrderResult;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

/**
 * 旧前台入金申请兼容入口闭环测试。
 *
 * 测试目标：
 * - user/deposit_request 旧入金申请入口必须识别旧字段别名并完成建单。
 * - user/deposit_request_otc 旧 OTC 入金申请入口必须与普通入口同链路建单。
 * - 两个入口都必须忽略伪造 user_id，以登录态为准；缺幂等键、缺金额、通道异常必须拒绝建单。
 *
 * 闭环说明：
 * - deposit_amt/deposit_amt_usd 是旧页面金额字段，pay_channel/passageway 是旧通道字段。
 * - Idempotency-Key 是建单幂等键，重复提交同一密钥只返回同一订单，防止重复扣款。
 * - 支付通道必须在 payment_channels 启用且注册了可用的支付网关适配器，否则失败关闭。
 */
class FrontLegacyDepositRequestClosureModuleTest extends TestCase
{
    use DatabaseTransactions;

    /**
     * 旧版入金请求用例的渠道码（legacy-fake-gateway）。夹具写入 payment_channels 并绑定 ADAPTER 适配器。
     * @var string
     */
    private const GATEWAY = 'legacy-fake-gateway';

    /**
     * 渠道绑定的适配器注册名（legacy-fake-adapter），指向 LegacyFakeProviderAdapter 替身。
     * @var string
     */
    private const ADAPTER = 'legacy-fake-adapter';

    /**
     * 用例固定幂等键。验证旧版请求别名参数下订单仍按幂等键去重；重复请求复用同一订单。
     * @var string
     */
    private const IDEMPOTENCY_KEY = 'front-legacy-deposit-request-closure';

    public function test_legacy_deposit_request_creates_order_with_legacy_aliases_and_ignores_spoofed_user_id(): void
    {
        $viewerId = 412370100;
        $otherId = 412370101;
        $viewerEmail = 'front-legacy-deposit-' . $viewerId . '@example.test';
        $otherEmail = 'front-legacy-deposit-' . $otherId . '@example.test';

        $this->deleteFixtureRows([$viewerId, $otherId], [$viewerEmail, $otherEmail]);
        $this->allowDepositsForTest();
        $this->registerFakeGateway();
        $this->insertPaymentChannel();
        $this->insertUserInfo($viewerId, 'legacy-deposit-viewer', $viewerEmail);
        $this->insertUserInfo($otherId, 'legacy-deposit-other', $otherEmail);

        $login = UserLogin::where('user_id', $viewerId)->firstOrFail();
        $response = $this->withoutMiddleware([JwtAuthMiddleware::class, SingleSignOn::class])
            ->actingAs($login, 'user')
            ->withHeader('Idempotency-Key', self::IDEMPOTENCY_KEY . '-a')
            ->postJson('/user/deposit_request', [
                'deposit_amt_usd' => '88.00',
                'pay_channel' => self::GATEWAY,
                'user_id' => $otherId,
                'userId' => $otherId,
            ]);

        $response->assertOk()
            ->assertJsonPath('code', ResponseCode::CREATED)
            ->assertJsonPath('data.channel', self::GATEWAY);
        $orderNo = (string) $response->json('data.order_no');
        $this->assertNotSame('', $orderNo);
        $this->assertStringContainsString('https://fake-provider.example.test/checkout/', (string) $response->json('data.payment_url'));
        $this->assertDatabaseHas('deposit_records', [
            'user_id' => $viewerId,
            'amount' => 88,
            'channel_name' => 'Legacy Fake Gateway',
            'local_order_no' => $orderNo,
            'gateway_code' => self::GATEWAY,
            'idempotency_key' => self::IDEMPOTENCY_KEY . '-a',
        ]);
        $this->assertDatabaseMissing('deposit_records', ['user_id' => $otherId]);
        $this->assertStringNotContainsString((string) $otherId, $response->getContent());
    }

    public function test_legacy_deposit_request_otc_creates_order_with_passageway_alias(): void
    {
        $viewerId = 412370200;
        $viewerEmail = 'front-legacy-deposit-otc-' . $viewerId . '@example.test';

        $this->deleteFixtureRows([$viewerId], [$viewerEmail]);
        $this->allowDepositsForTest();
        $this->registerFakeGateway();
        $this->insertPaymentChannel();
        $this->insertUserInfo($viewerId, 'legacy-deposit-otc', $viewerEmail);

        $login = UserLogin::where('user_id', $viewerId)->firstOrFail();
        $response = $this->withoutMiddleware([JwtAuthMiddleware::class, SingleSignOn::class])
            ->actingAs($login, 'user')
            ->withHeader('Idempotency-Key', self::IDEMPOTENCY_KEY . '-otc')
            ->postJson('/user/deposit_request_otc', [
                'amount' => '66.00',
                'passageway' => self::GATEWAY,
            ]);

        $response->assertOk()
            ->assertJsonPath('code', ResponseCode::CREATED)
            ->assertJsonPath('data.channel', self::GATEWAY);
        $orderNo = (string) $response->json('data.order_no');
        $this->assertDatabaseHas('deposit_records', [
            'user_id' => $viewerId,
            'amount' => 66,
            'local_order_no' => $orderNo,
            'gateway_code' => self::GATEWAY,
            'idempotency_key' => self::IDEMPOTENCY_KEY . '-otc',
        ]);
    }

    public function test_legacy_deposit_request_rejects_missing_idempotency_key_before_creating_order(): void
    {
        $viewerId = 412370300;
        $viewerEmail = 'front-legacy-deposit-idem-' . $viewerId . '@example.test';

        $this->deleteFixtureRows([$viewerId], [$viewerEmail]);
        $this->allowDepositsForTest();
        $this->registerFakeGateway();
        $this->insertPaymentChannel();
        $this->insertUserInfo($viewerId, 'legacy-deposit-idem', $viewerEmail);

        $login = UserLogin::where('user_id', $viewerId)->firstOrFail();
        $response = $this->withoutMiddleware([JwtAuthMiddleware::class, SingleSignOn::class])
            ->actingAs($login, 'user')
            ->postJson('/user/deposit_request', [
                'amount' => '50.00',
                'channel' => self::GATEWAY,
            ]);

        $response->assertOk()
            ->assertJsonPath('code', ResponseCode::VALIDATION_FAILED);
        $this->assertDatabaseMissing('deposit_records', ['user_id' => $viewerId]);
    }

    public function test_legacy_deposit_request_rejects_missing_amount_and_unknown_channel(): void
    {
        $viewerId = 412370400;
        $viewerEmail = 'front-legacy-deposit-valid-' . $viewerId . '@example.test';

        $this->deleteFixtureRows([$viewerId], [$viewerEmail]);
        $this->allowDepositsForTest();
        $this->registerFakeGateway();
        $this->insertPaymentChannel();
        $this->insertUserInfo($viewerId, 'legacy-deposit-valid', $viewerEmail);

        $login = UserLogin::where('user_id', $viewerId)->firstOrFail();
        $acting = $this->withoutMiddleware([JwtAuthMiddleware::class, SingleSignOn::class])
            ->actingAs($login, 'user')
            ->withHeader('Idempotency-Key', self::IDEMPOTENCY_KEY . '-valid');

        $missingAmount = $acting->postJson('/user/deposit_request', ['channel' => self::GATEWAY]);
        $missingAmount->assertOk()
            ->assertJsonPath('code', ResponseCode::VALIDATION_FAILED);

        $unknownChannel = $acting->postJson('/user/deposit_request', [
            'amount' => '50.00',
            'channel' => 'no-such-channel',
        ]);
        $unknownChannel->assertOk()
            ->assertJsonPath('code', ResponseCode::OPERATION_NOT_ALLOWED);

        $this->assertDatabaseMissing('deposit_records', ['user_id' => $viewerId]);
    }

    public function test_legacy_deposit_request_rejects_amount_outside_channel_limits(): void
    {
        $viewerId = 412370500;
        $viewerEmail = 'front-legacy-deposit-limit-' . $viewerId . '@example.test';

        $this->deleteFixtureRows([$viewerId], [$viewerEmail]);
        $this->allowDepositsForTest();
        $this->registerFakeGateway();
        $this->insertPaymentChannel();
        $this->insertUserInfo($viewerId, 'legacy-deposit-limit', $viewerEmail);

        $login = UserLogin::where('user_id', $viewerId)->firstOrFail();
        $response = $this->withoutMiddleware([JwtAuthMiddleware::class, SingleSignOn::class])
            ->actingAs($login, 'user')
            ->withHeader('Idempotency-Key', self::IDEMPOTENCY_KEY . '-limit')
            ->postJson('/user/deposit_request', [
                'amount' => '5.00',
                'channel' => self::GATEWAY,
            ]);

        $response->assertOk()
            ->assertJsonPath('code', ResponseCode::VALIDATION_FAILED);
        $this->assertDatabaseMissing('deposit_records', ['user_id' => $viewerId]);
    }

    public function test_legacy_deposit_request_fails_closed_when_gateway_adapter_is_missing(): void
    {
        $viewerId = 412370600;
        $viewerEmail = 'front-legacy-deposit-no-adapter-' . $viewerId . '@example.test';

        $this->deleteFixtureRows([$viewerId], [$viewerEmail]);
        $this->allowDepositsForTest();
        $this->insertPaymentChannel();
        $this->insertUserInfo($viewerId, 'legacy-deposit-no-adapter', $viewerEmail);

        $login = UserLogin::where('user_id', $viewerId)->firstOrFail();
        $response = $this->withoutMiddleware([JwtAuthMiddleware::class, SingleSignOn::class])
            ->actingAs($login, 'user')
            ->withHeader('Idempotency-Key', self::IDEMPOTENCY_KEY . '-no-adapter')
            ->postJson('/user/deposit_request', [
                'amount' => '50.00',
                'channel' => self::GATEWAY,
            ]);

        $response->assertOk()
            ->assertJsonPath('code', ResponseCode::OPERATION_NOT_ALLOWED);
        $this->assertDatabaseMissing('deposit_records', ['user_id' => $viewerId]);
    }

    private function allowDepositsForTest(): void
    {
        foreach ([
            'deposit_enabled' => '1',
            'deposit_weekend_enabled' => '1',
            'deposit_start_time' => '',
            'deposit_end_time' => '',
            'deposit_min_amount' => '10',
            'deposit_max_amount' => '500000',
            'deposit_disabled_message' => 'Deposits are disabled',
        ] as $key => $value) {
            $now = time();
            DB::table('system_configs')->updateOrInsert(
                ['key' => $key],
                [
                    'value' => $value,
                    'group' => 'deposit',
                    'description' => 'Front legacy deposit request closure test fixture',
                    'created_at' => $now,
                    'updated_at' => $now,
                    'deleted_at' => null,
                ]
            );
        }
    }

    private function registerFakeGateway(): void
    {
        app(PaymentGatewayRegistry::class)->register(
            self::ADAPTER,
            new LegacyFakeProviderAdapter('front-legacy-closure-secret'),
            ['USD']
        );
    }

    private function insertPaymentChannel(): void
    {
        $now = time();

        DB::table('payment_channels')->insert([
            'name' => 'Legacy Fake Gateway',
            'channel_code' => self::GATEWAY,
            'exchange_rate' => '1.00000000',
            'is_enabled' => 1,
            'sort' => 370,
            'config' => json_encode([
                'adapter' => self::ADAPTER,
                'gateway_code' => self::GATEWAY,
                'merchant_id' => 'legacy-fake-merchant',
                'gateway_url' => 'https://fake-provider.example.test/create',
                'secret_reference' => 'env:LEGACY_FAKE_PROVIDER_SECRET',
                'currency' => 'USD',
                'amount_unit' => 'decimal',
                'notify_route' => 'front_api_payment_notify',
                'return_route' => 'front_api_payment_return',
                'min_amount' => '10.00',
                'max_amount' => '500000.00',
            ]),
            'created_at' => $now,
            'updated_at' => $now,
            'deleted_at' => null,
        ]);
    }

    private function insertUserInfo(int $userId, string $userName, string $email): void
    {
        $now = time();

        $loginId = DB::table('user_logins')->insertGetId([
            'user_id' => $userId,
            'email' => $email,
            'password' => Hash::make('password'),
            'account_type' => 2,
            'role_id' => 0,
            'is_enabled' => 1,
            'is_cancelled' => 0,
            'source_type' => 0,
            'jwt_token_id' => '',
            'last_login_ip' => '',
            'last_login_at' => null,
            'created_at' => $now,
            'updated_at' => $now,
            'deleted_at' => null,
        ]);

        DB::table('user_infos')->insert([
            'user_id' => $userId,
            'login_id' => $loginId,
            'user_name' => $userName,
            'phone' => '1392370' . substr((string) $userId, -4),
            'gender' => 1,
            'account_type' => 2,
            'parent_id' => 0,
            'family_tree' => '',
            'group_id' => 0,
            'level_id' => 0,
            'comm_rate' => 0,
            'auth_status' => 1,
            'total_funds' => 0,
            'used_margin' => 0,
            'avail_margin' => 0,
            'equity' => 0,
            'effective_credit' => 0,
            'risk_ratio' => 0,
            'leverage' => 100,
            'is_ecn' => 0,
            'is_deposit_allowed' => 0,
            'created_at' => $now,
            'updated_at' => $now,
            'deleted_at' => null,
        ]);
    }

    /**
     * @param array<int, int> $userIds
     * @param array<int, string> $emails
     */
    private function deleteFixtureRows(array $userIds, array $emails): void
    {
        DB::table('deposit_records')->where('idempotency_key', 'like', self::IDEMPOTENCY_KEY . '%')->delete();
        DB::table('payment_channels')->where('channel_code', self::GATEWAY)->delete();
        DB::table('user_infos')->whereIn('user_id', $userIds)->delete();
        DB::table('user_logins')->whereIn('user_id', $userIds)->delete();
        DB::table('user_logins')->whereIn('email', $emails)->delete();
    }
}

/**
 * 本地 fake 支付网关适配器。
 *
 * 功能逻辑说明：
 * - LegacyFakeProviderAdapter 用于旧入金申请闭环测试，替代真实第三方支付通道。
 * - createOrder 返回固定跳转地址，便于断言建单成功后支付入口可访问。
 * - verifyCallback/parseCallback/acknowledge 仅用于满足 PaymentGatewayAdapter 契约。
 */
final class LegacyFakeProviderAdapter implements PaymentGatewayAdapter
{
    /**
     * 假适配器持有验签密钥。verifyCallback 据此校验回调签名，模拟真实渠道验签行为。
     * @var string
     */
    private $secret;

    public function __construct(string $secret)
    {
        $this->secret = $secret;
    }

    public function createOrder(\App\Models\DepositRecord $order, array $channelConfig): PaymentOrderResult
    {
        $providerOrderNo = 'LEGACY-' . $order->local_order_no;

        return new PaymentOrderResult(
            (string) $order->gateway_code,
            $providerOrderNo,
            'https://fake-provider.example.test/checkout/' . rawurlencode($providerOrderNo),
            null,
            []
        );
    }

    public function verifyCallback(Request $request, array $channelConfig): bool
    {
        return true;
    }

    public function parseCallback(Request $request, array $channelConfig): PaymentCallback
    {
        return new PaymentCallback(
            (string) $request->input('gateway', 'legacy-fake-gateway'),
            (string) $request->input('order_no'),
            (string) $request->input('provider_order_no'),
            (string) $request->input('status', 'success'),
            (string) $request->input('amount', '0'),
            'USD',
            (string) ($channelConfig['merchant_id'] ?? ''),
            'legacy-fake-signature'
        );
    }

    public function acknowledge(PaymentCallback $callback): Response
    {
        return new Response('LEGACY_FAKE_ACK', 200, ['Content-Type' => 'text/plain']);
    }
}
