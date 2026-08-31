<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/17
 * Time: 04:39
 */

/**
 * FrontDepositPaymentOrderIdempotencyClosureModuleTest
 *
 * 文件功能：
 * - 验证前台入金支付订单幂等闭环：金额 decimal 字符串校验与 bcmath、幂等键重放返回既有订单、竞态同额复用异额冲突、软删订单不可复用、渠道限制与硬ening 迁移幂等。
 * - 输入：HTTP 请求与事务回滚的数据库夹具；输出：PHPUnit 断言结果。
 * - 明确不负责：不覆盖真实 MT4 网关与线上支付/出金通道（由网关契约测试锁定）。
 */

namespace Tests\Feature;

use App\Constants\ResponseCode;
use App\Http\Middleware\JwtAuthMiddleware;
use App\Http\Middleware\SingleSignOn;
use App\Models\UserLogin;
use App\Models\UserInfo;
use App\Services\Payment\PaymentOrderService;
use App\Services\Payment\PaymentGatewayRegistry;
use App\Services\Payment\Gateways\WpPayAdapter;
use App\Support\Money;
use DomainException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use PDOException;
use RuntimeException;
use Tests\TestCase;
use Tests\Support\MySqlAutoIncrementSnapshot;
use Tests\Support\MySqlFixtureMutex;
use Tests\Support\MySqlIndexSnapshot;
use Tests\Support\MySqlTableFingerprint;
use Tests\Support\TableRowsSnapshot;

class FrontDepositPaymentOrderIdempotencyClosureModuleTest extends TestCase
{
    /**
     * 入金模块依赖的 system_configs 键集合（开关、时段、限额）。
     * setUp 捕获原始值、tearDown 恢复，保证测试对入金配置的改写不泄漏到其他用例。
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
     * 生成当前会话使用的入金幂等 schema 建议锁名称。
     *
     * 逻辑说明：
     * - 默认与历史运行器一致；PHPUNIT_LOCK_SUFFIX 用于避免与
     *   外部校验运行器（co_crmv5_verify 库）的同一全局锁名互相阻塞。
     *
     * @return string 当前会话的 schema 锁名。
     */
    private function schemaLockName(): string
    {
        return 'co_crmv5_payment_idempotency_schema_task4' . (string) getenv('PHPUNIT_LOCK_SUFFIX');
    }
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
     * 随机分配的未占用业务用户 ID，用于提交入金请求；按前缀清理 deposit_records 时以它为过滤条件。
     * @var int|null
     */
    private $userId;
    /**
     * 正常入金渠道码（payment-task2-{token}）。insertChannel 写入完整适配器配置，作为主用例渠道。
     * @var string|null
     */
    private $channelCode;
    /**
     * 缺失适配器配置的渠道码（payment-task2-incomplete-{token}）。
     * 驱动"渠道配置不完整/无适配器"的失败关闭分支。
     * @var string|null
     */
    private $incompleteChannelCode;
    /**
     * WpPayAdapter 渠道码（payment-task2-wp-{token}）。验证 WP 控制器渠道按手机号创建订单的链路。
     * @var string|null
     */
    private $wpChannelCode;
    /**
     * 随机夹具令牌。派生渠道码、幂等键前缀与订单号前缀，重复运行或并行时不会与既有数据撞唯一键。
     * @var string|null
     */
    private $fixtureToken;
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
     * 已被本夹具占用的用户号集合。分配新用户时跳过，避免用例内重复分配同一 user_id。
     * @var array<int, int>
     */
    private $reservedUserIds = [];
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
     * MySqlIndexSnapshot 捕获的 deposit_records 索引结构快照。tearDown 据此恢复，保证 DDL 不残留。
     * @var \Tests\Support\MySqlIndexSnapshot|null
     */
    private $indexSnapshot;
    /**
     * 幂等相关索引定义的 sha256 指纹。tearDown 比对，检测索引结构是否被意外漂移。
     * @var string|null
     */
    private $indexFingerprint;
    /**
     * deposit_records 的 AUTO_INCREMENT 快照。tearDown 恢复，防止夹具插入抬高自增计数。
     * @var \Tests\Support\MySqlAutoIncrementSnapshot|null
     */
    private $autoIncrementSnapshot;
    /**
     * 内层 MySQL 命名互斥锁（校验 runner 另有 OS 级互斥）。串行化夹具准备、DDL 与清理，
     * 避免并行进程互相踩踏。
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
                $this->indexFingerprint = $this->depositIndexFingerprint();
                $this->indexSnapshot = MySqlIndexSnapshot::capture(
                    'deposit_records',
                    ['idempotency_key', 'local_order_no'],
                    [
                        'deposit_records_idempotency_user_unique',
                        'deposit_records_idempotency_user_gateway_unique',
                        'deposit_records_local_order_no_unique',
                    ]
                );
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
            $this->insertChannel($this->channelCode, true);
        } catch (\Throwable $exception) {
            $this->abortFixtureSetup($exception);
        }
    }

    private function abortFixtureSetup(\Throwable $cause): void
    {
        $this->runFixtureCleanup($cause, [
            'delete database fixtures' => function (): void {
                $this->deleteFixtures();
            },
            'restore system configs' => function (): void {
                if ($this->systemConfigSnapshot !== null) {
                    $this->systemConfigSnapshot->restore();
                }
            },
            'restore schema and AUTO_INCREMENT' => function (): void {
                $this->restoreIndexes();
            },
            'verify table fingerprints' => function (): void {
                $this->withSchemaLock(function (): void {
                    $after = MySqlTableFingerprint::capture(self::FINGERPRINT_TABLES);
                    if ($after !== $this->tableFingerprints) {
                        throw new RuntimeException('Payment table fingerprints were not restored.');
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

    protected function tearDown(): void
    {
        $this->runFixtureCleanup(null, [
            'delete database fixtures' => function (): void {
                $this->deleteFixtures();
            },
            'restore system configs' => function (): void {
                $this->systemConfigSnapshot->restore();
            },
            'restore schema and AUTO_INCREMENT' => function (): void {
                $this->restoreIndexes();
            },
            'verify table fingerprints' => function (): void {
                $this->withSchemaLock(function (): void {
                    $after = MySqlTableFingerprint::capture(self::FINGERPRINT_TABLES);
                    if ($after !== $this->tableFingerprints) {
                        throw new RuntimeException('Payment table fingerprints were not restored.');
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

    /**
     * @dataProvider invalidMoneyProvider
     */
    public function test_invalid_money_decimal_strings_rejected(string $value): void
    {
        $this->expectException(\InvalidArgumentException::class);
        Money::fromDecimalString($value, '10.00', '500000.00');
    }

