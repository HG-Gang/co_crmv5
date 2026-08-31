<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/17
 * Time: 21:18
 */

/**
 * FrontWithdrawSettlementClosureModuleTest
 *
 * 文件功能：
 * - 验证前台出金结算闭环：金额与幂等键校验及多语言提示、结算契约类存在、配置夹具锁与快照恢复、夹具行清理失败关闭、生命周期回滚与自增恢复等 harness 契约。
 * - 输入：HTTP 请求与事务回滚的数据库夹具；输出：PHPUnit 断言结果。
 * - 明确不负责：不覆盖真实 MT4 网关与线上支付/出金通道（由网关契约测试锁定）。
 */

declare(strict_types=1);

namespace Tests\Feature;

use App\Constants\ResponseCode;
use App\Contracts\WithdrawalAccountSnapshotGateway;
use App\Http\Middleware\JwtAuthMiddleware;
use App\Http\Middleware\SingleSignOn;
use App\Models\UserInfo;
use App\Models\UserLogin;
use App\Services\Withdrawal\WithdrawalAccountSnapshot;
use App\Services\Withdrawal\WithdrawalOrderService;
use App\Support\Money;
use Carbon\Carbon;
use DomainException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use PDOException;
use Tests\Feature\Concerns\ManagesSharedSystemConfigFixtures;
use Tests\TestCase;

class FrontWithdrawSettlementClosureModuleTest extends TestCase
{
    use ManagesSharedSystemConfigFixtures;

    /**
     * 出金结算夹具的固定业务用户 ID，user_logins、user_infos、user_trades、withdraw_records 等夹具行共用。
     * 固定取值让 setUp 建行、用例断言与 tearDown 清零统计共用同一口径，避免散落的魔法数字互相漂移。
     * @var int
     */
    protected const USER_ID = 412372001;

    /**
     * 结算夹具会插入数据的表清单（表名 => 反引号包裹的表名）。
     * setUp 据此捕获各表 AUTO_INCREMENT 基线，tearDown 恢复原值，防止夹具插入抬高共享测试库的自增计数。
     * @var array<string, string>
     */
    private const SETTLEMENT_FIXTURE_AUTO_INCREMENT_TABLES = [
        'user_auths' => '`user_auths`',
        'user_infos' => '`user_infos`',
        'user_logins' => '`user_logins`',
        'user_trades' => '`user_trades`',
        'withdraw_records' => '`withdraw_records`',
        'withdraw_settlement_outbox' => '`withdraw_settlement_outbox`',
    ];

    /**
     * 改写提现相关 system_configs 之前保存的原始行快照；null 表示尚未捕获。
     * tearDown 依据它把被用例改写的提现配置恢复为原值，restore 后置回 null，测试也用它确认恢复逻辑确实执行。
     * @var array<int, array<string, mixed>>|null
     */
    private $configSnapshot;

    /**
     * 结算夹具各表的 AUTO_INCREMENT 基线（表名 => 自增值）；null 表示尚未捕获。
     * tearDown 恢复这些值，避免夹具插入在共享库上留下自增漂移。
     * @var array<string, int>|null
     */
    private $settlementFixtureAutoIncrementSnapshot;

    /**
     * withdraw_settlement_outbox 的基线行快照；null 表示尚未捕获。
     * tearDown 将当前行与基线比对，证明用例事务已回滚、没有泄漏新的结算 outbox 行；outboxCount 也以它为扣除基数。
     * @var array<int, array<string, mixed>>|null
     */
    private $settlementOutboxBaseline;

    /**
     * 独立的锁观察连接；用 GET_LOCK 尝试获取与 setUp 相同的夹具锁。
     * 拿到 0 说明锁确实被夹具生命周期持有，用来验证共享配置夹具锁的互斥语义。
     * @var \Illuminate\Database\Connection|null
     */
    private $fixtureLockObserver;

    /**
     * 观察连接尝试获取的锁名，取自 sharedSystemConfigFixtureAdvisoryLockName()，保证观察者与被测锁是同一把。
     * @var string|null
     */
    private $fixtureLockObserverName;

    /**
     * 清理生命周期防重入标记。setUp 失败与 tearDown 都会触发 cleanupSettlementFixtureLifecycle，
     * 标记保证清理步骤只执行一次，避免重复回滚、重复恢复配置。
     * @var bool
     */
    private $settlementFixtureLifecycleCleanupStarted = false;

    /**
     * 是否已执行 parent::setUp()。清理步骤据此决定是否补跑父类 tearDown，
     * 父类 setUp 尚未完成时不跑父类 tearDown，避免对未初始化资源做清理。
     * @var bool
     */
    private $settlementFixtureParentSetUpStarted = false;

    /**
     * 夹具生命周期是否进入活跃阶段（锁、快照、夹具行均已就绪）。
     * 只有活跃状态下清理才会回滚事务、删夹具行、恢复自增与配置，防止半初始化状态下误删数据。
     * @var bool
     */
    private $settlementFixtureLifecycleActive = false;

    /**
     * 默认连接事务是否已在清理中回滚。行清理、outbox 基线断言、自增恢复都以此为前置条件，
     * 未回滚就清理会破坏事务边界，因此这些步骤先断言该标记为 true。
     * @var bool
     */
    private $settlementFixtureTransactionsRolledBack = false;

    protected function setUp(): void
    {
        $this->settlementFixtureLifecycleCleanupStarted = false;
        $this->settlementFixtureParentSetUpStarted = true;
        $this->settlementFixtureLifecycleActive = false;
        $this->settlementFixtureTransactionsRolledBack = false;
        try {
            parent::setUp();

            $this->settlementFixtureLifecycleActive = true;
            $this->configSnapshot = null;
            $this->settlementFixtureAutoIncrementSnapshot = null;
            $this->settlementOutboxBaseline = null;
            $this->acquireSharedSystemConfigFixtureLock();
            $this->snapshotSettlementFixtureAutoIncrements();
            $this->cleanupFixtureRows();
            $this->snapshotSettlementOutboxBaseline();
            $this->snapshotWithdrawalConfig();
            $this->configureWithdrawals();
            $this->insertUser();
            $this->insertApprovedBank();
        } catch (\Throwable $exception) {
            $this->cleanupSettlementFixtureLifecycle($exception);
        }
    }

    protected function tearDown(): void
    {
        $this->cleanupSettlementFixtureLifecycle(null);
    }

    /**
     * 是否断言“出金结算配置夹具标记已清空”。
     *
     * 顶层夹具生命周期必须断言；嵌套 harness 实例复用外层已创建的配置夹具行，
     * 这些行归外层生命周期所有，因此嵌套实例跳过该断言，交由外层恢复生产配置。
     *
     * @return bool 返回 true 时执行配置标记清空断言。
     */
    protected function assertSettlementConfigFixtureMarkersAbsent(): bool
    {
        return true;
    }

    private function cleanupSettlementFixtureLifecycle(\Throwable $primary = null): void
    {
        if ($this->settlementFixtureLifecycleCleanupStarted) {
            return;
        }
        $this->settlementFixtureLifecycleCleanupStarted = true;
        $isSetUpFailure = $primary !== null;
        $this->settlementFixtureTransactionsRolledBack = false;
        $steps = [];

        if ($this->settlementFixtureLifecycleActive) {
            $steps['rollback settlement fixture transactions'] = function (): void {
                $this->rollbackDefaultConnectionTransactions();
                $this->settlementFixtureTransactionsRolledBack = true;
            };
        }

        $steps['reset Carbon test clock'] = static function (): void {
            Carbon::setTestNow();
        };

        if ($this->settlementFixtureLifecycleActive) {
            $steps['clean settlement fixture rows'] = function () use ($isSetUpFailure): void {
                if (!$this->hasSharedSystemConfigFixtureLockState()) {
                    if ($isSetUpFailure) {
                        return;
                    }

                    throw new \LogicException(
                        'The settlement fixture lifecycle cannot clean rows without its lock state.'
                    );
                }

                $this->assertSettlementFixtureTransactionsWereRolledBack();
                $this->cleanupFixtureRows();
            };
            if ($this->settlementOutboxBaseline !== null) {
                $steps['assert settlement outbox baseline is unchanged'] = function (): void {
                    $this->assertSettlementFixtureTransactionsWereRolledBack();
                    $this->assertSettlementOutboxMatchesBaseline();
                };
            }
            $steps['restore settlement fixture AUTO_INCREMENT values'] = function (): void {
                $this->assertSettlementFixtureTransactionsWereRolledBack();
                $this->restoreSettlementFixtureAutoIncrements();
            };
            $steps['restore withdrawal config snapshot'] = function (): void {
                $this->assertSettlementFixtureTransactionsWereRolledBack();
                $this->restoreWithdrawalConfig();
            };
        }

        if ($this->settlementFixtureLifecycleActive && !$isSetUpFailure) {
            $steps['assert settlement user fixture markers are absent'] = function (): void {
                $this->assertSame(
                    0,
                    DB::table('user_logins')
                        ->where('email', 'like', 'withdraw-task2-%@example.test')
                        ->count(),
                    'Withdrawal Task 2 user fixture leaked into the shared MySQL database.'
                );
            };
            $steps['assert settlement config fixture markers are absent'] = function (): void {
                if (!$this->assertSettlementConfigFixtureMarkersAbsent()) {
                    return;
                }
                $this->assertSame(
                    0,
                    DB::table('system_configs')
                        ->where('description', 'Withdrawal Task 2 fixture')
                        ->count(),
                    'Withdrawal Task 2 configuration marker leaked into the shared MySQL database.'
                );
            };
        }

        if ($this->settlementFixtureParentSetUpStarted) {
            $steps['parent teardown'] = function (): void {
                parent::tearDown();
            };
        }

        if ($this->settlementFixtureLifecycleActive) {
            $steps['release shared system config fixture lock'] = function (): void {
                if (!$this->settlementFixtureTransactionsRolledBack) {
                    $this->discardSharedSystemConfigFixtureAutoIncrementRestoreIntent();
                }
                $this->releaseSharedSystemConfigFixtureLock();
            };
        }

        if ($this->settlementFixtureLifecycleActive && !$isSetUpFailure) {
            $steps['assert fixture lock was released'] = function (): void {
                $this->assertFixtureLockWasReleased();
            };
        }

        $this->runSharedSystemConfigFixtureLifecycleCleanup($primary, $steps);
    }

    private function rollbackDefaultConnectionTransactions(): void
    {
        $connection = DB::connection();
        $failureMessages = [];
        $firstFailure = null;
        while ($connection->transactionLevel() > 0) {
            $before = $connection->transactionLevel();
            try {
                $connection->rollBack();
            } catch (\Throwable $exception) {
                $firstFailure = $exception;
                $failureMessages[] = 'level ' . $before . ': ' . $exception->getMessage();
                break;
            }

            $after = $connection->transactionLevel();
            if ($after >= $before) {
                $failureMessages[] = 'level ' . $before . ': rollback made no progress.';
                break;
            }
        }

        if ($connection->transactionLevel() !== 0) {
            $failureMessages[] = 'remaining transaction level: '
                . $connection->transactionLevel()
                . '.';
        }
        if ($failureMessages !== []) {
            throw new \RuntimeException(
                'Settlement fixture transaction rollback failed: '
                . implode(' | ', $failureMessages),
                0,
                $firstFailure
            );
        }
    }

    private function assertSettlementFixtureTransactionsWereRolledBack(): void
    {
        $level = DB::connection()->transactionLevel();
        if (!$this->settlementFixtureTransactionsRolledBack || $level !== 0) {
            throw new \LogicException(
                'Settlement fixture mutation requires transaction level 0; actual '
                . $level
                . '.'
            );
        }
    }

    private function discardSharedSystemConfigFixtureAutoIncrementRestoreIntent(): void
    {
        $this->sharedSystemConfigFixtureAutoIncrementSnapshot = null;
    }

    /**
     * @dataProvider invalidRequestAmountProvider
     * @param mixed $amount
     */
    public function test_invalid_request_amount_rejected($amount): void
    {
        $response = $this->submit($amount, 'amount-' . md5((string) $amount));

        $response->assertOk()->assertJsonPath('code', ResponseCode::VALIDATION_FAILED);
        $this->assertSame(0, $this->withdrawCount());
        $this->assertSame(0, $this->outboxCount());
    }

    public function invalidRequestAmountProvider(): array
    {
        return [
            'numeric JSON value' => [100],
            'scientific notation' => ['1e3'],
            'three decimal places' => ['100.001'],
            'zero' => ['0'],
            'negative' => ['-1.00'],
            'below configured minimum' => ['9.99'],
            'above configured maximum' => ['500000.01'],
            'DECIMAL(18,2) overflow' => ['10000000000000000.00'],
        ];
    }

    /**
     * @dataProvider invalidIdempotencyKeyProvider
     */
    public function test_invalid_idempotency_key_rejected(string $key = null): void
    {
        $response = $this->submit('100.00', $key);

        $response->assertOk()->assertJsonPath('code', ResponseCode::VALIDATION_FAILED);
        $this->assertStringContainsString('Idempotency-Key', (string) $response->json('message'));
        $this->assertSame(0, $this->withdrawCount());
        $this->assertSame(0, $this->outboxCount());
    }

    public function invalidIdempotencyKeyProvider(): array
    {
        return [
            'missing' => [null],
            'blank' => [''],
            'space' => ['unsafe key'],
            'slash' => ['unsafe/key'],
            'too long' => [str_repeat('x', 101)],
        ];
    }

    /** @dataProvider localizedInvalidIdempotencyKeyProvider */
    public function test_localized_invalid_idempotency_key_message(
        string $key = null
    ): void {
        $gateway = $this->snapshotGateway();
        $this->app->instance(WithdrawalAccountSnapshotGateway::class, $gateway);
        $expected = [
            'zh-CN' => 'Idempotency-Key 请求头缺失或格式无效。',
            'en' => 'The Idempotency-Key header is missing or invalid.',
        ];
        $messages = [];

        foreach ($expected as $locale => $message) {
            $response = $this->submitPayload('/api/front/withdrawals/submissions', [
                'amount' => '100.00',
                'password' => 'password',
                'agree' => true,
            ], $key, $locale);

            $response->assertOk()
                ->assertJsonPath('code', ResponseCode::VALIDATION_FAILED)
                ->assertJsonPath('message', $message);
            $messages[$locale] = (string) $response->json('message');
            if ($key !== null && $key !== '') {
                $this->assertStringNotContainsString($key, $messages[$locale]);
            }
        }

        $this->assertNotSame($messages['zh-CN'], $messages['en']);
        $this->assertSame(0, $gateway->calls);
        $this->assertSame(0, $this->withdrawCount());
        $this->assertSame(0, $this->outboxCount());
    }

    public function localizedInvalidIdempotencyKeyProvider(): array
    {
        return [
            'missing' => [null],
            'invalid' => ['unsafe/key-secret-do-not-echo'],
        ];
    }

    public function test_withdrawal_contract_classes_exist(): void
    {
        $this->assertTrue(
            interface_exists(\App\Contracts\WithdrawalAccountSnapshotGateway::class),
            'WithdrawalAccountSnapshotGateway contract is missing.'
        );
        $this->assertTrue(
            class_exists(\App\Services\Withdrawal\WithdrawalAccountSnapshot::class),
            'WithdrawalAccountSnapshot DTO is missing.'
        );
        $this->assertTrue(
            class_exists(\App\Services\Withdrawal\WithdrawalOrderService::class),
            'WithdrawalOrderService is missing.'
        );
        $this->assertTrue(method_exists(
            \App\Services\Withdrawal\WithdrawalOrderService::class,
            'createOrRetrieve'
        ));
    }

    public function test_setup_acquires_shared_config_fixture_lock(): void
    {
        $observer = $this->fixtureObserverConnection('withdraw_task2_fixture_observer');
        $this->fixtureLockObserver = $observer;
        $this->fixtureLockObserverName = $this->sharedSystemConfigFixtureAdvisoryLockName();
        $acquired = $observer->selectOne(
            'SELECT GET_LOCK(?, 0) AS acquired',
            [$this->fixtureLockObserverName]
        );

        $this->assertSame(0, (int) $acquired->acquired);
    }

    public function test_harness_setup_failure_cleans_up_before_teardown(): void
    {
        $restoreOuterApplication = function (): void {
            if (!$this->app || !$this->app->bound('config')) {
                $this->refreshApplication();
            }
            \Illuminate\Support\Facades\Facade::setFacadeApplication($this->app);
            \Illuminate\Support\Facades\Facade::clearResolvedInstances();
            \Illuminate\Container\Container::setInstance($this->app);
        };
        $lockName = 'wdr:test-setup-failure:' . substr(
            hash('sha256', DB::getDatabaseName() . ':' . spl_object_hash($this)),
            0,
            32
        );
        $harness = new FrontWithdrawSettlementClosureSetupFailureHarness(
            'testHarnessBodyIsNotRun',
            $lockName
        );
        $failure = null;

        try {
            try {
                $harness->runBare();
            } catch (\Throwable $exception) {
                $failure = $exception;
            } finally {
                $restoreOuterApplication();
            }

            $this->assertInstanceOf(\RuntimeException::class, $failure);
            $this->assertSame('simulated setup failure after lock acquisition', $failure->getMessage());
            $this->assertFalse($harness->bodyRan);
            $this->assertNotNull($harness->sentinelConnection);
            $connectionId = $harness->sentinelConnection->selectOne(
                'SELECT CONNECTION_ID() AS connection_id',
                [],
                false
            );
            $lockOwner = $harness->sentinelConnection->selectOne(
                'SELECT IS_USED_LOCK(?) AS connection_id',
                [$lockName],
                false
            );
            $this->assertSame((int) $connectionId->connection_id, (int) $lockOwner->connection_id);
            $this->assertTrue(
                $harness->sentinelConnection
                    ->table('user_trades')
                    ->useWritePdo()
                    ->where('id', $harness->sentinelTradeId)
                    ->where('user_id', 412372001)
                    ->where('ticket', $harness->sentinelTradeTicket)
                    ->exists(),
                'PHPUnit tearDown touched a sentinel row after setup cleanup released its lock.'
            );
            $this->assertSame(1, $harness->releaseCalls);
        } finally {
            $restoreOuterApplication();
            $harness->cleanupSentinel();
            $restoreOuterApplication();
        }
    }

    public function test_restore_withdrawal_config_clears_snapshot(): void
    {
        $this->assertNotNull($this->configSnapshot);

        $this->restoreWithdrawalConfig();

        $this->assertNull($this->configSnapshot);
    }

    public function test_restore_withdrawal_config_handles_split_default_connection(): void
    {
        $originalDefault = DB::getDefaultConnection();
        $splitName = 'withdraw_task2_config_restore_split';
        $observerName = 'withdraw_task2_config_restore_external';
        $targetKey = 'withdrawal_enabled';
        $fixtureRow = DB::table('system_configs')
            ->useWritePdo()
            ->where('key', $targetKey)
            ->first();
        $this->assertNotNull($fixtureRow);
        $fixtureData = (array) $fixtureRow;
        unset($fixtureData['id']);
        $externalValue = 'external-owned-update';
        $externalDescription = 'External concurrent config update';
        $splitConnection = null;
        $observer = null;
        $writePdo = null;
        $readPdo = null;
        $readTransactionActive = false;
        $staleReadValue = null;
        $failure = null;
        $rowAfterAttempt = null;

        try {
            $baseConfig = config('database.connections.' . $originalDefault);
            $endpoint = array_intersect_key($baseConfig, array_flip([
                'host',
                'port',
                'database',
                'username',
                'password',
                'unix_socket',
            ]));
            $splitConfig = $baseConfig;
            $splitConfig['read'] = $endpoint;
            $splitConfig['write'] = $endpoint;
            $splitConfig['sticky'] = false;
            config(['database.connections.' . $splitName => $splitConfig]);
            DB::purge($splitName);
            $observer = $this->fixtureObserverConnection($observerName);
            $observer->unsetEventDispatcher();
            DB::setDefaultConnection($splitName);
            $splitConnection = DB::connection($splitName);
            $writePdo = $splitConnection->getPdo();
            $readPdo = $splitConnection->getReadPdo();
            $this->assertNotSame($writePdo, $readPdo);
            $readPdo->exec('SET SESSION TRANSACTION ISOLATION LEVEL REPEATABLE READ');
            $readPdo->beginTransaction();
            $readTransactionActive = true;
            $statement = $readPdo->prepare(
                'SELECT `value` FROM `system_configs` WHERE `key` = ?'
            );
            $statement->execute([$targetKey]);
            $staleReadValue = (string) $statement->fetchColumn();

            $updated = $observer->table('system_configs')
                ->where('id', $fixtureRow->id)
                ->where('key', $targetKey)
                ->where('description', 'Withdrawal Task 2 fixture')
                ->update([
                    'value' => $externalValue,
                    'description' => $externalDescription,
                    'updated_at' => time(),
                ]);
            $this->assertSame(1, $updated);

            try {
                // The stale read transaction still holds a metadata lock; this call only proves CAS refusal.
                $this->restoreWithdrawalConfig(false);
            } catch (\Throwable $exception) {
                $failure = $exception;
            }
            $rowAfterAttempt = $observer->table('system_configs')
                ->useWritePdo()
                ->where('key', $targetKey)
                ->first();
        } finally {
            $this->runSharedSystemConfigFixtureLifecycleCleanup(null, [
                'rollback stale config read transaction' => static function () use (
                    &$readPdo,
                    &$readTransactionActive
                ): void {
                    if ($readPdo !== null && $readTransactionActive) {
                        $readPdo->rollBack();
                        $readTransactionActive = false;
                    }
                },
                'restore config test default connection' => static function () use (
                    $originalDefault
                ): void {
                    DB::setDefaultConnection($originalDefault);
                },
                'restore externally changed config to fixture state' => function () use (
                    $fixtureRow,
                    $targetKey,
                    $externalDescription,
                    $fixtureData
                ): void {
                    if ($this->configSnapshot !== null) {
                        DB::table('system_configs')
                            ->where('id', $fixtureRow->id)
                            ->where('key', $targetKey)
                            ->where('description', $externalDescription)
                            ->update($fixtureData);
                        $this->restoreWithdrawalConfig();
                    }
                },
                'disconnect config restore split connections' => static function () use (
                    &$splitConnection,
                    &$observer
                ): void {
                    if ($splitConnection !== null) {
                        $splitConnection->disconnect();
                    }
                    if ($observer !== null) {
                        $observer->disconnect();
                    }
                },
                'purge config restore split connections' => static function () use (
                    $splitName,
                    $observerName
                ): void {
                    DB::purge($splitName);
                    DB::purge($observerName);
                },
                'remove config restore split connection configs' => static function () use (
                    $splitName,
                    $observerName
                ): void {
                    config()->offsetUnset('database.connections.' . $splitName);
                    config()->offsetUnset('database.connections.' . $observerName);
                },
            ]);
        }

        $this->assertSame((string) $fixtureRow->value, $staleReadValue);
        $this->assertInstanceOf(\RuntimeException::class, $failure);
        $this->assertNotNull($rowAfterAttempt);
        $this->assertSame($externalValue, (string) $rowAfterAttempt->value);
        $this->assertSame($externalDescription, (string) $rowAfterAttempt->description);
    }

    public function test_restore_withdrawal_config_keeps_external_same_key_row(): void
    {
        $targetKey = 'withdrawal_enabled';
        $fixtureRow = DB::table('system_configs')
            ->useWritePdo()
            ->where('key', $targetKey)
            ->first();
        $this->assertNotNull($fixtureRow);
        $fixtureData = (array) $fixtureRow;
        $fixtureUpdate = $fixtureData;
        unset($fixtureUpdate['id']);
        $observerName = 'withdraw_task2_config_restore_competitor';
        $observer = null;
        $competitorId = null;
        $competitorDescription = 'External same-key competing config';
        $failure = null;
        $rowAfterAttempt = null;

        try {
            $observer = $this->fixtureObserverConnection($observerName);
            $observer->unsetEventDispatcher();
            $deleted = $observer->table('system_configs')
                ->where('id', $fixtureRow->id)
                ->where('key', $targetKey)
                ->where('description', 'Withdrawal Task 2 fixture')
                ->delete();
            $this->assertSame(1, $deleted);
            $competitorId = $observer->table('system_configs')->insertGetId([
                'key' => $targetKey,
                'value' => 'external-competitor',
                'group' => 'external',
                'description' => $competitorDescription,
                'created_at' => 1711111111,
                'updated_at' => 1711111112,
                'deleted_at' => null,
            ]);

            try {
                $this->restoreWithdrawalConfig();
            } catch (\Throwable $exception) {
                $failure = $exception;
            }
            $rowAfterAttempt = $observer->table('system_configs')
                ->useWritePdo()
                ->where('key', $targetKey)
                ->first();
        } finally {
            $this->runSharedSystemConfigFixtureLifecycleCleanup(null, [
                'restore same-key config competitor to fixture state' => function () use (
                    $targetKey,
                    $competitorId,
                    $competitorDescription,
                    $fixtureData,
                    $fixtureUpdate
                ): void {
                    if ($this->configSnapshot === null) {
                        return;
                    }
                    if ($competitorId !== null) {
                        DB::table('system_configs')
                            ->where('id', $competitorId)
                            ->where('key', $targetKey)
                            ->where('description', $competitorDescription)
                            ->delete();
                    }
                    $current = DB::table('system_configs')
                        ->useWritePdo()
                        ->where('key', $targetKey)
                        ->first();
                    if ($current === null) {
                        DB::table('system_configs')->insert($fixtureData);
                    } elseif ((int) $current->id === (int) $fixtureData['id']
                        && (array) $current !== $fixtureData) {
                        DB::table('system_configs')
                            ->where('id', $fixtureData['id'])
                            ->where('key', $targetKey)
                            ->where('description', (string) $current->description)
                            ->update($fixtureUpdate);
                    }
                    $this->restoreWithdrawalConfig();
                },
                'disconnect same-key config competitor observer' => static function () use (
                    $observer
                ): void {
                    if ($observer !== null) {
                        $observer->disconnect();
                    }
                },
                'purge same-key config competitor observer' => static function () use (
                    $observerName
                ): void {
                    DB::purge($observerName);
                },
                'remove same-key config competitor observer config' => static function () use (
                    $observerName
                ): void {
                    config()->offsetUnset('database.connections.' . $observerName);
                },
            ]);
        }

        $this->assertInstanceOf(\RuntimeException::class, $failure);
        $this->assertNotNull($rowAfterAttempt);
        $this->assertSame($competitorId, (int) $rowAfterAttempt->id);
        $this->assertSame($competitorDescription, (string) $rowAfterAttempt->description);
    }

