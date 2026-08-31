<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/02
 * Time: 03:48
 */

/**
 * PaymentOrderCrossGatewayIdempotencyClosureModuleTest
 *
 * 文件功能：
 * - 验证支付订单跨网关幂等闭环：跨网关幂等冲突、同网关金额冲突、重放返回同一订单、同键不同用户允许、软删键保留占用、真实 MySQL 竞态单赢家、规范化索引迁移幂等与 down 还原。
 * - 输入：HTTP 请求与事务回滚的数据库夹具；输出：PHPUnit 断言结果。
 * - 明确不负责：不覆盖真实 MT4 网关与线上支付/出金通道（由网关契约测试锁定）。
 */

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\DepositRecord;
use App\Models\UserInfo;
use App\Services\Payment\PaymentOrderService;
use App\Support\Money;
use DomainException;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Tests\TestCase;
use Tests\Support\MySqlAutoIncrementSnapshot;
use Tests\Support\MySqlFixtureMutex;
use Tests\Support\MySqlIndexSnapshot;
use Tests\Support\MySqlTableFingerprint;

class PaymentOrderCrossGatewayIdempotencyClosureModuleTest extends TestCase
{
    /**
     * 迁移要建立的目标唯一索引（user_id + idempotency_key）。用例断言迁移后该索引存在且定义正确，
     * 即幂等约束从"渠道内唯一"升级为"用户内唯一"。
     * @var string
     */
    private const TARGET_INDEX = 'deposit_records_idempotency_user_unique';
    /**
     * 迁移要移除的旧唯一索引（含 gateway 维度）。用例断言其被删除，避免旧约束继续拦截跨渠道复用。
     * @var string
     */
    private const LEGACY_INDEX = 'deposit_records_idempotency_user_gateway_unique';
    /**
     * 本模块 DDL 专用的 MySQL 命名锁。索引增删没有事务保护，必须持锁串行化，
     * 防止并行进程同时修改表结构造成互相覆盖。
     * @var string
     */
    private const SCHEMA_LOCK = 'co_crmv5_payment_idempotency_schema_task4';
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
     * 随机分配的未占用业务用户 A（700000000-899999999 段）。与 userB 一起验证幂等键的用户隔离边界；
     * deleteOrders 按这两个用户清理 deposit_records。
     * @var int|null
     */
    private $userA;
    /**
     * 随机分配的未占用业务用户 B，与 userA 共用键空间作对照，证明同键不同用户互不影响。
     * @var int|null
     */
    private $userB;
    /**
     * 幂等键前缀（payment-task4-{token}-）。分配时校验长度不超过 VARCHAR(100) 且未被占用；
     * deleteOrders 按前缀精确清理本夹具订单。
     * @var string|null
     */
    private $keyPrefix;
    /**
     * 本地订单号前缀。分配时校验长度不超过 VARCHAR(200) 且未被占用，避免与既有订单冲突。
     * @var string|null
     */
    private $orderPrefix;
    /**
     * 随机夹具令牌（12 位十六进制）。派生渠道码与前缀，重复运行或并行时不会与既有数据撞唯一键。
     * @var string|null
     */
    private $fixtureToken;
    /**
     * 渠道 A 的 channel_code（payment-task4-a-{token}）。与渠道 B 使用同一幂等键，验证跨网关复用行为。
     * @var string|null
     */
    private $gatewayA;
    /**
     * 渠道 B 的 channel_code（payment-task4-b-{token}），与 gatewayA 构成跨网关对照。
     * @var string|null
     */
    private $gatewayB;
    /**
     * suffix => 幂等键 的分配缓存。同一 suffix 复用同一键，并保证键长度合规且未在库中被占用。
     * @var array<string, string>
     */
    private $allocatedKeys = [];
    /**
     * suffix => 本地订单号 的分配缓存。同一 suffix 复用同一单号，并校验长度与占用情况。
     * @var array<string, string>
     */
    private $allocatedOrders = [];
    /**
     * MySqlIndexSnapshot 捕获的 deposit_records 索引结构快照（目标索引与旧索引）。
     * tearDown 据此恢复索引，保证 DDL 测试不残留结构变更。
     * @var \Tests\Support\MySqlIndexSnapshot|null
     */
    private $indexSnapshot;
    /**
     * 幂等相关索引定义（SHOW CREATE TABLE 提取）的 sha256 指纹。tearDown 比对，
     * 检测索引结构是否被意外漂移。
     * @var string|null
     */
    private $indexFingerprint;
    /**
     * deposit_records 的 AUTO_INCREMENT 快照。tearDown 恢复，防止夹具插入抬高自增计数。
     * @var \Tests\Support\MySqlAutoIncrementSnapshot|null
     */
    private $autoIncrementSnapshot;
    /**
     * 内层 MySQL 命名互斥锁（校验 runner 另有 OS 级互斥）。串行化本测试的夹具准备、DDL 与清理，
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
                $this->indexFingerprint = $this->idempotencyIndexFingerprint();
                $this->indexSnapshot = MySqlIndexSnapshot::capture(
                    'deposit_records',
                    ['idempotency_key'],
                    [self::TARGET_INDEX, self::LEGACY_INDEX]
                );
                $this->autoIncrementSnapshot = MySqlAutoIncrementSnapshot::capture([
                    'deposit_records',
                ]);
            });
        } catch (\Throwable $exception) {
            $this->abortFixtureSetup($exception);
        }
    }

    private function abortFixtureSetup(\Throwable $cause): void
    {
        $failures = [];
        $this->recordLifecycleStep('delete deposit orders', function (): void {
            $this->deleteOrders();
        }, $failures);
        $this->recordLifecycleStep('restore schema and AUTO_INCREMENT', function (): void {
            $this->restoreSchemaState();
        }, $failures);
        $this->recordLifecycleStep('verify table fingerprints', function (): void {
            $this->verifyTableFingerprints();
        }, $failures);
        $this->recordLifecycleStep('release fixture mutex', function (): void {
            if ($this->fixtureMutex !== null) {
                $this->fixtureMutex->releaseWithDisconnectFallback();
            }
        }, $failures);
        $this->recordLifecycleStep('parent tearDown', function (): void {
            parent::tearDown();
        }, $failures);

        if ($failures !== []) {
            throw new RuntimeException(
                'Cross-gateway fixture setup failed: ' . implode(' | ', $failures),
                0,
                $cause
            );
        }

        throw $cause;
    }

    protected function tearDown(): void
    {
        $failures = [];
        $this->recordLifecycleStep('delete deposit orders', function (): void {
            $this->deleteOrders();
        }, $failures);
        $this->recordLifecycleStep('restore schema and AUTO_INCREMENT', function (): void {
            $this->restoreSchemaState();
        }, $failures);
        $this->recordLifecycleStep('verify table fingerprints', function (): void {
            $this->withSchemaLock(function (): void {
                $after = MySqlTableFingerprint::capture(self::FINGERPRINT_TABLES);
                if ($after !== $this->tableFingerprints) {
                    throw new RuntimeException('Payment table fingerprints were not restored.');
                }
            });
        }, $failures);
        $this->recordLifecycleStep('release fixture mutex', function (): void {
            if ($this->fixtureMutex !== null) {
                $this->fixtureMutex->releaseWithDisconnectFallback();
            }
        }, $failures);
        $this->recordLifecycleStep('parent tearDown', function (): void {
            parent::tearDown();
        }, $failures);

        $this->fixtureMutex = null;
        $this->tableFingerprints = [];
        if ($failures !== []) {
            throw new RuntimeException(
                'Cross-gateway fixture lifecycle cleanup failed: ' . implode(' | ', $failures)
            );
        }
    }

    public function test_cross_gateway_idempotency_conflict(): void
    {
        $creatorCalls = 0;
        $service = new PaymentOrderService(function (array $attributes) use (&$creatorCalls): DepositRecord {
            ++$creatorCalls;

            return DepositRecord::create($attributes);
        });
        $user = $this->user($this->userA, 'payment-cross-gateway-a');
        $amount = Money::fromDecimalString('120.10', '10.00', '500000.00');
        $key = $this->key('gateway-conflict');

        $first = $service->createOrRetrieve($user, $this->channel($this->gatewayA), $amount, $key);

        try {
            $service->createOrRetrieve($user, $this->channel($this->gatewayB), $amount, $key);
            $this->fail('Expected a cross-gateway idempotency conflict.');
        } catch (DomainException $exception) {
            $this->assertSame('idempotency_conflict', $exception->getMessage());
        }

        $this->assertTrue($first['created']);
        $this->assertSame(1, $creatorCalls);
        $this->assertSame(1, DB::table('deposit_records')
            ->where('user_id', $this->userA)
            ->where('idempotency_key', $key)
            ->count());
        $this->assertSame($this->gatewayA, DB::table('deposit_records')
            ->where('user_id', $this->userA)
            ->where('idempotency_key', $key)
            ->value('gateway_code'));
    }

    public function test_amount_conflict_blocks_same_gateway(): void
    {
        $creatorCalls = 0;
        $service = new PaymentOrderService(function (array $attributes) use (&$creatorCalls): DepositRecord {
            ++$creatorCalls;

            return DepositRecord::create($attributes);
        });
        $user = $this->user($this->userA, 'payment-cross-gateway-amount');
        $key = $this->key('amount-conflict');

        $service->createOrRetrieve(
            $user,
            $this->channel($this->gatewayA),
            Money::fromDecimalString('120.10', '10.00', '500000.00'),
            $key
        );

        try {
            $service->createOrRetrieve(
                $user,
                $this->channel($this->gatewayA),
                Money::fromDecimalString('120.11', '10.00', '500000.00'),
                $key
            );
            $this->fail('Expected an amount idempotency conflict.');
        } catch (DomainException $exception) {
            $this->assertSame('idempotency_conflict', $exception->getMessage());
        }

        $this->assertSame(1, $creatorCalls);
        $this->assertSame(1, DB::table('deposit_records')
            ->where('user_id', $this->userA)
            ->where('idempotency_key', $key)
            ->count());
    }

    public function test_replay_returns_same_order(): void
    {
        $service = app(PaymentOrderService::class);
        $user = $this->user($this->userA, 'payment-cross-gateway-replay');
        $amount = Money::fromDecimalString('130.00', '10.00', '500000.00');
        $key = $this->key('replay');

        $first = $service->createOrRetrieve($user, $this->channel($this->gatewayA), $amount, $key);
        $second = $service->createOrRetrieve($user, $this->channel($this->gatewayA), $amount, $key);

        $this->assertTrue($first['created']);
        $this->assertFalse($second['created']);
        $this->assertSame($first['order']->getKey(), $second['order']->getKey());
    }

    public function test_same_key_allowed_for_different_users(): void
    {
        $service = app(PaymentOrderService::class);
        $amount = Money::fromDecimalString('140.00', '10.00', '500000.00');
        $key = $this->key('different-users');

        $first = $service->createOrRetrieve(
            $this->user($this->userA, 'payment-cross-gateway-user-a'),
            $this->channel($this->gatewayA),
            $amount,
            $key
        );
        $second = $service->createOrRetrieve(
            $this->user($this->userB, 'payment-cross-gateway-user-b'),
            $this->channel($this->gatewayB),
            $amount,
            $key
        );

        $this->assertTrue($first['created']);
        $this->assertTrue($second['created']);
        $this->assertSame(2, DB::table('deposit_records')->where('idempotency_key', $key)->count());
    }

    public function test_soft_deleted_key_remains_reserved(): void
    {
        $service = app(PaymentOrderService::class);
        $user = $this->user($this->userA, 'payment-cross-gateway-soft-delete');
        $amount = Money::fromDecimalString('150.00', '10.00', '500000.00');
        $key = $this->key('soft-delete');

        $created = $service->createOrRetrieve($user, $this->channel($this->gatewayA), $amount, $key);
        $created['order']->delete();

        try {
            $service->createOrRetrieve($user, $this->channel($this->gatewayB), $amount, $key);
            $this->fail('Expected a soft-deleted idempotency key to remain reserved.');
        } catch (DomainException $exception) {
            $this->assertSame('idempotency_conflict', $exception->getMessage());
        }

        $this->assertSame(1, DB::table('deposit_records')
            ->where('user_id', $this->userA)
            ->where('idempotency_key', $key)
            ->count());
    }

    public function test_real_mysql_race_cross_gateway_single_winner(): void
    {
        $key = $this->key('real-race');
        $results = $this->runRealMysqlRace($key, [
            'worker-a' => $this->gatewayA,
            'worker-b' => $this->gatewayB,
        ]);

        $this->assertCount(1, array_filter($results, static function (array $result): bool {
            return $result['status'] === 'ok' && ($result['created'] ?? false) === true;
        }));
        $this->assertCount(1, array_filter($results, static function (array $result): bool {
            return $result['status'] === 'idempotency_conflict';
        }));
        $this->assertSame(1, DB::table('deposit_records')
            ->where('user_id', $this->userA)
            ->where('idempotency_key', $key)
            ->count());
    }

    public function test_real_mysql_replay_race_single_winner(): void
    {
        $key = $this->key('real-replay-race');
        $results = $this->runRealMysqlRace($key, [
            'worker-a' => $this->gatewayA,
            'worker-b' => $this->gatewayA,
        ]);

        $this->assertCount(1, array_filter($results, static function (array $result): bool {
            return $result['status'] === 'ok' && ($result['created'] ?? false) === true;
        }));
        $this->assertCount(1, array_filter($results, static function (array $result): bool {
            return $result['status'] === 'ok' && ($result['created'] ?? true) === false;
        }));
        $this->assertSame(1, DB::table('deposit_records')
            ->where('user_id', $this->userA)
            ->where('idempotency_key', $key)
            ->count());
    }

    public function test_migration_creates_canonical_index_idempotently(): void
    {
        $migration = $this->migration();

        $this->withRestoredIndexes(function () use ($migration): void {
            $migration->up();
            $migration->up();

            $target = $this->indexRows(self::TARGET_INDEX);
            $this->assertSame(['idempotency_key', 'user_id'], $target->pluck('Column_name')->values()->all());
            $this->assertSame([null, null], $target->pluck('Sub_part')->values()->all());
            $this->assertSame(0, (int) $target->first()->Non_unique);
            $this->assertTrue($this->indexRows(self::LEGACY_INDEX)->isEmpty());
        });
    }

    public function test_migration_blocks_duplicate_user_idempotency_values(): void
    {
        $migration = $this->migration();
        $key = $this->key('migration-duplicate');

        $this->withRestoredIndexes(function () use ($migration, $key): void {
            $this->deleteOrders();
            $this->restoreLegacyIndexOnly();
            $this->insertOrder(
                $this->userA,
                $key,
                $this->gatewayA,
                $this->order('migration-duplicate-a')
            );
            $this->insertOrder(
                $this->userA,
                $key,
                null,
                $this->order('migration-duplicate-b'),
                time()
            );

            try {
                $migration->up();
                $this->fail('Expected duplicate user/idempotency values to stop the migration.');
            } catch (RuntimeException $exception) {
                $this->assertStringContainsString('duplicate', strtolower($exception->getMessage()));
                $this->assertStringContainsString('idempotency', strtolower($exception->getMessage()));
                $this->assertTrue($this->indexRows(self::TARGET_INDEX)->isEmpty());
                $this->assertSame(
                    ['idempotency_key', 'user_id', 'gateway_code'],
                    $this->indexRows(self::LEGACY_INDEX)->pluck('Column_name')->values()->all()
                );
                $this->assertSame(2, DB::table('deposit_records')
                    ->where('user_id', $this->userA)
                    ->where('idempotency_key', $key)
                    ->count());
            } finally {
                $this->deleteOrders();
            }
        });
    }

    public function test_migration_fails_closed_on_unknown_legacy_index_definition(): void
    {
        $migration = $this->migration();

        $this->withRestoredIndexes(function () use ($migration): void {
            $migration->up();
            DB::statement(
                'ALTER TABLE deposit_records ADD INDEX ' . self::LEGACY_INDEX . ' (gateway_code)'
            );

            try {
                $migration->up();
                $this->fail('Expected an unknown legacy index definition to fail closed.');
            } catch (RuntimeException $exception) {
                $this->assertStringContainsString(self::LEGACY_INDEX, $exception->getMessage());
                $this->assertSame(
                    ['gateway_code'],
                    $this->indexRows(self::LEGACY_INDEX)->pluck('Column_name')->values()->all()
                );
                $this->assertSame(
                    ['idempotency_key', 'user_id'],
                    $this->indexRows(self::TARGET_INDEX)->pluck('Column_name')->values()->all()
                );
            } finally {
                $this->dropIndexIfPresent(self::LEGACY_INDEX);
            }
        });
    }

    /** @dataProvider unknownIdempotencyIndexProvider */
    public function test_unknown_idempotency_index_fails_closed(
        string $indexName,
        string $definition
    ): void {
        $migration = $this->migration();

        $this->withRestoredIndexes(function () use ($migration, $indexName, $definition): void {
            $migration->up();
            DB::statement(
                'ALTER TABLE deposit_records ADD INDEX `' . $indexName . '` ' . $definition
            );

            try {
                $migration->up();
                $this->fail('Expected an unknown idempotency index to fail closed.');
            } catch (RuntimeException $exception) {
                $this->assertStringContainsString($indexName, $exception->getMessage());
                $this->assertFalse($this->indexRows($indexName)->isEmpty());
            }
        });
    }