    public function invalidMoneyProvider(): array
    {
        return [
            'scientific notation' => ['1e3'],
            'three decimal places' => ['10.001'],
            'zero' => ['0'],
            'negative' => ['-10.00'],
            'below configured minimum' => ['9.99'],
            'above configured maximum' => ['500000.01'],
            'leading decimal point' => ['.50'],
            'trailing decimal point' => ['10.'],
        ];
    }

    public function test_money_from_decimal_string_normalizes(): void
    {
        $this->assertSame('10.00', Money::fromDecimalString('10', '10.00', '500000.00')->toDecimalString());
        $this->assertSame('10.10', Money::fromDecimalString('10.1', '10.00', '500000.00')->toDecimalString());
        $this->assertSame('500000.00', Money::fromDecimalString('500000.00', '10.00', '500000.00')->toDecimalString());
    }

    public function test_oversized_money_value_rejected(): void
    {
        try {
            Money::fromDecimalString('10000000000000000.00', '0.01', '10000000000000000.00');
            $this->fail('Expected an oversized money value to be rejected.');
        } catch (\InvalidArgumentException $exception) {
            $this->assertStringContainsString('DECIMAL(18,2)', $exception->getMessage());
        }

        $money = Money::fromDecimalString('9999999999999999.99', '0.01', '9999999999999999.99');
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('DECIMAL(18,2)');
        $money->multiplyByRate('2.00000000');
    }

    public function test_money_uses_bcmath_and_declares_strict_types(): void
    {
        $source = file_get_contents(app_path('Support/Money.php')) ?: '';

        $this->assertStringContainsString("function_exists('bcmul')", $source);
        $this->assertStringContainsString('LogicException', $source);
        $this->assertStringContainsString('declare(strict_types=1)', $source);
    }

    /**
     * @dataProvider invalidRequestAmountProvider
     */
    public function test_invalid_request_amount_rejected($amount): void
    {
        $response = $this->submit($amount, $this->key('invalid-' . md5((string) $amount)));

        $response->assertOk()->assertJsonPath('code', ResponseCode::VALIDATION_FAILED);
        $this->assertSame(0, DB::table('deposit_records')->where('user_id', $this->userId)->count());
    }

    public function invalidRequestAmountProvider(): array
    {
        return [
            'numeric JSON value is not a decimal string' => [100],
            'scientific notation' => ['1e3'],
            'three decimals' => ['100.001'],
            'zero' => ['0'],
            'negative' => ['-1.00'],
            'below minimum' => ['9.99'],
            'above maximum' => ['500000.01'],
        ];
    }

    public function test_same_idempotency_key_returns_existing_order(): void
    {
        $service = app(PaymentOrderService::class);
        $user = UserInfo::where('user_id', $this->userId)->firstOrFail();
        $amount = Money::fromDecimalString('120.10', '10.00', '500000.00');
        $key = $this->key('same-key');
        $first = $service->createOrRetrieve($user, $this->serviceChannel(), $amount, $key);
        $second = $service->createOrRetrieve($user, $this->serviceChannel(), $amount, $key);

        $this->assertTrue($first['created']);
        $this->assertFalse($second['created']);
        $this->assertSame($first['order']->local_order_no, $second['order']->local_order_no);
        $this->assertSame(1, DB::table('deposit_records')->where('user_id', $this->userId)->count());
        $this->assertDatabaseHas('deposit_records', [
            'user_id' => $this->userId,
            'gateway_code' => $this->channelCode,
            'idempotency_key' => $key,
            'amount' => '120.10',
            'payment_status' => 'pending',
            'settlement_status' => 'pending',
        ]);
    }

