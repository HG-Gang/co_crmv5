<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/02
 * Time: 01:45
 */

/**
 * 遗留充值/出金表单意图桥接回归测试（nonce 防重放闭环）。
 *
 * 文件功能：
 * - 验证 /user/deposit 与 /user/withdraw 页面下发独立的高熵隐藏 nonce（idempotency_key）。
 * - 验证 LegacyFormIntentService 的 nonce 签发、TTL、容量、用途与会话绑定校验，
 *   且校验不消费 nonce。
 * - 验证遗留主/OTC 充值、出金接口复用同一 nonce 幂等，新 nonce 创建新单。
 * - 验证缺失或跨用途 nonce 被拒绝且无任何写入。
 * - 验证现代提交路由不回退到请求体 nonce。
 *
 * 适用场景：
 * - 前台遗留表单意图桥的回归测试，防止重放、跨用途复用与无 nonce 提交。
 *
 * 入参例子：
 * - POST /user/deposit_request：idempotency_key、deposit_amt_usd、pay_channel。
 * - POST /user/withdraw_request：idempotency_key、withdraw_amt、withdraw_psw、agree。
 *
 * 返回值：
 * - 首次提交 code 为 CREATED 并返回 data.order_no / data.id；同 nonce 重放返回相同单据。
 * - 缺失/跨用途 nonce 返回 VALIDATION_FAILED。
 *
 * 异常或失败场景：
 * - nonce 过期、容量超限、跨会话/跨用途/跨用户校验失败；重放不产生第二条记录。
 */

declare(strict_types=1);

namespace Tests\Feature;

use App\Constants\ResponseCode;
use App\Contracts\WithdrawalAccountSnapshotGateway;
use App\Contracts\PaymentGatewayAdapter;
use App\Models\DepositRecord;
use App\Models\UserLogin;
use App\Services\Legacy\LegacyFormIntentService;
use App\Services\Payment\PaymentCallback;
use App\Services\Payment\PaymentGatewayRegistry;
use App\Services\Payment\PaymentOrderResult;
use App\Services\Withdrawal\WithdrawalAccountSnapshot;
use App\Http\Middleware\JwtAuthMiddleware;
use App\Http\Middleware\SingleSignOn;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Illuminate\Session\ArraySessionHandler;
use Illuminate\Session\Store;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Testing\TestResponse;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

/**
 * Narrow regression tests for the legacy deposit/withdraw form intent bridge.
 *
 * The fixture is transaction-scoped and uses a per-test marker so a crashed
 * worker cannot accidentally reuse another test's user or payment channel.
 */
class FrontLegacyPaymentFormIntentClosureModuleTest extends TestCase
{
    use DatabaseTransactions;

    /**
     * 随机分配的业务用户 ID（700000000 段）。旧版支付表单用例以它建夹具用户并发起下单。
     * @var int
     */
    private $userId;
    /**
     * 夹具渠道码（legacy-intent-{marker}），写入完整支付渠道配置供注册表解析。
     * @var string
     */
    private $channelCode;
    /**
     * 夹具用户邮箱（legacy-intent-{marker}@example.test）。marker 含 pid 与随机数，避免唯一键冲突。
     * @var string
     */
    private $email;
    /**
     * LegacyFormIntentFixtureAdapter 替身实例。记录下单调用次数，验证旧表单意图只产生一次支付下单。
     * @var LegacyFormIntentFixtureAdapter
     */
    private $paymentAdapter;