    public function unknownIdempotencyIndexProvider(): array
    {
        return [
            'non-unique canonical columns' => [
                'task4_unknown_idem_lookup',
                '(`idempotency_key`, `user_id`)',
            ],
            'prefix columns' => [
                'task4_unknown_idem_prefix',
                '(`idempotency_key`(32), `user_id`)',
            ],
            'wrong order' => [
                'task4_unknown_idem_order',
                '(`user_id`, `idempotency_key`)',
            ],
        ];
    }

    public function test_migration_down_restores_legacy_index(): void
    {
        $migration = $this->migration();

        $this->withRestoredIndexes(function () use ($migration): void {
            $migration->up();
            $migration->down();

            $this->assertSame(
                ['idempotency_key', 'user_id'],
                $this->indexRows(self::TARGET_INDEX)->pluck('Column_name')->values()->all()
            );
            $this->assertTrue($this->indexRows(self::LEGACY_INDEX)->isEmpty());
        });
    }

    private function user(int $userId, string $userName): UserInfo
    {
        return (new UserInfo())->forceFill([
            'user_id' => $userId,
            'user_name' => $userName,
        ]);
    }

    /** @return array<string, string> */
    private function channel(string $code): array
    {
        return [
            'code' => $code,
            'name' => 'Payment Task 4 ' . $code,
            'exchange_rate' => '1.00000000',
            'currency' => 'USD',
        ];
    }