    public function test_channel_config_merchant_id_snapshot_used(): void
    {
        $channel = $this->serviceChannel();
        $channel['_config'] = [
            'merchant_id' => '   ',
            'app_id' => 'wp-app-only-merchant',
        ];

        $result = app(PaymentOrderService::class)->createOrRetrieve(
            UserInfo::where('user_id', $this->userId)->firstOrFail(),
            $channel,
            Money::fromDecimalString('100.00', '10.00', '500000.00'),
            $this->key('app-id-merchant-snapshot')
        );

        $this->assertTrue($result['created']);
        $this->assertSame('wp-app-only-merchant', $result['order']->merchant_id);
    }

    /** @dataProvider providerAmountSnapshotProvider */
    public function test_provider_amount_uses_currency_conversion(
        string $currency,
        string $expectedProviderAmount,
        string $key
    ): void {
        $channel = $this->serviceChannel();
        $channel['currency'] = $currency;
        $channel['_config'] = ['merchant_id' => 'provider-amount-merchant'];

        $result = app(PaymentOrderService::class)->createOrRetrieve(
            UserInfo::where('user_id', $this->userId)->firstOrFail(),
            $channel,
            Money::fromDecimalString('100.00', '10.00', '500000.00'),
            $this->key($key)
        );

        $this->assertSame($expectedProviderAmount, (string) $result['order']->provider_amount);
        $this->assertSame('250.00', (string) $result['order']->actual_amount);
    }

    public function providerAmountSnapshotProvider(): array
    {
        return [
            'USD keeps account amount' => ['USD', '100.00', 'provider-amount-usd'],
            'USDT keeps account amount' => ['USDT', '100.00', 'provider-amount-usdt'],
            'CNY uses converted amount' => ['CNY', '250.00', 'provider-amount-cny'],
        ];
    }

    public function test_idempotency_conflict_throws_domain_exception(): void
    {
        $service = app(PaymentOrderService::class);
        $user = UserInfo::where('user_id', $this->userId)->firstOrFail();
        $key = $this->key('conflict-key');
        $service->createOrRetrieve(
            $user,
            $this->serviceChannel(),
            Money::fromDecimalString('120.10', '10.00', '500000.00'),
            $key
        );

        try {
            $service->createOrRetrieve(
                $user,
                $this->serviceChannel(),
                Money::fromDecimalString('120.11', '10.00', '500000.00'),
                $key
            );
            $this->fail('Expected an idempotency conflict.');
        } catch (DomainException $exception) {
            $this->assertSame('idempotency_conflict', $exception->getMessage());
            $this->assertSame(1, DB::table('deposit_records')->where('user_id', $this->userId)->count());
        }
    }

    public function test_local_order_no_duplicate_query_exception_mapped(): void
    {
        $expected = $this->queryException(
            '23000',
            1062,
            "Duplicate entry for key 'deposit_records_local_order_no_unique'"
        );
        $service = new PaymentOrderService(function (array $attributes) use ($expected) {
            $this->insertOrderFromCompetingConnection($attributes);
            throw $expected;
        });

        DB::statement('SET SESSION TRANSACTION ISOLATION LEVEL READ COMMITTED');
        try {
            $service->createOrRetrieve(
                UserInfo::where('user_id', $this->userId)->firstOrFail(),
                $this->serviceChannel(),
                Money::fromDecimalString('130.00', '10.00', '500000.00'),
                $this->key('non-duplicate-error')
            );
            $this->fail('Expected the original QueryException.');
        } catch (QueryException $actual) {
            $this->assertSame($expected, $actual);
        } finally {
            DB::statement('SET SESSION TRANSACTION ISOLATION LEVEL REPEATABLE READ');
        }
    }

    public function test_idempotency_race_same_amount_returns_existing_order(): void
    {
        $key = $this->key('race-same-amount');
        $service = new PaymentOrderService(function (array $attributes) {
            $this->insertOrderFromCompetingConnection($attributes);
            throw $this->queryException(
                '23000',
                1062,
                "Duplicate entry for key 'deposit_records_idempotency_user_gateway_unique'"
            );
        });

        DB::statement('SET SESSION TRANSACTION ISOLATION LEVEL READ COMMITTED');
        try {
            $result = $service->createOrRetrieve(
                UserInfo::where('user_id', $this->userId)->firstOrFail(),
                $this->serviceChannel(),
                Money::fromDecimalString('140.00', '10.00', '500000.00'),
                $key
            );
        } finally {
            DB::statement('SET SESSION TRANSACTION ISOLATION LEVEL REPEATABLE READ');
        }

        $this->assertFalse($result['created']);
        $this->assertSame('140.00', (string) $result['order']->amount);
        $this->assertSame(1, DB::table('deposit_records')->where('idempotency_key', $key)->count());
    }