    public function test_capture_owned_state_fails_closed_when_rows_changed(): void
    {
        $managedKeys = array_keys($this->withdrawalConfig());
        $expectedRows = DB::table('system_configs')
            ->useWritePdo()
            ->whereIn('key', $managedKeys)
            ->orderBy('key')
            ->get()
            ->map(static function ($row): array {
                return (array) $row;
            })
            ->all();
        $targetKey = 'withdrawal_enabled';
        $expectedTarget = null;
        foreach ($expectedRows as $row) {
            if ((string) $row['key'] === $targetKey) {
                $expectedTarget = $row;
                break;
            }
        }
        $this->assertNotNull($expectedTarget);
        $externalValue = 'external-capture-window-update';
        $externalDescription = 'External capture window update';
        $observerName = 'withdraw_task2_config_capture_window';
        $observer = null;
        $failure = null;
        $rowAfterCapture = null;

        try {
            $observer = $this->fixtureObserverConnection($observerName);
            $observer->unsetEventDispatcher();
            $updated = $observer->table('system_configs')
                ->where('id', $expectedTarget['id'])
                ->where('key', $targetKey)
                ->where('value', $expectedTarget['value'])
                ->where('description', $expectedTarget['description'])
                ->update([
                    'value' => $externalValue,
                    'description' => $externalDescription,
                    'updated_at' => 1711111199,
                ]);
            $this->assertSame(1, $updated);

            try {
                $this->captureSharedSystemConfigFixtureOwnedState(
                    $managedKeys,
                    $expectedRows
                );
            } catch (\Throwable $exception) {
                $failure = $exception;
            }
            $rowAfterCapture = $observer->table('system_configs')
                ->useWritePdo()
                ->where('id', $expectedTarget['id'])
                ->where('key', $targetKey)
                ->first();
        } finally {
            $this->runSharedSystemConfigFixtureLifecycleCleanup(null, [
                'restore capture-window config fixture state' => function () use (
                    $expectedTarget,
                    $targetKey,
                    $externalValue,
                    $externalDescription
                ): void {
                    DB::table('system_configs')
                        ->where('id', $expectedTarget['id'])
                        ->where('key', $targetKey)
                        ->where('value', $externalValue)
                        ->where('description', $externalDescription)
                        ->where('updated_at', 1711111199)
                        ->update($expectedTarget);
                },
                'disconnect capture-window observer' => static function () use ($observer): void {
                    if ($observer !== null) {
                        $observer->disconnect();
                    }
                },
                'purge capture-window observer' => static function () use ($observerName): void {
                    DB::purge($observerName);
                },
                'remove capture-window observer config' => static function () use (
                    $observerName
                ): void {
                    config()->offsetUnset('database.connections.' . $observerName);
                },
            ]);
        }

        $this->assertInstanceOf(\RuntimeException::class, $failure);
        $this->assertNotNull($rowAfterCapture);
        $this->assertSame($externalValue, (string) $rowAfterCapture->value);
        $this->assertSame($externalDescription, (string) $rowAfterCapture->description);
    }

    public function test_harness_parent_setup_failure_skips_cleanup_without_lock(): void
    {
        $ownedRowsBefore = $this->settlementFixtureOwnedRowCounts();
        $harness = new FrontWithdrawSettlementClosureParentSetUpFailureHarness(
            'testHarnessBodyIsNotRun'
        );
        $failure = null;

        try {
            $harness->runBare();
        } catch (\Throwable $exception) {
            $failure = $exception;
        }

        $this->assertInstanceOf(\RuntimeException::class, $failure);
        $this->assertSame('simulated parent setup failure', $failure->getMessage());
        $this->assertFalse($harness->bodyRan);
        $this->assertSame(
            $ownedRowsBefore,
            $this->settlementFixtureOwnedRowCounts(),
            'A parent setup failure ran fixture cleanup without owning its advisory lock.'
        );
    }

    public function test_harness_transaction_leak_rolled_back_by_lifecycle(): void
    {
        $restoreOuterApplication = function (): void {
            if (!$this->app || !$this->app->bound('config')) {
                $this->refreshApplication();
            }
            \Illuminate\Support\Facades\Facade::setFacadeApplication($this->app);
            \Illuminate\Support\Facades\Facade::clearResolvedInstances();
            \Illuminate\Container\Container::setInstance($this->app);
        };
        $this->restoreWithdrawalConfig();
        $this->snapshotWithdrawalConfig();
        $expectedAutoIncrement = $this->settlementFixtureAutoIncrementSnapshot['withdraw_records'];
        $expectedSystemConfigAutoIncrement = $this->readSharedSystemConfigFixtureAutoIncrement(
            DB::connection()
        );
        $expectedSystemConfigRows = DB::table('system_configs')
            ->useWritePdo()
            ->orderBy('id')
            ->get()
            ->map(static function ($row): array {
                return (array) $row;
            })
            ->all();
        $expectedSystemConfigHash = hash('sha256', json_encode($expectedSystemConfigRows));
        $lockName = 'wdr:test-transaction-cleanup:' . substr(
            hash('sha256', DB::getDatabaseName() . ':' . spl_object_hash($this)),
            0,
            26
        );
        $harness = new FrontWithdrawSettlementClosureTransactionLeakHarness(
            'testLeavesNestedTransactionsForLifecycleCleanup',
            $lockName
        );
        $failure = null;

        try {
            try {
                $harness->runBare();
            } catch (\Throwable $exception) {
                $failure = $exception;
            } finally {
                $restoreOuterApplication();
            }

            $this->assertTrue($harness->bodyRan);
            $sentinelExists = $harness->sentinelId !== null
                && DB::table('withdraw_records')
                    ->useWritePdo()
                    ->where('id', $harness->sentinelId)
                    ->where('user_id', $harness->sentinelUserId)
                    ->where('local_order_no', $harness->sentinelLocalOrderNo)
                    ->exists();
            $this->assertFalse(
                $sentinelExists,
                'Lifecycle DDL implicitly committed a transaction sentinel.'
            );
            $this->assertNull($failure);
            $this->assertNotNull($harness->writeConnection);
            $this->assertSame(0, $harness->writeConnection->transactionLevel());
            $this->assertFalse(
                DB::table('system_configs')
                    ->useWritePdo()
                    ->where('id', $harness->systemConfigSentinelId)
                    ->where('key', $harness->systemConfigSentinelKey)
                    ->exists()
            );
            $this->assertSame(
                $expectedSystemConfigAutoIncrement,
                $this->readSharedSystemConfigFixtureAutoIncrement(DB::connection())
            );
            $actualSystemConfigRows = DB::table('system_configs')
                ->useWritePdo()
                ->orderBy('id')
                ->get()
                ->map(static function ($row): array {
                    return (array) $row;
                })
                ->all();
            $this->assertSame(
                $expectedSystemConfigHash,
                hash('sha256', json_encode($actualSystemConfigRows))
            );
        } finally {
            $restoreOuterApplication();
            $this->runSharedSystemConfigFixtureLifecycleCleanup(null, [
                'delete transaction leak system config sentinel' => function () use (
                    $harness
                ): void {
                    if ($harness->systemConfigSentinelId !== null) {
                        DB::table('system_configs')
                            ->where('id', $harness->systemConfigSentinelId)
                            ->where('key', $harness->systemConfigSentinelKey)
                            ->where(
                                'description',
                                'Transaction cleanup AUTO_INCREMENT sentinel'
                            )
                            ->delete();
                    }
                },
                'delete transaction leak sentinel' => function () use ($harness): void {
                    if ($harness->sentinelId !== null) {
                        DB::table('withdraw_records')
                            ->where('id', $harness->sentinelId)
                            ->where('user_id', $harness->sentinelUserId)
                            ->where('local_order_no', $harness->sentinelLocalOrderNo)
                            ->delete();
                    }
                },
                'restore transaction leak AUTO_INCREMENT' => function () use (
                    $expectedAutoIncrement
                ): void {
                    DB::statement(
                        'ALTER TABLE `withdraw_records` AUTO_INCREMENT = '
                        . $expectedAutoIncrement
                    );
                    $actual = $this->readSettlementFixtureAutoIncrements();
                    if ($actual['withdraw_records'] !== $expectedAutoIncrement) {
                        throw new \RuntimeException(
                            'Transaction leak AUTO_INCREMENT restore mismatch.'
                        );
                    }
                },
                'restore transaction leak config snapshot' => function (): void {
                    $this->restoreWithdrawalConfig();
                },
                'verify transaction leak system config AUTO_INCREMENT' => function () use (
                    $expectedSystemConfigAutoIncrement
                ): void {
                    $actual = $this->readSharedSystemConfigFixtureAutoIncrement(
                        DB::connection()
                    );
                    if ($actual !== $expectedSystemConfigAutoIncrement) {
                        throw new \RuntimeException(
                            'Transaction leak system config AUTO_INCREMENT restore mismatch.'
                        );
                    }
                },
                'disconnect transaction leak connection' => static function () use (
                    $harness
                ): void {
                    if ($harness->writeConnection !== null) {
                        $harness->writeConnection->disconnect();
                    }
                },
            ]);
            $restoreOuterApplication();
        }
    }

    public function test_harness_rollback_failure_collects_cleanup_errors(): void
    {
        $restoreOuterApplication = function (): void {
            if (!$this->app || !$this->app->bound('config')) {
                $this->refreshApplication();
            }
            \Illuminate\Support\Facades\Facade::setFacadeApplication($this->app);
            \Illuminate\Support\Facades\Facade::clearResolvedInstances();
            \Illuminate\Container\Container::setInstance($this->app);
        };
        $this->restoreWithdrawalConfig();
        $this->snapshotWithdrawalConfig();
        $expectedSystemConfigAutoIncrement = $this->readSharedSystemConfigFixtureAutoIncrement(
            DB::connection()
        );
        $expectedSystemConfigRows = DB::table('system_configs')
            ->useWritePdo()
            ->orderBy('id')
            ->get()
            ->map(static function ($row): array {
                return (array) $row;
            })
            ->all();
        $expectedSystemConfigHash = hash('sha256', json_encode($expectedSystemConfigRows));
        $expectedSystemConfigCount = count($expectedSystemConfigRows);
        $lockName = 'wdr:test-rollback-failure:' . substr(
            hash('sha256', DB::getDatabaseName() . ':' . spl_object_hash($this)),
            0,
            25
        );
        $harness = new FrontWithdrawSettlementClosureRollbackFailureHarness(
            'testRollbackFailureAfterTransactionLevelReachesZero',
            $lockName
        );
        $observerName = 'withdraw_task2_rollback_failure_observer';
        $observer = null;
        $observerAcquired = null;
        $failure = null;

        try {
            try {
                $harness->runBare();
            } catch (\Throwable $exception) {
                $failure = $exception;
            } finally {
                $restoreOuterApplication();
            }

            $this->assertInstanceOf(\RuntimeException::class, $failure);
            $this->assertStringContainsString(
                'rollback settlement fixture transactions',
                $failure->getMessage()
            );
            $this->assertStringContainsString(
                'clean settlement fixture rows',
                $failure->getMessage()
            );
            $this->assertStringContainsString(
                'restore settlement fixture AUTO_INCREMENT values',
                $failure->getMessage()
            );
            $this->assertStringContainsString(
                'restore withdrawal config snapshot',
                $failure->getMessage()
            );
            $this->assertTrue($harness->bodyRan);
            $this->assertTrue($harness->rollbackFailureInjected);
            $this->assertNotNull($harness->writeConnection);
            $this->assertSame(0, $harness->writeConnection->transactionLevel());
            $this->assertNotNull($harness->lockConnection);
            $this->assertNotNull($harness->systemConfigAutoIncrementSnapshot);
            $this->assertNotNull($harness->systemConfigAutoIncrementAfterSentinel);
            $this->assertGreaterThan(
                $harness->systemConfigAutoIncrementSnapshot,
                $harness->systemConfigAutoIncrementAfterSentinel
            );
            $this->assertSame([], $harness->cleanupDeleteStatements);
            $this->assertSame([], $harness->autoIncrementDdlStatements);
            $this->assertSame([], $harness->configMutationStatements);
            $this->assertSame([
                'user_logins' => 1,
                'user_infos' => 1,
                'user_auths' => 1,
                'user_trades' => 0,
                'withdraw_records' => 0,
                'withdraw_settlement_outbox' => 0,
            ], $this->settlementFixtureOwnedRowCounts());
            $this->assertSame(
                count($this->withdrawalConfig()),
                DB::table('system_configs')
                    ->where('description', 'Withdrawal Task 2 fixture')
                    ->count()
            );
            $this->assertTrue($harness->parentTeardownRan);
            $this->assertSame(1, $harness->releaseCalls);

            $observer = $this->fixtureObserverConnection($observerName);
            $observerAcquired = $observer->selectOne(
                'SELECT GET_LOCK(?, 0) AS acquired',
                [$lockName],
                false
            );
            $this->assertSame(1, (int) $observerAcquired->acquired);
            $this->assertSame([], $harness->lockConnectionAutoIncrementDdlStatements);
        } finally {
            $restoreOuterApplication();
            $this->runSharedSystemConfigFixtureLifecycleCleanup(null, [
                'delete rollback failure system config sentinel' => function () use (
                    $harness
                ): void {
                    if ($harness->systemConfigSentinelId !== null) {
                        DB::table('system_configs')
                            ->where('id', $harness->systemConfigSentinelId)
                            ->where('key', $harness->systemConfigSentinelKey)
                            ->where(
                                'description',
                                'Rollback failure AUTO_INCREMENT sentinel'
                            )
                            ->delete();
                    }
                },
                'clean rollback failure fixture rows' => function (): void {
                    $this->cleanupFixtureRows();
                },
                'restore rollback failure AUTO_INCREMENT values' => function (): void {
                    $this->restoreSettlementFixtureAutoIncrements();
                },
                'restore rollback failure config snapshot' => function (): void {
                    $this->captureCurrentWithdrawalConfigOwnedState();
                    $this->restoreWithdrawalConfig();
                },
                'release rollback failure observer lock' => static function () use (
                    $observer,
                    $observerAcquired,
                    $lockName
                ): void {
                    if ($observer !== null
                        && $observerAcquired !== null
                        && (int) $observerAcquired->acquired === 1) {
                        $released = $observer->selectOne(
                            'SELECT RELEASE_LOCK(?) AS released',
                            [$lockName],
                            false
                        );
                        if (!$released || (int) $released->released !== 1) {
                            throw new \RuntimeException(
                                'Rollback failure observer lock release failed.'
                            );
                        }
                    }
                },
                'disconnect rollback failure connections' => static function () use (
                    $harness,
                    $observer,
                    $observerName
                ): void {
                    if ($harness->writeConnection !== null) {
                        $harness->writeConnection->unsetEventDispatcher();
                        $harness->writeConnection->disconnect();
                    }
                    if ($harness->lockConnection !== null) {
                        $harness->lockConnection->unsetEventDispatcher();
                        $harness->lockConnection->disconnect();
                    }
                    if ($observer !== null) {
                        $observer->disconnect();
                    }
                    DB::purge($observerName);
                    config()->offsetUnset('database.connections.' . $observerName);
                },
            ]);
            $restoreOuterApplication();
        }

        $actualSystemConfigRows = DB::table('system_configs')
            ->useWritePdo()
            ->orderBy('id')
            ->get()
            ->map(static function ($row): array {
                return (array) $row;
            })
            ->all();
        $this->assertSame($expectedSystemConfigCount, count($actualSystemConfigRows));
        $this->assertSame(
            $expectedSystemConfigHash,
            hash('sha256', json_encode($actualSystemConfigRows))
        );
        $this->assertSame(
            $expectedSystemConfigAutoIncrement,
            $this->readSharedSystemConfigFixtureAutoIncrement(DB::connection())
        );
    }

    public function test_cleanup_fixture_rows_fails_fast_on_non_fixture_user_collision(): void
    {
        $login = DB::table('user_logins')->useWritePdo()
            ->where('user_id', self::USER_ID)
            ->first();
        $info = DB::table('user_infos')->useWritePdo()
            ->where('user_id', self::USER_ID)
            ->first();
        $this->assertNotNull($login);
        $this->assertNotNull($info);
        $failure = null;

        try {
            DB::table('user_logins')
                ->where('id', $login->id)
                ->where('user_id', self::USER_ID)
                ->update(['email' => 'real-customer@example.test']);
            DB::table('user_infos')
                ->where('id', $info->id)
                ->where('user_id', self::USER_ID)
                ->update(['user_name' => 'real-customer']);

            try {
                $this->cleanupFixtureRows();
            } catch (\LogicException $exception) {
                $failure = $exception;
            }

            $this->assertInstanceOf(\LogicException::class, $failure);
            $this->assertStringContainsString('non-fixture user', $failure->getMessage());
        } finally {
            $this->runSharedSystemConfigFixtureLifecycleCleanup(null, [
                'restore collision fixture login marker' => function () use ($login): void {
                    DB::table('user_logins')
                        ->where('id', $login->id)
                        ->where('user_id', self::USER_ID)
                        ->update(['email' => $login->email]);
                },
                'restore collision fixture info marker' => function () use ($info): void {
                    DB::table('user_infos')
                        ->where('id', $info->id)
                        ->where('user_id', self::USER_ID)
                        ->update(['user_name' => $info->user_name]);
                },
            ]);
        }
    }