    private function key(string $suffix): string
    {
        if (array_key_exists($suffix, $this->allocatedKeys)) {
            return $this->allocatedKeys[$suffix];
        }

        $key = $this->keyPrefix . $suffix;
        if (strlen($key) > 100) {
            throw new RuntimeException('Cross-gateway fixture idempotency key exceeds VARCHAR(100).');
        }
        if (DB::table('deposit_records')->useWritePdo()->where('idempotency_key', $key)->exists()) {
            throw new RuntimeException('Cross-gateway fixture idempotency key is already occupied: ' . $key);
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
            throw new RuntimeException('Cross-gateway fixture local order number exceeds VARCHAR(200).');
        }
        if (DB::table('deposit_records')->useWritePdo()->where('local_order_no', $order)->exists()) {
            throw new RuntimeException('Cross-gateway fixture local order number is already occupied: ' . $order);
        }

        return $this->allocatedOrders[$suffix] = $order;
    }

    private function deleteOrders(): void
    {
        if ($this->keyPrefix === null || ($this->userA === null && $this->userB === null)) {
            return;
        }

        DB::table('deposit_records')
            ->whereIn('user_id', [$this->userA, $this->userB])
            ->where('idempotency_key', 'like', $this->keyPrefix . '%')
            ->delete();
    }