    public function test_idempotency_race_different_amount_conflicts(): void
    {
        $service = new PaymentOrderService(function (array $attributes) {
            $attributes['amount'] = '140.01';
            $this->insertOrderFromCompetingConnection($attributes);
            throw $this->queryException(
                '23000',
                1062,
                "Duplicate entry for key 'deposit_records_idempotency_user_gateway_unique'"
            );
        });

        DB::statement('SET SESSION TRANSACTION ISOLATION LEVEL READ COMMITTED');
        try {
            $this->expectException(DomainException::class);
            $this->expectExceptionMessage('idempotency_conflict');
            $service->createOrRetrieve(
                UserInfo::where('user_id', $this->userId)->firstOrFail(),
                $this->serviceChannel(),
                Money::fromDecimalString('140.00', '10.00', '500000.00'),
                $this->key('race-different-amount')
            );
        } finally {
            DB::statement('SET SESSION TRANSACTION ISOLATION LEVEL REPEATABLE READ');
        }
    }

    public function test_soft_deleted_order_not_reusable(): void
    {
        $service = app(PaymentOrderService::class);
        $user = UserInfo::where('user_id', $this->userId)->firstOrFail();
        $key = $this->key('soft-deleted-key');
        $created = $service->createOrRetrieve(
            $user,
            $this->serviceChannel(),
            Money::fromDecimalString('150.00', '10.00', '500000.00'),
            $key
        );
        $created['order']->delete();

        foreach (['150.00', '150.01'] as $amount) {
            try {
                $service->createOrRetrieve(
                    $user,
                    $this->serviceChannel(),
                    Money::fromDecimalString($amount, '10.00', '500000.00'),
                    $key
                );
                $this->fail('A soft-deleted payment order must not be reusable.');
            } catch (DomainException $exception) {
                $this->assertSame('idempotency_conflict', $exception->getMessage());
            }
        }

        $this->assertSame(1, DB::table('deposit_records')->where('idempotency_key', $key)->count());
    }

    public function test_submission_without_idempotency_key_rejected(): void
    {
        $login = UserLogin::where('user_id', $this->userId)->firstOrFail();
        $response = $this->withoutMiddleware([JwtAuthMiddleware::class, SingleSignOn::class])
            ->actingAs($login, 'user')
            ->postJson('/api/front/deposits/submissions', [
                'amount' => '100.00',
                'channel' => $this->channelCode,
            ]);

        $response->assertOk()->assertJsonPath('code', ResponseCode::VALIDATION_FAILED);
        $this->assertStringContainsString('Idempotency-Key', (string) $response->json('message'));
    }

    public function test_incomplete_channel_returns_operation_not_allowed(): void
    {
        DB::table('payment_channels')->where('channel_code', $this->channelCode)->delete();
        $this->insertChannel($this->incompleteChannelCode, false);

        $response = $this->submit('100.00', $this->key('missing-adapter'), $this->incompleteChannelCode);

        $response->assertOk()->assertJsonPath('code', ResponseCode::OPERATION_NOT_ALLOWED);
        $this->assertSame(0, DB::table('deposit_records')->where('user_id', $this->userId)->count());
        $this->assertStringNotContainsString('/payment/return/', $response->getContent());
    }

    public function test_form_options_hide_incomplete_channels(): void
    {
        $login = UserLogin::where('user_id', $this->userId)->firstOrFail();
        $response = $this->withoutMiddleware([JwtAuthMiddleware::class, SingleSignOn::class])
            ->actingAs($login, 'user')
            ->getJson('/api/front/deposits/form-options');

        $response->assertOk()
            ->assertJsonPath('code', ResponseCode::SUCCESS)
            ->assertJsonCount(0, 'data.channels');
    }

    public function test_wp_pay_controller_channel_creates_order_with_phone(): void
    {
        $controllerSource = file_get_contents(app_path('Http/Controllers/Front/DepositController.php')) ?: '';
        $this->assertStringNotContainsString('WpPayAdapter', $controllerSource);
        $this->assertStringNotContainsString('instanceof WpPayAdapter', $controllerSource);
        $code = $this->wpChannelCode;
        DB::table('payment_channels')->where('channel_code', $this->channelCode)->delete();
        $this->insertWpChannel($code);
        $registry = app(PaymentGatewayRegistry::class);
        $registry->register('wp-controller-fixture', new WpPayAdapter(static function (string $reference): ?string {
            return $reference === 'env:PAYMENT_WP_CONTROLLER_FIXTURE' ? 'wp-controller-fixture-key' : null;
        }), ['CNY']);
        Http::fake([
            'https://provider.example.test/wp/controller' => Http::response([
                'status' => 1,
                'data' => ['pay_url' => 'https://checkout.example.test/wp/controller'],
            ], 200),
            '*' => Http::response([], 599),
        ]);

        $response = $this->submit('100.00', $this->key('wp-controller-phone'), $code);

        $response->assertOk()->assertJsonPath('code', ResponseCode::CREATED);
        Http::assertSent(function ($request): bool {
            return $request->url() === 'https://provider.example.test/wp/controller'
                && ($request->data()['mobile'] ?? null) === '13935550001';
        });
        $order = DB::table('deposit_records')->where('user_id', $this->userId)->first();
        $this->assertNotNull($order);
        $this->assertSame('wp-controller-app', $order->merchant_id);
        $this->assertSame('250.00', (string) $order->provider_amount);
    }

