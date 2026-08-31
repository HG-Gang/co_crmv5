<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/02
 * Time: 01:47
 */

/**
 * 前台假支付渠道端到端冒烟测试。
 *
 * 文件功能：
 * - 验证 FrontDemoDataSeeder 生命周期内 system_configs 被正确恢复、不泄漏。
 * - 端到端验证充值提交、重定向、签名回调、重复回调与结算 outbox 的幂等链路：
 *   创建订单 -> 返回支付 URL -> 回调验签 -> 成功状态 -> 仅一条 pending 结算 outbox ->
 *   仅派发一次 SettleDepositPayment。
 *
 * 适用场景：
 * - 前台充值全链路的冒烟回归测试，使用本地假渠道（task6-fake-provider）避免外部依赖。
 *
 * 入参例子：
 * - POST /api/front/deposits/submissions：Idempotency-Key 头 + amount=100.00、channel=task6-fake-provider。
 * - POST /api/front/payment/notify/task6-fake-provider：order_no、provider_order_no、amount、status、signature。
 *
 * 返回值：
 * - 创建返回 code 为 CREATED 与 data.order_no / data.payment_url；回调返回 TASK6_FAKE_ACK。
 * - 重复回调幂等，订单 payment_status=success、settlement_status=pending，outbox 一条。
 *
 * 异常或失败场景：
 * - 回调签名错误被拒绝；Seeder 泄漏系统配置时 tearDown 断言失败。
 */

declare(strict_types=1);

namespace Tests\Feature;

use App\Constants\ResponseCode;
use App\Contracts\PaymentGatewayAdapter;
use App\Http\Middleware\JwtAuthMiddleware;
use App\Http\Middleware\SingleSignOn;
use App\Jobs\SettleDepositPayment;
use App\Models\DepositRecord;
use App\Models\PaymentChannel;
use App\Models\UserLogin;
use App\Services\Payment\PaymentCallback;
use App\Services\Payment\PaymentGatewayRegistry;
use App\Services\Payment\PaymentOrderResult;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Symfony\Component\HttpFoundation\Response;
use Tests\Feature\Concerns\ManagesSharedSystemConfigFixtures;
use Tests\TestCase;

final class FrontPaymentFakeProviderSmokeTest extends TestCase
{
    use ManagesSharedSystemConfigFixtures;

    /**
     * 冒烟用例使用的假支付渠道码（task6-fake-provider）。注册 Task6FakeProviderAdapter 后经由真实 HTTP 链路走通下单-回调。
     * @var string
     */
    private const GATEWAY = 'task6-fake-provider';
    /**
     * 冒烟用例的固定幂等键。固定值让重复冒烟可复现同一订单路径；前缀表明属于 task6 夹具，便于定位清理。
     * @var string
     */
    private const IDEMPOTENCY_KEY = 'task6-fake-provider-smoke';
    /**
     * 假适配器回调验签用的本地夹具密钥。仅存在于测试进程配置中，绝不复用真实环境密钥。
     * @var string
     */
    private const SECRET = 'task6-local-fixture-secret';

    /**
     * 本用例改写前的 front demo 相关 system_configs 行快照。tearDown 据此恢复，防止配置改写泄漏到共享库。
     * @var array<int, array<string, mixed>>|null
     */
    private $frontDemoConfigSnapshot;

    /**
     * 进入探测阶段前再次捕获的配置快照。用例借它区分"夹具自己写入的行"与"探测并发写入的行"，
     * 恢复时只回滚前者。
     * @var array<int, array<string, mixed>>|null
     */
    private $frontDemoConfigProbeOriginalSnapshot;

    protected function setUp(): void
    {
        parent::setUp();

        $this->frontDemoConfigSnapshot = null;
        $this->frontDemoConfigProbeOriginalSnapshot = null;

        $this->acquireSharedSystemConfigFixtureLock();
        try {
            $initialRows = $this->frontDemoSystemConfigRows();
            $this->captureSharedSystemConfigFixtureOwnedState(
                $this->frontDemoSystemConfigKeys(),
                $initialRows
            );
            if ($this->getName() === $this->frontDemoConfigLeakProbeTest()) {
                $this->frontDemoConfigProbeOriginalSnapshot = $initialRows;
                $initialRows = $this->deleteFrontDemoInsertedWithdrawalConfigRows($initialRows);
            }

            $this->frontDemoConfigSnapshot = $initialRows;
            Queue::fake();
            $this->cleanup();
            $seederStartedAt = time();
            $this->seed(\Database\Seeders\FrontDemoDataSeeder::class);
            $this->captureFrontDemoSeederOwnedConfigRows(
                $this->frontDemoConfigSnapshot,
                $seederStartedAt,
                time()
            );
        } catch (\Throwable $exception) {
            $this->runSharedSystemConfigFixtureLifecycleCleanup($exception, [
                'restore front demo system config snapshot' => function (): void {
                    $this->restoreFrontDemoSystemConfigRows($this->frontDemoConfigSnapshot);
                },
                'restore front demo probe original snapshot' => function (): void {
                    if ($this->frontDemoConfigProbeOriginalSnapshot !== null
                        && $this->frontDemoConfigSnapshot !== null) {
                        $this->captureSharedSystemConfigFixtureOwnedState(
                            $this->frontDemoSystemConfigKeys(),
                            $this->frontDemoConfigSnapshot
                        );
                    }
                    $this->restoreFrontDemoSystemConfigRows($this->frontDemoConfigProbeOriginalSnapshot);
                },
                'release shared system config fixture lock' => function (): void {
                    $this->releaseSharedSystemConfigFixtureLock();
                },
            ]);
        }
    }

