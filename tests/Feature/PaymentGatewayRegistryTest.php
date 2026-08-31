<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/02
 * Time: 03:48
 */

/**
 * PaymentGatewayRegistryTest
 *
 * 文件功能：
 * - 验证支付网关注册中心契约：适配器契约与单例、旧别名支持、禁用与不完整配置解析为 null、按别名解析 pay_type、适配器配置校验、支付订单结果不可变字段与危险跳转拒绝、幂等/竞争/未知与终态重放拒绝。
 * - 输入：HTTP 请求与事务回滚的数据库夹具；输出：PHPUnit 断言结果。
 * - 明确不负责：不覆盖真实 MT4 网关与线上支付/出金通道（由网关契约测试锁定）。
 */

declare(strict_types=1);

namespace Tests\Feature;

use App\Contracts\PaymentGatewayAdapter;
use App\Constants\ResponseCode;
use App\Http\Middleware\JwtAuthMiddleware;
use App\Http\Middleware\SingleSignOn;
use App\Models\DepositRecord;
use App\Models\PaymentChannel;
use App\Models\UserLogin;
use App\Services\Payment\PaymentCallback;
use App\Services\Payment\PaymentGatewayRegistry;
use App\Services\Payment\PaymentOrderResult;
use App\Services\Payment\PaymentOrderService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Mockery;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;
use Tests\Support\MySqlAutoIncrementSnapshot;
use Tests\Support\MySqlFixtureMutex;
use Tests\Support\MySqlTableFingerprint;
use Tests\Support\TableRowsSnapshot;

class PaymentGatewayRegistryTest extends TestCase
{
    /**
     * 本模块 DDL 操作专用的 MySQL 命名锁。索引增删没有事务保护，必须持锁串行化，
     * 防止并行进程同时修改表结构。
     * @var string
     */
    private const SCHEMA_LOCK = 'co_crmv5_payment_idempotency_schema_task4';
    /**
     * 入金模块依赖的 system_configs 键集合（开关、时段、限额）。setUp 捕获原始值、tearDown 恢复，
     * 保证测试对入金配置的改写不泄漏。
     * @var array<int, string>
     */
    private const DEPOSIT_CONFIG_KEYS = [
        'deposit_enabled',
        'deposit_weekend_enabled',
        'deposit_start_time',
        'deposit_end_time',
        'deposit_min_amount',
        'deposit_max_amount',
    ];
    /**
     * 夹具涉及的表清单。setUp 捕获行指纹基线，tearDown 重新捕获比对，任何差异都说明夹具数据泄漏。
     * @var array<int, string>
     */
    private const FINGERPRINT_TABLES = [
        'deposit_records',
        'user_logins',
        'user_infos',
        'payment_channels',
        'system_configs',
    ];

    /**
     * 随机分配的未占用业务用户 ID，用于提交入金请求；清理 deposit_records 时以它为过滤条件。
     * @var int|null
     */
    private $userId;
    /**
     * 主测试渠道码（payment-task3-{token}）。insertChannel 写入完整配置并绑定 fixture 适配器。
     * @var string|null
     */
    private $channelCode;
    /**
     * 备用渠道码（payment-task3-alt-{token}）。验证同一适配器绑定多个渠道、按渠道取配置的注册表行为。
     * @var string|null
     */
    private $alternateChannelCode;
    /**
     * 幂等键前缀。分配时校验长度不超过 VARCHAR(100) 且未被占用；清理时按前缀删除本夹具订单。
     * @var string|null
     */
    private $keyPrefix;
    /**
     * 本地订单号前缀。分配时校验长度不超过 VARCHAR(200) 且未被占用。
     * @var string|null
     */
    private $orderPrefix;
    /**
     * suffix => 幂等键 的分配缓存。同一 suffix 复用同一键，并保证长度合规且未被占用。
     * @var array<string, string>
     */
    private $allocatedKeys = [];
    /**
     * suffix => 本地订单号 的分配缓存。同一 suffix 复用同一单号，并校验长度与占用情况。
     * @var array<string, string>
     */
    private $allocatedOrders = [];
    /**
     * setUp 捕获的入金相关 system_configs 行快照。tearDown 据此恢复配置原值。
     * @var array<int, array<string, mixed>>|null
     */
    private $systemConfigSnapshot;
    /**
     * deposit_records 的 AUTO_INCREMENT 快照。tearDown 恢复，防止夹具插入抬高自增计数。
     * @var \Tests\Support\MySqlAutoIncrementSnapshot|null
     */
    private $autoIncrementSnapshot;
    /**
     * 内层 MySQL 命名互斥锁（校验 runner 另有 OS 级互斥）。串行化夹具准备与清理，避免并行进程互相踩踏。
     * @var MySqlFixtureMutex|null
     */
    private $fixtureMutex;
    /**
     * setUp 捕获的各表行指纹基线。tearDown 比对，不一致即说明夹具清理不彻底。
     * @var array<string, array<string, int|string>>
     */
    private $tableFingerprints = [];

    protected function setUp(): void
    {
        parent::setUp();
        try {
            $this->assertSame('mysql', DB::getDriverName(), 'This closure test requires real MySQL.');
            $this->fixtureMutex = new MySqlFixtureMutex();
            $this->fixtureMutex->acquire();
            $this->initializeFixtureIdentity();
            $this->withSchemaLock(function (): void {
                $this->tableFingerprints = MySqlTableFingerprint::capture(self::FINGERPRINT_TABLES);
                $this->autoIncrementSnapshot = MySqlAutoIncrementSnapshot::capture([
                    'deposit_records',
                    'user_logins',
                    'user_infos',
                    'payment_channels',
                    'system_configs',
                ]);
            });
            $this->systemConfigSnapshot = TableRowsSnapshot::capture(
                'system_configs',
                'key',
                self::DEPOSIT_CONFIG_KEYS
            );
            $this->allowDeposits();
            $this->insertUser();
            $this->insertChannel($this->channelCode, 'fixture');
        } catch (\Throwable $exception) {
            $this->abortFixtureSetup($exception);
        }
    }