    public function test_deposit_schema_already_hardened(): void
    {
        $this->withRestoredIndexes(function (): void {
        $this->hardenDepositIdempotencyPerUser();

        $database = DB::getDatabaseName();
        $columns = DB::table('information_schema.COLUMNS')->useWritePdo()
            ->where('TABLE_SCHEMA', $database)
            ->where('TABLE_NAME', 'deposit_records')
            ->get()
            ->keyBy('COLUMN_NAME');

        $this->assertSame('decimal', strtolower((string) $columns['amount']->DATA_TYPE));
        $this->assertSame(18, (int) $columns['amount']->NUMERIC_PRECISION);
        $this->assertSame(2, (int) $columns['amount']->NUMERIC_SCALE);
        $this->assertSame(8, (int) $columns['exchange_rate']->NUMERIC_SCALE);
        foreach (['idempotency_key', 'gateway_code', 'currency', 'payment_status', 'settlement_status', 'provider_payload_hash'] as $column) {
            $this->assertTrue($columns->has($column), 'Missing deposit_records.' . $column);
        }

        $engine = DB::table('information_schema.TABLES')->useWritePdo()
            ->where('TABLE_SCHEMA', $database)
            ->where('TABLE_NAME', 'deposit_records')
            ->value('ENGINE');
        $this->assertSame('InnoDB', $engine);

        $indexes = DB::select('SHOW INDEX FROM deposit_records', [], false);
        $uniqueIndexes = collect($indexes)->where('Non_unique', 0)->groupBy('Key_name')->map(function ($rows) {
            return $rows->sortBy('Seq_in_index')->pluck('Column_name')->values()->all();
        });
        $this->assertContains(['local_order_no'], $uniqueIndexes->values()->all());
        $this->assertContains(['idempotency_key', 'user_id'], $uniqueIndexes->values()->all());
        $this->assertNotContains(['idempotency_key', 'user_id', 'gateway_code'], $uniqueIndexes->values()->all());
        });
    }

    public function test_harden_migration_is_idempotent(): void
    {
        $this->withRestoredIndexes(function (): void {
        $this->assertPaymentSchemaAlreadyHardened();
        require_once database_path('migrations/2026_07_11_000003_harden_deposit_payment_orders.php');
        $migration = new \HardenDepositPaymentOrders();
        $migration->up();
        $migration->up();
        $this->hardenDepositIdempotencyPerUser();

        $source = file_get_contents(database_path('migrations/2026_07_11_000003_harden_deposit_payment_orders.php')) ?: '';
        $this->assertStringContainsString("case 'mysql'", $source);
        $this->assertStringContainsString("case 'sqlite'", $source);
        $this->assertStringContainsString("case 'pgsql'", $source);
        $this->assertStringContainsString("modifyMySqlColumnIfNeeded('idempotency_key'", $source);
        });
    }

    public function test_harden_migration_blocks_duplicate_local_order_no(): void
    {
        $this->withRestoredIndexes(function (): void {
        $indexName = 'deposit_records_local_order_no_unique';
        $this->dropIndexIfPresent($indexName);
        $ids = [];

        try {
            $localOrderNo = $this->order('external-order-reference');
            $ids[] = $this->insertLegacyOrder($localOrderNo);
            $ids[] = $this->insertLegacyOrder($localOrderNo);
            require_once database_path('migrations/2026_07_11_000003_harden_deposit_payment_orders.php');

            try {
                (new \HardenDepositPaymentOrders())->up();
                $this->fail('Expected duplicate real local order numbers to stop the migration.');
            } catch (RuntimeException $exception) {
                $this->assertStringContainsString('duplicate local_order_no', $exception->getMessage());
                $this->assertSame(
                    [$localOrderNo, $localOrderNo],
                    DB::table('deposit_records')->whereIn('id', $ids)->orderBy('id')->pluck('local_order_no')->all()
                );
            }
        } finally {
            DB::table('deposit_records')->whereIn('id', $ids)->delete();
        }
        });
    }

    public function test_harden_migration_blocks_generated_order_collision(): void
    {
        $this->withRestoredIndexes(function (): void {
        $indexName = 'deposit_records_local_order_no_unique';
        $this->dropIndexIfPresent($indexName);
        $ids = [];

        try {
            $emptyId = $this->insertLegacyOrder('');
            $ids[] = $emptyId;
            $generatedOrderNo = 'LEGACY-DEP-' . $emptyId;
            if (DB::table('deposit_records')->useWritePdo()->where('local_order_no', $generatedOrderNo)->exists()) {
                throw new RuntimeException('Generated legacy fixture order is already occupied: ' . $generatedOrderNo);
            }
            $ids[] = $this->insertLegacyOrder($generatedOrderNo);
            require_once database_path('migrations/2026_07_11_000003_harden_deposit_payment_orders.php');

            try {
                (new \HardenDepositPaymentOrders())->up();
                $this->fail('Expected the generated legacy order number collision to stop the migration.');
            } catch (RuntimeException $exception) {
                $this->assertStringContainsString('generated local_order_no', $exception->getMessage());
                $this->assertSame('', (string) DB::table('deposit_records')->where('id', $emptyId)->value('local_order_no'));
            }
        } finally {
            DB::table('deposit_records')->whereIn('id', $ids)->delete();
        }
        });
    }