    /** @param array<int, string> $failures */
    private function recordLifecycleStep(string $label, callable $step, array &$failures): void
    {
        try {
            $step();
        } catch (\Throwable $exception) {
            $failures[] = $label . ': ' . $exception->getMessage();
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
                throw new RuntimeException('Payment table fingerprints were not restored.');
            }
        });
    }

    /** @param array<string, callable> $steps */
    private function runFixtureCleanup(array $steps): void
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
        if ($failures !== []) {
            throw new RuntimeException(
                'Cross-gateway fixture lifecycle cleanup failed: ' . implode(' | ', $failures),
                0,
                $firstFailure
            );
        }
    }

    private function restoreSchemaState(): void
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
                        $this->idempotencyIndexFingerprint(),
                        'The original deposit idempotency index definitions were not restored.'
                    );
                };
            }
            if ($this->autoIncrementSnapshot !== null) {
                $steps['restore AUTO_INCREMENT'] = function (): void {
                    $this->autoIncrementSnapshot->restore();
                };
            }

            $this->runFixtureCleanup($steps);
        });
    }

    /**
     * @param array<string, string> $workerGateways
     * @return array<int, array<string, mixed>>
     */
    private function runRealMysqlRace(string $key, array $workerGateways): array
    {
        $this->assertCount(2, $workerGateways);
        return $this->withRestoredIndexes(function () use ($key, $workerGateways): array {
            $this->migration()->up();
            $this->assertCanonicalIndexPresent();

            $results = $this->runRealMysqlWorkers($key, $workerGateways);
            $this->assertCanonicalIndexPresent();

            return $results;
        });
    }

    /**
     * @param array<string, string> $workerGateways
     * @return array<int, array<string, mixed>>
     */
    private function runRealMysqlWorkers(string $key, array $workerGateways): array
    {
        $this->assertCanonicalIndexPresent();
        $temp = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'payment-idempotency-race-' . bin2hex(random_bytes(6));
        mkdir($temp, 0700, true);
        $worker = $temp . DIRECTORY_SEPARATOR . 'worker.php';
        $script = <<<'PHP'
<?php
declare(strict_types=1);

require getenv('COMPOSER_AUTOLOAD');
$app = require getenv('LARAVEL_BOOTSTRAP');
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$args = json_decode(base64_decode($argv[1]), true, 512, JSON_THROW_ON_ERROR);
file_put_contents($args['ready'], 'ready');

try {
    $deadline = microtime(true) + 15;
    while (!is_file($args['go']) && microtime(true) < $deadline) {
        usleep(10000);
    }
    if (!is_file($args['go'])) {
        throw new RuntimeException('Worker start barrier timed out.');
    }

    Illuminate\Support\Facades\DB::statement('SET SESSION TRANSACTION ISOLATION LEVEL READ COMMITTED');
    $service = new App\Services\Payment\PaymentOrderService(
        function (array $attributes) use ($args): App\Models\DepositRecord {
            file_put_contents($args['insert_ready'], 'ready');
            $deadline = microtime(true) + 15;
            do {
                $allReady = true;
                foreach ($args['all_insert_ready'] as $path) {
                    if (!is_file($path)) {
                        $allReady = false;
                        break;
                    }
                }
                if (!$allReady) {
                    usleep(10000);
                }
            } while (!$allReady && microtime(true) < $deadline);
            if (!$allReady) {
                throw new RuntimeException('Insert barrier timed out.');
            }

            return App\Models\DepositRecord::create($attributes);
        }
    );
    $user = (new App\Models\UserInfo())->forceFill([
        'user_id' => (int) $args['user_id'],
        'user_name' => 'payment-idempotency-worker',
    ]);
    $created = $service->createOrRetrieve(
        $user,
        [
            'code' => $args['gateway'],
            'name' => 'Payment Task 4 ' . $args['gateway'],
            'exchange_rate' => '1.00000000',
            'currency' => 'USD',
        ],
        App\Support\Money::fromDecimalString('160.00', '10.00', '500000.00'),
        $args['key']
    );
    $result = ['status' => 'ok', 'created' => (bool) $created['created']];
} catch (Throwable $exception) {
    $result = [
        'status' => $exception instanceof DomainException
            ? $exception->getMessage()
            : get_class($exception) . ':' . $exception->getMessage(),
    ];
}

file_put_contents($args['result'], json_encode($result, JSON_THROW_ON_ERROR));
PHP;
        file_put_contents($worker, $script);

        $go = $temp . DIRECTORY_SEPARATOR . 'go';
        $workers = [];
        foreach ($workerGateways as $workerName => $gateway) {
            $workers[$workerName] = [
                'gateway' => $gateway,
                'ready' => $temp . DIRECTORY_SEPARATOR . $workerName . '.ready',
                'insert_ready' => $temp . DIRECTORY_SEPARATOR . $workerName . '.insert-ready',
                'result' => $temp . DIRECTORY_SEPARATOR . $workerName . '.result',
            ];
        }
        $insertReady = array_column($workers, 'insert_ready');
        $processes = [];
        $results = [];

        try {
            foreach ($workers as $workerName => $paths) {
                $payload = base64_encode(json_encode([
                    'user_id' => $this->userA,
                    'key' => $key,
                    'gateway' => $paths['gateway'],
                    'ready' => $paths['ready'],
                    'insert_ready' => $paths['insert_ready'],
                    'all_insert_ready' => $insertReady,
                    'result' => $paths['result'],
                    'go' => $go,
                ], JSON_THROW_ON_ERROR));
                $pipes = [];
                $processes[$workerName] = proc_open(
                    [PHP_BINARY, $worker, $payload],
                    [
                        1 => ['file', $temp . DIRECTORY_SEPARATOR . $workerName . '.stdout', 'a'],
                        2 => ['file', $temp . DIRECTORY_SEPARATOR . $workerName . '.stderr', 'a'],
                    ],
                    $pipes,
                    base_path(),
                    array_merge(getenv(), [
                        'COMPOSER_AUTOLOAD' => base_path('vendor/autoload.php'),
                        'LARAVEL_BOOTSTRAP' => base_path('bootstrap/app.php'),
                        'APP_ENV' => (string) env('APP_ENV', 'testing'),
                        'DB_CONNECTION' => (string) env('DB_CONNECTION', 'mysql'),
                        'DB_HOST' => (string) env('DB_HOST'),
                        'DB_PORT' => (string) env('DB_PORT'),
                        'DB_DATABASE' => (string) env('DB_DATABASE'),
                        'DB_USERNAME' => (string) env('DB_USERNAME'),
                        'DB_PASSWORD' => (string) env('DB_PASSWORD'),
                    ])
                );
                $this->assertIsResource($processes[$workerName]);
            }

            $deadline = microtime(true) + 10;
            do {
                $ready = true;
                foreach ($workers as $paths) {
                    if (!is_file($paths['ready'])) {
                        $ready = false;
                        break;
                    }
                }
                if (!$ready) {
                    usleep(10000);
                }
            } while (!$ready && microtime(true) < $deadline);
            $this->assertTrue($ready, 'Payment race workers did not reach the start barrier.');
            $this->assertCanonicalIndexPresent();
            touch($go);

            $workerExitDeadline = microtime(true) + 20.0;
            foreach ($processes as $workerName => $process) {
                $exitCode = $this->waitForWorkerExit($process, (string) $workerName, $workerExitDeadline);
                unset($processes[$workerName]);
                $this->assertCanonicalIndexPresent();
                $this->assertSame(0, $exitCode, $workerName . ' worker failed.');
                $this->assertFileExists($workers[$workerName]['insert_ready']);
                $this->assertFileExists($workers[$workerName]['result']);
                $results[] = json_decode(
                    (string) file_get_contents($workers[$workerName]['result']),
                    true,
                    512,
                    JSON_THROW_ON_ERROR
                );
            }
        } finally {
            foreach ($processes as $process) {
                if (is_resource($process)) {
                    $this->terminateWorker($process);
                }
            }
            foreach (glob($temp . DIRECTORY_SEPARATOR . '*') ?: [] as $path) {
                @unlink($path);
            }
            @rmdir($temp);
        }

        return $results;
    }

    /** @return int */
    private function waitForWorkerExit($process, string $name, float $deadline): int
    {
        while (microtime(true) < $deadline) {
            $status = proc_get_status($process);
            if (!is_array($status)) {
                $this->terminateWorker($process);
                $this->fail($name . ' returned an invalid worker status.');
            }
            if (!($status['running'] ?? false)) {
                $reportedExitCode = (int) ($status['exitcode'] ?? -1);
                $closedExitCode = proc_close($process);

                return $closedExitCode !== -1 ? $closedExitCode : $reportedExitCode;
            }
            usleep(10000);
        }

        $this->terminateWorker($process);
        $this->fail($name . ' exceeded the worker exit deadline.');
    }

    private function terminateWorker($process): void
    {
        $status = proc_get_status($process);
        if (is_array($status) && ($status['running'] ?? false)) {
            @proc_terminate($process);
        }

        $deadline = microtime(true) + 2.0;
        while (microtime(true) < $deadline) {
            $status = proc_get_status($process);
            if (!is_array($status) || !($status['running'] ?? false)) {
                @proc_close($process);

                return;
            }
            usleep(10000);
        }

        @proc_terminate($process, 9);
        @proc_close($process);
    }

    private function assertCanonicalIndexPresent(): void
    {
        $rows = $this->indexRows(self::TARGET_INDEX);
        $this->assertSame(['idempotency_key', 'user_id'], $rows->pluck('Column_name')->values()->all());
        $this->assertSame(0, (int) $rows->first()->Non_unique);
        $this->assertSame([null, null], $rows->pluck('Sub_part')->values()->all());
    }

    private function initializeFixtureIdentity(): void
    {
        $reservedUsers = [];
        $this->userA = $this->unusedUserId($reservedUsers);
        $reservedUsers[] = $this->userA;
        $this->userB = $this->unusedUserId($reservedUsers);

        for ($attempt = 0; $attempt < 100; ++$attempt) {
            $this->fixtureToken = bin2hex(random_bytes(6));
            $this->gatewayA = 'payment-task4-a-' . $this->fixtureToken;
            $this->gatewayB = 'payment-task4-b-' . $this->fixtureToken;
            $this->keyPrefix = 'payment-task4-' . $this->fixtureToken . '-';
            $this->orderPrefix = 'PAYMENT-TASK4-' . strtoupper($this->fixtureToken) . '-';
            $gateways = [$this->gatewayA, $this->gatewayB];
            $occupied = DB::table('payment_channels')->useWritePdo()->whereIn('channel_code', $gateways)->exists()
                || DB::table('deposit_records')->useWritePdo()->whereIn('gateway_code', $gateways)->exists()
                || DB::table('deposit_records')->useWritePdo()->where('idempotency_key', 'like', $this->keyPrefix . '%')->exists()
                || DB::table('deposit_records')->useWritePdo()->where('local_order_no', 'like', $this->orderPrefix . '%')->exists();
            if (!$occupied) {
                return;
            }
        }

        throw new RuntimeException('Unable to allocate an unused cross-gateway fixture identity.');
    }

    private function unusedUserId(array $reservedUsers): int
    {
        for ($attempt = 0; $attempt < 100; ++$attempt) {
            $userId = random_int(700000000, 899999999);
            $occupied = in_array($userId, $reservedUsers, true)
                || DB::table('user_logins')->useWritePdo()->where('user_id', $userId)->exists()
                || DB::table('user_infos')->useWritePdo()->where('user_id', $userId)->exists()
                || DB::table('deposit_records')->useWritePdo()->where('user_id', $userId)->exists();
            if (!$occupied) {
                return $userId;
            }
        }

        throw new RuntimeException('Unable to allocate an unused cross-gateway fixture user.');
    }

    private function migration()
    {
        $path = database_path('migrations/2026_07_19_000005_harden_deposit_idempotency_per_user.php');
        if (!is_file($path)) {
            throw new RuntimeException('Missing deposit idempotency migration: ' . $path);
        }

        require_once $path;

        return new \HardenDepositIdempotencyPerUser();
    }

    private function withSchemaLock(callable $callback): void
    {
        $acquired = (int) DB::selectOne(
            'SELECT GET_LOCK(?, 30) AS acquired',
            [self::SCHEMA_LOCK],
            false
        )->acquired;
        $this->assertSame(1, $acquired, 'Could not acquire the deposit idempotency schema lock.');

        try {
            $callback();
        } finally {
            $released = (int) DB::selectOne(
                'SELECT RELEASE_LOCK(?) AS released',
                [self::SCHEMA_LOCK],
                false
            )->released;
            $this->assertSame(1, $released, 'Could not release the deposit idempotency schema lock.');
        }
    }

    private function withRestoredIndexes(callable $callback)
    {
        $result = null;
        $this->withSchemaLock(function () use ($callback, &$result): void {
            $fingerprint = $this->idempotencyIndexFingerprint();
            $snapshot = MySqlIndexSnapshot::capture(
                'deposit_records',
                ['idempotency_key'],
                [self::TARGET_INDEX, self::LEGACY_INDEX]
            );

            try {
                $result = $callback();
            } finally {
                $snapshot->restore();
                $this->assertSame(
                    $fingerprint,
                    $this->idempotencyIndexFingerprint(),
                    'The scoped deposit idempotency index definitions were not restored.'
                );
            }
        });

        return $result;
    }

    private function indexRows(string $name)
    {
        return collect(DB::select('SHOW INDEX FROM deposit_records', [], false))
            ->where('Key_name', $name)
            ->sortBy('Seq_in_index')
            ->values();
    }

    private function idempotencyIndexFingerprint(): string
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
            if (!in_array($name, [self::TARGET_INDEX, self::LEGACY_INDEX], true)
                && preg_match('/(?<![A-Za-z0-9_])idempotency_key(?![A-Za-z0-9_])/i', $definition) !== 1) {
                continue;
            }
            $definitions[$name] = $definition;
        }
        ksort($definitions);

        return hash('sha256', json_encode($definitions, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
    }

    private function dropIndexIfPresent(string $name): void
    {
        if ($this->indexRows($name)->isEmpty()) {
            return;
        }

        DB::statement('ALTER TABLE deposit_records DROP INDEX `' . $name . '`');
    }

    private function restoreLegacyIndexOnly(): void
    {
        $this->dropIndexIfPresent(self::TARGET_INDEX);
        $legacy = $this->indexRows(self::LEGACY_INDEX);
        if (!$legacy->isEmpty()) {
            $matches = $legacy->pluck('Column_name')->values()->all()
                === ['idempotency_key', 'user_id', 'gateway_code']
                && (int) $legacy->first()->Non_unique === 0
                && $legacy->pluck('Sub_part')->filter()->isEmpty();
            if ($matches) {
                return;
            }

            $this->dropIndexIfPresent(self::LEGACY_INDEX);
        }

        DB::statement(
            'ALTER TABLE deposit_records ADD UNIQUE INDEX ' . self::LEGACY_INDEX
            . ' (idempotency_key, user_id, gateway_code)'
        );
    }

    private function insertOrder(
        int $userId,
        string $key,
        string $gateway = null,
        string $localOrderNo,
        int $deletedAt = null
    ): void {
        $now = time();
        DB::table('deposit_records')->insert([
            'user_id' => $userId,
            'user_name' => 'payment-cross-gateway-migration',
            'mt4_ticket' => 0,
            'amount' => '100.00',
            'actual_amount' => '100.00',
            'provider_amount' => '100.00',
            'exchange_rate' => '1.00000000',
            'channel_name' => $gateway ?: 'legacy-null-gateway',
            'channel_order_no' => '',
            'local_order_no' => $localOrderNo,
            'idempotency_key' => $key,
            'gateway_code' => $gateway,
            'merchant_id' => 'task4-merchant',
            'currency' => 'USD',
            'payment_status' => 'pending',
            'settlement_status' => 'pending',
            'status' => '01',
            'remarks' => '',
            'created_by' => 'test',
            'updated_by' => '',
            'created_at' => $now,
            'updated_at' => $now,
            'deleted_at' => $deletedAt,
        ]);
    }
}