    private function abortFixtureSetup(\Throwable $cause): void
    {
        $this->runFixtureCleanup($cause, [
            'delete database fixtures' => function (): void {
                $this->deleteDatabaseFixtures();
            },
            'restore system configs' => function (): void {
                if ($this->systemConfigSnapshot !== null) {
                    $this->systemConfigSnapshot->restore();
                }
            },
            'restore AUTO_INCREMENT' => function (): void {
                $this->restoreAutoIncrement();
            },
            'verify table fingerprints' => function (): void {
                $this->verifyTableFingerprints();
            },
            'release fixture mutex' => function (): void {
                if ($this->fixtureMutex !== null) {
                    $this->fixtureMutex->releaseWithDisconnectFallback();
                }
            },
            'parent tearDown' => function (): void {
                parent::tearDown();
            },
        ]);
    }

    protected function tearDown(): void
    {
        $this->runFixtureCleanup(null, [
            'delete database fixtures' => function (): void {
                $this->deleteDatabaseFixtures();
            },
            'restore system configs' => function (): void {
                if ($this->systemConfigSnapshot !== null) {
                    $this->systemConfigSnapshot->restore();
                }
            },
            'restore AUTO_INCREMENT' => function (): void {
                $this->restoreAutoIncrement();
            },
            'verify table fingerprints' => function (): void {
                $this->withSchemaLock(function (): void {
                    $after = MySqlTableFingerprint::capture(self::FINGERPRINT_TABLES);
                    if ($after !== $this->tableFingerprints) {
                        throw new \RuntimeException('Payment table fingerprints were not restored.');
                    }
                });
            },
            'release fixture mutex' => function (): void {
                if ($this->fixtureMutex !== null) {
                    $this->fixtureMutex->releaseWithDisconnectFallback();
                }
            },
            'parent tearDown' => function (): void {
                parent::tearDown();
            },
        ]);
    }

    public function test_payment_gateway_adapter_contract(): void
    {
        $contract = new \ReflectionClass(PaymentGatewayAdapter::class);

        $this->assertTrue($contract->isInterface());
        $this->assertSame(
            ['createOrder', 'verifyCallback', 'parseCallback', 'acknowledge'],
            array_map(static function (\ReflectionMethod $method): string {
                return $method->getName();
            }, $contract->getMethods())
        );
        $this->assertSame(Request::class, (string) $contract->getMethod('verifyCallback')->getParameters()[0]->getType());
        $this->assertSame(Response::class, (string) $contract->getMethod('acknowledge')->getReturnType());
    }

    public function test_registry_is_singleton(): void
    {
        $this->assertSame(app(PaymentGatewayRegistry::class), app(PaymentGatewayRegistry::class));
    }

    public function test_registry_supports_legacy_aliases(): void
    {
        $registry = new PaymentGatewayRegistry();

        foreach (['tiger', 'tigerpay', 'wp', 'wppay', 'exlink_fb', 'exlink_bb', 'btb', 'passto', 'switch', 'otc'] as $alias) {
            $this->assertTrue($registry->supportsAlias($alias), 'Missing payment alias: ' . $alias);
        }
    }

    public function test_disabled_channel_resolves_null(): void
    {
        $channel = $this->channel('tiger');
        $channel->is_enabled = 0;

        $this->assertNull((new PaymentGatewayRegistry())->resolve($channel));
    }

    /** @dataProvider incompleteConfigProvider */
    public function test_incomplete_config_resolves_null(string $missingKey): void
    {
        $registry = new PaymentGatewayRegistry();
        $registry->register('fixture', new RegistryFixtureAdapter(), ['USD']);
        $channel = $this->channel('fixture');
        $config = $channel->config;
        unset($config[$missingKey]);
        $channel->config = $config;

        $this->assertNull($registry->resolve($channel));
    }

    public function incompleteConfigProvider(): array
    {
        return [
            'adapter' => ['adapter'],
            'merchant' => ['merchant_id'],
            'endpoint' => ['gateway_url'],
            'secret reference' => ['secret_reference'],
            'currency' => ['currency'],
            'amount unit' => ['amount_unit'],
            'notify route' => ['notify_route'],
            'return route' => ['return_route'],
        ];
    }

    public function test_registry_resolve_binds_adapter_and_validates_config(): void
    {
        $adapter = new RegistryFixtureAdapter();
        $registry = new PaymentGatewayRegistry();
        $registry->register('fixture', $adapter, ['USD']);
        $channel = $this->channel('fixture', 'fixture-gateway');

        $resolved = $registry->resolve($channel, 'fixture-gateway');

        $this->assertSame($adapter, $resolved['adapter']);
        $this->assertSame('USD', $resolved['config']['currency']);
        $this->assertNull($registry->resolve($channel, 'different-gateway'));
        $config = $channel->config;
        $config['currency'] = 'EUR';
        $channel->config = $config;
        $this->assertNull($registry->resolve($channel, 'fixture-gateway'));
    }