    protected function tearDown(): void
    {
        $this->runSharedSystemConfigFixtureLifecycleCleanup(null, [
            'clean payment smoke fixtures' => function (): void {
                $this->cleanup();
            },
            'restore front demo system config snapshot' => function (): void {
                if ($this->frontDemoConfigSnapshot !== null) {
                    $this->restoreFrontDemoSystemConfigRows($this->frontDemoConfigSnapshot);
                    $this->assertSame(
                        $this->frontDemoConfigSnapshot,
                        $this->frontDemoSystemConfigRows(),
                        'FrontDemoDataSeeder leaked system configs from the payment smoke test.'
                    );
                }
            },
            'restore front demo probe original snapshot' => function (): void {
                if ($this->frontDemoConfigProbeOriginalSnapshot !== null
                    && $this->frontDemoConfigSnapshot !== null) {
                    $this->captureSharedSystemConfigFixtureOwnedState(
                        $this->frontDemoSystemConfigKeys(),
                        $this->frontDemoConfigSnapshot
                    );
                }
                $this->restoreFrontDemoSystemConfigRows($this->frontDemoConfigProbeOriginalSnapshot);
            },
            'parent teardown' => function (): void {
                    parent::tearDown();
            },
            'release shared system config fixture lock' => function (): void {
                    $this->releaseSharedSystemConfigFixtureLock();
            },
        ]);
    }

    // 验证前台演示 Seeder 生命周期恢复系统配置、无泄漏。
    public function test_front_demo_seeder_fixture_lifecycle_restores_system_configs(): void
    {
        $this->assertSame(
            $this->frontDemoInsertedWithdrawalConfigKeys(),
            DB::table('system_configs')
                ->whereIn('key', $this->frontDemoInsertedWithdrawalConfigKeys())
                ->whereNull('deleted_at')
                ->orderBy('key')
                ->pluck('key')
                ->all()
        );
    }

    // 验证创建、重定向、签名回调、重复回调与结算 outbox 构成一条幂等链路。
    public function test_create_redirect_signed_callback_duplicate_callback_and_outbox_are_one_idempotent_chain(): void
    {
        $adapter = new Task6FakeProviderAdapter(self::SECRET);
        app(PaymentGatewayRegistry::class)->register('task6-fake-adapter', $adapter, ['USD']);
        PaymentChannel::create([
            'name' => 'Task 6 Local Fake Provider',
            'channel_code' => self::GATEWAY,
            'exchange_rate' => '1.00000000',
            'is_enabled' => 1,
            'sort' => 906,
            'config' => [
                'adapter' => 'task6-fake-adapter',
                'gateway_code' => self::GATEWAY,
                'merchant_id' => 'task6-fake-merchant',
                'gateway_url' => 'https://fake-provider.example.test/create',
                'secret_reference' => 'env:TASK6_FAKE_PROVIDER_SECRET',
                'currency' => 'USD',
                'amount_unit' => 'decimal',
                'notify_route' => 'front_api_payment_notify',
                'return_route' => 'front_api_payment_return',
                'min_amount' => '10.00',
                'max_amount' => '500000.00',
            ],
        ]);

        $login = UserLogin::where('user_id', 1001)->firstOrFail();
        $create = $this->withoutMiddleware([JwtAuthMiddleware::class, SingleSignOn::class])
            ->actingAs($login, 'user')
            ->withHeader('Idempotency-Key', self::IDEMPOTENCY_KEY)
            ->postJson('/api/front/deposits/submissions', [
                'amount' => '100.00',
                'channel' => self::GATEWAY,
            ]);

        $create->assertOk()
            ->assertJsonPath('code', ResponseCode::CREATED)
            ->assertJsonPath('data.channel', self::GATEWAY);
        $orderNo = (string) $create->json('data.order_no');
        $providerOrderNo = 'TASK6-' . $orderNo;
        $this->assertSame(
            'https://fake-provider.example.test/checkout/' . rawurlencode($providerOrderNo),
            $create->json('data.payment_url')
        );
        $this->get('/api/front/payment/return/' . self::GATEWAY . '?status=success')
            ->assertRedirect(route('front_page_deposit', [
                'gateway' => self::GATEWAY,
                'status' => 'pending',
            ]));

        $payload = [
            'order_no' => $orderNo,
            'provider_order_no' => $providerOrderNo,
            'amount' => '100.00',
            'status' => 'success',
        ];
        $payload['signature'] = $adapter->sign($payload);

        $this->post('/api/front/payment/notify/' . self::GATEWAY, $payload)
            ->assertOk()
            ->assertSeeText('TASK6_FAKE_ACK');
        $this->post('/api/front/payment/notify/' . self::GATEWAY, $payload)
            ->assertOk()
            ->assertSeeText('TASK6_FAKE_ACK');

        $order = DepositRecord::where('local_order_no', $orderNo)->firstOrFail();
        $this->assertSame('success', $order->payment_status);
        $this->assertSame('pending', $order->settlement_status);
        $this->assertSame(1, DB::table('payment_settlement_outbox')
            ->where('deposit_record_id', $order->id)
            ->where('event_type', 'deposit_settlement')
            ->count());
        Queue::assertPushed(SettleDepositPayment::class, 1);
    }