    public function test_cleanup_fixture_rows_fails_fast_on_foreign_fixture_marker(): void
    {
        $foreignUserId = (int) DB::table('user_logins')->useWritePdo()->max('user_id') + 1;
        if ($foreignUserId === self::USER_ID) {
            ++$foreignUserId;
        }
        $foreignEmail = 'withdraw-task2-foreign-' . $foreignUserId . '@example.test';
        $foreignLoginId = null;
        $failure = null;
        $foreignMarkerRemained = false;
        $ownedRowsAfter = [];

        try {
            $foreignLoginId = DB::table('user_logins')->insertGetId([
                'user_id' => $foreignUserId,
                'email' => $foreignEmail,
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
            $ownedRowsBefore = $this->settlementFixtureOwnedRowCounts();
            try {
                $this->cleanupFixtureRows();
            } catch (\LogicException $exception) {
                $failure = $exception;
            }
            $foreignMarkerRemained = DB::table('user_logins')
                ->useWritePdo()
                ->where('id', $foreignLoginId)
                ->where('user_id', $foreignUserId)
                ->where('email', $foreignEmail)
                ->exists();
            $ownedRowsAfter = $this->settlementFixtureOwnedRowCounts();

            $this->assertInstanceOf(\LogicException::class, $failure);
            $this->assertStringContainsString('foreign fixture marker', $failure->getMessage());
            $this->assertTrue($foreignMarkerRemained, 'Cleanup deleted a foreign fixture marker.');
            $this->assertSame(
                $ownedRowsBefore,
                $ownedRowsAfter,
                'Cleanup deleted owned rows before failing.'
            );
        } finally {
            if ($foreignLoginId !== null) {
                DB::table('user_logins')
                    ->where('id', $foreignLoginId)
                    ->where('user_id', $foreignUserId)
                    ->where('email', $foreignEmail)
                    ->delete();
            }
        }
    }

    public function test_harness_foreign_marker_failure_leaves_no_sentinel(): void
    {
        $restoreOuterApplication = function (): void {
            if (!$this->app || !$this->app->bound('config')) {
                $this->refreshApplication();
            }
            \Illuminate\Support\Facades\Facade::setFacadeApplication($this->app);
            \Illuminate\Support\Facades\Facade::clearResolvedInstances();
            \Illuminate\Container\Container::setInstance($this->app);
        };
        $lockName = 'wdr:test-foreign-exception:' . substr(
            hash('sha256', DB::getDatabaseName() . ':' . spl_object_hash($this)),
            0,
            30
        );
        $harness = new FrontWithdrawSettlementClosureForeignMarkerExceptionHarness(
            'test_settlement_cleanup_fails_fast_for_a_foreign_fixture_marker_without_deletes',
            $lockName
        );
        $failure = null;

        try {
            try {
                $harness->runBare();
            } catch (\Throwable $exception) {
                $failure = $exception;
            } finally {
                $restoreOuterApplication();
            }

            $this->assertInstanceOf(\RuntimeException::class, $failure);
            $this->assertSame('simulated foreign marker count failure', $failure->getMessage());
            $this->assertTrue($harness->failureInjected);
            $this->assertNotNull($harness->capturedEmail);
            $leaked = DB::table('user_logins')
                ->useWritePdo()
                ->where('user_id', $harness->capturedUserId)
                ->where('email', $harness->capturedEmail)
                ->first();
            $this->assertNull($leaked, 'The foreign marker regression leaked its sentinel row.');
        } finally {
            $restoreOuterApplication();
            if ($harness->capturedEmail !== null && $harness->capturedUserId !== null) {
                $sentinel = DB::table('user_logins')
                    ->useWritePdo()
                    ->where('user_id', $harness->capturedUserId)
                    ->where('email', $harness->capturedEmail)
                    ->first();
                if ($sentinel !== null) {
                    DB::table('user_logins')
                        ->where('id', $sentinel->id)
                        ->where('user_id', $harness->capturedUserId)
                        ->where('email', $harness->capturedEmail)
                        ->delete();
                }
            }
        }
    }

    public function test_harness_collision_failure_leaves_no_fixture_rows(): void
    {
        $restoreOuterApplication = function (): void {
            if (!$this->app || !$this->app->bound('config')) {
                $this->refreshApplication();
            }
            \Illuminate\Support\Facades\Facade::setFacadeApplication($this->app);
            \Illuminate\Support\Facades\Facade::clearResolvedInstances();
            \Illuminate\Container\Container::setInstance($this->app);
        };
        $lockName = 'wdr:test-collision-exception:' . substr(
            hash('sha256', DB::getDatabaseName() . ':' . spl_object_hash($this)),
            0,
            28
        );
        $harness = new FrontWithdrawSettlementClosureCollisionExceptionHarness(
            'test_settlement_cleanup_fails_fast_for_a_non_fixture_user_collision',
            $lockName
        );
        $failure = null;

        try {
            try {
                $harness->runBare();
            } catch (\Throwable $exception) {
                $failure = $exception;
            } finally {
                $restoreOuterApplication();
            }

            $this->assertInstanceOf(\RuntimeException::class, $failure);
            $this->assertSame('simulated collision marker update failure', $failure->getMessage());
            $this->assertTrue($harness->failureInjected);
            $login = DB::table('user_logins')->useWritePdo()
                ->where('id', $harness->fixtureLoginId)
                ->first();
            $info = DB::table('user_infos')->useWritePdo()
                ->where('id', $harness->fixtureInfoId)
                ->first();
            $this->assertNull($login, 'The collision regression left its fixture login behind.');
            $this->assertNull($info, 'The collision regression left its fixture info behind.');
        } finally {
            $restoreOuterApplication();
            $this->runSharedSystemConfigFixtureLifecycleCleanup(null, [
                'restore collision harness login marker' => function () use ($harness): void {
                    if ($harness->fixtureLoginId !== null
                        && $harness->fixtureLoginEmail !== null) {
                        DB::table('user_logins')
                            ->where('id', $harness->fixtureLoginId)
                            ->where('user_id', self::USER_ID)
                            ->update(['email' => $harness->fixtureLoginEmail]);
                    }
                },
                'restore collision harness info marker' => function () use ($harness): void {
                    if ($harness->fixtureInfoId !== null
                        && $harness->fixtureUserName !== null) {
                        DB::table('user_infos')
                            ->where('id', $harness->fixtureInfoId)
                            ->where('user_id', self::USER_ID)
                            ->update(['user_name' => $harness->fixtureUserName]);
                    }
                },
            ]);
        }
    }

    public function test_cleanup_plan_revalidates_owned_rows_after_update_race(): void
    {
        $login = DB::table('user_logins')
            ->useWritePdo()
            ->where('user_id', self::USER_ID)
            ->first();
        $this->assertNotNull($login);
        $observerName = 'withdraw_task2_cleanup_plan_update_race';
        $observer = null;
        $listenerActive = false;
        $updateInjected = false;
        $failure = null;
        $rowAfterCleanup = null;
        $realEmail = 'real-customer-plan-update-' . self::USER_ID . '@example.test';
        $original = (array) $login;
        unset($original['id']);

        try {
            $observer = $this->fixtureObserverConnection($observerName);
            $observer->unsetEventDispatcher();
            $listenerActive = true;
            DB::listen(static function ($query) use (
                $observer,
                $login,
                $realEmail,
                &$listenerActive,
                &$updateInjected
            ): void {
                if (!$listenerActive
                    || $updateInjected
                    || strpos(strtolower((string) $query->sql), 'delete from `user_infos`') === false) {
                    return;
                }

                $updated = $observer->table('user_logins')
                    ->where('id', $login->id)
                    ->where('user_id', self::USER_ID)
                    ->where('email', $login->email)
                    ->update([
                        'email' => $realEmail,
                        'updated_at' => time(),
                    ]);
                if ($updated !== 1) {
                    throw new \RuntimeException(
                        'The cleanup plan update-race sentinel was not installed.'
                    );
                }
                $updateInjected = true;
            });

            try {
                $this->cleanupFixtureRows();
            } catch (\Throwable $exception) {
                $failure = $exception;
            }
            $rowAfterCleanup = $observer->table('user_logins')
                ->useWritePdo()
                ->where('id', $login->id)
                ->where('user_id', self::USER_ID)
                ->first();
        } finally {
            $listenerActive = false;
            $this->runSharedSystemConfigFixtureLifecycleCleanup(null, [
                'restore cleanup plan update-race login' => function () use (
                    $login,
                    $realEmail,
                    $original
                ): void {
                    DB::table('user_logins')
                        ->where('id', $login->id)
                        ->where('user_id', self::USER_ID)
                        ->where('email', $realEmail)
                        ->update($original);
                },
                'disconnect cleanup plan update-race observer' => static function () use (
                    $observer
                ): void {
                    if ($observer !== null) {
                        $observer->disconnect();
                    }
                },
                'purge cleanup plan update-race observer' => static function () use (
                    $observerName
                ): void {
                    DB::purge($observerName);
                },
                'remove cleanup plan update-race observer config' => static function () use (
                    $observerName
                ): void {
                    config()->offsetUnset('database.connections.' . $observerName);
                },
            ]);
        }

        $this->assertTrue($updateInjected);
        $this->assertNotNull(
            $rowAfterCleanup,
            'Cleanup deleted a planned login after its ownership signature changed.'
        );
        $this->assertSame($realEmail, (string) $rowAfterCleanup->email);
        $this->assertInstanceOf(\LogicException::class, $failure);
        $this->assertStringContainsString('user_logins', $failure->getMessage());
    }

    public function test_cleanup_keeps_unknown_orphan_outbox_row(): void
    {
        $expectedAutoIncrement =
            $this->settlementFixtureAutoIncrementSnapshot['withdraw_settlement_outbox'];
        $maxWithdrawalId = DB::table('withdraw_records')->useWritePdo()->max('id');
        $orphanWithdrawalId = (int) $maxWithdrawalId + 1000000;
        while (DB::table('withdraw_records')->useWritePdo()
            ->where('id', $orphanWithdrawalId)
            ->exists()) {
            ++$orphanWithdrawalId;
        }
        $marker = 'EXTERNAL-UNKNOWN-ORPHAN-' . $orphanWithdrawalId;
        $values = [
            'withdraw_record_id' => $orphanWithdrawalId,
            'local_order_no' => $marker,
            'event_type' => 'external_unknown_orphan',
            'status' => 'pending',
            'attempts' => 0,
            'payload_hash' => hash('sha256', $marker),
            'available_at' => 1711111111,
            'locked_at' => null,
            'processed_at' => null,
            'provider_reference' => null,
            'last_error_code' => null,
            'created_at' => 1711111111,
            'updated_at' => 1711111111,
            'deleted_at' => null,
        ];
        $orphanId = null;
        $signature = null;
        $reportedCount = null;
        $failure = null;
        $rowAfterCleanup = null;

        try {
            $orphanId = DB::table('withdraw_settlement_outbox')->insertGetId($values);
            $row = DB::table('withdraw_settlement_outbox')
                ->useWritePdo()
                ->where('id', $orphanId)
                ->first();
            $this->assertNotNull($row);
            $signature = $this->settlementFixtureRowSignature($row);
            $reportedCount = $this->outboxCount();

            try {
                $this->cleanupFixtureRows();
                $this->assertSettlementOutboxMatchesBaseline();
            } catch (\Throwable $exception) {
                $failure = $exception;
            }
            $rowAfterCleanup = DB::table('withdraw_settlement_outbox')
                ->useWritePdo()
                ->where('id', $orphanId)
                ->first();
        } finally {
            if ($signature !== null) {
                $this->deleteSettlementFixtureRowBySignature(
                    'withdraw_settlement_outbox',
                    $signature
                );
            } elseif ($orphanId !== null) {
                DB::table('withdraw_settlement_outbox')
                    ->where('id', $orphanId)
                    ->where('withdraw_record_id', $orphanWithdrawalId)
                    ->where('local_order_no', $marker)
                    ->delete();
            }
            DB::connection()->statement(
                'ALTER TABLE `withdraw_settlement_outbox` AUTO_INCREMENT = '
                . $expectedAutoIncrement
            );
            $this->assertSame(
                $expectedAutoIncrement,
                $this->readSettlementFixtureAutoIncrements()['withdraw_settlement_outbox']
            );
        }

        $this->assertSame(1, $reportedCount, 'The unknown outbox row was invisible to the test.');
        $this->assertInstanceOf(\RuntimeException::class, $failure);
        $this->assertStringContainsString(
            'withdraw_settlement_outbox baseline mismatch',
            $failure->getMessage()
        );
        $this->assertNotNull(
            $rowAfterCleanup,
            'Cleanup deleted an unknown orphan outbox row that it does not own.'
        );
        $this->assertSame($signature, $this->settlementFixtureRowSignature($rowAfterCleanup));
    }

    public function test_baseline_preexisting_orphan_row_is_kept(): void
    {
        $this->assertTrue(
            property_exists($this, 'settlementOutboxBaseline'),
            'The lifecycle must retain a whole-table settlement outbox baseline.'
        );
        $originalBaseline = $this->settlementOutboxBaseline;
        $expectedAutoIncrement =
            $this->settlementFixtureAutoIncrementSnapshot['withdraw_settlement_outbox'];
        $maxWithdrawalId = DB::table('withdraw_records')->useWritePdo()->max('id');
        $orphanWithdrawalId = (int) $maxWithdrawalId + 2000000;
        while (DB::table('withdraw_records')->useWritePdo()
            ->where('id', $orphanWithdrawalId)
            ->exists()) {
            ++$orphanWithdrawalId;
        }
        $marker = 'EXTERNAL-PREEXISTING-ORPHAN-' . $orphanWithdrawalId;
        $values = [
            'withdraw_record_id' => $orphanWithdrawalId,
            'local_order_no' => $marker,
            'event_type' => 'external_preexisting_orphan',
            'status' => 'pending',
            'attempts' => 0,
            'payload_hash' => hash('sha256', $marker),
            'available_at' => 1711111112,
            'locked_at' => null,
            'processed_at' => null,
            'provider_reference' => null,
            'last_error_code' => null,
            'created_at' => 1711111112,
            'updated_at' => 1711111112,
            'deleted_at' => null,
        ];
        $orphanId = null;
        $signature = null;
        $rowAfterCleanup = null;

        try {
            $orphanId = DB::table('withdraw_settlement_outbox')->insertGetId($values);
            $row = DB::table('withdraw_settlement_outbox')
                ->useWritePdo()
                ->where('id', $orphanId)
                ->first();
            $this->assertNotNull($row);
            $signature = $this->settlementFixtureRowSignature($row);
            $this->settlementOutboxBaseline = $this->readSettlementOutboxRows();

            $this->cleanupFixtureRows();
            $this->assertSettlementOutboxMatchesBaseline();
            $rowAfterCleanup = DB::table('withdraw_settlement_outbox')
                ->useWritePdo()
                ->where('id', $orphanId)
                ->first();
        } finally {
            $this->settlementOutboxBaseline = $originalBaseline;
            if ($signature !== null) {
                $this->deleteSettlementFixtureRowBySignature(
                    'withdraw_settlement_outbox',
                    $signature
                );
            } elseif ($orphanId !== null) {
                DB::table('withdraw_settlement_outbox')
                    ->where('id', $orphanId)
                    ->where('withdraw_record_id', $orphanWithdrawalId)
                    ->where('local_order_no', $marker)
                    ->delete();
            }
            DB::connection()->statement(
                'ALTER TABLE `withdraw_settlement_outbox` AUTO_INCREMENT = '
                . $expectedAutoIncrement
            );
            $this->assertSame(
                $expectedAutoIncrement,
                $this->readSettlementFixtureAutoIncrements()['withdraw_settlement_outbox']
            );
        }

        $this->assertNotNull(
            $rowAfterCleanup,
            'Cleanup deleted an unknown orphan that was already present in the baseline.'
        );
        $this->assertSame($signature, $this->settlementFixtureRowSignature($rowAfterCleanup));
    }

    public function test_cleanup_plan_race_revalidates_owned_rows(): void
    {
        $observerName = 'withdraw_task2_cleanup_plan_race';
        $expectedAutoIncrement = $this->settlementFixtureAutoIncrementSnapshot['user_logins'];
        $sentinelEmail = 'real-customer-plan-race-' . self::USER_ID . '@example.test';
        $sentinelPassword = Hash::make('password');
        $observer = null;
        $listenerActive = false;
        $sentinelLoginId = null;
        $sentinelSurvived = false;

        try {
            $observer = $this->fixtureObserverConnection($observerName);
            $observer->unsetEventDispatcher();
            $observer->statement('SET SESSION information_schema_stats_expiry = 0');
            $listenerActive = true;
            $this->listenForCleanupPlanLoginInsertion(
                $observer,
                $sentinelEmail,
                $sentinelPassword,
                $sentinelLoginId,
                $listenerActive
            );

            $this->cleanupFixtureRows();
            $sentinelSurvived = $sentinelLoginId !== null
                && $observer->table('user_logins')
                    ->useWritePdo()
                    ->where('id', $sentinelLoginId)
                    ->where('user_id', self::USER_ID)
                    ->where('email', $sentinelEmail)
                    ->exists();
        } finally {
            $listenerActive = false;
            $this->runSharedSystemConfigFixtureLifecycleCleanup(null, [
                'delete cleanup plan sentinel' => function () use (
                    $observer,
                    $sentinelLoginId,
                    $sentinelEmail
                ): void {
                    if ($observer !== null && $sentinelLoginId !== null) {
                        $observer->table('user_logins')
                            ->where('id', $sentinelLoginId)
                            ->where('user_id', self::USER_ID)
                            ->where('email', $sentinelEmail)
                            ->delete();
                    }
                },
                'restore cleanup plan sentinel AUTO_INCREMENT' => function () use (
                    $observer,
                    $expectedAutoIncrement
                ): void {
                    if ($observer === null) {
                        return;
                    }
                    $observer->statement(
                        'ALTER TABLE `user_logins` AUTO_INCREMENT = ' . $expectedAutoIncrement
                    );
                    $observer->statement('SET SESSION information_schema_stats_expiry = 0');
                    $status = $observer->selectOne(
                        'SELECT AUTO_INCREMENT AS auto_increment '
                        . 'FROM information_schema.TABLES '
                        . 'WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?',
                        ['user_logins'],
                        false
                    );
                    if (!$status || (int) $status->auto_increment !== $expectedAutoIncrement) {
                        throw new \RuntimeException(
                            'Cleanup plan sentinel AUTO_INCREMENT restore mismatch.'
                        );
                    }
                },
                'disconnect cleanup plan sentinel' => static function () use ($observer): void {
                    if ($observer !== null) {
                        $observer->disconnect();
                    }
                },
                'purge cleanup plan sentinel' => static function () use ($observerName): void {
                    DB::purge($observerName);
                },
                'remove cleanup plan sentinel connection config' => static function () use (
                    $observerName
                ): void {
                    config()->offsetUnset('database.connections.' . $observerName);
                },
            ]);
        }

        $this->assertNull(
            config('database.connections.' . $observerName),
            'Cleanup plan observer connection config was not removed.'
        );
        $this->assertNotNull($sentinelLoginId, 'The plan race sentinel was not inserted.');
        $this->assertTrue(
            $sentinelSurvived,
            'Cleanup deleted a same-user row that was absent from its validated plan.'
        );
    }

    public function test_inactive_cleanup_listener_does_not_reconnect(): void
    {
        $auth = DB::table('user_auths')
            ->useWritePdo()
            ->where('user_id', self::USER_ID)
            ->first();
        $this->assertNotNull($auth);
        $observerName = 'withdraw_task2_inactive_cleanup_listener';
        $expectedAutoIncrement = $this->readSettlementFixtureAutoIncrements()['user_logins'];
        $sentinelEmail = 'real-customer-inactive-listener-' . self::USER_ID . '@example.test';
        $sentinelPassword = Hash::make('password');
        $observer = null;
        $listenerActive = false;
        $sentinelLoginId = null;
        $failure = null;
        $sentinelInsertedAfterFailure = false;

        try {
            try {
                $observer = $this->fixtureObserverConnection($observerName);
                $observer->unsetEventDispatcher();
                $observer->statement('SET SESSION information_schema_stats_expiry = 0');
                $listenerActive = true;
                $this->listenForCleanupPlanLoginInsertion(
                    $observer,
                    $sentinelEmail,
                    $sentinelPassword,
                    $sentinelLoginId,
                    $listenerActive
                );
                DB::table('user_auths')
                    ->where('id', $auth->id)
                    ->where('user_id', self::USER_ID)
                    ->update(['id_card_no' => 'REAL-CUSTOMER-ID']);

                try {
                    $this->cleanupFixtureRows();
                } catch (\Throwable $exception) {
                    $failure = $exception;
                }
            } finally {
                $listenerActive = false;
                DB::table('user_auths')
                    ->where('id', $auth->id)
                    ->where('user_id', self::USER_ID)
                    ->where('id_card_no', 'REAL-CUSTOMER-ID')
                    ->update(['id_card_no' => $auth->id_card_no]);
                if ($observer !== null) {
                    $observer->disconnect();
                }
            }

            DB::table('user_infos')->where('id', -1)->delete();
            $sentinelInsertedAfterFailure = $sentinelLoginId !== null;
        } finally {
            $listenerActive = false;
            $this->runSharedSystemConfigFixtureLifecycleCleanup(null, [
                'delete inactive cleanup listener sentinel' => function () use (
                    $observer,
                    $sentinelLoginId,
                    $sentinelEmail
                ): void {
                    if ($observer !== null && $sentinelLoginId !== null) {
                        $observer->table('user_logins')
                            ->where('id', $sentinelLoginId)
                            ->where('user_id', self::USER_ID)
                            ->where('email', $sentinelEmail)
                            ->delete();
                    }
                },
                'restore inactive cleanup listener AUTO_INCREMENT' => function () use (
                    $observer,
                    $expectedAutoIncrement
                ): void {
                    if ($observer === null) {
                        return;
                    }
                    $observer->statement(
                        'ALTER TABLE `user_logins` AUTO_INCREMENT = ' . $expectedAutoIncrement
                    );
                    $observer->statement('SET SESSION information_schema_stats_expiry = 0');
                    $status = $observer->selectOne(
                        'SELECT AUTO_INCREMENT AS auto_increment '
                        . 'FROM information_schema.TABLES '
                        . 'WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?',
                        ['user_logins'],
                        false
                    );
                    if (!$status || (int) $status->auto_increment !== $expectedAutoIncrement) {
                        throw new \RuntimeException(
                            'Inactive cleanup listener AUTO_INCREMENT restore mismatch.'
                        );
                    }
                },
                'disconnect inactive cleanup listener observer' => static function () use (
                    $observer
                ): void {
                    if ($observer !== null) {
                        $observer->disconnect();
                    }
                },
                'purge inactive cleanup listener observer' => static function () use (
                    $observerName
                ): void {
                    DB::purge($observerName);
                },
                'remove inactive cleanup listener observer config' => static function () use (
                    $observerName
                ): void {
                    config()->offsetUnset('database.connections.' . $observerName);
                },
            ]);
        }

        $this->assertInstanceOf(\LogicException::class, $failure);
        $this->assertFalse(
            $sentinelInsertedAfterFailure,
            'An inactive cleanup listener reconnected and inserted its sentinel.'
        );
        $this->assertNull($sentinelLoginId);
        $this->assertNotNull($observer);
        $this->assertNull($observer->getRawPdo());
    }

    public function test_cleanup_fails_fast_and_deletes_nothing_when_owned_rows_change(): void
    {
        $fixtureLogin = DB::table('user_logins')->useWritePdo()
            ->where('user_id', self::USER_ID)
            ->first();
        $fixtureInfo = DB::table('user_infos')->useWritePdo()
            ->where('user_id', self::USER_ID)
            ->first();
        $fixtureAuth = DB::table('user_auths')->useWritePdo()
            ->where('user_id', self::USER_ID)
            ->first();
        $this->assertNotNull($fixtureLogin);
        $this->assertNotNull($fixtureInfo);
        $this->assertNotNull($fixtureAuth);
        $invalidLoginId = null;
        $invalidTradeId = null;
        $withdrawId = null;
        $outboxId = null;
        $invalidLoginEmail = 'real-customer-candidate-' . self::USER_ID . '@example.test';

        try {
            $invalidLoginId = DB::table('user_logins')->insertGetId([
                'user_id' => self::USER_ID,
                'email' => $invalidLoginEmail,
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
            DB::table('user_infos')->where('id', $fixtureInfo->id)->update(['login_id' => 0]);
            DB::table('user_auths')->where('id', $fixtureAuth->id)->update([
                'id_card_no' => 'REAL-CUSTOMER-ID',
            ]);
            $invalidTradeId = DB::table('user_trades')->insertGetId([
                'user_id' => self::USER_ID,
                'ticket' => 412372992,
                'symbol' => 'EXTERNAL',
                'digits' => 5,
                'cmd' => 0,
                'volume' => 100,
                'open_time' => '2026-07-17 09:00:00',
                'open_price' => 1.1,
                'close_time' => '1970-01-01 00:00:00',
                'modify_time' => '2026-07-17 09:00:00',
                'created_at' => time(),
                'updated_at' => time(),
                'deleted_at' => null,
            ]);
            $this->insertReservedWithdrawal('pending', '80.00');
            $withdraw = DB::table('withdraw_records')->useWritePdo()
                ->where('user_id', self::USER_ID)
                ->where('idempotency_key', 'seed-pending')
                ->first();
            $this->assertNotNull($withdraw);
            $withdrawId = (int) $withdraw->id;
            $outbox = DB::table('withdraw_settlement_outbox')->useWritePdo()
                ->where('withdraw_record_id', $withdrawId)
                ->first();
            $this->assertNotNull($outbox);
            $outboxId = (int) $outbox->id;
            DB::table('withdraw_records')->where('id', $withdrawId)->update([
                'user_name' => 'real-customer',
            ]);
            DB::table('withdraw_settlement_outbox')->where('id', $outboxId)->update([
                'event_type' => 'external_event',
            ]);

            $this->assertCleanupFailsWithoutDeletingOwnedRows('user_logins');

            DB::table('user_logins')->where('id', $invalidLoginId)->delete();
            $this->assertCleanupFailsWithoutDeletingOwnedRows('user_infos');

            DB::table('user_infos')->where('id', $fixtureInfo->id)->update([
                'login_id' => $fixtureInfo->login_id,
            ]);
            $this->assertCleanupFailsWithoutDeletingOwnedRows('user_auths');

            DB::table('user_auths')->where('id', $fixtureAuth->id)->update([
                'id_card_no' => $fixtureAuth->id_card_no,
            ]);
            $this->assertCleanupFailsWithoutDeletingOwnedRows('user_trades');

            DB::table('user_trades')->where('id', $invalidTradeId)->delete();
            $this->assertCleanupFailsWithoutDeletingOwnedRows('withdraw_records');

            DB::table('withdraw_records')->where('id', $withdrawId)->update([
                'user_name' => $withdraw->user_name,
            ]);
            $this->assertCleanupFailsWithoutDeletingOwnedRows('withdraw_settlement_outbox');

            DB::table('withdraw_settlement_outbox')->where('id', $outboxId)->update([
                'event_type' => $outbox->event_type,
            ]);
            $this->cleanupFixtureRows();
            $this->assertSame([
                'user_logins' => 0,
                'user_infos' => 0,
                'user_auths' => 0,
                'user_trades' => 0,
                'withdraw_records' => 0,
                'withdraw_settlement_outbox' => 0,
            ], $this->settlementFixtureOwnedRowCounts());
        } finally {
            if ($outboxId !== null) {
                DB::table('withdraw_settlement_outbox')->where('id', $outboxId)->delete();
            }
            if ($withdrawId !== null) {
                DB::table('withdraw_records')->where('id', $withdrawId)->delete();
            }
            if ($invalidTradeId !== null) {
                DB::table('user_trades')->where('id', $invalidTradeId)->delete();
            }
            if ($invalidLoginId !== null) {
                DB::table('user_logins')
                    ->where('id', $invalidLoginId)
                    ->where('user_id', self::USER_ID)
                    ->where('email', $invalidLoginEmail)
                    ->delete();
            }
            DB::table('user_infos')->where('id', $fixtureInfo->id)->update([
                'login_id' => $fixtureInfo->login_id,
            ]);
            DB::table('user_auths')->where('id', $fixtureAuth->id)->update([
                'id_card_no' => $fixtureAuth->id_card_no,
            ]);
        }
    }

    public function test_lifecycle_restores_auto_increments_after_fixture_advance(): void
    {
        $this->assertTrue(
            property_exists($this, 'settlementFixtureAutoIncrementSnapshot'),
            'The settlement fixture lifecycle must retain an AUTO_INCREMENT snapshot.'
        );
        $expected = $this->settlementFixtureAutoIncrementSnapshot;
        $this->assertIsArray($expected);
        $this->assertSame([
            'user_auths',
            'user_infos',
            'user_logins',
            'user_trades',
            'withdraw_records',
            'withdraw_settlement_outbox',
        ], array_keys($expected));

        $this->cleanupFixtureRows();
        $this->insertUser();
        $this->insertApprovedBank();
        $this->insertOpenTrade();
        $this->insertReservedWithdrawal('pending', '80.00');

        $advanced = $this->readSettlementFixtureAutoIncrements();
        foreach ($expected as $table => $autoIncrement) {
            $this->assertGreaterThan(
                $autoIncrement,
                $advanced[$table],
                $table . ' AUTO_INCREMENT was not advanced by its fixture insert.'
            );
        }

        $primary = new \RuntimeException('simulated settlement fixture setup failure');
        $caught = null;
        try {
            $this->runSharedSystemConfigFixtureLifecycleCleanup($primary, [
                'clean settlement fixture rows' => function (): void {
                    $this->cleanupFixtureRows();
                },
                'restore settlement fixture AUTO_INCREMENT values' => function (): void {
                    $this->restoreSettlementFixtureAutoIncrements();
                },
            ]);
        } catch (\Throwable $exception) {
            $caught = $exception;
        }

        $this->assertSame($primary, $caught, 'Lifecycle cleanup replaced the primary setup failure.');
        $this->assertSame($expected, $this->readSettlementFixtureAutoIncrements());
        $this->assertNull($this->settlementFixtureAutoIncrementSnapshot);
        $this->assertSame([
            'user_logins' => 0,
            'user_infos' => 0,
            'user_auths' => 0,
            'user_trades' => 0,
            'withdraw_records' => 0,
            'withdraw_settlement_outbox' => 0,
        ], $this->settlementFixtureOwnedRowCounts());
    }

    public function test_withdrawal_account_snapshot_available_contract(): void
    {
        $this->assertTrue(method_exists(
            \App\Services\Withdrawal\WithdrawalAccountSnapshot::class,
            'available'
        ));

        $snapshot = new \App\Services\Withdrawal\WithdrawalAccountSnapshot('100.1', '+80');
        $this->assertSame('100.10', $snapshot->balance());
        $this->assertSame('80.00', $snapshot->freeMargin());
        $this->assertSame('80.00', $snapshot->available());

        $negative = new \App\Services\Withdrawal\WithdrawalAccountSnapshot('-1.00', '50.00');
        $this->assertSame('0.00', $negative->available());
    }

    /** @dataProvider invalidSnapshotDecimalProvider */
    public function test_invalid_snapshot_decimal_rejected(string $value): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new \App\Services\Withdrawal\WithdrawalAccountSnapshot($value, '100.00');
    }

    public function invalidSnapshotDecimalProvider(): array
    {
        return [
            'empty' => [''],
            'scientific notation' => ['1e3'],
            'three decimals' => ['1.001'],
            'leading decimal point' => ['.50'],
            'trailing decimal point' => ['1.'],
            'DECIMAL positive overflow' => ['10000000000000000.00'],
            'DECIMAL negative overflow' => ['-10000000000000000.00'],
        ];
    }

    public function test_withdrawal_order_service_same_key_returns_same_order(): void
    {
        $gateway = $this->snapshotGateway('1000.00', '800.00');
        $service = new WithdrawalOrderService($gateway);
        $user = UserInfo::where('user_id', self::USER_ID)->firstOrFail();
        $amount = Money::fromDecimalString('120.10', '10.00', '500000.00');

        try {
            $first = $service->createOrRetrieve($user, $amount, 'same-withdraw-key');
            $second = $service->createOrRetrieve($user, $amount, 'same-withdraw-key');
        } catch (\LogicException $exception) {
            $this->fail($exception->getMessage());
        }

        $this->assertTrue($first['created']);
        $this->assertFalse($second['created']);
        $this->assertSame($first['order']->id, $second['order']->id);
        $this->assertSame(1, $gateway->calls);
        $this->assertSame([0], $gateway->transactionLevels);
        $this->assertSame(1, $this->withdrawCount());
        $this->assertSame(1, $this->outboxCount());
        $this->assertDatabaseHas('withdraw_records', [
            'user_id' => self::USER_ID,
            'idempotency_key' => 'same-withdraw-key',
            'apply_amount' => '120.10',
            'funding_status' => 'pending',
            'status' => 0,
        ]);
        $this->assertDatabaseHas('withdraw_settlement_outbox', [
            'withdraw_record_id' => $first['order']->id,
            'local_order_no' => $first['order']->local_order_no,
            'event_type' => 'withdraw_debit',
            'status' => 'pending',
            'attempts' => 0,
        ]);
        $this->assertDatabaseHas('user_infos', [
            'user_id' => self::USER_ID,
            'total_funds' => '9999.00',
            'avail_margin' => '9999.00',
        ]);
    }

    public function test_canonical_payload_hash_reflects_bank_snapshot(): void
    {
        $service = new WithdrawalOrderService($this->snapshotGateway('1000.00', '1000.00'));
        $user = UserInfo::where('user_id', self::USER_ID)->firstOrFail();
        $first = $service->createOrRetrieve(
            $user,
            Money::fromDecimalString('120.10', '10.00', '500000.00'),
            'canonical-payload-first'
        )['order']->fresh();

        DB::table('user_auths')->where('user_id', self::USER_ID)->update([
            'bank_no' => 'TASK2-BANK-002',
            'updated_at' => time(),
        ]);
        $second = $service->createOrRetrieve(
            $user,
            Money::fromDecimalString('121.10', '10.00', '500000.00'),
            'canonical-payload-second'
        )['order']->fresh();

        $canonicalPayload = static function ($order): array {
            return [
                'event_type' => 'withdraw_debit',
                'local_order_no' => (string) $order->local_order_no,
                'user_id' => (int) $order->user_id,
                'amount' => (string) $order->apply_amount,
                'fee' => (string) $order->fee,
                'actual_amount' => (string) $order->actual_amount,
                'exchange_rate' => (string) $order->exchange_rate,
                'rmb_fee' => (string) $order->rmb_fee,
                'bank_no' => (string) $order->bank_no,
                'bank_name' => (string) $order->bank_name,
                'bank_addr' => (string) $order->bank_addr,
            ];
        };
        $canonicalHash = static function (array $payload): string {
            return hash('sha256', json_encode(
                $payload,
                JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
            ));
        };

        $firstPayload = $canonicalPayload($first);
        $secondPayload = $canonicalPayload($second);
        $this->assertSame([
            'event_type',
            'local_order_no',
            'user_id',
            'amount',
            'fee',
            'actual_amount',
            'exchange_rate',
            'rmb_fee',
            'bank_no',
            'bank_name',
            'bank_addr',
        ], array_keys($firstPayload));
        $this->assertSame('120.10', $firstPayload['amount']);
        $this->assertSame('TASK2-BANK-001', $firstPayload['bank_no']);
        $this->assertSame('121.10', $secondPayload['amount']);
        $this->assertSame('TASK2-BANK-002', $secondPayload['bank_no']);

        $firstHash = $canonicalHash($firstPayload);
        $secondHash = $canonicalHash($secondPayload);
        $firstOutboxHash = (string) DB::table('withdraw_settlement_outbox')
            ->where('withdraw_record_id', $first->id)
            ->value('payload_hash');
        $secondOutboxHash = (string) DB::table('withdraw_settlement_outbox')
            ->where('withdraw_record_id', $second->id)
            ->value('payload_hash');

        $this->assertSame($firstHash, (string) $first->funding_payload_hash);
        $this->assertSame($firstHash, $firstOutboxHash);
        $this->assertSame($secondHash, (string) $second->funding_payload_hash);
        $this->assertSame($secondHash, $secondOutboxHash);
        $this->assertNotSame($firstHash, $secondHash);
        $this->assertStringContainsString(
            'JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR',
            file_get_contents(app_path('Services/Withdrawal/WithdrawalOrderService.php')) ?: ''
        );
    }

    public function test_different_amount_conflict_rejected(): void
    {
        $gateway = $this->snapshotGateway();
        $service = new WithdrawalOrderService($gateway);
        $user = UserInfo::where('user_id', self::USER_ID)->firstOrFail();
        $service->createOrRetrieve(
            $user,
            Money::fromDecimalString('120.10', '10.00', '500000.00'),
            'different-withdraw-amount'
        );

        try {
            $service->createOrRetrieve(
                $user,
                Money::fromDecimalString('120.11', '10.00', '500000.00'),
                'different-withdraw-amount'
            );
            $this->fail('Expected an idempotency amount conflict.');
        } catch (DomainException $exception) {
            $this->assertStringContainsString('different amount', $exception->getMessage());
        }

        $this->assertSame(1, $gateway->calls);
        $this->assertSame(1, $this->withdrawCount());
        $this->assertSame(1, $this->outboxCount());
    }

    public function test_soft_deleted_withdraw_key_not_reusable(): void
    {
        $gateway = $this->snapshotGateway();
        $service = new WithdrawalOrderService($gateway);
        $user = UserInfo::where('user_id', self::USER_ID)->firstOrFail();
        $created = $service->createOrRetrieve(
            $user,
            Money::fromDecimalString('150.00', '10.00', '500000.00'),
            'soft-deleted-withdraw-key'
        );
        $created['order']->delete();

        try {
            $service->createOrRetrieve(
                $user,
                Money::fromDecimalString('150.00', '10.00', '500000.00'),
                'soft-deleted-withdraw-key'
            );
            $this->fail('A soft-deleted withdrawal key must not be reusable.');
        } catch (DomainException $exception) {
            $this->assertStringContainsString('soft-deleted', $exception->getMessage());
        }

        $this->assertSame(1, $gateway->calls);
        $this->assertSame(1, DB::table('withdraw_records')
            ->where('user_id', self::USER_ID)
            ->where('idempotency_key', 'soft-deleted-withdraw-key')
            ->count());
        $this->assertSame(1, $this->outboxCount());
    }

    public function test_race_same_amount_returns_existing_order(): void
    {
        $gateway = $this->snapshotGateway();
        $service = new WithdrawalOrderService($gateway, function (array $attributes) {
            $this->insertCompetingWithdrawal($attributes);
            throw $this->duplicateIdempotencyException();
        });

        $result = $this->withReadCommittedSessionIsolation(function () use ($service) {
            $result = $service->createOrRetrieve(
                UserInfo::where('user_id', self::USER_ID)->firstOrFail(),
                Money::fromDecimalString('140.00', '10.00', '500000.00'),
                'race-same-withdraw-amount'
            );

            return $result;
        });

        $this->assertFalse($result['created']);
        $this->assertSame('140.00', (string) $result['order']->apply_amount);
        $this->assertSame(1, $this->withdrawCount());
        $this->assertSame(1, $this->outboxCount());
    }

    public function test_race_different_amount_conflicts(): void
    {
        $gateway = $this->snapshotGateway();
        $service = new WithdrawalOrderService($gateway, function (array $attributes) {
            $attributes['apply_amount'] = '140.01';
            $this->insertCompetingWithdrawal($attributes);
            throw $this->duplicateIdempotencyException();
        });

        $this->withReadCommittedSessionIsolation(function () use ($service): void {
            try {
                $service->createOrRetrieve(
                    UserInfo::where('user_id', self::USER_ID)->firstOrFail(),
                    Money::fromDecimalString('140.00', '10.00', '500000.00'),
                    'race-different-withdraw-amount'
                );
                $this->fail('Expected a competing different amount to conflict.');
            } catch (DomainException $exception) {
                $this->assertStringContainsString('different amount', $exception->getMessage());
            }
        });

        $this->assertSame(1, $this->withdrawCount());
        $this->assertSame(1, $this->outboxCount());
    }

    public function test_read_committed_isolation_restores_original(): void
    {
        $connection = DB::connection();
        $originalIsolation = $this->readSessionTransactionIsolation($connection);

        try {
            $this->setSessionTransactionIsolation($connection, 'SERIALIZABLE');
            $result = $this->withReadCommittedSessionIsolation(
                function () use ($connection): string {
                    $this->assertSame(
                        'READ COMMITTED',
                        $this->readSessionTransactionIsolation($connection)
                    );

                    return 'read-committed-callback-result';
                }
            );

            $this->assertSame('read-committed-callback-result', $result);
            $this->assertSame(
                'SERIALIZABLE',
                $this->readSessionTransactionIsolation($connection)
            );
            $this->assertSame(0, $this->withdrawCount());
            $this->assertSame(0, $this->outboxCount());
        } finally {
            $this->setSessionTransactionIsolation($connection, $originalIsolation);
        }
    }

    public function test_read_committed_callback_failure_restores_isolation(): void
    {
        $connection = DB::connection();
        $originalIsolation = $this->readSessionTransactionIsolation($connection);
        $expected = new \RuntimeException('simulated read committed callback failure');
        $caught = null;

        try {
            $this->setSessionTransactionIsolation($connection, 'SERIALIZABLE');
            try {
                $this->withReadCommittedSessionIsolation(
                    function () use ($connection, $expected): void {
                        $this->assertSame(
                            'READ COMMITTED',
                            $this->readSessionTransactionIsolation($connection)
                        );
                        throw $expected;
                    }
                );
            } catch (\Throwable $exception) {
                $caught = $exception;
            }

            $this->assertSame($expected, $caught);
            $this->assertSame(
                'SERIALIZABLE',
                $this->readSessionTransactionIsolation($connection)
            );
            $this->assertSame(0, $this->withdrawCount());
            $this->assertSame(0, $this->outboxCount());
        } finally {
            $this->setSessionTransactionIsolation($connection, $originalIsolation);
        }
    }

    public function test_read_committed_isolation_uses_write_session_only(): void
    {
        $originalDefault = DB::getDefaultConnection();
        $splitName = 'withdraw_task2_isolation_split';
        $splitConnection = null;
        $writePdo = null;
        $readPdo = null;
        $writeOriginal = null;
        $readOriginal = null;
        $normalize = function (string $isolation): string {
            return $this->normalizeSessionTransactionIsolation($isolation);
        };

        try {
            $baseConfig = config('database.connections.' . $originalDefault);
            $endpoint = array_intersect_key($baseConfig, array_flip([
                'host',
                'port',
                'database',
                'username',
                'password',
                'unix_socket',
            ]));
            $splitConfig = $baseConfig;
            $splitConfig['read'] = $endpoint;
            $splitConfig['write'] = $endpoint;
            $splitConfig['sticky'] = false;
            config(['database.connections.' . $splitName => $splitConfig]);
            DB::purge($splitName);
            DB::setDefaultConnection($splitName);
            $splitConnection = DB::connection($splitName);
            $writePdo = $splitConnection->getPdo();
            $readPdo = $splitConnection->getReadPdo();
            $this->assertNotSame($writePdo, $readPdo);
            $writeOriginal = $normalize((string) $writePdo
                ->query('SELECT @@SESSION.transaction_isolation')
                ->fetchColumn());
            $readOriginal = $normalize((string) $readPdo
                ->query('SELECT @@SESSION.transaction_isolation')
                ->fetchColumn());
            $writePdo->exec('SET SESSION TRANSACTION ISOLATION LEVEL SERIALIZABLE');
            $readPdo->exec('SET SESSION TRANSACTION ISOLATION LEVEL READ UNCOMMITTED');
            $insideIsolation = null;

            $result = $this->withReadCommittedSessionIsolation(
                static function () use ($writePdo, $normalize, &$insideIsolation): string {
                    $insideIsolation = $normalize((string) $writePdo
                        ->query('SELECT @@SESSION.transaction_isolation')
                        ->fetchColumn());

                    return 'write-session-result';
                }
            );

            $this->assertSame('write-session-result', $result);
            $this->assertSame('READ COMMITTED', $insideIsolation);
            $this->assertSame(
                'SERIALIZABLE',
                $normalize((string) $writePdo
                    ->query('SELECT @@SESSION.transaction_isolation')
                    ->fetchColumn())
            );
            $this->assertSame(
                'READ UNCOMMITTED',
                $normalize((string) $readPdo
                    ->query('SELECT @@SESSION.transaction_isolation')
                    ->fetchColumn())
            );
        } finally {
            $this->runSharedSystemConfigFixtureLifecycleCleanup(null, [
                'restore split write isolation' => static function () use (
                    &$writePdo,
                    &$writeOriginal
                ): void {
                    if ($writePdo !== null && $writeOriginal !== null) {
                        $writePdo->exec(
                            'SET SESSION TRANSACTION ISOLATION LEVEL ' . $writeOriginal
                        );
                    }
                },
                'restore split read isolation' => static function () use (
                    &$readPdo,
                    &$readOriginal
                ): void {
                    if ($readPdo !== null && $readOriginal !== null) {
                        $readPdo->exec(
                            'SET SESSION TRANSACTION ISOLATION LEVEL ' . $readOriginal
                        );
                    }
                },
                'restore default database connection' => static function () use (
                    $originalDefault
                ): void {
                    DB::setDefaultConnection($originalDefault);
                },
                'disconnect split database connection' => static function () use (
                    &$splitConnection
                ): void {
                    if ($splitConnection !== null) {
                        $splitConnection->disconnect();
                    }
                },
                'purge split database connection' => static function () use ($splitName): void {
                    DB::purge($splitName);
                },
                'remove split database connection config' => static function () use (
                    $splitName
                ): void {
                    config()->offsetUnset('database.connections.' . $splitName);
                },
            ]);
        }
    }

    public function test_snapshot_failure_fails_closed(): void
    {
        $gateway = new class implements WithdrawalAccountSnapshotGateway {
            /**
             * 快照替身抛出模拟 MT4 读失败前记录的 DB 事务层级。
             * 断言其值为 [0]，证明快照失败发生在开户事务之外，服务按失败关闭语义直接中止。
             * @var array<int, int>
             */
            public $transactionLevels = [];

            public function snapshot(int $userId): WithdrawalAccountSnapshot
            {
                $this->transactionLevels[] = DB::transactionLevel();
                throw new \RuntimeException('simulated MT4 account read failure');
            }
        };

        try {
            (new WithdrawalOrderService($gateway))->createOrRetrieve(
                UserInfo::where('user_id', self::USER_ID)->firstOrFail(),
                Money::fromDecimalString('100.00', '10.00', '500000.00'),
                'snapshot-failure'
            );
            $this->fail('A failed MT4 account snapshot must fail closed.');
        } catch (DomainException $exception) {
            $this->assertSame('snapshot_unavailable', $exception->getMessage());
        }

        $this->assertSame([0], $gateway->transactionLevels);
        $this->assertSame(0, $this->withdrawCount());
        $this->assertSame(0, $this->outboxCount());
    }

    public function test_pending_reservation_limits_second_key(): void
    {
        $gateway = $this->snapshotGateway('200.00', '200.00');
        $service = new WithdrawalOrderService($gateway);
        $user = UserInfo::where('user_id', self::USER_ID)->firstOrFail();
        $first = $service->createOrRetrieve(
            $user,
            Money::fromDecimalString('150.00', '10.00', '500000.00'),
            'reservation-first-key'
        );

        try {
            $service->createOrRetrieve(
                $user,
                Money::fromDecimalString('100.00', '10.00', '500000.00'),
                'reservation-second-key'
            );
            $this->fail('The second key must account for the first pending reservation.');
        } catch (DomainException $exception) {
            $this->assertSame('insufficient_balance', $exception->getMessage());
        }

        $this->assertTrue($first['created']);
        $this->assertSame(2, $gateway->calls);
        $this->assertSame([0, 0], $gateway->transactionLevels);
        $this->assertSame(1, $this->withdrawCount());
        $this->assertSame(1, $this->outboxCount());
    }

    /** @dataProvider reservingFundingStatusProvider */
    public function test_reserving_funding_status_blocks_new_withdrawal(string $fundingStatus): void
    {
        $this->insertReservedWithdrawal($fundingStatus, '80.00');
        $gateway = $this->snapshotGateway('100.00', '100.00');

        try {
            (new WithdrawalOrderService($gateway))->createOrRetrieve(
                UserInfo::where('user_id', self::USER_ID)->firstOrFail(),
                Money::fromDecimalString('30.00', '10.00', '500000.00'),
                'reserved-' . $fundingStatus
            );
            $this->fail($fundingStatus . ' must reserve account balance.');
        } catch (DomainException $exception) {
            $this->assertSame('insufficient_balance', $exception->getMessage());
        }

        $this->assertSame(1, $this->withdrawCount());
        $this->assertSame(1, $this->outboxCount());
    }

    public function reservingFundingStatusProvider(): array
    {
        return [
            'pending' => ['pending'],
            'processing' => ['processing'],
            'unknown' => ['unknown'],
            'retryable' => ['retryable'],
        ];
    }

    /** @dataProvider reservingFundingStatusProvider */
    public function test_soft_deleted_unsettled_withdrawals_still_reserve_remote_balance(
        string $fundingStatus
    ): void {
        $this->insertReservedWithdrawal($fundingStatus, '80.00');
        DB::table('withdraw_records')
            ->where('user_id', self::USER_ID)
            ->where('idempotency_key', 'seed-' . $fundingStatus)
            ->update(['deleted_at' => time()]);
        $ordersBefore = $this->withdrawCount();
        $outboxBefore = $this->outboxCount();
        $gateway = $this->snapshotGateway('100.00', '100.00');

        try {
            (new WithdrawalOrderService($gateway))->createOrRetrieve(
                UserInfo::where('user_id', self::USER_ID)->firstOrFail(),
                Money::fromDecimalString('30.00', '10.00', '500000.00'),
                'soft-deleted-reserved-' . $fundingStatus
            );
            $this->fail($fundingStatus . ' must reserve balance after soft deletion.');
        } catch (DomainException $exception) {
            $this->assertSame('insufficient_balance', $exception->getMessage());
        }

        $this->assertSame(1, $gateway->calls);
        $this->assertSame($ordersBefore, $this->withdrawCount());
        $this->assertSame($outboxBefore, $this->outboxCount());
    }

    /** @dataProvider reservingFundingStatusProvider */
    public function test_active_funding_statuses_reserve_remote_available_balance(): void
    {
        $provider = $this->reservingFundingStatusProvider();
        $this->assertCount(4, $provider);
        $this->assertSame([
            'pending',
            'processing',
            'unknown',
            'retryable',
        ], array_keys($provider));

        foreach ([
            'test_active_funding_statuses_reserve_remote_available_balance',
            'test_soft_deleted_unsettled_withdrawals_still_reserve_remote_balance',
        ] as $testMethod) {
            $docComment = (new \ReflectionMethod($this, $testMethod))->getDocComment();
            $this->assertIsString($docComment);
            $this->assertStringContainsString(
                '@dataProvider reservingFundingStatusProvider',
                $docComment
            );
        }

        $this->assertFalse(method_exists($this, 'softDeletedReservingFundingStatusProvider'));
    }

    public function test_withdrawal_creation_locks_user_row_for_update(): void
    {
        $statements = [];
        DB::listen(static function ($query) use (&$statements): void {
            $statements[] = strtolower((string) $query->sql);
        });

        (new WithdrawalOrderService($this->snapshotGateway()))->createOrRetrieve(
            UserInfo::where('user_id', self::USER_ID)->firstOrFail(),
            Money::fromDecimalString('100.00', '10.00', '500000.00'),
            'for-update-evidence'
        );

        $this->assertNotEmpty(array_filter($statements, static function (string $sql): bool {
            return strpos($sql, 'from `user_infos`') !== false
                && strpos($sql, 'for update') !== false;
        }), 'Withdrawal creation must issue SELECT ... FOR UPDATE against user_infos.');
    }

    public function test_held_reservation_lock_fails_closed_then_succeeds(): void
    {
        $lockName = $this->reservationLockName(self::USER_ID);
        $this->assertLessThanOrEqual(64, strlen($lockName));
        $holder = $this->lockHolderConnection();
        $acquired = $holder->selectOne('SELECT GET_LOCK(?, 0) AS acquired', [$lockName]);
        $this->assertSame(1, (int) $acquired->acquired);
        $failure = null;

        try {
            try {
                (new WithdrawalOrderService($this->snapshotGateway()))->createOrRetrieve(
                    UserInfo::where('user_id', self::USER_ID)->firstOrFail(),
                    Money::fromDecimalString('100.00', '10.00', '500000.00'),
                    'held-reservation-lock'
                );
            } catch (DomainException $exception) {
                $failure = $exception;
            }
        } finally {
            $holder->selectOne('SELECT RELEASE_LOCK(?) AS released', [$lockName]);
            DB::disconnect('withdraw_task2_lock_holder');
        }

        $this->assertInstanceOf(DomainException::class, $failure);
        $this->assertSame('reservation_lock_unavailable', $failure->getMessage());
        $this->assertSame(0, $this->withdrawCount());
        $this->assertSame(0, $this->outboxCount());

        $result = (new WithdrawalOrderService($this->snapshotGateway()))->createOrRetrieve(
            UserInfo::where('user_id', self::USER_ID)->firstOrFail(),
            Money::fromDecimalString('100.00', '10.00', '500000.00'),
            'held-reservation-lock'
        );
        $this->assertTrue($result['created']);
        $this->assertSame(1, $this->withdrawCount());
        $this->assertSame(1, $this->outboxCount());
    }

    public function test_api_submission_retry_after_reservation_busy(): void
    {
        $lockName = $this->reservationLockName(self::USER_ID);
        $holder = $this->lockHolderConnection();
        $acquired = $holder->selectOne('SELECT GET_LOCK(?, 0) AS acquired', [$lockName]);
        $this->assertSame(1, (int) $acquired->acquired);
        $gateway = $this->snapshotGateway('500.00', '500.00');
        $this->app->instance(WithdrawalAccountSnapshotGateway::class, $gateway);
        $key = 'api-held-reservation-retry';

        try {
            $failure = $this->submit('100.00', $key);
        } finally {
            $holder->selectOne('SELECT RELEASE_LOCK(?) AS released', [$lockName]);
            DB::disconnect('withdraw_task2_lock_holder');
        }

        $failure->assertOk()
            ->assertJsonPath('code', ResponseCode::SERVER_ERROR)
            ->assertJsonPath('message', __('response.withdrawal_reservation_busy'));
        $this->assertSame(0, $this->withdrawCount());
        $this->assertSame(0, $this->outboxCount());

        $retry = $this->submit('100.00', $key);

        $retry->assertOk()->assertJsonPath('code', ResponseCode::CREATED);
        $this->assertSame(2, $gateway->calls);
        $this->assertSame(1, $this->withdrawCount());
        $this->assertSame(1, $this->outboxCount());
        $this->assertDatabaseHas('withdraw_records', [
            'user_id' => self::USER_ID,
            'idempotency_key' => $key,
            'apply_amount' => '100.00',
        ]);
    }

    public function test_reservation_lock_released_after_transaction_failure(): void
    {
        $lockName = $this->reservationLockName(self::USER_ID);
        $lockQueries = [];
        DB::listen(static function ($query) use (&$lockQueries): void {
            $sql = strtolower((string) $query->sql);
            if (strpos($sql, 'get_lock') !== false || strpos($sql, 'release_lock') !== false) {
                $lockQueries[] = [$query->connectionName, $sql, $query->bindings];
            }
        });
        $service = new WithdrawalOrderService($this->snapshotGateway(), static function (): void {
            throw new \RuntimeException('simulated local insert failure');
        });

        try {
            $service->createOrRetrieve(
                UserInfo::where('user_id', self::USER_ID)->firstOrFail(),
                Money::fromDecimalString('100.00', '10.00', '500000.00'),
                'transaction-lock-release'
            );
            $this->fail('Expected the simulated transaction failure.');
        } catch (\RuntimeException $exception) {
            $this->assertSame('simulated local insert failure', $exception->getMessage());
        }

        $free = DB::selectOne('SELECT IS_FREE_LOCK(?) AS is_free', [$lockName]);
        $this->assertSame(1, (int) $free->is_free);
        $this->assertCount(2, $lockQueries);
        $this->assertSame(DB::getDefaultConnection(), $lockQueries[0][0]);
        $this->assertSame(DB::getDefaultConnection(), $lockQueries[1][0]);
        $this->assertStringContainsString('get_lock', $lockQueries[0][1]);
        $this->assertStringContainsString('release_lock', $lockQueries[1][1]);
        $this->assertSame([$lockName], $lockQueries[0][2]);
        $this->assertSame([$lockName], $lockQueries[1][2]);
        $this->assertSame(0, $this->withdrawCount());
        $this->assertSame(0, $this->outboxCount());
    }

    public function test_release_failure_after_primary_exception_logged_and_connection_closed(): void
    {
        Log::spy();
        $connection = DB::connection();
        $connection->getPdo();
        $lockName = $this->reservationLockName(self::USER_ID);
        $primary = new \RuntimeException('primary transaction failure');
        $releaseFailure = new \RuntimeException('simulated release failure');
        $releaseAttempts = 0;
        $service = new WithdrawalOrderService(
            $this->snapshotGateway(),
            static function () use ($primary): void {
                throw $primary;
            },
            static function () use (&$releaseAttempts, $releaseFailure): void {
                $releaseAttempts++;
                throw $releaseFailure;
            }
        );
        $caught = null;

        try {
            $service->createOrRetrieve(
                UserInfo::where('user_id', self::USER_ID)->firstOrFail(),
                Money::fromDecimalString('100.00', '10.00', '500000.00'),
                'release-failure-primary-exception'
            );
        } catch (\Throwable $exception) {
            $caught = $exception;
        }

        $this->assertSame(1, $releaseAttempts);
        $this->assertSame($primary, $caught);
        $this->assertNull($connection->getRawPdo());
        Log::shouldHaveReceived('error')->once()->withArgs(
            static function (string $message, array $context) use ($lockName, $releaseFailure): bool {
                return $message === 'withdrawal.reservation_lock_release_failed'
                    && $context['lock_hash'] === hash('sha256', $lockName)
                    && $context['database'] === DB::getDatabaseName()
                    && $context['exception_class'] === \RuntimeException::class
                    && $context['exception_message'] === $releaseFailure->getMessage();
            }
        );

        $observer = $this->fixtureObserverConnection('withdraw_task2_release_failure_observer');
        $acquired = null;
        try {
            $acquired = $observer->selectOne('SELECT GET_LOCK(?, 0) AS acquired', [$lockName], false);
            $this->assertSame(1, (int) $acquired->acquired);
        } finally {
            if ($acquired && (int) $acquired->acquired === 1) {
                $observer->selectOne('SELECT RELEASE_LOCK(?) AS released', [$lockName], false);
            }
            $observer->disconnect();
        }

        $this->assertSame(0, $this->withdrawCount());
        $this->assertSame(0, $this->outboxCount());
    }

    public function test_release_failure_after_commit_logged_and_order_created(): void
    {
        Log::spy();
        $connection = DB::connection();
        $connection->getPdo();
        $lockName = $this->reservationLockName(self::USER_ID);
        $releaseFailure = new \RuntimeException('simulated release failure after commit');
        $releaseAttempts = 0;
        $service = new WithdrawalOrderService(
            $this->snapshotGateway(),
            null,
            static function () use (&$releaseAttempts, $releaseFailure): void {
                $releaseAttempts++;
                throw $releaseFailure;
            }
        );

        $result = $service->createOrRetrieve(
            UserInfo::where('user_id', self::USER_ID)->firstOrFail(),
            Money::fromDecimalString('100.00', '10.00', '500000.00'),
            'release-failure-after-commit'
        );

        $this->assertSame(1, $releaseAttempts);
        $this->assertTrue($result['created']);
        $this->assertSame('release-failure-after-commit', (string) $result['order']->idempotency_key);
        $this->assertNull($connection->getRawPdo());
        $this->assertSame(1, $this->withdrawCount());
        $this->assertSame(1, $this->outboxCount());
        $this->assertDatabaseHas('withdraw_settlement_outbox', [
            'withdraw_record_id' => $result['order']->id,
            'local_order_no' => $result['order']->local_order_no,
            'status' => 'pending',
        ]);
        Log::shouldHaveReceived('error')->once()->withArgs(
            static function (string $message, array $context) use ($lockName, $releaseFailure): bool {
                return $message === 'withdrawal.reservation_lock_release_failed'
                    && $context['lock_hash'] === hash('sha256', $lockName)
                    && $context['database'] === DB::getDatabaseName()
                    && $context['exception_class'] === \RuntimeException::class
                    && $context['exception_message'] === $releaseFailure->getMessage();
            }
        );

        $observer = $this->fixtureObserverConnection('withdraw_task2_commit_release_failure_observer');
        $acquired = null;
        try {
            $acquired = $observer->selectOne('SELECT GET_LOCK(?, 0) AS acquired', [$lockName], false);
            $this->assertSame(1, (int) $acquired->acquired);
        } finally {
            if ($acquired && (int) $acquired->acquired === 1) {
                $observer->selectOne('SELECT RELEASE_LOCK(?) AS released', [$lockName], false);
            }
            $observer->disconnect();
        }
    }

    /** @dataProvider unconfirmedLockReleaseResultProvider */
    public function test_unconfirmed_lock_release_result_logged(
        string $case,
        object $releaseResult
    ): void {
        Log::spy();
        $connection = DB::connection();
        $connection->getPdo();
        $databaseName = $connection->getDatabaseName();
        $lockName = $this->reservationLockName(self::USER_ID);
        $service = new WithdrawalOrderService(
            $this->snapshotGateway(),
            null,
            static function () use ($releaseResult): object {
                return $releaseResult;
            }
        );

        $result = $service->createOrRetrieve(
            UserInfo::where('user_id', self::USER_ID)->firstOrFail(),
            Money::fromDecimalString('100.00', '10.00', '500000.00'),
            'unconfirmed-release-' . $case
        );

        $rawPdoAfterCleanup = $connection->getRawPdo();
        $observer = $this->fixtureObserverConnection('withdraw_task2_unconfirmed_release_observer');
        $acquired = null;
        try {
            $acquired = $observer->selectOne('SELECT GET_LOCK(?, 0) AS acquired', [$lockName], false);
        } finally {
            if ($acquired && (int) $acquired->acquired === 1) {
                $observer->selectOne('SELECT RELEASE_LOCK(?) AS released', [$lockName], false);
            }
            $observer->disconnect();
            if ($connection->getRawPdo() !== null) {
                $connection->disconnect();
            }
        }

        $this->assertTrue($result['created']);
        $this->assertNull($rawPdoAfterCleanup);
        $this->assertNotNull($acquired);
        $this->assertSame(1, (int) $acquired->acquired);
        $this->assertSame(1, $this->withdrawCount());
        $this->assertSame(1, $this->outboxCount());
        Log::shouldHaveReceived('error')->once()->withArgs(
            static function (string $message, array $context) use ($lockName, $databaseName): bool {
                return $message === 'withdrawal.reservation_lock_release_failed'
                    && $context['lock_hash'] === hash('sha256', $lockName)
                    && $context['database'] === $databaseName
                    && $context['exception_class'] === \LogicException::class
                    && $context['exception_message'] === 'Reservation lock release was not confirmed.';
            }
        );
    }

    public function unconfirmedLockReleaseResultProvider(): array
    {
        return [
            'zero' => ['zero', (object) ['released' => 0]],
            'null' => ['null', (object) ['released' => null]],
            'missing field' => ['missing', (object) []],
            'leading-zero string' => ['leading-zero', (object) ['released' => '01']],
            'non-numeric suffix' => ['suffix', (object) ['released' => '1x']],
            'boolean true' => ['boolean', (object) ['released' => true]],
        ];
    }

    public function test_unconfirmed_release_result_disconnects_connection(): void
    {
        Log::spy();
        $connection = DB::connection();
        $connection->getPdo();
        $disconnectAttempts = 0;
        $service = new WithdrawalOrderService(
            $this->snapshotGateway(),
            null,
            static function ($lockConnection, string $lockName): object {
                $result = $lockConnection->selectOne(
                    'SELECT RELEASE_LOCK(?) AS released',
                    [$lockName],
                    false
                );

                return (object) ['released' => (string) $result->released];
            },
            static function ($lockConnection) use (&$disconnectAttempts): void {
                $disconnectAttempts++;
                $lockConnection->disconnect();
            }
        );

        try {
            $result = $service->createOrRetrieve(
                UserInfo::where('user_id', self::USER_ID)->firstOrFail(),
                Money::fromDecimalString('100.00', '10.00', '500000.00'),
                'string-one-release-success'
            );

            $this->assertTrue($result['created']);
            $this->assertSame(0, $disconnectAttempts);
            $this->assertNotNull($connection->getRawPdo());
            $this->assertSame(1, $this->withdrawCount());
            $this->assertSame(1, $this->outboxCount());
            Log::shouldNotHaveReceived('error');
        } finally {
            if ($connection->getRawPdo() !== null) {
                $connection->disconnect();
            }
            Log::clearResolvedInstance('log');
        }
    }

    /** @dataProvider lockCleanupBusinessOutcomeProvider */
    public function test_lock_cleanup_business_outcome_when_logging_fails(string $outcome): void {
        $connection = DB::connection();
        $connection->getPdo();
        $primary = new \RuntimeException('primary failure while logging is unavailable');
        $releaseFailure = new \RuntimeException('release failure while logging is unavailable');
        $loggingFailure = new \RuntimeException('simulated logging failure');
        Log::shouldReceive('error')->once()->andThrow($loggingFailure);
        $orderCreator = $outcome === 'primary'
            ? static function () use ($primary): void {
                throw $primary;
            }
            : null;
        $service = new WithdrawalOrderService(
            $this->snapshotGateway(),
            $orderCreator,
            static function () use ($releaseFailure): void {
                throw $releaseFailure;
            }
        );
        $result = null;
        $caught = null;

        try {
            try {
                $result = $service->createOrRetrieve(
                    UserInfo::where('user_id', self::USER_ID)->firstOrFail(),
                    Money::fromDecimalString('100.00', '10.00', '500000.00'),
                    'logging-failure-' . $outcome
                );
            } catch (\Throwable $exception) {
                $caught = $exception;
            }
        } finally {
            Log::clearResolvedInstance('log');
        }

        $this->assertNull($connection->getRawPdo());
        if ($outcome === 'primary') {
            $this->assertSame($primary, $caught);
            $this->assertNull($result);
            $this->assertSame(0, $this->withdrawCount());
            $this->assertSame(0, $this->outboxCount());

            return;
        }

        $this->assertNull($caught);
        $this->assertNotNull($result);
        $this->assertTrue($result['created']);
        $this->assertSame(1, $this->withdrawCount());
        $this->assertSame(1, $this->outboxCount());
    }

    /** @dataProvider lockCleanupBusinessOutcomeProvider */
    public function test_lock_cleanup_business_outcome_when_disconnect_fails(string $outcome): void {
        Log::spy();
        $connection = DB::connection();
        $connection->getPdo();
        $databaseName = $connection->getDatabaseName();
        $lockName = $this->reservationLockName(self::USER_ID);
        $primary = new \RuntimeException('primary failure before disconnect failure');
        $releaseFailure = new \RuntimeException('simulated release failure before disconnect');
        $disconnectFailure = new \RuntimeException('simulated disconnect failure');
        $disconnectAttempts = 0;
        $orderCreator = $outcome === 'primary'
            ? static function () use ($primary): void {
                throw $primary;
            }
            : null;
        $service = new WithdrawalOrderService(
            $this->snapshotGateway(),
            $orderCreator,
            static function () use ($releaseFailure): void {
                throw $releaseFailure;
            },
            static function ($lockConnection) use (&$disconnectAttempts, $disconnectFailure): void {
                $disconnectAttempts++;
                $lockConnection->disconnect();
                throw $disconnectFailure;
            }
        );
        $result = null;
        $caught = null;

        try {
            try {
                $result = $service->createOrRetrieve(
                    UserInfo::where('user_id', self::USER_ID)->firstOrFail(),
                    Money::fromDecimalString('100.00', '10.00', '500000.00'),
                    'disconnect-failure-' . $outcome
                );
            } catch (\Throwable $exception) {
                $caught = $exception;
            }

            $this->assertSame(1, $disconnectAttempts);
            $this->assertNull($connection->getRawPdo());
            if ($outcome === 'primary') {
                $this->assertSame($primary, $caught);
                $this->assertNull($result);
                $this->assertSame(0, $this->withdrawCount());
                $this->assertSame(0, $this->outboxCount());
            } else {
                $this->assertNull($caught);
                $this->assertNotNull($result);
                $this->assertTrue($result['created']);
                $this->assertSame(1, $this->withdrawCount());
                $this->assertSame(1, $this->outboxCount());
            }
            Log::shouldHaveReceived('error')->withArgs(
                static function (string $message, array $context) use (
                    $lockName,
                    $databaseName,
                    $releaseFailure
                ): bool {
                    return $message === 'withdrawal.reservation_lock_release_failed'
                        && $context['lock_hash'] === hash('sha256', $lockName)
                        && $context['database'] === $databaseName
                        && $context['exception_class'] === \RuntimeException::class
                        && $context['exception_message'] === $releaseFailure->getMessage();
                }
            )->once();
            Log::shouldHaveReceived('error')->withArgs(
                static function (string $message, array $context) use (
                    $lockName,
                    $databaseName,
                    $disconnectFailure
                ): bool {
                    return $message === 'withdrawal.reservation_lock_disconnect_failed'
                        && $context['lock_hash'] === hash('sha256', $lockName)
                        && $context['database'] === $databaseName
                        && $context['exception_class'] === \RuntimeException::class
                        && $context['exception_message'] === $disconnectFailure->getMessage();
                }
            )->once();
        } finally {
            if ($connection->getRawPdo() !== null) {
                $connection->disconnect();
            }
            Log::clearResolvedInstance('log');
        }
    }

    public function lockCleanupBusinessOutcomeProvider(): array
    {
        return [
            'primary exception' => ['primary'],
            'committed success' => ['success'],
        ];
    }

    public function test_reservation_lock_uses_write_pdo(): void
    {
        $originalDefault = DB::getDefaultConnection();
        $connectionName = 'withdraw_task2_split_lock';
        $connectionConfig = config('database.connections.' . $originalDefault);
        $host = $connectionConfig['host'];
        $connectionConfig['read'] = ['host' => [$host]];
        $connectionConfig['write'] = ['host' => [$host]];
        $connectionConfig['sticky'] = false;
        config(['database.connections.' . $connectionName => $connectionConfig]);
        DB::purge($connectionName);
        DB::setDefaultConnection($connectionName);

        $connection = DB::connection($connectionName);
        $lockName = $this->reservationLockName(self::USER_ID);
        $observedOwnerId = null;

        try {
            $readId = (int) $connection
                ->selectOne('SELECT CONNECTION_ID() AS id', [], true)->id;
            $writeId = (int) $connection
                ->selectOne('SELECT CONNECTION_ID() AS id', [], false)->id;
            $this->assertNotSame($readId, $writeId);

            $service = new WithdrawalOrderService(
                $this->snapshotGateway(),
                static function () use ($connection, $lockName, &$observedOwnerId): void {
                    $owner = $connection->selectOne(
                        'SELECT IS_USED_LOCK(?) AS id',
                        [$lockName],
                        false
                    );
                    $observedOwnerId = $owner->id === null ? null : (int) $owner->id;

                    throw new \RuntimeException('inspect reservation lock owner');
                }
            );

            try {
                $service->createOrRetrieve(
                    UserInfo::where('user_id', self::USER_ID)->firstOrFail(),
                    Money::fromDecimalString('100.00', '10.00', '500000.00'),
                    'split-write-pdo-lock'
                );
                $this->fail('Expected the lock owner inspection exception.');
            } catch (\RuntimeException $exception) {
                $this->assertSame('inspect reservation lock owner', $exception->getMessage());
            }

            $free = $connection->selectOne(
                'SELECT IS_FREE_LOCK(?) AS is_free',
                [$lockName],
                false
            );
            $this->assertSame($writeId, $observedOwnerId);
            $this->assertSame(1, (int) $free->is_free);
            $this->assertSame(0, $this->withdrawCount());
            $this->assertSame(0, $this->outboxCount());
        } finally {
            $connection->selectOne('SELECT RELEASE_LOCK(?) AS released', [$lockName], false);
            $connection->selectOne('SELECT RELEASE_LOCK(?) AS released', [$lockName], true);
            DB::setDefaultConnection($originalDefault);
            DB::purge($connectionName);
            config(['database.connections.' . $connectionName => null]);
        }
    }

    public function test_reservation_lock_is_per_user(): void
    {
        $otherLockName = $this->reservationLockName(self::USER_ID + 1);
        $ownLockName = $this->reservationLockName(self::USER_ID);
        $this->assertNotSame($otherLockName, $ownLockName);
        $holder = $this->lockHolderConnection();
        $acquired = $holder->selectOne('SELECT GET_LOCK(?, 0) AS acquired', [$otherLockName]);
        $this->assertSame(1, (int) $acquired->acquired);
        $queries = [];
        DB::listen(static function ($query) use (&$queries): void {
            if ($query->connectionName === DB::getDefaultConnection()
                && stripos((string) $query->sql, 'GET_LOCK') !== false) {
                $queries[] = $query->bindings;
            }
        });

        try {
            $result = (new WithdrawalOrderService($this->snapshotGateway()))->createOrRetrieve(
                UserInfo::where('user_id', self::USER_ID)->firstOrFail(),
                Money::fromDecimalString('100.00', '10.00', '500000.00'),
                'different-user-reservation-lock'
            );
        } finally {
            $holder->selectOne('SELECT RELEASE_LOCK(?) AS released', [$otherLockName]);
            DB::disconnect('withdraw_task2_lock_holder');
        }

        $this->assertTrue($result['created']);
        $this->assertContains([$ownLockName], $queries);
        $this->assertSame(1, $this->withdrawCount());
        $this->assertSame(1, $this->outboxCount());
    }

    /** @dataProvider rejectedCreationRuleProvider */
    public function test_rejected_creation_rules(
        string $scenario,
        string $expectedError
    ): void {
        $this->applyRejectedRuleScenario($scenario);

        try {
            (new WithdrawalOrderService($this->snapshotGateway()))->createOrRetrieve(
                UserInfo::where('user_id', self::USER_ID)->firstOrFail(),
                Money::fromDecimalString('100.00', '10.00', '500000.00'),
                'rule-' . $scenario
            );
            $this->fail('Expected withdrawal rule rejection for ' . $scenario . '.');
        } catch (DomainException $exception) {
            $this->assertSame($expectedError, $exception->getMessage());
        }

        $this->assertSame(0, $this->withdrawCount());
        $this->assertSame(0, $this->outboxCount());
    }

    public function rejectedCreationRuleProvider(): array
    {
        return [
            'global switch' => ['global_switch', 'withdrawal_disabled'],
            'user switch' => ['user_switch', 'withdrawal_disabled'],
            'user auth status' => ['user_auth_status', 'identity_not_approved'],
            'ID card status' => ['id_card_status', 'identity_not_approved'],
            'bank status' => ['bank_status', 'bank_not_approved'],
            'bank number missing' => ['bank_no', 'bank_snapshot_incomplete'],
            'bank name missing' => ['bank_name', 'bank_snapshot_incomplete'],
            'bank address missing' => ['bank_addr', 'bank_snapshot_incomplete'],
            'risk below limit' => ['risk_rate', 'risk_rate_exceeded'],
            'open position' => ['open_position', 'open_positions'],
            'minimum changed after parse' => ['minimum', 'invalid_amount'],
            'maximum changed after parse' => ['maximum', 'invalid_amount'],
        ];
    }

    public function test_withdrawal_time_window_rejections(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-07-18 10:00:00', 'Asia/Shanghai'));
        $this->setConfig('withdrawal_weekend_enabled', '0');
        $this->assertServiceRejection('weekend-window', 'withdrawal_time_unavailable');

        Carbon::setTestNow(Carbon::parse('2026-07-15 11:00:00', 'Asia/Shanghai'));
        $this->setConfig('withdrawal_weekend_enabled', '1');
        $this->setConfig('withdrawal_start_time', '09:00');
        $this->setConfig('withdrawal_end_time', '10:00');
        $this->assertServiceRejection('closed-window', 'withdrawal_time_unavailable');
    }

    /** @dataProvider invalidSettlementConfigProvider */
    public function test_invalid_settlement_config_rejected(
        string $key,
        string $value
    ): void {
        $this->setConfig($key, $value);
        $this->assertServiceRejection(
            'invalid-config-' . md5($key . $value),
            'withdrawal_configuration_invalid'
        );
    }

    public function invalidSettlementConfigProvider(): array
    {
        return [
            'negative fixed fee' => ['withdrawal_fixed_fee_usd', '-0.01'],
            'scientific fee rate' => ['withdrawal_fee_rate', '1e2'],
            'negative fee rate' => ['withdrawal_fee_rate', '-1'],
            'zero exchange rate' => ['withdraw_exchange_rate_cny', '0'],
            'scientific exchange rate' => ['withdraw_exchange_rate_cny', '7.2e0'],
            'exchange rate overflow' => ['withdraw_exchange_rate_cny', '10000000000.00000000'],
        ];
    }

    public function test_fee_equal_to_amount_rejected(): void
    {
        $this->setConfig('withdrawal_fixed_fee_usd', '100.00');
        $this->assertServiceRejection('fee-equals-amount', 'fee_not_less_than_amount');
    }

    public function test_fee_and_rate_calculation_contract(): void
    {
        $this->setConfig('withdrawal_fixed_fee_usd', '0.10');
        $this->setConfig('withdrawal_fee_rate', '1.25');
        $this->setConfig('withdraw_exchange_rate_cny', '7.12345678');
        DB::table('user_infos')->where('user_id', self::USER_ID)->update(['risk_ratio' => '100.00']);
        $gateway = $this->snapshotGateway('1000.00', '900.00');

        $result = (new WithdrawalOrderService($gateway))->createOrRetrieve(
            UserInfo::where('user_id', self::USER_ID)->firstOrFail(),
            Money::fromDecimalString('100.05', '10.00', '500000.00'),
            'exact-settlement-snapshot'
        );
        $order = $result['order']->fresh();
        $outbox = DB::table('withdraw_settlement_outbox')
            ->where('withdraw_record_id', $order->id)
            ->where('event_type', 'withdraw_debit')
            ->first();

        $this->assertTrue($result['created']);
        $this->assertStringStartsWith('WDR', (string) $order->local_order_no);
        $this->assertSame('100.05', (string) $order->apply_amount);
        $this->assertSame('1.35', (string) $order->fee);
        $this->assertSame('98.70', (string) $order->actual_amount);
        $this->assertSame('7.12345678', (string) $order->exchange_rate);
        $this->assertSame('9.62', (string) $order->rmb_fee);
        $this->assertSame('TASK2-BANK-001', (string) $order->bank_no);
        $this->assertSame('Task 2 Bank', (string) $order->bank_name);
        $this->assertSame('Task 2 Branch', (string) $order->bank_addr);
        $this->assertSame('pending', (string) $order->funding_status);
        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', (string) $order->funding_payload_hash);
        $this->assertNotNull($outbox);
        $this->assertSame((string) $order->funding_payload_hash, (string) $outbox->payload_hash);
        $this->assertSame('pending', (string) $outbox->status);
        $this->assertSame(0, (int) $outbox->attempts);
        $this->assertGreaterThanOrEqual(time() - 5, (int) $outbox->available_at);
        $this->assertSame([0], $gateway->transactionLevels);
        $this->assertDatabaseHas('user_infos', [
            'user_id' => self::USER_ID,
            'total_funds' => '9999.00',
            'avail_margin' => '9999.00',
        ]);
    }

    public function test_controller_replay_returns_same_order_and_does_not_echo_debited(): void
    {
        $gateway = $this->snapshotGateway('500.00', '500.00');
        $this->app->instance(WithdrawalAccountSnapshotGateway::class, $gateway);

        $first = $this->submit('120.00', 'controller-replay-key');
        $second = $this->submit('120.00', 'controller-replay-key');

        foreach ([$first, $second] as $response) {
            $response->assertOk()
                ->assertJsonPath('code', ResponseCode::CREATED)
                ->assertJsonPath('data.funding_status', 'pending');
            $this->assertStringContainsString('资金处理中', (string) $response->json('message'));
            $this->assertStringNotContainsString('已扣款', (string) $response->json('message'));
        }
        $this->assertSame($first->json('data.id'), $second->json('data.id'));
        $this->assertSame(1, $gateway->calls);
        $this->assertSame(1, $this->withdrawCount());
        $this->assertSame(1, $this->outboxCount());
    }

    public function test_controller_priority_replay_skips_closed_rules(): void
    {
        $gateway = $this->snapshotGateway('500.00', '500.00');
        $this->app->instance(WithdrawalAccountSnapshotGateway::class, $gateway);
        $first = $this->submit('120.00', 'controller-priority-replay');
        $first->assertJsonPath('code', ResponseCode::CREATED);
        $orderId = $first->json('data.id');

        $this->closeCurrentWithdrawalRules();
        $replay = $this->submit('120.0', 'controller-priority-replay');

        $replay->assertOk()
            ->assertJsonPath('code', ResponseCode::CREATED)
            ->assertJsonPath('data.id', $orderId);
        $this->assertSame(1, $gateway->calls);
        $this->assertSame(1, $this->withdrawCount());
        $this->assertSame(1, $this->outboxCount());
    }

    public function test_controller_priority_different_amount_conflict(): void
    {
        $gateway = $this->snapshotGateway('500.00', '500.00');
        $this->app->instance(WithdrawalAccountSnapshotGateway::class, $gateway);
        $this->submit('120.00', 'controller-priority-different')
            ->assertJsonPath('code', ResponseCode::CREATED);

        $this->closeCurrentWithdrawalRules();
        $conflict = $this->submit('121.00', 'controller-priority-different');

        $conflict->assertOk()->assertJsonPath('code', ResponseCode::OPERATION_NOT_ALLOWED);
        $this->assertSame(1, $gateway->calls);
        $this->assertSame(1, $this->withdrawCount());
        $this->assertSame(1, $this->outboxCount());
    }

    public function test_controller_priority_deleted_key_conflict(): void
    {
        $gateway = $this->snapshotGateway('500.00', '500.00');
        $this->app->instance(WithdrawalAccountSnapshotGateway::class, $gateway);
        $first = $this->submit('120.00', 'controller-priority-deleted');
        $first->assertJsonPath('code', ResponseCode::CREATED);
        DB::table('withdraw_records')->where('id', $first->json('data.id'))->update([
            'deleted_at' => time(),
            'updated_at' => time(),
        ]);

        $this->closeCurrentWithdrawalRules();
        $conflict = $this->submit('120.00', 'controller-priority-deleted');

        $conflict->assertOk()->assertJsonPath('code', ResponseCode::OPERATION_NOT_ALLOWED);
        $this->assertSame(1, $gateway->calls);
        $this->assertSame(1, $this->withdrawCount());
        $this->assertSame(1, $this->outboxCount());
    }

    public function test_controller_replay_still_validates_password_and_terms(): void
    {
        $gateway = $this->snapshotGateway('500.00', '500.00');
        $this->app->instance(WithdrawalAccountSnapshotGateway::class, $gateway);
        $this->submit('120.00', 'controller-replay-security')
            ->assertJsonPath('code', ResponseCode::CREATED);
        $this->setConfig('withdrawal_enabled', '0');
        DB::table('user_infos')->where('user_id', self::USER_ID)->update(['auth_status' => 0]);

        $wrongPassword = $this->submitPayload('/api/front/withdrawals/submissions', [
            'amount' => '120.00',
            'password' => 'wrong-password',
            'agree' => true,
        ], 'controller-replay-security');
        $missingTerms = $this->submitPayload('/api/front/withdrawals/submissions', [
            'amount' => '120.00',
            'password' => 'password',
            'agree' => false,
        ], 'controller-replay-security');

        $wrongPassword->assertJsonPath('code', ResponseCode::OLD_PASSWORD_WRONG);
        $missingTerms->assertJsonPath('code', ResponseCode::VALIDATION_FAILED);
        $this->assertSame(1, $gateway->calls);
        $this->assertSame(1, $this->withdrawCount());
        $this->assertSame(1, $this->outboxCount());
    }

    public function test_controller_snapshot_failure_hides_internal_details(): void
    {
        $gateway = new class implements WithdrawalAccountSnapshotGateway {
            public function snapshot(int $userId): WithdrawalAccountSnapshot
            {
                throw new \RuntimeException('secret MT4 endpoint failure detail');
            }
        };
        $this->app->instance(WithdrawalAccountSnapshotGateway::class, $gateway);

        $response = $this->submit('100.00', 'controller-snapshot-failure');

        $response->assertOk()->assertJsonPath('code', ResponseCode::MT4_SYNC_FAILED);
        $this->assertStringNotContainsString('secret MT4', $response->getContent());
        $this->assertSame(0, $this->withdrawCount());
        $this->assertSame(0, $this->outboxCount());
    }

    public function test_open_position_rejection_localized_message(): void
    {
        $this->setConfig('withdraw_check_open', '1');
        $this->insertOpenTrade();
        $gateway = $this->snapshotGateway('500.00', '500.00');
        $this->app->instance(WithdrawalAccountSnapshotGateway::class, $gateway);
        $actual = [];

        foreach (['zh-CN', 'en'] as $locale) {
            $response = $this->submitPayload('/api/front/withdrawals/submissions', [
                'amount' => '100.00',
                'password' => 'password',
                'agree' => true,
            ], 'open-position-' . $locale, $locale);
            $actual[$locale] = [
                'code' => $response->json('code'),
                'message' => $response->json('message'),
            ];
        }

        $this->assertSame([
            'zh-CN' => [
                'code' => ResponseCode::OPERATION_NOT_ALLOWED,
                'message' => '存在未平仓持仓，暂不允许出金',
            ],
            'en' => [
                'code' => ResponseCode::OPERATION_NOT_ALLOWED,
                'message' => 'Withdrawal is not allowed while open positions exist',
            ],
        ], $actual);
        $this->assertSame(2, $gateway->calls);
        $this->assertSame(0, $this->withdrawCount());
        $this->assertSame(0, $this->outboxCount());
    }

    public function test_controller_amount_conflict_returns_operation_not_allowed(): void
    {
        $this->app->instance(
            WithdrawalAccountSnapshotGateway::class,
            $this->snapshotGateway('500.00', '500.00')
        );
        $this->submit('100.00', 'controller-amount-conflict')
            ->assertJsonPath('code', ResponseCode::CREATED);

        $response = $this->submit('100.01', 'controller-amount-conflict');

        $response->assertOk()->assertJsonPath('code', ResponseCode::OPERATION_NOT_ALLOWED);
        $this->assertSame(1, $this->withdrawCount());
        $this->assertSame(1, $this->outboxCount());
    }

    public function test_remote_balance_is_authoritative(): void
    {
        DB::table('user_infos')->where('user_id', self::USER_ID)->update([
            'total_funds' => '1.00',
            'avail_margin' => '1.00',
        ]);
        $gateway = $this->snapshotGateway('500.00', '500.00');
        $this->app->instance(WithdrawalAccountSnapshotGateway::class, $gateway);

        $response = $this->submit('100.00', 'remote-authoritative-balance');

        $response->assertOk()
            ->assertJsonPath('code', ResponseCode::CREATED)
            ->assertJsonPath('data.funding_status', 'pending');
        $this->assertSame(1, $gateway->calls);
        $this->assertSame(1, $this->withdrawCount());
        $this->assertSame(1, $this->outboxCount());
    }

    public function test_wrong_password_and_missing_terms_do_not_call_gateway(): void
    {
        $gateway = $this->snapshotGateway();
        $this->app->instance(WithdrawalAccountSnapshotGateway::class, $gateway);

        $wrongPassword = $this->submitPayload('/api/front/withdrawals/submissions', [
            'amount' => '100.00',
            'password' => 'wrong-password',
            'agree' => true,
        ], 'wrong-password-key');
        $wrongPassword->assertJsonPath('code', ResponseCode::OLD_PASSWORD_WRONG);

        $missingTerms = $this->submitPayload('/api/front/withdrawals/submissions', [
            'amount' => '100.00',
            'password' => 'password',
            'agree' => false,
        ], 'missing-terms-key');
        $missingTerms->assertJsonPath('code', ResponseCode::VALIDATION_FAILED);

        $this->assertSame(0, $gateway->calls);
        $this->assertSame(0, $this->withdrawCount());
        $this->assertSame(0, $this->outboxCount());
    }

    /** @dataProvider withdrawalTermsEntryProvider */
    public function test_withdrawal_terms_entry_rejected_without_agreement(
        string $endpoint,
        array $payload,
        string $key
    ): void {
        $gateway = $this->snapshotGateway();
        $this->app->instance(WithdrawalAccountSnapshotGateway::class, $gateway);

        $response = $this->submitPayload($endpoint, $payload, $key);

        $response->assertOk()->assertJsonPath('code', ResponseCode::VALIDATION_FAILED);
        $this->assertSame(0, $gateway->calls);
        $this->assertSame(0, $this->withdrawCount());
        $this->assertSame(0, $this->outboxCount());
    }

    public function withdrawalTermsEntryProvider(): array
    {
        return [
            'modern missing agree' => [
                '/api/front/withdrawals/submissions',
                ['amount' => '100.00', 'password' => 'password'],
                'terms-modern-missing',
            ],
            'modern false agree' => [
                '/api/front/withdrawals/submissions',
                ['amount' => '100.00', 'password' => 'password', 'agree' => false],
                'terms-modern-false',
            ],
            'legacy missing agree' => [
                '/user/withdraw_request',
                ['withdraw_amt' => '100.00', 'withdraw_password' => 'password'],
                'terms-legacy-missing',
            ],
            'legacy false agree' => [
                '/user/withdraw_request',
                ['withdraw_amt' => '100.00', 'withdraw_password' => 'password', 'agree' => false],
                'terms-legacy-false',
            ],
            'OTC missing agree' => [
                '/user/withdraw_request_OTC',
                ['withdraw_amt' => '100.00', 'withdraw_psw' => 'password'],
                'terms-otc-missing',
            ],
            'OTC false agree' => [
                '/user/withdraw_request_OTC',
                ['withdraw_amt' => '100.00', 'withdraw_psw' => 'password', 'agree' => false],
                'terms-otc-false',
            ],
        ];
    }

    /** @dataProvider withdrawalSubmissionEntryProvider */
    public function test_withdrawal_submission_entry_creates_pending_order(
        string $endpoint,
        array $payload,
        string $key
    ): void {
        $gateway = $this->snapshotGateway();
        $this->app->instance(WithdrawalAccountSnapshotGateway::class, $gateway);

        $response = $this->submitPayload($endpoint, $payload, $key);

        $response->assertOk()
            ->assertJsonPath('code', ResponseCode::CREATED)
            ->assertJsonPath('data.funding_status', 'pending');
        $this->assertSame(1, $gateway->calls);
        $this->assertSame(1, $this->withdrawCount());
        $this->assertSame(1, $this->outboxCount());
    }

    public function withdrawalSubmissionEntryProvider(): array
    {
        return [
            'modern' => [
                '/api/front/withdrawals/submissions',
                ['amount' => '100.00', 'password' => 'password', 'agree' => true],
                'entry-modern',
            ],
            'ordinary legacy' => [
                '/user/withdraw_request',
                ['withdraw_amt' => '100.00', 'withdraw_password' => 'password', 'agree' => true],
                'entry-legacy',
            ],
            'OTC legacy' => [
                '/user/withdraw_request_OTC',
                ['withdraw_amt' => '100.00', 'withdraw_psw' => 'password', 'agree' => true],
                'entry-otc',
            ],
        ];
    }

    public function test_mt4_snapshot_gateway_maps_manager_account_info(): void
    {
        config(['mt4.user_sync_enabled' => true]);
        $this->assertTrue(class_exists(
            \App\Services\Withdrawal\Mt4WithdrawalAccountSnapshotGateway::class
        ));
        $manager = new class extends \App\Services\Mt4ManagerService {
            /**
             * MT4 getAccountInfo 调用计数替身。
             * 用于断言 user_sync_enabled 关闭时适配器完全不发起 MT4 调用。
             * @var int
             */
            public $calls = 0;

            public function __construct()
            {
                parent::__construct('127.0.0.1', 1, 'key', 'version', 1);
            }

            public function getAccountInfo($userId)
            {
                $this->calls++;

                return [
                    'status' => 'ok',
                    'balance' => '125.50',
                    'free_margin' => '100.25',
                ];
            }

            public function withdrawal($userId, $amount, $comment)
            {
                throw new \RuntimeException('Snapshot adapter must never call withdrawal.');
            }
        };
        $adapter = new \App\Services\Withdrawal\Mt4WithdrawalAccountSnapshotGateway($manager);

        $snapshot = $adapter->snapshot(self::USER_ID);

        $this->assertSame('125.50', $snapshot->balance());
        $this->assertSame('100.25', $snapshot->freeMargin());
        $this->assertSame(1, $manager->calls);
        $this->assertInstanceOf(
            \App\Services\Withdrawal\Mt4WithdrawalAccountSnapshotGateway::class,
            app(WithdrawalAccountSnapshotGateway::class)
        );
    }

    /** @dataProvider malformedAccountInfoProvider */
    public function test_malformed_account_info_fails_closed(array $response): void
    {
        config(['mt4.user_sync_enabled' => true]);
        $this->assertTrue(class_exists(
            \App\Services\Withdrawal\Mt4WithdrawalAccountSnapshotGateway::class
        ));
        $manager = new class($response) extends \App\Services\Mt4ManagerService {
            /**
             * 预设的畸形 getAccountInfo 返回体，由数据提供器注入。
             * 驱动断言：Mt4WithdrawalAccountSnapshotGateway 对结构异常的 MT4 数据必须失败关闭而不是透传。
             * @var array<string, mixed>
             */
            private $response;

            public function __construct(array $response)
            {
                parent::__construct('127.0.0.1', 1, 'key', 'version', 1);
                $this->response = $response;
            }

            public function getAccountInfo($userId)
            {
                return $this->response;
            }
        };
        $adapter = new \App\Services\Withdrawal\Mt4WithdrawalAccountSnapshotGateway($manager);

        $this->expectException(DomainException::class);
        $adapter->snapshot(self::USER_ID);
    }

    public function malformedAccountInfoProvider(): array
    {
        return [
            'provider error' => [['status' => 'error', 'error_code' => 'read_timeout']],
            'numeric JSON balance' => [['status' => 'ok', 'balance' => 100, 'free_margin' => '100.00']],
            'missing free margin' => [['status' => 'ok', 'balance' => '100.00']],
            'scientific balance' => [['status' => 'ok', 'balance' => '1e3', 'free_margin' => '100.00']],
        ];
    }

    /**
     * @param mixed $amount
     * @return \Illuminate\Testing\TestResponse
     */
    private function submit($amount, string $key = null)
    {
        return $this->submitPayload('/api/front/withdrawals/submissions', [
            'amount' => $amount,
            'password' => 'password',
            'agree' => true,
        ], $key);
    }

    /**
     * @param array<string, mixed> $payload
     * @return \Illuminate\Testing\TestResponse
     */
    private function submitPayload(
        string $endpoint,
        array $payload,
        string $key = null,
        string $locale = 'zh-CN'
    )
    {
        $login = UserLogin::where('user_id', self::USER_ID)->firstOrFail();
        $request = $this->withoutMiddleware([JwtAuthMiddleware::class, SingleSignOn::class])
            ->actingAs($login, 'user')
            ->withHeader('X-Locale', $locale);
        if ($key !== null) {
            $request = $request->withHeader('Idempotency-Key', $key);
        }

        return $request->postJson($endpoint, $payload);
    }

    private function snapshotGateway(
        string $balance = '1000.00',
        string $freeMargin = '1000.00'
    ): WithdrawalAccountSnapshotGateway {
        return new class($balance, $freeMargin) implements WithdrawalAccountSnapshotGateway {
            /**
             * 快照替身返回的固定账户余额（字符串金额），由用例按场景注入。
             * 固定值保证 WithdrawalAccountSnapshot 的出参可复现、断言金额精确。
             * @var string
             */
            private $balance;

            /**
             * 快照替身返回的固定可用保证金（字符串金额），与 balance 共同构成替身快照的出参。
             * @var string
             */
            private $freeMargin;

            /**
             * 替身快照网关 snapshot() 的调用计数。
             * 断言同一幂等键复用既有订单时不会重复读取 MT4 账户。
             * @var int
             */
            public $calls = 0;

            /**
             * 替身快照网关每次 snapshot() 时记录的 DB 事务层级。
             * 用于断言 MT4 读取发生在正确的事务边界内（不在开户写事务中）。
             * @var array<int, int>
             */
            public $transactionLevels = [];

            public function __construct(string $balance, string $freeMargin)
            {
                $this->balance = $balance;
                $this->freeMargin = $freeMargin;
            }

            public function snapshot(int $userId): WithdrawalAccountSnapshot
            {
                $this->calls++;
                $this->transactionLevels[] = DB::transactionLevel();

                return new WithdrawalAccountSnapshot($this->balance, $this->freeMargin);
            }
        };
    }

    /**
     * @param callable(): mixed $callback
     * @return mixed
     */
    private function withReadCommittedSessionIsolation(callable $callback)
    {
        $connection = DB::connection();
        $originalIsolation = $this->readSessionTransactionIsolation($connection);
        $this->setSessionTransactionIsolation($connection, 'READ COMMITTED');

        try {
            return $callback();
        } finally {
            $this->setSessionTransactionIsolation($connection, $originalIsolation);
        }
    }

    private function readSessionTransactionIsolation(
        \Illuminate\Database\Connection $connection
    ): string {
        $queries = [
            'transaction_isolation' =>
                'SELECT @@SESSION.transaction_isolation AS isolation_level',
            'tx_isolation' => 'SELECT @@SESSION.tx_isolation AS isolation_level',
        ];
        $failureMessages = [];
        $firstFailure = null;

        foreach ($queries as $variable => $query) {
            try {
                $status = $connection->selectOne($query, [], false);
                if (!$status || !isset($status->isolation_level)) {
                    throw new \RuntimeException(
                        'The session variable returned no transaction isolation value.'
                    );
                }

                return $this->normalizeSessionTransactionIsolation(
                    (string) $status->isolation_level
                );
            } catch (\Throwable $exception) {
                if ($firstFailure === null) {
                    $firstFailure = $exception;
                }
                $failureMessages[] = $variable . ': ' . $exception->getMessage();
            }
        }

        throw new \RuntimeException(
            'Unable to read the session transaction isolation: '
            . implode(' | ', $failureMessages),
            0,
            $firstFailure
        );
    }

    private function setSessionTransactionIsolation(
        \Illuminate\Database\Connection $connection,
        string $isolation
    ): void {
        $normalized = $this->normalizeSessionTransactionIsolation($isolation);
        $connection->statement('SET SESSION TRANSACTION ISOLATION LEVEL ' . $normalized);
    }

    private function normalizeSessionTransactionIsolation(string $isolation): string
    {
        $normalized = preg_replace(
            '/\s+/',
            ' ',
            str_replace('-', ' ', strtoupper(trim($isolation)))
        );
        $allowed = [
            'READ UNCOMMITTED',
            'READ COMMITTED',
            'REPEATABLE READ',
            'SERIALIZABLE',
        ];
        if ($normalized === null || !in_array($normalized, $allowed, true)) {
            throw new \UnexpectedValueException(
                'Unsupported session transaction isolation: ' . $isolation . '.'
            );
        }

        return $normalized;
    }

    private function reservationLockName(int $userId): string
    {
        return 'wdr:reserve:' . substr(
            hash('sha256', DB::getDatabaseName() . ':' . $userId),
            0,
            48
        );
    }

    private function lockHolderConnection()
    {
        $connection = 'withdraw_task2_lock_holder';
        config([
            'database.connections.' . $connection => config(
                'database.connections.' . DB::getDefaultConnection()
            ),
        ]);
        DB::purge($connection);

        $database = DB::connection($connection);
        $database->unsetEventDispatcher();

        return $database;
    }

    private function fixtureObserverConnection(string $connection)
    {
        config([
            'database.connections.' . $connection => config(
                'database.connections.' . DB::getDefaultConnection()
            ),
        ]);
        DB::purge($connection);

        return DB::connection($connection);
    }

    private function listenForCleanupPlanLoginInsertion(
        \Illuminate\Database\Connection $observer,
        string $sentinelEmail,
        string $sentinelPassword,
        int &$sentinelLoginId = null,
        bool &$listenerActive
    ): void {
        DB::listen(static function ($query) use (
            $observer,
            $sentinelEmail,
            $sentinelPassword,
            &$sentinelLoginId,
            &$listenerActive
        ): void {
            if (!$listenerActive
                || $sentinelLoginId !== null
                || strpos(strtolower((string) $query->sql), 'delete from `user_infos`') === false) {
                return;
            }

            $sentinelLoginId = $observer->table('user_logins')->insertGetId([
                'user_id' => self::USER_ID,
                'email' => $sentinelEmail,
                'password' => $sentinelPassword,
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
        });
    }

    /** @param array<string, mixed> $attributes */
    private function insertCompetingWithdrawal(array $attributes): void
    {
        $connection = 'withdraw_task2_race';
        config(['database.connections.' . $connection => config('database.connections.' . DB::getDefaultConnection())]);
        DB::purge($connection);
        $database = DB::connection($connection);
        $withdrawId = $database->table('withdraw_records')->insertGetId($attributes + [
            'mt4_ticket' => '',
            'third_order_no' => '',
            'mt4_return_status' => '',
            'updated_by' => '',
            'created_at' => time(),
            'updated_at' => time(),
            'deleted_at' => null,
        ]);
        $database->table('withdraw_settlement_outbox')->insert([
            'withdraw_record_id' => $withdrawId,
            'local_order_no' => $attributes['local_order_no'],
            'event_type' => 'withdraw_debit',
            'status' => 'pending',
            'attempts' => 0,
            'payload_hash' => $attributes['funding_payload_hash'],
            'available_at' => time(),
            'locked_at' => null,
            'processed_at' => null,
            'provider_reference' => null,
            'last_error_code' => null,
            'created_at' => time(),
            'updated_at' => time(),
            'deleted_at' => null,
        ]);
        DB::disconnect($connection);
    }

    private function duplicateIdempotencyException(): QueryException
    {
        $message = "Duplicate entry for key 'withdraw_records_idempotency_user_unique'";
        $previous = new PDOException($message, 1062);
        $previous->errorInfo = ['23000', 1062, $message];

        return new QueryException('insert into withdraw_records', [], $previous);
    }

    private function insertReservedWithdrawal(string $fundingStatus, string $amount): void
    {
        $localOrderNo = 'WDR-RESERVATION-' . strtoupper($fundingStatus);
        $payloadHash = hash('sha256', $localOrderNo);
        $withdrawId = DB::table('withdraw_records')->insertGetId([
            'user_id' => self::USER_ID,
            'user_name' => 'withdraw-task2-user',
            'mt4_ticket' => '',
            'apply_amount' => $amount,
            'actual_amount' => $amount,
            'fee' => '0.00',
            'exchange_rate' => '1.00000000',
            'rmb_fee' => '0.00',
            'bank_no' => 'TASK2-BANK-001',
            'bank_name' => 'Task 2 Bank',
            'bank_addr' => 'Task 2 Branch',
            'status' => 0,
            'local_order_no' => $localOrderNo,
            'third_order_no' => '',
            'reject_reason' => '',
            'mt4_return_status' => '',
            'idempotency_key' => 'seed-' . $fundingStatus,
            'funding_status' => $fundingStatus,
            'funding_payload_hash' => $payloadHash,
            'created_by' => 'withdraw-task2-user',
            'updated_by' => '',
            'created_at' => time(),
            'updated_at' => time(),
            'deleted_at' => null,
        ]);
        DB::table('withdraw_settlement_outbox')->insert([
            'withdraw_record_id' => $withdrawId,
            'local_order_no' => $localOrderNo,
            'event_type' => 'withdraw_debit',
            'status' => $fundingStatus,
            'attempts' => 0,
            'payload_hash' => $payloadHash,
            'available_at' => time(),
            'locked_at' => null,
            'processed_at' => null,
            'provider_reference' => null,
            'last_error_code' => null,
            'created_at' => time(),
            'updated_at' => time(),
            'deleted_at' => null,
        ]);
    }

    private function closeCurrentWithdrawalRules(): void
    {
        $this->setConfig('withdraw_max_amount', '100.00');
        $this->setConfig('withdrawal_enabled', '0');
        $this->setConfig('withdrawal_start_time', '09:00');
        $this->setConfig('withdrawal_end_time', '10:00');
        DB::table('user_infos')->where('user_id', self::USER_ID)->update([
            'auth_status' => 0,
            'is_withdrawal_allowed' => 1,
            'updated_at' => time(),
        ]);
        Carbon::setTestNow(Carbon::parse('2026-07-15 12:00:00', 'Asia/Shanghai'));
    }

    private function applyRejectedRuleScenario(string $scenario): void
    {
        switch ($scenario) {
            case 'global_switch':
                $this->setConfig('withdrawal_enabled', '0');
                return;
            case 'user_switch':
                DB::table('user_infos')->where('user_id', self::USER_ID)
                    ->update(['is_withdrawal_allowed' => 1]);
                return;
            case 'user_auth_status':
                DB::table('user_infos')->where('user_id', self::USER_ID)
                    ->update(['auth_status' => 0]);
                return;
            case 'id_card_status':
                DB::table('user_auths')->where('user_id', self::USER_ID)
                    ->update(['id_card_status' => 1]);
                return;
            case 'bank_status':
                DB::table('user_auths')->where('user_id', self::USER_ID)
                    ->update(['bank_status' => 1]);
                return;
            case 'bank_no':
            case 'bank_name':
            case 'bank_addr':
                DB::table('user_auths')->where('user_id', self::USER_ID)
                    ->update([$scenario => '   ']);
                return;
            case 'risk_rate':
                DB::table('user_infos')->where('user_id', self::USER_ID)
                    ->update(['risk_ratio' => '99.99']);
                return;
            case 'open_position':
                $this->setConfig('withdraw_check_open', '1');
                $this->insertOpenTrade();
                return;
            case 'minimum':
                $this->setConfig('withdraw_min_amount', '100.01');
                return;
            case 'maximum':
                $this->setConfig('withdraw_max_amount', '99.99');
                return;
        }

        throw new \LogicException('Unknown rejected rule scenario: ' . $scenario);
    }

    private function assertServiceRejection(string $key, string $expectedError): void
    {
        try {
            (new WithdrawalOrderService($this->snapshotGateway()))->createOrRetrieve(
                UserInfo::where('user_id', self::USER_ID)->firstOrFail(),
                Money::fromDecimalString('100.00', '10.00', '500000.00'),
                $key
            );
            $this->fail('Expected withdrawal service rejection: ' . $expectedError);
        } catch (DomainException $exception) {
            $this->assertSame($expectedError, $exception->getMessage());
        }

        $this->assertSame(0, $this->withdrawCount());
        $this->assertSame(0, $this->outboxCount());
    }

    private function setConfig(string $key, string $value): void
    {
        $affected = DB::table('system_configs')->where('key', $key)->update([
            'value' => $value,
            'updated_at' => time(),
        ]);
        if ($affected !== 1) {
            throw new \RuntimeException(
                'Expected to update exactly one withdrawal config row for key '
                . $key
                . '; affected '
                . $affected
                . '.'
            );
        }

        $this->captureCurrentWithdrawalConfigOwnedState();
    }

    private function insertOpenTrade(): void
    {
        DB::table('user_trades')->insert([
            'user_id' => self::USER_ID,
            'ticket' => 412372991,
            'symbol' => 'EURUSD',
            'digits' => 5,
            'cmd' => 0,
            'volume' => 100,
            'open_time' => '2026-07-15 09:00:00',
            'open_price' => 1.1,
            'close_time' => '1970-01-01 00:00:00',
            'modify_time' => '2026-07-15 09:00:00',
            'created_at' => time(),
            'updated_at' => time(),
            'deleted_at' => null,
        ]);
    }

    private function withdrawCount(): int
    {
        return DB::table('withdraw_records')->where('user_id', self::USER_ID)->count();
    }

    private function outboxCount(): int
    {
        if ($this->settlementOutboxBaseline === null) {
            throw new \LogicException('The settlement outbox baseline is unavailable.');
        }

        $baselineIds = [];
        foreach ($this->settlementOutboxBaseline as $row) {
            if (!array_key_exists('id', $row)) {
                throw new \LogicException('The settlement outbox baseline contains a row without id.');
            }
            $baselineIds[(string) $row['id']] = true;
        }

        $count = 0;
        foreach ($this->readSettlementOutboxRows() as $row) {
            if (!isset($baselineIds[(string) $row['id']])) {
                ++$count;
            }
        }

        return $count;
    }

    private function snapshotSettlementOutboxBaseline(): void
    {
        if ($this->settlementOutboxBaseline !== null) {
            throw new \LogicException('The settlement outbox baseline already exists.');
        }

        $this->settlementOutboxBaseline = $this->readSettlementOutboxRows();
    }

    private function assertSettlementOutboxMatchesBaseline(): void
    {
        if ($this->settlementOutboxBaseline === null) {
            throw new \LogicException('The settlement outbox baseline is unavailable.');
        }

        $actual = $this->readSettlementOutboxRows();
        if ($actual === $this->settlementOutboxBaseline) {
            return;
        }

        throw new \RuntimeException(
            'withdraw_settlement_outbox baseline mismatch: expected count/hash '
            . count($this->settlementOutboxBaseline)
            . '/'
            . $this->settlementOutboxRowsHash($this->settlementOutboxBaseline)
            . ', actual count/hash '
            . count($actual)
            . '/'
            . $this->settlementOutboxRowsHash($actual)
            . '.'
        );
    }

    /** @return array<int, array<string, mixed>> */
    private function readSettlementOutboxRows(): array
    {
        return DB::table('withdraw_settlement_outbox')
            ->useWritePdo()
            ->orderBy('id')
            ->get()
            ->map(function ($row): array {
                return $this->settlementFixtureRowSignature($row);
            })
            ->all();
        $this->captureSharedSystemConfigFixtureOwnedState(
            array_keys($this->withdrawalConfig()),
            $this->configSnapshot
        );
    }

    /** @param array<int, array<string, mixed>> $rows */
    private function settlementOutboxRowsHash(array $rows): string
    {
        $json = json_encode(
            $rows,
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION
        );
        if ($json === false) {
            throw new \RuntimeException(
                'Unable to encode settlement outbox rows: ' . json_last_error_msg() . '.'
            );
        }

        return hash('sha256', $json);
    }

    /** @return array<string, int> */
    private function settlementFixtureOwnedRowCounts(): array
    {
        return [
            'user_logins' => DB::table('user_logins')->where('user_id', self::USER_ID)->count(),
            'user_infos' => DB::table('user_infos')->where('user_id', self::USER_ID)->count(),
            'user_auths' => DB::table('user_auths')->where('user_id', self::USER_ID)->count(),
            'user_trades' => DB::table('user_trades')->where('user_id', self::USER_ID)->count(),
            'withdraw_records' => $this->withdrawCount(),
            'withdraw_settlement_outbox' => $this->outboxCount(),
        ];
    }

    private function assertCleanupFailsWithoutDeletingOwnedRows(string $table): void
    {
        $before = $this->settlementFixtureOwnedRowCounts();
        $failure = null;
        try {
            $this->cleanupFixtureRows();
        } catch (\LogicException $exception) {
            $failure = $exception;
        }

        $this->assertInstanceOf(\LogicException::class, $failure);
        $this->assertStringContainsString($table, $failure->getMessage());
        $this->assertSame(
            $before,
            $this->settlementFixtureOwnedRowCounts(),
            $table . ' validation deleted rows before failing.'
        );
    }

    private function configureWithdrawals(): void
    {
        $managedKeys = array_keys($this->withdrawalConfig());
        $expectedByKey = [];
        foreach ($this->configSnapshot as $row) {
            $expectedByKey[(string) $row['key']] = $row;
        }

        foreach ($this->withdrawalConfig() as $key => $value) {
            // 时间戳必须用整型:秒级 time() 字符串化会让「同一秒内 outer+inner 两次配置」成为
            // 值不变的空更新(affected=0),从而误触发下方所有权守卫;整型在值相同时可被
            // 数组全等比较正确识别并跳过,仅在真正变化时才执行更新。
            $now = time();
            $attributes = [
                'value' => $value,
                'group' => 'withdraw',
                'description' => 'Withdrawal Task 2 fixture',
                'created_at' => $now,
                'updated_at' => $now,
                'deleted_at' => null,
            ];
            $owned = $expectedByKey[$key] ?? null;
            if ($owned === null) {
                $id = DB::table('system_configs')->insertGetId(
                    array_merge(['key' => $key], $attributes)
                );
                $expectedByKey[$key] = array_merge(
                    ['id' => (string) $id, 'key' => $key],
                    $attributes
                );
            } else {
                $expected = array_replace($owned, $attributes);
                if ($expected !== $owned) {
                    $affected = $this->sharedSystemConfigFixtureRowQuery($owned)
                        ->update($attributes);
                    if ($affected !== 1) {
                        throw new \RuntimeException(
                            'Withdrawal config ownership changed before fixture update for key '
                            . $key
                            . '; affected '
                            . $affected
                            . '.'
                        );
                    }
                }
                $expectedByKey[$key] = $expected;
            }

            $this->captureSharedSystemConfigFixtureOwnedState(
                $managedKeys,
                array_values($expectedByKey)
            );
        }
    }

    private function snapshotWithdrawalConfig(): void
    {
        $managedKeys = array_keys($this->withdrawalConfig());
        $this->configSnapshot = $this->readCurrentWithdrawalConfigRows($managedKeys);
        $this->captureSharedSystemConfigFixtureOwnedState($managedKeys, $this->configSnapshot);
    }

    /**
     * @param array<int, string>|null $managedKeys
     * @return array<int, array<string, mixed>>
     */
    private function readCurrentWithdrawalConfigRows(array $managedKeys = null): array
    {
        $managedKeys = $managedKeys ?? array_keys($this->withdrawalConfig());

        return DB::table('system_configs')
            ->useWritePdo()
            ->whereIn('key', $managedKeys)
            ->orderBy('key')
            ->get()
            ->map(static function ($row): array {
                $data = (array) $row;
                // 时间戳统一转整型：configureWithdrawals 的写入属性使用 time() 整型，
                // 若快照保留 PDO 字符串类型，同秒配置会被数组全等比较误判为“已变化”，
                // 进而执行值不变的空更新（affected=0）误触发所有权守卫。
                foreach (['created_at', 'updated_at'] as $column) {
                    if (array_key_exists($column, $data) && $data[$column] !== null) {
                        $data[$column] = (int) $data[$column];
                    }
                }

                return $data;
            })
            ->all();
    }

    private function captureCurrentWithdrawalConfigOwnedState(): void
    {
        $managedKeys = array_keys($this->withdrawalConfig());
        $this->captureSharedSystemConfigFixtureOwnedState(
            $managedKeys,
            $this->readCurrentWithdrawalConfigRows($managedKeys)
        );
    }

    /** @param array<string, mixed> $row */
    private function sharedSystemConfigFixtureRowQuery(array $row)
    {
        $query = DB::table('system_configs')->useWritePdo();
        foreach ($row as $column => $value) {
            if ($value === null) {
                $query->whereNull($column);
            } else {
                $query->where($column, $value);
            }
        }

        return $query;
    }

    private function restoreWithdrawalConfig(bool $restoreAutoIncrement = true): void
    {
        if ($this->configSnapshot === null) {
            return;
        }

        $this->restoreSharedSystemConfigSnapshot(
            array_keys($this->withdrawalConfig()),
            $this->configSnapshot,
            $restoreAutoIncrement
        );
        $this->configSnapshot = null;
    }

    /** @return array<string, string> */
    private function withdrawalConfig(): array
    {
        return [
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
            'withdraw_exchange_rate_cny' => '7.20000000',
        ];
    }

    private function snapshotSettlementFixtureAutoIncrements(): void
    {
        if ($this->settlementFixtureAutoIncrementSnapshot !== null) {
            throw new \LogicException('The settlement fixture AUTO_INCREMENT snapshot already exists.');
        }

        $this->settlementFixtureAutoIncrementSnapshot =
            $this->readSettlementFixtureAutoIncrements();
    }

    private function readSettlementFixtureAutoIncrements(): array
    {
        $connection = DB::connection();
        $connection->statement('SET SESSION information_schema_stats_expiry = 0');

        $autoIncrements = [];
        foreach (self::SETTLEMENT_FIXTURE_AUTO_INCREMENT_TABLES as $table => $_quotedTable) {
            $status = $connection->selectOne(
                'SELECT AUTO_INCREMENT AS auto_increment '
                . 'FROM information_schema.TABLES '
                . 'WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?',
                [$table],
                false
            );
            if (!$status || $status->auto_increment === null) {
                throw new \RuntimeException('Unable to read ' . $table . ' AUTO_INCREMENT.');
            }

            $value = (string) $status->auto_increment;
            if (!ctype_digit($value) || (int) $value < 1) {
                throw new \RuntimeException(
                    'Invalid ' . $table . ' AUTO_INCREMENT value: ' . $value . '.'
                );
            }
            $autoIncrements[$table] = (int) $value;
        }

        return $autoIncrements;
    }

    private function restoreSettlementFixtureAutoIncrements(): void
    {
        $expected = $this->settlementFixtureAutoIncrementSnapshot;
        if ($expected === null) {
            return;
        }
        $connection = DB::connection();
        if ($connection->transactionLevel() !== 0) {
            throw new \LogicException(
                'Refusing settlement fixture AUTO_INCREMENT restore at transaction level '
                . $connection->transactionLevel()
                . '.'
            );
        }
        if (array_keys($expected) !== array_keys(self::SETTLEMENT_FIXTURE_AUTO_INCREMENT_TABLES)) {
            throw new \RuntimeException('The settlement fixture AUTO_INCREMENT snapshot is invalid.');
        }

        $current = $this->readSettlementFixtureAutoIncrements();
        $failureMessages = [];
        $firstFailure = null;
        $targets = [];
        foreach (self::SETTLEMENT_FIXTURE_AUTO_INCREMENT_TABLES as $table => $quotedTable) {
            $maxId = (int) ($connection->table($table)->useWritePdo()->max('id') ?? 0);
            // MySQL cannot lower AUTO_INCREMENT below max(id)+1. Concurrent writers on the
            // shared database may leave higher rows; clamp the restore target accordingly.
            $target = max($expected[$table], $maxId + 1);
            $targets[$table] = $target;
            if ($current[$table] === $target) {
                continue;
            }

            try {
                $connection->statement(
                    'ALTER TABLE ' . $quotedTable . ' AUTO_INCREMENT = ' . $target
                );
            } catch (\Throwable $exception) {
                if ($firstFailure === null) {
                    $firstFailure = $exception;
                }
                $failureMessages[] = $table . ': ' . $exception->getMessage();
            }
        }

        try {
            $actual = $this->readSettlementFixtureAutoIncrements();
            foreach ($targets as $table => $target) {
                if (($actual[$table] ?? null) === $target) {
                    continue;
                }
                // A concurrent insert between ALTER and re-read may advance AI further; accept
                // only when it is still collision-safe (never below max(id)+1). The in-process
                // ALTER may not lower the counter below the session in-memory value, so exact
                // equality with the snapshot target is not required.
                $maxId = (int) ($connection->table($table)->useWritePdo()->max('id') ?? 0);
                $actualValue = (int) ($actual[$table] ?? 0);
                if ($actualValue >= ($maxId + 1)) {
                    continue;
                }
                $failureMessages[] = $table . ' final snapshot mismatch: expected_target='
                    . $target
                    . ', snapshot='
                    . $expected[$table]
                    . ', max_id='
                    . $maxId
                    . ', actual='
                    . $actualValue;
            }
        } catch (\Throwable $exception) {
            if ($firstFailure === null) {
                $firstFailure = $exception;
            }
            $failureMessages[] = 'final snapshot verification failed: ' . $exception->getMessage();
        }

        if ($failureMessages !== []) {
            throw new \RuntimeException(
                'Settlement fixture AUTO_INCREMENT restore failed: '
                . implode(' | ', $failureMessages),
                0,
                $firstFailure
            );
        }

        $this->settlementFixtureAutoIncrementSnapshot = null;
    }

    private function cleanupFixtureRows(): void
    {
        $plan = $this->buildSettlementFixtureCleanupPlan();

        foreach ([
            'withdraw_settlement_outbox',
            'withdraw_records',
            'user_trades',
            'user_auths',
            'user_infos',
            'user_logins',
        ] as $table) {
            foreach ($plan[$table] as $signature) {
                $this->deleteSettlementFixtureRowBySignature($table, $signature);
            }
        }
    }

    /**
     * @param array<string, mixed> $signature
     */
    private function deleteSettlementFixtureRowBySignature(
        string $table,
        array $signature
    ): void {
        if (!array_key_exists('id', $signature)) {
            throw new \LogicException(
                'Settlement fixture cleanup signature has no id for ' . $table . '.'
            );
        }
        $id = $signature['id'];
        $query = DB::table($table)->useWritePdo()->where('id', $id);
        foreach ($signature as $column => $value) {
            if ($column === 'id') {
                continue;
            }
            if ($value === null) {
                $query->whereNull($column);
            } else {
                $query->where($column, $value);
            }
        }

        $affected = $query->delete();
        if ($affected !== 1) {
            throw new \LogicException(
                'Settlement fixture cleanup ownership changed for '
                . $table
                . ' id '
                . (string) $id
                . '; affected '
                . $affected
                . '.'
            );
        }
    }

    /** @return array<string, mixed> */
    private function settlementFixtureRowSignature(object $row): array
    {
        $signature = (array) $row;
        ksort($signature);

        return $signature;
    }

    /** @return array<string, array<int, array<string, mixed>>> */
    private function buildSettlementFixtureCleanupPlan(): array
    {
        $plan = [
            'user_auths' => [],
            'user_infos' => [],
            'user_logins' => [],
            'user_trades' => [],
            'withdraw_records' => [],
            'withdraw_settlement_outbox' => [],
        ];
        $expectedEmail = 'withdraw-task2-' . self::USER_ID . '@example.test';
        $logins = DB::table('user_logins')
            ->useWritePdo()
            ->where(static function ($query): void {
                $query->where('user_id', self::USER_ID)
                    ->orWhere('email', 'like', 'withdraw-task2-%@example.test');
            })
            ->orderBy('id')
            ->get();
        $validatedLoginIds = [];
        foreach ($logins as $login) {
            if ((int) $login->user_id !== self::USER_ID) {
                throw new \LogicException(
                    'Refusing to delete a foreign fixture marker in user_logins id '
                    . (int) $login->id
                    . '.'
                );
            }
            if ((string) $login->email !== $expectedEmail) {
                throw new \LogicException(
                    'Refusing to delete a non-fixture user_logins candidate id '
                    . (int) $login->id
                    . '.'
                );
            }
            $validatedLoginIds[] = (int) $login->id;
            $plan['user_logins'][] = $this->settlementFixtureRowSignature($login);
        }

        $infos = DB::table('user_infos')
            ->useWritePdo()
            ->where('user_id', self::USER_ID)
            ->orderBy('id')
            ->get();
        foreach ($infos as $info) {
            if ((string) $info->user_name !== 'withdraw-task2-user'
                || !in_array((int) $info->login_id, $validatedLoginIds, true)) {
                throw new \LogicException(
                    'Refusing to delete a non-fixture user_infos candidate id '
                    . (int) $info->id
                    . '.'
                );
            }
            $plan['user_infos'][] = $this->settlementFixtureRowSignature($info);
        }

        $auths = DB::table('user_auths')
            ->useWritePdo()
            ->where('user_id', self::USER_ID)
            ->orderBy('id')
            ->get();
        foreach ($auths as $auth) {
            $bankNo = (string) $auth->bank_no;
            $bankName = (string) $auth->bank_name;
            $bankAddress = (string) $auth->bank_addr;
            $validBankNo = trim($bankNo) === ''
                || in_array($bankNo, ['TASK2-BANK-001', 'TASK2-BANK-002'], true);
            $validBankName = trim($bankName) === '' || $bankName === 'Task 2 Bank';
            $validBankAddress = trim($bankAddress) === '' || $bankAddress === 'Task 2 Branch';
            if ((string) $auth->id_card_no !== 'ID' . self::USER_ID
                || !$validBankNo
                || !$validBankName
                || !$validBankAddress) {
                throw new \LogicException(
                    'Refusing to delete a non-fixture user_auths candidate id '
                    . (int) $auth->id
                    . '.'
                );
            }
            $plan['user_auths'][] = $this->settlementFixtureRowSignature($auth);
        }

        $trades = DB::table('user_trades')
            ->useWritePdo()
            ->where('user_id', self::USER_ID)
            ->orderBy('id')
            ->get();
        foreach ($trades as $trade) {
            if ((int) $trade->ticket !== 412372991 || (string) $trade->symbol !== 'EURUSD') {
                throw new \LogicException(
                    'Refusing to delete a non-fixture user_trades candidate id '
                    . (int) $trade->id
                    . '.'
                );
            }
            $plan['user_trades'][] = $this->settlementFixtureRowSignature($trade);
        }

        $withdrawals = DB::table('withdraw_records')
            ->useWritePdo()
            ->where('user_id', self::USER_ID)
            ->orderBy('id')
            ->get();
        $withdrawalsById = [];
        foreach ($withdrawals as $withdrawal) {
            $bankNo = (string) $withdrawal->bank_no;
            if ((string) $withdrawal->user_name !== 'withdraw-task2-user'
                || (string) $withdrawal->created_by !== 'withdraw-task2-user'
                || strpos((string) $withdrawal->local_order_no, 'WDR') !== 0
                || !in_array($bankNo, ['TASK2-BANK-001', 'TASK2-BANK-002'], true)
                || (string) $withdrawal->bank_name !== 'Task 2 Bank'
                || (string) $withdrawal->bank_addr !== 'Task 2 Branch') {
                throw new \LogicException(
                    'Refusing to delete a non-fixture withdraw_records candidate id '
                    . (int) $withdrawal->id
                    . '.'
                );
            }
            $withdrawalId = (int) $withdrawal->id;
            $withdrawalsById[$withdrawalId] = $withdrawal;
            $plan['withdraw_records'][] = $this->settlementFixtureRowSignature($withdrawal);
        }

        $validatedWithdrawalIds = array_keys($withdrawalsById);
        if ($validatedWithdrawalIds !== []) {
            $outboxRows = DB::table('withdraw_settlement_outbox')
                ->useWritePdo()
                ->whereIn('withdraw_record_id', $validatedWithdrawalIds)
                ->orderBy('id')
                ->get();
            foreach ($outboxRows as $outbox) {
                $withdrawalId = (int) $outbox->withdraw_record_id;
                $withdrawal = $withdrawalsById[$withdrawalId] ?? null;
                if ($withdrawal === null
                    || (string) $outbox->event_type !== 'withdraw_debit'
                    || (string) $outbox->local_order_no !== (string) $withdrawal->local_order_no) {
                    throw new \LogicException(
                        'Refusing to delete a non-fixture withdraw_settlement_outbox candidate id '
                        . (int) $outbox->id
                        . '.'
                    );
                }
                $plan['withdraw_settlement_outbox'][] =
                    $this->settlementFixtureRowSignature($outbox);
            }
        }

        return $plan;
    }

    private function assertFixtureLockWasReleased(): void
    {
        if (!$this->fixtureLockObserver || !$this->fixtureLockObserverName) {
            return;
        }

        $observer = $this->fixtureLockObserver;
        $lockName = $this->fixtureLockObserverName;
        $this->fixtureLockObserver = null;
        $this->fixtureLockObserverName = null;
        $acquired = null;
        try {
            $acquired = $observer->selectOne(
                'SELECT GET_LOCK(?, 0) AS acquired',
                [$lockName],
                false
            );
            $this->assertSame(1, (int) $acquired->acquired);
        } finally {
            if ($acquired && (int) $acquired->acquired === 1) {
                $observer->selectOne(
                    'SELECT RELEASE_LOCK(?) AS released',
                    [$lockName],
                    false
                );
            }
            $observer->disconnect();
        }
    }

    private function insertUser(): void
    {
        $loginId = DB::table('user_logins')->insertGetId([
            'user_id' => self::USER_ID,
            'email' => 'withdraw-task2-' . self::USER_ID . '@example.test',
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
            'user_id' => self::USER_ID,
            'login_id' => $loginId,
            'user_name' => 'withdraw-task2-user',
            'phone' => '13937200001',
            'gender' => 1,
            'account_type' => 2,
            'parent_id' => 0,
            'family_tree' => '',
            'group_id' => 0,
            'level_id' => 0,
            'comm_rate' => 0,
            'auth_status' => 1,
            'total_funds' => '9999.00',
            'used_margin' => '0.00',
            'avail_margin' => '9999.00',
            'equity' => '9999.00',
            'effective_credit' => '0.00',
            'risk_ratio' => '0.00',
            'leverage' => 100,
            'is_ecn' => 0,
            'is_withdrawal_allowed' => 0,
            'is_deposit_allowed' => 0,
            'created_at' => time(),
            'updated_at' => time(),
            'deleted_at' => null,
        ]);
    }

    private function insertApprovedBank(): void
    {
        DB::table('user_auths')->insert([
            'user_id' => self::USER_ID,
            'bank_no' => 'TASK2-BANK-001',
            'bank_name' => 'Task 2 Bank',
            'bank_card_img' => '',
            'bank_card_img_tmp' => '',
            'bank_addr' => 'Task 2 Branch',
            'bank_addr_tmp' => '',
            'bank_status' => 2,
            'bank_remarks' => '',
            'id_card_no' => 'ID' . self::USER_ID,
            'id_card_status' => 2,
            'id_card_front' => '',
            'id_card_back' => '',
            'id_card_remarks' => '',
            'is_bank_synced' => 0,
            'created_at' => time(),
            'updated_at' => time(),
            'deleted_at' => null,
        ]);
    }
}

final class FrontWithdrawSettlementClosureSetupFailureHarness extends FrontWithdrawSettlementClosureModuleTest
{
    /**
     * SetupFailureHarness 的独立哨兵连接。模拟 setUp 失败后，用例通过它 GET_LOCK 验证夹具锁确实已释放，
     * 并插入 user_trades 哨兵行验证外层 tearDown 不会触碰清理完成后的数据。
     * @var \Illuminate\Database\Connection|null
     */
    public $sentinelConnection;

    /**
     * 哨兵连接插入的 user_trades 行主键；cleanupSentinel 据此精确删除哨兵行。
     * @var int|null
     */
    public $sentinelTradeId;

    /**
     * 哨兵行占用的 ticket（取当时最大 ticket+1）；与 id、user_id 一起构成哨兵行的删除定位键。
     * @var int|null
     */
    public $sentinelTradeTicket;

    /**
     * 锁释放瞬间 user_trades 的 AUTO_INCREMENT 读数。cleanupSentinel 据此把表自增恢复到哨兵插入前的值，
     * 消除哨兵行对共享库自增计数的影响。
     * @var int|null
     */
    public $sentinelAutoIncrement;

    /**
     * harness 主体是否执行过。断言为 false，证明 setUp 失败后用例主体绝不运行。
     * @var bool
     */
    public $bodyRan = false;

    /**
     * releaseSharedSystemConfigFixtureLock 的调用次数。断言为 1，证明清理路径只释放一次夹具锁。
     * @var int
     */
    public $releaseCalls = 0;

    /**
     * 哨兵是否已成功 GET_LOCK 夹具锁名。cleanupSentinel 只在拿到过锁时才执行 RELEASE_LOCK。
     * @var bool
     */
    private $sentinelLockAcquired = false;

    /**
     * harness 覆写的夹具锁名（构造时注入的唯一值），保证哨兵与外层用例互不干扰。
     * @var string
     */
    private $harnessLockName;

    public function __construct(string $name, string $lockName)
    {
        $this->harnessLockName = $lockName;
        parent::__construct($name);
    }

    public function body(): void
    {
        $this->bodyRan = true;
    }

    protected function sharedSystemConfigFixtureAdvisoryLockName(): string
    {
        return $this->harnessLockName;
    }

    protected function acquireSharedSystemConfigFixtureLock(): void
    {
        parent::acquireSharedSystemConfigFixtureLock();

        $connectionName = 'withdraw_task2_setup_failure_sentinel_' . substr(
            hash('sha256', spl_object_hash($this)),
            0,
            24
        );
        config([
            'database.connections.' . $connectionName => config(
                'database.connections.' . DB::getDefaultConnection()
            ),
        ]);
        DB::purge($connectionName);
        $this->sentinelConnection = DB::connection($connectionName);
        $this->sentinelConnection->unsetEventDispatcher();
        $this->sentinelConnection->statement(
            'SET SESSION information_schema_stats_expiry = 0'
        );
        $status = $this->sentinelConnection->selectOne(
            'SELECT AUTO_INCREMENT AS auto_increment '
            . 'FROM information_schema.TABLES '
            . 'WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?',
            ['user_trades'],
            false
        );
        if (!$status || $status->auto_increment === null) {
            throw new \RuntimeException('Unable to snapshot sentinel AUTO_INCREMENT.');
        }
        $this->sentinelAutoIncrement = (int) $status->auto_increment;

        throw new \RuntimeException('simulated setup failure after lock acquisition');
    }

    protected function releaseSharedSystemConfigFixtureLock(): void
    {
        $hadState = $this->hasSharedSystemConfigFixtureLockState();
        parent::releaseSharedSystemConfigFixtureLock();
        ++$this->releaseCalls;

        if (!$hadState || $this->sentinelLockAcquired || $this->sentinelConnection === null) {
            return;
        }

        $acquired = $this->sentinelConnection->selectOne(
            'SELECT GET_LOCK(?, 0) AS acquired',
            [$this->harnessLockName],
            false
        );
        if (!$acquired || (int) $acquired->acquired !== 1) {
            throw new \RuntimeException('The setup failure sentinel could not acquire its lock.');
        }
        $this->sentinelLockAcquired = true;
        $this->sentinelTradeTicket =
            (int) $this->sentinelConnection->table('user_trades')->max('ticket') + 1;
        $this->sentinelTradeId = $this->sentinelConnection->table('user_trades')->insertGetId([
            'user_id' => 412372001,
            'ticket' => $this->sentinelTradeTicket,
            'symbol' => 'SETUPFAIL',
            'digits' => 5,
            'cmd' => 0,
            'volume' => 100,
            'open_time' => '2026-07-17 09:00:00',
            'open_price' => 1.1,
            'close_time' => '1970-01-01 00:00:00',
            'modify_time' => '2026-07-17 09:00:00',
            'created_at' => time(),
            'updated_at' => time(),
            'deleted_at' => null,
        ]);
    }

    public function cleanupSentinel(): void
    {
        $connection = $this->sentinelConnection;
        if ($connection === null) {
            return;
        }

        $failures = [];
        $firstFailure = null;
        try {
            if ($this->sentinelTradeId !== null && $this->sentinelTradeTicket !== null) {
                $connection->table('user_trades')
                    ->where('id', $this->sentinelTradeId)
                    ->where('user_id', 412372001)
                    ->where('ticket', $this->sentinelTradeTicket)
                    ->delete();
            }
            if ($this->sentinelAutoIncrement !== null) {
                $connection->statement(
                    'ALTER TABLE `user_trades` AUTO_INCREMENT = '
                    . $this->sentinelAutoIncrement
                );
                $connection->statement('SET SESSION information_schema_stats_expiry = 0');
                $status = $connection->selectOne(
                    'SELECT AUTO_INCREMENT AS auto_increment '
                    . 'FROM information_schema.TABLES '
                    . 'WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?',
                    ['user_trades'],
                    false
                );
                if (!$status || (int) $status->auto_increment !== $this->sentinelAutoIncrement) {
                    throw new \RuntimeException('Sentinel AUTO_INCREMENT restore mismatch.');
                }
            }
        } catch (\Throwable $exception) {
            $firstFailure = $exception;
            $failures[] = 'row/AUTO_INCREMENT cleanup: ' . $exception->getMessage();
        }

        try {
            if ($this->sentinelLockAcquired) {
                $released = $connection->selectOne(
                    'SELECT RELEASE_LOCK(?) AS released',
                    [$this->harnessLockName],
                    false
                );
                if (!$released || (int) $released->released !== 1) {
                    throw new \RuntimeException('Sentinel advisory lock release failed.');
                }
            }
        } catch (\Throwable $exception) {
            if ($firstFailure === null) {
                $firstFailure = $exception;
            }
            $failures[] = 'lock cleanup: ' . $exception->getMessage();
        } finally {
            $connection->disconnect();
            $this->sentinelConnection = null;
            $this->sentinelLockAcquired = false;
        }

        if ($failures !== []) {
            throw new \RuntimeException(
                'Setup failure harness cleanup failed: ' . implode(' | ', $failures),
                0,
                $firstFailure
            );
        }
    }
}

final class FrontWithdrawSettlementClosureParentSetUpFailureHarness extends FrontWithdrawSettlementClosureModuleTest
{
    /**
     * harness 主体是否执行过。断言为 false，证明 parent::setUp() 失败时用例主体不会运行。
     * @var bool
     */
    public $bodyRan = false;

    public function setUp(): void
    {
        throw new \RuntimeException('simulated parent setup failure');
    }

    public function body(): void
    {
        $this->bodyRan = true;
    }
}

final class FrontWithdrawSettlementClosureForeignMarkerExceptionHarness extends FrontWithdrawSettlementClosureModuleTest
{
    /**
     * 通过 DB::listen 捕获的外部标记行 email（withdraw-task2-foreign-* 前缀）。
     * 用于确认清理失败前确实存在一个不属于本夹具的标记行。
     * @var string|null
     */
    public $capturedEmail;

    /**
     * 从捕获的 email 中解析出的外部标记行 user_id，供断言使用。
     * @var int|null
     */
    public $capturedUserId;

    /**
     * 是否已注入模拟失败。DB::listen 里只注入一次，避免同一次运行多次抛出干扰清理流程。
     * @var bool
     */
    public $failureInjected = false;

    /**
     * 外部标记行是否已插入。标记后监听器才会进入第二阶段，对标记行计数查询抛出模拟失败。
     * @var bool
     */
    private $markerInserted = false;

    /**
     * harness 覆写的夹具锁名（构造时注入的唯一值），隔离嵌套 harness 与外层用例。
     * @var string
     */
    private $harnessLockName;

    public function __construct(string $name, string $lockName)
    {
        $this->harnessLockName = $lockName;
        parent::__construct($name);
    }

    protected function sharedSystemConfigFixtureAdvisoryLockName(): string
    {
        return $this->harnessLockName;
    }

    protected function setUp(): void
    {
        parent::setUp();

        DB::listen(function ($query): void {
            $sql = strtolower((string) $query->sql);
            if (!$this->markerInserted && strpos($sql, 'insert into `user_logins`') !== false) {
                foreach ($query->bindings as $binding) {
                    if (is_string($binding)
                        && strpos($binding, 'withdraw-task2-foreign-') === 0) {
                        $this->capturedEmail = $binding;
                        if (preg_match('/foreign-(\d+)@/', $binding, $matches) === 1) {
                            $this->capturedUserId = (int) $matches[1];
                        }
                        $this->markerInserted = true;
                        break;
                    }
                }

                return;
            }

            if ($this->markerInserted
                && !$this->failureInjected
                && strpos($sql, 'select count(*) as aggregate from `user_logins`') !== false) {
                $this->failureInjected = true;
                throw new \RuntimeException('simulated foreign marker count failure');
            }
        });
    }

    public function test_settlement_cleanup_fails_fast_for_a_foreign_fixture_marker_without_deletes(): void
    {
        $foreignUserId = (int) DB::table('user_logins')->useWritePdo()->max('user_id') + 1;
        if ($foreignUserId === self::USER_ID) {
            ++$foreignUserId;
        }
        $foreignEmail = 'withdraw-task2-foreign-' . $foreignUserId . '@example.test';
        $foreignLoginId = DB::table('user_logins')->insertGetId([
            'user_id' => $foreignUserId,
            'email' => $foreignEmail,
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
        DB::table('user_logins')
            ->where('id', $foreignLoginId)
            ->where('user_id', $foreignUserId)
            ->where('email', $foreignEmail)
            ->delete();
        DB::table('user_logins')
            ->where('email', 'like', 'withdraw-task2-%@example.test')
            ->count();
    }
}

final class FrontWithdrawSettlementClosureCollisionExceptionHarness extends FrontWithdrawSettlementClosureModuleTest
{
    /**
     * 被测夹具 user_logins 行的主键。测试把该行 email 改成真实客户样式后触发清理，
     * 断言清理对疑似非夹具数据失败关闭；id 用于失败后精确还原。
     * @var int|null
     */
    public $fixtureLoginId;

    /**
     * 夹具行原始 email 快照；测试注入冲突失败后用它还原，保证外层夹具不受污染。
     * @var string|null
     */
    public $fixtureLoginEmail;

    /**
     * 夹具 user_infos 行主键，仅用于确认夹具行存在，缺失时快速失败。
     * @var int|null
     */
    public $fixtureInfoId;

    /**
     * 夹具 user_infos 行的 user_name 快照，供诊断信息使用。
     * @var string|null
     */
    public $fixtureUserName;

    /**
     * 是否已对 user_logins 更新注入模拟失败；只注入一次，保证失败恰好发生在冲突标记更新上。
     * @var bool
     */
    public $failureInjected = false;

    /**
     * harness 覆写的夹具锁名（构造时注入的唯一值），隔离嵌套 harness 与外层用例。
     * @var string
     */
    private $harnessLockName;

    public function __construct(string $name, string $lockName)
    {
        $this->harnessLockName = $lockName;
        parent::__construct($name);
    }

    protected function sharedSystemConfigFixtureAdvisoryLockName(): string
    {
        return $this->harnessLockName;
    }

    /**
     * 嵌套 harness 不拥有外层生命周期的配置夹具行，跳过配置标记清空断言。
     *
     * @return bool 固定返回 false。
     */
    protected function assertSettlementConfigFixtureMarkersAbsent(): bool
    {
        return false;
    }

    protected function setUp(): void
    {
        parent::setUp();

        $login = DB::table('user_logins')->useWritePdo()
            ->where('user_id', 412372001)
            ->first();
        $info = DB::table('user_infos')->useWritePdo()
            ->where('user_id', 412372001)
            ->first();
        if (!$login || !$info) {
            throw new \RuntimeException('Collision exception harness fixture is unavailable.');
        }
        $this->fixtureLoginId = (int) $login->id;
        $this->fixtureLoginEmail = (string) $login->email;
        $this->fixtureInfoId = (int) $info->id;
        $this->fixtureUserName = (string) $info->user_name;

        DB::listen(function ($query): void {
            if ($this->failureInjected
                || strpos(strtolower((string) $query->sql), 'update `user_logins`') === false
                || !in_array('real-customer@example.test', $query->bindings, true)) {
                return;
            }

            $this->failureInjected = true;
            throw new \RuntimeException('simulated collision marker update failure');
        });
    }

    public function test_settlement_cleanup_fails_fast_for_a_non_fixture_user_collision(): void
    {
        try {
            DB::table('user_logins')
                ->where('id', $this->fixtureLoginId)
                ->where('user_id', self::USER_ID)
                ->update(['email' => 'real-customer@example.test']);
        } catch (\Throwable $exception) {
            DB::table('user_logins')
                ->where('id', $this->fixtureLoginId)
                ->where('user_id', self::USER_ID)
                ->update(['email' => $this->fixtureLoginEmail]);
            throw $exception;
        }
    }
}

final class FrontWithdrawSettlementClosureTransactionLeakHarness extends FrontWithdrawSettlementClosureModuleTest
{
    /**
     * body 内使用的默认写连接。用例故意在其上保留两层未提交事务，
     * 断言夹具生命周期清理能把 transactionLevel 归零。
     * @var \Illuminate\Database\Connection|null
     */
    public $writeConnection;

    /**
     * 在未提交事务内插入的 withdraw_records 哨兵行主键；事务回滚后该行不应存在，
     * 用于证明泄漏事务被清理流程正确回滚。
     * @var int|null
     */
    public $sentinelId;

    /**
     * system_configs 哨兵行主键。插入后立即删除以推进 AUTO_INCREMENT，
     * 供断言“自增读数来自锁连接且已前进”；清理阶段据此删除残留。
     * @var int|null
     */
    public $systemConfigSentinelId;

    /**
     * 哨兵 withdraw_records 行使用的固定 user_id，与夹具 USER_ID 错开（…099），
     * 避免哨兵行被误认为夹具用户数据。
     * @var int
     */
    public $sentinelUserId = 412372099;

    /**
     * 哨兵行的 local_order_no（由锁名哈希派生的唯一值），同时充当 idempotency_key，
     * 保证哨兵行与真实夹具订单无键冲突。
     * @var string
     */
    public $sentinelLocalOrderNo;

    /**
     * system_configs 哨兵行的 key（由锁名哈希派生的唯一值），防止与其他用例的配置行冲突。
     * @var string
     */
    public $systemConfigSentinelKey;

    /**
     * harness 主体是否执行过。断言为 true，证明泄漏事务的场景确实被构造出来。
     * @var bool
     */
    public $bodyRan = false;

    /**
     * harness 覆写的夹具锁名（构造时注入的唯一值），隔离嵌套 harness 与外层用例。
     * @var string
     */
    private $harnessLockName;

    public function __construct(string $name, string $lockName)
    {
        $this->harnessLockName = $lockName;
        $this->sentinelLocalOrderNo = 'WDR-TX-' . substr(hash('sha256', $lockName), 0, 24);
        $this->systemConfigSentinelKey = 'transaction-cleanup-ai-'
            . substr(hash('sha256', $lockName), 0, 20);
        parent::__construct($name);
    }

    protected function sharedSystemConfigFixtureAdvisoryLockName(): string
    {
        return $this->harnessLockName;
    }

    /**
     * 嵌套 harness 不拥有外层生命周期的配置夹具行，跳过配置标记清空断言。
     *
     * @return bool 固定返回 false。
     */
    protected function assertSettlementConfigFixtureMarkersAbsent(): bool
    {
        return false;
    }

    public function testLeavesNestedTransactionsForLifecycleCleanup(): void
    {
        $this->body();
    }

    public function body(): void
    {
        $this->writeConnection = DB::connection();
        $this->systemConfigSentinelId = $this->writeConnection
            ->table('system_configs')
            ->insertGetId([
                'key' => $this->systemConfigSentinelKey,
                'value' => 'transaction-cleanup-sentinel',
                'group' => 'withdraw',
                'description' => 'Transaction cleanup AUTO_INCREMENT sentinel',
                'created_at' => time(),
                'updated_at' => time(),
                'deleted_at' => null,
            ]);
        $deleted = $this->writeConnection
            ->table('system_configs')
            ->where('id', $this->systemConfigSentinelId)
            ->where('key', $this->systemConfigSentinelKey)
            ->where('description', 'Transaction cleanup AUTO_INCREMENT sentinel')
            ->delete();
        if ($deleted !== 1) {
            throw new \RuntimeException(
                'Transaction cleanup system config sentinel was not deleted.'
            );
        }
        $this->writeConnection->beginTransaction();
        $this->writeConnection->beginTransaction();
        $this->sentinelId = $this->writeConnection->table('withdraw_records')->insertGetId([
            'user_id' => $this->sentinelUserId,
            'user_name' => 'transaction-sentinel',
            'mt4_ticket' => '',
            'apply_amount' => '10.00',
            'actual_amount' => '10.00',
            'fee' => '0.00',
            'exchange_rate' => '1.00000000',
            'rmb_fee' => '0.00',
            'bank_no' => 'TRANSACTION-SENTINEL',
            'bank_name' => 'Transaction Sentinel',
            'bank_addr' => 'Transaction Sentinel',
            'status' => 0,
            'local_order_no' => $this->sentinelLocalOrderNo,
            'third_order_no' => '',
            'reject_reason' => '',
            'mt4_return_status' => '',
            'idempotency_key' => strtolower($this->sentinelLocalOrderNo),
            'funding_status' => 'pending',
            'funding_payload_hash' => hash('sha256', $this->sentinelLocalOrderNo),
            'created_by' => 'transaction-sentinel',
            'updated_by' => '',
            'created_at' => time(),
            'updated_at' => time(),
            'deleted_at' => null,
        ]);
        $this->bodyRan = true;

        $this->assertSame(2, $this->writeConnection->transactionLevel());
    }
}

final class FrontWithdrawSettlementClosureRollbackFailureHarness extends FrontWithdrawSettlementClosureModuleTest
{
    /**
     * 被监听的默认写连接。回滚失败注入后仍断言其 transactionLevel 为 0，
     * 证明清理流程即使回滚事件抛错也能完成事务收尾。
     * @var \Illuminate\Database\Connection|null
     */
    public $writeConnection;

    /**
     * 夹具锁与 AUTO_INCREMENT 读数所在的连接。挂上查询监听器捕获锁连接上的 ALTER TABLE 语句，
     * 断言清理阶段没有对锁连接做任何 DDL。
     * @var \Illuminate\Database\Connection|null
     */
    public $lockConnection;

    /**
     * harness 主体是否执行过。断言为 true，证明回滚失败场景确实被构造出来。
     * @var bool
     */
    public $bodyRan = false;

    /**
     * 是否已对 TransactionRolledBack 事件注入模拟失败；只注入一次，失败后监听器直接放行。
     * @var bool
     */
    public $rollbackFailureInjected = false;

    /**
     * beforeApplicationDestroyed 钩子是否执行，等价于父类 tearDown 已运行。
     * 断言为 true，证明回滚失败没有阻断父类清理。
     * @var bool
     */
    public $parentTeardownRan = false;

    /**
     * releaseSharedSystemConfigFixtureLock 的调用次数。断言为 1，证明锁只释放一次。
     * @var int
     */
    public $releaseCalls = 0;

    /**
     * 写连接上捕获的夹具表 DELETE 语句。回滚失败场景下应为空——
     * 回滚已让事务内数据消失，清理不得再产生真实删除。
     * @var array<int, string>
     */
    public $cleanupDeleteStatements = [];

    /**
     * 写连接上捕获的 ALTER TABLE 语句。断言为空，证明自增恢复只读、未在写连接上执行 DDL。
     * @var array<int, string>
     */
    public $autoIncrementDdlStatements = [];

    /**
     * 锁连接上捕获的 ALTER TABLE 语句。断言为空，证明锁连接只用于加锁与读自增。
     * @var array<int, string>
     */
    public $lockConnectionAutoIncrementDdlStatements = [];

    /**
     * system_configs 在写连接上的增删改语句。断言为空，证明配置恢复未产生额外变更。
     * @var array<int, string>
     */
    public $configMutationStatements = [];

    /**
     * body 开始前 system_configs 的 AUTO_INCREMENT 基线，由 readSharedSystemConfigFixtureAutoIncrement 首次调用捕获。
     * @var int|null
     */
    public $systemConfigAutoIncrementSnapshot;

    /**
     * 哨兵行插入并删除后的 system_configs AUTO_INCREMENT 读数；必须大于基线，
     * 证明哨兵确实推进了自增、且读数取自锁连接。
     * @var int|null
     */
    public $systemConfigAutoIncrementAfterSentinel;

    /**
     * system_configs 哨兵行主键；外层 finally 据此删除哨兵残留。
     * @var int|null
     */
    public $systemConfigSentinelId;

    /**
     * system_configs 哨兵行的 key（由锁名哈希派生），构造时生成保证唯一。
     * @var string
     */
    public $systemConfigSentinelKey;

    /**
     * harness 覆写的夹具锁名（构造时注入的唯一值），隔离嵌套 harness 与外层用例。
     * @var string
     */
    private $harnessLockName;

    public function __construct(string $name, string $lockName)
    {
        $this->harnessLockName = $lockName;
        $this->systemConfigSentinelKey = 'rollback-ai-'
            . substr(hash('sha256', $lockName), 0, 24);
        parent::__construct($name);
    }

    protected function sharedSystemConfigFixtureAdvisoryLockName(): string
    {
        return $this->harnessLockName;
    }

    /**
     * 嵌套 harness 不拥有外层生命周期的配置夹具行，跳过配置标记清空断言。
     *
     * @return bool 固定返回 false。
     */
    protected function assertSettlementConfigFixtureMarkersAbsent(): bool
    {
        return false;
    }

    protected function readSharedSystemConfigFixtureAutoIncrement($connection): int
    {
        if ($this->lockConnection === null) {
            $this->lockConnection = $connection;
            $dispatcher = new \Illuminate\Events\Dispatcher();
            $dispatcher->listen(
                \Illuminate\Database\Events\QueryExecuted::class,
                function ($event): void {
                    if ($event->connection !== $this->lockConnection) {
                        return;
                    }
                    $sql = strtolower(ltrim((string) $event->sql));
                    if (strpos($sql, 'alter table ') === 0) {
                        $this->lockConnectionAutoIncrementDdlStatements[] = $sql;
                    }
                }
            );
            $connection->setEventDispatcher($dispatcher);
        }

        $autoIncrement = parent::readSharedSystemConfigFixtureAutoIncrement($connection);
        if ($this->systemConfigAutoIncrementSnapshot === null) {
            $this->systemConfigAutoIncrementSnapshot = $autoIncrement;
        }

        return $autoIncrement;
    }

    protected function releaseSharedSystemConfigFixtureLock(): void
    {
        try {
            parent::releaseSharedSystemConfigFixtureLock();
        } finally {
            if ($this->lockConnection !== null) {
                $this->lockConnection->unsetEventDispatcher();
            }
            ++$this->releaseCalls;
        }
    }

    public function testRollbackFailureAfterTransactionLevelReachesZero(): void
    {
        $this->body();
    }

    public function body(): void
    {
        $this->writeConnection = DB::connection();
        $this->systemConfigSentinelId = $this->writeConnection
            ->table('system_configs')
            ->insertGetId([
                'key' => $this->systemConfigSentinelKey,
                'value' => 'rollback-failure-sentinel',
                'group' => 'withdraw',
                'description' => 'Rollback failure AUTO_INCREMENT sentinel',
                'created_at' => time(),
                'updated_at' => time(),
                'deleted_at' => null,
            ]);
        $deleted = $this->writeConnection
            ->table('system_configs')
            ->where('id', $this->systemConfigSentinelId)
            ->where('key', $this->systemConfigSentinelKey)
            ->where('description', 'Rollback failure AUTO_INCREMENT sentinel')
            ->delete();
        if ($deleted !== 1) {
            throw new \RuntimeException('Rollback failure system config sentinel was not deleted.');
        }
        if ($this->lockConnection === null
            || $this->systemConfigAutoIncrementSnapshot === null) {
            throw new \RuntimeException('Rollback failure lock connection snapshot is unavailable.');
        }
        $this->systemConfigAutoIncrementAfterSentinel =
            parent::readSharedSystemConfigFixtureAutoIncrement($this->lockConnection);
        if ($this->systemConfigAutoIncrementAfterSentinel
            <= $this->systemConfigAutoIncrementSnapshot) {
            throw new \RuntimeException(
                'Rollback failure system config AUTO_INCREMENT did not advance.'
            );
        }

        $dispatcher = $this->writeConnection->getEventDispatcher();
        if ($dispatcher === null) {
            throw new \RuntimeException('Rollback failure harness event dispatcher is unavailable.');
        }
        $dispatcher->listen(
            \Illuminate\Database\Events\QueryExecuted::class,
            function ($event): void {
                if ($event->connection !== $this->writeConnection) {
                    return;
                }
                $sql = strtolower(ltrim((string) $event->sql));
                $tables = '(user_auths|user_infos|user_logins|user_trades|'
                    . 'withdraw_records|withdraw_settlement_outbox)';
                if (preg_match('/^delete from `' . $tables . '`/', $sql) === 1) {
                    $this->cleanupDeleteStatements[] = $sql;
                }
                if (strpos($sql, 'alter table ') === 0) {
                    $this->autoIncrementDdlStatements[] = $sql;
                }
                if (strpos($sql, '`system_configs`') !== false
                    && preg_match('/^(delete|insert|update) /', $sql) === 1) {
                    $this->configMutationStatements[] = $sql;
                }
            }
        );
        $dispatcher->listen(
            \Illuminate\Database\Events\TransactionRolledBack::class,
            function ($event): void {
                if ($event->connection !== $this->writeConnection
                    || $this->rollbackFailureInjected) {
                    return;
                }
                $this->rollbackFailureInjected = true;
                throw new \RuntimeException('simulated rollback event failure');
            }
        );
        $this->beforeApplicationDestroyed(function (): void {
            $this->parentTeardownRan = true;
        });
        $this->bodyRan = true;
        $this->writeConnection->beginTransaction();

        $this->assertSame(1, $this->writeConnection->transactionLevel());
    }
}