    public function test_registry_resolve_falls_back_to_app_id(): void
    {
        $registry = new PaymentGatewayRegistry();
        $registry->register('fixture', new RegistryFixtureAdapter(), ['USD']);
        $channel = $this->channel('fixture', 'fallback-gateway');
        $config = $channel->config;
        $config['merchant_id'] = '';
        $config['app_id'] = 'app-fallback';
        $config['secret_reference'] = '';
        $config['key_reference'] = 'env:PAYMENT_FALLBACK_KEY';
        $channel->config = $config;

        $this->assertNotNull($registry->resolve($channel, 'fallback-gateway'));
    }

    public function test_registry_resolves_pay_type_per_alias(): void
    {
        $expected = ['6' => 3, '7' => 2, '9' => 1, '10' => 2, '11' => 3];

        foreach ($expected as $alias => $payType) {
            $alias = (string) $alias;
            $registry = new PaymentGatewayRegistry();
            $registry->register($alias, new RegistryFixtureAdapter(), ['USD']);
            $resolved = $registry->resolve($this->channel($alias, $alias), $alias);
            $this->assertSame($payType, $resolved['config']['pay_type']);
        }
    }

    public function test_tiger_adapter_fails_closed_without_key_references(): void
    {
        $channel = $this->channel('tiger', '1');
        $config = $channel->config + [
            'gateway_code' => '1',
            'app_id' => 'tiger-registry-fixture',
            'charset' => 'utf-8',
            'method' => 'payq.trade.wap',
            'version' => '1.0.0',
        ];
        $channel->config = $config;
        $resolved = (new PaymentGatewayRegistry())->resolve($channel, '1');
        $this->assertNotNull($resolved);
        $this->assertInstanceOf(\App\Services\Payment\Gateways\TigerPayAdapter::class, $resolved['adapter']);
        $order = new DepositRecord();
        $order->forceFill([
            'local_order_no' => $this->order('tiger-registry-1001'),
            'gateway_code' => '1',
            'user_id' => $this->userId,
            'user_name' => 'tiger-registry-user',
            'amount' => '100.00',
            'actual_amount' => '700.00',
            'currency' => 'USD',
        ]);

        foreach ([
            'missing references' => $config,
            'unresolved references' => $config + [
                'app_private_key_reference' => 'env:PAYMENT_TIGER_REGISTRY_MISSING_PRIVATE',
                'server_public_key_reference' => 'env:PAYMENT_TIGER_REGISTRY_MISSING_PUBLIC',
            ],
        ] as $case => $createConfig) {
            putenv('PAYMENT_TIGER_REGISTRY_MISSING_PRIVATE');
            putenv('PAYMENT_TIGER_REGISTRY_MISSING_PUBLIC');
            try {
                $resolved['adapter']->createOrder($order, $createConfig);
                $this->fail('Tiger createOrder must fail closed for ' . $case . '.');
            } catch (\InvalidArgumentException $exception) {
                $this->assertNotSame('', $exception->getMessage());
            }
        }
    }

    public function test_payment_order_result_exposes_immutable_fields(): void
    {
        $result = new PaymentOrderResult(
            'fixture-gateway',
            'PROVIDER-1001',
            'https://provider.example.test/checkout/1001'
        );

        $this->assertSame('fixture-gateway', $result->gatewayCode());
        $this->assertSame('PROVIDER-1001', $result->providerOrderNumber());
        $this->assertSame('https://provider.example.test/checkout/1001', $result->redirectUrl());
        $this->assertFalse((new \ReflectionClass($result))->hasMethod('setRedirectUrl'));

        $this->expectException(\InvalidArgumentException::class);
        new PaymentOrderResult('fixture-gateway', 'PROVIDER-1002', 'javascript:alert(1)');
    }

    public function test_payment_order_result_rejects_dangerous_redirect(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new PaymentOrderResult(
            'fixture-gateway',
            'PROVIDER-1003',
            null,
            'https://provider.example.test/form',
            ['<script>' => 'bad']
        );
    }

    public function test_payment_callback_validates_identity_and_amount(): void
    {
        $callback = new PaymentCallback(
            'fixture-gateway',
            'DEP-1001',
            'PROVIDER-1001',
            'success',
            '100.00',
            'USD',
            'merchant-test',
            hash('sha256', 'fixture-payload')
        );

        $this->assertSame('DEP-1001', $callback->localOrderNumber());
        $this->assertSame('100.00', $callback->amount());
        $this->assertSame('success', $callback->status());

        $this->expectException(\InvalidArgumentException::class);
        new PaymentCallback(
            'fixture-gateway',
            'DEP-1001',
            'PROVIDER-1001',
            'paid-ish',
            '1e2',
            'USD',
            'merchant-test',
            'not-a-hash'
        );
    }

    /** @dataProvider invalidCallbackIdentityProvider */
    public function test_invalid_callback_identity_rejected(
        string $gateway,
        string $localOrder,
        string $providerOrder,
        string $currency
    ): void {
        $this->expectException(\InvalidArgumentException::class);
        new PaymentCallback(
            $gateway,
            $localOrder,
            $providerOrder,
            'success',
            '100.00',
            $currency,
            'merchant-test',
            hash('sha256', 'payload')
        );
    }

    public function invalidCallbackIdentityProvider(): array
    {
        return [
            'gateway' => ['gateway space', 'DEP-1001', 'PROVIDER-1001', 'USD'],
            'local order' => ['gateway', '../DEP-1001', 'PROVIDER-1001', 'USD'],
            'provider order' => ['gateway', 'DEP-1001', "PROVIDER\n1001", 'USD'],
            'currency' => ['gateway', 'DEP-1001', 'PROVIDER-1001', 'US D'],
        ];
    }