    private function cleanup(): void
    {
        $depositIds = DB::table('deposit_records')
            ->where('idempotency_key', self::IDEMPOTENCY_KEY)
            ->pluck('id');
        if ($depositIds->isNotEmpty()) {
            DB::table('payment_settlement_outbox')->whereIn('deposit_record_id', $depositIds)->delete();
            DB::table('deposit_records')->whereIn('id', $depositIds)->delete();
        }
        DB::table('payment_channels')->where('channel_code', self::GATEWAY)->delete();
    }

    private function frontDemoConfigLeakProbeTest(): string
    {
        return 'test_front_demo_seeder_fixture_lifecycle_restores_system_configs';
    }

    /** @return array<int, string> */
    private function frontDemoSystemConfigKeys(): array
    {
        return [
            'deposit_enabled',
            'deposit_exchange_rate_cny',
            'deposit_max_amount',
            'deposit_min_amount',
            'download_mobile_url',
            'download_pc_url',
            'withdraw_check_open',
            'withdraw_exchange_rate_cny',
            'withdraw_max_amount',
            'withdraw_min_amount',
            'withdraw_risk_rate_limit',
            'withdrawal_enabled',
            'withdrawal_end_time',
            'withdrawal_fee_rate',
            'withdrawal_fixed_fee_usd',
            'withdrawal_start_time',
            'withdrawal_weekend_enabled',
        ];
    }

    /** @return array<int, string> */
    private function frontDemoInsertedWithdrawalConfigKeys(): array
    {
        return [
            'withdraw_check_open',
            'withdrawal_end_time',
            'withdrawal_fee_rate',
            'withdrawal_fixed_fee_usd',
            'withdrawal_start_time',
            'withdrawal_weekend_enabled',
        ];
    }