    private function submit($amount, string $idempotencyKey, string $channel = null)
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

    /** @return array<string, string> */
    private function serviceChannel(): array
    {
        return [
            'code' => $this->channelCode,
            'name' => 'Payment Task 2 Channel',
            'exchange_rate' => '2.50000000',
            'currency' => 'USD',
        ];
    }

    private function insertOrderFromCompetingConnection(array $attributes): void
    {
        $connection = 'payment_task2_race';
        config(['database.connections.' . $connection => config('database.connections.' . DB::getDefaultConnection())]);
        DB::purge($connection);
        DB::connection($connection)->table('deposit_records')->insert($attributes + [
            'mt4_ticket' => 0,
            'channel_order_no' => '',
            'updated_by' => '',
            'created_at' => time(),
            'updated_at' => time(),
            'deleted_at' => null,
        ]);
        DB::disconnect($connection);
    }

    private function queryException(string $sqlState, int $driverCode, string $message): QueryException
    {
        $previous = new PDOException($message, $driverCode);
        $previous->errorInfo = [$sqlState, $driverCode, $message];

        return new QueryException('insert into deposit_records', [], $previous);
    }

    private function hardenDepositIdempotencyPerUser(): void
    {
        require_once database_path('migrations/2026_07_19_000005_harden_deposit_idempotency_per_user.php');
        (new \HardenDepositIdempotencyPerUser())->up();
    }

    private function assertPaymentSchemaAlreadyHardened(): void
    {
        $database = DB::getDatabaseName();
        $columns = DB::table('information_schema.COLUMNS')->useWritePdo()
            ->where('TABLE_SCHEMA', $database)
            ->where('TABLE_NAME', 'deposit_records')
            ->get()
            ->keyBy('COLUMN_NAME');
        foreach ([
            'amount' => ['decimal', 18, 2],
            'actual_amount' => ['decimal', 18, 2],
            'exchange_rate' => ['decimal', 18, 8],
        ] as $name => $expected) {
            $this->assertTrue($columns->has($name), 'Missing deposit_records.' . $name);
            $column = $columns[$name];
            $this->assertSame($expected[0], strtolower((string) $column->DATA_TYPE));
            $this->assertSame($expected[1], (int) $column->NUMERIC_PRECISION);
            $this->assertSame($expected[2], (int) $column->NUMERIC_SCALE);
        }
        foreach ([
            'idempotency_key' => ['varchar', 100],
            'gateway_code' => ['varchar', 50],
            'currency' => ['varchar', 10],
            'payment_status' => ['varchar', 30],
            'settlement_status' => ['varchar', 30],
            'provider_payload_hash' => ['char', 64],
        ] as $name => $expected) {
            $this->assertTrue($columns->has($name), 'Missing deposit_records.' . $name);
            $column = $columns[$name];
            $this->assertSame($expected[0], strtolower((string) $column->DATA_TYPE));
            $this->assertSame($expected[1], (int) $column->CHARACTER_MAXIMUM_LENGTH);
        }

        $engine = DB::table('information_schema.TABLES')->useWritePdo()
            ->where('TABLE_SCHEMA', $database)
            ->where('TABLE_NAME', 'deposit_records')
            ->value('ENGINE');
        $this->assertSame('InnoDB', $engine);
    }