    /** @dataProvider invalidCallbackMerchantProvider */
    public function test_invalid_callback_merchant_rejected(string $merchant): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new PaymentCallback(
            'gateway',
            'DEP-1001',
            'PROVIDER-1001',
            'success',
            '100.00',
            'USD',
            $merchant,
            hash('sha256', 'payload')
        );
    }

    public function invalidCallbackMerchantProvider(): array
    {
        return [
            'space' => ['merchant test'],
            'control' => ["merchant\ntest"],
            'too long' => [str_repeat('m', 101)],
            'unsafe punctuation' => ['merchant<script>'],
        ];
    }

    public function test_provider_order_result_columns_exist(): void
    {
        $this->assertTrue(Schema::hasColumn('deposit_records', 'provider_order_result'));
        $this->assertTrue(Schema::hasColumn('deposit_records', 'provider_create_started_at'));
        $this->assertTrue(Schema::hasColumn('deposit_records', 'provider_create_attempts'));
    }

    public function test_form_options_hide_private_config_and_submission_creates_order(): void
    {
        $adapter = new RegistryFixtureAdapter();
        $registry = new PaymentGatewayRegistry();
        $registry->register('fixture', $adapter, ['USD']);
        $this->app->instance(PaymentGatewayRegistry::class, $registry);
        $login = UserLogin::where('user_id', $this->userId)->firstOrFail();

        $options = $this->withoutMiddleware([JwtAuthMiddleware::class, SingleSignOn::class])
            ->actingAs($login, 'user')
            ->getJson('/api/front/deposits/form-options');
        $options->assertOk()->assertJsonCount(1, 'data.channels');
        foreach (['merchant-task3', 'PAYMENT_TASK3_SECRET', 'https://private-provider.example.test/orders'] as $privateValue) {
            $this->assertStringNotContainsString($privateValue, $options->getContent());
        }

        $successKey = $this->key('success');
        $response = $this->withoutMiddleware([JwtAuthMiddleware::class, SingleSignOn::class])
            ->actingAs($login, 'user')
            ->withHeader('Idempotency-Key', $successKey)
            ->postJson('/api/front/deposits/submissions', [
                'amount' => '100.00',
                'channel' => $this->channelCode,
            ]);

        $response->assertOk()
            ->assertJsonPath('code', ResponseCode::CREATED)
            ->assertJsonPath('data.redirect_url', 'https://checkout.example.test/provider-order')
            ->assertJsonPath('data.payment_url', 'https://checkout.example.test/provider-order')
            ->assertJsonPath('data.open_blank', true)
            ->assertJsonPath('data.channel', $this->channelCode);
        $this->assertNotSame('', (string) $response->json('data.order_no'));
        $this->assertSame(1, $adapter->createOrderCalls);
        $this->assertDatabaseHas('deposit_records', [
            'user_id' => $this->userId,
            'payment_status' => 'pending',
            'channel_order_no' => 'PROVIDER-TASK3',
        ]);
        foreach (['merchant-task3', 'PAYMENT_TASK3_SECRET', 'https://private-provider.example.test/orders'] as $privateValue) {
            $this->assertStringNotContainsString($privateValue, $response->getContent());
        }
    }

    public function test_repeat_submission_returns_same_order(): void
    {
        $adapter = new RegistryFixtureAdapter();
        $registry = new PaymentGatewayRegistry();
        $registry->register('fixture', $adapter, ['USD']);
        $this->app->instance(PaymentGatewayRegistry::class, $registry);
        $login = UserLogin::where('user_id', $this->userId)->firstOrFail();
        $client = $this->withoutMiddleware([JwtAuthMiddleware::class, SingleSignOn::class])
            ->actingAs($login, 'user');

        $repeatKey = $this->key('repeat');
        $first = $client->withHeader('Idempotency-Key', $repeatKey)->postJson('/api/front/deposits/submissions', [
            'amount' => '100.00',
            'channel' => $this->channelCode,
        ]);
        $second = $client->withHeader('Idempotency-Key', $repeatKey)->postJson('/api/front/deposits/submissions', [
            'amount' => '100.00',
            'channel' => $this->channelCode,
        ]);

        $first->assertJsonPath('code', ResponseCode::CREATED);
        $second->assertJsonPath('code', ResponseCode::CREATED);
        $this->assertSame(1, $adapter->createOrderCalls);
        $this->assertSame($first->json('data.order_no'), $second->json('data.order_no'));
        $this->assertSame($first->json('data.payment_url'), $second->json('data.payment_url'));
        $this->assertSame($first->json('data.form_action'), $second->json('data.form_action'));
        $this->assertSame(1, DB::table('deposit_records')->where('idempotency_key', $repeatKey)->count());
    }

    public function test_amount_conflict_rejected(): void
    {
        $adapter = new RegistryFixtureAdapter();
        $registry = new PaymentGatewayRegistry();
        $registry->register('fixture', $adapter, ['USD']);
        $this->app->instance(PaymentGatewayRegistry::class, $registry);
        $key = $this->key('amount-conflict');

        $this->submitAsFixtureUser($key, '100.00', $this->channelCode)
            ->assertJsonPath('code', ResponseCode::CREATED);
        $this->submitAsFixtureUser($key, '101.00', $this->channelCode)
            ->assertJsonPath('code', ResponseCode::OPERATION_NOT_ALLOWED);

        $this->assertSame(1, $adapter->createOrderCalls);
        $this->assertSame(1, DB::table('deposit_records')
            ->where('user_id', $this->userId)
            ->where('idempotency_key', $key)
            ->count());
    }

    public function test_gateway_conflict_rejected(): void
    {
        $firstAdapter = new RegistryFixtureAdapter();
        $secondAdapter = new RegistryFixtureAdapter();
        $registry = new PaymentGatewayRegistry();
        $registry->register('fixture', $firstAdapter, ['USD']);
        $registry->register('fixture-alternate', $secondAdapter, ['USD']);
        $this->app->instance(PaymentGatewayRegistry::class, $registry);
        $this->insertChannel($this->alternateChannelCode, 'fixture-alternate');
        $key = $this->key('gateway-conflict');

        $this->submitAsFixtureUser($key, '100.00', $this->channelCode)
            ->assertJsonPath('code', ResponseCode::CREATED);
        $this->submitAsFixtureUser($key, '100.00', $this->alternateChannelCode)
            ->assertJsonPath('code', ResponseCode::OPERATION_NOT_ALLOWED);

        $this->assertSame(1, $firstAdapter->createOrderCalls);
        $this->assertSame(0, $secondAdapter->createOrderCalls);
        $this->assertSame(1, DB::table('deposit_records')
            ->where('user_id', $this->userId)
            ->where('idempotency_key', $key)
            ->count());
    }

    public function test_table_rows_snapshot_restores_owned_rows(): void
    {
        $existingKey = $this->unusedSystemConfigKey('existing');
        $newKey = $this->unusedSystemConfigKey('new');
        $initialSnapshot = TableRowsSnapshot::capture('system_configs', 'key', [$existingKey, $newKey]);

        try {
            $id = DB::table('system_configs')->insertGetId([
                'key' => $existingKey,
                'value' => 'original-value',
                'group' => 'original-group',
                'description' => 'original-description',
                'created_at' => 1234567001,
                'updated_at' => 1234567002,
                'deleted_at' => 1234567003,
            ]);
            $expected = (array) DB::table('system_configs')->where('id', $id)->first();
            $snapshot = TableRowsSnapshot::capture('system_configs', 'key', [$existingKey, $newKey]);

            DB::table('system_configs')->where('key', $existingKey)->delete();
            $replacementId = DB::table('system_configs')->insertGetId([
                'key' => $existingKey,
                'value' => 'mutated-value',
                'group' => 'mutated-group',
                'description' => 'mutated-description',
                'created_at' => 1234567101,
                'updated_at' => 1234567102,
                'deleted_at' => null,
            ]);
            $this->assertNotSame($id, $replacementId);
            DB::table('system_configs')->insert([
                'key' => $newKey,
                'value' => 'test-created',
                'group' => 'test',
                'description' => 'test-created',
                'created_at' => 1234567201,
                'updated_at' => 1234567202,
                'deleted_at' => null,
            ]);

            $snapshot->restore();
            $snapshot->restore();

            $this->assertSame($expected, (array) DB::table('system_configs')->where('key', $existingKey)->first());
            $this->assertFalse(DB::table('system_configs')->where('key', $newKey)->exists());
        } finally {
            $initialSnapshot->restore();
        }
    }

    public function test_terminal_status_replay_rejected(): void
    {
        $adapter = new RegistryFixtureAdapter();
        $registry = new PaymentGatewayRegistry();
        $registry->register('fixture', $adapter, ['USD']);
        $this->app->instance(PaymentGatewayRegistry::class, $registry);

        $key = $this->key('terminal-replay');
        $this->submitAsFixtureUser($key)->assertJsonPath('code', ResponseCode::CREATED);
        DB::table('deposit_records')->where('idempotency_key', $key)->update([
            'payment_status' => 'success',
        ]);

        $response = $this->submitAsFixtureUser($key);

        $response->assertJsonPath('code', ResponseCode::OPERATION_NOT_ALLOWED);
        $this->assertNull($response->json('data.payment_url'));
        $this->assertSame(1, $adapter->createOrderCalls);
    }

    public function test_in_progress_claim_blocks_retry(): void
    {
        $adapter = new RegistryFixtureAdapter();
        $registry = new PaymentGatewayRegistry();
        $registry->register('fixture', $adapter, ['USD']);
        $this->app->instance(PaymentGatewayRegistry::class, $registry);
        $user = \App\Models\UserInfo::where('user_id', $this->userId)->firstOrFail();
        $key = $this->key('in-progress');
        $order = app(PaymentOrderService::class)->createOrRetrieve(
            $user,
            ['code' => $this->channelCode, 'name' => 'Task3', 'exchange_rate' => '1.00000000', 'currency' => 'USD'],
            \App\Support\Money::fromDecimalString('100.00', '10.00', '500000.00'),
            $key
        )['order'];
        $order->payment_status = 'provider_create_in_progress';
        $order->save();

        $response = $this->submitAsFixtureUser($key);

        $response->assertJsonPath('code', ResponseCode::OPERATION_NOT_ALLOWED);
        $this->assertSame(0, $adapter->createOrderCalls);
    }

    public function test_stale_claim_marks_unknown_without_retry(): void
    {
        $adapter = new RegistryFixtureAdapter();
        $registry = new PaymentGatewayRegistry();
        $registry->register('fixture', $adapter, ['USD']);
        $this->app->instance(PaymentGatewayRegistry::class, $registry);
        $user = \App\Models\UserInfo::where('user_id', $this->userId)->firstOrFail();
        $key = $this->key('stale-claim');
        $order = app(PaymentOrderService::class)->createOrRetrieve(
            $user,
            ['code' => $this->channelCode, 'name' => 'Task3', 'exchange_rate' => '1.00000000', 'currency' => 'USD'],
            \App\Support\Money::fromDecimalString('100.00', '10.00', '500000.00'),
            $key
        )['order'];
        DB::table('deposit_records')->where('id', $order->id)->update([
            'payment_status' => 'provider_create_in_progress',
            'provider_create_started_at' => now()->subHour(),
            'provider_create_attempts' => 1,
        ]);

        $this->submitAsFixtureUser($key)
            ->assertJsonPath('code', ResponseCode::OPERATION_NOT_ALLOWED);

        $this->assertDatabaseHas('deposit_records', [
            'id' => $order->id,
            'payment_status' => 'provider_create_unknown',
            'provider_create_attempts' => 1,
        ]);
        $this->assertSame(0, $adapter->createOrderCalls);
    }

    public function test_provider_result_persistence_failure_blocks_retry(): void
    {
        $adapter = new RegistryFixtureAdapter();
        $registry = new PaymentGatewayRegistry();
        $registry->register('fixture', $adapter, ['USD']);
        $this->app->instance(PaymentGatewayRegistry::class, $registry);
        $key = $this->key('persist-failure');
        $event = 'eloquent.updating: ' . DepositRecord::class;
        Event::listen($event, function (DepositRecord $order): void {
            if (trim((string) $order->channel_order_no) !== '') {
                throw new \RuntimeException('provider result persistence failed');
            }
        });

        $this->withoutExceptionHandling();
        try {
            $this->submitAsFixtureUser($key);
            $this->fail('Expected provider result persistence to fail.');
        } catch (\RuntimeException $exception) {
            $this->assertSame('provider result persistence failed', $exception->getMessage());
        } finally {
            Event::forget($event);
            $this->withExceptionHandling();
        }

        $this->assertDatabaseHas('deposit_records', [
            'idempotency_key' => $key,
            'payment_status' => 'provider_create_in_progress',
            'channel_order_no' => '',
            'provider_create_attempts' => 1,
        ]);
        $this->submitAsFixtureUser($key)
            ->assertJsonPath('code', ResponseCode::OPERATION_NOT_ALLOWED);
        $this->assertSame(1, $adapter->createOrderCalls);
    }

    public function test_system_failure_propagates(): void
    {
        $registry = new PaymentGatewayRegistry();
        $registry->register('fixture', new RegistryFixtureAdapter(), ['USD']);
        $this->app->instance(PaymentGatewayRegistry::class, $registry);
        $service = Mockery::mock(PaymentOrderService::class);
        $service->shouldReceive('createOrRetrieve')->once()->andThrow(new \RuntimeException('database unavailable'));
        $this->app->instance(PaymentOrderService::class, $service);
        $login = UserLogin::where('user_id', $this->userId)->firstOrFail();

        $this->withoutExceptionHandling();
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('database unavailable');
        $this->withoutMiddleware([JwtAuthMiddleware::class, SingleSignOn::class])
            ->actingAs($login, 'user')
            ->withHeader('Idempotency-Key', $this->key('system-failure'))
            ->postJson('/api/front/deposits/submissions', [
                'amount' => '100.00',
                'channel' => $this->channelCode,
            ]);
    }

    public function test_provider_failure_logged_without_payload_leak(): void
    {
        Log::spy();
        $adapter = new RegistryFixtureAdapter(true);
        $registry = new PaymentGatewayRegistry();
        $registry->register('fixture', $adapter, ['USD']);
        $this->app->instance(PaymentGatewayRegistry::class, $registry);
        $login = UserLogin::where('user_id', $this->userId)->firstOrFail();
        $key = $this->key('provider-failure');

        $response = $this->withoutMiddleware([JwtAuthMiddleware::class, SingleSignOn::class])
            ->actingAs($login, 'user')
            ->withHeader('Idempotency-Key', $key)
            ->postJson('/api/front/deposits/submissions', [
                'amount' => '100.00',
                'channel' => $this->channelCode,
            ]);

        $response->assertOk()->assertJsonPath('code', ResponseCode::OPERATION_NOT_ALLOWED);
        $this->assertNotSame(ResponseCode::CREATED, $response->json('code'));
        $this->assertDatabaseHas('deposit_records', [
            'user_id' => $this->userId,
            'idempotency_key' => $key,
            'payment_status' => 'provider_create_unknown',
            'settlement_status' => 'pending',
        ]);
        Log::shouldHaveReceived('error')->once()->withArgs(function (string $message, array $context): bool {
            return $message === 'front.payment.provider_create_failed'
                && $context['gateway'] === $this->channelCode
                && $context['exception_class'] === \RuntimeException::class
                && !array_key_exists('exception_message', $context)
                && !array_key_exists('payload', $context);
        });
    }

    public function test_provider_order_result_migration_documented(): void
    {
        $source = file_get_contents(database_path('migrations/2026_07_11_000004_add_provider_order_result_to_deposit_records.php')) ?: '';

        $this->assertStringContainsString("Schema::hasColumn('deposit_records', 'provider_order_result')", $source);
        $this->assertStringContainsString("dropColumn('provider_order_result')", $source);
    }

    private function submitAsFixtureUser(
        string $idempotencyKey,
        string $amount = '100.00',
        string $channel = null
    )
    {
        $login = UserLogin::where('user_id', $this->userId)->firstOrFail();

        return $this->withoutMiddleware([JwtAuthMiddleware::class, SingleSignOn::class])
            ->actingAs($login, 'user')
            ->withHeader('Idempotency-Key', $idempotencyKey)
            ->postJson('/api/front/deposits/submissions', [
                'amount' => $amount,
                'channel' => $channel ?? $this->channelCode,
            ]);
    }

    private function channel(string $adapter, string $code = '1'): PaymentChannel
    {
        $channel = new PaymentChannel();
        $channel->channel_code = $code;
        $channel->is_enabled = 1;
        $channel->config = [
            'adapter' => $adapter,
            'merchant_id' => 'merchant-test',
            'gateway_url' => 'https://provider.example.test/orders',
            'secret_reference' => 'env:PAYMENT_GATEWAY_SECRET',
            'currency' => 'USD',
            'amount_unit' => 'decimal',
            'notify_route' => 'front_api_payment_notify',
            'return_route' => 'front_api_payment_return',
        ];

        return $channel;
    }

    private function allowDeposits(): void
    {
        foreach ([
            'deposit_enabled' => '1',
            'deposit_weekend_enabled' => '1',
            'deposit_start_time' => '',
            'deposit_end_time' => '',
            'deposit_min_amount' => '10.00',
            'deposit_max_amount' => '500000.00',
        ] as $key => $value) {
            DB::table('system_configs')->updateOrInsert(['key' => $key], [
                'value' => $value,
                'group' => 'deposit',
                'description' => 'Payment Task 3 fixture',
                'created_at' => time(),
                'updated_at' => time(),
                'deleted_at' => null,
            ]);
        }
    }

    private function insertChannel(string $channelCode, string $adapter): void
    {
        DB::table('payment_channels')->insert([
            'name' => 'Payment Task 3 Channel',
            'channel_code' => $channelCode,
            'exchange_rate' => '1.00000000',
            'is_enabled' => 1,
            'sort' => 356,
            'config' => json_encode([
                'adapter' => $adapter,
                'merchant_id' => 'merchant-task3',
                'gateway_url' => 'https://private-provider.example.test/orders',
                'secret_reference' => 'env:PAYMENT_TASK3_SECRET',
                'currency' => 'USD',
                'amount_unit' => 'decimal',
                'notify_route' => 'front_api_payment_notify',
                'return_route' => 'front_api_payment_return',
                'min_amount' => '10.00',
                'max_amount' => '500000.00',
            ]),
            'created_at' => time(),
            'updated_at' => time(),
            'deleted_at' => null,
        ]);
    }

    private function insertUser(): void
    {
        $loginId = DB::table('user_logins')->insertGetId([
            'user_id' => $this->userId,
            'email' => 'payment-task3-' . $this->userId . '@example.test',
            'password' => Hash::make('password'),
            'account_type' => 2,
            'role_id' => 0,
            'is_enabled' => 1,
            'is_cancelled' => 0,
            'source_type' => 0,
            'jwt_token_id' => '',
            'last_login_ip' => '',
            'last_login_at' => null,
            'created_at' => time(),
            'updated_at' => time(),
            'deleted_at' => null,
        ]);
        DB::table('user_infos')->insert([
            'user_id' => $this->userId,
            'login_id' => $loginId,
            'user_name' => 'payment-task3-user',
            'phone' => '13935550002',
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
            'created_at' => time(),
            'updated_at' => time(),
            'deleted_at' => null,
        ]);
    }

    private function deleteDatabaseFixtures(): void
    {
        if ($this->userId === null && $this->channelCode === null) {
            return;
        }

        $steps = [];
        if ($this->userId !== null) {
            $steps['deposit_records'] = function (): void {
                DB::table('deposit_records')->where('user_id', $this->userId)->delete();
            };
            $steps['user_infos'] = function (): void {
                DB::table('user_infos')->where('user_id', $this->userId)->delete();
            };
            $steps['user_logins'] = function (): void {
                DB::table('user_logins')->where('user_id', $this->userId)->delete();
            };
        }
        if ($this->channelCode !== null) {
            $steps['payment_channels'] = function (): void {
                DB::table('payment_channels')->whereIn('channel_code', [
                    $this->channelCode,
                    $this->alternateChannelCode,
                ])->delete();
            };
        }

        $failures = [];
        $firstFailure = null;
        foreach ($steps as $label => $step) {
            try {
                $step();
            } catch (\Throwable $exception) {
                $firstFailure = $firstFailure ?? $exception;
                $failures[] = $label . ': ' . $exception->getMessage();
            }
        }
        if ($failures !== []) {
            throw new \RuntimeException(
                'Payment registry fixture cleanup failed: ' . implode(' | ', $failures),
                0,
                $firstFailure
            );
        }
    }

    private function verifyTableFingerprints(): void
    {
        if ($this->tableFingerprints === []) {
            return;
        }

        $this->withSchemaLock(function (): void {
            $after = MySqlTableFingerprint::capture(self::FINGERPRINT_TABLES);
            if ($after !== $this->tableFingerprints) {
                throw new \RuntimeException('Payment table fingerprints were not restored.');
            }
        });
    }

    /** @param array<string, callable> $steps */
    private function runFixtureCleanup(\Throwable $primary = null, array $steps): void
    {
        $failures = [];
        $firstFailure = null;
        foreach ($steps as $label => $step) {
            try {
                $step();
            } catch (\Throwable $exception) {
                $firstFailure = $firstFailure ?? $exception;
                $failures[] = $label . ': ' . $exception->getMessage();
            }
        }

        if ($failures === []) {
            if ($primary !== null) {
                throw $primary;
            }

            return;
        }

        $messages = $primary === null ? [] : ['primary: ' . $primary->getMessage()];
        $messages = array_merge($messages, $failures);
        throw new \RuntimeException(
            'Payment registry fixture lifecycle cleanup failed: ' . implode(' | ', $messages),
            0,
            $primary ?? $firstFailure
        );
    }

    private function restoreAutoIncrement(): void
    {
        if ($this->autoIncrementSnapshot === null) {
            return;
        }

        $this->withSchemaLock(function (): void {
            $this->autoIncrementSnapshot->restore();
        });
    }

    private function withSchemaLock(callable $callback): void
    {
        $acquired = (int) DB::selectOne(
            'SELECT GET_LOCK(?, 30) AS acquired',
            [self::SCHEMA_LOCK],
            false
        )->acquired;
        $this->assertSame(1, $acquired, 'Could not acquire the payment fixture schema lock.');

        try {
            $callback();
        } finally {
            $released = (int) DB::selectOne(
                'SELECT RELEASE_LOCK(?) AS released',
                [self::SCHEMA_LOCK],
                false
            )->released;
            $this->assertSame(1, $released, 'Could not release the payment fixture schema lock.');
        }
    }

    private function key(string $suffix): string
    {
        if (array_key_exists($suffix, $this->allocatedKeys)) {
            return $this->allocatedKeys[$suffix];
        }

        $key = $this->keyPrefix . $suffix;
        if (strlen($key) > 100) {
            throw new \RuntimeException('Payment registry fixture idempotency key exceeds VARCHAR(100).');
        }
        if (DB::table('deposit_records')->useWritePdo()->where('idempotency_key', $key)->exists()) {
            throw new \RuntimeException('Payment registry fixture idempotency key is already occupied: ' . $key);
        }

        return $this->allocatedKeys[$suffix] = $key;
    }

    private function order(string $suffix): string
    {
        if (array_key_exists($suffix, $this->allocatedOrders)) {
            return $this->allocatedOrders[$suffix];
        }

        $order = $this->orderPrefix . $suffix;
        if (strlen($order) > 200) {
            throw new \RuntimeException('Payment registry fixture local order number exceeds VARCHAR(200).');
        }
        if (DB::table('deposit_records')->useWritePdo()->where('local_order_no', $order)->exists()) {
            throw new \RuntimeException('Payment registry fixture local order number is already occupied: ' . $order);
        }

        return $this->allocatedOrders[$suffix] = $order;
    }

    private function unusedSystemConfigKey(string $suffix): string
    {
        for ($attempt = 0; $attempt < 100; ++$attempt) {
            $key = 'payment-snapshot-' . $suffix . '-' . bin2hex(random_bytes(6));
            if (!DB::table('system_configs')->useWritePdo()->where('key', $key)->exists()) {
                return $key;
            }
        }

        throw new \RuntimeException('Unable to allocate an unused system config fixture key.');
    }

    private function initializeFixtureIdentity(): void
    {
        for ($attempt = 0; $attempt < 100; ++$attempt) {
            $this->userId = random_int(700000000, 899999999);
            $email = 'payment-task3-' . $this->userId . '@example.test';
            $occupied = DB::table('user_logins')->useWritePdo()->where('user_id', $this->userId)->exists()
                || DB::table('user_logins')->useWritePdo()->where('email', $email)->exists()
                || DB::table('user_infos')->useWritePdo()->where('user_id', $this->userId)->exists()
                || DB::table('deposit_records')->useWritePdo()->where('user_id', $this->userId)->exists();
            if (!$occupied) {
                break;
            }
        }
        if ($occupied) {
            throw new \RuntimeException('Unable to allocate an unused payment registry fixture user.');
        }

        for ($attempt = 0; $attempt < 100; ++$attempt) {
            $suffix = bin2hex(random_bytes(6));
            $this->channelCode = 'payment-task3-' . $suffix;
            $this->alternateChannelCode = 'payment-task3-alt-' . $suffix;
            $this->keyPrefix = 'payment-task3-' . $suffix . '-';
            $this->orderPrefix = 'PAYMENT-TASK3-' . strtoupper($suffix) . '-';
            $channels = [$this->channelCode, $this->alternateChannelCode];
            $occupied = DB::table('payment_channels')->useWritePdo()->whereIn('channel_code', $channels)->exists()
                || DB::table('deposit_records')->useWritePdo()->whereIn('gateway_code', $channels)->exists()
                || DB::table('deposit_records')->useWritePdo()->where('idempotency_key', 'like', $this->keyPrefix . '%')->exists()
                || DB::table('deposit_records')->useWritePdo()->where('local_order_no', 'like', $this->orderPrefix . '%')->exists();
            if (!$occupied) {
                return;
            }
        }

        throw new \RuntimeException('Unable to allocate an unused payment registry fixture identity.');
    }
}

final class RegistryFixtureAdapter implements PaymentGatewayAdapter
{
    /**
     * createOrder 被调用次数。断言重试/幂等路径下适配器确实只被调用预期次数。
     * @var int
     */
    public $createOrderCalls = 0;
    /**
     * 是否在 createOrder 时抛出"Provider unavailable"。驱动下单失败后的状态回滚与失败关闭断言。
     * @var bool
     */
    private $failCreate;

    public function __construct(bool $failCreate = false)
    {
        $this->failCreate = $failCreate;
    }

    public function createOrder(DepositRecord $order, array $channelConfig): PaymentOrderResult
    {
        ++$this->createOrderCalls;
        if ($this->failCreate) {
            throw new \RuntimeException('Provider unavailable.');
        }

        return new PaymentOrderResult(
            (string) $order->gateway_code,
            'PROVIDER-TASK3',
            'https://checkout.example.test/provider-order'
        );
    }

    public function verifyCallback(Request $request, array $channelConfig): bool
    {
        return true;
    }

    public function parseCallback(Request $request, array $channelConfig): PaymentCallback
    {
        throw new \LogicException('Not used by registry contract tests.');
    }

    public function acknowledge(PaymentCallback $callback): Response
    {
        return new Response('OK');
    }
}