    protected function setUp(): void
    {
        parent::setUp();

        $marker = getmypid() . '-' . bin2hex(random_bytes(6));
        $this->userId = 700000000 + random_int(1, 9000000);
        $this->channelCode = 'legacy-intent-' . $marker;
        $this->email = 'legacy-intent-' . $marker . '@example.test';

        $now = time();
        foreach ([
            'deposit_enabled' => '1',
            'deposit_weekend_enabled' => '1',
            'deposit_start_time' => '',
            'deposit_end_time' => '',
            'deposit_min_amount' => '10.00',
            'deposit_max_amount' => '500000.00',
            'withdrawal_enabled' => '1',
            'withdrawal_weekend_enabled' => '1',
            'withdrawal_start_time' => '',
            'withdrawal_end_time' => '',
            'withdraw_min_amount' => '10.00',
            'withdraw_max_amount' => '500000.00',
            'withdraw_risk_rate_limit' => '100.00',
            'withdraw_check_open' => '0',
            'withdrawal_fee_rate' => '0',
            'withdrawal_fixed_fee_usd' => '0',
            'withdraw_exchange_rate_cny' => '7.20',
        ] as $key => $value) {
            DB::table('system_configs')->updateOrInsert(['key' => $key], [
                'value' => $value,
                'group' => 'finance',
                'description' => 'Legacy form intent fixture',
                'created_at' => $now,
                'updated_at' => $now,
                'deleted_at' => null,
            ]);
        }

        $loginId = DB::table('user_logins')->insertGetId([
            'user_id' => $this->userId,
            'email' => $this->email,
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
            'user_id' => $this->userId,
            'login_id' => $loginId,
            'user_name' => 'legacy-intent-user',
            'phone' => '13900000001',
            'gender' => 1,
            'avatar' => '',
            'account_type' => 2,
            'parent_id' => 0,
            'family_tree' => '',
            'group_id' => 0,
            'level_id' => 0,
            'comm_rate' => 0,
            'auth_status' => 1,
            'total_funds' => 10000,
            'used_margin' => 0,
            'avail_margin' => 10000,
            'equity' => 10000,
            'effective_credit' => 0,
            'risk_ratio' => 0,
            'leverage' => 100,
            'is_ecn' => 0,
            'is_deposit_allowed' => 0,
            'is_withdrawal_allowed' => 0,
            'is_agent_confirmed' => 1,
            'is_mt4_enabled' => 1,
            'is_mt4_readonly' => 0,
            'created_at' => $now,
            'updated_at' => $now,
            'deleted_at' => null,
        ]);

        DB::table('payment_channels')->insert([
            'name' => 'Legacy intent fixture channel',
            'channel_code' => $this->channelCode,
            'exchange_rate' => '1.00000000',
            'is_enabled' => 1,
            'sort' => 999,
            'config' => json_encode([
                'adapter' => 'legacy-intent-fixture',
                'merchant_id' => 'legacy-intent-merchant',
                'gateway_url' => 'https://provider.example.test/legacy-intent',
                'secret_reference' => 'env:LEGACY_INTENT_FIXTURE_SECRET',
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

        DB::table('user_auths')->insert([
            'user_id' => $this->userId,
            'bank_no' => 'LEGACY-INTENT-BANK',
            'bank_no_tmp' => '',
            'bank_name' => 'Legacy Intent Bank',
            'bank_name_tmp' => '',
            'bank_addr' => 'Legacy Intent Branch',
            'bank_addr_tmp' => '',
            'bank_status' => 2,
            'bank_remarks' => '',
            'id_card_no' => 'ID-' . $this->userId,
            'id_card_status' => 2,
            'id_card_front' => '',
            'id_card_back' => '',
            'id_card_remarks' => '',
            'bank_card_img' => '',
            'bank_card_back_img' => '',
            'bank_card_img_tmp' => '',
            'bank_card_back_img_tmp' => '',
            'is_bank_synced' => 0,
            'created_at' => $now,
            'updated_at' => $now,
            'deleted_at' => null,
        ]);

        $registry = new PaymentGatewayRegistry();
        $this->paymentAdapter = new LegacyFormIntentFixtureAdapter();
        $registry->register('legacy-intent-fixture', $this->paymentAdapter, ['USD']);
        $this->app->instance(PaymentGatewayRegistry::class, $registry);
        $this->app->instance(
            WithdrawalAccountSnapshotGateway::class,
            new class implements WithdrawalAccountSnapshotGateway {
                public function snapshot(int $userId): WithdrawalAccountSnapshot
                {
                    return new WithdrawalAccountSnapshot('10000.00', '10000.00');
                }
            }
        );
    }

    protected function tearDown(): void
    {
        $withdrawIds = DB::table('withdraw_records')->where('user_id', $this->userId)->pluck('id');
        if ($withdrawIds->isNotEmpty()) {
            DB::table('withdraw_settlement_outbox')->whereIn('withdraw_record_id', $withdrawIds)->delete();
        }
        DB::table('withdraw_records')->where('user_id', $this->userId)->delete();
        DB::table('user_auths')->where('user_id', $this->userId)->delete();
        DB::table('deposit_records')->where('user_id', $this->userId)->delete();
        DB::table('payment_channels')->where('channel_code', $this->channelCode)->delete();
        DB::table('user_infos')->where('user_id', $this->userId)->delete();
        DB::table('user_logins')->where('user_id', $this->userId)->delete();

        parent::tearDown();
    }

    // 验证充值与出金页面下发不同且高熵的隐藏 nonce。
    public function test_deposit_and_withdraw_pages_issue_distinct_high_entropy_hidden_nonces(): void
    {
        $login = UserLogin::where('user_id', $this->userId)->firstOrFail();

        $this->actingAs($login, 'user');
        $depositNonce = $this->issuePageNonce('/user/deposit');
        $withdrawNonce = $this->issuePageNonce('/user/withdraw');

        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $depositNonce);
        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $withdrawNonce);
        $this->assertNotSame($depositNonce, $withdrawNonce);
    }

    // 验证表单意图服务绑定 nonce 并执行 TTL、容量、用途与会话校验且不消费 nonce。
    public function test_form_intent_service_binds_nonce_and_enforces_ttl_and_capacity_without_consuming_it(): void
    {
        $now = 1700000000;
        $generated = [str_repeat('a', 64), str_repeat('b', 64), str_repeat('c', 64)];
        $service = new LegacyFormIntentService(
            10,
            2,
            static function () use (&$now): int {
                return $now;
            },
            static function () use (&$generated): string {
                return (string) array_shift($generated);
            }
        );
        $request = $this->sessionRequest('legacy-session-a');

        $depositNonce = $service->issue($request, 'deposit', $this->userId);
        $withdrawNonce = $service->issue($request, 'withdraw', $this->userId);

        $this->assertTrue($service->validate($request, 'deposit', $this->userId, $depositNonce));
        $this->assertTrue($service->validate($request, 'deposit', $this->userId, $depositNonce));
        $this->assertFalse($service->validate($request, 'withdraw', $this->userId, $depositNonce));
        $this->assertFalse($service->validate($request, 'deposit', $this->userId + 1, $depositNonce));

        $otherSession = $this->sessionRequest('legacy-session-b');
        $otherSession->session()->put(
            LegacyFormIntentService::SESSION_KEY,
            $request->session()->get(LegacyFormIntentService::SESSION_KEY)
        );
        $this->assertFalse($service->validate(
            $otherSession,
            'deposit',
            $this->userId,
            $depositNonce
        ));

        $newDepositNonce = $service->issue($request, 'deposit', $this->userId);
        $this->assertNotSame($depositNonce, $newDepositNonce);
        $this->assertFalse($service->validate($request, 'deposit', $this->userId, $depositNonce));
        $this->assertTrue($service->validate($request, 'withdraw', $this->userId, $withdrawNonce));
        $this->assertTrue($service->validate($request, 'deposit', $this->userId, $newDepositNonce));

        $now += 10;
        $this->assertFalse($service->validate($request, 'withdraw', $this->userId, $withdrawNonce));
        $this->assertFalse($service->validate($request, 'deposit', $this->userId, $newDepositNonce));
        $this->assertSame([], $request->session()->get(LegacyFormIntentService::SESSION_KEY));
    }

    // 验证遗留充值主/OTC 接口同 nonce 幂等重放，新 nonce 才创建新单。
    public function test_legacy_deposit_main_and_otc_replay_same_nonce_and_create_for_a_new_nonce(): void
    {
        $login = UserLogin::where('user_id', $this->userId)->firstOrFail();
        $client = $this->actingAs($login, 'user');
        $firstNonce = $this->issuePageNonce('/user/deposit');

        $first = $client->postJson('/user/deposit_request', [
            'idempotency_key' => $firstNonce,
            'deposit_amt_usd' => '100.00',
            'pay_channel' => $this->channelCode,
        ]);
        $replay = $client->postJson('/user/deposit_request_otc', [
            'idempotency_key' => $firstNonce,
            'deposit_amt' => '100.00',
            'passageway' => $this->channelCode,
        ]);

        $first->assertOk()->assertJsonPath('code', ResponseCode::CREATED);
        $replay->assertOk()->assertJsonPath('code', ResponseCode::CREATED);
        $this->assertSame($first->json('data.order_no'), $replay->json('data.order_no'));
        $this->assertSame(1, $this->paymentAdapter->createOrderCalls);

        $secondNonce = $this->issuePageNonce('/user/deposit');
        $second = $client->postJson('/user/deposit_request_otc', [
            'idempotency_key' => $secondNonce,
            'deposit_amt' => '100.00',
            'passageway' => $this->channelCode,
        ]);

        $second->assertOk()->assertJsonPath('code', ResponseCode::CREATED);
        $this->assertNotSame($first->json('data.order_no'), $second->json('data.order_no'));
        $this->assertSame(2, $this->paymentAdapter->createOrderCalls);
        $this->assertSame(
            2,
            DB::table('deposit_records')->where('user_id', $this->userId)->count()
        );
    }

    // 验证遗留出金主/OTC 接口 nonce 幂等桥接与缺少 agree 时被拒。
    public function test_legacy_withdraw_main_and_otc_bridge_nonce_replay_and_missing_agree_only(): void
    {
        $login = UserLogin::where('user_id', $this->userId)->firstOrFail();
        $client = $this->actingAs($login, 'user');
        $firstNonce = $this->issuePageNonce('/user/withdraw');

        $first = $client->postJson('/user/withdraw_request', [
            'idempotency_key' => $firstNonce,
            'withdraw_amt' => '100.00',
            'withdraw_psw' => 'password',
        ]);
        $replay = $client->postJson('/user/withdraw_request_OTC', [
            'idempotency_key' => $firstNonce,
            'withdraw_amt' => '100.00',
            'withdraw_password' => 'password',
        ]);

        $first->assertOk()->assertJsonPath('code', ResponseCode::CREATED);
        $replay->assertOk()->assertJsonPath('code', ResponseCode::CREATED);
        $this->assertSame((int) $first->json('data.id'), (int) $replay->json('data.id'));

        $secondNonce = $this->issuePageNonce('/user/withdraw');
        $second = $client->postJson('/user/withdraw_request_OTC', [
            'idempotency_key' => $secondNonce,
            'withdraw_amt' => '100.00',
            'withdraw_password' => 'password',
        ]);
        $second->assertOk()->assertJsonPath('code', ResponseCode::CREATED);
        $this->assertNotSame((int) $first->json('data.id'), (int) $second->json('data.id'));

        $refusedNonce = $this->issuePageNonce('/user/withdraw');
        $refused = $client->postJson('/user/withdraw_request', [
            'idempotency_key' => $refusedNonce,
            'withdraw_amt' => '100.00',
            'withdraw_psw' => 'password',
            'agree' => 0,
        ]);
        $refused->assertOk()->assertJsonPath('code', ResponseCode::VALIDATION_FAILED);

        $this->assertSame(2, DB::table('withdraw_records')->where('user_id', $this->userId)->count());
        $withdrawIds = DB::table('withdraw_records')->where('user_id', $this->userId)->pluck('id');
        $this->assertSame(
            2,
            DB::table('withdraw_settlement_outbox')->whereIn('withdraw_record_id', $withdrawIds)->count()
        );
    }

    // 验证遗留路由拒绝缺失或跨用途 nonce 且不产生任何写入。
    public function test_legacy_routes_reject_missing_or_cross_purpose_nonces_without_writes(): void
    {
        $login = UserLogin::where('user_id', $this->userId)->firstOrFail();
        $client = $this->actingAs($login, 'user');
        $depositNonce = $this->issuePageNonce('/user/deposit');
        $withdrawNonce = $this->issuePageNonce('/user/withdraw');

        foreach ([
            ['/user/deposit_request', [
                'deposit_amt_usd' => '100.00',
                'pay_channel' => $this->channelCode,
            ]],
            ['/user/deposit_request_otc', [
                'idempotency_key' => $withdrawNonce,
                'deposit_amt' => '100.00',
                'passageway' => $this->channelCode,
            ]],
            ['/user/withdraw_request', [
                'withdraw_amt' => '100.00',
                'withdraw_psw' => 'password',
            ]],
            ['/user/withdraw_request_OTC', [
                'idempotency_key' => $depositNonce,
                'withdraw_amt' => '100.00',
                'withdraw_password' => 'password',
            ]],
        ] as [$uri, $payload]) {
            $client->postJson($uri, $payload)
                ->assertOk()
                ->assertJsonPath('code', ResponseCode::VALIDATION_FAILED);
        }

        $this->assertSame(0, DB::table('deposit_records')->where('user_id', $this->userId)->count());
        $this->assertSame(0, DB::table('withdraw_records')->where('user_id', $this->userId)->count());
    }

    // 验证现代提交路由不回退到请求体 nonce。
    public function test_modern_submission_routes_do_not_fall_back_to_body_nonce(): void
    {
        $login = UserLogin::where('user_id', $this->userId)->firstOrFail();
        $this->actingAs($login, 'user');
        $depositNonce = $this->issuePageNonce('/user/deposit');
        $withdrawNonce = $this->issuePageNonce('/user/withdraw');
        $client = $this->withoutMiddleware([JwtAuthMiddleware::class, SingleSignOn::class])
            ->actingAs($login, 'user');

        $client->postJson('/api/front/deposits/submissions', [
            'idempotency_key' => $depositNonce,
            'amount' => '100.00',
            'channel' => $this->channelCode,
        ])->assertOk()->assertJsonPath('code', ResponseCode::VALIDATION_FAILED);
        $client->postJson('/api/front/withdrawals/submissions', [
            'idempotency_key' => $withdrawNonce,
            'amount' => '100.00',
            'password' => 'password',
            'agree' => 1,
        ])->assertOk()->assertJsonPath('code', ResponseCode::VALIDATION_FAILED);

        $this->assertSame(0, DB::table('deposit_records')->where('user_id', $this->userId)->count());
        $this->assertSame(0, DB::table('withdraw_records')->where('user_id', $this->userId)->count());
    }

    private function extractNonce(string $html): string
    {
        $matched = preg_match('/name=["\']idempotency_key["\'][^>]*value=["\']([a-f0-9]{64})["\']/i', $html, $matches);
        $this->assertSame(1, $matched, 'Expected a hidden idempotency_key input in the legacy form.');

        return (string) $matches[1];
    }

    private function issuePageNonce(string $path): string
    {
        $response = $this->get($path);
        $response->assertOk();
        $this->persistSessionCookie($response);

        return $this->extractNonce($response->getContent());
    }

    private function persistSessionCookie(TestResponse $response): void
    {
        $cookieName = (string) config('session.cookie');
        foreach ($response->headers->getCookies() as $cookie) {
            if ($cookie->getName() !== $cookieName) {
                continue;
            }

            $this->withUnencryptedCookie($cookieName, $cookie->getValue())->withCredentials();

            return;
        }

        $this->fail('Legacy form page did not return the session cookie required by the browser flow.');
    }

    private function sessionRequest(string $sessionId): Request
    {
        $store = new Store('legacy-form-intent-test', new ArraySessionHandler(120), $sessionId);
        $store->start();
        $request = Request::create('/legacy-form-intent-test');
        $request->setLaravelSession($store);

        return $request;
    }
}

final class LegacyFormIntentFixtureAdapter implements PaymentGatewayAdapter
{
    /**
     * createOrder 被调用次数。断言旧版表单意图链路的下单次数符合预期（如重复提交不重复下单）。
     * @var int
     */
    public $createOrderCalls = 0;

    public function createOrder(DepositRecord $order, array $channelConfig): PaymentOrderResult
    {
        ++$this->createOrderCalls;

        return new PaymentOrderResult(
            (string) $order->gateway_code,
            'LEGACY-PROVIDER-' . $order->getKey(),
            'https://provider.example.test/legacy-intent/checkout'
        );
    }

    public function verifyCallback(Request $request, array $channelConfig): bool
    {
        return true;
    }

    public function parseCallback(Request $request, array $channelConfig): PaymentCallback
    {
        throw new \LogicException('Callback parsing is not used by this fixture.');
    }

    public function acknowledge(PaymentCallback $callback): Response
    {
        return new Response('OK');
    }
}