    private function insertLegacyOrder(string $localOrderNo): int
    {
        $legacyUserId = $this->unusedLegacyUserId();

        return DB::table('deposit_records')->insertGetId([
            'user_id' => $legacyUserId,
            'user_name' => 'legacy-order-test',
            'mt4_ticket' => 0,
            'amount' => '10.00',
            'actual_amount' => '10.00',
            'exchange_rate' => '1.00000000',
            'channel_name' => 'Legacy Migration Test',
            'channel_order_no' => '',
            'local_order_no' => $localOrderNo,
            'status' => '01',
            'remarks' => '',
            'created_by' => 'test',
            'updated_by' => '',
            'created_at' => time(),
            'updated_at' => time(),
            'deleted_at' => null,
        ]);
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
                'description' => 'Payment Task 2 fixture',
                'created_at' => time(),
                'updated_at' => time(),
                'deleted_at' => null,
            ]);
        }
    }

    private function insertChannel(string $code, bool $complete): void
    {
        $config = [
            'gateway_url' => 'https://pay.example.test/create',
            'min_amount' => '10.00',
            'max_amount' => '500000.00',
        ];
        if ($complete) {
            $config += [
                'adapter' => 'fixture',
                'merchant_id' => 'merchant-task2',
                'secret_reference' => 'env:PAYMENT_TASK2_SECRET',
                'currency' => 'USD',
                'amount_unit' => 'decimal',
                'notify_route' => 'front_api_payment_notify',
                'return_route' => 'front_api_payment_return',
            ];
        }

        DB::table('payment_channels')->insert([
            'name' => 'Payment Task 2 Channel',
            'channel_code' => $code,
            'exchange_rate' => '2.50000000',
            'is_enabled' => 1,
            'sort' => 355,
            'config' => json_encode($config),
            'created_at' => time(),
            'updated_at' => time(),
            'deleted_at' => null,
        ]);
    }

    private function insertWpChannel(string $code): void
    {
        DB::table('payment_channels')->insert([
            'name' => 'Payment Task 4 WP Channel',
            'channel_code' => $code,
            'exchange_rate' => '2.50000000',
            'is_enabled' => 1,
            'sort' => 356,
            'config' => json_encode([
                'adapter' => 'wp-controller-fixture',
                'gateway_code' => $code,
                'app_id' => 'wp-controller-app',
                'gateway_url' => 'https://provider.example.test/wp/controller',
                'secret_reference' => 'env:PAYMENT_WP_CONTROLLER_FIXTURE',
                'callback_key_reference' => 'env:PAYMENT_WP_CONTROLLER_FIXTURE',
                'currency' => 'CNY',
                'amount_unit' => 'decimal',
                'notify_route' => 'front_api_payment_notify',
                'return_route' => 'front_api_payment_return',
                'payment_type' => '2',
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
        $email = 'payment-task2-' . $this->userId . '@example.test';
        $loginId = DB::table('user_logins')->insertGetId([
            'user_id' => $this->userId,
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
            'created_at' => time(),
            'updated_at' => time(),
            'deleted_at' => null,
        ]);

        DB::table('user_infos')->insert([
            'user_id' => $this->userId,
            'login_id' => $loginId,
            'user_name' => 'payment-task2-user',
            'phone' => '13935550001',
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

    private function deleteFixtures(): void
    {
        if ($this->userId === null && $this->reservedUserIds === [] && $this->channelCode === null) {
            return;
        }

        $steps = [];
        if ($this->reservedUserIds !== []) {
            $steps['deposit_records'] = function (): void {
                DB::table('deposit_records')->whereIn('user_id', $this->reservedUserIds)->delete();
            };
        }
        if ($this->userId !== null) {
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
                    $this->incompleteChannelCode,
                    $this->wpChannelCode,
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
            throw new RuntimeException(
                'Front deposit fixture cleanup failed: ' . implode(' | ', $failures),
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
                // 输出具体差异表与差异维度，便于定位是残留数据、结构还是自增序列未还原。
                $diffs = [];
                foreach (self::FINGERPRINT_TABLES as $table) {
                    $before = $this->tableFingerprints[$table] ?? null;
                    $current = $after[$table] ?? null;
                    if ($before === null || $current === null || $before === $current) {
                        continue;
                    }
                    $delta = [];
                    foreach (array_keys($before) as $dimension) {
                        $old = $before[$dimension] ?? null;
                        $new = $current[$dimension] ?? null;
                        if ($old !== $new) {
                            $delta[] = $dimension . ':' . (is_scalar($old) ? $old : '?') . '->'
                                . (is_scalar($new) ? $new : '?');
                        }
                    }
                    $diffs[] = $table . '{' . implode(',', $delta) . '}';
                }
                throw new RuntimeException('Payment table fingerprints were not restored: ' . implode(' | ', $diffs));
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
        throw new RuntimeException(
            'Front deposit fixture lifecycle cleanup failed: ' . implode(' | ', $messages),
            0,
            $primary ?? $firstFailure
        );
    }

    private function restoreIndexes(): void
    {
        if ($this->indexSnapshot === null && $this->autoIncrementSnapshot === null) {
            return;
        }

        $this->withSchemaLock(function (): void {
            $steps = [];
            if ($this->indexSnapshot !== null) {
                $steps['restore deposit indexes'] = function (): void {
                    $this->indexSnapshot->restore();
                    $this->assertSame(
                        $this->indexFingerprint,
                        $this->depositIndexFingerprint(),
                        'The original deposit index definitions were not restored.'
                    );
                };
            }
            if ($this->autoIncrementSnapshot !== null) {
                $steps['restore AUTO_INCREMENT'] = function (): void {
                    $this->autoIncrementSnapshot->restore();
                };
            }

            $this->runFixtureCleanup(null, $steps);
        });
    }

    private function withSchemaLock(callable $callback): void
    {
        $acquired = (int) DB::selectOne(
            'SELECT GET_LOCK(?, 30) AS acquired',
            [$this->schemaLockName()],
            false
        )->acquired;
        $this->assertSame(1, $acquired, 'Could not acquire the deposit schema lock.');

        try {
            $callback();
        } finally {
            $released = (int) DB::selectOne(
                'SELECT RELEASE_LOCK(?) AS released',
                [$this->schemaLockName()],
                false
            )->released;
            $this->assertSame(1, $released, 'Could not release the deposit schema lock.');
        }
    }

    private function withRestoredIndexes(callable $callback): void
    {
        $this->withSchemaLock(function () use ($callback): void {
            $fingerprint = $this->depositIndexFingerprint();
            $snapshot = MySqlIndexSnapshot::capture(
                'deposit_records',
                ['idempotency_key', 'local_order_no'],
                [
                    'deposit_records_idempotency_user_unique',
                    'deposit_records_idempotency_user_gateway_unique',
                    'deposit_records_local_order_no_unique',
                ]
            );

            try {
                $callback();
            } finally {
                $snapshot->restore();
                $this->assertSame(
                    $fingerprint,
                    $this->depositIndexFingerprint(),
                    'Scoped deposit index definitions were not restored.'
                );
            }
        });
    }

    private function depositIndexFingerprint(): string
    {
        $row = DB::selectOne('SHOW CREATE TABLE `deposit_records`', [], false);
        $createSql = (string) ($row->{'Create Table'} ?? '');
        if ($createSql === '') {
            throw new RuntimeException('SHOW CREATE TABLE returned no deposit_records definition.');
        }

        $definitions = [];
        foreach (preg_split('/\R/', $createSql) ?: [] as $line) {
            if (preg_match(
                '/^\s*((?:(?:UNIQUE|FULLTEXT|SPATIAL)\s+)?KEY\s+`((?:``|[^`])+)`\s+.+?)(?:,)?\s*$/i',
                $line,
                $matches
            ) !== 1) {
                continue;
            }
            $name = str_replace('``', '`', $matches[2]);
            $definition = $matches[1];
            if (!in_array($name, [
                'deposit_records_idempotency_user_unique',
                'deposit_records_idempotency_user_gateway_unique',
                'deposit_records_local_order_no_unique',
            ], true)
                && preg_match('/(?<![A-Za-z0-9_])(?:idempotency_key|local_order_no)(?![A-Za-z0-9_])/i', $definition) !== 1) {
                continue;
            }
            $definitions[$name] = $definition;
        }
        ksort($definitions);

        return hash('sha256', json_encode($definitions, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
    }

    private function dropIndexIfPresent(string $name): void
    {
        if (preg_match('/^[A-Za-z0-9_]+$/', $name) !== 1) {
            throw new RuntimeException('Unsafe deposit fixture index name: ' . $name);
        }
        $present = collect(DB::select('SHOW INDEX FROM `deposit_records`', [], false))
            ->contains('Key_name', $name);
        if (!$present) {
            return;
        }

        DB::statement('ALTER TABLE `deposit_records` DROP INDEX `' . $name . '`');
    }

    private function key(string $suffix): string
    {
        if (array_key_exists($suffix, $this->allocatedKeys)) {
            return $this->allocatedKeys[$suffix];
        }

        $key = $this->keyPrefix . $suffix;
        if (strlen($key) > 100) {
            throw new RuntimeException('Front deposit fixture idempotency key exceeds VARCHAR(100).');
        }
        if (DB::table('deposit_records')->useWritePdo()->where('idempotency_key', $key)->exists()) {
            throw new RuntimeException('Front deposit fixture idempotency key is already occupied: ' . $key);
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
            throw new RuntimeException('Front deposit fixture local order number exceeds VARCHAR(200).');
        }
        if (DB::table('deposit_records')->useWritePdo()->where('local_order_no', $order)->exists()) {
            throw new RuntimeException('Front deposit fixture local order number is already occupied: ' . $order);
        }

        return $this->allocatedOrders[$suffix] = $order;
    }

    private function initializeFixtureIdentity(): void
    {
        $this->userId = $this->unusedLegacyUserId();

        for ($attempt = 0; $attempt < 100; ++$attempt) {
            $this->fixtureToken = bin2hex(random_bytes(6));
            $this->channelCode = 'payment-task2-' . $this->fixtureToken;
            $this->incompleteChannelCode = 'payment-task2-incomplete-' . $this->fixtureToken;
            $this->wpChannelCode = 'payment-task2-wp-' . $this->fixtureToken;
            $this->keyPrefix = 'payment-task2-' . $this->fixtureToken . '-';
            $this->orderPrefix = 'PAYMENT-TASK2-' . strtoupper($this->fixtureToken) . '-';
            $channelCodes = [
                $this->channelCode,
                $this->incompleteChannelCode,
                $this->wpChannelCode,
            ];
            $occupied = DB::table('payment_channels')->useWritePdo()->whereIn('channel_code', $channelCodes)->exists()
                || DB::table('deposit_records')->useWritePdo()->whereIn('gateway_code', $channelCodes)->exists()
                || DB::table('deposit_records')->useWritePdo()->where('idempotency_key', 'like', $this->keyPrefix . '%')->exists()
                || DB::table('deposit_records')->useWritePdo()->where('local_order_no', 'like', $this->orderPrefix . '%')->exists();
            if (!$occupied) {
                return;
            }
        }

        throw new RuntimeException('Unable to allocate an unused front deposit fixture identity.');
    }

    private function unusedLegacyUserId(): int
    {
        for ($attempt = 0; $attempt < 100; ++$attempt) {
            $userId = random_int(700000000, 899999999);
            $email = 'payment-task2-' . $userId . '@example.test';
            $occupied = in_array($userId, $this->reservedUserIds, true)
                || DB::table('user_logins')->useWritePdo()->where('user_id', $userId)->exists()
                || DB::table('user_logins')->useWritePdo()->where('email', $email)->exists()
                || DB::table('user_infos')->useWritePdo()->where('user_id', $userId)->exists()
                || DB::table('deposit_records')->useWritePdo()->where('user_id', $userId)->exists();
            if ($occupied) {
                continue;
            }

            $this->reservedUserIds[] = $userId;

            return $userId;
        }

        throw new RuntimeException('Unable to allocate an unused front deposit fixture user.');
    }
}