    /** @return array<int, array<string, mixed>> */
    private function frontDemoSystemConfigRows(): array
    {
        return DB::table('system_configs')
            ->useWritePdo()
            ->whereIn('key', $this->frontDemoSystemConfigKeys())
            ->orderBy('key')
            ->get()
            ->map(static function ($row): array {
                $normalized = (array) $row;
                foreach (['id', 'created_at', 'updated_at', 'deleted_at'] as $column) {
                    if ($normalized[$column] !== null) {
                        $normalized[$column] = (string) $normalized[$column];
                    }
                }
                ksort($normalized);

                return $normalized;
            })
            ->all();
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     * @return array<int, array<string, mixed>>
     */
    private function deleteFrontDemoInsertedWithdrawalConfigRows(array $rows): array
    {
        return $this->deleteSharedSystemConfigFixtureOwnedRows(
            $this->frontDemoSystemConfigKeys(),
            $rows,
            $this->frontDemoInsertedWithdrawalConfigKeys()
        );
    }

    /**
     * @param array<int, array<string, mixed>> $beforeRows
     */
    private function captureFrontDemoSeederOwnedConfigRows(
        array $beforeRows,
        int $startedAt,
        int $finishedAt
    ): void {
        $this->captureSharedSystemConfigFixtureOwnedStateAfterFrontDemoSeeder(
            $this->frontDemoSystemConfigKeys(),
            $beforeRows,
            $this->frontDemoSystemConfigDefinitions(),
            $startedAt,
            $finishedAt
        );
    }

    /** @return array<string, array{value: string, group: string, description: string, required: bool}> */
    private function frontDemoSystemConfigDefinitions(): array
    {
        return [
            'deposit_enabled' => ['value' => '1', 'group' => 'finance', 'description' => 'Demo deposit switch', 'required' => false],
            'deposit_exchange_rate_cny' => ['value' => '7.12', 'group' => 'finance', 'description' => 'Demo CNY deposit rate', 'required' => false],
            'deposit_min_amount' => ['value' => '50', 'group' => 'finance', 'description' => 'Demo min deposit amount', 'required' => false],
            'deposit_max_amount' => ['value' => '500000', 'group' => 'finance', 'description' => 'Demo max deposit amount', 'required' => false],
            'withdrawal_enabled' => ['value' => '1', 'group' => 'finance', 'description' => 'Demo withdrawal switch', 'required' => true],
            'withdrawal_weekend_enabled' => ['value' => '1', 'group' => 'finance', 'description' => 'Demo weekend withdrawal switch', 'required' => true],
            'withdrawal_start_time' => ['value' => '', 'group' => 'finance', 'description' => 'Demo withdrawal start time', 'required' => true],
            'withdrawal_end_time' => ['value' => '', 'group' => 'finance', 'description' => 'Demo withdrawal end time', 'required' => true],
            'withdraw_exchange_rate_cny' => ['value' => '7.05', 'group' => 'finance', 'description' => 'Demo CNY withdrawal rate', 'required' => true],
            'withdraw_min_amount' => ['value' => '50', 'group' => 'finance', 'description' => 'Demo min withdrawal amount', 'required' => true],
            'withdraw_max_amount' => ['value' => '50000', 'group' => 'finance', 'description' => 'Demo max withdrawal amount', 'required' => true],
            'withdraw_risk_rate_limit' => ['value' => '50', 'group' => 'finance', 'description' => 'Demo withdrawal risk limit', 'required' => true],
            'withdraw_check_open' => ['value' => '0', 'group' => 'finance', 'description' => 'Demo open-position withdrawal check', 'required' => true],
            'withdrawal_fee_rate' => ['value' => '0', 'group' => 'finance', 'description' => 'Demo withdrawal fee rate', 'required' => true],
            'withdrawal_fixed_fee_usd' => ['value' => '0', 'group' => 'finance', 'description' => 'Demo fixed withdrawal fee', 'required' => true],
            'download_pc_url' => ['value' => '#', 'group' => 'front', 'description' => 'Demo PC download URL', 'required' => false],
            'download_mobile_url' => ['value' => '#', 'group' => 'front', 'description' => 'Demo mobile download URL', 'required' => false],
        ];
    }

    /** @param array<int, array<string, mixed>>|null $rows */
    private function restoreFrontDemoSystemConfigRows($rows): void
    {
        if ($rows === null) {
            return;
        }

        $this->restoreSharedSystemConfigSnapshot($this->frontDemoSystemConfigKeys(), $rows);
    }
}

final class Task6FakeProviderAdapter implements PaymentGatewayAdapter
{
    /**
     * 假支付适配器持有验签密钥。verifyCallback/parseCallback 据此校验回调签名，模拟真实渠道的验签行为。
     * @var string
     */
    private $secret;

    public function __construct(string $secret)
    {
        $this->secret = $secret;
    }

    public function createOrder(DepositRecord $order, array $channelConfig): PaymentOrderResult
    {
        $providerOrderNo = 'TASK6-' . $order->local_order_no;

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
        $payload = $request->only(['order_no', 'provider_order_no', 'amount', 'status']);

        return hash_equals($this->sign($payload), (string) $request->input('signature'));
    }

    public function parseCallback(Request $request, array $channelConfig): PaymentCallback
    {
        return new PaymentCallback(
            self::gateway(),
            (string) $request->input('order_no'),
            (string) $request->input('provider_order_no'),
            (string) $request->input('status'),
            (string) $request->input('amount'),
            'USD',
            (string) $channelConfig['merchant_id'],
            hash('sha256', json_encode($request->all(), JSON_UNESCAPED_SLASHES))
        );
    }

    public function acknowledge(PaymentCallback $callback): Response
    {
        return new Response('TASK6_FAKE_ACK', 200, ['Content-Type' => 'text/plain']);
    }

    public function sign(array $payload): string
    {
        return hash_hmac('sha256', implode('|', [
            (string) ($payload['order_no'] ?? ''),
            (string) ($payload['provider_order_no'] ?? ''),
            (string) ($payload['amount'] ?? ''),
            (string) ($payload['status'] ?? ''),
        ]), $this->secret);
    }

    private static function gateway(): string
    {
        return 'task6-fake-provider';
    }
}
